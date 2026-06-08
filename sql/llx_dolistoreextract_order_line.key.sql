ALTER TABLE llx_dolistoreextract_order_line ADD INDEX idx_dolistoreextract_order_line_entity (entity);
ALTER TABLE llx_dolistoreextract_order_line ADD INDEX idx_dolistoreextract_order_line_fk_order (fk_order);
ALTER TABLE llx_dolistoreextract_order_line ADD INDEX idx_dolistoreextract_order_line_product_ref (entity, product_dolistore_ref);
ALTER TABLE llx_dolistoreextract_order_line ADD INDEX idx_dolistoreextract_order_line_fk_product (entity, fk_product);
ALTER TABLE llx_dolistoreextract_order_line ADD UNIQUE INDEX uk_dolistoreextract_order_line_hash (entity, fk_order, raw_hash);
