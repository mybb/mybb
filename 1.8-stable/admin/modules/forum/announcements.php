<?php
/**
 * MyBB 1.6
 * Copyright 2010 MyBB Group, All Rights Reserved
 *
 * Website: http://mybb.com
 * License: http://mybb.com/about/license
 *
 * $Id: announcements.php 5353 2011-02-15 14:24:00Z Tomm $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->forum_announcements, "index.php?module=forum-announcements");

if($mybb->input['action'] == "add" || !$mybb->input['action'])
{
	$sub_tabs['forum_announcements'] = array(
		'title' => $lang->forum_announcements,
		'link' => "index.php?module=forum-announcements",
		'description' => $lang->forum_announcements_desc
	);

	$sub_tabs['add_announcement'] = array(
		'title' => $lang->add_announcement,
		'link' => "index.php?module=forum-announcements&amp;action=add",
		'description' => $lang->add_announcement_desc
	);
}
else if($mybb->input['action'] == "edit")
{
	$sub_tabs['forum_announcements'] = array(
		'title' => $lang->forum_announcements,
		'link' => "index.php?module=forum-announcements",
		'description' => $lang->forum_announcements_desc
	);
	
	$sub_tabs['update_announcement'] = array(
		'title' => $lang->update_announcement,
		'link' => "index.php?module=forum-announcements&amp;action=add",
		'description' => $lang->update_announcement_desc
	);
}

$plugins->run_hooks("admin_forum_announcements_begin");

if($mybb->input['action'] == "add")
{
	$plugins->run_hooks("admin_forum_announcements_add");
	
	if($mybb->request_method == "post")
	{
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}
		
		if(!trim($mybb->input['message']))
		{
			$errors[] = $lang->error_missing_message;
		}
		
		if(!trim($mybb->input['fid']))
		{
			$errors[] = $lang->error_missing_forum;
		}
		
		if(!$errors)
		{
			$startdate = @explode(" ", $mybb->input['starttime_time']);
			$startdate = @explode(":", $startdate[0]);
			$enddate = @explode(" ", $mybb->input['endtime_time']);
			$enddate = @explode(":", $enddate[0]);
		
			if(stristr($mybb->input['starttime_time'], "pm"))
			{
				$startdate[0] = 12+$startdate[0];
				if($startdate[0] >= 24)
				{
					$startdate[0] = "00";
				}
			}
			
			if(stristr($mybb->input['endtime_time'], "pm"))
			{
				$enddate[0] = 12+$enddate[0];
				if($enddate[0] >= 24)
				{
					$enddate[0] = "00";
				}
			}
			
			$months = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');			
			if(!in_array($mybb->input['starttime_month'], $months))
			{
				$mybb->input['starttime_month'] = 1;
			}
			
			$startdate = gmmktime(intval($startdate[0]), intval($startdate[1]), 0, (int)$mybb->input['starttime_month'], intval($mybb->input['starttime_day']), intval($mybb->input['starttime_year']));
			
			if($mybb->input['endtime_type'] == "2")
			{
				$enddate = '0';
			}
			else
			{
				if(!in_array($mybb->input['endtime_month'], $months))
				{
					$mybb->input['endtime_month'] = 1;
				}
				$enddate = gmmktime(intval($enddate[0]), intval($enddate[1]), 0, (int)$mybb->input['endtime_month'], intval($mybb->input['endtime_day']), intval($mybb->input['endtime_year']));
			}
			
			$insert_announcement = array(
				"fid" => $mybb->input['fid'],
				"uid" => $mybb->user['uid'],
				"subject" => $db->escape_string($mybb->input['title']),
				"message" => $db->escape_string($mybb->input['message']),
				"startdate" => $startdate,
				"enddate" => $enddate,
				"allowhtml" => $db->escape_string($mybb->input['allowhtml']),
				"allowmycode" => $db->escape_string($mybb->input['allowmycode']),
				"allowsmilies" => $db->escape_string($mybb->input['allowsmilies']),
			);
	
			$aid = $db->insert_query("announcements", $insert_announcement);
			
			$plugins->run_hooks("admin_forum_announcements_add_commit");
	
			// Log admin action
			log_admin_action($aid, $mybb->input['title']);
			$cache->update_forumsdisplay();
	
			flash_message($lang->success_added_announcement, 'success');
			admin_redirect("index.php?module=forum-announcements");
		}
	}
	
	$page->add_breadcrumb_item($lang->add_an_announcement);
	$page->output_header($lang->add_an_announcement);
	$page->output_nav_tabs($sub_tabs, "add_announcement");

	$form = new Form("index.php?module=forum-announcements&amp;action=add", "post");
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	
	if($mybb->input['endtime_type'] == "1")
	{
		$endtime_checked[1] = "checked=\"checked\"";
		$endtime_checked[2] = "";
	}
	else
	{		
		$endtime_checked[1] = "";
		$endtime_checked[2] = "checked=\"checked\"";
	}
	
	if(!$mybb->input['starttime_time'])
	{
		$start_time = explode("-", gmdate("g-i-a", TIME_NOW));
		$mybb->input['starttime_time'] = $start_time[0].":".$start_time[1]." ".$start_time[2];
	}
	
	if(!$mybb->input['endtime_time'])
	{
		$end_time = explode("-", gmdate("g-i-a", TIME_NOW));
		$mybb->input['endtime_time'] = $end_time[0].":".$end_time[1]." ".$end_time[2];
	}
	
	if($mybb->input['starttime_day'])
	{
		$startday = intval($mybb->input['starttime_day']);
	}
	else
	{
		$startday = gmdate("j", TIME_NOW);
	}
	
	if($mybb->input['endtime_day'])
	{
		$endday = intval($mybb->input['endtime_day']);
	}
	else
	{
		$endday = gmdate("j", TIME_NOW);
	}
	
	for($i = 1; $i <= 31; ++$i)
	{
		if($startday == $i)
		{
			$startdateday .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$startdateday .= "<option value=\"$i\">$i</option>\n";
		}
		
		if($endday == $i)
		{
			$enddateday .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$enddateday .= "<option value=\"$i\">$i</option>\n";
		}
	}
	
	if($mybb->input['starttime_month'])
	{
		$startmonth = intval($mybb->input['starttime_month']);
		$startmonthsel[$startmonth] = "selected=\"selected\"";
	}
	else
	{
		$startmonth = gmdate("m", TIME_NOW);
		$startmonthsel[$startmonth] = "selected=\"selected\"";
	}
	
	if($mybb->input['endtime_month'])
	{
		$endmonth = intval($mybb->input['endtime_month']);
		$endmonthsel[$endmonth] = "selected=\"selected\"";
	}
	else
	{
		$endmonth = gmdate("m", TIME_NOW);
		$endmonthsel[$endmonth] = "selected=\"selected\"";
	}
	
	$startdatemonth .= "<option value=\"01\" {$startmonthsel['01']}>{$lang->january}</option>\n";
	$enddatemonth .= "<option value=\"01\" {$endmonthsel['01']}>{$lang->january}</option>\n";
	$startdatemonth .= "<option value=\"02\" {$startmonthsel['02']}>{$lang->february}</option>\n";
	$enddatemonth .= "<option value=\"02\" {$endmonthsel['02']}>{$lang->february}</option>\n";
	$startdatemonth .= "<option value=\"03\" {$startmonthsel['03']}>{$lang->march}</option>\n";
	$enddatemonth .= "<option value=\"03\" {$endmonthsel['03']}>{$lang->march}</option>\n";
	$startdatemonth .= "<option value=\"04\" {$startmonthsel['04']}>{$lang->april}</option>\n";
	$enddatemonth .= "<option value=\"04\" {$endmonthsel['04']}>{$lang->april}</option>\n";
	$startdatemonth .= "<option value=\"05\" {$startmonthsel['05']}>{$lang->may}</option>\n";
	$enddatemonth .= "<option value=\"05\" {$endmonthsel['05']}>{$lang->may}</option>\n";
	$startdatemonth .= "<option value=\"06\" {$startmonthsel['06']}>{$lang->june}</option>\n";
	$enddatemonth .= "<option value=\"06\" {$endmonthsel['06']}>{$lang->june}</option>\n";
	$startdatemonth .= "<option value=\"07\" {$startmonthsel['07']}>{$lang->july}</option>\n";
	$enddatemonth .= "<option value=\"07\" {$endmonthsel['07']}>{$lang->july}</option>\n";
	$startdatemonth .= "<option value=\"08\" {$startmonthsel['08']}>{$lang->august}</option>\n";
	$enddatemonth .= "<option value=\"08\" {$endmonthsel['08']}>{$lang->august}</option>\n";
	$startdatemonth .= "<option value=\"09\" {$startmonthsel['09']}>{$lang->september}</option>\n";
	$enddatemonth .= "<option value=\"09\" {$endmonthsel['09']}>{$lang->september}</option>\n";
	$startdatemonth .= "<option value=\"10\" {$startmonthsel['10']}>{$lang->october}</option>\n";
	$enddatemonth .= "<option value=\"10\" {$endmonthsel['10']}>{$lang->october}</option>\n";
	$startdatemonth .= "<option value=\"11\" {$startmonthsel['11']}>{$lang->november}</option>\n";
	$enddatemonth .= "<option value=\"11\" {$endmonthsel['11']}>{$lang->november}</option>\n";
	$startdatemonth .= "<option value=\"12\" {$startmonthsel['12']}>{$lang->december}</option>\n";
	$enddatemonth .= "<option value=\"12\" {$endmonthsel['12']}>{$lang->december}</option>\n";
	
	if($mybb->input['starttime_year'])
	{
		$startdateyear = intval($mybb->input['starttime_year']);
	}
	else
	{
		$startdateyear = gmdate("Y", TIME_NOW);
	}
	
	if($mybb->input['endtime_year'])
	{
		$enddateyear = intval($mybb->input['endtime_year']);
	}
	else
	{
		$enddateyear = gmdate("Y", TIME_NOW) + 1;
	}
	
	$form_container = new FormContainer($lang->add_an_announcement);
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->start_date." <em>*</em>", $lang->start_date_desc, "<select name=\"starttime_day\">\n{$startdateday}</select>\n &nbsp; \n<select name=\"starttime_month\">\n{$startdatemonth}</select>\n &nbsp; \n<input type=\"text\" name=\"starttime_year\" value=\"{$startdateyear}\" size=\"4\" maxlength=\"4\" />\n - {$lang->time} ".$form->generate_text_box('starttime_time', $mybb->input['starttime_time'], array('id' => 'starttime_time', 'style' => 'width: 50px;')));

	$actions = "<script type=\"text/javascript\">
    function checkAction(id)
    {
        var checked = '';
        
        $$('.'+id+'s_check').each(function(e)
        {
            if(e.checked == true)
            {
                checked = e.value;
            }
        });
        $$('.'+id+'s').each(function(e)
        {
        	Element.hide(e);
        });
        if($(id+'_'+checked))
        {
            Element.show(id+'_'+checked);
        }
    }    
</script>
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"endtime_type\" value=\"1\" {$endtime_checked[1]} class=\"endtimes_check\" onclick=\"checkAction('endtime');\" style=\"vertical-align: middle;\" /> <strong>{$lang->set_time}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"endtime_1\" class=\"endtimes\">
			<table cellpadding=\"4\">
				<tr>
					<td><select name=\"endtime_day\">\n{$enddateday}</select>\n &nbsp; \n<select name=\"endtime_month\">\n{$enddatemonth}</select>\n &nbsp; \n<input type=\"text\" name=\"endtime_year\" value=\"{$enddateyear}\" class=\"text_input\" size=\"4\" maxlength=\"4\" />\n - {$lang->time} ".$form->generate_text_box('endtime_time', $mybb->input['endtime_time'], array('id' => 'endtime_time', 'style' => 'width: 50px;'))."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"endtime_type\" value=\"2\" {$endtime_checked[2]} class=\"endtimes_check\" onclick=\"checkAction('endtime');\" style=\"vertical-align: middle;\" /> <strong>{$lang->never}</strong></label></dt>
	</dl>
	<script type=\"text/javascript\">
	checkAction('endtime');
	</script>";
	$form_container->output_row($lang->end_date." <em>*</em>", $lang->end_date_desc, $actions);

	$form_container->output_row($lang->message." <em>*</em>", "", $form->generate_text_area('message', $mybb->input['message'], array('id' => 'message')), 'message');
	
	$form_container->output_row($lang->forums_to_appear_in." <em>*</em>", $lang->forums_to_appear_in_desc, $form->generate_forum_select('fid', $mybb->input['fid'], array('size' => 5, 'main_option' => $lang->all_forums)));

	$form_container->output_row($lang->allow_html." <em>*</em>", "", $form->generate_yes_no_radio('allowhtml', $mybb->input['allowhtml'], array('style' => 'width: 2em;')));

	$form_container->output_row($lang->allow_mycode." <em>*</em>", "", $form->generate_yes_no_radio('allowmycode', $mybb->input['allowmycode'], array('style' => 'width: 2em;')));
	
	$form_container->output_row($lang->allow_smilies." <em>*</em>", "", $form->generate_yes_no_radio('allowsmilies', $mybb->input['allowsmilies'], array('style' => 'width: 2em;')));

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_announcement);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "edit")
{
	$plugins->run_hooks("admin_forum_announcements_edit");
	
	if(!trim($mybb->input['aid']))
	{
		flash_message($lang->error_invalid_announcement, 'error');
		admin_redirect("index.php?module=forum-announcements");
	}
			
	if($mybb->request_method == "post")
	{		
		if(!trim($mybb->input['title']))
		{
			$errors[] = $lang->error_missing_title;
		}
		
		if(!trim($mybb->input['message']))
		{
			$errors[] = $lang->error_missing_message;
		}
		
		if(!trim($mybb->input['fid']))
		{
			$errors[] = $lang->error_missing_forum;
		}
		
		if(!$errors)
		{
			$startdate = @explode(" ", $mybb->input['starttime_time']);
			$startdate = @explode(":", $startdate[0]);
			$enddate = @explode(" ", $mybb->input['endtime_time']);
			$enddate = @explode(":", $enddate[0]);
		
			if(stristr($mybb->input['starttime_time'], "pm"))
			{
				$startdate[0] = 12+$startdate[0];
				if($startdate[0] >= 24)
				{
					$startdate[0] = "00";
				}
			}
			
			if(stristr($mybb->input['endtime_time'], "pm"))
			{
				$enddate[0] = 12+$enddate[0];
				if($enddate[0] >= 24)
				{
					$enddate[0] = "00";
				}
			}
			
			$months = array('01', '02', '03', '04', '05', '06', '07', '08', '09', '10', '11', '12');			
			if(!in_array($mybb->input['starttime_month'], $months))
			{
				$mybb->input['starttime_month'] = 1;
			}
			
			$startdate = gmmktime(intval($startdate[0]), intval($startdate[1]), 0, (int)$mybb->input['starttime_month'], intval($mybb->input['starttime_day']), intval($mybb->input['starttime_year']));
			
			if($mybb->input['endtime_type'] == "2")
			{
				$enddate = '0';
			}
			else
			{
				if(!in_array($mybb->input['endtime_month'], $months))
				{
					$mybb->input['endtime_month'] = 1;
				}
				$enddate = gmmktime(intval($enddate[0]), intval($enddate[1]), 0, (int)$mybb->input['endtime_month'], intval($mybb->input['endtime_day']), intval($mybb->input['endtime_year']));
			}
			
			$update_announcement = array(
				"fid" => $mybb->input['fid'],
				"subject" => $db->escape_string($mybb->input['title']),
				"message" => $db->escape_string($mybb->input['message']),
				"startdate" => $startdate,
				"enddate" => $enddate,
				"allowhtml" => $db->escape_string($mybb->input['allowhtml']),
				"allowmycode" => $db->escape_string($mybb->input['allowmycode']),
				"allowsmilies" => $db->escape_string($mybb->input['allowsmilies']),
			);
	
			$db->update_query("announcements", $update_announcement, "aid='{$mybb->input['aid']}'");
			
			$plugins->run_hooks("admin_forum_announcements_edit_commit");
	
			// Log admin action
			log_admin_action($mybb->input['aid'], $mybb->input['title']);
			$cache->update_forumsdisplay();
	
			flash_message($lang->success_updated_announcement, 'success');
			admin_redirect("index.php?module=forum-announcements");
		}
	}
	
	$page->add_breadcrumb_item($lang->update_an_announcement);
	$page->output_header($lang->update_an_announcement);
	$page->output_nav_tabs($sub_tabs, "update_announcement");

	$form = new Form("index.php?module=forum-announcements&amp;action=edit", "post");
	echo $form->generate_hidden_field("aid", $mybb->input['aid']);
	
	if($errors)
	{
		$page->output_inline_error($errors);
	}
	else
	{
		$query = $db->simple_select("announcements", "*", "aid='{$mybb->input['aid']}'");
		$announcement = $db->fetch_array($query);
		
		if(!$announcement)
		{
			flash_message($lang->error_invalid_announcement, 'error');
			admin_redirect("index.php?module=forum-announcements");
		}
		
		$start_time = explode("-", gmdate("g-i-a", $announcement['startdate']));
		$mybb->input['starttime_time'] = $start_time[0].":".$start_time[1]." ".$start_time[2];
		
		$startday = gmdate("j", $announcement['startdate']);
		
		$startmonth = gmdate("m", $announcement['startdate']);
		$startmonthsel[$startmonth] = "selected=\"selected\"";
		
		$startdateyear = gmdate("Y", $announcement['startdate']);
		
		$mybb->input['title'] = $announcement['subject'];
		$mybb->input['message'] = $announcement['message'];
		$mybb->input['allowhtml'] = $announcement['allowhtml'];
		$mybb->input['allowsmilies'] = $announcement['allowsmilies'];
		$mybb->input['allowmycode'] = $announcement['allowmycode'];
		$mybb->input['fid'] = $announcement['fid'];
		
		if($announcement['enddate'])
		{
			$endtime_checked[1] = "checked=\"checked\"";
			$endtime_checked[2] = "";
			
			$end_time = explode("-", gmdate("g-i-a", $announcement['enddate']));
			$mybb->input['endtime_time'] = $end_time[0].":".$end_time[1]." ".$end_time[2];
			
			$endday = gmdate("j", $announcement['enddate']);
			
			$endmonth = gmdate("m", $announcement['enddate']);
			$endmonthsel[$endmonth] = "selected";
			
			$enddateyear = gmdate("Y", $announcement['enddate']);
		}
		else
		{		
			$endtime_checked[1] = "";
			$endtime_checked[2] = "checked=\"checked\"";
			
			$mybb->input['endtime_time'] = $mybb->input['starttime_time'];
			$endday = $startday;
			$endmonth = $startmonth;
			$enddateyear = $startdateyear+1;
		}
	}
	
	for($i = 1; $i <= 31; ++$i)
	{
		if($startday == $i)
		{
			$startdateday .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$startdateday .= "<option value=\"$i\">$i</option>\n";
		}
		
		if($endday == $i)
		{
			$enddateday .= "<option value=\"$i\" selected=\"selected\">$i</option>\n";
		}
		else
		{
			$enddateday .= "<option value=\"$i\">$i</option>\n";
		}
	}
	
	$startdatemonth .= "<option value=\"01\" {$startmonthsel['01']}>{$lang->january}</option>\n";
	$enddatemonth .= "<option value=\"01\" {$endmonthsel['01']}>{$lang->january}</option>\n";
	$startdatemonth .= "<option value=\"02\" {$startmonthsel['02']}>{$lang->february}</option>\n";
	$enddatemonth .= "<option value=\"02\" {$endmonthsel['02']}>{$lang->february}</option>\n";
	$startdatemonth .= "<option value=\"03\" {$startmonthsel['03']}>{$lang->march}</option>\n";
	$enddatemonth .= "<option value=\"03\" {$endmonthsel['03']}>{$lang->march}</option>\n";
	$startdatemonth .= "<option value=\"04\" {$startmonthsel['04']}>{$lang->april}</option>\n";
	$enddatemonth .= "<option value=\"04\" {$endmonthsel['04']}>{$lang->april}</option>\n";
	$startdatemonth .= "<option value=\"05\" {$startmonthsel['05']}>{$lang->may}</option>\n";
	$enddatemonth .= "<option value=\"05\" {$endmonthsel['05']}>{$lang->may}</option>\n";
	$startdatemonth .= "<option value=\"06\" {$startmonthsel['06']}>{$lang->june}</option>\n";
	$enddatemonth .= "<option value=\"06\" {$endmonthsel['06']}>{$lang->june}</option>\n";
	$startdatemonth .= "<option value=\"07\" {$startmonthsel['07']}>{$lang->july}</option>\n";
	$enddatemonth .= "<option value=\"07\" {$endmonthsel['07']}>{$lang->july}</option>\n";
	$startdatemonth .= "<option value=\"08\" {$startmonthsel['08']}>{$lang->august}</option>\n";
	$enddatemonth .= "<option value=\"08\" {$endmonthsel['08']}>{$lang->august}</option>\n";
	$startdatemonth .= "<option value=\"09\" {$startmonthsel['09']}>{$lang->september}</option>\n";
	$enddatemonth .= "<option value=\"09\" {$endmonthsel['09']}>{$lang->september}</option>\n";
	$startdatemonth .= "<option value=\"10\" {$startmonthsel['10']}>{$lang->october}</option>\n";
	$enddatemonth .= "<option value=\"10\" {$endmonthsel['10']}>{$lang->october}</option>\n";
	$startdatemonth .= "<option value=\"11\" {$startmonthsel['11']}>{$lang->november}</option>\n";
	$enddatemonth .= "<option value=\"11\" {$endmonthsel['11']}>{$lang->november}</option>\n";
	$startdatemonth .= "<option value=\"12\" {$startmonthsel['12']}>{$lang->december}</option>\n";
	$enddatemonth .= "<option value=\"12\" {$endmonthsel['12']}>{$lang->december}</option>\n";
	
	$form_container = new FormContainer($lang->add_an_announcement);
	$form_container->output_row($lang->title." <em>*</em>", "", $form->generate_text_box('title', $mybb->input['title'], array('id' => 'title')), 'title');
	$form_container->output_row($lang->start_date." <em>*</em>", $lang->start_date_desc, "<select name=\"starttime_day\">\n{$startdateday}</select>\n &nbsp; \n<select name=\"starttime_month\">\n{$startdatemonth}</select>\n &nbsp; \n<input type=\"text\" name=\"starttime_year\" value=\"{$startdateyear}\" size=\"4\" maxlength=\"4\" class=\"text_input\" />\n - {$lang->time} ".$form->generate_text_box('starttime_time', $mybb->input['starttime_time'], array('id' => 'starttime_time', 'style' => 'width: 50px;')));

	$actions = "<script type=\"text/javascript\">
    function checkAction(id)
    {
        var checked = '';
        
        $$('.'+id+'s_check').each(function(e)
        {
            if(e.checked == true)
            {
                checked = e.value;
            }
        });
        $$('.'+id+'s').each(function(e)
        {
        	Element.hide(e);
        });
        if($(id+'_'+checked))
        {
            Element.show(id+'_'+checked);
        }
    }    
</script>
	<dl style=\"margin-top: 0; margin-bottom: 0; width: 100%;\">
	<dt><label style=\"display: block;\"><input type=\"radio\" name=\"endtime_type\" value=\"1\" {$endtime_checked[1]} class=\"endtimes_check\" onclick=\"checkAction('endtime');\" style=\"vertical-align: middle;\" /> <strong>{$lang->set_time}</strong></label></dt>
		<dd style=\"margin-top: 4px;\" id=\"endtime_1\" class=\"endtimes\">
			<table cellpadding=\"4\">
				<tr>
					<td><select name=\"endtime_day\">\n{$enddateday}</select>\n &nbsp; \n<select name=\"endtime_month\">\n{$enddatemonth}</select>\n &nbsp; \n<input type=\"text\" name=\"endtime_year\" value=\"{$enddateyear}\" size=\"4\" maxlength=\"4\" />\n - {$lang->time} ".$form->generate_text_box('endtime_time', $mybb->input['endtime_time'], array('id' => 'endtime_time', 'style' => 'width: 50px;'))."</td>
				</tr>
			</table>
		</dd>
		<dt><label style=\"display: block;\"><input type=\"radio\" name=\"endtime_type\" value=\"2\" {$endtime_checked[2]} class=\"endtimes_check\" onclick=\"checkAction('endtime');\" style=\"vertical-align: middle;\" /> <strong>{$lang->never}</strong></label></dt>
	</dl>
	<script type=\"text/javascript\">
	checkAction('endtime');
	</script>";
	$form_container->output_row($lang->end_date." <em>*</em>", $lang->end_date_desc, $actions);

	$form_container->output_row($lang->message." <em>*</em>", "", $form->generate_text_area('message', $mybb->input['message'], array('id' => 'message')), 'message');
	
	$form_container->output_row($lang->forums_to_appear_in." <em>*</em>", $lang->forums_to_appear_in_desc, $form->generate_forum_select('fid', $mybb->input['fid'], array('size' => 5, 'main_option' => $lang->all_forums)));

	$form_container->output_row($lang->allow_html." <em>*</em>", "", $form->generate_yes_no_radio('allowhtml', $mybb->input['allowhtml'], array('style' => 'width: 2em;')));

	$form_container->output_row($lang->allow_mycode." <em>*</em>", "", $form->generate_yes_no_radio('allowmycode', $mybb->input['allowmycode'], array('style' => 'width: 2em;')));
	
	$form_container->output_row($lang->allow_smilies." <em>*</em>", "", $form->generate_yes_no_radio('allowsmilies', $mybb->input['allowsmilies'], array('style' => 'width: 2em;')));

	$form_container->end();

	$buttons[] = $form->generate_submit_button($lang->save_announcement);
	$form->output_submit_wrapper($buttons);
	$form->end();

	$page->output_footer();
}

if($mybb->input['action'] == "delete")
{
	$plugins->run_hooks("admin_forum_announcements_delete");
	
	$query = $db->simple_select("announcements", "*", "aid='{$mybb->input['aid']}'");
	$announcement = $db->fetch_array($query);
	
	// Does the announcement not exist?
	if(!$announcement['aid'])
	{
		flash_message($lang->error_invalid_announcement, 'error');
		admin_redirect("index.php?module=forum-announcements");
	}

	// User clicked no
	if($mybb->input['no'])
	{
		admin_redirect("index.php?module=forum-announcements");
	}

	if($mybb->request_method == "post")
	{
		$db->delete_query("announcements", "aid='{$announcement['aid']}'");
		
		$plugins->run_hooks("admin_forum_announcements_delete_commit");
		
		// Log admin action
		log_admin_action($announcement['aid'], $announcement['title']);
		$cache->update_forumsdisplay();

		flash_message($lang->success_announcement_deleted, 'success');
		admin_redirect("index.php?module=forum-announcements");
	}
	else
	{
		$page->output_confirm_action("index.php?module=forum-announcements&amp;action=delete&amp;aid={$announcement['aid']}", $lang->confirm_announcement_deletion);
	}
}

if(!$mybb->input['action'])
{
	$plugins->run_hooks("admin_forum_announcements_start");
	
	$page->add_breadcrumb_item($lang->forum_announcements, "index.php?module=forum-announcements");
	
	$page->output_header($lang->forum_announcements);
	
	$page->output_nav_tabs($sub_tabs, "forum_announcements");

	// Fetch announcements into their proper arrays
	$query = $db->simple_select("announcements", "aid, fid, subject, enddate");
	while($announcement = $db->fetch_array($query))
	{
		if($announcement['fid'] == -1)
		{			
			$global_announcements[$announcement['aid']] = $announcement;
			continue;
		}
		$announcements[$announcement['fid']][$announcement['aid']] = $announcement;
	}
	
	if($global_announcements)
	{
		$table = new Table;
		$table->construct_header($lang->announcement);
		$table->construct_header($lang->controls, array("class" => "align_center", "colspan" => 2, "width" => 150));
		
		// Get the global announcements
		foreach($global_announcements as $aid => $announcement)
		{
			if($announcement['enddate'] < TIME_NOW && $announcement['enddate'] != 0)
			{
				$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_off.gif\" alt=\"(Expired)\" title=\"Expired Announcement\"  style=\"vertical-align: middle;\" /> ";
			}
			else
			{
				$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_on.gif\" alt=\"(Active)\" title=\"Active Announcement\"  style=\"vertical-align: middle;\" /> ";
			}
			
			$table->construct_cell($icon."<a href=\"index.php?module=forum-announcements&amp;action=edit&amp;aid={$aid}\">".htmlspecialchars_uni($announcement['subject'])."</a>");
			$table->construct_cell("<a href=\"index.php?module=forum-announcements&amp;action=edit&amp;aid={$aid}\">{$lang->edit}</a>", array("class" => "align_center", "width" => 75));
			$table->construct_cell("<a href=\"index.php?module=forum-announcements&amp;action=delete&amp;aid={$aid}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_announcement_deletion}')\">{$lang->delete}</a>", array("class" => "align_center", "width" => 75));
			$table->construct_row();
		}
		$table->output($lang->global_announcements);
	}
	
	
	$table = new Table;
	$table->construct_header($lang->announcement);
	$table->construct_header($lang->controls, array("class" => "align_center", "colspan" => 2, "width" => 200));
	
	fetch_forum_announcements($table);
	
	if($table->num_rows() == 0)
	{
		$table->construct_cell($lang->no_forums, array("colspan" => "3"));
		$table->construct_row();
	}
	
	$table->output($lang->forum_announcements);

	$page->output_footer();
}

function fetch_forum_announcements(&$table, $pid=0, $depth=1)
{
	global $mybb, $db, $lang, $announcements, $page;
	static $forums_by_parent;

	if(!is_array($forums_by_parent))
	{
		$forum_cache = cache_forums();

		foreach($forum_cache as $forum)
		{
			$forums_by_parent[$forum['pid']][$forum['disporder']][$forum['fid']] = $forum;
		}
	}

	if(!is_array($forums_by_parent[$pid]))
	{
		return;
	}

	foreach($forums_by_parent[$pid] as $children)
	{
		foreach($children as $forum)
		{
			$forum['name'] = htmlspecialchars_uni($forum['name']);
			if($forum['active'] == 0)
			{
				$forum['name'] = "<em>".$forum['name']."</em>";
			}
			
			if($forum['type'] == "c")
			{
				$forum['name'] = "<strong>".$forum['name']."</strong>";
			}
				
			$table->construct_cell("<div style=\"padding-left: ".(40*($depth-1))."px;\">{$forum['name']}</div>");
			$table->construct_cell("<a href=\"index.php?module=forum-announcements&amp;action=add&amp;fid={$forum['fid']}\">{$lang->add_announcement}</a>", array("class" => "align_center", "colspan" => 2));
			$table->construct_row();
				
			if($announcements[$forum['fid']])
			{
				foreach($announcements[$forum['fid']] as $aid => $announcement)
				{
					if($announcement['enddate'] < TIME_NOW && $announcement['enddate'] != 0)
					{
						$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_off.gif\" alt=\"(Expired)\" title=\"Expired Announcement\"  style=\"vertical-align: middle;\" /> ";
					}
					else
					{
						$icon = "<img src=\"styles/{$page->style}/images/icons/bullet_on.gif\" alt=\"(Active)\" title=\"Active Announcement\"  style=\"vertical-align: middle;\" /> ";
					}
							
					$table->construct_cell("<div style=\"padding-left: ".(40*$depth)."px;\">{$icon}<a href=\"index.php?module=forum-announcements&amp;action=edit&amp;aid={$aid}\">".htmlspecialchars_uni($announcement['subject'])."</a></div>");
					$table->construct_cell("<a href=\"index.php?module=forum-announcements&amp;action=edit&amp;aid={$aid}\">{$lang->edit}</a>", array("class" => "align_center"));
					$table->construct_cell("<a href=\"index.php?module=forum-announcements&amp;action=delete&amp;aid={$aid}&amp;my_post_key={$mybb->post_code}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->confirm_announcement_deletion}')\">{$lang->delete}</a>", array("class" => "align_center"));
					$table->construct_row();
				}
			}

			// Build the list for any sub forums of this forum
			if($forums_by_parent[$forum['fid']])
			{
				fetch_forum_announcements($table, $forum['fid'], $depth+1);
			}
		}
	}
}

?>