<?php

declare(strict_types=1);

/**
 * Tests for OrderStatuses.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Model\Config\Source;

use BillMySales\Magento\Model\Config\Source\OrderStatuses;
use Magento\Sales\Model\Order\Config as OrderConfig;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OrderStatuses::class)]
/**
 * OrderStatusesTest.
 */
final class OrderStatusesTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testToOptionArray(): void
    {
        $orderConfig = Mockery::mock(OrderConfig::class);
        $orderConfig->shouldReceive('getStatuses')->andReturn([
            'processing' => 'Processing',
            'complete' => 'Complete',
        ]);

        self::assertSame([
            ['value' => 'processing', 'label' => 'Processing'],
            ['value' => 'complete', 'label' => 'Complete'],
        ], (new OrderStatuses($orderConfig))->toOptionArray());
    }
}
