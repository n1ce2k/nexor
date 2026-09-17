<?php

namespace Nexor\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Cms\Models\FeedbackForm;
use Nexor\Cms\Models\FeedbackFormField;

/**
 * @extends Factory<FeedbackFormField>
 */
class FeedbackFormFieldFactory extends Factory
{
    /** @var class-string<FeedbackFormField> */
    protected $model = FeedbackFormField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'form_id' => FeedbackForm::factory(),
            'code' => 'field_'.fake()->unique()->numberBetween(1, 99999),
            'label' => 'Поле',
            'type' => 'string',
            'is_required' => false,
            'sort' => 500,
        ];
    }
}
