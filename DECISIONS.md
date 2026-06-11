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

## Werkwijze

- **Alle php/artisan/composer/npm/npx-commando's draaien in de container** via `./vendor/bin/sail …`,
  nooit op de host (host = PHP 8.4, container = 8.3).
- Git geïnitialiseerd in de projectroot. `pitch/` (de pitch deck) blijft ongemoeid.
