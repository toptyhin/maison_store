ALTER TABLE `oc_product_special`
ADD KEY `idx_ps_product_cg_priority_price`
    (`product_id`, `customer_group_id`, `priority`, `price`);


ALTER TABLE `oc_product_discount`
DROP KEY `product_customer_quantity`,
ADD KEY `product_customer_quantity`
    (`product_id`, `customer_group_id`, `quantity`, `priority`, `price`);

ALTER TABLE `oc_review`
DROP KEY `product_id`,
ADD KEY `idx_review_product_status_rating`
    (`product_id`, `status`, `rating`);    