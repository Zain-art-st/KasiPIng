<x-mail::message>
# Independence Day Attendance Confirmation

Dear {{ $vipName }},

Please confirm your attendance for the upcoming Independence Day event using the secure link below. {{ $contactName }} has also been copied on this email and may submit this confirmation on your behalf.
Please confirm attendance using the secure link below:

<x-mail::button :url="$rsvpUrl">
Confirm Attendance
</x-mail::button>

If the button above doesn't work, copy and paste this link into your browser:

{{ $rsvpUrl }}

This link is unique to you — please do not forward it.

Thanks,<br>
--
</x-mail::message>