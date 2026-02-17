<?php
class ControllerExtensionModuleContactForm extends Controller {
	public function index($setting) {
		// Проверяем статус модуля
		if (!isset($setting['status']) || !$setting['status']) {
			return '';
		}

		$this->load->language('extension/module/contact_form');

		$data['heading_title'] = isset($setting['heading_title']) ? $setting['heading_title'] : $this->language->get('heading_title');
		$data['description'] = isset($setting['description']) ? $setting['description'] : $this->language->get('text_description');
		$data['button_text'] = isset($setting['button_text']) ? $setting['button_text'] : $this->language->get('button_submit');
		$data['name_placeholder'] = isset($setting['name_placeholder']) ? $setting['name_placeholder'] : $this->language->get('entry_name');
		$data['email_placeholder'] = isset($setting['email_placeholder']) ? $setting['email_placeholder'] : $this->language->get('entry_email');
		$data['message_placeholder'] = isset($setting['message_placeholder']) ? $setting['message_placeholder'] : $this->language->get('entry_message');
		$data['phone_placeholder'] = isset($setting['phone_placeholder']) ? $setting['phone_placeholder'] : $this->language->get('entry_phone');
		
		$data['show_name'] = isset($setting['show_name']) ? (bool)$setting['show_name'] : true;
		$data['show_phone'] = isset($setting['show_phone']) ? (bool)$setting['show_phone'] : false;
		$data['show_message'] = isset($setting['show_message']) ? (bool)$setting['show_message'] : true;
		
		$data['action'] = $this->url->link('extension/module/contact_form/send', '', true);

		// Captcha
		if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('contact', (array)$this->config->get('config_captcha_page'))) {
			$data['captcha'] = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha'));
		} else {
			$data['captcha'] = '';
		}

		return $this->load->view('extension/module/contact_form', $data);
	}

	public function send() {
		$this->load->language('extension/module/contact_form');

		$json = array();

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			// Получаем настройки модуля для проверки, какие поля обязательны
			$this->load->model('setting/module');
			$contact_form_modules = $this->model_setting_module->getModulesByCode('contact_form');
			$show_name = true;
			foreach ($contact_form_modules as $module) {
				if ($module['setting']) {
					$setting_info = json_decode($module['setting'], true);
					if (isset($setting_info['status']) && $setting_info['status']) {
						$show_name = isset($setting_info['show_name']) ? (bool)$setting_info['show_name'] : true;
						break;
					}
				}
			}
			
			// Валидация
			if ($show_name) {
				if (isset($this->request->post['name']) && ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 32))) {
					$json['error']['name'] = $this->language->get('error_name');
				}
			}

			if (!filter_var($this->request->post['email'], FILTER_VALIDATE_EMAIL)) {
				$json['error']['email'] = $this->language->get('error_email');
			}

			if (isset($this->request->post['phone']) && (utf8_strlen($this->request->post['phone']) > 32)) {
				$json['error']['phone'] = $this->language->get('error_phone');
			}

			if (isset($this->request->post['message']) && ((utf8_strlen($this->request->post['message']) < 10) || (utf8_strlen($this->request->post['message']) > 3000))) {
				$json['error']['message'] = $this->language->get('error_message');
			}

			// Captcha
			if ($this->config->get('captcha_' . $this->config->get('config_captcha') . '_status') && in_array('contact', (array)$this->config->get('config_captcha_page'))) {
				$captcha = $this->load->controller('extension/captcha/' . $this->config->get('config_captcha') . '/validate');

				if ($captcha) {
					$json['error']['captcha'] = $captcha;
				}
			}

			if (!$json) {
				$mail = new Mail($this->config->get('config_mail_engine'));
				$mail->parameter = $this->config->get('config_mail_parameter');
				$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
				$mail->smtp_username = $this->config->get('config_mail_smtp_username');
				$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
				$mail->smtp_port = $this->config->get('config_mail_smtp_port');
				$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

				$mail->setTo($this->config->get('config_email'));
				$mail->setFrom($this->config->get('config_email'));
				$mail->setReplyTo($this->request->post['email']);
				
				$sender_name = isset($this->request->post['name']) && $this->request->post['name'] ? html_entity_decode($this->request->post['name'], ENT_QUOTES, 'UTF-8') : $this->request->post['email'];
				$mail->setSender($sender_name);
				
				$subject_name = isset($this->request->post['name']) && $this->request->post['name'] ? $this->request->post['name'] : 'Пользователь';
				$subject = sprintf($this->language->get('email_subject'), $subject_name . ' (' . $this->request->post['email'] . ')');
				$mail->setSubject(html_entity_decode($subject, ENT_QUOTES, 'UTF-8'));
				
				$message_text = '';
				if ($show_name && isset($this->request->post['name']) && $this->request->post['name']) {
					$message_text .= $this->language->get('entry_name') . ': ' . $this->request->post['name'] . "\n";
				}
				if (isset($this->request->post['phone']) && $this->request->post['phone']) {
					$message_text .= $this->language->get('text_phone') . ': ' . $this->request->post['phone'] . "\n";
				}
				if ($message_text) {
					$message_text .= "\n";
				}
				if (isset($this->request->post['message']) && $this->request->post['message']) {
					$message_text .= $this->language->get('text_message') . ":\n" . $this->request->post['message'];
				} else {
					$message_text .= $this->language->get('text_contact_form_submission');
				}
				
				$mail->setText($message_text);
				$mail->send();

				$json['success'] = $this->language->get('text_success');
			}
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}
}
