<?php

namespace App\Models;

use App\Enums\CardStatus;
use App\Enums\CoffeeType;
use App\Enums\CupSize;
use Database\Factories\CardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id', 'card_product_id', 'location_id', 'status',
    'preferred_coffee_type', 'preferred_cup_size',
    'cups_total', 'cups_remaining', 'price_paid_cents', 'activated_at', 'expires_at',
])]
class Card extends Model
{
    /** @use HasFactory<CardFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => CardStatus::class,
            'preferred_coffee_type' => CoffeeType::class,
            'preferred_cup_size' => CupSize::class,
            'cups_total' => 'integer',
            'cups_remaining' => 'integer',
            'price_paid_cents' => 'integer',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** Tekstuele weergave van het voorkeursdrankje, bv. "Cappuccino · Medium". */
    public function preferredDrinkLabel(): ?string
    {
        if ($this->preferred_coffee_type === null || $this->preferred_cup_size === null) {
            return null;
        }

        return $this->preferred_coffee_type->label().' · '.$this->preferred_cup_size->label();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function cardProduct(): BelongsTo
    {
        return $this->belongsTo(CardProduct::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CardEvent::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }
}
