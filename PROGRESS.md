# Voortgang (PROGRESS)

Bijgehouden per milestone. Zie `CLAUDE.md` §11 voor de fasering.

## Setup (in uitvoering)

- [x] Git geïnitialiseerd
- [x] Build-brief als `CLAUDE.md` geplaatst
- [x] Laravel 13 gescaffold + in root gemerged (pitch/ behouden)
- [x] Laravel Sail + Sanctum toegevoegd
- [x] Docker-opzet (compose + `docker/8.3`) gespiegeld op KlusApp, hernoemd naar `bon`/`koffiebon`
- [x] `.env` / `.env.example`: **SQLite** voor lokaal dev; mailpit; pgsql achter compose-profile (niet gestart)
- [x] Host-poorten 80/5173/1025 bleken bezet door ander Sail-project → app `8090`, vite `5273`, mailpit `1027/8027`
- [x] PHP-platform gepind op 8.3.31 + composer re-lock **in de container** (Symfony 8.1 → 7.4)
- [x] `migrate` gedraaid in de container (SQLite)
- [x] **Geverifieerd:** `http://localhost:8090` geeft HTTP 200, titel "Koffiebon"

> Stack draait. Commando's altijd in de container: `docker exec -w /var/www/html koffiebon-laravel.bon-1 php artisan …`
> of via `./vendor/bin/sail …` (met `APP_SERVICE=laravel.bon`).

## Fase 1 — Live aan de balie (in uitvoering)

Zie de Definition of Done in `CLAUDE.md` §11.

### Datamodel + domeinkern (af)

- [x] **Enums** (`app/Enums`): `StaffRole`, `CardStatus`, `CardEventType`, `QrSubjectType`,
      `QrPurpose`, `PaymentMethod`, `PaymentStatus`.
- [x] **Migraties** (FK-volgorde): merchants → locations → staff_users → customers →
      card_products → cards → card_events → qr_tokens → payments. Bedragen in centen (integers).
- [x] **Models** met relaties + casts: `Merchant`, `Location`, `StaffUser` (Sanctum), `Customer`
      (Sanctum, passwordless), `CardProduct`, `Card`, `CardEvent` (alleen `created_at`),
      `QrToken` (alleen `created_at`, nonce gehasht), `Payment`.
- [x] **PricingService**: korting (alleen koppen), kaartprijs, marge — alles in centen.
- [x] **RedemptionService**: atomaire conditionele decrement (nooit < 0 of > totaal, ook op SQLite),
      schrijft `redeem`-event en zet `depleted` op de laatste kop.
- [x] **Factories** voor alle modellen + states (`active`, `unverified`, `admin`, `expired`, `consumed`).
- [x] **Seeder**: demo-merchant + locatie, admin/balie-staff, "12 voor 10"-product, geverifieerde
      klant met actieve voorbeeldkaart (9/12) + grootboek + betaling.
- [x] **Tests groen (Pest, 14 tests / 36 asserts)** in de container:
  - `PricingServiceTest` — incl. criterium 6 (korting constant bij prijswijziging).
  - `RedemptionServiceTest` — incl. criterium 4 (parallelle verzilvering: nooit < 0 of > totaal).
  - `QrTokenTest` — TTL/single-use semantiek + nonce alleen gehasht (criterium 2, deels).
- [x] **Geverifieerd via tinker**: grootboek = cache (12 + −3 = 9), pricing klopt (€30 prijs, €22,80 marge).

### API + auth (af)

- [x] **API-routing** handmatig bedraad in `bootstrap/app.php` (`api:` + Sanctum ability-aliases
      `abilities`/`ability` + JSON-render voor `DomainException`).
- [x] **Klant-auth (passwordless)**: `register` → ondertekende mail (Mailpit, via queue) → `verify`
      (signed) → redirect met eenmalige **device-claim-code** → `claim` → Sanctum device-token.
      `magic-link` voor herstel; geen e-mail-enumeratie.
- [x] **Staff-auth**: `login`/`logout`, token met ability `staff`; rol op de staff-user.
- [x] **QrTokenService**: uitgifte (nonce ≥128 bit, alleen gehasht opgeslagen, ~45s) + **atomische
      single-use consumptie** (conditionele UPDATE op `consumed_at IS NULL`).
- [x] **CardIssuanceService**: `issue+activate+payment` in één transactie; prijs server-side afgeleid;
      e-mailverificatie verplicht. Losse `activate` voor pending kaarten.
- [x] **Endpoints**: `/api/pwa/{me,cards/{card},tokens}` (customer) en
      `/api/staff/{login,logout,products,scan,cards,cards/{card}/activate}` (staff), met throttling.
- [x] **API-resources** (`Card`, `Customer`, `CardProduct`) zonder `data`-wrapper (consistente JSON).
- [x] **Tests groen (28 / 83 asserts)**: `CustomerAuthTest`, `StaffAuthTest`, `ScanFlowTest`
      (volledige flow A→C, single-use + verlopen token, criterium 1/5/7), plus de eerdere domein-tests.
- [x] **Live geverifieerd op http://localhost**: staff-login + producten + scan (9→8) + dubbele scan
      (409 `token_consumed`); register → queue → Mailpit → verify → claim → `/pwa/me`.
- [x] `README.md` (setup + demo-credentials) en `API.md` geschreven.

### Frontend — klant-PWA + balie-app (af)

- [x] **Vite-workspace** `frontend/` (React 18 + TS + Tailwind, koffie-palet), draait in de container;
      dev-server proxyt `/api` → Laravel (same-origin, bearer-tokens).
- [x] **PWA**: `vite-plugin-pwa` (Workbox) — manifest "Koffiebon", espresso `theme_color`,
      `display: standalone`, service worker, gegenereerde icons (192/512/maskable + apple-touch).
      API-calls worden nooit gecachet (QR/saldo vereisen netwerk).
- [x] **Klant-PWA** (`/`): passwordless registratie + herstel, claim-landing (`/claim`),
      kaartlijst met saldo + voortgangsbalk, **roterende redeem-QR** met aflopende teller
      (auto-refresh ~30s + bij focus), identify-QR voor een nieuwe kaart, offline-staat.
- [x] **Balie-app** (`/balie`): staff-login, **camera-scan** (`@zxing/browser`) **én
      hardware-scanner** (keyboard-wedge), plus handmatige invoer. Identify → product + betaling +
      activeren; redeem → groot "Geschonken! nog N". Nette foutmeldingen (verlopen/gebruikt/leeg).
- [x] **Build groen**: `npm run build` (tsc + vite) compileert zonder fouten; SW + manifest gegenereerd.
- [x] **Live geverifieerd in de browser** (Chrome via CDP, screenshots in `docs/screenshots/`):
      klant-home (8/12), roterende QR (live, teller), balie-scan → redeem (8→7), nieuwe-kaart-flow
      (€30,00 + pin/contant), registratiescherm.

> **Fase 1 DoD gehaald**: verse checkout via `README` opstartbaar; flow A→D werkt end-to-end
> (API-tests + live HTTP + live in de PWA/balie-UI).

## Fase 3 — Groei (af)

> Op verzoek vóór fase 2 gebouwd: 2 vestigingen, 4 koffiesoorten × 3 maten, merchant-dashboard.

- [x] **Drankenkaart**: `CoffeeType` (regular/cappuccino/flat_white/espresso) × `CupSize`
      (small/medium/large) als `drinks`-tabel met kostprijs per drankje (4×3 = 12).
- [x] **Verzilvering legt het drankje vast** op het redeem-event (`drink_id` + snapshot type/maat/
      kostprijs). **Eén verzilvering blijft één kop** — type/maat dienen voor keuze + analytics.
- [x] **Twee vestigingen** (Centrum/Station) actief gebruikt: kaarten, staff en verzilveringen
      zijn vestiging-gebonden; het dashboard filtert erop.
- [x] **DashboardService** aggregeert uit het grootboek: omzet, openstaande koppen (verplichting),
      drukte per dag, populairste drankjes (type×maat), terugkerende klanten — per vestiging + periode.
- [x] **API**: `GET /api/staff/dashboard` (**admin only**) + `GET /api/staff/drinks`; `scan`
      accepteert optioneel `drink_id`.
- [x] **Seeder** met realistische data: 2 vestigingen, 12 drankjes, 3 producten, 14 klanten,
      ~75 verzilveringen over ~30 dagen (spitsuren, gewogen drankkeuze).
- [x] **Frontend**: balie-drank-keuze (soort × maat) meegestuurd bij de scan; **admin-dashboard**
      (`/dashboard`) met KPI's, vestigingsfilter, drukte-grafiek, drank-breakdown, terugkerende klanten.
- [x] **Tests groen (32 / 104 asserts)** incl. `DashboardTest`. **Live geverifieerd**: dashboard toont
      18 kaarten / €414 omzet / 75 koppen, splitsing Centrum vs Station; balie-scan met drank →
      "nog 8 · Flat White · Groot".

> **Fase 3 DoD gehaald**: het dashboard toont echte cijfers uit het grootboek en multi-vestiging werkt.

### Nog open

- **Fase 2** — Mollie achter een `PaymentProvider`-interface; kaart cadeau doen aan een ander adres.
