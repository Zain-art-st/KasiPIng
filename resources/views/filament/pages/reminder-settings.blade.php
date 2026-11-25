<x-filament-panels::page>
    <x-filament::section icon="heroicon-o-information-circle">
        <x-slot name="heading">{{ __('How reminders work') }}</x-slot>

        <ul style="list-style:disc; padding-left:1.25rem; font-size:.875rem; line-height:1.6;">
            <li>{{ __('The clock starts when the invitation is sent. A reminder goes out every interval after that, to the same people (VIP, with PA copied).') }}</li>
            <li>{{ __('Only guests who are still Pending get reminders. As soon as they answer, or an admin changes their status, reminders stop.') }}</li>
            <li>{{ __('Reminders stop when the meeting starts. Big-event guests only get reminders if their invitation has a "Link expires at" date, and stop at that time.') }}</li>
            <li><strong>{{ __('Example:') }}</strong> {{ __('invited at 2:00 PM for a 6:00 PM meeting, every 2 hours → one reminder at 4:00 PM. Every hour → 3:00, 4:00 and 5:00 PM.') }}</li>
            <li>{{ __('Whatever this is set to: anyone still Pending when the meeting starts is marked Not attending (No response). You can still change them by hand.') }}</li>
            <li>{{ __('Reminder emails contain both Bahasa Melayu and English.') }}</li>
        </ul>
    </x-filament::section>

    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top:1rem;">
            <x-filament::button type="submit">{{ __('Save') }}</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
