<?php
/**
 * Import an entire theme (stylesheets, properties & templates) from an XML file.
 *
 * @param string The contents of the XML file
 * @param array Optional array of options or overrides
 * @return boolean True on success, false on failure
 */
function import_theme_xml($xml, $options=array())
{
	global $mybb, $db;
	
	require_once MYBB_ROOT."inc/class_xml.php";

	$parser = new XMLParser($xml);
	$tree = $parser->get_tree();

	if(!is_array($tree) || !is_array($tree['theme']))
	{
		return -1;
	}
	
	$theme = $tree['theme'];
	
	if(is_array($theme['properties']))
	{
		foreach($theme['properties'] as $property => $value)
		{
			if($property == "tag" || $property == "value") continue;
			$properties[$property] = $value['value'];
		}
	}
	
	if(empty($mybb->input['name']))
	{
		$name = $theme['attributes']['name'];
	}
	else
	{
		$name = $mybb->input['name'];
	}
	$version = $theme['attributes']['version'];

	$query = $db->simple_select("themes", "tid", "name='".$db->escape_string($name)."'", array("limit" => 1));
	$existingtheme = $db->fetch_array($query);
	if($existingtheme['tid'])
	{
		$options['tid'] = $existingtheme['tid'];
	}

	if($mybb->version_code != $version && $options['version_compat'] != 1)
	{
		return -2;
	}

	// Not overriding an existing theme
	if(!$options['tid'])
	{
		// Insert the theme
		$theme_id = build_new_theme($theme['attributes']['name'], $properties, 0);
	}
	// Overriding an existing - delete refs.
	else
	{
		$db->delete_query("themestylesheets", "tid='{$options['tid']}'");
		$db->update_query("themes", array("properties" => $db->escape_string(serialize($properties))), "tid='{$options['tid']}'");
		$theme_id = $options['tid'];
	}

	// If we have any stylesheets, process them
	if(is_array($theme['stylesheets']) && !$options['no_stylesheets'])
	{
		foreach($theme['stylesheets']['stylesheet'] as $stylesheet)
		{
			if(!$stylesheet['attributes']['lastmodified'])
			{
				$stylesheet['attributes']['lastmodified'] = time();
			}
			$new_stylesheet = array(
				"name" => $db->escape_string($stylesheet['attributes']['name']),
				"tid" => $theme_id,
				"attachedto" => $db->escape_string($stylesheet['attributes']['attachedto']),
				"stylesheet" => $db->escape_string($stylesheet['value']),
				"lastmodified" => intval($stylesheet['attributes']['lastmodified']),
				"cachefile" => $db->escape_string($stylesheet['attributes']['name'])
			);
			$sid = $db->insert_query("themestylesheets", $new_stylesheet);
			$css_url = "css.php?stylesheet={$sid}";
			$cached = cache_stylesheet($theme_id, $stylesheet['attributes']['name'], $stylesheet['value']);
			if($cached)
			{
				$css_url = $cached;
			}
			$attachedto = $stylesheet['attributes']['attachedto'];
			if(!$attachedto)
			{
				$attachedto = "global";
			}
			// private.php?compose,folders|usercp.php,global|global
			$attachedto = explode("|", $attachedto);
			foreach($attachedto as $attached_file)
			{
				$attached_actions = explode(",", $attached_file);
				$attached_file = array_shift($attached_actions);
				if(count($attached_actions) == 0)
				{
					$attached_actions = array("global");
				}
				foreach($attached_actions as $action)
				{
					$theme_stylesheets[$attached_file][$action][] = $css_url;
				}
			}
		}
		// Now we have our list of built stylesheets, save them
		$updated_theme = array(
			"stylesheets" => $db->escape_string(serialize($theme_stylesheets))
		);
		$db->update_query("themes", $updated_theme, "tid='{$theme_id}'");
	}

	// Do we have any templates to insert?
	if(is_array($theme['templates']) && !$options['no_templates'])
	{		
		if($options['templateset']) 
		{ 
			$sid = $options['templateset'];
		} 
		else 
		{ 
			$sid = $db->insert_query("templatesets", array('title' => $db->escape_string($name)." Templates"));
		}
		
		$templates = $theme['templates']['template'];
		foreach($templates as $template)
		{
			$new_template = array(
				"title" => $template['attributes']['name'],
				"template" => $db->escape_string($template['value']),
				"sid" => $sid,
				"version" => $template['attributes']['version'],
				"dateline" => TIME_NOW
			);
			$db->insert_query("templates", $new_template);
		}
	}

	// And done?
	return $theme_id;
}

/**
 * Parse theme variables in a specific string.
 *
 * @param string The string to parse variables for
 * @param array Array of variables
 * @return string Parsed string with variables replaced
 */
function parse_theme_variables($string, $variables=array())
{
	foreach(array_keys($variables) as $variable) 
	{
		$find[] = "{{$variable}}";
		$replace[] = $variables[$variable];
	}
	return str_replace($find, $replace, $string);
}

/**
 * Caches a stylesheet to the file system.
 *
 * @param string The theme ID this stylesheet belongs to
 * @param string The name of the stylesheet
 * @param string The contents of the stylesheet
 */
function cache_stylesheet($tid, $filename, $stylesheet)
{
	global $mybb;
	
	if($mybb->safemode)
	{
		return false;
	}

	if(!is_dir(MYBB_ROOT."cache/themes/theme{$tid}"))
	{
		if(!@mkdir(MYBB_ROOT."cache/themes/theme{$tid}"))
		{
			return false;
		}
		// Add in empty index.html!
		$fp = @fopen(MYBB_ROOT."cache/themes/theme{$tid}/index.html", "w");
		@fwrite($fp, "");
		@fclose($fp);
	}

	$theme_vars = array(
		"theme" => "cache/themes/theme{$tid}"
	);
	$stylesheet = parse_theme_variables($stylesheet, $theme_vars);
	$stylesheet = preg_replace("#url\((\"|'|)(.*)\\1\)#e", "fix_css_urls('$2')", $stylesheet);
	
	$fp = @fopen(MYBB_ROOT."cache/themes/theme{$tid}/{$filename}", "wb");
	if(!$fp) return false;
	fwrite($fp, $stylesheet);
	fclose($fp);
	return "cache/themes/theme{$tid}/{$filename}";
}

function resync_stylesheet($stylesheet)
{
	global $db;
	
	if(!$stylesheet['cachefile'])
	{
		$stylesheet['cachefile'] = $stylesheet['name'];		
		$db->update_query("themestylesheets", array('cachefile' => $db->escape_string($stylesheet['name'])), "sid='{$stylesheet['sid']}'", 1);
	}
	
	if(!file_exists(MYBB_ROOT."cache/themes/theme{$stylesheet['tid']}/{$stylesheet['cachefile']}"))
	{
		cache_stylesheet($stylesheet['tid'], $stylesheet['cachefile'], $stylesheet['stylesheet']);
	
		return true;
	}
	else if(@filemtime(MYBB_ROOT."cache/themes/theme{$stylesheet['tid']}/{$stylesheet['cachefile']}") > $stylesheet['lastmodified'])
	{
		$contents = unfix_css_urls(file_get_contents(MYBB_ROOT."cache/themes/theme{$stylesheet['tid']}/{$stylesheet['cachefile']}"));		
		$db->update_query("themestylesheets", array('stylesheet' => $db->escape_string($contents)), "sid='{$stylesheet['sid']}'", 1);
		return true;
	}
	
	return false;
}

function fix_css_urls($url)
{
	if(!preg_match("#^(https?://|/)#i", $url))
	{
		return "url(../../../{$url})";
	}
	else
	{
		return "url({$url})";
	}
}

function unfix_css_urls($url)
{
	return preg_replace("#^".preg_quote("../../../", "#")."#", "./", $url);
}

/**
 * Build a theme based on the specified parameters.
 *
 * @param string The name of the theme
 * @param array Array of theme properties (if blank, inherits from parent)
 * @param int The parent ID for this theme (defaults to Master)
 * @return int The new theme ID
 */
function build_new_theme($name, $properties=null, $parent=1)
{
	global $db;

	$new_theme = array(
		"name" => $db->escape_string($name),
		"pid" => intval($parent),
		"def" => 0,
		"allowedgroups" => ""
	);
	$tid = $db->insert_query("themes", $new_theme);

	if($parent > 0)
	{
		$query = $db->simple_select("themes", "*", "tid='".intval($parent)."'");
		$parent_theme = $db->fetch_array($query);
		if(count($properties) == 0 || !is_array($properties))
		{
			$parent_properties = unserialize($parent_theme['properties']);
			foreach($parent_properties as $property => $value)
			{
				if($property == "inherited")
				{
					continue;
				}
				
				$properties[$property] = $value;
				if($parent_properties['inherited'][$property])
				{
					$properties['inherited'][$property] = $parent_properties['inherited'][$property];
				}
				else
				{
					$properties['inherited'][$property] = $parent;
				}
			}
			$inherited_properties = true;
		}

		if(count($stylesheets) == 0)
		{
			$parent_stylesheets = unserialize($parent_theme['stylesheets']);
			foreach($parent_stylesheets as $location => $value)
			{
				if($location == "inherited")
				{
					continue;
				}
				
				foreach($value as $action => $sheets)
				{
					foreach($sheets as $stylesheet)
					{
						$stylesheets[$location][$action][] = $stylesheet;
						$inherited_check = "{$location}_{$action}";
						if($parent_stylesheets['inherited'][$inherited_check][$stylesheet])
						{
							$stylesheets['inherited'][$inherited_check][$stylesheet] = $parent_stylesheets['inherited'][$inherited_check][$stylesheet];
						}
						else
						{
							$stylesheets['inherited'][$inherited_check][$stylesheet] = $parent;
						}
					}
				}
			}
			$inherited_stylesheets = true;
		}
	}

	if(!$inherited_properties)
	{
		$theme_vars = array(
			"theme" => "cache/themes/theme{$tid}"
		);
		$properties['logo'] = parse_theme_variables($properties['logo'], $theme_vars);
	}
	$updated_theme['stylesheets'] = $db->escape_string(serialize($stylesheets));
	$updated_theme['properties'] = $db->escape_string(serialize($properties));

	if(count($updated_theme) > 0)
	{
		$db->update_query("themes", $updated_theme, "tid='{$tid}'");
	}

	return $tid;
}



/**
 * Generates an array from an incoming CSS file.
 *
 * @param string The incoming CSS
 * @return array Parsed CSS file as array, false on failure
 */
function css_to_array($css)
{
	// Normalise line breaks
	$css = str_replace(array("\r\n", "\n", "\r"), "\n", $css);

	/**
	 * Play with the css a  little - just to ensure we can parse it
	 *
	 * This essentially adds line breaks before and after each } not inside a string
	 * so it's parsed correctly below
	 */
	$stripped_css = preg_replace('#(?<!\\")\}#', "\n}\n", $css);

	// Fetch out classes and comments
	preg_match_all('#(\/\*(.|[\r\n])*?\*\/)?([a-z0-9a+\\\[\]\-\"=_:>\*\.\#\,\s\(\)\|~\^]+)(\s*)\{(.*?)\}\n#msi', $stripped_css, $matches, PREG_PATTERN_ORDER);
	$total = count($matches[1]);

	for($i=0;$i<$total;$i++)
	{
		$name = $description = '';
		$class_name = $matches[3][$i];
		$class_name = trim($class_name);
		$comments = $matches[1][$i];
		preg_match_all("#Name:(.*)#i", $comments, $name_match);
		if($name_match[count($name_match)-1][0])
		{
			$name = trim($name_match[count($name_match)-1][0]);
		}
		preg_match_all("#Description:(.*)#i", $comments, $description_match);
		if($description_match[count($description_match)-1][0])
		{
			$description = trim($description_match[count($description_match)-1][0]);
		}
		$class_id = md5($class_name);
		if($already_parsed[$class_id])
		{
			$already_parsed[$class_id]++;
			$class_id .= "_".$already_parsed[$class_id];
		}
		else
		{
			$already_parsed[$class_id] = 1;
		}
		$values = trim($matches[5][$i]);
		$values = preg_replace("#/\*(.*?)\*/#s", "", $values);
		$parsed_css[$class_id] = array("class_name" => $class_name, "name" => $name, "description" => $description, "values" => $values);
	}

	return $parsed_css;
}

function get_selectors_as_options($css, $selected_item="")
{
	$select = "";
	
	if(!is_array($css))
	{
		$css = css_to_array($css);
	}
	
	foreach($css as $id => $css_array)
	{
		if(!$css_array['name'])
		{
			$css_array['name'] = $css_array['class_name'];
		}
		
		if($selected_item == $css_array['name'])
		{
			$select .= "<option value=\"{$id}\" selected=\"selected\">{$css_array['name']}</option>";
		}
		else
		{
			$select .= "<option value=\"{$id}\">{$css_array['name']}</option>";
		}
	}
	return $select;
}

function get_css_properties($css, $id)
{
	if(!is_array($css))
	{
		$css = css_to_array($css);
	}
	
	if(!isset($css[$id]))
	{
		return false;
	}
	return parse_css_properties($css[$id]['values']);
}

/**
 * Parses CSS supported properties and returns them as an array.
 *
 * @param string Value of CSS properties from within class or selector
 * @return array Array of CSS properties
 */
function parse_css_properties($values)
{
	if(!$values)
	{
		return;
	}
	$values = explode(";", $values);
	foreach($values as $value)
	{
		$value = trim($value);
		if(!$value) continue;
		list($property, $css_value) = explode(":", $value, 2);
		$property = trim($property);
		switch(strtolower($property))
		{
			case "background":
			case "color":
			case "width":
			case "font":
			case "font-family":
			case "font-size":
			case "font-weight":
			case "font-style":
				$css_bits[$property] = trim($css_value);
				break;
			default:
				$css_bits['extra'] .= "{$property}: ".trim($css_value).";\n";

		}
	}
	return $css_bits;
}

/**
 * Inserts an incoming string of CSS in to an already defined document. If the class ID is not found, the CSS is appended to the file.
 *
 * @param string Class ID for this piece of CSS
 * @param string CSS we wish to insert at this location
 * @param string The existing CSS if we have any
 * @param string (Optional) The optional friendly selector value just incase the CSS is not found in the file
 */
function insert_into_css($new_css, $class_id="", $css="", $selector="")
{
	$new_css = str_replace(array("\r\n", "\n", "\r"), "\n", $new_css);

	// Build the new CSS properties list
	$new_css = explode("\n", $new_css);
	foreach($new_css as $css_line)
	{
		$generated_css .= "\t".trim($css_line)."\n";
	}

	// Parse out the CSS
	if($css)
	{
		$parsed_css = css_to_array($css);
	}

	// The specified class ID cannot be found, add CSS to end of file
	if(!$css || !$parsed_css[$class_id])
	{
		return $css."{$selector}\n{$generated_css}}\n\n";
	}
	// Valid CSS, swap out old, swap in new
	else
	{
		$css = str_replace(array("\r\n", "\n", "\r"), "\n", $css);
		$css = preg_replace('#(?<!\\")\}#', "}\n", $css);
		$css = preg_replace("#\s*([a-z0-9a+\\\[\]\-\"=_:>\*\.\#\,\s\(\)\|~\^]+)(\s*)\{(\n*)#isu", "\n$1 {\n", $css);
		$css = preg_replace("#\s{1,}\{#", " {", $css);
		$existing_block = $parsed_css[$class_id];
		list($id, $actual_occurance) = explode("_", $class_id);
		if(!$actual_occurance) $actual_occurance = 1;
		$occurance = 1;
		$pos = 0;
		do
		{
			$pos = strpos($css, "\n".$existing_block['class_name']." {", $pos);
			if($pos === false)
			{
				break;
			}
			if($occurance == $actual_occurance)
			{
				// This is the part we want to replace, now we need to fetch the opening & closing braces
				$opening = strpos($css, "{", $pos);
				$closing = strpos($css, "}", $pos);
				$css = substr_replace($css, "\n".$generated_css."\n", $opening+1, $closing-$opening-1);
				break;
			}
			++$occurance;
			++$pos;
		} while($occurance <= $actual_occurance);
	}
	$css = preg_replace("#{\n*#s", "{\n", $css);
	$css = preg_replace("#\s*\}\s*#", "\n}\n\n", $css);
	return $css;	
}

function copy_stylesheet_to_theme($stylesheet, $tid)
{
	
}
?>