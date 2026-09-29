<?php

declare(strict_types=1);

/**
 * Tests for Payload.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Webhook;

use BillMySales\Magento\Model\ResourceModel\CheckoutFieldValue;
use BillMySales\Magento\Webhook\Payload;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Payload::class)]
/**
 * PayloadTest.
 */
final class PayloadTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testBuild(): void
    {
        $order = Mockery::mock(Order::class);
        $shippingAddress = Mockery::mock(OrderAddressInterface::class);
        $order->shouldReceive('getShippingAddress')->andReturn($shippingAddress);
        $order->shouldReceive('getQuoteId')->andReturn(9);

        $dataObjectProcessor = Mockery::mock(DataObjectProcessor::class);
        $dataObjectProcessor->shouldReceive('buildOutputDataArray')
            ->once()
            ->with($order, OrderInterface::class)
            ->andReturn(['entity_id' => 1, 'status' => 'processing']);
        $dataObjectProcessor->shouldReceive('buildOutputDataArray')
            ->once()
            ->with($shippingAddress, OrderAddressInterface::class)
            ->andReturn(['firstname' => 'Ana']);

        $checkoutFieldValue = Mockery::mock(CheckoutFieldValue::class);
        $checkoutFieldValue->shouldReceive('getByQuoteId')->once()->with(9)->andReturn(['rut' => '11.111.111-1']);

        $payload = new Payload($dataObjectProcessor, $checkoutFieldValue);

        self::assertSame([
            'entity_id' => 1,
            'status' => 'processing',
            'shipping_address' => ['firstname' => 'Ana'],
            'checkout_fields' => ['rut' => '11.111.111-1'],
        ], $payload->build($order));
    }

    /**
     * @return void
     */
    public function testBuildWithNoShippingAddress(): void
    {
        $order = Mockery::mock(Order::class);
        $order->shouldReceive('getShippingAddress')->andReturn(null);
        $order->shouldReceive('getQuoteId')->andReturn(null);

        $dataObjectProcessor = Mockery::mock(DataObjectProcessor::class);
        $dataObjectProcessor->shouldReceive('buildOutputDataArray')
            ->once()
            ->with($order, OrderInterface::class)
            ->andReturn(['entity_id' => 2]);

        $checkoutFieldValue = Mockery::mock(CheckoutFieldValue::class);
        $checkoutFieldValue->shouldNotReceive('getByQuoteId');

        $payload = new Payload($dataObjectProcessor, $checkoutFieldValue);
        $result = $payload->build($order);

        self::assertNull($result['shipping_address']);
        self::assertSame([], $result['checkout_fields']);
    }
}
