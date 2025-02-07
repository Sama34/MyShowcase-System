<?php
/**
 * MyShowcase Plugin for MyBB - Code for New and Edit
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\plugins\myshowcase\newedit.php
 *
 */

declare(strict_types=1);

use function MyShowcase\Core\getTemplate;

global $mybb, $lang, $db, $templates, $plugins;
global $me, $showcase_fields_max_length, $showcase_fields_format, $showcase_fields_require, $showcase_fields_min_length, $showcase_url;

$showcase_page = '';

switch ($mybb->get_input('action')) {
    case 'edit':
    {
        if (!$mybb->user['uid'] || !$me->userperms['canedit']) {
            error($lang->myshowcase_not_authorized);
        }

        $mybb->input['gid'] = intval($mybb->get_input('gid', MyBB::INPUT_INT));

        if (!$mybb->get_input('gid', MyBB::INPUT_INT) || $mybb->get_input('gid', MyBB::INPUT_INT) == '') {
            error($lang->myshowcase_invalid_id);
        }

        $query = $db->simple_select($me->table_name, '*', 'gid=' . $mybb->get_input('gid', MyBB::INPUT_INT));
        if ($db->num_rows($query) == 0) {
            error($lang->myshowcase_invalid_id);
        }

        $showcase_data = $db->fetch_array($query);

        //make sure current user is moderator or the myshowcase author
        if (!$me->userperms['canmodedit'] && $mybb->user['uid'] != $showcase_data['uid']) {
            error($lang->myshowcase_not_authorized);
        }

        //since its possible for a mod to edit another user's showcase, we need to get authors info/permimssions
        //get showcase author info
        $showcase_user = get_user($showcase_data['uid']);

        //set value for author id in form hidden fields so we know if current user is author
        $showcase_authid = $showcase_user['uid'];

        //get permissions for user
        $showcase_authorperms = $me->get_user_permissions($showcase_user);

        $mybb->input['posthash'] = $showcase_data['posthash'];
        $posthash = $mybb->get_input('posthash');
        //no break since edit will share NEW code
    }
    case 'new':
    {
        $can_add_attachments = $me->userperms['canattach'];
        $attach_limit = $me->userperms['attachlimit'];

        if ($mybb->get_input('action') == 'new') {
            add_breadcrumb($lang->myshowcase_new, SHOWCASE_URL);
            $showcase_action = 'do_newshowcase';
        } elseif ($mybb->get_input('action') == 'edit') {
            $showcase_editing_user = str_replace(
                '{username}',
                $showcase_user['username'],
                $lang->myshowcase_editing_user
            );
            add_breadcrumb($showcase_editing_user, SHOWCASE_URL);
            $showcase_action = 'do_editshowcase';

            //if editing, adjust permissions depending on mod or actual author
            if (is_array($showcase_authorperms)) {
                $can_add_attachments = $showcase_authorperms['canattach'];
                $attach_limit = $showcase_authorperms['attachlimit'];
            }
        }

        if (($mybb->get_input('action') == 'new' && $me->userperms['canadd']) || ($mybb->get_input(
                    'action'
                ) == 'edit' && ($me->userperms['canedit'] || $me->userperms['canmodedit']))) {
            $plugins->run_hooks('myshowcase_editnew_start');

            // Setup our posthash for managing attachments.
            if (!$mybb->get_input('posthash')) {
                mt_srand((int)(microtime() * 1000000));
                $mybb->input['posthash'] = md5($mybb->user['uid'] . mt_rand());
            }
            $posthash = $mybb->get_input('posthash');

            $attacherror = $showcase_attachments = '';

            // Get a listing of the current attachments.
            if ($can_add_attachments) {
                $attachcount = 0;
                $attachwhere = "posthash='" . $db->escape_string($posthash) . "'";

                $attachments = '';
                $query = $db->simple_select('myshowcase_attachments', '*', $attachwhere);
                while ($attachment = $db->fetch_array($query)) {
                    $attachment['size'] = get_friendly_size($attachment['filesize']);
                    $attachment['icon'] = get_attachment_icon(get_extension($attachment['filename']));
                    $attachment['icon'] = str_replace(
                        '<img src="',
                        '<img src="' . $mybb->settings['bburl'] . '/',
                        $attachment['icon']
                    );

                    $attach_mod_options = '';
                    if ($attachment['visible'] != 1) {
                        $attachments .= eval(getTemplate('new_attachments_attachment_unapproved'));
                    } else {
                        $attachments .= eval(getTemplate('new_attachments_attachment'));
                    }
                    $attachcount++;
                }
                $lang->myshowcase_attach_quota = $lang->sprintf(
                        $lang->myshowcase_attach_quota,
                        $attachcount,
                        ($attach_limit == -1 ? $lang->myshowcase_unlimited : $attach_limit)
                    ) . '<br>';
                if ($attach_limit == -1 || ($attach_limit != -1 && $attachcount < $attach_limit)) {
                    if ($me->userperms['canwatermark'] && $me->watermarkimage != '' && file_exists(
                            $me->watermarkimage
                        )) {
                        $showcase_watermark = eval(getTemplate('watermark'));
                    }
                    $showcase_new_attachments_input = eval(getTemplate('new_attachments_input'));
                }

                if (!empty($showcase_new_attachments_input) || $attachments != '') {
                    $showcase_attachments = eval(getTemplate('new_attachments'));
                }
            }

            $showcase_page .= eval(getTemplate('new_top'));

            $trow_style = 'trow2';

            reset($showcase_fields_enabled);

            foreach ($showcase_fields_enabled as $fname => $ftype) {
                $temp = 'myshowcase_field_' . $fname;
                $field_header = !empty($lang->$temp) ? $lang->$temp : $fname;

                $trow_style = ($trow_style == 'trow1' ? 'trow2' : 'trow1');

                if ($mybb->get_input('action') == 'edit') {
                    $mybb->input['myshowcase_field_' . $fname] = htmlspecialchars_uni(
                        stripslashes($showcase_data[$fname])
                    );
                }

                switch ($ftype) {
                    case 'textbox':
                        $showcase_field_width = 50;
                        $showcase_field_rows = '';
                        $showcase_field_name = 'myshowcase_field_' . $fname;
                        $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                        $showcase_field_enabled = '';//($showcase_fields_enabled[$fname] != 1 ? 'disabled' : '');
                        $showcase_field_checked = '';
                        $showcase_field_options = 'maxlength="' . $showcase_fields_max_length[$fname] . '"';
                        $showcase_field_input = eval(getTemplate('field_textbox'));

                        if ($showcase_fields_format[$fname] != 'no') {
                            $showcase_field_input .= '&nbsp;' . $lang->myshowcase_editing_number;
                        }
                        break;

                    case 'url':
                        $showcase_field_width = 150;
                        $showcase_field_rows = '';
                        $showcase_field_name = 'myshowcase_field_' . $fname;
                        $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                        $showcase_field_enabled = '';//($showcase_fields_enabled[$fname] != 1 ? 'disabled' : '');
                        $showcase_field_checked = '';
                        $showcase_field_options = 'maxlength="' . $showcase_fields_max_length[$fname] . '"';
                        $showcase_field_input = eval(getTemplate('field_textbox'));
                        break;

                    case 'textarea':
                        $showcase_field_width = 100;
                        $showcase_field_rows = $showcase_fields_max_length[$fname];
                        $showcase_field_name = 'myshowcase_field_' . $fname;
                        $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                        $showcase_field_enabled = '';//($showcase_fields_enabled[$fname] != 1 ? 'disabled' : '');
                        $showcase_field_checked = '';
                        $showcase_field_options = '';
                        $showcase_field_input = eval(getTemplate('field_textarea'));
                        break;

                    case 'radio':
                        $showcase_field_width = 50;
                        $showcase_field_rows = '';
                        $showcase_field_name = 'myshowcase_field_' . $fname;
                        $showcase_field_enabled = '';// ($showcase_fields_enabled[$fname] != 1 ? 'disabled' : '');
                        $showcase_field_options = '';

                        $query = $db->simple_select(
                            'myshowcase_field_data',
                            '*',
                            'setid=' . $me->fieldsetid . " AND name='" . $fname . "' AND valueid != 0",
                            ['order_by' => 'disporder']
                        );
                        if ($db->num_rows($query) == 0) {
                            error($lang->myshowcase_db_no_data);
                        }

                        $showcase_field_input = '';
                        while ($results = $db->fetch_array($query)) {
                            $showcase_field_value = $results['valueid'];
                            $showcase_field_checked = ($mybb->get_input(
                                'myshowcase_field_' . $fname
                            ) == $results['valueid'] ? ' checked' : '');
                            $showcase_field_text = $results['value'];
                            $showcase_field_input .= eval(getTemplate('field_radio'));
                        }
                        break;

                    case 'checkbox':
                        $showcase_field_width = 50;
                        $showcase_field_rows = '';
                        $showcase_field_name = 'myshowcase_field_' . $fname;
                        $showcase_field_value = '1';
                        $showcase_field_enabled = '';//($showcase_fields_enabled[$fname] != 1 ? 'disabled' : '');
                        $showcase_field_checked = ($mybb->get_input(
                            'myshowcase_field_' . $fname
                        ) == 1 ? ' checked="checked"' : '');
                        $showcase_field_options = '';
                        $showcase_field_input = eval(getTemplate('field_checkbox'));
                        break;

                    case 'db':
                        $showcase_field_width = 50;
                        $showcase_field_rows = '';
                        $showcase_field_name = 'myshowcase_field_' . $fname;
                        $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                        $showcase_field_enabled = '';//($showcase_fields_enabled[$fname] != 1 ? 'disabled' : '');
                        $showcase_field_checked = '';
                        $query = $db->simple_select(
                            'myshowcase_field_data',
                            '*',
                            'setid=' . $me->fieldsetid . " AND name='" . $fname . "' AND valueid != 0",
                            ['order_by' => 'disporder']
                        );
                        if ($db->num_rows($query) == 0) {
                            error($lang->myshowcase_db_no_data);
                        }

                        $showcase_field_options = ($mybb->get_input(
                            'action'
                        ) == 'new' ? '<option value=0>&lt;Select&gt;</option>' : '');
                        while ($results = $db->fetch_array($query)) {
                            $showcase_field_options .= '<option value="' . $results['valueid'] . '" ' . ($showcase_field_value == $results['valueid'] ? ' selected' : '') . '>' . $results['value'] . '</option>';
                        }
                        $showcase_field_input = eval(getTemplate('field_db'));
                        break;

                    case 'date':
                        $showcase_field_width = 50;
                        $showcase_field_rows = '';
                        $showcase_field_name = 'myshowcase_field_' . $fname;
                        $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                        $showcase_field_enabled = '';//($showcase_fields_enabled[$fname] != 1 ? 'disabled' : '');
                        $showcase_field_checked = '';

                        $date_bits = explode('|', $showcase_field_value);
                        $mybb->input['myshowcase_field_' . $fname . '_m'] = $date_bits[0];
                        $mybb->input['myshowcase_field_' . $fname . '_d'] = $date_bits[1];
                        $mybb->input['myshowcase_field_' . $fname . '_y'] = $date_bits[2];

                        $showcase_field_value_m = ($mybb->get_input(
                            'myshowcase_field_' . $fname . '_m'
                        ) == '00' ? '00' : $mybb->get_input('myshowcase_field_' . $fname . '_m'));
                        $showcase_field_value_d = ($mybb->get_input(
                            'myshowcase_field_' . $fname . '_d'
                        ) == '00' ? '00' : $mybb->get_input('myshowcase_field_' . $fname . '_d'));
                        $showcase_field_value_y = ($mybb->get_input(
                            'myshowcase_field_' . $fname . '_y'
                        ) == '0000' ? '0000' : $mybb->get_input('myshowcase_field_' . $fname . '_y'));

                        $showcase_field_options_m = '<option value=00' . ($showcase_field_value_m == '00' ? ' selected' : '') . '>&nbsp;</option>';
                        $showcase_field_options_d = '<option value=00' . ($showcase_field_value_d == '00' ? ' selected' : '') . '>&nbsp;</option>';
                        $showcase_field_options_y = '<option value=0000' . ($showcase_field_value_y == '0000' ? ' selected' : '') . '>&nbsp;</option>';
                        for ($i = 1; $i <= 12; $i++) {
                            $showcase_field_options_m .= '<option value="' . substr(
                                    '0' . $i,
                                    -2
                                ) . '" ' . ($showcase_field_value_m == substr(
                                    '0' . $i,
                                    -2
                                ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                        }
                        for ($i = 1; $i <= 31; $i++) {
                            $showcase_field_options_d .= '<option value="' . substr(
                                    '0' . $i,
                                    -2
                                ) . '" ' . ($showcase_field_value_d == substr(
                                    '0' . $i,
                                    -2
                                ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                        }
                        for ($i = $showcase_fields_max_length[$fname]; $i >= $showcase_fields_min_length[$fname]; $i--) {
                            $showcase_field_options_y .= '<option value="' . $i . '" ' . ($showcase_field_value_y == $i ? ' selected' : '') . '>' . $i . '</option>';
                        }
                        $showcase_field_input = eval(getTemplate('field_date'));
                        break;
                }

                $field_header = ($showcase_fields_require[$fname] ? '<strong>' . $field_header . ' *</strong>' : $field_header);
                $showcase_page .= eval(getTemplate('new_fields'));
            }

            $plugins->run_hooks('myshowcase_editnew_end');

            $showcase_authid = '';
            
            $showcase_page .= eval(getTemplate('new_bottom'));
        } else {
            error($lang->myshowcase_not_authorized);
        }
        break;
    }
    case 'do_editshowcase':
    {
        $query = $db->simple_select($me->table_name, '*', 'gid=' . intval($mybb->get_input('gid', MyBB::INPUT_INT)));
        if ($db->num_rows($query) == 0) {
            error($lang->myshowcase_invalid_id);
        }

        $showcase_data = $db->fetch_array($query);

        //get posters info
        $showcase_user = get_user($showcase_data['uid']);

        //set value for author id in form hidden fields so we know if current user is author
        $showcase_authid = $showcase_user['uid'];

        //since its possible for a mod to edit another user's showcase, we need to re-evaluate  permimssions for poster
        if ($me->userperms['canmodedit'] && $mybb->user['uid'] != $showcase_data['uid']) {
            //get permissions for user
            $showcase_authorperms = $me->get_user_permissions($showcase_user);
        }

        //if editing, adjust permissions depending on mod or actual user
        if (is_array($showcase_authorperms)) {
            $can_add_attachments = $showcase_authorperms['canattach'];
            $attach_limit = $showcase_authorperms['attachlimit'];
        } else {
            $can_add_attachments = $me->userperms['canattach'];
            $attach_limit = $me->userperms['attachlimit'];
        }
    }
    //no break since sharing code
    case 'do_newshowcase':
    {
        if ($mybb->get_input('action') == 'do_newshowcase') {
            add_breadcrumb($lang->myshowcase_new, SHOWCASE_URL);
            $showcase_action = 'do_newshowcase';

            //need to populated a default user value here for new entries
            $showcase_data['uid'] = $mybb->user['uid'];

            //get perms if new
            $can_add_attachments = $me->userperms['canattach'];
            $attach_limit = $me->userperms['attachlimit'];
        } else {
            $showcase_editing_user = str_replace(
                '{username}',
                $showcase_user['username'],
                $lang->myshowcase_editing_user
            );
            add_breadcrumb($showcase_editing_user, SHOWCASE_URL);
            $showcase_action = 'do_editshowcase';
        }

        // Setup our posthash for managing attachments.
        if (!$mybb->get_input('posthash')) {
            mt_srand((int)(microtime() * 1000000));
            $mybb->input['posthash'] = md5($mybb->user['uid'] . mt_rand());
        }
        $posthash = $db->escape_string($mybb->get_input('posthash'));

        // Get a listing of the current attachments.
        if ($can_add_attachments == 1) {
            $attachcount = 0;
            $attachwhere = 'id=' . $me->id . " AND posthash='" . $posthash . "'";

            $attachments = '';
            $query = $db->simple_select('myshowcase_attachments', '*', $attachwhere);
            while ($attachment = $db->fetch_array($query)) {
                $attachment['size'] = get_friendly_size($attachment['filesize']);
                $attachment['icon'] = get_attachment_icon(get_extension($attachment['filename']));
                $attachment['icon'] = str_replace(
                    '<img src="',
                    '<img src="' . $mybb->settings['bburl'] . '/',
                    $attachment['icon']
                );


                $attach_mod_options = '';
                if ($attachment['visible'] != 1) {
                    $attachments .= eval(getTemplate('new_attachments_attachment_unapproved'));
                } else {
                    $attachments .= eval(getTemplate('new_attachments_attachment'));
                }
                $attachcount++;
            }
            $lang->myshowcase_attach_quota = $lang->sprintf(
                    $lang->myshowcase_attach_quota,
                    $attachcount,
                    ($attach_limit == -1 ? $lang->myshowcase_unlimited : $attach_limit)
                ) . '<br>';
            if ($attach_limit == -1 || ($attach_limit != 0 && ($attachcount < $attach_limit))) {
                if ($me->userperms['canwatermark']) {
                    $showcase_watermark = eval(getTemplate('watermark'));
                }
                $showcase_new_attachments_input = eval(getTemplate('new_attachments_input'));
            }
            $showcase_attachments = eval(getTemplate('new_attachments'));
        }

        if ($mybb->request_method == 'post' && $mybb->get_input('submit')) {
            // Decide on the visibility of this post.
            if ($me->modnewedit && !$me->userperms['canmodapprove']) {
                $approved = 0;
                $approved_by = 0;
            } else {
                $approved = 1;
                $approved_by = $mybb->user['uid'];
            }

            $plugins->run_hooks('myshowcase_do_newedit_start');

            // Set up showcase handler.
            require_once MYBB_ROOT . 'inc/datahandlers/myshowcase_dh.php';
            if ($mybb->get_input('action') == 'do_editshowcase') {
                $showcasehandler = new MyShowcaseDataHandler('update');
                $showcasehandler->action = 'edit';
            } else {
                $showcasehandler = new MyShowcaseDataHandler('insert');
                $showcasehandler->action = 'new';
            }

            //This is where the work is done

            // Verify incoming POST request
            verify_post_check($mybb->get_input('my_post_key'));

            // Set the post data that came from the input to the $post array.
            $default_data = [
                'uid' => $showcase_data['uid'],
                'dateline' => TIME_NOW,
                'approved' => $approved,
                'approved_by' => $approved_by,
                'posthash' => $posthash
            ];

            //add showcase id if editing so we know what to update
            if ($mybb->get_input('action') == 'do_editshowcase') {
                $default_data = array_merge(
                    $default_data,
                    ['gid' => intval($mybb->get_input('gid', MyBB::INPUT_INT))]
                );
            }

            //add showcase specific fields
            reset($showcase_fields_enabled);
            $submitted_data = [];
            foreach ($showcase_fields_enabled as $fname => $ftype) {
                if ($ftype == 'db' || $ftype == 'radio') {
                    $submitted_data[$fname] = intval($mybb->get_input('myshowcase_field_' . $fname));
                } elseif ($ftype == 'checkbox') {
                    $submitted_data[$fname] = (isset($mybb->input['myshowcase_field_' . $fname]) ? 1 : 0);
                } elseif ($ftype == 'date') {
                    $m = $db->escape_string($mybb->get_input('myshowcase_field_' . $fname . '_m'));
                    $d = $db->escape_string($mybb->get_input('myshowcase_field_' . $fname . '_d'));
                    $y = $db->escape_string($mybb->get_input('myshowcase_field_' . $fname . '_y'));
                    $submitted_data[$fname] = $m . '|' . $d . '|' . $y;
                } else {
                    $submitted_data[$fname] = $db->escape_string($mybb->get_input('myshowcase_field_' . $fname));
                }
            }

            //redefine the showcase_data
            $showcase_data = array_merge($default_data, $submitted_data);

            //send data to handler
            $showcasehandler->set_data($showcase_data);

            // Now let the showcase handler do all the hard work.
            $valid_showcase = $showcasehandler->validate_showcase();

            $showcase_errors = [];

            // Fetch friendly error messages if this is an invalid showcase
            if (!$valid_showcase) {
                $showcase_errors = $showcasehandler->get_friendly_errors();
            }
            if (count($showcase_errors) > 0) {
                $error = inline_error($showcase_errors);
                $showcase_page = eval($templates->render('error'));
            } else {
                //update showcase
                if ($mybb->get_input('action') == 'do_editshowcase') {
                    $insert_showcase = $showcasehandler->update_showcase();
                    $showcaseid = intval($mybb->get_input('gid', MyBB::INPUT_INT));
                } //insert showcase
                else {
                    $insert_showcase = $showcasehandler->insert_showcase();
                    $showcaseid = $insert_showcase['gid'];
                }

                $plugins->run_hooks('myshowcase_do_newedit_end');

                //fix url insert variable to update results
                $item_viewcode = str_replace('{gid}', $showcaseid, SHOWCASE_URL_VIEW);

                $redirect_newshowcase = $lang->redirect_myshowcase_new . '' . $lang->redirect_myshowcase . '' . $lang->sprintf(
                        $lang->redirect_myshowcase_return,
                        $showcase_url
                    );
                redirect($item_viewcode, $redirect_newshowcase);
                exit;
            }
        } else {
            $plugins->run_hooks('myshowcase_newedit_start');

            $showcase_page .= eval(getTemplate('new_top'));

            reset($showcase_fields_enabled);
            foreach ($showcase_fields_enabled as $fname => $ftype) {
                $temp = 'myshowcase_field_' . $fname;
                $field_header = !empty($lang->$temp) ? $lang->$temp : $fname;

                $trow_style = ($trow_style == 'trow1' ? 'trow2' : 'trow1');

                if ($mybb->get_input('action') == 'edit') {
                    $mybb->input['myshowcase_field_' . $fname] = htmlspecialchars_uni(
                        stripslashes($showcase_data[$fname])
                    );
                }

                switch ($ftype) {
                    case 'textbox':
                        $showcase_field_width = 50;
                        $showcase_field_rows = '';
                        $showcase_field_name = 'myshowcase_field_' . $fname;
                        $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                        $showcase_field_enabled = '';//($showcase_fields_enabled[$fname] != 1 ? 'disabled' : '');
                        $showcase_field_checked = '';
                        $showcase_field_options = 'maxlength="' . $showcase_fields_max_length[$fname] . '"';
                        $showcase_field_input = eval(getTemplate('field_textbox'));

                        if ($showcase_fields_format[$fname] != 'no') {
                            $showcase_field_input .= '&nbsp;' . $lang->myshowcase_editing_number;
                        }
                        break;

                    case 'url':
                        $showcase_field_width = 150;
                        $showcase_field_rows = '';
                        $showcase_field_name = 'myshowcase_field_' . $fname;
                        $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                        $showcase_field_enabled = '';//($showcase_fields_enabled[$fname] != 1 ? 'disabled' : '');
                        $showcase_field_checked = '';
                        $showcase_field_options = 'maxlength="' . $showcase_fields_max_length[$fname] . '"';
                        $showcase_field_input = eval(getTemplate('field_textbox'));
                        break;

                    case 'textarea':
                        $showcase_field_width = 100;
                        $showcase_field_rows = $showcase_fields_max_length[$fname];
                        $showcase_field_name = 'myshowcase_field_' . $fname;
                        $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                        $showcase_field_enabled = '';//($showcase_fields_enabled[$fname] != 1 ? 'disabled' : '');
                        $showcase_field_checked = '';
                        $showcase_field_options = '';
                        $showcase_field_input = eval(getTemplate('field_textarea'));
                        break;

                    case 'radio':
                        $showcase_field_width = 50;
                        $showcase_field_rows = '';
                        $showcase_field_name = 'myshowcase_field_' . $fname;
                        $showcase_field_enabled = '';//($showcase_fields_enabled[$fname] != 1 ? 'disabled' : '');
                        $showcase_field_options = '';

                        $query = $db->simple_select(
                            'myshowcase_field_data',
                            '*',
                            'setid=' . $me->fieldsetid . " AND name='" . $fname . "' AND valueid != 0",
                            ['order_by' => 'disporder']
                        );
                        if ($db->num_rows($query) == 0) {
                            error($lang->myshowcase_db_no_data);
                        }

                        $showcase_field_input = '';
                        while ($results = $db->fetch_array($query)) {
                            $showcase_field_value = $results['valueid'];
                            $showcase_field_checked = ($mybb->get_input(
                                'myshowcase_field_' . $fname
                            ) == $results['valueid'] ? ' checked' : '');
                            $showcase_field_text = $results['value'];
                            $showcase_field_input .= eval(getTemplate('field_radio'));
                        }
                        break;

                    case 'checkbox':
                        $showcase_field_width = 50;
                        $showcase_field_rows = '';
                        $showcase_field_name = 'myshowcase_field_' . $fname;
                        $showcase_field_value = '1';
                        $showcase_field_enabled = '';//($showcase_fields_enabled[$fname] != 1 ? 'disabled' : '');
                        $showcase_field_checked = ($mybb->get_input(
                            'myshowcase_field_' . $fname
                        ) == 1 ? ' checked="checked"' : '');
                        $showcase_field_options = '';
                        $showcase_field_input = eval(getTemplate('field_checkbox'));
                        break;

                    case 'db':
                        $showcase_field_width = 50;
                        $showcase_field_rows = '';
                        $showcase_field_name = 'myshowcase_field_' . $fname;
                        $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                        $showcase_field_enabled = '';//($showcase_fields_enabled[$fname] != 1 ? 'disabled' : '');
                        $showcase_field_checked = '';
                        $query = $db->simple_select(
                            'myshowcase_field_data',
                            '*',
                            'setid=' . $me->fieldsetid . " AND name='" . $fname . "' AND valueid != 0",
                            ['order_by' => 'disporder']
                        );
                        if ($db->num_rows($query) == 0) {
                            error($lang->myshowcase_db_no_data);
                        }

                        $showcase_field_options = ($mybb->get_input(
                            'action'
                        ) == 'new' ? '<option value=0>&lt;Select&gt;</option>' : '');
                        while ($results = $db->fetch_array($query)) {
                            $showcase_field_options .= '<option value="' . $results['valueid'] . '" ' . ($showcase_field_value == $results['valueid'] ? ' selected' : '') . '>' . $results['value'] . '</option>';
                        }
                        $showcase_field_input = eval(getTemplate('field_db'));
                        break;

                    case 'date':
                        $showcase_field_width = 50;
                        $showcase_field_rows = '';
                        $showcase_field_name = 'myshowcase_field_' . $fname;
                        $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                        $showcase_field_enabled = '';//($showcase_fields_enabled[$fname] != 1 ? 'disabled' : '');
                        $showcase_field_checked = '';

                        $showcase_field_value_m = ($mybb->get_input(
                            'myshowcase_field_' . $fname . '_m'
                        ) == '00' ? '00' : $mybb->get_input('myshowcase_field_' . $fname . '_m'));
                        $showcase_field_value_d = ($mybb->get_input(
                            'myshowcase_field_' . $fname . '_d'
                        ) == '00' ? '00' : $mybb->get_input('myshowcase_field_' . $fname . '_d'));
                        $showcase_field_value_y = ($mybb->get_input(
                            'myshowcase_field_' . $fname . '_y'
                        ) == '0000' ? '0000' : $mybb->get_input('myshowcase_field_' . $fname . '_y'));

                        $showcase_field_options_m = '<option value=0' . ($showcase_field_value_m == '00' ? ' selected' : '') . '>&nbsp;</option>';
                        $showcase_field_options_d = '<option value=0' . ($showcase_field_value_d == '00' ? ' selected' : '') . '>&nbsp;</option>';
                        $showcase_field_options_y = '<option value=0' . ($showcase_field_value_y == '0000' ? ' selected' : '') . '>&nbsp;</option>';
                        for ($i = 1; $i <= 12; $i++) {
                            $showcase_field_options_m .= '<option value="' . substr(
                                    '0' . $i,
                                    -2
                                ) . '" ' . ($showcase_field_value_m == substr(
                                    '0' . $i,
                                    -2
                                ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                        }
                        for ($i = 1; $i <= 31; $i++) {
                            $showcase_field_options_d .= '<option value="' . substr(
                                    '0' . $i,
                                    -2
                                ) . '" ' . ($showcase_field_value_d == substr(
                                    '0' . $i,
                                    -2
                                ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                        }
                        for ($i = $showcase_fields_max_length[$fname]; $i >= $showcase_fields_min_length[$fname]; $i--) {
                            $showcase_field_options_y .= '<option value="' . $i . '" ' . ($showcase_field_value_y == $i ? ' selected' : '') . '>' . $i . '</option>';
                        }
                        $showcase_field_input = eval(getTemplate('field_date'));
                        break;
                }

                $field_header = ($showcase_fields_require[$fname] ? '<strong>' . $field_header . ' *</strong>' : $field_header);
                $showcase_page .= eval(getTemplate('new_fields'));
            }

            $plugins->run_hooks('myshowcase_newedit_end');

            $showcase_page .= eval(getTemplate('new_bottom'));
        }
        break;
    }
}