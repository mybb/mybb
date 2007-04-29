<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/license.php
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

	function generate_upload_field($name, $options=array())
	{
		
	}

	function generate_text_area($name, $value="", $options=array())
	{
		$textarea = "<textarea name=\"{$name}\"";
		if(isset($options['class']))
		{
			$textarea .= " class=\"{$options['class']}\"";
		}
		if(isset($options['id']))
		{
			$textarea .= " id=\"{$options['id']}\"";
		}
		if(!$options['rows'])
		{
			$options['rows'] = 5;
		}
		if(!$options['cols'])
		{
			$options['cols'] = 45;
		}
		if($options['style'])
		{
			$options['style'] = " style=\"{$options['style']}\"";
		}
		$textarea .= " rows=\"{$options['rows']}\" cols=\"{$options['cols']}\"{$options['style']}>";
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
		if(isset($options['checked']) && $options['checked'] != 0)
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
	
	function generate_select_box($name, $option_list, $selected=false, $options=array())
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
			if($value == $selected || (is_array($selected) && in_array($value, $selected, true)))
			{
				$select_add = " selected=\"selected\"";
			}
			$select .= "<option value=\"{$value}\"{$select_add}>{$option}</option>\n";
		}
		$select .= "</select>\n";
		return $select;
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

	function generate_yes_no_radio($name, $value="yes", $int=false)
	{
		if($value == "no" || $value === '0')
		{
			$no_checked = 1;
			$yes_checked = 0;
		}
		else
		{
			$yes_checked = 1;
			$no_checked = 0;
		}
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
		
		$yes = $this->generate_radio_button($name, $yes_value, "Yes", array("class" => "radio_yes", "checked" => $yes_checked));
		$no = $this->generate_radio_button($name, $no_value, "No", array("class" => "radio_no", "checked" => $no_checked));
		return $yes." ".$no;
	}

	function generate_on_off_radio($name, $value="yes")
	{
		if($value == "off")
		{
			$off_checked = 1;
			$on_checked = 0;
		}
		else
		{
			$on_checked = 1;
			$off_checked = 0;
		}
		$on = $this->generate_radio_button($name, "on", "On", array("class" => "radio_on", "checked" => $on_checked));
		$off = $this->generate_radio_button($name, "off", "Off", array("class" => "radio_off", "checked" => $off_checked));
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

	function output_row($title, $description="", $content="", $label_for="", $options=array())
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
		$this->container->construct_row();
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