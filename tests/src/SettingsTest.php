<?php

declare(strict_types=1);

/**
 * Tests for Settings.
 *
 * @package BillMySales\TestsMagento
 */

namespace BillMySales\TestsMagento;

use BillMySales\Magento\Settings;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Settings::class)]
/**
 * SettingsTest.
 */
final class SettingsTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    /**
     * @return void
     */
    public function testGetWithValidValues(): void
    {
        $scopeConfig = Mockery::mock(ScopeConfigInterface::class);
        $scopeConfig->shouldReceive('getValue')->with(Settings::PATH_URL)->andReturn('https://billmysales.example/webhook');
        $scopeConfig->shouldReceive('getValue')->with(Settings::PATH_SECRET)->andReturn('0:3:ciphertext');
        $scopeConfig->shouldReceive('getValue')->with(Settings::PATH_STATUSES)->andReturn('processing,complete');
        $scopeConfig->shouldReceive('getValue')->with(Settings::PATH_ACTIVE)->andReturn('1');
        $encryptor = Mockery::mock(EncryptorInterface::class);
        $encryptor->shouldReceive('decrypt')->once()->with('0:3:ciphertext')->andReturn('s3cr3t');

        $settings = new Settings($scopeConfig, $encryptor);

        self::assertSame([
            'url' => 'https://billmysales.example/webhook',
            'secret' => 's3cr3t',
            'statuses' => ['processing', 'complete'],
            'active' => true,
        ], $settings->get());
    }

    /**
     * @return void
     */
    public function testGetFallsBackToDefaults(): void
    {
        $scopeConfig = Mockery::mock(ScopeConfigInterface::class);
        $scopeConfig->shouldReceive('getValue')->andReturn(null);
        $encryptor = Mockery::mock(EncryptorInterface::class);
        $encryptor->shouldNotReceive('decrypt');

        $settings = new Settings($scopeConfig, $encryptor);

        self::assertSame([
            'url' => '',
            'secret' => '',
            'statuses' => [],
            'active' => false,
        ], $settings->get());
    }

    /**
     * @return void
     */
    public function testGetTrimsAndDropsEmptyStatuses(): void
    {
        $scopeConfig = Mockery::mock(ScopeConfigInterface::class);
        $scopeConfig->shouldReceive('getValue')->with(Settings::PATH_URL)->andReturn('');
        $scopeConfig->shouldReceive('getValue')->with(Settings::PATH_SECRET)->andReturn('');
        $scopeConfig->shouldReceive('getValue')->with(Settings::PATH_STATUSES)->andReturn(' processing ,,complete,');
        $scopeConfig->shouldReceive('getValue')->with(Settings::PATH_ACTIVE)->andReturn('0');
        $encryptor = Mockery::mock(EncryptorInterface::class);
        $encryptor->shouldNotReceive('decrypt');

        $settings = new Settings($scopeConfig, $encryptor);

        self::assertSame(['processing', 'complete'], $settings->get()['statuses']);
    }

    /**
     * @return void
     */
    public function testIsReady(): void
    {
        $ready = ['url' => 'https://example.test', 'secret' => 'x', 'statuses' => [], 'active' => true];
        self::assertTrue(Settings::isReady($ready));
        self::assertFalse(Settings::isReady(['url' => '', 'secret' => 'x', 'statuses' => [], 'active' => true]));
        self::assertFalse(Settings::isReady(['url' => 'https://example.test', 'secret' => '', 'statuses' => [], 'active' => true]));
        self::assertFalse(Settings::isReady(['url' => 'https://example.test', 'secret' => 'x', 'statuses' => [], 'active' => false]));
    }

    /**
     * @return void
     */
    public function testNotifies(): void
    {
        $settings = ['url' => 'https://example.test', 'secret' => 'x', 'statuses' => ['processing'], 'active' => true];
        self::assertTrue(Settings::notifies($settings, 'processing'));
        self::assertFalse(Settings::notifies($settings, 'pending'));
    }

    /**
     * @return void
     */
    public function testNotifiesWhenNotReady(): void
    {
        $settings = ['url' => '', 'secret' => '', 'statuses' => ['processing'], 'active' => false];
        self::assertFalse(Settings::notifies($settings, 'processing'));
    }
}
