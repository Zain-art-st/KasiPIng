<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSettings extends Model
{
    public const DEFAULTS = [
        'page_heading' => 'Independence Day Attendance Confirmation',
        'intro_text' => null,
        'attendance_question' => 'Will you be attending?',
        'attending_label' => 'Yes, attending',
        'representative_label' => 'Sending a representative',
        'not_attending_label' => 'No, not attending',
        'vehicle_label' => 'Vehicle registration number',
        'arrival_label' => 'Estimated arrival time',
        'departure_label' => 'Estimated departure time',
        'name_label' => 'Your name',
        'submit_button_label' => 'Submit',
        'thank_you_message' => 'Thank you — your response has been recorded.',
    ];

    protected $attributes = self::DEFAULTS;

    protected $fillable = [
        'page_heading',
        'intro_text',
        'attendance_question',
        'attending_label',
        'representative_label',
        'not_attending_label',
        'vehicle_label',
        'arrival_label',
        'departure_label',
        'name_label',
        'submit_button_label',
        'thank_you_message',
    ];

    public static function current(): self
    {
        return static::query()->first() ?? new static;
    }

    public function formState(): array
    {
        $state = [];

        foreach (self::DEFAULTS as $key => $default) {
            $value = $this->getAttribute($key);
            $state[$key] = ($value === null || $value === '') ? $default : $value;
        }

        return $state;
    }
}
