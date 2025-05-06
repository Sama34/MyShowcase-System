<?php
/**
 * MyShowcase Plugin for MyBB - English Language File
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\langauges\<language>\myshowcase.lang.php
 *
 */

declare(strict_types=1);

global $showcaseName, $showcase_lower;

//$l['nav_myshowcase'] = $showcaseName; //for breadcrumb
//$l['myshowcase'] = $showcaseName; //for rest of myshowcase
$l['myshowcase_specifications'] = 'Entry Details';
$l['latest_myshowcases'] = "Latest {$showcaseName}";
$l['myshowcase_showcase'] = 'Showcase';

//headerinclude / JS confirmations
$l['removeshowcase_confirm'] = 'Are you sure you want to remove the selected entry and all of its comments and attachments?';
$l['removeshowcaseattach_confirm'] = 'Are you sure you want to remove the selected attachment from this entry?';
$l['removeshowcasecomment_confirm'] = 'Are you sure you want to remove the selected comment from this entry?';

//basic headers for index table
$l['myShowcaseMainTableTheadView'] = 'View';
$l['myShowcaseMainTableTheadAuthor'] = 'Author';
$l['myShowcaseMainTableTheadComments'] = 'Comments';
$l['myShowcaseMainTableTheadViews'] = 'Views';
$l['myShowcaseMainTableTheadLastEdit'] = 'Last Updated';

$l['myShowcaseMainTableRowLastComment'] = 'Last comment';

//error messages
$l['myshowcase_disabled'] = 'This system is disabled.';
$l['myshowcase_not_authorized'] = 'You are not authorized to perform that action.';
$l['myshowcase_comment_error'] = 'The attempted action failed.';
$l['myshowcase_db_no_data'] = 'No results returned for table lookup. Data is missing.';
$l['myshowcase_feature_disabled'] = 'This feature is disabled.';
$l['myshowcase_comment_empty'] = 'The data submitted contains no text.';

//moderation words
$l['myshowcase_inline_moderation'] = 'Inline Moderation';
$l['myshowcase_mod_approve'] = 'Approve Entry';
$l['myshowcase_mod_unapprove'] = 'Unapprove Entry';
$l['myshowcase_mod_delete'] = 'Delete Entry';
$l['myshowcase_inline_go'] = 'Go';
$l['myshowcase_clear'] = 'Clear';
$l['myshowcase_no_myshowcaseselected'] = 'There are no selected entries';
$l['myshowcase_nav_multidelete'] = 'Inline Deletion';
$l['myshowcase_edit'] = 'Edit';
$l['myshowcase_save'] = 'Save';
$l['myshowcase_delete'] = 'Delete';
$l['myshowcase_delete_multi'] = 'Delete Entries';
$l['confirm_delete_myshowcases'] = 'Are you sure you wish to delete the selected entry? Once an entry has been deleted it cannot be restored and any attachments and comment within that entry are also deleted.';
$l['myshowcase_unapproved_count'] = 'The {1} Showcase has {2} unapproved entries.';
$l['myshowcase_unapproved_exist_title'] = 'Unapproved Entries';
$l['myshowcase_unapproved_link'] = 'Click to view unapproved.';

//sorting words
$l['myShowcaseMainSelectSortBy'] = 'Sort By:';
$l['myShowcaseMainSortDateline'] = 'Creation Date';
$l['myShowcaseMainSortEditDate'] = 'Edited Date';
$l['myShowcaseMainSortUsername'] = 'Username';
$l['myShowcaseMainSortViews'] = 'Views';
$l['myShowcaseMainSortComments'] = 'Comments';
$l['myShowcaseMainSelectAscending'] = 'Ascending';
$l['myShowcaseMainSelectDescending'] = 'Descending';
$l['myshowcase_order'] = 'Order By:';
$l['myShowcaseMainSearch'] = 'Search:';
$l['myShowcaseMainSearchFor'] = 'for:';
$l['myShowcaseMainSelectOrderDirection'] = 'Direction:';

$l['myshowcase_asc'] = 'asc';
$l['myshowcase_desc'] = 'desc';

$l['myShowcaseMainSearchMatch'] = 'exact match';

//query results
$l['myShowcaseMainTableEmpty'] = 'There are no entries at this time.';
$l['myShowcaseMainTableEmptySearch'] = 'The search returned no results. Please try another search term.';
$l['myshowcase_invalid_id'] = 'The specified Entry ID is invalid.';
$l['myshowcase_invalid_comment_id'] = 'The specified Comment ID is invalid.';
$l['myshowcase_invalid_aid'] = 'The specified Attachment ID is invalid.';
$l['myshowcase_not_specified'] = 'Not Specified';

//view options
$l['myshowcase_view_user'] = 'View this entry of &quot;{username}&quot;';
$l['myshowcase_viewing_user'] = 'Viewing entry for &quot;{username}&quot;';
$l['myshowcase_viewing_attachment'] = 'Viewing Attachment of &quot;{username}&quot;';

//new/edit myshowcase
$l['myShowcaseButtonEntryCreate'] = 'Create Entry';
$l['myShowcaseButtonEntryUpdate'] = 'Update Entry';
$l['myShowcaseButtonCommentCreate'] = 'Create Comment';
$l['myShowcaseButtonCommentUpdate'] = 'Update Comment';
$l['myshowcase_post'] = 'Post';
$l['myshowcase_edit_user'] = 'Edit entry of &quot;{username}&quot;';
$l['myshowcase_editing_user'] = 'Editing entry';
$l['myshowcase_editing_number'] = "<span class=\"smalltext\">This field is going to be formatted as numeric. Non-numeric content will result in a zero value.</span>";
$l['myshowcase_watermark'] = 'Watermark?';

//redirects
$l['redirect_myshowcase_return'] = "<br /><br />Alternatively, <a href=\"{1}\">return to the list</a>.";
$l['redirect_myshowcase_new'] = 'Thank you, your entry has been successfully built.';
$l['redirect_myshowcase'] = '<br />You will now be taken to your entry.';
$l['redirect_myshowcase_approve'] = 'The selected entries have been approved.';
$l['redirect_myshowcase_unapprove'] = 'The selected entries have been unapproved.';
$l['redirect_myshowcase_delete'] = 'The selected entries have been deleted.';
$l['redirect_myshowcase_back'] = 'You will now be redirected back to your original location.';

//attachments
$l['myshowcase_unlimited'] = 'unlimited';
$l['myshowcase_attachments'] = 'Attachments';
$l['myshowcase_new_attachments'] = "Optionally you may attach one or more attachments to this showcase. Please select the file and click 'Add Attachment' to upload it.";
$l['myshowcase_add_attachments'] = 'Add Attachment';
$l['myshowcase_attach_quota'] = 'You are currently using <strong>{1}</strong> of your allowable images ({2})';
$l['myshowcase_attachments_none'] = 'There are no attachments at this time.';
$l['myshowcase_attachment_alt'] = 'Attachment {1} of {2}';
$l['myshowcase_attachment_uploaded'] = '<strong>Date Uploaded:</strong> ';
$l['myshowcase_attachment_filename'] = '<strong>Filename:</strong> ';

$l['myshowcase_entry_approved'] = 'The entry has been approved.<br>You will now be redirected back to where you came from.';
$l['myshowcase_entry_unapproved'] = 'The entry has been unapproved.<br>You will now be redirected back to where you came from.';

//comment language
$l['myshowcase_comments_add'] = 'Add a comment';
$l['myshowcase_comments_not_logged_in'] = 'You must be logged in to add a comment';
$l['myshowcase_comments_none'] = 'There are no comments at this time.';
$l['myshowcase_posted_at'] = 'posted the following comment at';
$l['myshowcase_comment_edit'] = 'Edit';
$l['myshowcase_comment_delete'] = 'Delete';
$l['myshowcase_comment_soft_delete'] = 'Soft Delete';
$l['myshowcase_comment_restore'] = 'Restore';
$l['myshowcase_comment_approve'] = 'Approve';
$l['myshowcase_comment_unapprove'] = 'Unapprove';
$l['myshowcase_comment_show_all'] = 'Show all {count} comments';
$l['myshowcase_comment_text_limit'] = '{1} char max. No HTML. MyCodes and smilies allowed.';
$l['myshowcase_comment_deleted'] = 'The selected comment has been deleted.<br>You will now be redirected back to where you came from.';
$l['myshowcase_comment_added'] = 'Your comment has been added.<br>You will now be redirected back to where you came from.';
$l['myshowcase_comment_approved'] = 'The comment has been approved.<br>You will now be redirected back to where you came from.';
$l['myshowcase_comment_unapproved'] = 'The comment has been unapproved.<br>You will now be redirected back to where you came from.';
$l['myshowcase_comment_soft_deleted'] = 'The comment has been soft deleted.<br>You will now be redirected back to where you came from.';
$l['myshowcase_comment_deleted'] = 'The comment has been deleted.<br>You will now be redirected back to where you came from.';

$l['myshowcase_comment_more'] = '... (visit the entry to read more..)';

$l['myshowcase_comment_emailsubject'] = 'New comment to your {1}';

$l['myshowcase_comment_email'] = "{1},

{2} has just commented on your entry in '{3}'. 

Here is an excerpt of the message:
--
{4}
--

To view the comment, you can go to the following URL:
{5}

Thank you,
{6} Staff

------------------------------------------
Unsubscription Information:

If you would not like to receive any more notifications of new comments to your entries, visit the following URL in your browser and uncheck Receive emails from the Administrators:
{7}/usercp.php?action=options

------------------------------------------";

//misc
$l['myshowcase_from'] = 'From';
$l['myshowcase_jumpto'] = 'Jump to:';
$l['myshowcase_top'] = 'Goto Top';
$l['myshowcase_cancel'] = 'Cancel';

$l['myshowcase_day'] = 'Day';
$l['myshowcase_month'] = 'Month';
$l['myshowcase_year'] = 'Year';

//who's onine and portal
$l['latest_myshowcases'] = 'Latest Showcases';

$l['viewing_myshowcase_list'] = "Viewing <a href=\"../{1}\">{2} List</a>";
$l['viewing_myshowcase'] = "Viewing <a href=\"../{1}\">{2} entry</a> of <a href=\"{3}\">{4}</a>";
$l['viewing_myshowcase_new'] = "Creating <a href=\"../{1}\">New {2}</a>";
$l['viewing_myshowcase_edit'] = 'Editing a {1} entry';
$l['viewing_myshowcase_attach'] = "Viewing <a href=\"../{1}\">Attachment</a> in a <a href=\"../{2}\">{3} entry</a> of <a href=\"{4}\">{5}</a>";

$l = array_merge($l, [
    'myShowcaseNewEditFormTitle' => 'New Entry',
    'myShowcaseNewEditFormField' => 'Field',
    'myShowcaseNewEditFormValue' => 'Value',

    'myShowcaseNewEditFormButtonCreateEntry' => 'Post Entry',
    'myShowcaseNewEditFormButtonUpdate' => 'Update Entry',
    'myShowcaseNewEditFormButtonPreview' => 'Preview Entry',

    'myShowcaseEntryFieldValueEmpty' => '(no value)',
    'myShowcaseEntryFieldValueCheckBoxYes' => 'Yes',
    'myShowcaseEntryFieldValueCheckBoxNo' => 'No',

    'myShowcaseEntryModeratedBy' => 'Moderated by',

    'myShowcaseEntryAttachmentsFiles' => 'Attached Files',
    'myShowcaseEntryAttachmentsImages' => 'Image(s)',
    'myShowcaseEntryAttachmentsThumbnail' => 'Thumbnail(s)',

    'myShowcaseEntryButtonEdit' => 'Edit',
    'myShowcaseEntryButtonEditDescription' => 'Edit this entry',
    'myShowcaseEntryButtonWarn' => 'Warn',
    'myShowcaseEntryButtonWarnDescription' => 'Warn the author for this entry',
    'myShowcaseEntryButtonPurseSpammer' => 'Purge Spammer',
    'myShowcaseEntryButtonPurseSpammerDescription' => 'Purge spammer',
    'myShowcaseEntryButtonReport' => 'Report',
    'myShowcaseEntryButtonReportDescription' => 'Report comment',
    'myShowcaseEntryButtonApprove' => 'Approve',
    'myShowcaseEntryButtonApproveDescription' => 'Approve entry',
    'myShowcaseEntryButtonApproveConfirm' => 'Are you sure you want to approve this entry?',
    'myShowcaseEntryButtonUnapprove' => 'Unapprove',
    'myShowcaseEntryButtonUnapproveDescription' => 'Unapprove entry',
    'myShowcaseEntryButtonUnapproveConfirm' => 'Are you sure you want to unapprove this entry?',
    'myShowcaseEntryButtonSoftDelete' => 'Soft Delete',
    'myShowcaseEntryButtonSoftDeleteDescription' => 'Soft delete entry',
    'myShowcaseEntryButtonSoftDeleteConfirm' => 'Are you sure you want to soft delete this entry?',
    'myShowcaseEntryButtonRestore' => 'Restore',
    'myShowcaseEntryButtonRestoreDescription' => 'Restore entry',
    'myShowcaseEntryButtonRestoreConfirm' => 'Are you sure you want to restore this entry?',

    'myShowcaseEntryCommentOnlineStatusOnline' => 'Online',
    'myShowcaseEntryCommentOnlineStatusOffline' => 'Offline',
    'myShowcaseEntryCommentOnlineStatusAway' => 'Away',
    'myShowcaseEntryCommentDetailsPosts' => 'Posts',
    'myShowcaseEntryCommentDetailsThreads' => 'Threads',
    'myShowcaseEntryCommentDetailsJoined' => 'Joined',
    'myShowcaseEntryCommentDetailsReputation' => 'Reputation:',
    'myShowcaseEntryCommentDetailsWarningLevel' => 'Warning Level:',
    'myShowcaseEntryCommentButtonEmail' => 'Email',
    'myShowcaseEntryCommentButtonEmailDescription' => 'Send this user an email',
    'myShowcaseEntryCommentButtonPrivateMessage' => 'PM',
    'myShowcaseEntryCommentButtonPrivateMessageDescription' => 'Send this user a private message',
    'myShowcaseEntryCommentButtonWebsite' => 'Website',
    'myShowcaseEntryCommentButtonWebsiteDescription' => "Visit this user's website",

    'myShowcaseEntryCommentButtonEdit' => 'Edit',
    'myShowcaseEntryCommentButtonEditDescription' => 'Edit this comment',
    'myShowcaseEntryCommentButtonWarn' => 'Warn',
    'myShowcaseEntryCommentButtonWarnDescription' => 'Warn the author for this comment',
    'myShowcaseEntryCommentButtonPurseSpammer' => 'Purge Spammer',
    'myShowcaseEntryCommentButtonPurseSpammerDescription' => 'Purge spammer',
    'myShowcaseEntryCommentButtonReport' => 'Report',
    'myShowcaseEntryCommentButtonReportDescription' => 'Report comment',
    'myShowcaseEntryCommentButtonApprove' => 'Approve',
    'myShowcaseEntryCommentButtonApproveDescription' => 'Approve comment',
    'myShowcaseEntryCommentButtonApproveConfirm' => 'Are you sure you want to approve this comment?',
    'myShowcaseEntryCommentButtonUnapprove' => 'Unapprove',
    'myShowcaseEntryCommentButtonUnapproveDescription' => 'Unapprove comment',
    'myShowcaseEntryCommentButtonUnapproveConfirm' => 'Are you sure you want to unapprove this comment?',
    'myShowcaseEntryCommentButtonSoftDelete' => 'Soft Delete',
    'myShowcaseEntryCommentButtonSoftDeleteDescription' => 'Soft delete comment',
    'myShowcaseEntryCommentButtonSoftDeleteConfirm' => 'Are you sure you want to soft delete this comment?',
    'myShowcaseEntryCommentButtonRestore' => 'Restore',
    'myShowcaseEntryCommentButtonRestoreDescription' => 'Restore comment',
    'myShowcaseEntryCommentButtonRestoreConfirm' => 'Are you sure you want to restore this comment?',

    'myShowcaseEntryCommentCreateTitle' => 'Entry Comment',
    'myShowcaseEntryCommentCreateMessage' => 'Message:',
    'myShowcaseEntryCommentCreateMessageNote' => 'Type your comment to this entry here.',
    'myShowcaseEntryCommentCreateButton' => 'Post Comment',

    'myShowcaseEntryCommentCreated' => 'Your comment has been created successfully.<br />You will now be returned back to where you came from.',
    'myShowcaseEntryCommentUpdated' => 'Your comment has been updated successfully.<br />You will now be returned back to where you came from.',

    'myShowcaseEntryEntryCreated' => 'Your entry has been created successfully.<br />You will now be redirected to the entry.',
    'myShowcaseEntryEntryCreatedStatus' => 'Your entry has been created successfully.<br />You will now be returned back to where you came from.',
    'myShowcaseEntryEntryUpdated' => 'Your entry has been updated successfully.<br />You will now be redirected to the entry.',
    'myShowcaseEntryEntryUpdatedStatus' => 'Your entry has been updated successfully.<br />You will now be returned back to where you came from.',
    'myShowcaseEntryEntryApproved' => 'Your entry has been approved successfully.<br />You will now be returned back to where you came from.',
    'myShowcaseEntryEntryUnapproved' => 'The entry has been unapproved successfully.<br />You will now be returned back to where you came from.',
    'myShowcaseEntryEntrySoftDeleted' => 'The entry has been soft deleted successfully.<br />You will now be returned back to where you came from.',
    'myShowcaseEntryEntryDeleted' => 'The entry has been deleted successfully.<br />You will now be returned back to where you came from.',

    'myShowcaseEntryCommentApproved' => 'Your comment has been approved successfully.<br />You will now be returned back to where you came from.',
    'myShowcaseEntryCommentUnapproved' => 'The comment has been unapproved successfully.<br />You will now be returned back to where you came from.',
    'myShowcaseEntryCommentSoftDeleted' => 'The comment has been soft deleted successfully.<br />You will now be returned back to where you came from.',
    'myShowcaseEntryCommentDeleted' => 'The comment has been deleted successfully.<br />You will now be returned back to where you came from.',

    'myShowcaseReportCommentInvalid' => 'This comment either does not exist or is not allowed to be reported.',
    'myShowcaseReportCommentContent' => '<a href="{1}">Comment</a> from {2}',
    'myShowcaseReportCommentContentEntryUser' => '<br /><span class="smalltext">On {1}\'s entry</span>',

    'myShowcaseReportEntryInvalid' => 'This entry either does not exist or is not allowed to be reported.',
    'myShowcaseReportEntryContent' => '<a href="{1}">Entry</a> from {2}',
]);
