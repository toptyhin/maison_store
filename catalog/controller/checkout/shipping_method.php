<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerCheckoutShippingMethod extends Controller {
	public function index() {
		$this->load->language('checkout/checkout');

		// Минимальный адрес для расчёта методов, если не задан
		if (!isset($this->session->data['shipping_address']) && $this->cart->hasShipping()) {
			$this->load->model('localisation/country');
			$this->load->model('localisation/zone');
			$country_id = (int)$this->config->get('config_country_id') ?: 176;
			$country_info = $this->model_localisation_country->getCountry($country_id);
			$this->session->data['shipping_address'] = array(
				'firstname' => '', 'lastname' => '', 'company' => '', 'address_1' => '', 'address_2' => '',
				'postcode' => '', 'city' => '', 'country_id' => $country_id, 'zone_id' => 0,
				'country' => $country_info ? $country_info['name'] : '', 'zone' => '',
				'iso_code_2' => $country_info ? $country_info['iso_code_2'] : '',
				'iso_code_3' => $country_info ? $country_info['iso_code_3'] : '',
				'zone_code' => '', 'address_format' => $country_info ? $country_info['address_format'] : '',
				'custom_field' => array()
			);
		}

		if (isset($this->session->data['shipping_address'])) {
			$this->loadQuotes();
		}

		if (empty($this->session->data['shipping_methods'])) {
			$data['error_warning'] = sprintf($this->language->get('error_no_shipping'), $this->url->link('information/contact'));
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['shipping_methods'])) {
			$data['shipping_methods'] = $this->session->data['shipping_methods'];
		} else {
			$data['shipping_methods'] = array();
		}

		if (isset($this->session->data['shipping_method']['code'])) {
			$data['code'] = $this->session->data['shipping_method']['code'];
		} elseif (!empty($this->session->data['shipping_methods'])) {
			$providers = array_keys($this->session->data['shipping_methods']);
			$first_provider = $providers[0];
			$methods = array_keys($this->session->data['shipping_methods'][$first_provider]['quote']);
			$first_method = $methods[0];
			$data['code'] = $first_provider . '.' . $first_method;
		} else {
			$data['code'] = 'cdek.courier';
		}

		
		$code_parts = explode('.', $data['code']);
		$data['provider'] = $code_parts[0] ?? 'cdek';
		$data['method'] = $code_parts[1] ?? 'courier';

		if (isset($this->session->data['comment'])) {
			$data['comment'] = $this->session->data['comment'];
		} else {
			$data['comment'] = '';
		}
		
		if (isset($this->request->get['json'])) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($data));
			return;
		}

	
		return $this->load->view('checkout/shipping_method', $data);
	}

	/**
	 * Загружает доступные методы доставки в сессию.
	 */
	private function loadQuotes() {
		$method_data = array();
		$this->load->model('setting/extension');
		$results = $this->model_setting_extension->getExtensions('shipping');

		foreach ($results as $result) {
			if ($this->config->get('shipping_' . $result['code'] . '_status')) {
				if ($result['code'] == 'free') {
					continue;
				}
				$this->load->model('extension/shipping/' . $result['code']);
				$quote = $this->{'model_extension_shipping_' . $result['code']}->getQuote($this->session->data['shipping_address']);
				if ($quote) {
					$method_data[$result['code']] = array(
						'title'      => $quote['title'],
						'quote'      => $quote['quote'],
						'sort_order' => $quote['sort_order'],
						'error'      => $quote['error']
					);
				}
			}
		}

		$sort_order = array();
		foreach ($method_data as $key => $value) {
			$sort_order[$key] = $value['sort_order'];
		}
		array_multisort($sort_order, SORT_ASC, $method_data);
		$this->session->data['shipping_methods'] = $method_data;
	}

	public function save() {
		$this->load->language('checkout/checkout');

		$json = array();

		// Validate if shipping is required. If not the customer should not have reached this page.
		if (!$this->cart->hasShipping()) {
			$json['redirect'] = $this->url->link('checkout/checkout', '', true);
		}

		// Validate if shipping address has been set.
		if (!isset($this->session->data['shipping_address'])) {
			$json['redirect'] = $this->url->link('checkout/checkout', '', true);
		}

		// shipping_methods может быть сброшен в guest/save, saveShippingAddress и др. — восстанавливаем при необходимости
		if (!$json && empty($this->session->data['shipping_methods']) && isset($this->session->data['shipping_address'])) {
			$this->loadQuotes();
		}

		// Validate cart has products and has stock.
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart');
		}

		// Validate minimum quantity requirements.
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$product_total = 0;

			foreach ($products as $product_2) {
				if ($product_2['product_id'] == $product['product_id']) {
					$product_total += $product_2['quantity'];
				}
			}

			if ($product['minimum'] > $product_total) {
				$json['redirect'] = $this->url->link('checkout/cart');

				break;
			}
		}


		if (!isset($this->request->post['shipping_method'])) {
			$json['error']['warning'] = $this->language->get('error_shipping');
		} else {
			$shipping = explode('.', $this->request->post['shipping_method']);

			if (!isset($shipping[0]) || !isset($shipping[1]) || !isset($this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]])) {
				$json['error']['warning'] = $this->language->get('error_shipping');
			}
		}

		if (!$json) {
			$this->session->data['shipping_method'] = $this->session->data['shipping_methods'][$shipping[0]]['quote'][$shipping[1]];

			$this->session->data['comment'] = isset($this->request->post['comment']) ? strip_tags($this->request->post['comment']) : '';

			// Пересчёт тоталов с учётом стоимости доставки для обновления блока «Ваш заказ»
			$totals = array();
			$taxes = $this->cart->getTaxes();
			$total = 0;
			$total_data = array('totals' => &$totals, 'taxes' => &$taxes, 'total' => &$total);
			$this->load->model('setting/extension');
			$sort_order = array();
			$results = $this->model_setting_extension->getExtensions('total');
			foreach ($results as $key => $value) {
				$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
			}
			array_multisort($sort_order, SORT_ASC, $results);
			foreach ($results as $result) {
				if ($this->config->get('total_' . $result['code'] . '_status')) {
					$this->load->model('extension/total/' . $result['code']);
					$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
				}
			}
			$sort_order = array();
			foreach ($totals as $key => $value) {
				$sort_order[$key] = $value['sort_order'];
			}
			array_multisort($sort_order, SORT_ASC, $totals);
			$json['sub_total_text'] = '';
			$json['sub_total_before_discount_text'] = '';
			$json['shipping_text'] = '';
			$json['order_total_text'] = '';
			$json['discount_text'] = '';
			$discount_value = 0;
			$json['sub_total_before_discount_text'] = $this->currency->format($this->cart->getSubTotalBeforeDiscounts(), $this->session->data['currency']);
			foreach ($totals as $row) {
				if (!empty($row['code']) && $row['code'] == 'sub_total') {
					$json['sub_total_text'] = $this->currency->format($row['value'], $this->session->data['currency']);
				}
				if (!empty($row['code']) && $row['code'] == 'total') {
					$json['order_total_text'] = $this->currency->format($row['value'], $this->session->data['currency']);
				}
				if (!empty($row['code']) && $row['code'] == 'shipping') {
					$json['shipping_text'] = $this->currency->format($row['value'], $this->session->data['currency']);
				}
				if ($row['value'] < 0) {
					$discount_value += $row['value'];
				}
			}
			if ($discount_value < 0) {
				$json['discount_text'] = $this->currency->format($discount_value, $this->session->data['currency']);
			}
			if ($json['shipping_text'] === '' && $this->cart->hasShipping()) {
				$json['shipping_text'] = '—';
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}