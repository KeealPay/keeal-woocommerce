<?php

declare(strict_types=1);

namespace Keeal\WooCommerce;

use Keeal\Checkout\CheckoutHelpers;
use Keeal\Checkout\KeealCheckout;
use Keeal\Checkout\KeealCheckoutException;
use WC_Order;
use WC_Payment_Gateway;

defined('ABSPATH') || exit;

final class Gateway extends WC_Payment_Gateway
{
    public const ID = 'keeal_hosted_checkout';

    private const ICON_RELATIVE = 'assets/images/keeal-icon.png';

    public static function icon_url(): string
    {
        return plugins_url(self::ICON_RELATIVE, KEEAL_WC_PLUGIN_FILE);
    }

    public function __construct()
    {
        $this->id = self::ID;
        $this->icon = self::icon_url();
        $this->has_fields = false;
        $this->method_title = __('Keeal Payment', 'keeal-for-woocommerce');
        $this->method_description = __(
            'Accept credit and debit cards, Apple Pay, Google Pay, PayPal, and more via Keeal’s hosted checkout. Customers complete payment on Keeal’s secure page.',
            'keeal-for-woocommerce'
        );
        $this->supports = ['products'];

        $this->init_form_fields();
        $this->init_settings();

        $this->title = (string) $this->get_option('title');
        $this->description = (string) $this->get_option('description');

        add_action(
            'woocommerce_update_options_payment_gateways_'.$this->id,
            [$this, 'process_admin_options']
        );
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
    }

    public function enqueue_admin_assets(string $hook_suffix): void
    {
        if ($hook_suffix !== 'woocommerce_page_wc-settings') {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab/section for WC settings screen (GET).
        $tab = isset($_GET['tab']) ? sanitize_text_field(wp_unslash($_GET['tab'])) : '';
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab/section for WC settings screen (GET).
        $section = isset($_GET['section']) ? sanitize_text_field(wp_unslash($_GET['section'])) : '';
        if ($tab !== 'checkout' || $section !== self::ID) {
            return;
        }

        wp_enqueue_style(
            'keeal-wc-gateway-admin',
            plugins_url('assets/css/admin-gateway.css', KEEAL_WC_PLUGIN_FILE),
            [],
            KEEAL_WC_VERSION
        );
    }

    public function admin_options(): void
    {
        $chips = [
            __('Cards', 'keeal-for-woocommerce'),
            __('Apple Pay', 'keeal-for-woocommerce'),
            __('Google Pay', 'keeal-for-woocommerce'),
            __('PayPal', 'keeal-for-woocommerce'),
            __('More', 'keeal-for-woocommerce'),
        ];
        $tagline = $this->get_method_description();
        ?>
        <div class="keeal-wc-gateway-admin" id="keeal-wc-gateway-settings">
            <p class="keeal-wc-back-wrap"><?php wc_back_link(__('Return to payments', 'keeal-for-woocommerce'), admin_url('admin.php?page=wc-settings&tab=checkout')); ?></p>
            <div class="keeal-wc-settings-hero">
                <img src="<?php echo esc_url(self::icon_url()); ?>" alt="" class="keeal-wc-settings-hero-icon" width="56" height="56" loading="lazy" decoding="async" />
                <div class="keeal-wc-settings-hero-body">
                    <h2 class="keeal-wc-settings-hero-title"><?php echo esc_html($this->get_method_title()); ?></h2>
                    <p class="keeal-wc-settings-hero-tagline"><?php echo esc_html($tagline); ?></p>
                    <ul class="keeal-wc-pay-methods" aria-label="<?php esc_attr_e('Payment methods available via Keeal', 'keeal-for-woocommerce'); ?>">
                        <?php foreach ($chips as $label) : ?>
                            <li><?php echo esc_html($label); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            <div class="keeal-wc-settings-card">
                <table class="form-table"><?php
                    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML from WC_Settings_API::generate_settings_html(); wp_kses_post() strips <input> and breaks API key / webhook fields.
                    echo $this->generate_settings_html($this->get_form_fields(), false);
                ?></table>
            </div>
        </div>
        <?php
    }

    public function init_form_fields(): void
    {
        $webhook_url = rest_url('keeal-wc/v1/webhook');
        $this->form_fields = [
            'enabled' => [
                'title' => __('Enable / Disable', 'keeal-for-woocommerce'),
                'type' => 'checkbox',
                'label' => __('Enable Keeal Payment', 'keeal-for-woocommerce'),
                'default' => 'no',
            ],
            'section_display' => [
                'title' => __('Checkout appearance', 'keeal-for-woocommerce'),
                'type' => 'title',
                'description' => __('Text shown to customers when they choose this payment method.', 'keeal-for-woocommerce'),
            ],
            'title' => [
                'title' => __('Title', 'keeal-for-woocommerce'),
                'type' => 'text',
                'description' => __('Payment method title shown at checkout.', 'keeal-for-woocommerce'),
                'default' => __('Keeal Payment', 'keeal-for-woocommerce'),
                'desc_tip' => true,
            ],
            'description' => [
                'title' => __('Description', 'keeal-for-woocommerce'),
                'type' => 'textarea',
                'description' => __('Payment method description shown at checkout.', 'keeal-for-woocommerce'),
                'default' => __(
                    'Pay with credit or debit card, Apple Pay, Google Pay, PayPal, and more—you’ll complete checkout securely on Keeal.',
                    'keeal-for-woocommerce'
                ),
            ],
            'section_api' => [
                'title' => __('API connection', 'keeal-for-woocommerce'),
                'type' => 'title',
                'description' => Api_Config::is_dev_mode()
                    ? __(
                        'DEV: set KEEAL_WC_DEV_MODE in wp-config.php to use a custom API base URL. Production stores use the fixed Keeal API endpoint.',
                        'keeal-for-woocommerce'
                    )
                    : sprintf(
                        /* translators: %s: production API base URL */
                        __('Connects to Keeal production at %s. Only your secret API key is required.', 'keeal-for-woocommerce'),
                        KEEAL_WC_PRODUCTION_API_BASE
                    ),
            ],
            'api_key' => [
                'title' => __('Keeal secret API key', 'keeal-for-woocommerce'),
                'type' => 'password',
                'description' => __('Your keeal_sk_… key from Keeal (server-side only — never expose in the browser).', 'keeal-for-woocommerce'),
            ],
        ];

        if (Api_Config::is_dev_mode()) {
            $this->form_fields['base_url'] = [
                'title' => __('Keeal API base URL (dev)', 'keeal-for-woocommerce'),
                'type' => 'text',
                'placeholder' => KEEAL_WC_PRODUCTION_API_BASE,
                'description' => __(
                    'Must include the /api path. Used only when KEEAL_WC_DEV_MODE is true (local or staging).',
                    'keeal-for-woocommerce'
                ),
                'desc_tip' => true,
            ];
        }

        $this->form_fields = array_merge($this->form_fields, [
            'section_webhooks' => [
                'title' => __('Webhooks', 'keeal-for-woocommerce'),
                'type' => 'title',
                'description' => __('Receive payment events from Keeal so orders update automatically.', 'keeal-for-woocommerce'),
            ],
            'webhook_secret' => [
                'title' => __('Webhook signing secret', 'keeal-for-woocommerce'),
                'type' => 'password',
                'description' => sprintf(
                    /* translators: %s: webhook URL */
                    __('Optional but strongly recommended: your whsec_… secret so this site can verify webhooks. Webhook URL: %s', 'keeal-for-woocommerce'),
                    $webhook_url
                ),
            ],
            'webhook_help' => [
                'title' => __('Webhook endpoint', 'keeal-for-woocommerce'),
                'type' => 'title',
                'description' => sprintf(
                    /* translators: %s: URL */
                    __('Paste this HTTPS URL into Keeal (API key → Webhook) for the same environment as the key above: %s', 'keeal-for-woocommerce'),
                    '<code>'.esc_html($webhook_url).'</code>'
                ),
            ],
        ]);
    }

    /**
     * @param  int|string  $order_id
     */
    public function process_payment($order_id): array
    {
        $order = wc_get_order((int) $order_id);
        if (! $order instanceof WC_Order) {
            wc_add_notice(__('Invalid order.', 'keeal-for-woocommerce'), 'error');

            return ['result' => 'failure', 'redirect' => ''];
        }

        $gateway_settings = is_array($this->settings) ? $this->settings : [];
        $api_key = trim((string) $this->get_option('api_key'));
        $base_url = Api_Config::effective_base_url($gateway_settings);

        if ($api_key === '') {
            wc_add_notice(
                __('Keeal is not configured (missing API key).', 'keeal-for-woocommerce'),
                'error'
            );

            return ['result' => 'failure', 'redirect' => ''];
        }

        if ($base_url === '') {
            wc_add_notice(
                __('Keeal dev mode: set a custom API base URL in payment settings (KEEAL_WC_DEV_MODE is enabled).', 'keeal-for-woocommerce'),
                'error'
            );

            return ['result' => 'failure', 'redirect' => ''];
        }

        $line_items = Order_Line_Builder::from_order($order);
        if ($line_items === []) {
            wc_add_notice(__('Your order has no payable line items.', 'keeal-for-woocommerce'), 'error');

            return ['result' => 'failure', 'redirect' => ''];
        }

        try {
            CheckoutHelpers::assertSingleCurrency($line_items);
        } catch (\InvalidArgumentException $e) {
            wc_add_notice(__('Checkout currency configuration error. Please contact the store.', 'keeal-for-woocommerce'), 'error');

            return ['result' => 'failure', 'redirect' => ''];
        }

        try {
            $client = new KeealCheckout([
                'apiKey' => $api_key,
                'baseUrl' => $base_url,
            ]);

            $metadata = [
                'order_key' => $order->get_order_key(),
                'site_url' => home_url('/'),
            ];

            $idempotency_key = 'woo_'.$order->get_id().'_'.substr(hash('sha256', $order->get_order_key()), 0, 32);

            $session = $client->createSession(
                [
                    'line_items' => $line_items,
                    'success_url' => $order->get_checkout_order_received_url(),
                    'cancel_url' => wc_get_checkout_url(),
                    'customer_email' => $order->get_billing_email(),
                    'client_reference_id' => (string) $order->get_id(),
                    'metadata' => $metadata,
                ],
                ['idempotencyKey' => $idempotency_key]
            );
        } catch (KeealCheckoutException $e) {
            wc_get_logger()->error(
                'Keeal session error: '.$e->getMessage(),
                ['source' => 'keeal-for-woocommerce']
            );
            wc_add_notice(
                __('Could not start payment. Please try again or contact us.', 'keeal-for-woocommerce'),
                'error'
            );

            return ['result' => 'failure', 'redirect' => ''];
        } catch (\Throwable $e) {
            wc_get_logger()->error(
                'Keeal session exception: '.$e->getMessage(),
                ['source' => 'keeal-for-woocommerce']
            );
            wc_add_notice(
                __('Could not start payment. Please try again.', 'keeal-for-woocommerce'),
                'error'
            );

            return ['result' => 'failure', 'redirect' => ''];
        }

        $session_id = isset($session['id']) && is_string($session['id']) ? $session['id'] : '';
        $session_url = isset($session['url']) && is_string($session['url']) ? trim($session['url']) : '';
        if ($session_id === '' || $session_url === '') {
            wc_get_logger()->error(
                'Keeal session response missing id or url.',
                ['source' => 'keeal-for-woocommerce']
            );
            wc_add_notice(
                __('Could not start payment. Please try again or contact us.', 'keeal-for-woocommerce'),
                'error'
            );

            return ['result' => 'failure', 'redirect' => ''];
        }

        $order->update_meta_data('_keeal_checkout_session_id', $session_id);
        $order->save();

        $order->update_status(
            'pending',
            __('Customer redirected to Keeal checkout.', 'keeal-for-woocommerce')
        );

        return [
            'result' => 'success',
            'redirect' => $session_url,
        ];
    }
}
