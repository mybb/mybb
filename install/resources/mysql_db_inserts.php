<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: mysql_db_inserts.php 5616 2011-09-20 13:24:59Z Tomm $
 */

$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (1, 'ZIP File', 'application/zip', 'zip', 1024, 'images/attachtypes/zip.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (2, 'JPG Image', 'image/jpeg', 'jpg', 500, 'images/attachtypes/image.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (3, 'Text Document', 'text/plain', 'txt', 200, 'images/attachtypes/txt.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (4, 'GIF Image', 'image/gif', 'gif', 500, 'images/attachtypes/image.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (6, 'PHP File', 'application/x-httpd-php', 'php', 500, 'images/attachtypes/php.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (7, 'PNG Image', 'image/png', 'png', 500, 'images/attachtypes/image.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (8, 'Microsoft Word Document', 'application/msword', 'doc', 1024, 'images/attachtypes/doc.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (9, 'HTM File', 'text/html', 'htm', 100, 'images/attachtypes/html.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (10, 'HTML File', 'text/html', 'html', 100, 'images/attachtypes/html.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (11, 'JPEG Image', 'image/jpeg', 'jpeg', 500, 'images/attachtypes/image.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (12, 'GZIP Compressed File', 'application/x-gzip', 'gz', 1024, 'images/attachtypes/tar.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (13, 'TAR Compressed File', 'application/x-tar', 'tar', 1024, 'images/attachtypes/tar.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (14, 'CSS Stylesheet', 'text/css', 'css', 100, 'images/attachtypes/css.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (15, 'Adobe Acrobat PDF', 'application/pdf', 'pdf', 2048, 'images/attachtypes/pdf.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (16, 'Bitmap Image', 'image/bmp', 'bmp', 500, 'images/attachtypes/image.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (17, 'Microsoft Word 2007 Document', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'docx', 1024, 'images/attachtypes/doc.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (18, 'Microsoft Excel Document', 'application/vnd.ms-excel', 'xls', 1024, 'images/attachtypes/xls.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (19, 'Microsoft Excel 2007 Document', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xlsx', 1024, 'images/attachtypes/xls.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (20, 'Microsoft PowerPoint Document', 'application/vnd.ms-powerpoint', 'ppt', 1024, 'images/attachtypes/ppt.gif');";
$inserts[] = "INSERT INTO mybb_attachtypes (atid, name, mimetype, extension, maxsize, icon) VALUES (21, 'Microsoft PowerPoint 2007 Document', 'application/vnd.openxmlformats-officedocument.presentationml.presentation', 'pptx', 1024, 'images/attachtypes/ppt.gif');";

$inserts[] = "INSERT INTO mybb_calendars (name,disporder,startofweek,showbirthdays,eventlimit,moderation,allowhtml,allowmycode,allowimgcode,allowvideocode,allowsmilies) VALUES ('Default Calendar',1,0,1,4,0,0,1,1,1,1);";

$inserts[] = "INSERT INTO mybb_forums (fid, name, description, linkto, type, pid, parentlist, disporder, active, open, threads, posts, lastpost, lastposter, lastposttid, allowhtml, allowmycode, allowsmilies, allowimgcode, allowvideocode, allowpicons, allowtratings, status, usepostcounts, password, showinjump, modposts, modthreads, modattachments, style, overridestyle, rulestype, rulestitle, rules) VALUES (1, 'My Category', '', '', 'c', 0, '1', 1, 1, 1, 0, 0, 0, '0', 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, '', 1, 0, 0, 0, 0, 0, 0, '', '');";
$inserts[] = "INSERT INTO mybb_forums (fid, name, description, linkto, type, pid, parentlist, disporder, active, open, threads, posts, lastpost, lastposter, lastposttid, allowhtml, allowmycode, allowsmilies, allowimgcode, allowvideocode, allowpicons, allowtratings, status, usepostcounts, password, showinjump, modposts, modthreads, modattachments, style, overridestyle, rulestype, rulestitle, rules) VALUES (2, 'My Forum', '', '', 'f', 1, '1,2', 1, 1, 1, 0, 0, 0, '0', 0, 0, 1, 1, 1, 1, 1, 1, 1, 1, '', 1, 0, 0, 0, 0, 0, 0, '', '');";

$inserts[] = "INSERT INTO mybb_helpdocs (hid, sid, name, description, document, usetranslation, enabled, disporder) VALUES (1, 1, 'User Registration', 'Perks and privileges to user registration.', 'Some parts of this forum may require you to be logged in and registered. Registration is free and takes a few minutes to complete.<br />\r\n<br />\r\nYou are encouraged to register; once you register you will be able to post messages, set your own preferences, and maintain a profile.<br />\r\n<br />\r\nSome of the features that generally require registration are subscriptions, changing of styles, accessing of your Personal Pad (simple notepad) and emailing forum members.', 1, 1, 1);";
$inserts[] = "INSERT INTO mybb_helpdocs (hid, sid, name, description, document, usetranslation, enabled, disporder) VALUES (2, 1, 'Updating Profile', 'Changing your data currently on record.', 'At some point during your stay, you may decide you need to update some information such as your instant messenger information, your password, or perhaps you need to change your email address. You may change any of this information from your user control panel. To access this control panel, simply click on the link in the upper right hand corner of most any page entitled \"user cp\". From there, simply choose \"Edit Profile\" and change or update any desired items, then proceed to click the submit button located at the bottom of the page for changes to take effect.', 1, 1, 2);";
$inserts[] = "INSERT INTO mybb_helpdocs (hid, sid, name, description, document, usetranslation, enabled, disporder) VALUES (3, 1, 'Use of Cookies on myBB', 'myBB uses cookies to store certain information about your registration.', 'myBulletinBoard makes use of cookies to store your login information if you are registered, and your last visit if you are not.<br />\r\n<br />\r\nCookies are small text documents stored on your computer; the cookies set by this forum can only be used on this website and pose no security risk.<br />\r\n<br />\r\nCookies on this forum also track the specific topics you have read and when you last read them.<br />\r\n<br />\r\nTo clear all cookies set by this forum, you can click <a href=\"misc.php?action=clearcookies&amp;key={1}\">here</a>.', 1, 1, 3);";
$inserts[] = "INSERT INTO mybb_helpdocs (hid, sid, name, description, document, usetranslation, enabled, disporder) VALUES (4, 1, 'Logging In and Out', 'How to login and log out.', 'When you login, you set a cookie on your machine so that you can browse the forums without having to enter in your username and password each time. Logging out clears that cookie to ensure nobody else can browse the forum as you.<br />\r\n<br />\r\nTo login, simply click the login link at the top right hand corner of the forum. To log out, click the log out link in the same place. In the event you cannot log out, clearing cookies on your machine will take the same effect.', 1, 1, 4);";
$inserts[] = "INSERT INTO mybb_helpdocs (hid, sid, name, description, document, usetranslation, enabled, disporder) VALUES (5, 2, 'Posting a New Topic', 'Starting a new thread in a forum.', 'When you go to a forum you are interested in and you wish to create a new topic (or thread), simply choose the button at the top and bottom of the forums entitled \"New topic\". Please take note that you may not have permission to post a new topic in every forum as your administrator may have restricted posting in that forum to staff or archived the forum entirely.', 1, 1, 1);";
$inserts[] = "INSERT INTO mybb_helpdocs (hid, sid, name, description, document, usetranslation, enabled, disporder) VALUES (6, 2, 'Posting a Reply', 'Replying to a topic within a forum.', 'During the course of your visit, you may encounter a thread to which you would like to make a reply. To do so, simply click the \"Post reply\" button at the bottom or top of the thread. Please take note that your administrator may have restricted posting to certain individuals in that particular forum.<br />\r\n<br />\r\nAdditionally, a moderator of a forum may have closed a thread meaning that users cannot reply to it. There is no way for a user to open such a thread without the help of a moderator or administrator.', 1, 1, 2);";
$inserts[] = "INSERT INTO mybb_helpdocs (hid, sid, name, description, document, usetranslation, enabled, disporder) VALUES (7, 2, 'MyCode', 'Learn how to use MyCode to enhance your posts.', 'You can use MyCode, a simplified version of HTML, in your posts to create certain effects.\r\n<p><br />\r\n[b]This text is bold[/b]<br />\r\n&nbsp;&nbsp;&nbsp;<b>This text is bold</b>\r\n<p>\r\n[i]This text is italicized[/i]<br />\r\n&nbsp;&nbsp;&nbsp;<i>This text is italicized</i>\r\n<p>\r\n[u]This text is underlined[/u]<br />\r\n&nbsp;&nbsp;&nbsp;<u>This text is underlined</u>\r\n<p><br />\r\n[url]http://www.example.com/[/url]<br />\r\n&nbsp;&nbsp;&nbsp;<a href=\"http://www.example.com/\">http://www.example.com/</a>\r\n<p>\r\n[url=http://www.example.com/]Example.com[/url]<br />\r\n&nbsp;&nbsp;&nbsp;<a href=\"http://www.example.com/\">Example.com</a>\r\n<p>\r\n[email]example@example.com[/email]<br />\r\n&nbsp;&nbsp;&nbsp;<a href=\"mailto:example@example.com\">example@example.com</a>\r\n<p>\r\n[email=example@example.com]E-mail Me![/email]<br />\r\n&nbsp;&nbsp;&nbsp;<a href=\"mailto:example@example.com\">E-mail Me!</a>\r\n<p>\r\n[email=example@example.com?subject=spam]E-mail with subject[/email]<br />\r\n&nbsp;&nbsp;&nbsp;<a href=\"mailto:example@example.com?subject=spam\">E-mail with subject</a>\r\n<p><br />\r\n[quote]Quoted text will be here[/quote]<br />\r\n&nbsp;&nbsp;&nbsp;<quote>Quoted text will be here</quote>\r\n<p>\r\n[code]Text with preserved formatting[/code]<br />\r\n&nbsp;&nbsp;&nbsp;<code>Text with preserved formatting</code>\r\n<p><br />\r\n[img]http://www.php.net/images/php.gif[/img]<br />\r\n&nbsp;&nbsp;&nbsp;<img src=\"http://www.php.net/images/php.gif\">\r\n<p>\r\n[img=50x50]http://www.php.net/images/php.gif[/img]<br />\r\n&nbsp;&nbsp;&nbsp;<img src=\"http://www.php.net/images/php.gif\" width=\"50\" height=\"50\">\r\n<p><br />\r\n[color=red]This text is red[/color]<br />\r\n&nbsp;&nbsp;&nbsp;<font color=\"red\">This text is red</font>\r\n<p>\r\n[size=3]This text is size 3[/size]<br />\r\n&nbsp;&nbsp;&nbsp;<font size=\"3\">This text is size 3</font>\r\n<p>\r\n[font=Tahoma]This font is Tahoma[/font]<br />\r\n&nbsp;&nbsp;&nbsp;<font face=\"Tahoma\">This font is Tahoma</font>\r\n<p><br />\r\n[align=center]This is centered[/align]<div align=\"center\">This is centered</div>\r\n<p>\r\n[align=right]This is right-aligned[/align]<div align=\"right\">This is right-aligned</div>\r\n<p><br />\r\n[list]<br />\r\n[*]List Item #1<br />\r\n[*]List Item #2<br />\r\n[*]List Item #3<br />\r\n[/list]<br />\r\n<ul>\r\n<li>List item #1</li>\r\n<li>List item #2</li>\r\n<li>List Item #3</li>\r\n</ul><p><font size=1>You can make an ordered list by using [list=1] for a numbered, and [list=a] for an alphabetical list.</size>', 1, 1, 3);";

$inserts[] = "INSERT INTO mybb_helpsections (sid, name, description, usetranslation, enabled, disporder) VALUES (1, 'User Maintenance', 'Basic instructions for maintaining a forum account.', 1, 1, 1);";
$inserts[] = "INSERT INTO mybb_helpsections (sid, name, description, usetranslation, enabled, disporder) VALUES (2, 'Posting', 'Posting, replying, and basic usage of forum.', 1, 1, 2);";

$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(1, 'Bug', 'images/icons/bug.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(2, 'Exclamation', 'images/icons/exclamation.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(3, 'Question', 'images/icons/question.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(4, 'Smile', 'images/icons/smile.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(5, 'Sad', 'images/icons/sad.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(6, 'Wink', 'images/icons/wink.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(7, 'Big Grin', 'images/icons/biggrin.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(8, 'Tongue', 'images/icons/tongue.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(9, 'Brick', 'images/icons/brick.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(10, 'Heart', 'images/icons/heart.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(11, 'Information', 'images/icons/information.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(12, 'Lightbulb', 'images/icons/lightbulb.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(13, 'Music', 'images/icons/music.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(14, 'Photo', 'images/icons/photo.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(15, 'Rainbow', 'images/icons/rainbow.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(16, 'Shocked', 'images/icons/shocked.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(17, 'Star', 'images/icons/star.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(18, 'Thumbs Down', 'images/icons/thumbsdown.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(19, 'Thumbs Up', 'images/icons/thumbsup.gif');";
$inserts[] = "INSERT INTO mybb_icons (iid, name, path) VALUES(20, 'Video', 'images/icons/video.gif');";

$inserts[] = "INSERT INTO mybb_profilefields (fid, name, description, disporder, type, length, maxlength, required, editable, hidden, postnum) VALUES (1, 'Location', 'Where in the world do you live?', 1, 'text', 0, 255, 0, 1, 0, 0);";
$inserts[] = "INSERT INTO mybb_profilefields (fid, name, description, disporder, type, length, maxlength, required, editable, hidden, postnum) VALUES (2, 'Bio', 'Enter a few short details about yourself, your life story etc.', 2, 'textarea', 0, 0, 0, 1, 0, 0);";
$inserts[] = "INSERT INTO mybb_profilefields (fid, name, description, disporder, type, length, maxlength, required, editable, hidden, postnum) VALUES (3, 'Sex', 'Please select your sex from the list below.', 0, 'select\nUndisclosed\nMale\nFemale\nOther', 0, 0, 0, 1, 0, 0);";

$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(1, 'Smile', ':)', 'images/smilies/smile.gif', 1, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(2, 'Wink', ';)', 'images/smilies/wink.gif', 2, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(3, 'Cool', ':cool:', 'images/smilies/cool.gif', 3, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(4, 'Big Grin', ':D', 'images/smilies/biggrin.gif', 4, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(5, 'Tongue', ':P', 'images/smilies/tongue.gif', 5, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(6, 'Rolleyes', ':rolleyes:', 'images/smilies/rolleyes.gif', 6, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(7, 'Shy', ':shy:', 'images/smilies/shy.gif', 7, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(8, 'Sad', ':(', 'images/smilies/sad.gif', 8, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(9, 'At', ':at:', 'images/smilies/at.gif', 9, 0);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(10, 'Angel', ':angel:', 'images/smilies/angel.gif', 0, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(11, 'Angry', ':@', 'images/smilies/angry.gif', 0, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(12, 'Blush', ':blush:', 'images/smilies/blush.gif', 0, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(13, 'Confused', ':s', 'images/smilies/confused.gif', 0, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(14, 'Dodgy', ':dodgy:', 'images/smilies/dodgy.gif', 0, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(15, 'Exclamation', ':exclamation:', 'images/smilies/exclamation.gif', 0, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(16, 'Heart', ':heart:', 'images/smilies/heart.gif', 0, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(17, 'Huh', ':huh:', 'images/smilies/huh.gif', 0, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(18, 'Idea', ':idea:', 'images/smilies/lightbulb.gif', 0, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(19, 'Sleepy', ':sleepy:', 'images/smilies/sleepy.gif', 0, 1);";
$inserts[] = "INSERT INTO mybb_smilies (sid, name, find, image, disporder, showclickable) VALUES(20, 'Undecided', ':-/', 'images/smilies/undecided.gif', 0, 1);";


$inserts[] = "INSERT INTO mybb_spiders (name,useragent) VALUES ('Google','Googlebot');";
$inserts[] = "INSERT INTO mybb_spiders (name,useragent) VALUES ('Lycos','lycos');";
$inserts[] = "INSERT INTO mybb_spiders (name,useragent) VALUES ('Ask.com','Teoma');";
$inserts[] = "INSERT INTO mybb_spiders (name,useragent) VALUES ('What You Seek','whatuseek');";
$inserts[] = "INSERT INTO mybb_spiders (name,useragent) VALUES ('Internet Archive','archive_crawler');";
$inserts[] = "INSERT INTO mybb_spiders (name,useragent) VALUES ('Alexa Internet','ia_archiver');";
$inserts[] = "INSERT INTO mybb_spiders (name,useragent) VALUES ('Bing','msnbot');";
$inserts[] = "INSERT INTO mybb_spiders (name,useragent) VALUES ('Yahoo!','Slurp');";
$inserts[] = "INSERT INTO mybb_spiders (name,useragent) VALUES ('Baidu','Baiduspider');";

$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('1','calendar','<lang:group_calendar>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('2','editpost','<lang:group_editpost>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('3','forumbit','<lang:group_forumbit>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('4','forumjump','<lang:group_forumjump>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('5','forumdisplay','<lang:group_forumdisplay>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('6','index','<lang:group_index>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('7','error','<lang:group_error>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('8','memberlist','<lang:group_memberlist>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('9','multipage','<lang:group_multipage>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('10','private','<lang:group_private>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('11','portal','<lang:group_portal>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('12','postbit','<lang:group_postbit>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('13','redirect','<lang:group_redirect>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('14','showthread','<lang:group_showthread>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('15','usercp','<lang:group_usercp>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('16','online','<lang:group_online>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('17','moderation','<lang:group_moderation>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('18','nav','<lang:group_nav>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('19','search','<lang:group_search>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('20','showteam','<lang:group_showteam>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('21','reputation','<lang:group_reputation>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('22','newthread','<lang:group_newthread>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('23','newreply','<lang:group_newreply>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('24','member','<lang:group_member>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('25','warnings','<lang:group_warning>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('26','global','<lang:group_global>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('27','header','<lang:group_header>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('28','managegroup','<lang:group_managegroup>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('29','misc','<lang:group_misc>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('30','modcp','<lang:group_modcp>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('31','php','<lang:group_php>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('32','polls','<lang:group_polls>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('33','post','<lang:group_post>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('34','printthread','<lang:group_printthread>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('35','report','<lang:group_report>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('36','smilieinsert','<lang:group_smilieinsert>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('37','stats','<lang:group_stats>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('38','xmlhttp','<lang:group_xmlhttp>');";
$inserts[] = "INSERT INTO mybb_templategroups (gid,prefix,title) VALUES ('39','footer','<lang:group_footer>');";

$inserts[] = "INSERT INTO mybb_usertitles (utid, posts, title, stars, starimage) VALUES (1, 0, 'Newbie', 1, '');";
$inserts[] = "INSERT INTO mybb_usertitles (utid, posts, title, stars, starimage) VALUES (2, 1, 'Junior Member', 2, '');";
$inserts[] = "INSERT INTO mybb_usertitles (utid, posts, title, stars, starimage) VALUES (3, 50, 'Member', 3, '');";
$inserts[] = "INSERT INTO mybb_usertitles (utid, posts, title, stars, starimage) VALUES (4, 250, 'Senior Member', 4, '');";
$inserts[] = "INSERT INTO mybb_usertitles (utid, posts, title, stars, starimage) VALUES (5, 750, 'Posting Freak', 5, '');";

?>