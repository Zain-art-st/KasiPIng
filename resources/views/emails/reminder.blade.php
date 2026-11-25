<x-mail::message>
@foreach ($title as $locale => $eventTitle)
@if (! $loop->first)
<hr>

@endif
# {{ __($urgent ? 'Urgent: response needed' : 'Reminder: response needed', [], $locale) }}

{{ __('Dear :name,', ['name' => $attendeeName], $locale) }}

@if ($isMeeting)
{!! __('**:title** starts in **:lead** (:when)', ['title' => e($eventTitle), 'lead' => e($leadLabel[$locale]), 'when' => e($when[$locale].($location ? ', '.$location : ''))], $locale) !!}.
@else
{!! __('Please respond to **:title** before **:when** (:lead from now).', ['title' => e($eventTitle), 'when' => e($when[$locale]), 'lead' => e($leadLabel[$locale])], $locale) !!}
@endif

{{ __('We have not yet received your response. Please confirm with one click.', [], $locale) }}

{{ __('If we do not hear from you by then, we will assume you are not attending.', [], $locale) }}

@endforeach
<x-mail::button :url="$rsvpUrl">
{{ implode(' / ', array_map(fn ($locale) => __('Respond now', [], $locale), array_keys($title))) }}
</x-mail::button>

{{-- Sign-off at the bottom of the email; change the wording in lang/ms.json ("Event Secretariat"). --}}
{{ implode(' / ', array_map(fn ($locale) => __('Thank you', [], $locale), array_keys($title))) }},<br>
{{ implode(' / ', array_map(fn ($locale) => __('Event Secretariat', [], $locale), array_keys($title))) }}
</x-mail::message>
