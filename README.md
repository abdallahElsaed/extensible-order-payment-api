# Extensible Order & Payment Management API

[![CI](https://github.com/abdallahElsaed/extensible-order-payment-api/actions/workflows/ci.yml/badge.svg)](https://github.com/abdallahElsaed/extensible-order-payment-api/actions/workflows/ci.yml)

A Laravel JSON API for managing customer orders and processing payments through
pluggable, simulated payment gateways. Authentication uses JWT; money is stored
as integer minor units to avoid floating-point drift; and new payment gateways
can be added without modifying any existing gateway or the core payment flow.

- **PHP** 8.4, **Laravel** 13
- **Auth**: `php-open-source-saver/jwt-auth` via the `api` guard
- **Tests**: Pest (unit, feature, and real multi-process races on MySQL)

## Requirements

- PHP 8.4 (Laravel Herd recommended)
- Composer
- MySQL with databases named `order_payment_api` and `order_payment_api_test`

## Setup

```bash
composer install
cp .env.example .env          # then set DB_CONNECTION=mysql, DB_DATABASE=order_payment_api and credentials
php artisan key:generate
php artisan jwt:secret        # writes JWT_SECRET to .env

php artisan migrate
php artisan db:seed --class=DemoSeeder   # optional demo data (demo@example.com / password)
```

Served by Herd at `http://extensible-order-payment-api.test`. All endpoints are
prefixed with `/api`.

Run `php artisan schedule:run` every minute in production. It expires payments stuck in
`pending` (see [the payment flow decision](docs/payment-flow.md)).

## Running tests

Tests need MySQL: `lockForUpdate()` does nothing on SQLite, so a SQLite run would prove
nothing about locking. `phpunit.xml` pins the `order_payment_api_test` database, and the
base `TestCase` refuses any database whose name does not end in `_test`. Host and
credentials come from `.env`.

```bash
php artisan test --compact                         # full suite
php artisan test --compact --filter=Payment        # one story
php artisan test --compact --testsuite=Concurrency # races between real processes
vendor/bin/pint --format agent                     # code style
```

The `Concurrency` suite starts separate PHP processes, each with its own MySQL connection,
and sends them through the HTTP kernel at the same moment.

## Response envelope

Every response is wrapped in a consistent envelope:

```json
{ "success": true, "message": "...", "data": {}, "errors": null }
```

- `2xx` → `success: true`, payload in `data`.
- `402` declined payment → `success: true`, with the failed payment in `data`.
- `422` validation → `success: false`, field errors in `errors`.
- `401` / `404` / `409` / `429` → `success: false` with a `message`.
- Paginated lists also include `links` and `meta`.

`GET /api/user` is the exception: it returns the bare user.

Money fields (`total`, `unit_price`, `line_total`) are returned as decimal strings but
stored internally as integer minor units.

## Endpoints

Authenticated routes require an `Authorization: Bearer <token>` header.

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| POST | `/api/auth/register` | no | Register, returns `{ user, token }` |
| POST | `/api/auth/login` | no | Login, returns `{ user, token }`. Limited to 5 attempts a minute per email and IP, and 30 per IP |
| GET | `/api/user` | yes | Current user |
| GET | `/api/orders` | yes | List own orders (`?status=`, paginated) |
| POST | `/api/orders` | yes | Create an order with line items |
| GET | `/api/orders/{order}` | yes | Show an order |
| PATCH, PUT | `/api/orders/{order}` | yes | Update status and/or items (items are blocked if payments exist) |
| DELETE | `/api/orders/{order}` | yes | Delete (blocked if payments exist) |
| POST | `/api/orders/{order}/payments` | yes | Process a payment on a confirmed order |
| GET | `/api/orders/{order}/payments` | yes | List payments for an order |
| GET | `/api/payments` | yes | List all own payments (paginated) |
| GET | `/api/payments/{payment}` | yes | Show a payment by UUID |

### Core flow (happy path)

1. `POST /api/auth/register` → grab the `token`.
2. `POST /api/orders` with `items` → order created `pending`, `total` auto-summed.
3. `PATCH /api/orders/{id}` `{ "status": "confirmed" }`.
4. `POST /api/orders/{id}/payments` `{ "method": "credit_card" }` → payment recorded
   with the simulated outcome.
5. `GET /api/payments` → your paginated payments.

Status transitions are enforced: `pending` may become `confirmed` or `cancelled`, and
`confirmed` may become `cancelled`. Invalid transitions, changing the items of an order
that has payments, and deleting an order that has payments all return `409`.

### Payments

The gateway is called outside any transaction, so a slow provider never holds a row lock.
[docs/payment-flow.md](docs/payment-flow.md) explains the design, with a sequence diagram.

| Status | Meaning |
|--------|---------|
| `201` | Payment successful |
| `202` | Outcome unknown (gateway error or timeout). The payment stays `pending`; check `GET /api/payments/{payment}` |
| `402` | Payment failed or declined |
| `409` | Order not confirmed, already paid, another payment in progress, or a late gateway result needs manual review |
| `422` | Invalid method or `Idempotency-Key`, or the key was used with a different request |

Send an optional `Idempotency-Key` header (1 to 255 visible ASCII characters) to retry
safely. A replay returns the result of the first request without charging again. Keys are
scoped to the user.

## Adding a new payment gateway

`PaymentGatewayRegistry` resolves gateways by convention, not configuration. For a
`PaymentMethod` case named `Foo`, it resolves the class
`App\Services\Payment\Gateways\FooGateway` from the container. So registering a new
gateway needs no config edits and no changes to any existing gateway, the registry,
or the payment flow — just two additions:

1. **Add the enum case** in `app/Enums/Payment/PaymentMethod.php`. The case name
   determines the gateway class name; the value is the API-facing method string:

   ```php
   case Stripe = 'stripe';
   ```

2. **Create the matching gateway class** implementing the contract. The name must
   follow the convention (`{CaseName}Gateway`) so the registry can find it:

   ```php
   // app/Services/Payment/Gateways/StripeGateway.php
   final class StripeGateway implements PaymentGatewayContract
   {
       public function process(ProcessPaymentData $data): GatewayResult
       {
           // ... return new GatewayResult(status, reference, message)
       }
   }
   ```

That is the whole change. `Rule::enum(PaymentMethod::class)` validation and the
registry pick up the new method automatically; a method without a matching gateway
class yields a `422`. If the gateway calls a real provider, read its credentials
from the `credentials` block in `config/payments.php` (sourced from env).

A gateway should throw when it does not know the outcome, such as on a timeout. The
payment then stays `pending` rather than being reported as failed. Before going live with
a real provider, read the note on expiry in [docs/payment-flow.md](docs/payment-flow.md).

## Postman

An importable collection lives at `docs/postman_collection.json`, organized into
Authentication / Orders / Payments with success and error examples. Set the
`base_url` and `token` collection variables after importing.
