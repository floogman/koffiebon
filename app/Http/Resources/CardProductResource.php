<?php

namespace App\Http\Resources;

use App\Models\CardProduct;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CardProduct
 */
class CardProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $pricing = app(PricingService::class)->forProduct($this->resource);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'cups_total' => $this->cups_total,
            'cups_paid' => $this->cups_paid,
            'price_per_cup_cents' => $this->price_per_cup_cents,
            'currency' => $this->currency,
            'validity_days' => $this->validity_days,
            'card_price_cents' => $pricing['card_price_cents'],
            'gift_cups' => $pricing['gift_cups'],
            'discount_rate' => round($pricing['discount_rate'], 4),
        ];
    }
}
