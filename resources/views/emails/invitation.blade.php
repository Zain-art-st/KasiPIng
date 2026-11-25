<x-mail::message>
@foreach ($heading as $locale => $title)
@if (! $loop->first)
<hr>

@endif
# {{ $title }}

{{ __('Dear :name,', ['name' => $vipName], $locale) }}

{{ __('Please confirm your attendance using the secure link below. :contact has also been copied on this email and may submit this confirmation on your behalf.', ['contact' => $contactName], $locale) }}

{{ __('This link is unique to you — please do not forward it.', [], $locale) }}

@endforeach
<x-mail::button :url="$rsvpUrl">
{{ implode(' / ', array_map(fn ($locale) => __('Confirm Attendance', [], $locale), array_keys($heading))) }}
</x-mail::button>

{{ implode(' / ', array_map(fn ($locale) => __("If the button above doesn't work, copy and paste this link into your browser:", [], $locale), array_keys($heading))) }}

{{ $rsvpUrl }}

{{-- Sign-off at the bottom of the email; change the wording in lang/ms.json ("Event Secretariat"). --}}
{{ implode(' / ', array_map(fn ($locale) => __('Thank you', [], $locale), array_keys($heading))) }},<br>
{{ implode(' / ', array_map(fn ($locale) => __('Event Secretariat', [], $locale), array_keys($heading))) }}
</x-mail::message>
