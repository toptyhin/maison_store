<?php
class ControllerCustomerCustomerDocument extends Controller {
	private $error = array();

	public function index() {
		$this->load->language('customer/customer_document');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customer/customer');
		$this->load->model('customer/customer_group');

		$this->getList();
	}

	public function documents() {
		$this->load->language('customer/customer_document');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('customer/customer');
		$this->load->model('customer/customer_document');

		if (!isset($this->request->get['customer_id'])) {
			$this->response->redirect($this->url->link('customer/customer_document', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$customer_id = (int)$this->request->get['customer_id'];
		$customer_info = $this->model_customer_customer->getCustomer($customer_id);

		if (!$customer_info) {
			$this->session->data['error'] = $this->language->get('error_customer_not_found');
			$this->response->redirect($this->url->link('customer/customer_document', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('customer/customer_document', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $customer_info['firstname'] . ' ' . $customer_info['lastname'],
			'href' => $this->url->link('customer/customer_document/documents', 'user_token=' . $this->session->data['user_token'] . '&customer_id=' . $customer_id, true)
		);

		$data['customer_id'] = $customer_id;
		$data['customer_name'] = $customer_info['firstname'] . ' ' . $customer_info['lastname'];
		$data['back'] = $this->url->link('customer/customer_document', 'user_token=' . $this->session->data['user_token'], true);

		$data['help_upload'] = $this->language->get('help_upload');

		$data['document_types'] = array(
			'invoice'         => $this->language->get('text_document_invoice'),
			'invoice_factura' => $this->language->get('text_document_invoice_factura'),
			'delivery_note'   => $this->language->get('text_document_delivery_note'),
			'act'             => $this->language->get('text_document_act'),
			'contract'        => $this->language->get('text_document_contract')
		);

		$data['documents'] = array();

		$results = $this->model_customer_customer_document->getDocuments($customer_id);

		foreach ($results as $result) {
			$data['documents'][] = array(
				'customer_document_id' => $result['customer_document_id'],
				'document_type'        => isset($data['document_types'][$result['document_type']]) ? $data['document_types'][$result['document_type']] : $result['document_type'],
				'filename'             => $result['filename'] ? $result['filename'] : $result['upload_code'],
				'date_added'           => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'download'             => $this->url->link('customer/customer_document/download', 'user_token=' . $this->session->data['user_token'] . '&code=' . $result['upload_code'], true),
				'delete'               => $this->url->link('customer/customer_document/delete', 'user_token=' . $this->session->data['user_token'] . '&customer_document_id=' . $result['customer_document_id'] . '&customer_id=' . $customer_id, true)
			);
		}

		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		if (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];
			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('customer/customer_document_form', $data));
	}

	public function upload() {
		$this->load->language('customer/customer_document');

		$json = array();

		if (!$this->user->hasPermission('modify', 'customer/customer_document')) {
			$json['error'] = $this->language->get('error_permission');
		}

		if (!isset($this->request->get['customer_id'])) {
			$json['error'] = $this->language->get('error_customer_not_found');
		}

		if (!isset($this->request->get['document_type']) || !in_array($this->request->get['document_type'], array('invoice', 'invoice_factura', 'delivery_note', 'act', 'contract'))) {
			$json['error'] = $this->language->get('error_document_type');
		}

		if (!$json && !empty($this->request->files['file']['name']) && is_file($this->request->files['file']['tmp_name'])) {
			$filename = html_entity_decode($this->request->files['file']['name'], ENT_QUOTES, 'UTF-8');
			$extension = strtolower(substr(strrchr($filename, '.'), 1));

			if ($extension != 'pdf') {
				$json['error'] = $this->language->get('error_filetype');
			}

			if ($this->request->files['file']['type'] != 'application/pdf') {
				$json['error'] = $this->language->get('error_filetype');
			}

			if ((utf8_strlen($filename) < 3) || (utf8_strlen($filename) > 128)) {
				$json['error'] = $this->language->get('error_filename');
			}

			$content = file_get_contents($this->request->files['file']['tmp_name']);
			if (preg_match('/\<\?php/i', $content)) {
				$json['error'] = $this->language->get('error_filetype');
			}

			if ($this->request->files['file']['error'] != UPLOAD_ERR_OK) {
				$json['error'] = $this->language->get('error_upload');
			}
		} elseif (!$json) {
			$json['error'] = $this->language->get('error_upload');
		}

		if (!$json) {
			$file = $filename . '.' . token(32);
			move_uploaded_file($this->request->files['file']['tmp_name'], DIR_UPLOAD . $file);

			$this->load->model('tool/upload');
			$upload_code = $this->model_tool_upload->addUpload($filename, $file);

			$this->load->model('customer/customer_document');
			$this->model_customer_customer_document->addDocument((int)$this->request->get['customer_id'], $this->request->get['document_type'], $upload_code);

			$json['success'] = $this->language->get('text_upload_success');
		}

		$this->response->addHeader('Content-Type: application/json');
		$this->response->setOutput(json_encode($json));
	}

	public function delete() {
		$this->load->language('customer/customer_document');

		if (!$this->user->hasPermission('modify', 'customer/customer_document')) {
			$this->session->data['error'] = $this->language->get('error_permission');
		} elseif (isset($this->request->get['customer_document_id'])) {
			$this->load->model('customer/customer_document');
			$this->model_customer_customer_document->deleteDocument((int)$this->request->get['customer_document_id']);
			$this->session->data['success'] = $this->language->get('text_success');
		}

		$customer_id = isset($this->request->get['customer_id']) ? (int)$this->request->get['customer_id'] : 0;
		if ($customer_id) {
			$this->response->redirect($this->url->link('customer/customer_document/documents', 'user_token=' . $this->session->data['user_token'] . '&customer_id=' . $customer_id, true));
		} else {
			$this->response->redirect($this->url->link('customer/customer_document', 'user_token=' . $this->session->data['user_token'], true));
		}
	}

	public function download() {
		$this->load->language('customer/customer_document');
		$this->load->model('tool/upload');

		if (!isset($this->request->get['code'])) {
			$this->response->redirect($this->url->link('customer/customer_document', 'user_token=' . $this->session->data['user_token'], true));
			return;
		}

		$upload_info = $this->model_tool_upload->getUploadByCode($this->request->get['code']);

		if ($upload_info) {
			$file = DIR_UPLOAD . $upload_info['filename'];
			$mask = basename($upload_info['name']);

			if (file_exists($file) && filesize($file) > 0) {
				$this->response->addheader('Pragma: public');
				$this->response->addheader('Expires: 0');
				$this->response->addheader('Content-Description: File Transfer');
				$this->response->addheader('Content-Type: application/pdf');
				$this->response->addheader('Content-Disposition: attachment; filename="' . ($mask ? $mask : basename($file)) . '"');
				$this->response->addheader('Content-Transfer-Encoding: binary');
				$this->response->setOutput(file_get_contents($file, FILE_USE_INCLUDE_PATH, null));
			} else {
				$this->session->data['error'] = $this->language->get('error_file');
				$this->response->redirect($this->url->link('customer/customer_document', 'user_token=' . $this->session->data['user_token'], true));
			}
		} else {
			$this->session->data['error'] = $this->language->get('error_upload');
			$this->response->redirect($this->url->link('customer/customer_document', 'user_token=' . $this->session->data['user_token'], true));
		}
	}

	protected function getList() {
		if (isset($this->request->get['filter_name'])) {
			$filter_name = $this->request->get['filter_name'];
		} else {
			$filter_name = '';
		}

		if (isset($this->request->get['filter_email'])) {
			$filter_email = $this->request->get['filter_email'];
		} else {
			$filter_email = '';
		}

		if (isset($this->request->get['filter_customer_group_id'])) {
			$filter_customer_group_id = $this->request->get['filter_customer_group_id'] > 1 ? $this->request->get['filter_customer_group_id'] : '2';
		} else {
			$filter_customer_group_id = '2';
		}

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'name';
		}

		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}

		if (isset($this->request->get['page'])) {
			$page = (int)$this->request->get['page'];
		} else {
			$page = 1;
		}

		$url = '';

		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_email'])) {
			$url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_customer_group_id'])) {
			$url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('customer/customer_document', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);

		$filter_data = array(
			'filter_name'              => $filter_name,
			'filter_email'             => $filter_email,
			'filter_customer_group_id' => $filter_customer_group_id,
			'sort'                     => $sort,
			'order'                    => $order,
			'start'                    => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit'                    => $this->config->get('config_limit_admin')
		);

		$customer_total = $this->model_customer_customer->getTotalCustomers($filter_data);
		$results = $this->model_customer_customer->getCustomers($filter_data);

		$data['customers'] = array();

		foreach ($results as $result) {
			$data['customers'][] = array(
				'customer_id'    => $result['customer_id'],
				'name'           => $result['name'],
				'email'          => $result['email'],
				'customer_group' => $result['customer_group'],
				'documents'      => $this->url->link('customer/customer_document/documents', 'user_token=' . $this->session->data['user_token'] . '&customer_id=' . $result['customer_id'] . $url, true)
			);
		}

		$data['user_token'] = $this->session->data['user_token'];

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} elseif (isset($this->session->data['error'])) {
			$data['error_warning'] = $this->session->data['error'];
			unset($this->session->data['error']);
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$url = '';

		if (isset($this->request->get['filter_name'])) {
			$url .= '&filter_name=' . urlencode(html_entity_decode($this->request->get['filter_name'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_email'])) {
			$url .= '&filter_email=' . urlencode(html_entity_decode($this->request->get['filter_email'], ENT_QUOTES, 'UTF-8'));
		}

		if (isset($this->request->get['filter_customer_group_id'])) {
			$url .= '&filter_customer_group_id=' . $this->request->get['filter_customer_group_id'];
		}

		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}

		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		$pagination = new Pagination();
		$pagination->total = $customer_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('customer/customer_document', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);

		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), ($customer_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($customer_total - $this->config->get('config_limit_admin'))) ? $customer_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $customer_total, ceil($customer_total / $this->config->get('config_limit_admin')));

		$data['filter_name'] = $filter_name;
		$data['filter_email'] = $filter_email;
		$data['filter_customer_group_id'] = $filter_customer_group_id;
		$data['sort'] = $sort;
		$data['order'] = $order;

		$data['customer_groups'] = $this->model_customer_customer_group->getCustomerGroups();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('customer/customer_document_list', $data));
	}
}
