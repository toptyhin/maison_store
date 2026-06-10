-- CMS Block Builder: pages and blocks

DROP TABLE IF EXISTS `oc_cms_block`;
DROP TABLE IF EXISTS `oc_cms_page`;

CREATE TABLE `oc_cms_page` (
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE `oc_cms_block` (
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

INSERT INTO `oc_setting` (`store_id`, `code`, `key`, `value`, `serialized`) VALUES
(0, 'config', 'config_cms_blocks_status', '1', 0),
(0, 'config', 'config_cms_blocks_routes', 'common/home,information/information,product/category,product/product', 0);
