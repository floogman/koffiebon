<?php

namespace App\Models;

use App\Enums\CoffeeType;
use App\Enums\CupSize;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['merchant_id', 'type', 'size', 'cost_cents', 'active'])]
class Drink extends Model
{
    /** @use HasFactory<\Database\Factories\DrinkFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'type' => CoffeeType::class,
            'size' => CupSize::class,
            'cost_cents' => 'integer',
            'active' => 'boolean',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function label(): string
    {
        return $this->type->label().' · '.$this->size->label();
    }
}
