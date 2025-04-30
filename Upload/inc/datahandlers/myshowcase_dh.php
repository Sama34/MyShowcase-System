<?php
/**
 * MyShowcase Plugin for MyBB - MyShowcase Datahandler
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\datahandlers\myshowcase.php
 *
 */

declare(strict_types=1);

namespace inc\datahandlers;

use DataHandler;
use MyShowcase\System\UserPermissions;
use MyShowcase\System\Showcase;

use function MyShowcase\Core\attachmentUpdate;
use function MyShowcase\Core\cacheGet;
use function MyShowcase\Core\cacheUpdate;
use function MyShowcase\Core\showcaseDataInsert;
use function MyShowcase\Core\showcaseDataUpdate;

use const MyShowcase\Core\CACHE_TYPE_FIELDS;
use const MyShowcase\Core\CACHE_TYPE_PERMISSIONS;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

/**
 * MyShowcase handling class, provides common structure to handle post data.
 *
 */
class MyShowcaseDataHandler extends DataHandler
{
    /**
     * The language file used in the data handler.
     *
     * @var string
     */
    public string $language_file = 'datahandler_myshowcase';

    /**
     * The prefix for the language variables used in the data handler.
     *
     * @var string
     */
    public $language_prefix = 'myshowcasedata';

    /**
     * What are we performing?
     * new = New showcase entry
     * edit = Editing an entry
     */
    public string $action;

    /**
     * Array of data inserted in to a showcase.
     *
     * @var array
     */
    public array $myshowcase_insert_data = [];

    /**
     * Array of data used to update a showcase.
     *
     * @var array
     */
    public array $myshowcase_update_data = [];

    protected Showcase $showcase;

    public function __construct(Showcase $showcase, string $method = 'insert')
    {
        $this->showcase = $showcase;

        parent::__construct($method);
    }


    /**
     * Validate a showcase.
     *
     * @return bool True when valid, false when invalid.
     */
    public function validate_showcase(): bool
    {
        global $me, $cache, $db, $plugins, $lang;

        $myshowcase_data = &$this->data;

        //get this myshowcase's field info
        $fieldcache = cacheGet(CACHE_TYPE_FIELDS);

        //get this myshowcase's permissions
        $permcache = cacheGet(CACHE_TYPE_PERMISSIONS);
        if (!is_array($permcache[1])) {
            cacheUpdate(CACHE_TYPE_PERMISSIONS);
            $permcache = cacheGet(CACHE_TYPE_PERMISSIONS);
        }

        // Don't have a user ID at all - not good or guest is posting which usually is not allowed but check anyway.
        if (!isset($myshowcase_data['uid']) || ($myshowcase_data['uid'] == 0 && ((!$permcache[1][UserPermissions::CanAddEntries] && $this->action = 'new') || (!$permcache[1][UserPermissions::CanEditEntries] && $this->action = 'edit')))) {
            $this->set_error('invalid_user_id');
        } // If we have a user id but no username then fetch the username.
        elseif (!get_user($myshowcase_data['uid'])) {
            $this->set_error('invalid_user_id');
        }

        //run through all fields checking defined requirements
        foreach ($fieldcache[$me->fieldSetID] as $field) {
            $fname = $field['name'];
            $temp = 'myshowcase_field_' . $fname;
            $field_header = $lang->$temp;

            //verify required
            if ($field['requiredField'] == 1) {
                if (my_strlen($myshowcase_data[$fname]) == 0 || !isset($myshowcase_data[$fname])) {
                    $this->set_error('missing_field', [$field_header]);
                } elseif ($field['html_type'] == 'db' && $myshowcase_data[$fname] == 0) {
                    $this->set_error('missing_field', [$field_header]);
                }
            }

            if ($field['enabled'] == 1 || $field['requiredField'] == 1) //added require check in case admin sets require but not enable
            {
                //verify type and lengths
                switch ($field['field_type']) {
                    //numbers lumped together
                    case 'int':
                    case 'timestamp':
                        if (!is_numeric($myshowcase_data[$fname]) && $myshowcase_data[$fname] != '') {
                            $this->set_error('invalid_type', [$field_header]);
                        }

                    //numbers and simple text need length checked so no break
                    case 'varchar':
                        //date fields do not have length limitations since the min/max are settings for the year
                        if ($field['html_type'] != 'date') {
                            if (my_strlen(strval($myshowcase_data[$fname])) > $field['max_length'] || my_strlen(
                                    strval($myshowcase_data[$fname])
                                ) < $field['min_length']) {
                                $this->set_error(
                                    'invalid_length',
                                    [
                                        $field_header,
                                        my_strlen(strval($myshowcase_data[$fname])),
                                        $field['min_length'] . '-' . $field['max_length']
                                    ]
                                );
                            }
                        }
                        break;

                    //text all on its own since its for text areas
                    case 'text':
                        if (my_strlen(
                                $myshowcase_data[$fname]
                            ) > $me->maximumLengthForTextFields && $me->maximumLengthForTextFields > 0) {
                            $this->set_error(
                                'message_too_long',
                                [$field_header, my_strlen($myshowcase_data[$fname]), $me->maximumLengthForTextFields]
                            );
                        }

                        if (my_strlen(strval($myshowcase_data[$fname])) < $field['min_length']) {
                            $this->set_error(
                                'invalid_length_min',
                                [$field_header, my_strlen(strval($myshowcase_data[$fname])), $field['min_length']]
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
     * @return array Array of new showcase details, gid and visibility.
     */
    public function insert_showcase(): array
    {
        global $me, $db, $mybb, $plugins, $cache, $lang;

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

        $this->gid = showcaseDataInsert($this->showcase->id, $this->myshowcase_insert_data);

        // Assign any uploaded attachments with the specific posthash to the newly created post.
        if ($myshowcase_data['posthash']) {
            $myshowcase_data['posthash'] = $db->escape_string($myshowcase_data['posthash']);

            attachmentUpdate(["posthash='{$myshowcase_data['posthash']}'"], ['gid' => $this->gid]);
        }

        // Return the post's pid and whether or not it is visible.
        return [
            'gid' => $this->gid
        ];
    }


    /**
     * Updates a showcase that is already in the database.
     *
     */
    public function update_showcase(): array
    {
        global $me, $db, $mybb, $plugins, $cache, $lang;

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

        showcaseDataUpdate($this->id, $entryID, $this->myshowcase_update_data);

        // Assign any uploaded attachments with the specific posthash to the newly created post.
        if ($myshowcase_data['posthash']) {
            $myshowcase_data['posthash'] = $db->escape_string($myshowcase_data['posthash']);
            $attachmentassign = [
                'gid' => $myshowcase_data['gid']
            ];

            attachmentUpdate(
                ["id='{$me->id}'", "posthash='{$myshowcase_data['posthash']}'"],
                $attachmentassign
            );
        }

        // Return the post's pid and whether or not it is visible.
        return [
            'gid' => $this->gid
        ];
    }


}