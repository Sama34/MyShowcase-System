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

use DataHandler as CoreDataHandler;

use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\attachmentUpdate;
use function MyShowcase\Core\commentInsert;
use function MyShowcase\Core\commentUpdate;
use function MyShowcase\Core\fieldTypeMatchChar;
use function MyShowcase\Core\fieldTypeMatchInt;
use function MyShowcase\Core\fieldTypeMatchText;
use function MyShowcase\Core\hooksRun;

use const MyShowcase\Core\COMMENT_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\COMMENT_STATUS_SOFT_DELETED;
use const MyShowcase\Core\COMMENT_STATUS_VISIBLE;
use const MyShowcase\Core\DATA_HANDLER_METHOD_INSERT;
use const MyShowcase\Core\DATA_TABLE_STRUCTURE;
use const MyShowcase\Core\ENTRY_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\ENTRY_STATUS_SOFT_DELETED;
use const MyShowcase\Core\ENTRY_STATUS_VISIBLE;

/**
 * MyShowcase handling class, provides common structure to handle post data.
 *
 */
class DataHandler extends CoreDataHandler
{
    public function __construct(
        protected Showcase $showcaseObject,
        public $method = DATA_HANDLER_METHOD_INSERT,
        /**
         * The language file used in the data handler.
         *
         * @var string
         */
        public string $language_file = 'datahandler_myshowcase',
        /**
         * The prefix for the language variables used in the data handler.
         *
         * @var string
         */
        public $language_prefix = 'myshowcasedata',
        /**
         * What are we performing?
         * new = New showcase entry
         * edit = Editing an entry
         */
        public string $action = '',
        /**
         * Array of data inserted in to a showcase.
         *
         * @var array
         */
        public array $insertData = [],
        /**
         * Array of data used to update a showcase.
         *
         * @var array
         */
        public array $updateData = [],
        public int $entry_id = 0,
        public int $comment_id = 0,
        public array $returnData = [],
    ) {
        $hookArguments = [
            'dataHandler' => &$this,
        ];

        parent::__construct($method);

        $hookArguments = hooksRun('data_handler_construct_end', $hookArguments);
    }

    /**
     * Validate a showcase.
     *
     * @return bool True when valid, false when invalid.
     */
    public function entryValidate(): bool
    {
        $hookArguments = [
            'dataHandler' => &$this,
        ];

        $hookArguments = hooksRun('data_handler_entry_validate_start', $hookArguments);

        $entryData = $this->data;

        if (isset($entryData['entry_id']) && (int)$entryData['entry_id'] !== $this->showcaseObject->entryID) {
            $this->set_error('invalid entry identifier');
        }

        if (isset($entryData['entry_slug']) || $this->method === DATA_HANDLER_METHOD_INSERT) {
            $slugLength = my_strlen($this->data['entry_slug']);

            if ($slugLength < 1) {
                $this->set_error('the slug is too short');
            }

            if ($slugLength > DATA_TABLE_STRUCTURE['myshowcase_data']['entry_slug']['size']) {
                $this->set_error('the slug is too large');
            }
        }

        if (!empty($entryData['user_id']) && empty(get_user($this->data['user_id'])['uid'])) {
            $this->set_error('invalid user identifier');
        }

        if (isset($entryData['views']) && $entryData['views'] < 0) {
            $this->set_error('invalid views count');
        }

        if (isset($entryData['comments']) && $entryData['comments'] < 0) {
            $this->set_error('invalid comments count');
        }

        if (isset($entryData['status']) && !in_array(
                $this->data['status'],
                [
                    ENTRY_STATUS_PENDING_APPROVAL,
                    ENTRY_STATUS_VISIBLE,
                    ENTRY_STATUS_SOFT_DELETED
                ]
            )) {
            $this->set_error('invalid status');
        }

        if (!empty($entryData['moderator_user_id']) && empty(get_user($this->data['moderator_user_id'])['uid'])) {
            $this->set_error('invalid moderator identifier');
        }

        if (isset($entryData['dateline']) && $entryData['dateline'] < 0) {
            $this->set_error('invalid create stamp');
        }

        if (isset($entryData['edit_stamp']) && $entryData['edit_stamp'] < 0) {
            $this->set_error('invalid edit stamp');
        }

        global $lang;

        foreach ($this->showcaseObject->fieldSetCache as $fieldID => $fieldData) {
            $fieldKey = $fieldData['field_key'];

            if (!$fieldData['enabled'] || !isset($entryData[$fieldKey])) {
                continue;
            }

            if ($fieldData['is_required']) {
                if (empty($entryData[$fieldKey])) {
                    $this->set_error('missing_field', [$lang->{'myshowcase_field_' . $fieldKey} ?? $fieldKey]);
                } elseif ($fieldData['html_type'] === FieldHtmlTypes::SelectSingle && empty($entryData[$fieldKey])) {
                    $this->set_error('missing_field', [$lang->{'myshowcase_field_' . $fieldKey} ?? $fieldKey]);
                }
            }

            $fieldValueLength = my_strlen($entryData[$fieldKey]);

            if (fieldTypeMatchInt($fieldData['field_type'])) {
                if (isset($entryData[$fieldKey]) && !is_numeric($entryData[$fieldKey])) {
                    $this->set_error('invalid_type', [$lang->{'myshowcase_field_' . $fieldKey} ?? $fieldKey]);
                }
            } elseif (fieldTypeMatchChar($fieldData['field_type']) ||
                fieldTypeMatchText($fieldData['field_type'])) {
                if (isset($entryData[$fieldKey]) && !is_scalar($entryData[$fieldKey])) {
                    $this->set_error('invalid_type', [$lang->{'myshowcase_field_' . $fieldKey} ?? $fieldKey]);
                }

                //date fields do not have length limitations since the min/max are settings for the year
                if ($fieldData['html_type'] !== FieldHtmlTypes::Date) {
                    if ($fieldValueLength > $fieldData['maximum_length'] ||
                        $fieldValueLength < $fieldData['minimum_length']) {
                        $this->set_error(
                            'invalid_length',
                            [
                                $lang->{'myshowcase_field_' . $fieldKey} ?? $fieldKey,
                                $fieldValueLength,
                                $fieldData['minimum_length'] . '-' . $fieldData['maximum_length']
                            ]
                        );

                        if ($fieldValueLength > $this->showcaseObject->config['maximum_text_field_length'] &&
                            $this->showcaseObject->config['maximum_text_field_length'] > 0) {
                            $this->set_error(
                                'message_too_long',
                                [
                                    $lang->{'myshowcase_field_' . $fieldKey} ?? $fieldKey,
                                    $fieldValueLength,
                                    $this->showcaseObject->config['maximum_text_field_length']
                                ]
                            );
                        }
                    }
                }
            }
        }

        $this->set_validated();

        $hookArguments = hooksRun('data_handler_entry_validate_end', $hookArguments);

        if ($this->get_errors()) {
            return false;
        }

        return true;
    }

    /**
     * Insert a showcase into the database.
     *
     * @return array Array of new showcase details, entry_id and visibility.
     */
    public function entryInsert(bool $isUpdate = false): array
    {
        global $db;

        $hookArguments = [
            'dataHandler' => &$this,
            'isUpdate' => &$isUpdate,
        ];

        $entryData = &$this->data;

        if (!$this->get_validated()) {
            die('The entry needs to be validated before inserting it into the DB.');
        }

        if (count($this->get_errors()) > 0) {
            die('The entry is not valid.');
        }

        foreach ($entryData as $key => $value) {
            $this->insertData[$key] = $value;
        }

        $hookArguments = hooksRun('data_handler_entry_insert_update_start', $hookArguments);

        if ($isUpdate) {
            $this->entry_id = $this->showcaseObject->dataUpdate($this->insertData);
        } else {
            $this->entry_id = $this->showcaseObject->dataInsert($this->insertData);
        }

        // Assign any uploaded attachments with the specific entry_hash to the newly created entry.
        if (isset($entryData['entry_hash'])) {
            foreach (
                attachmentGet(
                    ["entry_hash='{$db->escape_string($entryData['entry_hash'])}'"]
                ) as $attachmentID => $attachmentData
            ) {
                attachmentUpdate(['entry_id' => $this->entry_id], $attachmentID);
            }
        }

        $this->returnData['entry_id'] = $this->entry_id;

        if (isset($this->insertData['entry_slug'])) {
            $this->returnData['entry_slug'] = $entryData['entry_slug'];
        } else {
            $this->returnData['entry_slug'] = $this->showcaseObject->dataGet(
                ["entry_id='{$this->entry_id}'"],
                ['entry_slug'],
                ['limit' => 1]
            )['entry_slug'] ?? '';
        }

        if (isset($this->insertData['status'])) {
            $this->returnData['status'] = $entryData['status'];
        }

        $hookArguments = hooksRun('data_handler_entry_insert_update_end', $hookArguments);

        return $this->returnData;
    }

    /**
     * Updates a showcase that is already in the database.
     *
     */
    public function updateEntry(): array
    {
        return $this->entryInsert(true);
    }

    public function commentValidate(): bool
    {
        $hookArguments = [
            'dataHandler' => &$this,
        ];

        $hookArguments = hooksRun('data_handler_comment_validate_start', $hookArguments);

        $commentData = $this->data;

        if (isset($commentData['showcase_id']) && (int)$commentData['showcase_id'] !== $this->showcaseObject->showcase_id) {
            $this->set_error('invalid showcase identifier');
        }

        if (isset($commentData['entry_id']) && (int)$commentData['entry_id'] !== $this->showcaseObject->entryID) {
            $this->set_error('invalid entry identifier');
        }

        if (!empty($commentData['user_id']) && empty(get_user($this->data['user_id'])['uid'])) {
            $this->set_error('invalid user identifier');
        }

        if (isset($commentData['comment']) || $this->method === DATA_HANDLER_METHOD_INSERT) {
            $commentLength = my_strlen($this->data['comment']);

            if ($commentLength < $this->showcaseObject->config['comments_minimum_length']) {
                $this->set_error('the message is too short');
            }

            if ($commentLength > $this->showcaseObject->config['comments_maximum_length']) {
                $this->set_error('the message is too large');
            }
        }

        if (isset($commentData['status']) && !in_array(
                $this->data['status'],
                [
                    COMMENT_STATUS_PENDING_APPROVAL,
                    COMMENT_STATUS_VISIBLE,
                    COMMENT_STATUS_SOFT_DELETED
                ]
            )) {
            $this->set_error('invalid status');
        }

        if (!empty($commentData['moderator_user_id']) && empty(get_user($this->data['moderator_user_id'])['uid'])) {
            $this->set_error('invalid moderator identifier');
        }

        $this->set_validated();

        $hookArguments = hooksRun('data_handler_comment_validate_end', $hookArguments);

        if ($this->get_errors()) {
            return false;
        }

        return true;
    }

    public function commentInsert(bool $isUpdate = false, int $commentID = 0): array
    {
        global $db;

        $hookArguments = [
            'dataHandler' => &$this,
            'isUpdate' => &$isUpdate,
            'commentID' => &$commentID,
        ];

        $commentData = &$this->data;

        if (!$this->get_validated()) {
            die('The comment needs to be validated before inserting it into the DB.');
        }

        if (count($this->get_errors()) > 0) {
            die('The comment is not valid.');
        }

        foreach ($commentData as $key => $value) {
            $this->insertData[$key] = $value;
        }

        $hookArguments = hooksRun('data_handler_comment_insert_update_start', $hookArguments);

        if ($isUpdate) {
            commentUpdate($this->insertData, $commentID);

            $this->comment_id = $commentID;
        } else {
            $this->comment_id = commentInsert(
                array_merge($this->insertData, [
                    'showcase_id' => $this->showcaseObject->showcase_id,
                    'entry_id' => $this->showcaseObject->entryID
                ])
            );
        }

        // Assign any uploaded attachments with the specific comment_hash to the newly created comment.
        if (isset($commentData['comment_hash'])) {
            foreach (
                attachmentGet(
                    ["comment_hash='{$db->escape_string($commentData['comment_hash'])}'"]
                ) as $attachmentID => $attachmentData
            ) {
                attachmentUpdate(['comment_id' => $this->comment_id], $attachmentID);
            }
        }

        $this->returnData['comment_id'] = $this->comment_id;

        if (isset($this->insertData['status'])) {
            $this->returnData['status'] = $commentData['status'];
        }

        $hookArguments = hooksRun('data_handler_comment_insert_update_end', $hookArguments);

        return $this->returnData;
    }

    public function commentUpdate(int $commentID): array
    {
        return $this->commentInsert(true, $commentID);
    }
}