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
 * Build a post bit
 *
 * @param array The post data
 * @param int The type of post bit we're building (1 = preview, 2 = pm, 3 = announcement, else = post)
 * @return string The built post bit
 */
function build_postbit($post, $post_type=0)
{
	global $db, $altbg, $theme, $mybb, $postcounter, $profile_fields;
	global $titlescache, $page, $templates, $forumpermissions, $attachcache;
	global $lang, $ismod, $inlinecookie, $inlinecount, $groupscache, $fid;
	global $plugins, $parser, $cache, $ignored_users, $hascustomtitle;

	$hascustomtitle = 0;

	// Set default values for any fields not provided here
	foreach(array('pid', 'aid', 'pmid', 'posturl', 'button_multiquote', 'subject_extra', 'attachments', 'button_rep', 'button_warn', 'button_pm', 'button_reply_pm', 'button_replyall_pm', 'button_forward_pm', 'button_delete_pm', 'replink', 'warninglevel') as $post_field)
	{
		if(empty($post[$post_field]))
		{
			$post[$post_field] = '';
		}
	}

	// Set up the message parser if it doesn't already exist.
	if(!$parser)
	{
		require_once MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser;
	}

	$unapproved_shade = '';
	if(isset($post['visible']) && $post['visible'] == 0 && $post_type == 0)
	{
		$altbg = $unapproved_shade = 'unapproved_post';
	}
	elseif(isset($post['visible']) && $post['visible'] == -1 && $post_type == 0)
	{
		$altbg = $unapproved_shade = 'unapproved_post deleted_post';
	}
	elseif($altbg == 'trow1')
	{
		$altbg = 'trow2';
	}
	else
	{
		$altbg = 'trow1';
	}
	$post['fid'] = $fid;
	switch($post_type)
	{
		case 1: // Message preview
			global $forum;
			$parser_options['allow_html'] = $forum['allowhtml'];
			$parser_options['allow_mycode'] = $forum['allowmycode'];
			$parser_options['allow_smilies'] = $forum['allowsmilies'];
			$parser_options['allow_imgcode'] = $forum['allowimgcode'];
			$parser_options['allow_videocode'] = $forum['allowvideocode'];
			$parser_options['me_username'] = $post['username'];
			$parser_options['filter_badwords'] = 1;
			$id = 0;
			break;
		case 2: // Private message
			global $message, $pmid;
			$idtype = 'pmid';
			$parser_options['allow_html'] = $mybb->settings['pmsallowhtml'];
			$parser_options['allow_mycode'] = $mybb->settings['pmsallowmycode'];
			$parser_options['allow_smilies'] = $mybb->settings['pmsallowsmilies'];
			$parser_options['allow_imgcode'] = $mybb->settings['pmsallowimgcode'];
			$parser_options['allow_videocode'] = $mybb->settings['pmsallowvideocode'];
			$parser_options['me_username'] = $post['username'];
			$parser_options['filter_badwords'] = 1;
			$id = $pmid;
			break;
		case 3: // Announcement
			global $announcementarray, $message;
			$parser_options['allow_html'] = $announcementarray['allowhtml'];
			$parser_options['allow_mycode'] = $announcementarray['allowmycode'];
			$parser_options['allow_smilies'] = $announcementarray['allowsmilies'];
			$parser_options['allow_imgcode'] = 1;
			$parser_options['allow_videocode'] = 1;
			$parser_options['me_username'] = $post['username'];
			$parser_options['filter_badwords'] = 1;
			$id = $announcementarray['aid'];
			break;
		default: // Regular post
			global $forum, $thread, $tid;
			$oldforum = $forum;
			$id = intval($post['pid']);
			$idtype = 'pid';
			$parser_options['allow_html'] = $forum['allowhtml'];
			$parser_options['allow_mycode'] = $forum['allowmycode'];
			$parser_options['allow_smilies'] = $forum['allowsmilies'];
			$parser_options['allow_imgcode'] = $forum['allowimgcode'];
			$parser_options['allow_videocode'] = $forum['allowvideocode'];
			$parser_options['filter_badwords'] = 1;

			if(!$post['username'])
			{
				$post['username'] = $lang->guest;
			}

			if($post['userusername'])
			{
				$parser_options['me_username'] = $post['userusername'];
			}
			else
			{
				$parser_options['me_username'] = $post['username'];
			}
			break;
	}

	if(!$postcounter)
	{ // Used to show the # of the post
		if($page > 1)
		{
			if(!$mybb->settings['postsperpage'])
			{
				$mybb->settings['postsperpage'] = 20;
			}

			$postcounter = $mybb->settings['postsperpage']*($page-1);
		}
		else
		{
			$postcounter = 0;
		}
		$post_extra_style = "border-top-width: 0;";
	}
	elseif($mybb->input['mode'] == "threaded")
	{
		$post_extra_style = "border-top-width: 0;";
	}
	else
	{
		$post_extra_style = "margin-top: 5px;";
	}

	if(!$altbg)
	{ // Define the alternate background colour if this is the first post
		$altbg = "trow1";
	}
	$postcounter++;

	// Format the post date and time using my_date
	$post['postdate'] = my_date('relative', $post['dateline']);

	// Dont want any little 'nasties' in the subject
	$post['subject'] = $parser->parse_badwords($post['subject']);

	// Pm's have been htmlspecialchars_uni()'ed already.
	if($post_type != 2)
	{
		$post['subject'] = htmlspecialchars_uni($post['subject']);
	}

	if(empty($post['subject']))
	{
		$post['subject'] = '&nbsp;';
	}

	$post['author'] = $post['uid'];
	$post['subject_title'] = $post['subject'];

	// Get the usergroup
	if($post['userusername'])
	{
		if(!$post['displaygroup'])
		{
			$post['displaygroup'] = $post['usergroup'];
		}
		$usergroup = $groupscache[$post['displaygroup']];
	}
	else
	{
		$usergroup = $groupscache[1];
	}

	if(!is_array($titlescache))
	{
		$cached_titles = $cache->read("usertitles");
		if(!empty($cached_titles))
		{
			foreach($cached_titles as $usertitle)
			{
				$titlescache[$usertitle['posts']] = $usertitle;
			}
		}

		if(is_array($titlescache))
		{
			krsort($titlescache);
		}
		unset($usertitle, $cached_titles);
	}

	// Work out the usergroup/title stuff
	$post['groupimage'] = '';
	if(!empty($usergroup['image']))
	{
		$language = $mybb->settings['bblanguage'];
		if(!empty($mybb->user['language']))
		{
			$language = $mybb->user['language'];
		}

		$usergroup['image'] = str_replace("{lang}", $language, $usergroup['image']);
		$usergroup['image'] = str_replace("{theme}", $theme['imgdir'], $usergroup['image']);
		eval("\$post['groupimage'] = \"".$templates->get("postbit_groupimage")."\";");

		if($mybb->settings['postlayout'] == "classic")
		{
			$post['groupimage'] .= "<br />";
		}
	}

	if($post['userusername'])
	{
		// This post was made by a registered user
		$post['username'] = $post['userusername'];
		$post['profilelink_plain'] = get_profile_link($post['uid']);
		$post['username_formatted'] = format_name($post['username'], $post['usergroup'], $post['displaygroup']);
		$post['profilelink'] = build_profile_link($post['username_formatted'], $post['uid']);

		if(trim($post['usertitle']) != "")
		{
			$hascustomtitle = 1;
		}

		if($usergroup['usertitle'] != "" && !$hascustomtitle)
		{
			$post['usertitle'] = $usergroup['usertitle'];
		}
		elseif(is_array($titlescache) && !$usergroup['usertitle'])
		{
			reset($titlescache);
			foreach($titlescache as $key => $titleinfo)
			{
				if($post['postnum'] >= $key)
				{
					if(!$hascustomtitle)
					{
						$post['usertitle'] = $titleinfo['title'];
					}
					$post['stars'] = $titleinfo['stars'];
					$post['starimage'] = $titleinfo['starimage'];
					break;
				}
			}
		}

		if($usergroup['stars'])
		{
			$post['stars'] = $usergroup['stars'];
		}

		if(empty($post['starimage']))
		{
			$post['starimage'] = $usergroup['starimage'];
		}

		if($post['starimage'] && $post['stars'])
		{
			// Only display stars if we have an image to use...
			$post['starimage'] = str_replace("{theme}", $theme['imgdir'], $post['starimage']);

			$post['userstars'] = '';
			for($i = 0; $i < $post['stars']; ++$i)
			{
				$post['userstars'] .= "<img src=\"{$post['starimage']}\" border=\"0\" alt=\"*\" />";
			}

			$post['userstars'] .= "<br />";
		}

		$postnum = $post['postnum'];
		$post['postnum'] = my_number_format($post['postnum']);

		// Determine the status to show for the user (Online/Offline/Away)
		$timecut = TIME_NOW - $mybb->settings['wolcutoff'];
		if($post['lastactive'] > $timecut && ($post['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1) && $post['lastvisit'] != $post['lastactive'])
		{
			eval("\$post['onlinestatus'] = \"".$templates->get("postbit_online")."\";");
		}
		else
		{
			if($post['away'] == 1 && $mybb->settings['allowaway'] != 0)
			{
				eval("\$post['onlinestatus'] = \"".$templates->get("postbit_away")."\";");
			}
			else
			{
				eval("\$post['onlinestatus'] = \"".$templates->get("postbit_offline")."\";");
			}
		}

		$post['useravatar'] = '';
		if(isset($mybb->user['showavatars']) && $mybb->user['showavatars'] != 0 || $mybb->user['uid'] == 0)
		{
			$useravatar = format_avatar(htmlspecialchars_uni($post['avatar']), $post['avatardimensions'], $mybb->settings['postmaxavatarsize']);
			eval("\$post['useravatar'] = \"".$templates->get("postbit_avatar")."\";");
		}
		else
		{
			$post['useravatar'] = '';
		}

		eval("\$post['button_find'] = \"".$templates->get("postbit_find")."\";");

		if($mybb->settings['enablepms'] == 1 && $post['receivepms'] != 0 && $mybb->usergroup['cansendpms'] == 1 && my_strpos(",".$post['ignorelist'].",", ",".$mybb->user['uid'].",") === false)
		{
			eval("\$post['button_pm'] = \"".$templates->get("postbit_pm")."\";");
		}

		$post['button_rep'] = '';
		if($mybb->settings['enablereputation'] == 1 && $mybb->settings['postrep'] == 1 && $mybb->usergroup['cangivereputations'] == 1 && $usergroup['usereputationsystem'] == 1 && ($mybb->settings['posrep'] || $mybb->settings['neurep'] || $mybb->settings['negrep']) && $post['uid'] != $mybb->user['uid'])
		{
			if(!$post['pid'])
			{
				$post['pid'] = 0;
			}

			eval("\$post['button_rep'] = \"".$templates->get("postbit_rep_button")."\";");
		}

		if($post['website'] != "")
		{
			$post['website'] = htmlspecialchars_uni($post['website']);
			eval("\$post['button_www'] = \"".$templates->get("postbit_www")."\";");
		}
		else
		{
			$post['button_www'] = "";
		}

		if($post['hideemail'] != 1 && $mybb->usergroup['cansendemail'] == 1)
		{
			eval("\$post['button_email'] = \"".$templates->get("postbit_email")."\";");
		}
		else
		{
			$post['button_email'] = "";
		}

		$post['userregdate'] = my_date($mybb->settings['regdateformat'], $post['regdate']);

		// Work out the reputation this user has (only show if not announcement)
		if($post_type != 3 && $usergroup['usereputationsystem'] != 0 && $mybb->settings['enablereputation'] == 1)
		{
			$post['userreputation'] = get_reputation($post['reputation'], $post['uid']);
			eval("\$post['replink'] = \"".$templates->get("postbit_reputation")."\";");
		}

		// Showing the warning level? (only show if not announcement)
		if($post_type != 3 && $mybb->settings['enablewarningsystem'] != 0 && $usergroup['canreceivewarnings'] != 0 && ($mybb->usergroup['canwarnusers'] != 0 || ($mybb->user['uid'] == $post['uid'] && $mybb->settings['canviewownwarning'] != 0)))
		{
			$warning_level = round($post['warningpoints']/$mybb->settings['maxwarningpoints']*100);
			if($warning_level > 100)
			{
				$warning_level = 100;
			}
			$warning_level = get_colored_warning_level($warning_level);

			// If we can warn them, it's not the same person, and we're in a PM or a post.
			if($mybb->usergroup['canwarnusers'] != 0 && $post['uid'] != $mybb->user['uid'] && ($post_type == 0 || $post_type == 2))
			{
				eval("\$post['button_warn'] = \"".$templates->get("postbit_warn")."\";");
				$warning_link = "warnings.php?uid={$post['uid']}";
			}
			else
			{
				$post['button_warn'] = '';
				$warning_link = "usercp.php";
			}
			eval("\$post['warninglevel'] = \"".$templates->get("postbit_warninglevel")."\";");
		}

		// Display profile fields on posts - only if field is filled in
		if(is_array($profile_fields))
		{
			foreach($profile_fields as $field)
			{
				$fieldfid = "fid{$field['fid']}";
				if(!empty($post[$fieldfid]))
				{
					$post['fieldvalue'] = '';
					$post['fieldname'] = htmlspecialchars_uni($field['name']);

					$thing = explode("\n", $field['type'], "2");
					$type = trim($thing[0]);
					$useropts = explode("\n", $post[$fieldfid]);

					// Skip over texa area fields, as they might break layout
					if($type == "textarea")
					{
						continue;
					}

					if(is_array($useropts) && ($type == "multiselect" || $type == "checkbox"))
					{
						foreach($useropts as $val)
						{
							if($val != '')
							{
								$post['fieldvalue'] .= "<li style=\"margin-left: 0;\">{$val}</li>";
							}
						}
						if($post['fieldvalue'] != '')
						{
							$post['fieldvalue'] = "<ul style=\"margin: 0; padding-left: 15px;\">{$post['fieldvalue']}</ul>";
						}
					}
					else
					{
						$post['fieldvalue'] = htmlspecialchars_uni($parser->parse_badwords($post[$fieldfid]));
					}

					eval("\$post['profilefield'] .= \"".$templates->get("postbit_profilefield")."\";");
				}
			}
		}

		eval("\$post['user_details'] = \"".$templates->get("postbit_author_user")."\";");
	}
	else
	{ // Message was posted by a guest or an unknown user
		$post['profilelink'] = format_name($post['username'], 1);

		if($usergroup['usertitle'])
		{
			$post['usertitle'] = $usergroup['usertitle'];
		}
		else
		{
			$post['usertitle'] = $lang->guest;
		}

		$usergroup['title'] = $lang->na;

		$post['userregdate'] = $lang->na;
		$post['postnum'] = $lang->na;
		$post['button_profile'] = '';
		$post['button_email'] = '';
		$post['button_www'] = '';
		$post['signature'] = '';
		$post['button_pm'] = '';
		$post['button_find'] = '';
		$post['onlinestatus'] = '';
		$post['replink'] = '';
		eval("\$post['user_details'] = \"".$templates->get("postbit_author_guest")."\";");
	}

	$post['button_edit'] = '';
	$post['button_quickdelete'] = '';
	$post['button_quote'] = '';
	$post['button_quickquote'] = '';
	$post['button_report'] = '';
	$post['button_reply_pm'] = '';
	$post['button_replyall_pm'] = '';
	$post['button_forward_pm']  = '';
	$post['button_delete_pm'] = '';

	// For private messages, fetch the reply/forward/delete icons
	if($post_type == 2 && $post['pmid'])
	{
		global $replyall;

		eval("\$post['button_reply_pm'] = \"".$templates->get("postbit_reply_pm")."\";");
		eval("\$post['button_forward_pm'] = \"".$templates->get("postbit_forward_pm")."\";");
		eval("\$post['button_delete_pm'] = \"".$templates->get("postbit_delete_pm")."\";");

		if($replyall == true)
		{
			eval("\$post['button_replyall_pm'] = \"".$templates->get("postbit_replyall_pm")."\";");
		}
	}

	$post['editedmsg'] = '';
	if(!$post_type)
	{
		// Figure out if we need to show an "edited by" message
		if($post['edituid'] != 0 && $post['edittime'] != 0 && $post['editusername'] != "" && (($mybb->settings['showeditedby'] != 0 && $usergroup['cancp'] == 0) || ($mybb->settings['showeditedbyadmin'] != 0 && $usergroup['cancp'] == 1)))
		{
			$post['editdate'] = my_date('relative', $post['edittime']);
			$post['editnote'] = $lang->sprintf($lang->postbit_edited, $post['editdate']);
			$post['editedprofilelink'] = build_profile_link($post['editusername'], $post['edituid']);
			$editreason = "";
			if($post['editreason'] != "")
			{
				$post['editreason'] = $parser->parse_badwords($post['editreason']);
				$post['editreason'] = htmlspecialchars_uni($post['editreason']);
				eval("\$editreason = \"".$templates->get("postbit_editedby_editreason")."\";");
			}
			eval("\$post['editedmsg'] = \"".$templates->get("postbit_editedby")."\";");
		}

		if((is_moderator($fid, "caneditposts") || ($forumpermissions['caneditposts'] == 1 && $mybb->user['uid'] == $post['uid'])) && $mybb->user['uid'] != 0)
		{
			eval("\$post['button_edit'] = \"".$templates->get("postbit_edit")."\";");
		}

		// Quick Delete button
		$can_delete = 0;
		if($mybb->user['uid'] == $post['uid'])
		{
			if($forumpermissions['candeletethreads'] == 1 && $postcounter == 1)
			{
				$can_delete = 1;
			}
			else if($forumpermissions['candeleteposts'] == 1 && $postcounter != 1)
			{
				$can_delete = 1;
			}
		}

		if((is_moderator($fid, "candeleteposts") || $can_delete == 1) && $mybb->user['uid'] != 0)
		{
			eval("\$post['button_quickdelete'] = \"".$templates->get("postbit_quickdelete")."\";");
		}

		// Inline moderation stuff
		if($ismod)
		{
			if(isset($mybb->cookies[$inlinecookie]) && my_strpos($mybb->cookies[$inlinecookie], "|".$post['pid']."|"))
			{
				$inlinecheck = "checked=\"checked\"";
				$inlinecount++;
			}
			else
			{
				$inlinecheck = "";
			}

			eval("\$post['inlinecheck'] = \"".$templates->get("postbit_inlinecheck")."\";");

			if($post['visible'] == 0)
			{
				$invisiblepost = 1;
			}
		}
		else
		{
			$post['inlinecheck'] = "";
		}
		$post['postlink'] = get_post_link($post['pid'], $post['tid']);
		$post_number = my_number_format($postcounter);
		eval("\$post['posturl'] = \"".$templates->get("postbit_posturl")."\";");
		global $forum, $thread;

		if($forum['open'] != 0 && ($thread['closed'] != 1 || is_moderator($forum['fid'])) && ($thread['uid'] == $mybb->user['uid'] || $forumpermissions['canonlyreplyownthreads'] != 1))
		{
			eval("\$post['button_quote'] = \"".$templates->get("postbit_quote")."\";");
		}

		if($forumpermissions['canpostreplys'] != 0 && ($thread['uid'] == $mybb->user['uid'] || $forumpermissions['canonlyreplyownthreads'] != 1) && ($thread['closed'] != 1 || is_moderator($fid)) && $mybb->settings['multiquote'] != 0 && $forum['open'] != 0 && !$post_type)
		{
			eval("\$post['button_multiquote'] = \"".$templates->get("postbit_multiquote")."\";");
		}

		if($mybb->user['uid'] != "0")
		{
			eval("\$post['button_report'] = \"".$templates->get("postbit_report")."\";");
		}
	}
	elseif($post_type == 3) // announcement
	{
		if($mybb->usergroup['issupermod'] == 1 || is_moderator($fid))
		{
			eval("\$post['button_edit'] = \"".$templates->get("announcement_edit")."\";");
			eval("\$post['button_quickdelete'] = \"".$templates->get("announcement_quickdelete")."\";");
		}
	}

	$post['iplogged'] = '';
	$show_ips = $mybb->settings['logip'];
	$ipaddress = my_inet_ntop($db->unescape_binary($post['ipaddress']));

	// Show post IP addresses... PMs now can have IP addresses too as of 1.8!
	if($post_type == 2)
	{
		$show_ips = $mybb->settings['showpmip'];
	}
	if(!$post_type || $post_type == 2)
	{
		if($show_ips != "no" && !empty($post['ipaddress']))
		{
			if($show_ips == "show")
			{
				eval("\$post['iplogged'] = \"".$templates->get("postbit_iplogged_show")."\";");
			}
			else if($show_ips == "hide" && (is_moderator($fid, "canviewips") || $mybb->usergroup['issupermod']))
			{
				$action = 'getip';
				if($post_type == 2)
				{
					$action = 'getpmip';
				}
				eval("\$post['iplogged'] = \"".$templates->get("postbit_iplogged_hiden")."\";");
			}
		}
	}

	if(isset($post['smilieoff']) && $post['smilieoff'] == 1)
	{
		$parser_options['allow_smilies'] = 0;
	}

	// If we have incoming search terms to highlight - get it done.
	if(!empty($mybb->input['highlight']))
	{
		$parser_options['highlight'] = $mybb->input['highlight'];
		$post['subject'] = $parser->highlight_message($post['subject'], $parser_options['highlight']);
	}

	$post['message'] = $parser->parse_message($post['message'], $parser_options);

	$post['attachments'] = '';
	if($mybb->settings['enableattachments'] != 0)
	{
		get_post_attachments($id, $post);
	}

	if(isset($post['includesig']) && $post['includesig'] != 0 && $post['username'] && $post['signature'] != "" && ($mybb->user['uid'] == 0 || $mybb->user['showsigs'] != 0) && ($post['suspendsignature'] == 0 || $post['suspendsignature'] == 1 && $post['suspendsigtime'] != 0 && $post['suspendsigtime'] < TIME_NOW) && $usergroup['canusesig'] == 1 && ($usergroup['canusesigxposts'] == 0 || $usergroup['canusesigxposts'] > 0 && $postnum > $usergroup['canusesigxposts']))
	{
		$sig_parser = array(
			"allow_html" => $mybb->settings['sightml'],
			"allow_mycode" => $mybb->settings['sigmycode'],
			"allow_smilies" => $mybb->settings['sigsmilies'],
			"allow_imgcode" => $mybb->settings['sigimgcode'],
			"me_username" => $post['username'],
			"filter_badwords" => 1
		);

		if($usergroup['signofollow'])
		{
			$sig_parser['nofollow_on'] = 1;
		}

		$post['signature'] = $parser->parse_message($post['signature'], $sig_parser);
		eval("\$post['signature'] = \"".$templates->get("postbit_signature")."\";");
	}
	else
	{
		$post['signature'] = "";
	}

	$icon_cache = $cache->read("posticons");

	if(isset($post['icon']) && $post['icon'] > 0 && $icon_cache[$post['icon']])
	{
		$icon = $icon_cache[$post['icon']];

		$icon['path'] = htmlspecialchars_uni($icon['path']);
		$icon['name'] = htmlspecialchars_uni($icon['name']);
		$post['icon'] = "<img src=\"{$icon['path']}\" alt=\"{$icon['name']}\" style=\"vertical-align: middle;\" />&nbsp;";
	}
	else
	{
		$post['icon'] = "";
	}

	$post_visibility = $ignore_bit = '';
	switch($post_type)
	{
		case 1: // Message preview
			$post = $plugins->run_hooks("postbit_prev", $post);
			break;
		case 2: // Private message
			$post = $plugins->run_hooks("postbit_pm", $post);
			break;
		case 3: // Announcement
			$post = $plugins->run_hooks("postbit_announcement", $post);
			break;
		default: // Regular post
			$post = $plugins->run_hooks("postbit", $post);

			// Is this author on the ignore list of the current user? Hide this post
			if(is_array($ignored_users) && $post['uid'] != 0 && isset($ignored_users[$post['uid']]) && $ignored_users[$post['uid']] == 1)
			{
				$ignored_message = $lang->sprintf($lang->postbit_currently_ignoring_user, $post['username']);
				eval("\$ignore_bit = \"".$templates->get("postbit_ignored")."\";");
				$post_visibility = "display: none;";
			}
			break;
	}

	if($mybb->settings['postlayout'] == "classic")
	{
		eval("\$postbit = \"".$templates->get("postbit_classic")."\";");
	}
	else
	{
		eval("\$postbit = \"".$templates->get("postbit")."\";");
	}
	$GLOBALS['post'] = "";

	return $postbit;
}

/**
 * Fetch the attachments for a specific post and parse inline [attachment=id] code.
 * Note: assumes you have $attachcache, an array of attachments set up.
 *
 * @param int The ID of the item.
 * @param array The post or item passed by reference.
 */
function get_post_attachments($id, &$post)
{
	global $attachcache, $mybb, $theme, $templates, $forumpermissions, $lang;

	$validationcount = 0;
	$tcount = 0;
	$post['attachmentlist'] = $post['thumblist'] = $post['imagelist'] = '';
	if(isset($attachcache[$id]) && is_array($attachcache[$id]))
	{ // This post has 1 or more attachments
		foreach($attachcache[$id] as $aid => $attachment)
		{
			if($attachment['visible'])
			{ // There is an attachment thats visible!
				$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);
				$attachment['filesize'] = get_friendly_size($attachment['filesize']);
				$ext = get_extension($attachment['filename']);
				if($ext == "jpeg" || $ext == "gif" || $ext == "bmp" || $ext == "png" || $ext == "jpg")
				{
					$isimage = true;
				}
				else
				{
					$isimage = false;
				}
				$attachment['icon'] = get_attachment_icon($ext);
				$attachment['downloads'] = my_number_format($attachment['downloads']);

				if(!$attachment['dateuploaded'])
				{
					$attachment['dateuploaded'] = $attachment['dateline'];
				}
				$attachdate = my_date('relative', $attachment['dateuploaded']);
				// Support for [attachment=id] code
				if(stripos($post['message'], "[attachment=".$attachment['aid']."]") !== false)
				{
					// Show as thumbnail IF image is big && thumbnail exists && setting=='thumb'
					// Show as full size image IF setting=='fullsize' || (image is small && permissions allow)
					// Show as download for all other cases
					if($attachment['thumbnail'] != "SMALL" && $attachment['thumbnail'] != "" && $mybb->settings['attachthumbnails'] == "yes")
					{
						eval("\$attbit = \"".$templates->get("postbit_attachments_thumbnails_thumbnail")."\";");
					}
					elseif((($attachment['thumbnail'] == "SMALL" && $forumpermissions['candlattachments'] == 1) || $mybb->settings['attachthumbnails'] == "no") && $isimage)
					{
						eval("\$attbit = \"".$templates->get("postbit_attachments_images_image")."\";");
					}
					else
					{
						eval("\$attbit = \"".$templates->get("postbit_attachments_attachment")."\";");
					}
					$post['message'] = preg_replace("#\[attachment=".$attachment['aid']."]#si", $attbit, $post['message']);
				}
				else
				{
					// Show as thumbnail IF image is big && thumbnail exists && setting=='thumb'
					// Show as full size image IF setting=='fullsize' || (image is small && permissions allow)
					// Show as download for all other cases
					if($attachment['thumbnail'] != "SMALL" && $attachment['thumbnail'] != "" && $mybb->settings['attachthumbnails'] == "yes")
					{
						eval("\$post['thumblist'] .= \"".$templates->get("postbit_attachments_thumbnails_thumbnail")."\";");
						if($tcount == 5)
						{
							$thumblist .= "<br />";
							$tcount = 0;
						}
						++$tcount;
					}
					elseif((($attachment['thumbnail'] == "SMALL" && $forumpermissions['candlattachments'] == 1) || $mybb->settings['attachthumbnails'] == "no") && $isimage)
					{
						eval("\$post['imagelist'] .= \"".$templates->get("postbit_attachments_images_image")."\";");
					}
					else
					{
						eval("\$post['attachmentlist'] .= \"".$templates->get("postbit_attachments_attachment")."\";");
					}
				}
			}
			else
			{
				$validationcount++;
			}
		}
		if($validationcount > 0 && is_moderator($post['fid']))
		{
			if($validationcount == 1)
			{
				$postbit_unapproved_attachments = $lang->postbit_unapproved_attachment;
			}
			else
			{
				$postbit_unapproved_attachments = $lang->sprintf($lang->postbit_unapproved_attachments, $validationcount);
			}
			eval("\$post['attachmentlist'] .= \"".$templates->get("postbit_attachments_attachment_unapproved")."\";");
		}
		if($post['thumblist'])
		{
			eval("\$post['attachedthumbs'] = \"".$templates->get("postbit_attachments_thumbnails")."\";");
		}
		else
		{
			$post['attachedthumbs'] = '';
		}
		if($post['imagelist'])
		{
			eval("\$post['attachedimages'] = \"".$templates->get("postbit_attachments_images")."\";");
		}
		else
		{
			$post['attachedimages'] = '';
		}
		if($post['attachmentlist'] || $post['thumblist'] || $post['imagelist'])
		{
			eval("\$post['attachments'] = \"".$templates->get("postbit_attachments")."\";");
		}
	}
}
?>