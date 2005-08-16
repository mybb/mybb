<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $$
 */

/**
 * Upgrade Script: Preview Release 1
 */

function upgrade5_dbchanges()
{
	global $db, $output;

	$output->print_header("Update Process");
	echo "Modifying database...";
	$db->query("UPDATE ".TABLE_PREFIX."users SET salt='';");
	$db->query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (113, 'decpoint', 'Decimal Point', 'The decimal point you use in your region.', 'text', '.', 1, 1);");
	$db->query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (114, 'thousandssep', 'Thousands Numeric Separator', 'The punctuation you want to use .  (for example, the setting \',\' with the number 1200 will give you a number such as 1,200)', 'text', ',', 1, 1);");
	$db->query("INSERT INTO ".TABLE_PREFIX."settings (sid, name, title, description, optionscode, value, disporder, gid) VALUES (115, 'showvernum', 'Show Version Numbers', 'Allows you to turn off the public display of version numbers in MyBB.', 'onoff', 'off', 1, 1);");

	$db->query("ALTER TABLE ".TABLE_PREFIX."users ADD loginkey varchar(50) NOT NULL AFTER salt;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."threads ADD firstpost int unsigned NOT NULL default '0' AFTER dateline;");
	$db->query("DROP TABLE ".TABLE_PREFIX."online;");
	$db->query("CREATE TABLE ".TABLE_PREFIX."sessions (
		  sid varchar(32) NOT NULL default '',
		  uid int unsigned NOT NULL default '0',
		  ip varchar(40) NOT NULL default '',
		  time bigint(30) NOT NULL default '0',
		  location varchar(150) NOT NULL default '',
		  useragent varchar(100) NOT NULL default '',
		  anonymous int(1) NOT NULL default '0',
		  nopermission int(1) NOT NULL default '0',
		  location1 int(10) NOT NULL default '0',
		  location2 int(10) NOT NULL default '0',
		  PRIMARY KEY(sid),
		  KEY location1 (location1),
		  KEY location2 (location2)
		);");
	$db->query("CREATE TABLE ".TABLE_PREFIX."datacache (
	  title varchar(30) NOT NULL default '',
	  cache mediumtext NOT NULL,
	  PRIMARY KEY(title)
	) TYPE=MyISAM;");

	$db->query("ALTER TABLE ".TABLE_PREFIX."helpdocs ADD usetranslation CHAR( 3 ) NOT NULL AFTER document;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."helpdocs ADD enabled CHAR( 3 ) NOT NULL AFTER usetranslation;");
	$db->query("UPDATE ".TABLE_PREFIX."helpdocs SET hid='6' WHERE hid='7'");
	$db->query("UPDATE ".TABLE_PREFIX."helpdocs SET hid='7' WHERE hid='8'");
	
	$db->query("ALTER TABLE ".TABLE_PREFIX."helpsections ADD usetranslation CHAR( 3 ) NOT NULL AFTER description;");
	$db->query("ALTER TABLE ".TABLE_PREFIX."helpsections ADD enabled CHAR( 3 ) NOT NULL AFTER usetranslation;");
	
	echo "Done. Click Next.";
	$output->print_footer("5_dbchanges2");
}

function upgrade5_dbchanges2()
{
	global $db, $output;

	$output->print_header("More Database Modifications");
	echo "Be patient, this page could take a while to complete depending on the size of your forums...";

$db->query("ALTER TABLE ".TABLE_PREFIX."adminlog CHANGE uid uid int unsigned NOT NULL;");
	
$db->query("ALTER TABLE ".TABLE_PREFIX."adminoptions CHANGE uid uid int(10) NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."announcements CHANGE aid aid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."announcements CHANGE fid fid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."announcements CHANGE uid uid int unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."attachments CHANGE aid aid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."attachments CHANGE uid uid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."attachments CHANGE visible visible int(1) NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."attachments CHANGE downloads downloads int unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."attachtypes CHANGE atid atid int unsigned NOT NULL auto_increment;");

$db->query("ALTER TABLE ".TABLE_PREFIX."awaitingactivation CHANGE aid aid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."awaitingactivation CHANGE uid uid int unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."badwords CHANGE bid bid int unsigned NOT NULL auto_increment;");

$db->query("ALTER TABLE ".TABLE_PREFIX."banned CHANGE uid uid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."banned CHANGE gid gid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."banned CHANGE oldgroup oldgroup int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."banned CHANGE admin admin int unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."events CHANGE eid eid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."events CHANGE author author int unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."favorites CHANGE fid fid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."favorites CHANGE uid uid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."favorites CHANGE tid tid int unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."forumpermissions CHANGE pid pid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."forumpermissions CHANGE fid fid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."forumpermissions CHANGE gid gid int unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."forums CHANGE fid fid smallint unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."forums CHANGE pid pid smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."forums CHANGE disporder disporder smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."forums CHANGE threads threads int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."forums CHANGE posts posts int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."forums CHANGE style style smallint unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."forumsubscriptions CHANGE fsid fsid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."forumsubscriptions CHANGE fid fid smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."forumsubscriptions CHANGE uid uid int unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."groupleaders CHANGE lid lid smallint unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."groupleaders CHANGE gid gid smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."groupleaders CHANGE uid uid int unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."helpdocs CHANGE hid hid smallint unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."helpdocs CHANGE sid sid smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."helpdocs CHANGE disporder disporder smallint unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."helpsections CHANGE sid sid smallint unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."helpsections CHANGE disporder disporder smallint unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."icons CHANGE iid iid smallint unsigned NOT NULL auto_increment;");

$db->query("ALTER TABLE ".TABLE_PREFIX."joinrequests CHANGE rid rid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."joinrequests CHANGE uid uid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."joinrequests CHANGE gid gid smallint unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."moderatorlog CHANGE uid uid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."moderatorlog CHANGE fid fid smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."moderatorlog CHANGE tid tid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."moderatorlog CHANGE pid pid int unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."moderators CHANGE mid mid smallint unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."moderators CHANGE fid fid smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."moderators CHANGE uid uid int unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."polls CHANGE pid pid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."polls CHANGE tid tid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."polls CHANGE numoptions numoptions smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."polls CHANGE numvotes numvotes smallint unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."pollvotes CHANGE vid vid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."pollvotes CHANGE pid pid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."pollvotes CHANGE uid uid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."pollvotes CHANGE voteoption voteoption smallint unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE pid pid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE tid tid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE replyto replyto int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE fid fid smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE icon icon smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE uid uid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE edituid edituid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."posts CHANGE visible visible int(1) NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."privatemessages CHANGE pmid pmid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."privatemessages CHANGE uid uid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."privatemessages CHANGE toid toid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."privatemessages CHANGE fromid fromid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."privatemessages CHANGE folder folder smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."privatemessages CHANGE icon icon smallint unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."profilefields CHANGE fid fid smallint unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."profilefields CHANGE disporder disporder smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."profilefields CHANGE length length smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."profilefields CHANGE maxlength maxlength smallint unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."reportedposts CHANGE rid rid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."reportedposts CHANGE pid pid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."reportedposts CHANGE tid tid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."reportedposts CHANGE fid fid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."reportedposts CHANGE uid uid int unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."reputation CHANGE uid uid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."reputation CHANGE pid pid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."reputation CHANGE adduid adduid int unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."searchlog CHANGE sid sid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."searchlog CHANGE uid uid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."searchlog CHANGE limitto limitto smallint(4) NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."settinggroups CHANGE gid gid smallint unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."settinggroups CHANGE disporder disporder smallint unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."settings CHANGE sid sid smallint unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."settings CHANGE disporder disporder smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."settings CHANGE gid gid smallint unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."smilies CHANGE sid sid smallint unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."smilies CHANGE disporder disporder smallint unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."templates CHANGE tid tid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."templates CHANGE sid sid int(10) NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."templatesets CHANGE sid sid smallint unsigned NOT NULL auto_increment;");

$db->query("ALTER TABLE ".TABLE_PREFIX."themes CHANGE tid tid smallint unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."themes CHANGE pid pid smallint unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."threadratings CHANGE rid rid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."threadratings CHANGE tid tid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."threadratings CHANGE uid uid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."threadratings CHANGE rating rating smallint unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE tid tid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE fid fid smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE icon icon smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE poll poll int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE uid uid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE replies replies int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE views views int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE sticky sticky int(1) NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE numratings numratings smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE totalratings totalratings smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."threads CHANGE visible visible int(1) NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."threadsread CHANGE tid tid int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."threadsread CHANGE uid uid int unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."userfields CHANGE ufid ufid int unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups CHANGE gid gid smallint unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups CHANGE stars stars smallint(4) NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."users CHANGE uid uid int unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."users CHANGE usergroup usergroup smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."users CHANGE displaygroup displaygroup smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."users CHANGE style style smallint unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."users CHANGE referrer referrer int unsigned NOT NULL;");

$db->query("ALTER TABLE ".TABLE_PREFIX."usertitles CHANGE utid utid smallint unsigned NOT NULL auto_increment;");
$db->query("ALTER TABLE ".TABLE_PREFIX."usertitles CHANGE posts posts int unsigned NOT NULL;");
$db->query("ALTER TABLE ".TABLE_PREFIX."usertitles CHANGE stars stars smallint(4) NOT NULL;");

	echo "Done";
	echo "<br /><br />Click Next.";
	echo "<p><font color=\red\"><b>WARNING:</font> The next step will delete any custom themes or templates you have! Please back them up before continuing!</p>";
	$output->print_footer("templates");
}


?>