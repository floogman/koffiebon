<?php

return [
    /*
     | Basis-URL van de PWA/frontend. Hierin zitten o.a. de QR-deeplinks (/s/{nonce}).
     | Same-origin in productie heeft de voorkeur.
     */
    'frontend_url' => env('FRONTEND_URL', env('APP_URL', 'http://localhost')),

    // Levensduur van een QR-token in seconden (kortlevend, single-use). Geldt ook
    // voor de 6-cijferige baliecode die bij dezelfde token hoort.
    'qr_token_ttl' => (int) env('QR_TOKEN_TTL', 60),

    // Levensduur van een cross-device login-sessie én de bijbehorende e-mail-bevestigingslink
    // (minuten). Ruim genoeg om de mail te checken, kort genoeg om hangende sessies op te ruimen.
    'login_session_minutes' => (int) env('LOGIN_SESSION_MINUTES', 30),
];
