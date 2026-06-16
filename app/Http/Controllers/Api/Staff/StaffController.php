<?php

namespace App\Http\Controllers\Api\Staff;

use App\Enums\CardStatus;
use App\Enums\CoffeeType;
use App\Enums\CupSize;
use App\Enums\PaymentMethod;
use App\Enums\QrPurpose;
use App\Enums\QrSubjectType;
use App\Http\Controllers\Controller;
use App\Http\Resources\CardProductResource;
use App\Http\Resources\CardResource;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\DrinkResource;
use App\Models\Card;
use App\Models\CardProduct;
use App\Models\Customer;
use App\Models\Drink;
use App\Services\CardIssuanceService;
use App\Services\QrTokenService;
use App\Services\RedemptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

    /** GET /api/staff/drinks -> actieve drankenkaart van de merchant */
    public function drinks(Request $request): JsonResponse
    {
        $drinks = Drink::where('merchant_id', $request->user()->merchant_id)
            ->where('active', true)
            ->orderBy('type')->orderBy('size')
            ->get();

        return response()->json(['data' => DrinkResource::collection($drinks)]);
    }

    /**
     * POST /api/staff/scan { nonce, drink_id? }
     * Consumeert de token (single-use) en handelt identify of redeem af.
     */
    public function scan(Request $request): JsonResponse
    {
        $data = $request->validate([
            'nonce' => ['required', 'string'],
        ]);
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
                // Het in de PWA gekozen vaste drankje; de balie legt dit op de nieuwe kaart vast.
                'preferred_drink' => $token->preferred_coffee_type ? [
                    'type' => $token->preferred_coffee_type->value,
                    'size' => $token->preferred_cup_size->value,
                    'label' => $token->preferred_coffee_type->label().' · '.$token->preferred_cup_size->label(),
                ] : null,
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

        // Het geschonken drankje staat vast op de kaart; zoek de bijbehorende drink-rij
        // (voor kostprijs/analytics). Kan ontbreken als de drankenkaart later wijzigde.
        $drink = ($card->preferred_coffee_type && $card->preferred_cup_size)
            ? Drink::where('merchant_id', $staff->merchant_id)
                ->where('type', $card->preferred_coffee_type->value)
                ->where('size', $card->preferred_cup_size->value)
                ->first()
            : null;

        $card = $this->redemption->redeemCup($card, $staff, $drink);

        return response()->json([
            'type' => 'redeem',
            'result' => 'redeemed',
            'card' => new CardResource($card->load('cardProduct')),
            'drink' => $drink ? new DrinkResource($drink) : null,
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
            // Het door de klant gekozen vaste drankje (komt mee uit de identify-scan).
            'preferred_coffee_type' => ['required', Rule::enum(CoffeeType::class)],
            'preferred_cup_size' => ['required', Rule::enum(CupSize::class)],
        ]);

        $staff = $request->user();
        $customer = Customer::findOrFail($data['customer_id']);
        $product = CardProduct::where('merchant_id', $staff->merchant_id)
            ->findOrFail($data['card_product_id']);

        $card = $this->issuance->issueAndActivate(
            $customer,
            $product,
            PaymentMethod::from($data['payment']['method']),
            CoffeeType::from($data['preferred_coffee_type']),
            CupSize::from($data['preferred_cup_size']),
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
