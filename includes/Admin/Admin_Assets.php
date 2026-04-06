<?php

declare(strict_types=1);

namespace Keeal\WooCommerce\Admin;

defined('ABSPATH') || exit;

final class Admin_Assets
{
    public static function enqueue_ui(): void
    {
        wp_enqueue_style(
            'keeal-wc-admin-ui',
            plugins_url('assets/css/admin-ui.css', KEEAL_WC_PLUGIN_FILE),
            [],
            KEEAL_WC_VERSION
        );
    }
}
