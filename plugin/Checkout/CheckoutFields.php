<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

namespace BillMySales\Magento\Checkout;

use Magento\Framework\App\Config\ScopeConfigInterface;

/**
 * The admin-defined checkout fields (etc/adminhtml/system.xml's "Checkout
 * fields" section, edited through Block\Adminhtml\System\Config\CheckoutFields'
 * row editor, stored as one JSON-encoded configuration value).
 */
class CheckoutFields
{
    /**
     * Configuration path of the field definitions (JSON).
     */
    public const PATH_DEFINITIONS = 'billmysales/checkout_fields/definitions';

    /**
     * Magento's own store configuration.
     *
     * @var ScopeConfigInterface
     */
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param ScopeConfigInterface $scopeConfig Magento's own store configuration.
     */
    public function __construct(ScopeConfigInterface $scopeConfig)
    {
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * The configured fields, added to the checkout and sent with the order.
     *
     * @return array<int,array{key:string,label:string,values:string[],required:bool}>
     */
    public function get(): array
    {
        return self::decode((string) $this->scopeConfig->getValue(self::PATH_DEFINITIONS));
    }

    /**
     * Decodes a JSON-encoded list of field definitions.
     *
     * Invalid JSON, or a value that isn't a list, decodes to no fields.
     *
     * @param string $json JSON-encoded field definitions.
     * @return array<int,array{key:string,label:string,values:string[],required:bool}>
     */
    public static function decode(string $json): array // phpcs:ignore
    {
        if ('' === $json) {
            return [];
        }
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $fields = [];
        foreach ($decoded as $row) {
            if (!is_array($row) || !isset($row['key'], $row['label']) || '' === (string) $row['key']) {
                continue;
            }
            $fields[] = [
                'key' => (string) $row['key'],
                'label' => (string) $row['label'],
                'values' => array_map('strval', is_array($row['values'] ?? null) ? $row['values'] : []),
                'required' => (bool) ($row['required'] ?? false),
            ];
        }
        return $fields;
    }

    /**
     * Validates and normalizes a JSON-encoded list of field definitions
     * (Model\Config\Backend\CheckoutFields, on save): rows without a label
     * are dropped, and the key is always derived from the label (unique
     * within the list).
     *
     * @param string $json JSON-encoded field definitions, as submitted by the admin.
     * @return string
     */
    public static function sanitize(string $json): string // phpcs:ignore
    {
        $decoded = '' !== $json ? json_decode($json, true) : [];
        $rows = is_array($decoded) ? $decoded : [];

        $fields = [];
        $keys = [];
        foreach ($rows as $row) {
            $label = is_array($row) && isset($row['label']) ? trim((string) $row['label']) : '';
            if ('' === $label) {
                continue;
            }
            $key = self::uniqueKey($label, $keys);
            $keys[] = $key;
            $values = [];
            foreach (is_array($row['values'] ?? null) ? $row['values'] : [] as $value) {
                $value = trim((string) $value);
                if ('' !== $value) {
                    $values[] = $value;
                }
            }
            $fields[] = [
                'key' => $key,
                'label' => $label,
                'values' => $values,
                'required' => !empty($row['required']),
            ];
        }
        return (string) json_encode($fields);
    }

    /**
     * Builds a key from a label, with a numeric suffix when it's taken
     * (two "RUT" fields give "rut" and "rut_2").
     *
     * @param string $label Field label.
     * @param string[] $taken Keys already used.
     * @return string
     */
    private static function uniqueKey(string $label, array $taken): string // phpcs:ignore
    {
        $base = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '_', $label));
        $base = trim($base, '_');
        $base = '' !== $base ? $base : 'field';
        $key = $base;
        $i = 2;
        while (in_array($key, $taken, true)) {
            $key = $base . '_' . $i;
            ++$i;
        }
        return $key;
    }
}
