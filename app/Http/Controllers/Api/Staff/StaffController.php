<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\CardStatus;
use App\Enums\PaymentMethod;
use App\Enums\QrPurpose;
use App\Enums\QrSubjectType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CardProductResource;
use App\Http\Resources\CardResource;
use App\Http\Resources\CustomerResource;
use App\Models\Card;
use App\Models\CardProduct;
use App\Models\Customer;
use App\Services\CardIssuanceService;
use App\Services\QrTokenService;
use App\Services\RedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function __construct(
        private readonly QrTokenService $tokens,
        private readonly RedemptionService $redemption,
        private readonly CardIssuanceService $issuance,
    ) {}

    /** GET /api/staff/products -> actieve card_products van de merchant */
    public function products(Request $request): JsonResponse
    {
        $products = CardProduct::where('merchant_id', $request->user()->merchant_id)
            ->where('active', true)
            ->get();

        return response()->json(['data' => CardProductResource::collection($products)]);
    }

    /**
     * POST /api/staff/scan { nonce }
     * Consumeert de token (single-use) en handelt identify of redeem af.
     */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate(['nonce' => ['required', 'string']]);
        $staff = $request->user();

        $token = $this->tokens->consume($data['nonce']);

        // identify -> start nieuwe-kaart-flow aan de balie.
        if ($token->subject_type === QrSubjectType::Customer || $token->purpose === QrPurpose::Identify) {
            $customer = Customer::with(['cards' => fn ($q) => $q->latest('id'), 'cards.cardProduct'])
                ->findOrFail($token->subject_id);

            $products = CardProduct::where('merchant_id', $staff->merchant_id)
                ->where('active', true)->get();

            return response()->json([
                'type' => 'identify',
                'customer' => new CustomerResource($customer),
                'products' => CardProductResource::collection($products),
            ]);
        }

        // redeem -> verzilver een kop van de kaart.
        $card = Card::with('customer')->findOrFail($token->subject_id);

        if ($card->status === CardStatus::Pending) {
            return response()->json([
                'type' => 'redeem',
                'result' => 'needs_activation',
                'message' => 'Kaart vereist eerst betaling en activatie.',
                'card' => new CardResource($card),
            ], 409);
        }

        $card = $this->redemption->redeemCup($card, $staff);

        return response()->json([
            'type' => 'redeem',
            'result' => 'redeemed',
            'card' => new CardResource($card->load('cardProduct')),
            'customer' => ['id' => $card->customer->id, 'email' => $card->customer->email],
        ]);
    }

    /**
     * POST /api/staff/cards { customer_id, card_product_id, payment{method} }
     * issue + activate + payment in één transactie.
     */
    public function createCard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'card_product_id' => ['required', 'integer'],
            'payment.method' => ['required', 'in:cash,pin'],
        ]);

        $staff = $request->user();
        $customer = Customer::findOrFail($data['customer_id']);
        $product = CardProduct::where('merchant_id', $staff->merchant_id)
            ->findOrFail($data['card_product_id']);

        $card = $this->issuance->issueAndActivate(
            $customer,
            $product,
            PaymentMethod::from($data['payment']['method']),
            $staff,
            $staff->location,
        );

        return response()->json([
            'card' => new CardResource($card->load('cardProduct')),
        ], 201);
    }

    /** POST /api/staff/cards/{card}/activate { payment{method} } */
    public function activateCard(Request $request, Card $card): JsonResponse
    {
        $data = $request->validate([
            'payment.method' => ['required', 'in:cash,pin'],
        ]);

        $card = $this->issuance->activate(
            $card,
            PaymentMethod::from($data['payment']['method']),
            $request->user(),
        );

        return response()->json([
            'card' => new CardResource($card->load('cardProduct')),
        ]);
    }
}
