<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('Invalid Invitation') }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow p-8 max-w-md text-center">
        <div class="flex justify-end gap-1 text-xs mb-3">
            @foreach (\App\Http\Middleware\SetLocale::LOCALES as $code => $name)
                @if (app()->getLocale() === $code)
                    <span class="px-2 py-1 rounded bg-gray-900 text-white">{{ $name }}</span>
                @else
                    <a href="{{ route('language.switch', $code) }}" class="px-2 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-50">{{ $name }}</a>
                @endif
            @endforeach
        </div>
        <h1 class="text-xl font-semibold text-gray-800 mb-2">{{ __('Invitation Not Found') }}</h1>
        <p class="text-gray-600">
            {{ __('This invitation link is invalid or has expired. Please contact the event secretariat.') }}
        </p>
    </div>
</body>
</html>
