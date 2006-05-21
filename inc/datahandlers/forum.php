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

/**
 * Forum handling class, provides common structure to handle forum data.
 *
 */
class ForumDataHandler extends DataHandler
{
	/**
	* The language file used in the data handler.
	*
	* @var string
	*/
	var $language_file = 'datahandler_forum';

	/**
	* The prefix for the language variables used in the data handler.
	*
	* @var string
	*/
	var $language_prefix = 'forumdata';

	/**
	* Verifies a forum name.
	*
	* @return boolean True if valid, false if invalid.
	*/
	function verify_name()
	{
		$forum = &$this->data;

		if(trim($forum['name']) == "")
		{
			$this->set_error("missing_forum_name");
			return false;
		}

		return true;
	}

	/**
	* Verifies a forum type.
	*
	* @return boolean True if valid, false if invalid.
	*/
	function verify_type()
	{
		$forum = &$this->data;

		// Trim the link for redirect forums.
		$forum['linkto'] = trim($forum['linkto']);

		// Check this is a valid type of forum.
		if($forum['type'] != "f" && $forum['type'] != "c")
		{
			$forum['type'] = "f";
		}

		return true;
	}

	/**
	* Verifies a forum parent.
	*
	* @return boolean True if valid, false if invalid.
	*/
	function verify_parent()
	{
		$forum = &$this->data;

		// Check if the parent forum actually exists.
		$parentlist = get_forum($forum['fid']);
		$parentlist = explode("," $parentlist);
		if(!in_array($forum['pid'], $parentlist))
		{
			$this->set_error("invalid_parent_forum");
			return false;
		}

		return true;
	}

	/**
	* Verifies a forum's options.
	*
	* @return boolean True if valid, false if invalid.
	*/
	function verify_options()
	{
		$forum = &$this->data;

		// Clean all the forum options.
		if($forum['options']['allowhtml'] != "yes")
		{
			$forum['options']['allowhtml'] = "no";
		}
		if($forum['options']['allowmycode'] != "yes")
		{
			$forum['options']['allowmycode'] = "no";
		}
		if($forum['options']['allowsmilies'] != "yes")
		{
			$forum['options']['allowsmilies'] = "no";
		}
		if($forum['options']['allowimgcode'] != "yes")
		{
			$forum['options']['allowimgcode'] = "no";
		}
		if($forum['options']['allowpicon'] != "yes")
		{
			$forum['options']['allowpicon'] = "no";
		}
		if($forum['options']['allowtratings'] != "yes")
		{
			$forum['options']['allowtratings'] = "no";
		}
		if($forum['options']['usepostcounts'] != "yes")
		{
			$forum['options']['usepostcounts'] = "no";
		}
		if($forum['options']['modposts'] != "yes")
		{
			$forum['options']['modposts'] = "no";
		}
		if($forum['options']['modthreads'] != "yes")
		{
			$forum['options']['modthreads'] = "no";
		}
		if($forum['options']['modattachments'] != "yes")
		{
			$forum['options']['modattachments'] = "no";
		}
		if($forum['options']['showinjump'] != "yes")
		{
			$forum['options']['showinjump'] = "no";
		}
		if($forum['options']['overridetheme'] != "yes")
		{
			$forum['options']['overridetheme'] = "no";
		}
	}

	/**
	* Verifies a forum's display order.
	*
	* @return boolean True if valid, false if invalid.
	*/
	function verify_displayorder()
	{
		// Check if the display order has already been chosen?
		// This is not in MyBB right now, but might be nice to implement.

		$forum = &$this->data;

		return true;
	}

	/**
	* Verifies a forum's theme.
	*
	* @return boolean True if valid, false if invalid.
	*/
	function verify_theme()
	{
		$forum = &$this->data;

		// Check if the theme exists.
		$options = array(
			"limit" => 1
		);
		$query = $db->simple_select(TABLE_PREFIX."theme", "tid", "theme=".$forum['theme'], $options);
		if($db->num_rows($query) != 1)
		{
			$this->set_error("invalid_theme");
			return false;
		}

		return true;
	}


	/**
	* Verifies a forum's default settings.
	*
	* @return boolean True if valid, false if invalid.
	*/
	function verify_defaults()
	{
		$forum = &$this->data;

		// Verify the default date cut for showing threads.
		$forum['defaultdatecut'] = intval($forum['defaultdatecut']);
		if($forum['defaultdatecut'] != 5 &&
			$forum['defaultdatecut'] != 10 &&
			$forum['defaultdatecut'] != 20 &&
			$forum['defaultdatecut'] != 50 &&
			$forum['defaultdatecut'] != 75 &&
			$forum['defaultdatecut'] != 100 &&
			$forum['defaultdatecut'] != 365)
		{
			$forum['defaultdatecut'] = 9999;
		}

		// Verify the default sort order field.
		if($forum['defaultsortby'] != "posts" &&
			$forum['defaultsortby'] != "replies" &&
			$forum['defaultsortby'] != "views" &&
			$forum['defaultsortby'] != "subject" &&
			$forum['defaultsortby'] != "starter" &&
			$forum['defaultsortby'] != "rating" &&
		)
		{
			$forum['defaultsortby'] = "lastpost";
		}

		// Verify the default sort order.
		if($forum['defaultsortorder'] != "asc")
		{
			$forum['defaultsortorder'] = "desc";
		}
	}

	/**
	* Validates a forum.
	*
	*/
	function validate_forum()
	{
		// Verify all forum assets.
		$this->verify_name();
		$this->verify_linkto();
		$this->verify_parent();
		$this->verify_options();
		$this->verify_displayorder();
		$this->verify_theme();
		$this->verify_defaults();

		// We are done validating, return.
		$this->set_validated(true);
		if(count($this->get_errors()) > 0)
		{
			return false;
		}
		else
		{
			return true;
		}
	}

	/**
	* Inserts a forum.
	*
	*/
	function insert_forum()
	{
		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The forum needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The forum is not valid.");
		}

		$forum = &$this->data;

		$insert_forum = array(
			"name" => $db->escape_string($forum['name']),
			"description" => $db->escape_string($forum['description']),
			"linkto" => $db->escape_string($forum['linkto']),
			"type" => $forum['type'],
			"pid" => $forum['pid'],
			"disporder" => $forum['disporder'],
			"active" => $db->escape_string($mybb->input['isactive']),
			"open" => $db->escape_string($mybb->input['isopen']),
			"threads" => '0',
			"posts" => '0',
			"lastpost" => '0',
			"lastposter" => '0',
			"password" => $db->escape_string($mybb->input['password']),
			"theme" => $db->escape_string($forum['theme']),
			"rulestype" => $db->escape_string($forum['rulestype']),
			"rulestitle" => $db->escape_string($forum['rulestitle']),
			"rules" => $db->escape_string($forum['rules']),
			"defaultdatecut" => $forum['defaultdatecut'],
			"defaultsortby" => $db->escape_string($forum['defaultsortby']),
			"defaultsortorder" => $db->escape_string($forum['defaultsortorder']),
			"allowhtml" => $db->escape_string($forum['options']['allowhtml']),
			"allowmycode" => $db->escape_string($forum['options']['allowmycode']),
			"allowsmilies" => $db->escape_string($forum['options']['allowsmilies']),
			"allowimgcode" => $db->escape_string($forum['options']['allowimgcode']),
			"allowpicons" => $db->escape_string($forum['options']['allowpicons']),
			"allowtratings" => $db->escape_string($forum['options']['allowtratings']),
			"usepostcounts" => $db->escape_string($forum['options']['usepostcounts']),
			"showinjump" => $db->escape_string($forum['options']['showinjump']),
			"modposts" => $db->escape_string($forum['options']['modposts']),
			"modthreads" => $db->escape_string($forum['options']['modthreads']),
			"modattachments" => $db->escape_string($forum['options']['modattachments']),
			"overridetheme" => $db->escape_string($forum['options']['overridetheme'])
		);
		$db->insert_query(TABLE_PREFIX."forums", $insert_forum);
		$fid = $db->insert_id();

		return array(
			"fid" => $fid;
		);

	}

	/**
	* Updates a forum.
	*
	*/
	function update_forum()
	{
		// Yes, validating is required.
		if(!$this->get_validated())
		{
			die("The forum needs to be validated before inserting it into the DB.");
		}
		if(count($this->get_errors()) > 0)
		{
			die("The forum is not valid.");
		}

		$forum = &$this->data;

		$update_forum = array(
			"name" => $db->escape_string($forum['name']),
			"description" => $db->escape_string($forum['description']),
			"linkto" => $db->escape_string($forum['linkto']),
			"type" => $forum['type'],
			"pid" => $forum['pid'],
			"disporder" => $forum['disporder'],
			"active" => $db->escape_string($mybb->input['isactive']),
			"open" => $db->escape_string($mybb->input['isopen']),
			"threads" => '0',
			"posts" => '0',
			"lastpost" => '0',
			"lastposter" => '0',
			"password" => $db->escape_string($mybb->input['password']),
			"theme" => $db->escape_string($forum['theme']),
			"rulestype" => $db->escape_string($forum['rulestype']),
			"rulestitle" => $db->escape_string($forum['rulestitle']),
			"rules" => $db->escape_string($forum['rules']),
			"defaultdatecut" => $forum['defaultdatecut'],
			"defaultsortby" => $db->escape_string($forum['defaultsortby']),
			"defaultsortorder" => $db->escape_string($forum['defaultsortorder']),
			"allowhtml" => $db->escape_string($forum['options']['allowhtml']),
			"allowmycode" => $db->escape_string($forum['options']['allowmycode']),
			"allowsmilies" => $db->escape_string($forum['options']['allowsmilies']),
			"allowimgcode" => $db->escape_string($forum['options']['allowimgcode']),
			"allowpicons" => $db->escape_string($forum['options']['allowpicons']),
			"allowtratings" => $db->escape_string($forum['options']['allowtratings']),
			"usepostcounts" => $db->escape_string($forum['options']['usepostcounts']),
			"showinjump" => $db->escape_string($forum['options']['showinjump']),
			"modposts" => $db->escape_string($forum['options']['modposts']),
			"modthreads" => $db->escape_string($forum['options']['modthreads']),
			"modattachments" => $db->escape_string($forum['options']['modattachments']),
			"overridetheme" => $db->escape_string($forum['options']['overridetheme'])
		);
		$db->update_query(TABLE_PREFIX."forums", $update_forum, "fid=".$forum['fid']);
	}
}
?>