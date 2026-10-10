<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $settings->page_heading }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow p-8 max-w-md w-full">
        <h1 class="text-lg font-semibold text-gray-500 mb-1">{{ $settings->page_heading }}</h1>
        <h2 class="text-2xl font-bold text-gray-900 mb-6">{{ $invitation->vip_name }}</h2>

        @if ($settings->intro_text)
            <p class="text-sm text-gray-600 mb-4">{{ $settings->intro_text }}</p>
        @endif
        @if (session('error'))
    <div class="bg-red-50 text-red-800 text-sm rounded p-3 mb-4">
        {{ session('error') }}
    </div>
@endif

        @if (session('success'))
            <div class="bg-green-50 text-green-800 text-sm rounded p-3 mb-4">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('rsvp.submit', $contact->token) }}" class="space-y-5">
    @csrf

    @if ($invitation->is_locked)
        <div class="bg-amber-50 text-amber-800 text-sm rounded p-3 mb-4">
            This response has already been recorded and cannot be changed. Please contact the event secretariat if you need to make corrections.
        </div>
        <fieldset disabled class="opacity-60 pointer-events-none">
    @endif

            <div>
    <label class="block text-sm font-medium text-gray-700 mb-2">{{ $settings->attendance_question }}</label>
    <div class="grid grid-cols-1 gap-2">
        <label>
            <input type="radio" name="attendance_status" value="attending" class="peer sr-only"
                {{ old('attendance_status', $invitation->attendance_status) === 'attending' ? 'checked' : '' }}
                onchange="document.getElementById('rep-fields').classList.add('hidden')">
            <div class="text-center py-2 rounded border border-gray-300 peer-checked:bg-green-600 peer-checked:text-white peer-checked:border-green-600 cursor-pointer">
                {{ $settings->attending_label }}
            </div>
        </label>
        <label>
            <input type="radio" name="attendance_status" value="representative_attending" class="peer sr-only"
                {{ old('attendance_status', $invitation->attendance_status) === 'representative_attending' ? 'checked' : '' }}
                onchange="document.getElementById('rep-fields').classList.remove('hidden')">
            <div class="text-center py-2 rounded border border-gray-300 peer-checked:bg-amber-600 peer-checked:text-white peer-checked:border-amber-600 cursor-pointer">
                Representative Attending
            </div>
        </label>
        <label>
            <input type="radio" name="attendance_status" value="not_attending" class="peer sr-only"
                {{ old('attendance_status', $invitation->attendance_status) === 'not_attending' ? 'checked' : '' }}
                onchange="document.getElementById('rep-fields').classList.add('hidden')">
            <div class="text-center py-2 rounded border border-gray-300 peer-checked:bg-red-600 peer-checked:text-white peer-checked:border-red-600 cursor-pointer">
                {{ $settings->not_attending_label }}
            </div>
        </label>
    </div>
    @error('attendance_status')
        <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
    @enderror
</div>

<div id="rep-fields" class="{{ old('attendance_status', $invitation->attendance_status) === 'representative_attending' ? '' : 'hidden' }} space-y-4 border-l-2 border-amber-300 pl-4">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Representative's Name</label>
        <input type="text" name="representative_name"
            value="{{ old('representative_name', $invitation->representative_name) }}"
            class="w-full border border-gray-300 rounded px-3 py-2">
        @error('representative_name')
            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Representative's Role / Title</label>
        <input type="text" name="representative_role"
            value="{{ old('representative_role', $invitation->representative_role) }}"
            class="w-full border border-gray-300 rounded px-3 py-2">
    </div>
</div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $settings->vehicle_label }}</label>
                <input type="text" name="vehicle_registration"
                    value="{{ old('vehicle_registration', $invitation->vehicle_registration) }}"
                    class="w-full border border-gray-300 rounded px-3 py-2">
            </div>

            <!-- New Estimated Arrival Field -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Arrival</label>
                <input type="text" name="estimated_arrival"
                    value="{{ old('estimated_arrival', $invitation->estimated_arrival ?? '') }}"
                    placeholder="09:00 AM"
                    class="w-full border border-gray-300 rounded px-3 py-2">
            </div>

            <!-- New Estimated Departure Field -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Estimated Departure</label>
                <input type="text" name="estimated_departure"
                    value="{{ old('estimated_departure', $invitation->estimated_departure ?? '') }}"
                    placeholder="03:00 PM"
                    class="w-full border border-gray-300 rounded px-3 py-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ $settings->name_label }}</label>
                <input type="text" name="submitted_by_name"
                    value="{{ old('submitted_by_name', $contact->name) }}"
                    class="w-full border border-gray-300 rounded px-3 py-2">
                <p class="text-xs text-gray-500 mt-1">Confirming as {{ $contact->email }}</p>
            </div>

            @if ($customFields->isNotEmpty())
            <div class="space-y-5 border-t border-gray-200 pt-5">
                @foreach ($customFields as $field)
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">{{ $field->label }}</label>
                        <input type="text" name="custom_fields[{{ $field->field_key }}]"
                            value="{{ old('custom_fields.'.$field->field_key, $existingValues[$field->field_key] ?? '') }}"
                            class="w-full border border-gray-300 rounded px-3 py-2">
                    </div>
                @endforeach
            </div>
            @endif

           @if ($invitation->is_locked)
        </fieldset>
    @else
        <button type="submit" class="w-full bg-gray-900 text-white rounded py-2.5 font-medium">
            {{ $settings->submit_button_label }}
        </button>
    @endif
</form>
    </div>
</body>
</html>