<?php
/**
 * MyShowcase Plugin for MyBB - Frontend File
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
				http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: <showcase>.php (this file is renamed for multiple showcase versions)
 *
 */

/*
 * Only user edits required
*/

$forumdir = ""; //no trailing slash

/*
 * Stop editing
*/

define("IN_MYBB", 1);
define("IN_SHOWCASE", 1);
define("VERSION_OF_FILE", "2.5.2");

$filename = substr($_SERVER['SCRIPT_NAME'], -strpos(strrev($_SERVER['SCRIPT_NAME']), "/"));
define('THIS_SCRIPT', $filename);

$current_dir = getcwd();

//change working directory to allow board includes to work
$forumdirslash = ($forumdir == '' ? '' : $forumdir."/");
$change_dir = "./";

if(!@chdir($forumdir) && !empty($forumdir))
{
	if(@is_dir($forumdir))
	{
		$change_dir = $forumdir;
	}
	else
	{
		die("\$forumdir is invalid!");
	}
}
	
//setup templates
$templatelist = "myshowcase_list, myshowcase_list_empty, myshowcase_list_items, myshowcase_list_no_results, myshowcase_orderarrow, ";
$templatelist .= "myshowcase_view, myshowcase_view_attachments, myshowcase_view_comments, myshowcase_view_comments_add, myshowcase_view_comments_add_login, ";
$templatelist .= "myshowcase_view_comments_admin, myshowcase_view_comments_none, myshowcase_view_data, myshowcase_inlinemod_col, myshowcase_inlinemod, ";
$templatelist .= "myshowcase_orderarrow, myshowcase_list_custom_header, multipage_page_current, multipage_page, multipage_end, ";
$templatelist .= "multipage_nextpage, multipage, myshowcase_list_custom_fields, myshowcase_inlinemod_item, myshowcase_list_items, myshowcase_list, ";
$templatelist .= "myshowcase_top, myshowcase_new_button, myshowcase_field_date, myshowcase_js_header, ";
$templatelist .= "myshowcase_view_admin_edit, myshowcase_view_admin_delete, myshowcase_view_admin, myshowcase_table_header, myshowcase_view_data_1, myshowcase_view_data_2, myshowcase_view_data_3, myshowcase_view_attachments_image, ";
$templatelist .= "myshowcase_new_attachments_input, myshowcase_new_attachments, myshowcase_new_top, myshowcase_field_textbox, myshowcase_new_fields, myshowcase_field_db, myshowcase_field_textarea, myshowcase_new_bottom, ";

//get MyBB stuff
require_once $change_dir."/global.php";

//change directory back to current where script is
@chdir($current_dir);

//make sure this file is current
if(function_exists("myshowcase_info"))
{
	$system_info = myshowcase_info();
	if(VERSION_OF_FILE != $system_info['version'])
	{
		error("This file is not the same version as the MyShowcase System. Please be sure to upload and configure ALL files.");
	}
}

//adjust theme settings in case this file is outside mybb_root
global $theme, $templates;
$theme['imgdir'] = $forumdirslash.substr($theme['imgdir'],0);
$theme['imglangdir'] = $forumdirslash.substr($theme['imglangdir'],0);

//start by constructing the showcase
require_once(MYBB_ROOT.'/inc/class_myshowcase.php');
$me = new MyShowcaseSystem();

// Load global language phrases
//global $showcase_proper, $showcase_lower;

//try to load showcase specific language file
$lang->load("myshowcase".$me->id, false, true);

//if loaded then this will be set, if not load generic lang file
if($lang->myshowcase == '')
{
	$lang->load("myshowcase");
}

$lang->nav_myshowcase = $lang->myshowcase = $showcase_proper = ucwords(strtolower($me->name));
$showcase_lower = strtolower($me->name);

//check if this showcase is enabled
if(!$me->enabled)
{
	error($lang->myshowcase_disabled);
}

// Check if the active user is a moderator and get the inline moderation tools.
$mybb->input['unapproved'] = intval($mybb->input['unapproved']);
if($me->userperms['canmodapprove'])
{
	$list_where_clause = "(g.approved=0 or g.approved=1)";
	if($mybb->input['unapproved'] == 1)
	{
		$list_where_clause = "g.approved=0";
	}
	$inlinecount = 0;
	eval("\$showcase_inlinemod_col = \"".$templates->get("myshowcase_inlinemod_col")."\";");
	eval("\$showcase_inlinemod = \"".$templates->get("myshowcase_inlinemod")."\";");
}
else
{
	$ismod = false;
	$list_where_clause = "(g.approved=1 OR g.uid=".$mybb->user['uid'].")";
}

//handle image output here for performance reasons since we dont need fields and stuff
if($mybb->input['action'] == "item")
{
	$aid = intval($mybb->input['aid']);

	// Select attachment data from database
	if($aid)
	{
		$query = $db->simple_select("myshowcase_attachments", "*", "aid='{$aid}'");
	}

	$attachment = $db->fetch_array($query);

	// Error if attachment is invalid or not visible
	if(!$attachment['aid'] || !$attachment['attachname'] || (!$ismod && $attachment['visible'] != 1))
	{
		error($lang->error_invalidattachment);
	}

	if(!$me->allow_attachments || !$me->userperms['canviewattach'])
	{
		error_no_permission();
	}

	$ext = get_extension($attachment['filename']);

	switch($attachment['filetype'])
	{
		case "application/pdf":
		case "image/bmp":
		case "image/gif":
		case "image/jpeg":
		case "image/pjpeg":
		case "image/png":
		case "text/plain":
			header("Content-type: {$attachment['filetype']}");
			$disposition = "inline";
			break;

		default:
			header("Content-type: application/force-download");
			$disposition = "attachment";
			break;
	}

	if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "msie") !== false)
	{
		header("Content-disposition: attachment; filename=\"{$attachment['filename']}\"");
	}
	else
	{
		header("Content-disposition: {$disposition}; filename=\"{$attachment['filename']}\"");
	}
	
	if(strpos(strtolower($_SERVER['HTTP_USER_AGENT']), "msie 6.0") !== false)
	{
		header("Expires: -1");
	}
	
	header("Content-length: {$attachment['filesize']}");
	header("Content-range: bytes=0-".($attachment['filesize']-1)."/".$attachment['filesize']); 
		
	$plugins->run_hooks("myshowcase_image");

	echo file_get_contents($me->imgfolder."/".$attachment['attachname']);
	die();
}

//here for performance since we dont need the fields and other stuff
//this block is only used if user disables JS or if admin removes FancyBox code
if($mybb->input['action'] == "attachment")
{
	$aid = intval($mybb->input['aid']);

	// Select attachment data from database
	if($aid)
	{
		$query = $db->simple_select("myshowcase_attachments", "*", "aid='{$aid}'");
	}

	$attachment = $db->fetch_array($query);

	// Error if attachment is invalid or not visible
	if(!$attachment['aid'] || !$attachment['attachname'] || (!$ismod && $attachment['visible'] != 1))
	{
		error($lang->error_invalidattachment);
	}

	if(!$me->allow_attachments || !$me->userperms['canviewattach'])
	{
		error_no_permission();
	}

	$plugins->run_hooks("myshowcase_attachment_start");

	$db->update_query("myshowcase_attachments",array("downloads" => $attachment['downloads'] + 1),"aid='{$aid}'");

	if(stristr($attachment['filetype'], 'image/'))
	{
		$posterdata = get_user($attachment['uid']);

		$showcase_viewing_user = str_replace("{username}", $posterdata['username'], $lang->myshowcase_viewing_user);

		add_breadcrumb($showcase_viewing_user, str_replace('{gid}', $attachment['gid'], SHOWCASE_URL_VIEW));

		$attachment['filename'] = rawurlencode($attachment['filename']);

		$plugins->run_hooks("myshowcase_attachment_end");

		$showcase_viewing_attachment = str_replace("{username}", $posterdata['username'], $lang->myshowcase_viewing_attachment);
		add_breadcrumb($showcase_viewing_attachment, str_replace('{gid}', $attachment['gid'], SHOWCASE_URL_VIEW));

		$showcase_header_label = $showcase_viewing_attachment;

		$lasteditdate = my_date($mybb->settings['dateformat'], $attachment['dateuploaded']);
		$lastedittime = my_date($mybb->settings['timeformat'], $attachment['dateuploaded']);
		$item_lastedit = $lasteditdate."&nbsp;".$lastedittime;

		$showcase_attachment_description = $lang->myshowcase_attachment_filename.$attachment['filename'].'<br />'.$lang->myshowcase_attachment_uploaded.$item_lastedit;
		eval("\$showcase_table_header = \"".$templates->get("myshowcase_table_header")."\";");
		$showcase_attachment = str_replace('{aid}', $attachment['aid'], SHOWCASE_URL_ITEM);//$me->imgfolder."/".$attachment['attachname'];
		eval("\$showcase_page = \"".$templates->get("myshowcase_attachment_view")."\";");
			
		$plugins->run_hooks("myshowcase_attachment_end");
		output_page($showcase_page);
		die();
	}
	else //should never really be called, but just incase, support inline output
	{
		header("Cache-Control: private",false);
		header("Content-Type: ".$attachment['filetype']); 
   		header("Content-Description: File Transfer");
   		header('Content-Disposition: inline; filename='.$attachment['filename']);
   		header("Content-Length: ".$attachment['filesize']);
		header('Expires: 0');
	    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	    header('Pragma: public'); 
	    ob_clean();
   		flush();
   		readfile($me->imgfolder."/".$attachment['attachname']);
   		die();
	}
}
	
//need a few items from the index language file
$lang->load("index");

//load language file specific to this showcase's assigned fieldset
$lang->load("myshowcase_fs".$me->fieldsetid, false, true);

//see if current user can view this showcase
if(!$me->userperms['canview'])
{
	error_no_permission();
}

//init time
$dateline = time();

/* URL Definitions */

$URLStart = $mybb->settings['bburl'].'/';
if($forumdir != "" && $forumdir != ".")
{
	$URLStart = $mybb->settings['homeurl'].'/';
}

if($mybb->settings['seourls'] == "yes" || ($mybb->settings['seourls'] == "auto" && $_SERVER['SEO_SUPPORT'] == 1))
{
	$showcase_name = strtolower($me->name);
	define('SHOWCASE_URL', $URLStart.$me->clean_name.".html");
	define('SHOWCASE_URL_PAGED', $URLStart.$me->clean_name."-page-{page}.html");
	define('SHOWCASE_URL_VIEW', $URLStart.$me->clean_name."-view-{gid}.html");
	define('SHOWCASE_URL_NEW', $URLStart.$me->clean_name."-new.html");
	define('SHOWCASE_URL_VIEW_ATTACH', $URLStart.$me->clean_name."-attachment-{aid}.html");
	define('SHOWCASE_URL_ITEM', $URLStart.$me->clean_name."-item-{aid}.php");
	$amp = '?';
}
else
{
	define('SHOWCASE_URL', $URLStart.$me->prefix.".php");
	define('SHOWCASE_URL_PAGED', $URLStart.$me->prefix.".php?page={page}");
	define('SHOWCASE_URL_VIEW', $URLStart.$me->prefix.".php?action=view&gid={gid}");
	define('SHOWCASE_URL_NEW', $URLStart.$me->prefix.".php?action=new");
	define('SHOWCASE_URL_VIEW_ATTACH', $URLStart.$me->prefix.".php?action=attachment&aid={aid}");
	define('SHOWCASE_URL_ITEM', $URLStart.$me->prefix.".php?action=item&aid={aid}");
	$amp = '&amp;';
}

//make var for JS in template
$showcase_url = SHOWCASE_URL;

//add initial showcase breadcrumb
//$navbits = array();
add_breadcrumb($lang->nav_myshowcase, SHOWCASE_URL);

//process cancel button
if(isset($mybb->input['cancel']) && $mybb->request_method == "post")
{
	verify_post_check($mybb->input['my_post_key']);
	
	if(!$mybb->input['gid'])
	{
		require_once MYBB_ROOT."inc/functions_myshowcase_upload.php";
		myshowcase_remove_attachments(0, $mybb->input['posthash']);
	}

	if($mybb->input['action'] == "do_editshowcase" || $mybb->input['action'] == "do_newshowcase")
	{
		$mybb->input['action'] = "view";
	}
}

//get count of existing attachments if editing (posthash sent)
$current_attach_count = 0;
if($mybb->input['posthash'] != "" && $mybb->request_method == "post")
{
	verify_post_check($mybb->input['my_post_key']);

	$mybb->input['posthash'] = $db->escape_string($mybb->input['posthash']);
	$query = $db->simple_select("myshowcase_attachments", "*", "posthash = '".$mybb->input['posthash']."'");
	$current_attach_count = $db->num_rows($query);
	unset($query);
}

$plugins->run_hooks("myshowcase_start");

//process new/updated attachments
if(!$mybb->input['attachmentaid'] && ($mybb->input['newattachment'] || $mybb->input['updateattachment'] || (($mybb->input['action'] == "do_newshowcase" || $mybb->input['action'] == "do_editshowcase") &&	$mybb->input['submit'] && $_FILES['attachment'])) && $mybb->request_method == "post")
{
	verify_post_check($mybb->input['my_post_key']);

	$can_add_attachments = $me->userperms['canattach'];
	$attach_limit = $me->userperms['attachlimit'];
	$showcase_uid = $mybb->user['uid'];

	//if a mod is editing someone elses showcase, get orig authors perms
	if($mybb->input['action'] == "do_editshowcase" && $mybb->user['uid'] != $mybb->input['authid'])
	{
		//get showcase author info
		$showcase_uid = (int)$mybb->input['authid'];
		$showcase_user = get_user($showcase_uid);

		//get permissions for author
		$showcase_authorperms = $me->get_user_permissions($showcase_user);

		$can_add_attachments = $showcase_authorperms['canattach'];
		$attach_limit = $showcase_authorperms['attachlimit'];
	}

	// If there's an attachment, check it and upload it.
	if(($attach_limit == -1 || ($attach_limit != -1 && $current_attach_count < $attach_limit)) && $can_add_attachments)
	{
		if($_FILES['attachment']['size'] > 0)
		{
			if(!function_exists(myshowcase_upload_attachment))
			{
				require_once MYBB_ROOT."inc/functions_myshowcase_upload.php";
			}

			$update_attachment = false;
			if($mybb->input['updateattachment'])
			{
				$update_attachment = true;
			}
			$attachedfile = myshowcase_upload_attachment($_FILES['attachment'],$update_attachment,(int)$mybb->input['watermark']);
		}
		if($attachedfile['error'])
		{
			eval("\$attacherror = \"".$templates->get("error_attacherror")."\";");
			$mybb->input['action'] = "new";
		}
	}

	if(!$mybb->input['submit'])
	{
		if(isset($mybb->input['gid']) && $mybb->input['gid'] != '')
		{
			$mybb->input['action'] = "do_editshowcase";
		}
		else
		{
			$mybb->input['action'] = "do_newshowcase";
		}
	}
}

// Remove an attachment.
if($mybb->input['attachmentaid'] && $mybb->input['posthash'] && ($me->userperms['canedit'] || $me->userperms['canmodedit']) && $mybb->request_method == "post")
{
	verify_post_check($mybb->input['my_post_key']);

	require_once MYBB_ROOT."inc/functions_myshowcase_upload.php";
	myshowcase_remove_attachment(0, $mybb->input['posthash'], $mybb->input['attachmentaid']);
	if(!$mybb->input['submit'])
	{
		if(isset($mybb->input['gid']) && $mybb->input['gid'] != '')
		{
			$mybb->input['action'] = "do_editshowcase";
		}
		else
		{
			$mybb->input['action'] = "do_newshowcase";
		}
	}
}

//setup add comment
if(!$mybb->input['commentcid'] && $mybb->input['addcomment'] && $mybb->input['posthash'])
{
	$mybb->input['action'] = "addcomment";
}

//setup remove comment
if($mybb->input['commentcid'] && $mybb->input['remcomment'] && $mybb->input['posthash'])
{
	$mybb->input['action'] = "delcomment";
	$mybb->input['cid'] = intval($mybb->input['commentcid']);
}

//deal with admin buttons from view page
if($mybb->input['showcasegid'] && $mybb->input['posthash'])
{
	if($mybb->input['showcaseact'] == "remove")
	{
		$mybb->input['action'] = "delete";
	}

	if($mybb->input['showcaseact'] == "edit")
	{
		$mybb->input['action'] = "edit";
	}
}

//get this showcase's field info
$fieldcache = $cache->read('myshowcase_fields');
if(!is_array($fieldcache[$me->fieldsetid]))
{
	myshowcase_update_cache('fields');
	$fieldcache = $cache->read('myshowcase_fields');
}

//init dynamic field info
$showcase_fields = array();
$showcase_fields_enabled = array();
$showcase_fields_showinlist = array();
$showcase_fields_searchable = array();
$showcase_fields_parse = array();
$showcase_fields_max_length = array();
$showcase_fields_require = array();
$showcase_fields_require['uid'] = 1;
$showcase_fields_format = array();

foreach($fieldcache[$me->fieldsetid] as $field)
{
	$showcase_fields[$field['name']] = $field['html_type'];

	$showcase_fields_format[$field['name']] = $field['format'];

	$showcase_fields_max_length[$field['name']] = $field['max_length'];
	$showcase_fields_min_length[$field['name']] = $field['min_length'];

	//limit array only to those fields that are required
	if($field['enabled'] == 1 || $field['require'] == 1)
	{
		$showcase_fields_enabled[$field['name']] = $field['html_type'];
	}

	//limit array only to those fields that are required
	if($field['require'] == 1)
	{
		$showcase_fields_require[$field['name']] = 1;
	}
	else
	{
		$showcase_fields_require[$field['name']] = 0;
	}

	//limit array to those fields to show in the list of showcases
	if ($field['list_table_order'] != -1)
	{
		$showcase_fields_showinlist[$field['list_table_order']] = $field['name'];
	}

	//limit array to searchable fields
	if ($field['searchable'] == 1)
	{
		$showcase_fields_searchable[$field['field_order']] = $field['name'];
	}
	
	//limit array to searchable fields
	if ($field['parse'] == 1)
	{
		$showcase_fields_parse[$field['name']] = 1;
	}
	else
	{
		$showcase_fields_parse[$field['name']] = 0;
	}
}

//sort array of header fields by their list display order
ksort($showcase_fields_showinlist);

//sort array of searchable fields by their field order
ksort($showcase_fields_searchable);

//clean up/default expected inputs
if(!isset($mybb->input['action']))
{
	$mybb->input['action'] = "list";
}
$mybb->input['action'] = $db->escape_string($mybb->input['action']);


if(!isset($mybb->input['showall']) || $mybb->input['showall'] != 1)
{
	$mybb->input['showall'] = 0;
}

// Setup our posthash for managing attachments.
if(!$mybb->input['posthash'])
{
	mt_srand ((double) microtime() * 1000000);
	$mybb->input['posthash'] = md5(intval($mybb->input['gid']).$mybb->user['uid'].mt_rand());
}

//init form action
$form_page = $me->mainfile;

//get FancyBox JS for header if viewing
if($mybb->input['action'] == 'view')
{
	eval("\$myshowcase_js_header = \"".$templates->get("myshowcase_js_header")."\";");
}
eval("\$showcase_top = \"".$templates->get("myshowcase_top")."\";");

//main showcase code
switch($mybb->input['action'])
{
	case "list":
	{
		$showcase_url_new = SHOWCASE_URL_NEW;

		$plugins->run_hooks("myshowcase_list_start");

		if($me->userperms['canadd'])
		{
			eval("\$new_button = \"".$templates->get("myshowcase_new_button")."\";");
		}

		//init fixed fields for list view and insert the custom fields into the options list array
		$showcase_order_fields = array();
		$showcase_order_fields['createdate'] = $lang->myshowcase_sort_createdate;
		$showcase_order_fields['dateline'] = $lang->myshowcase_sort_editdate;
		$showcase_order_fields['username'] = $lang->myshowcase_sort_username;
		foreach($showcase_fields_showinlist as $forder => $fname)
		{
			eval("\$order_text = \"\$lang->myshowcase_field_".$fname."\";");
			$showcase_order_fields[$fname] = $order_text;
		}
		$showcase_order_fields['views'] = $lang->myshowcase_sort_views;
		$showcase_order_fields['comments'] = $lang->myshowcase_sort_comments;

		//init fixed fields for list view and insert the custom fields into the options list array
		$showcase_search_fields = array();
		$showcase_search_fields['username'] = $lang->myshowcase_sort_username;
		foreach($showcase_fields_searchable as $forder => $fname)
		{
			eval("\$order_text = \"\$lang->myshowcase_field_".$fname."\";");
			$showcase_search_fields[$fname] = $order_text;
		}

		//clean up inputs
		$mybb->input['searchterm'] = $db->escape_string($mybb->input['searchterm']);

		if(!isset($mybb->input['sortby']))
		{
			$mybb->input['sortby'] = "dateline";
		}
		$mybb->input['sortby'] = $db->escape_string($mybb->input['sortby']);

		// orderrow
		if(strpos(SHOWCASE_URL, '?'))
		{
			$sorturl = SHOWCASE_URL.$amp;
		}
		else
		{
			$sorturl = SHOWCASE_URL.'?';
		}

		$mybb->input['page'] = intval($mybb->input['page']);
		
		if($mybb->input['page'] && !strpos(SHOWCASE_URL, 'page='))
		{
			$sorturl .= "page=".$mybb->input['page'].$amp;
		}

		// Pick out some sorting options.
		// Pick the sort order.
		if(!isset($mybb->input['order']))
		{
			$mybb->input['order'] = "DESC";
		}

		$mybb->input['order'] = $db->escape_string($mybb->input['order']);

		switch(strtolower($mybb->input['order']))
		{
			case "asc":
				$sortordernow = "ASC";
				$orderascsel = "selected=\"selected\"";
				$oppsort = $lang->myshowcase_desc;
				$oppsortnext = "desc";
				break;
			default:
				$sortordernow = "DESC";
				$orderdescsel = "selected=\"selected\"";
				$oppsort = $lang->myshowcase_asc;
				$oppsortnext = "asc";
				break;
		}

		//make sure specified sortby is valid
		if(!array_key_exists($mybb->input['sortby'], $showcase_order_fields))
		{
			$mybb->input['sortby'] = "createdate";
		}

		//set sort field (required since test data does not have correct create date)
		if($mybb->input['sortby'] == 'createdate')
		{
			$sortfield = "`gid` ".$sortordernow;
		}
		else
		{
			$sortfield = "`".$mybb->input['sortby']."` ".$sortordernow.", gid ASC";
		}

		//build sortby option list
		$showcase_orderby = '';
		reset($showcase_order_fields);
		foreach($showcase_order_fields as $ordername => $ordertext)
		{
			$showcase_orderby .= '<option value="'.$ordername.'" '.($mybb->input['sortby'] == $ordername ? 'selected' : '').'>'.$ordertext.'</option>';
		}

		//build searchfield option list
		$showcase_search = '';
		reset($showcase_search_fields);
		foreach($showcase_search_fields as $ordername => $ordertext)
		{
			$showcase_search .= '<option value="'.$ordername.'" '.($mybb->input['search'] == $ordername ? 'selected' : '').'>'.$ordertext.'</option>';
		}

		//set alternate sort code
		$matchchecked = ($mybb->input['exactmatch'] == 'on' ? 'checked' : '');
		eval("\$orderarrow[\$mybb->input['sortby']] = \"".$templates->get("myshowcase_orderarrow")."\";");

		if($mybb->settings['seourls'] == "yes" || ($mybb->settings['seourls'] == "auto" && $_SERVER['SEO_SUPPORT'] == 1))
		{
			$amp = '?';
		}
		else
		{
			$amp = '&amp;';
		}

		//build custom list header based on field settings
		$showcase_list_custom_header = '';
		foreach($showcase_fields_showinlist as $forder => $fname)
		{
			eval("\$custom_header = \"\$lang->myshowcase_field_".$fname."\";");
			$custom_orderarrow = $orderarrow[$fname];
			eval("\$showcase_list_custom_header .= \"".$templates->get("myshowcase_list_custom_header")."\";");
		}

		//setup joins for query and build where clause based on search terms
		$showcase_fields_for_search = $showcase_fields;
		$addon_join = '';
		$addon_fields = '';
		$searchdone = 0;
		reset($showcase_fields_for_search);
		
		$mybb->input['searchterm'] = $db->escape_string($mybb->input['searchterm']);
		$mybb->input['search'] = $db->escape_string($mybb->input['search']);
		$mybb->input['exactmatch'] = $db->escape_string($mybb->input['exactmatch']);
		
		foreach($showcase_fields_for_search as $fname => $ftype)
		{
			if($ftype == 'db' || $ftype == 'radio')
			{
				$addon_join .= " LEFT JOIN ".TABLE_PREFIX."myshowcase_field_data tbl_".$fname." ON (tbl_".$fname.".valueid = g.".$fname." AND tbl_".$fname.".name = '".$fname."') ";
				$addon_fields .= ", tbl_".$fname.".value AS `".$fname."`";
				if($mybb->input['searchterm'] != '' && $mybb->input['search'] == $fname)
				{
					if($mybb->input['exactmatch'])
					{
						$list_where_clause .= " AND tbl_".$fname.".value ='".$mybb->input['searchterm']."'";
					}
					else
					{
						$list_where_clause .= " AND tbl_".$fname.".value LIKE '%".$mybb->input['searchterm']."%'";
					}
					$list_where_clause .= " AND tbl_".$fname.".setid = ".$me->fieldsetid;
				}
			}
			elseif($mybb->input['search'] == "username" && !$searchdone)
			{
				$addon_join .= " LEFT JOIN ".TABLE_PREFIX."users us ON (g.uid = us.uid) ";
				$addon_fields .= ", `".$fname."`";
				if($mybb->input['searchterm'] != '')
				{
					if($mybb->input['exactmatch'])
					{
						$list_where_clause .= " AND us.username='".$mybb->input['searchterm']."'";
					}
					else
					{
						$list_where_clause .= " AND us.username LIKE '%".$mybb->input['searchterm']."%'";
					}
				}
				$searchdone = 1;
			}			
			else
			{
				$addon_fields .= ", `".$fname."`";
				if($mybb->input['searchterm'] != '' && $mybb->input['search'] == $fname)
				{
					if($mybb->input['exactmatch'])
					{
						$list_where_clause .= " AND g.".$fname."='".$mybb->input['searchterm']."'";
					}
					else
					{
						$list_where_clause .= " AND g.".$fname." LIKE '%".$mybb->input['searchterm']."%'";
					}
				}
			}
		}

		// How many entries are there?
		$showcasecount = 0;

        //$query = $db->simple_select($me->table_name.' g', "gid", $list_where_clause);
        $query = $db->query("SELECT count(*) AS total FROM ".TABLE_PREFIX.$me->table_name." g ".$addon_join." WHERE ".$list_where_clause);

        $result = $db->fetch_array($query);
		$showcasecount = $result['total'];
		
		if($showcasecount != 0)
		{

			// How many pages are there?
			$perpage = $mybb->settings['threadsperpage'];

			if($mybb->input['page'] > 0)
			{
				$page = $mybb->input['page'];
				$start = ($page-1) *$perpage;
				$pages = $showcasecount / $perpage;
				$pages = ceil($pages);
				if($page > $pages)
				{
					$start = 0;
					$page = 1;
				}
			}
			else
			{
				$start = 0;
				$page = 1;
			}
			$end = $start + $perpage;
			$lower = $start + 1;
			$upper = $end;
			if($upper > $showcasecount)
			{
				$upper = $showcasecount;
			}

			$multipage = multipage($showcasecount, $perpage, $page, SHOWCASE_URL_PAGED.$amp."sortby=".$mybb->input['sortby']."&amp;order=".$sortordernow.($mybb->input['unapproved'] ? "&amp;unapproved=".$mybb->input['unapproved'] : "").($mybb->input['search'] <> '' ? "&amp;search=".$mybb->input['search'] : "").($mybb->input['search'] <> '' ? "&amp;searchterm=".$mybb->input['searchterm'] : ""));

			$trow_style = "trow2";

			
			// start getting showcases
			$query = $db->query("
				SELECT `gid`, u.username, `views`, `comments`, `dateline`, `createdate`, `approved`, `approved_by`, `posthash`, g.uid".$addon_fields."
				FROM ".TABLE_PREFIX.$me->table_name." g
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = g.uid)
				".$addon_join."
				WHERE $list_where_clause
				ORDER BY $sortfield
				LIMIT $start, $perpage
				");

			// get first attachment for each showcase on this page
			$showcase_images = array();
			if($me->use_attach == 1)
			{
			
				$gidlist = "";
				while($results = $db->fetch_array($query))
				{
					if($gidlist == "")
					{
						$gidlist = $results['gid'];
					}
					else
					{
						$gidlist = $gidlist.','.$results['gid'];
					}
				}			

				$attquery = $db->query("SELECT gid, min(aid) as aid, filetype, filename, attachname, thumbnail FROM ".TABLE_PREFIX."myshowcase_attachments WHERE id=".$me->id." AND gid IN (".$gidlist.") AND visible=1 GROUP BY gid");
					
				while($results = $db->fetch_array($attquery))
				{
					$showcase_images[$results['gid']]['aid'] = $results['aid'];
					$showcase_images[$results['gid']]['attachname'] = $results['attachname'];
					$showcase_images[$results['gid']]['thumbnail'] = $results['thumbnail'];
					$showcase_images[$results['gid']]['filetype'] = $results['filetype'];
					$showcase_images[$results['gid']]['filename'] = $results['filename'];
				}	
			}

			//reset results since we may have iterated for attachments.
			$db->data_seek($query,0);
			
			unset($showcase_items);

			$trow_style = "trow2";
			while($showcase = $db->fetch_array($query))
			{
				//obtain fixed field items
				$item_uid = $showcase['uid'];
				$item_view = $showcase['gid'];
				$item_member = $showcase['username'];
				$item_numview = $showcase['views'];
				$item_numcomment = $showcase['comments'];

				$usersearch = $mybb->input['searchterm'];

				$lasteditdate = my_date($mybb->settings['dateformat'], $showcase['dateline']);
				$lastedittime = my_date($mybb->settings['timeformat'], $showcase['dateline']);
				$item_lastedit = $lasteditdate."<br />".$lastedittime;

				if($showcase['username'] == '')
				{
					$showcase['username'] = $lang->guest;
					$showcase['uid'] = 0;
				}

				$item_member = build_profile_link($showcase['username'], $showcase['uid'], '','', $forumdir.'/');

				$showcase_view_user = str_replace("{username}", $showcase['username'], $lang->myshowcase_view_user);

				$item_viewcode = str_replace('{gid}', $item_view, SHOWCASE_URL_VIEW);

				//add bits for search highlighting
				if($mybb->input['searchterm'] != '')
				{
					$item_viewcode .= '?search='.$mybb->input['search'].'&highlight='.urlencode($mybb->input['searchterm']);
				}
				
                //build link for list view, starting with basic text
                $item_viewimage = $lang->myshowcase_view;

                //use default image is specified
                if($me->defaultimage != '' && (@file_exists($theme['imgdir'].'/'.$me->defaultimage) || stristr($theme['imgdir'], 'http://')))
                {
                    $item_viewimage = '<img src="'.$theme['imgdir'].'/'.$me->defaultimage.'" border="0" alt="'.$showcase_view_user.'">';
                }

                //use showcase attachment if one exists, scaled of course
				if($me->use_attach)
				{
					if(stristr($showcase_images[$showcase['gid']]['filetype'], 'image/'))
					{
				        if($showcase_images[$showcase['gid']]['aid'] && @file_exists($me->imgfolder.'/'.$showcase_images[$showcase['gid']]['attachname']))
				        {
				            if($showcase_images[$showcase['gid']]['thumbnail'] == "SMALL")
				            {
				                $item_viewimage = '<img src="'.$me->imgfolder.'/'.$showcase_images[$showcase['gid']]['attachname'].'" width="50" border="0" alt="'.$showcase_view_user.'">';
				            }
				            else
				            {
				                $item_viewimage = '<img src="'.$me->imgfolder.'/'.$showcase_images[$showcase['gid']]['thumbnail'].'" width="50" border="0" alt="'.$showcase_view_user.'">';
				            }
				        }
					}
					else
					{
						$attachtypes = $cache->read('attachtypes');
						$ext = get_extension($showcase_images[$showcase['gid']]['filename']);
						if(array_key_exists($ext, $attachtypes))
						{
							$item_image = $mybb->settings['bburl']."/".$attachtypes[$ext]['icon'];
							$item_viewimage = '<img src="'.$item_image.'" border="0" alt="'.$showcase_view_user.'">';
						}
					}
				}

				//set row style for default use
				$trow_style = ($trow_style == "trow1" ? "trow2" : "trow1");

				//change style is unapproved
				if($showcase['approved'] != 1)
				{
					$trow_style = "trow_shaded";
				}

				//build custom list items based on field settings
				$showcase_list_custom_fields = '';
				foreach($showcase_fields_showinlist as $forder => $fname)
				{
					$item_text = $showcase[$fname];
					
					//format numbers as requested
					switch($showcase_fields_format[$fname])
					{
						case 'no':
							$item_text = $item_text;
						break;
						
						case 'decimal0':
							$item_text = number_format($item_text);
						break;

						case 'decimal1':
							$item_text = number_format(floatval($item_text), 1);
						break;
						
						case 'decimal2':
							$item_text = number_format(floatval($item_text), 2);
						break;
					}
	
					switch($showcase_fields[$fname])
					{
						case 'date':
							if($item_text == 0)
							{
								$item_text = '';
							}
							else
							{
								$date_bits = explode('|',$item_text);
								$date_bits = array_map('intval', $date_bits);
								if($date_bits[0] > 0 && $date_bits[1] > 0 && $date_bits[2] > 0)
								{
									$item_text = my_date($mybb->settings['dateformat'], mktime(0, 0, 0, $date_bits[0], $date_bits[1], $date_bits[2]));
								}
								else
								{
									$item_text = '';
									if($date_bits[0]) $item_text .= $date_bits[0];
									if($date_bits[1]) $item_text .= ($item_text != '' ? '-' : '').$date_bits[1];
									if($date_bits[2]) $item_text .= ($item_text != '' ? '-' : '').$date_bits[2];
								}
							}
						break;
					}
					
					$item_text = htmlspecialchars_uni($item_text);
					eval("\$showcase_list_custom_fields .= \"".$templates->get("myshowcase_list_custom_fields")."\";");
				}
				if($me->userperms['canmodapprove'] || $me->userperms['canmoddelete'])
				{
					$multigid = $showcase['gid'];
					eval("\$showcase_inlinemod_item = \"".$templates->get("myshowcase_inlinemod_item")."\";");
				}

				eval("\$showcase_list_items .= \"".$templates->get("myshowcase_list_items")."\";");

				//add row indicating report
				if(is_array($reports[$showcase['gid']]))
				{
					foreach($reports[$showcase['gid']] as $rid => $report)
					{
						$reportdate = my_date($mybb->settings['dateformat'], $report['dateline']);
						$reporttime = my_date($mybb->settings['timeformat'], $report['dateline']);
						
						$reporter = get_user($report['uid']);
						$reporter_link = build_profile_link($reporter['username'], $reporter['uid'], '','', $forumdir.'/');
						
						$trow_style = "red_alert";
						$message = '<img src="'.$mybb->settings['bburl'].'/images/nav_bit.gif" alt="Above">  ';
						$message .= $lang->sprintf($lang->myshowcase_report_item, $reportdate." ".$reporttime, $reporter_link, $report['reason']);
						$showcase_num_headers = ($showcase_inlinemod_item ? 6 : 5) + count($showcase_fields_showinlist);
						eval("\$showcase_list_items .= \"".$templates->get("myshowcase_list_message")."\";");
					}
				}
			}
		}
		else
		{
			$trow_style = "trow1";
			$colcount = 5;
			if($me->userperms['canmodapprove'] || $me->userperms['canmoddelete'])
			{
				$colcount = 6;
			}
			$showcase_num_headers = $colcount + count($showcase_fields_showinlist);
			if($mybb->input['searchterm'] == "")
			{
				$message = $lang->myshowcase_empty;
				eval("\$showcase_list_items .= \"".$templates->get("myshowcase_list_message")."\";");
			}
			else
			{
				$message = $lang->myshowcase_no_results;
				eval("\$showcase_list_items .= \"".$templates->get("myshowcase_list_message")."\";");
			}
		}

		$plugins->run_hooks("myshowcase_list_end");

		eval("\$showcase_page = \"".$templates->get("myshowcase_list")."\";");

	break;
	}
	case "view":
	{
		$mybb->input['gid'] = intval($mybb->input['gid']);

		$plugins->run_hooks("myshowcase_view_start");

		if($mybb->input['gid'] == "" || $mybb->input['gid'] == 0)
		{
			error($lang->myshowcase_invalid_id);
		}

		$addon_join = '';
		$addon_fields = '';
		reset($showcase_fields);
		foreach($showcase_fields_enabled as $fname => $ftype)
		{
			if($ftype == 'db' || $ftype == 'radio')
			{
				$addon_join .= " LEFT JOIN ".TABLE_PREFIX."myshowcase_field_data tbl_".$fname." ON (tbl_".$fname.".valueid = g.".$fname." AND tbl_".$fname.".name = '".$fname."') ";
				$addon_fields .= ", tbl_".$fname.".value AS `".$fname."`";
				$view_where_clause .= " AND tbl_".$fname.".setid = ".$me->fieldsetid;
			}
			else
			{
				$addon_fields .= ", `".$fname."`";
			}
		}
 		// start getting showcase base data
		$query = $db->query("
			SELECT `gid`, g.uid, `username`, `views`, `comments`, `dateline`, `approved`, `approved_by`, `posthash`".$addon_fields."
			FROM ".TABLE_PREFIX.$me->table_name." g
			LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = g.uid)
			".$addon_join."
			WHERE g.gid=".$mybb->input['gid'].$view_where_clause
			);

		if($db->num_rows($query) == 0)
		{
			error($lang->myshowcase_invalid_id);
		}

		$showcase = $db->fetch_array($query);

		if($showcase['username'] == '')
		{
			$showcase['username'] = $lang->guest;
			$showcase['uid'] = 0;
		}

		$showcase_viewing_user = str_replace("{username}", $showcase['username'], $lang->myshowcase_viewing_user);
		add_breadcrumb($showcase_viewing_user, SHOWCASE_URL);

		//set up jump to links
		$jumpto = $lang->myshowcase_jumpto;

		$item_viewcode = str_replace('{gid}', $mybb->input['gid'], SHOWCASE_URL_VIEW);
		if($me->allow_attachments && $me->userperms['canviewattach'])
		{
			$jumpto .= ' <a href="'.$item_viewcode.($mybb->input['showall'] == 1 ? '&showall=1' : '').'#images">'.$lang->myshowcase_attachments.'</a>';
		}

		if($me->allow_comments && $me->userperms['canviewcomment'])
		{
			$jumpto .= ' <a href="'.$item_viewcode.($mybb->input['showall'] == 1 ? '&showall=1' : '').'#comments">'.$lang->myshowcase_comments.'</a>';
		}

		$jumptop = '(<a href="'.$item_viewcode.($mybb->input['showall'] == 1 ? '&showall=1' : '').'#top">'.$lang->myshowcase_top.'</a>)';

		$posthash = $showcase['posthash'];

		$showcase_gid = $mybb->input['gid'];
		$showcase_views = $showcase['views'];
		$showcase_numcomments = $showcase['comments'];

		$showcase_header_label = $lang->myshowcase_specifications;
		$showcase_header_jumpto = $jumpto;

		$showcase_admin_url = SHOWCASE_URL;

		if($me->userperms['canmodedit'] || ($showcase['uid'] == $mybb->user['uid'] && $me->userperms['canedit']))
		{
			eval("\$showcase_view_admin_edit = \"".$templates->get("myshowcase_view_admin_edit")."\";");
		}

		if($me->userperms['canmoddelete'] || ($showcase['uid'] == $mybb->user['uid'] && $me->userperms['canedit']))
		{
			eval("\$showcase_view_admin_delete = \"".$templates->get("myshowcase_view_admin_delete")."\";");
		}

		if($showcase_view_admin_edit != '' || $showcase_view_admin_delete != '')
		{
			eval("\$showcase_header_special = \"".$templates->get("myshowcase_view_admin")."\";");
		}

		eval("\$showcase_data_header = \"".$templates->get("myshowcase_table_header")."\";");

		//trick MyBB into thinking its in archive so it adds bburl to smile link inside parser
		//doing this now should not impact anyhting. no issues with gomobile beta4
	 	define("IN_ARCHIVE", 1);
	 	
	 	$mybb->input['highlight'] = $db->escape_string($mybb->input['highlight']);
	 	$mybb->input['search'] = $db->escape_string($mybb->input['search']);

		require_once(MYBB_ROOT.'inc/class_parser.php');
		$parser = new postParser;

		reset($showcase_fields_enabled);
		$trow_style = "trow2";
		foreach($showcase_fields_enabled as $fname => $ftype)
		{
			$temp = 'myshowcase_field_'.$fname;
			$field_header = $lang->$temp;

			$trow_style = ($trow_style == "trow1" ? "trow2" : "trow1");

			//if we have search handle search term highlighting
			$highlight = 0;
			if($mybb->input['highlight'] != '' && $mybb->input['search'] == $fname)
			{
				$highlight = $mybb->input['highlight'];
			}
			
			//set parser options for current field				 	
			$parser_options = array('filter_badwords' => 1,
									'allow_html' => $me->allowhtml,
									'allow_mycode' => $me->allowbbcode,
									'me_username' => 0,
									'allow_smilies' => $me->allowsmilies,
									'highlight' => $highlight,
									'nl2br' => 1);

			switch($ftype)
			{
				case "textarea":
					$field_data = $showcase[$fname];
					if($field_data != '' || $me->disp_empty == 1)
					{
						if($showcase_fields_parse[$fname] || $highlight)
						{
							$field_data = $parser->parse_message($field_data,$parser_options);
						}
						else
						{
							$field_data = htmlspecialchars_uni($field_data);
							$field_data = nl2br($field_data);
						}
						eval("\$showcase_data .= \"".$templates->get("myshowcase_view_data_2")."\";");
					}
				break;

				case "textbox":
					$field_data = $showcase[$fname];

					//format numbers as requested
					switch($showcase_fields_format[$fname])
					{
						case 'no':
							$field_data = $field_data;
						break;
						
						case 'decimal0':
							$field_data = number_format(floatval($field_data));
						break;

						case 'decimal1':
							$field_data = number_format(floatval($field_data), 1);
						break;
						
						case 'decimal2':
							$field_data = number_format(floatval($field_data), 2);
						break;
					}
					
					if($field_data != '' || $me->disp_empty == 1)
					{
						$field_data = htmlspecialchars_uni($field_data);
						if($showcase_fields_parse[$fname] || $highlight)
						{
							$field_data = $parser->parse_message($field_data,$parser_options);
						}
						eval("\$showcase_data .= \"".$templates->get("myshowcase_view_data_1")."\";");
					}
				break;

				case "url":
					$field_data = $showcase[$fname];
					if($field_data != '' || $me->disp_empty == 1)
					{
						$field_data = $parser->mycode_parse_url($showcase[$fname]);
						eval("\$showcase_data .= \"".$templates->get("myshowcase_view_data_2")."\";");
					}
				break;

				case "date":
					$field_data = $showcase[$fname];
					if($field_data != '')
					{
						$date_bits = explode('|',$showcase[$fname]);
						$date_bits = array_map('intval', $date_bits);

						if($date_bits[0] > 0 && $date_bits[1] > 0 && $date_bits[2] > 0)
						{
							$field_data = my_date($mybb->settings['dateformat'], mktime(0, 0, 0, $date_bits[0], $date_bits[1], $date_bits[2]));
						}
						else
						{
							$field_data = '';
							if($date_bits[0]) $field_data .= $date_bits[0];
							if($date_bits[1]) $field_data .= ($field_data != '' ? '-' : '').$date_bits[1];
							if($date_bits[2]) $field_data .= ($field_data != '' ? '-' : '').$date_bits[2];
						}
					}
					else
					{
						$field_data = '';
					}
					if(($field_data != '') || $me->disp_empty == 1)
					{
						eval("\$showcase_data .= \"".$templates->get("myshowcase_view_data_1")."\";");
					}
				break;

				case "db":
					$field_data = $showcase[$fname];
					if(($field_data != '') || $me->disp_empty == 1)
					{
						if($showcase_fields_parse[$fname] || $highlight)
						{
							$field_data = $parser->parse_message($field_data,$parser_options);
						}
						eval("\$showcase_data .= \"".$templates->get("myshowcase_view_data_1")."\";");
					}
				break;

				case "radio":
					$field_data = $showcase[$fname];
					if(($field_data != '') || $me->disp_empty == 1)
					{
						eval("\$showcase_data .= \"".$templates->get("myshowcase_view_data_1")."\";");
					}
				break;

				case "checkbox":
					if(($showcase[$fname] != '') || $me->disp_empty == 1)
					{
						if($showcase[$fname] == 1)
						{
							$field_data = '<img src="'.$mybb->settings['bburl'].'/images/valid.gif" alt="Yes">';
						}
						else
						{
							$field_data = '<img src="'.$mybb->settings['bburl'].'/images/invalid.gif" alt="No">';
						}
						eval("\$showcase_data .= \"".$templates->get("myshowcase_view_data_1")."\";");
					}
				break;
			}
		}

		//for moderators, show who last approved the entry
		if($me->userperms['canmodapprove'] && $showcase['approved'])
		{
			$field_header = $lang->myshowcase_last_approved;
			$modapproved = get_user($showcase['approved_by']);
			$field_data = build_profile_link($modapproved['username'], $modapproved['uid'], '','', $forumdir.'/');
			eval("\$showcase_data .= \"".$templates->get("myshowcase_view_data_1")."\";");
		}

		//output bottom row for report button and future add-ons
//		$entry_final_row = '<a href="'.SHOWCASE_URL.'?action=report&gid='.$mybb->input['gid'].'"><img src="'.$theme['imglangdir'].'/postbit_report.gif"></a>';
		$entry_final_row = '<a href="javascript:Showcase.reportShowcase('.$mybb->input['gid'].');"><img src="'.$theme['imglangdir'].'/postbit_report.gif"></a>';
		eval("\$showcase_data .= \"".$templates->get("myshowcase_view_data_3")."\";");

		if($me->allow_comments && $me->userperms['canviewcomment'])
		{
			// start getting comments
			$query = $db->query("
				SELECT gc.*, u.username
				FROM ".TABLE_PREFIX."myshowcase_comments gc
				LEFT JOIN ".TABLE_PREFIX."users u ON (u.uid = gc.uid)
				WHERE gc.gid=".$mybb->input['gid']." AND gc.id=".$me->id."
				ORDER BY dateline DESC".
				($mybb->input['showall'] == 1 ? '' : " LIMIT ".$me->comment_dispinit)
				);

			$trow_style = "trow2";
			$showcase_comments = '';
			while($gcomments = $db->fetch_array($query))
			{

				$trow_style = ($trow_style == "trow1" ? "trow2" : "trow1");

				//clean up comment and timestamp
				$comment_date = my_date($mybb->settings['dateformat'], $gcomments['dateline']);
				$comment_time = my_date($mybb->settings['timeformat'], $gcomments['dateline']);
				$comment_posted = $comment_date." ".$comment_time;
				$comment_poster = $item_member = build_profile_link($gcomments['username'], $gcomments['uid'], '','', $forumdir.'/');
				$comment_data = $parser->parse_message($gcomments['comment'],$parser_options);
			
				//setup comment admin options
				//only mods, original author (if allowed) or owner (if allowed) can delete comments
				if(
				($me->userperms['canmoddelcomment']) ||
				($gcomments['uid'] == $mybb->user['uid'] && $me->userperms['candelowncomment']) ||
				($showcase['uid'] == $mybb->user['uid'] && $me->userperms['candelauthcomment'])
				)
				{
					eval("\$showcase_comments_admin = \"".$templates->get("myshowcase_view_comments_admin")."\";");
				}

				eval("\$showcase_comments .= \"".$templates->get("myshowcase_view_comments")."\";");
			}

			$showcase_show_all = '';
			if($mybb->input['showall'] != 1 && $showcase_numcomments > $me->comment_dispinit)
			{
				$showcase_show_all = '(<a href="'.$item_viewcode.$amp.'showall=1#comments">'.str_replace("{count}", $showcase['comments'], $lang->myshowcase_comment_show_all).'</a>)'."<br>";
			}

			$showcase_comment_form_url = SHOWCASE_URL;//.'?action=view&gid='.$mybb->input['gid'];
			$showcase_header_label = '<a name="comments"><form action="'.$showcase_comment_form_url.'" method="post" name="comment">'.$lang->myshowcase_comments.'</a>';
			$showcase_header_jumpto = $jumptop;
			$showcase_header_special = $showcase_show_all;
			eval("\$showcase_comment_header = \"".$templates->get("myshowcase_table_header")."\";");

			$trow_style = ($trow_style == "trow1" ? "trow2" : "trow1");
			if($showcase_comments == '')
			{
				eval("\$showcase_comments = \"".$templates->get("myshowcase_view_comments_none")."\";");
			}

			//check if logged in for ability to add comments
			$trow_style = ($trow_style == "trow1" ? "trow2" : "trow1");
			if(!$mybb->user['uid'])
			{
				eval("\$showcase_comments .= \"".$templates->get("myshowcase_view_comments_add_login")."\";");
			}
			elseif($me->userperms['cancomment'])
			{
				$comment_text_limit = str_replace("{text_limit}", $me->comment_length, $lang->myshowcase_comment_text_limit);
				eval("\$showcase_comments .= \"".$templates->get("myshowcase_view_comments_add")."\";");
			}
		}

		if($me->allow_attachments && $me->userperms['canviewattach'])
		{
			// start getting attachments
			$query = $db->query("
				SELECT ga.*
				FROM ".TABLE_PREFIX."myshowcase_attachments ga
				WHERE ga.gid=".$mybb->input['gid']." AND ga.id=".$me->id
				);

			$attach_count = 0;
			$showcase_attachment_data = '';
			while($gattachments = $db->fetch_array($query))
			{
				//setup default and non-JS enabled URLs
				$item_attachurljs = str_replace('{aid}', $gattachments['aid'], SHOWCASE_URL_ITEM);
				$item_attachurl = str_replace('{aid}', $gattachments['aid'], SHOWCASE_URL_VIEW_ATTACH);

				//if mime is image
				if(stristr($gattachments['filetype'], 'image/'))
				{	
					//determine what image to use for thumbnail
					if($gattachments['thumbnail'] != "SMALL" && file_exists($me->imgfolder."/".$gattachments['thumbnail']))
					{
						$item_image = './'.$me->imgfolder."/".$gattachments['thumbnail'];
					}
					else
					{
						$item_image = $item_attachurljs;
					}

					//see if the Fancybox code is being used and if not go back to the actual attachment for the link url
					if(stripos($showcase_top, "[rel=showcase_images]"))
					{
						$item_class = "rel=\\\"showcase_images\\\"";
					}
					else
					{
						$item_attachurljs = $item_attachurl;
					}

				}
				else //it's any other allowed type, so use this
				{
					$item_class = "class=\\\"attachment\\\"";
					$attachtypes = $cache->read('attachtypes');
					$ext = get_extension($gattachments['filename']);
					$item_image = $theme['imgdir']."/error.gif";
					if(array_key_exists($ext, $attachtypes))
					{
						$item_image = $mybb->settings['bburl']."/".$attachtypes[$ext]['icon'];
					}
				}
				
				$item_alt = $lang->sprintf($lang->myshowcase_attachment_alt, $gattachments['filename'], $showcase['username']);

				eval("\$showcase_attachment_data .= \"".$templates->get("myshowcase_view_attachments_image")."\";");

				$attach_count++;
				if($attach_count == $me->disp_attachcols && $me->disp_attachcols != 0)
				{
					$showcase_attachment_data .= '<br />';
					$attach_count = 0;
				}
				else
				{
					$showcase_attachment_data .= '&nbsp;';
				}
			}

			if(substr($showcase_attachment_data,-6) == '&nbsp;')
			{
				$showcase_attachment_data = substr($showcase_attachment_data,0,-6);
			}

			$showcase_header_label = '<a name="images">'.$lang->myshowcase_attachments.'</a>';
			$showcase_header_jumpto = $jumptop;
			$showcase_header_special = "";
			eval("\$showcase_attachment_header = \"".$templates->get("myshowcase_table_header")."\";");


 			if($showcase_attachment_data != '')
			{
				eval("\$showcase_attachments = \"".$templates->get("myshowcase_view_attachments")."\";");
			}
			else
			{
				eval("\$showcase_attachments = \"".$templates->get("myshowcase_view_attachments_none")."\";");
			}
		}

		// Update view count
		$db->shutdown_query("UPDATE ".TABLE_PREFIX.$me->table_name." SET views=views+1 WHERE gid=".$mybb->input['gid']);

		$plugins->run_hooks("myshowcase_view_end");

		eval("\$showcase_page = \"".$templates->get("myshowcase_view")."\";");

	break;
	}
	
	case "report":
	case "do_report":
	case "reports":
	case "do_reports":
	case "allreports":
	{
		require_once(MYBB_ROOT.'inc/plugins/myshowcase/report.php');
	}
	break;
	
	case "addcomment":
	{
		if(!$mybb->user['uid'])
		{
			error($lang->myshowcase_comments_not_logged_in);
		}

		if($me->userperms['cancomment'] && $mybb->request_method == "post")
		{
			verify_post_check($mybb->input['my_post_key']);
			
			$plugins->run_hooks("myshowcase_add_comment_start");

			$mybb->input['gid'] = intval($mybb->input['gid']);

			if($mybb->input['gid'] == "" || $mybb->input['gid'] == 0)
			{
				error($lang->myshowcase_invalid_id);
			}

			if($mybb->input['comments'] == "")
			{
				error($lang->myshowcase_comment_empty);
			}

			$query = $db->simple_select($me->table_name, "gid, uid", "gid=".$mybb->input['gid']);
			if($db->num_rows($query) == 0)
			{
				error($lang->myshowcase_invalid_id);
			}
			
			$authorid = $db->fetch_field($query, "uid");

			//don't trust the myshowcase_data comment count, get the real count at time of insert to cover deletions and edits at same time.
			$query = $db->query("
				SELECT COUNT(*) AS num_comments
				FROM ".TABLE_PREFIX."myshowcase_comments
				WHERE gid = ".$mybb->input['gid']." AND id=".$me->id."
				GROUP BY gid
				LIMIT 1
			");

			$showcase = $db->fetch_array($query);
			$num_comments = $showcase['num_comments'];

			$mybb->input['comments'] = $db->escape_string($mybb->input['comments']);

			if($mybb->input['comments'] != '')
			{
				$comment_insert_data = array(
					"id" => $me->id,
					"gid" => $mybb->input['gid'],
					"uid" => $mybb->user['uid'],
					"ipaddress" => get_ip(),
					"comment" => $mybb->input['comments'],
					"dateline" => $dateline
					);

				$plugins->run_hooks("myshowcase_add_comment_commit");

				$db->insert_query("myshowcase_comments", $comment_insert_data);

				$db->update_query($me->table_name, array("comments" => $num_comments + 1),"gid=".$mybb->input['gid']);

				//notify showcase owner of new comment by others
				$author = get_user($authorid);
				if($author['allownotices'] && $author['uid'] != $mybb->user['uid'])
				{
					require_once MYBB_ROOT.'inc/class_parser.php';
					$parser = new Postparser;

					$excerpt = $parser->text_parse_message($mybb->input['comments'], array('me_username' => $mybb->user['username'], 'filter_badwords' => 1, 'safe_html' => 1));
					$excerpt = my_substr($excerpt, 0, $mybb->settings['subscribeexcerpt']).$lang->myshowcase_comment_more;
					
					$item_viewcode = str_replace('{gid}', $mybb->input['gid'], SHOWCASE_URL_VIEW);
					
					if($forumdir == '' || $forumdir == './')
					{
						$showcase_url = $mybb->settings['bburl'].'/'.$item_viewcode;
					}
					else
					{
						$forumdir = str_replace('.', '', $forumdir);
						$showcase_url = str_replace($forumdir, '', $mybb->settings['bburl']).'/'.$item_viewcode;
					}
					
					
					$emailsubject = $lang->sprintf($lang->myshowcase_comment_emailsubject, $me->name);
					$emailmessage = $lang->sprintf($lang->myshowcase_comment_email, $author['username'], $mybb->user['username'], $me->name, $excerpt, $showcase_url, $mybb->settings['bbname'], $mybb->settings['bburl']);
					
					$new_email = array(
						"mailto" => $db->escape_string($author['email']),
						"mailfrom" => '',
						"subject" => $db->escape_string($emailsubject),
						"message" => $db->escape_string($emailmessage),
						"headers" => ''
						);

					$db->insert_query("mailqueue", $new_email);
					$cache->update_mailqueue();
				}
						
				$item_viewcode = str_replace('{gid}', $mybb->input['gid'], SHOWCASE_URL_VIEW);

				redirect($item_viewcode.'#comments', $lang->myshowcase_comment_added);
			}
		}
		else
		{
			error($lang->myshowcase_not_authorized);
		}


	break;
	}
	case "delcomment":
	{
		$plugins->run_hooks("myshowcase_del_comment_start");

		$mybb->input['cid'] = intval($mybb->input['cid']);

		if($mybb->input['cid'] == "" || $mybb->input['cid'] == 0)
		{
			error($lang->myshowcase_invalid_cid);
		}

		$query = $db->query("SELECT c.cid, g.uid AS owner, c.uid AS author, c.gid FROM ".TABLE_PREFIX."myshowcase_comments c
				LEFT JOIN ".TABLE_PREFIX.$me->table_name." g
				ON g.gid = c.gid
				WHERE c.cid = ".$mybb->input['cid']);

		if($db->num_rows($query) == 0)
		{
			error($lang->myshowcase_invalid_cid);
		}

		$gcomments = $db->fetch_array($query);

		if(
			(($mybb->user['uid'] == $gcomments['author'] && $me->userperms['candelowncomment']) ||
			($mybb->user['uid'] == $gcomments['owner'] && $me->userperms['candelauthcomment']) ||
			($me->userperms['canmoddelcomment']) && $mybb->request_method == "post")
		)
		{
			verify_post_check($mybb->input['my_post_key']);
		
			$query = $db->delete_query("myshowcase_comments", "id=".$me->id." AND cid=".$mybb->input['cid']);
			if($db->affected_rows($query) != 1)
			{
				error($lang->myshowcase_comment_error);
			}
			else
			{
				$query = $db->query("
					SELECT COUNT(*) AS num_comments
					FROM ".TABLE_PREFIX."myshowcase_comments
					WHERE gid = ".$gcomments['gid']." AND id=".$me->id."
					GROUP BY gid
					LIMIT 1
				");

				$showcase = $db->fetch_array($query);
				$num_comments = $showcase['num_comments'];

				$plugins->run_hooks("myshowcase_del_comment_commit");

				$db->update_query($me->table_name, array("comments" => $num_comments),"gid=".$gcomments['gid']);

				$item_viewcode = str_replace('{gid}', $gcomments['gid'], SHOWCASE_URL_VIEW);

				redirect($item_viewcode.'#comments', $lang->myshowcase_comment_deleted);
			}
		}
		else
		{
			error($lang->myshowcase_not_authorized);
		}

	break;
	}
	case "delete";
	{
		if($mybb->request_method == "post")
		{
			verify_post_check($mybb->input['my_post_key']);

			if(!$mybb->user['uid'] || !$me->userperms['canedit'])
			{
				error($lang->myshowcase_not_authorized);
			}

			$plugins->run_hooks("myshowcase_delete_start");

			$mybb->input['gid'] = intval($mybb->input['gid']);

			if($mybb->input['gid'] == "" || $mybb->input['gid'] == 0)
			{
				error($lang->myshowcase_invalid_id);
			}

			$query = $db->simple_select($me->table_name, "*", "gid=".$mybb->input['gid']);
			if($db->num_rows($query) == 0)
			{
				error($lang->myshowcase_invalid_id);
			}

			$showcase_data = $db->fetch_array($query);

			if(!$me->userperms['canmoddelete'] && $mybb->user['uid'] != $showcase_data['uid'])
			{
				error($lang->myshowcase_not_authorized);
			}

			$gid = $showcase_data['gid'];
			$me->delete($gid);

			//log_moderator_action($modlogdata, $lang->multi_deleted_threads);

			$plugins->run_hooks("myshowcase_delete_end");
		}
		redirect(SHOWCASE_URL, $lang->redirect_myshowcase_delete);
		exit;
	break;
	}

	case "edit":
	case "new":
	case "do_editshowcase":
	case "do_newshowcase":
	{
		require_once(MYBB_ROOT.'inc/plugins/myshowcase/newedit.php');
	}
	break;

	case "multiapprove":
	case "multiunapprove":
	case "multidelete":
	case "do_multidelete":
	{
		require_once(MYBB_ROOT.'inc/plugins/myshowcase/inlinemod.php');
	}
	break;

}


$plugins->run_hooks("myshowcase_end");
output_page($showcase_page);


//query to get templates

/*
SELECT title, template, -2 as sid , 1600 as version , status, unix_timestamp() as dateline FROM `myforum_templates` WHERE tid in (SELECT distinct max(tid) as tid FROM `myforum_templates` WHERE title like '%showcase%'  group by title order by title, dateline desc)
*/
?>
