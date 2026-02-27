<?php
class ControllerAccountDocuments extends Controller {
	public function index() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/documents', '', true);
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('account/documents');

		$this->document->setTitle($this->language->get('heading_title'));
		$this->document->setRobots('noindex,follow');

		$this->load->model('account/document');

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
			'href' => $this->url->link('account/documents', '', true)
		);

		$data['heading_title'] = $this->language->get('heading_title');
		$data['action_documents'] = $this->url->link('account/documents', '', true);
		$data['form_action'] = $this->config->get('config_url');
		if ($this->request->server['HTTPS']) {
			$data['form_action'] = $this->config->get('config_ssl');
		}
		$data['form_action'] .= 'index.php';
		$data['continue'] = $this->url->link('account/account', '', true);
		$data['button_continue'] = $this->language->get('button_continue');
		$data['column_document_type'] = $this->language->get('column_document_type');
		$data['column_filename'] = $this->language->get('column_filename');
		$data['column_date_added'] = $this->language->get('column_date_added');
		$data['column_order_id'] = $this->language->get('column_order_id');
		$data['button_download'] = $this->language->get('button_download');
		$data['filter_search_placeholder'] = $this->language->get('filter_search_placeholder');
		$data['filter_all_types'] = $this->language->get('filter_all_types');
		$data['filter_period_placeholder'] = $this->language->get('filter_period_placeholder');
		$data['button_show_older'] = $this->language->get('button_show_older');
		$data['text_no_order'] = $this->language->get('text_no_order');
		$data['text_no_results'] = $this->language->get('text_no_results');
		$data['button_apply'] = $this->language->get('button_apply');
		$data['button_reset_filters'] = $this->language->get('button_reset_filters');

		$document_types = array(
			'invoice'         => $this->language->get('text_document_invoice'),
			'invoice_factura' => $this->language->get('text_document_invoice_factura'),
			'delivery_note'   => $this->language->get('text_document_delivery_note'),
			'act'             => $this->language->get('text_document_act'),
			'contract'        => $this->language->get('text_document_contract')
		);

		$filter_type = isset($this->request->get['filter_type']) ? $this->request->get['filter_type'] : '';
		$filter_search = isset($this->request->get['filter_search']) ? trim($this->request->get['filter_search']) : '';
		$filter_date = isset($this->request->get['filter_date']) ? $this->request->get['filter_date'] : '';
		$page = isset($this->request->get['page']) ? (int)$this->request->get['page'] : 1;

		$limit = 20;
		$start = ($page - 1) * $limit;

		$filter_data = array(
			'filter_type'   => $filter_type,
			'filter_search' => $filter_search,
			'filter_date'   => $filter_date,
			'start'         => $start,
			'limit'         => $limit
		);

		$document_total = $this->model_account_document->getTotalDocuments($this->customer->getId(), $filter_data);
		$results = $this->model_account_document->getDocuments($this->customer->getId(), $filter_data);

		$data['documents'] = array();

		foreach ($results as $result) {
			$data['documents'][] = array(
				'document_type'  => isset($document_types[$result['document_type']]) ? $document_types[$result['document_type']] : $result['document_type'],
				'document_type_key' => $result['document_type'],
				'filename'       => $result['filename'] ? $result['filename'] : $result['upload_code'],
				'date_added'     => date($this->language->get('date_format_short'), strtotime($result['date_added'])),
				'download'       => $this->url->link('account/documents/download', 'code=' . urlencode($result['upload_code']), true)
			);
		}

		$url = '';
		if ($filter_type) $url .= '&filter_type=' . urlencode($filter_type);
		if ($filter_search) $url .= '&filter_search=' . urlencode($filter_search);
		if ($filter_date) $url .= '&filter_date=' . urlencode($filter_date);

		$data['filter_type'] = $filter_type;
		$data['filter_search'] = $filter_search;
		$data['filter_date'] = $filter_date;
		$data['filter_applied'] = $filter_type || $filter_search || $filter_date;
		$data['document_types'] = $document_types;

		$pagination = new Pagination();
		$pagination->total = $document_total;
		$pagination->page = $page;
		$pagination->limit = $limit;
		$pagination->url = $this->url->link('account/documents', ($url ? $url . '&' : '') . 'page={page}', true);
		$data['pagination'] = $pagination->render();
		$data['document_total'] = $document_total;

		$data['text_empty'] = $this->language->get('text_empty');

		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}

		$data['menu'] = $this->load->controller('account/menu');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['column_right'] = $this->load->controller('common/column_right');
		$data['content_top'] = $this->load->controller('common/content_top');
		$data['content_bottom'] = $this->load->controller('common/content_bottom');
		$data['footer'] = $this->load->controller('common/footer');
		$data['header'] = $this->load->controller('common/header');

		$this->response->setOutput($this->load->view('account/documents', $data));
	}

	public function download() {
		if (!$this->customer->isLogged()) {
			$this->session->data['redirect'] = $this->url->link('account/documents', '', true);
			$this->response->redirect($this->url->link('account/login', '', true));
		}

		$this->load->language('account/documents');
		$this->load->model('account/document');

		if (!isset($this->request->get['code'])) {
			$this->response->redirect($this->url->link('account/documents', '', true));
			return;
		}

		$document_info = $this->model_account_document->getDocumentByCode($this->customer->getId(), $this->request->get['code']);

		if ($document_info && $document_info['storage_filename']) {
			$file = DIR_UPLOAD . $document_info['storage_filename'];
			$mask = basename($document_info['filename']);

			if (file_exists($file) && filesize($file) > 0) {
				if (!headers_sent()) {
					header('Content-Type: application/pdf');
					header('Content-Disposition: attachment; filename="' . ($mask ? $mask : basename($file)) . '"');
					header('Expires: 0');
					header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
					header('Pragma: public');
					header('Content-Length: ' . filesize($file));

					if (ob_get_level()) {
						ob_end_clean();
					}

					readfile($file, 'rb');
					exit();
				}
			}
		}

		$this->response->redirect($this->url->link('account/documents', '', true));
	}
}
