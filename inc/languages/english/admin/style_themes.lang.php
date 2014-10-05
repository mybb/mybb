<?php
/**
 * MyBB 1.8 English Language Pack
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 */

$l['themes'] = "Themes";
$l['themes_desc'] = "Here you can manage the themes set up on your forum. Themes allow you to customize the appearance of your forum. A list of the themes currently set up are shown below.";

$l['create_new_theme'] = "Create New Theme";
$l['create_new_theme_desc'] = "Here you can create a new theme based on the default. <strong>Template sets, stylesheets, and other settings are inherited from the parent theme.</strong>";

$l['import_a_theme'] = "Import a Theme";
$l['import_a_theme_desc'] = "Here you can import new themes. You may import a theme from your computer, or a remote URL.";

$l['edit_stylesheets'] = "Edit Stylesheets";
$l['edit_stylesheets_desc'] = "Here you can easily manage the stylesheets in use by this theme. Stylesheets are based on CSS and define the fonts, colors and other visual aspects for this theme. A list of stylesheets attached to this theme is below.";

$l['add_stylesheet'] = "Add Stylesheet";
$l['add_stylesheet_desc'] = "Here you can add a new stylesheet to this theme. A stylesheet contains CSS that allows you to customize the appearance of this theme. You will be taken to the stylesheet edit page following creation.";

$l['browse_themes'] = "Browse Themes";
$l['browse_themes_desc'] = "Here you may browse the official MyBB modifications site for themes compatible with your series of MyBB.";

$l['browse_all_themes'] = "Browse All Themes";

$l['export_theme'] = "Export Theme";
$l['export_theme_desc'] = "Here you can export your themes and customized templates. Exporting themes is useful if you wish to share them with others or import them to another forum.";

$l['duplicate_theme'] = "Duplicate Theme";
$l['duplicate_theme_desc'] = "Here you can duplicate your themes. This helps you if you want to develop another version of it.";

$l['colors_manage'] = "Manage Colors";
$l['colors_attached_to'] = "color setting";
$l['colors_setting'] = "Base Color";
$l['colors_setting_desc'] = "Select the color this theme should use as its base color. Stylesheets attached to this color will be used.";
$l['colors_no_color_setting'] = "There are no colors available. Please create a list of colors below to use this feature.";
$l['colors_add'] = "Manage Colors";
$l['colors_add_desc'] = "A list of colors available for this theme. This should be a list of key paired (key=item) colors, for example, <em>blue=Blue</em>. Separate items with a new line.";
$l['colors_please_select'] = "None";
$l['colors_add_edit_desc'] = "Select a color to attach this stylesheet to. You can select more than one color.";
$l['colors_specific_color'] = "Specific color";

$l['include_custom_only'] = "Include customized items only?";
$l['include_custom_only_desc'] = "If you wish to include items (css and stylesheets) inherited from parent themes select \"no\", otherwise only customized elements will be exported.";
$l['include_templates'] = "Include templates in the export as well?";
$l['include_templates_desc'] = "If you want to export the customized templates used in this theme as well, select yes.";

$l['edit_stylesheet_simple_mode'] = "Edit Stylesheet: Simple Mode";
$l['edit_stylesheet_simple_mode_desc'] = "Here you can easily edit your theme's stylesheet. Simple mode allows you to customize the CSS in this stylesheet with little or no knowledge of CSS. Begin by selecting an item below.";
$l['edit_stylesheet_advanced_mode'] = "Edit Stylesheet: Advanced Mode";
$l['edit_stylesheet_advanced_mode_desc'] = "Here you can edit this stylesheet like a flat file. The contents of the CSS stylesheet is shown in the text area below.";

$l['theme'] = "Theme";
$l['num_users'] = "# Users";
$l['edit_theme'] = "Edit Theme";
$l['delete_theme'] = "Delete Theme";
$l['set_as_default'] = "Set as Default";
$l['default_theme'] = "Default Theme";
$l['force_on_users'] = "Force on Users";
$l['delete_revert'] = "Delete / Revert";

$l['local_file'] = "Local File";
$l['url'] = "URL";
$l['import_from'] = "Import from";
$l['import_from_desc'] = "Select a file to import. You can either import the theme file from your computer or from a URL.";
$l['parent_theme'] = "Parent Theme";
$l['parent_theme_desc'] = "Select the theme this theme should be a child of.";
$l['new_name'] = "New Name";
$l['new_name_desc'] = "A new name for the imported theme. If left blank, the name in the theme file will be used.";
$l['advanced_options'] = "Advanced Options";
$l['ignore_version_compatibility'] = "Ignore Version Compatibility";
$l['ignore_version_compat_desc'] = "Should this theme be installed regardless of the version of MyBB it was created for?";
$l['import_stylesheets'] = "Import Stylesheets";
$l['import_stylesheets_desc'] = "If this theme contains custom stylesheets should they be imported?";
$l['import_templates'] = "Import Templates";
$l['import_templates_desc'] = "If this theme contains custom templates should they be imported?";
$l['import_theme'] = "Import Theme";

$l['new_name_duplicate_desc'] = "A new name for the duplicated theme.";
$l['duplicate_stylesheets'] = "Duplicate Stylesheets";
$l['duplicate_stylesheets_desc'] = "If this theme contains custom stylesheets should they be duplicated?";
$l['duplicate_templates'] = "Duplicate Templates";
$l['duplicate_templates_desc'] = "If this theme contains custom templates should they be duplicated?";

$l['create_a_theme'] = "Create a Theme";
$l['name'] = "Name";
$l['name_desc'] = "Specify a name for the new theme.";
$l['display_order'] = "Order";

$l['edit_theme_properties'] = "Edit Theme Properties";
$l['name_desc_edit'] = "Specify a name for the theme.";
$l['allowed_user_groups'] = "Allowed User Groups";
$l['allowed_user_groups_desc'] = "Specify which user groups are allowed to use this theme. Selecting 'All User Groups' will override any other selection. Hold down the CTRL key to select multiple user groups.";
$l['all_user_groups'] = "All User Groups";
$l['template_set'] = "Template Set";
$l['template_set_desc'] = "Specify the template set the theme should use. The selected template set defines the markup (HTML) used in presenting the theme.";
$l['editor_theme'] = "Editor Style";
$l['editor_theme_desc'] = "Specify the style to be used for the MyCode editor in this theme. Editor styles can be found in the <strong>jscripts/editor_themes</strong> folder.";
$l['img_directory'] = "Image Directory";
$l['img_directory_desc'] = "The root directory for the location of the images used in this theme. Note that this only specifies the directory for the images used in templates, not the stylesheets.";
$l['logo'] = "Board Logo";
$l['logo_desc'] = "Location of the board logo used in this theme (this is the logo that appears at the top of each page).";
$l['table_spacing'] = "Table Spacing";
$l['table_spacing_desc'] = "The width of the inner padding of table cells, in pixels. This is HTML's <em>cellpadding</em> attribute of the <em>table</em> tag.";
$l['inner_border'] = "Inner Table Border Width";
$l['inner_border_desc'] = "The amount of padding between each table cell, in pixels. This is HTML's <em>cellspacing</em> attribute of the <em>table</em> tag.";
$l['save_theme_properties'] = "Save Theme Properties";
$l['save_stylesheet_order'] = "Save Stylesheet Orders";

$l['background'] = "Background";
$l['extra_css_atribs'] = "Extra CSS Attributes";
$l['color'] = "Color";
$l['width'] = "Width";
$l['text_decoration'] = "Text Decoration";
$l['font_family'] = "Font Family";
$l['font_size'] = "Font Size";
$l['font_style'] = "Font Style";
$l['font_weight'] = "Font Weight";

$l['stylesheets'] = "Stylesheets";
$l['inherited_from'] = "Inherited from";
$l['attached_to'] = "Attached to";
$l['attached_to_nothing'] = "Attached to nothing";
$l['attached_to_desc'] = "You can either attach stylesheets globally or to specific files. If you attach it to specific files you can attach it to specific actions within each file.";
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
$l['specific_files'] = "Specific files";
$l['specific_actions'] = "Specific actions";
$l['specific_actions_desc'] = "Actions are separated by commas";
$l['file'] = "File";
$l['add_another'] = "Add another";
$l['edit_stylesheet_properties_for'] = "Edit Stylesheet Properties for";
$l['file_name'] = "File Name";
$l['file_name_desc'] = "Name for the stylesheet, usually ending in <strong>[.css]</strong>";
$l['save_stylesheet_properties'] = "Save Stylesheet Properties";
$l['saved'] = "Saved";
$l['editing'] = "Editing";
$l['selector'] = "Selector";
$l['save_changes'] = "Save Changes";
$l['save_changes_and_close'] = "Save Changes & Close";
$l['save_changes_js'] = "Do you want to save your changes first?";
$l['delete_confirm_js'] = "Are you sure you want to delete this?";
$l['import_stylesheet_from'] = "Import from another stylesheet in this theme";
$l['write_own'] = "Write my own content";
$l['save_stylesheet'] = "Save Stylesheet";
$l['add_stylesheet_to'] = "Add Stylesheet to";

$l['full_stylesheet_for'] = "Full Stylesheet for";

$l['recommended_themes_for_mybb'] = "Recommended Themes for MyBB {1}";
$l['browse_results_for_mybb'] = "Browse Results for MyBB {1}";
$l['search_for_themes'] = "Search for Themes";
$l['search'] = "Search";
$l['download'] = "Download";
$l['created_by'] = "Created by";

$l['error_invalid_stylesheet'] = "You have selected an invalid stylesheet.";
$l['error_invalid_theme'] = "You have selected an invalid theme.";
$l['error_missing_name'] = "Please enter a name for this theme.";
$l['error_missing_url'] = "Please enter a valid url to import a theme from.";
$l['error_theme_already_exists'] = "A theme with the same name already exists. Please specify a different name.";
$l['error_theme_security_problem'] = "A potential security issue was found in the theme. It was not imported. Please contact the Author or MyBB Group for support.";

$l['error_local_file'] = "Could not open the local file. Does it exist? Please check and try again.";
$l['error_uploadfailed'] = "Upload failed. Please try again.";
$l['error_uploadfailed_detail'] = "Error details: ";
$l['error_uploadfailed_php1'] = "PHP returned: Uploaded file exceeded upload_max_filesize directive in php.ini.  Please contact your forum administrator with this error.";
$l['error_uploadfailed_php2'] = "The uploaded file exceeded the maximum file size specified.";
$l['error_uploadfailed_php3'] = "The uploaded file was only partially uploaded.";
$l['error_uploadfailed_php4'] = "No file was uploaded.";
$l['error_uploadfailed_php6'] = "PHP returned: Missing a temporary folder.  Please contact your forum administrator with this error.";
$l['error_uploadfailed_php7'] = "PHP returned: Failed to write the file to disk.  Please contact your forum administrator with this error.";
$l['error_uploadfailed_phpx'] = "PHP returned error code: {1}.  Please contact your forum administrator with this error.";
$l['error_uploadfailed_lost'] = "The file could not be found on the server.";
$l['error_uploadfailed_nocontents'] = "MyBB could not find the theme with the file you uploaded. Please check the file is the correct and is not corrupt.";
$l['error_invalid_version'] = "This theme has been written for another version of MyBB. Please check the \"Ignore Version Compatibility\" to ignore this error.";
$l['error_missing_stylesheet_name'] = "Please enter a name for this stylesheet.";
$l['error_missing_stylesheet_extension'] = "This stylesheet must end with the correct file extension, for example, {1}<em>.css</em>";
$l['error_invalid_parent_theme'] = "The selected parent theme does not exist. Please select a valid parent theme.";
$l['error_invalid_templateset'] = "The selected template set does not exist. Please select a valid template set.";
$l['error_invalid_editortheme'] = "The selected editor theme does not exist. Please select a valid editor theme.";
$l['error_inheriting_stylesheets'] = "You cannot delete this theme because there are still other themes that are inheriting stylesheets from it.";
$l['error_cannot_parse'] = "MyBB cannot parse this stylesheet for the simple editor. It can only be edited in advanced mode.";
$l['error_communication_problem'] = "There was a problem communicating with the MyBB themes server. Please try again in a few minutes.";
$l['error_no_results_found'] = "No results were found for the specified keyword(s).";
$l['error_no_color_picked'] = "You didn't specify which colors to attach this stylesheet to.";
$l['error_no_display_order'] = "There was an error finding the display orders for the stylesheets. Please refresh the page and try again.";

$l['success_duplicated_theme'] = "The selected theme has been duplicated successfully.";
$l['success_imported_theme'] = "The selected theme has been imported successfully.";
$l['success_theme_created'] = "The theme has been created successfully.";
$l['success_theme_deleted'] = "The selected theme has been deleted successfully.";
$l['success_stylesheet_properties_updated'] = "The properties for the selected stylesheet have been updated successfully.";
$l['success_stylesheet_updated'] = "The selected stylesheet has been updated successfully.";
$l['success_stylesheet_deleted'] = "The selected stylesheet has been deleted / reverted successfully.";
$l['success_theme_set_default'] = "The selected theme is now the forum default.";
$l['success_theme_forced'] = "All users have been forced to use the selected theme successfully.";
$l['success_theme_properties_updated'] = "The properties for the select theme have been updated successfully.";
$l['success_stylesheet_added'] = "The stylesheet for this theme has been created successfully.";
$l['success_stylesheet_order_updated'] = "The display orders for the stylesheets have been updated successfully.";

$l['confirm_theme_deletion'] = "Are you sure you want to delete this theme?";
$l['confirm_stylesheet_deletion'] = "Are you sure you want to delete / revert this stylesheet?";
$l['confirm_theme_forced'] = "Are you sure you want to force this theme on all users?";

$l['theme_info_fetch_error'] = 'There was an error fetching the style info.';
$l['theme_info_save_error'] = 'There was an error saving the style info.';

$l['saving'] = 'Saving...';

