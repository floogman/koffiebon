<?php

namespace App\Http\Controllers\Api\Pwa;

use App\Enums\CardStatus;
use App\Enums\QrPurpose;
use App\Http\Controllers\Controller;
use App\Http\Resources\CardResource;
use App\Http\Resources\CustomerResource;
use App\Models\Card;
use App\Services\QrTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    /** POST /api/pwa/tokens { purpose, card_id? } -> { nonce, expires_at } */
    public function issueToken(Request $request): JsonResponse
    {
        $data = $request->validate([
            'purpose' => ['required', 'in:identify,redeem'],
            'card_id' => ['required_if:purpose,redeem', 'integer'],
        ]);

        $customer = $request->user();
        $purpose = QrPurpose::from($data['purpose']);

        if ($purpose === QrPurpose::Identify) {
            ['nonce' => $nonce, 'token' => $token] = $this->tokens->issueForCustomer($customer);
        } else {
            $card = Card::findOrFail($data['card_id']);
            $this->authorizeOwnership($request, $card);

            abort_unless($card->status === CardStatus::Active, 409, 'Kaart is niet actief.');

            ['nonce' => $nonce, 'token' => $token] = $this->tokens->issueForCard($card);
        }

        return response()->json([
            'nonce' => $nonce,
            'purpose' => $purpose->value,
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
