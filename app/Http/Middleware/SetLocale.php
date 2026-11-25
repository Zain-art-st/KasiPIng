<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    // Languages users can switch between. Malay text lives in lang/ms.json (English text is the key).
    public const LOCALES = [
        'en' => 'English',
        'ms' => 'Bahasa Melayu',
    ];

    public function handle(Request $request, Closure $next, ?string $default = null): Response
    {
        if ($request->hasSession() && isset(self::LOCALES[$lang = (string) $request->query('lang')])) {
            $request->session()->put('locale', $lang);
        }

        $locale = $request->hasSession() ? $request->session()->get('locale') : null;

        static::apply(isset(self::LOCALES[$locale]) ? $locale : ($default ?? config('app.locale')));

        return $next($request);
    }

    public static function apply(string $locale): void
    {
        App::setLocale($locale);
        Carbon::setLocale($locale);
    }

    public static function other(): string
    {
        return App::getLocale() === 'ms' ? 'en' : 'ms';
    }
}
