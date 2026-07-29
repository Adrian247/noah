#!/bin/sh
set -e

cd /var/www/html

echo "[phoenix] Esperando base de datos…"
attempt=0
until php artisan migrate --force --no-interaction 2>/dev/null; do
  attempt=$((attempt + 1))
  if [ "$attempt" -ge 30 ]; then
    echo "[phoenix] No se pudo migrar tras 30 intentos." >&2
    exit 1
  fi
  sleep 2
done

echo "[phoenix] Demo (seed + credenciales)…"
php artisan phoenix:refresh-demo --skip-migrate --no-interaction

php artisan storage:link --force 2>/dev/null || true

echo "[phoenix] php-fpm"
exec php-fpm
