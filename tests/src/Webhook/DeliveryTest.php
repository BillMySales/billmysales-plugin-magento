<?php

declare(strict_types=1);

/**
 * Tests for Delivery.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Webhook;

use BillMySales\Magento\Model\ResourceModel\DeliveryQueue;
use BillMySales\Magento\Settings;
use BillMySales\Magento\Webhook\Delivery;
use BillMySales\Magento\Webhook\Headers;
use BillMySales\Magento\Webhook\Payload;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Phrase;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(Delivery::class)]
#[UsesClass(Settings::class)]
#[UsesClass(Headers::class)]
/**
 * DeliveryTest.
 */
final class DeliveryTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * Test double.
     */
    private DeliveryQueue&MockInterface $queue;

    /**
     * Test double.
     */
    private OrderRepositoryInterface&MockInterface $orderRepository;

    /**
     * Test double.
     */
    private Payload&MockInterface $payload;

    /**
     * Test double.
     */
    private ClientInterface&MockInterface $httpClient;

    /**
     * Test double.
     */
    private ModuleListInterface&MockInterface $moduleList;

    /**
     * Test double.
     */
    private Random&MockInterface $random;

    /**
     * Test double.
     */
    private LoggerInterface&MockInterface $logger;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->queue = Mockery::mock(DeliveryQueue::class);
        $this->orderRepository = Mockery::mock(OrderRepositoryInterface::class);
        $this->payload = Mockery::mock(Payload::class);
        $this->httpClient = Mockery::mock(ClientInterface::class);
        $this->moduleList = Mockery::mock(ModuleListInterface::class);
        $this->moduleList->shouldReceive('getOne')->andReturn(['setup_version' => '1.0.0']);
        $this->random = Mockery::mock(Random::class);
        $this->random->shouldReceive('getRandomBytes')->with(16)->andReturn(str_repeat('a', 16));
        $this->logger = Mockery::mock(LoggerInterface::class);
    }

    /**
     * @param array{url: string, secret: string, statuses: string[], active: bool}|null $settings Settings the mock returns.
     * @return Delivery
     */
    private function delivery(?array $settings = null): Delivery
    {
        $settingsMock = Mockery::mock(Settings::class);
        $settingsMock->shouldReceive('get')->andReturn($settings ?? $this->readySettings());

        $store = Mockery::mock(StoreInterface::class);
        $store->shouldReceive('getBaseUrl')->andReturn('https://shop.test/');
        $storeManager = Mockery::mock(StoreManagerInterface::class);
        $storeManager->shouldReceive('getStore')->andReturn($store);

        $productMetadata = Mockery::mock(ProductMetadataInterface::class);
        $productMetadata->shouldReceive('getVersion')->andReturn('2.4.9');

        return new Delivery(
            $settingsMock,
            $this->queue,
            $this->orderRepository,
            $this->payload,
            $this->httpClient,
            $storeManager,
            $productMetadata,
            $this->moduleList,
            $this->random,
            $this->logger
        );
    }

    /**
     * @return array{url: string, secret: string, statuses: string[], active: bool}
     */
    private function readySettings(): array
    {
        return ['url' => 'https://billmysales.example/webhook', 'secret' => 's3cr3t', 'statuses' => ['processing'], 'active' => true];
    }

    /**
     * @return void
     */
    public function testEnqueue(): void
    {
        $this->queue->shouldReceive('enqueue')
            ->once()
            ->with(1, 'order.status_changed', Mockery::type('string'), Mockery::type('string'));

        $this->delivery()->enqueue(1, 'order.status_changed');
    }

    /**
     * @return void
     */
    public function testProcessDueDoesNothingWhenNotReady(): void
    {
        $this->queue->shouldReceive('due')->never();
        $this->delivery(['url' => '', 'secret' => '', 'statuses' => [], 'active' => false])->processDue();
    }

    /**
     * @return void
     */
    public function testProcessDueDropsAnOrderThatIsGone(): void
    {
        $row = ['id' => 5, 'order_id' => 9, 'event' => 'order.status_changed', 'delivery_id' => 'uuid-9', 'attempt' => 0];
        $this->queue->shouldReceive('due')->once()->andReturn([$row]);
        $this->orderRepository->shouldReceive('get')->with(9)->andThrow(new NoSuchEntityException(new Phrase('gone')));
        $this->queue->shouldReceive('delete')->once()->with(5);
        $this->orderRepository->shouldNotReceive('save');
        $this->logger->shouldReceive('warning')->once();

        $this->delivery()->processDue();
    }

    /**
     * @return void
     */
    public function testProcessDueDeliversSuccessfully(): void
    {
        $row = ['id' => 1, 'order_id' => 1, 'event' => 'order.status_changed', 'delivery_id' => 'uuid-1', 'attempt' => 0];
        $order = Mockery::mock(Order::class);
        $this->queue->shouldReceive('due')->once()->andReturn([$row]);
        $this->orderRepository->shouldReceive('get')->with(1)->andReturn($order);
        $this->payload->shouldReceive('build')->with($order)->andReturn(['entity_id' => 1]);
        $this->httpClient->shouldReceive('setTimeout')->once();
        $this->httpClient->shouldReceive('setHeaders')->once();
        $this->httpClient->shouldReceive('post')->once();
        $this->httpClient->shouldReceive('getStatus')->andReturn(200);
        $this->httpClient->shouldReceive('getBody')->andReturn('ok');
        $this->queue->shouldReceive('delete')->once()->with(1);
        $order->shouldReceive('addStatusHistoryComment')->once()->with(Mockery::type('string'));
        $this->orderRepository->shouldReceive('save')->once()->with($order);
        $this->logger->shouldReceive('info')->once();

        $this->delivery()->processDue();
    }

    /**
     * @return void
     */
    public function testProcessDueRetriesARetryableFailure(): void
    {
        $row = ['id' => 1, 'order_id' => 1, 'event' => 'order.status_changed', 'delivery_id' => 'uuid-1', 'attempt' => 0];
        $order = Mockery::mock(Order::class);
        $this->queue->shouldReceive('due')->once()->andReturn([$row]);
        $this->orderRepository->shouldReceive('get')->andReturn($order);
        $this->payload->shouldReceive('build')->andReturn([]);
        $this->httpClient->shouldReceive('setTimeout', 'setHeaders', 'post');
        $this->httpClient->shouldReceive('getStatus')->andReturn(503);
        $this->httpClient->shouldReceive('getBody')->andReturn('temporarily unavailable');
        $this->queue->shouldReceive('reschedule')->once()->with(1, Mockery::type('string'), 1);
        $order->shouldReceive('addStatusHistoryComment')->once()->with(Mockery::type('string'));
        $this->orderRepository->shouldReceive('save')->once()->with($order);
        $this->logger->shouldReceive('warning')->once();

        $this->delivery()->processDue();
    }

    /**
     * @return void
     */
    public function testProcessDueRetriesANetworkError(): void
    {
        $row = ['id' => 1, 'order_id' => 1, 'event' => 'order.status_changed', 'delivery_id' => 'uuid-1', 'attempt' => 0];
        $order = Mockery::mock(Order::class);
        $this->queue->shouldReceive('due')->once()->andReturn([$row]);
        $this->orderRepository->shouldReceive('get')->andReturn($order);
        $this->payload->shouldReceive('build')->andReturn([]);
        $this->httpClient->shouldReceive('setTimeout', 'setHeaders');
        $this->httpClient->shouldReceive('post')->andThrow(new \Exception('Connection timed out'));
        $this->queue->shouldReceive('reschedule')->once();
        $order->shouldReceive('addStatusHistoryComment')->once()->with(Mockery::type('string'));
        $this->orderRepository->shouldReceive('save')->once()->with($order);
        $this->logger->shouldReceive('warning')->once();

        $this->delivery()->processDue();
    }

    /**
     * @return void
     */
    public function testProcessDueGivesUpAfterTheLastRetry(): void
    {
        $row = ['id' => 1, 'order_id' => 1, 'event' => 'order.status_changed', 'delivery_id' => 'uuid-1', 'attempt' => count(Delivery::RETRY_DELAYS)];
        $order = Mockery::mock(Order::class);
        $this->queue->shouldReceive('due')->once()->andReturn([$row]);
        $this->orderRepository->shouldReceive('get')->andReturn($order);
        $this->payload->shouldReceive('build')->andReturn([]);
        $this->httpClient->shouldReceive('setTimeout', 'setHeaders', 'post');
        $this->httpClient->shouldReceive('getStatus')->andReturn(503);
        $this->httpClient->shouldReceive('getBody')->andReturn('still down');
        $this->queue->shouldReceive('delete')->once()->with(1);
        $order->shouldReceive('addStatusHistoryComment')->once()->with(Mockery::type('string'));
        $this->orderRepository->shouldReceive('save')->once()->with($order);
        $this->logger->shouldReceive('error')->once();

        $this->delivery()->processDue();
    }

    /**
     * @return void
     */
    public function testProcessDueRejectsAClientError(): void
    {
        $row = ['id' => 1, 'order_id' => 1, 'event' => 'order.status_changed', 'delivery_id' => 'uuid-1', 'attempt' => 0];
        $order = Mockery::mock(Order::class);
        $this->queue->shouldReceive('due')->once()->andReturn([$row]);
        $this->orderRepository->shouldReceive('get')->andReturn($order);
        $this->payload->shouldReceive('build')->andReturn([]);
        $this->httpClient->shouldReceive('setTimeout', 'setHeaders', 'post');
        $this->httpClient->shouldReceive('getStatus')->andReturn(401);
        $this->httpClient->shouldReceive('getBody')->andReturn('invalid secret');
        $this->queue->shouldReceive('delete')->once()->with(1);
        $order->shouldReceive('addStatusHistoryComment')->once()->with(Mockery::type('string'));
        $this->orderRepository->shouldReceive('save')->once()->with($order);
        $this->logger->shouldReceive('error')->once();

        $this->delivery()->processDue();
    }

    /**
     * @return void
     */
    public function testProcessDueRejectsWithNoBody(): void
    {
        $row = ['id' => 1, 'order_id' => 1, 'event' => 'order.status_changed', 'delivery_id' => 'uuid-1', 'attempt' => 0];
        $order = Mockery::mock(Order::class);
        $this->queue->shouldReceive('due')->once()->andReturn([$row]);
        $this->orderRepository->shouldReceive('get')->andReturn($order);
        $this->payload->shouldReceive('build')->andReturn([]);
        $this->httpClient->shouldReceive('setTimeout', 'setHeaders', 'post');
        $this->httpClient->shouldReceive('getStatus')->andReturn(400);
        $this->httpClient->shouldReceive('getBody')->andReturn('');
        $this->queue->shouldReceive('delete')->once();
        $order->shouldReceive('addStatusHistoryComment')->once()->with(Mockery::type('string'));
        $this->orderRepository->shouldReceive('save')->once()->with($order);
        $this->logger->shouldReceive('error')->once();

        $this->delivery()->processDue();
    }

    /**
     * @return void
     */
    public function testIsRetryable(): void
    {
        self::assertTrue(Delivery::isRetryable(-1));
        self::assertTrue(Delivery::isRetryable(408));
        self::assertTrue(Delivery::isRetryable(429));
        self::assertTrue(Delivery::isRetryable(500));
        self::assertTrue(Delivery::isRetryable(503));
        self::assertFalse(Delivery::isRetryable(400));
        self::assertFalse(Delivery::isRetryable(401));
        self::assertFalse(Delivery::isRetryable(200));
    }
}
