<?php

declare(strict_types=1);

namespace Keeal\WooCommerce\Admin;

use Keeal\Checkout\KeealCheckout;
use Keeal\WooCommerce\Api_Config;
use Keeal\WooCommerce\Gateway;

defined('ABSPATH') || exit;

/**
 * Build Keeal API client from saved gateway settings (same credentials as checkout).
 */
final class Admin_Client
{
    public static function from_settings(): ?KeealCheckout
    {
        $settings = get_option('woocommerce_'.Gateway::ID.'_settings', []);
        if (! is_array($settings)) {
            $settings = [];
        }

        $api_key = isset($settings['api_key']) ? trim((string) $settings['api_key']) : '';
        $base_url = Api_Config::effective_base_url($settings);

        if (! Api_Config::is_ready_for_client($settings, $api_key)) {
            return null;
        }

        return new KeealCheckout([
            'apiKey' => $api_key,
            'baseUrl' => $base_url,
        ]);
    }
}
