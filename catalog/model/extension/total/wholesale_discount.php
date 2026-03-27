<?php
class ModelExtensionTotalWholesaleDiscount extends Model {
	public function getTotal($total) {
		if (!$this->config->get('total_wholesale_discount_status')) {
			return;
		}

		$tiers = $this->getDiscountTiersByGroupId($this->getCurrentCustomerGroupId());

		if (!$tiers) {
			return;
		}

		$sub_total = $this->cart->getSubTotal();

		if ($sub_total <= 0) {
			return;
		}

		$current_discount_percent = $this->getCurrentDiscountPercent($sub_total, $tiers);

		if ($current_discount_percent <= 0) {
			return;
		}

		$discount_total = round($sub_total * ($current_discount_percent / 100), 2);

		if ($discount_total <= 0) {
			return;
		}

		$this->load->language('extension/total/wholesale_discount');

		$total['totals'][] = array(
			'code'       => 'wholesale_discount',
			'title'      => sprintf($this->language->get('text_wholesale_discount'), rtrim(rtrim(number_format($current_discount_percent, 2, '.', ''), '0'), '.')),
			'value'      => -$discount_total,
			'sort_order' => $this->config->get('total_wholesale_discount_sort_order')
		);

		$total['total'] -= $discount_total;
	}

	private function getCurrentCustomerGroupId() {
		if ($this->customer->isLogged()) {
			return (int)$this->customer->getGroupId();
		}

		return (int)$this->config->get('config_customer_group_id');
	}

	private function getDiscountTiersByGroupId($customer_group_id) {
		$raw = $this->config->get('total_wholesale_discount_customer_group_tiers');

		if (is_string($raw)) {
			$decoded = json_decode($raw, true);
			$raw = is_array($decoded) ? $decoded : array();
		}

		if (!is_array($raw)) {
			return array();
		}

		$group_tiers = isset($raw[$customer_group_id]) ? $raw[$customer_group_id] : array();

		if (!is_array($group_tiers)) {
			return array();
		}

		$tiers = array();

		foreach ($group_tiers as $tier) {
			if (!is_array($tier)) {
				continue;
			}

			$threshold = isset($tier['threshold']) ? (float)$tier['threshold'] : 0;
			$percent = isset($tier['percent']) ? (float)$tier['percent'] : 0;

			if ($threshold <= 0 || $percent <= 0) {
				continue;
			}

			$tiers[] = array(
				'threshold' => $threshold,
				'percent' => $percent
			);
		}

		usort($tiers, function($a, $b) {
			if ($a['threshold'] == $b['threshold']) {
				return 0;
			}

			return ($a['threshold'] < $b['threshold']) ? -1 : 1;
		});

		return $tiers;
	}

	private function getCurrentDiscountPercent($sub_total, $tiers) {
		$current = 0;

		foreach ($tiers as $tier) {
			if ($sub_total >= $tier['threshold']) {
				$current = (float)$tier['percent'];
			}
		}

		return $current;
	}
}
