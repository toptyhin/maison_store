<?php
class ModelExtensionShippingCdek extends Model {
	public function getQuote($address) {
		$this->load->language('extension/shipping/cdek');

		$method_data = array();

		$quote_data = array();

			$cost_pickup = (float)($this->config->get('shipping_cdek_cost_pickup') ?: 300);
			$cost_courier = (float)($this->config->get('shipping_cdek_cost_courier') ?: 500);
			$tax_class_id = (int)$this->config->get('shipping_cdek_tax_class_id');

			$quote_data['pickup_point'] = array(
				'code'         => 'cdek.pickup_point',
				'title'        => $this->language->get('text_pickup_point'),
				'cost'         => $cost_pickup,
				'tax_class_id' => $tax_class_id,
				'text'         => $this->currency->format($this->tax->calculate($cost_pickup, $tax_class_id, $this->config->get('config_tax')), $this->session->data['currency'])
			);

			$quote_data['courier'] = array(
				'code'         => 'cdek.courier',
				'title'        => $this->language->get('text_courier'),
				'cost'         => $cost_courier,
				'tax_class_id' => $tax_class_id,
				'text'         => $this->currency->format($this->tax->calculate($cost_courier, $tax_class_id, $this->config->get('config_tax')), $this->session->data['currency'])
			);

			$method_data = array(
				'code'       => 'cdek',
				'title'      => $this->language->get('text_title'),
				'quote'      => $quote_data,
				'sort_order' => $this->config->get('shipping_cdek_sort_order'),
				'error'      => false
		);

		return $method_data;
	}
}
