<x-filament-panels::page>
    @include('filament.partials.settings-with-preview-styles')

    <x-filament::section icon="heroicon-o-information-circle" collapsible>
        <x-slot name="heading">How extra questions work</x-slot>

        <ul style="list-style:disc; padding-left:1.25rem; font-size:.875rem; line-height:1.6;">
            <li><strong>Where they appear:</strong> on the guest's RSVP page, under the standard questions (vehicle, arrival, departure). They apply to every RSVP link, big events and meetings.</li>
            <li><strong>Where the answers go:</strong> open an invitation or attendee; the answers are listed under "Answers to extra questions". You can also show them as columns in the invitation list (column toggle, top right of the table).</li>
            <li><strong>Importing:</strong> when importing a CSV you can put a column straight into one of these questions, or create a new question from a column.</li>
            <li><strong>Removing:</strong> deleting a question also stops showing its answers. To stop asking but keep the answers, switch "Ask guests this question" off instead.</li>
        </ul>
    </x-filament::section>

    <div class="settings-with-preview">
        <form wire:submit="save">
            {{ $this->form }}

            <div style="margin-top:1rem;">
                <x-filament::button type="submit">Save questions</x-filament::button>
            </div>
        </form>

        <div class="settings-preview-column">
            @include('filament.partials.rsvp-preview')
        </div>
    </div>
</x-filament-panels::page>
