# Extensible Order & Payment Management API

A Laravel JSON API for managing customer orders and processing payments through
pluggable, simulated payment gateways. Authentication uses JWT; money is stored
as integer minor units to avoid floating-point drift; and new payment gateways
can be added without modifying any existing gateway or the core payment flow.

- **PHP** 8.4, **Laravel** 12
- **Auth**: `php-open-source-saver/jwt-auth` via the `api` guard
- **Tests**: Pest (feature + unit)

## Requirements

- PHP 8.4 (Laravel Herd recommended)
- Composer
- MySQL with a database named `order_payment_api`

## Setup

```bash
composer install
cp .env.example .env          # then set DB_DATABASE=order_payment_api
php artisan key:generate

# JWT
php artisan vendor:publish --provider="PHPOpenSourceSaver\JWTAuth\Providers\LaravelServiceProvider"
php artisan jwt:secret        # writes JWT_SECRET to .env

php artisan migrate
php artisan db:seed --class=DemoSeeder   # optional demo data (demo@example.com / password)
```

Served by Herd at `http://extensible-order-payment-api.test`. All endpoints are
prefixed with `/api`.

## Running tests

```bash
php artisan test --compact                  # full suite
php artisan test --compact --filter=Payment # one story
vendor/bin/pint --format agent              # code style
```

## Response envelope

Every response is wrapped in a consistent envelope:

```json
{ "success": true, "message": "...", "data": {}, "errors": {} }
```

- `2xx` → `success: true`, payload in `data`.
- `422` validation → `success: false`, field errors in `errors`.
- `401` / `404` / `409` → `success: false` with a `message`.

Money fields (`total`, `unit_price`) are returned as decimal strings/numbers but
stored internally as integer minor units.

## Endpoints

Authenticated routes require an `Authorization: Bearer <token>` header.

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| POST | `/api/auth/register` | no | Register, returns `{ user, token }` |
| POST | `/api/auth/login` | no | Login, returns `{ user, token }` |
| GET | `/api/user` | yes | Current user |
| GET | `/api/orders` | yes | List own orders (`?status=`, paginated) |
| POST | `/api/orders` | yes | Create an order with line items |
| GET | `/api/orders/{order}` | yes | Show an order |
| PATCH | `/api/orders/{order}` | yes | Update details/items/status |
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

Status transitions are enforced (`pending → confirmed → cancelled`); invalid
transitions and deleting an order that has payments return `409`.

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

## Postman

An importable collection lives at `docs/postman_collection.json`, organized into
Authentication / Orders / Payments with success and error examples. Set the
`base_url` and `token` collection variables after importing.
