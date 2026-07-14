CREATE TABLE llx_dolistoreextract_import_log (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_order integer DEFAULT NULL,
	fk_invoice_batch integer DEFAULT NULL,
	source varchar(64) DEFAULT NULL,
	level varchar(16) DEFAULT 'info',
	message text,
	context text,
	datec datetime DEFAULT NULL,
	fk_user_creat integer DEFAULT NULL
) ENGINE=innodb;
