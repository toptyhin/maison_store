<?php

/**
 * Central admin alerts: e-mail (existing behaviour) + optional Telegram.
 */
class AdminNotifier {
	private $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	private function config($key) {
		return $this->registry->get('config')->get($key);
	}

	/**
	 * Whether any channel wants this alert type (order, account, affiliate, review).
	 */
	public function isAlertChannelEnabled($event) {
		return $this->wantsMail($event) || $this->wantsTelegram($event);
	}

	public function wantsMail($event) {
		return in_array($event, (array)$this->config('config_mail_alert'), true);
	}

	public function wantsTelegram($event) {
		if (!in_array($event, (array)$this->config('config_telegram_alert'), true)) {
			return false;
		}

		$token = trim((string)$this->config('config_telegram_bot_token'));
		$chats = trim((string)$this->config('config_telegram_chat_ids'));

		return $token !== '' && $chats !== '';
	}

	private function createMail() {
		$mail = new Mail($this->config('config_mail_engine'));
		$mail->parameter = $this->config('config_mail_parameter');
		$mail->smtp_hostname = $this->config('config_mail_smtp_hostname');
		$mail->smtp_username = $this->config('config_mail_smtp_username');
		$mail->smtp_password = html_entity_decode($this->config('config_mail_smtp_password'), ENT_QUOTES, 'UTF-8');
		$mail->smtp_port = $this->config('config_mail_smtp_port');
		$mail->smtp_timeout = $this->config('config_mail_smtp_timeout');

		return $mail;
	}

	private function sendMailToAlertRecipients($mail) {
		$emails = explode(',', (string)$this->config('config_mail_alert_email'));

		foreach ($emails as $email) {
			$email = trim($email);
			if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
				$mail->setTo($email);
				$mail->send();
			}
		}
	}

	private function sendTelegramMessage($text) {
		$text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
		if (utf8_strlen($text) > 4090) {
			$text = utf8_substr($text, 0, 4087) . '...';
		}

		$token = trim((string)$this->config('config_telegram_bot_token'));
		$raw_chats = trim((string)$this->config('config_telegram_chat_ids'));
		if ($token === '' || $raw_chats === '') {
			return;
		}

		$chat_ids = preg_split('/[\s,]+/', $raw_chats, -1, PREG_SPLIT_NO_EMPTY);
		if (!$chat_ids) {
			return;
		}

		$url = 'https://api.telegram.org/bot' . $token . '/sendMessage';

		try {
			foreach ($chat_ids as $chat_id) {
				$payload = array(
					'chat_id' => $chat_id,
					'text'    => $text,
				);

				$ch = curl_init($url);
				if ($ch === false) {
					continue;
				}
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
				curl_setopt($ch, CURLOPT_TIMEOUT, 10);
				curl_exec($ch);
				curl_close($ch);
			}
		} catch (\Exception $e) {
			return;
		}
	}

	/**
	 * @param array $data Keys for mail/order_alert template (labels + values).
	 */
	public function notifyOrderAlert(array $data) {
		$load = $this->registry->get('load');

		if ($this->wantsMail('order')) {
			$load->language('mail/order_alert');
			$language = $this->registry->get('language');

			$sender = isset($data['store_name']) ? html_entity_decode($data['store_name'], ENT_QUOTES, 'UTF-8') : html_entity_decode($this->config('config_name'), ENT_QUOTES, 'UTF-8');

			$mail = $this->createMail();
			$mail->setTo($this->config('config_email'));
			$mail->setFrom($this->config('config_email'));
			$mail->setSender($sender);
			$mail->setSubject(html_entity_decode(sprintf($language->get('text_subject'), $this->config('config_name'), $data['order_id']), ENT_QUOTES, 'UTF-8'));
			$mail->setText($load->view('mail/order_alert', $data));
			$mail->send();
			$this->sendMailToAlertRecipients($mail);
		}

		if ($this->wantsTelegram('order')) {
			$load->language('mail/order_alert');
			$this->sendTelegramMessage($this->formatOrderTelegram($data));
		}
	}

	private function formatOrderTelegram(array $data) {
		$lines = array();
		$lines[] = isset($data['store_name']) ? html_entity_decode($data['store_name'], ENT_QUOTES, 'UTF-8') : $this->config('config_name');
		$lines[] = $data['text_received'];
		$lines[] = '';
		$lines[] = $data['text_order_id'] . ' ' . $data['order_id'];
		$lines[] = $data['text_date_added'] . ' ' . $data['date_added'];
		$lines[] = $data['text_order_status'] . ' ' . $data['order_status'];
		$lines[] = '';
		$lines[] = $data['text_product'];

		if (!empty($data['products']) && is_array($data['products'])) {
			foreach ($data['products'] as $product) {
				$line = '• ' . $product['name'] . ' ×' . (int)$product['quantity'];
				if (!empty($product['model'])) {
					$line .= ' (' . $product['model'] . ')';
				}
				$lines[] = $line;
			}
		}

		if (!empty($data['totals']) && is_array($data['totals'])) {
			$lines[] = '';
			$lines[] = $data['text_total'];
			foreach ($data['totals'] as $total) {
				$lines[] = $total['title'] . ' ' . $total['value'];
			}
		}

		if (!empty($data['comment'])) {
			$lines[] = '';
			$lines[] = $data['text_comment'];
			$lines[] = strip_tags((string)$data['comment']);
		}

		return implode("\n", $lines);
	}

	/**
	 * @param array $data Keys for mail/register_alert template.
	 */
	public function notifyAccountAlert(array $data) {
		$load = $this->registry->get('load');

		if ($this->wantsMail('account')) {
			$load->language('mail/register');
			$language = $this->registry->get('language');

			$mail = $this->createMail();
			$mail->setTo($this->config('config_email'));
			$mail->setFrom($this->config('config_email'));
			$mail->setSender(html_entity_decode($this->config('config_name'), ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode($language->get('text_new_customer'), ENT_QUOTES, 'UTF-8'));
			$mail->setText($load->view('mail/register_alert', $data));
			$mail->send();
			$this->sendMailToAlertRecipients($mail);
		}

		if ($this->wantsTelegram('account')) {
			$load->language('mail/register');
			$this->sendTelegramMessage($this->formatRegisterTelegram($data));
		}
	}

	private function formatRegisterTelegram(array $data) {
		$lines = array();
		$lines[] = $this->config('config_name');
		$lines[] = $data['text_signup'];
		$lines[] = '';
		$lines[] = $data['text_firstname'] . ' ' . $data['firstname'];
		$lines[] = $data['text_lastname'] . ' ' . $data['lastname'];
		$lines[] = $data['text_customer_group'] . ' ' . $data['customer_group'];
		$lines[] = $data['text_email'] . ' ' . $data['email'];
		$lines[] = $data['text_telephone'] . ' ' . $data['telephone'];

		return implode("\n", $lines);
	}

	/**
	 * @param array $data Keys for mail/affiliate_alert template.
	 */
	public function notifyAffiliateAlert(array $data) {
		$load = $this->registry->get('load');

		if ($this->wantsMail('affiliate')) {
			$load->language('mail/affiliate');
			$language = $this->registry->get('language');

			$mail = $this->createMail();
			$mail->setTo($this->config('config_email'));
			$mail->setFrom($this->config('config_email'));
			$mail->setSender(html_entity_decode($this->config('config_name'), ENT_QUOTES, 'UTF-8'));
			$mail->setSubject(html_entity_decode($language->get('text_new_affiliate'), ENT_QUOTES, 'UTF-8'));
			$mail->setText($load->view('mail/affiliate_alert', $data));
			$mail->send();
			$this->sendMailToAlertRecipients($mail);
		}

		if ($this->wantsTelegram('affiliate')) {
			$load->language('mail/affiliate');
			$this->sendTelegramMessage($this->formatAffiliateTelegram($data));
		}
	}

	private function formatAffiliateTelegram(array $data) {
		$lines = array();
		$lines[] = $this->config('config_name');
		$lines[] = $data['text_signup'];
		$lines[] = '';
		$lines[] = $data['text_website'] . ' ' . $data['website'];
		$lines[] = $data['text_firstname'] . ' ' . $data['firstname'];
		$lines[] = $data['text_lastname'] . ' ' . $data['lastname'];
		$lines[] = $data['text_customer_group'] . ' ' . $data['customer_group'];
		$lines[] = $data['text_email'] . ' ' . $data['email'];
		$lines[] = $data['text_telephone'] . ' ' . $data['telephone'];

		return implode("\n", $lines);
	}

	public function notifyProductReviewAlert($subject, $message) {
		$load = $this->registry->get('load');

		if ($this->wantsMail('review')) {
			$mail = $this->createMail();
			$mail->setTo($this->config('config_email'));
			$mail->setFrom($this->config('config_email'));
			$mail->setSender(html_entity_decode($this->config('config_name'), ENT_QUOTES, 'UTF-8'));
			$mail->setSubject($subject);
			$mail->setText($message);
			$mail->send();
			$this->sendMailToAlertRecipients($mail);
		}

		if ($this->wantsTelegram('review')) {
			$plain = strip_tags(html_entity_decode($subject, ENT_QUOTES, 'UTF-8')) . "\n\n" . strip_tags(html_entity_decode($message, ENT_QUOTES, 'UTF-8'));
			$this->sendTelegramMessage($plain);
		}
	}
}
