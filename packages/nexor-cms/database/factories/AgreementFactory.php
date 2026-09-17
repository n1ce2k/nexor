<?php

namespace Nexor\Cms\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Cms\Models\Agreement;

/**
 * @extends Factory<Agreement>
 */
class AgreementFactory extends Factory
{
    /** @var class-string<Agreement> */
    protected $model = Agreement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'agreement-'.fake()->unique()->numberBetween(1, 99999),
            'name' => 'Согласие на обработку персональных данных',
            'label' => 'Я согласен на обработку персональных данных',
            'link_text' => 'обработку персональных данных',
            'text' => '<p>Текст соглашения.</p>',
            'text_type' => 'html',
            'is_active' => true,
            'sort' => 500,
        ];
    }
}
