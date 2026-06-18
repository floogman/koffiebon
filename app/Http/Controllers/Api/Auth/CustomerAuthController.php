<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Services\CustomerAuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerAuthController extends Controller
{
    public function __construct(private readonly CustomerAuthService $auth) {}

    /**
     * POST /api/auth/login-request { email, channel_hash }
     * Start een cross-device login en mailt een bevestigingslink. Generiek antwoord
     * (lekt niet of het adres bestond). `channel_hash` = sha256(geheim) van de PWA.
     */
    public function loginRequest(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'channel_hash' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]{64}$/'],
        ]);

        $this->auth->loginRequest($data['email'], $data['channel_hash']);

        return response()->json([
            'message' => 'Controleer je e-mail en klik de link om in te loggen.',
        ], 202);
    }

    /**
     * GET /api/auth/confirm/{token}  (signed)
     * Bevestigt de sessie en seint de wachtende PWA in. Toont een eenvoudige pagina;
     * de gebruiker keert daarna terug naar de app, die zichzelf inlogt.
     */
    public function confirm(string $token): View
    {
        $status = $this->auth->confirm($token);

        return view('auth.confirmed', ['status' => $status]);
    }

    /**
     * POST /api/auth/claim { secret } -> { device_token, customer }
     * De PWA wisselt haar geheim in voor een device-token zodra de sessie bevestigd is.
     */
    public function claim(Request $request): JsonResponse
    {
        $data = $request->validate(['secret' => ['required', 'string']]);

        ['customer' => $customer, 'token' => $token] = $this->auth->claim($data['secret']);

        return response()->json([
            'device_token' => $token,
            'customer' => new CustomerResource($customer),
        ]);
    }
}
