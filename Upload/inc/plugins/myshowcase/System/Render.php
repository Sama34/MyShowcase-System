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
use function MyShowcase\Core\attachmentInsert;
use function MyShowcase\Core\cacheGet;
use function MyShowcase\Core\getTemplate;
use function MyShowcase\Core\postParser;
use function MyShowcase\Core\urlHandlerBuild;

use const MyShowcase\Core\CACHE_TYPE_FIELDS;
use const MyShowcase\Core\CHECK_BOX_IS_CHECKED;
use const MyShowcase\Core\FIELD_TYPE_HTML_CHECK_BOX;
use const MyShowcase\Core\FIELD_TYPE_HTML_DATE;
use const MyShowcase\Core\FIELD_TYPE_HTML_DB;
use const MyShowcase\Core\FIELD_TYPE_HTML_RADIO;
use const MyShowcase\Core\FIELD_TYPE_HTML_TEXT_BOX;
use const MyShowcase\Core\FIELD_TYPE_HTML_TEXTAREA;
use const MyShowcase\Core\FIELD_TYPE_HTML_URL;
use const MyShowcase\Core\FORMAT_TYPE_MY_NUMBER_FORMAT;
use const MyShowcase\Core\FORMAT_TYPE_MY_NUMBER_FORMAT_1_DECIMALS;
use const MyShowcase\Core\FORMAT_TYPE_MY_NUMBER_FORMAT_2_DECIMALS;
use const MyShowcase\Core\FORMAT_TYPES;

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
        public array $fieldSetFieldsOrder = [],
        public array $fieldSetFieldsDisplayFields = [],
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

        $this->fieldSetFieldsDisplayFields = [
            'createdate' => $lang->myShowcaseMainSortDateline,
            //'edit_stamp' => $lang->myShowcaseMainSortEditDate,
            'username' => $lang->myShowcaseMainSortUsername,
            'views' => $lang->myShowcaseMainSortViews,
            'comments' => $lang->myShowcaseMainSortComments
        ];

        foreach ($this->showcaseObject->fieldSetFieldsOrder as $fieldOrder => $fieldName) {
            $this->fieldSetFieldsDisplayFields[$fieldName] = $lang->{"myshowcase_field_{$fieldName}"} ?? ucfirst(
                $fieldName
            );
        }

        $this->fieldSetFieldsSearchFields = [
            'username' => $lang->myShowcaseMainSortUsername
        ];

        foreach ($this->showcaseObject->fieldSetSearchableFields as $fieldName => $htmlType) {
            $fieldNameUpper = ucfirst($fieldName);

            $this->fieldSetFieldsSearchFields[$fieldName] = $lang->{"myShowcaseMainSort{$fieldNameUpper}"} ?? $lang->{"myshowcase_field_{$fieldName}"} ?? $fieldNameUpper;
        }
    }

    public function templateGet(string $templateName = '', bool $enableHTMLComments = true): string
    {
        return getTemplate($templateName, $enableHTMLComments, $this->showcaseObject->id);
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

            $commentUrl = urlHandlerBuild(
                ['action' => 'view', 'gid' => $entryID, 'commentID' => $commentID]
            );

            $commentUrl = eval($this->templateGet($templatePrefix . 'Url'));
        } else {
            $entryFields = $this->buildEntryFields();

            $entryUrl = urlHandlerBuild(
                ['action' => 'view', 'gid' => $entryID]
            );

            $entryAttachments = $this->entryBuildAttachments($entryFields);

            $entryFields = implode('', $entryFields);
        }

        $currentUserID = (int)$mybb->user['uid'];

        $userID = (int)$postData['user_id'];

        $userData = get_user($userID);

        // todo, this should probably account for additional groups, but I just took it from post bit logic for now
        if ($userData['usergroup']) {
            $groupPermissions = usergroup_permissions($userData['usergroup']);
        } else {
            $groupPermissions = usergroup_permissions(1);
        }

        if (!empty($userData['username'])) {
            $userData['username'] = $lang->guest;
        }

        $userProfileLinkPlain = get_profile_link($userID);

        $commentUserName = htmlspecialchars_uni($userData['username']);

        $commentUserNameFormatted = format_name(
            $commentUserName,
            $userData['usergroup'],
            $userData['displaygroup']
        );

        $userProfileLink = build_profile_link($commentUserNameFormatted, $userID);

        if (isset($mybb->user['showavatars']) && !empty($mybb->user['showavatars']) || !$currentUserID) {
            $userAvatar = format_avatar(
                $userData['avatar'],
                $userData['avatardimensions'],
                $this->showcaseObject->maximumAvatarSize
            );

            $userAvatarImage = $userAvatar['image'] ?? '';

            $userAvatarWidthHeight = $userAvatar['width_height'] ?? '';

            $serAvatar = eval($this->templateGet($templatePrefix . 'UserAvatar'));
        }

        if (!empty($groupPermissions['usertitle']) && empty($userData['usertitle'])) {
            $userData['usertitle'] = $groupPermissions['usertitle'];
        } elseif (empty($groupPermissions['usertitle']) && ($userTitlesCache = $this->cacheGetUserTitles())) {
            reset($userTitlesCache);

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

        // Determine the status to show for the user (Online/Offline/Away)
        if ($userData['lastactive'] > TIME_NOW - $mybb->settings['wolcutoff'] &&
            (empty($userData['invisible']) || !empty($mybb->usergroup['canviewwolinvis'])) &&
            (int)$userData['lastvisit'] !== (int)$userData['lastactive']) {
            $userOnlineStatus = eval($this->templateGet($templatePrefix . 'UserOnlineStatusOnline'));
        } elseif (!empty($userData['away']) && !empty($mybb->settings['allowaway'])) {
            $userOnlineStatus = eval($this->templateGet($templatePrefix . 'UserOnlineStatusAway'));
        } else {
            $userOnlineStatus = eval($this->templateGet($templatePrefix . 'UserOnlineStatusOffline'));
        }

        $userPostNumber = my_number_format($userData['postnum']);

        $userThreadNumber = my_number_format($userData['threadnum']);

        $userRegistrationDate = my_date($mybb->settings['regdateformat'], $userData['regdate']);

        if (!empty($groupPermissions['usereputationsystem']) && !empty($mybb->settings['enablereputation'])) {
            $userReputation = get_reputation($userData['reputation'], $userID);

            $userDetailsReputationLink = eval($this->templateGet($templatePrefix . 'UserReputation'));
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
            !empty($postData['moderator_uid'])) {
            $moderatorUserData = get_user($postData['moderator_uid']);

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

        $buttonDelete = '';

        //setup comment admin options
        //only mods, original author (if allowed) or owner (if allowed) can delete comments

        if ($postType === self::POST_TYPE_COMMENT) {
            if (
                ($this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteComments]) ||
                ((int)$postData['user_id'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteComments]) ||
                ((int)$this->showcaseObject->entryData['uid'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteAuthorComments])
            ) {
                $buttonDelete = eval($this->templateGet($templatePrefix . 'ButtonDelete'));
            }
        }

        $buttonPurgeSpammer = '';

        if (
            ($this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteComments]) ||
            ((int)$postData['user_id'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteComments]) ||
            ((int)$this->showcaseObject->entryData['uid'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteAuthorComments])
        ) {
            $buttonPurgeSpammer = eval($this->templateGet($templatePrefix . 'ButtonPurgeSpammer'));
        }

        $serDetails = eval($this->templateGet($templatePrefix . 'UserDetails'));

        return eval($this->templateGet($templatePrefix));
    }

    public function buildEntry(array $entryData): string
    {
        return $this->buildPost(0, [
            'user_id' => $this->showcaseObject->entryUserID,
            'dateline' => $this->showcaseObject->entryData['dateline'],
            //'ipaddress' => $this->showcaseObject->entryData['ipaddress'],
            'moderator_uid' => $this->showcaseObject->entryData['approved_by'],
        ], alt_trow(true), self::POST_TYPE_ENTRY);
    }

    public function buildComment(int $commentsCounter, array $commentData, string $alternativeBackground): string
    {
        return $this->buildPost($commentsCounter, [
            'comment_id' => $commentData['cid'],
            'user_id' => $commentData['uid'],
            'message' => $commentData['comment'],
            'dateline' => $commentData['dateline'],
            'ipaddress' => $commentData['ipaddress'],
            //'moderator_uid' => $commentData['moderator_uid'],
        ], $alternativeBackground);
    }

    public function buildEntryFields(): array
    {
        global $mybb, $lang;

        $entryFieldsList = [];

        foreach ($this->showcaseObject->fieldSetEnabledFields as $fieldName => $htmlType) {
            $fieldHeader = $lang->{'myshowcase_field_' . $fieldName} ?? $fieldName;

            //set parser options for current field

            $entryFieldValue = $this->showcaseObject->entryData[$fieldName] ?? '';

            switch ($htmlType) {
                case FIELD_TYPE_HTML_TEXTAREA:
                    if (!empty($entryFieldValue) || $this->showcaseObject->displayEmptyFields) {
                        if (empty($entryFieldValue)) {
                            $entryFieldValue = $lang->myShowcaseEntryFieldValueEmpty;
                        } elseif ($this->showcaseObject->fieldSetParseableFields[$fieldName] || $this->highlightTerms) {
                            $entryFieldValue = $this->showcaseObject->parseMessage(
                                $entryFieldValue,
                                $this->parserOptions
                            );
                        } else {
                            $entryFieldValue = htmlspecialchars_uni($entryFieldValue);

                            $entryFieldValue = nl2br($entryFieldValue);
                        }

                        $entryFieldsList[$fieldName] = eval($this->templateGet('pageViewDataFieldTextArea'));
                    }

                    break;
                case FIELD_TYPE_HTML_TEXT_BOX:
                    //format values as requested
                    match ($this->showcaseObject->fieldSetFormatableFields[$fieldName]) {
                        FORMAT_TYPE_MY_NUMBER_FORMAT => FORMAT_TYPES[FORMAT_TYPE_MY_NUMBER_FORMAT](
                            $entryFieldValue
                        ),
                        FORMAT_TYPE_MY_NUMBER_FORMAT_1_DECIMALS => FORMAT_TYPES[FORMAT_TYPE_MY_NUMBER_FORMAT_1_DECIMALS](
                            $entryFieldValue
                        ),
                        FORMAT_TYPE_MY_NUMBER_FORMAT_2_DECIMALS => FORMAT_TYPES[FORMAT_TYPE_MY_NUMBER_FORMAT_2_DECIMALS](
                            $entryFieldValue
                        ),
                        default => false
                    };

                    if (!empty($entryFieldValue) || $this->showcaseObject->displayEmptyFields) {
                        if (empty($entryFieldValue)) {
                            $entryFieldValue = $lang->myShowcaseEntryFieldValueEmpty;
                        } elseif ($this->showcaseObject->fieldSetParseableFields[$fieldName] || $this->highlightTerms) {
                            $entryFieldValue = $this->showcaseObject->parseMessage(
                                $entryFieldValue,
                                $this->parserOptions
                            );
                        } else {
                            $entryFieldValue = htmlspecialchars_uni($entryFieldValue);
                        }

                        $entryFieldsList[$fieldName] = eval($this->templateGet('pageViewDataFieldTextBox'));
                    }
                    break;
                case FIELD_TYPE_HTML_URL:
                    if (!empty($entryFieldValue) || $this->showcaseObject->displayEmptyFields) {
                        if (empty($entryFieldValue)) {
                            $entryFieldValue = $lang->myShowcaseEntryFieldValueEmpty;
                        } elseif ($this->showcaseObject->fieldSetParseableFields[$fieldName]) {
                            $entryFieldValue = postParser()->mycode_parse_url(
                                $entryFieldValue
                            );
                        } else {
                            $entryFieldValue = htmlspecialchars_uni($entryFieldValue);
                        }

                        $entryFieldsList[$fieldName] = eval($this->templateGet('pageViewDataFieldUrl'));
                    }
                    break;
                case FIELD_TYPE_HTML_RADIO:
                    if (!empty($entryFieldValue) || $this->showcaseObject->displayEmptyFields) {
                        if (empty($entryFieldValue)) {
                            $entryFieldValue = $lang->myShowcaseEntryFieldValueEmpty;
                        } else {
                            $entryFieldValue = htmlspecialchars_uni($entryFieldValue);
                        }

                        $entryFieldsList[$fieldName] = eval($this->templateGet('pageViewDataFieldRadio'));
                    }
                    break;
                case FIELD_TYPE_HTML_CHECK_BOX:
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

                        $entryFieldsList[$fieldName] = eval($this->templateGet('pageViewDataFieldCheckBox'));
                    }
                    break;
                case FIELD_TYPE_HTML_DB:
                    if (!empty($entryFieldValue) || $this->showcaseObject->displayEmptyFields) {
                        if (empty($entryFieldValue)) {
                            $entryFieldValue = $lang->myShowcaseEntryFieldValueEmpty;
                        } elseif ($this->showcaseObject->fieldSetParseableFields[$fieldName] || $this->highlightTerms) {
                            $entryFieldValue = $this->showcaseObject->parseMessage(
                                $entryFieldValue,
                                $this->parserOptions
                            );
                        } else {
                            $entryFieldValue = htmlspecialchars_uni($entryFieldValue);
                        }

                        $entryFieldsList[$fieldName] = eval($this->templateGet('pageViewDataFieldDataBase'));
                    }
                    break;
                case FIELD_TYPE_HTML_DATE:
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

                        $entryFieldsList[$fieldName] = eval(getTemplate('pageViewDataFieldDate'));
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
            ["gid='{$this->showcaseObject->entryID}'", "id='{$this->showcaseObject->id}'"],
            ['visible', 'filename', 'filesize', 'filename', 'downloads', 'dateuploaded', 'thumbnail']
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
            $attachmentUrl = urlHandlerBuild(['action' => 'item', 'aid' => $attachmentID]);

            $attachmentThumbnailUrl = urlHandlerBuild(['action' => 'item', 'thumbnail' => $attachmentID]);

            if ($attachmentData['visible']) { // There is an attachment thats visible!
                $attachmentFileName = htmlspecialchars_uni($attachmentData['filename']);

                $attachmentFileSize = get_friendly_size($attachmentData['filesize']);

                $attachmentExtension = get_extension($attachmentData['filename']);

                $isImageAttachment = in_array($attachmentExtension, ['jpeg', 'gif', 'bmp', 'png', 'jpg']);

                $attachmentIcon = get_attachment_icon($attachmentExtension);

                $attachmentDownloads = my_number_format($attachmentData['downloads']);

                if (!$attachmentData['dateuploaded']) {
                    $attachmentData['dateuploaded'] = $this->showcaseObject->entryData['dateline'];
                }

                $attachmentDate = my_date('normal', $attachmentData['dateuploaded']);

                // Support for [attachment=id] code
                $attachmentInField = false;

                foreach ($entryFields as $fieldName => &$fieldValue) {
                    if (str_contains($fieldValue, '[attachment=' . $attachmentID . ']') !== false) {
                        $attachmentInField = true;

                        // Show as thumbnail IF image is big && thumbnail exists && setting=='thumb'
                        // Show as full size image IF setting=='fullsize' || (image is small && permissions allow)
                        // Show as download for all other cases
                        if ($attachmentData['thumbnail'] != 'SMALL' &&
                            $attachmentData['thumbnail'] != '' &&
                            $this->showcaseObject->attachmentsDisplayThumbnails) {
                            $attachmentBit = eval($this->templateGet('pageViewEntryAttachmentsThumbnailsItem'));
                        } elseif ((($attachmentData['thumbnail'] == 'SMALL' &&
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
                    $attachmentData['thumbnail'] != 'SMALL' &&
                    $attachmentData['thumbnail'] != '' &&
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
                } elseif (!$attachmentInField && (($attachmentData['thumbnail'] == 'SMALL' &&
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

            if (is_array($titlesCache)) {
                krsort($titlesCache);
            }
        }

        return $titlesCache;
    }
}