ALTER TABLE llx_dolistoreextract_import_log ADD INDEX idx_dolistoreextract_import_log_entity (entity);
ALTER TABLE llx_dolistoreextract_import_log ADD INDEX idx_dolistoreextract_import_log_fk_order (entity, fk_order);
ALTER TABLE llx_dolistoreextract_import_log ADD INDEX idx_dolistoreextract_import_log_fk_batch (entity, fk_invoice_batch);
ALTER TABLE llx_dolistoreextract_import_log ADD INDEX idx_dolistoreextract_import_log_level (entity, level);
ALTER TABLE llx_dolistoreextract_import_log ADD INDEX idx_dolistoreextract_import_log_datec (entity, datec);
