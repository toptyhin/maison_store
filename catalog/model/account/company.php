<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ModelAccountCompany extends Model {

	public function addCompany($customer_id, $data) {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "customer_company` SET
			`customer_id` = '" . (int)$customer_id . "',
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

	public function editCompany($company_id, $data) {
		$this->db->query("UPDATE `" . DB_PREFIX . "customer_company` SET
			`company` = '" . $this->db->escape($data['company']) . "',
			`inn` = '" . $this->db->escape($data['inn']) . "',
			`kpp` = '" . $this->db->escape($data['kpp']) . "',
			`address` = '" . $this->db->escape($data['address']) . "',
			`bik` = '" . $this->db->escape($data['bik']) . "',
			`bank` = '" . $this->db->escape($data['bank']) . "',
			`rs` = '" . $this->db->escape($data['rs']) . "',
			`ks` = '" . $this->db->escape($data['ks']) . "'
			WHERE `company_id` = '" . (int)$company_id . "'
			AND `customer_id` = '" . (int)$this->customer->getId() . "'");
	}

	public function deleteCompany($company_id) {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "customer_company`
			WHERE `company_id` = '" . (int)$company_id . "'
			AND `customer_id` = '" . (int)$this->customer->getId() . "'");
	}

	public function getCompany($company_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer_company`
			WHERE `company_id` = '" . (int)$company_id . "'
			AND `customer_id` = '" . (int)$this->customer->getId() . "'");

		return $query->row;
	}

	public function getCompanies() {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "customer_company`
			WHERE `customer_id` = '" . (int)$this->customer->getId() . "'
			ORDER BY `date_added` DESC");

		return $query->rows;
	}

	public function getTotalCompanies() {
		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "customer_company`
			WHERE `customer_id` = '" . (int)$this->customer->getId() . "'");

		return $query->row['total'];
	}
}
