<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerAccountManager extends Controller {

	public function index() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/manager', '', true);
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		if ($this->customer->getGroupId() <= 1) {
			$this->response->redirect($this->url->link('account/account', '', true));
		}

		$this->load->language('account/manager');

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
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('account/manager', '', true)
		);

		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_subtitle'] = $this->language->get('text_subtitle');
		$data['text_online'] = $this->language->get('text_online');
		$data['text_telegram_hint'] = $this->language->get('text_telegram_hint');
		$data['text_whatsapp_hint'] = $this->language->get('text_whatsapp_hint');
		$data['text_phone'] = $this->language->get('text_phone');
		$data['text_requests_history'] = $this->language->get('text_requests_history');
		$data['text_all_requests'] = $this->language->get('text_all_requests');
		$data['text_no_requests'] = $this->language->get('text_no_requests');
		$data['button_continue'] = $this->language->get('button_continue');
		$data['continue'] = $this->url->link('account/account', '', true);

		// Заглушка: данные менеджера (в дальнейшем — из БД/настроек)
		$data['manager_name'] = 'Анна Петрова';
		$data['manager_avatar'] = 'https://lh3.googleusercontent.com/aida-public/AB6AXuAgFhkjGKf5kBBBvjSvc-9NvBNnQlvQizLdd02_L1KmG55MgvJn0GP5i7_fdwFWwI3QK5zO6h3HAkq7p8wjA7boLHBGkiopbAcGM_Y2Ic2Pb6LLsfgrPI49NSLL6siT9YFLD3rsm5Dzda0rSHMWFLh94TKD_VLdiupet8QF1sag2Cp5AlpLrcDrxLf1f7n6WPQ26blv7OSx_HMEa-9MJhMTN_GxGi8UllY1cLg1N4ZywRhqnRtJ3HbM7R-WUXALto83aoNU9CLVs3Q';
		$data['manager_position'] = 'Старший менеджер по работе с партнёрами';
		$data['manager_experience'] = 'Опыт работы: 5 лет • Работает с вами с 2022 года';
		$data['manager_telegram'] = '#';
		$data['manager_whatsapp'] = '#';
		$data['manager_phone'] = '8 (800) 555-35-35';
		$data['manager_email'] = 'anna.p@maisontextile.ru';

		// Заглушка: история обращений (пустой массив — в дальнейшем из модели)
		$data['requests'] = array();

		$data['menu'] = $this->load->controller('account/menu');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/manager', $data));
	}
}
