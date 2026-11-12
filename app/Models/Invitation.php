<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invitation extends Model
{
    protected $fillable = [
    'meeting_id',
    'vip_name',
    'organization',
    'vip_email',
    'vip_phone',
    'attendance_status',
    'representative_name',
    'representative_role',
    'vehicle_registration',
    'estimated_arrival',
    'estimated_departure',
    'submitted_by_name',
    'submitted_by_email',
    'submitted_at',
    'is_locked',
    'expires_at',
    'invited_at',
    'no_response_at',
];

    protected $casts = [
    'submitted_at' => 'datetime',
    'expires_at' => 'datetime',
    'invited_at' => 'datetime',
    'no_response_at' => 'datetime',
    'is_locked' => 'boolean',
];

    protected static function booted(): void
    {
        static::saving(function (Invitation $invitation) {
            if ($invitation->isDirty('attendance_status') && ! $invitation->isDirty('no_response_at')) {
                $invitation->no_response_at = null;
            }
        });
    }

    public function responseDeadline(): ?\Carbon\CarbonInterface
    {
        return $this->meeting_id ? $this->meeting?->starts_at : $this->expires_at;
    }

    public function statusLabel(): string
    {
        if ($this->attendance_status === 'not_attending' && $this->no_response_at) {
            return 'No response';
        }

        return match ($this->attendance_status) {
            'attending' => 'Attending',
            'representative_attending' => 'Representative',
            'not_attending' => 'Not attending',
            default => 'Pending',
        };
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(InvitationContact::class);
    }

    public function meeting(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
    return $this->belongsTo(Meeting::class);
    }

    public function customFieldValues(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
    return $this->hasMany(CustomFieldValue::class);
    }

    public function ensureVipContact(): ?InvitationContact
    {
        if (! $this->vip_email || $this->contacts()->exists()) {
            return null;
        }

        $contact = $this->contacts()->create([
            'name' => $this->vip_name,
            'email' => $this->vip_email,
        ]);

        $this->unsetRelation('contacts');

        return $contact;
    }

    public function sendInvitation(): int
    {
        $this->ensureVipContact();
        $contacts = $this->mailableContacts();

        foreach ($contacts as $contact) {
            \Illuminate\Support\Facades\Mail::send(new \App\Mail\InvitationEmail($contact));
        }

        if ($contacts->isNotEmpty()) {
            $this->forceFill(['invited_at' => now()])->save();
        }

        return $contacts->count();
    }

    public function recipientSummary(): string
    {
        $contacts = $this->vip_email || $this->contacts()->exists()
            ? $this->mailableContacts()
            : collect();

        if ($contacts->isEmpty()) {
            return $this->vip_email ? 'To: '.$this->vip_email : 'No email address on this invitation.';
        }

        return $contacts->map(function (InvitationContact $c) {
            $to = $this->vip_email ?: $c->email;
            $cc = $this->vip_email && strcasecmp($this->vip_email, $c->email) !== 0 ? ' · CC: '.$c->email : '';

            return 'To: '.$to.$cc;
        })->implode("\n");
    }

    public function mailableContacts(): \Illuminate\Support\Collection
    {
        $contacts = $this->contacts->sortBy('id')->values();

        $others = $contacts->reject(
            fn (InvitationContact $c) => $this->vip_email && strcasecmp($c->email, $this->vip_email) === 0
        );

        return $others->isNotEmpty() ? $others->values() : $contacts;
    }
}