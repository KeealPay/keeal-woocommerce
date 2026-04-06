<?php

declare(strict_types=1);

namespace Keeal\WooCommerce;

use WC_Order;
use WC_Order_Item_Fee;
use WC_Order_Item_Product;
use WC_Order_Item_Shipping;

final class Order_Line_Builder
{
    /**
     * Build Keeal `line_items` from a WooCommerce order (single currency).
     *
     * @return list<array<string, mixed>>
     */
    public static function from_order(WC_Order $order): array
    {
        $items = [];

        foreach ($order->get_items('line_item') as $line) {
            if (! $line instanceof WC_Order_Item_Product) {
                continue;
            }

            $qty = max(1, (int) $line->get_quantity());
            $line_gross = (float) $line->get_total() + (float) $line->get_total_tax();
            $unit_major = $line_gross / $qty;

            $items[] = [
                'price_data' => [
                    'currency' => strtolower($order->get_currency()),
                    'product_data' => [
                        'name' => self::truncate_name(wp_strip_all_tags($line->get_name())),
                    ],
                    'unit_amount' => self::major_to_minor($unit_major, $order->get_currency()),
                ],
                'quantity' => $qty,
            ];
        }

        foreach ($order->get_items('shipping') as $ship) {
            if (! $ship instanceof WC_Order_Item_Shipping) {
                continue;
            }

            $total = (float) $ship->get_total() + (float) $ship->get_total_tax();
            if (abs($total) < 0.00001) {
                continue;
            }

            $label = $ship->get_name() ?: __('Shipping', 'keeal-for-woocommerce');
            $items[] = [
                'price_data' => [
                    'currency' => strtolower($order->get_currency()),
                    'product_data' => [
                        'name' => self::truncate_name(wp_strip_all_tags($label)),
                    ],
                    'unit_amount' => self::major_to_minor($total, $order->get_currency()),
                ],
                'quantity' => 1,
            ];
        }

        foreach ($order->get_items('fee') as $fee) {
            if (! $fee instanceof WC_Order_Item_Fee) {
                continue;
            }

            $total = (float) $fee->get_total() + (float) $fee->get_total_tax();
            if (abs($total) < 0.00001) {
                continue;
            }

            $name = $fee->get_name() ?: __('Fee', 'keeal-for-woocommerce');
            $items[] = [
                'price_data' => [
                    'currency' => strtolower($order->get_currency()),
                    'product_data' => [
                        'name' => self::truncate_name(wp_strip_all_tags($name)),
                    ],
                    'unit_amount' => self::major_to_minor($total, $order->get_currency()),
                ],
                'quantity' => 1,
            ];
        }

        return $items;
    }

    /**
     * Convert store-major units to minor units (cents) using WooCommerce decimal setting.
     */
    public static function major_to_minor(float $amount, string $currency_code): int
    {
        unset($currency_code);

        $decimals = (int) wc_get_price_decimals();
        $factor = 10 ** $decimals;
        $normalized = (float) wc_format_decimal((string) $amount, $decimals);

        return (int) round($normalized * $factor);
    }

    private static function truncate_name(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return __('Order item', 'keeal-for-woocommerce');
        }

        return mb_substr($name, 0, 500);
    }
}
