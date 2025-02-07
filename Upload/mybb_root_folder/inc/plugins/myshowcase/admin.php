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

use DirectoryIterator;

use function MyShowcase\Core\cacheUpdate;
use function MyShowcase\Core\getTemplatesList;
use function MyShowcase\Core\loadLanguage;
use function MyShowcase\Core\showcaseDataTableExists;

use const MyShowcase\Core\VERSION;
use const MyShowcase\Core\VERSION_CODE;
use const MyShowcase\ROOT;

function pluginInformation(): array
{
    global $lang;

    loadLanguage();

    $donate_button =
        '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=NZ574GV99KXTU&item_name=Donation%20for%20MyShowcase" style="float:right;margin-top:-8px;padding:4px;" target="_blank"><img src="https://www.paypalobjects.com/WEBSCR-640-20110306-1/en_US/i/btn/btn_donate_SM.gif" /></a>';

    return [
        'name' => 'MyShowcase System',
        'description' => $donate_button . $lang->MyShowcaseSystemDescription,
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
            'version' => '2.0.4',
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
    global $db;

    static $isInstalledEach = null;

    if ($isInstalledEach === null) {
        $isInstalledEach = true;

        foreach (dbTables() as $tableName => $tableData) {
            $isInstalledEach = $db->table_exists($tableName) && $isInstalledEach;
        }
    }

    return $isInstalledEach;
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

function dbTables(): array
{
    return [
        'myshowcase_attachments' => [
            'id' => "int(3) NOT NULL default '1'",
            'aid' => 'int(10) unsigned NOT NULL auto_increment',
            'gid' => "int(10) NOT NULL default '0'",
            'posthash' => "varchar(50) NOT NULL default ''",
            'uid' => "int(10) unsigned NOT NULL default '0'",
            'filename' => "varchar(120) NOT NULL default ''",
            'filetype' => "varchar(120) NOT NULL default ''",
            'filesize' => "int(10) NOT NULL default '0'",
            'attachname' => "varchar(120) NOT NULL default ''",
            'downloads' => "int(10) unsigned NOT NULL default '0'",
            'dateuploaded' => "bigint(30) NOT NULL default '0'",
            'thumbnail' => "varchar(120) NOT NULL default ''",
            'visible' => "smallint(1) NOT NULL default '0'",
            'primary_key' => 'aid',
            'unique_key' => ['posthash' => 'posthash', 'gid' => 'gid', 'uid' => 'uid']
        ],
        'myshowcase_comments' => [
            'id' => "int(3) NOT NULL default '1'",
            'cid' => 'int(10) NOT NULL auto_increment',
            'gid' => 'int(10) NOT NULL',
            'uid' => 'int(10) NOT NULL',
            'ipaddress' => "varchar(30) NOT NULL default '0.0.0.0'",
            'comment' => 'text',
            'dateline' => 'bigint(30) NOT NULL',
            'primary_key' => 'cid`,`gid',
        ],
        'myshowcase_config' => [
            'id' => 'int(3) NOT NULL auto_increment',
            'name' => 'varchar(50) NOT NULL',
            'description' => 'varchar(255) NOT NULL',
            'mainfile' => 'varchar(50) NOT NULL',
            'fieldsetid' => "int(3) NOT NULL default '1'",
            'imgfolder' => 'varchar(255) default NULL',
            'defaultimage' => 'varchar(50) default NULL',
            'watermarkimage' => 'varchar(50) default NULL',
            'watermarkloc' => "varchar(12) default 'lower-right'",
            'use_attach' => "INT( 1 ) NOT NULL DEFAULT '0'",
            'f2gpath' => 'varchar(255) NOT NULL',
            'enabled' => "int(1) NOT NULL default '0'",
            'allowsmilies' => "smallint(1) NOT NULL default '0'",
            'allowbbcode' => "smallint(1) NOT NULL default '0'",
            'allowhtml' => "smallint(1) NOT NULL default '0'",
            'prunetime' => "varchar(10) NOT NULL default '0'",
            'modnewedit' => "smallint(1) NOT NULL default '1'",
            'othermaxlength' => "smallint(5) NOT NULL default '500'",
            'allow_attachments' => "smallint(1) NOT NULL default '1'",
            'allow_comments' => "smallint(1) NOT NULL default '1'",
            'thumb_width' => "smallint(4) NOT NULL default '200'",
            'thumb_height' => "smallint(4) NOT NULL default '200'",
            'comment_length' => "smallint(5) NOT NULL default '200'",
            'comment_dispinit' => "smallint(2) NOT NULL default '5'",
            'disp_attachcols' => "smallint(2) NOT NULL default '0'",
            'disp_empty' => "smallint(1) NOT NULL default '1'",
            'link_in_postbit' => "smallint(1) NOT NULL default '0'",
            'portal_random' => "smallint(1) NOT NULL default '0'",
            'primary_key' => 'id',
            'unique_key' => ['name' => 'name']
        ],
        'myshowcase_fieldsets' => [
            'setid' => 'int(3) NOT NULL auto_increment',
            'setname' => 'varchar(50) NOT NULL',
            'primary_key' => 'setid',
        ],
        'myshowcase_permissions' => [
            'pid' => 'INT( 10 ) NOT NULL AUTO_INCREMENT',
            'id' => "INT( 10 ) NOT NULL DEFAULT  '0'",
            'gid' => "INT( 10 ) NOT NULL DEFAULT  '0'",
            'canview' => "INT( 1 ) NOT NULL DEFAULT  '0'",
            'canadd' => "INT( 1 ) NOT NULL DEFAULT  '0'",
            'canedit' => "INT( 1 ) NOT NULL DEFAULT  '0'",
            'cancomment' => "INT( 1 ) NOT NULL DEFAULT  '0'",
            'canattach' => "INT( 1 ) NOT NULL DEFAULT  '0'",
            'canviewcomment' => "INT( 1 ) NOT NULL DEFAULT  '0'",
            'canviewattach' => "INT( 1 ) NOT NULL DEFAULT  '0'",
            'candelowncomment' => "INT( 1 ) NOT NULL DEFAULT  '0'",
            'candelauthcomment' => "INT( 1 ) NOT NULL DEFAULT  '0'",
            'cansearch' => "INT( 1 ) NOT NULL DEFAULT  '0'",
            'canwatermark' => "INT( 1 ) NOT NULL DEFAULT  '0'",
            'attachlimit' => "INT( 4 ) NOT NULL DEFAULT  '0'",
            'primary_key' => 'pid',
        ],
        'myshowcase_moderators' => [
            'mid' => 'int(5) NOT NULL auto_increment',
            'id' => 'int(5) NOT NULL',
            'uid' => 'mediumint(10) NOT NULL',
            'isgroup' => 'smallint(1) NOT NULL default 0',
            'canmodapprove' => "int(1) NOT NULL default '0'",
            'canmodedit' => "int(1) NOT NULL default '0'",
            'canmoddelete' => "int(1) NOT NULL default '0'",
            'canmoddelcomment' => "int(1) NOT NULL default '0'",
            'primary_key' => 'mid',
            'unique_key' => ['id' => 'id,uid']
        ],
        'myshowcase_reports' => [
            'rid' => 'INT( 10 ) UNSIGNED NOT NULL AUTO_INCREMENT',
            'id' => 'INT( 3 ) UNSIGNED NOT NULL',
            'gid' => 'INT( 10 ) UNSIGNED NOT NULL',
            'reporteruid' => 'INT( 10 ) UNSIGNED NOT NULL',
            'authoruid' => 'INT( 10 ) UNSIGNED NOT NULL',
            'status' => "INT( 1 ) UNSIGNED NOT NULL DEFAULT '0'",
            'reason' => 'VARCHAR( 250 ) NOT NULL',
            'dateline' => 'BIGINT( 30 ) NOT NULL',
            'primary_key' => 'rid',
        ],
        'myshowcase_fields' => [
            'setid' => 'int(3) NOT NULL',
            'fid' => 'mediumint(9) NOT NULL auto_increment',
            'name' => 'varchar(30) NOT NULL',
            'html_type' => 'varchar(10) NOT NULL',
            'enabled' => 'smallint(6) NOT NULL',
            'field_type' => "varchar(10) NOT NULL default 'varchar'",
            'min_length' => "smallint(6) NOT NULL default '0'",
            'max_length' => "smallint(6) NOT NULL default '0'",
            'require' => "tinyint(4) NOT NULL default '0'",
            'parse' => "tinyint(4) NOT NULL default '0'",
            'field_order' => 'tinyint(4) NOT NULL',
            'list_table_order' => "smallint(6) NOT NULL default '-1'",
            'searchable' => "smallint(1) NOT NULL default '0'",
            'format' => "varchar(10) NOT NULL default 'no'",
            'primary_key' => 'fid',
            'unique_key' => ['id' => 'setid,fid']
        ],
        'myshowcase_field_data' => [
            'setid' => 'smallint(3) NOT NULL',
            'fid' => 'mediumint(5) NOT NULL',
            'name' => 'varchar(15) NOT NULL',
            'valueid' => "smallint(3) NOT NULL default '0'",
            'value' => 'varchar(15) NOT NULL',
            'disporder' => 'smallint(6) NOT NULL',
            'primary_key' => 'setid',
            'unique_key' => ['name' => 'name']
        ],
    ];
}

function dbColumns(): array
{
    return [
    ];
}

function dbVerifyIndex(): bool
{
    global $db;

    foreach (dbTables() as $table => $fields) {
        if (!$db->table_exists($table)) {
            continue;
        }

        if (isset($fields['unique_key'])) {
            foreach ($fields['unique_key'] as $k => $v) {
                if ($db->index_exists($table, $k)) {
                    continue;
                }

                $db->write_query("ALTER TABLE {$db->table_prefix}{$table} ADD UNIQUE KEY {$k} ({$v})");
            }
        }
    }

    return true;
}

function dbVerifyTables(): bool
{
    global $db;

    $collation = $db->build_create_table_collation();

    foreach (dbTables() as $table => $fields) {
        if ($db->table_exists($table)) {
            foreach ($fields as $field => $definition) {
                if ($field == 'primary_key' || $field == 'unique_key') {
                    continue;
                }

                if ($db->field_exists($field, $table)) {
                    $db->modify_column($table, "`{$field}`", $definition);
                } else {
                    $db->add_column($table, $field, $definition);
                }
            }
        } else {
            $query = "CREATE TABLE IF NOT EXISTS `{$db->table_prefix}{$table}` (";

            $comma = '';

            foreach ($fields as $field => $definition) {
                if ($field == 'primary_key') {
                    $query .= "{$comma}PRIMARY KEY (`{$definition}`)";
                } elseif ($field != 'unique_key') {
                    $query .= "{$comma}`{$field}` {$definition}";
                }

                $comma = ',';
            }

            $query .= ") ENGINE=MyISAM{$collation};";

            $db->write_query($query);
        }
    }

    dbVerifyIndex();

    return true;
}

function dbVerifyColumns(): bool
{
    global $db;

    foreach (dbColumns() as $tableName => $tableData) {
        foreach ($tableData as $fieldName => $fieldDefinition) {
            if ($db->field_exists($fieldName, $tableName)) {
                $db->modify_column($tableName, "`{$fieldName}`", $fieldDefinition);
            } else {
                $db->add_column($tableName, $fieldName, $fieldDefinition);
            }
        }
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