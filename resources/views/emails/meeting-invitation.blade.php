<x-mail::message>
@foreach ($startsAt as $locale => $time)
@if (! $loop->first)
<hr>

@endif
# {{ $meetingTitle }}

{{ __('Dear :name,', ['name' => $attendeeName], $locale) }}

{!! __('You are invited to **:title** on **:time**', ['title' => e($meetingTitle), 'time' => e($time)], $locale) !!}@if ($location){{ __(', at :location', ['location' => $location], $locale) }}@endif.

{{ __('Please confirm your attendance with the secure link below. :contact has also been copied and may respond on your behalf.', ['contact' => $contactName], $locale) }}

@endforeach
<x-mail::button :url="$rsvpUrl">
{{ implode(' / ', array_map(fn ($locale) => __('Respond now', [], $locale), array_keys($startsAt))) }}
</x-mail::button>

{{-- Sign-off at the bottom of the email; change the wording in lang/ms.json ("Event Secretariat"). --}}
{{ implode(' / ', array_map(fn ($locale) => __('Thank you', [], $locale), array_keys($startsAt))) }},<br>
{{ implode(' / ', array_map(fn ($locale) => __('Event Secretariat', [], $locale), array_keys($startsAt))) }}
</x-mail::message>
