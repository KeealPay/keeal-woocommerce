=== Keeal for WooCommerce ===
Contributors: keeal
Tags: woocommerce, payment, checkout, keeal
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.3
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

WooCommerce gateway for Keeal Payment: buyers pay on Keeal’s hosted checkout, with orders confirmed through signed webhooks.

== Description ==

This plugin adds a **Keeal Payment** gateway (hosted checkout redirect):

* Builds a checkout session from the WooCommerce order (line items, shipping, fees).
* Sends the customer to Keeal’s hosted payment page.
* Marks the order **Processing / Paid** when Keeal sends `checkout.session.completed` to your WordPress REST webhook.
* Verifies webhook signatures using your **`whsec_…`** secret (recommended).

**Production API:** The plugin uses the fixed Keeal production endpoint `https://api.keeal.com/api` unless you define **`KEEAL_WC_DEV_MODE`** as `true` in `wp-config.php` (then add a custom base URL in payment settings for local/staging).

**Requirements:** After copying the plugin, run `composer install` inside the plugin folder so `vendor/` pulls **`keeal/keeal-php`** from [Packagist](https://packagist.org/packages/keeal/keeal-php).

== Installation ==

1. Copy `keeal-for-woocommerce` into `wp-content/plugins/`.
2. In that directory, run: `composer install`
3. Activate **Keeal for WooCommerce** and **WooCommerce** in WordPress.
4. Go to **WooCommerce → Settings → Payments → Keeal** and enter your **API key** and (recommended) **webhook signing secret**. For dev-only custom API hosts, set `KEEAL_WC_DEV_MODE` in `wp-config.php` and fill **Keeal API base URL (dev)**.
5. Use **WooCommerce → Keeal** for a status overview, copy-paste webhook URL, and “Ping Keeal merchant API” (connection test).
6. In Keeal (API key → Webhook), set the webhook URL (same environment as the key).

== Frequently Asked Questions ==

= Does this support WooCommerce Cart & Checkout Blocks? =

Yes. The plugin registers with the Blocks payment API and ships a small `assets/js/keeal-blocks.js` handler. It also declares compatibility with the `cart_checkout_blocks` feature where supported. If your WooCommerce version behaves differently, fall back to classic checkout or report the WC version.

= Where do I find the webhook URL? =

**WooCommerce → Keeal** (copy field), or **WooCommerce → Settings → Payments → Keeal** under “Webhook endpoint”. Example: `https://yoursite.com/wp-json/keeal-wc/v1/webhook`.

= What data is sent to Keeal? =

Order and customer data needed to create a payment session is transmitted to Keeal’s API over HTTPS when checkout runs. Webhooks are sent to your site. Add details to your privacy policy as required by your jurisdiction.

== Changelog ==

= 1.0.3 =
* Transactions list: `meta_key` order query with Plugin Check annotation; Plugin Check pass (ABSPATH, i18n, escaping, no redundant `load_plugin_textdomain`).

= 1.0.2 =
* Transactions list: use WooCommerce `meta_key` order query instead of `meta_query` (Plugin Check compatibility).

= 1.0.1 =
* Plugin Check: ABSPATH guards in all includes; fix text domain and escaping on gateway settings; remove redundant load_plugin_textdomain (WordPress.org loads translations); PHPCS annotations for safe GET usage.

= 1.0.0 =
* First public release: hosted checkout redirect, REST webhooks with signature verification, HPOS compatibility, Cart & Checkout Blocks payment registration.
