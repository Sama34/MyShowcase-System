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

use MyBB;

use function MyShowcase\Core\cacheGet;
use function MyShowcase\Core\cacheUpdate;

use function MyShowcase\Core\permissionsDelete;
use function MyShowcase\Core\permissionsInsert;

use const MyShowcase\Core\CACHE_TYPE_CONFIG;
use const MyShowcase\Core\CACHE_TYPE_PERMISSIONS;

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
 * Insert default permissions for any new groups.
 * since we can't get new group ID from add group hook,
 * we need to use the edit group hook which is called
 * after a successful add group
 */
function admin_user_groups_edit(): bool
{
    global $db, $cache, $config;

    require_once(MYBB_ROOT . $config['admin_dir'] . '/modules/myshowcase/module_meta.php');

    $curgroups = $cache->read('usergroups');
    $showgroups = cacheGet(CACHE_TYPE_PERMISSIONS);
    $myshowcases = cacheGet(CACHE_TYPE_CONFIG);

    //see if added group is in each enabled myshowcase's permission set
    foreach ($myshowcases as $myshowcase) {
        foreach ($curgroups as $group) {
            if (!array_key_exists($group['entry_id'], $showgroups[$myshowcase['showcase_id']] ?? [])) {
                $myshowcase_defaultperms['showcase_id'] = $myshowcase['showcase_id'];
                $myshowcase_defaultperms['entry_id'] = $group['entry_id'];

                permissionsInsert($myshowcase_defaultperms);
            }
        }
    }
    cacheUpdate(CACHE_TYPE_PERMISSIONS);

    return true;
}

/**
 * delete default permissions for any new groups.
 */
function admin_user_groups_delete_commit(): bool
{
    global $usergroup;

    permissionsDelete(["entry_id='{$usergroup['entry_id']}'"]);

    cacheUpdate(CACHE_TYPE_PERMISSIONS);

    return true;
}