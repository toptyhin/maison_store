<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerCheckoutCart extends Controller {
	public function index() {
		$this->load->language('checkout/cart');

		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->setRobots('noindex,follow');

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'href' => $this->url->link('common/home'),
			'text' => $this->language->get('text_home')
		);

		$data['breadcrumbs'][] = array(
			'href' => $this->url->link('checkout/cart'),
			'text' => $this->language->get('heading_title')
		);

		if ($this->cart->hasProducts() || !empty($this->session->data['vouchers'])) {
			if (!$this->cart->hasStock() && (!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning'))) {
				$data['error_warning'] = $this->language->get('error_stock');
			} elseif (isset($this->session->data['error'])) {
				$data['error_warning'] = $this->session->data['error'];

				unset($this->session->data['error']);
			} else {
				$data['error_warning'] = '';
			}

			if ($this->config->get('config_customer_price') && !$this->customer->isLogged()) {
				$data['attention'] = sprintf($this->language->get('text_login'), $this->url->link('account/login'), $this->url->link('account/register'));
			} else {
				$data['attention'] = '';
			}

			if (isset($this->session->data['success'])) {
				$data['success'] = $this->session->data['success'];

				unset($this->session->data['success']);
			} else {
				$data['success'] = '';
			}

			$data['action'] = $this->url->link('checkout/cart/edit', '', true);

			if ($this->config->get('config_cart_weight')) {
				$data['weight'] = $this->weight->format($this->cart->getWeight(), $this->config->get('config_weight_class_id'), $this->language->get('decimal_point'), $this->language->get('thousand_point'));
			} else {
				$data['weight'] = '';
			}

			$this->load->model('tool/image');
			$this->load->model('tool/upload');
			$this->load->model('catalog/product');

			$data['products'] = array();

			$products = $this->cart->getProducts();

			$items_count = 0;

			$total_discount = 0;
			$total_original_value = 0;

			foreach ($products as $product) {
				$product_total = 0;

				foreach ($products as $product_2) {
					if ($product_2['product_id'] == $product['product_id']) {
						$product_total += $product_2['quantity'];
					}
				}

				if ($product['minimum'] > $product_total) {
					$data['error_warning'] = sprintf($this->language->get('error_minimum'), $product['name'], $product['minimum']);
				}

				$image_path = $product['image'];
				if (!empty($product['option'])) {
					foreach ($product['option'] as $option) {
						if (!empty($option['custom_fields']['images'])) {
							$image_path = $option['custom_fields']['images'];
							break;
						}
					}
				}
				if ($image_path) {
					$image = $this->model_tool_image->resize($image_path, $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_cart_height'));
				} else {
					$image = '';
				}

				$option_data = array();
				$old_price = 0;
				$discount = 0;
				$discount_percentage = 0;
				foreach ($product['option'] as $option) {
					if ($option['type'] != 'file') {
						$value = $option['value'];
					} else {
						$upload_info = $this->model_tool_upload->getUploadByCode($option['value']);

						if ($upload_info) {
							$value = $upload_info['name'];
						} else {
							$value = '';
						}
					}

					$option_data[] = array(
						'name'  => $option['name'],
						'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value)
					);
				if (!empty($option['special_price']) && (float)$option['special_price'] > 0) {
					$discount = $option['price'] - $option['special_price'];
					$total_discount += $discount * $product['quantity'];
					$total_original_value += $option['price'] * $product['quantity'];
					$discount_percentage = $discount / $option['price'] * 100;
					$discount_percentage = number_format(round($discount_percentage, 0), 0);
					$old_price = $this->currency->format($option['price'], $this->session->data['currency']);
				}
				}

				// Display prices
				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$unit_price = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));
					
					$price = $this->currency->format($unit_price, $this->session->data['currency']);
					$total = $this->currency->format($unit_price * $product['quantity'], $this->session->data['currency']);
				} else {
					$price = false;
					$total = false;
				}

				$recurring = '';

				if ($product['recurring']) {
					$frequencies = array(
						'day'        => $this->language->get('text_day'),
						'week'       => $this->language->get('text_week'),
						'semi_month' => $this->language->get('text_semi_month'),
						'month'      => $this->language->get('text_month'),
						'year'       => $this->language->get('text_year')
					);

					if ($product['recurring']['trial']) {
						$recurring = sprintf($this->language->get('text_trial_description'), $this->currency->format($this->tax->calculate($product['recurring']['trial_price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']), $product['recurring']['trial_cycle'], $frequencies[$product['recurring']['trial_frequency']], $product['recurring']['trial_duration']) . ' ';
					}

					if ($product['recurring']['duration']) {
						$recurring .= sprintf($this->language->get('text_payment_description'), $this->currency->format($this->tax->calculate($product['recurring']['price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']), $product['recurring']['cycle'], $frequencies[$product['recurring']['frequency']], $product['recurring']['duration']);
					} else {
						$recurring .= sprintf($this->language->get('text_payment_cancel'), $this->currency->format($this->tax->calculate($product['recurring']['price'] * $product['quantity'], $product['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']), $product['recurring']['cycle'], $frequencies[$product['recurring']['frequency']], $product['recurring']['duration']);
					}
				}

				$items_count += $product['quantity'];

				$data['products'][] = array(
					'cart_id'   => $product['cart_id'],
					'thumb'     => $image,
					'name'      => $product['name'],
					'model'     => $product['model'],
					'option'    => $option_data,
					'recurring' => $recurring,
					'quantity'  => $product['quantity'],
					'stock'     => $product['stock'] ? true : !(!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning')),
					'reward'    => ($product['reward'] ? sprintf($this->language->get('text_points'), $product['reward']) : ''),
					'price'     => $price,
					'old_price' => $old_price,
					'discount'  => $discount,
					'discount_percentage' => $discount_percentage,
					'total'     => $total,
					'href'      => $this->url->link('product/product', 'product_id=' . $product['product_id'])
				);
			}

			$data['items_count'] = $items_count;

			$data['total_discount'] = $total_discount;
			$data['total_original_value'] = $total_original_value;
			$data['total_discount_text'] = $this->currency->format($total_discount, $this->session->data['currency']);

			// Gift Voucher
			$data['vouchers'] = array();

			if (!empty($this->session->data['vouchers'])) {
				foreach ($this->session->data['vouchers'] as $key => $voucher) {
					$data['vouchers'][] = array(
						'key'         => $key,
						'description' => $voucher['description'],
						'amount'      => $this->currency->format($voucher['amount'], $this->session->data['currency']),
						'remove'      => $this->url->link('checkout/cart', 'remove=' . $key)
					);
				}
			}

			// Totals
			$this->load->model('setting/extension');

			$totals = array();
			$taxes = $this->cart->getTaxes();
			$total = 0;
			
			// Because __call can not keep var references so we put them into an array. 			
			$total_data = array(
				'totals' => &$totals,
				'taxes'  => &$taxes,
				'total'  => &$total
			);
			
			// Display prices
			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$sort_order = array();

				$results = $this->model_setting_extension->getExtensions('total');

				foreach ($results as $key => $value) {
					$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
				}

				array_multisort($sort_order, SORT_ASC, $results);

				foreach ($results as $result) {
					if ($this->config->get('total_' . $result['code'] . '_status')) {
						$this->load->model('extension/total/' . $result['code']);
						
						// We have to put the totals in an array so that they pass by reference.
						$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
					}
				}

				$sort_order = array();

				foreach ($totals as $key => $value) {
					$sort_order[$key] = $value['sort_order'];
				}

				array_multisort($sort_order, SORT_ASC, $totals);
			}

			$data['totals'] = array();

			$data['sub_total_text'] = '';
			$data['sub_total_value'] = 0;
			$data['order_total_text'] = '';
			$data['order_total_value'] = 0;
			$data['discount_value'] = 0;
			$data['discount_text'] = '';
			$data['shipping_text'] = '';
			$data['shipping_pending'] = false;

			foreach ($totals as $row) {
				$data['totals'][] = array(
					'title' => $row['title'],
					'text'  => $this->currency->format($row['value'], $this->session->data['currency'])
				);

				if (!empty($row['code']) && $row['code'] == 'sub_total') {
					$data['sub_total_text'] = $this->currency->format($row['value'], $this->session->data['currency']);
					$data['sub_total_value'] = $row['value'];
				}

				if (!empty($row['code']) && $row['code'] == 'total') {
					$data['order_total_text'] = $this->currency->format($row['value'], $this->session->data['currency']);
					$data['order_total_value'] = $row['value'];
				}

				if (!empty($row['code']) && $row['code'] == 'shipping') {
					$data['shipping_text'] = $this->currency->format($row['value'], $this->session->data['currency']);
				}

				if ($row['value'] < 0) {
					$data['discount_value'] += $row['value'];
				}
			}

			if ($data['discount_value']) {
				$data['discount_text'] = $this->currency->format($data['discount_value'], $this->session->data['currency']);
			}

			$denominator = $data['total_original_value'] > 0 ? $data['total_original_value'] : $data['sub_total_value'];
			$data['total_discount_percentage'] = $denominator > 0
				? $data['total_discount'] / $denominator * 100
				: 0;
			$data['total_discount_percentage'] = number_format(round($data['total_discount_percentage'], 0), 0);

			if ($data['shipping_text'] === '') {
				$data['shipping_pending'] = true;
			}

			// Free shipping progress (based on free shipping total threshold if configured)
			$data['free_shipping_left'] = 0;
			$data['free_shipping_progress'] = 0;
			$data['free_shipping_left_text'] = '';

			$free_shipping_threshold = (float)$this->config->get('shipping_free_total');

			if ($free_shipping_threshold > 0 && $data['sub_total_value'] > 0 && $data['sub_total_value'] < $free_shipping_threshold) {
				$data['free_shipping_left'] = $free_shipping_threshold - $data['sub_total_value'];

				$progress = ($data['sub_total_value'] / $free_shipping_threshold) * 100;
				$data['free_shipping_progress'] = max(0, min(100, round($progress)));

				$data['free_shipping_left_text'] = sprintf(
					$this->language->get('text_free_shipping_left'),
					$this->currency->format($data['free_shipping_left'], $this->session->data['currency'])
				);
			}

			$data['free_shipping_active'] = $free_shipping_threshold > 0
				&& $data['sub_total_value'] > 0
				&& $data['free_shipping_left'] === 0;

			$data['continue'] = $this->url->link('common/home');

			if ($this->config->get('config_theme') == 'maison') {
				$data['checkout'] = $this->url->link('checkout/maison_checkout', '', true);
			} else {
				$data['checkout'] = $this->url->link('checkout/checkout', '', true);
			}

			// Coupon helpers
			if (isset($this->session->data['coupon'])) {
				$data['coupon'] = $this->session->data['coupon'];
			} else {
				$data['coupon'] = '';
			}

			$data['coupon_action'] = $this->url->link('extension/total/coupon/coupon', '', true);

			// Frequently bought (upsell) from upsell module
			$data['frequently_bought'] = array();
			$data['text_frequently_bought'] = $this->language->get('text_frequently_bought');
			$this->load->model('setting/module');
			$upsell_modules = $this->model_setting_module->getModulesByCode('upsell');
			foreach ($upsell_modules as $mod) {
				$setting = json_decode($mod['setting'], true);
				if (empty($setting['status']) || empty($setting['product'])) {
					continue;
				}
				$limit = !empty($setting['limit']) ? (int)$setting['limit'] : 5;
				$products = array_slice($setting['product'], 0, $limit);
				foreach ($products as $item) {
					$product_id = is_array($item) ? (int)($item['product_id'] ?? 0) : (int)$item;
					if (!$product_id) {
						continue;
					}
					$option = is_array($item) && !empty($item['option']) ? $item['option'] : array();
					$product_info = $this->model_catalog_product->getProduct($product_id);
					if (!$product_info) {
						continue;
					}
					$price = (float)$product_info['price'];
					if (!empty($option)) {
						$product_options = $this->model_catalog_product->getProductOptions($product_id);
						foreach ($product_options as $po) {
							foreach ($po['product_option_value'] as $pov) {
								if (isset($option[$po['product_option_id']]) && $option[$po['product_option_id']] == $pov['product_option_value_id']) {
									if ($pov['price_prefix'] == '+') {
										$price += (float)$pov['price'];
									} elseif ($pov['price_prefix'] == '-') {
										$price -= (float)$pov['price'];
									} elseif ($pov['price_prefix'] == '=') {
										$price = (float)$pov['price'];
									}
									break;
								}
							}
						}
					}
					$image_path = $product_info['image'];
					if (!empty($option)) {
						$product_options = $this->model_catalog_product->getProductOptions($product_id);
						foreach ($product_options as $po) {
							foreach ($po['product_option_value'] as $pov) {
								if (isset($option[$po['product_option_id']]) && $option[$po['product_option_id']] == $pov['product_option_value_id']) {
									if (!empty($pov['custom_fields']['images'])) {
										$image_path = $pov['custom_fields']['images'];
									}
									break;
								}
							}
						}
					}
					if ($image_path) {
						$thumb = $this->model_tool_image->resize($image_path, $this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_height'));
					} else {
						$thumb = $this->model_tool_image->resize('placeholder.png', $this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_width'), $this->config->get('theme_' . $this->config->get('config_theme') . '_image_category_height'));
					}
					if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
						$price_formatted = $this->currency->format($this->tax->calculate($price, $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
					} else {
						$price_formatted = false;
					}
					$add_option = '';
					if (!empty($option)) {
						$parts = array();
						foreach ($option as $po_id => $pov_id) {
							$parts[] = 'option[' . (int)$po_id . ']=' . (int)$pov_id;
						}
						$add_option = implode('&', $parts);
					}
					$data['frequently_bought'][] = array(
						'product_id'  => $product_id,
						'thumb'       => $thumb,
						'href'        => $this->url->link('product/product', 'product_id=' . $product_id),
						'name'        => $product_info['name'],
						'price'       => $price_formatted,
						'add_option'  => $add_option
					);
				}
				break;
			}

			$this->load->model('setting/extension');

			$data['modules'] = array();
			
			$files = glob(DIR_APPLICATION . '/controller/extension/total/*.php');

			if ($files) {
				foreach ($files as $file) {
					$result = $this->load->controller('extension/total/' . basename($file, '.php'));
					
					if ($result) {
						$data['modules'][] = $result;
					}
				}
			}

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('checkout/cart', $data));
		} else {
			$data['text_error'] = $this->language->get('text_empty');
			
			$data['continue'] = $this->url->link('common/home');

			unset($this->session->data['success']);

			$data['column_left'] = $this->load->controller('common/column_left');
			$data['column_right'] = $this->load->controller('common/column_right');
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$data['footer'] = $this->load->controller('common/footer');
			$data['header'] = $this->load->controller('common/header');

			$this->response->setOutput($this->load->view('error/not_found', $data));
		}
	}

	public function add() {
		$this->load->language('checkout/cart');

		$json = array();

		if (isset($this->request->post['product_id'])) {
			$product_id = (int)$this->request->post['product_id'];
		} else {
			$product_id = 0;
		}

		$this->load->model('catalog/product');

		$product_info = $this->model_catalog_product->getProduct($product_id);

		if ($product_info) {
			if (isset($this->request->post['quantity'])) {
				$quantity = (int)$this->request->post['quantity'];
			} else {
				$quantity = 1;
			}

			if (isset($this->request->post['option'])) {
				$option = array_filter($this->request->post['option']);
			} else {
				$option = array();
			}

			$product_options = $this->model_catalog_product->getProductOptions($this->request->post['product_id']);

			foreach ($product_options as $product_option) {
				if ($product_option['required'] && empty($option[$product_option['product_option_id']])) {
					$json['error']['option'][$product_option['product_option_id']] = sprintf($this->language->get('error_required'), $product_option['name']);
				}
			}

			if (isset($this->request->post['recurring_id'])) {
				$recurring_id = $this->request->post['recurring_id'];
			} else {
				$recurring_id = 0;
			}

			$recurrings = $this->model_catalog_product->getProfiles($product_info['product_id']);

			if ($recurrings) {
				$recurring_ids = array();

				foreach ($recurrings as $recurring) {
					$recurring_ids[] = $recurring['recurring_id'];
				}

				if (!in_array($recurring_id, $recurring_ids)) {
					$json['error']['recurring'] = $this->language->get('error_recurring_required');
				}
			}

			if (!$json) {
				$this->cart->add($this->request->post['product_id'], $quantity, $option, $recurring_id);

				$json['success'] = sprintf($this->language->get('text_success'), $this->url->link('product/product', 'product_id=' . $this->request->post['product_id']), $product_info['name'], $this->url->link('checkout/cart'));

				// Unset all shipping and payment methods
				unset($this->session->data['shipping_method']);
				unset($this->session->data['shipping_methods']);
				unset($this->session->data['payment_method']);
				unset($this->session->data['payment_methods']);

				// Totals
				$this->load->model('setting/extension');

				$totals = array();
				$taxes = $this->cart->getTaxes();
				$total = 0;
		
				// Because __call can not keep var references so we put them into an array. 			
				$total_data = array(
					'totals' => &$totals,
					'taxes'  => &$taxes,
					'total'  => &$total
				);

				// Display prices
				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$sort_order = array();

					$results = $this->model_setting_extension->getExtensions('total');

					foreach ($results as $key => $value) {
						$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
					}

					array_multisort($sort_order, SORT_ASC, $results);

					foreach ($results as $result) {
						if ($this->config->get('total_' . $result['code'] . '_status')) {
							$this->load->model('extension/total/' . $result['code']);

							// We have to put the totals in an array so that they pass by reference.
							$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
						}
					}

					$sort_order = array();

					foreach ($totals as $key => $value) {
						$sort_order[$key] = $value['sort_order'];
					}

					array_multisort($sort_order, SORT_ASC, $totals);
				}

				$json['total'] = sprintf($this->language->get('text_items'), $this->cart->countProducts() + (isset($this->session->data['vouchers']) ? count($this->session->data['vouchers']) : 0), $this->currency->format($total, $this->session->data['currency']));
			} else {
				$json['redirect'] = str_replace('&amp;', '&', $this->url->link('product/product', 'product_id=' . $this->request->post['product_id']));
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function edit() {
		$this->load->language('checkout/cart');

		$json = array();

		// Update
		if (!empty($this->request->post['quantity'])) {
			foreach ($this->request->post['quantity'] as $key => $value) {
				$this->cart->update($key, $value);
			}

			$this->session->data['success'] = $this->language->get('text_remove');

			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['reward']);

			$this->response->redirect($this->url->link('checkout/cart'));
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function remove() {
		$this->load->language('checkout/cart');

		$json = array();

		// Remove
		if (isset($this->request->post['key'])) {
			$this->cart->remove($this->request->post['key']);

			unset($this->session->data['vouchers'][$this->request->post['key']]);

			$json['success'] = $this->language->get('text_remove');

			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['reward']);

			// Totals
			$this->load->model('setting/extension');

			$totals = array();
			$taxes = $this->cart->getTaxes();
			$total = 0;

			// Because __call can not keep var references so we put them into an array. 			
			$total_data = array(
				'totals' => &$totals,
				'taxes'  => &$taxes,
				'total'  => &$total
			);

			// Display prices
			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$sort_order = array();

				$results = $this->model_setting_extension->getExtensions('total');

				foreach ($results as $key => $value) {
					$sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
				}

				array_multisort($sort_order, SORT_ASC, $results);

				foreach ($results as $result) {
					if ($this->config->get('total_' . $result['code'] . '_status')) {
						$this->load->model('extension/total/' . $result['code']);

						// We have to put the totals in an array so that they pass by reference.
						$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
					}
				}

				$sort_order = array();

				foreach ($totals as $key => $value) {
					$sort_order[$key] = $value['sort_order'];
				}

				array_multisort($sort_order, SORT_ASC, $totals);
			}

			$json['total'] = sprintf($this->language->get('text_items'), $this->cart->countProducts() + (isset($this->session->data['vouchers']) ? count($this->session->data['vouchers']) : 0), $this->currency->format($total, $this->session->data['currency']));
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
