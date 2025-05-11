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

namespace MyShowcase\Controllers;

use JetBrains\PhpStorm\NoReturn;
use MyShowcase\Models\Comments as CommentsModel;
use MyShowcase\System\ModeratorPermissions;
use MyShowcase\System\Router;
use MyShowcase\System\UserPermissions;

use function MyShowcase\Core\commentsGet;
use function MyShowcase\Core\dataHandlerGetObject;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\SimpleRouter\url;

use const MyShowcase\ROOT;
use const MyShowcase\Core\ENTRY_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\ENTRY_STATUS_VISIBLE;
use const MyShowcase\Core\FILTER_TYPE_USER_ID;
use const MyShowcase\Core\URL_TYPE_COMMENT_CREATE;
use const MyShowcase\Core\URL_TYPE_COMMENT_UPDATE;
use const MyShowcase\Core\URL_TYPE_COMMENT_VIEW;
use const MyShowcase\Core\URL_TYPE_ENTRY_VIEW;
use const MyShowcase\Core\URL_TYPE_MAIN;
use const MyShowcase\Core\URL_TYPE_MAIN_USER;
use const MyShowcase\Core\COMMENT_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\COMMENT_STATUS_SOFT_DELETED;
use const MyShowcase\Core\COMMENT_STATUS_VISIBLE;
use const MyShowcase\Core\DATA_HANDLER_METHOD_UPDATE;

class Comments extends Base
{
    public function __construct(
        public ?Router $router = null,
        protected ?CommentsModel $commentsModel = null,
    ) {
        require_once ROOT . '/Models/Comments.php';

        $this->commentsModel = new CommentsModel();

        parent::__construct($router);
    }

    public function setEntry(
        string $entrySlug,
        bool $loadFields = false
    ) {
        require_once ROOT . '/Controllers/Entries.php';

        (new Entries())->setEntry($entrySlug, $loadFields);
    }

    public function updateEntryCommentsCount(): void
    {
        $statusVisible = COMMENT_STATUS_VISIBLE;

        $totalComments = $this->showcaseObject->commentGet(
            ["entry_id='{$this->showcaseObject->entryID}'", "status='{$statusVisible}'"],
            ['COUNT(comment_id) AS total_comments'],
            ['group_by' => 'entry_id', 'limit' => 1]
        )['total_comments'] ?? 0;

        $this->showcaseObject->dataUpdate(['comments' => $totalComments]);
    }

    #[NoReturn] public function viewComment(
        string $entrySlug,
        int $commentID
    ): void {
        if (!$this->showcaseObject->config['comments_allow'] || !$this->showcaseObject->userPermissions[UserPermissions::CanViewComments]) {
            error_no_permission();
        }

        require_once ROOT . '/Controllers/Entries.php';

        $entriesController = new Entries($this->router);

        $entriesController->setEntry($entrySlug, true);

        global $mybb;

        $currentUserID = (int)$mybb->user['uid'];

        $whereClauses = [
            "comment_id='{$commentID}'",
            "entry_id='{$this->showcaseObject->entryID}'",
            "showcase_id='{$this->showcaseObject->showcase_id}'"
        ];

        $statusVisible = COMMENT_STATUS_VISIBLE;

        $statusPendingApproval = COMMENT_STATUS_PENDING_APPROVAL;

        $statusSoftDeleted = COMMENT_STATUS_SOFT_DELETED;

        $whereClausesClauses = [
            "status='{$statusVisible}'",
            "user_id='{$currentUserID}' AND status='{$statusPendingApproval}'",
        ];

        if (ModeratorPermissions::CanManageEntries) {
            $whereClausesClauses[] = "status='{$statusPendingApproval}'";

            $whereClausesClauses[] = "status='{$statusSoftDeleted}'";
        }

        $whereClausesClauses = implode(' OR ', $whereClausesClauses);

        $whereClauses[] = "({$whereClausesClauses})";

        $commentData = commentsGet($whereClauses, ['dateline'], ['limit' => 1]);

        if (empty($commentData)) {
            error_no_permission();
        }

        $entriesController->viewEntry($entrySlug, commentID: $commentID, commentData: $commentData);
    }

    #[NoReturn] public function createComment(

        string $entrySlug,
        bool $isEditPage = false,
        int $commentID = 0
    ): void {
        global $mybb, $lang, $plugins;

        $hookArguments = [
            'this' => &$this,
            'isEditPage' => $isEditPage,
        ];

        $extractVariables = [];

        $hookArguments['extractVariables'] = &$extractVariables;

        $currentUserID = (int)$mybb->user['uid'];

        $this->setEntry($entrySlug, true);

        if (!$this->showcaseObject->config['comments_allow'] ||
            !$this->showcaseObject->entryID) {
            error_no_permission();
        }

        if ($isEditPage && !$this->showcaseObject->userPermissions[UserPermissions::CanUpdateComments] ||
            !$isEditPage && !$this->showcaseObject->userPermissions[UserPermissions::CanCreateComments]) {
            error_no_permission();
        }
        /*
                if ($isEditPage && empty($commentData) ||
                    !(
                        $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageComments] ||
                        ((int)$commentData['user_id'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteComments])/* ||
                        ((int)$this->showcaseObject->entryData['user_id'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteAuthorComments])*//*
            ) ||
            !$this->showcaseObject->entryID) {
            error_no_permission();
        }*/

        $plugins->run_hooks('myshowcase_add_comment_start');

        if ($mybb->request_method === 'post') {
            verify_post_check($mybb->get_input('my_post_key'));

            if ($isEditPage) {
                $dataHandler = dataHandlerGetObject($this->showcaseObject, DATA_HANDLER_METHOD_UPDATE);
            } else {
                $dataHandler = dataHandlerGetObject($this->showcaseObject);
            }

            $insertData = [
                'comment' => $mybb->get_input('comment'),
                'status' => COMMENT_STATUS_VISIBLE,
            ];

            if (!$isEditPage) {
                $insertData['user_id'] = $currentUserID;

                $insertData['ipaddress'] = $mybb->session->packedip;

                $insertData['dateline'] = TIME_NOW;
            }

            if ($isEditPage && (
                    $this->showcaseObject->config['moderate_comments_update'] ||
                    $this->showcaseObject->userPermissions[UserPermissions::ModerateCommentsUpdate]
                )) {
                $insertData['status'] = ENTRY_STATUS_PENDING_APPROVAL;
            } elseif (!$isEditPage && $this->showcaseObject->config['moderate_comments_create'] && (
                    $this->showcaseObject->config['moderate_comments_update'] ||
                    $this->showcaseObject->userPermissions[UserPermissions::ModerateCommentsCreate]
                )) {
                $insertData['status'] = ENTRY_STATUS_PENDING_APPROVAL;
            }

            $dataHandler->set_data($insertData);

            if (!$dataHandler->commentValidateData()) {
                $this->showcaseObject->errorMessages = array_merge(
                    $this->showcaseObject->errorMessages,
                    $dataHandler->get_friendly_errors()
                );
            }

            if (!$this->showcaseObject->errorMessages) {
                $plugins->run_hooks('myshowcase_add_comment_commit');

                if ($isEditPage) {
                    $dataHandler->commentUpdate($commentID);
                } else {
                    $insertResult = $dataHandler->commentInsert();

                    $commentID = $insertResult['comment_id'];
                }

                $this->updateEntryCommentsCount();

                if (isset($insertResult['status']) && $insertResult['status'] !== ENTRY_STATUS_VISIBLE) {
                    $entryUrl = url(
                        URL_TYPE_ENTRY_VIEW,
                        ['entry_slug' => $this->showcaseObject->entryData['entry_slug']]
                    )->getRelativeUrl();

                    redirect(
                        $entryUrl,
                        $isEditPage ? $lang->myShowcaseEntryCommentUpdatedStatus : $lang->myShowcaseEntryCommentCreatedStatus
                    );
                } else {
                    $commentUrl = url(
                            URL_TYPE_COMMENT_VIEW,
                            ['entry_slug' => $this->showcaseObject->entryData['entry_slug'], 'comment_id' => $commentID]
                        )->getRelativeUrl() . '#commentID' . $commentID;

                    redirect(
                        $commentUrl,
                        $isEditPage ? $lang->myShowcaseEntryCommentUpdated : $lang->myShowcaseEntryCommentCreated
                    );
                }
            }
        } elseif ($isEditPage) {
            $commentData = $this->commentsModel->getComment($commentID, ['comment']);

            $mybb->input = array_merge($commentData, $mybb->input);
        }

        global $theme;

        switch ($this->showcaseObject->config['filter_force_field']) {
            case FILTER_TYPE_USER_ID:
                $userData = get_user($this->showcaseObject->entryUserID);

                if (empty($userData['uid']) || empty($mybb->usergroup['canviewprofiles'])) {
                    error_no_permission();
                }

                $lang->load('member');

                $userName = htmlspecialchars_uni($userData['username']);

                add_breadcrumb(
                    $lang->sprintf($lang->nav_profile, $userName),
                    $mybb->settings['bburl'] . '/' . get_profile_link($userData['uid'])
                );

                $mainUrl = str_replace(
                    '/user/',
                    '/user/' . $this->showcaseObject->entryUserID,
                    url(URL_TYPE_MAIN_USER)->getRelativeUrl()
                );

                break;
            default:
                $mainUrl = url(URL_TYPE_MAIN, getParams: $this->showcaseObject->urlParams)->getRelativeUrl();
                break;
        }

        add_breadcrumb(
            $this->showcaseObject->config['name_friendly'],
            $mainUrl
        );

        $entrySubject = $this->renderObject->buildEntrySubject();

        $entryUrl = url(
            URL_TYPE_ENTRY_VIEW,
            ['entry_slug' => $this->showcaseObject->entryData['entry_slug']]
        )->getRelativeUrl();

        add_breadcrumb(
            $entrySubject,
            $entryUrl
        );

        if ($isEditPage) {
            add_breadcrumb(
                $lang->myShowcaseButtonCommentUpdate,
                $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlUpdateComment,
                    $this->showcaseObject->entryData['entry_slug'],
                    $commentID
                )
            );
        } else {
            add_breadcrumb(
                $lang->myShowcaseButtonCommentCreate,
                $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlCreateComment,
                    $this->showcaseObject->entryData['entry_slug']
                )
            );
        }

        $commentLengthLimitNote = $lang->sprintf(
            $lang->myshowcase_comment_text_limit,
            my_number_format($this->showcaseObject->config['comments_maximum_length'])
        );

        $alternativeBackground = alt_trow(true);

        if ($isEditPage) {
            $createUpdateUrl = url(
                URL_TYPE_COMMENT_UPDATE,
                ['entry_slug' => $this->showcaseObject->entryData['entry_slug'], 'comment_id' => $commentID]
            )->getRelativeUrl();
        } else {
            $createUpdateUrl = url(
                URL_TYPE_COMMENT_CREATE,
                ['entry_slug' => $this->showcaseObject->entryData['entry_slug']]
            )->getRelativeUrl();
        }

        $commentMessage = htmlspecialchars_uni($mybb->get_input('comment'));

        $hookArguments = hooksRun('comment_create_update_end', $hookArguments);

        extract($hookArguments['extractVariables']);

        $code_buttons = $smile_inserter = '';

        $this->renderObject->buildCommentsFormEditor($code_buttons, $smile_inserter);

        $this->outputSuccess(eval($this->renderObject->templateGet('pageEntryCommentCreateUpdateContents')));
    }

    #[NoReturn] public function updateComment(

        string $entrySlug,
        int $commentID
    ): void {
        $this->createComment($entrySlug, true, $commentID);
    }

    #[NoReturn] public function approveComment(

        string $entrySlug,
        int $commentID,
        int $status = COMMENT_STATUS_VISIBLE
    ): void {
        global $mybb, $lang;

        verify_post_check($mybb->get_input('my_post_key'));

        $this->setEntry($entrySlug);

        if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanManageComments] ||
            !$this->showcaseObject->entryID) {
            error_no_permission();
        }

        $dataHandler = dataHandlerGetObject($this->showcaseObject, DATA_HANDLER_METHOD_UPDATE);

        $dataHandler->set_data(['status' => $status]);

        if ($dataHandler->commentValidateData()) {
            $dataHandler->commentUpdate($commentID);

            $this->updateEntryCommentsCount();

            $commentUrl = url(
                    URL_TYPE_COMMENT_VIEW,
                    ['entry_slug' => $this->showcaseObject->entryData['entry_slug'], 'comment_id' => $commentID]
                )->getRelativeUrl() . '#commentID' . $commentID;

            switch ($status) {
                case COMMENT_STATUS_PENDING_APPROVAL:
                    $redirectMessage = $lang->myShowcaseEntryCommentUnapproved;
                    break;
                case COMMENT_STATUS_VISIBLE:
                    $redirectMessage = $lang->myShowcaseEntryCommentApproved;
                    break;
                case COMMENT_STATUS_SOFT_DELETED:
                    $redirectMessage = $lang->myShowcaseEntryCommentSoftDeleted;
                    break;
            }

            redirect($commentUrl, $redirectMessage);
        }
    }

    #[NoReturn] public function unapproveComment(

        string $entrySlug,
        int $commentID
    ): void {
        $this->approveComment(
            $entrySlug,
            $commentID,
            COMMENT_STATUS_PENDING_APPROVAL
        );
    }

    #[NoReturn] public function softDeleteComment(

        string $entrySlug,
        int $commentID
    ): void {
        $this->approveComment(
            $entrySlug,
            $commentID,
            COMMENT_STATUS_SOFT_DELETED
        );
    }

    #[NoReturn] public function restoreComment(

        string $entrySlug,
        int $commentID
    ): void {
        $this->approveComment(
            $entrySlug,
            $commentID
        );
    }

    #[NoReturn] public function deleteComment(

        string $entrySlug,
        int $commentID
    ): void {
        global $mybb, $lang, $plugins;

        $currentUserID = (int)$mybb->user['uid'];

        $this->setEntry($entrySlug);

        $commentData = $this->commentsModel->getComment($commentID, ['user_id']);

        if (empty($commentData) ||
            !(
                $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageComments] ||
                ((int)$commentData['user_id'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteComments])/* ||
                ((int)$this->showcaseObject->entryData['user_id'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteAuthorComments])*/
            ) ||
            !$this->showcaseObject->entryID) {
            error_no_permission();
        }

        $dataHandler = dataHandlerGetObject($this->showcaseObject);

        $dataHandler->commentDelete($commentID);

        $this->updateEntryCommentsCount();

        $entryUrl = url(
            URL_TYPE_ENTRY_VIEW,
            ['entry_slug' => $this->showcaseObject->entryData['entry_slug']]
        )->getRelativeUrl();

        redirect($entryUrl, $lang->myShowcaseEntryCommentDeleted);
    }
}