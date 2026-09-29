<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

/**
 * End-to-end tests: a small CLI, bootstrapping Magento itself (like
 * bin/magento does), for the setup steps Magento has no dedicated CLI
 * command for (a simple product, an order's status change through its
 * real repository save, so BillMySales_Magento's own observer fires).
 * Adding to cart, the address and checkout fields, and placing the order
 * go through the same REST endpoints the checkout's own JavaScript calls
 * instead, exactly as a shopper would (run.sh). Run inside the stack's
 * "magento" or "cron" container, from the Magento root (bin/magento's own
 * directory). Prints one plain-text result line per command and exits
 * non-zero on failure.
 *
 * Usage: php e2e-fixtures.php <command> [args...]
 */

require 'app/bootstrap.php';

use BillMySales\Magento\Setup\Uninstall;
use Magento\Catalog\Api\Data\ProductInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Bootstrap;
use Magento\Framework\App\State;
use Magento\Framework\Registry;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Setup\Model\ModuleContext;

$bootstrap = Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$objectManager->get(State::class)->setAreaCode('adminhtml');
// Product and order writes are otherwise refused outside a real admin
// request (Magento\Framework\Model\AbstractModel::beforeDelete() and the
// product repository's own save flow both check it).
$objectManager->get(Registry::class)->register('isSecureArea', true);

/**
 * Creates a simple, in-stock, website-visible product. Safe to repeat
 * (updates the existing one instead of failing on a duplicate SKU).
 *
 * @param string $sku   SKU.
 * @param string $price Price.
 * @return void
 */
function billmysales_e2e_create_product(string $sku, string $price): void
{
    global $objectManager;

    $productRepository = $objectManager->get(ProductRepositoryInterface::class);
    try {
        $product = $productRepository->get($sku);
    } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
        $product = $objectManager->get(ProductInterfaceFactory::class)->create();
        $product->setTypeId('simple')->setAttributeSetId(4)->setSku($sku);
    }
    $product->setName('BillMySales E2E Product')->setPrice((float) $price)->setWeight(1)
        ->setVisibility(4)->setStatus(1)->setWebsiteIds([1])
        ->setStockData(['use_config_manage_stock' => 1, 'qty' => 100, 'is_in_stock' => 1]);
    $objectManager->get(ProductRepositoryInterface::class)->save($product);
    echo "ok\n";
}

/**
 * Sets an order's status and state through its real repository save (so
 * BillMySales_Magento's own observer, bound to the save event, fires
 * exactly as it does for a real order).
 *
 * @param string $entityId Order id (entity_id).
 * @param string $status   New status (e.g. "processing").
 * @param string $state    New state (e.g. "processing"; blank keeps it).
 * @return void
 */
function billmysales_e2e_set_order_status(string $entityId, string $status, string $state = ''): void
{
    global $objectManager;

    $orderRepository = $objectManager->get(OrderRepositoryInterface::class);
    $order = $orderRepository->get((int) $entityId);
    $order->setStatus($status);
    if ('' !== $state) {
        $order->setState($state);
    }
    $orderRepository->save($order);
    echo "ok\n";
}

/**
 * Prints one field of an order (as Magento's Order model holds it, e.g.
 * status, state, grand_total, quote_id).
 *
 * @param string $entityId Order id (entity_id).
 * @param string $field    Property name.
 * @return void
 */
function billmysales_e2e_get_order_field(string $entityId, string $field): void
{
    global $objectManager;

    $order = $objectManager->get(OrderRepositoryInterface::class)->get((int) $entityId);
    echo $order->getData($field) . "\n";
}

/**
 * Runs the module's own scheduled job once
 * (BillMySales\Magento\Cron\ProcessDueDeliveries), instead of waiting for
 * Magento's cron to pick it up (which itself needs an earlier run to have
 * scheduled it: there is no single built-in command to run one job now).
 *
 * @return void
 */
function billmysales_e2e_run_queue(): void
{
    global $objectManager;

    $objectManager->get(\BillMySales\Magento\Cron\ProcessDueDeliveries::class)->execute();
    echo "ok\n";
}

/**
 * Runs the module's own Setup\Uninstall (drops its tables, removes its
 * settings): what `bin/magento module:uninstall --remove-data` runs
 * internally, called directly because that command only proceeds for a
 * module composer installed (refusing, with no error, one placed in
 * app/code directly; `--non-composer` skips this entirely, reverting only
 * data patches). run.sh disables the module, removes its code and
 * re-runs setup:upgrade itself, exactly as the real command does around
 * this same call.
 *
 * @return void
 */
function billmysales_e2e_uninstall_module(): void
{
    global $objectManager;

    $setup = $objectManager->create(SchemaSetupInterface::class);
    $context = new ModuleContext('');
    (new Uninstall())->uninstall($setup, $context);
    echo "ok\n";
}

$argv = $_SERVER['argv'] ?? [];
$command = $argv[1] ?? '';
$args = array_slice($argv, 2);

switch ($command) {
    case 'create-product':
        billmysales_e2e_create_product($args[0] ?? 'e2e-product', $args[1] ?? '9990');
        break;
    case 'set-order-status':
        billmysales_e2e_set_order_status($args[0] ?? '0', $args[1] ?? '', $args[2] ?? '');
        break;
    case 'get-order-field':
        billmysales_e2e_get_order_field($args[0] ?? '0', $args[1] ?? 'status');
        break;
    case 'run-queue':
        billmysales_e2e_run_queue();
        break;
    case 'uninstall-module':
        billmysales_e2e_uninstall_module();
        break;
    default:
        fwrite(STDERR, "unknown command: {$command}\n");
        exit(1);
}
