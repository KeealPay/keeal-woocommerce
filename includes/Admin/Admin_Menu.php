<?php

declare(strict_types=1);

namespace Keeal\WooCommerce\Admin;

/**
 * Top-level Woo admin menu: Keeal → Transactions, Overview.
 */
final class Admin_Menu
{
    public static function register(): void
    {
        add_action('admin_menu', [self::class, 'add_menus'], 56);
    }

    public static function add_menus(): void
    {
        add_menu_page(
            __('Keeal', 'keeal-for-woocommerce'),
            __('Keeal', 'keeal-for-woocommerce'),
            'manage_woocommerce',
            'keeal',
            [Transactions_Page::class, 'render'],
            'dashicons-money-alt',
            56
        );

        add_submenu_page(
            'keeal',
            __('Transactions', 'keeal-for-woocommerce'),
            __('Transactions', 'keeal-for-woocommerce'),
            'manage_woocommerce',
            'keeal',
            [Transactions_Page::class, 'render']
        );

        add_submenu_page(
            'keeal',
            __('Overview', 'keeal-for-woocommerce'),
            __('Overview', 'keeal-for-woocommerce'),
            'manage_woocommerce',
            'keeal-overview',
            [Admin_Page::class, 'render_page']
        );
    }
}
