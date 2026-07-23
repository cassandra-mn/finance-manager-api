#!/bin/sh
set -e

# config:cache and migrate need the real runtime environment variables
# (DB_*, APP_KEY, etc.). Those only exist once the container actually starts
# on Render — not during `docker build`, which has no database to connect to
# and no access to the runtime env vars. Running them here (instead of as a
# Dockerfile RUN instruction) is what makes the cached config and the schema
# match what the container is actually running with. route:cache/view:cache
# don't depend on runtime secrets, so they stay in the build (see Dockerfile).
php artisan config:cache
php artisan migrate --force

# The commands above may create/touch files as root; keep ownership
# consistent with the user PHP-FPM workers run as before handing off.
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

exec "$@"
