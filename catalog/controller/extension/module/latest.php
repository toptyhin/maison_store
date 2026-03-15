<?php
class ControllerExtensionModuleLatest extends Controller {
	public function index($setting) {
		$this->load->language('extension/module/latest');

		$this->load->model('catalog/product');

		$this->load->model('tool/image');

		$data['products'] = array();

		$results = $this->model_catalog_product->getLatestProducts($setting['limit']);

		$wishlist_ids = $this->registry->get('wishlist_product_ids');
		if (!is_array($wishlist_ids)) {
			$wishlist_ids = array();
		}

		if ($results) {
			foreach ($results as $result) {
				if ($result['image']) {
					$image = $this->model_tool_image->resize($result['image'], $setting['width'], $setting['height']);
				} else {
					$image = $this->model_tool_image->resize('placeholder.png', $setting['width'], $setting['height']);
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

				if ($this->config->get('config_tax')) {
					$tax = $this->currency->format($tax_price, $this->session->data['currency']);
				} else {
					$tax = false;
				}

				if ($this->config->get('config_review_status')) {
					$rating = $result['rating'];
				} else {
					$rating = false;
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

				$data['products'][] = array(
					'product_id'  => $result['product_id'],
					'thumb'       => $image,
					'name'        => $result['name'],
					'description' => utf8_substr(trim(strip_tags(html_entity_decode($result['description'], ENT_QUOTES, 'UTF-8'))), 0, $this->config->get('theme_' . $this->config->get('config_theme') . '_product_description_length')) . '..',
					'price'       => $display_price,
					'special'     => $display_special,
					'discount_percent' => $discount_percent,
					'tax'         => $tax,
					'rating'      => $rating,
					'in_wishlist' => in_array((int)$result['product_id'], $wishlist_ids, true),
					'href'        => $this->url->link('product/product', 'product_id=' . $result['product_id'])
				);
			}

			return $this->load->view('extension/module/latest', $data);
		}
	}
}
