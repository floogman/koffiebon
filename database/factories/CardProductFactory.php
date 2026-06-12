<?php

namespace Database\Factories;

use App\Models\CardProduct;
use App\Models\Merchant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardProduct>
 */
class CardProductFactory extends Factory
{
    protected $model = CardProduct::class;

    public function definition(): array
    {
        // Default: "12 voor de prijs van 10" tegen € 3,00 per kop.
        return [
            'merchant_id' => Merchant::factory(),
            'name' => '12 voor de prijs van 10',
            'cups_total' => 12,
            'cups_paid' => 10,
            'price_per_cup_cents' => 300,
            'cost_per_cup_cents' => 60,
            'currency' => 'EUR',
            'validity_days' => 730,
            'active' => true,
        ];
    }
}
