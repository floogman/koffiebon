<?php

use App\Models\StaffUser;
use Illuminate\Support\Facades\Hash;

it('logt een staff-user in en geeft een token met de staff-ability', function () {
    $staff = StaffUser::factory()->create([
        'email' => 'balie@test.test',
        'password' => Hash::make('geheim123'),
    ]);

    $token = $this->postJson('/api/staff/login', [
        'email' => 'balie@test.test',
        'password' => 'geheim123',
    ])->assertOk()->json('staff_token');

    // Het token werkt op een staff-endpoint.
    $this->withToken($token)->getJson('/api/staff/products')->assertOk();

    // Maar niet op een customer-endpoint (verkeerde ability).
    $this->withToken($token)->getJson('/api/pwa/me')->assertForbidden();
});

it('weigert onjuiste inloggegevens', function () {
    StaffUser::factory()->create([
        'email' => 'balie@test.test',
        'password' => Hash::make('geheim123'),
    ]);

    $this->postJson('/api/staff/login', [
        'email' => 'balie@test.test',
        'password' => 'fout',
    ])->assertStatus(422);
});
