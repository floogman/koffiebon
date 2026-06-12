<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class StaffAuthController extends Controller
{
    /** POST /api/staff/login { email, password } -> { staff_token } */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $staff = StaffUser::where('email', $data['email'])->first();

        if ($staff === null || ! Hash::check($data['password'], $staff->password)) {
            throw ValidationException::withMessages([
                'email' => ['Onjuiste inloggegevens.'],
            ]);
        }

        $token = $staff->createToken('balie', ['staff'])->plainTextToken;

        return response()->json([
            'staff_token' => $token,
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'role' => $staff->role->value,
                'merchant_id' => $staff->merchant_id,
                'location_id' => $staff->location_id,
            ],
        ]);
    }

    /** POST /api/staff/logout */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Uitgelogd.']);
    }
}
