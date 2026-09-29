<?php

declare(strict_types=1);

/**
 * Tests for CheckoutFields.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Block\Adminhtml\System\Config;

use BillMySales\Magento\Block\Adminhtml\System\Config\CheckoutFields;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(CheckoutFields::class)]
/**
 * CheckoutFieldsTest.
 */
final class CheckoutFieldsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testRendersAHiddenInputWithTheEditorsMountPoint(): void
    {
        $element = Mockery::mock(AbstractElement::class);
        $element->shouldReceive('getClass')->once()->andReturn('textarea');
        $element->shouldReceive('setClass')->once()->with('textarea billmysales-checkout-fields-input');
        $element->shouldReceive('getElementHtml')->once()->andReturn('<textarea id="billmysales_checkout_fields_definitions"></textarea>');
        $element->shouldReceive('getHtmlId')->once()->andReturn('billmysales_checkout_fields_definitions');

        $context = Mockery::mock(Context::class)->shouldIgnoreMissing();
        $block = new CheckoutFields($context);

        $method = new ReflectionMethod(CheckoutFields::class, '_getElementHtml');
        $html = $method->invoke($block, $element);

        self::assertStringContainsString(
            '<div style="display:none"><textarea id="billmysales_checkout_fields_definitions"></textarea></div>',
            $html
        );
        self::assertStringContainsString('billmysalesCheckoutFields', $html);
        self::assertStringContainsString('#billmysales_checkout_fields_definitions', $html);
    }
}
