<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerAccountAccount extends Controller {
	public function index() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/account', '', true);

			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('account/account');

		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->setRobots('noindex,follow');

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];

			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		} 
		
		$data['edit'] = $this->url->link('account/edit', '', true);
		$data['password'] = $this->url->link('account/password', '', true);
		$data['address'] = $this->url->link('account/address', '', true);
		$data['action'] = $this->url->link('account/edit', '', true);

		$this->load->model('account/customer');
		$customer_info = $this->model_account_customer->getCustomer($this->customer->getId());

		$data['firstname'] = $customer_info ? $customer_info['firstname'] : $this->customer->getFirstName();
		$data['lastname'] = $customer_info ? $customer_info['lastname'] : $this->customer->getLastName();
		$data['email'] = $customer_info ? $customer_info['email'] : $this->customer->getEmail();
		$data['telephone'] = $customer_info ? $customer_info['telephone'] : '';

		$data['custom_fields'] = array();
		$data['account_custom_field'] = array();
		$this->load->model('account/custom_field');
		$custom_fields = $this->model_account_custom_field->getCustomFields($this->config->get('config_customer_group_id'));
		foreach ($custom_fields as $custom_field) {
			if ($custom_field['location'] == 'account') {
				$data['custom_fields'][] = $custom_field;
			}
		}
		if ($customer_info && !empty($customer_info['custom_field'])) {
			$data['account_custom_field'] = json_decode($customer_info['custom_field'], true) ?: array();
		}

		$data['dob'] = '';
		$data['gender'] = '';
		$data['custom_field_dob'] = null;
		$data['custom_field_gender'] = null;
		foreach ($data['custom_fields'] as $cf) {
			if ($cf['type'] == 'date') {
				$data['custom_field_dob'] = $cf;
				$data['dob'] = isset($data['account_custom_field'][$cf['custom_field_id']]) ? $data['account_custom_field'][$cf['custom_field_id']] : '';
			}
			if ($cf['type'] == 'radio' && (stripos($cf['name'], 'пол') !== false || stripos($cf['name'], 'gender') !== false)) {
				$data['custom_field_gender'] = $cf;
				$data['gender'] = isset($data['account_custom_field'][$cf['custom_field_id']]) ? $data['account_custom_field'][$cf['custom_field_id']] : '';
			}
		}

		$this->load->language('account/edit');
		$data['entry_firstname'] = $this->language->get('entry_firstname');
		$data['entry_lastname'] = $this->language->get('entry_lastname');
		$data['entry_email'] = $this->language->get('entry_email');
		$data['entry_telephone'] = $this->language->get('entry_telephone');
		$data['button_save'] = 'Сохранить изменения';
		$data['text_password'] = 'Пароль';
		$data['text_password_change'] = 'Сменить пароль';
		$data['text_password_last_change'] = '';
		$data['text_section_info'] = 'Основная информация';
		$data['text_section_security'] = 'Безопасность';
		$data['entry_dob'] = 'Дата рождения';
		$data['entry_gender'] = 'Пол';
		$data['text_gender_male'] = 'Мужской';
		$data['text_gender_female'] = 'Женский';

		$data['error_firstname'] = '';
		$data['error_lastname'] = '';
		$data['error_email'] = '';
		$data['error_telephone'] = '';

		$data['credit_cards'] = array();
		
		$files = glob(DIR_APPLICATION . 'controller/extension/credit_card/*.php');
		
		foreach ($files as $file) {
			$code = basename($file, '.php');
			
			if ($this->config->get('payment_' . $code . '_status') && $this->config->get('payment_' . $code . '_card')) {
				$this->load->language('extension/credit_card/' . $code, 'extension');

				$data['credit_cards'][] = array(
					'name' => $this->language->get('extension')->get('heading_title'),
					'href' => $this->url->link('extension/credit_card/' . $code, '', true)
				);
			}
		}
		
		$data['wishlist'] = $this->url->link('account/wishlist');
		$data['order'] = $this->url->link('account/order', '', true);
		$data['download'] = $this->url->link('account/download', '', true);
		
		if ($this->config->get('total_reward_status')) {
			$data['reward'] = $this->url->link('account/reward', '', true);
		} else {
			$data['reward'] = '';
		}		
		
		$data['return'] = $this->url->link('account/return', '', true);
		$data['transaction'] = $this->url->link('account/transaction', '', true);
		$data['newsletter'] = $this->url->link('account/newsletter', '', true);
		$data['recurring'] = $this->url->link('account/recurring', '', true);
		
		$this->load->model('account/customer');
		
		$affiliate_info = $this->model_account_customer->getAffiliate($this->customer->getId());
		
		if (!$affiliate_info) {	
			$data['affiliate'] = $this->url->link('account/affiliate/add', '', true);
		} else {
			$data['affiliate'] = $this->url->link('account/affiliate/edit', '', true);
		}
		
		if ($affiliate_info) {		
			$data['tracking'] = $this->url->link('account/tracking', '', true);
		} else {
			$data['tracking'] = '';
		}

		$data['logout'] = $this->url->link('account/logout', '', true);
		$data['home'] = $this->url->link('common/home');
		$this->load->language('extension/module/account');
		$data['text_logout'] = $this->language->get('text_logout');

		if ($this->config->get('total_reward_status')) {
			$this->load->model('account/reward');
			$data['reward_total'] = $this->model_account_reward->getTotalPoints();
		} else {
			$data['reward_total'] = 0;
		}
		
		$data['menu'] = $this->load->controller('account/menu');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');
		
		$this->response->setOutput($this->load->view('account/account', $data));
	}

	public function country() {
		$json = array();

		$this->load->model('localisation/country');

		$country_info = $this->model_localisation_country->getCountry($this->request->get['country_id']);

		if ($country_info) {
			$this->load->model('localisation/zone');

			$json = array(
				'country_id'        => $country_info['country_id'],
				'name'              => $country_info['name'],
				'iso_code_2'        => $country_info['iso_code_2'],
				'iso_code_3'        => $country_info['iso_code_3'],
				'address_format'    => $country_info['address_format'],
				'postcode_required' => $country_info['postcode_required'],
				'zone'              => $this->model_localisation_zone->getZonesByCountryId($this->request->get['country_id']),
				'status'            => $country_info['status']
			);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
