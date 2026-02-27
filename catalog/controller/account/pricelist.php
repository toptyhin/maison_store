<?php
// *	@source		See SOURCE.txt for source and other copyright.
// *	@license	GNU General Public License version 3; see LICENSE.txt

class ControllerAccountPricelist extends Controller {

	public function index() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/pricelist', '', true);
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		if ($this->customer->getGroupId() <= 1) {
			$this->response->redirect($this->url->link('account/account', '', true));
		}

		$this->load->language('account/pricelist');
		$this->load->model('catalog/category');
		$this->load->model('account/pricelist');

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
			'href' => $this->url->link('account/pricelist', '', true)
		);

		$data['heading_title'] = $this->language->get('heading_title');
		$data['action'] = $this->url->link('account/pricelist', '', true);
		$data['download_action'] = $this->url->link('account/pricelist/download', '', true);
		$data['continue'] = $this->url->link('account/account', '', true);
		$data['button_continue'] = $this->language->get('button_continue');
		$data['button_download'] = $this->language->get('button_download');
		$data['text_categories'] = $this->language->get('text_categories');
		$data['text_select_categories'] = $this->language->get('text_select_categories');
		$data['button_download_csv'] = $this->language->get('button_download_csv');
		$data['button_download_xls'] = $this->language->get('button_download_xls');

		$data['categories'] = $this->getCategoryTree(0);

		if (isset($this->session->data['error'])) {
			$data['error'] = $this->session->data['error'];
			unset($this->session->data['error']);
		} else {
			$data['error'] = '';
		}

		$data['menu'] = $this->load->controller('account/menu');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/pricelist', $data));
	}

	public function download() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/pricelist', '', true);
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		if ($this->customer->getGroupId() <= 1) {
			$this->response->redirect($this->url->link('account/pricelist', '', true));
		}

		$category_ids = array();
		if (isset($this->request->post['category'])) {
			$category_ids = is_array($this->request->post['category']) ? $this->request->post['category'] : array();
		}
		if (isset($this->request->get['category'])) {
			$cat = $this->request->get['category'];
			$category_ids = is_array($cat) ? $cat : array($cat);
		}

		$this->load->language('account/pricelist');

		if (empty($category_ids)) {
			$this->session->data['error'] = $this->language->get('error_no_categories');
			$this->response->redirect($this->url->link('account/pricelist', '', true));
			return;
		}

		$this->load->model('account/pricelist');

		$customer_group_id = (int)$this->config->get('config_customer_group_id');
		$store_id = (int)$this->config->get('config_store_id');
		$language_id = (int)$this->config->get('config_language_id');

		$rows = $this->model_account_pricelist->getPricelistRows($category_ids, $customer_group_id, $store_id, $language_id);

		$format = isset($this->request->post['format']) ? $this->request->post['format'] : (isset($this->request->get['format']) ? $this->request->get['format'] : 'csv');
		if ($format !== 'csv' && $format !== 'xls') {
			$format = 'csv';
		}

		$filename = 'pricelist_' . date('Y-m-d_H-i') . '.' . $format;

		if ($format === 'csv') {
			$this->outputCsv($rows, $filename);
		} else {
			$this->outputXls($rows, $filename);
		}
	}

	protected function outputCsv($rows, $filename) {
		$sep = ',';
		$enc = '"';

		header('Content-Type: text/csv; charset=UTF-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');

		if (ob_get_level()) {
			ob_end_clean();
		}

		$output = fopen('php://output', 'w');
		if ($output === false) {
			$this->response->redirect($this->url->link('account/pricelist', '', true));
			return;
		}

		fwrite($output, "\xEF\xBB\xBF");
		$header = array('product_id', 'sku', 'option_code', 'option_name', 'name', 'price', 'quantity');
		fputcsv($output, $header, $sep, $enc);

		foreach ($rows as $row) {
			$line = array(
				$row['product_id'],
				$row['sku'],
				$row['option_code'],
				isset($row['option_name']) ? $row['option_name'] : '',
				$row['name'],
				$row['price'],
				$row['quantity']
			);
			fputcsv($output, $line, $sep, $enc);
		}
		fclose($output);
		exit;
	}

	protected function outputXls($rows, $filename) {
		$sep = "\t";
		$enc = '"';

		header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Expires: 0');
		header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
		header('Pragma: public');

		if (ob_get_level()) {
			ob_end_clean();
		}

		$output = fopen('php://output', 'w');
		if ($output === false) {
			$this->response->redirect($this->url->link('account/pricelist', '', true));
			return;
		}

		fwrite($output, "\xEF\xBB\xBF");
		$header = array('product_id', 'sku', 'option_code', 'option_name', 'name', 'price', 'quantity');
		fputcsv($output, $header, $sep, $enc);

		foreach ($rows as $row) {
			$line = array(
				$row['product_id'],
				$row['sku'],
				$row['option_code'],
				isset($row['option_name']) ? $row['option_name'] : '',
				$row['name'],
				$row['price'],
				$row['quantity']
			);
			fputcsv($output, $line, $sep, $enc);
		}
		fclose($output);
		exit;
	}

	protected function getCategoryTree($parent_id, $level = 0) {
		$categories = array();
		$results = $this->model_catalog_category->getCategories($parent_id);
		foreach ($results as $result) {
			$categories[] = array(
				'category_id' => $result['category_id'],
				'name'        => str_repeat('&nbsp;&nbsp;', $level) . $result['name'],
				'level'       => $level,
				'children'    => $this->getCategoryTree($result['category_id'], $level + 1)
			);
		}
		return $categories;
	}
}
