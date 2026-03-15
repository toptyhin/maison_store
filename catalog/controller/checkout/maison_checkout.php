<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerCheckoutMaisonCheckout extends Controller {
	public function index() {
		// Validate cart has products and has stock.
		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$this->response->redirect($this->url->link('checkout/cart'));
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
				$this->response->redirect($this->url->link('checkout/cart'));
			}
		}

		$this->load->language('checkout/checkout');

		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->setRobots('noindex,follow');

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_cart'),
			'href' => $this->url->link('checkout/cart')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('checkout/maison_checkout', '', true)
		);

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];
			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		$data['logged'] = $this->customer->isLogged();

		if (isset($this->session->data['account'])) {
			$data['account'] = $this->session->data['account'];
		} else {
			$data['account'] = '';
		}

		$data['shipping_required'] = $this->cart->hasShipping();

		// Totals for order summary
		$totals = array();
		$taxes = $this->cart->getTaxes();
		$total = 0;

		$total_data = array(
			'totals' => &$totals,
			'taxes'  => &$taxes,
			'total'  => &$total
		);

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

		$data['totals'] = array();
		$data['sub_total_text'] = '';
		$data['sub_total_before_discount_text'] = $this->currency->format($this->cart->getSubTotalBeforeDiscounts(), $this->session->data['currency']);
		$data['order_total_text'] = '';
		$data['shipping_text'] = '';
		$data['discount_text'] = '';
		$data['items_count'] = 0;
		$discount_value = 0;

		foreach ($totals as $row) {
			$data['totals'][] = array(
				'title' => $row['title'],
				'text'  => $this->currency->format($row['value'], $this->session->data['currency'])
			);

			if (!empty($row['code']) && $row['code'] == 'sub_total') {
				$data['sub_total_text'] = $this->currency->format($row['value'], $this->session->data['currency']);
			}

			if (!empty($row['code']) && $row['code'] == 'total') {
				$data['order_total_text'] = $this->currency->format($row['value'], $this->session->data['currency']);
			}

			if (!empty($row['code']) && $row['code'] == 'shipping') {
				$data['shipping_text'] = $this->currency->format($row['value'], $this->session->data['currency']);
			}

			if ($row['value'] < 0) {
				$discount_value += $row['value'];
			}
		}

		if ($discount_value < 0) {
			$data['discount_text'] = $this->currency->format($discount_value, $this->session->data['currency']);
		}

		foreach ($products as $product) {
			$data['items_count'] += $product['quantity'];
		}

		if ($data['shipping_text'] === '' && $data['shipping_required']) {
			$data['shipping_text'] = '—';
		}
		if ($data['shipping_text'] === '' && !$data['shipping_required']) {
			$data['shipping_text'] = 'Бесплатно';
		}

		if (!isset($this->session->data['payment_address']) || !isset($this->session->data['shipping_address'])) {
			$this->load->model('localisation/country');
			$this->load->model('localisation/zone');
			$country_id = (int)$this->config->get('config_country_id') ?: 176;
			$zone_id = 0; // 0 = все регионы, совпадает с zone_to_geo_zone где zone_id=0
			$country_info = $this->model_localisation_country->getCountry($country_id);
			$zone_info = $this->model_localisation_zone->getZone($zone_id);
		}

		// Для залогиненных: инициализация из адреса по умолчанию
		if ($data['logged'] && !isset($this->session->data['payment_address'])) {
			$this->load->model('account/address');
			$address_id = $this->customer->getAddressId();
			if ($address_id) {
				$addr = $this->model_account_address->getAddress($address_id);
				if ($addr) {
					$this->session->data['payment_address'] = $addr;
					if ($data['shipping_required']) {
						$this->session->data['shipping_address'] = $addr;
					}
				}
			}
		}

		// Инициализация адреса по умолчанию для отображения методов доставки сразу при загрузке
		if ($data['shipping_required'] && !isset($this->session->data['shipping_address'])) {
			$this->session->data['shipping_address'] = array(
				'firstname' => '',
				'lastname' => '',
				'company' => '',
				'address_1' => '',
				'address_2' => '',
				'postcode' => '',
				'city' => '',
				'country_id' => $country_id,
				'zone_id' => $zone_id,
				'country' => $country_info ? $country_info['name'] : '',
				'zone' => $zone_info ? $zone_info['name'] : '',
				'iso_code_2' => $country_info ? $country_info['iso_code_2'] : '',
				'iso_code_3' => $country_info ? $country_info['iso_code_3'] : '',
				'zone_code' => $zone_info ? $zone_info['code'] : '',
				'address_format' => $country_info ? $country_info['address_format'] : '',
				'custom_field' => array()
			);
		}

		// Загрузка доступных методов доставки при наличии адреса
		if ($data['shipping_required'] && isset($this->session->data['shipping_address'])) {
			$data['shipping_method_html'] = $this->load->controller('checkout/shipping_method');
		} else {
			$data['shipping_method_html'] = '';
		}

		if (!isset($this->session->data['payment_address'])) {
			$this->session->data['payment_address'] = array(
				'firstname' => '',
				'lastname' => '',
				'company' => '',
				'address_1' => '',
				'address_2' => '',
				'postcode' => '',
				'city' => '',
				'country_id' => $country_id,
				'zone_id' => $zone_id,
				'country' => $country_info ? $country_info['name'] : '',
				'zone' => $zone_info ? $zone_info['name'] : '',
				'iso_code_2' => $country_info ? $country_info['iso_code_2'] : '',
				'iso_code_3' => $country_info ? $country_info['iso_code_3'] : '',
				'zone_code' => $zone_info ? $zone_info['code'] : '',
				'address_format' => $country_info ? $country_info['address_format'] : '',
				'custom_field' => array()
			);
		}

		$data['payment_method_html'] = $this->load->controller('checkout/payment_method');

		$data['dadata_token'] = $this->config->get('config_dadata_token') ?: '85cf2f2c09d828596149c915817a2e19b5335522';

		$data['cart_link'] = $this->url->link('checkout/cart');
		$data['coupon_action'] = $this->url->link('extension/total/coupon/coupon', '', true);

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('checkout/maison_checkout', $data));
	}

	/**
	 * Сохранение данных покупателя: имя, телефон, email.
	 * Только для гостей.
	 */
	public function saveCustomer() {
		$this->load->language('checkout/checkout');

		$json = array();

		// if ($this->customer->isLogged()) {
		// 	$json['redirect'] = $this->url->link('checkout/checkout', '', true);
		// }

		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart');
		}

		if (!$this->config->get('config_checkout_guest') || $this->config->get('config_customer_price') || $this->cart->hasDownload()) {
			$json['redirect'] = $this->url->link('checkout/checkout', '', true);
		}

		if (!$json) {
			// if ((utf8_strlen(trim($this->request->post['firstname'])) < 1) || (utf8_strlen(trim($this->request->post['firstname'])) > 32)) {
			// 	$json['error']['firstname'] = $this->language->get('error_firstname');
			// }

			// if ((utf8_strlen(trim($this->request->post['lastname'])) < 1) || (utf8_strlen(trim($this->request->post['lastname'])) > 32)) {
			// 	$json['error']['lastname'] = $this->language->get('error_lastname');
			// }

			// if ((utf8_strlen($this->request->post['email']) > 96) || !filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
			// 	$json['error']['email'] = $this->language->get('error_email');
			// }

			// if ((utf8_strlen($this->request->post['telephone']) < 3) || (utf8_strlen($this->request->post['telephone']) > 32)) {
			// 	$json['error']['telephone'] = $this->language->get('error_telephone');
			// }

			if (isset($this->request->post['customer_group_id']) && is_array($this->config->get('config_customer_group_display')) && in_array($this->request->post['customer_group_id'], $this->config->get('config_customer_group_display'))) {
				$customer_group_id = $this->request->post['customer_group_id'];
			} else {
				$customer_group_id = $this->config->get('config_customer_group_id');
			}
		}

		if (!$json) {
			$this->session->data['account'] = 'guest';

			$this->session->data['guest']['customer_group_id'] = $customer_group_id;
			$this->session->data['guest']['firstname'] = $this->request->post['firstname'];
			$this->session->data['guest']['lastname'] = $this->request->post['lastname'];
			$this->session->data['guest']['email'] = $this->request->post['email'];
			$this->session->data['guest']['telephone'] = $this->request->post['telephone'];

			if (isset($this->request->post['custom_field']['account'])) {
				$this->session->data['guest']['custom_field'] = $this->request->post['custom_field']['account'];
			} else {
				$this->session->data['guest']['custom_field'] = array();
			}

			$this->session->data['guest']['shipping_address'] = !empty($this->request->post['shipping_address']) || $this->cart->hasShipping();

			$country_id = $this->config->get('config_country_id');
			$this->load->model('localisation/zone');
			$config_zone = $this->model_localisation_zone->getZone($this->config->get('config_zone_id'));
			$zone_id = ($config_zone && isset($config_zone['country_id']) && $config_zone['country_id'] == $country_id)
				? $this->config->get('config_zone_id')
				: 0;
			$zones = $this->model_localisation_zone->getZonesByCountryId($country_id);
			if (!empty($zones)) {
				$zone_id = $zones[0]['zone_id'];
			}

			$payment_address = array(
				'firstname' => $this->request->post['firstname'],
				'lastname' => $this->request->post['lastname'],
				'company' => '',
				'address_1' => isset($this->session->data['shipping_address']['address_1']) ? $this->session->data['shipping_address']['address_1'] : '',
				'address_2' => '',
				'postcode' => isset($this->session->data['shipping_address']['postcode']) ? $this->session->data['shipping_address']['postcode'] : '',
				'city' => isset($this->session->data['shipping_address']['city']) ? $this->session->data['shipping_address']['city'] : '—',
				'country_id' => $country_id,
				'zone_id' => $zone_id,
				'custom_field' => array()
			);

			$this->load->model('localisation/country');
			$country_info = $this->model_localisation_country->getCountry($country_id);
			if ($country_info) {
				$payment_address['country'] = $country_info['name'];
				$payment_address['iso_code_2'] = $country_info['iso_code_2'];
				$payment_address['iso_code_3'] = $country_info['iso_code_3'];
				$payment_address['address_format'] = $country_info['address_format'];
			} else {
				$payment_address['country'] = '';
				$payment_address['iso_code_2'] = '';
				$payment_address['iso_code_3'] = '';
				$payment_address['address_format'] = '';
			}

			$zone_info = $this->model_localisation_zone->getZone($zone_id);
			if ($zone_info) {
				$payment_address['zone'] = $zone_info['name'];
				$payment_address['zone_code'] = $zone_info['code'];
			} else {
				$payment_address['zone'] = '';
				$payment_address['zone_code'] = '';
			}

			$this->session->data['payment_address'] = $payment_address;

			if ($this->session->data['guest']['shipping_address']) {
				$this->session->data['shipping_address'] = $payment_address;
			}

			// unset($this->session->data['shipping_method']);
			// unset($this->session->data['shipping_methods']);
			// unset($this->session->data['payment_method']);
			// unset($this->session->data['payment_methods']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Сохранение данных получателя и адреса (форма как у Guest).
	 * Для гостей — через guest/save; для залогиненных — обновляет сессию.
	 */
	public function saveContact() {
		if ($this->customer->isLogged()) {
			$this->saveContactLogged();
		} else {
			$this->load->controller('checkout/guest/save');
		}
	}

	/**
	 * Сохранение контактных данных для залогиненного пользователя (только сессия).
	 */
	private function saveContactLogged() {
		$this->load->language('checkout/checkout');

		$json = array();

		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart');
		}

		if (!$json) {
			$firstname = isset($this->request->post['firstname']) ? trim($this->request->post['firstname']) : '';
			$lastname = isset($this->request->post['lastname']) ? trim($this->request->post['lastname']) : '';
			if (empty($lastname)) {
				$lastname = $firstname;
			}
			$address_1 = isset($this->request->post['address_1']) ? trim($this->request->post['address_1']) : '';
			$city = isset($this->request->post['city']) && trim($this->request->post['city']) !== '' ? trim($this->request->post['city']) : '—';

			$country_id = $this->config->get('config_country_id');
			$this->load->model('localisation/zone');
			$config_zone = $this->model_localisation_zone->getZone($this->config->get('config_zone_id'));
			$zone_id = ($config_zone && isset($config_zone['country_id']) && $config_zone['country_id'] == $country_id)
				? $this->config->get('config_zone_id')
				: 0;
			$zones = $this->model_localisation_zone->getZonesByCountryId($country_id);
			if (!empty($zones)) {
				$zone_id = $zones[0]['zone_id'];
			}

			$payment_address = array(
				'firstname' => $firstname,
				'lastname' => $lastname,
				'company' => '',
				'address_1' => $address_1,
				'address_2' => '',
				'postcode' => isset($this->request->post['postcode']) ? $this->request->post['postcode'] : '',
				'city' => $city,
				'country_id' => $country_id,
				'zone_id' => $zone_id,
				'custom_field' => array()
			);

			$this->load->model('localisation/country');
			$country_info = $this->model_localisation_country->getCountry($country_id);
			if ($country_info) {
				$payment_address['country'] = $country_info['name'];
				$payment_address['iso_code_2'] = $country_info['iso_code_2'];
				$payment_address['iso_code_3'] = $country_info['iso_code_3'];
				$payment_address['address_format'] = $country_info['address_format'];
			} else {
				$payment_address['country'] = $payment_address['iso_code_2'] = $payment_address['iso_code_3'] = $payment_address['address_format'] = '';
			}

			$zone_info = $this->model_localisation_zone->getZone($zone_id);
			if ($zone_info) {
				$payment_address['zone'] = $zone_info['name'];
				$payment_address['zone_code'] = $zone_info['code'];
			} else {
				$payment_address['zone'] = $payment_address['zone_code'] = '';
			}

			$this->session->data['payment_address'] = $payment_address;

			if ($this->cart->hasShipping()) {
				$this->session->data['shipping_address'] = $payment_address;
			}

			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Сохранение адреса доставки.
	 * Работает и для гостей, и для залогиненных (обновляет сессию).
	 */
	public function saveShippingAddress() {
		$this->load->language('checkout/checkout');

		$json = array();

		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart');
		}

		if (!$this->customer->isLogged() && (!$this->config->get('config_checkout_guest') || $this->config->get('config_customer_price') || $this->cart->hasDownload())) {
			$json['redirect'] = $this->url->link('checkout/checkout', '', true);
		}

		if (!$json) {
			if ((utf8_strlen(trim($this->request->post['address_1'])) < 3) || (utf8_strlen(trim($this->request->post['address_1'])) > 128)) {
				$json['error']['address_1'] = $this->language->get('error_address_1');
			}

			$country_id = $this->config->get('config_country_id');
			$this->load->model('localisation/zone');
			$config_zone = $this->model_localisation_zone->getZone($this->config->get('config_zone_id'));
			$zone_id = ($config_zone && isset($config_zone['country_id']) && $config_zone['country_id'] == $country_id)
				? $this->config->get('config_zone_id')
				: 0;
			$zones = $this->model_localisation_zone->getZonesByCountryId($country_id);
			if (!empty($zones)) {
				$zone_id = $zones[0]['zone_id'];
			}

			$address_1 = trim($this->request->post['address_1']);
			$city = isset($this->request->post['city']) && trim($this->request->post['city']) !== '' ? trim($this->request->post['city']) : '—';

			$firstname = isset($this->session->data['guest']['firstname']) ? $this->session->data['guest']['firstname'] : '';
			$lastname = isset($this->session->data['guest']['lastname']) ? $this->session->data['guest']['lastname'] : '';
			if (empty($firstname) && isset($this->session->data['payment_address']['firstname'])) {
				$firstname = $this->session->data['payment_address']['firstname'];
			}
			if (empty($lastname) && isset($this->session->data['payment_address']['lastname'])) {
				$lastname = $this->session->data['payment_address']['lastname'];
			}

			$shipping_address = array(
				'firstname' => $firstname,
				'lastname' => $lastname,
				'company' => '',
				'address_1' => $address_1,
				'address_2' => '',
				'postcode' => isset($this->request->post['postcode']) ? $this->request->post['postcode'] : '',
				'city' => $city,
				'country_id' => $country_id,
				'zone_id' => $zone_id,
				'custom_field' => array()
			);

			$this->load->model('localisation/country');
			$country_info = $this->model_localisation_country->getCountry($country_id);
			if ($country_info) {
				$shipping_address['country'] = $country_info['name'];
				$shipping_address['iso_code_2'] = $country_info['iso_code_2'];
				$shipping_address['iso_code_3'] = $country_info['iso_code_3'];
				$shipping_address['address_format'] = $country_info['address_format'];
			} else {
				$shipping_address['country'] = '';
				$shipping_address['iso_code_2'] = '';
				$shipping_address['iso_code_3'] = '';
				$shipping_address['address_format'] = '';
			}

			$zone_info = $this->model_localisation_zone->getZone($zone_id);
			if ($zone_info) {
				$shipping_address['zone'] = $zone_info['name'];
				$shipping_address['zone_code'] = $zone_info['code'];
			} else {
				$shipping_address['zone'] = '';
				$shipping_address['zone_code'] = '';
			}

			$this->session->data['shipping_address'] = $shipping_address;

			$this->session->data['payment_address']['address_1'] = $address_1;
			$this->session->data['payment_address']['city'] = $city;

			// unset($this->session->data['shipping_method']);
			// unset($this->session->data['shipping_methods']);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Подтверждение заказа. Создаёт и записывает заказ, перенаправляет на checkout/success.
	 */
	public function confirmOrder() {
		$this->load->language('checkout/checkout');

		$json = array();

		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart');
		}

		if (!isset($this->session->data['payment_address'])) {
			$json['error']['warning'] = $this->language->get('error_address');
		}

		if ($this->cart->hasShipping()) {
			if (!isset($this->session->data['shipping_address'])) {
				$json['error']['warning'] = $this->language->get('error_address');
			}
			if (!isset($this->session->data['shipping_method'])) {
				$json['error']['warning'] = $this->language->get('error_shipping');
			}
		}

		if (!isset($this->session->data['payment_method'])) {
			$json['error']['warning'] = $this->language->get('error_payment');
		}

		if (!$json) {
			if (!$this->customer->isLogged() && isset($this->session->data['guest'])) {
				$error = $this->createAccountAndLogin();
				if ($error) {
					$json['error']['warning'] = $error;
				}
			}

			if (!$json) {
				$order_id = $this->buildOrderDataAndAddOrder();
				if ($order_id) {
					if (isset($this->session->data['payment_method']['code']) && $this->session->data['payment_method']['code'] === 'bank_transfer') {
						$this->load->language('extension/payment/bank_transfer');
						$this->load->model('checkout/order');
						$comment = $this->language->get('text_instruction') . "\n\n";
						$comment .= $this->config->get('payment_bank_transfer_bank' . $this->config->get('config_language_id')) . "\n\n";
						$comment .= $this->language->get('text_payment');
						$this->model_checkout_order->addOrderHistory($order_id, 1, $comment, true);
					}
					$json['redirect'] = $this->url->link('checkout/success', '', true);
				} else {
					$json['error']['warning'] = $this->language->get('error_warning');
				}
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Сохранение реквизитов юрлица при оплате банковским переводом.
	 */
	public function saveUrLic() {
		$this->load->language('checkout/checkout');

		$json = array();

		if ((!$this->cart->hasProducts() && empty($this->session->data['vouchers'])) || (!$this->cart->hasStock() && !$this->config->get('config_stock_checkout'))) {
			$json['redirect'] = $this->url->link('checkout/cart');
		}

		if (!isset($this->session->data['payment_method']['code']) || $this->session->data['payment_method']['code'] !== 'bank_transfer') {
			$json['error']['warning'] = $this->language->get('error_payment');
		}

		if (!$json) {
			if (!$this->customer->isLogged() && isset($this->session->data['guest'])) {
				$error = $this->createAccountAndLogin();
				if ($error) {
					$json['error']['warning'] = $error;
				}
			}

			if (!$json) {
				$company = isset($this->request->post['ur_company']) ? trim($this->request->post['ur_company']) : '';
				$ur_lic_data = array(
					'customer_id' => (int)$this->customer->getId(),
					'company' => $company,
					'inn'     => isset($this->request->post['ur_inn']) ? trim($this->request->post['ur_inn']) : '',
					'kpp'     => isset($this->request->post['ur_kpp']) ? trim($this->request->post['ur_kpp']) : '',
					'address' => isset($this->request->post['ur_address']) ? trim($this->request->post['ur_address']) : '',
					'bik'     => isset($this->request->post['ur_bik']) ? trim($this->request->post['ur_bik']) : '',
					'bank'    => isset($this->request->post['ur_bank']) ? trim($this->request->post['ur_bank']) : '',
					'rs'      => isset($this->request->post['ur_rs']) ? trim($this->request->post['ur_rs']) : '',
					'ks'      => isset($this->request->post['ur_ks']) ? trim($this->request->post['ur_ks']) : ''
				);
				$this->session->data['ur_lic'] = $ur_lic_data;
				if (isset($this->session->data['payment_address'])) {
					$this->session->data['payment_address']['company'] = $company;
				}
				$this->load->model('checkout/ur_lic');
				$ur_lic_id = $this->model_checkout_ur_lic->addUrLic($ur_lic_data);
				$this->session->data['ur_lic_id'] = $ur_lic_id;
				$json['redirect'] = $this->url->link('checkout/confirm', '', true);
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	/**
	 * Собирает данные заказа из сессии и создаёт заказ.
	 * @return int|false order_id или false при ошибке
	 */
	private function buildOrderDataAndAddOrder() {
		if (!$this->cart->hasShipping()) {
			unset($this->session->data['shipping_address']);
			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
		}

		$totals = array();
		$taxes = $this->cart->getTaxes();
		$total = 0;
		$total_data = array(
			'totals' => &$totals,
			'taxes'  => &$taxes,
			'total'  => &$total
		);

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

		$order_data = array();
		$order_data['totals'] = $totals;
		$order_data['invoice_prefix'] = $this->config->get('config_invoice_prefix');
		$order_data['store_id'] = $this->config->get('config_store_id');
		$order_data['store_name'] = $this->config->get('config_name');
		$order_data['store_url'] = $order_data['store_id'] ? $this->config->get('config_url') : ($this->request->server['HTTPS'] ? HTTPS_SERVER : HTTP_SERVER);

		$this->load->model('account/customer');
		if ($this->customer->isLogged()) {
			$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
			$order_data['customer_id'] = $this->customer->getId();
			$order_data['customer_group_id'] = $customer_info['customer_group_id'];
			$order_data['firstname'] = $customer_info['firstname'];
			$order_data['lastname'] = $customer_info['lastname'];
			$order_data['email'] = $customer_info['email'];
			$order_data['telephone'] = $customer_info['telephone'];
			$order_data['custom_field'] = json_decode($customer_info['custom_field'], true);
		} elseif (isset($this->session->data['guest'])) {
			$order_data['customer_id'] = 0;
			$order_data['customer_group_id'] = $this->session->data['guest']['customer_group_id'];
			$order_data['firstname'] = $this->session->data['guest']['firstname'];
			$order_data['lastname'] = $this->session->data['guest']['lastname'];
			$order_data['email'] = $this->session->data['guest']['email'];
			$order_data['telephone'] = $this->session->data['guest']['telephone'];
			$order_data['custom_field'] = isset($this->session->data['guest']['custom_field']) ? $this->session->data['guest']['custom_field'] : array();
		} else {
			return false;
		}

		$order_data['payment_firstname'] = $this->session->data['payment_address']['firstname'];
		$order_data['payment_lastname'] = $this->session->data['payment_address']['lastname'];
		$order_data['payment_company'] = isset($this->session->data['payment_address']['company']) ? $this->session->data['payment_address']['company'] : '';
		$order_data['payment_address_1'] = $this->session->data['payment_address']['address_1'];
		$order_data['payment_address_2'] = isset($this->session->data['payment_address']['address_2']) ? $this->session->data['payment_address']['address_2'] : '';
		$order_data['payment_city'] = $this->session->data['payment_address']['city'];
		$order_data['payment_postcode'] = isset($this->session->data['payment_address']['postcode']) ? $this->session->data['payment_address']['postcode'] : '';
		$order_data['payment_zone'] = $this->session->data['payment_address']['zone'];
		$order_data['payment_zone_id'] = $this->session->data['payment_address']['zone_id'];
		$order_data['payment_country'] = $this->session->data['payment_address']['country'];
		$order_data['payment_country_id'] = $this->session->data['payment_address']['country_id'];
		$order_data['payment_address_format'] = isset($this->session->data['payment_address']['address_format']) ? $this->session->data['payment_address']['address_format'] : '';
		$order_data['payment_custom_field'] = isset($this->session->data['payment_address']['custom_field']) ? $this->session->data['payment_address']['custom_field'] : array();
		$order_data['payment_method'] = isset($this->session->data['payment_method']['title']) ? $this->session->data['payment_method']['title'] : '';
		$order_data['payment_code'] = isset($this->session->data['payment_method']['code']) ? $this->session->data['payment_method']['code'] : '';

		if ($this->cart->hasShipping()) {
			$order_data['shipping_firstname'] = $this->session->data['shipping_address']['firstname'];
			$order_data['shipping_lastname'] = $this->session->data['shipping_address']['lastname'];
			$order_data['shipping_company'] = isset($this->session->data['shipping_address']['company']) ? $this->session->data['shipping_address']['company'] : '';
			$order_data['shipping_address_1'] = $this->session->data['shipping_address']['address_1'];
			$order_data['shipping_address_2'] = isset($this->session->data['shipping_address']['address_2']) ? $this->session->data['shipping_address']['address_2'] : '';
			$order_data['shipping_city'] = $this->session->data['shipping_address']['city'];
			$order_data['shipping_postcode'] = isset($this->session->data['shipping_address']['postcode']) ? $this->session->data['shipping_address']['postcode'] : '';
			$order_data['shipping_zone'] = $this->session->data['shipping_address']['zone'];
			$order_data['shipping_zone_id'] = $this->session->data['shipping_address']['zone_id'];
			$order_data['shipping_country'] = $this->session->data['shipping_address']['country'];
			$order_data['shipping_country_id'] = $this->session->data['shipping_address']['country_id'];
			$order_data['shipping_address_format'] = isset($this->session->data['shipping_address']['address_format']) ? $this->session->data['shipping_address']['address_format'] : '';
			$order_data['shipping_custom_field'] = isset($this->session->data['shipping_address']['custom_field']) ? $this->session->data['shipping_address']['custom_field'] : array();
			$order_data['shipping_method'] = isset($this->session->data['shipping_method']['title']) ? $this->session->data['shipping_method']['title'] : '';
			$order_data['shipping_code'] = isset($this->session->data['shipping_method']['code']) ? $this->session->data['shipping_method']['code'] : '';
		} else {
			$order_data['shipping_firstname'] = $order_data['shipping_lastname'] = $order_data['shipping_company'] = '';
			$order_data['shipping_address_1'] = $order_data['shipping_address_2'] = $order_data['shipping_city'] = $order_data['shipping_postcode'] = '';
			$order_data['shipping_zone'] = $order_data['shipping_zone_id'] = $order_data['shipping_country'] = $order_data['shipping_country_id'] = '';
			$order_data['shipping_address_format'] = $order_data['shipping_custom_field'] = array();
			$order_data['shipping_method'] = $order_data['shipping_code'] = '';
		}

		$order_data['products'] = array();
		foreach ($this->cart->getProducts() as $product) {
			$option_data = array();
			foreach ($product['option'] as $option) {
				$option_data[] = array(
					'product_option_id'       => $option['product_option_id'],
					'product_option_value_id' => $option['product_option_value_id'],
					'option_id'               => $option['option_id'],
					'option_value_id'         => $option['option_value_id'],
					'name'                    => $option['name'],
					'value'                   => $option['value'],
					'type'                    => $option['type']
				);
			}
			$order_data['products'][] = array(
				'product_id' => $product['product_id'],
				'name'       => $product['name'],
				'model'      => $product['model'],
				'option'     => $option_data,
				'download'   => $product['download'],
				'quantity'   => $product['quantity'],
				'subtract'   => $product['subtract'],
				'price'      => $product['price'],
				'total'      => $product['total'],
				'tax'        => $this->tax->getTax($product['price'], $product['tax_class_id']),
				'reward'     => $product['reward']
			);
		}

		$order_data['vouchers'] = array();
		if (!empty($this->session->data['vouchers'])) {
			foreach ($this->session->data['vouchers'] as $voucher) {
				$order_data['vouchers'][] = array(
					'description'      => $voucher['description'],
					'code'             => token(10),
					'to_name'          => $voucher['to_name'],
					'to_email'         => $voucher['to_email'],
					'from_name'        => $voucher['from_name'],
					'from_email'       => $voucher['from_email'],
					'voucher_theme_id' => $voucher['voucher_theme_id'],
					'message'          => $voucher['message'],
					'amount'           => $voucher['amount']
				);
			}
		}

		$order_data['comment'] = isset($this->session->data['comment']) ? $this->session->data['comment'] : '';
		if (isset($this->session->data['payment_method']['code']) && $this->session->data['payment_method']['code'] === 'bank_transfer' && !empty($this->session->data['ur_lic'])) {
			$ur = $this->session->data['ur_lic'];
			$order_data['comment'] .= "\n\n--- Реквизиты для счёта ---\n";
			if (!empty($ur['company'])) $order_data['comment'] .= "Организация: " . $ur['company'] . "\n";
			if (!empty($ur['inn'])) $order_data['comment'] .= "ИНН: " . $ur['inn'] . "\n";
			if (!empty($ur['kpp'])) $order_data['comment'] .= "КПП: " . $ur['kpp'] . "\n";
			if (!empty($ur['address'])) $order_data['comment'] .= "Юр. адрес: " . $ur['address'] . "\n";
			if (!empty($ur['bank'])) $order_data['comment'] .= "Банк: " . $ur['bank'] . "\n";
			if (!empty($ur['bik'])) $order_data['comment'] .= "БИК: " . $ur['bik'] . "\n";
			if (!empty($ur['rs'])) $order_data['comment'] .= "Р/с: " . $ur['rs'] . "\n";
			if (!empty($ur['ks'])) $order_data['comment'] .= "К/с: " . $ur['ks'] . "\n";
		}
		$order_data['total'] = $total;

		if (isset($this->request->cookie['tracking'])) {
			$order_data['tracking'] = $this->request->cookie['tracking'];
			$subtotal = $this->cart->getSubTotal();
			$affiliate_info = $this->model_account_customer->getAffiliateByTracking($this->request->cookie['tracking']);
			$order_data['affiliate_id'] = $affiliate_info ? $affiliate_info['customer_id'] : 0;
			$order_data['commission'] = $affiliate_info ? ($subtotal / 100) * $affiliate_info['commission'] : 0;
			$this->load->model('checkout/marketing');
			$marketing_info = $this->model_checkout_marketing->getMarketingByCode($this->request->cookie['tracking']);
			$order_data['marketing_id'] = $marketing_info ? $marketing_info['marketing_id'] : 0;
		} else {
			$order_data['affiliate_id'] = 0;
			$order_data['commission'] = 0;
			$order_data['marketing_id'] = 0;
			$order_data['tracking'] = '';
		}

		$order_data['language_id'] = $this->config->get('config_language_id');
		$order_data['currency_id'] = $this->currency->getId($this->session->data['currency']);
		$order_data['currency_code'] = $this->session->data['currency'];
		$order_data['currency_value'] = $this->currency->getValue($this->session->data['currency']);
		$order_data['ip'] = $this->request->server['REMOTE_ADDR'];
		$order_data['forwarded_ip'] = !empty($this->request->server['HTTP_X_FORWARDED_FOR']) ? $this->request->server['HTTP_X_FORWARDED_FOR'] : (!empty($this->request->server['HTTP_CLIENT_IP']) ? $this->request->server['HTTP_CLIENT_IP'] : '');
		$order_data['user_agent'] = isset($this->request->server['HTTP_USER_AGENT']) ? $this->request->server['HTTP_USER_AGENT'] : '';
		$order_data['accept_language'] = isset($this->request->server['HTTP_ACCEPT_LANGUAGE']) ? $this->request->server['HTTP_ACCEPT_LANGUAGE'] : '';


	
		$this->load->model('checkout/order');
		$this->session->data['order_id'] = $this->model_checkout_order->addOrder($order_data);

		return $this->session->data['order_id'];
	}

	/**
	 * Создаёт аккаунт и логинит гостя из сессии.
	 * @return string|null Текст ошибки или null при успехе
	 */
	private function createAccountAndLogin() {
		$this->load->language('checkout/checkout');
		$this->load->model('account/customer');

		$email = isset($this->session->data['guest']['email']) ? trim($this->session->data['guest']['email']) : '';
		if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
			return $this->language->get('error_email');
		}
		if ($this->model_account_customer->getTotalCustomersByEmail($email)) {
			return $this->language->get('error_exists');
		}

		$password = token(12);
		$reg_data = array(
			'firstname' => isset($this->session->data['guest']['firstname']) ? $this->session->data['guest']['firstname'] : '',
			'lastname'  => isset($this->session->data['guest']['lastname']) ? $this->session->data['guest']['lastname'] : '',
			'email'     => $email,
			'telephone' => isset($this->session->data['guest']['telephone']) ? $this->session->data['guest']['telephone'] : '',
			'password'  => $password,
			'confirm'   => $password,
			'customer_group_id' => isset($this->session->data['guest']['customer_group_id']) ? $this->session->data['guest']['customer_group_id'] : $this->config->get('config_customer_group_id'),
			'custom_field' => isset($this->session->data['guest']['custom_field']) ? array('account' => $this->session->data['guest']['custom_field']) : array()
		);
		$addr = isset($this->session->data['payment_address']) ? $this->session->data['payment_address'] : array();
		$reg_data['address_1'] = isset($addr['address_1']) ? $addr['address_1'] : '';
		$reg_data['address_2'] = isset($addr['address_2']) ? $addr['address_2'] : '';
		$reg_data['postcode'] = isset($addr['postcode']) ? $addr['postcode'] : '';
		$reg_data['city'] = isset($addr['city']) ? $addr['city'] : '';
		$reg_data['zone_id'] = isset($addr['zone_id']) ? $addr['zone_id'] : 0;
		$reg_data['country_id'] = isset($addr['country_id']) ? $addr['country_id'] : $this->config->get('config_country_id');
		$reg_data['company'] = isset($addr['company']) ? $addr['company'] : '';

		$customer_id = $this->model_account_customer->addCustomer($reg_data);

		$this->load->model('account/address');
		$address_id = $this->model_account_address->addAddress($customer_id, $reg_data);
		$this->model_account_customer->editAddressId($customer_id, $address_id);
		$this->model_account_customer->deleteLoginAttempts($email);

		$this->load->model('account/customer_group');
		$customer_group_info = $this->model_account_customer_group->getCustomerGroup($reg_data['customer_group_id']);
		if (!$customer_group_info || $customer_group_info['approval']) {
			return $this->language->get('error_approved');
		}

		$this->customer->login($email, $password);
		$this->session->data['account'] = 'register';
		$this->session->data['payment_address'] = $this->model_account_address->getAddress($address_id);
		if (!empty($this->session->data['guest']['shipping_address'])) {
			$this->session->data['shipping_address'] = $this->session->data['payment_address'];
		}
		unset($this->session->data['guest']);

		return null;
	}
}
