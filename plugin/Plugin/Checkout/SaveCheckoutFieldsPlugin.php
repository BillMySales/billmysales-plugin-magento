<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

namespace BillMySales\Magento\Plugin\Checkout;

use BillMySales\Magento\Checkout\CheckoutFields;
use BillMySales\Magento\Model\ResourceModel\CheckoutFieldValue;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Framework\Exception\LocalizedException;

/**
 * Stores the admin-defined checkout fields submitted with the shipping
 * information, keyed by the quote: the same method registered guest
 * checkout delegates to (Magento\Checkout\Model\GuestShippingInformationManagement),
 * with the cart's real (unmasked) id already resolved. The fields
 * themselves (Plugin\Checkout\LayoutProcessorPlugin adds them to the
 * shipping address form) reach here as one JSON-encoded object, in the
 * extension attribute view/frontend/web/js/collect-checkout-fields.js
 * combines them into (etc/extension_attributes.xml). A required field
 * missing or empty refuses the whole address save (the checkout's own
 * client-side validation only stops a real browser, not a direct API
 * call), naming the field.
 */
class SaveCheckoutFieldsPlugin
{
    /**
     * The admin-defined checkout fields.
     *
     * @var CheckoutFields
     */
    private CheckoutFields $checkoutFields;

    /**
     * Stores the checkout fields submitted with a quote.
     *
     * @var CheckoutFieldValue
     */
    private CheckoutFieldValue $checkoutFieldValue;

    /**
     * @param CheckoutFields $checkoutFields The admin-defined checkout fields.
     * @param CheckoutFieldValue $checkoutFieldValue Stores the checkout fields submitted with a quote.
     */
    public function __construct(CheckoutFields $checkoutFields, CheckoutFieldValue $checkoutFieldValue)
    {
        $this->checkoutFields = $checkoutFields;
        $this->checkoutFieldValue = $checkoutFieldValue;
    }

    /**
     * Stores every configured field with a non-empty submitted value.
     *
     * @param ShippingInformationManagement $subject The service being extended.
     * @param int $cartId Quote id.
     * @param ShippingInformationInterface $addressInformation Submitted shipping information.
     * @return void
     * @throws LocalizedException A required field is missing or empty.
     */
    public function beforeSaveAddressInformation(// phpcs:ignore
        ShippingInformationManagement $subject,
        int $cartId,
        ShippingInformationInterface $addressInformation
    ): void {
        $fields = $this->checkoutFields->get();
        if (empty($fields)) {
            return;
        }

        $extensionAttributes = $addressInformation->getExtensionAttributes();
        $json = null !== $extensionAttributes ? $extensionAttributes->getBillmysalesCheckoutFields() : null;
        $submitted = null !== $json ? json_decode($json, true) : null;
        $submitted = is_array($submitted) ? $submitted : [];

        foreach ($fields as $field) {
            $value = isset($submitted[$field['key']]) ? trim((string) $submitted[$field['key']]) : '';
            if ('' === $value) {
                if ($field['required']) {
                    throw new LocalizedException(__('"%1" is a required field.', $field['label']));
                }
                continue;
            }
            $this->checkoutFieldValue->save($cartId, $field['key'], $value);
        }
    }
}
