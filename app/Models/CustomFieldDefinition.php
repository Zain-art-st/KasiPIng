<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomFieldDefinition extends Model
{
    protected $fillable = [
        'field_key',
        'label',
        'sort_order',
        'enabled',
    ];

    protected $casts = [
        'enabled' => 'boolean',
    ];

    public ?string $previewLabelMs = null;

    public function malayLabel(): ?string
    {
        return $this->previewLabelMs ?? (AppSetting::getJson(AppSetting::QUESTION_LABELS_MS)[$this->field_key] ?? null);
    }

    public function localizedLabel(?string $locale = null): string
    {
        $malay = ($locale ?? app()->getLocale()) === 'ms' ? $this->malayLabel() : null;

        return filled($malay) ? $malay : (string) $this->label;
    }
}
