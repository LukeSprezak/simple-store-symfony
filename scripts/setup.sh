#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/.."

console() {
    docker compose exec php85 php bin/console "$@"
}

docker compose build --pull php85
docker compose up -d --wait mysql redis rabbit php85
docker compose exec php85 composer install
console lexik:jwt:generate-keypair --skip-if-exists
console doctrine:migrations:migrate --no-interaction
console doctrine:migrations:migrate --env=test --no-interaction
console doctrine:fixtures:load --no-interaction
docker compose up -d --wait nginx frontend
