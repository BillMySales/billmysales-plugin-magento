/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

/**
 * Mixin of Magento_Checkout/js/model/shipping-save-processor/payload-extender:
 * combines the admin-defined checkout fields (Plugin\Checkout\LayoutProcessorPlugin
 * adds them to the shipping address form, each bound to its own scratch
 * data scope, shippingAddress.billmysales_fields.*, not a real address
 * attribute) into one JSON object, in the extension attribute
 * etc/extension_attributes.xml declares on the shipping information being
 * saved.
 */
define(['uiRegistry'], function (registry) {
    'use strict';

    /**
     * @return {Object<string, string>} Every non-empty field, by key.
     */
    function collect() {
        var checkoutProvider = registry.get('checkoutProvider'),
            shippingAddress = checkoutProvider ? checkoutProvider.get('shippingAddress') : null,
            fields = shippingAddress ? shippingAddress.billmysales_fields : null,
            values = {};

        if (!fields) {
            return values;
        }
        Object.keys(fields).forEach(function (key) {
            var value = fields[key];

            if (value !== '' && value !== null && typeof value !== 'undefined') {
                values[key] = value;
            }
        });

        return values;
    }

    return function (payloadExtender) {
        /**
         * @param {Object} payload The shipping-information payload being built.
         * @return {Object} The payload, with the fields combined into it.
         */
        return function (payload) {
            payload = payloadExtender(payload);
            payload.addressInformation.extension_attributes = payload.addressInformation.extension_attributes || {};
            payload.addressInformation.extension_attributes.billmysales_checkout_fields
                = JSON.stringify(collect());

            return payload;
        };
    };
});
