<x-mail::message>
# {{ $urgent ? 'Urgent: response needed' : 'Reminder: response needed' }}

Dear {{ $attendeeName }},

@if ($isMeeting)
**{{ $title }}** starts in **{{ $leadLabel }}** ({{ $when }}@if ($location), {{ $location }}@endif).
@else
Please respond to **{{ $title }}** before **{{ $when }}** ({{ $leadLabel }} from now).
@endif

We have not yet received your response. Please confirm with one click:

<x-mail::button :url="$rsvpUrl">
Respond now
</x-mail::button>

If we do not hear from you by then, we will assume you are not attending.

Thank you,<br>
Event Secretariat
</x-mail::message>
