<?php

declare(strict_types=1);

namespace Keeal\WooCommerce;

use Automattic\WooCommerce\StoreApi\Exceptions\RouteException;
use Automattic\WooCommerce\StoreApi\Payments\PaymentContext;
use Automattic\WooCommerce\StoreApi\Payments\PaymentResult;
use Automattic\WooCommerce\StoreApi\Utilities\NoticeHandler;
use WC_Payment_Gateway;

/**
 * Checkout block / Store API payment processing.
 *
 * {@see PaymentContext::get_payment_method_instance()} only loads gateways from
 * {@see \WC_Payment_Gateways::get_available_payment_gateways()}. If Keeal is not in that
 * list during the request (timing, filters, cart context), core Legacy processing exits
 * without setting a payment status and the Place Order step fails. This handler resolves
 * the gateway from the full registered list when Keeal is the selected method, mirroring
 * {@see \Automattic\WooCommerce\StoreApi\Legacy::process_legacy_payment()}.
 */
final class StoreApi_Checkout
{
    public static function register(): void
    {
        if (! class_exists(PaymentContext::class)) {
            return;
        }

        add_action(
            'woocommerce_rest_checkout_process_payment_with_context',
            [self::class, 'process_payment'],
            10,
            2
        );
    }

    /**
     * @throws RouteException
     */
    public static function process_payment(PaymentContext $context, PaymentResult &$result): void
    {
        if ($context->payment_method !== Gateway::ID) {
            return;
        }

        if ($result->status !== '') {
            return;
        }

        if (! function_exists('WC') || ! WC()) {
            return;
        }

        $registered = WC()->payment_gateways()->payment_gateways();
        if (! isset($registered[Gateway::ID]) || ! $registered[Gateway::ID] instanceof WC_Payment_Gateway) {
            return;
        }

        /** @var WC_Payment_Gateway $gateway */
        $gateway = $registered[Gateway::ID];
        if ('yes' !== $gateway->enabled) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification
        $post_data = $_POST;

        wc_maybe_define_constant('WOOCOMMERCE_CHECKOUT', true);

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $_POST = $context->payment_data;

        $gateway->validate_fields();

        NoticeHandler::convert_notices_to_exceptions('woocommerce_rest_payment_error');

        $gateway_result = $gateway->process_payment($context->order->get_id());

        $_POST = $post_data;

        if (isset($gateway_result['result']) && 'failure' === $gateway_result['result']) {
            if (isset($gateway_result['message'])) {
                throw new RouteException(
                    'woocommerce_rest_payment_error',
                    esc_html(wp_strip_all_tags((string) $gateway_result['message'])),
                    400
                );
            }
            NoticeHandler::convert_notices_to_exceptions('woocommerce_rest_payment_error');
        }

        $result_status = $gateway_result['result'] ?? 'failure';
        $valid_status = ['success', 'failure', 'pending', 'error'];
        $result->set_status(in_array($result_status, $valid_status, true) ? $result_status : 'failure');

        wc_clear_notices();

        $result->set_payment_details(array_merge($result->payment_details, $gateway_result));
        $result->set_redirect_url($gateway_result['redirect'] ?? '');
    }
}
