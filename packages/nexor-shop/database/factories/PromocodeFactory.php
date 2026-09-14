<?php

namespace Nexor\Shop\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Nexor\Shop\Enums\PromocodeScope;
use Nexor\Shop\Enums\PromocodeType;
use Nexor\Shop\Models\Promocode;

/**
 * @extends Factory<Promocode>
 */
class PromocodeFactory extends Factory
{
    /** @var class-string<Promocode> */
    protected $model = Promocode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->bothify('SALE####')),
            'description' => null,
            'type' => PromocodeType::Percent,
            'value' => 10,
            'min_sum' => null,
            'starts_at' => null,
            'ends_at' => null,
            'usage_limit' => null,
            'scope' => PromocodeScope::All,
            'section_ids' => null,
            'element_ids' => null,
            'is_active' => true,
        ];
    }

    public function fixed(float $amount): static
    {
        return $this->state(['type' => PromocodeType::Fixed, 'value' => $amount]);
    }

    public function percent(float $percent): static
    {
        return $this->state(['type' => PromocodeType::Percent, 'value' => $percent]);
    }
}
