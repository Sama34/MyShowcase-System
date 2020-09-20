<?php
/**
 * MyShowcase Plugin for MyBB - Main Plugin Controls
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
				http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\plugins\myshowcase\plugin.php
 *
 */

namespace MyShowcase\AdminHooks;

function admin_config_plugins_begin01()
{
	global $mybb, $lang, $page, $db;

	if($mybb->get_input('action') != 'myshowcase')
	{
		return;
	}

	\MyShowcase\Core\load_language();

	if($mybb->request_method != 'post')
	{
		$page->output_confirm_action('index.php?module=config-plugins&amp;action=myshowcase', $lang->myshowcase_myalerts_confirm);
	}

	if($mybb->get_input('no') || !\MyShowcase\MyAlerts\MyAlertsIsIntegrable())
	{
		admin_redirect('index.php?module=config-plugins');
	}

	$availableLocations = \MyShowcase\MyAlerts\getAvailableLocations();

	$installedLocations = \MyShowcase\MyAlerts\getInstalledLocations();

	foreach($availableLocations as $availableLocation)
	{
		\MyShowcase\MyAlerts\installLocation($availableLocation);
	}

	flash_message($lang->myshowcase_myalerts_success, 'success');

	admin_redirect('index.php?module=config-plugins');
}

function admin_config_plugins_deactivate()
{
	global $mybb, $page;

	if(
		$mybb->get_input('action') != 'deactivate' ||
		$mybb->get_input('plugin') != 'myshowcase' ||
		!$mybb->get_input('uninstall', \MyBB::INPUT_INT)
	)
	{
		return;
	}

	if($mybb->request_method != 'post')
	{
		$page->output_confirm_action('index.php?module=config-plugins&amp;action=deactivate&amp;uninstall=1&amp;plugin=myshowcase');
	}

	if($mybb->get_input('no'))
	{
		admin_redirect('index.php?module=config-plugins');
	}
}

/**
 * Insert default permissions for any new groups.
 * since we can't get new group ID from add group hook,
 * we need to use the edit group hook which is called
 * after a successful add group
 */
function admin_user_groups_edit()
{
	global $db, $cache, $config;

	require_once(MYBB_ROOT.$config['admin_dir'].'/modules/myshowcase/module_meta.php');

	$curgroups = $cache->read('usergroups');
	$showgroups = $cache->read('myshowcase_permissions');
	$myshowcases = $cache->read('myshowcase_config');

	//see if added group is in each enabled myshowcase's permission set
	foreach($myshowcases as $myshowcase)
	{
		foreach($curgroups as $group)
		{
			if(!array_key_exists($group['gid'], $showgroups[$myshowcase['id']]))
			{
				$myshowcase_defaultperms['id'] = $myshowcase['id'];
				$myshowcase_defaultperms['gid'] = $group['gid'];

				$db->insert_query('myshowcase_permissions', $myshowcase_defaultperms);
			}
		}
	}
	myshowcase_update_cache('permissions');
}

/**
 * delete default permissions for any new groups.
 */
function admin_user_groups_delete_commit()
{
	global $db, $cache, $usergroup;

	$db->delete_query('myshowcase_permissions', "gid='{$usergroup['gid']}'");
	myshowcase_update_cache('permissions');
}