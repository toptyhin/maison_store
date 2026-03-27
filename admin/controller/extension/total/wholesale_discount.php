<?php
class ControllerExtensionTotalWholesaleDiscount extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/total/wholesale_discount');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		$this->load->model('customer/customer_group');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$post_data = $this->request->post;
			$post_data['total_wholesale_discount_customer_group_tiers'] = $this->normalizeCustomerGroupTiers(
				isset($this->request->post['total_wholesale_discount_customer_group_tiers']) ? $this->request->post['total_wholesale_discount_customer_group_tiers'] : array()
			);

			$this->model_setting_setting->editSetting('total_wholesale_discount', $post_data);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=total', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=total', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/total/wholesale_discount', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/total/wholesale_discount', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=total', true);

		if (isset($this->request->post['total_wholesale_discount_status'])) {
			$data['total_wholesale_discount_status'] = $this->request->post['total_wholesale_discount_status'];
		} else {
			$data['total_wholesale_discount_status'] = $this->config->get('total_wholesale_discount_status');
		}

		if (isset($this->request->post['total_wholesale_discount_sort_order'])) {
			$data['total_wholesale_discount_sort_order'] = $this->request->post['total_wholesale_discount_sort_order'];
		} else {
			$data['total_wholesale_discount_sort_order'] = $this->config->get('total_wholesale_discount_sort_order');
		}

		if (isset($this->request->post['total_wholesale_discount_customer_group_tiers'])) {
			$data['total_wholesale_discount_customer_group_tiers'] = $this->normalizeCustomerGroupTiers($this->request->post['total_wholesale_discount_customer_group_tiers']);
		} else {
			$data['total_wholesale_discount_customer_group_tiers'] = $this->normalizeCustomerGroupTiers($this->config->get('total_wholesale_discount_customer_group_tiers'));
		}

		$data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/total/wholesale_discount', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/total/wholesale_discount')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		return !$this->error;
	}

	private function normalizeCustomerGroupTiers($group_tiers) {
		$normalized = array();

		if (is_string($group_tiers)) {
			$decoded = json_decode($group_tiers, true);

			if (is_array($decoded)) {
				$group_tiers = $decoded;
			} else {
				$group_tiers = array();
			}
		}

		if (!is_array($group_tiers)) {
			return $normalized;
		}

		foreach ($group_tiers as $customer_group_id => $tiers) {
			if (!is_array($tiers)) {
				continue;
			}

			$by_threshold = array();

			foreach ($tiers as $tier) {
				if (!is_array($tier)) {
					continue;
				}

				$threshold = isset($tier['threshold']) ? (float)$tier['threshold'] : 0;
				$percent = isset($tier['percent']) ? (float)$tier['percent'] : 0;

				if ($threshold <= 0 || $percent <= 0) {
					continue;
				}

				$threshold_key = number_format($threshold, 4, '.', '');
				$by_threshold[$threshold_key] = array(
					'threshold' => (float)$threshold_key,
					'percent' => (float)number_format($percent, 2, '.', '')
				);
			}

			if ($by_threshold) {
				ksort($by_threshold, SORT_NUMERIC);
				$normalized[(int)$customer_group_id] = array_values($by_threshold);
			}
		}

		return $normalized;
	}
}
