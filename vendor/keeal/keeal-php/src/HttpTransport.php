<?php

declare(strict_types=1);

namespace Keeal\Checkout;

/**
 * @internal
 */
final class HttpTransport implements HttpTransportInterface
{
    /**
     * @param list<string> $headerLines "Name: value"
     * @return array{status: int, body: string}
     */
    public function send(string $method, string $url, ?string $body, array $headerLines): array
    {
        $headers = implode("\r\n", $headerLines);
        $ctx = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $headers,
                'content' => $body ?? '',
                'ignore_errors' => true,
                'timeout' => 120,
            ],
        ]);

        $response = @file_get_contents($url, false, $ctx);
        if ($response === false) {
            throw new \RuntimeException('HTTP request failed (network or URL error): ' . $url);
        }

        $status = 0;
        if (!empty($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $line, $m)) {
                    $status = (int) $m[1];
                }
            }
        }

        return ['status' => $status, 'body' => $response];
    }
}
