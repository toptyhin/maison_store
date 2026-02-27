<?php
class ModelCheckoutUrLic extends Model {
	/**
	 * Добавляет реквизиты юрлица.
	 * @param array $data customer_id, company, inn, kpp, address, bik, bank, rs, ks
	 * @return int ur_lic_id
	 */
	public function addUrLic($data) {
		$customer_id = isset($data['customer_id']) ? (int)$data['customer_id'] : (int)$this->customer->getId();
		$this->db->query("INSERT INTO `" . DB_PREFIX . "customer_company` SET
			`customer_id` = '" . $customer_id . "',
			`company` = '" . $this->db->escape($data['company']) . "',
			`inn` = '" . $this->db->escape($data['inn']) . "',
			`kpp` = '" . $this->db->escape($data['kpp']) . "',
			`address` = '" . $this->db->escape($data['address']) . "',
			`bik` = '" . $this->db->escape($data['bik']) . "',
			`bank` = '" . $this->db->escape($data['bank']) . "',
			`rs` = '" . $this->db->escape($data['rs']) . "',
			`ks` = '" . $this->db->escape($data['ks']) . "',
			`date_added` = NOW()");

		return $this->db->getLastId();
	}

	public function getUrLic($customer_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer_company` WHERE `customer_id` = '" . (int)$customer_id . "'");
		return $query->row;
	}
}
