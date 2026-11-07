ALTER TABLE llx_dolistoreextract_welcome ADD UNIQUE INDEX uk_dse_welcome_order (entity, fk_order);
ALTER TABLE llx_dolistoreextract_welcome ADD INDEX idx_dse_welcome_due (entity, status, next_attempt);
ALTER TABLE llx_dolistoreextract_welcome ADD INDEX idx_dse_welcome_author (fk_user_creat);
