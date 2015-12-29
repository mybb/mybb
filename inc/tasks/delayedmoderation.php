<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

function task_delayedmoderation($task)
{
	global $db, $lang, $plugins;

	require_once MYBB_ROOT."inc/class_moderation.php";
	$moderation = new Moderation;

	require_once MYBB_ROOT."inc/class_custommoderation.php";
	$custommod = new CustomModeration;

	// Iterate through all our delayed moderation actions
	$query = $db->simple_select("delayedmoderation", "*", "delaydateline <= '".TIME_NOW."'");
	while($delayedmoderation = $db->fetch_array($query))
	{
		if(is_object($plugins))
		{
			$args = array(
				'task' => &$task,
				'delayedmoderation' => &$delayedmoderation,
			);
			$plugins->run_hooks('task_delayedmoderation', $args);
		}

		$tids = explode(',', $delayedmoderation['tids']);
		$input = my_unserialize($delayedmoderation['inputs']);

		if(my_strpos($delayedmoderation['type'], "modtool") !== false)
		{
			list(, $custom_id) = explode('_', $delayedmoderation['type'], 2);
			$custommod->execute($custom_id, $tids);
		}
		else
		{
			switch($delayedmoderation['type'])
			{
				case "openclosethread":
					$closed_tids = $open_tids = array();
					$query2 = $db->simple_select("threads", "tid,closed", "tid IN({$delayedmoderation['tids']})");
					while($thread = $db->fetch_array($query2))
					{
						if($thread['closed'] == 1)
						{
							$closed_tids[] = $thread['tid'];
						}
						else
						{
							$open_tids[] = $thread['tid'];
						}
					}

					if(!empty($closed_tids))
					{
						$moderation->open_threads($closed_tids);
					}

					if(!empty($open_tids))
					{
						$moderation->close_threads($open_tids);
					}
					break;
				case "deletethread":
					foreach($tids as $tid)
					{
						$moderation->delete_thread($tid);
					}
					break;
				case "move":
					foreach($tids as $tid)
					{
						$moderation->move_thread($tid, $input['new_forum']);
					}
					break;
				case "stick":
					$unstuck_tids = $stuck_tids = array();
					$query2 = $db->simple_select("threads", "tid,sticky", "tid IN({$delayedmoderation['tids']})");
					while($thread = $db->fetch_array($query2))
					{
						if($thread['sticky'] == 1)
						{
							$stuck_tids[] = $thread['tid'];
						}
						else
						{
							$unstuck_tids[] = $thread['tid'];
						}
					}

					if(!empty($stuck_tids))
					{
						$moderation->unstick_threads($stuck_tids);
					}

					if(!empty($unstuck_tids))
					{
						$moderation->stick_threads($unstuck_tids);
					}
					break;
				case "merge":
					// $delayedmoderation['tids'] should be a single tid
					if(count($tids) != 1)
					{
						continue;
					}

					// explode at # sign in a url (indicates a name reference) and reassign to the url
					$realurl = explode("#", $input['threadurl']);
					$input['threadurl'] = $realurl[0];

					// Are we using an SEO URL?
					if(substr($input['threadurl'], -4) == "html")
					{
						// Get thread to merge's tid the SEO way
						preg_match("#thread-([0-9]+)?#i", $input['threadurl'], $threadmatch);
						preg_match("#post-([0-9]+)?#i", $input['threadurl'], $postmatch);

						if($threadmatch[1])
						{
							$parameters['tid'] = $threadmatch[1];
						}

						if($postmatch[1])
						{
							$parameters['pid'] = $postmatch[1];
						}
					}
					else
					{
						// Get thread to merge's tid the normal way
						$splitloc = explode(".php", $input['threadurl']);
						$temp = explode("&", my_substr($splitloc[1], 1));

						if(!empty($temp))
						{
							for($i = 0; $i < count($temp); $i++)
							{
								$temp2 = explode("=", $temp[$i], 2);
								$parameters[$temp2[0]] = $temp2[1];
							}
						}
						else
						{
							$temp2 = explode("=", $splitloc[1], 2);
							$parameters[$temp2[0]] = $temp2[1];
						}
					}

					if($parameters['pid'] && !$parameters['tid'])
					{
						$post = get_post($parameters['pid']);
						$mergetid = $post['tid'];
					}
					else if($parameters['tid'])
					{
						$mergetid = $parameters['tid'];
					}

					$mergetid = (int)$mergetid;
					$mergethread = get_thread($mergetid);

					if(!$mergethread['tid'])
					{
						continue;
					}

					if($mergetid == $delayedmoderation['tids'])
					{
						// sanity check
						continue;
					}

					if($input['subject'])
					{
						$subject = $input['subject'];
					}
					else
					{
						$query = $db->simple_select("threads", "subject", "tid='{$delayedmoderation['tids']}'");
						$subject = $db->fetch_field($query, "subject");
					}

					$moderation->merge_threads($mergetid, $delayedmoderation['tids'], $subject);
					break;
				case "removeredirects":
					foreach($tids as $tid)
					{
						$moderation->remove_redirects($tid);
					}
					break;
				case "removesubscriptions":
					$moderation->remove_thread_subscriptions($tids, true);
					break;
				case "approveunapprovethread":
					$approved_tids = $unapproved_tids = array();
					$query2 = $db->simple_select("threads", "tid,visible", "tid IN({$delayedmoderation['tids']})");
					while($thread = $db->fetch_array($query2))
					{
						if($thread['visible'] == 1)
						{
							$approved_tids[] = $thread['tid'];
						}
						else
						{
							$unapproved_tids[] = $thread['tid'];
						}
					}

					if(!empty($approved_tids))
					{
						$moderation->unapprove_threads($approved_tids);
					}

					if(!empty($unapproved_tids))
					{
						$moderation->approve_threads($unapproved_tids);
					}
					break;
				case "softdeleterestorethread":
					$delete_tids = $restore_tids = array();
					$query2 = $db->simple_select("threads", "tid,visible", "tid IN({$delayedmoderation['tids']})");
					while($thread = $db->fetch_array($query2))
					{
						if($thread['visible'] == -1)
						{
							$restore_tids[] = $thread['tid'];
						}
						else
						{
							$delete_tids[] = $thread['tid'];
						}
					}

					if(!empty($restore_tids))
					{
						$moderation->restore_threads($restore_tids);
					}

					if(!empty($delete_tids))
					{
						$moderation->soft_delete_threads($delete_tids);
					}
					break;
			}
		}

		$db->delete_query("delayedmoderation", "did='{$delayedmoderation['did']}'");
	}

	add_task_log($task, $lang->task_delayedmoderation_ran);
}
