<?php

namespace App\Http\Resources;

use App\Models\Card;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Card
 */
class CardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'preferred_coffee_type' => $this->preferred_coffee_type?->value,
            'preferred_cup_size' => $this->preferred_cup_size?->value,
            'preferred_drink_label' => $this->preferredDrinkLabel(),
            'cups_total' => $this->cups_total,
            'cups_remaining' => $this->cups_remaining,
            'price_paid_cents' => $this->price_paid_cents,
            'activated_at' => $this->activated_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'product' => $this->whenLoaded('cardProduct', fn () => [
                'id' => $this->cardProduct->id,
                'name' => $this->cardProduct->name,
                'cups_total' => $this->cardProduct->cups_total,
                'cups_paid' => $this->cardProduct->cups_paid,
                'price_per_cup_cents' => $this->cardProduct->price_per_cup_cents,
                'currency' => $this->cardProduct->currency,
            ]),
        ];
    }
}
