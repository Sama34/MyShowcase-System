<?php
/**
 * MyShowcase Plugin for MyBB - MyShowcase Class
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\class_showcase.php
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

    #[NoReturn] public function entryPost(bool $isEditPage = false): void
    {
        global $lang, $mybb, $db, $cache;
        global $header, $headerinclude, $footer, $theme;
        global $entryHash;
        global $templates, $plugins;
        global $entryHash;

        $currentUserID = (int)$mybb->user['uid'];

        $hookArguments = [];

        $entryUserData = get_user($this->showcaseObject->entryUserID);

        $showcaseUserPermissions = $this->showcaseObject->userPermissionsGet($this->showcaseObject->entryUserID);

        if ($mybb->request_method === 'post') {
            verify_post_check($mybb->get_input('my_post_key'));

            if ($isEditPage) {
                if (!$entryUserData) {
                    error($lang->myshowcase_invalid_id);
                }
            } else {
                // Get a listing of the current attachments.
                if (!empty($showcaseUserPermissions[UserPermissions::CanUploadAttachments])) {
                    $attachcount = 0;

                    $attachments = '';

                    $attachmentObjects = attachmentGet(
                        [
                            "entry_hash='{$db->escape_string($entryHash)}'",
                            "showcase_id={$this->showcaseObject->showcase_id}"
                        ],
                        array_keys(TABLES_DATA['myshowcase_attachments'])
                    );

                    foreach ($attachmentObjects as $attachmentID => $attachmentData) {
                        $attachmentData['size'] = get_friendly_size($attachmentData['file_size']);
                        $attachmentData['icon'] = get_attachment_icon(get_extension($attachmentData['file_name']));
                        $attachmentData['icon'] = str_replace(
                            '<img src="',
                            '<img src="' . $this->showcaseObject->urlBase . '/',
                            $attachmentData['icon']
                        );


                        $attach_mod_options = '';
                        if ($attachmentData['status'] !== 1) {
                            $attachments .= eval(getTemplate('new_attachments_attachment_unapproved'));
                        } else {
                            $attachments .= eval(getTemplate('new_attachments_attachment'));
                        }
                        $attachcount++;
                    }
                    $lang->myshowcase_attach_quota = $lang->sprintf(
                            $lang->myshowcase_attach_quota,
                            $attachcount,
                            ($showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit] === ATTACHMENT_UNLIMITED ? $lang->myshowcase_unlimited : $showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit])
                        ) . '<br>';
                    if ($showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit] === ATTACHMENT_UNLIMITED || ($showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit] !== ATTACHMENT_ZERO && ($attachcount < $showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit]))) {
                        if ($this->showcaseObject->userPermissions[UserPermissions::CanWaterMarkAttachments]) {
                            $showcase_watermark = eval(getTemplate('watermark'));
                        }
                        $showcase_new_attachments_input = eval(getTemplate('new_attachments_input'));
                    }
                    $showcase_attachments = eval(getTemplate('new_attachments'));
                }
            }
        }

        if ($isEditPage) {
            if (!$this->showcaseObject->userPermissions[UserPermissions::CanUpdateEntries]) {
                error($lang->myshowcase_not_authorized);
            }
        } elseif (!$this->showcaseObject->userPermissions[UserPermissions::CanCreateEntries]) {
            error($lang->myshowcase_not_authorized);
        }

        $hookArguments = hooksRun('output_new_start', $hookArguments);

        global $errorsAttachments;

        $errorsAttachments ??= '';

        $attachmentsTable = '';

        // Get a listing of the current attachments.
        if (!empty($showcaseUserPermissions[UserPermissions::CanUploadAttachments])) {
            $attachcount = 0;

            $attachments = '';

            $attachmentObjects = attachmentGet(
                ["entry_hash='{$db->escape_string($entryHash)}'"],
                array_keys(TABLES_DATA['myshowcase_attachments'])
            );

            foreach ($attachmentObjects as $attachmentID => $attachmentData) {
                $attachmentData['size'] = get_friendly_size($attachmentData['file_size']);
                $attachmentData['icon'] = get_attachment_icon(get_extension($attachmentData['file_name']));
                $attachmentData['icon'] = str_replace(
                    '<img src="',
                    '<img src="' . $this->showcaseObject->urlBase . '/',
                    $attachmentData['icon']
                );

                $attach_mod_options = '';
                if ($attachmentData['status'] !== 1) {
                    $attachments .= eval(getTemplate('new_attachments_attachment_unapproved'));
                } else {
                    $attachments .= eval(getTemplate('new_attachments_attachment'));
                }
                $attachcount++;
            }
            $lang->myshowcase_attach_quota = $lang->sprintf(
                    $lang->myshowcase_attach_quota,
                    $attachcount,
                    ($showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit] === ATTACHMENT_UNLIMITED ? $lang->myshowcase_unlimited : $showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit])
                ) . '<br>';
            if ($showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit] === ATTACHMENT_UNLIMITED || ($showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit] !== ATTACHMENT_UNLIMITED && $attachcount < $showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit])) {
                if ($this->showcaseObject->userPermissions[UserPermissions::CanWaterMarkAttachments] && $this->showcaseObject->config['attachments_watermark_file'] !== '' && file_exists(
                        $this->showcaseObject->config['attachments_watermark_file']
                    )) {
                    $showcase_watermark = eval(getTemplate('watermark'));
                }
                $showcase_new_attachments_input = eval(getTemplate('new_attachments_input'));
            }

            if (!empty($showcase_new_attachments_input) || $attachments !== '') {
                $attachmentsTable = eval(getTemplate('pageNewAttachments'));
            }
        }

        $alternativeBackground = 'trow2';

        $showcaseFields = '';

        $fieldTabIndex = 1;

        $pageTitle = $this->showcaseObject->config['name'];

        $pageContents = eval(getTemplate('pageEntryCreateUpdateContents'));

        $mainUrl = url(URL_TYPE_MAIN, getParams: $this->showcaseObject->urlParams)->getRelativeUrl();

        $pageContents = eval(getTemplate('page'));

        $hookArguments = hooksRun('output_new_end', $hookArguments);

        output_page($pageContents);

        exit;
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

    #[NoReturn] public function edit(): void
    {
        global $mybb, $lang;

        $currentUserID = (int)$mybb->user['uid'];

        if (!$this->showcaseObject->entryData) {
            error($lang->myshowcase_invalid_id);
        }

        $entryFieldData = entryDataGet(
            $this->showcaseObject->showcase_id,
            ["entry_id='{$this->showcaseObject->entryData}'"],
            ['user_id', 'entry_hash']
        );

        if (!$entryFieldData) {
            error($lang->myshowcase_invalid_id);
        }

        //make sure current user is moderator or the myshowcase author
        if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries] && $currentUserID !== $entryFieldData['user_id']) {
            error($lang->myshowcase_not_authorized);
        }

        //get permissions for user
        $entryUserPermissions = $this->showcaseObject->userPermissionsGet($this->showcaseObject->entryUserID);

        $entryHash = $entryFieldData['entry_hash'];
        //no break since edit will share NEW code
    }

    #[NoReturn] public function attachmentDownload(int $attachmentID): void
    {
        global $mybb, $lang, $plugins;

        $attachmentData = attachmentGet(["attachment_id='{$attachmentID}'"],
            array_keys(TABLES_DATA['myshowcase_attachments']),
            ['limit' => 1]);

        // Error if attachment is invalid or not status
        if (!$attachmentData['attachment_id'] || !$attachmentData['attachment_name'] || (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries] && $attachmentData['status'] !== 1)) {
            error($lang->error_invalidattachment);
        }

        if (!$this->showcaseObject->config['attachments_allow_entries'] || !$this->showcaseObject->userPermissions[UserPermissions::CanViewAttachments]) {
            error_no_permission();
        }

        $filePath = MYBB_ROOT . $this->showcaseObject->config['attachments_uploads_path'] . '/' . $attachmentData['attachment_name'];

        if (!file_exists($filePath)) {
            error($lang->error_invalidattachment);
        }

        $plugins->run_hooks('myshowcase_attachment_start');

        attachmentUpdate(['downloads' => $attachmentData['downloads'] + 1], $attachmentID);

        if (stristr($attachmentData['file_type'], 'image/')) {
            $posterdata = get_user($attachmentData['user_id']);

            $showcase_viewing_user = str_replace('{username}', $posterdata['username'], $lang->myshowcase_viewing_user);

            add_breadcrumb(
                $showcase_viewing_user,
                str_replace('{entry_id}', $attachmentData['entry_id'], $this->showcaseObject->urlViewEntry)
            );

            $attachmentData['file_name'] = rawurlencode($attachmentData['file_name']);

            $plugins->run_hooks('myshowcase_attachment_end');

            $showcase_viewing_attachment = str_replace(
                '{username}',
                $posterdata['username'],
                $lang->myshowcase_viewing_attachment
            );

            add_breadcrumb(
                $showcase_viewing_attachment,
                str_replace('{entry_id}', $attachmentData['entry_id'], $this->showcaseObject->urlViewEntry)
            );

            $lasteditdate = my_date($mybb->settings['dateformat'], $attachmentData['dateline']);

            $lastedittime = my_date($mybb->settings['timeformat'], $attachmentData['dateline']);

            $entryDateline = $lasteditdate . '&nbsp;' . $lastedittime;

            $showcase_attachment_description = $lang->myshowcase_attachment_filename . $attachmentData['file_name'] . '<br />' . $lang->myshowcase_attachment_uploaded . $entryDateline;

            $showcase_attachment = str_replace(
                '{attachment_id}',
                $attachmentData['attachment_id'],
                $this->showcaseObject->urlViewAttachmentItem
            );//$this->showcaseObject->attachments_uploads_path."/".$attachmentData['attachment_name'];

            $pageContents = eval(getTemplate('attachment_view'));

            $plugins->run_hooks('myshowcase_attachment_end');

            output_page($pageContents);
        } else //should never really be called, but just incase, support inline output
        {
            header('Cache-Control: private', false);

            header('Content-Type: ' . $attachmentData['file_type']);

            header('Content-Description: File Transfer');

            header('Content-Disposition: inline; file_name=' . $attachmentData['file_name']);

            header('Content-Length: ' . $attachmentData['file_size']);

            header('Expires: 0');

            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

            header('Pragma: public');

            ob_clean();

            flush();

            readfile($filePath);
        }

        exit;
    }

    #[NoReturn] public function attachmentDownloadItem(int $attachmentID): void
    {
        global $lang, $plugins;

        $attachmentData = attachmentGet(["attachment_id='{$attachmentID}'"],
            array_keys(TABLES_DATA['myshowcase_attachments']),
            ['limit' => 1]);

        // Error if attachment is invalid or not status
        if (empty($attachmentData['attachment_id']) ||
            empty($attachmentData['attachment_name']) ||
            (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries] && empty($attachmentData['status']))) {
            error($lang->error_invalidattachment);
        }

        if (!$this->showcaseObject->config['attachments_allow_entries'] || !$this->showcaseObject->userPermissions[UserPermissions::CanDownloadAttachments]) {
            error_no_permission();
        }

        //$attachmentExtension = get_extension($attachmentData['file_name']);

        $filePath = MYBB_ROOT . $this->showcaseObject->config['attachments_uploads_path'] . '/' . $attachmentData['attachment_name'];

        if (!file_exists($filePath)) {
            error($lang->error_invalidattachment);
        }

        switch ($attachmentData['file_type']) {
            case 'application/pdf':
            case 'image/bmp':
            case 'image/gif':
            case 'image/jpeg':
            case 'image/pjpeg':
            case 'image/png':
            case 'text/plain':
                header("Content-type: {$attachmentData['file_type']}");

                $disposition = 'inline';
                break;

            default:
                header('Content-type: application/force-download');

                $disposition = 'attachment';
        }

        if (my_strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'msie') !== false) {
            header("Content-disposition: attachment; file_name=\"{$attachmentData['file_name']}\"");
        } else {
            header("Content-disposition: {$disposition}; file_name=\"{$attachmentData['file_name']}\"");
        }

        if (my_strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'msie 6.0') !== false) {
            header('Expires: -1');
        }

        header("Content-length: {$attachmentData['file_size']}");

        header('Content-range: bytes=0-' . ($attachmentData['file_size'] - 1) . '/' . $attachmentData['file_size']);

        $plugins->run_hooks('myshowcase_image');

        echo file_get_contents($filePath);

        exit;
    }
}