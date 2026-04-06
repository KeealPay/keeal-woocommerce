<?php

declare(strict_types=1);

namespace Keeal\Checkout;

final class KeealCheckoutException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $httpStatus,
        public readonly ?string $errorCode = null,
        public readonly mixed $details = null,
        public readonly mixed $body = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function isKeealCheckoutException(\Throwable $e): bool
    {
        return $e instanceof self;
    }
}
