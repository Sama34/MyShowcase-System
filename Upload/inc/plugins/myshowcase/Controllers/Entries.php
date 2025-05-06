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
use MyShowcase\System\ModeratorPermissions;
use MyShowcase\System\Router;
use MyShowcase\System\UserPermissions;

use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\commentsGet;
use function MyShowcase\Core\dataHandlerGetObject;
use function MyShowcase\Core\dataTableStructureGet;
use function MyShowcase\Core\fieldDataGet;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\Core\urlHandlerBuild;

use const MyShowcase\ROOT;
use const MyShowcase\Core\COMMENT_STATUS_VISIBLE;
use const MyShowcase\Core\ATTACHMENT_UNLIMITED;
use const MyShowcase\Core\ATTACHMENT_ZERO;
use const MyShowcase\Core\DATA_HANDLERT_METHOD_UPDATE;
use const MyShowcase\Core\DATA_TABLE_STRUCTURE;
use const MyShowcase\Core\ENTRY_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\ENTRY_STATUS_SOFT_DELETED;
use const MyShowcase\Core\ENTRY_STATUS_VISIBLE;
use const MyShowcase\Core\FIELD_TYPE_HTML_CHECK_BOX;
use const MyShowcase\Core\FIELD_TYPE_HTML_DATE;
use const MyShowcase\Core\FIELD_TYPE_HTML_DB;
use const MyShowcase\Core\FIELD_TYPE_HTML_RADIO;
use const MyShowcase\Core\FIELD_TYPE_HTML_TEXT_BOX;
use const MyShowcase\Core\FIELD_TYPE_HTML_TEXTAREA;
use const MyShowcase\Core\FIELD_TYPE_HTML_URL;
use const MyShowcase\Core\FORMAT_TYPE_MY_NUMBER_FORMAT;
use const MyShowcase\Core\FORMAT_TYPE_NONE;
use const MyShowcase\Core\FORMAT_TYPES;
use const MyShowcase\Core\ORDER_DIRECTION_ASCENDING;
use const MyShowcase\Core\ORDER_DIRECTION_DESCENDING;
use const MyShowcase\Core\TABLES_DATA;

class Entries extends Base
{
    public function __construct(
        public Router $router,
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
            foreach ($this->showcaseObject->fieldSetEnabledFields as $fieldName => $htmlType) {
                if ($htmlType === FIELD_TYPE_HTML_DB || $htmlType === FIELD_TYPE_HTML_RADIO) {
                    $queryTables[] = "myshowcase_field_data table_{$fieldName} ON (table_{$fieldName}.value_id=entryData.{$fieldName} AND table_{$fieldName}.name='{$fieldName}')";

                    $queryFields[] = "table_{$fieldName}.value AS {$fieldName}";

                    // todo, I don't understand the purpose of this now
                    // the condition after OR seems to fix it for now
                    //$whereClauses[] = "(table_{$fieldName}.set_id='{$this->showcaseObject->fieldSetID}' OR entryData.{$fieldName}=0)";
                } else {
                    $queryFields[] = $fieldName;
                }
            }
        }

        $this->showcaseObject->entryDataSet(
            $this->showcaseObject->dataGet($whereClauses, $queryFields, ['limit' => 1], $queryTables)
        );
    }

    #[NoReturn] public function listEntries(
        string $showcaseSlug,
        int $limit = 10,
        int $limitStart = 0,
        string $groupBy = '',
        string $orderBy = 'user_id',
        string $orderDirection = ORDER_DIRECTION_ASCENDING,
        array $whereClauses = []
    ): void {
        global $lang, $mybb, $db;
        global $theme;

        $hookArguments = [];

        $hookArguments = hooksRun('output_main_start', $hookArguments);

        add_breadcrumb(
            $this->showcaseObject->nameFriendly,
            $this->showcaseObject->urlBuild($this->showcaseObject->urlMain)
        );

        $buttonNewEntry = '';

        if ($this->showcaseObject->userPermissions[UserPermissions::CanAddEntries]) {
            $urlEntryCreate = $this->showcaseObject->urlBuild($this->showcaseObject->urlCreateEntry);

            $buttonNewEntry = eval($this->renderObject->templateGet('buttonNewEntry'));
        }

        $showcaseSelectOrderAscendingSelectedElement = $showcaseSelectOrderDescendingSelectedElement = '';

        switch ($this->showcaseObject->orderBy) {
            case ORDER_DIRECTION_ASCENDING:
                $showcaseSelectOrderAscendingSelectedElement = 'selected="selected"';

                $showcaseInputOrderText = $lang->myshowcase_desc;
                break;
            case ORDER_DIRECTION_DESCENDING:
                $showcaseSelectOrderDescendingSelectedElement = 'selected="selected"';

                $showcaseInputOrderText = $lang->myshowcase_asc;
                break;
            default:
                $showcaseInputOrderText = '';
        }

        //build sort_by option list
        $selectOptions = '';

        reset($this->showcaseObject->fieldSetFieldsDisplayFields);

        foreach ($this->showcaseObject->fieldSetFieldsDisplayFields as $fieldName => $fieldDisplayName) {
            $selectedElement = '';

            if ($this->showcaseObject->sortByField === $fieldName) {
                $selectedElement = 'selected="selected"';
            }

            $fieldDisplayName = $lang->myShowcaseMainSelectSortBy . ' ' . $fieldDisplayName;

            $selectOptions .= eval($this->renderObject->templateGet('pageMainSelectOption'));
        }

        $selectFieldName = 'sort_by';

        $selectFieldCode = eval($this->renderObject->templateGet('pageMainSelect'));

        //build searchfield option list
        $selectOptionsSearchField = '';

        reset($this->renderObject->fieldSetFieldsSearchFields);

        foreach ($this->renderObject->fieldSetFieldsSearchFields as $fieldName => $fieldDisplayName) {
            $optionSelectedElement = '';

            if ($this->showcaseObject->searchField === $fieldName) {
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

        $orderInputs[$this->showcaseObject->sortByField] = eval(
        $this->renderObject->templateGet(
            'pageMainTableTheadFieldSort'
        )
        );

        // Check if the active user is a moderator and get the inline moderation tools.
        $showcaseColumnsCount = 5;

        //build custom list header based on field settings
        $showcaseTableTheadExtra = '';

        foreach ($this->showcaseObject->fieldSetFieldsOrder as $fieldOrder => $fieldName) {
            $showcaseTableTheadExtraFieldTitle = $lang->{"myshowcase_field_{$fieldName}"} ?? ucfirst($fieldName);

            $showcaseTableTheadExtraFieldOrder = $this->showcaseObject->fieldSetFieldsDisplayFields[$fieldName];

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

        reset($this->showcaseObject->fieldSetSearchableFields);

        foreach ($this->showcaseObject->fieldSetSearchableFields as $fieldName => $htmlType) {
            if ($htmlType === FIELD_TYPE_HTML_DB || $htmlType === FIELD_TYPE_HTML_RADIO) {
                $queryTables[] = "myshowcase_field_data table_{$fieldName} ON (table_{$fieldName}.value_id = entryData.{$fieldName} AND table_{$fieldName}.name = '{$fieldName}')";

                $queryFields[] = "table_{$fieldName}.value AS {$fieldName}";

                if ($this->renderObject->searchKeyWords && $this->showcaseObject->searchField === $fieldName) {
                    if ($this->renderObject->searchExactMatch) {
                        $whereClauses[] = "table_{$fieldName}.value='{$db->escape_string($this->renderObject->searchKeyWords)}'";
                    } else {
                        $whereClauses[] = "table_{$fieldName}.value LIKE '%{$db->escape_string($this->renderObject->searchKeyWords)}%'";
                    }

                    $whereClauses[] = "table_{$fieldName}.set_id='{$this->showcaseObject->fieldSetID}'";
                }
            } elseif ($this->showcaseObject->searchField === 'username' && !$searchDone) {
                $queryTables[] = 'users us ON (entryData.user_id = us.uid)';

                $queryFields[] = $fieldName;

                if ($this->renderObject->searchKeyWords) {
                    if ($this->renderObject->searchExactMatch) {
                        $whereClauses[] = "us.username='{$db->escape_string($this->renderObject->searchKeyWords)}'";
                    } else {
                        $whereClauses[] = "us.username LIKE '%{$db->escape_string($this->renderObject->searchKeyWords)}%'";
                    }
                }

                $searchDone = true;
            } else {
                $queryFields[] = $fieldName;

                if ($this->renderObject->searchKeyWords && $this->showcaseObject->searchField === $fieldName) {
                    if ($this->renderObject->searchExactMatch) {
                        $whereClauses[] = "entryData.{$fieldName}='{$db->escape_string($this->renderObject->searchKeyWords)}'";
                    } else {
                        $whereClauses[] = "entryData.{$fieldName} LIKE '%{$db->escape_string($this->renderObject->searchKeyWords)}%'";
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
            $entriesPerPage = $mybb->settings['threadsperpage'];

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

            if ($this->showcaseObject->userEntryAttachmentAsImage) {
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

                $entryFieldData['username'] = $entryFieldData['username'] ?? $lang->guest;

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

                $entryUrl = $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlViewEntry,
                    $this->showcaseObject->entryData['entry_slug']
                );

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
                if ($this->showcaseObject->defaultImage !== '' && (file_exists(
                            $theme['imgdir'] . '/' . $this->showcaseObject->defaultImage
                        ) || stristr(
                            $theme['imgdir'],
                            'http://'
                        ))) {
                    $urlImage = $mybb->get_asset_url($theme['imgdir'] . '/' . $this->showcaseObject->defaultImage);

                    $entryImage = eval($this->renderObject->templateGet('pageMainTableRowsImage'));
                }

                //use showcase attachment if one exists, scaled of course
                if ($this->showcaseObject->userEntryAttachmentAsImage) {
                    if (stristr($entryAttachmentsCache[$entryFieldData['entry_id']]['file_type'], 'image/')) {
                        $imagePath = $this->showcaseObject->imageFolder . '/' . $entryAttachmentsCache[$entryFieldData['entry_id']]['attachment_name'];

                        if ($entryAttachmentsCache[$entryFieldData['entry_id']]['attachment_id'] && file_exists(
                                $imagePath
                            )) {
                            if ($entryAttachmentsCache[$entryFieldData['entry_id']]['thumbnail'] === 'SMALL') {
                                $urlImage = $mybb->get_asset_url($imagePath);

                                $entryImage = eval($this->renderObject->templateGet('pageMainTableRowsImage'));
                            } else {
                                $urlImage = $mybb->get_asset_url(
                                    $this->showcaseObject->imageFolder . '/' . $entryAttachmentsCache[$entryFieldData['entry_id']]['thumbnail']
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
                    $fieldName = $fieldData['name'];

                    $entryFieldText = $entryFieldData[$fieldName] ?? '';

                    if (empty($this->showcaseObject->fieldSetParseableFields[$fieldName])) {
                        // todo, remove this legacy updating the database and updating the format field to TINYINT
                        match ($this->showcaseObject->fieldSetFormatableFields[$fieldName]) {
                            FORMAT_TYPE_MY_NUMBER_FORMAT => $this->showcaseObject->fieldSetFormatableFields[$fieldName] = FORMAT_TYPE_MY_NUMBER_FORMAT,
                            'decimal1' => $this->showcaseObject->fieldSetFormatableFields[$fieldName] = 2,
                            'decimal2' => $this->showcaseObject->fieldSetFormatableFields[$fieldName] = 3,
                            default => $this->showcaseObject->fieldSetFormatableFields[$fieldName] = 0
                        };

                        $formatTypes = FORMAT_TYPES;

                        if (!empty($formatTypes[$this->showcaseObject->fieldSetFormatableFields[$fieldName]]) &&
                            function_exists(
                                $formatTypes[$this->showcaseObject->fieldSetFormatableFields[$fieldName]]
                            )) {
                            $entryFieldText = $formatTypes[$this->showcaseObject->fieldSetFormatableFields[$fieldName]](
                                $entryFieldText
                            );
                        } else {
                            $entryFieldText = match ($this->showcaseObject->fieldSetFormatableFields[$fieldName]) {
                                2 => number_format((float)$entryFieldText, 1),
                                3 => number_format((float)$entryFieldText, 2),
                                default => htmlspecialchars_uni($entryFieldText),
                            };
                        }

                        if ($this->showcaseObject->fieldSetEnabledFields[$fieldName] === FIELD_TYPE_HTML_DATE) {
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

                    $showcaseTableRowExtra[$fieldName] = eval(
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

                if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries] &&
                    $this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries]) {
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

            if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries] &&
                $this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries]) {
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

        $pageTitle = $this->showcaseObject->name;

        $urlSortByUsername = urlHandlerBuild(array_merge($this->outputObject->urlParams, ['sort_by' => 'username']));

        $urlSortByComments = urlHandlerBuild(array_merge($this->outputObject->urlParams, ['sort_by' => 'comments']));

        $urlSortByViews = urlHandlerBuild(array_merge($this->outputObject->urlParams, ['sort_by' => 'views']));

        $urlSortByDateline = urlHandlerBuild(array_merge($this->outputObject->urlParams, ['sort_by' => 'dateline']));

        if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries] || $this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries]) {
            ++$showcaseColumnsCount;
        }

        $tableColumnInlineModeration = $inlineModeration = '';

        if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries]) {
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

    #[NoReturn] public function listEntriesUnapproved(
        string $showcaseSlug,
        int $limit = 10,
        int $limitStart = 0,
        string $groupBy = '',
        string $orderBy = 'user_id',
        string $orderDirection = ORDER_DIRECTION_ASCENDING
    ): void {
        $statusUnapproved = ENTRY_STATUS_PENDING_APPROVAL;

        $this->listEntries(
            $showcaseSlug,
            $limit,
            $limitStart,
            $groupBy,
            $orderBy,
            $orderDirection,
            ["status='{$statusUnapproved}'"]
        );
    }

    #[NoReturn] public function createEntry(
        string $showcaseSlug,
        bool $isEditPage = false,
        string $entrySlug = '',
    ): void {
        global $lang, $mybb, $db;
        global $header, $headerinclude, $footer, $theme;
        global $plugins;

        $currentUserID = (int)$mybb->user['uid'];

        $entryHash = $mybb->get_input('entryHash');

        $hookArguments = [];

        $entryUserData = get_user($this->showcaseObject->entryUserID);

        $showcaseUserPermissions = $this->showcaseObject->userPermissionsGet($this->showcaseObject->entryUserID);

        $showcase_watermark = '';

        add_breadcrumb(
            $this->showcaseObject->nameFriendly,
            $this->showcaseObject->urlBuild($this->showcaseObject->urlMain)
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

        if ($isEditPage) {
            $this->setEntry($entrySlug, true);
        }

        if ($mybb->request_method === 'post') {
            verify_post_check($mybb->get_input('my_post_key'));

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
                        ($showcaseUserPermissions[UserPermissions::AttachmentsLimit] === ATTACHMENT_UNLIMITED ? $lang->myshowcase_unlimited : $showcaseUserPermissions[UserPermissions::AttachmentsLimit])
                    ) . '<br>';
                if ($showcaseUserPermissions[UserPermissions::AttachmentsLimit] === ATTACHMENT_UNLIMITED || ($showcaseUserPermissions[UserPermissions::AttachmentsLimit] !== ATTACHMENT_ZERO && ($attachcount < $showcaseUserPermissions[UserPermissions::AttachmentsLimit]))) {
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
                'user_id' => $currentUserID,
                'dateline' => TIME_NOW
            ];

            if ($this->showcaseObject->moderateEdits &&
                !$this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries]) {
                $insertData['status'] = ENTRY_STATUS_PENDING_APPROVAL;
            }

            if ($entryHash) {
                $insertData['entry_hash'] = $entryHash;
            }

            $plugins->run_hooks('myshowcase_do_newedit_start');

            // Set up showcase handler.
            //require_once MYBB_ROOT . 'inc/datahandlers/myshowcase_dh.php';

            if ($isEditPage) {
                $dataHandler = dataHandlerGetObject($this->showcaseObject, DATA_HANDLERT_METHOD_UPDATE);
            } else {
                $dataHandler = dataHandlerGetObject($this->showcaseObject);
            }

            reset($this->showcaseObject->fieldSetEnabledFields);

            $entrySlug = [];

            foreach ($this->showcaseObject->fieldSetCache as $fieldID => $fieldData) {
                $fieldName = $fieldData['name'];

                switch ($fieldData['html_type']) {
                    case FIELD_TYPE_HTML_DB;
                    case FIELD_TYPE_HTML_RADIO;
                    case FIELD_TYPE_HTML_CHECK_BOX;
                        $insertData[$fieldName] = $mybb->get_input($fieldName, MyBB::INPUT_INT);
                        break;
                    case FIELD_TYPE_HTML_DATE;
                        $insertData[$fieldName] = $mybb->get_input($fieldName . '_month', MyBB::INPUT_INT) . '|' .
                            $mybb->get_input($fieldName . '_day', MyBB::INPUT_INT) . '|' .
                            $mybb->get_input($fieldName . '_year', MyBB::INPUT_INT);
                        break;
                    default:
                        $insertData[$fieldName] = $mybb->get_input($fieldName);
                        break;
                }

                if ($fieldData['enable_slug']) {
                    $entrySlug[] = $insertData[$fieldName];
                }
            }

            $entrySlug = str_replace(['---', '--'],
                '-',
                preg_replace(
                    '/[^\da-z]/i',
                    '-',
                    my_strtolower(implode('-', $entrySlug))
                ));

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

            $dataHandler->setData($insertData);

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

                $plugins->run_hooks('myshowcase_do_newedit_end');

                if (isset($insertResult['status']) && $insertResult['status'] !== ENTRY_STATUS_VISIBLE) {
                    $mainUrl = $this->showcaseObject->urlBuild($this->showcaseObject->urlMain);

                    redirect(
                        $mainUrl,
                        $isEditPage ? $lang->myShowcaseEntryEntryUpdatedStatus : $lang->myShowcaseEntryEntryCreatedStatus
                    );
                } else {
                    $entryUrl = $this->showcaseObject->urlBuild(
                        $this->showcaseObject->urlViewEntry,
                        $insertResult['entry_slug']
                    );

                    redirect(
                        $entryUrl,
                        $isEditPage ? $lang->myShowcaseEntryEntryUpdated : $lang->myShowcaseEntryEntryCreated
                    );
                }

                exit;
            }
        } elseif ($isEditPage) {
            $mybb->input = array_merge($this->showcaseObject->entryData, $mybb->input);
        }

        if ($isEditPage && !$this->showcaseObject->userPermissions[UserPermissions::CanEditEntries]) {
            error_no_permission();
        } elseif (!$isEditPage && !$this->showcaseObject->userPermissions[UserPermissions::CanAddEntries]) {
            error_no_permission();
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
                    $attachments .= eval($this->renderObject->templateGet('new_attachments_attachment_unapproved'));
                } else {
                    $attachments .= eval($this->renderObject->templateGet('new_attachments_attachment'));
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
                    $showcase_watermark = eval($this->renderObject->templateGet('watermark'));
                }
                $showcase_new_attachments_input = eval($this->renderObject->templateGet('new_attachments_input'));
            }

            if (!empty($showcase_new_attachments_input) || $attachments !== '') {
                $attachmentsTable = eval($this->renderObject->templateGet('pageNewAttachments'));
            }
        }

        $alternativeBackground = alt_trow(true);

        reset($this->showcaseObject->fieldSetEnabledFields);

        $showcaseFields = '';

        $fieldTabIndex = 1;

        foreach ($this->showcaseObject->fieldSetEnabledFields as $fieldName => $htmlType) {
            $fieldID = $this->showcaseObject->fieldSetFieldsIDs[$fieldName] ?? 0;

            $fieldNameEscaped = $db->escape_string($fieldName);

            $fieldTitle = $lang->{'myshowcase_field_' . $fieldName} ?? $fieldName;

            $fieldElementRequired = '';

            if ($this->showcaseObject->fieldSetFieldsRequired[$fieldName]) {
                $fieldElementRequired = 'required="required"';
            }

            $fieldMinimumLength = $this->showcaseObject->fieldSetFieldsMinimumLenght[$fieldName] ?? 0;

            $fieldMaximumLength = $this->showcaseObject->fieldSetFieldsMaximumLenght[$fieldName] ?? 0;

            $fieldInput = '';

            switch ($htmlType) {
                case FIELD_TYPE_HTML_TEXT_BOX:
                    $fieldValue = htmlspecialchars_uni($mybb->get_input($fieldName));

                    $fieldInput = eval($this->renderObject->templateGet('pageEntryCreateUpdateDataTextBox'));
                    break;
                case FIELD_TYPE_HTML_URL:
                    $fieldValue = htmlspecialchars_uni($mybb->get_input($fieldName));

                    $fieldInput = eval($this->renderObject->templateGet('pageEntryCreateUpdateDataTextUrl'));
                    break;
                case FIELD_TYPE_HTML_TEXTAREA:
                    $fieldValue = htmlspecialchars_uni($mybb->get_input($fieldName));

                    $fieldInput = eval($this->renderObject->templateGet('pageEntryCreateUpdateDataTextArea'));
                    break;
                case FIELD_TYPE_HTML_RADIO:
                    $fieldDataObjects = fieldDataGet(
                        ["set_id='{$this->showcaseObject->fieldSetID}'", "name='{$fieldNameEscaped}'"],
                        ['value_id', 'value'],
                        ['order_by' => 'display_order']
                    );

                    if ($fieldDataObjects) {
                        $fieldOptions = [];

                        foreach ($fieldDataObjects as $fieldDataID => $fieldData) {
                            $valueID = (int)$fieldData['value_id'];

                            $valueName = htmlspecialchars_uni($fieldData['value']);

                            $checkedElement = '';

                            if ($mybb->get_input($fieldName, MyBB::INPUT_INT) === $valueID) {
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
                case FIELD_TYPE_HTML_CHECK_BOX:
                    $valueID = 1;

                    $valueName = htmlspecialchars_uni($fieldName);

                    $checkedElement = '';

                    if ($mybb->get_input($fieldName, MyBB::INPUT_INT) === 1) {
                        $checkedElement = 'checked="checked"';
                    }

                    $fieldInput = eval($this->renderObject->templateGet('pageEntryCreateUpdateDataFieldCheckBox'));
                    break;
                case FIELD_TYPE_HTML_DB:
                    $fieldDataObjects = fieldDataGet(
                        [
                            "set_id='{$this->showcaseObject->fieldSetID}'",
                            "name='{$fieldNameEscaped}'",
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

                            if ($mybb->get_input($fieldName, MyBB::INPUT_INT) === $valueID) {
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

                case FIELD_TYPE_HTML_DATE:
                    list($mybb->input[$fieldName . '_month'], $mybb->input[$fieldName . '_day'], $mybb->input[$fieldName . '_year']) = array_pad(
                        array_map('intval', explode('|', $mybb->get_input($fieldName))),
                        3,
                        0
                    );

                    $daySelect = (function (string $fieldName) use (
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

                            if ($mybb->get_input($fieldName . '_day', MyBB::INPUT_INT) === $valueID) {
                                $selectedElement = 'selected="selected"';
                            }

                            $fieldOptions[] = eval(
                            $this->renderObject->templateGet(
                                'pageEntryCreateUpdateDataFieldDataBaseOption'
                            )
                            );
                        }

                        $fieldOptions = implode('', $fieldOptions);

                        $fieldName = $fieldName . '_day';

                        return eval($this->renderObject->templateGet('pageEntryCreateUpdateDataFieldDataBase'));
                    })(
                        $fieldName
                    );

                    $monthSelect = (function (string $fieldName) use (
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

                            if ($mybb->get_input($fieldName . '_month', MyBB::INPUT_INT) === $valueID) {
                                $selectedElement = 'selected="selected"';
                            }

                            $fieldOptions[] = eval(
                            $this->renderObject->templateGet(
                                'pageEntryCreateUpdateDataFieldDataBaseOption'
                            )
                            );
                        }

                        $fieldOptions = implode('', $fieldOptions);

                        $fieldName = $fieldName . '_month';

                        return eval($this->renderObject->templateGet('pageEntryCreateUpdateDataFieldDataBase'));
                    })(
                        $fieldName
                    );

                    $yearSelect = (function (string $fieldName) use (
                        $mybb,
                        $lang,
                        $fieldTabIndex,
                        $fieldElementRequired,
                        $fieldMinimumLength,
                        $fieldMaximumLength
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

                        for ($valueID = $fieldMinimumLength; $valueID <= $fieldMaximumLength; ++$valueID) {
                            $valueName = $valueID;

                            $selectedElement = '';

                            if ($mybb->get_input($fieldName . '_year', MyBB::INPUT_INT) === $valueID) {
                                $selectedElement = 'selected="selected"';
                            }

                            $fieldOptions[] = eval(
                            $this->renderObject->templateGet(
                                'pageEntryCreateUpdateDataFieldDataBaseOption'
                            )
                            );
                        }

                        $fieldOptions = implode('', $fieldOptions);

                        $fieldName = $fieldName . '_year';

                        return eval($this->renderObject->templateGet('pageEntryCreateUpdateDataFieldDataBase'));
                    })(
                        $fieldName
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

        $this->outputSuccess(eval($this->renderObject->templateGet('pageEntryCreateUpdateContents')));
    }

    #[NoReturn] public function updateEntry(
        string $showcaseSlug,
        string $entrySlug,
    ): void {
        $this->createEntry($showcaseSlug, true, $entrySlug);
    }

    #[NoReturn] public function viewEntry(
        string $showcaseSlug,
        string $entrySlug
    ): void {
        global $mybb, $plugins, $lang, $db, $theme;

        $hookArguments = [
            'this' => &$this
        ];

        $currentUserID = (int)$mybb->user['uid'];

        $plugins->run_hooks('myshowcase_view_start');

        reset($this->showcaseObject->fieldSetEnabledFields);

        $this->setEntry($entrySlug, true);

        if (!$this->showcaseObject->entryID || empty($this->showcaseObject->entryData)) {
            error($lang->myshowcase_invalid_id);
        }

        add_breadcrumb(
            $this->showcaseObject->nameFriendly,
            $this->showcaseObject->urlBuild($this->showcaseObject->urlMain)
        );

        $entrySubject = [];

        foreach ($this->showcaseObject->fieldSetCache as $fieldID => $fieldData) {
            if (!$fieldData['enable_subject']) {
                continue;
            }

            $fieldName = $fieldData['name'];

            $entryFieldText = $this->showcaseObject->entryData[$fieldName] ?? '';

            if (empty($this->showcaseObject->fieldSetParseableFields[$fieldName])) {
                // todo, remove this legacy updating the database and updating the format field to TINYINT
                match ($this->showcaseObject->fieldSetFormatableFields[$fieldName]) {
                    FORMAT_TYPE_MY_NUMBER_FORMAT => $this->showcaseObject->fieldSetFormatableFields[$fieldName] = FORMAT_TYPE_MY_NUMBER_FORMAT,
                    'decimal1' => $this->showcaseObject->fieldSetFormatableFields[$fieldName] = 2,
                    'decimal2' => $this->showcaseObject->fieldSetFormatableFields[$fieldName] = 3,
                    default => $this->showcaseObject->fieldSetFormatableFields[$fieldName] = 0
                };

                $formatTypes = FORMAT_TYPES;

                if (!empty($formatTypes[$this->showcaseObject->fieldSetFormatableFields[$fieldName]]) &&
                    function_exists(
                        $formatTypes[$this->showcaseObject->fieldSetFormatableFields[$fieldName]]
                    )) {
                    $entryFieldText = $formatTypes[$this->showcaseObject->fieldSetFormatableFields[$fieldName]](
                        $entryFieldText
                    );
                } else {
                    $entryFieldText = match ($this->showcaseObject->fieldSetFormatableFields[$fieldName]) {
                        2 => number_format((float)$entryFieldText, 1),
                        3 => number_format((float)$entryFieldText, 2),
                        default => htmlspecialchars_uni($entryFieldText),
                    };
                }

                if ($this->showcaseObject->fieldSetEnabledFields[$fieldName] === FIELD_TYPE_HTML_DATE) {
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

            if (!empty($entryFieldText)) {
                $entrySubject[] = $entryFieldText;
            }
        }

        $entrySubject = implode(' ', $entrySubject);

        if (!$entrySubject) {
            $entrySubject = str_replace(
                '{username}',
                $this->showcaseObject->entryData['username'],
                $lang->myshowcase_viewing_user
            );
        }

        add_breadcrumb(
            $entrySubject,
            $this->showcaseObject->urlBuild(
                $this->showcaseObject->urlViewEntry,
                $this->showcaseObject->entryData['entry_slug']
            )
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

        $showcase_views = $this->showcaseObject->entryData['views'];
        $showcase_numcomments = $this->showcaseObject->entryData['comments'];

        $showcase_admin_url = $this->showcaseObject->urlBuild($this->showcaseObject->urlMain);

        $showcase_view_admin_edit = '';

        if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanEditEntries] || ((int)$this->showcaseObject->entryData['user_id'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanEditEntries])) {
            $showcase_view_admin_edit = eval($this->renderObject->templateGet('view_admin_edit'));
        }

        $showcase_view_admin_delete = '';

        if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries] || ((int)$this->showcaseObject->entryData['user_id'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanEditEntries])) {
            $showcase_view_admin_delete = eval($this->renderObject->templateGet('view_admin_delete'));
        }

        //trick MyBB into thinking its in archive so it adds bburl to smile link inside parser
        //doing this now should not impact anyhting. no issues with gomobile beta4
        define('IN_ARCHIVE', 1);

        reset($this->showcaseObject->fieldSetEnabledFields);

        $entryPost = $this->renderObject->buildEntry($this->showcaseObject->entryData);

        if ($this->showcaseObject->allowComments && $this->showcaseObject->userPermissions[UserPermissions::CanViewComments]) {
            $queryOptions = ['order_by' => 'dateline', 'order_dir' => 'DESC'];

            $queryOptions['limit'] = $this->showcaseObject->commentsPerPageLimit;

            $commentObjects = commentsGet(
                ["entry_id='{$this->showcaseObject->entryID}'", "showcase_id='{$this->showcaseObject->showcase_id}'"],
                ['user_id', 'comment', 'dateline', 'ipaddress', 'status', 'moderator_user_id'],
                $queryOptions
            );

            // start getting comments

            $commentsList = $commentsEmpty = $commentsForm = '';

            $commentsCounter = 0;

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

            $showcase_comment_form_url = $this->showcaseObject->urlBuild(
                $this->showcaseObject->urlMain
            );//.'?action=view&entry_id='.$mybb->get_input('entry_id', \MyBB::INPUT_INT);

            $alternativeBackground = ($alternativeBackground === 'trow1' ? 'trow2' : 'trow1');
            if (!$commentsList) {
                $commentsEmpty = eval($this->renderObject->templateGet('pageViewCommentsNone'));
            }

            //check if logged in for ability to add comments
            $alternativeBackground = ($alternativeBackground === 'trow1' ? 'trow2' : 'trow1');
            if (!$currentUserID) {
                $commentsForm = eval($this->renderObject->templateGet('pageViewCommentsFormGuest'));
            } elseif ($this->showcaseObject->userPermissions[UserPermissions::CanCreateComments]) {
                global $collapsedthead, $collapsedimg, $expaltext, $collapsed;

                isset($collapsedthead) || $collapsedthead = [];

                isset($collapsedimg) || $collapsedimg = [];

                isset($collapsed) || $collapsed = [];

                $collapsedthead['quickreply'] = $collapsedthead['quickreply'] ?? '';

                $collapsedimg['quickreply'] = $collapsedimg['quickreply'] ?? '';

                $collapsed['quickreply_e'] = $collapsed['quickreply_e'] ?? '';

                $commentLengthLimitNote = $lang->sprintf(
                    $lang->myshowcase_comment_text_limit,
                    my_number_format($this->showcaseObject->commentsMaximumLength)
                );

                $alternativeBackground = alt_trow(true);

                $urlCommentCreate = $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlCreateComment,
                    $this->showcaseObject->entryData['entry_slug']
                );

                $commentMessage = htmlspecialchars_uni($mybb->get_input('comment'));

                $commentsForm = eval($this->renderObject->templateGet('pageViewCommentsFormUser'));
            }
        }

        // Update view count
        $db->shutdown_query(
            "UPDATE {$db->table_prefix}{$this->showcaseObject->dataTableName} SET views=views+1 WHERE entry_id='{$this->showcaseObject->entryID}'"
        );

        $plugins->run_hooks('myshowcase_view_end');

        $unpackVariables = [];

        $hookArguments['extractVariables'] = &$unpackVariables;

        $hookArguments = \MyShowcase\Core\hooksRun('entry_view_end', $hookArguments);

        extract($hookArguments['extractVariables']);

        $this->outputSuccess(eval($this->renderObject->templateGet('pageView')));
    }

    #[NoReturn] public function approveEntry(
        string $showcaseSlug,
        string $entrySlug,
        int $status = ENTRY_STATUS_VISIBLE
    ): void {
        global $mybb, $lang, $plugins;

        $this->setEntry($entrySlug);

        if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries] ||
            !$this->showcaseObject->entryID) {
            error_no_permission();
        }

        $dataHandler = dataHandlerGetObject($this->showcaseObject, DATA_HANDLERT_METHOD_UPDATE);

        $dataHandler->setData(['status' => $status]);

        if ($dataHandler->entryValidateData()) {
            $dataHandler->entryUpdate();

            $entryUrl = $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlViewEntry,
                    $this->showcaseObject->entryData['entry_slug']
                ) . '#entryID' . $this->showcaseObject->entryID;

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
        string $showcaseSlug,
        string $entrySlug,
    ): void {
        $this->approveEntry(
            $showcaseSlug,
            $entrySlug,
            ENTRY_STATUS_PENDING_APPROVAL
        );
    }

    #[NoReturn] public function softDeleteEntry(
        string $showcaseSlug,
        string $entrySlug
    ): void {
        $this->approveEntry(
            $showcaseSlug,
            $entrySlug,
            ENTRY_STATUS_SOFT_DELETED
        );
    }

    #[NoReturn] public function restoreEntry(
        string $showcaseSlug,
        string $entrySlug
    ): void {
        $this->approveEntry(
            $showcaseSlug,
            $entrySlug
        );
    }

    #[NoReturn] public function deleteEntry(
        string $showcaseSlug,
        string $entrySlug
    ): void {
        global $mybb, $lang, $plugins;

        $currentUserID = (int)$mybb->user['uid'];

        $this->setEntry($entrySlug);

        if (!$this->showcaseObject->entryID ||
            !(
                $this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries] ||
                ($this->showcaseObject->entryUserID === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteEntries])
            ) || !$this->showcaseObject->entryID) {
            error_no_permission();
        }

        $dataHandler = dataHandlerGetObject($this->showcaseObject);

        $dataHandler->entryDelete();

        $mainUrl = $this->showcaseObject->urlBuild($this->showcaseObject->urlMain);

        redirect($mainUrl, $lang->myShowcaseEntryEntryDeleted);

        exit;
    }
}