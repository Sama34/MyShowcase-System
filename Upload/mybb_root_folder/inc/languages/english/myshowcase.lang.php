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

global $showcase_proper, $showcase_lower;

//$l['nav_myshowcase'] = $showcase_proper; //for breadcrumb
//$l['myshowcase'] = $showcase_proper; //for rest of myshowcase
$l['myshowcase_specifications'] = 'Specs';
$l['latest_myshowcases'] = "Latest {$showcase_proper}";
$l['myshowcase_showcase'] = 'Showcase';

//headerinclude / JS confirmations
$l['removeshowcase_confirm'] = 'Are you sure you want to remove the selected entry and all of its comments and attachments?';
$l['removeshowcaseattach_confirm'] = 'Are you sure you want to remove the selected attachment from this entry?';
$l['removeshowcasecomment_confirm'] = 'Are you sure you want to remove the selected comment from this entry?';

//basic headers for index table
$l['myshowcase_view'] = 'View';
$l['myshowcase_member'] = 'Member';
$l['myshowcase_lastedit'] = 'Last Updated';
$l['myshowcase_views'] = 'Views';
$l['myshowcase_comments'] = 'Comments';

//error messages
$l['myshowcase_disabled'] = 'This system is disabled.';
$l['myshowcase_not_authorized'] = 'You are not authorized to perform that action.';
$l['myshowcase_comment_error'] = 'The attempted action failed.';
$l['myshowcase_db_no_data'] = 'No results returned for table lookup. Data is missing.';
$l['myshowcase_feature_disabled'] = 'This feature is disabled.';
$l['myshowcase_comment_empty'] = 'The data submitted contains no text.';

//moderation words
$l['myshowcase_last_approved'] = 'Last Approved By';
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
$l['myshowcase_unapproved_count'] = 'The {name} Showcase has {num} unapproved entries.';
$l['myshowcase_unapproved_exist_title'] = 'Unapproved Entries';
$l['myshowcase_unapproved_link'] = 'Click to view unapproved.';

//sorting words
$l['myshowcase_sort_by'] = 'Sort By:';
$l['myshowcase_sort_createdate'] = 'Creation Date';
$l['myshowcase_sort_editdate'] = 'Edited Date';
$l['myshowcase_sort_username'] = 'Username';
$l['myshowcase_sort_views'] = '#Views';
$l['myshowcase_sort_comments'] = '#Comments';
$l['myshowcase_sort_asc'] = 'Ascending';
$l['myshowcase_sort_desc'] = 'Descending';
$l['myshowcase_order'] = 'Order By:';
$l['myshowcase_search'] = 'Search:';
$l['myshowcase_for'] = 'for:';
$l['myshowcase_sort_in'] = 'Direction:';

$l['myshowcase_asc'] = 'asc';
$l['myshowcase_desc'] = 'desc';

$l['myshowcase_exact_match'] = 'exact match';

//query results
$l['myshowcase_empty'] = 'There are no entries at this time.';
$l['myshowcase_no_results'] = 'The query returned no results. Please try another search term.';
$l['myshowcase_invalid_id'] = 'The specified Entry ID is invalid.';
$l['myshowcase_invalid_cid'] = 'The specified Comment ID is invalid.';
$l['myshowcase_invalid_aid'] = 'The specified Attachment ID is invalid.';
$l['myshowcase_not_specified'] = 'Not Specified';

//view options
$l['myshowcase_view_user'] = 'View this entry of &quot;{username}&quot;';
$l['myshowcase_viewing_user'] = 'Viewing entry for &quot;{username}&quot;';
$l['myshowcase_viewing_attachment'] = 'Viewing Attachment of &quot;{username}&quot;';

//new/edit myshowcase
$l['myshowcase_new'] = 'Post New';
$l['myshowcase_post'] = 'Post';
$l['myshowcase_edit_user'] = 'Edit entry of &quot;{username}&quot;';
$l['myshowcase_editing_user'] = 'Editing entry of &quot;{username}&quot;';
$l['myshowcase_editing_number'] = "<span class=\"smalltext\">This field is going to be formatted as numeric. Non-numeric content will result in a zero value.</span>";
$l['myshowcase_watermark'] = 'Watermark?';

//report
$l['myshowcase_report'] = 'Report Entry';
$l['myshowcase_report_label'] = 'Report this entry to a moderator';
$l['myshowcase_report_warning'] = 'The report system is only for reporting spam, abuse, or offensive entries.';
$l['myshowcase_report_success'] = 'Thank you, Your report has been recieved.';
$l['myshowcase_report_error'] = 'There was an error submitting your report. Please go back and try again';
$l['myshowcase_report_reason'] = 'Your reason for reporting this entry:';
$l['myshowcase_report_count'] = 'The {name} Showcase has {num} reported entries.';
$l['myshowcase_report_exist_title'] = 'Reported Entries';
$l['myshowcase_report_link'] = 'Click to view reported entries.';
$l['myshowcase_reports'] = 'Reports';
$l['myshowcase_reports_entryid'] = 'Entry ID';
$l['myshowcase_reports_showcaseid'] = 'Showcase ID';
$l['myshowcase_reports_showcase_name'] = 'Showcase';
$l['myshowcase_reports_reason'] = 'Reason';
$l['myshowcase_reports_reportedby'] = 'Reported By';
$l['myshowcase_reports_dateline'] = 'Reported At';
$l['myshowcase_report_item'] = "The above entry was reported {1} by {2} with reason: \"{3}\"";
$l['myshowcase_reports_note'] = 'Reported Showcases';

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

//comment language
$l['myshowcase_comments_add'] = 'Add a comment';
$l['myshowcase_comments_not_logged_in'] = 'You must be logged in to add a comment';
$l['myshowcase_comments_none'] = 'There are no comments at this time.';
$l['myshowcase_posted_at'] = 'posted the following comment at';
$l['myshowcase_comment_edit'] = 'Edit';
$l['myshowcase_comment_delete'] = 'Delete';
$l['myshowcase_comment_show_all'] = 'Show all {count} comments';
$l['myshowcase_comment_text_limit'] = '({text_limit} char max. No HTML. MyCodes and smilies allowed.)';
$l['myshowcase_comment_deleted'] = 'The selected comment has been deleted.<br>You will now be redirected back to where you came from.';
$l['myshowcase_comment_added'] = 'Your comment has been added.<br>You will now be redirected back to where you came from.';

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

$l['myshowcase_month'] = 'M:';
$l['myshowcase_day'] = 'D:';
$l['myshowcase_year'] = 'Y:';

//who's onine and portal
$l['latest_myshowcases'] = 'Latest Showcases';

$l['viewing_myshowcase_list'] = "Viewing <a href=\"../{1}\">{2} List</a>";
$l['viewing_myshowcase'] = "Viewing <a href=\"../{1}\">{2} entry</a> of <a href=\"{3}\">{4}</a>";
$l['viewing_myshowcase_new'] = "Creating <a href=\"../{1}\">New {2}</a>";
$l['viewing_myshowcase_edit'] = 'Editing a {1} entry';
$l['viewing_myshowcase_attach'] = "Viewing <a href=\"../{1}\">Attachment</a> in a <a href=\"../{2}\">{3} entry</a> of <a href=\"{4}\">{5}</a>";
?>
