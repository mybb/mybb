<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */

$tables[] = "CREATE TABLE mybb_adminlog (
  uid int unsigned NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  scriptname varchar(50) NOT NULL default '',
  action varchar(50) NOT NULL default '',
  querystring varchar(150) NOT NULL default '',
  ipaddress varchar(50) NOT NULL default '',
  KEY scriptname (scriptname, action)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_adminoptions (
  uid int(10) NOT NULL default '0',
  cpstyle varchar(50) NOT NULL default '',
  notes text NOT NULL,
  permissions text NOT NULL,
  PRIMARY KEY  (uid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_adminsessions (
	sid varchar(32) NOT NULL default '',
	uid int unsigned NOT NULL default '0',
	loginkey varchar(50) NOT NULL default '',
	ip varchar(40) NOT NULL default '',
	dateline bigint(30) NOT NULL default '0',
	lastactive bigint(30) NOT NULL default '0'
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_announcements (
  aid int unsigned NOT NULL auto_increment,
  fid int(10) NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  subject varchar(120) NOT NULL default '',
  message text NOT NULL,
  startdate bigint(30) NOT NULL default '0',
  enddate bigint(30) NOT NULL default '0',
  allowhtml char(3) NOT NULL default '',
  allowmycode char(3) NOT NULL default '',
  allowsmilies char(3) NOT NULL default '',
  KEY fid (fid),
  PRIMARY KEY  (aid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_attachments (
  aid int unsigned NOT NULL auto_increment,
  pid int(10) NOT NULL default '0',
  posthash varchar(50) NOT NULL default '',
  uid int unsigned NOT NULL default '0',
  filename varchar(120) NOT NULL default '',
  filetype varchar(120) NOT NULL default '',
  filesize int(10) NOT NULL default '0',
  attachname varchar(120) NOT NULL default '',
  downloads int unsigned NOT NULL default '0',
  visible int(1) NOT NULL default '0',
  thumbnail varchar(120) NOT NULL default '',
  KEY posthash (posthash),
  KEY pid (pid, visible),
  KEY uid (uid),
  PRIMARY KEY  (aid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_attachtypes (
  atid int unsigned NOT NULL auto_increment,
  name varchar(120) NOT NULL default '',
  mimetype varchar(120) NOT NULL default '',
  extension varchar(10) NOT NULL default '',
  maxsize int(15) NOT NULL default '0',
  icon varchar(100) NOT NULL default '',
  PRIMARY KEY  (atid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_awaitingactivation (
  aid int unsigned NOT NULL auto_increment,
  uid int unsigned NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  code varchar(100) NOT NULL default '',
  type char(1) NOT NULL default '',
  oldgroup bigint(30) NOT NULL default '0',
  misc varchar(255) NOT NULL default '',
  PRIMARY KEY  (aid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_badwords (
  bid int unsigned NOT NULL auto_increment,
  badword varchar(100) NOT NULL default '',
  replacement varchar(100) NOT NULL default '',
  PRIMARY KEY  (bid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_banfilters (
  fid int unsigned NOT NULL auto_increment,
  filter varchar(200) NOT NULL default '',
  type int(1) NOT NULL default '0',
  lastuse bigint(30) NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  PRIMARY KEY  (fid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_banned (
  uid int unsigned NOT NULL default '0',
  gid int unsigned NOT NULL default '0',
  oldgroup int unsigned NOT NULL default '0',
  oldadditionalgroups text NOT NULL,
  olddisplaygroup int NOT NULL default '0',
  admin int unsigned NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  bantime varchar(50) NOT NULL default '',
  lifted bigint(30) NOT NULL default '0',
  reason varchar(255) NOT NULL default '',
  KEY uid (uid),
  KEY dateline (dateline)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_calendars (
  cid int unsigned NOT NULL auto_increment,
  name varchar(100) NOT NULL default '',
  disporder int unsigned NOT NULL default '0',
  startofweek int(1) NOT NULL default '0',
  showbirthdays int(1) NOT NULL default '0',
  eventlimit int(3) NOT NULL default '0',
  moderation int(1) NOT NULL default '0',
  allowhtml char(3) NOT NULL default '',
  allowmycode char(3) NOT NULL default '',
  allowimgcode char(3) NOT NULL default '',
  allowsmilies char(3) NOT NULL default '',
  PRIMARY KEY(cid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_calendarpermissions (
  cid int unsigned NOT NULL default '0',
  gid int unsigned NOT NULL default '0',
  canviewcalendar char(3) NOT NULL default '',
  canaddevents char(3) NOT NULL default '',
  canbypasseventmod char(3) NOT NULL default '',
  canmoderateevents char(3) NOT NULL default ''
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_captcha (
  imagehash varchar(32) NOT NULL default '',
  imagestring varchar(8) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0',
  KEY imagehash (imagehash),
  KEY dateline (dateline)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_datacache (
  title varchar(50) NOT NULL default '',
  cache mediumtext NOT NULL,
  PRIMARY KEY(title)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_events (
  eid int unsigned NOT NULL auto_increment,
  cid int unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  name varchar(120) NOT NULL default '',
  description text NOT NULL,
  visible int(1) NOT NULL default '0',
  private int(1) NOT NULL default '0',
  dateline int(10) unsigned NOT NULL default '0',
  starttime int(10) unsigned NOT NULL default '0',
  endtime int(10) unsigned NOT NULL default '0',
  timezone int(3) NOT NULL default '0',
  ignoretimezone int(1) NOT NULL default '0',
  usingtime int(1) NOT NULL default '0',
  repeats text NOT NULL,
  KEY daterange (starttime, endtime),
  KEY private (private),
  PRIMARY KEY  (eid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_forumpermissions (
  pid int unsigned NOT NULL auto_increment,
  fid int unsigned NOT NULL default '0',
  gid int unsigned NOT NULL default '0',
  canview char(3) NOT NULL default '',
  canviewthreads char(3) NOT NULL default '',
  candlattachments char(3) NOT NULL default '',
  canpostthreads char(3) NOT NULL default '',
  canpostreplys char(3) NOT NULL default '',
  canpostattachments char(3) NOT NULL default '',
  canratethreads char(3) NOT NULL default '',
  caneditposts char(3) NOT NULL default '',
  candeleteposts char(3) NOT NULL default '',
  candeletethreads char(3) NOT NULL default '',
  caneditattachments char(3) NOT NULL default '',
  canpostpolls char(3) NOT NULL default '',
  canvotepolls char(3) NOT NULL default '',
  cansearch char(3) NOT NULL default '',
  PRIMARY KEY  (pid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_forums (
  fid smallint unsigned NOT NULL auto_increment,
  name varchar(120) NOT NULL default '',
  description text NOT NULL,
  linkto varchar(180) NOT NULL default '',
  type char(1) NOT NULL default '',
  pid smallint unsigned NOT NULL default '0',
  parentlist text NOT NULL,
  disporder smallint unsigned NOT NULL default '0',
  active char(3) NOT NULL default '',
  open char(3) NOT NULL default '',
  threads int unsigned NOT NULL default '0',
  posts int unsigned NOT NULL default '0',
  lastpost int(10) unsigned NOT NULL default '0',
  lastposter varchar(120) NOT NULL default '',
  lastposteruid int(10) unsigned NOT NULL default '0',
  lastposttid int(10) NOT NULL default '0',
  lastpostsubject varchar(120) NOT NULL default '',
  allowhtml char(3) NOT NULL default '',
  allowmycode char(3) NOT NULL default '',
  allowsmilies char(3) NOT NULL default '',
  allowimgcode char(3) NOT NULL default '',
  allowpicons char(3) NOT NULL default '',
  allowtratings char(3) NOT NULL default '',
  status int(4) NOT NULL default '1',
  usepostcounts char(3) NOT NULL default '',
  password varchar(50) NOT NULL default '',
  showinjump char(3) NOT NULL default '',
  modposts char(3) NOT NULL default '',
  modthreads char(3) NOT NULL default '',
  mod_edit_posts char(3) NOT NULL default '',
  modattachments char(3) NOT NULL default '',
  style smallint unsigned NOT NULL default '0',
  overridestyle char(3) NOT NULL default '',
  rulestype smallint(1) NOT NULL default '0',
  rulestitle varchar(200) NOT NULL default '',
  rules text NOT NULL,
  unapprovedthreads int(10) unsigned NOT NULL default '0',
  unapprovedposts int(10) unsigned NOT NULL default '0',
  defaultdatecut smallint(4) unsigned NOT NULL default '0',
  defaultsortby varchar(10) NOT NULL default '',
  defaultsortorder varchar(4) NOT NULL default '',
  PRIMARY KEY  (fid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_forumsread (
  fid int unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  dateline int(10) NOT NULL default '0',
  KEY dateline (dateline),
  UNIQUE KEY fid (fid,uid)
) TYPE=MyISAM;";


$tables[] = "CREATE TABLE mybb_forumsubscriptions (
  fsid int unsigned NOT NULL auto_increment,
  fid smallint unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  PRIMARY KEY  (fsid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_groupleaders (
  lid smallint unsigned NOT NULL auto_increment,
  gid smallint unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  canmanagemembers char(3) NOT NULL default '',
  canmanagerequests char(3) NOT NULL default '',
  PRIMARY KEY  (lid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_helpdocs (
  hid smallint unsigned NOT NULL auto_increment,
  sid smallint unsigned NOT NULL default '0',
  name varchar(120) NOT NULL default '',
  description text NOT NULL,
  document text NOT NULL,
  usetranslation char(3) NOT NULL default '',
  enabled char(3) NOT NULL default '',
  disporder smallint unsigned NOT NULL default '0',
  PRIMARY KEY  (hid)
) TYPE=MyISAM;";


$tables[] = "CREATE TABLE mybb_helpsections (
  sid smallint unsigned NOT NULL auto_increment,
  name varchar(120) NOT NULL default '',
  description text NOT NULL,
  usetranslation char(3) NOT NULL default '',
  enabled char(3) NOT NULL default '',
  disporder smallint unsigned NOT NULL default '0',
  PRIMARY KEY  (sid)
) TYPE=MyISAM;";


$tables[] = "CREATE TABLE mybb_icons (
  iid smallint unsigned NOT NULL auto_increment,
  name varchar(120) NOT NULL default '',
  path varchar(220) NOT NULL default '',
  PRIMARY KEY  (iid)
) TYPE=MyISAM;";


$tables[] = "CREATE TABLE mybb_joinrequests (
  rid int unsigned NOT NULL auto_increment,
  uid int unsigned NOT NULL default '0',
  gid smallint unsigned NOT NULL default '0',
  reason varchar(250) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0',
  PRIMARY KEY  (rid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_mailerrors (
  eid int unsigned NOT NULL auto_increment,
  subject varchar(200) NOT NULL default '',
  message text NOT NULL,
  toaddress varchar(150) NOT NULL default '',
  fromaddress varchar(150) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0',
  error text NOT NULL,
  smtperror varchar(200) NOT NULL default '',
  smtpcode int(5) NOT NULL default '0',
  PRIMARY KEY(eid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_maillogs (
	mid int unsigned NOT NULL auto_increment,
	subject varchar(200) not null default '',
	message text NOT NULL,
	dateline bigint(30) NOT NULL default '0',
	fromuid int unsigned NOT NULL default '0',
	fromemail varchar(200) not null default '',
	touid bigint(30) NOT NULL default '0',
	toemail varchar(200) NOT NULL default '',
	tid int unsigned NOT NULL default '0',
	ipaddress varchar(20) NOT NULL default '',
	PRIMARY KEY(mid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_mailqueue (
	mid int unsigned NOT NULL auto_increment,
	mailto varchar(200) NOT NULL,
	mailfrom varchar(200) NOT NULL,
	subject varchar(200) NOT NULL,
	message text NOT NULL,
	headers text NOT NULL,
	PRIMARY KEY(mid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_moderatorlog (
  uid int unsigned NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  fid smallint unsigned NOT NULL default '0',
  tid int unsigned NOT NULL default '0',
  pid int unsigned NOT NULL default '0',
  action text NOT NULL,
  data text NOT NULL,
  ipaddress varchar(50) NOT NULL default '',
  KEY tid (tid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_moderators (
  mid smallint unsigned NOT NULL auto_increment,
  fid smallint unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  caneditposts char(3) NOT NULL default '',
  candeleteposts char(3) NOT NULL default '',
  canviewips char(3) NOT NULL default '',
  canopenclosethreads char(3) NOT NULL default '',
  canmanagethreads char(3) NOT NULL default '',
  canmovetononmodforum char(3) NOT NULL default '',
  KEY uid (uid, fid),
  PRIMARY KEY  (mid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_modtools (
	tid smallint unsigned NOT NULL auto_increment,
	name varchar(200) NOT NULL,
	description text NOT NULL,
	forums text NOT NULL,
	type char(1) NOT NULL default '',
	postoptions text NOT NULL,
	threadoptions text NOT NULL,
	PRIMARY KEY (tid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_mycode (
  cid int unsigned NOT NULL auto_increment,
  title varchar(100) NOT NULL default '',
  description text NOT NULL,
  regex text NOT NULL,
  replacement text NOT NULL,
  active char(3) NOT NULL default '',
  parseorder smallint unsigned NOT NULL default '0',
  PRIMARY KEY(cid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_polls (
  pid int unsigned NOT NULL auto_increment,
  tid int unsigned NOT NULL default '0',
  question varchar(200) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0',
  options text NOT NULL,
  votes text NOT NULL,
  numoptions smallint unsigned NOT NULL default '0',
  numvotes smallint unsigned NOT NULL default '0',
  timeout bigint(30) NOT NULL default '0',
  closed char(3) NOT NULL default '',
  multiple char(3) NOT NULL default '',
  public char(3) NOT NULL default '',
  PRIMARY KEY  (pid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_pollvotes (
  vid int unsigned NOT NULL auto_increment,
  pid int unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  voteoption smallint unsigned NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  KEY pid (pid, uid),
  PRIMARY KEY  (vid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_posts (
  pid int unsigned NOT NULL auto_increment,
  tid int unsigned NOT NULL default '0',
  replyto int unsigned NOT NULL default '0',
  fid smallint unsigned NOT NULL default '0',
  subject varchar(120) NOT NULL default '',
  icon smallint unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  username varchar(80) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0',
  message text NOT NULL,
  ipaddress varchar(30) NOT NULL default '',
  includesig char(3) NOT NULL default '',
  smilieoff char(3) NOT NULL default '',
  edituid int unsigned NOT NULL default '0',
  edittime int(10) NOT NULL default '0',
  visible int(1) NOT NULL default '0',
  posthash varchar(32) NOT NULL default '',
  KEY tid (tid, uid),
  KEY uid (uid),
  KEY visible (visible),
  KEY dateline (dateline),
  PRIMARY KEY  (pid)
) TYPE=MyISAM;";


$tables[] = "CREATE TABLE mybb_privatemessages (
  pmid int unsigned NOT NULL auto_increment,
  uid int unsigned NOT NULL default '0',
  toid int unsigned NOT NULL default '0',
  fromid int unsigned NOT NULL default '0',
  recipients text NOT NULL,
  folder smallint unsigned NOT NULL default '1',
  subject varchar(120) NOT NULL default '',
  icon smallint unsigned NOT NULL default '0',
  message text NOT NULL,
  dateline bigint(30) NOT NULL default '0',
  deletetime bigint(30) NOT NULL default '0',
  status int(1) NOT NULL default '0',
  statustime bigint(30) NOT NULL default '0',
  includesig char(3) NOT NULL default '',
  smilieoff char(3) NOT NULL default '',
  receipt int(1) NOT NULL default '0',
  readtime bigint(30) NOT NULL default '0',
  KEY pmid (pmid),
  KEY uid (uid, folder),
  PRIMARY KEY  (pmid)
) TYPE=MyISAM;";


$tables[] = "CREATE TABLE mybb_profilefields (
  fid smallint unsigned NOT NULL auto_increment,
  name varchar(100) NOT NULL default '',
  description text NOT NULL,
  disporder smallint unsigned NOT NULL default '0',
  type text NOT NULL,
  length smallint unsigned NOT NULL default '0',
  maxlength smallint unsigned NOT NULL default '0',
  required char(3) NOT NULL default '',
  editable char(3) NOT NULL default '',
  hidden char(3) NOT NULL default '',
  PRIMARY KEY  (fid)
) TYPE=MyISAM;";


$tables[] = "CREATE TABLE mybb_promotions (
  pid int unsigned NOT NULL auto_increment,
  title varchar(120) NOT NULL default '',
  description text NOT NULL,
  enabled tinyint(1) NOT NULL default '1',
  logging tinyint(1) NOT NULL default '0',
  posts int NOT NULL default '0',
  posttype char(2) NOT NULL default '',
  registered int NOT NULL default '0',
  registeredtype varchar(20) NOT NULL default '',
  reputations int NOT NULL default '0',
  reputationtype char(2) NOT NULL default '',
  requirements varchar(50) NOT NULL default '',
  originalusergroup varchar(120) NOT NULL default '0',
  newusergroup smallint unsigned NOT NULL default '0',
  usergrouptype varchar(120) NOT NULL default '0',
  PRIMARY KEY(pid)
) TYPE=MyISAM;";
	
$tables[] = "CREATE TABLE mybb_promotionlogs (
  plid int unsigned NOT NULL auto_increment,
  pid int unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  oldusergroup smallint unsigned NOT NULL default '0',
  newusergroup smallint unsigned NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  PRIMARY KEY(plid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_reportedposts (
  rid int unsigned NOT NULL auto_increment,
  pid int unsigned NOT NULL default '0',
  tid int unsigned NOT NULL default '0',
  fid int unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  reportstatus int(1) NOT NULL default '0',
  reason varchar(250) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0',
  KEY fid (fid),
  KEY dateline (dateline),
  PRIMARY KEY  (rid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_reputation (
  rid int unsigned NOT NULL auto_increment,
  uid int unsigned NOT NULL default '0',
  adduid int unsigned NOT NULL default '0',
  reputation bigint(30) NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  comments text NOT NULL,
  KEY uid (uid),
  KEY dateline (dateline),
  PRIMARY KEY(rid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_searchlog (
  sid varchar(32) NOT NULL default '',
  uid int unsigned NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  ipaddress varchar(120) NOT NULL default '',
  threads text NOT NULL,
  posts text NOT NULL,
  searchtype varchar(10) NOT NULL default '',
  resulttype varchar(10) NOT NULL default '',
  querycache text NOT NULL,
  keywords text NOT NULL,
  PRIMARY KEY  (sid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_sessions (
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
  loginattempts tinyint(2) NOT NULL default '1',
  failedlogin bigint(30) NOT NULL default '0',
  PRIMARY KEY(sid),
  KEY location1 (location1),
  KEY location2 (location2),
  KEY time (time),
  KEY uid (uid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_settinggroups (
  gid smallint unsigned NOT NULL auto_increment,
  name varchar(100) NOT NULL default '',
  title varchar(220) NOT NULL default '',
  description text NOT NULL,
  disporder smallint unsigned NOT NULL default '0',
  isdefault char(3) NOT NULL default '',
  PRIMARY KEY  (gid)
) TYPE=MyISAM;";


$tables[] = "CREATE TABLE mybb_settings (
  sid smallint unsigned NOT NULL auto_increment,
  name varchar(120) NOT NULL default '',
  title varchar(120) NOT NULL default '',
  description text NOT NULL,
  optionscode text NOT NULL,
  value text NOT NULL,
  disporder smallint unsigned NOT NULL default '0',
  gid smallint unsigned NOT NULL default '0',
  PRIMARY KEY  (sid)
) TYPE=MyISAM;";


$tables[] = "CREATE TABLE mybb_smilies (
  sid smallint unsigned NOT NULL auto_increment,
  name varchar(120) NOT NULL default '',
  find varchar(120) NOT NULL default '',
  image varchar(220) NOT NULL default '',
  disporder smallint unsigned NOT NULL default '0',
  showclickable char(3) NOT NULL default '',
  PRIMARY KEY  (sid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_spiders (
	sid int unsigned NOT NULL auto_increment,
	name varchar(100) NOT NULL default '',
	theme int unsigned NOT NULL default '0',
	language varchar(20) NOT NULL default '',
	usergroup int unsigned NOT NULL default '0',
	useragent varchar(200) NOT NULL default '',
	lastvisit bigint(30) NOT NULL default '0',
	PRIMARY KEY(sid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_stats (
	dateline bigint(30) NOT NULL default '0',
	numusers int unsigned NOT NULL default '0',
	numthreads int unsigned NOT NULL default '0',
	numposts int unsigned NOT NULL default '0',
	PRIMARY KEY(dateline)
) TYPE=MyISAM;";
	
$tables[] = "CREATE TABLE mybb_tasks (
	tid int unsigned NOT NULL auto_increment,
	title varchar(120) NOT NULL default '',
	description text NOT NULL,
	file varchar(30) NOT NULL default '',
	minute varchar(200) NOT NULL default '',
	hour varchar(200) NOT NULL default '',
	day varchar(100) NOT NULL default '',
	month varchar(30) NOT NULL default '',
	weekday varchar(15) NOT NULL default '',
	nextrun bigint(30) NOT NULL default '0',
	lastrun bigint(30) NOT NULL default '0',
	enabled int(1) NOT NULL default '1',
	logging int(1) NOT NULL default '0',
	locked bigint(30) NOT NULL default '0',
	PRIMARY KEY(tid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_tasklog (
	lid int unsigned NOT NULL auto_increment,
	tid int unsigned NOT NULL default '0',
	dateline bigint(30) NOT NULL default '0',
	data text NOT NULL,
	PRIMARY KEY(lid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_templategroups (
  gid int unsigned NOT NULL auto_increment,
  prefix varchar(50) NOT NULL default '',
  title varchar(100) NOT NULL default '',
  PRIMARY KEY (gid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_templates (
  tid int unsigned NOT NULL auto_increment,
  title varchar(120) NOT NULL default '',
  template text NOT NULL,
  sid int(10) NOT NULL default '0',
  version varchar(20) NOT NULL default '0',
  status varchar(10) NOT NULL default '',
  dateline int(10) NOT NULL default '0',
  PRIMARY KEY  (tid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_templatesets (
  sid smallint unsigned NOT NULL auto_increment,
  title varchar(120) NOT NULL default '',
  PRIMARY KEY  (sid)
) TYPE=MyISAM;";


$tables[] = "CREATE TABLE mybb_themes (
  tid smallint unsigned NOT NULL auto_increment,
  name varchar(100) NOT NULL default '',
  pid smallint unsigned NOT NULL default '0',
  def smallint(1) NOT NULL default '0',
  css text NOT NULL,
  cssbits text NOT NULL,
  themebits text NOT NULL,
  extracss text NOT NULL,
  allowedgroups text NOT NULL,
  csscached bigint(30) NOT NULL default '0',
  PRIMARY KEY  (tid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_threadratings (
  rid int unsigned NOT NULL auto_increment,
  tid int unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  rating smallint unsigned NOT NULL default '0',
  ipaddress varchar(30) NOT NULL default '',
  KEY tid (tid, uid),
  PRIMARY KEY  (rid)
) TYPE=MyISAM;";


$tables[] = "CREATE TABLE mybb_threads (
  tid int unsigned NOT NULL auto_increment,
  fid smallint unsigned NOT NULL default '0',
  subject varchar(120) NOT NULL default '',
  icon smallint unsigned NOT NULL default '0',
  poll int unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  username varchar(80) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0',
  firstpost int unsigned NOT NULL default '0',
  lastpost bigint(30) NOT NULL default '0',
  lastposter varchar(120) NOT NULL default '',
  lastposteruid int unsigned NOT NULL default '0',
  views int(100) NOT NULL default '0',
  replies int(100) NOT NULL default '0',
  closed varchar(30) NOT NULL default '',
  sticky int(1) NOT NULL default '0',
  numratings smallint unsigned NOT NULL default '0',
  totalratings smallint unsigned NOT NULL default '0',
  notes text NOT NULL,
  visible int(1) NOT NULL default '0',
  unapprovedposts int(10) unsigned NOT NULL default '0',
  attachmentcount int(10) unsigned NOT NULL default '0',
  deletetime int(10) unsigned NOT NULL default '0',
  KEY fid (fid, visible, sticky),
  KEY dateline (dateline),
  KEY lastpost (lastpost, fid),
  KEY firstpost (firstpost),
  KEY uid (uid),
  PRIMARY KEY  (tid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_threadsread (
  tid int unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  dateline int(10) NOT NULL default '0',
  KEY dateline (dateline),
  UNIQUE KEY tid (tid,uid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_threadsubscriptions (
  sid int unsigned NOT NULL auto_increment,
  uid int unsigned NOT NULL default '0',
  tid int unsigned NOT NULL default '0',
  notification int(1) NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  subscriptionkey varchar(32) NOT NULL default '',
  KEY uid (uid),
  KEY tid (tid,notification),
  PRIMARY KEY  (sid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_userfields (
  ufid int unsigned NOT NULL default '0',
  fid1 text NOT NULL,
  fid2 text NOT NULL,
  fid3 text NOT NULL,
  PRIMARY KEY (ufid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_usergroups (
  gid smallint unsigned NOT NULL auto_increment,
  type smallint(2) NOT NULL default '2',
  title varchar(120) NOT NULL default '',
  description text NOT NULL,
  namestyle varchar(200) NOT NULL default '{username}',
  usertitle varchar(120) NOT NULL default '',
  stars smallint(4) NOT NULL default '0',
  starimage varchar(120) NOT NULL default '',
  image varchar(120) NOT NULL default '',
  disporder smallint(6) unsigned NOT NULL,
  isbannedgroup char(3) NOT NULL default '',
  canview char(3) NOT NULL default '',
  canviewthreads char(3) NOT NULL default '',
  canviewprofiles char(3) NOT NULL default '',
  candlattachments char(3) NOT NULL default '',
  canpostthreads char(3) NOT NULL default '',
  canpostreplys char(3) NOT NULL default '',
  canpostattachments char(3) NOT NULL default '',
  canratethreads char(3) NOT NULL default '',
  caneditposts char(3) NOT NULL default '',
  candeleteposts char(3) NOT NULL default '',
  candeletethreads char(3) NOT NULL default '',
  caneditattachments char(3) NOT NULL default '',
  canpostpolls char(3) NOT NULL default '',
  canvotepolls char(3) NOT NULL default '',
  canusepms char(3) NOT NULL default '',
  cansendpms char(3) NOT NULL default '',
  cantrackpms char(3) NOT NULL default '',
  candenypmreceipts char(3) NOT NULL default '',
  pmquota int(3) NOT NULL default '0',
  maxpmrecipients int(4) NOT NULL default '5',
  cansendemail char(3) NOT NULL default '',
  maxemails int(3) NOT NULL default '5',
  canviewmemberlist char(3) NOT NULL default '',
  canviewcalendar char(3) NOT NULL default '',
  canaddevents char(3) NOT NULL default '',
  canbypasseventmod char(3) NOT NULL default '',
  canmoderateevents char(3) NOT NULL default '',
  canviewonline char(3) NOT NULL default '',
  canviewwolinvis char(3) NOT NULL default '',
  canviewonlineips char(3) NOT NULL default '',
  cancp char(3) NOT NULL default '',
  issupermod char(3) NOT NULL default '',
  cansearch char(3) NOT NULL default '',
  canusercp char(3) NOT NULL default '',
  canuploadavatars char(3) NOT NULL default '',
  canratemembers char(3) NOT NULL default '',
  canchangename char(3) NOT NULL default '',
  showforumteam char(3) NOT NULL default '',
  usereputationsystem char(3) NOT NULL default '',
  cangivereputations char(3) NOT NULL default '',
  reputationpower bigint(30) NOT NULL default '0',
  maxreputationsday bigint(30) NOT NULL default '0',
  candisplaygroup char(3) NOT NULL default '',
  attachquota bigint(30) NOT NULL default '0',
  cancustomtitle char(3) NOT NULL default '',
  canwarnusers char(3) NOT NULL default '',
  canreceivewarnings char(3) NOT NULL default '',
  maxwarningsday int(3) NOT NULL default '3',
  PRIMARY KEY  (gid)
) TYPE=MyISAM;";


$tables[] = "CREATE TABLE mybb_users (
  uid int unsigned NOT NULL auto_increment,
  username varchar(120) NOT NULL default '',
  password varchar(120) NOT NULL default '',
  salt varchar(10) NOT NULL default '',
  loginkey varchar(50) NOT NULL default '',
  email varchar(220) NOT NULL default '',
  postnum int(10) NOT NULL default '0',
  avatar varchar(200) NOT NULL default '',
  avatardimensions varchar(10) NOT NULL default '',
  avatartype varchar(10) NOT NULL default '0',
  usergroup smallint unsigned NOT NULL default '0',
  additionalgroups varchar(200) NOT NULL default '',
  displaygroup smallint unsigned NOT NULL default '0',
  usertitle varchar(250) NOT NULL default '',
  regdate bigint(30) NOT NULL default '0',
  lastactive bigint(30) NOT NULL default '0',
  lastvisit bigint(30) NOT NULL default '0',
  lastpost bigint(30) NOT NULL default '0',
  website varchar(200) NOT NULL default '',
  icq varchar(10) NOT NULL default '',
  aim varchar(50) NOT NULL default '',
  yahoo varchar(50) NOT NULL default '',
  msn varchar(75) NOT NULL default '',
  birthday varchar(15) NOT NULL default '',
  signature text NOT NULL,
  allownotices char(3) NOT NULL default '',
  hideemail char(3) NOT NULL default '',
  subscriptionmethod int(1) NOT NULL default '0',
  invisible char(3) NOT NULL default '',
  receivepms char(3) NOT NULL default '',
  pmnotice char(3) NOT NULL default '',
  pmnotify char(3) NOT NULL default '',
  remember char(3) NOT NULL default '',
  threadmode varchar(8) NOT NULL default '',
  showsigs char(3) NOT NULL default '',
  showavatars char(3) NOT NULL default '',
  showquickreply char(3) NOT NULL default '',
  showredirect char(3) NOT NULL default '',
  ppp smallint(6) NOT NULL default '0',
  tpp smallint(6) NOT NULL default '0',
  daysprune smallint(6) NOT NULL default '0',
  dateformat varchar(4) NOT NULL default '',
  timeformat varchar(4) NOT NULL default '',
  timezone varchar(4) NOT NULL default '',
  dst int(1) NOT NULL default '0',
  dstcorrection int(1) NOT NULL default '0',
  buddylist text NOT NULL,
  ignorelist text NOT NULL,
  style smallint unsigned NOT NULL default '0',
  away char(3) NOT NULL default '',
  awaydate int(10) unsigned NOT NULL default '0',
  returndate varchar(15) NOT NULL default '',
  awayreason varchar(200) NOT NULL default '',
  pmfolders text NOT NULL,
  notepad text NOT NULL,
  referrer int unsigned NOT NULL default '0',
  reputation bigint(30) NOT NULL default '0',
  regip varchar(50) NOT NULL default '',
  language varchar(50) NOT NULL default '',
  timeonline bigint(30) NOT NULL default '0',
  showcodebuttons int(1) NOT NULL default '1',
  totalpms int(10) NOT NULL default '0',
  unreadpms int(10) NOT NULL default '0',
  warningpoints int(3) NOT NULL default '0',
  moderateposts int(1) NOT NULL default '0',
  moderationtime bigint(30) NOT NULL default '0',
  suspendposting int(1) NOT NULL default '0',
  suspensiontime bigint(30) NOT NULL default '0',
  KEY username (username),
  KEY usergroup (usergroup),
  KEY birthday (birthday),
  PRIMARY KEY  (uid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_usertitles (
  utid smallint unsigned NOT NULL auto_increment,
  posts int unsigned NOT NULL default '0',
  title varchar(250) NOT NULL default '',
  stars smallint(4) NOT NULL default '0',
  starimage varchar(120) NOT NULL default '',
  PRIMARY KEY  (utid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_warninglevels (
	lid int unsigned NOT NULL auto_increment,
	percentage int(3) NOT NULL default '0',
	action text NOT NULL,
	PRIMARY KEY(lid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_warningtypes (
	tid int unsigned NOT NULL auto_increment,
	title varchar(120) NOT NULL default '',
	points int unsigned NOT NULL default '0',
	expirationtime bigint(30) NOT NULL default '0',
	PRIMARY KEY(tid)
) TYPE=MyISAM;";

$tables[] = "CREATE TABLE mybb_warnings (
	wid int unsigned NOT NULL auto_increment,
	uid int unsigned NOT NULL default '0',
	tid int unsigned NOT NULL default '0',
	pid int unsigned NOT NULL default '0',
	title varchar(120) NOT NULL default '',
	points int unsigned NOT NULL default '0',
	dateline bigint(30) NOT NULL default '0',
	issuedby int unsigned NOT NULL default '0',
	expires bigint(30) NOT NULL default '0',
	expired int(1) NOT NULL default '0',
	daterevoked bigint(30) NOT NULL default '0',
	revokedby int unsigned NOT NULL default '0',
	revokereason text NOT NULL,
	notes text NOT NULL,
	PRIMARY KEY(wid)
) TYPE=MyISAM;";

?>