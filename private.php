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

$templatelist = "private_send,private_send_buddyselect,private_read,private_tracking,private_tracking_readmessage,private_tracking_unreadmessage,private_orderarrow,usercp_nav_attachments,usercp_nav_messenger_compose,private_tracking_readmessage_stop";
$templatelist .= ",private_folders,private_folders_folder,private_folders_folder_unremovable,private,usercp_nav,private_empty_folder,private_empty,private_archive_txt,private_archive_csv,private_archive_html,private_tracking_unreadmessage_stop";
$templatelist .= ",usercp_nav_messenger,usercp_nav_changename,usercp_nav_profile,usercp_nav_misc,multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,multipage_start,usercp_nav_editsignature,posticons_icon";
$templatelist .= ",private_messagebit,codebuttons,smilieinsert,smilieinsert_getmore,smilieinsert_smilie,smilieinsert_smilie_empty,posticons,private_send_autocomplete,private_messagebit_denyreceipt,private_read_to,postbit_online,postbit_warninglevel_formatted,postbit_iplogged_hiden";
$templatelist .= ",postbit_delete_pm,postbit,private_tracking_nomessage,private_nomessages,postbit_author_guest,private_multiple_recipients_user,private_multiple_recipients_bcc,private_multiple_recipients,usercp_nav_messenger_folder";
$templatelist .= ",private_search_messagebit,private_search_results_nomessages,private_search_results,private_advanced_search,previewpost,private_send_tracking,private_send_signature,private_read_bcc,private_composelink,postbit_purgespammer";
$templatelist .= ",private_archive,private_quickreply,private_pmspace,private_limitwarning,postbit_groupimage,postbit_offline,postbit_www,postbit_replyall_pm,postbit_signature,postbit_classic,postbit_gotopost,postbit_userstar,postbit_reputation_formatted_link,postbit_icon";
$templatelist .= ",private_archive_folders_folder,private_archive_folders,postbit_warninglevel,postbit_author_user,postbit_reply_pm,postbit_forward_pm,private_messagebit_icon,private_jump_folders_folder,private_advanced_search_folders";
$templatelist .= ",private_jump_folders,postbit_avatar,postbit_warn,postbit_rep_button,postbit_email,postbit_reputation,private_move,private_read_action,postbit_away,postbit_pm,usercp_nav_messenger_tracking,postbit_find,private_emptyexportlink";

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

if(!$mybb->user['pmfolders'])
{
	$mybb->user['pmfolders'] = "1**$%%$2**$%%$3**$%%$4**";

	$sql_array = array(
		 "pmfolders" => $mybb->user['pmfolders']
	);
	$db->update_query("users", $sql_array, "uid = ".$mybb->user['uid']);
}

// On a random occassion, recount the user's pms just to make sure everything is in sync.
$rand = my_rand(0, 9);
if($rand == 5)
{
	update_pm_count();
}

$mybb->input['fid'] = $mybb->get_input('fid', MyBB::INPUT_INT);

$folder_id = $folder_name = '';

$foldernames = array();
$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
foreach($foldersexploded as $key => $folders)
{
	$folderinfo = explode("**", $folders, 2);
	if($mybb->input['fid'] == $folderinfo[0])
	{
		$sel = ' selected="selected"';
	}
	else
	{
		$sel = '';
	}
	$folderinfo[1] = get_pm_folder_name($folderinfo[0], $folderinfo[1]);
	$foldernames[$folderinfo[0]] = $folderinfo[1];

	$folder_id = $folderinfo[0];
	$folder_name = $folderinfo[1];

	eval("\$folderjump_folder .= \"".$templates->get("private_jump_folders_folder")."\";");
	eval("\$folderoplist_folder .= \"".$templates->get("private_jump_folders_folder")."\";");
	eval("\$foldersearch_folder .= \"".$templates->get("private_jump_folders_folder")."\";");
}

eval("\$folderjump = \"".$templates->get("private_jump_folders")."\";");
eval("\$folderoplist = \"".$templates->get("private_move")."\";");
eval("\$foldersearch = \"".$templates->get("private_advanced_search_folders")."\";");

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
		$mybb->input['folder'] = $mybb->input['fid'];
		unset($mybb->input['jumpto']);
		unset($mybb->input['fromfid']);
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
	$query = $db->simple_select("privatemessages", "COUNT(*) AS total", "pmid IN(".$db->escape_string($search['querycache']).")");
	$pmscount = $db->fetch_array($query);

	if($upper > $pmscount)
	{
		$upper = $pmscount;
	}
	$multipage = multipage($pmscount['total'], $perpage, $page, "private.php?action=results&amp;sid=".htmlspecialchars_uni($mybb->get_input('sid'))."&amp;sortby={$sortby}&amp;order={$order}");
	$messagelist = '';

	$icon_cache = $cache->read("posticons");

	// Cache users in multiple recipients for sent & drafts folder
	// Get all recipients into an array
	$cached_users = $get_users = array();
	$users_query = $db->simple_select("privatemessages", "recipients", "pmid IN(".$db->escape_string($search['querycache']).")", array('limit_start' => $start, 'limit' => $perpage, 'order_by' => $query_sortby, 'order_dir' => $order));
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
		$users_query = $db->simple_select("users", "uid, username, usergroup, displaygroup", "uid IN ({$get_users})");
		while($user = $db->fetch_array($users_query))
		{
			$cached_users[$user['uid']] = $user;
		}
	}

	$query = $db->query("
		SELECT pm.*, fu.username AS fromusername, tu.username as tousername
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users fu ON (fu.uid=pm.fromid)
		LEFT JOIN ".TABLE_PREFIX."users tu ON (tu.uid=pm.toid)
		WHERE pm.pmid IN(".$db->escape_string($search['querycache']).") AND pm.uid='{$mybb->user['uid']}'
		ORDER BY pm.{$query_sortby} {$order}
		LIMIT {$start}, {$perpage}
	");
	while($message = $db->fetch_array($query))
	{
		$msgalt = $msgsuffix = $msgprefix = '';

		// Determine Folder Icon
		if($message['status'] == 0)
		{
			$msgfolder = 'new_pm.png';
			$msgalt = $lang->new_pm;
			$msgprefix = "<strong>";
			$msgsuffix = "</strong>";
		}
		elseif($message['status'] == 1)
		{
			$msgfolder = 'old_pm.png';
			$msgalt = $lang->old_pm;
		}
		elseif($message['status'] == 3)
		{
			$msgfolder = 're_pm.png';
			$msgalt = $lang->reply_pm;
		}
		else if($message['status'] == 4)
		{
			$msgfolder = 'fw_pm.png';
			$msgalt = $lang->fwd_pm;
		}

		$folder = $message['folder'];

		$tofromuid = 0;
		if($folder == 2 || $folder == 3)
		{
			// Sent Items or Drafts Folder Check
			$recipients = my_unserialize($message['recipients']);
			$to_users = $bcc_users = '';
			if(count($recipients['to']) > 1 || (count($recipients['to']) == 1 && isset($recipients['bcc']) && count($recipients['bcc']) > 0))
			{
				foreach($recipients['to'] as $uid)
				{
					$profilelink = get_profile_link($uid);
					$user = $cached_users[$uid];
					$username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
					eval("\$to_users .= \"".$templates->get("private_multiple_recipients_user")."\";");
				}
				if(isset($recipients['bcc']) && is_array($recipients['bcc']) && count($recipients['bcc']))
				{
					eval("\$bcc_users = \"".$templates->get("private_multiple_recipients_bcc")."\";");
					foreach($recipients['bcc'] as $uid)
					{
						$profilelink = get_profile_link($uid);
						$user = $cached_users[$uid];
						$username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
						eval("\$bcc_users .= \"".$templates->get("private_multiple_recipients_user")."\";");
					}
				}

				eval("\$tofromusername = \"".$templates->get("private_multiple_recipients")."\";");
			}
			else if($message['toid'])
			{
				$tofromusername = $message['tousername'];
				$tofromuid = $message['toid'];
			}
			else
			{
				$tofromusername = $lang->not_sent;
			}
		}
		else
		{
			$tofromusername = $message['fromusername'];
			$tofromuid = $message['fromid'];
			if($tofromuid == 0)
			{
				$tofromusername = $lang->mybb_engine;
			}
		}

		$tofromusername = build_profile_link($tofromusername, $tofromuid);

		$denyreceipt = '';

		if($message['icon'] > 0 && $icon_cache[$message['icon']])
		{
			$icon = $icon_cache[$message['icon']];
			$icon['path'] = str_replace("{theme}", $theme['imgdir'], $icon['path']);
			$icon['path'] = htmlspecialchars_uni($icon['path']);
			$icon['name'] = htmlspecialchars_uni($icon['name']);
			eval("\$icon = \"".$templates->get("private_messagebit_icon")."\";");
		}
		else
		{
			$icon = '&#009;';
		}

		if(!trim($message['subject']))
		{
			$message['subject'] = $lang->pm_no_subject;
		}

		$message['subject'] = $parser->parse_badwords($message['subject']);

		if(my_strlen($message['subject']) > 50)
		{
			$message['subject'] = htmlspecialchars_uni(my_substr($message['subject'], 0, 50)."...");
		}
		else
		{
			$message['subject'] = htmlspecialchars_uni($message['subject']);
		}

		if($message['folder'] != "3")
		{
			$senddate = my_date('relative', $message['dateline']);
		}
		else
		{
			$senddate = $lang->not_sent;
		}

		$foldername = $foldernames[$message['folder']];

		// What we do here is parse the post using our post parser, then strip the tags from it
		$parser_options = array(
			'allow_html' => 0,
			'allow_mycode' => 1,
			'allow_smilies' => 0,
			'allow_imgcode' => 0,
			'filter_badwords' => 1
		);
		$message['message'] = strip_tags($parser->parse_message($message['message'], $parser_options));
		if(my_strlen($message['message']) > 200)
		{
			$message['message'] = my_substr($message['message'], 0, 200)."...";
		}

		eval("\$messagelist .= \"".$templates->get("private_search_messagebit")."\";");
	}

	if($db->num_rows($query) == 0)
	{
		eval("\$messagelist = \"".$templates->get("private_search_results_nomessages")."\";");
	}

	$plugins->run_hooks("private_results_end");

	eval("\$results = \"".$templates->get("private_search_results")."\";");
	output_page($results);
}

if($mybb->input['action'] == "advanced_search")
{
	$plugins->run_hooks("private_advanced_search");

	eval("\$advanced_search = \"".$templates->get("private_advanced_search")."\";");

	output_page($advanced_search);
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
	$time_cutoff = TIME_NOW - (5 * 60 * 60);
	$query = $db->query("
		SELECT pm.pmid
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users u ON(u.uid=pm.toid)
		WHERE LOWER(u.username)='".$db->escape_string(my_strtolower($mybb->get_input('to')))."' AND pm.dateline > {$time_cutoff} AND pm.fromid='{$mybb->user['uid']}' AND pm.subject='".$db->escape_string($mybb->get_input('subject'))."' AND pm.message='".$db->escape_string($mybb->get_input('message'))."' AND pm.folder!='3'
	");
	$duplicate_check = $db->fetch_field($query, "pmid");
	if($duplicate_check)
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
	$pm['to'] = explode(",", $mybb->get_input('to'));
	$pm['to'] = array_map("trim", $pm['to']);
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

	$lang->post_icon = $lang->message_icon;

	$posticons = get_post_icons();
	$message = htmlspecialchars_uni($parser->parse_badwords($mybb->get_input('message')));
	$subject = htmlspecialchars_uni($parser->parse_badwords($mybb->get_input('subject')));

	$optionschecked = array('signature' => '', 'disablesmilies' => '', 'savecopy' => '', 'readreceipt' => '');
	$to = $bcc = '';

	if(!empty($mybb->input['preview']) || $send_errors)
	{
		$options = $mybb->get_input('options', MyBB::INPUT_ARRAY);
		if(isset($options['signature']) && $options['signature'] == 1)
		{
			$optionschecked['signature'] = 'checked="checked"';
		}
		if(isset($options['disablesmilies']) && $options['disablesmilies'] == 1)
		{
			$optionschecked['disablesmilies'] = 'checked="checked"';
		}
		if(isset($options['savecopy']) && $options['savecopy'] != 0)
		{
			$optionschecked['savecopy'] = 'checked="checked"';
		}
		if(isset($options['readreceipt']) && $options['readreceipt'] != 0)
		{
			$optionschecked['readreceipt'] = 'checked="checked"';
		}
		$to = htmlspecialchars_uni($mybb->get_input('to'));
		$bcc = htmlspecialchars_uni($mybb->get_input('bcc'));
	}

	$preview = '';
	// Preview
	if(!empty($mybb->input['preview']))
	{
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
		eval("\$preview = \"".$templates->get("previewpost")."\";");
	}
	else if(!$send_errors)
	{
		// New PM, so load default settings
		if($mybb->user['signature'] != '')
		{
			$optionschecked['signature'] = 'checked="checked"';
		}
		if($mybb->usergroup['cantrackpms'] == 1)
		{
			$optionschecked['readreceipt'] = 'checked="checked"';
		}
		$optionschecked['savecopy'] = 'checked="checked"';
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
		$message = htmlspecialchars_uni($parser->parse_badwords($pm['message']));
		$subject = htmlspecialchars_uni($parser->parse_badwords($pm['subject']));

		if($pm['folder'] == "3")
		{
			// message saved in drafts
			$mybb->input['uid'] = $pm['toid'];

			if($pm['includesig'] == 1)
			{
				$optionschecked['signature'] = 'checked="checked"';
			}
			if($pm['smilieoff'] == 1)
			{
				$optionschecked['disablesmilies'] = 'checked="checked"';
			}
			if($pm['receipt'])
			{
				$optionschecked['readreceipt'] = 'checked="checked"';
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
						$bcc .= htmlspecialchars_uni($user['username']).', ';
					}
					else
					{
						$to .= htmlspecialchars_uni($user['username']).', ';
					}
				}
			}
		}
		else
		{
			// forward/reply
			$subject = preg_replace("#(FW|RE):( *)#is", '', $subject);
			$message = "[quote='{$pm['quotename']}']\n$message\n[/quote]";
			$message = preg_replace('#^/me (.*)$#im', "* ".$pm['quotename']." \\1", $message);

			require_once MYBB_ROOT."inc/functions_posting.php";

			if($mybb->settings['maxpmquotedepth'] != '0')
			{
				$message = remove_message_quotes($message, $mybb->settings['maxpmquotedepth']);
			}

			if($mybb->input['do'] == 'forward')
			{
				$subject = "Fw: $subject";
			}
			elseif($mybb->input['do'] == 'reply')
			{
				$subject = "Re: $subject";
				$uid = $pm['fromid'];
				if($mybb->user['uid'] == $uid)
				{
					$to = $mybb->user['username'];
				}
				else
				{
					$query = $db->simple_select('users', 'username', "uid='{$uid}'");
					$to = $db->fetch_field($query, 'username');
				}
				$to = htmlspecialchars_uni($to);
			}
			else if($mybb->input['do'] == 'replyall')
			{
				$subject = "Re: $subject";

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
					$to .= $comma.htmlspecialchars_uni($user['username']);
					$comma = $lang->comma;
				}
			}
		}
	}

	// New PM with recipient preset
	if($mybb->get_input('uid', MyBB::INPUT_INT) && empty($mybb->input['preview']))
	{
		$query = $db->simple_select('users', 'username', "uid='".$mybb->get_input('uid', MyBB::INPUT_INT)."'");
		$to = htmlspecialchars_uni($db->fetch_field($query, 'username')).', ';
	}

	$max_recipients = '';
	if($mybb->usergroup['maxpmrecipients'] > 0)
	{
		$max_recipients = $lang->sprintf($lang->max_recipients, $mybb->usergroup['maxpmrecipients']);
	}

	if($send_errors)
	{
		$to = htmlspecialchars_uni($mybb->get_input('to'));
		$bcc = htmlspecialchars_uni($mybb->get_input('bcc'));
	}

	// Load the auto complete javascript if it is enabled.
	eval("\$autocompletejs = \"".$templates->get("private_send_autocomplete")."\";");

	$pmid = $mybb->get_input('pmid', MyBB::INPUT_INT);
	$do = $mybb->get_input('do');
	if($do != "forward" && $do != "reply" && $do != "replyall")
	{
		$do = '';
	}

	$buddy_select_to = $buddy_select_bcc = '';
	// See if it's actually worth showing the buddylist icon.
	if($mybb->user['buddylist'] != '' && $mybb->settings['use_xmlhttprequest'] == 1)
	{
		$buddy_select = 'to';
		eval("\$buddy_select_to = \"".$templates->get("private_send_buddyselect")."\";");
		$buddy_select = 'bcc';
		eval("\$buddy_select_bcc = \"".$templates->get("private_send_buddyselect")."\";");
	}

	// Hide tracking option if no permission
	$private_send_tracking = '';
	if($mybb->usergroup['cantrackpms'])
	{
		eval("\$private_send_tracking = \"".$templates->get("private_send_tracking")."\";");
	}

	$plugins->run_hooks("private_send_end");

	eval("\$send = \"".$templates->get("private_send")."\";");
	output_page($send);
}

if($mybb->input['action'] == "read")
{
	$plugins->run_hooks("private_read");

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
		update_pm_count($mybb->user['uid'], 6);

		// Update PM notice value if this is our last unread PM
		if($mybb->user['unreadpms']-1 <= 0 && $mybb->user['pmnotice'] == 2)
		{
			$updated_user = array(
				"pmnotice" => 1
			);
			$db->update_query("users", $updated_user, "uid='{$mybb->user['uid']}'");
		}
	}
	// Replied PM?
	else if($pm['status'] == 3 && $pm['statustime'])
	{
		$reply_string = $lang->you_replied_on;
		$reply_date = my_date('relative', $pm['statustime']);

		if((TIME_NOW - $pm['statustime']) < 3600)
		{
			// Relative string for the first hour
			$reply_string = $lang->you_replied;
		}

		$actioned_on = $lang->sprintf($reply_string, $reply_date);
		eval("\$action_time = \"".$templates->get("private_read_action")."\";");
	}
	else if($pm['status'] == 4 && $pm['statustime'])
	{
		$forward_string = $lang->you_forwarded_on;
		$forward_date = my_date('relative', $pm['statustime']);

		if((TIME_NOW - $pm['statustime']) < 3600)
		{
			$forward_string = $lang->you_forwarded;
		}

		$actioned_on = $lang->sprintf($forward_string, $forward_date);
		eval("\$action_time = \"".$templates->get("private_read_action")."\";");
	}

	$pm['userusername'] = $pm['username'];
	$pm['subject'] = htmlspecialchars_uni($parser->parse_badwords($pm['subject']));

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

	if(is_array($pm['recipients']['to']))
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
	$bcc_recipients = $to_recipients = array();
	$query = $db->simple_select('users', 'uid, username', "uid IN ({$uid_sql})");
	while($recipient = $db->fetch_array($query))
	{
		// User is a BCC recipient
		if($show_bcc && in_array($recipient['uid'], $pm['recipients']['bcc']))
		{
			$bcc_recipients[] = build_profile_link($recipient['username'], $recipient['uid']);
		}
		// User is a normal recipient
		else if(in_array($recipient['uid'], $pm['recipients']['to']))
		{
			$to_recipients[] = build_profile_link($recipient['username'], $recipient['uid']);
		}
	}

	$bcc = '';
	if(count($bcc_recipients) > 0)
	{
		$bcc_recipients = implode(', ', $bcc_recipients);
		eval("\$bcc = \"".$templates->get("private_read_bcc")."\";");
	}

	$replyall = false;
	if(count($to_recipients) > 1)
	{
		$replyall = true;
	}

	if(count($to_recipients) > 0)
	{
		$to_recipients = implode(", ", $to_recipients);
	}
	else
	{
		$to_recipients = $lang->nobody;
	}

	eval("\$pm['subject_extra'] = \"".$templates->get("private_read_to")."\";");

	add_breadcrumb($pm['subject']);
	$message = build_postbit($pm, 2);

	// Decide whether or not to show quick reply.
	$quickreply = '';
	if($mybb->settings['pmquickreply'] != 0 && $mybb->user['showquickreply'] != 0 && $mybb->usergroup['cansendpms'] != 0 && $pm['fromid'] != 0 && $pm['folder'] != 3)
	{
		$trow = alt_trow();

		$optionschecked = array('savecopy' => 'checked="checked"');
		if(!empty($mybb->user['signature']))
		{
			$optionschecked['signature'] = 'checked="checked"';
		}
		if($mybb->usergroup['cantrackpms'] == 1)
		{
			$optionschecked['readreceipt'] = 'checked="checked"';
		}

		require_once MYBB_ROOT.'inc/functions_posting.php';

		$quoted_message = array(
			'message' => htmlspecialchars_uni($parser->parse_badwords($pm['message'])),
			'username' => $pm['username'],
			'quote_is_pm' => true
		);
		$quoted_message = parse_quoted_message($quoted_message);

		if($mybb->settings['maxpmquotedepth'] != '0')
		{
			$quoted_message = remove_message_quotes($quoted_message, $mybb->settings['maxpmquotedepth']);
		}

		$subject = preg_replace("#(FW|RE):( *)#is", '', $pm['subject']);

		if($mybb->user['uid'] == $pm['fromid'])
		{
			$to = htmlspecialchars_uni($mybb->user['username']);
		}
		else
		{
			$query = $db->simple_select('users', 'username', "uid='{$pm['fromid']}'");
			$to = htmlspecialchars_uni($db->fetch_field($query, 'username'));
		}

		$private_send_tracking = '';
		if($mybb->usergroup['cantrackpms'])
		{
			$lang->options_read_receipt = $lang->quickreply_read_receipt;

			eval("\$private_send_tracking = \"".$templates->get("private_send_tracking")."\";");
		}

		eval("\$quickreply = \"".$templates->get("private_quickreply")."\";");
	}

	$plugins->run_hooks("private_read_end");

	eval("\$read = \"".$templates->get("private_read")."\";");
	output_page($read);
}

if($mybb->input['action'] == "tracking")
{
	if(!$mybb->usergroup['cantrackpms'])
	{
		error_no_permission();
	}

	$plugins->run_hooks("private_tracking_start");
	$readmessages = '';
	$unreadmessages = '';

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
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$read_multipage = multipage($postcount, $perpage, $page, "private.php?action=tracking&amp;read_page={page}");

	$query = $db->query("
		SELECT pm.pmid, pm.subject, pm.toid, pm.readtime, u.username as tousername
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.toid)
		WHERE pm.receipt='2' AND pm.folder!='3'  AND pm.status!='0' AND pm.fromid='".$mybb->user['uid']."'
		ORDER BY pm.readtime DESC
		LIMIT {$start}, {$perpage}
	");
	while($readmessage = $db->fetch_array($query))
	{
		$readmessage['subject'] = htmlspecialchars_uni($parser->parse_badwords($readmessage['subject']));
		$readmessage['profilelink'] = build_profile_link($readmessage['tousername'], $readmessage['toid']);
		$readdate = my_date('relative', $readmessage['readtime']);
		eval("\$readmessages .= \"".$templates->get("private_tracking_readmessage")."\";");
	}

	$stoptrackingread = '';
	if(!empty($readmessages))
	{
		eval("\$stoptrackingread = \"".$templates->get("private_tracking_readmessage_stop")."\";");
	}

	if(!$readmessages)
	{
		eval("\$readmessages = \"".$templates->get("private_tracking_nomessage")."\";");
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
		$start = ($page-1) * $perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$unread_multipage = multipage($postcount, $perpage, $page, "private.php?action=tracking&amp;unread_page={page}");

	$query = $db->query("
		SELECT pm.pmid, pm.subject, pm.toid, pm.dateline, u.username as tousername
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=pm.toid)
		WHERE pm.receipt='1' AND pm.folder!='3' AND pm.status='0' AND pm.fromid='".$mybb->user['uid']."'
		ORDER BY pm.dateline DESC
		LIMIT {$start}, {$perpage}
	");
	while($unreadmessage = $db->fetch_array($query))
	{
		$unreadmessage['subject'] = htmlspecialchars_uni($parser->parse_badwords($unreadmessage['subject']));
		$unreadmessage['profilelink'] = build_profile_link($unreadmessage['tousername'], $unreadmessage['toid']);
		$senddate = my_date('relative', $unreadmessage['dateline']);
		eval("\$unreadmessages .= \"".$templates->get("private_tracking_unreadmessage")."\";");
	}

	$stoptrackingunread = '';
	if(!empty($unreadmessages))
	{
		eval("\$stoptrackingunread = \"".$templates->get("private_tracking_unreadmessage_stop")."\";");
	}

	if(!$unreadmessages)
	{
		$lang->no_readmessages = $lang->no_unreadmessages;
		eval("\$unreadmessages = \"".$templates->get("private_tracking_nomessage")."\";");
	}

	$plugins->run_hooks("private_tracking_end");

	eval("\$tracking = \"".$templates->get("private_tracking")."\";");
	output_page($tracking);
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

	$folderlist = '';
	$foldersexploded = explode("$%%$", $mybb->user['pmfolders']);
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		$foldername = $folderinfo[1];
		$fid = $folderinfo[0];
		$foldername = get_pm_folder_name($fid, $foldername);

		if($folderinfo[0] == "1" || $folderinfo[0] == "2" || $folderinfo[0] == "3" || $folderinfo[0] == "4")
		{
			$foldername2 = get_pm_folder_name($fid);
			eval("\$folderlist .= \"".$templates->get("private_folders_folder_unremovable")."\";");
			unset($name);
		}
		else
		{
			eval("\$folderlist .= \"".$templates->get("private_folders_folder")."\";");
		}
	}

	$newfolders = '';
	for($i = 1; $i <= 5; ++$i)
	{
		$fid = "new$i";
		$foldername = '';
		eval("\$newfolders .= \"".$templates->get("private_folders_folder")."\";");
	}

	$plugins->run_hooks("private_folders_end");

	eval("\$folders = \"".$templates->get("private_folders")."\";");
	output_page($folders);
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
		if(empty($donefolders[$val]) )// Probably was a check for duplicate folder names, but doesn't seem to be used now
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
				switch($fid)
				{
					case 1:
						if($val == $lang->folder_inbox || trim($val) == '')
						{
							$val = '';
						}
						break;
					case 2:
						if($val == $lang->folder_sent_items || trim($val) == '')
						{
							$val = '';
						}
						break;
					case 3:
						if($val == $lang->folder_drafts || trim($val) == '')
						{
							$val = '';
						}
						break;
					case 4:
						if($val == $lang->folder_trash || trim($val) == '')
						{
							$val = '';
						}
						break;
				}
			}

			if($val != '' && trim($val) == '' && !($key >= 1 && $key <= 4))
			{
				// If the name only contains whitespace and it's not a default folder, print an error
				error($lang->error_emptypmfoldername);
			}

			if($val != '' || ($key >= 1 && $key <= 4))
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
	$folderlist = '';
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		$fid = $folderinfo[0];
		$foldername = get_pm_folder_name($fid, $folderinfo[1]);
		$query = $db->simple_select("privatemessages", "COUNT(*) AS pmsinfolder", " folder='$fid' AND uid='".$mybb->user['uid']."'");
		$thing = $db->fetch_array($query);
		$foldercount = my_number_format($thing['pmsinfolder']);
		eval("\$folderlist .= \"".$templates->get("private_empty_folder")."\";");
	}

	$plugins->run_hooks("private_empty_end");

	eval("\$folders = \"".$templates->get("private_empty")."\";");
	output_page($folders);
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
		$mybb->input['check'] = $mybb->get_input('check', MyBB::INPUT_ARRAY);
		if(!empty($mybb->input['check']))
		{
			foreach($mybb->input['check'] as $key => $val)
			{
				$sql_array = array(
					"folder" => $mybb->input['fid']
				);
				$db->update_query("privatemessages", $sql_array, "pmid='".(int)$key."' AND uid='".$mybb->user['uid']."'");
			}
		}
		// Update PM count
		update_pm_count();

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
	$folder_name = $folder_id = '';
	foreach($foldersexploded as $key => $folders)
	{
		$folderinfo = explode("**", $folders, 2);
		$folderinfo[1] = get_pm_folder_name($folderinfo[0], $folderinfo[1]);

		$folder_id = $folderinfo[0];
		$folder_name = $folderinfo[1];

		eval("\$folderlist_folder .= \"".$templates->get("private_archive_folders_folder")."\";");
	}

	eval("\$folderlist = \"".$templates->get("private_archive_folders")."\";");

	$plugins->run_hooks("private_export_end");

	eval("\$archive = \"".$templates->get("private_archive")."\";");

	output_page($archive);
}

if($mybb->input['action'] == "do_export" && $mybb->request_method == "post")
{
	// Verify incoming POST request
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("private_do_export_start");

	$lang->private_messages_for = $lang->sprintf($lang->private_messages_for, $mybb->user['username']);
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
			$datecut = TIME_NOW-($mybb->get_input('daycut', MyBB::INPUT_INT) * 86400);
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
		SELECT pm.*, fu.username AS fromusername, tu.username AS tousername
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

	$pmsdownload = $ids = '';
	while($message = $db->fetch_array($query))
	{
		if($message['folder'] == 2 || $message['folder'] == 3)
		{ // Sent Items or Drafts Folder Check
			if($message['toid'])
			{
				$tofromuid = $message['toid'];
				if($mybb->input['exporttype'] == "txt")
				{
					$tofromusername = $message['tousername'];
				}
				else
				{
					$tofromusername = build_profile_link($message['tousername'], $tofromuid);
				}
			}
			else
			{
				$tofromusername = $lang->not_sent;
			}
			$tofrom = $lang->to;
		}
		else
		{
			$tofromuid = $message['fromid'];
			if($mybb->input['exporttype'] == "txt")
			{
				$tofromusername = $message['fromusername'];
			}
			else
			{
				$tofromusername = build_profile_link($message['fromusername'], $tofromuid);
			}

			if($tofromuid == 0)
			{
				$tofromusername = $lang->mybb_engine;
			}
			$tofrom = $lang->from;
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
		if($message['folder'] != "3")
		{
			$senddate = my_date($mybb->settings['dateformat'], $message['dateline']);
			$sendtime = my_date($mybb->settings['timeformat'], $message['dateline']);
			$senddate .= " $lang->at $sendtime";
		}
		else
		{
			$senddate = $lang->not_sent;
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
			$message['subject'] = htmlspecialchars_uni($message['subject']);
		}

		if($mybb->input['exporttype'] == "txt" || $mybb->input['exporttype'] == "csv")
		{
			$message['message'] = str_replace("\r\n", "\n", $message['message']);
			$message['message'] = str_replace("\n", "\r\n", $message['message']);
		}

		if($mybb->input['exporttype'] == "csv")
		{
			$message['message'] = addslashes($message['message']);
			$message['subject'] = addslashes($message['subject']);
			$message['tousername'] = addslashes($message['tousername']);
			$message['fromusername'] = addslashes($message['fromusername']);
		}

		if(empty($donefolder[$message['folder']]))
		{
			reset($foldersexploded);
			foreach($foldersexploded as $key => $val)
			{
				$folderinfo = explode("**", $val, 2);
				if($folderinfo[0] == $message['folder'])
				{
					$foldername = $folderinfo[1];
					if($mybb->input['exporttype'] != "csv")
					{
						if($mybb->input['exporttype'] != "html")
						{
							$mybb->input['exporttype'] == "txt";
						}
						eval("\$pmsdownload .= \"".$templates->get("private_archive_".$mybb->input['exporttype']."_folderhead", 1, 0)."\";");
					}
					else
					{
						$foldername = addslashes($folderinfo[1]);
					}
					$donefolder[$message['folder']] = 1;
				}
			}
		}

		eval("\$pmsdownload .= \"".$templates->get("private_archive_".$mybb->input['exporttype']."_message", 1, 0)."\";");
		$ids .= ",'{$message['pmid']}'";
	}

	if($mybb->input['exporttype'] == "html")
	{
		// Gather global stylesheet for HTML
		$query = $db->simple_select("themestylesheets", "stylesheet", "sid = '1'", array('limit' => 1));
		$css = $db->fetch_field($query, "stylesheet");
	}

	$plugins->run_hooks("private_do_export_end");

	eval("\$archived = \"".$templates->get("private_archive_".$mybb->input['exporttype'], 1, 0)."\";");
	if($mybb->get_input('deletepms', MyBB::INPUT_INT) == 1)
	{ // delete the archived pms
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

	$archived = str_replace("\\\'","'",$archived);
	header("Content-disposition: filename=$filename");
	header("Content-type: ".$contenttype);

	if($mybb->input['exporttype'] == "html")
	{
		output_page($archived);
	}
	else
	{
		echo $archived;
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("private_inbox");

	if(!$mybb->input['fid'] || !array_key_exists($mybb->input['fid'], $foldernames))
	{
		$mybb->input['fid'] = 1;
	}

	$folder = $mybb->input['fid'];
	$foldername = $foldernames[$folder];

	if($folder == 2 || $folder == 3)
	{ // Sent Items Folder
		$sender = $lang->sentto;
	}
	else
	{
		$sender = $lang->sender;
	}

	$mybb->input['order'] = htmlspecialchars_uni($mybb->get_input('order'));
	$ordersel = array('asc' => '', 'desc');
	switch(my_strtolower($mybb->input['order']))
	{
		case "asc":
			$sortordernow = "asc";
			$ordersel['asc'] = "selected=\"selected\"";
			$oppsort = $lang->desc;
			$oppsortnext = "desc";
			break;
		default:
			$sortordernow = "desc";
			$ordersel['desc'] = "selected=\"selected\"";
			$oppsort = $lang->asc;
			$oppsortnext = "asc";
			break;
	}

	// Sort by which field?
	$sortby = htmlspecialchars_uni($mybb->get_input('sortby'));
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
	$orderarrow = $sortsel = array('subject' => '', 'username' => '', 'dateline' => '');
	$sortsel[$sortby] = "selected=\"selected\"";

	eval("\$orderarrow['$sortby'] = \"".$templates->get("private_orderarrow")."\";");

	// Do Multi Pages
	$query = $db->simple_select("privatemessages", "COUNT(*) AS total", "uid='".$mybb->user['uid']."' AND folder='$folder'");
	$pmscount = $db->fetch_array($query);

	if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
	{
		$mybb->settings['threadsperpage'] = 20;
	}

	$perpage = $mybb->settings['threadsperpage'];
	$page = $mybb->get_input('page', MyBB::INPUT_INT);

	if($page > 0)
	{
		$start = ($page-1) *$perpage;
	}
	else
	{
		$start = 0;
		$page = 1;
	}

	$end = $start + $perpage;
	$lower = $start+1;
	$upper = $end;

	if($upper > $pmscount)
	{
		$upper = $pmscount;
	}

	if($mybb->input['order'] || ($sortby && $sortby != "dateline"))
	{
		$page_url = "private.php?fid={$folder}&sortby={$sortby}&order={$mybb->input['order']}";
	}
	else
	{
		$page_url = "private.php?fid={$folder}";
	}

	$multipage = multipage($pmscount['total'], $perpage, $page, $page_url);
	$messagelist = '';

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
			ORDER BY {$u}{$sortfield} {$mybb->input['order']}
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
			$users_query = $db->simple_select("users", "uid, username, usergroup, displaygroup", "uid IN ({$get_users})");
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
		if($sortfield == "username")
		{
			$pm = "fu.";
		}
		else
		{
			$pm = "pm.";
		}
	}

	$query = $db->query("
		SELECT pm.*, fu.username AS fromusername, tu.username as tousername
		FROM ".TABLE_PREFIX."privatemessages pm
		LEFT JOIN ".TABLE_PREFIX."users fu ON (fu.uid=pm.fromid)
		LEFT JOIN ".TABLE_PREFIX."users tu ON (tu.uid=pm.toid)
		WHERE pm.folder='$folder' AND pm.uid='".$mybb->user['uid']."'
		ORDER BY {$pm}{$sortfield} {$sortordernow}
		LIMIT $start, $perpage
	");

	if($db->num_rows($query) > 0)
	{
		while($message = $db->fetch_array($query))
		{
			$msgalt = $msgsuffix = $msgprefix = '';
			// Determine Folder Icon
			if($message['status'] == 0)
			{
				$msgfolder = 'new_pm.png';
				$msgalt = $lang->new_pm;
				$msgprefix = "<strong>";
				$msgsuffix = "</strong>";
			}
			elseif($message['status'] == 1)
			{
				$msgfolder = 'old_pm.png';
				$msgalt = $lang->old_pm;
			}
			elseif($message['status'] == 3)
			{
				$msgfolder = 're_pm.png';
				$msgalt = $lang->reply_pm;
			}
			elseif($message['status'] == 4)
			{
				$msgfolder = 'fw_pm.png';
				$msgalt = $lang->fwd_pm;
			}

			$tofromuid = 0;
			if($folder == 2 || $folder == 3)
			{ // Sent Items or Drafts Folder Check
				$recipients = my_unserialize($message['recipients']);
				$to_users = $bcc_users = '';
				if(count($recipients['to']) > 1 || (count($recipients['to']) == 1 && isset($recipients['bcc']) && count($recipients['bcc']) > 0))
				{
					foreach($recipients['to'] as $uid)
					{
						$profilelink = get_profile_link($uid);
						$user = $cached_users[$uid];
						$username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
						if(!$user['username'])
						{
							$username = $lang->na;
						}
						eval("\$to_users .= \"".$templates->get("private_multiple_recipients_user")."\";");
					}
					if(isset($recipients['bcc']) && is_array($recipients['bcc']) && count($recipients['bcc']))
					{
						eval("\$bcc_users = \"".$templates->get("private_multiple_recipients_bcc")."\";");
						foreach($recipients['bcc'] as $uid)
						{
							$profilelink = get_profile_link($uid);
							$user = $cached_users[$uid];
							$username = format_name($user['username'], $user['usergroup'], $user['displaygroup']);
							if(!$user['username'])
							{
								$username = $lang->na;
							}
							eval("\$bcc_users .= \"".$templates->get("private_multiple_recipients_user")."\";");
						}
					}

					eval("\$tofromusername = \"".$templates->get("private_multiple_recipients")."\";");
				}
				else if($message['toid'])
				{
					$tofromusername = $message['tousername'];
					$tofromuid = $message['toid'];
				}
				else
				{
					$tofromusername = $lang->not_sent;
				}
			}
			else
			{
				$tofromusername = $message['fromusername'];
				$tofromuid = $message['fromid'];
				if($tofromuid == 0)
				{
					$tofromusername = $lang->mybb_engine;
				}

				if(!$tofromusername)
				{
					$tofromuid = 0;
					$tofromusername = $lang->na;
				}
			}

			$tofromusername = build_profile_link($tofromusername, $tofromuid);

			if($mybb->usergroup['candenypmreceipts'] == 1 && $message['receipt'] == '1' && $message['folder'] != '3' && $message['folder'] != 2)
			{
				eval("\$denyreceipt = \"".$templates->get("private_messagebit_denyreceipt")."\";");
			}
			else
			{
				$denyreceipt = '';
			}

			if($message['icon'] > 0 && $icon_cache[$message['icon']])
			{
				$icon = $icon_cache[$message['icon']];
				$icon['path'] = str_replace("{theme}", $theme['imgdir'], $icon['path']);
				$icon['path'] = htmlspecialchars_uni($icon['path']);
				$icon['name'] = htmlspecialchars_uni($icon['name']);
				eval("\$icon = \"".$templates->get("private_messagebit_icon")."\";");
			}
			else
			{
				$icon = '&#009;';
			}

			if(!trim($message['subject']))
			{
				$message['subject'] = $lang->pm_no_subject;
			}

			$message['subject'] = htmlspecialchars_uni($parser->parse_badwords($message['subject']));
			if($message['folder'] != "3")
			{
				$senddate = my_date('relative', $message['dateline']);
			}
			else
			{
				$senddate = $lang->not_sent;
			}

			$plugins->run_hooks("private_message");

			eval("\$messagelist .= \"".$templates->get("private_messagebit")."\";");
		}
	}
	else
	{
		eval("\$messagelist .= \"".$templates->get("private_nomessages")."\";");
	}

	$pmspacebar = '';
	if($mybb->usergroup['pmquota'] != '0' && $mybb->usergroup['cancp'] != 1)
	{
		$query = $db->simple_select("privatemessages", "COUNT(*) AS total", "uid='".$mybb->user['uid']."'");
		$pmscount = $db->fetch_array($query);
		if($pmscount['total'] == 0)
		{
			$spaceused = 0;
		}
		else
		{
			$spaceused = $pmscount['total'] / $mybb->usergroup['pmquota'] * 100;
		}
		$spaceused2 = 100 - $spaceused;
		$belowhalf = $overhalf = '';
		if($spaceused <= "50")
		{
			$spaceused_severity = "low";
			$belowhalf = round($spaceused, 0)."%";
			if((int)$belowhalf > 100)
			{
				$belowhalf = "100%";
			}
		}
		else
		{
			if($spaceused <= "75")
			{
				$spaceused_severity = "medium";
			}

			else
			{
				$spaceused_severity = "high";
			}
			
			$overhalf = round($spaceused, 0)."%";
			if((int)$overhalf > 100)
			{
				$overhalf = "100%";
			}
		}

		if($spaceused > 100)
		{
			$spaceused = 100;
			$spaceused2 = 0;
		}

		eval("\$pmspacebar = \"".$templates->get("private_pmspace")."\";");
	}

	$composelink = '';
	if($mybb->usergroup['cansendpms'] == 1)
	{
		eval("\$composelink = \"".$templates->get("private_composelink")."\";");
	}

	$emptyexportlink = '';
	if($mybb->user['totalpms'] > 0)
	{
		eval("\$emptyexportlink = \"".$templates->get("private_emptyexportlink")."\";");
	}

	$limitwarning = '';
	if($mybb->usergroup['pmquota'] != "0" && $pmscount['total'] >= $mybb->usergroup['pmquota'] && $mybb->usergroup['cancp'] != 1)
	{
		eval("\$limitwarning = \"".$templates->get("private_limitwarning")."\";");
	}

	$plugins->run_hooks("private_end");

	eval("\$folder = \"".$templates->get("private")."\";");
	output_page($folder);
}
