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
 * The pending-deliveries queue (billmysales_delivery_queue, declared in
 * etc/db_schema.xml): one row per order with a delivery still pending,
 * removed once it's no longer pending (not a growing log). A plain class
 * over Magento's own database connection, the same way Magento's core
 * modules manage a simple custom table (e.g.
 * Magento\ReleaseNotification\Model\ResourceModel\Viewer\Logger): no
 * Model/Collection is needed, since nothing here is an editable object,
 * loaded by id or listed in a grid.
 */
class DeliveryQueue
{
    /**
     * Table name (declared in etc/db_schema.xml).
     */
    public const TABLE = 'billmysales_delivery_queue';

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
     * Queues a new delivery, due immediately.
     *
     * @param int $orderId Order id (entity_id).
     * @param string $event Event, e.g. "order.status_changed".
     * @param string $deliveryId UUID of the notification.
     * @param string $now Current date/time ("Y-m-d H:i:s").
     * @return void
     */
    public function enqueue(int $orderId, string $event, string $deliveryId, string $now): void
    {
        $this->resource->getConnection()->insert($this->table(), [
            'order_id' => $orderId,
            'event' => $event,
            'delivery_id' => $deliveryId,
            'attempt' => 0,
            'next_attempt_at' => $now,
            'created_at' => $now,
        ]);
    }

    /**
     * The deliveries due by now, oldest first.
     *
     * @param string $now Current date/time ("Y-m-d H:i:s").
     * @param int $limit Maximum rows.
     * @return array<int,array{id:int,order_id:int,event:string,delivery_id:string,attempt:int}>
     */
    public function due(string $now, int $limit): array
    {
        $connection = $this->resource->getConnection();
        $select = $connection->select()
            ->from($this->table(), ['id', 'order_id', 'event', 'delivery_id', 'attempt'])
            ->where('next_attempt_at <= ?', $now)
            ->order('next_attempt_at ASC')
            ->limit($limit);

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'order_id' => (int) $row['order_id'],
                'event' => (string) $row['event'],
                'delivery_id' => (string) $row['delivery_id'],
                'attempt' => (int) $row['attempt'],
            ],
            $connection->fetchAll($select)
        );
    }

    /**
     * Schedules the next attempt.
     *
     * @param int $id Queue row id.
     * @param string $nextAttemptAt Next attempt's date/time ("Y-m-d H:i:s").
     * @param int $attempt Attempts done so far.
     * @return void
     */
    public function reschedule(int $id, string $nextAttemptAt, int $attempt): void
    {
        $connection = $this->resource->getConnection();
        $connection->update(
            $this->table(),
            ['next_attempt_at' => $nextAttemptAt, 'attempt' => $attempt],
            $connection->quoteInto('id = ?', $id)
        );
    }

    /**
     * Removes a delivery (delivered, rejected or given up).
     *
     * @param int $id Queue row id.
     * @return void
     */
    public function delete(int $id): void
    {
        $connection = $this->resource->getConnection();
        $connection->delete($this->table(), $connection->quoteInto('id = ?', $id));
    }

    /**
     * How many deliveries are pending.
     *
     * @return int
     */
    public function countPending(): int
    {
        $connection = $this->resource->getConnection();
        return (int) $connection->fetchOne($connection->select()->from($this->table(), ['COUNT(*)']));
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
