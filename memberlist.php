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

$templatelist = "memberlist,memberlist_search,memberlist_user,memberlist_user_groupimage,memberlist_user_avatar,memberlist_user_userstar,memberlist_search_contact_field,memberlist_referrals,memberlist_referrals_bit";
$templatelist .= ",multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start,memberlist_error,memberlist_orderarrow";

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

	$contact_fields = array();
	foreach(array('skype', 'google', 'yahoo', 'icq') as $field)
	{
		$contact_fields[$field] = '';
		$settingkey = 'allow'.$field.'field';

		if($mybb->settings[$settingkey] != '' && is_member($mybb->settings[$settingkey], array('usergroup' => $mybb->usergroup['usergroup'], 'additionalgroups' => $mybb->usergroup['additionalgroups'])))
		{
			$tmpl = 'memberlist_search_'.$field;

			$lang_string = 'search_'.$field;
			$lang_string = $lang->{$lang_string};

			$bgcolors[$field] = alt_trow();
			eval('$contact_fields[\''.$field.'\'] = "'.$templates->get('memberlist_search_contact_field').'";');
		}
	}

	if($mybb->settings['usereferrals'] == 1)
	{
		eval("\$referrals_option = \"".$templates->get("memberlist_referrals_option")."\";");
	}

	eval("\$search_page = \"".$templates->get("memberlist_search")."\";");
	output_page($search_page);
}
else
{
	$colspan = 6;
	$search_url = '';

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
		case "threadnum":
			$sort_field = "u.threadnum";
			break;
		case "referrals":
			if($mybb->settings['usereferrals'] == 1)
			{
				$sort_field = "u.referrals";
			}
			else
			{
				$sort_field = "u.username";
			}
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
		$sortordernow = "ascending";
		$oppsort = $lang->desc;
		$oppsortnext = "descending";
		$mybb->input['order'] = "ascending";
	}
	else
	{
		$sort_order = "DESC";
		$sortordernow = "descending";
		$oppsort = $lang->asc;
		$oppsortnext = "ascending";
		$mybb->input['order'] = "descending";
	}
	$order_check[$mybb->input['order']] = " checked=\"checked\"";

	// Incoming results per page?
	$mybb->input['perpage'] = $mybb->get_input('perpage', MyBB::INPUT_INT);
	if($mybb->input['perpage'] > 0 && $mybb->input['perpage'] <= 500)
	{
		$per_page = $mybb->input['perpage'];
	}
	else if($mybb->settings['membersperpage'])
	{
		$per_page = $mybb->input['perpage'] = (int)$mybb->settings['membersperpage'];
	}
	else
	{
		$per_page = $mybb->input['perpage'] = 20;
	}

	$search_query = '1=1';
	$search_url = "";

	switch($db->type)
	{
		// PostgreSQL's LIKE is case sensitive
		case "pgsql":
			$like = "ILIKE";
			break;
		default:
			$like = "LIKE";
	}

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
			$search_query .= " AND u.username {$like} '".$db->escape_string_like($letter)."%'";
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
			$search_query .= " AND u.username {$like} '".$username_like_query."%'";
			$search_url .= "&username_match=begins";
		}
		// Just contains
		else if($mybb->input['username_match'] == "contains")
		{
			$search_query .= " AND u.username {$like} '%".$username_like_query."%'";
			$search_url .= "&username_match=contains";
		}
		// Exact
		else
		{
			$search_query .= " AND u.username='{$username_like_query}'";
		}

		$search_url .= "&username=".urlencode($search_username);
	}

	// Website contains
	$mybb->input['website'] = trim($mybb->get_input('website'));
	$search_website = htmlspecialchars_uni($mybb->input['website']);
	if(trim($mybb->input['website']))
	{
		$search_query .= " AND u.website {$like} '%".$db->escape_string_like($mybb->input['website'])."%'";
		$search_url .= "&website=".urlencode($mybb->input['website']);
	}

	// Search by contact field input
	foreach(array('icq', 'google', 'skype', 'yahoo') as $cfield)
	{
		$csetting = 'allow'.$cfield.'field';
		$mybb->input[$cfield] = trim($mybb->get_input($cfield));
		if($mybb->input[$cfield] && $mybb->settings[$csetting] != '')
		{
			if($mybb->settings[$csetting] != -1)
			{
				$gids = explode(',', (string)$mybb->settings[$csetting]);

				$search_query .= " AND (";
				$or = '';
				foreach($gids as $gid)
				{
					$gid = (int)$gid;
					$search_query .= $or.'u.usergroup=\''.$gid.'\'';
					switch($db->type)
					{
						case 'pgsql':
						case 'sqlite':
							$search_query .= " OR ','||u.additionalgroups||',' LIKE '%,{$gid},%'";
							break;
						default:
							$search_query .= " OR CONCAT(',',u.additionalgroups,',') LIKE '%,{$gid},%'";
							break;
					}
					$or = ' OR ';
				}
				$search_query .= ")";
			}
			if($cfield == 'icq')
			{
				$search_query .= " AND u.{$cfield} LIKE '%".(int)$mybb->input[$cfield]."%'";
			}
			else
			{
				$search_query .= " AND u.{$cfield} {$like} '%".$db->escape_string_like($mybb->input[$cfield])."%'";
			}
			$search_url .= "&{$cfield}=".urlencode($mybb->input[$cfield]);
		}
	}

	$usergroups_cache = $cache->read('usergroups');

	$group = array();
	foreach($usergroups_cache as $gid => $groupcache)
	{
		if($groupcache['showmemberlist'] == 0)
		{
			$group[] = (int)$gid;
		}
	}

	if(is_array($group) && !empty($group))
	{
		$hiddengroup = implode(',', $group);

		$search_query .= " AND u.usergroup NOT IN ({$hiddengroup})";

		foreach($group as $hidegid)
		{
			switch($db->type)
			{
				case "pgsql":
				case "sqlite":
					$search_query .= " AND ','||u.additionalgroups||',' NOT LIKE '%,{$hidegid},%'";
					break;
				default:
					$search_query .= " AND CONCAT(',',u.additionalgroups,',') NOT LIKE '%,{$hidegid},%'";
					break;
			}
		}
	}
  
	$sorturl = htmlspecialchars_uni("memberlist.php?perpage={$mybb->input['perpage']}{$search_url}");
	$search_url = htmlspecialchars_uni("memberlist.php?sort={$mybb->input['sort']}&order={$mybb->input['order']}&perpage={$mybb->input['perpage']}{$search_url}");

	$plugins->run_hooks('memberlist_intermediate');

	$query = $db->simple_select("users u", "COUNT(*) AS users", "{$search_query}");
	$num_users = $db->fetch_field($query, "users");

	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	if($page && $page > 0)
	{
		$start = ($page - 1) * $per_page;
		$pages = ceil($num_users / $per_page);
		if($page > $pages)
		{
			$start = 0;
			$page = 1;
		}
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$sort = htmlspecialchars_uni($mybb->input['sort']);
	eval("\$orderarrow['{$sort}'] = \"".$templates->get("memberlist_orderarrow")."\";");

	// Referral?
	if($mybb->settings['usereferrals'] == 1)
	{
		$colspan = 7;
		eval("\$referral_header = \"".$templates->get("memberlist_referrals")."\";");
	}

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

		$alt_bg = alt_trow();

		$user['username'] = format_name(htmlspecialchars_uni($user['username']), $user['usergroup'], $user['displaygroup']);

		$user['profilelink'] = build_profile_link($user['username'], $user['uid']);

		// Get the display usergroup
		if($user['usergroup'])
		{
			$usergroup = usergroup_permissions($user['usergroup']);
		}
		else
		{
			$usergroup = usergroup_permissions(1);
		}

		$displaygroupfields = array("title", "description", "namestyle", "usertitle", "stars", "starimage", "image");

		if(!$user['displaygroup'])
		{
			$user['displaygroup'] = $user['usergroup'];
		}

		$display_group = usergroup_displaygroup($user['displaygroup']);
		if(is_array($display_group))
		{
			$usergroup = array_merge($usergroup, $display_group);
		}

		// Build referral?
		if($mybb->settings['usereferrals'] == 1)
		{
			$referral_count = (int) $user['referrals'];
			if($referral_count > 0)
			{
				$uid = (int) $user['uid'];
				eval("\$user['referrals'] = \"".$templates->get('member_referrals_link')."\";");
			}

			eval("\$referral_bit = \"".$templates->get("memberlist_referrals_bit")."\";");
			eval("\$referrals_option = \"".$templates->get("memberlist_referrals_option")."\";");
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
		
		$user['usertitle'] = htmlspecialchars_uni($user['usertitle']);

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
				eval("\$user['userstars'] .= \"".$templates->get("memberlist_user_userstar", 1, 0)."\";");
			}
		}

		if($user['userstars'] && $usergroup['groupimage'])
		{
			$user['userstars'] = "<br />".$user['userstars'];
		}

		// Show avatar
		$useravatar = format_avatar($user['avatar'], $user['avatardimensions'], my_strtolower($mybb->settings['memberlistmaxavatarsize']));
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
		$user['threadnum'] = my_number_format($user['threadnum']);
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