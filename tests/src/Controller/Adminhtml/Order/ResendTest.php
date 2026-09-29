<?php

declare(strict_types=1);

/**
 * Tests for Resend.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Controller\Adminhtml\Order;

use BillMySales\Magento\Controller\Adminhtml\Order\Resend;
use BillMySales\Magento\Webhook\Delivery;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Message\ManagerInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Resend::class)]
/**
 * ResendTest.
 */
final class ResendTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testExecuteQueuesADeliveryAndRedirectsToTheOrder(): void
    {
        $request = Mockery::mock(RequestInterface::class);
        $request->shouldReceive('getParam')->with('order_id')->andReturn('9');

        $messageManager = Mockery::mock(ManagerInterface::class);
        $messageManager->shouldReceive('addSuccessMessage')->once();

        $redirect = Mockery::mock(Redirect::class);
        $redirect->shouldReceive('setPath')->once()->with('sales/order/view', ['order_id' => 9])->andReturnSelf();

        $resultFactory = Mockery::mock(ResultFactory::class);
        $resultFactory->shouldReceive('create')->once()->with(ResultFactory::TYPE_REDIRECT)->andReturn($redirect);

        $context = Mockery::mock(Context::class)->shouldIgnoreMissing();
        $context->shouldReceive('getRequest')->andReturn($request);
        $context->shouldReceive('getMessageManager')->andReturn($messageManager);
        $context->shouldReceive('getResultFactory')->andReturn($resultFactory);

        $delivery = Mockery::mock(Delivery::class);
        $delivery->shouldReceive('enqueue')->once()->with(9, 'order.resent');

        $result = (new Resend($context, $delivery))->execute();

        self::assertSame($redirect, $result);
    }
}
