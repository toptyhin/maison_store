<?php
class ControllerCmsPreview extends Controller {
	public function index() {
		$this->response->addHeader('X-Robots-Tag: noindex, nofollow');

		$page_id = isset($this->request->get['page_id']) ? (int)$this->request->get['page_id'] : 0;
		$token = isset($this->request->get['cms_preview']) ? $this->request->get['cms_preview'] : '';

		if (!$page_id || !class_exists('Cms\\PreviewToken') || !\Cms\PreviewToken::validate($this->registry, $token, $page_id)) {
			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 403 Forbidden');
			$this->response->setOutput('Forbidden');
			return;
		}

		$this->load->model('cms/page');
		$page = $this->model_cms_page->getPage($page_id);
		if (!$page) {
			$this->response->addHeader($this->request->server['SERVER_PROTOCOL'] . ' 404 Not Found');
			$this->response->setOutput('Not found');
			return;
		}

		$engine = new \Cms\BlockEngine($this->registry);
		$html = $engine->renderSlot($page['route'], (int)$page['entity_id'], 'main', array(), array(
			'preview' => true,
			'page_id' => $page_id,
		));

		if ($html === '') {
			$slots = array('content_top', 'before_content', 'after_content', 'content_bottom');
			foreach ($slots as $slot) {
				$html .= $engine->renderSlot($page['route'], (int)$page['entity_id'], $slot, array(), array(
					'preview' => true,
					'page_id' => $page_id,
				));
			}
		}

		$this->response->addHeader('Content-Type: text/html; charset=utf-8');
		$this->response->setOutput('<!DOCTYPE html><html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><link href="catalog/view/theme/maison/stylesheet/tailwind.bundle.min.css" rel="stylesheet"><link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;700&family=Playfair+Display:wght@400;500;700&display=swap" rel="stylesheet"><link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"></head><body class="bg-background-dark text-luxury-charcoal">' . $html . '</body></html>');
	}
}
