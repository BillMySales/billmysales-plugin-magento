<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

namespace BillMySales\Magento\Model\Config\Backend;

use BillMySales\Magento\Checkout\CheckoutFields as CheckoutFieldsHelper;
use Magento\Framework\App\Config\Value;

/**
 * Validates and normalizes the checkout field definitions
 * (etc/adminhtml/system.xml's backend_model) before they're saved: rows
 * without a label are dropped, and the key is always derived from the
 * label.
 */
class CheckoutFields extends Value
{
    /**
     * Sanitizes the value before it's saved.
     *
     * @return $this
     */
    public function beforeSave()
    {
        $this->setValue(CheckoutFieldsHelper::sanitize((string) $this->getValue()));
        return parent::beforeSave();
    }
}
