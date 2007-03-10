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

define("IN_MYBB", 1);

require_once "./global.php";

// Load language packs for this section
global $lang;
$lang->load("announcements");

checkadminpermissions("caneditann");
logadmin();

$fid = intval($mybb->input['fid']);
$aid = intval($mybb->input['aid']);

addacpnav($lang->nav_announcements);
switch($mybb->input['action'])
{
	case "add":
		addacpnav($lang->nav_add_announcement);
		break;
	case "delete":
		addacpnav($lang->nav_delete_announcement);
		break;
}

function getforums($pid="0")
{
	global $db, $forumlist, $lang;
	
	$query = $db->simple_select("forums", "*", "pid = '$pid'", array('order_by' => 'disporder'));
	
	while($forum = $db->fetch_array($query))
	{
		$forumlist .= "\n<li><b>$forum[name]</b>".makelinkcode($lang->add_announcement, "announcements.php?".SID."&amp;action=add&amp;fid=$forum[fid]");
		$annquery = $db->simple_select("announcements", "*", "fid = '{$forum['fid']}'");
		$numannouncements = $db->num_rows($annquery);
		if($numannouncements != "0")
		{
			$forumlist .= "<ul>";
			while($announcement = $db->fetch_array($annquery))
			{
				$announcement['subject'] = stripslashes($announcement['subject']);
				$forumlist .= "<li>$announcement[subject]".
				makelinkcode($lang->edit_announcement, "announcements.php?".SID."&amp;action=edit&amp;aid=$announcement[aid]").
				makelinkcode($lang->delete_announcement, "announcements.php?".SID."&amp;action=delete&amp;aid=$announcement[aid]")."</li>\n";
			}
			$forumlist .= "</ul>\n";
		}
		$forumlist .= "<ul>\n";
		getforums($forum['fid']);
		$forumlist .= "</ul>\n";
		$forumlist .= "</li>\n";
	}
	return $forumlist;
}

if($mybb->input['action'] == "do_add")
{
	$message = $db->escape_string($mybb->input['message']);
	$subject = $db->escape_string($mybb->input['subject']);
	$startdateampm = $mybb->input['startdateampm'];
	$startdatehour = intval($mybb->input['startdatehour']);
	$enddateampm = $mybb->input['enddateampm'];
	$enddatehour = intval($mybb->input['enddatehour']);

	if($startdateampm == "pm")
	{
		$startdatehour = 12+$startdatehour;
		if($startdatehour >= 24)
		{
			$startdatehour = "00";
		}
	}
	if($enddateampm == "pm")
	{
		$enddatehour = 12+$enddatehour;
		if($enddatehour >= 24)
		{
			$enddatehour = "00";
		}
	}
	$startdate = gmmktime($startdatehour, intval($mybb->input['startdatemin']), 0, intval($mybb->input['startdatemonth']), intval($mybb->input['startdateday']), intval($mybb->input['startdateyear']));
	if($mybb->input['end'] == "never")
	{
		$enddate = '0';
	}
	else
	{
		$enddate = gmmktime($enddatehour, intval($mybb->input['enddatemin']), 0, intval($mybb->input['enddatemonth']), intval($mybb->input['enddateday']), intval($mybb->input['enddateyear']));
	}
	$sqlarray = array(
		"fid" => $fid,
		"uid" => $mybbadmin['uid'],
		"subject" => $subject,
		"message" => $message,
		"startdate" => $startdate,
		"enddate" => $enddate,
		"allowhtml" => $db->escape_string($mybb->input['allowhtml']),
		"allowmycode" => $db->escape_string($mybb->input['allowmycode']),
		"allowsmilies" => $db->escape_string($mybb->input['allowsmilies']),
	);
	$plugins->run_hooks("admin_announcements_do_add");
	$db->insert_query("announcements", $sqlarray);
	cpredirect("announcements.php?".SID, $lang->announcement_added);
}
if($mybb->input['action'] == "do_delete")
{
	if($mybb->input['deletesubmit'])
	{
		$plugins->run_hooks("admin_announcements_do_delete");
		$db->delete_query("announcements", "aid = '$aid'");
		cpredirect("announcements.php?".SID, $lang->announcement_deleted);
	}
	else
	{
		$mybb->input['action'] = "modify";
	}
}
if($mybb->input['action'] == "do_edit")
{
	$message = $db->escape_string($mybb->input['message']);
	$subject = $db->escape_string($mybb->input['subject']);
	$startdateampm = $mybb->input['startdateampm'];
	$startdatehour = intval($mybb->input['startdatehour']);
	$enddateampm = $mybb->input['enddateampm'];
	$enddatehour = intval($mybb->input['enddatehour']);

	if($startdateampm == "pm")
	{
		$startdatehour = 12+$startdatehour;
		if($startdatehour >= 24)
		{
			$startdatehour = "00";
		}
	}
	if($enddateampm == "pm")
	{
		$enddatehour = 12+$enddatehour;
		if($enddatehour >= 24)
		{
			$enddatehour = "00";
		}
	}
	$startdate = gmmktime($startdatehour, intval($mybb->input['startdatemin']), 0, intval($mybb->input['startdatemonth']), intval($mybb->input['startdateday']), intval($mybb->input['startdateyear']));
	if($mybb->input['end'] == "never")
	{
		$enddate = '0';
	}
	else
	{
		$enddate = gmmktime($enddatehour, intval($mybb->input['enddatemin']), 0, intval($mybb->input['enddatemonth']), intval($mybb->input['enddateday']), intval($mybb->input['enddateyear']));
	}
	$sqlarray = array(
		"aid" => intval($mybb->input['aid']), 
		"fid" => $fid,
		"uid" => $mybbadmin['uid'],
		"subject" => $subject,
		"message" => $message,
		"startdate" => $startdate,
		"enddate" => $enddate,
		"allowhtml" => $db->escape_string($mybb->input['allowhtml']),
		"allowmycode" => $db->escape_string($mybb->input['allowmycode']),
		"allowsmilies" => $db->escape_string($mybb->input['allowsmilies']),
	);
	$plugins->run_hooks("admin_announcements_do_edit");
	$db->update_query("announcements", $sqlarray, "aid='$aid'");
	cpredirect("announcements.php?".SID, $lang->announcement_edited);
}
if($mybb->input['action'] == "add") 
{
	$plugins->run_hooks("admin_announcements_add");
	cpheader();
	startform("announcements.php", "" , "do_add");
	starttable();
	tableheader($lang->add_announcement2);
	makeinputcode($lang->subject, "subject");
	$hourmin = explode("-", gmdate("g-i-a", time()));
	for($h = 1; $h <= 12; ++$h)
	{
		if($hourmin[0] == $h)
		{
			$startdatehour .= "<option value=\"$h\" selected>$h</option>";
			$enddatehour .= "<option value=\"$h\" selected>$h</option>";
		}
		else
		{
			$startdatehour .= "<option value=\"$h\">$h</option>";
			$enddatehour .= "<option value=\"$h\">$h</option>";
		}
	}
	if($hourmin[2] == "am")
	{
		$amsel = "selected";
	}
	else
	{
		$pmsel = "selected";
	}
	for($m = 0; $m <= 59; ++$m)
	{
		if(!$m)
		{ // 00
			$m = "00";
		}
		if(strlen($m) == 1)
		{
			$m = "0".$m;
		}
		if($hourmin[1] == $m)
		{
			$startdatemin .= "<option value=\"$m\" selected>$m</option>";
			$enddatemin .= "<option value=\"$m\" selected>$m</option>";
		}
		else
		{
			$startdatemin .= "<option value=\"$m\">$m</option>";
			$enddatemin .= "<option value=\"$m\">$m</option>";
		}
	}
	$day = gmdate("j", time());
	for($i = 1; $i <= 31; ++$i)
	{
		if($day == $i)
		{
			$startdateday .= "<option value=\"$i\" selected>$i</option>\n";
			$enddateday .= "<option value=\"$i\" selected>$i</option>\n";
		}
		else
		{
			$startdateday .= "<option value=\"$i\">$i</option>\n";
			$enddateday .= "<option value=\"$i\">$i</option>\n";
		}
	}
	$month = gmdate("m", time());
	$monthsel[$month] = "selected";
	$startdatemonth .= "<option value=\"01\" $monthsel[01]>January</option>\n";
	$enddatemonth .= "<option value=\"01\" $monthsel[01]>January</option>\n";
	$startdatemonth .= "<option value=\"02\" $monthsel[02]>February</option>\n";
	$enddatemonth .= "<option value=\"02\" $monthsel[02]>February</option>\n";
	$startdatemonth .= "<option value=\"03\" $monthsel[03]>March</option>\n";
	$enddatemonth .= "<option value=\"03\" $monthsel[03]>March</option>\n";
	$startdatemonth .= "<option value=\"04\" $monthsel[04]>April</option>\n";
	$enddatemonth .= "<option value=\"04\" $monthsel[04]>April</option>\n";
	$startdatemonth .= "<option value=\"05\" $monthsel[05]>May</option>\n";
	$enddatemonth .= "<option value=\"05\" $monthsel[05]>May</option>\n";
	$startdatemonth .= "<option value=\"06\" $monthsel[06]>June</option>\n";
	$enddatemonth .= "<option value=\"06\" $monthsel[06]>June</option>\n";
	$startdatemonth .= "<option value=\"07\" $monthsel[07]>July</option>\n";
	$enddatemonth .= "<option value=\"07\" $monthsel[07]>July</option>\n";
	$startdatemonth .= "<option value=\"08\" $monthsel[08]>August</option>\n";
	$enddatemonth .= "<option value=\"08\" $monthsel[08]>August</option>\n";
	$startdatemonth .= "<option value=\"09\" $monthsel[09]>September</option>\n";
	$enddatemonth .= "<option value=\"09\" $monthsel[09]>September</option>\n";
	$startdatemonth .= "<option value=\"10\" $monthsel[10]>October</option>\n";
	$enddatemonth .= "<option value=\"10\" $monthsel[10]>October</option>\n";
	$startdatemonth .= "<option value=\"11\" $monthsel[11]>November</option>\n";
	$enddatemonth .= "<option value=\"11\" $monthsel[11]>November</option>\n";
	$startdatemonth .= "<option value=\"12\" $monthsel[12]>December</option>\n";
	$enddatemonth .= "<option value=\"12\" $monthsel[12]>December</option>\n";
	$startdateyear = gmdate("Y", time());
	$enddateyear = gmdate("Y", time()) + 1;
	
	makelabelcode($lang->start_date, "<select name=\"startdatehour\">\n$startdatehour</select>\n &nbsp; \n<select name=\"startdatemin\">\n$startdatemin</select>\n &nbsp; \n<select name=\"startdateampm\"><option value=\"am\" $amsel>AM</option><option value=\"pm\" $pmsel>PM</option></select>\n &nbsp; \n<select name=\"startdateday\">\n$startdateday</select>\n &nbsp; \n<select name=\"startdatemonth\">\n$startdatemonth</select>\n &nbsp; \n<input type=\"text\" name=\"startdateyear\" value=\"$startdateyear\" size=\"4\" maxlength=\"4\"> (GMT)\n");
	makelabelcode($lang->end_date, "<input type=\"radio\" name=\"end\" value=\"selected\" checked=\"checked\" /> <select name=\"enddatehour\">\n$enddatehour</select>\n &nbsp; \n<select name=\"enddatemin\">\n$enddatemin</select>\n &nbsp; \n<select name=\"enddateampm\"><option value=\"am\" $amsel>AM</option><option value=\"pm\" $pmsel>PM</option></select>\n &nbsp; \n<select name=\"enddateday\">\n$enddateday</select>\n &nbsp; \n<select name=\"enddatemonth\">\n$enddatemonth</select>\n &nbsp; \n<input type=\"text\" name=\"enddateyear\" value=\"$enddateyear\" size=\"4\" maxlength=\"4\"> (GMT)<br /><input type=\"radio\" name=\"end\" value=\"never\" /> $lang->never\n");
	maketextareacode($lang->announcement, "message", "", "10", "50");
	makeyesnocode($lang->allow_html, "allowhtml", "yes");
	makeyesnocode($lang->allow_mycode, "allowmycode", "yes");
	makeyesnocode($lang->allow_smilies, "allowsmilies", "yes");
	makelabelcode($lang->parent_forum, forumselect("fid", $fid, "", "", "0", $lang->global_to_all));
	$plugins->run_hooks("admin_announcements_add_form");
	endtable();
	endform($lang->add_announcement2, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "delete")
{
	$plugins->run_hooks("admin_announcements_delete");
	$query = $db->simple_select("announcements", "*", "aid = '".intval($mybb->input['aid'])."'");
	$announcement = $db->fetch_array($query);
	cpheader();
	startform("announcements.php", "", "do_delete");
	makehiddencode("aid", $mybb->input['aid']);
	starttable();

	$lang->delete_announcement2 = sprintf($lang->delete_announcement2, $announcement['subject']);
	$lang->delete_announcement_confirm = sprintf($lang->delete_announcement_confirm, $announcement['subject']);

	tableheader($lang->delete_announcement2, "", 1);
	$yes = makebuttoncode("deletesubmit", $lang->yes);
	$no = makebuttoncode("no", $lang->no);
	makelabelcode("<div align=\"center\">$lang->delete_announcement_confirm<br /><br />$yes$no</div>", "");
	endtable();
	endform();
	cpfooter();
}
if($mybb->input['action'] == "edit")
{
	$query = $db->simple_select("announcements", "*", "aid = '$aid'");
	$announcement = $db->fetch_array($query);

	$plugins->run_hooks("admin_announcements_edit");
	
	$lang->nav_edit_announcement = sprintf($lang->nav_edit_announcement, $announcement['subject']);
	addacpnav($lang->nav_edit_announcement);
	if(!$noheader)
	{
		cpheader();
	}
	startform("announcements.php", "" , "do_edit");
	makehiddencode("aid", "$aid");
	starttable();

	$lang->edit_announcement2 = sprintf($lang->edit_announcement2, $announcement['subject']);

	tableheader($lang->edit_announcement2);
	makeinputcode($lang->subject, "subject", "$announcement[subject]");
	$startdate = explode("-", gmdate("j-m-Y-g-i-a", $announcement['startdate']));
	$enddate = explode("-", gmdate("j-m-Y-g-i-a", $announcement['enddate']));

	for($h = 1; $h <= 12; ++$h)
	{
		if($startdate[3] == $h)
		{
			$startdatehour .= "<option value=\"$h\" selected>$h</option>";
		}
		else
		{
			$startdatehour .= "<option value=\"$h\">$h</option>";
		}
		if($enddate[3] == $h)
		{
			$enddatehour .= "<option value=\"$h\" selected>$h</option>";
		}
		else
		{
			$enddatehour .= "<option value=\"$h\">$h</option>";
		}
	}
	if($startdate[5] == "am")
	{
		$samsel = "selected";
	}
	else
	{
		$spmsel = "selected";
	}
	if($enddate[5] == "am")
	{
		$eamsel = "selected";
	}
	else
	{
		$epmsel = "selected";
	}
	for($m = 0; $m <= 59; ++$m)
	{
		if(!$m)
		{ // 00
			$m = "00";
		}
		if(strlen($m) == 1)
		{
			$m = "0".$m;
		}
		if($startdate[4] == $m)
		{
			$startdatemin .= "<option value=\"$m\" selected>$m</option>";
		}
		else
		{
			$startdatemin .= "<option value=\"$m\">$m</option>";
		}
		if($enddate[4] == $m)
		{
			$enddatemin .= "<option value=\"$m\" selected>$m</option>";
		}
		else
		{
			$enddatemin .= "<option value=\"$m\">$m</option>";
		}
	}

	for($i = 1; $i <= 31; ++$i)
	{
		if($startdate[0] == $i)
		{
			$startdateday .= "<option value=\"$i\" selected>$i</option>\n";
		}
		else
		{
			$startdateday .= "<option value=\"$i\">$i</option>\n";
		}
		if($enddate[0] == $i)
		{
			$enddateday .= "<option value=\"$i\" selected>$i</option>\n";
		}
		else
		{
			$enddateday .= "<option value=\"$i\">$i</option>\n";
		}
	}
	$startmonthsel[$startdate[1]] = "selected";
	$endmonthsel[$enddate[1]] = "selected";
	$startdatemonth .= "<option value=\"01\" $startmonthsel[01]>January</option>\n";
	$enddatemonth .= "<option value=\"01\" $endmonthsel[01]>January</option>\n";
	$startdatemonth .= "<option value=\"02\" $startmonthsel[02]>February</option>\n";
	$enddatemonth .= "<option value=\"02\" $endmonthsel[02]>February</option>\n";
	$startdatemonth .= "<option value=\"03\" $startmonthsel[03]>March</option>\n";
	$enddatemonth .= "<option value=\"03\" $endmonthsel[03]>March</option>\n";
	$startdatemonth .= "<option value=\"04\" $startmonthsel[04]>April</option>\n";
	$enddatemonth .= "<option value=\"04\" $endmonthsel[04]>April</option>\n";
	$startdatemonth .= "<option value=\"05\" $startmonthsel[05]>May</option>\n";
	$enddatemonth .= "<option value=\"05\" $endmonthsel[05]>May</option>\n";
	$startdatemonth .= "<option value=\"06\" $startmonthsel[06]>June</option>\n";
	$enddatemonth .= "<option value=\"06\" $endmonthsel[06]>June</option>\n";
	$startdatemonth .= "<option value=\"07\" $startmonthsel[07]>July</option>\n";
	$enddatemonth .= "<option value=\"07\" $endmonthsel[07]>July</option>\n";
	$startdatemonth .= "<option value=\"08\" $startmonthsel[08]>August</option>\n";
	$enddatemonth .= "<option value=\"08\" $endmonthsel[08]>August</option>\n";
	$startdatemonth .= "<option value=\"09\" $startmonthsel[09]>September</option>\n";
	$enddatemonth .= "<option value=\"09\" $endmonthsel[09]>September</option>\n";
	$startdatemonth .= "<option value=\"10\" $startmonthsel[10]>October</option>\n";
	$enddatemonth .= "<option value=\"10\" $endmonthsel[10]>October</option>\n";
	$startdatemonth .= "<option value=\"11\" $startmonthsel[11]>November</option>\n";
	$enddatemonth .= "<option value=\"11\" $endmonthsel[11]>November</option>\n";
	$startdatemonth .= "<option value=\"12\" $startmonthsel[12]>December</option>\n";
	$enddatemonth .= "<option value=\"12\" $endmonthsel[12]>December</option>\n";
	
	if($announcement['enddate'])
	{
		$endcheck = " checked=\"checked\"";
		$endnever = "";
	}
	else
	{
		$endcheck = "";
		$endnever = " checked=\"checked\"";
	}

	makelabelcode($lang->start_date, "<select name=\"startdatehour\">\n$startdatehour</select>\n &nbsp; \n<select name=\"startdatemin\">\n$startdatemin</select>\n &nbsp; \n<select name=\"startdateampm\"><option value=\"am\" $samsel>AM</option><option value=\"pm\" $spmsel>PM</option></select>\n &nbsp; \n<select name=\"startdateday\">\n$startdateday</select>\n &nbsp; \n<select name=\"startdatemonth\">\n$startdatemonth</select>\n &nbsp; \n<input type=\"text\" name=\"startdateyear\" value=\"$startdate[2]\" size=\"4\" maxlength=\"4\"> (GMT)\n");
	makelabelcode($lang->end_date, "<input type=\"radio\" name=\"end\" value=\"selected\"$endcheck /> <select name=\"enddatehour\">\n$enddatehour</select>\n &nbsp; \n<select name=\"enddatemin\">\n$enddatemin</select>\n &nbsp; \n<select name=\"enddateampm\"><option value=\"am\" $eamsel>AM</option><option value=\"pm\" $epmsel>PM</option></select>\n &nbsp; \n<select name=\"enddateday\">\n$enddateday</select>\n &nbsp; \n<select name=\"enddatemonth\">\n$enddatemonth</select>\n &nbsp; \n<input type=\"text\" name=\"enddateyear\" value=\"$enddate[2]\" size=\"4\" maxlength=\"4\"> (GMT)<br /><input type=\"radio\" name=\"end\" value=\"never\"$endnever /> $lang->never\n");
	maketextareacode($lang->announcement, "message", "$announcement[message]", "10", "50");
	makeyesnocode($lang->allow_html, "allowhtml", "$announcement[allowhtml]");
	makeyesnocode($lang->allow_mycode, "allowmycode", "$announcement[allowmycode]");
	makeyesnocode($lang->allow_smilies, "allowsmilies", "$announcement[allowsmilies]");
	makelabelcode($lang->parent_forum, forumselect("fid", $announcement['fid'], "", "", "0", $lang->global_to_all));
	endtable();
	$plugins->run_hooks("admin_announcements_edit_form");
	endform($lang->update_announcement, $lang->reset_button);
	cpfooter();
}
if($mybb->input['action'] == "modify" || $mybb->input['action'] == "")
{
	$plugins->run_hooks("admin_announcements_modify");
	if(!$noheader)
	{
		cpheader();
	}
	starttable();
	tableheader($lang->forum_announcements);
	$forumlist = getforums();
	$globallist = "\n<li><b>$lang->global_announcements</b>".makelinkcode($lang->add_announcement, "announcements.php?".SID."&amp;action=add&amp;fid=-1")."\n";
	$query = $db->simple_select("announcements", "*", "fid = '-1'");
	if($query)
	{
    $globalist .= "<ul>";
  }  
  while($globannouncement = $db->fetch_array($query))
	{
		$globallist .= "<li>$globannouncement[subject]".
		makelinkcode($lang->edit_announcement, "announcements.php?".SID."&amp;action=edit&amp;aid=$globannouncement[aid]").
		makelinkcode($lang->delete_announcement, "announcements.php?".SID."&amp;action=delete&amp;aid=$globannouncement[aid]")."</li>\n";
	}
	if($query)
	{
    $globalist .= "</ul>";
  } 
	$globallist .= "\n</li>";
	makelabelcode($lang->edit_delete_notice."<br /><ul>$globallist\n$forumlist</ul>", "");
	endtable();
	cpfooter();
}

?>
