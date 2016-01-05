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
 * Import an entire theme (stylesheets, properties & templates) from an XML file.
 *
 * @param string $xml The contents of the XML file
 * @param array $options Optional array of options or overrides
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

	// Do we have MyBB 1.2 template's we're importing?
	$css_120 = "";

	if(isset($theme['cssbits']) && is_array($theme['cssbits']))
	{
		$cssbits = kill_tags($theme['cssbits']);

		foreach($cssbits as $name => $values)
		{
			$css_120 .= "{$name} {\n";
			foreach($values as $property => $value)
			{
				if(is_array($value))
				{
					$property = str_replace('_', ':', $property);

					$css_120 .= "}\n{$name} {$property} {\n";
					foreach($value as $property2 => $value2)
					{
						$css_120 .= "\t{$property2}: {$value2}\n";
					}
				}
				else
				{
					$css_120 .= "\t{$property}: {$value}\n";
				}
			}
			$css_120 .= "}\n";
		}
	}

	if(isset($theme['themebits']) && is_array($theme['themebits']))
	{
		$themebits = kill_tags($theme['themebits']);

		$theme['properties']['tag'] = 'properties';

		foreach($themebits as $name => $value)
		{
			if($name == "extracss")
			{
				$css_120 .= $value;
				continue;
			}

			$theme['properties'][$name] = $value;
		}
	}

	if($css_120)
	{
		$css_120 = upgrade_css_120_to_140($css_120);
		$theme['stylesheets']['tag'] = 'stylesheets';
		$theme['stylesheets']['stylesheet'][0]['tag'] = 'stylesheet';
		$theme['stylesheets']['stylesheet'][0]['attributes'] = array('name' => 'global.css', 'version' => $mybb->version_code);
		$theme['stylesheets']['stylesheet'][0]['value'] = $css_120;

		unset($theme['cssbits']);
		unset($theme['themebits']);
	}

	if(is_array($theme['properties']))
	{
		foreach($theme['properties'] as $property => $value)
		{
			if($property == "tag" || $property == "value")
			{
				continue;
			}

			if($property == 'colors' || $property == 'disporder')
			{
				$data = my_unserialize($value['value']);

				if(!is_array($data))
				{
					// Bad data?
					continue;
				}

				$value['value'] = $data;
			}

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
	if(!empty($options['force_name_check']) && $existingtheme['tid'])
	{
		return -3;
	}
	else if($existingtheme['tid'])
	{
		$options['tid'] = $existingtheme['tid'];
	}

	if($mybb->version_code != $version && $options['version_compat'] != 1)
	{
		return -2;
	}

	// Do we have any templates to insert?
	if(!empty($theme['templates']['template']) && empty($options['no_templates']))
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
		if(is_array($templates))
		{
			// Theme only has one custom template
			if(array_key_exists("attributes", $templates))
			{
				$templates = array($templates);
			}
		}

		$security_check = false;
		$templatecache = array();
		foreach($templates as $template)
		{
			if(check_template($template['value']))
			{
				$security_check = true;
				break;
			}

			$templatecache[] = array(
				"title" => $db->escape_string($template['attributes']['name']),
				"template" => $db->escape_string($template['value']),
				"sid" => $db->escape_string($sid),
				"version" => $db->escape_string($template['attributes']['version']),
				"dateline" => TIME_NOW
			);
		}

		if($security_check == true)
		{
			return -4;
		}

		foreach($templatecache as $template)
		{
			// PostgreSQL causes apache to stop sending content sometimes and
			// causes the page to stop loading during many queries all at one time
			if($db->engine == "pgsql")
			{
				echo " ";
				flush();
			}

			$db->insert_query("templates", $template);
		}

		$properties['templateset'] = $sid;
	}

	// Not overriding an existing theme
	if(empty($options['tid']))
	{
		// Insert the theme
		if(!isset($options['parent']))
		{
			$options['parent'] = 0;
		}
		$theme_id = build_new_theme($name, $properties, $options['parent']);
	}
	// Overriding an existing - delete refs.
	else
	{
		$db->delete_query("themestylesheets", "tid='{$options['tid']}'");
		$db->update_query("themes", array("properties" => $db->escape_string(my_serialize($properties))), "tid='{$options['tid']}'");
		$theme_id = $options['tid'];
	}

	// If we have any stylesheets, process them
	if(!empty($theme['stylesheets']['stylesheet']) && empty($options['no_stylesheets']))
	{
		// Are we dealing with a single stylesheet?
		if(isset($theme['stylesheets']['stylesheet']['tag']))
		{
			// Trick the system into thinking we have a good array =P
			$theme['stylesheets']['stylesheet'] = array($theme['stylesheets']['stylesheet']);
		}

		// Retrieve a list of inherited stylesheets
		$query = $db->simple_select("themes", "stylesheets", "tid = '{$theme_id}'");
		if($db->num_rows($query))
		{
			$inherited_stylesheets = my_unserialize($db->fetch_field($query, "stylesheets"));

			if(is_array($inherited_stylesheets['inherited']))
			{
				$loop = 1;
				foreach($inherited_stylesheets['inherited'] as $action => $stylesheets)
				{
					foreach($stylesheets as $filename => $stylesheet)
					{
						if($properties['disporder'][basename($filename)])
						{
							continue;
						}

						$properties['disporder'][basename($filename)] = $loop;
						++$loop;
					}
				}
			}
		}

		$loop = 1;
		foreach($theme['stylesheets']['stylesheet'] as $stylesheet)
		{
			if(substr($stylesheet['attributes']['name'], -4) != ".css")
			{
				continue;
			}

			if(empty($stylesheet['attributes']['lastmodified']))
			{
				$stylesheet['attributes']['lastmodified'] = TIME_NOW;
			}

			if(empty($stylesheet['attributes']['disporder']))
			{
				$stylesheet['attributes']['disporder'] = $loop;
			}

			if(empty($stylesheet['attributes']['attachedto']))
			{
				$stylesheet['attributes']['attachedto'] = '';
			}

			$properties['disporder'][$stylesheet['attributes']['name']] = $stylesheet['attributes']['disporder'];

			$new_stylesheet = array(
				"name" => $db->escape_string($stylesheet['attributes']['name']),
				"tid" => $theme_id,
				"attachedto" => $db->escape_string($stylesheet['attributes']['attachedto']),
				"stylesheet" => $db->escape_string($stylesheet['value']),
				"lastmodified" => (int)$stylesheet['attributes']['lastmodified'],
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

			++$loop;
		}
		// Now we have our list of built stylesheets, save them
		$updated_theme = array(
			"stylesheets" => $db->escape_string(my_serialize($theme_stylesheets))
		);

		if(is_array($properties['disporder']))
		{
			asort($properties['disporder'], SORT_NUMERIC);

			// Because inherited stylesheets can mess this up
			$loop = 1;
			$orders = array();
			foreach($properties['disporder'] as $filename => $order)
			{
				$orders[$filename] = $loop;
				++$loop;
			}

			$properties['disporder'] = $orders;
			$updated_theme['properties'] = $db->escape_string(my_serialize($properties));
		}

		$db->update_query("themes", $updated_theme, "tid='{$theme_id}'");
	}

	update_theme_stylesheet_list($theme_id);

	// And done?
	return $theme_id;
}

/**
 * Parse theme variables in a specific string.
 *
 * @param string $string The string to parse variables for
 * @param array $variables Array of variables
 * @return string Parsed string with variables replaced
 */
function parse_theme_variables($string, $variables=array())
{
	$find = array();
	$replace = array();
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
 * @param string $tid The theme ID this stylesheet belongs to.
 * @param string $filename The name of the stylesheet.
 * @param string $stylesheet The contents of the stylesheet.
 *
 * @return string The cache file path.
 */
function cache_stylesheet($tid, $filename, $stylesheet)
{
	global $mybb;

	$filename = str_replace('/', '', $filename);
	$tid = (int) $tid;
	$theme_directory = "cache/themes/theme{$tid}";

	// If we're in safe mode save to the main theme folder by default
	if($mybb->safemode)
	{
		$theme_directory = "cache/themes";
		$filename = $tid."_".$filename;
	}
	// Does our theme directory exist? Try and create it.
	elseif(!is_dir(MYBB_ROOT . $theme_directory))
	{
		if(!@mkdir(MYBB_ROOT . $theme_directory))
		{
			$theme_directory = "cache/themes";
			$filename        = $tid."_".$filename;
		}
		else
		{
			// Add in empty index.html!
			$fp = @fopen(MYBB_ROOT . $theme_directory."/index.html", "w");
			@fwrite($fp, "");
			@fclose($fp);

		}
	}

	$theme_vars = array(
		"theme" => $theme_directory
	);
	$stylesheet = parse_theme_variables($stylesheet, $theme_vars);
	$stylesheet = preg_replace_callback("#url\((\"|'|)(.*)\\1\)#", create_function('$matches', 'return fix_css_urls($matches[2]);'), $stylesheet);

	$fp = @fopen(MYBB_ROOT . "{$theme_directory}/{$filename}", "wb");
	if(!$fp)
	{
		return false;
	}

	@fwrite($fp, $stylesheet);
	@fclose($fp);

	$stylesheet_min = minify_stylesheet($stylesheet);
	$filename_min = str_replace('.css', '.min.css', $filename);
	$fp_min = @fopen(MYBB_ROOT . "{$theme_directory}/{$filename_min}", "wb");
	if(!$fp_min)
	{
		return false;
	}
	@fwrite($fp_min, $stylesheet_min);
	@fclose($fp_min);

	copy_file_to_cdn(MYBB_ROOT . "{$theme_directory}/{$filename}");
	copy_file_to_cdn(MYBB_ROOT . "{$theme_directory}/{$filename_min}");

	return "{$theme_directory}/{$filename}";
}

/**
 * Minify a stylesheet to remove comments, linebreaks, whitespace,
 * unnecessary semicolons, and prefers #rgb over #rrggbb.
 *
 * @param $stylesheet string The stylesheet in it's untouched form.
 * @return string The minified stylesheet
 */
function minify_stylesheet($stylesheet)
{
	// Remove comments.
	$stylesheet = preg_replace('@/\*.*?\*/@s', '', $stylesheet);
	// Remove whitespace around symbols.
	$stylesheet = preg_replace('@\s*([{}:;,])\s*@', '\1', $stylesheet);
	// Remove unnecessary semicolons.
	$stylesheet = preg_replace('@;}@', '}', $stylesheet);
	// Replace #rrggbb with #rgb when possible.
	$stylesheet = preg_replace('@#([a-f0-9])\1([a-f0-9])\2([a-f0-9])\3@i','#\1\2\3',$stylesheet);
	$stylesheet = trim($stylesheet);
	return $stylesheet;
}

/**
 * @param array $stylesheet
 *
 * @return bool
 */
function resync_stylesheet($stylesheet)
{
	global $db;

	// Try and fix any missing cache file names
	if(!$stylesheet['cachefile'] && $stylesheet['name'])
	{
		$stylesheet['cachefile'] = $stylesheet['name'];
		$db->update_query("themestylesheets", array('cachefile' => $db->escape_string($stylesheet['name'])), "sid='{$stylesheet['sid']}'");
	}

	// Still don't have the cache file name or is it not a flat file? Return false
	if(!$stylesheet['cachefile'] || strpos($stylesheet['cachefile'], 'css.php') !== false)
	{
		return false;
	}

	if(!file_exists(MYBB_ROOT."cache/themes/theme{$stylesheet['tid']}/{$stylesheet['name']}") && !file_exists(MYBB_ROOT."cache/themes/{$stylesheet['tid']}_{$stylesheet['name']}"))
	{
		if(cache_stylesheet($stylesheet['tid'], $stylesheet['cachefile'], $stylesheet['stylesheet']) !== false)
		{
			$db->update_query("themestylesheets", array('cachefile' => $db->escape_string($stylesheet['name'])), "sid='{$stylesheet['sid']}'");

			update_theme_stylesheet_list($stylesheet['tid']);

			if($stylesheet['sid'] != 1)
			{
				$db->update_query("themestylesheets", array('lastmodified' => TIME_NOW), "sid='{$stylesheet['sid']}'");
			}
		}

		return true;
	}

	return false;
}

/**
 * @param string $url
 *
 * @return string
 */
function fix_css_urls($url)
{
	if(!preg_match("#^([a-z0-9]+\:|/)#i", $url) && strpos($url, "../../../") === false)
	{
		return "url(../../../{$url})";
	}
	else
	{
		return "url({$url})";
	}
}

/**
 * @param string $url
 *
 * @return string
 */
function unfix_css_urls($url)
{
	return str_replace("../../../", "", $url);
}

/**
 * Build a theme based on the specified parameters.
 *
 * @param string $name The name of the theme
 * @param array $properties Array of theme properties (if blank, inherits from parent)
 * @param int $parent The parent ID for this theme (defaults to Master)
 * @return int The new theme ID
 */
function build_new_theme($name, $properties=null, $parent=1)
{
	global $db;

	$new_theme = array(
		"name" => $db->escape_string($name),
		"pid" => (int)$parent,
		"def" => 0,
		"allowedgroups" => "all",
		"properties" => "",
		"stylesheets" => ""
	);
	$tid = $db->insert_query("themes", $new_theme);

	$inherited_properties = false;
	$stylesheets = array();
	if($parent > 0)
	{
		$query = $db->simple_select("themes", "*", "tid='".(int)$parent."'");
		$parent_theme = $db->fetch_array($query);
		if(count($properties) == 0 || !is_array($properties))
		{
			$parent_properties = my_unserialize($parent_theme['properties']);
			if(!empty($parent_properties))
			{
				foreach($parent_properties as $property => $value)
				{
					if($property == "inherited")
					{
						continue;
					}

					$properties[$property] = $value;
					if(!empty($parent_properties['inherited'][$property]))
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
		}

		$parent_stylesheets = my_unserialize($parent_theme['stylesheets']);
		if(!empty($parent_stylesheets))
		{
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
						if(!empty($parent_stylesheets['inherited'][$inherited_check][$stylesheet]))
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
		}
	}

	if(!$inherited_properties)
	{
		$theme_vars = array(
			"theme" => "cache/themes/theme{$tid}"
		);
		$properties['logo'] = parse_theme_variables($properties['logo'], $theme_vars);
	}
	if(!empty($stylesheets))
	{
		$updated_theme['stylesheets'] = $db->escape_string(my_serialize($stylesheets));
	}
	$updated_theme['properties'] = $db->escape_string(my_serialize($properties));

	if(count($updated_theme) > 0)
	{
		$db->update_query("themes", $updated_theme, "tid='{$tid}'");
	}

	return $tid;
}

/**
 * Generates an array from an incoming CSS file.
 *
 * @param string $css The incoming CSS
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
	preg_match_all('#(\/\*(.|[\r\n])*?\*\/)?([a-z0-9a+\\\[\]\-\"=_:>\*\.\#\,\s\(\)\|~|@\^]+)(\s*)\{(.*?)\}\n#msi', $stripped_css, $matches, PREG_PATTERN_ORDER);
	$total = count($matches[1]);

	$parsed_css = array();

	for($i=0; $i < $total; $i++)
	{
		$name = $description = '';
		$class_name = $matches[3][$i];
		$class_name = trim($class_name);
		$comments = $matches[1][$i];
		preg_match_all("#Name:(.*)#i", $comments, $name_match);
		if(isset($name_match[count($name_match)-1][0]))
		{
			$name = trim($name_match[count($name_match)-1][0]);
		}
		preg_match_all("#Description:(.*)#i", $comments, $description_match);
		if(isset($description_match[count($description_match)-1][0]))
		{
			$description = trim($description_match[count($description_match)-1][0]);
		}
		$class_id = md5($class_name);
		if(isset($already_parsed[$class_id]))
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

/**
 * @param array|string $css
 * @param int $selected_item
 *
 * @return string
 */
function get_selectors_as_options($css, $selected_item=null)
{
	$select = "";

	if(!is_array($css))
	{
		$css = css_to_array($css);
	}

	$selected = false;

	if(is_array($css))
	{
		uasort($css, "css_selectors_sort_cmp");

		foreach($css as $id => $css_array)
		{
			if(!$css_array['name'])
			{
				$css_array['name'] = $css_array['class_name'];
			}

			if($selected_item == $id || (!$selected_item && !$selected))
			{
				$select .= "<option value=\"{$id}\" selected=\"selected\">{$css_array['name']}</option>\n";
				$selected = true;
			}
			else
			{
				$select .= "<option value=\"{$id}\">{$css_array['name']}</option>\n";
			}
		}
	}
	return $select;
}

/**
 * @param array $a
 * @param array $b
 *
 * @return int
 */
function css_selectors_sort_cmp($a, $b)
{
	if(!$a['name'])
	{
		$a['name'] = $a['class_name'];
	}

	if(!$b['name'])
	{
		$b['name'] = $b['class_name'];
	}
	return strcmp($a['name'], $b['name']);
}

/**
 * @param array|string $css
 * @param string $id
 *
 * @return array|bool
 */
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
 * @param string $values Value of CSS properties from within class or selector
 * @return array Array of CSS properties
 */
function parse_css_properties($values)
{
	$css_bits = array();

	if(!$values)
	{
		return null;
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
			case "font-family":
			case "font-size":
			case "font-weight":
			case "font-style":
			case "text-decoration":
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
 * @param string $new_css CSS we wish to insert at this location.
 * @param string $selector The selector for this piece of CSS.
 * @param string $css The existing CSS if we have any.
 * @param string $class_id (Optional) The optional friendly class id value just incase the CSS is not found in the file.
 *
 * @return string The altered CSS.
 */
function insert_into_css($new_css, $selector="", $css="", $class_id="")
{
	$new_css = str_replace(array("\r\n", "\n", "\r"), "\n", $new_css);

	$generated_css = '';

	// Build the new CSS properties list
	$new_css = explode("\n", $new_css);
	foreach($new_css as $css_line)
	{
		$generated_css .= "\t".trim($css_line)."\n";
	}

	$parsed_css = array();

	// Parse out the CSS
	if($css)
	{
		$parsed_css = css_to_array($css);
	}

	if(!$class_id)
	{
		$class_id = $parsed_css[$selector]['class_name'];
	}

	// The specified class ID cannot be found, add CSS to end of file
	if(!$css || !$parsed_css[$selector])
	{
		return $css."{$class_id}\n{\n{$generated_css}\n}\n\n";
	}
	// Valid CSS, swap out old, swap in new
	else
	{
		$css = str_replace(array("\r\n", "\n", "\r"), "\n", $css);
		$css = preg_replace('#(?<!\\")\}#', "}\n", $css);
		$css = preg_replace("#^(?!@)\s*([a-z0-9a+\\\[\]\-\"=_:>\*\.\#\,\s\(\)\|~\^]+)(\s*)\{(\n*)#isu", "\n$1 {\n", $css);
		$css = preg_replace("#\s{1,}\{#", " {", $css);
		$existing_block = $parsed_css[$selector];

		$break = strrpos($selector, "_");
		$actual_occurance = 0;
		if($break !== false)
		{
			$actual_occurance = (int)substr($selector, ($break+1));
		}

		if(!$actual_occurance)
		{
			$actual_occurance = 1;
		}

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

/**
 * @param array $stylesheet
 * @param int $tid
 *
 * @return bool|int
 */
function copy_stylesheet_to_theme($stylesheet, $tid)
{
	global $db;

	$stylesheet['tid'] = $tid;
	unset($stylesheet['sid']);

	$new_stylesheet = array();
	foreach($stylesheet as $key => $value)
	{
		if(!is_numeric($key))
		{
			$new_stylesheet[$db->escape_string($key)] = $db->escape_string($value);
		}
	}

	$sid = $db->insert_query("themestylesheets", $new_stylesheet);

	return $sid;
}

/**
 * @param int $tid
 * @param bool|array $theme
 * @param bool $update_disporders
 *
 * @return bool
 */
function update_theme_stylesheet_list($tid, $theme = false, $update_disporders = true)
{
	global $mybb, $db, $cache, $plugins;

	$stylesheets = array();

	$child_list = make_child_theme_list($tid);
	$parent_list = make_parent_theme_list($tid);

	if(!is_array($parent_list))
	{
		return false;
	}

	$tid_list = implode(',', $parent_list);

	// Get our list of stylesheets
	$query = $db->simple_select("themestylesheets", "*", "tid IN ({$tid_list})", array('order_by' => 'tid', 'order_dir' => 'desc'));
	while($stylesheet = $db->fetch_array($query))
	{
		if(empty($stylesheets[$stylesheet['name']]))
		{
			if($stylesheet['tid'] != $tid)
			{
				$stylesheet['inherited'] = $stylesheet['tid'];
			}

			$stylesheets[$stylesheet['name']] = $stylesheet;
		}
	}

	$theme_stylesheets = array();

	foreach($stylesheets as $name => $stylesheet)
	{
		$sid = $stylesheet['sid'];
		$css_url = "css.php?stylesheet={$sid}";

		foreach($parent_list as $theme_id)
		{
			if($mybb->settings['usecdn'] && !empty($mybb->settings['cdnpath']))
			{
				$cdnpath = rtrim($mybb->settings['cdnpath'], '/\\').'/';
				if(file_exists($cdnpath."cache/themes/theme{$theme_id}/{$stylesheet['name']}") && filemtime(
						$cdnpath."cache/themes/theme{$theme_id}/{$stylesheet['name']}"
					) >= $stylesheet['lastmodified']
				)
				{
					$css_url = "cache/themes/theme{$theme_id}/{$stylesheet['name']}";
					break;
				}
			}
			else
			{
				if(file_exists(MYBB_ROOT."cache/themes/theme{$theme_id}/{$stylesheet['name']}") && filemtime(
						MYBB_ROOT."cache/themes/theme{$theme_id}/{$stylesheet['name']}"
					) >= $stylesheet['lastmodified']
				)
				{
					$css_url = "cache/themes/theme{$theme_id}/{$stylesheet['name']}";
					break;
				}
			}
		}
		
		if(is_object($plugins))
		{
			$plugins->run_hooks('update_theme_stylesheet_list_set_css_url', $css_url);
		}

		$attachedto = $stylesheet['attachedto'];
		if(!$attachedto)
		{
			$attachedto = "global";
		}
		// private.php?compose,folders|usercp.php,global|global
		$attachedto = explode("|", $attachedto);
		foreach($attachedto as $attached_file)
		{
			$attached_actions = array();
			if(strpos($attached_file, '?') !== false)
			{
				$attached_file = explode('?', $attached_file);
				$attached_actions = explode(",", $attached_file[1]);
				$attached_file = $attached_file[0];
			}

			if(count($attached_actions) == 0)
			{
				$attached_actions = array("global");
			}

			foreach($attached_actions as $action)
			{
				$theme_stylesheets[$attached_file][$action][] = $css_url;

				if(!empty($stylesheet['inherited']))
				{
					$theme_stylesheets['inherited']["{$attached_file}_{$action}"][$css_url] = $stylesheet['inherited'];
				}
			}
		}
	}

	// Now we have our list of built stylesheets, save them
	$updated_theme = array(
		"stylesheets" => $db->escape_string(my_serialize($theme_stylesheets))
	);

	// Do we have a theme present? If so, update the stylesheet display orders
	if($update_disporders)
	{
		if(!is_array($theme) || !$theme)
		{
			$theme_cache = cache_themes();
			$theme = $theme_cache[$tid];
		}

		$orders = $orphaned_stylesheets = array();
		$properties = $theme['properties'];

		if(!is_array($properties))
		{
			$properties = my_unserialize($theme['properties']);
		}
		
		$max_disporder = 0;

		foreach($stylesheets as $stylesheet)
		{
			if(!isset($properties['disporder'][$stylesheet['name']]))
			{
				$orphaned_stylesheets[] = $stylesheet['name'];
				continue;
			}
			
			if($properties['disporder'][$stylesheet['name']] > $max_disporder)
			{
				$max_disporder = $properties['disporder'][$stylesheet['name']];
			}

			$orders[$stylesheet['name']] = $properties['disporder'][$stylesheet['name']];
		}

		if(!empty($orphaned_stylesheets))
		{
			$loop = $max_disporder + 1;
			$max_disporder = $loop;
			foreach($orphaned_stylesheets as $stylesheet)
			{
				$orders[$stylesheet] = $loop;
				++$loop;
			}
		}

		asort($orders);
		$properties['disporder'] = $orders;
		$updated_theme['properties'] = $db->escape_string(my_serialize($properties));
	}

	$db->update_query("themes", $updated_theme, "tid = '{$tid}'");

	// Do we have any children themes that need updating too?
	if(count($child_list) > 0)
	{
		foreach($child_list as $id)
		{
			update_theme_stylesheet_list($id, false, $update_disporders);
		}
	}

	$cache->update_default_theme();

	return true;
}

/**
 * @param int $tid
 *
 * @return array|bool
 */
function make_parent_theme_list($tid)
{
	static $themes_by_parent;

	$themes = array();
	if(!is_array($themes_by_parent))
	{
		$theme_cache = cache_themes();
		foreach($theme_cache as $key => $theme)
		{
			if($key == "default")
			{
				continue;
			}

			$themes_by_parent[$theme['tid']][$theme['pid']] = $theme;
		}
	}

	if(!isset($themes_by_parent[$tid]) || !is_array($themes_by_parent[$tid]))
	{
		return false;
	}

	reset($themes_by_parent);
	reset($themes_by_parent[$tid]);

	$themes = array();

	foreach($themes_by_parent[$tid] as $key => $theme)
	{
		$themes[] = $theme['tid'];
		$parents = make_parent_theme_list($theme['pid']);

		if(is_array($parents))
		{
			$themes = array_merge($themes, $parents);
		}
	}

	return $themes;
}

/**
 * @param int $tid
 *
 * @return array|null
 */
function make_child_theme_list($tid)
{
	static $themes_by_child;

	$themes = array();
	if(!is_array($themes_by_child))
	{
		$theme_cache = cache_themes();
		foreach($theme_cache as $key => $theme)
		{
			if($key == "default")
			{
				continue;
			}

			$themes_by_child[$theme['pid']][$theme['tid']] = $theme;
		}
	}

	if(!isset($themes_by_child[$tid]) || !is_array($themes_by_child[$tid]))
	{
		return null;
	}

	$themes = array();

	foreach($themes_by_child[$tid] as $theme)
	{
		$themes[] = $theme['tid'];
		$children = make_child_theme_list($theme['tid']);

		if(is_array($children))
		{
			$themes = array_merge($themes, $children);
		}
	}

	return $themes;
}

/**
 * @return array
 */
function cache_themes()
{
	global $db, $theme_cache;

	if(empty($theme_cache) || !is_array($theme_cache))
	{
		$query = $db->simple_select("themes", "*", "", array('order_by' => "pid, name"));
		while($theme = $db->fetch_array($query))
		{
			$theme['properties'] = my_unserialize($theme['properties']);
			$theme['stylesheets'] = my_unserialize($theme['stylesheets']);
			$theme_cache[$theme['tid']] = $theme;

			if($theme['def'] == 1)
			{
				$theme_cache['default'] = $theme['tid'];
			}
		}
	}

	// Do we have no themes assigned as default?
	if(empty($theme_cache['default']))
	{
		$theme_cache['default'] = 1;
	}

	return $theme_cache;
}

/**
 * @param int $parent
 * @param int $depth
 */
function build_theme_list($parent=0, $depth=0)
{
	global $mybb, $db, $table, $lang, $page; // Global $table is bad, but it will have to do for now
	static $theme_cache;

	$padding = $depth*20; // Padding

	if(!is_array($theme_cache))
	{
		$themes = cache_themes();
		$query = $db->simple_select("users", "style, COUNT(uid) AS users", "", array('group_by' => 'style'));
		while($user_themes = $db->fetch_array($query))
		{
			if($user_themes['style'] == 0)
			{
				$user_themes['style'] = $themes['default'];
			}

			if($themes[$user_themes['style']]['users'] > 0)
			{
				$themes[$user_themes['style']]['users'] += (int)$user_themes['users'];
			}
			else
			{
				$themes[$user_themes['style']]['users'] = (int)$user_themes['users'];
			}
		}

		// Restrucure the theme array to something we can "loop-de-loop" with
		foreach($themes as $key => $theme)
		{
			if($key == "default")
			{
				continue;
			}

			$theme_cache[$theme['pid']][$theme['tid']] = $theme;
		}
		$theme_cache['num_themes'] = count($themes);
		unset($themes);
	}

	if(!is_array($theme_cache[$parent]))
	{
		return;
	}

	foreach($theme_cache[$parent] as $theme)
	{
		$popup = new PopupMenu("theme_{$theme['tid']}", $lang->options);
		if($theme['tid'] > 1)
		{
			$popup->add_item($lang->edit_theme, "index.php?module=style-themes&amp;action=edit&amp;tid={$theme['tid']}");
			$theme['name'] = "<a href=\"index.php?module=style-themes&amp;action=edit&amp;tid={$theme['tid']}\">".htmlspecialchars_uni($theme['name'])."</a>";

			// We must have at least the master and 1 other active theme
			if($theme_cache['num_themes'] > 2)
			{
				$popup->add_item($lang->delete_theme, "index.php?module=style-themes&amp;action=delete&amp;tid={$theme['tid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_theme_deletion}')");
			}

			if($theme['def'] != 1)
			{
				$popup->add_item($lang->set_as_default, "index.php?module=style-themes&amp;action=set_default&amp;tid={$theme['tid']}&amp;my_post_key={$mybb->post_code}");
				$set_default = "<a href=\"index.php?module=style-themes&amp;action=set_default&amp;tid={$theme['tid']}&amp;my_post_key={$mybb->post_code}\"><img src=\"styles/{$page->style}/images/icons/make_default.png\" alt=\"{$lang->set_as_default}\" style=\"vertical-align: middle;\" title=\"{$lang->set_as_default}\" /></a>";
			}
			else
			{
				$set_default = "<img src=\"styles/{$page->style}/images/icons/default.png\" alt=\"{$lang->default_theme}\" style=\"vertical-align: middle;\" title=\"{$lang->default_theme}\" />";
			}
			$popup->add_item($lang->force_on_users, "index.php?module=style-themes&amp;action=force&amp;tid={$theme['tid']}&amp;my_post_key={$mybb->post_code}", "return AdminCP.deleteConfirmation(this, '{$lang->confirm_theme_forced}')");
		}
		$popup->add_item($lang->export_theme, "index.php?module=style-themes&amp;action=export&amp;tid={$theme['tid']}");
		$popup->add_item($lang->duplicate_theme, "index.php?module=style-themes&amp;action=duplicate&amp;tid={$theme['tid']}");
		$table->construct_cell("<div class=\"float_right\">{$set_default}</div><div style=\"margin-left: {$padding}px;\"><strong>{$theme['name']}</strong></div>");
		$table->construct_cell(my_number_format($theme['users']), array("class" => "align_center"));
		$table->construct_cell($popup->fetch(), array("class" => "align_center"));
		$table->construct_row();

		// Fetch & build any child themes
		build_theme_list($theme['tid'], ++$depth);
	}
}

/**
 * returns an array which can be sent to generate_select_box()
 *
 * @param int $ignoretid
 * @param int  $parent
 * @param int  $depth
 *
 * @return null|string
 */
function build_theme_array($ignoretid = null, $parent=0, $depth=0)
{
	global $list;
	static $theme_cache;

	if(!is_array($theme_cache))
	{
		$themes = cache_themes();
		// Restrucure the theme array to something we can "loop-de-loop" with
		foreach($themes as $key => $theme)
		{
			if($key == "default")
			{
				continue;
			}

			$theme_cache[$theme['pid']][$theme['tid']] = $theme;
		}
		unset($theme);
	}

	if(!is_array($theme_cache[$parent]) || $ignoretid === $parent)
	{
		return null;
	}

	foreach($theme_cache[$parent] as $theme)
	{
		if($ignoretid === $theme['tid'])
		{
			continue;
		}

		$list[$theme['tid']] = str_repeat("--", $depth).$theme['name'];
		// Fetch & build any child themes
		build_theme_array($ignoretid, $theme['tid'], $depth+1);
	}

	if(!$parent)
	{
		return $list;
	}
}

/**
 * @param array $theme
 *
 * @return array|bool
 */
function fetch_theme_stylesheets($theme)
{
	// Fetch list of all of the stylesheets for this theme
	$file_stylesheets = my_unserialize($theme['stylesheets']);

	if(!is_array($file_stylesheets))
	{
		return false;
	}

	$stylesheets = array();
	$inherited_load = array();

	// Now we loop through the list of stylesheets for each file
	foreach($file_stylesheets as $file => $action_stylesheet)
	{
		if($file == 'inherited')
		{
			continue;
		}

		foreach($action_stylesheet as $action => $style)
		{
			foreach($style as $stylesheet2)
			{
				$stylesheets[$stylesheet2]['applied_to'][$file][] = $action;
				if(is_array($file_stylesheets['inherited'][$file."_".$action]) && in_array($stylesheet2, array_keys($file_stylesheets['inherited'][$file."_".$action])))
				{
					$stylesheets[$stylesheet2]['inherited'] = $file_stylesheets['inherited'][$file."_".$action];
					foreach($file_stylesheets['inherited'][$file."_".$action] as $value)
					{
						$inherited_load[] = $value;
					}
				}
			}
		}
	}

	foreach($stylesheets as $file => $stylesheet2)
	{
		if(is_array($stylesheet2['inherited']))
		{
			foreach($stylesheet2['inherited'] as $inherited_file => $tid)
			{
				$stylesheet2['inherited'][basename($inherited_file)] = $tid;
				unset($stylesheet2['inherited'][$inherited_file]);
			}
		}

		$stylesheets[basename($file)] = $stylesheet2;
		unset($stylesheets[$file]);
	}

	return $stylesheets;
}

/**
 * @param string $css
 *
 * @return string
 */
function upgrade_css_120_to_140($css)
{
	// Update our CSS to the new stuff in 1.4
	$parsed_css = css_to_array($css);

	if(!is_array($parsed_css))
	{
		return "";
	}

	foreach($parsed_css as $class_id => $array)
	{
		$parsed_css[$class_id]['values'] = str_replace('#eea8a1', '#ffdde0', $array['values']);
		$parsed_css[$class_id]['values'] = str_replace('font-family: Verdana;', 'font-family: Verdana, Arial, Sans-Serif;', $array['values']);

		switch($array['class_name'])
		{
			case '.bottommenu':
				$parsed_css[$class_id]['values'] = str_replace('padding: 6px;', 'padding: 10px;', $array['values']);
				break;
			case '.expcolimage':
				$parsed_css[$class_id]['values'] .= "\n\tmargin-top: 2px;";
				break;
			case '.toolbar_normal':
			case '.toolbar_hover':
			case '.toolbar_clicked':
			case '.pagenav':
			case '.pagenavbit':
			case '.pagenavbit a':
			case '.pagenavcurrent':
			case '.quote_header':
			case '.quote_body':
			case '.code_header':
			case '.code_body':
			case '.usercpnav':
			case '.usercpnav li':
			case '.usercpnav .pmfolders':
				unset($parsed_css[$class_id]);
				break;
			default:
		}
	}

	$to_add = array(
		md5('.trow_selected td') => array("class_name" => '.trow_selected td', "values" => 'background: #FFFBD9;'),
		md5('blockquote') => array("class_name" => 'blockquote', "values" => "border: 1px solid #ccc;\n\tmargin: 0;\n\tbackground: #fff;\n\tpadding: 4px;"),
		md5('blockquote cite') => array("class_name" => 'blockquote cite', "values" => "font-weight: bold;\n\tborder-bottom: 1px solid #ccc;\n\tfont-style: normal;\n\tdisplay: block;\n\tmargin: 4px 0;"),
		md5('blockquote cite span') => array("class_name" => 'blockquote cite span', "values" => "float: right;\n\tfont-weight: normal;"),
		md5('.codeblock') => array("class_name" => '.codeblock', "values" => "background: #fff;\n\tborder: 1px solid #ccc;\n\tpadding: 4px;"),
		md5('.codeblock .title') => array("class_name" => '.codeblock .title', "values" => "border-bottom: 1px solid #ccc;\n\tfont-weight: bold;\n\tmargin: 4px 0;"),
		md5('.codeblock code') => array("class_name" => '.codeblock code', "values" => "overflow: auto;\n\theight: auto;\n\tmax-height: 200px;\n\tdisplay: block;\n\tfont-family: Monaco, Consolas, Courier, monospace;\n\tfont-size: 13px;"),
		md5('.subject_new') => array("class_name" => '.subject_new', "values" => "font-weight: bold;"),
		md5('.highlight') => array("class_name" => '.highlight', "values" => "background: #FFFFCC;\n\tpadding: 3px;"),
		md5('.pm_alert') => array("class_name" => '.pm_alert', "values" => "background: #FFF6BF;\n\tborder: 1px solid #FFD324;\n\ttext-align: center;\n\tpadding: 5px 20px;\n\tfont-size: 11px;"),
		md5('.red_alert') => array("class_name" => '.red_alert', "values" => "background: #FBE3E4;\n\tborder: 1px solid #A5161A;\n\tcolor: #A5161A;\n\ttext-align: center;\n\tpadding: 5px 20px;\n\tfont-size: 11px;"),
		md5('.high_warning') => array("class_name" => '.high_warning', "values" => "color: #CC0000;"),
		md5('.moderate_warning') => array("class_name" => '.moderate_warning', "values" => "color: #F3611B;"),
		md5('.low_warning') => array("class_name" => '.low_warning', "values" => "color: #AE5700;"),
		md5('div.error') => array("class_name" => 'div.error', "values" => "padding: 5px 10px;\n\tborder-top: 2px solid #FFD324;\n\tborder-bottom: 2px solid #FFD324;\n\tbackground: #FFF6BF\n\tfont-size: 12px;"),
		md5('.high_warning') => array("class_name" => '.high_warning', "values" => "color: #CC0000;"),
		md5('.moderate_warning') => array("class_name" => '.moderate_warning', "values" => "color: #F3611B;"),
		md5('.low_warning') => array("class_name" => '.low_warning', "values" => "color: #AE5700;"),
		md5('div.error') => array("class_name" => 'div.error', "values" => "padding: 5px 10px;\n\tborder-top: 2px solid #FFD324;\n\tborder-bottom: 2px solid #FFD324;\n\tbackground: #FFF6BF;\n\tfont-size: 12px;"),
		md5('div.error p') => array("class_name" => 'div.error p', "values" => "margin: 0;\n\tcolor: #000;\n\tfont-weight: normal;"),
		md5('div.error p em') => array("class_name" => 'div.error p em', "values" => "font-style: normal;\n\tfont-weight: bold;\n\tpadding-left: 24px;\n\tdisplay: block;\n\tcolor: #C00;\n\tbackground: url({$mybb->settings['bburl']}/images/error.png) no-repeat 0;"),
		md5('div.error.ul') => array("class_name" => 'div.error.ul', "values" => "margin-left: 24px;"),
		md5('.online') => array("class_name" => '.online', "values" => "color: #15A018;"),
		md5('.offline') => array("class_name" => '.offline', "values" => "color: #C7C7C7;"),
		md5('.pagination') => array("class_name" => '.pagination', "values" => "font-size: 11px;\n\tpadding-top: 10px;\n\tmargin-bottom: 5px;"),
		md5('.tfoot .pagination, .tcat .pagination') => array("class_name" => '.tfoot .pagination, .tcat .pagination', "values" => "padding-top: 0;"),
		md5('.pagination .pages') => array("class_name" => '.pagination .pages', "values" => "font-weight: bold;"),
		md5('.pagination .pagination_current, .pagination a') => array("class_name" => '.pagination .pagination_current, .pagination a', "values" => "padding: 2px 6px;\n\tmargin-bottom: 3px;"),
		md5('.pagination a') => array("class_name" => '.pagination a', "values" => "border: 1px solid #81A2C4;"),
		md5('.pagination .pagination_current') => array("class_name" => '.pagination .pagination_current', "values" => "background: #F5F5F5;\n\tborder: 1px solid #81A2C4;\n\tfont-weight: bold;"),
		md5('.pagination a:hover') => array("class_name" => '.pagination a:hover', "values" => "background: #F5F5F5;\n\ttext-decoration: none;"),
		md5('.thread_legend, .thread_legend dd') => array("class_name" => '.thread_legend, .thread_legend dd', "values" => "margin: 0;\n\tpadding: 0;"),
		md5('.thread_legend dd') => array("class_name" => '.thread_legend dd', "values" => "padding-bottom: 4px;\n\tmargin-right: 15px;"),
		md5('.thread_legend img') => array("class_name" => '.thread_legend img', "values" => "margin-right: 4px;\n\tvertical-align: bottom;"),
		md5('.forum_legend, .forum_legend dt, .forum_legend dd') => array("class_name" => '.forum_legend, .forum_legend dt, .forum_legend dd', "values" => "margin: 0;\n\tpadding: 0;"),
		md5('.forum_legend dd') => array("class_name" => '.forum_legend dd', "values" => "float: left;\n\tmargin-right: 10px;"),
		md5('.forum_legend dt') => array("class_name" => '.forum_legend dt', "values" => "margin-right: 10px;\n\tfloat: left;"),
		md5('.success_message') => array("class_name" => '.success_message', "values" => "color: #00b200;\n\tfont-weight: bold;\n\tfont-size: 10px;\n\tmargin-bottom: 10px;"),
		md5('.error_message') => array("class_name" => '.error_message', "values" => "color: #C00;\n\tfont-weight: bold;\n\tfont-size: 10px;\n\tmargin-bottom: 10px;"),
		md5('.post_body') => array("class_name" => '.post_body', "values" => "padding: 5px;"),
		md5('.post_content') => array("class_name" => '.post_content', "values" => "padding: 5px 10px;"),
		md5('.invalid_field') => array("class_name" => '.invalid_field', "values" => "border: 1px solid #f30;\n\tcolor: #f30;"),
		md5('.valid_field') => array("class_name" => '.valid_field', "values" => "border: 1px solid #0c0;"),
		md5('.validation_error') => array("class_name" => '.validation_error', "values" => "background: url(images/invalid.png) no-repeat center left;\n\tcolor: #f30;\n\tmargin: 5px 0;\n\tpadding: 5px;\n\tfont-weight: bold;\n\tfont-size: 11px;\n\tpadding-left: 22px;"),
		md5('.validation_success') => array("class_name" => '.validation_success', "values" => "background: url(images/valid.png) no-repeat center left;\n\tcolor: #00b200;\n\tmargin: 5px 0;\n\tpadding: 5px;\n\tfont-weight: bold;\n\tfont-size: 11px;\n\tpadding-left: 22px;"),
		md5('.validation_loading') => array("class_name" => '.validation_loading', "values" => "background: url(images/spinner.gif) no-repeat center left;\n\tcolor: #555;\n\tmargin: 5px 0;\n\tpadding: 5px;\n\tfont-weight: bold;\n\tfont-size: 11px;\n\tpadding-left: 22px;"),
	);

	$already_parsed = array();

	foreach($to_add as $class_id => $array)
	{
		if($already_parsed[$class_id])
		{
			$already_parsed[$class_id]++;
			$class_id .= "_".$already_parsed[$class_id];
		}
		else
		{
			$already_parsed[$class_id] = 1;
		}

		$array['name'] = "";
		$array['description'] = "";

		$parsed_css[$class_id] = $array;
	}

	$theme = array(
		'css' => '',
	);

	$css = "";
	foreach($parsed_css as $class_id => $array)
	{
		if($array['name'] || $array['description'])
		{
			$theme['css'] .= "/* ";
			if($array['name'])
			{
				$array['css'] .= "Name: {$array['name']}";

				if($array['description'])
				{
					$array['css'] .= "\n";
				}
			}

			if($array['description'])
			{
				$array['css'] .= "Description: {$array['description']}";
			}

			$array['css'] .= " */\n";
		}

		$css .= "{$array['class_name']} {\n\t{$array['values']}\n}\n";
	}

	return $css;
}
