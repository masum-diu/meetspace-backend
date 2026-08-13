#!/bin/sh
set -e

mkdir -p database
touch database/database.sqlite

php artisan migrate --force

exec php artisan serve --host=0.0.0.0 --port="${PORT:-10000}"
