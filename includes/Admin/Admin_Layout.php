<?php

declare(strict_types=1);

namespace Keeal\WooCommerce\Admin;

use Keeal\WooCommerce\Gateway;

final class Admin_Layout
{
    /**
     * @param  list<array{text: string, url: string, primary?: bool}>  $actions
     */
    public static function hero(string $title, string $subtitle, array $actions = []): void
    {
        $icon = Gateway::icon_url();
        ?>
        <div class="keeal-wc-hero">
            <div class="keeal-wc-hero-inner">
                <img src="<?php echo esc_url($icon); ?>" alt="" class="keeal-wc-hero-icon" width="56" height="56" loading="lazy" decoding="async" />
                <div class="keeal-wc-hero-text">
                    <p class="keeal-wc-hero-kicker"><?php esc_html_e('Keeal Payment', 'keeal-for-woocommerce'); ?></p>
                    <h1 class="keeal-wc-hero-title"><?php echo esc_html($title); ?></h1>
                    <p class="keeal-wc-hero-sub"><?php echo esc_html($subtitle); ?></p>
                </div>
                <?php if ($actions !== []) : ?>
                    <div class="keeal-wc-hero-actions">
                        <?php foreach ($actions as $a) : ?>
                            <?php
                            $primary = ! empty($a['primary']);
                            $cls = $primary ? 'button button-primary' : 'button';
                            ?>
                            <a href="<?php echo esc_url($a['url']); ?>" class="<?php echo esc_attr($cls); ?>"><?php echo esc_html($a['text']); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
