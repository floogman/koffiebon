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

## Fase 1 — Live aan de balie (nog niet gestart)

Zie de Definition of Done in `CLAUDE.md` §11. Begin pas na een werkende, geverifieerde basis.
