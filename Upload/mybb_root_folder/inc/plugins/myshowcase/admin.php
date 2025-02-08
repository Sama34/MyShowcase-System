<?php
/**
 * MyShowcase Plugin for MyBB - Main Plugin Controls
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\plugins\myshowcase\plugin.php
 *
 */

declare(strict_types=1);

namespace MyShowcase\Admin;

use function MyShowcase\Core\cacheUpdate;
use function MyShowcase\Core\getTemplatesList;
use function MyShowcase\Core\loadLanguage;
use function MyShowcase\Core\showcaseDataTableExists;

use const MyShowcase\Core\VERSION;
use const MyShowcase\Core\VERSION_CODE;

const TABLES_DATA = [
    'myshowcase_attachments' => [
        'aid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 1
        ],
        'gid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
        ],
        'posthash' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => '',
        ],
        'uid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
        ],
        'filename' => [
            'type' => 'VARCHAR',
            'size' => 120,
            'default' => ''
        ],
        'filetype' => [
            'type' => 'VARCHAR',
            'size' => 120,
            'default' => ''
        ],
        'filesize' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'attachname' => [
            'type' => 'VARCHAR',
            'size' => 120,
            'default' => ''
        ],
        'downloads' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'dateuploaded' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'thumbnail' => [
            'type' => 'VARCHAR',
            'size' => 120,
            'default' => ''
        ],
        'visible' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
    ],
    'myshowcase_comments' => [
        'cid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 1
        ],
        'gid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'uid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'ipaddress' => [
            'type' => 'VARCHAR',
            'size' => 30,
            'default' => ''
        ],
        'comment' => [
            'type' => 'TEXT',
            'null' => true
        ],
        'dateline' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'unique_key' => ['cid_gid' => 'cid,gid']
    ],
    'myshowcase_config' => [
        'id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'name' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => '',
            'unique_key' => true
        ],
        'description' => [
            'type' => 'VARCHAR',
            'size' => 255,
            'default' => ''
        ],
        'mainfile' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => ''
        ],
        'fieldsetid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 1
        ],
        'imgfolder' => [
            'type' => 'VARCHAR',
            'size' => 255,
            'default' => ''
        ],
        'defaultimage' => [
            'type' => 'VARCHAR',
            'null' => true,
            'size' => 50,
        ],
        'watermarkimage' => [
            'type' => 'VARCHAR',
            'null' => true,
            'size' => 50,
        ],
        'watermarkloc' => [
            'type' => 'VARCHAR',
            'size' => 12,
            'default' => 'lower-right'
        ],
        'use_attach' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'f2gpath' => [
            'type' => 'VARCHAR',
            'size' => 255,
            'default' => ''
        ],
        'enabled' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'allowsmilies' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'allowbbcode' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'allowhtml' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'prunetime' => [
            'type' => 'VARCHAR',
            'size' => 10,
            'default' => 0
        ],
        'modnewedit' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'othermaxlength' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 500
        ],
        'allow_attachments' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'allow_comments' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'thumb_width' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 200
        ],
        'thumb_height' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 200
        ],
        'comment_length' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 200
        ],
        'comment_dispinit' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 5
        ],
        'disp_attachcols' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'disp_empty' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'link_in_postbit' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'portal_random' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
    ],
    'myshowcase_fieldsets' => [
        'setid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'setname' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => ''
        ],
    ],
    'myshowcase_permissions' => [
        'pid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'gid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'canview' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canadd' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canedit' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'cancomment' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canattach' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canviewcomment' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canviewattach' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'candelowncomment' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'candelauthcomment' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'cansearch' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canwatermark' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'attachlimit' => [
            'type' => 'INT',
            'default' => 0
        ],
    ],
    'myshowcase_moderators' => [
        'mid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'uid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'isgroup' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canmodapprove' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canmodedit' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canmoddelete' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canmoddelcomment' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'unique_key' => ['id_uid_isgroup' => 'id,uid,isgroup']
    ],
    'myshowcase_reports' => [
        'rid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'gid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'reporteruid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'authoruid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'status' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'reason' => [
            'type' => 'VARCHAR',
            'size' => 250,
            'default' => ''
        ],
        'dateline' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
    ],
    'myshowcase_fields' => [
        'fid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'setid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'name' => [
            'type' => 'VARCHAR',
            'size' => 30,
            'default' => ''
        ],
        'html_type' => [
            'type' => 'VARCHAR',
            'size' => 10,
            'default' => ''
        ],
        'enabled' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'field_type' => [
            'type' => 'VARCHAR',
            'size' => 10,
            'default' => 'varchar'
        ],
        'min_length' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 0
        ],
        'max_length' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 0
        ],
        'requiredField' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ], // todo, rename from require to requiredField
        'parse' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'field_order' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'list_table_order' => [
            'type' => 'SMALLINT',
            'default' => -1
        ],
        'searchable' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'format' => [
            'type' => 'VARCHAR',
            'size' => 10,
            'default' => 'no'
        ],
        'unique_key' => ['setid_fid' => 'setid,fid']
    ],
    'myshowcase_field_data' => [
        'fieldDataID' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'setid' => [
            'type' => 'INT',
            'unsigned' => true,
        ],
        'fid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'name' => [
            'type' => 'VARCHAR',
            'size' => 15,
            'default' => '',
            //'unique_key' => true
        ],
        'valueid' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 0
        ],
        'value' => [
            'type' => 'VARCHAR',
            'size' => 15,
            'default' => ''
        ],
        'disporder' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 0
        ],
        'unique_key' => ['setid_fid_valueid' => 'setid,fid,valueid']
    ],
];

const FIELDS_DATA = [
];

const DATA_TABLE_STRUCTURE = [
    'myshowcase_data' => [
        'gid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'uid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'views' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
        ],
        'comments' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
        ],
        'submit_date' => [
            'type' => 'VARCHAR',
            'size' => 20,
            'default' => ''
        ],
        'dateline' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'createdate' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'approved' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'approved_by' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'posthash' => [
            'type' => 'VARCHAR',
            'size' => 32,
            'default' => ''
        ],
    ],
];

const FIELD_TYPE_VARCHAR = 'varchar';

const FIELD_TYPE_TEXT = 'text';

const FIELD_TYPE_INT = 'int';

const FIELD_TYPE_BIGINT = 'bigint';

const FIELD_TYPE_TIMESTAMP = 'timestamp';

function pluginInformation(): array
{
    global $lang;

    loadLanguage();

    $donate_button =
        '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=NZ574GV99KXTU&item_name=Donation%20for%20MyShowcase" style="float:right;margin-top:-8px;padding:4px;" target="_blank"><img src="https://www.paypalobjects.com/WEBSCR-640-20110306-1/en_US/i/btn/btn_donate_SM.gif" /></a>';

    return [
        'name' => 'MyShowcase System',
        'description' => $donate_button . $lang->myShowcaseSystemDescription,
        'website' => '',
        'author' => 'CommunityPlugins.com',
        'authorsite' => '',
        'version' => VERSION,
        'versioncode' => VERSION_CODE,
        'compatibility' => '18*',
        'codename' => 'ougc_myshowcase',
        'pl' => [
            'version' => 13,
            'url' => 'https://community.mybb.com/mods.php?action=view&pid=573'
        ],
        'myalerts' => [
            'version' => '2.1.0',
            'url' => 'https://community.mybb.com/thread-171301.html'
        ]
    ];
}

function pluginActivation(): bool
{
    global $PL, $cache;

    pluginUninstallation();

    loadPluginLibrary();

    $PL->settings('myshowcase', 'MyShowcase System', 'Options on how to configure the MyShowcase section.', [
        'delete_tables_on_uninstall' => [
            'title' => 'Drop MyShowcase tables when uninstalling?',
            'description' => 'When uninstalling, do you want to drop the showcase tables and delete the data in them? Reinstalling will not overwrite existing data if you select [no].',
            'optionscode' => 'yesno',
            'value' => 0
        ],
    ]);

    $templatesList = getTemplatesList();

    if ($templatesList) {
        $PL->templates('myshowcase', 'MyShowcase System', $templatesList);
    }

    // Insert/update version into cache
    $plugins = $cache->read('ougc_plugins');

    if (!$plugins) {
        $plugins = [];
    }

    $pluginInformation = pluginInformation();

    if (!isset($plugins['myshowcase'])) {
        $plugins['myshowcase'] = $pluginInformation['versioncode'];
    }

    dbVerifyTables();

    dbVerifyColumns();

    taskInstallation();

    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    find_replace_templatesets(
        'header',
        '#' . preg_quote('{$pm_notice}') . '#',
        '{$pm_notice}{$myshowcase_unapproved}{$myshowcase_reported}'
    );

    /*~*~* RUN UPDATES START *~*~*/

    /*~*~* RUN UPDATES END *~*~*/

    $plugins['myshowcase'] = $pluginInformation['versioncode'];

    $cache->update('ougc_plugins', $plugins);

    foreach (['config', 'field_data', 'fieldsets', 'fields', 'permissions', 'moderators', 'reports'] as $key) {
        cacheUpdate($key);
    }

    return true;
}

function pluginDeactivation(): bool
{
    include MYBB_ROOT . '/inc/adminfunctions_templates.php';

    find_replace_templatesets('header', '#' . preg_quote('{$myshowcase_unapproved}') . '#', '');

    find_replace_templatesets('header', '#' . preg_quote('{$myshowcase_reported}') . '#', '');

    _deactivate_task();

    return true;
}

function pluginInstallation(): bool
{
    dbVerifyTables();

    dbVerifyColumns();

    return true;
}

function pluginIsInstalled(): bool
{
    return dbVerifyTablesExists() && dbVerifyColumnsExists() && dbVerifyColumnsExists(TABLES_DATA);
}

function pluginUninstallation(): bool
{
    global $db, $PL, $mybb;

    loadPluginLibrary();

    //drop tables but only if setting specific to allow uninstall is enabled
    if (!empty($mybb->settings['myshowcase_delete_tables_on_uninstall'])) {
        //get list of possible tables from config and delete
        $query = $db->simple_select('myshowcase_config', 'id');

        while ($showcaseData = $db->fetch_array($query)) {
            if (showcaseDataTableExists($showcaseData['id'])) {
                $db->drop_table('myshowcase_data' . $showcaseData['id']);
            }
        }

        foreach (dbTables() as $tableName => $tableData) {
            $db->drop_table($tableName);
        }

        foreach (dbColumns() as $tableName => $tableData) {
            foreach ($tableData as $fieldName => $fieldDefinition) {
                !$db->field_exists($fieldName, $tableName) || $db->drop_column($tableName, $fieldName);
            }
        }
    }

    $PL->settings_delete('myshowcase');

    $PL->templates_delete('myshowcase');

    // Delete version from cache
    $plugins = (array)$mybb->cache->read('ougc_plugins');

    if (isset($plugins['myshowcase'])) {
        unset($plugins['myshowcase']);
    }

    if (!empty($plugins)) {
        $mybb->cache->update('ougc_plugins', $plugins);
    } else {
        $mybb->cache->delete('ougc_plugins');
    }

    foreach (
        [
            'myshowcase_mylaerts',
            'myshowcase_config',
            'myshowcase_fields',
            'myshowcase_field_data',
            'myshowcase_fieldsets',
            'myshowcase_permissions',
            'myshowcase_moderators',
            'myshowcase_version'
        ] as $cache_name
    ) {
        $mybb->cache->delete($cache_name);
    }

    taskUninstallation();

    return true;
}

function loadPluginLibrary(bool $check = true): bool
{
    global $PL, $lang;

    loadLanguage();

    if ($file_exists = file_exists(PLUGINLIBRARY)) {
        global $PL;

        $PL || require_once PLUGINLIBRARY;
    }

    if (!$check) {
        return false;
    }

    $_info = pluginInformation();

    if (!$file_exists || $PL->version < $_info['pl']['version']) {
        flash_message(
            $lang->sprintf($lang->MyShowcaseSystemPluginLibrary, $_info['pl']['url'], $_info['pl']['version']),
            'error'
        );

        admin_redirect('index.php?module=config-plugins');
    }

    return true;
}

function taskInstallation(int $action = 1): bool
{
    global $db, $lang, $cache, $new_task;

    loadLanguage();

    $query = $db->simple_select('tasks', '*', "file='myshowcase'", ['limit' => 1]);

    $task = $db->fetch_array($query);

    if ($task) {
        $db->update_query('tasks', ['enabled' => $action], "file='myshowcase'");
    } else {
        include_once MYBB_ROOT . 'inc/functions_task.php';

        $_ = $db->escape_string('*');

        $new_task = [
            'title' => $db->escape_string('MyShowcase'),
            'description' => $db->escape_string('Prunes showcases where enabled.'),
            'file' => $db->escape_string('myshowcase'),
            'minute' => 9,
            'hour' => 4,
            'day' => $_,
            'weekday' => $_,
            'month' => $_,
            'enabled' => 1,
            'logging' => 1
        ];

        $new_task['nextrun'] = fetch_next_run($new_task);

        $cache->update_tasks();

        $db->insert_query('tasks', $new_task);
    }

    return true;
}

function taskUninstallation(): bool
{
    global $db, $cache, $tid;

    $tid = $db->delete_query('tasks', "file='myshowcase'");

    $cache->update_tasks();

    return true;
}

function _deactivate_task(): bool
{
    global $cache;

    taskInstallation(0);

    $cache->update_tasks();

    return true;
}

function dbTables(array $tableObjects = TABLES_DATA): array
{
    $tablesData = [];

    foreach ($tableObjects as $tableName => $tableColumns) {
        foreach ($tableColumns as $fieldName => $fieldData) {
            if (!isset($fieldData['type'])) {
                continue;
            }

            $tablesData[$tableName][$fieldName] = buildDbFieldDefinition($fieldData);
        }

        foreach ($tableColumns as $fieldName => $fieldData) {
            if (isset($fieldData['primary_key'])) {
                $tablesData[$tableName]['primary_key'] = $fieldName;
            }
            if ($fieldName === 'unique_key') {
                $tablesData[$tableName]['unique_key'] = $fieldData;
            }
        }
    }

    return $tablesData;
}

function dbVerifyTables(array $tableObjects = TABLES_DATA): bool
{
    global $db;

    $collation = $db->build_create_table_collation();

    $tablePrefix = $db->table_prefix;

    foreach (dbTables($tableObjects) as $tableName => $tableData) {
        if ($db->table_exists($tableName)) {
            foreach ($tableData as $fieldName => $fieldData) {
                if ($fieldName == 'primary_key' || $fieldName == 'unique_key') {
                    continue;
                }

                if ($db->field_exists($fieldName, $tableName)) {
                    $db->modify_column($tableName, "`{$fieldName}`", $fieldData);
                } else {
                    var_dump($tableName, $fieldName, $fieldData);
                    $db->add_column($tableName, $fieldName, $fieldData);
                }
            }
        } else {
            $query = "CREATE TABLE IF NOT EXISTS `{$tablePrefix}{$tableName}` (";

            $fields = [];

            foreach ($tableData as $fieldName => $fieldData) {
                if ($fieldName === 'primary_key') {
                    $fields[] = "PRIMARY KEY (`{$fieldData}`)";
                } elseif ($fieldName !== 'unique_key') {
                    $fields[] = "`{$fieldName}` {$fieldData}";
                }
            }

            $query .= implode(',', $fields);

            $query .= ") ENGINE=MyISAM{$collation};";

            $db->write_query($query);
        }
    }

    dbVerifyIndexes();

    return true;
}

function dbVerifyIndexes(array $tableObjects = TABLES_DATA): bool
{
    global $db;

    $tablePrefix = $db->table_prefix;

    foreach (dbTables($tableObjects) as $tableName => $tableData) {
        if (!$db->table_exists($tableName)) {
            continue;
        }

        if (isset($tableData['unique_key'])) {
            foreach ($tableData['unique_key'] as $keyName => $keyValue) {
                if ($db->index_exists($tableName, $keyName)) {
                    continue;
                }

                $db->write_query("ALTER TABLE {$tablePrefix}{$tableName} ADD UNIQUE KEY {$keyName} ({$keyValue})");
            }
        }
    }

    return true;
}

function dbVerifyColumns(array $fieldObjects = FIELDS_DATA): bool
{
    global $db;

    foreach ($fieldObjects as $tableName => $tableColumns) {
        foreach ($tableColumns as $fieldName => $fieldData) {
            if (!isset($fieldData['type'])) {
                continue;
            }

            if ($db->field_exists($fieldName, $tableName)) {
                $db->modify_column($tableName, "`{$fieldName}`", buildDbFieldDefinition($fieldData));
            } else {
                $db->add_column($tableName, $fieldName, buildDbFieldDefinition($fieldData));
            }
        }
    }

    return true;
}

function dbVerifyTablesExists(array $tableObjects = TABLES_DATA): bool
{
    global $db;

    $isInstalledEach = true;

    foreach (dbTables($tableObjects) as $tableName => $tableData) {
        $isInstalledEach = $db->table_exists($tableName) && $isInstalledEach;
    }

    return $isInstalledEach;
}

function dbVerifyColumnsExists(array $fieldObjects = FIELDS_DATA): bool
{
    global $db;

    $isInstalledEach = true;

    foreach ($fieldObjects as $tableName => $tableColumns) {
        if (!$db->table_exists($tableName)) {
            $isInstalledEach = false;

            continue;
        }

        foreach ($tableColumns as $fieldName => $fieldData) {
            if (!isset($fieldData['type'])) {
                continue;
            }

            $isInstalledEach = $db->field_exists($fieldName, $tableName) && $isInstalledEach;
        }
    }

    return $isInstalledEach;
}

function buildDbFieldDefinition(array $fieldData): string
{
    $fieldDefinition = '';

    $fieldDefinition .= $fieldData['type'];

    if (isset($fieldData['size'])) {
        $fieldDefinition .= "({$fieldData['size']})";
    }

    if (isset($fieldData['unsigned'])) {
        if ($fieldData['unsigned'] === true) {
            $fieldDefinition .= ' UNSIGNED';
        } else {
            $fieldDefinition .= ' SIGNED';
        }
    }

    if (!isset($fieldData['null'])) {
        $fieldDefinition .= ' NOT';
    }

    $fieldDefinition .= ' NULL';

    if (isset($fieldData['auto_increment'])) {
        $fieldDefinition .= ' AUTO_INCREMENT';
    }

    if (isset($fieldData['default'])) {
        $fieldDefinition .= " DEFAULT '{$fieldData['default']}'";
    }

    return $fieldDefinition;
}

/**
 * Create or edit language file.
 *
 * @param string Language file name or title.
 * @param array Language variables to add (key is varname, value is value).
 * @param array Language variables to remove (key is varname, value is value).
 * @param string Actual language.
 * @param bool Specifies if the language file is for ACP
 */
function languageModify(
    string $languageFileName,
    array $languageVariablesAddition = [],
    array $languageVariablesDelete = [],
    string $languageName = 'english',
    bool $isAdministrator = false
): bool {
    global $lang;

    if (count($languageVariablesAddition) == 0 && count($languageVariablesDelete) == 0) {
        return false;
    }

    $languageName = my_strtolower($languageName);

    $languageFileName = my_strtolower($languageFileName);

    if (!$isAdministrator) {
        $languagePath = $lang->path . '/' . $languageName;
    } else {
        $languagePath = $lang->path . $languageName . '/admin/';
    }

    if (!is_writable($languagePath)) {
        return false;
    }

    $languagePath = $languagePath . '/' . $languageFileName . '.lang.php';

    if (file_exists($languagePath)) {
        if (!is_writable($languagePath)) {
            return false;
        }

        include_once $languagePath;
    }

    if (!isset($l) || !is_array($l)) {
        $l = [];
    }

    $l = array_merge($l, $languageVariablesAddition);

    $l = array_diff_key($l, $languageVariablesDelete);

    $pluginInformation = myshowcase_info();

    $fp = fopen($languagePath, 'w');

    fwrite($fp, '<?php' . PHP_EOL);

    $languageLines = '/**' . PHP_EOL;
    $languageLines .= ' * MyShowcase System for MyBB - ' . $lang->language . ' Language File ' . PHP_EOL;
    $languageLines .= ' * Copyright 2010 PavementSucks.com, All Rights Reserved' . PHP_EOL;
    $languageLines .= ' *' . PHP_EOL;
    $languageLines .= ' * Website: http://www.pavementsucks.com/plugins.html' . PHP_EOL;
    $languageLines .= ' * Version ' . $pluginInformation['version'] . PHP_EOL;
    $languageLines .= ' * License:' . PHP_EOL;
    $languageLines .= ' * File: \inc\langauges\\' . $lang->language . '\myshowcase.lang.php ' . PHP_EOL;
    $languageLines .= ' *' . PHP_EOL;
    $languageLines .= ' * MyShowcase language file for fieldset' . PHP_EOL;
    $languageLines .= ' */' . PHP_EOL . PHP_EOL;

    fwrite($fp, $languageLines);

    foreach ($l as $languageVariableKey => $languageVariableValue) {
        $languageVariableValue = ucfirst($languageVariableValue);

        $languageLines = "\$l['{$languageVariableKey}'] = '{$languageVariableValue}';" . PHP_EOL;

        fwrite($fp, $languageLines);
    }

    fclose($fp);

    unset($l, $fp, $languagePath);

    return true;
}