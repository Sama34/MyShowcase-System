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
use inc\datahandlers\MyShowcaseDataHandler;

use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\attachmentUpdate;
use function MyShowcase\Core\dataHandlerGetObject;
use function MyShowcase\Core\getTemplate;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\Core\fieldDataGet;
use function MyShowcase\Core\entryDataGet;
use function MyShowcase\Core\cacheUpdate;
use function MyShowcase\Core\loadLanguage;
use function MyShowcase\Core\entryDataUpdate;

use const MyShowcase\Core\ATTACHMENT_UNLIMITED;
use const MyShowcase\Core\ATTACHMENT_ZERO;
use const MyShowcase\Core\DATA_HANDLERT_METHOD_UPDATE;
use const MyShowcase\Core\FIELD_TYPE_HTML_CHECK_BOX;
use const MyShowcase\Core\FIELD_TYPE_HTML_DB;
use const MyShowcase\Core\FIELD_TYPE_HTML_DATE;
use const MyShowcase\Core\FIELD_TYPE_HTML_RADIO;
use const MyShowcase\Core\FIELD_TYPE_HTML_TEXT_BOX;
use const MyShowcase\Core\FIELD_TYPE_HTML_TEXTAREA;
use const MyShowcase\Core\FIELD_TYPE_HTML_URL;
use const MyShowcase\Core\FORMAT_TYPE_NONE;
use const MyShowcase\Core\TABLES_DATA;

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

        global $showcaseName;
        global $buttonGo;

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
                if ($mybb->get_input('action') === 'new') {
                    add_breadcrumb(
                        $lang->myShowcaseButtonNewEntry,
                        $this->showcaseObject->urlBuild($this->showcaseObject->urlMain)
                    );
                    $showcase_action = 'new';

                    //need to populated a default user value here for new entries
                    $entryFieldsData['user_id'] = $currentUserID;
                } else {
                    $showcase_editing_user = str_replace(
                        '{username}',
                        $entryUserData['username'],
                        $lang->myshowcase_editing_user
                    );
                    add_breadcrumb(
                        $showcase_editing_user,
                        $this->showcaseObject->urlBuild($this->showcaseObject->urlMain)
                    );
                    $showcase_action = 'edit';
                }

                // Get a listing of the current attachments.
                if (!empty($showcaseUserPermissions[UserPermissions::CanAttachFiles])) {
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
                            ($showcaseUserPermissions[UserPermissions::AttachmentsLimit] === ATTACHMENT_UNLIMITED ? $lang->myshowcase_unlimited : $showcaseUserPermissions[UserPermissions::AttachmentsLimit])
                        ) . '<br>';
                    if ($showcaseUserPermissions[UserPermissions::AttachmentsLimit] === ATTACHMENT_UNLIMITED || ($showcaseUserPermissions[UserPermissions::AttachmentsLimit] !== ATTACHMENT_ZERO && ($attachcount < $showcaseUserPermissions[UserPermissions::AttachmentsLimit]))) {
                        if ($this->showcaseObject->userPermissions[UserPermissions::CanWaterMarkAttachments]) {
                            $showcase_watermark = eval(getTemplate('watermark'));
                        }
                        $showcase_new_attachments_input = eval(getTemplate('new_attachments_input'));
                    }
                    $showcase_attachments = eval(getTemplate('new_attachments'));
                }

                if ($mybb->request_method === 'post' && $mybb->get_input('submit')) {
                    // Decide on the visibility of this post.
                    if ($this->showcaseObject->moderateEdits && !$this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries]) {
                        $approved = 0;
                        $approved_by = 0;
                    } else {
                        $approved = 1;
                        $approved_by = $currentUserID;
                    }

                    $plugins->run_hooks('myshowcase_do_newedit_start');

                    // Set up showcase handler.
                    //require_once MYBB_ROOT . 'inc/datahandlers/myshowcase_dh.php';

                    if ($mybb->get_input('action') === 'edit') {
                        $showcasehandler = dataHandlerGetObject($this->showcaseObject, DATA_HANDLERT_METHOD_UPDATE);
                        $showcasehandler->action = 'edit';
                    } else {
                        $showcasehandler = dataHandlerGetObject($this->showcaseObject);
                        $showcasehandler->action = 'new';
                    }

                    //This is where the work is done

                    // Verify incoming POST request
                    verify_post_check($mybb->get_input('my_post_key'));

                    // Set the post data that came from the input to the $post array.
                    $default_data = [
                        'user_id' => $entryFieldsData['user_id'],
                        'dateline' => TIME_NOW,
                        'approved' => $approved,
                        'approved_by' => $approved_by,
                        'entry_hash' => $entryHash
                    ];

                    //add showcase showcase_id if editing so we know what to update
                    if ($mybb->get_input('action') === 'edit') {
                        $default_data = array_merge(
                            $default_data,
                            ['entry_id' => $this->showcaseObject->entryData]
                        );
                    }

                    //add showcase specific fields
                    reset($this->showcaseObject->fieldSetEnabledFields);

                    $submitted_data = [];

                    foreach ($this->showcaseObject->fieldSetEnabledFields as $fieldName => $htmlType) {
                        if ($htmlType === FIELD_TYPE_HTML_DB || $htmlType === FIELD_TYPE_HTML_RADIO) {
                            $submitted_data[$fieldName] = intval($mybb->get_input('myshowcase_field_' . $fieldName));
                        } elseif ($htmlType === FIELD_TYPE_HTML_CHECK_BOX) {
                            $submitted_data[$fieldName] = (isset($mybb->input['myshowcase_field_' . $fieldName]) ? 1 : 0);
                        } elseif ($htmlType === FIELD_TYPE_HTML_DATE) {
                            $m = $db->escape_string($mybb->get_input('myshowcase_field_' . $fieldName . '_m'));
                            $d = $db->escape_string($mybb->get_input('myshowcase_field_' . $fieldName . '_d'));
                            $y = $db->escape_string($mybb->get_input('myshowcase_field_' . $fieldName . '_y'));
                            $submitted_data[$fieldName] = $m . '|' . $d . '|' . $y;
                        } else {
                            $submitted_data[$fieldName] = $db->escape_string(
                                $mybb->get_input('myshowcase_field_' . $fieldName)
                            );
                        }
                    }

                    //send data to handler
                    $showcasehandler->set_data(array_merge($default_data, $submitted_data));

                    // Now let the showcase handler do all the hard work.
                    $valid_showcase = $showcasehandler->validate_showcase();

                    $showcase_errors = [];

                    // Fetch friendly error messages if this is an invalid showcase
                    if (!$valid_showcase) {
                        $showcase_errors = $showcasehandler->get_friendly_errors();
                    }
                    if (count($showcase_errors) > 0) {
                        $error = inline_error($showcase_errors);
                        $pageContents = eval($templates->render('error'));
                    } else {
                        //update showcase
                        if ($mybb->get_input('action') === 'edit') {
                            $insert_showcase = $showcasehandler->update_showcase();
                            $showcaseid = $this->showcaseObject->entryData;
                        } //insert showcase
                        else {
                            $insert_showcase = $showcasehandler->insert_showcase();
                            $showcaseid = $insert_showcase['entry_id'];
                        }

                        $plugins->run_hooks('myshowcase_do_newedit_end');

                        //fix url insert variable to update results
                        $entryUrl = str_replace('{entry_id}', (string)$showcaseid, $this->showcaseObject->urlViewEntry);

                        $redirect_newshowcase = $lang->redirect_myshowcase_new . '' . $lang->redirect_myshowcase . '' . $lang->sprintf(
                                $lang->redirect_myshowcase_return,
                                $this->showcaseObject->urlBuild($this->showcaseObject->urlMain)
                            );
                        redirect($entryUrl, $redirect_newshowcase);
                        exit;
                    }
                } else {
                    $plugins->run_hooks('myshowcase_newedit_start');

                    $pageContents .= eval(getTemplate('new_top'));

                    reset($this->showcaseObject->fieldSetEnabledFields);

                    foreach ($this->showcaseObject->fieldSetEnabledFields as $fieldName => $htmlType) {
                        $temp = 'myshowcase_field_' . $fieldName;
                        $field_header = !empty($lang->$temp) ? $lang->$temp : $fieldName;

                        $alternativeBackground = ($alternativeBackground === 'trow1' ? 'trow2' : 'trow1');

                        if ($mybb->get_input('action') === 'edit') {
                            $mybb->input['myshowcase_field_' . $fieldName] = htmlspecialchars_uni(
                                stripslashes($entryFieldsData[$fieldName])
                            );
                        }

                        switch ($htmlType) {
                            case FIELD_TYPE_HTML_TEXT_BOX:
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fieldName;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fieldName);
                                $showcase_field_enabled = '';//($this->showcaseObject->fieldSetEnabledFields[$fieldName] !== 1 ? 'disabled' : '');
                                $showcase_field_checked = '';
                                $showcase_field_options = 'maxlength="' . $this->showcaseObject->fieldSetFieldsMaximumLenght[$fieldName] . '"';
                                $showcase_field_input = eval(getTemplate('field_textbox'));

                                if ($this->showcaseObject->fieldSetFormatableFields[$fieldName] !== FORMAT_TYPE_NONE) {
                                    $showcase_field_input .= '&nbsp;' . $lang->myshowcase_editing_number;
                                }
                                break;

                            case FIELD_TYPE_HTML_URL:
                                $showcase_field_width = 150;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fieldName;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fieldName);
                                $showcase_field_enabled = '';//($this->showcaseObject->fieldSetEnabledFields[$fieldName] !== 1 ? 'disabled' : '');
                                $showcase_field_checked = '';
                                $showcase_field_options = 'maxlength="' . $this->showcaseObject->fieldSetFieldsMaximumLenght[$fieldName] . '"';
                                $showcase_field_input = eval(getTemplate('field_textbox'));
                                break;

                            case 'textarea':
                                $showcase_field_width = 100;
                                $showcase_field_rows = $this->showcaseObject->fieldSetFieldsMaximumLenght[$fieldName];
                                $showcase_field_name = 'myshowcase_field_' . $fieldName;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fieldName);
                                $showcase_field_enabled = '';//($this->showcaseObject->fieldSetEnabledFields[$fieldName] !== 1 ? 'disabled' : '');
                                $showcase_field_checked = '';
                                $showcase_field_options = '';
                                $showcase_field_input = eval(getTemplate('field_textarea'));
                                break;

                            case FIELD_TYPE_HTML_RADIO:
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fieldName;
                                $showcase_field_enabled = '';//($this->showcaseObject->fieldSetEnabledFields[$fieldName] !== 1 ? 'disabled' : '');
                                $showcase_field_options = '';

                                $fieldDataObjects = fieldDataGet(
                                    [
                                        "set_id='{$this->showcaseObject->fieldSetID}'",
                                        "name='{$fieldName}'",
                                        "value_id!='0'"
                                    ],
                                    ['value_id', 'value'],
                                    ['order_by' => 'display_order']
                                );

                                if (!$fieldDataObjects) {
                                    error($lang->myshowcase_db_no_data);
                                }

                                $showcase_field_input = '';
                                foreach ($fieldDataObjects as $fieldDataID => $results) {
                                    $showcase_field_value = $results['value_id'];
                                    $showcase_field_checked = ($mybb->get_input(
                                        'myshowcase_field_' . $fieldName
                                    ) === $results['value_id'] ? ' checked' : '');
                                    $showcase_field_text = $results['value'];
                                    $showcase_field_input .= eval(getTemplate('field_radio'));
                                }
                                break;

                            case FIELD_TYPE_HTML_CHECK_BOX:
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fieldName;
                                $showcase_field_value = '1';
                                $showcase_field_enabled = '';//($this->showcaseObject->fieldSetEnabledFields[$fieldName] !== 1 ? 'disabled' : '');
                                $showcase_field_checked = ($mybb->get_input(
                                    'myshowcase_field_' . $fieldName
                                ) === 1 ? ' checked="checked"' : '');
                                $showcase_field_options = '';
                                $showcase_field_input = eval(getTemplate('field_checkbox'));
                                break;

                            case FIELD_TYPE_HTML_DB:
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fieldName;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fieldName);
                                $showcase_field_enabled = '';//($this->showcaseObject->fieldSetEnabledFields[$fieldName] !== 1 ? 'disabled' : '');
                                $showcase_field_checked = '';

                                $fieldDataObjects = fieldDataGet(
                                    [
                                        "set_id='{$this->showcaseObject->fieldSetID}'",
                                        "name='{$fieldName}'",
                                        "value_id!='0'"
                                    ],
                                    ['value_id', 'value'],
                                    ['order_by' => 'display_order']
                                );

                                if (!$fieldDataObjects) {
                                    error($lang->myshowcase_db_no_data);
                                }

                                $showcase_field_options = ($mybb->get_input(
                                    'action'
                                ) === 'new' ? '<option value=0>&lt;Select&gt;</option>' : '');
                                foreach ($fieldDataObjects as $fieldDataID => $results) {
                                    $showcase_field_options .= '<option value="' . $results['value_id'] . '" ' . ($showcase_field_value === $results['value_id'] ? ' selected' : '') . '>' . $results['value'] . '</option>';
                                }
                                $showcase_field_input = eval(getTemplate('field_db'));
                                break;

                            case FIELD_TYPE_HTML_DATE:
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fieldName;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fieldName);
                                $showcase_field_enabled = '';//($this->showcaseObject->fieldSetEnabledFields[$fieldName] !== 1 ? 'disabled' : '');
                                $showcase_field_checked = '';

                                $showcase_field_value_m = ($mybb->get_input(
                                    'myshowcase_field_' . $fieldName . '_m'
                                ) === '00' ? '00' : $mybb->get_input('myshowcase_field_' . $fieldName . '_m'));
                                $showcase_field_value_d = ($mybb->get_input(
                                    'myshowcase_field_' . $fieldName . '_d'
                                ) === '00' ? '00' : $mybb->get_input('myshowcase_field_' . $fieldName . '_d'));
                                $showcase_field_value_y = ($mybb->get_input(
                                    'myshowcase_field_' . $fieldName . '_y'
                                ) === '0000' ? '0000' : $mybb->get_input('myshowcase_field_' . $fieldName . '_y'));

                                $showcase_field_options_m = '<option value=0' . ($showcase_field_value_m === '00' ? ' selected' : '') . '>&nbsp;</option>';
                                $showcase_field_options_d = '<option value=0' . ($showcase_field_value_d === '00' ? ' selected' : '') . '>&nbsp;</option>';
                                $showcase_field_options_y = '<option value=0' . ($showcase_field_value_y === '0000' ? ' selected' : '') . '>&nbsp;</option>';
                                for ($i = 1; $i <= 12; $i++) {
                                    $showcase_field_options_m .= '<option value="' . substr(
                                            '0' . $i,
                                            -2
                                        ) . '" ' . ($showcase_field_value_m === substr(
                                            '0' . $i,
                                            -2
                                        ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                                }
                                for ($i = 1; $i <= 31; $i++) {
                                    $showcase_field_options_d .= '<option value="' . substr(
                                            '0' . $i,
                                            -2
                                        ) . '" ' . ($showcase_field_value_d === substr(
                                            '0' . $i,
                                            -2
                                        ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                                }
                                for ($i = $this->showcaseObject->fieldSetFieldsMaximumLenght[$fieldName]; $i >= $this->showcaseObject->fieldSetFieldsMinimumLenght[$fieldName]; $i--) {
                                    $showcase_field_options_y .= '<option value="' . $i . '" ' . ($showcase_field_value_y === $i ? ' selected' : '') . '>' . $i . '</option>';
                                }
                                $showcase_field_input = eval(getTemplate('field_date'));
                                break;
                        }

                        $field_header = ($this->showcaseObject->fieldSetFieldsRequired[$fieldName] ? '<strong>' . $field_header . ' *</strong>' : $field_header);
                        $pageContents .= eval(getTemplate('new_fields'));
                    }

                    $plugins->run_hooks('myshowcase_newedit_end');

                    $pageContents .= eval(getTemplate('new_bottom'));
                }
            }
        }

        if ($mybb->get_input('action') === 'new') {
            add_breadcrumb(
                $lang->myShowcaseButtonNewEntry,
                $this->showcaseObject->urlBuild($this->showcaseObject->urlMain)
            );
        } elseif ($mybb->get_input('action') === 'edit') {
            $showcase_editing_user = str_replace(
                '{username}',
                $entryUserData['username'],
                $lang->myshowcase_editing_user
            );
            add_breadcrumb($showcase_editing_user, $this->showcaseObject->urlBuild($this->showcaseObject->urlMain));
        }

        if ($isEditPage) {
            if (!$this->showcaseObject->userPermissions[UserPermissions::CanEditEntries]) {
                error($lang->myshowcase_not_authorized);
            }
        } elseif (!$this->showcaseObject->userPermissions[UserPermissions::CanAddEntries]) {
            error($lang->myshowcase_not_authorized);
        }

        $hookArguments = hooksRun('output_new_start', $hookArguments);

        global $errorsAttachments;

        $errorsAttachments = $errorsAttachments ?? '';

        $attachmentsTable = '';

        // Get a listing of the current attachments.
        if (!empty($showcaseUserPermissions[UserPermissions::CanAttachFiles])) {
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
                    ($showcaseUserPermissions[UserPermissions::AttachmentsLimit] === ATTACHMENT_UNLIMITED ? $lang->myshowcase_unlimited : $showcaseUserPermissions[UserPermissions::AttachmentsLimit])
                ) . '<br>';
            if ($showcaseUserPermissions[UserPermissions::AttachmentsLimit] === ATTACHMENT_UNLIMITED || ($showcaseUserPermissions[UserPermissions::AttachmentsLimit] !== ATTACHMENT_UNLIMITED && $attachcount < $showcaseUserPermissions[UserPermissions::AttachmentsLimit])) {
                if ($this->showcaseObject->userPermissions[UserPermissions::CanWaterMarkAttachments] && $this->showcaseObject->waterMarkImage !== '' && file_exists(
                        $this->showcaseObject->waterMarkImage
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

        reset($this->showcaseObject->fieldSetEnabledFields);

        $showcaseFields = '';

        $fieldTabIndex = 1;

        foreach ($this->showcaseObject->fieldSetEnabledFields as $fieldName => $htmlType) {
            $fieldKey = "myshowcase_field_{$fieldName}";

            $fieldTitle = $fieldName;

            if (isset($lang->{$fieldKey})) {
                $fieldTitle = $lang->{$fieldKey};
            }

            $alternativeBackground = ($alternativeBackground === 'trow1' ? 'trow2' : 'trow1');

            if ($mybb->get_input('action') === 'edit') {
                $mybb->input[$fieldKey] = htmlspecialchars_uni(
                    stripslashes($entryFieldsData[$fieldName])
                );
            }

            $fieldElementRequired = '';

            if ($this->showcaseObject->fieldSetFieldsRequired[$fieldName]) {
                $fieldElementRequired = 'required="required"';
            }

            $minimumLength = $this->showcaseObject->fieldSetFieldsMinimumLenght[$fieldName] ?? '';

            $maximumLength = $this->showcaseObject->fieldSetFieldsMaximumLenght[$fieldName] ?? '';

            switch ($htmlType) {
                case FIELD_TYPE_HTML_TEXT_BOX:
                    $fieldValue = $mybb->get_input($fieldKey);

                    $fieldInput = eval(getTemplate('pageNewFieldTextBox'));
                    break;
                case FIELD_TYPE_HTML_URL:
                    $fieldValue = $mybb->get_input($fieldKey);

                    $fieldInput = eval(getTemplate('pageNewFieldUrl'));
                    break;
                case FIELD_TYPE_HTML_TEXTAREA:
                    $fieldValue = $mybb->get_input($fieldKey);

                    $fieldInput = eval(getTemplate('pageNewFieldTextArea'));
                    break;
                case FIELD_TYPE_HTML_RADIO:
                    $showcase_field_width = 50;
                    $showcase_field_rows = '';
                    $showcase_field_enabled = '';// ($this->showcaseObject->fieldSetEnabledFields[$fieldName] !== 1 ? 'disabled' : '');
                    $showcase_field_options = '';

                    $fieldObjects = fieldDataGet(
                        ["set_id='{$this->showcaseObject->fieldSetID}'", "name='{$fieldName}'", "value_id!='0'"],
                        ['value_id', 'value'],
                        ['order_by' => 'display_order']
                    );

                    if (!$fieldObjects) {
                        error($lang->myshowcase_db_no_data);
                    }

                    $fieldInput = '';

                    foreach ($fieldObjects as $fieldDataID => $fieldData) {
                        $fieldValue = $fieldData['value_id'];

                        $showcase_field_checked = ($mybb->get_input(
                            $fieldKey
                        ) === $fieldData['value_id'] ? ' checked' : '');

                        $showcase_field_text = $fieldData['value'];

                        $fieldInput .= eval(getTemplate('field_radio'));
                    }

                    break;

                case FIELD_TYPE_HTML_CHECK_BOX:
                    $showcase_field_width = 50;
                    $showcase_field_rows = '';
                    $fieldValue = '1';

                    $showcase_field_checked = ($mybb->get_input($fieldKey) === 1 ? ' checked="checked"' : '');
                    $showcase_field_options = '';
                    $fieldInput = eval(getTemplate('field_checkbox'));
                    break;

                case FIELD_TYPE_HTML_DB:
                    $showcase_field_width = 50;
                    $showcase_field_rows = '';
                    $fieldValue = $mybb->get_input($fieldKey);

                    $showcase_field_checked = '';

                    $fieldDataObjects = fieldDataGet(
                        ["set_id='{$this->showcaseObject->fieldSetID}'", "name='{$fieldName}'", "value_id!='0'"],
                        ['value_id', 'value'],
                        ['order_by' => 'display_order']
                    );

                    if (!$fieldDataObjects) {
                        error($lang->myshowcase_db_no_data);
                    }

                    $showcase_field_options = ($mybb->get_input(
                        'action'
                    ) === 'new' ? '<option value=0>&lt;Select&gt;</option>' : '');
                    foreach ($fieldDataObjects as $fieldDataID => $results) {
                        $showcase_field_options .= '<option value="' . $results['value_id'] . '" ' . ($fieldValue === $results['value_id'] ? ' selected' : '') . '>' . $results['value'] . '</option>';
                    }
                    $fieldInput = eval(getTemplate('field_db'));
                    break;

                case FIELD_TYPE_HTML_DATE:
                    $showcase_field_width = 50;
                    $showcase_field_rows = '';
                    $fieldValue = $mybb->get_input($fieldKey);

                    $showcase_field_checked = '';

                    $date_bits = explode('|', $fieldValue);
                    $mybb->input[$fieldKey . '_m'] = $date_bits[0];
                    $mybb->input[$fieldKey . '_d'] = $date_bits[1];
                    $mybb->input[$fieldKey . '_y'] = $date_bits[2];

                    $showcase_field_value_m = ($mybb->get_input(
                        $fieldKey . '_m'
                    ) === '00' ? '00' : $mybb->get_input($fieldKey . '_m'));
                    $showcase_field_value_d = ($mybb->get_input(
                        $fieldKey . '_d'
                    ) === '00' ? '00' : $mybb->get_input($fieldKey . '_d'));
                    $showcase_field_value_y = ($mybb->get_input(
                        $fieldKey . '_y'
                    ) === '0000' ? '0000' : $mybb->get_input($fieldKey . '_y'));

                    $showcase_field_options_m = '<option value=00' . ($showcase_field_value_m === '00' ? ' selected' : '') . '>&nbsp;</option>';
                    $showcase_field_options_d = '<option value=00' . ($showcase_field_value_d === '00' ? ' selected' : '') . '>&nbsp;</option>';
                    $showcase_field_options_y = '<option value=0000' . ($showcase_field_value_y === '0000' ? ' selected' : '') . '>&nbsp;</option>';
                    for ($i = 1; $i <= 12; $i++) {
                        $showcase_field_options_m .= '<option value="' . substr(
                                '0' . $i,
                                -2
                            ) . '" ' . ($showcase_field_value_m === substr(
                                '0' . $i,
                                -2
                            ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                    }
                    for ($i = 1; $i <= 31; $i++) {
                        $showcase_field_options_d .= '<option value="' . substr(
                                '0' . $i,
                                -2
                            ) . '" ' . ($showcase_field_value_d === substr(
                                '0' . $i,
                                -2
                            ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                    }
                    for ($i = $this->showcaseObject->fieldSetFieldsMaximumLenght[$fieldName]; $i >= $this->showcaseObject->fieldSetFieldsMinimumLenght[$fieldName]; $i--) {
                        $showcase_field_options_y .= '<option value="' . $i . '" ' . ($showcase_field_value_y === $i ? ' selected' : '') . '>' . $i . '</option>';
                    }
                    $fieldInput = eval(getTemplate('field_date'));
                    break;
            }

            $showcaseFields .= eval(getTemplate('pageNewField'));

            ++$fieldTabIndex;
        }

        $pageTitle = $this->showcaseObject->name;

        $pageContents = eval(getTemplate('pageNewContents'));

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
                if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries] && $mybb->get_input(
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

                $url = $this->showcaseObject->urlBuild($this->showcaseObject->urlMain) . (count(
                        $url_params
                    ) > 0 ? '?' . implode(
                            '&amp;',
                            $url_params
                        ) : '');

                $redirtext = ($mybb->get_input(
                    'action'
                ) === 'multiapprove' ? $lang->redirect_myshowcase_approve : $lang->redirect_myshowcase_unapprove);
                redirect($url, $redirtext);
                exit;
                break;
            }

            case 'multidelete';
            {
                add_breadcrumb($lang->myshowcase_nav_multidelete);

                if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries] && $mybb->get_input(
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

                $return_url = $this->showcaseObject->urlBuild($this->showcaseObject->urlMain) . (count(
                        $url_params
                    ) > 0 ? '?' . implode(
                            '&amp;',
                            $url_params
                        ) : '');
                //$return_url = htmlspecialchars_uni($mybb->get_input('url'));
                $multidelete = eval(getTemplate('inline_deleteshowcases'));
                output_page($multidelete);
                break;
            }
            case 'do_multidelete';
            {
                if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries]) {
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

                $url = $this->showcaseObject->urlBuild($this->showcaseObject->urlMain) . (count(
                        $url_params
                    ) > 0 ? '?' . implode(
                            '&amp;',
                            $url_params
                        ) : '');

                redirect($url, $lang->redirect_myshowcase_delete);
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
        if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanEditEntries] && $currentUserID !== $entryFieldData['user_id']) {
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
        if (!$attachmentData['attachment_id'] || !$attachmentData['attachment_name'] || (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries] && $attachmentData['status'] !== 1)) {
            error($lang->error_invalidattachment);
        }

        if (!$this->showcaseObject->allowAttachments || !$this->showcaseObject->userPermissions[UserPermissions::CanViewAttachments]) {
            error_no_permission();
        }

        $filePath = MYBB_ROOT . $this->showcaseObject->imageFolder . '/' . $attachmentData['attachment_name'];

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
            );//$this->showcaseObject->images_directory."/".$attachmentData['attachment_name'];

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
            (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries] && empty($attachmentData['status']))) {
            error($lang->error_invalidattachment);
        }

        if (!$this->showcaseObject->allowAttachments || !$this->showcaseObject->userPermissions[UserPermissions::CanDownloadAttachments]) {
            error_no_permission();
        }

        //$attachmentExtension = get_extension($attachmentData['file_name']);

        $filePath = MYBB_ROOT . $this->showcaseObject->imageFolder . '/' . $attachmentData['attachment_name'];

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