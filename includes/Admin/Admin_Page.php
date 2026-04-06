<?php

declare(strict_types=1);

namespace Keeal\WooCommerce\Admin;

use Keeal\Checkout\KeealCheckoutException;
use Keeal\WooCommerce\Api_Config;
use Keeal\WooCommerce\Gateway;

defined('ABSPATH') || exit;

/**
 * Keeal → Overview: status + webhook URL + optional API test (Stripe-style lite panel).
 */
final class Admin_Page
{
    public static function register(): void
    {
        add_action('admin_init', [self::class, 'handle_test_connection']);
    }

    public static function handle_test_connection(): void
    {
        if (! isset($_POST['keeal_wc_test_api']) || ! is_string($_POST['keeal_wc_test_api'])) {
            return;
        }

        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        check_admin_referer('keeal_wc_test_api', 'keeal_wc_nonce');

        $settings = get_option('woocommerce_'.Gateway::ID.'_settings', []);
        if (! is_array($settings)) {
            $settings = [];
        }

        $api_key = isset($settings['api_key']) ? trim((string) $settings['api_key']) : '';

        if (! Api_Config::is_ready_for_client($settings, $api_key)) {
            add_settings_error(
                'keeal_wc',
                'keeal_missing',
                Api_Config::is_dev_mode()
                    ? __('Save your Keeal API key and dev base URL on the payment settings page first.', 'keeal-for-woocommerce')
                    : __('Save your Keeal API key on the payment settings page first.', 'keeal-for-woocommerce'),
                'error'
            );

            return;
        }

        try {
            $client = Admin_Client::from_settings();
            if ($client === null) {
                throw new \RuntimeException(__('Could not load API client.', 'keeal-for-woocommerce'));
            }
            $client->listMerchantSessions(['limit' => 1]);
            add_settings_error(
                'keeal_wc',
                'keeal_ok',
                __('Connection OK: merchant API responded successfully.', 'keeal-for-woocommerce'),
                'success'
            );
        } catch (KeealCheckoutException $e) {
            add_settings_error(
                'keeal_wc',
                'keeal_api',
                sprintf(
                    /* translators: 1: HTTP status or message */
                    __('API error: %s', 'keeal-for-woocommerce'),
                    esc_html($e->getMessage())
                ),
                'error'
            );
        } catch (\Throwable $e) {
            add_settings_error(
                'keeal_wc',
                'keeal_ex',
                sprintf(
                    /* translators: %s: error message */
                    __('Request failed: %s', 'keeal-for-woocommerce'),
                    esc_html($e->getMessage())
                ),
                'error'
            );
        }
    }

    public static function render_page(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = get_option('woocommerce_'.Gateway::ID.'_settings', []);
        if (! is_array($settings)) {
            $settings = [];
        }

        $api_key = isset($settings['api_key']) ? trim((string) $settings['api_key']) : '';
        $api_key_set = $api_key !== '';
        $whsec_set = isset($settings['webhook_secret']) && trim((string) $settings['webhook_secret']) !== '';
        $enabled = isset($settings['enabled']) && $settings['enabled'] === 'yes';
        $dev = Api_Config::is_dev_mode();
        $effective_base = Api_Config::effective_base_url($settings);
        $base_url_ok = Api_Config::is_ready_for_client($settings, $api_key);
        $webhook_url = rest_url('keeal-wc/v1/webhook');
        $gw_url = admin_url('admin.php?page=wc-settings&tab=checkout&section='.Gateway::ID);

        Admin_Assets::enqueue_ui();

        $tx_url = admin_url('admin.php?page=keeal');

        settings_errors('keeal_wc');
        ?>
        <div class="wrap woocommerce keeal-wc-admin-ui">
            <?php
            Admin_Layout::hero(
                __('Overview', 'keeal-for-woocommerce'),
                __('Hosted checkout status, webhook endpoint, and a quick connection check. Branding and API keys live under payment settings.', 'keeal-for-woocommerce'),
                [
                    ['text' => __('View transactions', 'keeal-for-woocommerce'), 'url' => $tx_url],
                    ['text' => __('Payment settings', 'keeal-for-woocommerce'), 'url' => $gw_url, 'primary' => true],
                ]
            );
            ?>

            <div class="keeal-wc-overview-grid">
                <div class="keeal-wc-stat-card">
                    <p class="keeal-wc-stat-label"><?php esc_html_e('Gateway', 'keeal-for-woocommerce'); ?></p>
                    <p class="keeal-wc-stat-value <?php echo $enabled ? 'keeal-wc-ok' : 'keeal-wc-warn'; ?>">
                        <?php echo $enabled ? esc_html__('Enabled', 'keeal-for-woocommerce') : esc_html__('Disabled', 'keeal-for-woocommerce'); ?>
                    </p>
                    <?php if (! $enabled) : ?>
                        <p class="keeal-wc-stat-meta"><a href="<?php echo esc_url($gw_url); ?>"><?php esc_html_e('Turn on in settings', 'keeal-for-woocommerce'); ?></a></p>
                    <?php endif; ?>
                </div>
                <div class="keeal-wc-stat-card">
                    <p class="keeal-wc-stat-label"><?php esc_html_e('API key', 'keeal-for-woocommerce'); ?></p>
                    <p class="keeal-wc-stat-value <?php echo $api_key_set ? 'keeal-wc-ok' : 'keeal-wc-bad'; ?>">
                        <?php echo $api_key_set ? esc_html__('Saved', 'keeal-for-woocommerce') : esc_html__('Missing', 'keeal-for-woocommerce'); ?>
                    </p>
                </div>
                <div class="keeal-wc-stat-card">
                    <p class="keeal-wc-stat-label"><?php esc_html_e('API endpoint', 'keeal-for-woocommerce'); ?></p>
                    <p class="keeal-wc-stat-value <?php echo $base_url_ok ? 'keeal-wc-ok' : 'keeal-wc-bad'; ?>">
                        <?php echo $base_url_ok ? esc_html($effective_base) : esc_html__('Not configured', 'keeal-for-woocommerce'); ?>
                    </p>
                    <?php if ($dev) : ?>
                        <p class="keeal-wc-stat-meta description"><?php esc_html_e('Dev mode: custom base URL from settings.', 'keeal-for-woocommerce'); ?></p>
                    <?php endif; ?>
                </div>
                <div class="keeal-wc-stat-card">
                    <p class="keeal-wc-stat-label"><?php esc_html_e('Webhook secret', 'keeal-for-woocommerce'); ?></p>
                    <p class="keeal-wc-stat-value <?php echo $whsec_set ? 'keeal-wc-ok' : 'keeal-wc-warn'; ?>">
                        <?php echo $whsec_set ? esc_html__('Configured', 'keeal-for-woocommerce') : esc_html__('Recommended', 'keeal-for-woocommerce'); ?>
                    </p>
                    <?php if (! $whsec_set) : ?>
                        <p class="keeal-wc-stat-meta description"><?php esc_html_e('Without it, signing verification fails.', 'keeal-for-woocommerce'); ?></p>
                    <?php endif; ?>
                </div>
                <div class="keeal-wc-stat-card">
                    <p class="keeal-wc-stat-label"><?php esc_html_e('Environment', 'keeal-for-woocommerce'); ?></p>
                    <p class="keeal-wc-stat-value <?php echo $dev ? 'keeal-wc-warn' : 'keeal-wc-ok'; ?>">
                        <?php echo $dev ? esc_html__('Development (KEEAL_WC_DEV_MODE)', 'keeal-for-woocommerce') : esc_html__('Production', 'keeal-for-woocommerce'); ?>
                    </p>
                </div>
            </div>

            <div class="keeal-wc-panel" style="max-width: 880px;">
                <h2 class="keeal-wc-panel-h"><?php esc_html_e('Webhook URL', 'keeal-for-woocommerce'); ?></h2>
                <div style="padding: 20px 24px 24px;">
                    <p class="description" style="margin-top:0;">
                        <?php esc_html_e('Add this URL in your Keeal dashboard. Production sites need a public HTTPS endpoint.', 'keeal-for-woocommerce'); ?>
                    </p>
                    <div class="keeal-wc-webhook-field">
                        <input type="text" readonly class="large-text code" value="<?php echo esc_attr($webhook_url); ?>" onclick="this.select();" aria-label="<?php esc_attr_e('Webhook URL', 'keeal-for-woocommerce'); ?>" />
                    </div>
                    <p class="description">
                        <?php esc_html_e('Tip: for local development, use ngrok, Cloudflare Tunnel, or similar so Keeal can reach your site.', 'keeal-for-woocommerce'); ?>
                    </p>
                </div>
            </div>

            <div class="keeal-wc-panel" style="max-width: 880px;">
                <h2 class="keeal-wc-panel-h"><?php esc_html_e('Test API connection', 'keeal-for-woocommerce'); ?></h2>
                <div style="padding: 20px 24px 24px;">
                    <p class="description" style="margin-top:0;">
                        <?php esc_html_e('Safely calls the merchant list endpoint with limit=1 using your saved credentials.', 'keeal-for-woocommerce'); ?>
                    </p>
                    <form method="post">
                        <?php wp_nonce_field('keeal_wc_test_api', 'keeal_wc_nonce'); ?>
                        <input type="hidden" name="keeal_wc_test_api" value="1" />
                        <button type="submit" class="button button-secondary"><?php esc_html_e('Ping Keeal API', 'keeal-for-woocommerce'); ?></button>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}
