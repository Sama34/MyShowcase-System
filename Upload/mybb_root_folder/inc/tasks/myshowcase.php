<?php

/**
 * MyShowcase Plugin for MyBB - Task File
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\tasks\myshowcase.php
 *
 */
function task_myshowcase($task)
{
    global $mybb, $db, $cache;

    require_once(MYBB_ROOT . '/inc/class_myshowcase.php');

    //get showcases
    $showcases = $cache->read('myshowcase_config');
    foreach ($showcases as $id => $showcase) {
        //see if pruning is enabled
        $prunetime = explode('|', $showcase['prunetime']);
        if ($prunetime[0] > 0 && $showcase['enabled'] == 1) {
            //generate time using negative english dates
            $prunedateline = strtotime('-' . $prunetime[0] . ' ' . $prunetime[1], TIME_NOW);

            //create showcase object
            $me = new MyShowcaseSystem($showcase['mainfile']);

            //get showcases that are at least prune time old
            $query = $db->simple_select($me->table_name, 'gid', "dateline<='{$prunedateline}'");
            while ($result = $db->fetch_array($query)) {
                //and delete them
                $me->delete($result['gid']);
            }
            //clean up for next one
            unset($me);
        }
    }

    //log task
    add_task_log($task, 'Showcase Pruning Run');
}

?>
