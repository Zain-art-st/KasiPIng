<x-mail::message>
@foreach ($bodies as $bodyHtml)
@if (! $loop->first)
<hr>
@endif
{!! $bodyHtml !!}
@endforeach

@if ($showButton)
<x-mail::button :url="$rsvpUrl">
{{ implode(' / ', array_map(fn ($locale) => __('Respond now', [], $locale), \App\Mail\InvitationEmail::LANGUAGES)) }}
</x-mail::button>
@endif
</x-mail::message>
