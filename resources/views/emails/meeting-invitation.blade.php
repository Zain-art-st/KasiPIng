<x-mail::message>
# {{ $meetingTitle }}

Dear {{ $attendeeName }},

You are invited to **{{ $meetingTitle }}** on **{{ $startsAt }}**@if ($location), at {{ $location }}@endif.

Please confirm your attendance with the secure link below. {{ $contactName }} has also been copied and may respond on your behalf.

<x-mail::button :url="$rsvpUrl">
Respond now
</x-mail::button>

Thank you,<br>
Event Secretariat
</x-mail::message>