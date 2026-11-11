<?php

namespace App\Filament\Pages\Concerns;

use App\Models\CustomFieldDefinition;
use App\Models\FormSettings;
use App\Models\Invitation;
use App\Models\InvitationContact;
use App\Models\Meeting;
use Illuminate\Support\Collection;

/**
 * Renders the real guest RSVP page with sample data and the settings as they
 * are on screen right now (unsaved), for the preview panel beside the form.
 */
trait HasRsvpPreview
{
    /** 'event' (Big Event RSVP) or 'meeting' */
    public string $previewAs = 'event';

    public bool $previewSubmitted = false;

    public function setPreviewAs(string $as): void
    {
        $this->previewAs = $as === 'meeting' ? 'meeting' : 'event';
    }

    public function setPreviewSubmitted(bool $submitted): void
    {
        $this->previewSubmitted = $submitted;
    }

    /** Settings shown in the preview; pages override to use their unsaved form state. */
    protected function previewSettings(): FormSettings
    {
        return FormSettings::current();
    }

    /** Extra questions shown in the preview; pages override to use their unsaved form state. */
    protected function previewCustomFields(): Collection
    {
        return CustomFieldDefinition::where('enabled', true)->orderBy('sort_order')->get();
    }

    public function previewHtml(): string
    {
        $invitation = new Invitation([
            'vip_name' => "Dato' Sri Example",
            'organization' => 'Ministry of Example',
            'attendance_status' => 'pending',
        ]);

        if ($this->previewAs === 'meeting') {
            $invitation->setRelation('meeting', new Meeting([
                'title' => 'Sample Meeting',
                'starts_at' => now()->addWeek()->setTime(10, 0),
                'location' => 'Meeting Room 1',
            ]));
        } else {
            $invitation->setRelation('meeting', null);
        }

        $contact = new InvitationContact([
            'name' => 'Aisyah (PA)',
            'email' => 'pa@example.gov.my',
            'token' => 'preview',
        ]);

        return view('rsvp.show', [
            'invitation' => $invitation,
            'contact' => $contact,
            'settings' => $this->previewSettings(),
            'customFields' => $this->previewCustomFields(),
            'existingValues' => collect(),
            'preview' => true,
            'previewSubmitted' => $this->previewSubmitted,
        ])->render();
    }
}
