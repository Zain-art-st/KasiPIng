<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    public const REMINDER_INTERVAL_HOURS = 'reminder_interval_hours';

    public const FORM_WORDING_MS = 'form_wording_ms';

    public const QUESTION_LABELS_MS = 'question_labels_ms';

    public const EMAIL_TEMPLATE_MS = 'email_template_ms';

    // Reminder interval choices in hours; 0 = off (the default).
    public const REMINDER_INTERVAL_OPTIONS = [0, 1, 2, 4, 6, 8, 12, 24];

    protected $fillable = ['key', 'value'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::query()->where('key', $key)->value('value') ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }

    public static function getJson(string $key): array
    {
        $value = json_decode((string) static::get($key, ''), true);

        return is_array($value) ? $value : [];
    }

    public static function putJson(string $key, array $value): void
    {
        static::put($key, json_encode($value, JSON_UNESCAPED_UNICODE));
    }

    public static function reminderIntervalHours(): int
    {
        $hours = (int) static::get(self::REMINDER_INTERVAL_HOURS, 0);

        return in_array($hours, self::REMINDER_INTERVAL_OPTIONS, true) ? $hours : 0;
    }
}
