<?php
class ControllerCommonCallback extends Controller {

	public function index() {
		$this->load->language('common/callback');

		$data['action'] = $this->url->link('common/callback/send', '', true);
		$data['base'] = $this->config->get('config_url');
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_description'] = $this->language->get('text_description');
		$data['entry_name'] = $this->language->get('entry_name');
		$data['entry_phone'] = $this->language->get('entry_phone');
		$data['entry_comment'] = $this->language->get('entry_comment');
		$data['button_submit'] = $this->language->get('button_submit');

		// Ссылка на условия обработки персональных данных
		$data['show_agree'] = (bool)$this->config->get('config_account_id');
		if ($data['show_agree']) {
			$this->load->model('catalog/information');
			$information_info = $this->model_catalog_information->getInformation($this->config->get('config_account_id'));
			if ($information_info) {
				$data['privacy_href'] = $this->url->link('information/information', 'information_id=' . $this->config->get('config_account_id'));
				$data['privacy_title'] = $information_info['title'];
			} else {
				$data['privacy_href'] = '#';
				$data['privacy_title'] = $this->language->get('text_privacy');
			}
		} else {
			$data['privacy_href'] = '#';
			$data['privacy_title'] = $this->language->get('text_privacy');
		}


		$this->response->setOutput($this->load->view('common/callback', $data));
	}

	public function send() {
		$this->load->language('common/callback');
		$json = array();

		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			// Валидация
			if (empty($this->request->post['name']) || (utf8_strlen($this->request->post['name']) < 2) || (utf8_strlen($this->request->post['name']) > 32)) {
				$json['error']['name'] = $this->language->get('error_name');
			}

			$phone = preg_replace('/[^0-9+]/', '', $this->request->post['phone'] ?? '');
			if (strlen($phone) < 10) {
				$json['error']['phone'] = $this->language->get('error_phone');
			}

			if ($this->config->get('config_account_id') && empty($this->request->post['agree'])) {
				$json['error']['agree'] = $this->language->get('error_agree');
			}

			if (empty($json['error'])) {
				$mail = new Mail($this->config->get('config_mail_engine'));
				$mail->parameter = $this->config->get('config_mail_parameter');
				$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
				$mail->smtp_username = $this->config->get('config_mail_smtp_username');
				$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
				$mail->smtp_port = $this->config->get('config_mail_smtp_port');
				$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

				$mail->setTo($this->config->get('config_email'));
				$mail->setFrom($this->config->get('config_email'));
				$mail->setReplyTo($this->config->get('config_email'));
				$mail->setSender(html_entity_decode($this->request->post['name'], ENT_QUOTES, 'UTF-8'));
				$mail->setSubject(html_entity_decode($this->language->get('email_subject'), ENT_QUOTES, 'UTF-8'));

				$message = $this->language->get('entry_name') . ': ' . $this->request->post['name'] . "\n";
				$message .= $this->language->get('entry_phone') . ': ' . ($this->request->post['phone'] ?? '') . "\n";
				if (!empty($this->request->post['comment'])) {
					$message .= $this->language->get('entry_comment') . ": " . $this->request->post['comment'] . "\n";
				}
				$mail->setText($message);
				$mail->send();

				$json['success'] = $this->language->get('text_success');
			}
		}

		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->setOutput(json_encode($json));
	}
}
