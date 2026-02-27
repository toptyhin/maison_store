<?php
class ModelExtensionShippingFlat extends Model {
	function getQuote($address) {
		$this->load->language('extension/shipping/flat');

		$method_data = array();

		$quote_data = array();

		$quote_data['flat'] = array(
			'code'         => 'flat.flat',
			'title'        => $this->language->get('text_description'),
			'cost'         => $this->config->get('shipping_flat_cost'),
			'tax_class_id' => $this->config->get('shipping_flat_tax_class_id'),
			'text'         => $this->currency->format($this->tax->calculate($this->config->get('shipping_flat_cost'), $this->config->get('shipping_flat_tax_class_id'), $this->config->get('config_tax')), $this->session->data['currency'])
		);

		$method_data = array(
			'code'       => 'flat',
			'title'      => $this->language->get('text_title'),
			'quote'      => $quote_data,
			'sort_order' => $this->config->get('shipping_flat_sort_order'),
			'error'      => false
		);

		return $method_data;
	}
}