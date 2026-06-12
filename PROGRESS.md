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

### Nog te doen in fase 1

- [ ] Klant-auth: register / verify (signed) / magic-link / claim — Mailpit lokaal.
- [ ] Staff-auth + rol-gebaseerde abilities.
- [ ] Token-uitgifte (`POST /api/pwa/tokens`) + atomische scan (`POST /api/staff/scan`) met single-use consumptie.
- [ ] Kaart kopen/activeren aan de balie (`issue+activate+payment` in één transactie).
- [ ] Klant-PWA (React/Vite) + balie-app (camera + hardware-scan).
