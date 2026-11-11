<x-mail::message>
{!! $bodyHtml !!}

@if ($showButton)
<x-mail::button :url="$rsvpUrl">
Respond now
</x-mail::button>
@endif
</x-mail::message>
