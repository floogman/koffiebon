# Koffiebon — Build Brief voor Claude

> **Opdracht in één zin:** bouw, grotendeels zelfstandig, de Koffiebon-applicatie:
> een **Laravel**-backend (API) + **React/PWA**-frontends voor een prepaid koffiekaart
> die in koppen koffie wordt verkocht en via een roterende, eenmalige QR-code aan de balie
> wordt verzilverd.
>
> Dit document is je opdracht én je referentie. De pitch in `pitch/index.html` beschrijft het
> product voor café-eigenaren; lees die eerst voor de context en toon.

---

## 0. Hoe je werkt (werkafspraken voor de agent)

Je voert dit zo veel mogelijk **autonoom** uit. Houd je aan deze werkwijze:

1. **Werk in fases** (zie §11). Lever per fase een werkend, getest geheel op. Begin niet aan fase N+1
   voordat de Definition of Done van fase N gehaald is.
2. **Plan kort, bouw, verifieer.** Per milestone: schets de stappen, implementeer, schrijf tests,
   **draai de tests**, en **verifieer in de echte app** (server + frontend starten, demo-data seeden,
   de flow doorlopen, een screenshot maken van de PWA en de balie-app).
3. **Commit per milestone** met betekenisvolle berichten. Werk op een feature-branch, niet op de
   hoofd-branch. Push of open pas een PR als daar om gevraagd wordt.
4. **Maak verstandige defaults** waar dit document een keuze open laat, en **noteer die keuze** in
   `DECISIONS.md`. Stop niet om te vragen tenzij een keuze onomkeerbaar is of geld/keys vereist
   (bv. een Mollie-account).
5. **Rapporteer eerlijk.** Falen een test of stap? Zeg dat met de output. Niet "klaar" melden wat niet
   geverifieerd is.
6. **Houd het simpel en goedkoop** (kernwaarde van het product). Geen native apps, geen exotische
   infra. Standaardtech die op elke telefoon werkt.
7. **Geld altijd in hele centen** (integers), nooit floats. Tijdzones expliciet (Europe/Amsterdam).

Bij de start: maak `DECISIONS.md` en `PROGRESS.md` aan en houd ze bij.

---

## 1. Productconcept & domeinregels (de waarheid)

- Een **kaart** is een aantal **koppen koffie**, vooruit betaald. De **eenheid is een kop, geen bedrag.**
- **Korting, prijs per kop en kaartprijs zijn losse knoppen:**
  - `korting = cadeau_koppen / koppen_op_kaart` — staat los van de prijs.
  - `kaartprijs = betaalde_koppen × prijs_per_kop`.
  - `marge_per_kaart = kaartprijs − (geleverde_koppen × kostprijs_per_kop)`.
  - Voorbeeld "12 voor de prijs van 10": `koppen_op_kaart=12`, `betaalde_koppen=10`.
- De **server is de bron van waarheid** voor het saldo. De QR is "dom" en bevat nooit saldo.
- **Roterende, eenmalige QR per koffie:** de PWA vraagt telkens een **verse, kortlevende, single-use**
  token aan de server. Een screenshot is daardoor direct waardeloos.
- **Eén gebaar aan de balie:** eerste verzilvering op een nog niet geactiveerde kaart = activeren
  (na fysieke betaling); elke volgende scan = **−1 kop**. Een kaart kan **nooit méér dan zijn totaal**
  worden verzilverd (server bewaakt dit atomisch).
- **Geverifieerd e-mailadres is verplicht** voordat een klant een kaart kan krijgen.
- **Herstelbaar:** de PWA bewaart geen saldo; een **herstel-/magic-link uit de e-mail** laadt de
  kaarten van die klant op elk toestel opnieuw vanaf de server.
- **Betaling:** nu **fysiek** aan de balie (pin/contant, alleen vastleggen). Later **Mollie** (online).

---

## 2. Techstack & defaults

> Dit zijn de aanbevolen defaults. Wijk alleen af met reden in `DECISIONS.md`.

**Backend**
- **Laravel 12**, PHP 8.3+.
- **Laravel Sanctum** voor API-tokens (klant-device-tokens + staff-tokens).
- **Database:** PostgreSQL (of MySQL) in productie; **SQLite** voor lokaal/tests.
- **Queue:** database driver; e-mail via queue. Lokaal e-mail opvangen met **Mailpit**.
- **Tests:** **Pest** (of PHPUnit) — feature tests voor alle flows.
- Money: integers in centen; `decimal`/`integer` kolommen, nooit float.

**Frontend (twee oppervlakken, één codebase)**
- **React 18 + TypeScript + Vite**.
- **PWA** via `vite-plugin-pwa` (Workbox): manifest, service worker, installeerbaar.
- **Styling:** Tailwind CSS. Hergebruik het koffie-kleurpalet uit `pitch/theme.css`
  (espresso `#1e1410`, cream `#f5ece1`, caramel `#c5772a`).
- **Data:** TanStack Query voor server-state; fetch met Sanctum bearer-token.
- **QR tonen (klant):** `qrcode.react`.
- **QR scannen (balie):** `@zxing/browser` (camera) **én** ondersteuning voor een
  hardware-scanner die de token als toetsenbordinvoer "typt" (verborgen input die op Enter submit).

**Projectstructuur (aanbevolen)**
```
/                 Laravel-app (API-only)
  app/ ...        domein, models, services, http
  database/       migrations, factories, seeders
  tests/
/frontend         Vite React PWA-workspace
  src/customer/   klant-PWA (kaarten + QR)
  src/balie/      balie-app (scannen + activeren/afboeken)
  src/shared/     api-client, auth, types, ui
/pitch            bestaande pitch deck (niet wijzigen)
```
API en frontend communiceren via REST + Sanctum **bearer-tokens** (geen cookie-sessies), zodat de
PWA simpel blijft. Stel CORS correct in. Same-origin in productie heeft de voorkeur (serve de
frontend-build via Laravel of een reverse proxy).

---

## 3. Datamodel (richtinggevend; verfijn waar nodig)

Bedragen in centen. Gebruik enums en foreign keys. Snapshot waarden bij uitgifte (de kaart bevriest
zijn `cups_total`).

- **merchants** `(id, name, timezone, created_at)`
- **locations** `(id, merchant_id, name)` — voorbereiding op multi-vestiging (fase 3).
- **staff_users** `(id, merchant_id, location_id?, name, email, password, role[admin|balie], created_at)`
- **customers** `(id, email unique, email_verified_at?, name?, created_at)`
- **card_products** `(id, merchant_id, name, cups_total, cups_paid, price_per_cup_cents, currency, validity_days, active)`
  - definieert het aanbod; "12 voor 10" = `cups_total=12, cups_paid=10`.
- **cards** `(id, customer_id, card_product_id, location_id?, status[pending|active|depleted|expired|void], cups_total, cups_remaining, price_paid_cents?, activated_at?, expires_at?, created_at)`
  - `cups_remaining` is een **gecachte** afgeleide van het event-grootboek; het grootboek is leidend.
- **card_events** `(id, card_id, staff_user_id?, type[issue|activate|redeem|void|adjust], cups_delta, created_at)`
  - onveranderlijk grootboek; `cups_remaining = cups_total + som(cups_delta van redeem/void/adjust)`.
- **qr_tokens** `(id, subject_type[customer|card], subject_id, nonce_hash, purpose[identify|redeem], expires_at, consumed_at?, created_at)`
  - kortlevend (≈45s), **single-use**, `nonce` alleen gehasht opgeslagen.
- **payments** `(id, card_id, method[cash|pin|mollie], amount_cents, status[recorded|paid|failed|refunded], mollie_id?, created_at)`
  - fase 1: vastleggen van de fysieke betaling t.b.v. boekhouding. fase 2: Mollie.
- **email_verifications / magic_links** — of gebruik Laravel's signed URLs + een korte one-time
  device-claim code (zie §5).

Schrijf **factories en seeders** voor alle modellen.

---

## 4. Het QR-/tokenmodel (de kern — implementeer dit zorgvuldig)

**Token uitgeven (PWA → server):**
- `POST /api/pwa/tokens` met `{ purpose: "identify" | "redeem", card_id? }`, geauth. als klant-device.
- Server genereert een willekeurige `nonce` (cryptografisch, ≥128 bit), slaat **alleen de hash** op met
  `expires_at = now()+45s`, en retourneert de **platte nonce** één keer.
- De PWA rendert de QR met inhoud `https://{app}/s/{nonce}` (deeplink, zodat ook een gewone camera
  werkt) en toont een aflopende teller. **Ververst automatisch** elke ~30s en bij focus.

**Token verzilveren (balie → server):** `POST /api/staff/scan { nonce }`, geauth. als staff.
Voer dit **atomisch** uit (DB-transactie + row lock op de token en de kaart):
1. Zoek token op hash. Weiger als niet gevonden, verlopen of al `consumed_at`.
2. Markeer token `consumed_at = now()` (eenmalig; race-safe).
3. **purpose = identify** → retourneer de klant + zijn kaarten → de balie start de
   **nieuwe-kaart-flow** (product kiezen, betaling vastleggen, activeren).
4. **purpose = redeem** → laad de kaart:
   - `status=pending` → meld "betaling + activatie vereist"; staff bevestigt → `activate`.
   - `status=active` & `cups_remaining>0` → schrijf `redeem`-event, `cups_remaining -= 1`;
     wordt het 0 → `status=depleted`. Retourneer nieuw saldo.
   - anders → nette fout (leeg/verlopen/ongeldig).

**Beveiligingseisen:**
- Tokens: single-use, korte TTL, gehasht at rest, gebonden aan subject, **atomisch geconsumeerd**.
- Rate-limit zowel token-uitgifte als scan-endpoints.
- Alle staff-acties **geaudit** (`staff_user_id` op het event).
- Alleen HTTPS; signed URLs voor e-maillinks; Sanctum-tokens met scopes (`customer` vs `staff`).
- Een kaart kan door gelijktijdige scans **nooit** onder 0 of boven `cups_total` komen — bewijs dit
  met een test die parallelle verzilvering simuleert.

---

## 5. Authenticatie

**Klant (passwordless):**
- `POST /api/auth/register { email }` → maakt/zoekt customer, mailt een **signed verificatielink**.
- Klik op link → `GET /api/auth/verify` (signed) → zet `email_verified_at`, redirect naar de PWA met
  een **eenmalige device-claim-code**.
- PWA wisselt die code in via `POST /api/auth/claim { code }` voor een **device-token** (Sanctum),
  opgeslagen in IndexedDB/localStorage.
- **Herstel:** `POST /api/auth/magic-link { email }` → mailt opnieuw een link → zelfde claim-flow →
  nieuw device-token. De kaarten "leven op de server", dus elk geauthenticeerd toestel ziet ze.

**Staff (balie):**
- Klassiek `POST /api/staff/login { email, password }` → staff-token (Sanctum), rol-gebaseerd.
- Balie-endpoints achter `auth:sanctum` + ability/rol `balie`.

---

## 6. API-endpoints (minimaal, fase 1)

```
# Klant / PWA
POST   /api/auth/register            { email }
GET    /api/auth/verify              (signed)            -> redirect naar PWA + claim-code
POST   /api/auth/magic-link          { email }
POST   /api/auth/claim               { code }            -> { device_token }
GET    /api/pwa/me                                        -> customer + kaarten + saldi
GET    /api/pwa/cards/{card}                              -> kaartdetail
POST   /api/pwa/tokens               { purpose, card_id? }-> { nonce, expires_at }

# Staff / balie
POST   /api/staff/login              { email, password } -> { staff_token }
POST   /api/staff/scan               { nonce }           -> resolved subject (klant of kaart + saldo)
POST   /api/staff/cards              { customer_id, card_product_id, payment{method,amount_cents} }
                                                          -> maakt + activeert kaart (issue+activate+payment)
POST   /api/staff/cards/{card}/activate { payment{...} } -> activeert een pending kaart
GET    /api/staff/products                               -> actieve card_products
```
Retourneer nette, voorspelbare JSON-fouten (422/409 met code + bericht). Documenteer de endpoints
in `API.md`.

---

## 7. Flows (stap voor stap)

**A. Klant registreert** → e-mail verifiëren → PWA opent → device-token → leeg "nog geen kaart"-scherm
met knop **"Toon QR om een kaart te kopen"** (identify-token).

**B. Kaart kopen aan de balie** → klant toont identify-QR → balie scant → kiest product (bv. 12-voor-10)
→ legt fysieke betaling vast (pin/contant, bedrag = `cups_paid × price_per_cup`) → server doet
`issue + activate + payment` in één transactie → kaart `active`, saldo `cups_total` → klant ziet het
saldo live in de PWA.

**C. Koffie verzilveren** → klant opent kaart in PWA → roterende redeem-QR → balie scant → server
`redeem` (−1) → saldo daalt → klant ziet 12 → 11 live.

**D. Herstel** → klant op nieuw toestel → magic-link → claim → kaarten en saldi terug vanaf server.

---

## 8. PWA-eisen (klant)

- Installeerbaar: manifest met naam "Koffiebon", icoon, `theme_color` espresso, `display: standalone`.
- Service worker cachet de app-shell; **QR-weergave vereist netwerk** — toon een duidelijke
  offline-staat ("Ga online om je QR te tonen").
- Per kaart: saldo (`9 / 12`), voortgangsbalk, grote roterende QR met aflopende teller,
  "Toon aan de balie", en een "Kaart kwijt? Herstel via e-mail"-link.
- Auto-refresh van de QR (~30s) en bij window-focus. Nooit saldo client-side "verzinnen".
- Visuele stijl conform de pitch (zie de telefoon-mockup in `pitch/index.html`, slide 4).

## 9. Balie-app-eisen

- Achter staff-login. Werkt op telefoon én desktop.
- Scannen via **camera** (`@zxing/browser`) **en** **hardware-scanner** (keyboard-wedge input).
- Na scan: toon klant/kaart, **groot** het resterende saldo en een duidelijke bevestiging
  ("☕ geschonken — nog 8"). Bij identify: productkeuze + betaal-vastlegging + activeren.
- Snelle, foutbestendige UI; nette foutmeldingen (verlopen/ongeldige/lege kaart).
- Eis: internet aanwezig (documenteer dit; geen offline verzilvering in fase 1).

## 10. Betaling

- **Fase 1:** alleen **vastleggen** van de fysieke betaling (`payments` record, method pin/contant).
- **Fase 2 (Mollie):** abstraheer betaling achter een `PaymentProvider`-interface zodat Mollie later
  inplugbaar is. Implementeer Mollie pas wanneer er een API-key beschikbaar is (vraag erom; bouw tot
  dan tegen de interface met een fake provider + tests).

---

## 11. Fasering met Definition of Done

**Fase 1 — Live aan de balie (MVP)**
- [ ] Laravel-API met datamodel, migraties, factories, seeders (demo-merchant, balie-user, product,
      geverifieerde demo-klant met een voorbeeldkaart).
- [ ] Klant-auth (register/verify/magic-link/claim) met Mailpit lokaal werkend.
- [ ] Staff-auth + rollen.
- [ ] Token-uitgifte + atomische scan/verzilvering + activatie + fysieke betaling vastleggen.
- [ ] Klant-PWA: installeerbaar, kaarten + roterende QR + herstel.
- [ ] Balie-app: camera- én hardware-scan, activeren en afboeken.
- [ ] Tests groen, incl. de **parallelle-verzilvering-test** (nooit < 0 of > totaal).
- [ ] Zelf geverifieerd: app gestart, demo-flow A→D doorlopen, screenshots van PWA + balie.
- **DoD:** een verse checkout kan via `README` lokaal opgestart worden en de hele flow A→D werkt.

**Fase 2 — Online & betalen**
- [ ] Mollie-integratie achter `PaymentProvider` (online kaart kopen / opwaarderen).
- [ ] Kaart cadeau doen (uitgeven aan een ander e-mailadres).
- **DoD:** online aankoop met testbetaling activeert een kaart end-to-end.

**Fase 3 — Groei**
- [ ] Merchant-dashboard met klant-/omzetdata (terugkerende klanten, frequentie, drukte).
- [ ] Meerdere card_products en acties; meerdere vestigingen (locations actief gebruiken).
- **DoD:** dashboard toont echte cijfers uit het grootboek; multi-vestiging werkt.

---

## 12. Acceptatiecriteria (functioneel)

1. Zonder geverifieerd e-mailadres kan een klant **geen** kaart krijgen.
2. De getoonde QR is na ~45s of na één scan **ongeldig** (bewijs met test).
3. Saldo daalt uitsluitend server-side; de PWA toont altijd de serverwaarde.
4. Een kaart kan nooit boven `cups_total` of onder 0 (race-test verplicht).
5. Herstel via e-mail geeft op een ander toestel exact dezelfde kaarten/saldi.
6. Kaartprijs en marge volgen uit `cups_paid`, `price_per_cup_cents` en `cost_per_cup_cents`;
   korting blijft gelijk als de prijs per kop verandert (bewijs met een unit-test op de pricing-service).
7. Alle balie-acties zijn herleidbaar tot een `staff_user` in het grootboek.

---

## 13. Compliance & privacy (inbouwen, niet als bijzaak)

- **Prepaid = verplichting** tot verzilvering. Leg betalingen vast; omzet is pas omzet bij
  verzilvering (`redeem`-events dragen de cup-waarde voor latere boekhouding/rapportage).
- **Geldigheidsduur:** `expires_at` per kaart op basis van `validity_days`; default ruim
  (bv. 24 maanden). Houd rekening met NL-regels voor minimale geldigheid van waardebonnen.
- **Privacy/AVG:** sla minimale PII op (e-mail + optioneel naam). Bied verwijdering van een klant +
  zijn data. Geen tracking van meer dan nodig.

## 14. Niet-doelen (fase 1)

- Geen native iOS/Android-app. Geen offline verzilvering. Geen loyaliteits-/puntenmechaniek
  bovenop de prepaid-koppen. Geen Mollie vóór fase 2. Geen meervoudige merchants in de UI vóór fase 3
  (wel in het datamodel voorbereid).

---

## 15. Oplevering

- `README.md` met lokale setup (Laravel + Vite + Mailpit), seed-commando en de demo-credentials.
- `API.md`, `DECISIONS.md`, `PROGRESS.md` bijgewerkt.
- Tests groen; commando om ze te draaien gedocumenteerd.
- Korte demonstratie: screenshots van de PWA (kaart + QR) en de balie-app (na een scan).

> Begin met fase 1. Maak je branch, zet de Laravel-app en het datamodel op, en bouw richting een
> werkende A→D-flow. Verifieer door de app echt te draaien. Succes.
