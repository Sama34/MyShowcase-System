<?php
/**
 * MyShowcase Plugin for MyBB - Admin module for Cache
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \admin\modules\myshowcase\cache.php
 *
 */

declare(strict_types=1);

// Disallow direct access to this file for security reasons
if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $lang, $cache, $db, $plugins;
global $page;

$page->add_breadcrumb_item($lang->myshowcase_admin_summary, 'index.php?module=myshowcase-cache');

//make sure plugin is installed and active
$plugin_cache = $cache->read('plugins');
if (!$db->table_exists('myshowcase_config') || !array_key_exists('myshowcase', $plugin_cache['active'])) {
    flash_message($lang->myshowcase_plugin_not_installed, 'error');
    admin_redirect('index.php?module=config-plugins');
}

$plugins->run_hooks('admin_myshowcase_cache_begin');

myshowcase_update_cache('config');
myshowcase_update_cache('field_data');
myshowcase_update_cache('fieldsets');
myshowcase_update_cache('fields');
myshowcase_update_cache('permissions');
myshowcase_update_cache('moderators');
myshowcase_update_cache('reports');

$plugins->run_hooks('admin_myshowcase_cache_end');

// Log admin action
$log = array('rebuild' => 'all');
log_admin_action($log);

flash_message($lang->myshowcase_cache_update_success, 'success');
admin_redirect('index.php?module=myshowcase-summary');