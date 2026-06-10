<?php
namespace Cms;

final class Registry {
	private static $types = null;

	public static function all() {
		if (self::$types === null) {
			self::$types = self::definitions();
		}
		return self::$types;
	}

	public static function get($type) {
		$all = self::all();
		return isset($all[$type]) ? $all[$type] : null;
	}

	public static function isAllowed($type, $route, $slot) {
		$def = self::get($type);
		if (!$def) {
			return false;
		}

		if (!in_array($slot, $def['slots'], true)) {
			return false;
		}

		if (in_array('*', $def['contexts'], true)) {
			return !self::isDeniedRoute($route);
		}

		foreach ($def['contexts'] as $context) {
			if ($context === $route || (substr($context, -1) === '*' && strpos($route, rtrim($context, '*')) === 0)) {
				return true;
			}
		}

		return false;
	}

	public static function allowedForPage($route, $slot) {
		$allowed = array();
		foreach (self::all() as $type => $def) {
			if (self::isAllowed($type, $route, $slot)) {
				$allowed[$type] = $def;
			}
		}
		return $allowed;
	}

	public static function isDeniedRoute($route) {
		$denied = array(
			'checkout/',
			'account/',
			'affiliate/',
			'api/',
			'mail/',
			'tool/',
		);

		foreach ($denied as $prefix) {
			if (strpos($route, $prefix) === 0) {
				return true;
			}
		}

		return false;
	}

	public static function isEngineRouteEnabled($route, $config) {
		if (empty($config['status'])) {
			return false;
		}

		if (self::isDeniedRoute($route)) {
			return false;
		}

		$routes = isset($config['routes']) ? $config['routes'] : array();
		if (!$routes) {
			return true;
		}

		foreach ($routes as $enabledRoute) {
			$enabledRoute = trim($enabledRoute);
			if ($enabledRoute === $route || (substr($enabledRoute, -1) === '*' && strpos($route, rtrim($enabledRoute, '*')) === 0)) {
				return true;
			}
		}

		return false;
	}

	private static function definitions() {
		$contentSlots = array('main', 'content_top', 'content_bottom', 'before_content', 'after_content');
		$slotOnly = array('content_top', 'content_bottom', 'before_content', 'after_content');

		return array(
			'about_hero' => array(
				'label' => 'Hero-баннер',
				'description' => 'Полноэкранный баннер с заголовком и подзаголовком',
				'icon' => 'image',
				'admin_form' => 'cms/block_form/about_hero',
				'catalog_tpl' => 'cms/blocks/about_hero',
				'contexts' => array('information/information'),
				'slots' => array('main'),
				'cacheable' => true,
				'cache_vary' => false,
			),
			'feature_cards' => array(
				'label' => 'Карточки преимуществ',
				'description' => 'Сетка карточек с иконками',
				'icon' => 'grid_view',
				'admin_form' => 'cms/block_form/feature_cards',
				'catalog_tpl' => 'cms/blocks/feature_cards',
				'contexts' => array('*'),
				'slots' => $contentSlots,
				'cacheable' => true,
				'cache_vary' => false,
			),
			'media_text' => array(
				'label' => 'Текст с изображением',
				'description' => 'Две колонки: фото и текст с кнопкой',
				'icon' => 'article',
				'admin_form' => 'cms/block_form/media_text',
				'catalog_tpl' => 'cms/blocks/media_text',
				'contexts' => array('*'),
				'slots' => $contentSlots,
				'cacheable' => true,
				'cache_vary' => false,
			),
			'category_grid' => array(
				'label' => 'Сетка категорий',
				'description' => 'Асимметричная сетка категорий каталога',
				'icon' => 'category',
				'admin_form' => 'cms/block_form/category_grid',
				'catalog_tpl' => 'cms/blocks/category_grid',
				'contexts' => array('information/information', 'common/home'),
				'slots' => array('main', 'content_top', 'content_bottom'),
				'cacheable' => true,
				'cache_vary' => false,
			),
			'rich_text' => array(
				'label' => 'Текстовый блок',
				'description' => 'Заголовок и форматированный текст',
				'icon' => 'format_align_left',
				'admin_form' => 'cms/block_form/rich_text',
				'catalog_tpl' => 'cms/blocks/rich_text',
				'contexts' => array('*'),
				'slots' => $contentSlots,
				'cacheable' => true,
				'cache_vary' => false,
			),
			'promo_banner' => array(
				'label' => 'Промо-баннер',
				'description' => 'Баннер с изображением и ссылкой',
				'icon' => 'campaign',
				'admin_form' => 'cms/block_form/promo_banner',
				'catalog_tpl' => 'cms/blocks/promo_banner',
				'contexts' => array('*'),
				'slots' => $contentSlots,
				'cacheable' => true,
				'cache_vary' => false,
			),
			'cta_button' => array(
				'label' => 'Кнопка действия',
				'description' => 'Кнопка со ссылкой',
				'icon' => 'smart_button',
				'admin_form' => 'cms/block_form/cta_button',
				'catalog_tpl' => 'cms/blocks/cta_button',
				'contexts' => array('*'),
				'slots' => $contentSlots,
				'cacheable' => true,
				'cache_vary' => false,
			),
			'hero_slideshow' => array(
				'label' => 'Слайдер главной',
				'description' => 'Hero-слайдер (обёртка над модулем slideshow)',
				'icon' => 'view_carousel',
				'admin_form' => 'cms/block_form/hero_slideshow',
				'catalog_tpl' => 'cms/blocks/hero_slideshow',
				'contexts' => array('common/home'),
				'slots' => array('main', 'content_top'),
				'cacheable' => true,
				'cache_vary' => false,
			),
			'featured_products' => array(
				'label' => 'Рекомендуемые товары',
				'description' => 'Сетка товаров из модуля featured',
				'icon' => 'shopping_bag',
				'admin_form' => 'cms/block_form/featured_products',
				'catalog_tpl' => 'cms/blocks/featured_products',
				'contexts' => array('common/home'),
				'slots' => array('main', 'content_top', 'content_bottom'),
				'cacheable' => true,
				'cache_vary' => true,
			),
			'category_tiles' => array(
				'label' => 'Плитки категорий',
				'description' => 'Категории с иконками для главной',
				'icon' => 'dashboard',
				'admin_form' => 'cms/block_form/category_tiles',
				'catalog_tpl' => 'cms/blocks/category_tiles',
				'contexts' => array('common/home'),
				'slots' => array('main', 'content_top', 'content_bottom'),
				'cacheable' => true,
				'cache_vary' => false,
			),
			'module_reference' => array(
				'label' => 'Модуль OpenCart',
				'description' => 'Подключение существующего extension/module',
				'icon' => 'extension',
				'admin_form' => 'cms/block_form/module_reference',
				'catalog_tpl' => 'cms/blocks/module_reference',
				'contexts' => array('*'),
				'slots' => array_merge(array('main'), $slotOnly),
				'cacheable' => false,
				'cache_vary' => true,
			),
		);
	}
}
