<?php

declare(strict_types=1);

/**
 * Tests for ProcessDueDeliveries.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Cron;

use BillMySales\Magento\Cron\ProcessDueDeliveries;
use BillMySales\Magento\Webhook\Delivery;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessDueDeliveries::class)]
/**
 * ProcessDueDeliveriesTest.
 */
final class ProcessDueDeliveriesTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testExecuteProcessesDueDeliveries(): void
    {
        $delivery = Mockery::mock(Delivery::class);
        $delivery->shouldReceive('processDue')->once()->withNoArgs();

        (new ProcessDueDeliveries($delivery))->execute();
    }
}
