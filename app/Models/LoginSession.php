<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Wegwerp-sessie voor cross-device login. De PWA bewaart het platte `secret`; hier
 * staat alleen `secret_hash` (tevens de publieke kanaalnaam) en `email_token_hash`.
 * Status loopt van pending → confirmed (na e-mailklik) → consumed (token opgehaald).
 */
#[Fillable(['customer_id', 'secret_hash', 'email_token_hash', 'status', 'expires_at', 'confirmed_at', 'consumed_at'])]
class LoginSession extends Model
{
    public const UPDATED_AT = null;

    public const PENDING = 'pending';
    public const CONFIRMED = 'confirmed';
    public const CONSUMED = 'consumed';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'consumed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
