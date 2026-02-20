<?php
/**
 * Product Filter Module - Admin controller
 */
class ControllerExtensionModuleProductFilter extends Controller {
	private $error = array();

	public function install() {
		$this->load->model('setting/event');
		$this->model_setting_event->addEvent('product_filter', 'model/catalog/product/getProducts/before', 'event/product_filter/injectFilterParams', 1, 0);
		$this->model_setting_event->addEvent('product_filter', 'model/catalog/product/getTotalProducts/before', 'event/product_filter/injectFilterParams', 1, 0);
		$this->model_setting_event->addEvent('product_filter', 'catalog/view/product/filter/before', 'event/product_filter/addFilterData', 1, 0);
		$this->model_setting_event->addEvent('product_filter', 'catalog/view/product/category/before', 'event/product_filter/categoryViewBefore', 1, 0);
		$this->model_setting_event->addEvent('product_filter', 'admin/view/catalog/category_form/after', 'event/product_filter/categoryFormAfter', 1, 0);
		$this->model_setting_event->addEvent('product_filter', 'admin/model/catalog/category/addCategory/after', 'event/product_filter/addCategoryAfter', 1, 0);
		$this->model_setting_event->addEvent('product_filter', 'admin/model/catalog/category/editCategory/after', 'event/product_filter/editCategoryAfter', 1, 0);

		// Create tables if not exist
		$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "category_filter_config'");
		if (!$query->num_rows) {
			$this->db->query("CREATE TABLE `" . DB_PREFIX . "category_filter_config` (
				`category_id` int(11) NOT NULL,
				`criterion_type` varchar(32) NOT NULL,
				`source_id` int(11) NOT NULL DEFAULT 0,
				`widget_type` varchar(32) NOT NULL DEFAULT 'checkboxes',
				`sort_order` int(3) NOT NULL DEFAULT 0,
				PRIMARY KEY (`category_id`, `criterion_type`, `source_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
		}
		$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "filter_seo_url'");
		if (!$query->num_rows) {
			$this->db->query("CREATE TABLE `" . DB_PREFIX . "filter_seo_url` (
				`filter_seo_id` int(11) NOT NULL AUTO_INCREMENT,
				`store_id` int(11) NOT NULL DEFAULT 0,
				`language_id` int(11) NOT NULL,
				`category_id` int(11) NOT NULL,
				`filter_hash` varchar(32) NOT NULL DEFAULT '',
				`keyword` varchar(255) NOT NULL,
				`filter_data` text NOT NULL,
				PRIMARY KEY (`filter_seo_id`),
				KEY `keyword_store_language` (`keyword`, `store_id`, `language_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
		}
	}

	public function uninstall() {
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('product_filter');
	}

	public function index() {
		$this->load->model('setting/event');
		// Исправление триггеров для catalog (substr в startup/event отрезает первый сегмент)
		// $this->db->query("UPDATE `" . DB_PREFIX . "event` SET `trigger` = 'catalog/view/product/filter/before' WHERE `code` = 'product_filter' AND `trigger` = 'view/product/filter/before'");
		// $this->db->query("UPDATE `" . DB_PREFIX . "event` SET `trigger` = 'catalog/view/product/category/before' WHERE `code` = 'product_filter' AND `trigger` = 'view/product/category/before'");

		$this->load->language('extension/module/product_filter');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/module');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			if (!isset($this->request->get['module_id'])) {
				$this->model_setting_module->addModule('product_filter', $this->request->post);
			} else {
				$this->model_setting_module->editModule($this->request->get['module_id'], $this->request->post);
			}
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_home'), 'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('text_extension'), 'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		$data['breadcrumbs'][] = array('text' => $this->language->get('heading_title'), 'href' => $this->url->link('extension/module/product_filter', 'user_token=' . $this->session->data['user_token'], true));

		$data['action'] = $this->url->link('extension/module/product_filter', 'user_token=' . $this->session->data['user_token'] . (isset($this->request->get['module_id']) ? '&module_id=' . $this->request->get['module_id'] : ''), true);
		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		$module_info = array();
		if (isset($this->request->get['module_id']) && $this->request->server['REQUEST_METHOD'] != 'POST') {
			$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		if (isset($this->request->post['name'])) {
			$data['name'] = $this->request->post['name'];
		} elseif (!empty($module_info['name'])) {
			$data['name'] = $module_info['name'];
		} else {
			$data['name'] = $this->language->get('heading_title');
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($module_info['status'])) {
			$data['status'] = $module_info['status'];
		} else {
			$data['status'] = 1;
		}

		$data['category_filter_url'] = $this->url->link('catalog/category', 'user_token=' . $this->session->data['user_token'], true);
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['button_save'] = $this->language->get('button_save');
		$data['button_cancel'] = $this->language->get('button_cancel');
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/product_filter', $data));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/product_filter')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		return !$this->error;
	}
}
