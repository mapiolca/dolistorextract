CREATE TABLE llx_dolistoreextract_welcome
(
	rowid integer AUTO_INCREMENT PRIMARY KEY,
	entity integer DEFAULT 1 NOT NULL,
	fk_order integer NOT NULL,
	lang varchar(8) NOT NULL,
	snapshot_firstname varchar(255),
	snapshot_lastname varchar(255),
	status varchar(24) DEFAULT 'pending' NOT NULL,
	attempts integer DEFAULT 0 NOT NULL,
	first_failure datetime,
	next_attempt datetime,
	started_at datetime,
	sent_at datetime,
	last_error varchar(128),
	lock_token varchar(64),
	date_creation datetime NOT NULL,
	fk_user_creat integer NOT NULL
) ENGINE=innodb;
