<?php

declare(strict_types=1);

/**
 * Tests for QueueOnStatusChange.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Observer;

use BillMySales\Magento\Observer\QueueOnStatusChange;
use BillMySales\Magento\Settings;
use BillMySales\Magento\Webhook\Delivery;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Sales\Model\Order;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueueOnStatusChange::class)]
#[UsesClass(Settings::class)]
/**
 * QueueOnStatusChangeTest.
 */
final class QueueOnStatusChangeTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @param Order $order Test double.
     * @return EventObserver
     */
    private function observerFor(Order $order): EventObserver
    {
        $event = Mockery::mock(Event::class);
        $event->shouldReceive('getOrder')->andReturn($order);
        $observer = Mockery::mock(EventObserver::class);
        $observer->shouldReceive('getEvent')->andReturn($event);
        return $observer;
    }

    /**
     * @return void
     */
    public function testQueuesWhenTheNewStatusNotifies(): void
    {
        $order = Mockery::mock(Order::class);
        $order->shouldReceive('getStatus')->andReturn('processing');
        $order->shouldReceive('getOrigData')->with('status')->andReturn('pending');
        $order->shouldReceive('getEntityId')->andReturn(7);

        $settingsService = Mockery::mock(Settings::class);
        $settingsService->shouldReceive('get')->andReturn(['url' => 'https://example.test', 'secret' => 'x', 'statuses' => ['processing'], 'active' => true]);

        $delivery = Mockery::mock(Delivery::class);
        $delivery->shouldReceive('enqueue')->once()->with(7, 'order.status_changed');

        (new QueueOnStatusChange($settingsService, $delivery))->execute($this->observerFor($order));
    }

    /**
     * @return void
     */
    public function testDoesNothingWhenTheStatusDidNotChange(): void
    {
        $order = Mockery::mock(Order::class);
        $order->shouldReceive('getStatus')->andReturn('processing');
        $order->shouldReceive('getOrigData')->with('status')->andReturn('processing');

        $settingsService = Mockery::mock(Settings::class);
        $delivery = Mockery::mock(Delivery::class);
        $delivery->shouldReceive('enqueue')->never();

        (new QueueOnStatusChange($settingsService, $delivery))->execute($this->observerFor($order));
    }

    /**
     * @return void
     */
    public function testDoesNothingWhenTheNewStatusIsNotSelected(): void
    {
        $order = Mockery::mock(Order::class);
        $order->shouldReceive('getStatus')->andReturn('canceled');
        $order->shouldReceive('getOrigData')->with('status')->andReturn('processing');

        $settingsService = Mockery::mock(Settings::class);
        $settingsService->shouldReceive('get')->andReturn(['url' => 'https://example.test', 'secret' => 'x', 'statuses' => ['processing'], 'active' => true]);

        $delivery = Mockery::mock(Delivery::class);
        $delivery->shouldReceive('enqueue')->never();

        (new QueueOnStatusChange($settingsService, $delivery))->execute($this->observerFor($order));
    }
}
