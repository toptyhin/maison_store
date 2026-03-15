<?php
class ControllerStartupWishlist extends Controller {
	public function index() {
		if ($this->customer->isLogged()) {
			$this->load->model('account/wishlist');
			$rows = $this->model_account_wishlist->getWishlist();
			$ids = array_map('intval', array_column($rows, 'product_id'));
		} else {
			$ids = isset($this->session->data['wishlist']) && is_array($this->session->data['wishlist'])
				? array_map('intval', $this->session->data['wishlist'])
				: array();
		}
		$this->registry->set('wishlist_product_ids', $ids);
	}
}
