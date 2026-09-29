<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

namespace BillMySales\Magento\Controller\Adminhtml\Order;

use BillMySales\Magento\Webhook\Delivery;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;

/**
 * "Send to BillMySales" (the order view page's own button, added by
 * view/adminhtml/layout/sales_order_view.xml): queues a new delivery for
 * the order, whatever the statuses selected in the settings.
 */
class Resend extends Action implements HttpGetActionInterface
{
    /**
     * ACL resource this controller needs (etc/acl.xml).
     */
    public const ADMIN_RESOURCE = 'BillMySales_Magento::resend';

    /**
     * Queues and sends deliveries.
     *
     * @var Delivery
     */
    private Delivery $delivery;

    /**
     * @param Context $context Action context.
     * @param Delivery $delivery Queues and sends deliveries.
     */
    public function __construct(Context $context, Delivery $delivery)
    {
        parent::__construct($context);
        $this->delivery = $delivery;
    }

    /**
     * Queues a new delivery and redirects back to the order.
     *
     * @return Redirect
     */
    public function execute()
    {
        $orderId = (int) $this->getRequest()->getParam('order_id');
        $this->delivery->enqueue($orderId, 'order.resent');
        $this->messageManager->addSuccessMessage(__('BillMySales: sending requested.'));

        /** @var Redirect $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_REDIRECT);
        return $result->setPath('sales/order/view', ['order_id' => $orderId]);
    }
}
