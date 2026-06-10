<?php
class ControllerCmsRender extends Controller {
	public function slot($route, $entity_id, $slot, $context = array()) {
		if (!class_exists('Cms\\BlockEngine')) {
			return '';
		}

		$preview = false;
		$page_id = 0;
		if (isset($this->request->get['cms_preview']) && isset($this->request->get['page_id'])) {
			$page_id = (int)$this->request->get['page_id'];
			if (class_exists('Cms\\PreviewToken') && \Cms\PreviewToken::validate($this->registry, $this->request->get['cms_preview'], $page_id)) {
				$preview = true;
			}
		}

		$engine = new \Cms\BlockEngine($this->registry);
		return $engine->renderSlot($route, (int)$entity_id, $slot, $context, array(
			'preview' => $preview,
			'page_id' => $page_id,
		));
	}

	public function hasCmsPage($route, $entity_id) {
		if (!class_exists('Cms\\BlockEngine')) {
			return false;
		}
		$engine = new \Cms\BlockEngine($this->registry);
		return $engine->hasPage($route, (int)$entity_id);
	}
}
