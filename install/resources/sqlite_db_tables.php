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
	ipaddress blob(16) NOT NULL default '',
	dateline int unsigned NOT NULL default '0',
	module varchar(50) NOT NULL default '',
	action varchar(50) NOT NULL default '',
	data TEXT NOT NULL
 );";

$tables[] = "CREATE TABLE mybb_adminoptions (
	uid int unsigned NOT NULL default '0',
	cpstyle varchar(50) NOT NULL default '',
	cplanguage varchar(50) NOT NULL default '',
	codepress tinyint(1) NOT NULL default '1',
	notes TEXT NOT NULL,
	permissions TEXT NOT NULL,
	defaultviews TEXT NOT NULL,
	loginattempts smallint unsigned NOT NULL default '0',
	loginlockoutexpiry int unsigned NOT NULL default '0'
	2fasecret varchar(16) NOT NULL default '',
	recovery_codes varchar(177) NOT NULL default '',
 );";

$tables[] = "CREATE TABLE mybb_adminsessions (
	sid varchar(32) NOT NULL default '',
	uid int NOT NULL default '0',
	loginkey varchar(50) NOT NULL default '',
	ip blob(16) NOT NULL default '',
	dateline int unsigned NOT NULL default '0',
	lastactive int unsigned NOT NULL default '0',
	data TEXT NOT NULL,
	useragent varchar(100) NOT NULL default '',
	authenticated tinyint(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_adminviews (
	vid INTEGER PRIMARY KEY,
	uid int(10) NOT NULL default '0',
	title varchar(100) NOT NULL default '',
	type varchar(6) NOT NULL default '',
	visibility tinyint(1) NOT NULL default '0',
	fields TEXT NOT NULL,
	conditions TEXT NOT NULL,
	custom_profile_fields TEXT NOT NULL,
	sortby varchar(20) NOT NULL default '',
	sortorder varchar(4) NOT NULL default '',
	perpage smallint(4) NOT NULL default '0',
	view_type varchar(6) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_announcements (
	aid INTEGER PRIMARY KEY,
	fid int NOT NULL default '0',
	uid int NOT NULL default '0',
	subject varchar(120) NOT NULL default '',
	message TEXT NOT NULL,
	startdate int unsigned NOT NULL default '0',
	enddate int unsigned NOT NULL default '0',
	allowhtml tinyint(1) NOT NULL default '0',
	allowmycode tinyint(1) NOT NULL default '0',
	allowsmilies tinyint(1) NOT NULL default '0'
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
	dateuploaded int unsigned NOT NULL default '0',
	visible tinyint(1) NOT NULL default '0',
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
	dateline int unsigned NOT NULL default '0',
	code varchar(100) NOT NULL default '',
	type char(1) NOT NULL default '',
	validated tinyint(1) NOT NULL default '0',
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
	type tinyint(1) NOT NULL default '0',
	lastuse int unsigned NOT NULL default '0',
	dateline int unsigned NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_banned (
	uid int NOT NULL default '0',
	gid int NOT NULL default '0',
	oldgroup int NOT NULL default '0',
	oldadditionalgroups TEXT NOT NULL,
	olddisplaygroup int NOT NULL default '0',
	admin int NOT NULL default '0',
	dateline int unsigned NOT NULL default '0',
	bantime varchar(50) NOT NULL default '',
	lifted int unsigned NOT NULL default '0',
	reason varchar(255) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_buddyrequests (
 id INTEGER PRIMARY KEY,
 uid bigint UNSIGNED NOT NULL,
 touid bigint UNSIGNED NOT NULL,
 date int UNSIGNED NOT NULL
);";

$tables[] = "CREATE TABLE mybb_calendars (
	cid INTEGER PRIMARY KEY,
	name varchar(100) NOT NULL default '',
	disporder smallint NOT NULL default '0',
	startofweek tinyint(1) NOT NULL default '0',
	showbirthdays tinyint(1) NOT NULL default '0',
	eventlimit smallint(3) NOT NULL default '0',
	moderation tinyint(1) NOT NULL default '0',
	allowhtml tinyint(1) NOT NULL default '0',
	allowmycode tinyint(1) NOT NULL default '0',
	allowimgcode tinyint(1) NOT NULL default '0',
	allowvideocode tinyint(1) NOT NULL default '0',
	allowsmilies tinyint(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_calendarpermissions (
	cid int NOT NULL default '0',
	gid int NOT NULL default '0',
	canviewcalendar tinyint(1) NOT NULL default '0',
	canaddevents tinyint(1) NOT NULL default '0',
	canbypasseventmod tinyint(1) NOT NULL default '0',
	canmoderateevents tinyint(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_captcha (
	imagehash varchar(32) NOT NULL default '',
	imagestring varchar(8) NOT NULL default '',
	dateline int unsigned NOT NULL default '0',
	used tinyint(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_datacache (
	title varchar(50) NOT NULL default '' PRIMARY KEY,
	cache mediumTEXT NOT NULL
);";

$tables[] = "CREATE TABLE mybb_delayedmoderation (
	did integer PRIMARY KEY,
	type varchar(30) NOT NULL default '',
	delaydateline int unsigned NOT NULL default '0',
	uid int(10) NOT NULL default '0',
	fid smallint(5) NOT NULL default '0',
	tids text NOT NULL,
	dateline int unsigned NOT NULL default '0',
	inputs text NOT NULL
);";

$tables[] = "CREATE TABLE mybb_events (
	eid INTEGER PRIMARY KEY,
	cid int NOT NULL default '0',
	uid int NOT NULL default '0',
	name varchar(120) NOT NULL default '',
	description TEXT NOT NULL,
	visible tinyint(1) NOT NULL default '0',
	private tinyint(1) NOT NULL default '0',
	dateline int(10) NOT NULL default '0',
	starttime int(10) NOT NULL default '0',
	endtime int(10) NOT NULL default '0',
	timezone varchar(5) NOT NULL default '',
	ignoretimezone tinyint(1) NOT NULL default '0',
	usingtime tinyint(1) NOT NULL default '0',
	repeats TEXT NOT NULL
);";

$tables[] = "CREATE TABLE mybb_forumpermissions (
	pid INTEGER PRIMARY KEY,
	fid int NOT NULL default '0',
	gid int NOT NULL default '0',
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
	modposts tinyint(1) NOT NULL default '0',
	modthreads tinyint(1) NOT NULL default '0',
	mod_edit_posts tinyint(1) NOT NULL default '0',
	modattachments tinyint(1) NOT NULL default '0',
	canpostpolls tinyint(1) NOT NULL default '0',
	canvotepolls tinyint(1) NOT NULL default '0',
	cansearch tinyint(1) NOT NULL default '0'
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
	active tinyint(1) NOT NULL default '0',
	open tinyint(1) NOT NULL default '0',
	threads int NOT NULL default '0',
	posts int NOT NULL default '0',
	lastpost int(10) NOT NULL default '0',
	lastposter varchar(120) NOT NULL default '',
	lastposteruid int(10) NOT NULL default '0',
	lastposttid int(10) NOT NULL default '0',
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
	style smallint NOT NULL default '0',
	overridestyle tinyint(1) NOT NULL default '0',
	rulestype tinyint(1) NOT NULL default '0',
	rulestitle varchar(200) NOT NULL default '',
	rules TEXT NOT NULL,
	unapprovedthreads int(10) NOT NULL default '0',
	unapprovedposts int(10) NOT NULL default '0',
	deletedthreads int(10) NOT NULL default '0',
	deletedposts int(10) NOT NULL default '0',
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
	canmanagemembers tinyint(1) NOT NULL default '0',
	canmanagerequests tinyint(1) NOT NULL default '0',
	caninvitemembers tinyint(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_helpdocs (
	hid INTEGER PRIMARY KEY,
	sid smallint NOT NULL default '0',
	name varchar(120) NOT NULL default '',
	description TEXT NOT NULL,
	document TEXT NOT NULL,
	usetranslation tinyint(1) NOT NULL default '0',
	enabled tinyint(1) NOT NULL default '0',
	disporder smallint NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_helpsections (
	sid INTEGER PRIMARY KEY,
	name varchar(120) NOT NULL default '',
	description TEXT NOT NULL,
	usetranslation tinyint(1) NOT NULL default '0',
	enabled tinyint(1) NOT NULL default '0',
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
	dateline int unsigned NOT NULL default '0',
	invite tinyint(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_massemails (
	mid INTEGER PRIMARY KEY,
	uid int NOT NULL default '0',
	subject varchar(200) NOT NULL default '',
	message text NOT NULL,
	htmlmessage text NOT NULL,
	type tinyint(1) NOT NULL default '0',
	format tinyint(1) NOT NULL default '0',
	dateline int unsigned NOT NULL default '0',
	senddate int unsigned NOT NULL default '0',
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
	dateline int unsigned NOT NULL default '0',
	error TEXT NOT NULL,
	smtperror varchar(200) NOT NULL default '',
	smtpcode smallint(5) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_maillogs (
	mid INTEGER PRIMARY KEY,
	subject varchar(200) not null default '',
	message TEXT NOT NULL,
	dateline int unsigned NOT NULL default '0',
	fromuid int NOT NULL default '0',
	fromemail varchar(200) not null default '',
	touid int unsigned NOT NULL default '0',
	toemail varchar(200) NOT NULL default '',
	tid int NOT NULL default '0',
	ipaddress blob(16) NOT NULL default '',
	type tinyint(1) NOT NULL default '0'
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
	dateline int unsigned NOT NULL default '0',
	fid smallint NOT NULL default '0',
	tid int NOT NULL default '0',
	pid int NOT NULL default '0',
	action TEXT NOT NULL,
	data text NOT NULL,
	ipaddress blob(16) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_moderators (
	mid INTEGER PRIMARY KEY,
	fid smallint NOT NULL default '0',
	id int NOT NULL default '0',
	isgroup tinyint(1) NOT NULL default '0',
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
	canviewmodlog tinyint(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_modtools (
	tid INTEGER PRIMARY KEY,
	name varchar(200) NOT NULL default '',
	description TEXT NOT NULL,
	forums TEXT NOT NULL,
	groups TEXT NOT NULL,
	type char(1) NOT NULL default '',
	postoptions TEXT NOT NULL,
	threadoptions TEXT NOT NULL
);";

$tables[] = "CREATE TABLE mybb_mycode (
	cid INTEGER PRIMARY KEY,
	title varchar(100) NOT NULL default '',
	description TEXT NOT NULL,
	regex TEXT NOT NULL,
	replacement TEXT NOT NULL,
	active tinyint(1) NOT NULL default '0',
	parseorder smallint NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_polls (
	pid INTEGER PRIMARY KEY,
	tid int NOT NULL default '0',
	question varchar(200) NOT NULL default '',
	dateline int unsigned NOT NULL default '0',
	options TEXT NOT NULL,
	votes TEXT NOT NULL,
	numoptions smallint NOT NULL default '0',
	numvotes int NOT NULL default '0',
	timeout int unsigned NOT NULL default '0',
	closed tinyint(1) NOT NULL default '0',
	multiple tinyint(1) NOT NULL default '0',
	public tinyint(1) NOT NULL default '0',
	maxoptions smallint NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_pollvotes (
	vid INTEGER PRIMARY KEY,
	pid int NOT NULL default '0',
	uid int NOT NULL default '0',
	voteoption smallint NOT NULL default '0',
	dateline int unsigned NOT NULL default '0'
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
	dateline int unsigned NOT NULL default '0',
	message TEXT NOT NULL,
	ipaddress blob(16) NOT NULL default '',
	includesig tinyint(1) NOT NULL default '0',
	smilieoff tinyint(1) NOT NULL default '0',
	edituid int NOT NULL default '0',
	edittime int(10) NOT NULL default '0',
	editreason varchar(150) NOT NULL default '',
	visible tinyint(1) NOT NULL default '0'
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
	dateline int unsigned NOT NULL default '0',
	deletetime int unsigned NOT NULL default '0',
	status tinyint(1) NOT NULL default '0',
	statustime int unsigned NOT NULL default '0',
	includesig tinyint(1) NOT NULL default '0',
	smilieoff tinyint(1) NOT NULL default '0',
	receipt tinyint(1) NOT NULL default '0',
	readtime int unsigned NOT NULL default '0',
	ipaddress blob(16) NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_profilefields (
	fid INTEGER PRIMARY KEY,
	name varchar(100) NOT NULL default '',
	description TEXT NOT NULL,
	disporder smallint NOT NULL default '0',
	type TEXT NOT NULL,
	regex TEXT NOT NULL,
	length smallint NOT NULL default '0',
	maxlength smallint NOT NULL default '0',
	required tinyint(1) NOT NULL default '0',
	registration tinyint(1) NOT NULL default '0',
	profile tinyint(1) NOT NULL default '0',
	postbit tinyint(1) NOT NULL default '0',
	viewableby TEXT NOT NULL,
	editableby TEXT NOT NULL,
	postnum smallint NOT NULL default '0',
	allowhtml tinyint(1) NOT NULL default '0',
	allowmycode tinyint(1) NOT NULL default '0',
	allowsmilies tinyint(1) NOT NULL default '0',
	allowimgcode tinyint(1) NOT NULL default '0',
	allowvideocode tinyint(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_promotions (
	pid INTEGER PRIMARY KEY,
	title varchar(120) NOT NULL default '',
	description TEXT NOT NULL,
	enabled tinyint(1) NOT NULL default '1',
	logging tinyint(1) NOT NULL default '0',
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
	usergrouptype varchar(120) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_promotionlogs (
	plid INTEGER PRIMARY KEY,
	pid int NOT NULL default '0',
	uid int NOT NULL default '0',
	oldusergroup varchar(200) NOT NULL default '0',
	newusergroup smallint NOT NULL default '0',
	dateline int unsigned NOT NULL default '0',
	type varchar(9) NOT NULL default 'primary'
);";

$tables[] = "CREATE TABLE mybb_questions (
	qid INTEGER PRIMARY KEY,
	question varchar(200) NOT NULL default '',
	answer varchar(150) NOT NULL default '',
	shown int unsigned NOT NULL default 0,
	correct int unsigned NOT NULL default 0,
	incorrect int unsigned NOT NULL default 0,
	active tinyint(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_questionsessions (
	sid varchar(32) NOT NULL default '',
	qid int unsigned NOT NULL default '0',
	dateline int unsigned NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_reportedcontent (
	rid INTEGER PRIMARY KEY,
	id int NOT NULL default '0',
	id2 int NOT NULL default '0',
	id3 int NOT NULL default '0',
	uid int NOT NULL default '0',
	reportstatus tinyint(1) NOT NULL default '0',
	reason varchar(250) NOT NULL default '',
	type varchar(50) NOT NULL default '',
	reports int NOT NULL default '0',
	reporters text NOT NULL,
	dateline int unsigned NOT NULL default '0',
	lastreport int unsigned NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_reputation (
	rid INTEGER PRIMARY KEY,
	uid int NOT NULL default '0',
	adduid int NOT NULL default '0',
	pid int NOT NULL default '0',
	reputation smallint NOT NULL default '0',
	dateline int unsigned NOT NULL default '0',
	comments TEXT NOT NULL
);";

$tables[] = "CREATE TABLE mybb_searchlog (
	sid varchar(32) NOT NULL default '',
	uid int NOT NULL default '0',
	dateline int unsigned NOT NULL default '0',
	ipaddress blob(16) NOT NULL default '',
	threads LONGTEXT NOT NULL,
	posts LONGTEXT NOT NULL,
	resulttype varchar(10) NOT NULL default '',
	querycache TEXT NOT NULL,
	keywords TEXT NOT NULL
);";

$tables[] = "CREATE TABLE mybb_sessions (
	sid varchar(32) NOT NULL default '',
	uid int NOT NULL default '0',
	ip blob(16) NOT NULL default '',
	time int unsigned NOT NULL default '0',
	location varchar(150) NOT NULL default '',
	useragent varchar(100) NOT NULL default '',
	anonymous tinyint(1) NOT NULL default '0',
	nopermission tinyint(1) NOT NULL default '0',
	location1 int(10) NOT NULL default '0',
	location2 int(10) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_settinggroups (
	gid INTEGER PRIMARY KEY,
	name varchar(100) NOT NULL default '',
	title varchar(220) NOT NULL default '',
	description TEXT NOT NULL,
	disporder smallint NOT NULL default '0',
	isdefault tinyint(1) NOT NULL default '0'
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
	isdefault tinyint(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_smilies (
	sid INTEGER PRIMARY KEY,
	name varchar(120) NOT NULL default '',
	find TEXT NOT NULL,
	image varchar(220) NOT NULL default '',
	disporder smallint NOT NULL default '0',
	showclickable tinyint(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_spamlog (
	sid INTEGER PRIMARY KEY,
	username varchar(120) NOT NULL DEFAULT '',
	email varchar(220) NOT NULL DEFAULT '',
	ipaddress blob(16) NOT NULL default '',
	dateline int unsigned NOT NULL default '0',
	data TEXT NOT NULL default ''
);";

$tables[] = "CREATE TABLE mybb_spiders (
	sid INTEGER PRIMARY KEY,
	name varchar(100) NOT NULL default '',
	theme smallint NOT NULL default '0',
	language varchar(20) NOT NULL default '',
	usergroup smallint NOT NULL default '0',
	useragent varchar(200) NOT NULL default '',
	lastvisit int unsigned NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_stats (
	dateline int unsigned NOT NULL default '0' PRIMARY KEY,
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
	nextrun int unsigned NOT NULL default '0',
	lastrun int unsigned NOT NULL default '0',
	enabled tinyint(1) NOT NULL default '1',
	logging tinyint(1) NOT NULL default '0',
	locked int unsigned NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_tasklog (
	lid INTEGER PRIMARY KEY,
	tid int NOT NULL default '0',
	dateline int unsigned NOT NULL default '0',
	data TEXT NOT NULL
);";

$tables[] = "CREATE TABLE mybb_templategroups (
	gid INTEGER PRIMARY KEY,
	prefix varchar(50) NOT NULL default '',
	title varchar(100) NOT NULL default '',
	isdefault tinyint(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_templates (
	tid INTEGER PRIMARY KEY,
	title varchar(120) NOT NULL default '',
	template TEXT NOT NULL,
	sid smallint NOT NULL default '0',
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
	def tinyint(1) NOT NULL default '0',
	properties TEXT NOT NULL,
	stylesheets TEXT NOT NULL,
	allowedgroups TEXT NOT NULL
);";

$tables[] = "CREATE TABLE mybb_themestylesheets(
	sid INTEGER PRIMARY KEY,
	name varchar(30) NOT NULL default '',
	tid smallint unsigned NOT NULL default '0',
	attachedto TEXT NOT NULL,
	stylesheet LONGTEXT NOT NULL,
	cachefile varchar(100) NOT NULL default '',
	lastmodified int unsigned NOT NULL default '0'
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
	ipaddress blob(16) NOT NULL default ''
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
	dateline int unsigned NOT NULL default '0',
	firstpost int NOT NULL default '0',
	lastpost int unsigned NOT NULL default '0',
	lastposter varchar(120) NOT NULL default '',
	lastposteruid int NOT NULL default '0',
	views int(100) NOT NULL default '0',
	replies int(100) NOT NULL default '0',
	closed varchar(30) NOT NULL default '',
	sticky tinyint(1) NOT NULL default '0',
	numratings smallint NOT NULL default '0',
	totalratings smallint NOT NULL default '0',
	notes TEXT NOT NULL,
	visible tinyint(1) NOT NULL default '0',
	unapprovedposts int(10) NOT NULL default '0',
	deletedposts int(10) NOT NULL default '0',
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
	notification tinyint(1) NOT NULL default '0',
	dateline int unsigned NOT NULL default '0',
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
	canpostpolls tinyint(1) NOT NULL default '0',
	canvotepolls tinyint(1) NOT NULL default '0',
	canundovotes tinyint(1) NOT NULL default '0',
	canusepms tinyint(1) NOT NULL default '0',
	cansendpms tinyint(1) NOT NULL default '0',
	cantrackpms tinyint(1) NOT NULL default '0',
	candenypmreceipts tinyint(1) NOT NULL default '0',
	pmquota int(3) NOT NULL default '0',
	maxpmrecipients int(4) NOT NULL default '5',
	cansendemail tinyint(1) NOT NULL default '0',
	cansendemailoverride tinyint(1) NOT NULL default '0',
	maxemails int(3) NOT NULL default '5',
	emailfloodtime int(3) NOT NULL default '5',
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
	maxwarningsday int NOT NULL default '3',
	canmodcp tinyint(1) NOT NULL default '0',
	showinbirthdaylist tinyint(1) NOT NULL default '0',
	canoverridepm tinyint(1) NOT NULL default '0',
	canusesig tinyint(1) NOT NULL default '0',
	canusesigxposts int unsigned NOT NULL default '0',
	signofollow tinyint(1) NOT NULL default '0',
	edittimelimit int(4) NOT NULL default '0',
	maxposts int(4) NOT NULL default '0',
	showmemberlist tinyint(1) NOT NULL default '1',
	canmanageannounce tinyint(1) NOT NULL default '0',
	canmanagemodqueue tinyint(1) NOT NULL default '0',
	canmanagereportedcontent tinyint(1) NOT NULL default '0',
	canviewmodlogs tinyint(1) NOT NULL default '0',
	caneditprofiles tinyint(1) NOT NULL default '0',
	canbanusers tinyint(1) NOT NULL default '0',
	canviewwarnlogs tinyint(1) NOT NULL default '0',
	canuseipsearch tinyint(1) NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_users (
	uid INTEGER PRIMARY KEY,
	username varchar(120) NOT NULL default '',
	password varchar(120) NOT NULL default '',
	salt varchar(10) NOT NULL default '',
	loginkey varchar(50) NOT NULL default '',
	email varchar(220) NOT NULL default '',
	postnum int(10) NOT NULL default '0',
	threadnum int(10) NOT NULL default '0',
	avatar varchar(200) NOT NULL default '',
	avatardimensions varchar(10) NOT NULL default '',
	avatartype varchar(10) NOT NULL default '0',
	usergroup smallint NOT NULL default '0',
	additionalgroups varchar(200) NOT NULL default '',
	displaygroup smallint NOT NULL default '0',
	usertitle varchar(250) NOT NULL default '',
	regdate int unsigned NOT NULL default '0',
	lastactive int unsigned NOT NULL default '0',
	lastvisit int unsigned NOT NULL default '0',
	lastpost int unsigned NOT NULL default '0',
	website varchar(200) NOT NULL default '',
	icq varchar(10) NOT NULL default '',
	aim varchar(50) NOT NULL default '',
	yahoo varchar(50) NOT NULL default '',
	skype varchar(75) NOT NULL default '',
	google varchar(75) NOT NULL default '',
	birthday varchar(15) NOT NULL default '',
	birthdayprivacy varchar(4) NOT NULL default 'all',
	signature TEXT NOT NULL,
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
	ppp smallint(6) NOT NULL default '0',
	tpp smallint(6) NOT NULL default '0',
	daysprune smallint(6) NOT NULL default '0',
	dateformat varchar(4) NOT NULL default '',
	timeformat varchar(4) NOT NULL default '',
	timezone varchar(5) NOT NULL default '',
	dst tinyint(1) NOT NULL default '0',
	dstcorrection tinyint(1) NOT NULL default '0',
	buddylist TEXT NOT NULL,
	ignorelist TEXT NOT NULL,
	style smallint NOT NULL default '0',
	away tinyint(1) NOT NULL default '0',
	awaydate int(10) NOT NULL default '0',
	returndate varchar(15) NOT NULL default '',
	awayreason varchar(200) NOT NULL default '',
	pmfolders TEXT NOT NULL,
	notepad TEXT NOT NULL,
	referrer int NOT NULL default '0',
	referrals int NOT NULL default '0',
	reputation int NOT NULL default '0',
	regip blob(16) NOT NULL default '',
	lastip blob(16) NOT NULL default '',
	language varchar(50) NOT NULL default '',
	timeonline int unsigned NOT NULL default '0',
	showcodebuttons tinyint(1) NOT NULL default '1',
	totalpms int(10) NOT NULL default '0',
	unreadpms int(10) NOT NULL default '0',
	warningpoints int(3) NOT NULL default '0',
	moderateposts tinyint(1) NOT NULL default '0',
	moderationtime int unsigned NOT NULL default '0',
	suspendposting tinyint(1) NOT NULL default '0',
	suspensiontime int unsigned NOT NULL default '0',
	suspendsignature tinyint(1) NOT NULL default '0',
	suspendsigtime int unsigned NOT NULL default '0',
	coppauser tinyint(1) NOT NULL default '0',
	classicpostbit tinyint(1) NOT NULL default '0',
	loginattempts tinyint(2) NOT NULL default '1',
	usernotes TEXT NOT NULL,
	sourceeditor tinyint(1) NOT NULL default '0'
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
	percentage smallint(3) NOT NULL default '0',
	action TEXT NOT NULL
);";

$tables[] = "CREATE TABLE mybb_warningtypes (
	tid INTEGER PRIMARY KEY,
	title varchar(120) NOT NULL default '',
	points smallint NOT NULL default '0',
	expirationtime int unsigned NOT NULL default '0'
);";

$tables[] = "CREATE TABLE mybb_warnings (
	wid INTEGER PRIMARY KEY,
	uid int NOT NULL default '0',
	tid int NOT NULL default '0',
	pid int NOT NULL default '0',
	title varchar(120) NOT NULL default '',
	points smallint NOT NULL default '0',
	dateline int unsigned NOT NULL default '0',
	issuedby int NOT NULL default '0',
	expires int unsigned NOT NULL default '0',
	expired tinyint(1) NOT NULL default '0',
	daterevoked int unsigned NOT NULL default '0',
	revokedby int NOT NULL default '0',
	revokereason TEXT NOT NULL,
	notes TEXT NOT NULL
);";


