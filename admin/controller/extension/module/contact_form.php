<?php
class ControllerExtensionModuleContactForm extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/contact_form');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/module');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			if (!isset($this->request->get['module_id'])) {
				$this->model_setting_module->addModule('contact_form', $this->request->post);
			} else {
				$this->model_setting_module->editModule($this->request->get['module_id'], $this->request->post);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		if (!isset($this->request->get['module_id'])) {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/contact_form', 'user_token=' . $this->session->data['user_token'], true)
			);
		} else {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/contact_form', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true)
			);
		}

		if (!isset($this->request->get['module_id'])) {
			$data['action'] = $this->url->link('extension/module/contact_form', 'user_token=' . $this->session->data['user_token'], true);
		} else {
			$data['action'] = $this->url->link('extension/module/contact_form', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true);
		}

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->get['module_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		if (isset($this->request->post['name'])) {
			$data['name'] = $this->request->post['name'];
		} elseif (!empty($module_info)) {
			$data['name'] = $module_info['name'];
		} else {
			$data['name'] = '';
		}

		if (isset($this->request->post['heading_title'])) {
			$data['heading_title'] = $this->request->post['heading_title'];
		} elseif (!empty($module_info)) {
			$data['heading_title'] = $module_info['heading_title'];
		} else {
			$data['heading_title'] = '';
		}

		if (isset($this->request->post['description'])) {
			$data['description'] = $this->request->post['description'];
		} elseif (!empty($module_info)) {
			$data['description'] = $module_info['description'];
		} else {
			$data['description'] = '';
		}

		if (isset($this->request->post['button_text'])) {
			$data['button_text'] = $this->request->post['button_text'];
		} elseif (!empty($module_info)) {
			$data['button_text'] = $module_info['button_text'];
		} else {
			$data['button_text'] = '';
		}

		if (isset($this->request->post['name_placeholder'])) {
			$data['name_placeholder'] = $this->request->post['name_placeholder'];
		} elseif (!empty($module_info)) {
			$data['name_placeholder'] = isset($module_info['name_placeholder']) ? $module_info['name_placeholder'] : '';
		} else {
			$data['name_placeholder'] = '';
		}

		if (isset($this->request->post['email_placeholder'])) {
			$data['email_placeholder'] = $this->request->post['email_placeholder'];
		} elseif (!empty($module_info)) {
			$data['email_placeholder'] = isset($module_info['email_placeholder']) ? $module_info['email_placeholder'] : '';
		} else {
			$data['email_placeholder'] = '';
		}

		if (isset($this->request->post['phone_placeholder'])) {
			$data['phone_placeholder'] = $this->request->post['phone_placeholder'];
		} elseif (!empty($module_info)) {
			$data['phone_placeholder'] = isset($module_info['phone_placeholder']) ? $module_info['phone_placeholder'] : '';
		} else {
			$data['phone_placeholder'] = '';
		}

		if (isset($this->request->post['message_placeholder'])) {
			$data['message_placeholder'] = $this->request->post['message_placeholder'];
		} elseif (!empty($module_info)) {
			$data['message_placeholder'] = isset($module_info['message_placeholder']) ? $module_info['message_placeholder'] : '';
		} else {
			$data['message_placeholder'] = '';
		}

		if (isset($this->request->post['show_phone'])) {
			$data['show_phone'] = $this->request->post['show_phone'];
		} elseif (!empty($module_info)) {
			$data['show_phone'] = isset($module_info['show_phone']) ? $module_info['show_phone'] : 0;
		} else {
			$data['show_phone'] = 0;
		}

		if (isset($this->request->post['show_name'])) {
			$data['show_name'] = $this->request->post['show_name'];
		} elseif (!empty($module_info)) {
			$data['show_name'] = isset($module_info['show_name']) ? $module_info['show_name'] : 1;
		} else {
			$data['show_name'] = 1;
		}

		if (isset($this->request->post['show_message'])) {
			$data['show_message'] = $this->request->post['show_message'];
		} elseif (!empty($module_info)) {
			$data['show_message'] = isset($module_info['show_message']) ? $module_info['show_message'] : 1;
		} else {
			$data['show_message'] = 1;
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($module_info)) {
			$data['status'] = $module_info['status'];
		} else {
			$data['status'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/contact_form', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/contact_form')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 64)) {
			$this->error['name'] = $this->language->get('error_name');
		}

		return !$this->error;
	}
}
