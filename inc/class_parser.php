<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: class_parser.php 5710 2011-12-08 14:51:56Z Tomm $
 */

/*
options = array(
	allow_html
	allow_smilies
	allow_mycode
	nl2br
	filter_badwords
	me_username
	shorten_urls
	highlight
)
*/

class postParser
{
	/**
	 * Internal cache of MyCode.
	 *
	 * @access public
	 * @var mixed
	 */
	public $mycode_cache = 0;

	/**
	 * Internal cache of smilies
	 *
	 * @access public
	 * @var mixed
	 */
	public $smilies_cache = 0;

	/**
	 * Internal cache of badwords filters
	 *
	 * @access public
	 * @var mixed
	 */
	public $badwords_cache = 0;

	/**
	 * Base URL for smilies
	 *
	 * @access public
	 * @var string
	 */
	public $base_url;
	
	/**
	 * Parsed Highlights cache
	 *
	 * @access public
	 * @var array
	 */
	public $highlight_cache = array();
	
	/**
	 * Options for this parsed message (Private - set by parse_message argument)
	 *
	 * @access public
	 * @var array
	 */
	public $options;

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
		
		// Set the options		
		$this->options = $options;

		$message = $plugins->run_hooks("parse_message_start", $message);

		// Get rid of cartridge returns for they are the workings of the devil
		$message = str_replace("\r", "", $message);
		
		// Filter bad words if requested.
		if($this->options['filter_badwords'])
		{
			$message = $this->parse_badwords($message);
		}

		if($this->options['allow_html'] != 1)
		{
			$message = $this->parse_html($message);
		}
		else
		{		
			while(preg_match("#<script(.*)>(.*)</script(.*)>#is", $message))
			{
				$message = preg_replace("#<script(.*)>(.*)</script(.*)>#is", "&lt;script$1&gt;$2&lt;/script$3&gt;", $message);
			}

			$message = str_replace(array('<?php', '<!--', '-->', '?>', "<br />\n", "<br>\n"), array('&lt;?php', '&lt;!--', '--&gt;', '?&gt;', "\n", "\n"), $message);
		}
		
		// If MyCode needs to be replaced, first filter out [code] and [php] tags.
		if($this->options['allow_mycode'])
		{
			preg_match_all("#\[(code|php)\](.*?)\[/\\1\](\r\n?|\n?)#si", $message, $code_matches, PREG_SET_ORDER);
			$message = preg_replace("#\[(code|php)\](.*?)\[/\\1\](\r\n?|\n?)#si", "<mybb-code>\n", $message);
		}

		// Always fix bad Javascript in the message.
		$message = $this->fix_javascript($message);
		
		// Replace "me" code and slaps if we have a username
		if($this->options['me_username'])
		{
			global $lang;
			
			$message = preg_replace('#(>|^|\r|\n)/me ([^\r\n<]*)#i', "\\1<span style=\"color: red;\">* {$this->options['me_username']} \\2</span>", $message);
			$message = preg_replace('#(>|^|\r|\n)/slap ([^\r\n<]*)#i', "\\1<span style=\"color: red;\">* {$this->options['me_username']} {$lang->slaps} \\2 {$lang->with_trout}</span>", $message);
		}
		
		// If we can, parse smilies
		if($this->options['allow_smilies'])
		{
			$message = $this->parse_smilies($message, $this->options['allow_html']);
		}

		// Replace MyCode if requested.
		if($this->options['allow_mycode'])
		{
			$message = $this->parse_mycode($message, $this->options);
		}
		
		// Parse Highlights
		if($this->options['highlight'])
		{
			$message = $this->highlight_message($message, $this->options['highlight']);
		}

		// Run plugin hooks
		$message = $plugins->run_hooks("parse_message", $message);
		
		if($this->options['allow_mycode'])
		{
			// Now that we're done, if we split up any code tags, parse them and glue it all back together
			if(count($code_matches) > 0)
			{
				foreach($code_matches as $text)
				{
					// Fix up HTML inside the code tags so it is clean
					if($options['allow_html'] != 0)
					{
						$text[2] = $this->parse_html($text[2]);
					}

					if(my_strtolower($text[1]) == "code")
					{
						$code = $this->mycode_parse_code($text[2]);
					}
					elseif(my_strtolower($text[1]) == "php")
					{
						$code = $this->mycode_parse_php($text[2]);
					}
					$message = preg_replace("#\<mybb-code>\n?#", $code, $message, 1);
				}
			}
		}

		// Replace meta and base tags in our post - these are > dangerous <
		if($this->options['allow_html'])
		{
			$message = preg_replace_callback("#<((m[^a])|(b[^diloru>])|(s[^aemptu>]))(\s*[^>]*)>#si", create_function(
				'$matches',
				'return htmlspecialchars($matches[0]);'
			), $message);
		}

		if($options['nl2br'] !== 0)
		{
			$message = nl2br($message);
			// Fix up new lines and block level elements
			$message = preg_replace("#(</?(?:html|head|body|div|p|form|table|thead|tbody|tfoot|tr|td|th|ul|ol|li|div|p|blockquote|cite|hr)[^>]*>)\s*<br />#i", "$1", $message);
			$message = preg_replace("#(&nbsp;)+(</?(?:html|head|body|div|p|form|table|thead|tbody|tfoot|tr|td|th|ul|ol|li|div|p|blockquote|cite|hr)[^>]*>)#i", "$2", $message);
		}

		$message = my_wordwrap($message);
	
		$message = $plugins->run_hooks("parse_message_end", $message);
				
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
	private function cache_mycode()
	{
		global $cache, $lang;
		$this->mycode_cache = array();

		$standard_mycode['b']['regex'] = "#\[b\](.*?)\[/b\]#si";
        $standard_mycode['b']['replacement'] = "<span style=\"font-weight: bold;\">$1</span>";

        $standard_mycode['u']['regex'] = "#\[u\](.*?)\[/u\]#si";
        $standard_mycode['u']['replacement'] = "<span style=\"text-decoration: underline;\">$1</span>";

        $standard_mycode['i']['regex'] = "#\[i\](.*?)\[/i\]#si";
        $standard_mycode['i']['replacement'] = "<span style=\"font-style: italic;\">$1</span>";

		$standard_mycode['s']['regex'] = "#\[s\](.*?)\[/s\]#si";
		$standard_mycode['s']['replacement'] = "<del>$1</del>";

		$standard_mycode['copy']['regex'] = "#\(c\)#i";
		$standard_mycode['copy']['replacement'] = "&copy;";

		$standard_mycode['tm']['regex'] = "#\(tm\)#i";
		$standard_mycode['tm']['replacement'] = "&#153;";

		$standard_mycode['reg']['regex'] = "#\(r\)#i";
		$standard_mycode['reg']['replacement'] = "&reg;";

		$standard_mycode['url_simple']['regex'] = "#\[url\]([a-z]+?://)([^\r\n\"<]+?)\[/url\]#sei";
		$standard_mycode['url_simple']['replacement'] = "\$this->mycode_parse_url(\"$1$2\")";

		$standard_mycode['url_simple2']['regex'] = "#\[url\]([^\r\n\"<]+?)\[/url\]#ei";
		$standard_mycode['url_simple2']['replacement'] = "\$this->mycode_parse_url(\"$1\")";

		$standard_mycode['url_complex']['regex'] = "#\[url=([a-z]+?://)([^\r\n\"<]+?)\](.+?)\[/url\]#esi";
		$standard_mycode['url_complex']['replacement'] = "\$this->mycode_parse_url(\"$1$2\", \"$3\")";

		$standard_mycode['url_complex2']['regex'] = "#\[url=([^\r\n\"<&\(\)]+?)\](.+?)\[/url\]#esi";
		$standard_mycode['url_complex2']['replacement'] = "\$this->mycode_parse_url(\"$1\", \"$2\")";

		$standard_mycode['email_simple']['regex'] = "#\[email\](.*?)\[/email\]#ei";
		$standard_mycode['email_simple']['replacement'] = "\$this->mycode_parse_email(\"$1\")";

		$standard_mycode['email_complex']['regex'] = "#\[email=(.*?)\](.*?)\[/email\]#ei";
		$standard_mycode['email_complex']['replacement'] = "\$this->mycode_parse_email(\"$1\", \"$2\")";
		
		$standard_mycode['hr']['regex'] = "#\[hr\]#si";
		$standard_mycode['hr']['replacement'] = "<hr />";

		$nestable_mycode['color']['regex'] = "#\[color=([a-zA-Z]*|\#?[0-9a-fA-F]{6})](.*?)\[/color\]#si";
		$nestable_mycode['color']['replacement'] = "<span style=\"color: $1;\">$2</span>";

		$nestable_mycode['size']['regex'] = "#\[size=(xx-small|x-small|small|medium|large|x-large|xx-large)\](.*?)\[/size\]#si";
        $nestable_mycode['size']['replacement'] = "<span style=\"font-size: $1;\">$2</span>";

        $nestable_mycode['size_int']['regex'] = "#\[size=([0-9\+\-]+?)\](.*?)\[/size\]#esi";
        $nestable_mycode['size_int']['replacement'] = "\$this->mycode_handle_size(\"$1\", \"$2\")";

        $nestable_mycode['font']['regex'] = "#\[font=([a-z ]+?)\](.+?)\[/font\]#si";
        $nestable_mycode['font']['replacement'] = "<span style=\"font-family: $1;\">$2</span>";

        $nestable_mycode['align']['regex'] = "#\[align=(left|center|right|justify)\](.*?)\[/align\]#si";
        $nestable_mycode['align']['replacement'] = "<div style=\"text-align: $1;\">$2</div>";

		$custom_mycode = $cache->read("mycode");

		// If there is custom MyCode, load it.
		if(is_array($custom_mycode))
		{
			foreach($custom_mycode as $key => $mycode)
			{
				$mycode['regex'] = str_replace("\x0", "", $mycode['regex']);
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
			$this->mycode_cache['standard']['find'][] = $code['regex'];
			$this->mycode_cache['standard']['replacement'][] = $code['replacement'];
		}
		
		// Assign the nestable MyCode to the cache.
		foreach($nestable_mycode as $code)
		{
			$this->mycode_cache['nestable'][] = array('find' => $code['regex'], 'replacement' => $code['replacement']);
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
		
		$message = $this->mycode_auto_url($message);

		$message = str_replace('$', '&#36;', $message);
		
		// Replace the rest
		$message = preg_replace($this->mycode_cache['standard']['find'], $this->mycode_cache['standard']['replacement'], $message);
		
		// Replace the nestable mycode's
		foreach($this->mycode_cache['nestable'] as $mycode)
		{
			while(preg_match($mycode['find'], $message))
			{
				$message = preg_replace($mycode['find'], $mycode['replacement'], $message);
			}
		}

		// Special code requiring special attention
		while(preg_match("#\[list\](.*?)\[/list\]#esi", $message))
		{
			$message = preg_replace("#\s?\[list\](.*?)\[/list\](\r\n?|\n?)#esi", "\$this->mycode_parse_list('$1')\n", $message);
		}

		// Replace lists.
		while(preg_match("#\[list=(a|A|i|I|1)\](.*?)\[/list\](\r\n?|\n?)#esi", $message))
		{
			$message = preg_replace("#\s?\[list=(a|A|i|I|1)\](.*?)\[/list\]#esi", "\$this->mycode_parse_list('$2', '$1')\n", $message);
		}

		// Convert images when allowed.
		if($options['allow_imgcode'] != 0)
		{
			$message = preg_replace("#\[img\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#ise", "\$this->mycode_parse_img('$2')\n", $message);
			$message = preg_replace("#\[img=([0-9]{1,3})x([0-9]{1,3})\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#ise", "\$this->mycode_parse_img('$4', array('$1', '$2'));", $message);
			$message = preg_replace("#\[img align=([a-z]+)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#ise", "\$this->mycode_parse_img('$3', array(), '$1');", $message);
			$message = preg_replace("#\[img=([0-9]{1,3})x([0-9]{1,3}) align=([a-z]+)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#ise", "\$this->mycode_parse_img('$5', array('$1', '$2'), '$3');", $message);
		}
		
		// Convert videos when allow.
		if($options['allow_videocode'] != 0)
		{
			$message = preg_replace("#\[video=(.*?)\](.*?)\[/video\]#ei", "\$this->mycode_parse_video('$1', '$2');", $message);
		}

		return $message;
	}

	/**
	 * Generates a cache of smilies
	 *
	 * @access private
	 */
	private function cache_smilies()
	{
		global $cache, $mybb;
		$this->smilies_cache = array();

		$smilies = $cache->read("smilies");
		if(is_array($smilies))
		{
			foreach($smilies as $sid => $smilie)
			{
				if(defined("IN_ARCHIVE") && substr($smilie['image'], 0, 4) != "http")
				{
					// We're in the archive and not using an outside image, add in our address
					$smilie['image'] = $mybb->settings['bburl']."/".$smilie['image'];
				}

				$this->smilies_cache[$smilie['find']] = "<img src=\"{$smilie['image']}\" style=\"vertical-align: middle;\" border=\"0\" alt=\"{$smilie['name']}\" title=\"{$smilie['name']}\" />";
			}
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
	function parse_smilies($message, $allow_html=0)
	{
		if($this->smilies_cache == 0)
		{
			$this->cache_smilies();
		}
		
		$message = ' ' . $message . ' ';
		
		// First we take out any of the tags we don't want parsed between (url= etc)
		preg_match_all("#\[(url(=[^\]]*)?\]|quote=([^\]]*)?\])#i", $message, $bad_matches, PREG_PATTERN_ORDER);
		$message = preg_replace("#\[(url(=[^\]]*)?\]|quote=([^\]]*)?\])#si", "<mybb-bad-sm>", $message);
		
		// Impose a hard limit of 500 smilies per message as to not overload the parser
		$remaining = 500;

		if(is_array($this->smilies_cache))
		{
			foreach($this->smilies_cache as $find => $replace)
			{
				$orig_message = $message;
				$find = $this->parse_html($find);
				$find = preg_quote($find, "#");

				$replace = strip_tags($replace, "<img>");
				
				// Fix issues for smileys starting with a ";"
				$orig_find = $find;
				if(substr($find, 0, 1) == ";")
				{
					$find = "(?<!&gt|&lt|&amp)".$find;
				}
				
				$message = @preg_replace("#(?<=[^\"])".$find."(?=.\W|\"|\W.|\W$)#si", $replace, $message, $remaining, $replacements);
				
				if($message == null)
				{
					$message = preg_replace("#(?<=[^&;\"])".$orig_find."(?=.\W|\"|\W.|\W$)#si", $replace, $orig_message, $remaining);
				}
				
				$remaining -= $replacements;
				if($remaining <= 0)
				{
					break; // Reached the limit
				}
			}
			unset($orig_message, $orig_find);
		}

		// If we matched any tags previously, swap them back in
		if(count($bad_matches[0]) > 0)
		{
			foreach($bad_matches[0] as $match)
			{
				$match = str_replace('$', '\$', $match);
				$message = preg_replace("#<mybb-bad-sm>#", $match, $message, 1);
			}
		}

		return trim($message);
	}

	/**
	 * Generates a cache of badwords filters.
	 *
	 * @access private
	 */
	private function cache_badwords()
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
				if(!$badword['replacement'])
				{
					$badword['replacement'] = "*****";
				}
				
				// Take into account the position offset for our last replacement.
				$index = substr_count($badword['badword'], '*')+2;
				$badword['badword'] = str_replace('\*', '([a-zA-Z0-9_]{1})', preg_quote($badword['badword'], "#"));
				
				// Ensure we run the replacement enough times but not recursively (i.e. not while(preg_match..))
				$count = preg_match_all("#(^|\W)".$badword['badword']."(\W|$)#i", $message, $matches);
				for($i=0; $i < $count; ++$i)
				{
					$message = preg_replace("#(^|\W)".$badword['badword']."(\W|$)#i", "\\1".$badword['replacement'].'\\'.$index, $message);
				}
			}
		}
		if($options['strip_tags'] == 1)
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
		$js_array = array(
			"#(&\#(0*)106;?|&\#(0*)74;?|&\#x(0*)4a;?|&\#x(0*)6a;?|j)((&\#(0*)97;?|&\#(0*)65;?|a)(&\#(0*)118;?|&\#(0*)86;?|v)(&\#(0*)97;?|&\#(0*)65;?|a)(\s)?(&\#(0*)115;?|&\#(0*)83;?|s)(&\#(0*)99;?|&\#(0*)67;?|c)(&\#(0*)114;?|&\#(0*)82;?|r)(&\#(0*)105;?|&\#(0*)73;?|i)(&\#112;?|&\#(0*)80;?|p)(&\#(0*)116;?|&\#(0*)84;?|t)(&\#(0*)58;?|\:))#i",
			"#(o)(nmouseover\s?=)#i",
			"#(o)(nmouseout\s?=)#i",
			"#(o)(nmousedown\s?=)#i",
			"#(o)(nmousemove\s?=)#i",
			"#(o)(nmouseup\s?=)#i",
			"#(o)(nclick\s?=)#i",
			"#(o)(ndblclick\s?=)#i",
			"#(o)(nload\s?=)#i",
			"#(o)(nsubmit\s?=)#i",
			"#(o)(nblur\s?=)#i",
			"#(o)(nchange\s?=)#i",
			"#(o)(nfocus\s?=)#i",
			"#(o)(nselect\s?=)#i",
			"#(o)(nunload\s?=)#i",
			"#(o)(nkeypress\s?=)#i"
		);
		
		$message = preg_replace($js_array, "$1<strong></strong>$2$4", $message);

		return $message;
	}
	
	/**
	* Handles fontsize.
	*
	* @param string The original size.
	* @param string The text within a size tag.
	* @return string The parsed text.
	*/
	function mycode_handle_size($size, $text)
	{
		$size = intval($size)+10;

		if($size > 50)
		{
			$size = 50;
		}

		$text = "<span style=\"font-size: {$size}pt;\">".str_replace("\'", "'", $text)."</span>";

		return $text;
	}

	/**
	* Parses quote MyCode.
	*
	* @param string The message to be parsed
	* @param boolean Are we formatting as text?
	* @return string The parsed message.
	*/
	function mycode_parse_quotes($message, $text_only=false)
	{
		global $lang, $templates, $theme, $mybb;

		// Assign pattern and replace values.
		$pattern = array(
			"#\[quote=([\"']|&quot;|)(.*?)(?:\\1)(.*?)(?:[\"']|&quot;)?\](.*?)\[/quote\](\r\n?|\n?)#esi",
			"#\[quote\](.*?)\[\/quote\](\r\n?|\n?)#si"
		);

		if($text_only == false)
		{
			$replace = array(
				"\$this->mycode_parse_post_quotes('$4','$2$3')",
				"<blockquote><cite>$lang->quote</cite>$1</blockquote>\n"
			);
		}
		else
		{
			$replace = array(
				"\$this->mycode_parse_post_quotes('$4','$2$3', true)",
				"\n{$lang->quote}\n--\n$1\n--\n"
			);
		}

		do
		{
			$previous_message = $message;
			$message = preg_replace($pattern, $replace, $message, -1, $count);
		} while($count);

		if(!$message)
		{
			$message = $previous_message;
		}

		if($text_only == false)
		{
			$find = array(
				"#(\r\n*|\n*)<\/cite>(\r\n*|\n*)#",
				"#(\r\n*|\n*)<\/blockquote>#"
			);

			$replace = array(
				"</cite><br />",
				"</blockquote>"
			);
			$message = preg_replace($find, $replace, $message);
		}
		return $message;
	}
	
	/**
	* Parses quotes with post id and/or dateline.
	*
	* @param string The message to be parsed
	* @param string The username to be parsed
	* @param boolean Are we formatting as text?
	* @return string The parsed message.
	*/
	function mycode_parse_post_quotes($message, $username, $text_only=false)
	{
		global $lang, $templates, $theme, $mybb;

		$linkback = $date = "";

		$message = trim($message);
		$message = preg_replace("#(^<br(\s?)(\/?)>|<br(\s?)(\/?)>$)#i", "", $message);

		if(!$message) return '';

		$message = str_replace('\"', '"', $message);
		$username = str_replace('\"', '"', $username)."'";
		$delete_quote = true;

		preg_match("#pid=(?:&quot;|\"|')?([0-9]+)[\"']?(?:&quot;|\"|')?#i", $username, $match);
		if(intval($match[1]))
		{
			$pid = intval($match[1]);
			$url = $mybb->settings['bburl']."/".get_post_link($pid)."#pid$pid";
			if(defined("IN_ARCHIVE"))
			{
				$linkback = " <a href=\"{$url}\">[ -> ]</a>";
			}
			else
			{
				eval("\$linkback = \" ".$templates->get("postbit_gotopost", 1, 0)."\";");
			}
			
			$username = preg_replace("#(?:&quot;|\"|')? pid=(?:&quot;|\"|')?[0-9]+[\"']?(?:&quot;|\"|')?#i", '', $username);
			$delete_quote = false;
		}

		unset($match);
		preg_match("#dateline=(?:&quot;|\"|')?([0-9]+)(?:&quot;|\"|')?#i", $username, $match);
		if(intval($match[1]))
		{
			if($match[1] < TIME_NOW)
			{
				$postdate = my_date($mybb->settings['dateformat'], intval($match[1]));
				$posttime = my_date($mybb->settings['timeformat'], intval($match[1]));
				$date = " ({$postdate} {$posttime})";
			}
			$username = preg_replace("#(?:&quot;|\"|')? dateline=(?:&quot;|\"|')?[0-9]+(?:&quot;|\"|')?#i", '', $username);
			$delete_quote = false;
		}

		if($delete_quote)
		{
			$username = my_substr($username, 0, my_strlen($username)-1);
		}

		if($text_only)
		{
			return "\n".htmlspecialchars_uni($username)." $lang->wrote{$date}\n--\n{$message}\n--\n";
		}
		else
		{
			$span = "";
			if(!$delete_quote)
			{
				$span = "<span>{$date}</span>";
			}
			
			return "<blockquote><cite>{$span}".htmlspecialchars_uni($username)." $lang->wrote{$linkback}</cite>{$message}</blockquote>\n";
		}
	}

	/**
	* Parses code MyCode.
	*
	* @param string The message to be parsed
	* @param boolean Are we formatting as text?
	* @return string The parsed message.
	*/
	function mycode_parse_code($code, $text_only=false)
	{
		global $lang;

		if($text_only == true)
		{
			return "\n{$lang->code}\n--\n{$code}\n--\n";
		}

		// Clean the string before parsing.
		$code = preg_replace('#^(\t*)(\n|\r|\0|\x0B| )*#', '\\1', $code);
		$code = rtrim($code);
		$original = preg_replace('#^\t*#', '', $code);

		if(empty($original))
		{
			return;
		}

		$code = str_replace('$', '&#36;', $code);
		$code = preg_replace('#\$([0-9])#', '\\\$\\1', $code);
		$code = str_replace('\\', '&#92;', $code);
		$code = str_replace("\t", '&nbsp;&nbsp;&nbsp;&nbsp;', $code);
		$code = str_replace("  ", '&nbsp;&nbsp;', $code);

		return "<div class=\"codeblock\">\n<div class=\"title\">".$lang->code."\n</div><div class=\"body\" dir=\"ltr\"><code>".$code."</code></div></div>\n";
	}

	/**
	* Parses PHP code MyCode.
	*
	* @param string The message to be parsed
	* @param boolean Whether or not it should return it as pre-wrapped in a div or not.
	* @param boolean Are we formatting as text?
	* @return string The parsed message.
	*/
	function mycode_parse_php($str, $bare_return = false, $text_only = false)
	{
		global $lang;

		if($text_only == true)
		{
			return "\n{$lang->php_code}\n--\n$str\n--\n";
		}

		// Clean the string before parsing except tab spaces.
		$str = preg_replace('#^(\t*)(\n|\r|\0|\x0B| )*#', '\\1', $str);
		$str = rtrim($str);

		$original = preg_replace('#^\t*#', '', $str);

		if(empty($original))
		{
			return;
		}

		$str = str_replace('&amp;', '&', $str);
		$str = str_replace('&lt;', '<', $str);
		$str = str_replace('&gt;', '>', $str);

		// See if open and close tags are provided.
		$added_open_tag = false;
		if(!preg_match("#^\s*<\?#si", $str))
		{
			$added_open_tag = true;
			$str = "<?php \n".$str;
		}

		$added_end_tag = false;
		if(!preg_match("#\?>\s*$#si", $str))
		{
			$added_end_tag = true;
			$str = $str." \n?>";
		}

		$code = @highlight_string($str, true);

		// Do the actual replacing.
		$code = preg_replace('#<code>\s*<span style="color: \#000000">\s*#i', "<code>", $code);
		$code = preg_replace("#</span>\s*</code>#", "</code>", $code);
		$code = preg_replace("#</span>(\r\n?|\n?)</code>#", "</span></code>", $code);
		$code = str_replace("\\", '&#092;', $code);
		$code = str_replace('$', '&#36;', $code);
		$code = preg_replace("#&amp;\#([0-9]+);#si", "&#$1;", $code);

		if($added_open_tag)
		{
			$code = preg_replace("#<code><span style=\"color: \#([A-Z0-9]{6})\">&lt;\?php( |&nbsp;)(<br />?)#", "<code><span style=\"color: #$1\">", $code);
		}

		if($added_end_tag)
		{
			$code = str_replace("?&gt;</span></code>", "</span></code>", $code);
			// Wait a minute. It fails highlighting? Stupid highlighter.
			$code = str_replace("?&gt;</code>", "</code>", $code);
		}

		$code = preg_replace("#<span style=\"color: \#([A-Z0-9]{6})\"></span>#", "", $code);
		$code = str_replace("<code>", "<div dir=\"ltr\"><code>", $code);
		$code = str_replace("</code>", "</code></div>", $code);
		$code = preg_replace("# *$#", "", $code);

		if($bare_return)
		{
			return $code;
		}

		// Send back the code all nice and pretty
		return "<div class=\"codeblock phpcodeblock\"><div class=\"title\">$lang->php_code\n</div><div class=\"body\">".$code."</div></div>\n";
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
		if(!preg_match("#^[a-z0-9]+://#i", $url))
		{
			$url = "http://".$url;
		}
		$fullurl = $url;

		$url = str_replace('&amp;', '&', $url);
		$name = str_replace('&amp;', '&', $name);
		
		if(!$name)
		{
			$name = $url;
		}
		
		$name = str_replace("\'", "'", $name);
		$url = str_replace("\'", "'", $url);
		$fullurl = str_replace("\'", "'", $fullurl);
		
		if($name == $url && (!isset($this->options['shorten_urls']) || $this->options['shorten_urls'] != 0))
		{
			if(my_strlen($url) > 55)
			{
				$name = my_substr($url, 0, 40)."...".my_substr($url, -10);
			}
		}

		$nofollow = '';
		if(isset($this->options['nofollow_on']))
		{
			$nofollow = " rel=\"nofollow\"";
		}

		// Fix some entities in URLs
		$entities = array('$' => '%24', '&#36;' => '%24', '^' => '%5E', '`' => '%60', '[' => '%5B', ']' => '%5D', '{' => '%7B', '}' => '%7D', '"' => '%22', '<' => '%3C', '>' => '%3E', ' ' => '%20');
		$fullurl = str_replace(array_keys($entities), array_values($entities), $fullurl);

		$name = preg_replace("#&amp;\#([0-9]+);#si", "&#$1;", $name); // Fix & but allow unicode
		$link = "<a href=\"$fullurl\" target=\"_blank\"{$nofollow}>$name</a>";
		return $link;
	}

	/**
	 * Parses IMG MyCode.
	 *
	 * @param string The URL to the image
	 * @param array Optional array of dimensions
	 */
	function mycode_parse_img($url, $dimensions=array(), $align='')
	{
		global $lang;
		$url = trim($url);
		$url = str_replace("\n", "", $url);
		$url = str_replace("\r", "", $url);
		if($align == "right")
		{
			$css_align = " style=\"float: right;\"";
		}
		else if($align == "left")
		{
			$css_align = " style=\"float: left;\"";
		}
		$alt = htmlspecialchars_uni(basename($url));
		if(my_strlen($alt) > 55)
		{
			$alt = my_substr($alt, 0, 40)."...".my_substr($alt, -10);
		}
		$alt = $lang->sprintf($lang->posted_image, $alt);
		if($dimensions[0] > 0 && $dimensions[1] > 0)
		{
			return "<img src=\"{$url}\" width=\"{$dimensions[0]}\" height=\"{$dimensions[1]}\" border=\"0\" alt=\"{$alt}\"{$css_align} />";
		}
		else
		{
			return "<img src=\"{$url}\" border=\"0\" alt=\"{$alt}\"{$css_align} />";			
		}
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
		$name = str_replace("\\'", "'", $name);
		$email = str_replace("\\'", "'", $email);
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
	
	function mycode_parse_video($video, $url)
	{
		global $templates;
		
		if(empty($video) || empty($url))
		{
			return "[video={$video}]{$url}[/video]";
		}
		
		$parsed_url = @parse_url(urldecode($url));
		if($parsed_url == false)
		{
			return "[video={$video}]{$url}[/video]";;
		}
		
		$fragments = array();
		if($parsed_url['fragment'])
		{
			$fragments = explode("&", $parsed_url['fragment']);
		}
		
		$queries = explode("&", $parsed_url['query']);
		
		$input = array();
		foreach($queries as $query)
		{
			list($key, $value) = explode("=", $query);
			$key = str_replace("amp;", "", $key);
			$input[$key] = $value;
		}
		
		$path = explode('/', $parsed_url['path']);
		
		switch($video)
		{
			case "dailymotion":
				list($id, ) = split("_", $path[2], 1); // http://www.dailymotion.com/video/fds123_title-goes-here
				break;
			case "metacafe":
				$id = $path[2]; // http://www.metacafe.com/watch/fds123/title_goes_here/
				$title = htmlspecialchars_uni($path[3]);
				break;
			case "myspacetv":
				$id = $input['videoid']; // http://myspacetv.com/index.cfm?fuseaction=vids.individual&videoid=fds123
				break;
			case "yahoo":
				$id = $path[3]; // http://video.yahoo.com/watch/fds123/abc567
				$vid = htmlspecialchars_uni($path[2]);
				break;
			case "vimeo":
				$id = $path[1]; // http://vimeo.com/fds123
				break;
			case "youtube":
				if($fragments[0])
				{
					$id = str_replace('!v=', '', $fragments[0]); // http://www.youtube.com/watch#!v=fds123
				}
				elseif($input['v'])
				{
					$id = $input['v']; // http://www.youtube.com/watch?v=fds123
				}
				else
				{
					$id = $path[1]; // http://www.youtu.be/fds123
				}
				break;
			default:
				return "[video={$video}]{$url}[/video]";
		}

		if(empty($id) || ($video == "yahoo" && empty($vid)))
		{
			return "[video={$video}]{$url}[/video]";
		}
		
		$id = htmlspecialchars_uni($id);
		
		eval("\$video_code = \"".$templates->get("video_{$video}_embed")."\";");
		
		return $video_code;
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
		$message = preg_replace("#([\>\s\(\)])(http|https|ftp|news){1}://([^\/\"\s\<\[\.]+\.([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/[^\"\s<\[]*)?)#i", "$1[url]$2://$3[/url]", $message);
		$message = preg_replace("#([\>\s\(\)])(www|ftp)\.(([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/[^\"\s<\[]*)?)#i", "$1[url]$2.$3[/url]", $message);
		$message = my_substr($message, 1);
		
		return $message;
	}

	/**
	* Parses list MyCode.
	*
	* @param string The message to be parsed
	* @param string The list type
	* @param boolean Are we formatting as text?
	* @return string The parsed message.
	*/
	function mycode_parse_list($message, $type="")
	{
		$message = str_replace('\"', '"', $message);
		$message = preg_replace("#\s*\[\*\]\s*#", "</li>\n<li>", $message);
		$message .= "</li>";

		if($type)
		{
			$list = "\n<ol type=\"$type\">$message</ol>\n";
		}
		else
		{
			$list = "<ul>$message</ul>\n";
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
	 * Highlights a string
 	 *
	 * @param string The message to be highligted
	 * @param string The highlight keywords
	 * @return string The message with highlight bbcodes
	 */
	function highlight_message($message, $highlight)
	{
		if(empty($this->highlight_cache))
		{
			$this->highlight_cache = build_highlight_array($highlight);
		}
		
		if(is_array($this->highlight_cache) && !empty($this->highlight_cache))
		{
			$message = preg_replace(array_keys($this->highlight_cache), $this->highlight_cache, $message);
		}
		
		return $message;
	}

	/**
	 * Parses message to plain text equivalents of MyCode.
	 *
	 * @param string The message to be parsed
	 * @return string The parsed message.
	 */
	function text_parse_message($message, $options=array())
	{
		global $plugins;
		
		// Filter bad words if requested.
		if($options['filter_badwords'] != 0)
		{
			$message = $this->parse_badwords($message);
		}

		// Parse quotes first
		$message = $this->mycode_parse_quotes($message, true);

		$find = array(
			"#\[(b|u|i|s|url|email|color|img)\](.*?)\[/\\1\]#is",
			"#\[php\](.*?)\[/php\](\r\n?|\n?)#ise",
			"#\[code\](.*?)\[/code\](\r\n?|\n?)#ise",
			"#\[img=([0-9]{1,3})x([0-9]{1,3})\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is",
			"#\[url=([a-z]+?://)([^\r\n\"<]+?)\](.+?)\[/url\]#si",
			"#\[url=([^\r\n\"<&\(\)]+?)\](.+?)\[/url\]#si",
		);
		
		$replace = array(
			"$2",
			"\$this->mycode_parse_php('$1', false, true)",
			"\$this->mycode_parse_code('$1', true)",
			"$4",
			"$3 ($1$2)",
			"$2 ($1)",
		);
		$message = preg_replace($find, $replace, $message);
		
		// Replace "me" code and slaps if we have a username
		if($options['me_username'])
		{
			global $lang;
			
			$message = preg_replace('#(>|^|\r|\n)/me ([^\r\n<]*)#i', "\\1* {$options['me_username']} \\2", $message);
			$message = preg_replace('#(>|^|\r|\n)/slap ([^\r\n<]*)#i', "\\1* {$options['me_username']} {$lang->slaps} \\2 {$lang->with_trout}", $message);
		}

		// Special code requiring special attention
		while(preg_match("#\[list\](.*?)\[/list\]#esi", $message))
		{
			$message = preg_replace("#\s?\[list\](.*?)\[/list\](\r\n?|\n?)#esi", "\$this->mycode_parse_list('$1')\n", $message);
		}

		// Replace lists.
		while(preg_match("#\[list=(a|A|i|I|1)\](.*?)\[/list\](\r\n?|\n?)#esi", $message))
		{
			$message = preg_replace("#\s?\[list=(a|A|i|I|1)\](.*?)\[/list\]#esi", "\$this->mycode_parse_list('$2', '$1')\n", $message);
		}

		// Run plugin hooks
		$message = $plugins->run_hooks("text_parse_message", $message);
		
		return $message;
	}
}
?>