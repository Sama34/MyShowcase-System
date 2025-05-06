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

use DataHandler as CoreDataHandler;

use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\attachmentUpdate;
use function MyShowcase\Core\cacheGet;
use function MyShowcase\Core\cacheUpdate;
use function MyShowcase\Core\commentInsert;
use function MyShowcase\Core\commentsDelete;
use function MyShowcase\Core\commentUpdate;
use function MyShowcase\Core\entryDataInsert;
use function MyShowcase\Core\entryDataUpdate;

use const MyShowcase\Core\CACHE_TYPE_PERMISSIONS;
use const MyShowcase\Core\COMMENT_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\COMMENT_STATUS_SOFT_DELETED;
use const MyShowcase\Core\COMMENT_STATUS_VISIBLE;
use const MyShowcase\Core\DATA_HANDLERT_METHOD_INSERT;
use const MyShowcase\Core\DATA_TABLE_STRUCTURE;
use const MyShowcase\Core\ENTRY_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\ENTRY_STATUS_SOFT_DELETED;
use const MyShowcase\Core\ENTRY_STATUS_VISIBLE;
use const MyShowcase\Core\FIELD_TYPE_HTML_DATE;
use const MyShowcase\Core\FIELD_TYPE_HTML_DB;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_INT;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_TEXT;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_VARCHAR;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

/**
 * MyShowcase handling class, provides common structure to handle post data.
 *
 */
class DataHandler extends CoreDataHandler
{
    public function __construct(
        protected Showcase $showcaseObject,
        public $method = DATA_HANDLERT_METHOD_INSERT,
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
        public array $myshowcase_insert_data = [],
        /**
         * Array of data used to update a showcase.
         *
         * @var array
         */
        public array $myshowcase_update_data = [],
    ) {
        parent::__construct($method);
    }


    /**
     * Validate a showcase.
     *
     * @return bool True when valid, false when invalid.
     */
    public function validate_showcase(): bool
    {
        global $lang;

        $myshowcase_data = &$this->data;

        //get this myshowcase's permissions
        $permcache = cacheGet(CACHE_TYPE_PERMISSIONS);
        if (!is_array($permcache[1])) {
            cacheUpdate(CACHE_TYPE_PERMISSIONS);
            $permcache = cacheGet(CACHE_TYPE_PERMISSIONS);
        }

        // Don't have a user ID at all - not good or guest is posting which usually is not allowed but check anyway.
        if (!isset($myshowcase_data['user_id']) || ($myshowcase_data['user_id'] == 0 && ((!$permcache[1][UserPermissions::CanAddEntries] && $this->action = 'new') || (!$permcache[1][UserPermissions::CanEditEntries] && $this->action = 'edit')))) {
            $this->set_error('invalid_user_id');
        } // If we have a user id but no username then fetch the username.
        elseif (!get_user($myshowcase_data['user_id'])) {
            $this->set_error('invalid_user_id');
        }

        //run through all fields checking defined requirements
        foreach ($this->fieldSetCache as $fieldID => $fieldData) {
            $fname = $fieldData['name'];
            $temp = 'myshowcase_field_' . $fname;
            $field_header = $lang->$temp;

            //verify required
            if ($fieldData['is_required'] == 1) {
                if (my_strlen($myshowcase_data[$fname]) == 0 || !isset($myshowcase_data[$fname])) {
                    $this->set_error('missing_field', [$field_header]);
                } elseif ($fieldData['html_type'] == FIELD_TYPE_HTML_DB && $myshowcase_data[$fname] == 0) {
                    $this->set_error('missing_field', [$field_header]);
                }
            }

            if ($fieldData['enabled'] == 1 || $fieldData['is_required'] == 1) //added require check in case admin sets require but not enable
            {
                //verify type and lengths
                switch ($fieldData['field_type']) {
                    //numbers lumped together
                    case FIELD_TYPE_STORAGE_INT:
                    case 'timestamp':
                        if (!is_numeric($myshowcase_data[$fname]) && $myshowcase_data[$fname] != '') {
                            $this->set_error('invalid_type', [$field_header]);
                        }

                    //numbers and simple text need length checked so no break
                    case FIELD_TYPE_STORAGE_VARCHAR:
                        //date fields do not have length limitations since the min/max are settings for the year
                        if ($fieldData['html_type'] != FIELD_TYPE_HTML_DATE) {
                            if (my_strlen(strval($myshowcase_data[$fname])) > $fieldData['maximum_length'] || my_strlen(
                                    strval($myshowcase_data[$fname])
                                ) < $fieldData['minimum_length']) {
                                $this->set_error(
                                    'invalid_length',
                                    [
                                        $field_header,
                                        my_strlen(strval($myshowcase_data[$fname])),
                                        $fieldData['minimum_length'] . '-' . $fieldData['maximum_length']
                                    ]
                                );
                            }
                        }
                        break;

                    //text all on its own since its for text areas
                    case FIELD_TYPE_STORAGE_TEXT:
                        if (my_strlen(
                                $myshowcase_data[$fname]
                            ) > $this->showcaseObject->maximumLengthForTextFields && $this->showcaseObject->maximumLengthForTextFields > 0) {
                            $this->set_error(
                                'message_too_long',
                                [
                                    $field_header,
                                    my_strlen($myshowcase_data[$fname]),
                                    $this->showcaseObject->maximumLengthForTextFields
                                ]
                            );
                        }

                        if (my_strlen(strval($myshowcase_data[$fname])) < $fieldData['minimum_length']) {
                            $this->set_error(
                                'invalid_length_min',
                                [
                                    $field_header,
                                    my_strlen(strval($myshowcase_data[$fname])),
                                    $fieldData['minimum_length']
                                ]
                            );
                        }
                        break;
                }
            }

            //escape the input (since validation above already checked for forced numeric, we can escape everything)
            //$myshowcase_data[$fname] = $db->escape_string($myshowcase_data[$fname]);
        }

        //$plugins->run_hooks('datahandler_post_validate_post', $this);

        // We are done validating, return.
        $this->set_validated(true);
        if (count($this->get_errors()) > 0) {
            return false;
        } else {
            return true;
        }
    }


    /**
     * Insert a showcase into the database.
     *
     * @return array Array of new showcase details, entry_id and visibility.
     */
    public function insert_showcase(): array
    {
        global $db, $mybb, $plugins, $cache, $lang;

        $myshowcase_data = &$this->data;

        // Yes, validating is required.
        if (!$this->get_validated()) {
            die('The myshowcase needs to be validated before inserting it into the DB.');
        }
        if (count($this->get_errors()) > 0) {
            die('The myshowcase is not valid.');
        }

        foreach ($myshowcase_data as $key => $value) {
            $this->myshowcase_insert_data[$key] = $value;
        }
        $plugins->run_hooks('datahandler_myshowcase_insert', $this);

        $this->entry_id = entryDataInsert($this->showcaseObject->showcase_id, $this->myshowcase_insert_data);

        // Assign any uploaded attachments with the specific entry_hash to the newly created post.
        if ($myshowcase_data['entry_hash']) {
            $myshowcase_data['entry_hash'] = $db->escape_string($myshowcase_data['entry_hash']);

            $attachmentID = (int)(attachmentGet(["entry_hash='{$myshowcase_data['entry_hash']}'"],
                queryOptions: ['limit' => 1]
            )['attachment_id'] ?? 0);

            attachmentUpdate(['entry_id' => $this->entry_id], $attachmentID);
        }

        return [
            'entry_id' => $this->entry_id
        ];
    }


    /**
     * Updates a showcase that is already in the database.
     *
     */
    public function update_showcase(): array
    {
        global $db, $mybb, $plugins, $cache, $lang;

        $myshowcase_data = &$this->data;

        // Yes, validating is required.
        if (!$this->get_validated()) {
            die('The myshowcase needs to be validated before updating it in the DB.');
        }
        if (count($this->get_errors()) > 0) {
            die('The myshowcase is not valid.');
        }

        foreach ($myshowcase_data as $key => $value) {
            $this->myshowcase_update_data[$key] = $value;
        }

        $plugins->run_hooks('datahandler_myshowcase_update', $this);

        entryDataUpdate($this->showcase_id, $entryID, $this->myshowcase_update_data);

        // Assign any uploaded attachments with the specific entry_hash to the newly created post.
        if ($myshowcase_data['entry_hash']) {
            $myshowcase_data['entry_hash'] = $db->escape_string($myshowcase_data['entry_hash']);
            $attachmentassign = [
                'entry_id' => $myshowcase_data['entry_id']
            ];

            $attachmentID = (int)(attachmentGet(
                ["showcase_id='{$this->showcaseObject->showcase_id}'", "entry_hash='{$myshowcase_data['entry_hash']}'"],
                queryOptions: ['limit' => 1]
            )['attachment_id'] ?? 0);

            attachmentUpdate($attachmentassign, $attachmentID);
        }

        return [
            'entry_id' => $this->entry_id
        ];
    }

    public function setData(array $entryCommentData): void
    {
        $this->set_data($entryCommentData);
    }

    public function entryValidateData(): bool
    {
        $commentData = $this->data;

        if (isset($commentData['entry_id']) && (int)$commentData['entry_id'] !== $this->showcaseObject->entryID) {
            $this->set_error('invalid entry identifier');
        }

        if (isset($commentData['slug']) || $this->method === DATA_HANDLERT_METHOD_INSERT) {
            $slugLength = my_strlen($this->data['slug']);

            if ($slugLength < 1) {
                $this->set_error('the slug is too short');
            }

            if ($slugLength > DATA_TABLE_STRUCTURE['myshowcase_data']['slug']['size']) {
                $this->set_error('the slug is too large');
            }
        }

        if (!empty($commentData['user_id']) && empty(get_user($this->data['user_id'])['uid'])) {
            $this->set_error('invalid user identifier');
        }

        if (isset($commentData['views']) && $commentData['views'] < 0) {
            $this->set_error('invalid views count');
        }

        if (isset($commentData['comments']) && $commentData['comments'] < 0) {
            $this->set_error('invalid comments count');
        }

        if (isset($commentData['status']) && !in_array(
                $this->data['status'],
                [
                    ENTRY_STATUS_PENDING_APPROVAL,
                    ENTRY_STATUS_VISIBLE,
                    ENTRY_STATUS_SOFT_DELETED
                ]
            )) {
            $this->set_error('invalid status');
        }

        if (!empty($commentData['moderator_user_id']) && empty(get_user($this->data['moderator_user_id'])['uid'])) {
            $this->set_error('invalid moderator identifier');
        }

        if (isset($commentData['dateline']) && $commentData['dateline'] < 0) {
            $this->set_error('invalid create stamp');
        }

        if (isset($commentData['edit_stamp']) && $commentData['edit_stamp'] < 0) {
            $this->set_error('invalid edit stamp');
        }

        if ($this->get_errors()) {
            return false;
        }

        return true;
    }

    public function entryInsert(): int
    {
        return $this->showcaseObject->dataInsert($this->data);
    }

    public function entryUpdate(): int
    {
        return $this->showcaseObject->dataUpdate($this->data);
    }

    public function entryDelete(): void
    {
        $this->showcaseObject->attachmentsDelete($this->showcaseObject->entryID);

        $this->showcaseObject->commentsDelete($this->showcaseObject->entryID);

        $this->showcaseObject->showcaseDataDelete(["entry_id='{$this->showcaseObject->entryID}'"]);
    }

    public function commentValidateData(): bool
    {
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

        if (isset($commentData['comment']) || $this->method === DATA_HANDLERT_METHOD_INSERT) {
            $commentLength = my_strlen($this->data['comment']);

            if ($commentLength < 1) {
                $this->set_error('the message is too short');
            }

            if ($commentLength > $this->showcaseObject->commentsMaximumLength) {
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

        if ($this->get_errors()) {
            return false;
        }

        return true;
    }

    public function commentInsert(): int
    {
        return commentInsert(array_merge($this->data, [
            'showcase_id' => $this->showcaseObject->showcase_id,
            'entry_id' => $this->showcaseObject->entryID
        ]));
    }

    public function commentUpdate(int $commentID): int
    {
        return commentUpdate($this->data, $commentID);
    }

    public function commentDelete(int $commentID): void
    {
        commentsDelete(["comment_id='{$commentID}'"]);
    }
}