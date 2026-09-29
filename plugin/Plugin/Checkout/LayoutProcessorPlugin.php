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
use Magento\Checkout\Block\Checkout\LayoutProcessor;

/**
 * Adds the admin-defined checkout fields to the shipping address form, each
 * bound to its own scratch data scope (shippingAddress.billmysales_fields.*,
 * not a real address attribute: Magento\Quote\Model\Quote\Address only
 * carries a custom_attributes code that is also a real customer_address EAV
 * attribute, which these dynamic, admin-defined fields aren't).
 * view/frontend/web/js/collect-checkout-fields.js (a payload-extender
 * mixin) reads them from there and combines them into the one
 * extension attribute etc/extension_attributes.xml declares.
 */
class LayoutProcessorPlugin
{
    /**
     * The admin-defined checkout fields.
     *
     * @var CheckoutFields
     */
    private CheckoutFields $checkoutFields;

    /**
     * @param CheckoutFields $checkoutFields The admin-defined checkout fields.
     */
    public function __construct(CheckoutFields $checkoutFields)
    {
        $this->checkoutFields = $checkoutFields;
    }

    /**
     * Adds the fields to the shipping address form's own fieldset.
     *
     * @param LayoutProcessor $subject The checkout's own layout processor.
     * @param array<string,mixed> $result Its own result.
     * @return array<string,mixed>
     */
    public function afterProcess(LayoutProcessor $subject, array $result): array // phpcs:ignore
    {
        $fields = $this->checkoutFields->get();
        if (empty($fields)) {
            return $result;
        }

        if (!isset(
            $result['components']['checkout']['children']['steps']['children']['shipping-step']
            ['children']['shippingAddress']['children']['shipping-address-fieldset']['children']
        )) {
            return $result;
        }

        $fieldset = &$result['components']['checkout']['children']['steps']['children']['shipping-step']
            ['children']['shippingAddress']['children']['shipping-address-fieldset']['children'];
        foreach ($fields as $sortOrder => $field) {
            $fieldset[$field['key']] = $this->fieldConfig($field, $sortOrder);
        }
        return $result;
    }

    /**
     * The knockout UI component configuration of one field, in the same
     * shape Magento's own AttributeMerger builds for a customer address
     * attribute (Magento\Checkout\Block\Checkout\AttributeMerger::getFieldConfig()).
     *
     * @param array{key:string,label:string,values:string[],required:bool} $field Field definition.
     * @param int $sortOrder Field's position among the others.
     * @return array<string,mixed>
     */
    private function fieldConfig(array $field, int $sortOrder): array
    {
        $isSelect = !empty($field['values']);
        return [
            'component' => $isSelect ? 'Magento_Ui/js/form/element/select' : 'Magento_Ui/js/form/element/abstract',
            'config' => [
                'customScope' => 'shippingAddress',
                'template' => 'ui/form/field',
                'elementTmpl' => $isSelect ? 'ui/form/element/select' : 'ui/form/element/input',
            ],
            'dataScope' => 'shippingAddress.billmysales_fields.' . $field['key'],
            'label' => $field['label'],
            'provider' => 'checkoutProvider',
            'sortOrder' => $sortOrder,
            'validation' => ['required-entry' => $field['required']],
            'options' => $isSelect ? $this->options($field['values']) : [],
            'visible' => true,
        ];
    }

    /**
     * A select field's options, with a blank one first (no value selected yet).
     *
     * @param string[] $values Field's own values.
     * @return array<int,array{value:string,label:string}>
     */
    private function options(array $values): array
    {
        $options = [['value' => '', 'label' => '']];
        foreach ($values as $value) {
            $options[] = ['value' => $value, 'label' => $value];
        }
        return $options;
    }
}
