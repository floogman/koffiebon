<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Broadcast;

/*
 | Privé-kanaal per klant. De PWA abonneert zich op haar eigen kanaal en ontvangt
 | live kaart-updates (verzilvering / activatie) zodra de balie scant. Autorisatie
 | gebeurt op identiteit: alleen de klant zelf mag op haar kanaal.
 */
Broadcast::channel('Customer.{customerId}', function (Customer $customer, int $customerId) {
    return $customer->getKey() === $customerId;
});
