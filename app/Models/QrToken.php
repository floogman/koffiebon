<?php

namespace App\Models;

use App\Enums\CoffeeType;
use App\Enums\CupSize;
use App\Enums\QrPurpose;
use App\Enums\QrSubjectType;
use Database\Factories\QrTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Kortlevende, single-use QR-token. Alleen de hash van de nonce wordt opgeslagen.
 */
#[Fillable([
    'subject_type', 'subject_id', 'nonce_hash', 'code_hash', 'purpose',
    'preferred_coffee_type', 'preferred_cup_size', 'expires_at', 'consumed_at',
])]
class QrToken extends Model
{
    /** @use HasFactory<QrTokenFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'subject_type' => QrSubjectType::class,
            'purpose' => QrPurpose::class,
            'preferred_coffee_type' => CoffeeType::class,
            'preferred_cup_size' => CupSize::class,
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function isConsumed(): bool
    {
        return $this->consumed_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isUsable(): bool
    {
        return ! $this->isConsumed() && ! $this->isExpired();
    }
}
