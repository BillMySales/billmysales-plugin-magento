<?php

declare(strict_types=1);

/**
 * Tests for LayoutProcessorPlugin.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Plugin\Checkout;

use BillMySales\Magento\Checkout\CheckoutFields;
use BillMySales\Magento\Plugin\Checkout\LayoutProcessorPlugin;
use Magento\Checkout\Block\Checkout\LayoutProcessor;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(LayoutProcessorPlugin::class)]
/**
 * LayoutProcessorPluginTest.
 */
final class LayoutProcessorPluginTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * A jsLayout with the shipping address fieldset present, with the
     * given existing children.
     *
     * @param array<string,mixed> $children Existing fieldset children.
     * @return array<string,mixed>
     */
    private static function jsLayout(array $children = []): array
    {
        return [
            'components' => [
                'checkout' => [
                    'children' => [
                        'steps' => [
                            'children' => [
                                'shipping-step' => [
                                    'children' => [
                                        'shippingAddress' => [
                                            'children' => [
                                                'shipping-address-fieldset' => [
                                                    'children' => $children,
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return void
     */
    public function testReturnsResultUnchangedWithNoFieldsConfigured(): void
    {
        $checkoutFields = Mockery::mock(CheckoutFields::class);
        $checkoutFields->shouldReceive('get')->once()->andReturn([]);

        $subject = Mockery::mock(LayoutProcessor::class);
        $result = self::jsLayout();

        self::assertSame($result, (new LayoutProcessorPlugin($checkoutFields))->afterProcess($subject, $result));
    }

    /**
     * @return void
     */
    public function testReturnsResultUnchangedWhenTheFieldsetIsMissing(): void
    {
        $checkoutFields = Mockery::mock(CheckoutFields::class);
        $checkoutFields->shouldReceive('get')->once()->andReturn([
            ['key' => 'rut', 'label' => 'RUT', 'values' => [], 'required' => true],
        ]);

        $subject = Mockery::mock(LayoutProcessor::class);
        $result = ['components' => []];

        self::assertSame($result, (new LayoutProcessorPlugin($checkoutFields))->afterProcess($subject, $result));
    }

    /**
     * @return void
     */
    public function testAddsATextFieldToTheFieldset(): void
    {
        $checkoutFields = Mockery::mock(CheckoutFields::class);
        $checkoutFields->shouldReceive('get')->once()->andReturn([
            ['key' => 'rut', 'label' => 'RUT', 'values' => [], 'required' => true],
        ]);

        $subject = Mockery::mock(LayoutProcessor::class);
        $result = self::jsLayout(['company' => ['label' => 'Company']]);

        $processed = (new LayoutProcessorPlugin($checkoutFields))->afterProcess($subject, $result);

        $fieldset = $processed['components']['checkout']['children']['steps']['children']['shipping-step']
            ['children']['shippingAddress']['children']['shipping-address-fieldset']['children'];

        self::assertArrayHasKey('company', $fieldset);
        self::assertSame([
            'component' => 'Magento_Ui/js/form/element/abstract',
            'config' => [
                'customScope' => 'shippingAddress',
                'template' => 'ui/form/field',
                'elementTmpl' => 'ui/form/element/input',
            ],
            'dataScope' => 'shippingAddress.billmysales_fields.rut',
            'label' => 'RUT',
            'provider' => 'checkoutProvider',
            'sortOrder' => 0,
            'validation' => ['required-entry' => true],
            'options' => [],
            'visible' => true,
        ], $fieldset['rut']);
    }

    /**
     * @return void
     */
    public function testAddsASelectFieldWithABlankFirstOption(): void
    {
        $checkoutFields = Mockery::mock(CheckoutFields::class);
        $checkoutFields->shouldReceive('get')->once()->andReturn([
            ['key' => 'doc_type', 'label' => 'Document type', 'values' => ['Receipt', 'Invoice'], 'required' => false],
        ]);

        $subject = Mockery::mock(LayoutProcessor::class);
        $result = self::jsLayout();

        $processed = (new LayoutProcessorPlugin($checkoutFields))->afterProcess($subject, $result);

        $field = $processed['components']['checkout']['children']['steps']['children']['shipping-step']
            ['children']['shippingAddress']['children']['shipping-address-fieldset']['children']['doc_type'];

        self::assertSame('Magento_Ui/js/form/element/select', $field['component']);
        self::assertSame('ui/form/element/select', $field['config']['elementTmpl']);
        self::assertSame([
            ['value' => '', 'label' => ''],
            ['value' => 'Receipt', 'label' => 'Receipt'],
            ['value' => 'Invoice', 'label' => 'Invoice'],
        ], $field['options']);
    }
}
