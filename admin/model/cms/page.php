<?php
class ModelCmsPage extends Model {
	public function addPage($data) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "cms_page` SET route = '" . $this->db->escape($data['route']) . "', entity_id = '" . (int)$data['entity_id'] . "', store_id = '" . (int)$data['store_id'] . "', name = '" . $this->db->escape($data['name']) . "', status = '" . (int)$data['status'] . "', date_modified = NOW()");
		return $this->db->getLastId();
	}

	public function editPage($page_id, $data) {
		$this->db->query("UPDATE `" . DB_PREFIX . "cms_page` SET route = '" . $this->db->escape($data['route']) . "', entity_id = '" . (int)$data['entity_id'] . "', store_id = '" . (int)$data['store_id'] . "', name = '" . $this->db->escape($data['name']) . "', status = '" . (int)$data['status'] . "', date_modified = NOW() WHERE page_id = '" . (int)$page_id . "'");
	}

	public function deletePage($page_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cms_block` WHERE page_id = '" . (int)$page_id . "'");
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cms_page` WHERE page_id = '" . (int)$page_id . "'");
	}

	public function getPage($page_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cms_page` WHERE page_id = '" . (int)$page_id . "' LIMIT 1");
		return $query->num_rows ? $query->row : null;
	}

	public function getPageByRoute($route, $entity_id, $store_id = 0) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cms_page` WHERE route = '" . $this->db->escape($route) . "' AND entity_id = '" . (int)$entity_id . "' AND store_id = '" . (int)$store_id . "' LIMIT 1");
		return $query->num_rows ? $query->row : null;
	}

	public function getPages($data = array()) {
		$sql = "SELECT * FROM `" . DB_PREFIX . "cms_page` WHERE 1=1";

		if (!empty($data['filter_route'])) {
			$sql .= " AND route LIKE '" . $this->db->escape($data['filter_route']) . "%'";
		}

		$sql .= " ORDER BY date_modified DESC";

		if (isset($data['start']) || isset($data['limit'])) {
			$start = isset($data['start']) ? (int)$data['start'] : 0;
			$limit = isset($data['limit']) ? (int)$data['limit'] : 20;
			$sql .= " LIMIT " . $start . "," . $limit;
		}

		$query = $this->db->query($sql);
		return $query->rows;
	}

	public function getTotalPages() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "cms_page`");
		return (int)$query->row['total'];
	}

	public function getPageRouteLabel($page) {
		$labels = array(
			'common/home' => 'Главная',
			'information/information' => 'Информационная страница',
			'product/category' => 'Категория',
			'product/product' => 'Товар',
		);
		$base = isset($labels[$page['route']]) ? $labels[$page['route']] : $page['route'];
		if ((int)$page['entity_id'] > 0) {
			return $base . ' #' . (int)$page['entity_id'];
		}
		return $base;
	}

	public function getStorefrontUrl($page) {
		switch ($page['route']) {
			case 'common/home':
				return HTTP_CATALOG;
			case 'information/information':
				return HTTP_CATALOG . 'index.php?route=information/information&information_id=' . (int)$page['entity_id'];
			case 'product/category':
				return HTTP_CATALOG . 'index.php?route=product/category&path=' . (int)$page['entity_id'];
			case 'product/product':
				return HTTP_CATALOG . 'index.php?route=product/product&product_id=' . (int)$page['entity_id'];
		}
		return HTTP_CATALOG;
	}
}
