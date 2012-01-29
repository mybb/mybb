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

class Panel implements Modules
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
		
		$inline_errors = '';

		if ($mybb->input['panelaction'] == 'createproject')
		{
			// Let's make sure we're doing this via POST
			if ($mybb->request_method != 'post')
				$mods->error();
				
			$errors = array();
			$data = array();
			
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			$insert_array = array(
				'name' => trim_blank_chrs($mybb->input['name']),
				'codename' => trim_blank_chrs($mybb->input['codename']),
				'description' => trim_blank_chrs($mybb->input['description']),
				'information' => $mybb->input['information'],
				'versions' => implode(',', $mybb->input['versions']),
				'licence_name' => trim_blank_chrs($mybb->input['licence_name']),
				'licence' => $mybb->input['licence'],
				'uid' => (int)$mybb->user['uid'],
			);
			
			switch ($mybb->input['category'])
			{
				case 'plugins':
					$insert_array['cid'] = $mybb->input['pluginscid'];
				break;
				case 'themes':
					$insert_array['cid'] = $mybb->input['themescid'];
				break;
				case 'resources':
					$insert_array['cid'] = $mybb->input['resourcescid'];
				break;
				case 'graphics':
					$insert_array['cid'] = $mybb->input['graphicscid'];
				break;
				default:
					$errors[] = $lang->mods_create_project_missing_cat;
			}
			
			// Let's check if a project with the chosen codename already exists
			$projects = $mods->projects->getAll('codename=\''.$db->escape_string($insert_array['codename']).'\'');
			if (!empty($projects))
			{
				$errors[] = $lang->mods_create_project_codename_already;
			}
			
			$errors = array_merge($errors, $mods->projects->validate($insert_array));
			if(count($errors) > 0)
			{
				$inline_errors = inline_error($errors);
				
				$data['name'] = htmlspecialchars_uni($mybb->input['name']);
				$data['codename'] = htmlspecialchars_uni($mybb->input['codename']);
				$data['description'] = htmlspecialchars_uni($mybb->input['description']);
				$data['information'] = htmlspecialchars_uni($mybb->input['information']);
				$data['category'] = $mybb->input['category'];
				$data['versions'] = implode(',', $mybb->input['versions']);
				$data['cid'] = (int)$insert_array['cid'];
				$default_licence = htmlspecialchars_uni($mybb->input['licence']);
				$default_licence_name = htmlspecialchars_uni($mybb->input['licence_name']);
				
				$locationforjs = 'createproject';
			}
			else {
				// Try uploading the build now
				$filename = basename($_FILES['buildzip']['name']);
				if ($filename != '')
				{
					$ext = get_extension($_FILES['buildzip']['name']);
					if ($ext != "zip")
					{
						$errors[] = $lang->mods_not_zip;
					}
					else {
						// get first 4 bytes of the uploaded file
						$handle = fopen($_FILES['buildzip']['tmp_name'], "r");
						$contents = fread($handle, 4);
						fclose($handle);
						
						// check the first 4 bytes of the header to check if it's really a ZIP
						if (bin2hex($contents) != "504b0304")
						{
							$errors[] = $lang->mods_not_zip;
						}
						else {
						
							// remove bad characters from file name
							$illegal_characters = array(" ", "\\", "/", ":", "*", "?", "\"", "<", ">", "|");
							$_FILES['buildzip']['name'] = str_replace($illegal_characters, "_", $_FILES['buildzip']['name']);
							
							// we passed the first two checks for the zip extension/file format
							
							// versions
							$_FILES['buildzip']['versions'] = $insert_array['versions'];
							
							// uploading time!
							$build = $mods->projects->builds->upload($_FILES['buildzip']);
						
							// check for errors in the uploading process
							if ($build['error'])
							{
								$errors[] = $lang->mods_upload_problem."<br />".$build['error'];
							}
						
							// Unzipping time
							/*$zip = zip_open(MYBB_ROOT."mods/".$build['filename']);
							if (is_resource($zip)) {
								zip_close($zip);
							}
							else {
								// remove ZIP
								// ....
								
								// output error
								$errors[] = $lang->mods_not_zip;
							}*/
						}
					}
				}
				else 
				{
					$errors[] = $lang->mods_create_project_missing_build;
				}
				
				if (count($errors) > 0)
				{
					$inline_errors = inline_error($errors);
					
					$data['name'] = htmlspecialchars_uni($mybb->input['name']);
					$data['codename'] = htmlspecialchars_uni($mybb->input['codename']);
					$data['description'] = htmlspecialchars_uni($mybb->input['description']);
					$data['information'] = htmlspecialchars_uni($mybb->input['information']);
					$data['category'] = $mybb->input['category'];
					$data['versions'] = implode(',', $mybb->input['versions']);
					$data['cid'] = (int)$insert_array['cid'];
					$default_licence = htmlspecialchars_uni($mybb->input['licence']);
					$default_licence_name = htmlspecialchars_uni($mybb->input['licence_name']);
					
					$locationforjs = 'createproject';
				}
				else {
					$mybb->user['devstatus'] = $mods->getDevStatus($mybb->user);
					if ($mybb->user['devstatus'] == $lang->mods_regular_dev)
						$auto_approval = false;
					else
						$auto_approval = true;
						
					$insert_array['guid'] = $mods->projects->generateGUID($insert_array['codename']);
				
					// Finally create the project
					$pid = $mods->projects->create($insert_array, $auto_approval);
					
					// Update build's pid
					$mods->projects->builds->updateByID(array('pid' => $pid), $build['bid']);
					
					// Success message!
					if ($auto_approval)
						$inline_errors = '<div class="success">'.$lang->mods_project_created_already_approved.'</div>';
					else {
						$inline_errors = '<div class="success">'.$lang->mods_project_created_under_approval.'</div>';
						
						// Prepare message
						/*$message = $lang->sprintf($lang->mods_new_project_email, '<a href="'.$mybb->settings['bburl'].'/mods.php?action=view&amp;pid='.intval($project['pid']).'">'.htmlspecialchars_uni($project['name']).'</a>', build_profile_link(htmlspecialchars_uni($mybb->user['username']), $mybb->user['uid']));
						$subject = $lang->mods_new_project_email_subject;
						
						// Send email to Mods Site moderators, all of them!! SPAM THEIR ACCOUNTS MUAHAHAAHAHA
						my_mail($user['email'], $subject, $message, "", "", "", false, "html", "");*/
					}
				}
			}
			
			$action = 'panel';
			$mybb->input['panelaction'] = '';
		}
		elseif ($mybb->input['panelaction'] == 'setstable')
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
			
			// Are we the owner of the project?
			if ($mybb->user['uid'] != $project['uid'])
			{
				error_no_permission();
			}
			
			// Does the build exist?
			$bid = (int)$mybb->input['bid'];
			$build = $mods->projects->builds->getByID($bid,true,true);
			if (empty($build))
			{
				$mods->error($lang->mods_invalid_bid);
			}
			
			// Make sure it's not waiting stable change already
			if ($build['waitingstable'] || $build['status'] == 'stable')
			{
				$mods->error($lang->mods_already_stable);
			}
			
			if ($mods->getDevStatus($mybb->user) == $lang->mods_approved_dev)
			{
				$trusted = 1;
				$inline_errors = '<div class="success">'.$lang->mods_build_stable_approved.'</div>';
			}
			else {
				$trusted = 0;
				$inline_errors = '<div class="success">'.$lang->mods_build_stable_awaiting_approval.'</div>';
			}
			
			$mods->projects->builds->setStable($bid, $trusted);
			
			$action = 'panel';
			$mybb->input['panelaction'] = 'managebuilds';
		}
		elseif ($mybb->input['panelaction'] == 'accept' && $mybb->request_method == "post")
		{
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			$uid = (int)$mybb->user['uid'];
			$iid = (int)$mybb->input['iid'];
			
			// Verify invitation
			$query = $db->simple_select('mods_invitations', '*', 'touid=\''.$uid.'\' AND iid=\''.$iid.'\'');
			$invitation = $db->fetch_array($query);
			if (empty($invitation))
			{
				$mods->error($lang->mods_invalid_iid);
			}
			
			// Does the project exist? Must be approved, otherwise we can't join
			$pid = (int)$invitation['pid'];
			$project = $mods->projects->getByID($pid,true,true);
			if (empty($project))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			// Are we the owner of the project? If it can happen accidentally if someone changes the owner of the project before we accept the invitation.
			// If we are, then this invitation cannot be accepted - someone will report the error sooner or later.
			if ($mybb->user['uid'] == $project['uid'])
			{
				error_no_permission();
			}
			
			// It's not an error. But a success message
			$inline_errors = '<div class="success">'.$lang->mods_invitation_accepted.'</div>';
			
			// PM the owner
			$pm = array(
				'subject' 		=> $lang->mods_pm_invitation_accepted_subject,
				'message' 		=> $lang->sprintf($lang->mods_pm_invitation_accepted_message, $project['name']),
				'touid' 		=> $project['uid'],
				'receivepms'	 => 1, // force
			);
			$mods->send_pm($pm, $mybb->user['uid']);
			
			// Update the list of dev's of the project
			if (!empty($project['collaborators']))
				$project['collaborators'] .= ','.$uid;
			else
				$project['collaborators'] = $uid;
			
			$mods->projects->updateByID(array('collaborators' => $project['collaborators']), $project['pid']);
			
			// Delete the invitation now
			$db->delete_query('mods_invitations', 'iid=\''.$iid.'\'');
		
			$action = 'panel';
			$mybb->input['panelaction'] = 'invitations';
		}
		elseif ($mybb->input['panelaction'] == 'reject' && $mybb->request_method == "post")
		{
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			$uid = (int)$mybb->user['uid'];
			$iid = (int)$mybb->input['iid'];
			
			// Verify invitation
			$query = $db->simple_select('mods_invitations', '*', 'touid=\''.$uid.'\' AND iid=\''.$iid.'\'');
			$invitation = $db->fetch_array($query);
			if (empty($invitation))
			{
				$mods->error($lang->mods_invalid_iid);
			}
			
			// Does the project exist? Must be approved, otherwise we can't join
			$pid = (int)$invitation['pid'];
			$project = $mods->projects->getByID($pid,true,true);
			if (empty($project))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			// It's not an error. But a success message
			$inline_errors = '<div class="success">'.$lang->mods_invitation_rejected.'</div>';
			
			// PM the owner
			$pm = array(
				'subject' 		=> $lang->mods_pm_invitation_rejected_subject,
				'message' 		=> $lang->sprintf($lang->mods_pm_invitation_rejected_message, $project['name']),
				'touid' 		=> $project['uid'],
				'receivepms'	 => 1, // force
			);
			$mods->send_pm($pm, $mybb->user['uid']);
			
			// Delete the invitation now
			$db->delete_query('mods_invitations', 'iid=\''.$iid.'\'');
		
			$action = 'panel';
			$mybb->input['panelaction'] = 'invitations';
		}
		elseif ($mybb->input['panelaction'] == 'cancel' && $mybb->request_method == "post")
		{
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			$uid = (int)$mybb->user['uid'];
			$iid = (int)$mybb->input['iid'];
			
			// Verify invitation (must have been sent from us)
			$query = $db->simple_select('mods_invitations', '*', 'fromuid=\''.$uid.'\' AND iid=\''.$iid.'\'');
			$invitation = $db->fetch_array($query);
			if (empty($invitation))
			{
				$mods->error($lang->mods_invalid_iid);
			}
			
			// Does the project exist? Must be approved, otherwise we can't join
			$pid = (int)$invitation['pid'];
			$project = $mods->projects->getByID($pid,true,true);
			if (empty($project))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			if ($mybb->user['uid'] != $project['uid'])
			{
				error_no_permission();
			}
			
			// It's not an error. But a success message
			$inline_errors = '<div class="success">'.$lang->mods_invitation_cancelled.'</div>';
			
			// PM the owner
			$pm = array(
				'subject' 		=> $lang->mods_pm_invitation_cancelled_subject,
				'message' 		=> $lang->sprintf($lang->mods_pm_invitation_cancelled_message, $project['name']),
				'touid' 		=> $invitation['touid'],
				'receivepms'	 => 1, // force
			);
			$mods->send_pm($pm, $mybb->user['uid']);
			
			// Delete the invitation now
			$db->delete_query('mods_invitations', 'iid=\''.$iid.'\'');
		
			$action = 'panel';
			$mybb->input['panelaction'] = 'invitations';
		}
		elseif ($mybb->input['panelaction'] == 'invite' && $mybb->request_method == "post")
		{
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			$uid = (int)$mybb->user['uid'];
			
			// Does the project exist? Must be approved, otherwise we can't join
			$pid = (int)$mybb->input['project'];
			$project = $mods->projects->getByID($pid,true,true);
			if (empty($project))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			// Verify that we are the owner of the project
			if ($mybb->user['uid'] != $project['uid'])
			{
				error_no_permission();
			}
			
			// Verify that the user exists
			$q = $db->simple_select('users', 'uid', 'username=\''.trim_blank_chrs($mybb->input['username']).'\'');
			$user = (int)$db->fetch_field($q, 'uid');
			if ($user <= 0)
			{
				$mods->error($lang->mods_invalid_uid);
			}
			
			// Make sure we haven't sent an invitation already
			$query = $db->simple_select('mods_invitations', '*', 'touid=\''.$uid.'\' AND pid=\''.$pid.'\'');
			$invitation = $db->fetch_array($query);
			if (!empty($invitation))
			{
				$mods->error($lang->mods_invite_already_sent);
			}
			
			// PM the user
			$pm = array(
				'subject' 		=> $lang->mods_pm_invitation_received_subject,
				'message' 		=> $lang->sprintf($lang->mods_pm_invitation_received_message, $project['name']),
				'touid' 		=> $user,
				'receivepms'	 => 1, // force
			);
			$mods->send_pm($pm, $mybb->user['uid']);
			
			// Create the invitation now
			$db->insert_query('mods_invitations', array('pid' => (int)$project['pid'], 'touid' => (int)$user, 'fromuid' => $uid, 'date' => TIME_NOW));
			
			// It's not an error. But a success message
			$inline_errors = '<div class="success">'.$lang->mods_invitation_sent.'</div>';
		
			$action = 'panel';
			$mybb->input['panelaction'] = 'invitations';
		}
		elseif ($mybb->input['panelaction'] == 'remove' && $mybb->request_method == "post")
		{
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			$uid = (int)$mybb->input['uid'];
			
			// Does the project exist? Must be approved, otherwise we can't remove this user from it
			$pid = (int)$mybb->input['pid'];
			$project = $mods->projects->getByID($pid,true,true);
			if (empty($project))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			// Verify that we are the owner of the project
			if ($mybb->user['uid'] != $project['uid'])
			{
				error_no_permission();
			}
			
			// Verify that the user exists
			$q = $db->simple_select('users', 'uid', 'uid=\''.$uid.'\'');
			$user = (int)$db->fetch_field($q, 'uid');
			if ($user <= 0)
			{
				$mods->error($lang->mods_invalid_uid);
			}
			
			// Is the user collaborator of the project?
			if (!in_array($uid, explode(',', $project['collaborators'])))
			{
				// error!
				$mods->error($lang->mods_invalid_uid);
			}
			
			// Remove user
			$collabs = explode(',', $project['collaborators']);
			foreach ($collabs as $key => $collaborator)
			{
				if ($collaborator == $uid)
				{
					unset($collabs[$key]);
					break;
				}
			}
			
			$project['collaborators'] = implode(',', $collabs);
			
			// PM the user
			$pm = array(
				'subject' 		=> $lang->mods_pm_removed_subject,
				'message' 		=> $lang->sprintf($lang->mods_pm_removed_message, $project['name']),
				'touid' 		=> $user,
				'receivepms'	 => 1, // force
			);
			$mods->send_pm($pm, $mybb->user['uid']);
			
			$mods->projects->updateByID(array('collaborators' => $project['collaborators']), $project['pid']);
			
			// It's not an error. But a success message
			$inline_errors = '<div class="success">'.$lang->mods_user_removed.'</div>';
		
			$action = 'panel';
			$mybb->input['panelaction'] = 'managecollaborators';
		}
		elseif ($mybb->input['panelaction'] == 'leave' && $mybb->request_method == "post")
		{
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			$uid = (int)$mybb->user['uid'];
			
			// Does the project exist? Must be approved, otherwise we can't remove this user from it
			$pid = (int)$mybb->input['pid'];
			$project = $mods->projects->getByID($pid,true,true);
			if (empty($project))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			// Are we the owner of the project? If so, we can't leave the project.
			if ($mybb->user['uid'] == $project['uid'])
			{
				error_no_permission();
			}
			
			// Are we collaborator of the project»
			if (!in_array($uid, explode(',', $project['collaborators'])))
			{
				// error!
				$mods->error($lang->mods_invalid_uid);
			}
			
			// PM the user
			$pm = array(
				'subject' 		=> $lang->mods_pm_left_subject,
				'message' 		=> $lang->sprintf($lang->mods_pm_left_message, $project['name']),
				'touid' 		=> intval($project['uid']),
				'receivepms'	 => 1, // force
			);
			$mods->send_pm($pm, $mybb->user['uid']);
			
			// Remove user
			$collabs = explode(',', $project['collaborators']);
			foreach ($collabs as $key => $collaborator)
			{
				if ($collaborator == $uid)
				{
					unset($collabs[$key]);
					break;
				}
			}
			
			$project['collaborators'] = implode(',', $collabs);
			
			$mods->projects->updateByID(array('collaborators' => $project['collaborators']), $project['pid']);
			
			// It's not an error. But a success message
			$inline_errors = '<div class="success">'.$lang->mods_user_left.'</div>';
		
			$action = 'panel';
			$mybb->input['panelaction'] = '';
		}
		elseif ($mybb->input['panelaction'] == 'deletepreview' && $mybb->request_method == "post")
		{
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			// Is it a valid preview?
			$pid = (int)$mybb->input['previewid'];
			$preview = $mods->projects->previews->getByID($pid, true);
			if (empty($preview))
			{
				$mods->error($lang->mods_invalid_preview);
			}
			
			// Does the project exist? Must be approved in order to be viewed
			$project = $mods->projects->getByID($preview['project'],true);
			if (empty($project))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			// If the project hasn't been approved and we're not moderators we can't view the project
			if ($project['approved'] != 1 && !$mods->check_permissions($mybb->settings['mods_mods']))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			// If we're a developer or project owner we can manage previews
			if (!empty($project['collaborators']))
				$project['collaborators'] = explode(',', $project['collaborators']);
			else
				$project['collaborators'] = array();

			if ($project['uid'] != $mybb->user['uid'] && !in_array($mybb->user['uid'], $project['collaborators']))
			{
				error_no_permission();
			}
			
			$mods->projects->previews->deleteByID($pid);
			
			@unlink(MYBB_ROOT."uploads/mods/previews/".$preview['filename']);
			@unlink(MYBB_ROOT."uploads/mods/previews/".$preview['thumbnail']);
			
			// It's not an error. But a success message
			$inline_errors = '<div class="success">'.$lang->mods_preview_deleted.'</div>';
		
			$action = 'panel';
			$mybb->input['panelaction'] = 'managepreviews';
		}
	}
	
	public function run()
	{
		global $mybb, $db, $lang, $mods, $primerblock, $rightblock, $content, $title, $theme, $templates, $navigation, $inline_errors;
		
		// we do not check if we're a guest here because it's done at the top of this file.
	
		$title .= ' - '.$lang->mods_panel;
		
		// Licence
		$default_licence = $lang->mods_default_licence;
		$default_licence_name = $lang->mods_default_licence_name;
		
		if ($mybb->input['panelaction'] == 'edit')
		{
			// Does the project exist? Must be approved in order to be edited
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
			
			// Are we moderators or the author of the project? 
			if ($project['uid'] != $mybb->user['uid'] && !$mods->check_permissions($mybb->settings['mods_mods']))
			{
				error_no_permission();
			}
			
			// Get category so we can build the breadcrumb and update the counters if needed
			$cat = $mods->categories->getByID($project['cid']);
		
			$project['name'] = htmlspecialchars_uni($project['name']);
			
			// Prepare data to be output
			$data = array();
			$data['name'] = $project['name'];
			$data['paypal'] = $project['paypal_email'];
			$data['description'] = htmlspecialchars_uni($project['description']);
			$data['information'] = htmlspecialchars_uni($project['information']);
			$data['versions'] = $project['versions'];
			$default_licence = htmlspecialchars_uni($project['licence']);
			$default_licence_name = htmlspecialchars_uni($project['licence_name']);
			
			if ($project['bugtracking'] == '1')
				$data['bugtracking'] = 'checked="checked"';
			else
				$data['bugtracking'] = '';
				
			if ($project['bugtracking_collabs'] == '1')
				$data['bugtracking_collabs'] = 'checked="checked"';
			else
				$data['bugtracking_collabs'] = '';
				
			if ($project['hidden'] == '1')
				$data['hidden'] = 'checked="checked"';
			else
				$data['hidden'] = '';
			
			if ($project['suggestions'] == '1')
				$data['suggestions'] = 'checked="checked"';
			else
				$data['suggestions'] = '';
				
			$data['notifications']['1'] = '';
			$data['notifications']['2'] = '';
			$data['notifications']['3'] = '';
				
			switch ($project['notifications'])
			{
				case 1:
					$data['notifications']['1'] = 'checked="checked"';
				break;
				case 2:
					$data['notifications']['2'] = 'checked="checked"';
				break;
				case 3:
					$data['notifications']['3'] = 'checked="checked"';
				break;
				default:
					$data['notifications']['3'] = 'checked="checked"';
			}
				
			// Escape everything
			$project['testers'] = array_map('intval', explode(',', $project['testers']));
			$project['testers'] = implode('\',\'', $project['testers']);
			
			$data['bugtracker_link'] = htmlspecialchars_uni($project['bugtracker_link']);
			$data['support_link'] = htmlspecialchars_uni($project['support_link']);
			
			$users = '';
			
			// Build list of usernames
			$comma = '';
			$q = $db->simple_select('users', 'username', 'uid IN (\''.$project['testers'].'\')');
			while ($user = $db->fetch_array($q))
			{
				$users .= $comma.htmlspecialchars_uni($user['username']);
				$comma = ', ';
			}
			
			$data['testers'] = $users;
			
			$inline_errors = '';
			
			// Are we updating it already?
			if ($mybb->request_method == "post")
			{
				// Correct post key?
				verify_post_check($mybb->input['my_post_key']);
				
				$errors = array();
				$data = array();
				
				// Get testers user names
				if (trim_blank_chrs($mybb->input['testers']) != '')
				{
					$testers = explode(',', $mybb->input['testers']);
					
					// Escape everything
					$testers = array_map('trim_blank_chrs', $testers);
					$testers = array_map(array($db, 'escape_string'), $testers);
					$testers = implode('\',\'', $testers);
					
					$users = '';
					
					// Build list of UIDs
					$comma = '';
					$q = $db->simple_select('users', 'uid', 'username IN (\''.$testers.'\')');
					while ($user = $db->fetch_array($q))
					{
						$users .= $comma.$user['uid'];
						$comma = ',';
					}
				}
				
				$update_array = array(
					'name' => trim_blank_chrs($mybb->input['name']),
					'codename' => trim_blank_chrs($project['codename']),
					'description' => trim_blank_chrs($mybb->input['description']),
					'information' => $mybb->input['information'],
					'versions' => (is_array($mybb->input['versions']) ? implode(',', $mybb->input['versions']) : ''),
					'licence_name' => trim_blank_chrs($mybb->input['licence_name']),
					'licence' => $mybb->input['licence'],
					'cid' => (int)$project['cid'],
					'bugtracking' => (int)$mybb->input['bugtracking'],
					'bugtracking_collabs' => (int)$mybb->input['bugtracking_collabs'],
					'hidden' => (int)$mybb->input['hidden'],
					'bugtracker_link' => $mybb->input['bugtracker_link'],
					'support_link' => $mybb->input['support_link'],
					'suggestions' => (int)$mybb->input['suggestions'],
					'notifications' => (int)$mybb->input['notifications'],
					'testers' => $users,
					'paypal_email' => trim_blank_chrs($mybb->input['paypal']),
				);
				
				$errors = array_merge($errors, $mods->projects->validate($update_array));
				if(count($errors) > 0)
				{
					$inline_errors = inline_error($errors);
					
					$data['name'] = htmlspecialchars_uni($mybb->input['name']);
					$data['paypal'] = htmlspecialchars_uni($mybb->input['paypal']);
					$data['description'] = htmlspecialchars_uni($mybb->input['description']);
					$data['information'] = htmlspecialchars_uni($mybb->input['information']);
					if (is_array($mybb->input['versions']))
						$data['versions'] = implode(',', $mybb->input['versions']);
					else
						$data['versions'] = '';
					
					if ($mybb->input['bugtracking'] == 1)
						$data['bugtracking'] = 'checked="checked"';
					else
						$data['bugtracking'] = '';
						
					if ($mybb->input['bugtracking_collabs'] == 1)
						$data['bugtracking_collabs'] = 'checked="checked"';
					else
						$data['bugtracking_collabs'] = '';
						
					if ($mybb->input['hidden'] == 1)
						$data['hidden'] = 'checked="checked"';
					else
						$data['hidden'] = '';
						
					if ($mybb->input['suggestions'] == 1)
						$data['suggestions'] = 'checked="checked"';
					else
						$data['suggestions'] = '';
						
					$data['notifications']['1'] = '';
					$data['notifications']['2'] = '';
					$data['notifications']['3'] = '';
						
					switch ($mybb->input['notifications'])
					{
						case 1:
							$data['notifications']['1'] = 'checked="checked"';
						break;
						case 2:
							$data['notifications']['2'] = 'checked="checked"';
						break;
						case 3:
							$data['notifications']['3'] = 'checked="checked"';
						break;
						default:
							$data['notifications']['3'] = 'checked="checked"';
					}
					
					$data['testers'] = htmlspecialchars_uni($mybb->input['testers']);
					$data['bugtracker_link'] = htmlspecialchars_uni($mybb->input['bugtracker_link']);
					$data['support_link'] = htmlspecialchars_uni($mybb->input['support_link']);
					
					$default_licence = htmlspecialchars_uni($mybb->input['licence']);
					$default_licence_name = htmlspecialchars_uni($mybb->input['licence_name']);
				}
				else {
					// Finally update the project
					$pid = $mods->projects->updateByID($update_array, $project['pid']);
					
					// Now let's check if we have changed the "visible" status
					if ($update_array['hidden'] != $project['hidden'])
					{
						// If we changed it to visible, we need to update the counter by adding +1
						if ($update_array['hidden'] == 0)
						{
							$db->update_query('mods_categories', array('counter' => ++$cat['counter']), 'cid=\''.intval($cat['cid']).'\'');
						}
						else // we changed it do hidden
						{
							$db->update_query('mods_categories', array('counter' => --$cat['counter']), 'cid=\''.intval($cat['cid']).'\'');
						}
					}
					
					$data['name'] = htmlspecialchars_uni($update_array['name']);
					$data['paypal'] = htmlspecialchars_uni($update_array['paypal']);
					$data['description'] = htmlspecialchars_uni($update_array['description']);
					$data['information'] = htmlspecialchars_uni($update_array['information']);
					$data['versions'] = $update_array['versions'];
					
					if ($mybb->input['bugtracking'] == 1)
						$data['bugtracking'] = 'checked="checked"';
					else
						$data['bugtracking'] = '';
						
					if ($mybb->input['bugtracking_collabs'] == 1)
						$data['bugtracking_collabs'] = 'checked="checked"';
					else
						$data['bugtracking_collabs'] = '';
						
					if ($mybb->input['hidden'] == 1)
						$data['hidden'] = 'checked="checked"';
					else
						$data['hidden'] = '';
						
					if ($mybb->input['suggestions'] == 1)
						$data['suggestions'] = 'checked="checked"';
					else
						$data['suggestions'] = '';
					
					$data['notifications']['1'] = '';
					$data['notifications']['2'] = '';
					$data['notifications']['3'] = '';
						
					switch ($mybb->input['notifications'])
					{
						case 1:
							$data['notifications']['1'] = 'checked="checked"';
						break;
						case 2:
							$data['notifications']['2'] = 'checked="checked"';
						break;
						case 3:
							$data['notifications']['3'] = 'checked="checked"';
						break;
						default:
							$data['notifications']['3'] = 'checked="checked"';
					}
					
					$users = '';
					
					// Build list of usernames
					$comma = '';
					$q = $db->simple_select('users', 'username', 'uid IN (\''.str_replace(",", "','", $update_array['testers']).'\')');
					while ($user = $db->fetch_array($q))
					{
						$users .= $comma.htmlspecialchars_uni($user['username']);
						$comma = ', ';
					}
					
					$data['testers'] = $users;
					
					$data['bugtracker_link'] = htmlspecialchars_uni($mybb->input['bugtracker_link']);
					$data['support_link'] = htmlspecialchars_uni($mybb->input['support_link']);

					$default_licence = htmlspecialchars_uni($mybb->input['licence']);
					$default_licence_name = htmlspecialchars_uni($mybb->input['licence_name']);
					
					// Success message!
					$inline_errors = '<div class="success">'.$lang->mods_project_updated.'</div>';
				}
			}
			
			// Build versions select box
			$versions = '';
			$vs = explode(',', MYBB_VERSIONS);
			rsort($vs);
			
			$selvs = explode(',', $data['versions']);
			
			foreach ($vs as $version)
			{
				if (in_array($version, $selvs))
					$versions .= '<option value="'.$version.'" selected="selected">'.substr($version, 0, 1).'.'.substr($version, 1, 1).'.'.substr($version, 2).'</option>';
				else
					$versions .= '<option value="'.$version.'">'.substr($version, 0, 1).'.'.substr($version, 1, 1).'.'.substr($version, 2).'</option>';
			}
			
			// Title
			$title .= ' - '.$lang->mods_edit_project.' - '.$data['name'];
			
			// Primer
			$primer['title'] = $lang->mods_primer_editproject_title;
			$primer['content'] = $lang->mods_primer_editproject_content;
			$meta_description = $primer['content'];
			$primer['content'] = '<p>'.$primer['content'].'</p>';
			
			// Navigation
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
			$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=panel&amp;panelaction=edit&amp;pid=".$project['pid'].'">'.$lang->mods_editing.htmlspecialchars_uni($project['name']).'</a>';
			
			eval("\$navigation = \"".$templates->get("mods_nav")."\";");
			
			// Force Navigation here
			$mods->buildNavHighlight($cat['parent']);
			
			eval("\$primerblock = \"".$templates->get("mods_primer")."\";");
			eval("\$content = \"".$templates->get("mods_project_edit")."\";");
			
			// we do this here because we do not want to run the lines below these 3
			eval("\$page = \"".$templates->get("mods")."\";");
			output_page($page);
			exit;
		}
		elseif ($mybb->input['panelaction'] == 'managebuilds')
		{
			// Does the project exist? Must be approved in order to be managed
			$pid = (int)$mybb->input['pid'];
			$project = $mods->projects->getByID($pid,true,true);
			if (empty($project))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			// If we're a developer or project owner we can manage builds
			if (!empty($project['collaborators']))
				$project['collaborators'] = explode(',', $project['collaborators']);
			else
				$project['collaborators'] = array();

			if ($project['uid'] != $mybb->user['uid'] && !in_array($mybb->user['uid'], $project['collaborators']))
			{
				error_no_permission();
			}
			
			// Let's make sure we're doing this via POST
			if ($mybb->request_method == 'post' && $mybb->input['submit'])
			{	
				$errors = array();
				$data = array();
				
				// Correct post key?
				verify_post_check($mybb->input['my_post_key']);
				
				// Let's check if we exceeded the post_max_size php ini directive
				if (empty($_FILES) && empty($_POST) && isset($_SERVER['REQUEST_METHOD']) && strtolower($_SERVER['REQUEST_METHOD']) == 'post') { 
					error($lang->sprintf($lang->mods_exceeded, ini_get('post_max_size')));
				} 
				
				// From now on we force a change log.
				if (!isset($mybb->input['changelog']) || my_strlen(trim_blank_chrs($mybb->input['changelog'])) == 0)
				{
					$errors[] = $lang->mods_empty_changelog;
				}
				
				// Show errors if any
				if(count($errors) > 0)
				{
					$inline_errors = inline_error($errors);
				}
				else
				{
					// Try uploading the build now
					$filename = basename($_FILES['buildzip']['name']);
					if ($filename != '' && $_FILES['buildzip']['tmp_name'] != '')
					{
						$ext = get_extension($_FILES['buildzip']['name']);
						if ($ext != "zip")
						{
							$errors[] = $lang->mods_not_zip;
						}
						else {
							// get first 4 bytes of the uploaded file
							$handle = fopen($_FILES['buildzip']['tmp_name'], "r");
							$contents = fread($handle, 4);
							fclose($handle);
							
							// check the first 4 bytes of the header to check if it's really a ZIP
							if (bin2hex($contents) != "504b0304")
							{
								$errors[] = $lang->mods_not_zip;
							}
							else {
							
								// remove bad characters from file name
								$illegal_characters = array(" ", "\\", "/", ":", "*", "?", "\"", "<", ">", "|");
								$_FILES['buildzip']['name'] = str_replace($illegal_characters, "_", $_FILES['buildzip']['name']);
								
								// we passed the first two checks for the zip extension/file format
								
								// set the pid
								$_FILES['buildzip']['pid'] = $project['pid'];
								$_FILES['buildzip']['versions'] = $project['versions'];
								$_FILES['buildzip']['changes'] = $mybb->input['changelog'];
								
								// uploading time!
								$build = $mods->projects->builds->upload($_FILES['buildzip']);
							
								// check for errors in the uploading process
								if ($build['error'])
								{
									$errors[] = $lang->mods_upload_problem."<br />".$build['error'];
								}
							
								// Unzipping time
								/*$zip = zip_open(MYBB_ROOT."mods/".$build['filename']);
								if (is_resource($zip)) {
									zip_close($zip);
								}
								else {
									// remove ZIP
									// ....
									
									// output error
									$errors[] = $lang->mods_not_zip;
								}*/
							}
						}
						
						if (count($errors) == 0)
						{
							// Success message
							$inline_errors = '<div class="success">'.$lang->mods_build_submitted.'</div>';
						}
						else {
							$inline_errors = inline_error($errors);
							$changes = htmlspecialchars_uni($mybb->input['changelog']);
						}
					}
					else 
					{
						$changes = htmlspecialchars_uni($mybb->input['changelog']);
						$errors[] = $lang->mods_newbuild_invalid_build;
						$inline_errors = inline_error($errors);
					}
				}
			}
			
			$project['name'] = htmlspecialchars_uni($project['name']);
			
			$bid = (int)$mybb->input['bid'];
			$selected = array();
			
			// Get all builds
			$builds = '';
			$query = $db->simple_select('mods_builds', '*', 'pid=\''.$pid.'\'', array('order_by' => 'dateuploaded', 'order_dir' => 'desc'));
			while ($build = $db->fetch_array($query))
			{
				$build['number'] = intval($build['number']);
				$build['status'] = htmlspecialchars_uni($build['status']);
				
				$builds .= '<option value="'.intval($build['bid']).'">#'.$build['number'].' ('.$build['status'].')</option>';
				
				if ($bid == $build['bid'] || (empty($selected) && $bid == 0))
				{
					$selected = $build;
					
					if ($selected['status'] == 'dev')
						$selected['status'] = $lang->mods_development;
					else
						$selected['status'] = $lang->mods_stable;
						
					$selected['name'] = htmlspecialchars_uni($selected['name']);
					if ($selected['name'] == '')
					{
						$selected['name'] = $lang->mods_not_set;
					}
					
					$selected['dateuploaded'] = my_date($mybb->settings['dateformat'], $selected['dateuploaded'], '', false).', '.my_date($mybb->settings['timeformat'], $selected['dateuploaded']);
					$selected['filesize'] = get_friendly_size($selected['filesize']);
				}
			}
			
			$build = $selected;
			
			// MD5
			$build['md5'] = htmlspecialchars_uni($build['md5']);
			
			// Versions
			$vs = explode(',', $build['versions']);
			$build['versions'] = '';
			$comma = '';
			foreach ($vs as $version)
			{
				if (!empty($build['versions']))
					$comma = ', ';
				$build['versions'] .= $comma.htmlspecialchars_uni(substr($version, 0, 1).'.'.substr($version, 1, 1).'.'.substr($version, 2));
			}

			// Get author 
			$query = $db->simple_select('users', 'username', 'uid=\''.intval($build['uid']).'\'');
			$build['uploadedby'] = $db->fetch_field($query, 'username');

			/*if ($project['uid'] == $mybb->user['uid'] || in_array($mybb->user['uid'], $project['collaborators']))
			{*/
				eval("\$newbuild = \"".$templates->get("mods_managebuilds_new")."\";");
			//}
			
			// Mark as Stable link
			if ($build['status'] == $lang->mods_development && $build['waitingstable'] == 0)
			{
				$setstable = ' (<a href="'.$mybb->settings['bburl'].'/mods.php?action=panel&amp;panelaction=setstable&amp;bid='.intval($build['bid']).'&amp;pid='.intval($build['pid']).'&amp;my_post_key='.$mybb->post_code.'">'.$lang->mods_set_stable.'</a>)';
			}
			
			if ($build['waitingstable'] == 1)
			{
				$setstable = ' ('.$lang->mods_waiting_stable_approval.')';
			}
			
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
			$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=panel&amp;panelaction=managebuilds&amp;pid=".$project['pid'].'">'.$lang->mods_builds.'</a>';
			
			eval("\$navigation = \"".$templates->get("mods_nav")."\";");
			
			// Force Navigation here
			$mods->buildNavHighlight($cat['parent']);
			
			// Title
			$title .= ' - '.$lang->mods_manage_builds.' - '.htmlspecialchars_uni($project['name']);
			
			eval("\$content = \"".$templates->get("mods_manage_builds")."\";");
		}
		elseif ($mybb->input['panelaction'] == 'managecollaborators')
		{
			// Does the project exist? Must be approved in order to be viewed
			$pid = (int)$mybb->input['pid'];
			$project = $mods->projects->getByID($pid,true,true);
			if (empty($project))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			// We must be owner of this project
			if ($project['uid'] != $mybb->user['uid'])
			{
				error_no_permission();
			}
			
			$collaborators = '';
			
			if (!$project['collaborators'])
				$project['collaborators'] = array();
			else
				$project['collaborators'] = explode(',', $project['collaborators']);
			
			// Get collaborators' names
			$query = $db->simple_select('users', 'username,uid', 'uid IN (\''.implode('\',\'', array_map('intval', $project['collaborators'])).'\')');
			while ($user = $db->fetch_array($query))
			{
				$user['uid'] = (int)$user['uid'];
				
				$user['username'] = build_profile_link(htmlspecialchars_uni($user['username']), $user['uid']);
				
				eval("\$collaborators .= \"".$templates->get("mods_manage_collaborators_collaborator")."\";");
			}
			
			if (empty($collaborators))
			{
				$colspan = 2;
				eval("\$collaborators = \"".$templates->get("mods_no_data")."\";");
			}
			
			$project['name'] = htmlspecialchars_uni($project['name']);
			
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
			$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=builds&amp;pid=".$project['pid'].'">'.$lang->mods_builds.'</a>';
			
			eval("\$navigation = \"".$templates->get("mods_nav")."\";");
			
			// Force Navigation here
			$mods->buildNavHighlight($cat['parent']);
			
			// Title
			$title .= ' - '.$lang->mods_manage_collaborators.' - '.htmlspecialchars_uni($project['name']);
			
			eval("\$content = \"".$templates->get("mods_manage_collaborators")."\";");
		}
		elseif ($mybb->input['panelaction'] == 'managepreviews')
		{
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
			
			// If we're a developer or project owner we can manage previews
			if (!empty($project['collaborators']))
				$project['collaborators'] = explode(',', $project['collaborators']);
			else
				$project['collaborators'] = array();

			if ($project['uid'] != $mybb->user['uid'] && !in_array($mybb->user['uid'], $project['collaborators']))
			{
				error_no_permission();
			}
			
			// Get all previews of this project
			$previews = $mods->projects->previews->getAll('project=\''.intval($project['pid']).'\'');
			
			// Let's make sure we're doing this via POST
			if ($mybb->request_method == 'post' && $mybb->input['submit'])
			{	
				$errors = array();
				$data = array();
				
				// Correct post key?
				verify_post_check($mybb->input['my_post_key']);
				
				// Let's check if we exceeded the post_max_size php ini directive
				if (empty($_FILES) && empty($_POST) && isset($_SERVER['REQUEST_METHOD']) && strtolower($_SERVER['REQUEST_METHOD']) == 'post') { 
					error($lang->sprintf($lang->mods_exceeded, ini_get('post_max_size')));
				}
				
				if (count($previews) >= 6)
					$errors[] = $lang->mods_previews_limit;
				
				if (empty($errors))
				{
					// Try uploading the build now
					$filename = basename($_FILES['preview']['name']);
					if ($filename != '' && $_FILES['preview']['tmp_name'] != '')
					{
						$ext = get_extension($_FILES['preview']['name']);
						if ($ext != "jpeg" && $ext != "png")
						{
							$errors[] = $lang->mods_not_valid_image;
						}
						else {
							// get first 8 bytes of the uploaded file
							$handle = fopen($_FILES['preview']['tmp_name'], "r");
							$png = fread($handle, 8);
							fclose($handle);
							
							// get first 4 bytes of the uploaded file
							$handle = fopen($_FILES['preview']['tmp_name'], "r");
							$jpeg = fread($handle, 4);
							fclose($handle);
							
							// check the first 4 bytes of the header to check if it's really a PNG
							if (bin2hex($png) != "89504e470d0a1a0a" && bin2hex($jpeg) != "ffd8")
							{
								$errors[] = $lang->mods_not_valid_image;
							}
							else {
							
								// remove bad characters from file name
								$illegal_characters = array(" ", "\\", "/", ":", "*", "?", "\"", "<", ">", "|");
								$_FILES['preview']['name'] = str_replace($illegal_characters, "_", $_FILES['preview']['name']);
								
								// we passed the first two checks for the image extension/file format
								
								// set the pid
								$_FILES['preview']['project'] = $project['pid'];
								
								// uploading time!
								$build = $mods->projects->previews->upload($_FILES['preview']);
							
								// check for errors in the uploading process
								if ($build['error'])
								{
									$errors[] = $lang->mods_upload_problem."<br />".$build['error'];
								}
							}
						}
						
						if (count($errors) == 0)
						{
							// Success message
							$inline_errors = '<div class="success">'.$lang->mods_preview_submitted.'</div>';
						}
						else {
							$inline_errors = inline_error($errors);
						}
					}
					else 
					{
						$errors[] = $lang->mods_newpreview_invalid_preview;
						$inline_errors = inline_error($errors);
					}
				}
				else
					$inline_errors = inline_error($errors);
			}
			
			$project['name'] = htmlspecialchars_uni($project['name']);
			
			// Get previews of this project
			if (empty($previews))
			{
				$project['previews'] = $lang->mods_no_previews;
			}
			else {
			
				$project['previews'] = '';
				$count = 1;
			
				foreach ($previews as $preview)
				{
					if ($count == 4)
						$project['previews'] .= '<br class="clear" />';
				
					$image = htmlspecialchars_uni($preview['filename']);
					$thumbnail = htmlspecialchars_uni($preview['thumbnail']);
					
					eval("\$project['previews'] .= \"".$templates->get("mods_manage_previews_preview")."\";");
					
					$count++;
				}
				
				$project['previews'] .= '';
			}
			
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
			$breadcrumb .= ' \ <a href="'.$mybb->settings['bburl']."/mods.php?action=panel&amp;panelaction=managepreviews&amp;pid=".$project['pid'].'">'.$lang->mods_manage_previews.'</a>';
			
			eval("\$navigation = \"".$templates->get("mods_nav")."\";");
			
			// Force Navigation here
			$mods->buildNavHighlight($cat['parent']);
			
			// Title
			$title .= ' - '.$lang->mods_manage_previews.' - '.htmlspecialchars_uni($project['name']);
			
			eval("\$content = \"".$templates->get("mods_manage_previews")."\";");
		}
		elseif ($mybb->input['panelaction'] == 'deletepreview')
		{
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			// Is it a valid preview?
			$pid = (int)$mybb->input['previewid'];
			$preview = $mods->projects->previews->getByID($pid, true);
			if (empty($preview))
			{
				$mods->error($lang->mods_invalid_preview);
			}
			
			// Does the project exist? Must be approved in order to be viewed
			$project = $mods->projects->getByID($preview['project'],true);
			if (empty($project))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			// If the project hasn't been approved and we're not moderators we can't view the project
			if ($project['approved'] != 1 && !$mods->check_permissions($mybb->settings['mods_mods']))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			// If we're a developer or project owner we can manage previews
			if (!empty($project['collaborators']))
				$project['collaborators'] = explode(',', $project['collaborators']);
			else
				$project['collaborators'] = array();

			if ($project['uid'] != $mybb->user['uid'] && !in_array($mybb->user['uid'], $project['collaborators']))
			{
				error_no_permission();
			}
			
			$notice = $lang->sprintf($lang->mods_confirm_delete_preview, htmlspecialchars_uni($project['name']));
			$title .= ' - '.$lang->mods_delete_preview;
			$subaction = 'deletepreview';
			$otherfields .= '<input type="hidden" name="previewid" value="'.$pid.'" />';
			$otherfields .= '<input type="hidden" name="pid" value="'.$project['pid'].'" />';
			$action = 'panel';
			
			eval("\$content = \"".$templates->get("mods_do_action")."\";");
		}
		elseif ($mybb->input['panelaction'] == 'invitations')
		{
			$primer['title'] = $lang->mods_primer_panel_title;
			$primer['content'] = $lang->mods_primer_panel_content;
			$meta_description = $primer['content'];
			$primer['content'] = '<p>'.$primer['content'].'</p>';
			
			$uid = (int)$mybb->user['uid'];
			
			// pagination
			$per_page = 10;
			$mybb->input['page1'] = intval($mybb->input['page1']);
			if($mybb->input['page1'] && $mybb->input['page1'] > 1)
			{
				$mybb->input['page1'] = intval($mybb->input['page1']);
				$start = ($mybb->input['page1']*$per_page)-$per_page;
			}
			else
			{
				$mybb->input['page1'] = 1;
				$start = 0;
			}
			
			$query = $db->simple_select("mods_invitations", "COUNT(iid) as invitations", "touid = {$uid}");
			$total_rows = $db->fetch_field($query, "invitations");
			
			// multi-page
			if ($total_rows > $per_page)
				$multipage1 = multipage($total_rows, $per_page, $mybb->input['page1'], $mybb->settings['bburl']."/mods.php?action=panel&amp;panelaction=invitations&amp;page2={page}");	
			
			// Get list of projects
			$pjs = $mods->projects->getAll();
			
			$query = $db->query("
				SELECT u.*, i.*
				FROM ".TABLE_PREFIX."mods_invitations i
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=i.fromuid)
				WHERE i.touid = {$uid}
				ORDER BY i.date DESC LIMIT {$start}, {$per_page}
			");
			
			$received = '';
			while ($invitation = $db->fetch_array($query))
			{
				$invitation['user'] = build_profile_link(htmlspecialchars_uni($invitation['username']), $invitation['fromuid']);
				$invitation['project'] = htmlspecialchars_uni($pjs[$invitation['pid']]['name']);
				
				$invitation['date'] = my_date($mybb->settings['dateformat'], $invitation['date'], '', false).', '.my_date($mybb->settings['timeformat'], $invitation['date']);
				
				$invitation['options'] = '
					<a href="'.$mybb->settings['bburl'].'/mods.php?action=panel&amp;panelaction=accept&amp;iid='.$invitation['iid'].'&amp;my_post_key='.$mybb->post_code.'">'.$lang->mods_panel_invitations_accept.'</a>
					-
					<a href="'.$mybb->settings['bburl'].'/mods.php?action=panel&amp;panelaction=reject&amp;iid='.$invitation['iid'].'&amp;my_post_key='.$mybb->post_code.'">'.$lang->mods_panel_invitations_reject.'</a>';
				
				eval("\$received .= \"".$templates->get("mods_panel_invitations_invitation")."\";");
			}
			
			// Get list of received invitations
			if (empty($received))
			{
				$colspan = 4;
				eval("\$received = \"".$templates->get("mods_no_data")."\";");
			}
			
			// pagination
			$per_page = 10;
			$mybb->input['page2'] = intval($mybb->input['page2']);
			if($mybb->input['page2'] && $mybb->input['page2'] > 1)
			{
				$mybb->input['page2'] = intval($mybb->input['page2']);
				$start = ($mybb->input['page2']*$per_page)-$per_page;
			}
			else
			{
				$mybb->input['page2'] = 1;
				$start = 0;
			}
			
			$query = $db->simple_select("mods_invitations", "COUNT(iid) as invitations", "fromuid = {$uid}");
			$total_rows = $db->fetch_field($query, "invitations");
			
			// multi-page
			if ($total_rows > $per_page)
				$multipage2 = multipage($total_rows, $per_page, $mybb->input['page2'], $mybb->settings['bburl']."/mods.php?action=panel&amp;panelaction=invitations&amp;page2={page}");	
			
			$query = $db->query("
				SELECT u.*, i.*
				FROM ".TABLE_PREFIX."mods_invitations i
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=i.touid)
				WHERE i.fromuid = {$uid}
				ORDER BY i.date DESC LIMIT {$start}, {$per_page}
			");
			
			$sent = '';
			while ($invitation = $db->fetch_array($query))
			{
				$invitation['user'] = build_profile_link(htmlspecialchars_uni($invitation['username']), $invitation['touid']);
				$invitation['project'] = htmlspecialchars_uni($pjs[$invitation['pid']]['name']);
				
				$invitation['date'] = my_date($mybb->settings['dateformat'], $invitation['date'], '', false).', '.my_date($mybb->settings['timeformat'], $invitation['date']);
				
				$invitation['options'] = '
					<a href="'.$mybb->settings['bburl'].'/mods.php?action=panel&amp;panelaction=cancel&amp;iid='.$invitation['iid'].'&amp;my_post_key='.$mybb->post_code.'">'.$lang->mods_panel_invitations_cancel.'</a>';
				
				
				eval("\$sent .= \"".$templates->get("mods_panel_invitations_invitation")."\";");
			}
			
			// Get list of pending invitations
			if (empty($sent))
			{
				$colspan = 4;
				eval("\$sent = \"".$templates->get("mods_no_data")."\";");
			}
			
			$projects = '';
			
			// Get list of projects made by us
			foreach ($pjs as $project)
			{
				if ($project['uid'] != $mybb->user['uid'])
					continue;
					
				$projects .= '<option value='.intval($project['pid']).'>'.htmlspecialchars_uni($project['name']).'</option>';
			}
			
			// Title
			$title .= ' - '.$lang->mods_manage_projects;
			
			eval("\$primerblock = \"".$templates->get("mods_primer")."\";");
			eval("\$content = \"".$templates->get("mods_panel_invitations")."\";");
		}
		elseif ($mybb->input['panelaction'] == 'accept')
		{
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			$uid = (int)$mybb->user['uid'];
			$iid = (int)$mybb->input['iid'];
			
			// Verify invitation
			$query = $db->simple_select('mods_invitations', '*', 'touid=\''.$uid.'\' AND iid=\''.$iid.'\'');
			$invitation = $db->fetch_array($query);
			if (empty($invitation))
			{
				$mods->error($lang->mods_invalid_iid);
			}
			
			// Does the project exist? Must be approved, otherwise we can't join
			$pid = (int)$invitation['pid'];
			$project = $mods->projects->getByID($pid,true,true);
			if (empty($project))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			// Are we the owner of the project? If it can happen accidentally if someone changes the owner of the project before we accept the invitation.
			// If we are, then this invitation cannot be accepted - someone will report the error sooner or later.
			if ($mybb->user['uid'] == $project['uid'])
			{
				error_no_permission();
			}
			
			$notice = $lang->sprintf($lang->mods_confirm_accept, htmlspecialchars_uni($project['name']));
			$title .= ' - '.$lang->mods_accept_invitation;
			$subaction = 'accept';
			$otherfields = '<input type="hidden" name="iid" value="'.$iid.'" />';
			$action = 'panel';
			
			eval("\$content = \"".$templates->get("mods_do_action")."\";");
		}
		elseif ($mybb->input['panelaction'] == 'reject')
		{
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			$uid = (int)$mybb->user['uid'];
			$iid = (int)$mybb->input['iid'];
			
			// Verify invitation
			$query = $db->simple_select('mods_invitations', '*', 'touid=\''.$uid.'\' AND iid=\''.$iid.'\'');
			$invitation = $db->fetch_array($query);
			if (empty($invitation))
			{
				$mods->error($lang->mods_invalid_iid);
			}
			
			// Does the project exist? Must be approved, otherwise we can't join
			$pid = (int)$invitation['pid'];
			$project = $mods->projects->getByID($pid,true,true);
			if (empty($project))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			// Are we the owner of the project? If it can happen accidentally if someone changes the owner of the project before we accept the invitation.
			// If we are, then this invitation cannot be accepted - someone will report the error sooner or later.
			/*if ($mybb->user['uid'] == $project['uid'])
			{
				error_no_permission();
			}*/
			// REVISED: we don't need the above because if we're the owner we need to find a way to get rid of the invitation sent to ourselves
			
			$notice = $lang->sprintf($lang->mods_confirm_reject, htmlspecialchars_uni($project['name']));
			$title .= ' - '.$lang->mods_reject_invitation;
			$subaction = 'reject';
			$otherfields = '<input type="hidden" name="iid" value="'.$iid.'" />';
			$action = 'panel';
			
			eval("\$content = \"".$templates->get("mods_do_action")."\";");
		}
		elseif ($mybb->input['panelaction'] == 'cancel')
		{
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			$uid = (int)$mybb->user['uid'];
			$iid = (int)$mybb->input['iid'];
			
			// Verify invitation (must be sent from us in order to allow us to cancel)
			$query = $db->simple_select('mods_invitations', '*', 'fromuid=\''.$uid.'\' AND iid=\''.$iid.'\'');
			$invitation = $db->fetch_array($query);
			if (empty($invitation))
			{
				$mods->error($lang->mods_invalid_iid);
			}
			
			// Does the project exist? Must be approved, otherwise we can't join
			$pid = (int)$invitation['pid'];
			$project = $mods->projects->getByID($pid,true,true);
			if (empty($project))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			// We must be the owner of the project, otherwise we were not supposed to send the invitation
			if ($mybb->user['uid'] != $project['uid'])
			{
				error_no_permission();
			}
			
			$notice = $lang->sprintf($lang->mods_confirm_cancel, htmlspecialchars_uni($project['name']));
			$title .= ' - '.$lang->mods_cancel_invitation;
			$subaction = 'cancel';
			$otherfields = '<input type="hidden" name="iid" value="'.$iid.'" />';
			$action = 'panel';
			
			eval("\$content = \"".$templates->get("mods_do_action")."\";");
		}
		elseif ($mybb->input['panelaction'] == 'remove')
		{
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			$uid = (int)$mybb->input['uid'];
			
			// Does the project exist? Must be approved, otherwise we can't remove this user from it
			$pid = (int)$mybb->input['pid'];
			$project = $mods->projects->getByID($pid,true,true);
			if (empty($project))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			// Verify that we are the owner of the project
			if ($mybb->user['uid'] != $project['uid'])
			{
				error_no_permission();
			}
			
			// Verify that the user exists
			$q = $db->simple_select('users', 'uid', 'uid=\''.$uid.'\'');
			$user = (int)$db->fetch_field($q, 'uid');
			if ($user <= 0)
			{
				$mods->error($lang->mods_invalid_uid);
			}

			// Is the user collaborator of the project?
			if (!in_array($uid, explode(',', $project['collaborators'])))
			{
				$mods->error($lang->mods_invalid_uid);
			}
			
			$notice = $lang->sprintf($lang->mods_confirm_remove, htmlspecialchars_uni($project['name']));
			$title .= ' - '.$lang->mods_remove_user;
			$subaction = 'remove';
			$otherfields = '<input type="hidden" name="uid" value="'.$uid.'" />';
			$otherfields .= '<input type="hidden" name="pid" value="'.$pid.'" />';
			$action = 'panel';
			
			eval("\$content = \"".$templates->get("mods_do_action")."\";");
		}
		elseif ($mybb->input['panelaction'] == 'leave')
		{
			// Correct post key?
			verify_post_check($mybb->input['my_post_key']);
			
			$uid = (int)$mybb->user['uid'];
			
			// Does the project exist? Must be approved, otherwise we can't leave...can we?? Perhaps we should but..no.
			$pid = (int)$mybb->input['pid'];
			$project = $mods->projects->getByID($pid,true,true);
			if (empty($project))
			{
				$mods->error($lang->mods_invalid_pid);
			}
			
			// Are we the owner of the project? If so, we can't leave the project.
			if ($mybb->user['uid'] == $project['uid'])
			{
				error_no_permission();
			}
			
			// Are we collaborator of the project»
			if (!in_array($uid, explode(',', $project['collaborators'])))
			{
				// error!
				$mods->error($lang->mods_invalid_uid);
			}
			
			$notice = $lang->sprintf($lang->mods_confirm_leave, htmlspecialchars_uni($project['name']));
			$title .= ' - '.$lang->mods_leave_project;
			$subaction = 'leave';
			$otherfields = '<input type="hidden" name="pid" value="'.$pid.'" />';
			$action = 'panel';
			
			eval("\$content = \"".$templates->get("mods_do_action")."\";");
		}
		else {
			$primer['title'] = $lang->mods_primer_panel_title;
			$primer['content'] = $lang->mods_primer_panel_content;
			$meta_description = $primer['content'];
			$primer['content'] = '<p>'.$primer['content'].'</p>';
			
			// Get developer status - Approved? Regular?
			if (!$mybb->user['devstatus'])
				$mybb->user['devstatus'] = $mods->getDevStatus($mybb->user);
			else
				$mybb->user['devstatus'] = htmlspecialchars_uni($mybb->user['devstatus']);
			
			// Get total amount of projects we have created
			$mybb->user['projectscreated'] = $mods->projects->getCreatedBy((int)$mybb->user['uid'], true);
			
			// Get total amount of projects we collaborate on
			$mybb->user['collabprojects'] = $mods->projects->getCollaboratedBy((int)$mybb->user['uid'], true);
			
			$uid = (int)$mybb->user['uid'];

			// Get list of projects that we're part of and that have been updated recently
			// We can't use our fantastic Mods class
			$query = $db->query("
				SELECT u.*, p.*
				FROM ".TABLE_PREFIX."mods_projects p
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
				WHERE (p.uid={$uid} OR (CONCAT(',',p.testers,',') LIKE '%,{$uid},%' AND p.bugtracker_link='' AND p.bugtracking='1') OR CONCAT(',',p.collaborators,',') LIKE '%,{$uid},%') AND p.approved=1
				ORDER BY p.lastupdated DESC, p.submitted DESC LIMIT 5
			");
			
			$projects = '';
			while ($project = $db->fetch_array($query))
			{
				$project['name'] = htmlspecialchars_uni($project['name']);
				if ($project['hidden'] == 1)
				{
					$project['hidden'] = $lang->mods_hidden_project;
				}
				else
					$project['hidden'] = '';
				
				$project['description'] = htmlspecialchars_uni($project['description']);
				
				$project['author'] = build_profile_link(htmlspecialchars_uni($project['username']), $project['uid']);
				
				if ($project['lastupdated'] == 0)
					$project['lastupdated'] = $lang->mods_never;
				else
					$project['lastupdated'] = my_date($mybb->settings['dateformat'], $project['lastupdated'], '', false).', '.my_date($mybb->settings['timeformat'], $project['lastupdated']);
				
				eval("\$projects .= \"".$templates->get("mods_panel_recently_project")."\";");
			}

			if (empty($projects))
			{
				$colspan = 3;
				eval("\$projects = \"".$templates->get("mods_no_data")."\";");
			}
			
			// Get list of projects
			$pjs = $mods->projects->getAll();
			
			$query = $db->query("
				SELECT u.*, i.*
				FROM ".TABLE_PREFIX."mods_invitations i
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=i.fromuid)
				WHERE i.touid = {$uid}
				ORDER BY i.date DESC LIMIT 5
			");
			
			$invitations = '';
			while ($invitation = $db->fetch_array($query))
			{
				$invitation['from'] = build_profile_link(htmlspecialchars_uni($invitation['username']), $invitation['fromuid']);
				$invitation['project'] = htmlspecialchars_uni($pjs[$invitation['pid']]['name']);
				
				$invitation['date'] = my_date($mybb->settings['dateformat'], $invitation['date'], '', false).', '.my_date($mybb->settings['timeformat'], $invitation['date']);
				
				eval("\$invitations .= \"".$templates->get("mods_panel_recently_invitation")."\";");
			}
			
			// Get list of pending invitations
			if (empty($invitations))
			{
				$colspan = 3;
				eval("\$invitations = \"".$templates->get("mods_no_data")."\";");
			}
			
			// Build versions select box
			$versions = '';
			$vs = explode(',', MYBB_VERSIONS);
			rsort($vs);
			$selvs = explode(',', $data['versions']);
			
			foreach ($vs as $version)
			{
				if (in_array($version, $selvs))
					$versions .= '<option value="'.$version.'" selected="selected">'.substr($version, 0, 1).'.'.substr($version, 1, 1).'.'.substr($version, 2).'</option>';
				else
					$versions .= '<option value="'.$version.'">'.substr($version, 0, 1).'.'.substr($version, 1, 1).'.'.substr($version, 2).'</option>';
			}
			
			// Get list of parent categories
			$parents = $mods->categories->getParents();
			
			$categories = '';
			foreach ($parents as $cat)
			{
				if ($cat == $data['category'])
					$categories .= '<option value="'.$cat.'" selected="selected">'.ucfirst($cat).'</option>';
				else
					$categories .= '<option value="'.$cat.'">'.ucfirst($cat).'</option>';
			}
			
			// Build sub categories select boxes
			$cats = $mods->categories->getAll();
			
			$plugins_subcategories = $themes_subcategories = $resources_subcategories = $graphics_subcategories = '';
			foreach ($cats as $cat)
			{
				if ($cat['cid'] == $data['cid'])
					$selected = ' selected="selected"';
				else
					$selected = '';
				
				switch ($cat['parent'])
				{
					case 'plugins':
						
						$plugins_subcategories .= '<option value="'.$cat['cid'].'" '.$selected.'>'.htmlspecialchars_uni($cat['name']).'</option>';
					break;
					case 'themes':
						$themes_subcategories .= '<option value="'.$cat['cid'].'" '.$selected.'>'.htmlspecialchars_uni($cat['name']).'</option>';
					break;
					case 'resources':
						$resources_subcategories .= '<option value="'.$cat['cid'].'" '.$selected.'>'.htmlspecialchars_uni($cat['name']).'</option>';
					break;
					case 'graphics':
						$graphics_subcategories .= '<option value="'.$cat['cid'].'" '.$selected.'>'.htmlspecialchars_uni($cat['name']).'</option>';
					break;
				}
			}

			eval("\$primerblock = \"".$templates->get("mods_primer")."\";");
			eval("\$content = \"".$templates->get("mods_panel")."\";");
		}
	}
}

?>