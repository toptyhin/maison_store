<?php
/**
 * Product Filter Module - Event handlers
 * Injects filter params from $_GET into $data for getProducts/getTotalProducts
 */
class ControllerEventProductFilter extends Controller {
	/**
	 * Called before model/catalog/product/getProducts and getTotalProducts
	 * Injects filter params from request into $args[0] (the $data array)
	 */
	public function injectFilterParams(&$route, &$args) {
		$this->resolveFilterSeoPreset();

		if (!isset($args[0]) || !is_array($args[0])) {
			return;
		}
		$data = &$args[0];

		// filter_price: "min-max" or separate filter_price_min, filter_price_max
		// Округляем до целых чисел для работы с инпутами
		if (!empty($this->request->get['filter_price'])) {
			$parts = explode('-', $this->request->get['filter_price']);
			if (count($parts) >= 2) {
				$data['filter_price_min'] = (float)round((float)trim($parts[0]));
				$data['filter_price_max'] = (float)round((float)trim($parts[1]));
			}
		}
		if (isset($this->request->get['filter_price_min']) && $this->request->get['filter_price_min'] !== '') {
			$data['filter_price_min'] = (float)round((float)$this->request->get['filter_price_min']);
		}
		if (isset($this->request->get['filter_price_max']) && $this->request->get['filter_price_max'] !== '') {
			$data['filter_price_max'] = (float)round((float)$this->request->get['filter_price_max']);
		}

		// filter_manufacturer: comma-separated IDs
		if (!empty($this->request->get['filter_manufacturer'])) {
			$ids = array_map('intval', explode(',', $this->request->get['filter_manufacturer']));
			$ids = array_filter($ids);
			if (!empty($ids)) {
				$data['filter_manufacturer_ids'] = $ids;
			}
		}

		// filter_rating: min rating
		if (isset($this->request->get['filter_rating']) && $this->request->get['filter_rating'] !== '') {
			$data['filter_rating_min'] = max(1, min(5, (int)$this->request->get['filter_rating']));
		}

		// filter_rating_high: show products with rating > 4
		if (!empty($this->request->get['filter_rating_high'])) {
			$data['filter_rating_min'] = 4;
		}

		// filter_discount: 1 = only products with special/discount
		if (!empty($this->request->get['filter_discount'])) {
			$data['filter_has_discount'] = true;
		}

		// filter_top_brands: sort by brand purchase count
		if (!empty($this->request->get['filter_top_brands'])) {
			$data['filter_top_brands'] = true;
		}

		// filter_country_russia: filter by country attribute = "Россия"
		if (!empty($this->request->get['filter_country_russia'])) {
			$this->load->model('extension/module/product_filter');
			$country_attr_id = $this->model_extension_module_product_filter->getAttributeIdByName('страна');
			if ($country_attr_id) {
				if (!isset($data['filter_attribute'])) {
					$data['filter_attribute'] = array();
				}
				$data['filter_attribute'][$country_attr_id] = array('Россия');
			}
		}

		// filter_attr_{attribute_id}: comma-separated values
		$data['filter_attribute'] = array();
		foreach ($this->request->get as $key => $value) {
			if (strpos($key, 'filter_attr_') === 0 && $value !== '') {
				$attr_id = (int)str_replace('filter_attr_', '', $key);
				if ($attr_id > 0) {
					$values = array_map('trim', explode(',', $value));
					$values = array_filter($values);
					if (!empty($values)) {
						$data['filter_attribute'][$attr_id] = $values;
					}
				}
			}
		}

		// filter_opt_{option_id}: comma-separated option_value_ids
		$data['filter_option'] = array();
		foreach ($this->request->get as $key => $value) {
			if (strpos($key, 'filter_opt_') === 0 && $value !== '') {
				$opt_id = (int)str_replace('filter_opt_', '', $key);
				if ($opt_id > 0) {
					$ids = array_map('intval', explode(',', $value));
					$ids = array_filter($ids);
					if (!empty($ids)) {
						$data['filter_option'][$opt_id] = $ids;
					}
				}
			}
		}
	}

	/**
	 * Called before view/product/filter is rendered
	 * Adds filter_groups and filter data to $data for the filter template
	 */
	public function addFilterData(&$route, &$data, &$code) {
		
		$this->resolveFilterSeoPreset();
		$category_id = isset($data['category_id']) ? (int)$data['category_id'] : 0;
		if (!$category_id) {
			$path = isset($data['path']) ? $data['path'] : (isset($this->request->get['path']) ? $this->request->get['path'] : '');
			if (empty($path)) {
				return;
			}
			$parts = explode('_', (string)$path);
			$category_id = (int)end($parts);
		}
		if ($category_id <= 0) {
			return;
		}
		$path = isset($data['path']) ? $data['path'] : (isset($this->request->get['path']) ? $this->request->get['path'] : (string)$category_id);
		$this->load->language('extension/module/product_filter');
		$this->load->model('extension/module/product_filter');
		$context = array_merge($data, array('path' => $path));
		$filter_data = $this->model_extension_module_product_filter->getFilterData($category_id, $context);
		foreach ($filter_data as $key => $value) {
			$data[$key] = $value;
		}
		$data['text_filters'] = $this->language->get('text_filters');
		$data['text_hide'] = $this->language->get('text_hide');
		$data['text_from'] = $this->language->get('text_from');
		$data['text_to'] = $this->language->get('text_to');
		$data['text_apply'] = $this->language->get('text_apply');
		$data['text_select'] = $this->language->get('text_select');
		$data['text_no_filters'] = $this->language->get('text_no_filters');
		
		// Get module settings for toggle filters
		$this->load->model('setting/module');
		$modules = $this->model_setting_module->getModulesByCode('product_filter');
		$toggle_settings = array(
			'toggle_top_brands' => 1,
			'toggle_rating_high' => 1,
			'toggle_country_russia' => 1
		);
		if (!empty($modules)) {
			foreach ($modules as $module) {
				if (!empty($module['setting'])) {
					$settings = json_decode($module['setting'], true);
					if (!empty($settings)) {
						if (isset($settings['toggle_top_brands'])) {
							$toggle_settings['toggle_top_brands'] = (int)$settings['toggle_top_brands'];
						}
						if (isset($settings['toggle_rating_high'])) {
							$toggle_settings['toggle_rating_high'] = (int)$settings['toggle_rating_high'];
						}
						if (isset($settings['toggle_country_russia'])) {
							$toggle_settings['toggle_country_russia'] = (int)$settings['toggle_country_russia'];
						}
					}
					break; // Use first module's settings
				}
			}
		}
		
		// Prepare toggle filter data
		$base_params = $this->model_extension_module_product_filter->getBaseUrlParams();
		
		// Top Bands toggle (only if enabled in settings)
		if ($toggle_settings['toggle_top_brands']) {
			$top_brands_params = $base_params;
			if (!empty($this->request->get['filter_top_brands'])) {
				unset($top_brands_params['filter_top_brands']);
			} else {
				$top_brands_params['filter_top_brands'] = '1';
			}
			$top_brands_query = $this->model_extension_module_product_filter->buildQueryString($top_brands_params);
			$data['toggle_top_brands'] = array(
				'enabled' => true,
				'active' => !empty($this->request->get['filter_top_brands']),
				'href' => $this->url->link('product/category', $top_brands_query)
			);
		} else {
			$data['toggle_top_brands'] = array('enabled' => false);
		}
		
		// High Rating toggle (only if enabled in settings)
		if ($toggle_settings['toggle_rating_high']) {
			$rating_high_params = $base_params;
			if (!empty($this->request->get['filter_rating_high'])) {
				unset($rating_high_params['filter_rating_high']);
			} else {
				$rating_high_params['filter_rating_high'] = '1';
			}
			$rating_high_query = $this->model_extension_module_product_filter->buildQueryString($rating_high_params);
			$data['toggle_rating_high'] = array(
				'enabled' => true,
				'active' => !empty($this->request->get['filter_rating_high']),
				'href' => $this->url->link('product/category', $rating_high_query)
			);
		} else {
			$data['toggle_rating_high'] = array('enabled' => false);
		}
		
		// Made in Russia toggle (only if enabled in settings)
		if ($toggle_settings['toggle_country_russia']) {
			$country_russia_params = $base_params;
			$country_attr_id = $this->model_extension_module_product_filter->getAttributeIdByName('страна');
			if ($country_attr_id) {
				$attr_key = 'filter_attr_' . $country_attr_id;
				if (!empty($this->request->get[$attr_key]) && strpos($this->request->get[$attr_key], 'Россия') !== false) {
					// Remove Russia from filter
					$current_values = array_map('trim', explode(',', $this->request->get[$attr_key]));
					$new_values = array_diff($current_values, array('Россия'));
					if (!empty($new_values)) {
						$country_russia_params[$attr_key] = implode(',', $new_values);
					} else {
						unset($country_russia_params[$attr_key]);
					}
				} else {
					// Add Russia to filter
					if (!empty($this->request->get[$attr_key])) {
						$current_values = array_map('trim', explode(',', $this->request->get[$attr_key]));
						$current_values[] = 'Россия';
						$country_russia_params[$attr_key] = implode(',', array_unique($current_values));
					} else {
						$country_russia_params[$attr_key] = 'Россия';
					}
				}
				$country_russia_query = $this->model_extension_module_product_filter->buildQueryString($country_russia_params);
				$data['toggle_country_russia'] = array(
					'enabled' => true,
					'active' => !empty($this->request->get[$attr_key]) && strpos($this->request->get[$attr_key], 'Россия') !== false,
					'href' => $this->url->link('product/category', $country_russia_query)
				);
			} else {
				$data['toggle_country_russia'] = array(
					'enabled' => true,
					'active' => false,
					'href' => '#'
				);
			}
		} else {
			$data['toggle_country_russia'] = array('enabled' => false);
		}
	}

	/**
	 * Append extended filter params to sort/limit/pagination URLs in category view
	 */
	public function categoryViewBefore(&$route, &$data, &$code) {
		// Get category_id

		$category_id = isset($data['category_id']) ? (int)$data['category_id'] : 0;
		// $path = isset($data['path']) ? $data['path'] : (isset($this->request->get['path']) ? $this->request->get['path'] : '');
		// if (!empty($path)) {
		// 	$parts = explode('_', (string)$path);
		// 	$category_id = (int)end($parts);
		// }
		
		if (!$category_id || $category_id <= 0) {
			$data['product_filter_script'] = '';
			return;
		}
		
		// Check if module is installed and enabled
		$this->load->model('setting/module');
		$modules = $this->model_setting_module->getModulesByCode('product_filter');
		$module_enabled = false;
		if (!empty($modules)) {
			foreach ($modules as $module) {
				if (!empty($module['setting'])) {
					$settings = json_decode($module['setting'], true);
					if (!empty($settings['status'])) {
						$module_enabled = true;
						break;
					}
				} else {
					// If setting is empty, consider module enabled (default state)
					$module_enabled = true;
					break;
				}
			}
		}
		
		// Check if category has filter settings
		// First check if filter_groups already exist (added by addFilterData)
		$has_filter_groups = !empty($data['filter_groups']) && is_array($data['filter_groups']) && count($data['filter_groups']) > 0;
		
		$has_filter_settings = false;
		if ($module_enabled) {
			if ($has_filter_groups) {
				// filter_groups already exist, so settings are present
				$has_filter_settings = true;
			} else {
				// Check directly in database
				$this->load->model('extension/module/product_filter');
				$configs = $this->model_extension_module_product_filter->getCategoryFilterConfig($category_id);
				$has_filter_settings = !empty($configs);
			}
		}
		
		// If module is enabled and category has filter settings, add script path to data
		// Script will be rendered in category.twig template
		if ($module_enabled && $has_filter_settings) {
			$data['product_filter_script'] = 'catalog/view/theme/maison/js/product_filter.js';
		} else {
			$data['product_filter_script'] = '';
		}
		
		$params = $this->getExtendedFilterParams();
		if (empty($params)) {
			return;
		}
		$append = '';
		foreach ($params as $k => $v) {
			$append .= '&' . $k . '=' . urlencode((string)$v);
		}
		if (isset($data['sorts']) && is_array($data['sorts'])) {
			foreach ($data['sorts'] as $i => $s) {
				if (!empty($s['href'])) {
					$data['sorts'][$i]['href'] .= $append;
				}
			}
		}
		if (isset($data['limits']) && is_array($data['limits'])) {
			foreach ($data['limits'] as $i => $l) {
				if (!empty($l['href'])) {
					$data['limits'][$i]['href'] .= $append;
				}
			}
		}
		if (isset($data['pagination']) && is_string($data['pagination'])) {
			$data['pagination'] = preg_replace_callback(
				'/(href=["\'])([^"\']+)(["\'])/',
				function ($m) use ($append) {
					return $m[1] . $m[2] . $append . $m[3];
				},
				$data['pagination']
			);
		}
	}

	/**
	 * Resolve ?filter_seo=keyword: lookup oc_filter_seo_url and merge filter_data into request
	 */
	protected function resolveFilterSeoPreset() {
		$keyword = isset($this->request->get['filter_seo']) ? trim($this->request->get['filter_seo']) : '';
		if ($keyword === '') {
			return;
		}
		$store_id = (int)$this->config->get('config_store_id');
		$language_id = (int)$this->config->get('config_language_id');
		$query = $this->db->query("SELECT category_id, filter_data FROM " . DB_PREFIX . "filter_seo_url
			WHERE keyword = '" . $this->db->escape($keyword) . "'
			AND store_id = '" . $store_id . "'
			AND language_id = '" . $language_id . "'
			LIMIT 1");
		if (!$query->num_rows) {
			return;
		}
		$row = $query->row;
		$filter_data = json_decode($row['filter_data'], true);
		if (!is_array($filter_data)) {
			$filter_data = array();
		}
		$this->request->get['path'] = (int)$row['category_id'];
		foreach ($filter_data as $k => $v) {
			$this->request->get[$k] = $v;
		}
		unset($this->request->get['filter_seo']);
	}

	protected function getExtendedFilterParams() {
		$out = array();
		foreach ($this->request->get as $k => $v) {
			if ($v !== '' && (strpos($k, 'filter_attr_') === 0 || strpos($k, 'filter_opt_') === 0 || in_array($k, array('filter_manufacturer', 'filter_rating', 'filter_discount', 'filter_price_min', 'filter_price_max', 'filter_top_brands', 'filter_rating_high', 'filter_country_russia')))) {
				$out[$k] = $v;
			}
		}
		return $out;
	}
}
