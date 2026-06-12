# Koffiebon API (fase 1)

REST + Sanctum **bearer-tokens** (geen cookie-sessies). Basis-URL lokaal: `http://localhost`.
Bedragen in hele centen. Fouten komen als voorspelbare JSON terug.

## Authenticatie & abilities

Twee soorten tokens, onderscheiden via Sanctum-abilities:

- **`customer`** — passwordless device-token van de PWA. Vereist op `/api/pwa/*`.
- **`staff`** — balie-token (e-mail + wachtwoord). Vereist op de beschermde `/api/staff/*`-routes.

Stuur het token mee als `Authorization: Bearer <token>`.

## Foutformaat

Domeinfouten:

```json
{ "code": "token_expired", "message": "De code is verlopen. Vraag een nieuwe QR." }
```

| code                | status | betekenis |
|---------------------|--------|-----------|
| `token_invalid`     | 409    | onbekende/ongeldige nonce |
| `token_expired`     | 409    | token verlopen (~45s) |
| `token_consumed`    | 409    | token al gebruikt (single-use) |
| `card_not_redeemable` | 409  | kaart niet actief of leeg |
| `email_not_verified`| 422    | klant heeft geen geverifieerd e-mailadres |
| `card_not_pending`  | 422    | kaart is niet (meer) in afwachting van activatie |

Validatiefouten gebruiken het standaard Laravel-formaat (`422` met `errors`).

---

## Klant / PWA

### `POST /api/auth/register`
Body: `{ "email": "klant@example.com" }` → `202`. Maakt/zoekt de klant en mailt een ondertekende
verificatielink. Lekt nooit of het adres al bestond. _Throttle: 6/min._

### `POST /api/auth/magic-link`
Body: `{ "email": "klant@example.com" }` → `202`. Stuurt opnieuw een ondertekende link (herstel),
maar alleen als de klant bestaat. _Throttle: 6/min._

### `GET /api/auth/verify/{customer}` _(signed)_
Volgt vanuit de e-mail. Markeert `email_verified_at` en **redirect** naar de PWA op
`{FRONTEND_URL}/claim?code=<eenmalige-code>`. Een ongeldige/ontbrekende signature → `403`.

### `POST /api/auth/claim`
Body: `{ "code": "<claim-code>" }` → `{ "device_token": "...", "customer": { ... } }`.
Wisselt de eenmalige code in voor een Sanctum device-token (ability `customer`). _Throttle: 20/min._

### `GET /api/pwa/me` _(customer)_
→ `{ "id", "email", "name", "email_verified", "cards": [ ... ] }` — de klant met alle kaarten + saldi.

### `GET /api/pwa/cards/{card}` _(customer)_
→ kaartdetail (incl. `product`). `403` als de kaart niet van de klant is.

### `POST /api/pwa/tokens` _(customer)_
Body: `{ "purpose": "identify" }` of `{ "purpose": "redeem", "card_id": 1 }`
→ `{ "nonce", "purpose", "expires_at", "url" }`. Kortlevend (~45s), single-use. `url` is de deeplink
`{FRONTEND_URL}/s/{nonce}` voor een gewone camera. Redeem vereist een **actieve** eigen kaart.
_Throttle: 60/min._

---

## Staff / balie

### `POST /api/staff/login`
Body: `{ "email", "password" }` → `{ "staff_token", "staff": { id, name, role, merchant_id, location_id } }`.
Token-ability `staff`. Onjuiste gegevens → `422`. _Throttle: 10/min._

### `POST /api/staff/logout` _(staff)_
Verwijdert het huidige token → `{ "message": "Uitgelogd." }`.

### `GET /api/staff/products` _(staff)_
→ `{ "data": [ { id, name, cups_total, cups_paid, price_per_cup_cents, currency, validity_days,
card_price_cents, gift_cups, discount_rate } ] }` — actieve producten van de merchant.

### `GET /api/staff/drinks` _(staff)_
→ `{ "data": [ { id, type, type_label, size, size_label, cost_cents } ] }` — de drankenkaart
(4 soorten × 3 maten) van de merchant, voor de drank-keuze bij het verzilveren.

### `POST /api/staff/scan` _(staff)_
Body: `{ "nonce": "...", "drink_id"?: 1 }`. Consumeert de token **atomisch** (single-use) en handelt
af op `purpose`:

- **identify** → `{ "type": "identify", "customer": { ...kaarten }, "products": [ ... ] }`
  (start de nieuwe-kaart-flow).
- **redeem**, kaart actief → `{ "type": "redeem", "result": "redeemed", "card": { ...nieuw saldo },
  "drink": { ... } | null, "customer": { id, email } }`. Een opgegeven `drink_id` wordt op het
  redeem-event vastgelegd (type/maat/kostprijs) voor analytics; één scan blijft één kop.
- **redeem**, kaart `pending` → `409 { "result": "needs_activation", ... }`.
- leeg/verlopen/ongeldig → `409 card_not_redeemable` / `token_*`.

_Throttle: 120/min._

### `GET /api/staff/dashboard` _(staff, **admin only** → anders `403`)_
Query: `location_id?` (vestiging van de merchant), `from?`/`to?` (datums, default laatste 30 dagen).
→ echte cijfers uit het grootboek:

```json
{
  "range": { "from": "...", "to": "..." },
  "locations": [ { "id", "name" } ],
  "summary": { "cards_sold", "active_cards", "cups_outstanding", "cups_redeemed",
               "revenue_cents", "drink_cost_cents" },
  "by_location": [ { "id", "name", "cards", "cups_redeemed", "cups_outstanding", "revenue_cents" } ],
  "by_drink": { "by_type": [ { "type", "label", "sizes": {...}, "total" } ],
                "by_size": [ { "size", "label", "count" } ] },
  "activity": [ { "date", "count" } ],
  "customers": { "total", "returning", "one_time", "avg_cups_per_customer" }
}
```

### `POST /api/staff/cards` _(staff)_
Body: `{ "customer_id", "card_product_id", "payment": { "method": "pin"|"cash" } }`
→ `201 { "card": { ... } }`. Doet `issue + activate + payment` in één transactie. De kaartprijs wordt
**server-side** afgeleid (`cups_paid × price_per_cup_cents`). Vereist een geverifieerd e-mailadres
(anders `422 email_not_verified`).

### `POST /api/staff/cards/{card}/activate` _(staff)_
Body: `{ "payment": { "method": "pin"|"cash" } }` → `{ "card": { ... } }`. Activeert een pending-kaart.
`422 card_not_pending` als de kaart al actief/anders is.
