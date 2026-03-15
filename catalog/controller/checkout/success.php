<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerCheckoutSuccess extends Controller {
	public function index() {
		$this->load->language('checkout/success');

		if (isset($this->session->data['order_id'])) {
			$this->session->data['last_order_id'] = $this->session->data['order_id'];
			$this->cart->clear();

			unset($this->session->data['shipping_method']);
			unset($this->session->data['shipping_methods']);
			unset($this->session->data['payment_method']);
			unset($this->session->data['payment_methods']);
			unset($this->session->data['guest']);
			unset($this->session->data['comment']);
			unset($this->session->data['order_id']);
			unset($this->session->data['coupon']);
			unset($this->session->data['reward']);
			unset($this->session->data['voucher']);
			unset($this->session->data['vouchers']);
			unset($this->session->data['totals']);
		}

		if (!empty($this->session->data['last_order_id']) ) {
			$this->document->setTitle(sprintf($this->language->get('heading_title_customer'), $this->session->data['last_order_id']));
			$this->document->setRobots('noindex,follow');
		} else {
			$this->document->setTitle($this->language->get('heading_title'));
			$this->document->setRobots('noindex,follow');
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_basket'),
			'href' => $this->url->link('checkout/cart')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_checkout'),
			'href' => $this->url->link('checkout/checkout', '', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_success'),
			'href' => $this->url->link('checkout/success')
		);

		if (!empty($this->session->data['last_order_id'])) {
			$data['heading_title'] = sprintf($this->language->get('heading_title_customer'), $this->session->data['last_order_id']);
		} else {
			$data['heading_title'] = $this->language->get('heading_title');
		}

		if ($this->customer->isLogged() && !empty($this->session->data['last_order_id'])) {
			$data['text_message'] = sprintf($this->language->get('text_customer'), $this->url->link('account/order/info&order_id=' . $this->session->data['last_order_id'], '', true), $this->url->link('account/account', '', true), $this->url->link('account/order', '', true), $this->url->link('information/contact'), $this->url->link('product/special'), $this->session->data['last_order_id'], $this->url->link('account/download', '', true));
		} else {
			$data['text_message'] = sprintf($this->language->get('text_guest'), $this->url->link('information/contact'));
		}

		$data['continue'] = $this->url->link('common/home');
		$data['button_continue'] = $this->language->get('button_continue');
		$data['button_print'] = $this->language->get('button_print');

		$data['order_id'] = null;
		$data['order_status'] = null;
		$data['order_email'] = null;
		$data['shipping_address'] = null;
		$data['delivery_time'] = null;
		$data['success_related_products'] = array();

		if (!empty($this->session->data['last_order_id'])) {
			$this->load->model('checkout/order');
			$order_info = $this->model_checkout_order->getOrder($this->session->data['last_order_id']);
			if ($order_info) {
				$data['order_id'] = $order_info['order_id'];
				$data['order_status'] = $order_info['order_status'];
				$data['order_email'] = $order_info['email'];
				$data['delivery_time'] = '2-4 рабочих дня';
				$addr_parts = array_filter([
					$order_info['shipping_city'] ? 'г. ' . $order_info['shipping_city'] : '',
					$order_info['shipping_address_1'],
					$order_info['shipping_address_2']
				]);
				$data['shipping_address'] = implode(', ', $addr_parts) ?: null;

				// Товары заказа → related_products для блока «Вам также может понравиться»
				$data['success_related_products'] = array();
				$order_products = $this->model_checkout_order->getOrderProducts($order_info['order_id']);
				$order_product_ids = array();
				foreach ($order_products as $op) {
					$order_product_ids[(int)$op['product_id']] = true;
				}
				$related_ids = array();
				$this->load->model('catalog/product');
				foreach ($order_products as $op) {
					$related = $this->model_catalog_product->getProductRelated($op['product_id']);
					foreach ($related as $related_product) {
						$rid = (int)$related_product['product_id'];
						if (!isset($order_product_ids[$rid]) && !isset($related_ids[$rid])) {
							$related_ids[$rid] = true;
						}
					}
				}
				$related_ids = array_keys($related_ids);
				$related_ids = array_slice($related_ids, 0, 8);

				$wishlist_ids = $this->registry->get('wishlist_product_ids');
				if (!is_array($wishlist_ids)) {
					$wishlist_ids = array();
				}
				$this->load->model('tool/image');
				$image_w = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_width') ?: 300;
				$image_h = (int)$this->config->get('theme_' . $this->config->get('config_theme') . '_image_product_height') ?: 400;
				foreach ($related_ids as $product_id) {
					$result = $this->model_catalog_product->getProduct($product_id);
					if (!$result) {
						continue;
					}
					if ($result['image']) {
						$image = $this->model_tool_image->resize($result['image'], $image_w, $image_h);
					} else {
						$image = $this->model_tool_image->resize('placeholder.png', $image_w, $image_h);
					}
					if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
						$price = $this->currency->format($this->tax->calculate($result['price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
					} else {
						$price = false;
					}
					if (!is_null($result['special']) && (float)$result['special'] >= 0) {
						$special = $this->currency->format($this->tax->calculate($result['special'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
						$tax_price = (float)$result['special'];
					} else {
						$special = false;
						$tax_price = (float)$result['price'];
					}
					if (!is_null($result['min_option_price']) && (float)$result['min_option_price'] >= 0) {
						$min_option_price = $this->currency->format($this->tax->calculate($result['min_option_price'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
						$tax_price = (float)$result['min_option_price'];
					} else {
						$min_option_price = false;
						$tax_price = (float)$result['price'];
					}
					if (!is_null($result['min_option_price_before_discount']) && (float)$result['min_option_price_before_discount'] >= 0) {
						$min_option_price_before_discount = $this->currency->format($this->tax->calculate($result['min_option_price_before_discount'], $result['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
					} else {
						$min_option_price_before_discount = false;
					}
					if ($min_option_price_before_discount && $min_option_price) {
						$display_price = $min_option_price_before_discount;
						$display_special = ((float)$result['min_option_price_before_discount'] > (float)$result['min_option_price']) ? $min_option_price : false;
						$price_before = (float)$result['min_option_price_before_discount'];
						$price_after = (float)$result['min_option_price'];
					} elseif ($min_option_price) {
						$display_price = $min_option_price;
						$display_special = false;
						$price_before = $price_after = 0;
					} else {
						$display_price = $price;
						$display_special = $special;
						$price_before = (float)$result['price'];
						$price_after = (float)(isset($result['special']) ? $result['special'] : $result['price']);
					}
					$discount_percent = false;
					if ($display_special && $price_before > 0 && $price_after < $price_before) {
						$discount_percent = (int)round(($price_before - $price_after) / $price_before * 100);
					}
					$data['success_related_products'][] = array(
						'product_id'       => $result['product_id'],
						'thumb'            => $image,
						'name'             => $result['name'],
						'price'            => $display_price,
						'special'          => $display_special,
						'discount_percent' => $discount_percent,
						'rating'           => $this->config->get('config_review_status') ? (int)$result['rating'] : false,
						'reviews'          => isset($result['reviews']) ? $result['reviews'] : 0,
						'in_wishlist'      => in_array((int)$result['product_id'], $wishlist_ids, true),
						'href'             => $this->url->link('product/product', 'product_id=' . $result['product_id'])
					);
				}
			}
		}

		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = !empty($data['success_related_products']) ? '' : $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('common/success', $data));
	}
}
