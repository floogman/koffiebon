<?php

return [
    /*
     | Basis-URL van de PWA/frontend. Hierheen redirect de e-mailverificatie met
     | een eenmalige device-claim-code. Same-origin in productie heeft de voorkeur.
     */
    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost')),

    // Levensduur van een QR-token in seconden (kortlevend, single-use).
    'qr_token_ttl' => (int) env('QR_TOKEN_TTL', 45),

    // Levensduur van een e-mail-verificatielink en device-claim-code.
    'verification_link_minutes' => (int) env('VERIFICATION_LINK_MINUTES', 60),
    'device_claim_ttl' => (int) env('DEVICE_CLAIM_TTL', 600),
];
