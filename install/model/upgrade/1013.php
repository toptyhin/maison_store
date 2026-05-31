<?php
/**
 * Product Filter Module - Database migration
 * Creates oc_category_filter_config and oc_filter_seo_url tables
 */
class ModelUpgrade1013 extends Model {
	public function upgrade() {
		// oc_category_filter_config
		$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "category_filter_config'");
		if (!$query->num_rows) {
			$this->db->query("CREATE TABLE `" . DB_PREFIX . "category_filter_config` (
				`category_id` int(11) NOT NULL,
				`criterion_type` varchar(32) NOT NULL,
				`source_id` int(11) NOT NULL DEFAULT 0,
				`widget_type` varchar(32) NOT NULL DEFAULT 'checkboxes',
				`sort_order` int(3) NOT NULL DEFAULT 0,
				PRIMARY KEY (`category_id`, `criterion_type`, `source_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
		}

		// oc_filter_seo_url
		$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "filter_seo_url'");
		if (!$query->num_rows) {
			$this->db->query("CREATE TABLE `" . DB_PREFIX . "filter_seo_url` (
				`filter_seo_id` int(11) NOT NULL AUTO_INCREMENT,
				`store_id` int(11) NOT NULL DEFAULT 0,
				`language_id` int(11) NOT NULL,
				`category_id` int(11) NOT NULL,
				`filter_hash` varchar(32) NOT NULL DEFAULT '',
				`keyword` varchar(255) NOT NULL,
				`filter_data` text NOT NULL,
				PRIMARY KEY (`filter_seo_id`),
				KEY `keyword_store_language` (`keyword`, `store_id`, `language_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
		}
	}
}
