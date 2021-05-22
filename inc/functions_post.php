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
 * @param array $post The post data
 * @param int $post_type The type of post bit we're building (1 = preview, 2 = pm, 3 = announcement, else = post)
 * @return string The built post bit
 */
function build_postbit($post, $post_type=0)
{
	global $db, $altbg, $theme, $mybb, $postcounter, $profile_fields;
	global $titlescache, $page, $forumpermissions, $attachcache;
	global $lang, $ismod, $inlinecookie, $inlinecount, $groupscache, $fid;
	global $plugins, $parser, $cache, $ignored_users, $hascustomtitle;

	$hascustomtitle = 0;

	// Set default values for any fields not provided here
	foreach(array('pid', 'aid', 'pmid', 'posturl', 'subject_extra', 'warninglevel') as $post_field)
	{
		if(empty($post[$post_field]))
		{
			$post[$post_field] = '';
		}
	}

	// Set default boolean values
	foreach(array('button_multiquote', 'button_rep', 'can_warn', 'button_purgespammer', 'button_pm', 'button_reply_pm', 'is_pm', 'replink', 'can_edit', 'quick_delete', 'quick_restore') as $post_field)
	{
		if(empty($post[$post_field]))
		{
			$post[$post_field] = false;
		}
	}

	// Set up the message parser if it doesn't already exist.
	if(!$parser)
	{
		require_once MYBB_ROOT.'inc/class_parser.php';
		$parser = new postParser;
	}

	if(!function_exists('purgespammer_show'))
	{
		require_once MYBB_ROOT.'inc/functions_user.php';
	}

	$post['unapproved_shade'] = '';
	if(isset($post['visible']) &&
		$post['visible'] == 0 && $post_type == 0)
	{
		$altbg = $post['unapproved_shade'] = 'unapproved_post';
	}
	elseif(isset($post['visible']) &&
		$post['visible'] == -1 &&
		$post_type == 0)
	{
		$altbg = $post['unapproved_shade'] = 'unapproved_post deleted_post';
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
			$post['id'] = 0;
			break;
		case 2: // Private message
			global $message, $pmid;
			$post['idtype'] = 'pmid';
			$parser_options['allow_html'] = $mybb->settings['pmsallowhtml'];
			$parser_options['allow_mycode'] = $mybb->settings['pmsallowmycode'];
			$parser_options['allow_smilies'] = $mybb->settings['pmsallowsmilies'];
			$parser_options['allow_imgcode'] = $mybb->settings['pmsallowimgcode'];
			$parser_options['allow_videocode'] = $mybb->settings['pmsallowvideocode'];
			$parser_options['me_username'] = $post['username'];
			$parser_options['filter_badwords'] = 1;
			$post['id'] = $pmid;
			break;
		case 3: // Announcement
			global $announcementarray, $message;
			$parser_options['allow_html'] = $mybb->settings['announcementshtml'] && $announcementarray['allowhtml'];
			$parser_options['allow_mycode'] = $announcementarray['allowmycode'];
			$parser_options['allow_smilies'] = $announcementarray['allowsmilies'];
			$parser_options['allow_imgcode'] = 1;
			$parser_options['allow_videocode'] = 1;
			$parser_options['me_username'] = $post['username'];
			$parser_options['filter_badwords'] = 1;
			$post['id'] = $announcementarray['aid'];
			break;
		default: // Regular post
			global $forum, $thread, $tid;
			$oldforum = $forum;
			$post['id'] = (int)$post['pid'];
			$post['idtype'] = 'pid';
			$parser_options['allow_html'] = $forum['allowhtml'];
			$parser_options['allow_mycode'] = $forum['allowmycode'];
			$parser_options['allow_smilies'] = $forum['allowsmilies'];
			$parser_options['allow_imgcode'] = $forum['allowimgcode'];
			$parser_options['allow_videocode'] = $forum['allowvideocode'];
			$parser_options['filter_badwords'] = 1;
			break;
	}

	if(!$post['username'])
	{
		$post['username'] = $lang->guest; // htmlspecialchars_uni'd below
	}

	if($post['userusername'])
	{
		$parser_options['me_username'] = $post['userusername'];
	}
	else
	{
		$parser_options['me_username'] = $post['username'];
	}

	$post['username'] = htmlspecialchars_uni($post['username']);
	$post['userusername'] = htmlspecialchars_uni($post['userusername']);

	// Used to show the # of the post
	if(!$postcounter)
	{
		if($page > 1)
		{
			if(!$mybb->settings['postsperpage'] ||
				(int)$mybb->settings['postsperpage'] < 1)
			{
				$mybb->settings['postsperpage'] = 20;
			}

			$postcounter = $mybb->settings['postsperpage'] * ($page - 1);
		}
		else
		{
			$postcounter = 0;
		}
		$post_extra_style = 'border-top-width: 0;';
	}
	elseif($mybb->input['mode'] == 'threaded')
	{
		$post_extra_style = 'border-top-width: 0;';
	}
	else
	{
		$post_extra_style = 'margin-top: 5px;';
	}

	// Define the alternate background colour if this is the first post
	if(!$altbg)
	{
		$altbg = 'trow1';
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
	if($post['usergroup'])
	{
		$usergroup = usergroup_permissions($post['usergroup']);
	}
	else
	{
		$usergroup = usergroup_permissions(1);
	}
	// Fetch display group data.
	$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");
	if(!$post['displaygroup'])
	{
		$post['displaygroup'] = $post['usergroup'];
	}
	$displaygroup = usergroup_displaygroup($post['displaygroup']);
	if(is_array($displaygroup))
	{
		$usergroup = array_merge($usergroup, $displaygroup);
	}

	if(!is_array($titlescache))
	{
		$cached_titles = $cache->read('usertitles');
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
	$post['groupimage'] = false;
	if(!empty($usergroup['image']))
	{
		$language = $mybb->settings['bblanguage'];
		if(!empty($mybb->user['language']))
		{
			$language = $mybb->user['language'];
		}

		$usergroup['image'] = str_replace('{lang}', $language, $usergroup['image']);
		$usergroup['image'] = str_replace('{theme}', $theme['imgdir'], $usergroup['image']);
		$post['groupimage'] = true;
	}

	$post['isguest'] = true;
	if($post['userusername'])
	{
		$post['isguest'] = false;
		// This post was made by a registered user
		$post['username'] = $post['userusername'];
		$post['profilelink_plain'] = get_profile_link($post['uid']);
		$post['username_formatted'] = format_name($post['username'], $post['usergroup'], $post['displaygroup']);
		$post['profilelink'] = build_profile_link($post['username_formatted'], $post['uid']);

		if(trim($post['usertitle']) != '')
		{
			$hascustomtitle = 1;
		}

		if($usergroup['usertitle'] != '' &&
			!$hascustomtitle)
		{
			$post['usertitle'] = $usergroup['usertitle'];
		}
		elseif(is_array($titlescache) &&
			!$usergroup['usertitle'])
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

		$post['usertitle'] = htmlspecialchars_uni($post['usertitle']);

		if($usergroup['stars'])
		{
			$post['stars'] = $usergroup['stars'];
		}

		if(empty($post['starimage']))
		{
			$post['starimage'] = $usergroup['starimage'];
		}

		if($post['starimage'] && isset($post['stars']))
		{
			// Only display stars if we have an image to use...
			$post['starimage'] = str_replace('{theme}', $theme['imgdir'], $post['starimage']);
		}

		$postnum = $post['postnum'];
		$post['postnum'] = my_number_format($post['postnum']);
		$post['threadnum'] = my_number_format($post['threadnum']);

		// Determine the status to show for the user (Online/Offline/Away)
		$timecut = TIME_NOW - $mybb->settings['wolcutoff'];
		if($post['lastactive'] > $timecut &&
			($post['invisible'] != 1 || $mybb->usergroup['canviewwolinvis'] == 1) &&
			$post['lastvisit'] != $post['lastactive'])
		{
			$post['onlinestatus'] = 'online';
		}
		else
		{
			if($post['away'] == 1 &&
				$mybb->settings['allowaway'] != 0)
			{
				$post['onlinestatus'] = 'away';
			}
			else
			{
				$post['onlinestatus'] = 'offline';
			}
		}

		$post['showavatar'] = false;
		if(isset($mybb->user['showavatars']) &&
			$mybb->user['showavatars'] != 0 ||
			$mybb->user['uid'] == 0)
		{
			$post['showavatar'] = true;
			$post['useravatar'] = format_avatar($post['avatar'], $post['avatardimensions'], $mybb->settings['postmaxavatarsize']);
		}

		$post['button_find'] = false;
		if($mybb->usergroup['cansearch'] == 1)
		{
			$post['button_find'] = true;
		}

		if($mybb->settings['enablepms'] == 1 && $post['uid'] != $mybb->user['uid'] && (($post['receivepms'] != 0 && $usergroup['canusepms'] != 0 && $mybb->usergroup['cansendpms'] == 1 && my_strpos(",".$post['ignorelist'].",", ",".$mybb->user['uid'].",") === false) || $mybb->usergroup['canoverridepm'] == 1))
		{
			$post['button_pm'] = true;
		}

		$post['button_rep'] = false;
		if($post_type != 3 &&
			$mybb->settings['enablereputation'] == 1 &&
			$mybb->settings['postrep'] == 1 &&
			$mybb->usergroup['cangivereputations'] == 1 &&
			$usergroup['usereputationsystem'] == 1 &&
			($mybb->settings['posrep'] || $mybb->settings['neurep'] || $mybb->settings['negrep']) &&
			$post['uid'] != $mybb->user['uid'] &&
			(!isset($post['visible']) || $post['visible'] == 1) &&
			(!isset($thread['visible']) || $thread['visible'] == 1))
		{
			if(!$post['pid'])
			{
				$post['pid'] = 0;
			}

			$post['button_rep'] = true;
		}

		$post['button_www'] = false;
		if($post['website'] != '' &&
			!is_member($mybb->settings['hidewebsite']) &&
			$usergroup['canchangewebsite'] == 1)
		{
			$post['button_www'] = true;
		}

		$post['button_email'] = false;
		if($post['hideemail'] != 1 && $post['uid'] != $mybb->user['uid'] && $mybb->usergroup['cansendemail'] == 1)
		{
			$post['button_email'] = true;
		}

		$post['userregdate'] = my_date($mybb->settings['regdateformat'], $post['regdate']);

		// Work out the reputation this user has (only show if not announcement)
		if($post_type != 3 &&
			$usergroup['usereputationsystem'] != 0
			&& $mybb->settings['enablereputation'] == 1)
		{
			$post['userreputation'] = get_reputation($post['reputation'], $post['uid']);
			$post['replink'] = true;
		}

		// Showing the warning level? (only show if not announcement)
		if($post_type != 3 &&
			$mybb->settings['enablewarningsystem'] != 0 &&
			$usergroup['canreceivewarnings'] != 0 &&
			($mybb->usergroup['canwarnusers'] != 0 || ($mybb->user['uid'] == $post['uid'] && $mybb->settings['canviewownwarning'] != 0)))
		{
			if($mybb->settings['maxwarningpoints'] < 1)
			{
				$mybb->settings['maxwarningpoints'] = 10;
			}

			$post['warning_level'] = round($post['warningpoints'] / $mybb->settings['maxwarningpoints'] * 100);
			if($post['warning_level'] > 100)
			{
				$post['warning_level'] = 100;
			}
			$post['warning_level'] = get_colored_warning_level($post['warning_level']);

			// If we can warn them, it's not the same person, and we're in a PM or a post.
			$post['warning_link'] = 'usercp.php';
			if($mybb->usergroup['canwarnusers'] != 0 &&
				$post['uid'] != $mybb->user['uid'] &&
				($post_type == 0 || $post_type == 2))
			{
				$post['can_warn'] = true;
				$post['warning_link'] = "warnings.php?uid={$post['uid']}";
			}
		}

		if($post_type != 3 &&
			$post_type != 1 &&
			purgespammer_show($post['postnum'], $post['usergroup'], $post['uid']))
		{
			$post['button_purgespammer'] = true;
		}

		if(!isset($profile_fields))
		{
			$profile_fields = array();

			// Fetch profile fields to display
			$pfcache = $cache->read('profilefields');

			if(is_array($pfcache))
			{
				foreach($pfcache as $profilefield)
				{
					if($profilefield['postbit'] != 1)
					{
						continue;
					}

					$profile_fields[$profilefield['fid']] = $profilefield;
				}
			}
		}

		// Display profile fields on posts - only if field is filled in
		$post['profile_fields'] = array();
		foreach((array)$profile_fields as $field)
		{
			$fieldfid = "fid{$field['fid']}";
			if(empty($post[$fieldfid]))
			{
				continue;
			}

			$thing = explode("\n", $field['type'], '2');
			$field['type'] = trim($thing[0]);
			$field['useropts'] = explode("\n", $post[$fieldfid]);

			$field['multi'] = false;
			if(is_array($useropts) &&
				($type == 'multiselect' || $type == 'checkbox'))
			{
				$field['multi'] = true;
			}
			else
			{
				$field_parser_options = array(
					'allow_html' => $field['allowhtml'],
					'allow_mycode' => $field['allowmycode'],
					'allow_smilies' => $field['allowsmilies'],
					'allow_imgcode' => $field['allowimgcode'],
					'allow_videocode' => $field['allowvideocode'],
					'filter_badwords' => 1,
				);

				if($field['type'] == 'textarea')
				{
					$field_parser_options['me_username'] = $post['username'];
				}
				else
				{
					$field_parser_options['nl2br'] = 0;
				}

				if($mybb->user['showimages'] != 1 &&
					$mybb->user['uid'] != 0 ||
					$mybb->settings['guestimages'] != 1 &&
					$mybb->user['uid'] == 0)
				{
					$field_parser_options['allow_imgcode'] = 0;
				}

				$field['value'] = $parser->parse_message($post[$fieldfid], $field_parser_options);
			}

			$post['profile_fields'][] = $field;
		}
		// Message was posted by a guest or an unknown user
	}
	else
	{
		$post['profilelink'] = format_name($post['username'], 1);

		if($usergroup['usertitle'])
		{
			$post['usertitle'] = $usergroup['usertitle'];
		}
		else
		{
			$post['usertitle'] = $lang->guest;
		}

		$post['usertitle'] = htmlspecialchars_uni($post['usertitle']);

		$usergroup['title'] = $lang->na;

		$post['userregdate'] = $lang->na;
		$post['postnum'] = $lang->na;
		$post['onlinestatus'] = '';
		$post['signature'] = '';
		$post['button_email'] = false;
		$post['button_www'] = false;
		$post['button_pm'] = false;
		$post['button_find'] = false;
		$post['replink'] = false;
	}

	$post['button_quote'] = false;
	$post['button_report'] = false;

	// For private messages, fetch the reply/forward/delete icons
	if($post_type == 2 &&
		$post['pmid'])
	{
		global $replyall;

		$post['replyall'] = $replyall;
		$post['is_pm'] = true;
	}

	$post['editedmsg'] = false;
	$post['is_announcement'] = false;
	if(!$post_type)
	{
		if(!isset($forumpermissions))
		{
			$forumpermissions = forum_permissions($fid);
		}

		// Figure out if we need to show an "edited by" message
		if($post['edituid'] != 0 && $post['edittime'] != 0 && $post['editusername'] != "" && ($mybb->settings['showeditedby'] != 0 && $usergroup['cancp'] == 0 && !is_moderator($post['fid'], "", $post['uid']) || ($mybb->settings['showeditedbyadmin'] != 0 && ($usergroup['cancp'] == 1 || is_moderator($post['fid'], "", $post['uid'])))))
		{
			$post['editedmsg'] = true;

			$post['editdate'] = my_date('relative', $post['edittime']);
			$post['editnote'] = $lang->sprintf($lang->postbit_edited, $post['editdate']);
			$post['editedprofilelink'] = build_profile_link($post['editusername'], $post['edituid']);
			if($post['editreason'] != '')
			{
				$post['editreason'] = $parser->parse_badwords($post['editreason']);
			}
		}

		$time = TIME_NOW;
		if((is_moderator($fid, 'caneditposts') || ($forumpermissions['caneditposts'] == 1 && $mybb->user['uid'] == $post['uid'] && $thread['closed'] != 1 && ($mybb->usergroup['edittimelimit'] == 0 || $mybb->usergroup['edittimelimit'] != 0 && $post['dateline'] > ($time - ($mybb->usergroup['edittimelimit'] * 60))))) &&
			$mybb->user['uid'] != 0)
		{
			$post['can_edit'] = true;
		}

		// Quick Delete button
		$can_delete_thread = $can_delete_post = 0;
		if($mybb->user['uid'] == $post['uid'] &&
			$thread['closed'] == 0)
		{
			if($forumpermissions['candeletethreads'] == 1 &&
				$postcounter == 1)
			{
				$can_delete_thread = 1;
			}
			elseif($forumpermissions['candeleteposts'] == 1 &&
				$postcounter != 1)
			{
				$can_delete_post = 1;
			}
		}

		if($mybb->user['uid'] != 0)
		{
			$post['quick_delete_display'] = '';
			$post['quick_restore_display'] = 'none';
			if($post['visible'] == -1)
			{
				$post['quick_delete_display'] = 'none';
				$post['quick_restore_display'] = '';
			}

			if((is_moderator($fid, 'candeleteposts') || is_moderator($fid, 'cansoftdeleteposts') || $can_delete_post == 1) &&
				$postcounter != 1)
			{
				$post['quick_delete'] = true;
				$lang->postbit_quick_delete = $lang->postbit_qdelete_post;
			}
			elseif((is_moderator($fid, 'candeletethreads') || is_moderator($fid, 'cansoftdeletethreads') || $can_delete_thread == 1) &&
				$postcounter == 1)
			{
				$post['quick_delete'] = true;
				$lang->postbit_quick_delete = $lang->postbit_qdelete_thread;
			}

			// Restore Post
			if(is_moderator($fid, 'canrestoreposts') &&
				$postcounter != 1)
			{
				$post['quick_restore'] = true;
				$lang->postbit_quick_restore = $lang->postbit_qrestore_post;
				// Restore Thread
			}
			elseif(is_moderator($fid, 'canrestorethreads') &&
				$postcounter == 1)
			{
				$post['quick_restore'] = true;
				$lang->postbit_quick_restore = $lang->postbit_qrestore_thread;
			}
		}

		if(!isset($ismod))
		{
			$ismod = is_moderator($fid);
		}
		$post['ismod'] = $ismod;

		// Inline moderation stuff
		$post['inlinechecked'] = false;
		if($ismod)
		{
			if(isset($mybb->cookies[$inlinecookie]) &&
				my_strpos($mybb->cookies[$inlinecookie], '|'.$post['pid'].'|') !== false)
			{
				$post['inlinechecked'] = true;
				$inlinecount++;
			}

			if($post['visible'] == 0)
			{
				$invisiblepost = 1;
			}
		}
		$post['postlink'] = get_post_link($post['pid'], $post['tid']);
		$post['number'] = my_number_format($postcounter);

		global $forum, $thread;

		if($forum['open'] != 0 &&
			($thread['closed'] != 1 ||
				is_moderator($forum['fid'], 'canpostclosedthreads')) &&
			($thread['uid'] == $mybb->user['uid'] || $forumpermissions['canonlyreplyownthreads'] != 1))
		{
			$post['button_quote'] = true;
		}

		if($forumpermissions['canpostreplys'] != 0 && ($thread['uid'] == $mybb->user['uid'] || empty($forumpermissions['canonlyreplyownthreads'])) && ($thread['closed'] != 1 || is_moderator($fid, "canpostclosedthreads")) && $mybb->settings['multiquote'] != 0 && $forum['open'] != 0 && !$post_type)
		{
			$post['button_multiquote'] = true;
		}

		$skip_report = my_unserialize($post['reporters']);
		if(is_array($skip_report))
		{
			$skip_report[] = 0;
		}
		else
		{
			$skip_report = array(0);
		}

		$reportable = user_permissions($post['uid']);
		if(!in_array($mybb->user['uid'], $skip_report) && !empty($reportable['canbereported']))
		{
			$post['button_report'] = true;
		}
		// announcement
	}
	elseif($post_type == 3)
	{
		if($mybb->usergroup['canmodcp'] == 1 &&
			$mybb->usergroup['canmanageannounce'] == 1 &&
			is_moderator($fid, 'canmanageannouncements'))
		{
			$post['quick_delete'] = $post['can_edit'] = $post['is_announcement'] = true;
		}
	}

	$post['iplogged'] = '';
	$show_ips = $mybb->settings['logip'];
	$post['ip'] = my_inet_ntop($db->unescape_binary($post['ipaddress']));

	// Show post IP addresses... PMs now can have IP addresses too as of 1.8!
	if($post_type == 2)
	{
		$show_ips = $mybb->settings['showpmip'];
	}

	if(!$post_type ||
		$post_type == 2)
	{
		if($show_ips != "no" && !empty($post['ipaddress']))
		{
			$post['iphide'] = $post['ipshow'] = false;

			if($show_ips == 'show')
			{
				$post['ipshow'] = true;
			}
			else if($show_ips == 'hide' &&
				(is_moderator($fid, 'canviewips') || $mybb->usergroup['issupermod']))
			{
				$post['iphide'] = true;
				$post['action'] = 'getip';
				$post['javascript'] = 'getIP';
				if($post_type == 2)
				{
					$post['action'] = 'getpmip';
					$post['javascript'] = 'getPMIP';
				}
			}
		}
	}

	$post['poststatus'] = false;
	if(!$post_type &&
		$post['visible'] != 1)
	{
		if(is_moderator($fid, 'canviewdeleted') &&
			$postcounter != 1 &&
			$post['visible'] == -1)
		{
			$post['status_type'] = $lang->postbit_post_deleted;
		}
		else if(is_moderator($fid, 'canviewunapprove') &&
			$postcounter != 1 &&
			$post['visible'] == 0)
		{
			$post['status_type'] = $lang->postbit_post_unapproved;
		}
		else if(is_moderator($fid, 'canviewdeleted') &&
			$postcounter == 1 &&
			$post['visible'] == -1)
		{
			$post['status_type'] = $lang->postbit_thread_deleted;
		}
		else if(is_moderator($fid, 'canviewunapprove') &&
			$postcounter == 1 &&
			$post['visible'] == 0)
		{
			$post['status_type'] = $lang->postbit_thread_unapproved;
		}

		$post['poststatus'] = true;
	}

	if(isset($post['smilieoff']) &&
		$post['smilieoff'] == 1)
	{
		$parser_options['allow_smilies'] = 0;
	}

	if($mybb->user['showimages'] != 1 &&
		$mybb->settings['guestimages'] != 1 &&
		$mybb->user['uid'] == 0)
	{
		$parser_options['allow_imgcode'] = 0;
	}

	if($mybb->user['showvideos'] != 1 &&
		$mybb->settings['guestvideos'] != 1 &&
		$mybb->user['uid'] == 0)
	{
		$parser_options['allow_videocode'] = 0;
	}

	// If we have incoming search terms to highlight - get it done.
	if(!empty($mybb->input['highlight']))
	{
		$parser_options['highlight'] = $mybb->input['highlight'];
		$post['subject'] = $parser->highlight_message($post['subject'], $parser_options['highlight']);
	}

	$post['message'] = $parser->parse_message($post['message'], $parser_options);

	if($mybb->settings['enableattachments'] != 0)
	{
		$attached = get_post_attachments($post['id'], $post);
	}

	$post['showbcc'] = false;
	if($post_type == 2)
	{
		if(count($post['bcc_recipients']) > 0)
		{
			$post['showbcc'] = true;
			$post['bcc_recipients'] = implode(', ', $post['bcc_recipients']);
		}

		if(count($post['to_recipients']) > 0)
		{
			$post['to_recipients'] = implode($lang->comma, $post['to_recipients']);
		}
		else
		{
			$post['to_recipients'] = $lang->nobody;
		}
	}

	$post['showsig'] = false;
	if(isset($post['includesig']) &&
		$post['includesig'] != 0 &&
		$post['username'] &&
		$post['signature'] != '' &&
		($mybb->user['uid'] == 0 || $mybb->user['showsigs'] != 0) &&
		($post['suspendsignature'] == 0 || $post['suspendsignature'] == 1 && $post['suspendsigtime'] != 0 && $post['suspendsigtime'] < TIME_NOW) &&
		$usergroup['canusesig'] == 1 &&
		($usergroup['canusesigxposts'] == 0 || $usergroup['canusesigxposts'] > 0 && $postnum > $usergroup['canusesigxposts']) &&
		!is_member($mybb->settings['hidesignatures']))
	{
		$sig_parser = array(
			'allow_html' => $mybb->settings['sightml'],
			'allow_mycode' => $mybb->settings['sigmycode'],
			'allow_smilies' => $mybb->settings['sigsmilies'],
			'allow_imgcode' => $mybb->settings['sigimgcode'],
			'me_username' => $parser_options['me_username'],
			'filter_badwords' => 1
		);

		if($usergroup['signofollow'])
		{
			$sig_parser['nofollow_on'] = 1;
		}

		if($mybb->user['showimages'] != 1 &&
			$mybb->user['uid'] != 0 ||
			$mybb->settings['guestimages'] != 1 &&
			$mybb->user['uid'] == 0)
		{
			$sig_parser['allow_imgcode'] = 0;
		}

		$post['signature'] = $parser->parse_message($post['signature'], $sig_parser);
		$post['showsig'] = true;
	}

	$icon_cache = $cache->read('posticons');

	$post['showicon'] = false;
	if(isset($post['icon']) &&
		$post['icon'] > 0 &&
		$icon_cache[$post['icon']])
	{
		$post['showicon'] = true;

		$icon = $icon_cache[$post['icon']];
		$icon['path'] = str_replace('{theme}', $theme['imgdir'], $icon['path']);
	}

	$post['visibility'] = '';
	switch($post_type)
	{
		case 1: // Message preview
			$post = $plugins->run_hooks('postbit_prev', $post);
			break;
		case 2: // Private message
			$post = $plugins->run_hooks('postbit_pm', $post);
			break;
		case 3: // Announcement
			$post = $plugins->run_hooks('postbit_announcement', $post);
			break;
		default: // Regular post
			$post = $plugins->run_hooks('postbit', $post);

			if(!isset($ignored_users))
			{
				$ignored_users = array();
				if($mybb->user['uid'] > 0 &&
					$mybb->user['ignorelist'] != '')
				{
					$ignore_list = explode(',', $mybb->user['ignorelist']);
					foreach($ignore_list as $uid)
					{
						$ignored_users[$uid] = 1;
					}
				}
			}

			// Has this post been deleted but can be viewed? Hide this post
			$post['isdeleted'] = false;
			if($post['visible'] == -1 &&
				is_moderator($fid, 'canviewdeleted'))
			{
				$post['isdeleted'] = true;

				$post['deleted_message'] = $lang->sprintf($lang->postbit_deleted_post_user, $post['username']);
				$post['visibility'] = 'display: none;';
			}

			// Is the user (not moderator) logged in and have unapproved posts?
			if($mybb->user['uid'] && $post['visible'] == 0 && $post['uid'] == $mybb->user['uid'] && !is_moderator($fid, "canviewunapprove"))
			{
				$ignored_message = $lang->sprintf($lang->postbit_post_under_moderation, $post['username']);
				eval("\$ignore_bit = \"".$templates->get("postbit_ignored")."\";");
				$post_visibility = "display: none;";
			}

			// Is this author on the ignore list of the current user? Hide this post
			$post['isignored'] = false;
			if(is_array($ignored_users) &&
				$post['uid'] != 0 &&
				isset($ignored_users[$post['uid']]) &&
				$ignored_users[$post['uid']] == 1 &&
				empty($deleted_bit))
			{
				$post['isignored'] = true;

				$post['ignored_message'] = $lang->sprintf($lang->postbit_currently_ignoring_user, $post['username']);
				$post['visibility'] = 'display: none;';
			}
			break;
	}

	if($post_type == 0 && $forumpermissions['canviewdeletionnotice'] == 1 && $post['visible'] == -1 && !is_moderator($fid, "canviewdeleted"))
	{
		$postbit = \MyBB\template('postbit/postbit_deleted_member.twig', [
			'post' => $post,
		]);
	}
	else
	{
		$postbit = \MyBB\template('postbit/postbit.twig', [
			'post' => $post,
			'attached' => $attached,
			'icon' => $icon,
			'usergroup' => $usergroup,
		]);
	}

	$GLOBALS['post'] = '';

	return $postbit;
}

/**
 * Fetch the attachments for a specific post and parse inline [attachment=id] code.
 * Note: assumes you have $attachcache, an array of attachments set up.
 *
 * @param int $id The ID of the item.
 * @param array $post The post or item passed by reference.
 */
function get_post_attachments($id, &$post)
{
	global $attachcache, $mybb, $forumpermissions, $lang;

	$attached['validationcount'] = 0;

	if(!isset($forumpermissions))
	{
		$forumpermissions = forum_permissions($post['fid']);
	}

	$post['hasattachments'] = (isset($attachcache[$id]) && is_array($attachcache[$id]));
	if(!$post['hasattachments'])
	{
		return $attached;
	}

	foreach($attachcache[$id] as $aid => $attachment)
	{
		if(!$attachment['visible'])
		{
			$attached['validationcount']++;
			continue;
		}

		$ext = get_extension($attachment['filename']);
		$isimage = in_array($ext, ['jpeg', 'jpg', 'gif', 'bmp', 'png']);

		$attachment['filesize'] = get_friendly_size($attachment['filesize']);
		$attachment['icon'] = get_attachment_icon($ext);
		$attachment['downloads'] = my_number_format($attachment['downloads']);

		$attachment['date'] = my_date('normal', $attachment['dateuploaded'] ?? $attachment['dateline']);

		// Support for [attachment=id] code
		if(stripos($post['message'], "[attachment={$attachment['aid']}]") !== false)
		{
			// Show as thumbnail IF image is big && thumbnail exists && setting=='thumb'
			// Show as full size image IF setting=='fullsize' || (image is small && permissions allow)
			// Show as download for all other cases
			if($attachment['thumbnail'] != 'SMALL' &&
				$attachment['thumbnail'] != '' &&
				$mybb->settings['attachthumbnails'] == 'yes')
			{
				$attbit = \MyBB\template('postbit/postbit_attached_thumbnail.twig', [
					'thumb' => $attachment,
				]);
			}
			elseif((($attachment['thumbnail'] == 'SMALL' && $forumpermissions['candlattachments'] == 1) || $mybb->settings['attachthumbnails'] == 'no') &&
				$isimage)
			{
				$attbit = \MyBB\template('postbit/postbit_attached_image.twig', [
					'image' => $attachment,
				]);
			}
			else
			{
				$attbit = \MyBB\template('postbit/postbit_attached_image.twig', [
					'attachment' => $attachment,
				]);
			}

			$post['message'] = preg_replace("#\[attachment={$attachment['aid']}]#si", $attbit, $post['message']);
		}
		else
		{
			// Show as thumbnail IF image is big && thumbnail exists && setting=='thumb'
			// Show as full size image IF setting=='fullsize' || (image is small && permissions allow)
			// Show as download for all other cases
			if($attachment['thumbnail'] != 'SMALL' &&
				$attachment['thumbnail'] != '' &&
				$mybb->settings['attachthumbnails'] == 'yes')
			{
				$attached['thumbs'][] = $attachment;
			}
			elseif((($attachment['thumbnail'] == 'SMALL' && $forumpermissions['candlattachments'] == 1) || $mybb->settings['attachthumbnails'] == 'no') &&
				$isimage)
			{
				$attached['images'][] = $attachment;
			}
			else
			{
				$attached['attachments'][] = $attachment;
			}
		}
	}

	if($attached['validationcount'] > 0 &&
		is_moderator($post['fid'], 'canviewunapprove'))
	{
		$post['showunapproved'] = true;

		$lang->postbit_unapproved_attachment_language = $lang->postbit_unapproved_attachment;
		if($attached['validationcount'] > 1)
		{
			$lang->postbit_unapproved_attachment_language = $lang->sprintf($lang->postbit_unapproved_attachments, $attached['validationcount']);
		}
	}

	return $attached;
}

/**
 * Returns bytes count from human readable string
 * Used to parse ini_get human-readable values to int
 *
 * @param string $val Human-readable value
 */
function return_bytes($val) {
	$val = trim($val);
	if ($val == "")
	{
		return 0;
	}

	$last = strtolower($val[strlen($val)-1]);

	$val = intval($val);

	switch($last)
	{
		case 'g':
			$val *= 1024;
		case 'm':
			$val *= 1024;
		case 'k':
			$val *= 1024;
	}

	return $val;
}

/**
 * Detects whether an attachment removal/approval/unapproval
 * submit button was pressed (without triggering an AJAX request)
 * and sets inputs accordingly (as for an AJAX request).
 */
function detect_attachmentact()
{
	global $mybb;

	foreach($mybb->input as $key => $val)
	{
		if(strpos($key, 'rem_') === 0)
		{
			$mybb->input['attachmentaid'] = (int)substr($key, 4);
			$mybb->input['attachmentact'] = 'remove';
			break;
		}
		elseif(strpos($key, 'approveattach_') === 0)
		{
			$mybb->input['attachmentaid'] = (int)substr($key, 14);
			$mybb->input['attachmentact'] = 'approve';
			break;
		}
		elseif(strpos($key, 'unapproveattach_') === 0)
		{
			$mybb->input['attachmentaid'] = (int)substr($key, 16);
			$mybb->input['attachmentact'] = 'unapprove';
			break;
		}
	}
}
