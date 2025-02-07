<?php
/**
 * MyShowcase Plugin for MyBB - Language file for ACP, MyShowcase Summary
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\languages\<language>\admin\myshowcase_summary.lang.php
 *
 */

declare(strict_types=1);

//main words
$l['myshowcase_summary_id'] = 'ID';
$l['myshowcase_summary_name'] = 'Name';
$l['myshowcase_summary_description'] = 'Description';
$l['myshowcase_summary_entries_count'] = 'Record Count';
$l['myshowcase_summary_attachments_count'] = 'Attachments';
$l['myshowcase_summary_attachments_size'] = 'Files Size';
$l['myshowcase_summary_comment_count'] = 'Comments';
$l['myshowcase_summary_image_folder'] = 'Image Folder';
$l['myshowcase_summary_main_file'] = 'Main File';
$l['myshowcase_summary_forum_folder'] = 'Relative Path';
$l['myshowcase_summary_field_set'] = 'Field Set';

//status
$l['myshowcase_summary_status'] = 'Status';
$l['myshowcase_summary_status_enabled'] = 'Enabled';
$l['myshowcase_summary_status_disabled'] = 'Disabled';
$l['myshowcase_summary_status_notable'] = 'Data table for this showcase does not exist';

//editing
$l['myshowcase_summary_save_changes'] = 'Save Changes';
$l['myshowcase_summary_new'] = 'Add a New Showcase';
$l['myshowcase_summary_add'] = 'Add Showcase';
$l['myshowcase_summary_existing'] = 'Existing Showcases';

//messages
$l['myshowcase_summary_no_myshowcases'] = 'There are no showcases present.';
$l['myshowcase_summary_already_exists'] = 'The selected showcase name or file is already in use.';
$l['myshowcase_summary_invalid_name'] = 'The selected showcase name is already in use.';
$l['myshowcase_summary_invalid_id'] = 'The specified ID is not valid.';
$l['myshowcase_summary_folder_exists'] = 'The specified folder already exists.';
$l['myshowcase_summary_mkdir_failed'] = 'Can not create requested image folder.';
$l['myshowcase_summary_not_specified'] = 'Not Specified';
$l['myshowcase_summary_no_folder'] = 'The specified folder does not exist or is not writable, please correct this before enabling this showcase.';

$l['myshowcase_summary_nofieldsets'] = 'Can not create a new showcase as there are no field sets defined.';

$l['myshowcase_summary_missing_required'] = 'Some required fields are missing.';

$l['myshowcase_summary_add_success'] = 'The showcase was successfully added.';
$l['myshowcase_summary_enable_success'] = 'The showcase was successfully enabled.';
$l['myshowcase_summary_disable_success'] = 'The showcase was successfully disabled.';

$l['myshowcase_summary_add_failed'] = 'The showcase not added.';
$l['myshowcase_summary_enable_failed'] = 'The showcase was not enabled.';
$l['myshowcase_summary_disable_failed'] = 'The showcase was not disabled.';

$l['myshowcase_summary_create_success'] = 'The showcase data table was successfully created.';
$l['myshowcase_summary_create_failed'] = 'Creating the showcase data table was unsuccessful.';

$l['myshowcase_summary_confirm_deletetable_long'] = 'Are you sure you want to delete the table to this showcase?';
$l['myshowcase_summary_confirm_delete'] = 'Confirm Delete';

$l['myshowcase_summary_deletetable_not_allowed'] = "This showcase's data table contains data and can not be deleted separately. If you wish to delete the populated table, delete the entire showcase.";
$l['myshowcase_summary_deletetable_success'] = 'The showcase data table was successfully deleted.';
$l['myshowcase_summary_deletetable_failed'] = 'Deleting the showcase data table was unsuccessful.';


//control-options
$l['myshowcase_summary_edit'] = 'Edit';
$l['myshowcase_summary_delete'] = 'Delete';
$l['myshowcase_summary_enable'] = 'Enable';
$l['myshowcase_summary_disable'] = 'Disable';
$l['myshowcase_summary_createtable'] = 'Create Table';
$l['myshowcase_summary_deletetable'] = 'Delete Table';
$l['myshowcase_summary_seo'] = 'Show SEO';

$l = array_merge([
    'myShowcaseAdminSummary' => 'Summary',
    'myShowcaseAdminSummaryDescription' => 'View and manage showcases',

    'myShowcaseAdminSummaryNew' => 'Add New Showcase',
    'myShowcaseAdminSummaryNewDescription' => 'Add a new showcase',
    'myShowcaseAdminSummaryNewFormName' => 'Name',
    'myShowcaseAdminSummaryNewFormNameDescription' => 'Name of the showcase',
    'myShowcaseAdminSummaryNewFormDescription' => 'Description',
    'myShowcaseAdminSummaryNewFormDescriptionDescription' => 'Description of the showcase',
    'myShowcaseAdminSummaryNewFormMainFile' => 'Main File',
    'myShowcaseAdminSummaryNewFormMainFileDescription' => 'Main file for the showcase',
    'myShowcaseAdminSummaryNewFormImageFolder' => 'Image Folder',
    'myShowcaseAdminSummaryNewFormImageFolderDescription' => 'Folder for images',
    'myShowcaseAdminSummaryNewFormRelativePath' => 'Relative Path',
    'myShowcaseAdminSummaryNewFormRelativePathDescription' => 'Relative path from the forum folder',
    'myShowcaseAdminSummaryNewFormFieldSet' => 'Field Set',
    'myShowcaseAdminSummaryNewFormFieldSetDescription' => 'Field set for the showcase',

    'myShowcaseAdminButtonSubmit' => 'Submit',
    'myShowcaseAdminButtonReset' => 'Reset',

    'myShowcaseAdminErrorInvalidPostKey' => 'Invalid post key',
    'myShowcaseAdminErrorNoFieldSets' => 'Can not create a new showcase as there are no field sets defined.',
    'myShowcaseAdminErrorMissingRequiredFields' => 'Some required fields are missing.',
    'myShowcaseAdminErrorDuplicatedName' => 'The selected name is already in use.',
    'myShowcaseAdminErrorDuplicatedMainFile' => 'The selected main file is already in use.',

    'myShowcaseAdminSuccessNewShowcase' => 'The showcase was successfully added.',
], $l);