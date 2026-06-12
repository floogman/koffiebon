<?php

namespace Database\Seeders;

use App\Enums\CardEventType;
use App\Enums\CardStatus;
use App\Enums\CoffeeType;
use App\Enums\CupSize;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\StaffRole;
use App\Models\Card;
use App\Models\CardProduct;
use App\Models\Customer;
use App\Models\Drink;
use App\Models\Location;
use App\Models\Merchant;
use App\Models\Payment;
use App\Models\StaffUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Demo-data voor fase 3: één merchant met twee vestigingen, een volledige
     * drankenkaart (4 soorten × 3 maten) en een realistische verzilver-historie
     * over ~30 dagen, zodat het merchant-dashboard echte cijfers toont.
     */
    public function run(): void
    {
        $merchant = Merchant::factory()->create([
            'name' => 'Espresso Bar Demo',
            'timezone' => 'Europe/Amsterdam',
        ]);

        $centrum = Location::factory()->for($merchant)->create(['name' => 'Centrum']);
        $station = Location::factory()->for($merchant)->create(['name' => 'Station']);

        // Admin ziet beide vestigingen (location_id = null).
        StaffUser::factory()->for($merchant)->create([
            'location_id' => null,
            'name' => 'Admin Demo',
            'email' => 'admin@koffiebon.test',
            'password' => Hash::make('password'),
            'role' => StaffRole::Admin,
        ]);

        $balieCentrum = StaffUser::factory()->for($merchant)->for($centrum)->create([
            'name' => 'Balie Centrum',
            'email' => 'balie@koffiebon.test',
            'password' => Hash::make('password'),
            'role' => StaffRole::Balie,
        ]);
        $balieStation = StaffUser::factory()->for($merchant)->for($station)->create([
            'name' => 'Balie Station',
            'email' => 'station@koffiebon.test',
            'password' => Hash::make('password'),
            'role' => StaffRole::Balie,
        ]);
        $balie = [$centrum->id => $balieCentrum, $station->id => $balieStation];

        $drinks = $this->seedDrinks($merchant);

        $products = collect([
            ['name' => '12 voor de prijs van 10', 'cups_total' => 12, 'cups_paid' => 10, 'price_per_cup_cents' => 300],
            ['name' => '6 voor de prijs van 5', 'cups_total' => 6, 'cups_paid' => 5, 'price_per_cup_cents' => 320],
            ['name' => '10 koppen', 'cups_total' => 10, 'cups_paid' => 10, 'price_per_cup_cents' => 300],
        ])->map(fn ($p) => CardProduct::factory()->for($merchant)->create($p + ['cost_per_cup_cents' => 55]));

        $locations = [$centrum, $station];

        // Vaste demo-klant met een herkenbare 9/12-kaart aan het Centrum.
        $demo = Customer::factory()->create([
            'email' => 'klant@koffiebon.test',
            'name' => 'Demo Klant',
            'email_verified_at' => now(),
        ]);
        $this->makeCard($demo, $products[0], $centrum, $balieCentrum, $drinks, redeems: 3, daysAgoActivated: 12);

        // Een groep klanten met realistische historie verspreid over beide vestigingen.
        Customer::factory()->count(13)->create()->each(function (Customer $customer) use ($products, $locations, $balie, $drinks) {
            $cardCount = random_int(1, 2); // sommige klanten komen terug (meerdere kaarten)
            for ($i = 0; $i < $cardCount; $i++) {
                $location = $locations[array_rand($locations)];
                $product = $products[array_rand($products->all())];
                $redeems = random_int(0, $product->cups_total);
                $this->makeCard(
                    $customer,
                    $product,
                    $location,
                    $balie[$location->id],
                    $drinks,
                    redeems: $redeems,
                    daysAgoActivated: random_int(1, 28),
                );
            }
        });
    }

    /** Drankenkaart: 4 soorten × 3 maten met een kostprijs per drankje. */
    private function seedDrinks(Merchant $merchant): array
    {
        $cost = [
            CoffeeType::Regular->value => [CupSize::Small->value => 30, CupSize::Medium->value => 38, CupSize::Large->value => 46],
            CoffeeType::Espresso->value => [CupSize::Small->value => 28, CupSize::Medium->value => 32, CupSize::Large->value => 38],
            CoffeeType::Cappuccino->value => [CupSize::Small->value => 48, CupSize::Medium->value => 58, CupSize::Large->value => 68],
            CoffeeType::FlatWhite->value => [CupSize::Small->value => 52, CupSize::Medium->value => 62, CupSize::Large->value => 72],
        ];

        $drinks = [];
        foreach (CoffeeType::cases() as $type) {
            foreach (CupSize::cases() as $size) {
                $drinks[] = Drink::factory()->for($merchant)->create([
                    'type' => $type,
                    'size' => $size,
                    'cost_cents' => $cost[$type->value][$size->value],
                ]);
            }
        }

        return $drinks;
    }

    /**
     * Maakt een actieve kaart met issue/activate/payment en een aantal verzilveringen
     * (met willekeurige drankjes en tijdstippen) zodat cache == grootboek blijft.
     */
    private function makeCard(
        Customer $customer,
        CardProduct $product,
        Location $location,
        StaffUser $staff,
        array $drinks,
        int $redeems,
        int $daysAgoActivated,
    ): Card {
        $redeems = min($redeems, $product->cups_total);
        $activatedAt = now()->subDays($daysAgoActivated)->setTime(random_int(8, 17), random_int(0, 59));
        $depleted = $redeems >= $product->cups_total;

        $card = Card::factory()->for($customer)->for($product, 'cardProduct')->for($location)->create([
            'status' => $depleted ? CardStatus::Depleted : CardStatus::Active,
            'cups_total' => $product->cups_total,
            'cups_remaining' => $product->cups_total - $redeems,
            'price_paid_cents' => $product->cups_paid * $product->price_per_cup_cents,
            'activated_at' => $activatedAt,
            'expires_at' => $activatedAt->copy()->addDays($product->validity_days),
        ]);

        $card->events()->create(['type' => CardEventType::Issue, 'cups_delta' => 0, 'staff_user_id' => $staff->id, 'created_at' => $activatedAt]);
        $card->events()->create(['type' => CardEventType::Activate, 'cups_delta' => 0, 'staff_user_id' => $staff->id, 'created_at' => $activatedAt]);

        Payment::factory()->for($card)->create([
            'method' => array_rand([PaymentMethod::Pin->value => 1, PaymentMethod::Cash->value => 1]) === PaymentMethod::Pin->value ? PaymentMethod::Pin : PaymentMethod::Cash,
            'amount_cents' => $card->price_paid_cents,
            'status' => PaymentStatus::Recorded,
            'created_at' => $activatedAt,
        ]);

        // Verzilveringen verspreid tussen activatie en nu, met midden-op-de-dag-bias.
        for ($i = 1; $i <= $redeems; $i++) {
            $drink = $this->pickDrink($drinks);
            $when = $activatedAt->copy()
                ->addDays((int) floor(($daysAgoActivated * $i) / max(1, $redeems + 1)))
                ->setTime($this->busyHour(), random_int(0, 59));

            $card->events()->create([
                'type' => CardEventType::Redeem,
                'cups_delta' => -1,
                'staff_user_id' => $staff->id,
                'drink_id' => $drink->id,
                'coffee_type' => $drink->type,
                'cup_size' => $drink->size,
                'cost_cents' => $drink->cost_cents,
                'created_at' => $when->isFuture() ? now() : $when,
            ]);
        }

        return $card;
    }

    /** Cappuccino is populairder; espresso het minst. */
    private function pickDrink(array $drinks): Drink
    {
        $weights = [
            CoffeeType::Cappuccino->value => 5,
            CoffeeType::Regular->value => 4,
            CoffeeType::FlatWhite->value => 3,
            CoffeeType::Espresso->value => 2,
        ];
        $bag = [];
        foreach ($drinks as $i => $drink) {
            $w = $weights[$drink->type->value] ?? 1;
            for ($k = 0; $k < $w; $k++) {
                $bag[] = $i;
            }
        }

        return $drinks[$bag[array_rand($bag)]];
    }

    /** Spitsuren rond 8-9u en 12-14u. */
    private function busyHour(): int
    {
        $hours = [8, 8, 9, 9, 10, 11, 12, 12, 13, 13, 14, 15, 16];

        return $hours[array_rand($hours)];
    }
}
