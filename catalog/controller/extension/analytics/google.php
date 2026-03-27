<?php
class ControllerExtensionAnalyticsGoogle extends Controller {
	public function index() {
		$this->load->helper('cookie_consent');

		if (!cookie_consent_analytics_allowed($this->request, $this->customer)) {
			return '';
		}

		return html_entity_decode($this->config->get('analytics_google_code'), ENT_QUOTES, 'UTF-8');
	}
}
