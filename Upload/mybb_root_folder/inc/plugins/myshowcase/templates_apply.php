<?php
/**
 * MyShowcase Plugin for MyBB - Force application of latest default/base MyShowcase templates
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
				http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: /inc/plugins/myshowcase/templates_apply.php
 *
 */
 
define("IN_MYBB", 1);
require_once "../../../global.php";

if($mybb->user['uid'] == 0)
{
	die("You are not logged in.");
}

if($mybb->user['usergroup'] != 4)
{
	die("Only a primary administrator can run this file.");
}

echo "Starting template update...<br /><br />";

require_once "templates.php";

foreach($myshowcase_templates as $title => $template)
{
	$db->delete_query("templates", "title='".$title."' and sid=-2");
	$insert_array = array(
		'title' => $title,
		'template' => $db->escape_string($template),
		'sid' => -2,
		'version' => 0,
		'dateline' => TIME_NOW
	);
			
	$db->insert_query('templates', $insert_array);
	
	echo "Updated ".$title."<br />";
}

echo "<br /><br />Done with template update.";

echo "<br /><br />Please delete this file now!!!!!!!";

?>

 
