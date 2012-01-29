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

$plugins->add_hook('modcp_start', 'mods_modcp');
$plugins->add_hook('global_end', 'mods_global');
$plugins->add_hook("member_profile_end", "mods_profile");
$plugins->add_hook("build_friendly_wol_location_end", "mods_online");

function mods_info()
{
	return array(
		"name"			=> "Mods Site",
		"description"	=> "The official Mods Site for MyBB!",
		"website"		=> "http://mybb.com",
		"author"		=> "MyBB Group",
		"authorsite"	=> "http://mybb.com",
		"version"		=> "1.0",
		"guid" 			=> "",
		"compatibility" => "*"
	);
}

function mods_install()
{
	require_once MYBB_ROOT."inc/plugins/mods/Mods.php";
	
	$mods = Mods::getInstance();
	$mods->install();
}

function mods_is_installed()
{
	require_once MYBB_ROOT."inc/plugins/mods/Mods.php";
	
	$mods = Mods::getInstance();
	return $mods->is_installed();
}

function mods_uninstall()
{
	require_once MYBB_ROOT."inc/plugins/mods/Mods.php";
	
	$mods = Mods::getInstance();
	$mods->uninstall();
}

function mods_activate()
{
	require_once MYBB_ROOT."inc/plugins/mods/Mods.php";
	
	$mods = Mods::getInstance();
	$mods->activate();
}

function mods_deactivate()
{
	require_once MYBB_ROOT."inc/plugins/mods/Mods.php";
	
	$mods = Mods::getInstance();
	$mods->deactivate();
}

// *************************** HOOKS *************************** //

function mods_modcp()
{
	global $mybb, $lang;
	
	if ($mybb->input['action'] != 'mods' || empty($mybb->input['modsaction']))
		return;
		
	$lang->load("mods");
	
	require_once MYBB_ROOT."inc/plugins/mods/Mods.php";
	$mods = Mods::getInstance();
		
	global $theme, $templates, $headerinclude, $header, $footer, $modcp_nav, $db;
		
	switch ($mybb->input['modsaction'])
	{
		// Manage builds page
		case 'builds':
			$per_page = 10;
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
			
			// get all projects
			$projects = $mods->projects->getAll();
			
			$where = '';
			$url = '';
			
			if ($mybb->input['waitingonly'])
			{
				$where .= 'b.waitingstable = 1';
				$url .= '&amp;waitingonly=1';
			}
			else
				$where .= '(b.waitingstable = 0 OR b.waitingstable = 1)';
				
			$query = $db->simple_select("mods_builds", "COUNT(bid) as builds", str_replace('b.', '', $where));
			$total_rows = $db->fetch_field($query, "builds");

			if ($total_rows > $per_page)
				$multipage = multipage($total_rows, $per_page, $mybb->input['page'], "modcp.php?action=mods&modsaction=builds".$url);
		
			// Browse builds
			$query = $db->query("
				SELECT p.name as project_name, b.*
				FROM ".TABLE_PREFIX."mods_builds b
				LEFT JOIN ".TABLE_PREFIX."mods_projects p ON (p.pid=b.pid) WHERE {$where}
				ORDER BY b.dateuploaded DESC LIMIT {$start}, {$per_page}
			");
			
			while ($build = $db->fetch_array($query))
			{
				$bgcolor = alt_trow();
				
				if ($build['waitingstable'] == 1)
					$build['number'] = $build['number']." (".htmlspecialchars_uni($build['status']).") <small>".$lang->mods_modcp_waitingstable_notice."</small>";
				else
					$build['number'] = $build['number']." (".htmlspecialchars_uni($build['status']).")";
				
				$build['project'] = htmlspecialchars_uni($build['project_name']);
				
				if ($build['status'] == 'dev')
				{
					$build['options'] = '<a href="'.$mybb->settings['bburl'].'/modcp.php?action=mods&amp;modsaction=changestatus&amp;status=stable&amp;id='.$build['bid'].'&amp;my_post_key='.$mybb->post_code.'">'.$lang->mods_modcp_builds_set_stable.'</a>';
				}
				else {
					$build['options'] = '<a href="'.$mybb->settings['bburl'].'/modcp.php?action=mods&amp;modsaction=changestatus&amp;status=dev&amp;id='.$build['bid'].'&amp;my_post_key='.$mybb->post_code.'">'.$lang->mods_modcp_builds_set_dev.'</a>';
				}
				
				$build['options'] .= ' - <a href="'.$mybb->settings['bburl'].'/modcp.php?action=mods&amp;modsaction=delete&amp;id='.$build['bid'].'&amp;my_post_key='.$mybb->post_code.'">'.$lang->mods_delete.'</a>';
				
				$build['dateuploaded'] = my_date($mybb->settings['dateformat'], $build['dateuploaded'], '', false).', '.my_date($mybb->settings['timeformat'], $build['dateuploaded']);

				eval("\$builds .= \"".$templates->get("mods_modcp_builds_build")."\";");
			}
			
			if (empty($builds))
			{
				$colspan = 4;
				eval("\$builds = \"".$templates->get("mods_modcp_empty")."\";");
			}
		
			eval("\$page = \"".$templates->get("mods_modcp_builds")."\";");
			output_page($page);
		break;
		
		// delete build
		case 'delete':
			// Verify incoming POST request
			verify_post_check($mybb->input['my_post_key']);
			
			// Get Build
			$bid = (int)$mybb->input['id'];
			$build = $mods->projects->builds->getByID($bid);
			if (empty($build))
			{
				error($lang->mods_invalid_bid);
			}
			
			if ($mybb->request_method == "post")
			{
				$update_array = array('approved' => 0);
		
				// delete build
				$db->delete_query('mods_builds', 'bid=\''.$bid.'\'');
				
				redirect("modcp.php?action=mods&amp;modsaction=builds", $lang->mods_modcp_build_deleted);
			}
			
			$title = $lang->mods_modcp_delete_build;
			$modsaction = 'delete';
			$id = $bid;
			
			// Get author 
			$query = $db->simple_select('users', 'username', 'uid=\''.intval($build['uid']).'\'');
			$build['username'] = $db->fetch_field($query, 'username');
			
			$build['author'] = build_profile_link(htmlspecialchars_uni($build['username']), $build['uid']);
			
			$notice = $lang->mods_modcp_confirm_delete_build."<br /><br /><strong>".$lang->mods_build.":</strong> #".intval($build['number'])."<br /><strong>".$lang->mods_author.":</strong> ".$build['author'];
			
			eval("\$page = \"".$templates->get("mods_modcp_do_action")."\";");
			output_page($page);
		break;
		
		case 'changestatus':
			// Verify incoming POST request
			verify_post_check($mybb->input['my_post_key']);
			
			// Get Build
			$bid = (int)$mybb->input['id'];
			$build = $mods->projects->builds->getByID($bid);
			if (empty($build))
			{
				error($lang->mods_invalid_bid);
			}
			
			switch ($mybb->input['status'])
			{
				case 'stable':
					$status = 'stable';
				break;
				
				case 'dev':
					$status = 'dev';
				break;
				
				default:
					error();
			}
			
			if ($mybb->request_method == "post")
			{
				// Change waitingstable to 0 regardless of the new status being dev or stable
				$update_array = array('status' => $status, 'waitingstable' => 0);
		
				// Update the build
				$mods->projects->builds->updateByID($update_array, $bid);
		
				redirect("modcp.php?action=mods&amp;modsaction=builds", $lang->mods_modcp_build_status_updated);
			}
			
			$title = $lang->mods_modcp_change_status;
			$modsaction = 'changestatus&amp;status='.$status;
			$id = $bid;
			
			// Get project name 
			$query = $db->simple_select('mods_projects', 'name', 'pid=\''.intval($build['pid']).'\'');
			$build['project'] = htmlspecialchars_uni($db->fetch_field($query, 'name'));
			
			if ($status == 'dev')
				$status = $lang->mods_development;
			else
				$status = $lang->mods_stable;
			
			$notice = $lang->mods_modcp_confirm_change."<br /><br /><strong>".$lang->mods_number.":</strong> #".htmlspecialchars_uni($build['number'])."<br /><strong>".$lang->mods_project.":</strong> <a href=\"{$mybb->settings['bburl']}/mods.php?action=view&amp;pid={$build['pid']}\">".htmlspecialchars_uni($build['project'])."</a><br /><strong>".$lang->mods_new_status.":</strong> ".$status;
			
			eval("\$page = \"".$templates->get("mods_modcp_do_action")."\";");
			output_page($page);
		break;
		
		// Manage projects
		case 'projects':
			$per_page = 10;
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
			
			$url = '';
			$where = '';
			
			if ($mybb->input['unapprovedonly'])
			{
				if ($where != '')
					$where .= ' AND ';
				
				$where .= 'p.approved = 0';
				$url .= '&amp;unapprovedonly=1';
			}
			else {
				if ($where != '')
					$where .= ' AND ';
				
				$where .= '(p.approved = 0 OR p.approved = 1)';
			}
		
			$query = $db->simple_select("mods_projects", "COUNT(pid) as projects", str_replace('p.', '', $where));
			$total_rows = $db->fetch_field($query, "projects");

			if ($total_rows > $per_page)
				$multipage = multipage($total_rows, $per_page, $mybb->input['page'], "modcp.php?action=mods&modsaction=projects".$url);
		
			// get all categories
			$cats = $mods->categories->getAll();
		
			// Browse projects
			$query = $db->query("
				SELECT u.*, p.*
				FROM ".TABLE_PREFIX."mods_projects p
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE {$where}
				ORDER BY p.submitted DESC LIMIT {$start}, {$per_page}
			");
			
			while ($project = $db->fetch_array($query))
			{
				$bgcolor = alt_trow();
				
				if ($project['approved'] == 0)
					$project['name'] = htmlspecialchars_uni($project['name'])." <small>".$lang->mods_modcp_unapproved_notice."</small>";
				else
					$project['name'] = htmlspecialchars_uni($project['name']);
					
				$project['author'] = build_profile_link(htmlspecialchars_uni($project['username']), $project['uid']);
				
				$cat = $cats[$project['cid']];
				
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
				
				$project['category'] = $parent.' / '.htmlspecialchars_uni($cat['name']);
				
				if ($project['approved'] == 1)
				{
					$project['options'] = '<a href="'.$mybb->settings['bburl'].'/modcp.php?action=mods&amp;modsaction=unapprove&amp;id='.$project['pid'].'&amp;my_post_key='.$mybb->post_code.'">'.$lang->mods_modcp_projects_unapprove.'</a>';
				}
				else {
					$project['options'] = '<a href="'.$mybb->settings['bburl'].'/modcp.php?action=mods&amp;modsaction=approve&amp;id='.$project['pid'].'&amp;my_post_key='.$mybb->post_code.'">'.$lang->mods_modcp_projects_approve.'</a>';
				}
				
				$project['options'] .= ' - <a href="'.$mybb->settings['bburl'].'/modcp.php?action=mods&amp;modsaction=notes&amp;id='.$project['pid'].'&amp;my_post_key='.$mybb->post_code.'">'.$lang->mods_notes.'</a>';
				
				$project['submitted'] = my_date($mybb->settings['dateformat'], $project['submitted'], '', false).', '.my_date($mybb->settings['timeformat'], $project['submitted']);
				
				// $project['options'] .= " - <a href=\"{$mybb->settings['bburl']}/modcp.php?action=mods&amp;modsaction=delete&amp;pid={$project['pid']}&amp;my_post_key={$mybb->post_code}\">".$lang->mods_delete."</a>";
				
				eval("\$projects .= \"".$templates->get("mods_modcp_projects_project")."\";");
			}
			
			if (empty($projects))
			{
				$colspan = 4;
				eval("\$projects = \"".$templates->get("mods_modcp_empty")."\";");
			}
		
			eval("\$page = \"".$templates->get("mods_modcp_projects")."\";");
			output_page($page);
		break;
		
		case 'unapprove':
			// Verify incoming POST request
			verify_post_check($mybb->input['my_post_key']);
			
			// Get Project
			$pid = (int)$mybb->input['id'];
			$project = $mods->projects->getByID($pid);
			if (empty($project))
			{
				error($lang->mods_invalid_pid);
			}
			
			if ($mybb->request_method == "post")
			{
				$update_array = array('approved' => 0, 'notes' => $mybb->input['notes']);
		
				// Update the project
				$mods->projects->updateByID($update_array, $pid);
				
				// Get Category and update counter
				$cat = $mods->categories->getByID($project['cid']);
				if (!empty($cat))
					$mods->categories->updateByID(array('counter' => --$cat['counter']), $cat['cid']);
		
				redirect("modcp.php?action=mods&amp;modsaction=projects", $lang->mods_modcp_project_unapproved);
			}
			
			$title = $lang->mods_modcp_unapprove_project;
			$modsaction = 'unapprove';
			$id = $pid;
			
			// Get author 
			$query = $db->simple_select('users', 'username', 'uid=\''.intval($project['uid']).'\'');
			$project['username'] = $db->fetch_field($query, 'username');
	
			$project['name'] = htmlspecialchars_uni($project['name']);
			$project['notes'] = htmlspecialchars_uni($project['notes']);
			$project['author'] = build_profile_link(htmlspecialchars_uni($project['username']), $project['uid']);
			
			$notice = $lang->mods_modcp_confirm_unapprove."<br /><br /><strong>".$lang->mods_name.":</strong> ".$project['name']."<br /><strong>".$lang->mods_author.":</strong> ".$project['author'];
			
			$notice .= '<br /><br /><strong>'.$lang->mods_modcp_notes.':</strong><br /><textarea name="notes" rows="5" cols="50">'.$project['notes'].'</textarea>';
			
			eval("\$page = \"".$templates->get("mods_modcp_do_action")."\";");
			output_page($page);
			
		break;
		
		case 'approve':
			// Verify incoming POST request
			verify_post_check($mybb->input['my_post_key']);
			
			// Get Project
			$pid = (int)$mybb->input['id'];
			$project = $mods->projects->getByID($pid);
			if (empty($project))
			{
				error($lang->mods_invalid_pid);
			}
			
			if ($mybb->request_method == "post")
			{
				$update_array = array('approved' => 1, 'notes' => $mybb->input['notes']);
		
				// Update the project
				$mods->projects->updateByID($update_array, $pid);
		
				// Get Category and update counter
				$cat = $mods->categories->getByID($project['cid']);
				if (!empty($cat))
					$mods->categories->updateByID(array('counter' => ++$cat['counter']), $cat['cid']);
		
				redirect("modcp.php?action=mods&amp;modsaction=projects", $lang->mods_modcp_project_approved);
			}
			
			$title = $lang->mods_modcp_approve_project;
			$modsaction = 'approve';
			$id = $pid;
			
			// Get author 
			$query = $db->simple_select('users', 'username', 'uid=\''.intval($project['uid']).'\'');
			$project['username'] = $db->fetch_field($query, 'username');
	
			$project['name'] = htmlspecialchars_uni($project['name']);
			$project['notes'] = htmlspecialchars_uni($project['notes']);
			$project['author'] = build_profile_link(htmlspecialchars_uni($project['username']), $project['uid']);
			
			$notice = $lang->mods_modcp_confirm_approve."<br /><br /><strong>".$lang->mods_name.":</strong> ".$project['name']."<br /><strong>".$lang->mods_author.":</strong> ".$project['author'];
			
			$notice .= '<br /><br /><strong>'.$lang->mods_modcp_notes.':</strong><br /><textarea name="notes" rows="5" cols="50">'.$project['notes'].'</textarea>';
			
			eval("\$page = \"".$templates->get("mods_modcp_do_action")."\";");
			output_page($page);
			
		break;
		
		case 'notes':
			// Verify incoming POST request
			verify_post_check($mybb->input['my_post_key']);
			
			// Get Project
			$pid = (int)$mybb->input['id'];
			$project = $mods->projects->getByID($pid);
			if (empty($project))
			{
				error($lang->mods_invalid_pid);
			}
			
			if ($mybb->request_method == "post")
			{
				$update_array = array('notes' => $mybb->input['notes']);
		
				// Update the project
				$mods->projects->updateByID($update_array, $pid);
		
				// Get Category and update counter
				$cat = $mods->categories->getByID($project['cid']);
				if (!empty($cat))
					$mods->categories->updateByID(array('counter' => ++$cat['counter']), $cat['cid']);
		
				redirect("modcp.php?action=mods&amp;modsaction=projects", $lang->mods_modcp_project_notes_updated);
			}
			
			$title = $lang->mods_modcp_project_notes;
			$modsaction = 'notes';
			$id = $pid;
			
			// Get author 
			$query = $db->simple_select('users', 'username', 'uid=\''.intval($project['uid']).'\'');
			$project['username'] = $db->fetch_field($query, 'username');
	
			$project['name'] = htmlspecialchars_uni($project['name']);
			$project['notes'] = htmlspecialchars_uni($project['notes']);
			$project['author'] = build_profile_link(htmlspecialchars_uni($project['username']), $project['uid']);
			
			$notice = $lang->mods_modcp_notes_edit_notice."<br /><br /><strong>".$lang->mods_name.":</strong> ".htmlspecialchars_uni($project['name'])."<br /><strong>".$lang->mods_author.":</strong> ".$project['author'];
			
			$notice .= '<br /><br /><strong>'.$lang->mods_modcp_notes.':</strong><br /><textarea name="notes" rows="5" cols="50">'.$project['notes'].'</textarea>';
			
			eval("\$page = \"".$templates->get("mods_modcp_do_action")."\";");
			output_page($page);
		break;
		
		default:
			error();
	}
	
	exit;
}

// member profile
function mods_profile()
{
	global $mybb, $db, $currency, $points, $templates, $memprofile, $lang, $uid;
	
	$lang->load("mods");
	
	// Get total number of projects the user is currently collaborating on (includes owned projects)
	$query = $db->simple_select('mods_projects', 'COUNT(*) as projects', "(CONCAT(',',collaborators,',') LIKE '%,{$uid},%' OR (CONCAT(',',testers,',') LIKE '%,{$uid},%' AND bugtracker_link='' AND bugtracking='1' AND hidden='0')) OR uid='".intval($uid)."'");
	$memprofile['projects'] = (int)$db->fetch_field($query, 'projects');
}

function mods_global()
{
	global $db, $mybb, $mods_site_builds, $lang, $header;
	
	$mods_site_builds = '';
	
	require_once MYBB_ROOT."inc/plugins/mods/Mods.php";
	$mods = Mods::getInstance();
	
	// We must be Mods Site moderators 
	if (!$mods->check_permissions($mybb->settings['mods_mods']))
		return;
		
	$lang->load("mods");
	
	$query = $db->simple_select('mods_projects', 'COUNT(*) as projects', 'approved=0');
	$projects = $db->fetch_field($query, 'projects');
	
	$query = $db->simple_select('mods_builds', 'COUNT(*) as builds', 'waitingstable=1');
	$builds = $db->fetch_field($query, 'builds');

	// If we've got builds waiting dev -> stable change and zero unapproved projects 
	if ($builds >= 1 && $projects <= 0)
	{
		if ($builds > 1)
		{
			$message = $lang->sprintf($lang->mods_waiting_stable_builds, "<a href=\"modcp.php?action=mods&amp;modsaction=builds\">{$builds}</a>");
		}
		elseif ($builds == 1)
		{
			$message = $lang->sprintf($lang->mods_waiting_stable_build, "<a href=\"modcp.php?action=mods&amp;modsaction=builds\">{$builds}</a>");
		}
	} // otherwise if we've got zero builds waiting status change but at least one project waiting approval
	elseif ($projects >= 1 && $builds <= 0)
	{
		if ($projects > 1)
		{
			$message = $lang->sprintf($lang->mods_waiting_approval_projects, "<a href=\"modcp.php?action=mods&amp;modsaction=projects\">{$projects}</a>");
		}
		elseif ($projects == 1)
		{
			$message = $lang->sprintf($lang->mods_waiting_approval_project, "<a href=\"modcp.php?action=mods&amp;modsaction=projects\">{$projects}</a>");
		}
	}
	elseif ($projects >= 1 && $builds >= 1)
	{
		$message = $lang->sprintf($lang->mods_waiting_projects_builds, "<a href=\"modcp.php?action=mods&amp;modsaction=projects\">{$projects}</a>", "<a href=\"modcp.php?action=mods&amp;modsaction=builds\">{$builds}</a>");
	}
	
	// If message is empty simply set the notice to ''
	if (empty($message))
		$header = str_replace('{mods_site_builds}', '', $header);
	else
		$header = str_replace('{mods_site_builds}', "<div class=\"red_alert\">{$message}</div><br />", $header);
}

function mydownloads_online(&$plugin_array)
{
	if (strpos('mods.php', $plugin_array['user_activity']['location']) !== false)
	{
		global $lang;
		$lang->load("mods");
		
		$plugin_array['location_name'] = "Browsing <a href=\"mods.php\">".$lang->mods."</a>";
	}
	
	return $plugin_array;

}

?>