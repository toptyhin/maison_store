<?php
class ModelUpgrade1012 extends Model {
	public function upgrade() {
		$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "product_option_value_field'");

		if (!$query->num_rows) {
			$this->db->query("CREATE TABLE `" . DB_PREFIX . "product_option_value_field` (
				`product_option_value_field_id` int(11) NOT NULL AUTO_INCREMENT,
				`product_option_value_id` int(11) NOT NULL,
				`field_key` varchar(255) NOT NULL,
				`field_value` text NOT NULL,
				PRIMARY KEY (`product_option_value_field_id`),
				KEY `product_option_value_id` (`product_option_value_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
		}
	}
}
