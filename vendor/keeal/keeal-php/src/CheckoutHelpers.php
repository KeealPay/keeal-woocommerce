<?php

declare(strict_types=1);

namespace Keeal\Checkout;

final class CheckoutHelpers
{
    public static function normalizeBaseUrl(string $baseUrl): string
    {
        return rtrim($baseUrl, '/');
    }

    public static function isCheckoutSessionId(string $value): bool
    {
        return str_starts_with($value, 'cs_') && strlen($value) > 3;
    }

    /**
     * @param list<array{price_data: array{currency: string}}> $lineItems
     */
    public static function assertSingleCurrency(array $lineItems): void
    {
        if ($lineItems === []) {
            throw new \InvalidArgumentException('line_items must not be empty');
        }
        $codes = [];
        foreach ($lineItems as $item) {
            $codes[strtoupper($item['price_data']['currency'])] = true;
        }
        if (count($codes) > 1) {
            throw new \InvalidArgumentException(
                'All line items must use the same currency; got: ' . implode(', ', array_keys($codes))
            );
        }
    }

    /**
     * @param list<array{price_data: array{unit_amount: int}, quantity: int}> $lineItems
     */
    public static function previewTotalCents(array $lineItems): int
    {
        $sum = 0;
        foreach ($lineItems as $item) {
            $sum += $item['price_data']['unit_amount'] * $item['quantity'];
        }
        return $sum;
    }

    public static function randomIdempotencyKey(): string
    {
        try {
            $hex = bin2hex(random_bytes(16));
            return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
        } catch (\Throwable) {
            return 'keeal_' . time() . '_' . bin2hex(random_bytes(8));
        }
    }
}
