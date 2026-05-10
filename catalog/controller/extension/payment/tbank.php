<?php

class ControllerExtensionPaymentTBank extends Controller
{
    public function index()
    {
        $this->load->language('extension/payment/tbank');
        $this->load->model('extension/payment/tbank');
        $url = $this->model_extension_payment_tbank->getPaymentUrl();
        $data = [
            'status' => 'error',
        ];

        if ($url) {
            $data = [
                'status' => 'success',
                'url' => $url,
            ];
        }

        return $this->load->view('extension/payment/tbank', $data);
    }

    public function callback()
    {
        $request = json_decode(file_get_contents("php://input"));
        $request->Success = $request->Success ? 'true' : 'false';
        $request = (array)$request;
        $this->load->model('extension/payment/tbank');

        if ($request['Token'] !== $this->model_extension_payment_tbank->getToken($request)) {
            die('TOKEN INCORRECT');
        }

        $this->load->model('checkout/order');
        $order = $this->model_checkout_order->getOrder($request['OrderId']);

        if (!$order) {
            die('ORDER NOT FOUND');
        }

        if ((int)$request['Amount'] !== (int)round($order['total'] * 100)) {
            die('AMOUNTS DO NOT MATCH');
        }

        switch ($request['Status']) {
            case 'AUTHORIZED':
                $status = $this->config->get('payment_tbank_authorized');
                break;
            case 'CONFIRMED':
                $status = $this->config->get('payment_tbank_confirmed');
                break;
            case 'CANCELED':
                $status = $this->config->get('payment_tbank_canceled');
                break;
            case 'REJECTED':
                $status = $this->config->get('payment_tbank_rejected');
                break;
            case 'REVERSED':
                $status = $this->config->get('payment_tbank_reversed');
                break;
            case 'REFUNDED':
                $status = $this->config->get('payment_tbank_refunded');
                break;
            default:
                $status = null;
                break;
        }

        if (!$status) {
            die('STATUS INCORRECT');
        }

        $this->load->model('checkout/order');
        $this->model_checkout_order->addOrderHistory((int)$request['OrderId'], $status);

        die('OK');
    }

    public function failure()
    {
        $this->load->language('extension/payment/tbank');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        return $this->response->setOutput($this->load->view('extension/payment/tbank_failure', $data));
    }

    public function success()
    {
        $this->load->language('extension/payment/tbank');
        $this->cart->clear();
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        return $this->response->setOutput($this->load->view('extension/payment/tbank_success', $data));
    }
}