<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

namespace BillMySales\Magento\Observer;

use BillMySales\Magento\Settings;
use BillMySales\Magento\Webhook\Delivery;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Event\ObserverInterface;
use Magento\Sales\Model\Order;

/**
 * Queues a delivery when an order's status reaches one selected in the
 * settings (etc/events.xml: sales_order_save_commit_after, fired only
 * after the order's database transaction actually commits).
 */
class QueueOnStatusChange implements ObserverInterface
{
    /**
     * Reads the current settings.
     *
     * @var Settings
     */
    private Settings $settings;

    /**
     * Queues and sends deliveries.
     *
     * @var Delivery
     */
    private Delivery $delivery;

    /**
     * @param Settings $settings Reads the current settings.
     * @param Delivery $delivery Queues and sends deliveries.
     */
    public function __construct(Settings $settings, Delivery $delivery)
    {
        $this->settings = $settings;
        $this->delivery = $delivery;
    }

    /**
     * Queues a delivery when the order's new status is selected.
     *
     * @param EventObserver $observer The dispatched event.
     * @return void
     */
    public function execute(EventObserver $observer): void
    {
        /** @var Order $order */
        $order = $observer->getEvent()->getOrder();
        $status = (string) $order->getStatus();

        if ((string) $order->getOrigData('status') === $status) {
            return;
        }

        if (!Settings::notifies($this->settings->get(), $status)) {
            return;
        }

        $this->delivery->enqueue((int) $order->getEntityId(), 'order.status_changed');
    }
}
