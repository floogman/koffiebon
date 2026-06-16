<?php

namespace App\Http\Controllers\Api\Pwa;

use App\Enums\CardStatus;
use App\Enums\CoffeeType;
use App\Enums\CupSize;
use App\Enums\QrPurpose;
use App\Http\Controllers\Controller;
use App\Http\Resources\CardResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\DrinkResource;
use App\Models\Card;
use App\Models\Drink;
use App\Services\QrTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PwaController extends Controller
{
    public function __construct(private readonly QrTokenService $tokens) {}

    /** GET /api/pwa/me -> customer + kaarten + saldi */
    public function me(Request $request): CustomerResource
    {
        $customer = $request->user();
        $customer->load(['cards' => fn ($q) => $q->latest('id'), 'cards.cardProduct']);

        return new CustomerResource($customer);
    }

    /** GET /api/pwa/cards/{card} -> kaartdetail */
    public function card(Request $request, Card $card): CardResource
    {
        $this->authorizeOwnership($request, $card);
        $card->load('cardProduct');

        return new CardResource($card);
    }

    /** GET /api/pwa/drinks -> actieve drankenkaart voor de keuze bij het kopen van een kaart */
    public function drinks(): JsonResponse
    {
        // Single-merchant MVP: de hele actieve drankenkaart. Bij multi-merchant (fase 3)
        // scopen we dit op de merchant van de gekozen vestiging/kaart.
        $drinks = Drink::query()
            ->where('active', true)
            ->orderBy('type')->orderBy('size')
            ->get();

        return response()->json(['data' => DrinkResource::collection($drinks)]);
    }

    /** POST /api/pwa/tokens { purpose, card_id?, preferred_coffee_type?, preferred_cup_size? } */
    public function issueToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['required', 'in:identify,redeem'],
            'card_id' => ['required_if:purpose,redeem', 'integer'],
            // Verplicht bij het kopen van een kaart: de klant kiest het vaste drankje vooraf.
            'preferred_coffee_type' => ['required_if:purpose,identify', Rule::enum(CoffeeType::class)],
            'preferred_cup_size' => ['required_if:purpose,identify', Rule::enum(CupSize::class)],
        ]);

        $customer = $request->user();
        $purpose = QrPurpose::from($data['purpose']);

        if ($purpose === QrPurpose::Identify) {
            ['nonce' => $nonce, 'code' => $code, 'token' => $token] = $this->tokens->issueForCustomer(
                $customer,
                CoffeeType::from($data['preferred_coffee_type']),
                CupSize::from($data['preferred_cup_size']),
            );
        } else {
            $card = Card::findOrFail($data['card_id']);
            $this->authorizeOwnership($request, $card);

            abort_unless($card->status === CardStatus::Active, 409, 'Kaart is niet actief.');

            ['nonce' => $nonce, 'code' => $code, 'token' => $token] = $this->tokens->issueForCard($card);
        }

        return response()->json([
            'nonce' => $nonce,
            // 6-cijferige code die de balie ook met de hand kan intypen.
            'code' => $code,
            'purpose' => $purpose->value,
            // Tekstueel drankje dat bij deze QR/kaart hoort (identify: de gekozen drank;
            // redeem: het vaste drankje van de kaart).
            'preferred_drink' => $purpose === QrPurpose::Identify
                ? $token->preferred_coffee_type->label().' · '.$token->preferred_cup_size->label()
                : $card->preferredDrinkLabel(),
            'expires_at' => $token->expires_at->toIso8601String(),
            // Deeplink zodat ook een gewone camera werkt.
            'url' => rtrim((string) config('koffiebon.frontend_url'), '/').'/s/'.$nonce,
        ]);
    }

    private function authorizeOwnership(Request $request, Card $card): void
    {
        abort_unless($card->customer_id === $request->user()->id, 403, 'Geen toegang tot deze kaart.');
    }
}
