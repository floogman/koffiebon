<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CustomerAuthController extends Controller
{
    public function __construct(private readonly CustomerAuthService $auth) {}

    /** POST /api/auth/register { email } */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $this->auth->register($data['email']);

        // Bewust generiek: lek niet of het adres al bestond.
        return response()->json([
            'message' => 'Controleer je e-mail om je adres te bevestigen.',
        ], 202);
    }

    /** POST /api/auth/magic-link { email } */
    public function magicLink(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);

        $this->auth->sendMagicLink($data['email']);

        return response()->json([
            'message' => 'Als dit e-mailadres bekend is, sturen we een inloglink.',
        ], 202);
    }

    /**
     * GET /api/auth/verify/{customer}  (signed)
     * Markeert geverifieerd en redirect naar de PWA met een eenmalige claim-code.
     */
    public function verify(Customer $customer): RedirectResponse
    {
        $redirect = $this->auth->verifyAndIssueClaim($customer);

        return redirect()->away($redirect);
    }

    /** POST /api/auth/claim { code } -> { device_token } */
    public function claim(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string']]);

        ['customer' => $customer, 'token' => $token] = $this->auth->claim($data['code']);

        return response()->json([
            'device_token' => $token,
            'customer' => new CustomerResource($customer),
        ]);
    }
}
