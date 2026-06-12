<?php

namespace App\Http\Controllers;

use App\Models\CardProduct;
use App\Models\Drink;
use App\Models\Merchant;
use App\Services\PricingService;
use Illuminate\Contracts\View\View;

/**
 * Eenvoudige publieke café-website (server-rendered) op de Laravel-root:
 * een landing die wijst naar de menukaart en naar de Koffiebon-app.
 */
class SiteController extends Controller
{
    public function home(): View
    {
        return view('site.home', [
            'merchant' => Merchant::first(),
            'products' => $this->products(),
            'frontendUrl' => rtrim((string) config('koffiebon.frontend_url'), '/'),
        ]);
    }

    public function menu(): View
    {
        return view('site.menu', [
            'merchant' => Merchant::first(),
            'drinksByType' => $this->drinksByType(),
            'products' => $this->products(),
            'frontendUrl' => rtrim((string) config('koffiebon.frontend_url'), '/'),
        ]);
    }

    /** Koffiebon-aanbod met afgeleide kaartprijs + cadeau-koppen. */
    private function products(): array
    {
        $pricing = app(PricingService::class);

        return CardProduct::where('active', true)
            ->orderByDesc('cups_total')
            ->get()
            ->map(fn (CardProduct $p) => [
                'name' => $p->name,
                'cups_total' => $p->cups_total,
                'price' => $pricing->cardPriceCents($p->cups_paid, $p->price_per_cup_cents),
                'gift' => $pricing->giftCups($p->cups_total, $p->cups_paid),
                'per_cup' => $p->price_per_cup_cents,
            ])
            ->all();
    }

    /** De drankenkaart, gegroepeerd per koffiesoort met de beschikbare maten. */
    private function drinksByType(): array
    {
        return Drink::where('active', true)
            ->orderBy('type')->orderBy('size')
            ->get()
            ->groupBy(fn (Drink $d) => $d->type->label())
            ->map(fn ($group) => $group->map(fn (Drink $d) => $d->size->label())->all())
            ->all();
    }
}
