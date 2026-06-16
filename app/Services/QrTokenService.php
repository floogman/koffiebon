<?php

namespace App\Services;

use App\Enums\CoffeeType;
use App\Enums\CupSize;
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
     * Geef een token uit voor een subject. Retourneert de platte nonce én de
     * 6-cijferige baliecode (beide eenmalig) plus het opgeslagen token-record.
     *
     * @return array{nonce: string, code: string, token: QrToken}
     */
    public function issue(
        QrSubjectType $subjectType,
        int $subjectId,
        QrPurpose $purpose,
        ?CoffeeType $coffeeType = null,
        ?CupSize $cupSize = null,
    ): array {
        // >= 128 bit cryptografische willekeur.
        $nonce = bin2hex(random_bytes(16));
        $code = $this->uniqueCode();

        $token = QrToken::create([
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'nonce_hash' => hash('sha256', $nonce),
            'code_hash' => hash('sha256', $code),
            'purpose' => $purpose,
            'preferred_coffee_type' => $coffeeType,
            'preferred_cup_size' => $cupSize,
            'expires_at' => now()->addSeconds(config('koffiebon.qr_token_ttl')),
        ]);

        return ['nonce' => $nonce, 'code' => $code, 'token' => $token];
    }

    /**
     * Genereer een 6-cijferige code (100000–999999, dus nooit met voorloopnul) die
     * op dit moment niet door een ander nog-bruikbaar token in gebruik is.
     */
    private function uniqueCode(): string
    {
        do {
            $code = (string) random_int(100000, 999999);
            $taken = QrToken::query()
                ->where('code_hash', hash('sha256', $code))
                ->where('expires_at', '>', now())
                ->whereNull('consumed_at')
                ->exists();
        } while ($taken);

        return $code;
    }

    public function issueForCustomer(Customer $customer, CoffeeType $coffeeType, CupSize $cupSize): array
    {
        return $this->issue(QrSubjectType::Customer, $customer->getKey(), QrPurpose::Identify, $coffeeType, $cupSize);
    }

    public function issueForCard(Card $card): array
    {
        return $this->issue(QrSubjectType::Card, $card->getKey(), QrPurpose::Redeem);
    }

    /**
     * Consumeer een token op basis van de platte nonce óf de 6-cijferige baliecode.
     * Atomair en single-use.
     *
     * @throws TokenException als de token onbekend, verlopen of al gebruikt is.
     */
    public function consume(string $nonceOrCode): QrToken
    {
        $hash = hash('sha256', $nonceOrCode);

        return DB::transaction(function () use ($hash) {
            $token = QrToken::query()
                ->where(fn ($q) => $q->where('nonce_hash', $hash)->orWhere('code_hash', $hash))
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
