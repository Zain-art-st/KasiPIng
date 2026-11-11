<x-filament-panels::page>
    @include('filament.partials.settings-with-preview-styles')

    <div class="settings-with-preview">
        <form wire:submit="save">
            {{ $this->form }}

            <div style="margin-top:1rem;">
                <x-filament::button type="submit">Save</x-filament::button>
            </div>
        </form>

        <div class="settings-preview-column">
            @include('filament.partials.rsvp-preview')
        </div>
    </div>
</x-filament-panels::page>
