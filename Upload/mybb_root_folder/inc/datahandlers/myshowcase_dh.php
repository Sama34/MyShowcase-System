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

// Disallow direct access to this file for security reasons
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
    public $language_file = 'datahandler_myshowcase';

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
    public $action;

    /**
     * Array of data inserted in to a showcase.
     *
     * @var array
     */
    public $myshowcase_insert_data = array();

    /**
     * Array of data used to update a showcase.
     *
     * @var array
     */
    public $myshowcase_update_data = array();


    /**
     * Validate a showcase.
     *
     * @return bool True when valid, false when invalid.
     */
    public function validate_showcase()
    {
        global $me, $cache, $db, $plugins, $lang;

        $myshowcase_data = &$this->data;

        //get this myshowcase's field info
        $fieldcache = $cache->read('myshowcase_fields');
        if (!is_array($fieldcache[$me->fieldsetid])) {
            myshowcase_update_cache('fields');
            $fieldcache = $cache->read('myshowcase_fields');
        }

        //get this myshowcase's permissions
        $permcache = $cache->read('myshowcase_permissions');
        if (!is_array($permcache[1])) {
            myshowcase_update_cache('permissions');
            $permcache = $cache->read('myshowcase_permissions');
        }

        // Don't have a user ID at all - not good or guest is posting which usually is not allowed but check anyway.
        if (!isset($myshowcase_data['uid']) || ($myshowcase_data['uid'] == 0 && ((!$permcache[1]['canadd'] && $this->action = 'new') || (!$permcache[1]['canedit'] && $this->action = 'edit')))) {
            $this->set_error('invalid_user_id');
        } // If we have a user id but no username then fetch the username.
        else {
            if (!get_user($myshowcase_data['uid'])) {
                $this->set_error('invalid_user_id');
            }
        }

        //run through all fields checking defined requirements
        foreach ($fieldcache[$me->fieldsetid] as $field) {
            $fname = $field['name'];
            $temp = 'myshowcase_field_' . $fname;
            $field_header = $lang->$temp;

            //verify required
            if ($field['require'] == 1) {
                if (my_strlen($myshowcase_data[$fname]) == 0 || !isset($myshowcase_data[$fname])) {
                    $this->set_error('missing_field', array($field_header));
                } elseif ($field['html_type'] == 'db' && $myshowcase_data[$fname] == 0) {
                    $this->set_error('missing_field', array($field_header));
                }
            }

            if ($field['enabled'] == 1 || $field['require'] == 1) //added require check in case admin sets require but not enable
            {
                //verify type and lengths
                switch ($field['field_type']) {
                    //numbers lumped together
                    case 'int':
                    case 'timestamp':
                        if (!is_numeric($myshowcase_data[$fname]) && $myshowcase_data[$fname] != '') {
                            $this->set_error('invalid_type', array($field_header));
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
                                    array(
                                        $field_header,
                                        my_strlen(strval($myshowcase_data[$fname])),
                                        $field['min_length'] . '-' . $field['max_length']
                                    )
                                );
                            }
                        }
                        break;

                    //text all on its own since its for text areas
                    case 'text':
                        if (my_strlen($myshowcase_data[$fname]) > $me->othermaxlength && $me->othermaxlength > 0) {
                            $this->set_error(
                                'message_too_long',
                                array($field_header, my_strlen($myshowcase_data[$fname]), $me->othermaxlength)
                            );
                        }

                        if (my_strlen(strval($myshowcase_data[$fname])) < $field['min_length']) {
                            $this->set_error(
                                'invalid_length_min',
                                array($field_header, my_strlen(strval($myshowcase_data[$fname])), $field['min_length'])
                            );
                        }
                        break;
                }
            }

            //escape the input (since validation above already checked for forced numeric, we can escape everything)
            //$myshowcase_data[$fname] = $db->escape_string($myshowcase_data[$fname]);
        }

        $plugins->run_hooks('datahandler_post_validate_post', $this);

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
    public function insert_showcase()
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

        $this->gid = $db->insert_query($me->table_name, $this->myshowcase_insert_data);

        // Assign any uploaded attachments with the specific posthash to the newly created post.
        if ($myshowcase_data['posthash']) {
            $myshowcase_data['posthash'] = $db->escape_string($myshowcase_data['posthash']);
            $attachmentassign = array(
                'gid' => $this->gid
            );
            $db->update_query('myshowcase_attachments', $attachmentassign, "posthash='{$myshowcase_data['posthash']}'");
        }

        // Return the post's pid and whether or not it is visible.
        return array(
            'gid' => $this->gid,
            'visible' => $visible
        );
    }


    /**
     * Updates a showcase that is already in the database.
     *
     */
    public function update_showcase()
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

        $db->update_query($me->table_name, $this->myshowcase_update_data, 'gid=' . $myshowcase_data['gid']);

        // Assign any uploaded attachments with the specific posthash to the newly created post.
        if ($myshowcase_data['posthash']) {
            $myshowcase_data['posthash'] = $db->escape_string($myshowcase_data['posthash']);
            $attachmentassign = array(
                'gid' => $myshowcase_data['gid']
            );
            $db->update_query(
                'myshowcase_attachments',
                $attachmentassign,
                'id=' . $me->id . " AND posthash='{$myshowcase_data['posthash']}'"
            );
        }

        // Return the post's pid and whether or not it is visible.
        return array(
            'gid' => $this->gid,
            'visible' => $visible
        );
    }


}

?>
