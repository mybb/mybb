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
 * Build the mass email SQL query for the specified conditions.
 *
 * @param array $conditions Array of conditions to match users against.
 * @return string The generated search SQL
 */
function build_mass_mail_query($conditions)
{
	global $db;

	if(!is_array($conditions))
	{
		return '';
	}

	$search_sql = 'u.allownotices=1';

	// List of valid LIKE search fields
	$user_like_fields = array("username", "email");
	foreach($user_like_fields as $search_field)
	{
		if($conditions[$search_field])
		{
			$search_sql .= " AND u.{$search_field} LIKE '%".$db->escape_string_like($conditions[$search_field])."%'";
		}
	}

	// LESS THAN or GREATER THAN
	$direction_fields = array("postnum");
	foreach($direction_fields as $search_field)
	{
		$direction_field = $search_field."_dir";
		if(!empty($conditions[$search_field]) && $conditions[$direction_field])
		{
			switch($conditions[$direction_field])
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
			$search_sql .= " AND u.{$search_field}{$direction}'".(int)$conditions[$search_field]."'";
		}
	}

	// Time-based search fields
	$time_fields = array("regdate", "lastactive");
	foreach($time_fields as $search_field)
	{
		$time_field = $search_field."_date";
		$direction_field = $search_field."_dir";
		if(!empty($conditions[$search_field]) && $conditions[$time_field] && $conditions[$direction_field])
		{
			switch($conditions[$time_field])
			{
				case "hours":
					$date = $conditions[$search_field]*60*60;
					break;
				case "days":
					$date = $conditions[$search_field]*60*60*24;
					break;
				case "weeks":
					$date = $conditions[$search_field]*60*60*24*7;
					break;
				case "months":
					$date = $conditions[$search_field]*60*60*24*30;
					break;
				case "years":
					$date = $conditions[$search_field]*60*60*24*365;
					break;
				default:
					$date = $conditions[$search_field]*60*60*24;
			}

			switch($conditions[$direction_field])
			{
				case "less_than":
					$direction = ">";
					break;
				case "more_than":
					$direction = "<";
					break;
				default:
					$direction = "<";
			}
			$search_sql .= " AND u.{$search_field}{$direction}'".(TIME_NOW-$date)."'";
		}
	}

	// Usergroup based searching
	if(!empty($conditions['usergroup']))
	{
		if(!is_array($conditions['usergroup']))
		{
			$conditions['usergroup'] = array($conditions['usergroup']);
		}

		$conditions['usergroup'] = array_map('intval', $conditions['usergroup']);

		$additional_sql = '';
		foreach($conditions['usergroup'] as $usergroup)
		{
			switch($db->type)
			{
				case "pgsql":
				case "sqlite":
					$additional_sql .= " OR ','||additionalgroups||',' LIKE '%,{$usergroup},%'";
					break;
				default:
					$additional_sql .= " OR CONCAT(',',additionalgroups,',') LIKE '%,{$usergroup},%'";
			}
		}
		$search_sql .= " AND (u.usergroup IN (".implode(",", $conditions['usergroup']).") {$additional_sql})";
	}

	return $search_sql;
}

/**
 * Create a text based version of a HTML mass email.
 *
 * @param string $message The HTML version.
 * @return string The generated text based version.
 */
function create_text_message($message)
{
	// Cut out all current line breaks
	// Makes links CONTENT (link)
	$message = make_pretty_links($message);
	$message = str_replace(array("\r\n", "\n"), "\n", $message);
	$message = preg_replace("#</p>#i", "\n\n", $message);
	$message = preg_replace("#<br( \/?)>#i", "\n", $message);
	$message = preg_replace("#<p[^>]*?>#i", "", $message);
	$message = preg_replace("#<hr[^>]*?>\s*#i", "-----------\n", $message);
	$message = html_entity_decode($message);
	$message = str_replace("\t", "", $message);
	do
	{
		$message = str_replace("  ", " ", $message);
	}
	while(strpos($message, "  ") !== false);

	$search = array('@<script[^>]*?>.*?</script>@si',  // Strip out javascript
				   '@<style[^>]*?>.*?</style>@siU',    // Strip style tags properly
				   '@<title[^>]*?>.*?</title>@siU',    // Strip title tags
				   '@<[\/\!]*?[^<>]*?>@si',            // Strip out HTML tags
				   '@<![\s\S]*?--[ \t\n\r]*>@'        // Strip multi-line comments including CDATA
	);
	$message = preg_replace($search, '', $message);
	$message = preg_replace("#\n\n+#", "\n\n", $message);
	$message = preg_replace("#^\s+#is", "", $message);
	return $message;
}

/**
 * Generates friendly links for a text based version of a mass email from the HTML version.
 *
 * @param string $message_html The HTML version.
 * @return string The version with the friendly links and all <a> tags stripped.
 */
function make_pretty_links($message_html)
{
	do
	{
		$start = stripos($message_html, "<a");
		if($start === false)
		{
			break;
		}
		$end = stripos($message_html, "</a>", $start);
		if($end === false)
		{
			break;
		}

		$a_href = substr($message_html, $start, ($end-$start));

		preg_match("#href=\"?([^\"> ]+)\"?#i", $a_href, $href_matches);
		if(!$href_matches[1])
		{
			continue;
		}
		$link = $href_matches[1];

		$contents = strip_tags($a_href);
		if(!$contents)
		{
			preg_match("#alt=\"?([^\">]+)\"?#i", $a_href, $matches2);
			if($matches2[1])
			{
				$contents = $matches2[1];
			}
			if(!$contents)
			{
				preg_match("#title=\"?([^\">]+)\"?#i", $a_href, $matches2);
				if($matches2[1])
				{
					$contents = $matches2[1];
				}
			}
		}

		$replaced_link = $contents." ({$link}) ";

		$message_html = substr_replace($message_html, $replaced_link, $start, ($end-$start));
	} while(true);
	return $message_html;
}
