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

namespace MyShowcase\Admin;

use function MyShowcase\Core\load_language;
use function MyShowcase\Core\load_pluginlibrary;
use function MyShowcase\MyAlerts\getAvailableLocations;

function _info()
{
    global $lang;

    load_language();

    $myalerts_desc = '';

    /*if(_is_installed() && \MyShowcase\MyAlerts\MyAlertsIsIntegrable())
    {
        $myalerts_desc .= $lang->myshowcase_myalerts_desc;
    }*/
    $donate_button =
        '<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=NZ574GV99KXTU&item_name=Donation%20for%20MyShowcase" style="float:right;margin-top:-8px;padding:4px;" target="_blank"><img src="https://www.paypalobjects.com/WEBSCR-640-20110306-1/en_US/i/btn/btn_donate_SM.gif" /></a>';

    $lang->setting_group_myshowcase_desc = 'Create and manage showcase sections for user to enter information about their property or collections.';

    return [
        'name' => 'MyShowcase System',
        'description' => $donate_button . $myalerts_desc,
        'website' => '',
        'author' => 'CommunityPlugins.com',
        'authorsite' => '',
        'version' => '3.0.0',
        'versioncode' => 3000,
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

function _activate()
{
    global $PL, $lang, $cache, $db;

    load_pluginlibrary();

    $PL->settings('myshowcase', $lang->setting_group_myshowcase, $lang->setting_group_myshowcase_desc, [
        'groups' => [
            'title' => $lang->setting_myshowcase_groups,
            'description' => $lang->setting_myshowcase_groups_desc,
            'optionscode' => 'groupselect',
            'value' => -1,
        ],
        'forums' => [
            'title' => $lang->setting_myshowcase_forums,
            'description' => $lang->setting_myshowcase_forums_desc,
            'optionscode' => 'forumselect',
            'value' => -1,
        ],
        'allowempty' => [
            'title' => $lang->setting_myshowcase_allowempty,
            'description' => $lang->setting_myshowcase_allowempty_desc,
            'optionscode' => 'yesno',
            'value' => 0,
        ],
        'deletesubscriptions' => [
            'title' => $lang->setting_myshowcase_deletesubscriptions,
            'description' => $lang->setting_myshowcase_deletesubscriptions_desc,
            'optionscode' => 'yesno',
            'value' => 1,
        ],
        'allowgroupsbypass' => [
            'title' => $lang->setting_myshowcase_allowgroupsbypass,
            'description' => $lang->setting_myshowcase_allowgroupsbypass_desc,
            'optionscode' => 'groupselect',
            'value' => 4,
        ],
        'allowmodbypass' => [
            'title' => $lang->setting_myshowcase_allowmodbypass,
            'description' => $lang->setting_myshowcase_allowmodbypass_desc,
            'optionscode' => 'yesno',
            'value' => 1,
        ],
        'allowmodbypass' => [
            'title' => $lang->setting_myshowcase_allowmodbypass,
            'description' => $lang->setting_myshowcase_allowmodbypass_desc,
            'optionscode' => 'yesno',
            'value' => 1,
        ],
        'allowsearch' => [
            'title' => $lang->setting_myshowcase_allowsearch,
            'description' => $lang->setting_myshowcase_allowsearch_desc,
            'optionscode' => 'groupselect',
            'value' => -1,
        ],
        'displayprefix' => [
            'title' => $lang->setting_myshowcase_displayprefix,
            'description' => $lang->setting_myshowcase_displayprefix_desc,
            'optionscode' => 'yesno',
            'value' => 1,
        ],
        'displaystyle' => [
            'title' => $lang->setting_myshowcase_displaystyle,
            'description' => $lang->setting_myshowcase_displaystyle_desc,
            'optionscode' => 'text',
            'value' => 'trow_private',
        ],
        'displaylist' => [
            'title' => $lang->setting_myshowcase_displaylist,
            'description' => $lang->setting_myshowcase_displaylist_desc,
            'optionscode' => 'yesno',
            'value' => 1,
        ],
        'allowchange' => [
            'title' => $lang->setting_myshowcase_allowchange,
            'description' => $lang->setting_myshowcase_allowchange_desc,
            'optionscode' => 'yesno',
            'value' => 1,
        ],
        'notifications' => [
            'title' => $lang->setting_myshowcase_notifications,
            'description' => $lang->setting_myshowcase_notifications_desc,
            'optionscode' => "checkbox
myalerts={$lang->setting_myshowcase_notifications_mylaerts}
pm={$lang->setting_myshowcase_notifications_pm}",
            'value' => 'pm',
        ],
        'fixforumlastpost' => [
            'title' => $lang->setting_myshowcase_fixforumlastpost,
            'description' => $lang->setting_myshowcase_fixforumlastpost_desc,
            'optionscode' => 'onoff',
            'value' => 1,
        ],
        'fixforumcount' => [
            'title' => $lang->setting_myshowcase_fixforumcount,
            'description' => $lang->setting_myshowcase_fixforumcount_desc,
            'optionscode' => 'onoff',
            'value' => 0,
        ],
    ]);

    // Add template group
    _dump('templates');
    /* $templatesDirIterator = new \DirectoryIterator(MYSHOWCASE_ROOT.'/templates');

     $templates = [];

     foreach($templatesDirIterator as $template)
     {
         if(!$template->isFile())
         {
             continue;
         }

         $pathName = $template->getPathname();

         $pathInfo = pathinfo($pathName);

         if($pathInfo['extension'] === 'html')
         {
             $templates[$pathInfo['filename']] = file_get_contents($pathName);
         }
     }*/

    if ($templates) {
        $PL->templates('myshowcase', 'MyShowcase System', $templates);
    }

    // Insert/update version into cache
    $plugins = $cache->read('ougc_plugins');

    if (!$plugins) {
        $plugins = [];
    }

    $_info = _info();

    if (!isset($plugins['privatethreads'])) {
        $plugins['privatethreads'] = $_info['versioncode'];
    }

    _db_verify_tables();

    _db_verify_columns();

    _install_task();

    require_once MYBB_ROOT . 'inc/adminfunctions_templates.php';

    find_replace_templatesets('newthread', '#' . preg_quote('{$pollbox}') . '#', '{$pollbox}{$myshowcase_hide}');

    find_replace_templatesets('editpost', '#' . preg_quote('{$pollbox}') . '#', '{$pollbox}{$myshowcase_hide}');

    find_replace_templatesets(
        'showthread',
        '#' . preg_quote('{$usersbrowsing}') . '#',
        '{$usersbrowsing}{$myshowcase_hidden_list}'
    );

    find_replace_templatesets(
        'search',
        '#' . preg_quote('{$lang->show_results_posts}') . '#',
        '{$lang->show_results_posts}{$myshowcase_search}'
    );

    find_replace_templatesets(
        'search_results_threads_thread',
        '#' . preg_quote('{$thread[\'threadprefix\']}') . '#',
        '{$thread[\'threadprefix\']}{$thread[\'privateprefix\']}'
    );

    find_replace_templatesets(
        'search_results_posts_post',
        '#' . preg_quote('{$lang->post_thread}') . '#',
        '{$lang->post_thread}{$post[\'privateprefix\']}'
    );

    find_replace_templatesets(
        'forumdisplay_thread',
        '#' . preg_quote('{$prefix}') . '#',
        '{$prefix}{$thread[\'privateprefix\']}'
    );

    find_replace_templatesets(
        'forumdisplay_thread_modbit',
        '#' . preg_quote('{$bgcolor}') . '#',
        '{$bgcolor}{$thread[\'myshowcase_class\']}'
    );

    find_replace_templatesets(
        'forumdisplay_thread',
        '#' . preg_quote('{$bgcolor}') . '#',
        '{$bgcolor}{$thread[\'myshowcase_class\']}'
    );

    /*~*~* RUN UPDATES START *~*~*/

    /*~*~* RUN UPDATES END *~*~*/

    $plugins['privatethreads'] = $_info['versioncode'];

    $cache->update('ougc_plugins', $plugins);
}

function _deactivate()
{
    include MYBB_ROOT . '/inc/adminfunctions_templates.php';

    find_replace_templatesets('header', '#' . preg_quote('{$myshowcase_unapproved}') . '#', '');

    find_replace_templatesets('header', '#' . preg_quote('{$myshowcase_reported}') . '#', '');

    _deactivate_task();
}

function _install()
{
    global $cache;

    _db_verify_tables();

    _db_verify_columns();

    // MyAlerts
    $MyAlertLocationsInstalled = array_filter(
        getAvailableLocations(),
        '\\MyShowcase\MyAlerts\\isLocationAlertTypePresent'
    );

    $cache->update('myshowcase', [
        'MyAlertLocationsInstalled' => $MyAlertLocationsInstalled,
    ]);
}

function _is_installed()
{
    global $db;

    static $installed = null;

    if ($installed === null) {
        foreach (_db_tables() as $name => $table) {
            $installed = $db->table_exists($name);

            break;
        }
    }

    return $installed;
}

function _uninstall()
{
    global $db, $PL, $mybb;

    load_pluginlibrary();

    //drop tables but only if setting specific to allow uninstall is enabled
    if ($mybb->settings['myshowcase_delete_tables_on_uninstall'] == 1) {
        //get list of possible tables from config and delete
        $query = $db->simple_select('myshowcase_config', 'id');

        while ($result = $db->fetch_array($query)) {
            if ($db->table_exists('myshowcase_data' . $result['id'])) {
                $db->drop_table('myshowcase_data' . $result['id']);
            }
        }

        // Drop DB entries
        foreach (_db_tables() as $name => $table) {
            $db->drop_table($name);
        }

        foreach (_db_columns() as $table => $columns) {
            foreach ($columns as $name => $definition) {
                !$db->field_exists($name, $table) || $db->drop_column($table, $name);
            }
        }
    }

    $PL->settings_delete('myshowcase_');

    $PL->templates_delete('myshowcase_');

    // Delete version from cache
    $plugins = (array)$mybb->cache->read('ougc_plugins');

    if (isset($plugins['myshowcase_'])) {
        unset($plugins['myshowcase_']);
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

    _uninstall_task();
}

// List of tables
function _db_tables()
{
    global $db;

    $collation = $db->build_create_table_collation();

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
        'myshowcase_moderators' => [
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
            'unique_key' => ['id' => 'setid']
        ],
        'myshowcase_field_data' => [
            'setid' => 'smallint(3) NOT NULL',
            'fid' => 'mediumint(5) NOT NULL',
            'name' => 'varchar(15) NOT NULL',
            'valueid' => "smallint(3) NOT NULL default '0'",
            'value' => 'varchar(15) NOT NULL',
            'disporder' => 'smallint(6) NOT NULL',
            'primary_key' => 'setid',
            'unique_key' => ['setid' => 'setid', 'name' => 'name']
        ],
    ];
}

// List of columns
function _db_columns()
{
    return [
        'threads' => [
            'ougc_private_thread' => "int UNSIGNED NOT NULL DEFAULT '0'",
            'ougc_private_thread_users' => 'text NULL',
        ],
    ];
}

// Verify DB indexes
function _db_verify_indexes()
{
    global $db;

    foreach (_db_tables() as $table => $fields) {
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
}

// Verify DB tables
function _db_verify_tables()
{
    global $db;

    $collation = $db->build_create_table_collation();

    foreach (_db_tables() as $table => $fields) {
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

            foreach ($fields as $field => $definition) {
                if ($field == 'primary_key') {
                    $query .= "PRIMARY KEY (`{$definition}`)";
                } elseif ($field != 'unique_key') {
                    $query .= "`{$field}` {$definition},";
                }
            }

            $query .= ") ENGINE=MyISAM{$collation};";

            $db->write_query($query);
        }
    }

    _db_verify_indexes();
}

// Verify DB columns
function _db_verify_columns()
{
    global $db;

    foreach (_db_columns() as $table => $columns) {
        foreach ($columns as $field => $definition) {
            if ($db->field_exists($field, $table)) {
                $db->modify_column($table, "`{$field}`", $definition);
            } else {
                $db->add_column($table, $field, $definition);
            }
        }
    }
}

// Install task file
function _install_task($action = 1)
{
    global $db, $lang, $cache, $plugins, $new_task;

    load_language();

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

        $plugins->run_hooks('admin_tools_tasks_add_commit');

        $plugins->run_hooks('admin_tools_tasks_edit_commit');

        $cache->update_tasks();

        $db->insert_query('tasks', $new_task);
    }
}

function _uninstall_task()
{
    global $db, $cache, $plugins, $tid;

    $tid = $db->delete_query('tasks', "file='myshowcase'");

    $plugins->run_hooks('admin_tools_tasks_delete_commit');

    $cache->update_tasks();
}

function _deactivate_task()
{
    global $db, $cache;

    _install_task(0);

    $plugins->run_hooks('admin_tools_tasks_edit_commit');

    $cache->update_tasks();
}


/**
 * Plugin install
 *
 */
function myshowcase_plugin_install()
{
    global $db, $mybb, $config, $cache, $need_upgrade, $plugins;

    //load upgrade code
    include_once(MYBB_ROOT . 'inc/plugins/myshowcase/upgrade.php');

    //get this plugin's info so we have current version number
    $myshowcase = myshowcase_info();

    //get currently installed version, if there is one
    $oldver = '2.0.0'; //from beta release
    $cpcom_plugins = $cache->read('cpcom_plugins');
    if (is_array($cpcom_plugins)) {
        $oldver = $cpcom_plugins['versions']['myshowcase'];
    }

    //check versions
    if (version_compare($oldver, $myshowcase['version']) == -1) {
        $need_upgrade = array();
        $need_upgrade['prev'] = $oldver;
        $need_upgrade['this'] = $myshowcase['version'];
    }

    //write new version 
    //don't write new version here as this function is called before activate() and we need old version information in that function as well

    //upgrade procedure for pre-table install
    myshowcase_upgrade_install_pre_table($need_upgrade);

    //grab sample data if it exists
    $insert_sample_data = 0;
    if (file_exists(MYBB_ROOT . 'inc/plugins/myshowcase/sample_data.php')) {
        require_once(MYBB_ROOT . 'inc/plugins/myshowcase/sample_data.php');
        $insert_sample_data = 1;
    }

    //create table for field sets
    if (!$db->table_exists('myshowcase_fieldsets')) {
        //insert default data based on 4x4 trucks
        if ($insert_sample_data) {
            $sql = 'INSERT INTO ' . TABLE_PREFIX . 'myshowcase_fieldsets (`setname`) VALUES ';

            reset($custom_fieldsets);
            foreach ($custom_fieldsets as $fieldinfo) {
                $sql .= "('" . $fieldinfo[0] . "'),";
            }

            $sql = substr($sql, 0, strlen($sql) - 1);

            $db->query($sql);
        }
    }

    //create data for fields in a field set
    if (!$db->table_exists('myshowcase_fields')) {
        //insert default data based on 4x4 trucks
        if ($insert_sample_data) {
            $sql = 'INSERT INTO ' . TABLE_PREFIX . 'myshowcase_fields (`setid`, `name`, `html_type`, `enabled`, `field_type`, `min_length`, `max_length`, `require`, `parse`, `field_order`, `list_table_order`, `searchable`, `format`) VALUES ';

            reset($custom_fields);
            foreach ($custom_fields as $fieldinfo) {
                $sql .= "('" . $fieldinfo[0] . "', '" . $fieldinfo[1] . "','" . $fieldinfo[2] . "','" . $fieldinfo[3] . "','" . $fieldinfo[4] . "','" . $fieldinfo[5] . "','" . $fieldinfo[6] . "','" . $fieldinfo[7] . "','" . $fieldinfo[8] . "','" . $fieldinfo[9] . "','" . $fieldinfo[10] . "','" . $fieldinfo[11] . "', 'no'),";
            }

            $sql = substr($sql, 0, strlen($sql) - 1);

            $db->query($sql);
        }
    }

    //create table for field data used in option fields
    if (!$db->table_exists('myshowcase_field_data')) {
        //insert default data based on 4x4 trucks
        if ($insert_sample_data) {
            $sql = 'INSERT INTO ' . TABLE_PREFIX . 'myshowcase_field_data (`setid`, `fid`, `name`, `valueid`, `value`, `disporder`) VALUES ';

            reset($custom_field_data);
            foreach ($custom_field_data as $fieldinfo) {
                $sql .= '(1,' . $fieldinfo[0] . ",'" . $fieldinfo[1] . "', " . $fieldinfo[2] . ",'" . $fieldinfo[3] . "'," . $fieldinfo[4] . '),';
            }

            $sql = substr($sql, 0, strlen($sql) - 1);

            $db->query($sql);
        }
    }

    // DELETE ALL SETTINGS TO AVOID DUPLICATES
    $db->write_query(
        'DELETE FROM ' . TABLE_PREFIX . "settings WHERE name IN(
		'myshowcase_delete_on_uninstall')"
    );

    $db->delete_query('settinggroups', "name = 'myshowcase'");

    //start new setting group
    $settings_group = array(
        'name' => 'myshowcase',
        'title' => 'MyShowcase Section',
        'description' => 'Options on how to configure the MyShowcase section.',
        'disporder' => '50',
        'isdefault' => '0',
    );

    $db->insert_query('settinggroups', $settings_group);
    $gid = $db->insert_id();

    //start new settings (THERE IS NO MAIN SWITCH AS EACH SHOWCASE IS ENABLED INDIVIDUALLY IN THE SHOWCASE ADMIN)
    $pluginsetting = array();
    $disporder = 1;

    $pluginsetting[] = array(
        'name' => 'myshowcase_delete_tables_on_uninstall',
        'title' => 'Drop MyShowcase tables when uninstalling?',
        'description' => 'When uninstalling, do you want to drop the showcase tables and delete the data in them? Reinstalling will not overwrite existing data if you select [no].',
        'optionscode' => 'yesno',
        'value' => '0',
        'disporder' => $disporder,
        'gid' => $gid
    );

    reset($pluginsetting);
    foreach ($pluginsetting as $setting) {
        $db->insert_query('settings', $setting);
    }

    //upgrade procedure for post-table install
    myshowcase_upgrade_install_post_table($need_upgrade);

    //upgrade procedure for post-template install
    myshowcase_upgrade_install_post_template($need_upgrade);
}

/**
 * Plugin activate
 *
 */
function myshowcase_plugin_activate()
{
    global $db, $cache, $plugins;

    //load upgrade code
    include_once(MYBB_ROOT . 'inc/plugins/myshowcase/upgrade.php');

    //get this plugin's info so we have current version number
    $myshowcase = myshowcase_info();

    //get currently installed version, if there is one
    $oldver = '2.0.0'; //from beta release
    $cpcom_plugins = $cache->read('cpcom_plugins');
    if (is_array($cpcom_plugins)) {
        $oldver = $cpcom_plugins['versions']['myshowcase'];
    }

    if (version_compare($oldver, $myshowcase['version']) == -1) {
        $need_upgrade = array();
        $need_upgrade['prev'] = $oldver;
        $need_upgrade['this'] = $myshowcase['version'];
    }

    include MYBB_ROOT . '/inc/adminfunctions_templates.php';
    find_replace_templatesets(
        'header',
        '#' . preg_quote('<navigation>') . '#',
        "{\$myshowcase_unapproved}{\$myshowcase_reported}<navigation>"
    );

    //upgrade procedure for activation
    myshowcase_upgrade_activate($need_upgrade);

    //update version cache to latest
    $cpcom_plugins['versions']['myshowcase'] = $myshowcase['version'];
    $cache->update('cpcom_plugins', $cpcom_plugins);

    //update cahce to latest
    myshowcase_update_cache('config');
    myshowcase_update_cache('field_data');
    myshowcase_update_cache('fieldsets');
    myshowcase_update_cache('fields');
    myshowcase_update_cache('permissions');
    myshowcase_update_cache('moderators');
    myshowcase_update_cache('reports');

    _install_task();
}