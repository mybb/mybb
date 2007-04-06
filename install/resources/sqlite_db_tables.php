<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id: mysql_db_tables.php 2962 2007-03-25 12:31:36Z chris $
 */

$tables[] = "CREATE TABLE mybb_adminlog (
  uid int NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  scriptname varchar(50) NOT NULL default '',
  action varchar(50) NOT NULL default '',
  querystring varchar(150) NOT NULL default '',
  ipaddress varchar(50) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_adminoptions (
  uid int(10) NOT NULL default '0',
  cpstyle varchar(50) NOT NULL default '',
  notes text NOT NULL,
  permissions text NOT NULL,
);";

$tables[] = "CREATE TABLE mybb_adminsessions (
	sid varchar(32) NOT NULL default '',
	uid int NOT NULL default '0',
	loginkey varchar(50) NOT NULL default '',
	ip varchar(40) NOT NULL default '',
	dateline bigint(30) NOT NULL default '0',
	lastactive bigint(30) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_announcements (
  aid INTEGER PRIMARY KEY,
  fid int(10) NOT NULL default '0',
  uid int NOT NULL default '0',
  subject varchar(120) NOT NULL default '',
  message text NOT NULL,
  startdate bigint(30) NOT NULL default '0',
  enddate bigint(30) NOT NULL default '0',
  allowhtml char(3) NOT NULL default '',
  allowmycode char(3) NOT NULL default '',
  allowsmilies char(3) NOT NULL default ''
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
  oldadditionalgroups text NOT NULL,
  olddisplaygroup int NOT NULL default '0',
  admin int NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  bantime varchar(50) NOT NULL default '',
  lifted bigint(30) NOT NULL default '0',
  reason varchar(255) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_captcha (
  imagehash varchar(32) NOT NULL default '',
  imagestring varchar(8) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_datacache (
  title varchar(50) NOT NULL default '',
  cache mediumtext NOT NULL
);";

$tables[] = "CREATE TABLE mybb_events (
  eid INTEGER PRIMARY KEY,
  subject varchar(120) NOT NULL default '',
  author int NOT NULL default '0',
  start_day tinyint(2) NOT NULL,
  start_month tinyint(2) NOT NULL,
  start_year smallint(4) NOT NULL,
  end_day tinyint(2) NOT NULL,
  end_month tinyint(2) NOT NULL,
  end_year smallint(4) NOT NULL,
  repeat_days varchar(20) NOT NULL,
  start_time_hours varchar(2) NOT NULL,
  start_time_mins varchar(2) NOT NULL,
  end_time_hours varchar(2) NOT NULL,
  end_time_mins varchar(2) NOT NULL,
  description text NOT NULL,
  private char(3) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_forumpermissions (
  pid INTEGER PRIMARY KEY,
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
  cansearch char(3) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_forums (
  fid INTEGER PRIMARY KEY,
  name varchar(120) NOT NULL default '',
  description text NOT NULL,
  linkto varchar(180) NOT NULL default '',
  type char(1) NOT NULL default '',
  pid smallint NOT NULL default '0',
  parentlist text NOT NULL,
  disporder smallint NOT NULL default '0',
  active char(3) NOT NULL default '',
  open char(3) NOT NULL default '',
  threads int NOT NULL default '0',
  posts int NOT NULL default '0',
  lastpost int(10) NOT NULL default '0',
  lastposter varchar(120) NOT NULL default '',
  lastposteruid int(10) NOT NULL default '0',
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
  style smallint NOT NULL default '0',
  overridestyle char(3) NOT NULL default '',
  rulestype smallint(1) NOT NULL default '0',
  rulestitle varchar(200) NOT NULL default '',
  rules text NOT NULL,
  unapprovedthreads int(10) NOT NULL default '0',
  unapprovedposts int(10) NOT NULL default '0',
  defaultdatecut smallint(4) NOT NULL default '0',
  defaultsortby varchar(10) NOT NULL default '',
  defaultsortorder varchar(4) NOT NULL default ''
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
  canmanagemembers char(3) NOT NULL default '',
  canmanagerequests char(3) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_helpdocs (
  hid INTEGER PRIMARY KEY,
  sid smallint NOT NULL default '0',
  name varchar(120) NOT NULL default '',
  description text NOT NULL,
  document text NOT NULL,
  usetranslation char(3) NOT NULL default '',
  enabled char(3) NOT NULL default '',
  disporder smallint NOT NULL default '0'
);";


$tables[] = "CREATE TABLE mybb_helpsections (
  sid INTEGER PRIMARY KEY,
  name varchar(120) NOT NULL default '',
  description text NOT NULL,
  usetranslation char(3) NOT NULL default '',
  enabled char(3) NOT NULL default '',
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

$tables[] = "CREATE TABLE mybb_mailerrors (
  eid INTEGER PRIMARY KEY,
  subject varchar(200) NOT NULL default '',
  message text NOT NULL,
  toaddress varchar(150) NOT NULL default '',
  fromaddress varchar(150) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0',
  error text NOT NULL,
  smtperror varchar(200) NOT NULL default '',
  smtpcode int(5) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_maillogs (
	mid INTEGER PRIMARY KEY,
	subject varchar(200) not null default '',
	message text NOT NULL,
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
	mailto varchar(200) NOT NULL,
	mailfrom varchar(200) NOT NULL,
	subject varchar(200) NOT NULL,
	message text NOT NULL,
	headers text NOT NULL
);";

$tables[] = "CREATE TABLE mybb_moderatorlog (
  uid int NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  fid smallint NOT NULL default '0',
  tid int NOT NULL default '0',
  pid int NOT NULL default '0',
  action text NOT NULL,
  data text NOT NULL,
  ipaddress varchar(50) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_moderators (
  mid INTEGER PRIMARY KEY,
  fid smallint NOT NULL default '0',
  uid int NOT NULL default '0',
  caneditposts char(3) NOT NULL default '',
  candeleteposts char(3) NOT NULL default '',
  canviewips char(3) NOT NULL default '',
  canopenclosethreads char(3) NOT NULL default '',
  canmanagethreads char(3) NOT NULL default '',
  canmovetononmodforum char(3) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_modtools (
	tid INTEGER PRIMARY KEY,
	name varchar(200) NOT NULL,
	description text NOT NULL,
	forums text NOT NULL,
	type char(1) NOT NULL default '',
	postoptions text NOT NULL,
	threadoptions text NOT NULL
);";

$tables[] = "CREATE TABLE mybb_mycode (
  cid INTEGER PRIMARY KEY,
  title varchar(100) NOT NULL default '',
  description text NOT NULL,
  regex text NOT NULL,
  replacement text NOT NULL,
  active char(3) NOT NULL default '',
  parseorder smallint NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_polls (
  pid INTEGER PRIMARY KEY,
  tid int NOT NULL default '0',
  question varchar(200) NOT NULL default '',
  dateline bigint(30) NOT NULL default '0',
  options text NOT NULL,
  votes text NOT NULL,
  numoptions smallint NOT NULL default '0',
  numvotes smallint NOT NULL default '0',
  timeout bigint(30) NOT NULL default '0',
  closed char(3) NOT NULL default '',
  multiple char(3) NOT NULL default '',
  public char(3) NOT NULL default ''
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
  message text NOT NULL,
  ipaddress varchar(30) NOT NULL default '',
  includesig char(3) NOT NULL default '',
  smilieoff char(3) NOT NULL default '',
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
  recipients text NOT NULL,
  folder smallint NOT NULL default '1',
  subject varchar(120) NOT NULL default '',
  icon smallint NOT NULL default '0',
  message text NOT NULL,
  dateline bigint(30) NOT NULL default '0',
  deletetime bigint(30) NOT NULL default '0',
  status int(1) NOT NULL default '0',
  statustime bigint(30) NOT NULL default '0',
  includesig char(3) NOT NULL default '',
  smilieoff char(3) NOT NULL default '',
  receipt int(1) NOT NULL default '0',
  readtime bigint(30) NOT NULL default '0'
);";


$tables[] = "CREATE TABLE mybb_profilefields (
  fid INTEGER PRIMARY KEY,
  name varchar(100) NOT NULL default '',
  description text NOT NULL,
  disporder smallint NOT NULL default '0',
  type text NOT NULL,
  length smallint NOT NULL default '0',
  maxlength smallint NOT NULL default '0',
  required char(3) NOT NULL default '',
  editable char(3) NOT NULL default '',
  hidden char(3) NOT NULL default ''
);";


$tables[] = "CREATE TABLE mybb_promotions (
  pid INTEGER PRIMARY KEY,
  title varchar(120) NOT NULL default '',
  description text NOT NULL,
  enabled int(1) NOT NULL default '1',
  logging int(1) NOT NULL default '0',
  posts int NOT NULL default '0',
  posttype varchar(120) NOT NULL default '',
  registered int NOT NULL default '0',
  registeredtype varchar(120) NOT NULL default '',
  reputations int NOT NULL default '0',
  reputationtype varchar(120) NOT NULL default '',
  requirements varchar(200) NOT NULL default '',
  originalusergroup varchar(200) NOT NULL default '0',
  newusergroup smallint NOT NULL default '0',
  usergrouptype varchar(120) NOT NULL default '0'
);";
	
$tables[] = "CREATE TABLE mybb_promotionlogs (
  plid INTEGER PRIMARY KEY,
  pid int NOT NULL default '0',
  uid int NOT NULL default '0',
  oldusergroup smallint NOT NULL default '0',
  newusergroup smallint NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0'
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
  reputation bigint(30) NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  comments text NOT NULL
);";

$tables[] = "CREATE TABLE mybb_searchlog (
  sid varchar(32) NOT NULL default '',
  uid int NOT NULL default '0',
  dateline bigint(30) NOT NULL default '0',
  ipaddress varchar(120) NOT NULL default '',
  threads text NOT NULL,
  posts text NOT NULL,
  searchtype varchar(10) NOT NULL default '',
  resulttype varchar(10) NOT NULL default '',
  querycache text NOT NULL,
  keywords text NOT NULL
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
  location2 int(10) NOT NULL default '0',
  loginattempts tinyint(2) NOT NULL default '1',
  failedlogin bigint(30) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_settinggroups (
  gid INTEGER PRIMARY KEY,
  name varchar(100) NOT NULL default '',
  title varchar(220) NOT NULL default '',
  description text NOT NULL,
  disporder smallint NOT NULL default '0',
  isdefault char(3) NOT NULL default ''
);";


$tables[] = "CREATE TABLE mybb_settings (
  sid INTEGER PRIMARY KEY,
  name varchar(120) NOT NULL default '',
  title varchar(120) NOT NULL default '',
  description text NOT NULL,
  optionscode text NOT NULL,
  value text NOT NULL,
  disporder smallint NOT NULL default '0',
  gid smallint NOT NULL default '0'
);";


$tables[] = "CREATE TABLE mybb_smilies (
  sid INTEGER PRIMARY KEY,
  name varchar(120) NOT NULL default '',
  find varchar(120) NOT NULL default '',
  image varchar(220) NOT NULL default '',
  disporder smallint NOT NULL default '0',
  showclickable char(3) NOT NULL default ''
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
	
$tables[] = "CREATE TABLE mybb_tasks (
	tid INTEGER PRIMARY KEY,
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
	locked bigint(30) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_tasklog (
	lid INTEGER PRIMARY KEY,
	tid int NOT NULL default '0',
	dateline bigint(30) NOT NULL default '0',
	data text NOT NULL
);";

$tables[] = "CREATE TABLE mybb_templategroups (
  gid INTEGER PRIMARY KEY,
  prefix varchar(50) NOT NULL default '',
  title varchar(100) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_templates (
  tid INTEGER PRIMARY KEY,
  title varchar(120) NOT NULL default '',
  template text NOT NULL,
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
  css text NOT NULL,
  cssbits text NOT NULL,
  themebits text NOT NULL,
  extracss text NOT NULL,
  allowedgroups text NOT NULL,
  csscached bigint(30) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_threadratings (
  rid INTEGER PRIMARY KEY,
  tid int NOT NULL default '0',
  uid int NOT NULL default '0',
  rating smallint NOT NULL default '0',
  ipaddress varchar(30) NOT NULL default ''
);";


$tables[] = "CREATE TABLE mybb_threads (
  tid INTEGER PRIMARY KEY,
  fid smallint NOT NULL default '0',
  subject varchar(120) NOT NULL default '',
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
  notes text NOT NULL,
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
  fid1 text NOT NULL,
  fid2 text NOT NULL,
  fid3 text NOT NULL
);";

$tables[] = "CREATE TABLE mybb_usergroups (
  gid INTEGER PRIMARY KEY,
  type smallint(2) NOT NULL default '2',
  title varchar(120) NOT NULL default '',
  description text NOT NULL,
  namestyle varchar(200) NOT NULL default '{username}',
  usertitle varchar(120) NOT NULL default '',
  stars smallint(4) NOT NULL default '0',
  starimage varchar(120) NOT NULL default '',
  image varchar(120) NOT NULL default '',
  disporder smallint(6) NOT NULL,
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
  canaddpublicevents char(3) NOT NULL default '',
  canaddprivateevents char(3) NOT NULL default '',
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
  cancustomtitle char(3) NOT NULL default ''
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
  style smallint NOT NULL default '0',
  away char(3) NOT NULL default '',
  awaydate int(10) NOT NULL default '0',
  returndate varchar(15) NOT NULL default '',
  awayreason varchar(200) NOT NULL default '',
  pmfolders text NOT NULL,
  notepad text NOT NULL,
  referrer int NOT NULL default '0',
  reputation bigint(30) NOT NULL default '0',
  regip varchar(50) NOT NULL default '',
  language varchar(50) NOT NULL default '',
  timeonline bigint(30) NOT NULL default '0',
  showcodebuttons int(1) NOT NULL default '1',
  totalpms int(10) NOT NULL default '0',
  unreadpms int(10) NOT NULL default '0'
);";


$tables[] = "CREATE TABLE mybb_usertitles (
  utid INTEGER PRIMARY KEY,
  posts int NOT NULL default '0',
  title varchar(250) NOT NULL default '',
  stars smallint(4) NOT NULL default '0',
  starimage varchar(120) NOT NULL default ''
);";

?>