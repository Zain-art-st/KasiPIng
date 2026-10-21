<x-mail::message>
# {{ $urgent ? 'Urgent: response needed' : 'Reminder: response needed' }}

Dear {{ $attendeeName }},

**{{ $meetingTitle }}** starts in **{{ $leadLabel }}** ({{ $startsAt }}@if ($location), {{ $location }}@endif).

We have not yet received your response. Please confirm with one click:

<x-mail::button :url="$rsvpUrl">
Respond now
</x-mail::button>

Thank you,<br>
Event Secretariat
</x-mail::message>