<?php
/**
 * MyBB 1.6 English Language Pack
 * Copyright 2010 MyBB Group, All Rights Reserved
 * 
 * $Id: config_profile_fields.lang.php 5616 2011-09-20 13:24:59Z Tomm $
 */
 
$l['custom_profile_fields'] = "Custom Profile Fields";
$l['custom_profile_fields_desc'] = "This section allows you to edit, delete, and manage your custom profile fields.";
$l['add_profile_field'] = "Add Profile Field";
$l['add_new_profile_field'] = "Add New Profile Field";
$l['add_new_profile_field_desc'] = "Here you can add a new custom profile field.";
$l['edit_profile_field'] = "Edit Profile Field";
$l['edit_profile_field_desc'] = "Here you can edit a custom profile field.";

$l['title'] = "Title";
$l['short_description'] = "Short Description";
$l['maximum_length'] = "Maximum Length";
$l['maximum_length_desc'] = "This maximum number of characters that can be entered. This only applies to text boxes and text areas.";
$l['field_length'] = "Field Length";
$l['field_length_desc'] = "This length of the field. This only applies to single and multiple select boxes.";
$l['display_order'] = "Display Order";
$l['display_order_desc'] = "This is the order of custom profile fields in relation to other custom profile fields. This number should not be the same as another field.";
$l['text'] = "Textbox";
$l['textarea'] = "Textarea";
$l['select'] = "Select Box";
$l['multiselect'] = "Multiple Option Selection Box";
$l['radio'] = "Radio Buttons";
$l['checkbox'] = "Check Boxes";
$l['field_type'] = "Field Type";
$l['field_type_desc'] = "This is the field type that will be shown.";
$l['selectable_options'] = "Selectable Options?";
$l['selectable_options_desc'] = "Please enter each option on a separate line. This only applies to the select boxes, check boxes, and radio buttons types.";
$l['required'] = "Required?";
$l['required_desc'] = "Is this field required to be filled in during registration or profile editing? Note that this does not apply if the field is hidden.";
$l['editable_by_user'] = "Editable by user?";
$l['editable_by_user_desc'] = "Should this field be editable by the user? If not, administrators/moderators can still edit the field.";
$l['hide_on_profile'] = "Hide on profile?";
$l['hide_on_profile_desc'] = "Should this field be hidden on the user's profile? If it is hidden, it can only be viewed by administrators/moderators.";
$l['min_posts_enabled'] = "Minimum post count?";
$l['min_posts_enabled_desc'] = "Should this field only be available to users with a certain post count? If so, set the minimum amount of posts required here.";
$l['save_profile_field'] = "Save Profile Field";
$l['name'] = "Name";
$l['id'] = "ID";
$l['editable'] = "Editable?";
$l['hidden'] = "Hidden?";
$l['edit_field'] = "Edit Field";
$l['delete_field'] = "Delete Field";
$l['no_profile_fields'] = "There are no custom profile fields on your forum at this time.";

$l['error_missing_name'] = "You did not enter a title for this custom profile field";
$l['error_missing_description'] = "You did not enter a description for this custom profile field";
$l['error_missing_filetype'] = "You did not enter a field type for this custom profile field";
$l['error_missing_required'] = "You did not select Yes or No for the \"Required?\" option";
$l['error_missing_editable'] = "You did not select Yes or No for the \"Editable by user?\" option";
$l['error_missing_hidden'] = "You did not select Yes or No for the \"Hide on profile?\" option";
$l['error_invalid_fid'] = "The selected profile field does not exist.";

$l['success_profile_field_added'] = "The custom profile field has been created successfully.";
$l['success_profile_field_saved'] = "The custom profile field has been saved successfully.";
$l['success_profile_field_deleted'] = "The selected custom profile field has been deleted successfully.";

$l['confirm_profile_field_deletion'] = "Are you sure you wish to delete this profile field?";
?>