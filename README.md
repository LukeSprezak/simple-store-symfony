# Symfony CQRS

Development stack: Symfony 8.1, PHP 8.5.11, MySQL 8.4.11 LTS, Redis 8.10.2
and RabbitMQ 4.3.6. Dependencies are pinned in `composer.lock`.

Symfony 8.2 is still under development as of 2026-09-28 (stable release planned
for November 2026), so this iteration uses the latest stable 8.1 release.
MySQL 8.4.11 is the latest available official Docker image verified for this
iteration; the 8.4.12 image was not yet available.

## Local setup

Requires Docker Desktop / Docker Engine with Docker Compose v2 or newer.

```sh
cp .env.dist .env.local
docker compose build --pull php85
docker compose up -d --wait mysql redis rabbit php85
docker compose exec php85 composer install
docker compose exec php85 php bin/console lexik:jwt:generate-keypair --skip-if-exists
docker compose exec php85 php bin/console doctrine:migrations:migrate --no-interaction
docker compose exec php85 php bin/console app:create-user
docker compose up -d nginx phpmyadmin
```

Set a local `APP_SECRET` in `.env.local`. Existing `.env.local` files must use
the new MySQL URL and Messenger variables from `.env.dist`.

The API is available at http://localhost:8080, phpMyAdmin at
http://localhost:8883 and RabbitMQ management at http://localhost:15672.
MySQL is exposed on port 13306; inside Docker its hostname is `mysql`.
The supplied credentials are for local development.

## Tests and validation

MySQL initializes `dbname` and `dbname_test` in a fresh volume and grants the
development `app` user access to both. Test migrations only affect `dbname_test`.

```sh
docker compose exec php85 php bin/console doctrine:migrations:migrate --env=test --no-interaction
docker compose exec -e XDEBUG_MODE=off php85 php bin/phpunit
docker compose exec php85 composer validate --strict --no-check-publish
docker compose exec php85 composer check-platform-reqs
docker compose exec php85 php bin/console lint:container
docker compose exec php85 php bin/console lint:yaml config
```

The integration suite requires MySQL, RabbitMQ and generated JWT keys.
Unit tests can also be run separately with `php bin/phpunit tests/Unit` on PHP 8.5.

## Cart limits

`src/Order/Domain/Policy/CartLimits.php` defines the limits: 10 units of each
product per cart and 3 active carts per owner. Repeated additions count toward
the same quantity limit. A removed item no longer contributes to that quantity.

Both `active` and `abandoned` carts occupy an allowance slot, including empty
carts and carts past their deadline whose reservations have not yet been
released. Conversion or expiration by the scheduler frees a slot. Existing
carts can still be edited when the owner has used the entire allowance.
The reservation TTL remains 24 hours; keep the expiration worker running.

The API returns 422 for a request quantity outside 1–10 and 409 when an addition
exceeds the accumulated product quantity or a new cart exceeds the owner's
allowance. Rejected operations do not reserve stock. Existing data is not
automatically reduced or deleted when these limits are introduced.

Creation is checked inside the command transaction. The guard locks the owner
row until commit and checks occupied slots using a locking read, including when
the transaction already has an older snapshot. See the
[MySQL locking-read semantics](https://dev.mysql.com/doc/refman/8.4/en/innodb-locking-reads.html).
Call creation commands through the command bus so the check and cart persistence
share the same transaction. Migration `Version20260928180000` adds the
`cart(owner_id, status)` index; apply pending migrations when updating an existing
environment using the setup command above.

## Workers

```sh
docker compose exec php85 php bin/console messenger:consume async -vv --limit=50
docker compose exec php85 php bin/console messenger:consume scheduler_expired_cart
```

## Existing development data

MySQL uses a new `mysql_storage` volume. The previous MariaDB `database_storage`
volume is not reused or deleted. To retain its data, export a logical SQL dump
with the old MariaDB environment, review its MySQL compatibility, and import it
into MySQL before running outstanding migrations. Do not copy MariaDB data files
into the MySQL volume.

RabbitMQ uses a new `rabbitmq_storage` volume and a stable hostname. The old
`etc/docker/volumes/rabbitmq` directory remains untouched. The new broker starts
without old queues/messages; drain or migrate required messages before switching
an existing environment. A direct data-directory upgrade from RabbitMQ 3.8 to
4.3 is not supported.
