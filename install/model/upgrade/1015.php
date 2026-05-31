<?php
/**
 * Customer Company - Database migration
 * Creates oc_customer_company table for storing customer company details (legal entity data)
 */
class ModelUpgrade1015 extends Model {
	public function upgrade() {
		$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "customer_company'");
		if (!$query->num_rows) {
			$this->db->query("CREATE TABLE `" . DB_PREFIX . "customer_company` (
				`company_id` int(11) NOT NULL AUTO_INCREMENT,
				`customer_id` int(11) NOT NULL DEFAULT '0',
				`company` varchar(255) NOT NULL DEFAULT '',
				`inn` varchar(20) NOT NULL DEFAULT '',
				`kpp` varchar(20) NOT NULL DEFAULT '',
				`address` varchar(255) NOT NULL DEFAULT '',
				`bik` varchar(20) NOT NULL DEFAULT '',
				`bank` varchar(255) NOT NULL DEFAULT '',
				`rs` varchar(34) NOT NULL DEFAULT '',
				`ks` varchar(34) NOT NULL DEFAULT '',
				`date_added` datetime NOT NULL,
				PRIMARY KEY (`company_id`),
				KEY `customer_id` (`customer_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
		}
	}
}
