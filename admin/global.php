<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

// Lets pretend we're a level higher
chdir('../');
if(!$config['admindir'])
{
	$config['admindir'] = "admin";
}

require "./inc/init.php";
require $config['admindir']."/adminfunctions.php";

$style = "./styles/$settings[cpstyle]/stylesheet.css";
if(!file_exists($config['admindir']."/".$style))
{
	$style = "./styles/Axiom/stylesheet.css";
}

$lang->setLanguage($settings[cplanguage], "admin");

// Load global language phrases
$lang->load("global");

$time = time();
if(is_dir("./install") && !file_exists("./install/lock")) {
	echo $lang->setup_warning;
	exit;
}
if($action == "logout")
{
	$expires = $time-1;
	setcookie("mybbadmin[", "", $expires);
	unset($mybbadmin);
}

$showlogin = 1;
$ipaddress = getip();

unset($user);
if($do == "login")
{
	$md5pw = md5($password);
	$username = addslashes($_POST['username']);
	$query = $db->query("SELECT username, uid, password, usergroup FROM ".TABLE_PREFIX."users WHERE username='$username' AND password='$md5pw'");
	$user = $db->fetch_array($query);
	$failcheck = 1;
}
else
{
	$logon = explode("_", $_COOKIE['mybbadmin'], 2);
	$query = $db->query("SELECT username, uid, password, usergroup FROM ".TABLE_PREFIX."users WHERE uid='$logon[0]'");
	$user = $db->fetch_array($query);
	if(md5($user['password'].md5($user['salt'])) != $logon[1])
	{
		unset($user);
	}
}
$mybbgroups = $user['usergroup'].",".$user['additionalgroups'];

if(!$user['usergroup'])
{
	$mybbgroups = 1;
}
$groupscache = $cache->read("usergroups");
$admingroup = usergroup_permissions($mybbgroups);
if($admingroup['cancp'] != "yes" || !$user['uid'])
{
	unset($user);
}

if($user['uid'])
{
	$expires = $time+60*60*24;
	setcookie("mybbadmin", $user['uid']."_".md5($user['password'].md5($user['salt'])), $expires);
	$mybbadmin = $user;
	$query = $db->query("SELECT * FROM ".TABLE_PREFIX."adminoptions WHERE uid='$user[uid]'");
	$adminoptions = $db->fetch_array($query);
	if($adminoptions[cpstyle] && file_exists($config['admindir']."/styles/$adminoptions[cpstyle]/stylesheet.css")) {
		$style = "./styles/$adminoptions[cpstyle]/stylesheet.css";
	}
} else {
	if($failcheck) {
		$ipaddress = getip();
		$iphost = @gethostbyaddr($ipaddress);
		
		$message="A user has tried to access the Administration Control Panel for $settings[bbname]. They were unable to succeed in doing so.\nBelow are the login details:\n\nUsername: $username\nPassword: $password (MD5: $md5pw)\n\nIP Address: $ipaddress\nHostname: $iphost\n\nThank you.";
		mail($settings[adminemail], "Warning: $settings[bbname] Login Attempt", $message, "From: \"$settings[bbname] Admin CP\" <$settings[adminemail]>");
	}
	cpheader("", 0, "javascript:document.loginform.username.focus();");
	echo "<br />\n<br />\n<br />";
	echo "<form action=\"$PHP_SELF\" method=\"post\" name=\"loginform\">\n";
	echo "<table cellspacing=\"0\" cellpadding=\"0\" border=\"0\" width=\"450\" align=\"center\">\n";
	echo "<tr><td class=\"bordercolor\">\n";
	echo "<table border=\"0\" cellspacing=\"1\" cellpadding=\"4\" width=\"100%\">\n";
	echo "<tr>\n";
	echo "<td class=\"header\" align=\"center\">".$lang->administration_login."</td>\n";
	echo "</tr>";
	echo "<tr>\n";
	echo "<td id=\"logo\"><h1><span class=\"hidden\">MyBB>td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td class=\"altbg1\" align=\"center\">".$lang->invalid_admin."</td>\n";
	echo "</tr>";
	echo "<tr>\n";
	echo "<td class=\"altbg2\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n";
	echo "<td><b>".$lang->login_username."</b></td>\n";
	echo "<td><input type=\"text\" name=\"username\"></td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td><b>".$lang->login_password."</b></td>\n";
	echo "<td><input type=\"password\" name=\"password\" /></td>\n";
	echo "</tr>\n";
	echo "</table>\n";
	echo "</td>";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td class=\"altbg2\" align=\"center\"><input type=\"submit\" value=\"".$lang->login."\">&nbsp;&nbsp;&nbsp;<input type=\"reset\" value=\"".$lang->reset."\"></td>\n";
	echo "</td>\n";
	echo "</table>\n";
	echo "</td></tr></table>\n";
	echo "</td></tr></table>\n";
	echo "<input type=\"hidden\" name=\"do\" value=\"login\">\n";
	echo "</form>\n";
	cpfooter();
	exit;
}
$navbits[0]['name'] = $settings['bbname']." ".$lang->control_panel;
$navbits[0]['url'] = "index.php?action=home";
//addacpnav($lang->mybb_admin, "index.php");
?>
