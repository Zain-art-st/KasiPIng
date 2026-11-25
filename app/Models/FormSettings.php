<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FormSettings extends Model
{
    // Default RSVP page wording. Admins can change it in Settings → RSVP Form Wording without touching code.
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

    // Malay default wording, used when no Malay wording has been saved.
    public const DEFAULTS_MS = [
        'page_heading' => 'Pengesahan Kehadiran Sambutan Hari Kebangsaan',
        'intro_text' => null,
        'attendance_question' => 'Adakah anda akan hadir?',
        'attending_label' => 'Ya, hadir',
        'representative_label' => 'Menghantar wakil',
        'not_attending_label' => 'Tidak hadir',
        'vehicle_label' => 'Nombor pendaftaran kenderaan',
        'arrival_label' => 'Anggaran masa tiba',
        'departure_label' => 'Anggaran masa bertolak',
        'name_label' => 'Nama anda',
        'submit_button_label' => 'Hantar',
        'thank_you_message' => 'Terima kasih — maklum balas anda telah direkodkan.',
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

    public static function malayWording(): array
    {
        return array_intersect_key(AppSetting::getJson(AppSetting::FORM_WORDING_MS), self::DEFAULTS);
    }

    public function malayFormState(?array $saved = null): array
    {
        $saved ??= static::malayWording();
        $english = $this->formState();
        $state = [];

        foreach (self::DEFAULTS_MS as $key => $default) {
            $state[$key] = filled($saved[$key] ?? null)
                ? $saved[$key]
                : ($english[$key] === self::DEFAULTS[$key] ? $default : $english[$key]);
        }

        return $state;
    }

    public function localized(?string $locale = null, ?array $malay = null): self
    {
        $state = ($locale ?? app()->getLocale()) === 'ms' ? $this->malayFormState($malay) : $this->formState();

        return (new static)->forceFill($state);
    }
}
