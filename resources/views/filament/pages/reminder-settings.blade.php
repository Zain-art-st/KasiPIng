<x-filament-panels::page>
    <x-filament::section icon="heroicon-o-information-circle">
        <x-slot name="heading">How reminders work</x-slot>

        <ul style="list-style:disc; padding-left:1.25rem; font-size:.875rem; line-height:1.6;">
            <li>The clock starts when the invitation is <strong>sent</strong>. A reminder goes out every interval after that, to the same people (VIP, with PA copied).</li>
            <li>Only guests who are still <strong>Pending</strong> get reminders. As soon as they answer, or an admin changes their status, reminders stop.</li>
            <li>Reminders stop when the meeting starts. Big-event guests only get reminders if their invitation has a "Link expires at" date, and stop at that time.</li>
            <li><strong>Example:</strong> invited at 2:00 PM for a 6:00 PM meeting, every 2 hours → one reminder at 4:00 PM. Every hour → 3:00, 4:00 and 5:00 PM.</li>
            <li>Whatever this is set to: anyone still Pending when the meeting starts is marked <strong>Not attending (No response)</strong>. You can still change them by hand.</li>
        </ul>
    </x-filament::section>

    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top:1rem;">
            <x-filament::button type="submit">Save</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
