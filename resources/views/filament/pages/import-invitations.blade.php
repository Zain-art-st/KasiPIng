<x-filament-panels::page>
    <div class="mb-4 text-sm text-gray-600">
        {{ __('Upload a CSV with columns:') }} <code>vip_name</code>, <code>organization</code> ({{ __('optional') }}), <code>pa_name</code>, <code>pa_email</code>.
        {{ __('Multiple rows with the same VIP name will be grouped as multiple contacts under one invitation.') }}
        @if ($this->meeting)
    <div class="mb-4 text-sm">
        {{ __('Importing attendees into:') }}
        <strong>{{ \App\Models\Meeting::find($this->meeting)?->title ?? __('Unknown meeting') }}</strong>
    </div>
    @endif
    </div>

    <form wire:submit="import">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                {{ __('Import') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>