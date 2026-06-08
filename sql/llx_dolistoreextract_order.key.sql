ALTER TABLE llx_dolistoreextract_order ADD INDEX idx_dolistoreextract_order_entity (entity);
ALTER TABLE llx_dolistoreextract_order ADD UNIQUE INDEX uk_dolistoreextract_order_ref (entity, ref);
ALTER TABLE llx_dolistoreextract_order ADD UNIQUE INDEX uk_dolistoreextract_order_dolistore_ref (entity, dolistore_order_ref);
ALTER TABLE llx_dolistoreextract_order ADD UNIQUE INDEX uk_dolistoreextract_order_email_message_id (entity, email_message_id);
ALTER TABLE llx_dolistoreextract_order ADD UNIQUE INDEX uk_dolistoreextract_order_raw_hash (entity, raw_hash);
ALTER TABLE llx_dolistoreextract_order ADD INDEX idx_dolistoreextract_order_fk_facture (entity, fk_facture);
ALTER TABLE llx_dolistoreextract_order ADD INDEX idx_dolistoreextract_order_release_date (entity, release_date);
ALTER TABLE llx_dolistoreextract_order ADD INDEX idx_dolistoreextract_order_status (entity, status);
ALTER TABLE llx_dolistoreextract_order ADD INDEX idx_dolistoreextract_order_customer_email (entity, customer_email);
