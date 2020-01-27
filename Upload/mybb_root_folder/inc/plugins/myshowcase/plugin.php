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

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

/**
 * Plugin info
 *
 */
function myshowcase_plugin_info()
{

	$donate_button = 
'<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=NZ574GV99KXTU&item_name=Donation%20for%20MyShowcase" style="float:right;margin-top:-8px;padding:4px;" target="_blank"><img src="https://www.paypalobjects.com/WEBSCR-640-20110306-1/en_US/i/btn/btn_donate_SM.gif" /></a>';

	return array(
		"name"		=> "MyShowcase System",
		"description"	=> $donate_button."Create and manage showcase sections for user to enter information about their property or collections.",
		"website"	=> "http://www.communityplugins.com",
		"author"	=> "CommunityPlugins.com",
		"authorsite"	=> "http://www.communityplugins.com",
		"version"	=> "3.0.0",
		"versioncode"	=> 3000,
		"compatibility" => "18*",
	);
}

/**
 * Plugin install
 *
 */
function myshowcase_plugin_install()
{
	global $db, $mybb, $config, $cache, $need_upgrade, $plugins;

    //load upgrade code
    include_once(MYBB_ROOT."inc/plugins/myshowcase/upgrade.php");
    
    //get this plugin's info so we have current version number
	$myshowcase = myshowcase_info();

    //get currently installed version, if there is one
    $oldver = '2.0.0'; //from beta release
    $cpcom_plugins = $cache->read('cpcom_plugins');
    if(is_array($cpcom_plugins))
    {
        $oldver = $cpcom_plugins['versions']['myshowcase'];
    }
    
    //check versions
	if(version_compare($oldver, $myshowcase['version']) == -1)
	{
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
	if(file_exists(MYBB_ROOT."inc/plugins/myshowcase/sample_data.php"))
	{
		require_once(MYBB_ROOT."inc/plugins/myshowcase/sample_data.php");
		$insert_sample_data = 1;
	}

	//create table for attachments
	if(!$db->table_exists("myshowcase_attachments"))
	{
		$db->query("CREATE TABLE `".TABLE_PREFIX."myshowcase_attachments` (
			`id` int(3) NOT NULL default '1',
			`aid` int(10) unsigned NOT NULL auto_increment,
			`gid` int(10) NOT NULL default '0',
			`posthash` varchar(50) NOT NULL default '',
			`uid` int(10) unsigned NOT NULL default '0',
			`filename` varchar(120) NOT NULL default '',
			`filetype` varchar(120) NOT NULL default '',
			`filesize` int(10) NOT NULL default '0',
			`attachname` varchar(120) NOT NULL default '',
			`downloads` int(10) unsigned NOT NULL default '0',
			`dateuploaded` bigint(30) NOT NULL default '0',
			`thumbnail` varchar(120) NOT NULL default '',
			`visible` smallint(1) NOT NULL default '0',
			PRIMARY KEY  (`aid`),
			KEY `posthash` (`posthash`),
			KEY `gid` (`gid`),
			KEY `uid` (`uid`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
			");
	}

	//create table for comments
	if(!$db->table_exists("myshowcase_comments"))
	{
		$db->query("CREATE TABLE `".TABLE_PREFIX."myshowcase_comments` (
			`id` int(3) NOT NULL default '1',
			`cid` int(10) NOT NULL auto_increment,
			`gid` int(10) NOT NULL,
			`uid` int(10) NOT NULL,
			`ipaddress` varchar(30) NOT NULL default '0.0.0.0',
			`comment` text,
			`dateline` bigint(30) NOT NULL,
			PRIMARY KEY  (`cid`,`gid`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;");
	}

	//create table for config
	if(!$db->table_exists("myshowcase_config"))
	{
		$db->query("CREATE TABLE `".TABLE_PREFIX."myshowcase_config` (
			`id` int(3) NOT NULL auto_increment,
			`name` varchar(50) NOT NULL,
			`description` varchar(255) NOT NULL,
			`mainfile` varchar(50) NOT NULL,
			`fieldsetid` int(3) NOT NULL default '1',
			`imgfolder` varchar(255) default NULL,
			`defaultimage` varchar(50) default NULL,
			`watermarkimage` varchar(50) default NULL,
			`watermarkloc` varchar(12) default 'lower-right',
			`use_attach` INT( 1 ) NOT NULL DEFAULT '0',
			`f2gpath` varchar(255) NOT NULL,
			`enabled` int(1) NOT NULL default '0',
			`allowsmilies` smallint(1) NOT NULL default '0',
			`allowbbcode` smallint(1) NOT NULL default '0',
			`allowhtml` smallint(1) NOT NULL default '0',
			`prunetime` varchar(10) NOT NULL default '0',
			`modnewedit` smallint(1) NOT NULL default '1',
			`othermaxlength` smallint(5) NOT NULL default '500',
			`allow_attachments` smallint(1) NOT NULL default '1',
			`allow_comments` smallint(1) NOT NULL default '1',
			`thumb_width` smallint(4) NOT NULL default '200',
			`thumb_height` smallint(4) NOT NULL default '200',
			`comment_length` smallint(5) NOT NULL default '200',
			`comment_dispinit` smallint(2) NOT NULL default '5',
			`disp_attachcols` smallint(2) NOT NULL default '0',
			`disp_empty` smallint(1) NOT NULL default '1',
			`link_in_postbit` smallint(1) NOT NULL default '0',
			`portal_random` smallint(1) NOT NULL default '0',
			PRIMARY KEY  (`id`),
			KEY `name` (`name`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;");
	}

	//create table for field sets
	if(!$db->table_exists("myshowcase_fieldsets"))
	{
		$db->query("CREATE TABLE `".TABLE_PREFIX."myshowcase_fieldsets` (
			`setid` int(3) NOT NULL auto_increment,
			`setname` varchar(50) NOT NULL,
			PRIMARY KEY  (`setid`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;");

		//insert default data based on 4x4 trucks
		if($insert_sample_data)
		{
			$sql = "INSERT INTO ".TABLE_PREFIX."myshowcase_fieldsets (`setname`) VALUES ";

			reset($custom_fieldsets);
			foreach($custom_fieldsets as $fieldinfo)
			{
				$sql .= "('".$fieldinfo[0]."'),";
			}

			$sql = substr($sql, 0, strlen($sql)-1);

			$db->query($sql);
		}
	}

	//create table for permissions
	if(!$db->table_exists("myshowcase_permissions"))
	{
		$db->query("CREATE TABLE ".TABLE_PREFIX."myshowcase_permissions (
			`pid` INT( 10 ) NOT NULL AUTO_INCREMENT ,
			 `id` INT( 10 ) NOT NULL DEFAULT  '0',
			 `gid` INT( 10 ) NOT NULL DEFAULT  '0',
			 `canview` INT( 1 ) NOT NULL DEFAULT  '0',
			 `canadd` INT( 1 ) NOT NULL DEFAULT  '0',
			 `canedit` INT( 1 ) NOT NULL DEFAULT  '0',
			 `cancomment` INT( 1 ) NOT NULL DEFAULT  '0',
			 `canattach` INT( 1 ) NOT NULL DEFAULT  '0',
			 `canviewcomment` INT( 1 ) NOT NULL DEFAULT  '0',
			 `canviewattach` INT( 1 ) NOT NULL DEFAULT  '0',
			 `candelowncomment` INT( 1 ) NOT NULL DEFAULT  '0',
			 `candelauthcomment` INT( 1 ) NOT NULL DEFAULT  '0',
			 `cansearch` INT( 1 ) NOT NULL DEFAULT  '0',
			 `canwatermark` INT( 1 ) NOT NULL DEFAULT  '0',
			 `attachlimit` INT( 4 ) NOT NULL DEFAULT  '0',
			PRIMARY KEY (  `pid` )
			) ENGINE = MYISAM DEFAULT CHARSET = utf8;");
	}

	//create table for moderators
	if(!$db->table_exists("myshowcase_moderators"))
	{
		$db->query("CREATE TABLE ".TABLE_PREFIX."myshowcase_moderators (
			  `mid` int(5) NOT NULL auto_increment,
			  `id` int(5) NOT NULL,
			  `uid` mediumint(10) NOT NULL,
			  `isgroup` smallint(1) NOT NULL default 0,
			  `canmodapprove` int(1) NOT NULL default '0',
			  `canmodedit` int(1) NOT NULL default '0',
			  `canmoddelete` int(1) NOT NULL default '0',
			  `canmoddelcomment` int(1) NOT NULL default '0',
			  PRIMARY KEY  (`mid`),
			  KEY `id` (`id`,`uid`)
			) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ");
	}

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

	//create data for fields in a field set
	if(!$db->table_exists("myshowcase_fields"))
	{
		$db->query("CREATE TABLE `".TABLE_PREFIX."myshowcase_fields` (
			`setid` int(3) NOT NULL,
			`fid` mediumint(9) NOT NULL auto_increment,
			`name` varchar(30) NOT NULL,
			`html_type` varchar(10) NOT NULL,
			`enabled` smallint(6) NOT NULL,
			`field_type` varchar(10) NOT NULL default 'varchar',
			`min_length` smallint(6) NOT NULL default '0',
			`max_length` smallint(6) NOT NULL default '0',
			`require` tinyint(4) NOT NULL default '0',
			`parse` tinyint(4) NOT NULL default '0',
			`field_order` tinyint(4) NOT NULL,
			`list_table_order` smallint(6) NOT NULL default '-1',
			`searchable` smallint(1) NOT NULL default '0',
			`format` varchar(10) NOT NULL default 'no',
			PRIMARY KEY  (`fid`),
			KEY `id` (`setid`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;");

		//insert default data based on 4x4 trucks
		if($insert_sample_data)
		{
			$sql = "INSERT INTO ".TABLE_PREFIX."myshowcase_fields (`setid`, `name`, `html_type`, `enabled`, `field_type`, `min_length`, `max_length`, `require`, `parse`, `field_order`, `list_table_order`, `searchable`, `format`) VALUES ";

			reset($custom_fields);
			foreach($custom_fields as $fieldinfo)
			{
				$sql .= "('".$fieldinfo[0]."', '".$fieldinfo[1]."','".$fieldinfo[2]."','".$fieldinfo[3]."','".$fieldinfo[4]."','".$fieldinfo[5]."','".$fieldinfo[6]."','".$fieldinfo[7]."','".$fieldinfo[8]."','".$fieldinfo[9]."','".$fieldinfo[10]."','".$fieldinfo[11]."', 'no'),";
			}

			$sql = substr($sql, 0, strlen($sql)-1);

			$db->query($sql);
		}
	}

	//create table for field data used in option fields
	if(!$db->table_exists("myshowcase_field_data"))
	{
		$db->query("CREATE TABLE ".TABLE_PREFIX."myshowcase_field_data (
			`setid` smallint(3) NOT NULL,
			`fid` mediumint(5) NOT NULL,
			`name` varchar(15) NOT NULL,
			`valueid` smallint(3) NOT NULL default '0',
			`value` varchar(15) NOT NULL,
			`disporder` smallint(6) NOT NULL,
			KEY `setid` (`setid`),
			KEY `name` (`name`)
			) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
			");

		//insert default data based on 4x4 trucks
		if($insert_sample_data)
		{
			$sql = "INSERT INTO ".TABLE_PREFIX."myshowcase_field_data (`setid`, `fid`, `name`, `valueid`, `value`, `disporder`) VALUES ";

			reset($custom_field_data);
			foreach($custom_field_data as $fieldinfo)
			{
				$sql .= "(1,".$fieldinfo[0].",'".$fieldinfo[1]."', ".$fieldinfo[2].",'".$fieldinfo[3]."',".$fieldinfo[4]."),";
			}

			$sql = substr($sql, 0, strlen($sql)-1);

			$db->query($sql);
		}
	}

	// DELETE ALL SETTINGS TO AVOID DUPLICATES
	$db->write_query("DELETE FROM ".TABLE_PREFIX."settings WHERE name IN(
		'myshowcase_delete_on_uninstall')");

	$db->delete_query("settinggroups", "name = 'myshowcase'");

	//start new setting group
	$settings_group = array(
		"name" => "myshowcase",
		"title" => "MyShowcase Section",
		"description" => "Options on how to configure the MyShowcase section.",
		"disporder" => "50",
		"isdefault" => "0",
        );

	$db->insert_query("settinggroups", $settings_group);
	$gid = $db->insert_id();

	//start new settings (THERE IS NO MAIN SWITCH AS EACH SHOWCASE IS ENABLED INDIVIDUALLY IN THE SHOWCASE ADMIN)
	$pluginsetting = array();
	$disporder = 1;

	$pluginsetting[] = array(
		"name"		=> "myshowcase_delete_tables_on_uninstall",
		"title"		=> "Drop MyShowcase tables when uninstalling?",
		"description"	=> "When uninstalling, do you want to drop the showcase tables and delete the data in them? Reinstalling will not overwrite existing data if you select [no].",
		"optionscode"	=> "yesno",
		"value"		=> "0",
		"disporder"	=> $disporder,
		"gid"		=> $gid
	);

	reset($pluginsetting);
	foreach($pluginsetting as $setting)
	{
		$db->insert_query("settings", $setting);
	}

    //upgrade procedure for post-table install
	myshowcase_upgrade_install_post_table($need_upgrade);

	rebuild_settings();
	
	//insert template group
	$insert_array = array(
		'prefix' => 'myshowcase',
		'title' => '<lang:group_myshowcase>'
	);
	$db->insert_query("templategroups", $insert_array);

	//insert new base templates
	if(file_exists(MYBB_ROOT."inc/plugins/myshowcase/templates.php"))
	{
		require_once(MYBB_ROOT."inc/plugins/myshowcase/templates.php");
		ksort($myshowcase_templates);
		reset($myshowcase_templates);
		foreach($myshowcase_templates as $title => $template)
		{
			$insert_array = array(
				'title' => $title,
				'template' => $db->escape_string($template),
				'sid' => -2,
				'version' => 2300,
				'dateline' => TIME_NOW
				);
			
			$db->insert_query('templates', $insert_array);
		}
	}

    //upgrade procedure for post-template install
	myshowcase_upgrade_install_post_template($need_upgrade);

/*---- Add Task ---*/
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

/**
 * Plugin is_installed
 *
 */
function myshowcase_plugin_is_installed()
{
	global $db;

	$query = $db->simple_select("settinggroups", "gid", "name='myshowcase'");
	if($db->num_rows($query) == 1 && $db->table_exists('myshowcase_fields'))
	{
		return true;
	}
	else
	{
		return false;
	}
}

/**
 * Plugin activate
 *
 */
function myshowcase_plugin_activate()
{
    global $db, $cache, $plugins;

    //load upgrade code
    include_once(MYBB_ROOT."inc/plugins/myshowcase/upgrade.php");
    
    //get this plugin's info so we have current version number
	$myshowcase = myshowcase_info();

    //get currently installed version, if there is one
    $oldver = '2.0.0'; //from beta release
    $cpcom_plugins = $cache->read('cpcom_plugins');
    if(is_array($cpcom_plugins))
    {
        $oldver = $cpcom_plugins['versions']['myshowcase'];
    }
    
	if(version_compare($oldver, $myshowcase['version']) == -1)
	{
		$need_upgrade = array();
		$need_upgrade['prev'] = $oldver;
		$need_upgrade['this'] = $myshowcase['version'];
	}

    include MYBB_ROOT.'/inc/adminfunctions_templates.php';
    find_replace_templatesets('header', '#'.preg_quote('<navigation>').'#', "{\$myshowcase_unapproved}{\$myshowcase_reported}<navigation>");

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

    rebuild_settings();
    
    /*---- Enable Task ---*/
	$db->update_query("tasks", array("enabled" => 1), "title='".$db->escape_string('MyShowcase')."'");
	$plugins->run_hooks("admin_tools_tasks_edit_commit");
	$cache->update_tasks();
    
    return true;
}

/**
 * Plugin deactivate
 *
 */
function myshowcase_plugin_deactivate()
{
	global $db, $mybb, $plugins, $cache;
	
    include MYBB_ROOT.'/inc/adminfunctions_templates.php';
    find_replace_templatesets('header', '#'.preg_quote('{$myshowcase_unapproved}{$myshowcase_reported}').'#', "");

/*---- Disable Task ---*/
	$db->update_query("tasks", array("enabled" => 0), "title='".$db->escape_string('MyShowcase')."'");
	$plugins->run_hooks("admin_tools_tasks_edit_commit");
	$cache->update_tasks();
	
	return true;

}


/**
 * Plugin uninstall
 *
 */
function myshowcase_plugin_uninstall()
{
	global $db, $mybb, $cache, $plugins;

	//drop tables but only if setting specific to allow uninstall is enabled
	if($mybb->settings['myshowcase_delete_tables_on_uninstall'] == 1)
	{

		//remove myshowcase cache items
		$cache->update('myshowcase_config',false);
		$cache->update('myshowcase_fields',false);
		$cache->update('myshowcase_field_data',false);
		$cache->update('myshowcase_fieldsets',false);
		$cache->update('myshowcase_permissions',false);
		$cache->update('myshowcase_moderators',false);
        $cache->update('myshowcase_version',false);

		//get list of possible tables from config and delete
		$query = $db->simple_select("myshowcase_config", "id");
		while($result = $db->fetch_array($query))
		{
			if($db->table_exists("myshowcase_data".$result['id']))
			{
				$db->drop_table("myshowcase_data".$result['id']);
			}
		}

		//delete standard tables
		if($db->table_exists("myshowcase_attachments"))
		{
			$db->drop_table("myshowcase_attachments");
		}

		if($db->table_exists("myshowcase_comments"))
		{
			$db->drop_table("myshowcase_comments");
		}

		if($db->table_exists("myshowcase_config"))
		{
			$db->drop_table("myshowcase_config");
		}

		if($db->table_exists("myshowcase_fieldsets"))
		{
			$db->drop_table("myshowcase_fieldsets");
		}

		if($db->table_exists("myshowcase_fields"))
		{
			$db->drop_table("myshowcase_fields");
		}

		if($db->table_exists("myshowcase_field_data"))
		{
			$db->drop_table("myshowcase_field_data");
		}

		if($db->table_exists("myshowcase_permissions"))
		{
			$db->drop_table("myshowcase_permissions");
		}

		if($db->table_exists("myshowcase_moderators"))
		{
			$db->drop_table("myshowcase_moderators");
		}
	}

	// DELETE ALL SETTINGS
	$db->write_query("DELETE FROM ".TABLE_PREFIX."settings WHERE name IN(
		'myshowcase_delete_on_uninstall')");

	$db->delete_query("settinggroups", "name = 'myshowcase'");

	//delete old base templates
	$db->write_query("DELETE FROM ".TABLE_PREFIX."templates WHERE title LIKE '%myshowcase%' AND sid = -2");
	$db->write_query("DELETE FROM ".TABLE_PREFIX."templates WHERE title = 'portal_basic_box' AND sid = -2");
	
	$db->delete_query("templategroups", "prefix = 'myshowcase'");

	rebuild_settings();
    
/*---- Remove Task ---*/
	include('../inc/functions_task.php');

	$tid = $db->delete_query("tasks", "title='".$db->escape_string('MyShowcase')."'");
	$plugins->run_hooks("admin_tools_tasks_delete_commit");
	$cache->update_tasks();    
    return true;
}


?>
