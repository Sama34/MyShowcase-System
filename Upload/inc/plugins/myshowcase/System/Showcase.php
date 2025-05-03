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
use function MyShowcase\Core\commentsDelete;
use function MyShowcase\Core\getSetting;
use function MyShowcase\Core\getTemplate;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\Core\postParser;
use function MyShowcase\Core\reportGet;
use function MyShowcase\Core\showcaseDataTableExists;
use function MyShowcase\Core\showcaseDefaultModeratorPermissions;
use function MyShowcase\Core\showcaseDefaultPermissions;

use const MyShowcase\Core\ALL_UNLIMITED_VALUE;
use const MyShowcase\Core\CACHE_TYPE_CONFIG;
use const MyShowcase\Core\CACHE_TYPE_FIELDS;
use const MyShowcase\Core\CACHE_TYPE_MODERATORS;
use const MyShowcase\Core\CACHE_TYPE_PERMISSIONS;
use const MyShowcase\Core\ENTRY_STATUS_UNAPPROVED;
use const MyShowcase\Core\ERROR_TYPE_NOT_CONFIGURED;
use const MyShowcase\Core\ERROR_TYPE_NOT_INSTALLED;
use const MyShowcase\Core\GUEST_GROUP_ID;
use const MyShowcase\Core\REPORT_STATUS_PENDING;

class Showcase
{

    /**
     * Constructor of class.
     *
     * @return Showcase
     */
    public function __construct(
        public string $fileName = THIS_SCRIPT,
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
        #Config
        public int $id = 0,
        public string $name = '',
        public string $description = '',
        public string $mainFile = '',
        public int $fieldSetID = 0,
        public array $fieldSetCache = [],
        public array $fieldSetEnabledFields = [],
        public array $fieldSetParseableFields = [],
        public array $fieldSetFormatableFields = [],
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
        public int $commentsAttachmentsPerRowLimit = 0,
        public bool $displayEmptyFields = false,
        public bool $linkInPosts = false,
        public bool $portalShowRandomAttachmentWidget = false,
        public bool $displaySignatures = false,
    ) {
        global $db, $mybb, $cache;

        $this->friendlyUrlsEnabled = $mybb->settings['seourls'] === 'yes' ||
            ($mybb->settings['seourls'] === 'auto' && isset($_SERVER['SEO_SUPPORT']) && (int)$_SERVER['SEO_SUPPORT'] === 1);

        //make sure plugin is installed and active
        $plugin_cache = $cache->read('plugins');

        //check if the requesting file is in the cache
        foreach (cacheGet(CACHE_TYPE_CONFIG) as $showcase) {
            if ($showcase['mainfile'] === $this->fileName) {
                $this->id = (int)$showcase['id'];

                $this->name = (string)$showcase['name'];

                $this->description = (string)$showcase['description'];

                $this->mainFile = (string)$showcase['mainfile'];

                $this->fieldSetID = (int)$showcase['fieldsetid'];

                $this->imageFolder = (string)$showcase['imgfolder'];

                $this->defaultImage = (string)$showcase['defaultimage'];

                $this->waterMarkImage = (string)$showcase['watermarkimage'];

                $this->waterMarkLocation = (string)$showcase['watermarkloc'];

                $this->userEntryAttachmentAsImage = (bool)$showcase['use_attach'];

                $this->relativePath = (string)$showcase['f2gpath'];

                $this->enabled = (bool)$showcase['enabled'];

                $this->parserAllowSmiles = (bool)$showcase['allowsmilies'];

                $this->parserAllowMyCode = (bool)$showcase['allowbbcode'];

                $this->parserAllowHTML = (bool)$showcase['allowhtml'];

                $this->pruneTime = (int)$showcase['prunetime'];

                $this->moderateEdits = (bool)$showcase['modnewedit'];

                $this->maximumLengthForTextFields = (int)$showcase['othermaxlength'];

                $this->allowAttachments = (bool)$showcase['allow_attachments'];

                $this->allowComments = (bool)$showcase['allow_comments'];

                $this->attachmentThumbWidth = (int)$showcase['thumb_width'];

                $this->attachmentThumbHeight = (int)$showcase['thumb_height'];

                $this->commentsMaximumLength = (int)$showcase['comment_length'];

                $this->commentsPerPageLimit = (int)$showcase['comment_dispinit'];

                $this->commentsAttachmentsPerRowLimit = (int)$showcase['disp_attachcols'];

                $this->displayEmptyFields = (bool)$showcase['disp_empty'];

                $this->linkInPosts = (bool)$showcase['allow_attachments'];

                $this->portalShowRandomAttachmentWidget = (bool)$showcase['portal_random'];

                $this->displaySignatures = (bool)$showcase['display_signatures'];

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
        if (showcaseDataTableExists($this->id)) {
            $this->dataTableName = 'myshowcase_data' . $this->id;
        }

        if (!$this->id || !$this->dataTableName || !$this->fieldSetID) {
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
                if (!$fieldData['enabled']/* || $fieldData['requiredField']*/) {
                    continue;
                }

                $this->fieldSetEnabledFields[$fieldData['name']] = $fieldData['html_type'];

                $this->fieldSetParseableFields[$fieldData['name']] = $fieldData['parse'];

                $this->fieldSetFormatableFields[$fieldData['name']] = $fieldData['format'];
            }
        }

        return $this;
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
            global $cache, $config;

            //require_once MYBB_ROOT . $config['admin_dir'] . '/modules/myshowcase/module_meta.php';
            $defaultShowcasePermissions = showcaseDefaultPermissions();

            $showcaseGroupPermissions = [];

            $groupsCache = (array)$cache->read('usergroups');

            foreach (cacheGet(CACHE_TYPE_PERMISSIONS)[$this->id] as $showcasePermissions) {
                $groupID = (int)$showcasePermissions['gid'];

                $showcaseGroupPermissions[$groupID]['id'] = $groupID;
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
                    $showcaseGroupPermissions[$groupID]['id'] = $groupID;
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

            if (!empty($moderatorsCache[$this->id])) {
                foreach ($moderatorsCache[$this->id] as $moderatorPermissions) {
                    if ($moderatorPermissions['isgroup'] && in_array($moderatorPermissions['uid'], $userGroupsIDs) ||
                        !$moderatorPermissions['isgroup'] && (int)$moderatorPermissions['uid'] === $userID) {
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

    /**
     * delete a showcase entry
     */
    public function entryDelete(int $entryID): bool
    {
        $this->attachmentsDelete($entryID);

        $this->commentsDelete($entryID);

        $this->showcaseDataDelete(["gid='{$entryID}'"]);

        return true;
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
        foreach (attachmentGet(["gid='{$entryID}'", "id='{$this->id}'"]) as $attachmentID => $attachmentData) {
            attachmentDelete(["aid='{$attachmentID}'"]);
        }

        return true;
    }

    /**
     * delete a comment
     */
    public function commentsDelete(int $entryID): void
    {
        commentsDelete(["gid='{$entryID}'", "id='{$this->id}'"]);
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
        $whereClauses[] = "id='{$this->id}'";

        $attachmentObjects = attachmentGet($whereClauses, ['attachname', 'thumbnail', 'visible']);

        $attachmentObjects = hooksRun('remove_attachment_do_delete', $attachmentObjects);

        foreach ($attachmentObjects as $attachmentID => $attachmentData) {
            attachmentDelete(["aid='{$attachmentID}'"]);

            if (file_exists($this->imageFolder . '/' . $attachmentData['attachname'])) {
                unlink($this->imageFolder . '/' . $attachmentData['attachname']);
            }

            if (!empty($attachmentData['thumbnail'])) {
                if (file_exists($this->imageFolder . '/' . $attachmentData['thumbnail'])) {
                    unlink($this->imageFolder . '/' . $attachmentData['thumbnail']);
                }
            }

            $dateDirectory = explode('/', $attachmentData['attachname']);

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

        $entryStatusUnapproved = ENTRY_STATUS_UNAPPROVED;

        $query = $db->simple_select(
            $this->dataTableName,
            'COUNT(gid) AS totalUnapprovedEntries',
            "approved='{$entryStatusUnapproved}'",
            [
                'group_by' => 'gid, approved'
            ]
        );

        return (int)$db->fetch_field($query, 'totalUnapprovedEntries');
    }

    public function entriesGetReportedCount(): int
    {
        $reportStatusPending = REPORT_STATUS_PENDING;

        return (int)(reportGet(
            ["id='{$this->id}'", "status='{$reportStatusPending}'"],
            ['COUNT(rid) AS totalReportedEntries'],
            ['group_by' => 'id, rid, status']
        )['totalReportedEntries'] ?? 0);
    }

    public function templateGet(string $templateName = '', bool $enableHTMLComments = true): string
    {
        return getTemplate($templateName, $enableHTMLComments, $this->id);
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
            implode(',', array_merge(['gid'], $queryFields)),
            implode(' AND ', $whereClauses),
            $queryOptions
        );

        if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
            return (array)$db->fetch_array($query);
        }

        $entriesObjects = [];

        while ($fieldValueData = $db->fetch_array($query)) {
            $entriesObjects[(int)$fieldValueData['gid']] = $fieldValueData;
        }

        return $entriesObjects;
    }

    public function entryDataSet(array $entryData): void
    {
        $this->entryData = $entryData;

        if (isset($this->entryData['gid'])) {
            $this->entryID = (int)$this->entryData['gid'];
        }

        if (isset($this->entryData['uid'])) {
            $this->entryUserID = (int)$this->entryData['uid'];
        }
    }

    public function urlSet(string $showcaseUrl): void
    {
        $this->showcaseUrl = $showcaseUrl;
    }
}