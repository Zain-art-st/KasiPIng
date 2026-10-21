<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReminderLog extends Model
{
    protected $fillable = ['invitation_id', 'interval_minutes', 'status', 'detail'];

    public function invitation(): BelongsTo
    {
        return $this->belongsTo(Invitation::class);
    }
}