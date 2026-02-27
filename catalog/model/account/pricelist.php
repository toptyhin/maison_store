<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ModelAccountPricelist extends Model {

	/**
	 * Get pricelist rows for selected categories and customer group.
	 * One row per product option value (or one row per product if no options).
	 *
	 * @param array $category_ids Category IDs to include
	 * @param int $customer_group_id
	 * @param int $store_id
	 * @param int $language_id
	 * @return array Rows: product_id, sku, option_code, name, price, quantity
	 */
	public function getPricelistRows($category_ids, $customer_group_id, $store_id, $language_id) {
		if (empty($category_ids)) {
			return array();
		}

		$category_ids = array_map('intval', $category_ids);
		$category_ids = array_filter($category_ids);
		if (empty($category_ids)) {
			return array();
		}

		$cg = (int)$customer_group_id;
		$sid = (int)$store_id;
		$lid = (int)$language_id;

		// Get product IDs from selected categories (including subcategories via path)
		$product_ids = array();
		$sql = "SELECT DISTINCT p2c.product_id
			FROM " . DB_PREFIX . "category_path cp
			INNER JOIN " . DB_PREFIX . "product_to_category p2c ON (cp.category_id = p2c.category_id)
			INNER JOIN " . DB_PREFIX . "product p ON (p2c.product_id = p.product_id)
			INNER JOIN " . DB_PREFIX . "product_to_store p2s ON (p.product_id = p2s.product_id)
			WHERE cp.path_id IN (" . implode(',', $category_ids) . ")
				AND p.status = '1'
				AND p.date_available <= NOW()
				AND p2s.store_id = '" . $sid . "'";
		$query = $this->db->query($sql);
		foreach ($query->rows as $row) {
			$product_ids[] = (int)$row['product_id'];
		}
		$product_ids = array_unique($product_ids);
		if (empty($product_ids)) {
			return array();
		}

		$rows = array();
		$pid_list = implode(',', $product_ids);

		// Product base prices (with discount/special for customer group)
		$product_prices = array();
		$pq = $this->db->query("
			SELECT p.product_id, p.sku, p.price,
				(SELECT price FROM " . DB_PREFIX . "product_discount pd2
					WHERE pd2.product_id = p.product_id AND pd2.customer_group_id = '" . $cg . "'
					AND pd2.quantity = 1
					AND ((pd2.date_start = '0000-00-00' OR pd2.date_start < NOW()) AND (pd2.date_end = '0000-00-00' OR pd2.date_end > NOW()))
					ORDER BY pd2.priority ASC, pd2.price ASC LIMIT 1) AS discount,
				(SELECT price FROM " . DB_PREFIX . "product_special ps
					WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . $cg . "'
					AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))
					ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special
			FROM " . DB_PREFIX . "product p
			WHERE p.product_id IN (" . $pid_list . ")
		");
		foreach ($pq->rows as $p) {
			$base = (float)$p['price'];
			if (!empty($p['special']) && (float)$p['special'] > 0) {
				$base = (float)$p['special'];
			} elseif (!empty($p['discount']) && (float)$p['discount'] > 0) {
				$base = (float)$p['discount'];
			}
			$product_prices[$p['product_id']] = array(
				'sku'   => $p['sku'],
				'price' => $base
			);
		}

		// Product names
		$product_names = array();
		$pnq = $this->db->query("SELECT product_id, name FROM " . DB_PREFIX . "product_description WHERE product_id IN (" . $pid_list . ") AND language_id = '" . $lid . "'");
		foreach ($pnq->rows as $r) {
			$product_names[$r['product_id']] = $r['name'];
		}

		// Option values with prices and custom fields
		$ov_sql = "
			SELECT pov.product_option_value_id, pov.product_id, pov.price, pov.price_prefix, pov.quantity,
				povp.price AS group_price,
				povp.special_price AS group_special,
				(SELECT field_value FROM " . DB_PREFIX . "product_option_value_field povf
					WHERE povf.product_option_value_id = pov.product_option_value_id AND povf.field_key = 'code' LIMIT 1) AS option_code,
				(SELECT ovd.name FROM " . DB_PREFIX . "option_value_description ovd
					WHERE ovd.option_value_id = pov.option_value_id AND ovd.language_id = '" . $lid . "' LIMIT 1) AS option_name
			FROM " . DB_PREFIX . "product_option_value pov
			LEFT JOIN " . DB_PREFIX . "product_option_value_prices povp
				ON pov.product_option_value_id = povp.product_option_value_id AND povp.customer_group_id = '" . $cg . "'
			WHERE pov.product_id IN (" . $pid_list . ")
		";
		$ovq = $this->db->query($ov_sql);

		// Products that have options
		$products_with_options = array();
		foreach ($ovq->rows as $ov) {
			$products_with_options[$ov['product_id']] = true;
		}

		// Build rows: one per option value
		foreach ($ovq->rows as $ov) {
			$pid = (int)$ov['product_id'];
			$base_info = isset($product_prices[$pid]) ? $product_prices[$pid] : array('sku' => '', 'price' => 0);
			$name = isset($product_names[$pid]) ? $product_names[$pid] : '';

			$price = 0;
			if (isset($ov['group_price']) && $ov['group_price'] !== null && $ov['group_price'] !== '') {
				$gp = (float)$ov['group_price'];
				$gsp = isset($ov['group_special']) ? (float)$ov['group_special'] : 0;
				if ($gsp > 0) {
					$price = $gsp;
				} else {
					$price = $gp;
				}
			} else {
				$base = (float)$base_info['price'];
				$opt_price = (float)$ov['price'];
				$prefix = $ov['price_prefix'];
				if ($prefix == '=') {
					$price = $opt_price;
				} elseif ($prefix == '+') {
					$price = $base + $opt_price;
				} elseif ($prefix == '-') {
					$price = $base - $opt_price;
				} else {
					$price = $base + $opt_price;
				}
			}

			$rows[] = array(
				'product_id'       => $pid,
				'sku'              => $base_info['sku'],
				'option_code'      => isset($ov['option_code']) && $ov['option_code'] ? $ov['option_code'] : '',
				'option_name'      => isset($ov['option_name']) ? html_entity_decode($ov['option_name'], ENT_QUOTES, 'UTF-8') : '',
				'name'             => html_entity_decode($name, ENT_QUOTES, 'UTF-8'),
				'price'            => $price,
				'quantity'         => isset($ov['quantity']) ? (int)$ov['quantity'] : 0
			);
		}

		// Products without options: one row each
		foreach ($product_ids as $pid) {
			if (isset($products_with_options[$pid])) {
				continue;
			}
			$base_info = isset($product_prices[$pid]) ? $product_prices[$pid] : array('sku' => '', 'price' => 0);
			$name = isset($product_names[$pid]) ? $product_names[$pid] : '';

			$qty = 0;
			$qoq = $this->db->query("SELECT quantity FROM " . DB_PREFIX . "product WHERE product_id = '" . (int)$pid . "'");
			if ($qoq->num_rows) {
				$qty = (int)$qoq->row['quantity'];
			}

			$rows[] = array(
				'product_id'       => $pid,
				'sku'              => $base_info['sku'],
				'option_code'      => '',
				'option_name'      => '',
				'name'             => html_entity_decode($name, ENT_QUOTES, 'UTF-8'),
				'price'            => (float)$base_info['price'],
				'quantity'         => $qty
			);
		}

		return $rows;
	}
}
