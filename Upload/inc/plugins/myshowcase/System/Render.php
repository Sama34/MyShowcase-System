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

namespace MyShowcase\System;

use MyBB;

use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\formatField;
use function MyShowcase\Core\getTemplate;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\Core\loadLanguage;
use function MyShowcase\Core\postParser;
use function MyShowcase\Core\templateGetCachedName;
use function MyShowcase\SimpleRouter\url;

use const MyShowcase\Core\ALL_UNLIMITED_VALUE;
use const MyShowcase\Core\ATTACHMENT_THUMBNAIL_SMALL;
use const MyShowcase\Core\DEBUG;
use const MyShowcase\Core\CHECK_BOX_IS_CHECKED;
use const MyShowcase\Core\COMMENT_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\COMMENT_STATUS_SOFT_DELETED;
use const MyShowcase\Core\COMMENT_STATUS_VISIBLE;
use const MyShowcase\Core\ENTRY_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\ENTRY_STATUS_SOFT_DELETED;
use const MyShowcase\Core\ENTRY_STATUS_VISIBLE;
use const MyShowcase\Core\URL_TYPE_ATTACHMENT_VIEW;
use const MyShowcase\Core\URL_TYPE_COMMENT_APPROVE;
use const MyShowcase\Core\URL_TYPE_COMMENT_DELETE;
use const MyShowcase\Core\URL_TYPE_COMMENT_RESTORE;
use const MyShowcase\Core\URL_TYPE_COMMENT_SOFT_DELETE;
use const MyShowcase\Core\URL_TYPE_COMMENT_UNAPPROVE;
use const MyShowcase\Core\URL_TYPE_COMMENT_UPDATE;
use const MyShowcase\Core\URL_TYPE_COMMENT_VIEW;
use const MyShowcase\Core\URL_TYPE_ENTRY_APPROVE;
use const MyShowcase\Core\URL_TYPE_ENTRY_CREATE;
use const MyShowcase\Core\URL_TYPE_ENTRY_DELETE;
use const MyShowcase\Core\URL_TYPE_ENTRY_RESTORE;
use const MyShowcase\Core\URL_TYPE_ENTRY_SOFT_DELETE;
use const MyShowcase\Core\URL_TYPE_ENTRY_UNAPPROVE;
use const MyShowcase\Core\URL_TYPE_ENTRY_UPDATE;
use const MyShowcase\Core\URL_TYPE_ENTRY_VIEW;
use const MyShowcase\Core\URL_TYPE_THUMBNAIL_VIEW;

class Render
{
    public const POST_TYPE_ENTRY = 1;

    public const POST_TYPE_COMMENT = 2;

    public function __construct(
        public Showcase &$showcaseObject,
        public string $highlightTerms = '',
        public string $searchKeyWords = '',
        public int $searchExactMatch = 0,
        public array $parserOptions = [],
        public array $fieldSetFieldsSearchFields = [],
        public array $urlParams = [],
        public int $page = 0,
        public int $pageCurrent = 0,
    ) {
        global $mybb;

        if (isset($mybb->input['highlight'])) {
            $this->highlightTerms = $mybb->get_input('highlight');

            $this->parserOptions['highlight'] = $this->highlightTerms;
        }

        if (isset($mybb->input['keywords'])) {
            $this->searchKeyWords = $mybb->get_input('keywords');
        }

        if (!empty($mybb->input['exact_match'])) {
            $this->searchExactMatch = 1;
        }

        global $lang;

        loadLanguage();

        foreach ($this->showcaseObject->fieldSetFieldsDisplayFields as $fieldKey => &$fieldDisplayName) {
            $fieldDisplayName = $lang->{"myShowcaseMainSort{$fieldKey}"} ?? ucfirst($fieldKey);
        }

        $this->fieldSetFieldsSearchFields = [
            'username' => $lang->myShowcaseMainSortUsername
        ];

        foreach ($this->showcaseObject->fieldSetCache as $fieldID => $fieldData) {
            $fieldKey = $fieldData['field_key'];

            $fieldKeyUpper = ucfirst($fieldKey);

            $this->fieldSetFieldsSearchFields[$fieldKey] = $lang->{"myShowcaseMainSort{$fieldKeyUpper}"} ?? $lang->{"myshowcase_field_{$fieldKey}"} ?? $fieldKeyUpper;
        }

        global $mybb;

        $this->urlParams = [];

        if ($mybb->get_input('unapproved', MyBB::INPUT_INT)) {
            $this->urlParams['unapproved'] = $mybb->get_input('unapproved', MyBB::INPUT_INT);
        }

        if (array_key_exists($this->showcaseObject->sortByField, $this->showcaseObject->fieldSetFieldsDisplayFields)) {
            $this->urlParams['sort_by'] = $this->showcaseObject->sortByField;
        }

        if ($this->searchExactMatch) {
            $this->urlParams['exact_match'] = $this->searchExactMatch;
        }

        if ($this->searchKeyWords) {
            $this->urlParams['keywords'] = $this->searchKeyWords;
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

        $hookArguments = [
            'renderObject' => &$this,
        ];

        $hookArguments = hooksRun('system_render_construct_end', $hookArguments);
    }

    public function templateGet(string $templateName = '', bool $enableHTMLComments = true): string
    {
        return getTemplate(
            $templateName,
            $enableHTMLComments,
            trim($this->showcaseObject->config['custom_theme_template_prefix'])
        );
    }

    public function templateGetCacheStatus(string $templateName): string
    {
        return templateGetCachedName(
            $templateName,
            trim($this->showcaseObject->config['custom_theme_template_prefix'])
        );
    }

    private function buildPost(
        int $commentsCounter,
        array $postData,
        string $alternativeBackground,
        int $postType = self::POST_TYPE_COMMENT,
        bool $isPreview = false,
    ): string {
        global $mybb, $lang, $theme;

        $currentUserID = (int)$mybb->user['uid'];

        static $currentUserIgnoredUsers = null;

        if ($currentUserIgnoredUsers === null) {
            $currentUserIgnoredUsers = [];

            if ($currentUserID > 0 && !empty($mybb->user['ignorelist'])) {
                $currentUserIgnoredUsers = array_flip(explode(',', $mybb->user['ignorelist']));
            }
        }

        $userID = (int)$postData['user_id'];

        $userData = get_user($userID);

        $hookArguments = [
            'this' => &$this,
            'postData' => &$postData,
            'alternativeBackground' => $alternativeBackground,
            'postType' => $postType,
            'userID' => &$userID,
            'userData' => &$userData,
        ];

        $extractVariables = [];

        $hookArguments['extractVariables'] = &$extractVariables;

        $hookArguments = hooksRun('render_build_entry_comment_start', $hookArguments);

        extract($hookArguments['extractVariables']);

        $extractVariables = [];

        $entryID = $this->showcaseObject->entryID;

        if ($postType === self::POST_TYPE_COMMENT) {
            $templatePrefix = 'pageViewCommentsComment';
        } else {
            $templatePrefix = 'pageViewEntry';
        }

        if ($postType === self::POST_TYPE_COMMENT) {
            $commentMessage = $this->showcaseObject->parseMessage($postData['message'], $this->parserOptions);

            $commentID = (int)$postData['comment_id'];

            $commentNumber = my_number_format($commentsCounter);

            $commentUrl = url(
                URL_TYPE_COMMENT_VIEW,
                ['entry_slug' => $this->showcaseObject->entryData['entry_slug'], 'comment_id' => $commentID]
            )->getRelativeUrl();

            $commentUrl = eval($this->templateGet($templatePrefix . 'Url'));
        } else {
            $entryFields = $this->buildEntryFields();

            $commentsNumber = my_number_format($this->showcaseObject->entryData['comments']);

            $entryUrl = url(
                URL_TYPE_ENTRY_VIEW,
                ['entry_slug' => $this->showcaseObject->entryData['entry_slug']]
            )->getRelativeUrl();

            $entryUrl = eval($this->templateGet($templatePrefix . 'Url'));

            $entryAttachments = '';

            if ($this->showcaseObject->config['attachments_allow_entries'] &&
                $this->showcaseObject->userPermissions[UserPermissions::CanViewAttachments]) {
                $entryAttachments = $this->entryBuildAttachments($entryFields, $postType, $commentID ?? 0);
            }

            $entryFields = implode('', $entryFields);
        }

        // todo, this should probably account for additional groups, but I just took it from post bit logic for now
        if (!empty($userData['usergroup'])) {
            $groupPermissions = usergroup_permissions($userData['usergroup']);
        } else {
            $groupPermissions = usergroup_permissions(1);
        }

        if (empty($userData['username'])) {
            $userData['username'] = $lang->guest;
        }

        $userProfileLinkPlain = get_profile_link($userID);

        $userName = htmlspecialchars_uni($userData['username']);

        $userNameFormatted = format_name(
            $userName,
            $userData['usergroup'] ?? 0,
            $userData['displaygroup'] ?? 0
        );

        $userProfileLink = build_profile_link($userNameFormatted, $userID);

        $userAvatar = $userStars = $userGroupImage = $userDetailsReputationLink = $userDetailsWarningLevel = $userSignature = '';

        if ($postType === self::POST_TYPE_ENTRY && $this->showcaseObject->config['display_avatars_entries'] ||
            $postType === self::POST_TYPE_COMMENT && $this->showcaseObject->config['display_avatars_comments']) {
            if (isset($userData['avatar']) && isset($mybb->user['showavatars']) && !empty($mybb->user['showavatars']) || !$currentUserID) {
                $userAvatar = format_avatar(
                    $userData['avatar'],
                    $userData['avatardimensions'],
                    $this->showcaseObject->maximumAvatarSize
                );

                $userAvatarImage = $userAvatar['image'] ?? '';

                $userAvatarWidthHeight = $userAvatar['width_height'] ?? '';

                $userAvatar = eval($this->templateGet($templatePrefix . 'UserAvatar'));
            }
        }

        if (!empty($groupPermissions['usertitle']) && empty($userData['usertitle'])) {
            $userData['usertitle'] = $groupPermissions['usertitle'];
        } elseif (empty($groupPermissions['usertitle']) && ($userTitlesCache = $this->cacheGetUserTitles())) {
            foreach ($userTitlesCache as $postNumber => $titleinfo) {
                if ($userData['postnum'] >= $postNumber) {
                    if (empty($userData['usertitle'])) {
                        $userData['usertitle'] = $titleinfo['title'];
                    }

                    $groupPermissions['stars'] = $titleinfo['stars'];

                    $groupPermissions['starimage'] = $titleinfo['starimage'];

                    break;
                }
            }
        }

        $userTitle = htmlspecialchars_uni($userData['usertitle']);

        if ($postType === self::POST_TYPE_ENTRY && $this->showcaseObject->config['display_stars_entries'] ||
            $postType === self::POST_TYPE_COMMENT && $this->showcaseObject->config['display_stars_comments']) {
            if (!empty($groupPermissions['starimage']) && isset($groupPermissions['stars'])) {
                $groupStarImage = str_replace('{theme}', $theme['imgdir'], $groupPermissions['starimage']);

                for ($i = 0; $i < $groupPermissions['stars']; ++$i) {
                    $userStars .= eval($this->templateGet($templatePrefix . 'UserStar', false));
                }

                $userStars .= '<br />';
            }
        }

        if ($postType === self::POST_TYPE_ENTRY && $this->showcaseObject->config['display_group_image_entries'] ||
            $postType === self::POST_TYPE_COMMENT && $this->showcaseObject->config['display_group_image_comments']) {
            if (!empty($groupPermissions['image'])) {
                $groupImage = str_replace(['{lang}', '{theme}'],
                    [$mybb->user['language'] ?? $mybb->settings['language'], $theme['imgdir']],
                    $groupPermissions['image']);

                $groupTitle = $groupPermissions['title'];

                $userGroupImage = eval($this->templateGet($templatePrefix . 'UserGroupImage'));
            }
        }

        $userOnlineStatus = '';

        if (isset($userData['lastactive'])) {
            if ($userData['lastactive'] > TIME_NOW - $mybb->settings['wolcutoff'] &&
                (empty($userData['invisible']) || !empty($mybb->usergroup['canviewwolinvis'])) &&
                (int)$userData['lastvisit'] !== (int)$userData['lastactive']) {
                $userOnlineStatus = eval($this->templateGet($templatePrefix . 'UserOnlineStatusOnline'));
            } elseif (!empty($userData['away']) && !empty($mybb->settings['allowaway'])) {
                $userOnlineStatus = eval($this->templateGet($templatePrefix . 'UserOnlineStatusAway'));
            } else {
                $userOnlineStatus = eval($this->templateGet($templatePrefix . 'UserOnlineStatusOffline'));
            }
        }

        $buttonEdit = '';

        if ($postType === self::POST_TYPE_ENTRY && $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries]) {
            $editUrl = url(
                URL_TYPE_ENTRY_UPDATE,
                ['entry_slug' => $this->showcaseObject->entryData['entry_slug']]
            )->getRelativeUrl();

            $buttonEdit = eval($this->templateGet($templatePrefix . 'ButtonEdit'));
        } elseif ($postType === self::POST_TYPE_COMMENT && $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageComments]) {
            $editUrl = url(
                URL_TYPE_COMMENT_UPDATE,
                ['entry_slug' => $this->showcaseObject->entryData['entry_slug'], 'comment_id' => $commentID]
            )->getRelativeUrl();

            $buttonEdit = eval($this->templateGet($templatePrefix . 'ButtonEdit'));
        }

        $buttonWarn = '';

        if (!empty($mybb->settings['enablewarningsystem']) &&
            !empty($groupPermissions['canreceivewarnings']) &&
            (!empty($mybb->usergroup['canwarnusers']) || ($currentUserID === $userID && !empty($mybb->settings['canviewownwarning'])))) {
            if ($mybb->settings['maxwarningpoints'] < 1) {
                $mybb->settings['maxwarningpoints'] = 10;
            }

            $warningLevel = round(
                $userData['warningpoints'] / $mybb->settings['maxwarningpoints'] * 100
            );

            if ($warningLevel > 100) {
                $warningLevel = 100;
            }

            $warningLevel = get_colored_warning_level($warningLevel);

            // If we can warn them, it's not the same person, and we're in a PM or a post.
            if (!empty($mybb->usergroup['canwarnusers']) && $userID !== $currentUserID) {
                $buttonWarn = eval($this->templateGet($templatePrefix . 'ButtonWarn'));

                $warningUrl = "warnings.php?uid={$userID}";
            } else {
                $warningUrl = 'usercp.php';
            }

            if ($postType === self::POST_TYPE_ENTRY && $this->showcaseObject->config['display_group_image_entries'] ||
                $postType === self::POST_TYPE_COMMENT && $this->showcaseObject->config['display_group_image_comments']) {
                $userDetailsWarningLevel = eval($this->templateGet($templatePrefix . 'UserWarningLevel'));
            }
        }

        if (($postType === self::POST_TYPE_ENTRY && $this->showcaseObject->config['display_signatures_entries'] ||
                $postType === self::POST_TYPE_COMMENT && $this->showcaseObject->config['display_signatures_comments']) &&
            !empty($userData['username']) &&
            !empty($userData['signature']) &&
            (!$currentUserID || !empty($mybb->user['showsigs'])) &&
            (empty($userData['suspendsignature']) || !empty($userData['suspendsignature']) && !empty($userData['suspendsigtime']) && $userData['suspendsigtime'] < TIME_NOW) &&
            !empty($groupPermissions['canusesig']) &&
            (empty($groupPermissions['canusesigxposts']) || $groupPermissions['canusesigxposts'] > 0 && $userData['postnum'] > $groupPermissions['canusesigxposts']) &&
            !is_member($mybb->settings['hidesignatures'])) {
            $signatureParserOptions = [
                'allow_html' => !empty($mybb->settings['sightml']),
                'allow_mycode' => !empty($mybb->settings['sigmycode']),
                'allow_smilies' => !empty($mybb->settings['sigsmilies']),
                'allow_imgcode' => !empty($mybb->settings['sigimgcode']),
                'me_username' => $userData['username']
            ];

            if ($groupPermissions['signofollow']) {
                $signatureParserOptions['nofollow_on'] = true;
            }

            if ($currentUserID && empty($mybb->user['showimages']) || empty($mybb->settings['guestimages']) && !$currentUserID) {
                $signatureParserOptions['allow_imgcode'] = false;
            }

            $userSignature = $this->showcaseObject->parseMessage(
                $userData['signature'],
                $signatureParserOptions
            );

            $userSignature = eval($this->templateGet($templatePrefix . 'UserSignature'));
        }

        $date = my_date('relative', $postData['dateline']);

        $approvedByMessage = '';

        if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries] &&
            !empty($postData['moderator_user_id'])) {
            $moderatorUserData = get_user($postData['moderator_user_id']);

            $moderatorUserProfileLink = build_profile_link(
                $moderatorUserData['username'],
                $moderatorUserData['uid']
            );

            $approvedByMessage = eval($this->templateGet($templatePrefix . 'ModeratedBy'));
        }

        $buttonEmail = '';

        if (empty($userData['hideemail']) && $userID !== $currentUserID && !empty($mybb->usergroup['cansendemail'])) {
            $buttonEmail = eval($this->templateGet($templatePrefix . 'ButtonEmail'));
        }

        $buttonPrivateMessage = '';

        if (!empty($mybb->settings['enablepms']) &&
            $userID !== $currentUserID &&
            ((!empty($userData['receivepms']) &&
                    !empty($groupPermissions['canusepms']) &&
                    !empty($mybb->usergroup['cansendpms']) &&
                    my_strpos(
                        ',' . $userData['ignorelist'] . ',',
                        ',' . $currentUserID . ','
                    ) === false) ||
                !empty($mybb->usergroup['canoverridepm']))) {
            $buttonPrivateMessage = eval(
            $this->templateGet(
                $templatePrefix . 'ButtonPrivateMessage'
            )
            );
        }

        $buttonWebsite = '';

        if (!empty($userData['website']) &&
            !is_member($mybb->settings['hidewebsite']) &&
            !empty($groupPermissions['canchangewebsite'])) {
            $userWebsite = htmlspecialchars_uni($userData['website']);

            $buttonWebsite = eval($this->templateGet($templatePrefix . 'ButtonWebsite'));
        }

        $buttonPurgeSpammer = '';

        if ($userID && purgespammer_show($userData['postnum'], $userData['usergroup'], $userID)) {
            $buttonPurgeSpammer = eval($this->templateGet($templatePrefix . 'ButtonPurgeSpammer'));
        }

        global $db;

        $query = $db->simple_select(
            'reportedcontent',
            'uid'
        );

        $reportUserIDs = [];

        while ($reportData = $db->fetch_array($query)) {
            $reportUserIDs[] = (int)$reportData['uid'];
        }

        $userPermissions = user_permissions($userID);

        $buttonReport = '';

        if ($postType === self::POST_TYPE_ENTRY) {
            if (!in_array($currentUserID, $reportUserIDs) && !empty($userPermissions['canbereported'])) {
                $buttonReport = eval($this->templateGet($templatePrefix . 'ButtonReport'));
            }
        }

        $postStatus = (int)$postData['status'];

        $buttonApprove = $buttonUnpprove = $buttonRestore = $buttonSoftDelete = $buttonDelete = '';

        if ($postType === self::POST_TYPE_ENTRY &&
            $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries]) {
            if ($postStatus === ENTRY_STATUS_PENDING_APPROVAL) {
                $approveUrl = url(
                    URL_TYPE_ENTRY_APPROVE,
                    ['entry_slug' => $this->showcaseObject->entryData['entry_slug']]
                )->getRelativeUrl();

                $buttonApprove = eval($this->templateGet($templatePrefix . 'ButtonApprove'));
            } elseif ($postStatus === ENTRY_STATUS_VISIBLE) {
                $unapproveUrl = url(
                    URL_TYPE_ENTRY_UNAPPROVE,
                    ['entry_slug' => $this->showcaseObject->entryData['entry_slug']]
                )->getRelativeUrl();

                $buttonUnpprove = eval($this->templateGet($templatePrefix . 'ButtonUnapprove'));
            }

            if ($postStatus === ENTRY_STATUS_SOFT_DELETED) {
                $restoreUrl = url(
                    URL_TYPE_ENTRY_RESTORE,
                    ['entry_slug' => $this->showcaseObject->entryData['entry_slug']]
                )->getRelativeUrl();

                $buttonRestore = eval($this->templateGet($templatePrefix . 'ButtonRestore'));
            } elseif ($postStatus === ENTRY_STATUS_VISIBLE) {
                $softDeleteUrl = url(
                    URL_TYPE_ENTRY_SOFT_DELETE,
                    ['entry_slug' => $this->showcaseObject->entryData['entry_slug']]
                )->getRelativeUrl();

                $buttonSoftDelete = eval($this->templateGet($templatePrefix . 'ButtonSoftDelete'));
            }

            //only mods, original author (if allowed) or owner (if allowed) can delete comments
            if (
                $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries] ||
                ($userID === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteEntries])
            ) {
                $deleteUrl = url(
                    URL_TYPE_ENTRY_DELETE,
                    ['entry_slug' => $this->showcaseObject->entryData['entry_slug']]
                )->getRelativeUrl();

                $buttonDelete = eval($this->templateGet($templatePrefix . 'ButtonDelete'));
            }
        }

        if ($postType === self::POST_TYPE_COMMENT &&
            $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageComments]) {
            if ($postStatus === COMMENT_STATUS_PENDING_APPROVAL) {
                $approveUrl = url(
                    URL_TYPE_COMMENT_APPROVE,
                    ['entry_slug' => $this->showcaseObject->entryData['entry_slug'], 'comment_id' => $commentID]
                )->getRelativeUrl();

                $buttonApprove = eval($this->templateGet($templatePrefix . 'ButtonApprove'));
            } elseif ($postStatus === COMMENT_STATUS_VISIBLE) {
                $unapproveUrl = url(
                    URL_TYPE_COMMENT_UNAPPROVE,
                    ['entry_slug' => $this->showcaseObject->entryData['entry_slug'], 'comment_id' => $commentID]
                )->getRelativeUrl();

                $buttonUnpprove = eval($this->templateGet($templatePrefix . 'ButtonUnapprove'));
            }

            if ($postStatus === COMMENT_STATUS_SOFT_DELETED) {
                $restoreUrl = url(
                    URL_TYPE_COMMENT_RESTORE,
                    ['entry_slug' => $this->showcaseObject->entryData['entry_slug'], 'comment_id' => $commentID]
                )->getRelativeUrl();

                $buttonRestore = eval($this->templateGet($templatePrefix . 'ButtonRestore'));
            } elseif ($postStatus === COMMENT_STATUS_VISIBLE) {
                $softDeleteUrl = url(
                    URL_TYPE_COMMENT_SOFT_DELETE,
                    ['entry_slug' => $this->showcaseObject->entryData['entry_slug'], 'comment_id' => $commentID]
                )->getRelativeUrl();

                $buttonSoftDelete = eval($this->templateGet($templatePrefix . 'ButtonSoftDelete'));
            }

            //only mods, original author (if allowed) or owner (if allowed) can delete comments
            if (
                $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageComments] ||
                ($userID === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteComments])/* ||
                ((int)$this->showcaseObject->entryData['user_id'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteAuthorComments])*/
            ) {
                $deleteUrl = url(
                    URL_TYPE_COMMENT_DELETE,
                    ['entry_slug' => $this->showcaseObject->entryData['entry_slug'], 'comment_id' => $commentID]
                )->getRelativeUrl();

                $buttonDelete = eval($this->templateGet($templatePrefix . 'ButtonDelete'));
            }
        }

        $hookArguments['extractVariables'] = &$extractVariables;

        $hookArguments = hooksRun('render_build_entry_comment_end', $hookArguments);

        extract($hookArguments['extractVariables']);

        $userDetails = '';

        if ($postType === self::POST_TYPE_ENTRY && $this->showcaseObject->config['display_user_details_entries'] ||
            $postType === self::POST_TYPE_COMMENT && $this->showcaseObject->config['display_user_details_comments']) {
            $userPostNumber = my_number_format($userData['postnum'] ?? 0);

            $userThreadNumber = my_number_format($userData['threadnum'] ?? 0);

            $userRegistrationDate = my_date($mybb->settings['regdateformat'], $userData['regdate'] ?? 0);

            if (!empty($groupPermissions['usereputationsystem']) && !empty($mybb->settings['enablereputation'])) {
                $userReputation = get_reputation($userData['reputation'], $userID);

                $userDetailsReputationLink = eval($this->templateGet($templatePrefix . 'UserReputation'));
            }

            if ($userID) {
                $userDetails = eval($this->templateGet($templatePrefix . 'UserDetails'));
            }
        }

        $styleClass = '';

        switch ($postStatus) {
            case COMMENT_STATUS_PENDING_APPROVAL:
                $styleClass = 'unapproved_post';
                break;
            case COMMENT_STATUS_SOFT_DELETED:
                $styleClass = 'unapproved_post deleted_post';
                break;
        }

        $deletedBit = $ignoredBit = $postVisibility = '';

        if (!$isPreview && (
                self::POST_TYPE_ENTRY && $postStatus === ENTRY_STATUS_SOFT_DELETED ||
                self::POST_TYPE_COMMENT && $postStatus === COMMENT_STATUS_SOFT_DELETED
            )) {
            if (self::POST_TYPE_ENTRY) {
                $deletedMessage = $lang->sprintf($lang->myShowcaseEntryDeletedMessage, $userName);
            } else {
                $deletedMessage = $lang->sprintf($lang->myShowcaseEntryCommentDeletedMessage, $userName);
            }

            $deletedBit = eval($this->templateGet($templatePrefix . 'DeletedBit'));

            $postVisibility = 'display: none;';
        }

        // Is the user (not moderator) logged in and have unapproved posts?
        if (!$isPreview && ($currentUserID &&
                (
                    $postType === self::POST_TYPE_ENTRY && $postStatus === ENTRY_STATUS_PENDING_APPROVAL ||
                    $postType === self::POST_TYPE_COMMENT && $postStatus === COMMENT_STATUS_PENDING_APPROVAL
                ) &&
                $userID === $currentUserID &&
                !(
                    $postType === self::POST_TYPE_ENTRY && $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageEntries] ||
                    $postType === self::POST_TYPE_COMMENT && $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageComments]
                ))) {
            $ignoredMessage = $lang->sprintf($lang->postbit_post_under_moderation, $userName);

            $ignoredBit = eval($this->templateGet($templatePrefix . 'IgnoredBit'));

            $postVisibility = 'display: none;';
        }

        // Is this author on the ignore list of the current user? Hide this post
        if (is_array($currentUserIgnoredUsers) &&
            $userID &&
            isset($currentUserIgnoredUsers[$userID]) &&
            empty($deletedBit)) {
            $ignoredMessage = $lang->sprintf(
                $lang->myShowcaseEntryIgnoredUserMessage,
                $userName,
                $mybb->settings['bburl']
            );

            $ignoredBit = eval($this->templateGet($templatePrefix . 'IgnoredBit'));

            $postVisibility = 'display: none;';
        }

        return eval($this->templateGet($templatePrefix));
    }

    public function buildEntry(array $entryData, bool $isPreview = false): string
    {
        return $this->buildPost(0, [
            'user_id' => $this->showcaseObject->entryUserID,
            'dateline' => $this->showcaseObject->entryData['dateline'],
            //'ipaddress' => $this->showcaseObject->entryData['ipaddress'],
            'status' => $this->showcaseObject->entryData['status'],
            'moderator_user_id' => $this->showcaseObject->entryData['approved_by'],
        ], alt_trow(true), self::POST_TYPE_ENTRY, $isPreview);
    }

    public function buildComment(int $commentsCounter, array $commentData, string $alternativeBackground): string
    {
        return $this->buildPost($commentsCounter, [
            'comment_id' => $commentData['comment_id'],
            'user_id' => $commentData['user_id'],
            'message' => $commentData['comment'],
            'dateline' => $commentData['dateline'],
            'ipaddress' => $commentData['ipaddress'],
            'status' => $commentData['status'],
            'moderator_user_id' => $commentData['moderator_user_id'],
        ], $alternativeBackground);
    }

    public function buildEntryFields(): array
    {
        global $mybb, $lang;

        $entryFieldsList = [];

        foreach ($this->showcaseObject->fieldSetCache as $fieldID => $fieldData) {
            $fieldKey = $fieldData['field_key'];

            $htmlType = $fieldData['html_type'];

            $fieldHeader = $lang->{'myshowcase_field_' . $fieldKey} ?? $fieldKey;

            //set parser options for current field

            $entryFieldValue = $this->showcaseObject->entryData[$fieldKey] ?? '';

            switch ($htmlType) {
                case FieldHtmlTypes::TextArea:
                    if (!empty($entryFieldValue) || $this->showcaseObject->config['display_empty_fields']) {
                        if (empty($entryFieldValue)) {
                            $entryFieldValue = $lang->myShowcaseEntryFieldValueEmpty;
                        } elseif ($fieldData['parse'] || $this->highlightTerms) {
                            $entryFieldValue = $this->showcaseObject->parseMessage(
                                $entryFieldValue,
                                $this->parserOptions,
                            );
                        } else {
                            $entryFieldValue = htmlspecialchars_uni($entryFieldValue);

                            $entryFieldValue = nl2br($entryFieldValue);
                        }

                        $entryFieldsList[$fieldKey] = eval($this->templateGet('pageViewDataFieldTextArea'));
                    }

                    break;
                case FieldHtmlTypes::Text:
                    //format values as requested
                    formatField((int)$fieldData['format'], $entryFieldValue);

                    if (!empty($entryFieldValue) || $this->showcaseObject->config['display_empty_fields']) {
                        if (empty($entryFieldValue)) {
                            $entryFieldValue = $lang->myShowcaseEntryFieldValueEmpty;
                        } elseif ($fieldData['parse'] || $this->highlightTerms) {
                            $entryFieldValue = $this->showcaseObject->parseMessage(
                                $entryFieldValue,
                                $this->parserOptions
                            );
                        } else {
                            $entryFieldValue = htmlspecialchars_uni($entryFieldValue);
                        }

                        $entryFieldsList[$fieldKey] = eval($this->templateGet('pageViewDataFieldTextBox'));
                    }
                    break;
                case FieldHtmlTypes::Url:
                    if (!empty($entryFieldValue) || $this->showcaseObject->config['display_empty_fields']) {
                        if (empty($entryFieldValue)) {
                            $entryFieldValue = $lang->myShowcaseEntryFieldValueEmpty;
                        } elseif ($fieldData['parse']) {
                            $entryFieldValue = postParser()->mycode_parse_url(
                                $entryFieldValue
                            );
                        } else {
                            $entryFieldValue = htmlspecialchars_uni($entryFieldValue);
                        }

                        $entryFieldsList[$fieldKey] = eval($this->templateGet('pageViewDataFieldUrl'));
                    }
                    break;
                case FieldHtmlTypes::Radio:
                    if (!empty($entryFieldValue) || $this->showcaseObject->config['display_empty_fields']) {
                        if (empty($entryFieldValue)) {
                            $entryFieldValue = $lang->myShowcaseEntryFieldValueEmpty;
                        } else {
                            $entryFieldValue = htmlspecialchars_uni($entryFieldValue);
                        }

                        $entryFieldsList[$fieldKey] = eval($this->templateGet('pageViewDataFieldRadio'));
                    }
                    break;
                case FieldHtmlTypes::CheckBox:
                    if (!empty($entryFieldValue) || $this->showcaseObject->config['display_empty_fields']) {
                        if (empty($entryFieldValue)) {
                            $entryFieldValue = $entryFieldValueImage = $lang->myShowcaseEntryFieldValueEmpty;
                        } else {
                            if ((int)$entryFieldValue === CHECK_BOX_IS_CHECKED) {
                                $imageName = 'valid';

                                $imageAlternativeText = $lang->myShowcaseEntryFieldValueCheckBoxYes;
                            } else {
                                $imageName = 'invalid';

                                $imageAlternativeText = $lang->myShowcaseEntryFieldValueCheckBoxNo;
                            }

                            $entryFieldValueImage = eval($this->templateGet('pageViewDataFieldCheckBoxImage'));
                        }

                        $entryFieldsList[$fieldKey] = eval($this->templateGet('pageViewDataFieldCheckBox'));
                    }
                    break;
                case FieldHtmlTypes::SelectSingle:
                    if (!empty($entryFieldValue) || $this->showcaseObject->config['display_empty_fields']) {
                        if (empty($entryFieldValue)) {
                            $entryFieldValue = $lang->myShowcaseEntryFieldValueEmpty;
                        } elseif ($fieldData['parse'] || $this->highlightTerms) {
                            $entryFieldValue = $this->showcaseObject->parseMessage(
                                $entryFieldValue,
                                $this->parserOptions
                            );
                        } else {
                            $entryFieldValue = htmlspecialchars_uni($entryFieldValue);
                        }

                        $entryFieldsList[$fieldKey] = eval($this->templateGet('pageViewDataFieldDataBase'));
                    }
                    break;
                case FieldHtmlTypes::Date:
                    if (!empty($entryFieldValue) || $this->showcaseObject->config['display_empty_fields']) {
                        if (empty($entryFieldValue)) {
                            $entryFieldValue = $lang->myShowcaseEntryFieldValueEmpty;
                        } else {
                            $entryFieldValue = '';

                            list($month, $day, $year) = array_pad(
                                array_map('intval', explode('|', $entryFieldValue)),
                                3,
                                0
                            );

                            if ($month > 0 && $day > 0 && $year > 0) {
                                $entryFieldValue = my_date(
                                    $mybb->settings['dateformat'],
                                    mktime(0, 0, 0, $month, $day, $year)
                                );
                            } else {
                                if ($month) {
                                    $entryFieldValue .= $month;
                                }

                                if ($day) {
                                    $entryFieldValue .= ($entryFieldValue ? '-' : '') . $day;
                                }

                                if ($year) {
                                    $entryFieldValue .= ($entryFieldValue ? '-' : '') . $year;
                                }
                            }
                        }

                        $entryFieldsList[$fieldKey] = eval(getTemplate('pageViewDataFieldDate'));
                    }

                    break;
            }
        }

        return $entryFieldsList;
    }

    public function entryBuildAttachments(
        array &$entryFields,
        int $postType = self::POST_TYPE_ENTRY,
        int $commentID = 0
    ): string {
        $attachmentObjects = attachmentGet(
            ["entry_id='{$this->showcaseObject->entryID}'", "showcase_id='{$this->showcaseObject->showcase_id}'"],
            [
                'status',
                'attachment_name',
                'file_size',
                'downloads',
                'dateline',
                'thumbnail_name',
                'thumbnail_dimensions',
                'attachment_hash'
            ]
        );

        if (!$attachmentObjects) {
            return '';
        }

        $entryID = $this->showcaseObject->entryID;

        global $mybb, $theme, $templates, $lang;

        $unapprovedCount = 0;

        $thumbnailsCount = 0;

        $attachedFiles = $attachedThumbnails = $attachedImages = '';

        foreach ($attachmentObjects as $attachmentID => $attachmentData) {
            $attachmentUrl = url(
                URL_TYPE_ATTACHMENT_VIEW,
                [
                    'entry_slug' => $this->showcaseObject->entryData['entry_slug'],
                    'attachment_hash' => $attachmentData['attachment_hash']
                ]
            )->getRelativeUrl();

            if (empty($attachmentData['thumbnail_name'])) {
                $attachmentThumbnailUrl = $attachmentUrl;
            } else {
                $attachmentThumbnailUrl = url(
                    URL_TYPE_THUMBNAIL_VIEW,
                    [
                        'entry_slug' => $this->showcaseObject->entryData['entry_slug'],
                        'attachment_hash' => $attachmentData['attachment_hash']
                    ]
                )->getRelativeUrl();
            }

            if ($attachmentData['status']) { // There is an attachment thats status!
                $attachmentFileName = htmlspecialchars_uni($attachmentData['attachment_name']);

                $attachmentFileSize = get_friendly_size($attachmentData['file_size']);

                $attachmentExtension = get_extension($attachmentData['attachment_name']);

                $isImageAttachment = in_array($attachmentExtension, ['jpeg', 'gif', 'bmp', 'png', 'jpg']);

                $attachmentIcon = get_attachment_icon($attachmentExtension);

                $attachmentDownloads = my_number_format($attachmentData['downloads']);

                if (!$attachmentData['dateline']) {
                    $attachmentData['dateline'] = $this->showcaseObject->entryData['dateline'];
                }

                $attachmentDate = my_date('normal', $attachmentData['dateline']);

                // Support for [attachment=showcase_id] code
                $attachmentInField = false;

                foreach ($entryFields as $fieldKey => &$fieldValue) {
                    if (str_contains($fieldValue, '[attachment=' . $attachmentID . ']') !== false) {
                        $attachmentInField = true;

                        // Show as thumbnail IF image is big && thumbnail exists && setting=='thumb'
                        // Show as full size image IF setting=='fullsize' || (image is small && permissions allow)
                        // Show as download for all other cases
                        if ((int)$attachmentData['thumbnail_dimensions'] !== ATTACHMENT_THUMBNAIL_SMALL &&
                            $attachmentData['thumbnail_name'] !== '' &&
                            $this->showcaseObject->attachmentsDisplayThumbnails) {
                            $attachmentBit = eval($this->templateGet('pageViewEntryAttachmentsThumbnailsItem'));
                        } elseif ((((int)$attachmentData['thumbnail_dimensions'] === ATTACHMENT_THUMBNAIL_SMALL &&
                                    $this->showcaseObject->userPermissions[UserPermissions::CanDownloadAttachments]) ||
                                $this->showcaseObject->attachmentsDisplayFullSizeImage) &&
                            $isImageAttachment) {
                            $attachmentBit = eval($this->templateGet('pageViewEntryAttachmentsImagesItem'));
                        } else {
                            $attachmentBit = eval($this->templateGet('pageViewEntryAttachmentsFilesItem'));
                        }

                        $fieldValue = preg_replace(
                            '#\[attachment=' . $attachmentID . ']#si',
                            $attachmentBit,
                            $fieldValue
                        );
                    }
                }

                if (!$attachmentInField &&
                    (int)$attachmentData['thumbnail_dimensions'] !== ATTACHMENT_THUMBNAIL_SMALL &&
                    $attachmentData['thumbnail_name'] !== '' &&
                    $this->showcaseObject->attachmentsDisplayThumbnails) {
                    // Show as thumbnail IF image is big && thumbnail exists && setting=='thumb'
                    // Show as full size image IF setting=='fullsize' || (image is small && permissions allow)
                    // Show as download for all other cases
                    $attachedThumbnails .= eval($this->templateGet('pageViewEntryAttachmentsThumbnailsItem'));

                    if ($thumbnailsCount === $this->showcaseObject->config['attachments_grouping']) {
                        $attachedThumbnails .= '<br />';

                        $thumbnailsCount = 0;
                    }

                    ++$thumbnailsCount;
                } elseif (!$attachmentInField && (((int)$attachmentData['thumbnail_dimensions'] === ATTACHMENT_THUMBNAIL_SMALL &&
                            $this->showcaseObject->userPermissions[UserPermissions::CanDownloadAttachments]) ||
                        $this->showcaseObject->attachmentsDisplayFullSizeImage) &&
                    $isImageAttachment) {
                    if ($this->showcaseObject->userPermissions[UserPermissions::CanDownloadAttachments]) {
                        $attachedImages .= eval($this->templateGet('pageViewEntryAttachmentsImagesItem'));
                    } else {
                        $attachedThumbnails .= eval($this->templateGet('pageViewEntryAttachmentsThumbnailsItem'));

                        if ($thumbnailsCount === $this->showcaseObject->config['attachments_grouping']) {
                            $attachedThumbnails .= '<br />';

                            $thumbnailsCount = 0;
                        }

                        ++$thumbnailsCount;
                    }
                } elseif (!$attachmentInField) {
                    $attachedFiles .= eval($this->templateGet('pageViewEntryAttachmentsFilesItem'));
                }
            } else {
                ++$unapprovedCount;
            }
        }

        if ($unapprovedCount > 0 && $this->showcaseObject->userPermissions[ModeratorPermissions::CanManageAttachments]) {
            if ($unapprovedCount === 1) {
                $unapprovedMessage = $lang->postbit_unapproved_attachment;
            } else {
                $unapprovedMessage = $lang->sprintf(
                    $lang->postbit_unapproved_attachments,
                    $unapprovedCount
                );
            }

            $attachedFiles .= eval($this->templateGet('pageViewEntryAttachmentsFilesItemUnapproved'));
        }

        if ($attachedFiles) {
            $attachedFiles = eval($this->templateGet('pageViewEntryAttachmentsFiles'));
        }

        if ($attachedThumbnails) {
            $attachedThumbnails = eval($this->templateGet('pageViewEntryAttachmentsThumbnails'));
        }

        if ($attachedImages) {
            $attachedImages = eval($this->templateGet('pageViewEntryAttachmentsImages'));
        }

        if ($attachedThumbnails || $attachedImages || $attachedFiles) {
            return eval($this->templateGet('pageViewEntryAttachments'));
        }

        return '';
    }

    public function cacheGetUserTitles(): array
    {
        static $titlesCache = null;

        if ($titlesCache === null) {
            global $cache;

            $titlesCache = [];

            foreach ((array)$cache->read('usertitles') as $userTitle) {
                if (!empty($userTitle)) {
                    $titlesCache[(int)$userTitle['posts']] = $userTitle;
                }
            }
        }

        return $titlesCache;
    }

    public function buildAttachmentsUpload(bool $isEditPage): string
    {
        global $mybb, $lang, $theme;

        if (DEBUG) {
            $version = TIME_NOW;
        }

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

        $watermarkSelectedElement = '';

        if ($mybb->get_input('attachment_watermark_file')) {
            $watermarkSelectedElement = 'checked="checked"';
        }

        $currentUserID = (int)$mybb->user['uid'];

        if ($this->showcaseObject->userPermissions['attachments_upload_quote'] === ALL_UNLIMITED_VALUE) {
            $usageQuoteNote = $lang->sprintf(
                $lang->myShowcaseAttachmentsUsageQuote,
                $lang->myShowcaseAttachmentsUsageQuoteUnlimited
            );
        } else {
            $usageQuoteNote = $lang->sprintf(
                $lang->myShowcaseAttachmentsUsageQuote,
                get_friendly_size($this->showcaseObject->userPermissions['attachments_upload_quote'] * 1024)
            );
        }

        $totalUserUsage = (int)(attachmentGet(
            ["showcase_id='{$this->showcaseObject->showcase_id}'", "user_id='{$currentUserID}'"],
            ['SUM(file_size) AS total_user_usage'],
            ['limit' => 1]
        )['total_user_usage'] ?? 0);

        $usageDetails = $viewMyAttachmentsLink = '';

        if ($totalUserUsage > 0) {
            $usageDetails = $lang->sprintf(
                $lang->myShowcaseAttachmentsUsageDetails,
                get_friendly_size($totalUserUsage)
            );

            $viewMyAttachmentsLink = eval($this->templateGet('pageEntryCommentCreateUpdateAttachmentsBoxViewLink'));
        }

        return eval($this->templateGet('pageEntryCommentCreateUpdateAttachmentsBox'));
    }

    public function buildEntrySubject(): string
    {
        global $mybb, $lang;

        $entrySubject = [];

        foreach ($this->showcaseObject->fieldSetCache as $fieldID => $fieldData) {
            if (!$fieldData['enable_subject']) {
                continue;
            }

            $fieldKey = $fieldData['field_key'];

            $htmlType = $fieldData['html_type'];

            $entryFieldText = $this->showcaseObject->entryData[$fieldKey] ?? '';

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

        return $entrySubject;
    }

    public function buildCommentsFormEditor(string &$code_buttons, string &$smile_inserter): void
    {
        if ($this->showcaseObject->config['comments_build_editor']) {
            $this->buildEditor($code_buttons, $smile_inserter);
        }
    }

    public function buildEditor(string &$code_buttons, string &$smile_inserter, string $editorID = 'comment'): void
    {
        global $mybb;

        $currentUserID = (int)$mybb->user['uid'];

        if (!empty($mybb->settings['bbcodeinserter']) &&
            $this->showcaseObject->config['parser_allow_mycode'] &&
            (!$currentUserID || !empty($mybb->user['showcodebuttons']))) {
            $code_buttons = build_mycode_inserter(
                $editorID,
                $this->showcaseObject->config['parser_allow_smiles']
            );

            if ($this->showcaseObject->config['parser_allow_smiles']) {
                $smile_inserter = build_clickable_smilies();
            }
        }
    }

}