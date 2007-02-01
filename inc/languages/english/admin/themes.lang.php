<?php
/**
 * MyBB 1.2 English Language Pack
 * Copyright © 2007 MyBB Group, All Rights Reserved
 * 
 * $Id$
 */

$l['nav_themes'] = "Theme Manager";
$l['nav_add_theme'] = "New Theme";
$l['nav_edit_theme'] = "Edit Theme";
$l['nav_delete_theme'] = "Delete Theme";
$l['nav_download_theme'] = "Download Themes";
$l['nav_import_theme'] = "Import Themes";

$l['select_edit_delete'] = "Please select a theme from the list below to edit or delete.<br />If you change the default theme, remember to submit your changes below.";
$l['theme'] = "Theme";
$l['users'] = "# Users";
$l['edit'] = "Edit";
$l['delete'] = "Delete";
$l['default'] = "Default";
$l['controls'] = "Controls";
$l['new_theme'] = "Create New Theme";
$l['import_theme'] = "Import Theme";
$l['download_theme'] = "Download Themes";

$l['add_theme'] = "Add Theme";
$l['modify_theme'] = "Modify Theme: {1}";
$l['update_theme'] = "Update Theme";
$l['delete_theme'] = "Delete Theme: {1}";
$l['import_theme'] = "Import Theme";
$l['do_download'] = "Download Theme";
$l['download_theme'] = "Download a Theme";
$l['theme_management'] = "Theme Management";
$l['key_guidelines'] = "Key and Guidelines";
$l['update_default'] = "Update Default Theme";

$l['import_local_file'] = "Import Theme From a Local File";
$l['import_from_pc'] = "Import Theme From Your Computer";
$l['import_remote_url'] = "Import Theme From a Remote URL";

$l['delete_theme_confirm'] = "<div align=\"center\">Are you sure you want to delete the theme titled {1}?<br />All users and forums using this theme will be reverted to the forums default.<br /><br />$yes$no</div>";
$l['error_delete_default'] = "<div align=\"center\">You cannot delete this theme because it is set as the default theme for your forums.<br />Please change the default theme then try deleting this theme again.</di>";

$l['theme_name'] = "Name<br /><small>Enter the name of this theme here.</small>";
$l['template_set'] = "Template Set<br /><small>Select the name of the template set you wish to use for this theme.</small>";
$l['image_dir'] = "Image Directory<br /><small>Where all the images for this theme reside.</small>";
$l['forum_logo'] = "Forum Logo<br /><small>The URL of the image to display as the forum logo in this theme.</small>";
$l['content_table_width'] = "Content Table Width";
$l['table_spacing'] = "Table Spacing";
$l['inner_border_width'] = "Inner Table Border Width";
$l['body'] = "Body";
$l['container'] = "Page Container";
$l['content'] = "Content Container";
$l['top_menu'] = "Top Links Menu";
$l['panel'] = "Welcome Panel";
$l['tables'] = "Tables (Ignored by 'Body')";
$l['tborder'] = "Table Border";
$l['thead'] = "Table Headers";
$l['tcat'] = "Table Sub Headers (Category Backgrounds) ";
$l['trow1'] = "Alternating Table Row 1";
$l['trow2'] = "Alternating Table Row 2";
$l['trow_shaded'] = "Shaded Table Row";
$l['trow_sep'] = "Table Row Separator";
$l['tfoot'] = "Table Footers";
$l['bottom_menu'] = "Bottom Links Menu";
$l['navigation'] = "Navigation Breadcrumb";
$l['active_navigation'] = "Active Breadcrumb Item";
$l['smalltext'] = "Small Text";
$l['largetext'] = "Large Text";
$l['form_elements'] = "Form Elements";
$l['additional_css'] = "Additional CSS";
$l['additional_css_note'] = "Below you may enter additional CSS to be included with this theme.";
$l['master_css_note'] = "Below is the MASTER additional CSS. Updating it will update the master additional CSS applied to all themes.";
$l['local_file_path'] = "Please enter the path of the theme file relative to this directory.";
$l['local_file_name'] = "Local Filename:";
$l['import_custom_templates'] = "Import Custom Templates<br /><small>If this theme file includes custom templates, do you want to import them?</small>";
$l['ignore_version'] = "Ignore Version Compatibility<br /><small>Do you still want to install this style even if it is for a different version of myBB?</small>";
$l['select_import_file'] = "Please select the file from your computer.";
$l['theme_file'] = "File:";
$l['remote_file_url'] = "Please enter the remote url of the theme file.";
$l['remote_url'] = "Remote URL:";

$l['select_download'] = "Please select the theme to download and options below.";
$l['theme_select'] = "Theme<br /><small>Select the name of the theme you wish to download.</small>";
$l['include_templates'] = "Include Templates?<br /><small>If you want to download the templates used in this theme as well, select yes.</small>";
$l['include_custom_only'] = "Include only Customized Items?<br /><small>If you wish to include items (css and theme bits) inherited from parent themes select \"no\", otherwise only customized elements will be exported.</small>";
$l['include_custom_temps_only'] = "Include only Customized Templates?<br /><small>If you wish to download only customized templates in this theme select \"yes\", if you want to export all templates in MyBB including custom ones for this theme select \"no\".</small>";
$l['export_advanced_settings'] = "Do you wish to select advanced export settings for this theme?\n\nClicking OK will take you to the export page, clicking Cancel will automatically include customized theme elements and customized templates used within this theme.";

$l['theme_added'] = "The theme has successfully been added to the database.";
$l['theme_updated'] = "The theme has successfully been updated.";
$l['theme_same_parent'] = "The theme's parent cannot be the theme itself!";
$l['theme_deleted'] = "The theme has successfully been deleted.<br />All users using this style have been reverted to the default style.";
$l['default_updated'] = "The default theme has successfully been changed.";
$l['theme_forced'] = "The theme has successfully been forced to all users.";
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
$l['error_uploadfailed_nocontents'] = "There was no content in the file you uploaded.";
$l['failed_finding_theme'] = "The upload was successful, or the file you specified exists, but does it not contain valid theme information.  Please ensure that it is a valid theme file.";
$l['error_remote_url'] = "Could not connect to the remote url. Please check the url and try again.";
$l['theme_exists'] = "A theme with the same name already exists.";
$l['version_warning'] = "The theme file is not for the current version of myBB you are running ({1}). If you still wish to install it, please change 'Ignore Version Compatibility' to yes.";
$l['theme_imported'] = "The theme, {1} has successfully been imported.";
$l['del_theme'] = "Delete Theme";
$l['edit_theme_settings'] = "Edit Theme Settings";
$l['edit_theme_style'] = "Edit Theme Style";
$l['export_theme'] = "Export Theme";
$l['inherited_from'] = "Inherited From";
$l['customized_this_style'] = "Customized in this style";
$l['mybb_master_style'] = "MyBB Master Style";
$l['theme_options'] = "Theme Options";
$l['set_as_default'] = "Set as Default";
$l['force_on_users'] = "Force Theme on Users";
$l['other_options'] = "Other Options";
$l['theme_style'] = "Theme Style";
$l['theme_users'] = " ({1} Users)";
$l['import_options'] = "Theme Import Options";
$l['import_name'] = "Custom Name<br /><small>If left blank, the name specified in the theme file will be used.</small>";
$l['theme_parent'] = "Parent Theme";
$l['revert_customizations'] = "Revert Customizations";
$l['general_options'] = "General Options";
$l['allowed_groups'] = "Allowed Usergroups<br /><small>Select the usergroups that are able to use this theme.  Selecting 'All Usergroups' overrides any other selection.</small>";
$l['all_groups'] = "All Usergroups";

$l['autocomplete_popup'] = "Auto Complete Popup";
$l['popup_window'] = "Popup Window";
$l['selected_result'] = "Selected Result";
$l['form_elements_button'] = "Buttons";
$l['form_elements_textbox'] = "Text Input Boxes";
$l['form_elements_textarea'] = "Textareas";
$l['form_elements_select'] = "Select Boxes";
$l['form_elements_radio'] = "Radio Buttons";
$l['form_elements_checkbox'] = "Check Boxes";
$l['popup_menus'] = "Popup Menus";
$l['reputation_system'] = "Reputation System";
$l['positive_reputation_count'] = "Positive Reputation Count";
$l['neutral_reputation_count'] = "Neutral Reputation Count";
$l['negative_reputation_count'] = "Negative Reputation Count";
$l['trow_positive_reputation'] = "Positive Reputation Row";
$l['trow_neutral_reputation'] = "Neutral Reputation Row";
$l['trow_negative_reputation'] = "Negative Reputation Row";
$l['main_css_attributes'] = "Main CSS Attributes";
$l['extra_css_attributes'] = "Extra CSS Attributes";
$l['background'] = "Background";
$l['width'] = "Width";
$l['font_color'] = "Font Color";
$l['font_family'] = "Font Family";
$l['font_size'] = "Font Size";
$l['font_style'] = "Font Style";
$l['font_weight'] = "Font Weight";
$l['link_css_attributes'] = "Link CSS Attributes";
$l['normal_links'] = "Normal Links";
$l['visited_links'] = "Visited Links";
$l['hovered_links'] = "Hovered Links";
$l['save_changes'] = "Save Changes";
$l['text_decoration'] = "Text Decoration";
$l['mycode_toolbar'] = "MyCode Formatting Toolbar";
$l['editor'] = "Message Editor";
$l['toolbar_normal'] = "Toolbar Item Normal";
$l['toolbar_hovered'] = "Toolbar Item Hovered";
$l['toolbar_clicked'] = "Toolbar Item Selected";
$l['toolbar_mousedown'] = "Toolbar Item Mouse Down";
$l['border'] = "Border";
$l['editor_control_bar'] = "Editor Control Bar";
$l['popup_menu'] = "Popup Menu";
$l['popup_menu_items'] = "Menu Items";
$l['popup_menu_items_hovered'] = "Hovered Menu Items";
?>