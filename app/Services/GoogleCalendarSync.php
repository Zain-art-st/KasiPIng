<?php

namespace App\Services;

use App\Models\GoogleConnection;
use App\Models\Meeting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

class GoogleCalendarSync
{
    public function run(): string
    {
        $connection = GoogleConnection::first();

        if (! $connection) {
            return 'No Google Calendar is connected yet.';
        }

        try {
            $summary = $this->sync($connection);
            $connection->update(['last_synced_at' => now(), 'last_error' => null]);

            return $summary;
        } catch (\Throwable $e) {
            $connection->update(['last_error' => mb_substr($e->getMessage(), 0, 500)]);

            throw $e;
        }
    }

    private function sync(GoogleConnection $connection): string
    {
        $token = $this->freshAccessToken($connection);

        $created = 0;
        $updated = 0;
        $cancelled = 0;
        $pageToken = null;

        do {
            $response = Http::withToken($token)
                ->acceptJson()
                ->get('https://www.googleapis.com/calendar/v3/calendars/primary/events', array_filter([
                    'timeMin' => now()->subDay()->toRfc3339String(),
                    'timeMax' => now()->addDays(60)->toRfc3339String(),
                    'singleEvents' => 'true',
                    'orderBy' => 'startTime',
                    'showDeleted' => 'true',
                    'maxResults' => 250,
                    'pageToken' => $pageToken,
                ]))
                ->throw();

            foreach ($response->json('items', []) as $event) {
                $this->apply($event, $created, $updated, $cancelled);
            }

            $pageToken = $response->json('nextPageToken');
        } while ($pageToken);

        return "Synced: {$created} new, {$updated} updated, {$cancelled} cancelled";
    }

    private function apply(array $event, int &$created, int &$updated, int &$cancelled): void
    {
        $id = $event['id'] ?? null;

        if (! $id) {
            return;
        }

        $meeting = Meeting::where('google_event_id', $id)->first();

        if (($event['status'] ?? '') === 'cancelled') {
            if ($meeting && ! $meeting->cancelled_at) {
                $meeting->update(['cancelled_at' => now()]);
                $cancelled++;
            }

            return;
        }

        $start = $this->parseTime($event['start'] ?? []);

        if (! $start) {
            return;
        }

        $data = [
            'title' => mb_substr($event['summary'] ?? '(No title)', 0, 255),
            'description' => $event['description'] ?? null,
            'location' => isset($event['location']) ? mb_substr($event['location'], 0, 255) : null,
            'starts_at' => $start,
            'ends_at' => $this->parseTime($event['end'] ?? []),
            'cancelled_at' => null,
        ];

        if ($meeting) {
            $meeting->fill($data);

            if ($meeting->isDirty()) {
                $meeting->save();
                $updated++;
            }

            return;
        }

        Meeting::create($data + ['google_event_id' => $id]);
        $created++;
    }

    private function parseTime(array $time): ?Carbon
    {
        if (! empty($time['dateTime'])) {
            return Carbon::parse($time['dateTime'])->setTimezone(config('app.timezone'));
        }

        if (! empty($time['date'])) {
            return Carbon::parse($time['date'], config('app.timezone'))->startOfDay();
        }

        return null;
    }

    private function freshAccessToken(GoogleConnection $connection): string
    {
        if ($connection->expires_at && $connection->expires_at->gt(now()->addMinutes(2))) {
            return $connection->access_token;
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google.client_id'),
            'client_secret' => config('services.google.client_secret'),
            'refresh_token' => $connection->refresh_token,
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed()) {
            throw new \RuntimeException(
                'Google rejected the saved connection. Please reconnect the calendar ('.$response->json('error', 'unknown').').'
            );
        }

        $connection->update([
            'access_token' => $response->json('access_token'),
            'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600)),
        ]);

        return $connection->access_token;
    }
}