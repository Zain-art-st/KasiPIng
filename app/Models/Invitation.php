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
];

    protected $casts = [
    'submitted_at' => 'datetime',
    'expires_at' => 'datetime',
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