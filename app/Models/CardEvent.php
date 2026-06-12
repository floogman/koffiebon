<?php

namespace App\Models;

use App\Enums\CardEventType;
use App\Enums\CoffeeType;
use App\Enums\CupSize;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Onveranderlijk grootboek. Rijen worden nooit gewijzigd; alleen created_at.
 */
#[Fillable(['card_id', 'staff_user_id', 'type', 'cups_delta', 'drink_id', 'coffee_type', 'cup_size', 'cost_cents'])]
class CardEvent extends Model
{
    /** @use HasFactory<\Database\Factories\CardEventFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'type' => CardEventType::class,
            'cups_delta' => 'integer',
            'coffee_type' => CoffeeType::class,
            'cup_size' => CupSize::class,
            'cost_cents' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }

    public function drink(): BelongsTo
    {
        return $this->belongsTo(Drink::class);
    }
}
