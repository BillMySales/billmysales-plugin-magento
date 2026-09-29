/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

/**
 * Settings page: the checkout fields editor
 * (data-mage-init='{"billmysalesCheckoutFields": {"input": "#..."}}'),
 * a row per field (label, values, required, remove) plus an "add field"
 * button, kept in sync with the hidden input the form actually submits.
 * The field's key isn't edited here: it's always derived from the label
 * when the settings are saved (Checkout\CheckoutFields::sanitize()).
 */
define([], function () {
    'use strict';

    /**
     * @param {string} text Text to escape.
     * @return {string} The text, safe to use in an HTML attribute.
     */
    function escapeHtml(text) {
        var div = document.createElement('div');

        div.textContent = text;

        return div.innerHTML.replace(/"/g, '&quot;');
    }

    /**
     * @param {{label: string, values: string[], required: boolean}} field A row's field.
     * @param {{label: string, values: string, required: string}} labels The editor's own labels (translated).
     * @return {HTMLDivElement} The row's element.
     */
    function buildRow(field, labels) {
        var row = document.createElement('div');

        row.className = 'billmysales-checkout-fields-row';
        row.innerHTML
            = '<input type="text" class="billmysales-checkout-fields-label" placeholder="'
                + escapeHtml(labels.label) + '" value="' + escapeHtml(field.label || '') + '">'
                + '<input type="text" class="billmysales-checkout-fields-values" placeholder="'
                + escapeHtml(labels.values) + '" value="'
                + escapeHtml((field.values || []).join(', ')) + '">'
                + '<label><input type="checkbox" class="billmysales-checkout-fields-required"'
                + (field.required ? ' checked' : '') + '> ' + escapeHtml(labels.required) + '</label>'
                + '<button type="button" class="action-remove billmysales-checkout-fields-remove">&times;</button>';

        return row;
    }

    /**
     * @param {HTMLElement} container Rows container.
     * @return {{label: string, values: string[], required: boolean}[]} The edited fields.
     */
    function readRows(container) {
        return Array.prototype.map.call(container.children, function (row) {
            var values = row.querySelector('.billmysales-checkout-fields-values').value;

            return {
                label: row.querySelector('.billmysales-checkout-fields-label').value,
                values: values === ''
                    ? []
                    : values.split(',').map(function (value) {
                            return value.trim();
                        }),
                required: row.querySelector('.billmysales-checkout-fields-required').checked,
            };
        }).filter(function (field) {
            return field.label !== '';
        });
    }

    /**
     * @param {{input: string, labels: {label: string, values: string, required: string, add: string}}} config
     *     Widget config: the hidden input's CSS selector and the editor's own labels (translated).
     * @param {Element} element The container this instance is bound to.
     * @return {void}
     */
    return function (config, element) {
        var input = document.querySelector(config.input),
            rows,
            addButton,
            fields;

        if (!input) {
            return;
        }

        try {
            fields = JSON.parse(input.value || '[]');
        } catch (error) {
            console.error('BillMySales: invalid checkout fields JSON.', error);
            fields = [];
        }
        if (!Array.isArray(fields)) {
            fields = [];
        }

        rows = document.createElement('div');
        addButton = document.createElement('button');
        addButton.type = 'button';
        addButton.className = 'action-default billmysales-checkout-fields-add';
        addButton.textContent = config.labels.add;

        /**
         * @return {void}
         */
        function sync() {
            input.value = JSON.stringify(readRows(rows));
        }

        /**
         * @param {{label: string, values: string[], required: boolean}} field A row's field.
         * @return {void}
         */
        function addRow(field) {
            var row = buildRow(field, config.labels);

            row.addEventListener('input', sync);
            row.addEventListener('change', sync);
            row.querySelector('.billmysales-checkout-fields-remove').addEventListener('click', function () {
                row.remove();
                sync();
            });
            rows.appendChild(row);
        }

        fields.forEach(addRow);
        addButton.addEventListener('click', function () {
            addRow({ label: '', values: [], required: false });
        });

        element.appendChild(rows);
        element.appendChild(addButton);
    };
});
