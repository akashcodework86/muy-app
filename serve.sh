#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")"

HOST="${SERVER_HOST:-127.0.0.1}"
PORT="${SERVER_PORT:-8000}"
ROUTER="vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php"

cd public
exec php -c ../php.ini -S "${HOST}:${PORT}" "../${ROUTER}"
