CREATE TABLE IF NOT EXISTS `admin` (
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `superadmin` tinyint(1) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `modified` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `alias` (
  `address` varchar(255) NOT NULL,
  `goto` text NOT NULL,
  `domain` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `modified` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`address`),
  KEY `domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `sender_acl` (
  `logged_in_as` varchar(255) NOT NULL,
  `send_as` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `spamalias` (
  `address` varchar(255) NOT NULL,
  `goto` text NOT NULL,
  `validity` datetime NOT NULL,
  PRIMARY KEY (`address`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `alias_domain` (
  `alias_domain` varchar(255) NOT NULL,
  `target_domain` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `modified` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`alias_domain`),
  KEY `active` (`active`),
  KEY `target_domain` (`target_domain`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `domain` (
  `domain` varchar(255) NOT NULL,
  `description` varchar(255) CHARACTER SET utf8 NOT NULL,
  `aliases` int(10) NOT NULL DEFAULT '0',
  `mailboxes` int(10) NOT NULL DEFAULT '0',
  `maxquota` bigint(20) NOT NULL DEFAULT '0',
  `quota` bigint(20) NOT NULL DEFAULT '0',
  `transport` varchar(255) NOT NULL,
  `backupmx` tinyint(1) NOT NULL DEFAULT '0',
  `relay_all_recipients` tinyint(1) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `modified` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `domain_admins` (
  `username` varchar(255) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `mailbox` (
  `username` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(255) CHARACTER SET utf8 NOT NULL,
  `maildir` varchar(255) NOT NULL,
  `quota` bigint(20) NOT NULL DEFAULT '0',
  `local_part` varchar(255) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `created` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `modified` datetime NOT NULL DEFAULT '1000-01-01 00:00:00',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`username`),
  KEY `domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

CREATE TABLE IF NOT EXISTS `quota2` (
  `username` varchar(100) NOT NULL,
  `bytes` bigint(20) NOT NULL DEFAULT '0',
  `messages` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

ALTER TABLE `quota2` ENGINE=InnoDB;
ALTER TABLE `mailbox` ENGINE=InnoDB;
ALTER TABLE `domain_admins` ENGINE=InnoDB;
ALTER TABLE `domain` ENGINE=InnoDB;
ALTER TABLE `alias_domain` ENGINE=InnoDB;
ALTER TABLE `alias` ENGINE=InnoDB;
ALTER TABLE `admin` ENGINE=InnoDB;
