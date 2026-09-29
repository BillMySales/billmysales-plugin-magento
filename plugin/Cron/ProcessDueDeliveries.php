<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

namespace BillMySales\Magento\Cron;

use BillMySales\Magento\Webhook\Delivery;

/**
 * The scheduled job (etc/crontab.xml) that sends the orders queued for
 * BillMySales.
 */
class ProcessDueDeliveries
{
    /**
     * Queues and sends deliveries.
     *
     * @var Delivery
     */
    private Delivery $delivery;

    /**
     * @param Delivery $delivery Queues and sends deliveries.
     */
    public function __construct(Delivery $delivery)
    {
        $this->delivery = $delivery;
    }

    /**
     * Sends every delivery due by now.
     *
     * @return void
     */
    public function execute(): void
    {
        $this->delivery->processDue();
    }
}
