<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvitationContact extends Model
{
    protected $fillable = [
        'invitation_id',
        'name',
        'email',
        'token',
        'last_used_at',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (InvitationContact $contact) {
            if (empty($contact->token)) {
                $contact->token = \Illuminate\Support\Str::random(40);
            }
        });
    }

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}