<?php

namespace Nexor\Cms\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Nexor\Cms\Models\FeedbackForm;
use Nexor\Cms\Models\FeedbackFormField;
use Nexor\Cms\Support\FeedbackForms;
use Nexor\Cms\Support\FormCaptcha;
use Nexor\Cms\Support\FormTelegram;

/**
 * @mixin FeedbackForm
 */
class FeedbackFormResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'title' => $this->title,
            'button_text' => $this->button_text,
            'success_text' => $this->success_text,
            'mail_template_id' => $this->mail_template_id,
            'mail_template' => $this->whenLoaded('mailTemplate', fn () => $this->mailTemplate ? [
                'id' => $this->mailTemplate->id,
                'code' => $this->mailTemplate->code,
                'name' => $this->mailTemplate->name,
                'to' => $this->mailTemplate->to,
            ] : null),
            'to' => $this->to,
            'agreement_id' => $this->agreement_id,
            'agreement' => $this->whenLoaded('agreement', fn () => $this->agreement ? [
                'id' => $this->agreement->id,
                'name' => $this->agreement->name,
                'is_active' => $this->agreement->is_active,
            ] : null),
            'agreement_popup' => $this->agreement_popup,
            'ajax' => $this->ajax,
            'store_submissions' => $this->store_submissions,
            // Секреты — только маской.
            'telegram' => FormTelegram::forPanel($this->resource),
            'protection' => FormCaptcha::forPanel($this->resource),
            'is_active' => $this->is_active,
            'sort' => $this->sort,
            'fields' => $this->whenLoaded('fields', fn () => $this->fields->map(fn (FeedbackFormField $field) => [
                'id' => $field->id,
                'code' => $field->code,
                'label' => $field->label,
                'type' => $field->type->value,
                'is_required' => $field->is_required,
                'placeholder' => $field->placeholder,
                'settings' => $field->settings ?? (object) [],
                'sort' => $field->sort,
            ])->values()),
            'placeholders' => $this->whenLoaded('fields', fn () => FeedbackForms::placeholderNames($this->resource)),
            'submissions_count' => $this->whenCounted('submissions'),
            'unread_count' => $this->when(isset($this->unread_count), fn () => (int) $this->unread_count),
            'tag' => '<x-nexor::form :id="'.$this->id.'" />',
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
