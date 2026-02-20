<?php
/**
 * Product Filter Module - Catalog model
 * Fetches filter values (manufacturers, attributes, options, price range, etc.) for category
 */
class ModelExtensionModuleProductFilter extends Model {
	/**
	 * Get filter data for category page
	 * @param int $category_id
	 * @param array $context_data Data from category controller (path, sort, order, limit)
	 * @return array filter_groups, current_values, action_url, etc.
	 */
	public function getFilterData($category_id, $context_data = array()) {
		$configs = $this->getCategoryFilterConfig($category_id);
		if (empty($configs)) {
			return array('filter_groups' => array());
		}

		$filter_groups = array();
		$path = isset($context_data['path']) ? $context_data['path'] : $category_id;

		foreach ($configs as $config) {
			$group = array(
				'criterion_type' => $config['criterion_type'],
				'source_id'      => $config['source_id'],
				'widget_type'    => $config['widget_type'],
				'name'           => '',
				'values'         => array(),
				'min'            => 0,
				'max'            => 0,
				'current'        => array()
			);

			switch ($config['criterion_type']) {
				case 'price':
					$group['name'] = $this->language->get('text_price');
					$range = $this->getPriceRange($category_id);
					$group['min'] = round($range['min']);
					$group['max'] = round($range['max']);
					$group['current'] = array(
						'min' => isset($this->request->get['filter_price_min']) ? round((float)$this->request->get['filter_price_min']) : round($range['min']),
						'max' => isset($this->request->get['filter_price_max']) ? round((float)$this->request->get['filter_price_max']) : round($range['max'])
					);
					break;
				case 'manufacturer':
					$group['name'] = $this->language->get('text_manufacturer');
					$group['values'] = $this->getManufacturers($category_id);
					$group['current'] = !empty($this->request->get['filter_manufacturer']) ? array_map('intval', explode(',', $this->request->get['filter_manufacturer'])) : array();
					break;
				case 'rating':
					$group['name'] = $this->language->get('text_rating');
					$group['values'] = array(
						array('value' => 4, 'name' => '4 ' . $this->language->get('text_stars') . '+'),
						array('value' => 3, 'name' => '3 ' . $this->language->get('text_stars') . '+'),
					);
					$group['current'] = isset($this->request->get['filter_rating']) ? array((int)$this->request->get['filter_rating']) : array();
					break;
				case 'discount':
					$group['name'] = $this->language->get('text_discount');
					$group['values'] = array(array('value' => 1, 'name' => $this->language->get('text_yes')));
					$group['current'] = !empty($this->request->get['filter_discount']) ? array(1) : array();
					break;
				case 'attribute':
					$attr = $this->getAttributeInfo($config['source_id']);
					$group['name'] = $attr ? $attr['name'] : 'Attribute #' . $config['source_id'];
					$group['values'] = $this->getAttributeValues($category_id, $config['source_id']);
					$key = 'filter_attr_' . $config['source_id'];
					$group['current'] = !empty($this->request->get[$key]) ? array_map('trim', explode(',', $this->request->get[$key])) : array();
					break;
				case 'option':
					$opt = $this->getOptionInfo($config['source_id']);
					$group['name'] = $opt ? $opt['name'] : 'Option #' . $config['source_id'];
					$group['values'] = $this->getOptionValues($category_id, $config['source_id']);
					$key = 'filter_opt_' . $config['source_id'];
					$group['current'] = !empty($this->request->get[$key]) ? array_map('intval', explode(',', $this->request->get[$key])) : array();
					break;
				default:
					continue 2;
			}

			$group['path'] = $path;
			$group['base_params'] = $this->getBaseUrlParams();
			$group['base_url'] = $this->url->link('product/category', $this->buildQueryString($group['base_params']));

			// Add href and active for discrete values (toggle on click)
			if (!empty($group['values']) && in_array($group['criterion_type'], array('manufacturer', 'attribute', 'option', 'rating', 'discount'))) {
				$param_key = $this->getParamKey($config);
				foreach ($group['values'] as $idx => $val) {
					$group['values'][$idx]['active'] = in_array($val['value'], $group['current']);
					$group['values'][$idx]['href'] = $this->buildFilterValueUrl($group['base_params'], $param_key, $val['value'], $group['current']);
				}
			}

			// Price form: hidden inputs for all base params except filter_price_min/max
			if ($group['criterion_type'] === 'price') {
				$group['hidden_filter_params'] = array();
				foreach ($group['base_params'] as $k => $v) {
					if ($k !== 'filter_price_min' && $k !== 'filter_price_max') {
						$group['hidden_filter_params'][] = array('name' => $k, 'value' => $v);
					}
				}
			}

			$filter_groups[] = $group;
		}

		return array(
			'filter_groups' => $filter_groups
		);
	}

	public function getCategoryFilterConfig($category_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category_filter_config WHERE category_id = '" . (int)$category_id . "' ORDER BY sort_order ASC, criterion_type ASC");
		return $query->rows;
	}

	protected function getPriceRange($category_id) {
		$customer_group_id = (int)$this->config->get('config_customer_group_id');
		$sql = "SELECT MIN(
			CASE
				WHEN ps.product_id IS NOT NULL AND (ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()) THEN ps.price
				WHEN pd2.product_id IS NOT NULL AND (pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW()) THEN pd2.price
				ELSE p.price
			END
		) AS min_price, MAX(p.price) AS max_price
		FROM " . DB_PREFIX . "product_to_category p2c
		LEFT JOIN " . DB_PREFIX . "category_path cp ON (p2c.category_id = cp.category_id)
		LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)
		LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
		LEFT JOIN " . DB_PREFIX . "product_special ps ON (p.product_id = ps.product_id AND ps.customer_group_id = '" . $customer_group_id . "' AND (ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))
		LEFT JOIN " . DB_PREFIX . "product_discount pd2 ON (p.product_id = pd2.product_id AND pd2.customer_group_id = '" . $customer_group_id . "' AND pd2.quantity = 1 AND (pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW()))
		WHERE cp.path_id = '" . (int)$category_id . "' AND p.status = 1 AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "'";
		$query = $this->db->query($sql);
		return array(
			'min' => $query->row['min_price'] ? (float)$query->row['min_price'] : 0,
			'max' => $query->row['max_price'] ? (float)$query->row['max_price'] : 100000
		);
	}

	protected function getManufacturers($category_id) {
		$query = $this->db->query("SELECT DISTINCT m.manufacturer_id, m.name
			FROM " . DB_PREFIX . "product_to_category p2c
			LEFT JOIN " . DB_PREFIX . "category_path cp ON (p2c.category_id = cp.category_id)
			LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)
			LEFT JOIN " . DB_PREFIX . "manufacturer m ON (p.manufacturer_id = m.manufacturer_id)
			LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
			WHERE cp.path_id = '" . (int)$category_id . "' AND p.status = 1 AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND m.manufacturer_id IS NOT NULL
			ORDER BY m.name ASC");
		$result = array();
		foreach ($query->rows as $row) {
			$result[] = array('value' => $row['manufacturer_id'], 'name' => $row['name']);
		}
		return $result;
	}

	protected function getAttributeInfo($attribute_id) {
		$query = $this->db->query("SELECT ad.name FROM " . DB_PREFIX . "attribute_description ad WHERE ad.attribute_id = '" . (int)$attribute_id . "' AND ad.language_id = '" . (int)$this->config->get('config_language_id') . "'");
		return $query->row;
	}

	/**
	 * Get attribute ID by name
	 * @param string $attribute_name
	 * @return int|false Attribute ID or false if not found
	 */
	public function getAttributeIdByName($attribute_name) {
		$query = $this->db->query("SELECT attribute_id FROM " . DB_PREFIX . "attribute_description WHERE name = '" . $this->db->escape($attribute_name) . "' AND language_id = '" . (int)$this->config->get('config_language_id') . "' LIMIT 1");
		if ($query->num_rows) {
			return (int)$query->row['attribute_id'];
		}
		return false;
	}

	protected function getAttributeValues($category_id, $attribute_id) {
		$query = $this->db->query("SELECT DISTINCT pa.text
			FROM " . DB_PREFIX . "product_to_category p2c
			LEFT JOIN " . DB_PREFIX . "category_path cp ON (p2c.category_id = cp.category_id)
			LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)
			LEFT JOIN " . DB_PREFIX . "product_attribute pa ON (p.product_id = pa.product_id AND pa.attribute_id = '" . (int)$attribute_id . "' AND pa.language_id = '" . (int)$this->config->get('config_language_id') . "')
			LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
			WHERE cp.path_id = '" . (int)$category_id . "' AND p.status = 1 AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND pa.text != ''
			ORDER BY pa.text ASC");
		$result = array();
		foreach ($query->rows as $row) {
			$result[] = array('value' => $row['text'], 'name' => $row['text']);
		}
		return $result;
	}

	protected function getOptionInfo($option_id) {
		$query = $this->db->query("SELECT od.name FROM " . DB_PREFIX . "option_description od WHERE od.option_id = '" . (int)$option_id . "' AND od.language_id = '" . (int)$this->config->get('config_language_id') . "'");
		return $query->row;
	}

	/**
	 * Get base URL params (path, sort, order, limit, filter, filter_*) from request
	 */
	public function getBaseUrlParams() {
		$params = array();
		if (!empty($this->request->get['path'])) {
			$params['path'] = $this->request->get['path'];
		}
		if (!empty($this->request->get['sort'])) {
			$params['sort'] = $this->request->get['sort'];
		}
		if (!empty($this->request->get['order'])) {
			$params['order'] = $this->request->get['order'];
		}
		if (!empty($this->request->get['limit'])) {
			$params['limit'] = $this->request->get['limit'];
		}
		if (!empty($this->request->get['filter'])) {
			$params['filter'] = $this->request->get['filter'];
		}
		foreach ($this->request->get as $k => $v) {
			if ($v !== '' && (strpos($k, 'filter_attr_') === 0 || strpos($k, 'filter_opt_') === 0 || in_array($k, array('filter_manufacturer', 'filter_rating', 'filter_discount', 'filter_price_min', 'filter_price_max', 'filter_top_brands', 'filter_rating_high', 'filter_country_russia')))) {
				$params[$k] = $v;
			}
		}
		return $params;
	}

	/**
	 * Build query string from params, optionally merging filter_* params
	 * @param array $base
	 * @param array $extra_keys Keys to include from request (e.g. filter_price_min, filter_price_max)
	 */
	public function buildQueryString($base, $extra_keys = array()) {
		foreach ($extra_keys as $key) {
			if (isset($this->request->get[$key]) && $this->request->get[$key] !== '') {
				$base[$key] = $this->request->get[$key];
			}
		}
		$str = '';
		foreach ($base as $k => $v) {
			$str .= ($str ? '&' : '') . $k . '=' . urlencode((string)$v);
		}
		return $str;
	}

	protected function getParamKey($config) {
		$ct = $config['criterion_type'];
		if ($ct === 'manufacturer') return 'filter_manufacturer';
		if ($ct === 'rating') return 'filter_rating';
		if ($ct === 'discount') return 'filter_discount';
		if ($ct === 'attribute') return 'filter_attr_' . $config['source_id'];
		if ($ct === 'option') return 'filter_opt_' . $config['source_id'];
		return '';
	}

	/**
	 * Build URL for toggling a filter value (add if not in current, remove if in current)
	 */
	protected function buildFilterValueUrl($base_params, $param_key, $value, $current) {
		$current = array_map('strval', $current);
		$value_str = (string)$value;
		if (in_array($value_str, $current)) {
			$new = array_diff($current, array($value_str));
		} else {
			$new = $current;
			$new[] = $value_str;
		}
		$new = array_values(array_filter($new));
		if (!empty($new)) {
			$base_params[$param_key] = implode(',', $new);
		} else {
			unset($base_params[$param_key]);
		}
		$str = '';
		foreach ($base_params as $k => $v) {
			$str .= ($str ? '&' : '') . $k . '=' . urlencode((string)$v);
		}
		return $this->url->link('product/category', $str);
	}

	protected function getOptionValues($category_id, $option_id) {
		$query = $this->db->query("SELECT DISTINCT ovd.option_value_id, ovd.name
			FROM " . DB_PREFIX . "product_to_category p2c
			LEFT JOIN " . DB_PREFIX . "category_path cp ON (p2c.category_id = cp.category_id)
			LEFT JOIN " . DB_PREFIX . "product_option_value pov ON (p2c.product_id = pov.product_id AND pov.option_id = '" . (int)$option_id . "')
			LEFT JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)
			LEFT JOIN " . DB_PREFIX . "option_value_description ovd ON (pov.option_value_id = ovd.option_value_id AND ovd.language_id = '" . (int)$this->config->get('config_language_id') . "')
			LEFT JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
			WHERE cp.path_id = '" . (int)$category_id . "' AND p.status = 1 AND p.date_available <= NOW() AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "' AND pov.product_option_value_id IS NOT NULL AND ovd.name != ''
			ORDER BY ovd.name ASC");
		$result = array();
		foreach ($query->rows as $row) {
			$result[] = array('value' => $row['option_value_id'], 'name' => $row['name']);
		}
		return $result;
	}
}
