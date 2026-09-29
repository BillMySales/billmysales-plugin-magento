<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

namespace BillMySales\Magento\Model\ResourceModel;

use Magento\Framework\App\ResourceConnection;

/**
 * The checkout fields submitted with a quote (billmysales_checkout_field_value,
 * declared in etc/db_schema.xml): one row per field, keyed by the quote
 * (not the order, which doesn't exist yet when the checkout's address step
 * saves it).
 */
class CheckoutFieldValue
{
    /**
     * Table name (declared in etc/db_schema.xml).
     */
    public const TABLE = 'billmysales_checkout_field_value';

    /**
     * Magento's database connection.
     *
     * @var ResourceConnection
     */
    private ResourceConnection $resource;

    /**
     * @param ResourceConnection $resource Magento's database connection.
     */
    public function __construct(ResourceConnection $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Stores a field's value.
     *
     * Replaces any previous value of the same field for this quote.
     *
     * @param int $quoteId Quote id.
     * @param string $fieldKey Field key.
     * @param string $value Submitted value.
     * @return void
     */
    public function save(int $quoteId, string $fieldKey, string $value): void
    {
        $this->resource->getConnection()->insertOnDuplicate(
            $this->table(),
            [
                'quote_id' => $quoteId,
                'field_key' => $fieldKey,
                'value' => $value,
            ],
            ['value']
        );
    }

    /**
     * The checkout fields submitted with a quote, by key.
     *
     * @param int $quoteId Quote id.
     * @return array<string,string>
     */
    public function getByQuoteId(int $quoteId): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->table(), ['field_key', 'value'])
            ->where('quote_id = ?', $quoteId);
        $values = [];
        foreach ($connection->fetchAll($select) as $row) {
            $values[(string) $row['field_key']] = (string) $row['value'];
        }
        return $values;
    }

    /**
     * The table's real name (Magento's own table prefix, if any).
     *
     * @return string
     */
    private function table(): string
    {
        return $this->resource->getTableName(self::TABLE);
    }
}
