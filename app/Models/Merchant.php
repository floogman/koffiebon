<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'timezone'])]
class Merchant extends Model
{
    /** @use HasFactory<\Database\Factories\MerchantFactory> */
    use HasFactory;

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function staffUsers(): HasMany
    {
        return $this->hasMany(StaffUser::class);
    }

    public function cardProducts(): HasMany
    {
        return $this->hasMany(CardProduct::class);
    }

    public function drinks(): HasMany
    {
        return $this->hasMany(Drink::class);
    }
}
