<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerAccountCompany extends Controller {
	private $error = array();

	public function index() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/company', '', true);
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		if ($this->customer->getGroupId() <= 1) {
			$this->response->redirect($this->url->link('account/account', '', true));
		}

		$this->load->language('account/company');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->setRobots('noindex,follow');
		$this->load->model('account/company');

		$this->getList();
	}

	public function add() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/company', '', true);
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		if ($this->customer->getGroupId() <= 1) {
			$this->response->redirect($this->url->link('account/account', '', true));
		}

		$this->load->language('account/company');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->setRobots('noindex,follow');
		$this->load->model('account/company');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_account_company->addCompany($this->customer->getId(), $this->request->post);
			$this->session->data['success'] = $this->language->get('text_add');
			$this->response->redirect($this->url->link('account/company', '', true));
		}

		$this->getForm();
	}

	public function edit() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/company', '', true);
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		if ($this->customer->getGroupId() <= 1) {
			$this->response->redirect($this->url->link('account/account', '', true));
		}

		$this->load->language('account/company');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->setRobots('noindex,follow');
		$this->load->model('account/company');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			$this->model_account_company->editCompany($this->request->get['company_id'], $this->request->post);
			$this->session->data['success'] = $this->language->get('text_edit');
			$this->response->redirect($this->url->link('account/company', '', true));
		}

		$this->getForm();
	}

	public function delete() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/company', '', true);
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		if ($this->customer->getGroupId() <= 1) {
			$this->response->redirect($this->url->link('account/account', '', true));
		}

		$this->load->language('account/company');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->setRobots('noindex,follow');
		$this->load->model('account/company');

		if (isset($this->request->get['company_id']) && $this->validateDelete()) {
			$this->model_account_company->deleteCompany($this->request->get['company_id']);
			$this->session->data['success'] = $this->language->get('text_delete');
			$this->response->redirect($this->url->link('account/company', '', true));
		}

		$this->getList();
	}

	protected function getList() {
		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('account/company', '', true)
		);

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['companies'] = array();
		$results = $this->model_account_company->getCompanies();

		foreach ($results as $result) {
			$data['companies'][] = array(
				'company_id'          => $result['company_id'],
				'company'            => $result['company'],
				'inn'               => $result['inn'],
				'kpp'               => $result['kpp'],
				'address'           => $result['address'],
				'bank'              => $result['bank'],
				'rs'                => $result['rs'],
				'update'            => $this->url->link('account/company/edit', 'company_id=' . $result['company_id'], true),
				'delete'            => $this->url->link('account/company/delete', 'company_id=' . $result['company_id'], true)
			);
		}

		$data['add'] = $this->url->link('account/company/add', '', true);
		$data['back'] = $this->url->link('account/account', '', true);
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_company_book'] = $this->language->get('text_company_book');
		$data['button_edit'] = $this->language->get('button_edit');
		$data['button_delete'] = $this->language->get('button_delete');
		$data['button_back'] = $this->language->get('button_back');
		$data['button_new_company'] = $this->language->get('button_new_company');
		$data['text_confirm'] = $this->language->get('text_confirm');
		$data['text_empty'] = $this->language->get('text_empty');
		$data['entry_company'] = $this->language->get('entry_company');
		$data['text_inn'] = $this->language->get('text_inn');
		$data['text_kpp'] = $this->language->get('text_kpp');
		$data['text_bank'] = $this->language->get('text_bank');
		$data['text_rs'] = $this->language->get('text_rs');

		$data['menu'] = $this->load->controller('account/menu');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/company_list', $data));
	}

	protected function getForm() {
		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/home')
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_account'),
			'href' => $this->url->link('account/account', '', true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('account/company', '', true)
		);

		if (!isset($this->request->get['company_id'])) {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_company_add'),
				'href' => $this->url->link('account/company/add', '', true)
			);
		} else {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('text_company_edit'),
				'href' => $this->url->link('account/company/edit', 'company_id=' . $this->request->get['company_id'], true)
			);
		}

		$data['text_company'] = !isset($this->request->get['company_id']) ? $this->language->get('text_company_add') : $this->language->get('text_company_edit');
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_company_book'] = $this->language->get('text_company_book');

		$data['entry_company'] = $this->language->get('entry_company');
		$data['entry_inn'] = $this->language->get('entry_inn');
		$data['entry_kpp'] = $this->language->get('entry_kpp');
		$data['entry_address'] = $this->language->get('entry_address');
		$data['entry_bik'] = $this->language->get('entry_bik');
		$data['entry_bank'] = $this->language->get('entry_bank');
		$data['entry_rs'] = $this->language->get('entry_rs');
		$data['entry_ks'] = $this->language->get('entry_ks');

		$data['button_back'] = $this->language->get('button_back');
		$data['button_continue'] = $this->language->get('button_continue');

		$error_keys = array('company', 'inn', 'kpp', 'address', 'bik', 'bank', 'rs', 'ks');
		foreach ($error_keys as $key) {
			$data['error_' . $key] = isset($this->error[$key]) ? $this->error[$key] : '';
		}

		if (!isset($this->request->get['company_id'])) {
			$data['action'] = $this->url->link('account/company/add', '', true);
		} else {
			$data['action'] = $this->url->link('account/company/edit', 'company_id=' . $this->request->get['company_id'], true);
		}

		$company_info = array();
		if (isset($this->request->get['company_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$company_info = $this->model_account_company->getCompany($this->request->get['company_id']);
		}

		$field_keys = array('company', 'inn', 'kpp', 'address', 'bik', 'bank', 'rs', 'ks');
		foreach ($field_keys as $key) {
			if (isset($this->request->post[$key])) {
				$data[$key] = $this->request->post[$key];
			} elseif (!empty($company_info)) {
				$data[$key] = $company_info[$key];
			} else {
				$data[$key] = '';
			}
		}

		$data['back'] = $this->url->link('account/company', '', true);

		$data['menu'] = $this->load->controller('account/menu');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/company_form', $data));
	}

	protected function validateForm() {
		if ((utf8_strlen(trim($this->request->post['company'])) < 1) || (utf8_strlen(trim($this->request->post['company'])) > 255)) {
			$this->error['company'] = $this->language->get('error_company');
		}

		if ((utf8_strlen(trim($this->request->post['inn'])) < 10) || (utf8_strlen(trim($this->request->post['inn'])) > 12)) {
			$this->error['inn'] = $this->language->get('error_inn');
		}

		if (utf8_strlen(trim($this->request->post['kpp'])) > 0 && (utf8_strlen(trim($this->request->post['kpp'])) != 9)) {
			$this->error['kpp'] = $this->language->get('error_kpp');
		}

		if ((utf8_strlen(trim($this->request->post['address'])) < 3) || (utf8_strlen(trim($this->request->post['address'])) > 255)) {
			$this->error['address'] = $this->language->get('error_address');
		}

		if ((utf8_strlen(trim($this->request->post['bik'])) < 9) || (utf8_strlen(trim($this->request->post['bik'])) > 9)) {
			$this->error['bik'] = $this->language->get('error_bik');
		}

		if ((utf8_strlen(trim($this->request->post['bank'])) < 1) || (utf8_strlen(trim($this->request->post['bank'])) > 255)) {
			$this->error['bank'] = $this->language->get('error_bank');
		}

		if ((utf8_strlen(trim($this->request->post['rs'])) < 20) || (utf8_strlen(trim($this->request->post['rs'])) > 34)) {
			$this->error['rs'] = $this->language->get('error_rs');
		}

		if (utf8_strlen(trim($this->request->post['ks'])) > 0 && (utf8_strlen(trim($this->request->post['ks'])) != 20)) {
			$this->error['ks'] = $this->language->get('error_ks');
		}

		return !$this->error;
	}

	protected function validateDelete() {
		return !$this->error;
	}
}
