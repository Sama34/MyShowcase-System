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
$l['myshowcase_summary_slug'] = 'Slug';
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
$l['myshowcase_summary_existing'] = 'Existing Showcases';

//messages
$l['myshowcase_summary_no_myshowcases'] = 'There are no showcases present.';
$l['myshowcase_summary_not_specified'] = 'Not Specified';

//control-options
$l['myshowcase_summary_edit'] = 'Edit';
$l['myshowcase_summary_delete'] = 'Delete';
$l['myshowcase_summary_enable'] = 'Enable';
$l['myshowcase_summary_disable'] = 'Disable';
$l['myshowcase_summary_createtable'] = 'Create Table';
$l['myshowcase_summary_rebuildtable'] = 'Rebuild Table';
$l['myshowcase_summary_deletetable'] = 'Drop Table';
$l['myshowcase_summary_seo'] = 'Show SEO';

$l = array_merge([
    'myShowcaseAdminSummary' => 'Summary',
    'myShowcaseAdminSummaryDescription' => 'View and manage showcases.',

    'myShowcaseAdminSummaryNew' => 'Add New Showcase',
    'myShowcaseAdminSummaryNewDescription' => 'Add a new showcase.',
    'myShowcaseAdminSummaryNewFormName' => 'Name',
    'myShowcaseAdminSummaryNewFormNameDescription' => 'This is the name used in links and titles of the showcase.',
    'myShowcaseAdminSummaryNewFormMShowcaseSlug' => 'Showcase Slug',
    'myShowcaseAdminSummaryNewFormMShowcaseSlugDescription' => 'Showcase slug that is used with this specific showcase.',
    'myShowcaseAdminSummaryNewFormDescription' => 'Description',
    'myShowcaseAdminSummaryNewFormDescriptionDescription' => 'Simple description of the showcase. Not used externally, for admin use only.',
    'myShowcaseAdminSummaryNewFormMainFile' => 'Main File',
    'myShowcaseAdminSummaryNewFormMainFileDescription' => 'File name that is used with this specific showcase.',
    'myShowcaseAdminSummaryNewFormImageFolder' => 'Attachments Folder',
    'myShowcaseAdminSummaryNewFormImageFolderDescription' => 'This is path, relative to the main file, that is used for storing attachments.',
    'myShowcaseAdminSummaryNewFormRelativePath' => 'Relative Path',
    'myShowcaseAdminSummaryNewFormRelativePathDescription' => 'This is path from the forum index page to the showcase. (empty is okay if file is in same folder as forum).',
    'myShowcaseAdminSummaryNewFormFieldSet' => 'Field Set',
    'myShowcaseAdminSummaryNewFormFieldSetDescription' => 'Field set used to define the showcase data table.',

    'myShowcaseAdminSummaryEdit' => 'Edit Showcase',
    'myShowcaseAdminSummaryEditDescription' => 'Edit a new showcase.',
    'myShowcaseAdminSummaryEditFormDefaultListImage' => 'Default List Image ',
    'myShowcaseAdminSummaryEditFormDefaultListImageDescription' => 'This is the name of the image file (assuming relative to theme image folder) that is used as the default image for each record in the list view. Empty value will use View text as link.',
    'myShowcaseAdminSummaryEditFormWaterMarkImage' => 'Watermark Image',
    'myShowcaseAdminSummaryEditFormWaterMarkImageDescription' => 'Specify the path to the watermark image. If this is a valid file and the user chooses to watermark an attachment, this file will be used as the watermark. Applies to new image attachments only.',
    'myShowcaseAdminSummaryEditFormWaterMarkLocation' => 'Watermark Location',
    'myShowcaseAdminSummaryEditFormWaterMarkLocationDescription' => 'This setting is only applied to new image attachments. Existing images are not watermarked.',
    'myShowcaseAdminSummaryEditFormWaterMarkLocationUpperLeft' => 'Upper Left',
    'myShowcaseAdminSummaryEditFormWaterMarkLocationUpperRight' => 'Upper Right',
    'myShowcaseAdminSummaryEditFormWaterMarkLocationCenter' => 'Center',
    'myShowcaseAdminSummaryEditFormWaterMarkLocationLowerLeft' => 'Lower Left',
    'myShowcaseAdminSummaryEditFormWaterMarkLocationLowerRight' => 'Lower Right',
    'myShowcaseAdminSummaryEditFormListViewLink' => 'List View Link',
    'myShowcaseAdminSummaryEditFormListViewLinkDescription' => 'Enabled this feature if you want to replace the default image above with the first showcase attachment, if available. If you are experiencing slow performance, disable this feature.',
    'myShowcaseAdminSummaryEditFormListViewLinkUseAttach' => 'Replace Default List Image above with thumbnail of showcase entry',

    'myShowcaseAdminSummaryEditFormPruning' => 'Pruning',
    'myShowcaseAdminSummaryEditFormPruningDescription' => 'Prune entries when last edit is this long ago. Set to 0 to disable.',
    'myShowcaseAdminSummaryEditFormModerationOptions' => 'Moderation Options',
    'myShowcaseAdminSummaryEditFormModerationOptionsDescription' => 'These options control how the showcase is moderated.',
    'myShowcaseAdminSummaryEditFormModerationOptionsNewEdits' => 'Moderate new or edited entries.',
    'myShowcaseAdminSummaryEditFormTextTypeFields' => 'Text Type Fields',
    'myShowcaseAdminSummaryEditFormTextTypeFieldsDescription' => 'These fields are used to define the showcase data table.',
    'myShowcaseAdminSummaryEditFormTextTypeFieldsMaxCharacters' => 'Max characters for Text type fields.',
    'myShowcaseAdminSummaryEditFormAttachments' => 'Attachments',
    'myShowcaseAdminSummaryEditFormAttachmentsDescription' => 'These options control how attachments are handled.',
    'myShowcaseAdminSummaryEditFormAttachmentsAllow' => 'Allow Attachments',
    'myShowcaseAdminSummaryEditFormAttachmentsThumbnailWidth' => 'Thumbnail Width',
    'myShowcaseAdminSummaryEditFormAttachmentsThumbnailHeight' => 'Thumbnail Height',
    'myShowcaseAdminSummaryEditFormComments' => 'Comments',
    'myShowcaseAdminSummaryEditFormCommentsDescription' => 'These options control how comments are handled.',
    'myShowcaseAdminSummaryEditFormCommentsAllow' => 'Allow Comments',
    'myShowcaseAdminSummaryEditFormCommentsMaxCharacters' => 'Max characters for comments',
    'myShowcaseAdminSummaryEditParserOptions' => 'Parser Options',
    'myShowcaseAdminSummaryEditParserOptionsDescription' => 'These options control how the parser is used for both entries and comments.',
    'myShowcaseAdminSummaryEditParserOptionsAllowSmiles' => 'Allow Smiles in this showcase?',
    'myShowcaseAdminSummaryEditParserOptionsAllowMyCode' => 'Allow MyCode in this showcase?',
    'myShowcaseAdminSummaryEditParserOptionsAllowHtml' => 'Allow HTML in this showcase?',
    'myShowcaseAdminSummaryEditDisplaySettings' => 'Display Settings',
    'myShowcaseAdminSummaryEditDisplaySettingsDescription' => 'These options control how the showcase is displayed.',
    'myShowcaseAdminSummaryEditDisplaySettingsAttachmentColumns' => 'Number of attachments to display per row. Use 0 to allow browser to control.',
    'myShowcaseAdminSummaryEditDisplaySettingsCommentsPerPage' => 'Comments to display per page in entries.',
    'myShowcaseAdminSummaryEditDisplaySettingsDisplayEmptyFields' => 'Show all fields, even if empty, in showcase view.',
    'myShowcaseAdminSummaryEditDisplaySettingsDisplayInPosts' => 'Link this showcase in user details in posts.',
    'myShowcaseAdminSummaryEditDisplaySettingsDisplayRandomEntry' => 'Try to display a random entry in this showcase that has attachments on the portal.',
    'myShowcaseAdminSummaryEditParserOptionsAllowSignatures' => 'Display user signatures in this showcase?',
    'myShowcaseAdminSummaryEditModeratorPermissions' => 'Moderator Permissions',
    'myShowcaseAdminSummaryEditModeratorPermissionsDescription' => '',
    'myShowcaseAdminSummaryEditPermissionsGroup' => 'Group',
    'myShowcaseAdminSummaryEditPermissionsCanView' => 'Can View?',
    'myShowcaseAdminSummaryEditPermissionsCanAdd' => 'Can Add?',
    'myShowcaseAdminSummaryEditPermissionsCanEdit' => 'Can Edit?',
    'myShowcaseAdminSummaryEditPermissionsCanComment' => 'Can Comment?',
    'myShowcaseAdminSummaryEditPermissionsCanAttach' => 'Can Attach?',
    'myShowcaseAdminSummaryEditPermissionsCanDeleteOwnComment' => 'Can Delete Own Comments?',
    'myShowcaseAdminSummaryEditPermissionsCanDeleteAuthorComment' => 'Can Delete Author Comments?',
    'myShowcaseAdminSummaryEditPermissionsCanViewComments' => 'Can View Comments?',
    'myShowcaseAdminSummaryEditPermissionsCanViewAttachments' => 'Can View Attachments?',
    'myShowcaseAdminSummaryEditPermissionsCanSearch' => 'Can Search Showcases?',
    'myShowcaseAdminSummaryEditPermissionsCanWatermark' => 'Can Watermark Images?',
    'myShowcaseAdminSummaryEditPermissionsAttachmentLimit' => 'Max Attachments per showcase entry',
    'myShowcaseAdminSummaryEditModeratorPermissionsAssigned' => 'Moderators Assigned to "{1}"',
    'myShowcaseAdminSummaryEditModeratorPermissionsName' => 'Name',
    'myShowcaseAdminSummaryEditModeratorPermissionsCanApprove' => 'Can Approve?',
    'myShowcaseAdminSummaryEditModeratorPermissionsCanDelete' => 'Can Delete?',
    'myShowcaseAdminSummaryEditModeratorPermissionsCanEdit' => 'Can Edit?',
    'myShowcaseAdminSummaryEditModeratorPermissionsCanDeleteAuthorComment' => 'Can Delete Author Comments?',
    'myShowcaseAdminSummaryEditModeratorPermissionsControls' => 'Controls',
    'myShowcaseAdminSummaryEditModeratorPermissionsEmpty' => 'There are no moderators assigned.',
    'myShowcaseAdminSummaryEditModeratorPermissionsAddGroup' => 'Add a user group as Moderators',
    'myShowcaseAdminSummaryEditModeratorPermissionsAddGroupGroup' => 'User Group',
    'myShowcaseAdminSummaryEditModeratorPermissionsAddGroupGroupDescription' => 'Select a user group to add as a Moderator from the list below.',
    'myShowcaseAdminSummaryEditModeratorPermissionsCanApproveEntries' => 'Can approve entries in this showcase?',
    'myShowcaseAdminSummaryEditModeratorPermissionsCanEditEntries' => 'Can edit entries in this showcase?',
    'myShowcaseAdminSummaryEditModeratorPermissionsCanDeleteEntries' => 'Can delete entries in this showcase?',
    'myShowcaseAdminSummaryEditModeratorPermissionsCanDeleteComments' => 'Can delete comments in this showcase?',
    'myShowcaseAdminSummaryEditModeratorPermissionsAddUser' => 'Add a user as a Moderator',
    'myShowcaseAdminSummaryEditModeratorPermissionsAddUserUsername' => 'User',
    'myShowcaseAdminSummaryEditModeratorPermissionsAddUserUsernameDescription' => 'Username of the moderator to be added.',

    'myShowcaseAdminErrorInvalidShowcase' => 'Invalid showcase',
    'myShowcaseAdminErrorNoFieldSets' => 'Can not create a new showcase as there are no field sets defined.',
    'myShowcaseAdminErrorDuplicatedName' => 'The selected name is already in use.',
    'myShowcaseAdminErrorDuplicatedShowcaseSlug' => 'The selected showcase slug is already in use.',
    'myShowcaseAdminErrorDuplicatedMainFile' => 'The selected main file is already in use.',
    'myShowcaseAdminErrorTableDelete' => "This showcase's data table contains data and can not be deleted separately. If you wish to delete the populated table, delete the entire showcase.",
    'myShowcaseAdminErrorTableCreate' => 'Creating the showcase data table was unsuccessful.',
    'myShowcaseAdminErrorShowcaseDelete' => 'The showcase deletion failed.',
    'myShowcaseAdminErrorInvalidGroup' => 'Invalid user group',

    'myShowcaseAdminSuccessNewShowcase' => 'The showcase was successfully added.',
    'myShowcaseAdminSuccessEnabledShowcase' => 'The showcase was successfully enabled.',
    'myShowcaseAdminSuccessDisabledShowcase' => 'The showcase was successfully disabled.',
    'myShowcaseAdminSuccessTableCreated' => 'The showcase data table was successfully created.',
    'myShowcaseAdminSuccessTableRebuilt' => 'The showcase data table was successfully rebuilt.',
    'myShowcaseAdminSuccessTableDropped' => 'The showcase data table was successfully dropped.',
    'myShowcaseAdminSuccessShowcaseDeleted' => 'The showcase was successfully deleted.',
    'myShowcaseAdminSuccessShowcaseEditMain' => 'The showcase main settings were successfully edited.',
    'myShowcaseAdminSuccessShowcaseEditOther' => 'The showcase additional settings were successfully edited.',
    'myShowcaseAdminSuccessShowcaseEditPermissions' => 'The showcase permissions were successfully edited.',
    'myShowcaseAdminSuccessShowcaseEditModerators' => 'The showcase moderators were successfully edited.',
    'myShowcaseAdminSuccessShowcaseEditModeratorDelete' => 'The moderator was successfully removed.',

    'myShowcaseAdminConfirmTableDrop' => 'Are you sure you want to drop the table for the "{1}" showcase?',
    'myShowcaseAdminConfirmShowcaseDelete' => 'Are you sure you want to delete the "{1}" showcase? All comments, attachment references and showcase entries will be permanently deleted.',
    'myShowcaseAdminConfirmModeratorDelete' => 'Are you sure you want to remove this moderator?',

    'myShowcaseAdminSummaryViewRewrites' => 'View Rewrites',
    'myShowcaseAdminSummaryViewRewritesDescription' => 'If you are using the built-in MyBB SEO or other SEO plugin, you will need to enter the information below into your .htaccess file. These settings are specific to the "{1}" showcase. If there are other showcases, the SEO settings for those will need to be added as well. If the "{2}" showcase name is changed, these .htaccess settings will need to be updated. After changing the name, return to this page to obtain the new settings and update the .htaccess file as appropriate.<br /><br />',
    'myShowcaseAdminSummaryViewRewritesMain' => 'RewriteRule ^{1}\.html$ {2} [L,QSA]<br />',
    'myShowcaseAdminSummaryViewRewritesPage' => 'RewriteRule ^{1}-page-([0-9]+)\.html$ {2}?page=$1 [L,QSA]<br />',
    'myShowcaseAdminSummaryViewRewritesView' => 'RewriteRule ^{1}-view-([0-9]+)\.html$ {2}?action=view&entry_id=$1 [L,QSA]<br />',
    'myShowcaseAdminSummaryViewRewritesNew' => 'RewriteRule ^{1}-new\.html$ {2}?action=new [L,QSA]<br />',
    'myShowcaseAdminSummaryViewRewritesAttachment' => 'RewriteRule ^{1}-attachment-([0-9]+)\.html$ {2}?action=attachment&attachment_id=$1 [L,QSA]<br />',
    'myShowcaseAdminSummaryViewRewritesEntry' => 'RewriteRule ^{1}-item-([0-9]+)\.php$ {2}?action=item&attachment_id=$1 [L,QSA]<br />',

    'myShowcaseAdminUserGroup' => 'Usergroup',
], $l);
