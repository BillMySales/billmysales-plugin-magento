<?php

declare(strict_types=1);

/**
 * Tests for Status.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Block\Adminhtml\Order\View;

use BillMySales\Magento\Block\Adminhtml\Order\View\Status;
use BillMySales\Magento\Settings;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Sales\Model\Order;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Status::class)]
#[UsesClass(Settings::class)]
/**
 * StatusTest.
 */
final class StatusTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @param Order $order Test double.
     * @param Settings $settings Test double.
     * @return Status
     */
    private function block(Order $order, Settings $settings): Status
    {
        $urlBuilder = Mockery::mock(UrlInterface::class);
        $urlBuilder->shouldReceive('getUrl')->with('billmysales/order/resend', ['order_id' => 9])->andReturn('https://shop.test/admin/billmysales/order/resend/order_id/9/');

        $context = Mockery::mock(Context::class)->shouldIgnoreMissing();
        $context->shouldReceive('getUrlBuilder')->andReturn($urlBuilder);

        $registry = Mockery::mock(Registry::class);
        $registry->shouldReceive('registry')->with('current_order')->andReturn($order);

        return new Status($context, $registry, $settings);
    }

    /**
     * @return void
     */
    public function testGetOrder(): void
    {
        $order = Mockery::mock(Order::class);
        $order->shouldReceive('getEntityId')->andReturn(9);
        $settings = Mockery::mock(Settings::class);

        self::assertSame($order, $this->block($order, $settings)->getOrder());
    }

    /**
     * @return void
     */
    public function testCanResend(): void
    {
        $order = Mockery::mock(Order::class);
        $order->shouldReceive('getEntityId')->andReturn(9);
        $settings = Mockery::mock(Settings::class);
        $settings->shouldReceive('get')->andReturn(['url' => 'https://example.test', 'secret' => 'x', 'statuses' => [], 'active' => true]);

        self::assertTrue($this->block($order, $settings)->canResend());
    }

    /**
     * @return void
     */
    public function testGetResendUrl(): void
    {
        $order = Mockery::mock(Order::class);
        $order->shouldReceive('getEntityId')->andReturn(9);
        $settings = Mockery::mock(Settings::class);

        self::assertSame(
            'https://shop.test/admin/billmysales/order/resend/order_id/9/',
            $this->block($order, $settings)->getResendUrl()
        );
    }
}
