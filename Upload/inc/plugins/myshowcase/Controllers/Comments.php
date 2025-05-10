<?php

/***************************************************************************
 *
 *    ougc REST API plugin (/inc/plugins/ougc/RestApi/core.php)
 *    Author: Omar Gonzalez
 *    Copyright: © 2024 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    Implements a REST Api system to your forum.
 *
 ***************************************************************************
 ****************************************************************************
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

declare(strict_types=1);

namespace myshowcase\Controllers;

use JetBrains\PhpStorm\NoReturn;
use MyShowcase\Models\Comments as CommentsModel;
use MyShowcase\System\ModeratorPermissions;
use MyShowcase\System\Router;
use MyShowcase\System\UserPermissions;

use function MyShowcase\Core\commentsGet;
use function MyShowcase\Core\dataHandlerGetObject;
use function MyShowcase\Core\dataTableStructureGet;

use function MyShowcase\Core\hooksRun;

use const MyShowcase\Core\ENTRY_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\ENTRY_STATUS_VISIBLE;
use const MyShowcase\ROOT;
use const MyShowcase\Core\COMMENT_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\COMMENT_STATUS_SOFT_DELETED;
use const MyShowcase\Core\COMMENT_STATUS_VISIBLE;
use const MyShowcase\Core\DATA_HANDLER_METHOD_UPDATE;
use const MyShowcase\Core\DATA_TABLE_STRUCTURE;

class Comments extends Base
{
    public function __construct(
        public Router $router,
        protected ?CommentsModel $commentsModel = null,
    ) {
        require_once ROOT . '/Models/Comments.php';

        $this->commentsModel = new CommentsModel();

        parent::__construct($router);
    }

    public function setEntry(
        string $entrySlug
    ) {
        $dataTableStructure = dataTableStructureGet($this->showcaseObject->showcase_id);

        $queryFields = array_merge(
            array_map(function (string $columnName): string {
                return 'entryData.' . $columnName;
            }, array_keys(DATA_TABLE_STRUCTURE['myshowcase_data'])),
            [
                'userData.username',
            ]
        );

        $queryTables = ['users userData ON (userData.uid=entryData.user_id)'];

        $this->showcaseObject->entryDataSet(
            $this->showcaseObject->dataGet(["entry_slug='{$entrySlug}'"], $queryFields, ['limit' => 1], $queryTables)
        );
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
        string $showcaseSlug,
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

        $entriesController->viewEntry($showcaseSlug, $entrySlug, commentID: $commentID, commentData: $commentData);
    }

    #[NoReturn] public function createComment(
        string $showcaseSlug,
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

        $this->setEntry($entrySlug);

        if (!$this->showcaseObject->config['comments_allow'] ||
            !$this->showcaseObject->entryID) {
            error_no_permission();
        }

        if ($isEditPage && !$this->showcaseObject->userPermissions[UserPermissions::CanUpdateComments] ||
            !$isEditPage && !$this->showcaseObject->userPermissions[UserPermissions::CanCreateComments]) {
            error_no_permission();
        }

        if (empty($commentData) ||
            !(
                $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageComments] ||
                ((int)$commentData['user_id'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteComments])/* ||
                ((int)$this->showcaseObject->entryData['user_id'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteAuthorComments])*/
            ) ||
            !$this->showcaseObject->entryID) {
            error_no_permission();
        }

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
                'dateline' => TIME_NOW
            ];

            if (!$isEditPage) {
                $insertData['user_id'] = $currentUserID;

                $insertData['ipaddress'] = $mybb->session->packedip;
            }

            if ($isEditPage && $this->showcaseObject->config['moderate_comments_update']) {
                $insertData['status'] = ENTRY_STATUS_PENDING_APPROVAL;
            } elseif (!$isEditPage && $this->showcaseObject->config['moderate_comments_create']) {
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
                    $entryUrl = $this->showcaseObject->urlGetEntry();

                    redirect(
                        $entryUrl,
                        $isEditPage ? $lang->myShowcaseEntryCommentUpdatedStatus : $lang->myShowcaseEntryCommentCreatedStatus
                    );
                } else {
                    $commentUrl = $this->showcaseObject->urlBuild(
                            $this->showcaseObject->urlViewComment,
                            $this->showcaseObject->entryData['entry_slug'],
                            $commentID
                        ) . '#commentID' . $commentID;

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

        add_breadcrumb(
            $this->showcaseObject->config['name_friendly'],
            $this->showcaseObject->urlBuild($this->showcaseObject->urlMain)
        );

        $entrySubject = $this->renderObject->buildEntrySubject();

        add_breadcrumb(
            $entrySubject,
            $this->showcaseObject->urlBuild(
                $this->showcaseObject->urlViewEntry,
                $this->showcaseObject->entryData['entry_slug']
            )
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
            $urlCommentCreateUpdate = $this->showcaseObject->urlBuild(
                $this->showcaseObject->urlUpdateComment,
                $this->showcaseObject->entryData['entry_slug'],
                $commentID
            );
        } else {
            $urlCommentCreateUpdate = $this->showcaseObject->urlBuild(
                $this->showcaseObject->urlCreateComment,
                $this->showcaseObject->entryData['entry_slug'],
            );
        }

        $commentMessage = htmlspecialchars_uni($mybb->get_input('comment'));

        $hookArguments = hooksRun('comment_create_update_end', $hookArguments);

        extract($hookArguments['extractVariables']);

        $code_buttons = $smile_inserter = '';

        $this->renderObject->buildCommentsFormEditor($code_buttons, $smile_inserter);

        $this->outputSuccess(eval($this->renderObject->templateGet('pageEntryCommentCreateUpdateContents')));
    }

    #[NoReturn] public function updateComment(
        string $showcaseSlug,
        string $entrySlug,
        int $commentID
    ): void {
        $this->createComment($showcaseSlug, $entrySlug, true, $commentID);
    }

    #[NoReturn] public function approveComment(
        string $showcaseSlug,
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

            $commentUrl = $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlViewComment,
                    $this->showcaseObject->entryData['entry_slug'],
                    $commentID
                ) . '#commentID' . $commentID;

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
        string $showcaseSlug,
        string $entrySlug,
        int $commentID
    ): void {
        $this->approveComment(
            $showcaseSlug,
            $entrySlug,
            $commentID,
            COMMENT_STATUS_PENDING_APPROVAL
        );
    }

    #[NoReturn] public function softDeleteComment(
        string $showcaseSlug,
        string $entrySlug,
        int $commentID
    ): void {
        $this->approveComment(
            $showcaseSlug,
            $entrySlug,
            $commentID,
            COMMENT_STATUS_SOFT_DELETED
        );
    }

    #[NoReturn] public function restoreComment(
        string $showcaseSlug,
        string $entrySlug,
        int $commentID
    ): void {
        $this->approveComment(
            $showcaseSlug,
            $entrySlug,
            $commentID
        );
    }

    #[NoReturn] public function deleteComment(
        string $showcaseSlug,
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

        $entryUrl = $this->showcaseObject->urlBuild(
            $this->showcaseObject->urlViewEntry,
            $this->showcaseObject->entryData['entry_slug']
        );

        redirect($entryUrl, $lang->myShowcaseEntryCommentDeleted);
    }
}