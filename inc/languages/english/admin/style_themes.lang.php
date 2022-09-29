<?php
/**
 * MyBB 1.8 English Language Pack
 * Copyright 2014 MyBB Group, All Rights Reserved
 *
 */

$l['themes'] = "Themes";
$l['themes_desc'] = "Here, you can manage the themes set up on your forum. Themes allow you to customize the appearance of your forum. A list of the themes currently set up are shown below.";

$l['theme_templates'] = "Theme Templates";

$l['create_new_theme'] = "Create New Theme";
$l['create_new_theme_desc'] = "Here, you can create a new theme based on the default. <strong>Template sets, stylesheets, and other settings are inherited from the parent theme.</strong>";

$l['import_a_theme'] = "Import a Theme";
$l['import_a_theme_desc'] = "Here, you can import a new theme. You may import a theme from your computer, or a remote URL.";

$l['edit_stylesheets'] = "Edit Stylesheets";
$l['edit_stylesheets_desc'] = "Here, you can easily manage the stylesheets in use by this theme. Stylesheets are based on CSS (.css) or SASS (.scss) and define the fonts, colors and other visual aspects for this theme. A list of stylesheets attached to this theme is below.";

$l['add_stylesheet'] = "Add Stylesheet";
$l['add_stylesheet_desc'] = "Here, you can add a new stylesheet to this theme. A stylesheet contains CSS (.css) or SASS (.scss) that allows you to customize the appearance of this theme. You will be taken to the stylesheet edit page following creation.";

$l['browse_themes'] = "Browse Themes";
$l['browse_themes_desc'] = "Here, you may browse the official MyBB modifications site for themes compatible with your series of MyBB.";

$l['browse_all_themes'] = "Browse All Themes";

$l['export_theme'] = "Export Theme";
$l['export_theme_desc'] = "Here, you can export your theme, including its properties, stylesheets, and templates. Exporting a theme is useful if you wish to share it with others or import it into another forum.";

$l['duplicate_theme'] = "Duplicate Theme";
$l['duplicate_theme_desc'] = "Here, you can duplicate your theme. This helps you if you want to develop another version of it.";

$l['namespace_lc_sq'] = '[namespace]';
$l['plugin_lc_sq'] = '[plugin]';
$l['inherited_lc_sq'] = '[inherited]';
$l['have_own_copy_lc_sq'] = '[have own copy]';

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
$l['include_custom_only_desc'] = "If you wish to include resources (stylesheets, templates, images, etc) inherited from parent themes select \"no\", otherwise only customized elements will be exported.";
$l['export_devdist'] = "Export the `devdist` version of the theme?";
$l['export_devdist_desc'] = "If you want to export the `current` version instead, select no.";

$l['edit_stylesheet_simple_mode'] = "Edit Stylesheet: Simple Mode";
$l['edit_stylesheet_simple_mode_desc'] = "Here, you can easily edit your theme's stylesheet. Simple mode allows you to customize the CSS in this stylesheet with little or no knowledge of CSS. Begin by selecting an item below.";
$l['edit_stylesheet_advanced_mode'] = "Edit Stylesheet: Advanced Mode";
$l['edit_stylesheet_advanced_mode_desc'] = "Here, you can edit this stylesheet like a flat file. The contents of the {1} stylesheet are shown in the text area below.";
$l['stylesheet_type'] = "Stylesheet type: ";

$l['editing_template'] = 'Editing Template: {1}';
$l['edit_template'] = 'Edit Template';
$l['edit_template_desc'] = "Here, you can edit the template's code.";

$l['add_template'] = 'Add Template';
$l['add_template_desc'] = 'Here you can create a new template.';

$l['template_add_name'] = 'Template Path and Name';
$l['template_add_name_desc'] = 'The template will be saved to this path and name, e.g., `showthread/my_showthread_template.twig`.';
$l['template_add_namespace'] = 'Template Namespace';
$l['template_add_namespace_desc'] = 'The template will be saved to this namespace. Leave blank for the default namespace of `frontend`. Other core namespaces include `acp` and `parser`. To save to a plugin namespace, prefix its codename with `ext.`. For example, to save to the namespace of the distributed example "Hello World" plugin, enter here `ext.hello`.';

$l['theme'] = "Theme";
$l['num_users'] = "# Users";
$l['quick_links'] = 'Quick Links';
$l['edit_theme'] = "Edit Theme";
$l['delete_theme'] = "Delete Theme";
$l['set_as_default'] = "Set as Default";
$l['default_theme'] = "Default Theme";
$l['force_on_users'] = "Force on Users";
$l['delete_revert'] = "Delete / Revert";

$l['local_file'] = "Local File";
$l['url'] = "URL";
$l['staged_theme'] = 'Staged Theme';
$l['import_from'] = "Import from";
$l['import_from_desc'] = "Select a file to import. You can either import the theme file from your computer or from a URL. Remember to use themes from <strong>safe and trusted sources only</strong>.";
$l['parent_theme'] = "Parent Theme";
$l['parent_theme_desc'] = "Select the theme this theme should be a child of.";
$l['new_name'] = "New Name";
$l['new_name_desc'] = "A new name for the imported theme. If left blank, the name in the theme file will be used.";
$l['new_codename'] = "New Codename";
$l['advanced_options'] = "Advanced Options";
$l['ignore_version_compatibility'] = "Ignore Version Compatibility";
$l['ignore_version_compat_desc'] = "Should this theme be installed regardless of the version of MyBB it was created for?";
$l['import_stylesheets'] = "Import Stylesheets";
$l['import_stylesheets_desc'] = "If this theme contains custom stylesheets should they be imported?";
$l['import_templates'] = "Import Templates";
$l['import_templates_desc'] = "If this theme contains custom templates should they be imported?";
$l['import_theme'] = "Import Theme";

$l['new_name_duplicate_desc'] = 'A new name for the duplicated theme.';
$l['new_codename_duplicate_desc'] = 'A new codename for the duplicated theme.';
$l['original_vs_board_themes_desc'] = 'Via an optional prefix, you can indicate the type of the theme: "board" or "original".
<br><br>
<strong>Board</strong> themes are suitable for production use. A board theme unconditionally (1) is editable via the ACP, (2) can be modified by plugins, and (3) can be set as default or selected by members, whether in ordinary operation (assuming it has a `current` directory), or when themelet development mode is on (assuming it has a `devdist` directory).
<br><br>
To duplicate as a <strong>board</strong> theme, prefix this codename with `<strong>board.</strong>` (that is, the word <strong>board</strong> followed by a dot).
<br><br>
Without that prefix, an <strong>original</strong> theme is indicated.
<br><br>
<strong>Original</strong> themes are suitable for development and distribution, and/or for inheriting from. When themelet development mode is off, and an original theme is thus using its `current` directory, it is wholly immutable, and cannot even be selected by members. Its sole function is to be inherited from. Only in themelet development mode, and only if the original theme has a `devdist` directory, can it be modified by plugins, edited via the ACP, and selected by members.<br><br>
Valid codename characters (aside from the dot in any `board.` prefix) are lowercase `a` through `z` and underscore.';
$l['create_devdist'] = "Add a `devdist` directory?";
$l['create_devdist_desc'] = "The theme to be duplicated does not have a development and distribution (`devdist`) directory. Check this box to create one in the duplicated theme. It will be copied from the ultimate `current` directory when generating the duplicate theme.";
$l['create_current'] = "Add a `current` directory?";
$l['create_current_desc'] = "The theme to be duplicated does not have a production (`current`) directory. Check this box to create one in the duplicated theme. It will be copied from the ultimate `devdist` directory when generating the duplicate theme.";
$l['dup_type'] = 'Duplicate as:';
$l['dup_type_desc'] = '(Safe to leave as default if unsure)';
$l['dup_type_child_full'] = 'A child theme, with full inheritance (no resources copied).';
$l['dup_type_child_exact'] = 'A child theme, with identical inheritance (only parent resources copied).';
$l['dup_type_child_resolved'] = 'A child theme, with fully resolved inheritance (all resources copied).';
$l['dup_type_sibling_exact'] = 'A sibling theme, with identical inheritance (a strict horizontal resources copy).';
$l['dup_type_sibling_resolved'] = 'A sibling theme, with fully resolved inheritance (all resources copied).';

$l['based_on_theme'] = 'Click the existing theme on which the new theme should be based';

$l['duplicate_stylesheets'] = "Duplicate Stylesheets";
$l['duplicate_stylesheets_desc'] = "If this theme contains custom stylesheets should they be duplicated?";
$l['duplicate_templates'] = "Duplicate Templates";
$l['duplicate_templates_desc'] = "If this theme contains custom templates should they be duplicated?";

$l['create_a_theme'] = "Create a Theme";
$l['name'] = "Name";
$l['name_desc'] = "Specify a name for the new theme.";
$l['display_order'] = "Order";

$l['edit_templates'] = 'Edit Templates';
$l['edit_templates_desc'] = 'Here, you can easily manage the templates in use by this theme.';
$l['templates_in_theme'] = 'Templates in {1}';
$l['actions_ucf'] = 'Actions';
$l['add_template_here'] = 'Add Template Here';

$l['template_name'] = 'Template Path and Name';
$l['template_name_desc'] = 'If you change this, the template will be saved to the new path and name. The original template will not be affected.';
$l['template_namespace'] = 'Template Namespace';
$l['template_namespace_desc'] = 'If you change this, the template will be saved to the new namespace. The original template will not be affected.';

$l['save_continue'] = 'Save and Continue Editing';
$l['save_close'] = 'Save and Return to Listing';
$l['save'] = 'Save';

$l['edit_theme_properties'] = "Edit Theme Properties";
$l['edit_theme_desc'] = 'Here, you can edit the theme\'s properties.';
$l['name_desc_edit'] = "Specify a name for the theme.";
$l['description'] = 'Description';
$l['description_desc_edit'] = 'Provide a description for the theme.';
$l['allowed_user_groups'] = "Allowed User Groups";
$l['allowed_user_groups_desc'] = "Specify which user groups are allowed to use this theme. Selecting 'All User Groups' will override any other selection. Hold down the CTRL key to select multiple user groups.";
$l['all_user_groups'] = "All User Groups";
$l['template_set'] = "Template Set";
$l['template_set_desc'] = "Specify the template set the theme should use. The selected template set defines the markup (HTML) used in presenting the theme.";
$l['editor_theme'] = "Editor Style";
$l['editor_theme_desc'] = "Specify the style to be used for the MyCode editor in this theme. Editor styles can be found in the <strong>jscripts/sceditor/themes</strong> folder.";
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
$l['the_plugin_sq_br'] = '[the plugin]';
$l['in_plugin_namespace'] = '[In the namespace of plugin {1}]';
$l['in_namespace'] = '[In the {1} namespace]';
$l['attached_to'] = "Attached to";
$l['attached_to_nothing'] = "Attached to nothing";
$l['attached_to_desc'] = "You can attach stylesheets either globally, to specific files, or to one or more theme colors. When attaching a stylesheet to a specific file, you can stipulate the specific action(s) within that file for which it will be triggered.";
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
$l['namespace'] = 'Namespace';
$l['namespace_desc'] = 'The stylesheet\'s namespace, for example <em>frontend</em> (the default if left blank), <em>parser</em>, or <em>acp</em>.';
$l['file_name'] = "File Name";
$l['file_name_desc'] = "Name for the stylesheet. Must end in <strong>.css</strong> (even if you provide SASS content).";
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
$l['theme_thumbnail'] = "Theme Thumbnail";

$l['error_invalid_stylesheet'] = "You have selected an invalid stylesheet.";
$l['error_invalid_theme'] = "You have selected an invalid theme.";
$l['error_missing_name'] = "Please enter a name for this theme.";
$l['error_missing_url'] = "Please enter a valid url to import a theme from.";
$l['error_missing_import_source'] = "You supplied neither a local file, a URL, nor a staged theme.";
$l['error_theme_already_exists'] = "A theme with the same name, '{1}', already exists. Please specify a different name.";
$l['error_theme_security_problem'] = "A potential security issue was found in the theme. It was not imported. Please contact the Author or MyBB Group for support.";

$l['error_missing_or_empty_remote_file'] = "Failed to fetch the file at the URL {1} (or it is empty). Does it exist? Please check and try again.";
$l['error_failed_to_save_remote_file'] = 'Failed to create a temporary directory and save the remote file to it.';
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
$l['error_invalid_stylesheet_name'] = 'The supplied stylesheet name "{1}" contains invalid characters (aside from directory separators - forward slashes - and the dot before the "css" extension, only lowercase `a` through `z` and underscore are valid. It must neither begin nor end with a directory separator, nor end with a dot. It must also not contain any doubled directory separators.).';
$l['error_invalid_template_name'] = 'The supplied template name "{1}" contains invalid characters (aside from directory separators - forward slashes - and the dot before the "twig" extension, only lowercase `a` through `z` and underscore are valid. It must neither begin nor end with a directory separator, nor end with a dot. It must also not contain any doubled directory separators.).';
$l['error_missing_stylesheet_extension'] = "This stylesheet must end with the correct file extension, for example, {1}<em>.css</em>";
$l['error_invalid_parent_theme'] = "The selected parent theme does not exist. Please select a valid parent theme.";
$l['error_invalid_templateset'] = "The selected template set does not exist. Please select a valid template set.";
$l['error_invalid_color'] = "The defined color set \"{1}\" either has invalid character(s) or is not in the prescribed format. Only numbers, Unicode letters, and underscores are allowed in a color's name and its value, which must be separated by '='.";
$l['error_invalid_editortheme'] = "The selected editor theme does not exist. Please select a valid editor theme.";
$l['error_inheriting_stylesheets'] = "You cannot delete this theme because there are still other themes that are inheriting stylesheets from it.";
$l['error_cannot_parse'] = "MyBB cannot parse this stylesheet for the simple editor. It can only be edited in advanced mode.";
$l['error_communication_problem'] = "There was a problem communicating with the MyBB themes server. Please try again in a few minutes.";
$l['error_no_results_found'] = "No results were found for the specified keyword(s).";
$l['error_no_color_picked'] = "You didn't specify which colors to attach this stylesheet to.";
$l['error_no_display_order'] = "There was an error finding the display orders for the stylesheets. Please refresh the page and try again.";
$l['error_stylesheet_order_update'] = 'Failed to write the stylesheet order to the theme\'s resources.json file.';
$l['error_failed_to_save_theme'] = 'Failed to update the theme\'s theme.json file.';
$l['error_failed_to_save_stylesheet_props'] = 'Failed to save the stylesheet properties to the theme\'s resources.json file.';
$l['error_failed_to_create_tmpdir'] = 'Failed to create a temporary directory.';

$l['error_path_with_double_dot'] = 'The supplied {1} contains an illegal double-dot followed by a directory separator character (/ or \\).';
// Each of these can be supplied to replace {1} in the string above.
$l['theme_codename'] = 'theme codename';
$l['stylesheet_filename'] = 'stylesheet filename';
$l['staging_filename'] = 'staging filename';
$l['template_path'] = 'template path';

$l['error_template_path_with_directory_separator'] = 'The supplied  contains an illegal double-dot followed by a directory separator character (/ or \\).';
$l['error_logo_with_directory_separator'] = 'The supplied logo path contains an illegal double-dot followed by a directory separator character (/ or \\).';
$l['error_no_ziparchive_for_theme'] = 'The ZipArchive class was not found. This class is necessary to automatically unzip theme archives. If you are unable to install the PHP package providing this class, then instead simply unzip your theme archive manually into the `staging/themes/` directory and refresh this page. It should then show up as a "Staged" theme, allowing you to import it.';
$l['error_theme_unzip_open_failed'] = 'Unable to open the uploaded zip file. ZipArchive::open() returned code: {1}.';
$l['error_theme_unzip_failed'] = 'Failed to unzip the theme archive.';
$l['error_theme_unzip_multi_or_none_root'] = 'Invalid theme archive: either no or multiple file/directory entries found in root. Expected one theme directory entry.';
$l['error_theme_already_staged'] = 'A version of this theme with codename "{1}" is already staged. Please either delete the existing staged version at `staged/themes/{1}` before trying again, or select the theme with the corresponding name under "Staged Theme".';
$l['error_theme_move_fail'] = 'Failed to move the unzipped theme from its temporary directory ({1}) to its staging directory ({2}).';
$l['error_bad_staged_json_file'] = "Failed to parse the staged theme JSON file '{1}'.";
$l['error_bad_json_file'] = "Failed to parse the installed JSON theme file '{1}'.";
$l['error_missing_theme_file_property'] = 'The `{1}` property in the theme JSON file "{2}" is missing or empty.';
$l['error_codename_mismatch'] = 'The staged theme\'s `codename` property ({1}) does not match the theme\'s root directory name ({2}).';
$l['error_identical_theme_version'] = 'The installed version of this theme with codename "{1}" has the same version number ({2}) as that of the theme package you have supplied.';
$l['error_theme_incompatible_with_mybb_version'] = 'The theme that you are trying to install/upgrade/downgrade has a compatibility property ("{1}") that does not match the current MyBB version ("{2}"). If you wish to install it anyway, please try again with the "Ignore Version Compatibility" checkbox checked.';
$l['error_theme_archival_failed'] = 'Failed to archive the existing installed version of the theme. Upgrade/downgrade aborted.';
$l['error_theme_rename_failed'] = 'Failed to move the staged version of the theme into the theme installation directory. If this was an upgraded/downgrade, the previously installed version of the theme has been successfully archived - you can restore it by copying it from its archival directory (`storage/themelets/[codename]/[version]`) to its installation directory (`inc/themes/[codename]/current`).';
$l['error_stylesheet_already_exists'] = 'A stylesheet with that name already exists in this theme.';
$l['error_no_ziparchive_for_theme'] = 'The ZipArchive class was not found. This class is necessary to automatically zip themes for distribution. If you are unable to install the PHP package providing this class, then please zip up your theme manually.';
$l['error_failed_to_mkdir'] = 'Failed to create the directory "{1}".';
$l['error_failed_write_stylesheet'] = 'Failed to write the stylesheet to the file "{1}".';
$l['error_stylesheet_not_found'] = 'Stylesheet "{1}" not found.';
$l['error_theme_has_no_contents'] = 'The theme to be duplicated has no contents (neither a `current` nor a `devdist` subdirectory).';
$l['error_missing_theme_codename'] = 'No theme codename was supplied.';
$l['error_invalid_theme_codename'] = 'The supplied theme codename "{1}" contains invalid characters (only lowercase `a` through `z` and underscore are valid, optionally prefixed by `board.` or `core.`.';
$l['error_invalid_namespace'] = 'The supplied namespace "{1}" contains invalid characters (only lowercase `a` through `z` and underscore are valid, optionally prefixed with `ext.`).';
$l['error_invalid_theme_archive_tld'] = 'The top-level theme directory in the supplied archive "{1}" contains invalid characters (only lowercase `a` through `z` and underscore are valid, optionally prefixed by `board.` or `core.`.';
$l['error_invalid_staged_theme_tld'] = 'The top-level theme directory "{1}" in the referenced staged theme contains invalid characters (only lowercase `a` through `z` and underscore are valid, optionally prefixed by `board.` or `core.`.';
$l['error_theme_codename_exists'] = 'The supplied theme codename "{1}" already exists.';
$l['error_cp_failed'] = 'Failed to copy {1} to {2}.';
$l['error_no_template_input'] = 'The path to the template to edit was not supplied.';
$l['error_failed_write_template'] = 'Failed to write the template file to the filesystem.';
$l['error_missing_template_path'] = 'The template path and name were missing.';
$l['error_missing_template_body'] = 'The contents of the template were empty.';
$l['error_template_already_exists'] = 'A template in that namespace with that path and name already exists. You can {1}edit{2} it instead.';
$l['error_invalid_template'] = 'A template in this theme with the supplied namespace and name was not found.';
$l['error_failed_to_delete_template_file'] = 'The template file could not be deleted from the filesystem (the call to unlink returned false).';
$l['error_undeletable_core_theme'] = 'This theme cannot be deleted because it is a core theme.';
$l['error_immutable_theme'] = 'Changes cannot be made to this theme because it is either a core theme or an original theme and themelet development mode is not enabled (outside of themelet development mode, only board themes can be changed).';
$l['error_cannot_set_to_default'] = 'This theme may not be set to default because it is not a board theme.';
$l['error_cannot_force_theme'] = 'This theme may not be forced on users because it is not a board theme.';

$l['warning_immutable_theme'] = 'This theme is immutable, because it is not a board theme and themelet development mode is not enabled. Any changes that you make on this page will not be saved: it is read-only. To create a mutable (board) theme from this one, use the "Duplicate Theme" tool.';

$l['success_duplicated_theme'] = "The selected theme has been duplicated successfully.";
$l['success_upgraded_theme'] = "The theme '{1}' has been successfully upgraded from version '{2}' to version '{3}'.";
$l['success_downgraded_theme'] = "The theme '{1}' has been successfully downgraded from version '{2}' to version '{3}'.";
$l['success_imported_theme'] = "The new theme '{1}' has been imported successfully.";
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
$l['success_template_saved'] = "The selected template has successfully been saved.";
$l['success_new_template_saved'] = 'The new template has successfully been saved.';
$l['success_template_deleted'] = 'The selected template has successfully been deleted.';

$l['confirm_theme_deletion'] = "Are you sure you want to delete this theme?";
$l['confirm_stylesheet_deletion'] = "Are you sure you want to delete / revert this stylesheet?";
$l['confirm_theme_forced'] = "Are you sure you want to force this theme on all users? This may reset the theme's access level.";
$l['confirm_template_deletion'] = 'Are you sure you want to delete / revert this template?';

$l['theme_info_fetch_error'] = 'There was an error fetching the style info.';
$l['theme_info_save_error'] = 'There was an error saving the style info.';

$l['saving'] = 'Saving&hellip;';

