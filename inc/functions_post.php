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

function postify($message, $allowhtml="no", $allowmycode="yes", $allowsmilies="yes", $allowimgcode="yes", $archive=0)
{
	global $db, $mybb, $theme, $plugins;

	$message = dobadwords($message);
	if($allowhtml != "yes")
	{
		$message = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $message); // fix & but allow unicode
		$message = str_replace("<","&lt;",$message);
		$message = str_replace(">","&gt;",$message);
		if($allowimgcode != "yes")
		{
			$message = str_replace("<img","&lt;img",$message);
		}
	}

	/* Parse mycode */
	if($allowmycode != "no")
	{
		$mycode_perms = array();
		$mycode_perms['allowimagecode'] = $allowimagecode;
		$mycode_perms['allowsmilies'] = $allowsmilies;
		
		require_once "class_mycode.php";
		$mycode = new MyCode();
		$message = $mycode->do_mycode($message, $mycode_perms);
	}
	
	$message = fixjavascript($message);

	$message = $plugins->run_hooks("parse_message", $message);
	$message = nl2br($message);
	return $message;
}

function fixjavascript($message)
{
	$message = preg_replace("#javascript:#i", "java script:", $message);
	/* This patch provided by Ryan (try to remove XSS Cross-site scripting issues). */
	$message = preg_replace("#(a)(lert)#ie", "'&#'.ord($1).';$2'", $message);
	$message = preg_replace("#onmouseover#i", "&#111;nmouseover", $message);
	$message = preg_replace("#onmouseout#i", "&#111;nmouseout", $message);
	$message = preg_replace("#onclick#i", "&#111;nclick", $message);
	$message = preg_replace("#onload#i", "&#111;nload", $message);
	$message = eregi_replace("#onsubmit#i", "&#111;nsubmit", $message);
	return $message;
}

function dobadwords($message)
{
	global $db, $badwordcache, $cache;

	if(!$badwordcache)
	{
		$badwordcache = $cache->read("badwords");
	}

	if(is_array($badwordcache))
	{
		reset($badwordcache);
		foreach($badwordcache as $bid => $badword)
		{
			if(!$badword['replacement'])
			{
				$badword['replacement'] = "*****";
			}
			$badword['badword'] = preg_quote($badword['badword']);
			$message = preg_replace("#".$badword['badword']."#i", $badword['replacement'], $message);
		}
	}
	return $message;
}

function makepostbit($post, $pmprevann=0)
{
	global $db, $altbg, $theme, $settings, $mybb, $mybbuser, $postcounter, $titlescache, $page, $templates;
	global $forumpermissions, $attachcache, $lang, $ismod, $inlinecookie, $inlinecount, $groupscache, $fid;
	global $plugins;

	$GLOBALS['post'] = $post;

	if($post['visible'] == 0 && $pmprevann == 0)
	{
		$altbg = "trow_shaded";
	}
	elseif($altbg == "trow1")
	{
		$altbg = "trow2";
	}
	else
	{
		$altbg = "trow1";
	}
	switch($pmprevann)
	{
		case "1": // Message preview
			global $forum;
			$id = 0;
			break;
		case "2": // Private message
			global $message, $pmid;
			$forum['allowhtml'] = $mybb->settings['pmsallowhtml'];
			$forum['allowmycode'] = $mybb->settings['pmsallowmycode'];
			$forum['allowsmilies'] = $mybb->settings['pmsallowsmilies'];
			$forum['allowimgcode'] = $mybb->settings['pmsallowimgcode'];
			$id = $pmid;
			break;
		case "3": // Announcement
			global $announcementarray, $message;
			$forum['allowhtml'] = $announcementarray['allowhtml'];
			$forum['allowmycode'] = $announcementarray['allowmycode'];
			$forum['allowsmilies'] = $announcementarray['allowsmilies'];
			$forum['allowimgcode'] = 'yes';
			break;
		default: // Regular post
			global $forum, $thread, $tid;
			$oldforum = $forum;
			$id = $post['pid'];
			break;
	}

	if(!$postcounter)
	{ // Used to show the # of the post
		if($page > 1)
		{
			$postcounter = $mybb->settings['postsperpage']*($page-1);
		}
		else
		{
			$postcounter = 0;
		}
	}
	if(!$altbg)
	{ // Define the alternate background colour if this is the first post
		$altbg = "trow1";
	}
	$postcounter++;

	// Format the post date and time using mydate
	$post['postdate'] = mydate($mybb->settings['dateformat'], $post['dateline']);
	$post['posttime'] = mydate($mybb->settings['timeformat'], $post['dateline']);

	// Dont want any little 'nasties' in the subject
	$post['subject'] = htmlspecialchars_uni(dobadwords($post['subject']));

	$post['author'] = $post['uid'];

	// Get the usergroup
	if($post['userusername'])
	{
		if(!$post['displaygroup'])
		{
			$post['displaygroup'] = $post['usergroup'];
		}
		$usergroup = $groupscache[$post['displaygroup']];
	}
	else
	{
		$usergroup = $groupscache[1];
	}
	
	if(!is_array($titlescache))
	{
		// Get user titles (i guess we should improve this, maybe in version3.
		$query = $db->query("SELECT * FROM ".TABLE_PREFIX."usertitles ORDER BY posts DESC");
		while($usertitle = $db->fetch_array($query))
		{
			$titlescache[$usertitle['posts']] = $usertitle;
		}
		unset($usertitle);
	}
	
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
		eval("\$post['groupimage'] = \"".$templates->get("postbit_groupimage")."\";");
	}

	if($post['userusername'])
	{ // This post was made by a registered user

		$post['username'] = $post['userusername'];
		$post['profilelink'] = "<a href=\"".str_replace("{uid}", $post['uid'], PROFILE_URL)."\">".formatname($post['username'], $post['usergroup'], $post['displaygroup'])."</a>";
		if(trim($post['usertitle']) != "")
		{
			$hascustomtitle = 1;
		}
		if($usergroup['usertitle'] != "" && !$hascustomtitle)
		{
			$post['usertitle'] = $usergroup['usertitle'];
		}
		elseif(is_array($titlescache) && !$usergroup['usertitle'])
		{
			reset($titlescache);
			foreach($titlescache as $key => $titleinfo)
			{
				if($post['postnum'] >= $key)
				{
					if(!$hascustomtitle)
					{
						$post['usertitle'] = $titleinfo['title'];
					}
					$post['stars'] = $titleinfo['stars'];
					$post['starimage'] = $titleinfo['starimage'];
					break;
				}
			}
		}

		if($usergroup['stars'])
		{
			$post['stars'] = $usergroup['stars'];
		}
		
		if(!$post['starimage'])
		{
			$post['starimage'] = $usergroup['starimage'];
		}
		for($i = 0; $i < $post['stars']; $i++)
		{
			$post['userstars'] .= "<img src=\"".$post['starimage']."\" border=\"0\" alt=\"*\" />";
		}
		if($post['userstars'] && $post['starimage'] && $post['stars'])
		{
			$post['userstars'] .= "<br />";
		}
		$post['postnum'] = mynumberformat($post['postnum']);
		
		// Determine the status to show for the user (Online/Offline/Away)
		$timecut = time() - $mybb->settings['wolcutoff'];
		if($post['lastactive'] > $timecut && ($post['invisible'] != "yes" || $mybb->usergroup['canviewwolinvis'] == "yes") && $post['lastvisit'] != $post['lastactive'])
		{
			eval("\$post['onlinestatus'] = \"".$templates->get("postbit_online")."\";");
		}
		else
		{
			if($post['away'] == "yes" && $mybb->settings['allowaway'] != "no")
			{
				eval("\$post['onlinestatus'] = \"".$templates->get("postbit_away")."\";");
			}
			else
			{
				eval("\$post['onlinestatus'] = \"".$templates->get("postbit_offline")."\";");
			}
		}

		if($post['avatar'] != "" && $mybb->user['showavatars'] != "no")
		{
			$post['avatar'] = htmlspecialchars_uni($post['avatar']);
			eval("\$post['useravatar'] = \"".$templates->get("postbit_avatar")."\";");
		}
		else
		{
			$post['useravatar'] = "";
		}
		eval("\$post['button_profile'] = \"".$templates->get("postbit_profile")."\";");
		eval("\$post['button_find'] = \"".$templates->get("postbit_find")."\";");
		eval("\$post['button_pm'] = \"".$templates->get("postbit_pm")."\";");
		if($post['website'] != "")
		{
			$post['website'] = htmlspecialchars_uni($post['website']);
			eval("\$post['button_www'] = \"".$templates->get("postbit_www")."\";");
		}
		else
		{
			$post['button_www'] = "";
		}
		if($post['hideemail'] != "yes")
		{
			eval("\$post['button_email'] = \"".$templates->get("postbit_email")."\";");
		}
		else
		{
			$post['button_email'] = "";
		}
		$post['userregdate'] = mydate($mybb->settings['regdateformat'], $post['regdate']);

		// Work out the reputation this user has
		if($usergroup['usereputationsystem'] != "no")
		{
			if($mybb->usergroup['cangivereputations'] == "yes")
			{
				if(!$pmprevann)
				{
					$post['neglink'] = "<a href=\"javascript:MyBB.reputation(".$post['pid'].", 'n');\">[-]</a> ";
					$post['poslink'] = " <a href=\"javascript:MyBB.reputation(".$post['pid'].", 'p');\">[+]</a>";
				}
				else
				{
					$post['neglink'] = "";
					$post['poslink'] = "";
				}
			}
			$post['userreputation'] = getreputation($post['reputation']);
			eval("\$post['replink'] = \"".$templates->get("postbit_reputation")."\";");
		}
	}
	else
	{ // Message was posted by a guest or an unknown user
		$post['username'] = $post['username'];
		$post['profilelink'] = formatname($post['username'], 1);
		if($usergroup['usertitle'])
		{
			$post['usertitle'] = $usergroup['usertitle'];
		}
		else
		{
			$post['usertitle'] = $lang->guest;
		}
		$usergroup['title'] = $lang->na;

		$post['userregdate'] = $lang->na;
		$post['postnum'] = $lang->na;
		$post['button_profile'] = "";
		$post['button_email'] = "";
		$post['button_www'] = "";
		$post['signature'] = "";
		$post['button_pm'] = "";
		$post['button_find'] = "";
		$post['onlinestatus'] = $lang->unknown;
		$post['replink'] = "";
	}
	if(!$pmprevann)
	{
		if($post['edituid'] != "" && $post['edittime'] != "" && $post['editusername'] != "")
		{
			$post['editdate'] = mydate($mybb->settings['dateformat'], $post['edittime']);
			$post['edittime'] = mydate($mybb->settings['timeformat'], $post['edittime']);
			$post['editnote'] = sprintf($lang->postbit_edited, $post['editdate'], $post['edittime']);
			eval("\$post['editedmsg'] = \"".$templates->get("postbit_editedby")."\";");
		}
		if((ismod($fid, "caneditposts") == "yes" || $mybb->user['uid'] == $post['uid']) && $mybb->user['uid'] != 0)
		{
			eval("\$post['button_edit'] = \"".$templates->get("postbit_edit")."\";");
		}
		// Quick Delete button
		if((ismod($fid, "candeleteposts") == "yes" || $mybb->user['uid'] == $post['uid']) && $mybb->user['uid'] != 0)
		{
			eval("\$post['button_quickdelete'] = \"".$templates->get("postbit_quickdelete")."\";");
		}
		// Inline moderation stuff
		if($ismod)
		{
			if(strstr($_COOKIE[$inlinecookie], "|".$post['pid']."|"))
			{
				$inlinecheck = "checked=\"checked\"";
				$inlinecount++;
			}
			else
			{
				$inlinecheck = "";
			}
			eval("\$post['inlinecheck'] = \"".$templates->get("postbit_inlinecheck")."\";");
			if($post['visible'] == 0)
			{
				$invisiblepost = 1;
			}
		}
		else
		{
			$post['inlinecheck'] = "";
		}
		eval("\$post['posturl'] = \"".$templates->get("postbit_posturl")."\";");
		global $forum, $thread;
		if($forum['open'] != "no" && ($thread['closed'] != "yes" || ismod($forum['fid']) == "yes"))
		{
			eval("\$post['button_quote'] = \"".$templates->get("postbit_quote")."\";");
		}
		if($forumpermissions['canpostreplys'] != "no" && ($thread['closed'] != "yes" || ismod($fid) == "yes") && $mybb->settings['quickreply'] != "off" && $mybb->settings['quickquote'] != "off" && $mybb->user['showquickreply'] != "no" && $forum['open'] != "no" && !$pmprevann)
		{
			eval("\$post['button_quickquote'] = \"".$templates->get("postbit_quickquote")."\";");
			$post['quickquote_message'] = htmlspecialchars($post['message']);
			eval("\$post['qqmessage'] = \"".$templates->get("postbit_qqmessage")."\";");
		}
		if($mybb->user['uid'] != "0")
		{
			eval("\$post['button_report'] = \"".$templates->get("postbit_report")."\";");
		}

		if($mybb->settings['logip'] != "no")
		{
			if($mybb->settings['logip'] == "show")
			{
				eval("\$post['iplogged'] = \"".$templates->get("postbit_iplogged_show")."\";");
			}
			else if($mybb->settings['logip'] == "hide" && $ismod)
			{
				eval("\$post['iplogged'] = \"".$templates->get("postbit_iplogged_hiden")."\";");
			}
			else
			{
				$post['iplogged'] = "";
			}
		}
		else
		{
				$post['iplogged'] = "";
		}

	}
	if($post['smilieoff'] == "yes")
	{
		$allowsmilies = "no";
	}
	else
	{
		$allowsmilies = $forum['allowsmilies'];
	}
	$post['message'] = postify($post['message'], $forum['allowhtml'], $forum['allowmycode'], $allowsmilies, $forum['allowimgcode']);

	if(is_array($attachcache[$id]))
	{ // This post has 1 or more attachments
		$validationcount = 0;
		foreach($attachcache[$id] as $aid => $attachment)
		{
			if($attachment['visible'])
			{ // There is an attachment thats visible!
				$attachment['name'] = htmlspecialchars_uni($attachment['name']);
				$attachment['filesize'] = getfriendlysize($attachment['filesize']);
				$ext = getextention($attachment['filename']);
				if($ext == "jpeg" || $ext == "gif" || $ext == "bmp" || $ext == "png" || $ext == "jpg")
				{
					$isimage = true;
				}
				else
				{
					$isimage = false;
				}
				$attachment['icon'] = getattachicon($ext);
				// Support for [attachment=id] code
				if(stripos($post['message'], "[attachment=".$attachment['aid']."]") !== false)
				{
					if($attachment['thumbnail'] != "SMALL" && $attachment['thumbnail'] != "")
					{ // We have a thumbnail to show (and its not the "SMALL" enough image
						eval("\$attbit = \"".$templates->get("postbit_attachments_thumbnails_thumbnail")."\";");
					}
					elseif($attachment['thumbnail'] == "SMALL" && $forumpermissions['candlattachments'] == "yes")
					{
						// Image is small enough to show - no thumbnail
						eval("\$attbit = \"".$templates->get("postbit_attachments_images_image")."\";");
					}
					else
					{
						// Show standard link to attachment
						eval("\$attbit = \"".$templates->get("postbit_attachments_attachment")."\";");
					}
					$post['message'] = preg_replace("#\[attachment=".$attachment['aid']."]#si", $attbit, $post['message']);
				}
				else
				{
					if($attachment['thumbnail'] != "SMALL" && $attachment['thumbnail'] != "")
					{ // We have a thumbnail to show
						eval("\$post['thumblist'] .= \"".$templates->get("postbit_attachments_thumbnails_thumbnail")."\";");
						if($tcount == 5)
						{
							$thumblist .= "<br />";
							$tcount = 0;
						}
						$tcount++;
					}
					elseif($attachment['thumbnail'] == "SMALL" && $forumpermissions['candlattachments'] == "yes")
					{
						// Image is small enough to show - no thumbnail
						eval("\$post['imagelist'] .= \"".$templates->get("postbit_attachments_images_image")."\";");
					}
					else
					{
						eval("\$post['attachmentlist'] .= \"".$templates->get("postbit_attachments_attachment")."\";");
					}
				}
			}
			else
			{
				$validationcount++;
			}
		}
		if($post['thumblist'])
		{
			eval("\$post['attachedthumbs'] = \"".$templates->get("postbit_attachments_thumbnails")."\";");
		}
		if($post['imagelist'])
		{
			eval("\$post['attachedimages'] = \"".$templates->get("postbit_attachments_images")."\";");
		}
		if($post['attachmentlist'] || $post['thumblist'] || $post['imagelist'])
		{
			eval("\$post['attachments'] = \"".$templates->get("postbit_attachments")."\";");
		}
	}

	if($post['includesig'] != "no" && $post['username'] && $post['signature'] != "" && $mybb->user['showsigs'] != "no")
	{
		$post['signature'] = postify(stripslashes($post['signature']), $mybb->settings['sightml'], $mybb->settings['sigmycode'], $mybb->settings['sigsmilies'], $mybb->settings['sigimgcode']);
		eval("\$post['signature'] = \"".$templates->get("postbit_signature")."\";");
	}
	else
	{
		$post['signature'] = "";
	}

	if($post['iconpath'])
	{
		$post['icon'] = "<img src=\"".$post['iconpath']."\" alt=\"".$post['iconname']."\">&nbsp;";
	}
	else
	{
		$post['icon'] = "";
	}
	$GLOBALS['post'] =& $post;
	switch($pmprevann)
	{
		case 1: // Message preview
			$plugins->run_hooks("postbit_prev");
			break;
		case 2: // Private message
			$plugins->run_hooks("postbit_pm");
			break;
		case 3: // Announcement
			$plugins->run_hooks("postbit_announcement");
			break;
		default: // Regular post
			eval("\$seperator = \"".$templates->get("postbit_seperator")."\";");
			$plugins->run_hooks("postbit");
			break;
	}
	eval("\$postbit = \"".$templates->get("postbit")."\";");
	return $postbit;
	$GLOBALS['post'] = "";
}
?>
