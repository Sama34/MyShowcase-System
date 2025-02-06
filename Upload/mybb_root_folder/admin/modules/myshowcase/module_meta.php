<?php
/**
 * MyShowcase Plugin for MyBB - Admin module for MyShowcase
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \admin\modules\myshowcase\module_meta.php
 *
 */

declare(strict_types=1);

// Disallow direct access to this file for security reasons
if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

function myshowcase_meta(): bool
{
    global $page, $lang, $plugins;

    $sub_menu = [];
    $sub_menu['10'] = [
        'id' => 'summary',
        'title' => $lang->myshowcase_admin_summary,
        'link' => 'index.php?module=myshowcase-summary'
    ];
    $sub_menu['20'] = [
        'id' => 'fields',
        'title' => $lang->myshowcase_admin_fields,
        'link' => 'index.php?module=myshowcase-fields'
    ];
    $sub_menu['30'] = [
        'id' => 'edit',
        'title' => $lang->myshowcase_admin_edit_existing,
        'link' => 'index.php?module=myshowcase-edit'
    ];
    $sub_menu['40'] = [
        'id' => 'cache',
        'title' => $lang->myshowcase_admin_cache,
        'link' => 'index.php?module=myshowcase-cache'
    ];
    $sub_menu['50'] = [
        'id' => 'help',
        'title' => $lang->myshowcase_admin_help,
        'link' => 'index.php?module=myshowcase-help'
    ];

    $plugins->run_hooks('admin_myshowcase_menu', $sub_menu);

    $page->add_menu_item(
        $lang->myshowcase_admin_myshowcase,
        'myshowcase',
        'index.php?module=myshowcase',
        60,
        $sub_menu
    );
    return true;
}

function myshowcase_action_handler(string $action): string
{
    global $page, $lang, $plugins;

    $page->active_module = 'myshowcase';

    $actions = [
        'summary' => ['active' => 'summary', 'file' => 'summary.php'],
        'new' => ['active' => 'new', 'file' => 'summary.php'],
        'fields' => ['active' => 'fields', 'file' => 'fields.php'],
        'edit' => ['active' => 'edit', 'file' => 'edit.php'],
        'cache' => ['active' => 'cache', 'file' => 'cache.php'],
        'help' => ['active' => 'help', 'file' => 'help.php']
    ];

    $plugins->run_hooks('admin_myshowcase_action_handler', $actions);

    if (isset($actions[$action])) {
        $page->active_action = $actions[$action]['active'];
        return $actions[$action]['file'];
    } else {
        $page->active_action = 'summary';
        return 'summary.php';
    }
}

function myshowcase_admin_permissions(): array
{
    global $lang, $plugins;

    $admin_permissions = [
        'summary' => $lang->myshowcase_admin_perm_summary,
        'new' => $lang->myshowcase_admin_perm_new,
        'edit' => $lang->myshowcase_admin_perm_edit,
        'fields' => $lang->myshowcase_admin_perm_fields,
        'cache' => $lang->myshowcase_admin_perm_cache,
        'help' => $lang->myshowcase_admin_perm_help,
    ];

    $plugins->run_hooks('admin_myshowcase_permissions', $admin_permissions);

    return ['name' => $lang->myshowcase_admin_myshowcase, 'permissions' => $admin_permissions, 'disporder' => 60];
}