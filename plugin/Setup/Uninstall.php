<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

namespace BillMySales\Magento\Setup;

use BillMySales\Magento\Checkout\CheckoutFields;
use BillMySales\Magento\Model\ResourceModel\CheckoutFieldValue;
use BillMySales\Magento\Model\ResourceModel\DeliveryQueue;
use BillMySales\Magento\Settings;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UninstallInterface;

/**
 * Runs when the module is uninstalled with `bin/magento module:uninstall
 * --remove-data`: drops the module's tables (declarative schema doesn't
 * do this on its own) and removes its settings.
 */
class Uninstall implements UninstallInterface
{
    /**
     * Drops the tables and removes the settings.
     *
     * @param SchemaSetupInterface $setup Schema installer.
     * @param ModuleContextInterface $context Module context.
     * @return void
     */
    public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();
        $connection = $setup->getConnection();
        $connection->dropTable($setup->getTable(DeliveryQueue::TABLE));
        $connection->dropTable($setup->getTable(CheckoutFieldValue::TABLE));
        foreach ([...Settings::PATHS, CheckoutFields::PATH_DEFINITIONS] as $path) {
            $connection->delete($setup->getTable('core_config_data'), $connection->quoteInto('path = ?', $path));
        }
        $setup->endSetup();
    }
}
