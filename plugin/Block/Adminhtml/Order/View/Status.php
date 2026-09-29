<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

namespace BillMySales\Magento\Block\Adminhtml\Order\View;

use BillMySales\Magento\Settings;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Sales\Model\Order;

/**
 * The order view page's "BillMySales" block (order_additional_info
 * container, view/adminhtml/layout/sales_order_view.xml): a "Send to
 * BillMySales" link when deliveries are configured. Each delivery
 * attempt's own status is a private note in the order's native "Notes
 * History" tab instead (Webhook\Delivery), not rendered here.
 */
class Status extends Template
{
    /**
     * Magento's object registry (the current order).
     *
     * @var Registry
     */
    private Registry $registry;

    /**
     * Reads the current settings.
     *
     * @var Settings
     */
    private Settings $settings;

    /**
     * Constructor.
     *
     * @param Context $context Block context.
     * @param Registry $registry Magento's object registry (the current order).
     * @param Settings $settings Reads the current settings.
     * @param array<string,mixed> $data Block data.
     */
    public function __construct(// phpcs:ignore Magento2.Annotation.MethodArguments.ArgumentMissing
        Context $context,
        Registry $registry,
        Settings $settings,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->registry = $registry;
        $this->settings = $settings;
    }

    /**
     * The order being viewed.
     *
     * @return Order
     */
    public function getOrder(): Order
    {
        return $this->registry->registry('current_order');
    }

    /**
     * Whether deliveries are configured.
     *
     * So "Send to BillMySales" can be offered.
     *
     * @return bool
     */
    public function canResend(): bool
    {
        return Settings::isReady($this->settings->get());
    }

    /**
     * The URL of the "Send to BillMySales" action.
     *
     * @return string
     */
    public function getResendUrl(): string
    {
        return $this->getUrl('billmysales/order/resend', ['order_id' => $this->getOrder()->getEntityId()]);
    }
}
