<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Card;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'method' => PaymentMethod::Pin,
            'amount_cents' => 3000,
            'status' => PaymentStatus::Recorded,
            'mollie_id' => null,
        ];
    }
}
