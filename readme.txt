=== Keeal for WooCommerce ===
Contributors: keeal
Tags: woocommerce, payment, checkout, keeal
Requires at least: 6.5
Tested up to: 6.9
Requires PHP: 8.1
Stable tag: 1.0.5
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

== External services ==

This plugin relies on **Keeal** (hosted checkout and merchant API) so customers can pay on Keeal’s pages and your store can confirm payment status.

**What is used for**

* Creating a Keeal checkout session from the WooCommerce order and redirecting the buyer to Keeal’s hosted payment flow.
* Optional: a connection test from **WooCommerce → Keeal** that calls the merchant API to confirm your API key and base URL work.

**What data is sent and when**

* **When checkout runs:** Your server sends HTTPS requests to the Keeal API (production: `https://api.keeal.com/api`; or your custom base URL when `KEEAL_WC_DEV_MODE` is enabled in `wp-config.php`). Each session request includes your configured **API key** (for authentication) and information derived from the order: **currency**; **line items** (product names and payable amounts, plus shipping and fee lines where applicable); the customer **billing email**; **order ID** and **order key** as references; **success** and **cancel** return URLs; and your **site URL** in metadata so Keeal can complete the redirect flow.
* **When you use “Ping Keeal merchant API”:** An administrator with **manage_woocommerce** triggers a short authenticated request (listing merchant sessions, limited to one row) to verify connectivity. No order payload is sent for that test.

**Inbound traffic:** Keeal’s servers send **webhooks** (signed HTTP `POST` requests) to the REST URL you configure in the Keeal dashboard, so your site can update order status when payment completes or fails. Card data is handled by Keeal, not collected or stored by this plugin on your site.

**Legal links (Keeal)**

* [Terms of Service](https://keeal.com/terms)
* [Privacy Policy](https://keeal.com/privacy)

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

= 1.0.5 =
* Readme: add **External services** section (Keeal API usage, data sent, webhook flow, Terms and Privacy links) for WordPress.org guidelines.

= 1.0.4 =
* Fix payment settings: do not run gateway settings HTML through `wp_kses_post()` (it stripped password/API inputs). Documented PHPCS exception for WC-generated markup.

= 1.0.3 =
* Transactions list: `meta_key` order query with Plugin Check annotation; Plugin Check pass (ABSPATH, i18n, escaping, no redundant `load_plugin_textdomain`).

= 1.0.2 =
* Transactions list: use WooCommerce `meta_key` order query instead of `meta_query` (Plugin Check compatibility).

= 1.0.1 =
* Plugin Check: ABSPATH guards in all includes; fix text domain and escaping on gateway settings; remove redundant load_plugin_textdomain (WordPress.org loads translations); PHPCS annotations for safe GET usage.

= 1.0.0 =
* First public release: hosted checkout redirect, REST webhooks with signature verification, HPOS compatibility, Cart & Checkout Blocks payment registration.
