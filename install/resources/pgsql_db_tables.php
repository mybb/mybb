<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$tables[] = "CREATE TABLE mybb_adminlog (
  uid int NOT NULL default '0',
  ipaddress bytea NOT NULL default '',
  dateline int NOT NULL default '0',
  module varchar(50) NOT NULL default '',
  action varchar(50) NOT NULL default '',
  data text NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_adminoptions (
  uid int NOT NULL default '0',
  cpstyle varchar(50) NOT NULL default '',
  cplanguage varchar(50) NOT NULL default '',
  codepress smallint NOT NULL default '1',
  notes text NOT NULL default '',
  permissions text NOT NULL default '',
  defaultviews text NOT NULL,
  loginattempts smallint NOT NULL default '0',
  loginlockoutexpiry int NOT NULL default '0',
  2fasecret varchar(16) NOT NULL default '',
  recovery_codes varchar(177) NOT NULL default '',
  UNIQUE (uid)
);";

$tables[] = "CREATE TABLE mybb_adminsessions (
	sid varchar(32) NOT NULL default '',
	uid int NOT NULL default '0',
	loginkey varchar(50) NOT NULL default '',
	ip bytea NOT NULL default '',
	dateline int NOT NULL default '0',
	lastactive int NOT NULL default '0',
	data text NOT NULL default '',
	useragent varchar(100) NOT NULL default '',
	authenticated smallint NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_adminviews (
    vid serial,
    uid int NOT NULL default '0',
    title varchar(100) NOT NULL default '',
    type varchar(6) NOT NULL default '',
    visibility smallint NOT NULL default '0',
    fields text NOT NULL,
    conditions text NOT NULL,
	custom_profile_fields text NOT NULL,
    sortby varchar(20) NOT NULL default '',
    sortorder varchar(4) NOT NULL default '',
    perpage smallint NOT NULL default '0',
    view_type varchar(6) NOT NULL default '',
    PRIMARY KEY (vid)
);";

$tables[] = "CREATE TABLE mybb_announcements (
  aid serial,
  fid int NOT NULL default '0',
  uid int NOT NULL default '0',
  subject varchar(120) NOT NULL default '',
  message text NOT NULL default '',
  startdate int NOT NULL default '0',
  enddate int NOT NULL default '0',
  allowhtml smallint NOT NULL default '0',
  allowmycode smallint NOT NULL default '0',
  allowsmilies smallint NOT NULL default '0',
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
  dateuploaded int NOT NULL default '0',
  visible smallint NOT NULL default '0',
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
  dateline int NOT NULL default '0',
  code varchar(100) NOT NULL default '',
  type char(1) NOT NULL default '',
  validated smallint NOT NULL default '0',
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
  type smallint NOT NULL default '0',
  lastuse int NOT NULL default '0',
  dateline int NOT NULL default '0',
  PRIMARY KEY (fid)
);";

$tables[] = "CREATE TABLE mybb_banned (
  uid int NOT NULL default '0',
  gid int NOT NULL default '0',
  oldgroup int NOT NULL default '0',
  oldadditionalgroups text NOT NULL default '',
  olddisplaygroup int NOT NULL default '0',
  admin int NOT NULL default '0',
  dateline int NOT NULL default '0',
  bantime varchar(50) NOT NULL default '',
  lifted int NOT NULL default '0',
  reason varchar(255) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_buddyrequests (
 id serial,
 uid int NOT NULL,
 touid int NOT NULL,
 date int NOT NULL,
 PRIMARY KEY (id)
);";

$tables[] = "CREATE TABLE mybb_calendars (
  cid serial,
  name varchar(100) NOT NULL default '',
  disporder smallint NOT NULL default '0',
  startofweek smallint NOT NULL default '0',
  showbirthdays smallint NOT NULL default '0',
  eventlimit smallint NOT NULL default '0',
  moderation smallint NOT NULL default '0',
  allowhtml smallint NOT NULL default '0',
  allowmycode smallint NOT NULL default '0',
  allowimgcode smallint NOT NULL default '0',
  allowvideocode smallint NOT NULL default '0',
  allowsmilies smallint NOT NULL default '0',
  PRIMARY KEY(cid)
);";

$tables[] = "CREATE TABLE mybb_calendarpermissions (
  cid serial,
  gid int NOT NULL default '0',
  canviewcalendar smallint NOT NULL default '0',
  canaddevents smallint NOT NULL default '0',
  canbypasseventmod smallint NOT NULL default '0',
  canmoderateevents smallint NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_captcha (
  imagehash varchar(32) NOT NULL default '',
  imagestring varchar(8) NOT NULL default '',
  dateline int NOT NULL default '0',
  used smallint NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_datacache (
  title varchar(50) NOT NULL default '',
  cache text NOT NULL default '',
  PRIMARY KEY (title)
);";

$tables[] = "CREATE TABLE mybb_delayedmoderation (
  did serial,
  type varchar(30) NOT NULL default '',
  delaydateline int NOT NULL default '0',
  uid int NOT NULL default '0',
  fid smallint NOT NULL default '0',
  tids text NOT NULL,
  dateline int NOT NULL default '0',
  inputs text NOT NULL default '',
  PRIMARY KEY (did)
);";

$tables[] = "CREATE TABLE mybb_events (
  eid serial,
  cid int NOT NULL default '0',
  uid int NOT NULL default '0',
  name varchar(120) NOT NULL default '',
  description text NOT NULL,
  visible smallint NOT NULL default '0',
  private smallint NOT NULL default '0',
  dateline int NOT NULL default '0',
  starttime int NOT NULL default '0',
  endtime int NOT NULL default '0',
  timezone varchar(5) NOT NULL default '',
  ignoretimezone smallint NOT NULL default '0',
  usingtime smallint NOT NULL default '0',
  repeats text NOT NULL,
  PRIMARY KEY  (eid)
);";

$tables[] = "CREATE TABLE mybb_forumpermissions (
  pid serial,
  fid int NOT NULL default '0',
  gid int NOT NULL default '0',
  canview smallint NOT NULL default '0',
  canviewthreads smallint NOT NULL default '0',
  canonlyviewownthreads smallint NOT NULL default '0',
  candlattachments smallint NOT NULL default '0',
  canpostthreads smallint NOT NULL default '0',
  canpostreplys smallint NOT NULL default '0',
  canonlyreplyownthreads smallint NOT NULL default '0',
  canpostattachments smallint NOT NULL default '0',
  canratethreads smallint NOT NULL default '0',
  caneditposts smallint NOT NULL default '0',
  candeleteposts smallint NOT NULL default '0',
  candeletethreads smallint NOT NULL default '0',
  caneditattachments smallint NOT NULL default '0',
  modposts smallint NOT NULL default '0',
  modthreads smallint NOT NULL default '0',
  mod_edit_posts smallint NOT NULL default '0',
  modattachments smallint NOT NULL default '0',
  canpostpolls smallint NOT NULL default '0',
  canvotepolls smallint NOT NULL default '0',
  cansearch smallint NOT NULL default '0',
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
  active smallint NOT NULL default '0',
  open smallint NOT NULL default '0',
  threads int NOT NULL default '0',
  posts int NOT NULL default '0',
  lastpost int NOT NULL default '0',
  lastposter varchar(120) NOT NULL default '',
  lastposteruid int NOT NULL default '0',
  lastposttid int NOT NULL default '0',
  lastpostsubject varchar(120) NOT NULL default '',
  allowhtml smallint NOT NULL default '0',
  allowmycode smallint NOT NULL default '0',
  allowsmilies smallint NOT NULL default '0',
  allowimgcode smallint NOT NULL default '0',
  allowvideocode smallint NOT NULL default '0',
  allowpicons smallint NOT NULL default '0',
  allowtratings smallint NOT NULL default '0',
  usepostcounts smallint NOT NULL default '0',
  usethreadcounts smallint NOT NULL default '0',
  requireprefix smallint NOT NULL default '0',
  password varchar(50) NOT NULL default '',
  showinjump smallint NOT NULL default '0',
  style smallint NOT NULL default '0',
  overridestyle smallint NOT NULL default '0',
  rulestype smallint NOT NULL default '0',
  rulestitle varchar(200) NOT NULL default '',
  rules text NOT NULL default '',
  unapprovedthreads int NOT NULL default '0',
  unapprovedposts int NOT NULL default '0',
  deletedthreads int NOT NULL default '0',
  deletedposts int NOT NULL default '0',
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
  canmanagemembers smallint NOT NULL default '0',
  canmanagerequests smallint NOT NULL default '0',
  caninvitemembers smallint NOT NULL default '0',
  PRIMARY KEY (lid)
);";

$tables[] = "CREATE TABLE mybb_helpdocs (
  hid serial,
  sid smallint NOT NULL default '0',
  name varchar(120) NOT NULL default '',
  description text NOT NULL default '',
  document text NOT NULL default '',
  usetranslation smallint NOT NULL default '0',
  enabled smallint NOT NULL default '0',
  disporder smallint NOT NULL default '0',
  PRIMARY KEY (hid)
);";

$tables[] = "CREATE TABLE mybb_helpsections (
  sid serial,
  name varchar(120) NOT NULL default '',
  description text NOT NULL default '',
  usetranslation smallint NOT NULL default '0',
  enabled smallint NOT NULL default '0',
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
  dateline int NOT NULL default '0',
  invite smallint NOT NULL default '0',
  PRIMARY KEY (rid)
);";

$tables[] = "CREATE TABLE mybb_massemails (
	mid serial,
	uid int NOT NULL default '0',
	subject varchar(200) NOT NULL default '',
	message text NOT NULL,
	htmlmessage text NOT NULL,
	type smallint NOT NULL default '0',
	format smallint NOT NULL default '0',
	dateline numeric(30,0) NOT NULL default '0',
	senddate numeric(30,0) NOT NULL default '0',
	status smallint NOT NULL default '0',
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
  dateline int NOT NULL default '0',
  error text NOT NULL default '',
  smtperror varchar(200) NOT NULL default '',
  smtpcode smallint NOT NULL default '0',
  PRIMARY KEY(eid)
);";

$tables[] = "CREATE TABLE mybb_maillogs (
	mid serial,
	subject varchar(200) not null default '',
	message text NOT NULL default '',
	dateline int NOT NULL default '0',
	fromuid int NOT NULL default '0',
	fromemail varchar(200) not null default '',
	touid int NOT NULL default '0',
	toemail varchar(200) NOT NULL default '',
	tid int NOT NULL default '0',
	ipaddress bytea NOT NULL default '',
	type smallint NOT NULL default '0',
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
  dateline int NOT NULL default '0',
  fid smallint NOT NULL default '0',
  tid int NOT NULL default '0',
  pid int NOT NULL default '0',
  action text NOT NULL default '',
  data text NOT NULL,
  ipaddress bytea NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_moderators (
  mid serial,
  fid smallint NOT NULL default '0',
  id int NOT NULL default '0',
  isgroup smallint NOT NULL default '0',
  caneditposts smallint NOT NULL default '0',
  cansoftdeleteposts smallint NOT NULL default '0',
  canrestoreposts smallint NOT NULL default '0',
  candeleteposts smallint NOT NULL default '0',
  cansoftdeletethreads smallint NOT NULL default '0',
  canrestorethreads smallint NOT NULL default '0',
  candeletethreads smallint NOT NULL default '0',
  canviewips smallint NOT NULL default '0',
  canviewunapprove smallint NOT NULL default '0',
  canviewdeleted smallint NOT NULL default '0',
  canopenclosethreads smallint NOT NULL default '0',
  canstickunstickthreads smallint NOT NULL default '0',
  canapproveunapprovethreads smallint NOT NULL default '0',
  canapproveunapproveposts smallint NOT NULL default '0',
  canapproveunapproveattachs smallint NOT NULL default '0',
  canmanagethreads smallint NOT NULL default '0',
  canmanagepolls smallint NOT NULL default '0',
  canpostclosedthreads smallint NOT NULL default '0',
  canmovetononmodforum smallint NOT NULL default '0',
  canusecustomtools smallint NOT NULL default '0',
  canmanageannouncements smallint NOT NULL default '0',
  canmanagereportedposts smallint NOT NULL default '0',
  canviewmodlog smallint NOT NULL default '0',
  PRIMARY KEY (mid)
);";

$tables[] = "CREATE TABLE mybb_modtools (
	tid serial,
	name varchar(200) NOT NULL,
	description text NOT NULL default '',
	forums text NOT NULL default '',
	groups text NOT NULL default '',
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
  active smallint NOT NULL default '0',
  parseorder smallint NOT NULL default '0',
  PRIMARY KEY(cid)
);";

$tables[] = "CREATE TABLE mybb_polls (
  pid serial,
  tid int NOT NULL default '0',
  question varchar(200) NOT NULL default '',
  dateline int NOT NULL default '0',
  options text NOT NULL default '',
  votes text NOT NULL default '',
  numoptions smallint NOT NULL default '0',
  numvotes int NOT NULL default '0',
  timeout int NOT NULL default '0',
  closed smallint NOT NULL default '0',
  multiple smallint NOT NULL default '0',
  public smallint NOT NULL default '0',
  maxoptions smallint NOT NULL default '0',
  PRIMARY KEY (pid)
);";

$tables[] = "CREATE TABLE mybb_pollvotes (
  vid serial,
  pid int NOT NULL default '0',
  uid int NOT NULL default '0',
  voteoption smallint NOT NULL default '0',
  dateline int NOT NULL default '0',
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
  dateline int NOT NULL default '0',
  message text NOT NULL default '',
  ipaddress  bytea NOT NULL default '',
  includesig smallint NOT NULL default '0',
  smilieoff smallint NOT NULL default '0',
  edituid int NOT NULL default '0',
  edittime int NOT NULL default '0',
  editreason varchar(150) NOT NULL default '',
  visible smallint NOT NULL default '0',
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
  dateline int NOT NULL default '0',
  deletetime int NOT NULL default '0',
  status smallint NOT NULL default '0',
  statustime int NOT NULL default '0',
  includesig smallint NOT NULL default '0',
  smilieoff smallint NOT NULL default '0',
  receipt smallint NOT NULL default '0',
  readtime int NOT NULL default '0',
  ipaddress bytea NOT NULL default '',
  PRIMARY KEY (pmid)
);";

$tables[] = "CREATE TABLE mybb_profilefields (
  fid serial,
  name varchar(100) NOT NULL default '',
  description text NOT NULL default '',
  disporder smallint NOT NULL default '0',
  type text NOT NULL default '',
  regex text NOT NULL default '',
  length smallint NOT NULL default '0',
  maxlength smallint NOT NULL default '0',
  required smallint NOT NULL default '0',
  registration smallint NOT NULL default '0',
  profile smallint NOT NULL default '0',
  postbit smallint NOT NULL default '0',
  viewableby text NOT NULL default '-1',
  editableby text NOT NULL default '-1',
  postnum smallint NOT NULL default '0',
  allowhtml smallint NOT NULL default '0',
  allowmycode smallint NOT NULL default '0',
  allowsmilies smallint NOT NULL default '0',
  allowimgcode smallint NOT NULL default '0',
  allowvideocode smallint NOT NULL default '0',
  PRIMARY KEY (fid)
);";

$tables[] = "CREATE TABLE mybb_promotions (
  pid serial,
  title varchar(120) NOT NULL default '',
  description text NOT NULL default '',
  enabled smallint NOT NULL default '1',
  logging smallint NOT NULL default '0',
  posts int NOT NULL default '0',
  posttype varchar(2) NOT NULL default '',
  threads int NOT NULL default '0',
  threadtype varchar(2) NOT NULL default '',
  registered int NOT NULL default '0',
  registeredtype varchar(20) NOT NULL default '',
  online int NOT NULL default '0',
  onlinetype varchar(20) NOT NULL default '',
  reputations int NOT NULL default '0',
  reputationtype varchar(2) NOT NULL default '',
  referrals int NOT NULL default '0',
  referralstype varchar(2) NOT NULL default '',
  warnings int NOT NULL default '0',
  warningstype varchar(2) NOT NULL default '',
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
  dateline int NOT NULL default '0',
  type varchar(9) NOT NULL default 'primary',
  PRIMARY KEY(plid)
);";

$tables[] = "CREATE TABLE mybb_questions (
  qid serial,
  question varchar(200) NOT NULL default '',
  answer varchar(150) NOT NULL default '',
  shown int NOT NULL default 0,
  correct int NOT NULL default 0,
  incorrect int NOT NULL default 0,
  active smallint NOT NULL default '0',
  PRIMARY KEY (qid)
);";

$tables[] = "CREATE TABLE mybb_questionsessions (
  sid varchar(32) NOT NULL default '',
  qid int NOT NULL default '0',
  dateline int NOT NULL default '0',
  UNIQUE (sid)
);";

$tables[] = "CREATE TABLE mybb_reportedcontent (
  rid serial,
  id int NOT NULL default '0',
  id2 int NOT NULL default '0',
  id3 int NOT NULL default '0',
  uid int NOT NULL default '0',
  reportstatus smallint NOT NULL default '0',
  reason varchar(250) NOT NULL default '',
  type varchar(50) NOT NULL default '',
  reports int NOT NULL default '0',
  reporters text NOT NULL default '',
  dateline int NOT NULL default '0',
  lastreport int NOT NULL default '0',
  PRIMARY KEY (rid)
);";

$tables[] = "CREATE TABLE mybb_reputation (
  rid serial,
  uid int NOT NULL default '0',
  adduid int NOT NULL default '0',
  pid int NOT NULL default '0',
  reputation smallint NOT NULL default '0',
  dateline int NOT NULL default '0',
  comments text NOT NULL default '',
  PRIMARY KEY(rid)
);";

$tables[] = "CREATE TABLE mybb_searchlog (
  sid varchar(32) NOT NULL default '',
  uid int NOT NULL default '0',
  dateline int NOT NULL default '0',
  ipaddress bytea NOT NULL default '',
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
  ip bytea NOT NULL default '',
  time int NOT NULL default '0',
  location varchar(150) NOT NULL default '',
  useragent varchar(100) NOT NULL default '',
  anonymous smallint NOT NULL default '0',
  nopermission smallint NOT NULL default '0',
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
  isdefault smallint NOT NULL default '0',
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
  isdefault smallint NOT NULL default '0',
  PRIMARY KEY (sid)
);";

$tables[] = "CREATE TABLE mybb_smilies (
  sid serial,
  name varchar(120) NOT NULL default '',
  find text NOT NULL,
  image varchar(220) NOT NULL default '',
  disporder smallint NOT NULL default '0',
  showclickable smallint NOT NULL default '0',
  PRIMARY KEY (sid)
);";

$tables[] = "CREATE TABLE mybb_spamlog (
  sid serial,
  username varchar(120) NOT NULL DEFAULT '',
  email varchar(220) NOT NULL DEFAULT '',
  ipaddress bytea NOT NULL default '',
  dateline numeric(30,0) NOT NULL default '0',
  data text NOT NULL default '',
  PRIMARY KEY (sid)
);";

$tables[] = "CREATE TABLE mybb_spiders (
	sid serial,
	name varchar(100) NOT NULL default '',
	theme smallint NOT NULL default '0',
	language varchar(20) NOT NULL default '',
	usergroup smallint NOT NULL default '0',
	useragent varchar(200) NOT NULL default '',
	lastvisit int NOT NULL default '0',
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
	nextrun int NOT NULL default '0',
	lastrun int NOT NULL default '0',
	enabled smallint NOT NULL default '1',
	logging smallint NOT NULL default '0',
	locked int NOT NULL default '0',
	PRIMARY KEY(tid)
);";

$tables[] = "CREATE TABLE mybb_tasklog (
	lid serial,
	tid int NOT NULL default '0',
	dateline int NOT NULL default '0',
	data text NOT NULL,
	PRIMARY KEY(lid)
);";

$tables[] = "CREATE TABLE mybb_templategroups (
  gid serial,
  prefix varchar(50) NOT NULL default '',
  title varchar(100) NOT NULL default '',
  isdefault smallint NOT NULL default '0',
  PRIMARY KEY (gid)
);";

$tables[] = "CREATE TABLE mybb_templates (
  tid serial,
  title varchar(120) NOT NULL default '',
  template text NOT NULL default '',
  sid smallint NOT NULL default '0',
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
  ipaddress bytea NOT NULL default '',
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
  dateline int NOT NULL default '0',
  firstpost int NOT NULL default '0',
  lastpost int NOT NULL default '0',
  lastposter varchar(120) NOT NULL default '',
  lastposteruid int NOT NULL default '0',
  views int NOT NULL default '0',
  replies int NOT NULL default '0',
  closed varchar(30) NOT NULL default '',
  sticky smallint NOT NULL default '0',
  numratings smallint NOT NULL default '0',
  totalratings smallint NOT NULL default '0',
  notes text NOT NULL default '',
  visible smallint NOT NULL default '0',
  unapprovedposts int NOT NULL default '0',
  deletedposts int NOT NULL default '0',
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
  notification smallint NOT NULL default '0',
  dateline int NOT NULL default '0',
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
  isbannedgroup smallint NOT NULL default '0',
  canview smallint NOT NULL default '0',
  canviewthreads smallint NOT NULL default '0',
  canviewprofiles smallint NOT NULL default '0',
  candlattachments smallint NOT NULL default '0',
  canviewboardclosed smallint NOT NULL default '0',
  canpostthreads smallint NOT NULL default '0',
  canpostreplys smallint NOT NULL default '0',
  canpostattachments smallint NOT NULL default '0',
  canratethreads smallint NOT NULL default '0',
  modposts smallint NOT NULL default '0',
  modthreads smallint NOT NULL default '0',
  mod_edit_posts smallint NOT NULL default '0',
  modattachments smallint NOT NULL default '0',
  caneditposts smallint NOT NULL default '0',
  candeleteposts smallint NOT NULL default '0',
  candeletethreads smallint NOT NULL default '0',
  caneditattachments smallint NOT NULL default '0',
  canpostpolls smallint NOT NULL default '0',
  canvotepolls smallint NOT NULL default '0',
  canundovotes smallint NOT NULL default '0',
  canusepms smallint NOT NULL default '0',
  cansendpms smallint NOT NULL default '0',
  cantrackpms smallint NOT NULL default '0',
  candenypmreceipts smallint NOT NULL default '0',
  pmquota int NOT NULL default '0',
  maxpmrecipients int NOT NULL default '5',
  cansendemail smallint NOT NULL default '0',
  cansendemailoverride smallint NOT NULL default '0',
  maxemails int NOT NULL default '5',
  emailfloodtime int NOT NULL default '5',
  canviewmemberlist smallint NOT NULL default '0',
  canviewcalendar smallint NOT NULL default '0',
  canaddevents smallint NOT NULL default '0',
  canbypasseventmod smallint NOT NULL default '0',
  canmoderateevents smallint NOT NULL default '0',
  canviewonline smallint NOT NULL default '0',
  canviewwolinvis smallint NOT NULL default '0',
  canviewonlineips smallint NOT NULL default '0',
  cancp smallint NOT NULL default '0',
  issupermod smallint NOT NULL default '0',
  cansearch smallint NOT NULL default '0',
  canusercp smallint NOT NULL default '0',
  canuploadavatars smallint NOT NULL default '0',
  canratemembers smallint NOT NULL default '0',
  canchangename smallint NOT NULL default '0',
  canbereported smallint NOT NULL default '0',
  canchangewebsite smallint NOT NULL default '1',
  showforumteam smallint NOT NULL default '0',
  usereputationsystem smallint NOT NULL default '0',
  cangivereputations smallint NOT NULL default '0',
  candeletereputations smallint NOT NULL default '0',
  reputationpower int NOT NULL default '0',
  maxreputationsday int NOT NULL default '0',
  maxreputationsperuser int NOT NULL default '0',
  maxreputationsperthread int NOT NULL default '0',
  candisplaygroup smallint NOT NULL default '0',
  attachquota int NOT NULL default '0',
  cancustomtitle smallint NOT NULL default '0',
  canwarnusers smallint NOT NULL default '0',
  canreceivewarnings smallint NOT NULL default '0',
  maxwarningsday int NOT NULL default '3',
  canmodcp smallint NOT NULL default '0',
  showinbirthdaylist smallint NOT NULL default '0',
  canoverridepm smallint NOT NULL default '0',
  canusesig smallint NOT NULL default '0',
  canusesigxposts smallint NOT NULL default '0',
  signofollow smallint NOT NULL default '0',
  edittimelimit smallint NOT NULL default '0',
  maxposts smallint NOT NULL default '0',
  showmemberlist smallint NOT NULL default '1',
  canmanageannounce smallint NOT NULL default '0',
  canmanagemodqueue smallint NOT NULL default '0',
  canmanagereportedcontent smallint NOT NULL default '0',
  canviewmodlogs smallint NOT NULL default '0',
  caneditprofiles smallint NOT NULL default '0',
  canbanusers smallint NOT NULL default '0',
  canviewwarnlogs smallint NOT NULL default '0',
  canuseipsearch smallint NOT NULL default '0',
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
  threadnum int NOT NULL default '0',
  avatar varchar(200) NOT NULL default '',
  avatardimensions varchar(10) NOT NULL default '',
  avatartype varchar(10) NOT NULL default '0',
  usergroup smallint NOT NULL default '0',
  additionalgroups varchar(200) NOT NULL default '',
  displaygroup smallint NOT NULL default '0',
  usertitle varchar(250) NOT NULL default '',
  regdate int NOT NULL default '0',
  lastactive int NOT NULL default '0',
  lastvisit int NOT NULL default '0',
  lastpost int NOT NULL default '0',
  website varchar(200) NOT NULL default '',
  icq varchar(10) NOT NULL default '',
  aim varchar(50) NOT NULL default '',
  yahoo varchar(50) NOT NULL default '',
  skype varchar(75) NOT NULL default '',
  google varchar(75) NOT NULL default '',
  birthday varchar(15) NOT NULL default '',
  birthdayprivacy varchar(4) NOT NULL default 'all',
  signature text NOT NULL default '',
  allownotices smallint NOT NULL default '0',
  hideemail smallint NOT NULL default '0',
  subscriptionmethod smallint NOT NULL default '0',
  invisible smallint NOT NULL default '0',
  receivepms smallint NOT NULL default '0',
  receivefrombuddy smallint NOT NULL default '0',
  pmnotice smallint NOT NULL default '0',
  pmnotify smallint NOT NULL default '0',
  buddyrequestspm smallint NOT NULL default '1',
  buddyrequestsauto smallint NOT NULL default '0',
  threadmode varchar(8) NOT NULL default '',
  showimages smallint NOT NULL default '0',
  showvideos smallint NOT NULL default '0',
  showsigs smallint NOT NULL default '0',
  showavatars smallint NOT NULL default '0',
  showquickreply smallint NOT NULL default '0',
  showredirect smallint NOT NULL default '0',
  ppp smallint NOT NULL default '0',
  tpp smallint NOT NULL default '0',
  daysprune smallint NOT NULL default '0',
  dateformat varchar(4) NOT NULL default '',
  timeformat varchar(4) NOT NULL default '',
  timezone varchar(5) NOT NULL default '',
  dst smallint NOT NULL default '0',
  dstcorrection smallint NOT NULL default '0',
  buddylist text NOT NULL default '',
  ignorelist text NOT NULL default '',
  style smallint NOT NULL default '0',
  away smallint NOT NULL default '0',
  awaydate int NOT NULL default '0',
  returndate varchar(15) NOT NULL default '',
  awayreason varchar(200) NOT NULL default '',
  pmfolders text NOT NULL default '',
  notepad text NOT NULL default '',
  referrer int NOT NULL default '0',
  referrals int NOT NULL default '0',
  reputation int NOT NULL default '0',
  regip bytea NOT NULL default '',
  lastip bytea NOT NULL default '',
  language varchar(50) NOT NULL default '',
  timeonline int NOT NULL default '0',
  showcodebuttons smallint NOT NULL default '1',
  totalpms int NOT NULL default '0',
  unreadpms int NOT NULL default '0',
  warningpoints int NOT NULL default '0',
  moderateposts int NOT NULL default '0',
  moderationtime int NOT NULL default '0',
  suspendposting smallint NOT NULL default '0',
  suspensiontime int NOT NULL default '0',
  suspendsignature smallint NOT NULL default '0',
  suspendsigtime int NOT NULL default '0',
  coppauser smallint NOT NULL default '0',
  classicpostbit smallint NOT NULL default '0',
  loginattempts smallint NOT NULL default '1',
  usernotes text NOT NULL default '',
  sourceeditor smallint NOT NULL default '0',
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
	percentage smallint NOT NULL default '0',
	action text NOT NULL,
	PRIMARY KEY(lid)
);";

$tables[] = "CREATE TABLE mybb_warningtypes (
	tid serial,
	title varchar(120) NOT NULL default '',
	points smallint NOT NULL default '0',
	expirationtime int NOT NULL default '0',
	PRIMARY KEY(tid)
);";

$tables[] = "CREATE TABLE mybb_warnings (
	wid serial,
	uid int NOT NULL default '0',
	tid int NOT NULL default '0',
	pid int NOT NULL default '0',
	title varchar(120) NOT NULL default '',
	points smallint NOT NULL default '0',
	dateline int NOT NULL default '0',
	issuedby int NOT NULL default '0',
	expires int NOT NULL default '0',
	expired smallint NOT NULL default '0',
	daterevoked int NOT NULL default '0',
	revokedby int NOT NULL default '0',
	revokereason text NOT NULL default '',
	notes text NOT NULL default '',
	PRIMARY KEY(wid)
);";


