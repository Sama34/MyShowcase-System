<?php
/**
 * MyShowcase Plugin for MyBB - Language file for ACP, MyShowcase Fields
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\languages\<language>\admin\myshowcase_fields.lang.php
 *
 */

/**
 * FIELDSETS
 */

//main words
$l['myshowcase_fields_title'] = 'Field Sets';
$l['myshowcase_fields_new'] = 'New Field Set';

//headers
$l['myshowcase_fields_id'] = 'ID';
$l['myshowcase_fields_name'] = 'Name';
$l['myshowcase_fields_count'] = 'Field Count';
$l['myshowcase_fields_used_by'] = 'In Use By';
$l['myshowcase_fields_assigned_to'] = 'Assigned To';

//editing
$l['myshowcase_fields_save_changes'] = 'Save Changes';
$l['myshowcase_fields_in_use'] = 'Can not perform that action, specified field set is being used by a showcase.';

//messages
$l['myshowcase_fields_invalid_name'] = 'The selected field set name is already in use.';
$l['myshowcase_fields_invalid_id'] = 'The selected field set ID is invalid.';
$l['myshowcase_fields_no_fieldsets'] = 'There are no field sets present.';
$l['myshowcase_fields_already_exists'] = 'The selected field set name or file is already in use.';

$l['myshowcase_fields_add_success'] = 'The field set was successfully added.';
$l['myshowcase_fields_edit_success'] = 'The field set was successfully edited.';
$l['myshowcase_fields_delete_success'] = 'The field set was successfully deleted.';
$l['myshowcase_fields_update_success'] = 'The field set was successfully updated or added.';

$l['myshowcase_fields_add_failed'] = 'The field set not added.';
$l['myshowcase_fields_edit_failed'] = 'The field set was not edited.';
$l['myshowcase_fields_delete_failed'] = 'The field set was not deleted.';

$l['myshowcase_fields_confirm'] = 'Confirm';
$l['myshowcase_fields_confirm_delete'] = 'Confirm Delete';

$l['myshowcase_fields_confirm_delete_long'] = "You are about to delete a fieldset titled '{1}'";
$l['myshowcase_fields_no_fields'] = 'The specified field set has no fields.';

$l['myshowcase_fields_lang_not_writable'] = 'The language folder, {1},  is not writable. Please corect and try again.';
$l['myshowcase_fields_lang_exists_no'] = 'The default language file for this fieldset does not exist.';
$l['myshowcase_fields_lang_exists_yes'] = 'The default language file for this fieldset exists.';
$l['myshowcase_fields_lang_exists_write'] = 'The default language file for this fieldset exists but is not writable.';
$l['myshowcase_fields_lang_exists'] = 'Language File';

/**
 * FIELDS
 */

$l['myshowcase_field_new'] = 'New Field';
$l['myshowcase_field_list'] = 'Current Fields';

//headers
$l['myshowcase_field_fid'] = 'FID';
$l['myshowcase_field_fdid'] = 'FDID';
$l['myshowcase_field_value'] = 'Value';
$l['myshowcase_field_valueid'] = 'Value ID';
$l['myshowcase_field_disporder'] = 'Display Order';
$l['myshowcase_field_name'] = 'Field Name';
$l['myshowcase_field_label'] = 'Field Label';
$l['myshowcase_field_html_type'] = 'HTML Type';
$l['myshowcase_field_field_type'] = 'Field Type';
$l['myshowcase_field_enabled'] = 'Is Enabled?';
$l['myshowcase_field_min_length'] = 'Min Field Data Length';
$l['myshowcase_field_max_length'] = 'Max Field Data Length';
$l['myshowcase_field_required'] = 'Is Required?';
$l['myshowcase_field_field_order'] = 'Field Order<br>(View)';
$l['myshowcase_field_list_table_order'] = 'Field Order<br>(List)';
$l['myshowcase_field_searchable'] = 'Is Searchable?';
$l['myshowcase_field_parse'] = 'Run Through Parser?';
$l['myshowcase_field_format'] = 'Format?';

$l['myshowcase_field_delete'] = 'Delete';
$l['myshowcase_field_edit_options'] = 'Edit Options';

//messages
$l['myshowcase_field_in_use'] = 'The field is already in use in a table.';
$l['myshowcase_field_invalid_name'] = 'The selected field name is invalid or already in use.';
$l['myshowcase_field_invalid_id'] = 'The selected field ID is invalid.';
$l['myshowcase_field_required_not_filled'] = 'The required fields are not populated or are invalid.';

$l['myshowcase_field_add_success'] = 'The field was successfully added.';
$l['myshowcase_field_edit_success'] = 'The field was successfully edited.';
$l['myshowcase_field_delete_success'] = 'The field was successfully deleted.';
$l['myshowcase_field_update_success'] = 'The field was successfully updated or added.';

$l['myshowcase_field_add_opt_success'] = 'The field option was successfully added.';
$l['myshowcase_field_edit_opt_success'] = 'The field option was successfully edited.';

$l['myshowcase_field_delete_opt_success'] = 'The field option was successfully deleted.';
$l['myshowcase_field_update_opt_success'] = 'The field option was successfully updated or added.';

$l['myshowcase_field_confirm'] = 'Confirm';
$l['myshowcase_field_confirm_delete'] = 'Confirm Delete';

$l['myshowcase_field_confirm_delete_long'] = "You are about to delete a field titled '{1}'";
$l['myshowcase_field_no_options'] = 'The specified field has no options.';
$l['myshowcase_field_new_option'] = 'New Field Option';

$l['myshowcase_field_option_text'] = 'Option text';
$l['myshowcase_field_invalid_opt'] = 'The selected field option is invalid.';

$l['myshowcase_field_year_order'] = 'For the DATE field the MIN and MAX values need to be in the proper order.';


?>
