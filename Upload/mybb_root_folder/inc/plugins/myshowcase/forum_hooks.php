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

declare(strict_types=1);

namespace MyShowcase\AdminHooks;

use function MyShowcase\Core\cacheGet;
use function MyShowcase\Core\entryGetRandom;
use function MyShowcase\Core\getSetting;
use function MyShowcase\Core\loadLanguage;
use function MyShowcase\Core\getTemplate;

/**
 * Add global notices for unapproved and reported showcases
 *
 */
function global_start(): bool
{
    global $templatelist, $mybb;

    if (isset($templatelist)) {
        $templatelist .= ',';
    } else {
        $templatelist = '';
    }

    $templatelist .= ',';

    if (defined('THIS_SCRIPT')) {
        if (THIS_SCRIPT == 'showthread.php') {
            $templatelist .= ', ';
        }

        if (THIS_SCRIPT == 'editpost.php' || THIS_SCRIPT == 'newthread.php') {
            $templatelist .= ', ';
        }

        if (THIS_SCRIPT == 'forumdisplay.php') {
            $templatelist .= ', ';
        }
    }

    /*if(\MyShowcase\MyAlerts\myalertsIsIntegrable())
    {
        if($mybb->user['uid'])
        {
            \MyShowcase\MyAlerts\registerMyalertsFormatters();
        }
    }*/

    global $mybb, $db, $cache, $myshowcase_unapproved, $myshowcase_reported, $theme, $templates, $lang;

    //get showcases and mods
    $showcases = cacheGet(CACHE_TYPE_CONFIG);
    $moderators = cacheGet(CACHE_TYPE_MODERATORS);

    //loop through showcases
    $rep_ids = [];
    foreach ($showcases as $id => $showcase) {
        //if showcase is enabled...
        if ($showcase['enabled']) {
            ///get array of all user's groups
            $usergroups = explode(',', $mybb->user['additionalgroups']);
            $usergroups[] = $mybb->user['usergroup'];

            //...loop through mods
            $canapprove = 0;
            $caneditdel = 0;
            if (is_array($moderators[$id])) {
                foreach ($moderators[$id] as $mid => $mod) {
                    //check if user is specifically a mod
                    if ($mybb->user['uid'] == $mod[$mod['id']]['uid'] && $mod[$mod['id']]['isgroup'] == 0) {
                        if ($mod[$mod['id']]['canmodapprove'] == 1) {
                            $canapprove = 1;
                        }

                        if ($mod[$mod['id']]['canmodedit'] == 1 || $mod[$mod['id']]['canmoddelete'] == 1 || $mod[$mod['id']]['canmoddelcomment'] == 1) {
                            $caneditdel = 1;
                        }
                        continue;
                    }

                    //check if user in mod group
                    if (array_key_exists($mod[$mod['id']]['uid'], $usergroups) && $mod[$mod['id']]['isgroup'] == 1) {
                        if ($mod[$mod['id']]['canmodapprove'] == 1) {
                            $canapprove = 1;
                        }

                        if ($mod[$mod['id']]['canmodedit'] == 1 || $mod[$mod['id']]['canmoddelete'] == 1 || $mod[$mod['id']]['canmoddelcomment'] == 1) {
                            $caneditdel = 1;
                        }
                        continue;
                    }
                }
            }

            //check if user in default mod groups
            if (is_member(getSetting('moderatorGroups'))) {
                $canapprove = 1;
                $caneditdel = 1;
            }

            //load language if we are going to use it
            if ($canapprove || $caneditdel) {
                loadLanguage();
            }

            $showcase_path = $mybb->settings['bburl'] . '/' . $showcase['f2gpath'] . $showcase['mainfile'];

            //awaiting approval
            if ($canapprove) {
                $query = $db->query(
                    'SELECT COUNT(*) AS total FROM ' . TABLE_PREFIX . 'myshowcase_data' . $id . ' WHERE approved=0 GROUP BY approved'
                );
                $num_unapproved = $db->fetch_field($query, 'total');
                if ($num_unapproved > 0) {
                    $unapproved_text = str_replace('{num}', $num_unapproved, $lang->myshowcase_unapproved_count);
                    $unapproved_text = str_replace('{name}', $showcase['name'], $unapproved_text);
                    if ($unapproved_notice != '') {
                        $unapproved_notice .= '<br />';
                    }
                    $unapproved_notice .= "<a href=\"" . $showcase_path . "?unapproved=1\" />{$unapproved_text}</a>";
                }
            }

            //report notices
            if ($caneditdel) {
                $rep_ids[$id]['name'] = $showcase['name'];
                $rep_ids[$id]['path'] = $showcase_path;
            }
        }
    }

    if (count($rep_ids) > 0) {
        $ids = implode(',', array_keys($rep_ids));
        $query = $db->query(
            'SELECT `id`, COUNT(*) AS total FROM ' . TABLE_PREFIX . 'myshowcase_reports WHERE `id` IN (' . $ids . ') AND `status`=0 GROUP BY `id`, `status`'
        );
        while ($reports = $db->fetch_array($query)) {
            $reported_text = str_replace('{num}', $reports['total'], $lang->myshowcase_report_count);
            $reported_text = str_replace('{name}', $rep_ids[$reports['id']]['name'], $reported_text);
            if ($reported_notice != '') {
                $reported_notice .= '<br />';
            }
            $reported_notice .= "<a href=\"" . $rep_ids[$reports['id']]['path'] . "?action=reports\" />{$reported_text}</a>";
        }
    }

    //get templates
    if ($unapproved_notice != '') {
        $myshowcase_unapproved = eval(getTemplate('unapproved'));
    }

    if ($reported_notice != '') {
        $myshowcase_reported = eval(getTemplate('reported'));
    }

    return true;
}

//build info for who's online
function fetch_wol_activity_end(array &$user_activity): array
{
    global $user, $mybb, $cache;

    //get filename of location
    $split_loc = explode('.php', $user_activity['location']);
    if ($split_loc[0] == $user['location']) {
        $filename = '';
    } else {
        $filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), '/'));
    }

    //get query params
    if ($split_loc[1]) {
        $temp = explode('&', my_substr($split_loc[1], 1));
        foreach ($temp as $param) {
            $temp2 = explode('=', $param, 2);
            $temp2[0] = str_replace('amp;', '', $temp2[0]);
            $parameters[$temp2[0]] = $temp2[1];
        }
    }

    //get cache of configured myshowcases
    $myshowcase_config = cacheGet(CACHE_TYPE_CONFIG);

    //check cache for matching filename
    //have to do it this way since the filename can vary for each myshowcase
    if (is_array($myshowcase_config)) {
        foreach ($myshowcase_config as $id => $myshowcase) {
            $split_mainfile = explode('.php', $myshowcase['mainfile']);
            if ($split_mainfile[0] == $filename) {
                //preload here so we don't need to get it in next function
                $user_activity['myshowcase_filename'] = $filename;
                $user_activity['myshowcase_name'] = $myshowcase['name'];
                $user_activity['myshowcase_id'] = $myshowcase['id'];
                $user_activity['myshowcase_mainfile'] = $myshowcase['mainfile'];

                if ($parameters['action'] == 'view') {
                    $user_activity['activity'] = 'myshowcase_view';
                    if (is_numeric($parameters['gid'])) {
                        $user_activity['gid'] = $parameters['gid'];
                    }
                } elseif ($parameters['action'] == 'new') {
                    $user_activity['activity'] = 'myshowcase_new';
                } elseif ($parameters['action'] == 'attachment') {
                    $user_activity['activity'] = 'myshowcase_view_attach';
                    if (is_numeric($parameters['aid'])) {
                        $user_activity['aid'] = $parameters['aid'];
                    }
                } elseif ($parameters['action'] == 'edit') {
                    $user_activity['activity'] = 'myshowcase_edit';
                    if (is_numeric($parameters['gid'])) {
                        $user_activity['gid'] = $parameters['gid'];
                    }
                } else {
                    $user_activity['activity'] = 'myshowcase_list';
                }

                //if here, we found the lcoation, so exit loop
                continue;
            }
        }
    }

    return $user_activity;
}

//setup friendly WOL locations 
function build_friendly_wol_location_end(array &$plugin_array): array
{
    global $db, $lang, $mybb, $_SERVER, $user;

    loadLanguage();

    //get filename of location
    $split_loc = explode('.php', $plugin_array['user_activity']['location']);
    if ($split_loc[0] == $user['location']) {
        $filename = '';
    } else {
        $filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), '/'));
    }

    /* URL Definitions */
    if ($mybb->settings['seourls'] == 'yes' || ($mybb->settings['seourls'] == 'auto' && $_SERVER['SEO_SUPPORT'] == 1)) {
        $myshowcase_name = strtolower($plugin_array['user_activity']['myshowcase_name']);

        //clean the name and make it suitable for SEO
        //cleaning borrowed from Google SEO plugin
        $pattern = '!"#$%&\'( )*+,-./:;<=>?@[\]^_`{|}~';
        $pattern = preg_replace(
            "/[\\\\\\^\\-\\[\\]\\/]/u",
            "\\\\\\0",
            $pattern
        );

        // Cut off punctuation at beginning and end.
        $myshowcase_name = preg_replace(
            "/^[$pattern]+|[$pattern]+$/u",
            '',
            strtolower($myshowcase_name)
        );

        // Replace middle punctuation with one separator.
        $myshowcase_name = preg_replace(
            "/[$pattern]+/u",
            '-',
            $myshowcase_name
        );

        $myshowcase_url = $myshowcase_name . '.html';
        $myshowcase = $myshowcase_name . '-page-{page}.html';
        $myshowcase_url_view = $myshowcase_name . '-view-{gid}.html';
        $myshowcase_url_new = $myshowcase_name . '-new.html';
        $myshowcase_url_view_attach = $myshowcase_name . '-attachment-{aid}.html';
        $amp = '?';
    } else {
        $myshowcase_url = $plugin_array['user_activity']['myshowcase_mainfile'];
        $myshowcase_url_paged = $plugin_array['user_activity']['myshowcase_mainfile'] . '?page={page}';
        $myshowcase_url_view = $plugin_array['user_activity']['myshowcase_mainfile'] . '?action=view&gid={gid}';
        $myshowcase_url_new = $plugin_array['user_activity']['myshowcase_mainfile'] . '?action=new';
        $myshowcase_url_view_attach = $plugin_array['user_activity']['myshowcase_mainfile'] . '?action=attachment&aid={aid}';
        $amp = '&';
    }

    switch ($plugin_array['user_activity']['activity']) {
        case 'myshowcase_list':
            $plugin_array['location_name'] = $lang->sprintf(
                $lang->viewing_myshowcase_list,
                $myshowcase_url,
                $plugin_array['user_activity']['myshowcase_name']
            );
            break;

        case 'myshowcase_view':
            if (array_key_exists('gid', $plugin_array['user_activity'])) {
                $query = $db->simple_select(
                    "myshowcase_data{$plugin_array['user_activity']['myshowcase_id']}",
                    'gid,uid',
                    'gid=' . $plugin_array['user_activity']['gid']
                );
                while ($myshowcase = $db->fetch_array($query)) {
                    $uid = $myshowcase['uid'];
                    $userinfo = get_user($uid);
                }
            }
            $plugin_array['location_name'] = $lang->sprintf(
                $lang->viewing_myshowcase,
                str_replace('{gid}', $plugin_array['user_activity']['gid'], $myshowcase_url_view),
                $plugin_array['user_activity']['myshowcase_name'],
                get_profile_link($uid),
                $userinfo['username']
            );
            break;

        case 'myshowcase_new':
            $plugin_array['location_name'] = $lang->sprintf(
                $lang->viewing_myshowcase_new,
                $myshowcase_url_new,
                $plugin_array['user_activity']['myshowcase_name']
            );
            break;

        case 'myshowcase_edit':
            $plugin_array['location_name'] = $lang->sprintf(
                $lang->viewing_myshowcase_edit,
                $plugin_array['user_activity']['myshowcase_name']
            );
            break;

        case 'myshowcase_view_attach':
            if (array_key_exists('aid', $plugin_array['user_activity'])) {
                $query = $db->simple_select(
                    'myshowcase_attachments',
                    'aid,gid,uid',
                    'aid=' . $plugin_array['user_activity']['aid']
                );
                while ($showcase = $db->fetch_array($query)) {
                    $uid = $showcase['uid'];
                    $gid = $showcase['gid'];
                    $userinfo = get_user($uid);
                }
            }
            $plugin_array['location_name'] = $lang->sprintf(
                $lang->viewing_myshowcase_attach,
                str_replace('{aid}', $plugin_array['user_activity']['aid'], $myshowcase_url_view_attach),
                str_replace('{gid}', $gid, $myshowcase_view_url ?? ''),
                $plugin_array['user_activity']['myshowcase_name'],
                get_profile_link($uid),
                $userinfo['username']
            );
            break;
    }

    return $plugin_array;
}

//get the myshowcase counts for users that posted in the thread. keeps
//from having to do it every post, just every page view
function showthread_start(): bool
{
    global $db, $mybb, $thread, $cache, $myshowcase_uids;

    //get list of enabled myshowcases with postbit links turned on
    $myshowcase_uids = [];

    $myshowcases = cacheGet(CACHE_TYPE_CONFIG);
    foreach ($myshowcases as $id => $myshowcase) {
        if ($myshowcase['enabled'] && $myshowcase['link_in_postbit']) {
            $myshowcase_uids[$myshowcase['id']]['name'] = $myshowcase['name'];
            $myshowcase_uids[$myshowcase['id']]['mainfile'] = $myshowcase['mainfile'];
            $myshowcase_uids[$myshowcase['id']]['f2gpath'] = $myshowcase['f2gpath'];
        }
    }

    //if we have any myshowcases to link....
    if (count($myshowcase_uids) > 0) {
        $gidlist = implode(',', array_keys($myshowcase_uids));

        //get uids for users that posted to the thread
        $query = $db->query(
            'SELECT uid FROM ' . TABLE_PREFIX . 'posts WHERE tid=' . (int)$thread['tid'] . ' AND uid > 0 GROUP BY uid'
        );
        $uids = [];
        while ($result = $db->fetch_array($query)) {
            $uids[$result['uid']] = 0;
        }
        $uidlist = implode(',', array_keys($uids));
        unset($query);
        unset($result);

        //get myshowcase counts for users in thread
        if (count($uids)) {
            foreach ($myshowcase_uids as $gid => $data) {
                $query = $db->query(
                    'SELECT uid, count(uid) AS total FROM ' . TABLE_PREFIX . 'myshowcase_data' . $gid . " WHERE uid IN ({$uidlist}) AND approved = 1 GROUP BY uid"
                );
                while ($result = $db->fetch_array($query)) {
                    $myshowcase_uids[$gid]['uids'][$result['uid']] = $result['total'];
                }
            }
        }
        unset($query);
        unset($result);
    }

    return true;
}

//add myshowcase links/counts
function postbit(array &$post): array
{
    global $mybb, $_SERVER, $lang, $myshowcase_uids;

    if (count($myshowcase_uids) > 0) {
        foreach ($myshowcase_uids as $myshowcase => $data) {
            $showcase_name = $data['name'];
            $showcase_file = $data['mainfile'];
            $showcase_fldr = $data['f2gpath'];

            /* URL Definitions */
            if ($mybb->settings['seourls'] == 'yes' || ($mybb->settings['seourls'] == 'auto' && $_SERVER['SEO_SUPPORT'] == 1)) {
                $showcase_file = strtolower($data['name']) . '.html';
            } else {
                $showcase_file = $data['mainfile'];
            }

            if ($data['uids'][$post['uid']] > 0) {
                $post['user_details'] .= '<br />' . $showcase_name . ':  <a href="' . $showcase_fldr . $showcase_file . '?search=username&searchterm=' . rawurlencode(
                        $post['username']
                    ) . '&exactmatch=1">' . $data['uids'][$post['uid']] . '</a>';
            }
        }
    }

    return $post;
}

//function to pull a random entry from a random showcase (if enabled)
function portal_start(): bool
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
        $portal_rand_showcase = entryGetRandom();
        if (!$portal_rand_showcase) {
            //add code here to use portal_basic_box template box or some
            //other output if a random showcase with attachments is not found
        }
    }

    return true;
}