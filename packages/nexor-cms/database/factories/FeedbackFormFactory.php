<?php

namespace Nexor\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Cms\Models\FeedbackForm;

/**
 * @extends Factory<FeedbackForm>
 */
class FeedbackFormFactory extends Factory
{
    /** @var class-string<FeedbackForm> */
    protected $model = FeedbackForm::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'form-'.fake()->unique()->numberBetween(1, 99999),
            'name' => 'Обратный звонок',
            'title' => 'Заказать звонок',
            'button_text' => 'Отправить',
            'success_text' => 'Спасибо! Мы перезвоним.',
            'to' => 'manager@example.com',
            'agreement_popup' => true,
            'ajax' => false,
            'store_submissions' => true,
            'is_active' => true,
            'sort' => 500,
        ];
    }
}
