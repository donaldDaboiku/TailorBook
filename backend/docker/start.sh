#!/usr/bin/env bash
set -euo pipefail

php artisan config:clear
php artisan migrate --force
php artisan db:seed --force

if [[ -n "${ADMIN_EMAIL:-}" && -n "${ADMIN_PASSWORD:-}" ]]; then
  php artisan admin:create \
    --name="${ADMIN_NAME:-Admin}" \
    --email="${ADMIN_EMAIL}" \
    --password="${ADMIN_PASSWORD}"
fi

php artisan config:cache
php artisan route:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
