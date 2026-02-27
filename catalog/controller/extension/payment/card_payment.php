<?php
class ControllerExtensionPaymentCardPayment extends Controller {
	public function index() {
		$data['continue'] = $this->url->link('checkout/success');

		return $this->load->view('extension/payment/card_payment', $data);
	}

	public function confirm() {
		if (isset($this->session->data['payment_method']['code']) && $this->session->data['payment_method']['code'] == 'card_payment') {
			$this->load->model('checkout/order');

			$this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('payment_card_payment_order_status_id'));
		}
	}
}
