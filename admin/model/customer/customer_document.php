<?php
class ModelCustomerCustomerDocument extends Model {
	public function getDocuments($customer_id) {
		$query = $this->db->query("SELECT cd.*, u.name AS filename FROM `" . DB_PREFIX . "customer_document` cd 
			LEFT JOIN `" . DB_PREFIX . "upload` u ON (cd.upload_code = u.code) 
			WHERE cd.customer_id = '" . (int)$customer_id . "' 
			ORDER BY cd.date_added DESC");

		return $query->rows;
	}

	public function addDocument($customer_id, $document_type, $upload_code) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "customer_document` SET 
			customer_id = '" . (int)$customer_id . "', 
			document_type = '" . $this->db->escape($document_type) . "', 
			upload_code = '" . $this->db->escape($upload_code) . "', 
			date_added = NOW()");

		return $this->db->getLastId();
	}

	public function deleteDocument($customer_document_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "customer_document` WHERE customer_document_id = '" . (int)$customer_document_id . "'");
	}

	public function getDocument($customer_document_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer_document` WHERE customer_document_id = '" . (int)$customer_document_id . "'");

		return $query->row;
	}
}
