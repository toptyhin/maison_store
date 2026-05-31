-- Custom field keys per option (admin: catalog/option/edit → product card editor)
-- Re-run safe: drops broken/intermediate schema and recreates the final one.

DROP TABLE IF EXISTS `oc_option_custom_field_key`;

CREATE TABLE `oc_option_custom_field_key` (
  `option_custom_field_key_id` INT(11) NOT NULL AUTO_INCREMENT,
  `option_id`                  INT(11) NOT NULL,
  `field_key`                  VARCHAR(64) NOT NULL,
  `name`                       VARCHAR(255) NOT NULL DEFAULT '',
  `required`                   TINYINT(1) NOT NULL DEFAULT '0',
  `sort_order`                 INT(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`option_custom_field_key_id`),
  UNIQUE KEY `uniq_option_field_key` (`option_id`, `field_key`),
  KEY `option_id` (`option_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
