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

use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Renders the secret field as a password input with a show/hide button
 * (Magento's own "obscure" field type has no such button; the field stays
 * a plain HTML password input, kept out of the page's HTML in cleartext,
 * with plugin/view/adminhtml/web/js/secret-toggle.js adding the button).
 * The stored value is the backend_model's own encrypted ciphertext, not
 * the secret itself: decrypted here, so "Show" reveals the real secret,
 * not the ciphertext a plain _getElementHtml() would put in the page.
 */
class Secret extends Field
{
    /**
     * Decrypts the stored value.
     *
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @param Context $context Block context.
     * @param EncryptorInterface $encryptor Decrypts the stored value.
     * @param array<string,mixed> $data Block data.
     */
    public function __construct(Context $context, EncryptorInterface $encryptor, array $data = [])
    {
        parent::__construct($context, $data);
        $this->encryptor = $encryptor;
    }

    /**
     * Renders the field as a password input plus a show/hide button.
     *
     * @param AbstractElement $element The secret field.
     * @return string
     */
    protected function _getElementHtml(AbstractElement $element): string
    {
        $stored = (string) $element->getValue();
        if ('' !== $stored) {
            $element->setValue($this->encryptor->decrypt($stored));
        }
        $element->setType('password');
        $element->setClass(trim($element->getClass() . ' billmysales-secret-field'));
        $element->setData('autocomplete', 'off');

        $html = $element->getElementHtml();
        $html .= '<button type="button" class="action-default billmysales-secret-toggle"';
        $html .= ' data-mage-init=\'{"billmysalesSecretToggle": {"target": "#' . $element->getHtmlId() . '"}}\'>';
        $html .= __('Show');
        $html .= '</button>';

        return $html;
    }
}
