<?php
class ModelAccountDocument extends Model {
	public function getDocuments($customer_id, $filter_data = array()) {
		$sql = "SELECT cd.*, u.name AS filename FROM `" . DB_PREFIX . "customer_document` cd 
			LEFT JOIN `" . DB_PREFIX . "upload` u ON (cd.upload_code = u.code) 
			WHERE cd.customer_id = '" . (int)$customer_id . "'";

		if (!empty($filter_data['filter_type'])) {
			$sql .= " AND cd.document_type = '" . $this->db->escape($filter_data['filter_type']) . "'";
		}

		if (!empty($filter_data['filter_search'])) {
			$sql .= " AND (u.name LIKE '%" . $this->db->escape($filter_data['filter_search']) . "%' OR cd.upload_code LIKE '%" . $this->db->escape($filter_data['filter_search']) . "%')";
		}

		if (!empty($filter_data['filter_date'])) {
			$sql .= " AND DATE(cd.date_added) = '" . $this->db->escape($filter_data['filter_date']) . "'";
		}

		$sql .= " ORDER BY cd.date_added DESC";

		if (isset($filter_data['start']) || isset($filter_data['limit'])) {
			$start = isset($filter_data['start']) ? (int)$filter_data['start'] : 0;
			$limit = isset($filter_data['limit']) ? (int)$filter_data['limit'] : 20;
			$sql .= " LIMIT " . $start . "," . $limit;
		}

		$query = $this->db->query($sql);
		return $query->rows;
	}

	public function getTotalDocuments($customer_id, $filter_data = array()) {
		$sql = "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "customer_document` cd 
			LEFT JOIN `" . DB_PREFIX . "upload` u ON (cd.upload_code = u.code) 
			WHERE cd.customer_id = '" . (int)$customer_id . "'";

		if (!empty($filter_data['filter_type'])) {
			$sql .= " AND cd.document_type = '" . $this->db->escape($filter_data['filter_type']) . "'";
		}

		if (!empty($filter_data['filter_search'])) {
			$sql .= " AND (u.name LIKE '%" . $this->db->escape($filter_data['filter_search']) . "%' OR cd.upload_code LIKE '%" . $this->db->escape($filter_data['filter_search']) . "%')";
		}

		if (!empty($filter_data['filter_date'])) {
			$sql .= " AND DATE(cd.date_added) = '" . $this->db->escape($filter_data['filter_date']) . "'";
		}

		$query = $this->db->query($sql);
		return (int)$query->row['total'];
	}

	public function getDocumentByCode($customer_id, $upload_code) {
		$query = $this->db->query("SELECT cd.*, u.name AS filename, u.filename AS storage_filename FROM `" . DB_PREFIX . "customer_document` cd 
			LEFT JOIN `" . DB_PREFIX . "upload` u ON (cd.upload_code = u.code) 
			WHERE cd.customer_id = '" . (int)$customer_id . "' AND cd.upload_code = '" . $this->db->escape($upload_code) . "'");

		return $query->row;
	}
}
