<?php
class ModelCatalogAttribute extends Model {
	public function addAttribute($data) {
		$this->db->query("INSERT INTO " . DB_PREFIX . "attribute SET attribute_group_id = '" . (int)$data['attribute_group_id'] . "', sort_order = '" . (int)$data['sort_order'] . "'");

		$attribute_id = $this->db->getLastId();

		foreach ($data['attribute_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_description SET attribute_id = '" . (int)$attribute_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "'");
		}

		$this->saveAttributeValues($attribute_id, isset($data['attribute_value']) ? $data['attribute_value'] : array());

		return $attribute_id;
	}

	public function editAttribute($attribute_id, $data) {
		$this->db->query("UPDATE " . DB_PREFIX . "attribute SET attribute_group_id = '" . (int)$data['attribute_group_id'] . "', sort_order = '" . (int)$data['sort_order'] . "' WHERE attribute_id = '" . (int)$attribute_id . "'");

		$this->db->query("DELETE FROM " . DB_PREFIX . "attribute_description WHERE attribute_id = '" . (int)$attribute_id . "'");

		foreach ($data['attribute_description'] as $language_id => $value) {
			$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_description SET attribute_id = '" . (int)$attribute_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($value['name']) . "'");
		}

		$this->saveAttributeValues($attribute_id, isset($data['attribute_value']) ? $data['attribute_value'] : array());
	}

	public function deleteAttribute($attribute_id) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "attribute WHERE attribute_id = '" . (int)$attribute_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "attribute_description WHERE attribute_id = '" . (int)$attribute_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "attribute_value WHERE attribute_id = '" . (int)$attribute_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "attribute_value_description WHERE attribute_id = '" . (int)$attribute_id . "'");
	}

	public function saveAttributeValues($attribute_id, $rows) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "attribute_value WHERE attribute_id = '" . (int)$attribute_id . "'");
		$this->db->query("DELETE FROM " . DB_PREFIX . "attribute_value_description WHERE attribute_id = '" . (int)$attribute_id . "'");

		if (!is_array($rows)) {
			return;
		}

		foreach ($rows as $attribute_value) {
			if (!$this->attributeValueRowHasContent($attribute_value)) {
				continue;
			}

			if (!empty($attribute_value['attribute_value_id'])) {
				$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_value SET attribute_value_id = '" . (int)$attribute_value['attribute_value_id'] . "', attribute_id = '" . (int)$attribute_id . "', sort_order = '" . (int)$attribute_value['sort_order'] . "'");
			} else {
				$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_value SET attribute_id = '" . (int)$attribute_id . "', sort_order = '" . (int)$attribute_value['sort_order'] . "'");
			}

			$attribute_value_id = $this->db->getLastId();

			foreach ($attribute_value['attribute_value_description'] as $language_id => $attribute_value_description) {
				$name = isset($attribute_value_description['name']) ? trim($attribute_value_description['name']) : '';

				if ($name === '') {
					continue;
				}

				$this->db->query("INSERT INTO " . DB_PREFIX . "attribute_value_description SET attribute_value_id = '" . (int)$attribute_value_id . "', attribute_id = '" . (int)$attribute_id . "', language_id = '" . (int)$language_id . "', name = '" . $this->db->escape($name) . "'");
			}
		}
	}

	protected function attributeValueRowHasContent($attribute_value) {
		if (empty($attribute_value['attribute_value_description']) || !is_array($attribute_value['attribute_value_description'])) {
			return false;
		}

		foreach ($attribute_value['attribute_value_description'] as $attribute_value_description) {
			if (!empty($attribute_value_description['name']) && trim($attribute_value_description['name']) !== '') {
				return true;
			}
		}

		return false;
	}

	public function getAttributeValueDescriptions($attribute_id) {
		$attribute_value_data = array();

		$attribute_value_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute_value WHERE attribute_id = '" . (int)$attribute_id . "' ORDER BY sort_order");

		foreach ($attribute_value_query->rows as $attribute_value) {
			$attribute_value_description_data = array();

			$attribute_value_description_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute_value_description WHERE attribute_value_id = '" . (int)$attribute_value['attribute_value_id'] . "'");

			foreach ($attribute_value_description_query->rows as $attribute_value_description) {
				$attribute_value_description_data[$attribute_value_description['language_id']] = array('name' => $attribute_value_description['name']);
			}

			$attribute_value_data[] = array(
				'attribute_value_id'          => $attribute_value['attribute_value_id'],
				'attribute_value_description' => $attribute_value_description_data,
				'sort_order'                  => $attribute_value['sort_order']
			);
		}

		return $attribute_value_data;
	}

	public function getAttributeValueAutocomplete($attribute_id, $language_id, $filter_name, $limit = 20) {
		$sql = "SELECT avd.name FROM " . DB_PREFIX . "attribute_value av LEFT JOIN " . DB_PREFIX . "attribute_value_description avd ON (av.attribute_value_id = avd.attribute_value_id) WHERE av.attribute_id = '" . (int)$attribute_id . "' AND avd.language_id = '" . (int)$language_id . "' AND avd.name <> ''";

		if ($filter_name !== '') {
			$sql .= " AND avd.name LIKE '" . $this->db->escape($filter_name) . "%'";
		}

		$sql .= " ORDER BY av.sort_order, avd.name ASC";

		if ($limit > 0) {
			$sql .= " LIMIT " . (int)$limit;
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getAttribute($attribute_id) {
		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE a.attribute_id = '" . (int)$attribute_id . "' AND ad.language_id = '" . (int)$this->config->get('config_language_id') . "'");

		return $query->row;
	}

	public function getAttributes($data = array()) {
		$sql = "SELECT *, (SELECT agd.name FROM " . DB_PREFIX . "attribute_group_description agd WHERE agd.attribute_group_id = a.attribute_group_id AND agd.language_id = '" . (int)$this->config->get('config_language_id') . "') AS attribute_group FROM " . DB_PREFIX . "attribute a LEFT JOIN " . DB_PREFIX . "attribute_description ad ON (a.attribute_id = ad.attribute_id) WHERE ad.language_id = '" . (int)$this->config->get('config_language_id') . "'";

		if (!empty($data['filter_name'])) {
			$sql .= " AND ad.name LIKE '" . $this->db->escape($data['filter_name']) . "%'";
		}

		if (!empty($data['filter_attribute_group_id'])) {
			$sql .= " AND a.attribute_group_id = '" . $this->db->escape($data['filter_attribute_group_id']) . "'";
		}

		$sort_data = array(
			'ad.name',
			'attribute_group',
			'a.sort_order'
		);

		if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
			$sql .= " ORDER BY " . $data['sort'];
		} else {
			$sql .= " ORDER BY attribute_group, ad.name";
		}

		if (isset($data['order']) && ($data['order'] == 'DESC')) {
			$sql .= " DESC";
		} else {
			$sql .= " ASC";
		}

		if (isset($data['start']) || isset($data['limit'])) {
			if ($data['start'] < 0) {
				$data['start'] = 0;
			}

			if ($data['limit'] < 1) {
				$data['limit'] = 20;
			}

			$sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
		}

		$query = $this->db->query($sql);

		return $query->rows;
	}

	public function getAttributeDescriptions($attribute_id) {
		$attribute_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "attribute_description WHERE attribute_id = '" . (int)$attribute_id . "'");

		foreach ($query->rows as $result) {
			$attribute_data[$result['language_id']] = array('name' => $result['name']);
		}

		return $attribute_data;
	}

	public function getTotalAttributes() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "attribute");

		return $query->row['total'];
	}

	public function getTotalAttributesByAttributeGroupId($attribute_group_id) {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM " . DB_PREFIX . "attribute WHERE attribute_group_id = '" . (int)$attribute_group_id . "'");

		return $query->row['total'];
	}
}
