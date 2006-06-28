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

/*
options = array(
	allow_html
	allow_smilies
	allow_mycode
	nl2br
	filter_badwords
	me_username
)
*/

class postParser
{
	/**
	 * Internal cache of MyCode.
	 *
	 * @var mixed
	 */
	var $mycode_cache = 0;

	/**
	 * Internal cache of smilies
	 *
	 * @var mixed
	 */
	var $smilies_cache = 0;

	/**
	 * Internal cache of badwords filters
	 *
	 * @var mixed
	 */
	var $badwords_cache = 0;

	/**
	 * Base URL for smilies
	 *
	 * @var string
	 */
	var $base_url;

	/**
	 * Parses a message with the specified options.
	 *
	 * @param string The message to be parsed.
	 * @param array Array of yes/no options - allow_html,filter_badwords,allow_mycode,allow_smilies,nl2br,me_username.
	 * @return string The parsed message.
	 */
	function parse_message($message, $options=array())
	{
		global $plugins, $mybb;

		// Set base URL for parsing smilies
		$this->base_url = $mybb->settings['bburl'];

		if($this->base_url != "")
		{
			if(my_substr($this->base_url, my_strlen($this->base_url) -1) != "/")
			{
				$this->base_url = $this->base_url."/";
			}
		}

		// Always fix bad Javascript in the message.
		$message = $this->fix_javascript($message);

		// If HTML is disallowed, clean the post of it.
		if($options['allow_html'] != "yes")
		{
			$message = $this->parse_html($message);
		}

		// Filter bad words if requested.
		if($options['filter_badwords'] != "no")
		{
			$message = $this->parse_badwords($message);
		}

		// If MyCode needs to be replaced, first filter out [code] and [php] tags.
		if($options['allow_mycode'] != "no")
		{
			// First we split up the contents of code and php tags to ensure they're not parsed.
			preg_match_all("#\[(code|php)\](.*?)\[/\\1\](\r\n?|\n?)#si", $message, $code_matches, PREG_SET_ORDER);
			$message = preg_replace("#\[(code|php)\](.*?)\[/\\1\](\r\n?|\n?)#si", "<mybb-code>\n", $message);
		}

		// If we can, parse smiliesa
		if($options['allow_smilies'] != "no")
		{
			$message = $this->parse_smilies($message, $options['allowhtml']);
		}

		// Replace MyCode if requested.
		if($options['allow_mycode'] != "no")
		{
			$message = $this->parse_mycode($message, $options);
		}

		// Run plugin hooks
		$message = $plugins->run_hooks("parse_message", $message);

		if($options['allow_mycode'] != "no")
		{
			// Now that we're done, if we split up any code tags, parse them and glue it all back together
			if(count($code_matches) > 0)
			{
				foreach($code_matches as $text)
				{
					if(strtolower($text[1]) == "code")
					{
						$code = $this->mycode_parse_code($text[2]);
					}
					elseif(strtolower($text[1]) == "php")
					{
						$code = $this->mycode_parse_php($text[2]);
					}
					$message = preg_replace("#<mybb-code>\n#", $code, $message, 1);
				}
			}
		}

		if($options['nl2br'] != "no")
		{
			$message = nl2br($message);
			$message = str_replace("</div><br />", "</div>", $message);
		}
		return $message;
	}

	/**
	 * Converts HTML in a message to their specific entities whilst allowing unicode characters.
	 *
	 * @param string The message to be parsed.
	 * @return string The formatted message.
	 */
	function parse_html($message)
	{
		$message = preg_replace("#&(?!\#[0-9]+;)#si", "&amp;", $message); // fix & but allow unicode
		$message = str_replace("<","&lt;",$message);
		$message = str_replace(">","&gt;",$message);
		return $message;
	}

	/**
	 * Generates a cache of MyCode, both standard and custom.
	 *
	 * @access private
	 */
	function cache_mycode()
	{
		global $cache;
		$this->mycode_cache = array();

		$standard_mycode['b']['regex'] = "#\[b\](.*?)\[/b\]#si";
		$standard_mycode['b']['replacement'] = "<strong>$1</strong>";

		$standard_mycode['u']['regex'] = "#\[u\](.*?)\[/u\]#si";
		$standard_mycode['u']['replacement'] = "<u>$1</u>";

		$standard_mycode['i']['regex'] = "#\[i\](.*?)\[/i\]#si";
		$standard_mycode['i']['replacement'] = "<em>$1</em>";

		$standard_mycode['s']['regex'] = "#\[s\](.*?)\[/s\]#si";
		$standard_mycode['s']['replacement'] = "<del>$1</del>";

		$standard_mycode['copy']['regex'] = "#\(c\)#i";
		$standard_mycode['copy']['replacement'] = "&copy;";

		$standard_mycode['tm']['regex'] = "#\(tm\)#i";
		$standard_mycode['tm']['replacement'] = "&#153;";

		$standard_mycode['reg']['regex'] = "#\(r\)#i";
		$standard_mycode['reg']['replacement'] = "&reg;";

		$standard_mycode['url_simple']['regex'] = "#\[url\]([a-z]+?://)([^\r\n\"\[<]+?)\[/url\]#sei";
		$standard_mycode['url_simple']['replacement'] = "\$this->mycode_parse_url(\"$1$2\")";

		$standard_mycode['url_simple2']['regex'] = "#\[url\]([^\r\n\"\[<]+?)\[/url\]#ei";
		$standard_mycode['url_simple2']['replacement'] = "\$this->mycode_parse_url(\"$1\")";

		$standard_mycode['url_complex']['regex'] = "#\[url=([a-z]+?://)([^\r\n\"\[<]+?)\](.+?)\[/url\]#esi";
		$standard_mycode['url_complex']['replacement'] = "\$this->mycode_parse_url(\"$1$2\", \"$3\")";

		$standard_mycode['url_complex2']['regex'] = "#\[url=([^\r\n\"\[<&\(\)]+?)\](.+?)\[/url\]#esi";
		$standard_mycode['url_complex2']['replacement'] = "\$this->mycode_parse_url(\"$1\", \"$2\")";

		$standard_mycode['email_simple']['regex'] = "#\[email\](.*?)\[/email\]#ei";
		$standard_mycode['email_simple']['replacement'] = "\$this->mycode_parse_email(\"$1\")";

		$standard_mycode['email_complex']['regex'] = "#\[email=(.*?)\](.*?)\[/email\]#ei";
		$standard_mycode['email_complex']['replacement'] = "\$this->mycode_parse_email(\"$1\", \"$2\")";

		$standard_mycode['color']['regex'] = "#\[color=([a-zA-Z]*|\#?[0-9a-fA-F]{6})](.*?)\[/color\]#si";
		$standard_mycode['color']['replacement'] = "<span style=\"color: $1;\">$2</span>";

		$standard_mycode['size']['regex'] = "#\[size=(xx-small|x-small|small|medium|large|x-large|xx-large)\](.*?)\[/size\]#si";
		$standard_mycode['size']['replacement'] = "<span style=\"font-size: $1;\">$2</span>";

		$standard_mycode['size_int']['regex'] = "#\[size=([0-9\+\-]+?)\](.*?)\[/size\]#si";
		$standard_mycode['size_int']['replacement'] = "<font size=\"$1\">$2</font>";

		$standard_mycode['font']['regex'] = "#\[font=([a-z ]+?)\](.+?)\[/font\]#si";
		$standard_mycode['font']['replacement'] = "<span style=\"font-family: $1;\">$2</span>";

		$standard_mycode['align']['regex'] = "#\[align=(left|center|right|justify)\](.*?)\[/align\]#si";
		$standard_mycode['align']['replacement'] = "<p style=\"text-align: $1;\">$2</p>";

		$standard_mycode['hr']['regex'] = "#\[hr\]#si";
		$standard_mycode['hr']['replacement'] = "<hr />";

		$custom_mycode = $cache->read("mycode");

		// If there is custom MyCode, load it.
		if(is_array($custom_mycode))
		{
			foreach($custom_mycode as $key => $mycode)
			{
				$custom_mycode[$key]['regex'] = "#".$mycode['regex']."#si";
			}
			$mycode = array_merge($standard_mycode, $custom_mycode);
		}
		else
		{
			$mycode = $standard_mycode;
		}

		// Assign the MyCode to the cache.
		foreach($mycode as $code)
		{
			$this->mycode_cache['find'][] = $code['regex'];
			$this->mycode_cache['replacement'][] = $code['replacement'];
		}
	}

	/**
	 * Parses MyCode tags in a specific message with the specified options.
	 *
	 * @param string The message to be parsed.
	 * @param array Array of options in yes/no format. Options are allow_imgcode.
	 * @return string The parsed message.
	 */
	function parse_mycode($message, $options=array())
	{
		global $lang;

		// Cache the MyCode globally if needed.
		if($this->mycode_cache == 0)
		{
			$this->cache_mycode();
		}

		// Parse quotes first
		$message = $this->mycode_parse_quotes($message);

		// Replace the rest
		$message = preg_replace($this->mycode_cache['find'], $this->mycode_cache['replacement'], $message);

		// Special code requiring special attention
		while(preg_match("#\[list\](.*?)\[/list\]#esi", $message))
		{
			$message = preg_replace("#\[list\](.*?)\[/list\](\r\n?|\n?)#esi", "\$this->mycode_parse_list('$1')\n", $message);
		}

		// Replace lists.
		while(preg_match("#\[list=(a|A|i|I|1)\](.*?)\[/list\](\r\n?|\n?)#esi", $message))
		{
			$message = preg_replace("#\[list=(a|A|i|I|1)\](.*?)\[/list\]#esi", "\$this->mycode_parse_list('$2', '$1')\n", $message);
		}

		// Convert images when allowed.
		if($options['allow_imgcode'] != "no")
		{
			$message = preg_replace("#\[img\](https?://([^<>\"']+))\[/img\]#i", "<img src=\"$1\" border=\"0\" alt=\"\" />", $message);
			$message = preg_replace("#\[img=([0-9]{1,3})x([0-9]{1,3})\](https?://([^<>\"']+))\[/img\]#i", "<img src=\"$3\" style=\"border: 0; width: $1px; height: $2px;\" alt=\"\" />", $message);
		}

		// Replace "me" code and slaps if we have a username
		if($options['me_username'])
		{
			$message = preg_replace('#(\r|\n)/me ([^\r\n<]*)#i', "\n<span style=\"color: red;\">* {$options['me_username']} \\2</span>", $message);
			$message = preg_replace('#(\r|\n)/slap ([^\r\n]*)#i', "\n<span style=\"color: red;\">* {$options['me_username']} {$lang->slaps} \\2 {$lang->with_trout}</span><br />", $message);
		}

		$message = $this->mycode_auto_url($message);

		return $message;
	}

	/**
	 * Generates a cache of smilies
	 *
	 * @access private
	 */
	function cache_smilies()
	{
		global $cache;
		$this->smilies_cache = array();

		$smilies = $cache->read("smilies");
		foreach($smilies as $sid => $smilie)
		{
			$this->smilies_cache[$smilie['find']] = "<img src=\"{$this->base_url}{$smilie['image']}\" style=\"vertical-align: middle;\" border=\"0\" alt=\"{$smilie['name']}\" title=\"{$smilie['name']}\" />";
		}
	}

	/**
	 * Parses smilie code in the specified message.
	 *
	 * @param string The message being parsed.
	 * @param string Base URL for the image tags created by smilies.
	 * @param string Yes/No if HTML is allowed in the post
	 * @return string The parsed message.
	 */
	function parse_smilies($message, $allow_html="no")
	{
		if($this->smilies_cache == 0)
		{
			$this->cache_smilies();
		}
		if(is_array($this->smilies_cache))
		{
			reset($this->smilies_cache);
			foreach($this->smilies_cache as $find => $replace)
			{
				if($allow_html != "yes")
				{
					$find = $this->parse_html($find);
				}
				$message = str_replace($find, $replace, $message);
			}
		}
		return $message;
	}

	/**
	 * Generates a cache of badwords filters.
	 *
	 * @access private
	 */
	function cache_badwords()
	{
		global $cache;
		$this->badwords_cache = array();
		$this->badwords_cache = $cache->read("badwords");
	}

	/**
	 * Parses a list of filtered/badwords in the specified message.
	 *
	 * @param string The message to be parsed.
	 * @param array Array of parser options in yes/no format.
	 * @return string The parsed message.
	 */
	function parse_badwords($message, $options=array())
	{
		if($this->badwords_cache == 0)
		{
			$this->cache_badwords();
		}
		if(is_array($this->badwords_cache))
		{
			reset($this->badwords_cache);
			foreach($this->badwords_cache as $bid => $badword)
			{
				if(!$badword['replacement']) $badword['replacement'] = "*****";
				$badword['badword'] = preg_quote($badword['badword']);
				$message = preg_replace("#\b".$badword['badword']."\b#i", $badword['replacement'], $message);
			}
		}
		if($options['strip_tags'] == "yes")
		{
			$message = strip_tags($message);
		}
		return $message;
	}

	/**
	 * Attempts to move any javascript references in the specified message.
	 *
	 * @param string The message to be parsed.
	 * @return string The parsed message.
	 */
	function fix_javascript($message)
	{
		$message = preg_replace("#(java)(script:)#i", "$1 $2", $message);
		$js_array = array(
			"#(a)(lert)#ie",
			"#(o)(nmouseover)#ie",
			"#(o)(nmouseout)#ie",
			"#(o)(nmousedown)#ie",
			"#(o)(nmousemove)#ie",
			"#(o)(nmouseup)#ie",
			"#(o)(nclick)#ie",
			"#(o)(ndblclick)#ie",
			"#(o)(nload)#ie",
			"#(o)(nsubmit)#ie",
			"#(o)(nblur)#ie",
			"#(o)(nchange)#ie",
			"#(o)(nfocus)#ie",
			"#(o)(nselect)#ie",
			"#(o)(nunload)#ie",
			"#(o)(nkeypress)#ie"
			);
		$message = preg_replace($js_array, "'&#'.ord($1).';$2'", $message);
		return $message;
	}

	/**
	* Parses quote MyCode.
	*
	* @param string The message to be parsed
	* @return string The parsed message.
	*/
	function mycode_parse_quotes($message)
	{
		global $lang;

		// Assign pattern and replace values.
		$pattern = array("#\[quote=(?:&quot;|\"|')?(.*?)[\"']?(?:&quot;|\"|')?\](.*?)\[\/quote\](\r\n?|\n?)#si",
						 "#\[quote\](.*?)\[\/quote\](\r\n?|\n?)#si");

		$replace = array("<div class=\"quote_header\">".htmlentities('\\1')." $lang->wrote</div><div class=\"quote_body\">$2</div>\n",
						 "<div class=\"quote_header\">$lang->quote</div><div class=\"quote_body\">$1</div>\n");

		while(preg_match($pattern[0], $message) or preg_match($pattern[1], $message))
		{
			$message = preg_replace($pattern, $replace, $message);
		}
		$find = array(
			"#<div class=\"quote_body\">(\r\n?|\n?)#",
			"#(\r\n?|\n?)</div>#"
		);

		$replace = array(
			"<div class=\"quote_body\">",
			"</div>"
		);
		$message = preg_replace($find, $replace, $message);
		return $message;

	}

	/**
	* Parses code MyCode.
	*
	* @param string The message to be parsed
	* @return string The parsed message.
	*/
	function mycode_parse_code($code)
	{
		global $lang;
		$code = trim($code);
		return "<div class=\"code_header\">".$lang->code."</div><div class=\"code_body\"><code><div dir=\"ltr\">".$code."</div></code></div>\n";
	}

	/**
	* Parses PHP code MyCode.
	*
	* @param string The message to be parsed
	* @return string The parsed message.
	*/
	function mycode_parse_php($str)
	{
		global $lang;
		

		// Clean the string before parsing.
		$str = trim($str);
		if(!$str)
		{
			return;
		}
		$str = str_replace('&lt;', '<', $str);
		$str = str_replace('&gt;', '>', $str);
		$str = str_replace('&amp;', '&', $str);
		$original = $str;
		// See if open and close tags are provided.
		$added_open_close = false;
		if(!preg_match("#^\s*<\?#si", $str))
		{
			$added_open_close = true;
			$str = "<?php \n".$str." \n?>";
		}
		// If the PHP version < 4.2, catch highlight_string() output.
		if(version_compare(PHP_VERSION, "4.2.0", "<"))
		{
			ob_start();
			@highlight_string($str);
			$code = ob_get_contents();
			ob_end_clean();
		}
		else
		{
			$code = @highlight_string($str, true);
		}
		// If < PHP 5, make XHTML compatible.
		if(version_compare(PHP_VERSION, "5", "<"))
		{
			$find = array(
				"<font",
				"color=\"",
				"</font>"
			);

			$replace = array(
				"<span",
				"style=\"color: ",
				"</span>"
			);
			$code = str_replace($find, $replace, $code);
		}

		// Do the actual replacing.
		$code = preg_replace('#<code>\s*<span style="color: \#000000">\s*#i', "<code>", $code);
		$code = preg_replace("#</span>\s*</code>#", "</code>", $code);
		$code = preg_replace("#</span>(\r\n?|\n?)</code>#", "</span></code>", $code);
		$code = str_replace('\\', '&#092;', $code);
		$code = preg_replace("#&amp;\#([0-9]+);#si", "&#$1;", $code);


		if($added_open_close == true)
		{
			$code = preg_replace("#<code><span style=\"color: \#0000BB\">&lt;\?php( |&nbsp;)(<br />?)#", "<code><span style=\"color: #0000BB\">", $code);
			$code = str_replace("?&gt;</span></code>", "</span></code>", $code);
		}

		$code = str_replace("<code>", "<code><div dir=\"ltr\">", $code);
		$code = str_replace("</code>", "</div></code>", $code);
		$code = preg_replace("#\s*$#", "", $code);

		// Send back the code all nice and pretty
		return "<div class=\"code_header\">$lang->php_code</div><div class=\"code_body\">".$code."</div>\n";
	}

	/**
	* Parses URL MyCode.
	*
	* @param string The URL to link to.
	* @param string The name of the link.
	* @return string The built-up link.
	*/
	function mycode_parse_url($url, $name="")
	{
		$fullurl = $url;
		$url = str_replace('&amp;', '&', $url);

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
			if(my_strlen($url) > 55)
			{
				$name = my_substr($url, 0, 40)."...".my_substr($url, -10);
			}
		}

		$name = preg_replace("#&amp;\#([0-9]+);#si", "&#$1;", $name);
		$link = "<a href=\"$fullurl\" target=\"_blank\">$name</a>";
		return $link;
	}

	/**
	* Parses email MyCode.
	*
	* @param string The email address to link to.
	* @param string The name for the link.
	* @return string The built-up email link.
	*/
	function mycode_parse_email($email, $name="")
	{
		if(!$name)
		{
			$name = $email;
		}
		if(preg_match("/^([a-zA-Z0-9-_\+\.]+?)@[a-zA-Z0-9-]+\.[a-zA-Z0-9\.-]+$/si", $email))
		{
			return "<a href=\"mailto:$email\">".$name."</a>";
		}
		else
		{
			return $email;
		}
	}

	/**
	* Parses URLs automatically.
	*
	* @param string The message to be parsed
	* @return string The parsed message.
	*/
	function mycode_auto_url($message)
	{
		$message = " ".$message;
		$message = preg_replace("#([\s\(\)])(https?|ftp|news){1}://([\w\-]+\.([\w\-]+\.)*[\w]+(:[0-9]+)?(/[^\"\s\(\)<\[]*)?)#ie", "\"$1\".\$this->mycode_parse_url(\"$2://$3\")", $message);
		$message = preg_replace("#([\s\(\)])(www|ftp)\.(([\w\-]+\.)*[\w]+(:[0-9]+)?(/[^\"\s\(\)<\[]*)?)#ie", "\"$1\".\$this->mycode_parse_url(\"$2.$3\", \"$2.$3\")", $message);
		$message = my_substr($message, 1);
		return $message;
	}

	/**
	* Parses list MyCode.
	*
	* @param string The message to be parsed
	* @return string The parsed message.
	*/
	function mycode_parse_list($message, $type="")
	{
		$message = str_replace('\"', '"', $message);
		$message = preg_replace("#\[\*\]#", "</li><li> ", $message);
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
	 * Strips smilies from a string
 	 *
	 * @param string The message for smilies to be stripped from
	 * @return string The message with smilies stripped
	 */
	function strip_smilies($message)
	{
		if($this->smilies_cache == 0)
		{
			$this->cache_smilies();
		}
		if(is_array($this->smilies_cache))
		{
			$message = str_replace($this->smilies_cache, array_keys($this->smilies_cache), $message);
		}
		return $message;
	}

	/**
	 * Strips MyCode.
	 *
	 * @param string The message to be parsed
	 * @return string The parsed message.
	 */
	function strip_mycode($message, $options=array())
	{
		if($options['allow_html'] != "yes")
		{
			$options['allow_html'] = "no";
		}
		$options['allow_smilies'] = "no";
		$options['allow_mycode'] = "yes";
		$options['nl2br'] = "no";
		$options['filter_badwords'] = "no";
		$message = $this->parse_message($message, $options);
		$message = strip_tags($message);
		return $message;
	}
}
?>