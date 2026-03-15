<?php
class ControllerExtensionModuleRecentlyViewed extends Controller {
	public function index($setting) {
		// Проверяем статус модуля
		if (!isset($setting['status']) || !$setting['status']) {
			return '';
		}

		$this->load->language('extension/module/recently_viewed');

		$this->load->model('catalog/product');
		$this->load->model('tool/image');

		$data['products'] = array();
		$data['heading_title'] = isset($setting['name']) ? $setting['name'] : $this->language->get('heading_title');

		// Значения по умолчанию для размеров изображений
		$width = isset($setting['width']) ? $setting['width'] : 300;
		$height = isset($setting['height']) ? $setting['height'] : 300;
		
		// Количество товаров для отображения (по умолчанию 4)
		$limit = isset($setting['limit']) ? (int)$setting['limit'] : 4;

		$wishlist_ids = $this->registry->get('wishlist_product_ids');
		if (!is_array($wishlist_ids)) {
			$wishlist_ids = array();
		}

		// Получаем просмотренные товары из сессии
		if (isset($this->session->data['recently_viewed']) && is_array($this->session->data['recently_viewed'])) {
			$recently_viewed_ids = $this->session->data['recently_viewed'];
			
			// Берем последние N товаров (из настройки модуля)
			$recently_viewed_ids = array_slice($recently_viewed_ids, 0, $limit);
			
			// Получаем информацию о товарах
			foreach ($recently_viewed_ids as $product_id) {
				$product_info = $this->model_catalog_product->getProduct($product_id);
				
				if ($product_info) {
					if ($product_info['image']) {
						$image = $this->model_tool_image->resize($product_info['image'], $width, $height);
					} else {
						$image = $this->model_tool_image->resize('placeholder.png', $width, $height);
					}

					if ($this->customer->isLogged() || !$this->config->get('config_customer_price')) {
						$price = $this->currency->format($this->tax->calculate($product_info['price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
					} else {
						$price = false;
					}

					if (!is_null($product_info['special']) && (float)$product_info['special'] >= 0) {
						$special = $this->currency->format($this->tax->calculate($product_info['special'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
						$tax_price = (float)$product_info['special'];
					} else {
						$special = false;
						$tax_price = (float)$product_info['price'];
					}

					if (!is_null($product_info['min_option_price']) && (float)$product_info['min_option_price'] >= 0) {
						$min_option_price = $this->currency->format($this->tax->calculate($product_info['min_option_price'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
						$tax_price = (float)$product_info['min_option_price'];
					} else {
						$min_option_price = false;
						$tax_price = (float)$product_info['price'];
					}

					if (!is_null($product_info['min_option_price_before_discount']) && (float)$product_info['min_option_price_before_discount'] >= 0) {
						$min_option_price_before_discount = $this->currency->format($this->tax->calculate($product_info['min_option_price_before_discount'], $product_info['tax_class_id'], $this->config->get('config_tax')), $this->session->data['currency']);
					} else {
						$min_option_price_before_discount = false;
					}

					if ($this->config->get('config_tax')) {
						$tax = $this->currency->format($tax_price, $this->session->data['currency']);
					} else {
						$tax = false;
					}

					if ($this->config->get('config_review_status')) {
						$rating = (int)$product_info['rating'];
					} else {
						$rating = false;
					}

					if ($min_option_price_before_discount && $min_option_price) {
						$display_price = $min_option_price_before_discount;
						$display_special = ((float)$product_info['min_option_price_before_discount'] > (float)$product_info['min_option_price']) ? $min_option_price : false;
						$price_before = (float)$product_info['min_option_price_before_discount'];
						$price_after = (float)$product_info['min_option_price'];
					} elseif ($min_option_price) {
						$display_price = $min_option_price;
						$display_special = false;
						$price_before = $price_after = 0;
					} else {
						$display_price = $price;
						$display_special = $special;
						$price_before = (float)$product_info['price'];
						$price_after = (float)(isset($product_info['special']) ? $product_info['special'] : $product_info['price']);
					}

					$discount_percent = false;
					if ($display_special && $price_before > 0 && $price_after < $price_before) {
						$discount_percent = (int)round(($price_before - $price_after) / $price_before * 100);
					}

					$data['products'][] = array(
						'product_id'  => $product_id,
						'thumb'       => $image,
						'name'        => $product_info['name'],
						'description' => utf8_substr(trim(strip_tags(html_entity_decode($product_info['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
						'price'       => $display_price,
						'special'     => $display_special,
						'discount_percent' => $discount_percent,
						'tax'         => $tax,
						'rating'      => $rating,
						'reviews'     => isset($product_info['reviews']) ? $product_info['reviews'] : 0,
						'in_wishlist' => in_array((int)$product_id, $wishlist_ids, true),
						'href'        => $this->url->link('product/product', 'product_id=' . $product_id)
					);
				}
			}
		}

		if (!empty($data['products'])) {
			return $this->load->view('extension/module/recently_viewed', $data);
		}
		
		return '';
	}
}
