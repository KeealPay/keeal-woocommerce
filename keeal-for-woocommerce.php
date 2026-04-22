<?php
/**
 * Plugin Name: Keeal for WooCommerce
 * Plugin URI: https://keeal.com
 * Description: Offer Keeal Payment in WooCommerce—customers complete checkout on Keeal’s secure hosted page. Enter your Keeal API key and webhook details; production uses https://api.keeal.com/api.
 * Version: 1.0.5
 * Author: Keeal
 * Text Domain: keeal-for-woocommerce
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Requires Plugins: woocommerce
 * WC requires at least: 8.2
 * WC tested up to: 9.4
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package KeealWooCommerce
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('KEEAL_WC_VERSION', '1.0.5');
define('KEEAL_WC_PLUGIN_FILE', __FILE__);
define('KEEAL_WC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KEEAL_WC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('KEEAL_WC_PLUGIN_BASENAME', plugin_basename(__FILE__));
define('KEEAL_WC_WOOCOMMERCE_MAIN', 'woocommerce/woocommerce.php');

if (! defined('KEEAL_WC_PRODUCTION_API_BASE')) {
    define('KEEAL_WC_PRODUCTION_API_BASE', 'https://api.keeal.com/api');
}

if (! is_readable(KEEAL_WC_PLUGIN_DIR.'vendor/autoload.php')) {
    add_action('admin_notices', static function (): void {
        if (! current_user_can('activate_plugins')) {
            return;
        }
        echo '<div class="notice notice-error"><p>';
        esc_html_e('Keeal for WooCommerce: run `composer install` in the plugin directory (vendor/autoload.php missing).', 'keeal-for-woocommerce');
        echo '</p></div>';
    });

    return;
}

require_once KEEAL_WC_PLUGIN_DIR.'vendor/autoload.php';

register_activation_hook(__FILE__, static function (): void {
    if (! function_exists('is_plugin_active')) {
        require_once ABSPATH.'wp-admin/includes/plugin.php';
    }

    $wc_file = WP_PLUGIN_DIR.'/'.KEEAL_WC_WOOCOMMERCE_MAIN;
    if (! file_exists($wc_file) || ! is_plugin_active(KEEAL_WC_WOOCOMMERCE_MAIN)) {
        deactivate_plugins(KEEAL_WC_PLUGIN_BASENAME);
        set_transient('keeal_wc_activation_blocked', 1, 60);
    }
});

/**
 * Install / dependency messaging (WooCommerce must be present and active).
 */
add_action('admin_notices', static function (): void {
    if (! current_user_can('activate_plugins')) {
        return;
    }

    if (get_transient('keeal_wc_activation_blocked')) {
        delete_transient('keeal_wc_activation_blocked');
        $install = admin_url('plugin-install.php?s=woocommerce&tab=search&type=term');
        $activate = admin_url('plugins.php');
        echo '<div class="notice notice-error"><p><strong>';
        esc_html_e('Keeal for WooCommerce was not activated.', 'keeal-for-woocommerce');
        echo '</strong> ';
        esc_html_e('Install and activate WooCommerce first, then activate Keeal again.', 'keeal-for-woocommerce');
        echo '</p><p>';
        echo '<a href="'.esc_url($install).'" class="button button-primary">'.esc_html__('Install WooCommerce', 'keeal-for-woocommerce').'</a> ';
        echo '<a href="'.esc_url($activate).'" class="button">'.esc_html__('Back to Plugins', 'keeal-for-woocommerce').'</a>';
        echo '</p></div>';

        return;
    }

    if (! function_exists('is_plugin_active')) {
        require_once ABSPATH.'wp-admin/includes/plugin.php';
    }

    if (! is_plugin_active(KEEAL_WC_PLUGIN_BASENAME)) {
        return;
    }

    if (file_exists(WP_PLUGIN_DIR.'/'.KEEAL_WC_WOOCOMMERCE_MAIN) && is_plugin_active(KEEAL_WC_WOOCOMMERCE_MAIN)) {
        return;
    }

    $install = admin_url('plugin-install.php?s=woocommerce&tab=search&type=term');
    echo '<div class="notice notice-error"><p><strong>';
    esc_html_e('Keeal for WooCommerce requires WooCommerce.', 'keeal-for-woocommerce');
    echo '</strong> ';
    esc_html_e('Install and activate the WooCommerce plugin to use Keeal checkout.', 'keeal-for-woocommerce');
    echo '</p><p><a href="'.esc_url($install).'" class="button button-primary">'.esc_html__('Install WooCommerce', 'keeal-for-woocommerce').'</a></p></div>';
}, 5);

add_filter('plugin_action_links_'.KEEAL_WC_PLUGIN_BASENAME, static function (array $links): array {
    if (! function_exists('is_plugin_active')) {
        require_once ABSPATH.'wp-admin/includes/plugin.php';
    }
    $wc_ok = file_exists(WP_PLUGIN_DIR.'/'.KEEAL_WC_WOOCOMMERCE_MAIN) && is_plugin_active(KEEAL_WC_WOOCOMMERCE_MAIN);
    if ($wc_ok) {
        return $links;
    }

    $url = admin_url('plugin-install.php?s=woocommerce&tab=search&type=term');
    array_unshift($links, '<a href="'.esc_url($url).'"><span class="dashicons dashicons-warning" style="vertical-align:text-bottom;font-size:18px"></span> '.esc_html__('Requires WooCommerce', 'keeal-for-woocommerce').'</a>');

    return $links;
}, 10, 1);

/*
 * Priority 2: WooCommerce Blocks may fire `woocommerce_blocks_loaded` on `plugins_loaded` 5–10.
 * Hooking Keeal at 20 misses registration and Checkout Block shows no payment methods.
 */
add_action('plugins_loaded', static function (): void {
    if (! class_exists('WooCommerce', false)) {
        return;
    }

    Keeal\WooCommerce\Plugin::instance();
}, 2);
