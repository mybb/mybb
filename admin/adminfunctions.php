<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.com
 * License: http://www.mybboard.com/license.php
 *
 * $Id$
 */

$cssselectors = array(
	"body" => "body",
	"container" => "#container",
	"content" => "#content",
	"menu" => ".menu ul",
	"panel" => "#panel",
	"table" => "table",
	"tborder" => ".tborder",
	"thead" => ".thead",
	"tcat" => ".tcat",
	"trow1" => ".trow1",
	"trow2" => ".trow2",
	"trow_shaded" => ".trow_shaded",
	"trow_sep" => ".trow_sep",
	"tfoot" => ".tfoot",
	"bottommenu" => ".bottommenu",
	"navigation" => ".navigation",
	"navigation_active" => ".navigation .active",
	"smalltext" => ".smalltext",
	"largetext" => ".largetext",
	"textbox" => "input.textbox",
	"textarea" => "textarea",
	"radio" => "input.radio",
	"checkbox" => "input.checkbox",
	"select" => "select",
	"button" => "input.button",
	"editor" => ".editor",
	"editor_control_bar" => ".editor_control_bar",
	"toolbar_normal" => ".toolbar_normal",
	"toolbar_hover" => ".toolbar_hover",
	"toolbar_clicked" => ".toolbar_clicked",
	"autocomplete" => ".autocomplete",
	"autocomplete_selected" => ".autocomplete_selected",
	"popup_menu" => ".popup_menu",
	"popup_item" => ".popup_menu .popup_item",
	"popup_item_hovered" => ".popup_menu .popup_item:hover",
	"trow_reputation_positive" => ".trow_reputation_positive",
	"trow_reputation_neutral" => ".trow_reputation_neutral",
	"trow_reputation_negative" => ".trow_reputation_negative",
	"reputation_positive" => ".reputation_positive",
	"reputation_neutral" => ".reputation_neutral",
	"reputation_negative" => ".reputation_negative",
	"invalid_field" => ".invalid_field",
	"valid_field" => ".valid_field",
	"validation_error" => ".validation_error",
	"validation_success" => ".validation_success",
	"validation_loading" => ".validation_loading",
	
	// Link selectors
	"a_link" => "a:link",
	"a_visited" => "a:visited",
	"a_hover" => "a:hover"
);

$themebitlist = array("templateset", "imgdir", "logo", "tablespace", "borderwidth", "extracss");


function cpheader($title="", $donav=1, $onload="", $extraheaders="")
{
	global $mybb, $style, $lang, $cpheader, $error_handler;

	if($cpheader === true)
	{
    	return true;
  	}

	$cpheader = true;
  	if(!$title)
	{
		$title = $mybb->settings['bbname']." - ".$lang->admin_center;
	}
	$htmltag = "<html>\n";
	if($lang->settings['rtl'] == 1)
	{
		$htmltag = str_replace("<html", "<html dir=\"rtl\"", $htmltag);
	}
	if($lang->settings['htmllang'])
	{
		$htmltag = str_replace("<html", "<html lang=\"".$lang->settings['htmllang']."\"", $htmltag);
	}
	echo "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n";
	echo $htmltag;
	echo "<head>\n";
	echo "<title>$title</title>\n";
	echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=".$lang->settings['charset']."\" />\n";
	echo "<link type=\"text/css\" rel=\"stylesheet\" href=\"$style\" />\n";
	echo "<script type=\"text/javascript\">\n";
	echo "function hopto(url) {\n";
	echo "window.location = url;\n";
	echo "}\n";
	echo "</script>\n";
	echo $extraheaders;
	echo "</head>\n";
	if($onload)
	{
		echo "<body onload=\"$onload\">\n";
	}
	else
	{
		echo "<body class=\"main_body\">\n";
	}
	if($donav != 0)
	{
		echo buildacpnav();
	}	
	else
	{
		echo $error_handler->show_warnings();
	}
}

function makeacpphpwarning($warnings)
{
	global $lang;
	
	return '<table border="0" cellspacing="1" cellpadding="4" align="center" class="bordercolor">
<tr>
<td class="header">
<strong>'.$lang->warnings.'</strong>
</td>
</tr>
<tr>
<td class="altbg1">'.$warnings.'<br /></td>
</tr>
</table><br /><br />';
}

function makehoptolinks($links)
{
	echo "<table cellpadding=\"0\" cellspacing=\"0\" border=\"0\" align=\"center\" width=\"100%\">\n";
	echo "<tr><td class=\"hoptobuttons\">";
	if(!is_array($links))
	{
		$links = array($links);
	}
	foreach($links as $key => $val)
	{
		echo $val;
	}
	echo "</td></tr>";
	echo "</table>";
}

function startform($script, $name="", $action="", $autocomplete=1)
{
	global $admin_session;
	$acomplete = "";
	if($autocomplete == 0)
	{
		$acomplete = " autocomplete=\"off\"";
	}
	if($name != '')
	{
    	$name = " name=\"$name\"";
  	}
	echo "<form action=\"$script\" method=\"post\"$name enctype=\"multipart/form-data\"$acomplete>\n";
	makehiddencode("adminsid", $admin_session['sid']);
	if($action != "")
	{
		makehiddencode("action", $action);
	}
}
function starttable($width="100%", $border=1, $padding=6)
{
	echo "<table cellpadding=\"$border\" cellspacing=\"0\" border=\"0\" align=\"center\" width=\"$width\" class=\"bordercolor\">\n";
	echo "<tr><td>\n";
	echo "<table cellpadding=\"$padding\" cellspacing=\"0\" border=\"0\" width=\"100%\" class=\"tback\">";
}
function tableheader($title, $anchor="", $colspan=2)
{
	global $bgcolor;

	if($anchor)
	{
    	$anchor = "<a name=\"$anchor\">$title</a>";
 	}
  	else
  	{
    	$anchor = $title;
  	}
	echo "<tr>\n<td class=\"header\" align=\"center\" colspan=\"$colspan\">$anchor</td>\n</tr>\n";
	$bgcolor = "altbg2";
}
function tablesubheader($titles, $anchor="", $colspan=2, $align="center")
{
	global $bgcolor;
	echo "<tr>\n";
	if(!is_array($titles))
	{
		$title[] = $titles;
	}
	else
	{
		$colspan = 1;
		$title = $titles;
	}
	foreach($title as $ttitle)
	{
		if($anchor)
		{
			$ttitle = "<a href=\"$anchor\">$ttitle</a>";
		}
		echo "<td class=\"subheader\" align=\"$align\" colspan=\"$colspan\">$ttitle</td>\n";
		$bgcolor = "altbg2";
	}
	echo "</tr>\n";
}
function makelabelcode($title, $value="", $colspan=1, $width1="40%", $width2="60%")
{
	$bgcolor = getaltbg();
	if($value != "")
	{
		$width1 = " width=\"$width1\"";
		$width2 = " width=\"$width2\"";
	}
	else
	{
		$width1 = $width2 = "";
	}
	echo "<tr>\n<td class=\"$bgcolor\" colspan=\"$colspan\" valign=\"top\"$width1>$title</td>\n";
	if($value != "")
	{
		echo "<td class=\"$bgcolor\" valign=\"top\" $width2>$value</td>\n";
	}
	echo "</tr>\n";
}
function makelinkcode($text, $url, $newwin=0, $class="")
{
	if($newwin)
	{
		$target = "target=\"_blank\"";
	}
	if($class)
	{
    	$class = "<span class=\"$class\">[$text]</span>";
  	}
  	else
  	{
    	$class = "[$text]";
  	}
	return " <a href=\"$url\" $target>$class</a>";
}
function makeinputcode($title, $name, $value="", $size="25", $extra="", $maxlength="", $autocomplete=1, $extra2="")
{
	$bgcolor = getaltbg();
	$value = htmlspecialchars_uni($value);
	if($autocomplete != 1)
	{
		$ac = " autocomplete=\"off\"";
	}
	if($value != '')
	{
		$value = " value=\"{$value}\"";
	}
	if($maxlength != '')
	{
    	$maxlength = " maxlength=\"$maxlength\"";
  	}
  	if($size != '')
  	{
    	$size = " size=\"$size\"";
  	}
	echo "<tr>\n<td class=\"$bgcolor\" valign=\"top\" width=\"40%\">$title</td>\n<td class=\"$bgcolor\" valign=\"top\" width=\"60%\">\n$extra2<input type=\"text\" class=\"inputbox\" name=\"$name\"$size$maxlength$ac$value />\n$extra\n</td>\n</tr>\n";
}
function makeuploadcode($title, $name, $size="25", $extra="")
{
	$bgcolor = getaltbg();
	echo "<tr>\n<td class=\"$bgcolor\" valign=\"top\" width=\"40%\">$title</td>\n<td class=\"$bgcolor\" valign=\"top\" width=\"60%\"><input type=\"file\" class=\"inputbox\" name=\"$name\" size=\"$size\" />$extra</td>\n</tr>\n";
}
function makepasswordcode($title, $name, $value="", $size="25", $autocomplete=1)
{
	$bgcolor = getaltbg();
	$value = htmlspecialchars_uni($value);
	if($autocomplete != 1)
	{
		$ac = " autocomplete=\"off\"";
	}
	if($value != '')
	{
		$value = " value=\"{$value}\"";
	}
	echo "<tr>\n<td class=\"$bgcolor\" valign=\"top\" width=\"40%\">$title</td>\n<td class=\"$bgcolor\" valign=\"top\" width=\"60%\"><input type=\"password\" class=\"inputbox\" name=\"$name\" size=\"$size\"$ac$value /></td>\n</tr>\n";
}
function maketextareacode($title, $name, $value="", $rows="4", $columns="40")
{
	$bgcolor = getaltbg();
	$value = htmlspecialchars_uni($value);
	echo "<tr>\n<td class=\"$bgcolor\" valign=\"top\" width=\"40%\">$title</td>\n<td class=\"$bgcolor\" valign=\"top\" width=\"60%\"><textarea name=\"$name\" rows=\"$rows\" cols=\"$columns\">$value</textarea></td>\n</tr>\n";
}
function makehiddencode($name, $value="", $nameid="name")
{
	$value = htmlspecialchars_uni($value);
	echo "<input type=\"hidden\" $nameid=\"$name\" value=\"$value\" />\n";
}
function makeyesnocode($title, $name, $value="yes")
{
	global $lang;
	$bgcolor = getaltbg();
	if($value == "no")
	{
		$nocheck = " checked=\"checked\"";
	}
	else
	{
		$yescheck = " checked=\"checked\"";
	}
	echo "<tr>\n<td class=\"$bgcolor\" valign=\"top\" width=\"40%\">$title</td>\n<td class=\"$bgcolor\" valign=\"top\" width=\"60%\"><label><input type=\"radio\" name=\"$name\" value=\"yes\"$yescheck />&nbsp;$lang->yes</label> &nbsp;&nbsp;<label><input type=\"radio\" name=\"$name\" value=\"no\"$nocheck />&nbsp;$lang->no</label></td>\n</tr>\n";
}
function makeonoffcode($title, $name, $value="on")
{
	global $lang;
	$bgcolor = getaltbg();
	if($value == "off")
	{
		$offcheck = "checked";
	}
	else
	{
		$oncheck = "checked";
	}
	echo "<tr>\n<td class=\"$bgcolor\" valign=\"top\" width=\"40%\">$title</td>\n<td class=\"$bgcolor\" valign=\"top\" width=\"60%\"><label><input type=\"radio\" name=\"$name\" value=\"on\" $oncheck>&nbsp;$lang->on</label> &nbsp;&nbsp;<label><input type=\"radio\" name=\"$name\" value=\"off\" $offcheck>&nbsp;$lang->off</label></td>\n</tr>\n";
}
function makeselectcode($title, $name, $table, $tableid, $optiondisp, $selected="", $extra="", $blank="", $condition="", $order="")
{
	global $db;
	$bgcolor = getaltbg();
	echo "<tr>\n<td class=\"$bgcolor\" valign=\"top\" width=\"40%\">$title</td><td class=\"$bgcolor\" valign=\"top\" width=\"60%\">\n<select name=\"$name\">\n";
	if($order)
	{
		$options = array(
			"order_by" => $order
		);
	}

	$query = $db->simple_select("$table", "$tableid, $optiondisp", $condition, $options);
	if($blank && !$selected)
	{
		echo "<option value=\"\" selected> </option>";
	}
	while($item = $db->fetch_array($query))
	{
		if($item[$tableid] == $selected)
		{
			echo "<option value=\"$item[$tableid]\" selected>$item[$optiondisp]</option>\n";
		}
		else
		{
			echo "<option value=\"$item[$tableid]\">$item[$optiondisp]</option>\n";
		}
	}
	if($extra)
	{
		$eq_pos = my_strpos($extra, "=");
		if($eq_pos !== false)
		{
			$exp = explode("=", $extra, 2);
			$extra = $exp[1];
			$value = $exp[0];
		}
		else
		{
			$value = "0";
		}

		if($selected == $value)
		{
			echo "<option value=\"$value\" selected>$extra</option>\n";
		}
		else
		{
			echo "<option value=\"$value\">$extra</option>\n";
		}
	}
	echo "</select>\n</td>\n</tr>\n";
}
function makeselectcode_array($title, $name, $options, $selected="", $blank="", $blank_label="")
{
	global $db;
	$bgcolor = getaltbg();
	echo "<tr>\n<td class=\"$bgcolor\" valign=\"top\" width=\"40%\">$title</td><td class=\"$bgcolor\" valign=\"top\" width=\"60%\">\n<select name=\"$name\">\n";
	if($blank)
	{
		echo "<option value=\"\"> $blank_label</option>";
	}
	if(!is_array($options))
	{
		$options = array($options);
	}
	foreach($options as $value => $label)
	{
		if($value == $selected)
		{
			echo "<option value=\"$value\" selected=\"selected\">$label</option>\n";
		}
		else
		{
			echo "<option value=\"$value\">$label</option>\n";
		}
	}
	echo "</select>\n</td>\n</tr>\n";
}
function makedateselect($title, $name, $day, $month, $year)
{
	$dname = $name."[day]";
	$mname = $name."[month]";
	$yname = $name."[year]";

	for($i = 1; $i <= 31; ++$i)
	{
		if($day == $i)
		{
			$daylist .= "<option value=\"$i\" selected>$i</option>\n";
		}
		else
		{
			$daylist .= "<option value=\"$i\">$i</option>\n";
		}
	}

	$monthsel[$month] = "selected";
	$monthlist .= "<option value=\"\">------------</option>";
	$monthlist .= "<option value=\"01\" $monthsel[01]>January</option>\n";
	$monthlist .= "<option value=\"02\" $monthsel[02]>February</option>\n";
	$monthlist .= "<option value=\"03\" $monthsel[03]>March</option>\n";
	$monthlist .= "<option value=\"04\" $monthsel[04]>April</option>\n";
	$monthlist .= "<option value=\"05\" $monthsel[05]>May</option>\n";
	$monthlist .= "<option value=\"06\" $monthsel[06]>June</option>\n";
	$monthlist .= "<option value=\"07\" $monthsel[07]>July</option>\n";
	$monthlist .= "<option value=\"08\" $monthsel[08]>August</option>\n";
	$monthlist .= "<option value=\"09\" $monthsel[09]>September</option>\n";
	$monthlist .= "<option value=\"10\" $monthsel[10]>October</option>\n";
	$monthlist .= "<option value=\"11\" $monthsel[11]>November</option>\n";
	$monthlist .= "<option value=\"12\" $monthsel[12]>December</option>\n";
	$dateselect = "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\"><tr>\n";
	$dateselect .= "<td><b><small>Day</small></b><br />\n<select name=\"$dname\"><option value=\"\">--</option>\n$daylist</select></td>\n";
	$dateselect .= "<td><b><small>Month</small></b><br />\n<select name=\"$mname\">$monthlist</select></td>\n";
	$dateselect .= "<td><b><small>Year</small></b><br />\n<input name=\"$yname\" value=\"$year\" size=\"4\"></td>\n";
	$dateselect .= "</tr></table>";

	$bgcolor = getaltbg();

	echo "<tr>\n<td class=\"$bgcolor\" valign=\"top\" width=\"40%\">$title</td>\n<td class=\"$bgcolor\" valign=\"top\">$dateselect</tr>\n";
}
function makebuttoncode($name, $value, $type="submit", $onclick="", $nameid="name")
{
  if($onclick != '')
  {
    $onclick = " onclick=\"$onclick\"";
  }
	return "<input type=\"$type\" class=\"submitbutton\" $nameid=\"$name\" value=\"  $value  \"$onclick />&nbsp;&nbsp;\n";
}

function makecssedit($css, $selector, $name, $description="", $showfonts=1, $showbackground=1, $showlinks=1, $showwidth=0)
{
	global $lang, $tid, $tcache;
	if(!is_array($tcache))
	{
		cache_themes();
	}
	if($css['inherited'] != $tid && $css['inherited'] != 1)
	{
		$inheritid = $css['inherited'];
		$inheritedfrom = $tcache[$inheritid]['name'];
		$highlight = "highlight3";
		$name .= "(".$lang->inherited_from." ".$inheritedfrom.")";
	}
	elseif($css['inherited'] == 1)
	{
	}
	else
	{
		$highlight = "highlight2";
		$name .= " (".$lang->customized_this_style.")";
		$revert = "<input type=\"checkbox\" name=\"revert_css[$selector]\" value=\"1\" id=\"revert_css_$selector\" /> <label for=\"revert_css_$selector\">".$lang->revert_customizations."</label>";
	}
	starttable();
	tableheader($name);
	echo "<tr>\n<td class=\"subheader\" align=\"center\">".$lang->main_css_attributes."</td><td class=\"subheader\" align=\"center\">".$lang->extra_css_attributes."</td>\n</tr>\n";
	echo "<tr>\n";
	echo "<td class=\"altbg1\" width=\"50%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	if($showbackground)
	{
		echo "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[$selector][background]\" value=\"".htmlspecialchars_uni($css['background'])."\" size=\"25\" class=\"$highlight\"/></td>\n</tr>\n";
	}
	if($showwidth)
	{
		echo "<tr>\n<td>".$lang->width."</td>\n<td><input type=\"text\" name=\"css[$selector][width]\" value=\"".htmlspecialchars_uni($css['width'])."\" size=\"25\" class=\"$highlight\" /></td>\n</tr>\n";
	}
	if($showfonts)
	{
		echo "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[$selector][color]\" value=\"".htmlspecialchars_uni($css['color'])."\" size=\"25\"  class=\"$highlight\" /></td>\n</tr>\n";
		echo "<tr>\n<td>".$lang->font_family."</td>\n<td><input type=\"text\" name=\"css[$selector][font-family]\" value=\"".htmlspecialchars_uni($css['font-family'])."\" size=\"25\"  class=\"$highlight\" /></td>\n</tr>\n";
		echo "<tr>\n<td>".$lang->font_size."</td>\n<td><input type=\"text\" name=\"css[$selector][font-size]\" value=\"".htmlspecialchars_uni($css['font-size'])."\" size=\"25\"  class=\"$highlight\" /></td>\n</tr>\n";
		echo "<tr>\n<td>".$lang->font_style."</td>\n<td><input type=\"text\" name=\"css[$selector][font-style]\" value=\"".htmlspecialchars_uni($css['font-style'])."\" size=\"25\"  class=\"$highlight\" /></td>\n</tr>\n";
		echo "<tr>\n<td>".$lang->font_weight."</td>\n<td><input type=\"text\" name=\"css[$selector][font-weight]\" value=\"".htmlspecialchars_uni($css['font-weight'])."\" size=\"25\"  class=\"$highlight\" /></td>\n</tr>\n";
	}
	echo "</table>\n";
	echo "</td>\n";
	echo "<td class=\"altbg1\" width=\"50%\ valign=\"top\">\n";
	echo "<textarea style=\"width: 98%; padding: 4px;\" ";
	if($showfonts)
	{
		echo "rows=\"9\"";
	}
	else
	{
		echo "rows=\"4\"";
	}
	echo "name=\"css[$selector][extra]\" class=\"$highlight\">".htmlspecialchars_uni($css['extra'])."</textarea>\n";
	echo "</td>\n";
	echo "</tr>\n";
	if($showlinks == 1)
	{
		echo "<tr>\n";
		echo "<td colspan=\"2\" class=\"subheader\" align=\"center\">".$lang->link_css_attributes."</td>\n";
		echo "</tr>\n";
		echo "<tr>\n";
		echo "<td class=\"altbg2\" colspan=\"2\">\n";
		echo "<table width=\"100%\">\n";
		echo "<tr>\n";
		echo "<td>\n";
		makecsslinkedit($selector, "a_link", $lang->normal_links, $css['a_link'], $highlight);
		echo "</td>\n";
		echo "<td>\n";
		makecsslinkedit($selector, "a_visited", $lang->visited_links, $css['a_visited'], $highlight);
		echo "</td>\n";
		echo "<td>\n";
		makecsslinkedit($selector, "a_hover", $lang->hovered_links, $css['a_hover'], $highlight);
		echo "</td>\n";
		echo "</tr>\n</table>\n";
		echo "</td>\n";
		echo "</tr>\n";
	}
	$submit = makebuttoncode($lang->save_changes, $lang->save_changes, "submit");
	tablesubheader("<div style=\"float: right;\">$submit</div><div>$revert</div>", "", 2, "left");
	endtable();
}

function makecsslinkedit($selector, $type, $name, $css, $highlight=1)
{
	global $lang;
	echo "<fieldset>\n";
	echo "<legend>".$name."</legend>\n";
	echo "<table width=\"100%\">\n";
	echo "<tr><td>".$lang->background."</td><td><input type=\"text\" name=\"css[$selector][$type][background]\" value=\"".htmlspecialchars_uni($css['background'])."\" size=\"8\" class=\"$highlight\" /></td></tr>\n";
	echo "<tr><td>".$lang->font_color."</td><td><input type=\"text\" name=\"css[$selector][$type][color]\" value=\"".htmlspecialchars_uni($css['color'])."\" size=\"8\" class=\"$highlight\" /></td></tr>\n";
	echo "<tr><td>".$lang->text_decoration."</td><td><input type=\"text\" name=\"css[$selector][$type][text-decoration]\" value=\"".htmlspecialchars_uni($css['text-decoration'])."\" size=\"8\" class=\"$highlight\" /></td></tr>\n";
	echo "</table>\n";
	echo "</fieldset>\n";
}

function makecsstoolbaredit($css)
{
	global $lang;
	starttable();
	tableheader($lang->mycode_toolbar);
	echo "<tr>\n<td class=\"subheader\" align=\"center\">".$lang->editor."</td><td class=\"subheader\" align=\"center\">".$lang->editor_control_bar."</td>\n</tr>\n";
	echo "<tr>\n";
	echo "<td class=\"altbg1\" width=\"50%\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[editor][background]\" value=\"".htmlspecialchars_uni($css['editor']['background'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->border."</td>\n<td><input type=\"text\" name=\"css[editor][border]\" value=\"".htmlspecialchars_uni($css['editor']['border'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "<td class=\"altbg1\" width=\"50%\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[editor_control_bar][background]\" value=\"".htmlspecialchars_uni($css['editor_control_bar']['background'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->border."</td>\n<td><input type=\"text\" name=\"css[editor_control_bar][border]\" value=\"".htmlspecialchars_uni($css['editor_control_bar']['border'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "</tr>\n";
	echo "<tr>\n";
	echo "<td class=\"altbg1\" colspan=\"2\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n";
	echo "<td>\n";
	echo "<fieldset>\n";
	echo "<legend>".$lang->toolbar_normal."</legend>\n";
	echo "<table width=\"100%\">\n";
	echo "<tr><td>".$lang->background."</td><td><input type=\"text\" name=\"css[toolbar_normal][background]\" value=\"".htmlspecialchars_uni($css['toolbar_normal']['background'])."\" size=\"8\" class=\"inputbox\" /></td></tr>\n";
	echo "<tr><td>".$lang->border."</td><td><input type=\"text\" name=\"css[toolbar_normal][border]\" value=\"".htmlspecialchars_uni($css['toolbar_normal']['border'])."\" size=\"8\" class=\"inputbox\" /></td></tr>\n";
	echo "</table>\n";
	echo "</fieldset>\n";
	echo "</td>\n";
	echo "<td>\n";
	echo "<fieldset>\n";
	echo "<legend>".$lang->toolbar_hovered."</legend>\n";
	echo "<table width=\"100%\">\n";
	echo "<tr><td>".$lang->background."</td><td><input type=\"text\" name=\"css[toolbar_hover][background]\" value=\"".htmlspecialchars_uni($css['toolbar_hover']['background'])."\" size=\"8\" class=\"inputbox\" /></td></tr>\n";
	echo "<tr><td>".$lang->border."</td><td><input type=\"text\" name=\"css[toolbar_hover][border]\" value=\"".htmlspecialchars_uni($css['toolbar_hover']['border'])."\" size=\"8\" class=\"inputbox\" /></td></tr>\n";
	echo "</table>\n";
	echo "</fieldset>\n";
	echo "</td>\n";
	echo "<td>\n";
	echo "<fieldset>\n";
	echo "<legend>".$lang->toolbar_clicked."</legend>\n";
	echo "<table width=\"100%\">\n";
	echo "<tr><td>".$lang->background."</td><td><input type=\"text\" name=\"css[toolbar_clicked][background]\" value=\"".htmlspecialchars_uni($css['toolbar_clicked']['background'])."\" size=\"8\" class=\"inputbox\" /></td></tr>\n";
	echo "<tr><td>".$lang->border."</td><td><input type=\"text\" name=\"css[toolbar_clicked][border]\" value=\"".htmlspecialchars_uni($css['toolbar_clicked']['border'])."\" size=\"8\" class=\"inputbox\" /></td></tr>\n";
	echo "</table>\n";
	echo "</fieldset>\n";
	echo "</td>\n";
	echo "</tr>\n</table>\n";
	echo "</td>\n";
	echo "</tr>\n";
	endtable();
}

function makecssautocompleteedit($css)
{
	global $lang;
	starttable();
	tableheader($lang->autocomplete_popup);
	echo "<tr>\n<td class=\"subheader\" align=\"center\">".$lang->popup_window."</td><td class=\"subheader\" align=\"center\">".$lang->selected_result."</td>\n</tr>\n";
	echo "<tr>\n";
	echo "<td class=\"altbg1\" width=\"50%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[autocomplete][background]\" value=\"".htmlspecialchars_uni($css['autocomplete']['background'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->border."</td>\n<td><input type=\"text\" name=\"css[autocomplete][border]\" value=\"".htmlspecialchars_uni($css['autocomplete']['border'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[autocomplete][color]\" value=\"".htmlspecialchars_uni($css['autocomplete']['color'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "<td class=\"altbg1\" width=\"50%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[autocomplete_selected][background]\" value=\"".htmlspecialchars_uni($css['autocomplete_selected']['background'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[autocomplete_selected][color]\" value=\"".htmlspecialchars_uni($css['autocomplete_selected']['color'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "</tr>\n";
	endtable();
}

function makecsspopupmenuedit($css)
{
	global $lang;
	starttable();
	tableheader($lang->popup_menus, "", 3);
	echo "<tr>\n<td class=\"subheader\" align=\"center\">".$lang->popup_menu."</td><td class=\"subheader\" align=\"center\">".$lang->popup_menu_items."</td><td class=\"subheader\" align=\"center\">".$lang->popup_menu_items_hovered."</td>\n</tr>\n";
	echo "<tr>\n";
	echo "<td class=\"altbg1\" width=\"33%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[popup_menu][background]\" value=\"".htmlspecialchars_uni($css['popup_menu']['background'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->border."</td>\n<td><input type=\"text\" name=\"css[popup_menu][border]\" value=\"".htmlspecialchars_uni($css['popup_menu']['border'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "<td class=\"altbg1\" width=\"33%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[popup_item][background]\" value=\"".htmlspecialchars_uni($css['popup_item']['background'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[popup_item][color]\" value=\"".htmlspecialchars_uni($css['popup_item']['color'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "</table>\n";
	echo "<td class=\"altbg1\" width=\"33%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[popup_item_hovered][background]\" value=\"".htmlspecialchars_uni($css['popup_item_hovered']['background'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[popup_item_hovered][color]\" value=\"".htmlspecialchars_uni($css['popup_item_hovered']['color'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "</tr>\n";
	endtable();
}

function makecssreputationedit($css)
{
	global $lang;
	starttable();
	tableheader($lang->reputation_system, "", 3);
	echo "<tr>\n<td class=\"subheader\" align=\"center\">".$lang->positive_reputation_count."</td><td class=\"subheader\" align=\"center\">".$lang->neutral_reputation_count."</td><td class=\"subheader\" align=\"center\">".$lang->negative_reputation_count."</td>\n</tr>\n";
	echo "<tr>\n";
	echo "<td class=\"altbg1\" width=\"33%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[reputation_positive][color]\" value=\"".htmlspecialchars_uni($css['reputation_positive']['color'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "<td class=\"altbg1\" width=\"33%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[reputation_neutral][color]\" value=\"".htmlspecialchars_uni($css['reputation_neutral']['color'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "<td class=\"altbg1\" width=\"33%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[reputation_negative][color]\" value=\"".htmlspecialchars_uni($css['reputation_negative']['color'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "</tr>\n";
	echo "<tr>\n<td class=\"subheader\" align=\"center\">".$lang->trow_positive_reputation."</td><td class=\"subheader\" align=\"center\">".$lang->trow_neutral_reputation."</td><td class=\"subheader\" align=\"center\">".$lang->trow_negative_reputation."</td>\n</tr>\n";
	echo "<tr>\n";
	echo "<td class=\"altbg1\" width=\"33%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[trow_reputation_positive][background]\" value=\"".htmlspecialchars_uni($css['trow_reputation_positive']['background'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[trow_reputation_positive][color]\" value=\"".htmlspecialchars_uni($css['trow_reputation_positive']['color'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "<td class=\"altbg1\" width=\"33%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[trow_reputation_neutral][background]\" value=\"".htmlspecialchars_uni($css['trow_reputation_neutral']['background'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[trow_reputation_neutral][color]\" value=\"".htmlspecialchars_uni($css['trow_reputation_neutral']['color'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "<td class=\"altbg1\" width=\"33%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[trow_reputation_negative][background]\" value=\"".htmlspecialchars_uni($css['trow_reputation_negative']['background'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[trow_reputation_negative][color]\" value=\"".htmlspecialchars_uni($css['trow_reputation_negative']['color'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "</tr>\n";
	endtable();
}

function makecssvalidationedit($css)
{
	/** YES THIS IS TEMPORARY CRAP CODE, NOT TRANSLATED - We're getting a new Admin CP, remember? **/
	global $lang;
	starttable();
	tableheader("Inline Form Field Validation", "", 2);
	echo "<tr>\n<td class=\"subheader\" align=\"center\">Valid Fields</td><td class=\"subheader\" align=\"center\">Invalid Fields</td></tr>\n";
	echo "<tr>\n";
	echo "<td class=\"altbg1\" width=\"50%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[valid_field][background]\" value=\"".htmlspecialchars_uni($css['valid_field']['background'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->border."</td>\n<td><input type=\"text\" name=\"css[valid_field][border]\" value=\"".htmlspecialchars_uni($css['valid_field']['border'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[valid_field][color]\" value=\"".htmlspecialchars_uni($css['valid_field']['color'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "<td class=\"altbg1\" width=\"50%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[invalid_field][background]\" value=\"".htmlspecialchars_uni($css['invalid_field']['background'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->border."</td>\n<td><input type=\"text\" name=\"css[invalid_field][border]\" value=\"".htmlspecialchars_uni($css['invalid_field']['border'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[invalid_field][color]\" value=\"".htmlspecialchars_uni($css['invalid_field']['color'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "</td>\n";
	echo "</tr>\n";
	endtable();
	starttable();
	tableheader("Inline Form Field Validation 2", "", 3);
	echo "<tr>\n<td class=\"subheader\" align=\"center\">Valid Status Messages</td><td class=\"subheader\" align=\"center\">Invalid Error Messages</td><td class=\"subheader\" align=\"center\">Loading Status Messages</td></tr>\n";
	echo "<tr>\n";
	echo "<td class=\"altbg1\" width=\"33%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[validation_success][background]\" value=\"".htmlspecialchars_uni($css['validation_success']['background'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[validation_success][color]\" value=\"".htmlspecialchars_uni($css['validation_success']['color'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n";
	echo "<td class=\"altbg1\" valign=\"top\" colspan=\"2\">\n";
	echo "<textarea style=\"width: 98%; padding: 4px;\" rows=\"4\" name=\"css[validation_success][extra]\" class=\"$highlight\">".htmlspecialchars_uni($css['validation_success']['extra'])."</textarea>\n";
	echo "</td>\n";
	echo"</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "<td class=\"altbg1\" width=\"33%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[validation_error][background]\" value=\"".htmlspecialchars_uni($css['validation_error']['background'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[validation_error][color]\" value=\"".htmlspecialchars_uni($css['validation_error']['color'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n";
	echo "<td class=\"altbg1\" valign=\"top\" colspan=\"2\">\n";
	echo "<textarea style=\"width: 98%; padding: 4px;\" rows=\"4\" name=\"css[validation_error][extra]\" class=\"$highlight\">".htmlspecialchars_uni($css['validation_error']['extra'])."</textarea>\n";
	echo "</td>\n";
	echo"</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "<td class=\"altbg1\" width=\"33%\" valign=\"top\">\n";
	echo "<table width=\"100%\">\n";
	echo "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[validation_loading][background]\" value=\"".htmlspecialchars_uni($css['validation_loading']['background'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[validation_loading][color]\" value=\"".htmlspecialchars_uni($css['validation_loading']['color'])."\" size=\"25\" class=\"inputbox\"/></td>\n</tr>\n";
	echo "<tr>\n";
	echo "<td class=\"altbg1\" valign=\"top\" colspan=\"2\">\n";
	echo "<textarea style=\"width: 98%; padding: 4px;\" rows=\"4\" name=\"css[validation_loading][extra]\" class=\"$highlight\">".htmlspecialchars_uni($css['validation_loading']['extra'])."</textarea>\n";
	echo "</td>\n";	
	echo"</tr>\n";
	echo "</table>\n";
	echo "</td>\n";
	echo "</td>\n";
	echo "</tr>\n";	
	endtable();
}
function makecssinputedit($css)
{
	global $lang, $tid, $tcache;
	if(!is_array($tcache))
	{
		cache_themes();
	}
	starttable();
	tableheader($lang->form_elements);
	$form_elements = array("textbox", "textarea", "radio", "checkbox", "select", "button");
	$c = 0;
	foreach($form_elements as $key => $element)
	{
		$name = "";
		$revert = "";
		$highlight = "";
		if($c == 2)
		{
			$c = 1;
			echo "<tr>\n";
			echo $header_buffer;
			echo "</tr>\n";
			echo "<tr>\n";
			echo $content_buffer;
			echo "</tr>";
			$header_buffer = "";
			$content_buffer = "";
		}
		else
		{
			++$c;
		}
		$langvar = "form_elements_".$element;
		$name = $lang->$langvar;
		if($css[$element]['inherited'] != $tid && $css[$element]['inherited'] != 1)
		{
			$inheritid = $css[$element]['inherited'];
			$inheritedfrom = $tcache[$inheritid]['name'];
			$highlight = "highlight3";
			$name .= "(".$lang->inherited_from." ".$inheritedfrom.")";
		}
		elseif($css[$element]['inherited'] == 1)
		{
		}
		else
		{
			$highlight = "highlight2";
			$name .= " (".$lang->customized_this_style.")";
			$revert = "<input type=\"checkbox\" name=\"revert_css[$element]\" value=\"1\" id=\"revert_css_$element\" /> <label for=\"revert_css_$element\">".$lang->revert_customizations."</label>";
		}
		$header_buffer .= "<td class=\"subheader\" align=\"center\">".$name."</td>\n";

		$content_buffer .= "<td class=\"altbg1\" width=\"50%\" valign=\"top\">\n";
		$content_buffer .= "<table width=\"100%\">\n";
		$content_buffer .= "<tr>\n<td>".$lang->background."</td>\n<td><input type=\"text\" name=\"css[$element][background]\" value=\"".htmlspecialchars_uni($css[$element]['background'])."\" size=\"25\" class=\"$highlight\"/></td>\n</tr>\n";
		$content_buffer .= "<tr>\n<td>".$lang->font_color."</td>\n<td><input type=\"text\" name=\"css[$element][color]\" value=\"".htmlspecialchars_uni($css[$element]['color'])."\" size=\"25\"  class=\"$highlight\" /></td>\n</tr>\n";
		$content_buffer .= "<tr>\n<td>".$lang->border."</td>\n<td><input type=\"text\" name=\"css[$element][border]\" value=\"".htmlspecialchars_uni($css[$element]['border'])."\" size=\"25\"  class=\"$highlight\" /></td>\n</tr>\n";
		$content_buffer .= "<tr>\n";
		$content_buffer .= "<td class=\"altbg1\" width=\"50%\" valign=\"top\" colspan=\"2\">\n";
		$content_buffer .= "<textarea style=\"width: 98%; padding: 4px;\" rows=\"4\" name=\"css[$element][extra]\" class=\"$highlight\">".htmlspecialchars_uni($css[$element]['extra'])."</textarea>\n";
		$content_buffer .= $revert;
		$content_buffer .= "</td>\n";
		$content_buffer .= "</tr>\n";
		$content_buffer .= "</table>\n";
		$content_buffer .= "</td>\n";
	}
	echo "<tr>\n";
	echo $header_buffer;
	echo "</tr>\n";
	echo "<tr>\n";
	echo $content_buffer;
	echo "</tr>";
	$submit = makebuttoncode($lang->save_changes, $lang->save_changes, "submit");
	tablesubheader("<div style=\"float: right;\">$submit</div>", "", 2, "left");
	endtable();
}
function endtable()
{
	echo "</table>\n";
	echo "</td>\n</tr>\n</table>\n";
	echo "<br />\n";
}
function endform($submit="", $reset="")
{
	if($submit || $reset)
	{
		echo "<div align=\"center\">\n<div class=\"formbuttons\">\n";
	}
	if($submit)
	{
		echo makebuttoncode($submit, $submit, "submit");
	}
	if($reset)
	{
		echo makebuttoncode($reset, $reset, "reset");
	}
	if($submit || $reset)
	{
		echo "</div>\n</div>";
	}
	echo "</form>\n";
}

function makewarning($text)
{
	echo "<p class=\"warning\">\n".$text."\n</p>\n";
}

function cperror($message="")
{
	global $lang;

	// If there is no message, use the default error message.
	if(empty($message))
	{
		$error = $lang->error_msg;
	}

	// Are there multiple errors or is there just one?
	if(is_array($message))
	{
		$error = "<ul>\n";
		foreach($message as $item)
		{
			$error .= "\t<li>{$item}</li>\n";
		}
		$error .= "</ul>\n";
	}
	else
	{
		$error = $message;
	}

	cpheader("", 0);
	starttable("65%");
	tableheader($lang->cp_error_header);
	makelabelcode($error);
	endtable();
	cpfooter();
	exit;
}

function cpmessage($message="")
{
	global $lang;
	if(!$message)
	{
		$message = $lang->cp_message;
	}
	cpheader();
	starttable("65%");
	tableheader($lang->cp_message_header);
	makelabelcode($message);
	endtable();
	cpfooter();
	exit;
}
function cpredirect($url, $message="")
{
	global $lang;
	if(!$message)
	{
		$message = $lang->redirect_msg;
	}
	cpheader();
	starttable("65%");
	tableheader($lang->cp_message_header);
	makelabelcode($message);
	echo "<script type=\"text/javascript\">\n";
	echo "timeout = 10;\n";
	echo "function redirect() {\n";
	echo "	timerID = setTimeout(\"redirect();\", 100);\n";
	echo "	if(timeout > 0) {\n";
	echo "		timeout -= 1;\n";
	echo "	} else {\n";
	echo "		clearTimeout(timerID);\n";
	echo "		window.location = \"$url\";\n";
	echo "	}\n";
	echo "}\n";
	echo "redirect();\n";
	echo "</script>	\n";
	endtable();
	cpfooter();
}

function cpfooter($showversion=1)
{
	global $mybb, $db, $maintimer, $lang;

	echo "<div align=\"center\">\n<br />\n<br />\n";
	$totaltime = $maintimer->stop();
	$lang->footer_stats = sprintf($lang->footer_stats, $totaltime, $db->query_count);
	if(!$showversion)
	{
		$mybbversion = "";
	}
	else
	{
		$mybbversion = $mybb->version;
	}
	echo "<font size=\"1\" face=\"Verdana,Arial,Helvetica\">\n".$lang->footer_powered_by."\n<b>\n<a href=\"http://www.mybboard.com\" target=\"_blank\">MyBB</a> $mybbversion\n</b>\n<br />\n".$lang->footer_copyright." &copy; 2002-".my_date("Y")." <a href=\"http://www.mybboard.com\">MyBB Group</a>\n<br />\n".$lang->footer_stats."\n</font>\n</div>\n";
	echo "</body>\n";
	echo "</html>";
}

function getaltbg()
{
	global $bgcolor;
	if($bgcolor == "altbg1")
	{
		$bgcolor = "altbg2";
	}
	else
	{
		$bgcolor = "altbg1";
	}
	return $bgcolor;
}
function startnav()
{
	echo "<table cellpadding=\"1\" cellspacing=\"0\" border=\"0\" align=\"center\" width=\"100%\" class=\"lnavbordercolor\">\n";
	echo "<tr>\n<td>\n";
	echo "<table cellpadding=\"6\" cellspacing=\"0\" border=\"0\" width=\"100%\">\n";
}
function makenavoption($name, $url)
{
	global $navoptions;
	$navoptions .= "<li>\n\t<a href=\"$url\">$name</a>\n</li>\n";
}
function makenavselect($name)
{
	global $navoptions, $navselects;
	echo "<table cellpadding=\"1\" cellspacing=\"0\" border=\"0\" align=\"center\" width=\"100%\" class=\"lnavbordercolor\">\n";
	echo "<tr>\n<td>\n";
	echo "<table cellpadding=\"6\" cellspacing=\"0\" border=\"0\" width=\"100%\">\n";
	if($name)
	{
		echo "<tr>\n<td class=\"lnavhead\" align=\"center\">$name</td>\n</tr>\n";
	}
	echo "<tr>\n<td class=\"lnavitem\" valign=\"top\">\n";
	echo "<ul>\n";
	echo $navoptions;
	echo "</ul>\n</td>\n";
	echo "</table>\n";
	echo "</td>\n</tr>\n</table>\n";
	echo "<br />\n";

	$navoptions = "";
}


function endnav()
{
	echo "</table>\n";
	echo "</td>\n</tr>\n</table>\n";
	echo "<br />\n";
}
function makenavgroup($name="")
{
	global $noframes, $navoptions, $navselects;
	if($noframes)
	{
		echo "<td>\n<select onchange=\"navJump(this.options[this.selectedIndex].value, this.form)\">\n";
		echo "<option value=\"\">$name</option>\n<option value=\"\">&nbsp;</option>\n";
		echo $navselects;
		echo "</select>\n</td>\n";
	}
	else
	{
		echo $navselects;
		echo "<hr>";
	}
	$navselects = "";
	$navoptions = "";
}

function makehopper($name, $values, $onchange="")
{
	if(!is_array($values))
	{
		$values = array($values);
	}
	foreach($values as $action => $title)
	{
		$options .= "<option value=\"$action\">$title</option>\n";
	}
	$buttononchange = "";
	if($onchange)
	{
	  $buttononchange = " onclick=\"{$onchange} this.form.submit(); return false;\"";
      $onchange .= " ";
  	}
	return "<select name=\"$name\" onchange=\"{$onchange}this.form.submit();\">\n$options</select>\n&nbsp;\n<input type=\"submit\" value=\"Go\"$buttononchange />\n";
}

/**
* Build a dropdown to select a forum
*
* @param string The unique identifier for this dropdown, e.g. "selectedforum".
* @param int Optional number indicating the forum ID to pre-select
* @param int Optional forum ID to start the select (usually left at zero if making a complete list)
* @param string Used to indent the forum list, used in recursion
* @param int Optional toggle to show the 'no parent' option
* @param string Optional label for the value -1
* @param string Optional label for the value -2
* @return string The completed dropdown
*/
function forumselect($name, $selected="",$fid="0",$depth="", $shownone="1", $extra="", $extra2="")
{
	global $db, $forumselect, $lang, $cforumcache;

	if(!is_array($cforumcache))
	{
		$options = array(
			'order_by' => 'disporder'
		);
		$query = $db->simple_select("forums", "name, fid, pid", "", $options);
		while($forum = $db->fetch_array($query))
		{
			$cforumcache['pid'][$forum['pid']][$forum['fid']] = $forum;
			$cforumcache['fid'][$forum['fid']] = $forum;
		}
	}

	if(!$fid)
	{
		$forumselect .= "<select name=\"$name\">";
		if($extra)
		{
			$selected1 = '';
			if($selected == -1)
			{
				$selected1 = ' selected="selected"';
			}
			$forumselect .= "<option value=\"-1\"$selected1>$extra</option>";
		}
		if($extra2)
		{
			$selected2 = '';
			if($selected == -2)
			{
				$selected2 = ' selected="selected"';
			}
			$forumselect .= "<option value=\"-2\"$selected2>$extra2</option>";
		}
		if($extra || $extra2)
		{
			$forumselect .= "<option value=\"0\">-----------</option>";
		}
		if($shownone)
		{
			$forumselect .= "<option value=\"0\">$lang->parentforum_none</option><option value=\"0\">-----------</option>";
		}
		$fid = 0;
	}
	else
	{
		$startforum = $cforumcache['fid'][$fid];
		$forumselect .= "<option value=\"$startforum[fid]\"";
		if($selected == $startforum[fid])
		{
			$forumselect .= " selected";
		}
		$forumselect .= ">$depth$startforum[name]</option>";
		$depth .= "--";
	}

	if(is_array($cforumcache['pid'][$fid]))
	{
		foreach($cforumcache['pid'][$fid] as $forum)
		{
			forumselect($name, $selected, $forum[fid], $depth, $shownone, $extra, $extra2);
		}
	}
	if(!$fid)
	{
		$forumselect .= "</select>";
	}
	return $forumselect;
}

/**
* Build a list of checkboxes to select multiple forums
*
* @param string The unique identifier for this list, e.g. "selectedforums".
* @param int Optional number indicating the forum ID to pre-select
* @param int Optional forum ID to start the select (usually left at zero if making a complete list)
* @param string Used to indent the forum list, used in recursion
* @param string Optional label for the value -1
* @return string The completed checkbox list
*/
function forum_checkbox_list($name, $selected="", $fid="0", $depth="", $extra="")
{
	global $db, $forumchecklist, $lang, $cforumcache;
	if(!is_array($cforumcache))
	{
		$options = array(
			'order_by' => 'disporder'
		);
		$query = $db->simple_select("forums", "name, fid, pid", "", $options);
		while($forum = $db->fetch_array($query))
		{
			$cforumcache['pid'][$forum['pid']][$forum['fid']] = $forum;
			$cforumcache['fid'][$forum['fid']] = $forum;
		}
	}

	if(!$fid)
	{
		if($extra)
		{
			$selected1 = '';
			if(!is_array($selected))
			{
				$selected = array($selected);
			}
			if(in_array(-1, $selected))
			{
				$selected1 = ' checked="checked"';
			}
			$forumchecklist .= "<input type=\"checkbox\" name=\"{$name}[]\" value=\"-1\"$selected1 /> $extra <br /><br />\n";
		}
		$fid = 0;
	}
	else
	{
		$startforum = $cforumcache['fid'][$fid];
		$forumchecklist .= "$depth<input type=\"checkbox\" name=\"{$name}[]\" value=\"$startforum[fid]\"";
		if(in_array($startforum['fid'], $selected) || (is_array($selected) && in_array($forum['fid'], $selected)))
		{
			$forumchecklist .= ' checked="checked"';
		}
		$forumchecklist .= " /> $startforum[name]<br />\n";
		$depth .= "&nbsp;&nbsp;&nbsp;&nbsp;";
	}

	if(is_array($cforumcache['pid'][$fid]))
	{
		foreach($cforumcache['pid'][$fid] as $forum)
		{
			forum_checkbox_list($name, $selected, $forum['fid'], $depth, $extra);
		}
	}

	return $forumchecklist;
}

function checkadminpermissions($action)
{
	global $mybb, $lang;
	$perms = getadminpermissions($mybb->user['uid']);
	if($perms[$action] != "yes")
	{
		cperror($lang->access_denied);
		exit;
	}
}

function getadminpermissions($get_uid="", $get_gid="")
{
	global $db, $mybb;
	
	// Set UID and GID if none
	$uid = $get_uid;
	$gid = $get_gid;
	
	$gid_array = array();
	
	if($uid === "")
	{
		$uid = $mybb->user['uid'];
	}
	
	if(!$gid)
	{
		// Prepare user's groups since the group isn't specified
		$gid_array[] = (-1) * intval($mybb->user['usergroup']);
		$additional_groups = explode(',', $mybb->user['additionalgroups']);
		// Make sure gids are negative
		foreach($additional_groups as $g)
		{
			$gid_array[] = (-1) * abs($g);
		}
	}
	else
	{
		// Group is specified
		// Make sure gid is negative
		$gid = (-1) * abs($gid);
	}

	// What are we trying to find?
	if($get_gid && !$get_uid)
	{
		// A group only
		
		$options = array(
			"order_by" => "uid",
			"order_dir" => "ASC",
			"limit" => "1"
		);

		$query = $db->simple_select("adminoptions", "*", "(uid='$gid' OR uid='0') AND permsset != ''", $options);
		$perms = $db->fetch_array($query);
		return $perms;
	}
	else
	{
		// A user and/or group
		
		$options = array(
			"order_by" => "uid",
			"order_dir" => "DESC"
		);
		
		// Prepare user's groups into SQL format
		$group_sql = '';
		foreach($gid_array as $gid)
		{
			$group_sql .= " OR uid='{$gid}'";
		}
		
		$perms_group = array();
		$query = $db->simple_select("adminoptions", "*", "(uid='{$uid}'{$group_sql}) AND permsset != ''", $options);
		while($perm = $db->fetch_array($query))
		{
			// Sorting out which permission is which
			if($perm['uid'] > 0)
			{
				$perms_user = $perm;
				return $perms_user;
			}
			elseif($perm['uid'] < 0)
			{
				$perms_group[] = $perm;
			}
			else
			{
				$perms_def = $perm;
			}
		}
		
		// Figure out group permissions...ugh.
		foreach($perms_group as $gperms)
		{
			if(!isset($final_group_perms))
			{
				// Use this group as the base for admin group permissions
				$final_group_perms = $gperms;
				continue;
			}
			
			// Loop through each specific permission to find the highest permission
			foreach($gperms as $perm_name => $perm_value)
			{
				if($final_group_perms[$perm_name] != 'yes' && $perm_value == 'yes')
				{
					$final_group_perms[$perm_name] = 'yes';
				}
			}
		}

		// Send specific user, or group permissions before default.
		// If user's permission are explicitly set, they've already been returned above.
		if(isset($final_group_perms))
		{
			return $final_group_perms;
		}
		else
		{
			return $perms_def;
		}
	}
}

function logadmin()
{
	global $mybbadmin, $db, $mybb;
	$scriptname = basename($_SERVER['PHP_SELF']);
	$qstring = explode("&", $_SERVER['QUERY_STRING']);
	$sep = '';
	foreach($qstring as $key => $value)
	{
		$vale = explode("=", $value, 2);
		if(trim($vale[0]) != "" && trim($vale[1]) != "")
		{
			if($vale[0] != "action" && $vale[0] != "adminsid")
			{
				$querystring .= "$sep$vale[0] = $vale[1]";
				$sep = " / ";
			}
		}
	}
	$now = time();

	$insertquery = array(
		"uid" => $mybbadmin['uid'],
		"dateline" => $now,
		"scriptname" => $db->escape_string($scriptname),
		"action" => $db->escape_string($mybb->input['action']),
		"querystring" => $db->escape_string($querystring),
		"ipaddress" => $db->escape_string(get_ip())
	);

	$db->insert_query("adminlog", $insertquery);
}

function buildacpnav()
{
	global $nav, $navbits, $error_handler;
	$navsep = " &raquo; ";
	if(is_array($navbits))
	{
		reset($navbits);
		foreach($navbits as $key => $navbit)
		{
			if($navbits[$key+1])
			{
				if($navbits[$key+2])
        {
          $sep = $navsep;
        }
        else
        {
          $sep = "";
        }
				$nav .= "<a href=\"$navbit[url]\">$navbit[name]</a>$sep";
			}
		}
	}
	$navsize = count($navbits);
	$navbit = $navbits[$navsize-1];
	if($nav)
  	{
		$activesep = "<br /><img src=\"../images/nav_bit.gif\" alt=\"---\" border=\"0\" />";
	}
	$activebit = "<span class=\"active\">$navbit[name]</span>";
	$donenav = "<div  align=\"center\"><div class=\"navigation\">\n$nav$activesep$activebit\n</div></div><br />";
	
	echo $error_handler->show_warnings();
	
	return $donenav;
}

function addacpnav($name, $url="")
{
	global $navbits;
	$navsize = count($navbits);
	$navbits[$navsize]['name'] = $name;
	$navbits[$navsize]['url'] = $url;
}

function makeacpforumnav($fid)
{
	global $pforumcache, $db, $forum_cache, $navbits;
	if(!$pforumcache)
	{
		if(!is_array($forum_cache))
		{
			cache_forums();
		}
		reset($forum_cache);
		foreach($forum_cache as $key => $val)
		{
			$pforumcache[$val['fid']][$val['pid']] = $val;
		}
	}
	if(is_array($pforumcache[$fid]))
	{
		foreach($pforumcache[$fid] as $key => $forumnav)
		{
			if($fid == $forumnav['fid'])
			{
				if($pforumcache[$forumnav['pid']])
				{
					makeacpforumnav($forumnav['pid']);
				}
				$navsize = count($navbits);
				$navbits[$navsize]['name'] = $forumnav['name'];
				$navbits[$navsize]['url'] = "forums.php?fid=$forumnav[fid]";
			}
		}
	}
	return 1;
}

function quickpermissions($fid="", $pid="")
{
	global $db, $cache, $lang;
	if($fid)
	{
		$query = $db->simple_select("forums", "*", "fid='$fid'");
		$forum = $db->fetch_array($query);
		$query = $db->simple_select("forumpermissions", "*", "fid='$fid'");
		while($fperm = $db->fetch_array($query))
		{
			$fperms[$fperm[gid]] = $fperm;
		}
	}
	echo "<script type=\"text/javascript\">\n";
?>
function uncheckInheritPerm(id) {
	chk = getElemRefs("inherit:"+id);
	chk.checked = false;
	h = getElemRefs("inheritlbl_"+id);
	h.className = "";
}

function checkInheritPerm(id) {
	chk = getElemRefs("inherit:"+id);
	chk.checked = true;
	h = getElemRefs("inheritlbl_"+id);
	h.className = "highlight1";
}

function checkPermRow(id, master) {
	chk = getElemRefs("canview:"+id);
	chk.checked = master.checked;
	chk = getElemRefs("canpostthreads:"+id);
	chk.checked = master.checked;
	chk = getElemRefs("canpostreplies:"+id);
	chk.checked = master.checked;
	chk = getElemRefs("canpostpolls:"+id);
	chk.checked = master.checked;
	chk = getElemRefs("canpostattachments:"+id);
	chk.checked = master.checked;

	uncheckInheritPerm(id);
}


function getElemRefs(id) {
	if(document.getElementById) {
		return document.getElementById(id);
	}
	else if(document.all) {
		return document.all[id];
	}
	else if(document.layers) {
		return document.layers[id];
	}
}
</script>
<?php
	starttable();
	if($fid)
	{
		tableheader(sprintf($lang->quickperms_forum, $forum['name']), "", "7");
	}
	else
	{
		tableheader($lang->quickperms, "", "7");
	}
	echo "<tr>\n";
	echo "<td class=\"subheader\">".$lang->quickperms_group."</td>\n";
	echo "<td class=\"subheader\" align=\"center\" width=\"10%\">".$lang->quickperms_view."</td>\n";
	echo "<td class=\"subheader\" align=\"center\" width=\"10%\">".$lang->quickperms_postthreads."</td>\n";
	echo "<td class=\"subheader\" align=\"center\" width=\"10%\">".$lang->quickperms_postreplies."</td>\n";
	echo "<td class=\"subheader\" align=\"center\" width=\"10%\">".$lang->quickperms_postpolls."</td>\n";
	echo "<td class=\"subheader\" align=\"center\" width=\"10%\">".$lang->quickperms_upload."</td>\n";
	echo "<td class=\"subheader\" align=\"center\" width=\"10%\">".$lang->quickperms_all."</td>\n";
	echo "</tr>\n";

	$cachedforumpermissions = $cache->read("forumpermissions");

	$options = array(
		"order_by" => "title"
	);

	$query = $db->simple_select("usergroups", "*", "", $options);
	while($usergroup = $db->fetch_array($query))
	{
		$bgcolor = getaltbg();
		if($fperms[$usergroup['gid']])
		{
			$perms = $fperms[$usergroup['gid']];
		}
		elseif($pid && $cachedforumpermissions[$pid][$usergroup['gid']])
		{
			$perms = $cachedforumpermissions[$pid][$usergroup['gid']];
		}
		elseif($fid && $cachedforumpermissions[$fid][$usergroup['gid']])
		{
			$perms = $cachedforumpermissions[$fid][$usergroup['gid']];
		}
		if(!is_array($perms))
		{
			$perms = $usergroup;
		}
		if($fperms[$usergroup['gid']])
		{
			$inheritcheck = "";
			$inheritclass = "";
		}
		else
		{
			$inheritcheck = " checked=\"checked\"";
			$inheritclass = "highlight1";
		}
		if($perms['canview'] == "yes")
		{
			$canview = " checked=\"checked\"";
		}
		else
		{
			$canview = "";
		}
		if($perms['canpostthreads'] == "yes")
		{
			$canpostthreads = " checked=\"checked\"";
		}
		else
		{
			$canpostthreads = "";
		}
		if($perms['canpostreplys'] == "yes")
		{
			$canpostreplies = " checked=\"checked\"";
		}
		else
		{
			$canpostreplies = "";
		}
		if($perms['canpostpolls'] == "yes")
		{
			$canpostpolls = " checked=\"checked\"";
		}
		else
		{
			$canpostpolls = "";
		}
		if($perms['canpostattachments'] == "yes")
		{
			$canpostattachments = " checked=\"checked\"";
		}
		else
		{
			$canpostattachments = "";
		}
		if($canview && $canpostthreads && $canpostreplies && $canpostpolls && $canpostattachments)
		{
			$allcheck = " checked=\"checked\"";
		}
		else
		{
			$allcheck = "";
		}
		echo "<tr>\n";
		echo "<td class=\"$bgcolor\"><strong>$usergroup[title]</strong><br /><small><input type=\"checkbox\" name=\"inherit[$usergroup[gid]]\" id=\"inherit:$usergroup[gid]\" value=\"yes\" onclick=\"checkInheritPerm($usergroup[gid]);\"$inheritcheck /> <span id=\"inheritlbl_$usergroup[gid]\" class=\"$inheritclass\">".$lang->quickperms_inheritdefault."</span></small></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"checkbox\" name=\"canview[$usergroup[gid]]\" id=\"canview:$usergroup[gid]\" value=\"yes\" onclick=\"uncheckInheritPerm($usergroup[gid])\"$canview /></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"checkbox\" name=\"canpostthreads[$usergroup[gid]]\" id=\"canpostthreads:$usergroup[gid]\" value=\"yes\" onclick=\"uncheckInheritPerm($usergroup[gid])\"$canpostthreads /></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"checkbox\" name=\"canpostreplies[$usergroup[gid]]\" id=\"canpostreplies:$usergroup[gid]\" value=\"yes\" onclick=\"uncheckInheritPerm($usergroup[gid])\"$canpostreplies /></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"checkbox\" name=\"canpostpolls[$usergroup[gid]]\" id=\"canpostpolls:$usergroup[gid]\" value=\"yes\" onclick=\"uncheckInheritPerm($usergroup[gid])\"$canpostpolls /></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"checkbox\" name=\"canpostattachments[$usergroup[gid]]\" id=\"canpostattachments:$usergroup[gid]\" value=\"yes\" onclick=\"uncheckInheritPerm($usergroup[gid])\"$canpostattachments /></td>\n";
		echo "<td class=\"$bgcolor\" align=\"center\"><input type=\"checkbox\" onclick=\"checkPermRow($usergroup[gid], this);\"$allcheck /></td>\n";
		echo "</tr>\n";
		unset($perms);
	}
	endtable();
}

function savequickperms($fid)
{
	global $db, $inherit, $canview, $canpostthreads, $canpostreplies, $canpostpolls, $canpostattachments, $cache;

	$query = $db->simple_select("usergroups");

	while($usergroup = $db->fetch_array($query))
	{
		// Delete existing permissions
		$db->delete_query("forumpermissions", "fid='$fid' AND gid='$usergroup[gid]'");

		// Only insert the new ones if we're using custom permissions
		if($inherit[$usergroup['gid']] != "yes")
		{
			if($canview[$usergroup['gid']] == "yes")
			{
				$pview = "yes";
			}
			else
			{
				$pview = "no";
			}
			if($canpostthreads[$usergroup['gid']] == "yes")
			{
				$pthreads = "yes";
			}
			else
			{
				$pthreads = "no";
			}
			if($canpostreplies[$usergroup['gid']] == "yes")
			{
				$preplies = "yes";
			}
			else
			{
				$preplies = "no";
			}
			if($canpostpolls[$usergroup['gid']] == "yes")
			{
				$ppolls = "yes";
			}
			else
			{
				$ppolls = "no";
			}
			if($canpostattachments[$usergroup['gid']] == "yes")
			{
				$pattachments = "yes";
			}
			else
			{
				$pattachments = "no";
			}
			if(!$preplies && !$pthreads)
			{
				$ppost = "no";
			}
			else
			{
				$ppost = "yes";
			}

			$insertquery = array(
				"fid" => $fid,
				"gid" => $usergroup['gid'],
				"canview" => $pview,
				"canviewthreads" => $pview,
				"candlattachments" => $pview,
				"canpostthreads" => $pthreads,
				"canpostreplys" => $preplies,
				"canpostattachments" => $pattachments,
				"canratethreads" => $pview,
				"caneditposts" => $ppost,
				"candeleteposts" => $ppost,
				"candeletethreads" => $pthreads,
				"caneditattachments" => $pattachments,
				"canpostpolls" => $ppolls,
				"canvotepolls" => $pview,
				"cansearch" => $pview
			);
			$db->insert_query("forumpermissions", $insertquery);
		}
	}
	$cache->update_forumpermissions();
}

function build_css_array($tid, $addinherited=1)
{
	global $db, $tcache, $cssselectors;
	if(!is_array($tcache))
	{
		cache_themes();
	}
	$theme = $tcache[$tid];
	foreach($cssselectors as $selector => $realname)
	{
		$css[$selector] = build_css_selector_array($theme['tid'], $selector, $addinherited);
	}
	return $css;
}
function build_css_selector_array($tid, $selector, $addinherited=1)
{
	global $tcache;
	if(!$tcache[$tid])
	{
		return false;
	}
	$theme = $tcache[$tid];
	$cssbits = $theme['cssbits'];
	if(!$cssbits[$selector])
	{
		if($theme['pid'] > 0)
		{
			$cssbit = build_css_selector_array($theme['pid'], $selector, $addinherited);
		}
	}
	else // Is customized in this theme
	{
		$cssbit = $cssbits[$selector];
		if($addinherited)
		{
			$cssbit['inherited'] = $tid;
		}
	}
	return $cssbit;
}

function build_theme_array($tid, $addinherited=1)
{
	global $db, $tcache, $themebitlist;
	if(!is_array($tcache))
	{
		cache_themes();
	}
	$theme = $tcache[$tid];
	foreach($themebitlist as $themebit)
	{
		$thebit = build_theme_bit_array($theme['tid'], $themebit, $addinherited);
		if($thebit['inherited'])
		{
			$tdetail['inherited'][$themebit] = $thebit['inherited'];
		}
		$tdetail[$themebit] = $thebit['themebit'];
	}
	return $tdetail;
}
function build_theme_bit_array($tid, $themebit, $addinherited=1)
{
	global $tcache;
	if(!$tcache[$tid])
	{
		return false;
	}
	$theme = $tcache[$tid];
	$pid = $theme['pid'];
	$themebits = $theme['themebits'];
	if($themebits[$themebit] && !$themebits['inherited'][$themebit])
	{
		$thebit = $themebits[$themebit];
		if($addinherited)
		{
			$inherited = $tid;
		}
	}
	else
	{
		if($theme['pid'] > 0)
		{
			$thetbit = build_theme_bit_array($theme['pid'], $themebit, $addinherited);
			$inherited = $thetbit['inherited'];
			$thebit = $thetbit['themebit'];
		}

	}
	return array("themebit" => $thebit, "inherited" => $inherited);
}

function make_theme($themebits="", $css="", $pid=0, $isnew=0)
{
	global $db, $mybb, $themebitlist, $cssselectors, $revert_themebits;
	if(!$css || !$themebits || $isnew)
	{
		$query = $db->simple_select("themes", "*", "tid='$pid'");
		$parent = $db->fetch_array($query);
		if(!$themebits || $isnew)
		{
			$themebits = unserialize($parent['themebits']);
		}
		if(!$css || $isnew)
		{
			$css = unserialize($parent['cssbits']);
		}

	}
	// Build the actual css
	$cssbits = $css;
	if($isnew != 1)
	{
		// Check the inheritance stuff and unset inherited values
		// Theme bits
		$parentbits = build_theme_array($pid);
		foreach($themebitlist as $themebit)
		{
			$parentbit = $parentbits[$themebit];
			$childbit = $themebits[$themebit];
			if(($parentbit == $childbit || $revert_themebits[$themebit]) && $themebit != "extracss")
			{
				$themebits['inherited'][$themebit] = $parentbits['inherited'][$themebit];
				$themebits[$themebit] = $parentbit;
			}
			$parentbit = $childbit = "";
		}
		// CSS bits
		$parentcss = build_css_array($pid, 0);
		foreach($cssselectors as $selector => $realname)
		{
			$parentbit = serialize(killempty($parentcss[$selector]));
			$childbit = serialize(killempty($css[$selector]));
			if($parentbit == $childbit)
			{
				unset($cssbits[$selector]);
			}
			if($mybb->input['revert_css'][$selector])
			{
				$css[$selector] = $parentbit;
				unset($cssbits[$selector]);
			}
			$parentbit = $childbit = "";
		}
		$css = array_merge($parentcss, $cssbits);
	}
	else
	{
		//unset($css); unset($cssbits);
		unset($cssbits);
		$themebits = build_theme_array($pid);
		unset($themebits['extracss']);
	}
	$csscontents = build_css($css);
	return array("css" => $csscontents, "cssbits" => $cssbits, "themebits" => $themebits);
}

function get_parent_theme_bits($pid)
{
	global $db, $themebits;

	$query = $db->simple_select("themes", "themebits", "tid='$pid'");
	$parent = $db->fetch_array($query);
	$bits = unserialize($parent['themebits']);
	foreach($themebits as $themebit)
	{
		$theme[$themebit] = $bits[$themebit];
	}
	return $theme;
}

function build_css($array, $name="")
{
	global $cssselectors;
	if(!is_array($array))
	{
		return;
	}
	foreach($array as $friendlyname => $bits)
	{
		$selector = $cssselectors[$friendlyname];
		if(is_array($bits))
		{

			foreach($bits as $attribute => $value)
			{
				if(is_array($value))
				{
					$subcss[$attribute] = $value;
					$incss .= build_css($subcss, $friendlyname);
					unset($subcss);
				}
				elseif($attribute == "extra")
				{
					$extra = $value;
				}
				else
				{
					if($value)
					{
						$cssbits .= "\t".$attribute.": ".$value.";\n";
					}
				}
			}
		}
		if($cssbits || $extra)
		{
			if($extra)
			{
				$extrabits = explode("\n", $extra);
				foreach($extrabits as $exbit)
				{
					$cssbits .= "\t".$exbit."\n";
				}
			}
			$doname = 0;
			if(($name != "body" || ($name != "body" && $selector != "a_link" && $selector != "a_visited" && $selector != "a_hover")) && $name)
			{
				$name = $cssselectors[$name];
				$css .= $name." ";
				$doname = 1;
			}
			if($selector == "a:hover")
			{
				$selector = "a:hover, ";
				if($name && $doname) { $selector .= $name." "; }
				$selector .= "a:active";
			}
			$css .= $selector." {\n".$cssbits."}\n";
			$css .= $incss;
		}
		$cssbits = $incss = "";
		$extra = "";
	}
	return $css;
}

function makethemebitedit($title, $name)
{
	global $tid, $themebits, $tcache, $lang, $db, $theme;
	if(!is_array($tcache))
	{
		cache_themes();
	}
	$bgcolor = getaltbg();
	if($name == "extracss" && $themebits['extracss'] == "")
	{
		$themebits['inherited']['extracss'] = 0;
	}
	if($themebits['inherited'][$name] && $themebits['inherited'][$name] != $tid && $themebits['inherited'][$name] != 1)
	{
		$inheritid = $themebits['inherited'][$name];
		$inheritedfrom = $tcache[$inheritid]['name'];
		$highlight = "highlight3";
		$inheritnote = "(".$lang->inherited_from." ".$inheritedfrom.")";
	}
	elseif($themebits['inherited'][$name] == 1 || $tcache[$tid]['parent'] == 0)
	{
		$highlight = "";
	}
	else
	{
		$highlight = "highlight2";
		$customnote = " (".$lang->customized_this_style.")";
		$custom = 1;
	}
	if($name != "extracss")
	{
	echo "<tr>\n<td class=\"$bgcolor\" valign=\"top\" width=\"40%\">$title</td>\n";
	echo "<td class=\"$bgcolor\" valign=\"top\" width=\"60%\">";
	echo "<table cellspacing=\"0\" cellpadding=\"0\" border=\"0\"><tr><td>\n";
	}
	if($name == "templateset")
	{
		$options = array(
			"order_by" => "title",
			"order_dir" => "ASC"
		);

		$query = $db->simple_select("templatesets", "*", "", $options);

		while($templateset = $db->fetch_array($query))
		{
			$selected = "";
			if($templateset['sid'] == $themebits[$name])
			{
				$selected = "selected";
			}
			$templatesets .= "<option value=\"".$templateset['sid']."\" $selected>".$templateset['title']."</option>\n";
		}
		echo "<select name=\"themebits[$name]\">$templatesets</select>";
	}
	elseif($name == "extracss")
	{
		echo "<tr>\n";
		echo "<td class=\"altbg1\" align=\"center\">\n";
		echo "<textarea style=\"width: 98%; padding: 4px;\"	class=\"$highlight\" rows=\"9\"name=\"themebits[extracss]\">".htmlspecialchars_uni($theme['extracss'])."</textarea>$revcustom\n";
		echo "</td>\n";
		echo "</tr>\n";
	}
	else
	{
		$value = $themebits[$name];
		$value = htmlspecialchars_uni($value);
		echo "<input type=\"text\" name=\"themebits[$name]\" value=\"$value\" size=\"20\" class=\"$highlight\" />\n";
	}
	if($name != "extracss")
	{
		echo "</td>";
		if($custom == 1)
		{
			echo "<td><small><input type=\"checkbox\" name=\"revert_themebits[$name]\" id=\"revert_themebit_$name\" value=\"1\" /><label for=\"revert_themebit_$name\">".$lang->revert_customizations."</label></small></td>\n</tr>";
			echo "<tr>\n<td colspan=\"2\" align=\"center\"><span class=\"smalltext\">$customnote</span></td>\n";
		}
		elseif($inheritid)
		{
			echo "</tr>\n<td><span class=\"smalltext\">$inheritnote</span></td>";
		}
		echo "</tr></table></td></tr>";
	}
}

function cache_themes()
{
	global $db, $tcache;

	$options = array(
		"order_by" => "pid, name"
	);

	$query = $db->simple_select("themes", "*", "", $options);

	while($theme = $db->fetch_array($query))
	{
		$theme['themebits'] = unserialize($theme['themebits']);
		$theme['cssbits'] = unserialize($theme['cssbits']);
		$tcache[$theme['tid']] = $theme;
	}
}

function make_theme_list($tid="0", $depth="")
{
	global $db, $tcache, $tcache2, $lang;
	if(!is_array($tcache))
	{
		cache_themes();
	}
	if(!is_array($tcache2))
	{
		$query = $db->query("
			SELECT style, COUNT(uid) AS users
			FROM ".TABLE_PREFIX."users
			GROUP BY style
		");
		while($userstyle = $db->fetch_array($query))
		{
			$tcache[$userstyle['style']]['users'] = $userstyle['users'];
		}
		foreach($tcache as $theme)
		{
			$tcache2[$theme['pid']][$theme['tid']] = $theme;
		}
		unset($theme);
	}
	if(is_array($tcache2[$tid]))
	{
		foreach($tcache2[$tid] as $theme)
		{
			$bgcolor = getaltbg();
			if($theme['def'] == 1)
			{
				$def = " (" . $lang->default . ")";
				$setdefault = "";
			}
			else
			{
				$setdefault = 1;
				$def = "";
			}
			if($theme['users'])
			{
				$users = sprintf($lang->theme_users, $theme['users']);
			}
			else
			{
				$users = "";
			}

			echo "<tr>\n";
			echo "<td class=\"$bgcolor\">$depth$theme[name]$users$def</td>\n";
			echo "<td class=\"$bgcolor\" align=\"right\" nowrap=\"nowrap\">";
			echo "<select name=\"theme_".$theme['tid']."\" onchange=\"theme_hop($theme[tid]);\">\n";
			echo "<option value=\"\" style=\"font-weight: bold;\">$lang->theme_options&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</option>\n";
			if($theme['tid'] > 1)
			{
				//echo "<option value=\"settings\">- $lang->edit_theme_settings</option>\n";
				echo "<option value=\"delete\">- $lang->del_theme</option>\n";
				if($setdefault)
				{
					echo "<option value=\"default\">- $lang->set_as_default</option>";
				}
				echo "<option value=\"force\">- $lang->force_on_users</option>";
				echo "<option value=\"\" style=\"font-weight: bold;\">$lang->theme_style</option>";
				echo "<option value=\"edit\" selected>- $lang->edit_theme_style</option>\n";
			}
			echo "<option value=\"\" style=\"font-weight: bold;\">$lang->other_options</option>\n";
			echo "<option value=\"download\">- $lang->export_theme</option>\n";
			echo "</select>&nbsp;<input type=\"button\" onclick=\"theme_hop($theme[tid]);\" value=\"$lang->go\" /></td>\n";
			echo "</tr>\n";
			if(is_array($tcache2[$theme['tid']]))
			{
				make_theme_list($theme['tid'], $depth."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;");
			}
		}
	}
}

function make_theme_select($name, $selected="", $tid="0", $depth="")
{
	global $db, $themeselect, $tcache, $tcache2;
	if(!$tid)
	{
		$themeselect .= "<select name=\"$name\">";
	}
	if(!is_array($tcache))
	{
		cache_themes();
	}
	if(!is_array($tcache2))
	{
		foreach($tcache as $theme)
		{
			$tcache2[$theme['pid']][$theme['tid']] = $theme;
		}
		unset($theme);
	}
	if(is_array($tcache2[$tid]))
	{
		foreach($tcache2[$tid] as $theme)
		{
			$sel = "";
			if($theme['tid'] == $selected)
			{
				$sel = "selected=\"selected\"";
			}
			$themeselect .= "<option value=\"".$theme['tid']."\" $sel>".$depth.$theme['name']."</option>";
			if(is_array($tcache2[$theme['tid']]))
			{
				$depth .= "--";
				make_theme_select($name, $selected, $theme['tid'], $depth);
			}
		}
	}
	if(!$tid)
	{
		$themeselect .= "</select>";
	}
	return $themeselect;
}

function update_theme($tid, $pid="", $themebits="", $css="", $child=0, $isnew=0)
{
	global $tcache, $db, $tcache2;
	if(!is_array($tcache))
	{
		cache_themes();
	}
	if(!is_array($tcache2))
	{
		foreach($tcache as $theme)
		{
			$tcache2[$theme['pid']][$theme['tid']] = $theme;
		}
		unset($theme);
	}
	if($child == 1 && $pid)
	{
		$css = $tcache[$tid]['cssbits'];
		$themebits = $tcache[$tid]['themebits'];
	}
	else
	{
		$pid = $tcache[$tid]['pid'];
	}
	$tname = $tcache[$tid]['name'];
	$updatedthemes .= "<li>$tname</li>";
	$newtheme = make_theme($themebits, $css, $pid, $isnew);
	$theme['css'] = $newtheme['css'];
	$theme['cssbits'] = $newtheme['cssbits'];
	$theme['themebits'] = $newtheme['themebits'];
	$theme['extracss'] = $newtheme['themebits']['extracss'];
	$tcache[$tid] = array_merge($tcache[$tid], $theme);
	$masterextra = $tcache[1]['extracss'];

	if($masterextra)
	{
		$theme['css'] .= "\n/* Additional CSS (Master) */\n";
		$theme['css'] .= $masterextra;
	}
	if($theme['extracss'] && $tid != 1 && serialize($theme['extracss']) != serialize($masterextra))
	{
		$theme['css'] .= "\n/* Additional CSS (Custom) */\n";
		$theme['css'] .= $theme['extracss'];
	}

	$theme['css'] = $db->escape_string($theme['css']);
	$theme['themebits'] = $db->escape_string(serialize($theme['themebits']));
	$theme['cssbits'] = $db->escape_string(serialize($theme['cssbits']));
	$theme['extracss'] = $db->escape_string($theme['extracss']);
	$db->update_query("themes", $theme, "tid='$tid'");

	// Cache the CSS if we're supposed to
	update_css_file($tid);

	// Update kids!
	if(is_array($tcache2[$tid]))
	{
		$updatedthemes .= "<ul>";
		foreach($tcache2[$tid] as $ctid => $ct)
		{
			$updatedthemes .= update_theme($ctid, $tid, "", "", 1, $isnew);
		}
		$updatedthemes .= "</ul>";
	}
	return $updatedthemes;
}

function killempty($array)
{
	if(!is_array($array))
	{
		return;
	}
	foreach($array as $key => $val)
	{
		if(is_array($val))
		{
			$array[$key] = killempty($val);
			$val = $array[$key];
		}
		if(empty($val))
		{
			unset($array[$key]);
		}
	}
	return $array;
}

/**
* Build a date dropdown and return the HTML for it.
*
* @param string The unique identifier for this dropdown, e.g. "birthday".
* @param array Optional array of options for this dropdown.
*/
function build_date_dropdown($id, $options=array())
{
	global $lang;

	// Start building the dropdown HTML.
	$dropdown = '';
	// Now add the days.
	$dropdown .= "<select name=\"{$id}_day\">\n";
	if(isset($options['blank_fields']) && $options['blank_fields'] == true)
	{
		$dropdown .= "<option value=\"0\"></option>\n";
	}
	for($d = 1; $d <= 31; ++$d)
	{
		if($d == $options['selected_day'])
		{
			$dropdown .= "<option selected=\"selected\" value=\"{$d}\">{$d}</option>\n";
		}
		else
		{
			$dropdown .= "<option value=\"{$d}\">{$d}</option>\n";
		}
	}
	$dropdown .= "</select>\n";

	// The months.
	$dropdown .= "<select name=\"{$id}_month\">\n";
	if(isset($options['blank_fields']) && $options['blank_fields'] == true)
	{
		$dropdown .= "<option value=\"0\"></option>\n";
	}
	for($m = 1; $m <= 12; ++$m)
	{
		$month_lang = 'month_'.$m;
		$month = $lang->$month_lang;

		if($m == $options['selected_month'])
		{
			$dropdown .= "<option selected=\"selected\" value=\"{$m}\">{$month}</option>\n";
		}
		else
		{
			$dropdown .= "<option value=\"{$m}\">{$month}</option>\n";
		}
	}
	$dropdown .= "</select>\n";

	// Add years to the dropdown.
	$this_year = my_date("Y");
	$years = array();

	// Is there a specified limit for showing the years?
	if(array_key_exists('years_back', $options) && array_key_exists('years_ahead', $options))
	{
		for($y = $this_year-$options['years_back']; $y <= ($this_year+$options['years_ahead']); ++$y)
	    {
	        $years[$y] = $y;
		}
	}
	else
	{
		for($y = $this_year-5; $y <= ($this_year+5); ++$y)
	    {
	        $years[$y] = $y;
		}
	}
	krsort($years);

	// And the years.
	$dropdown .= "<select name=\"{$id}_year\">\n";
	if(isset($options['blank_fields']) && $options['blank_fields'] == true)
	{
		$dropdown .= "<option value=\"0\"></option>\n";
	}
	foreach($years as $k => $v)
	{
		if($k == $options['selected_year'] || (!$options['selected_year'] && $k == $this_year && !isset($options['no_selected_year'])))
		{
			$dropdown .= "<option selected=\"selected\" value=\"{$k}\">{$v}</option>\n";
		}
		else
		{
			$dropdown .= "<option value=\"{$k}\">{$v}</option>\n";
		}
	}
	$dropdown .= "</select>\n";

	// Add time, too?
	if($options['show_time'] === true)
	{
		// Build hours HTML.
		$dropdown .= "<select name=\"{$id}_hours\">\n";
		if(isset($options['blank_fields']) && $options['blank_fields'] == true)
		{
			$dropdown .= "<option value=\"0\"></option>\n";
		}
		for($h = 0; $h <= 23; ++$h)
		{
			if($h == $options['selected_hour'])
			{
				$dropdown .= "<option selected=\"selected\" value=\"{$h}\">{$h}</option>\n";
			}
			else
			{
				$dropdown .= "<option value=\"{$h}\">{$h}</option>\n";
			}
		}
		$dropdown .= "</select>\n";

		// And the minutes HTML.
		$dropdown .= "<select name=\"{$id}_minutes\">\n";
		if(isset($options['blank_fields']) && $options['blank_fields'] == true)
		{
			$dropdown .= "<option value=\"0\"></option>\n";
		}
		for($min = 0; $min <= 59; ++$min)
		{
			if($min == $options['selected_minute'])
			{
				$dropdown .= "<option selected=\"selected\" value=\"{$min}\">{$min}</option>\n";
			}
			else
			{
				$dropdown .= "<option value=\"{$min}\">{$min}</option>\n";
			}
		}
		$dropdown .= "</select>\n";
	}

	return $dropdown;

}

function update_css_file($tid)
{
	global $mybb, $db;
	$filename = MYBB_ROOT.'css/theme_'.intval($tid).'.css';

	//If the CSS storage medium is in a file, then create a new css file
	if($mybb->settings['cssmedium'] == 'file')
	{
		$query = $db->simple_select('themes', 'tid,name,css', "tid='".intval($tid)."'");
		$theme = $db->fetch_array($query);

		$theme['css'] = "/**\n * CSS for theme \"{$theme['name']}\" (tid {$theme['tid']})\n * Cached:".my_date("r")."\n *\n * DO NOT EDIT THIS FILE\n *\n */\n\n".$theme['css'];
		$theme['css'] = preg_replace("#url\((\"|'|)(.*)\\1\)#e", "fix_css_urls('$2')", $theme['css']);

		$fp = @fopen($filename, "w");
		if($fp)
		{
			fwrite($fp, $theme['css']);
			fclose($fp);

			$update_theme = array(
				"csscached" => time()
			);
			$db->update_query("themes", $update_theme, "tid='{$theme['tid']}'");
			return true;
		}
	}
	// If we are still here, could not write to folder or themes are not supposed to be cached to files
	$update_theme = array(
		"csscached" => 0
	);
	$db->update_query("themes", $update_theme, "tid='{$theme['tid']}'");
	return false;
}

function fix_css_urls($url)
{
	if(!preg_match("#^(https?://|/)#i", $url))
	{
		return "url(../{$url})";
	}
	else
	{
		return "url({$url})";
	}
}

/**
* Build a checkbox list of usergroups
*
* @param string The unique identifier for this list, e.g. "additionalgroups".
* @param array Optional array of preselected groups.
* @param string Optional SQL where limiter
* @return string Completed list of checkboxes
*/
function make_usergroup_checkbox_code($name, $checked_groups='', $where='')
{
	global $db;
	if(!is_array($checked_groups))
	{
		$checked_groups = array($checked_groups);
	}
	$options = array(
		'order_by' => 'title',
		'order_dir' => 'ASC'
		);
	$query = $db->simple_select("usergroups", "gid, title", $where, $options);
	while($usergroup = $db->fetch_array($query))
	{
		$checked = '';
		if(in_array($usergroup['gid'], $checked_groups))
		{
			$checked = ' checked="checked"';
		}
		$groups[] = "<input type=\"checkbox\" name=\"{$name}[]\" value=\"{$usergroup['gid']}\"{$checked} /> {$usergroup['title']}";
	}
	$groups = implode('<br />', $groups);
	return $groups;
}

/**
 * Checks if a certain user ID is in the list of super administrators
 *
 * @param int The user ID to be checked
 * @return boolean true when the user is a super admin, false when not
 */
function is_super_admin($uid)
{
	global $config;
	$config['super_admins'] = str_replace(" ", "", $config['super_admins']);
	if(my_strpos(",{$config['super_admins']},", ",{$uid},") === false)
	{
		return false;
	}
	else
	{
		return true;
	}
}

/**
 * Makes a parent list for forums
 *
 * @param int The forum ID
 * @param string The separator of the parent forum IDs
 * @return string The generated parent list
 */
function makeparentlist($fid, $navsep=",")
{
	global $pforumcache, $db;
	if(!$pforumcache)
	{
		$options = array(
			"order_by" => "disporder, pid"
		);
		$query = $db->simple_select("forums", "name, fid, pid", "", $options);
		while($forum = $db->fetch_array($query))
		{
			$pforumcache[$forum[fid]][$forum[pid]] = $forum;
		}
	}
	reset($pforumcache);
	reset($pforumcache[$fid]);
	foreach($pforumcache[$fid] as $key => $forum)
	{
		if($fid == $forum['fid'])
		{
			if($pforumcache[$forum['pid']])
			{
				$navigation = makeparentlist($forum['pid'], $navsep) . $navigation;
			}
			if($navigation)
			{
				$navigation .= $navsep;
			}
			$navigation .= $forum['fid'];
		}
	}
	return $navigation;
}
?>
