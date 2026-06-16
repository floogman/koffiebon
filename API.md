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
→ kaartdetail (incl. `product`). Bevat ook `preferred_coffee_type`, `preferred_cup_size` en
`preferred_drink_label` (bv. "Cappuccino · Medium"). `403` als de kaart niet van de klant is.

### `GET /api/pwa/drinks` _(customer)_
→ `{ "data": [ { id, type, type_label, size, size_label, cost_cents } ] }` — de actieve drankenkaart
zodat de klant bij het kopen van een kaart een vast drankje kan kiezen. (Single-merchant MVP.)

### `POST /api/pwa/tokens` _(customer)_
Body: `{ "purpose": "identify", "preferred_coffee_type", "preferred_cup_size" }` of
`{ "purpose": "redeem", "card_id": 1 }`
→ `{ "nonce", "code", "purpose", "preferred_drink", "expires_at", "url" }`. Kortlevend (~60s),
single-use. `url` is de deeplink `{FRONTEND_URL}/s/{nonce}` voor een gewone camera. `code` is een
6-cijferige baliecode (100000–999999) die de balie i.p.v. de QR met de hand kan intypen — hoort bij
dezelfde token, dus één scan/code consumeert beide. Bij **identify** kiest de klant vooraf een vast
drankje (**verplicht**); dit reist mee in de token en komt op de nieuwe kaart. `preferred_drink` is de
tekstuele weergave (identify: de gekozen drank; redeem: het vaste drankje van de kaart). Redeem vereist
een **actieve** eigen kaart. _Throttle: 60/min._

### `POST /api/broadcasting/auth` _(customer)_
Kanaal-autorisatie voor de WebSocket (Reverb). De PWA stuurt `{ socket_id, channel_name }`; de server
autoriseert alleen het eigen kanaal `private-Customer.{id}`. Bearer-token (geen cookies), zodat de PWA
same-origin met Sanctum werkt.

### Live events (Reverb)
Privé-kanaal **`Customer.{id}`** zendt event **`card.updated`** zodra de balie een kaart wijzigt:
`{ "action": "redeemed" | "activated" | "issued", "card": { …CardResource } }`. De PWA ververst
daarmee direct het saldo en toont een bevestiging. Best-effort: een Reverb-storing laat de
balie-flow nooit falen.

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
(4 soorten × 3 maten) van de merchant. _(Niet meer gebruikt bij het scannen: het drankje volgt uit de
kaart. Beschikbaar voor beheer/overzicht.)_

### `POST /api/staff/scan` _(staff)_
Body: `{ "nonce": "..." }`. Consumeert de token **atomisch** (single-use) en handelt af op `purpose`.
De balie kiest géén drankje meer: bij verzilveren volgt het geschonken drankje uit het **vaste drankje
van de kaart**.

- **identify** → `{ "type": "identify", "customer": { ...kaarten }, "products": [ ... ],
  "preferred_drink": { "type", "size", "label" } | null }` (start de nieuwe-kaart-flow; `preferred_drink`
  is het in de PWA gekozen vaste drankje dat de balie op de nieuwe kaart vastlegt).
- **redeem**, kaart actief → `{ "type": "redeem", "result": "redeemed", "card": { ...nieuw saldo },
  "drink": { ... } | null, "customer": { id, email } }`. Het redeem-event legt het **vaste drankje van
  de kaart** vast (type/maat als tekst); de bijpassende drink-rij levert de kostprijs (`drink` is `null`
  als die niet meer bestaat). Eén scan blijft één kop.
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
Body: `{ "customer_id", "card_product_id", "payment": { "method": "pin"|"cash" },
"preferred_coffee_type", "preferred_cup_size" }`
→ `201 { "card": { ... } }`. Doet `issue + activate + payment` in één transactie. De kaartprijs wordt
**server-side** afgeleid (`cups_paid × price_per_cup_cents`). Het vaste drankje (verplicht) komt uit de
identify-scan en wordt als tekst op de kaart vastgelegd. Vereist een geverifieerd e-mailadres
(anders `422 email_not_verified`).

### `POST /api/staff/cards/{card}/activate` _(staff)_
Body: `{ "payment": { "method": "pin"|"cash" } }` → `{ "card": { ... } }`. Activeert een pending-kaart.
`422 card_not_pending` als de kaart al actief/anders is.
