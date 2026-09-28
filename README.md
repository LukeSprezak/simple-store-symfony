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

## Read API and Query Bus

Send a JWT in `Authorization: Bearer <token>` to use these JSON endpoints:

| Endpoint | Access | Response |
| --- | --- | --- |
| `GET /api/cart/{cartId}` | Cart owner with `ROLE_USER` | `id`, `status`, `createdAt`, `expiresAt`, `items`, `totalAmountInCents` |
| `GET /api/product/{id}` | `ROLE_USER`, active products | `id`, `name`, `description`, `priceInCents`, `stockQuantity` |

Cart items contain `id`, `productId`, `productName`, `unitPriceInCents`,
`quantity` and `totalAmountInCents`. Names and prices come from the cart snapshot;
removed items are excluded. Empty carts return `items: []` and a zero total.
Dates use ISO 8601 with an offset. All `*InCents` fields are integers.

Foreign and missing carts return 404. Inactive and missing products also return
404. Expired and converted carts remain readable by their owner. Reads do not
expire carts or release stock. Product creation/removal requires
`ROLE_SUPER_ADMIN`. Route IDs must be UUIDs, including the v4 and v7 IDs used by
the application; malformed IDs return 404.

Controllers ask the application `QueryBus` for immutable read models.
`GetCartQuery` and `GetProductQuery` run on Messenger's synchronous `query.bus`,
which requires exactly one handler and checks the result type. Reader ports
live in `Application/ReadModel`; their DBAL adapters query existing tables
directly. The cart reader retrieves the owner-filtered cart and its items in
one SQL statement. These reads neither hydrate ORM aggregates nor flush pending
ORM changes. Queries have no transport routing or Doctrine transaction
middleware.

The product and cart-detail read models use existing tables. Changes become
visible after they are stored; asynchronous product commands become visible
after the worker processes them. Cart activity uses the event projection
described below and is eventually consistent.

The API uses stateless JWT authentication with sessions disabled. Exception
details and traces are included only when `kernel.debug` is true; use
`APP_DEBUG=0` in deployed environments, including staging.

## Cart limits

`src/Order/Domain/Policy/CartLimits.php` defines the limits: 10 units of each
product per cart and 3 active carts per owner. Repeated additions count toward
the same quantity limit. A removed item no longer contributes to that quantity.

Both `active` and `abandoned` carts occupy an allowance slot, including empty
carts and carts past their deadline whose reservations have not yet been
released. Conversion or expiration by the scheduler frees a slot. Existing
carts can still be edited when the owner has used the entire allowance.
The reservation TTL is 2 hours; keep the expiration worker running. Carts created before this change keep their 24-hour deadline.

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

## User credentials

User activation and password-reset tokens are stored only as SHA-256 hashes.
Pass the original token to `setToken()` / `setResetPasswordToken()` and check it
with `matchesToken()` / `matchesResetPasswordToken()`. Passing `null` or an empty
string clears that token; an unset token never matches. These fields are
separate from JWT authentication.

`plainPassword` is transient and cleared by `eraseCredentials()`; the redundant
`repeatPassword` field has been removed. Symfony's serializer excludes passwords
and token hashes. Email addresses allow up to 254 characters, with format
validation and separate uniqueness validation for email and username.

Migration `Version20260928183000` converts existing tokens to hashes and drops
the old token, plaintext-password and repeated-password columns. Existing
nonempty tokens and login password hashes remain valid. The migration is
irreversible and uses MySQL DDL outside a transaction. Pause application traffic
and workers while applying it together with this code update; the old and new
user mappings require different columns. Apply it to each environment using
the migration command in the setup section (`--env=test` for the test database).

## Workers

```sh
docker compose exec php85 php bin/console messenger:consume async -vv --limit=50
docker compose exec php85 php bin/console messenger:consume scheduler_expired_cart
docker compose exec php85 php bin/console messenger:consume events --time-limit=3600 --memory-limit=128M
```

## Transactional outbox and cart activity

Apply migration `Version20260928190000` before deploying this code to an existing
environment. It creates `domain_event_outbox` and `cart_activity`. It has no
automatic rollback because dropping the tables would discard pending events
and recorded history. Both tables are covered by Doctrine schema validation.

Cart and order repositories persist events through `DoctrineOutboxEventBus`.
The ORM flush writes the aggregate and its outbox entries in the same database
transaction. A command rollback discards both. The command does not contact
RabbitMQ. Event names are stable strings such as `cart.created`; messages carry
an event UUID, UTC recording time, JSON payload and schema version (currently 1).

Run the publisher periodically using a timer or process supervisor:

```sh
docker compose exec -T php85 php bin/console app:outbox:publish --limit=100
```

Each invocation processes at most one batch (limit 1–1000). Repeat it to drain
the backlog, and run the `events` consumer alongside it. Multiple publishers
can run concurrently: each locks one committed row with `FOR UPDATE SKIP LOCKED`
until publication finishes. The RabbitMQ transport uses a durable
`domain_events` exchange/queue, persistent messages and publisher confirmations.
An event is marked published only after confirmation. Failed sends retain the
event and retry with backoff from 2 seconds up to 5 minutes; a batch with failures
exits with status 1. Inspect `published_at`, `available_at`, `attempts` and
`last_error` to monitor the backlog. Error storage contains the exception class,
without connection details.

Delivery is at least once: a crash after broker confirmation and before the
database commit can resend the same event UUID. The cart-activity projector
uses that UUID as its primary key, so duplicate or reordered delivery does not
duplicate activity. Projection handling runs in a transaction on `event.bus`;
consumer failures use Messenger retries and the existing failure transport.

`GET /api/cart/{cartId}/activity?limit=50&after=<eventId>` requires the cart owner
and a JWT. It returns `{ "items": [...], "nextCursor": null }`. Each item has
`eventId`, `eventName`, `recordedAt`, nullable `productId` and nullable `quantity`.
`limit` defaults to 50 and must be 1–100; `after` is an optional UUID cursor.
Invalid pagination returns 422; foreign or missing carts return 404. Pass the
returned `nextCursor` as `after` to read the next page.

History is ordered by the original event UUID and can lag behind cart details.
Restart pagination to see older events delivered late. The projection records
cart creation, additions, removals, expiration and conversion. `order.placed`
is published for other consumers. History begins with this deployment; past
actions are not reconstructed from current cart state. Published outbox rows
are retained; archival/retention and replay tooling remain operational follow-up
work. A consumer of a new event schema needs an explicit compatible handler.

The tests exercise real RabbitMQ using an isolated queue that is removed after
the test, transaction rollback, publication retries and concurrent publishers.

## Local infrastructure limits

Compose publishes service ports only on `127.0.0.1`; PHP-FPM has no host port.
Recreate existing containers with `docker compose up -d --wait` to apply port
changes. The existing named MySQL and RabbitMQ volumes are reused.

PHP has a 256 MB memory limit. nginx and PHP accept request bodies up to 1 MB.
Xdebug starts on an explicit trigger (`XDEBUG_TRIGGER`); enable coverage for a
CLI run with `XDEBUG_MODE=coverage`. The Compose configuration does not set the
legacy global `XDEBUG_CONFIG` trigger. PHP and nginx hide their versions. nginx sends
`X-Content-Type-Options: nosniff` and `Referrer-Policy: no-referrer`; JSON responses
also receive a restrictive Content Security Policy. TLS/HSTS and production
image hardening still require deployment-specific configuration.

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
