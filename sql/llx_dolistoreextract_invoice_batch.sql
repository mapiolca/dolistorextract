CREATE TABLE llx_dolistoreextract_invoice_batch (
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_facture integer DEFAULT NULL,
	period_year smallint NOT NULL,
	period_month tinyint NOT NULL,
	amount_ht double(24,8) DEFAULT 0,
	orders_count integer DEFAULT 0,
	lines_count integer DEFAULT 0,
	email_sent tinyint DEFAULT 0,
	email_sent_date datetime DEFAULT NULL,
	status smallint DEFAULT 0 NOT NULL,
	log text,
	datec datetime DEFAULT NULL,
	tms timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
	fk_user_creat integer DEFAULT NULL,
	fk_user_modif integer DEFAULT NULL
) ENGINE=innodb;
