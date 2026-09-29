<?php

declare(strict_types=1);

/**
 * HTTP headers of a delivery.
 *
 * @package BillMySales\Magento
 */

namespace BillMySales\Magento\Webhook;

/**
 * Builds the headers of a delivery: the standard BillMySales headers plus
 * the one BillMySales' Magento datasource reads today (the signature
 * header the module's first, unfinished version already named).
 */
class Headers
{
    /**
     * Platform code sent in the X-BillMySales-Platform header.
     */
    public const PLATFORM = 'magento';

    /**
     * Signature of a body: base64 of its HMAC-SHA256 with the secret.
     *
     * @param string $body Request body.
     * @param string $secret Shared secret.
     * @return string
     */
    public static function signature(string $body, string $secret): string // phpcs:ignore
    {
        return base64_encode(hash_hmac('sha256', $body, $secret, true));
    }

    /**
     * Headers of a delivery.
     *
     * @param string $body Request body.
     * @param string $secret Shared secret.
     * @param string $event Event, e.g. "order.status_changed".
     * @param string $deliveryId UUID of the notification (the same on retries).
     * @param string $source Store base URL.
     * @param string $platformVersion Magento version.
     * @param string $pluginVersion Module version.
     * @return array<string,string>
     */
    public static function build(// phpcs:ignore Magento2.Functions.StaticFunction.StaticFunction
        string $body,
        string $secret,
        string $event,
        string $deliveryId,
        string $source,
        string $platformVersion,
        string $pluginVersion
    ): array {
        $signature = self::signature($body, $secret);
        return [
            'Content-Type' => 'application/json; charset=utf-8',
            'User-Agent' => 'BillMySales-' . self::PLATFORM . '/' . $pluginVersion,
            'X-BillMySales-Signature' => $signature,
            'X-BillMySales-Platform' => self::PLATFORM,
            'X-BillMySales-Platform-Version' => $platformVersion,
            'X-BillMySales-Plugin-Version' => $pluginVersion,
            'X-BillMySales-Source' => $source,
            'X-BillMySales-Event' => $event,
            'X-BillMySales-Delivery' => $deliveryId,
            // Read by BillMySales' Magento datasource (the module's own,
            // original signature header).
            'X-MagentoBMS-Hmac-Sha256' => $signature,
        ];
    }
}
