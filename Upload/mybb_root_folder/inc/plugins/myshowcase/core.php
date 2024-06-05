<?php
/**
 * MyShowcase Plugin for MyBB - Main Plugin Controls
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\plugins\myshowcase\plugin.php
 *
 */

namespace MyShowcase\Core;

use MybbStuff_MyAlerts_AlertManager;
use MybbStuff_MyAlerts_AlertTypeManager;
use MybbStuff_MyAlerts_Entity_Alert;
use PMDataHandler;

use function MyShowcase\Admin\_info;

function load_language()
{
    global $lang;

    isset($lang->mysupport) || $lang->load(defined('IN_ADMINCP') ? 'config_mysupport' : 'mysupport');
}

function load_pluginlibrary($check = true)
{
    global $PL, $lang;

    load_language();

    if ($file_exists = file_exists(PLUGINLIBRARY)) {
        global $PL;

        $PL or require_once PLUGINLIBRARY;
    }

    if (!$check) {
        return;
    }

    $_info = _info();

    if (!$file_exists || $PL->version < $_info['pl']['version']) {
        flash_message(
            $lang->sprintf($lang->mysupport_pluginlibrary, $_info['pl']['url'], $_info['pl']['version']),
            'error'
        );

        admin_redirect('index.php?module=config-plugins');
    }
}

function addHooks(string $namespace)
{
    global $plugins;

    $namespaceLowercase = strtolower($namespace);
    $definedUserFunctions = get_defined_functions()['user'];

    foreach ($definedUserFunctions as $callable) {
        $namespaceWithPrefixLength = strlen($namespaceLowercase) + 1;

        if (substr($callable, 0, $namespaceWithPrefixLength) == $namespaceLowercase . '\\') {
            $hookName = substr_replace($callable, null, 0, $namespaceWithPrefixLength);

            $priority = substr($callable, -2);

            if (is_numeric(substr($hookName, -2))) {
                $hookName = substr($hookName, 0, -2);
            } else {
                $priority = 10;
            }

            $plugins->add_hook($hookName, $callable, $priority);
        }
    }
}

// Send a Private Message to a user  (Copied from MyBB 1.7)
function send_pm($pm, $fromid = 0, $admin_override = false)
{
    global $mybb;

    if (!$mybb->settings['mysupport_notifications'] || !$mybb->settings['enablepms'] || !is_array($pm)) {
        return false;
    }

    if (!$pm['subject'] || !$pm['message'] || (!$pm['receivepms'] && !$admin_override)) {
        return false;
    }

    global $lang, $db, $session;

    $lang->load((defined('IN_ADMINCP') ? '../' : '') . 'messages');

    static $pmhandler = null;

    if ($pmhandler === null) {
        require_once MYBB_ROOT . 'inc/datahandlers/pm.php';

        $pmhandler = new PMDataHandler();
    }

    // Build our final PM array
    $pm = array(
        'subject' => $pm['subject'],
        'message' => $pm['message'],
        'icon' => -1,
        'fromid' => ($fromid == 0 ? (int)$mybb->user['uid'] : ($fromid < 0 ? 0 : $fromid)),
        'toid' => array($pm['touid']),
        'bccid' => array(),
        'do' => '',
        'pmid' => '',
        'saveasdraft' => 0,
        'options' => array(
            'signature' => 0,
            'disablesmilies' => 0,
            'savecopy' => 0,
            'readreceipt' => 0
        )
    );

    if (isset($mybb->session)) {
        $pm['ipaddress'] = $mybb->session->packedip;
    }

    // Admin override
    $pmhandler->admin_override = (int)$admin_override;

    $pmhandler->set_data($pm);

    if ($pmhandler->validate_pm()) {
        $pmhandler->insert_pm();

        return true;
    }

    return false;
}

function send_alert($tid, $uid, $author = 0)
{
    global $lang, $mybb, $alertType, $db;

    load_language();

    if (!($mybb->settings['mysupport_notifications'] && class_exists('MybbStuff_MyAlerts_AlertTypeManager'))) {
        return false;
    }

    $alertType = MybbStuff_MyAlerts_AlertTypeManager::getInstance()->getByCode('mysupport_thread');

    if (!$alertType) {
        return false;
    }

    $query = $db->simple_select(
        'alerts',
        'id',
        "object_id='{$tid}' AND uid='{$uid}' AND unread=1 AND alert_type_id='{$alertType->getId()}'"
    );

    if ($db->fetch_field($query, 'id')) {
        return false;
    }

    if ($alertType->getEnabled()) {
        $alert = new MybbStuff_MyAlerts_Entity_Alert();

        $alert->setType($alertType)->setUserId($uid)->setExtraDetails([
            'type' => $alertType->getId()
        ]);

        if ($tid) {
            $alert->setObjectId($tid);
        }

        if ($author) {
            $alert->setFromUserId($author);
        }

        MybbStuff_MyAlerts_AlertManager::getInstance()->addAlert($alert);
    }
}

function _cache($what = '')
{
    global $db, $cache;

    $old_cache = $cache->read('mysupport');
    $new_cache = array();

    if ($what == 'version' || !$what) {
        $new_cache['version'] = MYSUPPORT_VERSION;
    } else {
        $new_cache['version'] = $old_cache['version'];
    }

    if ($what == 'priorities' || !$what) {
        $query = $db->simple_select('mysupport', 'mid, name, description, extra', "type = 'priority'");
        $new_cache['priorities'] = array();
        while ($priority = $db->fetch_array($query)) {
            $new_cache['priorities'][$priority['mid']] = $priority;
        }
    } else {
        $new_cache['priorities'] = $old_cache['priorities'];
    }

    if ($what == 'deniedreasons' || !$what) {
        $query = $db->simple_select('mysupport', 'mid, name, description', "type = 'deniedreason'");
        $new_cache['deniedreasons'] = array();
        while ($deniedreason = $db->fetch_array($query)) {
            $new_cache['deniedreasons'][$deniedreason['mid']] = $deniedreason;
        }
    } else {
        $new_cache['deniedreasons'] = $old_cache['deniedreasons'];
    }

    $cache->update('mysupport', $new_cache);
}

/**
 * Get the count of technical or assigned threads.
 *
 * @param int The FID we're in.
 * @return int The number of technical or assigned threads in this forum.
 **/
function _get_count($type, $fid = 0)
{
    global $mybb, $db, $cache;

    $fid = intval($fid);
    $mysupport_forums = implode(',', array_map('intval', _forums()));

    $count = 0;
    $forums = $cache->read('forums');
    if ($type == 'technical') {
        // there's no FID given so this is loading the total number of technical threads
        if ($fid == 0) {
            foreach ($forums as $forum => $info) {
                $count += $info['technicalthreads'];
            }
        } // we have an FID, so count the number of technical threads in this specific forum and all it's parents
        else {
            $forums_list = array();
            foreach ($forums as $forum => $info) {
                $parentlist = $info['parentlist'];
                if (strpos(',' . $parentlist . ',', ',' . $fid . ',') !== false) {
                    $forums_list[] = $forum;
                }
            }
            foreach ($forums_list as $forum) {
                $count += $forums[$forum]['technicalthreads'];
            }
        }
    } elseif ($type == 'assigned') {
        $assigned = unserialize($mybb->user['assignedthreads']);
        if (!is_array($assigned)) {
            return 0;
        }
        // there's no FID given so this is loading the total number of assigned threads
        if ($fid == 0) {
            foreach ($assigned as $fid => $threads) {
                $count += $threads;
            }
        } // we have an FID, so count the number of assigned threads in this specific forum
        else {
            $forums_list = array();
            foreach ($forums as $forum => $info) {
                $parentlist = $info['parentlist'];
                if (strpos(',' . $parentlist . ',', ',' . $fid . ',') !== false) {
                    $forums_list[] = $forum;
                }
            }
            foreach ($forums_list as $forum) {
                $count += $assigned[$forum];
            }
        }
    }

    return $count;
}

/**
 * Generates a list of all forums that have MySupport enabled.
 *
 * @param array Array of forums that have MySupport enabled.
 **/
function _forums()
{
    global $cache;

    $forums = $cache->read('forums');
    $mysupport_forums = array();

    foreach ($forums as $forum) {
        // if this forum/category has MySupport enabled, add it to the array
        if ($forum['mysupport'] == 1) {
            if (!in_array($forum['fid'], $mysupport_forums)) {
                $mysupport_forums[] = $forum['fid'];
            }
        } // if this forum/category hasn't got MySupport enabled...
        else {
            // ... go through the parent list...
            $parentlist = explode(',', $forum['parentlist']);
            foreach ($parentlist as $parent) {
                // ... if this parent has MySupport enabled...
                if ($forums[$parent]['mysupport'] == 1) {
                    // ... add the original forum we're looking at to the list
                    if (!in_array($forum['fid'], $mysupport_forums)) {
                        $mysupport_forums[] = $forum['fid'];
                        continue;
                    }
                    // this is for if we enable MySupport for a whole category; this will pick up all the forums inside that category and add them to the array
                }
            }
        }
    }

    return $mysupport_forums;
}

/**
 * Get the text version of the status of a thread.
 *
 * @param int The status of the thread.
 * @param string The text version of the status of the thread.
 **/
function _get_friendly_status($status = 0)
{
    global $lang;

    $lang->load('mysupport');

    $status = intval($status);
    switch ($status) {
        // has it been marked as not techincal?
        case 4:
            $friendlystatus = $lang->not_technical;
            break;
        // is it a technical thread?
        case 2:
            $friendlystatus = $lang->technical;
            break;
        // no, is it a solved thread?
        case 3:
        case 1:
            $friendlystatus = $lang->solved;
            break;
        // must be not solved then
        default:
            $friendlystatus = $lang->not_solved;
    }

    return $friendlystatus;
}

/**
 * Show the status of a thread.
 *
 * @param int The status of the thread.
 * @param int The time the thread was solved.
 * @param int The TID of the thread.
 **/
function _get_display_status($status, $onhold = 0, $statustime = 0, $thread_author = 0)
{
    global $mybb, $lang, $templates, $theme, $mysupport_status, $thread;

    $thread_author = intval($thread_author);

    // if this user is logged in, we want to override the global setting for display with their own setting
    if ($mybb->user['uid'] != 0 && $mybb->settings['mysupport_displaytypeuserchange']) {
        if ($mybb->user['mysupportdisplayastext'] == 1) {
            $mybb->settings['mysupport_displaytype'] = 'text';
        } else {
            $mybb->settings['mysupport_displaytype'] = 'image';
        }
    }

    // big check to see if either the status is to be show to everybody, only to people who can mark as solved, or to people who can mark as solved or who authored the thread
    if ($mybb->settings['mysupport_displayto'] == 'all' || ($mybb->settings['mysupport_displayto'] == 'canmas' && mysupport_usergroup(
                'canmarksolved'
            )) || ($mybb->settings['mysupport_displayto'] == 'canmasauthor' && (mysupport_usergroup(
                    'canmarksolved'
                ) || $mybb->user['uid'] == $thread_author))) {
        $text = $mybb->settings['mysupport_displaytype'] == 'text';

        if ($mybb->settings['mysupport_relativetime']) {
            $date_time = my_date('relative', $statustime);

            if (!$text) {
                $date_time = strip_tags($date_time);
            }

            $status_title = htmlspecialchars_uni($lang->sprintf($lang->technical_time, $date_time_technical));
        } else {
            $date_time = my_date('normal', intval($statustime));
        }

        // if this user cannot mark a thread as technical and people who can't mark as technical can't see that a technical thread is technical, don't execute this
        // I used the word technical 4 times in that sentence didn't I? sorry about that
        if ($status == 2 && !($mybb->settings['mysupport_hidetechnical'] || ($mybb->usergroup['canseetechnotice'] || is_moderator(
                        $thread['fid'],
                        'canmarktechnical'
                    )))) {
            $status_class = $status_img = 'technical';
            $status_title = htmlspecialchars_uni($lang->sprintf($lang->technical_time, $date_time));

            if ($text) {
                $status_text = $lang->technical;
            }
        } elseif ($status == 1) {
            $status_class = $status_img = 'solved';
            $status_text = $lang->solved;
            $status_title = htmlspecialchars_uni($lang->sprintf($lang->solved_time, $date_time));

            if ($text) {
                $status_text = $lang->solved;
            }
        } else {
            $status_class = $status_img = 'notsolved';
            $status_text = $status_title = $lang->not_solved;
        }

        if ($onhold == 1) {
            $status_class = $status_img = 'onhold';
            $status_text = $lang->onhold;
            $status_title = $lang->onhold . ' - ' . $status_title;
        }

        if ($text) {
            $mysupport_status = eval($templates->render('mysupport_status_text'));
        } else {
            $mysupport_status = eval($templates->render('mysupport_status_image'));
        }

        return $mysupport_status;
    }
}

function get_usergroup_permissions($uid, $user = [])
{
    if (empty($user)) {
        $user = get_user($uid);
    }

    if (empty($user['uid'])) {
        $usergroup = [];
    } else {
        $usergroup = usergroup_permissions(
            !$user['additionalgroups'] ? $user['usergroup'] : $user['usergroup'] . ',' . $user['additionalgroups']
        );

        if ($user['displaygroup']) {
            $mydisplaygroup = usergroup_displaygroup($user['displaygroup']);

            if (is_array($mydisplaygroup)) {
                $usergroup = array_merge($usergroup, $mydisplaygroup);
            }
        }
    }

    return $usergroup;
}

/**
 * Check if a points system is enabled for points system integration.
 *
 * @return bool Whether or not your chosen points system is enabled.
 **/
function _points_system_enabled()
{
    global $mybb, $cache;

    $plugins = $cache->read('plugins');

    if ($mybb->settings['mysupport_pointssystem'] != 'none') {
        if ($mybb->settings['mysupport_pointssystem'] == 'other') {
            $mybb->settings['mysupportpointssystem'] = $mybb->settings['mysupportpointssystemname'];
        }

        return in_array($mybb->settings['mysupport_pointssystem'], $plugins['active']);
    }

    return false;
}

/**
 * Change the status of a thread.
 *
 * @param array Information about the thread.
 * @param int The new status.
 * @param bool If this is changing the status of multiple threads.
 **/
function _change_status($thread_info, $status = 0, $multiple = false)
{
    global $mybb, $db, $lang, $cache;

    $status = intval($status);
    if ($status == 3) {
        // if it's 3, we're solving and closing, but we'll just check for regular solving in the list of things to log
        // saves needing to have a 3, for the solving and closing option, in the setting of what to log
        // then below it'll check if 1 is in the list of things to log; 1 is normal solving, so if that's in the list, it'll log this too
        $log_status = 1;
    } else {
        $log_status = $status;
    }

    if ($multiple) {
        $tid = -1;
        $old_status = -1;
    } else {
        $tid = intval($thread_info['tid']);
        $old_status = intval($thread_info['status']);
    }

    $move_fid = '';
    /*
    $forums = $cache->read("forums");
    foreach($forums as $forum)
    {
        if(!empty($forum['mysupportmove']) && $forum['mysupportmove'] != 0)
        {
            $move_fid = intval($forum['fid']);
            break;
        }
    }
    */
    // are we marking it as solved and is it being moved?
    if (!empty($move_fid) && ($status == 1 || $status == 3)) {
        if ($mybb->settings['mysupport_moveredirect'] == 'none') {
            $move_type = 'move';
            $redirect_time = 0;
        } else {
            $move_type = 'redirect';
            if ($mybb->settings['mysupport_moveredirect'] == 'forever') {
                $redirect_time = 0;
            } else {
                $redirect_time = intval($mybb->settings['mysupport_moveredirect']);
            }
        }
        if ($multiple) {
            $move_tids = $thread_info;
        } else {
            $move_tids = array($thread_info['tid']);
        }
        require_once MYBB_ROOT . 'inc/class_moderation.php';
        $moderation = new Moderation();
        // the reason it loops through using move_thread is because move_threads doesn't give the option for a redirect
        // if it's not a multiple thread it will just loop through once as there'd only be one value in the array
        foreach ($move_tids as $move_tid) {
            $moderation->move_thread($move_tid, $move_fid, $move_type, $redirect_time);
        }
    }

    if ($multiple) {
        $tids = implode(',', array_map('intval', $thread_info));
        $where_sql = 'tid IN (' . $db->escape_string($tids) . ')';
    } else {
        $where_sql = "tid = '" . intval($tid) . "'";
    }

    // we need to build an array of users who have been assigned threads before the assignment is removed
    if ($status == 1 || $status == 3) {
        $query = $db->simple_select('threads', 'DISTINCT assign', $where_sql . " AND assign != '0'");
        $assign_users = array();
        while ($user = $db->fetch_field($query, 'assign')) {
            $assign_users[] = $user;
        }
    }

    if ($status == 3 || ($status == 1 && $mybb->settings['mysupport_closewhensolved'] == 'always')) {
        // the bit after || here is for if we're marking as solved via marking a post as the best answer, it will close if it's set to always close
        // the incoming status would be 1 but we need to close it if necessary
        $status_update = array(
            'closed' => 1,
            'status' => 1,
            'statusuid' => intval($mybb->user['uid']),
            'statustime' => TIME_NOW,
            'assign' => 0,
            'assignuid' => 0,
            'priority' => 0,
            'closedbymysupport' => 1,
            'onhold' => 0
        );
    } elseif ($status == 0) {
        // if we're marking it as unsolved, a post may have been marked as the best answer when it was originally solved, best remove it, as well as rest everything else
        $status_update = array(
            'status' => 0,
            'statusuid' => 0,
            'statustime' => 0,
            'bestanswer' => 0
        );
    } elseif ($status == 4) {
        /** if it's 4, it's because it was marked as being not technical after being marked technical
         ** basically put back to the original status of not solved (0)
         ** however it needs to be 4 so we can differentiate between this action (technical => not technical), and a user marking it as not solved
         ** because both of these options eventually set it back to 0
         ** so the mod log entry will say the correct action as the status was 4 and it used that
         ** now that the log has been inserted we can set it to 0 again for the thread update query so it's marked as unsolved **/
        $status_update = array(
            'status' => 0,
            'statusuid' => 0,
            'statustime' => 0
        );
    } elseif ($status == 2) {
        $status_update = array(
            'status' => 2,
            'statusuid' => intval($mybb->user['uid']),
            'statustime' => TIME_NOW
        );
    } // if not, it's being marked as solved
    else {
        $status_update = array(
            'status' => 1,
            'statusuid' => intval($mybb->user['uid']),
            'statustime' => TIME_NOW,
            'assign' => 0,
            'assignuid' => 0,
            'priority' => 0,
            'onhold' => 0
        );
    }

    $db->update_query('threads', $status_update, $where_sql);

    // if the thread is being marked as technical, being marked as something else after being marked technical, or we're changing the status of multiple threads, recount the number of technical threads
    if ($status == 2 || $old_status == 2 || $multiple) {
        mysupport_recount_technical_threads();
    }
    // if the thread is being marked as solved, recount the number of assigned threads for any users who were assigned threads that are now being marked as solved
    if ($status == 1 || $status == 3) {
        foreach ($assign_users as $user) {
            mysupport_recount_assigned_threads($user);
        }
    }
    if ($status == 0) {
        // if we're marking a thread(s) as unsolved, re-open any threads that were closed when they were marked as solved, but not any that were closed by denying support
        $update = array(
            'closed' => 0,
            'closedbymysupport' => 0
        );
        $db->update_query('threads', $update, $where_sql . " AND closed = '1' AND closedbymysupport = '1'");
    }

    // get the friendly version of the status for the redirect message and mod log
    $friendly_old_status = "'" . _get_friendly_status($old_status) . "'";
    $friendly_new_status = "'" . _get_friendly_status($status) . "'";

    if ($multiple) {
        mysupport_mod_log_action(
            $log_status,
            $lang->sprintf($lang->status_change_mod_log_multi, count($thread_info), $friendly_new_status)
        );
        mysupport_redirect_message(
            $lang->sprintf(
                $lang->status_change_success_multi,
                count($thread_info),
                htmlspecialchars_uni($friendly_new_status)
            )
        );
    } else {
        mysupport_mod_log_action($log_status, $lang->sprintf($lang->status_change_mod_log, $friendly_new_status));
        mysupport_redirect_message(
            $lang->sprintf(
                $lang->status_change_success,
                htmlspecialchars_uni($friendly_old_status),
                htmlspecialchars_uni($friendly_new_status)
            )
        );
    }
}

// loads the dropdown menu for inline thread moderation
function inline_thread_moderation()
{
    global $mybb, $db, $cache, $lang, $templates, $foruminfo, $mysupport_inline_thread_moderation;

    $lang->load('mysupport');

    $mysupport_solved = $mysupport_not_solved = $mysupport_solved_and_close = $mysupport_technical = $mysupport_not_technical = '';
    if (is_moderator($foruminfo['fid'], 'canmarksolved')) {
        $mysupport_solved = "<option value=\"mysupport_status_1\">-- " . $lang->solved . '</option>';
        $mysupport_not_solved = "<option value=\"mysupport_status_0\">-- " . $lang->not_solved . '</option>';
        if ($mybb->settings['mysupport_closewhensolved'] != 'never') {
            $mysupport_solved_and_close = "<option value=\"mysupport_status_3\">-- " . $lang->solved_close . '</option>';
        }
    }
    if ($mybb->settings['mysupport_enabletechnical']) {
        if (is_moderator($foruminfo['fid'], 'canmarktechnical')) {
            $mysupport_technical = "<option value=\"mysupport_status_2\">-- " . $lang->technical . '</option>';
            $mysupport_not_technical = "<option value=\"mysupport_status_4\">-- " . $lang->not_technical . '</option>';
        }
    }

    $mysupport_onhold = $mysupport_offhold = '';
    if ($mybb->settings['mysupport_enableonhold']) {
        if (is_moderator($foruminfo['fid'], 'canmarksolved')) {
            $mysupport_onhold = "<option value=\"mysupport_onhold_1\">-- " . $lang->hold_status_onhold . '</option>';
            $mysupport_offhold = "<option value=\"mysupport_onhold_0\">-- " . $lang->hold_status_offhold . '</option>';
        }
    }

    if ($mybb->settings['mysupport_enableassign']) {
        $mysupport_assign = '';
        $assign_users = get_assign_users();
        // only continue if there's one or more users that can be assigned threads
        $mysupport_assign .= "<option value=\"mysupport_assign_find\">-- <i>{$lang->my_support_inline_find}</i></option>\n";
        if (!empty($assign_users)) {
            foreach ($assign_users as $assign_userid => $assign_username) {
                $mysupport_assign .= "<option value=\"mysupport_assign_" . intval(
                        $assign_userid
                    ) . "\">-- " . htmlspecialchars_uni($assign_username) . "</option>\n";
            }
        }
    }

    if ($mybb->settings['mysupport_enablepriorities']) {
        $mysupport_cache = $cache->read('mysupport');
        $mysupport_priorities = '';
        // only continue if there's any priorities
        if (!empty($mysupport_cache['priorities'])) {
            foreach ($mysupport_cache['priorities'] as $priority) {
                $mysupport_priorities .= "<option value=\"mysupport_priority_" . intval(
                        $priority['mid']
                    ) . "\">-- " . htmlspecialchars_uni($priority['name']) . "</option>\n";
            }
        }
    }

    $mysupport_categories = '';
    $categories_users = get_categories($foruminfo);
    // only continue if there's any priorities
    if (!empty($categories_users)) {
        foreach ($categories_users as $category_id => $category_name) {
            $mysupport_categories .= "<option value=\"mysupport_priority_" . intval(
                    $category_id
                ) . "\">-- " . htmlspecialchars_uni($category_name) . "</option>\n";
        }
    }

    $mysupport_inline_thread_moderation = eval($templates->render('mysupport_inline_thread_moderation'));
}

/**
 * Build an array of who can be assigned threads. Used to build the dropdown menus, and also check a valid user has been chosen.
 *
 * @return array Array of available categories.
 **/
function get_assign_users()
{
    global $db, $cache;

    // who can be assigned threads?
    $groups = $cache->read('usergroups');
    $assign_groups = array();
    foreach ($groups as $group) {
        if ($group['canbeassigned'] == 1) {
            $assign_groups[] = intval($group['gid']);
        }
    }

    // only continue if there's one or more groups that can be assigned threads
    if (!empty($assign_groups)) {
        $assigngroups = '';
        $assigngroups = implode(',', array_map('intval', $assign_groups));
        $assign_concat_sql = '';
        foreach ($assign_groups as $assign_group) {
            if (!empty($assign_concat_sql)) {
                $assign_concat_sql .= ' OR ';
            }
            $assign_concat_sql .= "CONCAT(',',additionalgroups,',') LIKE '%,{$assign_group},%'";
        }

        $query = $db->simple_select(
            'users',
            'uid, username',
            'usergroup IN (' . $db->escape_string($assigngroups) . ') OR displaygroup IN (' . $db->escape_string(
                $assigngroups
            ) . ") OR {$assign_concat_sql}"
        );
        $assign_users = array();
        while ($assigned = $db->fetch_array($query)) {
            $assign_users[$assigned['uid']] = $assigned['username'];
        }
    }
    return $assign_users;
}

/**
 * Build an array of available categories (thread prefixes). Used to build the dropdown menus, and also check a valid category has been chosen.
 *
 * @param array Info on the forum.
 * @return array Array of available categories.
 **/
function get_categories($forum)
{
    global $mybb, $db;

    $forums_concat_sql = $groups_concat_sql = '';

    $parent_list = explode(',', $forum['parentlist']);

    foreach ($parent_list as $parent) {
        if (!empty($forums_concat_sql)) {
            $forums_concat_sql .= ' OR ';
        }
        $forums_concat_sql .= "CONCAT(',',forums,',') LIKE '%," . intval($parent) . ",%'";
    }
    $forums_concat_sql = '(' . $forums_concat_sql . " OR forums = '-1')";

    $usergroup_list = $mybb->user['usergroup'];
    if (!empty($mybb->user['additionalgroups'])) {
        $usergroup_list .= ',' . $mybb->user['additionalgroups'];
    }
    $usergroup_list = explode(',', $usergroup_list);
    foreach ($usergroup_list as $usergroup) {
        if (!empty($groups_concat_sql)) {
            $groups_concat_sql .= ' OR ';
        }
        $groups_concat_sql .= "CONCAT(',',groups,',') LIKE '%," . intval($usergroup) . ",%'";
    }
    $groups_concat_sql = '(' . $groups_concat_sql . " OR groups = '-1')";

    $query = $db->simple_select('threadprefixes', 'pid, prefix', "{$forums_concat_sql} AND {$groups_concat_sql}");
    $categories = array();
    while ($category = $db->fetch_array($query)) {
        $categories[$category['pid']] = $category['prefix'];
    }
    return $categories;
}