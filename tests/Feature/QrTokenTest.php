<?php

use App\Models\QrToken;
use Illuminate\Support\Str;

it('is bruikbaar zolang hij niet verlopen of geconsumeerd is', function () {
    $token = QrToken::factory()->create();

    expect($token->isUsable())->toBeTrue()
        ->and($token->isExpired())->toBeFalse()
        ->and($token->isConsumed())->toBeFalse();
});

it('is onbruikbaar na ~45s (verlopen)', function () {
    $token = QrToken::factory()->expired()->create();

    expect($token->isExpired())->toBeTrue()
        ->and($token->isUsable())->toBeFalse();
});

it('is onbruikbaar nadat hij is geconsumeerd (single-use)', function () {
    $token = QrToken::factory()->consumed()->create();

    expect($token->isConsumed())->toBeTrue()
        ->and($token->isUsable())->toBeFalse();
});

it('slaat de nonce alleen gehasht op, nooit als platte tekst', function () {
    $nonce = Str::random(40);
    $token = QrToken::factory()->create([
        'nonce_hash' => hash('sha256', $nonce),
    ]);

    expect($token->nonce_hash)->not->toBe($nonce)
        ->and($token->nonce_hash)->toBe(hash('sha256', $nonce))
        ->and($token->getAttributes())->not->toHaveKey('nonce');
});
