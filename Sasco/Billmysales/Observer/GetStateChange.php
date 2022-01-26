<?php

/**
 * BillMySales: Pasarela de Facturación
 * Copyright (C) SASCO SpA (https://sasco.cl)
 *
 * Este programa es software libre: usted puede redistribuirlo y/o
 * modificarlo bajo los términos de la Licencia Pública General Affero de GNU
 * publicada por la Fundación para el Software Libre, ya sea la versión
 * 3 de la Licencia, o (a su elección) cualquier versión posterior de la
 * misma.
 *
 * Este programa se distribuye con la esperanza de que sea útil, pero
 * SIN GARANTÍA ALGUNA; ni siquiera la garantía implícita
 * MERCANTIL o de APTITUD PARA UN PROPÓSITO DETERMINADO.
 * Consulte los detalles de la Licencia Pública General Affero de GNU para
 * obtener una información más detallada.
 *
 * Debería haber recibido una copia de la Licencia Pública General Affero de GNU
 * junto a este programa.
 * En caso contrario, consulte <http://www.gnu.org/licenses/agpl.html>.
 */

namespace Sasco\Billmysales\Observer;

use Magento\Framework\Event\ObserverInterface;
use Psr\Log\LoggerInterface;

class GetStateChange implements ObserverInterface
{
    /*
    * BILLMYSALES_WEBHOOK_URL: Url del webhook correspondiente a la pasarela de facturación creada en BillMySales.
    * BILLMYSALES_WEBHOOK_TOKEN: Token que permite validar que los datos son enviados desde Magento,
    * este debe ser el mismo que se configuró en el origen de datos en la pasarela de facturación en BillMySales.
    */
    const BILLMYSALES_WEBHOOK_URL = '';
    const BILLMYSALES_WEBHOOK_TOKEN = '';

    protected $logger;

    public function __construct(LoggerInterface$logger)
    {
        $this->logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        try {
            $order = $observer->getEvent()->getOrder();
            $order_normalized = $this->order_normalized($order);
            $this->logger->info(json_encode($order_normalized));
            $response = $this->api_post(self::BILLMYSALES_WEBHOOK_URL, $order_normalized);
            $this->logger->info(json_encode($response));
        }catch (\Exception $e) {
            $this->logger->info($e->getMessage());
        }
    }
    /**
     * Método que hace un llamado por POST a BillMySales
    */
    private function api_post($url, $data)
    {
        $data = json_encode($data);
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'X-MagentoBMS-Hmac-Sha256: ' . $this->sign_data($data)
            ],
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_RETURNTRANSFER => true,
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        return json_decode($response, true);
    }

    /**
     * Método que calcula la firma de los datos
    */
    private function sign_data($data)
    {
        return base64_encode(hash_hmac('sha256', $data, self::BILLMYSALES_WEBHOOK_TOKEN, true));
    }

    /**
     * Método para normalizar estructura de la orden
    */
    private function order_normalized($order)
    {
        // Get Order Information
        $order_data = $order->getData();
        // Get Billing Information
        $order_data['billing_address'] = $order->getBillingAddress()->getData();
        // Get Payment Information
        $order_data['payment'] = $order->getPayment()->getData();
        // get shipping details
        $shipping = $order->getShippingAddress();
        $order_data['shipping_address'] = null;
        if ($shipping != null)
        {
            $order_data['shipping_address'] = $order->getShippingAddress()->getData();
        }
        // Get Order Items
        $orderItems = $order->getAllItems();
        $order_data['items'] = array();
        foreach ($orderItems as $item)
        {
            array_push(
                $order_data['items'],
                $item->getData()
            );
        }
        return $order_data;
    }
}
