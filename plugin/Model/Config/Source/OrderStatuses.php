<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

namespace BillMySales\Magento\Model\Config\Source;

use Magento\Framework\Option\ArrayInterface;
use Magento\Sales\Model\Order\Config as OrderConfig;

/**
 * Lists every order status configured in the store, for the settings
 * page's "order statuses that notify" multiselect.
 */
class OrderStatuses implements ArrayInterface
{
    /**
     * Order statuses and states configuration.
     *
     * @var OrderConfig
     */
    private OrderConfig $orderConfig;

    /**
     * @param OrderConfig $orderConfig Order statuses and states configuration.
     */
    public function __construct(OrderConfig $orderConfig)
    {
        $this->orderConfig = $orderConfig;
    }

    /**
     * Every configured order status, as {value, label} options.
     *
     * @return array<int,array{value:string,label:string}>
     */
    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->orderConfig->getStatuses() as $code => $label) {
            $options[] = ['value' => $code, 'label' => $label];
        }
        return $options;
    }
}
