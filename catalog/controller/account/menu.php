<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerAccountMenu extends Controller {
	public function index() {
		$route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : 'common/home';

		$menu_active = 'account';
		if ($route == 'account/order' || $route == 'account/order/info') {
			$menu_active = 'order';
		} elseif ($route == 'account/wishlist') {
			$menu_active = 'wishlist';
		} elseif ($route == 'account/reward') {
			$menu_active = 'reward';
		} elseif (in_array($route, array('account/address', 'account/address/add', 'account/address/edit', 'account/address/delete'))) {
			$menu_active = 'address';
		} elseif (in_array($route, array('account/edit', 'account/password', 'account/newsletter'))) {
			$menu_active = 'account';
		}

		$data['menu_active'] = $menu_active;
		$data['account'] = $this->url->link('account/account', '', true);
		$data['address'] = $this->url->link('account/address', '', true);
		$data['wishlist'] = $this->url->link('account/wishlist');
		$data['order'] = $this->url->link('account/order', '', true);
		$data['logout'] = $this->url->link('account/logout', '', true);

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

		if ($this->config->get('total_reward_status')) {
			$data['reward'] = $this->url->link('account/reward', '', true);
		} else {
			$data['reward'] = '';
		}

		$this->load->language('extension/module/account');
		$data['text_order'] = $this->language->get('text_order');
		$data['text_wishlist'] = $this->language->get('text_wishlist');
		$data['text_reward'] = $this->language->get('text_reward');
		$data['text_edit'] = $this->language->get('text_edit');
		$data['text_address'] = $this->language->get('text_address');
		$data['text_logout'] = $this->language->get('text_logout');

		return $this->load->view('account/menu', $data);
	}
}
