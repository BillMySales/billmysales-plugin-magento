<?php

declare(strict_types=1);

/**
 * Tests for Uninstall.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Setup;

use BillMySales\Magento\Model\ResourceModel\CheckoutFieldValue;
use BillMySales\Magento\Model\ResourceModel\DeliveryQueue;
use BillMySales\Magento\Setup\Uninstall;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Uninstall::class)]
/**
 * UninstallTest.
 */
final class UninstallTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testUninstallDropsTheTablesAndTheSettings(): void
    {
        $connection = Mockery::mock(AdapterInterface::class);
        $connection->shouldReceive('dropTable')->once()->with('billmysales_delivery_queue');
        $connection->shouldReceive('dropTable')->once()->with('billmysales_checkout_field_value');
        $connection->shouldReceive('quoteInto')->andReturnUsing(static fn (string $text, $value): string => str_replace('?', (string) $value, $text));
        $connection->shouldReceive('delete')->times(5)->with('core_config_data', Mockery::type('string'));

        $setup = Mockery::mock(SchemaSetupInterface::class);
        $setup->shouldReceive('startSetup')->once();
        $setup->shouldReceive('endSetup')->once();
        $setup->shouldReceive('getConnection')->andReturn($connection);
        $setup->shouldReceive('getTable')->with(DeliveryQueue::TABLE)->andReturn('billmysales_delivery_queue');
        $setup->shouldReceive('getTable')
            ->with(CheckoutFieldValue::TABLE)
            ->andReturn('billmysales_checkout_field_value');
        $setup->shouldReceive('getTable')->with('core_config_data')->andReturn('core_config_data');

        (new Uninstall())->uninstall($setup, Mockery::mock(ModuleContextInterface::class));
    }
}
