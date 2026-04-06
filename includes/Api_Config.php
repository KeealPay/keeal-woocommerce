<?php

declare(strict_types=1);

namespace Keeal\WooCommerce;

defined('ABSPATH') || exit;

/**
 * Production API base URL is fixed. Custom base URLs are only used when
 * {@see KEEAL_WC_DEV_MODE} is true (define in wp-config.php for local/staging).
 */
final class Api_Config
{
    public static function is_dev_mode(): bool
    {
        return defined('KEEAL_WC_DEV_MODE') && KEEAL_WC_DEV_MODE;
    }

    /**
     * @param  array<string, mixed>  $gateway_settings
     */
    public static function effective_base_url(array $gateway_settings): string
    {
        if (! self::is_dev_mode()) {
            return KEEAL_WC_PRODUCTION_API_BASE;
        }

        return isset($gateway_settings['base_url']) ? trim((string) $gateway_settings['base_url']) : '';
    }

    /**
     * @param  array<string, mixed>  $gateway_settings
     */
    public static function is_ready_for_client(array $gateway_settings, string $api_key): bool
    {
        if (trim($api_key) === '') {
            return false;
        }

        return self::effective_base_url($gateway_settings) !== '';
    }
}
