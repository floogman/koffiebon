<?php

namespace App\Models;

use App\Enums\QrPurpose;
use App\Enums\QrSubjectType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Kortlevende, single-use QR-token. Alleen de hash van de nonce wordt opgeslagen.
 */
#[Fillable(['subject_type', 'subject_id', 'nonce_hash', 'purpose', 'expires_at', 'consumed_at'])]
class QrToken extends Model
{
    /** @use HasFactory<\Database\Factories\QrTokenFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'subject_type' => QrSubjectType::class,
            'purpose' => QrPurpose::class,
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
