<?php
/**
 * MyBB 1.0
 * Copyright © 2005 MyBulletinBoard Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/eula.html
 *
 * $Id:  $
 */

/*

$l['invalid_mycode_cid'] = "The MyCode id is invalid. Please specify a valid MyCode id.";
$l['invalid_mycode_title'] = "The MyCode title you entered is invalid. Please enter a valid title.";
$l['invalid_mycode_description'] = "The MyCode description you entered is invalid. Please enter a valid description or leave the field empty.";
$l['invalid_mycode_regex_replacement'] = "The regex and/or replacement you entered either do not match or are invalid. Please enter a valid regex/replacement combination.";

*/

class MyCode {
	
	/**
	 * The message that is being parsed.
	 *
	 * @var string
	 */
	var $message;
	
	/**
	 * Parses a string of text with mycode.
	 *
	 * @param string The message to be parsed.
	 * @return string The parsed message.
	 */
	function parse($message, $mycode_perms = array())
	{		
		global $db;
		
		$this->message = $message;
				
		$query = $db->query("SELECT regex, replacement FROM ".TABLE_PREFIX."mycodes");
		
		while($mycodes = $db->fetch_array($query))
		{
			$this->message = preg_replace($mycodes['regex'], $mycodes['replacement'], $this->message);
		}
		
		/* Parse lists */
		while(preg_match("#\[list\](.*?)\[/list\]#esi", $this->message))
		{
			$this->message = preg_replace("#\[list\](.*?)\[/list\]#esi", "MyCode::do_list('$1')", $this->message);
		}
		while(preg_match("#\[list=(a|A|i|I|1)\](.*?)\[/list\]#esi", $this->message))
		{
			$this->message = preg_replace("#\[list=(a|A|i|I|1)\](.*?)\[/list\]#esi", "MyCode::do_list('$2', '$1')", $this->message);
		}
		
		/* Parse image code */
		if($mycode_perms['allowimgcode'] != "no")
		{
			$this->message = preg_replace("#\[img\]([a-z]+?://){1}(.+?)\[/img\]#i", "<img src=\"$1$2\" border=\"0\" alt=\"\" />", $this->message);
			$this->message = preg_replace("#\[img=([0-9]{1,3})x([0-9]{1,3})\]([a-z]+?://){1}(.+?)\[/img\]#i", "<img src=\"$3$4\" style=\"border: 0; width: $1px; height: $2px;\" alt=\"\" />", $this->message);
		}
		
		/* Parse smilies if allowed */
		if($mycode_perms['allowsmilies'] != "no")
		{
			if($archive == "yes")
			{
				$this->do_smilies($mybb->settings['bburl']);
			}
			else
			{
				$this->do_smilies();
			}
		}
		
		/* Parse special mycodes */
		$this->do_code();
		$this->do_quotes();
		$this->do_autourl();
		
		return $this->message;
	}
	
	/**
	 * Parses a message for quotes.
	 *
	 * @param string The message to be parsed.
	 * @return The parsed message.
	 */
	function do_quotes()
	{
		global $lang;
		
		/* User sanity check */
		$pattern = array("#\[quote=(?:&quot;|\"|')?(.*?)[\"']?(?:&quot;|\"|')?\](.*?)\[\/quote\]#si",
						 "#\[quote\](.*?)\[\/quote\]#si");
		
		$replace = array("<div class=\"quote_header\">$1 $lang->wrote</div><div class=\"quote_body\">$2</div>",
						 "<div class=\"quote_header\">$lang->quote</div><div class=\"quote_body\">$1</div>\n");
		
		while(preg_match($pattern[0], $this->message) or preg_match($pattern[1], $this->message))
		{
			$this->message = preg_replace($pattern, $replace, $this->message);
		}
		$this->message = str_replace("<div class=\"quote_body\"><br />", "<div class=\"quote_body\">", $this->message);
		$this->message = str_replace("<br /></div>", "</div>", $this->message);
	}
	
	/**
	 * Parses a message for code.
	 *
	 */
	function do_code()
	{
		global $lang;
		
		/* User sanity check */
		$m2 = strtolower($this->message);
		$opencount = substr_count($m2, "[code]");
		$closedcount = substr_count($m2, "[/code]");
		if($opencount > $closedcount)
		{
			$limit = $closedcount;
		}
		elseif($closedcount > $opencount)
		{
			$limit = $opencount;
		}
		else
		{
			$limit = -1;
		}
		$pattern = array("#\[code\](.*?)#si",
						 "#\[\/code\]#si");
	
		$replace = array("<div class=\"code_header\">$lang->code</div><div class=\"code_body\">",
						 "</div>\n");
	
		$this->message = preg_replace($pattern, $replace, $this->message, $limit);
		$this->message = str_replace("<div class=\"code_body\"><br />", "<div class=\"code_body\">", $this->message);
		$this->message = str_replace("<br /></div>", "</div>", $this->message);
		
		while(preg_match("#\[php\](.+?)\[/php\]#ies", $this->message, $matches))
		{
			$this->message = str_replace($matches[0], $this->do_phpcode($matches[1]), $this->message);
		}
	}
	
	/**
	 * Parses a message for PHP code.
	 *
	 * @param string The message to be parsed.
	 * @return string The parsed message.
	 */
	function do_phpcode($str)
	{
		global $lang;
		
		/* Replace bad characters */
		$str = str_replace('&lt;', '<', $str);
		$str = str_replace('&gt;', '>', $str);
		$str = str_replace('&amp;', '&', $str);
		$str = str_replace("\n", '', $str);
		$original = $str;
		
		if(preg_match("/\A[\s]*\<\?/", $str) === 0)
		{
			$str = "<?php\n".$str;
		}
	
		if(preg_match("/\A[\s]*\>\?/", strrev($str)) === 0)
		{
			$str = $str."\n?>";
		}
		
		/* If we can highlight, do so */
		if(PHP_VERSION >= 4)
		{
			ob_start();
			@highlight_string($str);
			$code = ob_get_contents();
			ob_end_clean();
		}
		else
		{
			$code = $str;
		}
		
		
		/* Errrr... */
		if(preg_match("/\A[\s]*\<\?/", $original) === 0)
		{
			$code = substr_replace($code, "", strpos($code, "&lt;?php"), strlen("&lt;?php"));
			$code = strrev(substr_replace(strrev($code), "", strpos(strrev($code), strrev("?&gt;")), strlen("?&gt;")));
			$code = str_replace('<br />', '', $code);
		}
		
		/* Send back the code all nice and pretty */
		return "<div class=\"code_header\">$lang->php_code</div><div class=\"code_body\">".$code."</div>";
	}
	
	/**
	 * Parses a message for smilies.
	 *
	 * @param string The URL to the smilie directory.
	 */
	function do_smilies( $url="")
	{
		global $db, $smiliecache, $cache;
	
		if($url != "")
		{
			if(substr($url, strlen($url) -1) != "/")
			{
				$url = $url."/";
			}
		}
		
		$smiliecache = $cache->read("smilies");
		if(is_array($smiliecache))
		{
			reset($smiliecache);
			foreach($smiliecache as $sid => $smilie)
			{
				$this->message = str_replace($smilie['find'], "<img src=\"".$url.$smilie['image']."\" class=\"smilie\" alt=\"".$smilie['name']."\" />", $this->message);
			}
		}
	}
	
	/**
	 * Parses a message for lists.
	 *
	 * @param string The message to be parsed.
	 * @param string The type of list (when list is ordered).
	 * @return string The parsed message.
	 */
	function do_list($message, $type="")
	{
		$message = str_replace('\"', '"', $message);
		$message = preg_replace("#\[\*\]#", "</li><li>", $message);
		$message .= "</li>";
	
		if($type)
		{
			$list = "<ol type=\"$type\">$message</ol>";
		}
		else
		{
			$list = "<ul>$message</ul>";
		}
		$list = preg_replace("#<(ol type=\"$type\"|ul)>\s*</li>#", "<$1>", $list);
		
		return $list;
	}
	
	/**
	 * Parses a message for URLs.
	 *
	 */
	function do_autourl()
	{
		$this->message = " ".$this->message;
		$this->message = preg_replace("#([\s\(\)])(https?|ftp|news){1}://([\w\-]+\.([\w\-]+\.)*[\w]+(:[0-9]+)?(/[^\"\s\(\)<\[]*)?)#ie", "\"$1\".MyCode::do_shorturl(\"$2://$3\")", $this->message);
		$this->message = preg_replace("#([\s\(\)])(www|ftp)\.(([\w\-]+\.)*[\w]+(:[0-9]+)?(/[^\"\s\(\)<\[]*)?)#ie", "\"$1\".MyCode::do_shorturl(\"$2.$3\", \"$2.$3\")", $this->message);
		$this->message = substr($this->message, 1);
	}
	
	/**
	 * Converts URLs to hyperlinks.
	 *
	 * @param string The full URL.
	 * @param string The name of the URL.
	 * @return string The built hyperlink.
	 */
	function do_shorturl($url, $name="")
	{
		$fullurl = $url;
		/* Attempt to make a bit of sense out of their URL if they dont type it properly */
		if(strpos($url, "www.") === 0)
		{
			$fullurl = "http://".$fullurl;
		}
		if(strpos($url, "ftp.") === 0)
		{
			$fullurl = "ftp://".$fullurl;
		}
	    if(strpos($fullurl, "://") === false)
	    {
	        $fullurl = "http://".$fullurl;
	    }
		if(!$name)
		{
			$name = $url;
		}
		$name = stripslashes($name);
		$url = stripslashes($url);
		$fullurl = stripslashes($fullurl);
		if($name == $url)
		{
			if(strlen($url) > 55)
			{
				$name = substr($url, 0, 40)."...".substr($url, -10);
			}
		}
		$link = "<a href=\"$fullurl\" target=\"_blank\">$name</a>";
		
		return $link;
	}
	
	/**
	 * Parses a message for email urls.
	 *
	 * @param string The email address.
	 * @param string An optional name for the link.
	 * @return string The parsed mail address.
	 */
	function do_emailurl($email, $name="")
	{
		if(!$name)
		{
			$name = $email;
		}
		if(preg_match("/^(.+)@[a-zA-Z0-9-]+\.[a-zA-Z0-9.-]+$/si", $email))
		{
			return "<a href=\"mailto:$email\">".$name."</a>";
		}
	}
	
	/**
	 * Add a mycode.
	 *
	 * @param string The title of this mycode.
	 * @param string (Optional) The description of this mycode.
	 * @param string The regex of this mycode.
	 * @param string The code to replace the regexes with.
	 */
	function add($title, $description = "", $regex, $replacement)
	{
		global $db;
		
		/* Do some validity checks */
		if(!$this->validate_title($title))
		{
			cperror($lang->invalid_mycode_title);
		}
		if(!$this->validate_description($description))
		{
			cperro($lang->invalid_mycode_description);
		}		
		if(!$this->validate_regex_replacement($regex, $replacement))
		{
			cperror($lang->invalid_regex_replacement);
		}
		
		$mycode_add = array(
			"cid" => 0,
			"title" => $title,
			"description" => $description,
			"regex" => $regex,
			"replacement" => $replacement			
		);
		
		$db->insert_query(TABLE_PREFIX."mycodes", $mycode_add);
	}
	
	/**
	 * Edit a mycode.
	 *
	 * @param int The MyCode id.
	 * @param string The title of the mycode.
	 * @param string (Optional) The description of the mycode.
	 * @param string The regex of this mycode.
	 * @param string The code to replace the regexes with.
	 */
	function edit($cid, $title, $description = "", $regex, $replacement)
	{
		global $db;
		
		/* Do some validity checks */
		if(!$this->validate_title($title))
		{
			cperror($lang->invalid_mycode_title);
		}
		if(!$this->validate_description($description))
		{
			cperro($lang->invalid_mycode_description);
		}		
		if(!$this->validate_regex_replacement($regex, $replacement))
		{
			cperror($lang->invalid_regex_replacement);
		}
		
		if(!$this->validate_cid($cid))
		{
			cperror($lang->invalid_cid);
		}
		
		$cid = intval($cid);
		
		$mycode_edit = array(
			"regex" => $regex,
			"replacement" => $replacement
		);
		
		$db->update_query(TABLE_PREFIX."mycodes", $mycode_edit, "cid = ".$cid);
	}
	
	/**
	 * Delete a mycode.
	 *
	 * @param int The MyCode id.
	 */
	function delete($cid)
	{
		global $db;
		
		/* Do some validity checks */
		if(!$this->validate_cid($cid))
		{
			cperror($lang->invalid_cid);
		}
		$cid = intval($cid);
		
		$db->query("DELETE FROM ".TABLE_PREFIX."mycodes WHERE cid = ".$cid);
	}
	
	/**
	 * Validates a mycode id.
	 *
	 * @param int|string The mycode id.
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_cid($cid)
	{
		if(!is_numeric($cid))
		{
			return FALSE;
		}
		
		return TRUE;
	}
	
	/**
	 * Validates a title.
	 *
	 * @param string The mycode title.
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_title($title)
	{
		if(empty($title) || !is_string($title))
		{
			return FALSE;
		}
		if(my_strlen($title) > 100)
		{
			return FALSE;
		}
		
		return TRUE;
	}
	
	/**
	 * Validates a description.
	 *
	 * @param string The description string.
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_description($description)
	{
		if(!is_string($description))
		{
			return FALSE;
		}
		
		return TRUE;
	}
	
	/**
	 * Validates a regex and its associated replacement.
	 *
	 * @param string The regex of this mycode.
	 * @param string The code to replace the regexes with.
	 * @return boolean True when valid, false when invalid.
	 */
	function validate_regex_replacement($regex, $replacement)
	{
		if(empty($regex) || !is_string($regex))
		{
			return FALSE;
		}
		
		if(empty($replacement) || !is_string($regex))
		{
			return FALSE;
		}
		
		return TRUE;
	}
	
}
?>