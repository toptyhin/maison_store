<?php
/**
 * Product Filter Module - Admin model
 */
class ModelExtensionModuleProductFilter extends Model {
	public function getCategoryFilterConfig($category_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category_filter_config WHERE category_id = '" . (int)$category_id . "' ORDER BY sort_order ASC");
		return $query->rows;
	}

	public function saveCategoryFilterConfig($category_id, $configs) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "category_filter_config WHERE category_id = '" . (int)$category_id . "'");
		if (is_array($configs)) {
			$sort = 0;
			foreach ($configs as $c) {
				$type = $this->db->escape($c['criterion_type']);
				$source_id = (int)(isset($c['source_id']) ? $c['source_id'] : 0);
				$widget = $this->db->escape(isset($c['widget_type']) ? $c['widget_type'] : 'checkboxes');
				$this->db->query("INSERT INTO " . DB_PREFIX . "category_filter_config SET category_id = '" . (int)$category_id . "', criterion_type = '" . $type . "', source_id = " . $source_id . ", widget_type = '" . $widget . "', sort_order = " . ($sort++));
			}
		}
	}

	public function getAttributes() {
		$query = $this->db->query("SELECT a.attribute_id, ad.name FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE ad.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY ad.name");
		return $query->rows;
	}

	public function getOptions() {
		$query = $this->db->query("SELECT o.option_id, od.name FROM " . DB_PREFIX . "option o LEFT JOIN " . DB_PREFIX . "option_description od ON (o.option_id = od.option_id) WHERE od.language_id = '" . (int)$this->config->get('config_language_id') . "' ORDER BY od.name");
		return $query->rows;
	}
}
