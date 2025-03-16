<?php
/**
 * MyShowcase Plugin for MyBB - Code for Inline Moderation
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\plugins\myshowcase\inlinemod.php
 *
 */

declare(strict_types=1);

use function MyShowcase\Core\getTemplate;
use function MyShowcase\Core\showcaseDataUpdate;

global $mybb, $lang, $db, $templates, $plugins;
global $me;

global $showcaseInputOrder, $currentUserID, $showcaseInputSortBy, $currentPage;

switch ($mybb->get_input('action')) {
    case 'multiapprove';
    {
    } //no break since the code is the same except for the value being assigned
    case 'multiunapprove';
    {
        //verify if moderator and coming in from a click
        if (!$me->userperms['canmodapprove'] && $mybb->get_input('modtype') != 'inlineshowcase') {
            error($lang->myshowcase_not_authorized);
        }

        // Verify incoming POST request
        verify_post_check($mybb->get_input('my_post_key'));

        $gids = $me->getids('all', 'showcase');
        array_map('intval', $gids);

        if (count($gids) < 1) {
            error($lang->myshowcase_no_showcaseselected);
        }

        $groupIDs = implode(',', $gids);

        showcaseDataUpdate($me->id, ["gid IN ('{$groupIDs}')"], [
            'approved' => $mybb->get_input('action') === 'multiapprove' ? 1 : 0,
            'approved_by' => $currentUserID,
        ]);

        $modlogdata = [
            'id' => $me->id,
            'gids' => implode(',', $gids)
        ];
        log_moderator_action(
            $modlogdata,
            ($mybb->get_input(
                'action'
            ) == 'multiapprove' ? $lang->myshowcase_mod_approve : $lang->myshowcase_mod_unapprove)
        );

        $me->clearinline('all', 'showcase');

        //build URL to get back to where mod action happened
        $showcaseInputSortBy = $db->escape_string($showcaseInputSortBy);
        if ($showcaseInputSortBy != '') {
            $url_params[] = 'sort_by=' . $showcaseInputSortBy;
        }

        if ($showcaseInputOrder) {
            $url_params[] = 'order=' . $showcaseInputOrder;
        }

        $currentPage = intval($mybb->get_input('page', MyBB::INPUT_INT));
        if ($currentPage != '') {
            $url_params[] = 'page=' . $currentPage;
        }

        $url = SHOWCASE_URL . (count($url_params) > 0 ? '?' . implode('&amp;', $url_params) : '');

        $redirtext = ($mybb->get_input(
            'action'
        ) == 'multiapprove' ? $lang->redirect_myshowcase_approve : $lang->redirect_myshowcase_unapprove);
        redirect($url, $redirtext);
        exit;
        break;
    }

    case 'multidelete';
    {
        add_breadcrumb($lang->myshowcase_nav_multidelete);

        if (!$me->userperms['canmoddelete'] && $mybb->get_input('modtype') != 'inlineshowcase') {
            error($lang->myshowcase_not_authorized);
        }

        // Verify incoming POST request
        verify_post_check($mybb->get_input('my_post_key'));

        $gids = $me->getids('all', 'showcase');

        if (count($gids) < 1) {
            error($lang->myshowcase_no_myshowcaseselected);
        }

        $inlineids = implode('|', $gids);

        $me->clearinline('all', 'showcase');

        //build URl to get back to where mod action happened
        $showcaseInputSortBy = $db->escape_string($showcaseInputSortBy);
        if ($showcaseInputSortBy != '') {
            $url_params[] = 'sort_by=' . $showcaseInputSortBy;
        }

        if ($showcaseInputOrder) {
            $url_params[] = 'order=' . $showcaseInputOrder;
        }

        $currentPage = intval($currentPage);
        if ($currentPage != '') {
            $url_params[] = 'page=' . $currentPage;
        }

        $return_url = SHOWCASE_URL . (count($url_params) > 0 ? '?' . implode('&amp;', $url_params) : '');
        //$return_url = htmlspecialchars_uni($mybb->get_input('url'));
        $multidelete = eval(getTemplate('inline_deleteshowcases'));
        output_page($multidelete);
        break;
    }
    case 'do_multidelete';
    {
        if (!$me->userperms['canmoddelete']) {
            error($lang->myshowcase_not_authorized);
        }

        // Verify incoming POST request
        verify_post_check($mybb->get_input('my_post_key'));

        $gids = explode('|', $mybb->get_input('showcases'));

        foreach ($gids as $gid) {
            $gid = intval($gid);
            $me->delete($gid);
            $glist[] = $gid;
        }

        //log_moderator_action($modlogdata, $lang->multi_deleted_threads);

        $me->clearinline('all', 'showcase');

        //build URl to get back to where mod action happened
        $showcaseInputSortBy = $db->escape_string($showcaseInputSortBy);
        if ($showcaseInputSortBy != '') {
            $url_params[] = 'sort_by=' . $showcaseInputSortBy;
        }

        if ($showcaseInputOrder) {
            $url_params[] = 'order=' . $showcaseInputOrder;
        }

        $currentPage = intval($currentPage);
        if ($currentPage != '') {
            $url_params[] = 'page=' . $currentPage;
        }

        $url = SHOWCASE_URL . (count($url_params) > 0 ? '?' . implode('&amp;', $url_params) : '');

        redirect($url, $lang->redirect_myshowcase_delete);
        exit;
        break;
    }
}