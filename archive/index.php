<?php
/**
 * MyBB 1.2
 * Copyright © 2006 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

define("IN_MYBB", 1);

require_once "./global.php"
require_once MYBB_ROOT."inc/functions_post.php";
// Load global language phrases
$lang->load("index");

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
			if($forumpermissions['canview'] != "yes" || $forumpermissions['canviewthreads'] != 'yes')
			{
				archive_error_no_permission();
			}
		}
		
		$announcement['subject'] = htmlspecialchars_uni($parser->parse_badwords($announcement['subject']));
		
		$parser_options = array(
			"allow_html" => $announcement['allowhtml'],
			"allow_mycode" => $announcement['allowmycode'],
			"allow_smilies" => $announcement['allowsmilies'],
			"allow_imgcode" => $announcement['allowmycode'],
			"me_username" => $announcement['username']
		);

		$announcement['message'] = $parser->parse_message($announcement['message'], $parser_options);

		$profile_link = build_profile_link($announcement['username'], $announcement['uid']);

		// Build the navigation
		add_breadcrumb($announcement['subject']);
		archive_header($announcement['subject'], $announcement['subject'], $mybb->settings['bburl']."/announcements.php?aid={$id}");

		// Format announcement contents.
		$announcement['startdate'] = mydate($mybb->settings['dateformat'].", ".$mybb->settings['timeformat'], $announcement['startdate']);

		echo "<div class=\"post\">\n<div class=\"header\">\n<h2>{$announcement['subject']} - {$profile_link}</h2>";
		echo "<div class=\"dateline\">{$announcement['startdate']}</div>\n</div>\n<div class=\"message\">{$announcement['message']}</div>\n</div>\n";

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
		if($forumpermissions['canview'] != "yes" || $forumpermissions['canviewthreads'] != 'yes')
		{
			archive_error_no_permission();
		}
		// Build the navigation
		build_forum_breadcrumb($forum['fid'], 1);
		add_breadcrumb($thread['subject']);

		archive_header($thread['subject'], $thread['subject'], $mybb->settings['bburl']."/showthread.php?tid=$id");

		// Paginate this thread
		$perpage = $mybb->settings['postsperpage'];
		$postcount = intval($thread['replies'])+1;
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
		$query = $db->simple_select(TABLE_PREFIX."posts", "pid", "tid='{$id}' AND visible='1'", array('limit_start' => $start, 'limit' => $perpage));
		while($post = $db->fetch_array($query))
		{
			$pids[$post['pid']] = $post['pid'];
		}
		
		$pids = implode(",", $pids);

		// Build attachments cache
		$query = $db->simple_select(TABLE_PREFIX."attachments", "*", "pid IN ({$pids})");
		while($attachment = $db->fetch_array($query))
		{
			$acache[$attachment['pid']][$attachment['aid']] = $attachment;
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
			$post['date'] = mydate($mybb->settings['dateformat'].", ".$mybb->settings['timeformat'], $post['dateline'], "", 0);
			if($post['userusername'])
			{
				$post['username'] = $post['userusername'];
			}
			
			// Parse the message
			$parser_options = array(
				"allow_html" => $forum['allow_html'],
				"allow_mycode" => $forum['allow_mycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode'],
				"me_username" => $post['username']
			);
			if($post['smilieoff'] == "yes")
			{
				$parser_options['allow_smilies'] = "no";
			}

			$post['message'] = $parser->parse_message($post['message'], $parser_options);

			// Is there an attachment in this post?
			if(is_array($acache[$post['pid']]))
			{
				foreach($acache[$post['pid']] as $aid => $attachment)
				{
					$post['message'] = str_replace("[attachment=$attachment[aid]]", "[<a href=\"".$mybb->settings['bburl']."/attachment.php?aid=$attachment[aid]\">attachment=$attachment[aid]</a>]", $post['message']);
				}
			}

			// Damn thats a lot of parsing, now to determine which username to show..
			if($post['userusername'])
			{
				$post['username'] = "<a href=\"".$mybb->settings['bburl']."/member.php?action=profile&amp;uid=".$post['uid']."\">".$post['userusername']."</a>";
			}

			// Finally show the post
			echo "<div class=\"post\">\n<div class=\"header\">\n<div class=\"author\"><h2>{$post['username']}</h2></div>";
			echo "<div class=\"dateline\">{$post['date']}</div>\n</div>\n<div class=\"message\">{$post['message']}</div>\n</div>\n";
		}
		archive_multipage($postcount, $perpage, $page, "{$base_url}thread-$id");

		archive_footer();
		break;

	// Display a category or a forum.
	case "forum":
		// Check if we have permission to view this forum
		$forumpermissions = forum_permissions($forum['fid']);
		if($forumpermissions['canview'] != "yes")
		{
			archive_error_no_permission();
		}

		// Paginate this forum
		$query = $db->simple_select(TABLE_PREFIX."threads", "COUNT(tid) AS threads", "fid='{$id}' AND visible='1'");
		$threadcount = $db->fetch_field($query, "threads");

		// Build the navigation
		build_forum_breadcrumb($forum['fid'], 1);

		// No threads and not a category? Error!
		if($threadcount < 1 && $forum['type'] != 'c')
		{
			archive_header($forum['name'], $forum['name'], $mybb->settings['bburl']."/forumdisplay.php?fid={$id}");
			archive_error($lang->error_nothreads);
		}

		// Build the archive header.
		archive_header($forum['name'], $forum['name'], $mybb->settings['bburl']."/forumdisplay.php?fid={$id}");

		$perpage = $mybb->settings['threadsperpage'];
		$pages = ceil($threadcount/$perpage);
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
		$query = $db->simple_select(TABLE_PREFIX."forums", "COUNT(fid) AS subforums", "pid='{$id}' AND status='1'");
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

		// Get the announcements if the forum is not a category.
		if($forum['type'] == 'f')
		{
			$sql = build_parent_list($forum['fid'], "fid", "OR", $form['parentlist']);
			$time = time();
			$query = $db->simple_select(TABLE_PREFIX."announcements", "*", "startdate < '{$time}' AND (enddate > '{$time}' OR enddate=0) AND ({$sql} OR fid='-1')");
			if($db->num_rows($query) > 0)
			{
				echo "<div class=\"announcementlist\">\n";
				echo "<h3>{$lang->forumbit_announcements}</h3>";
				echo "<ol>\n";
				while($announcement = $db->fetch_array($query))
				{
					echo "<li><a href=\"{$base_url}announcement-{$announcement['aid']}.html\">{$announcement['subject']}</a></li>";
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
			$query = $db->simple_select(TABLE_PREFIX."threads", "*", "fid='{$id}' AND visible='1' AND sticky='1' AND closed NOT LIKE 'moved|%'", $options);
			if($db->num_rows($query) > 0)
			{
				echo "<div class=\"threadlist\">\n";
				echo "<h3>{$lang->forumbit_stickies}</h3>";
				echo "<ol>\n";
				while($sticky = $db->fetch_array($query))
				{
					if($sticky['replies'] != 1)
					{
						$lang_reply_text = $lang->archive_replies;
					}
					else
					{
						$lang_reply_text = $lang->archive_reply;
					}					
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
			$query = $db->simple_select(TABLE_PREFIX."threads", "*", "fid='{$id}' AND visible='1' AND sticky='0' AND closed NOT LIKE 'moved|%'", $options);
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
					echo "<li><a href=\"{$base_url}thread-{$thread['tid']}.html\">{$thread['subject']}</a>";
					echo "<span class=\"replycount\"> ({$thread['replies']} {$lang_reply_text})</span></li>";
				}
				echo "</ol>\n</div>\n";
			}
		}

		echo "</div>\n";

		archive_multipage($threadcount, $perpage, $page, "{$base_url}forum-$id");
		archive_footer();
		break;

	// Display the board home.
	case "index":
		// Build our forum listing
		$forums = build_archive_forumbits(0);
		archive_header("", $mybb->settings['bbname'], $mybb->settings['bburl']."/index.php");
		echo "<div class=\"listing forumlist\">\n<div class=\"header\">{$mybb->settings['bbname']}</div>\n<div class=\"forums\">\n<ul>\n";
		echo $forums;
		echo "\n</ul>\n</div>\n</div>";
		archive_footer();
		break;
	default:
		header("HTTP/1.0 404 Not Found");
		echo $lang->archive_not_found;
		exit;
}

/**
* Gets a list of forums and possibly subforums.
*
* @param int The parent forum to get the childforums for.
* @return array Array of information regarding the child forums of this parent forum
*/
function build_archive_forumbits($pid=0)
{
	global $db, $forumpermissions, $mybb, $lang, $archiveurl, $base_url;

	// Sort out the forum cache first.
	static $fcache;
	if(!is_array($fcache))
	{
		// Fetch forums
		$query = $db->simple_select(TABLE_PREFIX."forums", "*", "active!='no' AND password=''", array('order_by' =>'pid, disporder'));
		while($forum = $db->fetch_array($query))
		{
			$fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}
		$forumpermissions = forum_permissions();
	}

	// Start the process.
	if(is_array($fcache[$pid]))
	{
		foreach($fcache[$pid] as $key => $main)
		{
			foreach($main as $key => $forum)
			{
				$perms = $forumpermissions[$forum['fid']];
				if(($perms['canview'] == "yes" || $mybb->settings['hideprivateforums'] == "no") && $forum['active'] != "no")
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
					if($fcache[$forum['fid']])
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
?>