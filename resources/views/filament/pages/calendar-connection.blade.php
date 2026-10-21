<x-filament-panels::page>
    @php($connection = \App\Models\GoogleConnection::first())

    <div class="space-y-2 text-sm">
        @if ($connection)
            <p>Connected as <strong>{{ $connection->email }}</strong></p>
            <p>Last successful sync: {{ $connection->last_synced_at?->diffForHumans() ?? 'never' }}</p>
            @if ($connection->last_error)
                <p class="text-danger-600">Last error: {{ $connection->last_error }}</p>
            @endif
        @else
            <p>No Google Calendar is connected yet.</p>
        @endif
    </div>

    <div>
        <x-filament::button tag="a" href="{{ route('auth.google.calendar') }}">
            {{ $connection ? 'Reconnect Google Calendar' : 'Connect Google Calendar' }}
        </x-filament::button>
    </div>
</x-filament-panels::page>