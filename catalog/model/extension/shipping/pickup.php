<?php
class ModelExtensionShippingPickup extends Model {
	function getQuote($address) {
		$this->load->language('extension/shipping/pickup');

		$method_data = array();

		$quote_data = array();

			$quote_data['pickup'] = array(
				'code'         => 'pickup.pickup',
				'title'        => $this->language->get('text_description'),
				'cost'         => 0.00,
				'tax_class_id' => 0,
				'text'         => $this->currency->format(0.00, $this->session->data['currency'])
			);

			$method_data = array(
				'code'       => 'pickup',
				'title'      => $this->language->get('text_title'),
				'quote'      => $quote_data,
				'sort_order' => $this->config->get('shipping_pickup_sort_order'),
				'error'      => false
		);

		return $method_data;
	}
}