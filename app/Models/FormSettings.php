<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSettings extends Model
{
    protected $fillable = [
        'page_heading',
        'intro_text',
        'attendance_question',
        'attending_label',
        'not_attending_label',
        'vehicle_label',
        'arrival_label',
        'departure_label',
        'name_label',
        'submit_button_label',
        'thank_you_message',
    ];
}