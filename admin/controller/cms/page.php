<?php
class ControllerCmsPage extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('cms/page');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('cms/page');

		$data['breadcrumbs'] = $this->getBreadcrumbs();
		$data['add'] = $this->url->link('cms/page/add', 'user_token=' . $this->session->data['user_token'], true);
		$data['pages'] = array();

		foreach ($this->model_cms_page->getPages() as $page) {
			$data['pages'][] = array(
				'page_id' => $page['page_id'],
				'name' => $page['name'],
				'label' => $this->model_cms_page->getPageRouteLabel($page),
				'status' => $page['status'],
				'date_modified' => $page['date_modified'],
				'edit' => $this->url->link('cms/page/edit', 'user_token=' . $this->session->data['user_token'] . '&page_id=' . $page['page_id'], true),
			);
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['heading_title'] = $this->language->get('heading_title');
		$data['text_list'] = $this->language->get('text_list');
		$data['column_name'] = $this->language->get('column_name');
		$data['column_route'] = $this->language->get('column_route');
		$data['column_status'] = $this->language->get('column_status');
		$data['column_action'] = $this->language->get('column_action');
		$data['button_add'] = $this->language->get('button_add');
		$data['button_edit'] = $this->language->get('button_edit');
		$data['button_delete_page'] = $this->language->get('button_delete_page');
		$data['text_confirm_delete_page'] = $this->language->get('text_confirm_delete_page');
		$data['text_enabled'] = $this->language->get('text_enabled');
		$data['text_disabled'] = $this->language->get('text_disabled');
		$data['text_no_pages'] = $this->language->get('text_no_pages');
		$data['user_token'] = $this->session->data['user_token'];

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('cms/page_list', $data));
	}

	public function add() {
		$this->load->language('cms/page');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('cms/page');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {
			if (isset($this->request->post['route']) && $this->request->post['route'] === 'common/home') {
				$this->request->post['entity_id'] = 0;
			}

			$store_id = isset($this->request->post['store_id']) ? (int)$this->request->post['store_id'] : 0;
			$entity_id = isset($this->request->post['entity_id']) ? (int)$this->request->post['entity_id'] : 0;
			$existing = $this->model_cms_page->getPageByRoute($this->request->post['route'], $entity_id, $store_id);
			if ($existing) {
				$this->session->data['success'] = $this->language->get('text_duplicate_redirect');
				$this->response->redirect($this->url->link('cms/page/edit', 'user_token=' . $this->session->data['user_token'] . '&page_id=' . (int)$existing['page_id'], true));
				return;
			}

			$page_id = $this->model_cms_page->addPage($this->request->post);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('cms/page/edit', 'user_token=' . $this->session->data['user_token'] . '&page_id=' . $page_id, true));
		}

		$this->getForm();
	}

	public function edit() {
		$this->load->language('cms/page');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->addScript('view/javascript/jquery/Sortable.js');
		$this->document->addScript('view/javascript/jquery/jquery-sortable.js');
		$this->load->model('cms/page');
		$this->load->model('cms/block');

		$page_id = isset($this->request->get['page_id']) ? (int)$this->request->get['page_id'] : 0;
		$page = $this->model_cms_page->getPage($page_id);
		if (!$page) {
			$this->response->redirect($this->url->link('cms/page', 'user_token=' . $this->session->data['user_token'], true));
		}

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && isset($this->request->post['save_page']) && $this->validateForm($page_id)) {
			$this->model_cms_page->editPage($page_id, $this->request->post);
			$this->model_cms_block->publishDrafts($page_id);
			$this->invalidateCache($page);
			$this->session->data['success'] = $this->language->get('text_success');
			$this->response->redirect($this->url->link('cms/page/edit', 'user_token=' . $this->session->data['user_token'] . '&page_id=' . $page_id, true));
		}

		$this->getBuilder($page);
	}

	public function delete() {
		$this->load->language('cms/page');
		$this->load->model('cms/page');

		if (!$this->user->hasPermission('modify', 'cms/page')) {
			$this->response->redirect($this->url->link('error/permission', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$page_id = isset($this->request->get['page_id']) ? (int)$this->request->get['page_id'] : 0;
		$page = $page_id ? $this->model_cms_page->getPage($page_id) : null;

		if ($page) {
			$this->invalidateCache($page);
			$this->model_cms_page->deletePage($page_id);
			$this->session->data['success'] = $this->language->get('text_delete_success');
		}

		$this->response->redirect($this->url->link('cms/page', 'user_token=' . $this->session->data['user_token'], true));
	}

	public function renderBlock() {
		$json = array();
		if (!$this->user->hasPermission('modify', 'cms/page')) {
			$this->load->language('cms/page');
			$json['error'] = $this->language->get('error_permission');
		} else {
			$block_id = isset($this->request->get['block_id']) ? (int)$this->request->get['block_id'] : 0;
			$this->load->model('cms/block');
			$this->load->model('cms/page');
			$block = $this->model_cms_block->getBlock($block_id);
			if (!$block) {
				$this->load->language('cms/page');
				$json['error'] = $this->language->get('error_block');
			} else {
				$page = $this->model_cms_page->getPage($block['page_id']);
				$data = $this->getBuilderFormData($page);
				$registryTypes = class_exists('Cms\\Registry') ? \Cms\Registry::all() : array();
				$def = isset($registryTypes[$block['type']]) ? $registryTypes[$block['type']] : null;
				$data['block'] = $this->enrichBlockSettings($block, $data['placeholder']);
				$data['label'] = $def ? $def['label'] : $block['type'];
				$json['success'] = true;
				$json['html'] = $this->load->view('cms/block_item', $data);
			}
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function addBlock() {
		$this->load->language('cms/page');
		$json = array();
		if (!$this->user->hasPermission('modify', 'cms/page')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$page_id = isset($this->request->post['page_id']) ? (int)$this->request->post['page_id'] : 0;
			$type = isset($this->request->post['type']) ? $this->request->post['type'] : '';
			$slot = isset($this->request->post['slot']) ? $this->request->post['slot'] : 'main';
			$this->load->model('cms/page');
			$this->load->model('cms/block');
			$page = $this->model_cms_page->getPage($page_id);
			if (!$page || !class_exists('Cms\\Registry') || !\Cms\Registry::isAllowed($type, $page['route'], $slot)) {
				$json['error'] = $this->language->get('error_block_type');
			} else {
				$blocks = $this->model_cms_block->getBlocksByPageId($page_id);
				$block_id = $this->model_cms_block->addBlock($page_id, array(
					'type' => $type,
					'slot' => $slot,
					'sort_order' => count($blocks),
					'status' => 1,
					'is_draft' => 1,
					'settings' => $this->model_cms_block->getDefaultSettings($type),
				));
				$json['success'] = true;
				$json['block_id'] = $block_id;
			}
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function saveBlock() {
		$this->load->language('cms/page');
		$json = array();
		if (!$this->user->hasPermission('modify', 'cms/page')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$block_id = isset($this->request->post['block_id']) ? (int)$this->request->post['block_id'] : 0;
			$this->load->model('cms/block');
			$this->load->model('cms/page');
			$block = $this->model_cms_block->getBlock($block_id);
			if (!$block) {
				$json['error'] = $this->language->get('error_block');
			} else {
				$page = $this->model_cms_page->getPage($block['page_id']);
				$settings = isset($this->request->post['settings']) ? $this->request->post['settings'] : array();
				$this->model_cms_block->editBlock($block_id, array(
					'type' => $block['type'],
					'slot' => isset($this->request->post['slot']) ? $this->request->post['slot'] : $block['slot'],
					'sort_order' => (int)$block['sort_order'],
					'status' => isset($this->request->post['status']) ? (int)$this->request->post['status'] : 1,
					'is_draft' => 1,
					'settings' => $this->model_cms_block->sanitizeSettings($block['type'], $settings),
				));
				if ($page) {
					$this->invalidateCache($page);
				}
				$json['success'] = true;
			}
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function deleteBlock() {
		$this->load->language('cms/page');
		$json = array();
		if (!$this->user->hasPermission('modify', 'cms/page')) {
			$json['error'] = $this->language->get('error_permission');
		} else {
			$block_id = isset($this->request->post['block_id']) ? (int)$this->request->post['block_id'] : 0;
			$this->load->model('cms/block');
			$this->load->model('cms/page');
			$block = $this->model_cms_block->getBlock($block_id);
			if ($block) {
				$page = $this->model_cms_page->getPage($block['page_id']);
				$this->model_cms_block->deleteBlock($block_id);
				if ($page) {
					$this->invalidateCache($page);
				}
			}
			$json['success'] = true;
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function reorderBlocks() {
		$json = array();
		if (!$this->user->hasPermission('modify', 'cms/page')) {
			$json['error'] = 'Permission denied';
		} else {
			$page_id = isset($this->request->post['page_id']) ? (int)$this->request->post['page_id'] : 0;
			$block_ids = isset($this->request->post['block_ids']) ? $this->request->post['block_ids'] : array();
			$this->load->model('cms/block');
			$this->load->model('cms/page');
			$this->model_cms_block->reorderBlocks($page_id, $block_ids);
			$page = $this->model_cms_page->getPage($page_id);
			if ($page) {
				$this->invalidateCache($page);
			}
			$json['success'] = true;
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function entityAutocomplete() {
		$json = array();

		if (!$this->user->hasPermission('access', 'cms/page')) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$route = isset($this->request->get['entity_route']) ? (string)$this->request->get['entity_route'] : '';
		$filter_name = isset($this->request->get['filter_name']) ? $this->request->get['filter_name'] : '';
		$limit = (int)$this->config->get('config_limit_autocomplete');
		if ($limit < 1) {
			$limit = 20;
		}

		$filter_data = array(
			'filter_name' => $filter_name,
			'start' => 0,
			'limit' => $limit,
		);

		switch ($route) {
			case 'information/information':
				$this->load->model('catalog/information');
				$language_id = (int)$this->config->get('config_language_id');
				$sql = "SELECT i.information_id, id.title FROM `" . DB_PREFIX . "information` i LEFT JOIN `" . DB_PREFIX . "information_description` id ON (i.information_id = id.information_id) WHERE id.language_id = '" . $language_id . "'";
				if ($filter_name !== '') {
					$sql .= " AND id.title LIKE '%" . $this->db->escape($filter_name) . "%'";
				}
				$sql .= " ORDER BY id.title ASC LIMIT " . (int)$limit;
				$query = $this->db->query($sql);
				foreach ($query->rows as $row) {
					$json[] = array(
						'id' => (int)$row['information_id'],
						'name' => strip_tags(html_entity_decode($row['title'], ENT_QUOTES, 'UTF-8')),
					);
				}
				break;

			case 'product/category':
				$this->load->model('catalog/category');
				$results = $this->model_catalog_category->getCategories($filter_data);
				foreach ($results as $result) {
					$json[] = array(
						'id' => (int)$result['category_id'],
						'name' => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
					);
				}
				break;

			case 'product/product':
				$this->load->model('catalog/product');
				$results = $this->model_catalog_product->getProducts($filter_data);
				foreach ($results as $result) {
					$json[] = array(
						'id' => (int)$result['product_id'],
						'name' => strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')),
					);
				}
				break;
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function previewToken() {
		$json = array();
		if (!$this->user->hasPermission('access', 'cms/page')) {
			$json['error'] = 'Permission denied';
		} else {
			$page_id = isset($this->request->get['page_id']) ? (int)$this->request->get['page_id'] : 0;
			$token = \Cms\PreviewToken::create($this->registry, $page_id, (int)$this->user->getId());
			$json['token'] = $token;
			$json['url'] = HTTP_CATALOG . 'index.php?route=cms/preview&page_id=' . $page_id . '&cms_preview=' . urlencode($token);
		}
		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	private function getForm() {
		$data = $this->load->language('cms/page');
		$data['breadcrumbs'] = $this->getBreadcrumbs();
		$data['action'] = $this->url->link('cms/page/add', 'user_token=' . $this->session->data['user_token'], true);
		$data['cancel'] = $this->url->link('cms/page', 'user_token=' . $this->session->data['user_token'], true);
		$data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
		$data['name'] = isset($this->request->post['name']) ? $this->request->post['name'] : '';
		$data['route'] = isset($this->request->post['route']) ? $this->request->post['route'] : 'information/information';
		$data['entity_id'] = isset($this->request->post['entity_id']) ? (int)$this->request->post['entity_id'] : 0;
		$data['entity_name'] = isset($this->request->post['entity_name']) ? $this->request->post['entity_name'] : '';
		if ($data['entity_name'] === '' && $data['entity_id'] > 0) {
			$data['entity_name'] = $this->resolveEntityName($data['route'], $data['entity_id']);
		}
		$data['store_id'] = isset($this->request->post['store_id']) ? (int)$this->request->post['store_id'] : 0;
		$data['status'] = isset($this->request->post['status']) ? (int)$this->request->post['status'] : 0;
		$data['user_token'] = $this->session->data['user_token'];
		$data['routes'] = array(
			array('value' => 'common/home', 'label' => 'Главная'),
			array('value' => 'information/information', 'label' => 'Информационная страница'),
			array('value' => 'product/category', 'label' => 'Категория'),
			array('value' => 'product/product', 'label' => 'Товар'),
		);
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('cms/page_form', $data));
	}

	private function getBuilderFormData($page) {
		$data = $this->load->language('cms/page');

		$this->load->model('localisation/language');
		$this->load->model('catalog/category');
		$this->load->model('catalog/information');
		$this->load->model('setting/module');
		$this->load->model('tool/image');

		$data['placeholder'] = $this->model_tool_image->resize('no_image.png', 100, 100);
		$data['languages'] = $this->model_localisation_language->getLanguages();
		$data['categories'] = $this->model_catalog_category->getCategories(0);
		$data['informations'] = $this->model_catalog_information->getInformations();
		$data['modules'] = $this->model_setting_module->getModules();
		$data['slots'] = $this->getSlotsForRoute($page['route']);

		return $data;
	}

	private function getBuilder($page) {
		$data = $this->getBuilderFormData($page);
		$data['breadcrumbs'] = $this->getBreadcrumbs($page);
		$data['page'] = $page;
		$data['page_id'] = (int)$page['page_id'];
		$data['action'] = $this->url->link('cms/page/edit', 'user_token=' . $this->session->data['user_token'] . '&page_id=' . $page['page_id'], true);
		$data['cancel'] = $this->url->link('cms/page', 'user_token=' . $this->session->data['user_token'], true);
		$data['storefront_url'] = $this->model_cms_page->getStorefrontUrl($page);
		$data['preview_token_url'] = $this->url->link('cms/page/previewToken', 'user_token=' . $this->session->data['user_token'] . '&page_id=' . $page['page_id'], true);
		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['blocks'] = array();
		$registryTypes = class_exists('Cms\\Registry') ? \Cms\Registry::all() : array();
		foreach ($this->model_cms_block->getBlocksByPageId($page['page_id']) as $block) {
			$block = $this->enrichBlockSettings($block, $data['placeholder']);
			$def = isset($registryTypes[$block['type']]) ? $registryTypes[$block['type']] : null;
			$data['blocks'][] = array(
				'block' => $block,
				'label' => $def ? $def['label'] : $block['type'],
				'icon' => $def ? $def['icon'] : 'widgets',
				'form' => $def ? $def['admin_form'] : '',
			);
		}

		$data['allowed_types'] = array();
		foreach (\Cms\Registry::allowedForPage($page['route'], 'main') as $type => $def) {
			$data['allowed_types'][] = array(
				'type' => $type,
				'label' => $def['label'],
				'description' => $def['description'],
				'icon' => $def['icon'],
			);
		}

		$data['link_types'] = array(
			array('value' => 'catalog', 'label' => 'Каталог'),
			array('value' => 'category', 'label' => 'Категория'),
			array('value' => 'information', 'label' => 'Информационная страница'),
			array('value' => 'contact', 'label' => 'Контакты'),
			array('value' => 'custom', 'label' => 'Свой URL'),
		);

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('cms/page_builder', $data));
	}

	private function getSlotsForRoute($route) {
		switch ($route) {
			case 'common/home':
				return array('main' => 'Основной контент', 'content_top' => 'Верх', 'content_bottom' => 'Низ');
			case 'product/category':
				return array('before_content' => 'Перед контентом', 'after_content' => 'После контента', 'content_top' => 'Верх', 'content_bottom' => 'Низ');
			case 'product/product':
				return array('content_top' => 'Над карточкой', 'content_bottom' => 'Под карточкой');
			default:
				return array('main' => 'Основной контент');
		}
	}

	private function getBreadcrumbs($page = null) {
		$this->load->language('cms/page');
		$breadcrumbs = array(
			array(
				'text' => $this->language->get('text_home'),
				'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true),
			),
			array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('cms/page', 'user_token=' . $this->session->data['user_token'], true),
			),
		);
		if ($page) {
			$breadcrumbs[] = array(
				'text' => $page['name'],
				'href' => $this->url->link('cms/page/edit', 'user_token=' . $this->session->data['user_token'] . '&page_id=' . $page['page_id'], true),
			);
		}
		return $breadcrumbs;
	}

	private function validateForm($page_id = 0) {
		if (!$this->user->hasPermission('modify', 'cms/page')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		if (utf8_strlen(trim($this->request->post['name'])) < 2) {
			$this->error['warning'] = $this->language->get('error_name');
		}

		$route = isset($this->request->post['route']) ? $this->request->post['route'] : '';
		$entity_id = isset($this->request->post['entity_id']) ? (int)$this->request->post['entity_id'] : 0;
		$store_id = isset($this->request->post['store_id']) ? (int)$this->request->post['store_id'] : 0;

		if ($route === 'common/home') {
			$entity_id = 0;
		} elseif ($entity_id < 1) {
			$this->error['warning'] = $this->language->get('error_entity');
		}

		if ((int)$page_id > 0 && !$this->error) {
			$existing = $this->model_cms_page->getPageByRoute($route, $entity_id, $store_id);
			if ($existing && (int)$existing['page_id'] !== (int)$page_id) {
				$this->error['warning'] = $this->language->get('error_duplicate');
			}
		}

		return !$this->error;
	}

	private function invalidateCache($page) {
		if ($this->registry->has('cache')) {
			$prefix = 'cms.' . preg_replace('/[^a-z0-9_]/', '_', $page['route']) . '.' . (int)$page['page_id'];
			$this->registry->get('cache')->delete($prefix);
		}
	}

	private function resolveEntityName($route, $entity_id) {
		$entity_id = (int)$entity_id;
		if ($entity_id < 1) {
			return '';
		}

		switch ($route) {
			case 'information/information':
				$this->load->model('catalog/information');
				$descriptions = $this->model_catalog_information->getInformationDescriptions($entity_id);
				$language_id = (int)$this->config->get('config_language_id');
				if (isset($descriptions[$language_id]['title'])) {
					return $descriptions[$language_id]['title'];
				}
				break;

			case 'product/category':
				$this->load->model('catalog/category');
				$category = $this->model_catalog_category->getCategory($entity_id);
				if ($category) {
					return $category['name'];
				}
				break;

			case 'product/product':
				$this->load->model('catalog/product');
				$product = $this->model_catalog_product->getProduct($entity_id);
				if ($product) {
					return $product['name'];
				}
				break;
		}

		return '';
	}

	private function enrichBlockSettings($block, $placeholder) {
		if (!isset($block['settings']) || !is_array($block['settings'])) {
			$block['settings'] = array();
		}

		if (!empty($block['settings']['image'])) {
			$block['settings']['image_thumb'] = $this->model_tool_image->resize($block['settings']['image'], 100, 100);
		} else {
			$block['settings']['image_thumb'] = $placeholder;
		}

		if ($block['type'] === 'category_grid' && !empty($block['settings']['tiles']) && is_array($block['settings']['tiles'])) {
			foreach ($block['settings']['tiles'] as $i => $tile) {
				if (!empty($tile['image'])) {
					$block['settings']['tiles'][$i]['image_thumb'] = $this->model_tool_image->resize($tile['image'], 100, 100);
				} else {
					$block['settings']['tiles'][$i]['image_thumb'] = $placeholder;
				}
			}
		}

		return $block;
	}
}
