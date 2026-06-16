# Koffiebon

Prepaid koffiekaart — vooruit betaald in **koppen koffie**, verzilverd aan de balie via een
**roterende, eenmalige QR-code**. De server is de bron van waarheid voor het saldo; de QR is "dom".

- **Backend:** Laravel 13 (API-only), Sanctum, Pest. Geld in hele centen, tijdzone `Europe/Amsterdam`.
- **Frontend:** React 18 + TypeScript + Vite PWA in [`/frontend`](frontend) — klant-PWA (`/`) en
  balie-app (`/balie`), één codebase, Tailwind met het koffie-palet, TanStack Query, `qrcode.react`,
  `@zxing/browser` + hardware-scanner.
- Zie [`CLAUDE.md`](CLAUDE.md) voor de volledige opdracht/spec, [`API.md`](API.md) voor de endpoints,
  en [`DECISIONS.md`](DECISIONS.md) / [`PROGRESS.md`](PROGRESS.md) voor keuzes en voortgang.

## Screenshots

| Klant — kaart + saldo | Klant — roterende QR | Balie — na een scan |
|---|---|---|
| ![Klant home](docs/screenshots/customer-home.png) | ![Roterende QR](docs/screenshots/customer-qr.png) | ![Balie redeem](docs/screenshots/balie-redeemed-drink.png) |

**Merchant-dashboard** (fase 3) — echte cijfers uit het grootboek, per vestiging en drankje:

![Dashboard](docs/screenshots/dashboard.png)

Meer in [`docs/screenshots/`](docs/screenshots) (registratie, balie-scanscherm met drank-keuze,
nieuwe-kaart-flow).

## Lokale setup (Docker / Laravel Sail)

> **Alle php/artisan/composer/npm-commando's draaien in de container.** De service heet `laravel.bon`;
> `APP_SERVICE=laravel.bon` staat in `.env` zodat de `sail`-wrapper werkt.

```bash
# 1. Env klaarzetten (eenmalig)
cp .env.example .env

# 2. Containers bouwen + starten (native arm64 op Apple Silicon)
DOCKER_DEFAULT_PLATFORM=linux/arm64 docker compose up -d --build

# 3. Dependencies, app key, database
./vendor/bin/sail composer install
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate:fresh --seed
```

De app draait nu op **http://localhost** (API), Mailpit op **http://localhost:8025**.

Lokaal dev draait op **SQLite** (`database/database.sqlite`). PostgreSQL zit achter een compose-profile
en hoeft niet te starten: `./vendor/bin/sail --profile pgsql up -d` indien gewenst.

### E-mail (verificatie / magic-link)

Lokaal staat `QUEUE_CONNECTION=sync`, dus verificatie-/herstelmails worden **direct in-process**
verstuurd — **geen `queue:work`-worker nodig**. Ze landen in **Mailpit** (http://localhost:8025).

In productie zet je `QUEUE_CONNECTION=database` en draai je een worker:

```bash
./vendor/bin/sail artisan queue:work
```

> De ondertekende verificatielink wordt op het **verzendmoment** gegenereerd (TTL 24u), dus ook met
> een trage queue arriveert hij nooit al verlopen.

### Frontend (Vite PWA) & Reverb — draaien automatisch

De React-app staat in `frontend/` en draait **in de container** (Node 20). De Vite-dev-server én de
**Reverb**-websocketserver worden door **supervisor** gestart, dus ze komen automatisch op met de
container en herstarten bij een crash — geen losse `docker exec`-commando's meer nodig.

Eénmalig de frontend-dependencies installeren (en na het wijzigen van `package.json`):

```bash
docker exec -w /var/www/html/frontend koffiebon-laravel.bon-1 npm install
# Productiebuild (optioneel): docker exec -w /var/www/html/frontend koffiebon-laravel.bon-1 npm run build
```

> Pas je `docker/8.3/supervisord.conf` aan (of pull je deze versie voor het eerst), draai dan
> éénmalig `./vendor/bin/sail build && ./vendor/bin/sail up -d` zodat de nieuwe config in het image komt.

- **PWA:** http://localhost:5173 (klant op `/`, balie op `/balie`). Zet `FRONTEND_URL=http://localhost:5173`
  in `.env` zodat e-mailverificatie en QR-deeplink daarheen wijzen.
- **Reverb:** ws://localhost:8080 (poort staat in `docker-compose.yml`).

Live-updates: zodra de balie een QR/code scant, werkt de klant-PWA het saldo **direct** bij en toont een
bevestiging (`☕ Geschonken — nog 8`, `Kaart geactiveerd`, …). Valt stil terug op polling als Reverb uit
staat. De klant-websocket authenticeert privé-kanalen via `POST /api/broadcasting/auth` (Sanctum
bearer-token). Eigen credentials genereer je met `./vendor/bin/sail artisan reverb:install`.

Logs van beide processen lopen mee in `./vendor/bin/sail logs -f` (supervisor → stdout).

## Tests

```bash
# Volledige suite (draait op SQLite :memory:)
docker exec -w /var/www/html koffiebon-laravel.bon-1 php artisan test
# of:
./vendor/bin/sail pest
```

Belangrijke tests: `PricingServiceTest` (korting onafhankelijk van prijs), `RedemptionServiceTest`
(parallelle verzilvering — nooit < 0 of > totaal), `ScanFlowTest` (volledige flow A→C + single-use/
verlopen tokens, geverifieerd e-mailadres verplicht, herstel op ander toestel).

## Demo-data (na `migrate:fresh --seed`)

| Rol   | E-mail                  | Wachtwoord |
|-------|-------------------------|------------|
| Admin | `admin@koffiebon.test`  | `password` |
| Balie | `balie@koffiebon.test`  | `password` |
| Klant | `klant@koffiebon.test`  | passwordless (device-token via e-mail) |

De demo-klant heeft één actieve kaart **"12 voor de prijs van 10"** met saldo **9/12**.

## De flow in het kort

1. **Klant registreert** → e-mail verifiëren → PWA krijgt een device-token.
2. **Kaart kopen** → klant toont identify-QR → balie scant → kiest product → legt betaling vast →
   `issue + activate + payment` in één transactie → kaart actief.
3. **Koffie verzilveren** → klant toont roterende redeem-QR → balie scant → `−1 kop` (atomisch).
4. **Herstel** → magic-link op een ander toestel → zelfde kaarten/saldi vanaf de server.
