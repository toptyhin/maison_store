-- Predefined attribute values (admin: catalog/attribute/edit → product card autocomplete)

DROP TABLE IF EXISTS `oc_attribute_value_description`;
DROP TABLE IF EXISTS `oc_attribute_value`;

CREATE TABLE `oc_attribute_value` (
  `attribute_value_id` INT(11) NOT NULL AUTO_INCREMENT,
  `attribute_id`       INT(11) NOT NULL,
  `sort_order`         INT(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`attribute_value_id`),
  KEY `attribute_id` (`attribute_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;

CREATE TABLE `oc_attribute_value_description` (
  `attribute_value_id` INT(11) NOT NULL,
  `attribute_id`       INT(11) NOT NULL,
  `language_id`        INT(11) NOT NULL,
  `name`               VARCHAR(255) NOT NULL,
  PRIMARY KEY (`attribute_value_id`, `language_id`),
  KEY `attribute_id` (`attribute_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
