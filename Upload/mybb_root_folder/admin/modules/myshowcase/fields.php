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

declare(strict_types=1);

// Disallow direct access to this file for security reasons
use function MyShowcase\Core\cacheUpdate;
use function MyShowcase\Core\showcaseDataTableExists;

use const MyShowcase\Core\CACHE_TYPE_CONFIG;
use const MyShowcase\Core\CACHE_TYPE_FIELD_DATA;
use const MyShowcase\Core\CACHE_TYPE_FIELD_SETS;
use const MyShowcase\Core\CACHE_TYPE_FIELDS;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $lang, $cache, $db, $plugins, $mybb;
global $page;

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
if ($mybb->get_input('action') == 'new') {
    $page->add_breadcrumb_item($lang->myshowcase_admin_create_new, 'index.php?module=myshowcase-fields');
    $page->output_header($lang->myshowcase_admin_fields);

    if ($mybb->request_method == 'post') {
        $plugins->run_hooks('admin_myshowcase_fields_insert_begin');

        //update any existing names
        foreach ($mybb->get_input('setname', MyBB::INPUT_ARRAY) as $setid => $setname) {
            $update_array = [
                'setname' => $db->escape_string($setname),
            ];
            $db->update_query('myshowcase_fieldsets', $update_array, "setid='{$setid}'");

            if ($db->affected_rows()) {
                $log = ['setid' => $setid, 'setname' => $setname];
                log_admin_action($log);
            }
        }

        //add new set
        if (!empty($mybb->get_input('newname'))) {
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

            $newname = $db->escape_string($mybb->get_input('newname'));
            $query = $db->simple_select('myshowcase_fieldsets', '*', "setname='{$newname}'");
            if ($db->num_rows($query) != 0) {
                flash_message($lang->myshowcase_fields_already_exists, 'error');
                admin_redirect('index.php?module=myshowcase-fields');
            }

            $insert_array = [
                'setname' => $db->escape_string($mybb->get_input('newname')),
            ];
            $db->insert_query('myshowcase_fieldsets', $insert_array);
            $id = $db->insert_id();

            //generate filename for this new fieldset
            $file = 'myshowcase_fs' . $id . '.lang.php';

            // Log admin action
            $log = ['id' => $id, 'myshowcase' => $mybb->get_input('newname')];
            log_admin_action($log);

            // Reset new myshowcase info
            unset($mybb->input['newname']);
        }

        cacheUpdate(CACHE_TYPE_CONFIG);
        cacheUpdate(CACHE_TYPE_FIELDS);
        cacheUpdate(CACHE_TYPE_FIELD_DATA);
        cacheUpdate(CACHE_TYPE_FIELD_SETS);
    }

    $plugins->run_hooks('admin_myshowcase_fields_insert_end');

    flash_message($lang->myshowcase_fields_update_success, 'success');
    admin_redirect('index.php?module=myshowcase-fields');
}

//edit fields in a field set
if ($mybb->get_input('action') == 'editset') {
    if ($mybb->get_input('setid', MyBB::INPUT_INT)) {
        //check if set is in use, if so, limit edit ability
        $can_edit = true;
        $query = $db->simple_select(
            'myshowcase_config',
            '*',
            'fieldsetid=' . $mybb->get_input('setid', MyBB::INPUT_INT)
        );
        $result = $db->fetch_array($query);
        if ($db->num_rows($query) != 0 && showcaseDataTableExists($result['id'])) {
            $can_edit = false;
        }

        //get lang file status
        $can_edit_lang = true;
        if (!is_writable($langpath) || (!is_writable(
                    $langpath . '/myshowcase_fs' . $mybb->get_input('setid', MyBB::INPUT_INT) . '.lang.php'
                ) && file_exists(
                    $langpath . '/myshowcase_fs' . $mybb->get_input('setid', MyBB::INPUT_INT) . '.lang.php'
                ))) {
            $can_edit_lang = false;
        }

        //check if set exists
        $query = $db->simple_select(
            'myshowcase_fieldsets',
            '*',
            'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT)
        );
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
            $query = $db->simple_select(
                'myshowcase_fields',
                '*',
                'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT)
            );
            while ($result = $db->fetch_array($query)) {
                if ($can_edit) {
                    //make field name valid and remove any starting/ending underscores after replacement
                    $newname = $db->escape_string($mybb->get_input('name')[$result['fid']]);
                    $newname = preg_replace('#[^\w]#', '_', $newname);
                    $origname = '';
                    while ($origname != $newname) {
                        $newname = trim($newname, '_');
                        $origname = $newname;
                    }

                    $update_array = [
                        'name' => strtolower($newname),
                        'field_order' => $db->escape_string(
                            $mybb->get_input('field_order', MyBB::INPUT_ARRAY)[$result['fid']]
                        ),
                        'list_table_order' => $db->escape_string(
                            $mybb->get_input('list_table_order', MyBB::INPUT_ARRAY)[$result['fid']]
                        ),
                        'enabled' => (isset($mybb->get_input('enabled', MyBB::INPUT_ARRAY)[$result['fid']]) ? 1 : 0),
                        'require' => (isset($mybb->get_input('require', MyBB::INPUT_ARRAY)[$result['fid']]) ? 1 : 0),
                        'parse' => (isset($mybb->get_input('parse', MyBB::INPUT_ARRAY)[$result['fid']]) ? 1 : 0),
                        'searchable' => (isset(
                            $mybb->get_input(
                                'searchable',
                                MyBB::INPUT_ARRAY
                            )[$result['fid']]
                        ) ? 1 : 0),
                        'format' => $mybb->get_input('format', MyBB::INPUT_ARRAY)[$result['fid']]
                    ];
                } else {
                    $update_array = [
                        'field_order' => $db->escape_string(
                            $mybb->get_input('field_order', MyBB::INPUT_ARRAY)[$result['fid']]
                        ),
                        'list_table_order' => $db->escape_string(
                            $mybb->get_input('list_table_order', MyBB::INPUT_ARRAY)[$result['fid']]
                        ),
                        'enabled' => (isset($mybb->get_input('enabled', MyBB::INPUT_ARRAY)[$result['fid']]) ? 1 : 0),
                        'require' => (isset($mybb->get_input('require', MyBB::INPUT_ARRAY)[$result['fid']]) ? 1 : 0),
                        'parse' => (isset($mybb->get_input('parse', MyBB::INPUT_ARRAY)[$result['fid']]) ? 1 : 0),
                        'searchable' => (isset(
                            $mybb->get_input(
                                'searchable',
                                MyBB::INPUT_ARRAY
                            )[$result['fid']]
                        ) ? 1 : 0),
                        'format' => $mybb->get_input('format', MyBB::INPUT_ARRAY)[$result['fid']]
                    ];
                }
                $update_query = $db->update_query(
                    'myshowcase_fields',
                    $update_array,
                    'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT) . ' AND fid=' . $result['fid']
                );

                if ($can_edit && ($mybb->get_input(
                            'html_type',
                            MyBB::INPUT_ARRAY
                        )[$result['fid']] == 'db' || $mybb->get_input(
                            'html_type',
                            MyBB::INPUT_ARRAY
                        )[$result['fid']] == 'radio')) {
                    $update_array = [
                        'name' => $db->escape_string($mybb->get_input('name')[$result['fid']])
                    ];
                    $update_query = $db->update_query(
                        'myshowcase_field_data',
                        $update_array,
                        'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT) . ' AND fid=' . $result['fid']
                    );
                }
            }

            //apply new field
            $do_new_field = 0;
            if ($mybb->get_input('newname') != '' && $mybb->get_input(
                    'newmax_length',
                    MyBB::INPUT_INT
                ) != '' && is_numeric(
                    $mybb->get_input('newmax_length', MyBB::INPUT_INT)
                ) && $mybb->get_input('newfield_order', MyBB::INPUT_INT) != '' && is_numeric(
                    $mybb->get_input('newfield_order', MyBB::INPUT_INT)
                ) && $mybb->get_input('newlist_table_order', MyBB::INPUT_INT) != '' && is_numeric(
                    $mybb->get_input('newlist_table_order', MyBB::INPUT_INT)
                )) {
                //set default field type/size for certain html types overwritting user entries
                $do_default_option_insert = 0;
                switch ($mybb->get_input('newhtml_type')) {
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
                        $mybb->input['newmin_length'] = max(
                            intval($mybb->get_input('newmin_length', MyBB::INPUT_INT)),
                            1901
                        );
                        $mybb->input['newmax_length'] = min(
                            intval($mybb->get_input('newmax_length', MyBB::INPUT_INT)),
                            2038
                        );

                        if ($mybb->get_input('newmin_length', MyBB::INPUT_INT) > $mybb->get_input(
                                'newmax_length',
                                MyBB::INPUT_INT
                            )) {
                            flash_message($lang->myshowcase_field_year_order, 'error');
                            admin_redirect(
                                'index.php?module=myshowcase-fields&amp;action=editset&amp;setid=' . $mybb->get_input(
                                    'setid',
                                    MyBB::INPUT_INT
                                )
                            );
                        }

                        break;

                    case 'url':
                        $mybb->input['newfield_type'] = 'varchar';
                        $mybb->input['newmax_length'] = 255;
                        break;
                }

                //make field name valid and remove any starting/ending underscores after replacement
                $newname = $db->escape_string($mybb->get_input('newname'));
                $newname = preg_replace('#[^\w]#', '_', $newname);
                $origname = '';
                while ($origname != $newname) {
                    $newname = trim($newname, '_');
                    $origname = $newname;
                }

                $insert_array = [
                    'setid' => $mybb->get_input('setid', MyBB::INPUT_INT),
                    'name' => strtolower($newname),
                    'html_type' => $mybb->get_input('newhtml_type'),
                    'field_type' => $mybb->get_input('newfield_type'),
                    'min_length' => intval($mybb->get_input('newmin_length', MyBB::INPUT_INT)),
                    'max_length' => intval($mybb->get_input('newmax_length', MyBB::INPUT_INT)),
                    'field_order' => intval($mybb->get_input('newfield_order', MyBB::INPUT_INT)),
                    'list_table_order' => intval($mybb->get_input('newlist_table_order', MyBB::INPUT_INT)),
                    'enabled' => intval($mybb->get_input('newenabled', MyBB::INPUT_INT)),
                    'require' => intval($mybb->get_input('newrequire', MyBB::INPUT_INT)),
                    'parse' => intval($mybb->get_input('newparse', MyBB::INPUT_INT)),
                    'searchable' => intval($mybb->get_input('newsearchable', MyBB::INPUT_INT)),
                    'format' => intval($mybb->get_input('newformat', MyBB::INPUT_INT)),
                ];

                $insert_query = $db->insert_query('myshowcase_fields', $insert_array);
                $do_new_field = 1;

                if ($do_default_option_insert) {
                    $insert_array = [
                        'setid' => $mybb->get_input('setid', MyBB::INPUT_INT),
                        'fid' => $insert_query,
                        'name' => strtolower($newname),
                        'value' => 'Not Specified',
                        'valueid' => 0,
                        'disporder' => 0
                    ];

                    $insert_query = $db->insert_query('myshowcase_field_data', $insert_array);
                }
            }

            //edit language file if can be edited
            if ($can_edit_lang) {
                //get existing fields from input
                $items_add = [];
                foreach ($mybb->get_input('name') as $key => $value) {
                    $items_add['myshowcase_field_' . $value] = ($mybb->get_input(
                        'label',
                        MyBB::INPUT_ARRAY
                    )[$key] == '' ? $db->escape_string(
                        $mybb->get_input('name')[$key]
                    ) : $db->escape_string($mybb->get_input('label', MyBB::INPUT_ARRAY)[$key]));
                }

                //write new field from input
                if ($do_new_field) {
                    $items_add['myshowcase_field_' . $db->escape_string(
                        $mybb->get_input('newname')
                    )] = ($mybb->get_input('newlabel') == '' ? $db->escape_string(
                        $mybb->get_input('newname')
                    ) : $db->escape_string($mybb->get_input('newlabel')));
                }

                $retval = modify_lang(
                    'myshowcase_fs' . $mybb->get_input('setid', MyBB::INPUT_INT),
                    $items_add,
                    [],
                    'english',
                    false
                );
            }

            flash_message($lang->myshowcase_field_update_success, 'success');
            admin_redirect(
                'index.php?module=myshowcase-fields&amp;action=editset&amp;setid=' . $mybb->get_input(
                    'setid',
                    MyBB::INPUT_INT
                )
            );
        }

        $form = new Form(
            'index.php?module=myshowcase-fields&amp;action=editset&amp;setid=' . $mybb->get_input(
                'setid',
                MyBB::INPUT_INT
            ),
            'post',
            'editset'
        );
        $form_container = new FormContainer($lang->myshowcase_field_list);

        $form_container->output_row_header(
            $lang->myshowcase_field_fid,
            ['width' => '5%', 'class' => 'align_center']
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_name,
            ['width' => '10%', 'class' => 'align_center']
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_label,
            ['width' => '10%', 'class' => 'align_center']
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_html_type,
            ['width' => '5%', 'class' => 'align_center']
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_field_type,
            ['width' => '5%', 'class' => 'align_center']
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_min_length,
            ['width' => '5%', 'class' => 'align_center']
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_max_length,
            ['width' => '5%', 'class' => 'align_center']
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_field_order,
            ['width' => '5%', 'class' => 'align_center']
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_list_table_order,
            ['width' => '4%', 'class' => 'align_center']
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_format,
            ['width' => '10%', 'class' => 'align_center']
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_enabled,
            ['width' => '4%', 'class' => 'align_center']
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_required,
            ['width' => '4%', 'class' => 'align_center']
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_parse,
            ['width' => '4%', 'class' => 'align_center']
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_searchable,
            ['width' => '4%', 'class' => 'align_center']
        );
        $form_container->output_row_header($lang->controls, ['width' => '10%', 'class' => 'align_center']);

        $max_order = 0;

        $query = $db->simple_select(
            'myshowcase_fields',
            '*',
            'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT),
            ['order_by' => 'fid']
        );
        $num_fields = $db->num_rows($query);
        if ($num_fields == 0) {
            $form_container->output_cell(
                $lang->myshowcase_fields_no_fields,
                ['class' => 'align_center', 'colspan' => 15]
            );
            $form_container->construct_row();
        } else {
            include($langpath . '/myshowcase_fs' . $mybb->get_input('setid', MyBB::INPUT_INT) . '.lang.php');
            $max_order = 1;
            while ($result = $db->fetch_array($query)) {
                // Build popup menu
                $popup = new PopupMenu("field_{$result['fid']}", $lang->options);
                $popup->add_item(
                    $lang->myshowcase_field_delete,
                    "index.php?module=myshowcase-fields&amp;action=delfield&amp;fid={$result['fid']}&amp;setid=" . $mybb->get_input(
                        'setid',
                        MyBB::INPUT_INT
                    )
                );

                //add option to edit list items if db type
                if ($result['html_type'] == 'db' || $result['html_type'] == 'radio') {
                    $popup->add_item(
                        $lang->myshowcase_field_edit_options,
                        "index.php?module=myshowcase-fields&amp;action=editopt&amp;fid={$result['fid']}&amp;setid=" . $mybb->get_input(
                            'setid',
                            MyBB::INPUT_INT
                        )
                    );
                }

                $max_order = max($max_order, $result['field_order']);
                $form_container->output_cell($result['fid'], ['class' => 'align_center']);
                if ($can_edit) {
                    $form_container->output_cell(
                        $form->generate_text_box(
                            'name[' . $result['fid'] . ']',
                            $result['name'],
                            ['id' => 'label[' . $result['fid'] . ']', 'style' => 'width: 100px']
                        ),
                        ['class' => 'align_left']
                    );
                } else {
                    $form_container->output_cell(
                        $result['name'] . $form->generate_hidden_field('name[' . $result['fid'] . ']', $result['name']),
                        ['class' => 'align_left']
                    );
                }
                if ($can_edit_lang) {
                    $form_container->output_cell(
                        $form->generate_text_box(
                            'label[' . $result['fid'] . ']',
                            $l['myshowcase_field_' . $result['name']] ?? '',
                            ['id' => 'label[' . $result['fid'] . ']', 'style' => 'width: 100px']
                        ),
                        ['class' => 'align_left']
                    );
                } else {
                    $form_container->output_cell(
                        $l['myshowcase_field_' . $result['name']],
                        ['class' => 'align_left']
                    );
                }
                $form_container->output_cell(
                    $result['html_type'] . $form->generate_hidden_field(
                        'html_type[' . $result['fid'] . ']',
                        $result['html_type']
                    ),
                    ['class' => 'align_center']
                );
                $form_container->output_cell(
                    $result['field_type'] . $form->generate_hidden_field(
                        'field_type[' . $result['fid'] . ']',
                        $result['field_type']
                    ),
                    ['class' => 'align_center']
                );
                $form_container->output_cell(
                    $result['min_length'] . $form->generate_hidden_field(
                        'min_length[' . $result['fid'] . ']',
                        $result['min_length']
                    ),
                    ['class' => 'align_center']
                );
                $form_container->output_cell(
                    $result['max_length'] . $form->generate_hidden_field(
                        'max_length[' . $result['fid'] . ']',
                        $result['max_length']
                    ),
                    ['class' => 'align_center']
                );
                $form_container->output_cell(
                    $form->generate_text_box(
                        'field_order[' . $result['fid'] . ']',
                        $result['field_order'],
                        ['id' => 'field_order[' . $result['fid'] . ']', 'style' => 'width: 35px']
                    ),
                    ['class' => 'align_center']
                );
                $form_container->output_cell(
                    $form->generate_text_box(
                        'list_table_order[' . $result['fid'] . ']',
                        $result['list_table_order'],
                        ['id' => 'list_table_order[' . $result['fid'] . ']', 'style' => 'width: 35px']
                    ),
                    ['class' => 'align_center']
                );
                $form_container->output_cell(
                    $form->generate_select_box(
                        'format[' . $result['fid'] . ']',
                        $field_format_options,
                        $result['format'],
                        ['id' => 'format[' . $result['fid'] . ']']
                    ),
                    ['class' => 'align_center']
                );
                $form_container->output_cell(
                    $form->generate_check_box(
                        'enabled[' . $result['fid'] . ']',
                        'true',
                        null,
                        ['checked' => $result['enabled']],
                        ''
                    ),
                    ['class' => 'align_center']
                );
                $form_container->output_cell(
                    $form->generate_check_box(
                        'require[' . $result['fid'] . ']',
                        'true',
                        null,
                        ['checked' => $result['require']],
                        ''
                    ),
                    ['class' => 'align_center']
                );
                $form_container->output_cell(
                    $form->generate_check_box(
                        'parse[' . $result['fid'] . ']',
                        'true',
                        null,
                        ['checked' => $result['parse']],
                        ''
                    ),
                    ['class' => 'align_center']
                );
                $form_container->output_cell(
                    $form->generate_check_box(
                        'searchable[' . $result['fid'] . ']',
                        'true',
                        null,
                        ['checked' => $result['searchable']],
                        ''
                    ),
                    ['class' => 'align_center']
                );
                $form_container->output_cell($popup->fetch(), ['class' => 'align_center']);
                $form_container->construct_row();
            }
        }
        $form_container->end();

        if ($can_edit) {
            echo '<br /><br />';
            $form_container = new FormContainer($lang->myshowcase_field_new);

            $form_container->output_row_header(
                $lang->myshowcase_field_name,
                ['width' => '10%', 'class' => 'align_center']
            );
            $form_container->output_row_header(
                $lang->myshowcase_field_label,
                ['width' => '10%', 'class' => 'align_center']
            );
            $form_container->output_row_header(
                $lang->myshowcase_field_html_type,
                ['width' => '5%', 'class' => 'align_center']
            );
            $form_container->output_row_header(
                $lang->myshowcase_field_field_type,
                ['width' => '5%', 'class' => 'align_center']
            );
            $form_container->output_row_header(
                $lang->myshowcase_field_min_length,
                ['width' => '5%', 'class' => 'align_center']
            );
            $form_container->output_row_header(
                $lang->myshowcase_field_max_length,
                ['width' => '5%', 'class' => 'align_center']
            );
            $form_container->output_row_header(
                $lang->myshowcase_field_field_order,
                ['width' => '5%', 'class' => 'align_center']
            );
            $form_container->output_row_header(
                $lang->myshowcase_field_list_table_order,
                ['width' => '4%', 'class' => 'align_center']
            );
            $form_container->output_row_header(
                $lang->myshowcase_field_format,
                ['width' => '10%', 'class' => 'align_center']
            );
            /*			$form_container->output_row_header($lang->myshowcase_field_enabled, array("width" => "4%", "class" => "align_center"));
                        $form_container->output_row_header($lang->myshowcase_field_required, array("width" => "4%", "class" => "align_center"));
                        $form_container->output_row_header($lang->myshowcase_field_parse, array("width" => "4%", "class" => "align_center"));
                        $form_container->output_row_header($lang->myshowcase_field_searchable, array("width" => "4%", "class" => "align_center"));*/

            $form_container->output_cell(
                $form->generate_text_box('newname', '', ['id' => 'newname', 'style' => 'width: 100px']),
                ['class' => 'align_center']
            );
            $form_container->output_cell(
                $form->generate_text_box('newlabel', '', ['id' => 'newlabel', 'style' => 'width: 100px']),
                ['class' => 'align_center']
            );
            $form_container->output_cell(
                $form->generate_select_box('newhtml_type', $html_type_options, '', ['id' => 'newhtml_type']),
                ['class' => 'align_center']
            );
            $form_container->output_cell(
                $form->generate_select_box('newfield_type', $field_type_options, '', ['id' => 'newfield_type']),
                ['class' => 'align_center']
            );
            $form_container->output_cell(
                $form->generate_text_box(
                    'newmin_length',
                    '',
                    ['id' => 'newmin_length', 'style' => 'width: 50px']
                ),
                ['class' => 'align_center']
            );
            $form_container->output_cell(
                $form->generate_text_box(
                    'newmax_length',
                    '',
                    ['id' => 'newmax_length', 'style' => 'width: 50px']
                ),
                ['class' => 'align_center']
            );
            $form_container->output_cell(
                $form->generate_text_box(
                    'newfield_order',
                    $max_order + 1,
                    ['id' => 'newfield_order', 'style' => 'width: 35px']
                ),
                ['class' => 'align_center']
            );
            $form_container->output_cell(
                $form->generate_text_box(
                    'newlist_table_order',
                    -1,
                    ['id' => 'newlist_table_order', 'style' => 'width: 35px']
                ),
                ['class' => 'align_center']
            );
            $form_container->output_cell(
                $form->generate_select_box('newformat', $field_format_options, '', ['id' => 'newformat']),
                ['class' => 'align_center']
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

        cacheUpdate(CACHE_TYPE_CONFIG);
        cacheUpdate(CACHE_TYPE_FIELDS);
        cacheUpdate(CACHE_TYPE_FIELD_DATA);
        cacheUpdate(CACHE_TYPE_FIELD_SETS);
    } else {
        flash_message($lang->myshowcase_fields_invalid_id, 'error');
        admin_redirect('index.php?module=myshowcase-fields');
    }
}

//delete field set
if ($mybb->get_input('action') == 'delset') {
    if ($mybb->get_input('setid', MyBB::INPUT_INT)) {
        $query = $db->simple_select(
            'myshowcase_fieldsets',
            '*',
            'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT)
        );
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

            $query = $db->simple_select(
                'myshowcase_config',
                '*',
                'fieldsetid=' . $mybb->get_input('setid', MyBB::INPUT_INT)
            );
            if ($db->num_rows($query) != 0) {
                flash_message($lang->myshowcase_fields_in_use, 'error');
                admin_redirect('index.php?module=myshowcase-fields');
            } else {
                $result = $db->fetch_array($query);
                echo $lang->sprintf($lang->myshowcase_fields_confirm_delete_long, $setname);
                $form = new Form(
                    'index.php?module=myshowcase-fields&amp;action=do_delset&amp;setid=' . $mybb->get_input(
                        'setid',
                        MyBB::INPUT_INT
                    ),
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
if ($mybb->get_input('action') == 'do_delset') {
    if ($mybb->get_input('setid', MyBB::INPUT_INT) && $mybb->request_method == 'post') {
        $page->add_breadcrumb_item($lang->myshowcase_admin_edit_fieldset, 'index.php?module=myshowcase-fields');
        $page->output_header($lang->myshowcase_admin_fields);


        $query = $db->delete_query('myshowcase_fieldsets', 'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT));
        if ($db->affected_rows($query) != 1) {
            flash_message($lang->myshowcase_fields_delete_failed, 'error');
            admin_redirect('index.php?module=myshowcase-fields');
        } else {
            $plugins->run_hooks('admin_myshowcase_fields_dodeleteset_begin');

            $query = $db->delete_query('myshowcase_fields', 'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT));
            $query = $db->delete_query('myshowcase_field_data', 'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT));

            //also delete langauge files for this fieldset
            $langs = $lang->get_languages(false);
            foreach ($langs as $langfolder => $langname) {
                $langfile = $lang->path . '/' . $langfolder . '/myshowcase_fs' . $mybb->get_input(
                        'setid',
                        MyBB::INPUT_INT
                    ) . '.lang.php';
                unlink($langfile);
            }

            $plugins->run_hooks('admin_myshowcase_fields_dodeleteset_end');

            cacheUpdate(CACHE_TYPE_CONFIG);
            cacheUpdate(CACHE_TYPE_FIELDS);
            cacheUpdate(CACHE_TYPE_FIELD_DATA);
            cacheUpdate(CACHE_TYPE_FIELD_SETS);

            // Log admin action
            $log = ['setid' => $mybb->get_input('setid', MyBB::INPUT_INT)];
            log_admin_action($log);

            flash_message($lang->myshowcase_field_delete_success, 'success');
            admin_redirect('index.php?module=myshowcase-fields');
        }
    }
}

//edit specific field in a field set (DB or select types)
if ($mybb->get_input('action') == 'editopt') {
    if ($mybb->get_input('fid', MyBB::INPUT_INT)) {
        //check if set is in use, if so, limit edit ability
        $can_edit = true;
        $query = $db->simple_select(
            'myshowcase_config',
            '*',
            'fieldsetid=' . $mybb->get_input('setid', MyBB::INPUT_INT)
        );
        $result = $db->fetch_array($query);

        if ($db->num_rows($query) != 0 && showcaseDataTableExists($result['id'])) {
            //flash_message($lang->myshowcase_fields_in_use, 'error');
            //admin_redirect("index.php?module=myshowcase-fields");
            $can_edit = false;
        }

        //get set name
        $query = $db->simple_select(
            'myshowcase_fieldsets',
            'setname',
            'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT)
        );
        $result = $db->fetch_array($query);
        $setname = $result['setname'];

        //check if DB/radio type field exists
        $query = $db->simple_select(
            'myshowcase_fields',
            '*',
            'setid=' . $mybb->get_input(
                'setid',
                MyBB::INPUT_INT
            ) . ' AND fid=' . $mybb->get_input('fid', MyBB::INPUT_INT) . " AND html_type In ('db', 'radio')"
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
                'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT) . ' AND fid=' . $mybb->get_input(
                    'fid',
                    MyBB::INPUT_INT
                )
            );
            while ($result = $db->fetch_array($query)) {
                $update_array = [
                    'value' => $db->escape_string($mybb->get_input('value', MyBB::INPUT_ARRAY)[$result['valueid']]),
                    'disporder' => $db->escape_string(
                        $mybb->get_input('disporder', MyBB::INPUT_ARRAY)[$result['valueid']]
                    )
                ];

                $update_query = $db->update_query(
                    'myshowcase_field_data',
                    $update_array,
                    'setid=' . $mybb->get_input(
                        'setid',
                        MyBB::INPUT_INT
                    ) . ' AND fid=' . $mybb->get_input('fid', MyBB::INPUT_INT) . ' AND valueid=' . $result['valueid']
                );
            }

            //apply new field
            if ($mybb->get_input('newtext') != '' && $mybb->get_input(
                    'newvalueid',
                    MyBB::INPUT_INT
                ) != '' && is_numeric(
                    $mybb->get_input('newvalueid', MyBB::INPUT_INT)
                ) && $mybb->get_input('newdisporder', MyBB::INPUT_INT) != '' && is_numeric(
                    $mybb->get_input('newdisporder', MyBB::INPUT_INT)
                )) {
                $insert_array = [
                    'setid' => $mybb->get_input('setid', MyBB::INPUT_INT),
                    'fid' => $mybb->get_input('fid', MyBB::INPUT_INT),
                    'name' => $db->escape_string($mybb->get_input('newfieldname')),
                    'value' => $db->escape_string($mybb->get_input('newtext')),
                    'valueid' => $mybb->get_input('newvalueid', MyBB::INPUT_INT),
                    'disporder' => $mybb->get_input('newdisporder', MyBB::INPUT_INT)
                ];

                $insert_query = $db->insert_query('myshowcase_field_data', $insert_array);
            }

            cacheUpdate(CACHE_TYPE_CONFIG);
            cacheUpdate(CACHE_TYPE_FIELDS);
            cacheUpdate(CACHE_TYPE_FIELD_DATA);
            cacheUpdate(CACHE_TYPE_FIELD_SETS);

            flash_message($lang->myshowcase_field_update_opt_success, 'success');
            admin_redirect(
                'index.php?module=myshowcase-fields&amp;action=editopt&amp;fid=' . $mybb->get_input(
                    'fid',
                    MyBB::INPUT_INT
                ) . '&amp;setid=' . $mybb->get_input(
                    'setid',
                    MyBB::INPUT_INT
                )
            );
        }

        $form = new Form(
            'index.php?module=myshowcase-fields&amp;action=editopt&amp;fid=' . $mybb->get_input(
                'fid',
                MyBB::INPUT_INT
            ) . '&amp;setid=' . $mybb->get_input(
                'setid',
                MyBB::INPUT_INT
            ),
            'post',
            'editopt'
        );
        $form_container = new FormContainer($lang->myshowcase_field_list);

        $form_container->output_row_header(
            $lang->myshowcase_field_fdid,
            ['width' => '5%', 'class' => 'align_center']
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_option_text,
            ['width' => '10%', 'class' => 'align_center']
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_disporder,
            ['width' => '5%', 'class' => 'align_center']
        );
        $form_container->output_row_header($lang->controls, ['width' => '10%', 'class' => 'align_center']);

        $max_order = 0;

        $query = $db->simple_select(
            'myshowcase_field_data',
            '*',
            'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT) . ' AND fid=' . $mybb->get_input(
                'fid',
                MyBB::INPUT_INT
            ),
            ['order_by' => 'valueid']
        );
        $num_fields = $db->num_rows($query);
        if ($num_fields == 0) {
            $form_container->output_cell(
                $lang->myshowcase_field_no_options,
                ['class' => 'align_center', 'colspan' => 5]
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
                        "index.php?module=myshowcase-fields&amp;action=delopt&amp;setid={$mybb->get_input('setid', MyBB::INPUT_INT)}&amp;fid={$mybb->get_input('fid', MyBB::INPUT_INT)}&amp;valueid={$result['valueid']}"
                    );
                }

                $max_order = max($max_order, $result['disporder']);
                $max_valueid = max($max_valueid, $result['valueid']);
                $form_container->output_cell(
                    $result['valueid'] . $form->generate_hidden_field(
                        'fieldname[' . $result['valueid'] . ']',
                        $result['name']
                    ),
                    ['class' => 'align_center']
                );

                $form_container->output_cell(
                    $form->generate_text_box(
                        'value[' . $result['valueid'] . ']',
                        $result['value'],
                        ['id' => 'value[' . $result['valueid'] . ']', 'style' => 'width: 105px']
                    ),
                    ['class' => 'align_center']
                );

                $form_container->output_cell(
                    $form->generate_text_box(
                        'disporder[' . $result['valueid'] . ']',
                        $result['disporder'],
                        ['id' => 'disporder[' . $result['valueid'] . ']', 'style' => 'width: 65px']
                    ),
                    ['class' => 'align_center']
                );
                if ($can_edit) {
                    $form_container->output_cell($popup->fetch(), ['class' => 'align_center']);
                } else {
                    $form_container->output_cell('N/A', ['class' => 'align_center']);
                }
                $form_container->construct_row();
            }
        }
        $form_container->end();

        echo '<br /><br />';
        $form_container = new FormContainer($lang->myshowcase_field_new_option);

        $form_container->output_row_header(
            $lang->myshowcase_field_option_text . ' *',
            ['width' => '65', 'class' => 'align_center']
        );
        $form_container->output_row_header(
            $lang->myshowcase_field_disporder,
            ['width' => '65', 'class' => 'align_center']
        );

        $form_container->output_cell(
            $form->generate_text_box('newtext', '', ['id' => 'newname', 'style' => 'width: 150px']) .
            $form->generate_hidden_field('newfieldname', $fieldname) .
            $form->generate_hidden_field('newvalueid', $max_valueid + 1)
            ,
            ['class' => 'align_center']
        );
        $form_container->output_cell(
            $form->generate_text_box(
                'newdisporder',
                $max_order + 1,
                ['id' => 'newdisporder', 'style' => 'width: 150px']
            ),
            ['class' => 'align_center']
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
if ($mybb->get_input('action') == 'delopt') {
    if ($mybb->get_input('valueid', MyBB::INPUT_INT)) {
        $page->add_breadcrumb_item($lang->myshowcase_admin_edit_fieldset, 'index.php?module=myshowcase-fields');
        $page->output_header($lang->myshowcase_admin_fields);

        $query = $db->simple_select(
            'myshowcase_field_data',
            '*',
            'setid=' . $mybb->get_input(
                'setid',
                MyBB::INPUT_INT
            ) . ' AND fid=' . $mybb->get_input('fid', MyBB::INPUT_INT) . ' AND valueid=' . $mybb->get_input(
                'valueid',
                MyBB::INPUT_INT
            )
        );
        $result = $db->fetch_array($query);
        $fieldname = $result['name'];
        if ($db->num_rows($query) == 0) {
            flash_message($lang->myshowcase_field_invalid_opt, 'error');
        } else {
            $plugins->run_hooks('admin_myshowcase_fields_delopt_begin');

            $query = $db->simple_select(
                'myshowcase_config',
                '*',
                'fieldsetid=' . $mybb->get_input('setid', MyBB::INPUT_INT)
            );
            if ($db->num_rows($query) != 0) {
                flash_message($lang->myshowcase_fields_in_use, 'error');
                admin_redirect(
                    'index.php?module=myshowcase-fields&amp;action=editopt&amp;fid=' . $mybb->get_input(
                        'fid',
                        MyBB::INPUT_INT
                    ) . '&amp;setid=' . $mybb->get_input(
                        'setid',
                        MyBB::INPUT_INT
                    )
                );
            } else {
                $query = $db->delete_query(
                    'myshowcase_field_data',
                    'setid=' . $mybb->get_input(
                        'setid',
                        MyBB::INPUT_INT
                    ) . ' AND fid=' . $mybb->get_input(
                        'fid',
                        MyBB::INPUT_INT
                    ) . ' AND valueid=' . $mybb->get_input('valueid', MyBB::INPUT_INT)
                );

                $plugins->run_hooks('admin_myshowcase_fields_delopt_end');

                cacheUpdate(CACHE_TYPE_CONFIG);
                cacheUpdate(CACHE_TYPE_FIELDS);
                cacheUpdate(CACHE_TYPE_FIELD_DATA);
                cacheUpdate(CACHE_TYPE_FIELD_SETS);

                // Log admin action
                $log = [
                    'setid' => $mybb->get_input('setid', MyBB::INPUT_INT),
                    'fid' => $mybb->get_input('fid', MyBB::INPUT_INT),
                    'valueid' => $mybb->get_input('valueid', MyBB::INPUT_INT)
                ];
                log_admin_action($log);

                flash_message($lang->myshowcase_field_delete_opt_success, 'success');
                admin_redirect(
                    'index.php?module=myshowcase-fields&amp;action=editopt&amp;setid=' . $mybb->get_input(
                        'setid',
                        MyBB::INPUT_INT
                    ) . '&amp;fid=' . $mybb->get_input('fid', MyBB::INPUT_INT)
                );
            }
        }
    }
}

//delete specific field
if ($mybb->get_input('action') == 'delfield') {
    if ($mybb->get_input('fid', MyBB::INPUT_INT)) {
        //check if set exists
        $query = $db->simple_select(
            'myshowcase_fieldsets',
            'setname',
            'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT)
        );
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
            'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT) . ' AND fid=' . $mybb->get_input(
                'fid',
                MyBB::INPUT_INT
            )
        );
        $result = $db->fetch_array($query);
        $fieldname = $result['name'];
        if ($fieldname == '') {
            flash_message($lang->myshowcase_field_invalid_id, 'error');
            admin_redirect(
                'index.php?module=myshowcase-fields&amp;action=editset&amp;setid=' . $mybb->get_input(
                    'setid',
                    MyBB::INPUT_INT
                )
            );
        } else {
            $plugins->run_hooks('admin_myshowcase_fields_delete_begin');

            //check if tables created from this fiedlset already
            $query = $db->simple_select(
                'myshowcase_config',
                'id',
                'fieldsetid=' . $mybb->get_input('setid', MyBB::INPUT_INT)
            );
            $field_in_use = false;
            while ($result = $db->fetch_array($query)) {
                if (showcaseDataTableExists($result['id'])) {
                    $field_in_use = true;
                }
            }

            if ($field_in_use) {
                flash_message($lang->myshowcase_field_in_use, 'error');
                admin_redirect(
                    'index.php?module=myshowcase-fields&amp;action=editset&amp;setid=' . $mybb->get_input(
                        'setid',
                        MyBB::INPUT_INT
                    )
                );
            } else {
                echo $lang->sprintf($lang->myshowcase_field_confirm_delete_long, $fieldname);
                $form = new Form(
                    'index.php?module=myshowcase-fields&amp;action=do_delfield&amp;fid=' . $mybb->get_input(
                        'fid',
                        MyBB::INPUT_INT
                    ) . '&amp;setid=' . $mybb->get_input(
                        'setid',
                        MyBB::INPUT_INT
                    ),
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
if ($mybb->get_input('action') == 'do_delfield') {
    if ($mybb->get_input('fid', MyBB::INPUT_INT) && $mybb->request_method == 'post') {
        $page->add_breadcrumb_item($lang->myshowcase_admin_edit_fieldset, 'index.php?module=myshowcase-fields');
        $page->output_header($lang->myshowcase_admin_fields);

        //get field name being deleted
        $query = $db->simple_select(
            'myshowcase_fields',
            'name',
            'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT) . ' AND fid=' . $mybb->get_input(
                'fid',
                MyBB::INPUT_INT
            )
        );
        $result = $db->fetch_array($query);
        $fieldname = $result['name'];

        $plugins->run_hooks('admin_myshowcase_fields_dodelete_begin');

        //delete actual field
        $query = $db->delete_query(
            'myshowcase_fields',
            'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT) . ' AND fid=' . $mybb->get_input(
                'fid',
                MyBB::INPUT_INT
            )
        );
        if ($db->affected_rows($query) != 1) {
            flash_message($lang->myshowcase_field_delete_failed, 'error');
            admin_redirect(
                'index.php?module=myshowcase-fields&amp;action=editset&amp;setid=' . $mybb->get_input(
                    'setid',
                    MyBB::INPUT_INT
                )
            );
        }

        //delete field data if select type
        $query = $db->delete_query(
            'myshowcase_field_data',
            'setid=' . $mybb->get_input('setid', MyBB::INPUT_INT) . ' AND fid=' . $mybb->get_input(
                'fid',
                MyBB::INPUT_INT
            )
        );

        //edit language file if can be edited
        $retval = modify_lang(
            'myshowcase_fs' . $mybb->get_input('setid', MyBB::INPUT_INT),
            [],
            ['myshowcase_field_' . $fieldname => ''],
            'english',
            false
        );

        $plugins->run_hooks('admin_myshowcase_fields_dodelete_end');

        cacheUpdate(CACHE_TYPE_CONFIG);
        cacheUpdate(CACHE_TYPE_FIELDS);
        cacheUpdate(CACHE_TYPE_FIELD_DATA);
        cacheUpdate(CACHE_TYPE_FIELD_SETS);

        // Log admin action
        $log = [
            'setid' => $mybb->get_input('setid', MyBB::INPUT_INT),
            'fid' => $mybb->get_input('fid', MyBB::INPUT_INT)
        ];
        log_admin_action($log);

        flash_message($lang->myshowcase_field_delete_success, 'success');
        admin_redirect(
            'index.php?module=myshowcase-fields&amp;action=editset&amp;setid=' . $mybb->get_input(
                'setid',
                MyBB::INPUT_INT
            )
        );
    }
}
//default output
if ($mybb->get_input('action') == '') {
    $page->output_header($lang->myshowcase_admin_fields);

    $plugins->run_hooks('admin_myshowcase_fields_sets_start');

    $form = new Form('index.php?module=myshowcase-fields&amp;action=new', 'post', 'new');

    //existing field sets
    $form_container = new FormContainer($lang->myshowcase_fields_title);

    $form_container->output_row_header($lang->myshowcase_fields_id, ['width' => '5%', 'class' => 'align_center']);
    $form_container->output_row_header($lang->myshowcase_fields_name, ['width' => '15%', 'class' => 'align_center']
    );
    $form_container->output_row_header(
        $lang->myshowcase_fields_count,
        ['width' => '25%', 'class' => 'align_center']
    );
    $form_container->output_row_header(
        $lang->myshowcase_fields_assigned_to,
        ['width' => '10%', 'class' => 'align_center']
    );
    $form_container->output_row_header(
        $lang->myshowcase_fields_used_by,
        ['width' => '10%', 'class' => 'align_center']
    );
    $form_container->output_row_header(
        $lang->myshowcase_fields_lang_exists,
        ['width' => '10%', 'class' => 'align_center']
    );
    $form_container->output_row_header($lang->controls, ['width' => '10%', 'class' => 'align_center']);

    $query = $db->simple_select('myshowcase_fieldsets', 'setid, setname', '1=1');
    $num_fieldsets = $db->num_rows($query);

    if ($num_fieldsets == 0) {
        $form_container->output_cell($lang->myshowcase_fields_no_fieldsets, ['colspan' => 6]);
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
                if (showcaseDataTableExists($usetable['id'])) {
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
                    $status_image = "styles/{$page->style}/images/icons/tick.png";
                    $status_alt = $lang->myshowcase_fields_lang_exists_yes;
                } else {
                    $status_image = "styles/{$page->style}/images/icons/warning.png";
                    $status_alt = $lang->myshowcase_fields_lang_exists_write;
                }
            } else {
                $status_image = "styles/{$page->style}/images/icons/error.png";
                $status_alt = $lang->myshowcase_fields_lang_exists_no;
            }

            $form_container->output_cell($result['setid'], ['class' => 'align_center']);
            $form_container->output_cell(
                $form->generate_text_box('setname[' . $result['setid'] . ']', $result['setname'])
            );
            $form_container->output_cell($num_fields['total'], ['class' => 'align_center']);
            $form_container->output_cell($num_used['total'], ['class' => 'align_center']);
            $form_container->output_cell($tables, ['class' => 'align_center']);
            $form_container->output_cell(
                '<img src="' . $status_image . '" title="' . $status_alt . '">',
                ['class' => 'align_center']
            );
            $form_container->output_cell($popup->fetch(), ['class' => 'align_center']);
            $form_container->construct_row();
        }
    }
    $form_container->end();

    $forumdir = str_replace($mybb->settings['homeurl'], '.', $mybb->settings['bburl']);

    //new set
    $form_container = new FormContainer($lang->myshowcase_fields_new);

    $form_container->output_row_header($lang->myshowcase_fields_name, ['width' => '25%', 'class' => 'align_left']);

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
function modify_lang(
    string $name,
    array $items_add = [],
    array $items_drop = [],
    string $language = 'english',
    bool $isadmin = false
): bool {
    global $lang, $mybb;

    //init var to free any lingering language file variables
    $l = [];

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

    fclose($fp);

    unset($l);
    unset($fp);
    unset($langpath);

    return true;
}