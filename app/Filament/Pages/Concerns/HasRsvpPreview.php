<?php

namespace App\Filament\Pages\Concerns;

use App\Http\Middleware\SetLocale;
use App\Models\CustomFieldDefinition;
use App\Models\FormSettings;
use App\Models\Invitation;
use App\Models\InvitationContact;
use App\Models\Meeting;
use Illuminate\Support\Collection;

trait HasRsvpPreview
{
    public string $previewAs = 'event';

    public bool $previewSubmitted = false;

    public ?string $previewLanguage = null;

    public function setPreviewAs(string $as): void
    {
        $this->previewAs = $as === 'meeting' ? 'meeting' : 'event';
    }

    public function setPreviewSubmitted(bool $submitted): void
    {
        $this->previewSubmitted = $submitted;
    }

    public function setPreviewLanguage(string $locale): void
    {
        $this->previewLanguage = isset(SetLocale::LOCALES[$locale]) ? $locale : null;
    }

    public function previewLocale(): string
    {
        return $this->previewLanguage ?? app()->getLocale();
    }

    protected function previewSettings(): FormSettings
    {
        return FormSettings::current()->localized($this->previewLocale());
    }

    protected function previewCustomFields(): Collection
    {
        return CustomFieldDefinition::where('enabled', true)->orderBy('sort_order')->get();
    }

    public function previewHtml(): string
    {
        $adminLocale = app()->getLocale();
        $settings = $this->previewSettings();
        $customFields = $this->previewCustomFields();
        SetLocale::apply($this->previewLocale());

        try {
            $invitation = new Invitation([
                'vip_name' => "Dato' Sri Example",
                'organization' => 'Ministry of Example',
                'attendance_status' => 'pending',
            ]);

            if ($this->previewAs === 'meeting') {
                $invitation->setRelation('meeting', new Meeting([
                    'title' => __('Sample Meeting'),
                    'starts_at' => now()->addWeek()->setTime(10, 0),
                    'location' => __('Meeting Room 1'),
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
                'settings' => $settings,
                'customFields' => $customFields,
                'existingValues' => collect(),
                'preview' => true,
                'previewSubmitted' => $this->previewSubmitted,
            ])->render();
        } finally {
            SetLocale::apply($adminLocale);
        }
    }
}
