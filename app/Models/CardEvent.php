<?php

namespace App\Models;

use App\Enums\CardEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Onveranderlijk grootboek. Rijen worden nooit gewijzigd; alleen created_at.
 */
#[Fillable(['card_id', 'staff_user_id', 'type', 'cups_delta'])]
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
}
