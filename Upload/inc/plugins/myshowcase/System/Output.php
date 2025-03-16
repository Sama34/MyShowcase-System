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

namespace inc\plugins\myshowcase\System;

use inc\plugins\myshowcase\Showcase;
use JetBrains\PhpStorm\NoReturn;

use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\getTemplate;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\Core\urlHandlerBuild;
use function MyShowcase\Core\urlHandlerGet;
use function MyShowcase\Core\urlHandlerSet;

use const MyShowcase\System\PERMISSION_MODERATOR_CAN_APPROVE;
use const MyShowcase\System\PERMISSION_MODERATOR_CAN_DELETE;
use const MyShowcase\System\PERMISSION_USER_CAN_NEW_ENTRY;

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

        global $plugins;
        global $showcaseFieldsOrder, $showcaseFieldsSearchable, $currentPage, $amp, $urlSort, $showcaseInputSortBy;
        global $showcaseInputSearchExactMatch, $showcaseFieldsShow, $showcaseFields, $unapproved;
        global $showcaseInputSearchKeywords, $urlParams, $urlBase, $showcaseInputSearchField, $showcaseColumnsCount;
        global $showcaseFieldsFormat, $showcaseName, $showcaseTableTheadInlineModeration, $urlShowcase, $showcase_url;
        global $showcaseInlineModeration, $buttonGo, $orderInput, $showcaseInputOrder, $showcaseFieldsParseable;

        $hookArguments = [];

        $hookArguments = hooksRun('output_main_start', $hookArguments);

        $buttonNewEntry = '';

        if ($this->showcase->permissionCheck(PERMISSION_USER_CAN_NEW_ENTRY)) {
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

        if ($this->showcase->seo_support) {
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

        $queryTables = ["{$this->showcase->table_name} g"];

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

                    $whereClauses[] = "tbl_{$fieldName}.setid='{$this->showcase->fieldsetid}'";
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

            if ($this->showcase->use_attach) {
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
                if ($this->showcase->defaultimage != '' && (file_exists(
                            $theme['imgdir'] . '/' . $this->showcase->defaultimage
                        ) || stristr(
                            $theme['imgdir'],
                            'http://'
                        ))) {
                    $urlImage = $mybb->get_asset_url($theme['imgdir'] . '/' . $this->showcase->defaultimage);

                    $entryImage = eval(getTemplate('pageMainTableRowsImage'));
                }

                //use showcase attachment if one exists, scaled of course
                if ($this->showcase->use_attach) {
                    if (stristr($entryAttachmentsCache[$entryFieldData['gid']]['filetype'], 'image/')) {
                        $imagePath = $this->showcase->imgfolder . '/' . $entryAttachmentsCache[$entryFieldData['gid']]['attachname'];

                        if ($entryAttachmentsCache[$entryFieldData['gid']]['aid'] && file_exists($imagePath)) {
                            if ($entryAttachmentsCache[$entryFieldData['gid']]['thumbnail'] == 'SMALL') {
                                $urlImage = $mybb->get_asset_url($imagePath);

                                $entryImage = eval(getTemplate('pageMainTableRowsImage'));
                            } else {
                                $urlImage = $mybb->get_asset_url(
                                    $this->showcase->imgfolder . '/' . $entryAttachmentsCache[$entryFieldData['gid']]['thumbnail']
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

                        $formatTypes = \MyShowcase\Core\FORMAT_TYPES;

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
                        $entryFieldText = $this->showcase->parse_message($entryFieldText);
                    }

                    $showcaseTableRowExtra .= eval(getTemplate('pageMainTableRowsExtra'));
                }

                if ($this->showcase->permissionCheck(PERMISSION_MODERATOR_CAN_APPROVE) &&
                    $this->showcase->permissionCheck(PERMISSION_MODERATOR_CAN_DELETE)) {
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

            if ($this->showcase->permissionCheck(PERMISSION_MODERATOR_CAN_APPROVE) &&
                $this->showcase->permissionCheck(PERMISSION_MODERATOR_CAN_DELETE)) {
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

        $pageTitle = $this->showcase->description;

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
}