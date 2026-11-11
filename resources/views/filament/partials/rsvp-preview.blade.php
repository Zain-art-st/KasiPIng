{{-- Live preview of the guest RSVP page. Expects a Livewire page using HasRsvpPreview. --}}
<div class="rsvp-preview">
    <div style="display:flex; flex-wrap:wrap; gap:.5rem; align-items:center; margin-bottom:.75rem;">
        <span style="font-weight:600; font-size:.875rem; margin-right:auto;">Live preview: what guests see</span>

        <x-filament::button size="xs" :color="$this->previewAs === 'event' ? 'primary' : 'gray'" wire:click="setPreviewAs('event')">
            Big event
        </x-filament::button>
        <x-filament::button size="xs" :color="$this->previewAs === 'meeting' ? 'primary' : 'gray'" wire:click="setPreviewAs('meeting')">
            Meeting
        </x-filament::button>

        <span style="width:1px; height:1.25rem; background:rgba(127,127,127,.35);"></span>

        <x-filament::button size="xs" :color="! $this->previewSubmitted ? 'primary' : 'gray'" wire:click="setPreviewSubmitted(false)">
            Form
        </x-filament::button>
        <x-filament::button size="xs" :color="$this->previewSubmitted ? 'primary' : 'gray'" wire:click="setPreviewSubmitted(true)">
            After submitting
        </x-filament::button>
    </div>

    <iframe
        title="RSVP form preview"
        srcdoc="{{ $this->previewHtml() }}"
        sandbox="allow-scripts"
        style="width:100%; height:820px; border:1px solid rgba(127,127,127,.3); border-radius:.75rem; background:#f3f4f6;"
    ></iframe>

    <p style="font-size:.75rem; opacity:.7; margin-top:.5rem;">
        Sample guest and PA. Updates as you type; nothing here is saved or sent until you press Save.
    </p>
</div>
