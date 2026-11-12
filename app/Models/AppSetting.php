<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    public const REMINDER_INTERVAL_HOURS = 'reminder_interval_hours';

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

    public static function reminderIntervalHours(): int
    {
        $hours = (int) static::get(self::REMINDER_INTERVAL_HOURS, 0);

        return in_array($hours, self::REMINDER_INTERVAL_OPTIONS, true) ? $hours : 0;
    }
}
