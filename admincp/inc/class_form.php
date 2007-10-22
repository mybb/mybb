<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id$
 */


class DefaultForm
{
	function DefaultForm($script, $method, $allow_uploads=0, $name="", $id="")
	{
		$form = "<form action=\"{$script}\" method=\"{$method}\"";
		if($allow_uploads != 0)
		{
			$form .= " type=\"multipart/form-data\"";
		}
		if($name != "")
		{
			$form .= " name=\"{$name}\"";
		}
		if($id != "")
		{
			$form .= " id=\"{$id}\"";
		}
		$form .= ">\n";
		echo $form;
	}

	function generate_hidden_field($name, $value, $options=array())
	{
		$input = "<input type=\"hidden\" name=\"{$name}\" value=\"".htmlspecialchars($value)."\"";
		if(isset($options['id']))
		{
			$input .= " id=\"".$options['id']."\"";
		}
		$input .= " />";
		return $input;
	}
	
	function generate_text_box($name, $value="", $options=array())
	{
		$input = "<input type=\"text\" name=\"".$name."\" value=\"".htmlspecialchars($value)."\"";
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
	
	function generate_password_box($name, $value="", $options=array())
	{
		$input = "<input type=\"password\" name=\"".$name."\" value=\"".htmlspecialchars($value)."\"";
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
		$input .= " />";
		return $input;
	}

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
		if($options['style'])
		{
			$textarea .= " style=\"{$options['style']}\"";
		}
		if($options['disabled'])
		{
			$textarea .= " disabled=\"disabled\"";
		}
		if(!$options['rows'])
		{
			$options['rows'] = 5;
		}
		if(!$options['cols'])
		{
			$options['cols'] = 45;
		}
		$textarea .= " rows=\"{$options['rows']}\" cols=\"{$options['cols']}\">";
		$textarea .= htmlspecialchars_uni($value);
		$textarea .= "</textarea>";
		return $textarea;
	}

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
		$input .= "><input type=\"radio\" name=\"{$name}\" value=\"".htmlspecialchars($value)."\"";
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
		$input .= "><input type=\"checkbox\" name=\"{$name}\" value=\"".htmlspecialchars($value)."\"";
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
		if($options['checked'] === true || $options['checked'] == 1)
		{
			$input .= " checked=\"checked\"";
		}
		if(isset($options['onclick']))
		{
			$input .= " onclick=\"{$options['onclick']}\"";
		}
		$input .= " />";
		if($label != "")
		{
			$input .= $label;
		}
		$input .= "</label>";
		return $input;
	}
	
	function generate_select_box($name, $option_list, $selected=array(), $options=array())
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
		$select .= ">\n";
		foreach($option_list as $value => $option)
		{
			$select_add = '';
			if(!empty($selected) && ($value == $selected || (is_array($selected) && in_array($value, $selected))))
			{
				$select_add = " selected=\"selected\"";
			}
			$select .= "<option value=\"{$value}\"{$select_add}>{$option}</option>\n";
		}
		$select .= "</select>\n";
		return $select;
	}
	
	function generate_forum_select($name, $selected, $options=array(), $is_first=1)
	{
		global $fselectcache, $forum_cache, $selectoptions;
		
		if(!$selectoptions)
		{
			$selectoptions = '';
		}
		
		if(!$options['depth'])
		{
			$options['depth'] = 0;
		}
		
		$options['depth'] = intval($options['depth']);
		
		if(!$options['pid'])
		{
			$pid = 0;
		}
		
		$pid = intval($options['pid']);
		
		if(!is_array($fselectcache))
		{
			if(!is_array($forum_cache))
			{
				$forum_cache = cache_forums();
			}
	
			foreach($forum_cache as $fid => $forum)
			{
				if($forum['active'] != 0)
				{
					$fselectcache[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
				}
			}
		}
		
		if($options['allforums_option'] == true && $is_first)
		{
			global $lang;
			
			$selectoptions .= "<option value=\"-1\">{$lang->all_forums}</option>\n";
		}
		
		if(is_array($fselectcache[$pid]))
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

						$selectoptions .= "<option value=\"{$forum['fid']}\"{$select_add} style=\"padding-left: {$options['depth']}px;\">{$forum['name']}</option>\n";
	
						if($forum_cache[$forum['fid']])
						{
							$options['depth'] += 15;
							$options['pid'] = $forum['fid'];
							$this->generate_forum_select($forum['fid'], $selected, $options, 0);
							$options['depth'] -= 15;
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
	
	function generate_submit_button($value, $options=array())
	{
		$input = "<input type=\"submit\" value=\"".htmlspecialchars($value)."\"";

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
		if($options['disabled'])
		{
			$input .= " disabled=\"disabled\"";
		}
		if($options['onclick'])
		{
			$input .= " onclick=\"".str_replace('"', '\"', $options['onclick'])."\"";
		}
		$input .= " />";
		return $input;
	}
	
	function generate_reset_button($value, $options=array())
	{
		$input = "<input type=\"reset\" value=\"".htmlspecialchars($value)."\"";

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

	function generate_yes_no_radio($name, $value=1, $int=true, $yes_options=array(), $no_options = array())
	{
		// Checked status
		if($value == "yes" || $value === '0')
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
		// Set the options straight
		$yes_options['class'] = "radio_yes ".$yes_options['class'];
		$yes_options['checked'] = $yes_checked;
		$no_options['class'] = "radio_no ".$no_options['class'];
		$no_options['checked'] = $no_checked;
		
		$yes = $this->generate_radio_button($name, $yes_value, "Yes", $yes_options);
		$no = $this->generate_radio_button($name, $no_value, "No", $no_options);
		return $yes." ".$no;
	}

	function generate_on_off_radio($name, $value=1, $int=true, $on_options=array(), $off_options = array())
	{
		// Checked status
		if($value == "off" || $value !== 1)
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
		$on_options['class'] = "radio_on ".$on_options['class'];
		$on_options['checked'] = $on_checked;
		$off_options['class'] = "radio_off ".$off_options['class'];
		$off_options['checked'] = $off_checked;
		
		$on = $this->generate_radio_button($name, $on_value, "On", $on_options);
		$off = $this->generate_radio_button($name, $off_value, "Off", $off_options);
		return $on." ".$off;
	}
	
	function generate_date_select($name, $date, $options)
	{
		
	}
	
	function output_submit_wrapper($buttons)
	{
		echo "<div class=\"form_button_wrapper\">\n";
		foreach($buttons as $button)
		{
			echo $button." \n";
		}
		echo "</div>\n";
	}	

	function end()
	{
		echo "</form>";
	}
}

class DefaultFormContainer
{
	var $container;
	var $title;

	function DefaultFormContainer($title='', $extra_class='')
	{
		$this->container = new Table;
		$this->extra_class = $extra_class;
		$this->title = $title;
	}

	function output_row_header($title, $extra=array())
	{
		$this->container->construct_header($title, $extra);
	}

	function output_row($title, $description="", $content="", $label_for="", $options=array(), $row_options=array())
	{
		if($label_for != '')
		{
			$for = " for=\"{$label_for}\"";
		}
		$row = "<label{$for}>{$title}</label>";
		if($description != '')
		{
			$row .= "\n<div class=\"description\">{$description}</div>\n";
		}
		$row .= "<div class=\"form_row\">{$content}</div>\n";
		
		$this->container->construct_cell($row, $options);
		
		if(!isset($options['skip_construct']))
		{
			$this->container->construct_row($row_options);
		}
	}
	
	function output_cell($data, $options=array())
	{
		$this->container->construct_cell($data, $options);
	}
	
	function construct_row()
	{
		$this->container->construct_row();
	}

	function end()
	{
		$this->container->output($this->title, 1, "general form_container {$this->extra_class}");
	}
}

?>