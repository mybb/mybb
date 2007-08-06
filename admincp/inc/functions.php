<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
 *
 * $Id$
 */


function log_admin_action()
{
	global $db, $mybb;

	$log_function = $page->active_module."_admin_log_data";
	if(function_exists($log_function))
	{
		$data = $log_function();
	}
	if(!is_array($data))
	{
		$data = array();
	}

	$log_entry = array(
		"uid" => $mybb->user['uid'],
		"ipaddress" => $db->escape_string(get_ip()),
		"dateline" => TIME_NOW,
		"module" => $page->active_module,
		"action" => $page->active_action,
		"data" => $db->escape_string(serialize($data))
	);

	$db->insert_query("adminlog2", $log_entry);
}

function admin_redirect($url)
{
	if(!headers_sent())
	{
		header("Location: $url");
	}
	else
	{
		echo "<meta http-equiv=\"refresh\" content=\"0; url={$url}\">";
	}
	exit;
}

function update_admin_session($name, $value)
{
	global $db, $admin_session;
	
	$admin_session['data'][$name] = $value;
	$updated_session = array(
		"data" => $db->escape_string(@serialize($admin_session['data']))
	);
	$db->update_query("adminsessions", $updated_session, "sid='{$admin_session['sid']}'");
}

function flash_message($message, $type='')
{
	$flash = array('message' => $message, 'type' => $type);
	update_admin_session('flash_message', $flash);
}

function draw_admin_pagination($page, $per_page, $total_items, $url)
{
	if($total_items <= $per_page)
	{
		return;
	}

	$pages = ceil($total_items / $per_page);

	$pagination = "<div class=\"pagination\"><span class=\"pages\">Pages: </span>\n";

	if($page > 1)
	{
		$prev = $page-1;
		$prev_page = fetch_page_url($url, $prev);
		$pagination .= "<a href=\"$prev_page\" class=\"pagination_previous\">&laquo; Previous</a> \n";
	}

	// Maximum number of "page bits" to show
	if(!$mybb->settings['maxmultipagelinks'])
	{
		$mybb->settings['maxmultipagelinks'] = 5;
	}

	$from = $page-floor($mybb->settings['maxmultipagelinks']/2);
	$to = $page+floor($mybb->settings['maxmultipagelinks']/2);

	if($from <= 0)
	{
		$from = 1;
		$to = $from+$max_links-1;
	}

	if($to > $pages)
	{
		$to = $pages;
		$from = $pages-$max_links+1;
		if($from <= 0)
		{
			$from = 1;
		}
	}

	if($to == 0)
	{
		$to = $pages;
	}


	if($from > 2)
	{
		$first = fetch_page_url($url, 1);
		$pagination .= "<a href=\"{$page_url}\" title=\"Page 1\" class=\"pagination_first\">1</a> ... ";
	}

	for($i = $from; $i <= $to; ++$i)
	{
		$page_url = fetch_page_url($url, $i);
		if($page == $i)
		{
			$pagination .= "<span class=\"pagination_current\">{$i}</span> \n";
		}
		else
		{
			$pagination .= "<a href=\"{$page_url}\" title=\"Page {$i}\">{$i}</a> \n";
		}
	}

	if($to < $pages)
	{
		$last = fetch_page_url($url, $pages);
		$pagination .= "... <a href=\"{$page_url}\" title=\"Page {$pages}\" class=\"pagination_last\">{$pages}</a>";
	}

	if($page < $pages)
	{
		$next = $page+1;
		$next_page = fetch_page_url($url, $next);
		$pagination .= " <a href=\"$next_page\" class=\"pagination_next\">Next &raquo;</a>\n";
	}
	$pagination .= "</div>\n";
	return $pagination;
}


function make_parent_list($fid, $navsep=",")
{
	global $pforumcache, $db;
	
	if(!$pforumcache)
	{
		$query = $db->simple_select("forums", "name, fid, pid", "", array("order_by" => "disporder, pid"));
		while($forum = $db->fetch_array($query))
		{
			$pforumcache[$forum['fid']][$forum['pid']] = $forum;
		}
	}
	
	reset($pforumcache);
	reset($pforumcache[$fid]);
	
	foreach($pforumcache[$fid] as $key => $forum)
	{
		if($fid == $forum['fid'])
		{
			if($pforumcache[$forum['pid']])
			{
				$navigation = make_parent_list($forum['pid'], $navsep).$navigation;
			}
			
			if($navigation)
			{
				$navigation .= $navsep;
			}
			$navigation .= $forum['fid'];
		}
	}
	return $navigation;
}

function is_super_admin($uid)
{
	global $config;
	
	$config['super_admins'] = str_replace(" ", "", $config['super_admins']);
	if(my_strpos(",{$config['super_admins']},", ",{$uid},") === false)
	{
		return false;
	}
	else
	{
		return true;
	}
}


function check_admin_permissions($action)
{
	global $mybb, $page, $lang;
	
	if($mybb->admin['permissions'][$action['module']][$action['action']] != 1)
	{
		$page->output_header($lang->access_denied);
		$page->add_breadcrumb_item($lang->access_denied, "index.php?".SID."&amp;module=home/index");
		$page->output_error("<b>{$lang->access_denied}</b><ul>{$lang->access_denied_desc}</ul>");
		$page->output_footer();
		exit;
	}
}

function get_admin_permissions($get_uid="", $get_gid="")
{
	global $db, $mybb;
	
	// Set UID and GID if none
	$uid = $get_uid;
	$gid = $get_gid;
	
	$gid_array = array();
	
	if($uid === "")
	{
		$uid = $mybb->user['uid'];
	}
	
	if(!$gid)
	{
		// Prepare user's groups since the group isn't specified
		$gid_array[] = (-1) * intval($mybb->user['usergroup']);
		$additional_groups = explode(',', $mybb->user['additionalgroups']);
		// Make sure gids are negative
		foreach($additional_groups as $g)
		{
			$gid_array[] = (-1) * abs($g);
		}
	}
	else
	{
		// Group is specified
		// Make sure gid is negative
		$gid = (-1) * abs($gid);
	}

	// What are we trying to find?
	if($get_gid && !$get_uid)
	{
		// A group only
		
		$options = array(
			"order_by" => "uid",
			"order_dir" => "ASC",
			"limit" => "1"
		);

		$query = $db->simple_select("adminoptions", "permissions", "(uid='$gid' OR uid='0') AND permissions != ''", $options);
		return unserialize($db->fetch_field($query, "permissions"));
	}
	else
	{
		// A user and/or group
		
		$options = array(
			"order_by" => "uid",
			"order_dir" => "DESC"
		);
		
		// Prepare user's groups into SQL format
		$group_sql = '';
		foreach($gid_array as $gid)
		{
			$group_sql .= " OR uid='{$gid}'";
		}
		
		$perms_group = array();
		$query = $db->simple_select("adminoptions", "permissions, uid", "(uid='{$uid}'{$group_sql}) AND permissions != ''", $options);
		while($perm = $db->fetch_array($query))
		{
			$perm['permissions'] = unserialize($perm['permissions']);
			
			// Sorting out which permission is which
			if($perm['uid'] > 0)
			{
				$perms_user = $perm;
				return $perms_user['permissions'];
			}
			elseif($perm['uid'] < 0)
			{
				$perms_group[] = $perm['permissions'];
			}
			else
			{
				$perms_def = $perm['permissions'];
			}
		}

		// Figure out group permissions...ugh.
		foreach($perms_group as $gperms)
		{
			if(!isset($final_group_perms))
			{
				// Use this group as the base for admin group permissions
				$final_group_perms = $gperms;
				continue;
			}
			
			// Loop through each specific permission to find the highest permission
			foreach($gperms as $perm_name => $perm_value)
			{
				if($final_group_perms[$perm_name] != '1' && $perm_value == '1')
				{
					$final_group_perms[$perm_name] = '1';
				}
			}
		}

		// Send specific user, or group permissions before default.
		// If user's permission are explicitly set, they've already been returned above.
		if(isset($final_group_perms))
		{
			return $final_group_perms;
		}
		else
		{
			return $perms_def;
		}
	}
}

function fetch_iconv_encoding($mysql_encoding)
{
    $mysql_encoding = explode("_", $mysql_encoding);
    switch($mysql_encoding[0])
    {
        case "utf8":
            return "utf-8";
			break;
        case "latin1":
            return "iso-8859-1";
			break;
		default:
			return $mysql_encoding[0];
    }
}

?>