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
define("IN_ARCHIVE", 1);

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";
// Load global language phrases
$lang->load("index");

$plugins->run_hooks("archive_start");

switch($action)
{
	// Display an announcement.
	case "announcement":
		// Fetch the forum this thread is in
		if($announcement['fid'] != -1)
		{
			$forum = get_forum($announcement['fid']);
			if(!$forum['fid'] || $forum['password'] != '')
			{
				archive_error($lang->error_invalidforum);
			}

			// Check if we have permission to view this thread
			$forumpermissions = forum_permissions($forum['fid']);
			if($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1)
			{
				archive_error_no_permission();
			}

			check_forum_password_archive($forum['fid']);
		}

		$announcement['subject'] = htmlspecialchars_uni($parser->parse_badwords($announcement['subject']));

		$parser_options = array(
			"allow_html" => $announcement['allowhtml'],
			"allow_mycode" => $announcement['allowmycode'],
			"allow_smilies" => $announcement['allowsmilies'],
			"allow_imgcode" => 1,
			"allow_videocode" => 1,
			"me_username" => $announcement['username'],
			"filter_badwords" => 1
		);

		$announcement['message'] = $parser->parse_message($announcement['message'], $parser_options);

		$profile_link = build_profile_link($announcement['username'], $announcement['uid']);

		// Build the navigation
		add_breadcrumb($announcement['subject']);
		archive_header($announcement['subject'], $announcement['subject'], $mybb->settings['bburl']."/announcements.php?aid={$id}");

		// Format announcement contents.
		$announcement['startdate'] = my_date('relative', $announcement['startdate']);

		$plugins->run_hooks("archive_announcement_start");

		echo "<div class=\"post\">\n<div class=\"header\">\n<h2>{$announcement['subject']} - {$profile_link}</h2>";
		echo "<div class=\"dateline\">{$announcement['startdate']}</div>\n</div>\n<div class=\"message\">{$announcement['message']}</div>\n</div>\n";

		$plugins->run_hooks("archive_announcement_end");

		archive_footer();
		break;

	// Display a thread.
	case "thread":
		$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));

		// Fetch the forum this thread is in
		$forum = get_forum($thread['fid']);
		if(!$forum['fid'] || $forum['password'] != '')
		{
			archive_error($lang->error_invalidforum);
		}

		// Check if we have permission to view this thread
		$forumpermissions = forum_permissions($forum['fid']);
		if($forumpermissions['canview'] != 1 || $forumpermissions['canviewthreads'] != 1)
		{
			archive_error_no_permission();
		}

		if($thread['visible'] != 1)
		{
			if(is_moderator($forum['fid'], "canviewunapprove"))
			{
				archive_error($lang->sprintf($lang->error_unapproved_thread, $mybb->settings['bburl']."/".get_thread_link($thread['tid'], $page)));
			}
			else
			{
				archive_error($lang->error_invalidthread);
			}
		}

		if(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] == 1 && $thread['uid'] != $mybb->user['uid'])
		{
			archive_error_no_permission();
		}

		check_forum_password_archive($forum['fid']);

		// Build the navigation
		build_forum_breadcrumb($forum['fid'], 1);
		add_breadcrumb($thread['subject']);

		archive_header($thread['subject'], $thread['subject'], $mybb->settings['bburl']."/".get_thread_link($thread['tid'], $page));

		$plugins->run_hooks("archive_thread_start");

		// Paginate this thread
		if(!$mybb->settings['postsperpage'] || (int)$mybb->settings['postsperpage'] < 1)
		{
			$mybb->settings['postsperpage'] = 20;
		}
		$perpage = $mybb->settings['postsperpage'];
		$postcount = (int)$thread['replies']+1;
		$pages = ceil($postcount/$perpage);

		if($page > $pages)
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

		$pids = array();
		// Fetch list of post IDs to be shown
		$query = $db->simple_select("posts", "pid", "tid='{$id}' AND visible='1'", array('order_by' => 'dateline', 'limit_start' => $start, 'limit' => $perpage));
		while($post = $db->fetch_array($query))
		{
			$pids[$post['pid']] = $post['pid'];
		}

		if(empty($pids))
		{
			archive_error($lang->error_invalidthread);
		}

		archive_multipage($postcount, $perpage, $page, "{$base_url}thread-$id");

		$pids = implode(",", $pids);

		if($pids && $mybb->settings['enableattachments'] == 1)
		{
			// Build attachments cache
			$query = $db->simple_select("attachments", "*", "pid IN ({$pids})");
			while($attachment = $db->fetch_array($query))
			{
				$acache[$attachment['pid']][$attachment['aid']] = $attachment;
			}
		}

		// Start fetching the posts
		$query = $db->query("
			SELECT u.*, u.username AS userusername, p.*
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid)
			WHERE p.pid IN ({$pids})
			ORDER BY p.dateline
		");
		while($post = $db->fetch_array($query))
		{
			$post['date'] = my_date('relative', $post['dateline']);
			if($post['userusername'])
			{
				$post['username'] = $post['userusername'];
			}

			// Parse the message
			$parser_options = array(
				"allow_html" => $forum['allowhtml'],
				"allow_mycode" => $forum['allowmycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode'],
				"allow_videocode" => $forum['allowvideocode'],
				"me_username" => $post['username'],
				"filter_badwords" => 1
			);
			if($post['smilieoff'] == 1)
			{
				$parser_options['allow_smilies'] = 0;
			}

			$post['message'] = $parser->parse_message($post['message'], $parser_options);

			// Is there an attachment in this post?
			if($mybb->settings['enableattachments'] == 1 && isset($acache[$post['pid']]) && is_array($acache[$post['pid']]))
			{
				foreach($acache[$post['pid']] as $aid => $attachment)
				{
					$post['message'] = str_replace("[attachment={$attachment['aid']}]", "[<a href=\"".$mybb->settings['bburl']."/attachment.php?aid={$attachment['aid']}\">attachment={$attachment['aid']}</a>]", $post['message']);
				}
			}

			// Damn thats a lot of parsing, now to determine which username to show..
			if($post['userusername'])
			{
				$post['username'] = $post['userusername'];
			}
			$post['username'] = build_profile_link($post['username'], $post['uid']);

			$plugins->run_hooks("archive_thread_post");

			// Finally show the post
			echo "<div class=\"post\">\n<div class=\"header\">\n<div class=\"author\"><h2>{$post['username']}</h2></div>";
			echo "<div class=\"dateline\">{$post['date']}</div>\n</div>\n<div class=\"message\">{$post['message']}</div>\n</div>\n";
		}
		archive_multipage($postcount, $perpage, $page, "{$base_url}thread-$id");

		$plugins->run_hooks("archive_thread_end");

		archive_footer();
		break;

	// Display a category or a forum.
	case "forum":
		// Check if we have permission to view this forum
		$forumpermissions = forum_permissions($forum['fid']);
		if($forumpermissions['canview'] != 1)
		{
			archive_error_no_permission();
		}

		check_forum_password_archive($forum['fid']);

		$useronly = "";
		if(isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] == 1)
		{
			$useronly = "AND uid={$mybb->user['uid']}";
		}

		// Paginate this forum
		$query = $db->simple_select("threads", "COUNT(tid) AS threads", "fid='{$id}' AND visible='1' {$useronly}");
		$threadcount = $db->fetch_field($query, "threads");

		// Build the navigation
		build_forum_breadcrumb($forum['fid'], 1);

		// No threads and not a category? Error!
		if(($threadcount < 1 || $forumpermissions['canviewthreads'] != 1) && $forum['type'] != 'c')
		{
			archive_header(strip_tags($forum['name']), $forum['name'], $mybb->settings['bburl']."/".get_forum_link($id, $page)."");
			archive_error($lang->error_nothreads);
		}

		// Build the archive header.
		archive_header(strip_tags($forum['name']), $forum['name'], $mybb->settings['bburl']."/".get_forum_link($id, $page), 1);

		$plugins->run_hooks("archive_forum_start");

		if(!$mybb->settings['threadsperpage'] || (int)$mybb->settings['threadsperpage'] < 1)
		{
			$mybb->settings['threadsperpage'] = 20;
		}

		$perpage = $mybb->settings['threadsperpage'];
		$pages = ceil($threadcount/$perpage);
		if($page > $pages)
		{
			$page = 1;
		}

		if($page > 0)
		{
			$start = ($page-1) * $perpage;
		}
		else
		{
			$start = 0;
			$page = 1;
		}

		// Decide what type of listing to show.
		if($forum['type'] == 'f')
		{
			echo "<div class=\"listing\">\n<div class=\"header\"><h2>{$forum['name']}</h2></div>\n";
		}
		elseif($forum['type'] == 'c')
		{
			echo "<div class=\"listing\">\n<div class=\"header\"><h2>{$forum['name']}</h2></div>\n";
		}

		// Show subforums.
		$query = $db->simple_select("forums", "COUNT(fid) AS subforums", "pid='{$id}'");
		$subforumcount = $db->fetch_field($query, "subforums");
		if($subforumcount > 0)
		{
			echo "<div class=\"forumlist\">\n";
			echo "<h3>{$lang->subforums}</h3>\n";
			echo "<ol>\n";
			$forums = build_archive_forumbits($forum['fid']);
			echo $forums;
			echo "</ol>\n</div>\n";
		}

		archive_multipage($threadcount, $perpage, $page, "{$base_url}forum-$id");

		// Get the announcements if the forum is not a category.
		if($forum['type'] == 'f')
		{
			$sql = build_parent_list($forum['fid'], "fid", "OR", $forum['parentlist']);
			$time = TIME_NOW;
			$query = $db->simple_select("announcements", "*", "startdate < '{$time}' AND (enddate > '{$time}' OR enddate=0) AND ({$sql} OR fid='-1')");
			if($db->num_rows($query) > 0)
			{
				echo "<div class=\"announcementlist\">\n";
				echo "<h3>{$lang->forumbit_announcements}</h3>";
				echo "<ol>\n";
				while($announcement = $db->fetch_array($query))
				{
					$announcement['subject'] = $parser->parse_badwords($announcement['subject']);
					echo "<li><a href=\"{$base_url}announcement-{$announcement['aid']}.html\">".htmlspecialchars_uni($announcement['subject'])."</a></li>";
				}
				echo "</ol>\n</div>\n";
			}

		}

		// Get the stickies if the forum is not a category.
		if($forum['type'] == 'f')
		{
			$options = array(
				'order_by' => 'sticky, lastpost',
				'order_dir' => 'desc',
				'limit_start' => $start,
				'limit' => $perpage
			);
			$query = $db->simple_select("threads", "*", "fid='{$id}' AND visible='1' AND sticky='1' AND closed NOT LIKE 'moved|%' {$useronly}", $options);
			if($db->num_rows($query) > 0)
			{
				echo "<div class=\"threadlist\">\n";
				echo "<h3>{$lang->forumbit_stickies}</h3>";
				echo "<ol>\n";
				while($sticky = $db->fetch_array($query))
				{
					$sticky['subject'] = htmlspecialchars_uni($parser->parse_badwords($sticky['subject']));
					if($sticky['replies'] != 1)
					{
						$lang_reply_text = $lang->archive_replies;
					}
					else
					{
						$lang_reply_text = $lang->archive_reply;
					}

					$plugins->run_hooks("archive_forum_thread");

					$sticky['replies'] = my_number_format($sticky['replies']);

					echo "<li><a href=\"{$base_url}thread-{$sticky['tid']}.html\">{$sticky['subject']}</a>";
					echo "<span class=\"replycount\"> ({$sticky['replies']} {$lang_reply_text})</span></li>";
				}
				echo "</ol>\n</div>\n";
			}
		}

		// Get the threads if the forum is not a category.
		if($forum['type'] == 'f')
		{
			$options = array(
				'order_by' => 'sticky, lastpost',
				'order_dir' => 'desc',
				'limit_start' => $start,
				'limit' => $perpage
			);
			$query = $db->simple_select("threads", "*", "fid='{$id}' AND visible='1' AND sticky='0' AND closed NOT LIKE 'moved|%' {$useronly}", $options);
			if($db->num_rows($query) > 0)
			{
				echo "<div class=\"threadlist\">\n";
				echo "<h3>{$lang->forumbit_threads}</h3>";
				echo "<ol>\n";
				while($thread = $db->fetch_array($query))
				{
					$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
					if($thread['replies'] != 1)
					{
						$lang_reply_text = $lang->archive_replies;
					}
					else
					{
						$lang_reply_text = $lang->archive_reply;
					}

					$plugins->run_hooks("archive_forum_thread");

					$thread['replies'] = my_number_format($thread['replies']);

					echo "<li><a href=\"{$base_url}thread-{$thread['tid']}.html\">{$thread['subject']}</a>";
					echo "<span class=\"replycount\"> ({$thread['replies']} {$lang_reply_text})</span></li>";
				}
				echo "</ol>\n</div>\n";
			}
		}

		echo "</div>\n";

		archive_multipage($threadcount, $perpage, $page, "{$base_url}forum-$id");

		$plugins->run_hooks("archive_forum_end");

		archive_footer();
		break;

	// Display the board home.
	case "index":
		// Build our forum listing
		$forums = build_archive_forumbits(0);
		archive_header("", $mybb->settings['bbname_orig'], $mybb->settings['bburl']."/index.php");

		$plugins->run_hooks("archive_index_start");

		echo "<div class=\"listing forumlist\">\n<div class=\"header\">{$mybb->settings['bbname']}</div>\n<div class=\"forums\">\n<ul>\n";
		echo $forums;
		echo "\n</ul>\n</div>\n</div>";

		$plugins->run_hooks("archive_index_end");

		archive_footer();
		break;
	default:
		header("HTTP/1.0 404 Not Found");
		switch($action2)
		{
			case "announcement":
				archive_error($lang->error_invalidannouncement);
			case "thread":
				archive_error($lang->error_invalidthread);
			case "forum":
				archive_error($lang->error_invalidforum);
			default:
				archive_error($lang->archive_not_found);
		}
}

$plugins->run_hooks("archive_end");

/**
* Gets a list of forums and possibly subforums.
*
* @param int The parent forum to get the childforums for.
* @return array Array of information regarding the child forums of this parent forum
*/
function build_archive_forumbits($pid=0)
{
	global $db, $forumpermissions, $mybb, $base_url;

	// Sort out the forum cache first.
	static $fcache;
	if(!is_array($fcache))
	{
		// Fetch forums
		$query = $db->simple_select("forums", "*", "active!=0 AND password=''", array('order_by' =>'pid, disporder'));
		while($forum = $db->fetch_array($query))
		{
			$fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}
		$forumpermissions = forum_permissions();
	}

	$forums = '';

	// Start the process.
	if(is_array($fcache[$pid]))
	{
		foreach($fcache[$pid] as $key => $main)
		{
			foreach($main as $key => $forum)
			{
				$perms = $forumpermissions[$forum['fid']];
				if(($perms['canview'] == 1 || $mybb->settings['hideprivateforums'] == 0) && $forum['active'] != 0)
				{
					if($forum['linkto'])
					{
						$forums .= "<li><a href=\"{$forum['linkto']}\">{$forum['name']}</a>";
					}
					elseif($forum['type'] == "c")
					{
						$forums .= "<li><strong><a href=\"{$base_url}forum-{$forum['fid']}.html\">{$forum['name']}</a></strong>";
					}
					else
					{
						$forums .= "<li><a href=\"{$base_url}forum-{$forum['fid']}.html\">{$forum['name']}</a>";
					}
					if(!empty($fcache[$forum['fid']]))
					{
						$forums .= "\n<ol>\n";
						$forums .= build_archive_forumbits($forum['fid']);
						$forums .= "</ol>\n";
					}
					$forums .= "</li>\n";
				}
			}
		}
	}
	return $forums;
}
