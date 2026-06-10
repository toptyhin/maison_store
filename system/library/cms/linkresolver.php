<?php
namespace Cms;

final class LinkResolver {
	private $registry;

	public function __construct($registry) {
		$this->registry = $registry;
	}

	public function resolve($link) {
		if (!is_array($link)) {
			return '#';
		}

		$type = isset($link['type']) ? (string)$link['type'] : 'custom';
		$id = isset($link['id']) ? (int)$link['id'] : 0;
		$url = isset($link['url']) ? trim((string)$link['url']) : '';

		switch ($type) {
			case 'category':
				if ($id > 0) {
					return $this->registry->get('url')->link('product/category', 'path=' . $id, true);
				}
				break;
			case 'information':
				if ($id > 0) {
					return $this->registry->get('url')->link('information/information', 'information_id=' . $id, true);
				}
				break;
			case 'contact':
				return $this->registry->get('url')->link('information/contact', '', true);
			case 'catalog':
				return $this->resolveCatalog();
			case 'custom':
				return $this->resolveCustom($url);
		}

		return '#';
	}

	public function resolveCustom($url) {
		if ($url === '') {
			return '#';
		}

		$lower = strtolower($url);
		if (strpos($lower, 'javascript:') === 0 || strpos($lower, 'data:') === 0 || strpos($lower, 'vbscript:') === 0) {
			return '#';
		}

		if (preg_match('#^https?://#i', $url)) {
			return $url;
		}

		if ($url[0] === '/') {
			$base = $this->registry->get('config')->get('config_url');
			return rtrim($base, '/') . $url;
		}

		return $url;
	}

	public function isExternal($href) {
		return (bool)preg_match('#^https?://#i', $href);
	}

	private function resolveCatalog() {
		$query = $this->registry->get('db')->query("SELECT keyword FROM `" . DB_PREFIX . "seo_url` WHERE keyword = 'katalog' LIMIT 1");
		if ($query->num_rows && !empty($query->row['keyword'])) {
			$base = $this->registry->get('config')->get('config_url');
			return rtrim($base, '/') . '/' . $query->row['keyword'];
		}

		return $this->registry->get('url')->link('product/category', '', true);
	}
}
