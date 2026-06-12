<?php

namespace Database\Factories;

use App\Enums\CoffeeType;
use App\Enums\CupSize;
use App\Models\Drink;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Drink>
 */
class DrinkFactory extends Factory
{
    protected $model = Drink::class;

    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'type' => CoffeeType::Cappuccino,
            'size' => CupSize::Medium,
            'cost_cents' => 60,
            'active' => true,
        ];
    }
}
