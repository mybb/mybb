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
define('THIS_SCRIPT', 'memberlist.php');

$templatelist = "memberlist,memberlist_search,memberlist_user,memberlist_user_groupimage,memberlist_user_avatar,multipage_prevpage";
$templatelist .= ",multipage_nextpage,multipage_page_current,multipage_page,multipage_start,multipage_end,multipage,memberlist_referrals,memberlist_referrals_bit,memberlist_error";
require_once "./global.php";

// Load global language phrases
$lang->load("memberlist");

if($mybb->settings['enablememberlist'] == 0)
{
	error($lang->memberlist_disabled);
}

$plugins->run_hooks("memberlist_start");

add_breadcrumb($lang->nav_memberlist, "memberlist.php");

if($mybb->usergroup['canviewmemberlist'] == 0)
{
	error_no_permission();
}

// Showing advanced search page?
if($mybb->get_input('action') == "search")
{
	$plugins->run_hooks("memberlist_search");
	add_breadcrumb($lang->nav_memberlist_search);
	eval("\$search_page = \"".$templates->get("memberlist_search")."\";");
	output_page($search_page);
}
else
{
	$colspan = 5;
	$search_url = '';

	// Referral?
	if($mybb->settings['usereferrals'] == 1)
	{
		$colspan = 6;
		eval("\$referral_header = \"".$templates->get("memberlist_referrals")."\";");
	}

	// Incoming sort field?
	if(isset($mybb->input['sort']))
	{
		$mybb->input['sort'] = strtolower($mybb->get_input('sort'));
	}
	else
	{
		$mybb->input['sort'] = $mybb->settings['default_memberlist_sortby'];
	}

	$sort_selected = array(
		'regdate' => '',
		'lastvisit' => '',
		'reputation' => '',
		'postnum' => '',
		'referrals' => '',
		'username' => ''
	);

	switch($mybb->input['sort'])
	{
		case "regdate":
			$sort_field = "u.regdate";
			break;
		case "lastvisit":
			$sort_field = "u.lastactive";
			break;
		case "reputation":
			$sort_field = "u.reputation";
			break;
		case "postnum":
			$sort_field = "u.postnum";
			break;
		case "referrals":
			$sort_field = "u.referrals";
			break;
		default:
			$sort_field = "u.username";
			$mybb->input['sort'] = 'username';
			break;
	}
	$sort_selected[$mybb->input['sort']] = " selected=\"selected\"";

	// Incoming sort order?
	if(isset($mybb->input['order']))
	{
		$mybb->input['order'] = strtolower($mybb->input['order']);
	}
	else
	{
		$mybb->input['order'] = strtolower($mybb->settings['default_memberlist_order']);
	}

	$order_check = array('ascending' => '', 'descending' => '');
	if($mybb->input['order'] == "ascending" || (!$mybb->input['order'] && $mybb->input['sort'] == 'username'))
	{
		$sort_order = "ASC";
		$mybb->input['order'] = "ascending";
	}
	else
	{
		$sort_order = "DESC";
		$mybb->input['order'] = "descending";
	}
	$order_check[$mybb->input['order']] = " checked=\"checked\"";

	// Incoming results per page?
	$mybb->input['perpage'] = intval($mybb->get_input('perpage'));
	if($mybb->input['perpage'] > 0 && $mybb->input['perpage'] <= 500)
	{
		$per_page = $mybb->input['perpage'];
	}
	else if($mybb->settings['membersperpage'])
	{
		$per_page = $mybb->input['perpage'] = intval($mybb->settings['membersperpage']);
	}
	else
	{
		$per_page = $mybb->input['perpage'] = 20;
	}

	$search_query = '1=1';
	$search_url = "memberlist.php?sort={$mybb->input['sort']}&order={$mybb->input['order']}&perpage={$mybb->input['perpage']}";

	// Limiting results to a certain letter
	if(isset($mybb->input['letter']))
	{
		$letter = chr(ord($mybb->get_input('letter')));
		if($mybb->input['letter'] == -1)
		{
			$search_query .= " AND u.username NOT REGEXP('[a-zA-Z]')";
		}
		else if(strlen($letter) == 1)
		{
			$search_query .= " AND u.username LIKE '".$db->escape_string_like($letter)."%'";
		}
		$search_url .= "&letter={$letter}";
	}

	// Searching for a matching username
	$search_username = htmlspecialchars_uni(trim($mybb->get_input('username')));
	if($search_username != '')
	{
		$username_like_query = $db->escape_string_like($search_username);

		// Name begins with
		if($mybb->input['username_match'] == "begins")
		{
			$search_query .= " AND u.username LIKE '".$username_like_query."%'";
			$search_url .= "&username_match=begins";
		}
		// Just contains
		else
		{
			$search_query .= " AND u.username LIKE '%".$username_like_query."%'";
		}

		$search_url .= "&username=".urlencode($search_username);
	}

	// Website contains
	$mybb->input['website'] = trim($mybb->get_input('website'));
	$search_website = htmlspecialchars_uni($mybb->input['website']);
	if(trim($mybb->input['website']))
	{
		$search_query .= " AND u.website LIKE '%".$db->escape_string_like($mybb->input['website'])."%'";
		$search_url .= "&website=".urlencode($mybb->input['website']);
	}

	// AIM Identity
	$mybb->input['aim'] = trim($mybb->get_input('aim'));
	if($mybb->input['aim'])
	{
		$search_query .= " AND u.aim LIKE '%".$db->escape_string_like($mybb->input['aim'])."%'";
		$search_url .= "&aim=".urlencode($mybb->input['aim']);
	}

	// ICQ Number
	$mybb->input['icq'] = trim($mybb->get_input('icq'));
	if($mybb->input['icq'])
	{
		$search_query .= " AND u.icq LIKE '%".$db->escape_string_like($mybb->input['icq'])."%'";
		$search_url .= "&icq=".urlencode($mybb->input['icq']);
	}

	// Google Talk address
	$mybb->input['google'] = trim($mybb->get_input('google'));
	if($mybb->input['google'])
	{
		$search_query .= " AND u.google LIKE '%".$db->escape_string_like($mybb->input['google'])."%'";
		$search_url .= "&google=".urlencode($mybb->input['google']);
	}

	// Skype address
	$mybb->input['skype'] = trim($mybb->get_input('skype'));
	if($mybb->input['skype'])
	{
		$search_query .= " AND u.skype LIKE '%".$db->escape_string_like($mybb->input['skype'])."%'";
		$search_url .= "&skype=".urlencode($mybb->input['skype']);
	}

	// Yahoo! Messenger address
	$mybb->input['yahoo'] = trim($mybb->get_input('yahoo'));
	if($mybb->input['yahoo'])
	{
		$search_query .= " AND u.yahoo LIKE '%".$db->escape_string_like($mybb->input['yahoo'])."%'";
		$search_url .= "&yahoo=".urlencode($mybb->input['yahoo']);
	}

	$usergroups_cache = $cache->read('usergroups');

	$group = array();
	foreach($usergroups_cache as $gid => $groupcache)
	{
		if($groupcache['showmemberlist'] == 0)
		{
			$group[] = $gid;
		}
	}

	if(is_array($group) && !empty($group))
	{
		$hiddengroup = implode(',', $group);

		$search_query .= " AND u.usergroup NOT IN ($hiddengroup)";
	}

	$query = $db->simple_select("users u", "COUNT(*) AS users", "{$search_query}");
	$num_users = $db->fetch_field($query, "users");

	$page = $mybb->get_input('page', 1);
	if($page && $page > 0)
	{
		$start = ($page - 1) * $per_page;
	}
	else
	{
		$start = 0;
		$page = 1;
	}
	$search_url = htmlspecialchars_uni($search_url);
	$multipage = multipage($num_users, $per_page, $page, $search_url);

	// Cache a few things
	$usertitles = $cache->read('usertitles');
	$usertitles_cache = array();
	foreach($usertitles as $usertitle)
	{
		$usertitles_cache[$usertitle['posts']] = $usertitle;
	}
	$users = '';
	$query = $db->query("
		SELECT u.*, f.*
		FROM ".TABLE_PREFIX."users u
		LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
		WHERE {$search_query}
		ORDER BY {$sort_field} {$sort_order}
		LIMIT {$start}, {$per_page}
	");
	while($user = $db->fetch_array($query))
	{
		$user = $plugins->run_hooks("memberlist_user", $user);
		if(!$user['username'])
		{
			continue;
		}

		$alt_bg = alt_trow();

		$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);

		$user['profilelink'] = build_profile_link($user['username'], $user['uid']);

		// Get the display usergroup
		if(empty($user['displaygroup']))
		{
			$user['displaygroup'] = $user['usergroup'];
		}
		$usergroup = $usergroups_cache[$user['displaygroup']];

		// Build referral?
		if($mybb->settings['usereferrals'] == 1)
		{
			eval("\$referral_bit = \"".$templates->get("memberlist_referrals_bit")."\";");
		}

		$usergroup['groupimage'] = '';
		// Work out the usergroup/title stuff
		if(!empty($usergroup['image']))
		{
			if(!empty($mybb->user['language']))
			{
				$language = $mybb->user['language'];
			}
			else
			{
				$language = $mybb->settings['bblanguage'];
			}
			$usergroup['image'] = str_replace("{lang}", $language, $usergroup['image']);
			$usergroup['image'] = str_replace("{theme}", $theme['imgdir'], $usergroup['image']);
			eval("\$usergroup['groupimage'] = \"".$templates->get("memberlist_user_groupimage")."\";");
		}

		$has_custom_title = 0;
		if(trim($user['usertitle']) != "")
		{
			$has_custom_title = 1;
		}

		if($usergroup['usertitle'] != "" && !$has_custom_title)
		{
			$user['usertitle'] = $usergroup['usertitle'];
		}
		elseif(is_array($usertitles_cache) && !$usergroup['usertitle'])
		{
			foreach($usertitles_cache as $posts => $titleinfo)
			{
				if($user['postnum'] >= $posts)
				{
					if(!$has_custom_title)
					{
						$user['usertitle'] = $titleinfo['title'];
					}
					$user['stars'] = $titleinfo['stars'];
					$user['starimage'] = $titleinfo['starimage'];
					break;
				}
			}
		}

		if(!empty($usergroup['stars']))
		{
			$user['stars'] = $usergroup['stars'];
		}

		if(empty($user['starimage']))
		{
			$user['starimage'] = $usergroup['starimage'];
		}

		$user['userstars'] = '';
		if(!empty($user['starimage']))
		{
			// Only display stars if we have an image to use...
			$starimage = str_replace("{theme}", $theme['imgdir'], $user['starimage']);

			for($i = 0; $i < $user['stars']; ++$i)
			{
				$user['userstars'] .= "<img src=\"{$starimage}\" border=\"0\" alt=\"*\" />";
			}
		}

		if($user['userstars'] && $usergroup['groupimage'])
		{
			$user['userstars'] = "<br />".$user['userstars'];
		}

		// Show avatar
		$useravatar = format_avatar(htmlspecialchars_uni($user['avatar']), $user['avatardimensions'], my_strtolower($mybb->settings['memberlistmaxavatarsize']));
		eval("\$user['avatar'] = \"".$templates->get("memberlist_user_avatar")."\";");

		if($user['invisible'] == 1 && $mybb->usergroup['canviewwolinvis'] != 1 && $user['uid'] != $mybb->user['uid'])
		{
			$user['lastvisit'] = $lang->lastvisit_never;

			if($user['lastvisit'])
			{
				// We have had at least some active time, hide it instead
				$user['lastvisit'] = $lang->lastvisit_hidden;
			}
		}
		else
		{
			$user['lastvisit'] = my_date('relative', $user['lastactive']);
		}

		$user['regdate'] = my_date('relative', $user['regdate']);
		$user['postnum'] = my_number_format($user['postnum']);
		eval("\$users .= \"".$templates->get("memberlist_user")."\";");
	}

	// Do we have no results?
	if(!$users)
	{
		eval("\$users = \"".$templates->get("memberlist_error")."\";");
	}

	$plugins->run_hooks("memberlist_end");

	eval("\$memberlist = \"".$templates->get("memberlist")."\";");
	output_page($memberlist);
}
?>