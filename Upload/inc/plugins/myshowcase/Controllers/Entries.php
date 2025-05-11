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

namespace MyShowcase\Controllers;

use MyBB;
use JetBrains\PhpStorm\NoReturn;
use MyShowcase\Models\Entries as EntriesModel;
use MyShowcase\System\FieldHtmlTypes;
use MyShowcase\System\ModeratorPermissions;
use MyShowcase\System\Router;
use MyShowcase\System\UserPermissions;
use Pecee\SimpleRouter\SimpleRouter;

use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\cleanSlug;
use function MyShowcase\Core\commentsGet;
use function MyShowcase\Core\createUUIDv4;
use function MyShowcase\Core\dataHandlerGetObject;
use function MyShowcase\Core\dataTableStructureGet;
use function MyShowcase\Core\fieldDataGet;
use function MyShowcase\Core\formatField;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\Core\urlHandlerBuild;
use function MyShowcase\SimpleRouter\url;

use const MyShowcase\ROOT;
use const MyShowcase\Core\COMMENT_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\COMMENT_STATUS_SOFT_DELETED;
use const MyShowcase\Core\COMMENT_STATUS_VISIBLE;
use const MyShowcase\Core\FILTER_TYPE_USER_ID;
use const MyShowcase\Core\URL_TYPE_COMMENT_CREATE;
use const MyShowcase\Core\URL_TYPE_ENTRY_CREATE;
use const MyShowcase\Core\URL_TYPE_ENTRY_VIEW;
use const MyShowcase\Core\URL_TYPE_MAIN;
use const MyShowcase\Core\URL_TYPE_MAIN_USER;
use const MyShowcase\Core\ATTACHMENT_UNLIMITED;
use const MyShowcase\Core\ATTACHMENT_ZERO;
use const MyShowcase\Core\DATA_HANDLER_METHOD_UPDATE;
use const MyShowcase\Core\DATA_TABLE_STRUCTURE;
use const MyShowcase\Core\ENTRY_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\ENTRY_STATUS_SOFT_DELETED;
use const MyShowcase\Core\ENTRY_STATUS_VISIBLE;
use const MyShowcase\Core\ORDER_DIRECTION_ASCENDING;
use const MyShowcase\Core\ORDER_DIRECTION_DESCENDING;
use const MyShowcase\Core\TABLES_DATA;

class Entries extends Base
{
    public function __construct(
        public ?Router $router = null,
        protected ?EntriesModel $entriesModel = null,
    ) {
        require_once ROOT . '/Models/Entries.php';

        $this->entriesModel = new EntriesModel();

        parent::__construct($router);

        if (!$this->showcaseObject->userPermissions[UserPermissions::CanView]) {
            error_no_permission();
        }
    }

    public function setEntry(
        string $entrySlug,
        bool $loadFields = false
    ): void {
        global $db;

        $dataTableStructure = dataTableStructureGet($this->showcaseObject->showcase_id);

        $whereClauses = ["entryData.entry_slug='{$db->escape_string($entrySlug)}'"];

        $queryFields = array_merge(array_map(function (string $columnName): string {
            return 'entryData.' . $columnName;
        }, array_keys(DATA_TABLE_STRUCTURE['myshowcase_data'])), [
            'userData.username',
        ]);

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

    #[NoReturn] public function listEntries(
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
        global $lang, $mybb, $db;
        global $theme;

        $currentUserID = (int)$mybb->user['uid'];

        /*
                _dump(
                    url(URL_TYPE_ENTRY_CREATE)->getRelativeUrl(),
                    url(URL_TYPE_ENTRY_CREATE)->getRelativeUrl(),
                    url(URL_TYPE_ENTRY_CREATE)->getOriginalPath(),
                    url(URL_TYPE_ENTRY_CREATE)->getOriginalUrl(),
                    url(URL_TYPE_ENTRY_CREATE)->contains('/user'),
                );
        */
        //$this->showcaseObject->getDefaultFilter();

        if ($limit < 1) {
            $limit = $this->showcaseObject->config['entries_per_page'];
        }

        if ($limit < 2) {
            $limit = 2;
        }

        $hookArguments = [];

        $hookArguments = hooksRun('output_main_start', $hookArguments);

        $entryCreateButton = '';

        $displayCreateButton = $this->showcaseObject->userPermissions[UserPermissions::CanCreateEntries];

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
            array_merge($this->outputObject->urlParams, ['order_by' => $this->showcaseObject->orderBy])
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

            //_dump($this->showcaseObject->fieldSetFieldsDisplayFields);
            //$showcaseTableTheadExtraFieldOrder = $this->showcaseObject->fieldSetFieldsDisplayFields[$fieldKey];

            $showcaseTableTheadExtra .= eval($this->renderObject->templateGet('pageMainTableTheadRowField'));

            ++$showcaseColumnsCount;
        }

        //setup joins for query and build where clause based on search_field terms

        $queryTables = ["{$this->showcaseObject->dataTableName} entryData"];

        $queryTables [] = 'users userData ON (userData.uid = entryData.user_id)';

        $queryFields = array_merge(array_map(function (string $columnName): string {
            return 'entryData.' . $columnName;
        }, array_keys(DATA_TABLE_STRUCTURE['myshowcase_data'])), [
            'userData.username',
            'userData.usergroup',
            'userData.displaygroup'
        ]);

        $searchDone = false;

        $whereClauses = array_merge($whereClauses, $this->showcaseObject->whereClauses);

        foreach ($this->showcaseObject->fieldSetCache as $fieldID => $fieldData) {
            $fieldKey = $fieldData['field_key'];

            $htmlType = $fieldData['html_type'];

            if ($htmlType === FieldHtmlTypes::SelectSingle || $htmlType === FieldHtmlTypes::Radio) {
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

        $totalEntries = (int)($this->entriesModel->getEntries(
            $queryTables,
            ['COUNT(entryData.entry_id) AS total_entries'],
            $whereClauses,
            array_merge(['limit' => 1], $queryOptions)
        )['total_entries'] ?? 0);

        $showcaseEntriesList = '';

        $pagination = '';

        $alternativeBackground = alt_trow(true);

        $hookArguments = hooksRun('output_main_intermediate', $hookArguments);

        if ($totalEntries) {
            $entriesPerPage = $limit;

            if ($this->outputObject->pageCurrent > 0) {
                $pageCurrent = $this->outputObject->pageCurrent;

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
                urlHandlerBuild(array_merge($this->outputObject->urlParams, ['page' => '{page}']), '&amp;', false)
            );

            $entriesObjects = $this->entriesModel->getEntries(
                $queryTables,
                $queryFields,
                $whereClauses,
                $queryOptions
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
                        'file_type',
                        'file_name',
                        'attachment_name',
                        'thumbnail'
                    ],
                    // todo, seems like MIN(attachment_id) as attachment_id is unnecessary
                    ['group_by' => 'entry_id']
                );

                foreach ($attachmentObjects as $attachmentID => $attachmentData) {
                    $entryAttachmentsCache[$attachmentData['entry_id']] = [
                        'attachment_id' => $attachmentID,
                        'attachment_name' => $attachmentData['attachment_name'],
                        'thumbnail' => $attachmentData['thumbnail'],
                        'file_type' => $attachmentData['file_type'],
                        'file_name' => $attachmentData['file_name'],
                    ];
                }
            }

            $inlineModerationCount = 0;

            foreach ($entriesObjects as $entryFieldData) {
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
                    $alternativeBackground .= ' trow_shaded';
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
                    ['entry_slug' => $this->showcaseObject->entryData['entry_slug']],
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

                //use default image is specified
                if ($this->showcaseObject->config['attachments_uploads_path'] !== '' && (file_exists(
                            $theme['imgdir'] . '/' . $this->showcaseObject->config['attachments_uploads_path']
                        ) || stristr(
                            $theme['imgdir'],
                            'http://'
                        ))) {
                    $urlImage = $mybb->get_asset_url(
                        $theme['imgdir'] . '/' . $this->showcaseObject->config['attachments_uploads_path']
                    );

                    $entryImage = eval($this->renderObject->templateGet('pageMainTableRowsImage'));
                }

                //use showcase attachment if one exists, scaled of course
                if ($this->showcaseObject->config['attachments_main_render_first']) {
                    if (stristr($entryAttachmentsCache[$entryFieldData['entry_id']]['file_type'], 'image/')) {
                        $imagePath = $this->showcaseObject->config['attachments_uploads_path'] . '/' . $entryAttachmentsCache[$entryFieldData['entry_id']]['attachment_name'];

                        if ($entryAttachmentsCache[$entryFieldData['entry_id']]['attachment_id'] && file_exists(
                                $imagePath
                            )) {
                            if ($entryAttachmentsCache[$entryFieldData['entry_id']]['thumbnail'] === 'SMALL') {
                                $urlImage = $mybb->get_asset_url($imagePath);

                                $entryImage = eval($this->renderObject->templateGet('pageMainTableRowsImage'));
                            } else {
                                $urlImage = $mybb->get_asset_url(
                                    $this->showcaseObject->config['attachments_uploads_path'] . '/' . $entryAttachmentsCache[$entryFieldData['entry_id']]['thumbnail']
                                );

                                $entryImage = eval($this->renderObject->templateGet('pageMainTableRowsImage'));
                            }
                        }
                    } else {
                        $attachmentTypes = (array)$mybb->cache->read('attachtypes');

                        $attachmentExtension = get_extension(
                            $entryAttachmentsCache[$entryFieldData['entry_id']]['file_name']
                        );

                        if (array_key_exists($attachmentExtension, $attachmentTypes)) {
                            $urlImage = $mybb->get_asset_url($attachmentTypes[$attachmentExtension]['icon']);

                            $entryImage = eval($this->renderObject->templateGet('pageMainTableRowsImage'));
                        }
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

                $entrySubject = implode(' ', $entrySubject) ?? $lang->myShowcaseMainTableTheadView;

                $this->renderObject->entryBuildAttachments($showcaseTableRowExtra);

                $showcaseTableRowExtra = implode('', $showcaseTableRowExtra);

                $showcaseTableRowInlineModeration = '';

                if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries] &&
                    $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries]) {
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

        $urlSortByUsername = urlHandlerBuild(array_merge($this->outputObject->urlParams, ['sort_by' => 'username']));

        $urlSortByComments = urlHandlerBuild(array_merge($this->outputObject->urlParams, ['sort_by' => 'comments']));

        $urlSortByViews = urlHandlerBuild(array_merge($this->outputObject->urlParams, ['sort_by' => 'views']));

        $urlSortByDateline = urlHandlerBuild(array_merge($this->outputObject->urlParams, ['sort_by' => 'dateline']));

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

    #[NoReturn] public function listEntriesUser(
        int $userID,
        int $limit = 10,
        int $limitStart = 0,
        string $groupBy = '',
        string $orderBy = 'user_id',
        string $orderDirection = ORDER_DIRECTION_ASCENDING
    ): void {
        $this->listEntries(
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

    #[NoReturn] public function listEntriesUnapproved(
        int $userID = 0,
        int $limit = 10,
        int $limitStart = 0,
        string $groupBy = '',
        string $orderBy = 'user_id',
        string $orderDirection = ORDER_DIRECTION_ASCENDING
    ): void {
        $statusUnapproved = ENTRY_STATUS_PENDING_APPROVAL;

        $this->listEntries(
            $userID,
            $limit,
            $limitStart,
            $groupBy,
            $orderBy,
            $orderDirection,
            ["status='{$statusUnapproved}'"]
        );
    }

    /**
     * @throws RandomException
     */
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
            $this->setEntry($entrySlug, true);
        }

        switch ($this->showcaseObject->config['filter_force_field']) {
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

        if ($mybb->request_method === 'post') {
            verify_post_check($mybb->get_input('my_post_key'));

            $entryHash = $mybb->get_input('entryHash');

            $this->uploadAttachment();

            if (!empty($showcaseUserPermissions[UserPermissions::CanUploadAttachments])) {
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
                        $attachments .= eval(
                        $this->renderObject->templateGet(
                            'new_attachments_attachment_unapproved'
                        )
                        );
                    } else {
                        $attachments .= eval($this->renderObject->templateGet('new_attachments_attachment'));
                    }
                    $attachcount++;
                }
                $lang->myshowcase_attach_quota = $lang->sprintf(
                        $lang->myshowcase_attach_quota,
                        $attachcount,
                        ($showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit] === ATTACHMENT_UNLIMITED ? $lang->myshowcase_unlimited : $showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit])
                    ) . '<br>';
                if ($showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit] === ATTACHMENT_UNLIMITED || ($showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit] !== ATTACHMENT_ZERO && ($attachcount < $showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit]))) {
                    if ($this->showcaseObject->userPermissions[UserPermissions::CanWaterMarkAttachments]) {
                        $showcase_watermark = eval($this->renderObject->templateGet('watermark'));
                    }
                    $showcase_new_attachments_input = eval(
                    $this->renderObject->templateGet(
                        'new_attachments_input'
                    )
                    );
                }
                $showcase_attachments = eval($this->renderObject->templateGet('new_attachments'));
            }

            $insertData = [
                'user_id' => $currentUserID
            ];

            if ($this->showcaseObject->config['moderate_entries_update'] &&
                !$this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries]) {
                $insertData['status'] = ENTRY_STATUS_PENDING_APPROVAL;
            }

            if ($entryHash) {
                $insertData['entry_hash'] = $entryHash;
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

                switch ($fieldData['html_type']) {
                    case FieldHtmlTypes::SelectSingle;
                    case FieldHtmlTypes::Radio;
                    case FieldHtmlTypes::CheckBox;
                        $insertData[$fieldKey] = $mybb->get_input($fieldKey, MyBB::INPUT_INT);
                        break;
                    case FieldHtmlTypes::Date;
                        $insertData[$fieldKey] = $mybb->get_input($fieldKey . '_month', MyBB::INPUT_INT) . '|' .
                            $mybb->get_input($fieldKey . '_day', MyBB::INPUT_INT) . '|' .
                            $mybb->get_input($fieldKey . '_year', MyBB::INPUT_INT);
                        break;
                    default:
                        $insertData[$fieldKey] = $mybb->get_input($fieldKey);
                        break;
                }

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

            if ($isEditPage && $this->showcaseObject->config['moderate_entries_update']) {
                $insertData['status'] = ENTRY_STATUS_PENDING_APPROVAL;
            } elseif (!$isEditPage && $this->showcaseObject->config['moderate_entries_create']) {
                $insertData['status'] = ENTRY_STATUS_PENDING_APPROVAL;
            }

            $dataHandler->set_data($insertData);

            if (!$dataHandler->entryValidateData()) {
                $this->showcaseObject->errorMessages = array_merge(
                    $this->showcaseObject->errorMessages,
                    $dataHandler->get_friendly_errors()
                );
            }

            if (!$this->showcaseObject->errorMessages) {
                if ($isEditPage) {
                    $insertResult = $dataHandler->updateEntry();
                } else {
                    $insertResult = $dataHandler->entryInsert();
                }

                if (isset($insertResult['status']) && $insertResult['status'] !== ENTRY_STATUS_VISIBLE) {
                    $mainUrl = url(URL_TYPE_MAIN, getParams: $this->showcaseObject->urlParams)->getRelativeUrl();

                    redirect(
                        $mainUrl,
                        $isEditPage ? $lang->myShowcaseEntryEntryUpdatedStatus : $lang->myShowcaseEntryEntryCreatedStatus
                    );
                } else {
                    $entryUrl = url(
                        URL_TYPE_ENTRY_VIEW,
                        ['entry_slug' => $insertResult['entry_slug']]
                    )->getRelativeUrl();

                    redirect(
                        $entryUrl,
                        $isEditPage ? $lang->myShowcaseEntryEntryUpdated : $lang->myShowcaseEntryEntryCreated
                    );
                }

                exit;
            }
        } elseif ($isEditPage) {
            $mybb->input = array_merge($this->showcaseObject->entryData, $mybb->input);

            $entryHash = $mybb->get_input('entry_hash');
        } else {
            $entryHash = createUUIDv4();
        }

        if ($isEditPage && !$this->showcaseObject->userPermissions[UserPermissions::CanUpdateEntries]) {
            error_no_permission();
        } elseif (!$isEditPage && !$this->showcaseObject->userPermissions[UserPermissions::CanCreateEntries]) {
            error_no_permission();
        }

        $hookArguments = hooksRun('output_new_start', $hookArguments);

        global $errorsAttachments;

        $errorsAttachments ??= '';

        $attachmentsTable = '';

        // Get a listing of the current attachments.
        if (!empty($showcaseUserPermissions[UserPermissions::CanUploadAttachments])) {
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
                    $attachments .= eval($this->renderObject->templateGet('new_attachments_attachment_unapproved'));
                } else {
                    $attachments .= eval($this->renderObject->templateGet('new_attachments_attachment'));
                }
                $attachcount++;
            }
            $lang->myshowcase_attach_quota = $lang->sprintf(
                    $lang->myshowcase_attach_quota,
                    $attachcount,
                    ($showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit] === ATTACHMENT_UNLIMITED ? $lang->myshowcase_unlimited : $showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit])
                ) . '<br>';
            if ($showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit] === ATTACHMENT_UNLIMITED || ($showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit] !== ATTACHMENT_UNLIMITED && $attachcount < $showcaseUserPermissions[UserPermissions::AttachmentsFilesLimit])) {
                if ($this->showcaseObject->userPermissions[UserPermissions::CanWaterMarkAttachments] && $this->showcaseObject->config['attachments_watermark_file'] !== '' && file_exists(
                        $this->showcaseObject->config['attachments_watermark_file']
                    )) {
                    $showcase_watermark = eval($this->renderObject->templateGet('watermark'));
                }
                $showcase_new_attachments_input = eval($this->renderObject->templateGet('new_attachments_input'));
            }

            if (!empty($showcase_new_attachments_input) || $attachments !== '') {
                $attachmentsTable = eval($this->renderObject->templateGet('pageNewAttachments'));
            }
        }

        $alternativeBackground = alt_trow(true);

        $showcaseFields = '';

        $fieldTabIndex = 1;

        foreach ($this->showcaseObject->fieldSetCache as $fieldID => $fieldData) {
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
                    $code_buttons = $smile_inserter = '';

                    $this->renderObject->buildEditor($code_buttons, $smile_inserter, $fieldKey);

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
                case FieldHtmlTypes::SelectSingle:
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

            $showcaseFields .= eval($this->renderObject->templateGet('pageEntryCreateUpdateRow'));

            ++$fieldTabIndex;

            $alternativeBackground = alt_trow();
        }

        $hookArguments = hooksRun('output_new_end', $hookArguments);

        if ($isEditPage) {
            $createUpdateUrl = $this->showcaseObject->urlBuild(
                $this->showcaseObject->urlUpdateEntry,
                $this->showcaseObject->entryData['entry_slug']
            );
        } else {
            $createUpdateUrl = $this->showcaseObject->urlBuild(
                $this->showcaseObject->urlCreateEntry
            );
        }

        $attachmentsUpload = $this->renderObject->buildAttachmentsUpload($isEditPage);

        $hookArguments = hooksRun('entry_create_update_end', $hookArguments);

        extract($hookArguments['extractVariables']);

        $this->outputSuccess(eval($this->renderObject->templateGet('pageEntryCreateUpdateContents')));
    }

    #[NoReturn] public function updateEntry(
        string $entrySlug,
    ): void {
        $this->createEntry(true, $entrySlug);
    }

    #[NoReturn] public function viewEntry(
        string $entrySlug,
        int $commentID = 0,
        array $commentData = []
    ): void {
        global $mybb, $lang, $db, $theme;

        if ($mybb->request_method === 'post') {
        }

        /*if ($mybb->get_input('action') === 'comment_create') {
            require_once ROOT . '/Controllers/Comments.php';

            (new \MyShowcase\Controllers\Comments())->
            $controlelr = ;
            _dump($controlelr);
        }*/

        $hookArguments = [
            'this' => &$this
        ];

        $extractVariables = [];

        $hookArguments['extractVariables'] = &$extractVariables;

        $currentUserID = (int)$mybb->user['uid'];

        if (empty($this->showcaseObject->entryID)) {
            $this->setEntry($entrySlug, true);
        }

        if (!$this->showcaseObject->entryID || empty($this->showcaseObject->entryData)) {
            error($lang->myshowcase_invalid_id);
        }

        switch ($this->showcaseObject->config['filter_force_field']) {
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

        //$entryHash = $this->showcaseObject->entryData['entry_hash'];

        //trick MyBB into thinking its in archive so it adds bburl to smile link inside parser
        //doing this now should not impact anyhting. no issues with gomobile beta4
        define('IN_ARCHIVE', 1);

        $entryPost = $this->renderObject->buildEntry($this->showcaseObject->entryData);

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

            $currentPage = $mybb->get_input('page', MyBB::INPUT_INT);

            if ($commentID) {
                $commentTimeStamp = (int)$commentData['dateline'];

                $totalCommentsBeforeMainComment = (int)(commentsGet(
                    array_merge($whereClauses, ["dateline<='{$commentTimeStamp}'"]),
                    ['COUNT(comment_id) AS total_comments'],
                    ['limit' => 1]
                )['total_comments'] ?? 0);

                if (($totalCommentsBeforeMainComment % $this->showcaseObject->config['comments_per_page']) == 0) {
                    $currentPage = $totalCommentsBeforeMainComment / $this->showcaseObject->config['comments_per_page'];
                } else {
                    $currentPage = (int)($totalCommentsBeforeMainComment / $this->showcaseObject->config['comments_per_page']) + 1;
                }
            }

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

            SimpleRouter::get('/product-view/{id}', 'ProductsController@show', ['as' => 'product']);

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
                    URL_TYPE_ENTRY_VIEW,
                    ['entry_slug' => $this->showcaseObject->entryData['entry_slug']],
                    $urlParams
                )->getRelativeUrl()
            ) ?? '';

            extract($hookArguments['extractVariables']);

            $extractVariables = [];

            $hookArguments = hooksRun('entry_view_comment_form_intermediate', $hookArguments);

            $commentObjects = commentsGet(
                $whereClauses,
                ['user_id', 'comment', 'dateline', 'ipaddress', 'status', 'moderator_user_id'],
                $queryOptions
            );

            $alternativeBackground = alt_trow(true);

            foreach ($commentObjects as $commentID => $commentData) {
                ++$commentsCounter;

                $commentsList .= $this->renderObject->buildComment(
                    $commentsCounter,
                    $commentData,
                    $alternativeBackground
                );

                $alternativeBackground = alt_trow();
            }

            if (!$commentsList) {
                $commentsEmpty = eval($this->renderObject->templateGet('pageViewCommentsNone'));
            }

            $hookArguments = hooksRun('entry_view_comment_form_end', $hookArguments);

            if (!$currentUserID) {
                $commentsForm = eval($this->renderObject->templateGet('pageViewCommentsFormGuest'));
            } elseif ($this->showcaseObject->userPermissions[UserPermissions::CanCreateComments]) {
                global $collapsedthead, $collapsedimg, $expaltext, $collapsed;

                isset($collapsedthead) || $collapsedthead = [];

                isset($collapsedimg) || $collapsedimg = [];

                isset($collapsed) || $collapsed = [];

                $collapsedthead['quickreply'] ??= '';

                $collapsedimg['quickreply'] ??= '';

                $collapsed['quickreply_e'] ??= '';

                $commentLengthLimitNote = $lang->sprintf(
                    $lang->myshowcase_comment_text_limit,
                    my_number_format($this->showcaseObject->config['comments_maximum_length'])
                );

                $alternativeBackground = alt_trow(true);

                $createUpdateUrl = url(
                    URL_TYPE_COMMENT_CREATE,
                    ['entry_slug' => $this->showcaseObject->entryData['entry_slug']]
                )->getRelativeUrl();

                $commentMessage = htmlspecialchars_uni($mybb->get_input('comment'));

                $code_buttons = $smile_inserter = '';

                $this->renderObject->buildCommentsFormEditor($code_buttons, $smile_inserter);

                $commentsForm = eval($this->renderObject->templateGet('pageViewCommentsFormUser'));
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

        $this->setEntry($entrySlug);

        if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries] ||
            !$this->showcaseObject->entryID) {
            error_no_permission();
        }

        $dataHandler = dataHandlerGetObject($this->showcaseObject, DATA_HANDLER_METHOD_UPDATE);

        $dataHandler->set_data(['status' => $status]);

        if ($dataHandler->entryValidateData()) {
            $dataHandler->entryUpdate();

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

        $this->setEntry($entrySlug);

        if (!$this->showcaseObject->entryID ||
            !(
                $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries] ||
                ($this->showcaseObject->entryUserID === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteEntries])
            ) || !$this->showcaseObject->entryID) {
            error_no_permission();
        }

        $dataHandler = dataHandlerGetObject($this->showcaseObject);

        $dataHandler->entryDelete();

        $mainUrl = url(URL_TYPE_MAIN, getParams: $this->showcaseObject->urlParams)->getRelativeUrl();

        redirect($mainUrl, $lang->myShowcaseEntryEntryDeleted);

        exit;
    }

    #[NoReturn] private function uploadAttachment(): void
    {
        if (empty($_FILES['attachment']) || empty($_FILES['attachment']['name'])) {
            return;
        }
        /*
        echo json_encode($_FILES['attachment']);
        exit();*/
    }
}