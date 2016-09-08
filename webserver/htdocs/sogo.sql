DROP VIEW IF EXISTS grouped_mail_aliases;
DROP VIEW IF EXISTS grouped_sender_acl;
DROP VIEW IF EXISTS sogo_view;

CREATE VIEW grouped_mail_aliases (username, aliases) AS
SELECT goto, IFNULL(GROUP_CONCAT(address SEPARATOR ' '), '') AS address FROM alias
WHERE address!=goto
AND active = '1'
AND address NOT LIKE '@%'
GROUP BY goto;

CREATE VIEW grouped_sender_acl (username, send_as) AS
SELECT logged_in_as, IFNULL(GROUP_CONCAT(send_as SEPARATOR ' '), '') AS send_as FROM sender_acl
WHERE send_as NOT LIKE '@%'
GROUP BY logged_in_as;

CREATE VIEW sogo_view (c_uid, c_name, c_password, c_cn, mail, aliases, senderacl, home) AS
SELECT mailbox.username, mailbox.username, mailbox.password, CONVERT(mailbox.name USING latin1), mailbox.username, IFNULL(ga.aliases, ''), IFNULL(gs.send_as, ''), CONCAT('/var/vmail/', maildir)
FROM mailbox
LEFT OUTER JOIN grouped_mail_aliases ga ON ga.username = mailbox.username
LEFT OUTER JOIN grouped_sender_acl gs ON gs.username = mailbox.username
WHERE mailbox.active = '1';
