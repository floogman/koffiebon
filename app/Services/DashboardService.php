<?php

namespace App\Services;

use App\Enums\CardStatus;
use App\Enums\CoffeeType;
use App\Enums\CupSize;
use App\Models\CardProduct;
use App\Models\Location;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Aggregeert echte cijfers uit het grootboek voor het merchant-dashboard:
 * omzet, openstaande verplichting (koppen), drukte per dag, populairste drankjes
 * en terugkerende klanten — optioneel gefilterd op vestiging en periode.
 */
class DashboardService
{
    public function build(int $merchantId, ?int $locationId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $productIds = CardProduct::where('merchant_id', $merchantId)->pluck('id')->all();

        return [
            'range' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'locations' => Location::where('merchant_id', $merchantId)
                ->get(['id', 'name'])
                ->map(fn ($l) => ['id' => $l->id, 'name' => $l->name])
                ->all(),
            'summary' => $this->summary($productIds, $locationId, $from, $to),
            'by_location' => $this->byLocation($merchantId, $productIds, $from, $to),
            'by_drink' => $this->byDrink($productIds, $locationId, $from, $to),
            'activity' => $this->activity($productIds, $locationId, $from, $to),
            'customers' => $this->customers($productIds, $locationId),
        ];
    }

    private function cardScope(array $productIds, ?int $locationId)
    {
        return DB::table('cards')
            ->whereIn('card_product_id', $productIds)
            ->when($locationId, fn ($q) => $q->where('location_id', $locationId));
    }

    private function redeemScope(array $productIds, ?int $locationId, CarbonImmutable $from, CarbonImmutable $to)
    {
        return DB::table('card_events')
            ->join('cards', 'cards.id', '=', 'card_events.card_id')
            ->where('card_events.type', 'redeem')
            ->whereIn('cards.card_product_id', $productIds)
            ->whereBetween('card_events.created_at', [$from, $to])
            ->when($locationId, fn ($q) => $q->where('cards.location_id', $locationId));
    }

    private function summary(array $productIds, ?int $locationId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $cardsSold = (clone $this->cardScope($productIds, $locationId))->count();
        $activeCards = (clone $this->cardScope($productIds, $locationId))->where('status', CardStatus::Active->value)->count();
        // Openstaande verplichting = nog te schenken koppen (huidige stand, niet periode-gebonden).
        $outstanding = (int) (clone $this->cardScope($productIds, $locationId))->where('status', CardStatus::Active->value)->sum('cups_remaining');

        $redeemed = (clone $this->redeemScope($productIds, $locationId, $from, $to))->count();
        $drinkCost = (int) (clone $this->redeemScope($productIds, $locationId, $from, $to))->sum('card_events.cost_cents');

        $revenue = (int) DB::table('payments')
            ->join('cards', 'cards.id', '=', 'payments.card_id')
            ->whereIn('cards.card_product_id', $productIds)
            ->when($locationId, fn ($q) => $q->where('cards.location_id', $locationId))
            ->whereIn('payments.status', ['recorded', 'paid'])
            ->whereBetween('payments.created_at', [$from, $to])
            ->sum('payments.amount_cents');

        return [
            'cards_sold' => $cardsSold,
            'active_cards' => $activeCards,
            'cups_outstanding' => $outstanding,
            'cups_redeemed' => $redeemed,
            'revenue_cents' => $revenue,
            'drink_cost_cents' => $drinkCost,
            'liability_cents' => null, // verplichting in geld is afhankelijk van kaartprijs; koppen zijn de eenheid
        ];
    }

    private function byLocation(int $merchantId, array $productIds, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return Location::where('merchant_id', $merchantId)
            ->get()
            ->map(function (Location $l) use ($productIds, $from, $to) {
                $redeemed = (clone $this->redeemScope($productIds, $l->id, $from, $to))->count();
                $outstanding = (int) (clone $this->cardScope($productIds, $l->id))->where('status', CardStatus::Active->value)->sum('cups_remaining');
                $revenue = (int) DB::table('payments')
                    ->join('cards', 'cards.id', '=', 'payments.card_id')
                    ->whereIn('cards.card_product_id', $productIds)
                    ->where('cards.location_id', $l->id)
                    ->whereIn('payments.status', ['recorded', 'paid'])
                    ->whereBetween('payments.created_at', [$from, $to])
                    ->sum('payments.amount_cents');

                return [
                    'id' => $l->id,
                    'name' => $l->name,
                    'cards' => (clone $this->cardScope($productIds, $l->id))->count(),
                    'cups_redeemed' => $redeemed,
                    'cups_outstanding' => $outstanding,
                    'revenue_cents' => $revenue,
                ];
            })
            ->values()
            ->all();
    }

    private function byDrink(array $productIds, ?int $locationId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $rows = (clone $this->redeemScope($productIds, $locationId, $from, $to))
            ->whereNotNull('card_events.coffee_type')
            ->selectRaw('card_events.coffee_type as type, card_events.cup_size as size, count(*) as c')
            ->groupBy('card_events.coffee_type', 'card_events.cup_size')
            ->get();

        $byType = [];
        foreach (CoffeeType::cases() as $type) {
            $sizes = [];
            foreach (CupSize::cases() as $size) {
                $sizes[$size->value] = 0;
            }
            $byType[$type->value] = ['type' => $type->value, 'label' => $type->label(), 'sizes' => $sizes, 'total' => 0];
        }
        foreach ($rows as $r) {
            if (! isset($byType[$r->type])) {
                continue;
            }
            $byType[$r->type]['sizes'][$r->size] = (int) $r->c;
            $byType[$r->type]['total'] += (int) $r->c;
        }

        $bySize = [];
        foreach (CupSize::cases() as $size) {
            $bySize[$size->value] = ['size' => $size->value, 'label' => $size->label(), 'count' => 0];
        }
        foreach ($rows as $r) {
            if (isset($bySize[$r->size])) {
                $bySize[$r->size]['count'] += (int) $r->c;
            }
        }

        return ['by_type' => array_values($byType), 'by_size' => array_values($bySize)];
    }

    private function activity(array $productIds, ?int $locationId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $dates = (clone $this->redeemScope($productIds, $locationId, $from, $to))
            ->pluck('card_events.created_at')
            ->map(fn ($d) => CarbonImmutable::parse($d)->toDateString())
            ->countBy()
            ->all();

        $series = [];
        for ($day = $from->startOfDay(); $day->lessThanOrEqualTo($to); $day = $day->addDay()) {
            $key = $day->toDateString();
            $series[] = ['date' => $key, 'count' => $dates[$key] ?? 0];
        }

        return $series;
    }

    private function customers(array $productIds, ?int $locationId): array
    {
        $cardsByCustomer = (clone $this->cardScope($productIds, $locationId))
            ->selectRaw('customer_id, count(*) as c, sum(cups_total - cups_remaining) as redeemed')
            ->groupBy('customer_id')
            ->get();

        $total = $cardsByCustomer->count();
        $returning = $cardsByCustomer->where('c', '>', 1)->count();
        $totalRedeemed = (int) $cardsByCustomer->sum('redeemed');

        return [
            'total' => $total,
            'returning' => $returning,
            'one_time' => $total - $returning,
            'avg_cups_per_customer' => $total > 0 ? round($totalRedeemed / $total, 1) : 0,
        ];
    }
}
