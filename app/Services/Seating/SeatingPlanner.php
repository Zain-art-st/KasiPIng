<?php

namespace App\Services\Seating;

use App\Models\Invitation;
use App\Models\PrecedenceRank;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SeatingPlanner
{
    public const UNRANKED = 1_000_000;

    public array $state;

    public function __construct(array $state = [])
    {
        $this->state = array_merge([
            'layout' => null,
            'config' => SeatingLayout::DEFAULT_CONFIG,
            'people' => [],
            'order' => [],
            'chair' => null,
            'host' => null,
            'seats' => [],
        ], $state);
    }

    public static function person(array $attrs): array
    {
        return array_merge([
            'key' => 'm:'.Str::random(8),
            'name' => '',
            'org' => null,
            'title' => null,
            'rank_sort' => self::UNRANKED,
            'rank_label' => null,
            'note' => null,
            'present' => true,
            'invitation_id' => null,
        ], $attrs);
    }

    public static function fromInvitation(Invitation $i): array
    {
        $rep = $i->attendance_status === 'representative_attending';
        $rank = $i->precedenceRank;

        return self::person([
            'key' => 'inv:'.$i->id,
            'name' => $rep ? ($i->representative_name ?: 'Wakil '.$i->vip_name) : $i->vip_name,
            'org' => $i->organization,
            'title' => $rep ? $i->representative_role : $i->position_title,
            'rank_sort' => $rank?->sort ?? self::UNRANKED,
            'rank_label' => $rank?->label(),
            'note' => $rep ? 'Mewakili '.$i->vip_name : null,
            'present' => in_array($i->attendance_status, ['attending', 'representative_attending'], true),
            'invitation_id' => $i->id,
        ]);
    }

    public static function fromRank(?PrecedenceRank $rank, array $attrs): array
    {
        return self::person($attrs + [
            'rank_sort' => $rank?->sort ?? self::UNRANKED,
            'rank_label' => $rank?->label(),
        ]);
    }

    public function syncInvitations(Collection $invitations): void
    {
        $seen = [];

        foreach ($invitations as $invitation) {
            $fresh = self::fromInvitation($invitation);
            $key = $fresh['key'];
            $seen[] = $key;

            if (isset($this->state['people'][$key])) {
                $old = $this->state['people'][$key];
                $manual = $old['manual_presence'] ?? false;
                $this->state['people'][$key] = array_merge($fresh, [
                    'present' => $manual ? $old['present'] : $fresh['present'],
                    'manual_presence' => $manual,
                ]);
            } else {
                $this->addPerson($fresh);
            }
        }

        foreach ($this->state['people'] as $key => $p) {
            if (str_starts_with($key, 'inv:') && ! in_array($key, $seen, true)) {
                $this->removePerson($key);
            }
        }
    }

    public function addPerson(array $person, ?string $afterKey = null): string
    {
        $key = $person['key'];
        $this->state['people'][$key] = $person;
        $this->state['order'] = array_values(array_diff($this->state['order'], [$key]));

        if ($afterKey !== null && ($pos = array_search($afterKey, $this->state['order'], true)) !== false) {
            array_splice($this->state['order'], $pos + 1, 0, [$key]);

            return $key;
        }

        $pos = count($this->state['order']);
        foreach ($this->state['order'] as $i => $other) {
            if (($this->state['people'][$other]['rank_sort'] ?? self::UNRANKED) > $person['rank_sort']) {
                $pos = $i;
                break;
            }
        }
        array_splice($this->state['order'], $pos, 0, [$key]);

        return $key;
    }

    public function removePerson(string $key): void
    {
        unset($this->state['people'][$key]);
        $this->state['order'] = array_values(array_diff($this->state['order'], [$key]));
        $this->state['seats'] = array_filter($this->state['seats'], fn ($k) => $k !== $key);

        foreach (['chair', 'host'] as $role) {
            if ($this->state[$role] === $key) {
                $this->state[$role] = null;
            }
        }
    }

    public function resortByRank(): void
    {
        $people = $this->state['people'];
        usort($this->state['order'], fn ($a, $b) => [$people[$a]['rank_sort'], $people[$a]['name']] <=> [$people[$b]['rank_sort'], $people[$b]['name']]);
    }

    public function presentKeys(): array
    {
        return array_values(array_filter($this->state['order'], fn ($k) => $this->state['people'][$k]['present'] ?? false));
    }

    public function seatCount(): int
    {
        return $this->state['layout'] ? SeatingLayout::seatCount($this->state['layout'], $this->state['config']) : 0;
    }

    public function sequence(): array
    {
        $keys = [];

        foreach (['chair', 'host'] as $role) {
            $k = $this->state[$role];
            if ($k && isset($this->state['people'][$k]) && ! in_array($k, $keys, true)) {
                $this->state['people'][$k]['present'] = true;
                $keys[] = $k;
            }
        }

        return [...$keys, ...array_values(array_diff($this->presentKeys(), $keys))];
    }

    public function arrange(): void
    {
        $this->state['seats'] = [];
        $total = $this->seatCount();

        foreach ($this->sequence() as $i => $key) {
            if ($i >= $total) {
                break;
            }
            $this->state['seats'][(string) ($i + 1)] = $key;
        }
    }

    public function arrangeKeepingSeats(): void
    {
        $total = $this->seatCount();
        $seats = array_filter(
            $this->state['seats'],
            fn ($key, $seq) => (int) $seq <= $total && ($this->state['people'][$key]['present'] ?? false),
            ARRAY_FILTER_USE_BOTH,
        );

        $waiting = array_values(array_diff($this->sequence(), $seats));

        for ($seq = 1; $seq <= $total && $waiting; $seq++) {
            if (! isset($seats[(string) $seq])) {
                $seats[(string) $seq] = array_shift($waiting);
            }
        }

        $this->state['seats'] = $seats;
    }

    public function seatOf(string $key): ?int
    {
        $seq = array_search($key, $this->state['seats'], true);

        return $seq === false ? null : (int) $seq;
    }

    public function swap(int $a, int $b): void
    {
        $seats = $this->state['seats'];
        $ka = $seats[(string) $a] ?? null;
        $kb = $seats[(string) $b] ?? null;
        unset($seats[(string) $a], $seats[(string) $b]);

        if ($kb !== null) {
            $seats[(string) $a] = $kb;
        }
        if ($ka !== null) {
            $seats[(string) $b] = $ka;
        }

        $this->state['seats'] = $seats;
    }

    public function place(string $key, int $seq): void
    {
        if (! isset($this->state['people'][$key]) || $seq < 1 || $seq > $this->seatCount()) {
            return;
        }

        if (($from = $this->seatOf($key)) !== null) {
            $this->swap($from, $seq);

            return;
        }

        $this->state['people'][$key]['present'] = true;
        $this->state['seats'][(string) $seq] = $key;
    }

    public function unseat(int $seq): void
    {
        unset($this->state['seats'][(string) $seq]);
    }

    public function setPresent(string $key, bool $present): void
    {
        if (! isset($this->state['people'][$key])) {
            return;
        }

        $this->state['people'][$key]['present'] = $present;
        $this->state['people'][$key]['manual_presence'] = true;

        if (! $present) {
            foreach (['chair', 'host'] as $role) {
                if ($this->state[$role] === $key) {
                    $this->state[$role] = null;
                }
            }
        }

        $this->arrange();
    }

    public function replace(int $seq, string $newKey): void
    {
        $oldKey = $this->state['seats'][(string) $seq] ?? null;

        if (($from = $this->seatOf($newKey)) !== null) {
            $this->unseat($from);
        }

        if ($oldKey !== null && $oldKey !== $newKey) {
            $this->state['people'][$oldKey]['present'] = false;
            $this->state['people'][$oldKey]['manual_presence'] = true;
            $this->state['order'] = array_values(array_diff($this->state['order'], [$newKey]));
            $pos = array_search($oldKey, $this->state['order'], true);
            array_splice($this->state['order'], $pos === false ? count($this->state['order']) : $pos + 1, 0, [$newKey]);

            foreach (['chair', 'host'] as $role) {
                if ($this->state[$role] === $oldKey) {
                    $this->state[$role] = $newKey;
                }
            }
        }

        $this->state['people'][$newKey]['present'] = true;
        $this->state['people'][$newKey]['manual_presence'] = true;
        $this->state['seats'][(string) $seq] = $newKey;
    }

    public function moveInOrder(string $key, ?string $beforeKey): void
    {
        $order = array_values(array_diff($this->state['order'], [$key]));
        $pos = $beforeKey === null ? false : array_search($beforeKey, $order, true);
        array_splice($order, $pos === false ? count($order) : $pos, 0, [$key]);
        $this->state['order'] = $order;
        $this->arrange();
    }

    public function unseated(): array
    {
        $seated = array_values($this->state['seats']);

        return array_values(array_filter($this->presentKeys(), fn ($k) => ! in_array($k, $seated, true)));
    }

    public function absent(): array
    {
        return array_values(array_filter($this->state['order'], fn ($k) => ! ($this->state['people'][$k]['present'] ?? false)));
    }

    public function misplaced(): array
    {
        $index = array_flip($this->state['order']);
        $fixed = array_filter([$this->state['chair'], $this->state['host']]);
        $seats = array_filter($this->state['seats'], fn ($k) => ! in_array($k, $fixed, true) && isset($index[$k]));
        ksort($seats, SORT_NUMERIC);

        $flagged = [];
        $entries = array_map(null, array_keys($seats), array_values($seats));

        foreach ($entries as $i => [$seq, $key]) {
            foreach (array_slice($entries, $i + 1) as [, $later]) {
                if ($index[$later] < $index[$key]) {
                    $flagged[] = (int) $seq;
                    break;
                }
            }
        }

        return $flagged;
    }
}
