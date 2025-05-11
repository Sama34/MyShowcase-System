<?php
/**
 * MyShowcase Plugin for MyBB - Main Plugin
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: https://github.com/Sama34/MyShowcase-System
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\plugins\myshowcase.php
 *
 */

declare(strict_types=1);

namespace MyShowcase\System;

use MyBB;
use JetBrains\PhpStorm\NoReturn;

use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\attachmentUpdate;
use function MyShowcase\Core\getTemplate;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\Core\entryDataGet;
use function MyShowcase\Core\entryDataUpdate;
use function MyShowcase\SimpleRouter\url;

use const MyShowcase\Core\ATTACHMENT_UNLIMITED;
use const MyShowcase\Core\ATTACHMENT_ZERO;
use const MyShowcase\Core\TABLES_DATA;
use const MyShowcase\Core\URL_TYPE_MAIN;

class Output
{
    public function __construct(
        public Showcase &$showcaseObject,
        public Render &$renderObject,
        public array $urlParams = [],
        public int $page = 0,
        public int $pageCurrent = 0,
    ) {
        global $mybb;

        $this->urlParams = [];

        if ($mybb->get_input('unapproved', MyBB::INPUT_INT)) {
            $this->urlParams['unapproved'] = $mybb->get_input('unapproved', MyBB::INPUT_INT);
        }

        if (array_key_exists($this->showcaseObject->sortByField, $this->showcaseObject->fieldSetFieldsDisplayFields)) {
            $this->urlParams['sort_by'] = $this->showcaseObject->sortByField;
        }

        if ($renderObject->searchExactMatch) {
            $this->urlParams['exact_match'] = $renderObject->searchExactMatch;
        }

        if ($renderObject->searchKeyWords) {
            $this->urlParams['keywords'] = $renderObject->searchKeyWords;
        }

        if (in_array($this->showcaseObject->searchField, array_keys($this->showcaseObject->fieldSetSearchableFields))) {
            $this->urlParams['search_field'] = $this->showcaseObject->searchField;
        }

        if ($this->showcaseObject->orderBy) {
            $this->urlParams['order_by'] = $this->showcaseObject->orderBy;
        }

        if ($mybb->get_input('page', MyBB::INPUT_INT) > 0) {
            $this->pageCurrent = $mybb->get_input('page', MyBB::INPUT_INT);
        }

        if ($this->pageCurrent) {
            $this->urlParams['page'] = $this->pageCurrent;
        }
    }

    public function inlineModeration(): void
    {
        global $mybb, $lang;

        $currentUserID = (int)$mybb->user['uid'];

        switch ($mybb->get_input('action')) {
            case 'multiapprove';
            {
            } //no break since the code is the same except for the value being assigned
            case 'multiunapprove';
            {
                //verify if moderator and coming in from a click
                if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries] && $mybb->get_input(
                        'modtype'
                    ) !== 'inlineshowcase') {
                    error($lang->myshowcase_not_authorized);
                }

                // Verify incoming POST request
                verify_post_check($mybb->get_input('my_post_key'));

                $gids = $this->showcaseObject->inlineGetIDs();
                array_map('intval', $gids);

                if (count($gids) < 1) {
                    error($lang->myshowcase_no_showcaseselected);
                }

                $groupIDs = implode(',', $gids);

                foreach ($gids as $entryID) {
                    entryDataUpdate($this->showcaseObject->showcase_id, $entryID, [
                        'approved' => $mybb->get_input('action') === 'multiapprove' ? 1 : 0,
                        'approved_by' => $currentUserID,
                    ]);
                }

                $modlogdata = [
                    'showcase_id' => $this->showcaseObject->showcase_id,
                    'gids' => implode(',', $gids)
                ];
                log_moderator_action(
                    $modlogdata,
                    ($mybb->get_input(
                        'action'
                    ) === 'multiapprove' ? $lang->myshowcase_mod_approve : $lang->myshowcase_mod_unapprove)
                );

                $this->showcaseObject->inlineClear();

                if ($this->showcaseObject->sortByField) {
                    $url_params['sort_by'] = $this->showcaseObject->sortByField;
                }

                if ($this->showcaseObject->orderBy) {
                    $url_params[] = 'order_by=' . $this->showcaseObject->orderBy;
                }

                if ($this->pageCurrent) {
                    $url_params[] = 'page=' . $this->pageCurrent;
                }

                $mainUrl = url(URL_TYPE_MAIN, getParams: $this->showcaseObject->urlParams)->getRelativeUrl();

                $redirtext = ($mybb->get_input(
                    'action'
                ) === 'multiapprove' ? $lang->redirect_myshowcase_approve : $lang->redirect_myshowcase_unapprove);

                redirect($mainUrl, $redirtext);
                exit;
                break;
            }

            case 'multidelete';
            {
                add_breadcrumb($lang->myshowcase_nav_multidelete);

                if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries] && $mybb->get_input(
                        'modtype'
                    ) !== 'inlineshowcase') {
                    error($lang->myshowcase_not_authorized);
                }

                // Verify incoming POST request
                verify_post_check($mybb->get_input('my_post_key'));

                $gids = $this->showcaseObject->inlineGetIDs();

                if (count($gids) < 1) {
                    error($lang->myshowcase_no_myshowcaseselected);
                }

                $inlineids = implode('|', $gids);

                $this->showcaseObject->inlineClear();

                if ($this->showcaseObject->sortByField) {
                    $url_params['sort_by'] = $this->showcaseObject->sortByField;
                }

                if ($this->showcaseObject->orderBy) {
                    $url_params[] = 'order_by=' . $this->showcaseObject->orderBy;
                }

                if ($this->pageCurrent) {
                    $url_params[] = 'page=' . $this->pageCurrent;
                }

                $mainUrl = url(URL_TYPE_MAIN, getParams: $this->showcaseObject->urlParams)->getRelativeUrl();

                $multidelete = eval(getTemplate('inline_deleteshowcases'));

                output_page($multidelete);
                break;
            }
            case 'do_multidelete';
            {
                if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries]) {
                    error($lang->myshowcase_not_authorized);
                }

                // Verify incoming POST request
                verify_post_check($mybb->get_input('my_post_key'));

                $gids = explode('|', $mybb->get_input('showcases'));

                foreach ($gids as $gid) {
                    $gid = intval($gid);
                    $this->showcaseObject->entryDelete($gid);
                    $glist[] = $gid;
                }

                //log_moderator_action($modlogdata, $lang->multi_deleted_threads);

                $this->showcaseObject->inlineClear();

                //build URl to get back to where mod action happened
                if ($this->showcaseObject->sortByField) {
                    $url_params['sort_by'] = $this->showcaseObject->sortByField;
                }

                if ($this->showcaseObject->orderBy) {
                    $url_params[] = 'order_by=' . $this->showcaseObject->orderBy;
                }

                if ($this->pageCurrent) {
                    $url_params[] = 'page=' . $this->pageCurrent;
                }

                $mainUrl = url(URL_TYPE_MAIN, getParams: $this->showcaseObject->urlParams)->getRelativeUrl();

                redirect($mainUrl, $lang->redirect_myshowcase_delete);
                exit;
                break;
            }
        }
    }
}