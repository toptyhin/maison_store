<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerAccountMenu extends Controller {
	public function index() {

		if (!$this->customer->isLogged()) {
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$enhanced_account = $this->customer->getGroupId() > 1;

		$route = isset($this->request->get['route']) ? (string)$this->request->get['route'] : 'common/home';

		$menu_active = 'account';
		switch ($route) {
			case 'account/order':
			case 'account/order/info':
				$menu_active = 'order';
				break;
			case 'account/wishlist':
				$menu_active = 'wishlist';
				break;
			case 'account/reward':
				$menu_active = 'reward';
				break;
			case 'account/address':
			case 'account/address/add':
			case 'account/address/edit':
			case 'account/address/delete':
				$menu_active = 'address';
				break;
			case 'account/documents':
				$menu_active = 'documents';
				break;
			case 'account/pricelist':
				$menu_active = 'pricelist';
				break;
			case 'account/manager':
				$menu_active = 'manager';
				break;
			case 'account/cart':
				$menu_active = 'cart';
				break;
			case 'account/company':
				$menu_active = 'company';
				break;				
			case 'account/edit':
			case 'account/password':
			case 'account/newsletter':
				$menu_active = 'account';
				break;
		}

		$data['menu_active'] = $menu_active;
		$data['enhanced_account'] = $enhanced_account;
		$data['account'] = $this->url->link('account/account', '', true);
		$data['address'] = $this->url->link('account/address', '', true);
		$data['wishlist'] = $this->url->link('account/wishlist');
		$data['order'] = $this->url->link('account/order', '', true);
		$data['logout'] = $this->url->link('account/logout', '', true);
		$data['company'] = $this->url->link('account/company', '', true);

		
		if ($enhanced_account) {
			$data['documents'] = $this->url->link('account/documents', '', true);
			$data['pricelist'] = $this->url->link('account/pricelist', '', true);
			$data['manager'] = $this->url->link('account/manager', '', true);
			$data['cart'] = $this->url->link('account/cart', '', true);
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
		$data['text_my_account'] = $this->language->get('text_my_account');
		$data['text_address'] = $this->language->get('text_address');
		$data['text_logout'] = $this->language->get('text_logout');

		return $this->load->view('account/menu', $data);
	}
}
