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

use function MyShowcase\Core\dataHandlerGetObject;
use function MyShowcase\Core\dataTableStructureGet;

use const MyShowcase\ROOT;
use const MyShowcase\Core\COMMENT_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\COMMENT_STATUS_SOFT_DELETED;
use const MyShowcase\Core\COMMENT_STATUS_VISIBLE;
use const MyShowcase\Core\DATA_HANDLERT_METHOD_UPDATE;
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
        int $entryID
    ) {
        $dataTableStructure = dataTableStructureGet($this->showcaseObject->id);

        $queryFields = array_merge(array_map(function (string $columnName): string {
            return 'entryData.' . $columnName;
        }, array_keys(DATA_TABLE_STRUCTURE['myshowcase_data'])), [
            'userData.username',
        ]);

        $queryTables = ['users userData ON (userData.uid=entryData.uid)'];

        $this->showcaseObject->entryDataSet(
            $this->showcaseObject->dataGet(["gid='{$entryID}'"], $queryFields, ['limit' => 1], $queryTables)
        );
    }

    public function updateEntryCommentsCount(): void
    {
        $statusVisible = COMMENT_STATUS_VISIBLE;

        $totalComments = $this->showcaseObject->commentGet(
            ["gid='{$this->showcaseObject->entryID}'", "status='{$statusVisible}'"],
            ['COUNT(cid) AS total_comments'],
            ['group_by' => 'gid', 'limit' => 1]
        )['total_comments'] ?? 0;

        $this->showcaseObject->dataUpdate(['comments' => $totalComments]);
    }

    public function viewComment(
        string $showcaseSlug,
        int $entryID,
        int $commentID
    ): void {
        require_once ROOT . '/Controllers/Entries.php';

        (new Entries($this->router))->viewEntry($showcaseSlug, $entryID);
    }

    #[NoReturn] public function createComment(
        string $showcaseSlug,
        int $entryID
    ): void {
        global $mybb, $lang, $plugins;

        $currentUserID = (int)$mybb->user['uid'];

        $this->setEntry($entryID);

        if (!$this->showcaseObject->allowComments ||
            !$this->showcaseObject->userPermissions[UserPermissions::CanCreateComments] ||
            !$this->showcaseObject->entryID ||
            !$this->showcaseObject->entryID) {
            error_no_permission();
        }

        verify_post_check($mybb->get_input('my_post_key'));

        $plugins->run_hooks('myshowcase_add_comment_start');

        $dataHandler = dataHandlerGetObject($this->showcaseObject);

        $dataHandler->setData([
            'uid' => $currentUserID,
            'ipaddress' => $mybb->session->packedip,
            'comment' => $mybb->get_input('comment'),
            'status' => COMMENT_STATUS_VISIBLE
        ]);

        if (!$dataHandler->commentValidateData()) {
            $this->showcaseObject->errorMessages = array_merge(
                $this->showcaseObject->errorMessages,
                $dataHandler->get_friendly_errors()
            );
        }

        if (!$this->showcaseObject->errorMessages) {
            $plugins->run_hooks('myshowcase_add_comment_commit');

            $commentID = $dataHandler->commentInsert();

            $this->updateEntryCommentsCount();

            $commentUrl = $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlViewComment,
                    $entryID,
                    $commentID
                ) . '#commentID' . $commentID;

            redirect($commentUrl, $lang->myShowcaseEntryCommentCreated);
        }

        require_once ROOT . '/Controllers/Entries.php';

        (new Entries($this->router))->viewEntry($showcaseSlug, $entryID);
    }

    #[NoReturn] public function approveComment(
        string $showcaseSlug,
        int $entryID,
        int $commentID,
        int $status = COMMENT_STATUS_VISIBLE
    ): void {
        global $mybb, $lang;

        verify_post_check($mybb->get_input('my_post_key'));

        $this->setEntry($entryID);

        if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveComments] ||
            !$this->showcaseObject->entryID) {
            error_no_permission();
        }

        $dataHandler = dataHandlerGetObject($this->showcaseObject, DATA_HANDLERT_METHOD_UPDATE);

        $dataHandler->setData(['status' => $status]);

        if ($dataHandler->commentValidateData()) {
            $dataHandler->commentUpdate($commentID);

            $this->updateEntryCommentsCount();

            $commentUrl = $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlViewComment,
                    $entryID,
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
        int $entryID,
        int $commentID
    ): void {
        $this->approveComment(
            $showcaseSlug,
            $entryID,
            $commentID,
            COMMENT_STATUS_PENDING_APPROVAL
        );
    }

    #[NoReturn] public function softDeleteComment(
        string $showcaseSlug,
        int $entryID,
        int $commentID
    ): void {
        $this->approveComment(
            $showcaseSlug,
            $entryID,
            $commentID,
            COMMENT_STATUS_SOFT_DELETED
        );
    }

    #[NoReturn] public function restoreComment(
        string $showcaseSlug,
        int $entryID,
        int $commentID
    ): void {
        $this->approveComment(
            $showcaseSlug,
            $entryID,
            $commentID
        );
    }

    #[NoReturn] public function deleteComment(
        string $showcaseSlug,
        int $entryID,
        int $commentID
    ): void {
        global $mybb, $lang, $plugins;

        $currentUserID = (int)$mybb->user['uid'];

        $this->setEntry($entryID);

        $commentData = $this->commentsModel->getComment($commentID, ['uid']);

        if (!$this->commentsModel->getComment($commentID) ||
            !(
                $this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteComments] ||
                ((int)$commentData['uid'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteComments]) ||
                ((int)$this->showcaseObject->entryData['uid'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteAuthorComments])
            ) ||
            !$this->showcaseObject->entryID) {
            error_no_permission();
        }

        $dataHandler = dataHandlerGetObject($this->showcaseObject);

        $dataHandler->commentDelete($commentID);

        $this->updateEntryCommentsCount();

        $entryUrl = $this->showcaseObject->urlBuild($this->showcaseObject->urlViewEntry, $entryID);

        redirect($entryUrl, $lang->myShowcaseEntryCommentDeleted);
    }
}