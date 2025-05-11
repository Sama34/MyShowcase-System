<?php
/**
 * MyShowcase Plugin for MyBB - Main Plugin
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: https://github.com/Sama34/MyShowcase-System
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\plugins\myshowcase.php
 *
 */

declare(strict_types=1);

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

$l['myshowcase_fields_no_fields'] = 'The specified field set has no fields.';

$l['myshowcase_fields_lang_not_writable'] = 'The language folder, "{1}",  is not writable. Please corect and try again.';
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
$l['myshowcase_field_enabled'] = 'Enabled';
$l['myshowcase_field_min_length'] = 'Minimum Length';
$l['myshowcase_field_max_length'] = 'Maximum Length';
$l['myshowcase_field_field_order'] = 'View Order';
$l['myshowcase_field_list_table_order'] = 'List Order';
$l['myshowcase_field_searchable'] = 'Searchable';
$l['myshowcase_field_parse'] = 'Parse';
$l['myshowcase_field_format'] = 'Format?';

$l['myshowcase_field_delete'] = 'Delete';
$l['myshowcase_field_edit_options'] = 'Edit Options';

//messages
$l['myshowcase_field_invalid_name'] = 'The selected field name is invalid or already in use.';
$l['myshowcase_field_invalid_id'] = 'The selected field ID is invalid.';
$l['myshowcase_field_required_not_filled'] = 'The required fields are not populated or are invalid.';

$l['myshowcase_field_add_success'] = 'The field was successfully added.';
$l['myshowcase_field_edit_success'] = 'The field was successfully edited.';
$l['myshowcase_field_update_success'] = 'The field was successfully updated or added.';

$l['myshowcase_field_add_opt_success'] = 'The field option was successfully added.';
$l['myshowcase_field_edit_opt_success'] = 'The field option was successfully edited.';

$l['myshowcase_field_delete_opt_success'] = 'The field option was successfully deleted.';
$l['myshowcase_field_update_opt_success'] = 'The field option was successfully updated or added.';

$l['myshowcase_field_confirm'] = 'Confirm';
$l['myshowcase_field_confirm_delete'] = 'Confirm Delete';

$l['myshowcase_field_confirm_delete_long'] = 'You are about to delete a field titled "{1}"';
$l['myshowcase_field_no_options'] = 'The specified field has no options.';
$l['myshowcase_field_new_option'] = 'New Field Option';

$l['myshowcase_field_option_text'] = 'Option Value';
$l['myshowcase_field_display_style'] = 'Display Style';
$l['myshowcase_field_invalid_opt'] = 'The selected field option is invalid.';

$l['myshowcase_field_year_order'] = 'For the DATE field the MIN and MAX values need to be in the proper order.';

$l = array_merge([
    'myShowcaseAdminFieldSets' => 'Field Sets',
    'myShowcaseAdminFieldSetsDescription' => 'View and manage fields sets for showcases.',

    'myShowcaseAdminFieldSetsNew' => 'New Field Set',
    'myShowcaseAdminFieldSetsNewDescription' => 'Create a new field set for showcases.',
    'myShowcaseAdminFieldSetsNewFormName' => 'Name',
    'myShowcaseAdminFieldSetsNewFormNameDescription' => 'The name of the field set.',

    'myShowcaseAdminFieldSetsEdit' => 'Edit Field Set',
    'myShowcaseAdminFieldSetsEditDescription' => 'Edit a field set for showcases.',

    'myShowcaseAdminFields' => 'Fields',
    'myShowcaseAdminFieldsDescription' => 'View and manage fields for showcases.',

    'myShowcaseAdminFieldsNew' => 'New Field',
    'myShowcaseAdminFieldsNewDescription' => 'Create a new field for this fieldset.',
    'myShowcaseAdminFieldsNewFormKey' => 'Name',
    'myShowcaseAdminFieldsNewFormKeyDescription' => 'The key of the field.',
    'myShowcaseAdminFieldsNewFormLabel' => 'Label',
    'myShowcaseAdminFieldsNewFormLabelDescription' => 'The label of the field.',
    'myShowcaseAdminFieldsNewFormHtmlType' => 'HTML Type',
    'myShowcaseAdminFieldsNewFormHtmlTypeDescription' => 'The HTML type of the field.',
    'myShowcaseAdminFieldsNewFormFieldType' => 'Field Type',
    'myShowcaseAdminFieldsNewFormFieldTypeDescription' => 'The type of the field.',
    'myShowcaseAdminFieldsNewFormFieldTypeExisting' => ' <span style="color: red;">Warning: changing this option may cause data loss to existing data stored in the showcase data table!</span>',
    'myShowcaseAdminFieldsNewFormDisplayInCreateUpdatePage' => 'Display in Create/Update Page',
    'myShowcaseAdminFieldsNewFormDisplayInCreateUpdatePageDescription' => 'Display this field in the create/update page.',
    'myShowcaseAdminFieldsNewFormDisplayInViewPage' => 'Display in View Page',
    'myShowcaseAdminFieldsNewFormDisplayInViewPageDescription' => 'Display this field in the view page.',
    'myShowcaseAdminFieldsNewFormDisplayInMainPage' => 'Display in Main Page',
    'myShowcaseAdminFieldsNewFormDisplayInMainPageDescription' => 'Display this field in the main page.',
    'myShowcaseAdminFieldsNewFormDefaultValue' => 'Default Value',
    'myShowcaseAdminFieldsNewFormDefaultValueDescription' => 'The default value of the field.',
    'myShowcaseAdminFieldsNewFormDefaultType' => 'Default Type',
    'myShowcaseAdminFieldsNewFormDefaultTypeDescription' => 'The default value type for this field.',
    'myShowcaseAdminFieldsNewFormDefaultTypeAsDefined' => 'As Defined',
    'myShowcaseAdminFieldsNewFormDefaultTypeNull' => 'NULL',
    'myShowcaseAdminFieldsNewFormDefaultTypeTimeStamp' => 'Time Stamp',
    'myShowcaseAdminFieldsNewFormDefaultTypeUUID' => 'UUID',
    'myShowcaseAdminFieldsNewFormMinimumLength' => 'Minimum Length',
    'myShowcaseAdminFieldsNewFormMinimumLengthDescription' => 'The minimum length of the field.',
    'myShowcaseAdminFieldsNewFormMaximumLength' => 'Maximum Length',
    'myShowcaseAdminFieldsNewFormMaximumLengthDescription' => 'The maximum length of the field.',
    'myShowcaseAdminFieldsNewFormRequired' => 'Is Required',
    'myShowcaseAdminFieldsNewFormRequiredDescription' => 'Require allowed groups to fill this field.',
    'myShowcaseAdminFieldsNewAllowedGroupsFill' => 'Allowed Fill Groups',
    'myShowcaseAdminFieldsNewAllowedGroupsFillDescription' => 'The groups that can fill this field.',
    'myShowcaseAdminFieldsNewFormAllowedGroupsView' => 'Allowed View Groups',
    'myShowcaseAdminFieldsNewFormAllowedGroupsViewDescription' => 'The groups that can view this field.',
    'myShowcaseAdminFieldsNewFormOrderList' => 'List Order',
    'myShowcaseAdminFieldsNewFormOrderListDescription' => 'The order of the field in the list.',
    'myShowcaseAdminFieldsNewFormEnableSubject' => 'Append as Subject',
    'myShowcaseAdminFieldsNewFormEnableSubjectDescription' => 'Append this field value as the entry subject.',
    'myShowcaseAdminFieldsNewFormEnableSlug' => 'Append as Slug',
    'myShowcaseAdminFieldsNewFormEnableSlugDescription' => 'Append this field value as the entry slug.',
    'myShowcaseAdminFieldsNewFormFormat' => 'Format',
    'myShowcaseAdminFieldsNewFormFormatDescription' => 'The format of the field.',
    'myShowcaseAdminFieldsNewFormEnableEditor' => 'Enable Editor',
    'myShowcaseAdminFieldsNewFormEnableEditorDescription' => 'Build an editor for this field.',

    'myShowcaseAdminFieldsEdit' => 'Edit Field',
    'myShowcaseAdminFieldsEditDescription' => 'Edit a field for this fieldset.',

    'myShowcaseAdminFieldsOptions' => 'Field Options',
    'myShowcaseAdminFieldsOptionsDescription' => 'View and manage field options for this field.',

    'myShowcaseAdminErrorInvalidFieldset' => 'Invalid fieldset',
    'myShowcaseAdminErrorDuplicatedName' => 'The selected name is already in use.',
    'myShowcaseAdminErrorFieldsetDeleteFailed' => 'Can not perform that action, specified field set is being used by a showcase.',
    'myShowcaseAdminErrorFieldsetDelete' => 'The fieldset was not deleted.',
    'myShowcaseAdminErrorInvalidField' => 'Invalid field',
    'myShowcaseAdminErrorFieldDeleteFailed' => 'The field deletion failed.',
    'myShowcaseAdminErrorInvalidMinMax' => 'Invalid minimum/maximum values.',

    'myShowcaseAdminSuccessNewFieldset' => 'The fieldset was successfully added.',
    'myShowcaseAdminSuccessEditFieldset' => 'The fieldset was successfully updated.',
    'myShowcaseAdminSuccessFieldsetDeleted' => 'The fieldset was successfully deleted.',
    'myShowcaseAdminSuccessNewField' => 'The field was successfully added.',
    'myShowcaseAdminSuccessEditField' => 'The field was successfully updated.',
    'myShowcaseAdminSuccessFieldDeleted' => 'The field was successfully deleted.',

    'myShowcaseAdminConfirmFieldsetDelete' => 'Are you sure you want to delete the "{1}" fieldset? ',
    'myShowcaseAdminConfirmFieldDelete' => 'Are you sure you want to delete the "{1}" field?',
    'myShowcaseAdminConfirmFieldDeleteExisting' => '<br/>The field is already in use in a table and data could be lost.',
], $l);