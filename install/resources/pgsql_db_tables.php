<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: pgsql_db_tables.php 5690 2011-11-30 16:08:49Z Tomm $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}
 
$tables[] = "CREATE TABLE mybb_adminlog (
  uid int NOT NULL default '0',
  ipaddress varchar(50) NOT NULL default '',
  dateline bigint NOT NULL default '0',
  module varchar(50) NOT NULL default '',
  action varchar(50) NOT NULL default '',
  data text NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_adminoptions (
  uid int NOT NULL default '0',
  cpstyle varchar(50) NOT NULL default '',
  codepress int NOT NULL default '1',
  notes text NOT NULL default '',
  permissions text NOT NULL default '',
  defaultviews text NOT NULL,
  loginattempts int NOT NULL default '0',
  loginlockoutexpiry int NOT NULL default '0',
  UNIQUE (uid)
);";

$tables[] = "CREATE TABLE mybb_adminsessions (
	sid varchar(32) NOT NULL default '',
	uid int NOT NULL default '0',
	loginkey varchar(50) NOT NULL default '',
	ip varchar(40) NOT NULL default '',
	dateline bigint NOT NULL default '0',
	lastactive bigint NOT NULL default '0',
	data text NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_adminviews (
    vid serial,
    uid int NOT NULL default '0',
    title varchar(100) NOT NULL default '',
    type varchar(6) NOT NULL default '',
    visibility int NOT NULL default '0',
    fields text NOT NULL,
    conditions text NOT NULL,
	custom_profile_fields text NOT NULL,
    sortby varchar(20) NOT NULL default '',
    sortorder varchar(4) NOT NULL default '',
    perpage int NOT NULL default '0',
    view_type varchar(6) NOT NULL default '',
    PRIMARY KEY (vid)
);"; 

$tables[] = "CREATE TABLE mybb_announcements (
  aid serial,
  fid int NOT NULL default '0',
  uid int NOT NULL default '0',
  subject varchar(120) NOT NULL default '',
  message text NOT NULL default '',
  startdate bigint NOT NULL default '0',
  enddate bigint NOT NULL default '0',
  allowhtml int NOT NULL default '0',
  allowmycode int NOT NULL default '0',
  allowsmilies int NOT NULL default '0',
  PRIMARY KEY (aid)
);";

$tables[] = "CREATE TABLE mybb_attachments (
  aid serial,
  pid int NOT NULL default '0',
  posthash varchar(50) NOT NULL default '',
  uid int NOT NULL default '0',
  filename varchar(120) NOT NULL default '',
  filetype varchar(120) NOT NULL default '',
  filesize int NOT NULL default '0',
  attachname varchar(120) NOT NULL default '',
  downloads int NOT NULL default '0',
  dateuploaded bigint NOT NULL default '0',
  visible int NOT NULL default '0',
  thumbnail varchar(120) NOT NULL default '',
  PRIMARY KEY (aid)
);";

$tables[] = "CREATE TABLE mybb_attachtypes (
  atid serial,
  name varchar(120) NOT NULL default '',
  mimetype varchar(120) NOT NULL default '',
  extension varchar(10) NOT NULL default '',
  maxsize int NOT NULL default '0',
  icon varchar(100) NOT NULL default '',
  PRIMARY KEY (atid)
);";

$tables[] = "CREATE TABLE mybb_awaitingactivation (
  aid serial,
  uid int NOT NULL default '0',
  dateline bigint NOT NULL default '0',
  code varchar(100) NOT NULL default '',
  type char(1) NOT NULL default '',
  oldgroup bigint NOT NULL default '0',
  misc varchar(255) NOT NULL default '',
  PRIMARY KEY (aid)
);";

$tables[] = "CREATE TABLE mybb_badwords (
  bid serial,
  badword varchar(100) NOT NULL default '',
  replacement varchar(100) NOT NULL default '',
  PRIMARY KEY (bid)
);";

$tables[] = "CREATE TABLE mybb_banfilters (
  fid serial,
  filter varchar(200) NOT NULL default '',
  type int NOT NULL default '0',
  lastuse bigint NOT NULL default '0',
  dateline bigint NOT NULL default '0',
  PRIMARY KEY (fid)
);";

$tables[] = "CREATE TABLE mybb_banned (
  uid int NOT NULL default '0',
  gid int NOT NULL default '0',
  oldgroup int NOT NULL default '0',
  oldadditionalgroups text NOT NULL default '',
  olddisplaygroup int NOT NULL default '0',
  admin int NOT NULL default '0',
  dateline bigint NOT NULL default '0',
  bantime varchar(50) NOT NULL default '',
  lifted bigint NOT NULL default '0',
  reason varchar(255) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_calendars (
  cid serial,
  name varchar(100) NOT NULL default '',
  disporder int NOT NULL default '0',
  startofweek int NOT NULL default '0',
  showbirthdays int NOT NULL default '0',
  eventlimit int NOT NULL default '0',
  moderation int NOT NULL default '0',
  allowhtml int NOT NULL default '0',
  allowmycode int NOT NULL default '0',
  allowimgcode int NOT NULL default '0',
  allowvideocode int NOT NULL default '0',
  allowsmilies int NOT NULL default '0',
  PRIMARY KEY(cid)
);";

$tables[] = "CREATE TABLE mybb_calendarpermissions (
  cid serial,
  gid int NOT NULL default '0',
  canviewcalendar int NOT NULL default '0',
  canaddevents int NOT NULL default '0',
  canbypasseventmod int NOT NULL default '0',
  canmoderateevents int NOT NULL default '0'
);";
$tables[] = "CREATE TABLE mybb_captcha (
  imagehash varchar(32) NOT NULL default '',
  imagestring varchar(8) NOT NULL default '',
  dateline bigint NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_datacache (
  title varchar(50) NOT NULL default '',
  cache text NOT NULL default '',
  PRIMARY KEY (title)
);";

$tables[] = "CREATE TABLE mybb_delayedmoderation (
  did serial,
  type varchar(30) NOT NULL default '',
  delaydateline bigint NOT NULL default '0',
  uid int NOT NULL default '0',
  fid smallint NOT NULL default '0',
  tids text NOT NULL,
  dateline bigint NOT NULL default '0',
  inputs text NOT NULL default '',
  PRIMARY KEY (did)
);";

$tables[] = "CREATE TABLE mybb_events (
  eid serial,
  cid int NOT NULL default '0',
  uid int NOT NULL default '0',
  name varchar(120) NOT NULL default '',
  description text NOT NULL,
  visible int NOT NULL default '0',
  private int NOT NULL default '0',
  dateline int NOT NULL default '0',
  starttime int NOT NULL default '0',
  endtime int  NOT NULL default '0',
  timezone varchar(4) NOT NULL default '0',
  ignoretimezone int NOT NULL default '0',
  usingtime int NOT NULL default '0',
  repeats text NOT NULL,
  PRIMARY KEY  (eid)
);";

$tables[] = "CREATE TABLE mybb_forumpermissions (
  pid serial,
  fid int NOT NULL default '0',
  gid int NOT NULL default '0',
  canview int NOT NULL default '0',
  canviewthreads int NOT NULL default '0',
  canonlyviewownthreads int NOT NULL default '0',
  candlattachments int NOT NULL default '0',
  canpostthreads int NOT NULL default '0',
  canpostreplys int NOT NULL default '0',
  canpostattachments int NOT NULL default '0',
  canratethreads int NOT NULL default '0',
  caneditposts int NOT NULL default '0',
  candeleteposts int NOT NULL default '0',
  candeletethreads int NOT NULL default '0',
  caneditattachments int NOT NULL default '0',
  canpostpolls int NOT NULL default '0',
  canvotepolls int NOT NULL default '0',
  cansearch int NOT NULL default '0',
  PRIMARY KEY (pid)
);";

$tables[] = "CREATE TABLE mybb_forums (
  fid serial,
  name varchar(120) NOT NULL default '',
  description text NOT NULL default '',
  linkto varchar(180) NOT NULL default '',
  type char(1) NOT NULL default '',
  pid smallint NOT NULL default '0',
  parentlist text NOT NULL default '',
  disporder smallint NOT NULL default '0',
  active int NOT NULL default '0',
  open int NOT NULL default '0',
  threads int NOT NULL default '0',
  posts int NOT NULL default '0',
  lastpost int NOT NULL default '0',
  lastposter varchar(120) NOT NULL default '',
  lastposteruid int NOT NULL default '0',
  lastposttid int NOT NULL default '0',
  lastpostsubject varchar(120) NOT NULL default '',
  allowhtml int NOT NULL default '0',
  allowmycode int NOT NULL default '0',
  allowsmilies int NOT NULL default '0',
  allowimgcode int NOT NULL default '0',
  allowvideocode int NOT NULL default '0',
  allowpicons int NOT NULL default '0',
  allowtratings int NOT NULL default '0',
  status int NOT NULL default '1',
  usepostcounts int NOT NULL default '0',
  password varchar(50) NOT NULL default '',
  showinjump int NOT NULL default '0',
  modposts int NOT NULL default '0',
  modthreads int NOT NULL default '0',
  mod_edit_posts int NOT NULL default '0',
  modattachments int NOT NULL default '0',
  style smallint NOT NULL default '0',
  overridestyle int NOT NULL default '0',
  rulestype smallint NOT NULL default '0',
  rulestitle varchar(200) NOT NULL default '',
  rules text NOT NULL default '',
  unapprovedthreads int NOT NULL default '0',
  unapprovedposts int NOT NULL default '0',
  defaultdatecut smallint NOT NULL default '0',
  defaultsortby varchar(10) NOT NULL default '',
  defaultsortorder varchar(4) NOT NULL default '',
  PRIMARY KEY (fid)
);";

$tables[] = "CREATE TABLE mybb_forumsread (
  fid int NOT NULL default '0',
  uid int NOT NULL default '0',
  dateline int NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_forumsubscriptions (
  fsid serial,
  fid smallint NOT NULL default '0',
  uid int NOT NULL default '0',
  PRIMARY KEY (fsid)
);";

$tables[] = "CREATE TABLE mybb_groupleaders (
  lid serial,
  gid smallint NOT NULL default '0',
  uid int NOT NULL default '0',
  canmanagemembers int NOT NULL default '0',
  canmanagerequests int NOT NULL default '0',
  PRIMARY KEY (lid)
);";

$tables[] = "CREATE TABLE mybb_helpdocs (
  hid serial,
  sid smallint NOT NULL default '0',
  name varchar(120) NOT NULL default '',
  description text NOT NULL default '',
  document text NOT NULL default '',
  usetranslation int NOT NULL default '0',
  enabled int NOT NULL default '0',
  disporder smallint NOT NULL default '0',
  PRIMARY KEY (hid)
);";


$tables[] = "CREATE TABLE mybb_helpsections (
  sid serial,
  name varchar(120) NOT NULL default '',
  description text NOT NULL default '',
  usetranslation int NOT NULL default '0',
  enabled int NOT NULL default '0',
  disporder smallint NOT NULL default '0',
  PRIMARY KEY (sid)
);";


$tables[] = "CREATE TABLE mybb_icons (
  iid serial,
  name varchar(120) NOT NULL default '',
  path varchar(220) NOT NULL default '',
  PRIMARY KEY (iid)
);";


$tables[] = "CREATE TABLE mybb_joinrequests (
  rid serial,
  uid int NOT NULL default '0',
  gid smallint NOT NULL default '0',
  reason varchar(250) NOT NULL default '',
  dateline bigint NOT NULL default '0',
  PRIMARY KEY (rid)
);";

$tables[] = "CREATE TABLE mybb_massemails (
	mid serial,
	uid int NOT NULL default '0',
	subject varchar(200) NOT NULL default '',
	message text NOT NULL,
	htmlmessage text NOT NULL,
	type int2 NOT NULL default '0',
	format int2 NOT NULL default '0',
	dateline numeric(30,0) NOT NULL default '0',
	senddate numeric(30,0) NOT NULL default '0',
	status int2 NOT NULL default '0',
	sentcount int NOT NULL default '0',
	totalcount int NOT NULL default '0',
	conditions text NOT NULL,
	perpage int NOT NULL default '50',
	PRIMARY KEY(mid)
);";

$tables[] = "CREATE TABLE mybb_mailerrors (
  eid serial,
  subject varchar(200) NOT NULL default '',
  message text NOT NULL default '',
  toaddress varchar(150) NOT NULL default '',
  fromaddress varchar(150) NOT NULL default '',
  dateline bigint NOT NULL default '0',
  error text NOT NULL default '',
  smtperror varchar(200) NOT NULL default '',
  smtpcode int NOT NULL default '0',
  PRIMARY KEY(eid)
);";

$tables[] = "CREATE TABLE mybb_maillogs (
	mid serial,
	subject varchar(200) not null default '',
	message text NOT NULL default '',
	dateline bigint NOT NULL default '0',
	fromuid int NOT NULL default '0',
	fromemail varchar(200) not null default '',
	touid bigint NOT NULL default '0',
	toemail varchar(200) NOT NULL default '',
	tid int NOT NULL default '0',
	ipaddress varchar(20) NOT NULL default '',
	PRIMARY KEY(mid)
);";

$tables[] = "CREATE TABLE mybb_mailqueue (
	mid serial,
	mailto varchar(200) NOT NULL,
	mailfrom varchar(200) NOT NULL,
	subject varchar(200) NOT NULL,
	message text NOT NULL default '',
	headers text NOT NULL default '',
	PRIMARY KEY(mid)
);";

$tables[] = "CREATE TABLE mybb_moderatorlog (
  uid int NOT NULL default '0',
  dateline bigint NOT NULL default '0',
  fid smallint NOT NULL default '0',
  tid int NOT NULL default '0',
  pid int NOT NULL default '0',
  action text NOT NULL default '',
  data text NOT NULL,
  ipaddress varchar(50) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_moderators (
  mid serial,
  fid smallint NOT NULL default '0',
  id int NOT NULL default '0',
  isgroup int NOT NULL default '0',
  caneditposts int NOT NULL default '0',
  candeleteposts int NOT NULL default '0',
  canviewips int NOT NULL default '0',
  canopenclosethreads int NOT NULL default '0',
  canmanagethreads int NOT NULL default '0',
  canmovetononmodforum int NOT NULL default '0',
  canusecustomtools int NOT NULL default '0',
  PRIMARY KEY (mid)
);";

$tables[] = "CREATE TABLE mybb_modtools (
	tid serial,
	name varchar(200) NOT NULL,
	description text NOT NULL default '',
	forums text NOT NULL default '',
	type char(1) NOT NULL default '',
	postoptions text NOT NULL default '',
	threadoptions text NOT NULL default '',
	PRIMARY KEY (tid)
);";

$tables[] = "CREATE TABLE mybb_mycode (
  cid serial,
  title varchar(100) NOT NULL default '',
  description text NOT NULL default '',
  regex text NOT NULL default '',
  replacement text NOT NULL default '',
  active int NOT NULL default '0',
  parseorder smallint NOT NULL default '0',
  PRIMARY KEY(cid)
);";

$tables[] = "CREATE TABLE mybb_polls (
  pid serial,
  tid int NOT NULL default '0',
  question varchar(200) NOT NULL default '',
  dateline bigint NOT NULL default '0',
  options text NOT NULL default '',
  votes text NOT NULL default '',
  numoptions smallint NOT NULL default '0',
  numvotes smallint NOT NULL default '0',
  timeout bigint NOT NULL default '0',
  closed int NOT NULL default '0',
  multiple int NOT NULL default '0',
  public int NOT NULL default '0',
  PRIMARY KEY (pid)
);";

$tables[] = "CREATE TABLE mybb_pollvotes (
  vid serial,
  pid int NOT NULL default '0',
  uid int NOT NULL default '0',
  voteoption smallint NOT NULL default '0',
  dateline bigint NOT NULL default '0',
  PRIMARY KEY (vid)
);";

$tables[] = "CREATE TABLE mybb_posts (
  pid serial,
  tid int NOT NULL default '0',
  replyto int NOT NULL default '0',
  fid smallint NOT NULL default '0',
  subject varchar(120) NOT NULL default '',
  icon smallint NOT NULL default '0',
  uid int NOT NULL default '0',
  username varchar(80) NOT NULL default '',
  dateline bigint NOT NULL default '0',
  message text NOT NULL default '',
  ipaddress varchar(30) NOT NULL default '',
  longipaddress int NOT NULL default '0',
  includesig int NOT NULL default '0',
  smilieoff int NOT NULL default '0',
  edituid int NOT NULL default '0',
  edittime int NOT NULL default '0',
  visible int NOT NULL default '0',
  posthash varchar(32) NOT NULL default '',
  PRIMARY KEY (pid)
);";


$tables[] = "CREATE TABLE mybb_privatemessages (
  pmid serial,
  uid int NOT NULL default '0',
  toid int NOT NULL default '0',
  fromid int NOT NULL default '0',
  recipients text NOT NULL default '',
  folder smallint NOT NULL default '1',
  subject varchar(120) NOT NULL default '',
  icon smallint NOT NULL default '0',
  message text NOT NULL default '',
  dateline bigint NOT NULL default '0',
  deletetime bigint NOT NULL default '0',
  status int NOT NULL default '0',
  statustime bigint NOT NULL default '0',
  includesig int NOT NULL default '0',
  smilieoff int NOT NULL default '0',
  receipt int NOT NULL default '0',
  readtime bigint NOT NULL default '0',
  PRIMARY KEY (pmid)
);";


$tables[] = "CREATE TABLE mybb_profilefields (
  fid serial,
  name varchar(100) NOT NULL default '',
  description text NOT NULL default '',
  disporder smallint NOT NULL default '0',
  type text NOT NULL default '',
  length smallint NOT NULL default '0',
  maxlength smallint NOT NULL default '0',
  required int NOT NULL default '0',
  editable int NOT NULL default '0',
  hidden int NOT NULL default '0',
  postnum int NOT NULL default '0',
  PRIMARY KEY (fid)
);";


$tables[] = "CREATE TABLE mybb_promotions (
  pid serial,
  title varchar(120) NOT NULL default '',
  description text NOT NULL default '',
  enabled int NOT NULL default '1',
  logging int NOT NULL default '0',
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
  usergrouptype varchar(120) NOT NULL default '0',
  PRIMARY KEY(pid)
);";
	
$tables[] = "CREATE TABLE mybb_promotionlogs (
  plid serial,
  pid int NOT NULL default '0',
  uid int NOT NULL default '0',
  oldusergroup varchar(200) NOT NULL default '0',
  newusergroup smallint NOT NULL default '0',
  dateline bigint NOT NULL default '0',
  type varchar(9) NOT NULL default 'primary',
  PRIMARY KEY(plid)
);";

$tables[] = "CREATE TABLE mybb_reportedposts (
  rid serial,
  pid int NOT NULL default '0',
  tid int NOT NULL default '0',
  fid int NOT NULL default '0',
  uid int NOT NULL default '0',
  reportstatus int NOT NULL default '0',
  reason varchar(250) NOT NULL default '',
  dateline bigint NOT NULL default '0',
  PRIMARY KEY (rid)
);";

$tables[] = "CREATE TABLE mybb_reputation (
  rid serial,
  uid int NOT NULL default '0',
  adduid int NOT NULL default '0',
  pid int NOT NULL default '0',
  reputation bigint NOT NULL default '0',
  dateline bigint NOT NULL default '0',
  comments text NOT NULL default '',
  PRIMARY KEY(rid)
);";

$tables[] = "CREATE TABLE mybb_searchlog (
  sid varchar(32) NOT NULL default '',
  uid int NOT NULL default '0',
  dateline bigint NOT NULL default '0',
  ipaddress varchar(120) NOT NULL default '',
  threads text NOT NULL default '',
  posts text NOT NULL default '',
  resulttype varchar(10) NOT NULL default '',
  querycache text NOT NULL default '',
  keywords text NOT NULL default '',
  UNIQUE (sid)
);";

$tables[] = "CREATE TABLE mybb_sessions (
  sid varchar(32) NOT NULL default '',
  uid int NOT NULL default '0',
  ip varchar(40) NOT NULL default '',
  time bigint NOT NULL default '0',
  location varchar(150) NOT NULL default '',
  useragent varchar(100) NOT NULL default '',
  anonymous int NOT NULL default '0',
  nopermission int NOT NULL default '0',
  location1 int NOT NULL default '0',
  location2 int NOT NULL default '0',
  UNIQUE (sid)
);";

$tables[] = "CREATE TABLE mybb_settinggroups (
  gid serial,
  name varchar(100) NOT NULL default '',
  title varchar(220) NOT NULL default '',
  description text NOT NULL default '',
  disporder smallint NOT NULL default '0',
  isdefault int NOT NULL default '0',
  PRIMARY KEY (gid)
);";


$tables[] = "CREATE TABLE mybb_settings (
  sid serial,
  name varchar(120) NOT NULL default '',
  title varchar(120) NOT NULL default '',
  description text NOT NULL default '',
  optionscode text NOT NULL default '',
  value text NOT NULL default '',
  disporder smallint NOT NULL default '0',
  gid smallint NOT NULL default '0',
  isdefault int NOT NULL default '0',
  PRIMARY KEY (sid)
);";


$tables[] = "CREATE TABLE mybb_smilies (
  sid serial,
  name varchar(120) NOT NULL default '',
  find varchar(120) NOT NULL default '',
  image varchar(220) NOT NULL default '',
  disporder smallint NOT NULL default '0',
  showclickable int NOT NULL default '0',
  PRIMARY KEY (sid)
);";

$tables[] = "CREATE TABLE mybb_spiders (
	sid serial,
	name varchar(100) NOT NULL default '',
	theme int NOT NULL default '0',
	language varchar(20) NOT NULL default '',
	usergroup int NOT NULL default '0',
	useragent varchar(200) NOT NULL default '',
	lastvisit bigint NOT NULL default '0',
	PRIMARY KEY(sid)
);";

$tables[] = "CREATE TABLE mybb_stats (
	dateline numeric(30,0) NOT NULL default '0',
	numusers numeric(10,0) NOT NULL default '0',
	numthreads numeric(10,0) NOT NULL default '0',
	numposts numeric(10,0) NOT NULL default '0',
	UNIQUE (dateline)
);";
	
$tables[] = "CREATE TABLE mybb_tasks (
	tid serial,
	title varchar(120) NOT NULL default '',
	description text NOT NULL default '',
	file varchar(30) NOT NULL default '',
	minute varchar(200) NOT NULL default '',
	hour varchar(200) NOT NULL default '',
	day varchar(100) NOT NULL default '',
	month varchar(30) NOT NULL default '',
	weekday varchar(15) NOT NULL default '',
	nextrun bigint NOT NULL default '0',
	lastrun bigint NOT NULL default '0',
	enabled int NOT NULL default '1',
	logging int NOT NULL default '0',
	locked bigint NOT NULL default '0',
	PRIMARY KEY(tid)
);";

$tables[] = "CREATE TABLE mybb_tasklog (
	lid serial,
	tid int NOT NULL default '0',
	dateline bigint NOT NULL default '0',
	data text NOT NULL,
	PRIMARY KEY(lid)
);";

$tables[] = "CREATE TABLE mybb_templategroups (
  gid serial,
  prefix varchar(50) NOT NULL default '',
  title varchar(100) NOT NULL default '',
  PRIMARY KEY (gid)
);";

$tables[] = "CREATE TABLE mybb_templates (
  tid serial,
  title varchar(120) NOT NULL default '',
  template text NOT NULL default '',
  sid int NOT NULL default '0',
  version varchar(20) NOT NULL default '0',
  status varchar(10) NOT NULL default '',
  dateline int NOT NULL default '0',
  PRIMARY KEY (tid)
);";

$tables[] = "CREATE TABLE mybb_templatesets (
  sid serial,
  title varchar(120) NOT NULL default '',
  PRIMARY KEY (sid)
);";


$tables[] = "CREATE TABLE mybb_themes (
  tid serial,
  name varchar(100) NOT NULL default '',
  pid smallint NOT NULL default '0',
  def smallint NOT NULL default '0',
  properties text NOT NULL default '',
  stylesheets text NOT NULL default '',
  allowedgroups text NOT NULL default '',
  PRIMARY KEY (tid)
);";

$tables[] = "CREATE TABLE mybb_themestylesheets(
	sid serial,
	name varchar(30) NOT NULL default '',
	tid numeric(10,0) NOT NULL default '0',
	attachedto text NOT NULL,
	stylesheet text NOT NULL,
	cachefile varchar(100) NOT NULL default '',
	lastmodified numeric(30,0) NOT NULL default '0',
	PRIMARY KEY(sid)
);";

$tables[] = "CREATE TABLE mybb_threadprefixes (
	pid serial,
	prefix varchar(120) NOT NULL default '',
	displaystyle varchar(200) NOT NULL default '',
	forums text NOT NULL,
	groups text NOT NULL,
	PRIMARY KEY(pid)
);";

$tables[] = "CREATE TABLE mybb_threadratings (
  rid serial,
  tid int NOT NULL default '0',
  uid int NOT NULL default '0',
  rating smallint NOT NULL default '0',
  ipaddress varchar(30) NOT NULL default '',
  PRIMARY KEY (rid)
);";

$tables[] = "CREATE TABLE mybb_threadviews (
	tid int NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_threads (
  tid serial,
  fid smallint NOT NULL default '0',
  subject varchar(120) NOT NULL default '',
  prefix smallint NOT NULL default '0',
  icon smallint NOT NULL default '0',
  poll int NOT NULL default '0',
  uid int NOT NULL default '0',
  username varchar(80) NOT NULL default '',
  dateline bigint NOT NULL default '0',
  firstpost int NOT NULL default '0',
  lastpost bigint NOT NULL default '0',
  lastposter varchar(120) NOT NULL default '',
  lastposteruid int NOT NULL default '0',
  views int NOT NULL default '0',
  replies int NOT NULL default '0',
  closed varchar(30) NOT NULL default '',
  sticky int NOT NULL default '0',
  numratings smallint NOT NULL default '0',
  totalratings smallint NOT NULL default '0',
  notes text NOT NULL default '',
  visible int NOT NULL default '0',
  unapprovedposts int NOT NULL default '0',
  attachmentcount int NOT NULL default '0',
  deletetime int NOT NULL default '0',
  PRIMARY KEY (tid)
);";

$tables[] = "CREATE TABLE mybb_threadsread (
  tid int NOT NULL default '0',
  uid int NOT NULL default '0',
  dateline int NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_threadsubscriptions (
  sid serial,
  uid int NOT NULL default '0',
  tid int NOT NULL default '0',
  notification int NOT NULL default '0',
  dateline bigint NOT NULL default '0',
  subscriptionkey varchar(32) NOT NULL default '',
  PRIMARY KEY (sid)
);";

$tables[] = "CREATE TABLE mybb_userfields (
  ufid int NOT NULL default '0',
  fid1 text NOT NULL default '',
  fid2 text NOT NULL default '',
  fid3 text NOT NULL default '',
  PRIMARY KEY (ufid)
);";
$query = $db->write_query("SELECT column_name
						  FROM information_schema.constraint_column_usage
						  WHERE table_name = '".$config['tableprefix']."userfields' 
						  AND constraint_name = '".$config['tableprefix']."userfields_pkey'
						  LIMIT 1");
$main_field = $db->fetch_field($query, 'column_name');
if(!empty($main_field))
{
	$tables[] = "DROP SEQUENCE mybb_userfields_ufid_seq;";
}
$tables[] = "CREATE SEQUENCE mybb_userfields_ufid_seq;";

$tables[] = "CREATE TABLE mybb_usergroups (
  gid serial,
  type smallint NOT NULL default '2',
  title varchar(120) NOT NULL default '',
  description text NOT NULL default '',
  namestyle varchar(200) NOT NULL default '{username}',
  usertitle varchar(120) NOT NULL default '',
  stars smallint NOT NULL default '0',
  starimage varchar(120) NOT NULL default '',
  image varchar(120) NOT NULL default '',
  disporder smallint NOT NULL default '0',
  isbannedgroup int NOT NULL default '0',
  canview int NOT NULL default '0',
  canviewthreads int NOT NULL default '0',
  canviewprofiles int NOT NULL default '0',
  candlattachments int NOT NULL default '0',
  canpostthreads int NOT NULL default '0',
  canpostreplys int NOT NULL default '0',
  canpostattachments int NOT NULL default '0',
  canratethreads int NOT NULL default '0',
  caneditposts int NOT NULL default '0',
  candeleteposts int NOT NULL default '0',
  candeletethreads int NOT NULL default '0',
  caneditattachments int NOT NULL default '0',
  canpostpolls int NOT NULL default '0',
  canvotepolls int NOT NULL default '0',
  canundovotes int NOT NULL default '0',
  canusepms int NOT NULL default '0',
  cansendpms int NOT NULL default '0',
  cantrackpms int NOT NULL default '0',
  candenypmreceipts int NOT NULL default '0',
  pmquota int NOT NULL default '0',
  maxpmrecipients int NOT NULL default '5',
  cansendemail int NOT NULL default '0',
  cansendemailoverride int NOT NULL default '0',
  maxemails int NOT NULL default '5',
  canviewmemberlist int NOT NULL default '0',
  canviewcalendar int NOT NULL default '0',
  canaddevents int NOT NULL default '0',
  canbypasseventmod int NOT NULL default '0',
  canmoderateevents int NOT NULL default '0',
  canviewonline int NOT NULL default '0',
  canviewwolinvis int NOT NULL default '0',
  canviewonlineips int NOT NULL default '0',
  cancp int NOT NULL default '0',
  issupermod int NOT NULL default '0',
  cansearch int NOT NULL default '0',
  canusercp int NOT NULL default '0',
  canuploadavatars int NOT NULL default '0',
  canratemembers int NOT NULL default '0',
  canchangename int NOT NULL default '0',
  showforumteam int NOT NULL default '0',
  usereputationsystem int NOT NULL default '0',
  cangivereputations int NOT NULL default '0',
  reputationpower bigint NOT NULL default '0',
  maxreputationsday bigint NOT NULL default '0',
  maxreputationsperuser bigint NOT NULL default '0',
  maxreputationsperthread bigint NOT NULL default '0',
  candisplaygroup int NOT NULL default '0',
  attachquota bigint NOT NULL default '0',
  cancustomtitle int NOT NULL default '0',
  canwarnusers int NOT NULL default '0',
  canreceivewarnings int NOT NULL default '0',
  maxwarningsday int NOT NULL default '3',
  canmodcp int NOT NULL default '0',
  showinbirthdaylist int NOT NULL default '0',
  canoverridepm int NOT NULL default '0',
  canusesig int NOT NULL default '0',
  canusesigxposts int NOT NULL default '0',
  signofollow int NOT NULL default '0',
  PRIMARY KEY (gid)
);";


$tables[] = "CREATE TABLE mybb_users (
  uid serial,
  username varchar(120) NOT NULL default '',
  password varchar(120) NOT NULL default '',
  salt varchar(10) NOT NULL default '',
  loginkey varchar(50) NOT NULL default '',
  email varchar(220) NOT NULL default '',
  postnum int NOT NULL default '0',
  avatar varchar(200) NOT NULL default '',
  avatardimensions varchar(10) NOT NULL default '',
  avatartype varchar(10) NOT NULL default '0',
  usergroup smallint NOT NULL default '0',
  additionalgroups varchar(200) NOT NULL default '',
  displaygroup smallint NOT NULL default '0',
  usertitle varchar(250) NOT NULL default '',
  regdate bigint NOT NULL default '0',
  lastactive bigint NOT NULL default '0',
  lastvisit bigint NOT NULL default '0',
  lastpost bigint NOT NULL default '0',
  website varchar(200) NOT NULL default '',
  icq varchar(10) NOT NULL default '',
  aim varchar(50) NOT NULL default '',
  yahoo varchar(50) NOT NULL default '',
  msn varchar(75) NOT NULL default '',
  birthday varchar(15) NOT NULL default '',
  birthdayprivacy varchar(4) NOT NULL default 'all',
  signature text NOT NULL default '',
  allownotices int NOT NULL default '0',
  hideemail int NOT NULL default '0',
  subscriptionmethod int NOT NULL default '0',
  invisible int NOT NULL default '0',
  receivepms int NOT NULL default '0',
  receivefrombuddy int NOT NULL default '0',
  pmnotice int NOT NULL default '0',
  pmnotify int NOT NULL default '0',
  threadmode varchar(8) NOT NULL default '',
  showsigs int NOT NULL default '0',
  showavatars int NOT NULL default '0',
  showquickreply int NOT NULL default '0',
  showredirect int NOT NULL default '0',
  ppp smallint NOT NULL default '0',
  tpp smallint NOT NULL default '0',
  daysprune smallint NOT NULL default '0',
  dateformat varchar(4) NOT NULL default '',
  timeformat varchar(4) NOT NULL default '',
  timezone varchar(4) NOT NULL default '',
  dst int NOT NULL default '0',
  dstcorrection int NOT NULL default '0',
  buddylist text NOT NULL default '',
  ignorelist text NOT NULL default '',
  style smallint NOT NULL default '0',
  away int NOT NULL default '0',
  awaydate int NOT NULL default '0',
  returndate varchar(15) NOT NULL default '',
  awayreason varchar(200) NOT NULL default '',
  pmfolders text NOT NULL default '',
  notepad text NOT NULL default '',
  referrer int NOT NULL default '0',
  referrals int NOT NULL default '0',
  reputation bigint NOT NULL default '0',
  regip varchar(50) NOT NULL default '',
  lastip varchar(50) NOT NULL default '',
  longregip int NOT NULL default '0',
  longlastip int NOT NULL default '0',
  language varchar(50) NOT NULL default '',
  timeonline bigint NOT NULL default '0',
  showcodebuttons int NOT NULL default '1',
  totalpms int NOT NULL default '0',
  unreadpms int NOT NULL default '0',
  warningpoints int NOT NULL default '0',
  moderateposts int NOT NULL default '0',
  moderationtime bigint NOT NULL default '0',
  suspendposting int NOT NULL default '0',
  suspensiontime bigint NOT NULL default '0',
  suspendsignature int NOT NULL default '0',
  suspendsigtime bigint NOT NULL default '0',
  coppauser int NOT NULL default '0',
  classicpostbit int NOT NULL default '0',
  loginattempts smallint NOT NULL default '1',
  failedlogin bigint NOT NULL default '0',
  usernotes text NOT NULL default '',
  PRIMARY KEY (uid)
);";


$tables[] = "CREATE TABLE mybb_usertitles (
  utid serial,
  posts int NOT NULL default '0',
  title varchar(250) NOT NULL default '',
  stars smallint NOT NULL default '0',
  starimage varchar(120) NOT NULL default '',
  PRIMARY KEY (utid)
);";

$tables[] = "CREATE TABLE mybb_warninglevels (
	lid serial,
	percentage int NOT NULL default '0',
	action text NOT NULL,
	PRIMARY KEY(lid)
);";

$tables[] = "CREATE TABLE mybb_warningtypes (
	tid serial,
	title varchar(120) NOT NULL default '',
	points int NOT NULL default '0',
	expirationtime bigint NOT NULL default '0',
	PRIMARY KEY(tid)
);";

$tables[] = "CREATE TABLE mybb_warnings (
	wid serial,
	uid int NOT NULL default '0',
	tid int NOT NULL default '0',
	pid int NOT NULL default '0',
	title varchar(120) NOT NULL default '',
	points int NOT NULL default '0',
	dateline bigint NOT NULL default '0',
	issuedby int NOT NULL default '0',
	expires bigint NOT NULL default '0',
	expired int NOT NULL default '0',
	daterevoked bigint NOT NULL default '0',
	revokedby int NOT NULL default '0',
	revokereason text NOT NULL default '',
	notes text NOT NULL default '',
	PRIMARY KEY(wid)
);";

?>