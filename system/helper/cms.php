<?php
function cms_render_slot($registry, $route, $entity_id, $slot, $context = array(), $options = array()) {
	if (!class_exists('Cms\\BlockEngine')) {
		return '';
	}

	$engine = new \Cms\BlockEngine($registry);
	return $engine->renderSlot($route, (int)$entity_id, $slot, $context, $options);
}

function cms_has_page($registry, $route, $entity_id) {
	if (!class_exists('Cms\\BlockEngine')) {
		return false;
	}

	$engine = new \Cms\BlockEngine($registry);
	return $engine->hasPage($route, (int)$entity_id);
}

function cms_preview_options($registry) {
	$options = array();
	$request = $registry->get('request');
	if (!isset($request->get['cms_preview'])) {
		return $options;
	}

	$page_id = isset($request->get['page_id']) ? (int)$request->get['page_id'] : 0;
	if ($page_id && class_exists('Cms\\PreviewToken') && \Cms\PreviewToken::validate($registry, $request->get['cms_preview'], $page_id)) {
		$options['preview'] = true;
		$options['page_id'] = $page_id;
	}

	return $options;
}

function cms_resolve_entity($registry) {
	$route = 'common/home';
	$entity_id = 0;
	$request = $registry->get('request');

	if (isset($request->get['route'])) {
		$route = (string)$request->get['route'];
	}

	if ($route === 'information/information' && isset($request->get['information_id'])) {
		$entity_id = (int)$request->get['information_id'];
	} elseif ($route === 'product/category' && isset($request->get['path'])) {
		$parts = explode('_', (string)$request->get['path']);
		$entity_id = (int)end($parts);
	} elseif ($route === 'product/product' && isset($request->get['product_id'])) {
		$entity_id = (int)$request->get['product_id'];
	}

	return array($route, $entity_id);
}
