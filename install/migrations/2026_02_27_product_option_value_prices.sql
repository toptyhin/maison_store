-- Migration: add oc_product_option_value_prices table
-- Stores per-customer-group absolute prices (and optional special prices) for product option values.
-- The existing oc_product_option_value.price remains as the default-group fallback.

CREATE TABLE IF NOT EXISTS `oc_product_option_value_prices` (
  `id`                      INT(11) NOT NULL AUTO_INCREMENT,
  `product_option_value_id` INT(11) NOT NULL,
  `product_id`              INT(11) NOT NULL,
  `customer_group_id`       INT(11) NOT NULL,
  `price`                   DECIMAL(15,4) NOT NULL DEFAULT '0.0000',
  `special_price`           DECIMAL(15,4) NOT NULL DEFAULT '0.0000',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_pov_group` (`product_option_value_id`, `customer_group_id`),
  KEY `product_id` (`product_id`),
  KEY `customer_group_id` (`customer_group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci;
