<?php
class ModelCmsBlock extends Model {
	public function addBlock($page_id, $data) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "cms_block` SET page_id = '" . (int)$page_id . "', type = '" . $this->db->escape($data['type']) . "', slot = '" . $this->db->escape($data['slot']) . "', sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "', is_draft = '" . (int)$data['is_draft'] . "', settings = '" . $this->db->escape(json_encode($data['settings'], JSON_UNESCAPED_UNICODE)) . "'");
		return $this->db->getLastId();
	}

	public function editBlock($block_id, $data) {
		$this->db->query("UPDATE `" . DB_PREFIX . "cms_block` SET type = '" . $this->db->escape($data['type']) . "', slot = '" . $this->db->escape($data['slot']) . "', sort_order = '" . (int)$data['sort_order'] . "', status = '" . (int)$data['status'] . "', is_draft = '" . (int)$data['is_draft'] . "', settings = '" . $this->db->escape(json_encode($data['settings'], JSON_UNESCAPED_UNICODE)) . "' WHERE block_id = '" . (int)$block_id . "'");
	}

	public function deleteBlock($block_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "cms_block` WHERE block_id = '" . (int)$block_id . "'");
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

	public function getBlocksByPageId($page_id, $includeDraft = true) {
		$sql = "SELECT * FROM `" . DB_PREFIX . "cms_block` WHERE page_id = '" . (int)$page_id . "'";
		if (!$includeDraft) {
			$sql .= " AND is_draft = '0'";
		}
		$sql .= " ORDER BY slot ASC, sort_order ASC, block_id ASC";
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

	public function reorderBlocks($page_id, $block_ids) {
		$sort = 0;
		foreach ($block_ids as $block_id) {
			$this->db->query("UPDATE `" . DB_PREFIX . "cms_block` SET sort_order = '" . (int)$sort . "' WHERE block_id = '" . (int)$block_id . "' AND page_id = '" . (int)$page_id . "'");
			$sort++;
		}
	}

	public function publishDrafts($page_id) {
		$this->db->query("UPDATE `" . DB_PREFIX . "cms_block` SET is_draft = '0', status = '1' WHERE page_id = '" . (int)$page_id . "'");
	}

	public function getDefaultSettings($type) {
		switch ($type) {
			case 'about_hero':
				return array('image' => '', 'title' => array('1' => ''), 'subtitle' => array('1' => ''));
			case 'feature_cards':
				return array('title' => array('1' => ''), 'items' => array());
			case 'media_text':
				return array(
					'image' => '',
					'label' => array('1' => ''),
					'title' => array('1' => ''),
					'paragraphs' => array(),
					'button_text' => array('1' => 'Узнать больше'),
					'button_link' => array('type' => 'contact', 'id' => 0, 'url' => ''),
				);
			case 'category_grid':
				return array(
					'title' => array('1' => ''),
					'subtitle' => array('1' => ''),
					'link_text' => array('1' => 'Смотреть каталог'),
					'link' => array('type' => 'catalog', 'id' => 0, 'url' => ''),
					'tiles' => array(),
					'list' => array(),
				);
			case 'rich_text':
				return array('title' => array('1' => ''), 'content' => array('1' => ''));
			case 'promo_banner':
				return array('image' => '', 'title' => array('1' => ''), 'subtitle' => array('1' => ''), 'link' => array('type' => 'custom', 'id' => 0, 'url' => ''));
			case 'cta_button':
				return array('text' => array('1' => ''), 'style' => 'primary', 'link' => array('type' => 'custom', 'id' => 0, 'url' => ''));
			case 'hero_slideshow':
			case 'featured_products':
			case 'module_reference':
				return array('module_code' => '');
			case 'category_tiles':
				return array('title' => array('1' => ''), 'items' => array());
		}
		return array();
	}

	public function sanitizeSettings($type, $settings) {
		if (!is_array($settings)) {
			$settings = array();
		}

		if (isset($settings['button_link'])) {
			$settings['button_link'] = $this->sanitizeLink($settings['button_link']);
		}
		if (isset($settings['link'])) {
			$settings['link'] = $this->sanitizeLink($settings['link']);
		}

		return $settings;
	}

	private function sanitizeLink($link) {
		if (!is_array($link)) {
			return array('type' => 'custom', 'id' => 0, 'url' => '');
		}
		$allowed = array('category', 'information', 'contact', 'catalog', 'custom');
		$type = isset($link['type']) && in_array($link['type'], $allowed, true) ? $link['type'] : 'custom';
		$url = isset($link['url']) ? trim((string)$link['url']) : '';
		if (preg_match('#^(javascript|data|vbscript):#i', $url)) {
			$url = '';
		}
		return array(
			'type' => $type,
			'id' => isset($link['id']) ? (int)$link['id'] : 0,
			'url' => $url,
		);
	}
}
