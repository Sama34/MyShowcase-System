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

use function MyShowcase\Core\attachmentDelete;
use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\cacheGet;
use function MyShowcase\Core\commentsGet;
use function MyShowcase\Core\commentsDelete;
use function MyShowcase\Core\entryDataInsert;
use function MyShowcase\Core\getSetting;
use function MyShowcase\Core\getTemplate;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\Core\postParser;
use function MyShowcase\Core\showcaseDataTableExists;
use function MyShowcase\Core\entryDataUpdate;
use function MyShowcase\Core\showcaseDefaultModeratorPermissions;
use function MyShowcase\Core\showcaseDefaultPermissions;

use const MyShowcase\Core\ALL_UNLIMITED_VALUE;
use const MyShowcase\Core\CACHE_TYPE_CONFIG;
use const MyShowcase\Core\CACHE_TYPE_FIELDS;
use const MyShowcase\Core\CACHE_TYPE_MODERATORS;
use const MyShowcase\Core\CACHE_TYPE_PERMISSIONS;
use const MyShowcase\Core\ENTRY_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\ERROR_TYPE_NOT_CONFIGURED;
use const MyShowcase\Core\ERROR_TYPE_NOT_INSTALLED;
use const MyShowcase\Core\GUEST_GROUP_ID;
use const MyShowcase\Core\ORDER_DIRECTION_ASCENDING;
use const MyShowcase\Core\ORDER_DIRECTION_DESCENDING;
use const MyShowcase\Core\REPORT_STATUS_PENDING;

class Showcase
{
    /**
     * Constructor of class.
     *
     * @return Showcase
     */
    public function __construct(
        public string $showcaseSlug,
        public string $dataTableName = '',
        public string $prefix = '',
        public string $cleanName = '',
        public array $userPermissions = [],
        public bool $friendlyUrlsEnabled = false,
        public array $parserOptions = [
            'filter_badwords' => true,
            'highlight' => '',
            'nl2br' => true
        ],
        public int $errorType = 0,
        public string $showcaseUrl = '',
        public array $entryData = [],
        public int $entryID = 0,
        public int $entryUserID = 0,
        public array $fieldSetFieldsDisplayFields = [],
        public string $searchField = '',
        public string $sortByField = '',
        public string $orderBy = '',
        public array $errorMessages = [],
        #Config
        public int $showcase_id = 0,
        public string $name = '',
        public string $nameFriendly = '',
        public string $description = '',
        public string $mainFile = '',
        public int $fieldSetID = 0,
        public array $fieldSetCache = [],
        public array $fieldSetEnabledFields = [],
        public array $fieldSetParseableFields = [],
        public array $fieldSetFormatableFields = [],
        public array $fieldSetSearchableFields = [],
        public array $fieldSetFieldsOrder = [],
        public array $fieldSetFieldsMaximumLenght = [],
        public array $fieldSetFieldsMinimumLenght = [],
        public array $fieldSetFieldsRequired = ['user_id' => 1], // todo, unsure if user_id is necessary
        public string $imageFolder = '',
        public string $defaultImage = '',
        public string $waterMarkImage = '',
        public string $waterMarkLocation = '',
        public bool $userEntryAttachmentAsImage = false,
        public string $relativePath = '',
        public bool $enabled = false,
        public bool $parserAllowSmiles = false,
        public bool $parserAllowMyCode = false,
        public bool $parserAllowHTML = false,
        public int $pruneTime = 0,
        public bool $moderateEdits = false,
        public int $maximumLengthForTextFields = 0,
        public bool $allowAttachments = false,
        public bool $allowComments = false,
        public int $attachmentThumbWidth = 0,
        public int $attachmentThumbHeight = 0,
        public int $commentsMaximumLength = 0,
        public int $commentsPerPageLimit = 0,
        public int $attachmentsPerRowLimit = 0,
        public bool $attachmentsDisplayThumbnails = true,
        public bool $attachmentsDisplayFullSizeImage = false,
        public bool $displayEmptyFields = false,
        public bool $linkInPosts = false,
        public bool $portalShowRandomAttachmentWidget = false,
        public bool $displaySignatures = false,
        public string $maximumAvatarSize = '55x55',
        #urls
        public string $urlBase = '',
        public string $urlMain = '',
        public string $urlPaged = '',
        public string $urlViewEntry = '',
        public string $urlViewComment = '',
        public string $urlCreateEntry = '',
        public string $urlApproveEntry = '',
        public string $urlUnapproveEntry = '',
        public string $urlSoftDeleteEntry = '',
        public string $urlDeleteEntry = '',
        public string $urlCreateComment = '',
        public string $urlApproveComment = '',
        public string $urlUnapproveComment = '',
        public string $urlRestoreComment = '',
        public string $urlSoftDeleteComment = '',
        public string $urlDeleteComment = '',
        public string $urlViewAttachment = '',
        public string $urlViewAttachmentItem = '',
    ) {
        global $db, $mybb, $cache;

        $this->friendlyUrlsEnabled = $mybb->settings['seourls'] === 'yes' ||
            ($mybb->settings['seourls'] === 'auto' && isset($_SERVER['SEO_SUPPORT']) && (int)$_SERVER['SEO_SUPPORT'] === 1);

        //make sure plugin is installed and active
        $plugin_cache = $cache->read('plugins');

        //check if the requesting file is in the cache
        foreach (cacheGet(CACHE_TYPE_CONFIG) as $showcaseData) {
            if ($showcaseData['showcase_slug'] === $this->showcaseSlug) {
                $this->showcase_id = (int)$showcaseData['showcase_id'];

                $this->name = (string)$showcaseData['name'];

                $this->nameFriendly = ucwords(strtolower($this->name));

                $this->description = (string)$showcaseData['description'];

                $this->mainFile = (string)$showcaseData['mainfile'];

                $this->fieldSetID = (int)$showcaseData['field_set_id'];

                $this->imageFolder = (string)$showcaseData['images_directory'];

                $this->defaultImage = (string)$showcaseData['images_directory'];

                $this->waterMarkImage = (string)$showcaseData['water_mark_image'];

                $this->waterMarkLocation = (string)$showcaseData['water_mark_image_location'];

                $this->userEntryAttachmentAsImage = (bool)$showcaseData['use_attach'];

                $this->relativePath = (string)$showcaseData['relative_path'];

                $this->enabled = (bool)$showcaseData['enabled'];

                $this->parserAllowSmiles = (bool)$showcaseData['allow_smilies'];

                $this->parserAllowMyCode = (bool)$showcaseData['allow_mycode'];

                $this->parserAllowHTML = (bool)$showcaseData['allow_html'];

                $this->pruneTime = (int)$showcaseData['prune_time'];

                $this->moderateEdits = (bool)$showcaseData['moderate_edits'];

                $this->maximumLengthForTextFields = (int)$showcaseData['maximum_text_field_lenght'];

                $this->allowAttachments = (bool)$showcaseData['allow_attachments'];

                $this->allowComments = (bool)$showcaseData['allow_comments'];

                $this->attachmentThumbWidth = (int)$showcaseData['thumb_width'];

                $this->attachmentThumbHeight = (int)$showcaseData['thumb_height'];

                $this->commentsMaximumLength = (int)$showcaseData['comment_length'];

                $this->commentsPerPageLimit = (int)$showcaseData['comments_per_page'];

                $this->attachmentsPerRowLimit = (int)$showcaseData['attachments_per_row'];

                $this->displayEmptyFields = (bool)$showcaseData['display_empty_fields'];

                $this->linkInPosts = (bool)$showcaseData['allow_attachments'];

                $this->portalShowRandomAttachmentWidget = (bool)$showcaseData['build_random_entry_widget'];

                $this->displaySignatures = (bool)$showcaseData['display_signatures'];

                break;
            }
        }

        if (!$db->table_exists('myshowcase_config') || !array_key_exists('myshowcase', $plugin_cache['active'])) {
            $this->enabled = false;

            $this->errorType = ERROR_TYPE_NOT_INSTALLED;
        }

        //clean the name and make it suitable for SEO
        //cleaning borrowed from Google SEO plugin
        $pattern = '!"#$%&\'( )*+,-./:;<=>?@[\]^_`{|}~';
        $pattern = preg_replace(
            "/[\\\\\\^\\-\\[\\]\\/]/u",
            "\\\\\\0",
            $pattern
        );

        // Cut off punctuation at beginning and end.
        $this->cleanName = preg_replace(
            "/^[$pattern]+|[$pattern]+$/u",
            '',
            strtolower($this->name)
        );

        // Replace middle punctuation with one separator.
        $this->cleanName = preg_replace(
            "/[$pattern]+/u",
            '-',
            $this->cleanName
        );

        //make sure data table exists and assign table name var if it does
        if (showcaseDataTableExists($this->showcase_id)) {
            $this->dataTableName = 'myshowcase_data' . $this->showcase_id;
        }

        if (!$this->showcase_id || !$this->dataTableName || !$this->fieldSetID) {
            $this->enabled = false;

            $this->errorType = ERROR_TYPE_NOT_CONFIGURED;
        }

        //get basename of the calling file. This is used later for SEO support
        $this->prefix = explode('.', $this->mainFile)[0];

        $currentUserID = (int)$mybb->user['uid'];

        $this->userPermissions = $this->userPermissionsGet($currentUserID);

        $this->parserOptions = array_merge($this->parserOptions, [
            'allow_html' => $this->parserAllowHTML,
            'allow_mycode' => $this->parserAllowMyCode,
            'me_username' => $mybb->user['username'] ?? '',
            'allow_smilies' => $this->parserAllowSmiles
        ]);

        $this->entryUserID = $currentUserID;

        // todo, probably unnecessary since entryDataSet() already sets the entryUserID
        // probably also ignores moderator permissions
        if (!empty($mybb->input['entryUserID'])) {
            $this->entryUserID = $mybb->get_input('entryUserID', MyBB::INPUT_INT);
        }

        if ($this->fieldSetID) {
            $this->fieldSetCache = cacheGet(CACHE_TYPE_FIELDS)[$this->fieldSetID] ?? [];

            foreach ($this->fieldSetCache as $fieldID => $fieldData) {
                if (!$fieldData['enabled']/* || $fieldData['is_required']*/) {
                    continue;
                }

                $this->fieldSetEnabledFields[$fieldData['name']] = $fieldData['html_type'];

                if ($fieldData['parse']) {
                    $this->fieldSetParseableFields[$fieldData['name']] = $fieldData['parse'];
                }

                $this->fieldSetFormatableFields[$fieldData['name']] = $fieldData['format'];

                if ($fieldData['enable_search']) {
                    $this->fieldSetSearchableFields[$fieldData['name']] = $fieldData['html_type'];
                }

                $this->fieldSetFieldsOrder[$fieldData['render_order']] = $fieldData['name'];
                //$this->fieldSetFieldsOrder[$fieldData['display_order']] = $fieldData['name'];

                $this->fieldSetFieldsMaximumLenght[$fieldData['name']] = (int)$fieldData['maximum_length'];

                $this->fieldSetFieldsMinimumLenght[$fieldData['name']] = (int)$fieldData['minimum_length'];

                $this->fieldSetFieldsRequired[$fieldData['name']] = !empty($field['is_required']);
            }

            ksort($this->fieldSetFieldsOrder);
        }

        $this->fieldSetFieldsDisplayFields = [
            'createdate' => '',
            //'edit_stamp' => '',
            'username' => '',
            'views' => '',
            'comments' => ''
        ];

        foreach ($this->fieldSetFieldsOrder as $fieldOrder => $fieldName) {
            $this->fieldSetFieldsDisplayFields[$fieldName] = '';
        }

        $this->searchField = $mybb->get_input('search_field');

        $this->sortByField = $mybb->get_input('sort_by');

        if ($mybb->get_input('order_by') === ORDER_DIRECTION_ASCENDING) {
            $this->orderBy = ORDER_DIRECTION_ASCENDING;
        } elseif ($mybb->get_input('order_by') === ORDER_DIRECTION_DESCENDING) {
            $this->orderBy = ORDER_DIRECTION_DESCENDING;
        }

        if (!array_key_exists($this->sortByField, $this->fieldSetFieldsDisplayFields)) {
            $this->sortByField = 'dateline';
        }

        $this->urlBase = $mybb->settings['bburl'];

        if ($this->friendlyUrlsEnabled) {
            //$showcase_name = strtolower($showcaseObject->name);
            $this->urlMain = $this->cleanName . '.html';

            $this->urlPaged = $this->cleanName . '-page-{page}.html';

            $this->urlViewEntry = $this->cleanName . '-view-{entry_id}.html';

            $this->urlViewComment = $this->cleanName . '-view-{entry_id}-last-comment.html';

            $this->urlCreateEntry = $this->cleanName . '-new.html';

            $this->urlViewAttachment = $this->cleanName . '-attachment-{attachment_id}.html';

            $this->urlViewAttachmentItem = $this->cleanName . '-item-{attachment_id}.php';
        } else {
            $this->urlMain = $this->showcaseSlug;

            $this->urlPaged = $this->prefix . '.php?page={page}';

            $this->urlViewEntry = $this->showcaseSlug . '/view/{entry_id}';

            $this->urlViewComment = $this->showcaseSlug . '/view/{entry_id}/comment/{comment_id}';

            $this->urlCreateEntry = $this->showcaseSlug . '/create';

            $this->urlApproveEntry = $this->showcaseSlug . '/view/{entry_id}/approve';

            $this->urlUnapproveEntry = $this->showcaseSlug . '/view/{entry_id}/unapprove';

            $this->urlRestoreEntry = $this->showcaseSlug . '/view/{entry_id}/restore';

            $this->urlSoftDeleteEntry = $this->showcaseSlug . '/view/{entry_id}/soft_delete';

            $this->urlDeleteEntry = $this->showcaseSlug . '/view/{entry_id}/delete';

            $this->urlCreateComment = $this->showcaseSlug . '/view/{entry_id}/comment/create';

            $this->urlApproveComment = $this->showcaseSlug . '/view/{entry_id}/comment/{comment_id}/approve';

            $this->urlUnapproveComment = $this->showcaseSlug . '/view/{entry_id}/comment/{comment_id}/unapprove';

            $this->urlRestoreComment = $this->showcaseSlug . '/view/{entry_id}/comment/{comment_id}/restore';

            $this->urlSoftDeleteComment = $this->showcaseSlug . '/view/{entry_id}/comment/{comment_id}/soft_delete';

            $this->urlDeleteComment = $this->showcaseSlug . '/view/{entry_id}/comment/{comment_id}/delete';

            $this->urlViewAttachment = $this->showcaseSlug . '/view/{entry_id}/attachment/{attachment_id}';

            $this->urlViewAttachmentThumbnail = $this->showcaseSlug . '/view/{entry_id}/attachment/{thumbnail_id}/thumbnail';

            $this->urlViewAttachmentItem = $this->prefix . '.php?action=item&attachment_id={attachment_id}';
        }

        return $this;
    }

    public function urlBuild(string $url, int $entryID = 0, int $commentID = 0, int $attachmentID = 0): string
    {
        return 'showcase.php?route=/' . str_replace(
                ['{entry_id}', '{comment_id}'],
                [(string)$entryID, (string)$commentID],
                $url
            );
    }

    /**
     * get group permissions for a specific showcase
     *
     * @return array group permissions for the specific showcase
     */
    public function groupPermissionsGet(int $groupID = GUEST_GROUP_ID): array
    {
        static $showcaseGroupPermissions = null;

        if ($showcaseGroupPermissions === null) {
            global $cache;

            //require_once MYBB_ROOT . $config['admin_dir'] . '/modules/myshowcase/module_meta.php';
            $defaultShowcasePermissions = showcaseDefaultPermissions();

            $showcaseGroupPermissions = [];

            $groupsCache = (array)$cache->read('usergroups');

            foreach (cacheGet(CACHE_TYPE_PERMISSIONS)[$this->showcase_id] as $showcasePermissions) {
                $groupID = (int)$showcasePermissions['group_id'];

                $showcaseGroupPermissions[$groupID]['showcase_id'] = $groupID;
                $showcaseGroupPermissions[$groupID]['name'] = $groupsCache[$groupID]['title'] ?? '';
                //$showcaseGroupPermissions[$groupID]['intable'] = 1;

                foreach ($defaultShowcasePermissions as $permissionKey => $permissionValue) {
                    $showcaseGroupPermissions[$groupID][$permissionKey] = $permissionValue;
                    $showcaseGroupPermissions[$groupID][$permissionKey] = $showcasePermissions[$permissionKey];
                }
            }

            //load defaults if group not already in cache (e.g. group added since myshowcase created)
            foreach ($groupsCache as $groupData) {
                $groupID = (int)$groupData['gid'];

                if (!array_key_exists($groupID, $showcaseGroupPermissions)) {
                    $showcaseGroupPermissions[$groupID]['showcase_id'] = $groupID;
                    $showcaseGroupPermissions[$groupID]['name'] = $groupData['title'] ?? '';
                    //$showcaseGroupPermissions[$groupID]['intable'] = 0;

                    foreach ($defaultShowcasePermissions as $permissionKey => $permissionValue) {
                        $showcaseGroupPermissions[$groupID][$permissionKey] = $permissionValue;
                    }
                }
            }
        }

        if ($groupID) {
            return $showcaseGroupPermissions[$groupID] ?? [];
        } else {
            return $showcaseGroupPermissions;
        }
    }

    /**
     * get user permissions for a specific showcase
     *
     * @param int $userID The User identifier for the user to build permissions for
     * @return array user permissions for the specific showcase
     */
    public function userPermissionsGet(int $userID): array
    {
        $userData = get_user($userID);

        $guestGroupPermissions = $this->groupPermissionsGet();

        $userPermissions = [];

        foreach (showcaseDefaultPermissions() as $permissionKey => $permissionValue) {
            $userPermissions[$permissionKey] = $guestGroupPermissions[$permissionKey];
        }

        if (!empty($userData['uid'])) {
            $userGroupsIDs = array_filter(
                array_map(
                    'intval',
                    explode(',', "{$userData['usergroup']},{$userData['additionalgroups']}")
                )
            );

            foreach (array_keys($userPermissions) as $permissionKey) {
                foreach ($userGroupsIDs as $groupID) {
                    $groupPermissions = $this->groupPermissionsGet($groupID);

                    $userPermissions[$permissionKey] = ((int)$groupPermissions[$permissionKey] === ALL_UNLIMITED_VALUE ? -1 :
                        max($userPermissions[$permissionKey], $groupPermissions[$permissionKey]));
                }
            }
        }

        return array_merge($userPermissions, $this->moderatorPermissionsGet($userID));
    }

    public function moderatorPermissionsGet(int $userID): array
    {
        $userData = get_user($userID);

        $userModeratorPermissions = showcaseDefaultModeratorPermissions();

        if (!empty($userData['uid'])) {
            $userGroupsIDs = array_filter(
                array_map(
                    'intval',
                    explode(',', "{$userData['usergroup']},{$userData['additionalgroups']}")
                )
            );

            if (is_member(getSetting('superModeratorGroups'), $userData)) {
                foreach ($userModeratorPermissions as $permissionKey => $permissionValue) {
                    $userModeratorPermissions[$permissionKey] = true;
                }
            }

            //get showcase moderator cache to handle additional mods/modgroups
            $moderatorsCache = cacheGet(CACHE_TYPE_MODERATORS);

            if (!empty($moderatorsCache[$this->showcase_id])) {
                foreach ($moderatorsCache[$this->showcase_id] as $moderatorPermissions) {
                    if ($moderatorPermissions['is_group'] && in_array(
                            $moderatorPermissions['user_id'],
                            $userGroupsIDs
                        ) ||
                        !$moderatorPermissions['is_group'] && (int)$moderatorPermissions['user_id'] === $userID) {
                        foreach ($userModeratorPermissions as $permissionKey => &$permissionValue) {
                            $userModeratorPermissions[$permissionKey] = !empty($moderatorPermissions[$permissionKey]) ||
                                !empty($permissionValue);
                        }
                    }
                }
            }
        }

        return $userModeratorPermissions;
    }

    /**
     * get ids from cookie inline moderation
     */
    public function inlineGetIDs(int $id = ALL_UNLIMITED_VALUE, string $type = 'showcase'): array
    {
        if ($id === ALL_UNLIMITED_VALUE) {
            $id = 'all';
        }

        global $mybb;

        $newIDs = [];

        if (!empty($id)) {
            foreach (explode('|', $mybb->cookies['inlinemod_' . $type . $id]) as $id) {
                $newIDs[] = (int)$id;
            }
        }

        return $newIDs;
    }

    public function showcaseDataDelete(array $whereClauses = []): void
    {
        global $db;

        $db->delete_query($this->dataTableName, implode(' AND ', $whereClauses));
    }

    /**
     * delete attachments from a showcase
     */
    public function attachmentsDelete(int $entryID): bool
    {
        foreach (
            attachmentGet(["entry_id='{$entryID}'", "showcase_id='{$this->showcase_id}'"]
            ) as $attachmentID => $attachmentData
        ) {
            attachmentDelete(["attachment_id='{$attachmentID}'"]);
        }

        return true;
    }

    /**
     * delete a comment
     */
    public function commentsDelete(int $entryID): void
    {
        commentsDelete(["entry_id='{$entryID}'", "showcase_id='{$this->showcase_id}'"]);
    }

    /**
     * clear cookie inline moderation
     */
    public function inlineClear(int $id = ALL_UNLIMITED_VALUE, string $type = 'showcase'): bool
    {
        if ($id === ALL_UNLIMITED_VALUE) {
            $id = 'all';
        }

        my_unsetcookie('inlinemod_' . $type . $id);

        return true;
    }

    /**
     * add to cookie inline moderation
     */
    public function inlineExtend(int $id, string $type): bool
    {
        my_setcookie("inlinemod_$type.$id", '', TIME_NOW + 3600);

        return true;
    }

    public function permissionCheck(string $permissionKey): bool|int
    {
        return $this->userPermissions[$permissionKey];
    }

    public function parseMessage(string $message, array $parserOptions = []): string
    {
        return postParser()->parse_message(
            $message,
            array_merge($this->parserOptions, $parserOptions)
        );
    }

    public function attachmentsRemove(array $whereClauses): void
    {
        $whereClauses[] = "showcase_id='{$this->showcase_id}'";

        $attachmentObjects = attachmentGet($whereClauses, ['attachment_name', 'thumbnail', 'status']);

        $attachmentObjects = hooksRun('remove_attachment_do_delete', $attachmentObjects);

        foreach ($attachmentObjects as $attachmentID => $attachmentData) {
            attachmentDelete(["attachment_id='{$attachmentID}'"]);

            if (file_exists($this->imageFolder . '/' . $attachmentData['attachment_name'])) {
                unlink($this->imageFolder . '/' . $attachmentData['attachment_name']);
            }

            if (!empty($attachmentData['thumbnail'])) {
                if (file_exists($this->imageFolder . '/' . $attachmentData['thumbnail'])) {
                    unlink($this->imageFolder . '/' . $attachmentData['thumbnail']);
                }
            }

            $dateDirectory = explode('/', $attachmentData['attachment_name']);

            if (!empty($dateDirectory[0]) && is_dir($this->imageFolder . '/' . $dateDirectory[0])) {
                rmdir($this->imageFolder . '/' . $dateDirectory[0]);
            }
        }
    }

    public function urlGet(): string
    {
        global $mybb;

        return $mybb->settings['bburl'] . '/' . $this->relativePath . $this->mainFile;
    }

    public function entriesGetUnapprovedCount(): int
    {
        global $db;

        $entryStatusUnapproved = ENTRY_STATUS_PENDING_APPROVAL;

        $query = $db->simple_select(
            $this->dataTableName,
            'COUNT(entry_id) AS totalUnapprovedEntries',
            "approved='{$entryStatusUnapproved}'",
            [
                'group_by' => 'entry_id, approved'
            ]
        );

        return (int)$db->fetch_field($query, 'totalUnapprovedEntries');
    }

    public function templateGet(string $templateName = '', bool $enableHTMLComments = true): string
    {
        return getTemplate($templateName, $enableHTMLComments, $this->showcase_id);
    }

    public function dataGet(
        array $whereClauses,
        array $queryFields = [],
        array $queryOptions = [],
        array $queryTables = []
    ): array {
        global $db;

        $queryTables = array_merge(["{$this->dataTableName} entryData"], $queryTables);

        $query = $db->simple_select(
            implode(" LEFT JOIN {$db->table_prefix}", $queryTables),
            implode(',', array_merge(['entry_id'], $queryFields)),
            implode(' AND ', $whereClauses),
            $queryOptions
        );

        if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
            return (array)$db->fetch_array($query);
        }

        $entriesObjects = [];

        while ($fieldValueData = $db->fetch_array($query)) {
            $entriesObjects[(int)$fieldValueData['entry_id']] = $fieldValueData;
        }

        return $entriesObjects;
    }

    public function dataInsert(
        array $insertData
    ): int {
        return entryDataInsert($this->showcase_id, $insertData);
    }

    public function dataUpdate(
        array $updateData
    ): int {
        return entryDataUpdate(
            $this->showcase_id,
            $this->entryID,
            $updateData
        );
    }

    public function commentGet(
        array $whereClauses,
        array $queryFields = [],
        array $queryOptions = [],
    ): array {
        $whereClauses[] = "showcase_id='{$this->showcase_id}'";

        return commentsGet(
            $whereClauses,
            $queryFields,
            $queryOptions
        );
    }

    public function entryDataSet(array $entryData): void
    {
        $this->entryData = $entryData;

        if (isset($this->entryData['entry_id'])) {
            $this->entryID = (int)$this->entryData['entry_id'];
        }

        if (isset($this->entryData['user_id'])) {
            $this->entryUserID = (int)$this->entryData['user_id'];
        }
    }

    public function urlSet(string $showcaseUrl): void
    {
        $this->showcaseUrl = $showcaseUrl;
    }
}