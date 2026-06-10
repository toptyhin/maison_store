<?php
class ModelCmsPage extends Model {
	public function getPage($page_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cms_page` WHERE page_id = '" . (int)$page_id . "' LIMIT 1");
		return $query->num_rows ? $query->row : null;
	}

	public function getActivePage($route, $entity_id, $store_id = null) {
		if ($store_id === null) {
			$store_id = (int)$this->config->get('config_store_id');
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cms_page` WHERE route = '" . $this->db->escape($route) . "' AND entity_id = '" . (int)$entity_id . "' AND store_id = '" . (int)$store_id . "' AND status = '1' LIMIT 1");
		return $query->num_rows ? $query->row : null;
	}

	public function getPageByRoute($route, $entity_id, $store_id = null) {
		if ($store_id === null) {
			$store_id = (int)$this->config->get('config_store_id');
		}

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cms_page` WHERE route = '" . $this->db->escape($route) . "' AND entity_id = '" . (int)$entity_id . "' AND store_id = '" . (int)$store_id . "' LIMIT 1");
		return $query->num_rows ? $query->row : null;
	}

	public function getBlocks($page_id, $slot = null, $preview = false) {
		$sql = "SELECT * FROM `" . DB_PREFIX . "cms_block` WHERE page_id = '" . (int)$page_id . "'";

		if ($slot !== null) {
			$sql .= " AND slot = '" . $this->db->escape($slot) . "'";
		}

		if ($preview) {
			$sql .= " AND (status = '1' OR is_draft = '1')";
		} else {
			$sql .= " AND status = '1' AND is_draft = '0'";
		}

		$sql .= " ORDER BY sort_order ASC, block_id ASC";

		$query = $this->db->query($sql);
		$blocks = array();

		foreach ($query->rows as $row) {
			$row['settings'] = json_decode($row['settings'], true);
			if (!is_array($row['settings'])) {
				$row['settings'] = array();
			}
			$blocks[] = $row;
		}

		return $blocks;
	}

	public function getBlock($block_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "cms_block` WHERE block_id = '" . (int)$block_id . "' LIMIT 1");
		if (!$query->num_rows) {
			return null;
		}
		$row = $query->row;
		$row['settings'] = json_decode($row['settings'], true);
		if (!is_array($row['settings'])) {
			$row['settings'] = array();
		}
		return $row;
	}
}
