<?php
/**
 * MyBB 1.8
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybb.com
 * License: http://www.mybb.com/about/license
 *
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
	filter_cdata
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
	 * Options for this parsed message
	 *
	 * @access public
	 * @var array
	 */
	public $options;

	/**
	 * Internal cache for nested lists
	 *
	 * @access public
	 * @var array
	 */
	public $list_elements;

	/**
	 * Internal counter for nested lists
	 *
	 * @access public
	 * @var int
	 */
	public $list_count;

	/**
	 * Whether or not should a <br /> with clear: both be added at the end of the parsed message
	 *
	 * @access public
	 * @var boolean
	 */
	public $clear_needed = false;

	/**
	 * Parses a message with the specified options.
	 *
	 * @param string $message The message to be parsed.
	 * @param array $options Array of yes/no options - allow_html,filter_badwords,allow_mycode,allow_smilies,nl2br,me_username,filter_cdata.
	 * @return string The parsed message.
	 */
	function parse_message($message, $options=array())
	{
		global $plugins, $mybb;

		$this->clear_needed = false;

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

		// Get rid of carriage returns for they are the workings of the devil
		$message = str_replace("\r", "", $message);

		// Filter bad words if requested.
		if(!empty($this->options['filter_badwords']))
		{
			$message = $this->parse_badwords($message);
		}

		// Filter CDATA tags if requested (syndication.php).
		if(!empty($this->options['filter_cdata']))
		{
			$message = $this->parse_cdata($message);
		}

		// If MyCode needs to be replaced, first filter out [code] and [php] tags.
		$code_matches = array();
		if(!empty($this->options['allow_mycode']) && $mybb->settings['allowcodemycode'] == 1)
		{
			// This code is reserved and could break codes
			$message = str_replace("<mybb-code>\n", "<mybb_code>\n", $message);

			preg_match_all("#\[(code|php)\](.*?)(\[/\\1\])+(\r\n?|\n?)#si", $message, $code_matches, PREG_SET_ORDER);
			foreach($code_matches as $point => $part)
			{
				if(isset($part[3]))
				{
					$part[1] = "[".$part[1]."]";
					$code_matches[$point][2] = substr_replace($part[0], "", strrpos($part[0], $part[3]), strlen($part[3]));
					$code_matches[$point][2] = substr_replace($code_matches[$point][2], "", strpos($code_matches[$point][2], $part[1]), strlen($part[1]));
				}
			}
			$message = preg_replace("#\[(code|php)\](.*?)(\[/\\1\])+(\r\n?|\n?)#si", "<mybb-code>\n", $message);
		}

		if(empty($this->options['allow_html']))
		{
			$message = $this->parse_html($message);
			$message = str_replace("&lt;mybb-code&gt;\n", "<mybb-code>\n", $message);
		}
		else
		{
			// Replace base, meta,script and style tags in our post - these are > dangerous <
			$message = preg_replace('#<(/?)(base|meta|script|style)([^>]*)>#i', '&lt;$1$2$3&gt;', $message);
			$message = $this->fix_javascript($message);

			$find = array("<br />\n", "<br>\n");
			$replace = array("\n", "\n");
			$message = str_replace($find, $replace, $message);
		}

		$message = $plugins->run_hooks("parse_message_htmlsanitized", $message);

		// Replace "me" code and slaps if we have a username
		if(!empty($this->options['me_username']) && $mybb->settings['allowmemycode'] == 1)
		{
			global $lang;

			$message = preg_replace('#(>|^|\r|\n)/me ([^\r\n<]*)#i', "\\1<span style=\"color: red;\" class=\"mycode_me\">* {$this->options['me_username']} \\2</span>", $message);
			$message = preg_replace('#(>|^|\r|\n)/slap ([^\r\n<]*)#i', "\\1<span style=\"color: red;\" class=\"mycode_slap\">* {$this->options['me_username']} {$lang->slaps} \\2 {$lang->with_trout}</span>", $message);
		}

		$message = $plugins->run_hooks("parse_message_me_mycode", $message);

		// If we can, parse smilies
		if(!empty($this->options['allow_smilies']))
		{
			$message = $this->parse_smilies($message, $this->options['allow_html']);
		}

		// Replace MyCode if requested.
		if(!empty($this->options['allow_mycode']))
		{
			$message = $this->parse_mycode($message);
		}

		// Filter url codes, if disabled.
		if($mybb->settings['allowlinkmycode'] != 1)
		{
			$message = preg_replace("#\[(\/)?url{1}(.*?)\]#i", "", $message);
		}

		// Parse Highlights
		if(!empty($this->options['highlight']))
		{
			$message = $this->highlight_message($message, $this->options['highlight']);
		}

		// Run plugin hooks
		$message = $plugins->run_hooks("parse_message", $message);

		if(!empty($this->options['allow_mycode']))
		{
			// Now that we're done, if we split up any code tags, parse them and glue it all back together
			if(count($code_matches) > 0)
			{
				foreach($code_matches as $text)
				{
					if(my_strtolower($text[1]) == "code")
					{
						// Fix up HTML inside the code tags so it is clean
						$text[2] = $this->parse_html($text[2]);

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

		if(!isset($this->options['nl2br']) || $this->options['nl2br'] != 0)
		{
			$message = nl2br($message);
			// Fix up new lines and block level elements
			$message = preg_replace("#(</?(?:html|head|body|div|p|form|table|thead|tbody|tfoot|tr|td|th|ul|ol|li|div|p|blockquote|cite|hr)[^>]*>)\s*<br />#i", "$1", $message);
			$message = preg_replace("#(&nbsp;)+(</?(?:html|head|body|div|p|form|table|thead|tbody|tfoot|tr|td|th|ul|ol|li|div|p|blockquote|cite|hr)[^>]*>)#i", "$2", $message);
		}

		if($this->clear_needed)
		{
			$message .= '<br class="clear" />';
		}

		$message = $plugins->run_hooks("parse_message_end", $message);

		return $message;
	}

	/**
	 * Converts HTML in a message to their specific entities whilst allowing unicode characters.
	 *
	 * @param string $message The message to be parsed.
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
		global $cache, $lang, $mybb;
		$this->mycode_cache = array();

		$standard_mycode = $callback_mycode = $nestable_mycode = array();
		$standard_count = $callback_count = $nestable_count = 0;

		if($mybb->settings['allowbasicmycode'] == 1)
		{
			$standard_mycode['b']['regex'] = "#\[b\](.*?)\[/b\]#si";
			$standard_mycode['b']['replacement'] = "<span style=\"font-weight: bold;\" class=\"mycode_b\">$1</span>";

			$standard_mycode['u']['regex'] = "#\[u\](.*?)\[/u\]#si";
			$standard_mycode['u']['replacement'] = "<span style=\"text-decoration: underline;\" class=\"mycode_u\">$1</span>";

			$standard_mycode['i']['regex'] = "#\[i\](.*?)\[/i\]#si";
			$standard_mycode['i']['replacement'] = "<span style=\"font-style: italic;\" class=\"mycode_i\">$1</span>";

			$standard_mycode['s']['regex'] = "#\[s\](.*?)\[/s\]#si";
			$standard_mycode['s']['replacement'] = "<span style=\"text-decoration: line-through;\" class=\"mycode_s\">$1</span>";

			$standard_mycode['hr']['regex'] = "#\[hr\]#si";
			$standard_mycode['hr']['replacement'] = "<hr class=\"mycode_hr\" />";

			++$standard_count;
		}

		if($mybb->settings['allowsymbolmycode'] == 1)
		{
			$standard_mycode['copy']['regex'] = "#\(c\)#i";
			$standard_mycode['copy']['replacement'] = "&copy;";

			$standard_mycode['tm']['regex'] = "#\(tm\)#i";
			$standard_mycode['tm']['replacement'] = "&#153;";

			$standard_mycode['reg']['regex'] = "#\(r\)#i";
			$standard_mycode['reg']['replacement'] = "&reg;";

			++$standard_count;
		}

		if($mybb->settings['allowlinkmycode'] == 1)
		{
			$callback_mycode['url_simple']['regex'] = "#\[url\]((?!javascript)[a-z]+?://)([^\r\n\"<]+?)\[/url\]#si";
			$callback_mycode['url_simple']['replacement'] = array($this, 'mycode_parse_url_callback1');

			$callback_mycode['url_simple2']['regex'] = "#\[url\]((?!javascript:)[^\r\n\"<]+?)\[/url\]#i";
			$callback_mycode['url_simple2']['replacement'] = array($this, 'mycode_parse_url_callback2');

			$callback_mycode['url_complex']['regex'] = "#\[url=((?!javascript)[a-z]+?://)([^\r\n\"<]+?)\](.+?)\[/url\]#si";
			$callback_mycode['url_complex']['replacement'] = array($this, 'mycode_parse_url_callback1');

			$callback_mycode['url_complex2']['regex'] = "#\[url=((?!javascript:)[^\r\n\"<]+?)\](.+?)\[/url\]#si";
			$callback_mycode['url_complex2']['replacement'] = array($this, 'mycode_parse_url_callback2');

			++$callback_count;
		}

		if($mybb->settings['allowemailmycode'] == 1)
		{
			$callback_mycode['email_simple']['regex'] = "#\[email\]((?:[a-zA-Z0-9-_\+\.]+?)@[a-zA-Z0-9-]+\.[a-zA-Z0-9\.-]+(?:\?.*?)?)\[/email\]#i";
			$callback_mycode['email_simple']['replacement'] = array($this, 'mycode_parse_email_callback');

			$callback_mycode['email_complex']['regex'] = "#\[email=((?:[a-zA-Z0-9-_\+\.]+?)@[a-zA-Z0-9-]+\.[a-zA-Z0-9\.-]+(?:\?.*?)?)\](.*?)\[/email\]#i";
			$callback_mycode['email_complex']['replacement'] = array($this, 'mycode_parse_email_callback');

			++$callback_count;
		}

		if($mybb->settings['allowcolormycode'] == 1)
		{
			$nestable_mycode['color']['regex'] = "#\[color=([a-zA-Z]*|\#?[\da-fA-F]{3}|\#?[\da-fA-F]{6})](.*?)\[/color\]#si";
			$nestable_mycode['color']['replacement'] = "<span style=\"color: $1;\" class=\"mycode_color\">$2</span>";

			++$nestable_count;
		}

		if($mybb->settings['allowsizemycode'] == 1)
		{
			$nestable_mycode['size']['regex'] = "#\[size=(xx-small|x-small|small|medium|large|x-large|xx-large)\](.*?)\[/size\]#si";
			$nestable_mycode['size']['replacement'] = "<span style=\"font-size: $1;\" class=\"mycode_size\">$2</span>";

			$callback_mycode['size_int']['regex'] = "#\[size=([0-9\+\-]+?)\](.*?)\[/size\]#si";
			$callback_mycode['size_int']['replacement'] = array($this, 'mycode_handle_size_callback');

			++$nestable_count;
			++$callback_count;
		}

		if($mybb->settings['allowfontmycode'] == 1)
		{
			$nestable_mycode['font']['regex'] = "#\[font=([a-z0-9 ,\-_'\"]+)\](.*?)\[/font\]#si";
			$nestable_mycode['font']['replacement'] = "<span style=\"font-family: $1;\" class=\"mycode_font\">$2</span>";

			++$nestable_count;
		}

		if($mybb->settings['allowalignmycode'] == 1)
		{
			$nestable_mycode['align']['regex'] = "#\[align=(left|center|right|justify)\](.*?)\[/align\]#si";
			$nestable_mycode['align']['replacement'] = "<div style=\"text-align: $1;\" class=\"mycode_align\">$2</div>";

			++$nestable_count;
		}

		$custom_mycode = $cache->read("mycode");

		// If there is custom MyCode, load it.
		if(is_array($custom_mycode))
		{
			foreach($custom_mycode as $key => $mycode)
			{
				$mycode['regex'] = str_replace("\x0", "", $mycode['regex']);
				$custom_mycode[$key]['regex'] = "#".$mycode['regex']."#si";

				++$standard_count;
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

		// Assign the nestable MyCode to the cache.
		foreach($callback_mycode as $code)
		{
			$this->mycode_cache['callback'][] = array('find' => $code['regex'], 'replacement' => $code['replacement']);
		}

		$this->mycode_cache['standard_count'] = $standard_count;
		$this->mycode_cache['callback_count'] = $callback_count;
		$this->mycode_cache['nestable_count'] = $nestable_count;
	}

	/**
	 * Parses MyCode tags in a specific message with the specified options.
	 *
	 * @param string $message The message to be parsed.
	 * @param array $options Array of options in yes/no format. Options are allow_imgcode.
	 * @return string The parsed message.
	 */
	function parse_mycode($message, $options=array())
	{
		global $lang, $mybb;

		if(empty($this->options))
		{
			$this->options = $options;
		}

		// Cache the MyCode globally if needed.
		if($this->mycode_cache == 0)
		{
			$this->cache_mycode();
		}

		// Parse quotes first
		$message = $this->mycode_parse_quotes($message);

		// Convert images when allowed.
		if(!empty($this->options['allow_imgcode']))
		{
			$message = preg_replace_callback("#\[img\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is", array($this, 'mycode_parse_img_callback1'), $message);
			$message = preg_replace_callback("#\[img=([1-9][0-9]*)x([1-9][0-9]*)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is", array($this, 'mycode_parse_img_callback2'), $message);
			$message = preg_replace_callback("#\[img align=(left|right)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is", array($this, 'mycode_parse_img_callback3'), $message);
			$message = preg_replace_callback("#\[img=([1-9][0-9]*)x([1-9][0-9]*) align=(left|right)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is", array($this, 'mycode_parse_img_callback4'), $message);
		}
		else
		{
			$message = preg_replace_callback("#\[img\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is", array($this, 'mycode_parse_img_disabled_callback1'), $message);
			$message = preg_replace_callback("#\[img=([1-9][0-9]*)x([1-9][0-9]*)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is", array($this, 'mycode_parse_img_disabled_callback2'), $message);
			$message = preg_replace_callback("#\[img align=(left|right)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is", array($this, 'mycode_parse_img_disabled_callback3'), $message);
			$message = preg_replace_callback("#\[img=([1-9][0-9]*)x([1-9][0-9]*) align=(left|right)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is", array($this, 'mycode_parse_img_disabled_callback4'), $message);
		}

		// Convert videos when allow.
		if(!empty($this->options['allow_videocode']))
		{
			$message = preg_replace_callback("#\[video=(.*?)\](.*?)\[/video\]#i", array($this, 'mycode_parse_video_callback'), $message);
		}
		else
		{
			$message = preg_replace_callback("#\[video=(.*?)\](.*?)\[/video\]#i", array($this, 'mycode_parse_video_disabled_callback'), $message);
		}

		$message = str_replace('$', '&#36;', $message);

		// Replace the rest
		if($this->mycode_cache['standard_count'] > 0)
		{
			$message = preg_replace($this->mycode_cache['standard']['find'], $this->mycode_cache['standard']['replacement'], $message);
		}

		if($this->mycode_cache['callback_count'] > 0)
		{
			foreach($this->mycode_cache['callback'] as $replace)
			{
				$message = preg_replace_callback($replace['find'], $replace['replacement'], $message);
			}
		}

		// Replace the nestable mycode's
		if($this->mycode_cache['nestable_count'] > 0)
		{
			foreach($this->mycode_cache['nestable'] as $mycode)
			{
				while(preg_match($mycode['find'], $message))
				{
					$message = preg_replace($mycode['find'], $mycode['replacement'], $message);
				}
			}
		}

		// Reset list cache
		if($mybb->settings['allowlistmycode'] == 1)
		{
			$this->list_elements = array();
			$this->list_count = 0;

			// Find all lists
			$message = preg_replace_callback("#(\[list(=(a|A|i|I|1))?\]|\[/list\])#si", array($this, 'mycode_prepare_list'), $message);

			// Replace all lists
			for($i = $this->list_count; $i > 0; $i--)
			{
				// Ignores missing end tags
				$message = preg_replace_callback("#\s?\[list(=(a|A|i|I|1))?&{$i}\](.*?)(\[/list&{$i}\]|$)(\r\n?|\n?)#si", array($this, 'mycode_parse_list_callback'), $message, 1);
			}
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
		global $cache, $mybb, $theme, $templates;
		$this->smilies_cache = array();

		$smilies = $cache->read("smilies");
		if(is_array($smilies))
		{
			$extra_class = $onclick = '';
			foreach($smilies as $sid => $smilie)
			{
				$smilie['find'] = explode("\n", $smilie['find']);
				$smilie['image'] = str_replace("{theme}", $theme['imgdir'], $smilie['image']);
				$smilie['image'] = htmlspecialchars_uni($mybb->get_asset_url($smilie['image']));
				$smilie['name'] = htmlspecialchars_uni($smilie['name']);

				foreach($smilie['find'] as $s)
				{
					$s = $this->parse_html($s);
					eval("\$smilie_template = \"".$templates->get("smilie", 1, 0)."\";");
					$this->smilies_cache[$s] = $smilie_template;
					// workaround for smilies starting with ;
					if($s[0] == ";")
					{
						$this->smilies_cache += array(
							"&amp$s" => "&amp$s",
							"&lt$s" => "&lt$s",
							"&gt$s" => "&gt$s",
						);
					}
				}
			}
		}
	}

	/**
	 * Parses smilie code in the specified message.
	 *
	 * @param string $message $message The message being parsed.
	 * @param int $allow_html not used
	 * @return string The parsed message.
	 */
	function parse_smilies($message, $allow_html=0)
	{
		if($this->smilies_cache == 0)
		{
			$this->cache_smilies();
		}

		// No smilies?
		if(!count($this->smilies_cache))
		{
			return $message;
		}

		// First we take out any of the tags we don't want parsed between (url= etc)
		preg_match_all("#\[(url(=[^\]]*)?\]|quote=([^\]]*)?\])|(http|ftp)(s|)://[^\s]*#i", $message, $bad_matches, PREG_PATTERN_ORDER);
		if(count($bad_matches[0]) > 0)
		{
			$message = preg_replace("#\[(url(=[^\]]*)?\]|quote=([^\]]*)?\])|(http|ftp)(s|)://[^\s]*#si", "<mybb-bad-sm>", $message);
		}

		$message = strtr($message, $this->smilies_cache);

		// If we matched any tags previously, swap them back in
		if(count($bad_matches[0]) > 0)
		{
			$message = explode("<mybb-bad-sm>", $message);
			$i = 0;
			foreach($bad_matches[0] as $match)
			{
				$message[$i] .= $match;
				$i++;
			}
			$message = implode("", $message);
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
	 * @param string $message The message to be parsed.
	 * @param array $options Array of parser options in yes/no format.
	 * @return string The parsed message.
	 */
	function parse_badwords($message, $options=array())
	{
		if(empty($this->options))
		{
			$this->options = $options;
		}

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

				if(!$badword['regex'])
				{
					$badword['badword'] = $this->generate_regex($badword['badword']);
				}

				$message = preg_replace('#'.$badword['badword'].'#is', $badword['replacement'], $message);
			}
		}
		if(!empty($this->options['strip_tags']))
		{
			$message = strip_tags($message);
		}
		return $message;
	}

	/**
	 * Generates REGEX patterns based on user defined badword string.
	 *
	 * @param string $badword The word defined to replace.
	 * @return string The regex pattern to match the word or null on error.
	 */
	function generate_regex($bad_word = "")
	{
		if($bad_word == "")
		{
			return;
		}

		// Neutralize multiple adjacent wildcards and generate pattern
		$ptrn = array('/\*\++/', '/\++\*/', '/\*+/');
		$rplc = array('*', '*', '[^\s\n]*');
		$bad_word = preg_replace($ptrn, $rplc, $bad_word);
		
		// Count + and generate pattern
		$bad_word = explode('+', $bad_word);
		$trap = "";
		$plus = 0;
		foreach($bad_word as $bad_piece)
		{
			if($bad_piece)
			{
				$trap .= $plus ? '[^\s\n]{'.$plus.'}'.$bad_piece : $bad_piece;
				$plus = 1;
			}
			else
			{
				$plus++;
			}
		}
		
		// Handle trailing +
		if($plus > 1)
		{
			$trap .= '[^\s\n]{'.($plus-1).'}';
		}
		
		return '\b'.$trap.'\b';
	}

	/**
	 * Resolves nested CDATA tags in the specified message.
	 *
	 * @param string $message The message to be parsed.
	 * @return string The parsed message.
	 */
	function parse_cdata($message)
	{
		$message = str_replace(']]>', ']]]]><![CDATA[>', $message);

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
			"#([\s\"']on)([a-z]+\s*=)#i",
		);

		// Add invisible white space
		$message = preg_replace($js_array, "$1\xE2\x80\x8C$2$6", $message);

		return $message;
	}

	/**
	* Handles fontsize.
	*
	* @param int $size The original size.
	* @param string $text The text within a size tag.
	* @return string The parsed text.
	*/
	function mycode_handle_size($size, $text)
	{
		global $templates;

		$size = (int)$size;

		if($size < 1)
		{
			$size = 1;
		}

		if($size > 50)
		{
			$size = 50;
		}

		$text = str_replace("\'", "'", $text);

		eval("\$mycode_size = \"".$templates->get("mycode_size_int", 1, 0)."\";");
		return $mycode_size;
	}

	/**
	* Handles fontsize.
	*
	* @param array $matches Matches.
	* @return string The parsed text.
	*/
	function mycode_handle_size_callback($matches)
	{
		return $this->mycode_handle_size($matches[1], $matches[2]);
	}

	/**
	* Parses quote MyCode.
	*
	* @param string $message The message to be parsed
	* @param boolean $text_only Are we formatting as text?
	* @return string The parsed message.
	*/
	function mycode_parse_quotes($message, $text_only=false)
	{
		global $lang, $templates, $theme, $mybb;

		// Assign pattern and replace values.
		$pattern = "#\[quote\](.*?)\[\/quote\](\r\n?|\n?)#si";
		$pattern_callback = "#\[quote=([\"']|&quot;|)(.*?)(?:\\1)(.*?)(?:[\"']|&quot;)?\](.*?)\[/quote\](\r\n?|\n?)#si";

		if($text_only == false)
		{
			$replace = "<blockquote class=\"mycode_quote\"><cite>$lang->quote</cite>$1</blockquote>\n";
			$replace_callback = array($this, 'mycode_parse_post_quotes_callback1');
		}
		else
		{
			$replace = empty($this->options['signature_parse']) ? "\n{$lang->quote}\n--\n$1\n--\n" : "$1";
			$replace_callback = array($this, 'mycode_parse_post_quotes_callback2');
		}

		do
		{
			// preg_replace has erased the message? Restore it...
			$previous_message = $message;
			$message = preg_replace($pattern, $replace, $message, -1, $count);
			$message = preg_replace_callback($pattern_callback, $replace_callback, $message, -1, $count_callback);
			if(!$message)
			{
				$message = $previous_message;
				break;
			}
		} while($count || $count_callback);

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
	* @param string $message The message to be parsed
	* @param string $username The username to be parsed
	* @param boolean $text_only Are we formatting as text?
	* @return string The parsed message.
	*/
	function mycode_parse_post_quotes($message, $username, $text_only=false)
	{
		global $lang, $templates, $theme, $mybb;

		$linkback = $date = "";

		$message = trim($message);
		$message = preg_replace("#(^<br(\s?)(\/?)>|<br(\s?)(\/?)>$)#i", "", $message);

		if(!$message)
		{
			return '';
		}

		$username .= "'";
		$delete_quote = true;

		preg_match("#pid=(?:&quot;|\"|')?([0-9]+)[\"']?(?:&quot;|\"|')?#i", $username, $match);
		if((int)$match[1])
		{
			$pid = (int)$match[1];
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
		if((int)$match[1])
		{
			if($match[1] < TIME_NOW)
			{
				if($text_only)
				{
					$postdate = my_date('normal', (int)$match[1]);
				}
				else
				{
					$postdate = my_date('relative', (int)$match[1]);
				}
				$date = " ({$postdate})";
			}
			$username = preg_replace("#(?:&quot;|\"|')? dateline=(?:&quot;|\"|')?[0-9]+(?:&quot;|\"|')?#i", '', $username);
			$delete_quote = false;
		}

		if($delete_quote)
		{
			$username = my_substr($username, 0, my_strlen($username)-1);
		}

		if(!empty($this->options['allow_html']))
		{
			$username = htmlspecialchars_uni($username);
		}

		if($text_only)
		{
			return "\n{$username} {$lang->wrote}{$date}\n--\n{$message}\n--\n";
		}
		else
		{
			$span = "";
			if(!$delete_quote)
			{
				$span = "<span>{$date}</span>";
			}

			eval("\$mycode_quote = \"".$templates->get("mycode_quote_post", 1, 0)."\";");
			return $mycode_quote;
		}
	}

	/**
	* Parses quotes with post id and/or dateline.
	*
	* @param array $matches Matches.
	* @return string The parsed message.
	*/
	function mycode_parse_post_quotes_callback1($matches)
	{
		return $this->mycode_parse_post_quotes($matches[4],$matches[2].$matches[3]);
	}

	/**
	* Parses quotes with post id and/or dateline.
	*
	* @param array $matches Matches.
	* @return string The parsed message.
	*/
	function mycode_parse_post_quotes_callback2($matches)
	{
		return $this->mycode_parse_post_quotes($matches[4],$matches[2].$matches[3], true);
	}

	/**
	* Parses code MyCode.
	*
	* @param string $code The message to be parsed
	* @param boolean $text_only Are we formatting as text?
	* @return string The parsed message.
	*/
	function mycode_parse_code($code, $text_only=false)
	{
		global $lang, $templates;

		if($text_only == true)
		{
			return empty($this->options['signature_parse']) ? "\n{$lang->code}\n--\n{$code}\n--\n" : $code;
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

		eval("\$mycode_code = \"".$templates->get("mycode_code", 1, 0)."\";");
		return $mycode_code;
	}

	/**
	* Parses code MyCode.
	*
	* @param array $matches Matches.
	* @return string The parsed message.
	*/
	function mycode_parse_code_callback($matches)
	{
		return $this->mycode_parse_code($matches[1], true);
	}

	/**
	* Parses PHP code MyCode.
	*
	* @param string $str The message to be parsed
	* @param boolean $bare_return Whether or not it should return it as pre-wrapped in a div or not.
	* @param boolean $text_only Are we formatting as text?
	* @return string The parsed message.
	*/
	function mycode_parse_php($str, $bare_return = false, $text_only = false)
	{
		global $lang, $templates;

		if($text_only == true)
		{
			return empty($this->options['signature_parse']) ? "\n{$lang->php_code}\n--\n{$str}\n--\n" : $str;
		}

		// Clean the string before parsing except tab spaces.
		$str = preg_replace('#^(\t*)(\n|\r|\0|\x0B| )*#', '\\1', $str);
		$str = rtrim($str);

		$original = preg_replace('#^\t*#', '', $str);

		if(empty($original))
		{
			return;
		}

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
		eval("\$mycode_php = \"".$templates->get("mycode_php", 1, 0)."\";");
		return $mycode_php;
	}

	/**
	* Parses PHP code MyCode.
	*
	* @param array $matches Matches.
	* @return string The parsed message.
	*/
	function mycode_parse_php_callback($matches)
	{
		return $this->mycode_parse_php($matches[1], false, true);
	}

	/**
	* Parses URL MyCode.
	*
	* @param string $url The URL to link to.
	* @param string $name The name of the link.
	* @return string The built-up link.
	*/
	function mycode_parse_url($url, $name="")
	{
		global $templates;
		if(!preg_match("#^[a-z0-9]+://#i", $url))
		{
			$url = "http://".$url;
		}

		if(!empty($this->options['allow_html']))
		{
			$url = $this->parse_html($url);
		}

		if(!$name)
		{
			$name = $url;
		}

		if($name == $url && (!isset($this->options['shorten_urls']) || !empty($this->options['shorten_urls'])))
		{
			$name = htmlspecialchars_decode($name);
			if(my_strlen($name) > 55)
			{
				$name = my_substr($name , 0, 40).'...'.my_substr($name , -10);
			}
			$name = htmlspecialchars_uni($name);
		}

		if(!empty($this->options['nofollow_on']))
		{
			$rel = " rel=\"noopener nofollow\"";
		}
		else
		{
			$rel = " rel=\"noopener\"";
		}

		// Fix some entities in URLs
		$url = $this->encode_url($url);


		$name = preg_replace("#&amp;\#([0-9]+);#si", "&#$1;", $name); // Fix & but allow unicode

		eval("\$mycode_url = \"".$templates->get("mycode_url", 1, 0)."\";");
		return $mycode_url;
	}

	/**
	* Parses URL MyCode.
	*
	* @param array $matches Matches.
	* @return string The built-up link.
	*/
	function mycode_parse_url_callback1($matches)
	{
		if(!isset($matches[3]))
		{
			$matches[3] = '';
		}
		return $this->mycode_parse_url($matches[1].$matches[2], $matches[3]);
	}

	/**
	* Parses URL MyCode.
	*
	* @param array $matches Matches.
	* @return string The built-up link.
	*/
	function mycode_parse_url_callback2($matches)
	{
		if(!isset($matches[2]))
		{
			$matches[2] = '';
		}
		return $this->mycode_parse_url($matches[1], $matches[2]);
	}

	/**
	 * Parses IMG MyCode.
	 *
	 * @param string $url The URL to the image
	 * @param array $dimensions Optional array of dimensions
	 * @param string $align
	 * @return string
	 */
	function mycode_parse_img($url, $dimensions=array(), $align='')
	{
		global $lang, $templates;
		$url = trim($url);
		$url = str_replace("\n", "", $url);
		$url = str_replace("\r", "", $url);

		if(!empty($this->options['allow_html']))
		{
			$url = $this->parse_html($url);
		}

		$css_align = '';
		if($align == "right")
		{
			$css_align = ' style="float: right;"';
		}
		else if($align == "left")
		{
			$css_align = ' style="float: left;"';
		}

		if($align)
		{
			$this->clear_needed = true;
		}

		$alt = basename($url);
		$alt = htmlspecialchars_decode($alt);
		if(my_strlen($alt) > 55)
		{
			$alt = my_substr($alt, 0, 40).'...'.my_substr($alt, -10);
		}
		$alt = $this->encode_url($alt);

		$alt = $lang->sprintf($lang->posted_image, $alt);
		$width = $height = '';
		if(isset($dimensions[0]) && $dimensions[0] > 0 && isset($dimensions[1]) && $dimensions[1] > 0)
		{
			$width = " width=\"{$dimensions[0]}\"";
			$height = " height=\"{$dimensions[1]}\"";
		}

		$url = $this->encode_url($url);

		eval("\$mycode_img = \"".$templates->get("mycode_img", 1, 0)."\";");
		return $mycode_img;
	}

	/**
	 * Parses IMG MyCode.
	 *
	 * @param array $matches Matches.
	 * @return string Image code.
	 */
	function mycode_parse_img_callback1($matches)
	{
		return $this->mycode_parse_img($matches[2]);
	}

	/**
	 * Parses IMG MyCode.
	 *
	 * @param array $matches Matches.
	 * @return string Image code.
	 */
	function mycode_parse_img_callback2($matches)
	{
		return $this->mycode_parse_img($matches[4], array($matches[1], $matches[2]));
	}

	/**
	 * Parses IMG MyCode.
	 *
	 * @param array $matches Matches.
	 * @return string Image code.
	 */
	function mycode_parse_img_callback3($matches)
	{
		return $this->mycode_parse_img($matches[3], array(), $matches[1]);
	}

	/**
	 * Parses IMG MyCode.
	 *
	 * @param array $matches Matches.
	 * @return string Image code.
	 */
	function mycode_parse_img_callback4($matches)
	{
		return $this->mycode_parse_img($matches[5], array($matches[1], $matches[2]), $matches[3]);
	}

	/**
	 * Parses IMG MyCode disabled.
	 *
	 * @param string $url The URL to the image
	 * @return string
	 */
	function mycode_parse_img_disabled($url)
	{
		global $lang;
		$url = trim($url);
		$url = str_replace("\n", "", $url);
		$url = str_replace("\r", "", $url);
		$url = str_replace("\'", "'", $url);

		$image = $lang->sprintf($lang->posted_image, $this->mycode_parse_url($url));
		return $image;
	}

	/**
	 * Parses IMG MyCode disabled.
	 *
	 * @param array $matches Matches.
	 * @return string Image code.
	 */
	function mycode_parse_img_disabled_callback1($matches)
	{
		return $this->mycode_parse_img_disabled($matches[2]);
	}

	/**
	 * Parses IMG MyCode disabled.
	 *
	 * @param array $matches Matches.
	 * @return string Image code.
	 */
	function mycode_parse_img_disabled_callback2($matches)
	{
		return $this->mycode_parse_img_disabled($matches[4]);
	}

	/**
	 * Parses IMG MyCode disabled.
	 *
	 * @param array $matches Matches.
	 * @return string Image code.
	 */
	function mycode_parse_img_disabled_callback3($matches)
	{
		return $this->mycode_parse_img_disabled($matches[3]);
	}

	/**
	 * Parses IMG MyCode disabled.
	 *
	 * @param array $matches Matches.
	 * @return string Image code.
	 */
	function mycode_parse_img_disabled_callback4($matches)
	{
		return $this->mycode_parse_img_disabled($matches[5]);
	}

	/**
	* Parses email MyCode.
	*
	* @param string $email The email address to link to.
	* @param string $name The name for the link.
	* @return string The built-up email link.
	*/
	function mycode_parse_email($email, $name="")
	{
		global $templates;

		if(!$name)
		{
			$name = $email;
		}
		if(preg_match("/^([a-zA-Z0-9-_\+\.]+?)@[a-zA-Z0-9-]+\.[a-zA-Z0-9\.-]+$/si", $email))
		{
			$email = $email;
		}
		elseif(preg_match("/^([a-zA-Z0-9-_\+\.]+?)@[a-zA-Z0-9-]+\.[a-zA-Z0-9\.-]+\?(.*?)$/si", $email))
		{
			$email = htmlspecialchars_uni($email);
		}

		eval("\$mycode_email = \"".$templates->get("mycode_email", 1, 0)."\";");
		return $mycode_email;
	}

	/**
	* Parses email MyCode.
	*
	* @param array $matches Matches
	* @return string The built-up email link.
	*/
	function mycode_parse_email_callback($matches)
	{
		if(!isset($matches[2]))
		{
			$matches[2] = '';
		}
		return $this->mycode_parse_email($matches[1], $matches[2]);
	}

	/**
	* Parses video MyCode.
	*
	* @param string $video The video provider.
	* @param string $url The video to link to.
	* @return string The built-up video code.
	*/
	function mycode_parse_video($video, $url)
	{
		global $templates;

		if(empty($video) || empty($url))
		{
			return "[video={$video}]{$url}[/video]";
		}

		// Check URL is a valid URL first, as `parse_url` doesn't check validity.
		if(false === filter_var($url, FILTER_VALIDATE_URL))
		{
            return "[video={$video}]{$url}[/video]";
        }

		$parsed_url = @parse_url(urldecode($url));
		if($parsed_url === false)
		{
			return "[video={$video}]{$url}[/video]";
		}

		$fragments = array();
		if($parsed_url['fragment'])
		{
			$fragments = explode("&", $parsed_url['fragment']);
		}

		if($video == "liveleak")
		{
			// The query part can start with any alphabet, but set only 'i' to catch in index key later
			$parsed_url['query'] = "i".substr($parsed_url['query'], 1);
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
				if(isset($path[2]))
				{
					list($id) = explode('_', $path[2], 2); // http://www.dailymotion.com/video/fds123_title-goes-here
				}
				else
				{
					$id = $path[1]; // http://dai.ly/fds123
				}
				break;
			case "metacafe":
				$id = $path[2]; // http://www.metacafe.com/watch/fds123/title_goes_here/
				$title = htmlspecialchars_uni($path[3]);
				break;
			case "myspacetv":
				$id = $path[4]; // http://www.myspace.com/video/fds/fds/123
				break;
			case "facebook":
				if(isset($input['v']))
				{
					$id = $input['v']; // http://www.facebook.com/video/video.php?v=123
				}
				elseif(substr($path[3], 0, 3) == 'vb.')
				{
					$id = $path[4]; // https://www.facebook.com/fds/videos/vb.123/123/
				}
				else
				{
					$id = $path[3]; // https://www.facebook.com/fds/videos/123/
				}
				break;
			case "mixer":
				$id = $path[1]; // https://mixer.com/streamer
				break;
			case "liveleak":
				$id = $input['i']; // http://www.liveleak.com/view?i=123
				break;
			case "yahoo":
				if(isset($path[2]))
				{
					$id = $path[2]; // http://xy.screen.yahoo.com/fds/fds-123.html
				}
				else
				{
					$id = $path[1]; // http://xy.screen.yahoo.com/fds-123.html
				}
				// Support for localized portals
				$domain = explode('.', $parsed_url['host']);
				if($domain[0] != 'screen' && preg_match('#^([a-z-]+)$#', $domain[0]))
				{
					$local = "{$domain[0]}.";
				}
				else
				{
					$local = '';
				}
				break;
			case "vimeo":
				if(isset($path[3]))
				{
					$id = $path[3]; // http://vimeo.com/fds/fds/fds123
				}
				else
				{
					$id = $path[1]; // http://vimeo.com/fds123
				}
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
			case "twitch":
				if(count($path) >= 3 && $path[1] == 'videos')
				{
					// Direct video embed with URL like: https://www.twitch.tv/videos/179723472
					$id = 'video=v'.$path[2];
				}
				elseif(count($path) >= 4 && $path[2] == 'v')
				{
					// Direct video embed with URL like: https://www.twitch.tv/waypoint/v/179723472
					$id = 'video=v'.$path[3];
				}
				elseif(count($path) >= 2)
				{
					// Channel (livestream) embed with URL like: https://twitch.tv/waypoint
					$id = 'channel='.$path[1];
				}
				break;
			default:
				return "[video={$video}]{$url}[/video]";
		}

		if(empty($id))
		{
			return "[video={$video}]{$url}[/video]";
		}

		$id = htmlspecialchars_uni($id);

		eval("\$video_code = \"".$templates->get("video_{$video}_embed", 1, 0)."\";");
		return $video_code;
	}

	/**
	* Parses video MyCode.
	*
	* @param array $matches Matches.
	* @return string The built-up video code.
	*/
	function mycode_parse_video_callback($matches)
	{
		return $this->mycode_parse_video($matches[1], $matches[2]);
	}

	/**
	 * Parses video MyCode disabled.
	 *
	 * @param string $url The URL to the video
	 * @return string
	 */
	function mycode_parse_video_disabled($url)
	{
		global $lang;
		$url = trim($url);
		$url = str_replace("\n", "", $url);
		$url = str_replace("\r", "", $url);
		$url = str_replace("\'", "'", $url);

		$video = $lang->sprintf($lang->posted_video, $this->mycode_parse_url($url));
		return $video;
	}

	/**
	* Parses video MyCode disabled.
	*
	* @param array $matches Matches.
	* @return string The built-up video code.
	*/
	function mycode_parse_video_disabled_callback($matches)
	{
		return $this->mycode_parse_video_disabled($matches[2]);
	}

	/**
	* Parses URLs automatically.
	*
	* @param string $message The message to be parsed
	* @return string The parsed message.
	*/
	function mycode_auto_url($message)
	{
		$message = " ".$message;

		// Links should end with slashes, numbers, characters and braces but not with dots, commas or question marks
		// Don't create links within existing links (handled up-front in the callback function).
		$message = preg_replace_callback("#<a\\s[^>]*>.*?</a>|([\s\(\)\[\>])(http|https|ftp|news|irc|ircs|irc6){1}://([^\/\"\s\<\[\.]+\.([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/([^\"\s<\[]|\[\])*)?([\w\/\)]))#ius", array($this, 'mycode_auto_url_callback'), $message);
		$message = preg_replace_callback("#<a\\s[^>]*>.*?</a>|([\s\(\)\[\>])(www|ftp)\.(([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/([^\"\s<\[]|\[\])*)?([\w\/\)]))#ius", array($this, 'mycode_auto_url_callback'), $message);
		$message = my_substr($message, 1);

		return $message;
	}

	/**
	* Parses URLs automatically.
	*
	* @param array $matches Matches
	* @return string The parsed message.
	*/
	function mycode_auto_url_callback($matches=array())
	{
		// If we matched a preexisting link (the part of the regexes in mycode_auto_url() before the pipe symbol),
		// then simply return it - we don't create links within existing links.
		if(count($matches) == 1)
		{
			return $matches[0];
		}

		$external = '';
		// Allow links like http://en.wikipedia.org/wiki/PHP_(disambiguation) but detect mismatching braces
		while(my_substr($matches[3], -1) == ')')
		{
			if(substr_count($matches[3], ')') > substr_count($matches[3], '('))
			{
				$matches[3] = my_substr($matches[3], 0, -1);
				$external = ')'.$external;
			}
			else
			{
				break;
			}

			// Example: ([...] http://en.wikipedia.org/Example_(disambiguation).)
			$last_char = my_substr($matches[3], -1);
			while($last_char == '.' || $last_char == ',' || $last_char == '?' || $last_char == '!')
			{
				$matches[3] = my_substr($matches[3], 0, -1);
				$external = $last_char.$external;
				$last_char = my_substr($matches[3], -1);
			}
		}
		if(in_array(strtolower($matches[2]), array('www', 'ftp')))
		{
			$url = "{$matches[2]}.{$matches[3]}";
		}
		else
		{
			$url = "{$matches[2]}://{$matches[3]}";
		}

		return $matches[1].$this->mycode_parse_url($url, $url).$external;
	}

	/**
	* Parses list MyCode.
	*
	* @param string $message The message to be parsed
	* @param string $type The list type
	* @return string The parsed message.
	*/
	function mycode_parse_list($message, $type="")
	{
		// No list elements? That's invalid HTML
		if(strpos($message, '[*]') === false)
		{
			$message = "[*]{$message}";
		}

		$message = preg_split("#[^\S\n\r]*\[\*\]\s*#", $message);
		if(isset($message[0]) && trim($message[0]) == '')
		{
			array_shift($message);
		}
		$message = '<li>'.implode("</li>\n<li>", $message)."</li>\n";

		if($type)
		{
			$list = "\n<ol type=\"$type\" class=\"mycode_list\">$message</ol>\n";
		}
		else
		{
			$list = "<ul class=\"mycode_list\">$message</ul>\n";
		}
		$list = preg_replace("#<(ol type=\"$type\"|ul)>\s*</li>#", "<$1>", $list);
		return $list;
	}

	/**
	* Parses list MyCode.
	*
	* @param array $matches Matches
	* @return string The parsed message.
	*/
	function mycode_parse_list_callback($matches)
	{
		return $this->mycode_parse_list($matches[3], $matches[2]);
	}

	/**
	* Prepares list MyCode by finding the matching list tags.
	*
	* @param array $matches Matches
	* @return string Temporary replacements.
	*/
	function mycode_prepare_list($matches)
	{
		// Append number to identify matching list tags
		if(strcasecmp($matches[1], '[/list]') == 0)
		{
			$count = array_pop($this->list_elements);
			if($count !== NULL)
			{
				return "[/list&{$count}]";
			}
			else
			{
				// No open list tag...
				return $matches[0];
			}
		}
		else
		{
			++$this->list_count;
			$this->list_elements[] = $this->list_count;
			if(!empty($matches[2]))
			{
				return "[list{$matches[2]}&{$this->list_count}]";
			}
			else
			{
				return "[list&{$this->list_count}]";
			}
		}
	}

	/**
	 * Strips smilies from a string
	 *
	 * @param string $message The message for smilies to be stripped from
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
	 * @param string $message The message to be highligted
	 * @param string $highlight The highlight keywords
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
	 * @param string $message The message to be parsed
	 * @param array $options
	 * @return string The parsed message.
	 */
	function text_parse_message($message, $options=array())
	{
		global $plugins;

		if(empty($this->options))
		{
			$this->options = $options;
		}
		else
		{
			foreach($options as $option_name => $option_value)
			{
				$this->options[$option_name] = $option_value;
			}
		}

		// Filter bad words if requested.
		if(!empty($this->options['filter_badwords']))
		{
			$message = $this->parse_badwords($message);
		}

		// Parse quotes first
		$message = $this->mycode_parse_quotes($message, true);

		$message = preg_replace_callback("#\[php\](.*?)\[/php\](\r\n?|\n?)#is", array($this, 'mycode_parse_php_callback'), $message);
		$message = preg_replace_callback("#\[code\](.*?)\[/code\](\r\n?|\n?)#is", array($this, 'mycode_parse_code_callback'), $message);

		$find = array(
			"#\[(b|u|i|s|url|email|color|img)\](.*?)\[/\\1\]#is",
			"#\[(email|color|size|font|align|video)=[^]]*\](.*?)\[/\\1\]#is",
			"#\[img=([1-9][0-9]*)x([1-9][0-9]*)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is",
			"#\[url=((?!javascript)[a-z]+?://)([^\r\n\"<]+?)\](.+?)\[/url\]#si",
			"#\[url=((?!javascript:)[^\r\n\"<&\(\)]+?)\](.+?)\[/url\]#si",
		);

		$replace = array(
			"$2",
			"$2",
			"$4",
			"$3 ($1$2)",
			"$2 ($1)",
		);
		
		$messageBefore = "";
		// The counter limit for this "for" loop is for defensive programming purpose only. It protects against infinite repetition. 
		for($cnt = 1; $cnt < 20 && $message != $messageBefore; $cnt++)
		{
			$messageBefore = $message;
			$message = preg_replace($find, $replace, $messageBefore);
		}

		// Replace "me" code and slaps if we have a username
		if(!empty($this->options['me_username']))
		{
			global $lang;

			$message = preg_replace('#(>|^|\r|\n)/me ([^\r\n<]*)#i', "\\1* {$this->options['me_username']} \\2", $message);
			$message = preg_replace('#(>|^|\r|\n)/slap ([^\r\n<]*)#i', "\\1* {$this->options['me_username']} {$lang->slaps} \\2 {$lang->with_trout}", $message);
		}

		// Reset list cache
		$this->list_elements = array();
		$this->list_count = 0;

		// Find all lists
		$message = preg_replace_callback("#(\[list(=(a|A|i|I|1))?\]|\[/list\])#si", array($this, 'mycode_prepare_list'), $message);

		// Replace all lists
		for($i = $this->list_count; $i > 0; $i--)
		{
			// Ignores missing end tags
			$message = preg_replace_callback("#\s?\[list(=(a|A|i|I|1))?&{$i}\](.*?)(\[/list&{$i}\]|$)(\r\n?|\n?)#si", array($this, 'mycode_parse_list_callback'), $message, 1);
		}

		// Run plugin hooks
		$message = $plugins->run_hooks("text_parse_message", $message);

		return $message;
	}

	/**
	 * Replaces certain characters with their entities in a URL.
	 *
	 * @param string $url The URL to be escaped.
	 * @return string The escaped URL.
	 */
	function encode_url($url)
	{
		$entities = array('$' => '%24', '&#36;' => '%24', '^' => '%5E', '`' => '%60', '[' => '%5B', ']' => '%5D', '{' => '%7B', '}' => '%7D', '"' => '%22', '<' => '%3C', '>' => '%3E', ' ' => '%20');

		$url = str_replace(array_keys($entities), array_values($entities), $url);

		return $url;
	}
}
