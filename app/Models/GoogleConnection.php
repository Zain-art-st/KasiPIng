<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoogleConnection extends Model
{
    protected $fillable = [
        'email',
        'access_token',
        'refresh_token',
        'expires_at',
        'last_synced_at',
        'last_error',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'expires_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];
}