<?php
/**
 * MyShowcase Plugin for MyBB - Main Plugin
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
				http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\plugins\myshowcase.php
 *
 */
 
// Die if IN_MYBB is not defined, for security reasons.
if(!defined('IN_MYBB'))
{
	die('This file cannot be accessed directly.');
}

define('MYSHOWCASE_ROOT', MYBB_ROOT . 'inc/plugins/myshowcase');

require_once MYSHOWCASE_ROOT.'/core.php';

// PLUGINLIBRARY
defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');

// Add our hooks
if(defined('IN_ADMINCP'))
{
	require_once MYSHOWCASE_ROOT.'/admin.php';

	require_once MYSHOWCASE_ROOT.'/admin_hooks.php';

	\MyShowcase\Core\addHooks('MyShowcase\AdminHooks');
}
else
{
	require_once MYSHOWCASE_ROOT.'/forum_hooks.php';

	\MyShowcase\Core\addHooks('MyShowcase\ForumHooks');
}
/*
require MYSHOWCASE_ROOT.'/myalerts.php';

if(\MyShowcase\MyAlerts\MyAlertsIsIntegrable())
{
	\MyShowcase\MyAlerts\initMyalerts();

	\MyShowcase\MyAlerts\initLocations();
}*/

// Plugin API
function myshowcase_info()
{
	return \MyShowcase\Admin\_info();
}

// Activate the plugin.
function myshowcase_activate()
{
	\MyShowcase\Admin\_activate();
}

// Deactivate the plugin.
function myshowcase_deactivate()
{
	\MyShowcase\Admin\_deactivate();
}

// Install the plugin.
function myshowcase_install()
{
	\MyShowcase\Admin\_install();
}

// Check if installed.
function myshowcase_is_installed()
{
	return \MyShowcase\Admin\_is_installed();
}

// Unnstall the plugin.
function myshowcase_uninstall()
{
	\MyShowcase\Admin\_uninstall();
}

// control_object by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com ), 1.62
if(!function_exists('control_object'))
{
	function control_object(&$obj, $code)
	{
		static $cnt = 0;
		$newname = '_objcont_'.(++$cnt);
		$objserial = serialize($obj);
		$classname = get_class($obj);
		$checkstr = 'O:'.strlen($classname).':"'.$classname.'":';
		$checkstr_len = strlen($checkstr);
		if(substr($objserial, 0, $checkstr_len) == $checkstr)
		{
			$vars = array();
			// grab resources/object etc, stripping scope info from keys
			foreach((array)$obj as $k => $v)
			{
				if($p = strrpos($k, "\0"))
				{
					$k = substr($k, $p+1);
				}
				$vars[$k] = $v;
			}
			if(!empty($vars))
			{
				$code .= '
					function ___setvars(&$a) {
						foreach($a as $k => &$v)
							$this->$k = $v;
					}
				';
			}
			eval('class '.$newname.' extends '.$classname.' {'.$code.'}');
			$obj = unserialize('O:'.strlen($newname).':"'.$newname.'":'.substr($objserial, $checkstr_len));
			if(!empty($vars))
			{
				$obj->___setvars($vars);
			}
		}
		// else not a valid object or PHP serialize has changed
	}
}

/**
 * Update the cache.
 *
 * @param string The cache item.
 * @param boolean Clear the cache item.
 */
function myshowcase_update_cache($area, $empty=false)
{
	global $cache;
	if($empty==true && $area != '')
	{
		$cache->update('myshowcase_'.$area,false);
	}
	else
	{
		global $db;

		switch ($area)
		{
			case 'config':
				$myshowcases=array();
				$query=$db->simple_select('myshowcase_config','*','1=1');
				while($myshowcase=$db->fetch_array($query))
				{
					$myshowcases[$myshowcase['id']]=$myshowcase;
				}
				$cache->update('myshowcase_config',$myshowcases);
			break;

			case 'permissions':
				$perms=array();
				$query=$db->simple_select('myshowcase_permissions','*','1=1');
				while($perm=$db->fetch_array($query))
				{
					$perms[$perm['id']][$perm['gid']]=$perm;
				}
				$cache->update('myshowcase_permissions',$perms);
			break;

			case 'fieldsets':
				$fieldsets=array();
				$query=$db->simple_select('myshowcase_fieldsets','*','1=1');
				while($fieldset=$db->fetch_array($query))
				{
					$fieldsets[$fieldset['setid']]=$fieldset;
				}
				$cache->update('myshowcase_fieldsets',$fieldsets);
			break;

			case 'fields':
				$fields=array();
				$query=$db->simple_select('myshowcase_fields','*','1=1',array('order_by'=>'setid, field_order'));
				while($field=$db->fetch_array($query))
				{
					$fields[$field['setid']][$field['fid']]=$field;
				}
				$cache->update('myshowcase_fields',$fields);
			break;

			case 'field_data';
				$set_data=array();
				$query=$db->simple_select('myshowcase_field_data','*','1=1',array('order_by'=>'setid, fid, disporder'));
				while($field_data=$db->fetch_array($query))
				{
					$set_data[$field_data['setid']][$field_data['fid']][$field_data['valueid']]=$field_data;
				}
				$cache->update('myshowcase_field_data',$set_data);
			break;

			case 'moderators';
				$set_data=array();
				$query=$db->simple_select('myshowcase_moderators','*','1=1');
				while($moderators=$db->fetch_array($query))
				{
					$set_data[$moderators['id']][$moderators['mid']]=$moderators;
				}
				$cache->update('myshowcase_moderators',$set_data);
			break;

			case 'reports';
				$set_data=array();
				$query=$db->simple_select('myshowcase_reports','*','status=0');
				while($reports=$db->fetch_array($query))
				{
					$set_data[$reports['id']][$reports['gid']][$reports['rid']]=$reports;
				}
				$cache->update('myshowcase_reports',$set_data);
			break;
		}
	}
}

//function to pull a random entry from a random showcase (if enabled)
function myshowcase_get_random()
{
	global $db, $lang, $mybb, $cache, $templates, $portal_rand_showcase, $adserver_med_rect;

	//get list of enabled myshowcases with random in portal turned on
	$showcase_list = array();
		
	$myshowcases = $cache->read('myshowcase_config');
	foreach($myshowcases as $id => $myshowcase)
	{
		//$myshowcase['portal_random'] == 1;
		if($myshowcase['enabled'] == 1 && $myshowcase['portal_random'] == 1)
		{
			$showcase_list[$id]['name'] = $myshowcase['name'];
			$showcase_list[$id]['mainfile'] = $myshowcase['mainfile'];
			$showcase_list[$id]['imgfolder'] = $myshowcase['imgfolder'];
			$showcase_list[$id]['fieldsetid'] = $myshowcase['fieldsetid'];
		}
	}
		
	//if no showcases set to show on portal return
	if(count($showcase_list) == 0)		
	{
		return 0;
	}
	else
	{
		//get a random showcase id of those enabled
		$rand_id = array_rand($showcase_list, 1);
		$rand_showcase = $showcase_list[$rand_id];
			
		/* URL Definitions */
		if($mybb->settings['seourls'] == "yes" || ($mybb->settings['seourls'] == "auto" && $_SERVER['SEO_SUPPORT'] == 1))
		{
			$showcase_file = strtolower($rand_showcase['name'])."-view-{gid}.html";
		}
		else
		{
			$showcase_file = $rand_showcase['mainfile']."?action=view&gid={gid}";
		}
		
		//init fixed fields
		$fields_fixed = array();
		$fields_fixed[0]['name'] = 'g.uid';
		$fields_fixed[0]['type'] = 'default';
		$fields_fixed[1]['name'] = 'dateline';
		$fields_fixed[1]['type'] = 'default';
		
		//get dynamic field info for the random showcase
		$field_list = array();
		$fields = $cache->read('myshowcase_fields');
		
		//get subset specific to the showcase given assigned field set
		$fields = $fields[$rand_showcase['fieldsetid']];
		
		//get fields that are enabled and set for list display with pad to help sorting fixed fields)
		$description_list = array();
		foreach($fields as $id => $field)
		{
			if($field['list_table_order'] != -1 && $field['enabled'] == 1)
			{
				$field_list[$field['list_table_order']+10]['name'] = $field['name'];
				$field_list[$field['list_table_order']+10]['type'] = $field['html_type'];
				$description_list[$field['list_table_order']]=$field['name'];
			}
		}
		
		//merge dynamic and fixed fields
		$fields_for_search = array_merge($fields_fixed, $field_list);
		
		//sort array of header fields by their list display order
		ksort($fields_for_search);
			
		//build where clause based on search terms
		$addon_join = '';
		$addon_fields = '';
		reset($fields_for_search);
		foreach($fields_for_search as $id => $field)
		{
			if($field['type'] == 'db' || $field['type'] == 'radio')
			{
				$addon_join .= " LEFT JOIN ".TABLE_PREFIX."myshowcase_field_data tbl_".$field['name']." ON (tbl_".$field['name'].".valueid = g.".$field['name']." AND tbl_".$field['name'].".name = '".$field['name']."') ";
				$addon_fields .= ", tbl_".$field['name'].".value AS ".$field['name'];
			}
			else
			{
				$addon_fields .= ", ".$field['name'];
			}
		}
			
		
		$rand_entry = 0;
		while($rand_entry == 0)
		{
			$query = $db->query("SELECT gid, attachname, thumbnail FROM `".TABLE_PREFIX."myshowcase_attachments` WHERE filetype LIKE 'image%' AND gid <> 0 AND visible =1 AND id=".$rand_id." ORDER BY RAND( ) LIMIT 0 , 1");
			$result = $db->fetch_array($query);
			$rand_entry = $result['gid'];
			$rand_entry_img = $result['attachname'];
			$rand_entry_thumb = $result['thumbnail'];
		
			if($rand_entry)
			{
				$query = $db->query("
					SELECT gid, username, g.views, comments".$addon_fields."
					FROM ".TABLE_PREFIX."myshowcase_data".$rand_id." g
					LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = g.uid)
					".$addon_join."
					WHERE approved = 1 AND gid=".$rand_entry."
					LIMIT 0, 1"
					);
					
				if($db->num_rows($query) == 0)
				{
					$rand_entry = 0;
				}
			}
			else
			{
				return 0;
			}
		}
		
		$trow_style = 'trow2';
		$entry = $db->fetch_array($query);

		$lasteditdate = my_date($mybb->settings['dateformat'], $entry['dateline']);
		$lastedittime = my_date($mybb->settings['timeformat'], $entry['dateline']);
		$item_lastedit = $lasteditdate."<br>".$lastedittime;
	
		$item_member = build_profile_link($entry['username'], $entry['uid'], '','', $mybb->settings['bburl'].'/');
		
		$item_view_user = str_replace("{username}", $entry['username'], $lang->myshowcase_view_user);
		
		$item_viewcode = str_replace('{gid}', $entry['gid'], $showcase_file);
		
		$entry['description'] = "";
		foreach($description_list as $order => $name)
		{
			$entry['description'] .= $entry[$name] ." ";
		}
		
		$trow_style = ($trow_style == "trow1" ? "trow2" : "trow1");
		
		if($rand_entry_thumb == 'SMALL')
		{
			$rand_img = $rand_showcase['imgfolder'].'/' .$rand_entry_img;
		}
		else
		{
			$rand_img = $rand_showcase['imgfolder'].'/' .$rand_entry_thumb;   
		}
		
		eval("\$portal_rand_showcase = \"".$templates->get("portal_rand_showcase")."\";");
		return $portal_rand_showcase;
	}
}