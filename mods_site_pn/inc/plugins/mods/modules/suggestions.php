<?php

/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 */
 
// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

class Suggestions implements Modules
{
	public function setup()
	{
	}
	
	public function end()
	{
	}

	public function run_pre()
	{
		global $mybb, $lang, $db, $templates, $mods, $inline_errors, $action;
		
		// Submiting suggestion?
		if ($mybb->input['sub'] == 'submit')
		{
			if (!$mybb->user['uid'])
			{
				error_no_permission();
			}
					
			if ($mybb->request_method == "post")
			{
				// Correct post key?
				verify_post_check($mybb->input['my_post_key']);
				
				// Does the project exist? Must be approved in order to be viewed
				$pid = (int)$mybb->input['pid'];
				$project = $mods->projects->getByID($pid,true,true);
				if (empty($project))
				{
					$mods->error($lang->mods_invalid_pid);
				}
				
				// Now let's verify if we've got suggestions open
				if ($project['suggestions'] != 1)
				{
					$mods->error($lang->mods_suggestions_closed);
				}
				
				$errors = array();
				
				$name = trim_blank_chrs($mybb->input['title']);
				// Empty title?
				if (empty($name))
				{
					$errors[] = $lang->mods_suggestions_empty_title;
				}
				
				$description = $mybb->input['description'];
				// Empty description?
				if (trim_blank_chrs($description) == '')
				{
					$errors[] = $lang->mods_suggestions_empty_description;
				}
				
				if (count($errors) > 0)
				{
					$inline_errors = inline_error($errors);
				}
				else {
					// Everything's ok, submit suggestion
					$db->insert_query('mods_suggestions',
						array(
							'title' => $db->escape_string($name),
							'description' => $db->escape_string($description),
							'replyto' => 0,
							'date' => TIME_NOW,
							'uid' => (int)$mybb->user['uid'],
							'pid' => (int)$project['pid']
						)
					);
					
					// It's not an error. But a success message
					$inline_errors = '<div class="success">'.$lang->mods_suggestion_submitted.'</div>';
					
					$action = 'suggestions';
					$mybb->input['sub'] = '';
				}
			}
		}
		elseif ($mybb->input['sub'] == 'reply')
		{
			if (!$mybb->user['uid'])
			{
				error_no_permission();
			}
					
			if ($mybb->request_method == "post")
			{
				// Correct post key?
				verify_post_check($mybb->input['my_post_key']);
				
				// Does the project exist? Must be approved in order to be viewed
				$pid = (int)$mybb->input['pid'];
				$project = $mods->projects->getByID($pid,true,true);
				if (empty($project))
				{
					$mods->error($lang->mods_invalid_pid);
				}
				
				// Now let's verify if we've got suggestions open
				if ($project['suggestions'] != 1)
				{
					$mods->error($lang->mods_suggestions_closed);
				}
				
				$sid = (int)$mybb->input['sid'];
			
				// Does the suggestion exist?
				$query = $db->simple_select("mods_suggestions", "*", 'sid=\''.$sid.'\'');
				$suggestion = $db->fetch_array($query);
				if (empty($suggestion))
				{
					$mods->error($lang->mods_invalid_sid);
				}
				
				$description = $mybb->input['message'];
				// Empty description?
				if (trim_blank_chrs($description) == '')
				{
					$errors[] = $lang->mods_suggestions_empty_message;
				}
				
				if (empty($errors))
				{
					// Everything's ok, submit reply
					$db->insert_query('mods_suggestions',
						array(
							'title' => '',
							'description' => $db->escape_string($description),
							'replyto' => $sid,
							'date' => TIME_NOW,
							'uid' => (int)$mybb->user['uid'],
							'pid' => (int)$project['pid']
						)
					);
					
					// It's not an error. But a success message
					$inline_errors = '<div class="success">'.$lang->mods_suggestion_replied.'</div>';
				}
				else {
					$inline_errors = inline_error($errors);
				}
				
				$action = 'suggestions';
				$mybb->input['sub'] = 'view';
			}
		}
		elseif ($mybb->input['sub'] == 'delete')
		{
			if (!$mybb->user['uid'])
			{
				error_no_permission();
			}
			
			if ($mybb->request_method == "post")
			{
				// Correct post key?
				verify_post_check($mybb->input['my_post_key']);
				
				// Does the project exist? Must be approved in order to be viewed
				$pid = (int)$mybb->input['pid'];
				$project = $mods->projects->getByID($pid,true,true);
				if (empty($project))
				{
					$mods->error($lang->mods_invalid_pid);
				}
				
				// Now let's verify if we've got suggestions open otherwise we can't delete anything
				if ($project['suggestions'] != 1)
				{
					$mods->error($lang->mods_suggestions_closed);
				}
				
				$sid = (int)$mybb->input['sid'];
				
				// Does the reply or suggestion exist?
				$query = $db->simple_select("mods_suggestions", "*", 'sid=\''.$sid.'\'');
				$message = $db->fetch_array($query);
				if (empty($message))
				{
					$mods->error($lang->mods_invalid_sid);
				}
				
				// Are we moderators or the author of the project or the author of the reply or message? If not, error out.
				if ($project['uid'] != $mybb->user['uid'] && !$mods->check_permissions($mybb->settings['mods_mods']) && $message['uid'] != $mybb->user['uid'])
				{
					error_no_permission();
				}
				
				// Delete it
				$db->delete_query('mods_suggestions', 'sid=\''.intval($message['sid']).'\'');
				
				// Now we must delete its replies in case we deleted a suggestion
				if ($message['replyto'] == 0)
				{
					$db->delete_query('mods_suggestions', 'replyto=\''.intval($message['sid']).'\'');
					
					// It's not an error. But a success message
					$inline_errors = '<div class="success">'.$lang->mods_suggestion_deleted.'</div>';
					
					$mybb->input['sub'] = 'suggestions';
					$mybb->input['pid'] = $message['pid'];
				}
				else {
					// It's not an error. But a success message
					$inline_errors = '<div class="success">'.$lang->mods_suggestion_reply_deleted.'</div>';
					
					$mybb->input['sub'] = 'view';
					$mybb->input['sid'] = $message['replyto'];
				}
				
				$action = 'suggestions';
			}
		}
		elseif ($mybb->input['sub'] == 'edit')
		{
			if (!$mybb->user['uid'])
			{
				error_no_permission();
			}
			
			if ($mybb->request_method == "post")
			{
				// Correct post key?
				verify_post_check($mybb->input['my_post_key']);
				
				// Does the project exist? Must be approved in order to be viewed
				$pid = (int)$mybb->input['pid'];
				$project = $mods->projects->getByID($pid,true,true);
				if (empty($project))
				{
					$mods->error($lang->mods_invalid_pid);
				}
				
				// Now let's verify if we've got suggestions open otherwise we can't edit anything
				if ($project['suggestions'] != 1)
				{
					$mods->error($lang->mods_suggestions_closed);
				}
				
				$sid = (int)$mybb->input['sid'];
				
				// Does the reply or suggestion exist?
				$query = $db->simple_select("mods_suggestions", "*", 'sid=\''.$sid.'\'');
				$message = $db->fetch_array($query);
				if (empty($message))
				{
					$mods->error($lang->mods_invalid_sid);
				}
				
				// Are we moderators or the author of the project or the author of the reply or message? If not, error out.
				if ($project['uid'] != $mybb->user['uid'] && !$mods->check_permissions($mybb->settings['mods_mods']) && $message['uid'] != $mybb->user['uid'])
				{
					error_no_permission();
				}
				
				$description = $mybb->input['message'];
				// Empty description?
				if (trim_blank_chrs($description) == '')
				{
					$errors[] = $lang->mods_suggestions_empty_message;
				}
				
				if (empty($errors))
				{
					// Edit it
					$db->update_query('mods_suggestions', array('description' => $db->escape_string($description)), 'sid=\''.intval($message['sid']).'\'');
					
					if ($message['replyto'] == 0)
					{
						// It's not an error. But a success message
						$inline_errors = '<div class="success">'.$lang->mods_suggestion_edited.'</div>';
						
						$mybb->input['sub'] = 'view';
						$mybb->input['pid'] = $message['pid'];
					}
					else {
						// It's not an error. But a success message
						$inline_errors = '<div class="success">'.$lang->mods_suggestion_reply_edited.'</div>';
						
						$mybb->input['sub'] = 'view';
						$mybb->input['sid'] = $message['replyto'];
					}
				}
				else {
					$inline_errors = inline_error($errors);
				}
				
				$action = 'suggestions';
			}
		}
	}
	
	public function run()
	{
		global $mybb, $db, $lang, $mods, $primerblock, $rightblock, $content, $title, $theme, $templates, $navigation, $inline_errors;
		
		// Does the project exist? Must be approved in order to be viewed
		$pid = (int)$mybb->input['pid'];
		$project = $mods->projects->getByID($pid,true);
		if (empty($project))
		{
			$mods->error($lang->mods_invalid_pid);
		}
		
		// If the project hasn't been approved and we're not moderators we can't view the project
		if ($project['approved'] != 1 && !$mods->check_permissions($mybb->settings['mods_mods']))
		{
			$mods->error($lang->mods_invalid_pid);
		}
		
		// Now let's verify if we've got suggestions open
		if ($project['suggestions'] != 1)
		{
			$mods->error($lang->mods_suggestions_closed);
		}
		
		// Ok we passed the check, now prepare data to be output
		$project['name'] = htmlspecialchars_uni($project['name']);
		$project['description'] = htmlspecialchars_uni($project['description']);
		
		// Submiting suggestion?
		if ($mybb->input['sub'] == 'submit')
		{
			if (!$mybb->user['uid'])
			{
				error_no_permission();
			}
			
			$mybb->input['title'] = htmlspecialchars_uni($mybb->input['title']);
			$mybb->input['description'] = htmlspecialchars_uni($mybb->input['description']);
			
			// Primer
			$primer['title'] = $project['name']." - ".$lang->mods_suggestions;
			$primer['content'] = $lang->mods_primer_submit_suggestion;
			$meta_description = $primer['content'];
			$primer['content'] = '<p>'.$primer['content'].'</p>';
			
			// Get category so we can build the breadcrumb
			$cat = $mods->categories->getByID($project['cid']);
			
			switch ($cat['parent'])
			{
				case 'plugins':
					$parent = $lang->mods_plugins;
					break;
				case 'themes':
					$parent = $lang->mods_themes;
					break;
				case 'resources':
					$parent = $lang->mods_resources;
					break;
				case 'graphics':
					$parent = $lang->mods_graphics;
					break;
			}
			
			$breadcrumb = '<a href="'.$mybb->settings['bburl']."/mods.php?action=browse&amp;category=".$cat['parent'].'">'.$parent.'</a>';
			$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=browse&amp;category=".$cat['cid'].'">'.htmlspecialchars_uni($cat['name']).'</a>';
			$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=view&amp;pid=".$project['pid'].'">'.$project['name'].'</a>';
			$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=suggestions&amp;pid=".$project['pid'].'">'.$lang->mods_suggestions.'</a>';
			
			eval("\$navigation = \"".$templates->get("mods_nav")."\";");
			
			// Force Navigation here
			$mods->buildNavHighlight($cat['parent']);
			
			// Title
			$title .= ' - '.$project['name'].' - '.$lang->mods_suggestions.' - '.$lang->mods_submit;
			
			eval("\$primerblock = \"".$templates->get("mods_primer")."\";");
			eval("\$rightblock = \"".$templates->get("mods_suggestions_rightblock")."\";");
			eval("\$content = \"".$templates->get("mods_suggestions_submit")."\";");
		}
		elseif ($mybb->input['sub'] == 'view')
		{
			if (!$mybb->user['uid'])
			{
				error_no_permission();
			}
			
			$sid = (int)$mybb->input['sid'];
			
			// Does the suggestion exist?
			$query = $db->simple_select("mods_suggestions", "*", 'sid=\''.$sid.'\' AND replyto=0');
			$suggestion = $db->fetch_array($query);
			if (empty($suggestion))
			{
				$mods->error($lang->mods_invalid_sid);
			}
			
			$suggestion['title'] = htmlspecialchars_uni($suggestion['title']);
			$suggestion['description'] = nl2br(htmlspecialchars_uni($suggestion['description']));
			
			$suggestion['date'] = my_date($mybb->settings['dateformat'], $suggestion['date'], '', false).', '.my_date($mybb->settings['timeformat'], $suggestion['date']);
			
			if ($project['uid'] == $mybb->user['uid'] || $mods->check_permissions($mybb->settings['mods_mods']))
			{
				$perms = true;
			}
			else
				$perms = false;
			
			if ($perms || $suggestion['uid'] == $mybb->user['uid'])
			{
				$options = "<div style=\"float: right\"><a href=\"{$mybb->settings['bburl']}/mods.php?action=suggestions&amp;sub=edit&amp;sid={$suggestion['sid']}&amp;pid={$suggestion['pid']}&amp;my_post_key={$mybb->post_code}\">{$lang->mods_edit}</a> - <a href=\"{$mybb->settings['bburl']}/mods.php?action=suggestions&amp;sub=delete&amp;sid={$suggestion['sid']}&amp;pid={$suggestion['pid']}&amp;my_post_key={$mybb->post_code}\">{$lang->mods_delete}</a></div>";
			}
			
			// pagination
			$per_page = 5;
			$mybb->input['page'] = intval($mybb->input['page']);
			if($mybb->input['page'] && $mybb->input['page'] > 1)
			{
				$mybb->input['page'] = intval($mybb->input['page']);
				$start = ($mybb->input['page']*$per_page)-$per_page;
			}
			else
			{
				$mybb->input['page'] = 1;
				$start = 0;
			}
			
			$query = $db->simple_select("mods_suggestions", "COUNT(sid) as suggestions", 'replyto=\''.$sid.'\'');
			$total_rows = $db->fetch_field($query, "suggestions");
			
			// multi-page
			if ($total_rows > $per_page)
				$multipage = multipage($total_rows, $per_page, $mybb->input['page'], $mybb->settings['bburl']."/mods.php?action=suggestions&amp;sub=view&amp;pid=".$pid."&amp;sid=".$sid);	
			
			// Get list of replies
			$query = $db->query("
				SELECT u.*, s.*
				FROM ".TABLE_PREFIX."mods_suggestions s
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=s.uid)
				WHERE s.replyto='{$sid}'
				ORDER BY s.date DESC LIMIT {$start}, {$per_page}
			");
			
			$replies = '';
			
			while($reply = $db->fetch_array($query))
			{
				$reply['title'] = htmlspecialchars_uni($reply['name']);
				$reply['description'] = nl2br(htmlspecialchars_uni($reply['description']));
				$reply['author'] = build_profile_link(htmlspecialchars_uni($reply['username']), $reply['uid']);
				
				$reply['date'] = my_date($mybb->settings['dateformat'], $reply['date'], '', false).', '.my_date($mybb->settings['timeformat'], $reply['date']);
				
				// If we're a moderator, if we're the author of the project or if we're the author of the reply, we can delete the reply
				if ($perms || $reply['uid'] == $mybb->user['uid'])
				{
					$reply['options'] = "<br /><a href=\"{$mybb->settings['bburl']}/mods.php?action=suggestions&amp;sub=edit&amp;sid={$reply['sid']}&amp;pid={$reply['pid']}&amp;my_post_key={$mybb->post_code}\">{$lang->mods_edit}</a> - <a href=\"{$mybb->settings['bburl']}/mods.php?action=suggestions&amp;sub=delete&amp;sid={$reply['sid']}&amp;pid={$reply['pid']}&amp;my_post_key={$mybb->post_code}\">{$lang->mods_delete}</a>";
				}
				
				eval("\$replies .= \"".$templates->get("mods_suggestions_reply")."\";");
			}
			
			if (empty($replies))
			{
				$colspan = 3;
				eval("\$replies = \"".$templates->get("mods_no_data")."\";");
			}
			
			// Primer
			$primer['title'] = $project['name']." - ".$lang->mods_suggestions;
			$primer['content'] = $lang->mods_primer_submit_suggestion;
			$meta_description = $primer['content'];
			$primer['content'] = '<p>'.$primer['content'].'</p>';
			
			// Get category so we can build the breadcrumb
			$cat = $mods->categories->getByID($project['cid']);
			
			switch ($cat['parent'])
			{
				case 'plugins':
					$parent = $lang->mods_plugins;
					break;
				case 'themes':
					$parent = $lang->mods_themes;
					break;
				case 'resources':
					$parent = $lang->mods_resources;
					break;
				case 'graphics':
					$parent = $lang->mods_graphics;
					break;
			}
			
			$breadcrumb = '<a href="'.$mybb->settings['bburl']."/mods.php?action=browse&amp;category=".$cat['parent'].'">'.$parent.'</a>';
			$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=browse&amp;category=".$cat['cid'].'">'.htmlspecialchars_uni($cat['name']).'</a>';
			$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=view&amp;pid=".$project['pid'].'">'.$project['name'].'</a>';
			$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=suggestions&amp;pid=".$project['pid'].'">'.$lang->mods_suggestions.'</a>';
			
			eval("\$navigation = \"".$templates->get("mods_nav")."\";");
			
			// Force Navigation here
			$mods->buildNavHighlight($cat['parent']);
			
			// Title
			$title .= ' - '.htmlspecialchars_uni($project['name']).' - '.$lang->mods_suggestions.' - "'.$suggestion['title'].'"';
			
			eval("\$primerblock = \"".$templates->get("mods_primer")."\";");
			eval("\$content = \"".$templates->get("mods_suggestions_view")."\";");
		}
		elseif ($mybb->input['sub'] == 'delete')
		{
			if (!$mybb->user['uid'])
			{
				error_no_permission();
			}
			
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			$uid = (int)$mybb->user['uid'];
			$sid = (int)$mybb->input['sid'];
			
			// Does the suggestion or reply exist?
			$query = $db->simple_select("mods_suggestions", "*", 'sid=\''.$sid.'\'');
			$message = $db->fetch_array($query);
			if (empty($message))
			{
				$mods->error($lang->mods_invalid_sid);
			}
			
			// Are we moderators or the author of the project or the author of the reply or suggestion? If not, error out.
			if ($project['uid'] != $mybb->user['uid'] && !$mods->check_permissions($mybb->settings['mods_mods']) && $message['uid'] != $mybb->user['uid'])
			{
				error_no_permission();
			}
			
			if ($message['replyto'] == 0) {
				$title .= ' - '.htmlspecialchars_uni($project['name']).' - '.$lang->mods_suggestions.' - '.$lang->mods_delete_suggestion;
				$notice = $lang->mods_confirm_delete_suggestion;
			}
			else {
				$title .= ' - '.htmlspecialchars_uni($project['name']).' - '.$lang->mods_suggestions.' - '.$lang->mods_delete_reply;
				$notice = $lang->mods_confirm_delete_reply;
			}

			$otherfields = '<input type="hidden" name="sid" value="'.$sid.'" />';
			$otherfields .= '<input type="hidden" name="pid" value="'.intval($project['pid']).'" />';
			$action = 'suggestions&amp;sub=delete';
			
			eval("\$content = \"".$templates->get("mods_do_action")."\";");
		}
		elseif ($mybb->input['sub'] == 'edit')
		{
			if (!$mybb->user['uid'])
			{
				error_no_permission();
			}
			
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			$uid = (int)$mybb->user['uid'];
			$sid = (int)$mybb->input['sid'];
			
			// Does the suggestion or reply exist?
			$query = $db->simple_select("mods_suggestions", "*", 'sid=\''.$sid.'\'');
			$message = $db->fetch_array($query);
			if (empty($message))
			{
				$mods->error($lang->mods_invalid_sid);
			}
			
			// Are we moderators or the author of the project or the author of the reply or suggestion? If not, error out.
			if ($project['uid'] != $mybb->user['uid'] && !$mods->check_permissions($mybb->settings['mods_mods']) && $message['uid'] != $mybb->user['uid'])
			{
				error_no_permission();
			}
			
			if ($message['replyto'] == 0) {
				$title .= ' - '.htmlspecialchars_uni($project['name']).' - '.$lang->mods_suggestions.' - '.$lang->mods_edit_suggestion;
				$notice = $lang->mods_confirm_edit_suggestion;
			}
			else {
				$title .= ' - '.htmlspecialchars_uni($project['name']).' - '.$lang->mods_suggestions.' - '.$lang->mods_edit_reply;
				$notice = $lang->mods_confirm_edit_reply;
			}

			$otherfields = '<input type="hidden" name="sid" value="'.$sid.'" />';
			$otherfields .= '<input type="hidden" name="pid" value="'.intval($project['pid']).'" />';
			$otherfields .= '<div class="form_container">
				<fieldset>
					<dl>
					<dt><label for="message">Description:</label></dt>
					<dd><textarea cols="10" rows="10" name="message">'.htmlspecialchars_uni($message['description']).'</textarea></dd>
					</dl>
				</fieldset>
			</div>';
			$action = 'suggestions&amp;sub=edit';
			
			eval("\$content = \"".$templates->get("mods_do_action")."\";");
		}
		else {
			// pagination
			$per_page = 15;
			$mybb->input['page'] = intval($mybb->input['page']);
			if($mybb->input['page'] && $mybb->input['page'] > 1)
			{
				$mybb->input['page'] = intval($mybb->input['page']);
				$start = ($mybb->input['page']*$per_page)-$per_page;
			}
			else
			{
				$mybb->input['page'] = 1;
				$start = 0;
			}
			
			// browsing someone's suggestions only?
			if ((int)$mybb->input['uid'] > 0)
			{
				$where = ' AND s.uid=\''.intval($mybb->input['uid']).'\'';
				$by = '&amp;uid='.intval($mybb->input['uid']);
			}
			
			$query = $db->simple_select("mods_suggestions", "COUNT(sid) as suggestions", str_replace("s.", "", "s.pid='{$pid}' AND s.replyto=0 {$where}"));
			$total_rows = $db->fetch_field($query, "suggestions");
			
			// multi-page
			if ($total_rows > $per_page)
				$multipage = multipage($total_rows, $per_page, $mybb->input['page'], $mybb->settings['bburl']."/mods.php?action=suggestions&amp;pid=".$pid.$by);	
			
			// Get list of suggestions
			$query = $db->query("
				SELECT u.*, s.*
				FROM ".TABLE_PREFIX."mods_suggestions s
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=s.uid)
				WHERE s.pid='{$pid}' AND s.replyto=0 {$where}
				ORDER BY s.date DESC LIMIT {$start}, {$per_page}
			");
			
			$suggestions = '';
			
			while($suggestion = $db->fetch_array($query))
			{
				$suggestion['title'] = htmlspecialchars_uni($suggestion['title']);
				$suggestion['author'] = build_profile_link(htmlspecialchars_uni($suggestion['username']), $suggestion['uid']);
				
				$suggestion['date'] = my_date($mybb->settings['dateformat'], $suggestion['date'], '', false).', '.my_date($mybb->settings['timeformat'], $suggestion['date']);
				
				eval("\$suggestions .= \"".$templates->get("mods_suggestions_suggestion")."\";");
			}
			
			if (empty($suggestions))
			{
				$colspan = 3;
				eval("\$suggestions = \"".$templates->get("mods_no_data")."\";");
			}
			
			// Primer
			$primer['title'] = $project['name']." - ".$lang->mods_suggestions;
			$primer['content'] = $lang->mods_primer_submit_suggestion;
			$meta_description = $primer['content'];
			$primer['content'] = '<p>'.$primer['content'].'</p>';
			
			// Get category so we can build the breadcrumb
			$cat = $mods->categories->getByID($project['cid']);
			
			switch ($cat['parent'])
			{
				case 'plugins':
					$parent = $lang->mods_plugins;
					break;
				case 'themes':
					$parent = $lang->mods_themes;
					break;
				case 'resources':
					$parent = $lang->mods_resources;
					break;
				case 'graphics':
					$parent = $lang->mods_graphics;
					break;
			}
			
			$breadcrumb = '<a href="'.$mybb->settings['bburl']."/mods.php?action=browse&amp;category=".$cat['parent'].'">'.$parent.'</a>';
			$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=browse&amp;category=".$cat['cid'].'">'.htmlspecialchars_uni($cat['name']).'</a>';
			$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=view&amp;pid=".$project['pid'].'">'.$project['name'].'</a>';
			$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=suggestions&amp;pid=".$project['pid'].'">'.$lang->mods_suggestions.'</a>';
			
			eval("\$navigation = \"".$templates->get("mods_nav")."\";");
			
			// Force Navigation here
			$mods->buildNavHighlight($cat['parent']);
			
			// Title
			$title .= ' - '.htmlspecialchars_uni($project['name']).' - '.$lang->mods_suggestions;
			
			eval("\$primerblock = \"".$templates->get("mods_primer")."\";");
			eval("\$rightblock = \"".$templates->get("mods_suggestions_rightblock")."\";");
			eval("\$content = \"".$templates->get("mods_suggestions")."\";");
		}
	}
}

?>