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

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// PLUGINLIBRARY
defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');

//deal with usegroup changes
$plugins->add_hook('admin_user_groups_edit', 'myshowcase_insert_group_perms');
$plugins->add_hook('admin_user_groups_delete_commit', 'myshowcase_delete_group_perms');

//deal with who's online
$plugins->add_hook('fetch_wol_activity_end', 'myshowcase_fetch_wol_activity');
$plugins->add_hook('build_friendly_wol_location_end', 'myshowcase_friendly_wol_location_end');

//links in postbit
$plugins->add_hook('showthread_start', 'myshowcase_get_user_myshowcases_from_thread');
$plugins->add_hook('postbit', 'myshowcase_postbit');

//portal
$plugins->add_hook('portal_start', 'myshowcase_portal_random');

//modcp/reported posts
$plugins->add_hook('global_start', 'myshowcase_global_notices');

/**
 * Plugin info
 *
 */
function myshowcase_info()
{
	include_once(MYBB_ROOT."inc/plugins/myshowcase/plugin.php");
	return myshowcase_plugin_info();
}

/**
 * Plugin install
 *
 */
function myshowcase_install()
{
	include_once(MYBB_ROOT."inc/plugins/myshowcase/plugin.php");
	return myshowcase_plugin_install();
}

/**
 * Plugin is_installed
 *
 */
function myshowcase_is_installed()
{
	include_once(MYBB_ROOT."inc/plugins/myshowcase/plugin.php");
	return myshowcase_plugin_is_installed();
}

/**
 * Plugin activate
 *
 */
function myshowcase_activate()
{
	include_once(MYBB_ROOT."inc/plugins/myshowcase/plugin.php");
	return myshowcase_plugin_activate();
}

/**
 * Plugin deactivate
 *
 */
function myshowcase_deactivate()
{
	include_once(MYBB_ROOT."inc/plugins/myshowcase/plugin.php");
	return myshowcase_plugin_deactivate();
}


/**
 * Plugin uninstall
 *
 */
function myshowcase_uninstall()
{
	include_once(MYBB_ROOT."inc/plugins/myshowcase/plugin.php");
	return myshowcase_plugin_uninstall();
}

/**
 * Add global notices for unapproved and reported showcases
 *
 */
function myshowcase_global_notices()
{
	global $mybb, $db, $cache, $myshowcase_unapproved, $myshowcase_reported, $theme, $templates, $lang;
	
	//get showcases and mods
	$showcases = $cache->read('myshowcase_config');
	$moderators = $cache->read('myshowcase_moderators');
	
	//loop through showcases
	$rep_ids = array();
	foreach($showcases as $id => $showcase)
	{
		//if showcase is enabled...
		if($showcase['enabled'])
		{
			///get array of all user's groups
			$usergroups = explode(',', $mybb->user['additionalgroups']);
			$usergroups[] = $mybb->user['usergroup'];
			
			//...loop through mods 
			$canapprove = 0;
			$caneditdel = 0;
			if(is_array($moderators[$id]))
			{
				foreach($moderators[$id] as $mid => $mod)
				{
					//check if user is specifically a mod 
					if($mybb->user['uid'] == $mod[$mod['id']]['uid'] && $mod[$mod['id']]['isgroup'] == 0 )
					{
						if($mod[$mod['id']]['canmodapprove'] == 1)
						{
							$canapprove = 1;
						}

						if($mod[$mod['id']]['canmodedit'] == 1 || $mod[$mod['id']]['canmoddelete'] == 1 || $mod[$mod['id']]['canmoddelcomment'] == 1)
						{
							$caneditdel = 1;
						}
						continue;
					}
					
					//check if user in mod group
					if(array_key_exists($mod[$mod['id']]['uid'], $usergroups) && $mod[$mod['id']]['isgroup'] == 1)
					{
						if($mod[$mod['id']]['canmodapprove'] == 1)
						{
							$canapprove = 1;
						}

						if($mod[$mod['id']]['canmodedit'] == 1 || $mod[$mod['id']]['canmoddelete'] == 1 || $mod[$mod['id']]['canmoddelcomment'] == 1)
						{
							$caneditdel = 1;
						}
						continue;
					}

				}
			}
			
			//check if user in default mod groups
			if(count(array_intersect(array(3,4), $usergroups)))
			{
				$canapprove = 1;
				$caneditdel = 1;
			}
	
			//load language if we are going to use it
			if($canapprove || $caneditdel)
			{
				$lang->load("myshowcase");
			}
			
			$showcase_path = $mybb->settings['bburl'].'/'.$showcase['f2gpath'].$showcase['mainfile'];
			
			//awaiting approval
			if($canapprove)
			{
				$query = $db->query("SELECT COUNT(*) AS total FROM ".TABLE_PREFIX."myshowcase_data".$id." WHERE approved=0 GROUP BY approved");
				$num_unapproved = $db->fetch_field($query,'total');
				if($num_unapproved > 0)
				{
					$unapproved_text = str_replace("{num}", $num_unapproved, $lang->myshowcase_unapproved_count);
					$unapproved_text = str_replace("{name}", $showcase['name'], $unapproved_text);
					if($unapproved_notice != '')
					{
						$unapproved_notice .= '<br />';
					}
					$unapproved_notice .= "<a href=\"".$showcase_path."?unapproved=1\" />{$unapproved_text}</a>";
				}	
			}
	
			//report notices
			if($caneditdel)
			{
				$rep_ids[$id]['name'] = $showcase['name'];
				$rep_ids[$id]['path'] = $showcase_path;
			}
		}
	}	

	if(count($rep_ids) > 0)
	{
		$ids = implode(',', array_keys($rep_ids));
		$query = $db->query("SELECT `id`, COUNT(*) AS total FROM ".TABLE_PREFIX."myshowcase_reports WHERE `id` IN (".$ids.") AND `status`=0 GROUP BY `id`, `status`");
		while($reports = $db->fetch_array($query))
		{
			$reported_text = str_replace("{num}", $reports['total'], $lang->myshowcase_report_count);
			$reported_text = str_replace("{name}", $rep_ids[$reports['id']]['name'], $reported_text);
			if($reported_notice != '')
			{
				$reported_notice .= '<br />';
			}
			$reported_notice .= "<a href=\"".$rep_ids[$reports['id']]['path']."?action=reports\" />{$reported_text}</a>";
		}	
	}
	
	//get templates
	if($unapproved_notice != '')
	{
		eval("\$myshowcase_unapproved = \"".$templates->get("myshowcase_unapproved")."\";");
	}

	if($reported_notice != '')
	{
		eval("\$myshowcase_reported = \"".$templates->get("myshowcase_reported")."\";");
	}
}

/**
 * Insert default permissions for any new groups.
 * since we can't get new group ID from add group hook,
 * we need to use the edit group hook which is called
 * after a successful add group
 */
function myshowcase_insert_group_perms()
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
function myshowcase_delete_group_perms()
{
	global $db, $cache, $usergroup;

	$db->delete_query('myshowcase_permissions', "gid='{$usergroup['gid']}'");
	myshowcase_update_cache('permissions');
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

//get the myshowcase counts for users that posted in the thread. keeps
//from having to do it every post, just every page view
function myshowcase_get_user_myshowcases_from_thread()
{
	global $db, $mybb, $thread, $cache, $myshowcase_uids;

	//get list of enabled myshowcases with postbit links turned on
	$myshowcase_uids = array();
	
	$myshowcases = $cache->read('myshowcase_config');
	foreach($myshowcases as $id => $myshowcase)
	{
		if($myshowcase['enabled'] && $myshowcase['link_in_postbit'])
		{
			$myshowcase_uids[$myshowcase['id']]['name'] = $myshowcase['name'];
			$myshowcase_uids[$myshowcase['id']]['mainfile'] = $myshowcase['mainfile'];
			$myshowcase_uids[$myshowcase['id']]['f2gpath'] = $myshowcase['f2gpath'];
		}
	}

	//if we have any myshowcases to link....
	if(count($myshowcase_uids) > 0)
	{
		$gidlist = implode(',', array_keys($myshowcase_uids));

		//get uids for users that posted to the thread
		$query = $db->query("SELECT uid FROM ".TABLE_PREFIX."posts WHERE tid=".(int)$thread['tid']." AND uid > 0 GROUP BY uid");
		$uids = array();
		while($result = $db->fetch_array($query))
		{
			$uids[$result['uid']] = 0;
		}
		$uidlist = implode(',', array_keys($uids));
		unset($query);
		unset($result);

		//get myshowcase counts for users in thread
		if(count($uids))
		{
			foreach($myshowcase_uids as $gid => $data)
			{
				$query = $db->query("SELECT uid, count(uid) AS total FROM ".TABLE_PREFIX."myshowcase_data".$gid." WHERE uid IN ({$uidlist}) AND approved = 1 GROUP BY uid");
				while($result = $db->fetch_array($query))
				{
					$myshowcase_uids[$gid]['uids'][$result['uid']] = $result['total'];
				}
			}
		}
		unset($query);
		unset($result);
	}

}

//add myshowcase links/counts
function myshowcase_postbit(&$post)
{
	global $mybb, $_SERVER, $lang, $myshowcase_uids;

	if(count($myshowcase_uids) > 0)
	{
		foreach($myshowcase_uids as $myshowcase => $data)
		{
			$showcase_name = $data['name'];
			$showcase_file = $data['mainfile'];
			$showcase_fldr = $data['f2gpath'];

			/* URL Definitions */
			if($mybb->settings['seourls'] == "yes" || ($mybb->settings['seourls'] == "auto" && $_SERVER['SEO_SUPPORT'] == 1))
			{
				$showcase_file = strtolower($data['name']).".html";
			}
			else
			{
				$showcase_file = $data['mainfile'];
			}

			if($data['uids'][$post['uid']] > 0)
			{
				
				$post['user_details'] .= '<br />'.$showcase_name.':  <a href="'.$showcase_fldr.$showcase_file.'?search=username&searchterm='.rawurlencode($post['username']).'&exactmatch=1">'.$data['uids'][$post['uid']].'</a>';
			}
		}
	}
	
	return $post;
}

//function to pull a random entry from a random showcase (if enabled)
function myshowcase_portal_random()
{
	global $db, $lang, $mybb, $cache, $templates, $portal_rand_showcase;

	//if user is guest or no showcases set to show on portal output something else?
	/*
	if($mybb->user['uid'] == 0)		
	{
		//add code here to display something for guests
	}
	else
	*/
	{
		$portal_rand_showcase = myshowcase_get_random();
		if(!$portal_rand_showcase)
		{
			//add code here to use portal_basic_box template box or some 
			//other output if a random showcase with attachments is not found
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
		
		eval("\$portal_rand_showcase = \"".$templates->get("myshowcase_portal_rand_showcase")."\";");
		return $portal_rand_showcase;
	}
}

//build info for who's online
function myshowcase_fetch_wol_activity(&$user_activity)
{
	global $user, $mybb, $cache;

	//get filename of location
	$split_loc = explode(".php", $user_activity['location']);
	if($split_loc[0] == $user['location'])
	{
		$filename = '';
	}
	else
	{
		$filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
	}
	
	//get query params
	if($split_loc[1])
	{
		$temp = explode("&", my_substr($split_loc[1], 1));
		foreach($temp as $param)
		{
			$temp2 = explode("=", $param, 2);
			$temp2[0] = str_replace("amp;", '', $temp2[0]);
			$parameters[$temp2[0]] = $temp2[1];
		}
	}

	//get cache of configured myshowcases
	$myshowcase_config = $cache->read("myshowcase_config");
	
	//check cache for matching filename
	//have to do it this way since the filename can vary for each myshowcase
    if(is_array($myshowcase_config))
    {
        foreach($myshowcase_config as $id => $myshowcase)
        {
            $split_mainfile = explode(".php", $myshowcase['mainfile']);
            if($split_mainfile[0] == $filename)
            {
                //preload here so we don't need to get it in next function
                $user_activity['myshowcase_filename'] = $filename;
                $user_activity['myshowcase_name'] = $myshowcase['name'];
                $user_activity['myshowcase_id'] = $myshowcase['id'];
                $user_activity['myshowcase_mainfile'] = $myshowcase['mainfile'];
                
                if($parameters['action'] == "view")
                {
                    $user_activity['activity'] = "myshowcase_view";
                    if(is_numeric($parameters['gid']))
                    {
                        $user_activity['gid'] = $parameters['gid'];
                    }
                }
                elseif($parameters['action'] == "new")
                {
                    $user_activity['activity'] = "myshowcase_new";
                }
                elseif($parameters['action'] == "attachment")
                {
                    $user_activity['activity'] = "myshowcase_view_attach";
                    if(is_numeric($parameters['aid']))
                    {
                        $user_activity['aid'] = $parameters['aid'];
                    }
                }
                elseif($parameters['action'] == "edit")
                {
                    $user_activity['activity'] = "myshowcase_edit";
                    if(is_numeric($parameters['gid']))
                    {
                        $user_activity['gid'] = $parameters['gid'];
                    }
                }
                else
                {
                    $user_activity['activity'] = "myshowcase_list";
                }

                //if here, we found the lcoation, so exit loop
                continue;
            }
        }
    }
    
    return $user_activity;
}

//setup friendly WOL locations 
function myshowcase_friendly_wol_location_end(&$plugin_array)
{
	global $db, $lang, $mybb, $_SERVER, $user;

	$lang->load('myshowcase');
	
	//get filename of location
	$split_loc = explode(".php", $plugin_array['user_activity']['location']);
	if($split_loc[0] == $user['location'])
	{
		$filename = '';
	}
	else
	{
		$filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
	}

	/* URL Definitions */
	if($mybb->settings['seourls'] == "yes" || ($mybb->settings['seourls'] == "auto" && $_SERVER['SEO_SUPPORT'] == 1))
	{
		$myshowcase_name = strtolower($plugin_array['user_activity']['myshowcase_name']);

        //clean the name and make it suitable for SEO
        //cleaning borrowed from Google SEO plugin
        $pattern = '!"#$%&\'( )*+,-./:;<=>?@[\]^_`{|}~';
        $pattern = preg_replace("/[\\\\\\^\\-\\[\\]\\/]/u",
                        "\\\\\\0",
                        $pattern);

        // Cut off punctuation at beginning and end.
        $myshowcase_name = preg_replace("/^[$pattern]+|[$pattern]+$/u",
                        "",
                        strtolower($myshowcase_name));

        // Replace middle punctuation with one separator.
        $myshowcase_name = preg_replace("/[$pattern]+/u",
                        '-',
                        $myshowcase_name);
 
		$myshowcase_url = $myshowcase_name.".html";
		$myshowcase = $myshowcase_name."-page-{page}.html";
		$myshowcase_url_view  = $myshowcase_name."-view-{gid}.html";
		$myshowcase_url_new  = $myshowcase_name."-new.html";
		$myshowcase_url_view_attach  = $myshowcase_name."-attachment-{aid}.html";
		$amp = '?';
	}
	else
	{
		$myshowcase_url = $plugin_array['user_activity']['myshowcase_mainfile'];
		$myshowcase_url_paged = $plugin_array['user_activity']['myshowcase_mainfile']."?page={page}";
		$myshowcase_url_view  = $plugin_array['user_activity']['myshowcase_mainfile']."?action=view&gid={gid}";
		$myshowcase_url_new  = $plugin_array['user_activity']['myshowcase_mainfile']."?action=new";
		$myshowcase_url_view_attach  = $plugin_array['user_activity']['myshowcase_mainfile']."?action=attachment&aid={aid}";
		$amp = '&';
	}

	switch($plugin_array['user_activity']['activity'])
	{
		case "myshowcase_list":
			$plugin_array['location_name'] = $lang->sprintf($lang->viewing_myshowcase_list, $myshowcase_url,$plugin_array['user_activity']['myshowcase_name']);
		break;

		case "myshowcase_view":
			if(array_key_exists('gid', $plugin_array['user_activity']))
			{
				$query = $db->simple_select("myshowcase_data{$plugin_array['user_activity']['myshowcase_id']}", "gid,uid", "gid=".$plugin_array['user_activity']['gid']);
				while($myshowcase = $db->fetch_array($query))
				{
					$uid = $myshowcase['uid'];
					$userinfo = get_user($uid);
				}
			}
			$plugin_array['location_name'] = $lang->sprintf($lang->viewing_myshowcase, str_replace('{gid}', $plugin_array['user_activity']['gid'], $myshowcase_url_view), $plugin_array['user_activity']['myshowcase_name'], get_profile_link($uid), $userinfo['username']);
		break;

		case "myshowcase_new":
			$plugin_array['location_name'] = $lang->sprintf($lang->viewing_myshowcase_new, $myshowcase_url_new, $plugin_array['user_activity']['myshowcase_name']);
		break;

		case "myshowcase_edit":
			$plugin_array['location_name'] = $lang->sprintf($lang->viewing_myshowcase_edit, $plugin_array['user_activity']['myshowcase_name']);
		break;

		case "myshowcase_view_attach":
			if(array_key_exists('aid', $plugin_array['user_activity']))
			{
				$query = $db->simple_select("myshowcase_attachments", "aid,gid,uid", "aid=".$plugin_array['user_activity']['aid']);
				while($showcase = $db->fetch_array($query))
				{
					$uid = $showcase['uid'];
					$gid = $showcase['gid'];
					$userinfo = get_user($uid);
				}
			}
			$plugin_array['location_name'] = $lang->sprintf($lang->viewing_myshowcase_attach, str_replace('{aid}', $plugin_array['user_activity']['aid'], $myshowcase_url_view_attach), str_replace('{gid}', $gid, $myshowcase_view_url), $plugin_array['user_activity']['myshowcase_name'], get_profile_link($uid), $userinfo['username']);
		break;
	}
	
	return $plugin_array;
}

?>
