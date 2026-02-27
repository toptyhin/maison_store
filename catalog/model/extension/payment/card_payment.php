<?php
class ModelExtensionPaymentCardPayment extends Model {
	public function getMethod($address, $total) {
		$this->load->language('extension/payment/card_payment');

		$method_data = array();

		if ($this->config->get('payment_card_payment_status')) {
			$method_data = array(
				'code'       => 'card_payment',
				'title'      => $this->language->get('text_title'),
				'terms'      => '',
				'sort_order' => $this->config->get('payment_card_payment_sort_order')
			);
		}

		return $method_data;
	}
}
