<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: sqlite_db_tables.php 5690 2011-11-30 16:08:49Z Tomm $
 */

$tables[] = "CREATE TABLE mybb_adminlog (
  uid int unsigned NOT NULL default '0',
  ipaddress varchar(50) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0',
  module varchar(50) NOT NULL default '',
  action varchar(50) NOT NULL default '',
  data TEXT NOT NULL 
 );";

$tables[] = "CREATE TABLE mybb_adminoptions (
  uid int unsigned NOT NULL default '0',
  cpstyle varchar(50) NOT NULL default '',
  codepress int(1) NOT NULL default '1',
  notes TEXT NOT NULL,
  permissions TEXT NOT NULL,
  defaultviews TEXT NOT NULL,
  loginattempts int unsigned NOT NULL default '0',
  loginlockoutexpiry int unsigned NOT NULL default '0'
 );";

$tables[] = "CREATE TABLE mybb_adminsessions (
	sid varchar(32) NOT NULL default '',
	uid int NOT NULL default '0',
	loginkey varchar(50) NOT NULL default '',
	ip varchar(40) NOT NULL default '',
	dateline bigint(30) NOT NULL default '0',
	lastactive bigint(30) NOT NULL default '0',
	data TEXT NOT NULL
);";

$tables[] = "CREATE TABLE mybb_adminviews (
	vid INTEGER PRIMARY KEY,
	uid int(10) NOT NULL default '0',
	title varchar(100) NOT NULL default '',
	type varchar(6) NOT NULL default '',
	visibility int(1) NOT NULL default '0',
	fields TEXT NOT NULL,
	conditions TEXT NOT NULL,
	custom_profile_fields TEXT NOT NULL,
	sortby varchar(20) NOT NULL default '',
	sortorder varchar(4) NOT NULL default '',
	perpage int(4) NOT NULL default '0',
	view_type varchar(6) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_announcements (
  aid INTEGER PRIMARY KEY,
  fid int(10) NOT NULL default '0',
  uid int NOT NULL default '0',
  subject varchar(120) NOT NULL default '',
  message TEXT NOT NULL,
  startdate bigint(30) NOT NULL default '0',
  enddate bigint(30) NOT NULL default '0',
  allowhtml int(1) NOT NULL default '0',
  allowmycode int(1) NOT NULL default '0',
  allowsmilies int(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_attachments (
  aid INTEGER PRIMARY KEY,
  pid int(10) NOT NULL default '0',
  posthash varchar(50) NOT NULL default '',
  uid int NOT NULL default '0',
  filename varchar(120) NOT NULL default '',
  filetype varchar(120) NOT NULL default '',
  filesize int(10) NOT NULL default '0',
  attachname varchar(120) NOT NULL default '',
  downloads int NOT NULL default '0',
  dateuploaded bigint(30) NOT NULL default '0',
  visible int(1) NOT NULL default '0',
  thumbnail varchar(120) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_attachtypes (
  atid INTEGER PRIMARY KEY,
  name varchar(120) NOT NULL default '',
  mimetype varchar(120) NOT NULL default '',
  extension varchar(10) NOT NULL default '',
  maxsize int(15) NOT NULL default '0',
  icon varchar(100) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_awaitingactivation (
  aid INTEGER PRIMARY KEY,
  uid int NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  code varchar(100) NOT NULL default '',
  type char(1) NOT NULL default '',
  oldgroup bigint(30) NOT NULL default '0',
  misc varchar(255) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_badwords (
  bid INTEGER PRIMARY KEY,
  badword varchar(100) NOT NULL default '',
  replacement varchar(100) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_banfilters (
  fid INTEGER PRIMARY KEY,
  filter varchar(200) NOT NULL default '',
  type int(1) NOT NULL default '0',
  lastuse bigint(30) NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_banned (
  uid int NOT NULL default '0',
  gid int NOT NULL default '0',
  oldgroup int NOT NULL default '0',
  oldadditionalgroups TEXT NOT NULL,
  olddisplaygroup int NOT NULL default '0',
  admin int NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  bantime varchar(50) NOT NULL default '',
  lifted bigint(30) NOT NULL default '0',
  reason varchar(255) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_calendars (
  cid INTEGER PRIMARY KEY,
  name varchar(100) NOT NULL default '',
  disporder int NOT NULL default '0',
  startofweek int(1) NOT NULL default '0',
  showbirthdays int(1) NOT NULL default '0',
  eventlimit int(3) NOT NULL default '0',
  moderation int(1) NOT NULL default '0',
  allowhtml int(1) NOT NULL default '0',
  allowmycode int(1) NOT NULL default '0',
  allowimgcode int(1) NOT NULL default '0',
  allowvideocode int(1) NOT NULL default '0',
  allowsmilies int(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_calendarpermissions (
  cid int NOT NULL default '0',
  gid int NOT NULL default '0',
  canviewcalendar int(1) NOT NULL default '0',
  canaddevents int(1) NOT NULL default '0',
  canbypasseventmod int(1) NOT NULL default '0',
  canmoderateevents int(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_captcha (
  imagehash varchar(32) NOT NULL default '',
  imagestring varchar(8) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_datacache (
  title varchar(50) NOT NULL default '' PRIMARY KEY,
  cache mediumTEXT NOT NULL
);";

$tables[] = "CREATE TABLE mybb_delayedmoderation (
  did integer PRIMARY KEY,
  type varchar(30) NOT NULL default '',
  delaydateline bigint(30) NOT NULL default '0',
  uid int(10) NOT NULL default '0',
  fid smallint(5) NOT NULL default '0',
  tids text NOT NULL,
  dateline bigint(30) NOT NULL default '0',
  inputs text NOT NULL
);";

$tables[] = "CREATE TABLE mybb_events (
  eid INTEGER PRIMARY KEY,
  cid int NOT NULL default '0',
  uid int NOT NULL default '0',
  name varchar(120) NOT NULL default '',
  description TEXT NOT NULL,
  visible int(1) NOT NULL default '0',
  private int(1) NOT NULL default '0',
  dateline int(10) NOT NULL default '0',
  starttime int(10) NOT NULL default '0',
  endtime int(10) NOT NULL default '0',
  timezone varchar(4) NOT NULL default '0',
  ignoretimezone int(1) NOT NULL default '0',
  usingtime int(1) NOT NULL default '0',
  repeats TEXT NOT NULL );";

$tables[] = "CREATE TABLE mybb_forumpermissions (
  pid INTEGER PRIMARY KEY,
  fid int NOT NULL default '0',
  gid int NOT NULL default '0',
  canview int(1) NOT NULL default '0',
  canviewthreads int(1) NOT NULL default '0',
  canonlyviewownthreads int(1) NOT NULL default '0',
  candlattachments int(1) NOT NULL default '0',
  canpostthreads int(1) NOT NULL default '0',
  canpostreplys int(1) NOT NULL default '0',
  canpostattachments int(1) NOT NULL default '0',
  canratethreads int(1) NOT NULL default '0',
  caneditposts int(1) NOT NULL default '0',
  candeleteposts int(1) NOT NULL default '0',
  candeletethreads int(1) NOT NULL default '0',
  caneditattachments int(1) NOT NULL default '0',
  canpostpolls int(1) NOT NULL default '0',
  canvotepolls int(1) NOT NULL default '0',
  cansearch int(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_forums (
  fid INTEGER PRIMARY KEY,
  name varchar(120) NOT NULL default '',
  description TEXT NOT NULL,
  linkto varchar(180) NOT NULL default '',
  type char(1) NOT NULL default '',
  pid smallint NOT NULL default '0',
  parentlist TEXT NOT NULL,
  disporder smallint NOT NULL default '0',
  active int(1) NOT NULL default '0',
  open int(1) NOT NULL default '0',
  threads int NOT NULL default '0',
  posts int NOT NULL default '0',
  lastpost int(10) NOT NULL default '0',
  lastposter varchar(120) NOT NULL default '',
  lastposteruid int(10) NOT NULL default '0',
  lastposttid int(10) NOT NULL default '0',
  lastpostsubject varchar(120) NOT NULL default '',
  allowhtml int(1) NOT NULL default '0',
  allowmycode int(1) NOT NULL default '0',
  allowsmilies int(1) NOT NULL default '0',
  allowimgcode int(1) NOT NULL default '0',
  allowvideocode int(1) NOT NULL default '0',
  allowpicons int(1) NOT NULL default '0',
  allowtratings int(1) NOT NULL default '0',
  status int(4) NOT NULL default '1',
  usepostcounts int(1) NOT NULL default '0',
  password varchar(50) NOT NULL default '',
  showinjump int(1) NOT NULL default '0',
  modposts int(1) NOT NULL default '0',
  modthreads int(1) NOT NULL default '0',
  mod_edit_posts int(1) NOT NULL default '0',
  modattachments int(1) NOT NULL default '0',
  style smallint NOT NULL default '0',
  overridestyle int(1) NOT NULL default '0',
  rulestype smallint(1) NOT NULL default '0',
  rulestitle varchar(200) NOT NULL default '',
  rules TEXT NOT NULL,
  unapprovedthreads int(10) NOT NULL default '0',
  unapprovedposts int(10) NOT NULL default '0',
  defaultdatecut smallint(4) NOT NULL default '0',
  defaultsortby varchar(10) NOT NULL default '',
  defaultsortorder varchar(4) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_forumsread (
  fid int NOT NULL default '0',
  uid int NOT NULL default '0',
  dateline int(10) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_forumsubscriptions (
  fsid INTEGER PRIMARY KEY,
  fid smallint NOT NULL default '0',
  uid int NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_groupleaders (
  lid INTEGER PRIMARY KEY,
  gid smallint NOT NULL default '0',
  uid int NOT NULL default '0',
  canmanagemembers int(1) NOT NULL default '0',
  canmanagerequests int(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_helpdocs (
  hid INTEGER PRIMARY KEY,
  sid smallint NOT NULL default '0',
  name varchar(120) NOT NULL default '',
  description TEXT NOT NULL,
  document TEXT NOT NULL,
  usetranslation int(1) NOT NULL default '0',
  enabled int(1) NOT NULL default '0',
  disporder smallint NOT NULL default '0'
);";


$tables[] = "CREATE TABLE mybb_helpsections (
  sid INTEGER PRIMARY KEY,
  name varchar(120) NOT NULL default '',
  description TEXT NOT NULL,
  usetranslation int(1) NOT NULL default '0',
  enabled int(1) NOT NULL default '0',
  disporder smallint NOT NULL default '0'
);";


$tables[] = "CREATE TABLE mybb_icons (
  iid INTEGER PRIMARY KEY,
  name varchar(120) NOT NULL default '',
  path varchar(220) NOT NULL default ''
);";


$tables[] = "CREATE TABLE mybb_joinrequests (
  rid INTEGER PRIMARY KEY,
  uid int NOT NULL default '0',
  gid smallint NOT NULL default '0',
  reason varchar(250) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_massemails (
	mid INTEGER PRIMARY KEY,
	uid int NOT NULL default '0',
	subject varchar(200) NOT NULL default '',
	message text NOT NULL,
	htmlmessage text NOT NULL,
	type tinyint(1) NOT NULL default '0',
	format tinyint(1) NOT NULL default '0',
	dateline bigint(30) NOT NULL default '0',
	senddate bigint(30) NOT NULL default '0',
	status tinyint(1) NOT NULL default '0',
	sentcount int NOT NULL default '0',
	totalcount int NOT NULL default '0',
	conditions text NOT NULL,
	perpage smallint(4) NOT NULL default '50'
);";

$tables[] = "CREATE TABLE mybb_mailerrors (
  eid INTEGER PRIMARY KEY,
  subject varchar(200) NOT NULL default '',
  message TEXT NOT NULL,
  toaddress varchar(150) NOT NULL default '',
  fromaddress varchar(150) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0',
  error TEXT NOT NULL,
  smtperror varchar(200) NOT NULL default '',
  smtpcode int(5) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_maillogs (
	mid INTEGER PRIMARY KEY,
	subject varchar(200) not null default '',
	message TEXT NOT NULL,
	dateline bigint(30) NOT NULL default '0',
	fromuid int NOT NULL default '0',
	fromemail varchar(200) not null default '',
	touid bigint(30) NOT NULL default '0',
	toemail varchar(200) NOT NULL default '',
	tid int NOT NULL default '0',
	ipaddress varchar(20) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_mailqueue (
	mid INTEGER PRIMARY KEY,
	mailto varchar(200) NOT NULL default '',
	mailfrom varchar(200) NOT NULL default '',
	subject varchar(200) NOT NULL default '',
	message TEXT NOT NULL,
	headers TEXT NOT NULL );";

$tables[] = "CREATE TABLE mybb_moderatorlog (
  uid int NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  fid smallint NOT NULL default '0',
  tid int NOT NULL default '0',
  pid int NOT NULL default '0',
  action TEXT NOT NULL,
  data text NOT NULL,
  ipaddress varchar(50) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_moderators (
  mid INTEGER PRIMARY KEY,
  fid smallint NOT NULL default '0',
  id int NOT NULL default '0',
  isgroup int(1) NOT NULL default '0',
  caneditposts int(1) NOT NULL default '0',
  candeleteposts int(1) NOT NULL default '0',
  canviewips int(1) NOT NULL default '0',
  canopenclosethreads int(1) NOT NULL default '0',
  canmanagethreads int(1) NOT NULL default '0',
  canmovetononmodforum int(1) NOT NULL default '0',
  canusecustomtools int(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_modtools (
	tid INTEGER PRIMARY KEY,
	name varchar(200) NOT NULL default '',
	description TEXT NOT NULL,
	forums TEXT NOT NULL,
	type char(1) NOT NULL default '',
	postoptions TEXT NOT NULL,
	threadoptions TEXT NOT NULL );";

$tables[] = "CREATE TABLE mybb_mycode (
  cid INTEGER PRIMARY KEY,
  title varchar(100) NOT NULL default '',
  description TEXT NOT NULL,
  regex TEXT NOT NULL,
  replacement TEXT NOT NULL,
  active int(1) NOT NULL default '0',
  parseorder smallint NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_polls (
  pid INTEGER PRIMARY KEY,
  tid int NOT NULL default '0',
  question varchar(200) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0',
  options TEXT NOT NULL,
  votes TEXT NOT NULL,
  numoptions smallint NOT NULL default '0',
  numvotes smallint NOT NULL default '0',
  timeout bigint(30) NOT NULL default '0',
  closed int(1) NOT NULL default '0',
  multiple int(1) NOT NULL default '0',
  public int(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_pollvotes (
  vid INTEGER PRIMARY KEY,
  pid int NOT NULL default '0',
  uid int NOT NULL default '0',
  voteoption smallint NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_posts (
  pid INTEGER PRIMARY KEY,
  tid int NOT NULL default '0',
  replyto int NOT NULL default '0',
  fid smallint NOT NULL default '0',
  subject varchar(120) NOT NULL default '',
  icon smallint NOT NULL default '0',
  uid int NOT NULL default '0',
  username varchar(80) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0',
  message TEXT NOT NULL,
  ipaddress varchar(30) NOT NULL default '',
  longipaddress int(11) NOT NULL default '0',
  includesig int(1) NOT NULL default '0',
  smilieoff int(1) NOT NULL default '0',
  edituid int NOT NULL default '0',
  edittime int(10) NOT NULL default '0',
  visible int(1) NOT NULL default '0',
  posthash varchar(32) NOT NULL default ''
);";


$tables[] = "CREATE TABLE mybb_privatemessages (
  pmid INTEGER PRIMARY KEY,
  uid int NOT NULL default '0',
  toid int NOT NULL default '0',
  fromid int NOT NULL default '0',
  recipients TEXT NOT NULL,
  folder smallint NOT NULL default '1',
  subject varchar(120) NOT NULL default '',
  icon smallint NOT NULL default '0',
  message TEXT NOT NULL,
  dateline bigint(30) NOT NULL default '0',
  deletetime bigint(30) NOT NULL default '0',
  status int(1) NOT NULL default '0',
  statustime bigint(30) NOT NULL default '0',
  includesig int(1) NOT NULL default '0',
  smilieoff int(1) NOT NULL default '0',
  receipt int(1) NOT NULL default '0',
  readtime bigint(30) NOT NULL default '0'
);";


$tables[] = "CREATE TABLE mybb_profilefields (
  fid INTEGER PRIMARY KEY,
  name varchar(100) NOT NULL default '',
  description TEXT NOT NULL,
  disporder smallint NOT NULL default '0',
  type TEXT NOT NULL,
  length smallint NOT NULL default '0',
  maxlength smallint NOT NULL default '0',
  required int(1) NOT NULL default '0',
  editable int(1) NOT NULL default '0',
  hidden int(1) NOT NULL default '0',
  postnum bigint(30) NOT NULL default '0'
);";


$tables[] = "CREATE TABLE mybb_promotions (
  pid INTEGER PRIMARY KEY,
  title varchar(120) NOT NULL default '',
  description TEXT NOT NULL,
  enabled int(1) NOT NULL default '1',
  logging int(1) NOT NULL default '0',
  posts int NOT NULL default '0',
  posttype varchar(2) NOT NULL default '',
  registered int NOT NULL default '0',
  registeredtype varchar(20) NOT NULL default '',
  reputations int NOT NULL default '0',
  reputationtype varchar(2) NOT NULL default '',
  referrals int NOT NULL default '0',
  referralstype varchar(2) NOT NULL default '',
  requirements varchar(200) NOT NULL default '',
  originalusergroup varchar(120) NOT NULL default '0',
  newusergroup smallint NOT NULL default '0',
  usergrouptype varchar(120) NOT NULL default '0'
);";
	
$tables[] = "CREATE TABLE mybb_promotionlogs (
  plid INTEGER PRIMARY KEY,
  pid int NOT NULL default '0',
  uid int NOT NULL default '0',
  oldusergroup varchar(200) NOT NULL default '0',
  newusergroup smallint NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  type varchar(9) NOT NULL default 'primary'
);";

$tables[] = "CREATE TABLE mybb_reportedposts (
  rid INTEGER PRIMARY KEY,
  pid int NOT NULL default '0',
  tid int NOT NULL default '0',
  fid int NOT NULL default '0',
  uid int NOT NULL default '0',
  reportstatus int(1) NOT NULL default '0',
  reason varchar(250) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_reputation (
  rid INTEGER PRIMARY KEY,
  uid int NOT NULL default '0',
  adduid int NOT NULL default '0',
  pid int NOT NULL default '0',
  reputation bigint(30) NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  comments TEXT NOT NULL );";

$tables[] = "CREATE TABLE mybb_searchlog (
  sid varchar(32) NOT NULL default '',
  uid int NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  ipaddress varchar(120) NOT NULL default '',
  threads LONGTEXT NOT NULL,
  posts LONGTEXT NOT NULL,
  resulttype varchar(10) NOT NULL default '',
  querycache TEXT NOT NULL,
  keywords TEXT NOT NULL
);";

$tables[] = "CREATE TABLE mybb_sessions (
  sid varchar(32) NOT NULL default '',
  uid int NOT NULL default '0',
  ip varchar(40) NOT NULL default '',
  time bigint(30) NOT NULL default '0',
  location varchar(150) NOT NULL default '',
  useragent varchar(100) NOT NULL default '',
  anonymous int(1) NOT NULL default '0',
  nopermission int(1) NOT NULL default '0',
  location1 int(10) NOT NULL default '0',
  location2 int(10) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_settinggroups (
  gid INTEGER PRIMARY KEY,
  name varchar(100) NOT NULL default '',
  title varchar(220) NOT NULL default '',
  description TEXT NOT NULL,
  disporder smallint NOT NULL default '0',
  isdefault int(1) NOT NULL default '0'
);";


$tables[] = "CREATE TABLE mybb_settings (
  sid INTEGER PRIMARY KEY,
  name varchar(120) NOT NULL default '',
  title varchar(120) NOT NULL default '',
  description TEXT NOT NULL,
  optionscode TEXT NOT NULL,
  value TEXT NOT NULL,
  disporder smallint NOT NULL default '0',
  gid smallint NOT NULL default '0',
  isdefault int(1) NOT NULL default '0'
);";


$tables[] = "CREATE TABLE mybb_smilies (
  sid INTEGER PRIMARY KEY,
  name varchar(120) NOT NULL default '',
  find varchar(120) NOT NULL default '',
  image varchar(220) NOT NULL default '',
  disporder smallint NOT NULL default '0',
  showclickable int(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_spiders (
	sid INTEGER PRIMARY KEY,
	name varchar(100) NOT NULL default '',
	theme int NOT NULL default '0',
	language varchar(20) NOT NULL default '',
	usergroup int NOT NULL default '0',
	useragent varchar(200) NOT NULL default '',
	lastvisit bigint(30) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_stats (
	dateline bigint(30) NOT NULL default '0' PRIMARY KEY,
	numusers int unsigned NOT NULL default '0',
	numthreads int unsigned NOT NULL default '0',
	numposts int unsigned NOT NULL default '0'
);";
	
$tables[] = "CREATE TABLE mybb_tasks (
	tid INTEGER PRIMARY KEY,
	title varchar(120) NOT NULL default '',
	description TEXT NOT NULL,
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
	locked bigint(30) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_tasklog (
	lid INTEGER PRIMARY KEY,
	tid int NOT NULL default '0',
	dateline bigint(30) NOT NULL default '0',
	data TEXT NOT NULL );";

$tables[] = "CREATE TABLE mybb_templategroups (
  gid INTEGER PRIMARY KEY,
  prefix varchar(50) NOT NULL default '',
  title varchar(100) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_templates (
  tid INTEGER PRIMARY KEY,
  title varchar(120) NOT NULL default '',
  template TEXT NOT NULL,
  sid int(10) NOT NULL default '0',
  version varchar(20) NOT NULL default '0',
  status varchar(10) NOT NULL default '',
  dateline int(10) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_templatesets (
  sid INTEGER PRIMARY KEY,
  title varchar(120) NOT NULL default ''
);";


$tables[] = "CREATE TABLE mybb_themes (
  tid INTEGER PRIMARY KEY,
  name varchar(100) NOT NULL default '',
  pid smallint NOT NULL default '0',
  def smallint(1) NOT NULL default '0',
  properties TEXT NOT NULL,
  stylesheets TEXT NOT NULL,
  allowedgroups TEXT NOT NULL );";

$tables[] = "CREATE TABLE mybb_themestylesheets(
	sid INTEGER PRIMARY KEY,
	name varchar(30) NOT NULL default '',
	tid int unsigned NOT NULL default '0',
	attachedto TEXT NOT NULL,
	stylesheet TEXT NOT NULL,
	cachefile varchar(100) NOT NULL default '',
	lastmodified bigint(30) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_threadprefixes (
	pid INTEGER PRIMARY KEY,
	prefix varchar(120) NOT NULL default '',
	displaystyle varchar(200) NOT NULL default '',
	forums TEXT NOT NULL,
	groups TEXT NOT NULL
);";

$tables[] = "CREATE TABLE mybb_threadratings (
  rid INTEGER PRIMARY KEY,
  tid int NOT NULL default '0',
  uid int NOT NULL default '0',
  rating smallint NOT NULL default '0',
  ipaddress varchar(30) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_threadviews (
	tid int NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_threads (
  tid INTEGER PRIMARY KEY,
  fid smallint NOT NULL default '0',
  subject varchar(120) NOT NULL default '',
  prefix smallint NOT NULL default '0',
  icon smallint NOT NULL default '0',
  poll int NOT NULL default '0',
  uid int NOT NULL default '0',
  username varchar(80) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0',
  firstpost int NOT NULL default '0',
  lastpost bigint(30) NOT NULL default '0',
  lastposter varchar(120) NOT NULL default '',
  lastposteruid int NOT NULL default '0',
  views int(100) NOT NULL default '0',
  replies int(100) NOT NULL default '0',
  closed varchar(30) NOT NULL default '',
  sticky int(1) NOT NULL default '0',
  numratings smallint NOT NULL default '0',
  totalratings smallint NOT NULL default '0',
  notes TEXT NOT NULL,
  visible int(1) NOT NULL default '0',
  unapprovedposts int(10) NOT NULL default '0',
  attachmentcount int(10) NOT NULL default '0',
  deletetime int(10) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_threadsread (
  tid int NOT NULL default '0',
  uid int NOT NULL default '0',
  dateline int(10) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_threadsubscriptions (
  sid INTEGER PRIMARY KEY,
  uid int NOT NULL default '0',
  tid int NOT NULL default '0',
  notification int(1) NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  subscriptionkey varchar(32) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_userfields (
  ufid int NOT NULL default '0',
  fid1 TEXT NOT NULL,
  fid2 TEXT NOT NULL,
  fid3 TEXT NOT NULL
);";

$tables[] = "CREATE TABLE mybb_usergroups (
  gid INTEGER PRIMARY KEY,
  type smallint(2) NOT NULL default '2',
  title varchar(120) NOT NULL default '',
  description TEXT NOT NULL,
  namestyle varchar(200) NOT NULL default '{username}',
  usertitle varchar(120) NOT NULL default '',
  stars smallint(4) NOT NULL default '0',
  starimage varchar(120) NOT NULL default '',
  image varchar(120) NOT NULL default '',
  disporder smallint(6) NOT NULL default '0',
  isbannedgroup int(1) NOT NULL default '0',
  canview int(1) NOT NULL default '0',
  canviewthreads int(1) NOT NULL default '0',
  canviewprofiles int(1) NOT NULL default '0',
  candlattachments int(1) NOT NULL default '0',
  canpostthreads int(1) NOT NULL default '0',
  canpostreplys int(1) NOT NULL default '0',
  canpostattachments int(1) NOT NULL default '0',
  canratethreads int(1) NOT NULL default '0',
  caneditposts int(1) NOT NULL default '0',
  candeleteposts int(1) NOT NULL default '0',
  candeletethreads int(1) NOT NULL default '0',
  caneditattachments int(1) NOT NULL default '0',
  canpostpolls int(1) NOT NULL default '0',
  canvotepolls int(1) NOT NULL default '0',
  canundovotes int(1) NOT NULL default '0',
  canusepms int(1) NOT NULL default '0',
  cansendpms int(1) NOT NULL default '0',
  cantrackpms int(1) NOT NULL default '0',
  candenypmreceipts int(1) NOT NULL default '0',
  pmquota int(3) NOT NULL default '0',
  maxpmrecipients int(4) NOT NULL default '5',
  cansendemail int(1) NOT NULL default '0',
  cansendemailoverride int(1) NOT NULL default '0',
  maxemails int(3) NOT NULL default '5',
  canviewmemberlist int(1) NOT NULL default '0',
  canviewcalendar int(1) NOT NULL default '0',
  canaddevents int(1) NOT NULL default '0',
  canbypasseventmod int(1) NOT NULL default '0',
  canmoderateevents int(1) NOT NULL default '0',
  canviewonline int(1) NOT NULL default '0',
  canviewwolinvis int(1) NOT NULL default '0',
  canviewonlineips int(1) NOT NULL default '0',
  cancp int(1) NOT NULL default '0',
  issupermod int(1) NOT NULL default '0',
  cansearch int(1) NOT NULL default '0',
  canusercp int(1) NOT NULL default '0',
  canuploadavatars int(1) NOT NULL default '0',
  canratemembers int(1) NOT NULL default '0',
  canchangename int(1) NOT NULL default '0',
  showforumteam int(1) NOT NULL default '0',
  usereputationsystem int(1) NOT NULL default '0',
  cangivereputations int(1) NOT NULL default '0',
  reputationpower bigint(30) NOT NULL default '0',
  maxreputationsday bigint(30) NOT NULL default '0',
  maxreputationsperuser bigint(30) NOT NULL default '0',
  maxreputationsperthread bigint(30) NOT NULL default '0',
  candisplaygroup int(1) NOT NULL default '0',
  attachquota bigint(30) NOT NULL default '0',
  cancustomtitle int(1) NOT NULL default '0',
  canwarnusers int(1) NOT NULL default '0',
  canreceivewarnings int(1) NOT NULL default '0',
  maxwarningsday int NOT NULL default '3',
  canmodcp int(1) NOT NULL default '0',
  showinbirthdaylist int(1) NOT NULL default '0',
  canoverridepm int(1) NOT NULL default '0',
  canusesig int(1) NOT NULL default '0',
  canusesigxposts bigint(30) NOT NULL default '0',
  signofollow int(1) NOT NULL default '0'
);";


$tables[] = "CREATE TABLE mybb_users (
  uid INTEGER PRIMARY KEY,
  username varchar(120) NOT NULL default '',
  password varchar(120) NOT NULL default '',
  salt varchar(10) NOT NULL default '',
  loginkey varchar(50) NOT NULL default '',
  email varchar(220) NOT NULL default '',
  postnum int(10) NOT NULL default '0',
  avatar varchar(200) NOT NULL default '',
  avatardimensions varchar(10) NOT NULL default '',
  avatartype varchar(10) NOT NULL default '0',
  usergroup smallint NOT NULL default '0',
  additionalgroups varchar(200) NOT NULL default '',
  displaygroup smallint NOT NULL default '0',
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
  birthdayprivacy varchar(4) NOT NULL default 'all',
  signature TEXT NOT NULL,
  allownotices int(1) NOT NULL default '0',
  hideemail int(1) NOT NULL default '0',
  subscriptionmethod int(1) NOT NULL default '0',
  invisible int(1) NOT NULL default '0',
  receivepms int(1) NOT NULL default '0',
  receivefrombuddy int(1) NOT NULL default '0',
  pmnotice int(1) NOT NULL default '0',
  pmnotify int(1) NOT NULL default '0',
  threadmode varchar(8) NOT NULL default '',
  showsigs int(1) NOT NULL default '0',
  showavatars int(1) NOT NULL default '0',
  showquickreply int(1) NOT NULL default '0',
  showredirect int(1) NOT NULL default '0',
  ppp smallint(6) NOT NULL default '0',
  tpp smallint(6) NOT NULL default '0',
  daysprune smallint(6) NOT NULL default '0',
  dateformat varchar(4) NOT NULL default '',
  timeformat varchar(4) NOT NULL default '',
  timezone varchar(4) NOT NULL default '',
  dst int(1) NOT NULL default '0',
  dstcorrection int(1) NOT NULL default '0',
  buddylist TEXT NOT NULL,
  ignorelist TEXT NOT NULL,
  style smallint NOT NULL default '0',
  away int(1) NOT NULL default '0',
  awaydate int(10) NOT NULL default '0',
  returndate varchar(15) NOT NULL default '',
  awayreason varchar(200) NOT NULL default '',
  pmfolders TEXT NOT NULL,
  notepad TEXT NOT NULL,
  referrer int NOT NULL default '0',
  referrals int NOT NULL default '0',
  reputation bigint(30) NOT NULL default '0',
  regip varchar(50) NOT NULL default '',
  lastip varchar(50) NOT NULL default '',
  longregip int(11) NOT NULL default '0',
  longlastip int(11) NOT NULL default '0',
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
  suspendsignature int(1) NOT NULL default '0',
  suspendsigtime bigint(30) NOT NULL default '0',
  coppauser int(1) NOT NULL default '0',
  classicpostbit int(1) NOT NULL default '0',
  loginattempts tinyint(2) NOT NULL default '1',
  failedlogin bigint(30) NOT NULL default '0',
  usernotes TEXT NOT NULL
);";


$tables[] = "CREATE TABLE mybb_usertitles (
  utid INTEGER PRIMARY KEY,
  posts int NOT NULL default '0',
  title varchar(250) NOT NULL default '',
  stars smallint(4) NOT NULL default '0',
  starimage varchar(120) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_warninglevels (
	lid INTEGER PRIMARY KEY,
	percentage int(2) NOT NULL default '0',
	action TEXT NOT NULL
);";

$tables[] = "CREATE TABLE mybb_warningtypes (
	tid INTEGER PRIMARY KEY,
	title varchar(120) NOT NULL default '',
	points int NOT NULL default '0',
	expirationtime bigint(30) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_warnings (
	wid INTEGER PRIMARY KEY,
	uid int NOT NULL default '0',
	tid int NOT NULL default '0',
	pid int NOT NULL default '0',
	title varchar(120) NOT NULL default '',
	points int NOT NULL default '0',
	dateline bigint(30) NOT NULL default '0',
	issuedby int NOT NULL default '0',
	expires bigint(30) NOT NULL default '0',
	expired int(1) NOT NULL default '0',
	daterevoked bigint(30) NOT NULL default '0',
	revokedby int NOT NULL default '0',
	revokereason TEXT NOT NULL,
	notes TEXT NOT NULL 
);";
?>