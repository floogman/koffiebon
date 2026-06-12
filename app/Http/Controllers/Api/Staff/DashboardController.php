<?php

namespace App\Http\Controllers\Api\Staff;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    /** GET /api/staff/dashboard?location_id=&from=&to=  (admin only) */
    public function show(Request $request): JsonResponse
    {
        $staff = $request->user();
        abort_unless($staff->isAdmin(), 403, 'Alleen beheerders kunnen het dashboard bekijken.');

        $data = $request->validate([
            'location_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $to = isset($data['to']) ? CarbonImmutable::parse($data['to'])->endOfDay() : CarbonImmutable::now()->endOfDay();
        $from = isset($data['from'])
            ? CarbonImmutable::parse($data['from'])->startOfDay()
            : $to->subDays(29)->startOfDay();

        // Vestiging moet bij de merchant van de staff horen.
        $locationId = $data['location_id'] ?? null;
        if ($locationId !== null) {
            $valid = $staff->merchant->locations()->whereKey($locationId)->exists();
            abort_unless($valid, 404, 'Onbekende vestiging.');
        }

        return response()->json(
            $this->dashboard->build($staff->merchant_id, $locationId, $from, $to),
        );
    }
}
