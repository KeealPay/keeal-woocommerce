<?php

declare(strict_types=1);

namespace Keeal\Checkout;

/**
 * Unauthenticated client: retrieve session + pay (+ cancel/abandon + PayPal) only.
 */
final class KeealCheckoutPublic
{
    private readonly string $baseUrl;
    private readonly HttpTransportInterface $http;
    /** @var array<string, string> */
    private readonly array $defaultHeaders;

    /**
     * @param array{baseUrl: string, defaultHeaders?: array<string, string>, http?: HttpTransportInterface} $options
     */
    public function __construct(array $options)
    {
        $baseUrl = trim((string) ($options['baseUrl'] ?? ''));
        if ($baseUrl === '') {
            throw new \InvalidArgumentException('KeealCheckoutPublic: baseUrl is required');
        }
        $this->baseUrl = CheckoutHelpers::normalizeBaseUrl($baseUrl);
        $this->defaultHeaders = $options['defaultHeaders'] ?? [];
        $this->http = $options['http'] ?? new HttpTransport();
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieveSession(string $sessionId): array
    {
        $enc = rawurlencode($sessionId);
        $url = $this->baseUrl . '/checkout/sessions/' . $enc;
        $lines = array_merge(
            ['Accept: application/json'],
            $this->buildDefaultHeaderLines()
        );
        $res = $this->http->send('GET', $url, null, $lines);
        return $this->decodeSuccessJson($res['status'], $res['body']);
    }

    /**
     * @param array<string, mixed> $params
     * @param array{idempotencyKey?: string|null} $options
     * @return array{paymentId: string, clientSecret: string|null}
     */
    public function createPayment(string $sessionId, array $params, array $options = []): array
    {
        $enc = rawurlencode($sessionId);
        $idem = isset($options['idempotencyKey']) && $options['idempotencyKey'] !== null
            ? trim((string) $options['idempotencyKey'])
            : CheckoutHelpers::randomIdempotencyKey();
        $url = $this->baseUrl . '/checkout/sessions/' . $enc . '/pay';
        $lines = array_merge(
            [
                'Content-Type: application/json',
                'Accept: application/json',
                'Idempotency-Key: ' . $idem,
            ],
            $this->buildDefaultHeaderLines()
        );
        $res = $this->http->send('POST', $url, json_encode($params, JSON_THROW_ON_ERROR), $lines);
        return $this->decodeSuccessJson($res['status'], $res['body']);
    }

    public function cancelSession(string $sessionId): void
    {
        $this->postEmpty('/checkout/sessions/' . rawurlencode($sessionId) . '/cancel');
    }

    public function abandonSession(string $sessionId): void
    {
        $this->postEmpty('/checkout/sessions/' . rawurlencode($sessionId) . '/abandon');
    }

    /**
     * @param array{amountCents: int, clientEmail?: string, clientName?: string} $params
     * @return array{orderId: string, paymentId: string}
     */
    public function paypalCreateOrder(string $sessionId, array $params): array
    {
        $enc = rawurlencode($sessionId);
        $url = $this->baseUrl . '/checkout/sessions/' . $enc . '/paypal/create-order';
        $lines = array_merge(
            ['Content-Type: application/json', 'Accept: application/json'],
            $this->buildDefaultHeaderLines()
        );
        $res = $this->http->send('POST', $url, json_encode($params, JSON_THROW_ON_ERROR), $lines);
        return $this->decodeSuccessJson($res['status'], $res['body']);
    }

    /**
     * @param array{orderId: string} $params
     * @return array<string, mixed>
     */
    public function paypalCapture(string $sessionId, array $params): array
    {
        $enc = rawurlencode($sessionId);
        $url = $this->baseUrl . '/checkout/sessions/' . $enc . '/paypal/capture';
        $lines = array_merge(
            ['Content-Type: application/json', 'Accept: application/json'],
            $this->buildDefaultHeaderLines()
        );
        $res = $this->http->send('POST', $url, json_encode($params, JSON_THROW_ON_ERROR), $lines);
        return $this->decodeSuccessJson($res['status'], $res['body']);
    }

    private function postEmpty(string $path): void
    {
        $url = $this->baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);
        $lines = array_merge(['Accept: application/json'], $this->buildDefaultHeaderLines());
        $res = $this->http->send('POST', $url, null, $lines);
        $status = $res['status'];
        if ($status === 204 || ($status >= 200 && $status < 300 && $res['body'] === '')) {
            return;
        }
        $this->throwForJsonBody($status, $res['body']);
    }

    /**
     * @return list<string>
     */
    private function buildDefaultHeaderLines(): array
    {
        $lines = [];
        foreach ($this->defaultHeaders as $k => $v) {
            $lines[] = $k . ': ' . $v;
        }
        return $lines;
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
