<?php

declare(strict_types=1);

/**
 * Tests for Headers.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Webhook;

use BillMySales\Magento\Webhook\Headers;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Headers::class)]
/**
 * HeadersTest.
 */
final class HeadersTest extends TestCase
{
    /**
     * @return void
     */
    public function testSignature(): void
    {
        $expected = base64_encode(hash_hmac('sha256', 'body', 'secret', true));
        self::assertSame($expected, Headers::signature('body', 'secret'));
    }

    /**
     * @return void
     */
    public function testBuild(): void
    {
        $headers = Headers::build('body', 'secret', 'order.status_changed', 'delivery-uuid', 'https://shop.test', '2.4.9', '1.0.0');

        self::assertSame(Headers::signature('body', 'secret'), $headers['X-BillMySales-Signature']);
        self::assertSame($headers['X-BillMySales-Signature'], $headers['X-MagentoBMS-Hmac-Sha256']);
        self::assertSame('magento', $headers['X-BillMySales-Platform']);
        self::assertSame('2.4.9', $headers['X-BillMySales-Platform-Version']);
        self::assertSame('1.0.0', $headers['X-BillMySales-Plugin-Version']);
        self::assertSame('https://shop.test', $headers['X-BillMySales-Source']);
        self::assertSame('order.status_changed', $headers['X-BillMySales-Event']);
        self::assertSame('delivery-uuid', $headers['X-BillMySales-Delivery']);
        self::assertSame('BillMySales-magento/1.0.0', $headers['User-Agent']);
        self::assertSame('application/json; charset=utf-8', $headers['Content-Type']);
    }
}
