<?php
/**
 * MyShowcase Plugin for MyBB - Admin module for MyShowcase
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
				http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \admin\modules\myshowcase\module_meta.php
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

function myshowcase_meta()
{
	global $page, $lang, $plugins;

	$sub_menu = array();
	$sub_menu['10'] = array("id" => "summary", "title" => $lang->myshowcase_admin_summary, "link" => "index.php?module=myshowcase-summary");
	$sub_menu['20'] = array("id" => "fields", "title" => $lang->myshowcase_admin_fields, "link" => "index.php?module=myshowcase-fields");
	$sub_menu['30'] = array("id" => "edit", "title" => $lang->myshowcase_admin_edit_existing, "link" => "index.php?module=myshowcase-edit");
	$sub_menu['40'] = array("id" => "cache", "title" => $lang->myshowcase_admin_cache, "link" => "index.php?module=myshowcase-cache");
	$sub_menu['50'] = array("id" => "help", "title" => $lang->myshowcase_admin_help, "link" => "index.php?module=myshowcase-help");

	$plugins->run_hooks("admin_myshowcase_menu", $sub_menu);

	$page->add_menu_item($lang->myshowcase_admin_myshowcase, "myshowcase", "index.php?module=myshowcase", 60, $sub_menu);
	return true;
}

function myshowcase_action_handler($action)
{
	global $page, $lang, $plugins;

	$page->active_module = "myshowcase";

	$actions = array(
		'summary' => array('active' => 'summary', 'file' => 'summary.php'),
		'new' => array('active' => 'new', 'file' => 'summary.php'),
		'fields' => array('active' => 'fields', 'file' => 'fields.php'),
		'edit' => array('active' => 'edit', 'file' => 'edit.php'),
		'cache' => array('active' => 'cache', 'file' => 'cache.php'),
		'help' => array('active' => 'help', 'file' => 'help.php')
	);

	$plugins->run_hooks("admin_myshowcase_action_handler", $actions);

	if(isset($actions[$action]))
	{
		$page->active_action = $actions[$action]['active'];
		return $actions[$action]['file'];
	}
	else
	{
		$page->active_action = "summary";
		return "summary.php";
	}
}

function myshowcase_admin_permissions()
{
	global $lang, $plugins;

	$admin_permissions = array(
		"summary" => $lang->myshowcase_admin_perm_summary,
		"new" => $lang->myshowcase_admin_perm_new,
		"edit" => $lang->myshowcase_admin_perm_edit,
		"fields" => $lang->myshowcase_admin_perm_fields,
		"cache" => $lang->myshowcase_admin_perm_cache,
		"help" => $lang->myshowcase_admin_perm_help,
	);

	$plugins->run_hooks("admin_myshowcase_permissions", $admin_permissions);

	return array("name" => $lang->myshowcase_admin_myshowcase, "permissions" => $admin_permissions, "disporder" => 60);
}

//set default permissions for all groups in all myshowcases
//if you edit or reorder these, you need to also edit
//the edit.php file (starting line 225) so the fields match this order
$showcase_defaultperms=array();
$showcase_defaultperms['canadd'] = 0;
$showcase_defaultperms['canedit'] = 0;
$showcase_defaultperms['canattach'] = 0;
$showcase_defaultperms['canview'] = 1;
$showcase_defaultperms['canviewcomment'] = 1;
$showcase_defaultperms['canviewattach'] = 1;
$showcase_defaultperms['cancomment'] = 0;
$showcase_defaultperms['candelowncomment'] = 0;
$showcase_defaultperms['candelauthcomment'] = 0;
$showcase_defaultperms['cansearch'] = 1;
$showcase_defaultperms['canwatermark'] = 0;
$showcase_defaultperms['attachlimit'] = 0;

?>
