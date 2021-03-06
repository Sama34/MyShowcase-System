<?php
/**
 * MyShowcase Plugin for MyBB - Code for Inline Moderation
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
				http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\plugins\myshowcase\inlinemod.php 
 *
 */

switch($mybb->input['action'])
{
	case "multiapprove";
	{
	} //no break since the code is the same except for the value being assigned
	case "multiunapprove";
	{
		//verify if moderator and coming in from a click
		if(!$me->userperms['canmodapprove'] && $mybb->input['modtype'] != "inlineshowcase")
		{
			error($lang->myshowcase_not_authorized);
		}

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		$gids = $me->getids('all', 'showcase');
		array_map('intval', $gids);
		
		if(count($gids) < 1)
		{
			error($lang->myshowcase_no_showcaseselected);
		}

		$query = $db->query("
			UPDATE ".TABLE_PREFIX.$me->table_name."
			SET approved = ".($mybb->input['action'] == "multiapprove" ? 1 : 0).", approved_by = ".$mybb->user['uid']."
			WHERE gid IN (".implode(",", $gids).")
			");

		$modlogdata = array(
			'id'=>$me->id,
			'gids'=>implode(",", $gids)
			);
		log_moderator_action($modlogdata, ($mybb->input['action'] == "multiapprove" ? $lang->myshowcase_mod_approve : $lang->myshowcase_mod_unapprove));

		$me->clearinline('all', 'showcase');

		//build URL to get back to where mod action happened
		$mybb->input['sortby'] = $db->escape_string($mybb->input['sortby']);
		if($mybb->input['sortby'] != '')
		{
			$url_params[] = 'sortby='.$mybb->input['sortby'];
		}

		$mybb->input['order'] = $db->escape_string($mybb->input['order']);
		if($mybb->input['order'] != '')
		{
			$url_params[] = 'order='.$mybb->input['order'];
		}

		$mybb->input['page'] = intval($mybb->input['page']);
		if($mybb->input['page'] != '')
		{
			$url_params[] = 'page='.$mybb->input['page'];
		}

		$url = SHOWCASE_URL.(count($url_params) > 0 ? '?'.implode("&amp;", $url_params) : '');

		$redirtext = ($mybb->input['action'] == "multiapprove" ? $lang->redirect_myshowcase_approve : $lang->redirect_myshowcase_unapprove);
		redirect($url, $redirtext);
		exit;
	break;
	}

	case "multidelete";
	{
		add_breadcrumb($lang->myshowcase_nav_multidelete);

		if(!$me->userperms['canmoddelete'] && $mybb->input['modtype'] != "inlineshowcase")
		{
			error($lang->myshowcase_not_authorized);
		}

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		$gids = $me->getids('all', 'showcase');

		if(count($gids) < 1)
		{
			error($lang->myshowcase_no_myshowcaseselected);
		}

		$inlineids = implode("|", $gids);

		$me->clearinline('all', 'showcase');

		//build URl to get back to where mod action happened
		$mybb->input['sortby'] = $db->escape_string($mybb->input['sortby']);
		if($mybb->input['sortby'] != '')
		{
			$url_params[] = 'sortby='.$mybb->input['sortby'];
		}

		$mybb->input['order'] = $db->escape_string($mybb->input['order']);
		if($mybb->input['order'] != '')
		{
			$url_params[] = 'order='.$mybb->input['order'];
		}

		$mybb->input['page'] = intval($mybb->input['page']);
		if($mybb->input['page'] != '')
		{
			$url_params[] = 'page='.$mybb->input['page'];
		}

		$return_url = SHOWCASE_URL.(count($url_params) > 0 ? '?'.implode("&amp;", $url_params) : '');
		//$return_url = htmlspecialchars_uni($mybb->input['url']);
		eval("\$multidelete = \"".$templates->get("myshowcase_inline_deleteshowcases")."\";");
		output_page($multidelete);
	break;
	}
	case "do_multidelete";
	{
		if(!$me->userperms['canmoddelete'])
		{
			error($lang->myshowcase_not_authorized);
		}

		// Verify incoming POST request
		verify_post_check($mybb->input['my_post_key']);

		$gids = explode("|", $mybb->input['showcases']);

		foreach($gids as $gid)
		{
			$gid = intval($gid);
			$me->delete($gid);
			$glist[] = $gid;
		}

		//log_moderator_action($modlogdata, $lang->multi_deleted_threads);

		$me->clearinline('all', 'showcase');

		//build URl to get back to where mod action happened
		$mybb->input['sortby'] = $db->escape_string($mybb->input['sortby']);
		if($mybb->input['sortby'] != '')
		{
			$url_params[] = 'sortby='.$mybb->input['sortby'];
		}

		$mybb->input['order'] = $db->escape_string($mybb->input['order']);
		if($mybb->input['order'] != '')
		{
			$url_params[] = 'order='.$mybb->input['order'];
		}

		$mybb->input['page'] = intval($mybb->input['page']);
		if($mybb->input['page'] != '')
		{
			$url_params[] = 'page='.$mybb->input['page'];
		}

		$url = SHOWCASE_URL.(count($url_params) > 0 ? '?'.implode("&amp;", $url_params) : '');

		redirect($url, $lang->redirect_myshowcase_delete);
		exit;
	break;
	}
}

?>
