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
use function MyShowcase\Core\getTemplate;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\Core\urlHandlerBuild;
use function MyShowcase\Core\urlHandlerGet;
use function MyShowcase\Core\urlHandlerSet;
use function MyShowcase\Core\fieldDataGet;
use function MyShowcase\Core\showcaseDataGet;

use const MyShowcase\Core\ATTACHMENT_UNLIMITED;
use const MyShowcase\Core\ATTACHMENT_ZERO;
use const MyShowcase\Core\FIELD_TYPE_HTML_CHECK_BOX;
use const MyShowcase\Core\FIELD_TYPE_HTML_DB;
use const MyShowcase\Core\FIELD_TYPE_HTML_DATE;
use const MyShowcase\Core\FIELD_TYPE_HTML_RADIO;
use const MyShowcase\Core\FIELD_TYPE_HTML_TEXT_BOX;
use const MyShowcase\Core\FIELD_TYPE_HTML_TEXTAREA;
use const MyShowcase\Core\FIELD_TYPE_HTML_URL;
use const MyShowcase\Core\FORMAT_TYPES;
use const MyShowcase\Core\TABLES_DATA;

class Output
{
    protected Showcase $showcase;

    public function __construct(Showcase $showcase)
    {
        $this->showcase = $showcase;
    }

    #[NoReturn] public function main(): void
    {
        global $lang, $mybb, $db, $cache;
        global $header, $headerinclude, $footer, $theme;

        global $showcaseFieldsOrder, $showcaseFieldsSearchable, $currentPage, $amp, $urlSort, $showcaseInputSortBy;
        global $showcaseInputSearchExactMatch, $showcaseFieldsShow, $showcaseFields, $unapproved;
        global $showcaseInputSearchKeywords, $urlParams, $urlBase, $showcaseInputSearchField, $showcaseColumnsCount;
        global $showcaseFieldsFormat, $showcaseName, $showcaseTableTheadInlineModeration, $urlShowcase, $showcase_url;
        global $showcaseInlineModeration, $buttonGo, $orderInput, $showcaseInputOrder, $showcaseFieldsParseable;

        $hookArguments = [];

        $hookArguments = hooksRun('output_main_start', $hookArguments);

        $buttonNewEntry = '';

        if ($this->showcase->userPermissions[UserPermissions::CanAddEntries]) {
            $urlNewEntry = SHOWCASE_URL_NEW;

            $buttonNewEntry = eval(getTemplate('buttonNewEntry'));
        }

        $showcaseFieldsSearch = [
            'username' => $lang->myShowcaseMainSortUsername
        ];

        foreach ($showcaseFieldsSearchable as $fieldKey => $fieldName) {
            $fieldNameUpper = ucfirst($fieldName);

            $showcaseFieldsSearch[$fieldName] = $lang->{"myShowcaseMainSort{$fieldNameUpper}"} ?? $lang->{"myshowcase_field_{$fieldName}"};
        }

        if ($currentPage && !my_strpos(SHOWCASE_URL, 'page=')) {
            $urlSort .= 'page=' . $currentPage . $amp;
        }

        $showcaseSelectOrderAscendingSelectedElement = $showcaseSelectOrderDescendingSelectedElement = '';

        switch ($showcaseInputOrder) {
            case 'asc':
                $showcaseSelectOrderAscendingSelectedElement = 'selected="selected"';

                $showcaseInputOrderText = $lang->myshowcase_desc;

                $showcaseInputSortByNew = 'desc';
                break;
            default:
                $showcaseSelectOrderDescendingSelectedElement = 'selected="selected"';

                $showcaseInputOrderText = $lang->myshowcase_asc;

                $showcaseInputSortByNew = 'asc';
                break;
        }

        //build sort_by option list
        $selectOptions = '';

        reset($showcaseFieldsOrder);

        foreach ($showcaseFieldsOrder as $optionName => $optionText) {
            $optionSelectedElement = '';

            if ($showcaseInputSortBy === $optionName) {
                $optionSelectedElement = 'selected="selected"';
            }

            $optionText = $lang->myShowcaseMainSelectSortBy . ' ' . $optionText;

            $selectOptions .= eval(getTemplate('pageMainSelectOption'));
        }

        $selectFieldName = 'sort_by';

        $selectFieldCode = eval(getTemplate('pageMainSelect'));

        //build searchfield option list
        $selectOptionsSearchField = '';

        reset($showcaseFieldsSearch);

        foreach ($showcaseFieldsSearch as $optionName => $optionText) {
            $optionSelectedElement = '';

            if ($showcaseInputSearchField === $optionName) {
                $optionSelectedElement = 'selected="selected"';
            }

            $selectOptionsSearchField .= eval(getTemplate('pageMainSelectOption'));
        }

        $showcaseInputSearchExactMatchCheckElement = '';

        if ($showcaseInputSearchExactMatch === 'on') {
            $showcaseInputSearchExactMatchCheckElement = 'checked="checked"';
        }

        $urlSortRow = urlHandlerBuild(array_merge($urlParams, ['order' => $showcaseInputSortByNew]));

        $orderInput[$showcaseInputSortBy] = eval(getTemplate('pageMainTableTheadFieldSort'));

        if ($this->showcase->friendlyUrlsEnabled) {
            $amp = '?';
        } else {
            $amp = '&amp;';
        }

        //build custom list header based on field settings
        $showcaseTableTheadExtra = '';

        foreach ($showcaseFieldsShow as $fieldKey => $fieldName) {
            $showcaseTableTheadExtraFieldTitle = $lang->{"myshowcase_field_{$fieldName}"};

            $showcaseTableTheadExtraFieldOrder = $orderInput[$fieldName];

            $showcaseTableTheadExtra .= eval(getTemplate('pageMainTableTheadRowField'));

            ++$showcaseColumnsCount;
        }

        //setup joins for query and build where clause based on search_field terms
        $showcaseSearchFields = $showcaseFields;

        $queryTables = ["{$this->showcase->dataTableName} g"];

        $queryTables [] = 'users u ON (u.uid = g.uid)';

        $queryFields = [
            'g.gid',
            'u.username',
            'u.usergroup',
            'u.displaygroup',
            'g.views',
            'g.comments',
            'g.dateline',
            'g.dateline',
            'g.approved',
            'g.approved_by',
            'g.posthash',
            'g.uid',
        ];

        $searchDone = false;

        reset($showcaseSearchFields);

        $whereClauses = [];

        foreach ($showcaseSearchFields as $fieldName => $fieldType) {
            if ($fieldType == 'db' || $fieldType == 'radio') {
                $queryTables[] = "myshowcase_field_data tbl_{$fieldName} ON (tbl_{$fieldName}.valueid = g.{$fieldName} AND tbl_{$fieldName}.name = '{$fieldName}')";

                $queryFields[] = "tbl_{$fieldName}.value AS `{$fieldName}`";

                if ($showcaseInputSearchKeywords && $showcaseInputSearchField == $fieldName) {
                    if ($showcaseInputSearchExactMatch) {
                        $whereClauses[] = "tbl_{$fieldName}.value='{$db->escape_string($showcaseInputSearchKeywords)}'";
                    } else {
                        $whereClauses[] = "tbl_{$fieldName}.value LIKE '%{$db->escape_string($showcaseInputSearchKeywords)}%'";
                    }

                    $whereClauses[] = "tbl_{$fieldName}.setid='{$this->showcase->fieldSetID}'";
                }
            } elseif ($showcaseInputSearchField == 'username' && !$searchDone) {
                $queryTables[] = 'users us ON (g.uid = us.uid)';

                $queryFields[] = "`{$fieldName}`";

                if ($showcaseInputSearchKeywords) {
                    if ($showcaseInputSearchExactMatch) {
                        $whereClauses[] = "us.username='{$db->escape_string($showcaseInputSearchKeywords)}'";
                    } else {
                        $whereClauses[] = "us.username LIKE '%{$db->escape_string($showcaseInputSearchKeywords)}%'";
                    }
                }

                $searchDone = true;
            } else {
                $queryFields[] = "`{$fieldName}`";

                if ($showcaseInputSearchKeywords && $showcaseInputSearchField == $fieldName) {
                    if ($showcaseInputSearchExactMatch) {
                        $whereClauses[] = "g.{$fieldName}='{$db->escape_string($showcaseInputSearchKeywords)}'";
                    } else {
                        $whereClauses[] = "g.{$fieldName} LIKE '%{$db->escape_string($showcaseInputSearchKeywords)}%'";
                    }
                }
            }
        }

        $queryOptions = [
            'order_by' => 'gid',
            'order_dir' => $showcaseInputOrder,
        ];

        if ($showcaseInputSortBy !== 'dateline') {
            $queryOptions['order_by'] = "{$db->escape_string($showcaseInputSortBy)} {$showcaseInputOrder}, gid";

            $queryOptions['order_dir'] = $showcaseInputOrder;
        }

        // How many entries are there?
        $query = $db->simple_select(
            implode(" LEFT JOIN {$db->table_prefix}", $queryTables),
            'COUNT(g.gid) AS totalEntries',
            implode(' AND ', $whereClauses),
            $queryOptions,
        );

        $totalEntries = $db->fetch_field($query, 'totalEntries');

        $showcaseEntriesList = '';

        $pagination = '';

        $alternativeBackground = alt_trow(true);

        $hookArguments = hooksRun('output_main_intermediate', $hookArguments);

        if ($totalEntries) {
            $entriesPerPage = $mybb->settings['threadsperpage'];

            if ($currentPage > 0) {
                $pageCurrent = $currentPage;

                $pageStart = ($pageCurrent - 1) * $entriesPerPage;

                $pageTotal = $totalEntries / $entriesPerPage;

                $pageTotal = ceil($pageTotal);

                if ($pageCurrent > $pageTotal) {
                    $pageStart = 0;

                    $pageCurrent = 1;
                }
            } else {
                $pageStart = 0;

                $pageCurrent = 1;
            }

            $upper = $pageStart + $entriesPerPage;

            if ($upper > $totalEntries) {
                $upper = $totalEntries;
            }

            $queryOptions['limit'] = $entriesPerPage;

            $queryOptions['limit_start'] = $pageStart;

            $pagination = multipage(
                $totalEntries,
                $entriesPerPage,
                $pageCurrent,
                urlHandlerBuild(array_merge($urlParams, ['page' => '{page}']), '&amp;', false)
            );

            // start getting showcases
            $query = $db->simple_select(
                implode(" LEFT JOIN {$db->table_prefix}", $queryTables),
                Implode(',', $queryFields),
                implode(' AND ', $whereClauses),
                $queryOptions,
            );

            // get first attachment for each showcase on this page
            $entryAttachmentsCache = [];

            if ($this->showcase->userEntryAttachmentAsImage) {
                $entryIDs = [];

                while ($entryFieldData = $db->fetch_array($query)) {
                    $entryIDs[] = (int)$entryFieldData['gid'];
                }

                $entryIDs = implode("','", $entryIDs);

                $attachmentObjects = attachmentGet(
                    ["id='{$this->showcase->id}'", "gid IN ('{$entryIDs}')", "visible='1'"],
                    ['gid', 'MIN(aid) as aid', 'filetype', 'filename', 'attachname', 'thumbnail'],
                    // todo, seems like MIN(aid) as aid is unnecessary
                    ['group_by' => 'gid']
                );

                foreach ($attachmentObjects as $attachmentID => $attachmentData) {
                    $entryAttachmentsCache[$attachmentData['gid']] = [
                        'aid' => $attachmentID,
                        'attachname' => $attachmentData['attachname'],
                        'thumbnail' => $attachmentData['thumbnail'],
                        'filetype' => $attachmentData['filetype'],
                        'filename' => $attachmentData['filename'],
                    ];
                }
            }

            //reset results since we may have iterated for attachments.
            $db->data_seek($query, 0);

            $inlineModerationCount = 0;

            while ($entryFieldData = $db->fetch_array($query)) {
                //change style is unapproved
                if (empty($entryFieldData['approved'])) {
                    $alternativeBackground .= ' trow_shaded';
                }

                $entryID = (int)$entryFieldData['gid'];

                $entryFieldData['username'] = $entryFieldData['username'] ?? $lang->guest;

                $entryUsername = $entryUsernameFormatted = htmlspecialchars_uni($entryFieldData['username']);

                $entryViews = my_number_format($entryFieldData['views']);

                $entryUnapproved = ''; // todo, show ({Unapproved}) in the list view

                $viewPagination = ''; // todo, show pagination in the list view

                $viewAttachmentsCount = ''; // todo, show attachment count in the list view

                $entryComments = my_number_format($entryFieldData['comments']);

                $entryDateline = my_date('relative', $entryFieldData['dateline']);

                if (!empty($entryFieldData['uid'])) {
                    $entryUsernameFormatted = build_profile_link(
                        format_name(
                            $entryFieldData['username'],
                            $entryFieldData['usergroup'],
                            $entryFieldData['displaygroup']
                        ),
                        $entryFieldData['uid']
                    );
                }

                $viewLastCommenter = $entryUsernameFormatted; // todo, show last commenter in the list view

                $entryUrl = str_replace('{gid}', (string)$entryID, SHOWCASE_URL_VIEW);

                $viewLastCommentID = 0; // todo, show last comment ID in the list view

                $entryUrlLastComment = str_replace('{gid}', (string)$entryID, SHOWCASE_URL_COMMENT);

                //add bits for search_field highlighting
                if ($showcaseInputSearchKeywords) {
                    $urlBackup = urlHandlerGet();

                    urlHandlerSet($entryUrl);

                    $entryUrl = urlHandlerBuild([
                        //'search_field' => $showcaseInputSearchField,
                        'highlight' => urlencode($showcaseInputSearchKeywords)
                    ]);

                    urlHandlerSet($urlBackup);
                }

                //build link for list view, starting with basic text
                $entryImage = $lang->myShowcaseMainTableTheadView;

                $entryImageText = str_replace('{username}', $entryUsername, $lang->myshowcase_view_user);

                //use default image is specified
                if ($this->showcase->defaultImage != '' && (file_exists(
                            $theme['imgdir'] . '/' . $this->showcase->defaultImage
                        ) || stristr(
                            $theme['imgdir'],
                            'http://'
                        ))) {
                    $urlImage = $mybb->get_asset_url($theme['imgdir'] . '/' . $this->showcase->defaultImage);

                    $entryImage = eval(getTemplate('pageMainTableRowsImage'));
                }

                //use showcase attachment if one exists, scaled of course
                if ($this->showcase->userEntryAttachmentAsImage) {
                    if (stristr($entryAttachmentsCache[$entryFieldData['gid']]['filetype'], 'image/')) {
                        $imagePath = $this->showcase->imageFolder . '/' . $entryAttachmentsCache[$entryFieldData['gid']]['attachname'];

                        if ($entryAttachmentsCache[$entryFieldData['gid']]['aid'] && file_exists($imagePath)) {
                            if ($entryAttachmentsCache[$entryFieldData['gid']]['thumbnail'] == 'SMALL') {
                                $urlImage = $mybb->get_asset_url($imagePath);

                                $entryImage = eval(getTemplate('pageMainTableRowsImage'));
                            } else {
                                $urlImage = $mybb->get_asset_url(
                                    $this->showcase->imageFolder . '/' . $entryAttachmentsCache[$entryFieldData['gid']]['thumbnail']
                                );

                                $entryImage = eval(getTemplate('pageMainTableRowsImage'));
                            }
                        }
                    } else {
                        $attachmentTypes = (array)$cache->read('attachtypes');

                        $attachmentExtension = get_extension(
                            $entryAttachmentsCache[$entryFieldData['gid']]['filename']
                        );

                        if (array_key_exists($attachmentExtension, $attachmentTypes)) {
                            $urlImage = $mybb->get_asset_url($attachmentTypes[$attachmentExtension]['icon']);

                            $entryImage = eval(getTemplate('pageMainTableRowsImage'));
                        }
                    }
                }

                //build custom list items based on field settings
                $showcaseTableRowExtra = '';

                foreach ($showcaseFieldsShow as $fieldKey => $fieldName) {
                    $entryFieldText = $entryFieldData[$fieldName];

                    if ((string)$entryFieldText === '') {
                        $entryFieldText = '';
                    }

                    if (empty($showcaseFieldsParseable[$fieldName])) {
                        // todo, remove this legacy updating the database and updating the format field to TINYINT
                        switch ($showcaseFieldsFormat[$fieldName]) {
                            case 'decimal0':
                                $showcaseFieldsFormat[$fieldName] = 1;
                                break;
                            case 'decimal1':
                                $showcaseFieldsFormat[$fieldName] = 2;
                                break;
                            case 'decimal2':
                                $showcaseFieldsFormat[$fieldName] = 3;
                                break;
                            case 'no':
                                $showcaseFieldsFormat[$fieldName] = 0;
                        }

                        $formatTypes = FORMAT_TYPES;

                        if (!empty($formatTypes[$showcaseFieldsFormat[$fieldName]]) &&
                            function_exists($formatTypes[$showcaseFieldsFormat[$fieldName]])) {
                            $entryFieldText = $formatTypes[$showcaseFieldsFormat[$fieldName]]($entryFieldText);
                        } else {
                            $entryFieldText = match ((int)$showcaseFieldsFormat[$fieldName]) {
                                2 => number_format((float)$entryFieldText, 1),
                                3 => number_format((float)$entryFieldText, 2),
                                default => htmlspecialchars_uni($entryFieldText),
                            };
                        }

                        switch ($showcaseFields[$fieldName]) {
                            case 'date':
                                if ((int)$entryFieldText === 0 || (string)$entryFieldText === '') {
                                    $entryFieldText = '';
                                } else {
                                    $entryFieldDateValue = explode('|', $entryFieldText);

                                    $entryFieldDateValue = array_map('intval', $entryFieldDateValue);

                                    if ($entryFieldDateValue[0] > 0 && $entryFieldDateValue[1] > 0 && $entryFieldDateValue[2] > 0) {
                                        $entryFieldText = my_date(
                                            $mybb->settings['dateformat'],
                                            mktime(
                                                0,
                                                0,
                                                0,
                                                $entryFieldDateValue[0],
                                                $entryFieldDateValue[1],
                                                $entryFieldDateValue[2]
                                            )
                                        );
                                    } else {
                                        $entryFieldText = [];

                                        if (!empty($entryFieldDateValue[0])) {
                                            $entryFieldText[] = $entryFieldDateValue[0];
                                        }

                                        if (!empty($entryFieldDateValue[1])) {
                                            $entryFieldText[] = $entryFieldDateValue[1];
                                        }

                                        if (!empty($entryFieldDateValue[2])) {
                                            $entryFieldText[] = $entryFieldDateValue[2];
                                        }

                                        $entryFieldText = implode('-', $entryFieldText);
                                    }
                                }
                                break;
                        }
                    } else {
                        $entryFieldText = $this->showcase->parseMessage($entryFieldText);
                    }

                    $showcaseTableRowExtra .= eval(getTemplate('pageMainTableRowsExtra'));
                }

                if ($this->showcase->userPermissions[ModeratorPermissions::CanApproveEntries] &&
                    $this->showcase->userPermissions[ModeratorPermissions::CanDeleteEntries]) {
                    $inlineModerationCheckElement = '';

                    if (isset($mybb->cookies['inlinemod_showcase' . $this->showcase->id]) &&
                        my_strpos(
                            "|{$mybb->cookies['inlinemod_showcase' . $this->showcase->id]}|",
                            "|{$entryID}|"
                        ) !== false) {
                        $inlineModerationCheckElement = 'checked="checked"';

                        ++$inlineModerationCount;
                    }

                    $showcaseTableRowInlineModeration = eval(getTemplate('pageMainTableRowInlineModeration'));
                }

                $showcaseEntriesList .= eval(getTemplate('pageMainTableRows'));

                //add row indicating report
                if (!empty($reports) && is_array($reports[$entryFieldData['gid']])) {
                    foreach ($reports[$entryFieldData['gid']] as $rid => $report) {
                        $entryReportDate = my_date($mybb->settings['dateformat'], $report['dateline']);

                        $entryReportTime = my_date($mybb->settings['timeformat'], $report['dateline']);

                        $entryReportUserData = get_user($report['uid']);

                        $entryReportUserProfileLink = build_profile_link(
                            $entryReportUserData['username'],
                            $entryReportUserData['uid'],
                        );

                        $alternativeBackground .= ' red_alert';

                        $message = $lang->sprintf(
                            $lang->myshowcase_report_item,
                            $entryReportDate . ' ' . $entryReportTime,
                            $entryReportUserProfileLink,
                            $report['reason']
                        );

                        $showcaseColumnsCount += count($showcaseFieldsShow);

                        $showcaseEntriesList .= eval(getTemplate('pageMainTableReport'));
                    }
                }

                $alternativeBackground = alt_trow();
            }
        } else {
            //$colcount = 5;

            if ($this->showcase->userPermissions[ModeratorPermissions::CanApproveEntries] &&
                $this->showcase->userPermissions[ModeratorPermissions::CanDeleteEntries]) {
                // ++$colcount;
            }

            //$showcaseColumnsCount = $colcount + count($showcaseFieldsShow);

            if (!$showcaseInputSearchKeywords) {
                $message = $lang->myShowcaseMainTableEmpty;
            } else {
                $message = $lang->myShowcaseMainTableEmptySearch;
            }

            $showcaseEntriesList .= eval(getTemplate('pageMainTableEmpty'));
        }

        $pageTitle = $this->showcase->name;

        $urlSortByUsername = urlHandlerBuild(array_merge($urlParams, ['sort_by' => 'username']));

        $urlSortByComments = urlHandlerBuild(array_merge($urlParams, ['sort_by' => 'comments']));

        $urlSortByViews = urlHandlerBuild(array_merge($urlParams, ['sort_by' => 'views']));

        $urlSortByCreateDate = urlHandlerBuild(array_merge($urlParams, ['sort_by' => 'dateline']));

        $pageContents = eval(getTemplate('pageMainContents'));

        $pageContents = eval(getTemplate('page'));

        $hookArguments = hooksRun('output_main_end', $hookArguments);

        output_page($pageContents);

        exit;
    }

    #[NoReturn] public function newEdit(bool $isEditPage = false): void
    {
        global $lang, $mybb, $db, $cache;
        global $header, $headerinclude, $footer, $theme;

        global $showcaseFieldsOrder, $showcaseFieldsSearchable, $currentPage, $amp, $urlSort, $showcaseInputSortBy;
        global $showcaseInputSearchExactMatch, $showcaseFieldsShow, $showcaseFields, $unapproved;
        global $showcaseInputSearchKeywords, $urlParams, $urlBase, $showcaseInputSearchField, $showcaseColumnsCount;
        global $showcaseFieldsFormat, $showcaseName, $showcaseTableTheadInlineModeration, $urlShowcase, $showcase_url;
        global $showcaseInlineModeration, $buttonGo, $orderInput, $showcaseInputOrder, $showcaseFieldsParseable;

        global $showcase_data, $entryHash, $showcaseFieldEnabled;
        global $showcaseFieldsMaximumLength, $showcaseFieldsMinimumLength, $showcaseFieldsRequired;
        global $entryID;

        global $mybb, $lang, $db, $templates, $plugins;
        global $showcaseFieldsMaximumLength, $showcaseFieldsFormat, $showcaseFieldsRequired, $showcaseFieldsMinimumLength, $showcase_url;


        global $entryID, $entryHash, $showcase_data;

        $hookArguments = [];

        $showcaseUserID = $this->showcase->currentUserID;

        $showcaseUserData = $mybb->user;

        if ($isEditPage && $this->showcase->currentUserID != $mybb->get_input('authid', MyBB::INPUT_INT)) {
            //get showcase author info
            $showcaseUserID = (int)$mybb->get_input('authid', MyBB::INPUT_INT);

            $showcaseUserData = get_user($showcaseUserID);
        }

        $showcaseUserPermissions = $this->showcase->userPermissionsGet($showcaseUserID);

        if ($mybb->request_method === 'post') {
            verify_post_check($mybb->get_input('my_post_key'));

            if ($isEditPage) {
                $showcaseUserData = showcaseDataGet($this->showcase->id, ["gid='{$entryID}'"], ['uid']);

                if (!$showcaseUserData) {
                    error($lang->myshowcase_invalid_id);
                }

                //get posters info
                $showcaseUserData = get_user($showcaseUserData['uid']);

                //set value for author id in form hidden fields so we know if current user is author
                $showcase_authid = $showcaseUserData['uid'];
            } else {
                if ($mybb->get_input('action') == 'new') {
                    add_breadcrumb($lang->myShowcaseButtonNewEntry, SHOWCASE_URL);
                    $showcase_action = 'new';

                    //need to populated a default user value here for new entries
                    $showcase_data['uid'] = $this->showcase->currentUserID;
                } else {
                    $showcase_editing_user = str_replace(
                        '{username}',
                        $showcaseUserData['username'],
                        $lang->myshowcase_editing_user
                    );
                    add_breadcrumb($showcase_editing_user, SHOWCASE_URL);
                    $showcase_action = 'edit';
                }

                // Get a listing of the current attachments.
                if (!empty($showcaseUserPermissions[UserPermissions::CanAttachFiles])) {
                    $attachcount = 0;

                    $attachments = '';

                    $attachmentObjects = attachmentGet(
                        ["posthash='{$db->escape_string($entryHash)}'", "id={$this->showcase->id}"],
                        array_keys(TABLES_DATA['myshowcase_attachments'])
                    );

                    foreach ($attachmentObjects as $attachmentID => $attachment) {
                        $attachment['size'] = get_friendly_size($attachment['filesize']);
                        $attachment['icon'] = get_attachment_icon(get_extension($attachment['filename']));
                        $attachment['icon'] = str_replace(
                            '<img src="',
                            '<img src="' . $mybb->settings['bburl'] . '/',
                            $attachment['icon']
                        );


                        $attach_mod_options = '';
                        if ($attachment['visible'] != 1) {
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
                        if ($this->showcase->userPermissions[UserPermissions::CanWaterMarkAttachments]) {
                            $showcase_watermark = eval(getTemplate('watermark'));
                        }
                        $showcase_new_attachments_input = eval(getTemplate('new_attachments_input'));
                    }
                    $showcase_attachments = eval(getTemplate('new_attachments'));
                }

                if ($mybb->request_method == 'post' && $mybb->get_input('submit')) {
                    // Decide on the visibility of this post.
                    if ($this->showcase->moderateEdits && !$this->showcase->userPermissions[ModeratorPermissions::CanApproveEntries]) {
                        $approved = 0;
                        $approved_by = 0;
                    } else {
                        $approved = 1;
                        $approved_by = $this->showcase->currentUserID;
                    }

                    $plugins->run_hooks('myshowcase_do_newedit_start');

                    // Set up showcase handler.
                    require_once MYBB_ROOT . 'inc/datahandlers/myshowcase_dh.php';
                    if ($mybb->get_input('action') == 'edit') {
                        $showcasehandler = new MyShowcaseDataHandler($this->showcase, 'update');
                        $showcasehandler->action = 'edit';
                    } else {
                        $showcasehandler = new MyShowcaseDataHandler($this->showcase);
                        $showcasehandler->action = 'new';
                    }

                    //This is where the work is done

                    // Verify incoming POST request
                    verify_post_check($mybb->get_input('my_post_key'));

                    // Set the post data that came from the input to the $post array.
                    $default_data = [
                        'uid' => $showcase_data['uid'],
                        'dateline' => TIME_NOW,
                        'approved' => $approved,
                        'approved_by' => $approved_by,
                        'posthash' => $entryHash
                    ];

                    //add showcase id if editing so we know what to update
                    if ($mybb->get_input('action') == 'edit') {
                        $default_data = array_merge(
                            $default_data,
                            ['gid' => intval($entryID)]
                        );
                    }

                    //add showcase specific fields
                    reset($showcaseFieldEnabled);
                    $submitted_data = [];
                    foreach ($showcaseFieldEnabled as $fname => $ftype) {
                        if ($ftype == 'db' || $ftype == 'radio') {
                            $submitted_data[$fname] = intval($mybb->get_input('myshowcase_field_' . $fname));
                        } elseif ($ftype == 'checkbox') {
                            $submitted_data[$fname] = (isset($mybb->input['myshowcase_field_' . $fname]) ? 1 : 0);
                        } elseif ($ftype == 'date') {
                            $m = $db->escape_string($mybb->get_input('myshowcase_field_' . $fname . '_m'));
                            $d = $db->escape_string($mybb->get_input('myshowcase_field_' . $fname . '_d'));
                            $y = $db->escape_string($mybb->get_input('myshowcase_field_' . $fname . '_y'));
                            $submitted_data[$fname] = $m . '|' . $d . '|' . $y;
                        } else {
                            $submitted_data[$fname] = $db->escape_string(
                                $mybb->get_input('myshowcase_field_' . $fname)
                            );
                        }
                    }

                    //redefine the showcase_data
                    $showcase_data = array_merge($default_data, $submitted_data);

                    //send data to handler
                    $showcasehandler->set_data($showcase_data);

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
                        if ($mybb->get_input('action') == 'edit') {
                            $insert_showcase = $showcasehandler->update_showcase();
                            $showcaseid = intval($entryID);
                        } //insert showcase
                        else {
                            $insert_showcase = $showcasehandler->insert_showcase();
                            $showcaseid = $insert_showcase['gid'];
                        }

                        $plugins->run_hooks('myshowcase_do_newedit_end');

                        //fix url insert variable to update results
                        $entryUrl = str_replace('{gid}', (string)$showcaseid, SHOWCASE_URL_VIEW);

                        $redirect_newshowcase = $lang->redirect_myshowcase_new . '' . $lang->redirect_myshowcase . '' . $lang->sprintf(
                                $lang->redirect_myshowcase_return,
                                $showcase_url
                            );
                        redirect($entryUrl, $redirect_newshowcase);
                        exit;
                    }
                } else {
                    $plugins->run_hooks('myshowcase_newedit_start');

                    $pageContents .= eval(getTemplate('new_top'));

                    reset($showcaseFieldEnabled);
                    foreach ($showcaseFieldEnabled as $fname => $ftype) {
                        $temp = 'myshowcase_field_' . $fname;
                        $field_header = !empty($lang->$temp) ? $lang->$temp : $fname;

                        $alternativeBackground = ($alternativeBackground == 'trow1' ? 'trow2' : 'trow1');

                        if ($mybb->get_input('action') == 'edit') {
                            $mybb->input['myshowcase_field_' . $fname] = htmlspecialchars_uni(
                                stripslashes($showcase_data[$fname])
                            );
                        }

                        switch ($ftype) {
                            case 'textbox':
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fname;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                                $showcase_field_enabled = '';//($showcaseFieldEnabled[$fname] != 1 ? 'disabled' : '');
                                $showcase_field_checked = '';
                                $showcase_field_options = 'maxlength="' . $showcaseFieldsMaximumLength[$fname] . '"';
                                $showcase_field_input = eval(getTemplate('field_textbox'));

                                if ($showcaseFieldsFormat[$fname] != 'no') {
                                    $showcase_field_input .= '&nbsp;' . $lang->myshowcase_editing_number;
                                }
                                break;

                            case 'url':
                                $showcase_field_width = 150;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fname;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                                $showcase_field_enabled = '';//($showcaseFieldEnabled[$fname] != 1 ? 'disabled' : '');
                                $showcase_field_checked = '';
                                $showcase_field_options = 'maxlength="' . $showcaseFieldsMaximumLength[$fname] . '"';
                                $showcase_field_input = eval(getTemplate('field_textbox'));
                                break;

                            case 'textarea':
                                $showcase_field_width = 100;
                                $showcase_field_rows = $showcaseFieldsMaximumLength[$fname];
                                $showcase_field_name = 'myshowcase_field_' . $fname;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                                $showcase_field_enabled = '';//($showcaseFieldEnabled[$fname] != 1 ? 'disabled' : '');
                                $showcase_field_checked = '';
                                $showcase_field_options = '';
                                $showcase_field_input = eval(getTemplate('field_textarea'));
                                break;

                            case 'radio':
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fname;
                                $showcase_field_enabled = '';//($showcaseFieldEnabled[$fname] != 1 ? 'disabled' : '');
                                $showcase_field_options = '';

                                $fieldDataObjects = fieldDataGet(
                                    ["setid='{$this->showcase->fieldSetID}'", "name='{$fname}'", "valueid!='0'"],
                                    ['valueid', 'value'],
                                    ['order_by' => 'disporder']
                                );

                                if (!$fieldDataObjects) {
                                    error($lang->myshowcase_db_no_data);
                                }

                                $showcase_field_input = '';
                                foreach ($fieldDataObjects as $fieldDataID => $results) {
                                    $showcase_field_value = $results['valueid'];
                                    $showcase_field_checked = ($mybb->get_input(
                                        'myshowcase_field_' . $fname
                                    ) == $results['valueid'] ? ' checked' : '');
                                    $showcase_field_text = $results['value'];
                                    $showcase_field_input .= eval(getTemplate('field_radio'));
                                }
                                break;

                            case 'checkbox':
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fname;
                                $showcase_field_value = '1';
                                $showcase_field_enabled = '';//($showcaseFieldEnabled[$fname] != 1 ? 'disabled' : '');
                                $showcase_field_checked = ($mybb->get_input(
                                    'myshowcase_field_' . $fname
                                ) == 1 ? ' checked="checked"' : '');
                                $showcase_field_options = '';
                                $showcase_field_input = eval(getTemplate('field_checkbox'));
                                break;

                            case 'db':
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fname;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                                $showcase_field_enabled = '';//($showcaseFieldEnabled[$fname] != 1 ? 'disabled' : '');
                                $showcase_field_checked = '';

                                $fieldDataObjects = fieldDataGet(
                                    ["setid='{$this->showcase->fieldSetID}'", "name='{$fname}'", "valueid!='0'"],
                                    ['valueid', 'value'],
                                    ['order_by' => 'disporder']
                                );

                                if (!$fieldDataObjects) {
                                    error($lang->myshowcase_db_no_data);
                                }

                                $showcase_field_options = ($mybb->get_input(
                                    'action'
                                ) == 'new' ? '<option value=0>&lt;Select&gt;</option>' : '');
                                foreach ($fieldDataObjects as $fieldDataID => $results) {
                                    $showcase_field_options .= '<option value="' . $results['valueid'] . '" ' . ($showcase_field_value == $results['valueid'] ? ' selected' : '') . '>' . $results['value'] . '</option>';
                                }
                                $showcase_field_input = eval(getTemplate('field_db'));
                                break;

                            case 'date':
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fname;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                                $showcase_field_enabled = '';//($showcaseFieldEnabled[$fname] != 1 ? 'disabled' : '');
                                $showcase_field_checked = '';

                                $showcase_field_value_m = ($mybb->get_input(
                                    'myshowcase_field_' . $fname . '_m'
                                ) == '00' ? '00' : $mybb->get_input('myshowcase_field_' . $fname . '_m'));
                                $showcase_field_value_d = ($mybb->get_input(
                                    'myshowcase_field_' . $fname . '_d'
                                ) == '00' ? '00' : $mybb->get_input('myshowcase_field_' . $fname . '_d'));
                                $showcase_field_value_y = ($mybb->get_input(
                                    'myshowcase_field_' . $fname . '_y'
                                ) == '0000' ? '0000' : $mybb->get_input('myshowcase_field_' . $fname . '_y'));

                                $showcase_field_options_m = '<option value=0' . ($showcase_field_value_m == '00' ? ' selected' : '') . '>&nbsp;</option>';
                                $showcase_field_options_d = '<option value=0' . ($showcase_field_value_d == '00' ? ' selected' : '') . '>&nbsp;</option>';
                                $showcase_field_options_y = '<option value=0' . ($showcase_field_value_y == '0000' ? ' selected' : '') . '>&nbsp;</option>';
                                for ($i = 1; $i <= 12; $i++) {
                                    $showcase_field_options_m .= '<option value="' . substr(
                                            '0' . $i,
                                            -2
                                        ) . '" ' . ($showcase_field_value_m == substr(
                                            '0' . $i,
                                            -2
                                        ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                                }
                                for ($i = 1; $i <= 31; $i++) {
                                    $showcase_field_options_d .= '<option value="' . substr(
                                            '0' . $i,
                                            -2
                                        ) . '" ' . ($showcase_field_value_d == substr(
                                            '0' . $i,
                                            -2
                                        ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                                }
                                for ($i = $showcaseFieldsMaximumLength[$fname]; $i >= $showcaseFieldsMinimumLength[$fname]; $i--) {
                                    $showcase_field_options_y .= '<option value="' . $i . '" ' . ($showcase_field_value_y == $i ? ' selected' : '') . '>' . $i . '</option>';
                                }
                                $showcase_field_input = eval(getTemplate('field_date'));
                                break;
                        }

                        $field_header = ($showcaseFieldsRequired[$fname] ? '<strong>' . $field_header . ' *</strong>' : $field_header);
                        $pageContents .= eval(getTemplate('new_fields'));
                    }

                    $plugins->run_hooks('myshowcase_newedit_end');

                    $pageContents .= eval(getTemplate('new_bottom'));
                }
            }
        }

        if ($mybb->get_input('action') == 'new') {
            add_breadcrumb($lang->myShowcaseButtonNewEntry, SHOWCASE_URL);
        } elseif ($mybb->get_input('action') == 'edit') {
            $showcase_editing_user = str_replace(
                '{username}',
                (string)$showcaseUserData['username'],
                $lang->myshowcase_editing_user
            );
            add_breadcrumb($showcase_editing_user, SHOWCASE_URL);
        }

        if ($isEditPage) {
            if (!$this->showcase->userPermissions[UserPermissions::CanEditEntries]) {
                error($lang->myshowcase_not_authorized);
            }
        } elseif (!$this->showcase->userPermissions[UserPermissions::CanAddEntries]) {
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
                ["posthash='{$db->escape_string($entryHash)}'"],
                array_keys(TABLES_DATA['myshowcase_attachments'])
            );

            foreach ($attachmentObjects as $attachmentID => $attachment) {
                $attachment['size'] = get_friendly_size($attachment['filesize']);
                $attachment['icon'] = get_attachment_icon(get_extension($attachment['filename']));
                $attachment['icon'] = str_replace(
                    '<img src="',
                    '<img src="' . $mybb->settings['bburl'] . '/',
                    $attachment['icon']
                );

                $attach_mod_options = '';
                if ($attachment['visible'] != 1) {
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
                if ($this->showcase->userPermissions[UserPermissions::CanWaterMarkAttachments] && $this->showcase->waterMarkImage != '' && file_exists(
                        $this->showcase->waterMarkImage
                    )) {
                    $showcase_watermark = eval(getTemplate('watermark'));
                }
                $showcase_new_attachments_input = eval(getTemplate('new_attachments_input'));
            }

            if (!empty($showcase_new_attachments_input) || $attachments != '') {
                $attachmentsTable = eval(getTemplate('pageNewAttachments'));
            }
        }

        $alternativeBackground = 'trow2';

        reset($showcaseFieldEnabled);

        $showcaseFields = '';

        $fieldTabIndex = 1;

        foreach ($showcaseFieldEnabled as $fieldName => $fieldType) {
            $fieldKey = "myshowcase_field_{$fieldName}";

            $fieldTitle = $fieldName;

            if (isset($lang->{$fieldKey})) {
                $fieldTitle = $lang->{$fieldKey};
            }

            $alternativeBackground = ($alternativeBackground == 'trow1' ? 'trow2' : 'trow1');

            if ($mybb->get_input('action') == 'edit') {
                $mybb->input[$fieldKey] = htmlspecialchars_uni(
                    stripslashes($showcase_data[$fieldName])
                );
            }

            $fieldElementRequired = '';

            if (isset($showcaseFieldsRequired[$fieldName])) {
                $fieldElementRequired = 'required="required"';
            }

            $minimumLength = $showcaseFieldsMinimumLength[$fieldName] ?? '';

            $maximumLength = $showcaseFieldsMaximumLength[$fieldName] ?? '';

            switch ($fieldType) {
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
                    $showcase_field_enabled = '';// ($showcaseFieldEnabled[$fieldName] != 1 ? 'disabled' : '');
                    $showcase_field_options = '';

                    $fieldObjects = fieldDataGet(
                        ["setid='{$this->showcase->fieldSetID}'", "name='{$fieldName}'", "valueid!='0'"],
                        ['valueid', 'value'],
                        ['order_by' => 'disporder']
                    );

                    if (!$fieldObjects) {
                        error($lang->myshowcase_db_no_data);
                    }

                    $fieldInput = '';

                    foreach ($fieldObjects as $fieldDataID => $fieldData) {
                        $fieldValue = $fieldData['valueid'];

                        $showcase_field_checked = ($mybb->get_input(
                            $fieldKey
                        ) == $fieldData['valueid'] ? ' checked' : '');

                        $showcase_field_text = $fieldData['value'];

                        $fieldInput .= eval(getTemplate('field_radio'));
                    }

                    break;

                case FIELD_TYPE_HTML_CHECK_BOX:
                    $showcase_field_width = 50;
                    $showcase_field_rows = '';
                    $fieldValue = '1';

                    $showcase_field_checked = ($mybb->get_input($fieldKey) == 1 ? ' checked="checked"' : '');
                    $showcase_field_options = '';
                    $fieldInput = eval(getTemplate('field_checkbox'));
                    break;

                case FIELD_TYPE_HTML_DB:
                    $showcase_field_width = 50;
                    $showcase_field_rows = '';
                    $fieldValue = $mybb->get_input($fieldKey);

                    $showcase_field_checked = '';

                    $fieldDataObjects = fieldDataGet(
                        ["setid='{$this->showcase->fieldSetID}'", "name='{$fieldName}'", "valueid!='0'"],
                        ['valueid', 'value'],
                        ['order_by' => 'disporder']
                    );

                    if (!$fieldDataObjects) {
                        error($lang->myshowcase_db_no_data);
                    }

                    $showcase_field_options = ($mybb->get_input(
                        'action'
                    ) == 'new' ? '<option value=0>&lt;Select&gt;</option>' : '');
                    foreach ($fieldDataObjects as $fieldDataID => $results) {
                        $showcase_field_options .= '<option value="' . $results['valueid'] . '" ' . ($fieldValue == $results['valueid'] ? ' selected' : '') . '>' . $results['value'] . '</option>';
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
                    ) == '00' ? '00' : $mybb->get_input($fieldKey . '_m'));
                    $showcase_field_value_d = ($mybb->get_input(
                        $fieldKey . '_d'
                    ) == '00' ? '00' : $mybb->get_input($fieldKey . '_d'));
                    $showcase_field_value_y = ($mybb->get_input(
                        $fieldKey . '_y'
                    ) == '0000' ? '0000' : $mybb->get_input($fieldKey . '_y'));

                    $showcase_field_options_m = '<option value=00' . ($showcase_field_value_m == '00' ? ' selected' : '') . '>&nbsp;</option>';
                    $showcase_field_options_d = '<option value=00' . ($showcase_field_value_d == '00' ? ' selected' : '') . '>&nbsp;</option>';
                    $showcase_field_options_y = '<option value=0000' . ($showcase_field_value_y == '0000' ? ' selected' : '') . '>&nbsp;</option>';
                    for ($i = 1; $i <= 12; $i++) {
                        $showcase_field_options_m .= '<option value="' . substr(
                                '0' . $i,
                                -2
                            ) . '" ' . ($showcase_field_value_m == substr(
                                '0' . $i,
                                -2
                            ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                    }
                    for ($i = 1; $i <= 31; $i++) {
                        $showcase_field_options_d .= '<option value="' . substr(
                                '0' . $i,
                                -2
                            ) . '" ' . ($showcase_field_value_d == substr(
                                '0' . $i,
                                -2
                            ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                    }
                    for ($i = $showcaseFieldsMaximumLength[$fieldName]; $i >= $showcaseFieldsMinimumLength[$fieldName]; $i--) {
                        $showcase_field_options_y .= '<option value="' . $i . '" ' . ($showcase_field_value_y == $i ? ' selected' : '') . '>' . $i . '</option>';
                    }
                    $fieldInput = eval(getTemplate('field_date'));
                    break;
            }

            $showcaseFields .= eval(getTemplate('pageNewField'));

            ++$fieldTabIndex;
        }

        $showcase_authid = '';

        $pageTitle = $this->showcase->name;

        $pageContents = eval(getTemplate('pageNewContents'));

        $pageContents = eval(getTemplate('page'));

        $hookArguments = hooksRun('output_new_end', $hookArguments);

        output_page($pageContents);

        exit;
    }

    #[NoReturn] public function edit(): void
    {
        global $lang;

        if (!$entryID || $entryID == '') {
            error($lang->myshowcase_invalid_id);
        }

        $entryFieldData = showcaseDataGet($this->showcase->id, ["gid='{$entryID}'"], ['uid', 'posthash']);

        if (!$entryFieldData) {
            error($lang->myshowcase_invalid_id);
        }

        //make sure current user is moderator or the myshowcase author
        if (!$this->showcase->userPermissions[ModeratorPermissions::CanEditEntries] && $this->showcase->currentUserID != $entryFieldData['uid']) {
            error($lang->myshowcase_not_authorized);
        }

        //since its possible for a mod to edit another user's showcase, we need to get authors info/permimssions
        //get showcase author info
        $showcaseUserData = get_user($entryFieldData['uid']);

        //set value for author id in form hidden fields so we know if current user is author
        $showcase_authid = $showcaseUserData['uid'];

        //get permissions for user
        $showcase_authorperms = $this->showcase->userPermissionsGet($showcaseUserID);

        $entryHash = $entryFieldData['posthash'];
        //no break since edit will share NEW code
    }
}