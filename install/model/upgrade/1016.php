<?php
/**
 * CMS Block Builder - database migration and seed
 */
class ModelUpgrade1016 extends Model {
	public function upgrade() {
		$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "cms_page'");
		if (!$query->num_rows) {
			$this->db->query("CREATE TABLE `" . DB_PREFIX . "cms_page` (
				`page_id` int(11) NOT NULL AUTO_INCREMENT,
				`route` varchar(64) NOT NULL,
				`entity_id` int(11) NOT NULL DEFAULT '0',
				`store_id` int(11) NOT NULL DEFAULT '0',
				`name` varchar(128) NOT NULL DEFAULT '',
				`status` tinyint(1) NOT NULL DEFAULT '1',
				`date_modified` datetime NOT NULL,
				PRIMARY KEY (`page_id`),
				UNIQUE KEY `route_entity_store` (`route`, `entity_id`, `store_id`),
				KEY `store_id` (`store_id`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
		}

		$query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "cms_block'");
		if (!$query->num_rows) {
			$this->db->query("CREATE TABLE `" . DB_PREFIX . "cms_block` (
				`block_id` int(11) NOT NULL AUTO_INCREMENT,
				`page_id` int(11) NOT NULL,
				`type` varchar(64) NOT NULL,
				`slot` varchar(32) NOT NULL DEFAULT 'main',
				`sort_order` int(11) NOT NULL DEFAULT '0',
				`status` tinyint(1) NOT NULL DEFAULT '1',
				`is_draft` tinyint(1) NOT NULL DEFAULT '0',
				`settings` mediumtext NOT NULL,
				PRIMARY KEY (`block_id`),
				KEY `page_id` (`page_id`),
				KEY `page_slot` (`page_id`, `slot`, `sort_order`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
		}

		$this->ensureSettings();
		$this->ensurePermissions();
		$this->seedAboutPage();
		$this->migrateHomeModules();
	}

	private function migrateHomeModules() {
		$query = $this->db->query("SELECT page_id FROM `" . DB_PREFIX . "cms_page` WHERE route = 'common/home' AND entity_id = '0' AND store_id = '0' LIMIT 1");
		if (!$query->num_rows) {
			return;
		}
		$page_id = (int)$query->row['page_id'];

		$query = $this->db->query("SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "cms_block` WHERE page_id = '" . $page_id . "'");
		if ($query->row['total'] > 0) {
			return;
		}

		$layout_query = $this->db->query("SELECT layout_module_id, code, sort_order FROM `" . DB_PREFIX . "layout_module` WHERE layout_id = '1' AND position = 'content_top' ORDER BY sort_order ASC");
		$sort = 0;
		foreach ($layout_query->rows as $row) {
			if ($row['code'] === '0' || $row['code'] === '') {
				continue;
			}
			$type = 'module_reference';
			if (strpos($row['code'], 'slideshow') === 0) {
				$type = 'hero_slideshow';
			} elseif (strpos($row['code'], 'featured') === 0) {
				$type = 'featured_products';
			} elseif (strpos($row['code'], 'carousel') === 0) {
				$type = 'category_tiles';
			}
			$this->db->query("INSERT INTO `" . DB_PREFIX . "cms_block` SET page_id = '" . $page_id . "', type = '" . $this->db->escape($type) . "', slot = 'main', sort_order = '" . (int)$sort . "', status = '1', is_draft = '0', settings = '" . $this->db->escape(json_encode(array('module_code' => $row['code']), JSON_UNESCAPED_UNICODE)) . "'");
			$sort++;
		}
	}

	private function ensureSettings() {
		$keys = array(
			'config_cms_blocks_status' => '1',
			'config_cms_blocks_routes' => 'common/home,information/information,product/category,product/product',
		);

		foreach ($keys as $key => $value) {
			$query = $this->db->query("SELECT setting_id FROM `" . DB_PREFIX . "setting` WHERE `key` = '" . $this->db->escape($key) . "' AND store_id = '0' LIMIT 1");
			if (!$query->num_rows) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = '0', `code` = 'config', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "', serialized = '0'");
			}
		}
	}

	private function ensurePermissions() {
		$query = $this->db->query("SELECT user_group_id, permission FROM `" . DB_PREFIX . "user_group`");

		foreach ($query->rows as $row) {
			$permission = json_decode($row['permission'], true);
			if (!is_array($permission)) {
				continue;
			}

			$changed = false;
			foreach (array('access', 'modify') as $type) {
				if (!isset($permission[$type]) || !is_array($permission[$type])) {
					$permission[$type] = array();
				}
				if (!in_array('cms/page', $permission[$type], true)) {
					$permission[$type][] = 'cms/page';
					$changed = true;
				}
			}

			if ($changed) {
				$this->db->query("UPDATE `" . DB_PREFIX . "user_group` SET permission = '" . $this->db->escape(json_encode($permission)) . "' WHERE user_group_id = '" . (int)$row['user_group_id'] . "'");
			}
		}
	}

	private function seedAboutPage() {
		$query = $this->db->query("SELECT page_id FROM `" . DB_PREFIX . "cms_page` WHERE route = 'information/information' AND entity_id = '4' AND store_id = '0' LIMIT 1");
		if ($query->num_rows) {
			return;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "cms_page` SET route = 'information/information', entity_id = '4', store_id = '0', name = 'О компании', status = '1', date_modified = NOW()");
		$page_id = $this->db->getLastId();

		$blocks = array(
			array(
				'type' => 'about_hero',
				'slot' => 'main',
				'sort_order' => 0,
				'settings' => array(
					'image' => 'catalog/view/theme/maison/image/cms/about-hero.svg',
					'title' => array('1' => 'Maison Textile — мультибрендовый интернет-магазин текстиля для дома и товаров для сна.'),
					'subtitle' => array('1' => 'В коллекции собраны товары, созданные для изысканного интерьера, здорового сна, комфорта и хорошего настроения.'),
				),
			),
			array(
				'type' => 'feature_cards',
				'slot' => 'main',
				'sort_order' => 1,
				'settings' => array(
					'title' => array('1' => 'Удобство покупки'),
					'items' => array(
						array('icon' => 'shopping_bag', 'title' => array('1' => 'Легкое и удобное оформление заказа'), 'subtitle' => array('1' => 'Интуитивный процесс'), 'status' => 1, 'sort_order' => 0),
						array('icon' => 'assignment_return', 'title' => array('1' => 'Срок возврата в течение 14 дней'), 'subtitle' => array('1' => 'После получения заказа'), 'status' => 1, 'sort_order' => 1),
						array('icon' => 'local_shipping', 'title' => array('1' => 'Возможность выбора комфортного варианта'), 'subtitle' => array('1' => 'Удобная доставка'), 'status' => 1, 'sort_order' => 2),
					),
				),
			),
			array(
				'type' => 'media_text',
				'slot' => 'main',
				'sort_order' => 2,
				'settings' => array(
					'image' => 'catalog/view/theme/maison/image/cms/about-history.svg',
					'label' => array('1' => 'О нас'),
					'title' => array('1' => 'История'),
					'paragraphs' => array(
						array('1' => 'Компания Maison Textile была основана в 2023 году. На сегодняшний день каждый покупатель найдёт именно то, что нужно для создания уютной обстановки в доме.'),
						array('1' => 'На сайте представлена сертифицированная продукция различных брендов, отобранная с особым вниманием к качеству материалов и эстетике исполнения.'),
						array('1' => 'В ближайших планах — открытие собственного флагманского шоурума в Москве, где вы сможете лично оценить фактуру и качество наших коллекций.', 'accent' => 1),
					),
					'button_text' => array('1' => 'Узнать больше'),
					'button_link' => array('type' => 'contact', 'id' => 0, 'url' => ''),
				),
			),
			array(
				'type' => 'category_grid',
				'slot' => 'main',
				'sort_order' => 3,
				'settings' => array(
					'title' => array('1' => 'Широкий ассортимент'),
					'subtitle' => array('1' => 'Изысканные решения для спальни и дома, созданные с заботой о вашем комфорте.'),
					'link_text' => array('1' => 'Смотреть каталог'),
					'link' => array('type' => 'catalog', 'id' => 0, 'url' => ''),
					'tiles' => array(),
					'list' => array(),
				),
			),
		);

		foreach ($blocks as $block) {
			$this->db->query("INSERT INTO `" . DB_PREFIX . "cms_block` SET page_id = '" . (int)$page_id . "', type = '" . $this->db->escape($block['type']) . "', slot = '" . $this->db->escape($block['slot']) . "', sort_order = '" . (int)$block['sort_order'] . "', status = '1', is_draft = '0', settings = '" . $this->db->escape(json_encode($block['settings'], JSON_UNESCAPED_UNICODE)) . "'");
		}

		$this->seedHomePage();
	}

	private function seedHomePage() {
		$query = $this->db->query("SELECT page_id FROM `" . DB_PREFIX . "cms_page` WHERE route = 'common/home' AND entity_id = '0' AND store_id = '0' LIMIT 1");
		if ($query->num_rows) {
			return;
		}

		$this->db->query("INSERT INTO `" . DB_PREFIX . "cms_page` SET route = 'common/home', entity_id = '0', store_id = '0', name = 'Главная страница', status = '0', date_modified = NOW()");
	}
}
