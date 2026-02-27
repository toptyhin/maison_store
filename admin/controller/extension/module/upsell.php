<?php
class ControllerExtensionModuleUpsell extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('extension/module/upsell');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/module');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$post_data = $this->request->post;

			// Normalize product data: product[] as array of {product_id, option: {product_option_id: product_option_value_id}}
			$products = array();
			if (!empty($post_data['product'])) {
				foreach ($post_data['product'] as $item) {
					if (empty($item['product_id'])) {
						continue;
					}
					$product_entry = array(
						'product_id' => (int)$item['product_id'],
						'option'     => array()
					);
					if (!empty($item['option']) && is_array($item['option'])) {
						foreach ($item['option'] as $product_option_id => $product_option_value_id) {
							if ($product_option_value_id) {
								$product_entry['option'][(int)$product_option_id] = (int)$product_option_value_id;
							}
						}
					}
					$products[] = $product_entry;
				}
			}
			$post_data['product'] = $products;

			if (!isset($this->request->get['module_id'])) {
				$this->model_setting_module->addModule('upsell', $post_data);
			} else {
				$this->model_setting_module->editModule($this->request->get['module_id'], $post_data);
			}

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
		}

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['name'])) {
			$data['error_name'] = $this->error['name'];
		} else {
			$data['error_name'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
		);

		if (!isset($this->request->get['module_id'])) {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/upsell', 'user_token=' . $this->session->data['user_token'], true)
			);
		} else {
			$data['breadcrumbs'][] = array(
				'text' => $this->language->get('heading_title'),
				'href' => $this->url->link('extension/module/upsell', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true)
			);
		}

		if (!isset($this->request->get['module_id'])) {
			$data['action'] = $this->url->link('extension/module/upsell', 'user_token=' . $this->session->data['user_token'], true);
		} else {
			$data['action'] = $this->url->link('extension/module/upsell', 'user_token=' . $this->session->data['user_token'] . '&module_id=' . $this->request->get['module_id'], true);
		}

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

		if (isset($this->request->get['module_id']) && ($this->request->server['REQUEST_METHOD'] != 'POST')) {
			$module_info = $this->model_setting_module->getModule($this->request->get['module_id']);
		}

		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->request->post['name'])) {
			$data['name'] = $this->request->post['name'];
		} elseif (!empty($module_info['name'])) {
			$data['name'] = $module_info['name'];
		} else {
			$data['name'] = '';
		}

		$this->load->model('catalog/product');
		$this->load->model('catalog/option');

		$data['products'] = array();

		$products = array();
		if (!empty($this->request->post['product'])) {
			$products = $this->request->post['product'];
		} elseif (!empty($module_info['product'])) {
			$products = $module_info['product'];
		}

		foreach ($products as $product_entry) {
			$product_id = is_array($product_entry) ? (int)($product_entry['product_id'] ?? 0) : (int)$product_entry;
			if (!$product_id) {
				continue;
			}
			$options = is_array($product_entry) && isset($product_entry['option']) ? $product_entry['option'] : array();

			$product_info = $this->model_catalog_product->getProduct($product_id);
			if (!$product_info) {
				continue;
			}

			$product_options = $this->model_catalog_product->getProductOptions($product_id);
			foreach ($product_options as $po_idx => $po) {
				if (in_array($po['type'], array('select', 'radio', 'checkbox', 'image'))) {
					foreach ($po['product_option_value'] as $pov_idx => $pov) {
						$ov = $this->model_catalog_option->getOptionValue($pov['option_value_id']);
						$product_options[$po_idx]['product_option_value'][$pov_idx]['name'] = $ov ? $ov['name'] : '';
					}
					// Ensure option key exists for Twig attribute() to avoid undefined key
					if (!isset($options[$po['product_option_id']])) {
						$options[$po['product_option_id']] = '';
					}
				}
			}

			$data['products'][] = array(
				'product_id'      => $product_id,
				'name'           => $product_info['name'],
				'product_options'=> $product_options,
				'option'         => $options
			);
		}

		if (isset($this->request->post['limit'])) {
			$data['limit'] = $this->request->post['limit'];
		} elseif (!empty($module_info['limit'])) {
			$data['limit'] = $module_info['limit'];
		} else {
			$data['limit'] = 5;
		}

		if (isset($this->request->post['status'])) {
			$data['status'] = $this->request->post['status'];
		} elseif (!empty($module_info['status'])) {
			$data['status'] = $module_info['status'];
		} else {
			$data['status'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/module/upsell', $data));
	}

	/**
	 * Returns product options for given product_id (JSON, for AJAX)
	 */
	public function productOptions() {
		$this->load->model('catalog/product');
		$this->load->model('catalog/option');

		$json = array();
		$product_id = isset($this->request->get['product_id']) ? (int)$this->request->get['product_id'] : 0;
		if (!$product_id) {
			$this->response->addHeader('Content-Type: application/json');
			$this->response->setOutput(json_encode($json));
			return;
		}

		$product_options = $this->model_catalog_product->getProductOptions($product_id);
		foreach ($product_options as $po) {
			if (!in_array($po['type'], array('select', 'radio', 'checkbox', 'image'))) {
				continue;
			}
			$values = array();
			foreach ($po['product_option_value'] as $pov) {
				$ov = $this->model_catalog_option->getOptionValue($pov['option_value_id']);
				$values[] = array(
					'product_option_value_id' => $pov['product_option_value_id'],
					'option_value_id'         => $pov['option_value_id'],
					'name'                    => $ov ? $ov['name'] : '',
					'price'                   => $pov['price'],
					'price_prefix'            => $pov['price_prefix']
				);
			}
			$json[] = array(
				'product_option_id'    => $po['product_option_id'],
				'option_id'            => $po['option_id'],
				'name'                 => $po['name'],
				'type'                 => $po['type'],
				'product_option_value' => $values
			);
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	protected function validate() {
		if (!$this->user->hasPermission('modify', 'extension/module/upsell')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ((utf8_strlen($this->request->post['name']) < 3) || (utf8_strlen($this->request->post['name']) > 64)) {
			$this->error['name'] = $this->language->get('error_name');
		}

		return !$this->error;
	}
}
