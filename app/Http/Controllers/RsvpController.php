<?php

namespace App\Http\Controllers;

use App\Models\InvitationContact;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RsvpController extends Controller
{
    public function show(string $token): View
{
    $contact = InvitationContact::where('token', $token)->first();

    if (! $contact || $this->isExpired($contact)) {
        return view('rsvp.invalid');
    }

    $contact->update(['last_used_at' => now()]);

    $customFields = \App\Models\CustomFieldDefinition::where('enabled', true)
        ->orderBy('sort_order')
        ->get();

    $existingValues = $contact->invitation->customFieldValues()
        ->pluck('value', 'field_key');

    return view('rsvp.show', [
        'invitation' => $contact->invitation,
        'contact' => $contact,
        'settings' => \App\Models\FormSettings::firstOrCreate([]),
        'customFields' => $customFields,
        'existingValues' => $existingValues,
    ]);
}

public function submit(string $token, Request $request): \Illuminate\Http\RedirectResponse
{
    $contact = InvitationContact::where('token', $token)->first();

    if (! $contact || $this->isExpired($contact)) {
        abort(404);
    }

    if ($contact->invitation->is_locked) {
        return redirect()
            ->route('rsvp.show', $token)
            ->with('error', 'This response has already been recorded and cannot be changed.');
    }

    $validated = $request->validate([
        'attendance_status' => ['required', 'in:attending,representative_attending,not_attending'],
        'representative_name' => ['required_if:attendance_status,representative_attending', 'nullable', 'string', 'max:255'],
        'representative_role' => ['nullable', 'string', 'max:255'],
        'vehicle_registration' => ['nullable', 'string', 'max:255'],
        'estimated_arrival' => ['nullable', 'string', 'max:255'],
        'estimated_departure' => ['nullable', 'string', 'max:255'],
        'submitted_by_name' => ['nullable', 'string', 'max:255'],
        'custom_fields' => ['nullable', 'array'],
        'custom_fields.*' => ['nullable', 'string', 'max:255'],
    ]);

    $contact->invitation->update([
        'attendance_status' => $validated['attendance_status'],
        'representative_name' => $validated['representative_name'] ?? null,
        'representative_role' => $validated['representative_role'] ?? null,
        'vehicle_registration' => $validated['vehicle_registration'],
        'estimated_arrival' => $validated['estimated_arrival'],
        'estimated_departure' => $validated['estimated_departure'],
        'submitted_by_name' => $validated['submitted_by_name'] ?: $contact->name,
        'submitted_by_email' => $contact->email,
        'submitted_at' => now(),
        'is_locked' => true,
    ]);

    foreach ($validated['custom_fields'] ?? [] as $fieldKey => $value) {
        \App\Models\CustomFieldValue::updateOrCreate(
            ['invitation_id' => $contact->invitation->id, 'field_key' => $fieldKey],
            ['value' => $value],
        );
    }

    return redirect()
        ->route('rsvp.show', $token)
        ->with('success', \App\Models\FormSettings::first()->thank_you_message ?? 'Thank you — your response has been recorded.');
}

    private function isExpired(InvitationContact $contact): bool
    {
        $expiresAt = $contact->invitation->expires_at;

        return $expiresAt !== null && $expiresAt->isPast();
    }
}