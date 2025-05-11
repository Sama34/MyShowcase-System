<?php
/**
 * MyShowcase Plugin for MyBB - Main Plugin Controls
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \MyShowcase\plugin.php
 *
 */

declare(strict_types=1);

namespace MyShowcase\Hooks\Admin;

use FormContainer;
use MyBB;

use function MyShowcase\Admin\buildPermissionsRow;
use function MyShowcase\Core\cacheUpdate;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\Core\loadLanguage;
use function MyShowcase\Core\permissionsDelete;
use function MyShowcase\Core\sanitizeTableFieldValue;

use const MyShowcase\Core\CACHE_TYPE_PERMISSIONS;
use const MyShowcase\Core\FIELDS_DATA;
use const MyShowcase\Core\TABLES_DATA;

function admin_config_plugins_deactivate(): bool
{
    global $mybb, $page;

    if (
        $mybb->get_input('action') != 'deactivate' ||
        $mybb->get_input('plugin') != 'myshowcase' ||
        !$mybb->get_input('uninstall', MyBB::INPUT_INT)
    ) {
        return false;
    }

    if ($mybb->request_method != 'post') {
        $page->output_confirm_action(
            'index.php?module=config-plugins&amp;action=deactivate&amp;uninstall=1&amp;plugin=myshowcase'
        );
    }

    if ($mybb->get_input('no')) {
        admin_redirect('index.php?module=config-plugins');
    }

    return true;
}

/**
 * delete default permissions for any new groups.
 */
function admin_user_groups_delete_commit(): bool
{
    global $usergroup;

    permissionsDelete(["group_id='{$usergroup['gid']}'"]);

    cacheUpdate(CACHE_TYPE_PERMISSIONS);

    return true;
}

function report_content_types(array &$contentTypes): array
{
    loadLanguage();

    $contentTypes = array_merge($contentTypes, [
        'showcase_entries',
        'showcase_comments',
    ]);

    return $contentTypes;
}

function admin_user_groups_edit_graph_tabs(array &$tabs): array
{
    global $lang;

    loadLanguage();

    $tabs['myshowcase'] = $lang->MyShowcaseGroupsTab;

    return $tabs;
}

function admin_user_groups_edit_graph(): bool
{
    global $lang, $form, $mybb;

    loadLanguage();
    loadLanguage('myshowcase_summary');

    $tablesData = TABLES_DATA;

    $dataFields = FIELDS_DATA['usergroups'];

    echo '<div id="tab_myshowcase">';

    $formContainer = new FormContainer($lang->MyShowcaseGroupsTab);

    $formFields = $formFieldsRate = $formFieldsIncome = [];

    $hookArguments = [
        'tablesData' => &$tablesData,
        'dataFields' => &$dataFields,
        'formFields' => &$formFields,
        'formFieldsRate' => &$formFieldsRate,
        'formFieldsIncome' => &$formFieldsIncome
    ];

    $hookArguments = hooksRun('admin_user_groups_edit_graph_start', $hookArguments);

    $rowObjects = [];

    foreach ($tablesData['myshowcase_permissions'] as $fieldName => $fieldData) {
        if (!isset($fieldData['isPermission'])) {
            continue;
        }

        //_dump($fieldData['formCategory']);

        //$sectionKey = ucfirst($formSection);
        //formSection

        //$key = str_replace(' ', '', ucwords(str_replace('_', ' ', $fieldName)));

        //$rowObjects[$fieldData['formCategory']][$fieldName] = $lang->{'myShowcaseAdminSummaryPermissionsField' . $key};

        $rowObjects[$fieldData['formCategory']][$fieldName] = $fieldData;
    }

    foreach ($rowObjects as $formSection => $fieldObjects) {
        $sectionKey = ucfirst($formSection);

        $settingCode = '';

        foreach ($fieldObjects as $fieldName => $fieldData) {
            $key = str_replace(' ', '', ucwords(str_replace('_', ' ', $fieldName)));

            $settingCode .= buildPermissionsRow(
                $form,
                'myshowcase_' . $fieldName,
                $fieldData,
                $key,
                '',
                true,
                'myShowcaseAdminSummaryPermissionsField'
            );
        }

        $formContainer->output_row(
            $lang->{'myShowcaseAdminSummaryPermissionsFieldGroup' . $sectionKey},
            '',
            $settingCode
        );
    }


    $hookArguments = hooksRun('admin_user_groups_edit_graph_end', $hookArguments);

    $formContainer->end();

    echo '</div>';

    return true;
}

function admin_user_groups_edit_commit(): bool
{
    global $mybb, $db;
    global $updated_group;

    $dataFields = TABLES_DATA['myshowcase_permissions'];

    $hook_arguments = [
        'dataFields' => &$dataFields,
    ];

    $hook_arguments = hooksRun('admin_user_groups_edit_commit_start', $hook_arguments);

    foreach ($dataFields as $dataFieldKey => $dataFieldData) {
        if (!isset($dataFieldData['isPermission'])) {
            continue;
        }

        if (isset($mybb->input['myshowcase_' . $dataFieldKey])) {
            $updated_group['myshowcase_' . $dataFieldKey] = sanitizeTableFieldValue(
                $mybb->get_input('myshowcase_' . $dataFieldKey),
                $dataFieldData['type']
            );
        } else {
            $updated_group['myshowcase_' . $dataFieldKey] = $dataFieldData['default'];
        }
    }

    return true;
}