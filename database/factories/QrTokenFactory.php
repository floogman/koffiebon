<?php

namespace Database\Factories;

use App\Enums\QrPurpose;
use App\Enums\QrSubjectType;
use App\Models\Card;
use App\Models\QrToken;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<QrToken>
 */
class QrTokenFactory extends Factory
{
    protected $model = QrToken::class;

    public function definition(): array
    {
        return [
            'subject_type' => QrSubjectType::Card,
            'subject_id' => Card::factory(),
            'nonce_hash' => hash('sha256', Str::random(40)),
            'code_hash' => hash('sha256', (string) $this->faker->numberBetween(100000, 999999)),
            'purpose' => QrPurpose::Redeem,
            'expires_at' => now()->addSeconds(60),
            'consumed_at' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subSecond(),
        ]);
    }

    public function consumed(): static
    {
        return $this->state(fn (array $attributes) => [
            'consumed_at' => now(),
        ]);
    }
}
