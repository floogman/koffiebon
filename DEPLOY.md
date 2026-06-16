# Koffiebon — deployen op de VPS (koffie.klusviewer.nl)

Productie-opzet: **native PHP-FPM** (geen Docker), **SQLite**, **nginx + Let's Encrypt**,
**Reverb websockets** en een **queue-worker** via systemd. De React-PWA wordt als statische
Vite-build geserveerd op de root; Laravel draait achter `/api`.

Kant-en-klare bestanden staan in [`deploy/`](deploy):
- `deploy/nginx/koffie.klusviewer.nl.conf` — nginx vhost
- `deploy/systemd/koffiebon-reverb.service` — Reverb websocket-server
- `deploy/systemd/koffiebon-queue.service` — queue-worker (mail)
- `deploy/env.production.example` — productie-`.env` sjabloon
- `deploy/deploy.sh` — pull + build + migrate + workers herstarten

> **DNS vooraf:** zet een A/AAAA-record `koffie.klusviewer.nl` → het IP van je VPS.

---

## 1. Systeempakketten (eenmalig)

```bash
sudo apt update
sudo apt install -y nginx git certbot python3-certbot-nginx \
  php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl \
  php8.3-sqlite3 php8.3-bcmath php8.3-intl php8.3-zip unzip

# Composer
php -r "copy('https://getcomposer.org/installer','composer-setup.php');"
sudo php composer-setup.php --install-dir=/usr/local/bin --filename=composer
rm composer-setup.php

# Node 20 (voor de Vite-build)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs
```

## 2. Code ophalen

```bash
sudo mkdir -p /var/www/koffiebon
sudo chown -R $USER:www-data /var/www/koffiebon
git clone <jouw-git-remote> /var/www/koffiebon
cd /var/www/koffiebon
git checkout main        # of je deploy-branch
```

## 3. `.env` aanmaken

```bash
cp deploy/env.production.example .env
php artisan key:generate

# verse Reverb-secrets
echo "REVERB_APP_KEY=$(openssl rand -hex 16)"
echo "REVERB_APP_SECRET=$(openssl rand -hex 32)"
# -> zet beide in .env (en VITE_REVERB_APP_KEY pakt REVERB_APP_KEY automatisch)
```

Vul in `.env` ook de **echte SMTP-gegevens** in (`MAIL_HOST`, `MAIL_USERNAME`, …). Zonder
werkende SMTP komen de verificatie-/magic-link-mails niet aan.

## 4. SQLite + rechten

```bash
touch database/database.sqlite
sudo chown -R www-data:www-data storage bootstrap/cache database
sudo chmod -R ug+rw storage bootstrap/cache database
```

## 5. Eerste install (backend + frontend)

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan db:seed --force      # alleen de eerste keer: demo-merchant/staff/product/klant

( cd frontend && npm ci && npm run build )   # genereert frontend/dist + service worker

php artisan optimize             # config/route/view-cache
```

## 6. systemd-services (Reverb + queue)

```bash
sudo cp deploy/systemd/koffiebon-reverb.service /etc/systemd/system/
sudo cp deploy/systemd/koffiebon-queue.service  /etc/systemd/system/
sudo systemctl daemon-reload
sudo systemctl enable --now koffiebon-reverb koffiebon-queue
sudo systemctl status koffiebon-reverb koffiebon-queue --no-pager
```

## 7. nginx (HTTP eerst)

```bash
sudo ln -s /var/www/koffiebon/deploy/nginx/koffie.klusviewer.nl.conf \
           /etc/nginx/sites-enabled/koffie.klusviewer.nl.conf
sudo nginx -t
sudo systemctl reload nginx
```

Controleer de PHP-FPM-socket in de vhost: `/run/php/php8.3-fpm.sock`
(`ls /run/php/` als je twijfelt) en pas zo nodig aan.

## 8. Let's Encrypt-certificaat (HTTPS)

**Voorwaarden** — certbot doet een HTTP-01-challenge op poort 80, dus controleer eerst:

```bash
# 1. DNS wijst naar deze VPS (moet het publieke IP teruggeven)
dig +short koffie.klusviewer.nl

# 2. Poort 80 en 443 staan open in de firewall (UFW-voorbeeld)
sudo ufw allow 'Nginx Full'

# 3. De vhost uit stap 7 is geladen (server_name koffie.klusviewer.nl op poort 80)
sudo nginx -t
```

**Certificaat aanvragen** met de nginx-plugin. Die zet automatisch het 443-block erbij,
verwijst naar de certificaten en voegt een 80→443-redirect toe:

```bash
sudo certbot --nginx -d koffie.klusviewer.nl --redirect \
  -m claude@so-ict.nl --agree-tos --no-eff-email
```

- `--nginx` past je vhost direct aan; je hoeft niets handmatig te bewerken.
- `--redirect` forceert https (nodig: zonder https geen service worker / installeerbare PWA).
- `-m` is het contact-adres voor verloop-waarschuwingen; pas aan naar wens.

Na afloop herlaadt certbot nginx zelf. Test daarna:

```bash
curl -I https://koffie.klusviewer.nl        # verwacht: HTTP/2 200
curl -I http://koffie.klusviewer.nl         # verwacht: 301 -> https
```

**Auto-renew** — de certbot-installatie zet een systemd-timer (`certbot.timer`) of cronjob klaar
die certificaten ~30 dagen voor verloop vernieuwt. Net als bij je andere sites; controleer:

```bash
sudo systemctl list-timers certbot.timer    # toont de volgende run
sudo certbot renew --dry-run                 # simuleert een vernieuwing zonder de limiet te raken
```

Certbot herlaadt nginx automatisch na een vernieuwing (deploy-hook in
`/etc/letsencrypt/renewal/koffie.klusviewer.nl.conf`). Reverb hoeft niet herstart te worden:
die termineert geen TLS zelf — dat doet nginx.

## 9. Klaar — controleren

- Open **https://koffie.klusviewer.nl** → de klant-PWA laadt (installeerbaar).
- **https://koffie.klusviewer.nl/balie** → balie-app (staff-login).
- Registreer met een e-mailadres → check dat de mail aankomt (SMTP) → claim → kaart-flow.
- Live-saldo: bij een scan moet het saldo in de PWA direct dalen (Reverb/websocket).

---

## Updates uitrollen

Na een nieuwe commit op de VPS gewoon:

```bash
cd /var/www/koffiebon
./deploy/deploy.sh
```

Dat doet: `git pull` → composer → migrate → frontend-build → caches → workers herstarten.

---

## PWA installeren op de telefoon

Omdat alles nu over **https** loopt, werkt de service worker en is de app installeerbaar:

- **iOS (Safari):** deelknop → **"Zet op beginscherm"**.
- **Android (Chrome):** menu (⋮) → **"App installeren"** / "Toevoegen aan startscherm".

De app opent dan standalone (eigen icoon, espresso-themakleur) op `https://koffie.klusviewer.nl`.

---

## Aandachtspunten / keuzes

- **PWA bezit de root.** De server-rendered café-site (`SiteController` op `/` en `/menu`) is op
  dit subdomein niet bereikbaar — de PWA overschaduwt hem. Wil je die marketing-site óók live,
  zet hem dan op een apart (sub)domein met een eigen vhost die `root` op `public/` en
  `try_files … /index.php` gebruikt.
- **Reverb deelt het subdomein.** De browser verbindt via `wss://koffie.klusviewer.nl/app/{key}`
  en Laravel publiceert via `/apps/...`; beide proxyt nginx naar `127.0.0.1:8081`. Een nóg
  schonere variant is een apart `ws.koffie.klusviewer.nl`-subdomein dat ál het verkeer naar
  Reverb proxyt.
- **SQLite-backups:** `database/database.sqlite` is je hele database — neem dit bestand mee in je
  back-ups.
- **Mollie (fase 2):** laat `MOLLIE_API_KEY` leeg tot je live wilt met online betalen.
