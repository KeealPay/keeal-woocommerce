<?php

declare(strict_types=1);

namespace Keeal\WooCommerce\Admin;

use Keeal\Checkout\KeealCheckoutException;
use Keeal\WooCommerce\Gateway;
use WC_Order;

defined('ABSPATH') || exit;

/**
 * Lists Keeal checkout sessions (merchant API) and shows session detail.
 */
final class Transactions_Page
{
    private const PER_PAGE = 25;

    public static function render(): void
    {
        if (! current_user_can('manage_woocommerce')) {
            return;
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Admin list/detail navigation (GET).
        $session_id = isset($_GET['session_id']) ? sanitize_text_field(wp_unslash($_GET['session_id'])) : '';
        if ($session_id !== '') {
            self::render_detail($session_id);

            return;
        }

        self::render_list();
    }

    private static function render_list(): void
    {
        $client = Admin_Client::from_settings();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- List pagination (GET).
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $gw_url = admin_url('admin.php?page=wc-settings&tab=checkout&section='.Gateway::ID);
        $overview_url = admin_url('admin.php?page=keeal-overview');
        $tx_url = admin_url('admin.php?page=keeal');

        Admin_Assets::enqueue_ui();

        echo '<div class="wrap keeal-wc-transactions keeal-wc-admin-ui">';

        Admin_Layout::hero(
            __('Transactions', 'keeal-for-woocommerce'),
            __('Review checkout sessions from Keeal and orders placed through your store.', 'keeal-for-woocommerce'),
            [
                ['text' => __('Overview', 'keeal-for-woocommerce'), 'url' => $overview_url],
                ['text' => __('Payment settings', 'keeal-for-woocommerce'), 'url' => $gw_url, 'primary' => true],
            ]
        );

        if ($client === null) {
            echo '<div class="notice notice-warning"><p>';
            esc_html_e('Add your Keeal API key in payment settings to load transactions.', 'keeal-for-woocommerce');
            echo ' <a href="'.esc_url($gw_url).'">'.esc_html__('Open settings', 'keeal-for-woocommerce').'</a>';
            echo '</p></div></div>';

            return;
        }

        try {
            $result = $client->listMerchantSessions([
                'limit' => self::PER_PAGE,
                'page' => $paged,
            ]);
        } catch (KeealCheckoutException $e) {
            echo '<div class="notice notice-error"><p>'.esc_html($e->getMessage()).'</p></div></div>';

            return;
        } catch (\Throwable $e) {
            echo '<div class="notice notice-error"><p>'.esc_html($e->getMessage()).'</p></div></div>';

            return;
        }

        $rows = self::extract_list_rows($result);
        $has_more = self::extract_has_more($result, count($rows), self::PER_PAGE);
        $page = isset($result['page']) ? (int) $result['page'] : (isset($result['Page']) ? (int) $result['Page'] : $paged);

        $list_source = 'api';
        if ($rows === []) {
            [$rows, $has_more] = self::rows_from_woocommerce_kept_sessions($paged, self::PER_PAGE);
            $list_source = 'woocommerce';
            if ($rows !== []) {
                echo '<div class="notice notice-info"><p>';
                esc_html_e('The Keeal merchant list API returned no rows for this page (empty account, different response shape, or paging). Showing WooCommerce orders that used Keeal and have a stored session id.', 'keeal-for-woocommerce');
                echo '</p></div>';
            } elseif (defined('WP_DEBUG') && WP_DEBUG && function_exists('wc_get_logger')) {
                wc_get_logger()->debug(
                    'Keeal transactions: API list empty after parse. Top-level keys: '.implode(', ', array_keys($result)),
                    ['source' => 'keeal-for-woocommerce']
                );
            }
        }

        $source_label = $list_source === 'woocommerce'
            ? __('WooCommerce orders', 'keeal-for-woocommerce')
            : __('Keeal merchant API', 'keeal-for-woocommerce');

        $desc = $list_source === 'woocommerce'
            ? __('Orders from this store that were placed with Keeal Payment (session id saved on the order).', 'keeal-for-woocommerce')
            : __('Checkout sessions from your Keeal account (newest first). Amounts are from the API; WooCommerce orders may differ until the webhook completes.', 'keeal-for-woocommerce');

        ?>
        <div class="keeal-wc-toolbar">
            <p class="description" style="margin:0; max-width: 42em;"><?php echo esc_html($desc); ?></p>
            <div style="display:flex; flex-wrap:wrap; gap:10px; align-items:center;">
                <span class="keeal-wc-chip">
                    <strong><?php echo esc_html((string) count($rows)); ?></strong>
                    <?php esc_html_e('on this page', 'keeal-for-woocommerce'); ?>
                </span>
                <span class="keeal-wc-chip"><?php echo esc_html($source_label); ?></span>
            </div>
        </div>

        <div class="keeal-wc-panel">
            <h2 class="keeal-wc-panel-h"><?php esc_html_e('Session activity', 'keeal-for-woocommerce'); ?></h2>
            <table class="wp-list-table widefat fixed striped table-view-list keeal-wc-tx-table">
                <thead>
                    <tr>
                        <th scope="col" class="column-date"><?php esc_html_e('Created', 'keeal-for-woocommerce'); ?></th>
                        <th scope="col" class="column-session"><?php esc_html_e('Session', 'keeal-for-woocommerce'); ?></th>
                        <th scope="col"><?php esc_html_e('Status', 'keeal-for-woocommerce'); ?></th>
                        <th scope="col"><?php esc_html_e('Amount', 'keeal-for-woocommerce'); ?></th>
                        <th scope="col"><?php esc_html_e('Customer', 'keeal-for-woocommerce'); ?></th>
                        <th scope="col"><?php esc_html_e('Order ref.', 'keeal-for-woocommerce'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if ($rows === []) {
                    echo '<tr><td colspan="6" class="keeal-wc-empty-cell">';
                    esc_html_e('No sessions or Keeal orders on this page. If you already completed checkouts, confirm the API key matches the Keeal environment that created those sessions, or check WooCommerce → Status → Logs (source: keeal-for-woocommerce) when WP_DEBUG is on.', 'keeal-for-woocommerce');
                    echo '</td></tr>';
                } else {
                    foreach ($rows as $row) {
                        if (! is_array($row)) {
                            continue;
                        }
                        $created = self::str_field($row, ['createdAt', 'created_at']);
                        $sess = self::str_field($row, ['sessionId', 'session_id', 'id']);
                        $status = self::str_field($row, ['status']);
                        $cents = self::int_field($row, ['amountCents', 'amount_cents']);
                        $currency = self::str_field($row, ['currency']);
                        $email = self::str_field($row, ['customerEmail', 'customer_email']);
                        $cref = self::str_field($row, ['clientReferenceId', 'client_reference_id']);

                        $detail_url = add_query_arg(
                            [
                                'page' => 'keeal',
                                'session_id' => $sess,
                            ],
                            admin_url('admin.php')
                        );
                        $status_slug = $status !== '' ? sanitize_html_class(strtolower($status)) : 'unknown';
                        ?>
                        <tr>
                            <td class="column-date"><?php echo esc_html(self::format_datetime($created)); ?></td>
                            <td class="column-session"><a href="<?php echo esc_url($detail_url); ?>"><code><?php echo esc_html($sess !== '' ? $sess : '—'); ?></code></a></td>
                            <td><span class="keeal-wc-status keeal-wc-status-<?php echo esc_attr($status_slug); ?>"><?php echo esc_html($status !== '' ? $status : '—'); ?></span></td>
                            <td class="keeal-wc-amount"><?php echo wp_kses_post(self::format_money($cents, $currency)); ?></td>
                            <td><?php echo esc_html($email !== '' ? $email : '—'); ?></td>
                            <td><?php echo wp_kses_post(self::order_ref_cell($cref)); ?></td>
                        </tr>
                        <?php
                    }
                }
                ?>
                </tbody>
            </table>
        </div>

        <div class="keeal-wc-pagination">
            <?php if ($paged > 1) : ?>
                <a class="button" href="<?php echo esc_url(add_query_arg('paged', $paged - 1, $tx_url)); ?>"><?php esc_html_e('← Previous', 'keeal-for-woocommerce'); ?></a>
            <?php endif; ?>
            <span class="keeal-wc-page-label"><?php echo esc_html(sprintf(/* translators: %d: page number */ __('Page %d', 'keeal-for-woocommerce'), $page)); ?></span>
            <?php if ($has_more) : ?>
                <a class="button button-primary" href="<?php echo esc_url(add_query_arg('paged', $paged + 1, $tx_url)); ?>"><?php esc_html_e('Next →', 'keeal-for-woocommerce'); ?></a>
            <?php endif; ?>
        </div>
        <?php

        echo '</div>';
    }

    /**
     * @param  array<string, mixed>  $result  Decoded JSON from list merchant sessions
     * @return list<array<string, mixed>>
     */
    private static function extract_list_rows(array $result): array
    {
        $keys = ['data', 'sessions', 'items', 'results', 'records', 'checkoutSessions', 'checkout_sessions'];
        foreach ($keys as $key) {
            if (! isset($result[$key]) || ! is_array($result[$key])) {
                continue;
            }
            $list = $result[$key];
            if ($list === []) {
                return [];
            }
            $first = reset($list);
            if (! is_array($first)) {
                continue;
            }

            return array_values($list);
        }

        if ($result !== [] && function_exists('array_is_list') && array_is_list($result)) {
            $first = reset($result);
            if (is_array($first)) {
                return $result;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private static function extract_has_more(array $result, int $row_count, int $limit): bool
    {
        if (! empty($result['has_more']) || ! empty($result['hasMore'])) {
            return true;
        }
        if (array_key_exists('has_more', $result) && $result['has_more'] === false) {
            return false;
        }
        if (array_key_exists('hasMore', $result) && $result['hasMore'] === false) {
            return false;
        }

        return $row_count >= $limit;
    }

    /**
     * @return array{0: list<array<string, mixed>>, 1: bool}
     */
    private static function rows_from_woocommerce_kept_sessions(int $page, int $limit): array
    {
        if (! function_exists('wc_get_orders')) {
            return [[], false];
        }

        $offset = max(0, ($page - 1) * $limit);
        $orders = wc_get_orders([
            'limit' => $limit + 1,
            'offset' => $offset,
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_key' => '_keeal_checkout_session_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Admin Keeal transactions; paginated; filter required.
        ]);

        $has_more = count($orders) > $limit;
        if ($has_more) {
            $orders = array_slice($orders, 0, $limit);
        }

        $rows = [];
        foreach ($orders as $order) {
            if (! $order instanceof WC_Order) {
                continue;
            }
            $sid = (string) $order->get_meta('_keeal_checkout_session_id');
            if ($sid === '') {
                continue;
            }
            $created = $order->get_date_created();
            $rows[] = [
                'createdAt' => $created ? $created->format('c') : '',
                'sessionId' => $sid,
                'status' => $order->get_status(),
                'amountCents' => (int) round((float) $order->get_total() * (10 ** (int) wc_get_price_decimals())),
                'currency' => $order->get_currency(),
                'customerEmail' => $order->get_billing_email(),
                'clientReferenceId' => (string) $order->get_id(),
                '_keeal_row_source' => 'woocommerce',
            ];
        }

        return [$rows, $has_more];
    }

    private static function render_detail(string $session_id): void
    {
        $client = Admin_Client::from_settings();
        $list_url = admin_url('admin.php?page=keeal');
        $gw_url = admin_url('admin.php?page=wc-settings&tab=checkout&section='.Gateway::ID);
        $overview_url = admin_url('admin.php?page=keeal-overview');

        Admin_Assets::enqueue_ui();

        echo '<div class="wrap keeal-wc-transactions keeal-wc-admin-ui">';

        Admin_Layout::hero(
            __('Session detail', 'keeal-for-woocommerce'),
            __('Identifiers, totals, and payment rows for this checkout session.', 'keeal-for-woocommerce'),
            [
                ['text' => __('All transactions', 'keeal-for-woocommerce'), 'url' => $list_url],
                ['text' => __('Overview', 'keeal-for-woocommerce'), 'url' => $overview_url],
                ['text' => __('Payment settings', 'keeal-for-woocommerce'), 'url' => $gw_url, 'primary' => true],
            ]
        );

        echo '<div class="keeal-wc-detail-head"><p><strong><code class="keeal-wc-session-code">'.esc_html($session_id).'</code></strong></p></div>';

        if ($client === null) {
            echo '<div class="notice notice-warning"><p>';
            esc_html_e('Configure the Keeal gateway first.', 'keeal-for-woocommerce');
            echo ' <a href="'.esc_url($gw_url).'">'.esc_html__('Settings', 'keeal-for-woocommerce').'</a>';
            echo '</p></div></div>';

            return;
        }

        try {
            $detail = $client->retrieveMerchantSession($session_id);
        } catch (KeealCheckoutException $e) {
            echo '<div class="notice notice-error"><p>'.esc_html($e->getMessage()).'</p></div></div>';

            return;
        } catch (\Throwable $e) {
            echo '<div class="notice notice-error"><p>'.esc_html($e->getMessage()).'</p></div></div>';

            return;
        }

        $status = self::str_field($detail, ['status']);
        $cents = self::int_field($detail, ['amountCents', 'amount_cents']);
        $currency = self::str_field($detail, ['currency']);
        $cref = self::word_field($detail, ['clientReferenceId', 'client_reference_id']);
        $email = self::str_field($detail, ['customerEmail', 'customer_email']);

        echo '<div class="keeal-wc-panel" style="max-width:920px;">';
        echo '<h2 class="keeal-wc-panel-h">'.esc_html__('Summary', 'keeal-for-woocommerce').'</h2>';
        echo '<table class="widefat striped keeal-wc-dl"><tbody>';
        printf('<tr><th>%s</th><td>%s</td></tr>', esc_html__('Status', 'keeal-for-woocommerce'), esc_html($status));
        printf('<tr><th>%s</th><td class="keeal-wc-amount">%s</td></tr>', esc_html__('Amount', 'keeal-for-woocommerce'), wp_kses_post(self::format_money($cents, $currency)));
        printf('<tr><th>%s</th><td>%s</td></tr>', esc_html__('Customer email', 'keeal-for-woocommerce'), esc_html($email !== '' ? $email : '—'));
        printf('<tr><th>%s</th><td>%s</td></tr>', esc_html__('Client reference', 'keeal-for-woocommerce'), wp_kses_post(self::order_ref_cell($cref)));
        echo '</tbody></table>';

        $payments = isset($detail['payments']) && is_array($detail['payments']) ? $detail['payments'] : [];
        if ($payments !== []) {
            echo '<h3 class="keeal-wc-subpanel-h">'.esc_html__('Payments', 'keeal-for-woocommerce').'</h3>';
            echo '<table class="wp-list-table widefat fixed striped table-view-list keeal-wc-tx-table keeal-wc-tx-table--nested"><thead><tr>';
            echo '<th scope="col" class="column-primary">'.esc_html__('ID', 'keeal-for-woocommerce').'</th>';
            echo '<th scope="col">'.esc_html__('Status', 'keeal-for-woocommerce').'</th>';
            echo '<th scope="col">'.esc_html__('Amount', 'keeal-for-woocommerce').'</th>';
            echo '<th scope="col">'.esc_html__('Method', 'keeal-for-woocommerce').'</th>';
            echo '<th scope="col" class="column-date">'.esc_html__('Created', 'keeal-for-woocommerce').'</th>';
            echo '</tr></thead><tbody>';
            foreach ($payments as $pay) {
                if (! is_array($pay)) {
                    continue;
                }
                $pid = self::str_field($pay, ['id']);
                $pst = self::str_field($pay, ['status']);
                $pc = self::int_field($pay, ['amountCents', 'amount_cents']);
                $pcur = self::str_field($pay, ['currency']);
                $pmt = self::str_field($pay, ['paymentMethodType', 'payment_method_type']);
                $pcr = self::str_field($pay, ['createdAt', 'created_at']);
                $pslug = $pst !== '' ? sanitize_html_class(strtolower($pst)) : 'unknown';
                echo '<tr>';
                echo '<td class="column-primary"><code>'.esc_html($pid).'</code></td>';
                echo '<td><span class="keeal-wc-status keeal-wc-status-'.esc_attr($pslug).'">'.esc_html($pst).'</span></td>';
                echo '<td class="keeal-wc-amount">'.wp_kses_post(self::format_money($pc, $pcur !== '' ? $pcur : $currency)).'</td>';
                echo '<td>'.esc_html($pmt !== '' ? $pmt : '—').'</td>';
                echo '<td class="column-date">'.esc_html(self::format_datetime($pcr)).'</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }

        echo '</div>';
        echo '<p class="keeal-wc-detail-head"><a href="'.esc_url($list_url).'" class="button">'.esc_html__('← Back to all transactions', 'keeal-for-woocommerce').'</a></p>';
        echo '</div>';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private static function str_field(array $row, array $keys): string
    {
        foreach ($keys as $key) {
            if (! isset($row[$key])) {
                continue;
            }
            $v = $row[$key];
            if (is_string($v) || is_numeric($v)) {
                return trim((string) $v);
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private static function word_field(array $row, array $keys): string
    {
        $s = self::str_field($row, $keys);

        return $s;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  list<string>  $keys
     */
    private static function int_field(array $row, array $keys): int
    {
        foreach ($keys as $key) {
            if (! isset($row[$key])) {
                continue;
            }
            $v = $row[$key];
            if (is_numeric($v)) {
                return (int) $v;
            }
        }

        return 0;
    }

    private static function format_money(int $amount_cents, string $currency): string
    {
        if ($amount_cents === 0 && $currency === '') {
            return esc_html('—');
        }

        $major = $amount_cents / 100;
        $cur = strtoupper($currency);
        if ($cur === '' && function_exists('get_woocommerce_currency')) {
            $cur = get_woocommerce_currency();
        }
        if (function_exists('wc_price')) {
            return wc_price($major, ['currency' => $cur !== '' ? $cur : 'USD']);
        }

        return esc_html(number_format_i18n($major, 2).' '.$cur);
    }

    private static function format_datetime(string $iso): string
    {
        if ($iso === '') {
            return '—';
        }

        $ts = strtotime($iso);
        if ($ts === false) {
            return $iso;
        }

        return wp_date(get_option('date_format').' '.get_option('time_format'), $ts);
    }

    private static function order_ref_cell(string $cref): string
    {
        if ($cref === '') {
            return esc_html('—');
        }

        if (ctype_digit($cref)) {
            $oid = (int) $cref;
            $order = function_exists('wc_get_order') ? wc_get_order($oid) : null;
            if ($order && $order->get_id() === $oid) {
                $url = $order->get_edit_order_url();

                return '<a href="'.esc_url($url).'">#'.esc_html((string) $oid).'</a>';
            }
        }

        return esc_html($cref);
    }
}
