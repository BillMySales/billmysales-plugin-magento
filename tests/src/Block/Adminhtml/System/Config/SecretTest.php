<?php

declare(strict_types=1);

/**
 * Tests for Secret.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento\Block\Adminhtml\System\Config;

use BillMySales\Magento\Block\Adminhtml\System\Config\Secret;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Encryption\EncryptorInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

#[CoversClass(Secret::class)]
/**
 * SecretTest.
 */
final class SecretTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testRendersAPasswordFieldWithAShowButton(): void
    {
        $element = Mockery::mock(AbstractElement::class);
        $element->shouldReceive('getValue')->once()->andReturn('');
        $element->shouldReceive('setType')->once()->with('password');
        $element->shouldReceive('getClass')->once()->andReturn('input-text');
        $element->shouldReceive('setClass')->once()->with('input-text billmysales-secret-field');
        $element->shouldReceive('setData')->once()->with('autocomplete', 'off');
        $element->shouldReceive('getElementHtml')->once()->andReturn('<input id="billmysales_general_token" type="password">');
        $element->shouldReceive('getHtmlId')->once()->andReturn('billmysales_general_token');

        $context = Mockery::mock(Context::class)->shouldIgnoreMissing();
        $encryptor = Mockery::mock(EncryptorInterface::class);
        $encryptor->shouldNotReceive('decrypt');
        $block = new Secret($context, $encryptor);

        $method = new ReflectionMethod(Secret::class, '_getElementHtml');
        $html = $method->invoke($block, $element);

        self::assertStringContainsString('<input id="billmysales_general_token" type="password">', $html);
        self::assertStringContainsString('billmysalesSecretToggle', $html);
        self::assertStringContainsString('#billmysales_general_token', $html);
    }

    /**
     * @return void
     */
    public function testDecryptsAnAlreadyStoredSecret(): void
    {
        $element = Mockery::mock(AbstractElement::class);
        $element->shouldReceive('getValue')->once()->andReturn('0:3:ciphertext');
        $element->shouldReceive('setValue')->once()->with('e2e-secret');
        $element->shouldReceive('setType')->once()->with('password');
        $element->shouldReceive('getClass')->once()->andReturn('input-text');
        $element->shouldReceive('setClass')->once()->with('input-text billmysales-secret-field');
        $element->shouldReceive('setData')->once()->with('autocomplete', 'off');
        $element->shouldReceive('getElementHtml')->once()->andReturn('<input id="billmysales_general_token" type="password" value="e2e-secret">');
        $element->shouldReceive('getHtmlId')->once()->andReturn('billmysales_general_token');

        $context = Mockery::mock(Context::class)->shouldIgnoreMissing();
        $encryptor = Mockery::mock(EncryptorInterface::class);
        $encryptor->shouldReceive('decrypt')->once()->with('0:3:ciphertext')->andReturn('e2e-secret');
        $block = new Secret($context, $encryptor);

        $method = new ReflectionMethod(Secret::class, '_getElementHtml');
        $html = $method->invoke($block, $element);

        self::assertStringContainsString('value="e2e-secret"', $html);
    }
}
