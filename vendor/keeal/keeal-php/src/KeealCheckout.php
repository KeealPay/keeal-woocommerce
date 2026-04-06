<?php

declare(strict_types=1);

namespace Keeal\Checkout;

/**
 * Server-side client: secret API key (keeal_sk_…). Never expose in browsers.
 */
final class KeealCheckout
{
    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly HttpTransportInterface $http;
    /** @var array<string, string> */
    private readonly array $defaultHeaders;

    /**
     * @param array{apiKey: string, baseUrl?: string, defaultHeaders?: array<string, string>, http?: HttpTransportInterface} $options
     */
    public function __construct(array $options)
    {
        $apiKey = trim((string) ($options['apiKey'] ?? ''));
        $baseUrl = trim((string) ($options['baseUrl'] ?? ''));
        if ($apiKey === '') {
            throw new \InvalidArgumentException('KeealCheckout: apiKey is required');
        }
        if ($baseUrl === '') {
            $dev = defined('KEEAL_CHECKOUT_DEV_MODE') && KEEAL_CHECKOUT_DEV_MODE;
            if ($dev) {
                throw new \InvalidArgumentException('KeealCheckout: baseUrl is required when KEEAL_CHECKOUT_DEV_MODE is true');
            }
            $baseUrl = 'https://api.keeal.com/api';
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = CheckoutHelpers::normalizeBaseUrl($baseUrl);
        $this->defaultHeaders = $options['defaultHeaders'] ?? [];
        $this->http = $options['http'] ?? new HttpTransport();
    }

    /**
     * @param array<string, mixed> $params Create session body (line_items, success_url, …)
     * @param array{idempotencyKey?: string|null} $options
     * @return array{id: string, url: string}
     */
    public function createSession(array $params, array $options = []): array
    {
        $idem = isset($options['idempotencyKey']) && $options['idempotencyKey'] !== null
            ? trim((string) $options['idempotencyKey'])
            : CheckoutHelpers::randomIdempotencyKey();
        $path = '/checkout/sessions';
        return $this->requestJson('POST', $path, json_encode($params, JSON_THROW_ON_ERROR), false, [
            'Idempotency-Key: ' . $idem,
        ]);
    }

    public function createSessionUrl(array $params, array $options = []): string
    {
        $r = $this->createSession($params, $options);
        return $r['url'];
    }

    /**
     * @param array{limit?: int, page?: int}|null $options
     * @return array{object: string, data: list<array<string, mixed>>, has_more: bool, page: int, limit: int}
     */
    public function listMerchantSessions(?array $options = null): array
    {
        $options = $options ?? [];
        $q = [];
        if (isset($options['limit'])) {
            $q['limit'] = (string) $options['limit'];
        }
        if (isset($options['page'])) {
            $q['page'] = (string) $options['page'];
        }
        $path = '/checkout/merchant/sessions';
        if ($q !== []) {
            $path .= '?' . http_build_query($q);
        }
        return $this->requestJson('GET', $path, null, false);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveMerchantSession(string $sessionId): array
    {
        $enc = rawurlencode($sessionId);
        return $this->requestJson('GET', "/checkout/merchant/sessions/{$enc}", null, false);
    }

    /**
     * Public session shape (unauthenticated).
     *
     * @return array<string, mixed>
     */
    public function retrieveSession(string $sessionId): array
    {
        $enc = rawurlencode($sessionId);
        return $this->requestJson('GET', "/checkout/sessions/{$enc}", null, true);
    }

    /**
     * @param array<string, mixed> $params amountCents, clientEmail, …
     * @param array{idempotencyKey?: string|null} $options
     * @return array{paymentId: string, clientSecret: string|null}
     */
    public function createPayment(string $sessionId, array $params, array $options = []): array
    {
        $enc = rawurlencode($sessionId);
        $idem = isset($options['idempotencyKey']) && $options['idempotencyKey'] !== null
            ? trim((string) $options['idempotencyKey'])
            : CheckoutHelpers::randomIdempotencyKey();
        return $this->requestJson(
            'POST',
            "/checkout/sessions/{$enc}/pay",
            json_encode($params, JSON_THROW_ON_ERROR),
            true,
            ['Idempotency-Key: ' . $idem]
        );
    }

    public function cancelSession(string $sessionId): void
    {
        $enc = rawurlencode($sessionId);
        $this->requestEmpty('POST', "/checkout/sessions/{$enc}/cancel", null, true);
    }

    public function abandonSession(string $sessionId): void
    {
        $enc = rawurlencode($sessionId);
        $this->requestEmpty('POST', "/checkout/sessions/{$enc}/abandon", null, true);
    }

    /**
     * @param array{amountCents: int, clientEmail?: string, clientName?: string} $params
     * @return array{orderId: string, paymentId: string}
     */
    public function paypalCreateOrder(string $sessionId, array $params): array
    {
        $enc = rawurlencode($sessionId);
        return $this->requestJson(
            'POST',
            "/checkout/sessions/{$enc}/paypal/create-order",
            json_encode($params, JSON_THROW_ON_ERROR),
            true
        );
    }

    /**
     * @param array{orderId: string} $params
     * @return array<string, mixed>
     */
    public function paypalCapture(string $sessionId, array $params): array
    {
        $enc = rawurlencode($sessionId);
        return $this->requestJson(
            'POST',
            "/checkout/sessions/{$enc}/paypal/capture",
            json_encode($params, JSON_THROW_ON_ERROR),
            true
        );
    }

    /**
     * @param list<string> $extraHeaderLines
     * @return array<string, mixed>
     */
    private function requestJson(string $method, string $path, ?string $body, bool $skipAuth, array $extraHeaderLines = []): array
    {
        $url = $this->baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
        $lines = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        foreach ($this->defaultHeaders as $k => $v) {
            $lines[] = $k . ': ' . $v;
        }
        if (!$skipAuth) {
            $lines[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        foreach ($extraHeaderLines as $h) {
            $lines[] = $h;
        }
        $res = $this->http->send($method, $url, $body, $lines);
        return $this->decodeSuccessJson($res['status'], $res['body']);
    }

    private function requestEmpty(string $method, string $path, ?string $body, bool $skipAuth): void
    {
        $url = $this->baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
        $lines = ['Accept: application/json'];
        foreach ($this->defaultHeaders as $k => $v) {
            $lines[] = $k . ': ' . $v;
        }
        if (!$skipAuth) {
            $lines[] = 'Authorization: Bearer ' . $this->apiKey;
        }
        if ($body !== null) {
            $lines[] = 'Content-Type: application/json';
        }
        $res = $this->http->send($method, $url, $body, $lines);
        $status = $res['status'];
        if ($status === 204 || ($status >= 200 && $status < 300 && $res['body'] === '')) {
            return;
        }
        $this->throwForJsonBody($status, $res['body']);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeSuccessJson(int $status, string $text): array
    {
        if ($status >= 200 && $status < 300) {
            if ($text === '') {
                return [];
            }
            try {
                $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new KeealCheckoutException('Invalid JSON response', $status, null, null, $text);
            }
            if (!is_array($data)) {
                throw new KeealCheckoutException('Unexpected JSON response', $status, null, null, $data);
            }
            return $data;
        }
        $this->throwForJsonBody($status, $text);
    }

    private function throwForJsonBody(int $status, string $text): never
    {
        $data = [];
        if ($text !== '') {
            try {
                $decoded = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            } catch (\JsonException) {
                $data = ['message' => $text];
            }
        }
        $message = is_string($data['message'] ?? null) ? $data['message'] : null;
        if ($message === null || $message === '') {
            $message = is_string($data['error'] ?? null) ? $data['error'] : "Request failed with status {$status}";
        }
        $code = is_string($data['error'] ?? null) ? $data['error'] : null;
        throw new KeealCheckoutException(
            $message,
            $status,
            $code,
            $data['details'] ?? null,
            $data !== [] ? $data : null
        );
    }
}
