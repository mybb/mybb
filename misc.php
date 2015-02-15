<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

define("IN_MYBB", 1);
define("IGNORE_CLEAN_VARS", "sid");
define('THIS_SCRIPT', 'misc.php');

$templatelist = "misc_rules_forum,misc_help_helpdoc,misc_whoposted_poster,misc_whoposted,misc_smilies_popup_smilie,misc_smilies_popup,misc_smilies_popup_empty,misc_syndication_feedurl,misc_syndication";
$templatelist .= ",misc_buddypopup,misc_buddypopup_user,misc_buddypopup_user_none,misc_buddypopup_user_online,misc_buddypopup_user_offline,misc_buddypopup_user_sendpm,misc_help_search,misc_syndication_forumlist";
$templatelist .= ",misc_smilies,misc_smilies_smilie,misc_help_section_bit,misc_help_section,misc_help,forumdisplay_password_wrongpass,forumdisplay_password,misc_helpresults,misc_helpresults_bit,misc_helpresults_noresults,multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";

// Load global language phrases
$lang->load("misc");

$plugins->run_hooks("misc_start");

$mybb->input['action'] = $mybb->get_input('action');
if($mybb->input['action'] == "dstswitch" && $mybb->request_method == "post" && $mybb->user['uid'] > 0)
{
	if($mybb->user['dstcorrection'] == 2)
	{
		if($mybb->user['dst'] == 1)
		{
			$update_array = array("dst" => 0);
		}
		else
		{
			$update_array = array("dst" => 1);
		}
	}
	$db->update_query("users", $update_array, "uid='{$mybb->user['uid']}'");
	if(!isset($mybb->input['ajax']))
	{
		redirect("index.php", $lang->dst_settings_updated);
	}
	else
	{
		echo "done";
		exit;
	}
}
elseif($mybb->input['action'] == "markread")
{
	if($mybb->user['uid'] && verify_post_check($mybb->get_input('my_post_key'), true) !== true)
	{
		// Protect our user's unread forums from CSRF
		error($lang->invalid_post_code);
	}

	if(isset($mybb->input['fid']))
	{
		$validforum = get_forum($mybb->input['fid']);
		if(!$validforum)
		{
			if(!isset($mybb->input['ajax']))
			{
				error($lang->error_invalidforum);
			}
			else
			{
				echo 0;
				exit;
			}
		}

		require_once MYBB_ROOT."/inc/functions_indicators.php";
		mark_forum_read($mybb->input['fid']);

		$plugins->run_hooks("misc_markread_forum");

		if(!isset($mybb->input['ajax']))
		{
			redirect(get_forum_link($mybb->input['fid']), $lang->redirect_markforumread);
		}
		else
		{
			echo 1;
			exit;
		}
	}
	else
	{

		$plugins->run_hooks("misc_markread_end");
		require_once MYBB_ROOT."/inc/functions_indicators.php";
		mark_all_forums_read();
		redirect("index.php", $lang->redirect_markforumsread);
	}
}
elseif($mybb->input['action'] == "clearpass")
{
	$plugins->run_hooks("misc_clearpass");

	if(isset($mybb->input['fid']))
	{
		if(!verify_post_check($mybb->get_input('my_post_key')))
		{
			error($lang->invalid_post_code);
		}

		my_unsetcookie("forumpass[".$mybb->get_input('fid', MyBB::INPUT_INT)."]");
		redirect("index.php", $lang->redirect_forumpasscleared);
	}
}
elseif($mybb->input['action'] == "rules")
{
	if(isset($mybb->input['fid']))
	{
		$plugins->run_hooks("misc_rules_start");

		$fid = $mybb->input['fid'];

		$forum = get_forum($fid);
		if(!$forum || $forum['type'] != "f" || $forum['rules'] == '')
		{
			error($lang->error_invalidforum);
		}

		$forumpermissions = forum_permissions($forum['fid']);
		if($forumpermissions['canview'] != 1)
		{
			error_no_permission();
		}

		if(!$forum['rulestitle'])
		{
			$forum['rulestitle'] = $lang->sprintf($lang->forum_rules, $forum['name']);
		}

		require_once MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser();
		$parser_options = array(
			"allow_html" => 1,
			"allow_mycode" => 1,
			"allow_smilies" => 1,
			"allow_imgcode" => 1,
			"filter_badwords" => 1
		);

		$forum['rules'] = $parser->parse_message($forum['rules'], $parser_options);

		// Make navigation
		build_forum_breadcrumb($mybb->input['fid']);
		add_breadcrumb($forum['rulestitle']);

		$plugins->run_hooks("misc_rules_end");

		eval("\$rules = \"".$templates->get("misc_rules_forum")."\";");
		output_page($rules);
	}

}
elseif($mybb->input['action'] == "do_helpsearch" && $mybb->request_method == "post")
{
	$plugins->run_hooks("misc_do_helpsearch_start");

	if($mybb->settings['helpsearch'] != 1)
	{
		error($lang->error_helpsearchdisabled);
	}

	// Check if search flood checking is enabled and user is not admin
	if($mybb->settings['searchfloodtime'] > 0 && $mybb->usergroup['cancp'] != 1)
	{
		// Fetch the time this user last searched
		$timecut = TIME_NOW-$mybb->settings['searchfloodtime'];
		$query = $db->simple_select("searchlog", "*", "uid='{$mybb->user['uid']}' AND dateline > '$timecut'", array('order_by' => "dateline", 'order_dir' => "DESC"));
		$last_search = $db->fetch_array($query);
		// Users last search was within the flood time, show the error
		if($last_search['sid'])
		{
			$remaining_time = $mybb->settings['searchfloodtime']-(TIME_NOW-$last_search['dateline']);
			if($remaining_time == 1)
			{
				$lang->error_searchflooding = $lang->sprintf($lang->error_searchflooding_1, $mybb->settings['searchfloodtime']);
			}
			else
			{
				$lang->error_searchflooding = $lang->sprintf($lang->error_searchflooding, $mybb->settings['searchfloodtime'], $remaining_time);
			}
			error($lang->error_searchflooding);
		}
	}

	if($mybb->get_input('name', MyBB::INPUT_INT) != 1 && $mybb->get_input('document', MyBB::INPUT_INT) != 1)
	{
		error($lang->error_nosearchresults);
	}

	if($mybb->get_input('document', MyBB::INPUT_INT) == 1)
	{
		$resulttype = "helpdoc";
	}
	else
	{
		$resulttype = "helpname";
	}

	$search_data = array(
		"keywords" => $mybb->get_input('keywords'),
		"name" => $mybb->get_input('name', MyBB::INPUT_INT),
		"document" => $mybb->get_input('document', MyBB::INPUT_INT),
	);

	if($db->can_search == true)
	{
		require_once MYBB_ROOT."inc/functions_search.php";

		$search_results = helpdocument_perform_search_mysql($search_data);
	}
	else
	{
		error($lang->error_no_search_support);
	}
	$sid = md5(uniqid(microtime(), true));
	$searcharray = array(
		"sid" => $db->escape_string($sid),
		"uid" => $mybb->user['uid'],
		"dateline" => TIME_NOW,
		"ipaddress" => $db->escape_binary($session->packedip),
		"threads" => '',
		"posts" => '',
		"resulttype" => $resulttype,
		"querycache" => $search_results['querycache'],
		"keywords" => $db->escape_string($mybb->get_input('keywords')),
	);
	$plugins->run_hooks("misc_do_helpsearch_process");

	$db->insert_query("searchlog", $searcharray);

	$plugins->run_hooks("misc_do_helpsearch_end");
	redirect("misc.php?action=helpresults&sid={$sid}", $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "helpresults")
{
	if($mybb->settings['helpsearch'] != 1)
	{
		error($lang->error_helpsearchdisabled);
	}

	$sid = $mybb->get_input('sid');
	$query = $db->simple_select("searchlog", "*", "sid='".$db->escape_string($sid)."' AND uid='{$mybb->user['uid']}'");
	$search = $db->fetch_array($query);

	if(!$search)
	{
		error($lang->error_invalidsearch);
	}

	$plugins->run_hooks("misc_helpresults_start");

	add_breadcrumb($lang->nav_helpdocs, "misc.php?action=help");
	add_breadcrumb($lang->search_results, "misc.php?action=helpresults&sid={$sid}");

	if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	// Work out pagination, which page we're at, as well as the limits.
	$perpage = $mybb->settings['threadsperpage'];
	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	if($page > 0)
	{
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	$end = $start + $perpage;
	$lower = $start+1;
	$upper = $end;

	// Work out if we have terms to highlight
	$highlight = "";
	if($search['keywords'])
	{
		$highlight = "&amp;highlight=".urlencode($search['keywords']);
	}

	// Do Multi Pages
	$query = $db->simple_select("helpdocs", "COUNT(*) AS total", "hid IN(".$db->escape_string($search['querycache']).")");
	$helpcount = $db->fetch_array($query);

	if($upper > $helpcount)
	{
		$upper = $helpcount;
	}
	$multipage = multipage($helpcount['total'], $perpage, $page, "misc.php?action=helpresults&amp;sid='".htmlspecialchars_uni($mybb->get_input('sid'))."'");
	$helpdoclist = '';

	require_once MYBB_ROOT."inc/class_parser.php";
	$parser = new postParser();

	$query = $db->query("
		SELECT h.*, s.enabled
		FROM ".TABLE_PREFIX."helpdocs h
		LEFT JOIN ".TABLE_PREFIX."helpsections s ON (s.sid=h.sid)
		WHERE h.hid IN(".$db->escape_string($search['querycache']).") AND h.enabled='1' AND s.enabled='1'
		LIMIT {$start}, {$perpage}
	");
	while($helpdoc = $db->fetch_array($query))
	{
		$bgcolor = alt_trow();

		if(my_strlen($helpdoc['name']) > 50)
		{
			$helpdoc['name'] = htmlspecialchars_uni(my_substr($helpdoc['name'], 0, 50)."...");
		}
		else
		{
			$helpdoc['name'] = htmlspecialchars_uni($helpdoc['name']);
		}

		$parser_options = array(
			'allow_html' => 1,
			'allow_mycode' => 0,
			'allow_smilies' => 0,
			'allow_imgcode' => 0,
			'filter_badwords' => 1
		);
		$helpdoc['helpdoc'] = strip_tags($parser->parse_message($helpdoc['document'], $parser_options));

		if(my_strlen($helpdoc['helpdoc']) > 350)
		{
			$prev = my_substr($helpdoc['helpdoc'], 0, 350)."...";
		}
		else
		{
			$prev = $helpdoc['helpdoc'];
		}

		$plugins->run_hooks("misc_helpresults_bit");

		eval("\$helpdoclist .= \"".$templates->get("misc_helpresults_bit")."\";");
	}

	if($db->num_rows($query) == 0)
	{
		eval("\$helpdoclist = \"".$templates->get("misc_helpresults_noresults")."\";");
	}

	$plugins->run_hooks("misc_helpresults_end");

	eval("\$helpresults = \"".$templates->get("misc_helpresults")."\";");
	output_page($helpresults);
}
elseif($mybb->input['action'] == "help")
{
	$lang->load("helpdocs");
	$lang->load("helpsections");
	$lang->load("customhelpdocs");
	$lang->load("customhelpsections");

	$hid = $mybb->get_input('hid', MyBB::INPUT_INT);
	add_breadcrumb($lang->nav_helpdocs, "misc.php?action=help");

	if($hid)
	{
		$query = $db->query("
			SELECT h.*, s.enabled AS section
			FROM ".TABLE_PREFIX."helpdocs h
			LEFT JOIN ".TABLE_PREFIX."helpsections s ON (s.sid=h.sid)
			WHERE h.hid='{$hid}'
		");

		$helpdoc = $db->fetch_array($query);
		if($helpdoc['section'] != 0 && $helpdoc['enabled'] != 0)
		{
			$plugins->run_hooks("misc_help_helpdoc_start");

			// If we have incoming search terms to highlight - get it done (only if not using translation).
			if(!empty($mybb->input['highlight']) && $helpdoc['usetranslation'] != 1)
			{
				require_once MYBB_ROOT."inc/class_parser.php";
				$parser = new postParser();

				$highlight = $mybb->input['highlight'];
				$helpdoc['name'] = $parser->highlight_message($helpdoc['name'], $highlight);
				$helpdoc['document'] = $parser->highlight_message($helpdoc['document'], $highlight);
			}

			if($helpdoc['usetranslation'] == 1)
			{
				$langnamevar = "d".$helpdoc['hid']."_name";
				$langdescvar = "d".$helpdoc['hid']."_desc";
				$langdocvar = "d".$helpdoc['hid']."_document";
				if($lang->$langnamevar)
				{
					$helpdoc['name'] = $lang->$langnamevar;
				}
				if($lang->$langdescvar)
				{
					$helpdoc['description'] = $lang->$langdescvar;
				}
				if($lang->$langdocvar)
				{
					$helpdoc['document'] = $lang->$langdocvar;
				}
			}

			if($helpdoc['hid'] == 3)
			{
				$helpdoc['document'] = $lang->sprintf($helpdoc['document'], $mybb->post_code);
			}

			add_breadcrumb($helpdoc['name']);

			$plugins->run_hooks("misc_help_helpdoc_end");

			eval("\$helppage = \"".$templates->get("misc_help_helpdoc")."\";");
			output_page($helppage);
		}
		else
		{
			error($lang->error_invalidhelpdoc);
		}
	}
	else
	{
		$plugins->run_hooks("misc_help_section_start");

		$query = $db->simple_select("helpdocs", "*", "", array('order_by' => 'sid, disporder'));
		while($helpdoc = $db->fetch_array($query))
		{
			$helpdocs[$helpdoc['sid']][$helpdoc['disporder']][$helpdoc['hid']] = $helpdoc;
		}
		unset($helpdoc);
		$sections = '';
		$query = $db->simple_select("helpsections", "*", "enabled != 0", array('order_by' => 'disporder'));
		while($section = $db->fetch_array($query))
		{
			if($section['usetranslation'] == 1)
			{
				$langnamevar = "s".$section['sid']."_name";
				$langdescvar = "s".$section['sid']."_desc";
				if($lang->$langnamevar)
				{
					$section['name'] = $lang->$langnamevar;
				}
				if($lang->$langdescvar)
				{
					$section['description'] = $lang->$langdescvar;
				}
			}
			if(is_array($helpdocs[$section['sid']]))
			{
				$helpbits = '';
				foreach($helpdocs[$section['sid']] as $key => $bit)
				{
					foreach($bit as $key => $helpdoc)
					{
						if($helpdoc['enabled'] != 0)
						{
							if($helpdoc['usetranslation'] == 1)
							{
								$langnamevar = "d".$helpdoc['hid'].'_name';
								$langdescvar = "d".$helpdoc['hid'].'_desc';
								if($lang->$langnamevar)
								{
									$helpdoc['name'] = $lang->$langnamevar;
								}
								if($lang->$langdescvar)
								{
									$helpdoc['description'] = $lang->$langdescvar;
								}
							}
							$altbg = alt_trow();
							eval("\$helpbits .= \"".$templates->get("misc_help_section_bit")."\";");
						}
					}
					$expdisplay = '';
					$sname = "sid_".$section['sid']."_c";
					if(isset($collapsed[$sname]) && $collapsed[$sname] == "display: show;")
					{
						$expcolimage = "collapse_collapsed.png";
						$expdisplay = "display: none;";
						$expthead = " thead_collapsed";
					}
					else
					{
						$expcolimage = "collapse.png";
						$expthead = "";
					}
				}
				eval("\$sections .= \"".$templates->get("misc_help_section")."\";");
			}
		}

		if($mybb->settings['helpsearch'] == 1)
		{
			eval("\$search = \"".$templates->get("misc_help_search")."\";");
		}

		$plugins->run_hooks("misc_help_section_end");

		eval("\$help = \"".$templates->get("misc_help")."\";");
		output_page($help);
	}
}
elseif($mybb->input['action'] == "buddypopup")
{
	$plugins->run_hooks("misc_buddypopup_start");

	if($mybb->user['uid'] == 0)
	{
		error_no_permission();
	}

	if(isset($mybb->input['removebuddy']) && verify_post_check($mybb->input['my_post_key']))
	{
		$buddies = $mybb->user['buddylist'];
		$namesarray = explode(",", $buddies);
		$mybb->input['removebuddy'] = $mybb->get_input('removebuddy', MyBB::INPUT_INT);
		if(is_array($namesarray))
		{
			foreach($namesarray as $key => $buddyid)
			{
				if($buddyid == $mybb->input['removebuddy'])
				{
					unset($namesarray[$key]);
				}
			}
			$buddylist = implode(',', $namesarray);
			$db->update_query("users", array('buddylist' => $buddylist), "uid='".$mybb->user['uid']."'");
			$mybb->user['buddylist'] = $buddylist;
		}
	}

	// Load Buddies
	$buddies = '';
	if($mybb->user['buddylist'] != "")
	{
		$buddys = array('online' => '', 'offline' => '');
		$timecut = TIME_NOW - $mybb->settings['wolcutoff'];

		$query = $db->simple_select("users", "*", "uid IN ({$mybb->user['buddylist']})", array('order_by' => 'lastactive'));

		while($buddy = $db->fetch_array($query))
		{
			$buddy_name = format_name($buddy['username'], $buddy['usergroup'], $buddy['displaygroup']);
			$profile_link = build_profile_link($buddy_name, $buddy['uid'], '_blank', 'if(window.opener) { window.opener.location = this.href; return false; }');

			$send_pm = '';
			if($mybb->user['receivepms'] != 0 && $buddy['receivepms'] != 0 && $groupscache[$buddy['usergroup']]['canusepms'] != 0)
			{
				eval("\$send_pm = \"".$templates->get("misc_buddypopup_user_sendpm")."\";");
			}

			if($buddy['lastactive'])
			{
				$last_active = $lang->sprintf($lang->last_active, my_date('relative', $buddy['lastactive']));
			}
			else
			{
				$last_active = $lang->sprintf($lang->last_active, $lang->never);
			}

			$buddy['avatar'] = format_avatar($buddy['avatar'], $buddy['avatardimensions'], '44x44');

			if($buddy['lastactive'] > $timecut && ($buddy['invisible'] == 0 || $mybb->user['usergroup'] == 4) && $buddy['lastvisit'] != $buddy['lastactive'])
			{
				$bonline_alt = alt_trow();
				eval("\$buddys['online'] .= \"".$templates->get("misc_buddypopup_user_online")."\";");
			}
			else
			{
				$boffline_alt = alt_trow();
				eval("\$buddys['offline'] .= \"".$templates->get("misc_buddypopup_user_offline")."\";");
			}
		}

		$colspan = ' colspan="2"';
		if(empty($buddys['online']))
		{
			$error = $lang->online_none;
			eval("\$buddys['online'] = \"".$templates->get("misc_buddypopup_user_none")."\";");
		}

		if(empty($buddys['offline']))
		{
			$error = $lang->offline_none;
			eval("\$buddys['offline'] = \"".$templates->get("misc_buddypopup_user_none")."\";");
		}

		eval("\$buddies = \"".$templates->get("misc_buddypopup_user")."\";");
	}
	else
	{
		// No buddies? :(
		$colspan = '';
		$error = $lang->no_buddies;
		eval("\$buddies = \"".$templates->get("misc_buddypopup_user_none")."\";");
	}

	$plugins->run_hooks("misc_buddypopup_end");

	eval("\$buddylist = \"".$templates->get("misc_buddypopup", 1, 0)."\";");
	echo $buddylist;
	exit;
}
elseif($mybb->input['action'] == "whoposted")
{
	$numposts = 0;
	$altbg = alt_trow();
	$whoposted = '';
	$tid = $mybb->get_input('tid', MyBB::INPUT_INT);
	$thread = get_thread($tid);

	// Make sure we are looking at a real thread here.
	if(!$thread)
	{
		error($lang->error_invalidthread);
	}

	if(is_moderator($thread['fid'], "canviewunapprove"))
	{
		$ismod = true;
		$show_posts = "(p.visible = '1' OR p.visible = '0')";
	}
	else
	{
		$ismod = false;
		$show_posts = "p.visible = '1'";
	}

	// Make sure we are looking at a real thread here.
	if(($thread['visible'] != 1 && $ismod == false) || ($thread['visible'] > 1 && $ismod == true))
	{
		error($lang->error_invalidthread);
	}
	// Does the thread belong to a valid forum?
	$forum = get_forum($thread['fid']);
	if(!$forum || $forum['type'] != "f")
	{
		error($lang->error_invalidforum);
	}

	// Does the user have permission to view this thread?
	$forumpermissions = forum_permissions($forum['fid']);

	if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']))
	{
		error_no_permission();
	}

	// Check if this forum is password protected and we have a valid password
	check_forum_password($forum['fid']);

	if($mybb->get_input('sort') != 'username')
	{
		$sortsql = ' ORDER BY posts DESC';
	}
	else
	{
		$sortsql = ' ORDER BY p.username ASC';
	}
	$whoposted = '';
	$query = $db->query("
		SELECT COUNT(p.pid) AS posts, p.username AS postusername, u.uid, u.username, u.usergroup, u.displaygroup
		FROM ".TABLE_PREFIX."posts p
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
		WHERE tid='".$tid."' AND $show_posts
		GROUP BY u.uid, p.username, u.uid, u.username, u.usergroup, u.displaygroup
		".$sortsql."
	");
	while($poster = $db->fetch_array($query))
	{
		if($poster['username'] == '')
		{
			$poster['username'] = $poster['postusername'];
		}
		$poster_name = format_name($poster['username'], $poster['usergroup'], $poster['displaygroup']);
		if($poster['uid'])
		{
			$onclick = "opener.location.href='".get_profile_link($poster['uid'])."'; return false;";
		}
		$profile_link = build_profile_link($poster_name, $poster['uid'], '_blank', $onclick);
		$numposts += $poster['posts'];
		eval("\$whoposted .= \"".$templates->get("misc_whoposted_poster")."\";");
		$altbg = alt_trow();
	}
	$numposts = my_number_format($numposts);
	$poster['posts'] = my_number_format($poster['posts']);
	eval("\$whop = \"".$templates->get("misc_whoposted", 1, 0)."\";");
	echo $whop;
	exit;
}
elseif($mybb->input['action'] == "smilies")
{
	$smilies = '';
	if(!empty($mybb->input['popup']) && !empty($mybb->input['editor']))
	{ // make small popup list of smilies
		$editor = preg_replace('#([^a-zA-Z0-9_-]+)#', '', $mybb->get_input('editor'));
		$e = 1;
		$class = "trow1";
		$smilies = "<tr>";
		$smilies_cache = $cache->read("smilies");
		if(is_array($smilies_cache))
		{
			$extra_class = ' smilie_pointer';
			foreach($smilies_cache as $smilie)
			{
				$smilie['image'] = str_replace("{theme}", $theme['imgdir'], $smilie['image']);
				$smilie['image'] = htmlspecialchars_uni($mybb->get_asset_url($smilie['image']));
				$smilie['name'] = htmlspecialchars_uni($smilie['name']);				
				
				// Only show the first text to replace in the box
				$temp = explode("\n", $smilie['find']); // use temporary variable for php 5.3 compatibility
				$smilie['find'] = $temp[0];

				$smilie['find'] = htmlspecialchars_uni($smilie['find']);
				$smilie_insert = str_replace(array('\\', "'"), array('\\\\', "\'"), $smilie['find']);

				$onclick = " onclick=\"MyBBEditor.insertText(' $smilie_insert ');\"";
				eval('$smilie_image = "'.$templates->get('smilie', 1, 0).'";');
				eval("\$smilies .= \"".$templates->get("misc_smilies_popup_smilie")."\";");
				if($e == 2)
				{
					$smilies .= "</tr><tr>";
					$e = 1;
					$class = alt_trow();
				}
				else
				{
					$e = 2;
				}
			}
		}
		if($e == 2)
		{
			eval("\$smilies .= \"".$templates->get("misc_smilies_popup_empty")."\";");
		}
		eval("\$smiliespage = \"".$templates->get("misc_smilies_popup", 1, 0)."\";");
		output_page($smiliespage);
	}
	else
	{
		add_breadcrumb($lang->nav_smilies);
		$class = "trow1";
		$smilies_cache = $cache->read("smilies");
		if(is_array($smilies_cache))
		{
			$extra_class = $onclick = '';
			foreach($smilies_cache as $smilie)
			{
				$smilie['image'] = str_replace("{theme}", $theme['imgdir'], $smilie['image']);
				$smilie['image'] = htmlspecialchars_uni($mybb->get_asset_url($smilie['image']));
				$smilie['name'] = htmlspecialchars_uni($smilie['name']);				
				
				$smilie['find'] = nl2br(htmlspecialchars_uni($smilie['find']));
				eval('$smilie_image = "'.$templates->get('smilie').'";');
				eval("\$smilies .= \"".$templates->get("misc_smilies_smilie")."\";");
				$class = alt_trow();
			}
		}
		eval("\$smiliespage = \"".$templates->get("misc_smilies")."\";");
		output_page($smiliespage);
	}
}
elseif($mybb->input['action'] == "imcenter")
{
	$mybb->input['imtype'] = $mybb->get_input('imtype');
	if($mybb->input['imtype'] != "aim" && $mybb->input['imtype'] != "skype" && $mybb->input['imtype'] != "yahoo")
	{
		error($lang->error_invalidimtype);
	}
	$uid = $mybb->get_input('uid', MyBB::INPUT_INT);
	$user = get_user($uid);

	if(!$user)
	{
		error($lang->error_invaliduser);
	}

	if(empty($user[$mybb->input['imtype']]))
	{
		error($lang->error_invalidimtype);
	}

	$settingkey = 'allow'.$mybb->input['imtype'].'field';
	if(!is_member($mybb->settings[$settingkey], $user))
	{
		error_no_permission();
	}

	// Build IM navigation bar
	$navigationbar = $navsep = $imtype = $imtype_lang = '';
	if($user['aim'])
	{
		$imtype = "aim";
		$imtype_lang = $lang->aol_im;
		eval("\$navigationbar .= \"".$templates->get("misc_imcenter_nav")."\";");
		$navsep = ' - ';
	}
	if($user['skype'])
	{
		$imtype = "skype";
		$imtype_lang = $lang->skype;
		eval("\$navigationbar .= \"".$templates->get("misc_imcenter_nav")."\";");
		$navsep = ' - ';
	}
	if($user['yahoo'])
	{
		$imtype = "yahoo";
		$imtype_lang = $lang->yahoo_im;
		eval("\$navigationbar .= \"".$templates->get("misc_imcenter_nav")."\";");
	}
	
	$user['skype'] = htmlspecialchars_uni($user['skype']);
	$user['yahoo'] = htmlspecialchars_uni($user['yahoo']);
	$user['aim'] = htmlspecialchars_uni($user['aim']);

	$lang->chat_on_skype = $lang->sprintf($lang->chat_on_skype, $user['username']);
	$lang->call_on_skype = $lang->sprintf($lang->call_on_skype, $user['username']);
	$lang->send_y_message = $lang->sprintf($lang->send_y_message, $user['username']);
	$lang->view_y_profile = $lang->sprintf($lang->view_y_profile, $user['username']);

	$imtemplate = "misc_imcenter_".$mybb->input['imtype'];
	eval("\$imcenter = \"".$templates->get($imtemplate, 1, 0)."\";");
	echo $imcenter;
	exit;
}
elseif($mybb->input['action'] == "syndication")
{
	$plugins->run_hooks("misc_syndication_start");

	$fid = $mybb->get_input('fid', MyBB::INPUT_INT);
	$version = $mybb->get_input('version');
	$limit = $mybb->get_input('limit', MyBB::INPUT_INT);
	$forums = $mybb->get_input('forums', MyBB::INPUT_ARRAY);
	$limit = 15;
	$feedurl = '';
	$add = false;

	add_breadcrumb($lang->nav_syndication);
	$unviewable = get_unviewable_forums();
	$inactiveforums = get_inactive_forums();
	$unexp1 = explode(',', $unviewable);
	$unexp2 = explode(',', $inactiveforums);
	$unexp = array_merge($unexp1, $unexp2);
	
	if(is_array($forums))
	{
		foreach($unexp as $fid)
		{
			$unview[$fid] = true;
		}

		$syndicate = '';
		$comma = '';
		$all = false;
		foreach($forums as $fid)
		{
			if($fid == "all")
			{
				$all = true;
				break;
			}
			elseif(is_numeric($fid))
			{
				if(!isset($unview[$fid]))
				{
					$syndicate .= $comma.$fid;
					$comma = ",";
					$flist[$fid] = true;
				}
			}
		}
		$url = $mybb->settings['bburl']."/syndication.php";
		if(!$all)
		{
			$url .= "?fid=$syndicate";
			$add = true;
		}

		// If the version is not RSS2.0, set the type to Atom1.0.
		if($version != "rss2.0")
		{
			if(!$add)
			{
				$url .= "?";
			}
			else
			{
				$url .= "&";
			}
			$url .= "type=atom1.0";
			$add = true;
		}
		if((int)$limit > 0)
		{
			if($limit > 100)
			{
				$limit = 100;
			}
			if(!$add)
			{
				$url .= "?";
			}
			else
			{
				$url .= "&";
			}
			if(is_numeric($limit))
			{
				$url .= "limit=$limit";
			}
		}
		eval("\$feedurl = \"".$templates->get("misc_syndication_feedurl")."\";");
	}
	unset($GLOBALS['forumcache']);

	// If there is no version in the input, check the default (RSS2.0).
	if($version == "atom1.0")
	{
		$atom1check = "checked=\"checked\"";
		$rss2check = '';
	}
	else
	{
		$atom1check = '';
		$rss2check = "checked=\"checked\"";
	}
	$forumselect = makesyndicateforums();

	$plugins->run_hooks("misc_syndication_end");

	eval("\$syndication = \"".$templates->get("misc_syndication")."\";");
	output_page($syndication);
}
elseif($mybb->input['action'] == "clearcookies")
{
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("misc_clearcookies");

	$remove_cookies = array('mybbuser', 'mybb[announcements]', 'mybb[lastvisit]', 'mybb[lastactive]', 'collapsed', 'mybb[forumread]', 'mybb[threadsread]', 'mybbadmin',
							'mybblang', 'mybbtheme', 'multiquote', 'mybb[readallforums]', 'coppauser', 'coppadob', 'mybb[referrer]');

	foreach($remove_cookies as $name)
	{
		my_unsetcookie($name);
	}
	redirect("index.php", $lang->redirect_cookiescleared);
}

/**
 * Build a list of forums for RSS multiselect.
 *
 * @param int Parent forum ID.
 * @param unknown_type deprecated
 * @param boolean Whether to add selected attribute or not.
 * @param string HTML for the depth of the forum.
 * @return string HTML of the list of forums for CSS.
 */
function makesyndicateforums($pid="0", $selitem="", $addselect="1", $depth="")
{
	global $db, $forumcache, $permissioncache, $mybb, $forumlist, $forumlistbits, $flist, $lang, $unexp, $templates;

	$pid = (int)$pid;
	$forumlist = '';

	if(!is_array($forumcache))
	{
		// Get Forums
		$query = $db->simple_select("forums", "*", "linkto = '' AND active!=0", array('order_by' => 'pid, disporder'));
		while($forum = $db->fetch_array($query))
		{
			$forumcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}
	}

	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}

	if(is_array($forumcache[$pid]))
	{
		foreach($forumcache[$pid] as $key => $main)
		{
			foreach($main as $key => $forum)
			{
				$perms = $permissioncache[$forum['fid']];
				if($perms['canview'] == 1 || $mybb->settings['hideprivateforums'] == 0)
				{
					$optionselected = '';
					if(isset($flist[$forum['fid']]))
					{
						$optionselected = 'selected="selected"';
						$selecteddone = "1";
					}

					if($forum['password'] == '' && !in_array($forum['fid'], $unexp) || $forum['password'] && isset($mybb->cookies['forumpass'][$forum['fid']]) && $mybb->cookies['forumpass'][$forum['fid']] == md5($mybb->user['uid'].$forum['password']))
					{
						$forumlistbits .= "<option value=\"{$forum['fid']}\" $optionselected>$depth {$forum['name']}</option>\n";
					}

					if(!empty($forumcache[$forum['fid']]))
					{
						$newdepth = $depth."&nbsp;&nbsp;&nbsp;&nbsp;";
						$forumlistbits .= makesyndicateforums($forum['fid'], '', 0, $newdepth);
					}
				}
			}
		}
	}
	
	if($addselect)
	{
		$addsel = '';
		if(empty($selecteddone))
		{
			$addsel = ' selected="selected"';
		}

		eval("\$forumlist = \"".$templates->get("misc_syndication_forumlist")."\";");
	}

	return $forumlist;
}
