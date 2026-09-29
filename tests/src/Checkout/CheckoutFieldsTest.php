<?php

declare(strict_types=1);

/**
 * Tests for CheckoutFields.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Checkout;

use BillMySales\Magento\Checkout\CheckoutFields;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

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
    public function testGetDecodesTheStoredDefinitions(): void
    {
        $scopeConfig = Mockery::mock(ScopeConfigInterface::class);
        $scopeConfig->shouldReceive('getValue')
            ->once()
            ->with(CheckoutFields::PATH_DEFINITIONS)
            ->andReturn('[{"key":"rut","label":"RUT","values":[],"required":true}]');

        self::assertSame(
            [['key' => 'rut', 'label' => 'RUT', 'values' => [], 'required' => true]],
            (new CheckoutFields($scopeConfig))->get()
        );
    }

    /**
     * @return void
     */
    public function testDecodeOfAnEmptyString(): void
    {
        self::assertSame([], CheckoutFields::decode(''));
    }

    /**
     * @return void
     */
    public function testDecodeOfInvalidJson(): void
    {
        self::assertSame([], CheckoutFields::decode('not json'));
    }

    /**
     * @return void
     */
    public function testDecodeOfAJsonObjectInsteadOfAList(): void
    {
        self::assertSame([], CheckoutFields::decode('{"key":"rut"}'));
    }

    /**
     * @return void
     */
    public function testDecodeDropsRowsMissingAKeyOrLabel(): void
    {
        self::assertSame(
            [],
            CheckoutFields::decode('[{"key":"rut"},{"label":"RUT"},"not-a-row"]')
        );
    }

    /**
     * @return void
     */
    public function testDecodeOfAFullRow(): void
    {
        self::assertSame(
            [['key' => 'doc_type', 'label' => 'Document type', 'values' => ['Receipt', 'Invoice'], 'required' => false]],
            CheckoutFields::decode(
                '[{"key":"doc_type","label":"Document type","values":["Receipt","Invoice"],"required":false}]'
            )
        );
    }

    /**
     * @return void
     */
    public function testSanitizeOfAnEmptyString(): void
    {
        self::assertSame('[]', CheckoutFields::sanitize(''));
    }

    /**
     * @return void
     */
    public function testSanitizeDropsRowsWithoutALabel(): void
    {
        self::assertSame('[]', CheckoutFields::sanitize('[{"label":"  "},{"values":["A"]}]'));
    }

    /**
     * @return void
     */
    public function testSanitizeDerivesTheKeyFromTheLabel(): void
    {
        $result = json_decode(CheckoutFields::sanitize('[{"label":"Business activity"}]'), true);

        self::assertSame('business_activity', $result[0]['key']);
    }

    /**
     * @return void
     */
    public function testSanitizeDedupesKeysOfRepeatedLabels(): void
    {
        $result = json_decode(CheckoutFields::sanitize('[{"label":"RUT"},{"label":"RUT"}]'), true);

        self::assertSame('rut', $result[0]['key']);
        self::assertSame('rut_2', $result[1]['key']);
    }

    /**
     * @return void
     */
    public function testSanitizeTrimsAndDropsEmptyValues(): void
    {
        $result = json_decode(
            CheckoutFields::sanitize('[{"label":"Document type","values":[" Receipt ","","Invoice"]}]'),
            true
        );

        self::assertSame(['Receipt', 'Invoice'], $result[0]['values']);
    }

    /**
     * @return void
     */
    public function testSanitizeCastsRequiredToABoolean(): void
    {
        $result = json_decode(CheckoutFields::sanitize('[{"label":"RUT","required":1}]'), true);

        self::assertTrue($result[0]['required']);
    }
}
