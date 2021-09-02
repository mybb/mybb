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
 * Generate a form on the page.
 */
class DefaultForm
{
	/**
	 * @var boolean Should this form be returned instead of output to the browser?
	 */
	private $_return = false;

	/**
	 * @var string Contents of the form if $_return is true from __construct
	 */
	public $construct_return = "";

	/**
	 * Constructor. Outputs the form tag with the specified options.
	 *
	 * @param string $script The action for the form.
	 * @param string $method The method (get or post) for this form.
	 * @param string $id The ID of the form.
	 * @param boolean $allow_uploads Should file uploads be allowed for this form?
	 * @param string $name The name of the form
	 * @param boolean $return Should this form be returned instead of output to the browser?
	 * @param string $onsubmit The onsubmit action of the form
	 */
	function __construct($script='', $method='', $id="", $allow_uploads=false, $name="", $return=false, $onsubmit="")
	{
		global $mybb;
		$form = "<form action=\"{$script}\" method=\"{$method}\"";
		if($allow_uploads != false)
		{
			$form .= " enctype=\"multipart/form-data\"";
		}

		if($name != "")
		{
			$form .= " name=\"{$name}\"";
		}

		if($id != "")
		{
			$form .= " id=\"{$id}\"";
		}

		if($onsubmit != "")
		{
			$form .= " onsubmit=\"{$onsubmit}\"";
		}
		$form .= ">\n";
		$form .= $this->generate_hidden_field("my_post_key", $mybb->post_code)."\n";
		if($return == false)
		{
			echo $form;
		}
		else
		{
			$this->_return = true;
			$this->construct_return = $form;
		}
	}

	/**
	 * Generate and return a hidden field.
	 *
	 * @param string $name The name of the hidden field.
	 * @param string $value The value of the hidden field.
	 * @param array $options Optional array of options (id)
	 * @return string The generated hidden
	 */
	function generate_hidden_field($name, $value, $options=array())
	{
		$input = "<input type=\"hidden\" name=\"{$name}\" value=\"".htmlspecialchars_uni($value)."\"";
		if(isset($options['id']))
		{
			$input .= " id=\"".$options['id']."\"";
		}
		$input .= " />";
		return $input;
	}

	/**
	 * Generate a text box field.
	 *
	 * @param string $name The name of the text box.
	 * @param string $value The value of the text box.
	 * @param array $options Array of options for the text box (class, style, id)
	 * @return string The generated text box.
	 */
	function generate_text_box($name, $value="", $options=array())
	{
		$input = "<input type=\"text\" name=\"".$name."\" value=\"".htmlspecialchars_uni($value)."\"";
		if(isset($options['class']))
		{
			$input .= " class=\"text_input ".$options['class']."\"";
		}
		else
		{
			$input .= " class=\"text_input\"";
		}
		if(isset($options['style']))
		{
			$input .= " style=\"".$options['style']."\"";
		}
		if(isset($options['id']))
		{
			$input .= " id=\"".$options['id']."\"";
		}
		$input .= " />";
		return $input;
	}

	/**
	 * Generate a numeric field.
	 *
	 * @param string $name The name of the numeric box.
	 * @param int $value The value of the numeric box.
	 * @param array $options Array of options for the numeric box (min, max, step, class, style, id)
	 * @return string The generated numeric box.
	 */
	function generate_numeric_field($name, $value=0, $options=array())
	{
		if(is_numeric($value))
		{
			$value = (float)$value;
		}
		else
		{
			$value = '';
		}

		$input = "<input type=\"number\" name=\"{$name}\" value=\"{$value}\"";
		if(isset($options['min']))
		{
			$input .= " min=\"".$options['min']."\"";
		}
		if(isset($options['max']))
		{
			$input .= " max=\"".$options['max']."\"";
		}
		if(isset($options['step']))
		{
			$input .= " step=\"".$options['step']."\"";
		}
		if(isset($options['class']))
		{
			$input .= " class=\"text_input ".$options['class']."\"";
		}
		else
		{
			$input .= " class=\"text_input\"";
		}
		if(isset($options['style']))
		{
			$input .= " style=\"".$options['style']."\"";
		}
		if(isset($options['id']))
		{
			$input .= " id=\"".$options['id']."\"";
		}
		$input .= " />";
		return $input;
	}

	/**
	 * Generate a password input box.
	 *
	 * @param string $name The name of the password box.
	 * @param string $value The value of the password box.
	 * @param array $options Array of options for the password box (class, id, autocomplete)
	 * @return string The generated password input box.
	 */
	function generate_password_box($name, $value="", $options=array())
	{
		$input = "<input type=\"password\" name=\"".$name."\" value=\"".htmlspecialchars_uni($value)."\"";
		if(isset($options['class']))
		{
			$input .= " class=\"text_input ".$options['class']."\"";
		}
		else
		{
			$input .= " class=\"text_input\"";
		}
		if(isset($options['id']))
		{
			$input .= " id=\"".$options['id']."\"";
		}
		if(isset($options['autocomplete']))
		{
			$input .= " autocomplete=\"".$options['autocomplete']."\"";
		}
		$input .= " />";
		return $input;
	}

	/**
	 * Generate a file upload field.
	 *
	 * @param string $name The name of the file upload field.
	 * @param array $options Array of options for the file upload field (class, id, style)
	 * @return string The generated file upload field.
	 */
	function generate_file_upload_box($name, $options=array())
	{
		$input = "<input type=\"file\" name=\"".$name."\"";
		if(isset($options['class']))
		{
			$input .= " class=\"text_input ".$options['class']."\"";
		}
		else
		{
			$input .= " class=\"text_input\"";
		}
		if(isset($options['style']))
		{
			$input .= " style=\"".$options['style']."\"";
		}
		if(isset($options['id']))
		{
			$input .= " id=\"".$options['id']."\"";
		}
		$input .= " />";
		return $input;

	}

	/**
	 * Generate a text area.
	 *
	 * @param string $name The name of of the text area.
	 * @param string $value The value of the text area field.
	 * @param array $options Array of options for text area (class, id, rows, cols, style, disabled, maxlength, readonly)
	 * @return string The generated text area field.
	 */
	function generate_text_area($name, $value="", $options=array())
	{
		$textarea = "<textarea";
		if(!empty($name))
		{
			$textarea .= " name=\"{$name}\"";
		}
		if(isset($options['class']))
		{
			$textarea .= " class=\"{$options['class']}\"";
		}
		if(isset($options['id']))
		{
			$textarea .= " id=\"{$options['id']}\"";
		}
		if(isset($options['style']))
		{
			$textarea .= " style=\"{$options['style']}\"";
		}
		if(isset($options['disabled']) && $options['disabled'] !== false)
		{
			$textarea .= " disabled=\"disabled\"";
		}
		if(isset($options['readonly']) && $options['readonly'] !== false)
		{
			$textarea .= " readonly=\"readonly\"";
		}
		if(isset($options['maxlength']))
		{
			$textarea .= " maxlength=\"{$options['maxlength']}\"";
		}
		if(!isset($options['rows']))
		{
			$options['rows'] = 5;
		}
		if(!isset($options['cols']))
		{
			$options['cols'] = 45;
		}
		$textarea .= " rows=\"{$options['rows']}\" cols=\"{$options['cols']}\">";
		$textarea .= htmlspecialchars_uni($value);
		$textarea .= "</textarea>";
		return $textarea;
	}

	/**
	 * Generate a radio button.
	 *
	 * @param string $name The name of the radio button.
	 * @param string $value The value of the radio button
	 * @param string $label The label of the radio button if there is one.
	 * @param array $options Array of options for the radio button (id, class, checked)
	 * @return string The generated radio button.
	 */
	function generate_radio_button($name, $value="", $label="", $options=array())
	{
		$input = "<label";
		if(isset($options['id']))
		{
			$input .= " for=\"{$options['id']}\"";
		}
		if(isset($options['class']))
		{
			$input .= " class=\"label_{$options['class']}\"";
		}
		$input .= "><input type=\"radio\" name=\"{$name}\" value=\"".htmlspecialchars_uni($value)."\"";
		if(isset($options['class']))
		{
			$input .= " class=\"radio_input ".$options['class']."\"";
		}
		else
		{
			$input .= " class=\"radio_input\"";
		}
		if(isset($options['id']))
		{
			$input .= " id=\"".$options['id']."\"";
		}
		if(isset($options['checked']) && $options['checked'] != 0)
		{
			$input .= " checked=\"checked\"";
		}
		$input .= " />";
		if($label != "")
		{
			$input .= $label;
		}
		$input .= "</label>";
		return $input;
	}

	/**
	 * Generate a checkbox.
	 *
	 * @param string $name The name of the check box.
	 * @param string $value The value of the check box.
	 * @param string $label The label of the check box if there is one.
	 * @param array $options Array of options for the check box (id, class, checked, onclick)
	 * @return string The generated check box.
	 */
	function generate_check_box($name, $value="", $label="", $options=array())
	{
		$input = "<label";
		if(isset($options['id']))
		{
			$input .= " for=\"{$options['id']}\"";
		}
		if(isset($options['class']))
		{
			$input .= " class=\"label_{$options['class']}\"";
		}
		$input .= "><input type=\"checkbox\" name=\"{$name}\" value=\"".htmlspecialchars_uni($value)."\"";
		if(isset($options['class']))
		{
			$input .= " class=\"checkbox_input ".$options['class']."\"";
		}
		else
		{
			$input .= " class=\"checkbox_input\"";
		}
		if(isset($options['id']))
		{
			$input .= " id=\"".$options['id']."\"";
		}
		if(isset($options['checked']) && ($options['checked'] === true || $options['checked'] == 1))
		{
			$input .= " checked=\"checked\"";
		}
		if(isset($options['onclick']))
		{
			$input .= " onclick=\"{$options['onclick']}\"";
		}
		$input .= " /> ";
		if($label != "")
		{
			$input .= $label;
		}
		$input .= "</label>";
		return $input;
	}

	/**
	 * Generate a select box.
	 *
	 * @param string $name The name of the select box.
	 * @param array $option_list Array of options in key => val format.
	 * @param string|array $selected Either a string containing the selected item or an array containing multiple selected items (options['multiple'] must be true)
	 * @param array $options Array of options for the select box (multiple, class, id, size)
	 * @return string The select box.
	 */
	function generate_select_box($name, $option_list=array(), $selected=array(), $options=array())
	{
		if(!isset($options['multiple']))
		{
			$select = "<select name=\"{$name}\"";
		}
		else
		{
			$select = "<select name=\"{$name}\" multiple=\"multiple\"";
			if(!isset($options['size']))
			{
				$options['size'] = count($option_list);
			}
		}
		if(isset($options['class']))
		{
			$select .= " class=\"{$options['class']}\"";
		}
		if(isset($options['id']))
		{
			$select .= " id=\"{$options['id']}\"";
		}
		if(isset($options['size']))
		{
			$select .= " size=\"{$options['size']}\"";
		}
		$select .= ">\n";
		foreach($option_list as $value => $option)
		{
			$select_add = '';
			if((!is_array($selected) || !empty($selected)) && ((is_array($selected) && in_array((string)$value, $selected)) || (!is_array($selected) && (string)$value === (string)$selected)))
			{
				$select_add = " selected=\"selected\"";
			}
			$select .= "<option value=\"{$value}\"{$select_add}>{$option}</option>\n";
		}
		$select .= "</select>\n";
		return $select;
	}

	/**
	 * Generate a forum selection box.
	 *
	 * @param string $name The name of the selection box.
	 * @param array|string $selected Array/string of the selected items.
	 * @param array Array of options (pid, main_option, multiple, depth)
	 * @param boolean|int $is_first Is this our first iteration of this function?
	 * @return string The built select box.
	 */
	function generate_forum_select($name, $selected, $options=array(), $is_first=1)
	{
		global $fselectcache, $forum_cache, $selectoptions;

		if(!$selectoptions)
		{
			$selectoptions = '';
		}

		if(!isset($options['depth']))
		{
			$options['depth'] = 0;
		}

		$options['depth'] = (int)$options['depth'];

		if(!isset($options['pid']))
		{
			$options['pid'] = 0;
		}

		$pid = (int)$options['pid'];

		if(!is_array($fselectcache))
		{
			if(!is_array($forum_cache))
			{
				$forum_cache = cache_forums();
			}

			foreach($forum_cache as $fid => $forum)
			{
				$fselectcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
			}
		}

		if(isset($options['main_option']) && $is_first)
		{
			$select_add = '';
			if($selected == -1)
			{
				$select_add = " selected=\"selected\"";
			}

			$selectoptions .= "<option value=\"-1\"{$select_add}>{$options['main_option']}</option>\n";
		}

		if(isset($fselectcache[$pid]))
		{
			foreach($fselectcache[$pid] as $main)
			{
				foreach($main as $forum)
				{
					if($forum['fid'] != "0" && $forum['linkto'] == '')
					{
						$select_add = '';

						if(!empty($selected) && ($forum['fid'] == $selected || (is_array($selected) && in_array($forum['fid'], $selected))))
						{
							$select_add = " selected=\"selected\"";
						}

						$sep = '';
						if(isset($options['depth']))
						{
							$sep = str_repeat("&nbsp;", $options['depth']);
						}

						$style = "";
						if($forum['active'] == 0)
						{
							$style = " style=\"font-style: italic;\"";
						}

						$selectoptions .= "<option value=\"{$forum['fid']}\"{$style}{$select_add}>".$sep.htmlspecialchars_uni(strip_tags($forum['name']))."</option>\n";

						if($forum_cache[$forum['fid']])
						{
							$options['depth'] += 5;
							$options['pid'] = $forum['fid'];
							$this->generate_forum_select($forum['fid'], $selected, $options, 0);
							$options['depth'] -= 5;
						}
					}
				}
			}
		}

		if($is_first == 1)
		{
			if(!isset($options['multiple']))
			{
				$select = "<select name=\"{$name}\"";
			}
			else
			{
				$select = "<select name=\"{$name}\" multiple=\"multiple\"";
			}
			if(isset($options['class']))
			{
				$select .= " class=\"{$options['class']}\"";
			}
			if(isset($options['id']))
			{
				$select .= " id=\"{$options['id']}\"";
			}
			if(isset($options['size']))
			{
				$select .= " size=\"{$options['size']}\"";
			}
			$select .= ">\n".$selectoptions."</select>\n";
			$selectoptions = '';
			return $select;
		}
	}

	/**
	 * Generate a group selection box.
	 *
	 * @param string $name The name of the selection box.
	 * @param array|string $selected Array/string of the selected items.
	 * @param array $options Array of options (class, id, multiple, size)
	 * @return string The built select box.
	 */
	function generate_group_select($name, $selected=array(), $options=array())
	{
		global $cache;

		$select = "<select name=\"{$name}\"";

		if(isset($options['multiple']))
		{
			$select .= " multiple=\"multiple\"";
		}

		if(isset($options['class']))
		{
			$select .= " class=\"{$options['class']}\"";
		}

		if(isset($options['id']))
		{
			$select .= " id=\"{$options['id']}\"";
		}

		if(isset($options['size']))
		{
			$select .= " size=\"{$options['size']}\"";
		}

		$select .= ">\n";

		$groups_cache = $cache->read('usergroups');

		if(!is_array($selected))
		{
			$selected = array($selected);
		}

		foreach($groups_cache as $group)
		{
			$selected_add = "";


			if(in_array($group['gid'], $selected))
			{
				$selected_add = " selected=\"selected\"";
			}

			$select .= "<option value=\"{$group['gid']}\"{$selected_add}>".htmlspecialchars_uni($group['title'])."</option>";
		}

		$select .= "</select>";

		return $select;
	}

	/**
	 * Generate a prefix selection box.
	 *
	 * @param string $name The name of the selection box.
	 * @param array|string $selected Array/string of the selected items.
	 * @param array $options Array of options (class, id, multiple, size)
	 * @return string The built select box.
	 */
	function generate_prefix_select($name, $selected=array(), $options=array())
	{
		global $cache;
		$select = "<select name=\"{$name}\"";
		if(isset($options['multiple']))
		{
			$select .= " multiple=\"multiple\"";
		}
		if(isset($options['class']))
		{
			$select .= " class=\"{$options['class']}\"";
		}
		if(isset($options['id']))
		{
			$select .= " id=\"{$options['id']}\"";
		}
		if(isset($options['size']))
		{
			$select .= " size=\"{$options['size']}\"";
		}
		$select .= ">\n";
		$prefix_cache = $cache->read('threadprefixes');

		if(!is_array($selected))
		{
			$selected = array($selected);
		}

		foreach($prefix_cache as $prefix)
		{
			$selected_add = "";


			if(in_array($prefix['pid'], $selected))
			{
				$selected_add = " selected=\"selected\"";
			}
			$select .= "<option value=\"{$prefix['pid']}\"{$selected_add}>".htmlspecialchars_uni($prefix['prefix'])."</option>";
		}
		$select .= "</select>";
		return $select;
	}

	/**
	 * Generate a submit button.
	 *
	 * @param string $value The value for the submit button.
	 * @param array $options Array of options for the submit button (class, id, name, dsiabled, onclick)
	 * @return string The generated submit button.
	 */
	function generate_submit_button($value, $options=array())
	{
		$input = "<input type=\"submit\" value=\"".htmlspecialchars_uni($value)."\"";

		if(isset($options['class']))
		{
			$input .= " class=\"submit_button ".$options['class']."\"";
		}
		else
		{
			$input .= " class=\"submit_button\"";
		}
		if(isset($options['id']))
		{
			$input .= " id=\"".$options['id']."\"";
		}
		if(isset($options['name']))
		{
			$input .= " name=\"".$options['name']."\"";
		}
		if(isset($options['disabled']))
		{
			$input .= " disabled=\"disabled\"";
		}
		if(isset($options['onclick']))
		{
			$input .= " onclick=\"".str_replace('"', '\"', $options['onclick'])."\"";
		}
		$input .= " />";
		return $input;
	}

	/**
	 * Generate a reset button.
	 *
	 * @param string $value The value for the reset button.
	 * @param array $options Array of options for the reset button (class, id, name)
	 * @return string The generated reset button.
	 */
	function generate_reset_button($value, $options=array())
	{
		$input = "<input type=\"reset\" value=\"".htmlspecialchars_uni($value)."\"";

		if(isset($options['class']))
		{
			$input .= " class=\"submit_button ".$options['class']."\"";
		}
		else
		{
			$input .= " class=\"submit_button\"";
		}
		if(isset($options['id']))
		{
			$input .= " id=\"".$options['id']."\"";
		}
		if(isset($options['name']))
		{
			$input .= " name=\"".$options['name']."\"";
		}
		$input .= " />";
		return $input;
	}

	/**
	 * Generate a yes/no radio button choice.
	 *
	 * @param string $name The name of the yes/no choice field.
	 * @param string $value The value that should be checked.
	 * @param boolean $int Using integers for the checkbox?
	 * @param array $yes_options Array of options for the yes checkbox (@see generate_radio_button)
	 * @param array $no_options Array of options for the no checkbox (@see generate_radio_button)
	 * @return string The generated yes/no radio button.
	 */
	function generate_yes_no_radio($name, $value="1", $int=true, $yes_options=array(), $no_options = array())
	{
		global $lang;

		// Checked status
		if($value === "no" || $value === '0' || $value === 0)
		{
			$no_checked = 1;
			$yes_checked = 0;
		}
		else
		{
			$yes_checked = 1;
			$no_checked = 0;
		}
		// Element value
		if($int == true)
		{
			$yes_value = 1;
			$no_value = 0;
		}
		else
		{
			$yes_value = "yes";
			$no_value = "no";
		}

		if(!isset($yes_options['class']))
		{
			$yes_options['class'] = '';
		}

		if(!isset($no_options['class']))
		{
			$no_options['class'] = '';
		}

		// Set the options straight
		$yes_options['class'] = "radio_yes ".$yes_options['class'];
		$yes_options['checked'] = $yes_checked;
		$no_options['class'] = "radio_no ".$no_options['class'];
		$no_options['checked'] = $no_checked;

		$yes = $this->generate_radio_button($name, $yes_value, $lang->yes, $yes_options);
		$no = $this->generate_radio_button($name, $no_value, $lang->no, $no_options);
		return $yes." ".$no;
	}

	/**
	 * Generate an on/off radio button choice.
	 *
	 * @param string $name The name of the on/off choice field.
	 * @param int $value The value that should be checked.
	 * @param boolean $int Using integers for the checkbox?
	 * @param array $on_options Array of options for the on checkbox (@see generate_radio_button)
	 * @param array $off_options Array of options for the off checkbox (@see generate_radio_button)
	 * @return string The generated on/off radio button.
	 */
	function generate_on_off_radio($name, $value=1, $int=true, $on_options=array(), $off_options = array())
	{
		global $lang;

		// Checked status
		if($value == "off" || (int) $value !== 1)
		{
			$off_checked = 1;
			$on_checked = 0;
		}
		else
		{
			$on_checked = 1;
			$off_checked = 0;
		}
		// Element value
		if($int == true)
		{
			$on_value = 1;
			$off_value = 0;
		}
		else
		{
			$on_value = "on";
			$off_value = "off";
		}

		// Set the options straight
		if(!isset($on_options['class']))
		{
			$on_options['class'] = '';
		}

		if(!isset($off_options['class']))
		{
			$off_options['class'] = '';
		}

		$on_options['class'] = "radio_on ".$on_options['class'];
		$on_options['checked'] = $on_checked;
		$off_options['class'] = "radio_off ".$off_options['class'];
		$off_options['checked'] = $off_checked;

		$on = $this->generate_radio_button($name, $on_value, $lang->on, $on_options);
		$off = $this->generate_radio_button($name, $off_value, $lang->off, $off_options);
		return $on." ".$off;
	}

	/**
	 * @param string $name
	 * @param int $day
	 * @param int $month
	 * @param int $year
	 *
	 * @return string
	 */
	function generate_date_select($name, $day=0,$month=0,$year=0)
	{
		global $lang;

		$months = array(
			1 => $lang->january,
			2 => $lang->february,
			3 => $lang->march,
			4 => $lang->april,
			5 => $lang->may,
			6 => $lang->june,
			7 => $lang->july,
			8 => $lang->august,
			9 => $lang->september,
			10 => $lang->october,
			11 => $lang->november,
			12 => $lang->december,
		);

		// Construct option list for days
		$days = array();
		for($i = 1; $i <= 31; ++$i)
		{
			$days[$i] = $i;
		}

		if(!$day)
		{
			$day = date("j", TIME_NOW);
		}

		if(!$month)
		{
			$month = date("n", TIME_NOW);
		}

		if(!$year)
		{
			$year = date("Y", TIME_NOW);
		}

		$built = $this->generate_select_box($name.'_day', $days, (int)$day, array('id' => $name.'_day'))." &nbsp; ";
		$built .= $this->generate_select_box($name.'_month', $months, (int)$month, array('id' => $name.'_month'))." &nbsp; ";
		$built .= $this->generate_numeric_field($name.'_year', $year, array('id' => $name.'_year', 'style' => 'width: 100px;', 'min' => 0));
		return $built;
	}

	/**
	 * Output a row of buttons in a wrapped container.
	 *
	 * @param array $buttons Array of the buttons (html) to output.
	 * @return string The submit wrapper (optional)
	 */
	function output_submit_wrapper($buttons)
	{
		global $plugins;
		$buttons = $plugins->run_hooks("admin_form_output_submit_wrapper", $buttons);
		$return = "<div class=\"form_button_wrapper\">\n";
		foreach($buttons as $button)
		{
			$return .= $button." \n";
		}
		$return .= "</div>\n";
		if($this->_return == false)
		{
			echo $return;
		}
		else
		{
			return $return;
		}
	}

	/**
	 * Finish up a form.
	 *
	 * @return string The ending form tag (optional)
	 */
	function end()
	{
		global $plugins;
		$plugins->run_hooks("admin_form_end", $this);
		if($this->_return == false)
		{
			echo "</form>";
		}
		else
		{
			return "</form>";
		}
	}
}

/**
 * Generate a form container.
 */
class DefaultFormContainer
{
	/** @var Table */
	private $_container;
	/** @var string */
	public $_title;

	/**
	 * Initialise the new form container.
	 *
	 * @param string $title The title of the form container
	 * @param string $extra_class An additional class to apply if we have one.
	 */
	function __construct($title='', $extra_class='')
	{
		$this->_container = new Table;
		$this->extra_class = $extra_class;
		$this->_title = $title;
	}

	/**
	 * Output a header row of the form container.
	 *
	 * @param string $title The header row label.
	 * @param array $extra Array of extra information for this header cell (class, style, colspan, width)
	 */
	function output_row_header($title, $extra=array())
	{
		$this->_container->construct_header($title, $extra);
	}

	/**
	 * Output a row of the form container.
	 *
	 * @param string $title The title of the row.
	 * @param string $description The description of the row/field.
	 * @param string $content The HTML content to show in the row.
	 * @param string $label_for The ID of the control this row should be a label for.
	 * @param array $options Array of options for the row cell.
	 * @param array $row_options Array of options for the row container.
	 */
	function output_row($title, $description="", $content="", $label_for="", $options=array(), $row_options=array())
	{
		global $plugins;
		$pluginargs = array(
			'title' => &$title,
			'description' => &$description,
			'content' => &$content,
			'label_for' => &$label_for,
			'options' => &$options,
			'row_options' => &$row_options,
			'this' => &$this
		);

		$plugins->run_hooks("admin_formcontainer_output_row", $pluginargs);

		$row = $for = '';
		if($label_for != '')
		{
			$for = " for=\"{$label_for}\"";
		}

		if($title)
		{
			$row = "<label{$for}>{$title}</label>";
		}

		if($description != '')
		{
			$row .= "\n<div class=\"description\">{$description}</div>\n";
		}

		$row .= "<div class=\"form_row\">{$content}</div>\n";

		$this->_container->construct_cell($row, $options);

		if(!isset($options['skip_construct']))
		{
			$this->_container->construct_row($row_options);
		}
	}

	/**
	 * Output a row cell for a table based form row.
	 *
	 * @param string $data The data to show in the cell.
	 * @param array $options Array of options for the cell (optional).
	 */
	function output_cell($data, $options=array())
	{
		$this->_container->construct_cell($data, $options);
	}

	/**
	 * Build a row for the table based form row.
	 *
	 * @param array $extra Array of extra options for the cell (optional).
	 */
	function construct_row($extra=array())
	{
		$this->_container->construct_row($extra);
	}

	/**
	 * return the cells of a row for the table based form row.
	 *
	 * @param string $row_id The id of the row.
	 * @param boolean $return Whether or not to return or echo the resultant contents.
	 * @return string The output of the row cells (optional).
	 */
	function output_row_cells($row_id, $return=false)
	{
		if(!$return)
		{
			echo $this->_container->output_row_cells($row_id, $return);
		}
		else
		{
			return $this->_container->output_row_cells($row_id, $return);
		}
	}

	/**
	 * Count the number of rows in the form container. Useful for displaying a 'no rows' message.
	 *
	 * @return int The number of rows in the form container.
	 */
	function num_rows()
	{
		return $this->_container->num_rows();
	}

	/**
	 * Output the end of the form container row.
	 *
	 * @param boolean $return Whether or not to return or echo the resultant contents.
	 * @return string The output of the form container (optional).
	 */
	function end($return=false)
	{
		global $plugins;

		$hook = array(
			'return'	=> &$return,
			'this'		=> &$this
		);

		$plugins->run_hooks("admin_formcontainer_end", $hook);
		if($return == true)
		{
			return $this->_container->output($this->_title, 1, "general form_container {$this->extra_class}", true);
		}
		else
		{
			echo $this->_container->output($this->_title, 1, "general form_container {$this->extra_class}", false);
		}
	}
}
