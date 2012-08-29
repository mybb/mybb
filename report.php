<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: report.php 5297 2010-12-28 22:01:14Z Tomm $
 */

define("IN_MYBB", 1);
define('THIS_SCRIPT', 'report.php');

$templatelist = "report,email_reportpost,emailsubject_reportpost,report_thanks";
require_once "./global.php";

// Load global language phrases
$lang->load("report");

if(!$mybb->user['uid'])
{
	error_no_permission();
}

$type = 'post';
if($mybb->input['type'])
{
	$type = $mybb->input['type'];
}

if($type == 'post')
{
	if($mybb->usergroup['canview'] == 0)
	{
		error_no_permission();
	}

	$error = '';
	$post = get_post($mybb->input['pid']);

	if(!$post['pid'])
	{
		// Invalid post
		$error = $lang->error_invalidpost;
	}
	else
	{
		// Post OK - check for valid forum
		$pid = $post['pid'];
		$forum = get_forum($post['fid']);

		if(!$forum)
		{
			$error = $lang->error_invalidforum;
		}

		// Password protected forums ......... yhummmmy!
		check_forum_password($forum['parentlist']);
	}

	if($error)
	{
		eval("\$report_error = \"".$templates->get("report_error")."\";");
		output_page($report_error);
		exit;
	}

	$report = array();
	$reportedposts = $cache->read("reportedposts");

	$query = $db->simple_select("reportedposts", "*", "pid = '{$pid}' AND (type = 'post' OR type = '')");

	if($db->num_rows($query))
	{
		// Existing report
		$report = $db->fetch_array($query);
	}

	$thread = get_thread($post['tid']);
	if($mybb->input['action'] == "do_report" && $mybb->request_method == "post")
	{
		// Save Report
		verify_post_check($mybb->input['my_post_key']);

		// Are we adding a vote to an existing report?
		if(isset($report['pid']))
		{
			$update_array = array(
				'type' => 'post',
				'reports' => ++$report['votes'],
				'lastreport' => TIME_NOW,
				'lastreporter' => $mybb->user['uid']
			);

			$db->update_query("reportedposts", $update_array, "rid = '{$report['rid']}'");
		}
		else
		{
			// This is a new report, check for reasons
			if(!$mybb->input['reason'] && !trim($mybb->input['comment']))
			{
				// No reason or no comment = no report
				eval("\$report = \"".$templates->get("report_noreason")."\";");
				output_page($report);
				exit;
			}
			else
			{
				$reason = trim($mybb->input['reason']);
				$comment = trim($mybb->input['comment']);

				if(!$reason)
				{
					$reason = 'other';
				}

				if($mybb->settings['reportmethod'] == "email" || $mybb->settings['reportmethod'] == "pms")
				{
					$query = $db->query("
						SELECT DISTINCT u.username, u.email, u.receivepms, u.uid
						FROM ".TABLE_PREFIX."moderators m
						LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid=m.id)
						WHERE m.fid IN (".$forum['parentlist'].") AND m.isgroup = '0'
					");

					$nummods = $db->num_rows($query);

					// Expand the reason
					$lang_string = "report_reason_{$reason}";

					if(isset($lang->$lang_string))
					{
						$reason = $lang->$lang_string;
					}

					$comment = $reason."\n".$comment;

					if(!$nummods)
					{
						unset($query);
						switch($db->type)
						{
							case "pgsql":
							case "sqlite":
								$query = $db->query("
									SELECT u.username, u.email, u.receivepms, u.uid
									FROM ".TABLE_PREFIX."users u
									LEFT JOIN ".TABLE_PREFIX."usergroups g ON (((','|| u.additionalgroups|| ',' LIKE '%,'|| g.gid|| ',%') OR u.usergroup = g.gid))
									WHERE (g.cancp=1 OR g.issupermod=1)
								");
								break;
							default:
								$query = $db->query("
									SELECT u.username, u.email, u.receivepms, u.uid
									FROM ".TABLE_PREFIX."users u
									LEFT JOIN ".TABLE_PREFIX."usergroups g ON (((CONCAT(',', u.additionalgroups, ',') LIKE CONCAT('%,', g.gid, ',%')) OR u.usergroup = g.gid))
									WHERE (g.cancp=1 OR g.issupermod=1)
								");
						}
					}
		
					while($mod = $db->fetch_array($query))
					{
						$emailsubject = $lang->sprintf($lang->emailsubject_reportpost, $mybb->settings['bbname']);
						$emailmessage = $lang->sprintf($lang->email_reportpost, $mybb->user['username'], $mybb->settings['bbname'], $post['subject'], $mybb->settings['bburl'], str_replace('&amp;', '&', get_post_link($post['pid'], $thread['tid'])."#pid".$post['pid']), $thread['subject'], $comment);

						if($mybb->settings['reportmethod'] == "pms" && $mod['receivepms'] != 0 && $mybb->settings['enablepms'] != 0)
						{
							$pm_recipients[] = $mod['uid'];
						}
						else
						{
							my_mail($mod['email'], $emailsubject, $emailmessage);
						}
					}

					if(count($pm_recipients) > 0)
					{
						$emailsubject = $lang->sprintf($lang->emailsubject_reportpost, $mybb->settings['bbname']);
						$emailmessage = $lang->sprintf($lang->email_reportpost, $mybb->user['username'], $mybb->settings['bbname'], $post['subject'], $mybb->settings['bburl'], str_replace('&amp;', '&', get_post_link($post['pid'], $thread['tid'])."#pid".$post['pid']), $thread['subject'], $comment);

						require_once MYBB_ROOT."inc/datahandlers/pm.php";
						$pmhandler = new PMDataHandler();

						$pm = array(
							"subject" => $emailsubject,
							"message" => $emailmessage,
							"icon" => 0,
							"fromid" => $mybb->user['uid'],
							"toid" => $pm_recipients
						);

						$pmhandler->admin_override = true;
						$pmhandler->set_data($pm);

						// Now let the pm handler do all the hard work.
						if(!$pmhandler->validate_pm())
						{
							// Force it to valid to just get it out of here
							$pmhandler->is_validated = true;
							$pmhandler->errors = array();
						}

						$pminfo = $pmhandler->insert_pm();
					}
				}
				else
				{
					$insert_array = array(
						'pid' => $post['pid'],
						'tid' => $post['tid'],
						'fid' => $post['fid'],
						'uid' => $mybb->user['uid'],
						'reportstatus' => 0,
						'reason' => $db->escape_string($reason."\n".$comment),
						'type' => 'post',
						'reports' => 1,
						'dateline' => TIME_NOW,
						'lastreport' => TIME_NOW,
						'lastreporter' => $mybb->user['uid']
					);

					$db->insert_query("reportedposts", $insert_array);
					$cache->update_reportedposts();
				}

				$plugins->run_hooks("report_do_report_end");

				eval("\$report = \"".$templates->get("report_thanks")."\";");
				output_page($report);
				exit;
			}
		}
	}

	// Report a Post
	$plugins->run_hooks("report_start");

	if(isset($report['pid']))
	{
		// Show duplicate message
		eval("\$report_reasons = \"".$templates->get("report_duplicate")."\";");
	}
	else
	{
		// Generate reason box
		$reasons = '';
		$options = $reportedposts['reasons'];

		if($options)
		{
			foreach($options as $key => $option)
			{
				$reason = $option;
				$lang_string = "report_reason_{$key}";

				if(isset($lang->$lang_string))
				{
					$reason = $lang->$lang_string;
				}

				$reasons .= "<option value=\"{$key}\">{$reason}</option>\n";
			}
		}

		$reasons .= "<option value=\"other\">{$lang->report_reason_other}</option>\n";
		eval("\$report_reasons = \"".$templates->get("report_reasons")."\";");
	}

	$plugins->run_hooks("report_end");

	eval("\$report = \"".$templates->get("report")."\";");
	output_page($report);
}
?>