<?php
namespace Cms;

final class BlockEngine {
	private $registry;
	private $linkResolver;

	public function __construct($registry) {
		$this->registry = $registry;
		$this->linkResolver = new LinkResolver($registry);
	}

	public function getConfig() {
		$routes = $this->registry->get('config')->get('config_cms_blocks_routes');
		if (is_string($routes)) {
			$routes = array_filter(array_map('trim', explode(',', $routes)));
		} else {
			$routes = array();
		}

		return array(
			'status' => (int)$this->registry->get('config')->get('config_cms_blocks_status'),
			'routes' => $routes,
		);
	}

	public function isEnabled($route) {
		return Registry::isEngineRouteEnabled($route, $this->getConfig());
	}

	public function hasPage($route, $entity_id, $store_id = null) {
		if (!$this->isEnabled($route)) {
			return false;
		}

		$this->registry->get('load')->model('cms/page');
		$page = $this->registry->get('model_cms_page')->getActivePage($route, $entity_id, $store_id);
		return !empty($page);
	}

	public function renderSlot($route, $entity_id, $slot, $context = array(), $options = array()) {
		if (!$this->isEnabled($route)) {
			return '';
		}

		$preview = !empty($options['preview']);
		$page = $this->resolvePage($route, $entity_id, $options);
		if (!$page) {
			return '';
		}

		$blocks = $this->registry->get('model_cms_page')->getBlocks($page['page_id'], $slot, $preview);
		if (!$blocks) {
			return '';
		}

		$html = '';
		foreach ($blocks as $block) {
			try {
				$html .= $this->renderBlock($block, $route, $slot, $context, $options);
			} catch (\Exception $e) {
				if ($this->registry->has('log')) {
					$this->registry->get('log')->write('CMS block render error [' . $block['type'] . ']: ' . $e->getMessage());
				}
			}
		}

		return $html;
	}

	public function renderBlock($block, $route, $slot, $context = array(), $options = array()) {
		if (empty($block['status'])) {
			return '';
		}

		if (!Registry::isAllowed($block['type'], $route, $slot)) {
			return '';
		}

		$def = Registry::get($block['type']);
		if (!$def) {
			return '';
		}

		$preview = !empty($options['preview']);
		$cacheKey = $this->buildCacheKey($block, $route, $slot, $def, $preview);
		if ($cacheKey && $this->registry->has('cache')) {
			$cached = $this->registry->get('cache')->get($cacheKey);
			if ($cached !== false && $cached !== null) {
				return $cached;
			}
		}

		$data = $this->prepareBlockData($block, $context);
		$output = $this->registry->get('load')->view($def['catalog_tpl'], $data);

		if ($cacheKey && $this->registry->has('cache')) {
			$this->registry->get('cache')->set($cacheKey, $output);
		}

		return $output;
	}

	public function invalidatePageCache($route, $entity_id, $store_id = null) {
		if (!$this->registry->has('cache')) {
			return;
		}

		$this->registry->get('load')->model('cms/page');
		$page = $this->registry->get('model_cms_page')->getPageByRoute($route, $entity_id, $store_id);
		if (!$page) {
			return;
		}

		$prefix = 'cms.' . preg_replace('/[^a-z0-9_]/', '_', $route) . '.' . (int)$page['page_id'];
		$this->registry->get('cache')->delete($prefix);
	}

	private function resolvePage($route, $entity_id, $options) {
		$this->registry->get('load')->model('cms/page');
		$store_id = isset($options['store_id']) ? (int)$options['store_id'] : null;

		if (!empty($options['preview']) && !empty($options['page_id'])) {
			return $this->registry->get('model_cms_page')->getPage((int)$options['page_id']);
		}

		return $this->registry->get('model_cms_page')->getActivePage($route, $entity_id, $store_id);
	}

	private function buildCacheKey($block, $route, $slot, $def, $preview) {
		if ($preview || empty($def['cacheable'])) {
			return '';
		}

		$store_id = (int)$this->registry->get('config')->get('config_store_id');
		$language_id = (int)$this->registry->get('config')->get('config_language_id');
		$key = 'cms.' . preg_replace('/[^a-z0-9_]/', '_', $route) . '.' . (int)$block['page_id'] . '.' . $slot . '.' . (int)$block['block_id'] . '.' . $store_id . '.' . $language_id;

		if (!empty($def['cache_vary'])) {
			$key .= '.' . (int)$this->registry->get('config')->get('config_customer_group_id');
		}

		return $key;
	}

	private function prepareBlockData($block, $context) {
		$language_id = (int)$this->registry->get('config')->get('config_language_id');
		$settings = is_array($block['settings']) ? $block['settings'] : json_decode($block['settings'], true);
		if (!is_array($settings)) {
			$settings = array();
		}

		$data = array(
			'block_id' => (int)$block['block_id'],
			'block_type' => $block['type'],
			'context' => $context,
		);

		switch ($block['type']) {
			case 'about_hero':
				$data['image'] = $this->resolveImage(isset($settings['image']) ? $settings['image'] : '');
				$data['title'] = $this->lang($settings, 'title', $language_id);
				$data['subtitle'] = $this->lang($settings, 'subtitle', $language_id);
				break;

			case 'feature_cards':
				$data['title'] = $this->lang($settings, 'title', $language_id);
				$data['items'] = $this->prepareFeatureItems(isset($settings['items']) ? $settings['items'] : array(), $language_id);
				break;

			case 'media_text':
				$data['image'] = $this->resolveImage(isset($settings['image']) ? $settings['image'] : '');
				$data['label'] = $this->lang($settings, 'label', $language_id);
				$data['title'] = $this->lang($settings, 'title', $language_id);
				$data['paragraphs'] = $this->prepareParagraphs(isset($settings['paragraphs']) ? $settings['paragraphs'] : array(), $language_id);
				$data['button_text'] = $this->lang($settings, 'button_text', $language_id);
				$data['button_href'] = $this->linkResolver->resolve(isset($settings['button_link']) ? $settings['button_link'] : array());
				$data['button_external'] = $this->linkResolver->isExternal($data['button_href']);
				break;

			case 'category_grid':
				$data['title'] = $this->lang($settings, 'title', $language_id);
				$data['subtitle'] = $this->lang($settings, 'subtitle', $language_id);
				$data['link_text'] = $this->lang($settings, 'link_text', $language_id);
				$data['link_href'] = $this->linkResolver->resolve(isset($settings['link']) ? $settings['link'] : array());
				$data['tiles'] = $this->prepareCategoryTiles(isset($settings['tiles']) ? $settings['tiles'] : array());
				$data['list'] = $this->prepareCategoryList(isset($settings['list']) ? $settings['list'] : array());
				break;

			case 'rich_text':
				$data['title'] = $this->lang($settings, 'title', $language_id);
				$data['content'] = HtmlSanitizer::richText($this->lang($settings, 'content', $language_id));
				break;

			case 'promo_banner':
				$data['image'] = $this->resolveImage(isset($settings['image']) ? $settings['image'] : '');
				$data['title'] = $this->lang($settings, 'title', $language_id);
				$data['subtitle'] = $this->lang($settings, 'subtitle', $language_id);
				$data['href'] = $this->linkResolver->resolve(isset($settings['link']) ? $settings['link'] : array());
				$data['external'] = $this->linkResolver->isExternal($data['href']);
				break;

			case 'cta_button':
				$data['text'] = $this->lang($settings, 'text', $language_id);
				$data['href'] = $this->linkResolver->resolve(isset($settings['link']) ? $settings['link'] : array());
				$data['external'] = $this->linkResolver->isExternal($data['href']);
				$data['style'] = isset($settings['style']) ? $settings['style'] : 'primary';
				break;

			case 'hero_slideshow':
			case 'featured_products':
			case 'module_reference':
				$data['module_html'] = $this->renderModuleReference($settings);
				break;

			case 'category_tiles':
				$data['title'] = $this->lang($settings, 'title', $language_id);
				$data['items'] = $this->prepareCategoryTiles(isset($settings['items']) ? $settings['items'] : array());
				break;

			default:
				$data['settings'] = $settings;
		}

		return $data;
	}

	private function lang($settings, $key, $language_id) {
		if (!isset($settings[$key])) {
			return '';
		}
		if (is_array($settings[$key])) {
			if (isset($settings[$key][$language_id])) {
				return $settings[$key][$language_id];
			}
			$first = reset($settings[$key]);
			return is_string($first) ? $first : '';
		}
		return (string)$settings[$key];
	}

	private function resolveImage($path) {
		$path = trim((string)$path);
		if ($path === '') {
			return '';
		}
		if (preg_match('#^https?://#i', $path)) {
			return $path;
		}
		if (defined('DIR_IMAGE') && is_file(DIR_IMAGE . $path)) {
			return $this->registry->get('config')->get('config_url') . 'image/' . $path;
		}
		return $this->registry->get('config')->get('config_url') . ltrim($path, '/');
	}

	private function prepareFeatureItems($items, $language_id) {
		$result = array();
		if (!is_array($items)) {
			return $result;
		}
		foreach ($items as $item) {
			if (isset($item['status']) && !$item['status']) {
				continue;
			}
			$result[] = array(
				'icon' => isset($item['icon']) ? $item['icon'] : 'star',
				'title' => $this->lang($item, 'title', $language_id),
				'subtitle' => $this->lang($item, 'subtitle', $language_id),
			);
		}
		return $result;
	}

	private function prepareParagraphs($paragraphs, $language_id) {
		$result = array();
		if (!is_array($paragraphs)) {
			return $result;
		}
		foreach ($paragraphs as $paragraph) {
			if (!is_array($paragraph)) {
				continue;
			}
			$text = isset($paragraph[$language_id]) ? $paragraph[$language_id] : '';
			if ($text === '' && isset($paragraph[1])) {
				$text = $paragraph[1];
			}
			if ($text === '') {
				continue;
			}
			$result[] = array(
				'text' => $text,
				'accent' => !empty($paragraph['accent']),
			);
		}
		return $result;
	}

	private function prepareCategoryTiles($tiles) {
		$this->registry->get('load')->model('catalog/category');
		$this->registry->get('load')->model('tool/image');
		$result = array();

		if (!is_array($tiles)) {
			return $result;
		}

		usort($tiles, function ($a, $b) {
			return (int)(isset($a['sort_order']) ? $a['sort_order'] : 0) - (int)(isset($b['sort_order']) ? $b['sort_order'] : 0);
		});

		foreach ($tiles as $tile) {
			if (isset($tile['status']) && !$tile['status']) {
				continue;
			}
			$category_id = isset($tile['category_id']) ? (int)$tile['category_id'] : 0;
			if (!$category_id) {
				continue;
			}
			$category = $this->registry->get('model_catalog_category')->getCategory($category_id);
			if (!$category) {
				continue;
			}
			$image = '';
			if (!empty($tile['image'])) {
				$image = $this->resolveImage($tile['image']);
			} elseif (!empty($category['image'])) {
				$image = $this->registry->get('model_tool_image')->resize($category['image'], 800, 800);
			}
			$result[] = array(
				'name' => $category['name'],
				'href' => $this->registry->get('url')->link('product/category', 'path=' . $category_id, true),
				'image' => $image,
				'layout' => isset($tile['layout']) ? $tile['layout'] : 'medium',
			);
		}

		return $result;
	}

	private function prepareCategoryList($items) {
		$this->registry->get('load')->model('catalog/category');
		$result = array();
		if (!is_array($items)) {
			return $result;
		}
		usort($items, function ($a, $b) {
			return (int)(isset($a['sort_order']) ? $a['sort_order'] : 0) - (int)(isset($b['sort_order']) ? $b['sort_order'] : 0);
		});
		foreach ($items as $item) {
			if (isset($item['status']) && !$item['status']) {
				continue;
			}
			$category_id = isset($item['category_id']) ? (int)$item['category_id'] : 0;
			if (!$category_id) {
				continue;
			}
			$category = $this->registry->get('model_catalog_category')->getCategory($category_id);
			if (!$category) {
				continue;
			}
			$result[] = array(
				'name' => $category['name'],
				'href' => $this->registry->get('url')->link('product/category', 'path=' . $category_id, true),
			);
		}
		return $result;
	}

	private function renderModuleReference($settings) {
		$code = isset($settings['module_code']) ? trim($settings['module_code']) : '';
		if ($code === '') {
			return '';
		}

		$parts = explode('.', $code);
		$this->registry->get('load')->model('setting/module');

		if (count($parts) === 2) {
			$module_info = $this->registry->get('model_setting_module')->getModule((int)$parts[1]);
			if ($module_info && !empty($module_info['status'])) {
				return $this->registry->get('load')->controller('extension/module/' . $parts[0], $module_info);
			}
		}

		if ($this->registry->get('config')->get('module_' . $parts[0] . '_status')) {
			return $this->registry->get('load')->controller('extension/module/' . $parts[0]);
		}

		return '';
	}
}
