<x-filament-panels::page>
    @php($connection = \App\Models\GoogleConnection::first())

    <div class="space-y-2 text-sm">
        @if ($connection)
            <p>{{ __('Connected as') }} <strong>{{ $connection->email }}</strong></p>
            <p>{{ __('Last successful sync') }}: {{ $connection->last_synced_at?->diffForHumans() ?? __('never') }}</p>
            @if ($connection->last_error)
                <p class="text-danger-600">{{ __('Last error') }}: {{ $connection->last_error }}</p>
            @endif
        @else
            <p>{{ __('No Google Calendar is connected yet.') }}</p>
        @endif
    </div>

    <div>
        <x-filament::button tag="a" href="{{ route('auth.google.calendar') }}">
            {{ $connection ? __('Reconnect Google Calendar') : __('Connect Google Calendar') }}
        </x-filament::button>
    </div>
</x-filament-panels::page>