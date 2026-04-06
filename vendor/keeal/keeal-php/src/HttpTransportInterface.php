<?php

declare(strict_types=1);

namespace Keeal\Checkout;

interface HttpTransportInterface
{
    /**
     * @param list<string> $headerLines "Name: value"
     *
     * @return array{status: int, body: string}
     */
    public function send(string $method, string $url, ?string $body, array $headerLines): array;
}
