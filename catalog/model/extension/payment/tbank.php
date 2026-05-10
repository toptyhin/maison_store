<?php

class ModelExtensionPaymentTBank extends Model
{
    protected static $vats = [
        22 => 'vat22',
        20 => 'vat20',
        10 => 'vat10',
        7  => 'vat7',
        5  => 'vat5',
        0  => 'vat0',
        'none' => 'none',
    ];

    protected static $defaultVat = '';

    public function __construct($registry)
    {
        self::$defaultVat = self::$vats['none'];
        parent::__construct($registry);
    }

    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/tbank');

        return array(
            'code' => 'tbank',
            'title' => $this->language->get('text_title'),
            'terms' => '',
            'sort_order' => $this->config->get('payment_tbank_sort_order')
        );
    }

    public function getPaymentUrl()
    {
        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $email = $order['email'] ? $order['email'] : '';

        $data = array(
            'OrderId' => $order['order_id'],
            'Amount' => round($order['total'] * 100),
            'DATA' => array(
                'Email' => $email,
                'Connection_type' => 'opencart3.0_v2.2'
            ),
        );

        if ($this->config->get('payment_tbank_enabled_taxation')) {
            $data['Receipt'] = array(
                'Email' => $email,
                'Taxation' => $this->config->get('payment_tbank_taxation'),
                'Items' => $this->getReceipt(),
            );
            if (null != ($this->config->get('payment_tbank_email_company'))){
                $data['Receipt']['EmailCompany'] = substr($this->config->get('payment_tbank_email_company'), 0, 64);
            }
            if ($this->config->get('payment_tbank_ffd_12_v') == 'ffd_1_2_enabled'){
                $data['Receipt']['FfdVersion'] = "1.2";
            }
        }

        if ($this->config->get('payment_tbank_language') == 'en') {
            $data['Language'] = 'en';
        }

        require_once __DIR__ . '/TBankMerchantAPI.php';

        $tbank = new TBankMerchantAPI(
            $this->config->get('payment_tbank_terminal'),
            $this->config->get('payment_tbank_password')
        );

        $request = $tbank->buildQuery('Init', $data);
        $request = json_decode($request);

        return isset($request->PaymentURL) ? $request->PaymentURL : '';
    }

    function logs($data, $request, $file)
    {
        // log send
        $log = '[' . date('D M d H:i:s Y', time()) . '] ';
        $log .= json_encode($data, JSON_UNESCAPED_UNICODE);
        $log .= "\n";
        file_put_contents(dirname(__FILE__) . $file, $log, FILE_APPEND);

        $log = '[' . date('D M d H:i:s Y', time()) . '] ';
        $log .= $request;
        $log .= "\n";
        file_put_contents(dirname(__FILE__) . $file, $log, FILE_APPEND);
    }
    function log($data)
    {
        // log send
        $log = '[' . date('D M d H:i:s Y', time()) . '] ';
        $log .= $data;
        $log .= "\n";
        file_put_contents(dirname(__FILE__) . "/tbank.log", $log, FILE_APPEND);
    }

    public function getReceipt()
    {
        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $amount = round($order['total'] * 100);
        $receiptItems = [];
        $products = $this->cart->getProducts();
        $session = $this->cart->session->data;

        foreach ($products as $product) {
            $vat = $this->getVat($product['price'], $product['tax_class_id']);
            $price = round($this->cart->tax->calculate($product['price'], $product['tax_class_id'], true) * 100);

            $item = array(
                'Name' => mb_substr($product['name'], 0, 64),
                "Price" => $price,
                "Quantity" => $product['quantity'],
                "Amount" => round($price * $product['quantity']),
                "PaymentMethod" => trim($this->config->get('payment_tbank_method')),
                "PaymentObject" => trim($this->config->get('payment_tbank_object')),
                "Tax" => $vat,
            );
            if ($this->config->get('payment_tbank_ffd_12_v') == 'ffd_1_2_enabled'){
                $item['MeasurementUnit'] = "pc";
            }
            $receiptItems[] = $item;

        }

        $shipping = false;
        if ($this->hasShipping()) {
            $vat = $this->getVat($session['shipping_method']['cost'], $session['shipping_method']['tax_class_id']);
            $price = round($this->cart->tax->calculate($session['shipping_method']['cost'], $session['shipping_method']['tax_class_id'], true) * 100);

            if ($price) {
                $shipping = true;
                $item = array(
                    'Name' => mb_substr($session['shipping_method']['title'], 0, 64),
                    'Price' => $price,
                    'Quantity' => 1,
                    'Amount' => $price,
                    "PaymentMethod" => trim($this->config->get('payment_tbank_method')),
                    "PaymentObject" => 'service',
                    'Tax' => $vat,
                );
                if ($this->config->get('payment_tbank_ffd_12_v') == 'ffd_1_2_enabled'){
                    $item['MeasurementUnit'] = "pc";
                }
                $receiptItems[] = $item;
            }
        }
        return $this->balanceAmount($shipping, $receiptItems, $amount);
    }

    public function hasShipping()
    {
        return isset($this->cart->session->data['shipping_method']['cost']) &&
            $this->cart->session->data['shipping_method']['cost'];
    }

    public function getVat($price, $taxClassId)
    {
        if (!$taxClassId) {
            return self::$defaultVat;
        } else {
            $taxClassRates = $this->tax->getRates($price, $taxClassId);
            //в tax class может быть несколько налогов
            $rateData = array_shift($taxClassRates);

            //P - percentage
            if ($rateData['type'] != 'P') {
                return self::$defaultVat;
            }

            return isset(self::$vats[(int)$rateData['rate']]) ? self::$vats[(int)$rateData['rate']] : self::$defaultVat;
        }
    }

    public function balanceAmount($isShipping, $items, $amount)
    {
        $itemsWithoutShipping = $items;

        if ($isShipping) {
            $shipping = array_pop($itemsWithoutShipping);
        }

        $sum = 0;

        foreach ($itemsWithoutShipping as $item) {
            $sum += $item['Amount'];
        }

        if (isset($shipping)) {
            $sum += $shipping['Amount'];
        }

        if ($sum != $amount) {
            $sumAmountNew = 0;
            $difference = $amount - $sum;
            $amountNews = array();

            foreach ($itemsWithoutShipping as $key => $item) {
                $itemsAmountNew = $item['Amount'] + floor($difference * $item['Amount'] / $sum);
                $amountNews[$key] = $itemsAmountNew;
                $sumAmountNew += $itemsAmountNew;
            }

            if (isset($shipping)) {
                $sumAmountNew += $shipping['Amount'];
            }

            if ($sumAmountNew != $amount) {
                $max_key = array_keys($amountNews, max($amountNews))[0];    // ключ макс значения
                $amountNews[$max_key] = max($amountNews) + ($amount - $sumAmountNew);
            }

            foreach ($amountNews as $key => $item) {
                $items[$key]['Amount'] = $amountNews[$key];
            }
        }

        return $items;
    }


    public function getToken($data)
    {
        $data['Password'] = htmlspecialchars_decode($this->config->get('payment_tbank_password'));
        unset($data['Token']);
        if (isset($data['DATA']))
            unset($data['DATA']);
        ksort($data);
        $values = implode('', array_values($data));
        return hash('sha256', $values);
    }
}