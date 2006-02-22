<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id$
 */

require "./global.php";
require "./inc/functions_post.php";
// Load global language phrases
$lang->load("index");

switch($action)
{
	case "thread":
		$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));

		// Fetch the forum this thread is in
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums WHERE fid='".$thread['fid']."' AND active!='no' AND type='f' AND password=''");
		$forum = $db->fetch_array($query);
		if(!$forum['fid'])
		{
			archive_error($lang->error_invalidforum);
		}

		// Check if we have permission to view this thread
		$forumpermissions = forum_permissions($forum['fid']);
		if($forumpermissions['canview'] != "yes") {
			archive_nopermission();
		}
		// Build the navigation
		makeforumnav($forum['fid'], 1);
		addnav($thread['subject']);

		archive_header($thread['subject'], $thread['subject'], $mybb->settings['bburl']."/showthread.php?tid=$id");

		// Paginate this thread
		$perpage = $mybb->settings['postsperpage'];
		$postcount = intval($thread['replies'])+1;
		$pages = ceil($postcount/$perpage);

		if($page > $pages)
		{
			$page = 1;
		}
		if($page) {
			$start = ($page-1) * $perpage;
		} else {
			$start = 0;
			$page = 1;
		}

		// Build attachments cache
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."attachments");
		while($attachment = $db->fetch_array($query))
		{
			$acache[$attachment['pid']][$attachment['aid']] = $attachment;
		}
		
		// Start fetching the posts
		$query = $db->query("SELECT u.*, u.username AS userusername, p.* FROM ".TABLE_PREFIX."posts p LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=p.uid) WHERE p.tid='$id' AND visible='1' ORDER BY p.dateline LIMIT $start, $perpage");
		while($post = $db->fetch_array($query))
		{
			$post['date'] = mydate($mybb->settings['dateformat'].", ".$mybb->settings['timeformat'], $post['dateline'], "", 0);
			// Parse the message
			$parser_options = array(
				"allow_html" => $forum['allow_html'],
				"allow_mycode" => $forum['allow_mycode'],
				"allow_smilies" => $forum['allowsmilies'],
				"allow_imgcode" => $forum['allowimgcode']
			);
			if($post['smilieoff'] == "yes")
			{
				$parser_options['allow_smilies'] = "no";
			}

			$post['message'] = $parser->parse_message($post['message'], $parser_options);
			// do me code
			if($forum['allowmycode'] != "no")
			{
				$post['message'] = domecode($post['message'], $post['username']);
			}

			// Is there an attachment in this post?
			if(is_array($acache[$post['pid']]))
			{
				while(list($aid, $attachment) = each($acache[$post['pid']]))
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
			?>
<div class="post">
<div class="header">
<div class="author"><?php echo $post['username']; ?></div>
<div class="dateline"><?php echo $post['date']; ?></div>
</div>
<div class="message"><?php echo $post['message']; ?></div>
</div>
			<?php
		}
		archive_multipage($postcount, $perpage, $page, "thread-$id");

		archive_footer();
		break;
	case "forum":
		// Check if we have permission to view this forum
		$forumpermissions = forum_permissions($forum['fid']);
		if($forumpermissions['canview'] != "yes") {
			archive_nopermission();
		}
		
		// Paginate this forum
		$query = $db->query("SELECT COUNT(t.tid) AS threads FROM ".TABLE_PREFIX."threads t WHERE t.fid='$id' AND t.visible='1'");
		$threadcount = $db->result($query, 0);

		// Build the navigation
		makeforumnav($forum['fid'], 1);
		archive_header($forum['name'], $forum['name'], $mybb->settings['bburl']."/forumdisplay.php?fid=$id");
		
		if($threadcount < 1)
		{
			archive_error($lang->error_nothreads);
		}

		$perpage = $mybb->settings['threadsperpage'];
		$pages = ceil($threadcount/$perpage);
		if($page > $pages)
		{
			$page = 1;
		}
		if($page) {
			$start = ($page-1) * $perpage;
		} else {
			$start = 0;
			$page = 1;
		}
		?>
<div class="threadlist">
<div class="header"><?php echo $forum['name']; ?></div>
<div class="threads">
<ol>
		<?php
		// Start fetching the threads
		$query = $db->query("SELECT t.* FROM ".TABLE_PREFIX."threads t WHERE t.fid='$id' AND t.visible='1' ORDER BY t.sticky DESC, t.lastpost DESC LIMIT $start, $perpage");
		while($thread = $db->fetch_array($query))
		{
			$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject']));
			$prefix = "";
			if($thread['sticky'] == 1)
			{
				$prefix = "<span class=\"threadprefix\">".$lang->archive_sticky."</span> ";
			}

			if($thread['replies'] != 1)
			{
				$lang_reply_text = $lang->archive_replies;
			}
			else
			{
				$lang_reply_text = $lang->archive_reply;
			}
			?>
<li><?php echo $prefix; ?><a href="<?php echo $archiveurl."/index.php/thread-".$thread['tid'].".html"; ?>"><?php echo $thread['subject']; ?></a> <span class="replycount">(<?php echo $thread['replies'] ." ".$lang_reply_text; ?>)</span></li>
			<?php
		}
		?>
</ol>
</div>
</div>
		<?php
		archive_multipage($threadcount, $perpage, $page, "forum-$id");
		archive_footer();
		break;
	default:
		// Fetch all of the forum permissions
		$forumpermissions = forum_permissions();

		// Fetch forums
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."forums f WHERE active!='no' AND password='' ORDER BY pid, disporder");
		while($forum = $db->fetch_array($query))
		{
			$fcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}

		// Build our forum listing
		$forums = getforums();
		archive_header("", $mybb->settings['bbname'], $mybb->settings['bburl']."/index.php");
?>
<div class="forumlist">
<div class="header"><?php echo $mybb->settings['bbname']; ?></div>
<div class="forums">
<ul>
<?php
		echo $forums;
?>
</ul>
</div>
</div>
<?php
		archive_footer();
		break;
}


function getforums($pid="0", $depth=1, $permissions="")
{
	global $fcache, $forumpermissions, $mybb, $lang, $archiveurl;
	if(is_array($fcache[$pid]))
	{
		while(list($key, $main) = each($fcache[$pid]))
		{
			while(list($key, $forum) = each($main))
			{
				$perms = $forumpermissions[$forum['fid']];
				if(($perms['canview'] == "yes" || $mybb->settings['hideprivateforums'] == "no") && $forum['active'] != "no")
				{
					if($forum['linkto'])
					{
						$forums .= "<li><a href=\"".$forum['linkto']."\">".$forum['name']."</a>";
					}
					elseif($forum['type'] == "c")
					{
						$forums .= "<li><strong>".$forum['name']."</strong>";
					}
					else
					{
						$forums .= "<li><a href=\"$archiveurl/index.php/forum-".$forum['fid'].".html\">".$forum['name']."</a>";
					}
					if($fcache[$forum['fid']])
					{
						$forums .= "\n<ul>\n";
						$newdepth = $depth + 1;
						$forums .= getforums($forum['fid'], $newdepth, $perms);
						$forums .= "</ul>\n";
					}
					$forums .= "</li>\n";
				}
			}
		}
	}
	return $forums;
}
?>