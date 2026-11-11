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
];

    protected $casts = [
    'submitted_at' => 'datetime',
    'expires_at' => 'datetime',
    'invited_at' => 'datetime',
    'is_locked' => 'boolean',
];

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

    /**
     * A VIP with no PA would otherwise have no token and no RSVP link, and be
     * skipped by bulk send and reminders. Give them a contact of their own.
     */
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

    /**
     * Email the invitation (one email per mailable contact) and record when.
     * Returns how many emails were sent; 0 means there is no address to send to.
     */
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

    /** "To: vip@… · CC: pa@…" lines describing who sendInvitation() will email. */
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

    /**
     * Contacts an invitation email goes out through. Each email is addressed to
     * the VIP with the contact copied, so when PAs exist the VIP's own contact is
     * left out (otherwise the VIP would receive the invitation twice).
     */
    public function mailableContacts(): \Illuminate\Support\Collection
    {
        $contacts = $this->contacts->sortBy('id')->values();

        $others = $contacts->reject(
            fn (InvitationContact $c) => $this->vip_email && strcasecmp($c->email, $this->vip_email) === 0
        );

        return $others->isNotEmpty() ? $others->values() : $contacts;
    }
}