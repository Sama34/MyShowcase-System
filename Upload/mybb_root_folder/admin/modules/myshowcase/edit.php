<?php
/**
 * MyShowcase Plugin for MyBB - Admin module for Showcase Editing
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.1
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
				http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \admin\modules\myshowcase\edit.php
 *
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$page->add_breadcrumb_item($lang->myshowcase_admin_edit_existing, "index.php?module=myshowcase-edit");

//preload usergroups
$usergroups = $cache->read('usergroups');

//make sure plugin is installed and active
$plugin_cache = $cache->read('plugins');
if(!$db->table_exists('myshowcase_config') || !array_key_exists('myshowcase', $plugin_cache['active']))
{
	flash_message($lang->myshowcase_plugin_not_installed, 'error');
	admin_redirect("index.php?module=config-plugins");
}

//generate interval list using language vars
$prune_intervals = array();
$prune_intervals['days'] = $lang->days;
$prune_intervals['weeks'] = $lang->weeks;
$prune_intervals['months'] = $lang->months;
$prune_intervals['years'] = $lang->years;

//generate watermark location list using language vars
$watermark_locs = array();
$watermark_locs['upper-left'] = $lang->myshowcase_upper_left;
$watermark_locs['upper-right'] = $lang->myshowcase_upper_right;
$watermark_locs['center'] = $lang->myshowcase_center;
$watermark_locs['lower-left'] = $lang->myshowcase_lower_left;
$watermark_locs['lower-right'] = $lang->myshowcase_lower_right;

$plugins->run_hooks("admin_myshowcase_edit_begin");

if(isset($mybb->input['id']) && is_numeric($mybb->input['id']) && $mybb->input['action'] != '')
{
	if($mybb->input['action'] == 'edit-main' || $mybb->input['action'] == 'edit-other' || $mybb->input['action'] == 'edit-perms' || $mybb->input['action'] == 'edit-mod' || $mybb->input['action'] == 'del-mod')
	{
		//check if set is in use, if so, limit edit ability
		$can_edit = true;
		if($db->table_exists('myshowcase_data'.$mybb->input['id']))
		{
			$can_edit = false;
		}

		$page->output_header($lang->myshowcase_admin_edit_existing);
	
		//user pushed a subimt button
		if($mybb->request_method == 'post')
		{
			switch($mybb->input['action'])
			{
				case "edit-perms":
					//update myshowcase permissions
					$permdata = $mybb->input['permissions'];

					foreach($usergroups as $group)
					{
						$groupdata = $permdata[$group['gid']];
						
						$update_array = array();

						foreach($showcase_defaultperms as $field => $value)
						{
							$update_array[$field] = (isset($groupdata[$field]) ? $groupdata[$field] : 0);
						}
						$db->update_query("myshowcase_permissions", $update_array, "id='{$mybb->input['id']}' AND gid='{$group['gid']}'");
					}

					myshowcase_update_cache('permissions');

                    if($db->affected_rows())
					{
                        flash_message($lang->myshowcase_edit_success.': '.$lang->myshowcase_admin_permissions, 'success');
                        admin_redirect("index.php?module=myshowcase-edit&action=edit-perms&id={$mybb->input['id']}");
					}
					else
					{
						flash_message($lang->myshowcase_edit_failed.': '.$lang->myshowcase_admin_permissions, 'error');
                        admin_redirect("index.php?module=myshowcase-edit&action=edit-perms&id={$mybb->input['id']}");
					}
						
				break;

				case "edit-main":
					//update main myshowcase settings
					$update_array = array(
						'name' => $db->escape_string($mybb->input['name']),
						'description' => $db->escape_string($mybb->input['description']),
						'mainfile' => $db->escape_string($mybb->input['mainfile']),
						'imgfolder' => $db->escape_string($mybb->input['imgfolder']),
						'defaultimage' => $db->escape_string($mybb->input['defaultimage']),
						'watermarkimage' => $db->escape_string($mybb->input['watermarkimage']),
						'watermarkloc' => $mybb->input['watermarkloc'],						
						'use_attach' => $mybb->input['use_attach'],
						'f2gpath' => $db->escape_string($mybb->input['f2gpath'])
						);

					if($can_edit)
					{
						$update_array = array_merge($update_array, array('fieldsetid' => $mybb->input['fieldset']));
					}

					$db->update_query("myshowcase_config", $update_array, "id='{$mybb->input['id']}'");

                    myshowcase_update_cache('config');
					
                    if($db->affected_rows())
					{
                        flash_message($lang->myshowcase_edit_success.': '.$lang->myshowcase_admin_main_options, 'success');
                        admin_redirect("index.php?module=myshowcase-edit&action=edit-main&id={$mybb->input['id']}");
					}
					else
					{
                        flash_message($lang->myshowcase_edit_success.': '.$lang->myshowcase_admin_main_options, 'error');
                        admin_redirect("index.php?module=myshowcase-edit&action=edit-main&id={$mybb->input['id']}");
					}
						
				break;
					
				case "edit-other":
					//update other myshowcase settings
					$update_array = array(
						'modnewedit' => $mybb->input['modnewedit'],
						'othermaxlength' => $db->escape_string($mybb->input['othermaxlength']),
						'allow_attachments' => $mybb->input['allow_attachments'],
						'allow_comments' => $mybb->input['allow_comments'],
						'thumb_width' => $db->escape_string($mybb->input['thumb_width']),
						'thumb_height' => $db->escape_string($mybb->input['thumb_height']),
						'comment_length' => $db->escape_string($mybb->input['comment_length']),
						'comment_dispinit' => $db->escape_string($mybb->input['comment_dispinit']),
						'disp_attachcols' => $db->escape_string($mybb->input['disp_attachcols']),
						'disp_empty' => $mybb->input['disp_empty'],
						'link_in_postbit' => $mybb->input['link_in_postbit'],
						'portal_random' => $mybb->input['portal_random'],
						'prunetime' => $mybb->input['prunetime'].'|'.$mybb->input['interval'],
						'allowsmilies' => $mybb->input['allowsmilies'],
						'allowbbcode' => $mybb->input['allowbbcode'],
						'allowhtml' => $mybb->input['allowhtml']
						);

					$db->update_query("myshowcase_config", $update_array, "id='{$mybb->input['id']}'");
						
                    myshowcase_update_cache('config');
					
					if($db->affected_rows())
					{
                        flash_message($lang->myshowcase_edit_success.': '.$lang->myshowcase_admin_other_options, 'success');
                        admin_redirect("index.php?module=myshowcase-edit&action=edit-other&id={$mybb->input['id']}");
					}
					else
					{
                        flash_message($lang->myshowcase_edit_success.': '.$lang->myshowcase_admin_other_options, 'error');
                        admin_redirect("index.php?module=myshowcase-edit&action=edit-other&id={$mybb->input['id']}");
					}
						
				break;

				case "edit-mod":
					//update existing moderator settings
					if($mybb->input['edit'] == 'modperms')
					{
						$modperms = $mybb->input['modperms'];

						foreach($modperms as $mid => $perms)
						{
							$update_array = array(
								'canmodapprove' => (isset($perms['canmodapprove']) ? 1 : 0),
								'canmodedit' => (isset($perms['canmodedit']) ? 1 : 0),
								'canmoddelete' => (isset($perms['canmoddelete']) ? 1 : 0),
								'canmoddelcomment' => (isset($perms['canmoddelcomment']) ? 1 : 0)
								);
							$db->update_query("myshowcase_moderators", $update_array, "id='{$mybb->input['id']}' AND mid='{$mid}'");
						}
					}
					
					//insert new user or group as mod
					if($mybb->input['add'] == 'modgroup')
					{
						//since MyBB 1.6.3 autocomplete adds "(Usergroup X)" and we need to remove that
						$mybb->input['usergroup'] = trim(preg_replace('/\('.$lang->usergroup.'(.+)\)/i', '', $mybb->input['usergroup']));

						$query = $db->simple_select("usergroups", "*", "title='{$mybb->input['usergroup']}'");
						$result = $db->fetch_array($query);
						if(!is_array($result))
						{
							flash_message($lang->myshowcase_mod_invalid, 'error');
							admin_redirect("index.php?module=myshowcase-edit&action=edit-mod&id={$mybb->input['id']}");
						}
						
						$uid = $result['gid'];
						$isgroup = 1;
						
						$insert_array = array(
							'id' => $mybb->input['id'],
							'uid' => $uid,
							'isgroup' => $isgroup,
							'canmodapprove' => (isset($mybb->input['gcanmodapprove']) ? 1 : 0),
							'canmodedit' => (isset($mybb->input['gcanmodedit']) ? 1 : 0),
							'canmoddelete' => (isset($mybb->input['gcanmoddelete']) ? 1 : 0),
							'canmoddelcomment' => (isset($mybb->input['gcanmoddelcomment']) ? 1 : 0)
							);
						
						$db->insert_query("myshowcase_moderators", $insert_array);
					}
					
					if($mybb->input['add'] == 'mod')
					{
						$query = $db->simple_select("users", "*", "username='{$mybb->input['username']}'");
						$result = $db->fetch_array($query);
						if(!is_array($result))
						{
							flash_message($lang->myshowcase_mod_invalid, 'error');
							admin_redirect("index.php?module=myshowcase-edit&action=edit-mod&id={$mybb->input['id']}");
						}
						
						$uid = $result['uid'];
						$isgroup = 0;

						$insert_array = array(
							'id' => $mybb->input['id'],
							'uid' => $uid,
							'isgroup' => $isgroup,
							'canmodapprove' => (isset($mybb->input['ucanmodapprove']) ? 1 : 0),
							'canmodedit' => (isset($mybb->input['ucanmodedit']) ? 1 : 0),
							'canmoddelete' => (isset($mybb->input['ucanmoddelete']) ? 1 : 0),
							'canmoddelcomment' => (isset($mybb->input['ucanmoddelcomment']) ? 1 : 0)
							);
						
						$db->insert_query("myshowcase_moderators", $insert_array);
					}

                    myshowcase_update_cache('moderators');
                    
                    flash_message($lang->myshowcase_edit_success.': '.$lang->myshowcase_admin_moderators, 'success');
					admin_redirect("index.php?module=myshowcase-edit&action=edit-mod&id={$mybb->input['id']}");
    
				break;
			}
			
			$log = array('id' => $mybb->input['id']);
			log_admin_action($log);
		}
		
		if($mybb->input['action'] == 'del-mod')
		{
			$db->delete_query("myshowcase_moderators", "mid={$mybb->input['mid']}");
			
			myshowcase_update_cache('moderators');

			if($db->affected_rows() == 0)
			{
				flash_message($lang->myshowcase_mod_delete_error.': '.$lang->myshowcase_admin_moderators, 'error');
				admin_redirect("index.php?module=myshowcase-edit&action=edit-mod&id={$mybb->input['id']}");
			}
			else
			{
				$log = array('id' => $mybb->input['id']);
				log_admin_action($log);
				
				flash_message($lang->myshowcase_mod_delete_success.': '.$lang->myshowcase_admin_moderators, 'success');
				admin_redirect("index.php?module=myshowcase-edit&action=edit-mod&id={$mybb->input['id']}");
			}
		}

		$tabs = array(
			'main' => $lang->myshowcase_admin_main_options,
			'other' => $lang->myshowcase_admin_other_options,
			'permissions' => $lang->myshowcase_admin_permissions,
			'moderators' => $lang->myshowcase_admin_moderators
		);

		$page->output_tab_control($tabs);

		$query = $db->simple_select("myshowcase_config", "*", "id=".$mybb->input['id']);
		$num_myshowcases = $db->num_rows($query);
		if($num_myshowcases == 0)
		{
			flash_message($lang->myshowcase_edit_invalid_id, 'error');
			admin_redirect("index.php?module=myshowcase-summary");
		}
		else
		{
			$permcache = $cache->read('myshowcase_permissions');//myshowcase_get_group_permissions($mybb->input['id']);
			$showcase_group_perms = $permcache[$mybb->input['id']];

			$plugins->run_hooks("admin_myshowcase_edit_start");

			$showcase_config = $db->fetch_array($query);

			unset($fieldsets);
			$query = $db->simple_select("myshowcase_fieldsets", "*");
			while($result = $db->fetch_array($query))
			{
				$fieldsets[$result['setid']] = $result['setname'];
			}

			//main options tab
			echo "<div id=\"tab_main\">\n";

			$form = new Form("index.php?module=myshowcase-edit&action=edit-main&id=".$mybb->input['id']."#tab_main", "post", "edit");

			$form_container = new FormContainer();

			$general_options = array();
			$general_options[] = $form->generate_text_box("name",$showcase_config['name'], array('id' => 'name', 'style' => 'width: 100px'),array("class" => "align_left"));
			$form_container->output_row("Showcase Name", "This is the name used in links and titles of the myshowcase.", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

			$general_options = array();
			$general_options[] = $form->generate_text_box("description",$showcase_config['description'], array('id' => 'description', 'style' => 'width: 250px'),array("class" => "align_left"));
			$form_container->output_row("Showcase Description", "Simple description of the myshowcase. Not used externally, for admin use only.", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

			$general_options = array();
			$general_options[] = $form->generate_text_box("mainfile",$showcase_config['mainfile'], array('id' => 'mainfile', 'style' => 'width: 250px'),array("class" => "align_left"));
			$form_container->output_row("Main File", "File name that is used with this specific myshowcase.", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

			$general_options = array();
			$general_options[] = $form->generate_text_box("imgfolder",$showcase_config['imgfolder'], array('id' => 'imgfolder', 'style' => 'width: 250px'),array("class" => "align_left"));
			$form_container->output_row("Folder to store attachments", "This is path, relative to the main file, that is used for storing attachemnts.", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

			$general_options = array();
			$general_options[] = $form->generate_text_box("defaultimage",$showcase_config['defaultimage'], array('id' => 'defaultimage', 'style' => 'width: 250px'),array("class" => "align_left"));
			$form_container->output_row("Default List Image", "This is the name of the image file (assuming relative to theme image folder) that is used as the default image for each record in the list view. Empty value will use View text as link.", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

			$general_options = array();
			$general_options[] = $form->generate_text_box("watermarkimage",$showcase_config['watermarkimage'], array('id' => 'watermarkimage', 'style' => 'width: 250px'),array("class" => "align_left"));
			$form_container->output_row("Watermark Image", "Specify the path to the watermark image. If this is a valid file and the user chooses to watermark an attachment, this file will be used as the watermark. Applies to new image attachments only.", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

			$general_options = array();
			$general_options[] = $form->generate_select_box('watermarkloc', $watermark_locs, $showcase_config['watermarkloc'], array('id' => 'watermarkloc'));
			
			$form_container->output_row("Watermark Location", "This setting is only applied to new image attachments. Existing images are not watermarked.", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");
			
			$general_options = array();
			$general_options[] = $form->generate_check_box("use_attach", 1, "Relace Default List Image above with thumbnail of showcase entry", array("checked" => $showcase_config['use_attach']));
			$form_container->output_row("List View Link", "Enabled this feature if you want to replace the default image above with the first showcase attachment, if available. If you are experiencing slow performance, disable this feature.", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

			$general_options = array();
			$general_options[] = $form->generate_text_box("f2gpath",$showcase_config['f2gpath'], array('id' => 'f2gpath', 'style' => 'width: 250px'),array("class" => "align_left"));
			$form_container->output_row("Path to forums", "This is path from the forum index page to the myshowcase. (empty is okay if file is in same folder as forum)", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

			if($can_edit)
			{
				$general_options = array();
				$general_options[] = $form->generate_select_box('fieldset', $fieldsets, $showcase_config['fieldsetid'], array('id' => 'fieldset'),array("class" => "align_center"));

				$form_container->output_row("Field Set", "Field set used to define the myshowcase data table.", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options).$fieldset_note."</div>");
			}
			else
			{
				$fieldset_note = '<font color="red"><br />'.$lang->myshowcase_edit_no_edit_set.'</font>';
				$form_container->output_row("Field Set", "Field set used to define the myshowcase data table.", "<div class=\"group_settings_bit\"><strong>".$fieldsets[$showcase_config['fieldsetid']].'</strong>'.$fieldset_note."</div>");
			}

			$form_container->end();

			$buttons = array();
			$buttons[] = $form->generate_submit_button($lang->myshowcase_edit_save_main, $submit_options);
			$buttons[] = $form->generate_reset_button($lang->reset);
			$form->output_submit_wrapper($buttons);

			$form->end();

			echo "</div>\n";

			//other options tab
			echo "<div id=\"tab_other\">\n";

			$form = new Form("index.php?module=myshowcase-edit&action=edit-other&id=".$mybb->input['id']."#tab_other", "post", "edit");

			$form_container = new FormContainer();

			$prunetime = explode('|',$showcase_config['prunetime']);
			
			$general_options = array();
			$general_options[] = $lang->myshowcase_edit_prunetime."<br />\n".$form->generate_text_box("prunetime", $prunetime[0], array('id' => 'prunetime', 'style' => 'width: 100px'),array("class" => "align_left")).' '.$form->generate_select_box('interval', $prune_intervals, $prunetime[1], array('id' => 'interval'));
			
			$form_container->output_row("Pruning", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

			$general_options = array();
			$general_options[] = $form->generate_check_box("modnewedit", 1, $lang->myshowcase_edit_modnewedit, array("checked" => $showcase_config['modnewedit']));
			$form_container->output_row("Moderation", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

			$general_options = array();
			$general_options[] = $lang->myshowcase_edit_othermaxlength."<br />\n".$form->generate_text_box("othermaxlength", $showcase_config['othermaxlength'], array('id' => 'othermaxlength', 'style' => 'width: 100px'),array("class" => "align_left"));
			$form_container->output_row("Text Type Fields", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

			$general_options = array();
			$general_options[] = $form->generate_check_box("allow_attachments", 1, $lang->myshowcase_edit_allow_attachments, array("checked" => $showcase_config['allow_attachments'])).'<br /><br />';
			$general_options[] = $lang->myshowcase_edit_thumb_width."<br />\n".$form->generate_text_box("thumb_width", $showcase_config['thumb_width'], array('id' => 'thumb_width', 'style' => 'width: 100px'),array("class" => "align_left"));
			$general_options[] = $lang->myshowcase_edit_thumb_height."<br />\n".$form->generate_text_box("thumb_height", $showcase_config['thumb_height'],array('id' => 'thumb_height', 'style' => 'width: 100px'),array("class" => "align_left"));
			$form_container->output_row("Attachments", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

			$general_options = array();
			$general_options[] = $form->generate_check_box("allow_comments", 1, $lang->myshowcase_edit_allow_comments, array("checked" => $showcase_config['allow_comments'])).'<br /><br />';
			$general_options[] = $lang->myshowcase_edit_comment_length."<br />\n".$form->generate_text_box("comment_length", $showcase_config['comment_length'], array('id' => 'comment_length', 'style' => 'width: 100px'),array("class" => "align_left"));
			$form_container->output_row("Comments", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

			$general_options = array();
			$general_options[] = $form->generate_check_box("allowsmilies", 1, $lang->myshowcase_edit_allow_smilies, array("checked" => $showcase_config['allowsmilies']));
			$general_options[] = $form->generate_check_box("allowbbcode", 1, $lang->myshowcase_edit_allow_bbcode, array("checked" => $showcase_config['allowbbcode']));
			$general_options[] = $form->generate_check_box("allowhtml", 1, $lang->myshowcase_edit_allow_html, array("checked" => $showcase_config['allowhtml']));
			$form_container->output_row("Parser Options (applies to entry and comments)", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

			$general_options = array();
			$general_options[] = $lang->myshowcase_edit_disp_attachcols."<br />\n".$form->generate_text_box("disp_attachcols", $showcase_config['disp_attachcols'], array('id' => 'disp_attachcols', 'style' => 'width: 100px'),array("class" => "align_left"));
			$general_options[] = $lang->myshowcase_edit_comment_dispinit."<br />\n".$form->generate_text_box("comment_dispinit", $showcase_config['comment_dispinit'],array('id' => 'comment_dispinit', 'style' => 'width: 100px'),array("class" => "align_left")).'<br /><br />';
			$general_options[] = $form->generate_check_box("disp_empty", 1, $lang->myshowcase_edit_disp_empty, array("checked" => $showcase_config['disp_empty']));
			$general_options[] = $form->generate_check_box("link_in_postbit", 1, $lang->myshowcase_edit_link_in_postbit, array("checked" => $showcase_config['link_in_postbit']));
			$general_options[] = $form->generate_check_box("portal_random", 1, $lang->myshowcase_edit_portal_random, array("checked" => $showcase_config['portal_random']));
			$form_container->output_row("Display Settings", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");

			$form_container->end();

			$buttons = array();
			$buttons[] = $form->generate_submit_button($lang->myshowcase_edit_save_other, $submit_options);
			$buttons[] = $form->generate_reset_button($lang->reset);
			$form->output_submit_wrapper($buttons);

			$form->end();

			echo "</div>\n";

			//permissions tab
			echo "<div id=\"tab_permissions\">\n";

			$form = new Form("index.php?module=myshowcase-edit&action=edit-perms&id=".$mybb->input['id']."#tab_permissions", "post", "edit");

			$form_container = new FormContainer();

			$form_container->output_row_header($lang->myshowcase_group, array("width" => "23%", "class" => "align_center"));
			$form_container->output_row_header($lang->myshowcase_canadd, array("width" => "6%", "class" => "align_center"));
			$form_container->output_row_header($lang->myshowcase_canedit, array("width" => "6%", "class" => "align_center"));
			$form_container->output_row_header($lang->myshowcase_canattach, array("width" => "6%", "class" => "align_center"));
			$form_container->output_row_header($lang->myshowcase_canview, array("width" => "6%", "class" => "align_center"));
			$form_container->output_row_header($lang->myshowcase_canviewcomment, array("width" => "6%", "class" => "align_center"));
			$form_container->output_row_header($lang->myshowcase_canviewattach, array("width" => "6%", "class" => "align_center"));
			$form_container->output_row_header($lang->myshowcase_cancomment, array("width" => "6%", "class" => "align_center"));
			$form_container->output_row_header($lang->myshowcase_candelowncomment, array("width" => "6%", "class" => "align_center"));
			$form_container->output_row_header($lang->myshowcase_candelauthcomment, array("width" => "6%", "class" => "align_center"));
			$form_container->output_row_header($lang->myshowcase_cansearch, array("width" => "6%", "class" => "align_center"));
			$form_container->output_row_header($lang->myshowcase_canwatermark, array("width" => "6%", "class" => "align_center"));
			$form_container->output_row_header($lang->myshowcase_attachlimit, array("class" => "align_center"));

			reset($usergroups);
			reset($showcase_defaultperms);
			foreach($usergroups as $group)
			{
				$perm_options = array();

				$perm_options[] = $form_container->output_cell('<strong>'.$group['title'].'</strong>',array("class" => "align_left"));

				foreach($showcase_defaultperms as $field => $value)
				{
					$lang_field = 'myshowcase_'.$field;
					if($field == 'attachlimit')
					{
						$perm_options[] = $form_container->output_cell($form->generate_text_box("permissions[{$group['gid']}][{$field}]", $showcase_group_perms[$group['gid']][$field], array('id' => $field.$group['gid'], 'style' => 'width: 100px')),array("class" => "align_center"));
					}
					else
					{
						$perm_options[] = $form_container->output_cell($form->generate_check_box("permissions[{$group['gid']}][{$field}]", 1, "", array('checked' => $showcase_group_perms[$group['gid']][$field], 'id' => $field.$group['gid'])),array("class" => "align_center"));
					}
				}
				$form_container->construct_row();
			}

			$form_container->end();

			$buttons = array();
			$buttons[] = $form->generate_submit_button($lang->myshowcase_edit_save_perms, $submit_options);
			$buttons[] = $form->generate_reset_button($lang->reset);
			$form->output_submit_wrapper($buttons);

			$form->end();

			echo "</div>\n";

			echo "<div id=\"tab_moderators\">\n";
			
				$buttons = array();
				$form = new Form("index.php?module=myshowcase-edit&action=edit-mod&id=".$mybb->input['id']."#tab_moderators", "post", "management");
				echo $form->generate_hidden_field("edit", "modperms");
				$form_container = new FormContainer($lang->sprintf($lang->myshowcase_moderators_assigned, $forum_cache[$fid]['name']));
				$form_container->output_row_header($lang->myshowcase_moderators_name, array('width' => '50%'));
				$form_container->output_row_header($lang->myshowcase_canapprove, array("width" => "10%", "class" => "align_center"));
				$form_container->output_row_header($lang->myshowcase_canedit, array("width" => "10%", "class" => "align_center"));
				$form_container->output_row_header($lang->myshowcase_candelete, array("width" => "10%", "class" => "align_center"));
				$form_container->output_row_header($lang->myshowcase_candelauthcomment, array("width" => "10%", "class" => "align_center"));
				$form_container->output_row_header($lang->myshowcase_moderators_controls, array("width" => "10%", "class" => "align_center"));
				$query = $db->query("
					SELECT m.*, u.username, g.title
					FROM ".TABLE_PREFIX."myshowcase_moderators m
					LEFT JOIN ".TABLE_PREFIX."users u ON (m.isgroup='0' AND m.uid=u.uid)
					LEFT JOIN ".TABLE_PREFIX."usergroups g ON (m.isgroup='1' AND m.uid=g.gid)
					WHERE id='{$mybb->input['id']}'
					ORDER BY m.isgroup DESC, u.username, g.title
				");
				while($moderator = $db->fetch_array($query))
				{
					$perm_options = array();
					if($moderator['isgroup'])
					{
						$moderator['img'] = "<img src=\"styles/{$page->style}/images/icons/group.png\" alt=\"{$lang->myshowcase_moderators_group}\" title=\"{$lang->myshowcase_moderators_group}\" />";
						$perm_options[] = $form_container->output_cell("{$moderator['img']} <a href=\"index.php?module=user-groups&amp;action=edit&amp;gid={$moderator['id']}\">".htmlspecialchars_uni($moderator['title'])."</a>");
					}
					else
					{
						$moderator['img'] = "<img src=\"styles/{$page->style}/images/icons/user.png\" alt=\"{$lang->myshowcase_moderators_user}\" title=\"{$lang->myshowcase_moderators_user}\" />";
						$perm_options[] = $form_container->output_cell("{$moderator['img']} <a href=\"index.php?module=user-users&amp;action=edit&amp;uid={$moderator['id']}\">".htmlspecialchars_uni($moderator['username'])."</a>");
					}
					$perm_options[] = $form_container->output_cell($form->generate_check_box("modperms[{$moderator['mid']}][canmodapprove]", 1, "", array('checked' => $moderator['canmodapprove'], 'id' => "modapprove{$moderator['mid']}")),array("class" => "align_center"));
					$perm_options[] = $form_container->output_cell($form->generate_check_box("modperms[{$moderator['mid']}][canmodedit]", 1, "", array('checked' => $moderator['canmodedit'], 'id' => "modedit{$moderator['mid']}")),array("class" => "align_center"));
					$perm_options[] = $form_container->output_cell($form->generate_check_box("modperms[{$moderator['mid']}][canmoddelete]", 1, "", array('checked' => $moderator['canmoddelete'], 'id' => "moddelete{$moderator['mid']}")),array("class" => "align_center"));
					$perm_options[] = $form_container->output_cell($form->generate_check_box("modperms[{$moderator['mid']}][canmoddelcomment]", 1, "", array('checked' => $moderator['canmoddelcomment'], 'id' => "moddelcomment{$moderator['mid']}")),array("class" => "align_center"));
					$perm_options[] = $form_container->output_cell("<a href=\"index.php?module=myshowcase-edit&amp;action=del-mod&amp;id={$mybb->input['id']}&amp;mid={$moderator['mid']}&amp;my_post_key={$mybb->post_code}#tab_moderators\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->myshowcase_moderators_confirm_deletion}')\">{$lang->myshowcase_moderators_delete}</a>",array("class" => "align_center"));
					$form_container->construct_row();
				}
				
				if($form_container->num_rows() == 0)
				{
					$form_container->output_cell($lang->myshowcase_moderators_none, array('colspan' => 6));
					$form_container->construct_row();
				}
				$form_container->end();
				$buttons[] = $form->generate_submit_button($lang->myshowcase_edit_save_modperms);
				$form->output_submit_wrapper($buttons);
				$form->end();
				
				// Add Usergropups
				$buttons = array();
				$form2 = new Form("index.php?module=myshowcase-edit&action=edit-mod&id=".$mybb->input['id']."#tab_moderators", "post", "management");
				echo $form2->generate_hidden_field("add", "modgroup");
				$form_container = new FormContainer($lang->myshowcase_add_usergroup_as_moderator);
				$form_container->output_row($lang->myshowcase_moderators_group." <em>*</em>", $lang->myshowcase_moderator_usergroup_desc, $form2->generate_text_box('usergroup', "", array('id' => 'usergroup')), 'usergroup');
					$general_options = array();
					$general_options[] = $form->generate_check_box("gcanmodapprove", 1, $lang->myshowcase_edit_modcanapprove, array("checked" =>1)).'<br />';
					$general_options[] = $form->generate_check_box("gcanmodedit", 1, $lang->myshowcase_edit_modcanedit, array("checked" =>1)).'<br />';
					$general_options[] = $form->generate_check_box("gcanmoddelete", 1, $lang->myshowcase_edit_modcandelete, array("checked" =>1)).'<br />';
					$general_options[] = $form->generate_check_box("gcanmoddelcomment", 1, $lang->myshowcase_edit_modcandelcomment, array("checked" =>1));
					$form_container->output_row("Moderator Permissions", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");
				$form_container->end();
				
				// Autocompletion for usergroups
				echo '
				<script type="text/javascript" src="../jscripts/autocomplete.js?ver=1600"></script>
				<script type="text/javascript">
				<!--
					new autoComplete("usergroup", "../xmlhttp.php?action=get_usergroups", {valueSpan: "usergroup"});
				// -->
				</script>';
				
				$buttons[] = $form2->generate_submit_button($lang->myshowcase_add_usergroup_moderator);
				$form2->output_submit_wrapper($buttons);
				$form2->end();
				echo "<br />";
				
				//add Users
				$form2 = new Form("index.php?module=myshowcase-edit&action=edit-mod&id=".$mybb->input['id']."#tab_moderators", "post", "management");
				echo $form2->generate_hidden_field("add", "mod");
				$form_container = new FormContainer($lang->myshowcase_add_user_as_moderator);
				$form_container->output_row($lang->myshowcase_moderators_user." <em>*</em>", $lang->myshowcase_moderator_username_desc, $form2->generate_text_box('username', "", array('id' => 'username')), 'username');
					$general_options = array();
					$general_options[] = $form->generate_check_box("ucanmodapprove", 1, $lang->myshowcase_edit_modcanapprove, array("checked" =>1)).'<br />';
					$general_options[] = $form->generate_check_box("ucanmodedit", 1, $lang->myshowcase_edit_modcanedit, array("checked" =>1)).'<br />';
					$general_options[] = $form->generate_check_box("ucanmoddelete", 1, $lang->myshowcase_edit_modcandelete, array("checked" => 1)).'<br />';
					$general_options[] = $form->generate_check_box("ucanmoddelcomment", 1, $lang->myshowcase_edit_modcandelcomment, array("checked" => 1));
					$form_container->output_row("Moderator Permissions", "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $general_options)."</div>");
				$form_container->end();
				// Autocompletion for usernames
				echo '
				<script type="text/javascript" src="../jscripts/autocomplete.js?ver=1600"></script>
				<script type="text/javascript">
				<!--
					new autoComplete("username", "../xmlhttp.php?action=get_users", {valueSpan: "username"});
				// -->
				</script>';
				
				$buttons = array($form->generate_submit_button($lang->myshowcase_add_user_moderator));
				$form2->output_submit_wrapper($buttons);
				$form2->end();
			echo "</div>\n";

		}	

		$plugins->run_hooks("admin_myshowcase_edit_commit");

		$showcase_info = myshowcase_info();
		echo '<p /><small>'.$showcase_info['name'].' version '.$showcase_info['version'].' &copy; 2006-'.COPY_YEAR.' <a href="'.$showcase_info['website'].'">'.$showcase_info['author'].'</a>.</small>';
		$page->output_footer();
	}

	if($mybb->input['action'] == 'delete')
	{
		$page->output_header($lang->myshowcase_admin_edit_existing);

		$plugins->run_hooks("admin_myshowcase_delete_start");

		$query = $db->simple_select("myshowcase_config", "*", "id=".$mybb->input['id']);
		$num_myshowcases = $db->num_rows($query);
		if($num_myshowcases == 0)
		{
			flash_message($lang->myshowcase_edit_invalid_id, 'error');
			admin_redirect("index.php?module=myshowcase-summary");
		}
		else
		{
			$result = $db->fetch_array($query);
			echo $lang->sprintf($lang->myshowcase_edit_confirm_delete_long,$result['name']);
			$form = new Form("index.php?module=myshowcase-edit&amp;action=do_delete&amp;id=".$mybb->input['id'], "post", "do_delete");

			$buttons[] = $form->generate_submit_button($lang->myshowcase_edit_confirm_delete);
			$form->output_submit_wrapper($buttons);

			$form->end();

		}

		$plugins->run_hooks("admin_myshowcase_delete_commit");

		$showcase_info = myshowcase_info();
		echo '<p /><small>'.$showcase_info['name'].' version '.$showcase_info['version'].' &copy; 2006-'.COPY_YEAR.' <a href="'.$showcase_info['website'].'">'.$showcase_info['author'].'</a>.</small>';
		$page->output_footer();
	}

	if($mybb->input['action'] == 'do_delete')
	{

		$query = $db->simple_select("myshowcase_config", "*", "id=".$mybb->input['id']);
		$num_myshowcases = $db->num_rows($query);
		if($num_myshowcases == 0)
		{
			flash_message($lang->myshowcase_edit_invalid_id, 'error');
			admin_redirect("index.php?module=myshowcase-summary");
		}
		else
		{
			$query = $db->query("DELETE FROM ".TABLE_PREFIX."myshowcase_config WHERE id=".$mybb->input['id']);
			$num_myshowcases = $db->affected_rows($query);
			if($num_myshowcases == 0)
			{
				flash_message($lang->myshowcase_edit_delete_failed, 'error');
				admin_redirect("index.php?module=myshowcase-summary");
			}
			else
			{
				$query = $db->query("DELETE FROM ".TABLE_PREFIX."myshowcase_comments WHERE id=".$mybb->input['id']);
				$query = $db->query("DELETE FROM ".TABLE_PREFIX."myshowcase_attachments WHERE id=".$mybb->input['id']);
				$query = $db->query("DELETE FROM ".TABLE_PREFIX."myshowcase_permissions WHERE id=".$mybb->input['id']);
				$query = $db->query("DELETE FROM ".TABLE_PREFIX."myshowcase_moderators WHERE id=".$mybb->input['id']);

				if($db->table_exists('myshowcase_data'.$mybb->input['id']))
				{
					$query = $db->query("DROP TABLE ".TABLE_PREFIX."myshowcase_data".$mybb->input['id']);
				}

				myshowcase_update_cache('config');
				myshowcase_update_cache('permissions');

				flash_message($lang->myshowcase_edit_delete_success, 'success');
				admin_redirect("index.php?module=myshowcase-summary");
			}
		}

	}
}
else
{
	//no action or ID specified, force user to edit via list
	flash_message($lang->myshowcase_edit_missing_action, 'error');
	admin_redirect("index.php?module=myshowcase-summary");
}


?>
