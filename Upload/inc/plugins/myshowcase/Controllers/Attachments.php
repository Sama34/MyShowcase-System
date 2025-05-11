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

use MyBB;
use JetBrains\PhpStorm\NoReturn;
use MyShowcase\Models\Entries as EntriesModel;
use MyShowcase\System\FieldHtmlTypes;
use MyShowcase\System\ModeratorPermissions;
use MyShowcase\System\Router;
use MyShowcase\System\UserPermissions;

use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\attachmentUpdate;
use function MyShowcase\Core\cacheGet;
use function MyShowcase\Core\dataTableStructureGet;
use function MyShowcase\Core\hooksRun;

use const MyShowcase\ROOT;
use const MyShowcase\Core\ATTACHMENT_STATUS_VISIBLE;
use const MyShowcase\Core\CACHE_TYPE_ATTACHMENT_TYPES;
use const MyShowcase\Core\DATA_TABLE_STRUCTURE;
use const MyShowcase\Core\ENTRY_STATUS_VISIBLE;
use const MyShowcase\Core\TABLES_DATA;

class Attachments extends Base
{
    public function __construct(
        public ?Router $router = null,
        protected ?EntriesModel $entriesModel = null,
    ) {
        require_once ROOT . '/Models/Entries.php';

        $this->entriesModel = new EntriesModel();

        parent::__construct($router);
    }

    public function setEntry(
        string $entrySlug,
        bool $loadFields = false
    ): void {
        global $db;

        $dataTableStructure = dataTableStructureGet($this->showcaseObject->showcase_id);

        $whereClauses = ["entryData.entry_slug='{$db->escape_string($entrySlug)}'"];

        $queryFields = array_merge(
            array_map(function (string $columnName): string {
                return 'entryData.' . $columnName;
            }, array_keys(DATA_TABLE_STRUCTURE['myshowcase_data'])),
            [
                'userData.username',
            ]
        );

        $queryTables = ['users userData ON (userData.uid=entryData.user_id)'];

        if ($loadFields) {
            foreach ($this->showcaseObject->fieldSetCache as $fieldID => $fieldData) {
                $fieldKey = $fieldData['field_key'];

                $htmlType = $fieldData['html_type'];

                $fieldID = (int)$fieldData['field_id'];

                if ($htmlType === FieldHtmlTypes::SelectSingle || $htmlType === FieldHtmlTypes::Radio) {
                    $queryTables[] = "myshowcase_field_data table_{$fieldKey} ON (table_{$fieldKey}.field_data_id=entryData.{$fieldKey} AND table_{$fieldKey}.field_id='{$fieldID}')";

                    //$queryFields[] = "table_{$fieldKey}.value AS {$fieldKey}";

                    $queryFields[] = "table_{$fieldKey}.display_style AS {$fieldKey}";

                    // todo, I don't understand the purpose of this now
                    // the condition after OR seems to fix it for now
                    //$whereClauses[] = "(table_{$fieldKey}.set_id='{$this->showcaseObject->config['field_set_id']}' OR entryData.{$fieldKey}=0)";
                } else {
                    $queryFields[] = $fieldKey;
                }
            }
        }

        $this->showcaseObject->entryDataSet(
            $this->showcaseObject->dataGet($whereClauses, $queryFields, ['limit' => 1], $queryTables)
        );
    }

    #[NoReturn] public function viewAttachment(
        string $entrySlug,
        string $attachmentHash
    ): void {
        $this->viewThumbnail($entrySlug, $attachmentHash, false);
    }

    #[NoReturn] public function viewThumbnail(
        string $entrySlug,
        string $attachmentHash,
        bool $isThumbnail = true,
    ): void {
        global $mybb, $db, $theme;
        global $lang;

        $lang->load('messages');

        $currentUserID = (int)$mybb->user['uid'];

        $entryData = $this->showcaseObject->dataGet(
            ["entry_slug='{$db->escape_string($entrySlug)}'"],
            ['user_id', 'entry_hash', 'status'],
            queryOptions: ['limit' => 1]
        );

        $this->showcaseObject->entryDataSet($entryData);

        $tableFields = TABLES_DATA['myshowcase_attachments'];

        $whereClauses = [
            "showcase_id='{$this->showcaseObject->showcase_id}'",
            "entry_id='{$this->showcaseObject->entryID}'",
            "attachment_hash='{$db->escape_string($attachmentHash)}'",
        ];

        if ($isThumbnail) {
            $whereClauses[] = "thumbnail_name!=''";
        }

        $attachmentData = attachmentGet(
            $whereClauses,
            array_keys($tableFields),
            queryOptions: ['limit' => 1]
        );

        $hookArguments = [
            'this' => &$this,
            'attachmentData' => &$attachmentData,
            'isThumbnail' => $isThumbnail
        ];

        $hookArguments = hooksRun('attachment_thumbnail_start', $hookArguments);

        if (empty($attachmentData['attachment_name'])) {
            $this->error($lang->error_invalidattachment);
        }

        $attachmentID = (int)$attachmentData['attachment_id'];

        $attachmentUserID = (int)$attachmentData['user_id'];

        if (
            !$this->showcaseObject->userPermissions[UserPermissions::CanView] ||
            !$this->showcaseObject->userPermissions[UserPermissions::CanViewEntries] ||
            (
                $currentUserID !== $attachmentUserID &&
                !$this->showcaseObject->userPermissions[UserPermissions::CanViewAttachments]
            )
        ) {
            $this->error($lang->error_invalidattachment);
        }

        $attachmentTypes = cacheGet(CACHE_TYPE_ATTACHMENT_TYPES)[$this->showcaseObject->showcase_id] ?? [];

        $attachmentFileExtension = my_strtolower(get_extension($attachmentData['attachment_name']));

        $attachmentMimeType = my_strtolower($attachmentData['mime_type']);

        $attachmentType = false;

        foreach ($attachmentTypes as $attachmentTypeID => $attachmentTypeData) {
            if ($attachmentTypeData['file_extension'] === $attachmentFileExtension &&
                $attachmentTypeData['mime_type'] === $attachmentMimeType) {
                $attachmentType = $attachmentTypeData;

                break;
            }
        }

        if ($attachmentType === false) {
            $this->error($lang->error_invalidattachment);
        }

        $attachmentStatus = (int)$attachmentData['status'];

        // Don't check the permissions on preview
        if ($currentUserID !== $attachmentUserID) {
            if (!$this->showcaseObject->userPermissions[UserPermissions::CanDownloadAttachments] && !$isThumbnail) {
                $this->errorNoPermission();
            }

            // Error if attachment is invalid or not visible
            if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanManageAttachments] &&
                (
                    $attachmentStatus !== ATTACHMENT_STATUS_VISIBLE ||
                    (int)$this->showcaseObject->entryData['status'] !== ENTRY_STATUS_VISIBLE
                )
            ) {
                $this->error($lang->error_invalidattachment);
            }
        }

        // Only increment the download count if this is not a thumbnail
        if (!$isThumbnail && $currentUserID !== $attachmentUserID) {
            if (!is_member($attachmentType['allowed_groups'])) {
                $this->errorNoPermission();
            }

            attachmentUpdate(['downloads' => $attachmentData['downloads'] + 1,], $attachmentID);
        }

        // basename isn't UTF-8 safe. This is a workaround.
        $attachmentName = ltrim(basename(' ' . $attachmentData['attachment_name']));

        $absoluteUploadsPath = mk_path_abs($this->showcaseObject->config['attachments_uploads_path']);

        $hookArguments = hooksRun('attachment_thumbnail_end', $hookArguments);

        if ($isThumbnail) {
            if (!file_exists($absoluteUploadsPath . '/' . $attachmentData['thumbnail_name'])) {
                $this->error($lang->error_invalidattachment);
            }

            $imageType = match ($attachmentFileExtension) {
                'gif' => 'image/gif',
                'bmp' => 'image/bmp',
                'png' => 'image/png',
                'jpg', 'jpeg', 'jpe' => 'image/jpeg',
                default => 'image/unknown',
            };

            header("Content-disposition: filename=\"{$attachmentName}\"");

            header('Content-type: ' . $imageType);

            header('Content-length: ' . filesize($absoluteUploadsPath . '/' . $attachmentData['thumbnail_name']));

            $handle = fopen($absoluteUploadsPath . '/' . $attachmentData['thumbnail_name'], 'rb');

            while (!feof($handle)) {
                echo fread($handle, 8192);
            }

            fclose($handle);
        } else {
            if (!file_exists($absoluteUploadsPath . '/' . $attachmentData['file_name'])) {
                $this->error($lang->error_invalidattachment);
            }

            switch ($attachmentMimeType) {
                case 'application/pdf':
                case 'image/bmp':
                case 'image/gif':
                case 'image/jpeg':
                case 'image/pjpeg':
                case 'image/png':
                case 'text/plain':
                    header("Content-type: {$attachmentMimeType}");

                    if (!empty($attachmentType['force_download'])) {
                        $disposition = 'attachment';
                    } else {
                        $disposition = 'inline';
                    }

                    break;

                default:
                    $filetype = $attachmentMimeType;

                    if (!$filetype) {
                        $filetype = 'application/force-download';
                    }

                    header("Content-type: {$filetype}");

                    $disposition = 'attachment';
            }

            if (str_contains(strtolower($_SERVER['HTTP_USER_AGENT']), 'msie')) {
                header("Content-disposition: attachment; filename=\"{$attachmentName}\"");
            } else {
                header("Content-disposition: {$disposition}; filename=\"{$attachmentName}\"");
            }

            if (str_contains(strtolower($_SERVER['HTTP_USER_AGENT']), 'msie 6.0')) {
                header('Expires: -1');
            }

            header("Content-length: {$attachmentData['file_size']}");

            header('Content-range: bytes=0-' . ($attachmentData['file_size'] - 1) . '/' . $attachmentData['file_size']);

            $handle = fopen($absoluteUploadsPath . '/' . $attachmentData['file_name'], 'rb');

            while (!feof($handle)) {
                echo fread($handle, 8192);
            }

            fclose($handle);
        }

        exit;
    }

    #[NoReturn] public function error(string $error = '', string $title = ''): void
    {
        global $mybb, $lang;

        $error ??= $lang->unknown_error;

        if ($mybb->get_input('ajax', MyBB::INPUT_INT)) {
            header("Content-type: application/json; charset={$lang->settings['charset']}");

            echo json_encode(['errors' => [$error]]);

            exit;
        }

        exit($error);
    }

    #[NoReturn] public function errorNoPermission(): void
    {
        global $mybb, $lang;

        if ($mybb->get_input('ajax', MyBB::INPUT_INT)) {
            header("Content-type: application/json; charset={$lang->settings['charset']}");

            echo json_encode(['errors' => [$lang->error_nopermission_user_ajax]]);

            exit;
        }

        $this->error($lang->error_nopermission_guest_1);
    }
}