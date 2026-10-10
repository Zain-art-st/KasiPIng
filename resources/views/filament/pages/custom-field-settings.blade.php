<x-filament-panels::page>
    <div class="mb-4 text-sm text-gray-600">
        Add up to 5 custom questions that will appear on both the admin invitation form and the public RSVP form.
    </div>

    <form wire:submit="save">
        {{ $this->form }}
        <div class="mt-4">
            <x-filament::button type="submit">Save Questions</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>