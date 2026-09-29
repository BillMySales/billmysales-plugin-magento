<?php

declare(strict_types=1);

/**
 * BillMySales for Magento.
 *
 * Copyright (c) 2026 BillMySales <https://www.billmysales.com>
 * Licensed under the GNU General Public License v3.0 or later.
 * See LICENSE file for more details.
 */

// Minimal Magento classes the plugin uses, for the unit tests (mocked with
// Mockery). PHPStan uses the real stubs instead (this file is excluded
// there): only the methods the plugin actually calls are declared, with
// dummy bodies.

namespace Magento\Framework\Event {
    /**
     * Double of the dispatched event (no return type, matching the real
     * Observer::getEvent(), which only has a docblock hint).
     */
    class Observer
    {
        /**
         * The dispatched event.
         *
         * @return \Magento\Framework\Event
         */
        public function getEvent()
        {
            return new \Magento\Framework\Event();
        }
    }

    /**
     * Double of the observer contract.
     */
    interface ObserverInterface
    {
        /**
         * @param Observer $observer The dispatched event.
         * @return void
         */
        public function execute(Observer $observer);
    }
}

namespace Magento\Framework\App {
    /**
     * Double of Magento's database connection.
     */
    class ResourceConnection
    {
        /**
         * @param string $connectionName Connection name.
         * @return \Magento\Framework\DB\Adapter\AdapterInterface
         */
        public function getConnection($connectionName = 'default'): \Magento\Framework\DB\Adapter\AdapterInterface
        {
            throw new \RuntimeException('double: not implemented');
        }

        /**
         * @param string $modelEntity    Table name.
         * @param string $connectionName Connection name.
         * @return string
         */
        public function getTableName($modelEntity, $connectionName = 'default'): string
        {
            return (string) $modelEntity;
        }
    }

    /**
     * Double of Magento's version.
     */
    interface ProductMetadataInterface
    {
        /**
         * @return string
         */
        public function getVersion();
    }

    /**
     * Double of the current HTTP request.
     */
    interface RequestInterface
    {
        /**
         * @param string $key     Parameter name.
         * @param mixed  $default Default value.
         * @return mixed
         */
        public function getParam($key, $default = null);
    }
}

namespace Magento\Framework\App\Config {
    /**
     * Double of Magento's store configuration.
     */
    interface ScopeConfigInterface
    {
        /**
         * @param string           $path      Configuration path.
         * @param string           $scopeType Scope type.
         * @param int|string|null  $scopeCode Scope id/code.
         * @return mixed
         */
        public function getValue($path, $scopeType = 'default', $scopeCode = null);
    }

    /**
     * Double of a system.xml field's backend model base class (the
     * methods the plugin calls; a real one has a much heavier
     * constructor, irrelevant to the plugin's own beforeSave() override).
     */
    class Value
    {
        /**
         * @var string
         */
        private $value = '';

        /**
         * @param mixed ...$args Ignored: the real constructor's framework dependencies.
         */
        public function __construct(...$args)
        {
        }

        /**
         * @return string
         */
        public function getValue()
        {
            return $this->value;
        }

        /**
         * @param string $value Value.
         * @return $this
         */
        public function setValue($value)
        {
            $this->value = $value;
            return $this;
        }

        /**
         * @return $this
         */
        public function beforeSave()
        {
            return $this;
        }
    }
}

namespace Magento\Framework\App\Action {
    /**
     * Double of a controller answering to plain HTTP GET.
     */
    interface HttpGetActionInterface
    {
    }
}

namespace Magento\Framework\DB\Adapter {
    /**
     * Double of Magento's database adapter (the methods the plugin calls).
     */
    interface AdapterInterface
    {
        /**
         * @return \Magento\Framework\DB\Select
         */
        public function select();

        /**
         * @param string $table Table name.
         * @param array  $bind  Column => value.
         * @return int
         */
        public function insert($table, array $bind);

        /**
         * @param string       $table  Table name.
         * @param array        $data   Column => value.
         * @param string|array $fields Columns to update on duplicate.
         * @return int
         */
        public function insertOnDuplicate($table, array $data, $fields = []);

        /**
         * @param string       $table Table name.
         * @param array        $bind  Column => value.
         * @param string|array $where Where clause.
         * @return int
         */
        public function update($table, array $bind, $where = '');

        /**
         * @param string       $table Table name.
         * @param string|array $where Where clause.
         * @return int
         */
        public function delete($table, $where = '');

        /**
         * @param string|\Magento\Framework\DB\Select $sql       Query.
         * @param array                                $bind      Bind parameters.
         * @param mixed                                $fetchMode Fetch mode.
         * @return array
         */
        public function fetchAll($sql, $bind = [], $fetchMode = null);

        /**
         * @param string|\Magento\Framework\DB\Select $sql       Query.
         * @param array                                $bind      Bind parameters.
         * @param mixed                                $fetchMode Fetch mode.
         * @return array|false
         */
        public function fetchRow($sql, $bind = [], $fetchMode = null);

        /**
         * @param string|\Magento\Framework\DB\Select $sql  Query.
         * @param array                                $bind Bind parameters.
         * @return string
         */
        public function fetchOne($sql, $bind = []);

        /**
         * @param string $text  Text with a "?" placeholder.
         * @param mixed  $value Value.
         * @param mixed  $type  Bind type.
         * @param mixed  $count Number of placeholders.
         * @return string
         */
        public function quoteInto($text, $value, $type = null, $count = null);

        /**
         * @param string      $tableName  Table name.
         * @param string|null $schemaName Schema name.
         * @return true
         */
        public function dropTable($tableName, $schemaName = null);
    }
}

namespace Magento\Framework\DB {
    /**
     * Double of Magento's query builder (the methods the plugin calls).
     */
    class Select
    {
        /**
         * @param string       $table Table name.
         * @param array|string $cols  Columns.
         * @return $this
         */
        public function from($table, $cols = '*')
        {
            return $this;
        }

        /**
         * @param string $condition Condition.
         * @param mixed  $value     Value.
         * @return $this
         */
        public function where($condition, $value = null)
        {
            return $this;
        }

        /**
         * @param string|array $spec Order spec.
         * @return $this
         */
        public function order($spec)
        {
            return $this;
        }

        /**
         * @param int $count  Row count.
         * @param int $offset Offset.
         * @return $this
         */
        public function limit($count = null, $offset = null)
        {
            return $this;
        }
    }
}

namespace Magento\Framework\Option {
    /**
     * Double of a system.xml multiselect/select source model.
     */
    interface ArrayInterface
    {
        /**
         * @return array
         */
        public function toOptionArray();
    }
}

namespace Magento\Framework\Setup {
    /**
     * Double of the setup script context.
     */
    interface ModuleContextInterface
    {
    }

    /**
     * Double of the schema installer (the methods the plugin calls).
     */
    interface SchemaSetupInterface
    {
        /**
         * @return \Magento\Framework\DB\Adapter\AdapterInterface
         */
        public function getConnection();

        /**
         * @param string $tableName Table name.
         * @return string
         */
        public function getTable($tableName);

        /**
         * @return void
         */
        public function startSetup();

        /**
         * @return void
         */
        public function endSetup();
    }

    /**
     * Double of the uninstall script contract.
     */
    interface UninstallInterface
    {
        /**
         * @param SchemaSetupInterface   $setup   Schema installer.
         * @param ModuleContextInterface $context Module context.
         * @return void
         */
        public function uninstall(SchemaSetupInterface $setup, ModuleContextInterface $context);
    }
}

namespace Magento\Framework\Exception {
    /**
     * Double of Magento's "not found" exception.
     */
    class NoSuchEntityException extends \Exception
    {
        /**
         * @param \Magento\Framework\Phrase $phrase Message.
         */
        public function __construct(\Magento\Framework\Phrase $phrase)
        {
            parent::__construct((string) $phrase);
        }
    }

    /**
     * Double of Magento's own "expected" exception (its message is shown
     * to the user as is).
     */
    class LocalizedException extends \Exception
    {
        /**
         * @param \Magento\Framework\Phrase $phrase Message.
         */
        public function __construct(\Magento\Framework\Phrase $phrase)
        {
            parent::__construct((string) $phrase);
        }
    }
}

namespace Magento\Framework {
    /**
     * Double of Magento's translatable string.
     */
    class Phrase
    {
        /**
         * @var string
         */
        private string $text;

        /**
         * @param string $text Text.
         * @param array  $args Arguments.
         */
        public function __construct(string $text, array $args = [])
        {
            $this->text = $text;
        }

        /**
         * @return string
         */
        public function __toString(): string
        {
            return $this->text;
        }
    }

    /**
     * Double of Magento's object registry.
     */
    class Registry
    {
        /**
         * @param string $key Key.
         * @return mixed
         */
        public function registry($key)
        {
            return null;
        }
    }

    /**
     * Double of Magento's own URL builder.
     */
    interface UrlInterface
    {
        /**
         * @param string $routePath Route path.
         * @param array  $params    Route params.
         * @return string
         */
        public function getUrl($routePath = null, $params = null);
    }

    /**
     * Double of an event's own data (the methods the plugin calls; no
     * return type, matching the real Event, whose getters are magic
     * (it extends DataObject)).
     */
    class Event
    {
        /**
         * The order the event carries.
         *
         * @return \Magento\Sales\Model\Order
         */
        public function getOrder()
        {
            return new \Magento\Sales\Model\Order();
        }
    }
}

namespace Magento\Framework\Reflection {
    /**
     * Double of Magento's data interface object serializer.
     */
    class DataObjectProcessor
    {
        /**
         * @param object $dataObject     Object to serialize.
         * @param string $dataObjectType Data interface it implements.
         * @return array
         */
        public function buildOutputDataArray($dataObject, $dataObjectType)
        {
            return [];
        }
    }
}

namespace Magento\Framework\HTTP {
    /**
     * Double of Magento's HTTP client (the methods the plugin calls).
     */
    interface ClientInterface
    {
        /**
         * @param int $value Timeout, in seconds.
         * @return void
         */
        public function setTimeout($value);

        /**
         * @param array $headers Header name => value.
         * @return void
         */
        public function setHeaders($headers);

        /**
         * @param string $uri    URL.
         * @param mixed  $params Request body.
         * @return void
         */
        public function post($uri, $params);

        /**
         * @return string
         */
        public function getBody();

        /**
         * @return int
         */
        public function getStatus();
    }
}

namespace Magento\Framework\Math {
    /**
     * Double of Magento's own randomness helper.
     */
    class Random
    {
        /**
         * @param int $length Number of bytes.
         * @return string
         */
        public function getRandomBytes(int $length): string
        {
            return str_repeat('a', $length);
        }
    }
}

namespace Magento\Framework\Module {
    /**
     * Double of Magento's module list (the methods the plugin calls).
     */
    interface ModuleListInterface
    {
        /**
         * @param string $moduleName Module name.
         * @return array|null
         */
        public function getOne($moduleName);
    }
}

namespace Magento\Framework\Encryption {
    /**
     * Double of Magento's own encryption service (the method the plugin
     * calls).
     */
    interface EncryptorInterface
    {
        /**
         * @param string $data Encrypted data.
         * @return string
         */
        public function decrypt($data);
    }
}

namespace Magento\Framework\Component {
    /**
     * Double of Magento's component registrar (the methods registration.php
     * calls; not covered by a test, only analysed).
     */
    class ComponentRegistrar
    {
        public const MODULE = 'module';

        /**
         * @param string $type      Component type.
         * @param string $componentName Component name.
         * @param string $path      Absolute path to the component.
         * @return void
         */
        public static function register($type, $componentName, $path)
        {
        }
    }
}

namespace Magento\Framework\Data\Form\Element {
    /**
     * Double of a system.xml field's form element (the methods the plugin
     * calls).
     */
    class AbstractElement
    {
        /**
         * @param string $type Input type.
         * @return void
         */
        public function setType($type)
        {
        }

        /**
         * @return string
         */
        public function getClass()
        {
            return '';
        }

        /**
         * @param string $class CSS classes.
         * @return void
         */
        public function setClass($class)
        {
        }

        /**
         * @param string $key   Data key.
         * @param mixed  $value Value.
         * @return void
         */
        public function setData($key, $value = null)
        {
        }

        /**
         * @return string
         */
        public function getElementHtml()
        {
            return '';
        }

        /**
         * @return string
         */
        public function getHtmlId()
        {
            return '';
        }

        /**
         * @return mixed
         */
        public function getValue()
        {
            return '';
        }

        /**
         * @param mixed $value Value.
         * @return void
         */
        public function setValue($value)
        {
        }
    }
}

namespace Magento\Framework\Controller {
    /**
     * Double of Magento's result builder (the methods the plugin calls).
     */
    class ResultFactory
    {
        /**
         * Redirect result type.
         */
        public const TYPE_REDIRECT = 'redirect';

        /**
         * @param string $type Result type.
         * @param array  $data Result data.
         * @return ResultInterface
         */
        public function create($type, array $data = [])
        {
            throw new \RuntimeException('double: not implemented');
        }
    }

    /**
     * Double of a controller's result contract.
     */
    interface ResultInterface
    {
    }
}

namespace Magento\Framework\Controller\Result {
    /**
     * Double of a redirect result (the methods the plugin calls).
     */
    class Redirect
    {
        /**
         * @param string $path   Route path.
         * @param array  $params Route params.
         * @return $this
         */
        public function setPath($path, array $params = [])
        {
            return $this;
        }
    }
}

namespace Magento\Framework\Message {
    /**
     * Double of Magento's admin notice manager (the methods the plugin
     * calls).
     */
    interface ManagerInterface
    {
        /**
         * @param \Magento\Framework\Phrase|string $message Message.
         * @return $this
         */
        public function addSuccessMessage($message);
    }
}

namespace Magento\Backend\App {
    /**
     * Double of an adminhtml controller's base class (only the properties
     * and the methods the plugin calls; every other getter used by the
     * real base classes returns null through Mockery's
     * shouldIgnoreMissing() on the mocked Context).
     */
    class Action
    {
        /**
         * @var \Magento\Framework\App\RequestInterface
         */
        protected $_request;

        /**
         * @var \Magento\Framework\Message\ManagerInterface
         */
        protected $messageManager;

        /**
         * @var \Magento\Framework\Controller\ResultFactory
         */
        protected $resultFactory;

        /**
         * @param \Magento\Backend\App\Action\Context $context Action context.
         */
        public function __construct($context)
        {
            $this->_request = $context->getRequest();
            $this->messageManager = $context->getMessageManager();
            $this->resultFactory = $context->getResultFactory();
        }

        /**
         * @return \Magento\Framework\App\RequestInterface
         */
        public function getRequest()
        {
            return $this->_request;
        }
    }
}

namespace Magento\Backend\App\Action {
    /**
     * Double of the action context.
     */
    class Context
    {
        /**
         * @return \Magento\Framework\App\RequestInterface
         */
        public function getRequest()
        {
            throw new \RuntimeException('double: not implemented');
        }

        /**
         * @return \Magento\Framework\Message\ManagerInterface
         */
        public function getMessageManager()
        {
            throw new \RuntimeException('double: not implemented');
        }

        /**
         * @return \Magento\Framework\Controller\ResultFactory
         */
        public function getResultFactory()
        {
            throw new \RuntimeException('double: not implemented');
        }
    }
}

namespace Magento\Backend\Block {
    /**
     * Double of an adminhtml block (the methods the plugin calls).
     */
    class Template
    {
        /**
         * @var \Magento\Framework\UrlInterface
         */
        protected $_urlBuilder;

        /**
         * @param \Magento\Backend\Block\Template\Context $context Block context.
         * @param array                                    $data    Block data.
         */
        public function __construct($context, array $data = [])
        {
            $this->_urlBuilder = $context->getUrlBuilder();
        }

        /**
         * @param string $routePath Route path.
         * @param array  $params    Route params.
         * @return string
         */
        public function getUrl($routePath = null, $params = null)
        {
            return $this->_urlBuilder->getUrl($routePath, $params);
        }
    }
}

namespace Magento\Backend\Block\Template {
    /**
     * Double of the block context.
     */
    class Context
    {
        /**
         * @return \Magento\Framework\UrlInterface
         */
        public function getUrlBuilder()
        {
            throw new \RuntimeException('double: not implemented');
        }
    }
}

namespace Magento\Config\Block\System\Config\Form {
    /**
     * Double of a system.xml field's renderer.
     */
    class Field extends \Magento\Backend\Block\Template
    {
    }
}

namespace Magento\Sales\Api\Data {
    /**
     * Double of the order's own data interface (declared here, ahead of
     * Magento\Sales\Model\Order below, so it can implement it: real
     * Magento's Order does, and OrderRepositoryInterface::save() takes
     * this interface, not the concrete class).
     */
    interface OrderInterface
    {
    }
}

namespace Magento\Sales\Model {
    /**
     * Double of Magento's order (the methods the plugin calls).
     */
    class Order implements \Magento\Sales\Api\Data\OrderInterface
    {
        /**
         * @return string
         */
        public function getStatus()
        {
            return '';
        }

        /**
         * @param string $key Field name.
         * @return mixed
         */
        public function getOrigData($key = null)
        {
            return null;
        }

        /**
         * @return int
         */
        public function getEntityId()
        {
            return 0;
        }

        /**
         * @return \Magento\Sales\Api\Data\OrderAddressInterface|null
         */
        public function getShippingAddress()
        {
            return null;
        }

        /**
         * @return int|null
         */
        public function getQuoteId()
        {
            return null;
        }

        /**
         * @param string $comment Comment.
         * @param bool|string $status Status.
         * @return mixed
         */
        public function addStatusHistoryComment($comment, $status = false)
        {
            return null;
        }
    }
}

namespace Magento\Sales\Model\Order {
    /**
     * Double of Magento's order statuses/states configuration (the methods
     * the plugin calls).
     */
    class Config
    {
        /**
         * @return array
         */
        public function getStatuses()
        {
            return [];
        }
    }
}

namespace Magento\Sales\Api {
    /**
     * Double of Magento's order reader (the methods the plugin calls).
     */
    interface OrderRepositoryInterface
    {
        /**
         * @param int $id Order id (entity_id).
         * @return \Magento\Sales\Api\Data\OrderInterface
         */
        public function get($id);

        /**
         * @param \Magento\Sales\Api\Data\OrderInterface $entity Order.
         * @return \Magento\Sales\Api\Data\OrderInterface
         */
        public function save($entity);
    }
}

namespace Magento\Sales\Api\Data {
    /**
     * Double of the order address's own data interface.
     */
    interface OrderAddressInterface
    {
    }
}

namespace Magento\Store\Model {
    /**
     * Double of Magento's store manager (the methods the plugin calls).
     */
    interface StoreManagerInterface
    {
        /**
         * @param int|string|null $storeId Store id.
         * @return \Magento\Store\Api\Data\StoreInterface
         */
        public function getStore($storeId = null);
    }
}

namespace Magento\Store\Api\Data {
    /**
     * Double of the store's own data interface (the methods the plugin
     * calls; getBaseUrl() is only on the concrete Store model, but every
     * store the plugin ever handles is one, so it is declared here too for
     * a lighter double).
     */
    interface StoreInterface
    {
        /**
         * @param string $type   URL type.
         * @param bool   $secure Force http(s).
         * @return string
         */
        public function getBaseUrl($type = 'link', $secure = null);
    }
}

namespace Magento\Checkout\Model {
    /**
     * Double of Magento's own shipping-information-saving service (the
     * plugin's plugin target; none of its own methods are called).
     */
    class ShippingInformationManagement
    {
    }
}

namespace Magento\Checkout\Block\Checkout {
    /**
     * Double of Magento's own checkout layout processor (the plugin's
     * plugin target; none of its own methods are called).
     */
    class LayoutProcessor
    {
    }
}

namespace Magento\Checkout\Api\Data {
    /**
     * Double of the checkout's own submitted shipping information (the
     * methods the plugin calls).
     */
    interface ShippingInformationInterface
    {
        /**
         * @return \Magento\Quote\Api\Data\AddressInterface|null
         */
        public function getShippingAddress();

        /**
         * @return \Magento\Checkout\Api\Data\ShippingInformationExtensionInterface|null
         */
        public function getExtensionAttributes();
    }

    /**
     * Double of the generated extension attributes class (the method the
     * plugin calls; a real one is generated from etc/extension_attributes.xml).
     */
    interface ShippingInformationExtensionInterface
    {
        /**
         * @return string|null
         */
        public function getBillmysalesCheckoutFields();
    }
}

namespace Magento\Quote\Api\Data {
    /**
     * Double of a quote address (the methods the plugin calls).
     */
    interface AddressInterface
    {
    }
}

namespace {
    if (!function_exists('__')) {
        /**
         * Double of Magento's global translate helper.
         *
         * @param string $text Text.
         * @param mixed  ...$args Arguments.
         * @return \Magento\Framework\Phrase
         */
        function __($text, ...$args)
        {
            return new \Magento\Framework\Phrase($text, $args);
        }
    }
}
