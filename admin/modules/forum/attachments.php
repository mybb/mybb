<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->attachments, "index.php?module=forum-attachments");

if($mybb->input['action'] == "stats" || $mybb->input['action'] == "orphans" || !$mybb->input['action'])
{
	$sub_tabs['find_attachments'] = array(
		'title' => $lang->find_attachments,
		'link' => "index.php?module=forum-attachments",
		'description' => $lang->find_attachments_desc
	);

	$sub_tabs['find_orphans'] = array(
		'title' => $lang->find_orphans,
		'link' => "index.php?module=forum-attachments&amp;action=orphans",
		'description' => $lang->find_orphans_desc
	);

	$sub_tabs['stats'] = array(
		'title' => $lang->attachment_stats,
		'link' => "index.php?module=forum-attachments&amp;action=stats",
		'description' => $lang->attachment_stats_desc
	);
}

$plugins->run_hooks("admin_forum_attachments_begin");

if($mybb->input['action'] == "delete")
{
	$plugins->run_hooks("admin_forum_attachments_delete");

	if(!is_array($mybb->input['aids']))
	{
		$mybb->input['aids'] = array($mybb->get_input('aid', MyBB::INPUT_INT));
	}
	else
	{
		$mybb->input['aids'] = array_map("intval", $mybb->input['aids']);
	}

	if(count($mybb->input['aids']) < 1)
	{
		flash_message($lang->error_nothing_selected, 'error');
		admin_redirect("index.php?module=forum-attachments");
	}

	if($mybb->request_method == "post")
	{
		require_once MYBB_ROOT."inc/functions_upload.php";

		$query = $db->simple_select("attachments", "aid,pid,posthash, filename", "aid IN (".implode(",", $mybb->input['aids']).")");
		while($attachment = $db->fetch_array($query))
		{
			if(!$attachment['pid'])
			{
				remove_attachment(null, $attachment['posthash'], $attachment['aid']);
				// Log admin action
				log_admin_action($attachment['aid'], $attachment['filename']);
			}
			else
			{
				remove_attachment($attachment['pid'], null, $attachment['aid']);
				// Log admin action
				log_admin_action($attachment['aid'], $attachment['filename'], $attachment['pid']);
			}
		}

		$plugins->run_hooks("admin_forum_attachments_delete_commit");

		flash_message($lang->success_deleted, 'success');
		admin_redirect("index.php?module=forum-attachments");
	}
	else
	{
		$aids = array();
		foreach($mybb->input['aids'] as $aid)
		{
			$aids .= "&amp;aids[]=$aid";
		}
		$page->output_confirm_action("index.php?module=forum-attachments&amp;action=delete&amp;aids={$aids}", $lang->confirm_delete);
	}
}

if($mybb->input['action'] == "stats")
{
	$plugins->run_hooks("admin_forum_attachments_stats");

	$query = $db->simple_select("attachments", "COUNT(*) AS total_attachments, SUM(filesize) as disk_usage, SUM(downloads*filesize) as bandwidthused", "visible='1'");
	$attachment_stats = $db->fetch_array($query);

		$page->add_breadcrumb_item($lang->stats);
		$page->output_header($lang->stats_attachment_stats);

		$page->output_nav_tabs($sub_tabs, 'stats');

	if($attachment_stats['total_attachments'] == 0)
	{
		$page->output_inline_error(array($lang->error_no_attachments));
		$page->output_footer();
		exit;
	}

	$table = new Table;

	$table->construct_cell($lang->num_uploaded, array('width' => '25%'));
	$table->construct_cell(my_number_format($attachment_stats['total_attachments']), array('width' => '25%'));
	$table->construct_cell($lang->space_used, array('width' => '200'));
	$table->construct_cell(get_friendly_size($attachment_stats['disk_usage']), array('width' => '200'));
	$table->construct_row();

	$table->construct_cell($lang->bandwidth_used, array('width' => '25%'));
	$table->construct_cell(get_friendly_size(round($attachment_stats['bandwidthused'])), array('width' => '25%'));
	$table->construct_cell($lang->average_size, array('width' => '25%'));
	$table->construct_cell(get_friendly_size(round($attachment_stats['disk_usage']/$attachment_stats['total_attachments'])), array('width' => '25%'));
	$table->construct_row();

	$table->output($lang->general_stats);

	// Fetch the most popular attachments
	$table = new Table;
	$table->construct_header($lang->attachments, array('colspan' => 2));
	$table->construct_header($lang->size, array('width' => '10%', 'class' => 'align_center'));
	$table->construct_header($lang->posted_by, array('width' => '20%', 'class' => 'align_center'));
	$table->construct_header($lang->thread, array('width' => '25%', 'class' => 'align_center'));
	$table->construct_header($lang->downloads, array('width' => '10%', 'class' => 'align_center'));
	$table->construct_header($lang->date_uploaded, array("class" => "align_center"));

	$query = $db->query("
		SELECT a.*, p.tid, p.fid, t.subject, p.uid, p.username, u.username AS user_username
		FROM ".TABLE_PREFIX."attachments a
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
		ORDER BY a.downloads DESC
		LIMIT 5
	");
	while($attachment = $db->fetch_array($query))
	{
		build_attachment_row($attachment, $table);
	}
	$table->output($lang->popular_attachments);

	// Fetch the largest attachments
	$table = new Table;
	$table->construct_header($lang->attachments, array('colspan' => 2));
	$table->construct_header($lang->size, array('width' => '10%', 'class' => 'align_center'));
	$table->construct_header($lang->posted_by, array('width' => '20%', 'class' => 'align_center'));
	$table->construct_header($lang->thread, array('width' => '25%', 'class' => 'align_center'));
	$table->construct_header($lang->downloads, array('width' => '10%', 'class' => 'align_center'));
	$table->construct_header($lang->date_uploaded, array("class" => "align_center"));

	$query = $db->query("
		SELECT a.*, p.tid, p.fid, t.subject, p.uid, p.username, u.username AS user_username
		FROM ".TABLE_PREFIX."attachments a
		LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
		LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
		LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
		ORDER BY a.filesize DESC
		LIMIT 5
	");
	while($attachment = $db->fetch_array($query))
	{
		build_attachment_row($attachment, $table);
	}
	$table->output($lang->largest_attachments);

	// Fetch users who've uploaded the most attachments
	$table = new Table;
	$table->construct_header($lang->username);
	$table->construct_header($lang->total_size, array('width' => '20%', 'class' => 'align_center'));

	switch($db->type)
	{
		case "pgsql":
			$query = $db->query("
				SELECT a.uid, u.username, SUM(a.filesize) as totalsize
				FROM ".TABLE_PREFIX."attachments a
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
				GROUP BY a.uid, u.username
				ORDER BY totalsize DESC
				LIMIT 5
			");
			break;
		default:
			$query = $db->query("
				SELECT a.uid, u.username, SUM(a.filesize) as totalsize
				FROM ".TABLE_PREFIX."attachments a
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
				GROUP BY a.uid
				ORDER BY totalsize DESC
				LIMIT 5
			");
	}
	while($user = $db->fetch_array($query))
	{
		if(!$user['uid'])
		{
			$user['username'] = $lang->na;
		}
		$table->construct_cell(build_profile_link(htmlspecialchars_uni($user['username']), $user['uid'], "_blank"));
		$table->construct_cell("<a href=\"index.php?module=forum-attachments&amp;results=1&amp;username=".urlencode($user['username'])."\" target=\"_blank\">".get_friendly_size($user['totalsize'])."</a>", array('class' => 'align_center'));
		$table->construct_row();
	}
	$table->output($lang->users_diskspace);

	$page->output_footer();
}

if($mybb->input['action'] == "delete_orphans" && $mybb->request_method == "post")
{
	$plugins->run_hooks("admin_forum_attachments_delete_orphans");

	$success_count = $error_count = 0;

	// Deleting specific attachments from uploads directory
	if(is_array($mybb->input['orphaned_files']))
	{
		foreach($mybb->input['orphaned_files'] as $file)
		{
			$file = str_replace('..', '', $file);
			$path = MYBB_ROOT.$mybb->settings['uploadspath']."/".$file;
			$real_path = realpath($path);

			if($real_path === false || strpos(str_replace('\\', '/', $real_path), str_replace('\\', '/', realpath(MYBB_ROOT)).'/') !== 0 || $real_path == realpath(MYBB_ROOT.'install/lock'))
			{
				$error_count++;
				continue;
			}

			if(!@unlink(MYBB_ROOT.$mybb->settings['uploadspath']."/".$file))
			{
				$error_count++;
			}
			else
			{
				$success_count++;
			}
		}
	}

	// Deleting physical attachments which exist in database
	if(is_array($mybb->input['orphaned_attachments']))
	{
		$mybb->input['orphaned_attachments'] = array_map("intval", $mybb->input['orphaned_attachments']);
		require_once MYBB_ROOT."inc/functions_upload.php";

		$query = $db->simple_select("attachments", "aid,pid,posthash", "aid IN (".implode(",", $mybb->input['orphaned_attachments']).")");
		while($attachment = $db->fetch_array($query))
		{
			if(!$attachment['pid'])
			{
				remove_attachment(null, $attachment['posthash'], $attachment['aid']);
			}
			else
			{
				remove_attachment($attachment['pid'], null, $attachment['aid']);
			}
			$success_count++;
		}
	}

	$plugins->run_hooks("admin_forum_attachments_delete_orphans_commit");

	// Log admin action
	log_admin_action();

	$message = '';
	$status = 'success';
	if($error_count > 0)
	{
		$status = 'error';
		$message = $lang->sprintf($lang->error_count, $error_count);
	}

	if($success_count > 0)
	{
		if($error_count > 0)
		{
			$message .= '<br />'.$lang->sprintf($lang->success_count, $success_count);
		}
		else
		{
			$message = $lang->success_orphan_deleted;
		}
	}
	flash_message($message, $status);
	admin_redirect('index.php?module=forum-attachments');
}

if($mybb->input['action'] == "orphans")
{
	$plugins->run_hooks("admin_forum_attachments_orphans");

	// Oprhans are defined as:
	// - Uploaded files in the uploads directory that don't exist in the database
	// - Attachments for which the uploaded file is missing
	// - Attachments for which the thread or post has been deleted
	// - Files uploaded > 24h ago not attached to a real post

	// This process is quite intensive so we split it up in to 2 steps, one which scans the file system and the other which scans the database.

	// Finished second step, show results
	if($mybb->input['step'] == 3)
	{
		$plugins->run_hooks("admin_forum_attachments_step3");

		$reults = 0;
		// Incoming attachments which exist as files but not in database
		if($mybb->input['bad_attachments'])
		{
			$bad_attachments = my_unserialize($mybb->input['bad_attachments']);
			$results = count($bad_attachments);
		}

		$aids = array();
		if($mybb->input['missing_attachment_files'])
		{
			$missing_attachment_files = my_unserialize($mybb->input['missing_attachment_files']);
			$aids = array_merge($aids, $missing_attachment_files);
		}

		if($mybb->input['missing_threads'])
		{
			$missing_threads = my_unserialize($mybb->input['missing_threads']);
			$aids = array_merge($aids, $missing_threads);
		}

		if($mybb->input['incomplete_attachments'])
		{
			$incomplete_attachments = my_unserialize($mybb->input['incomplete_attachments']);
			$aids = array_merge($aids, $incomplete_attachments);
		}

		foreach($aids as $key => $aid)
		{
			$aids[$key] = (int)$aid;
		}

		$results += count($aids);

		if($results == 0)
		{
			flash_message($lang->success_no_orphans, 'success');
			admin_redirect("index.php?module=forum-attachments");
		}

		$page->output_header($lang->orphan_results);
		$page->output_nav_tabs($sub_tabs, 'find_orphans');

		$form = new Form("index.php?module=forum-attachments&amp;action=delete_orphans", "post");

		$table = new Table;
		$table->construct_header($form->generate_check_box('allbox', '1', '', array('class' => 'checkall')), array( 'width' => 1));
		$table->construct_header($lang->size_attachments, array('colspan' => 2));
		$table->construct_header($lang->reason_orphaned, array('width' => '20%', 'class' => 'align_center'));
		$table->construct_header($lang->date_uploaded, array("class" => "align_center"));

		if(is_array($bad_attachments))
		{
			foreach($bad_attachments as $file)
			{
				$file_path = MYBB_ROOT.$mybb->settings['uploadspath']."/".$file;
				$filesize = get_friendly_size(filesize($file_path));
				$table->construct_cell($form->generate_check_box('orphaned_files[]', $file, '', array('checked' => true)));
				$table->construct_cell(get_attachment_icon(get_extension($attachment['filename'])), array('width' => 1));
				$table->construct_cell("<span class=\"float_right\">{$filesize}</span>{$file}");
				$table->construct_cell($lang->reason_not_in_table, array('class' => 'align_center'));
				$table->construct_cell(my_date('relative', filemtime($file_path)), array('class' => 'align_center'));
				$table->construct_row();
			}
		}

		if(count($aids) > 0)
		{
			$query = $db->simple_select("attachments", "*", "aid IN (".implode(",", $aids).")");
			while($attachment = $db->fetch_array($query))
			{
				$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);

				if($missing_attachment_files[$attachment['aid']])
				{
					$reason = $lang->reason_file_missing;
				}
				else if($missing_threads[$attachment['aid']])
				{
					$reason = $lang->reason_thread_deleted;
				}
				else if($incomplete_attachments[$attachment['aid']])
				{
					$reason = $lang->reason_post_never_made;
				}
				$table->construct_cell($form->generate_check_box('orphaned_attachments[]', $attachment['aid'], '', array('checked' => true)));
				$table->construct_cell(get_attachment_icon(get_extension($attachment['filename'])), array('width' => 1));
				$table->construct_cell("<span class=\"float_right\">".get_friendly_size($attachment['filesize'])."</span>{$attachment['filename']}", array('class' => $cell_class));
				$table->construct_cell($reason, array('class' => 'align_center'));
				if($attachment['dateuploaded'])
				{
					$table->construct_cell(my_date('relative', $attachment['dateuploaded']), array('class' => 'align_center'));
				}
				else
				{
					$table->construct_cell($lang->unknown, array('class' => 'align_center'));
				}
				$table->construct_row();
			}
		}

		$table->output("{$lang->orphan_attachments_search} - {$results} {$lang->results}");

		$buttons[] = $form->generate_submit_button($lang->button_delete_orphans);
		$form->output_submit_wrapper($buttons);
		$form->end();
		$page->output_footer();
	}

	// Running second step - scan the database
	else if($mybb->input['step'] == 2)
	{
		$plugins->run_hooks("admin_forum_attachments_orphans_step2");

		$page->output_header("{$lang->orphan_attachments_search} - {$lang->step2}");

		$page->output_nav_tabs($sub_tabs, 'find_orphans');
		echo "<h3>{$lang->step2of2}</h3>";
		echo "<p class=\"align_center\">{$lang->step2of2_line1}</p>";
		echo "<p class=\"align_center\">{$lang->step_line2}</p>";
		echo "<p class=\"align_center\"><img src=\"styles/{$page->style}/images/spinner_big.gif\" alt=\"{$lang->scanning}\" id=\"spinner\" /></p>";

		$page->output_footer(false);
		flush();

		$missing_attachment_files = array();
		$missing_threads = array();
		$incomplete_attachments = array();

		$query = $db->query("
			SELECT a.*, a.pid AS attachment_pid, p.pid
			FROM ".TABLE_PREFIX."attachments a
			LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
			ORDER BY a.aid");
		while($attachment = $db->fetch_array($query))
		{
			// Check if the attachment exists in the file system
			if(!file_exists(MYBB_ROOT.$mybb->settings['uploadspath']."/{$attachment['attachname']}"))
			{
				$missing_attachment_files[$attachment['aid']] = $attachment['aid'];
			}
			// Check if the thread/post for this attachment is missing
			else if(!$attachment['pid'] && $attachment['attachment_pid'])
			{
				$missing_threads[$attachment['aid']] = $attachment['aid'];
			}
			// Check if the attachment was uploaded > 24 hours ago but not assigned to a thread
			else if(!$attachment['attachment_pid'] && $attachment['dateuploaded'] < TIME_NOW-60*60*24 && $attachment['dateuploaded'] != 0)
			{
				$incomplete_attachments[$attachment['aid']] = $attachment['aid'];
			}
		}

		// Now send the user to the final page
		$form = new Form("index.php?module=forum-attachments&amp;action=orphans&amp;step=3", "post", "redirect_form", 0, "");
		// Scan complete
		if($mybb->input['bad_attachments'])
		{
			echo $form->generate_hidden_field("bad_attachments", $mybb->input['bad_attachments']);
		}
		if(is_array($missing_attachment_files) && count($missing_attachment_files) > 0)
		{
			$missing_attachment_files = my_serialize($missing_attachment_files);
			echo $form->generate_hidden_field("missing_attachment_files", $missing_attachment_files);
		}
		if(is_array($missing_threads) && count($missing_threads) > 0)
		{
			$missing_threads = my_serialize($missing_threads);
			echo $form->generate_hidden_field("missing_threads", $missing_threads);
		}
		if(is_array($incomplete_attachments) && count($incomplete_attachments) > 0)
		{
			$incomplete_attachments = my_serialize($incomplete_attachments);
			echo $form->generate_hidden_field("incomplete_attachments", $incomplete_attachments);
		}
		$form->end();
		echo "<script type=\"text/javascript\">$(function() {
				window.setTimeout(
					function() {
						$(\"#redirect_form\").submit();
					}, 100
				);
			});</script>";
		exit;
	}
	// Running first step, scan the file system
	else
	{
		$plugins->run_hooks("admin_forum_attachments_orphans_step1");

		/**
		 * @param string $dir
		 */
		function scan_attachments_directory($dir="")
		{
			global $db, $mybb, $bad_attachments, $attachments_to_check;

			$real_dir = MYBB_ROOT.$mybb->settings['uploadspath'];
			$false_dir = "";
			if($dir)
			{
				$real_dir .= "/".$dir;
				$false_dir = $dir."/";
			}

			if($dh = opendir($real_dir))
			{
				while(false !== ($file = readdir($dh)))
				{
					if($file == "." || $file == ".." || $file == ".svn")
					{
						continue;
					}

					if(is_dir($real_dir.'/'.$file))
					{
						scan_attachments_directory($false_dir.$file);
					}
					else if(my_substr($file, -7, 7) == ".attach")
					{
						$attachments_to_check["$false_dir$file"] = $false_dir.$file;
						// In allotments of 20, query the database for these attachments
						if(count($attachments_to_check) >= 20)
						{
							$attachments_to_check = array_map(array($db, "escape_string"), $attachments_to_check);
							$attachment_names = "'".implode("','", $attachments_to_check)."'";
							$query = $db->simple_select("attachments", "aid, attachname", "attachname IN ($attachment_names)");
							while($attachment = $db->fetch_array($query))
							{
								unset($attachments_to_check[$attachment['attachname']]);
							}

							// Now anything left is bad!
							if(count($attachments_to_check) > 0)
							{
								if($bad_attachments)
								{
									$bad_attachments = @array_merge($bad_attachments, $attachments_to_check);
								}
								else
								{
									$bad_attachments = $attachments_to_check;
								}
							}
							$attachments_to_check = array();
						}
					}
				}
				closedir($dh);
				// Any reamining to check?
				if(count($attachments_to_check) > 0)
				{
					$attachments_to_check = array_map(array($db, "escape_string"), $attachments_to_check);
					$attachment_names = "'".implode("','", $attachments_to_check)."'";
					$query = $db->simple_select("attachments", "aid, attachname", "attachname IN ($attachment_names)");
					while($attachment = $db->fetch_array($query))
					{
						unset($attachments_to_check[$attachment['attachname']]);
					}

					// Now anything left is bad!
					if(count($attachments_to_check) > 0)
					{
						if($bad_attachments)
						{
							$bad_attachments = @array_merge($bad_attachments, $attachments_to_check);
						}
						else
						{
							$bad_attachments = $attachments_to_check;
						}
					}
				}
			}
		}

		$page->output_header("{$lang->orphan_attachments_search} - {$lang->step1}");

		$page->output_nav_tabs($sub_tabs, 'find_orphans');
		echo "<h3>{$lang->step1of2}</h3>";
		echo "<p class=\"align_center\">{$lang->step1of2_line1}</p>";
		echo "<p class=\"align_center\">{$lang->step_line2}</p>";
		echo "<p class=\"align_center\"><img src=\"styles/{$page->style}/images/spinner_big.gif\" alt=\"{$lang->scanning}\" id=\"spinner\" /></p>";

		$page->output_footer(false);

		flush();

		scan_attachments_directory();
		global $bad_attachments;

		$form = new Form("index.php?module=forum-attachments&amp;action=orphans&amp;step=2", "post", "redirect_form", 0, "");
		// Scan complete
		if(is_array($bad_attachments) && count($bad_attachments) > 0)
		{
			$bad_attachments = my_serialize($bad_attachments);
			echo $form->generate_hidden_field("bad_attachments", $bad_attachments);
		}
		$form->end();
		echo "<script type=\"text/javascript\">$(function() {
				window.setTimeout(
					function() {
						$(\"#redirect_form\").submit();
					}, 100
				);
			});</script>";
		exit;
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_forum_attachments_start");

	if($mybb->request_method == "post" || $mybb->input['results'] == 1)
	{
		$search_sql = '1=1';

		// Build the search SQL for users

		// List of valid LIKE search fields
		$user_like_fields = array("filename", "filetype");
		foreach($user_like_fields as $search_field)
		{
			if($mybb->input[$search_field])
			{
				$search_sql .= " AND a.{$search_field} LIKE '%".$db->escape_string_like($mybb->input[$search_field])."%'";
			}
		}

		$errors = array();

		// Normal users only
		if($mybb->get_input('user_types', MyBB::INPUT_INT) == 1)
		{
			$user_types = 1;
		}
		// Guests only
		elseif($mybb->get_input('user_types', MyBB::INPUT_INT) == -1)
		{
			$user_types = -1;
			$search_sql .= " AND a.uid='0'";
		}
		// Users & Guests
		else
		{
			$user_types = 0;
		}

		// Username matching
		if($mybb->input['username'])
		{
			$user = get_user_by_username($mybb->input['username']);

			if(!$user['uid'])
			{
				if($user_types == 1)
				{
					$errors[] = $lang->error_invalid_username;
				}
				else
				{
					// Don't error if we are searching for guests or users & guests
					$search_sql .= " AND p.username LIKE '%".$db->escape_string_like($mybb->input['username'])."%'";
				}

			}
			else
			{
				$search_sql .= " AND a.uid='{$user['uid']}'";
			}
		}

		$forum_cache = cache_forums();

		// Searching for attachments in a specific forum, we need to fetch all child forums too
		if($mybb->input['forum'])
		{
			if(!is_array($mybb->input['forum']))
			{
				$mybb->input['forum'] = array($mybb->input['forum']);
			}

			$fid_in = array();
			foreach($mybb->input['forum'] as $fid)
			{
				if(!$forum_cache[$fid])
				{
					$errors[] = $lang->error_invalid_forums;
					break;
				}
				$child_forums = get_child_list($fid);
				$child_forums[] = $fid;
				$fid_in = array_merge($fid_in, $child_forums);
			}

			if(count($fid_in) > 0)
			{
				$search_sql .= " AND p.fid IN (".implode(",", $fid_in).")";
			}
		}

		// LESS THAN or GREATER THAN
		$direction_fields = array(
			"dateuploaded" => $mybb->get_input('dateuploaded', MyBB::INPUT_INT),
			"filesize"     => $mybb->get_input('filesize', MyBB::INPUT_INT),
			"downloads"    => $mybb->get_input('downloads', MyBB::INPUT_INT)
		);

		if($mybb->input['dateuploaded'] && $mybb->request_method == "post")
		{
			$direction_fields['dateuploaded'] = TIME_NOW-$direction_fields['dateuploaded']*60*60*24;
		}
		if($mybb->input['filesize'] && $mybb->request_method == "post")
		{
			$direction_fields['filesize'] *= 1024;
		}

		foreach($direction_fields as $field_name => $field_content)
		{
			$direction_field = $field_name."_dir";
			if($mybb->input[$field_name] && $mybb->input[$direction_field])
			{
				switch($mybb->input[$direction_field])
				{
					case "greater_than":
						$direction = ">";
						break;
					case "less_than":
						$direction = "<";
						break;
					default:
						$direction = "=";
				}
				$search_sql .= " AND a.{$field_name}{$direction}'".$field_content."'";
			}
		}
		if(!$errors)
		{
			// Lets fetch out how many results we have
			$query = $db->query("
				SELECT COUNT(a.aid) AS num_results
				FROM ".TABLE_PREFIX."attachments a
				LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
				WHERE {$search_sql}
			");
			$num_results = $db->fetch_field($query, "num_results");

			// No matching results then show an error
			if(!$num_results)
			{
				$errors[] = $lang->error_no_results;
			}
		}

		// Now we fetch the results if there were 100% no errors
		if(!$errors)
		{
			$mybb->input['perpage'] = $mybb->get_input('perpage', MyBB::INPUT_INT);
			if(!$mybb->input['perpage'])
			{
				$mybb->input['perpage'] = 20;
			}

			$mybb->input['page'] = $mybb->get_input('page', MyBB::INPUT_INT);
			if($mybb->input['page'])
			{
				$start = ($mybb->input['page'] - 1) * $mybb->input['perpage'];
			}
			else
			{
				$start = 0;
				$mybb->input['page'] = 1;
			}

			switch($mybb->input['sortby'])
			{
				case "filesize":
					$sort_field = "a.filesize";
					break;
				case "downloads":
					$sort_field = "a.downloads";
					break;
				case "dateuploaded":
					$sort_field = "a.dateuploaded";
					break;
				case "username":
					$sort_field = "u.username";
					break;
				default:
					$sort_field = "a.filename";
					$mybb->input['sortby'] = "filename";
			}

			if($mybb->input['order'] != "desc")
			{
				$mybb->input['order'] = "asc";
			}

			$page->add_breadcrumb_item($lang->results);
			$page->output_header($lang->index_find_attachments);

			$page->output_nav_tabs($sub_tabs, 'find_attachments');

			$form = new Form("index.php?module=forum-attachments&amp;action=delete", "post");

			$table = new Table;
			$table->construct_header($form->generate_check_box('allbox', '1', '', array('class' => 'checkall')), array( 'width' => 1));
			$table->construct_header($lang->attachments, array('colspan' => 2));
			$table->construct_header($lang->size, array('width' => '10%', 'class' => 'align_center'));
			$table->construct_header($lang->posted_by, array('width' => '20%', 'class' => 'align_center'));
			$table->construct_header($lang->thread, array('width' => '25%', 'class' => 'align_center'));
			$table->construct_header($lang->downloads, array('width' => '10%', 'class' => 'align_center'));
			$table->construct_header($lang->date_uploaded, array("class" => "align_center"));

			// Fetch matching attachments
			$query = $db->query("
				SELECT a.*, p.tid, p.fid, t.subject, p.uid, p.username, u.username AS user_username
				FROM ".TABLE_PREFIX."attachments a
				LEFT JOIN ".TABLE_PREFIX."posts p ON (p.pid=a.pid)
				LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=a.uid)
				WHERE {$search_sql}
				ORDER BY {$sort_field} {$mybb->input['order']}
				LIMIT {$start}, {$mybb->input['perpage']}
			");
			while($attachment = $db->fetch_array($query))
			{
				build_attachment_row($attachment, $table, true);
			}

			// Need to draw pagination for this result set
			if($num_results > $mybb->input['perpage'])
			{
				$pagination_url = "index.php?module=forum-attachments&amp;results=1";
				$pagination_vars = array('perpage', 'sortby', 'order', 'filename', 'mimetype', 'username', 'fid', 'downloads', 'downloads_dir', 'dateuploaded', 'dateuploaded_dir', 'filesize', 'filesize_dir');
				foreach($pagination_vars as $var)
				{
					if($mybb->input[$var])
					{
						$pagination_url .= "&{$var}=".urlencode($mybb->input[$var]);
					}
				}
				$pagination = draw_admin_pagination($mybb->input['page'], $mybb->input['perpage'], $num_results, $pagination_url);
			}

			echo $pagination;
			$table->output($lang->results);
			echo $pagination;

			$buttons[] = $form->generate_submit_button($lang->button_delete_attachments);

			$form->output_submit_wrapper($buttons);
			$form->end();

			$page->output_footer();
		}
	}

	$page->output_header($lang->find_attachments);

	$page->output_nav_tabs($sub_tabs, 'find_attachments');

	// If we have any error messages, show them
	if($errors)
	{
		$page->output_inline_error($errors);
	}

	$form = new Form("index.php?module=forum-attachments", "post");

	$form_container = new FormContainer($lang->find_where);
	$form_container->output_row($lang->name_contains, $lang->name_contains_desc, $form->generate_text_box('filename', $mybb->input['filename'], array('id' => 'filename')), 'filename');
	$form_container->output_row($lang->type_contains, "", $form->generate_text_box('mimetype', $mybb->input['mimetype'], array('id' => 'mimetype')), 'mimetype');
	$form_container->output_row($lang->forum_is, "", $form->generate_forum_select('forum[]', $mybb->input['forum'], array('multiple' => true, 'size' => 5, 'id' => 'forum')), 'forum');
	$form_container->output_row($lang->username_is, "", $form->generate_text_box('username', htmlspecialchars_uni($mybb->get_input('username')), array('id' => 'username')), 'username');
	$form_container->output_row($lang->poster_is, "", $form->generate_select_box('user_types', array('0' => $lang->poster_is_either, '1' => $lang->poster_is_user, '-1' => $lang->poster_is_guest), $mybb->get_input('user_types', MyBB::INPUT_INT), array('id' => 'guests')), 'user_types');

	$more_options = array(
		"less_than" => $lang->more_than,
		"greater_than" => $lang->less_than
	);

	$greater_options = array(
		"greater_than" => $lang->greater_than,
		"is_exactly" => $lang->is_exactly,
		"less_than" => $lang->less_than
	);

	$form_container->output_row($lang->date_posted_is, "", $form->generate_select_box('dateuploaded_dir', $more_options, $mybb->input['dateuploaded_dir'], array('id' => 'dateuploaded_dir'))." ".$form->generate_numeric_field('dateuploaded', $mybb->input['dateuploaded'], array('id' => 'dateuploaded', 'min' => 0))." {$lang->days_ago}", 'dateuploaded');
	$form_container->output_row($lang->file_size_is, "", $form->generate_select_box('filesize_dir', $greater_options, $mybb->input['filesize_dir'], array('id' => 'filesize_dir'))." ".$form->generate_numeric_field('filesize', $mybb->input['filesize'], array('id' => 'filesize', 'min' => 0))." {$lang->kb}", 'dateuploaded');
	$form_container->output_row($lang->download_count_is, "", $form->generate_select_box('downloads_dir', $greater_options, $mybb->input['downloads_dir'], array('id' => 'downloads_dir'))." ".$form->generate_numeric_field('downloads', $mybb->input['downloads'], array('id' => 'downloads', 'min' => 0))."", 'dateuploaded');
	$form_container->end();

	$form_container = new FormContainer($lang->display_options);
	$sort_options = array(
		"filename" => $lang->filename,
		"filesize" => $lang->filesize,
		"downloads" => $lang->download_count,
		"dateuploaded" => $lang->date_uploaded,
		"username" => $lang->post_username
	);
	$sort_directions = array(
		"asc" => $lang->asc,
		"desc" => $lang->desc
	);
	$form_container->output_row($lang->sort_results_by, "", $form->generate_select_box('sortby', $sort_options, $mybb->input['sortby'], array('id' => 'sortby'))." {$lang->in} ".$form->generate_select_box('order', $sort_directions, $mybb->input['order'], array('id' => 'order')), 'sortby');
	$form_container->output_row($lang->results_per_page, "", $form->generate_numeric_field('perpage', $mybb->input['perpage'], array('id' => 'perpage', 'min' => 1)), 'perpage');
	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->button_find_attachments);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

/**
 * @param array $attachment
 * @param DefaultTable $table
 * @param bool $use_form
 */
function build_attachment_row($attachment, &$table, $use_form=false)
{
	global $mybb, $form, $lang;
	$attachment['filename'] = htmlspecialchars_uni($attachment['filename']);

	// Here we do a bit of detection, we want to automatically check for removal any missing attachments and any not assigned to a post uploaded > 24hours ago
	// Check if the attachment exists in the file system
	$checked = false;
	$title = $cell_class = '';
	if(!file_exists(MYBB_ROOT.$mybb->settings['uploadspath']."/{$attachment['attachname']}"))
	{
		$cell_class = "bad_attachment";
		$title = $lang->error_not_found;
		$checked = true;
	}
	elseif(!$attachment['pid'] && $attachment['dateuploaded'] < TIME_NOW-60*60*24 && $attachment['dateuploaded'] != 0)
	{
		$cell_class = "bad_attachment";
		$title = $lang->error_not_attached;
		$checked = true;
	}
	else if(!$attachment['tid'] && $attachment['pid'])
	{
		$cell_class = "bad_attachment";
		$title = $lang->error_does_not_exist;
		$checked = true;
	}
	else if($attachment['visible'] == 0)
	{
		$cell_class = "invisible_attachment";
	}

	if($cell_class)
	{
		$cell_class .= " align_center";
	}
	else
	{
		$cell_class = "align_center";
	}

	if($use_form == true && is_object($form))
	{
		$table->construct_cell($form->generate_check_box('aids[]', $attachment['aid'], '', array('checked' => $checked)));
	}
	$table->construct_cell(get_attachment_icon(get_extension($attachment['filename'])), array('width' => 1));
	$table->construct_cell("<a href=\"../attachment.php?aid={$attachment['aid']}\" target=\"_blank\">{$attachment['filename']}</a>");
	$table->construct_cell(get_friendly_size($attachment['filesize']), array('class' => $cell_class));

	if($attachment['user_username'])
	{
		$attachment['username'] = $attachment['user_username'];
	}
	$table->construct_cell(build_profile_link(htmlspecialchars_uni($attachment['username']), $attachment['uid'], "_blank"), array("class" => "align_center"));
	$table->construct_cell("<a href=\"../".get_post_link($attachment['pid'])."\" target=\"_blank\">".htmlspecialchars_uni($attachment['subject'])."</a>", array("class" => "align_center"));
	$table->construct_cell(my_number_format($attachment['downloads']), array("class" => "align_center"));
	if($attachment['dateuploaded'] > 0)
	{
		$date = my_date('relative', $attachment['dateuploaded']);
	}
	else
	{
		$date = $lang->unknown;
	}
	$table->construct_cell($date, array("class" => "align_center"));
	$table->construct_row();
}
