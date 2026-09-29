/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

/**
 * Settings page: shows or hides the secret field a button is bound to
 * (data-mage-init='{"billmysalesSecretToggle": {"target": "#..."}}').
 */
define([], function () {
    'use strict';

    /**
     * @param {{target: string}} config Widget config: the field's CSS selector.
     * @param {Element} button The button this instance is bound to.
     * @return {void}
     */
    return function (config, button) {
        var field = document.querySelector(config.target);
        if (!field) {
            return;
        }
        button.addEventListener('click', function () {
            field.type = field.type === 'password' ? 'text' : 'password';
        });
    };
});
