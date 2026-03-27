#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

docker compose exec -T laravel.test php artisan tinker --env=testing --execute="if (config('database.connections.mysql.database') !== 'testing') { throw new RuntimeException('Refusing to run tests against [' . config('database.connections.mysql.database') . ']'); }"

docker compose exec -T laravel.test php artisan migrate:fresh --env=testing --force

if [ "$#" -eq 0 ]; then
    docker compose exec -T laravel.test php artisan test
else
    docker compose exec -T laravel.test php artisan test "$@"
fi
