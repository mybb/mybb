
<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: mysql_db_tables.php 2992 2007-04-05 14:43:48Z chris $
 */

$tables[] = "CREATE TABLE mybb_adminlog (
  uid int NOT NULL default '0',
  dateline bigint NOT NULL default '0',
  scriptname varchar(50) NOT NULL default '',
  action varchar(50) NOT NULL default '',
  querystring varchar(150) NOT NULL default '',
  ipaddress varchar(50) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_adminoptions (
  uid int NOT NULL default '0',
  cpstyle varchar(50) NOT NULL default '',
  notes text NOT NULL default '',
  permissions text NOT NULL default '',
  defaultviews text NOT NULL,
  PRIMARY KEY (uid)
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
	visibility int(1) NOT NULL default '0',
	fields text NOT NULL,
	conditions text NOT NULL,
	sortby varchar(20) NOT NULL default '',
	sortorder varchar(4) NOT NULL default '',
	perpage int(4) NOT NULL default '0',
	view_type varchar(6) NOT NULL default '',
	PRIMARY KEY(vid)
);";

$tables[] = "CREATE TABLE mybb_announcements (
  aid serial,
  fid int NOT NULL default '0',
  uid int NOT NULL default '0',
  subject varchar(120) NOT NULL default '',
  message text NOT NULL default '',
  startdate bigint NOT NULL default '0',
  enddate bigint NOT NULL default '0',
  allowhtml char(3) NOT NULL default '',
  allowmycode char(3) NOT NULL default '',
  allowsmilies char(3) NOT NULL default '',
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
  allowhtml char(3) NOT NULL default '',
  allowmycode char(3) NOT NULL default '',
  allowimgcode char(3) NOT NULL default '',
  allowsmilies char(3) NOT NULL default '',
  PRIMARY KEY(cid)
);";

$tables[] = "CREATE TABLE mybb_calendarpermissions (
  cid serial,
  gid int NOT NULL default '0',
  canviewcalendar char(3) NOT NULL default '',
  canaddevents char(3) NOT NULL default '',
  canbypasseventmod char(3) NOT NULL default '',
  canmoderateevents char(3) NOT NULL default ''
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
  timezone int NOT NULL default '0',
  ignoretimezone int NOT NULL default '0',
  usingtime int NOT NULL default '0',
  repeats text NOT NULL,
  PRIMARY KEY  (eid)
);";

$tables[] = "CREATE TABLE mybb_forumpermissions (
  pid serial,
  fid int NOT NULL default '0',
  gid int NOT NULL default '0',
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
  active char(3) NOT NULL default '',
  open char(3) NOT NULL default '',
  threads int NOT NULL default '0',
  posts int NOT NULL default '0',
  lastpost int NOT NULL default '0',
  lastposter varchar(120) NOT NULL default '',
  lastposteruid int NOT NULL default '0',
  lastposttid int NOT NULL default '0',
  lastpostsubject varchar(120) NOT NULL default '',
  allowhtml char(3) NOT NULL default '',
  allowmycode char(3) NOT NULL default '',
  allowsmilies char(3) NOT NULL default '',
  allowimgcode char(3) NOT NULL default '',
  allowpicons char(3) NOT NULL default '',
  allowtratings char(3) NOT NULL default '',
  status int NOT NULL default '1',
  usepostcounts char(3) NOT NULL default '',
  password varchar(50) NOT NULL default '',
  showinjump char(3) NOT NULL default '',
  modposts char(3) NOT NULL default '',
  modthreads char(3) NOT NULL default '',
  mod_edit_posts char(3) NOT NULL default '',
  modattachments char(3) NOT NULL default '',
  style smallint NOT NULL default '0',
  overridestyle char(3) NOT NULL default '',
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
  canmanagemembers char(3) NOT NULL default '',
  canmanagerequests char(3) NOT NULL default '',
  PRIMARY KEY (lid)
);";

$tables[] = "CREATE TABLE mybb_helpdocs (
  hid serial,
  sid smallint NOT NULL default '0',
  name varchar(120) NOT NULL default '',
  description text NOT NULL default '',
  document text NOT NULL default '',
  usetranslation char(3) NOT NULL default '',
  enabled char(3) NOT NULL default '',
  disporder smallint NOT NULL default '0',
  PRIMARY KEY (hid)
);";


$tables[] = "CREATE TABLE mybb_helpsections (
  sid serial,
  name varchar(120) NOT NULL default '',
  description text NOT NULL default '',
  usetranslation char(3) NOT NULL default '',
  enabled char(3) NOT NULL default '',
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
  data text NOT NULL default '',
  ipaddress varchar(50) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_moderators (
  mid serial,
  fid smallint NOT NULL default '0',
  uid int NOT NULL default '0',
  caneditposts char(3) NOT NULL default '',
  candeleteposts char(3) NOT NULL default '',
  canviewips char(3) NOT NULL default '',
  canopenclosethreads char(3) NOT NULL default '',
  canmanagethreads char(3) NOT NULL default '',
  canmovetononmodforum char(3) NOT NULL default '',
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
  active char(3) NOT NULL default '',
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
  closed char(3) NOT NULL default '',
  multiple char(3) NOT NULL default '',
  public char(3) NOT NULL default '',
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
  longipaddress int NOT NULL default '0'
  includesig char(3) NOT NULL default '',
  smilieoff char(3) NOT NULL default '',
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
  includesig char(3) NOT NULL default '',
  smilieoff char(3) NOT NULL default '',
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
  required char(3) NOT NULL default '',
  editable char(3) NOT NULL default '',
  hidden char(3) NOT NULL default '',
  PRIMARY KEY (fid)
);";


$tables[] = "CREATE TABLE mybb_promotions (
  pid serial,
  title varchar(120) NOT NULL default '',
  description text NOT NULL default '',
  enabled int NOT NULL default '1',
  logging int NOT NULL default '0',
  posts int NOT NULL default '0',
  posttype varchar(120) NOT NULL default '',
  registered int NOT NULL default '0',
  registeredtype varchar(120) NOT NULL default '',
  reputations int NOT NULL default '0',
  reputationtype varchar(120) NOT NULL default '',
  requirements varchar(200) NOT NULL default '',
  originalusergroup smallint NOT NULL default '0',
  newusergroup smallint NOT NULL default '0',
  usergrouptype varchar(120) NOT NULL default '0',
  PRIMARY KEY(pid)
);";
	
$tables[] = "CREATE TABLE mybb_promotionlogs (
  plid serial,
  pid int NOT NULL default '0',
  uid int NOT NULL default '0',
  oldusergroup smallint NOT NULL default '0',
  newusergroup smallint NOT NULL default '0',
  dateline bigint NOT NULL default '0',
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
  searchtype varchar(10) NOT NULL default '',
  resulttype varchar(10) NOT NULL default '',
  querycache text NOT NULL default '',
  keywords text NOT NULL default '',
  PRIMARY KEY (sid)
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
  loginattempts smallint NOT NULL default '1',
  failedlogin bigint NOT NULL default '0',
  PRIMARY KEY (sid)
);";

$tables[] = "CREATE TABLE mybb_settinggroups (
  gid serial,
  name varchar(100) NOT NULL default '',
  title varchar(220) NOT NULL default '',
  description text NOT NULL default '',
  disporder smallint NOT NULL default '0',
  isdefault char(3) NOT NULL default '',
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
  PRIMARY KEY (sid)
);";


$tables[] = "CREATE TABLE mybb_smilies (
  sid serial,
  name varchar(120) NOT NULL default '',
  find varchar(120) NOT NULL default '',
  image varchar(220) NOT NULL default '',
  disporder smallint NOT NULL default '0',
  showclickable char(3) NOT NULL default '',
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
	dateline bigint(30) NOT NULL default '0',
	numusers int unsigned NOT NULL default '0',
	numthreads int unsigned NOT NULL default '0',
	numposts int unsigned NOT NULL default '0',
	PRIMARY KEY(dateline)
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
	data text NOT NULL default '',
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
	tid int unsigned NOT NULL default '0',
	attachedto text NOT NULL,
	stylesheet text NOT NULL,
	cachefile varchar(100) NOT NULL default '',
	lastmodified bigint(30) NOT NULL default '0',
	PRIMARY KEY(sid)
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
  disporder smallint NOT NULL,
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
  pmquota int NOT NULL default '0',
  maxpmrecipients int NOT NULL default '5',
  cansendemail char(3) NOT NULL default '',
  maxemails int NOT NULL default '5',
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
  reputationpower bigint NOT NULL default '0',
  maxreputationsday bigint NOT NULL default '0',
  candisplaygroup char(3) NOT NULL default '',
  attachquota bigint NOT NULL default '0',
  cancustomtitle char(3) NOT NULL default '',
  canwarnusers char(3) NOT NULL default '',
  canreceivewarnings char(3) NOT NULL default '',
  maxwarningsday int NOT NULL default '3',
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
  allownotices char(3) NOT NULL default '',
  hideemail char(3) NOT NULL default '',
  subscriptionmethod int NOT NULL default '0',
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
  away char(3) NOT NULL default '',
  awaydate int NOT NULL default '0',
  returndate varchar(15) NOT NULL default '',
  awayreason varchar(200) NOT NULL default '',
  pmfolders text NOT NULL default '',
  notepad text NOT NULL default '',
  referrer int NOT NULL default '0',
  reputation bigint NOT NULL default '0',
  regip varchar(50) NOT NULL default '',
  lastip varchar(50) NOT NULL default '',
  longregip int NOT NULL default '0'
  longlastip int NOT NULL default '0'
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
  coppauser int(1) NOT NULL default '0'
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
	revokereason text NOT NULL,
	notes text NOT NULL,
	PRIMARY KEY(wid)
);";

?>