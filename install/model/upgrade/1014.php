<?php
/**
 * Customer Documents - Database migration
 * Creates oc_customer_document table for storing customer accounting documents
 */
class ModelUpgrade1014 extends Model {
	public function upgrade() {
		$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "customer_document'");
		if (!$query->num_rows) {
			$this->db->query("CREATE TABLE `" . DB_PREFIX . "customer_document` (
				`customer_document_id` int(11) NOT NULL AUTO_INCREMENT,
				`customer_id` int(11) NOT NULL,
				`document_type` varchar(32) NOT NULL,
				`upload_code` varchar(255) NOT NULL,
				`date_added` datetime NOT NULL,
				PRIMARY KEY (`customer_document_id`),
				KEY `customer_id` (`customer_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
		}

		// Add customer/customer_document permission to Administrator group
		$query = $this->db->query("SELECT permission FROM `" . DB_PREFIX . "user_group` WHERE user_group_id = 1");
		if ($query->num_rows) {
			$permission = json_decode($query->row['permission'], true);
			if (is_array($permission)) {
				$updated = false;
				if (!empty($permission['access']) && !in_array('customer/customer_document', $permission['access'])) {
					$permission['access'][] = 'customer/customer_document';
					$updated = true;
				}
				if (!empty($permission['modify']) && !in_array('customer/customer_document', $permission['modify'])) {
					$permission['modify'][] = 'customer/customer_document';
					$updated = true;
				}
				if ($updated) {
					$this->db->query("UPDATE `" . DB_PREFIX . "user_group` SET permission = '" . $this->db->escape(json_encode($permission)) . "' WHERE user_group_id = 1");
				}
			}
		}
	}
}
