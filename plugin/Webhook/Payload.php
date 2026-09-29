<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

namespace BillMySales\Magento\Webhook;

use BillMySales\Magento\Model\ResourceModel\CheckoutFieldValue;
use Magento\Framework\Reflection\DataObjectProcessor;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;

/**
 * Builds the payload: the order's own official representation (the same
 * fields, in the same shape, Magento's REST API (GET /V1/orders/<id>)
 * would return for it), built through DataObjectProcessor, the class
 * Magento's own webapi layer uses to serialize a data interface object
 * (Magento\WebapiAsync\Controller\Rest\AsynchronousRequestProcessor, for
 * one), rather than a hand-picked list of fields that could drift from it.
 * The shipping address isn't part of OrderInterface (only the billing one
 * is): added the same way, from the order's own getShippingAddress(). The
 * admin-defined checkout fields (Checkout\CheckoutFields), submitted with
 * the order's own quote, are added as "checkout_fields".
 */
class Payload
{
    /**
     * Serializes a data interface object the way Magento's webapi layer
     * does.
     *
     * @var DataObjectProcessor
     */
    private DataObjectProcessor $dataObjectProcessor;

    /**
     * The checkout fields submitted with a quote.
     *
     * @var CheckoutFieldValue
     */
    private CheckoutFieldValue $checkoutFieldValue;

    /**
     * @param DataObjectProcessor $dataObjectProcessor Serializes a data interface object.
     * @param CheckoutFieldValue $checkoutFieldValue The checkout fields submitted with a quote.
     */
    public function __construct(DataObjectProcessor $dataObjectProcessor, CheckoutFieldValue $checkoutFieldValue)
    {
        $this->dataObjectProcessor = $dataObjectProcessor;
        $this->checkoutFieldValue = $checkoutFieldValue;
    }

    /**
     * Builds the payload of an order.
     *
     * @param Order $order Order.
     * @return array<string,mixed>
     */
    public function build(Order $order): array
    {
        $payload = $this->dataObjectProcessor->buildOutputDataArray($order, OrderInterface::class);

        $shippingAddress = $order->getShippingAddress();
        $payload['shipping_address'] = $shippingAddress instanceof OrderAddressInterface
            ? $this->dataObjectProcessor->buildOutputDataArray($shippingAddress, OrderAddressInterface::class)
            : null;

        $quoteId = $order->getQuoteId();
        $payload['checkout_fields'] = null !== $quoteId
            ? $this->checkoutFieldValue->getByQuoteId((int) $quoteId)
            : [];

        return $payload;
    }
}
