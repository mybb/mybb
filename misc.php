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
define('THIS_SCRIPT', 'misc.php');

$templatelist = "misc_rules_forum,misc_help_helpdoc,misc_whoposted_poster,misc_whoposted,misc_smilies_popup_smilie,misc_smilies_popup,misc_smilies_popup_empty,misc_smilies_popup_row,multipage_start";
$templatelist .= ",misc_buddypopup,misc_buddypopup_user,misc_buddypopup_user_none,misc_buddypopup_user_online,misc_buddypopup_user_offline,misc_buddypopup_user_sendpm,misc_syndication_forumlist";
$templatelist .= ",misc_smilies,misc_smilies_smilie,misc_help_section_bit,misc_help_section,misc_help,forumdisplay_password_wrongpass,forumdisplay_password,misc_helpresults,misc_helpresults_bit";
$templatelist .= ",multipage,multipage_end,multipage_jump_page,multipage_nextpage,multipage_page,multipage_page_current,multipage_page_link_current,multipage_prevpage,misc_imcenter_error";
$templatelist .= ",misc_smilies_popup_no_smilies,misc_smilies_no_smilies,misc_syndication,misc_help_search,misc_helpresults_noresults,misc_syndication_forumlist_forum,misc_syndication_feedurl,misc_whoposted_page";

require_once "./global.php";
require_once MYBB_ROOT."inc/functions_post.php";

// Load global language phrases
$lang->load("misc");

$plugins->run_hooks("misc_start");

$mybb->input['action'] = $mybb->get_input('action');
if($mybb->input['action'] == "dstswitch" && $mybb->request_method == "post" && $mybb->user['uid'] > 0)
{
	if($mybb->user['dstcorrection'] == 2)
	{
		if($mybb->user['dst'] == 1)
		{
			$update_array = array("dst" => 0);
		}
		else
		{
			$update_array = array("dst" => 1);
		}
	}
	$db->update_query("users", $update_array, "uid='{$mybb->user['uid']}'");
	if(!isset($mybb->input['ajax']))
	{
		redirect("index.php", $lang->dst_settings_updated);
	}
	else
	{
		echo "done";
		exit;
	}
}
elseif($mybb->input['action'] == "markread")
{
	if($mybb->user['uid'] && verify_post_check($mybb->get_input('my_post_key'), true) !== true)
	{
		// Protect our user's unread forums from CSRF
		error($lang->invalid_post_code);
	}

	if(isset($mybb->input['fid']))
	{
		$validforum = get_forum($mybb->input['fid']);
		if(!$validforum)
		{
			if(!isset($mybb->input['ajax']))
			{
				error($lang->error_invalidforum);
			}
			else
			{
				echo 0;
				exit;
			}
		}

		require_once MYBB_ROOT."/inc/functions_indicators.php";
		mark_forum_read($mybb->input['fid']);

		$plugins->run_hooks("misc_markread_forum");

		if(!isset($mybb->input['ajax']))
		{
			redirect(get_forum_link($mybb->input['fid']), $lang->redirect_markforumread);
		}
		else
		{
			echo 1;
			exit;
		}
	}
	else
	{

		$plugins->run_hooks("misc_markread_end");
		require_once MYBB_ROOT."/inc/functions_indicators.php";
		mark_all_forums_read();
		redirect("index.php", $lang->redirect_markforumsread);
	}
}
elseif($mybb->input['action'] == "clearpass")
{
	$plugins->run_hooks("misc_clearpass");

	if(isset($mybb->input['fid']))
	{
		if(!verify_post_check($mybb->get_input('my_post_key')))
		{
			error($lang->invalid_post_code);
		}

		my_unsetcookie("forumpass[".$mybb->get_input('fid', MyBB::INPUT_INT)."]");
		redirect("index.php", $lang->redirect_forumpasscleared);
	}
}
elseif($mybb->input['action'] == "rules")
{
    if (isset($mybb->input['fid'])) {
        $plugins->run_hooks("misc_rules_start");

        $fid = $mybb->input['fid'];
        $forum = get_forum($fid);

        if (!$forum || $forum['type'] != "f" || $forum['rules'] == '') {
            error($lang->error_invalidforum);
        }

        $forumpermissions = forum_permissions($forum['fid']);
        if ($forumpermissions['canview'] != 1) {
            error_no_permission();
        }

        if (!$forum['rulestitle']) {
            $forum['rulestitle'] = $lang->sprintf($lang->forum_rules, $forum['name']);
        }

        require_once MYBB_ROOT."inc/class_parser.php";
        $parser = new postParser();
        $parser_options = array(
            "allow_html" => 1,
            "allow_mycode" => 1,
            "allow_smilies" => 1,
            "allow_imgcode" => 1,
            "filter_badwords" => 1
        );

        $forum['rules'] = $parser->parse_message($forum['rules'], $parser_options);

        // Make navigation
        build_forum_breadcrumb($forum['fid']);
        add_breadcrumb($forum['rulestitle']);

        $plugins->run_hooks("misc_rules_end");

        output_page(\MyBB\template('misc/rules.twig', [
            'forum' => $forum,
        ]));
    }
}
elseif($mybb->input['action'] == "do_helpsearch" && $mybb->request_method == "post")
{
	$plugins->run_hooks("misc_do_helpsearch_start");

	if($mybb->settings['helpsearch'] != 1)
	{
		error($lang->error_helpsearchdisabled);
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

	if($mybb->get_input('name', MyBB::INPUT_INT) != 1 && $mybb->get_input('document', MyBB::INPUT_INT) != 1)
	{
		error($lang->error_nosearchresults);
	}

	if($mybb->get_input('document', MyBB::INPUT_INT) == 1)
	{
		$resulttype = "helpdoc";
	}
	else
	{
		$resulttype = "helpname";
	}

	$search_data = array(
		"keywords" => $mybb->get_input('keywords'),
		"name" => $mybb->get_input('name', MyBB::INPUT_INT),
		"document" => $mybb->get_input('document', MyBB::INPUT_INT),
	);

	if($db->can_search == true)
	{
		require_once MYBB_ROOT."inc/functions_search.php";

		$search_results = helpdocument_perform_search_mysql($search_data);
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
	$plugins->run_hooks("misc_do_helpsearch_process");

	$db->insert_query("searchlog", $searcharray);

	$plugins->run_hooks("misc_do_helpsearch_end");
	redirect("misc.php?action=helpresults&sid={$sid}", $lang->redirect_searchresults);
}
elseif($mybb->input['action'] == "helpresults")
{
	if($mybb->settings['helpsearch'] != 1)
	{
		error($lang->error_helpsearchdisabled);
	}

	$sid = $mybb->get_input('sid');
	$query = $db->simple_select("searchlog", "*", "sid='".$db->escape_string($sid)."' AND uid='{$mybb->user['uid']}'");
	$search = $db->fetch_array($query);

	if(!$search)
	{
		error($lang->error_invalidsearch);
	}

	$plugins->run_hooks("misc_helpresults_start");

	add_breadcrumb($lang->nav_helpdocs, "misc.php?action=help");
	add_breadcrumb($lang->search_results, "misc.php?action=helpresults&sid={$sid}");

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
	$query = $db->simple_select("helpdocs", "COUNT(*) AS total", "hid IN(".$db->escape_string($search['querycache']).")");
	$helpcount = $db->fetch_array($query);

	if($upper > $helpcount)
	{
		$upper = $helpcount;
	}
	$multipage = multipage($helpcount['total'], $perpage, $page, "misc.php?action=helpresults&amp;sid='".htmlspecialchars_uni($mybb->get_input('sid'))."'");

    $helpdoclist = [];

    require_once MYBB_ROOT."inc/class_parser.php";
    $parser = new postParser();

    $query = $db->query("
        SELECT h.*, s.enabled
        FROM " . TABLE_PREFIX . "helpdocs h
        LEFT JOIN " . TABLE_PREFIX . "helpsections s ON (s.sid=h.sid)
        WHERE h.hid IN(" . $db->escape_string($search['querycache']) . ") AND h.enabled='1' AND s.enabled='1'
        LIMIT {$start}, {$perpage}
    ");
    while ($helpdoc = $db->fetch_array($query)) {
        $parser_options = array(
            'allow_html' => 1,
            'allow_mycode' => 0,
            'allow_smilies' => 0,
            'allow_imgcode' => 0,
            'filter_badwords' => 1
        );
        $helpdoc['helpdoc'] = my_strip_tags($parser->parse_message($helpdoc['document'], $parser_options));

        $plugins->run_hooks("misc_helpresults_bit");
        $helpdoclist[] = $helpdoc;
    }

    $plugins->run_hooks("misc_helpresults_end");

    output_page(\MyBB\template('misc/helpresults.twig', [
        'helpdoclist' => $helpdoclist,
        'highlight' => $highlight,
    ]));
}
elseif($mybb->input['action'] == "help")
{
	$lang->load("helpdocs");
	$lang->load("helpsections");
	$lang->load("customhelpdocs");
	$lang->load("customhelpsections");

	$hid = $mybb->get_input('hid', MyBB::INPUT_INT);
	add_breadcrumb($lang->nav_helpdocs, "misc.php?action=help");

    if ($hid) {
        $query = $db->query("
            SELECT h.*, s.enabled AS section
            FROM " . TABLE_PREFIX . "helpdocs h
            LEFT JOIN " . TABLE_PREFIX . "helpsections s ON (s.sid=h.sid)
            WHERE h.hid = '{$hid}'
        ");

        $helpdoc = $db->fetch_array($query);
        if ($helpdoc['section'] != 0 && $helpdoc['enabled'] != 0) {
            $plugins->run_hooks("misc_help_helpdoc_start");

            // If we have incoming search terms to highlight - get it done (only if not using translation).
            if (!empty($mybb->input['highlight']) && $helpdoc['usetranslation'] != 1) {
                require_once MYBB_ROOT."inc/class_parser.php";
                $parser = new postParser();

                $highlight = $mybb->input['highlight'];
                $helpdoc['name'] = $parser->highlight_message($helpdoc['name'], $highlight);
                $helpdoc['document'] = $parser->highlight_message($helpdoc['document'], $highlight);
            }

            if ($helpdoc['usetranslation'] == 1) {
                $langnamevar = "d".$helpdoc['hid']."_name";
                $langdescvar = "d".$helpdoc['hid']."_desc";
                $langdocvar = "d".$helpdoc['hid']."_document";
                if ($lang->$langnamevar) {
                    $helpdoc['name'] = $lang->$langnamevar;
                }

                if ($lang->$langdescvar) {
                    $helpdoc['description'] = $lang->$langdescvar;
                }

                if ($lang->$langdocvar) {
                    $helpdoc['document'] = $lang->$langdocvar;
                }
            }

            if ($helpdoc['hid'] == 3) {
                $helpdoc['document'] = $lang->sprintf($helpdoc['document'], $mybb->post_code);
            }

            add_breadcrumb($helpdoc['name']);

            $plugins->run_hooks("misc_help_helpdoc_end");

            output_page(\MyBB\template('misc/help_helpdoc.twig', [
                'helpdoc' => $helpdoc,
            ]));
        } else {
            error($lang->error_invalidhelpdoc);
        }
    } else {
        $plugins->run_hooks("misc_help_section_start");

        $query = $db->simple_select("helpdocs", "*", "", array('order_by' => 'sid, disporder'));
        while ($helpdoc = $db->fetch_array($query)) {
            $helpdocs[$helpdoc['sid']][$helpdoc['disporder']][$helpdoc['hid']] = $helpdoc;
        }

        unset($helpdoc);

        $sections = [];
        $query = $db->simple_select("helpsections", "*", "enabled != 0", array('order_by' => 'disporder'));
        while ($section = $db->fetch_array($query)) {
            if ($section['usetranslation'] == 1) {
                $langnamevar = "s".$section['sid']."_name";
                $langdescvar = "s".$section['sid']."_desc";

                if ($lang->$langnamevar) {
                    $section['name'] = $lang->$langnamevar;
                }

                if ($lang->$langdescvar) {
                    $section['description'] = $lang->$langdescvar;
                }
            }

            if (is_array($helpdocs[$section['sid']])) {
                $section['helpdocs'] = [];
                foreach ($helpdocs[$section['sid']] as $key => $bit) {
                    foreach ($bit as $key => $helpdoc) {
                        if ($helpdoc['enabled'] != 0) {
                            if ($helpdoc['usetranslation'] == 1) {
                                $langnamevar = "d".$helpdoc['hid'].'_name';
                                $langdescvar = "d".$helpdoc['hid'].'_desc';

                                if ($lang->$langnamevar) {
                                    $helpdoc['name'] = $lang->$langnamevar;
                                }

                                if ($lang->$langdescvar) {
                                    $helpdoc['description'] = $lang->$langdescvar;
                                }
                            }

                            $section['helpdocs'][] = $helpdoc;
                        }
                    }

                    $sname = "sid_".$section['sid']."_c";
                    if (isset($collapsed[$sname]) && $collapsed[$sname] == "display: show;") {
                        $section['expcolimage'] = "collapse_collapsed.png";
                        $section['expdisplay'] = "display: none;";
                        $section['expthead'] = " thead_collapsed";
                    } else {
                        $section['expcolimage'] = "collapse.png";
                        $section['expthead'] = '';
                        $section['expdisplay'] = '';
                    }
                }

                $sections[] = $section;
            }
        }

        $plugins->run_hooks("misc_help_section_end");

        output_page(\MyBB\template('misc/help.twig', [
            'sections' => $sections,
        ]));
    }
}
elseif($mybb->input['action'] == "buddypopup")
{
	$plugins->run_hooks("misc_buddypopup_start");

	if($mybb->user['uid'] == 0)
	{
		error_no_permission();
	}

	if(isset($mybb->input['removebuddy']) && verify_post_check($mybb->input['my_post_key']))
	{
		$buddies = $mybb->user['buddylist'];
		$namesarray = explode(",", $buddies);
		$mybb->input['removebuddy'] = $mybb->get_input('removebuddy', MyBB::INPUT_INT);
		if(is_array($namesarray))
		{
			foreach($namesarray as $key => $buddyid)
			{
				if($buddyid == $mybb->input['removebuddy'])
				{
					unset($namesarray[$key]);
				}
			}
			$buddylist = implode(',', $namesarray);
			$db->update_query("users", array('buddylist' => $buddylist), "uid='".$mybb->user['uid']."'");
			$mybb->user['buddylist'] = $buddylist;
		}
	}

    // Load Buddies
    $buddies['showlist'] = false;
    if ($mybb->user['buddylist'] != "") {
        $buddys = array('online' => [], 'offline' => []);
        $timecut = TIME_NOW - $mybb->settings['wolcutoff'];

        $query = $db->simple_select("users", "*", "uid IN ({$mybb->user['buddylist']})", array('order_by' => 'lastactive'));
        while ($buddy = $db->fetch_array($query)){
            $buddy_name = format_name($buddy['username'], $buddy['usergroup'], $buddy['displaygroup']);
            $buddy['profile_link'] = build_profile_link($buddy_name, $buddy['uid'], '_blank', 'if(window.opener) { window.opener.location = this.href; return false; }');

            $buddy['show_pm'] = false;
            if ($mybb->user['receivepms'] != 0 && $buddy['receivepms'] != 0 && $groupscache[$buddy['usergroup']]['canusepms'] != 0) {
                $buddy['show_pm'] = true;
            }

            if ($buddy['lastactive']) {
                $buddy['last_active'] = my_date('relative', $buddy['lastactive']);
            }

            $buddy['avatar'] = format_avatar($buddy['avatar'], $buddy['avatardimensions'], '44x44');

            if ($buddy['lastactive'] > $timecut && ($buddy['invisible'] == 0 || $mybb->user['usergroup'] == 4) && $buddy['lastvisit'] != $buddy['lastactive']) {
                $buddys['online'][] = $buddy;
            } else {
                $buddys['offline'][] = $buddy;
            }
        }

        $buddies['showlist'] = true;
    }

    $plugins->run_hooks("misc_buddypopup_end");

    output_page(\MyBB\template('misc/buddypopup.twig', [
        'buddies' => $buddies,
        'buddys' => $buddys,
    ]));
    exit;
}
elseif($mybb->input['action'] == "whoposted")
{
	$tid = $mybb->get_input('tid', MyBB::INPUT_INT);
	$thread = get_thread($tid);
	$modal = $mybb->get_input('modal', MyBB::INPUT_INT);
	$thread['numposts'] = 0;

	// Make sure we are looking at a real thread here.
	if(!$thread)
	{
		error($lang->error_invalidthread);
	}

	// Make sure we are looking at a real thread here.
	if(($thread['visible'] == -1 && !is_moderator($thread['fid'], "canviewdeleted")) || ($thread['visible'] == 0 && !is_moderator($thread['fid'], "canviewunapprove")) || $thread['visible'] > 1)
	{
		error($lang->error_invalidthread);
	}

	if(is_moderator($thread['fid'], "canviewdeleted") || is_moderator($thread['fid'], "canviewunapprove"))
	{
		if(is_moderator($thread['fid'], "canviewunapprove") && !is_moderator($thread['fid'], "canviewdeleted"))
		{
			$show_posts = "p.visible IN (0,1)";
		}
		elseif(is_moderator($thread['fid'], "canviewdeleted") && !is_moderator($thread['fid'], "canviewunapprove"))
		{
			$show_posts = "p.visible IN (-1,1)";
		}
		else
		{
			$show_posts = "p.visible IN (-1,0,1)";
		}
	}
	else
	{
		$show_posts = "p.visible = 1";
	}

	// Does the thread belong to a valid forum?
	$forum = get_forum($thread['fid']);
	if(!$forum || $forum['type'] != "f")
	{
		error($lang->error_invalidforum);
	}

	// Does the user have permission to view this thread?
	$forumpermissions = forum_permissions($forum['fid']);

	if($forumpermissions['canview'] == 0 || $forumpermissions['canviewthreads'] == 0 || (isset($forumpermissions['canonlyviewownthreads']) && $forumpermissions['canonlyviewownthreads'] != 0 && $thread['uid'] != $mybb->user['uid']))
	{
		error_no_permission();
	}

    // Check if this forum is password protected and we have a valid password
    check_forum_password($forum['fid']);

    if ($mybb->get_input('sort') != 'username') {
        $sortsql = ' ORDER BY posts DESC';
    } else {
        $sortsql = ' ORDER BY p.username ASC';
    }

    $whoposted = [];
    $query = $db->query("
        SELECT COUNT(p.pid) AS posts, p.username AS postusername, u.uid, u.username, u.usergroup, u.displaygroup
        FROM " . TABLE_PREFIX . "posts p
        LEFT JOIN " . TABLE_PREFIX . "users u ON (u.uid=p.uid)
        WHERE tid='".$tid."' AND $show_posts
        GROUP BY u.uid, p.username, u.uid, u.username, u.usergroup, u.displaygroup
        ".$sortsql."
    ");
    while ($poster = $db->fetch_array($query)) {
        if ($poster['username'] == '') {
            $poster['username'] = $poster['postusername'];
        }

        $poster_name = format_name($poster['username'], $poster['usergroup'], $poster['displaygroup']);
        if ($modal) {
            $onclick = '';
            if ($poster['uid']) {
                $onclick = "opener.location.href='".get_profile_link($poster['uid'])."'; return false;";
            }

            $poster['profile_link'] = build_profile_link($poster_name, $poster['uid'], '_blank', $onclick);
        } else {
            $poster['profile_link'] = build_profile_link($poster_name, $poster['uid']);
        }

        $thread['numposts'] += $poster['posts'];
        $poster['posts'] = my_number_format($poster['posts']);

        $whoposted[] = $poster;
    }

    $thread['numposts'] = my_number_format($thread['numposts']);

    if ($modal) {
        output_page(\MyBB\template('misc/whoposted_modal.twig', [
            'thread' => $thread,
            'whoposted' => $whoposted,
        ]));
        exit;
    } else {
        require_once MYBB_ROOT."inc/class_parser.php";
        $parser = new postParser;

        // Get thread prefix
        $breadcrumbprefix = '';
        $threadprefix = array('prefix' => '');
        if ($thread['prefix']) {
            $threadprefix = build_prefixes($thread['prefix']);
            if (!empty($threadprefix['displaystyle'])) {
                $breadcrumbprefix = $threadprefix['displaystyle'].'&nbsp;';
            }
        }

        $thread['subject'] = $parser->parse_badwords($thread['subject']);

        // Build the navigation.
        build_forum_breadcrumb($forum['fid']);
        add_breadcrumb($breadcrumbprefix.$thread['subject'], get_thread_link($thread['tid']));
        add_breadcrumb($lang->who_posted);

        output_page(\MyBB\template('misc/whoposted.twig', [
            'thread' => $thread,
            'whoposted' => $whoposted,
        ]));
    }
}
elseif($mybb->input['action'] == "smilies")
{
    $smilies = [];
    if (!empty($mybb->input['popup']) && !empty($mybb->input['editor'])){
        // make small popup list of smilies
        $editor = preg_replace('#([^a-zA-Z0-9_-]+)#', '', $mybb->get_input('editor'));
        $e = 1;
        $smilies_cache = $cache->read("smilies");

        if (is_array($smilies_cache)) {
            $extra_class = ' smilie_pointer';
            foreach ($smilies_cache as $smilie) {
                $smilie['image'] = str_replace("{theme}", $theme['imgdir'], $smilie['image']);
                $smilie['image'] = $mybb->get_asset_url($smilie['image']);

                // Only show the first text to replace in the box
                $temp = explode("\n", $smilie['find']); // use temporary variable for php 5.3 compatibility
                $smilie['find'] = $temp[0];

                $smilie['smilie_insert'] = str_replace(array('\\', "'"), array('\\\\', "\'"), $smilie['find']);

                $smilies_row[] = $smilie;
                if ($e == 2) {
                    $smilies[] = $smilies_row;
                    $smilies_row = '';
                    $e = 1;
                } else {
                    $e = 2;
                }
            }
        }

        output_page(\MyBB\template('misc/smilies_modal.twig', [
            'smilies' => $smilies,
        ]));
    } else {
        add_breadcrumb($lang->nav_smilies);
        $smilies_cache = $cache->read("smilies");

        if (is_array($smilies_cache)) {
            foreach ($smilies_cache as $smilie) {
                $smilie['image'] = str_replace("{theme}", $theme['imgdir'], $smilie['image']);
                $smilie['image'] = $mybb->get_asset_url($smilie['image']);

                $smilies[] = $smilie;
            }
        }

        output_page(\MyBB\template('misc/smilies.twig', [
            'smilies' => $smilies,
        ]));
    }
}
elseif($mybb->input['action'] == "syndication")
{
    $plugins->run_hooks("misc_syndication_start");

    $fid = $mybb->get_input('fid', MyBB::INPUT_INT);
    $version = $mybb->get_input('version');
    $new_limit = $mybb->get_input('limit', MyBB::INPUT_INT);
    $forums = $mybb->get_input('forums', MyBB::INPUT_ARRAY);
    $limit = 15;

    if (!empty($new_limit) && $new_limit != $limit) {
        $limit = $new_limit;
    }

    $add = false;

    add_breadcrumb($lang->nav_syndication);
    $unviewable = get_unviewable_forums();
    $inactiveforums = get_inactive_forums();
    $unexp1 = explode(',', $unviewable);
    $unexp2 = explode(',', $inactiveforums);
    $unexp = array_merge($unexp1, $unexp2);

    $syndication['url'] = '';
    $syndication['feedurl'] = $syndication['allselected'] = false;
    if (!empty($forums)) {
        foreach ($unexp as $fid) {
            $unview[$fid] = true;
        }

        $syndicate = '';
        $comma = '';
        $all = false;
        foreach ($forums as $fid) {
            if ($fid == "all") {
                $syndication['allselected'] = true;
                $all = true;
                break;
            }
            elseif (ctype_digit($fid)) {
                if (!isset($unview[$fid])) {
                    $syndicate .= $comma.$fid;
                    $comma = ",";
                    $flist[$fid] = true;
                }
            }
        }

        $syndication['url'] = $mybb->settings['bburl']."/syndication.php";
        if (!$all) {
            $syndication['url'] .= "?fid=$syndicate";
            $add = true;
        }

        // If the version is not RSS2.0, set the type to Atom1.0.
        if ($version != "rss2.0") {
            if (!$add) {
                $syndication['url'] .= "?";
            } else {
                $syndication['url'] .= "&";
            }

            $syndication['url'] .= "type=atom1.0";
            $add = true;
        }

        if ((int)$limit > 0) {
            if ($limit > 50) {
                $limit = 50;
            }

            if (!$add) {
                $syndication['url'] .= "?";
            } else {
                $syndication['url'] .= "&";
            }

            if (is_numeric($limit)) {
                $syndication['url'] .= "limit=$limit";
            }
        }

        $syndication['feedurl'] = true;
    }

    unset($GLOBALS['forumcache']);

    // If there is no version in the input, check the default (RSS2.0).
    if ($version == "atom1.0") {
        $syndication['atom1check'] = true;
        $syndication['rss2check'] = false;
    } else {
        $syndication['atom1check'] = false;
        $syndication['rss2check'] = true;
    }

    $forums = makesyndicateforums();
    $syndication['limit'] = $limit;

    if ($syndication['feedurl'] == false) {
        $syndication['allselected'] = true;
    }

    $plugins->run_hooks("misc_syndication_end");

    output_page(\MyBB\template('misc/syndication.twig', [
        'syndication' => $syndication,
        'forums' => $forums,
    ]));
}
elseif($mybb->input['action'] == "clearcookies")
{
	verify_post_check($mybb->get_input('my_post_key'));

	$plugins->run_hooks("misc_clearcookies");

	$remove_cookies = array('mybbuser', 'mybb[announcements]', 'mybb[lastvisit]', 'mybb[lastactive]', 'collapsed', 'mybb[forumread]', 'mybb[threadsread]', 'mybbadmin',
							'mybblang', 'mybbtheme', 'multiquote', 'mybb[readallforums]', 'coppauser', 'coppadob', 'mybb[referrer]');

	foreach($remove_cookies as $name)
	{
		my_unsetcookie($name);
	}
	redirect("index.php", $lang->redirect_cookiescleared);
}

/**
 * Build a list of forums for RSS multiselect.
 *
 * @param int $pid Parent forum ID.
 * @param string $selitem deprecated
 * @param boolean $addselect Whether to add selected attribute or not.
 * @param string $depth HTML for the depth of the forum.
 * @return string HTML of the list of forums for CSS.
 */
function makesyndicateforums($pid=0, $selitem="", $addselect=true, $depth="")
{
    global $db, $forumcache, $permissioncache, $mybb, $flist, $unexp;

    $pid = (int)$pid;

    $forumlist = [];
    $forumtree = [];
    if (!is_array($forumcache)) {
        // Get Forums
        $query = $db->simple_select("forums", "*", "linkto = '' AND active!=0", array('order_by' => 'pid, disporder'));
        while ($forum = $db->fetch_array($query)) {
            $forumcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
        }
    }

    if (!is_array($permissioncache)) {
        $permissioncache = forum_permissions();
    }

    if (is_array($forumcache[$pid])) {
        foreach ($forumcache[$pid] as $key => $main) {
            foreach ($main as $key => $forum) {
                $perms = $permissioncache[$forum['fid']];
                if ($perms['canview'] == 1 || $mybb->settings['hideprivateforums'] == 0) {
                    $forum['selected'] = false;
                    if (isset($flist[$forum['fid']])) {
                        $forum['selected'] = true;
                        $selecteddone = "1";
                    }

                    if ($forum['password'] == '' && !in_array($forum['fid'], $unexp) || $forum['password'] && isset($mybb->cookies['forumpass'][$forum['fid']]) && $mybb->cookies['forumpass'][$forum['fid']] === md5($mybb->user['uid'].$forum['password'])) {
                        $forum['depth'] = $depth;
                        $forumlist[] = $forum;
                    }

                    if (!empty($forumcache[$forum['fid']])) {
                        $newdepth = $depth."&nbsp;&nbsp;&nbsp;&nbsp;";
                        $forumtree = makesyndicateforums($forum['fid'], '', 0, $newdepth);
                        $forumlist = array_merge($forumlist, $forumtree);
                    }
                }
            }
        }
    }

    return $forumlist;
}
