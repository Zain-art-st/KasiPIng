<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SeatingPlan extends Model
{
    protected $fillable = ['context', 'meeting_id', 'data'];

    protected $casts = [
        'data' => 'array',
    ];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public static function contextFor(?int $meetingId): string
    {
        return $meetingId ? 'meeting:'.$meetingId : 'event';
    }
}
