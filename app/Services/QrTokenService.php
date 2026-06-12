<?php

namespace App\Services;

use App\Enums\QrPurpose;
use App\Enums\QrSubjectType;
use App\Exceptions\TokenException;
use App\Models\Card;
use App\Models\Customer;
use App\Models\QrToken;
use Illuminate\Support\Facades\DB;

/**
 * Geeft kortlevende, single-use QR-tokens uit en consumeert ze atomair.
 *
 * Alleen de hash van de nonce wordt opgeslagen; de platte nonce verlaat de server
 * precies één keer (bij uitgifte). Consumptie is race-safe via een conditionele
 * UPDATE op consumed_at IS NULL, zodat hooguit één gelijktijdige scan slaagt.
 */
class QrTokenService
{
    /**
     * Geef een token uit voor een subject. Retourneert de platte nonce (eenmalig)
     * plus het opgeslagen token-record.
     *
     * @return array{nonce: string, token: QrToken}
     */
    public function issue(QrSubjectType $subjectType, int $subjectId, QrPurpose $purpose): array
    {
        // >= 128 bit cryptografische willekeur.
        $nonce = bin2hex(random_bytes(16));

        $token = QrToken::create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'nonce_hash' => hash('sha256', $nonce),
            'purpose' => $purpose,
            'expires_at' => now()->addSeconds(config('koffiebon.qr_token_ttl')),
        ]);

        return ['nonce' => $nonce, 'token' => $token];
    }

    public function issueForCustomer(Customer $customer): array
    {
        return $this->issue(QrSubjectType::Customer, $customer->getKey(), QrPurpose::Identify);
    }

    public function issueForCard(Card $card): array
    {
        return $this->issue(QrSubjectType::Card, $card->getKey(), QrPurpose::Redeem);
    }

    /**
     * Consumeer een token op basis van de platte nonce. Atomair en single-use.
     *
     * @throws TokenException als de token onbekend, verlopen of al gebruikt is.
     */
    public function consume(string $nonce): QrToken
    {
        $hash = hash('sha256', $nonce);

        return DB::transaction(function () use ($hash) {
            $token = QrToken::query()
                ->where('nonce_hash', $hash)
                ->lockForUpdate()
                ->first();

            if ($token === null) {
                throw TokenException::invalid();
            }

            if ($token->isExpired()) {
                throw TokenException::expired();
            }

            // Race-safe markeren: slaagt alleen als nog niet geconsumeerd.
            $affected = QrToken::query()
                ->whereKey($token->getKey())
                ->whereNull('consumed_at')
                ->update(['consumed_at' => now()]);

            if ($affected === 0) {
                throw TokenException::alreadyUsed();
            }

            return $token->fresh();
        });
    }
}
