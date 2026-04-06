<?php

declare(strict_types=1);

namespace Keeal\WooCommerce;

final class Plugin
{
    private static ?self $instance = null;

    private ?Webhook_Controller $webhook = null;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    private function __construct()
    {
        add_action('before_woocommerce_init', [$this, 'declare_hpos_compatibility']);
        add_action('before_woocommerce_init', [$this, 'declare_cart_checkout_blocks_compatibility']);
        add_action('woocommerce_blocks_loaded', [$this, 'register_blocks_payment_method']);
        add_filter('woocommerce_payment_gateways', [$this, 'register_gateway']);
        StoreApi_Checkout::register();
        add_action('rest_api_init', [$this, 'register_rest']);
        add_action('init', [$this, 'load_textdomain']);
        add_action('woocommerce_thankyou', [$this, 'thankyou_pending_notice'], 10, 1);

        if (is_admin()) {
            Admin\Admin_Menu::register();
            Admin\Admin_Page::register();
        }
    }

    public function declare_hpos_compatibility(): void
    {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'custom_order_tables',
                KEEAL_WC_PLUGIN_FILE,
                true
            );
        }
    }

    public function declare_cart_checkout_blocks_compatibility(): void
    {
        if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'cart_checkout_blocks',
                KEEAL_WC_PLUGIN_FILE,
                true
            );
        }
    }

    public function register_blocks_payment_method(): void
    {
        if (! class_exists(\Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType::class)) {
            return;
        }

        $attach = static function (): void {
            add_action(
                'woocommerce_blocks_payment_method_type_registration',
                [Blocks\BlocksPaymentMethod::class, 'register'],
                5,
                1
            );
        };

        if (did_action('woocommerce_blocks_loaded')) {
            $attach();
        } else {
            add_action('woocommerce_blocks_loaded', $attach);
        }
    }

    /**
     * @param  array<string>  $methods
     * @return array<string>
     */
    public function register_gateway(array $methods): array
    {
        $methods[] = Gateway::class;

        return $methods;
    }

    public function register_rest(): void
    {
        $this->webhook ??= new Webhook_Controller();
        $this->webhook->register_routes();
    }

    public function load_textdomain(): void
    {
        load_plugin_textdomain(
            'keeal-for-woocommerce',
            false,
            dirname(plugin_basename(KEEAL_WC_PLUGIN_FILE)).'/languages'
        );
    }

    /**
     * If the customer lands on the thank-you page before the webhook runs, explain pending state.
     *
     * @param  mixed  $order_id
     */
    public function thankyou_pending_notice($order_id): void
    {
        $order_id = absint($order_id);
        if ($order_id <= 0) {
            return;
        }

        $order = wc_get_order($order_id);
        if (! $order instanceof \WC_Order) {
            return;
        }

        if ($order->get_payment_method() !== Gateway::ID) {
            return;
        }

        if (! $order->has_status(['pending', 'on-hold'])) {
            return;
        }

        if (! $order->get_meta('_keeal_checkout_session_id')) {
            return;
        }

        wc_print_notice(
            __('Your payment is still being confirmed. You will receive an email when the order is complete.', 'keeal-for-woocommerce'),
            'notice'
        );
    }
}
