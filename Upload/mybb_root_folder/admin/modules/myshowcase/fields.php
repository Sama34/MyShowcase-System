<?php
/**
 * MyShowcase Plugin for MyBB - Admin module for Field Editing
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \admin\modules\myshowcase\fields.php
 *
 */

// Disallow direct access to this file for security reasons
if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

$page->add_breadcrumb_item($lang->myshowcase_admin_fields, 'index.php?module=myshowcase-fields');

//make sure plugin is installed and active
$plugin_cache = $cache->read('plugins');
if (!$db->table_exists('myshowcase_config') || !array_key_exists('myshowcase', $plugin_cache['active'])) {
    flash_message($lang->myshowcase_plugin_not_installed, 'error');
    admin_redirect('index.php?module=config-plugins');
}

$html_type_options['textbox'] = 'textbox';
$html_type_options['textarea'] = 'textarea';
$html_type_options['db'] = 'db';
$html_type_options['radio'] = 'radio';
$html_type_options['checkbox'] = 'checkbox';
$html_type_options['url'] = 'url';
$html_type_options['date'] = 'date';

$field_type_options['varchar'] = 'varchar';
$field_type_options['int'] = 'int';
$field_type_options['bigint'] = 'bigint';
$field_type_options['text'] = 'text';
$field_type_options['timestamp'] = 'timestamp';

$field_format_options['no'] = 'None';
$field_format_options['decimal0'] = '#,###';
$field_format_options['decimal1'] = '#,###.#';
$field_format_options['decimal2'] = '#,###.##';


//get path to non-admin language folder currently in use
$langpath = str_replace('admin', '', $lang->path . '/' . $lang->language);

$plugins->run_hooks('admin_myshowcase_fields_begin');

//new field set
if ($mybb->input['action'] == 'new') {
    $page->add_breadcrumb_item($lang->myshowcase_admin_create_new, 'index.php?module=myshowcase-fields');
    $page->output_header($lang->myshowcase_admin_fields);

    if ($mybb->request_method == 'post') {
        $plugins->run_hooks('admin_myshowcase_fields_insert_begin');

        //update any existing names
        foreach ($mybb->input['setname'] as $setid => $setname) {
            $update_array = array(
                'setname' => $db->escape_string($setname),
            );
            $db->update_query('myshowcase_fieldsets', $update_array, "setid='{$setid}'");

            if ($db->affected_rows()) {
                $log = array('setid' => $setid, 'setname' => $setname);
                log_admin_action($log);
            }
        }

        //add new set
        if (!empty($mybb->input['newname'])) {
            //check if language folder is writable so we can create a new language file for this fieldset
            if (!is_writable($langpath)) {
                flash_message(
                    $lang->sprintf(
                        $lang->myshowcase_fields_lang_not_writable,
                        $lang->path . '/' . $lang->language . '/'
                    ),
                    'error'
                );
                admin_redirect('index.php?module=myshowcase-fields');
            }

            $newname = $db->escape_string($mybb->input['newname']);
            $query = $db->simple_select('myshowcase_fieldsets', '*', "setname='{$newname}'");
            if ($db->num_rows($query) != 0) {
                flash_message($lang->myshowcase_fields_already_exists, 'error');
                admin_redirect('index.php?module=myshowcase-fields');
            }

            $insert_array = array(
                'setname' => $db->escape_string($mybb->input['newname']),
            );
            $db->insert_query('myshowcase_fieldsets', $insert_array);
            $id = $db->insert_id();

            //generate filename for this new fieldset
            $file = 'myshowcase_fs' . $id . '.lang.php';

            // Log admin action
            $log = array('id' => $id, 'myshowcase' => $mybb->input['newname']);
            log_admin_action($log);

            // Reset new myshowcase info
            unset($mybb->input['newname']);
        }

        myshowcase_update_cache('config');
        myshowcase_update_cache('fields');
        myshowcase_update_cache('field_data');
        myshowcase_update_cache('fieldsets');
    }

    $plugins->run_hooks('admin_myshowcase_fields_insert_end');

    flash_message($lang->myshowcase_fields_update_success, 'success');
    admin_redirect('index.php?module=myshowcase-fields');
}

//edit fields in a field set
if ($mybb->input['action'] == 'editset') {
    if (isset($mybb->input['setid']) && is_numeric($mybb->input['setid'])) {
        //check if set is in use, if so, limit edit ability
        $can_edit = true;
        $query = $db->simple_select('myshowcase_config', '*', 'fieldsetid=' . $mybb->input['setid']);
        $result = $db->fetch_array($query);
        if ($db->num_rows($query) != 0 && $db->table_exists('myshowcase_data' . $result['id'])) {
            $can_edit = false;
        }

        //get lang file status
        $can_edit_lang = true;
        if (!is_writable($langpath) || (!is_writable(
                    $langpath . '/myshowcase_fs' . $mybb->input['setid'] . '.lang.php'
                ) && file_exists($langpath . '/myshowcase_fs' . $mybb->input['setid'] . '.lang.php'))) {
            $can_edit_lang = false;
        }

        //check if set exists
        $query = $db->simple_select('myshowcase_fieldsets', '*', 'setid=' . $mybb->input['setid']);
        $result = $db->fetch_array($query);

        if ($db->num_rows($query) == 0) {
            flash_message($lang->myshowcase_fields_invalid_id, 'error');
            admin_redirect('index.php?module=myshowcase-fields');
        }

        $page->add_breadcrumb_item(
            $lang->sprintf($lang->myshowcase_admin_edit_fieldset, $result['setname']),
            'index.php?module=myshowcase-fields'
        );
        $page->output_header($lang->myshowcase_admin_fields);

        $plugins->run_hooks('admin_myshowcase_fields_editset_begin');

        //user clicked Save button
        if ($mybb->request_method == 'post') {
            //apply changes to existing fields first
            $query = $db->simple_select('myshowcase_fields', '*', 'setid=' . $mybb->input['setid']);
            while ($result = $db->fetch_array($query)) {
                if ($can_edit) {
                    //make field name valid and remove any starting/ending underscores after replacement
                    $newname = $db->escape_string($mybb->input['name'][$result['fid']]);
                    $newname = preg_replace('#[^\w]#', '_', $newname);
                    $origname = '';
                    while ($origname != $newname) {
                        $newname = trim($newname, '_');
                        $origname = $newname;
                    }

                    $update_array = array(
                        'name' => strtolower($newname),
                        'field_order' => $db->escape_string($mybb->input['field_order'][$result['fid']]),
                        'list_table_order' => $db->escape_string($mybb->input['list_table_order'][$result['fid']]),
                        'enabled' => (isset($mybb->input['enabled'][$result['fid']]) ? 1 : 0),
                        'require' => (isset($mybb->input['require'][$result['fid']]) ? 1 : 0),
                        'parse' => (isset($mybb->input['parse'][$result['fid']]) ? 1 : 0),
                        'searchable' => (isset($mybb->input['searchable'][$result['fid']]) ? 1 : 0),
                        'format' => $mybb->input['format'][$result['fid']]
                    );
                } else {
                    $update_array = array(
                        'field_order' => $db->escape_string($mybb->input['field_order'][$result['fid']]),
                        'list_table_order' => $db->escape_string($mybb->input['list_table_order'][$result['fid']]),
                        'enabled' => (isset($mybb->input['enabled'][$result['fid']]) ? 1 : 0),
                        'require' => (isset($mybb->input['require'][$result['fid']]) ? 1 : 0),
                        'parse' => (isset($mybb->input['parse'][$result['fid']]) ? 1 : 0),
                        'searchable' => (isset($mybb->input['searchable'][$result['fid']]) ? 1 : 0),
                        'format' => $mybb->input['format'][$result['fid']]
                    );
                }
                $update_query = $db->update_query(
                    'myshowcase_fields',
                    $update_array,
                    'setid=' . $mybb->input['setid'] . ' AND fid=' . $result['fid']
                );

                if ($can_edit && ($mybb->input['html_type'][$result['fid']] == 'db' || $mybb->input['html_type'][$result['fid']] == 'radio')) {
                    $update_array = array(
                        'name' => $db->escape_string($mybb->input['name'][$result['fid']])
                    );
                    $update_query = $db->update_query(
                        'myshowcase_field_data',
                        $update_array,
                        'setid=' . $mybb->input['setid'] . ' AND fid=' . $result['fid']
                    );
                }
            }

            //apply new field
            $do_new_field = 0;
            if ($mybb->input['newname'] != '' && $mybb->input['newmax_length'] != '' && is_numeric(
                    $mybb->input['newmax_length']
                ) && $mybb->input['newfield_order'] != '' && is_numeric(
                    $mybb->input['newfield_order']
                ) && $mybb->input['newlist_table_order'] != '' && is_numeric($mybb->input['newlist_table_order'])) {
                //set default field type/size for certain html types overwritting user entries
                $do_default_option_insert = 0;
                switch ($mybb->input['newhtml_type']) {
                    case 'db':
                        $mybb->input['newfield_type'] = 'int';
                        $mybb->input['newmax_length'] = 3;
                        $do_default_option_insert = 1;
                        break;

                    case 'radio':
                        $mybb->input['newfield_type'] = 'int';
                        $mybb->input['newmax_length'] = 1;
                        $do_default_option_insert = 1;
                        break;

                    case 'checkbox':
                        $mybb->input['newfield_type'] = 'int';
                        $mybb->input['newmax_length'] = 1;
                        break;

                    case 'date':
                        $mybb->input['newfield_type'] = 'varchar';
                        $mybb->input['newmin_length'] = max(intval($mybb->input['newmin_length']), 1901);
                        $mybb->input['newmax_length'] = min(intval($mybb->input['newmax_length']), 2038);

                        if ($mybb->input['newmin_length'] > $mybb->input['newmax_length']) {
                            flash_message($lang->myshowcase_field_year_order, 'error');
                            admin_redirect(
                                'index.php?module=myshowcase-fields&amp;action=editset&amp;setid=' . $mybb->input['setid']
                            );
                        }

                        break;

                    case 'url':
                        $mybb->input['newfield_type'] = 'varchar';
                        $mybb->input['newmax_length'] = 255;
                        break;
                }

                //make field name valid and remove any starting/ending underscores after replacement
                $newname = $db->escape_string($mybb->input['newname']);
                $newname = preg_replace('#[^\w]#', '_', $newname);
                $origname = '';
                while ($origname != $newname) {
                    $newname = trim($newname, '_');
                    $origname = $newname;
                }

                $insert_array = array(
                    'setid' => $mybb->input['setid'],
                    'name' => strtolower($newname),
                    'html_type' => $mybb->input['newhtml_type'],
                    'field_type' => $mybb->input['newfield_type'],
                    'min_length' => intval($mybb->input['newmin_length']),
                    'max_length' => intval($mybb->input['newmax_length']),
                    'field_order' => intval($mybb->input['newfield_order']),
                    'list_table_order' => intval($mybb->input['newlist_table_order']),
                    'enabled' => (isset($mybb->input['newenabled']) ? 1 : 0),
                    'require' => (isset($mybb->input['newrequire']) ? 1 : 0),
                    'parse' => (isset($mybb->input['newparse']) ? 1 : 0),
                    'searchable' => (isset($mybb->input['newsearchable']) ? 1 : 0),
                    'format' => $mybb->input['newformat']
                );

                $insert_query = $db->insert_query('myshowcase_fields', $insert_array);
                $do_new_field = 1;

                if ($do_default_option_insert) {
                    $insert_array = array(
                        'setid' => $mybb->input['setid'],
                        'fid' => $insert_query,
                        'name' => strtolower($newname),
                        'value' => 'Not Specified',
                        'valueid' => 0,
                        'disporder' => 0
                    );

                    $insert_query = $db->insert_query('myshowcase_field_data', $insert_array);
                }
            }

            //edit language file if can be edited
            if ($can_edit_lang) {
                //get existing fields from input
                $items_add = array();
                foreach ($mybb->input['name'] as $key => $value) {
                    $items_add['myshowcase_field_' . $value] = ($mybb->input['label'][$key] == '' ? $db->escape_string(
                        $mybb->input['name'][$key]
                    ) : $db->escape_string($mybb->input['label'][$key]));
                }

                //write new field from input
                if ($do_new_field) {
                    $items_add['myshowcase_field_' . $db->escape_string(
                        $mybb->input['newname']
                    )] = ($mybb->input['newlabel'] == '' ? $db->escape_string(
                        $mybb->input['newname']
                    ) : $db->escape_string($mybb->input['newlabel']));
                }

                $retval = modify_lang('myshowcase_fs' . $mybb->input['setid'], $items_add, array(), 'english', false);
            }

            flash_message($lang->myshowcase_field_update_success, 'success');
            admin_redirect('index.php?module=myshowcase-fields&amp;action=editset&amp;setid=' . $mybb->input['setid']);
        }

        $form = new Form(
            'index.php?module=myshowcase-fields&amp;action=editset&amp;setid=' . $mybb->input['setid'],
            'post',
            'editset'
        );
        $form_container = new FormContainer($lang->myshowcase_field_list);

        $form_container->output_row_header(
            $lang->myshowcase_field_fid,
            array('width' => '5%', 'class' => 'align_center')
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_name,
            array('width' => '10%', 'class' => 'align_center')
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_label,
            array('width' => '10%', 'class' => 'align_center')
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_html_type,
            array('width' => '5%', 'class' => 'align_center')
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_field_type,
            array('width' => '5%', 'class' => 'align_center')
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_min_length,
            array('width' => '5%', 'class' => 'align_center')
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_max_length,
            array('width' => '5%', 'class' => 'align_center')
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_field_order,
            array('width' => '5%', 'class' => 'align_center')
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_list_table_order,
            array('width' => '4%', 'class' => 'align_center')
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_format,
            array('width' => '10%', 'class' => 'align_center')
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_enabled,
            array('width' => '4%', 'class' => 'align_center')
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_required,
            array('width' => '4%', 'class' => 'align_center')
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_parse,
            array('width' => '4%', 'class' => 'align_center')
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_searchable,
            array('width' => '4%', 'class' => 'align_center')
        );
        $form_container->output_row_header($lang->controls, array('width' => '10%', 'class' => 'align_center'));

        $query = $db->simple_select(
            'myshowcase_fields',
            '*',
            'setid=' . $mybb->input['setid'],
            array('order_by' => 'fid')
        );
        $num_fields = $db->num_rows($query);
        if ($num_fields == 0) {
            $form_container->output_cell(
                $lang->myshowcase_fields_no_fields,
                array('class' => 'align_center', 'colspan' => 15)
            );
            $form_container->construct_row();
        } else {
            @include($langpath . '/myshowcase_fs' . $mybb->input['setid'] . '.lang.php');
            $max_order = 1;
            while ($result = $db->fetch_array($query)) {
                // Build popup menu
                $popup = new PopupMenu("field_{$result['fid']}", $lang->options);
                $popup->add_item(
                    $lang->myshowcase_field_delete,
                    "index.php?module=myshowcase-fields&amp;action=delfield&amp;fid={$result['fid']}&amp;setid=" . $mybb->input['setid']
                );

                //add option to edit list items if db type
                if ($result['html_type'] == 'db' || $result['html_type'] == 'radio') {
                    $popup->add_item(
                        $lang->myshowcase_field_edit_options,
                        "index.php?module=myshowcase-fields&amp;action=editopt&amp;fid={$result['fid']}&amp;setid=" . $mybb->input['setid']
                    );
                }

                $max_order = max($max_order, $result['field_order']);
                $form_container->output_cell($result['fid'], array('class' => 'align_center'));
                if ($can_edit) {
                    $form_container->output_cell(
                        $form->generate_text_box(
                            'name[' . $result['fid'] . ']',
                            $result['name'],
                            array('id' => 'label[' . $result['fid'] . ']', 'style' => 'width: 100px')
                        ),
                        array('class' => 'align_left')
                    );
                } else {
                    $form_container->output_cell(
                        $result['name'] . $form->generate_hidden_field('name[' . $result['fid'] . ']', $result['name']),
                        array('class' => 'align_left')
                    );
                }
                if ($can_edit_lang) {
                    $form_container->output_cell(
                        $form->generate_text_box(
                            'label[' . $result['fid'] . ']',
                            $l['myshowcase_field_' . $result['name']],
                            array('id' => 'label[' . $result['fid'] . ']', 'style' => 'width: 100px')
                        ),
                        array('class' => 'align_left')
                    );
                } else {
                    $form_container->output_cell(
                        $l['myshowcase_field_' . $result['name']],
                        array('class' => 'align_left')
                    );
                }
                $form_container->output_cell(
                    $result['html_type'] . $form->generate_hidden_field(
                        'html_type[' . $result['fid'] . ']',
                        $result['html_type']
                    ),
                    array('class' => 'align_center')
                );
                $form_container->output_cell(
                    $result['field_type'] . $form->generate_hidden_field(
                        'field_type[' . $result['fid'] . ']',
                        $result['field_type']
                    ),
                    array('class' => 'align_center')
                );
                $form_container->output_cell(
                    $result['min_length'] . $form->generate_hidden_field(
                        'min_length[' . $result['fid'] . ']',
                        $result['min_length']
                    ),
                    array('class' => 'align_center')
                );
                $form_container->output_cell(
                    $result['max_length'] . $form->generate_hidden_field(
                        'max_length[' . $result['fid'] . ']',
                        $result['max_length']
                    ),
                    array('class' => 'align_center')
                );
                $form_container->output_cell(
                    $form->generate_text_box(
                        'field_order[' . $result['fid'] . ']',
                        $result['field_order'],
                        array('id' => 'field_order[' . $result['fid'] . ']', 'style' => 'width: 35px')
                    ),
                    array('class' => 'align_center')
                );
                $form_container->output_cell(
                    $form->generate_text_box(
                        'list_table_order[' . $result['fid'] . ']',
                        $result['list_table_order'],
                        array('id' => 'list_table_order[' . $result['fid'] . ']', 'style' => 'width: 35px')
                    ),
                    array('class' => 'align_center')
                );
                $form_container->output_cell(
                    $form->generate_select_box(
                        'format[' . $result['fid'] . ']',
                        $field_format_options,
                        $result['format'],
                        array('id' => 'format[' . $result['fid'] . ']')
                    ),
                    array('class' => 'align_center')
                );
                $form_container->output_cell(
                    $form->generate_check_box(
                        'enabled[' . $result['fid'] . ']',
                        'true',
                        null,
                        array('checked' => $result['enabled']),
                        ''
                    ),
                    array('class' => 'align_center')
                );
                $form_container->output_cell(
                    $form->generate_check_box(
                        'require[' . $result['fid'] . ']',
                        'true',
                        null,
                        array('checked' => $result['require']),
                        ''
                    ),
                    array('class' => 'align_center')
                );
                $form_container->output_cell(
                    $form->generate_check_box(
                        'parse[' . $result['fid'] . ']',
                        'true',
                        null,
                        array('checked' => $result['parse']),
                        ''
                    ),
                    array('class' => 'align_center')
                );
                $form_container->output_cell(
                    $form->generate_check_box(
                        'searchable[' . $result['fid'] . ']',
                        'true',
                        null,
                        array('checked' => $result['searchable']),
                        ''
                    ),
                    array('class' => 'align_center')
                );
                $form_container->output_cell($popup->fetch(), array('class' => 'align_center'));
                $form_container->construct_row();
            }
        }
        $form_container->end();

        if ($can_edit) {
            echo '<br /><br />';
            $form_container = new FormContainer($lang->myshowcase_field_new);

            $form_container->output_row_header(
                $lang->myshowcase_field_name,
                array('width' => '10%', 'class' => 'align_center')
            );
            $form_container->output_row_header(
                $lang->myshowcase_field_label,
                array('width' => '10%', 'class' => 'align_center')
            );
            $form_container->output_row_header(
                $lang->myshowcase_field_html_type,
                array('width' => '5%', 'class' => 'align_center')
            );
            $form_container->output_row_header(
                $lang->myshowcase_field_field_type,
                array('width' => '5%', 'class' => 'align_center')
            );
            $form_container->output_row_header(
                $lang->myshowcase_field_min_length,
                array('width' => '5%', 'class' => 'align_center')
            );
            $form_container->output_row_header(
                $lang->myshowcase_field_max_length,
                array('width' => '5%', 'class' => 'align_center')
            );
            $form_container->output_row_header(
                $lang->myshowcase_field_field_order,
                array('width' => '5%', 'class' => 'align_center')
            );
            $form_container->output_row_header(
                $lang->myshowcase_field_list_table_order,
                array('width' => '4%', 'class' => 'align_center')
            );
            $form_container->output_row_header(
                $lang->myshowcase_field_format,
                array('width' => '10%', 'class' => 'align_center')
            );
            /*			$form_container->output_row_header($lang->myshowcase_field_enabled, array("width" => "4%", "class" => "align_center"));
                        $form_container->output_row_header($lang->myshowcase_field_required, array("width" => "4%", "class" => "align_center"));
                        $form_container->output_row_header($lang->myshowcase_field_parse, array("width" => "4%", "class" => "align_center"));
                        $form_container->output_row_header($lang->myshowcase_field_searchable, array("width" => "4%", "class" => "align_center"));*/

            $form_container->output_cell(
                $form->generate_text_box('newname', '', array('id' => 'newname', 'style' => 'width: 100px')),
                array('class' => 'align_center')
            );
            $form_container->output_cell(
                $form->generate_text_box('newlabel', '', array('id' => 'newlabel', 'style' => 'width: 100px')),
                array('class' => 'align_center')
            );
            $form_container->output_cell(
                $form->generate_select_box('newhtml_type', $html_type_options, '', array('id' => 'newhtml_type')),
                array('class' => 'align_center')
            );
            $form_container->output_cell(
                $form->generate_select_box('newfield_type', $field_type_options, '', array('id' => 'newfield_type')),
                array('class' => 'align_center')
            );
            $form_container->output_cell(
                $form->generate_text_box(
                    'newmin_length',
                    '',
                    array('id' => 'newmin_length', 'style' => 'width: 50px')
                ),
                array('class' => 'align_center')
            );
            $form_container->output_cell(
                $form->generate_text_box(
                    'newmax_length',
                    '',
                    array('id' => 'newmax_length', 'style' => 'width: 50px')
                ),
                array('class' => 'align_center')
            );
            $form_container->output_cell(
                $form->generate_text_box(
                    'newfield_order',
                    $max_order + 1,
                    array('id' => 'newfield_order', 'style' => 'width: 35px')
                ),
                array('class' => 'align_center')
            );
            $form_container->output_cell(
                $form->generate_text_box(
                    'newlist_table_order',
                    -1,
                    array('id' => 'newlist_table_order', 'style' => 'width: 35px')
                ),
                array('class' => 'align_center')
            );
            $form_container->output_cell(
                $form->generate_select_box('newformat', $field_format_options, '', array('id' => 'newformat')),
                array('class' => 'align_center')
            );
            /*			$form_container->output_cell($form->generate_check_box('newenabled', 0, ""),array('class' => 'align_center'));
                        $form_container->output_cell($form->generate_check_box('newrequire', 0, ""),array('class' => 'align_center'));
                        $form_container->output_cell($form->generate_check_box('newparse', 0, ""),array('class' => 'align_center'));
                        $form_container->output_cell($form->generate_check_box('newsearchable', 0, ""),array('class' => 'align_center')); */

            $form_container->construct_row();
            $form_container->end();
        }
        $buttons[] = $form->generate_submit_button($lang->myshowcase_fields_save_changes);
        $form->output_submit_wrapper($buttons);

        $form->end();
        $plugins->run_hooks('admin_myshowcase_fields_editset_end');

        myshowcase_update_cache('config');
        myshowcase_update_cache('fields');
        myshowcase_update_cache('field_data');
        myshowcase_update_cache('fieldsets');
    } else {
        flash_message($lang->myshowcase_fields_invalid_id, 'error');
        admin_redirect('index.php?module=myshowcase-fields');
    }
}

//delete field set
if ($mybb->input['action'] == 'delset') {
    if (isset($mybb->input['setid']) && is_numeric($mybb->input['setid'])) {
        $query = $db->simple_select('myshowcase_fieldsets', '*', 'setid=' . $mybb->input['setid']);
        $result = $db->fetch_array($query);
        $setname = $result['setname'];
        if ($db->num_rows($query) == 0) {
            flash_message($lang->myshowcase_fields_invalid_id, 'error');
            admin_redirect('index.php?module=myshowcase-fields');
        } else {
            $page->add_breadcrumb_item(
                $lang->sprintf($lang->myshowcase_admin_edit_fieldset, $setname),
                'index.php?module=myshowcase-fields'
            );
            $page->output_header($lang->myshowcase_admin_fields);

            $plugins->run_hooks('admin_myshowcase_fields_deleteset_begin');

            $query = $db->simple_select('myshowcase_config', '*', 'fieldsetid=' . $mybb->input['setid']);
            if ($db->num_rows($query) != 0) {
                flash_message($lang->myshowcase_fields_in_use, 'error');
                admin_redirect('index.php?module=myshowcase-fields');
            } else {
                $result = $db->fetch_array($query);
                echo $lang->sprintf($lang->myshowcase_fields_confirm_delete_long, $setname);
                $form = new Form(
                    'index.php?module=myshowcase-fields&amp;action=do_delset&amp;setid=' . $mybb->input['setid'],
                    'post',
                    'do_delset'
                );
                $buttons[] = $form->generate_submit_button($lang->myshowcase_fields_confirm_delete);
                $form->output_submit_wrapper($buttons);

                $form->end();
            }

            $plugins->run_hooks('admin_myshowcase_fields_deleteset_end');
        }
    }
}

//delete field set
if ($mybb->input['action'] == 'do_delset') {
    if (isset($mybb->input['setid']) && is_numeric($mybb->input['setid']) && $mybb->request_method == 'post') {
        $page->add_breadcrumb_item($lang->myshowcase_admin_edit_fieldset, 'index.php?module=myshowcase-fields');
        $page->output_header($lang->myshowcase_admin_fields);


        $query = $db->delete_query('myshowcase_fieldsets', 'setid=' . $mybb->input['setid']);
        if ($db->affected_rows($query) != 1) {
            flash_message($lang->myshowcase_fields_delete_failed, 'error');
            admin_redirect('index.php?module=myshowcase-fields');
        } else {
            $plugins->run_hooks('admin_myshowcase_fields_dodeleteset_begin');

            $query = $db->delete_query('myshowcase_fields', 'setid=' . $mybb->input['setid']);
            $query = $db->delete_query('myshowcase_field_data', 'setid=' . $mybb->input['setid']);

            //also delete langauge files for this fieldset
            $langs = $lang->get_languages(false);
            foreach ($langs as $langfolder => $langname) {
                $langfile = $lang->path . '/' . $langfolder . '/myshowcase_fs' . $mybb->input['setid'] . '.lang.php';
                unlink($langfile);
            }

            $plugins->run_hooks('admin_myshowcase_fields_dodeleteset_end');

            myshowcase_update_cache('config');
            myshowcase_update_cache('fields');
            myshowcase_update_cache('field_data');
            myshowcase_update_cache('fieldsets');

            // Log admin action
            $log = array('setid' => $mybb->input['setid']);
            log_admin_action($log);

            flash_message($lang->myshowcase_field_delete_success, 'success');
            admin_redirect('index.php?module=myshowcase-fields');
        }
    }
}

//edit specific field in a field set (DB or select types)
if ($mybb->input['action'] == 'editopt') {
    if (isset($mybb->input['fid']) && is_numeric($mybb->input['fid'])) {
        //check if set is in use, if so, limit edit ability
        $can_edit = true;
        $query = $db->simple_select('myshowcase_config', '*', 'fieldsetid=' . $mybb->input['setid']);
        $result = $db->fetch_array($query);

        if ($db->num_rows($query) != 0 && $db->table_exists('myshowcase_data' . $result['id'])) {
            //flash_message($lang->myshowcase_fields_in_use, 'error');
            //admin_redirect("index.php?module=myshowcase-fields");
            $can_edit = false;
        }

        //get set name
        $query = $db->simple_select('myshowcase_fieldsets', 'setname', 'setid=' . $mybb->input['setid']);
        $result = $db->fetch_array($query);
        $setname = $result['setname'];

        //check if DB/radio type field exists
        $query = $db->simple_select(
            'myshowcase_fields',
            '*',
            'setid=' . $mybb->input['setid'] . ' AND fid=' . $mybb->input['fid'] . " AND html_type In ('db', 'radio')"
        );
        if ($db->num_rows($query) == 0) {
            flash_message($lang->myshowcase_field_invalid_id, 'error');
            admin_redirect('index.php?module=myshowcase-fields');
        }
        $result = $db->fetch_array($query);
        $fieldname = $result['name'];

        $page->add_breadcrumb_item(
            $lang->sprintf($lang->myshowcase_admin_edit_fieldopt, $fieldname, $setname),
            'index.php?module=myshowcase-fields'
        );
        $page->output_header($lang->myshowcase_admin_fields);

        $plugins->run_hooks('admin_myshowcase_fields_editopt_begin');

        //user clicked Save button
        if ($mybb->request_method == 'post') {
            //apply changes to existing fields first
            $query = $db->simple_select(
                'myshowcase_field_data',
                '*',
                'setid=' . $mybb->input['setid'] . ' AND fid=' . $mybb->input['fid']
            );
            while ($result = $db->fetch_array($query)) {
                $update_array = array(
                    'value' => $db->escape_string($mybb->input['value'][$result['valueid']]),
                    'disporder' => $db->escape_string($mybb->input['disporder'][$result['valueid']])
                );

                $update_query = $db->update_query(
                    'myshowcase_field_data',
                    $update_array,
                    'setid=' . $mybb->input['setid'] . ' AND fid=' . $mybb->input['fid'] . ' AND valueid=' . $result['valueid']
                );
            }

            //apply new field
            if ($mybb->input['newtext'] != '' && $mybb->input['newvalueid'] != '' && is_numeric(
                    $mybb->input['newvalueid']
                ) && $mybb->input['newdisporder'] != '' && is_numeric($mybb->input['newdisporder'])) {
                $insert_array = array(
                    'setid' => $mybb->input['setid'],
                    'fid' => $mybb->input['fid'],
                    'name' => $db->escape_string($mybb->input['newfieldname']),
                    'value' => $db->escape_string($mybb->input['newtext']),
                    'valueid' => $mybb->input['newvalueid'],
                    'disporder' => $mybb->input['newdisporder']
                );

                $insert_query = $db->insert_query('myshowcase_field_data', $insert_array);
            }

            myshowcase_update_cache('config');
            myshowcase_update_cache('fields');
            myshowcase_update_cache('field_data');
            myshowcase_update_cache('fieldsets');

            flash_message($lang->myshowcase_field_update_opt_success, 'success');
            admin_redirect(
                'index.php?module=myshowcase-fields&amp;action=editopt&amp;fid=' . $mybb->input['fid'] . '&amp;setid=' . $mybb->input['setid']
            );
        }

        $form = new Form(
            'index.php?module=myshowcase-fields&amp;action=editopt&amp;fid=' . $mybb->input['fid'] . '&amp;setid=' . $mybb->input['setid'],
            'post',
            'editopt'
        );
        $form_container = new FormContainer($lang->myshowcase_field_list);

        $form_container->output_row_header(
            $lang->myshowcase_field_fdid,
            array('width' => '5%', 'class' => 'align_center')
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_option_text,
            array('width' => '10%', 'class' => 'align_center')
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_disporder,
            array('width' => '5%', 'class' => 'align_center')
        );
        $form_container->output_row_header($lang->controls, array('width' => '10%', 'class' => 'align_center'));

        $query = $db->simple_select(
            'myshowcase_field_data',
            '*',
            'setid=' . $mybb->input['setid'] . ' AND fid=' . $mybb->input['fid'],
            array('order_by' => 'valueid')
        );
        $num_fields = $db->num_rows($query);
        if ($num_fields == 0) {
            $form_container->output_cell(
                $lang->myshowcase_field_no_options,
                array('class' => 'align_center', 'colspan' => 5)
            );
            $form_container->construct_row();
        } else {
            $max_order = 1;
            $max_valueid = 0;
            while ($result = $db->fetch_array($query)) {
                // Build popup menu
                $popup = new PopupMenu("field_{$result['valueid']}", $lang->options);
                if ($can_edit) {
                    $popup->add_item(
                        $lang->myshowcase_field_delete,
                        "index.php?module=myshowcase-fields&amp;action=delopt&amp;setid={$mybb->input['setid']}&amp;fid={$mybb->input['fid']}&amp;valueid={$result['valueid']}"
                    );
                }

                $max_order = max($max_order, $result['disporder']);
                $max_valueid = max($max_valueid, $result['valueid']);
                $form_container->output_cell(
                    $result['valueid'] . $form->generate_hidden_field(
                        'fieldname[' . $result['valueid'] . ']',
                        $result['name']
                    ),
                    array('class' => 'align_center')
                );

                $form_container->output_cell(
                    $form->generate_text_box(
                        'value[' . $result['valueid'] . ']',
                        $result['value'],
                        array('id' => 'value[' . $result['valueid'] . ']', 'style' => 'width: 105px')
                    ),
                    array('class' => 'align_center')
                );

                $form_container->output_cell(
                    $form->generate_text_box(
                        'disporder[' . $result['valueid'] . ']',
                        $result['disporder'],
                        array('id' => 'disporder[' . $result['valueid'] . ']', 'style' => 'width: 65px')
                    ),
                    array('class' => 'align_center')
                );
                if ($can_edit) {
                    $form_container->output_cell($popup->fetch(), array('class' => 'align_center'));
                } else {
                    $form_container->output_cell('N/A', array('class' => 'align_center'));
                }
                $form_container->construct_row();
            }
        }
        $form_container->end();

        echo '<br /><br />';
        $form_container = new FormContainer($lang->myshowcase_field_new_option);

        $form_container->output_row_header(
            $lang->myshowcase_field_option_text . ' *',
            array('width' => '65', 'class' => 'align_center')
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_disporder,
            array('width' => '65', 'class' => 'align_center')
        );

        $form_container->output_cell(
            $form->generate_text_box('newtext', '', array('id' => 'newname', 'style' => 'width: 150px')) .
            $form->generate_hidden_field('newfieldname', $fieldname) .
            $form->generate_hidden_field('newvalueid', $max_valueid + 1)
            ,
            array('class' => 'align_center')
        );
        $form_container->output_cell(
            $form->generate_text_box(
                'newdisporder',
                $max_order + 1,
                array('id' => 'newdisporder', 'style' => 'width: 150px')
            ),
            array('class' => 'align_center')
        );

        $form_container->construct_row();
        $form_container->end();

        $buttons[] = $form->generate_submit_button($lang->myshowcase_fields_save_changes);
        $form->output_submit_wrapper($buttons);

        $form->end();

        $plugins->run_hooks('admin_myshowcase_fields_editopt_end');
    } else {
        flash_message($lang->myshowcase_field_invalid_id, 'error');
        admin_redirect('index.php?module=myshowcase-fields');
    }
}

//delete field option
if ($mybb->input['action'] == 'delopt') {
    if (isset($mybb->input['valueid']) && is_numeric($mybb->input['valueid'])) {
        $page->add_breadcrumb_item($lang->myshowcase_admin_edit_fieldset, 'index.php?module=myshowcase-fields');
        $page->output_header($lang->myshowcase_admin_fields);

        $query = $db->simple_select(
            'myshowcase_field_data',
            '*',
            'setid=' . $mybb->input['setid'] . ' AND fid=' . $mybb->input['fid'] . ' AND valueid=' . $mybb->input['valueid']
        );
        $result = $db->fetch_array($query);
        $fieldname = $result['name'];
        if ($db->num_rows($query) == 0) {
            flash_message($lang->myshowcase_field_invalid_opt, 'error');
        } else {
            $plugins->run_hooks('admin_myshowcase_fields_delopt_begin');

            $query = $db->simple_select('myshowcase_config', '*', 'fieldsetid=' . $mybb->input['setid']);
            if ($db->num_rows($query) != 0) {
                flash_message($lang->myshowcase_fields_in_use, 'error');
                admin_redirect(
                    'index.php?module=myshowcase-fields&amp;action=editopt&amp;fid=' . $mybb->input['fid'] . '&amp;setid=' . $mybb->input['setid']
                );
            } else {
                $query = $db->delete_query(
                    'myshowcase_field_data',
                    'setid=' . $mybb->input['setid'] . ' AND fid=' . $mybb->input['fid'] . ' AND valueid=' . $mybb->input['valueid']
                );

                $plugins->run_hooks('admin_myshowcase_fields_delopt_end');

                myshowcase_update_cache('config');
                myshowcase_update_cache('fields');
                myshowcase_update_cache('field_data');
                myshowcase_update_cache('fieldsets');

                // Log admin action
                $log = array(
                    'setid' => $mybb->input['setid'],
                    'fid' => $mybb->input['fid'],
                    'valueid' => $mybb->input['valueid']
                );
                log_admin_action($log);

                flash_message($lang->myshowcase_field_delete_opt_success, 'success');
                admin_redirect(
                    'index.php?module=myshowcase-fields&amp;action=editopt&amp;setid=' . $mybb->input['setid'] . '&amp;fid=' . $mybb->input['fid']
                );
            }
        }
    }
}

//delete specific field
if ($mybb->input['action'] == 'delfield') {
    if (isset($mybb->input['fid']) && is_numeric($mybb->input['fid'])) {
        //check if set exists
        $query = $db->simple_select('myshowcase_fieldsets', 'setname', 'setid=' . $mybb->input['setid']);
        $result = $db->fetch_array($query);

        if ($db->num_rows($query) == 0) {
            flash_message($lang->myshowcase_fields_invalid_id, 'error');
            admin_redirect('index.php?module=myshowcase-fields');
        }

        $page->add_breadcrumb_item(
            $lang->sprintf($lang->myshowcase_admin_edit_fieldset, $result['setname']),
            'index.php?module=myshowcase-fields'
        );
        $page->output_header($lang->myshowcase_admin_fields);

        $query = $db->simple_select(
            'myshowcase_fields',
            'name',
            'setid=' . $mybb->input['setid'] . ' AND fid=' . $mybb->input['fid']
        );
        $result = $db->fetch_array($query);
        $fieldname = $result['name'];
        if ($fieldname == '') {
            flash_message($lang->myshowcase_field_invalid_id, 'error');
            admin_redirect('index.php?module=myshowcase-fields&amp;action=editset&amp;setid=' . $mybb->input['setid']);
        } else {
            $plugins->run_hooks('admin_myshowcase_fields_delete_begin');

            //check if tables created from this fiedlset already
            $query = $db->simple_select('myshowcase_config', 'id', 'fieldsetid=' . $mybb->input['setid']);
            $field_in_use = false;
            while ($result = $db->fetch_array($query)) {
                if ($db->table_exists('myshowcase_data' . $result['id'])) {
                    $field_in_use = true;
                }
            }

            if ($field_in_use) {
                flash_message($lang->myshowcase_field_in_use, 'error');
                admin_redirect(
                    'index.php?module=myshowcase-fields&amp;action=editset&amp;setid=' . $mybb->input['setid']
                );
            } else {
                echo $lang->sprintf($lang->myshowcase_field_confirm_delete_long, $fieldname);
                $form = new Form(
                    'index.php?module=myshowcase-fields&amp;action=do_delfield&amp;fid=' . $mybb->input['fid'] . '&amp;setid=' . $mybb->input['setid'],
                    'post',
                    'do_delete'
                );
                $buttons[] = $form->generate_submit_button($lang->myshowcase_field_confirm_delete);
                $form->output_submit_wrapper($buttons);

                $form->end();
            }

            $plugins->run_hooks('admin_myshowcase_fields_delete_end');
        }
    }
}

//delete field set
if ($mybb->input['action'] == 'do_delfield') {
    if (isset($mybb->input['fid']) && is_numeric($mybb->input['fid']) && $mybb->request_method == 'post') {
        $page->add_breadcrumb_item($lang->myshowcase_admin_edit_fieldset, 'index.php?module=myshowcase-fields');
        $page->output_header($lang->myshowcase_admin_fields);

        //get field name being deleted
        $query = $db->simple_select(
            'myshowcase_fields',
            'name',
            'setid=' . $mybb->input['setid'] . ' AND fid=' . $mybb->input['fid']
        );
        $result = $db->fetch_array($query);
        $fieldname = $result['name'];

        $plugins->run_hooks('admin_myshowcase_fields_dodelete_begin');

        //delete actual field
        $query = $db->delete_query(
            'myshowcase_fields',
            'setid=' . $mybb->input['setid'] . ' AND fid=' . $mybb->input['fid']
        );
        if ($db->affected_rows($query) != 1) {
            flash_message($lang->myshowcase_field_delete_failed, 'error');
            admin_redirect('index.php?module=myshowcase-fields&amp;action=editset&amp;setid=' . $mybb->input['setid']);
        }

        //delete field data if select type
        $query = $db->delete_query(
            'myshowcase_field_data',
            'setid=' . $mybb->input['setid'] . ' AND fid=' . $mybb->input['fid']
        );

        //edit language file if can be edited
        $retval = modify_lang(
            'myshowcase_fs' . $mybb->input['setid'], array(),
            array('myshowcase_field_' . $fieldname => ''),
            'english',
            false
        );

        $plugins->run_hooks('admin_myshowcase_fields_dodelete_end');

        myshowcase_update_cache('config');
        myshowcase_update_cache('fields');
        myshowcase_update_cache('field_data');
        myshowcase_update_cache('fieldsets');

        // Log admin action
        $log = array('setid' => $mybb->input['setid'], 'fid' => $mybb->input['fid']);
        log_admin_action($log);

        flash_message($lang->myshowcase_field_delete_success, 'success');
        admin_redirect('index.php?module=myshowcase-fields&amp;action=editset&amp;setid=' . $mybb->input['setid']);
    }
}
//default output
if ($mybb->input['action'] == '') {
    $page->output_header($lang->myshowcase_admin_fields);

    $plugins->run_hooks('admin_myshowcase_fields_sets_start');

    $form = new Form('index.php?module=myshowcase-fields&amp;action=new', 'post', 'new');

    //existing field sets
    $form_container = new FormContainer($lang->myshowcase_fields_title);

    $form_container->output_row_header($lang->myshowcase_fields_id, array('width' => '5%', 'class' => 'align_center'));
    $form_container->output_row_header($lang->myshowcase_fields_name, array('width' => '15%', 'class' => 'align_center')
    );
    $form_container->output_row_header(
        $lang->myshowcase_fields_count,
        array('width' => '25%', 'class' => 'align_center')
    );
    $form_container->output_row_header(
        $lang->myshowcase_fields_assigned_to,
        array('width' => '10%', 'class' => 'align_center')
    );
    $form_container->output_row_header(
        $lang->myshowcase_fields_used_by,
        array('width' => '10%', 'class' => 'align_center')
    );
    $form_container->output_row_header(
        $lang->myshowcase_fields_lang_exists,
        array('width' => '10%', 'class' => 'align_center')
    );
    $form_container->output_row_header($lang->controls, array('width' => '10%', 'class' => 'align_center'));

    $query = $db->simple_select('myshowcase_fieldsets', 'setid, setname', '1=1');
    $num_fieldsets = $db->num_rows($query);

    if ($num_fieldsets == 0) {
        $form_container->output_cell($lang->myshowcase_fields_no_fieldsets, array('colspan' => 6));
    } else {
        while ($result = $db->fetch_array($query)) {
            $query_used_in = $db->query(
                'SELECT count(*) AS total FROM ' . TABLE_PREFIX . 'myshowcase_config WHERE fieldsetid=' . $result['setid']
            );
            $num_used = $db->fetch_array($query_used_in);

            $query_fields = $db->query(
                'SELECT count(*) AS total FROM ' . TABLE_PREFIX . 'myshowcase_fields WHERE setid=' . $result['setid']
            );
            $num_fields = $db->fetch_array($query_fields);

            $query_tables = $db->simple_select('myshowcase_config', 'id', 'fieldsetid=' . $result['setid']);
            $tables = 0;
            while ($usetable = $db->fetch_array($query_tables)) {
                if ($db->table_exists('myshowcase_data' . $usetable['id'])) {
                    $tables++;
                }
            }

            // Build popup menu
            $popup = new PopupMenu("fieldset_{$result['setid']}", $lang->options);
            $popup->add_item(
                $lang->edit,
                "index.php?module=myshowcase-fields&amp;action=editset&amp;setid={$result['setid']}"
            );
            $popup->add_item(
                $lang->delete,
                "index.php?module=myshowcase-fields&amp;action=delset&amp;setid={$result['setid']}"
            );

            //grab status images for language file
            $langfile = $langpath . '/myshowcase_fs' . $result['setid'] . '.lang.php';
            if (file_exists($langfile)) {
                if (is_writable($langfile)) {
                    $status_image = "styles/{$page->style}/images/icons/tick.gif";
                    $status_alt = $lang->myshowcase_fields_lang_exists_yes;
                } else {
                    $status_image = "styles/{$page->style}/images/icons/warning.gif";
                    $status_alt = $lang->myshowcase_fields_lang_exists_write;
                }
            } else {
                $status_image = "styles/{$page->style}/images/icons/error.gif";
                $status_alt = $lang->myshowcase_fields_lang_exists_no;
            }

            $form_container->output_cell($result['setid'], array('class' => 'align_center'));
            $form_container->output_cell(
                $form->generate_text_box('setname[' . $result['setid'] . ']', $result['setname'])
            );
            $form_container->output_cell($num_fields['total'], array('class' => 'align_center'));
            $form_container->output_cell($num_used['total'], array('class' => 'align_center'));
            $form_container->output_cell($tables, array('class' => 'align_center'));
            $form_container->output_cell(
                '<img src="' . $status_image . '" title="' . $status_alt . '">',
                array('class' => 'align_center')
            );
            $form_container->output_cell($popup->fetch(), array('class' => 'align_center'));
            $form_container->construct_row();
        }
    }
    $form_container->end();

    $forumdir = str_replace($mybb->settings['homeurl'], '.', $mybb->settings['bburl']);

    //new set
    $form_container = new FormContainer($lang->myshowcase_fields_new);

    $form_container->output_row_header($lang->myshowcase_fields_name, array('width' => '25%', 'class' => 'align_left'));

    $form_container->output_cell($form->generate_text_box('newname', ''));
    $form_container->construct_row();
    $form_container->end();

    $buttons[] = $form->generate_submit_button($lang->myshowcase_fields_save_changes);
    $form->output_submit_wrapper($buttons);

    $form->end();
}

$plugins->run_hooks('admin_myshowcase_fields_end');

$myshowcase_info = myshowcase_info();
echo '<p /><small>' . $myshowcase_info['name'] . ' version ' . $myshowcase_info['version'] . ' &copy; 2006-' . COPY_YEAR . ' <a href="' . $myshowcase_info['website'] . '">' . $myshowcase_info['author'] . '</a>.</small>';
$page->output_footer();


/**
 * Create or edit language file.
 *
 * @param string Language file name or title.
 * @param array Language variables to add (key is varname, value is value).
 * @param array Language variables to remove (key is varname, value is value).
 * @param string Actual language.
 * @param bool Specifies if the language file is for ACP
 */
function modify_lang($name, $items_add = array(), $items_drop = array(), $language = 'english', $isadmin = false)
{
    global $lang, $mybb;

    //init var to free any lingering language file variables
    $l = array();

    //make sure items_xxx are populated arrays
    if (count($items_add) == 0 && count($items_drop) == 0) {
        return false;
    }

    //force lowercase for file name purposes
    $language = my_strtolower($language);
    $name = my_strtolower($name);

    //get path to requested language folder
    if (!$isadmin) {
        $langpath = $lang->path . '/' . $language;
    } else {
        $langpath = $lang->path . $language . '/admin/';
    }

    //check if path exists and is writable
    if (!is_writable($langpath)) {
        return false;
    }

    //set full language file path
    $langpath = $langpath . '/' . $name . '.lang.php';

    //try to get existing file
    if (file_exists($langpath)) {
        //check if file is writable
        if (!is_writable($langpath)) {
            return false;
        }

        include($langpath);
    }

    //merge existing language from file with new items
    //items last so it will update/replace existing
    $l = array_merge($l, $items_add);

    //diff arrays to drop the items requested
    $l = array_diff_key($l, $items_drop);

    //get plugin info
    $myshowcase = myshowcase_info();

    //open for writing
    $fp = fopen($langpath, 'w');

    //write file header
    fwrite($fp, '<?php' . PHP_EOL);

    $new_line = '/**' . PHP_EOL;
    $new_line .= ' * MyShowcase System for MyBB - ' . $lang->language . ' Language File ' . PHP_EOL;
    $new_line .= ' * Copyright 2010 PavementSucks.com, All Rights Reserved' . PHP_EOL;
    $new_line .= ' *' . PHP_EOL;
    $new_line .= ' * Website: http://www.pavementsucks.com/plugins.html' . PHP_EOL;
    $new_line .= ' * Version ' . $myshowcase['version'] . PHP_EOL;
    $new_line .= ' * License:' . PHP_EOL;
    $new_line .= ' * File: \inc\langauges\\' . $lang->language . '\myshowcase.lang.php ' . PHP_EOL;
    $new_line .= ' *' . PHP_EOL;
    $new_line .= ' * MyShowcase language file for fieldset' . PHP_EOL;
    $new_line .= ' */' . PHP_EOL . PHP_EOL;

    fwrite($fp, $new_line);

    //write items
    foreach ($l as $key => $value) {
        $new_line = '$l[\'' . $key . '\'] = "' . $value . '";' . PHP_EOL;
        fwrite($fp, $new_line);
    }

    fwrite($fp, '?>' . PHP_EOL);
    fclose($fp);

    unset($l);
    unset($fp);
    unset($langpath);

    return true;
}

?>
