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

use function MyShowcase\Core\getTemplate;
use function MyShowcase\Core\urlHandlerBuild;
use function MyShowcase\Core\urlHandlerGet;

class Render
{
    public function __construct(
        public Showcase &$showcaseObject,
        public string $highlightTerms = '',
        public string $searchKeyWords = '',
        public int $searchExactMatch = 0,
        public array $parserOptions = []
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
    }

    public function templateGet(string $templateName = '', bool $enableHTMLComments = true): string
    {
        return getTemplate($templateName, $enableHTMLComments, $this->showcaseObject->id);
    }

    public function buildComment(int $commentsCounter, array $commentData, string $alternativeBackground): string
    {
        global $mybb, $lang, $theme;

        $commentID = (int)$commentData['cid'];

        $currentUserID = (int)$mybb->user['uid'];

        $commentUserID = (int)$commentData['uid'];

        $commentUserData = get_user($commentUserID);

        // todo, this should probably account for additional groups, but I just took it from post bit logic for now
        if ($commentUserData['usergroup']) {
            $groupPermissions = usergroup_permissions($commentUserData['usergroup']);
        } else {
            $groupPermissions = usergroup_permissions(1);
        }

        if (!empty($commentUserData['username'])) {
            $commentUserData['username'] = $lang->guest;
        }

        $commentUserProfileLinkPlain = get_profile_link($commentUserID);

        $commentUserName = htmlspecialchars_uni($commentUserData['username']);

        $commentUserNameFormatted = format_name(
            $commentUserName,
            $commentUserData['usergroup'],
            $commentUserData['displaygroup']
        );

        $commentUserProfileLink = build_profile_link($commentUserNameFormatted, $commentUserID);

        if (isset($mybb->user['showavatars']) && !empty($mybb->user['showavatars']) || !$currentUserID) {
            $commentUserAvatar = format_avatar(
                $commentUserData['avatar'],
                $commentUserData['avatardimensions'],
                $mybb->settings['postmaxavatarsize']
            );

            $commentUserAvatar = eval($this->templateGet('pageViewCommentsCommentUserAvatar'));
        }

        if (!empty($groupPermissions['usertitle']) && empty($commentUserData['usertitle'])) {
            $commentUserData['usertitle'] = $groupPermissions['usertitle'];
        } elseif (empty($groupPermissions['usertitle']) && ($userTitlesCache = $this->cacheGetUserTitles())) {
            reset($userTitlesCache);

            foreach ($userTitlesCache as $postNumber => $titleinfo) {
                if ($commentUserData['postnum'] >= $postNumber) {
                    if (empty($commentUserData['usertitle'])) {
                        $commentUserData['usertitle'] = $titleinfo['title'];
                    }

                    $groupPermissions['stars'] = $titleinfo['stars'];

                    $groupPermissions['starimage'] = $titleinfo['starimage'];

                    break;
                }
            }
        }

        $commentUserTitle = htmlspecialchars_uni($commentUserData['usertitle']);

        $commentUserStars = '';

        if (!empty($groupPermissions['starimage']) && isset($groupPermissions['stars'])) {
            $groupStarImage = str_replace('{theme}', $theme['imgdir'], $groupPermissions['starimage']);

            for ($i = 0; $i < $groupPermissions['stars']; ++$i) {
                $commentUserStars .= eval($this->templateGet('pageViewCommentsCommentUserStar', false));
            }

            $commentUserStars .= '<br />';
        }

        $commentUserGroupImage = '';

        if (!empty($groupPermissions['image'])) {
            $groupImage = str_replace(['{lang}', '{theme}'],
                [$mybb->user['language'] ?? $mybb->settings['language'], $theme['imgdir']],
                $groupPermissions['image']);

            $groupTitle = $groupPermissions['title'];

            $commentUserGroupImage = eval($this->templateGet('pageViewCommentsCommentUserGroupImage'));
        }

        // Determine the status to show for the user (Online/Offline/Away)
        if ($commentUserData['lastactive'] > TIME_NOW - $mybb->settings['wolcutoff'] &&
            (empty($commentUserData['invisible']) || !empty($mybb->usergroup['canviewwolinvis'])) &&
            (int)$commentUserData['lastvisit'] !== (int)$commentUserData['lastactive']) {
            $commentUserOnlineStatus = eval($this->templateGet('pageViewCommentsCommentUserOnlineStatusOnline'));
        } elseif (!empty($commentUserData['away']) && !empty($mybb->settings['allowaway'])) {
            $commentUserOnlineStatus = eval($this->templateGet('pageViewCommentsCommentUserOnlineStatusAway'));
        } else {
            $commentUserOnlineStatus = eval($this->templateGet('pageViewCommentsCommentUserOnlineStatusOffline'));
        }

        $commentUserPostNumber = my_number_format($commentUserData['postnum']);

        $commentUserThreadNumber = my_number_format($commentUserData['threadnum']);

        $commentUserRegistrationDate = my_date($mybb->settings['regdateformat'], $commentUserData['regdate']);

        if (!empty($groupPermissions['usereputationsystem']) && !empty($mybb->settings['enablereputation'])) {
            $commentUserReputation = get_reputation($commentUserData['reputation'], $commentUserID);

            $commentUserDetailsReputationLink = eval($this->templateGet('pageViewCommentsCommentUserReputation'));
        }

        $commentButtonEmail = $commentButtonPrivateMessage = $commentButtonWebsite = '';
        $commentButtonDelete = $commentButtonPurgeSpammer = $commentButtonWarn = '';
        $commentUserSignature = '';

        $commentUserDetailsWarningLevel = '';

        if (!empty($mybb->settings['enablewarningsystem']) &&
            !empty($groupPermissions['canreceivewarnings']) &&
            (!empty($mybb->usergroup['canwarnusers']) || ($currentUserID === $commentUserID && !empty($mybb->settings['canviewownwarning'])))) {
            if ($mybb->settings['maxwarningpoints'] < 1) {
                $mybb->settings['maxwarningpoints'] = 10;
            }

            $warningLevel = round(
                $commentUserData['warningpoints'] / $mybb->settings['maxwarningpoints'] * 100
            );

            if ($warningLevel > 100) {
                $warningLevel = 100;
            }

            $warningLevel = get_colored_warning_level($warningLevel);

            // If we can warn them, it's not the same person, and we're in a PM or a post.
            if (!empty($mybb->usergroup['canwarnusers']) && $commentUserID !== $currentUserID) {
                $commentButtonWarn = eval($this->templateGet('pageViewCommentsCommentButtonWarn'));

                $warningUrl = "warnings.php?uid={$commentUserID}";
            } else {
                $warningUrl = 'usercp.php';
            }

            $commentUserDetailsWarningLevel = eval($this->templateGet('pageViewCommentsCommentUserWarningLevel'));
        }

        $commentUserDetails = eval($this->templateGet('pageViewCommentsCommentUserDetails'));

        $commentPostDate = my_date('relative', $commentData['dateline']);

        $commentMessage = $this->showcaseObject->parseMessage($commentData['comment'], $this->parserOptions);

        if (!empty($commentUserData['username']) &&
            !empty($commentUserData['signature']) &&
            (!$currentUserID || !empty($mybb->user['showsigs'])) &&
            (empty($commentUserData['suspendsignature']) || !empty($commentUserData['suspendsignature']) && !empty($commentUserData['suspendsigtime']) && $commentUserData['suspendsigtime'] < TIME_NOW) &&
            !empty($groupPermissions['canusesig']) &&
            (empty($groupPermissions['canusesigxposts']) || $groupPermissions['canusesigxposts'] > 0 && $commentUserData['postnum'] > $groupPermissions['canusesigxposts']) &&
            !is_member($mybb->settings['hidesignatures'])) {
            $signatureParserOptions = [
                'allow_html' => !empty($mybb->settings['sightml']),
                'allow_mycode' => !empty($mybb->settings['sigmycode']),
                'allow_smilies' => !empty($mybb->settings['sigsmilies']),
                'allow_imgcode' => !empty($mybb->settings['sigimgcode']),
                'me_username' => $commentUserData['username']
            ];

            if ($groupPermissions['signofollow']) {
                $signatureParserOptions['nofollow_on'] = true;
            }

            if ($currentUserID && empty($mybb->user['showimages']) || empty($mybb->settings['guestimages']) && !$currentUserID) {
                $signatureParserOptions['allow_imgcode'] = false;
            }

            $commentUserSignature = $this->showcaseObject->parseMessage(
                $commentUserData['signature'],
                $signatureParserOptions
            );

            $commentUserSignature = eval($this->templateGet('pageViewCommentsCommentUserSignature'));
        }

        if (empty($commentUserData['hideemail']) && $commentUserID !== $currentUserID && !empty($mybb->usergroup['cansendemail'])) {
            $commentButtonEmail = eval($this->templateGet('pageViewCommentsCommentButtonDelete'));
        }

        if (!empty($mybb->settings['enablepms']) &&
            $commentUserID !== $currentUserID &&
            ((!empty($commentUserData['receivepms']) &&
                    !empty($groupPermissions['canusepms']) &&
                    !empty($mybb->usergroup['cansendpms']) &&
                    my_strpos(
                        ',' . $commentUserData['ignorelist'] . ',',
                        ',' . $currentUserID . ','
                    ) === false) ||
                !empty($mybb->usergroup['canoverridepm']))) {
            $commentButtonPrivateMessage = eval(
            $this->templateGet(
                'pageViewCommentsCommentButtonPrivateMessage'
            )
            );
        }

        if (!empty($commentUserData['website']) &&
            !is_member($mybb->settings['hidewebsite']) &&
            !empty($groupPermissions['canchangewebsite'])) {
            $commentUserWebsite = htmlspecialchars_uni($commentUserData['website']);

            $commentButtonWebsite = eval($this->templateGet('pageViewCommentsCommentButtonWebsite'));
        }

        //setup comment admin options
        //only mods, original author (if allowed) or owner (if allowed) can delete comments
        if (
            ($this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteComments]) ||
            ((int)$commentData['uid'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteComments]) ||
            ((int)$this->showcaseObject->entryData['uid'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteAuthorComments])
        ) {
            $commentButtonDelete = eval($this->templateGet('pageViewCommentsCommentButtonDelete'));

            $commentButtonPurgeSpammer = eval($this->templateGet('pageViewCommentsCommentButtonPurgeSpammer'));
        }

        $commentUrl = urlHandlerBuild(
            ['action' => 'view', 'gid' => $this->showcaseObject->entryID, 'commentID' => $commentID]
        );

        $commentNumber = my_number_format($commentsCounter);

        $commentUrl = eval($this->templateGet('pageViewCommentsCommentUrl'));

        return eval($this->templateGet('pageViewCommentsComment'));
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