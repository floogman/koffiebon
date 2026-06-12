<?php

namespace Database\Seeders;

use App\Enums\CardEventType;
use App\Enums\CardStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Models\Card;
use App\Models\CardProduct;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\StaffUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo-data voor lokaal draaien: één merchant met een balie-user, een product,
     * en een geverifieerde klant met een actieve voorbeeldkaart (9/12).
     */
    public function run(): void
    {
        $merchant = Merchant::factory()->create([
            'name' => 'Espresso Bar Demo',
            'timezone' => 'Europe/Amsterdam',
        ]);

        $location = Location::factory()->for($merchant)->create([
            'name' => 'Centrum',
        ]);

        StaffUser::factory()->for($merchant)->for($location)->create([
            'name' => 'Admin Demo',
            'email' => 'admin@koffiebon.test',
            'password' => Hash::make('password'),
            'role' => StaffRole::Admin,
        ]);

        StaffUser::factory()->for($merchant)->for($location)->create([
            'name' => 'Balie Demo',
            'email' => 'balie@koffiebon.test',
            'password' => Hash::make('password'),
            'role' => StaffRole::Balie,
        ]);

        $product = CardProduct::factory()->for($merchant)->create([
            'name' => '12 voor de prijs van 10',
            'cups_total' => 12,
            'cups_paid' => 10,
            'price_per_cup_cents' => 300,
            'cost_per_cup_cents' => 60,
        ]);

        $customer = Customer::factory()->create([
            'email' => 'klant@koffiebon.test',
            'name' => 'Demo Klant',
            'email_verified_at' => now(),
        ]);

        // Actieve voorbeeldkaart met 3 verzilverde koppen (9/12 resterend).
        $card = Card::factory()
            ->for($customer)
            ->for($product, 'cardProduct')
            ->for($location)
            ->create([
                'status' => CardStatus::Active,
                'cups_total' => $product->cups_total,
                'cups_remaining' => 9,
                'price_paid_cents' => $product->cups_paid * $product->price_per_cup_cents,
                'activated_at' => now()->subDays(5),
                'expires_at' => now()->addDays($product->validity_days),
            ]);

        // Grootboek: uitgifte + activatie (delta 0) + 3 verzilveringen (delta -1).
        $card->events()->create(['type' => CardEventType::Issue, 'cups_delta' => 0, 'created_at' => now()->subDays(5)]);
        $card->events()->create(['type' => CardEventType::Activate, 'cups_delta' => 0, 'created_at' => now()->subDays(5)]);
        foreach (range(1, 3) as $i) {
            $card->events()->create([
                'type' => CardEventType::Redeem,
                'cups_delta' => -1,
                'created_at' => now()->subDays(5 - $i),
            ]);
        }

        Payment::factory()->for($card)->create([
            'method' => PaymentMethod::Pin,
            'amount_cents' => $card->price_paid_cents,
            'status' => PaymentStatus::Recorded,
        ]);
    }
}
