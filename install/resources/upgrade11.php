<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Upgrade Script: 1.2.10 or 1.2.11
 */


$upgrade_detail = array(
	"revert_all_templates" => 0,
	"revert_all_themes" => 0,
	"revert_all_settings" => 0
);

@set_time_limit(0);

function upgrade11_dbchanges()
{
	global $db, $output, $mybb;

	$output->print_header("Performing Queries");

	echo "<p>Performing necessary upgrade queries..</p>";

	$query = $db->simple_select("templates", "*", "title IN ('showthread_inlinemoderation','showthread_ratethread','editpost','newreply','usercp_drafts','newthread','usercp_options','forumdisplay_inlinemoderation','report','private_empty','usercp_profile','usercp_attachments','usercp_usergroups_joingroup','usercp_avatar','usercp_avatar_gallery','usercp_usergroups_memberof','managegroup','managegroup_adduser','managegroup_joinrequests','private_send','polls_editpoll','private_archive','calendar_addevent','moderation_inline_deleteposts','private_tracking','moderation_threadnotes','showthread_quickreply','member_emailuser','moderation_reports','member_login','index_loginform','moderation_deletethread','moderation_mergeposts','polls_newpoll','member_register_agreement','usercp_password','usercp_email','reputation_add','moderation_deletepoll','usercp_changeavatar','usercp_notepad','member_resetpassword','member_lostpw','usercp_changename','moderation_deleteposts','moderation_split','sendthread','usercp_editsig','private_read','error_nopermission','private_folders','moderation_move','moderation_merge','member_activate','usercp_editlists','calendar_editevent','member_resendactivation','moderation_inline_deletethreads','moderation_inline_movethreads','moderation_inline_mergeposts','moderation_inline_splitposts','member_register','showthread_moderationoptions','headerinclude','private','forumdisplay_threadlist_inlineedit_js')");
	while($template = $db->fetch_array($query))
	{
		if($template['title'] == "private_read")
		{
			$template['template'] = str_replace("private.php?action=delete&amp;pmid={\$pm['pmid']}", "private.php?action=delete&amp;pmid={\$pm['pmid']}&amp;my_post_key={\$mybb->post_code}", $template['template']);
		}
		elseif($template['title'] == "showthread_moderationoptions")
		{
			$template['template'] = str_replace('<input type="hidden" name="modtype" value="thread" />', '<input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
<input type="hidden" name="modtype" value="thread" />', $template['template']);

			$template['template'] = str_replace('moderation.php?action=\'+this.options[this.selectedIndex].value+\'&amp;tid={$tid}&amp;modtype=thread', 'moderation.php?action=\'+this.options[this.selectedIndex].value+\'&amp;tid={$tid}&amp;modtype=thread&amp;my_post_key={$mybb->post_code}', $template['template']);
		}
		elseif($template['title'] == "headerinclude")
		{
			$template['template'] = str_replace('var cookieDomain = "{$mybb->settings[\'cookiedomain\']}";', 'var my_post_key = \'{$mybb->post_code}\';
var cookieDomain = "{$mybb->settings[\'cookiedomain\']}";', $template['template']);
		}
		elseif($template['title'] == "forumdisplay_threadlist_inlineedit_js")
		{
			$template['template'] = str_replace('"xmlhttp.php?action=edit_subject"', '"xmlhttp.php?action=edit_subject&my_post_key="+my_post_key', $template['template']);
		}
		else
		{
			// Remove any duplicates
			$template['template'] = str_replace("<input type=\"hidden\" name=\"my_post_key\" value=\"{\$mybb->post_code}\" />", "", $template['template']);

			$template['template'] = preg_replace("#<form(.*?)method\=\\\"post\\\"(.*?)>#i", "<form$1method=\"post\"$2>\n<input type=\"hidden\" name=\"my_post_key\" value=\"{\$mybb->post_code}\" />", $template['template']);
		}

		// Update MyBB Javascript versions (to clear cache)
		$template['template'] = str_replace("?ver=121", "?ver=1212", $template['template']);

		$db->update_query("templates", array('template' => $db->escape_string($template['template']), 'version' => '1212'), "tid='{$template['tid']}'", 1);
	}

	$contents .= "Click next to continue with the upgrade process.</p>";
	$output->print_contents($contents);
	$output->print_footer("11_done");
}

