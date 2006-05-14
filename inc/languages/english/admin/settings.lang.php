<?php
/**
 * MyBB 1.2 English Language Pack
 * Copyright © 2006 MyBB Group, All Rights Reserved
 * 
 * $Id$
 */

$l['nav_settings'] = "Board Settings";
$l['nav_add'] = "Add New Setting or Setting Group";
$l['nav_edit'] = "Edit Setting";
$l['nav_delete'] = "Delete Setting";
$l['nav_modify'] = "Modify Settings and Groups";

$l['add_new_setting'] = "Add New Setting";
$l['manage_settings'] = "Manage Settings";
$l['show_all_settings'] = "Show All Settings";

$l['board_settings'] = "Board Settings";
$l['settings_count'] = "{1} Settings";
$l['setting_count'] = "1 Setting";
$l['sections'] = "Sections";
$l['options'] = "Options";
$l['modify_settings'] = "Modify Settings";
$l['edit_setting_group'] = "Edit Setting Group";
$l['add_setting'] = "Add Setting";
$l['delete_setting_group'] = "Delete Setting Group";
$l['change'] = "Change";
$l['submit_changes'] = "Submit Changes";

$l['settings_management'] = "Settings Management";
$l['select_edit_delete'] = "Please select a setting or group from the list below to edit or delete<br><br><b>Note:</b> Please submit the form at the bottom to update the orders.";
$l['disp_order'] = "Display Order:";
$l['disp_order_list'] = "Display Order:";
$l['update_orders'] = "Update Orders";
$l['edit'] = "edit";
$l['delete'] = "delete";
$l['setting_group_orders_updated'] = "The setting and setting group orders have successfully been updated";

$l['add_group'] = "Add Setting Group";
$l['modify_group'] = "Modify Setting Group";
$l['update_group'] = "Update Group";
$l['group_name'] = "Group Name<br /><small>This is the unique identifying string for this group, which is also used for language variables.</small>";
$l['group_title'] = "Group Title<br /><small>This is the friendly title to show for this group if a custom language variable does not exist.</small>";
$l['add_setting'] = "Add Setting";
$l['modify_setting'] = "Modify Setting";
$l['setting_title'] = "Setting Title";
$l['description'] = "Description";
$l['setting_name'] = "Setting Name<br /><small>This will be the name of the setting as used in scripts and templates</small>";
$l['setting_type'] = "Setting Type";
$l['value'] = "Value";
$l['is_default'] = "Is Default Setting?";
$l['group'] = "Group";

$l['delete_setting'] = "Delete Setting";
$l['delete_setting_confirm'] = "Are you sure you want to delete the selected setting?";
$l['delete_group'] = "Delete Setting Group";
$l['delete_group_confirm'] = "Are you sure you want to delete the selected setting group?";

$l['settings_updated'] = "The settings have been successfully updated.";
$l['setting_added'] = "The setting has successfully been added.";
$l['group_added'] = "The setting group has successfully been added.";
$l['setting_deleted'] = "The setting has successfully been deleted.";
$l['group_deleted'] = "The setting group has successfully been deleted.";
$l['setting_edited'] = "The setting has successfully been modified.";
$l['group_edited'] = "The setting group has successfully been edited.";
$l['group_exists'] = "A setting group with that name already exists. Please choose another one.";

/**
 * Translation instructions for settings and setting groups:
 *
 * Groups:
 *          * Obtain the group name (not title) from the edit group page.
 *          * Add language variables in the following format:
 *                $l['setting_group_{name}'] = "Group Name Here";
 *                $l['setting_group_{name}_desc'] = "Group Description";
 *
 *            Ex:
 *                $l['setting_group_general'] = "General Settings";
 *                $l['setting_group_general_desc'] = "Description of general settings here";
 *
 * Settings:
 *          * Obtain the setting name from the edit seting page.
 *          * Add language variables in the following format:
 *                $l['setting_{name}'] = "Setting Name Here";
 *                $l['setting_{name}_desc'] = "Setting Description";
 *
 *            Ex:
 *                $l['setting_bbname'] = "Board Name";
 *                $l['setting_bbname_desc'] = "Description for board name here";
 *
 */
?>