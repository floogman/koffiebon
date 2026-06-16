#!/usr/bin/env bash
# Koffiebon — deploy/update op de VPS. Draai als de eigenaar van /var/www/koffie.klusviewer.nl.
#   cd /var/www/koffie.klusviewer.nl && ./deploy/deploy.sh
set -euo pipefail

cd "$(dirname "$0")/.."   # repo-root

echo "→ Git pull"
git pull --ff-only

echo "→ Composer (productie)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "→ Migraties"
php artisan migrate --force

echo "→ Frontend build (Vite PWA)"
( cd frontend && npm ci && npm run build )

echo "→ Caches verversen"
php artisan optimize

echo "→ Workers herstarten"
php artisan queue:restart                 # queue-worker stopt netjes; systemd herstart hem
sudo systemctl restart koffiebon-reverb   # Reverb herladen

echo "✓ Deploy klaar"
