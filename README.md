# Keeal for WooCommerce

WooCommerce payment gateway for [Keeal](https://keeal.com): customers complete payment on Keeal’s hosted checkout; your store creates a session, redirects the buyer, and updates order status from signed webhooks. Supports **classic checkout** and **WooCommerce Cart & Checkout Blocks**.

[![WordPress](https://img.shields.io/badge/WordPress-6.5+-21759B?logo=wordpress&logoColor=white)](https://wordpress.org/)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.2+-96588A?logo=woocommerce&logoColor=white)](https://woocommerce.com/)
[![PHP](https://img.shields.io/badge/PHP-8.1+-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![License](https://img.shields.io/badge/License-GPLv2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

## Features

- Hosted checkout redirect (no card fields on your domain)
- Checkout session built from the WooCommerce order (line items, shipping, fees)
- REST webhook: `POST /wp-json/keeal-wc/v1/webhook` with `X-Keeal-Signature` verification (`whsec_…`)
- Events: `checkout.session.completed`, `checkout.session.payment_failed`, `checkout.session.canceled`
- Idempotent webhook handling via stored event IDs
- WooCommerce Blocks and Store API integration
- HPOS (custom order tables) compatibility declared

## Requirements

| Requirement | Version |
|-------------|---------|
| WordPress | 6.5+ |
| WooCommerce | 8.2+ |
| PHP | 8.1+ |

Production sites should use **HTTPS**. HTTPS is required for reliable webhooks and redirects.

PHP dependency: **[`keeal/keeal-php`](https://packagist.org/packages/keeal/keeal-php)** (installed with Composer into `vendor/`).

## Installation

### From a release archive

If you download a **release ZIP** that already includes the `vendor/` directory, install it under `wp-content/plugins/` and activate **Keeal for WooCommerce** and **WooCommerce** in the WordPress admin.

### From source (this repository)

1. Clone or copy the plugin into `wp-content/plugins/keeal-for-woocommerce` (main file: `keeal-for-woocommerce.php`).
2. Install dependencies:

   ```bash
   cd wp-content/plugins/keeal-for-woocommerce
   composer install --no-dev --optimize-autoloader
   ```

3. Activate **Keeal for WooCommerce** and **WooCommerce**.

### Build a distributable ZIP

Run `composer install --no-dev --optimize-autoloader`, then zip the plugin folder **including** `vendor/` so end users do not need Composer on the server.

## Configuration

1. **WooCommerce → Settings → Payments → Keeal Payment**  
   - Enable the gateway.  
   - **API key** — `keeal_sk_…` from the Keeal dashboard (server-side only).  
   - **Webhook signing secret** — `whsec_…` (recommended for signature verification).

2. **Production API** — Defaults to `https://api.keeal.com/api`. No base URL is required in production.

3. **Staging / local API** — In `wp-config.php` set:

   ```php
   define('KEEAL_WC_DEV_MODE', true);
   ```

   Then set **Keeal API base URL (dev)** in gateway settings (must include `/api`).

4. **Keeal dashboard** — Create a webhook pointing to your site, e.g.  
   `https://yourdomain.com/wp-json/keeal-wc/v1/webhook`  
   Use the same environment (keys and URL) as in WordPress.

5. Optional: **WooCommerce → Keeal** for status, webhook URL, and “Ping Keeal merchant API”.

## Webhooks

| Item | Value |
|------|--------|
| Endpoint | `POST /wp-json/keeal-wc/v1/webhook` |
| Signature header | `X-Keeal-Signature` (verified with `whsec_…`) |

For local development, expose your site with a tunnel (ngrok, Cloudflare Tunnel, etc.) so Keeal can reach the webhook URL.

## Privacy and data

When checkout runs, order and customer data needed to create a payment session are sent to Keeal’s API over HTTPS. Webhook payloads are received at your site and verified when a signing secret is configured. Describe your use of this data in your own privacy policy as required by your jurisdiction.

## Security

- **Never commit** API keys or webhook secrets.  
- Use environment-appropriate keys and webhook URLs.  
- Configure webhook signing in production.

## Troubleshooting

| Issue | What to check |
|-------|----------------|
| Redirect fails | API key, environment, and `KEEAL_WC_DEV_MODE` + dev base URL if not using production API |
| Order stays pending | Webhook URL in Keeal matches the live site; HTTPS; inbound POST allowed |
| Signature errors | Same `whsec` in WordPress as in Keeal for that webhook URL |
| Blocks checkout | WooCommerce version; compare with classic checkout |

## Support

- **Issues** — Use this repository’s issue tracker for bugs and feature requests.  
- **Keeal** — Account, API keys, and dashboard: [keeal.com](https://keeal.com).

## License

This plugin is licensed under **GPL-2.0-or-later**. See the plugin header in [`keeal-for-woocommerce.php`](keeal-for-woocommerce.php).

The bundled library **`keeal/keeal-php`** is **MIT**-licensed and [GPL-compatible](https://www.gnu.org/licenses/license-list.en.html#Expat) for distribution in a GPL plugin.

## Contributing

Fork the repository, create a branch, and open a pull request. Run `composer install` before testing; keep changes focused and match existing code style.
