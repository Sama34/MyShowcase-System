<?php
/**
 * MyShowcase Plugin for MyBB - Language file for ACP, MyShowcase Edit
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\languages\<language>\admin\myshowcase_edit.lang.php
 *
 */

//main words
$l['myshowcase_edit_title'] = 'Editing Showcase';

$l['myshowcase_edit_id'] = 'ID';
$l['myshowcase_edit_name'] = 'Name';
$l['myshowcase_edit_description'] = 'Description';

$l['myshowcase_edit_image_folder'] = 'Image Folder<br />(relative to Main File)';
$l['myshowcase_edit_main_file'] = 'Main File';
$l['myshowcase_edit_forum_folder'] = 'Path from Forum<br />(relative to index.php)';
$l['myshowcase_edit_field_set'] = 'Field Set';

$l['myshowcase_edit_prunetime'] = 'Prune entries when last edit is this long ago. Set to 0 to disable.';

//confirmations
$l['myshowcase_edit_save_changes'] = 'Save Changes';
$l['myshowcase_edit_save_main'] = 'Save Main Settings';
$l['myshowcase_edit_save_other'] = 'Save Other Settings';
$l['myshowcase_edit_save_perms'] = 'Save Permissions';
$l['myshowcase_edit_save_modperms'] = 'Save Moderator Permissions';

$l['myshowcase_edit_invalid_id'] = 'Invalid Showcase ID provided.';

//permissions
$l['myshowcase_group'] = 'Group';
$l['myshowcase_canview'] = 'Can View?';
$l['myshowcase_canadd'] = 'Can Add?';
$l['myshowcase_canedit'] = 'Can Edit?';
$l['myshowcase_canapprove'] = 'Can Approve?';
$l['myshowcase_candelete'] = 'Can Delete?';
$l['myshowcase_cancomment'] = 'Can Comment?';
$l['myshowcase_canattach'] = 'Can Attach?';
$l['myshowcase_candelowncomment'] = 'Can Delete Own Comments?';
$l['myshowcase_candelauthcomment'] = 'Can Delete Author Comments?';
$l['myshowcase_canviewcomment'] = 'Can View Comments?';
$l['myshowcase_canviewattach'] = 'Can View Attachments?';
$l['myshowcase_cansearch'] = 'Can Search Showcases?';
$l['myshowcase_canwatermark'] = 'Can Watermark Images?';
$l['myshowcase_attachlimit'] = 'Max Attachments per Showcase<br />(-1 for unlimited)';

//moderators
$l['myshowcase_moderators_assigned'] = 'Additional moderators assigned to this showcase.';
$l['myshowcase_moderators_name'] = 'Name';
$l['myshowcase_moderators_controls'] = 'Controls';
$l['myshowcase_moderators_group'] = 'Usergroup';
$l['myshowcase_moderators_user'] = 'Username';
$l['myshowcase_moderators_edit'] = 'Edit';
$l['myshowcase_moderators_delete'] = 'Delete';
$l['myshowcase_moderators_confirm_deletion'] = 'Are you sure you want to remove this moderator?';
$l['myshowcase_moderators_none'] = 'There are no additional moderators assigned.';
$l['myshowcase_moderator_usergroup_desc'] = 'Title of the usergroup to be added.';
$l['myshowcase_moderator_username_desc'] = 'Username of the moderator to be added.';
$l['myshowcase_add_usergroup_moderator'] = 'Add Usergroup Moderator';
$l['myshowcase_add_user_moderator'] = 'Add User Moderator';
$l['myshowcase_add_usergroup_as_moderator'] = 'Add a usergroup as Moderators';
$l['myshowcase_add_user_as_moderator'] = 'Add a user as a Moderator';

$l['myshowcase_edit_modmatcherror'] = 'The Showcase ID listed for specified moderator ID does not match the current Showcase ID';
$l['myshowcase_edit_modcanapprove'] = 'Can approve entries in this showcase?';
$l['myshowcase_edit_modcanedit'] = 'Can edit entries in this showcase?';
$l['myshowcase_edit_modcandelete'] = 'Can delete entries in this showcase (includes removing comments and attachments upon delete)?';
$l['myshowcase_edit_modcandelcomment'] = 'Can delete comments in this showcase?';

$l['myshowcase_mod_invalid'] = 'Invalid input';

//messages
$l['myshowcase_edit_missing_action'] = 'No Action specified. Please select Edit from the Summary page options. Direct call of this page not allowed.';
$l['myshowcase_edit_no_edit_set'] = "This showcase's data table already exists and<br />thus changes to the fieldset will not be applied.";

$l['myshowcase_edit_success'] = 'The showcase was successfully edited.';
$l['myshowcase_edit_failed'] = 'The showcase edit failed or did not affect any records.';

$l['myshowcase_mod_delete_error'] = 'Removing the specified moderator failed.';
$l['myshowcase_mod_delete_success'] = 'The moderator was successfully removed.';

$l['myshowcase_edit_delete_success'] = 'The showcase was successfully deleted.';
$l['myshowcase_edit_delete_failed'] = 'The showcase deletion failed.';
$l['myshowcase_edit_confirm_delete_long'] = 'Are you sure you want to delete this Showcase? All comments, attachment references and showcase entries will be permantently deleted.';
$l['myshowcase_edit_confirm_delete'] = 'Confirm Delete';

$l['myshowcase_edit_modnewedit'] = 'Moderate new or edited entries.';
$l['myshowcase_edit_othermaxlength'] = 'Max characters for Text type fields';
$l['myshowcase_edit_allow_attachments'] = 'Allow Attachments';
$l['myshowcase_edit_allow_comments'] = 'Allow Comments';
$l['myshowcase_edit_thumb_width'] = 'Thumbnail width';
$l['myshowcase_edit_thumb_height'] = 'Thumbnail height';
$l['myshowcase_edit_num_attachmod'] = 'Number of attachments for Moderator groups';
$l['myshowcase_edit_num_attachspcl'] = 'Number of attachments for Special groups';
$l['myshowcase_edit_num_attachreg'] = 'Number of attachments for Regular groups';
$l['myshowcase_edit_groups_mods'] = 'Group IDs for Moderator privledges. Comma separated.';
$l['myshowcase_edit_groups_spcl'] = 'Group IDs for Special privledges. Comma separated.';
$l['myshowcase_edit_groups_reg'] = 'Group IDs for Regular privledges. Comma separated.';
$l['myshowcase_edit_comment_length'] = 'Max characters for Comments';
$l['myshowcase_edit_comment_dispinit'] = 'Initial number of comments to display in each showcase.';
$l['myshowcase_edit_comment_authdel'] = 'Allow comment author to delete his/her comments.';
$l['myshowcase_edit_comment_ownerdel'] = 'Allow showcase owner to delete comments in his/her showcase.';
$l['myshowcase_edit_disp_attachcols'] = 'Number of attachments to display per row. Use 0 to allow browser to control.';
$l['myshowcase_edit_disp_empty'] = 'Show all fields, even if empty, in showcase view.';
$l['myshowcase_edit_link_in_postbit'] = 'Link this showcase in user details of postbit.';
$l['myshowcase_edit_portal_random'] = 'Try to display a random entry in this showcase that has attachments on the portal.';

$l['myshowcase_edit_allow_smilies'] = 'Allow Smilies in this showcase?';
$l['myshowcase_edit_allow_bbcode'] = 'Allow BBCode in this showcase?';
$l['myshowcase_edit_allow_html'] = 'Allow HTML in this showcase? (unchecked is highly recommended)';

$l['myshowcase_lower_left'] = 'Lower Left';
$l['myshowcase_lower_right'] = 'Lower Right';
$l['myshowcase_center'] = 'Center';
$l['myshowcase_upper_left'] = 'Upper Left';
$l['myshowcase_upper_right'] = 'Upper Right';

?>
