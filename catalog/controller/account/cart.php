<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerAccountCart extends Controller {
	public function index() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/cart', '', true);
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$enhanced_account = $this->customer->getGroupId() > 1;
		if (!$enhanced_account) {
			$this->response->redirect($this->url->link('account/account', '', true));
		}

		$this->load->language('account/cart');
		$this->load->language('checkout/cart');

		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->setRobots('noindex,follow');

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('account/cart', '', true)
		);

		$data = array_merge($data, $this->getCartData());

		$data['menu'] = $this->load->controller('account/menu');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/cart', $data));
	}

	public function exportExcel() {
		if (!$this->customer->isLogged()) {
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$enhanced_account = $this->customer->getGroupId() > 1;
		if (!$enhanced_account) {
			$this->response->redirect($this->url->link('account/account', '', true));
		}

		$this->load->language('account/cart');

		$products = $this->cart->getProducts();
		$rows = array();

		foreach ($products as $product) {
			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$unit_price = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));
				$price = $this->currency->format($unit_price, $this->session->data['currency']);
				$total = $this->currency->format($unit_price * $product['quantity'], $this->session->data['currency']);
			} else {
				$price = $total = '';
			}

			$option_text = array();
			if (!empty($product['option'])) {
				foreach ($product['option'] as $opt) {
					$option_text[] = html_entity_decode($opt['name'], ENT_QUOTES, 'UTF-8') . ': ' . html_entity_decode($opt['value'], ENT_QUOTES, 'UTF-8');
				}
			}
			$name = html_entity_decode($product['name'], ENT_QUOTES, 'UTF-8');
			if ($option_text) {
				$name .= ' (' . implode(', ', $option_text) . ')';
			}

			$rows[] = array(
				'name'     => $name,
				'model'    => $product['model'],
				'price'    => $price,
				'quantity' => $product['quantity'],
				'total'    => $total
			);
		}

		$filename = 'cart_' . date('Y-m-d_H-i') . '.xls';

		header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');

		if (ob_get_level()) {
			ob_end_clean();
		}

		$output = fopen('php://output', 'w');
		if ($output === false) {
			$this->response->redirect($this->url->link('account/cart', '', true));
			return;
		}

		fwrite($output, "\xEF\xBB\xBF");

		$header = array(
			$this->language->get('column_excel_name'),
			$this->language->get('column_excel_model'),
			$this->language->get('column_excel_price'),
			$this->language->get('column_excel_quantity'),
			$this->language->get('column_excel_total')
		);
		fputcsv($output, $header, "\t", '"');

		foreach ($rows as $row) {
			$line = array(
				$row['name'],
				$row['model'],
				$row['price'],
				$row['quantity'],
				$row['total']
			);
			fputcsv($output, $line, "\t", '"');
		}
		fclose($output);
		exit;
	}

	public function content() {
		if (!$this->customer->isLogged()) {
			$this->response->redirect($this->url->link('account/login', '', true));
		}
		$data = $this->getCartData();
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$this->response->setOutput($this->load->view('account/cart_content', $data));
	}

	public function edit() {
		$this->load->language('checkout/cart');

		if (!$this->customer->isLogged()) {
			if (!empty($this->request->server['HTTP_X_REQUESTED_WITH']) && strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
				$this->response->addHeader('Content-Type: application/json; charset=utf-8');
				$this->response->setOutput(json_encode(array('error' => 'login')));
			} else {
				$this->response->redirect($this->url->link('account/login', '', true));
			}
			return;
		}

		if (!empty($this->request->post['quantity'])) {
			foreach ($this->request->post['quantity'] as $key => $value) {
				$this->cart->update($key, (int)$value);
			}
			$this->session->data['success'] = $this->language->get('text_remove');
			unset($this->session->data['shipping_method'], $this->session->data['shipping_methods'], $this->session->data['payment_method'], $this->session->data['payment_methods'], $this->session->data['reward']);
		}

		if (!empty($this->request->server['HTTP_X_REQUESTED_WITH']) && strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			$data = $this->getCartData();
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$this->response->addHeader('Content-Type: application/json; charset=utf-8');
			$this->response->setOutput(json_encode(array('success' => true, 'html' => $this->load->view('account/cart_content', $data))));
		} else {
			$this->response->redirect($this->url->link('account/cart', '', true));
		}
	}

	public function remove() {
		$this->load->language('checkout/cart');

		if (!$this->customer->isLogged()) {
			if (!empty($this->request->server['HTTP_X_REQUESTED_WITH']) && strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
				$this->response->addHeader('Content-Type: application/json; charset=utf-8');
				$this->response->setOutput(json_encode(array('error' => 'login')));
			} else {
				$this->response->redirect($this->url->link('account/login', '', true));
			}
			return;
		}

		if (isset($this->request->get['key'])) {
			$this->cart->remove($this->request->get['key']);
			$this->session->data['success'] = $this->language->get('text_remove');
			unset($this->session->data['shipping_method'], $this->session->data['shipping_methods'], $this->session->data['payment_method'], $this->session->data['payment_methods'], $this->session->data['reward']);
		}

		if (!empty($this->request->server['HTTP_X_REQUESTED_WITH']) && strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			$data = $this->getCartData();
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$this->response->addHeader('Content-Type: application/json; charset=utf-8');
			$this->response->setOutput(json_encode(array('success' => true, 'html' => $this->load->view('account/cart_content', $data))));
		} else {
			$this->response->redirect($this->url->link('account/cart', '', true));
		}
	}

	public function clear() {
		$this->load->language('account/cart');

		if (!$this->customer->isLogged()) {
			if (!empty($this->request->server['HTTP_X_REQUESTED_WITH']) && strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
				$this->response->addHeader('Content-Type: application/json; charset=utf-8');
				$this->response->setOutput(json_encode(array('error' => 'login')));
			} else {
				$this->response->redirect($this->url->link('account/login', '', true));
			}
			return;
		}

		$products = $this->cart->getProducts();
		foreach ($products as $product) {
			$this->cart->remove($product['cart_id']);
		}
		if (!empty($this->session->data['vouchers'])) {
			$this->session->data['vouchers'] = array();
		}

		$this->session->data['success'] = $this->language->get('text_cart_cleared');
		unset($this->session->data['shipping_method'], $this->session->data['shipping_methods'], $this->session->data['payment_method'], $this->session->data['payment_methods'], $this->session->data['reward']);

		if (!empty($this->request->server['HTTP_X_REQUESTED_WITH']) && strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			$data = $this->getCartData();
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$this->response->addHeader('Content-Type: application/json; charset=utf-8');
			$this->response->setOutput(json_encode(array('success' => true, 'html' => $this->load->view('account/cart_content', $data))));
		} else {
			$this->response->redirect($this->url->link('account/cart', '', true));
		}
	}

	public function suggestions() {
		$json = array();

		if (!$this->customer->isLogged()) {
			$this->response->addHeader('Content-Type: application/json; charset=utf-8');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$search = isset($this->request->get['search']) ? trim($this->request->get['search']) : '';
		$search = isset($this->request->get['q']) ? trim($this->request->get['q']) : $search;

		if (utf8_strlen($search) < 2) {
			$this->response->addHeader('Content-Type: application/json; charset=utf-8');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$this->load->model('tool/image');
		$store_id = (int)$this->config->get('config_store_id');
		$lang_id = (int)$this->config->get('config_language_id');
		$term = $this->db->escape(utf8_strtolower($search));
		$term_like = '%' . $term . '%';
		$limit = 15;

		$seen = array();

		// 1. По product.model и product.sku
		$sql = "SELECT p.product_id, p.model, p.sku, pd.name
			FROM " . DB_PREFIX . "product p
			LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = '" . $lang_id . "')
			LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
			WHERE p.status = '1' AND p2s.store_id = '" . $store_id . "'
			AND (LCASE(p.model) LIKE '" . $this->db->escape($term_like) . "'
				OR LCASE(p.sku) LIKE '" . $this->db->escape($term_like) . "')
			ORDER BY (LCASE(p.model) = '" . $term . "' OR LCASE(p.sku) = '" . $term . "') DESC, p.model ASC
			LIMIT " . (int)$limit;
		$q = $this->db->query($sql);
		foreach ($q->rows as $row) {
			$key = 'p_' . (int)$row['product_id'];
			if (isset($seen[$key])) continue;
			$seen[$key] = true;
			$label = $row['model'] ?: $row['sku'];
			if ($row['name']) $label .= ' — ' . $row['name'];
			$img = $this->db->query("SELECT image FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$row['product_id'] . "'");
			$img_path = $img->num_rows && $img->row['image'] ? $img->row['image'] : 'placeholder.png';
			$json[] = array(
				'product_id' => (int)$row['product_id'],
				'product_option_id' => null,
				'product_option_value_id' => null,
				'label' => $label,
				'name' => $row['name'],
				'code' => $row['model'] ?: $row['sku'],
				'image' => $this->model_tool_image->resize($img_path, 40, 40),
				'href' => $this->url->link('product/product', 'product_id=' . (int)$row['product_id'])
			);
		}

		// 2. По product_option_value_field code
		$sql2 = "SELECT pov.product_id, po.product_option_id, pov.product_option_value_id,
				p.model, p.sku, pd.name AS product_name, ovd.name AS option_name,
				povf.field_value AS option_code
			FROM " . DB_PREFIX . "product_option_value pov
			INNER JOIN " . DB_PREFIX . "product_option po ON (pov.product_option_id = po.product_option_id)
			INNER JOIN " . DB_PREFIX . "product_option_value_field povf ON (pov.product_option_value_id = povf.product_option_value_id AND povf.field_key = 'code')
			INNER JOIN " . DB_PREFIX . "product p ON (pov.product_id = p.product_id)
			LEFT JOIN " . DB_PREFIX . "product_description pd ON (p.product_id = pd.product_id AND pd.language_id = '" . $lang_id . "')
			LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pov.option_value_id = ovd.option_value_id AND ovd.language_id = '" . $lang_id . "')
			INNER JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
			WHERE p.status = '1' AND p2s.store_id = '" . $store_id . "'
			AND LCASE(povf.field_value) LIKE '" . $this->db->escape($term_like) . "'
			ORDER BY (LCASE(povf.field_value) = '" . $term . "') DESC, povf.field_value ASC
			LIMIT " . (int)$limit;
		$q2 = $this->db->query($sql2);
		foreach ($q2->rows as $row) {
			$key = 'ov_' . (int)$row['product_option_value_id'];
			if (isset($seen[$key])) continue;
			$seen[$key] = true;
			$code = $row['option_code'];
			$label = $code;
			if ($row['option_name']) $label .= ' (' . $row['option_name'] . ')';
			if ($row['product_name']) $label .= ' — ' . $row['product_name'];
			$img = $this->db->query("SELECT image FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$row['product_id'] . "'");
			$img_path = $img->num_rows && $img->row['image'] ? $img->row['image'] : 'placeholder.png';
			$json[] = array(
				'product_id' => (int)$row['product_id'],
				'product_option_id' => (int)$row['product_option_id'],
				'product_option_value_id' => (int)$row['product_option_value_id'],
				'label' => $label,
				'name' => $row['product_name'],
				'code' => $code,
				'image' => $this->model_tool_image->resize($img_path, 40, 40),
				'href' => $this->url->link('product/product', 'product_id=' . (int)$row['product_id'])
			);
		}

		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->setOutput(json_encode($json));
	}

	public function addBySku() {
		$this->load->language('account/cart');

		if (!$this->customer->isLogged()) {
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$product_id = isset($this->request->post['product_id']) ? (int)$this->request->post['product_id'] : 0;
		$product_option_id = isset($this->request->post['product_option_id']) ? (int)$this->request->post['product_option_id'] : 0;
		$product_option_value_id = isset($this->request->post['product_option_value_id']) ? (int)$this->request->post['product_option_value_id'] : 0;
		$sku = isset($this->request->post['sku']) ? trim($this->request->post['sku']) : '';

		$quantity = isset($this->request->post['quantity']) ? (int)$this->request->post['quantity'] : 1;
		if ($quantity < 1) $quantity = 1;

		$option = array();
		if ($product_option_id && $product_option_value_id) {
			$option[$product_option_id] = $product_option_value_id;
		}

		if ($product_id) {
			$this->load->model('catalog/product');
			$product_info = $this->model_catalog_product->getProduct($product_id);
			if ($product_info) {
				$product_options = $this->model_catalog_product->getProductOptions($product_id);
				$has_required = false;
				foreach ($product_options as $po) {
					if ($po['required']) $has_required = true;
				}
				if ($has_required && empty($option)) {
					$this->session->data['error'] = $this->language->get('error_sku_not_found');
				} else {
					$this->cart->add($product_id, $quantity, $option);
					$this->session->data['success'] = $this->language->get('text_added_by_sku');
				}
			} else {
				$this->session->data['error'] = $this->language->get('error_sku_not_found');
			}
		} elseif ($sku) {
			$query = $this->db->query("SELECT p.product_id FROM " . DB_PREFIX . "product p
				LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
				WHERE (LCASE(p.model) = '" . $this->db->escape(utf8_strtolower($sku)) . "'
					OR LCASE(p.sku) = '" . $this->db->escape(utf8_strtolower($sku)) . "')
				AND p.status = '1' AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
				LIMIT 1");
			$product_id = $query->num_rows ? (int)$query->row['product_id'] : 0;
			if ($product_id) {
				$this->cart->add($product_id, $quantity);
				$this->session->data['success'] = $this->language->get('text_added_by_sku');
			} else {
				$pov_query = $this->db->query("SELECT pov.product_id, po.product_option_id, pov.product_option_value_id
					FROM " . DB_PREFIX . "product_option_value pov
					INNER JOIN " . DB_PREFIX . "product_option po ON (pov.product_option_id = po.product_option_id)
					INNER JOIN " . DB_PREFIX . "product_option_value_field povf ON (pov.product_option_value_id = povf.product_option_value_id AND povf.field_key = 'code')
					INNER JOIN " . DB_PREFIX . "product_to_store p2s ON (pov.product_id = p2s.product_id)
					WHERE LCASE(povf.field_value) = '" . $this->db->escape(utf8_strtolower($sku)) . "'
					AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'
					LIMIT 1");
				if ($pov_query->num_rows) {
					$r = $pov_query->row;
					$opt = array((int)$r['product_option_id'] => (int)$r['product_option_value_id']);
					$this->cart->add((int)$r['product_id'], $quantity, $opt);
					$this->session->data['success'] = $this->language->get('text_added_by_sku');
				} else {
					$this->session->data['error'] = $this->language->get('error_sku_not_found');
				}
			}
		}

		if (!empty($this->request->server['HTTP_X_REQUESTED_WITH']) && strtolower($this->request->server['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
			$data = $this->getCartData();
			$data['content_top'] = $this->load->controller('common/content_top');
			$data['content_bottom'] = $this->load->controller('common/content_bottom');
			$this->response->addHeader('Content-Type: application/json; charset=utf-8');
			$this->response->setOutput(json_encode(array('success' => true, 'html' => $this->load->view('account/cart_content', $data))));
		} else {
			$this->response->redirect($this->url->link('account/cart', '', true));
		}
	}

	private function getCartData() {
		$this->load->language('account/cart');
		$this->load->language('checkout/cart');
		$this->load->model('tool/image');
		$this->load->model('tool/upload');
		$this->load->model('catalog/product');

		$data = array();
		$data['heading_title'] = $this->language->get('heading_title');
		$data['action'] = $this->url->link('account/cart/edit', '', true);
		$data['action_clear'] = $this->url->link('account/cart/clear', '', true);
		$data['action_add_sku'] = $this->url->link('account/cart/addBySku', '', true);
		$data['action_suggestions'] = $this->url->link('account/cart/suggestions', '', true);
		$data['action_content'] = $this->url->link('account/cart/content', '', true);
		$data['action_export_excel'] = $this->url->link('account/cart/exportExcel', '', true);
		$data['continue'] = $this->url->link('account/account', '', true);
		$data['checkout'] = ($this->config->get('config_theme') == 'maison') ? $this->url->link('checkout/maison_checkout', '', true) : $this->url->link('checkout/checkout', '', true);

		if ($this->cart->hasProducts() || !empty($this->session->data['vouchers'])) {
			$data['error_warning'] = '';
			if (!$this->cart->hasStock() && (!$this->config->get('config_stock_checkout') || $this->config->get('config_stock_warning'))) {
				$data['error_warning'] = $this->language->get('error_stock');
			} elseif (isset($this->session->data['error'])) {
				$data['error_warning'] = $this->session->data['error'];
				unset($this->session->data['error']);
			}
			if (isset($this->session->data['success'])) {
				$data['success'] = $this->session->data['success'];
				unset($this->session->data['success']);
			} else {
				$data['success'] = '';
			}
			$data['weight'] = $this->config->get('config_cart_weight') ? $this->weight->format($this->cart->getWeight(), $this->config->get('config_weight_class_id'), $this->language->get('decimal_point'), $this->language->get('thousand_point')) : '';
			$data['volume'] = $this->getCartVolume();

			$this->load->model('tool/image');
			$this->load->model('tool/upload');
			$this->load->model('catalog/product');

			$data['products'] = array();
			$products = $this->cart->getProducts();
			$items_count = 0;

			foreach ($products as $product) {
				$product_total = 0;
				foreach ($products as $product_2) {
					if ($product_2['product_id'] == $product['product_id']) $product_total += $product_2['quantity'];
				}
				if ($product['minimum'] > $product_total) {
					$data['error_warning'] = sprintf($this->language->get('error_minimum'), $product['name'], $product['minimum']);
				}

				$image_path = $product['image'];
				if (!empty($product['option'])) {
					foreach ($product['option'] as $option) {
						if (!empty($option['custom_fields']['images'])) { $image_path = $option['custom_fields']['images']; break; }
					}
				}
				$image = $image_path ? $this->model_tool_image->resize($image_path, 64, 64) : $this->model_tool_image->resize('placeholder.png', 64, 64);

				$option_data = array();
				$old_price = 0;
				$discount_percentage = 0;
				$option_text = array();
				foreach ($product['option'] as $option) {
					$value = ($option['type'] != 'file') ? $option['value'] : ($this->model_tool_upload->getUploadByCode($option['value']) ? $this->model_tool_upload->getUploadByCode($option['value'])['name'] : '');
					$option_data[] = array('name' => $option['name'], 'value' => (utf8_strlen($value) > 20 ? utf8_substr($value, 0, 20) . '..' : $value));
					$option_text[] = $option['name'] . ': ' . $value;
					if (!empty($option['special_price']) && (float)$option['special_price'] > 0) {
						$discount_percentage = number_format(round(($option['price'] - $option['special_price']) / $option['price'] * 100, 0), 0);
						$old_price = $this->currency->format($option['price'], $this->session->data['currency']);
					}
				}

				if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
					$unit_price = $this->tax->calculate($product['price'], $product['tax_class_id'], $this->config->get('config_tax'));
					$price = $this->currency->format($unit_price, $this->session->data['currency']);
					$total = $this->currency->format($unit_price * $product['quantity'], $this->session->data['currency']);
				} else {
					$price = $total = false;
				}
				$items_count += $product['quantity'];

				$data['products'][] = array(
					'cart_id' => $product['cart_id'],
					'thumb' => $image,
					'name' => $product['name'],
					'model' => $product['model'],
					'option' => $option_data,
					'option_text' => implode(', ', $option_text),
					'quantity' => $product['quantity'],
					'stock' => $product['stock'],
					'price' => $price,
					'old_price' => $old_price,
					'discount_percentage' => $discount_percentage,
					'total' => $total,
					'href' => $this->url->link('product/product', 'product_id=' . $product['product_id']),
					'remove' => $this->url->link('account/cart/remove', 'key=' . $product['cart_id'], true)
				);
			}

			$data['items_count'] = $items_count;
			$data['products_count'] = count($products);

			$this->load->model('setting/extension');
			$totals = array();
			$taxes = $this->cart->getTaxes();
			$total = 0;
			$total_data = array('totals' => &$totals, 'taxes' => &$taxes, 'total' => &$total);

			if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
				$results = $this->model_setting_extension->getExtensions('total');
				$sort_order = array();
				foreach ($results as $key => $value) $sort_order[$key] = $this->config->get('total_' . $value['code'] . '_sort_order');
				array_multisort($sort_order, SORT_ASC, $results);
				foreach ($results as $result) {
					if ($this->config->get('total_' . $result['code'] . '_status')) {
						$this->load->model('extension/total/' . $result['code']);
						$this->{'model_extension_total_' . $result['code']}->getTotal($total_data);
					}
				}
				$sort_order = array();
				foreach ($totals as $key => $value) $sort_order[$key] = $value['sort_order'];
				array_multisort($sort_order, SORT_ASC, $totals);
			}

			$data['sub_total_value'] = 0;
			$data['order_total_text'] = '';
			foreach ($totals as $row) {
				if (!empty($row['code']) && $row['code'] == 'sub_total') $data['sub_total_value'] = $row['value'];
				if (!empty($row['code']) && $row['code'] == 'total') $data['order_total_text'] = $this->currency->format($row['value'], $this->session->data['currency']);
			}

			$data['discount_tiers'] = $this->getDiscountTiers();
			$data['current_discount'] = $this->getCurrentDiscount($data['sub_total_value']);
			$data['next_tier'] = $this->getNextTier($data['sub_total_value']);
			$max_threshold = 200000;
			$data['discount_progress_percent'] = min(100, round(($data['sub_total_value'] / $max_threshold) * 100));
		} else {
			$data['products'] = array();
			$data['items_count'] = 0;
			$data['products_count'] = 0;
			$data['weight'] = '';
			$data['volume'] = '';
			$data['order_total_text'] = $this->currency->format(0, $this->session->data['currency']);
			$data['sub_total_value'] = 0;
			$data['discount_tiers'] = $this->getDiscountTiers();
			$data['current_discount'] = 0;
			$data['next_tier'] = $this->getNextTier(0);
			$data['discount_progress_percent'] = 0;
			$data['error_warning'] = '';
			$data['success'] = '';
		}

		$data['text_items_count'] = $this->language->get('text_items_count');
		$data['button_clear'] = $this->language->get('button_clear');
		$data['button_add'] = $this->language->get('button_add');
		$data['button_export_excel'] = $this->language->get('button_export_excel');
		$data['button_checkout'] = $this->language->get('button_checkout');
		$data['placeholder_sku'] = $this->language->get('placeholder_sku');
		$data['column_image'] = $this->language->get('column_image');
		$data['column_product'] = $this->language->get('column_product');
		$data['column_price'] = $this->language->get('column_price');
		$data['column_your_price'] = $this->language->get('column_your_price');
		$data['column_quantity'] = $this->language->get('column_quantity');
		$data['column_total'] = $this->language->get('column_total');
		$data['text_weight'] = $this->language->get('text_weight');
		$data['text_volume'] = $this->language->get('text_volume');
		$data['text_total'] = $this->language->get('text_total');
		$data['text_discount'] = $this->language->get('text_discount');
		$data['text_discount_progress'] = !empty($data['next_tier']) ? sprintf($this->language->get('text_discount_progress'), $data['next_tier']['left_text'], $data['next_tier']['percent']) : '';
		$data['text_empty'] = $this->language->get('text_empty');
		$data['button_continue'] = $this->language->get('button_continue');

		return $data;
	}

	private function getCartVolume() {
		$volume = 0;
		$products = $this->cart->getProducts();

		foreach ($products as $product) {
			$length = (float)$product['length'];
			$width = (float)$product['width'];
			$height = (float)$product['height'];
			if ($length > 0 && $width > 0 && $height > 0) {
				$volume += ($length * $width * $height * $product['quantity']) / 1000000;
			}
		}

		return number_format($volume, 1, $this->language->get('decimal_point'), $this->language->get('thousand_point')) . ' ' . $this->language->get('text_m3');
	}

	private function getDiscountTiers() {
		$tiers = array(
			array('percent' => 5, 'threshold' => 50000),
			array('percent' => 10, 'threshold' => 100000),
			array('percent' => 15, 'threshold' => 200000)
		);
		foreach ($tiers as &$t) {
			$t['threshold_text'] = $this->currency->format($t['threshold'], $this->session->data['currency']);
		}
		return $tiers;
	}

	private function getCurrentDiscount($sub_total) {
		$tiers = $this->getDiscountTiers();
		$current = 0;
		foreach ($tiers as $tier) {
			if ($sub_total >= $tier['threshold']) {
				$current = $tier['percent'];
			}
		}
		return $current;
	}

	private function getNextTier($sub_total) {
		$tiers = $this->getDiscountTiers();
		foreach ($tiers as $tier) {
			if ($sub_total < $tier['threshold']) {
				return array(
					'percent'   => $tier['percent'],
					'threshold' => $tier['threshold'],
					'left'      => $tier['threshold'] - $sub_total,
					'left_text' => $this->currency->format($tier['threshold'] - $sub_total, $this->session->data['currency'])
				);
			}
		}
		return null;
	}
}
