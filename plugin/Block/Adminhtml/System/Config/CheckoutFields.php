<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

namespace BillMySales\Magento\Block\Adminhtml\System\Config;

use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;

/**
 * Renders the field definitions (JSON) as a hidden input plus a row editor
 * (label, values, required, add/remove row), added to the checkout and
 * sent with the order: the field itself stays a plain hidden input (the
 * form submits whatever JSON view/adminhtml/web/js/checkout-fields-editor.js
 * keeps it in sync with), so no admin grid/listing is needed for
 * something this small.
 */
class CheckoutFields extends Field
{
    /**
     * Renders the field as a hidden input plus the row editor's mount point.
     *
     * @param AbstractElement $element The field definitions (JSON).
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $element->setClass(trim($element->getClass() . ' billmysales-checkout-fields-input'));

        $config = [
            'input' => '#' . $element->getHtmlId(),
            'labels' => [
                'label' => (string) __('Label'),
                'values' => (string) __('Values, comma-separated (empty: free text)'),
                'required' => (string) __('Required'),
                'add' => (string) __('+ Add field'),
            ],
        ];

        // A textarea element has no "type" attribute to switch to "hidden"
        // (setType() has no effect on it): hidden with a wrapper instead.
        $html = '<div style="display:none">' . $element->getElementHtml() . '</div>';
        $html .= '<div class="billmysales-checkout-fields-editor"';
        $html .= ' data-mage-init=\'{"billmysalesCheckoutFields": '
            . (string) json_encode($config, JSON_HEX_APOS) . '}\'>';
        $html .= '</div>';

        return $html;
    }
}
