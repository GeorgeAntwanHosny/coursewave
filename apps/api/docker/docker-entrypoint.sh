#!/bin/sh
set -eu

cd /var/www/html
ROLE="${CONTAINER_ROLE:-web}"

log() { echo "[entrypoint:${ROLE}] $*"; }
as_app() { su-exec www-data "$@"; }

# 1) Writable directories. A freshly mounted volume starts empty, and the queue worker (www-data) must be able
#    to write what php-fpm (also www-data) later serves, e.g. certificate PDFs.
mkdir -p storage/app/public storage/app/private/certificates storage/fonts \
         storage/framework/cache/data storage/framework/sessions storage/framework/views \
         storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

# 2) Package manifest (composer ran with --no-scripts)
as_app php artisan package:discover --ansi >/dev/null

# 3) Production caches, built from THIS container's runtime environment (nothing is baked into the image).
if [ "${APP_ENV:-production}" = "production" ]; then
  log "caching config, routes, views and events"
  as_app php artisan config:cache
  as_app php artisan route:cache || log "route:cache skipped"
  as_app php artisan view:cache
  as_app php artisan event:cache
  as_app php artisan filament:optimize || log "filament:optimize skipped"
fi

# 4) Role-specific preparation
case "$ROLE" in
  web)
    as_app php artisan filament:assets
    [ -L public/storage ] || as_app php artisan storage:link
    ;;
  scheduler)
    # withoutOverlapping() locks last up to 24h; a container killed mid-task would otherwise block that task all day.
    as_app php artisan schedule:clear-cache || true
    ;;
esac

# 5) `docker compose run web php artisan ...` runs that command instead of the role
if [ "$#" -gt 0 ]; then
  log "running: $*"
  exec su-exec www-data "$@"
fi

case "$ROLE" in
  web|worker|scheduler|reverb)
    log "starting supervisord"
    exec supervisord -n -c "/etc/supervisor/roles/${ROLE}.conf"
    ;;
  migrate)
    log "running migrations"
    exec su-exec www-data php artisan migrate --force
    ;;
  *)
    echo "Unknown CONTAINER_ROLE [${ROLE}] (expected web, worker, scheduler, reverb or migrate)" >&2
    exit 1
    ;;
esac
