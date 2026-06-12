<?php

namespace Database\Factories;

use App\Enums\StaffRole;
use App\Models\Merchant;
use App\Models\StaffUser;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<StaffUser>
 */
class StaffUserFactory extends Factory
{
    protected $model = StaffUser::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'merchant_id' => Merchant::factory(),
            'location_id' => null,
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => StaffRole::Balie,
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => StaffRole::Admin,
        ]);
    }
}
