<?php

declare(strict_types=1);

/**
 * Tests for CheckoutFieldValue.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Model\ResourceModel;

use BillMySales\Magento\Model\ResourceModel\CheckoutFieldValue;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckoutFieldValue::class)]
/**
 * CheckoutFieldValueTest.
 */
final class CheckoutFieldValueTest extends TestCase
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
        $this->resource->shouldReceive('getTableName')
            ->with(CheckoutFieldValue::TABLE)
            ->andReturn('billmysales_checkout_field_value');
    }

    /**
     * @return void
     */
    public function testSave(): void
    {
        $this->connection->shouldReceive('insertOnDuplicate')->once()->with(
            'billmysales_checkout_field_value',
            ['quote_id' => 7, 'field_key' => 'rut', 'value' => '11.111.111-1'],
            ['value']
        );

        (new CheckoutFieldValue($this->resource))->save(7, 'rut', '11.111.111-1');
    }

    /**
     * @return void
     */
    public function testGetByQuoteId(): void
    {
        $select = Mockery::mock(Select::class);
        $select->shouldReceive('from')->once()->andReturnSelf();
        $select->shouldReceive('where')->once()->with('quote_id = ?', 7)->andReturnSelf();
        $this->connection->shouldReceive('select')->once()->andReturn($select);
        $this->connection->shouldReceive('fetchAll')->once()->with($select)->andReturn([
            ['field_key' => 'rut', 'value' => '11.111.111-1'],
            ['field_key' => 'giro', 'value' => 'Retail'],
        ]);

        self::assertSame(
            ['rut' => '11.111.111-1', 'giro' => 'Retail'],
            (new CheckoutFieldValue($this->resource))->getByQuoteId(7)
        );
    }

    /**
     * @return void
     */
    public function testGetByQuoteIdWithNoValues(): void
    {
        $select = Mockery::mock(Select::class);
        $select->shouldReceive('from')->once()->andReturnSelf();
        $select->shouldReceive('where')->once()->andReturnSelf();
        $this->connection->shouldReceive('select')->once()->andReturn($select);
        $this->connection->shouldReceive('fetchAll')->once()->with($select)->andReturn([]);

        self::assertSame([], (new CheckoutFieldValue($this->resource))->getByQuoteId(9));
    }
}
