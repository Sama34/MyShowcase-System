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

use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\formatField;
use function MyShowcase\Core\getTemplate;
use function MyShowcase\Core\loadLanguage;
use function MyShowcase\Core\postParser;

use const MyShowcase\Core\CHECK_BOX_IS_CHECKED;
use const MyShowcase\Core\COMMENT_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\COMMENT_STATUS_SOFT_DELETED;
use const MyShowcase\Core\COMMENT_STATUS_VISIBLE;
use const MyShowcase\Core\ENTRY_STATUS_PENDING_APPROVAL;
use const MyShowcase\Core\ENTRY_STATUS_SOFT_DELETED;
use const MyShowcase\Core\ENTRY_STATUS_VISIBLE;

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
    }

    public function templateGet(string $templateName = '', bool $enableHTMLComments = true): string
    {
        return getTemplate($templateName, $enableHTMLComments, $this->showcaseObject->showcase_id);
    }

    private function buildPost(
        int $commentsCounter,
        array $postData,
        string $alternativeBackground,
        int $postType = self::POST_TYPE_COMMENT
    ): string {
        global $mybb, $lang, $theme;

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

            $commentUrl = $this->showcaseObject->urlBuild(
                $this->showcaseObject->urlViewComment,
                $this->showcaseObject->entryData['entry_slug'],
                $commentID
            );

            $commentUrl = eval($this->templateGet($templatePrefix . 'Url'));
        } else {
            $entryFields = $this->buildEntryFields();

            $commentsNumber = my_number_format($this->showcaseObject->entryData['comments']);

            $entryUrl = $this->showcaseObject->urlBuild(
                $this->showcaseObject->urlViewEntry,
                $this->showcaseObject->entryData['entry_slug']
            );

            $entryUrl = eval($this->templateGet($templatePrefix . 'Url'));

            $entryAttachments = $this->entryBuildAttachments($entryFields);

            $entryFields = implode('', $entryFields);
        }

        $currentUserID = (int)$mybb->user['uid'];

        $userID = (int)$postData['user_id'];

        $userData = get_user($userID);

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

        $commentUserName = htmlspecialchars_uni($userData['username']);

        $commentUserNameFormatted = format_name(
            $commentUserName,
            $userData['usergroup'] ?? 0,
            $userData['displaygroup'] ?? 0
        );

        $userProfileLink = build_profile_link($commentUserNameFormatted, $userID);

        $userAvatar = '';

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

        $userStars = '';

        if (!empty($groupPermissions['starimage']) && isset($groupPermissions['stars'])) {
            $groupStarImage = str_replace('{theme}', $theme['imgdir'], $groupPermissions['starimage']);

            for ($i = 0; $i < $groupPermissions['stars']; ++$i) {
                $userStars .= eval($this->templateGet($templatePrefix . 'UserStar', false));
            }

            $userStars .= '<br />';
        }

        $userGroupImage = '';

        if (!empty($groupPermissions['image'])) {
            $groupImage = str_replace(['{lang}', '{theme}'],
                [$mybb->user['language'] ?? $mybb->settings['language'], $theme['imgdir']],
                $groupPermissions['image']);

            $groupTitle = $groupPermissions['title'];

            $userGroupImage = eval($this->templateGet($templatePrefix . 'UserGroupImage'));
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

        $userPostNumber = my_number_format($userData['postnum'] ?? 0);

        $userThreadNumber = my_number_format($userData['threadnum'] ?? 0);

        $userRegistrationDate = my_date($mybb->settings['regdateformat'], $userData['regdate'] ?? 0);

        if (!empty($groupPermissions['usereputationsystem']) && !empty($mybb->settings['enablereputation'])) {
            $userReputation = get_reputation($userData['reputation'], $userID);

            $userDetailsReputationLink = eval($this->templateGet($templatePrefix . 'UserReputation'));
        }

        $buttonEdit = '';

        if ($postType === self::POST_TYPE_ENTRY && $this->showcaseObject->userPermissions[ModeratorPermissions::CanEditEntries]) {
            $editUrl = $this->showcaseObject->urlBuild(
                $this->showcaseObject->urlUpdateEntry,
                $this->showcaseObject->entryData['entry_slug']
            );

            $buttonEdit = eval($this->templateGet($templatePrefix . 'ButtonEdit'));
        } elseif ($postType === self::POST_TYPE_COMMENT && $this->showcaseObject->userPermissions[ModeratorPermissions::CanEditComments]) {
            $editUrl = $this->showcaseObject->urlBuild(
                $this->showcaseObject->urlUpdateComment,
                $this->showcaseObject->entryData['entry_slug'],
                $commentID
            );

            $buttonEdit = eval($this->templateGet($templatePrefix . 'ButtonEdit'));
        }

        $buttonWarn = $userDetailsWarningLevel = '';

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

            $userDetailsWarningLevel = eval($this->templateGet($templatePrefix . 'UserWarningLevel'));
        }

        $date = my_date('relative', $postData['dateline']);

        $approvedByMessage = '';

        if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries] &&
            !empty($postData['moderator_user_id'])) {
            $moderatorUserData = get_user($postData['moderator_user_id']);

            $moderatorUserProfileLink = build_profile_link(
                $moderatorUserData['username'],
                $moderatorUserData['uid']
            );

            $approvedByMessage = eval($this->templateGet($templatePrefix . 'ModeratedBy'));
        }

        $userSignature = '';

        if ($this->showcaseObject->displaySignatures &&
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
            $this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries]) {
            if ($postStatus === ENTRY_STATUS_PENDING_APPROVAL) {
                $approveUrl = $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlApproveEntry,
                    $this->showcaseObject->entryData['entry_slug']
                );

                $buttonApprove = eval($this->templateGet($templatePrefix . 'ButtonApprove'));
            } elseif ($postStatus === ENTRY_STATUS_VISIBLE) {
                $unapproveUrl = $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlUnapproveEntry,
                    $this->showcaseObject->entryData['entry_slug']
                );

                $buttonUnpprove = eval($this->templateGet($templatePrefix . 'ButtonUnapprove'));
            }

            if ($postStatus === ENTRY_STATUS_SOFT_DELETED) {
                $restoreUrl = $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlRestoreEntry,
                    $this->showcaseObject->entryData['entry_slug']
                );

                $buttonRestore = eval($this->templateGet($templatePrefix . 'ButtonRestore'));
            } elseif ($postStatus === ENTRY_STATUS_VISIBLE) {
                $softDeleteUrl = $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlSoftDeleteEntry,
                    $this->showcaseObject->entryData['entry_slug']
                );

                $buttonSoftDelete = eval($this->templateGet($templatePrefix . 'ButtonSoftDelete'));
            }

            //only mods, original author (if allowed) or owner (if allowed) can delete comments
            if (
                $this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries] ||
                ($userID === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteEntries])
            ) {
                $deleteUrl = $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlDeleteEntry,
                    $this->showcaseObject->entryData['entry_slug']
                );

                $buttonDelete = eval($this->templateGet($templatePrefix . 'ButtonDelete'));
            }
        }

        if ($postType === self::POST_TYPE_COMMENT &&
            $this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveComments]) {
            if ($postStatus === COMMENT_STATUS_PENDING_APPROVAL) {
                $approveUrl = $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlApproveComment,
                    $this->showcaseObject->entryData['entry_slug'],
                    $commentID
                );

                $buttonApprove = eval($this->templateGet($templatePrefix . 'ButtonApprove'));
            } elseif ($postStatus === COMMENT_STATUS_VISIBLE) {
                $unapproveUrl = $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlUnapproveComment,
                    $this->showcaseObject->entryData['entry_slug'],
                    $commentID
                );

                $buttonUnpprove = eval($this->templateGet($templatePrefix . 'ButtonUnapprove'));
            }

            if ($postStatus === COMMENT_STATUS_SOFT_DELETED) {
                $restoreUrl = $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlRestoreComment,
                    $this->showcaseObject->entryData['entry_slug'],
                    $commentID
                );

                $buttonRestore = eval($this->templateGet($templatePrefix . 'ButtonRestore'));
            } elseif ($postStatus === COMMENT_STATUS_VISIBLE) {
                $softDeleteUrl = $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlSoftDeleteComment,
                    $this->showcaseObject->entryData['entry_slug'],
                    $commentID
                );

                $buttonSoftDelete = eval($this->templateGet($templatePrefix . 'ButtonSoftDelete'));
            }

            //only mods, original author (if allowed) or owner (if allowed) can delete comments
            if (
                $this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteComments] ||
                ($userID === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteComments]) ||
                ((int)$this->showcaseObject->entryData['user_id'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteAuthorComments])
            ) {
                $deleteUrl = $this->showcaseObject->urlBuild(
                    $this->showcaseObject->urlDeleteComment,
                    $this->showcaseObject->entryData['entry_slug'],
                    $commentID
                );

                $buttonDelete = eval($this->templateGet($templatePrefix . 'ButtonDelete'));
            }
        }

        $userDetails = '';

        if ($userID) {
            $userDetails = eval($this->templateGet($templatePrefix . 'UserDetails'));
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

        return eval($this->templateGet($templatePrefix));
    }

    public function buildEntry(array $entryData): string
    {
        return $this->buildPost(0, [
            'user_id' => $this->showcaseObject->entryUserID,
            'dateline' => $this->showcaseObject->entryData['dateline'],
            //'ipaddress' => $this->showcaseObject->entryData['ipaddress'],
            'status' => $this->showcaseObject->entryData['status'],
            'moderator_user_id' => $this->showcaseObject->entryData['approved_by'],
        ], alt_trow(true), self::POST_TYPE_ENTRY);
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
                    if (!empty($entryFieldValue) || $this->showcaseObject->displayEmptyFields) {
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

                    if (!empty($entryFieldValue) || $this->showcaseObject->displayEmptyFields) {
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
                    if (!empty($entryFieldValue) || $this->showcaseObject->displayEmptyFields) {
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
                    if (!empty($entryFieldValue) || $this->showcaseObject->displayEmptyFields) {
                        if (empty($entryFieldValue)) {
                            $entryFieldValue = $lang->myShowcaseEntryFieldValueEmpty;
                        } else {
                            $entryFieldValue = htmlspecialchars_uni($entryFieldValue);
                        }

                        $entryFieldsList[$fieldKey] = eval($this->templateGet('pageViewDataFieldRadio'));
                    }
                    break;
                case FieldHtmlTypes::CheckBox:
                    if (!empty($entryFieldValue) || $this->showcaseObject->displayEmptyFields) {
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
                    if (!empty($entryFieldValue) || $this->showcaseObject->displayEmptyFields) {
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
                    if (!empty($entryFieldValue) || $this->showcaseObject->displayEmptyFields) {
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

    // todo, add support for fancy box for attachment images
    public function entryBuildAttachments(array &$entryFields): string
    {
        if (!$this->showcaseObject->allowAttachments || !$this->showcaseObject->userPermissions[UserPermissions::CanViewAttachments]) {
            return '';
        }

        $attachmentObjects = attachmentGet(
            ["entry_id='{$this->showcaseObject->entryID}'", "showcase_id='{$this->showcaseObject->showcase_id}'"],
            ['status', 'file_name', 'file_size', 'file_name', 'downloads', 'dateline', 'thumbnail']
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
            $attachmentUrl = $this->showcaseObject->urlBuild(
                $this->showcaseObject->urlViewAttachment,
                $this->showcaseObject->entryData['entry_slug'],
                attachmentID: $attachmentID
            );

            $attachmentThumbnailUrl = $this->showcaseObject->urlBuild(
                $this->showcaseObject->urlViewAttachmentThumbnail,
                $this->showcaseObject->entryData['entry_slug'],
                attachmentID: $attachmentID
            );

            if ($attachmentData['status']) { // There is an attachment thats status!
                $attachmentFileName = htmlspecialchars_uni($attachmentData['file_name']);

                $attachmentFileSize = get_friendly_size($attachmentData['file_size']);

                $attachmentExtension = get_extension($attachmentData['file_name']);

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
                        if ($attachmentData['thumbnail'] !== 'SMALL' &&
                            $attachmentData['thumbnail'] !== '' &&
                            $this->showcaseObject->attachmentsDisplayThumbnails) {
                            $attachmentBit = eval($this->templateGet('pageViewEntryAttachmentsThumbnailsItem'));
                        } elseif ((($attachmentData['thumbnail'] === 'SMALL' &&
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
                    $attachmentData['thumbnail'] !== 'SMALL' &&
                    $attachmentData['thumbnail'] !== '' &&
                    $this->showcaseObject->attachmentsDisplayThumbnails) {
                    // Show as thumbnail IF image is big && thumbnail exists && setting=='thumb'
                    // Show as full size image IF setting=='fullsize' || (image is small && permissions allow)
                    // Show as download for all other cases
                    $attachedThumbnails .= eval($this->templateGet('pageViewEntryAttachmentsThumbnailsItem'));

                    if ($thumbnailsCount === $this->showcaseObject->attachmentsPerRowLimit) {
                        $attachedThumbnails .= '<br />';

                        $thumbnailsCount = 0;
                    }

                    ++$thumbnailsCount;
                } elseif (!$attachmentInField && (($attachmentData['thumbnail'] === 'SMALL' &&
                            $this->showcaseObject->userPermissions[UserPermissions::CanDownloadAttachments]) ||
                        $this->showcaseObject->attachmentsDisplayFullSizeImage) &&
                    $isImageAttachment) {
                    if ($this->showcaseObject->userPermissions[UserPermissions::CanDownloadAttachments]) {
                        $attachedImages .= eval($this->templateGet('pageViewEntryAttachmentsImagesItem'));
                    } else {
                        $attachedThumbnails .= eval($this->templateGet('pageViewEntryAttachmentsThumbnailsItem'));

                        if ($thumbnailsCount === $this->showcaseObject->attachmentsPerRowLimit) {
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
}