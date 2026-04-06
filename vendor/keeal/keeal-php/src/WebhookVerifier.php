<?php

declare(strict_types=1);

namespace Keeal\Checkout;

/**
 * Verifies X-Keeal-Signature (format t=<unix>,v1=<hex>) on the raw request body.
 * Signed payload is "{t}.{rawBody}" HMAC-SHA256 with the whsec secret (raw secret bytes).
 */
final class WebhookVerifier
{
    /**
     * @param  string  $rawBody  Exact bytes received (do not re-encode JSON).
     * @param  string  $signatureHeader  Value of X-Keeal-Signature.
     * @param  string  $whsecSigningSecret  Webhook signing secret (whsec_…).
     */
    public static function verify(string $rawBody, string $signatureHeader, string $whsecSigningSecret): bool
    {
        if ($rawBody === '' || $signatureHeader === '' || $whsecSigningSecret === '') {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signatureHeader) as $segment) {
            $segment = trim($segment);
            if ($segment === '') {
                continue;
            }
            $eq = strpos($segment, '=');
            if ($eq === false) {
                continue;
            }
            $parts[trim(substr($segment, 0, $eq))] = trim(substr($segment, $eq + 1));
        }
        $t = $parts['t'] ?? null;
        $v1 = $parts['v1'] ?? null;
        if ($t === null || $t === '' || $v1 === null || $v1 === '') {
            return false;
        }

        $signed = $t . '.' . $rawBody;
        $expected = hash_hmac('sha256', $signed, $whsecSigningSecret, false);
        if (strlen($expected) !== strlen($v1)) {
            return false;
        }

        return hash_equals($expected, $v1);
    }
}
