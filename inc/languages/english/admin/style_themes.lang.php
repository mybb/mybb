<?php
/**
 * MyBB 1.4 English Language Pack
 * Copyright © 2007 MyBB Group, All Rights Reserved
 * 
 * $Id$
 */

$l['themes'] = "Themes";
$l['themes_desc'] = "Here you can manage your themes.";

$l['create_new_theme'] = "Create New Theme";
$l['create_new_theme_desc'] = "Here you can create a new theme based on the default. <strong>Template sets, stylesheets, and other settings are inherited from the parent theme.</strong>";

$l['import_a_theme'] = "Import a Theme";
$l['import_a_theme_desc'] = "Here you can import new themes.";

$l['edit_stylesheets'] = "Edit Stylesheets";
$l['edit_stylesheets_desc'] = "Here you can easily manage this theme's stylesheets.";

$l['add_stylesheet'] = "Add Stylesheet";

$l['edit_stylesheet_simple_mode'] = "Edit Stylesheet: Simple Mode";
$l['edit_stylesheet_simple_mode_desc'] = "Here you can easily edit your theme's stylesheet.";
$l['edit_stylesheet_advanced_mode'] = "Edit Stylesheet: Advanced Mode";
$l['edit_stylesheet_advanced_mode_desc'] = "Here you can edit this stylesheet like a flat file.";

$l['theme'] = "Theme";
$l['num_users'] = "# Users";
$l['edit_theme'] = "Edit Theme";
$l['delete_theme'] = "Delete Theme";
$l['set_as_default'] = "Set as Default";
$l['default_theme'] = "Default Theme";
$l['force_on_users'] = "Force on Users";
$l['export_theme'] = "Export Theme";

$l['local_file'] = "Local File";
$l['url'] = "URL";
$l['import_from'] = "Import from";
$l['import_from_desc'] = "Select a file to import. You can either import the theme file from your computer or from a URL.";
$l['parent_theme'] = "Parent Theme";
$l['parent_theme_desc'] = "Select the theme this theme should be a child of.";
$l['new_name'] = "New Name";
$l['new_name_desc'] = "A new name for the imported theme. If left blank, the name specified in the theme file will be used.";
$l['advanced_options'] = "Advanced Options";
$l['advanced_options_desc'] = "A new name for the imported theme. If left blank, the name specified in the theme file will be used.";
$l['ignore_version_compatibility'] = "Ignore Version Compatibility";
$l['ignore_version_compat_desc'] = "Should this theme be installed regardless of the version of MyBB it was created for?";
$l['import_stylesheets'] = "Import Stylesheets";
$l['import_stylesheets_desc'] = "If this theme contains custom stylesheets should they be imported?";
$l['import_templates'] = "Import Templates";
$l['import_templates_desc'] = "If this theme contains custom templates should they be imported?";
$l['import_theme'] = "Import Theme";

$l['create_a_theme'] = "Create a Theme";
$l['name'] = "Name";
$l['name_desc'] = "Specify a name for the new theme.";
$l['parent_theme'] = "Parent Theme";
$l['parent_theme_desc'] = "Select the theme this theme should be a child of.";

$l['background'] = "Background";
$l['extra_css_atribs'] = "Extra CSS Attributes";
$l['color'] = "Color";
$l['width'] = "Width";
$l['font_color'] = "Font Color";
$l['font_family'] = "Font Family";
$l['font_size'] = "Font Size";
$l['font_style'] = "Font Style";
$l['font_weight'] = "Font Weight";

$l['stylesheets'] = "Stylesheets";
$l['inherited_from'] = "Inherited from";
$l['attached_to'] = "Attached to";
$l['actions'] = "actions";
$l['of'] = "of";
$l['attached_to_all_pages'] = "Attached to all pages";
$l['properties'] = "Properties";
$l['edit_style'] = "Edit Style";
$l['stylesheets_in'] = "Stylesheets in";
$l['stylesheet_properties'] = "Stylesheet Properties";
$l['stylesheet_inherited_default'] = "This stylesheet is currently being inherited from {1}. Any changes you make will break the inheritance, and the stylesheet will be copied to this theme.";
$l['stylesheet_inherited'] = "This stylesheet is currently being inherited from {1}. Any changes you make will break the inheritance, and the stylesheet will be copied to this theme. Edit this stylesheet in {1} to keep the inheritance.";
$l['globally'] = "Globally";
$l['specific_files'] = "Specific Files";
$l['specific_actions'] = "Specific actions";
$l['file'] = "File";
$l['add_another'] = "Add another";
$l['edit_stylesheet_properties_for'] = "Edit Stylesheet Properties for";
$l['file_name'] = "File Name";
$l['save_stylesheet_properties'] = "Save Stylesheet Properties";
$l['saved'] = "Saved";
$l['editing'] = "Editing";
$l['selector'] = "Selector";
$l['save_changes'] = "Save Changes";
$l['save_changes_and_close'] = "Save Changes & Close";
$l['save_changes_js'] = "Do you want to save your changes first?";
$l['delete_confirm_js'] = "Are you sure you want to delete this?";

$l['full_stylesheet_for'] = "Full Stylesheet for";

$l['error_invalid_stylesheet'] = "You have selected an invalid stylesheet.";
$l['error_invalid_theme'] = "You have selected an invalid theme.";
$l['error_missing_name'] = "Please enter a name for this theme.";
$l['error_missing_url'] = "Please enter a theme a import.";
$l['error_local_file'] = "Could not open the local file. Does it exist? Please check and try again.";
$l['error_uploadfailed'] = "Upload failed. Please try again.";
$l['error_uploadfailed_detail'] = "Error details: ";
$l['error_uploadfailed_php1'] = "PHP returned: Uploaded file exceeded upload_max_filesize directive in php.ini.  Please contact your forum administrator with this error.";
$l['error_uploadfailed_php2'] = "The uploaded file exceeded the maximum filesize specified.";
$l['error_uploadfailed_php3'] = "The uploaded file was only partially uploaded.";
$l['error_uploadfailed_php4'] = "No file was uploaded.";
$l['error_uploadfailed_php6'] = "PHP returned: Missing a temporary folder.  Please contact your forum administrator with this error.";
$l['error_uploadfailed_php7'] = "PHP returned: Failed to write the file to disk.  Please contact your forum administrator with this error.";
$l['error_uploadfailed_phpx'] = "PHP returned error code: {1}.  Please contact your forum administrator with this error.";
$l['error_uploadfailed_lost'] = "The file could not be found on the server.";
$l['error_uploadfailed_nocontents'] = "MyBB could not find the theme with the file you uploaded. Please check the file is the correct and is not corrupt.";
$l['error_invalid_version'] = "This theme has been written for another version of MyBB. Please check the \"Ignore Version Compatibility\" to ignore this error.";
$l['error_missing_stylesheet_name'] = "Please enter a name for this stylesheet.";

$l['success_imported_theme'] = "Successfully imported the theme.";
$l['success_created_theme'] = "Successfully created the theme.";
$l['success_theme_deleted'] = "Successfully deleted the specified theme.";
$l['success_stylesheet_properties_updated'] = "Successfully updated the specified stylesheet's proporties.";
$l['success_stylesheet_updated'] = "The stylesheet has successfully been updated.";
$l['success_stylesheet_deleted'] = "Successfully deleted the selected stylesheet.";
$l['success_theme_set_default'] = "The selected theme has now been marked as the default.";
$l['success_theme_forced'] = "The selected theme has now been forced as the default to all users.";

$l['confirm_theme_deletion'] = "Are you sure you want to delete this theme?";
$l['confirm_stylesheet_deletion'] = "Are you sure you want to delete this stylesheet?";
$l['confirm_theme_forced'] = "Are you sure you want to force this theme on all users?";

?>