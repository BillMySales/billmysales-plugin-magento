<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

namespace BillMySales\Magento\Webhook;

use BillMySales\Magento\Model\ResourceModel\DeliveryQueue;
use BillMySales\Magento\Settings;
use Magento\Framework\App\ProductMetadataInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\HTTP\ClientInterface;
use Magento\Framework\Math\Random;
use Magento\Framework\Module\ModuleListInterface;
use Magento\Framework\Phrase;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Queues a delivery when an order reaches a selected status (or the
 * merchant sends it again), and sends every due delivery from the
 * module's own cron job (etc/crontab.xml), so the observer and the order
 * page never wait for BillMySales. Failed deliveries are retried with a
 * growing delay; BillMySales is idempotent, so a repeated delivery is
 * harmless. The payload is built when the job runs, with the order's data
 * at that moment.
 *
 * Every attempt is recorded as a private note in the order's own status
 * history (Magento's native per-record note, admin-only: not shown to the
 * customer) and in Magento's own log.
 */
class Delivery
{
    /**
     * Delay (seconds) before each retry; after the last one, the delivery
     * is given up.
     */
    public const RETRY_DELAYS = [60, 300, 1800, 7200, 43200];

    /**
     * The order was accepted by BillMySales.
     */
    public const RESULT_DELIVERED = 'delivered';

    /**
     * A retryable error: another attempt is scheduled.
     */
    public const RESULT_RETRY = 'retry';

    /**
     * A retryable error, but every retry is already used.
     */
    public const RESULT_GIVEN_UP = 'given_up';

    /**
     * A non-retryable error: BillMySales rejected the order.
     */
    public const RESULT_REJECTED = 'rejected';

    /**
     * The order no longer exists (deleted before its delivery ran).
     */
    public const RESULT_GONE = 'gone';

    /**
     * The module's own name (etc/module.xml), to read its version.
     */
    private const MODULE_NAME = 'BillMySales_Magento';

    /**
     * Request timeout, in seconds (it runs in the background).
     */
    private const TIMEOUT = 30;

    /**
     * Reads the current settings.
     *
     * @var Settings
     */
    private Settings $settings;

    /**
     * The pending-deliveries queue.
     *
     * @var DeliveryQueue
     */
    private DeliveryQueue $queue;

    /**
     * Reads an order by id, and saves the note an attempt adds to it.
     *
     * @var OrderRepositoryInterface
     */
    private OrderRepositoryInterface $orderRepository;

    /**
     * Builds an order's payload.
     *
     * @var Payload
     */
    private Payload $payload;

    /**
     * Posts the payload.
     *
     * @var ClientInterface
     */
    private ClientInterface $httpClient;

    /**
     * The store's own base URL.
     *
     * @var StoreManagerInterface
     */
    private StoreManagerInterface $storeManager;

    /**
     * Magento's version.
     *
     * @var ProductMetadataInterface
     */
    private ProductMetadataInterface $productMetadata;

    /**
     * The module's own version.
     *
     * @var ModuleListInterface
     */
    private ModuleListInterface $moduleList;

    /**
     * A new delivery's UUID.
     *
     * @var Random
     */
    private Random $random;

    /**
     * Magento's own log.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * @param Settings $settings Reads the current settings.
     * @param DeliveryQueue $queue The pending-deliveries queue.
     * @param OrderRepositoryInterface $orderRepository Reads an order by id, and saves the note an attempt adds to it.
     * @param Payload $payload Builds an order's payload.
     * @param ClientInterface $httpClient Posts the payload.
     * @param StoreManagerInterface $storeManager The store's own base URL.
     * @param ProductMetadataInterface $productMetadata Magento's version.
     * @param ModuleListInterface $moduleList The module's own version.
     * @param Random $random A new delivery's UUID.
     * @param LoggerInterface $logger Magento's own log.
     */
    public function __construct(
        Settings $settings,
        DeliveryQueue $queue,
        OrderRepositoryInterface $orderRepository,
        Payload $payload,
        ClientInterface $httpClient,
        StoreManagerInterface $storeManager,
        ProductMetadataInterface $productMetadata,
        ModuleListInterface $moduleList,
        Random $random,
        LoggerInterface $logger
    ) {
        $this->settings = $settings;
        $this->queue = $queue;
        $this->orderRepository = $orderRepository;
        $this->payload = $payload;
        $this->httpClient = $httpClient;
        $this->storeManager = $storeManager;
        $this->productMetadata = $productMetadata;
        $this->moduleList = $moduleList;
        $this->random = $random;
        $this->logger = $logger;
    }

    /**
     * Queues a new delivery, due immediately.
     *
     * @param int $orderId Order id (entity_id).
     * @param string $event Event, e.g. "order.status_changed".
     * @return void
     */
    public function enqueue(int $orderId, string $event): void
    {
        $this->queue->enqueue($orderId, $event, $this->uuid(), $this->now());
    }

    /**
     * Sends every delivery due by now.
     *
     * @param int $limit Maximum deliveries sent in this run.
     * @return void
     */
    public function processDue(int $limit = 20): void
    {
        $settings = $this->settings->get();
        if (!Settings::isReady($settings)) {
            return;
        }
        foreach ($this->queue->due($this->now(), $limit) as $row) {
            $this->processOne($row, $settings);
        }
    }

    /**
     * Whether an HTTP status is worth retrying: a network error (-1),
     * timeouts, rate limits and server errors. Other client errors won't
     * change on a retry.
     *
     * Pure logic, nothing to intercept: kept static.
     *
     * @param int $code HTTP status, or -1 for a network error.
     * @return bool
     */
    public static function isRetryable(int $code): bool // phpcs:ignore Magento2.Functions.StaticFunction.StaticFunction
    {
        return -1 === $code || 408 === $code || 429 === $code || $code >= 500;
    }

    /**
     * Sends one due delivery.
     *
     * The shape types below aren't parsed by Magento2's own annotation
     * sniff, which otherwise flags this docblock as incomplete: not true.
     *
     * @param array{id:int,order_id:int,event:string,delivery_id:string,attempt:int} $row Queue row.
     * @param array{url:string,secret:string,statuses:string[],active:bool} $settings Settings.
     * @return void
     */
    private function processOne(array $row, array $settings): void
    {
        try {
            $order = $this->orderRepository->get($row['order_id']);
        } catch (NoSuchEntityException $e) {
            // No order left to add a note to: only the log keeps this one.
            $this->queue->delete($row['id']);
            $this->logger->warning(sprintf(
                'BillMySales: order #%d %s (%s, %s): order no longer exists.',
                $row['order_id'],
                self::RESULT_GONE,
                $row['event'],
                $row['delivery_id']
            ));
            return;
        }

        /** @var Order $order */
        $body = (string) json_encode($this->payload->build($order));
        $headers = Headers::build(
            $body,
            $settings['secret'],
            $row['event'],
            $row['delivery_id'],
            $this->storeManager->getStore()->getBaseUrl(),
            $this->productMetadata->getVersion(),
            $this->pluginVersion()
        );
        [$code, $response] = $this->post($settings['url'], $body, $headers);

        if ($code >= 200 && $code < 300) {
            $this->queue->delete($row['id']);
            $this->addNote($order, __('BillMySales: delivered (HTTP %1).', $code));
            $this->logger->info(sprintf(
                'BillMySales: order #%d delivered (%s, %s, HTTP %d).',
                $row['order_id'],
                $row['event'],
                $row['delivery_id'],
                $code
            ));
            return;
        }

        if (self::isRetryable($code) && isset(self::RETRY_DELAYS[$row['attempt']])) {
            $delay = self::RETRY_DELAYS[$row['attempt']];
            $nextAttemptAt = date('Y-m-d H:i:s', time() + $delay);
            $attempts = $row['attempt'] + 1;
            $this->queue->reschedule($row['id'], $nextAttemptAt, $attempts);
            $detail = $this->detail($code, $response);
            $this->addNote($order, __('BillMySales: not delivered (%1), retry %2 scheduled.', $detail, $attempts));
            $this->logger->warning(sprintf(
                'BillMySales: order #%d not delivered (%s), retry %d in %d s.',
                $row['order_id'],
                $detail,
                $attempts,
                $delay
            ));
            return;
        }

        $this->queue->delete($row['id']);
        $detail = $this->detail($code, $response);
        $result = self::isRetryable($code) ? self::RESULT_GIVEN_UP : self::RESULT_REJECTED;
        $this->addNote($order, __('BillMySales: %1 (%2).', $result, $detail));
        $this->logger->error(sprintf(
            'BillMySales: order #%d %s (%s, %s): %s',
            $row['order_id'],
            $result,
            $row['event'],
            $row['delivery_id'],
            $detail
        ));
    }

    /**
     * Posts the payload.
     *
     * A network error (including Curl's own thrown exception) is reported
     * as HTTP status -1.
     *
     * @param string $url Notification URL.
     * @param string $body Request body.
     * @param array<string,string> $headers Request headers.
     * @return array{int,string} The HTTP status (or -1) and the response body/error.
     */
    private function post(string $url, string $body, array $headers): array // phpcs:ignore
    {
        try {
            $this->httpClient->setTimeout(self::TIMEOUT);
            $this->httpClient->setHeaders($headers);
            $this->httpClient->post($url, $body);
            return [(int) $this->httpClient->getStatus(), (string) $this->httpClient->getBody()];
        } catch (\Throwable $e) {
            return [-1, $e->getMessage()];
        }
    }

    /**
     * Records the outcome of a delivery attempt as a private note in the
     * order's own status history (Magento's native per-record note,
     * `addStatusHistoryComment()`'s own default: admin-only, the current
     * status kept as is). Saving the order here, from a status this same
     * save could itself have triggered (etc/events.xml's
     * sales_order_save_commit_after), doesn't loop: the status is
     * unchanged, so QueueOnStatusChange's own guard (orig vs new status)
     * skips it.
     *
     * @param Order $order The order.
     * @param Phrase $comment The note.
     * @return void
     */
    private function addNote(Order $order, Phrase $comment): void // phpcs:ignore
    {
        $order->addStatusHistoryComment((string) $comment);
        $this->orderRepository->save($order);
    }

    /**
     * Formats an attempt's detail.
     *
     * The network error message as is, or the HTTP status plus a
     * truncated body for a 4xx/5xx response.
     *
     * @param int $code HTTP status, or -1 for a network error.
     * @param string $response Response body, or the network error message.
     * @return string
     */
    private function detail(int $code, string $response): string
    {
        if ($code < 0) {
            return $response;
        }
        $body = trim(substr($response, 0, 300));
        return 'HTTP ' . $code . ('' !== $body ? ': ' . $body : '');
    }

    /**
     * The module's own version (etc/module.xml's setup_version).
     *
     * @return string
     */
    private function pluginVersion(): string
    {
        $module = $this->moduleList->getOne(self::MODULE_NAME);
        return (string) ($module['setup_version'] ?? '');
    }

    /**
     * Current date/time ("Y-m-d H:i:s").
     *
     * @return string
     */
    private function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * A random UUID (v4).
     *
     * @return string
     */
    private function uuid(): string
    {
        $data = $this->random->getRandomBytes(16);
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged -- raw byte manipulation, not text: mb_chr()/mb_ord() would be wrong here.
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        // phpcs:ignore Magento2.Functions.DiscouragedFunction.Discouraged -- raw byte manipulation, not text: mb_chr()/mb_ord() would be wrong here.
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
