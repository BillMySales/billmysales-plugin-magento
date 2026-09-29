<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

namespace BillMySales\Magento;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;

/**
 * Reads the delivery settings from Magento's Configuration
 * (core_config_data, through ScopeConfigInterface, the same values
 * etc/adminhtml/system.xml's fields store). The secret's backend_model
 * (Magento\Config\Model\Config\Backend\Encrypted) only encrypts it on
 * save: ScopeConfigInterface::getValue() returns the stored ciphertext as
 * is, so it's decrypted here before use, the same way
 * Block\Adminhtml\System\Config\Secret decrypts it for display.
 */
class Settings
{
    /**
     * Configuration path of the "active" switch.
     */
    public const PATH_ACTIVE = 'billmysales/general/active';

    /**
     * Configuration path of the notification URL.
     */
    public const PATH_URL = 'billmysales/general/webhook';

    /**
     * Configuration path of the shared secret.
     */
    public const PATH_SECRET = 'billmysales/general/token';

    /**
     * Configuration path of the order statuses that notify (comma-separated,
     * Magento's own multiselect storage format).
     */
    public const PATH_STATUSES = 'billmysales/general/notify_statuses';

    /**
     * Every configuration path the module adds, removed when the module is
     * uninstalled with its data.
     *
     * @var string[]
     */
    public const PATHS = [
        self::PATH_ACTIVE,
        self::PATH_URL,
        self::PATH_SECRET,
        self::PATH_STATUSES,
    ];

    /**
     * Magento's own store configuration.
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * Decrypts the stored secret.
     *
     * @var EncryptorInterface
     */
    private EncryptorInterface $encryptor;

    /**
     * @param ScopeConfigInterface $scopeConfig Magento's own store configuration.
     * @param EncryptorInterface $encryptor Decrypts the stored secret.
     */
    public function __construct(ScopeConfigInterface $scopeConfig, EncryptorInterface $encryptor)
    {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * The current settings.
     *
     * @return array{url:string,secret:string,statuses:string[],active:bool}
     */
    public function get(): array
    {
        $statuses = (string) $this->scopeConfig->getValue(self::PATH_STATUSES);
        $secret = (string) $this->scopeConfig->getValue(self::PATH_SECRET);
        return [
            'url' => (string) $this->scopeConfig->getValue(self::PATH_URL),
            'secret' => '' !== $secret ? $this->encryptor->decrypt($secret) : '',
            'statuses' => '' !== $statuses
                ? array_values(array_filter(
                    array_map('trim', explode(',', $statuses)),
                    static fn (string $s): bool => '' !== $s
                ))
                : [],
            'active' => (bool) $this->scopeConfig->getValue(self::PATH_ACTIVE),
        ];
    }

    /**
     * Whether deliveries are enabled and fully configured.
     *
     * @param array{url:string,secret:string,statuses:string[],active:bool} $settings Settings.
     * @return bool
     */
    public static function isReady(array $settings): bool // phpcs:ignore
    {
        return $settings['active'] && '' !== $settings['url'] && '' !== $settings['secret'];
    }

    /**
     * Whether an order status notifies, given the settings.
     *
     * @param array{url:string,secret:string,statuses:string[],active:bool} $settings Settings.
     * @param string $status Order status code (e.g. "processing").
     * @return bool
     */
    public static function notifies(array $settings, string $status): bool // phpcs:ignore
    {
        return self::isReady($settings) && in_array($status, $settings['statuses'], true);
    }
}
