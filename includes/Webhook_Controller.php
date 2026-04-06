<?php

declare(strict_types=1);

namespace Keeal\WooCommerce;

use Keeal\Checkout\WebhookVerifier;
use WC_Order;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

final class Webhook_Controller
{
    public function register_routes(): void
    {
        register_rest_route(
            'keeal-wc/v1',
            '/webhook',
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'handle_webhook'],
                'permission_callback' => '__return_true',
            ]
        );
    }

    public function handle_webhook(WP_REST_Request $request): WP_REST_Response
    {
        $settings = get_option('woocommerce_'.Gateway::ID.'_settings', []);
        if (! is_array($settings)) {
            $settings = [];
        }

        $secret = isset($settings['webhook_secret']) ? trim((string) $settings['webhook_secret']) : '';
        if ($secret === '') {
            return new WP_REST_Response(
                ['error' => 'webhook_not_configured'],
                503
            );
        }

        $signature = $request->get_header('X-Keeal-Signature');
        if (! is_string($signature) || $signature === '') {
            return new WP_REST_Response(['error' => 'missing_signature'], 401);
        }

        $raw = $request->get_body();
        if ($raw === '') {
            return new WP_REST_Response(['error' => 'empty_body'], 400);
        }

        if (! WebhookVerifier::verify($raw, $signature, $secret)) {
            return new WP_REST_Response(['error' => 'invalid_signature'], 401);
        }

        try {
            $payload = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return new WP_REST_Response(['error' => 'invalid_json'], 400);
        }

        if (! is_array($payload)) {
            return new WP_REST_Response(['error' => 'invalid_payload'], 400);
        }

        $type = isset($payload['type']) ? (string) $payload['type'] : '';
        $event_id = isset($payload['id']) ? (string) $payload['id'] : '';

        $object = [];
        if (isset($payload['data']['object']) && is_array($payload['data']['object'])) {
            $object = $payload['data']['object'];
        }

        $cref = isset($object['client_reference_id']) ? trim((string) $object['client_reference_id']) : '';
        $order_id = ($cref !== '' && ctype_digit($cref)) ? (int) $cref : 0;

        if ($order_id <= 0) {
            return new WP_REST_Response(['error' => 'missing_client_reference_id'], 400);
        }

        $order = wc_get_order($order_id);
        if (! $order instanceof WC_Order) {
            return new WP_REST_Response(['error' => 'order_not_found'], 404);
        }

        if ($order->get_payment_method() !== Gateway::ID) {
            return new WP_REST_Response(['error' => 'payment_method_mismatch'], 400);
        }

        if ($event_id === '' && in_array($type, ['checkout.session.completed', 'checkout.session.payment_failed', 'checkout.session.canceled'], true)) {
            return new WP_REST_Response(['error' => 'missing_event_id'], 400);
        }

        if ($this->already_processed($order, $event_id)) {
            return new WP_REST_Response(['received' => true, 'duplicate' => true], 200);
        }

        $session_id = isset($object['session_id']) ? (string) $object['session_id'] : '';
        $stored_session = (string) $order->get_meta('_keeal_checkout_session_id');
        if ($session_id !== '' && $stored_session !== '' && $session_id !== $stored_session) {
            return new WP_REST_Response(['error' => 'session_mismatch'], 400);
        }

        switch ($type) {
            case 'checkout.session.completed':
                $this->mark_paid($order, $object, $event_id);

                break;
            case 'checkout.session.payment_failed':
                $this->mark_failed($order, $object, $event_id);

                break;
            case 'checkout.session.canceled':
                $this->mark_cancelled($order, $event_id);

                break;
            default:
                if ($event_id !== '') {
                    $this->record_event_id($order, $event_id);
                }

                break;
        }

        return new WP_REST_Response(['received' => true], 200);
    }

    private function already_processed(WC_Order $order, string $event_id): bool
    {
        if ($event_id === '') {
            return false;
        }

        $ids = $order->get_meta('_keeal_webhook_event_ids', true);
        if (! is_array($ids)) {
            return false;
        }

        return in_array($event_id, $ids, true);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function mark_paid(WC_Order $order, array $object, string $event_id): void
    {
        $transaction_id = isset($object['transaction_id']) ? trim((string) $object['transaction_id']) : '';
        if ($transaction_id === '' && isset($object['payment_id'])) {
            $transaction_id = trim((string) $object['payment_id']);
        }

        $note_parts = [__('Keeal: payment completed.', 'keeal-for-woocommerce')];
        if ($transaction_id !== '') {
            $note_parts[] = 'transaction_id: '.$transaction_id;
        }
        if (isset($object['payment_status'])) {
            $note_parts[] = 'payment_status: '.(string) $object['payment_status'];
        }
        $order->add_order_note(implode(' ', $note_parts));

        if (! $order->is_paid()) {
            $order->payment_complete($transaction_id);
        }

        if ($event_id !== '') {
            $this->record_event_id($order, $event_id);
        }

        $order->save();
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function mark_failed(WC_Order $order, array $object, string $event_id): void
    {
        $msg = isset($object['failure_message']) ? (string) $object['failure_message'] : '';
        $order->add_order_note(
            __('Keeal: payment failed.', 'keeal-for-woocommerce').($msg !== '' ? ' '.$msg : '')
        );

        if ($order->has_status(['pending', 'on-hold', 'failed'])) {
            $order->update_status('failed');
        }

        if ($event_id !== '') {
            $this->record_event_id($order, $event_id);
        }
    }

    private function mark_cancelled(WC_Order $order, string $event_id): void
    {
        $order->add_order_note(__('Keeal: checkout session canceled.', 'keeal-for-woocommerce'));

        if ($order->has_status(['pending', 'on-hold'])) {
            $order->update_status('cancelled');
        }

        if ($event_id !== '') {
            $this->record_event_id($order, $event_id);
        }
    }

    private function record_event_id(WC_Order $order, string $event_id): void
    {
        $ids = $order->get_meta('_keeal_webhook_event_ids', true);
        if (! is_array($ids)) {
            $ids = [];
        }
        $ids[] = $event_id;
        $order->update_meta_data('_keeal_webhook_event_ids', array_values(array_unique($ids)));
        $order->save();
    }
}
