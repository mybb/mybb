<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

$tables[] = "CREATE TABLE mybb_adminlog (
  uid int unsigned NOT NULL default '0',
  ipaddress varbinary(16) NOT NULL default '',
  dateline int unsigned NOT NULL default '0',
  module varchar(50) NOT NULL default '',
  action varchar(50) NOT NULL default '',
  data text NOT NULL,
  KEY module (module, action),
  KEY uid (uid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_adminoptions (
  uid int NOT NULL default '0',
  cpstyle varchar(50) NOT NULL default '',
  cplanguage varchar(50) NOT NULL default '',
  codepress tinyint(1) NOT NULL default '1',
  notes text NOT NULL,
  permissions text NOT NULL,
  defaultviews text NOT NULL,
  loginattempts smallint unsigned NOT NULL default '0',
  loginlockoutexpiry int unsigned NOT NULL default '0',
  authsecret varchar(16) NOT NULL default '',
  recovery_codes varchar(177) NOT NULL default '',
  PRIMARY KEY (uid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_adminsessions (
  sid varchar(32) NOT NULL default '',
  uid int unsigned NOT NULL default '0',
  loginkey varchar(50) NOT NULL default '',
  ip varbinary(16) NOT NULL default '',
  dateline int unsigned NOT NULL default '0',
  lastactive int unsigned NOT NULL default '0',
  data TEXT NOT NULL,
  useragent varchar(200) NOT NULL default '',
  authenticated tinyint(1) NOT NULL default '0'
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_adminviews (
	vid int unsigned NOT NULL auto_increment,
	uid int unsigned NOT NULL default '0',
	title varchar(100) NOT NULL default '',
	type varchar(6) NOT NULL default '',
	visibility tinyint(1) NOT NULL default '0',
	`fields` text NOT NULL,
	conditions text NOT NULL,
	custom_profile_fields text NOT NULL,
	sortby varchar(20) NOT NULL default '',
	sortorder varchar(4) NOT NULL default '',
	perpage smallint(4) unsigned NOT NULL default '0',
	view_type varchar(6) NOT NULL default '',
	PRIMARY KEY (vid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_announcements (
  aid int unsigned NOT NULL auto_increment,
  fid int NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  subject varchar(120) NOT NULL default '',
  message text NOT NULL,
  startdate int unsigned NOT NULL default '0',
  enddate int unsigned NOT NULL default '0',
  allowhtml tinyint(1) NOT NULL default '0',
  allowmycode tinyint(1) NOT NULL default '0',
  allowsmilies tinyint(1) NOT NULL default '0',
  KEY fid (fid),
  PRIMARY KEY (aid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_attachments (
  aid int unsigned NOT NULL auto_increment,
  pid int unsigned NOT NULL default '0',
  posthash varchar(50) NOT NULL default '',
  uid int unsigned NOT NULL default '0',
  filename varchar(120) NOT NULL default '',
  filetype varchar(120) NOT NULL default '',
  filesize int(10) unsigned NOT NULL default '0',
  attachname varchar(120) NOT NULL default '',
  downloads int unsigned NOT NULL default '0',
  dateuploaded int unsigned NOT NULL default '0',
  visible tinyint(1) NOT NULL default '0',
  thumbnail varchar(120) NOT NULL default '',
  KEY pid (pid, visible),
  KEY uid (uid),
  PRIMARY KEY (aid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_attachtypes (
  atid int unsigned NOT NULL auto_increment,
  name varchar(120) NOT NULL default '',
  mimetype varchar(120) NOT NULL default '',
  extension varchar(10) NOT NULL default '',
  maxsize int(15) unsigned NOT NULL default '0',
  icon varchar(100) NOT NULL default '',
  enabled tinyint(1) NOT NULL default '1',
  `groups` TEXT NOT NULL,
  forums TEXT NOT NULL,
  avatarfile tinyint(1) NOT NULL default '0',
  PRIMARY KEY (atid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_awaitingactivation (
  aid int unsigned NOT NULL auto_increment,
  uid int unsigned NOT NULL default '0',
  dateline int unsigned NOT NULL default '0',
  code varchar(100) NOT NULL default '',
  type char(1) NOT NULL default '',
  validated tinyint(1) NOT NULL default '0',
  misc varchar(255) NOT NULL default '',
  PRIMARY KEY (aid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_badwords (
  bid int unsigned NOT NULL auto_increment,
  badword varchar(100) NOT NULL default '',
  regex tinyint(1) NOT NULL default '0',
  replacement varchar(100) NOT NULL default '',
  PRIMARY KEY (bid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_banfilters (
  fid int unsigned NOT NULL auto_increment,
  filter varchar(200) NOT NULL default '',
  type tinyint(1) NOT NULL default '0',
  lastuse int unsigned NOT NULL default '0',
  dateline int unsigned NOT NULL default '0',
  KEY (type),
  PRIMARY KEY (fid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_banned (
  uid int unsigned NOT NULL default '0',
  gid int unsigned NOT NULL default '0',
  oldgroup int unsigned NOT NULL default '0',
  oldadditionalgroups text NOT NULL,
  olddisplaygroup int unsigned NOT NULL default '0',
  admin int unsigned NOT NULL default '0',
  dateline int unsigned NOT NULL default '0',
  bantime varchar(50) NOT NULL default '',
  lifted int unsigned NOT NULL default '0',
  reason varchar(255) NOT NULL default '',
  KEY uid (uid),
  KEY dateline (dateline)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_buddyrequests (
  id int(10) unsigned NOT NULL auto_increment,
  uid int unsigned NOT NULL default '0',
  touid int unsigned NOT NULL default '0',
  date int unsigned NOT NULL default '0',
  KEY (uid),
  KEY (touid),
  PRIMARY KEY (id)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_calendars (
  cid int unsigned NOT NULL auto_increment,
  name varchar(100) NOT NULL default '',
  disporder smallint unsigned NOT NULL default '0',
  startofweek tinyint(1) NOT NULL default '0',
  showbirthdays tinyint(1) NOT NULL default '0',
  eventlimit smallint(3) unsigned NOT NULL default '0',
  moderation tinyint(1) NOT NULL default '0',
  allowhtml tinyint(1) NOT NULL default '0',
  allowmycode tinyint(1) NOT NULL default '0',
  allowimgcode tinyint(1) NOT NULL default '0',
  allowvideocode tinyint(1) NOT NULL default '0',
  allowsmilies tinyint(1) NOT NULL default '0',
  PRIMARY KEY (cid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_calendarpermissions (
  cid int unsigned NOT NULL default '0',
  gid int unsigned NOT NULL default '0',
  canviewcalendar tinyint(1) NOT NULL default '0',
  canaddevents tinyint(1) NOT NULL default '0',
  canbypasseventmod tinyint(1) NOT NULL default '0',
  canmoderateevents tinyint(1) NOT NULL default '0'
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_captcha (
  imagehash varchar(32) NOT NULL default '',
  imagestring varchar(8) NOT NULL default '',
  dateline int unsigned NOT NULL default '0',
  used tinyint(1) NOT NULL default '0',
  KEY imagehash (imagehash),
  KEY dateline (dateline)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_datacache (
  title varchar(50) NOT NULL default '',
  cache mediumtext NOT NULL,
  PRIMARY KEY (title)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_delayedmoderation (
  did int unsigned NOT NULL auto_increment,
  type varchar(30) NOT NULL default '',
  delaydateline int unsigned NOT NULL default '0',
  uid int(10) unsigned NOT NULL default '0',
  fid smallint(5) unsigned NOT NULL default '0',
  tids text NOT NULL,
  dateline int unsigned NOT NULL default '0',
  inputs text NOT NULL,
  PRIMARY KEY (did)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_events (
  eid int unsigned NOT NULL auto_increment,
  cid int unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  name varchar(120) NOT NULL default '',
  description text NOT NULL,
  visible tinyint(1) NOT NULL default '0',
  private tinyint(1) NOT NULL default '0',
  dateline int(10) unsigned NOT NULL default '0',
  starttime int(10) unsigned NOT NULL default '0',
  endtime int(10) unsigned NOT NULL default '0',
  timezone varchar(5) NOT NULL default '',
  ignoretimezone tinyint(1) NOT NULL default '0',
  usingtime tinyint(1) NOT NULL default '0',
  repeats text NOT NULL,
  KEY cid (cid),
  KEY daterange (starttime, endtime),
  KEY private (private),
  PRIMARY KEY  (eid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_forumpermissions (
  pid int unsigned NOT NULL auto_increment,
  fid int unsigned NOT NULL default '0',
  gid int unsigned NOT NULL default '0',
  canview tinyint(1) NOT NULL default '0',
  canviewthreads tinyint(1) NOT NULL default '0',
  canonlyviewownthreads tinyint(1) NOT NULL default '0',
  candlattachments tinyint(1) NOT NULL default '0',
  canpostthreads tinyint(1) NOT NULL default '0',
  canpostreplys tinyint(1) NOT NULL default '0',
  canonlyreplyownthreads tinyint(1) NOT NULL default '0',
  canpostattachments tinyint(1) NOT NULL default '0',
  canratethreads tinyint(1) NOT NULL default '0',
  caneditposts tinyint(1) NOT NULL default '0',
  candeleteposts tinyint(1) NOT NULL default '0',
  candeletethreads tinyint(1) NOT NULL default '0',
  caneditattachments tinyint(1) NOT NULL default '0',
  canviewdeletionnotice tinyint(1) NOT NULL default '0',
  modposts tinyint(1) NOT NULL default '0',
  modthreads tinyint(1) NOT NULL default '0',
  mod_edit_posts tinyint(1) NOT NULL default '0',
  modattachments tinyint(1) NOT NULL default '0',
  canpostpolls tinyint(1) NOT NULL default '0',
  canvotepolls tinyint(1) NOT NULL default '0',
  cansearch tinyint(1) NOT NULL default '0',
  KEY fid (fid, gid),
  PRIMARY KEY (pid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_forums (
  fid smallint unsigned NOT NULL auto_increment,
  name varchar(120) NOT NULL default '',
  description text NOT NULL,
  linkto varchar(180) NOT NULL default '',
  type char(1) NOT NULL default '',
  pid smallint unsigned NOT NULL default '0',
  parentlist text NOT NULL,
  disporder smallint unsigned NOT NULL default '0',
  active tinyint(1) NOT NULL default '0',
  open tinyint(1) NOT NULL default '0',
  threads int unsigned NOT NULL default '0',
  posts int unsigned NOT NULL default '0',
  lastpost int(10) unsigned NOT NULL default '0',
  lastposter varchar(120) NOT NULL default '',
  lastposteruid int(10) unsigned NOT NULL default '0',
  lastposttid int(10) unsigned NOT NULL default '0',
  lastpostsubject varchar(120) NOT NULL default '',
  allowhtml tinyint(1) NOT NULL default '0',
  allowmycode tinyint(1) NOT NULL default '0',
  allowsmilies tinyint(1) NOT NULL default '0',
  allowimgcode tinyint(1) NOT NULL default '0',
  allowvideocode tinyint(1) NOT NULL default '0',
  allowpicons tinyint(1) NOT NULL default '0',
  allowtratings tinyint(1) NOT NULL default '0',
  usepostcounts tinyint(1) NOT NULL default '0',
  usethreadcounts tinyint(1) NOT NULL default '0',
  requireprefix tinyint(1) NOT NULL default '0',
  password varchar(50) NOT NULL default '',
  showinjump tinyint(1) NOT NULL default '0',
  style smallint unsigned NOT NULL default '0',
  overridestyle tinyint(1) NOT NULL default '0',
  rulestype tinyint(1) NOT NULL default '0',
  rulestitle varchar(200) NOT NULL default '',
  rules text NOT NULL,
  unapprovedthreads int(10) unsigned NOT NULL default '0',
  unapprovedposts int(10) unsigned NOT NULL default '0',
  deletedthreads int(10) unsigned NOT NULL default '0',
  deletedposts int(10) unsigned NOT NULL default '0',
  defaultdatecut smallint(4) unsigned NOT NULL default '0',
  defaultsortby varchar(10) NOT NULL default '',
  defaultsortorder varchar(4) NOT NULL default '',
  PRIMARY KEY (fid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_forumsread (
  fid int unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  dateline int unsigned NOT NULL default '0',
  KEY dateline (dateline),
  UNIQUE KEY fid (fid, uid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_forumsubscriptions (
  fsid int unsigned NOT NULL auto_increment,
  fid smallint unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  KEY uid (uid),
  PRIMARY KEY (fsid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_groupleaders (
  lid smallint unsigned NOT NULL auto_increment,
  gid smallint unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  canmanagemembers tinyint(1) NOT NULL default '0',
  canmanagerequests tinyint(1) NOT NULL default '0',
  caninvitemembers tinyint(1) NOT NULL default '0',
  PRIMARY KEY (lid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_helpdocs (
  hid smallint unsigned NOT NULL auto_increment,
  sid smallint unsigned NOT NULL default '0',
  name varchar(120) NOT NULL default '',
  description text NOT NULL,
  document text NOT NULL,
  usetranslation tinyint(1) NOT NULL default '0',
  enabled tinyint(1) NOT NULL default '0',
  disporder smallint unsigned NOT NULL default '0',
  PRIMARY KEY (hid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_helpsections (
  sid smallint unsigned NOT NULL auto_increment,
  name varchar(120) NOT NULL default '',
  description text NOT NULL,
  usetranslation tinyint(1) NOT NULL default '0',
  enabled tinyint(1) NOT NULL default '0',
  disporder smallint unsigned NOT NULL default '0',
  PRIMARY KEY (sid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_icons (
  iid smallint unsigned NOT NULL auto_increment,
  name varchar(120) NOT NULL default '',
  path varchar(220) NOT NULL default '',
  PRIMARY KEY (iid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_joinrequests (
  rid int unsigned NOT NULL auto_increment,
  uid int unsigned NOT NULL default '0',
  gid smallint unsigned NOT NULL default '0',
  reason varchar(250) NOT NULL default '',
  dateline int unsigned NOT NULL default '0',
  invite tinyint(1) NOT NULL default '0',
  PRIMARY KEY (rid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_massemails (
	mid int unsigned NOT NULL auto_increment,
	uid int unsigned NOT NULL default '0',
	subject varchar(200) NOT NULL default '',
	message text NOT NULL,
	htmlmessage text NOT NULL,
	type tinyint(1) NOT NULL default '0',
	format tinyint(1) NOT NULL default '0',
	dateline int unsigned NOT NULL default '0',
	senddate int unsigned NOT NULL default '0',
	status tinyint(1) NOT NULL default '0',
	sentcount int unsigned NOT NULL default '0',
	totalcount int unsigned NOT NULL default '0',
	conditions text NOT NULL,
	perpage smallint(4) unsigned NOT NULL default '50',
	PRIMARY KEY (mid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_mailerrors (
  eid int unsigned NOT NULL auto_increment,
  subject varchar(200) NOT NULL default '',
  message text NOT NULL,
  toaddress varchar(150) NOT NULL default '',
  fromaddress varchar(150) NOT NULL default '',
  dateline int unsigned NOT NULL default '0',
  error text NOT NULL,
  smtperror varchar(200) NOT NULL default '',
  smtpcode smallint(5) unsigned NOT NULL default '0',
  PRIMARY KEY (eid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_maillogs (
	mid int unsigned NOT NULL auto_increment,
	subject varchar(200) not null default '',
	message text NOT NULL,
	dateline int unsigned NOT NULL default '0',
	fromuid int unsigned NOT NULL default '0',
	fromemail varchar(200) not null default '',
	touid int unsigned NOT NULL default '0',
	toemail varchar(200) NOT NULL default '',
	tid int unsigned NOT NULL default '0',
	ipaddress varbinary(16) NOT NULL default '',
	type tinyint(1) NOT NULL default '0',
	PRIMARY KEY (mid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_mailqueue (
	mid int unsigned NOT NULL auto_increment,
	mailto varchar(200) NOT NULL,
	mailfrom varchar(200) NOT NULL,
	subject varchar(200) NOT NULL,
	message text NOT NULL,
	headers text NOT NULL,
	PRIMARY KEY (mid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_moderatorlog (
  uid int unsigned NOT NULL default '0',
  dateline int unsigned NOT NULL default '0',
  fid smallint unsigned NOT NULL default '0',
  tid int unsigned NOT NULL default '0',
  pid int unsigned NOT NULL default '0',
  action text NOT NULL,
  data text NOT NULL,
  ipaddress varbinary(16) NOT NULL default '',
  KEY uid (uid),
  KEY fid (fid),
  KEY tid (tid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_moderators (
  mid smallint unsigned NOT NULL auto_increment,
  fid smallint unsigned NOT NULL default '0',
  id int unsigned NOT NULL default '0',
  isgroup tinyint(1) unsigned NOT NULL default '0',
  caneditposts tinyint(1) NOT NULL default '0',
  cansoftdeleteposts tinyint(1) NOT NULL default '0',
  canrestoreposts tinyint(1) NOT NULL default '0',
  candeleteposts tinyint(1) NOT NULL default '0',
  cansoftdeletethreads tinyint(1) NOT NULL default '0',
  canrestorethreads tinyint(1) NOT NULL default '0',
  candeletethreads tinyint(1) NOT NULL default '0',
  canviewips tinyint(1) NOT NULL default '0',
  canviewunapprove tinyint(1) NOT NULL default '0',
  canviewdeleted tinyint(1) NOT NULL default '0',
  canopenclosethreads tinyint(1) NOT NULL default '0',
  canstickunstickthreads tinyint(1) NOT NULL default '0',
  canapproveunapprovethreads tinyint(1) NOT NULL default '0',
  canapproveunapproveposts tinyint(1) NOT NULL default '0',
  canapproveunapproveattachs tinyint(1) NOT NULL default '0',
  canmanagethreads tinyint(1) NOT NULL default '0',
  canmanagepolls tinyint(1) NOT NULL default '0',
  canpostclosedthreads tinyint(1) NOT NULL default '0',
  canmovetononmodforum tinyint(1) NOT NULL default '0',
  canusecustomtools tinyint(1) NOT NULL default '0',
  canmanageannouncements tinyint(1) NOT NULL default '0',
  canmanagereportedposts tinyint(1) NOT NULL default '0',
  canviewmodlog tinyint(1) NOT NULL default '0',
  KEY uid (id, fid),
  PRIMARY KEY (mid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_modtools (
	tid smallint unsigned NOT NULL auto_increment,
	name varchar(200) NOT NULL,
	description text NOT NULL,
	forums text NOT NULL,
	`groups` text NOT NULL,
	type char(1) NOT NULL default '',
	postoptions text NOT NULL,
	threadoptions text NOT NULL,
	PRIMARY KEY (tid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_mycode (
  cid int unsigned NOT NULL auto_increment,
  title varchar(100) NOT NULL default '',
  description text NOT NULL,
  regex text NOT NULL,
  replacement text NOT NULL,
  active tinyint(1) NOT NULL default '0',
  parseorder smallint unsigned NOT NULL default '0',
  PRIMARY KEY(cid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_polls (
  pid int unsigned NOT NULL auto_increment,
  tid int unsigned NOT NULL default '0',
  question varchar(200) NOT NULL default '',
  dateline int unsigned NOT NULL default '0',
  options text NOT NULL,
  votes text NOT NULL,
  numoptions smallint unsigned NOT NULL default '0',
  numvotes int unsigned NOT NULL default '0',
  timeout int unsigned NOT NULL default '0',
  closed tinyint(1) NOT NULL default '0',
  multiple tinyint(1) NOT NULL default '0',
  public tinyint(1) NOT NULL default '0',
  maxoptions smallint unsigned NOT NULL default '0',
  KEY tid (tid),
  PRIMARY KEY (pid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_pollvotes (
  vid int unsigned NOT NULL auto_increment,
  pid int unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  voteoption smallint unsigned NOT NULL default '0',
  dateline int unsigned NOT NULL default '0',
  ipaddress varbinary(16) NOT NULL default '',
  KEY pid (pid, uid),
  PRIMARY KEY (vid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_posts (
  pid int unsigned NOT NULL auto_increment,
  tid int unsigned NOT NULL default '0',
  replyto int unsigned NOT NULL default '0',
  fid smallint unsigned NOT NULL default '0',
  subject varchar(120) NOT NULL default '',
  icon smallint unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  username varchar(80) NOT NULL default '',
  dateline int unsigned NOT NULL default '0',
  message text NOT NULL,
  ipaddress varbinary(16) NOT NULL default '',
  includesig tinyint(1) NOT NULL default '0',
  smilieoff tinyint(1) NOT NULL default '0',
  edituid int unsigned NOT NULL default '0',
  edittime int unsigned NOT NULL default '0',
  editreason varchar(150) NOT NULL default '',
  visible tinyint(1) NOT NULL default '0',
  KEY tid (tid, uid),
  KEY uid (uid),
  KEY visible (visible),
  KEY dateline (dateline),
  KEY ipaddress (ipaddress),
  KEY tiddate (tid, dateline),
  PRIMARY KEY (pid)
) ENGINE=MyISAM;";

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
  dateline int unsigned NOT NULL default '0',
  deletetime int unsigned NOT NULL default '0',
  status tinyint(1) NOT NULL default '0',
  statustime int unsigned NOT NULL default '0',
  includesig tinyint(1) NOT NULL default '0',
  smilieoff tinyint(1) NOT NULL default '0',
  receipt tinyint(1) NOT NULL default '0',
  readtime int unsigned NOT NULL default '0',
  ipaddress varbinary(16) NOT NULL default '',
  KEY uid (uid, folder),
  KEY toid (toid),
  PRIMARY KEY (pmid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_profilefields (
  fid smallint unsigned NOT NULL auto_increment,
  name varchar(100) NOT NULL default '',
  description text NOT NULL,
  disporder smallint unsigned NOT NULL default '0',
  type text NOT NULL,
  regex text NOT NULL,
  length smallint unsigned NOT NULL default '0',
  maxlength smallint unsigned NOT NULL default '0',
  required tinyint(1) NOT NULL default '0',
  registration tinyint(1) NOT NULL default '0',
  profile tinyint(1) NOT NULL default '0',
  postbit tinyint(1) NOT NULL default '0',
  viewableby text NOT NULL,
  editableby text NOT NULL,
  postnum smallint unsigned NOT NULL default '0',
  allowhtml tinyint(1) NOT NULL default '0',
  allowmycode tinyint(1) NOT NULL default '0',
  allowsmilies tinyint(1) NOT NULL default '0',
  allowimgcode tinyint(1) NOT NULL default '0',
  allowvideocode tinyint(1) NOT NULL default '0',
  PRIMARY KEY (fid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_promotions (
  pid int unsigned NOT NULL auto_increment,
  title varchar(120) NOT NULL default '',
  description text NOT NULL,
  enabled tinyint(1) NOT NULL default '1',
  logging tinyint(1) NOT NULL default '0',
  posts int unsigned NOT NULL default '0',
  posttype char(2) NOT NULL default '',
  threads int unsigned NOT NULL default '0',
  threadtype char(2) NOT NULL default '',
  registered int unsigned NOT NULL default '0',
  registeredtype varchar(20) NOT NULL default '',
  online int unsigned NOT NULL default '0',
  onlinetype varchar(20) NOT NULL default '',
  reputations int NOT NULL default '0',
  reputationtype char(2) NOT NULL default '',
  referrals int unsigned NOT NULL default '0',
  referralstype char(2) NOT NULL default '',
  warnings int unsigned NOT NULL default '0',
  warningstype char(2) NOT NULL default '',
  requirements varchar(200) NOT NULL default '',
  originalusergroup varchar(120) NOT NULL default '0',
  newusergroup smallint unsigned NOT NULL default '0',
  usergrouptype varchar(120) NOT NULL default '0',
  PRIMARY KEY (pid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_promotionlogs (
  plid int unsigned NOT NULL auto_increment,
  pid int unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  oldusergroup varchar(200) NOT NULL default '0',
  newusergroup smallint NOT NULL default '0',
  dateline int unsigned NOT NULL default '0',
  type varchar(9) NOT NULL default 'primary',
  PRIMARY KEY (plid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_questions (
  qid int unsigned NOT NULL auto_increment,
  question varchar(200) NOT NULL default '',
  answer varchar(150) NOT NULL default '',
  shown int unsigned NOT NULL default 0,
  correct int unsigned NOT NULL default 0,
  incorrect int unsigned NOT NULL default 0,
  active tinyint(1) NOT NULL default '0',
  PRIMARY KEY (qid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_questionsessions (
  sid varchar(32) NOT NULL default '',
  qid int unsigned NOT NULL default '0',
  dateline int unsigned NOT NULL default '0',
  PRIMARY KEY (sid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_reportedcontent (
  rid int unsigned NOT NULL auto_increment,
  id int unsigned NOT NULL default '0',
  id2 int unsigned NOT NULL default '0',
  id3 int unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  reportstatus tinyint(1) NOT NULL default '0',
  reasonid smallint unsigned NOT NULL default '0',
  reason varchar(250) NOT NULL default '',
  type varchar(50) NOT NULL default '',
  reports int unsigned NOT NULL default '0',
  reporters text NOT NULL,
  dateline int unsigned NOT NULL default '0',
  lastreport int unsigned NOT NULL default '0',
  KEY reportstatus (reportstatus),
  KEY lastreport (lastreport),
  PRIMARY KEY (rid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_reportreasons (
  rid int unsigned NOT NULL auto_increment,
  title varchar(250) NOT NULL default '',
  appliesto varchar(250) NOT NULL default '',
  extra tinyint(1) NOT NULL default '0',
  disporder smallint unsigned NOT NULL default '0',
  PRIMARY KEY (rid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_reputation (
  rid int unsigned NOT NULL auto_increment,
  uid int unsigned NOT NULL default '0',
  adduid int unsigned NOT NULL default '0',
  pid int unsigned NOT NULL default '0',
  reputation smallint NOT NULL default '0',
  dateline int unsigned NOT NULL default '0',
  comments text NOT NULL,
  KEY uid (uid),
  PRIMARY KEY (rid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_searchlog (
  sid varchar(32) NOT NULL default '',
  uid int unsigned NOT NULL default '0',
  dateline int unsigned NOT NULL default '0',
  ipaddress varbinary(16) NOT NULL default '',
  threads longtext NOT NULL,
  posts longtext NOT NULL,
  resulttype varchar(10) NOT NULL default '',
  querycache text NOT NULL,
  keywords text NOT NULL,
  PRIMARY KEY (sid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_sessions (
  sid varchar(32) NOT NULL default '',
  uid int unsigned NOT NULL default '0',
  ip varbinary(16) NOT NULL default '',
  time int unsigned NOT NULL default '0',
  location varchar(150) NOT NULL default '',
  useragent varchar(200) NOT NULL default '',
  anonymous tinyint(1) NOT NULL default '0',
  nopermission tinyint(1) NOT NULL default '0',
  location1 int(10) unsigned NOT NULL default '0',
  location2 int(10) unsigned NOT NULL default '0',
  PRIMARY KEY(sid),
  KEY location (location1, location2),
  KEY time (time),
  KEY uid (uid),
  KEY ip (ip)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_settinggroups (
  gid smallint unsigned NOT NULL auto_increment,
  name varchar(100) NOT NULL default '',
  title varchar(220) NOT NULL default '',
  description text NOT NULL,
  disporder smallint unsigned NOT NULL default '0',
  isdefault tinyint(1) NOT NULL default '0',
  PRIMARY KEY (gid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_settings (
  sid smallint unsigned NOT NULL auto_increment,
  name varchar(120) NOT NULL default '',
  title varchar(120) NOT NULL default '',
  description text NOT NULL,
  optionscode text NOT NULL,
  value text NOT NULL,
  disporder smallint unsigned NOT NULL default '0',
  gid smallint unsigned NOT NULL default '0',
  isdefault tinyint(1) NOT NULL default '0',
  KEY gid (gid),
  PRIMARY KEY (sid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_smilies (
  sid smallint unsigned NOT NULL auto_increment,
  name varchar(120) NOT NULL default '',
  find text NOT NULL,
  image varchar(220) NOT NULL default '',
  disporder smallint unsigned NOT NULL default '0',
  showclickable tinyint(1) NOT NULL default '0',
  PRIMARY KEY (sid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_spamlog (
	sid int unsigned NOT NULL auto_increment,
	username varchar(120) NOT NULL DEFAULT '',
	email varchar(220) NOT NULL DEFAULT '',
	ipaddress varbinary(16) NOT NULL default '',
	dateline int unsigned NOT NULL default '0',
	data text NOT NULL,
	PRIMARY KEY (sid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_spiders (
	sid int unsigned NOT NULL auto_increment,
	name varchar(100) NOT NULL default '',
	theme smallint unsigned NOT NULL default '0',
	language varchar(20) NOT NULL default '',
	usergroup smallint unsigned NOT NULL default '0',
	useragent varchar(200) NOT NULL default '',
	lastvisit int unsigned NOT NULL default '0',
	PRIMARY KEY (sid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_stats (
	dateline int unsigned NOT NULL default '0',
	numusers int unsigned NOT NULL default '0',
	numthreads int unsigned NOT NULL default '0',
	numposts int unsigned NOT NULL default '0',
	PRIMARY KEY (dateline)
) ENGINE=MyISAM;";

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
	nextrun int unsigned NOT NULL default '0',
	lastrun int unsigned NOT NULL default '0',
	enabled tinyint(1) NOT NULL default '1',
	logging tinyint(1) NOT NULL default '0',
	locked int unsigned NOT NULL default '0',
	PRIMARY KEY (tid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_tasklog (
	lid int unsigned NOT NULL auto_increment,
	tid int unsigned NOT NULL default '0',
	dateline int unsigned NOT NULL default '0',
	data text NOT NULL,
	PRIMARY KEY (lid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_templategroups (
  gid int unsigned NOT NULL auto_increment,
  prefix varchar(50) NOT NULL default '',
  title varchar(100) NOT NULL default '',
  isdefault tinyint(1) NOT NULL default '0',
  PRIMARY KEY (gid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_templates (
  tid int unsigned NOT NULL auto_increment,
  title varchar(120) NOT NULL default '',
  template text NOT NULL,
  sid smallint NOT NULL default '0',
  version varchar(20) NOT NULL default '0',
  status varchar(10) NOT NULL default '',
  dateline int unsigned NOT NULL default '0',
  KEY sid (sid, title),
  PRIMARY KEY (tid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_templatesets (
  sid smallint unsigned NOT NULL auto_increment,
  title varchar(120) NOT NULL default '',
  PRIMARY KEY (sid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_themes (
  tid smallint unsigned NOT NULL auto_increment,
  name varchar(100) NOT NULL default '',
  pid smallint unsigned NOT NULL default '0',
  def tinyint(1) NOT NULL default '0',
  properties text NOT NULL,
  stylesheets text NOT NULL,
  allowedgroups text NOT NULL,
  PRIMARY KEY (tid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_themestylesheets (
	sid int unsigned NOT NULL auto_increment,
	name varchar(30) NOT NULL default '',
	tid smallint unsigned NOT NULL default '0',
	attachedto text NOT NULL,
	stylesheet longtext NOT NULL,
	cachefile varchar(100) NOT NULL default '',
	lastmodified int unsigned NOT NULL default '0',
	KEY tid (tid),
	PRIMARY KEY (sid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_threadprefixes (
	pid int unsigned NOT NULL auto_increment,
	prefix varchar(120) NOT NULL default '',
	displaystyle varchar(200) NOT NULL default '',
	forums text NOT NULL,
	`groups` text NOT NULL,
	PRIMARY KEY (pid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_threadratings (
  rid int unsigned NOT NULL auto_increment,
  tid int unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  rating tinyint(1) unsigned NOT NULL default '0',
  ipaddress varbinary(16) NOT NULL default '',
  KEY tid (tid, uid),
  PRIMARY KEY (rid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_threadviews (
	tid int unsigned NOT NULL default '0',
	KEY (tid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_threads (
  tid int unsigned NOT NULL auto_increment,
  fid smallint unsigned NOT NULL default '0',
  subject varchar(120) NOT NULL default '',
  prefix smallint unsigned NOT NULL default '0',
  icon smallint unsigned NOT NULL default '0',
  poll int unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  username varchar(80) NOT NULL default '',
  dateline int unsigned NOT NULL default '0',
  firstpost int unsigned NOT NULL default '0',
  lastpost int unsigned NOT NULL default '0',
  lastposter varchar(120) NOT NULL default '',
  lastposteruid int unsigned NOT NULL default '0',
  views int(100) unsigned NOT NULL default '0',
  replies int(100) unsigned NOT NULL default '0',
  closed varchar(30) NOT NULL default '',
  sticky tinyint(1) NOT NULL default '0',
  numratings smallint unsigned NOT NULL default '0',
  totalratings smallint unsigned NOT NULL default '0',
  notes text NOT NULL,
  visible tinyint(1) NOT NULL default '0',
  unapprovedposts int(10) unsigned NOT NULL default '0',
  deletedposts int(10) unsigned NOT NULL default '0',
  attachmentcount int(10) unsigned NOT NULL default '0',
  deletetime int(10) unsigned NOT NULL default '0',
  KEY fid (fid, visible, sticky),
  KEY dateline (dateline),
  KEY lastpost (lastpost, fid),
  KEY firstpost (firstpost),
  KEY uid (uid),
  PRIMARY KEY (tid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_threadsread (
  tid int unsigned NOT NULL default '0',
  uid int unsigned NOT NULL default '0',
  dateline int unsigned NOT NULL default '0',
  KEY dateline (dateline),
  UNIQUE KEY tid (tid, uid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_threadsubscriptions (
  sid int unsigned NOT NULL auto_increment,
  uid int unsigned NOT NULL default '0',
  tid int unsigned NOT NULL default '0',
  notification tinyint(1) NOT NULL default '0',
  dateline int unsigned NOT NULL default '0',
  KEY uid (uid),
  KEY tid (tid, notification),
  PRIMARY KEY (sid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_userfields (
  ufid int unsigned NOT NULL default '0',
  fid1 text NOT NULL,
  fid2 text NOT NULL,
  fid3 text NOT NULL,
  PRIMARY KEY (ufid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_usergroups (
  gid smallint unsigned NOT NULL auto_increment,
  type tinyint(1) unsigned NOT NULL default '2',
  title varchar(120) NOT NULL default '',
  description text NOT NULL,
  namestyle varchar(200) NOT NULL default '{username}',
  usertitle varchar(120) NOT NULL default '',
  stars smallint(4) unsigned NOT NULL default '0',
  starimage varchar(120) NOT NULL default '',
  image varchar(120) NOT NULL default '',
  disporder smallint(6) unsigned NOT NULL,
  isbannedgroup tinyint(1) NOT NULL default '0',
  canview tinyint(1) NOT NULL default '0',
  canviewthreads tinyint(1) NOT NULL default '0',
  canviewprofiles tinyint(1) NOT NULL default '0',
  candlattachments tinyint(1) NOT NULL default '0',
  canviewboardclosed tinyint(1) NOT NULL default '0',
  canpostthreads tinyint(1) NOT NULL default '0',
  canpostreplys tinyint(1) NOT NULL default '0',
  canpostattachments tinyint(1) NOT NULL default '0',
  canratethreads tinyint(1) NOT NULL default '0',
  modposts tinyint(1) NOT NULL default '0',
  modthreads tinyint(1) NOT NULL default '0',
  mod_edit_posts tinyint(1) NOT NULL default '0',
  modattachments tinyint(1) NOT NULL default '0',
  caneditposts tinyint(1) NOT NULL default '0',
  candeleteposts tinyint(1) NOT NULL default '0',
  candeletethreads tinyint(1) NOT NULL default '0',
  caneditattachments tinyint(1) NOT NULL default '0',
  canviewdeletionnotice tinyint(1) NOT NULL default '0',
  canpostpolls tinyint(1) NOT NULL default '0',
  canvotepolls tinyint(1) NOT NULL default '0',
  canundovotes tinyint(1) NOT NULL default '0',
  canusepms tinyint(1) NOT NULL default '0',
  cansendpms tinyint(1) NOT NULL default '0',
  cantrackpms tinyint(1) NOT NULL default '0',
  candenypmreceipts tinyint(1) NOT NULL default '0',
  pmquota int(3) unsigned NOT NULL default '0',
  maxpmrecipients int(4) unsigned NOT NULL default '5',
  cansendemail tinyint(1) NOT NULL default '0',
  cansendemailoverride tinyint(1) NOT NULL default '0',
  maxemails int(3) unsigned NOT NULL default '5',
  emailfloodtime int(3) unsigned NOT NULL default '5',
  canviewmemberlist tinyint(1) NOT NULL default '0',
  canviewcalendar tinyint(1) NOT NULL default '0',
  canaddevents tinyint(1) NOT NULL default '0',
  canbypasseventmod tinyint(1) NOT NULL default '0',
  canmoderateevents tinyint(1) NOT NULL default '0',
  canviewonline tinyint(1) NOT NULL default '0',
  canviewwolinvis tinyint(1) NOT NULL default '0',
  canviewonlineips tinyint(1) NOT NULL default '0',
  cancp tinyint(1) NOT NULL default '0',
  issupermod tinyint(1) NOT NULL default '0',
  cansearch tinyint(1) NOT NULL default '0',
  canusercp tinyint(1) NOT NULL default '0',
  canuploadavatars tinyint(1) NOT NULL default '0',
  canratemembers tinyint(1) NOT NULL default '0',
  canchangename tinyint(1) NOT NULL default '0',
  canbereported tinyint(1) NOT NULL default '0',
  canchangewebsite tinyint(1) NOT NULL default '1',
  showforumteam tinyint(1) NOT NULL default '0',
  usereputationsystem tinyint(1) NOT NULL default '0',
  cangivereputations tinyint(1) NOT NULL default '0',
  candeletereputations tinyint(1) NOT NULL default '0',
  reputationpower int unsigned NOT NULL default '0',
  maxreputationsday int unsigned NOT NULL default '0',
  maxreputationsperuser int unsigned NOT NULL default '0',
  maxreputationsperthread int unsigned NOT NULL default '0',
  candisplaygroup tinyint(1) NOT NULL default '0',
  attachquota int unsigned NOT NULL default '0',
  cancustomtitle tinyint(1) NOT NULL default '0',
  canwarnusers tinyint(1) NOT NULL default '0',
  canreceivewarnings tinyint(1) NOT NULL default '0',
  maxwarningsday int(3) unsigned NOT NULL default '3',
  canmodcp tinyint(1) NOT NULL default '0',
  showinbirthdaylist tinyint(1) NOT NULL default '0',
  canoverridepm tinyint(1) NOT NULL default '0',
  canusesig tinyint(1) NOT NULL default '0',
  canusesigxposts smallint unsigned NOT NULL default '0',
  signofollow tinyint(1) NOT NULL default '0',
  edittimelimit int(4) unsigned NOT NULL default '0',
  maxposts int(4) unsigned NOT NULL default '0',
  showmemberlist tinyint(1) NOT NULL default '1',
  canmanageannounce tinyint(1) NOT NULL default '0',
  canmanagemodqueue tinyint(1) NOT NULL default '0',
  canmanagereportedcontent tinyint(1) NOT NULL default '0',
  canviewmodlogs tinyint(1) NOT NULL default '0',
  caneditprofiles tinyint(1) NOT NULL default '0',
  canbanusers tinyint(1) NOT NULL default '0',
  canviewwarnlogs tinyint(1) NOT NULL default '0',
  canuseipsearch tinyint(1) NOT NULL default '0',
  PRIMARY KEY (gid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_users (
  uid int unsigned NOT NULL auto_increment,
  username varchar(120) NOT NULL default '',
  password varchar(120) NOT NULL default '',
  salt varchar(10) NOT NULL default '',
  loginkey varchar(50) NOT NULL default '',
  email varchar(220) NOT NULL default '',
  postnum int(10) unsigned NOT NULL default '0',
  threadnum int(10) unsigned NOT NULL default '0',
  avatar varchar(200) NOT NULL default '',
  avatardimensions varchar(10) NOT NULL default '',
  avatartype varchar(10) NOT NULL default '0',
  usergroup smallint unsigned NOT NULL default '0',
  additionalgroups varchar(200) NOT NULL default '',
  displaygroup smallint unsigned NOT NULL default '0',
  usertitle varchar(250) NOT NULL default '',
  regdate int unsigned NOT NULL default '0',
  lastactive int unsigned NOT NULL default '0',
  lastvisit int unsigned NOT NULL default '0',
  lastpost int unsigned NOT NULL default '0',
  website varchar(200) NOT NULL default '',
  icq varchar(10) NOT NULL default '',
  yahoo varchar(50) NOT NULL default '',
  skype varchar(75) NOT NULL default '',
  google varchar(75) NOT NULL default '',
  birthday varchar(15) NOT NULL default '',
  birthdayprivacy varchar(4) NOT NULL default 'all',
  signature text NOT NULL,
  allownotices tinyint(1) NOT NULL default '0',
  hideemail tinyint(1) NOT NULL default '0',
  subscriptionmethod tinyint(1) NOT NULL default '0',
  invisible tinyint(1) NOT NULL default '0',
  receivepms tinyint(1) NOT NULL default '0',
  receivefrombuddy tinyint(1) NOT NULL default '0',
  pmnotice tinyint(1) NOT NULL default '0',
  pmnotify tinyint(1) NOT NULL default '0',
  buddyrequestspm tinyint(1) NOT NULL default '1',
  buddyrequestsauto tinyint(1) NOT NULL default '0',
  threadmode varchar(8) NOT NULL default '',
  showimages tinyint(1) NOT NULL default '0',
  showvideos tinyint(1) NOT NULL default '0',
  showsigs tinyint(1) NOT NULL default '0',
  showavatars tinyint(1) NOT NULL default '0',
  showquickreply tinyint(1) NOT NULL default '0',
  showredirect tinyint(1) NOT NULL default '0',
  ppp smallint(6) unsigned NOT NULL default '0',
  tpp smallint(6) unsigned NOT NULL default '0',
  daysprune smallint(6) unsigned NOT NULL default '0',
  dateformat varchar(4) NOT NULL default '',
  timeformat varchar(4) NOT NULL default '',
  timezone varchar(5) NOT NULL default '',
  dst tinyint(1) NOT NULL default '0',
  dstcorrection tinyint(1) NOT NULL default '0',
  buddylist text NOT NULL,
  ignorelist text NOT NULL,
  style smallint unsigned NOT NULL default '0',
  away tinyint(1) NOT NULL default '0',
  awaydate int(10) unsigned NOT NULL default '0',
  returndate varchar(15) NOT NULL default '',
  awayreason varchar(200) NOT NULL default '',
  pmfolders text NOT NULL,
  notepad text NOT NULL,
  referrer int unsigned NOT NULL default '0',
  referrals int unsigned NOT NULL default '0',
  reputation int NOT NULL default '0',
  regip varbinary(16) NOT NULL default '',
  lastip varbinary(16) NOT NULL default '',
  language varchar(50) NOT NULL default '',
  timeonline int unsigned NOT NULL default '0',
  showcodebuttons tinyint(1) NOT NULL default '1',
  totalpms int(10) unsigned NOT NULL default '0',
  unreadpms int(10) unsigned NOT NULL default '0',
  warningpoints int(3) unsigned NOT NULL default '0',
  moderateposts tinyint(1) NOT NULL default '0',
  moderationtime int unsigned NOT NULL default '0',
  suspendposting tinyint(1) NOT NULL default '0',
  suspensiontime int unsigned NOT NULL default '0',
  suspendsignature tinyint(1) NOT NULL default '0',
  suspendsigtime int unsigned NOT NULL default '0',
  coppauser tinyint(1) NOT NULL default '0',
  classicpostbit tinyint(1) NOT NULL default '0',
  loginattempts smallint(2) unsigned NOT NULL default '0',
  loginlockoutexpiry int unsigned NOT NULL default '0',
  usernotes text NOT NULL,
  sourceeditor tinyint(1) NOT NULL default '0',
  UNIQUE KEY username (username),
  KEY usergroup (usergroup),
  KEY regip (regip),
  KEY lastip (lastip),
  PRIMARY KEY (uid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_usertitles (
  utid smallint unsigned NOT NULL auto_increment,
  posts int unsigned NOT NULL default '0',
  title varchar(250) NOT NULL default '',
  stars smallint(4) unsigned NOT NULL default '0',
  starimage varchar(120) NOT NULL default '',
  PRIMARY KEY (utid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_warninglevels (
	lid int unsigned NOT NULL auto_increment,
	percentage smallint(3) unsigned NOT NULL default '0',
	action text NOT NULL,
	PRIMARY KEY (lid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_warningtypes (
	tid int unsigned NOT NULL auto_increment,
	title varchar(120) NOT NULL default '',
	points smallint unsigned NOT NULL default '0',
	expirationtime int unsigned NOT NULL default '0',
	PRIMARY KEY (tid)
) ENGINE=MyISAM;";

$tables[] = "CREATE TABLE mybb_warnings (
	wid int unsigned NOT NULL auto_increment,
	uid int unsigned NOT NULL default '0',
	tid int unsigned NOT NULL default '0',
	pid int unsigned NOT NULL default '0',
	title varchar(120) NOT NULL default '',
	points smallint unsigned NOT NULL default '0',
	dateline int unsigned NOT NULL default '0',
	issuedby int unsigned NOT NULL default '0',
	expires int unsigned NOT NULL default '0',
	expired tinyint(1) NOT NULL default '0',
	daterevoked int unsigned NOT NULL default '0',
	revokedby int unsigned NOT NULL default '0',
	revokereason text NOT NULL,
	notes text NOT NULL,
	KEY uid (uid),
	PRIMARY KEY (wid)
) ENGINE=MyISAM;";


