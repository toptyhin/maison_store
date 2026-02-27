<?php
class ModelExtensionShippingFree extends Model {
	function getQuote($address) {
		$this->load->language('extension/shipping/free');

		$status = true;

		if ($this->cart->getSubTotal() < $this->config->get('shipping_free_total')) {
			$status = false;
		}

		$method_data = array();

		if ($status) {
			$quote_data = array();

			$quote_data['free'] = array(
				'code'         => 'free.free',
				'title'        => $this->language->get('text_description'),
				'cost'         => 0.00,
				'tax_class_id' => 0,
				'text'         => $this->currency->format(0.00, $this->session->data['currency'])
			);

			$method_data = array(
				'code'       => 'free',
				'title'      => $this->language->get('text_title'),
				'quote'      => $quote_data,
				'sort_order' => $this->config->get('shipping_free_sort_order'),
				'error'      => false
			);
		}

		return $method_data;
	}
}