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

class Bugtracker implements Modules
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
		
		// Submiting bug?
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
				
				// Now let's verify if we've got bug tracking open
				if ($project['bugtracking'] != 1 && $project['bugtracker_link'] == '')
				{
					$mods->error($lang->mods_bugtracker_closed);
				}
				
				// If this is set to 0 it means it's open for everyone
				// otherwise if set to 1 it means only collaborators and those whose uid is in the restrcited access field can access it
				if ($project['bugtracking_collabs'] == 1)
				{
					if ($project['collaborators'] != '')
					{
						$project['collaborators'] = explode(',', $project['collaborators']);
					}
					else
						$project['collaborators'] = array();
						
					if (!in_array($mybb->user['uid'], $project['collaborators']) && $mybb->user['uid'] != $project['uid'])
					{
						// Ok now that we're here we need to make sure that if the bug tracker is restricted, that we have access to it
						if ($project['testers'] != '' && $mybb->user['uid'] != $project['uid'] && $project['bugtracker_link'] == '')
						{
							$testers = explode(',', $project['testers']);
							if (!in_array($mybb->user['uid'], $testers))
							{
								$mods->error($lang->mods_bugtacker_no_permission);
							}
						}
						else // else if it's not restricted to anyone besides the collaborators and we're not one, error out
							$mods->error($lang->mods_bugtacker_no_permission);
					}
				}
				
				$errors = array();
				
				$name = trim_blank_chrs($mybb->input['title']);
				// Empty title?
				if (empty($name))
				{
					$errors[] = $lang->mods_bugtracker_empty_title;
				}
				
				$description = $mybb->input['description'];
				// Empty description?
				if (trim_blank_chrs($description) == '')
				{
					$errors[] = $lang->mods_bugtracker_empty_description;
				}
				
				// Verify affected builds
				$affected = $mybb->input['affected'];
				if (!is_array($affected))
				{
					$errors[] = $lang->mods_bugtracker_invalid_builds;
				}
				
				$builds = '';
				
				if (is_array($affected) && !in_array(0, $affected))
				{
					$comma = '';
					$projbuilds = $mods->projects->builds->getAll('pid=\''.$project['pid'].'\'');
					foreach ($affected as $build)
					{
						if (!isset($projbuilds[(int)$build]))
						{
							$errors[] = $lang->mods_bugtracker_invalid_build;
							break;
						}
						
						$builds .= $comma.intval($build);
						$comma = ',';
					}
					
					if (my_strlen($builds) > 255)
					{
						$errors[] = $lang->mods_bugtracker_too_many;
					}
				}
				else 
				{
					$builds = 'all';
				}
				
				if (count($errors) > 0)
				{
					$inline_errors = inline_error($errors);
				}
				else {
					// Everything's ok, submit bug
					$bugid = $db->insert_query('mods_bugs',
						array(
							'title' => $db->escape_string($name),
							'description' => $db->escape_string($description),
							'replyto' => 0,
							'date' => TIME_NOW,
							'uid' => (int)$mybb->user['uid'],
							'pid' => (int)$project['pid'],
							'builds' => $builds,
							'date' => TIME_NOW
						)
					);
					
					// Send emails if notifications are not disabled
					if ($project['notifications'] != 3)
					{
						$message = $lang->sprintf($lang->mods_email_message,
							$project['name'],
							nl2br($description),
							$mybb->settings['bburl'].'/mods.php?action=bugtracker&amp;sub=view&amp;pid='.$project['pid'].'&amp;bid='.$bugid
						);

						// Collaborators and Author?
						if ($project['notifications'] == 1)
						{
							// Merge collaborators and author
							$uids = array_merge(array($project['uid']), $project['collaborators']);
							
							// Email collaborators
							$q = $db->simple_select('users', 'email', 'uid IN (\''.implode("','", $uids).'\')');
							while ($email = $db->fetch_field($q, 'email'))
								my_mail($email, $lang->mods_email_subject, $message, "", "", "", false, "html", "");
						}
						else {
						
							$q = $db->simple_select('users', 'email', 'uid = '.intval($project['uid']));
							$email = $db->fetch_field($q, 'email');
								
							// Email project author
							my_mail($email, $lang->mods_email_subject, $message, "", "", "", false, "html", "");
						}
					}
					
					// It's not an error. But a success message
					$inline_errors = '<div class="success">'.$lang->mods_bug_submitted.'</div>';
					
					$action = 'bugtracker';
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
				
				// Now let's verify if we've got bug tracking open
				if ($project['bugtracking'] != 1 && $project['bugtracker_link'] == '')
				{
					$mods->error($lang->mods_bugtracker_closed);
				}
				
				// If this is set to 0 it means it's open for everyone
				// otherwise if set to 1 it means only collaborators and those whose uid is in the restrcited access field can access it
				if ($project['bugtracking_collabs'] == 1)
				{
					if ($project['collaborators'] != '')
					{
						$project['collaborators'] = explode(',', $project['collaborators']);
					}
					else
						$project['collaborators'] = array();
						
					if (!in_array($mybb->user['uid'], $project['collaborators']) && $mybb->user['uid'] != $project['uid'])
					{
						// Ok now that we're here we need to make sure that if the bug tracker is restricted, that we have access to it
						if ($project['testers'] != '' && $mybb->user['uid'] != $project['uid'] && $project['bugtracker_link'] == '')
						{
							$testers = explode(',', $project['testers']);
							if (!in_array($mybb->user['uid'], $testers))
							{
								$mods->error($lang->mods_bugtacker_no_permission);
							}
						}
						else // else if it's not restricted to anyone besides the collaborators and we're not one, error out
							$mods->error($lang->mods_bugtacker_no_permission);
					}
				}
				
				$bid = (int)$mybb->input['bid'];
			
				// Does the bug exist?
				$query = $db->simple_select("mods_bugs", "*", 'bid=\''.$bid.'\'');
				$bug = $db->fetch_array($query);
				if (empty($bug))
				{
					$mods->error($lang->mods_invalid_bug);
				}
				
				$description = $mybb->input['message'];
				// Empty description?
				if (trim_blank_chrs($description) == '')
				{
					$errors[] = $lang->mods_bugtracker_empty_message;
				}
				
				if ($project['uid'] == $mybb->user['uid'] || $mods->check_permissions($mybb->settings['mods_mods']))
				{
					$perms = true;
				}
				else
					$perms = false;
				
				if ($project['collaborators'] != '' && !is_array($project['collaborators']))
				{
					$project['collaborators'] = explode(',', $project['collaborators']);
				}
				else
					$project['collaborators'] = array();
				
				// If we're the author, moderators or collaborators then we can update this bug
				if ($perms || in_array($mybb->user['uid'], $project['collaborators']))
				{
					// we need to verify each thing so we update only the necessary
					
					$update_array = array();
					
					// priority (changed?)
					if ($mybb->input['priority'])
					{
						$priority = (int)$mybb->input['priority'];
						
						switch ($priority)
						{
							case 1:
								$priority = 'low';
							break;
							case 2:
								$priority = 'medium';
							break;
							case 3:
								$priority = 'high';
							break;
							default:
								// error!
								$errors[] = $lang->mods_bugtracker_invalid_priority;
						}
						
						if (empty($errors) && $priority != $bug['priority'])
						{
							$update_array['priority'] = $priority;
						}
					}
					
					// status (changed?)
					if ($mybb->input['priority'])
					{
						$status = (int)$mybb->input['status'];
						
						switch ($status)
						{
							case 1:
								$status = '0';
							break;
							case 2:
								$status = '1';
							break;
							case 3:
								$status = '2';
							break;
							default:
								// error!
								$errors[] = $lang->mods_bugtracker_invalid_status;
						}
						
						if (empty($errors) && $status != $bug['status'])
						{
							$update_array['status'] = $status;
						}
					}
					
					// assignee (changed?)
					if (isset($mybb->input['assignee']))
					{
						$mybb->input['assignee'] = (int)$mybb->input['assignee'];
						
						// Merge collaborators and author
						$uids = array_merge(array($project['uid']), $project['collaborators']);
						
						if (!in_array($mybb->input['assignee'], $uids) && $mybb->input['assignee'] != 0)
						{
							$errors[] = $lang->mods_bugtracker_invalid_assignee;
						}
						
						if ($mybb->input['assignee'] == 0)
						{
							$mybb->input['assignee'] = '';
						}
						
						if (empty($errors))
						{
							$update_array['assignee'] = $mybb->input['assignee'];
							
							$assignee = $db->fetch_field($db->simple_select('users', 'username', 'uid=\''.intval($update_array['assignee']).'\''), 'username');
							$update_array['assignee_name'] = $assignee;
						}
					}
					
					// affected builds (changed?)
					if (isset($mybb->input['affected']) && is_array($mybb->input['affected']))
					{
						$affected = $mybb->input['affected'];
						
						// not selecting all of them?
						if (!in_array(0, $affected))
						{
							$comma = '';
							$projbuilds = $mods->projects->builds->getAll('pid=\''.$project['pid'].'\'');
							foreach ($affected as $build)
							{
								if (!isset($projbuilds[(int)$build]))
								{
									$errors[] = $lang->mods_bugtracker_invalid_build;
									break;
								}
								
								$builds .= $comma.intval($build);
								$comma = ',';
							}
							
							if (my_strlen($builds) > 255)
							{
								$errors[] = $lang->mods_bugtracker_too_many;
							}
						}
						else 
						{
							$builds = 'all';
						}
						
						if (empty($errors))
						{
							$update_array['builds'] = $builds;
						}
					}
				}
				
				if (empty($errors))
				{
					// Everything's ok, submit reply
					$db->insert_query('mods_bugs',
						array(
							'title' => '',
							'description' => $db->escape_string($description),
							'replyto' => $bid,
							'date' => TIME_NOW,
							'uid' => (int)$mybb->user['uid'],
							'pid' => (int)$project['pid']
						)
					);
					
					// update the bug properties if needed
					if (!empty($update_array))
					{
						$db->update_query('mods_bugs', $update_array, 'bid=\''.intval($bug['bid']).'\'');
					}
					
					$mybb->input['message'] = '';
					
					// It's not an error. But a success message
					$inline_errors = '<div class="success">'.$lang->mods_bugtracker_replied.'</div>';
				}
				else {
					$inline_errors = inline_error($errors);
				}
				
				$action = 'bugtracker';
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
				
				// Now let's verify if we've got bug tracking open
				if ($project['bugtracking'] != 1 && $project['bugtracker_link'] == '')
				{
					$mods->error($lang->mods_bugtracker_closed);
				}
				
				// If this is set to 0 it means it's open for everyone
				// otherwise if set to 1 it means only collaborators and those whose uid is in the restrcited access field can access it
				if ($project['bugtracking_collabs'] == 1)
				{
					if ($project['collaborators'] != '')
					{
						$project['collaborators'] = explode(',', $project['collaborators']);
					}
					else
						$project['collaborators'] = array();
						
					if (!in_array($mybb->user['uid'], $project['collaborators']) && $mybb->user['uid'] != $project['uid'])
					{
						// Ok now that we're here we need to make sure that if the bug tracker is restricted, that we have access to it
						if ($project['testers'] != '' && $mybb->user['uid'] != $project['uid'] && $project['bugtracker_link'] == '')
						{
							$testers = explode(',', $project['testers']);
							if (!in_array($mybb->user['uid'], $testers))
							{
								$mods->error($lang->mods_bugtacker_no_permission);
							}
						}
						else // else if it's not restricted to anyone besides the collaborators and we're not one, error out
							$mods->error($lang->mods_bugtacker_no_permission);
					}
				}
				
				// Are we moderators or the author of the project? If not, error out.
				if ($project['uid'] != $mybb->user['uid'] && !$mods->check_permissions($mybb->settings['mods_mods']))
				{
					error_no_permission();
				}
				
				$bid = (int)$mybb->input['bid'];
				
				// Does the reply or bug exist?
				$query = $db->simple_select("mods_bugs", "*", 'bid=\''.$bid.'\'');
				$message = $db->fetch_array($query);
				if (empty($message))
				{
					$mods->error($lang->mods_invalid_sid);
				}
				
				// Delete it
				$db->delete_query('mods_bugs', 'bid=\''.intval($message['bid']).'\'');
				
				// Now we must delete its replies in case we deleted a bug
				if ($message['replyto'] == 0)
				{
					$db->delete_query('mods_bugs', 'replyto=\''.intval($message['bid']).'\'');
					
					// It's not an error. But a success message
					$inline_errors = '<div class="success">'.$lang->mods_bugtracker_deleted.'</div>';
					
					$mybb->input['sub'] = 'bugtracker';
					$mybb->input['pid'] = $message['pid'];
				}
				else {
					// It's not an error. But a success message
					$inline_errors = '<div class="success">'.$lang->mods_bugtracker_reply_deleted.'</div>';
					
					$mybb->input['sub'] = 'view';
					$mybb->input['bid'] = $message['replyto'];
				}
				
				$action = 'bugtracker';
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
				
				// Now let's verify if we've got bug tracking open
				if ($project['bugtracking'] != 1 && $project['bugtracker_link'] == '')
				{
					$mods->error($lang->mods_bugtracker_closed);
				}
				
				// If this is set to 0 it means it's open for everyone
				// otherwise if set to 1 it means only collaborators and those whose uid is in the restrcited access field can access it
				if ($project['bugtracking_collabs'] == 1)
				{
					if ($project['collaborators'] != '')
					{
						$project['collaborators'] = explode(',', $project['collaborators']);
					}
					else
						$project['collaborators'] = array();
						
					if (!in_array($mybb->user['uid'], $project['collaborators']) && $mybb->user['uid'] != $project['uid'])
					{
						// Ok now that we're here we need to make sure that if the bug tracker is restricted, that we have access to it
						if ($project['testers'] != '' && $mybb->user['uid'] != $project['uid'] && $project['bugtracker_link'] == '')
						{
							$testers = explode(',', $project['testers']);
							if (!in_array($mybb->user['uid'], $testers))
							{
								$mods->error($lang->mods_bugtacker_no_permission);
							}
						}
						else // else if it's not restricted to anyone besides the collaborators and we're not one, error out
							$mods->error($lang->mods_bugtacker_no_permission);
					}
				}
				
				// Are we moderators or the author of the project? If not, error out.
				if ($project['uid'] != $mybb->user['uid'] && !$mods->check_permissions($mybb->settings['mods_mods']))
				{
					error_no_permission();
				}
				
				$bid = (int)$mybb->input['bid'];
				
				// Does the reply or bug exist?
				$query = $db->simple_select("mods_bugs", "*", 'bid=\''.$bid.'\'');
				$message = $db->fetch_array($query);
				if (empty($message))
				{
					$mods->error($lang->mods_invalid_bug);
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
					$errors[] = $lang->mods_bugtracker_empty_message;
				}
				
				if (empty($errors))
				{
					// Edit it
					$db->update_query('mods_bugs', array('description' => $db->escape_string($description)), 'bid=\''.intval($message['bid']).'\'');
					
					if ($message['replyto'] == 0)
					{
						// It's not an error. But a success message
						$inline_errors = '<div class="success">'.$lang->mods_bugtracker_edited.'</div>';
						
						$mybb->input['sub'] = 'view';
						$mybb->input['pid'] = $message['pid'];
					}
					else {
						// It's not an error. But a success message
						$inline_errors = '<div class="success">'.$lang->mods_bugtracker_reply_edited.'</div>';
						
						$mybb->input['sub'] = 'view';
						$mybb->input['bid'] = $message['replyto'];
					}
				}
				else {
					$inline_errors = inline_error($errors);
				}
				
				$action = 'bugtracker';
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
		
		$project['name'] = htmlspecialchars_uni($project['name']);
		
		// Now let's verify if we've got bug tracking open
		if ($project['bugtracking'] != 1 && $project['bugtracker_link'] == '')
		{
			$mods->error($lang->mods_bugtracker_closed);
		}
		
		// If this is set to 0 it means it's open for everyone
		// otherwise if set to 1 it means only collaborators and those whose uid is in the restrcited access field can access it
		if ($project['bugtracking_collabs'] == 1)
		{
			if ($project['collaborators'] != '')
			{
				$project['collaborators'] = explode(',', $project['collaborators']);
			}
			else
				$project['collaborators'] = array();
				
			if (!in_array($mybb->user['uid'], $project['collaborators']) && $mybb->user['uid'] != $project['uid'])
			{
				// Ok now that we're here we need to make sure that if the bug tracker is restricted, that we have access to it
				if ($project['testers'] != '' && $mybb->user['uid'] != $project['uid'] && $project['bugtracker_link'] == '')
				{
					$testers = explode(',', $project['testers']);
					if (!in_array($mybb->user['uid'], $testers))
					{
						$mods->error($lang->mods_bugtacker_no_permission);
					}
				}
				else // else if it's not restricted to anyone besides the collaborators and we're not one, error out
					$mods->error($lang->mods_bugtacker_no_permission);
			}
		}
		
		$title .= ' - '.$lang->mods_bugtracker;
		
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
		$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=bugtracker&amp;pid=".$project['pid'].'">'.$lang->mods_bugtracker.'</a>';
		
		eval("\$navigation = \"".$templates->get("mods_nav")."\";");
		
		// Force Navigation here
		$mods->buildNavHighlight($cat['parent']);
		
		// Inform the user about the external bug tracker
		if ($project['bugtracker_link'] != '')
		{
			$notice = $lang->mods_bugtracker_notice;
			$link = htmlspecialchars_uni($project['bugtracker_link']);
			
			eval("\$content = \"".$templates->get("mods_redirect")."\";");
		}
		else // show the bug tracker page
		{
			if ($mybb->input['sub'] == 'submit')
			{
				$affected = $mybb->input['affected'];
				
				if(!empty($affected) && is_array($affected) && in_array(0, $affected))
				{
					$selectall = "selected=\"SELECTED\"";
				}
				else
					$selectall = '';
			
				$builds = '';
				// Get all builds of a certain project
				$projbuilds = $mods->projects->builds->getAll('pid=\''.$project['pid'].'\'');
				foreach ($projbuilds as $build)
				{
					if (is_array($affected) && in_array($build['bid'], $affected) && !$selectall)
						$builds .= '<option value="'.$build['bid'].'" selected="selected">#'.$build['number'].' ('.$build['status'].')</option>';
					else
						$builds .= '<option value="'.$build['bid'].'">#'.$build['number'].' ('.$build['status'].')</option>';
				}
				
				$mybb->input['title'] = htmlspecialchars_uni($mybb->input['title']);
				$mybb->input['description'] = htmlspecialchars_uni($mybb->input['description']);
			
				// Primer
				$primer['title'] = $project['name']." - ".$lang->mods_bugtracker;
				$primer['content'] = $lang->mods_primer_bugtracker;
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
				$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=bugtracker&amp;pid=".$project['pid'].'">'.$lang->mods_bugtracker.'</a>';
				
				eval("\$navigation = \"".$templates->get("mods_nav")."\";");
				
				// Force Navigation here
				$mods->buildNavHighlight($cat['parent']);
				
				// Title
				$title .= ' - '.$project['name'].' - '.$lang->mods_bugtracker.' - '.$lang->mods_submit;
				
				eval("\$primerblock = \"".$templates->get("mods_primer")."\";");
				eval("\$rightblock = \"".$templates->get("mods_bugtracker_rightblock")."\";");
				eval("\$content = \"".$templates->get("mods_bugtracker_submit")."\";");
			}
			elseif ($mybb->input['sub'] == 'view')
			{
				if (!$mybb->user['uid'])
				{
					error_no_permission();
				}
				
				$bid = (int)$mybb->input['bid'];
				
				// Does the bug exist?
				$query = $db->simple_select("mods_bugs", "*", 'bid=\''.$bid.'\' AND replyto=0');
				$bug = $db->fetch_array($query);
				if (empty($bug))
				{
					$mods->error($lang->mods_invalid_bug);
				}
				
				$bug['title'] = htmlspecialchars_uni($bug['title']);
				$bug['description'] = nl2br(htmlspecialchars_uni($bug['description']));
				
				$bug['username'] = $db->fetch_field($db->simple_select('users', 'username', 'uid=\''.intval($bug['uid']).'\''), 'username');
				$bug['author'] = build_profile_link(htmlspecialchars_uni($bug['username']), $bug['uid']);
				
				$bug['date'] = my_date($mybb->settings['dateformat'], $bug['date'], '', false).', '.my_date($mybb->settings['timeformat'], $bug['date']);
				
				// Status: 0 for open, 1 for closed, 2 for rejected
				if ($bug['status'] == '1')
				{
					$bug['title'] = '<del>'.htmlspecialchars_uni($bug['title']).'</del>';
					$bug['status'] = $lang->mods_closed;
				}
				elseif ($bug['status'] == '2')
				{
					$bug['status'] = $lang->mods_rejected;
				}
				elseif ($bug['status'] == '0')
				{
					$bug['status'] = $lang->mods_open;
				}
					
					
				// Priority
				switch ($bug['priority'])
				{
					case 'low':
						$bug['priority'] = $lang->mods_low_priority;
					break;
					
					case 'medium':
						$bug['priority'] = $lang->mods_medium_priority;
					break;
					
					case 'high':
						$bug['priority'] = $lang->mods_high_priority;
					break;
				}
				
				// Affected builds
				if ($bug['builds'] == '' || $bug['builds'] == 'all')
				{
					$bug['builds'] = $lang->mods_all;
				}
				
				if ($project['collaborators'] != '' && !is_array($project['collaborators']))
				{
					$project['collaborators'] = explode(',', $project['collaborators']);
				}
				else
					$project['collaborators'] = array();
				
				if ($project['uid'] == $mybb->user['uid'] || $mods->check_permissions($mybb->settings['mods_mods']))
				{
					$perms = true;
				}
				else
					$perms = false;
				
				// We must be either the project author or be a moderator in order to delete bugs
				// We must be either the project author or be a moderator or even the author of the bug to be able to edit it
				if ($perms || $bug['uid'] == $mybb->user['uid'])
				{
					// if we've got mod perms we can also delete
					if ($perms)
						$options = "<div style=\"float: right\"><a href=\"{$mybb->settings['bburl']}/mods.php?action=bugtracker&amp;sub=edit&amp;bid={$bug['bid']}&amp;pid={$bug['pid']}&amp;my_post_key={$mybb->post_code}\">{$lang->mods_edit}</a> - <a href=\"{$mybb->settings['bburl']}/mods.php?action=bugtracker&amp;sub=delete&amp;bid={$bug['bid']}&amp;pid={$bug['pid']}&amp;my_post_key={$mybb->post_code}\">{$lang->mods_delete}</a></div>";
					else
						$options = "<div style=\"float: right\"><a href=\"{$mybb->settings['bburl']}/mods.php?action=bugtracker&amp;sub=edit&amp;bid={$bug['bid']}&amp;pid={$bug['pid']}&amp;my_post_key={$mybb->post_code}\">{$lang->mods_edit}</a></div>";
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
				
				$query = $db->simple_select("mods_bugs", "COUNT(bid) as bugs", 'replyto=\''.$bid.'\'');
				$total_rows = $db->fetch_field($query, "bugs");
				
				// multi-page
				if ($total_rows > $per_page)
					$multipage = multipage($total_rows, $per_page, $mybb->input['page'], $mybb->settings['bburl']."/mods.php?action=bugtracker&amp;sub=view&amp;pid=".$pid."&amp;bid=".$bid);	
				
				// Get list of replies
				$query = $db->query("
					SELECT u.*, b.*
					FROM ".TABLE_PREFIX."mods_bugs b
					LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=b.uid)
					WHERE b.replyto='{$bid}'
					ORDER BY b.date DESC LIMIT {$start}, {$per_page}
				");
				
				$replies = '';
				
				while($reply = $db->fetch_array($query))
				{
					$reply['title'] = htmlspecialchars_uni($reply['name']);
					$reply['description'] = nl2br(htmlspecialchars_uni($reply['description']));
					$reply['author'] = build_profile_link(htmlspecialchars_uni($reply['username']), $reply['uid']);
					
					$reply['date'] = my_date($mybb->settings['dateformat'], $reply['date'], '', false).', '.my_date($mybb->settings['timeformat'], $reply['date']);
				
					// To delete replies we must be either the author of the project or a moderator
					// To edit replies we must be either the author of the project or a moderator or even the author of the reply
					if ($perms || $bug['uid'] == $mybb->user['uid'])
					{
						// if we've got mod perms we can also delete
						if ($perms)
							$reply['options'] = "<br /><a href=\"{$mybb->settings['bburl']}/mods.php?action=bugtracker&amp;sub=edit&amp;bid={$reply['bid']}&amp;pid={$reply['pid']}&amp;my_post_key={$mybb->post_code}\">{$lang->mods_edit}</a> - <a href=\"{$mybb->settings['bburl']}/mods.php?action=bugtracker&amp;sub=delete&amp;bid={$reply['bid']}&amp;pid={$reply['pid']}&amp;my_post_key={$mybb->post_code}\">{$lang->mods_delete}</a>";
						else
							$reply['options'] = "<br /><a href=\"{$mybb->settings['bburl']}/mods.php?action=bugtracker&amp;sub=edit&amp;bid={$reply['bid']}&amp;pid={$reply['pid']}&amp;my_post_key={$mybb->post_code}\">{$lang->mods_edit}</a>";
					}
					
					eval("\$replies .= \"".$templates->get("mods_bugtracker_reply")."\";");
				}
				
				if (empty($replies))
				{
					$colspan = 3;
					eval("\$replies = \"".$templates->get("mods_no_data")."\";");
				}
				
				// If we're the author, moderators or collaborators then we can update this bug
				if ($perms || in_array($mybb->user['uid'], $project['collaborators']))
				{
					$builds = '';
					
					if ($bug['builds'] != '' && $bug['builds'] != $lang->mods_all)
						$origaffected = explode(',', $bug['builds']);
					elseif ($bug['builds'] == $lang->mods_all)
						$origaffected = 'all';
					else
						$origaffected = array();
					
					if ($mybb->input['affected'] && is_array($mybb->input['affected']))
					{
						$affected = $mybb->input['affected'];
					}
					else {
						$affected = $origaffected;
					}
					
					if((!empty($affected) && is_array($affected) && in_array(0, $affected)) || (!is_array($affected) && $bug['builds'] == $lang->mods_all))
					{
						$selectall = "selected=\"SELECTED\"";
					}
					else {
						$selectall = '';
						$bug['builds'] = '';
					}
					
					$coma = '';
					
					// Get all builds of a certain project
					$projbuilds = $mods->projects->builds->getAll('pid=\''.$project['pid'].'\'');
					foreach ($projbuilds as $build)
					{
						if (!$selectall && is_array($affected) && in_array($build['bid'], $affected))
							$builds .= '<option value="'.$build['bid'].'" selected="selected">#'.$build['number'].' ('.$build['status'].')</option>';
						else
							$builds .= '<option value="'.$build['bid'].'">#'.$build['number'].' ('.$build['status'].')</option>';
							
						// Get the number of the builds for the bug details
						if (is_array($origaffected) && in_array($build['bid'], $origaffected))
						{
							$bug['builds'] .= $comma.'#'.$build['number'];
							$comma = ', ';
						}
						elseif ($origaffected == 'all')
							$bug['builds'] = $lang->mods_all;
					}
				
					// Priority dropdown
					if ($mybb->input['priority'])
					{
						$priority = (int)$mybb->input['priority'];
						
						$selected1['low'] = '';
						$selected1['medium'] = '';
						$selected1['high'] = '';
						
						switch ($priority)
						{
							case 1:
								$selected1['low'] = 'selected="SELECTED"';
							break;
							case 2:
								$selected1['medium'] = 'selected="SELECTED"';
							break;
							case 3:
								$selected1['high'] = 'selected="SELECTED"';
							break;
						}
					}
					else {
						$selected1['low'] = '';
						$selected1['medium'] = '';
						$selected1['high'] = '';
						
						switch ($bug['priority'])
						{
							case $lang->mods_low_priority:
								$selected1['low'] = 'selected="SELECTED"';
							break;
							case $lang->mods_medium_priority:

								$selected1['medium'] = 'selected="SELECTED"';
							break;
							case $lang->mods_high_priority:
								$selected1['high'] = 'selected="SELECTED"';
							break;
						}
					}
					
					// Merge collaborators and author
					$uids = array_merge(array($project['uid']), $project['collaborators']);
				
					$assignees = '';
					
					$selected3 = 'selected="SELECTED"';
					
					// Get list of possible assignees
					$query = $db->simple_select('users', 'username,uid', 'uid IN (\''.implode("','", $uids).'\')');
					while ($user = $db->fetch_array($query))
					{
						// Owner?
						if ($user['uid'] == $project['uid'])
							$user['username'] .= '*';
							
						if ($mybb->input['assignee'])
						{
							if ($user['uid'] == (int)$mybb->input['assignee'])
							{
								$selected = 'selected="SELECTED"';
								$selected3 = '';
							}
							else {
								$selected = '';	
							}
						}
						else {
							if ($user['uid'] == $bug['assignee'])
							{
								$selected = 'selected="SELECTED"';
								$selected3 = '';
							}
							else
								$selected = '';
						}
							
						$assignees .= '\n<option value="'.intval($user['uid']).'" '.$selected.'>'.htmlspecialchars_uni($user['username']).'</option>';
					}
					
					// Status
					if ($mybb->input['status'])
					{
						$status = (int)$mybb->input['status'];
						
						switch ($status)
						{
							case 1:
								$selected2['open'] = 'selected="SELECTED"';
							break;
							case 2:
								$selected2['closed'] = 'selected="SELECTED"';
							break;
							case 3:
								$selected2['rejected'] = 'selected="SELECTED"';
							break;
						}
					}
					else {
						$selected2['open'] = '';
						$selected2['closed'] = '';
						$selected2['rejected'] = '';
						
						switch ($bug['status'])
						{
							case $lang->mods_open:
								$selected2['open'] = 'selected="SELECTED"';
							break;
							case $lang->mods_closed:
								$selected2['closed'] = 'selected="SELECTED"';
							break;
							case $lang->mods_rejected:
								$selected2['rejected'] = 'selected="SELECTED"';
							break;
						}
					}
				
					eval("\$modoptions = \"".$templates->get("mods_bugtracker_modoptions")."\";");
				}
				else {
					// Since during the mod options code we also format the affected builds we need to do the same for those without mod perms
					
					if ($bug['builds'] != '' && $bug['builds'] != $lang->mods_all)
						$origaffected = explode(',', $bug['builds']);
					elseif ($bug['builds'] == $lang->mods_all)
						$origaffected = 'all';
					else
						$origaffected = array();
					
					$bug['builds'] = '';
					
					$comma = '';
						
					// Get all builds of a certain project
					$projbuilds = $mods->projects->builds->getAll('pid=\''.$project['pid'].'\'');
					foreach ($projbuilds as $build)
					{
						// Get the number of the builds for the bug details
						if (is_array($origaffected) && in_array($build['bid'], $origaffected))
						{
							$bug['builds'] .= $comma.'#'.$build['number'];
							$comma = ', ';
						}
						elseif ($origaffected == 'all')
							$bug['builds'] = $lang->mods_all;
					}
				}
				
				// Sanitise the input message for the reply (it should be filled in case of error in the updating process)
				$mybb->input['message'] = htmlspecialchars_uni($mybb->input['message']);
				
				// Get assignee if any
				if ($bug['assignee'])
				{
					$assignee = $db->fetch_field($db->simple_select('users', 'username', 'uid=\''.intval($bug['assignee']).'\''), 'username');
					$bug['assignee'] = build_profile_link(htmlspecialchars_uni($assignee), $bug['assignee']);
					
					// if the assignee name in the DB (used to speed up things in bug listings) differs from the actual one, we must update it
					if ($assignee != $bug['assignee_name'])
					{
						$db->update_query('mods_bugs', array('assignee_name' => $db->escape_string($assignee)), 'uid=\''.intval($bug['assignee']).'\'');
					}
				}
				else
					$bug['assignee'] = $lang->mods_none;
				
				// Primer
				$primer['title'] = $project['name']." - ".$lang->mods_bugtracker;
				$primer['content'] = $lang->mods_primer_submit_bug;
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
				$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=bugtracker&amp;pid=".$project['pid'].'">'.$lang->mods_bugtracker.'</a>';
				
				eval("\$navigation = \"".$templates->get("mods_nav")."\";");
				
				// Force Navigation here
				$mods->buildNavHighlight($cat['parent']);
				
				// Title
				$title .= ' - '.htmlspecialchars_uni($project['name']).' - '.$lang->mods_bugtracker.' - "'.$bug['title'].'"';
				
				eval("\$primerblock = \"".$templates->get("mods_primer")."\";");
				eval("\$content = \"".$templates->get("mods_bugtracker_view")."\";");
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
				$bid = (int)$mybb->input['bid'];
				
				// Does the bug or reply exist?
				$query = $db->simple_select("mods_bugs", "*", 'bid=\''.$bid.'\'');
				$message = $db->fetch_array($query);
				if (empty($message))
				{
					$mods->error($lang->mods_invalid_sid);
				}
				
				// Are we moderators or the author of the project? If not, error out.
				if ($project['uid'] != $mybb->user['uid'] && !$mods->check_permissions($mybb->settings['mods_mods']))
				{
					error_no_permission();
				}
				
				if ($message['replyto'] == 0) {
					$title .= ' - '.htmlspecialchars_uni($project['name']).' - '.$lang->mods_bugtracker.' - '.$lang->mods_delete_bug;
					$notice = $lang->mods_confirm_delete_bug;
				}
				else {
					$title .= ' - '.htmlspecialchars_uni($project['name']).' - '.$lang->mods_bugtracker.' - '.$lang->mods_delete_reply;
					$notice = $lang->mods_confirm_delete_reply;
				}

				$otherfields = '<input type="hidden" name="bid" value="'.$bid.'" />';
				$otherfields .= '<input type="hidden" name="pid" value="'.intval($project['pid']).'" />';
				$action = 'bugtracker&amp;sub=delete';
				
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
				$bid = (int)$mybb->input['bid'];
				
				// Does the bug or reply exist?
				$query = $db->simple_select("mods_bugs", "*", 'bid=\''.$bid.'\'');
				$message = $db->fetch_array($query);
				if (empty($message))
				{
					$mods->error($lang->mods_invalid_bug);
				}
				
				// Are we moderators or the author of the project or the author of the reply or bug? If not, error out.
				if ($project['uid'] != $mybb->user['uid'] && !$mods->check_permissions($mybb->settings['mods_mods']) && $message['uid'] != $mybb->user['uid'])
				{
					error_no_permission();
				}
				
				if ($message['replyto'] == 0) {
					$title .= ' - '.htmlspecialchars_uni($project['name']).' - '.$lang->mods_bugtracker.' - '.$lang->mods_edit_bug;
					$notice = $lang->mods_confirm_edit_bug;
				}
				else {
					$title .= ' - '.htmlspecialchars_uni($project['name']).' - '.$lang->mods_bugtracker.' - '.$lang->mods_edit_reply;
					$notice = $lang->mods_confirm_edit_reply_bug;
				}

				$otherfields = '<input type="hidden" name="bid" value="'.$bid.'" />';
				$otherfields .= '<input type="hidden" name="pid" value="'.intval($project['pid']).'" />';
				$otherfields .= '<div class="form_container">
					<fieldset>
						<dl>
						<dt><label for="message">Description:</label></dt>
						<dd><textarea cols="10" rows="10" name="message">'.htmlspecialchars_uni($message['description']).'</textarea></dd>
						</dl>
					</fieldset>
				</div>';
				$action = 'bugtracker&amp;sub=edit';
				
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
				
				// Merge collaborators and author
				if ($project['collaborators'] != '' && !is_array($project['collaborators']))
				{
					$project['collaborators'] = explode(',', $project['collaborators']);
				}
				else
					$project['collaborators'] = array();
			
				$uids = array_merge(array($project['uid']), $project['collaborators']);
				
				$where = '';
				$params = '';
				
				// browsing someone's bugs only only?
				if ((int)$mybb->input['assignee'] > 0)
				{
					if (!in_array($mybb->input['assignee'], $uids))
					{
						$errors[] = $lang->mods_bugtracker_invalid_assignee;
					}
					else {
						$where .= ' AND b.assignee=\''.intval($mybb->input['assignee']).'\'';
					
						$params .= '&amp;assignee='.intval($mybb->input['assignee']);
						
						$selected3 = '';
					}
				}
				else {
					$selected3 = 'selected="SELECTED"';
				}
				
				// posted by someone only?
				if ((int)$mybb->input['uid'] > 0)
				{
					$where .= ' AND b.uid=\''.intval($mybb->input['uid']).'\'';
					$params .= '&amp;uid='.intval($mybb->input['uid']);
				}
				
				// certain priority only?
				if ((int)$mybb->input['priority'] > 0)
				{
					$priority = (int)$mybb->input['priority'];
					if ($priority != 1 && $priority != 2 && $priority != 3)
					{
						$errors[] = $lang->mods_bugtracker_invalid_priority;
					}
					else {
						$selected1['low'] = '';
						$selected1['medium'] = '';
						$selected1['high'] = '';
						$selected1['all'] = '';
						
						switch ($priority)
						{
							case 1:
								$priority = 'low';
								$selected1['low'] = 'selected="SELECTED"';
							break;
							case 2:
								$priority = 'medium';
								$selected1['medium'] = 'selected="SELECTED"';
							break;
							case 3:
								$priority = 'high';
								$selected1['high'] = 'selected="SELECTED"';
							break;
						}
						
						$where .= ' AND b.priority=\''.$priority.'\'';
						$params .= '&amp;priority='.(int)$mybb->input['priority'];
					}
				}
				else {
					$selected1['low'] = '';
					$selected1['medium'] = '';
					$selected1['high'] = '';
					$selected1['all'] = 'selected="SELECTED"';
				}
				
				// certain status only?
				if ((int)$mybb->input['status'] > 0)
				{
					$status = (int)$mybb->input['status'];
					if ($status != 1 && $status != 2 && $status != 3)
					{
						$errors[] = $lang->mods_bugtracker_invalid_status;
					}
					else {
						$selected2['open'] = '';
						$selected2['closed'] = '';
						$selected2['rejected'] = '';
						$selected2['all'] = '';
						
						switch ($status)
						{
							case 1:
								$status = '0';
								$selected2['open'] = 'selected="SELECTED"';
							break;
							case 2:
								$status = '1';
								$selected2['closed'] = 'selected="SELECTED"';
							break;
							case 3:
								$status = '2';
								$selected2['rejected'] = 'selected="SELECTED"';
							break;
						}
						
						$where .= ' AND b.status=\''.$status.'\'';
						$params .= '&amp;status='.(int)$mybb->input['status'];
					}
				}
				else {
					$selected2['open'] = '';
					$selected2['closed'] = '';
					$selected2['rejected'] = '';
					$selected2['all'] = 'selected="SELECTED"';
				}
				
				$query = $db->simple_select("mods_bugs", "COUNT(bid) as bugs", str_replace("b.", "", "b.pid='{$pid}' AND b.replyto=0 {$where}"));
				$total_rows = $db->fetch_field($query, "bugs");
				
				// multi-page
				if ($total_rows > $per_page)
					$multipage = multipage($total_rows, $per_page, $mybb->input['page'], $mybb->settings['bburl']."/mods.php?action=bugtracker&amp;pid=".$pid.$params);	
			
				$bugs = '';
				
				// Get list of bugs
				$query = $db->query("
					SELECT u.*, b.*
					FROM ".TABLE_PREFIX."mods_bugs b
					LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=b.uid)
					WHERE b.pid='{$pid}' AND b.replyto=0 {$where}
					ORDER BY b.date DESC LIMIT {$start}, {$per_page}
				");
				while ($bug = $db->fetch_array($query))
				{
					$bug['title'] = htmlspecialchars_uni($bug['title']);
					
					// Status: 0 for open, 1 for closed, 2 for rejected
					if ($bug['status'] == '1')
					{
						$bug['title'] = '<del>'.htmlspecialchars_uni($bug['title']).'</del>';
						$bug['status'] = $lang->mods_closed;
					}
					elseif ($bug['status'] == '2')
					{
						$bug['status'] = $lang->mods_rejected;
					}
					elseif ($bug['status'] == '0')
					{
						$bug['status'] = $lang->mods_open;
					}
					
					$bug['author'] = build_profile_link(htmlspecialchars_uni($bug['username']), $bug['uid']);
					
					if ($bug['assignee'])
						$bug['assignee'] = build_profile_link(htmlspecialchars_uni($bug['assignee_name']), $bug['assignee']);
					else
						$bug['assignee'] = $lang->mods_none;
						
					$bug['date'] = my_date($mybb->settings['dateformat'], $bug['date'], '', false);
					
					switch ($bug['priority'])
					{
						case 'low':
							$bug['priority'] = "<img src=\"{$mybb->settings['bburl']}/assets/images/icons/low_priority.png\" title=\"{$lang->mods_low_priority}\" />";
						break;
						
						case 'medium':
							$bug['priority'] = "<img src=\"{$mybb->settings['bburl']}/assets/images/icons/medium_priority.png\" title=\"{$lang->mods_medium_priority}\" />";
						break;
						
						case 'high':
							$bug['priority'] = "<img src=\"{$mybb->settings['bburl']}/assets/images/icons/high_priority.png\" title=\"{$lang->mods_high_priority}\" />";
						break;
					}
					
					eval("\$bugs .= \"".$templates->get("mods_bugtracker_bug")."\";");
				}
				
				if (empty($bugs))
				{
					$colspan = 5;
					eval("\$bugs = \"".$templates->get("mods_no_data")."\";");
				}
				
				$assignees = '';
				
				// Get list of possible assignees
				$query = $db->simple_select('users', 'username,uid', 'uid IN (\''.implode("','", $uids).'\')');
				while ($user = $db->fetch_array($query))
				{
					// Owner?
					if ($user['uid'] == $project['uid'])
						$user['username'] .= '*';
						
					if ($user['uid'] == (int)$mybb->input['assignee'])
						$selected = 'selected="SELECTED"';
					else
						$selected = '';
						
					$assignees .= '\n<option value="'.intval($user['uid']).'" '.$selected.'>'.htmlspecialchars_uni($user['username']).'</option>';
				}
			
				// Primer
				$primer['title'] = $project['name']." - ".$lang->mods_bugtracker;
				$primer['content'] = $lang->mods_primer_bugtracker;
				$meta_description = $primer['content'];
				$primer['content'] = '<p>'.$primer['content'].'</p>';
				
				if (count($errors) > 0)
				{
					$inline_errors = inline_error($errors);
				}
				
				eval("\$primerblock = \"".$templates->get("mods_primer")."\";");
				eval("\$rightblock = \"".$templates->get("mods_bugtracker_rightblock")."\";");
				eval("\$content = \"".$templates->get("mods_bugtracker")."\";");
			}
		}
	}
}

?>