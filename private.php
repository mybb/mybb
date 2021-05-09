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
define('THIS_SCRIPT', 'private.php');

$templatelist = "private_send,private_send_buddyselect,private_tracking,private_tracking_readmessage,private_tracking_unreadmessage,usercp_nav_attachments,usercp_nav_messenger_compose,private_tracking_readmessage_stop";
$templatelist .= ",private_folders,private_folders_folder,private_folders_folder_unremovable,private,usercp_nav,private_empty_folder,private_archive_txt,private_archive_csv,private_archive_html,private_tracking_unreadmessage_stop";
$templatelist .= ",usercp_nav_messenger,usercp_nav_changename,multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start";
$templatelist .= ",private_messagebit,codebuttons,posticons,private_send_autocomplete,private_messagebit_denyreceipt,postbit_warninglevel_formatted,private_emptyexportlink,postbit_purgespammer,postbit_gotopost,private_read";
$templatelist .= ",postbit_delete_pm,postbit,private_tracking_nomessage,private_nomessages,postbit_author_guest,private_multiple_recipients_user,private_multiple_recipients_bcc,private_multiple_recipients,usercp_nav_messenger_folder";
$templatelist .= ",private_search_messagebit,private_search_results_nomessages,private_search_results,private_advanced_search,previewpost,private_send_tracking,private_send_signature,private_read_bcc,private_composelink";
$templatelist .= ",private_archive,private_quickreply,private_pmspace,private_limitwarning,postbit_groupimage,postbit_offline,postbit_www,postbit_replyall_pm,postbit_signature,postbit_classic,postbit_reputation_formatted_link";
$templatelist .= ",private_archive_folders_folder,private_archive_folders,postbit_warninglevel,postbit_author_user,postbit_forward_pm,private_messagebit_icon,private_jump_folders_folder,private_advanced_search_folders,usercp_nav_home";
$templatelist .= ",private_jump_folders,postbit_avatar,postbit_warn,postbit_rep_button,postbit_email,postbit_reputation,private_move,private_read_action,postbit_away,postbit_pm,usercp_nav_messenger_tracking,postbit_find";
$templatelist .= ",usercp_nav_editsignature,posticons_icon,postbit_icon,postbit_iplogged_hiden,usercp_nav_profile,usercp_nav_misc,postbit_userstar,private_read_to,postbit_online,private_empty,private_orderarrow,postbit_reply_pm";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
require_once MYBB_ROOT."inc/functions_user.php";
require_once MYBB_ROOT."inc/class_parser.php";
$parser = new postParser;

// Load global language phrases
$lang->load("private");

if($mybb->settings['enablepms'] == 0)
{
	error($lang->pms_disabled);
}

if($mybb->user['uid'] == '/' || $mybb->user['uid'] == 0 || $mybb->usergroup['canusepms'] == 0)
{
	error_no_permission();
}

$mybb->input['fid'] = $mybb->get_input('fid', MyBB::INPUT_INT);

$folder['id'] = $folder_name = '';

$foldernames = array();
$folders = [];
$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
foreach($foldersexploded as $key => $folder_name)
{
	$folderinfo = explode("**", $folder_name, 2);

	$folder['sel'] = false;
	if($input['fid'] == $folderinfo[0])
	{
		$folder['sel'] = true;
	}

	$folderinfo[1] = get_pm_folder_name($folderinfo[0], $folderinfo[1]);
	$foldernames[$folderinfo[0]] = $folderinfo[1];

	$folder['id'] = $folderinfo[0];
	$folder['name'] = $folderinfo[1];

	$folders[] = $folder;

	// Manipulate search folder selection & move selector to omit "Unread"
	if($folder['id'] != 1)
	{
		if($folder['id'] == 0)
		{
			$folder['id'] = 1;
		}
	}
}

$from_fid = $mybb->input['fid'];

usercp_menu();

$plugins->run_hooks("private_start");

// Make navigation
add_breadcrumb($lang->nav_pms, "private.php");

$mybb->input['action'] = $mybb->get_input('action');
switch($mybb->input['action'])
{
	case "send":
		add_breadcrumb($lang->nav_send);
		break;
	case "tracking":
		add_breadcrumb($lang->nav_tracking);
		break;
	case "folders":
		add_breadcrumb($lang->nav_folders);
		break;
	case "empty":
		add_breadcrumb($lang->nav_empty);
		break;
	case "export":
		add_breadcrumb($lang->nav_export);
		break;
	case "advanced_search":
		add_breadcrumb($lang->nav_search);
		break;
	case "results":
		add_breadcrumb($lang->nav_results);
		break;
}

if(!empty($mybb->input['preview']))
{
	$mybb->input['action'] = "send";
}

if(($mybb->input['action'] == "do_search" || $mybb->input['action'] == "do_stuff" && ($mybb->get_input('quick_search') || !$mybb->get_input('hop') && !$mybb->get_input('moveto') && !$mybb->get_input('delete'))) && $mybb->request_method == "post")
{
	$plugins->run_hooks("private_do_search_start");

	// Simulate coming from our advanced search form with some preset options
	if($mybb->get_input('quick_search'))
	{
		$mybb->input['action'] = "do_search";
		$mybb->input['subject'] = 1;
		$mybb->input['message'] = 1;
		$mybb->input['folder'] = $input['fid'];
		unset($mybb->input['jumpto']);
		unset($mybb->input['fromfid']);
	}

	// Check if search flood checking is enabled and user is not admin
	if($mybb->settings['searchfloodtime'] > 0 && $mybb->usergroup['cancp'] != 1)
	{
		// Fetch the time this user last searched
		$timecut = TIME_NOW - $mybb->settings['searchfloodtime'];
		$query = $db->simple_select("searchlog", "*", "uid='{$mybb->user['uid']}' AND dateline > '$timecut'", array('order_by' => "dateline", 'order_dir' => "DESC"));
		$last_search = $db->fetch_array($query);
		// Users last search was within the flood time, show the error
		if($last_search['sid'])
		{
			$remaining_time = $mybb->settings['searchfloodtime'] - (TIME_NOW - $last_search['dateline']);
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

	if($mybb->get_input('subject', MyBB::INPUT_INT) != 1 && $mybb->get_input('message', MyBB::INPUT_INT) != 1)
	{
		error($lang->error_nosearchresults);
	}

	if($mybb->get_input('message', MyBB::INPUT_INT) == 1)
	{
		$resulttype = "pmmessages";
	}
	else
	{
		$resulttype = "pmsubjects";
	}

	$search_data = array(
		"keywords" => $mybb->get_input('keywords'),
		"subject" => $mybb->get_input('subject', MyBB::INPUT_INT),
		"message" => $mybb->get_input('message', MyBB::INPUT_INT),
		"sender" => $mybb->get_input('sender'),
		"status" => $mybb->get_input('status', MyBB::INPUT_ARRAY),
		"folder" => $mybb->get_input('folder', MyBB::INPUT_ARRAY)
	);

	if($db->can_search == true)
	{
		require_once MYBB_ROOT."inc/functions_search.php";

		$search_results = privatemessage_perform_search_mysql($search_data);
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
	$plugins->run_hooks("private_do_search_process");

	$db->insert_query("searchlog", $searcharray);

	// Sender sort won't work yet
	$sortby = array('subject', 'sender', 'dateline');

	if(in_array($mybb->get_input('sort'), $sortby))
	{
		$sortby = $mybb->get_input('sort');
	}
	else
	{
		$sortby = "dateline";
	}

	if(my_strtolower($mybb->get_input('sortordr')) == "asc")
	{
		$sortorder = "asc";
	}
	else
	{
		$sortorder = "desc";
	}

	$plugins->run_hooks("private_do_search_end");
	redirect("private.php?action=results&sid=".$sid."&sortby=".$sortby."&order=".$sortorder, $lang->redirect_searchresults);
}

if($mybb->input['action'] == "results")
{
	$sid = $mybb->get_input('sid');
	$query = $db->simple_select("searchlog", "*", "sid='".$db->escape_string($sid)."' AND uid='{$mybb->user['uid']}'");
	$search = $db->fetch_array($query);

	if(!$search)
	{
		error($lang->error_invalidsearch);
	}

	$plugins->run_hooks("private_results_start");

	// Decide on our sorting fields and sorting order.
	$order = my_strtolower($mybb->get_input('order'));
	$sortby = my_strtolower($mybb->get_input('sortby'));

	$sortby_accepted = array('subject', 'username', 'dateline');

	if(in_array($sortby, $sortby_accepted))
	{
		$query_sortby = $sortby;

		if($query_sortby == "username")
		{
			$query_sortby = "fromusername";
		}
	}
	else
	{
		$sortby = $query_sortby = "dateline";
	}

	if($order != "asc")
	{
		$order = "desc";
	}

	if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	$query = $db->simple_select("privatemessages", "COUNT(*) AS total", "pmid IN(".$db->escape_string($search['querycache']).")");
	$pmscount = $db->fetch_field($query, "total");

	// Work out pagination, which page we're at, as well as the limits.
	$perpage = $mybb->settings['threadsperpage'];
	$page = $mybb->get_input('page', MyBB::INPUT_INT);
	if($page > 0)
	{
		$start = ($page-1) * $perpage;
		$pages = ceil($pmscount / $perpage);
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
	$end = $start + $perpage;
	$lower = $start + 1;
	$upper = $end;

	// Work out if we have terms to highlight
	$highlight = "";
	if($search['keywords'])
	{
		$highlight = "&amp;highlight=".urlencode($search['keywords']);
	}

	// Do Multi Pages
	if($upper > $pmscount)
	{
		$upper = $pmscount;
	}
	$multipage = multipage($pmscount, $perpage, $page, "private.php?action=results&amp;sid=".htmlspecialchars_uni($mybb->get_input('sid'))."&amp;sortby={$sortby}&amp;order={$order}");

	$icon_cache = $cache->read("posticons");

	// Cache users in multiple recipients for sent & drafts folder
	// Get all recipients into an array
	$cached_users = $get_users = array();
	$users_query = $db->simple_select("privatemessages", "recipients", "pmid IN(".$db->escape_string($search['querycache']).")", array('limit_start' => $start, 'limit' => $perpage, 'order_by' => $query_sortby, 'order_dir' => $order));
	while($row = $db->fetch_array($users_query))
	{
		$recipients = my_unserialize($row['recipients']);
		if(isset($recipients['to']) && is_array($recipients['to']) && count($recipients['to']))
		{
			$get_users = array_merge($get_users, $recipients['to']);
		}

		if(isset($recipients['bcc']) && is_array($recipients['bcc']) && count($recipients['bcc']))
		{
			$get_users = array_merge($get_users, $recipients['bcc']);
		}
	}

	$get_users = implode(',', array_unique($get_users));

	// Grab info
	if($get_users)
	{
		$users_query = $db->simple_select("users", "uid, username, usergroup, displaygroup, avatar", "uid IN ({$get_users})");
		while($user = $db->fetch_array($users_query))
		{
			$cached_users[$user['uid']] = $user;
		}
	}

	$messagelist = [];
	$query = $db->query("
        SELECT pm.*, fu.username AS fromusername, fu.avatar AS from_avatar, tu.username as tousername, tu.avatar as to_avatar
        FROM ".TABLE_PREFIX."privatemessages pm
        LEFT JOIN ".TABLE_PREFIX."users fu ON (fu.uid=pm.fromid)
        LEFT JOIN ".TABLE_PREFIX."users tu ON (tu.uid=pm.toid)
        WHERE pm.pmid IN(".$db->escape_string($search['querycache']).") AND pm.uid='{$mybb->user['uid']}'
        ORDER BY pm.{$query_sortby} {$order}
        LIMIT {$start}, {$perpage}
    ");
	while($message = $db->fetch_array($query))
	{
		// Determine Folder Icon
		if($message['status'] == 0)
		{
			$message['msgstatus'] = 'new_pm';
			$message['msgalt'] = $lang->new_pm;
		}
		elseif($message['status'] == 1)
		{
			$message['msgstatus'] = 'old_pm';
			$message['msgalt'] = $lang->old_pm;
		}
		elseif($message['status'] == 3)
		{
			$message['msgstatus'] = 're_pm';
			$message['msgalt'] = $lang->reply_pm;
		}
		elseif($message['status'] == 4)
		{
			$message['msgstatus'] = 'fw_pm';
			$message['msgalt'] = $lang->fwd_pm;
		}

		$message['multiplerecipients'] = false;
		if($message['folder'] == 2 || $message['folder'] == 3)
		{
			// Sent Items or Drafts Folder Check
			$recipients = my_unserialize($message['recipients']);
			if(
				isset($recipients['to']) &&
				(count($recipients['to']) > 1 || (count($recipients['to']) == 1 && isset($recipients['bcc']) && count($recipients['bcc']) > 0))
			)
			{
				$message['multiplerecipients'] = true;
				$message['tousers'] = $message['bbcusers'] = [];
				foreach($recipients['to'] as $uid)
				{
					$user = $cached_users[$uid];
					$user['profilelink'] = get_profile_link($uid);
					$user['username_raw'] = $user['username'];
					$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
					$message['tousers'][] = $user;
				}
				if(isset($recipients['bcc']) && is_array($recipients['bcc']) && count($recipients['bcc']))
				{
					foreach($recipients['bcc'] as $uid)
					{
						$user = $cached_users[$uid];
						$user['profilelink'] = get_profile_link($uid);
						$user['username_raw'] = $user['username'];
						$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
						$message['bbcusers'][] = $user;
					}
				}
			}
			elseif($message['toid'])
			{
				$message['tofromusername'] = $message['tousername'];
				$message['tofromuid'] = $message['toid'];
				$message['to_from_avatar'] = $message['to_avatar'];
			}
			else
			{
				$message['tofromusername'] = $lang->not_sent;
				$message['to_from_avatar'] = '';
			}
		}
		else
		{
			$message['tofromusername'] = $message['fromusername'];
			$message['tofromuid'] = $message['fromid'];
			if($message['tofromuid'] == 0)
			{
				$message['tofromusername'] = $lang->mybb_engine;
			}
			$message['to_from_avatar'] = $message['from_avatar'];
		}

		$message['avatar'] = $message['to_from_avatar'];
		$message['username_raw'] = $message['tofromusername'];
		$message['username'] = build_profile_link($message['tofromusername'], $message['tofromuid']);

		$message['hasicon'] = false;
		if($message['icon'] > 0 && $icon_cache[$message['icon']])
		{
			$message['hasicon'] = true;
			$icon = $icon_cache[$message['icon']];
			$icon['path'] = str_replace("{theme}", $theme['imgdir'], $icon['path']);
			$message['icon_path'] = $icon['path'];
			$message['icon_name'] = $icon['name'];
		}

		if(!trim($message['subject']))
		{
			$message['subject'] = $lang->pm_no_subject;
		}

		$message['subject'] = $parser->parse_badwords($message['subject']);

		if($message['folder'] != 3)
		{
			$message['senddate'] = my_date('relative', $message['dateline']);
		}
		else
		{
			$message['senddate'] = $lang->not_sent;
		}

		$fid = "0";
		if((int)$message['folder'] > 1)
		{
			$fid = $message['folder'];
		}
		$message['foldername'] = $foldernames[$fid];

		// What we do here is parse the post using our post parser, then strip the tags from it
		$parser_options = array(
			'allow_html' => 0,
			'allow_mycode' => 1,
			'allow_smilies' => 0,
			'allow_imgcode' => 0,
			'filter_badwords' => 1
		);
		$message['message'] = strip_tags($parser->parse_message($message['message'], $parser_options));

		$messagelist[] = $message;
	}

	$plugins->run_hooks("private_results_end");

	output_page(\MyBB\template('private/results.twig', [
		'messagelist' => $messagelist,
		'multipage' => $multipage,
		'folders' => $folders,
		'input' => $input,
	]));
}

if($mybb->input['action'] == "advanced_search")
{
	$plugins->run_hooks("private_advanced_search");

	output_page(\MyBB\template('private/advanced_search.twig', [
		'folders' => $folders,
	]));
}

// Dismissing a new/unread PM notice
if($mybb->input['action'] == "dismiss_notice")
{
	if($mybb->user['pmnotice'] != 2)
	{
		exit;
	}

	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$updated_user = array(
		"pmnotice" => 1
	);
	$db->update_query("users", $updated_user, "uid='{$mybb->user['uid']}'");

	if(!empty($mybb->input['ajax']))
	{
		echo 1;
		exit;
	}
	else
	{
		header("Location: index.php");
		exit;
	}
}

$send_errors = '';

if($mybb->input['action'] == "do_send" && $mybb->request_method == "post")
{
	if($mybb->usergroup['cansendpms'] == 0)
	{
		error_no_permission();
	}

	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("private_send_do_send");

	// Attempt to see if this PM is a duplicate or not
	$to = array_map("trim", explode(",", $mybb->get_input('to')));
	$to = array_unique($to); // Filter out any duplicates
	$to_escaped = implode("','", array_map(array($db, 'escape_string'), array_map('my_strtolower', $to)));
	$time_cutoff = TIME_NOW - (5 * 60 * 60);
	$query = $db->query("
		SELECT pm.pmid
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users u ON(u.uid=pm.toid)
		WHERE LOWER(u.username) IN ('{$to_escaped}') AND pm.dateline > {$time_cutoff} AND pm.fromid='{$mybb->user['uid']}' AND pm.subject='".$db->escape_string($mybb->get_input('subject'))."' AND pm.message='".$db->escape_string($mybb->get_input('message'))."' AND pm.folder!='3'
		LIMIT 0, 1
	");
	if($db->num_rows($query) > 0)
	{
		error($lang->error_pm_already_submitted);
	}

	require_once MYBB_ROOT."inc/datahandlers/pm.php";
	$pmhandler = new PMDataHandler();

	$pm = array(
		"subject" => $mybb->get_input('subject'),
		"message" => $mybb->get_input('message'),
		"icon" => $mybb->get_input('icon', MyBB::INPUT_INT),
		"fromid" => $mybb->user['uid'],
		"do" => $mybb->get_input('do'),
		"pmid" => $mybb->get_input('pmid', MyBB::INPUT_INT),
		"ipaddress" => $session->packedip
	);

	// Split up any recipients we have
	$pm['to'] = $to;
	if(!empty($mybb->input['bcc']))
	{
		$pm['bcc'] = explode(",", $mybb->get_input('bcc'));
		$pm['bcc'] = array_map("trim", $pm['bcc']);
	}

	$mybb->input['options'] = $mybb->get_input('options', MyBB::INPUT_ARRAY);

	if(!$mybb->usergroup['cantrackpms'])
	{
		$mybb->input['options']['readreceipt'] = false;
	}

	$pm['options'] = array();
	if(isset($mybb->input['options']['signature']) && $mybb->input['options']['signature'] == 1)
	{
		$pm['options']['signature'] = 1;
	}
	else
	{
		$pm['options']['signature'] = 0;
	}
	if(isset($mybb->input['options']['disablesmilies']))
	{
		$pm['options']['disablesmilies'] = $mybb->input['options']['disablesmilies'];
	}
	if(isset($mybb->input['options']['savecopy']) && $mybb->input['options']['savecopy'] == 1)
	{
		$pm['options']['savecopy'] = 1;
	}
	else
	{
		$pm['options']['savecopy'] = 0;
	}
	if(isset($mybb->input['options']['readreceipt']))
	{
		$pm['options']['readreceipt'] = $mybb->input['options']['readreceipt'];
	}

	if(!empty($mybb->input['saveasdraft']))
	{
		$pm['saveasdraft'] = 1;
	}
	$pmhandler->set_data($pm);

	// Now let the pm handler do all the hard work.
	if(!$pmhandler->validate_pm())
	{
		$pm_errors = $pmhandler->get_friendly_errors();
		$send_errors = inline_error($pm_errors);
		$mybb->input['action'] = "send";
	}
	else
	{
		$pminfo = $pmhandler->insert_pm();
		$plugins->run_hooks("private_do_send_end");

		if(isset($pminfo['draftsaved']))
		{
			redirect("private.php", $lang->redirect_pmsaved);
		}
		else
		{
			redirect("private.php", $lang->redirect_pmsent);
		}
	}
}

if($mybb->input['action'] == "send")
{
	if($mybb->usergroup['cansendpms'] == 0)
	{
		error_no_permission();
	}

	$plugins->run_hooks("private_send_start");

	$smilieinserter = $codebuttons = '';

	if($mybb->settings['bbcodeinserter'] != 0 && $mybb->settings['pmsallowmycode'] != 0 && $mybb->user['showcodebuttons'] != 0)
	{
		$codebuttons = build_mycode_inserter("message", $mybb->settings['pmsallowsmilies']);
		if($mybb->settings['pmsallowsmilies'] != 0)
		{
			$smilieinserter = build_clickable_smilies();
		}
	}

	$posticons = get_post_icons();
	$sendpm['message'] = $parser->parse_badwords($mybb->get_input('message'));
	$sendpm['subject'] = $parser->parse_badwords($mybb->get_input('subject'));

	$sendpm['options'] = array('signature' => false, 'disablesmilies' => false, 'savecopy' => false, 'readreceipt' => false);

	if(!empty($mybb->input['preview']) || $send_errors)
	{
		$options = $mybb->get_input('options', MyBB::INPUT_ARRAY);
		if(isset($options['signature']) && $options['signature'] == 1)
		{
			$sendpm['options']['signature'] = true;
		}

		if(isset($options['disablesmilies']) && $options['disablesmilies'] == 1)
		{
			$sendpm['options']['disablesmilies'] = true;
		}

		if(isset($options['savecopy']) && $options['savecopy'] != 0)
		{
			$sendpm['options']['savecopy'] = true;
		}

		if(isset($options['readreceipt']) && $options['readreceipt'] != 0)
		{
			$sendpm['options']['readreceipt'] = true;
		}

		$sendpm['to'] = implode(', ', array_unique(array_map('trim', explode(',', $mybb->get_input('to')))));
		$sendpm['bcc'] = implode(', ', array_unique(array_map('trim', explode(',', $mybb->get_input('bcc')))));
	}

	// Preview
	$sendpm['preview'] = false;
	if(!empty($mybb->input['preview']))
	{
		$sendpm['preview'] = true;
		$options = $mybb->get_input('options', MyBB::INPUT_ARRAY);
		$query = $db->query("
            SELECT u.username AS userusername, u.*, f.*
            FROM ".TABLE_PREFIX."users u
            LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
            WHERE u.uid='".$mybb->user['uid']."'
        ");

		$post = $db->fetch_array($query);

		$post['userusername'] = $mybb->user['username'];
		$post['postusername'] = $mybb->user['username'];
		$post['message'] = $mybb->get_input('message');
		$post['subject'] = htmlspecialchars_uni($mybb->get_input('subject'));
		$post['icon'] = $mybb->get_input('icon', MyBB::INPUT_INT);
		if(!isset($options['disablesmilies']))
		{
			$options['disablesmilies'] = 0;
		}

		$post['smilieoff'] = $options['disablesmilies'];
		$post['dateline'] = TIME_NOW;

		if(!isset($options['signature']))
		{
			$post['includesig'] = 0;
		}
		else
		{
			$post['includesig'] = 1;
		}

		// Merge usergroup data from the cache
		$data_key = array(
			'title' => 'grouptitle',
			'usertitle' => 'groupusertitle',
			'stars' => 'groupstars',
			'starimage' => 'groupstarimage',
			'image' => 'groupimage',
			'namestyle' => 'namestyle',
			'usereputationsystem' => 'usereputationsystem'
		);

		foreach($data_key as $field => $key)
		{
			$post[$key] = $groupscache[$post['usergroup']][$field];
		}

		$postbit = build_postbit($post, 2);
	}
	elseif(!$send_errors)
	{
		// New PM, so load default settings
		if($mybb->user['signature'] != '')
		{
			$sendpm['options']['signature'] = true;
		}

		if($mybb->usergroup['cantrackpms'] == 1)
		{
			$sendpm['options']['readreceipt'] = true;
		}

		$sendpm['options']['savecopy'] = true;
	}

	// Draft, reply, forward
	if($mybb->get_input('pmid') && empty($mybb->input['preview']) && !$send_errors)
	{
		$query = $db->query("
            SELECT pm.*, u.username AS quotename
            FROM ".TABLE_PREFIX."privatemessages pm
            LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.fromid)
            WHERE pm.pmid='".$mybb->get_input('pmid', MyBB::INPUT_INT)."' AND pm.uid='{$mybb->user['uid']}'
        ");

		$pm = $db->fetch_array($query);
		$sendpm['message'] = $parser->parse_badwords($pm['message']);
		$sendpm['subject'] = $parser->parse_badwords($pm['subject']);

		if($pm['folder'] == 3)
		{
			// Message saved in drafts
			$mybb->input['uid'] = $pm['toid'];

			if($pm['includesig'] == 1)
			{
				$sendpm['options']['signature'] = true;
			}

			if($pm['smilieoff'] == 1)
			{
				$sendpm['options']['disablesmilies'] = true;
			}

			if($pm['receipt'])
			{
				$sendpm['options']['readreceipt'] = true;
			}

			// Get list of recipients
			$recipients = my_unserialize($pm['recipients']);
			$comma = $recipientids = '';
			if(isset($recipients['to']) && is_array($recipients['to']))
			{
				foreach($recipients['to'] as $recipient)
				{
					$recipient_list['to'][] = $recipient;
					$recipientids .= $comma.$recipient;
					$comma = ',';
				}
			}

			if(isset($recipients['bcc']) && is_array($recipients['bcc']))
			{
				foreach($recipients['bcc'] as $recipient)
				{
					$recipient_list['bcc'][] = $recipient;
					$recipientids .= $comma.$recipient;
					$comma = ',';
				}
			}

			if(!empty($recipientids))
			{
				$query = $db->simple_select("users", "uid, username", "uid IN ({$recipientids})");
				while($user = $db->fetch_array($query))
				{
					if(isset($recipients['bcc']) && is_array($recipients['bcc']) && in_array($user['uid'], $recipient_list['bcc']))
					{
						$sendpm['bcc'] .= $user['username'].', ';
					}
					else
					{
						$sendpm['to'] .= $user['username'].', ';
					}
				}
			}
		}
		else
		{
			// Forward/Reply
			$sendpm['subject'] = preg_replace("#(FW|RE):( *)#is", '', $sendpm['subject']);
			$sendpm['message'] = "[quote='{$pm['quotename']}']\n{$sendpm['message']}\n[/quote]";
			$sendpm['message'] = preg_replace('#^/me (.*)$#im', "* ".$pm['quotename']." \\1", $sendpm['message']);

			require_once MYBB_ROOT."inc/functions_posting.php";

			if($mybb->settings['maxpmquotedepth'] != '0')
			{
				$sendpm['message'] = remove_message_quotes($sendpm['message'], $mybb->settings['maxpmquotedepth']);
			}

			if($mybb->input['do'] == 'forward')
			{
				$sendpm['subject'] = "Fw: {$sendpm['subject']}";
			}
			elseif($mybb->input['do'] == 'reply')
			{
				$sendpm['subject'] = "Re: {$sendpm['subject']}";
				$uid = $pm['fromid'];
				if($mybb->user['uid'] == $uid)
				{
					$sendpm['to'] = $mybb->user['username'];
				}
				else
				{
					$query = $db->simple_select('users', 'username', "uid='{$uid}'");
					$sendpm['to'] = $db->fetch_field($query, 'username');
				}
			}
			elseif($mybb->input['do'] == 'replyall')
			{
				$sendpm['subject'] = "Re: {$sendpm['subject']}";

				// Get list of recipients
				$recipients = my_unserialize($pm['recipients']);
				$recipientids = $pm['fromid'];
				if(isset($recipients['to']) && is_array($recipients['to']))
				{
					foreach($recipients['to'] as $recipient)
					{
						if($recipient == $mybb->user['uid'])
						{
							continue;
						}

						$recipientids .= ','.$recipient;
					}
				}

				$comma = '';
				$query = $db->simple_select('users', 'uid, username', "uid IN ({$recipientids})");
				while($user = $db->fetch_array($query))
				{
					$sendpm['to'] .= $comma.$user['username'];
					$comma = $lang->comma;
				}
			}
		}
	}

	// New PM with recipient preset
	if($mybb->get_input('uid', MyBB::INPUT_INT) && empty($mybb->input['preview']))
	{
		$query = $db->simple_select('users', 'username', "uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'");
		$sendpm['to'] = $db->fetch_field($query, 'username').', ';
	}

	if($send_errors)
	{
		$sendpm['to'] = implode(', ', array_unique(array_map('trim', explode(',', $mybb->get_input('to')))));
		$sendpm['bcc'] = implode(', ', array_unique(array_map('trim', explode(',', $mybb->get_input('bcc')))));
	}

	$sendpm['pmid'] = $mybb->get_input('pmid', MyBB::INPUT_INT);
	$sendpm['do'] = $mybb->get_input('do');
	if($sendpm['do'] != "forward" && $sendpm['do'] != "reply" && $sendpm['do'] != "replyall")
	{
		$sendpm['do'] = '';
	}

	$sendpm['showposticons'] = false;
	if(is_array($posticons))
	{
		$sendpm['showposticons'] = true;
	}

	$sendpm['emptyiconcheck'] = false;
	if(empty($mybb->input['icon']))
	{
		$sendpm['emptyiconcheck'] = true;
	}

	$plugins->run_hooks("private_send_end");

	output_page(\MyBB\template('private/send.twig', [
		'sendpm' => $sendpm,
		'send_errors' => $send_errors,
		'smilieinserter' => $smilieinserter,
		'codebuttons' => $codebuttons,
		'postbit' => $postbit,
		'posticons' => $posticons,
	]));
}

if($mybb->input['action'] == "read")
{
	$plugins->run_hooks("private_read_start");

	$pmid = $mybb->get_input('pmid', MyBB::INPUT_INT);

	$query = $db->query("
        SELECT pm.*, u.*, f.*
        FROM ".TABLE_PREFIX."privatemessages pm
        LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.fromid)
        LEFT JOIN ".TABLE_PREFIX."userfields f ON (f.ufid=u.uid)
        WHERE pm.pmid='{$pmid}' AND pm.uid='".$mybb->user['uid']."'
    ");
	$pm = $db->fetch_array($query);

	if(!$pm)
	{
		error($lang->error_invalidpm);
	}

	if($pm['folder'] == 3)
	{
		header("Location: private.php?action=send&pmid={$pm['pmid']}");
		exit;
	}

	// If we've gotten a PM, attach the group info
	$data_key = array(
		'title' => 'grouptitle',
		'usertitle' => 'groupusertitle',
		'stars' => 'groupstars',
		'starimage' => 'groupstarimage',
		'image' => 'groupimage',
		'namestyle' => 'namestyle'
	);

	foreach($data_key as $field => $key)
	{
		$pm[$key] = $groupscache[$pm['usergroup']][$field];
	}

	if($pm['receipt'] == 1)
	{
		if($mybb->usergroup['candenypmreceipts'] == 1 && $mybb->get_input('denyreceipt', MyBB::INPUT_INT) == 1)
		{
			$receiptadd = 0;
		}
		else
		{
			$receiptadd = 2;
		}
	}

	$action_time = '';
	if($pm['status'] == 0)
	{
		$time = TIME_NOW;
		$updatearray = array(
			'status' => 1,
			'readtime' => $time
		);

		if(isset($receiptadd))
		{
			$updatearray['receipt'] = $receiptadd;
		}

		$db->update_query('privatemessages', $updatearray, "pmid='{$pmid}'");

		// Update the unread count - it has now changed.
		$pmcount = update_pm_count($mybb->user['uid'], 6);

		if(is_array($pmcount) && isset($pmcount['unreadpms']))
		{
			$mybb->user['pms_unread'] = $pmcount['unreadpms'];
		}

		// Update PM notice value if this is our last unread PM
		if($mybb->user['unreadpms'] - 1 <= 0 && $mybb->user['pmnotice'] == 2)
		{
			$updated_user = array(
				"pmnotice" => 1
			);
			$db->update_query("users", $updated_user, "uid='{$mybb->user['uid']}'");
		}
	}
	elseif($pm['status'] == 3 && $pm['statustime'])
	{
		// Replied PM?
		$pm['reply_date'] = my_date('relative', $pm['statustime']);

		if((TIME_NOW - $pm['statustime']) < 3600)
		{
			// Relative string for the first hour
			$lang->you_replied_on = $lang->you_replied;
		}
	}
	elseif($pm['status'] == 4 && $pm['statustime'])
	{
		$pm['forward_date'] = my_date('relative', $pm['statustime']);

		if((TIME_NOW - $pm['statustime']) < 3600)
		{
			$lang->you_forwarded_on = $lang->you_forwarded;
		}
	}

	$pm['userusername'] = $pm['username'];
	$pm['subject'] = $parser->parse_badwords($pm['subject']);

	if($pm['fromid'] == 0)
	{
		$pm['username'] = $lang->mybb_engine;
	}

	if(!$pm['username'])
	{
		$pm['username'] = $lang->na;
	}

	// Fetch the recipients for this message
	$pm['recipients'] = my_unserialize($pm['recipients']);

	if(isset($pm['recipients']['to']) && is_array($pm['recipients']['to']))
	{
		$uid_sql = implode(',', $pm['recipients']['to']);
	}
	else
	{
		$uid_sql = $pm['toid'];
		$pm['recipients']['to'] = array($pm['toid']);
	}

	$show_bcc = 0;

	// If we have any BCC recipients and this user is an Administrator, add them on to the query
	if(isset($pm['recipients']['bcc']) && count($pm['recipients']['bcc']) > 0 && $mybb->usergroup['cancp'] == 1)
	{
		$show_bcc = 1;
		$uid_sql .= ','.implode(',', $pm['recipients']['bcc']);
	}

	// Fetch recipient names from the database
	$pm['bcc_recipients'] = $pm['to_recipients'] = $pm['bcc_form_val'] = array();
	$query = $db->simple_select('users', 'uid, username', "uid IN ({$uid_sql})");
	while($recipient = $db->fetch_array($query))
	{
		// User is a BCC recipient
		if($show_bcc && in_array($recipient['uid'], $pm['recipients']['bcc']))
		{
			$pm['bcc_recipients'][] = build_profile_link($recipient['username'], $recipient['uid']);
			$pm['bcc_form_val'][] = $recipient['username'];
		}
		elseif(in_array($recipient['uid'], $pm['recipients']['to']))
		{
			// User is a normal recipient
			$pm['to_recipients'][] = build_profile_link($recipient['username'], $recipient['uid']);
		}
	}

	if(count($pm['bcc_recipients']) > 0)
	{
		$pm['bcc_form_val'] = implode(',', $pm['bcc_form_val']);
	}
	else
	{
		$pm['bcc_form_val'] = '';
	}

	add_breadcrumb($pm['subject']);
	$message = build_postbit($pm, 2);

	// Decide whether or not to show quick reply.
	if($mybb->settings['pmquickreply'] != 0 && $mybb->user['showquickreply'] != 0 && $mybb->usergroup['cansendpms'] != 0 && $pm['fromid'] != 0 && $pm['folder'] != 3)
	{
		$pm['options'] = array('savecopy' => true, 'disablesmilies' => false);
		if(!empty($mybb->user['signature']))
		{
			$pm['options']['signature'] = true;
		}

		if($mybb->usergroup['cantrackpms'] == 1)
		{
			$pm['options']['readreceipt'] = true;
		}

		require_once MYBB_ROOT.'inc/functions_posting.php';

		$pm['quoted_message'] = array(
			'message' => $parser->parse_badwords($pm['message']),
			'username' => $pm['username'],
			'quote_is_pm' => true
		);
		$pm['quoted_message'] = parse_quoted_message($pm['quoted_message']);

		if($mybb->settings['maxpmquotedepth'] != 0)
		{
			$pm['quoted_message'] = remove_message_quotes($pm['quoted_message'], $mybb->settings['maxpmquotedepth']);
		}

		$pm['sendsubject'] = preg_replace("#(FW|RE):( *)#is", '', $pm['subject']);

		if($mybb->user['uid'] == $pm['fromid'])
		{
			$pm['sendto'] = $mybb->user['username'];
		}
		else
		{
			$query = $db->simple_select('users', 'username', "uid='{$pm['fromid']}'");
			$pm['sendto'] = $db->fetch_field($query, 'username');
		}
	}

	$plugins->run_hooks("private_read_end");

	output_page(\MyBB\template('private/read.twig', [
		'pm' => $pm,
		'message' => $message,
		'collapsedthead' => $collapsedthead,
		'collapsedimg' => $collapsedimg,
		'collapsed' => $collapsed,
	]));
}

if($mybb->input['action'] == "tracking")
{
	if(!$mybb->usergroup['cantrackpms'])
	{
		error_no_permission();
	}

	$plugins->run_hooks("private_tracking_start");

	if(!$mybb->settings['postsperpage'] || (int)$mybb->settings['postsperpage'] < 1)
	{
		$mybb->settings['postsperpage'] = 20;
	}

	// Figure out if we need to display multiple pages.
	$perpage = $mybb->settings['postsperpage'];

	$query = $db->simple_select("privatemessages", "COUNT(pmid) as readpms", "receipt='2' AND folder!='3' AND status!='0' AND fromid='".$mybb->user['uid']."'");
	$postcount = $db->fetch_field($query, "readpms");

	$page = $mybb->get_input('read_page', MyBB::INPUT_INT);
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->get_input('read_page') == "last")
	{
		$page = $pages;
	}

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}

	if($page)
	{
		$start = ($page - 1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$read_multipage = multipage($postcount, $perpage, $page, "private.php?action=tracking&amp;read_page={page}");

	$readmessages = [];
	$query = $db->query("
        SELECT pm.pmid, pm.subject, pm.toid, pm.readtime, u.username as tousername, u.avatar as to_avatar
        FROM ".TABLE_PREFIX."privatemessages pm
        LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.toid)
        WHERE pm.receipt='2' AND pm.folder!='3'  AND pm.status!='0' AND pm.fromid='".$mybb->user['uid']."'
        ORDER BY pm.readtime DESC
        LIMIT {$start}, {$perpage}
    ");
	while($readmessage = $db->fetch_array($query))
	{
		$readmessage['subject'] = $parser->parse_badwords($readmessage['subject']);
		$readmessage['profilelink'] = build_profile_link($readmessage['tousername'], $readmessage['toid']);
		$readmessage['readdate'] = my_date('relative', $readmessage['readtime']);

		$readmessages[] = $readmessage;
	}

	$query = $db->simple_select("privatemessages", "COUNT(pmid) as unreadpms", "receipt='1' AND folder!='3' AND status='0' AND fromid='".$mybb->user['uid']."'");
	$postcount = $db->fetch_field($query, "unreadpms");

	$page = $mybb->get_input('unread_page', MyBB::INPUT_INT);
	$pages = $postcount / $perpage;
	$pages = ceil($pages);

	if($mybb->get_input('unread_page') == "last")
	{
		$page = $pages;
	}

	if($page > $pages || $page <= 0)
	{
		$page = 1;
	}

	if($page)
	{
		$start = ($page - 1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$unread_multipage = multipage($postcount, $perpage, $page, "private.php?action=tracking&amp;unread_page={page}");

	$unreadmessages = [];
	$query = $db->query("
        SELECT pm.pmid, pm.subject, pm.toid, pm.dateline, u.username as tousername, u.avatar as to_avatar
        FROM ".TABLE_PREFIX."privatemessages pm
        LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.toid)
        WHERE pm.receipt='1' AND pm.folder!='3' AND pm.status='0' AND pm.fromid='".$mybb->user['uid']."'
        ORDER BY pm.dateline DESC
        LIMIT {$start}, {$perpage}
    ");
	while($unreadmessage = $db->fetch_array($query))
	{
		$unreadmessage['subject'] = $parser->parse_badwords($unreadmessage['subject']);
		$unreadmessage['profilelink'] = build_profile_link($unreadmessage['tousername'], $unreadmessage['toid']);
		$unreadmessage['senddate'] = my_date('relative', $unreadmessage['dateline']);

		$unreadmessages[] = $unreadmessage;
	}

	$plugins->run_hooks("private_tracking_end");

	output_page(\MyBB\template('private/tracking.twig', [
		'read_multipage' => $read_multipage,
		'unread_multipage' => $unread_multipage,
		'readmessages' => $readmessages,
		'unreadmessages' => $unreadmessages,
	]));
}

if($mybb->input['action'] == "do_tracking" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("private_do_tracking_start");

	if(!empty($mybb->input['stoptracking']))
	{
		$mybb->input['readcheck'] = $mybb->get_input('readcheck', MyBB::INPUT_ARRAY);
		if(!empty($mybb->input['readcheck']))
		{
			foreach($mybb->input['readcheck'] as $key => $val)
			{
				$sql_array = array(
					"receipt" => 0
				);
				$db->update_query("privatemessages", $sql_array, "pmid=".(int)$key." AND fromid=".$mybb->user['uid']);
			}
		}
		$plugins->run_hooks("private_do_tracking_end");
		redirect("private.php?action=tracking", $lang->redirect_pmstrackingstopped);
	}
	elseif(!empty($mybb->input['stoptrackingunread']))
	{
		$mybb->input['unreadcheck'] = $mybb->get_input('unreadcheck', MyBB::INPUT_ARRAY);
		if(!empty($mybb->input['unreadcheck']))
		{
			foreach($mybb->input['unreadcheck'] as $key => $val)
			{
				$sql_array = array(
					"receipt" => 0
				);
				$db->update_query("privatemessages", $sql_array, "pmid=".(int)$key." AND fromid=".$mybb->user['uid']);
			}
		}
		$plugins->run_hooks("private_do_tracking_end");
		redirect("private.php?action=tracking", $lang->redirect_pmstrackingstopped);
	}
	elseif(!empty($mybb->input['cancel']))
	{
		$mybb->input['unreadcheck'] = $mybb->get_input('unreadcheck', MyBB::INPUT_ARRAY);
		if(!empty($mybb->input['unreadcheck']))
		{
			foreach($mybb->input['unreadcheck'] as $pmid => $val)
			{
				$pmids[$pmid] = (int)$pmid;
			}

			$pmids = implode(",", $pmids);
			$query = $db->simple_select("privatemessages", "uid", "pmid IN ($pmids) AND fromid='".$mybb->user['uid']."'");
			while($pm = $db->fetch_array($query))
			{
				$pmuids[$pm['uid']] = $pm['uid'];
			}

			$db->delete_query("privatemessages", "pmid IN ($pmids) AND receipt='1' AND status='0' AND fromid='".$mybb->user['uid']."'");
			foreach($pmuids as $uid)
			{
				// Message is canceled, update PM count for this user
				update_pm_count($uid);
			}
		}
		$plugins->run_hooks("private_do_tracking_end");
		redirect("private.php?action=tracking", $lang->redirect_pmstrackingcanceled);
	}
}

if($mybb->input['action'] == "stopalltracking")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("private_stopalltracking_start");

	$sql_array = array(
		"receipt" => 0
	);
	$db->update_query("privatemessages", $sql_array, "receipt='2' AND folder!='3' AND status!='0' AND fromid=".$mybb->user['uid']);

	$plugins->run_hooks("private_stopalltracking_end");
	redirect("private.php?action=tracking", $lang->redirect_allpmstrackingstopped);
}

if($mybb->input['action'] == "folders")
{
	$plugins->run_hooks("private_folders_start");

	$folderlist = [];
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		$foldername = $folderinfo[1];
		$folder['fid'] = $folderinfo[0];
		$folder['foldername'] = get_pm_folder_name($folder['fid'], $foldername);

		$folder['default'] = false;
		if((int)$folderinfo[0] < 5)
		{
			$folder['default'] = true;
			$folder['defaultname'] = get_pm_folder_name($folder['fid']);
		}

		$folderlist[] = $folder;
	}

	$plugins->run_hooks("private_folders_end");

	output_page(\MyBB\template('private/folders.twig', [
		'folderlist' => $folderlist,
	]));
}

if($mybb->input['action'] == "do_folders" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("private_do_folders_start");

	$highestid = 2;
	$folders = '';
	$donefolders = array();
	$mybb->input['folder'] = $mybb->get_input('folder', MyBB::INPUT_ARRAY);
	foreach($mybb->input['folder'] as $key => $val)
	{
		if(empty($donefolders[$val]))// Probably was a check for duplicate folder names, but doesn't seem to be used now
		{
			if(my_substr($key, 0, 3) == "new") // Create a new folder
			{
				++$highestid;
				$fid = (int)$highestid;
			}
			else // Editing an existing folder
			{
				if($key > $highestid)
				{
					$highestid = $key;
				}

				$fid = (int)$key;
				// Use default language strings if empty or value is language string
				if($val == get_pm_folder_name($fid) || trim($val) == '')
				{
					$val = '';
				}
			}

			if($val != '' && trim($val) == '' && !(is_numeric($key) && $key <= 4))
			{
				// If the name only contains whitespace and it's not a default folder, print an error
				error($lang->error_emptypmfoldername);
			}

			if($val != '' || (is_numeric($key) && $key <= 4))
			{
				// If there is a name or if this is a default folder, save it
				$foldername = $db->escape_string(htmlspecialchars_uni($val));

				if(my_strpos($foldername, "$%%$") === false)
				{
					if($folders != '')
					{
						$folders .= "$%%$";
					}
					$folders .= "$fid**$foldername";
				}
				else
				{
					error($lang->error_invalidpmfoldername);
				}
			}
			else
			{
				// Delete PMs from the folder
				$db->delete_query("privatemessages", "folder='$fid' AND uid='".$mybb->user['uid']."'");
			}
		}
	}

	$sql_array = array(
		"pmfolders" => $folders
	);
	$db->update_query("users", $sql_array, "uid='".$mybb->user['uid']."'");

	// Update PM count
	update_pm_count();

	$plugins->run_hooks("private_do_folders_end");

	redirect("private.php", $lang->redirect_pmfoldersupdated);
}

if($mybb->input['action'] == "empty")
{
	if($mybb->user['totalpms'] == 0)
	{
		error($lang->error_nopms);
	}

	$plugins->run_hooks("private_empty_start");

	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	$folderlist = [];
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		$folder['fid'] = $folderinfo[0];
		if($folderinfo[0] == "1")
		{
			$folder['fid'] = "1";
			$unread = " AND status='0'";
		}
		if($folderinfo[0] == "0")
		{
			$folder['fid'] = "1";
		}
        $folder['foldername'] = get_pm_folder_name($folderinfo[0], $folderinfo[1]);
		$query = $db->simple_select("privatemessages", "COUNT(*) AS pmsinfolder", " folder='{$folder['fid']}'{$unread} AND uid='{$mybb->user['uid']}'");
		$thing = $db->fetch_array($query);
		$folder['foldercount'] = my_number_format($thing['pmsinfolder']);

		$folderlist[] = $folder;
	}

	$plugins->run_hooks("private_empty_end");

	output_page(\MyBB\template('private/empty.twig', [
		'folderlist' => $folderlist,
	]));
}

if($mybb->input['action'] == "do_empty" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("private_do_empty_start");

	$emptyq = '';
	$mybb->input['empty'] = $mybb->get_input('empty', MyBB::INPUT_ARRAY);
	$keepunreadq = '';
	if($mybb->get_input('keepunread', MyBB::INPUT_INT) == 1)
	{
		$keepunreadq = " AND status!='0'";
	}
	if(!empty($mybb->input['empty']))
	{
		foreach($mybb->input['empty'] as $key => $val)
		{
			if($val == 1)
			{
				$key = (int)$key;
				if($emptyq)
				{
					$emptyq .= " OR ";
				}
				$emptyq .= "folder='$key'";
			}
		}

		if($emptyq != '')
		{
			$db->delete_query("privatemessages", "($emptyq) AND uid='".$mybb->user['uid']."'{$keepunreadq}");
		}
	}

	// Update PM count
	update_pm_count();

	$plugins->run_hooks("private_do_empty_end");
	redirect("private.php", $lang->redirect_pmfoldersemptied);
}

if($mybb->input['action'] == "do_stuff" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("private_do_stuff");

	if(!empty($mybb->input['hop']))
	{
		header("Location: private.php?fid=".$mybb->get_input('jumpto'));
	}
	elseif(!empty($mybb->input['moveto']))
	{
		$pms = array_map('intval', array_keys($mybb->get_input('check', MyBB::INPUT_ARRAY)));
		if(!empty($pms))
		{
			if(empty($mybb->input['fid']))
			{
				$mybb->input['fid'] = 1;
			}

			if(array_key_exists($mybb->input['fid'], $foldernames))
			{
				$db->update_query("privatemessages", array("folder" => $mybb->input['fid']), "pmid IN (".implode(",", $pms).") AND uid='".$mybb->user['uid']."'");
				update_pm_count();
			}
			else
			{
				error($lang->error_invalidmovefid);
			}
		}

		if(!empty($mybb->input['fromfid']))
		{
			redirect("private.php?fid=".$mybb->get_input('fromfid', MyBB::INPUT_INT), $lang->redirect_pmsmoved);
		}
		else
		{
			redirect("private.php", $lang->redirect_pmsmoved);
		}
	}
	elseif(!empty($mybb->input['delete']))
	{
		$mybb->input['check'] = $mybb->get_input('check', MyBB::INPUT_ARRAY);
		if(!empty($mybb->input['check']))
		{
			$pmssql = '';
			foreach($mybb->input['check'] as $key => $val)
			{
				if($pmssql)
				{
					$pmssql .= ",";
				}
				$pmssql .= "'".(int)$key."'";
			}

			$deletepms = array();
			$query = $db->simple_select("privatemessages", "pmid, folder", "pmid IN ($pmssql) AND uid='".$mybb->user['uid']."' AND folder='4'", array('order_by' => 'pmid'));
			while($delpm = $db->fetch_array($query))
			{
				$deletepms[$delpm['pmid']] = 1;
			}

			foreach($mybb->input['check'] as $key => $val)
			{
				$key = (int)$key;
				if(!empty($deletepms[$key]))
				{
					$db->delete_query("privatemessages", "pmid='$key' AND uid='".$mybb->user['uid']."'");
				}
				else
				{
					$sql_array = array(
						"folder" => 4,
						"deletetime" => TIME_NOW
					);
					$db->update_query("privatemessages", $sql_array, "pmid='".$key."' AND uid='".$mybb->user['uid']."'");
				}
			}
		}
		// Update PM count
		update_pm_count();

		if(!empty($mybb->input['fromfid']))
		{
			redirect("private.php?fid=".$mybb->get_input('fromfid', MyBB::INPUT_INT), $lang->redirect_pmsdeleted);
		}
		else
		{
			redirect("private.php", $lang->redirect_pmsdeleted);
		}
	}
}

if($mybb->input['action'] == "delete")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("private_delete_start");

	$query = $db->simple_select("privatemessages", "*", "pmid='".$mybb->get_input('pmid', MyBB::INPUT_INT)."' AND uid='".$mybb->user['uid']."' AND folder='4'", array('order_by' => 'pmid'));
	if($db->num_rows($query) == 1)
	{
		$db->delete_query("privatemessages", "pmid='".$mybb->get_input('pmid', MyBB::INPUT_INT)."'");
	}
	else
	{
		$sql_array = array(
			"folder" => 4,
			"deletetime" => TIME_NOW
		);
		$db->update_query("privatemessages", $sql_array, "pmid='".$mybb->get_input('pmid', MyBB::INPUT_INT)."' AND uid='".$mybb->user['uid']."'");
	}

	// Update PM count
	update_pm_count();

	$plugins->run_hooks("private_delete_end");
	redirect("private.php", $lang->redirect_pmsdeleted);
}

if($mybb->input['action'] == "export")
{
	if($mybb->user['totalpms'] == 0)
	{
		error($lang->error_nopms);
	}

	$plugins->run_hooks("private_export_start");

	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	$folderlist = [];
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		$folderinfo[1] = get_pm_folder_name($folderinfo[0], $folderinfo[1]);

		$folder['id'] = $folderinfo[0];
		$folder['name'] = $folderinfo[1];

		$folderlist[] = $folder;
	}

	$plugins->run_hooks("private_export_end");

	output_page(\MyBB\template('private/export.twig', [
		'folderlist' => $folderlist,
	]));
}

if($mybb->input['action'] == "do_export" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("private_do_export_start");

	$lang->private_messages_for = $lang->sprintf($lang->private_messages_for, htmlspecialchars_uni($mybb->user['username']));
	$exdate = my_date($mybb->settings['dateformat'], TIME_NOW, 0, 0);
	$extime = my_date($mybb->settings['timeformat'], TIME_NOW, 0, 0);
	$lang->exported_date = $lang->sprintf($lang->exported_date, $exdate, $extime);
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		$folderinfo[1] = get_pm_folder_name($folderinfo[0], $folderinfo[1]);
		$foldersexploded[$key] = implode("**", $folderinfo);
	}

	if($mybb->get_input('pmid', MyBB::INPUT_INT))
	{
		$wsql = "pmid='".$mybb->get_input('pmid', MyBB::INPUT_INT)."' AND uid='".$mybb->user['uid']."'";
	}
	else
	{
		if($mybb->get_input('daycut', MyBB::INPUT_INT) && ($mybb->get_input('dayway') != "disregard"))
		{
			$datecut = TIME_NOW - ($mybb->get_input('daycut', MyBB::INPUT_INT) * 86400);
			$wsql = "pm.dateline";
			if($mybb->get_input('dayway') == "older")
			{
				$wsql .= "<=";
			}
			else
			{
				$wsql .= ">=";
			}
			$wsql .= "'$datecut'";
		}
		else
		{
			$wsql = "1=1";
		}

		$mybb->input['exportfolders'] = $mybb->get_input('exportfolders', MyBB::INPUT_ARRAY);
		if(!empty($mybb->input['exportfolders']))
		{
			$folderlst = '';
			foreach($mybb->input['exportfolders'] as $key => $val)
			{
				$val = $db->escape_string($val);
				if($val == "all")
				{
					$folderlst = '';
					break;
				}
				else
				{
					if(!$folderlst)
					{
						$folderlst = " AND pm.folder IN ('$val'";
					}
					else
					{
						$folderlst .= ",'$val'";
					}
				}
			}
			if($folderlst)
			{
				$folderlst .= ")";
			}
			$wsql .= "$folderlst";
		}
		else
		{
			error($lang->error_pmnoarchivefolders);
		}

		if($mybb->get_input('exportunread', MyBB::INPUT_INT) != 1)
		{
			$wsql .= " AND pm.status!='0'";
		}
	}

	$query = $db->query("
        SELECT pm.*, fu.username AS fromusername, fu.avatar AS from_avatar, tu.username AS tousername, tu.avatar AS to_avatar
        FROM ".TABLE_PREFIX."privatemessages pm
        LEFT JOIN ".TABLE_PREFIX."users fu ON (fu.uid=pm.fromid)
        LEFT JOIN ".TABLE_PREFIX."users tu ON (tu.uid=pm.toid)
        WHERE $wsql AND pm.uid='".$mybb->user['uid']."'
        ORDER BY pm.folder ASC, pm.dateline DESC
    ");
	$numpms = $db->num_rows($query);

	if(!$numpms)
	{
		error($lang->error_nopmsarchive);
	}

	$mybb->input['exporttype'] = $mybb->get_input('exporttype');

	$pmsdownload = [];
	$ids = '';
	while($message = $db->fetch_array($query))
	{
		if($message['folder'] == 2 || $message['folder'] == 3)
		{
			// Sent Items or Drafts Folder Check
			if($message['toid'])
			{
				$tofromuid = $message['toid'];
				if($mybb->input['exporttype'] == "txt")
				{
					$message['tofromusername'] = $message['tousername'];
				}
				else
				{
					$message['tofromusername'] = build_profile_link($message['tousername'], $tofromuid);
				}
			}
			else
			{
				$message['tofromusername'] = $lang->not_sent;
			}

			$message['tofrom'] = $lang->to;
		}
		else
		{
			$tofromuid = $message['fromid'];
			if($mybb->input['exporttype'] == "txt")
			{
				$message['tofromusername'] = $message['fromusername'];
			}
			else
			{
				$message['tofromusername'] = build_profile_link($message['fromusername'], $tofromuid);
			}

			if($tofromuid == 0)
			{
				$message['tofromusername'] = $lang->mybb_engine;
			}

			$message['tofrom'] = $lang->from;
		}

		if($tofromuid == 0)
		{
			$message['fromusername'] = $lang->mybb_engine;
		}

		if(!$message['toid'] && $message['folder'] == 3)
		{
			$message['tousername'] = $lang->not_sent;
		}

		$message['subject'] = $parser->parse_badwords($message['subject']);
		if($message['folder'] != 3)
		{
			$message['senddate'] = my_date($mybb->settings['dateformat'], $message['dateline'], "", false);
			$sendtime = my_date($mybb->settings['timeformat'], $message['dateline'], "", false);
			$message['senddate'] .= " $lang->at $sendtime";
		}
		else
		{
			$message['senddate'] = $lang->not_sent;
		}

		if($mybb->input['exporttype'] == "html")
		{
			$parser_options = array(
				"allow_html" => $mybb->settings['pmsallowhtml'],
				"allow_mycode" => $mybb->settings['pmsallowmycode'],
				"allow_smilies" => 0,
				"allow_imgcode" => $mybb->settings['pmsallowimgcode'],
				"allow_videocode" => $mybb->settings['pmsallowvideocode'],
				"me_username" => $mybb->user['username'],
				"filter_badwords" => 1
			);

			$message['message'] = $parser->parse_message($message['message'], $parser_options);
		}

		if($mybb->input['exporttype'] == "txt" || $mybb->input['exporttype'] == "csv")
		{
			$message['message'] = str_replace("\r\n", "\n", $message['message']);
			$message['message'] = str_replace("\n", "\r\n", $message['message']);
		}

		if($mybb->input['exporttype'] == "csv")
		{
			$message['message'] = my_escape_csv($message['message']);
			$message['subject'] = my_escape_csv($message['subject']);
			$message['tousername'] = my_escape_csv($message['tousername']);
			$message['fromusername'] = my_escape_csv($message['fromusername']);
		}

		if(empty($donefolder[$message['folder']]))
		{
			reset($foldersexploded);
			foreach($foldersexploded as $key => $val)
			{
				$message['isfolderheader'] = false;
				$folderinfo = explode("**", $val, 2);
				if($folderinfo[0] == $message['folder'])
				{
					$message['foldername'] = $folderinfo[1];
					if($mybb->input['exporttype'] != "csv")
					{
						if($mybb->input['exporttype'] != "html")
						{
							$mybb->input['exporttype'] == "txt";
						}

						$message['isfolderheader'] = true;
						$pmsdownload[] = $message;
					}
					else
					{
						$message['foldername'] = my_escape_csv($folderinfo[1]);
					}

					$donefolder[$message['folder']] = 1;
				}
			}
		}

		$pmsdownload[] = $message;
		$ids .= ",'{$message['pmid']}'";
	}

	if($mybb->input['exporttype'] == "html")
	{
		// Gather global stylesheet for HTML
		$query = $db->simple_select("themestylesheets", "stylesheet", "sid = '1'", array('limit' => 1));
		$css = $db->fetch_field($query, "stylesheet");
	}

	$plugins->run_hooks("private_do_export_end");

	if($mybb->get_input('deletepms', MyBB::INPUT_INT) == 1)
	{
		// Delete the archived pms
		$db->delete_query("privatemessages", "pmid IN ('0'$ids)");
		// Update PM count
		update_pm_count();
	}

	if($mybb->input['exporttype'] == "html")
	{
		$filename = "pm-archive.html";
		$contenttype = "text/html";
	}
	elseif($mybb->input['exporttype'] == "csv")
	{
		$filename = "pm-archive.csv";
		$contenttype = "application/octet-stream";
	}
	else
	{
		$filename = "pm-archive.txt";
		$contenttype = "text/plain";
	}

	$archived = str_replace("\\\'", "'", $archived);
	header("Content-disposition: filename=$filename");
	header("Content-type: ".$contenttype);

	if($mybb->input['exporttype'] == "html")
	{
		output_page(\MyBB\template('private/export/html.twig', [
			'pmsdownload' => $pmsdownload,
			'css' => $css,
		]));
	}
	elseif($mybb->input['exporttype'] == "csv")
	{
		echo "\xEF\xBB\xBF"; // UTF-8 BOM
		output_page(\MyBB\template('private/export/csv.twig', [
			'pmsdownload' => $pmsdownload,
		]));
		exit;
	}
	else
	{
		echo "\xEF\xBB\xBF"; // UTF-8 BOM
		output_page(\MyBB\template('private/export/txt.twig', [
			'pmsdownload' => $pmsdownload,
		]));
		exit;
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("private_folder");

	if(!$input['fid'] || !array_key_exists($input['fid'], $foldernames))
	{
		$input['fid'] = 1;
	}

	$private['folder'] = $folder = $fid = $input['fid'];
	$private['foldername'] = $foldernames[$folder];

	if($private['folder'] == 2 || $private['folder'] == 3)
	{
		// Sent Items or Drafts Folder
		$private['sender'] = $lang->sentto;
	}
	else
	{
		$private['sender'] = $lang->sender;
	}

	$mybb->input['order'] = $mybb->get_input('order');
	switch(my_strtolower($mybb->input['order']))
	{
		case "asc":
			$sortordernow = "asc";
			$private['oppsort'] = $lang->desc;
			$private['oppsortnext'] = "desc";
			break;
		default:
			$sortordernow = "desc";
			$private['oppsort'] = $lang->asc;
			$private['oppsortnext'] = "asc";
			break;
	}

	// Sort by which field?
	$sortby = $mybb->get_input('sortby');
	switch($mybb->get_input('sortby'))
	{
		case "subject":
			$sortfield = "subject";
			break;
		case "username":
			$sortfield = "username";
			break;
		default:
			$sortby = "dateline";
			$sortfield = "dateline";
			$mybb->input['sortby'] = "dateline";
			break;
	}

	$private['orderarrow'] = array('subject' => false, 'username' => false, 'dateline' => false);
	$private['orderarrow'][$sortby] = true;

	// Do Multi Pages
	$selective = "";
	if($fid == 1)
	{
		$selective = " AND status='0'";
	}

	$query = $db->simple_select("privatemessages", "COUNT(*) AS total", "uid='".$mybb->user['uid']."' AND folder='$folder'$selective");
	$pmscount = $db->fetch_field($query, "total");

	if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	$perpage = $mybb->settings['threadsperpage'];
	$page = $mybb->get_input('page', MyBB::INPUT_INT);

	if($page > 0)
	{
		$start = ($page-1) *$perpage;
		$pages = ceil($pmscount / $perpage);
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

	$end = $start + $perpage;
	$lower = $start + 1;
	$upper = $end;

	if($upper > $pmscount)
	{
		$upper = $pmscount;
	}

	if($mybb->input['order'] || ($sortby && $sortby != "dateline"))
	{
		$page_url = "private.php?fid={$input['fid']}&sortby={$sortby}&order={$sortordernow}";
	}
	else
	{
		$page_url = "private.php?fid={$input['fid']}";
	}

	$multipage = multipage($pmscount, $perpage, $page, $page_url);
	$selective = '';
	$messagelist = [];

	$icon_cache = $cache->read("posticons");

	// Cache users in multiple recipients for sent & drafts folder
	if($folder == 2 || $folder == 3)
	{
		if($sortfield == "username")
		{
			$u = "u.";
		}
		else
		{
			$u = "pm.";
		}

		// Get all recipients into an array
		$cached_users = $get_users = array();
		$users_query = $db->query("
            SELECT pm.recipients
            FROM ".TABLE_PREFIX."privatemessages pm
            LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.toid)
            WHERE pm.folder='{$folder}' AND pm.uid='{$mybb->user['uid']}'
            ORDER BY {$u}{$sortfield} {$sortordernow}
            LIMIT {$start}, {$perpage}
        ");
		while($row = $db->fetch_array($users_query))
		{
			$recipients = my_unserialize($row['recipients']);
			if(is_array($recipients['to']) && count($recipients['to']))
			{
				$get_users = array_merge($get_users, $recipients['to']);
			}

			if(isset($recipients['bcc']) && is_array($recipients['bcc']) && count($recipients['bcc']))
			{
				$get_users = array_merge($get_users, $recipients['bcc']);
			}
		}

		$get_users = implode(',', array_unique($get_users));

		// Grab info
		if($get_users)
		{
			$users_query = $db->simple_select("users", "uid, username, usergroup, displaygroup, avatar", "uid IN ({$get_users})");
			while($user = $db->fetch_array($users_query))
			{
				$cached_users[$user['uid']] = $user;
			}
		}
	}

	if($folder == 2 || $folder == 3)
	{
		if($sortfield == "username")
		{
			$pm = "tu.";
		}
		else
		{
			$pm = "pm.";
		}
	}
	else
	{
		if($fid == 1)
		{
			$selective = " AND pm.status='0'";
		}

		if($sortfield == "username")
		{
			$pm = "fu.";
		}
		else
		{
			$pm = "pm.";
		}
	}

	$messagelist = [];
	$query = $db->query("
        SELECT pm.*, fu.username AS fromusername, fu.avatar AS from_avatar, tu.username AS tousername, tu.avatar AS to_avatar
        FROM ".TABLE_PREFIX."privatemessages pm
        LEFT JOIN ".TABLE_PREFIX."users fu ON (fu.uid=pm.fromid)
        LEFT JOIN ".TABLE_PREFIX."users tu ON (tu.uid=pm.toid)
        WHERE pm.folder='$folder' AND pm.uid='".$mybb->user['uid']."'{$selective}
        ORDER BY {$pm}{$sortfield} {$sortordernow}
        LIMIT $start, $perpage
    ");

	if($db->num_rows($query) > 0)
	{
		while($message = $db->fetch_array($query))
		{
			// Determine Folder Icon
			if($message['status'] == 0)
			{
				$message['msgstatus'] = 'new_pm';
				$message['msgalt'] = $lang->new_pm;
			}
			elseif($message['status'] == 1)
			{
				$message['msgstatus'] = 'old_pm';
				$message['msgalt'] = $lang->old_pm;
			}
			elseif($message['status'] == 3)
			{
				$message['msgstatus'] = 're_pm';
				$message['msgalt'] = $lang->reply_pm;
			}
			elseif($message['status'] == 4)
			{
				$message['msgstatus'] = 'fw_pm';
				$message['msgalt'] = $lang->fwd_pm;
			}

			$message['multiplerecipients'] = false;
			if($folder == 2 || $folder == 3)
			{
				// Sent Items or Drafts Folder Check
				$recipients = my_unserialize($message['recipients']);
				if(isset($recipients['to']) && count($recipients['to']) > 1 || (isset($recipients['to']) && count($recipients['to']) == 1 && isset($recipients['bcc']) && count($recipients['bcc']) > 0))
				{
					$message['multiplerecipients'] = true;
					$message['tousers'] = $message['bbcusers'] = [];
					foreach($recipients['to'] as $uid)
					{
						$user = $cached_users[$uid];
						$user['profilelink'] = get_profile_link($uid);
						$user['username_raw'] = $user['username'];
						$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
						$message['tousers'][] = $user;
					}
					if(isset($recipients['bcc']) && is_array($recipients['bcc']) && count($recipients['bcc']))
					{
						foreach($recipients['bcc'] as $uid)
						{
							$user = $cached_users[$uid];
							$user['profilelink'] = get_profile_link($uid);
							$user['username_raw'] = $user['username'];
							$user['username'] = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
							$message['bbcusers'][] = $user;
						}
					}
				}
				elseif($message['toid'])
				{
					$message['to_from_avatar'] = $message['to_avatar'];
					$message['tofromusername'] = $message['tousername'];
					$message['tofromuid'] = $message['toid'];
				}
				else
				{
					$message['tofromusername'] = $lang->not_sent;
				}
			}
			else
			{
				$message['to_from_avatar'] = $message['from_avatar'];
				$message['tofromusername'] = $message['fromusername'];
				$message['tofromuid'] = $message['fromid'];
				if($message['tofromuid'] == 0)
				{
					$message['tofromusername'] = $lang->mybb_engine;
				}

				if(!$message['tofromusername'])
				{
					$message['tofromuid'] = 0;
					$message['tofromusername'] = $lang->na;
				}
			}

			$message['username_raw'] = $message['tofromusername'];
			$message['avatar'] = $message['to_from_avatar'];
			$message['username'] = build_profile_link($message['tofromusername'], $message['tofromuid']);

			$message['denyreceipt'] = false;
			if($mybb->usergroup['candenypmreceipts'] == 1 && $message['receipt'] == 1 && $message['folder'] != 3 && $message['folder'] != 2)
			{
				$message['denyreceipt'] = true;
			}

			$message['hasicon'] = false;
			if($message['icon'] > 0 && $icon_cache[$message['icon']])
			{
				$message['hasicon'] = true;
				$icon = $icon_cache[$message['icon']];
				$icon['path'] = str_replace("{theme}", $theme['imgdir'], $icon['path']);
				$message['icon_path'] = $icon['path'];
				$message['icon_name'] = $icon['name'];
			}

			if(!trim($message['subject']))
			{
				$message['subject'] = $lang->pm_no_subject;
			}

			$message['subject'] = $parser->parse_badwords($message['subject']);

			if($message['folder'] != 3)
			{
				$message['senddate'] = my_date('relative', $message['dateline']);
			}
			else
			{
				$message['senddate'] = $lang->not_sent;
			}

			$plugins->run_hooks("private_message");

			$messagelist[] = $message;
		}
	}

	if($mybb->usergroup['pmquota'] != 0)
	{
		$query = $db->simple_select("privatemessages", "COUNT(*) AS total", "uid='".$mybb->user['uid']."'");
		$pmscount = $db->fetch_field($query, 'total');
		if($pmscount == 0)
		{
			$private['spaceused'] = 0;
		}
		else
		{
			$private['spaceused'] = $pmscount / $mybb->usergroup['pmquota'] * 100;
		}

		$private['spaceused2'] = 100 - $private['spaceused'];
		if($private['spaceused'] <= "50")
		{
			$private['spaceused_severity'] = "low";
			$private['belowhalf'] = round($private['spaceused'], 0)."%";
			if((int)$private['belowhalf'] > 100)
			{
				$private['belowhalf'] = "100%";
			}
		}
		else
		{
			if($private['spaceused'] <= "75")
			{
				$private['spaceused_severity'] = "medium";
			}
			else
			{
				$private['spaceused_severity'] = "high";
			}

			$private['overhalf'] = round($private['spaceused'], 0)."%";
			if((int)$private['overhalf'] > 100)
			{
				$private['overhalf'] = "100%";
			}
		}

		if($private['spaceused'] > 100)
		{
			$private['spaceused'] = 100;
			$private['spaceused2'] = 0;
		}
	}

	$private['pmtotal'] = $pmscount;

	$plugins->run_hooks("private_end");

	output_page(\MyBB\template('private/private.twig', [
		'private' => $private,
		'messagelist' => $messagelist,
		'multipage' => $multipage,
		'folders' => $folders,
	]));
}
