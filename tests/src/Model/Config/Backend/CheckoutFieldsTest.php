<?php

declare(strict_types=1);

/**
 * Tests for CheckoutFields.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Model\Config\Backend;

use BillMySales\Magento\Checkout\CheckoutFields as CheckoutFieldsHelper;
use BillMySales\Magento\Model\Config\Backend\CheckoutFields;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CheckoutFields::class)]
#[UsesClass(CheckoutFieldsHelper::class)]
/**
 * CheckoutFieldsTest.
 */
final class CheckoutFieldsTest extends TestCase
{
    /**
     * @return void
     */
    public function testBeforeSaveSanitizesTheValue(): void
    {
        $backend = new CheckoutFields();
        $backend->setValue('[{"label":"RUT"},{"label":"  "}]');

        $backend->beforeSave();

        self::assertSame('[{"key":"rut","label":"RUT","values":[],"required":false}]', $backend->getValue());
    }
}
