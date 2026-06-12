<?php

namespace Database\Factories;

use App\Enums\CardStatus;
use App\Models\Card;
use App\Models\CardProduct;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Card>
 */
class CardFactory extends Factory
{
    protected $model = Card::class;

    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'card_product_id' => CardProduct::factory(),
            'location_id' => null,
            'status' => CardStatus::Pending,
            'cups_total' => 12,
            'cups_remaining' => 12,
            'price_paid_cents' => null,
            'activated_at' => null,
            'expires_at' => null,
        ];
    }

    /** Geactiveerde, betaalde kaart met vol saldo. */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CardStatus::Active,
            'price_paid_cents' => 3000,
            'activated_at' => now(),
            'expires_at' => now()->addDays(730),
            'cups_remaining' => $attributes['cups_total'] ?? 12,
        ]);
    }

    /** Laat de kaart een bepaald aantal koppen resterend hebben. */
    public function withRemaining(int $remaining): static
    {
        return $this->state(fn (array $attributes) => [
            'cups_remaining' => $remaining,
        ]);
    }
}
