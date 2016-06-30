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
 * Selectively removes quote tags from a message, depending on its nested depth.  This is to be used with reply with quote functions.
 * For malformed quote tag structures, will try to simulate how MyBB's parser handles the issue, but is slightly inaccurate.
 * Examples, with a cutoff depth of 2:
 *  #1. INPUT:  [quote]a[quote=me]b[quote]c[/quote][/quote][/quote]
 *     OUTPUT:  [quote]a[quote=me]b[/quote][/quote]
 *  #2. INPUT:  [quote=a][quote=b][quote=c][quote=d][/quote][quote=e][/quote][/quote][quote=f][/quote][/quote]
 *     OUTPUT:  [quote=a][quote=b][/quote][quote=f][/quote][/quote]
 *
 * @param string $text the message from which quotes are to be removed
 * @param integer $rmdepth nested depth at which quotes should be removed; if none supplied, will use MyBB's default; must be at least 0
 * @return string the original message passed in $text, but with quote tags selectively removed
 */
function remove_message_quotes(&$text, $rmdepth=null)
{
	if(!$text)
	{
		return $text;
	}
	if(!isset($rmdepth))
	{
		global $mybb;
		$rmdepth = $mybb->settings['maxquotedepth'];
	}
	$rmdepth = (int)$rmdepth;

	// find all tokens
	// note, at various places, we use the prefix "s" to denote "start" (ie [quote]) and "e" to denote "end" (ie [/quote])
	preg_match_all("#\[quote(=(?:&quot;|\"|')?.*?(?:&quot;|\"|')?)?\]#si", $text, $smatches, PREG_OFFSET_CAPTURE | PREG_PATTERN_ORDER);
	preg_match_all("#\[/quote\]#i", $text, $ematches, PREG_OFFSET_CAPTURE | PREG_PATTERN_ORDER);

	if(empty($smatches) || empty($ematches))
	{
		return $text;
	}

	// make things easier by only keeping offsets
	$soffsets = $eoffsets = array();
	foreach($smatches[0] as $id => $match)
	{
		$soffsets[] = $match[1];
	}
	// whilst we loop, also remove unnecessary end tokens at the start of string
	$first_token = $soffsets[0];
	foreach($ematches[0] as $id => $match)
	{
		if($match[1] > $first_token)
		{
			$eoffsets[] = $match[1];
		}
	}
	unset($smatches, $ematches);


	// elmininate malformed quotes by parsing like the parser does (preg_replace in a while loop)
	// NOTE: this is slightly inaccurate because the parser considers [quote] and [quote=...] to be different things
	$good_offsets = array();
	while(!empty($soffsets) && !empty($eoffsets)) // don't rely on this condition - an end offset before the start offset will cause this to loop indefinitely
	{
		$last_offset = 0;
		foreach($soffsets as $sk => &$soffset)
		{
			if($soffset >= $last_offset)
			{
				// search for corresponding eoffset
				foreach($eoffsets as $ek => &$eoffset) // use foreach instead of for to get around indexing issues with unset
				{
					if($eoffset > $soffset)
					{
						// we've found a pair
						$good_offsets[$soffset] = 1;
						$good_offsets[$eoffset] = -1;
						$last_offset = $eoffset;

						unset($soffsets[$sk], $eoffsets[$ek]);
						break;
					}
				}
			}
		}

		// remove any end offsets occurring before start offsets
		$first_start = reset($soffsets);
		foreach($eoffsets as $ek => &$eoffset)
		{
			if($eoffset < $first_start)
			{
				unset($eoffsets[$ek]);
			}
			else
			{
				break;
			}
		}
		// we don't need to remove start offsets after the last end offset, because the loop will deplete something before that
	}

	if(empty($good_offsets))
	{
		return $text;
	}
	ksort($good_offsets);


	// we now have a list of all the ordered tokens, ready to go through
	$depth = 0;
	$remove_regions = array();
	$tmp_start = 0;
	foreach($good_offsets as $offset => $dincr)
	{
		if($depth == $rmdepth && $dincr == 1)
		{
			$tmp_start = $offset;
		}
		$depth += $dincr;
		if($depth == $rmdepth && $dincr == -1)
		{
			$remove_regions[] = array($tmp_start, $offset);
		}
	}

	if(empty($remove_regions))
	{
		return $text;
	}

	// finally, remove the quotes from the string
	$newtext = '';
	$cpy_start = 0;
	foreach($remove_regions as &$region)
	{
		$newtext .= substr($text, $cpy_start, $region[0]-$cpy_start);
		$cpy_start = $region[1]+8; // 8 = strlen('[/quote]')
		// clean up newlines
		$next_char = $text{$region[1]+8};
		if($next_char == "\r" || $next_char == "\n")
		{
			++$cpy_start;
			if($next_char == "\r" && $text{$region[1]+9} == "\n")
			{
				++$cpy_start;
			}
		}
	}
	// append remaining end text
	if(strlen($text) != $cpy_start)
	{
		$newtext .= substr($text, $cpy_start);
	}

	// we're done
	return $newtext;
}

/**
 * Performs cleanup of a quoted message, such as replacing /me commands, before presenting quoted post to the user.
 *
 * @param array $quoted_post quoted post info, taken from the DB (requires the 'message', 'username', 'pid' and 'dateline' entries to be set; will use 'userusername' if present. requires 'quote_is_pm' if quote message is from a private message)
 * @param boolean $remove_message_quotes whether to call remove_message_quotes() on the quoted message
 * @return string the cleaned up message, wrapped in a quote tag
 */

function parse_quoted_message(&$quoted_post, $remove_message_quotes=true)
{
	global $parser, $lang, $plugins;
	if(!isset($parser))
	{
		require_once MYBB_ROOT."inc/class_parser.php";
		$parser = new postParser;
	}

	// Swap username over if we have a registered user
	if($quoted_post['userusername'])
	{
		$quoted_post['username'] = $quoted_post['userusername'];
	}
	// Clean up the message
	$quoted_post['message'] = preg_replace(array(
		'#(^|\r|\n)/me ([^\r\n<]*)#i',
		'#(^|\r|\n)/slap ([^\r\n<]*)#i',
		'#\[attachment=([0-9]+?)\]#i'
	), array(
		"\\1* {$quoted_post['username']} \\2",
		"\\1* {$quoted_post['username']} {$lang->slaps} \\2 {$lang->with_trout}",
		"",
	), $quoted_post['message']);
	$quoted_post['message'] = $parser->parse_badwords($quoted_post['message']);

	if($remove_message_quotes)
	{
		global $mybb;
		$max_quote_depth = (int)$mybb->settings['maxquotedepth'];
		if($max_quote_depth)
		{
			$quoted_post['message'] = remove_message_quotes($quoted_post['message'], $max_quote_depth-1); // we're wrapping the message in a [quote] tag, so take away one quote depth level
		}
	}

	$quoted_post = $plugins->run_hooks("parse_quoted_message", $quoted_post);

	$extra = '';
	if(empty($quoted_post['quote_is_pm']))
	{
		$extra = " pid='{$quoted_post['pid']}' dateline='{$quoted_post['dateline']}'";
	}

	return "[quote='{$quoted_post['username']}'{$extra}]\n{$quoted_post['message']}\n[/quote]\n\n";
}

