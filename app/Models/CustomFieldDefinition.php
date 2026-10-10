<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomFieldDefinition extends Model
{
    protected $fillable = [
        'field_key',
        'label',
        'sort_order',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];
}