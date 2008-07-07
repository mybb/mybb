<?php
/** TO BE REMOVED BEFORE RELEASE **/
/**
 * MyBB 1.4
 * Copyright © 2008 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */

/**
 * Upgrade Script: MyBB 1.4 Beta 1
 */


$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade13_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";
	
	$promotions = array();	
	$query = $db->simple_select("promotions", "requirements, registeredtype, pid");
	while($promotion = $db->fetch_array($query))
	{
		// Repair as much as we can of our promotions
		switch($promotion['requirements'])
		{
			case "po":
				$promotion['requirements'] = "postcount";
				break;
			case "re":
				$promotion['requirements'] = "reputation";
				break;
			case "ti":
				$promotion['requirements'] = "timeregistered";
				break;
		}
		
		switch($promotion['registeredtype'])
		{
			case "ho":
				$promotion['registeredtype'] = "hours";
				break;
			case "da":
				$promotion['registeredtype'] = "days";
				break;
			case "we":
				$promotion['registeredtype'] = "weeks";
				break;
			case "mo":
				$promotion['registeredtype'] = "months";
				break;
			case "ye":
				$promotion['registeredtype'] = "years";
				break;
		}
		
		$promotions[$promotion['pid']] = $promotion;
	}
	
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."promotions CHANGE requirements requirements varchar(200) NOT NULL default '';");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."promotions CHANGE registeredtype registeredtype varchar(20) NOT NULL default '';");
	$db->write_query("ALTER TABLE ".TABLE_PREFIX."promotions CHANGE originalusergroup originalusergroup varchar(120) NOT NULL default '0';");
	
	foreach($promotions as $pid => $promotion)
	{
		$db->update_query("promotions", array('registeredtype' => $promotion['registeredtype'], 'requirements' => $promotion['requirements']), "pid='{$pid}'");
	}
	
	$avatardimensions = str_replace('x', '|', $mybb->settings['postmaxavatarsize']);
	
	$db->simple_select("users", "uid", "avatar != '' && avatardimensions = ''");
	while($user = $db->fetch_array($query))
	{
		$db->update_user("users", array('avatardimensions' => $avatardimensions), "uid='{$user['uid']}'", 1);
	}
	
	// Update master templates 
	$query = $db->simple_select("templates", "*", "title IN ('newthread','newreply','polls_showresults_resultbit','member_register_password','member_register','modcp_finduser_user','private_send','online')");
	while($template = $db->fetch_array($query))
	{
		switch($template['title'])
		{
			case "newthread":
			case "newreply":
				$template['template'] = str_replace('{$editdraftpid}', '<input type="hidden" name="tid" value="{$tid}" />
{$editdraftpid}', $template['template']);
				break;
			case "polls_showresults_resultbit":
				$template['template'] = str_replace('<td class="{$optionbg}"><img src="{$theme[\'imgdir\']}/pollbar-s.gif" alt="" />', '<td class="{$optionbg}" width="{$imagerowwidth}"><img src="{$theme[\'imgdir\']}/pollbar-s.gif" alt="" />', $template['template']);
				break;
			case "member_register_password":
				$template['template'] = str_replace('size="40"', 'style="width: 100%"', $template['template']);
				break;
			case "member_register":
				$template['template'] = str_replace(array('size="88"', 'size="40"'), 'style="width: 100%"', $template['template']);
				break;
			case "modcp_finduser_user":
				$template['template'] = str_replace('align="right">{$user[\'postnum\']}', 'align="center">{$user[\'postnum\']}', $template['template']);
				break;
			case "private_read":
				$template['template'] = str_replace('{$action_time}
{$message}
</table>', '</table>
{$action_time}
{$message}', $template['template']);
				break;
			case "online":
				$template['template'] = str_replace('<table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder">
		<tr>
			<td align="center" class="trow1"><span class="smalltext">{$lang->online_count}</span></td>
		</tr>
	</table>', '', $template['template']);
				break;
		}
		
		$db->update_query("templates", array('template' => $db->escape_string($template['template']), 'version' => '1212'), "tid='{$template['tid']}'", 1);
	}

	
	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("13_done");
}

?>