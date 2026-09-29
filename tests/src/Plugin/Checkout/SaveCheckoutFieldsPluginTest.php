<?php

declare(strict_types=1);

/**
 * Tests for SaveCheckoutFieldsPlugin.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Plugin\Checkout;

use BillMySales\Magento\Checkout\CheckoutFields;
use BillMySales\Magento\Model\ResourceModel\CheckoutFieldValue;
use BillMySales\Magento\Plugin\Checkout\SaveCheckoutFieldsPlugin;
use Magento\Checkout\Api\Data\ShippingInformationExtensionInterface;
use Magento\Checkout\Api\Data\ShippingInformationInterface;
use Magento\Checkout\Model\ShippingInformationManagement;
use Magento\Framework\Exception\LocalizedException;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(SaveCheckoutFieldsPlugin::class)]
/**
 * SaveCheckoutFieldsPluginTest.
 */
final class SaveCheckoutFieldsPluginTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testDoesNothingWithNoFieldsConfigured(): void
    {
        $checkoutFields = Mockery::mock(CheckoutFields::class);
        $checkoutFields->shouldReceive('get')->once()->andReturn([]);
        $checkoutFieldValue = Mockery::mock(CheckoutFieldValue::class);
        $checkoutFieldValue->shouldNotReceive('save');

        $subject = Mockery::mock(ShippingInformationManagement::class);
        $addressInformation = Mockery::mock(ShippingInformationInterface::class);
        $addressInformation->shouldNotReceive('getExtensionAttributes');

        (new SaveCheckoutFieldsPlugin($checkoutFields, $checkoutFieldValue))
            ->beforeSaveAddressInformation($subject, 7, $addressInformation);
    }

    /**
     * @return void
     */
    public function testRefusesAMissingRequiredFieldWithNoExtensionAttributes(): void
    {
        $checkoutFields = Mockery::mock(CheckoutFields::class);
        $checkoutFields->shouldReceive('get')->once()->andReturn([
            ['key' => 'rut', 'label' => 'RUT', 'values' => [], 'required' => true],
        ]);
        $checkoutFieldValue = Mockery::mock(CheckoutFieldValue::class);
        $checkoutFieldValue->shouldNotReceive('save');

        $subject = Mockery::mock(ShippingInformationManagement::class);
        $addressInformation = Mockery::mock(ShippingInformationInterface::class);
        $addressInformation->shouldReceive('getExtensionAttributes')->once()->andReturn(null);

        $this->expectException(LocalizedException::class);
        (new SaveCheckoutFieldsPlugin($checkoutFields, $checkoutFieldValue))
            ->beforeSaveAddressInformation($subject, 7, $addressInformation);
    }

    /**
     * @return void
     */
    public function testRefusesAMissingRequiredFieldWithInvalidJson(): void
    {
        $checkoutFields = Mockery::mock(CheckoutFields::class);
        $checkoutFields->shouldReceive('get')->once()->andReturn([
            ['key' => 'rut', 'label' => 'RUT', 'values' => [], 'required' => true],
        ]);
        $checkoutFieldValue = Mockery::mock(CheckoutFieldValue::class);
        $checkoutFieldValue->shouldNotReceive('save');

        $extensionAttributes = Mockery::mock(ShippingInformationExtensionInterface::class);
        $extensionAttributes->shouldReceive('getBillmysalesCheckoutFields')->once()->andReturn('not json');

        $subject = Mockery::mock(ShippingInformationManagement::class);
        $addressInformation = Mockery::mock(ShippingInformationInterface::class);
        $addressInformation->shouldReceive('getExtensionAttributes')->once()->andReturn($extensionAttributes);

        $this->expectException(LocalizedException::class);
        (new SaveCheckoutFieldsPlugin($checkoutFields, $checkoutFieldValue))
            ->beforeSaveAddressInformation($subject, 7, $addressInformation);
    }

    /**
     * @return void
     */
    public function testRefusesAnEmptyRequiredField(): void
    {
        $checkoutFields = Mockery::mock(CheckoutFields::class);
        $checkoutFields->shouldReceive('get')->once()->andReturn([
            ['key' => 'rut', 'label' => 'RUT', 'values' => [], 'required' => true],
        ]);
        $checkoutFieldValue = Mockery::mock(CheckoutFieldValue::class);
        $checkoutFieldValue->shouldNotReceive('save');

        $extensionAttributes = Mockery::mock(ShippingInformationExtensionInterface::class);
        $extensionAttributes->shouldReceive('getBillmysalesCheckoutFields')->once()->andReturn('{"rut":"  "}');

        $subject = Mockery::mock(ShippingInformationManagement::class);
        $addressInformation = Mockery::mock(ShippingInformationInterface::class);
        $addressInformation->shouldReceive('getExtensionAttributes')->once()->andReturn($extensionAttributes);

        $this->expectException(LocalizedException::class);
        (new SaveCheckoutFieldsPlugin($checkoutFields, $checkoutFieldValue))
            ->beforeSaveAddressInformation($subject, 7, $addressInformation);
    }

    /**
     * @return void
     */
    public function testDoesNothingWithNoExtensionAttributesAndNoRequiredFields(): void
    {
        $checkoutFields = Mockery::mock(CheckoutFields::class);
        $checkoutFields->shouldReceive('get')->once()->andReturn([
            ['key' => 'giro', 'label' => 'Giro', 'values' => [], 'required' => false],
        ]);
        $checkoutFieldValue = Mockery::mock(CheckoutFieldValue::class);
        $checkoutFieldValue->shouldNotReceive('save');

        $subject = Mockery::mock(ShippingInformationManagement::class);
        $addressInformation = Mockery::mock(ShippingInformationInterface::class);
        $addressInformation->shouldReceive('getExtensionAttributes')->once()->andReturn(null);

        (new SaveCheckoutFieldsPlugin($checkoutFields, $checkoutFieldValue))
            ->beforeSaveAddressInformation($subject, 7, $addressInformation);
    }

    /**
     * @return void
     */
    public function testSavesEveryConfiguredFieldWithANonEmptySubmittedValue(): void
    {
        $checkoutFields = Mockery::mock(CheckoutFields::class);
        $checkoutFields->shouldReceive('get')->once()->andReturn([
            ['key' => 'rut', 'label' => 'RUT', 'values' => [], 'required' => true],
            ['key' => 'giro', 'label' => 'Giro', 'values' => [], 'required' => false],
            ['key' => 'not_submitted', 'label' => 'Not submitted', 'values' => [], 'required' => false],
        ]);
        $checkoutFieldValue = Mockery::mock(CheckoutFieldValue::class);
        $checkoutFieldValue->shouldReceive('save')->once()->with(7, 'rut', '11.111.111-1');

        $extensionAttributes = Mockery::mock(ShippingInformationExtensionInterface::class);
        $extensionAttributes->shouldReceive('getBillmysalesCheckoutFields')->once()->andReturn(
            '{"rut":" 11.111.111-1 ","giro":"  ","extra":"ignored"}'
        );

        $subject = Mockery::mock(ShippingInformationManagement::class);
        $addressInformation = Mockery::mock(ShippingInformationInterface::class);
        $addressInformation->shouldReceive('getExtensionAttributes')->once()->andReturn($extensionAttributes);

        (new SaveCheckoutFieldsPlugin($checkoutFields, $checkoutFieldValue))
            ->beforeSaveAddressInformation($subject, 7, $addressInformation);
    }
}
