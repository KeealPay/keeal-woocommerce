# Keeal PHP bindings

[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](./LICENSE)

The **Keeal** PHP library provides access to the **Keeal hosted checkout API** from PHP applications: create checkout sessions with your secret key, call **merchant** endpoints (list sessions, retrieve a session), run **public** flows without the secret (retrieve session, pay, PayPal), and verify **webhook** signatures.

---

## Requirements

- **PHP** 8.1 or higher  
- PHP extension **json** (`ext-json`)

HTTPS requests use PHP’s stream wrapper (`allow_url_fopen` enabled is typical). Ensure your PHP build can make outbound **TLS** connections to your Keeal API host (OpenSSL enabled).

---

## Composer

Install the package with [Composer](https://getcomposer.org/):

```bash
composer require keeal/keeal-php
```

Composer’s autoloader registers the `Keeal\Checkout\` namespace:

```php
require_once 'vendor/autoload.php';
```

---

## Authentication

| Credential | Where to use |
|------------|----------------|
| **Secret API key** `keeal_sk_…` | **Server-side only** — `KeealCheckout` constructor (`apiKey`). Never in browsers, mobile apps, or public repos. |
| **Base URL** | API root including `/api`, e.g. `https://api.keeal.com/api`. Must match the environment of your key. |

---

## Base URL and production default

- Pass **`baseUrl`** in the client options when you want an explicit host (staging, custom deployment, etc.).
- If you **omit** `baseUrl`, the client uses **`https://api.keeal.com/api`**.
- If you define **`KEEAL_CHECKOUT_DEV_MODE`** as `true`, **`baseUrl` is required** — the client will not guess a URL in dev mode.

---

## Getting started

Create a **checkout session** and send the customer to the returned **`url`** (hosted payment page):

```php
use Keeal\Checkout\KeealCheckout;

$checkout = new KeealCheckout([
    'apiKey' => getenv('KEEAL_API_KEY'),
    // 'baseUrl' => 'https://api.keeal.com/api', // optional; defaults to production
]);

$session = $checkout->createSession([
    'line_items' => [[
        'price_data' => [
            'currency' => 'usd',
            'product_data' => ['name' => 'Pro plan'],
            'unit_amount' => 2900, // cents
        ],
        'quantity' => 1,
    ]],
    'success_url' => 'https://yoursite.com/order-received',
    'cancel_url' => 'https://yoursite.com/cart',
    'customer_email' => 'buyer@example.com',
    'client_reference_id' => 'order_123', // your order / cart id
], [
    'idempotencyKey' => 'order_123_create_session', // optional; safe retries
]);

$id = $session['id'];   // cs_…
$url = $session['url']; // redirect the browser here
```

**Idempotency:** `createSession` accepts an options array with `idempotencyKey`. Repeating the same key returns the same logical result and avoids duplicate sessions on retries.

---

## `KeealCheckout` (authenticated)

Use **`KeealCheckout`** for any route that requires your **secret key** (`Authorization: Bearer …`).

| Method | Purpose |
|--------|---------|
| `createSession(array $params, array $options = [])` | Create a session; optional `idempotencyKey` in `$options`. |
| `createSessionUrl(...)` | Same as `createSession` but returns only the **URL** string. |
| `listMerchantSessions(?array $options)` | Paginated list (`limit`, `page`). |
| `retrieveMerchantSession(string $sessionId)` | Full session for your merchant account. |
| `retrieveSession(string $sessionId)` | Public GET by id (also available without secret via `KeealCheckoutPublic`). |
| `createPayment(string $sessionId, array $params, array $options)` | Start payment on a session (public route; included on server client). |
| `cancelSession` / `abandonSession` | End a session from server flows when needed. |
| `paypalCreateOrder` / `paypalCapture` | PayPal Smart Button flow helpers. |

All paths are relative to **`baseUrl`** (e.g. `POST /checkout/sessions`).

---

## `KeealCheckoutPublic` (no secret key)

Use **`KeealCheckoutPublic`** when you must not load `keeal_sk_…` (e.g. a narrow service that only calls public routes). It only needs **`baseUrl`** (required).

```php
use Keeal\Checkout\KeealCheckoutPublic;

$public = new KeealCheckoutPublic([
    'baseUrl' => getenv('KEEAL_BASE_URL') ?: 'https://api.keeal.com/api',
]);

$session = $public->retrieveSession('cs_xxx');
```

Supported: `retrieveSession`, `createPayment`, `cancelSession`, `abandonSession`, `paypalCreateOrder`, `paypalCapture`.

---

## Webhooks

Inbound events from Keeal include a **`X-Keeal-Signature`** header. Verify using your **`whsec_…`** signing secret and the **raw** request body bytes (do not re-encode JSON after parsing).

```php
use Keeal\Checkout\WebhookVerifier;

// $rawBody = file_get_contents('php://input'); // raw bytes / string as received
// $signature = $_SERVER['HTTP_X_KEEAL_SIGNATURE'] ?? '';

$ok = WebhookVerifier::verify(
    $rawBody,
    $signature,
    getenv('KEEAL_WEBHOOK_SECRET')
);
```

Use a **different** `whsec` per environment (staging vs production), the same way you use different API keys.

---

## Helpers (`CheckoutHelpers`)

Small utilities for integration code:

| Static method | Use |
|---------------|-----|
| `normalizeBaseUrl(string $url)` | Trim trailing slashes for consistent joins. |
| `isCheckoutSessionId(string $value)` | Returns true for ids starting with `cs_`. |
| `assertSingleCurrency(array $lineItems)` | Ensures one currency across line items (throws if mixed). |
| `previewTotalCents(array $lineItems)` | Sum of `unit_amount * quantity` in cents. |
| `randomIdempotencyKey()` | UUID-style key if you don’t supply your own. |

---

## Errors

API failures throw **`Keeal\Checkout\KeealCheckoutException`**:

- `getMessage()` — human-readable message  
- `httpStatus` — HTTP status code  
- `errorCode`, `details`, `body` — when the API returns structured errors  

```php
use Keeal\Checkout\KeealCheckoutException;

try {
    $checkout->createSession([/* … */]);
} catch (KeealCheckoutException $e) {
    $status = $e->httpStatus;
    // log, map to user message, etc.
}
```

---

## Advanced: custom HTTP transport

For tests or a custom stack, implement **`Keeal\Checkout\HttpTransportInterface`** and pass **`http`** in the client options array alongside `apiKey` / `baseUrl`. The default transport uses PHP streams.

---

## Staging vs production

Keeal secret keys do **not** use `sk_test_` / `sk_live_` prefixes. Isolate environments with:

1. Different **`baseUrl`** (each ending with `/api`).  
2. Different **`keeal_sk_…`** keys from the Keeal dashboard for that environment.  
3. Different **`whsec_…`** and webhook URLs.

---

## Documentation

- **Hosted checkout API** — request/response shapes, events (e.g. `checkout.session.completed`): see Keeal’s product documentation.  
- **Laravel** apps may prefer **`keeal/laravel-checkout`** (facades, config, webhook middleware) built on this package — see [`../laravel/README.md`](../laravel/README.md) in this monorepo.

---

## License

**MIT** — see [`LICENSE`](./LICENSE).
