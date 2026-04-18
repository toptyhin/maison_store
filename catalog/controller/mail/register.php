<?php
class ControllerMailRegister extends Controller {
	public function index(&$route, &$args, &$output) {
		$this->load->language('mail/register');

		$customer_id = (int)$output;
		$wholesale_gid = (int)$this->config->get('config_wholesale_customer_group_id');
		$is_wholesale = false;
		$customer_info = null;

		if ($customer_id > 0) {
			$this->load->model('account/customer');
			$customer_info = $this->model_account_customer->getCustomer($customer_id);

			if ($wholesale_gid > 0 && $customer_info && (int)$customer_info['customer_group_id'] === $wholesale_gid) {
				$is_wholesale = true;
			}
		}

		$data['wholesale'] = $is_wholesale;

		if ($is_wholesale) {
			$data['text_wholesale_welcome'] = $this->language->get('text_wholesale_welcome');
		} else {
			$data['text_wholesale_welcome'] = '';
		}

		$data['text_welcome'] = sprintf($this->language->get('text_welcome'), html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
		$data['text_login'] = $this->language->get('text_login');
		$data['text_approval'] = $this->language->get('text_approval');
		$data['text_service'] = $this->language->get('text_service');
		$data['text_thanks'] = $this->language->get('text_thanks');

		$this->load->model('account/customer_group');

		if (isset($args[0]['customer_group_id'])) {
			$customer_group_id = $args[0]['customer_group_id'];
		} else {
			$customer_group_id = $this->config->get('config_customer_group_id');
		}

		$customer_group_info = $this->model_account_customer_group->getCustomerGroup($customer_group_id);

		if ($customer_group_info) {
			$data['approval'] = $customer_group_info['approval'];
		} else {
			$data['approval'] = '';
		}

		if ($is_wholesale && $customer_info && !empty($customer_info['customer_group_id'])) {
			$saved_group = $this->model_account_customer_group->getCustomerGroup((int)$customer_info['customer_group_id']);

			if ($saved_group) {
				$data['approval'] = $saved_group['approval'];
			}
		}

		$data['login'] = $this->url->link('account/login', '', true);
		$data['store'] = html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8');

		$mail = new Mail($this->config->get('config_mail_engine'));
		$mail->parameter = $this->config->get('config_mail_parameter');
		$mail->smtp_hostname = $this->config->get('config_mail_smtp_hostname');
		$mail->smtp_username = $this->config->get('config_mail_smtp_username');
		$mail->smtp_password = html_entity_decode($this->config->get('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
		$mail->smtp_port = $this->config->get('config_mail_smtp_port');
		$mail->smtp_timeout = $this->config->get('config_mail_smtp_timeout');

		$mail->setTo($args[0]['email']);
		$mail->setFrom($this->config->get('config_email'));
		$mail->setSender(html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8'));
		if ($is_wholesale) {
			$mail->setSubject(sprintf($this->language->get('text_subject_wholesale'), html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8')));
		} else {
			$mail->setSubject(sprintf($this->language->get('text_subject'), html_entity_decode($this->config->get('config_name'), ENT_QUOTES, 'UTF-8')));
		}
		$mail->setText($this->load->view('mail/register', $data));
		$mail->send(); 
	}
	
	public function alert(&$route, &$args, &$output) {
		if (isset($args[0]['customer_group_id'])) {
			$customer_group_id = (int)$args[0]['customer_group_id'];
		} else {
			$customer_group_id = (int)$this->config->get('config_customer_group_id');
		}

		$wholesale_group_id = (int)$this->config->get('config_wholesale_customer_group_id');
		if ($wholesale_group_id < 1 || $customer_group_id !== $wholesale_group_id) {
			return;
		}

		$this->load->library('AdminNotifier');
		if (!$this->AdminNotifier->isAlertChannelEnabled('account')) {
			return;
		}

		$this->load->language('mail/register');

		$data['text_signup'] = $this->language->get('text_signup');
		$data['text_firstname'] = $this->language->get('text_firstname');
		$data['text_lastname'] = $this->language->get('text_lastname');
		$data['text_customer_group'] = $this->language->get('text_customer_group');
		$data['text_email'] = $this->language->get('text_email');
		$data['text_telephone'] = $this->language->get('text_telephone');

		$data['firstname'] = $args[0]['firstname'];
		$data['lastname'] = $args[0]['lastname'];

		$this->load->model('account/customer_group');

		$customer_group_info = $this->model_account_customer_group->getCustomerGroup($customer_group_id);

		if ($customer_group_info) {
			$data['customer_group'] = $customer_group_info['name'];
		} else {
			$data['customer_group'] = '';
		}

		$data['email'] = $args[0]['email'];
		$data['telephone'] = $args[0]['telephone'];

		$this->AdminNotifier->notifyAccountAlert($data);
	}
}
