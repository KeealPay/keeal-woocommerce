<?php

declare(strict_types=1);

namespace Keeal\WooCommerce\Blocks;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;
use Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry;
use Keeal\WooCommerce\Gateway;
use WC_Payment_Gateway;

/**
 * Registers Keeal with WooCommerce Cart & Checkout Blocks.
 */
class BlocksPaymentMethod extends AbstractPaymentMethodType
{
    /**
     * @var array<string, mixed>
     */
    protected $settings = [];

    public function __construct()
    {
        $this->name = Gateway::ID;
    }

    public function initialize(): void
    {
        $this->settings = get_option('woocommerce_'.Gateway::ID.'_settings', []);
        if (! is_array($this->settings)) {
            $this->settings = [];
        }

        $asset_file = KEEAL_WC_PLUGIN_DIR.'assets/js/keeal-blocks.asset.php';
        $asset = is_readable($asset_file) ? require $asset_file : [];
        $dependencies = isset($asset['dependencies']) && is_array($asset['dependencies'])
            ? $asset['dependencies']
            : ['wc-blocks-registry', 'wc-settings', 'wp-element'];

        wp_register_script(
            'keeal-wc-blocks-integration',
            plugins_url('assets/js/keeal-blocks.js', KEEAL_WC_PLUGIN_FILE),
            $dependencies,
            KEEAL_WC_VERSION,
            true
        );
    }

    public static function register(PaymentMethodRegistry $payment_method_registry): void
    {
        $payment_method_registry->register(new self());
    }

    public function is_active(): bool
    {
        if (! function_exists('WC')) {
            return false;
        }

        $wc = WC();
        if (! $wc) {
            return false;
        }

        // WC uses magic __get for payment_gateways — isset() is always false; call payment_gateways().
        $gateways = $wc->payment_gateways()->payment_gateways();
        if (! isset($gateways[Gateway::ID]) || ! $gateways[Gateway::ID] instanceof WC_Payment_Gateway) {
            return false;
        }

        $gateway = $gateways[Gateway::ID];

        /*
         * Do not use is_available() here: it can be false during Blocks / Store API bootstrap
         * (session/cart timing). Classic checkout still uses get_available_payment_gateways().
         */
        return 'yes' === $gateway->enabled;
    }

    public function get_payment_method_script_handles(): array
    {
        return ['keeal-wc-blocks-integration'];
    }

    public function get_payment_method_data(): array
    {
        if (! function_exists('WC')) {
            return [];
        }

        $gateways = WC()->payment_gateways->payment_gateways();
        if (! isset($gateways[Gateway::ID]) || ! $gateways[Gateway::ID] instanceof WC_Payment_Gateway) {
            return [];
        }

        /** @var WC_Payment_Gateway $gateway */
        $gateway = $gateways[Gateway::ID];

        $features = array_values(array_unique(array_merge(
            $this->get_supported_features(),
            is_array($gateway->supports) ? $gateway->supports : []
        )));

        return [
            'title' => $gateway->get_title(),
            'description' => $gateway->get_description(),
            'iconSrc' => Gateway::icon_url(),
            'supports' => $features,
        ];
    }
}
