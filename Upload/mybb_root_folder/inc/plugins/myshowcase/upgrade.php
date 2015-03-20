<?php
/**
 * MyShowcase Plugin for MyBB - Upgrade File
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
				http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\plugins\myshowcase\upgrade.php
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/*
* Code to upgrade cache name to new one
* after move from PavementSucks.com to CommunityForums.com
*/

$cpcom_plugins = $cache->read('cpcom_plugins');

$pscom_plugins = $cache->read('pscom_plugins');
if($pscom_plugins['versions']['myshowcase'])
{
    $cpcom_plugins['versions']['myshowcase'] = $pscom_plugins['versions']['myshowcase'];
    $cache->update('cpcom_plugins', $cpcom_plugins);
    
    unset($pscom_plugins['versions']['myshowcase']);
	$cache->update('pscom_plugins', $pscom_plugins);

}




/*
* Upgrade code that occurs prior to table installation
*
*/
function myshowcase_upgrade_install_pre_table($need_upgrade)
{

//	if(is_array($need_upgrade) )
//	{
        //save resources,don't load globals unless doing an upgrade
//       global $db, $mybb, $config, $lang, $cache, $lang;
 //    }
}

/*
* Upgrade code that occurs after table installation
*
*/
function myshowcase_upgrade_install_post_table($need_upgrade)
{

//	if(is_array($need_upgrade) )
//	{
        //save resources,don't load globals unless doing an upgrade
//        global $db, $mybb, $config;
//   }
}

/*
* Upgrade code that occurs after template installation
*
*/
function myshowcase_upgrade_install_post_template($need_upgrade)
{

//	if(is_array($need_upgrade) )
//	{
        //save resources,don't load globals unless doing an upgrade
//        global $db, $mybb, $config;
//   }
}

/*
* Upgrade code that occurs at activation
*
*/
function myshowcase_upgrade_activate($need_upgrade)
{
	if(is_array($need_upgrade) )
	{
        //save resources,don't load globals unless doing an upgrade
        global $db, $mybb, $config, $lang, $cache, $plugins;
				
		if(version_compare($need_upgrade['prev'], '2.1.0', '<'))
		{
				//create table for reported showcase entries
				if(!$db->table_exists("myshowcase_reports"))
				{
					$db->query("CREATE TABLE " .TABLE_PREFIX."myshowcase_reports (
						`rid` INT( 10 ) UNSIGNED NOT NULL AUTO_INCREMENT ,
						`id` INT( 3 ) UNSIGNED NOT NULL ,
						`gid` INT( 10 ) UNSIGNED NOT NULL ,
						`reporteruid` INT( 10 ) UNSIGNED NOT NULL ,
						`authoruid` INT( 10 ) UNSIGNED NOT NULL ,
						`status` INT( 1 ) NOT NULL DEFAULT '0',
						`reason` VARCHAR( 250 ) NOT NULL ,
						`dateline` BIGINT( 30 ) NOT NULL ,
						PRIMARY KEY ( `rid` ) 
						) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ");
				}
	
				//add new field for random portal option
				if(!$db->field_exists('portal_random', 'myshowcase_config'))
				{
					$query = 'ALTER TABLE '.TABLE_PREFIX.'myshowcase_config ADD `portal_random` INT(1) DEFAULT \'0\'';
					$db->write_query($query);
				}
		}
		if(version_compare($need_upgrade['prev'], '2.2.0', '<'))
		{
			//increase size of comment field
			$newdef = 'TEXT '.$db->build_create_table_collation().' NULL DEFAULT NULL';
			$db->modify_column('myshowcase_comments', 'comment', $newdef);
			
			//add prune time support
			if(!$db->field_exists('prunetime', 'myshowcase_config'))
			{
				$newdef = 'VARCHAR( 10 ) NOT NULL DEFAULT 0 AFTER `enabled`';
				$db->add_column('myshowcase_config', 'prunetime', $newdef);
			}
					
			//add smilie support
			if(!$db->field_exists('allowsmilies', 'myshowcase_config'))
			{
				$newdef = 'SMALLINT(1) NOT NULL DEFAULT 0 AFTER `enabled`';
				$db->add_column('myshowcase_config', 'allowsmilies', $newdef);
			}
					
			//add bbcode support
			if(!$db->field_exists('allowbbcode', 'myshowcase_config'))
			{
				$newdef = 'SMALLINT(1) NOT NULL DEFAULT 0 AFTER `enabled`';
				$db->add_column('myshowcase_config', 'allowbbcode', $newdef);
			}
					
			//add html support
			if(!$db->field_exists('allowhtml', 'myshowcase_config'))
			{
				$newdef = 'SMALLINT(1) NOT NULL DEFAULT 0 AFTER `enabled`';
				$db->add_column('myshowcase_config', 'allowhtml', $newdef);
			}
					
			//add parse setting
			if(!$db->field_exists('parse', 'myshowcase_fields'))
			{
				$newdef = 'SMALLINT(1) NOT NULL DEFAULT 0 AFTER `enabled`';
				$db->add_column('myshowcase_fields', 'parse', $newdef);
			}

			//add php format setting
			if(!$db->field_exists('format', 'myshowcase_fields'))
			{
				$newdef = 'VARCHAR(10) NOT NULL DEFAULT \'no\' AFTER `enabled`';
				$db->add_column('myshowcase_fields', 'format', $newdef);
			}

			//add task
			$query = $db->simple_select("tasks", "tid", "title='MyShowcase'");
			if($db->num_rows($query) == 0)
			{
				include('../inc/functions_task.php');
	
				$new_task = array(
					"title" => $db->escape_string('MyShowcase'),
					"description" => $db->escape_string('Prunes showcases where enabled.'),
					"file" => $db->escape_string('myshowcase'),
					"minute" => $db->escape_string('9'),
					"hour" => $db->escape_string('4'),
					"day" => $db->escape_string('*'),
					"month" => $db->escape_string('*'),
					"weekday" => $db->escape_string('*'),
					"enabled" => 1,
					"logging" => 1
				);
	
				$new_task['nextrun'] = fetch_next_run($new_task);
				$tid = $db->insert_query("tasks", $new_task);
				$plugins->run_hooks("admin_tools_tasks_add_commit");
				$cache->update_tasks();		
			}	
		}
		
		if(version_compare($need_upgrade['prev'], '2.2.1', '<'))
		{
			//fix format field
			if(!$db->field_exists('format', 'myshowcase_fields'))
			{
				$newdef = 'VARCHAR(10) NOT NULL DEFAULT \'no\' AFTER `enabled`';
				$db->add_column('myshowcase_fields', 'format', $newdef);
			}
		}
 
		if(version_compare($need_upgrade['prev'], '2.4.0', '<'))
		{
			//add watermark fields
			if(!$db->field_exists('canwatermark', 'myshowcase_permissions'))
			{
				$newdef = 'SMALLINT(1) NOT NULL DEFAULT 0 AFTER `cansearch`';
				$db->add_column('myshowcase_permissions', 'canwatermark', $newdef);
			}

			if(!$db->field_exists('watermarkimage', 'myshowcase_config'))
			{
				$newdef = 'VARCHAR(50) NULL AFTER `defaultimage`';
				$db->add_column('myshowcase_config', 'watermarkimage', $newdef);
			}
			
			if(!$db->field_exists('watermarkloc', 'myshowcase_config'))
			{
				$newdef = 'VARCHAR(12) NOT NULL DEFAULT \'lower-right\' AFTER `watermarkimage`';
				$db->add_column('myshowcase_config', 'watermarkloc', $newdef);
			}
		}
	}
}