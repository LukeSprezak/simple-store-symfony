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
# One-off container: the frontend service installs dependencies on start, so linting it right after would race npm ci.
docker compose run --rm --no-deps frontend sh -c "npm ci && npx ng lint"
docker compose up -d --wait nginx frontend
