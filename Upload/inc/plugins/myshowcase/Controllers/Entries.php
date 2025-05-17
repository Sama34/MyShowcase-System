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
use MyShowcase\System\FieldHtmlTypes;
use MyShowcase\System\ModeratorPermissions;
use MyShowcase\System\UserPermissions;

use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\attachmentUpload;
use function MyShowcase\Core\cacheUpdate;
use function MyShowcase\Core\cleanSlug;
use function MyShowcase\Core\commentsGet;
use function MyShowcase\Core\fieldGetObject;
use function MyShowcase\Core\generateUUIDv4;
use function MyShowcase\Core\dataHandlerGetObject;
use function MyShowcase\Core\dataTableStructureGet;
use function MyShowcase\Core\entryGet;
use function MyShowcase\Core\fieldDataGet;
use function MyShowcase\Core\formatField;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\Core\urlHandlerBuild;
use function MyShowcase\SimpleRouter\url;

use const MyShowcase\Core\CACHE_TYPE_ATTACHMENT_TYPES;
use const MyShowcase\Core\HTTP_CODE_PERMANENT_REDIRECT;
use const MyShowcase\Core\URL_TYPE_ATTACHMENT_VIEW;
use const MyShowcase\Core\URL_TYPE_ENTRY_VIEW_PAGE;
use const MyShowcase\Core\URL_TYPE_MAIN_PAGE;
use const MyShowcase\Core\ATTACHMENT_THUMBNAIL_SMALL;
use const MyShowcase\Core\URL_TYPE_ENTRY_UPDATE;
use const MyShowcase\Core\COMMENT_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\COMMENT_STATUS_SOFT_DELETED;
use const MyShowcase\Core\COMMENT_STATUS_VISIBLE;
use const MyShowcase\Core\FILTER_TYPE_USER_ID;
use const MyShowcase\Core\URL_TYPE_COMMENT_CREATE;
use const MyShowcase\Core\URL_TYPE_ENTRY_CREATE;
use const MyShowcase\Core\URL_TYPE_ENTRY_VIEW;
use const MyShowcase\Core\URL_TYPE_MAIN;
use const MyShowcase\Core\URL_TYPE_MAIN_USER;
use const MyShowcase\Core\DATA_HANDLER_METHOD_UPDATE;
use const MyShowcase\Core\DATA_TABLE_STRUCTURE;
use const MyShowcase\Core\ENTRY_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\ENTRY_STATUS_SOFT_DELETED;
use const MyShowcase\Core\ENTRY_STATUS_VISIBLE;
use const MyShowcase\Core\ORDER_DIRECTION_ASCENDING;
use const MyShowcase\Core\ORDER_DIRECTION_DESCENDING;
use const MyShowcase\Core\URL_TYPE_THUMBNAIL_VIEW;

class Entries extends Base
{
    public const NO_PERMISSION = 1;

    public const INVALID_ENTRY = 2;

    public const HAS_PERMISSION = 3;

    public const STATUS_PENDING_APPROVAL = 0;

    public const STATUS_VISIBLE = 1;

    public const STATUS_SOFT_DELETE = 2;

    public const STATUS_DELETE = 3;

    public function __construct()
    {
        parent::__construct();

        if (!$this->showcaseObject->userPermissions[UserPermissions::CanView]) {
            error_no_permission();
        }
    }

    public int $entryID = 0;

    public array $entryData = [];

    public function verifyPermission(string $entrySlug, array $entryFields = []): int
    {
        global $db;

        if (!(
            $this->showcaseObject->userPermissions[UserPermissions::CanViewEntries] ||
            $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries]
        )) {
            return self::NO_PERMISSION;
        }

        global $mybb;

        $currentUserID = (int)$mybb->user['uid'];

        $entryData = entryGet(
            $this->showcaseObject->showcase_id,
            ["entry_slug='{$db->escape_string($entrySlug)}'"],
            array_merge(['entry_id', 'status', 'user_id'], $entryFields),
            ['limit' => 1]
        );

        if (empty($entryData)) {
            return self::INVALID_ENTRY;
        }

        $entryStatus = (int)$entryData['status'];

        $entryUserID = (int)$entryData['user_id'];

        if ($entryStatus === ENTRY_STATUS_PENDING_APPROVAL && $currentUserID !== $entryUserID ||
            $entryStatus === ENTRY_STATUS_SOFT_DELETED && !$this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries]) {
            return self::INVALID_ENTRY;
        }

        $this->entryID = (int)$entryData['entry_id'];

        $this->entryData = $entryData;

        return self::HAS_PERMISSION;
    }

    #[NoReturn] public function redirect(string $entrySlug): void
    {
        switch ($this->verifyPermission($entrySlug)) {
            case self::NO_PERMISSION:
                error_no_permission();

                break;
            case self::INVALID_ENTRY:
                global $lang;

                error($lang->myShowcaseReportErrorInvalidEntry);
        }

        $entryUrl = $this->showcaseObject->urlGetEntry($entrySlug, $entrySlugCustom);

        \MyShowcase\SimpleRouter\redirect($entryUrl, HTTP_CODE_PERMANENT_REDIRECT);

        exit;
    }

    #[NoReturn] public function mainPage(
        int $currentPage = 1,
        int $userID = 0,
        int $limit = 0,
        int $limitStart = 0,
        string $groupBy = '',
        string $orderBy = 'user_id',
        string $orderDirection = ORDER_DIRECTION_ASCENDING,
        string $filterField = '',
        mixed $filterValue = '',
        array $whereClauses = []
    ): void {
        $this->mainView(userID: $userID, currentPage: $currentPage);
    }

    #[NoReturn] public function mainView(
        int $userID = 0,
        int $limit = 0,
        int $limitStart = 0,
        string $groupBy = '',
        string $orderBy = 'user_id',
        string $orderDirection = ORDER_DIRECTION_ASCENDING,
        string $filterField = '',
        mixed $filterValue = '',
        array $whereClauses = [],
        int $currentPage = 1,
    ): void {
        global $lang, $mybb, $db;
        global $theme;

        $currentUserID = (int)$mybb->user['uid'];

        if ($this->showcaseObject->config['entries_per_page'] < 1) {
            $this->showcaseObject->config['entries_per_page'] = $this->showcaseObject->config['entries_per_page'];
        }

        $hookArguments = [];

        $hookArguments = hooksRun('output_main_start', $hookArguments);

        $entryCreateButton = '';

        $displayCreateButton = $this->showcaseObject->userPermissions[UserPermissions::CanCreateEntries];
        /*
                switch ($this->showcaseObject->config['filter_force_field']) {
                    case FILTER_TYPE_USER_ID:
                        $displayCreateButton = $displayCreateButton && $userID === $currentUserID;

                        $whereClauses[] = "user_id='{$userID}'";

                        //$this->showcaseObject->urlParams['user_id'] = $userID;

                        $userData = get_user($userID);

                        if (empty($userData['uid']) || empty($mybb->usergroup['canviewprofiles'])) {
                            error_no_permission();
                        }

                        $lang->load('member');

                        $userName = htmlspecialchars_uni($userData['username']);

                        add_breadcrumb(
                            $lang->sprintf($lang->nav_profile, $userName),
                            $mybb->settings['bburl'] . '/' . get_profile_link($userData['uid'])
                        );

                        break;
                }
        */
        $mainUrl = url(
            URL_TYPE_MAIN,
            getParams: $this->showcaseObject->urlParams
        )->getRelativeUrl();

        add_breadcrumb(
            $this->showcaseObject->config['name_friendly'],
            $mainUrl
        );

        if ($displayCreateButton) {
            $entryCreateUrl = url(URL_TYPE_ENTRY_CREATE, getParams: $this->showcaseObject->urlParams)->getRelativeUrl();

            $entryCreateButton = eval($this->renderObject->templateGet('buttonNewEntry'));
        }

        $showcaseSelectOrderAscendingSelectedElement = $showcaseSelectOrderDescendingSelectedElement = '';

        $showcaseInputOrderText = '';

        switch ($this->showcaseObject->orderBy) {
            case ORDER_DIRECTION_ASCENDING:
                $showcaseSelectOrderAscendingSelectedElement = 'selected="selected"';

                $showcaseInputOrderText = $lang->myshowcase_desc;
                break;
            case ORDER_DIRECTION_DESCENDING:
                $showcaseSelectOrderDescendingSelectedElement = 'selected="selected"';

                $showcaseInputOrderText = $lang->myshowcase_asc;
                break;
        }

        //build sort_by option list
        $selectOptions = '';

        foreach ($this->showcaseObject->fieldSetFieldsDisplayFields as $fieldKey => $fieldDisplayName) {
            $selectedElement = '';

            if ($this->showcaseObject->sortByField === $fieldKey) {
                $selectedElement = 'selected="selected"';
            }

            $fieldDisplayName = $lang->myShowcaseMainSelectSortBy . ' ' . $fieldDisplayName;

            $selectOptions .= eval($this->renderObject->templateGet('pageMainSelectOption'));
        }

        $selectFieldName = 'sort_by';

        $selectFieldCode = eval($this->renderObject->templateGet('pageMainSelect'));

        //build searchfield option list
        $selectOptionsSearchField = '';

        foreach ($this->renderObject->fieldSetFieldsSearchFields as $fieldKey => $fieldDisplayName) {
            $optionSelectedElement = '';

            if ($this->showcaseObject->searchField === $fieldKey) {
                $optionSelectedElement = 'selected="selected"';
            }

            $selectOptionsSearchField .= eval($this->renderObject->templateGet('pageMainSelectOption'));
        }

        $inputElementExactMatch = '';

        if ($this->renderObject->searchExactMatch) {
            $inputElementExactMatch = 'checked="checked"';
        }

        $urlSortRow = urlHandlerBuild(
            array_merge($this->renderObject->urlParams, ['order_by' => $this->showcaseObject->orderBy])
        );

        $orderInputs = array_map(function (string $value): string {
            return '';
        }, $this->showcaseObject->fieldSetFieldsDisplayFields);

        if ($showcaseInputOrderText) {
            $orderInputs[$this->showcaseObject->sortByField] = eval(
            $this->renderObject->templateGet(
                'pageMainTableTheadFieldSort'
            )
            );
        }

        // Check if the active user is a moderator and get the inline moderation tools.
        $showcaseColumnsCount = 5;

        //build custom list header based on field settings
        $showcaseTableTheadExtra = '';

        foreach ($this->showcaseObject->fieldSetFieldsOrder as $renderOrder => $fieldID) {
            $fieldKey = $this->showcaseObject->fieldSetCache[$fieldID]['field_key'];

            $showcaseTableTheadExtraFieldTitle = $lang->{"myshowcase_field_{$fieldKey}"} ?? ucfirst($fieldKey);

            //$showcaseTableTheadExtraFieldOrder = $this->showcaseObject->fieldSetFieldsDisplayFields[$fieldKey];

            $showcaseTableTheadExtra .= eval($this->renderObject->templateGet('pageMainTableTheadRowField'));

            ++$showcaseColumnsCount;
        }

        //setup joins for query and build where clause based on search_field terms

        $queryTables = ['users userData ON (userData.uid = entryData.user_id)'];

        $queryFields = array_merge(array_map(function (string $columnName): string {
            return 'entryData.' . $columnName;
        }, array_keys(DATA_TABLE_STRUCTURE['myshowcase_data'])), [
            'userData.username',
            'userData.usergroup',
            'userData.displaygroup'
        ]);

        $searchDone = false;

        $allowedStatuses = [ENTRY_STATUS_VISIBLE];

        if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries]) {
            $allowedStatuses[] = ENTRY_STATUS_SOFT_DELETED;
        }

        $allowedStatuses = implode("','", $allowedStatuses);

        $whereClauseStatus = ["entryData.status IN ('{$allowedStatuses}')"];

        $statusPendingApproval = ENTRY_STATUS_PENDING_APPROVAL;

        $whereClauseStatus[] = "(entryData.user_id='{$currentUserID}' AND entryData.status='{$statusPendingApproval}')";

        $whereClauseStatus = implode(' OR ', $whereClauseStatus);

        $whereClauses[] = "({$whereClauseStatus})";

        $whereClauses = array_merge($whereClauses, $this->showcaseObject->whereClauses);

        foreach ($this->showcaseObject->fieldSetCache as $fieldID => $fieldData) {
            $fieldKey = $fieldData['field_key'];

            $htmlType = $fieldData['html_type'];

            if ($htmlType === FieldHtmlTypes::Select || $htmlType === FieldHtmlTypes::Radio) {
                $queryTables[] = "myshowcase_field_data table_{$fieldKey} ON (table_{$fieldKey}.field_data_id = entryData.{$fieldKey} AND table_{$fieldKey}.field_id = '{$fieldData['field_id']}')";

                $queryFields[] = "table_{$fieldKey}.display_style AS {$fieldKey}";

                if ($this->renderObject->searchKeyWords && $this->showcaseObject->searchField === $fieldKey) {
                    if ($this->renderObject->searchExactMatch) {
                        $whereClauses[] = "table_{$fieldKey}.value='{$db->escape_string($this->renderObject->searchKeyWords)}'";
                    } else {
                        $whereClauses[] = "table_{$fieldKey}.value LIKE '%{$db->escape_string($this->renderObject->searchKeyWords)}%'";
                    }

                    $whereClauses[] = "table_{$fieldKey}.set_id='{$this->showcaseObject->config['field_set_id']}'";
                }
            } elseif ($this->showcaseObject->searchField === 'username' && !$searchDone) {
                $queryTables[] = 'users us ON (entryData.user_id = us.uid)';

                $queryFields[] = $fieldKey;

                if ($this->renderObject->searchKeyWords) {
                    if ($this->renderObject->searchExactMatch) {
                        $whereClauses[] = "us.username='{$db->escape_string($this->renderObject->searchKeyWords)}'";
                    } else {
                        $whereClauses[] = "us.username LIKE '%{$db->escape_string($this->renderObject->searchKeyWords)}%'";
                    }
                }

                $searchDone = true;
            } else {
                $queryFields[] = $fieldKey;

                if ($this->renderObject->searchKeyWords && $this->showcaseObject->searchField === $fieldKey) {
                    if ($this->renderObject->searchExactMatch) {
                        $whereClauses[] = "entryData.{$fieldKey}='{$db->escape_string($this->renderObject->searchKeyWords)}'";
                    } else {
                        $whereClauses[] = "entryData.{$fieldKey} LIKE '%{$db->escape_string($this->renderObject->searchKeyWords)}%'";
                    }
                }
            }
        }

        $queryOptions = [
            'order_by' => 'entry_id',
            'order_dir' => $this->showcaseObject->orderBy,
        ];

        if ($this->showcaseObject->sortByField !== 'dateline') {
            $queryOptions['order_by'] = "{$db->escape_string($this->showcaseObject->sortByField)} {$this->showcaseObject->orderBy}, entry_id";

            $queryOptions['order_dir'] = $this->showcaseObject->orderBy;
        }

        $totalEntries = (int)(entryGet(
            $this->showcaseObject->showcase_id,
            $whereClauses,
            ['COUNT(entryData.entry_id) AS total_entries'],
            array_merge(['limit' => 1], $queryOptions),
            $queryTables
        )['total_entries'] ?? 0);

        $showcaseEntriesList = '';

        $entriesPagination = '';

        $alternativeBackground = alt_trow(true);

        $hookArguments = hooksRun('output_main_intermediate', $hookArguments);

        if ($totalEntries) {
            $entriesPerPage = $this->showcaseObject->config['entries_per_page'];

            if ($currentPage > 0) {
                $pageStart = ($currentPage - 1) * $entriesPerPage;

                $pageTotal = $totalEntries / $entriesPerPage;

                $pageTotal = ceil($pageTotal);

                if ($currentPage > $pageTotal) {
                    $pageStart = 0;

                    $currentPage = 1;
                }
            } else {
                $pageStart = 0;

                $currentPage = 1;
            }

            $upper = $pageStart + $entriesPerPage;

            if ($upper > $totalEntries) {
                $upper = $totalEntries;
            }

            $queryOptions['limit'] = $entriesPerPage;

            $queryOptions['limit_start'] = $pageStart;

            $urlParams = [];

            $entriesPagination = multipage(
                $totalEntries,
                $this->showcaseObject->config['entries_per_page'],
                $currentPage,
                url(
                    URL_TYPE_MAIN_PAGE,
                    [
                        'user_id' => $userID,
                        'page_id' => '{page}'
                    ],
                    $urlParams
                )->getRelativeUrl()
            );

            $entriesObjects = entryGet(
                $this->showcaseObject->showcase_id,
                $whereClauses,
                $queryFields,
                $queryOptions,
                $queryTables
            );

            // get first attachment for each showcase on this page
            $entryAttachmentsCache = [];

            if ($this->showcaseObject->config['attachments_main_render_first']) {
                $entryIDs = implode("','", array_column($entriesObjects, 'entry_id'));

                $attachmentObjects = attachmentGet(
                    ["showcase_id='{$this->showcaseObject->showcase_id}'", "entry_id IN ('{$entryIDs}')", "status='1'"],
                    [
                        'entry_id',
                        'MIN(attachment_id) as attachment_id',
                        'mime_type',
                        'file_name',
                        'attachment_name',
                        'thumbnail_name',
                        'attachment_hash',
                        'thumbnail_dimensions',
                    ],
                    // todo, seems like MIN(attachment_id) as attachment_id is unnecessary
                    ['group_by' => 'entry_id']
                );

                foreach ($attachmentObjects as $attachmentID => $attachmentData) {
                    $entryAttachmentsCache[$attachmentData['entry_id']] = [
                        'attachment_id' => $attachmentID,
                        'mime_type' => $attachmentData['mime_type'],
                        'file_name' => $attachmentData['file_name'],
                        'attachment_name' => $attachmentData['attachment_name'],
                        'thumbnail_name' => $attachmentData['thumbnail_name'],
                        'attachment_hash' => $attachmentData['attachment_hash'],
                        'thumbnail_dimensions' => $attachmentData['thumbnail_dimensions'],
                    ];
                }
            }

            $inlineModerationCount = 0;

            if ($this->showcaseObject->config['entries_per_page'] === 1) {
                $entriesObjects = [$entriesObjects];
            }

            foreach ($entriesObjects as $entryFieldData) {
                $entrySlug = $entryFieldData['entry_slug'];

                $this->showcaseObject->entryDataSet($entryFieldData);

                $entryStatus = (int)$entryFieldData['status'];

                $styleClass = '';

                switch ($entryStatus) {
                    case ENTRY_STATUS_PENDING_APPROVAL:
                        $styleClass = 'trow_shaded';
                        break;
                    case ENTRY_STATUS_SOFT_DELETED:
                        $styleClass = 'trow_shaded trow_deleted';
                        break;
                }

                //change style is unapproved
                if (empty($entryFieldData['approved'])) {
                    //$alternativeBackground .= ' trow_shaded';
                }

                $entryID = (int)$entryFieldData['entry_id'];

                $entryFieldData['username'] ??= $lang->guest;

                $entryUsername = $entryUsernameFormatted = htmlspecialchars_uni($entryFieldData['username']);

                $entryViews = my_number_format($entryFieldData['views']);

                $entryUnapproved = ''; // todo, show ({Unapproved}) in the list view

                $viewPagination = ''; // todo, show pagination in the list view

                $viewAttachmentsCount = ''; // todo, show attachment count in the list view

                $entryComments = my_number_format($entryFieldData['comments']);

                $entryDateline = my_date('relative', $entryFieldData['dateline']);

                if (!empty($entryFieldData['user_id'])) {
                    $entryUsernameFormatted = build_profile_link(
                        format_name(
                            $entryFieldData['username'],
                            $entryFieldData['usergroup'],
                            $entryFieldData['displaygroup']
                        ),
                        $entryFieldData['user_id']
                    );
                }

                $viewLastCommenter = $entryUsernameFormatted; // todo, show last commenter in the list view

                $entryUrl = url(
                    URL_TYPE_ENTRY_VIEW,
                    [
                        'entry_slug' => $this->showcaseObject->entryData['entry_slug'],
                        'entry_slug_custom' => $this->showcaseObject->entryData['entry_slug_custom']
                    ],
                    $this->showcaseObject->urlParams
                )->getRelativeUrl();

                $viewLastCommentID = 0; // todo, show last comment ID in the list view

                $entryUrlLastComment = str_replace(
                    '{entry_id}',
                    (string)$entryID,
                    $this->showcaseObject->urlViewComment
                );

                //add bits for search_field highlighting
                /*if ($this->renderObject->searchKeyWords) {
                    $urlBackup = urlHandlerGet();

                    urlHandlerSet($entryUrl);

                    $entryUrl = urlHandlerBuild([
                        //'search_field' => $this->showcaseObject->searchField,
                        'highlight' => urlencode($this->renderObject->searchKeyWords)
                    ]);

                    urlHandlerSet($urlBackup);
                }*/

                //build link for list view, starting with basic text

                $entryImageText = str_replace('{username}', $entryUsername, $lang->myshowcase_view_user);

                $entryImage = '';

                //use showcase attachment if one exists, scaled of course
                if ($this->showcaseObject->config['attachments_main_render_first'] &&
                    !empty($entryAttachmentsCache[$entryFieldData['entry_id']])) {
                    $attachmentUrl = url(
                        URL_TYPE_ATTACHMENT_VIEW,
                        [
                            'entry_slug' => $entrySlug,
                            'entry_slug_custom' => $this->showcaseObject->entryData['entry_slug_custom'],
                            'attachment_hash' => $entryAttachmentsCache[$entryFieldData['entry_id']]['attachment_hash']
                        ]
                    )->getRelativeUrl();

                    $thumbnailUrl = url(
                        URL_TYPE_THUMBNAIL_VIEW,
                        [
                            'entry_slug' => $entrySlug,
                            'entry_slug_custom' => $this->showcaseObject->entryData['entry_slug_custom'],
                            'attachment_hash' => $entryAttachmentsCache[$entryFieldData['entry_id']]['attachment_hash']
                        ]
                    )->getRelativeUrl();

                    if (stristr(
                            $entryAttachmentsCache[$entryFieldData['entry_id']]['mime_type'] ?? '',
                            'image/'
                        ) && $entryAttachmentsCache[$entryFieldData['entry_id']]['attachment_id']) {
                        if ((int)$entryAttachmentsCache[$entryFieldData['entry_id']]['thumbnail_dimensions'] === ATTACHMENT_THUMBNAIL_SMALL) {
                            $urlImage = $attachmentUrl;
                        } else {
                            $urlImage = $thumbnailUrl;
                        }

                        $entryImage = eval($this->renderObject->templateGet('pageMainTableRowsImage'));
                    }
                }

                //build custom list items based on field settings
                $showcaseTableRowExtra = [];

                $entrySubject = [];

                foreach ($this->showcaseObject->fieldSetCache as $fieldID => $fieldData) {
                    $fieldKey = $fieldData['field_key'];

                    $htmlType = $fieldData['html_type'];

                    $entryFieldText = $entryFieldData[$fieldKey] ?? '';

                    if (!$fieldData['parse']) {
                        // todo, remove this legacy updating the database and updating the format field to TINYINT
                        formatField((int)$fieldData['format'], $entryFieldText);

                        if ($htmlType === FieldHtmlTypes::Date) {
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
                        }
                    } else {
                        $entryFieldText = $this->showcaseObject->parseMessage($entryFieldText);
                    }

                    $showcaseTableRowExtra[$fieldKey] = eval(
                    $this->renderObject->templateGet(
                        'pageMainTableRowsExtra'
                    )
                    );

                    if ($fieldData['enable_subject'] && !empty($entryFieldText)) {
                        $entrySubject[] = $entryFieldText;
                    }
                }

                $entrySubject = trim(implode(' ', $entrySubject)) ?? $lang->myShowcaseMainTableTheadView;

                if ($this->showcaseObject->config['attachments_allow_entries'] &&
                    $this->showcaseObject->userPermissions[UserPermissions::CanViewAttachments]) {
                    $this->renderObject->entryBuildAttachments(
                        $showcaseTableRowExtra,
                        $this->renderObject::POST_TYPE_ENTRY
                    );
                }

                $showcaseTableRowExtra = implode('', $showcaseTableRowExtra);

                $showcaseTableRowInlineModeration = '';

                if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries]) {
                    $inlineModerationCheckElement = '';

                    if (isset($mybb->cookies['inlinemod_showcase' . $this->showcaseObject->showcase_id]) &&
                        my_strpos(
                            "|{$mybb->cookies['inlinemod_showcase' . $this->showcaseObject->showcase_id]}|",
                            "|{$entryID}|"
                        ) !== false) {
                        $inlineModerationCheckElement = 'checked="checked"';

                        ++$inlineModerationCount;
                    }

                    $showcaseTableRowInlineModeration = eval(
                    $this->renderObject->templateGet(
                        'pageMainTableRowInlineModeration'
                    )
                    );
                }

                $showcaseEntriesList .= eval($this->renderObject->templateGet('pageMainTableRows'));

                $alternativeBackground = alt_trow();
            }
        } else {
            //$colcount = 5;

            if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries] &&
                $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries]) {
                // ++$colcount;
            }

            //$showcaseColumnsCount = $colcount + count($this->showcaseObject->fieldSetFieldsOrder);

            if (!$this->renderObject->searchKeyWords) {
                $message = $lang->myShowcaseMainTableEmpty;
            } else {
                $message = $lang->myShowcaseMainTableEmptySearch;
            }

            $showcaseEntriesList .= eval($this->renderObject->templateGet('pageMainTableEmpty'));
        }

        $pageTitle = $this->showcaseObject->config['name'];

        $urlSortByUsername = urlHandlerBuild(array_merge($this->renderObject->urlParams, ['sort_by' => 'username']));

        $urlSortByComments = urlHandlerBuild(array_merge($this->renderObject->urlParams, ['sort_by' => 'comments']));

        $urlSortByViews = urlHandlerBuild(array_merge($this->renderObject->urlParams, ['sort_by' => 'views']));

        $urlSortByDateline = urlHandlerBuild(array_merge($this->renderObject->urlParams, ['sort_by' => 'dateline']));

        if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries] || $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries]) {
            ++$showcaseColumnsCount;
        }

        $tableColumnInlineModeration = $inlineModeration = '';

        if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries]) {
            $tableColumnInlineModeration = eval(
            $this->renderObject->templateGet(
                'pageMainTableTheadRowInlineModeration'
            )
            );

            ++$showcaseColumnsCount;

            $inlineModeration = eval($this->renderObject->templateGet('pageMainInlineModeration'));
        }

        $this->outputSuccess(eval($this->renderObject->templateGet('pageMainContents')));
    }

    #[NoReturn] public function mainUser(
        int $userID,
        int $limit = 10,
        int $limitStart = 0,
        string $groupBy = '',
        string $orderBy = 'user_id',
        string $orderDirection = ORDER_DIRECTION_ASCENDING
    ): void {
        $this->mainView(
            $userID,
            $limit,
            $limitStart,
            $groupBy,
            $orderBy,
            $orderDirection,
            filterField: 'user_id',
            filterValue: $userID
        );
    }

    #[NoReturn] public function mainUnapproved(
        int $userID = 0,
        int $limit = 10,
        int $limitStart = 0,
        string $groupBy = '',
        string $orderBy = 'user_id',
        string $orderDirection = ORDER_DIRECTION_ASCENDING
    ): void {
        $statusUnapproved = ENTRY_STATUS_PENDING_APPROVAL;

        $this->mainView(
            $userID,
            $limit,
            $limitStart,
            $groupBy,
            $orderBy,
            $orderDirection,
            ["status='{$statusUnapproved}'"]
        );
    }

    #[NoReturn] public function createEntry(
        bool $isEditPage = false,
        string $entrySlug = '',
    ): void {
        global $lang, $mybb, $db;
        global $header, $headerinclude, $footer, $theme;

        $hookArguments = [
            'this' => &$this,
            'isEditPage' => $isEditPage,
        ];

        $extractVariables = [];

        $hookArguments['extractVariables'] = &$extractVariables;

        $currentUserID = (int)$mybb->user['uid'];

        $entryUserData = get_user($this->showcaseObject->entryUserID);

        $showcaseUserPermissions = $this->showcaseObject->userPermissionsGet($this->showcaseObject->entryUserID);

        $showcase_watermark = '';

        if ($isEditPage) {
            $this->showcaseObject->setEntry($entrySlug, true);
        }

        switch (1 || $this->showcaseObject->config['filter_force_field']) {
            /*case FILTER_TYPE_USER_ID:
                $userData = get_user($this->showcaseObject->entryUserID);

                if (empty($userData['uid']) || empty($mybb->usergroup['canviewprofiles'])) {
                    error_no_permission();
                }

                $lang->load('member');

                $userName = htmlspecialchars_uni($userData['username']);

                add_breadcrumb(
                    $lang->sprintf($lang->nav_profile, $userName),
                    $mybb->settings['bburl'] . '/' . get_profile_link($userData['uid'])
                );

                $mainUrl = str_replace(
                    '/user/',
                    '/user/' . $this->showcaseObject->entryUserID,
                    url(URL_TYPE_MAIN_USER)->getRelativeUrl()
                );

                break;*/
            default:
                $mainUrl = url(URL_TYPE_MAIN, getParams: $this->showcaseObject->urlParams)->getRelativeUrl();

                break;
        }

        add_breadcrumb(
            $this->showcaseObject->config['name_friendly'],
            $mainUrl
        );

        if ($isEditPage) {
            add_breadcrumb(
                $lang->myShowcaseButtonEntryUpdate,
                $this->showcaseObject->urlBuild($this->showcaseObject->urlUpdateEntry)
            );
        } else {
            add_breadcrumb(
                $lang->myShowcaseButtonEntryCreate,
                $this->showcaseObject->urlBuild($this->showcaseObject->urlCreateEntry)
            );
        }

        $entryPreview = '';

        if ($mybb->request_method === 'post') {
            verify_post_check($mybb->get_input('my_post_key'));

            //$this->showcaseObject->entryHash = $mybb->get_input('entry_hash');

            if ($this->showcaseObject->config['attachments_allow_entries']) {
                $this->uploadAttachment();
            }

            $isPreview = isset($mybb->input['preview']);

            $insertData = [];

            $insertData['ipaddress'] = $mybb->session->packedip;

            if (!$isEditPage) {
                $insertData['user_id'] = $currentUserID;

                $insertData['ipaddress'] = $mybb->session->packedip;

                $insertData['dateline'] = TIME_NOW;

                if ($this->showcaseObject->entryHash) {
                    $insertData['entry_hash'] = $this->showcaseObject->entryHash;
                }
            }

            if ($isEditPage && (
                    $this->showcaseObject->config['moderate_entries_update'] ||
                    $this->showcaseObject->userPermissions[UserPermissions::ModerateEntryUpdate]
                )) {
                $insertData['status'] = ENTRY_STATUS_PENDING_APPROVAL;
            } elseif (!$isEditPage && $this->showcaseObject->config['moderate_entries_create'] && (
                    $this->showcaseObject->config['moderate_entries_create'] ||
                    $this->showcaseObject->userPermissions[UserPermissions::ModerateEntryCreate]
                )) {
                $insertData['status'] = ENTRY_STATUS_PENDING_APPROVAL;
            }

            // Set up showcase handler.
            //require_once MYBB_ROOT . 'inc/datahandlers/myshowcase_dh.php';

            if ($isEditPage) {
                $dataHandler = dataHandlerGetObject($this->showcaseObject, DATA_HANDLER_METHOD_UPDATE);
            } else {
                $insertData['dateline'] = TIME_NOW;

                $dataHandler = dataHandlerGetObject($this->showcaseObject);
            }

            $entrySlug = [];

            foreach ($this->showcaseObject->fieldSetCache as $fieldID => $fieldData) {
                $fieldKey = $fieldData['field_key'];

                $insertData[$fieldKey] = match ($fieldData['html_type']) {
                    FieldHtmlTypes::CheckBox, FieldHtmlTypes::Select => $mybb->get_input(
                        $fieldKey,
                        MyBB::INPUT_ARRAY
                    ),
                    default => $mybb->get_input($fieldKey),
                };

                if ($fieldData['enable_slug']) {
                    $entrySlug[] = $insertData[$fieldKey];
                }
            }

            $entrySlug = cleanSlug(implode('-', $entrySlug));

            $i = 1;

            while ($foundEntry = $this->showcaseObject->dataGet(
                ["entry_slug='{$db->escape_string($entrySlug)}'", "entry_id!='{$this->showcaseObject->entryID}'"],
                queryOptions: ['limit' => 1]
            )) {
                $entrySlug .= '-' . $i;

                ++$i;
            }

            if (!$isEditPage) {
                $insertData['entry_slug'] = $entrySlug;
            }
            // todo, slug should load all field data after update/insert to build

            $dataHandler->dataSet($insertData);

            if (!$dataHandler->entryValidate()) {
                $this->showcaseObject->errorMessages = array_merge(
                    $this->showcaseObject->errorMessages,
                    $dataHandler->get_friendly_errors()
                );
            }

            if (!$isPreview && !$this->showcaseObject->errorMessages) {
                if ($isEditPage) {
                    $dataHandler->updateEntry();
                } else {
                    $dataHandler->entryInsert();
                }

                if (isset($dataHandler->returnData['status']) && $dataHandler->returnData['status'] === ENTRY_STATUS_SOFT_DELETED) {
                    $mainUrl = url(URL_TYPE_MAIN, getParams: $this->showcaseObject->urlParams)->getRelativeUrl();

                    switch (1 || $this->showcaseObject->config['filter_force_field']) {
                        /*case FILTER_TYPE_USER_ID:
                            $mainUrl = str_replace(
                                '/user/',
                                '/user/' . $this->showcaseObject->entryUserID,
                                url(URL_TYPE_MAIN_USER)->getRelativeUrl()
                            );

                            break;*/
                        default:
                            $mainUrl = url(
                                URL_TYPE_MAIN,
                                getParams: $this->showcaseObject->urlParams
                            )->getRelativeUrl();
                            break;
                    }

                    redirect(
                        $mainUrl,
                        $isEditPage ? $lang->myShowcaseEntryEntryUpdatedStatus : $lang->myShowcaseEntryEntryCreatedStatus
                    );
                } else {
                    $entryUrl = url(
                        URL_TYPE_ENTRY_VIEW,
                        ['entry_slug' => $dataHandler->returnData['entry_slug']]
                    )->getRelativeUrl();

                    redirect(
                        $entryUrl,
                        $isEditPage ? $lang->myShowcaseEntryEntryUpdated : $lang->myShowcaseEntryEntryCreated
                    );
                }

                exit;
            }

            if ($isPreview) {
                $this->showcaseObject->entryData = array_merge($this->showcaseObject->entryData, $mybb->input);

                $entryPreview = $this->renderObject->buildEntry(true);
            }
        } elseif ($isEditPage) {
            $mybb->input = array_merge($this->showcaseObject->entryData, $mybb->input);
        }

        if ($isEditPage && !$this->showcaseObject->userPermissions[UserPermissions::CanUpdateEntries]) {
            error_no_permission();
        } elseif (!$isEditPage && !$this->showcaseObject->userPermissions[UserPermissions::CanCreateEntries]) {
            error_no_permission();
        }

        $hookArguments = hooksRun('output_new_start', $hookArguments);

        $alternativeBackground = alt_trow(true);

        $showcaseFields = '';

        $fieldTabIndex = 1;

        foreach ($this->showcaseObject->fieldSetCache as $fieldID => $fieldData) {
            $fieldObject = fieldGetObject($this->showcaseObject, $fieldData);

            // todo, field data values shouldn't be NULL ?
            if ($fieldData['html_type'] === FieldHtmlTypes::Radio) {
                //$this->showcaseObject->entryData[$fieldData['field_key']] ??= '';
            }

            $showcaseFields .= $fieldObject->setUserValue(
                $this->showcaseObject->entryData[$fieldData['field_key']] ?? ''
            )->renderCreateUpdate($alternativeBackground, $fieldTabIndex);
            /*
            $fieldKey = $fieldData['field_key'];

            $htmlType = $fieldData['html_type'];

            $fieldID = $this->showcaseObject->fieldSetFieldsIDs[$fieldKey] ?? 0;

            $fieldKeyEscaped = $db->escape_string($fieldKey);

            $fieldTitle = $lang->{'myshowcase_field_' . $fieldKey} ?? $fieldKey;

            $fieldElementRequired = '';

            if ($fieldData['is_required']) {
                $fieldElementRequired = 'required="required"';
            }

            $fieldInput = '';

            switch ($htmlType) {
                case FieldHtmlTypes::Text:
                    $fieldValue = htmlspecialchars_uni($mybb->get_input($fieldKey));

                    $fieldInput = eval($this->renderObject->templateGet('pageEntryCreateUpdateDataTextBox'));
                    break;
                case FieldHtmlTypes::Url:
                    $fieldValue = htmlspecialchars_uni($mybb->get_input($fieldKey));

                    $fieldInput = eval($this->renderObject->templateGet('pageEntryCreateUpdateDataTextUrl'));
                    break;
                case FieldHtmlTypes::TextArea:
                    $editorCodeButtons = $editorSmilesInserter = '';

                    $this->renderObject->buildEditor($editorCodeButtons, $editorSmilesInserter, $fieldKey);

                    $fieldValue = htmlspecialchars_uni($mybb->get_input($fieldKey));

                    $fieldInput = eval($this->renderObject->templateGet('pageEntryCreateUpdateDataTextArea'));
                    break;
                case FieldHtmlTypes::Radio:
                    $fieldDataObjects = fieldDataGet(
                        ["set_id='{$this->showcaseObject->config['field_set_id']}'", "field_id='{$fieldID}'"],
                        ['value_id', 'value'],
                        ['order_by' => 'display_order']
                    );

                    if ($fieldDataObjects) {
                        $fieldOptions = [];

                        foreach ($fieldDataObjects as $fieldDataID => $fieldData) {
                            $valueID = (int)$fieldData['value_id'];

                            $valueName = htmlspecialchars_uni($fieldData['value']);

                            $checkedElement = '';

                            if ($mybb->get_input($fieldKey, MyBB::INPUT_INT) === $valueID) {
                                $checkedElement = 'checked="checked"';
                            }

                            $fieldOptions[] = eval(
                            $this->renderObject->templateGet(
                                'pageEntryCreateUpdateDataFieldRadio'
                            )
                            );
                        }

                        $fieldInput = implode('', $fieldOptions);
                    }

                    break;
                case FieldHtmlTypes::CheckBox:
                    $valueID = 1;

                    $valueName = htmlspecialchars_uni($fieldKey);

                    $checkedElement = '';

                    if ($mybb->get_input($fieldKey, MyBB::INPUT_INT) === 1) {
                        $checkedElement = 'checked="checked"';
                    }

                    $fieldInput = eval($this->renderObject->templateGet('pageEntryCreateUpdateDataFieldCheckBox'));
                    break;
                case FieldHtmlTypes::Select:
                    $fieldDataObjects = fieldDataGet(
                        [
                            "set_id='{$this->showcaseObject->config['field_set_id']}'",
                            "field_id='{$fieldID}'"
                        ],
                        ['field_data_id', 'value_id', 'value'],
                        ['order_by' => 'display_order']
                    );

                    if ($fieldDataObjects) {
                        $fieldOptions = [];

                        foreach ($fieldDataObjects as $fieldDataID => $fieldData) {
                            $valueID = (int)$fieldData['field_data_id'];

                            $valueName = htmlspecialchars_uni($fieldData['value']);

                            $selectedElement = '';

                            if ($mybb->get_input($fieldKey, MyBB::INPUT_INT) === $valueID) {
                                $selectedElement = 'selected="selected"';
                            }

                            $fieldOptions[] = eval(
                            $this->renderObject->templateGet(
                                'pageEntryCreateUpdateDataFieldDataBaseOption'
                            )
                            );
                        }

                        $fieldOptions = implode('', $fieldOptions);

                        $fieldInput = eval($this->renderObject->templateGet('pageEntryCreateUpdateDataFieldDataBase'));
                    }
                    break;

                case FieldHtmlTypes::Date:
                    list($mybb->input[$fieldKey . '_month'], $mybb->input[$fieldKey . '_day'], $mybb->input[$fieldKey . '_year']) = array_pad(
                        array_map('intval', explode('|', $mybb->get_input($fieldKey))),
                        3,
                        0
                    );

                    $daySelect = (function (string $fieldKey) use (
                        $mybb,
                        $lang,
                        $fieldTabIndex,
                        $fieldElementRequired,
                    ): string {
                        $valueID = 0;

                        $selectedElement = '';

                        $valueName = $lang->myshowcase_day;

                        $fieldOptions = [
                            eval(
                            $this->renderObject->templateGet(
                                'pageEntryCreateUpdateDataFieldDataBaseOption'
                            )
                            )
                        ];

                        for ($valueID = 1; $valueID <= 31; ++$valueID) {
                            $valueName = $valueID;

                            $selectedElement = '';

                            if ($mybb->get_input($fieldKey . '_day', MyBB::INPUT_INT) === $valueID) {
                                $selectedElement = 'selected="selected"';
                            }

                            $fieldOptions[] = eval(
                            $this->renderObject->templateGet(
                                'pageEntryCreateUpdateDataFieldDataBaseOption'
                            )
                            );
                        }

                        $fieldOptions = implode('', $fieldOptions);

                        $fieldKey = $fieldKey . '_day';

                        return eval($this->renderObject->templateGet('pageEntryCreateUpdateDataFieldDataBase'));
                    })(
                        $fieldKey
                    );

                    $monthSelect = (function (string $fieldKey) use (
                        $mybb,
                        $lang,
                        $fieldTabIndex,
                        $fieldElementRequired,
                    ): string {
                        $valueID = 0;

                        $selectedElement = '';

                        $valueName = $lang->myshowcase_month;

                        $fieldOptions = [
                            eval(
                            $this->renderObject->templateGet(
                                'pageEntryCreateUpdateDataFieldDataBaseOption'
                            )
                            )
                        ];

                        for ($valueID = 1; $valueID <= 12; ++$valueID) {
                            $valueName = $valueID;

                            $selectedElement = '';

                            if ($mybb->get_input($fieldKey . '_month', MyBB::INPUT_INT) === $valueID) {
                                $selectedElement = 'selected="selected"';
                            }

                            $fieldOptions[] = eval(
                            $this->renderObject->templateGet(
                                'pageEntryCreateUpdateDataFieldDataBaseOption'
                            )
                            );
                        }

                        $fieldOptions = implode('', $fieldOptions);

                        $fieldKey = $fieldKey . '_month';

                        return eval($this->renderObject->templateGet('pageEntryCreateUpdateDataFieldDataBase'));
                    })(
                        $fieldKey
                    );

                    $yearSelect = (function (string $fieldKey) use (
                        $mybb,
                        $lang,
                        $fieldTabIndex,
                        $fieldElementRequired,
                        $fieldData
                    ): string {
                        $valueID = 0;

                        $selectedElement = '';

                        $valueName = $lang->myshowcase_year;

                        $fieldOptions = [
                            eval(
                            $this->renderObject->templateGet(
                                'pageEntryCreateUpdateDataFieldDataBaseOption'
                            )
                            )
                        ];

                        for ($valueID = $fieldData['minimum_length']; $valueID <= $fieldData['maximum_length']; ++$valueID) {
                            $valueName = $valueID;

                            $selectedElement = '';

                            if ($mybb->get_input($fieldKey . '_year', MyBB::INPUT_INT) === $valueID) {
                                $selectedElement = 'selected="selected"';
                            }

                            $fieldOptions[] = eval(
                            $this->renderObject->templateGet(
                                'pageEntryCreateUpdateDataFieldDataBaseOption'
                            )
                            );
                        }

                        $fieldOptions = implode('', $fieldOptions);

                        $fieldKey = $fieldKey . '_year';

                        return eval($this->renderObject->templateGet('pageEntryCreateUpdateDataFieldDataBase'));
                    })(
                        $fieldKey
                    );

                    $fieldInput = eval($this->renderObject->templateGet('pageEntryCreateUpdateDataFieldDate'));
                    break;
            }

            $showcaseFields .= eval($this->renderObject->templateGet('pageEntryCreateUpdateDataField'));*/

            ++$fieldTabIndex;

            $alternativeBackground = alt_trow();
        }

        $hookArguments = hooksRun('output_new_end', $hookArguments);

        if ($isEditPage) {
            $createUpdateUrl = url(
                URL_TYPE_ENTRY_UPDATE,
                ['entry_slug' => $this->showcaseObject->entryData['entry_slug']],
                $this->showcaseObject->urlParams
            )->getRelativeUrl();
        } else {
            $createUpdateUrl = url(
                URL_TYPE_ENTRY_CREATE,
                getParams: $this->showcaseObject->urlParams
            )->getRelativeUrl();
        }

        $attachmentsUpload = $this->renderObject->buildAttachmentsUpload($isEditPage);

        $hookArguments = hooksRun('entry_create_update_end', $hookArguments);

        extract($hookArguments['extractVariables']);

        if ($isEditPage) {
            $buttonText = $lang->myShowcaseNewEditFormButtonUpdateEntry;
        } else {
            $buttonText = $lang->myShowcaseNewEditFormButtonCreateEntry;
        }

        if ($entryPreview) {
            $entryPreview = eval($this->renderObject->templateGet('pageEntryCreateUpdateContentsPreview'));
        }

        $postHash = htmlspecialchars_uni($mybb->get_input('post_hash'));

        $this->outputSuccess(eval($this->renderObject->templateGet('pageEntryCreateUpdateContents')));
    }

    #[NoReturn] public function updateEntry(
        string $entrySlug,
    ): void {
        $this->createEntry(true, $entrySlug);
    }

    #[NoReturn] public function viewEntry(
        string $entrySlug,
        string $entrySlugCustom,
        int $currentPage = 1,
    ): void {
        $this->showcaseObject->setEntry($entrySlug, true);

        global $mybb, $lang, $db, $theme;

        $hookArguments = [
            'this' => &$this
        ];

        $extractVariables = [];

        $hookArguments['extractVariables'] = &$extractVariables;

        $currentUserID = (int)$mybb->user['uid'];

        if (empty($this->showcaseObject->entryID)) {
            $this->showcaseObject->setEntry($entrySlug, true);
        }

        if (!$this->showcaseObject->entryID || empty($this->showcaseObject->entryData)) {
            error($lang->myshowcase_invalid_id);
        }

        if ($currentUserID !== $this->showcaseObject->entryUserID &&
            !$this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries] &&
            (int)$this->showcaseObject->entryData['status'] !== ENTRY_STATUS_VISIBLE
        ) {
            error($lang->myshowcase_invalid_id);
        }
        switch (1 || $this->showcaseObject->config['filter_force_field']) {
            /*
                        case FILTER_TYPE_USER_ID:
                            $userData = get_user($this->showcaseObject->entryUserID);

                            if (empty($userData['uid']) || empty($mybb->usergroup['canviewprofiles'])) {
                                error_no_permission();
                            }

                            $lang->load('member');

                            $userName = htmlspecialchars_uni($userData['username']);

                            add_breadcrumb(
                                $lang->sprintf($lang->nav_profile, $userName),
                                $mybb->settings['bburl'] . '/' . get_profile_link($userData['uid'])
                            );

                            $mainUrl = str_replace(
                                '/user/',
                                '/user/' . $this->showcaseObject->entryUserID,
                                url(URL_TYPE_MAIN_USER)->getRelativeUrl()
                            );

                            break;
            */
            default:
                $mainUrl = url(URL_TYPE_MAIN, getParams: $this->showcaseObject->urlParams)->getRelativeUrl();
                break;
        }
        add_breadcrumb(
            $this->showcaseObject->config['name_friendly'],
            $mainUrl
        );

        $entrySubject = $this->renderObject->buildEntrySubject();

        $entryUrl = url(
            URL_TYPE_ENTRY_VIEW,
            ['entry_slug' => $this->showcaseObject->entryData['entry_slug']]
        )->getRelativeUrl();

        add_breadcrumb(
            $entrySubject,
            $entryUrl
        );

        if ($this->showcaseObject->entryData['username'] === '') {
            $this->showcaseObject->entryData['username'] = $lang->guest;
            $this->showcaseObject->entryData['user_id'] = 0;
        }

        $entryUrl = str_replace(
            '{entry_id}',
            (string)$mybb->get_input('entry_id'),
            $this->showcaseObject->urlViewEntry
        );

        //$this->showcaseObject->entryHash = $this->showcaseObject->entryData['entry_hash'];

        //trick MyBB into thinking its in archive so it adds bburl to smile link inside parser
        //doing this now should not impact anyhting. no issues with gomobile beta4
        define('IN_ARCHIVE', 1);

        $entryPost = $this->renderObject->buildEntry();

        $commentsList = $commentsEmpty = $commentsForm = '';

        if ($this->showcaseObject->config['comments_allow'] && $this->showcaseObject->userPermissions[UserPermissions::CanViewComments]) {
            $whereClauses = [
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

            $queryOptions = ['order_by' => 'dateline', 'order_dir' => 'asc'];

            $queryOptions['limit'] = $this->showcaseObject->config['comments_per_page'];

            $hookArguments = hooksRun('entry_view_comment_form_start', $hookArguments);

            $totalComments = (int)(commentsGet(
                $whereClauses,
                ['COUNT(comment_id) AS total_comments'],
                ['limit' => 1]
            )['total_comments'] ?? 0);

            //$currentPage = $mybb->get_input('page', MyBB::INPUT_INT);

            $totalPages = $totalComments / $this->showcaseObject->config['comments_per_page'];

            $totalPages = ceil($totalPages);

            if ($currentPage > $totalPages || $currentPage <= 0) {
                $currentPage = 1;
            }

            if ($currentPage) {
                $queryOptions['limit_start'] = ($currentPage - 1) * $this->showcaseObject->config['comments_per_page'];
            } else {
                $queryOptions['limit_start'] = 0;

                $currentPage = 1;
            }

            $commentsCounter = 0;

            if ($currentPage > 1) {
                $url_params['page'] = $currentPage;

                $commentsCounter += ($currentPage - 1) * $this->showcaseObject->config['comments_per_page'];
            }

            $urlParams = [
                //'page' => '{page}'
            ];

            //SimpleRouter::get('/product-view/{id}', 'ProductsController@show', ['as' => 'product']);

            $entryUrl = url(
                URL_TYPE_ENTRY_VIEW,
                ['entry_slug' => $this->showcaseObject->entryData['entry_slug']], ['category' => 'shoes']
            )->getRelativeUrl();
            /*

                \MyShowcase\SimpleRouter123\url('product', ['id' => 22], ['category' => 'shoes'])->getParam('category'),

                \MyShowcase\SimpleRouter123\url('product', ['id' => 22], ['category' => 'shoes'])->getParams(),
            */

            $commentsPagination = multipage(
                $totalComments,
                $this->showcaseObject->config['comments_per_page'],
                $currentPage,
                url(
                    URL_TYPE_ENTRY_VIEW_PAGE,
                    [
                        'entry_slug' => $this->showcaseObject->entryData['entry_slug'],
                        'entry_slug_custom' => $this->showcaseObject->entryData['entry_slug_custom'],
                        'page_id' => '{page}'
                    ],
                    $urlParams
                )->getRelativeUrl()
            );

            extract($hookArguments['extractVariables']);

            $extractVariables = [];

            $hookArguments = hooksRun('entry_view_comment_form_intermediate', $hookArguments);

            $commentObjects = commentsGet(
                $whereClauses,
                ['user_id', 'comment', 'dateline', 'ipaddress', 'status', 'moderator_user_id', 'comment_slug'],
                $queryOptions
            );

            $alternativeBackground = alt_trow(true);

            foreach ($commentObjects as $commentID => $commentData) {
                ++$commentsCounter;

                $commentsList .= $this->renderObject->buildComment(
                    $commentData,
                    $alternativeBackground,
                    $commentsCounter,
                );

                $alternativeBackground = alt_trow();
            }

            if (!$commentsList) {
                $commentsEmpty = eval($this->renderObject->templateGet('pageViewCommentsNone'));
            }

            $hookArguments = hooksRun('entry_view_comment_form_end', $hookArguments);

            if ($this->showcaseObject->userPermissions[UserPermissions::CanCreateComments]) {
                global $collapsedthead, $collapsedimg, $expaltext, $collapsed;

                isset($collapsedthead) || $collapsedthead = [];

                isset($collapsedimg) || $collapsedimg = [];

                isset($collapsed) || $collapsed = [];

                $collapsedthead['quickreply'] ??= '';

                $collapsedimg['quickreply'] ??= '';

                $collapsed['quickreply_e'] ??= '';

                $commentLengthLimitNote = $lang->sprintf(
                    $lang->myshowcase_comment_text_limit,
                    my_number_format($this->showcaseObject->config['comments_minimum_length']),
                    my_number_format($this->showcaseObject->config['comments_maximum_length'])
                );

                $alternativeBackground = alt_trow(true);

                $createUpdateUrl = url(
                    URL_TYPE_COMMENT_CREATE,
                    ['entry_slug' => $this->showcaseObject->entryData['entry_slug']]
                )->getRelativeUrl();

                $commentMessage = htmlspecialchars_uni($mybb->get_input('comment'));

                $editorCodeButtons = $editorSmilesInserter = '';

                $this->renderObject->buildCommentsFormEditor($editorCodeButtons, $editorSmilesInserter);

                $commentsForm = eval($this->renderObject->templateGet('pageViewCommentsFormUser'));
            } elseif (!$currentUserID) {
                $commentsForm = eval($this->renderObject->templateGet('pageViewCommentsFormGuest'));
            }
        }

        // Update view count
        $db->shutdown_query(
            "UPDATE {$db->table_prefix}{$this->showcaseObject->dataTableName} SET views=views+1 WHERE entry_id='{$this->showcaseObject->entryID}'"
        );

        $hookArguments = hooksRun('entry_view_end', $hookArguments);

        extract($hookArguments['extractVariables']);

        $this->outputSuccess(eval($this->renderObject->templateGet('pageView')));
    }

    #[NoReturn] public function approveEntry(
        string $entrySlug,
        int $status = ENTRY_STATUS_VISIBLE
    ): void {
        global $lang;

        $this->showcaseObject->setEntry($entrySlug);

        if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries] ||
            !$this->showcaseObject->entryID) {
            error_no_permission();
        }

        $this->showcaseObject->dataUpdate(['status' => $status]);

        $entryUrl = url(
                URL_TYPE_ENTRY_VIEW,
                ['entry_slug' => $this->showcaseObject->entryData['entry_slug']]
            )->getRelativeUrl() . '#entryID' . $this->showcaseObject->entryID;

        switch ($status) {
            case ENTRY_STATUS_PENDING_APPROVAL:
                $redirectMessage = $lang->myShowcaseEntryEntryUnapproved;
                break;
            case ENTRY_STATUS_VISIBLE:
                $redirectMessage = $lang->myShowcaseEntryEntryApproved;
                break;
            case ENTRY_STATUS_SOFT_DELETED:
                $redirectMessage = $lang->myShowcaseEntryEntrySoftDeleted;
                break;
        }

        redirect($entryUrl, $redirectMessage);
    }

    #[NoReturn] public function unapproveEntry(
        string $entrySlug,
    ): void {
        $this->approveEntry(
            $entrySlug,
            ENTRY_STATUS_PENDING_APPROVAL
        );
    }

    #[NoReturn] public function softDeleteEntry(
        string $entrySlug
    ): void {
        $this->approveEntry(
            $entrySlug,
            ENTRY_STATUS_SOFT_DELETED
        );
    }

    #[NoReturn] public function restoreEntry(
        string $entrySlug
    ): void {
        $this->approveEntry(
            $entrySlug
        );
    }

    #[NoReturn] public function deleteEntry(
        string $entrySlug
    ): void {
        global $mybb, $lang;

        $currentUserID = (int)$mybb->user['uid'];

        $this->showcaseObject->setEntry($entrySlug);

        if (!$this->showcaseObject->entryID ||
            !(
                $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries] ||
                ($this->showcaseObject->entryUserID === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteEntries])
            ) || !$this->showcaseObject->entryID) {
            error_no_permission();
        }

        $this->showcaseObject->entryDelete();

        $mainUrl = url(URL_TYPE_MAIN, getParams: $this->showcaseObject->urlParams)->getRelativeUrl();

        redirect($mainUrl, $lang->myShowcaseEntryEntryDeleted);

        exit;
    }

    #[NoReturn] private function uploadAttachment(): void
    {
        $process_file = (
            !empty($_FILES['attachment']) &&
            !empty($_FILES['attachment']['name'])
        );

        if (!$process_file) {
            return;
        }

        if (!$this->showcaseObject->userPermissions[UserPermissions::CanUploadAttachments]) {
            error_no_permission();
        }

        global $mybb, $lang;

        $mybb->input['preview'] = true;

        $currentUserID = (int)$mybb->user['uid'];

        require_once MYBB_ROOT . 'inc/functions_upload.php';

        $fileObject = attachmentUpload(
            $this->showcaseObject,
            $_FILES['attachment'],
            watermarkImage: $mybb->get_input('attachment_watermark_file', MyBB::INPUT_BOOL),
        );

        if (isset($fileObject['error'])) {
            $this->showcaseObject->errorMessages = array_merge(
                $this->showcaseObject->errorMessages,
                (array)$fileObject['error']
            );
        }
    }
}

//todo review hooks here