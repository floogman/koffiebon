# Beslissingen (DECISIONS)

Log van bewuste keuzes tijdens de bouw. Zie `CLAUDE.md` voor de opdracht/spec.

## Setup & infra

- **Laravel 13.15** (greenfield, `composer create-project laravel/laravel`). KlusApp draait op 12;
  voor een nieuw project nemen we de actuele major. Wijk hier alleen van af als parity met KlusApp
  vereist is.
- **Docker via Laravel Sail**, gespiegeld op `../KlusApp`:
  - PHP 8.3-runtime onder `docker/8.3/` (gekopieerd uit KlusApp; standaard Sail-stub).
  - **PostgreSQL** via `postgis/postgis:15-3.4`, **Mailpit** voor e-mail.
- **Naamgevingsconventie** (mapping op verzoek: `klusviewer` → `koffiebon`, `klus` → `bon`):
  - service `laravel.bon`, netwerk `sail-bon`, volume `sail-pgsql-bon`, image `sail-8.3/koffiebon`.
- **Host-poorten** gekozen om naast KlusApp te kunnen draaien (KlusApp: 80, 5173, 5433, 1026/8026):
  - app `8090` → 80, vite `5273` → 5173, pgsql `5434` → 5432, mailpit `1027`/`8027`.
- **DB**: database `koffiebon`, user `sail`, pass `password` (Sail-defaults). Tijdzone `Europe/Amsterdam`.
- **Sanctum** toegevoegd voor API-tokens (klant-device + staff). Config/migraties later in de container
  publiceren/aanpassen.
- **PHP-platform gepind op `8.3.31`** in `composer.json` (`config.platform.php`). Reden: de scaffold is per
  ongeluk met host-PHP 8.4 gelockt (Symfony 8.1 / vereiste ≥ 8.4.1), terwijl de container op 8.3 draait.
  Met de pin resolveert composer altijd voor 8.3 (Symfony 7.4), ook als iemand per ongeluk op de host draait.
  Daarna `php composer.phar update` **in de container** gedraaid om de lock te herstellen.
- **Apple Silicon → native arm64.** De shell heeft `DOCKER_DEFAULT_PLATFORM=linux/amd64` gezet, waardoor de
  image eerst als amd64 onder Rosetta draaide (traag). Opgelost via "aanpak B": de image herbouwd met
  `DOCKER_DEFAULT_PLATFORM=linux/arm64 docker compose build --no-cache laravel.bon` + arm64 mailpit gepulld +
  `... up -d --force-recreate`. Container draait nu native (`uname -m = aarch64`, geen `/run/rosetta`).
  - ⚠️ **Let op:** `DOCKER_DEFAULT_PLATFORM=linux/amd64` staat nog globaal in de shell. Een toekomstige
    `docker compose build`/`up` **zónder** de `linux/arm64`-prefix bouwt weer amd64. Wil je het permanent:
    `platform: linux/arm64` in de compose-service zetten (aanpak A) of de globale env-var weghalen.
- **`reverb`-programma uit `docker/8.3/supervisord.conf` verwijderd** (KlusApp gebruikt Reverb, wij niet in
  fase 1). Geldt bij de volgende image-rebuild.

## Datamodel & domein (fase 1)

- **Pest** als testframework (brief noemt Pest óf PHPUnit; Pest gekozen voor leesbare flow-tests).
  `RefreshDatabase` staat aan voor Feature-tests. Tests draaien op SQLite `:memory:`.
- **Queue lokaal = `sync` (afwijking van de brief).** De brief noemt de database-driver + e-mail via
  queue; dat blijft de **productie**-default (`.env.example` documenteert `database` + `queue:work`).
  Lokaal draaien we `sync` zodat e-mail direct in-process verstuurt — geen worker nodig en de
  verificatielink kan niet door queue-vertraging verlopen aankomen. De mailables blijven `ShouldQueue`
  (correct voor productie); de ondertekende link wordt op het **verzendmoment** in `CustomerLinkMail`
  gegenereerd (TTL 24u), niet bij het in de wachtrij zetten.
- **`cost_per_cup_cents` toegevoegd aan `card_products`** (default 0). Niet expliciet in §3, maar
  acceptatiecriterium 6 en de marge-formule (§1) hebben de kostprijs per kop nodig.
- **`card_events` / `qr_tokens` hebben alleen `created_at`** (onveranderlijk grootboek resp.
  kortlevend token) via `const UPDATED_AT = null`.
- **`qr_tokens.subject_type` is een string-enum (`customer|card`)**, geen polymorfe morph-class —
  bewust simpel; de twee subject-types zijn bekend en vast.
- **Atomaire verzilvering zonder row locks.** SQLite kent geen `SELECT … FOR UPDATE`. De
  `RedemptionService` gebruikt daarom een **conditionele UPDATE**
  (`WHERE status=active AND cups_remaining > 0 … SET cups_remaining = cups_remaining - 1`) en
  controleert de affected rows. Dat garandeert nooit < 0 of > totaal, ook bij gelijktijdige scans,
  en is portable naar Postgres. De parallel-test simuleert de race door meer verzilveringen aan te
  bieden dan er koppen zijn.
- **`cups_remaining` is een cache**; het grootboek (`card_events`) is leidend. Een tinker-check
  bevestigt `cups_total + som(redeem-delta's) == cups_remaining`.
- **Sanctum op zowel `StaffUser` als `Customer`** (beide `HasApiTokens`). Customer is passwordless
  (alleen device-tokens); StaffUser heeft e-mail+wachtwoord en een rol.
- **Modellen volgen de Laravel-13 attribuut-stijl** (`#[Fillable]`/`#[Hidden]`) zoals de scaffold-`User`.

## Frontend (fase 1)

- **Eén Vite-app, twee oppervlakken via React Router**: klant op `/`, balie op `/balie`, gedeelde
  code in `src/shared`. Eenvoudiger dan twee builds; same-origin in productie.
- **Tailwind v3** (stabiel) met het koffie-palet uit de pitch (`espresso/cream/caramel`).
- **State**: TanStack Query voor server-state; de klant-home polt (3–10s) zodat het saldo live daalt
  zodra de balie scant. Tokens in `localStorage` (aparte sleutels voor klant-device vs staff).
- **QR**: `qrcode.react` rendert de deeplink `{FRONTEND_URL}/s/{nonce}`; de balie leest met
  `@zxing/browser` (camera) of een **keyboard-wedge** hardware-scanner (globale keydown-buffer,
  submit op Enter). `extractNonce()` accepteert zowel de deeplink als een kale nonce.
- **PWA**: `vite-plugin-pwa`/Workbox; API-routes uit de precache/navigatie-fallback gehouden
  (QR/saldo vereisen netwerk → expliciete offline-staat).
- **Icons** gegenereerd uit `favicon.svg` via headless Chrome (geen extra build-tooling).
- **Dev-proxy**: Vite proxyt `/api` → `http://localhost:80` (Laravel in dezelfde container), zodat
  bearer-tokens same-origin werken zonder CORS-config in dev.
- **Bundle** ~694 KB (ongesplitst; `@zxing` is groot). Acceptabel voor fase 1; later code-splitten.
- **Verificatie** van de UI via Chrome DevTools Protocol (Node 23, geen extra deps) — screenshots
  in `docs/screenshots/`.

## Fase 3 — groei (vóór fase 2 gebouwd, op verzoek)

- **Koffiesoort × maat als `drinks`-menukaart** (4×3), met kostprijs per drankje. Bewuste keuze:
  **één verzilvering blijft één kop**, ongeacht soort/maat — consistent met de productfilosofie
  ("elke kop gelijk, één gebaar") en houdt de invariant/test uit fase 1 intact. Soort/maat worden
  op het redeem-event gesnapshot (`drink_id` + type/maat/kostprijs) voor analytics. Makkelijk te
  wijzigen mocht een grote koffie later méér koppen moeten kosten.
- **Dashboard rekent uit het grootboek** (geen aparte rapportagetabellen): omzet uit `payments`,
  openstaande koppen uit actieve kaarten, drukte/drankjes uit redeem-events. Filterbaar op vestiging
  en periode. Activity/by_drink in PHP gegroepeerd (portabel SQLite↔Postgres; datasets zijn klein).
- **Admin-only** afgedwongen in de controller (`$staff->isAdmin()`), niet via ability — de rol staat
  op de staff-user, het token heeft alleen ability `staff`.

## Werkwijze

- **Alle php/artisan/composer/npm/npx-commando's draaien in de container** via `./vendor/bin/sail …`,
  nooit op de host (host = PHP 8.4, container = 8.3).
- Git geïnitialiseerd in de projectroot. `pitch/` (de pitch deck) blijft ongemoeid.

## 6-cijferige baliecode naast de QR

- Elke QR-token krijgt naast de nonce een **6-cijferige code** (100000–999999, nooit een
  voorloopnul) die de balie met de hand kan intypen. Code en nonce horen bij hetzelfde token-record
  en delen één `expires_at`; één scan óf code consumeert het token (single-use).
- Alleen de **hash** van de code wordt opgeslagen (`qr_tokens.code_hash`), net als de nonce. De platte
  code verlaat de server precies één keer (bij uitgifte) en staat boven de QR in de klant-PWA.
- De TTL is verhoogd van 45s → **60s** (`QR_TOKEN_TTL`), zodat de korte code prettig in te typen blijft.
- **Afweging:** een 6-cijferige code is veel makkelijker te raden dan de 128-bit nonce (~900k ruimte).
  Aanvaardbaar omdat het token single-use is, 60s leeft, scannen alleen kan met een geauthenticeerde
  staff-token, en `POST /staff/scan` op 120/min gethrottled is (≈120 pogingen per levensduur).
- `consume()` matcht op `nonce_hash` **of** `code_hash`; de balie-frontend stuurt beide via hetzelfde
  `nonce`-veld (een kale code passeert `extractNonce` ongewijzigd).

## Live-updates via Laravel Reverb

- De klant-PWA krijgt **realtime** kaart-updates via **Laravel Reverb** (WebSockets, pusher-protocol)
  i.p.v. alleen polling. Eén event `App\Events\CardUpdated` (`action`: redeemed/activated/issued) gaat
  over privé-kanaal **`Customer.{id}`**; de PWA ververst het `['me']`-saldo en toont een bevestiging.
- **Kanaal-auth met bearer-tokens:** de PWA gebruikt Sanctum-bearer-tokens, niet de standaard
  cookie-sessie. Daarom een eigen endpoint `POST /api/broadcasting/auth` onder `auth:sanctum`
  (`abilities:customer`); Laravel-Echo wijst zijn `authEndpoint` daarheen met de `Authorization`-header.
  De default `/broadcasting/auth` (web/cookies) blijft ongebruikt.
- **Best-effort broadcasten:** events worden ná de DB-commit verstuurd en in een `try/catch`
  (trait `BroadcastsCardUpdates`) ingepakt — een onbereikbare Reverb-server mag verzilveren/activeren
  nooit laten falen. De PWA houdt polling als vangnet (lichter: 5s open / 20s dicht).
- **Infra:** Reverb draait in de bestaande `laravel.bon`-container op poort **8080** (toegevoegd aan
  `docker-compose.yml` en de `composer dev`-concurrently). `BROADCAST_CONNECTION=reverb`. Tests draaien
  met `BROADCAST_CONNECTION=null` (geen socket).
- **Frontend-env:** `vite.config.ts` leest nu de root-`.env` (`envDir: ..`) zodat de `VITE_REVERB_*`-
  variabelen in de build belanden. `laravel-echo` + `pusher-js` toegevoegd.
- **Afweging:** de QR-overlay toont het saldo nu uit de verse `['me']`-query (op `card_id`) i.p.v. een
  snapshot, zodat het getal live meeloopt met zowel de push als de refetch.

## Vast voorkeursdrankje per kaart

- Een kaart is voor **één vast drankje** (koffiesoort + maat). De klant kiest dit **vooraf in de PWA**
  bij het kopen; het staat naast zowel de koop-QR als de bestel-QR.
- **Als tekst opgeslagen, geen FK** naar `drinks`: `cards.preferred_coffee_type` + `cards.preferred_cup_size`
  (enums `CoffeeType`/`CupSize`, cast op het model; `preferredDrinkLabel()` → "Cappuccino · Medium").
  Zo blijft de keuze leesbaar ook als de drankenkaart later wijzigt.
- **Doorgeefroute (keuze → nieuwe kaart):** de identify-token draagt de gekozen drank mee
  (`qr_tokens.preferred_coffee_type/size`). Bij de scan geeft de server het door in de identify-respons;
  de balie stuurt het mee naar `POST /staff/cards`, dat het op de kaart vastlegt. De balie kiest dus niet.
- **Beslissingen (met de gebruiker afgestemd):** keuze **verplicht** (geen kaart zonder drank), **vast bij
  aankoop** (geen wijzig-endpoint), en de **klant** kiest (niet de balie).
- **Klant-drankenkaart:** nieuwe `GET /api/pwa/drinks` (alle actieve drinks; single-merchant MVP — bij
  multi-merchant scopen op de merchant van de vestiging/kaart).
- **Frontend:** `DrinkPicker` verplaatst naar `src/shared/` en hergebruikt door balie én klant-PWA. De
  koop-overlay selecteert standaard de eerste drank en geeft de keuze door aan de QR (token hernieuwt bij
  wijziging); de bestel-overlay toont het vaste drankje van de kaart.

## Balie kiest geen drankje meer bij het scannen

- De "Wat schenk je?"-picker is uit de balie-scanpagina verwijderd. Wat geschonken wordt staat **vast op
  de kaart** (het voorkeursdrankje); de balie scant alleen. `POST /staff/scan` accepteert geen `drink_id`
  meer.
- Bij verzilveren legt het redeem-event nu `coffee_type`/`cup_size` vast vanuit de **kaart** (tekst), en
  zoekt de server de bijbehorende drink-rij van de merchant op voor de **kostprijs**/`drink_id` (kan
  `null` zijn als de drankenkaart sinds aankoop wijzigde). De balie-bevestiging toont
  `card.preferred_drink_label`.
- `DrinkPicker` blijft bestaan in `src/shared/` (alleen nog gebruikt door de klant-PWA bij het kopen).
  `GET /staff/drinks` blijft als beheer-/overzichtsendpoint, maar wordt niet meer in de scanflow gebruikt.

## Productie-deploy (VPS, koffie.klusviewer.nl)

- **Native PHP-FPM** (geen Docker op de VPS), **SQLite** als productie-database, **nginx +
  Let's Encrypt** (certbot auto-renew), Reverb en queue-worker via **systemd**. Reden: sluit aan
  op de bestaande nginx/certbot-setup van klusviewer.nl en houdt het goedkoop/simpel.
- **De PWA bezit de root** van het subdomein; nginx serveert de statische Vite-build en stuurt
  alleen `/api` → PHP-FPM en `/app` + `/apps` → Reverb (127.0.0.1:8080) door. Gevolg: de
  server-rendered café-site (`SiteController` `/`, `/menu`) is op dit subdomein niet bereikbaar —
  desgewenst later op een eigen (sub)domein zetten.
- `FRONTEND_URL` = `https://koffie.klusviewer.nl` zodat de QR-deeplinks (`/s/{nonce}`) en de
  claim-link naar de PWA op hetzelfde subdomein wijzen; de PWA praat same-origin met `/api`.
- Alle artefacten staan in [`deploy/`](deploy); volledige handleiding in [`DEPLOY.md`](DEPLOY.md).

## Cross-device login (PWA blijft in beeld)

- **Probleem met de oude flow:** klikken op de e-maillink (verify → redirect `/claim?code=`) haalde de
  gebruiker uit zijn PWA, zeker op een ander toestel. Vervangen door een login waarbij de PWA blijft staan.
- **Nieuw model:** de PWA genereert client-side een 256-bit geheim `s` en stuurt **alleen
  `channel_hash = sha256(s)`** mee bij `POST /api/auth/login-request`. De server kent `s` nooit (alleen
  de hash, at rest in `login_sessions`). De e-mailklik (`GET /api/auth/confirm/{token}`, signed) zet de
  sessie op `confirmed` en zendt een **publiek** event `login.{channel_hash}` uit met payload **zonder
  id/token**. De PWA (die op het kanaal luistert én polt) wisselt daarna `s` in voor een Sanctum
  device-token (`POST /api/auth/claim { secret }`, single-use, atomisch).
- **Waarom veilig:** inloggen vereist het **preimage `s`**, dat alleen de initiërende PWA bezit. De push
  draagt geen id/token, dus niemand kan een sessie kapen door een id naar een PWA te sturen; hooguit kun
  je de kanaalnaam (de hash) kennen, en dáármee haalt de PWA zélf de login op — exact de gevraagde eis.
  De e-mailbevestiging blijft het bewijs van inbox-controle.
- **Push:** `LoginConfirmed` is `ShouldBroadcastNow` (direct, niet via queue) op een **publiek** `Channel`
  (geen kanaal-auth nodig vóór login). **Polling** (claim elke ~3s, `409 login_pending`) is de fallback
  als Reverb niet verbindt. De PWA bewaart de pending-login in `localStorage` en hervat na een refresh.
- **Eén endpoint** `login-request` (register + magic-link samengevoegd); de register/login-toggle verviel.
- **Bewust restrisico geaccepteerd:** initiator ≠ klikker (iemand start een login op jouw e-mail, jij
  klikt). Géén match-code toegevoegd — de e-mail is hier onderdeel van de login en fysieke aanwezigheid
  aan de balie is sowieso nodig om koffie te verzilveren, dus de impact is verwaarloosbaar.
- **`device_claims` → `login_sessions`:** de wegwerp-handshake is vervangen; durable device-identiteit
  zat altijd al in de Sanctum-tokens (`personal_access_tokens`), niet in de claims. Echte web-push
  (PWA dicht) zou later een aparte `push_subscriptions`-tabel vragen — losstaand hiervan.
