<?php

declare(strict_types=1);

/**
 * Tests for DeliveryQueue.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Model\ResourceModel;

use BillMySales\Magento\Model\ResourceModel\DeliveryQueue;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeliveryQueue::class)]
/**
 * DeliveryQueueTest.
 */
final class DeliveryQueueTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * Test double.
     */
    private AdapterInterface&MockInterface $connection;

    /**
     * Test double.
     */
    private ResourceConnection&MockInterface $resource;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = Mockery::mock(AdapterInterface::class);
        $this->resource = Mockery::mock(ResourceConnection::class);
        $this->resource->shouldReceive('getConnection')->andReturn($this->connection);
        $this->resource->shouldReceive('getTableName')->with(DeliveryQueue::TABLE)->andReturn('billmysales_delivery_queue');
    }

    /**
     * @return void
     */
    public function testEnqueue(): void
    {
        $this->connection->shouldReceive('insert')->once()->with('billmysales_delivery_queue', [
            'order_id' => 7,
            'event' => 'order.status_changed',
            'delivery_id' => 'uuid-1',
            'attempt' => 0,
            'next_attempt_at' => '2026-09-28 10:00:00',
            'created_at' => '2026-09-28 10:00:00',
        ]);

        (new DeliveryQueue($this->resource))->enqueue(7, 'order.status_changed', 'uuid-1', '2026-09-28 10:00:00');
    }

    /**
     * @return void
     */
    public function testDue(): void
    {
        $select = Mockery::mock(Select::class);
        $select->shouldReceive('from')->once()->andReturnSelf();
        $select->shouldReceive('where')->once()->with('next_attempt_at <= ?', '2026-09-28 10:00:00')->andReturnSelf();
        $select->shouldReceive('order')->once()->with('next_attempt_at ASC')->andReturnSelf();
        $select->shouldReceive('limit')->once()->with(20)->andReturnSelf();
        $this->connection->shouldReceive('select')->once()->andReturn($select);
        $this->connection->shouldReceive('fetchAll')->once()->with($select)->andReturn([
            ['id' => '1', 'order_id' => '7', 'event' => 'order.status_changed', 'delivery_id' => 'uuid-1', 'attempt' => '0'],
        ]);

        self::assertSame([
            ['id' => 1, 'order_id' => 7, 'event' => 'order.status_changed', 'delivery_id' => 'uuid-1', 'attempt' => 0],
        ], (new DeliveryQueue($this->resource))->due('2026-09-28 10:00:00', 20));
    }

    /**
     * @return void
     */
    public function testReschedule(): void
    {
        $this->connection->shouldReceive('quoteInto')->once()->with('id = ?', 3)->andReturn('id = 3');
        $this->connection->shouldReceive('update')->once()->with('billmysales_delivery_queue', ['next_attempt_at' => '2026-09-28 10:05:00', 'attempt' => 1], 'id = 3');

        (new DeliveryQueue($this->resource))->reschedule(3, '2026-09-28 10:05:00', 1);
    }

    /**
     * @return void
     */
    public function testDelete(): void
    {
        $this->connection->shouldReceive('quoteInto')->once()->with('id = ?', 9)->andReturn('id = 9');
        $this->connection->shouldReceive('delete')->once()->with('billmysales_delivery_queue', 'id = 9');

        (new DeliveryQueue($this->resource))->delete(9);
    }

    /**
     * @return void
     */
    public function testCountPending(): void
    {
        $select = Mockery::mock(Select::class);
        $select->shouldReceive('from')->once()->andReturnSelf();
        $this->connection->shouldReceive('select')->once()->andReturn($select);
        $this->connection->shouldReceive('fetchOne')->once()->with($select)->andReturn('5');

        self::assertSame(5, (new DeliveryQueue($this->resource))->countPending());
    }
}
