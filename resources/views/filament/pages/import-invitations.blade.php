<x-filament-panels::page>
    <div class="mb-4 text-sm text-gray-600">
        Upload a CSV with columns: <code>vip_name</code>, <code>organization</code> (optional), <code>pa_name</code>, <code>pa_email</code>.
        Multiple rows with the same VIP name will be grouped as multiple contacts under one invitation.
    </div>

    <form wire:submit="import">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Import
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>