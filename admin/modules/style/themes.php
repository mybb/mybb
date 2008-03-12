<?php
/**
 * MyBB 1.2
 * Copyright © 2007 MyBB Group, All Rights Reserved
 *
 * Website: http://www.mybboard.net
 * License: http://www.mybboard.net/about/license
 *
 * $Id: index.php 2992 2007-04-05 14:43:48Z chris $
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
        die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

require_once MYBB_ADMIN_DIR."inc/functions_themes.php";
require_once MYBB_ROOT."inc/functions_upload.php";


$page->add_breadcrumb_item("Themes", "index.php?".SID."&amp;module=style/themes");

if($mybb->input['action'] == "add" || $mybb->input['action'] == "import" || !$mybb->input['action'])
{
        $sub_tabs['themes'] = array(
                'title' => "Themes",
                'link' => "index.php?".SID."&amp;module=style/themes"
        );

        $sub_tabs['create_theme'] = array(
                'title' => "Create New Theme",
                'link' => "index.php?".SID."&amp;module=stylethemes&amp;action=add"
        );

        $sub_tabs['import_theme'] = array(
                'title' => "Import a User",
                'link' => "index.php?".SID."&amp;module=style/themes&amp;action=import"
        );
}

if($mybb->input['action'] == "import")
{
        if($mybb->request_method == "post")
        {
        }
}

if($mybb->input['action'] == "export")
{
        $query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
        $theme = $db->fetch_array($query);

        // Does the theme not exist?
        if(!$theme['tid'])
        {
                flash_message("You have selected an invalid theme.", 'error');
                admin_redirect("index.php?".SID."&module=style/themes");
        }

        if($mybb->request_method == "post")
        {
        }
}

if($mybb->input['action'] == "add")
{
        if($mybb->request_method == "post")
        {
        }
}

if($mybb->input['action'] == "delete")
{
        $query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
        $theme = $db->fetch_array($query);

        // Does the theme not exist?
        if(!$theme['tid'])
        {
                flash_message("You have selected an invalid theme.", 'error');
                admin_redirect("index.php?".SID."&module=style/themes");
        }

        // User clicked no
        if($mybb->input['no'])
        {
                admin_redirect("index.php?".SID."&module=styles/themes");
        }

        if($mybb->request_method == "post")
        {
                // Do delete

                // Delete from themes

                // Delete from stylesheets

                // Delete any references to inherited stylesheets
        }

        // Are any other themes relying on stylesheets from this theme? Get a list
}

if($mybb->input['action'] == "edit")
{
        $query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
        $theme = $db->fetch_array($query);

        // Does the theme not exist?
        if(!$theme['tid'])
        {
                flash_message("You have selected an invalid theme.", 'error');
                admin_redirect("index.php?".SID."&module=style/themes");
        }

        if($mybb->request_method == "post")
        {
        }

        // Show the edit properties table

        // Fetch list of all of the stylesheets for this theme
        $file_stylesheets = unserialize($theme['stylesheets']);

        $stylesheets = array();
        $inherited_load = array();

        // Now we loop through the list of stylesheets for each file
        foreach($file_stylesheets as $file => $action_stylesheet)
        {
                if($file == 'inherited') continue;
                foreach($action_stylesheets as $action => $style)
                {
                        foreach($style as $stylesheet)
                        {
                                $stylesheets[$stylesheet]['applied_to'][$file][] = $action;
                                if(is_array($file_stylesheets['inherited'][$file."_".$action]) && in_array($stylesheet, array_keys($file_stylesheets['inherited'][$file."_".$action])))
                                {
                                        $stylesheets[$stylesheet]['inherited'] = $file_stylesheets['inherited'][$file."_".$action];
                                        $inherited_load[] = $file_stylesheets['inherited'][$file."_".$action];
                                }
                        }
                }
        }

        if(count($inherited_load) > 0)
        {
                $query = $db->simple_select("themes", "tid, name", "tid IN (".implode(",", $inherited_load).")");
                while($inherited_theme = $db->fetch_array($query))
                {
                        $inherited_themes[$inherited_theme['tid']] = $inherited_theme['name'];
                }
        }

        print_r($stylesheets);

        // Show the stylesheets list
        /*
         | Stylesheet                                  | Controls |
         | general.css (Inherited from master)         | Properties Edit Delete |
         |  Attached to all pages                      |                        |
         +----------------------------------------------------------------------+
        */
}

if($mybb->input['action'] == "stylesheet_properties")
{
        $query = $db->simple_select("themestylesheets", "*", "sid='".intval($mybb->input['sid'])."'");
        $stylesheet = $db->fetch_array($query);

        // Does the theme not exist?
        if(!$stylesheet['sid'])
        {
                flash_message("You have selected an invalid stylesheet.", 'error');
                admin_redirect("index.php?".SID."&module=style/themes");
        }

        // Fetch the theme we want to edit this stylesheet in
        $query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
        $theme = $db->fetch_array($query);

        if($mybb->request_method == "post")
        {
                // Inheriting?

                        // Break inherit relationship
                        // Copy stylesheet to this theme
                        // Remove inherited stylesheet
        }

        // If the stylesheet and theme do not match, we must be editing something that is inherited
        if($theme['tid'] != $stylesheet['tid'])
        {
                $query = $db->simple_select("themes", "name", "tid='{$stylesheet['tid']}'");
                $stylesheet_parent = $db->fetch_field($query, 'name');

                // Show inherited warning
                echo "Warning: This stylesheet is currently being inherited from .... Any changes you make will break the inheritance and this stylesheet will be copied locally to this theme (name).</p><p>Edit this stylesheet in (name) to maintain the inheritance.</p>";
        }

        $files = explode("|", $stylesheet['attachedto']);
}

// Shows the page where you can actually edit a particular selector or the whole stylesheet
if($mybb->input['action'] == "edit_stylesheet")
{
        $query = $db->simple_select("themestylesheets", "*", "sid='".intval($mybb->input['sid'])."'");
        $stylesheet = $db->fetch_array($query);

        // Does the theme not exist?
        if(!$stylesheet['sid'])
        {
                flash_message("You have selected an invalid stylesheet.", 'error');
                admin_redirect("index.php?".SID."&module=style/themes");
        }

        // Fetch the theme we want to edit this stylesheet in
        $query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
        $theme = $db->fetch_array($query);

        if($mybb->request_method == "post")
        {
                $sid = $theme['sid'];

                // Theme & stylesheet theme ID do not match, editing inherited - we copy to local theme
                if($theme['tid'] != $stylesheet['tid'])
                {
                        $sid = copy_stylesheet_to_theme($stylesheet, $theme['id']);
                }

                // Insert the modified CSS
                $new_stylesheet = $stylesheet['stylesheet'];

                foreach($mybb->input['css_bits'] as $class_id => $properties)
                {
                        $selector = $properties['selector'];
                        $css_to_insert = '';
                        foreach($properties['values'] as $field => $value)
                        {
                                if($field == "extra")
                                {
                                        $css_to_insert .= $value."\n";
                                }
                                else
                                {
                                        $css_to_insert .= "{$field}: {$value}\n";
                                }
                        }
                        $new_stylesheet = insert_into_css($css_to_insert, $class_id, $new_stylesheet, $selector);
                }

                // Now we have the new stylesheet, save it
                $updated_stylesheet = array(
                        "stylesheet" => $db->escape_string($new_stylesheet),
                        "lastmodified" => TIME_NOW
                );
                $db->update_query("themestylesheets", $updated_stylesheet, "sid='{$sid}'");

                // Cache the stylesheet to the file
                cache_stylesheet($theme['tid'], $stylesheet['name'], $new_stylesheet);

                // Update the CSS file list for this theme
                update_theme_stylesheet_list($theme['tid']);

                // Log admin action
                log_admin_action($theme['name'], $stylesheet['name']);

                // AJAX requests don't get flash messages or redirects
                if(!$mybb->input['ajax'])
                {
                        flash_message("The stylesheet has successfully been updated.", 'success');
                        admin_redirect("index.php?".SID."&module=style/themes?action=edit_stylesheet&action=edit&tid={$theme['tid']}");
                }
                // They get fancy responses!
                else
                {
                        header("Content-type: text/javascript");
                        echo "alert('saved');\n";
                        exit;
                }
        }

        // If the stylesheet and theme do not match, we must be editing something that is inherited
        if($theme['tid'] != $stylesheet['tid'])
        {
                echo "Warning: This stylesheet is currently being inherited from .... Any changes you make will break the inheritance and this stylesheet will be copied locally to this theme (name).</p><p>Edit this stylesheet in (name) to maintain the inheritance.</p>";

        }

        // Has the file on the file system been modified?
        if(resync_stylesheet($stylesheet))
        {
                // Need to refetch new stylesheet as it was modified
                $query = $db->simple_select("themestylesheets", "stylesheet", "sid='{$stylesheet['sid']}'");
                $stylesheet['stylesheet'] = $db->fetch_field($query, 'stylesheet');
        }

        $selector_list = get_selectors_as_options($stylesheet['stylesheet'], $mybb->input['selector']);

        // Haven't chosen a selector to edit, show the first one from the stylesheet
        if(!$mybb->input['selector'])
        {
                $editable_selector = array($first);
        }
        // Show a specific selector
        else if($mybb->input['selector'] != -1)
        {
                $editable_selector = array($mybb->input['selector']);
        }
        // Showing all in this stylesheet
        else if($mybb->input['selector'] == 1)
        {
                $editable_selector = array_keys(css_to_array($stylesheet['stylesheet']));
        }

        // Output the selection box
        
        // Now show the edit form
        foreach($editable_selector as $id)
        {
                // Get the properties from this item
                $properties = get_css_properties($stylesheet['stylesheet'], $id);

                // Draw the edit form

                // Reset

                // Save Changes

                // Save Changes & Close
        }
}

if($mybb->input['action'] == "delete_stylesheet")
{
        if($mybb->request_method == "post")
        {
        }
}

if($mybb->input['action'] == "add_stylesheet")
{
        if($mybb->request_method == "post")
        {
        }
}

if($mybb->input['action'] == "set_default")
{
        $query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
        $theme = $db->fetch_array($query);

        // Does the theme not exist?
        if(!$theme['tid'])
        {
                flash_message("You have selected an invalid theme.", 'error');
                admin_redirect("index.php?".SID."&module=style/themes");
        }

        $updated_theme = array(
                "def" => 0
        );
        $db->update_query("themes", $updated_theme);
        $updated_theme['def'] = 1;
        $db->update_query("themes", $updated_theme, "tid='".intval($mybb->input['tid'])."'");

        flash_message("The selected theme has now been marked as the default.", 'success');
        admin_redirect("index.php?".SID."&module=style/themes");
}

if($mybb->input['action'] == "force")
{
        $query = $db->simple_select("themes", "*", "tid='".intval($mybb->input['tid'])."'");
        $theme = $db->fetch_array($query);

        // Does the theme not exist?
        if(!$theme['tid'])
        {
                flash_message("You have selected an invalid theme.", 'error');
                admin_redirect("index.php?".SID."&module=style/themes");
        }

        $updated_users = array(
                "style" => $theme['tid']
        );

        flash_message("The selected theme has now been forced as the default to all users.", 'success');
        admin_redirect("index.php?".SID."&module=style/themes");
}

if(!$mybb->input['action'])
{
        $page->output_header("Themes");
        
        $page->output_nav_tabs($sub_tabs, 'themes');

        $table = new Table;
        $table->construct_header("Theme");
        $table->construct_header("# Users", array("class" => "align_center", "width" => 100));
        $table->construct_header($lang->controls, array("class" => "align_center", "width" => 150));

        build_theme_list();

        $table->output("Themes");
        
        $page->output_footer();
}

function build_theme_list($parent=0, $depth=0)
{
        global $mybb, $db, $table, $lang; // Global $table is bad, but it will have to do for now
        static $theme_cache;

        $padding = $depth*20; // Padding

        if(!is_array($theme_cache))
        {
                $themes = cache_themes();
                $query = $db->query("
                        SELECT style, COUNT(uid) AS users
                        FROM ".TABLE_PREFIX."users
                        GROUP BY style
                ");
                while($user_themes = $db->fetch_array($query))
                {
                        $themes[$user_themes['style']]['users'] = intval($user_themes['users']);
                }

                // Restrucure the theme array to something we can "loop-de-loop" with
                foreach($themes as $theme)
                {
                        $theme_cache[$theme['pid']][$theme['tid']] = $theme;
                }
                unset($theme);
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
                        $popup->add_item("Edit Theme", "");
                        $popup->add_item("Delete Theme", "");
                        if($theme['def'] != 1)
                        {
                                $popup->add_item("Set as Default", "");
                                $set_default = "<a href=\"#\"><img src=\"\" title=\"Set as Default\" /></a>";
                        }
                        else
                        {
                                $set_default = "<img src=\"\" title=\"Default Theme\" />";
                        }
                        $popup->add_item("Force on Users", "");
                }
                $popup->add_item("Export Theme", "");
                $table->construct_cell("<div class=\"float_right;\">{$set_default}</div><div style=\"margin-left: {$padding}px\"><strong>{$theme['name']}</strong></div>");
                $table->construct_cell(my_number_format($theme['users']), array("class" => "align_center"));
                $table->construct_cell($popup->fetch(), array("class" => "align_center"));
                $table->construct_row();

                // Fetch & build any child themes
                build_theme_list($theme['tid'], ++$depth);
        }
}

function cache_themes()
{
        global $db, $theme_cache;

        $query = $db->simple_select("themes", "*", "", array('order_by' => "pid, name"));
        while($theme = $db->fetch_array($query))
        {
                $theme['themebits'] = unserialize($theme['themebits']);
                $theme['cssbits'] = unserialize($theme['cssbits']);
                $theme_cache[$theme['tid']] = $theme;
        }
        
        return $theme_cache;
}
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 3.2//EN">
<html>
<head>
<meta name="generator" content=
"HTML Tidy for Windows (vers 6 November 2007), see www.w3.org">
<title></title>
</head>
<body>
</body>
</html>
