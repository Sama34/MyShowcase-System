<?php
/**
 * MyShowcase Plugin for MyBB - MyShowcase Class
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
				http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\class_myshowcase.php
 *
 */

class MyShowcaseSystem
{

	/**
	 * The ID of the current showcase.
	 * @var integer
	 */
	public $id;

	/**
	 * The name of the current showcase
	 * @var string
	 */
	public $name;

	/**
	 * The description of the current showcase
	 * @var string
	 */
	public $description;

	/**
	 * The main PHP file of the current showcase
	 * @var string
	 */
	public $mainfile;

	/**
	 * The ID of the fieldset used in the current showcase
	 * @var integer
	 */
	public $fieldsetid;

	/**
	 * The image folder of the current showcase
	 * @var string
	 */
	public $imgfolder;

	/**
	 * The default image for each record in the list view of the current showcase
	 * @var string
	 */
	public $defaultimage;

	/**
	 * The watermark image to use as a watermark
	 * @var string
	 */
	public $watermarkimage;

	/**
	 * The watermark location
	 * @var string
	 */
	public $watermarkloc;

	/**
	 * The option to use an attachment in list view or not
	 * @var string
	 */
	public $use_attach;

	/**
	 * The relative path from the forum to the current showcase
	 * @var string
	 */
	public $f2gpath;

	/**
	 * The status of the current showcase
	 * @var integer
	 */
	public $enabled;

	/**
	 * The number of seconds from last edit to remove entry of the current showcase
	 * @var integer
	 */
	public $prunetime;

	/**
	 * The moderation status of the current showcase
	 * @var integer
	 */
	public $modnewedit;

	/**
	 * Allow smilies in the current showcase
	 * @var integer
	 */
	public $allowsmilies;

	/**
	 * Allow BBCode the current showcase
	 * @var integer
	 */
	public $allowbbcode;

	/**
	 * Allow HTML the current showcase
	 * @var integer
	 */
	public $allowhtml;

	/**
	 * The maxlength of the 'other' field of the current showcase
	 * @var integer
	 */
	public $othermaxlength;

	/**
	 * Allow attachments in the current showcase
	 * @var integer
	 */
	public $allow_attachments;

	/**
	 * Allow comments in the current showcase
	 * @var integer
	 */
	public $allow_comments;

	/**
	 * The thumbnail width of the current showcase
	 * @var integer
	 */
	public $thumb_width;

	/**
	 * The thumbnail height of the current showcase
	 * @var integer
	 */
	public $thumb_height;

	/**
	 * The max comment length of the current showcase
	 * @var integer
	 */
	public $comment_length;

	/**
	 * The number of comments to display initially of the current showcase
	 * @var integer
	 */
	public $comment_dispinit;

	/**
	 * The number of columns of attachments to disaply in the current showcase
	 * @var integer
	 */
	public $disp_attachcols;

	/**
	 * Dispaly empty fields in the current showcase
	 * @var integer
	 */
	public $disp_empty;

	/**
	 * Try to display and entry with attachment in this showcase on the portal
	 * @var integer
	 */
	public $portal_random;

	/**
	 * Table name of the showcase data
	 * @var string
	 */
	public $table_name;

	/**
	 * Basename of the calling file
	 * @var string
	 */
	public $prefix;

	/**
	 * Clean name for URL/SEO
	 * @var string
	 */
	public $clean_name;

	/**
	 * User permissions array for this showcase
	 * @var Array
	 */
	public $userperms;

	/**
	 * Mod permissions array for this showcase
	 * @var Array
	 */
	public $modperms;

	/**
	 * Constructor of class.
	 *
	 * @return Showcase
	 */
	function __construct($filename=THIS_SCRIPT)
	{
		global $db, $mybb, $cache;

        //make sure plugin is installed and active
        $plugin_cache = $cache->read('plugins');
        if(!$db->table_exists('myshowcase_config') || !array_key_exists('myshowcase', $plugin_cache['active']))
        {
            error("The MyShowcase System has not been installed and activated yet.");
        }
        
		//get this showcase's config info
		$showcases = $cache->read('myshowcase_config');
		if(!is_array($showcases))
		{
			myshowcase_update_cache('config');
			$showcases = $cache->read('myshowcase_config');
		}
		//check if the requesting file is in the cache
		foreach($showcases as $showcase)
		{
			if($showcase['mainfile'] == $filename)//THIS_SCRIPT)
			{
				foreach($showcase as $key=>$value)
				{
					$this->$key = $value;
				}
				continue;
			}
		}

        //clean the name and make it suitable for SEO
        //cleaning borrowed from Google SEO plugin
        $pattern = '!"#$%&\'( )*+,-./:;<=>?@[\]^_`{|}~';
        $pattern = preg_replace("/[\\\\\\^\\-\\[\\]\\/]/u",
                        "\\\\\\0",
                        $pattern);

        // Cut off punctuation at beginning and end.
        $this->clean_name = preg_replace("/^[$pattern]+|[$pattern]+$/u",
                        "",
                        strtolower($this->name));

        // Replace middle punctuation with one separator.
        $this->clean_name = preg_replace("/[$pattern]+/u",
                        '-',
                        $this->clean_name);
        
		//make sure data table exists and assign table name var if it does
		if($db->table_exists('myshowcase_data'.$this->id))
		{
			$this->table_name = 'myshowcase_data'.$this->id;
		}

		//simple tests for proper setup.
		if(!$this->id || !$this->table_name || $this->fieldsetid == 0)
		{
			error("This file is not properly configured in the MyShowcase Admin section of the ACP");
		}

		//get basename of the calling file. This is used later for SEO support
		$temp = explode('.',$this->mainfile);
		$this->prefix = $temp[0];

		//get group permissions now
		$this->userperms = $this->get_user_permissions($mybb->user);


	}

	/**
	* get group permissions for a specific showcase
	*
	* @return array group permissions for the specific showcase
	*/
	function get_group_permissions()
	{
		global $db, $cache, $config;

		require_once(MYBB_ROOT.$config['admin_dir'].'/modules/myshowcase/module_meta.php');
		$showcase_group_perms = array();

		//load permsissions already in cache
//		$query = $db->simple_select("myshowcase_permissions", "*", "id={$this->id}");
//		while($showperms = $db->fetch_array($query))
		$permcache = $cache->read('myshowcase_permissions');
		foreach($permcache[$this->id] as $id => $showperms)
		{
			$showcase_group_perms[$showperms['gid']]['id'] = $showperms['gid'];
			$showcase_group_perms[$showperms['gid']]['name'] = $usergroups[$showperms['gid']]['title'];
			$showcase_group_perms[$showperms['gid']]['canview'] = $showperms['canview'];
			$showcase_group_perms[$showperms['gid']]['canadd'] = $showperms['canadd'];
			$showcase_group_perms[$showperms['gid']]['canedit'] = $showperms['canedit'];
			$showcase_group_perms[$showperms['gid']]['cancomment'] = $showperms['cancomment'];
			$showcase_group_perms[$showperms['gid']]['canattach'] = $showperms['canattach'];
			$showcase_group_perms[$showperms['gid']]['candelowncomment'] = $showperms['candelowncomment'];
			$showcase_group_perms[$showperms['gid']]['candelauthcomment'] = $showperms['candelauthcomment'];
			$showcase_group_perms[$showperms['gid']]['canviewcomment'] = $showperms['canviewcomment'];
			$showcase_group_perms[$showperms['gid']]['canviewattach'] = $showperms['canviewattach'];
			$showcase_group_perms[$showperms['gid']]['cansearch'] = $showperms['cansearch'];
			$showcase_group_perms[$showperms['gid']]['canwatermark'] = $showperms['canwatermark'];
			$showcase_group_perms[$showperms['gid']]['attachlimit'] = $showperms['attachlimit'];
			$showcase_group_perms[$showperms['gid']]['intable'] = 1;
		}

		$usergroups = $cache->read('usergroups');

		//load defaults if group not already in cache (e.g. group added since myshowcase created)
		foreach($usergroups as $group)
		{
			if(!array_key_exists($group['gid'], $showcase_group_perms))
			{
				$showcase_group_perms[$group['gid']]['id'] = $group['gid'];
				$showcase_group_perms[$group['gid']]['name'] = $group['title'];
				$showcase_group_perms[$group['gid']]['canview'] = $showcase_defaultperms['canview'];
				$showcase_group_perms[$group['gid']]['canadd'] = $showcase_defaultperms['canadd'];
				$showcase_group_perms[$group['gid']]['canedit'] = $showcase_defaultperms['canedit'];
				$showcase_group_perms[$group['gid']]['cancomment'] = $showcase_defaultperms['cancomment'];
				$showcase_group_perms[$group['gid']]['canattach'] = $showcase_defaultperms['canattach'];
				$showcase_group_perms[$group['gid']]['candelowncomment'] = $showcase_defaultperms['candelowncomment'];
				$showcase_group_perms[$group['gid']]['candelauthcomment'] = $showcase_defaultperms['candelauthcomment'];
				$showcase_group_perms[$group['gid']]['canviewcomment'] = $showcase_defaultperms['canviewcomment'];
				$showcase_group_perms[$group['gid']]['canviewattach'] = $showcase_defaultperms['canviewattach'];
				$showcase_group_perms[$group['gid']]['cansearch'] = $showcase_defaultperms['cansearch'];
				$showcase_group_perms[$group['gid']]['canwatermark'] = $showcase_defaultperms['canwatermark'];
				$showcase_group_perms[$group['gid']]['attachlimit'] = $showcase_defaultperms['attachlimit'];
				$showcase_group_perms[$group['gid']]['intable'] = 0;
			}
		}

		return $showcase_group_perms;
	}

	/**
	* get user permissions for a specific showcase
	*
	* @param int The User array for the user to build permissions for
	* @return array user permissions for the specific showcase
	*/
	function get_user_permissions($user)
	{
		global $cache, $mybb;
		
		//basic user permissions
		
		$showcase_group_perms = $this->get_group_permissions();

		//init to guest permissions
		$showcase_user_perms['canview'] = $showcase_group_perms[1]['canview'];
		$showcase_user_perms['canadd'] = $showcase_group_perms[1]['canadd'];
		$showcase_user_perms['canedit'] = $showcase_group_perms[1]['canedit'];
		$showcase_user_perms['cancomment'] = $showcase_group_perms[1]['cancomment'];
		$showcase_user_perms['canattach'] = $showcase_group_perms[1]['canattach'];
		$showcase_user_perms['candelowncomment'] = $showcase_group_perms[1]['candelowncomment'];
		$showcase_user_perms['candelauthcomment'] = $showcase_group_perms[1]['candelauthcomment'];
		$showcase_user_perms['canviewcomment'] = $showcase_group_perms[1]['canviewcomment'];
		$showcase_user_perms['canviewattach'] = $showcase_group_perms[1]['canviewattach'];
		$showcase_user_perms['cansearch'] = $showcase_group_perms[1]['cansearch'];
		$showcase_user_perms['canwatermark'] = $showcase_group_perms[1]['canwatermark'];
		$showcase_user_perms['attachlimit'] = $showcase_group_perms[1]['attachlimit'];

		//set default mod perms
		$modperms = array(
			'canmodapprove' => 0,
			'canmodedit' => 0,
			'canmoddelete' => 0,
			'canmoddelcomment' =>0
			);
			
		//if not a guest, keep going....
		if($user['uid'] > 0)
		{
			//user permissions for user's groups
			$groups_csv = $user['usergroup'].($user['additionalgroups'] ? ','.$user['additionalgroups'] : '');
			$groups = explode(',', $groups_csv);

			foreach($showcase_user_perms as $field => $value)
			{
				foreach($groups as $gid)
				{
					$showcase_user_perms[$field] = ($showcase_group_perms[$gid][$field] == -1 ? -1 : max($showcase_user_perms[$field], $showcase_group_perms[$gid][$field]));
				}
			}

			//check moderator perms

			//assign full mod perms as default for supermod, admin groups if user in those groups
			$result = array_intersect(array(3,4), $groups);
			if(count($result) > 0)
			{
				$modperms = array(
					'canmodapprove' => 1,
					'canmodedit' => 1,
					'canmoddelete' => 1,
					'canmoddelcomment' => 1
					);
			}
			
			//get showcase moderator cache to handle additional mods/modgroups
			$modcache = $cache->read('myshowcase_moderators');
			if(is_array($modcache[$this->id]))
			{
				//get moderators specific to this myshowcase
				$mods = $modcache[$this->id];

				//check if user in additional moderator usergroup and use those perms instead (in case admin sets lower than full perms for  mod/super/admin groups)
				foreach($mods as $mid => $moddata)
				{
					if($moddata['isgroup'] && in_array($moddata['uid'],$groups))
					{
						$modperms2['canmodapprove'] = max($modperms2['canmodapprove'], $moddata['canmodapprove']);
						$modperms2['canmodedit'] = max($modperms2['canmodedit'], $moddata['canmodedit']);
						$modperms2['canmoddelete'] = max($modperms2['canmoddelete'], $moddata['canmoddelete']);
						$modperms2['canmoddelcomment'] = max($modperms2['canmoddelcomment'], $moddata['canmoddelcomment']);
					}

					//check for specific user and use those permissions regardless of group perms
					if(!$moddata['isgroup'] && $moddata['uid'] == $mybb->user['uid'])
					{
						$modperms3 = array(
							'canmodapprove' => $moddata['canmodapprove'],
							'canmodedit' => $moddata['canmodedit'],
							'canmoddelete' => $moddata['canmoddelete'],
							'canmoddelcomment' => $moddata['canmoddelcomment']
							);
							
						//since we want user specific perms first, might as well continue here and skip the rest of the checks
						continue;
					}
				}

				//if user is in assigned moderator group, $modperms2 is an array so use those permissions
				if(is_array($modperms2))
				{
						$modperms = $modperms2;
				}
				
				//if user is in assigned as moderator , $modperms3 is an array so use those permissions
				if(is_array($modperms3))
				{
						$modperms = $modperms3;
				}
			}
		}
	
		//insert mod perms into user perms
		$showcase_user_perms = array_merge($showcase_user_perms, $modperms);

		return $showcase_user_perms;
	}

	/**
	* get ids from cookie inline moderation
	*/
	function getids($id, $type)
	{
		global $mybb;
		$cookie = "inlinemod_".$type.$id;
		$ids = explode("|", $mybb->cookies[$cookie]);
		foreach($ids as $id)
		{
			if($id != '')
			{
				$newids[] = intval($id);
			}
		}
		return $newids;
	}

	/**
	* delete a showcase entry
	*/
	function delete($gid)
	{
		global $db;

		$gid = intval($gid);

		//delete attachments
		$this->delete_attachments($gid, $this->id);

		//delete comments
		$this->delete_comments($gid, $this->id);

		//delete showcase
		$query = $db->delete_query($this->table_name, "gid=".$gid);
	}

	/**
	* delete attachments from a showcase
	*/
	function delete_attachments($gid, $id)
	{
		global $db;

		$gid = intval($gid);
		$id = intval($id);
		
		$query = $db->simple_select("myshowcase_attachments", "*", "gid={$gid} AND id={$id}");
		if($db->num_rows($query) != 0)
		{
			if(!function_exists(myshowcase_remove_attachments))
			{
				require_once "functions_myshowcase_upload.php";
			}
			myshowcase_remove_attachments($gid, $id);

			//delete records
		}
	}

	/**
	* delete a comment
	*/
	function delete_comments($gid, $id)
	{
		global $db;

		$gid = intval($gid);
		$id = intval($id);

		$query = $db->delete_query("myshowcase_comments", "gid={$gid} AND id={$id}");
	}

	/**
	* clear cookie inline moderation
	*/
	function clearinline($id, $type)
	{
		my_unsetcookie("inlinemod_".$type.$id);
	}

	/**
	* add to cookie inline moderation
	*/
	function extendinline($id, $type)
	{
		global $mybb;

		my_setcookie("inlinemod_$type.$id", '', TIME_NOW+3600);
	}

}
?>
