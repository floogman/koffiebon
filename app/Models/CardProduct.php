<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'merchant_id', 'name', 'cups_total', 'cups_paid',
    'price_per_cup_cents', 'cost_per_cup_cents', 'currency', 'validity_days', 'active',
])]
class CardProduct extends Model
{
    /** @use HasFactory<\Database\Factories\CardProductFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'cups_total' => 'integer',
            'cups_paid' => 'integer',
            'price_per_cup_cents' => 'integer',
            'cost_per_cup_cents' => 'integer',
            'validity_days' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }
}
