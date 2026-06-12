<?php

namespace Database\Factories;

use App\Enums\CardEventType;
use App\Models\Card;
use App\Models\CardEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CardEvent>
 */
class CardEventFactory extends Factory
{
    protected $model = CardEvent::class;

    public function definition(): array
    {
        return [
            'card_id' => Card::factory(),
            'staff_user_id' => null,
            'type' => CardEventType::Redeem,
            'cups_delta' => -1,
        ];
    }
}
