<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
 */

/**
 * Build a select box list of forums the current user has permission to search
 *
 * @param int $pid The parent forum ID to start at
 * @param int $selitem The selected forum ID
 * @param int $addselect Add select boxes at this call or not
 * @param string $depth The current depth
 * @return string The forum select boxes
 */
function make_searchable_forums($pid=0, $selitem=0, $addselect=1, $depth='')
{
	global $db, $pforumcache, $permissioncache, $mybb, $selecteddone, $forumlist, $forumlistbits, $theme, $templates, $lang, $forumpass;
	$pid = (int)$pid;

	if(!is_array($pforumcache))
	{
		// Get Forums
		$query = $db->simple_select("forums", "pid,disporder,fid,password,name", "linkto='' AND active!=0", array('order_by' => "pid, disporder"));
		while($forum = $db->fetch_array($query))
		{
			$pforumcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}
	}
	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}
	if(is_array($pforumcache[$pid]))
	{
		foreach($pforumcache[$pid] as $key => $main)
		{
			foreach($main as $key => $forum)
			{
				$perms = $permissioncache[$forum['fid']];
				if(($perms['canview'] == 1 || $mybb->settings['hideprivateforums'] == 0) && $perms['cansearch'] != 0)
				{
					if($selitem == $forum['fid'])
					{
						$optionselected = "selected";
						$selecteddone = "1";
					}
					else
					{
						$optionselected = '';
						$selecteddone = "0";
					}
					if(forum_password_validated($forum, true))
					{
						eval("\$forumlistbits .= \"".$templates->get("search_forumlist_forum")."\";");
					}
					if(!empty($pforumcache[$forum['fid']]))
					{
						$newdepth = $depth."&nbsp;&nbsp;&nbsp;&nbsp;";
						$forumlistbits .= make_searchable_forums($forum['fid'], $selitem, 0, $newdepth);
					}
				}
			}
		}
	}
	if($addselect)
	{
		eval("\$forumlist = \"".$templates->get("search_forumlist")."\";");
	}
	return $forumlist;
}

/**
 * Build a comma separated list of the forums this user cannot search
 *
 * @param int $pid The parent ID to build from
 * @param int $first First rotation or not (leave at default)
 * @return string return a CSV list of forums the user cannot search
 */
function get_unsearchable_forums($pid=0, $first=1)
{
	global $forum_cache, $permissioncache, $mybb, $unsearchableforums, $unsearchable, $templates, $forumpass;

	$pid = (int)$pid;

	if(!is_array($forum_cache))
	{
		cache_forums();
	}
	if(!is_array($permissioncache))
	{
		$permissioncache = forum_permissions();
	}
	foreach($forum_cache as $fid => $forum)
	{
		if($permissioncache[$forum['fid']])
		{
			$perms = $permissioncache[$forum['fid']];
		}
		else
		{
			$perms = $mybb->usergroup;
		}

		$parents = explode(",", $forum['parentlist']);
		if(is_array($parents))
		{
			foreach($parents as $parent)
			{
				if($forum_cache[$parent]['active'] == 0)
				{
					$forum['active'] = 0;
				}
			}
		}

		if($perms['canview'] != 1 || $perms['cansearch'] != 1 || !forum_password_validated($forum, true) || $forum['active'] == 0)
		{
			if($unsearchableforums)
			{
				$unsearchableforums .= ",";
			}
			$unsearchableforums .= "'{$forum['fid']}'";
		}
	}
	$unsearchable = $unsearchableforums;

	// Get our unsearchable password protected forums
	$pass_protected_forums = get_password_protected_forums();

	if($unsearchable && $pass_protected_forums)
	{
		$unsearchable .= ",";
	}

	if($pass_protected_forums)
	{
		$unsearchable .= implode(",", $pass_protected_forums);
	}

	return $unsearchable;
}

/**
 * Build query condition for threads/posts the user is allowed to see.
 * Will return for example:
 *  - visible = 1 - for normal users
 *  - visible >= -1 - for admins & super mods
 *  - (visible = 1 OR (visible = ? AND fid IN ...)) - for forum moderators
 * 
 * @param string $table_alias The alias of the table eg t to use t.visible instead of visible
 * @return string the query condition 
 */
function get_visible_where($table_alias = null)
{
	global $db, $mybb;

	$aliasdot = '';
	if(!empty($table_alias))
	{
		$aliasdot = $table_alias.'.';
	}

	if($mybb->usergroup['issupermod'] == 1)
	{
		// Super moderators (and admins)
		return "{$aliasdot}visible >= -1";
	}
	elseif(is_moderator())
	{
		// Normal moderators
		$unapprove_forums = array();
		$deleted_forums = array();
		$unapproved_where = "({$aliasdot}visible = 1";
		
		$moderated_fids = get_moderated_fids($mybb->user['uid']);

		if($moderated_fids !== false)
		{
			foreach($moderated_fids as $fid)
			{
				if(!is_moderator($fid))
				{
					// Shouldn't occur.
					continue;
				}
	
				// Use moderates this forum
				$modperms = get_moderator_permissions($fid, $mybb->user['uid']);
	
				if($modperms['canviewunapprove'] == 1)
				{
					$unapprove_forums[] = $fid;
				}
	
				if($modperms['canviewdeleted'] == 1)
				{
					$deleted_forums[] = $fid;
				}
			}
	
			if(!empty($unapprove_forums))
			{
				$unapproved_where .= " OR ({$aliasdot}visible = 0 AND {$aliasdot}fid IN(".implode(',', $unapprove_forums)."))";
			}
			if(!empty($deleted_forums))
			{
				$unapproved_where .= " OR ({$aliasdot}visible = -1 AND {$aliasdot}fid IN(".implode(',', $deleted_forums)."))";
			}
			$unapproved_where .= ')';
	
			return $unapproved_where;
		}
	}

	// Normal users
	if($mybb->user['uid'] > 0 && $mybb->settings['showownunapproved'] == 1)
	{
		return "({$aliasdot}visible = 1 OR ({$aliasdot}visible = 0 AND {$aliasdot}uid = {$mybb->user['uid']}))";
	}
	return "{$aliasdot}visible = 1";
}

/**
 * Build a array list of the forums this user cannot search due to password protection
 *
 * @param array $fids the fids to check (leave blank to check all forums)
 * @return array return a array list of password protected forums the user cannot search
 */
function get_password_protected_forums($fids=array())
{
	global $forum_cache, $mybb;

	if(!is_array($fids))
	{
		return false;
	}

	if(!is_array($forum_cache))
	{
		$forum_cache = cache_forums();
		if(!$forum_cache)
		{
			return false;
		}
	}

	if(empty($fids))
	{
		$fids = array_keys($forum_cache);
	}

	$pass_fids = array();
	foreach($fids as $fid)
	{
		if(!forum_password_validated($forum_cache[$fid], true))
		{
			$pass_fids[] = $fid;
			$pass_fids = array_merge($pass_fids, get_child_list($fid));
		}
	}
	return array_unique($pass_fids);
}

/**
 * Clean search keywords and make them safe for querying
 *
 * @param string $keywords The keywords to be cleaned
 * @return string The cleaned keywords
 */
function clean_keywords($keywords)
{
	global $db, $lang;

	$keywords = my_strtolower($keywords);
	$keywords = $db->escape_string_like($keywords);
	$keywords = preg_replace("#\*{2,}#s", "*", $keywords);
	$keywords = str_replace("*", "%", $keywords);
	$keywords = preg_replace("#\s+#s", " ", $keywords);
	$keywords = str_replace('\\"', '"', $keywords);

	// Search for "and" or "or" and remove if it's at the beginning
	$keywords = trim($keywords);
	if(my_strpos($keywords, "or") === 0)
	{
		$keywords = substr_replace($keywords, "", 0, 2);
		$keywords = " ".$keywords;
	}

	if(my_strpos($keywords, "and") === 0)
	{
		$keywords = substr_replace($keywords, "", 0, 3);
		$keywords = " ".$keywords;
	}

	if(!$keywords)
	{
		error($lang->error_nosearchterms);
	}

	return $keywords;
}

/**
 * Clean search keywords for fulltext searching, making them safe for querying
 *
 * @param string $keywords The keywords to be cleaned
 * @return string|bool The cleaned keywords or false on failure
 */
function clean_keywords_ft($keywords)
{
	if(!$keywords)
	{
		return false;
	}
	$keywords = my_strtolower($keywords);
	$keywords = str_replace("%", "\\%", $keywords);
	$keywords = preg_replace("#\*{2,}#s", "*", $keywords);
	$keywords = preg_replace("#([\[\]\|\.\,:])#s", " ", $keywords);
	// Separate braces for further processing
	$keywords = preg_replace("#((\+|-|<|>|~)?\(|\))#s", " $1 ", $keywords);
	$keywords = preg_replace("#\s+#s", " ", $keywords);

	global $mybb;

	$min_word_length = (int) $mybb->settings['minsearchword'];
	if($min_word_length <= 0)
	{
		$min_word_length = 3;
	}
	$min_word_length -= 1;

	$word_length_regex = '';
	if($min_word_length > 1)
	{
		$word_length_regex = "{1,{$min_word_length}}";
	}

	// Replaces less than 3 characters
	$keywords = preg_replace("/(\b.{$word_length_regex})(\s)|(\b.{$word_length_regex}$)/u", '$2', $keywords);
	// Collapse multiple spaces
	$keywords = preg_replace('/(\s)+/', '$1', $keywords);
	$keywords = trim($keywords);

	$words = array(array());

	// Fulltext search syntax validation: http://dev.mysql.com/doc/refman/5.6/en/fulltext-boolean.html
	// Search for phrases
	$keywords = explode("\"", $keywords);
	$boolean = array('+');
	// Brace depth
	$depth = 0;
	$phrase_operator = '+';
	foreach($keywords as $phrase)
	{
		$phrase = trim($phrase);
		if($phrase != '')
		{
			if($inquote)
			{
				if($phrase_operator)
				{
					$boolean[$depth] = $phrase_operator;
				}
				// Phrases do not need further processing
				$words[$depth][] = "{$boolean[$depth]}\"{$phrase}\"";
				$boolean[$depth] = $phrase_operator = '+';
			}
			else
			{
				// Split words
				$split_words = preg_split("#\s{1,}#", $phrase, -1);
				if(!is_array($split_words))
				{
					continue;
				}
				if(!$inquote)
				{
					// Save possible operator in front of phrase
					$last_char = substr($phrase, -1);
					if($last_char == '+' || $last_char == '-' || $last_char == '<' || $last_char == '>' || $last_char == '~')
					{
						$phrase_operator = $last_char;
					}
				}
				foreach($split_words as $word)
				{
					$word = trim($word);
					if($word == "or")
					{
						$boolean[$depth] = '';
						// Remove "and" operator from previous element
						$last = array_pop($words[$depth]);
						if($last)
						{
							if(substr($last, 0, 1) == '+')
							{
								$last = substr($last, 1);
							}
							$words[$depth][] = $last;
						}
					}
					elseif($word == "and")
					{
						$boolean[$depth] = "+";
					}
					elseif($word == "not")
					{
						$boolean[$depth] = "-";
					}
					// Closing braces
					elseif($word == ")")
					{
						// Ignore when no brace was opened
						if($depth > 0)
						{
							$words[$depth-1][] = $boolean[$depth-1].'('.implode(' ', $words[$depth]).')';
							--$depth;
						}
					}
					// Valid operators for opening braces
					elseif($word == '+(' || $word == '-(' || $word == '<(' || $word == '>(' || $word == '~(' || $word == '(')
					{
						if(strlen($word) == 2)
						{
							$boolean[$depth] = substr($word, 0, 1);
						}
						$words[++$depth] = array();
						$boolean[$depth] = '+';
					}
					else
					{
						$operator = substr($word, 0, 1);
						switch($operator)
						{
							// Allowed operators
							case '-':
							case '+':
							case '>':
							case '<':
							case '~':
								$word = substr($word, 1);
								break;
							default:
								$operator = $boolean[$depth];
								break;
						}
						// Removed operators that are only allowed at the beginning
						$word = preg_replace("#(-|\+|<|>|~|@)#s", '', $word);
						// Removing wildcards at the beginning http://bugs.mysql.com/bug.php?id=72605
						$word = preg_replace("#^\*#s", '', $word);
						$word = $operator.$word;
						if(strlen($word) <= 1)
						{
							continue;
						}
						$words[$depth][] = $word;
						$boolean[$depth] = '+';
					}
				}
			}
		}
		$inquote = !$inquote;
	}

	// Close mismatching braces
	while($depth > 0)
	{
		$words[$depth-1][] = $boolean[$depth-1].'('.implode(' ', $words[$depth]).')';
		--$depth;
	}

	$keywords = implode(' ', $words[0]);
	return $keywords;
}

/* Database engine specific search functions */

/**
 * Perform a thread and post search under MySQL or MySQLi
 *
 * @param array $search Array of search data
 * @return array Array of search data with results mixed in
 */
function privatemessage_perform_search_mysql($search)
{
	global $mybb, $db, $lang;

	$keywords = clean_keywords($search['keywords']);
	if(!$keywords && !$search['sender'])
	{
		error($lang->error_nosearchterms);
	}

	if($mybb->settings['minsearchword'] < 1)
	{
		$mybb->settings['minsearchword'] = 3;
	}

	$subject_lookin = "";
	$message_lookin = "";
	$searchsql = "uid='{$mybb->user['uid']}'";

	if($keywords)
	{
		// Complex search
		$keywords = " {$keywords} ";

		switch($db->type)
		{
			case 'mysql':
			case 'mysqli':
				$sfield = 'subject';
				$mfield = 'message';
				break;
			default:
				$sfield = 'LOWER(subject)';
				$mfield = 'LOWER(message)';
				break;
		}

		if(preg_match("#\s(and|or)\s#", $keywords))
		{
			$string = "AND";
			if($search['subject'] == 1)
			{
				$string = "OR";
				$subject_lookin = " AND (";
			}

			if($search['message'] == 1)
			{
				$message_lookin = " {$string} (";
			}

			// Expand the string by double quotes
			$keywords_exp = explode("\"", $keywords);
			$inquote = false;
			$boolean = '';

			foreach($keywords_exp as $phrase)
			{
				// If we're not in a double quoted section
				if(!$inquote)
				{
					// Expand out based on search operators (and, or)
					$matches = preg_split("#\s{1,}(and|or)\s{1,}#", $phrase, -1, PREG_SPLIT_DELIM_CAPTURE);
					$count_matches = count($matches);

					for($i=0; $i < $count_matches; ++$i)
					{
						$word = trim($matches[$i]);
						if(empty($word))
						{
							continue;
						}
						// If this word is a search operator set the boolean
						if($i % 2 && ($word == "and" || $word == "or"))
						{
							if($i <= 1)
							{
								if($search['subject'] && $search['message'] && $subject_lookin == " AND (")
								{
									// We're looking for anything, check for a subject lookin
									continue;
								}
								elseif($search['subject'] && !$search['message'] && $subject_lookin == " AND (")
								{
									// Just in a subject?
									continue;
								}
								elseif(!$search['subject'] && $search['message'] && $message_lookin == " {$string} (")
								{
									// Just in a message?
									continue;
								}
							}

							$boolean = $word;
						}
						// Otherwise check the length of the word as it is a normal search term
						else
						{
							$word = trim($word);
							// Word is too short - show error message
							if(my_strlen($word) < $mybb->settings['minsearchword'])
							{
								$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
								error($lang->error_minsearchlength);
							}

							// Add terms to search query
							if($search['subject'] == 1)
							{
								$subject_lookin .= " $boolean {$sfield} LIKE '%{$word}%'";
							}
							if($search['message'] == 1)
							{
								$message_lookin .= " $boolean {$mfield} LIKE '%{$word}%'";
							}
							$boolean = 'AND';
						}
					}
				}
				// In the middle of a quote (phrase)
				else
				{
					$phrase = str_replace(array("+", "-", "*"), '', trim($phrase));
					if(my_strlen($phrase) < $mybb->settings['minsearchword'])
					{
						$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
						error($lang->error_minsearchlength);
					}
					// Add phrase to search query
					$subject_lookin .= " $boolean {$sfield} LIKE '%{$phrase}%'";
					if($search['message'] == 1)
					{
						$message_lookin .= " $boolean {$mfield} LIKE '%{$phrase}%'";
					}
					$boolean = 'AND';
				}

				// Check to see if we have any search terms and not a malformed SQL string
				$error = false;
				if($search['subject'] && $search['message'] && $subject_lookin == " AND (")
				{
					// We're looking for anything, check for a subject lookin
					$error = true;
				}
				elseif($search['subject'] && !$search['message'] && $subject_lookin == " AND (")
				{
					// Just in a subject?
					$error = true;
				}
				elseif(!$search['subject'] && $search['message'] && $message_lookin == " {$string} (")
				{
					// Just in a message?
					$error = true;
				}

				if($error == true)
				{
					// There are no search keywords to look for
					$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
					error($lang->error_minsearchlength);
				}

				$inquote = !$inquote;
			}

			if($search['subject'] == 1)
			{
				$subject_lookin .= ")";
			}

			if($search['message'] == 1)
			{
				$message_lookin .= ")";
			}

			$searchsql .= "{$subject_lookin} {$message_lookin}";
		}
		else
		{
			$keywords = str_replace("\"", '', trim($keywords));
			if(my_strlen($keywords) < $mybb->settings['minsearchword'])
			{
				$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
				error($lang->error_minsearchlength);
			}

			// If we're looking in both, then find matches in either the subject or the message
			if($search['subject'] == 1 && $search['message'] == 1)
			{
				$searchsql .= " AND ({$sfield} LIKE '%{$keywords}%' OR {$mfield} LIKE '%{$keywords}%')";
			}
			else
			{
				if($search['subject'] == 1)
				{
					$searchsql .= " AND {$sfield} LIKE '%{$keywords}%'";
				}

				if($search['message'] == 1)
				{
					$searchsql .= " AND {$mfield} LIKE '%{$keywords}%'";
				}
			}
		}
	}

	if($search['sender'])
	{
		$userids = array();
		$search['sender'] = my_strtolower($search['sender']);

		switch($db->type)
		{
			case 'mysql':
			case 'mysqli':
				$field = 'username';
				break;
			default:
				$field = 'LOWER(username)';
				break;
		}
		$query = $db->simple_select("users", "uid", "{$field} LIKE '%".$db->escape_string_like($search['sender'])."%'");
		while($user = $db->fetch_array($query))
		{
			$userids[] = $user['uid'];
		}

		if(count($userids) < 1)
		{
			error($lang->error_nosearchresults);
		}
		else
		{
			$userids = implode(',', $userids);
			$searchsql .= " AND fromid IN (".$userids.")";
		}
	}

	if(!is_array($search['folder']))
	{
		$search['folder'] = array($search['folder']);
	}

	if(!empty($search['folder']))
	{
		$folderids = array();

		$search['folder'] = array_map("intval", $search['folder']);

		$folderids = implode(',', $search['folder']);

		if($folderids)
		{
			$searchsql .= " AND folder IN (".$folderids.")";
		}
	}

	if($search['status'])
	{
		$searchsql .= " AND (";
		if($search['status']['new'])
		{
			$statussql[] = " status='0' ";
		}
		if($search['status']['replied'])
		{
			$statussql[] = " status='3' ";
		}
		if($search['status']['forwarded'])
		{
			$statussql[] = " status='4' ";
		}
		if($search['status']['read'])
		{
			$statussql[] = " (status != '0' AND readtime > '0') ";
		}
		// Sent Folder
		if(in_array(2, $search['folder']))
		{
			$statussql[] = " status='1' ";
		}
		$statussql = implode("OR", $statussql);
		$searchsql .= $statussql.")";
	}

	$limitsql = "";
	if((int)$mybb->settings['searchhardlimit'] > 0)
	{
		$limitsql = " LIMIT ".(int)$mybb->settings['searchhardlimit'];
	}
	$searchsql .= $limitsql;

	// Run the search
	$pms = array();
	$query = $db->simple_select("privatemessages", "pmid", $searchsql);
	while($pm = $db->fetch_array($query))
	{
		$pms[$pm['pmid']] = $pm['pmid'];
	}

	if(count($pms) < 1)
	{
		error($lang->error_nosearchresults);
	}
	$pms = implode(',', $pms);

	return array(
		"querycache" => $pms
	);
}

/**
 * Perform a help document search under MySQL or MySQLi
 *
 * @param array $search Array of search data
 * @return array Array of search data with results mixed in
 */
function helpdocument_perform_search_mysql($search)
{
	global $mybb, $db, $lang;

	$keywords = clean_keywords($search['keywords']);
	if(!$keywords && !$search['sender'])
	{
		error($lang->error_nosearchterms);
	}

	if($mybb->settings['minsearchword'] < 1)
	{
		$mybb->settings['minsearchword'] = 3;
	}

	$name_lookin = "";
	$document_lookin = "";
	$searchsql = "enabled='1'";

	if($keywords)
	{
		switch($db->type)
		{
			case 'mysql':
			case 'mysqli':
				$nfield = 'name';
				$dfield = 'document';
				break;
			default:
				$nfield = 'LOWER(name)';
				$dfield = 'LOWER(document)';
				break;
		}

		// Complex search
		$keywords = " {$keywords} ";
		if(preg_match("#\s(and|or)\s#", $keywords))
		{
			$string = "AND";
			if($search['name'] == 1)
			{
				$string = "OR";
				$name_lookin = " AND (";
			}

			if($search['document'] == 1)
			{
				$document_lookin = " {$string} (";
			}

			// Expand the string by double quotes
			$keywords_exp = explode("\"", $keywords);
			$inquote = false;

			foreach($keywords_exp as $phrase)
			{
				// If we're not in a double quoted section
				if(!$inquote)
				{
					// Expand out based on search operators (and, or)
					$matches = preg_split("#\s{1,}(and|or)\s{1,}#", $phrase, -1, PREG_SPLIT_DELIM_CAPTURE);
					$count_matches = count($matches);

					for($i=0; $i < $count_matches; ++$i)
					{
						$word = trim($matches[$i]);
						if(empty($word))
						{
							continue;
						}
						// If this word is a search operator set the boolean
						if($i % 2 && ($word == "and" || $word == "or"))
						{
							if($i <= 1)
							{
								if($search['name'] && $search['document'] && $name_lookin == " AND (")
								{
									// We're looking for anything, check for a name lookin
									continue;
								}
								elseif($search['name'] && !$search['document'] && $name_lookin == " AND (")
								{
									// Just in a name?
									continue;
								}
								elseif(!$search['name'] && $search['document'] && $document_lookin == " {$string} (")
								{
									// Just in a document?
									continue;
								}
							}

							$boolean = $word;
						}
						// Otherwise check the length of the word as it is a normal search term
						else
						{
							$word = trim($word);
							// Word is too short - show error message
							if(my_strlen($word) < $mybb->settings['minsearchword'])
							{
								$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
								error($lang->error_minsearchlength);
							}
							// Add terms to search query
							if($search['name'] == 1)
							{
								$name_lookin .= " $boolean {$nfield} LIKE '%{$word}%'";
							}
							if($search['document'] == 1)
							{
								$document_lookin .= " $boolean {$dfield} LIKE '%{$word}%'";
							}
						}
					}
				}
				// In the middle of a quote (phrase)
				else
				{
					$phrase = str_replace(array("+", "-", "*"), '', trim($phrase));
					if(my_strlen($phrase) < $mybb->settings['minsearchword'])
					{
						$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
						error($lang->error_minsearchlength);
					}
					// Add phrase to search query
					$name_lookin .= " $boolean {$nfield} LIKE '%{$phrase}%'";
					if($search['document'] == 1)
					{
						$document_lookin .= " $boolean {$dfield} LIKE '%{$phrase}%'";
					}
				}

				// Check to see if we have any search terms and not a malformed SQL string
				$error = false;
				if($search['name'] && $search['document'] && $name_lookin == " AND (")
				{
					// We're looking for anything, check for a name lookin
					$error = true;
				}
				elseif($search['name'] && !$search['document'] && $name_lookin == " AND (")
				{
					// Just in a name?
					$error = true;
				}
				elseif(!$search['name'] && $search['document'] && $document_lookin == " {$string} (")
				{
					// Just in a document?
					$error = true;
				}

				if($error == true)
				{
					// There are no search keywords to look for
					$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
					error($lang->error_minsearchlength);
				}

				$inquote = !$inquote;
			}

			if($search['name'] == 1)
			{
				$name_lookin .= ")";
			}

			if($search['document'] == 1)
			{
				$document_lookin .= ")";
			}

			$searchsql .= "{$name_lookin} {$document_lookin}";
		}
		else
		{
			$keywords = str_replace("\"", '', trim($keywords));
			if(my_strlen($keywords) < $mybb->settings['minsearchword'])
			{
				$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
				error($lang->error_minsearchlength);
			}

			// If we're looking in both, then find matches in either the name or the document
			if($search['name'] == 1 && $search['document'] == 1)
			{
				$searchsql .= " AND ({$nfield} LIKE '%{$keywords}%' OR {$dfield} LIKE '%{$keywords}%')";
			}
			else
			{
				if($search['name'] == 1)
				{
					$searchsql .= " AND {$nfield} LIKE '%{$keywords}%'";
				}

				if($search['document'] == 1)
				{
					$searchsql .= " AND {$dfield} LIKE '%{$keywords}%'";
				}
			}
		}
	}

	// Run the search
	$helpdocs = array();
	$query = $db->simple_select("helpdocs", "hid", $searchsql);
	while($help = $db->fetch_array($query))
	{
		$helpdocs[$help['hid']] = $help['hid'];
	}

	if(count($helpdocs) < 1)
	{
		error($lang->error_nosearchresults);
	}
	$helpdocs = implode(',', $helpdocs);

	return array(
		"querycache" => $helpdocs
	);
}

/**
 * Perform a thread and post search under MySQL or MySQLi
 *
 * @param array $search Array of search data
 * @return array Array of search data with results mixed in
 */
function perform_search_mysql($search)
{
	global $mybb, $db, $lang, $cache;

	$keywords = clean_keywords($search['keywords']);

	if($mybb->settings['minsearchword'] < 1)
	{
		$mybb->settings['minsearchword'] = 3;
	}

	$subject_lookin = $message_lookin = '';
	if($keywords)
	{
		switch($db->type)
		{
			case 'mysql':
			case 'mysqli':
				$tfield = 't.subject';
				$pfield = 'p.message';
				break;
			default:
				$tfield = 'LOWER(t.subject)';
				$pfield = 'LOWER(p.message)';
				break;
		}

		// Complex search
		$keywords = " {$keywords} ";
		if(preg_match("#\s(and|or)\s#", $keywords))
		{
			$subject_lookin = " AND (";
			$message_lookin = " AND (";

			// Expand the string by double quotes
			$keywords_exp = explode("\"", $keywords);
			$inquote = false;
			$boolean = '';

			foreach($keywords_exp as $phrase)
			{
				// If we're not in a double quoted section
				if(!$inquote)
				{
					// Expand out based on search operators (and, or)
					$matches = preg_split("#\s{1,}(and|or)\s{1,}#", $phrase, -1, PREG_SPLIT_DELIM_CAPTURE);
					$count_matches = count($matches);

					for($i=0; $i < $count_matches; ++$i)
					{
						$word = trim($matches[$i]);
						if(empty($word))
						{
							continue;
						}
						// If this word is a search operator set the boolean
						if($i % 2 && ($word == "and" || $word == "or"))
						{
							if($i <= 1 && $subject_lookin == " AND (")
							{
								continue;
							}

							$boolean = $word;
						}
						// Otherwise check the length of the word as it is a normal search term
						else
						{
							$word = trim($word);
							// Word is too short - show error message
							if(my_strlen($word) < $mybb->settings['minsearchword'])
							{
								$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
								error($lang->error_minsearchlength);
							}
							// Add terms to search query
							$subject_lookin .= " $boolean {$tfield} LIKE '%{$word}%'";
							if($search['postthread'] == 1)
							{
								$message_lookin .= " $boolean {$pfield} LIKE '%{$word}%'";
							}
							$boolean = 'AND';
						}
					}
				}
				// In the middle of a quote (phrase)
				else
				{
					$phrase = str_replace(array("+", "-", "*"), '', trim($phrase));
					if(my_strlen($phrase) < $mybb->settings['minsearchword'])
					{
						$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
						error($lang->error_minsearchlength);
					}
					// Add phrase to search query
					$subject_lookin .= " $boolean {$tfield} LIKE '%{$phrase}%'";
					if($search['postthread'] == 1)
					{
						$message_lookin .= " $boolean {$pfield} LIKE '%{$phrase}%'";
					}
					$boolean = 'AND';
				}

				if($subject_lookin == " AND (")
				{
					// There are no search keywords to look for
					$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
					error($lang->error_minsearchlength);
				}

				$inquote = !$inquote;
			}
			$subject_lookin .= ")";
			$message_lookin .= ")";
		}
		else
		{
			$keywords = str_replace("\"", '', trim($keywords));
			if(my_strlen($keywords) < $mybb->settings['minsearchword'])
			{
				$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
				error($lang->error_minsearchlength);
			}
			$subject_lookin = " AND {$tfield} LIKE '%{$keywords}%'";
			if($search['postthread'] == 1)
			{
				$message_lookin = " AND {$pfield} LIKE '%{$keywords}%'";
			}
		}
	}
	$post_usersql = '';
	$thread_usersql = '';
	if($search['author'])
	{
		$userids = array();
		$search['author'] = my_strtolower($search['author']);
		if($search['matchusername'])
		{
			$user = get_user_by_username($search['author']);
			if($user)
			{
				$userids[] = $user['uid'];
			}
		}
		else
		{
			switch($db->type)
			{
				case 'mysql':
				case 'mysqli':
					$field = 'username';
					break;
				default:
					$field = 'LOWER(username)';
					break;
			}
			$query = $db->simple_select("users", "uid", "{$field} LIKE '%".$db->escape_string_like($search['author'])."%'");
			while($user = $db->fetch_array($query))
			{
				$userids[] = $user['uid'];
			}
		}

		if(count($userids) < 1)
		{
			error($lang->error_nosearchresults);
		}
		else
		{
			$userids = implode(',', $userids);
			$post_usersql = " AND p.uid IN (".$userids.")";
			$thread_usersql = " AND t.uid IN (".$userids.")";
		}
	}
	$datecut = $post_datecut = $thread_datecut = '';
	if($search['postdate'])
	{
		if($search['pddir'] == 0)
		{
			$datecut = "<=";
		}
		else
		{
			$datecut = ">=";
		}
		$now = TIME_NOW;
		$datelimit = $now-(86400 * $search['postdate']);
		$datecut .= "'$datelimit'";
		$post_datecut = " AND p.dateline $datecut";
		$thread_datecut = " AND t.dateline $datecut";
	}

	$thread_replycut = '';
	if($search['numreplies'] != '' && $search['findthreadst'])
	{
		if((int)$search['findthreadst'] == 1)
		{
			$thread_replycut = " AND t.replies >= '".(int)$search['numreplies']."'";
		}
		else
		{
			$thread_replycut = " AND t.replies <= '".(int)$search['numreplies']."'";
		}
	}

	$thread_prefixcut = '';
	$prefixlist = array();
	if($search['threadprefix'] && $search['threadprefix'][0] != 'any')
	{
		foreach($search['threadprefix'] as $threadprefix)
		{
			$threadprefix = (int)$threadprefix;
			$prefixlist[] = $threadprefix;
		}
	}
	if(count($prefixlist) == 1)
	{
		$thread_prefixcut .= " AND t.prefix='$threadprefix' ";
	}
	else
	{
		if(count($prefixlist) > 1)
		{
			$thread_prefixcut = " AND t.prefix IN (".implode(',', $prefixlist).")";
		}
	}

	$forumin = '';
	$fidlist = array();
	if(!empty($search['forums']) && (!is_array($search['forums']) || $search['forums'][0] != "all"))
	{
		if(!is_array($search['forums']))
		{
			$search['forums'] = array((int)$search['forums']);
		}
		foreach($search['forums'] as $forum)
		{
			$forum = (int)$forum;
			if($forum > 0)
			{
				$fidlist[] = $forum;
				$child_list = get_child_list($forum);
				if(is_array($child_list))
				{
					$fidlist = array_merge($fidlist, $child_list);
				}
			}
		}
		$fidlist = array_unique($fidlist);
		if(count($fidlist) >= 1)
		{
			$forumin = " AND t.fid IN (".implode(',', $fidlist).")";
		}
	}

	$permsql = "";
	$onlyusfids = array();

	// Check group permissions if we can't view threads not started by us
	if($group_permissions = forum_permissions())
	{
		foreach($group_permissions as $fid => $forum_permissions)
		{
			if(isset($forum_permissions['canonlyviewownthreads']) && $forum_permissions['canonlyviewownthreads'] == 1)
			{
				$onlyusfids[] = $fid;
			}
		}
	}
	if(!empty($onlyusfids))
	{
		$permsql .= "AND ((t.fid IN(".implode(',', $onlyusfids).") AND t.uid='{$mybb->user['uid']}') OR t.fid NOT IN(".implode(',', $onlyusfids)."))";
	}

	$unsearchforums = get_unsearchable_forums();
	if($unsearchforums)
	{
		$permsql .= " AND t.fid NOT IN ($unsearchforums)";
	}
	$inactiveforums = get_inactive_forums();
	if($inactiveforums)
	{
		$permsql .= " AND t.fid NOT IN ($inactiveforums)";
	}

	$visiblesql = $post_visiblesql = $plain_post_visiblesql = $unapproved_where_t = $unapproved_where_p = "";
	if(isset($search['visible']))
	{
		if($search['visible'] == 1)
		{
			$visiblesql = " AND t.visible = '1'";

			if($search['postthread'] == 1)
			{
				$post_visiblesql = " AND p.visible = '1'";
				$plain_post_visiblesql = " AND visible = '1'";
			}
		}
		elseif($search['visible'] == -1)
		{
			$visiblesql = " AND t.visible = '-1'";

			if($search['postthread'] == 1)
			{
				$post_visiblesql = " AND p.visible = '-1'";
				$plain_post_visiblesql = " AND visible = '-1'";
			}
		}
		else
		{
			$visiblesql = " AND t.visible == '0'";

			if($search['postthread'] == 1)
			{
				$post_visiblesql = " AND p.visible == '0'";
				$plain_post_visiblesql = " AND visible == '0'";
			}
		}
	}

	// Moderators can view unapproved threads and deleted threads from forums they moderate
	$unapproved_where_t = get_visible_where('t');
	$unapproved_where_p = get_visible_where('p');

	// Searching a specific thread?
	$tidsql = '';
	if(!empty($search['tid']))
	{
		$tidsql = " AND t.tid='".(int)$search['tid']."'";
	}

	$limitsql = '';
	if((int)$mybb->settings['searchhardlimit'] > 0)
	{
		$limitsql = "LIMIT ".(int)$mybb->settings['searchhardlimit'];
	}

	// Searching both posts and thread titles
	$threads = array();
	$posts = array();
	$firstposts = array();
	if($search['postthread'] == 1)
	{
		// No need to search subjects when looking for results within a specific thread
		if(empty($search['tid']))
		{
			$query = $db->query("
				SELECT t.tid, t.firstpost
				FROM ".TABLE_PREFIX."threads t
				WHERE 1=1 {$thread_datecut} {$thread_replycut} {$thread_prefixcut} {$forumin} {$thread_usersql} {$permsql} {$visiblesql} AND ({$unapproved_where_t}) AND t.closed NOT LIKE 'moved|%' {$subject_lookin}
				{$limitsql}
			");
			while($thread = $db->fetch_array($query))
			{
				$threads[$thread['tid']] = $thread['tid'];
				if($thread['firstpost'])
				{
					$posts[$thread['tid']] = $thread['firstpost'];
				}
			}
		}

		$query = $db->query("
			SELECT p.pid, p.tid
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE 1=1 {$post_datecut} {$thread_replycut} {$thread_prefixcut} {$forumin} {$post_usersql} {$permsql} {$tidsql} {$visiblesql} {$post_visiblesql} AND ({$unapproved_where_t}) AND ({$unapproved_where_p}) AND t.closed NOT LIKE 'moved|%' {$message_lookin}
			{$limitsql}
		");
		while($post = $db->fetch_array($query))
		{
			$posts[$post['pid']] = $post['pid'];
			$threads[$post['tid']] = $post['tid'];
		}

		if(count($posts) < 1 && count($threads) < 1)
		{
			error($lang->error_nosearchresults);
		}
		$threads = implode(',', $threads);
		$posts = implode(',', $posts);

	}
	// Searching only thread titles
	else
	{
		$query = $db->query("
			SELECT t.tid, t.firstpost
			FROM ".TABLE_PREFIX."threads t
			WHERE 1=1 {$thread_datecut} {$thread_replycut} {$thread_prefixcut} {$forumin} {$thread_usersql} {$permsql} {$visiblesql} {$subject_lookin}
			{$limitsql}
		");
		while($thread = $db->fetch_array($query))
		{
			$threads[$thread['tid']] = $thread['tid'];
			if($thread['firstpost'])
			{
				$firstposts[$thread['tid']] = $thread['firstpost'];
			}
		}
		if(count($threads) < 1)
		{
			error($lang->error_nosearchresults);
		}

		$threads = implode(',', $threads);
		$firstposts = implode(',', $firstposts);
		if($firstposts)
		{
			$query = $db->simple_select("posts", "pid", "pid IN ($firstposts) {$plain_post_visiblesql} {$limitsql}");
			while($post = $db->fetch_array($query))
			{
				$posts[$post['pid']] = $post['pid'];
			}
			$posts = implode(',', $posts);
		}
	}
	return array(
		"threads" => $threads,
		"posts" => $posts,
		"querycache" => ''
	);
}

/**
 * Perform a thread and post search under MySQL or MySQLi using boolean fulltext capabilities
 *
 * @param array $search Array of search data
 * @return array Array of search data with results mixed in
 */
function perform_search_mysql_ft($search)
{
	global $mybb, $db, $lang;

	$keywords = clean_keywords_ft($search['keywords']);

	if($mybb->settings['minsearchword'] < 1)
	{
		$mybb->settings['minsearchword'] = 4;
	}

	$message_lookin = $subject_lookin = '';
	if($keywords)
	{
		$keywords_exp = explode("\"", $keywords);
		$inquote = false;
		foreach($keywords_exp as $phrase)
		{
			if(!$inquote)
			{
				$split_words = preg_split("#\s{1,}#", $phrase, -1);
				foreach($split_words as $word)
				{
					$word = str_replace(array("+", "-", "*"), '', $word);
					if(!$word)
					{
						continue;
					}
					if(my_strlen($word) < $mybb->settings['minsearchword'])
					{
						$all_too_short = true;
					}
					else
					{
						$all_too_short = false;
						break;
					}
				}
			}
			else
			{
				$phrase = str_replace(array("+", "-", "*"), '', $phrase);
				if(my_strlen($phrase) < $mybb->settings['minsearchword'])
				{
					$all_too_short = true;
				}
				else
				{
					$all_too_short = false;
					break;
				}
			}
			$inquote = !$inquote;
		}
		// Show the minimum search term error only if all search terms are too short
		if($all_too_short == true)
		{
			$lang->error_minsearchlength = $lang->sprintf($lang->error_minsearchlength, $mybb->settings['minsearchword']);
			error($lang->error_minsearchlength);
		}
		$message_lookin = "AND MATCH(message) AGAINST('".$db->escape_string($keywords)."' IN BOOLEAN MODE)";
		$subject_lookin = "AND MATCH(subject) AGAINST('".$db->escape_string($keywords)."' IN BOOLEAN MODE)";
	}
	$post_usersql = '';
	$thread_usersql = '';
	if($search['author'])
	{
		$userids = array();
		$search['author'] = my_strtolower($search['author']);
		if($search['matchusername'])
		{
			$user = get_user_by_username($search['author']);
			if($user)
			{
				$userids[] = $user['uid'];
			}
		}
		else
		{
			$query = $db->simple_select("users", "uid", "username LIKE '%".$db->escape_string_like($search['author'])."%'");

			while($user = $db->fetch_array($query))
			{
				$userids[] = $user['uid'];
			}
		}

		if(count($userids) < 1)
		{
			error($lang->error_nosearchresults);
		}
		else
		{
			$userids = implode(',', $userids);
			$post_usersql = " AND p.uid IN (".$userids.")";
			$thread_usersql = " AND t.uid IN (".$userids.")";
		}
	}
	$datecut = $thread_datecut = $post_datecut = '';
	if($search['postdate'])
	{
		if($search['pddir'] == 0)
		{
			$datecut = "<=";
		}
		else
		{
			$datecut = ">=";
		}
		$now = TIME_NOW;
		$datelimit = $now-(86400 * $search['postdate']);
		$datecut .= "'$datelimit'";
		$post_datecut = " AND p.dateline $datecut";
		$thread_datecut = " AND t.dateline $datecut";
	}

	$thread_replycut = '';
	if($search['numreplies'] != '' && $search['findthreadst'])
	{
		if((int)$search['findthreadst'] == 1)
		{
			$thread_replycut = " AND t.replies >= '".(int)$search['numreplies']."'";
		}
		else
		{
			$thread_replycut = " AND t.replies <= '".(int)$search['numreplies']."'";
		}
	}

	$thread_prefixcut = '';
	$prefixlist = array();
	if($search['threadprefix'] && $search['threadprefix'][0] != 'any')
	{
		foreach($search['threadprefix'] as $threadprefix)
		{
			$threadprefix = (int)$threadprefix;
			$prefixlist[] = $threadprefix;
		}
	}
	if(count($prefixlist) == 1)
	{
		$thread_prefixcut .= " AND t.prefix='$threadprefix' ";
	}
	else
	{
		if(count($prefixlist) > 1)
		{
			$thread_prefixcut = " AND t.prefix IN (".implode(',', $prefixlist).")";
		}
	}

	$forumin = '';
	$fidlist = array();
	$searchin = array();
	if(!empty($search['forums']) && (!is_array($search['forums']) || $search['forums'][0] != "all"))
	{
		if(!is_array($search['forums']))
		{
			$search['forums'] = array((int)$search['forums']);
		}
		foreach($search['forums'] as $forum)
		{
			$forum = (int)$forum;
			if($forum > 0)
			{
				$fidlist[] = $forum;
				$child_list = get_child_list($forum);
				if(is_array($child_list))
				{
					$fidlist = array_merge($fidlist, $child_list);
				}
			}
		}
		$fidlist = array_unique($fidlist);
		if(count($fidlist) >= 1)
		{
			$forumin = " AND t.fid IN (".implode(',', $fidlist).")";
		}
	}
	$permsql = "";
	$onlyusfids = array();

	// Check group permissions if we can't view threads not started by us
	$group_permissions = forum_permissions();
	foreach($group_permissions as $fid => $forum_permissions)
	{
		if(isset($forum_permissions['canonlyviewownthreads']) && $forum_permissions['canonlyviewownthreads'] == 1)
		{
			$onlyusfids[] = $fid;
		}
	}
	if(!empty($onlyusfids))
	{
		$permsql .= "AND ((t.fid IN(".implode(',', $onlyusfids).") AND t.uid='{$mybb->user['uid']}') OR t.fid NOT IN(".implode(',', $onlyusfids)."))";
	}

	$unsearchforums = get_unsearchable_forums();
	if($unsearchforums)
	{
		$permsql .= " AND t.fid NOT IN ($unsearchforums)";
	}
	$inactiveforums = get_inactive_forums();
	if($inactiveforums)
	{
		$permsql .= " AND t.fid NOT IN ($inactiveforums)";
	}

	$visiblesql = $post_visiblesql = $plain_post_visiblesql = $unapproved_where_t = $unapproved_where_p = "";
	if(isset($search['visible']))
	{
		if($search['visible'] == 1)
		{
			$visiblesql = " AND t.visible = '1'";

			if($search['postthread'] == 1)
			{
				$post_visiblesql = " AND p.visible = '1'";
				$plain_post_visiblesql = " AND visible = '1'";
			}
		}
		elseif($search['visible'] == -1)
		{
			$visiblesql = " AND t.visible = '-1'";

			if($search['postthread'] == 1)
			{
				$post_visiblesql = " AND p.visible = '-1'";
				$plain_post_visiblesql = " AND visible = '-1'";
			}
		}
		else
		{
			$visiblesql = " AND t.visible != '1'";

			if($search['postthread'] == 1)
			{
				$post_visiblesql = " AND p.visible != '1'";
				$plain_post_visiblesql = " AND visible != '1'";
			}
		}
	}

	// Moderators can view unapproved threads and deleted threads from forums they moderate
	$unapproved_where_t = get_visible_where('t');
	$unapproved_where_p = get_visible_where('p');

	// Searching a specific thread?
	$tidsql = '';
	if(!empty($search['tid']))
	{
		$tidsql = " AND t.tid='".(int)$search['tid']."'";
	}

	$limitsql = '';
	if((int)$mybb->settings['searchhardlimit'] > 0)
	{
		$limitsql = "LIMIT ".(int)$mybb->settings['searchhardlimit'];
	}

	// Searching both posts and thread titles
	$threads = array();
	$posts = array();
	$firstposts = array();
	if($search['postthread'] == 1)
	{
		// No need to search subjects when looking for results within a specific thread
		if(empty($search['tid']))
		{
			$query = $db->query("
				SELECT t.tid, t.firstpost
				FROM ".TABLE_PREFIX."threads t
				WHERE 1=1 {$thread_datecut} {$thread_replycut} {$thread_prefixcut} {$forumin} {$thread_usersql} {$permsql} {$visiblesql} AND ({$unapproved_where_t}) AND t.closed NOT LIKE 'moved|%' {$subject_lookin}
				{$limitsql}
			");
			while($thread = $db->fetch_array($query))
			{
				$threads[$thread['tid']] = $thread['tid'];
				if($thread['firstpost'])
				{
					$posts[$thread['tid']] = $thread['firstpost'];
				}
			}
		}

		$query = $db->query("
			SELECT p.pid, p.tid
			FROM ".TABLE_PREFIX."posts p
			LEFT JOIN ".TABLE_PREFIX."threads t ON (t.tid=p.tid)
			WHERE 1=1 {$post_datecut} {$thread_replycut} {$thread_prefixcut} {$forumin} {$post_usersql} {$permsql} {$tidsql} {$post_visiblesql} {$visiblesql} AND ({$unapproved_where_t}) AND {$unapproved_where_p} AND t.closed NOT LIKE 'moved|%' {$message_lookin}
			{$limitsql}
		");
		while($post = $db->fetch_array($query))
		{
			$posts[$post['pid']] = $post['pid'];
			$threads[$post['tid']] = $post['tid'];
		}
		if(count($posts) < 1 && count($threads) < 1)
		{
			error($lang->error_nosearchresults);
		}
		$threads = implode(',', $threads);
		$posts = implode(',', $posts);

	}
	// Searching only thread titles
	else
	{
		$query = $db->query("
			SELECT t.tid, t.firstpost
			FROM ".TABLE_PREFIX."threads t
			WHERE 1=1 {$thread_datecut} {$thread_replycut} {$thread_prefixcut} {$forumin} {$thread_usersql} {$permsql} {$visiblesql} {$subject_lookin}
			{$limitsql}
		");
		while($thread = $db->fetch_array($query))
		{
			$threads[$thread['tid']] = $thread['tid'];
			if($thread['firstpost'])
			{
				$firstposts[$thread['tid']] = $thread['firstpost'];
			}
		}
		if(count($threads) < 1)
		{
			error($lang->error_nosearchresults);
		}

		$threads = implode(',', $threads);
		$firstposts = implode(',', $firstposts);
		if($firstposts)
		{
			$query = $db->simple_select("posts", "pid", "pid IN ($firstposts) {$plain_post_visiblesql} {$limitsql}");
			while($post = $db->fetch_array($query))
			{
				$posts[$post['pid']] = $post['pid'];
			}
			$posts = implode(',', $posts);
		}
	}
	return array(
		"threads" => $threads,
		"posts" => $posts,
		"querycache" => ''
	);
}
