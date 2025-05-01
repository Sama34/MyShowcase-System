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
use JetBrains\PhpStorm\NoReturn;
use inc\datahandlers\MyShowcaseDataHandler;

use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\getTemplate;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\Core\urlHandlerBuild;
use function MyShowcase\Core\urlHandlerGet;
use function MyShowcase\Core\urlHandlerSet;
use function MyShowcase\Core\fieldDataGet;
use function MyShowcase\Core\showcaseDataGet;
use function MyShowcase\Core\cacheUpdate;
use function MyShowcase\Core\loadLanguage;
use function MyShowcase\Core\reportGet;
use function MyShowcase\Core\reportInsert;
use function MyShowcase\Core\reportUpdate;
use function MyShowcase\Core\showcaseDataUpdate;
use function MyShowcase\Core\commentsDelete;
use function MyShowcase\Core\commentGet;
use function MyShowcase\Core\commentInsert;
use function MyShowcase\Core\dataTableStructureGet;
use function MyShowcase\Core\postParser;

use const MyShowcase\Core\ATTACHMENT_UNLIMITED;
use const MyShowcase\Core\ATTACHMENT_ZERO;
use const MyShowcase\Core\FIELD_TYPE_HTML_CHECK_BOX;
use const MyShowcase\Core\FIELD_TYPE_HTML_DB;
use const MyShowcase\Core\FIELD_TYPE_HTML_DATE;
use const MyShowcase\Core\FIELD_TYPE_HTML_RADIO;
use const MyShowcase\Core\FIELD_TYPE_HTML_TEXT_BOX;
use const MyShowcase\Core\FIELD_TYPE_HTML_TEXTAREA;
use const MyShowcase\Core\FIELD_TYPE_HTML_URL;
use const MyShowcase\Core\FORMAT_TYPE_MY_NUMBER_FORMAT;
use const MyShowcase\Core\FORMAT_TYPE_NONE;
use const MyShowcase\Core\FORMAT_TYPES;
use const MyShowcase\Core\TABLES_DATA;
use const MyShowcase\Core\CACHE_TYPE_REPORTS;
use const MyShowcase\Core\FORMAT_TYPE_MY_NUMBER_FORMAT_1_DECIMALS;
use const MyShowcase\Core\FORMAT_TYPE_MY_NUMBER_FORMAT_2_DECIMALS;

class Output
{
    public function __construct(
        public Showcase &$showcaseObject,
        public Render &$renderObject
    ) {
    }

    public function entryView(): string
    {
        global $mybb, $plugins, $lang, $db, $theme;
        global $showcaseFields, $showcaseFieldEnabled, $showcaseFieldsParseable, $showcaseFieldsFormat;
        global $parser;

        $currentUserID = (int)$mybb->user['uid'];

        $plugins->run_hooks('myshowcase_view_start');

        reset($showcaseFields);

        $whereClauses = ["entryData.gid='{$mybb->get_input('gid', MyBB::INPUT_INT)}'"];

        $queryFields = [
            'entryData.gid',
            'entryData.uid',
            'userData.username',
            'entryData.views',
            'entryData.comments',
            'entryData.dateline',
            'entryData.approved',
            'entryData.approved_by',
            'entryData.posthash'
        ];

        $queryTables = ['users userData ON (userData.uid=entryData.uid)'];

        foreach ($showcaseFieldEnabled as $fieldName => $fieldType) {
            if ($fieldType == FIELD_TYPE_HTML_DB || $fieldType == FIELD_TYPE_HTML_RADIO) {
                $queryTables[] = "myshowcase_field_data table_{$fieldName} ON (table_{$fieldName}.valueid=entryData.{$fieldName} AND table_{$fieldName}.name='{$fieldName}')";

                $queryFields[] = "table_{$fieldName}.value AS {$fieldName}";

                // todo, I don't understand the purpose of this now
                // the condition after OR seems to fix it for now
                $whereClauses[] = "(table_{$fieldName}.setid='{$this->showcaseObject->fieldSetID}' OR entryData.{$fieldName}=0)";
            } else {
                $queryFields[] = $fieldName;
            }
        }
        // start getting showcase base data

        $this->showcaseObject->entryDataSet(
            $this->showcaseObject->dataGet($whereClauses, $queryFields, ['limit' => 1], $queryTables)
        );

        if (!$this->showcaseObject->entryID || empty($this->showcaseObject->entryData)) {
            error($lang->myshowcase_invalid_id);
        }

        if ($this->showcaseObject->entryData['username'] == '') {
            $this->showcaseObject->entryData['username'] = $lang->guest;
            $this->showcaseObject->entryData['uid'] = 0;
        }

        $showcase_viewing_user = str_replace(
            '{username}',
            $this->showcaseObject->entryData['username'],
            $lang->myshowcase_viewing_user
        );
        add_breadcrumb($showcase_viewing_user, SHOWCASE_URL);

        //set up jump to links
        $jumpto = $lang->myshowcase_jumpto;

        $entryUrl = str_replace('{gid}', (string)$mybb->get_input('gid'), SHOWCASE_URL_VIEW);
        if ($this->showcaseObject->allowAttachments && $this->showcaseObject->userPermissions[UserPermissions::CanViewAttachments]) {
            $jumpto .= ' <a href="' . $entryUrl . ($mybb->get_input(
                    'showall',
                    MyBB::INPUT_INT
                ) == 1 ? '&showall=1' : '') . '#images">' . $lang->myshowcase_attachments . '</a>';
        }

        if ($this->showcaseObject->allowComments && $this->showcaseObject->userPermissions[UserPermissions::CanViewComments]) {
            $jumpto .= ' <a href="' . $entryUrl . ($mybb->get_input(
                    'showall',
                    MyBB::INPUT_INT
                ) == 1 ? '&showall=1' : '') . '#comments">' . $lang->myShowcaseMainTableTheadComments . '</a>';
        }

        $jumptop = '(<a href="' . $entryUrl . ($mybb->get_input(
                'showall',
                MyBB::INPUT_INT
            ) == 1 ? '&showall=1' : '') . '#top">' . $lang->myshowcase_top . '</a>)';

        $entryHash = $this->showcaseObject->entryData['posthash'];

        $showcase_views = $this->showcaseObject->entryData['views'];
        $showcase_numcomments = $this->showcaseObject->entryData['comments'];

        $showcase_header_label = $lang->myshowcase_specifications;
        $showcase_header_jumpto = $jumpto;

        $showcase_admin_url = SHOWCASE_URL;

        if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanEditEntries] || ((int)$this->showcaseObject->entryData['uid'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanEditEntries])) {
            $showcase_view_admin_edit = eval(getTemplate('view_admin_edit'));
        }

        if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries] || ((int)$this->showcaseObject->entryData['uid'] === $currentUserID && $this->showcaseObject->userPermissions[UserPermissions::CanEditEntries])) {
            $showcase_view_admin_delete = eval(getTemplate('view_admin_delete'));
        }

        if ($showcase_view_admin_edit != '' || $showcase_view_admin_delete != '') {
            $showcase_header_special = eval(getTemplate('view_admin'));
        }

        $showcase_data_header = eval(getTemplate('table_header'));
        $showcase_header_jumpto;

        //trick MyBB into thinking its in archive so it adds bburl to smile link inside parser
        //doing this now should not impact anyhting. no issues with gomobile beta4
        define('IN_ARCHIVE', 1);

        reset($showcaseFieldEnabled);

        $alternativeBackground = alt_trow(true);

        $entryFieldsData = [];

        foreach ($showcaseFieldEnabled as $fieldName => $fieldType) {
            $fieldHeader = $lang->{'myshowcase_field_' . $fieldName} ?? $fieldName;

            //set parser options for current field

            $entryFieldValue = $this->showcaseObject->entryData[$fieldName] ?? '';

            if (empty($entryFieldValue) && $this->showcaseObject->displayEmptyFields) {
                $entryFieldValue = $lang->myShowcaseEntryFieldValueEmpty;
            }

            switch ($fieldType) {
                case FIELD_TYPE_HTML_TEXTAREA:
                    _dump('FIELD_TYPE_HTML_TEXTAREA');
                    $entryFieldValue = $this->showcaseObject->entryData[$fieldName];
                    if ($entryFieldValue != '' || $this->showcaseObject->displayEmptyFields == 1) {
                        if ($showcaseFieldsParseable[$fieldName] || $this->renderObject->highlightTerms) {
                            $entryFieldValue = $this->showcaseObject->parseMessage(
                                $entryFieldValue,
                                $this->renderObject->parserOptions
                            );
                        } else {
                            $entryFieldValue = htmlspecialchars_uni($entryFieldValue);
                            $entryFieldValue = nl2br($entryFieldValue);
                        }
                        $entryFieldsData[] = eval($this->renderObject->templateGet('pageViewDataFieldTextArea'));
                    }
                    break;

                case FIELD_TYPE_HTML_TEXT_BOX:
                    //format values as requested
                    match ($showcaseFieldsFormat[$fieldName]) {
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

                    if (!empty($entryFieldValue)) {
                        if ($showcaseFieldsParseable[$fieldName] || $this->renderObject->highlightTerms) {
                            $entryFieldValue = $this->showcaseObject->parseMessage(
                                $entryFieldValue,
                                $this->renderObject->parserOptions
                            );
                        } else {
                            $entryFieldValue = htmlspecialchars_uni($entryFieldValue);
                        }

                        $entryFieldsData[] = eval($this->renderObject->templateGet('pageViewDataFieldTextBox'));
                    }
                    break;

                case FIELD_TYPE_HTML_URL:
                    _dump('FIELD_TYPE_HTML_URL');
                    $entryFieldValue = $this->showcaseObject->entryData[$fieldName];
                    if ($entryFieldValue != '' || $this->showcaseObject->displayEmptyFields) {
                        $entryFieldValue = $parser->mycode_parse_url($this->showcaseObject->entryData[$fieldName]);
                        $entryFieldsData[] = eval(getTemplate('view_data_2'));
                    }
                    break;

                case FIELD_TYPE_HTML_RADIO:
                    _dump('FIELD_TYPE_HTML_RADIO');
                    $entryFieldValue = $this->showcaseObject->entryData[$fieldName];
                    if (($entryFieldValue != '') || $this->showcaseObject->displayEmptyFields) {
                        $entryFieldsData[] = eval(getTemplate('view_data_1'));
                    }
                    break;

                case FIELD_TYPE_HTML_CHECK_BOX:
                    _dump('FIELD_TYPE_HTML_CHECK_BOX');
                    if (($this->showcaseObject->entryData[$fieldName] != '') || $this->showcaseObject->displayEmptyFields) {
                        if ($this->showcaseObject->entryData[$fieldName] == 1) {
                            $entryFieldValue = '<img src="' . $mybb->settings['bburl'] . '/images/valid.gif" alt="Yes">';
                        } else {
                            $entryFieldValue = '<img src="' . $mybb->settings['bburl'] . '/images/invalid.gif" alt="No">';
                        }
                        $entryFieldsData[] = eval(getTemplate('view_data_1'));
                    }
                    break;

                case FIELD_TYPE_HTML_DB:
                    if (!empty($entryFieldValue)) {
                        if (!empty($entryFieldValue) && ($showcaseFieldsParseable[$fieldName] || $this->renderObject->highlightTerms)) {
                            $entryFieldValue = $this->showcaseObject->parseMessage(
                                $entryFieldValue,
                                $this->renderObject->parserOptions
                            );
                        } else {
                            $entryFieldValue = htmlspecialchars_uni($entryFieldValue);
                        }

                        $entryFieldsData[] = eval($this->renderObject->templateGet('pageViewDataFieldDataBase'));
                    }
                    break;

                case FIELD_TYPE_HTML_DATE:
                    _dump('FIELD_TYPE_HTML_DATE');
                    $entryFieldValue = $this->showcaseObject->entryData[$fieldName];
                    if ($entryFieldValue != '') {
                        $date_bits = explode('|', $this->showcaseObject->entryData[$fieldName]);
                        $date_bits = array_map('intval', $date_bits);

                        if ($date_bits[0] > 0 && $date_bits[1] > 0 && $date_bits[2] > 0) {
                            $entryFieldValue = my_date(
                                $mybb->settings['dateformat'],
                                mktime(0, 0, 0, $date_bits[0], $date_bits[1], $date_bits[2])
                            );
                        } else {
                            $entryFieldValue = '';
                            if ($date_bits[0]) {
                                $entryFieldValue .= $date_bits[0];
                            }
                            if ($date_bits[1]) {
                                $entryFieldValue .= ($entryFieldValue != '' ? '-' : '') . $date_bits[1];
                            }
                            if ($date_bits[2]) {
                                $entryFieldValue .= ($entryFieldValue != '' ? '-' : '') . $date_bits[2];
                            }
                        }
                    } else {
                        $entryFieldValue = '';
                    }
                    if (($entryFieldValue != '') || $this->showcaseObject->displayEmptyFields) {
                        $entryFieldsData[] = eval(getTemplate('view_data_1'));
                    }
                    break;
            }

            $alternativeBackground = alt_trow();
        }

        $entryLastApprovedBy = '';

        //for moderators, show who last approved the entry
        if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries] && $this->showcaseObject->entryData['approved']) {
            $moderatorUserData = get_user($this->showcaseObject->entryData['approved_by']);

            $moderatorUserProfileLink = build_profile_link(
                $moderatorUserData['username'],
                $moderatorUserData['uid']
            );

            $entryLastApprovedBy = eval($this->renderObject->templateGet('pageViewDataLastApprovedBy'));
        }

        /*
        //output bottom row for report button and future add-ons
//		$entry_final_row = '<a href="'.SHOWCASE_URL.'?action=report&gid='.$mybb->get_input('gid', \MyBB::INPUT_INT).'"><img src="'.$theme['imglangdir'].'/postbit_report.gif"></a>';
        $entry_final_row = '<a href="javascript:Showcase.reportShowcase(' . $mybb->get_input(
                'gid',
                MyBB::INPUT_INT
            ) . ');"><img src="' . $theme['imglangdir'] . '/postbit_report.gif"></a>';
        $entryFieldsData[] = eval(getTemplate('view_data_3'));
        */

        if ($this->showcaseObject->allowComments && $this->showcaseObject->userPermissions[UserPermissions::CanViewComments]) {
            $queryOptions = ['order_by' => 'dateline', 'order_dir' => 'DESC'];

            if (!$mybb->get_input('showall', MyBB::INPUT_INT)) {
                $queryOptions['limit'] = $this->showcaseObject->commentsPerPageLimit;
            }

            $commentObjects = commentGet(
                ["gid='{$this->showcaseObject->entryID}'", "id='{$this->showcaseObject->id}'"],
                ['uid', 'comment', 'dateline', 'ipaddress'],
                $queryOptions
            );

            // start getting comments

            $commentsList = $commentsForm = '';

            $entryFieldsData = implode('', $entryFieldsData);

            $entryUrl = urlHandlerBuild(['action' => 'view', 'gid' => $this->showcaseObject->entryID]);

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

            $showcase_show_all = '';
            if ($mybb->get_input(
                    'showall',
                    MyBB::INPUT_INT
                ) != 1 && $showcase_numcomments > $this->showcaseObject->commentsPerPageLimit) {
                $showcase_show_all = '(<a href="' . $entryUrl . $amp . 'showall=1#comments">' . str_replace(
                        '{count}',
                        $this->showcaseObject->entryData['comments'],
                        $lang->myshowcase_comment_show_all
                    ) . '</a>)' . '<br>';
            }

            $showcase_comment_form_url = SHOWCASE_URL;//.'?action=view&gid='.$mybb->get_input('gid', \MyBB::INPUT_INT);

            $showcase_header_label = '<a name="comments"><form action="' . $this->showcaseObject->showcaseUrl . '" method="post" name="comment">' . $lang->myShowcaseMainTableTheadComments . '</a>';
            $showcase_header_jumpto = $jumptop;
            $showcase_header_special = $showcase_show_all;
            $showcase_comment_header = eval(getTemplate('table_header'));

            $alternativeBackground = ($alternativeBackground == 'trow1' ? 'trow2' : 'trow1');
            if (!$commentsList) {
                $commentsList = eval($this->renderObject->templateGet('pageViewCommentsNone'));
            }

            //check if logged in for ability to add comments
            $alternativeBackground = ($alternativeBackground == 'trow1' ? 'trow2' : 'trow1');
            if (!$currentUserID) {
                $commentsForm = eval($this->renderObject->templateGet('pageViewCommentsFormGuest'));
            } elseif ($this->showcaseObject->userPermissions[UserPermissions::CanAddComments]) {
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

                $commentsForm = eval($this->renderObject->templateGet('pageViewCommentsFormUser'));
            }
        }

        if ($this->showcaseObject->allowAttachments && $this->showcaseObject->userPermissions[UserPermissions::CanViewAttachments]) {
            $attachmentObjects = attachmentGet(
                ["gid='{$this->showcaseObject->entryID}'", "id='{$this->showcaseObject->id}'"],
                ['filename', 'filetype']
            );

            $attach_count = 0;
            $showcase_attachment_data = '';
            foreach ($attachmentObjects as $attachmentID => $attachmentData) {
                //setup default and non-JS enabled URLs
                $item_attachurljs = str_replace('{aid}', $attachmentData['aid'], SHOWCASE_URL_ITEM);
                $item_attachurl = str_replace('{aid}', $attachmentData['aid'], SHOWCASE_URL_VIEW_ATTACH);

                //if mime is image
                if (stristr($attachmentData['filetype'], 'image/')) {
                    //determine what image to use for thumbnail
                    if ($gattachments['thumbnail'] != 'SMALL' && file_exists(
                            $this->showcaseObject->imageFolder . '/' . $gattachments['thumbnail']
                        )) {
                        $item_image = './' . $this->showcaseObject->imageFolder . '/' . $gattachments['thumbnail'];
                    } else {
                        $item_image = $item_attachurljs;
                    }

                    //see if the Fancybox code is being used and if not go back to the actual attachment for the link url
                    if (stripos($showcase_top, '[rel=showcase_images]')) {
                        $item_class = "rel=\\\"showcase_images\\\"";
                    } else {
                        $item_attachurljs = $item_attachurl;
                    }
                } else //it's any other allowed type, so use this
                {
                    $item_class = "class=\\\"attachment\\\"";
                    $attachmentTypes = (array)$cache->read('attachtypes');
                    $attachmentExtension = get_extension($attachmentData['filename']);
                    $item_image = $theme['imgdir'] . '/error.gif';
                    if (array_key_exists($attachmentExtension, $attachmentTypes)) {
                        $item_image = $mybb->settings['bburl'] . '/' . $attachmentTypes[$attachmentExtension]['icon'];
                    }
                }

                $item_alt = $lang->sprintf(
                    $lang->myshowcase_attachment_alt,
                    $gattachments['filename'],
                    $this->showcaseObject->entryData['username']
                );

                $showcase_attachment_data .= eval(getTemplate('view_attachments_image'));

                $attach_count++;
                if ($attach_count == $this->showcaseObject->commentsAttachmentsPerRowLimit && $this->showcaseObject->commentsAttachmentsPerRowLimit != 0) {
                    $showcase_attachment_data .= '<br />';
                    $attach_count = 0;
                } else {
                    $showcase_attachment_data .= '&nbsp;';
                }
            }

            if (substr($showcase_attachment_data, -6) == '&nbsp;') {
                $showcase_attachment_data = substr($showcase_attachment_data, 0, -6);
            }

            $showcase_header_label = '<a name="images">' . $lang->myshowcase_attachments . '</a>';
            $showcase_header_jumpto = $jumptop;
            $showcase_header_special = '';
            $showcase_attachment_header = eval(getTemplate('table_header'));


            if ($showcase_attachment_data != '') {
                $showcase_attachments = eval(getTemplate('view_attachments'));
            } else {
                $showcase_attachments = eval(getTemplate('view_attachments_none'));
            }
        }

        // Update view count
        $db->shutdown_query(
            'UPDATE ' . TABLE_PREFIX . $this->showcaseObject->dataTableName . ' SET views=views+1 WHERE gid=' . $this->showcaseObject->entryID
        );

        $plugins->run_hooks('myshowcase_view_end');

        return eval($this->renderObject->templateGet('pageView'));
    }

    public function report(): void
    {
        global $mybb, $lang, $db, $templates, $plugins;
        global $me, $forumdir;

        global $showcaseObject;

        $currentUserID = (int)$mybb->user['uid'];

        switch ($mybb->get_input('action')) {
            case 'report':
            {
                //load report lang and update with our items
                loadLanguage('report');
                $lang->only_report = $lang->myshowcase_report_warning;
                $lang->report_error = $lang->myshowcase_report_error;
                $lang->report_reason = $lang->myshowcase_report_reason;
                $lang->report_post = $lang->myshowcase_report;
                $lang->only_report = $lang->myshowcase_report_warning;
                $lang->report_to_mod = $lang->myshowcase_report_label;

                add_breadcrumb($lang->myshowcase_report, SHOWCASE_URL);


                if (!$this->showcaseObject->entryID) {
                    error($lang->myshowcase_invalid_id);
                }

                $report_url = SHOWCASE_URL;
                $pageContents = eval(getTemplate('report'));

                break;
            }

            case 'do_report':
            {
                if (!$mybb->request_method == 'post') {
                    error_no_permission();
                }

                //load report lang and update with our items
                loadLanguage('report');
                $lang->report_error = $lang->myshowcase_report_error;
                $lang->post_reported = $lang->myshowcase_report_success;

                verify_post_check($mybb->get_input('my_post_key'));

                if (!trim($mybb->get_input('reason'))) {
                    $report = eval($templates->render('report_noreason'));
                    output_page($report);
                    exit;
                }
                //add_breadcrumb($lang->myshowcase_report, SHOWCASE_URL);

                $showcaseUserData = showcaseDataGet($me->id, ["gid='{$this->showcaseObject->entryID}'"], ['uid']);

                if (!$showcaseUserData) {
                    error($lang->myshowcase_invalid_id);
                }

                $insert_array = [
                    'id' => $me->id,
                    'gid' => $showcaseUserData['gid'],
                    'reporteruid' => $currentUserID,
                    'authoruid' => $showcaseUserData['uid'],
                    'status' => 0,
                    'reason' => $db->escape_string($mybb->get_input('reason')),
                    'dateline' => TIME_NOW
                ];

                $rid = reportInsert($insert_array);

                cacheUpdate(CACHE_TYPE_REPORTS);

                if (!$rid) {
                    $report = eval($templates->render('report_error'));
                    output_page($report);
                    exit;
//			error($lang->myshowcase_report_error);
                } else {
                    $report = eval($templates->render('report_thanks'));
                    output_page($report);
                    exit;
//			$entryUrl = str_replace('{gid}', $mybb->get_input('gid', \MyBB::INPUT_INT), SHOWCASE_URL_VIEW);
//			$redirect_newshowcase = $lang->myshowcase_report_success.''.$lang->redirect_myshowcase_back.''.$lang->sprintf($lang->redirect_myshowcase_return, $showcase_url);
//			redirect($entryUrl, $redirect_newshowcase);
//			exit;
                }
                break;
            }

            case 'reports':
            {
                add_breadcrumb($lang->myshowcase_reports, SHOWCASE_URL);

                if (!$me->userPermissions[ModeratorPermissions::CanEditEntries] && !$me->userPermissions[ModeratorPermissions::CanDeleteEntries] && !$me->userPermissions[ModeratorPermissions::CanDeleteComments]) {
                    error_no_permission();
                }

                loadLanguage('modcp');

                if (!$mybb->settings['threadsperpage']) {
                    $mybb->settings['threadsperpage'] = 20;
                }

                // Figure out if we need to display multiple pages.
                $reportsPerPage = $mybb->settings['threadsperpage'];
                $currentPage = $mybb->get_input('page', MyBB::INPUT_INT); // todo, will be int

                if ($currentPage != 'last') {
                    $page = intval($currentPage);
                }

                $report_count = reportGet(
                    ["status='0'", "id={$me->id}"],
                    ['COUNT(rid) AS totalReports'],
                    ['limit' => 1]
                )['totalReports'] ?? 0;

                $mybb->input['rid'] = intval($mybb->get_input('rid', MyBB::INPUT_INT));

                if ($mybb->get_input('rid', MyBB::INPUT_INT)) {
                    $report_count = reportGet(
                        ["rid<='{$mybb->get_input('rid', MyBB::INPUT_INT)}'", "id={$me->id}"],
                        ['COUNT(rid) AS totalReports'],
                        ['limit' => 1]
                    )['totalReports'] ?? 0;

                    if (($result % $reportsPerPage) == 0) {
                        $page = $result / $reportsPerPage;
                    } else {
                        $page = intval($result / $reportsPerPage) + 1;
                    }
                }
                $postcount = intval($report_count);
                $pages = $postcount / $reportsPerPage;
                $pages = ceil($pages);

                if ($currentPage == 'last') {
                    $page = $pages;
                }

                if ($page > $pages || $page <= 0) {
                    $page = 1;
                }

                if ($page && $page > 0) {
                    $start = ($page - 1) * $reportsPerPage;
                } else {
                    $start = 0;
                    $page = 1;
                }
                $upper = $start + $reportsPerPage;

                $pagination = multipage($postcount, $reportsPerPage, $page, $me->mainFile . '?action=reports');
                if ($postcount > $reportsPerPage) {
                    $reportspages = eval(getTemplate('reports_multipage'));
                }

                $reports = '';
                $query = $db->query(
                    '
			SELECT r.*, u.username as reportername, u.uid as reporteruid, ua.username as authorname, ua.uid as authoruid, c.name, c.mainfile, c.f2gpath
			FROM ' . TABLE_PREFIX . 'myshowcase_reports r
			LEFT JOIN ' . TABLE_PREFIX . 'myshowcase_config c ON (r.id=c.id)
			LEFT JOIN ' . TABLE_PREFIX . 'users u ON (r.reporteruid=u.uid)
			LEFT JOIN ' . TABLE_PREFIX . "users ua ON (r.authoruid=ua.uid)
			WHERE r.status='0' AND r.id={$me->id}
			ORDER BY r.dateline DESC
			LIMIT {$start}, {$reportsPerPage}
		"
                );

                while ($report = $db->fetch_array($query)) {
                    $trow = 'trow_shaded';

                    $report['showcaselink'] = str_replace('{gid}', $report['gid'], SHOWCASE_URL_VIEW);

                    $report['reporterlink'] = build_profile_link(
                        $report['reportername'],
                        $report['reporteruid'],
                        '',
                        '',
                        $forumdir . '/'
                    );
                    $report['authorlink'] = build_profile_link(
                        $report['authorname'],
                        $report['authoruid'],
                        '',
                        '',
                        $forumdir . '/'
                    );

                    $entryReportDate = my_date($mybb->settings['dateformat'], $report['dateline']);
                    $entryReportTime = my_date($mybb->settings['timeformat'], $report['dateline']);
                    $reports .= eval(getTemplate('reports_report'));
                }
                if (!$reports) {
                    $reports = eval($templates->render('modcp_reports_noreports'));
                }

                $showcase_file = SHOWCASE_URL;
                $pageContents = eval(getTemplate('reports'));

                break;
            }

            case 'do_reports':
            {
                if (!$mybb->request_method == 'post') {
                    error_no_permission();
                }

                loadLanguage('modcp');

                // Verify incoming POST request
                verify_post_check($mybb->get_input('my_post_key'));

                if (!is_array($mybb->get_input('reports', MyBB::INPUT_ARRAY))) {
                    error($lang->error_noselected_reports);
                }

                $mybb->input['reports'] = array_map(
                    'intval',
                    $mybb->get_input('reports', MyBB::INPUT_ARRAY)
                );
                $rids = implode($mybb->get_input('reports', MyBB::INPUT_ARRAY), "','");
                $rids = "'0','{$rids}'";

                reportUpdate(['status' => 1], ["rid IN ({$rids})", "id={$me->id}"]);

                $page = intval($currentPage);

                redirect(SHOWCASE_URL . "?action=reports&page={$page}", $lang->redirect_reportsmarked);

                break;
            }

            case 'allreports':
            {
                add_breadcrumb($lang->myshowcase_reports, SHOWCASE_URL);

                if (!$me->userPermissions[ModeratorPermissions::CanEditEntries] && !$me->userPermissions[ModeratorPermissions::CanDeleteEntries] && !$me->userPermissions[ModeratorPermissions::CanDeleteComments]) {
                    error_no_permission();
                }

                loadLanguage('modcp');

                if (!$mybb->settings['threadsperpage']) {
                    $mybb->settings['threadsperpage'] = 20;
                }

                // Figure out if we need to display multiple pages.
                $reportsPerPage = $mybb->settings['threadsperpage'];
                if ($currentPage != 'last') {
                    $page = intval($currentPage);
                }

                $report_count = reportGet(
                    ["id='{$me->id}'"],
                    ['COUNT(rid) AS totalReports'],
                    ['limit' => 1]
                )['totalReports'] ?? 0;

                $mybb->input['rid'] = intval($mybb->get_input('rid', MyBB::INPUT_INT));

                if ($mybb->get_input('rid', MyBB::INPUT_INT)) {
                    $totalReports = reportGet(
                        ["rid<='{$mybb->get_input('rid', MyBB::INPUT_INT)}'", "id={$me->id}"],
                        ['COUNT(rid) AS totalReports'],
                        ['limit' => 1]
                    )['totalReports'] ?? 0;

                    if (($totalReports % $reportsPerPage) == 0) {
                        $page = $totalReports / $reportsPerPage;
                    } else {
                        $page = intval($totalReports / $reportsPerPage) + 1;
                    }
                }
                $postcount = intval($report_count);
                $pages = $postcount / $reportsPerPage;
                $pages = ceil($pages);

                if ($currentPage == 'last') {
                    $page = $pages;
                }

                if ($page > $pages || $page <= 0) {
                    $page = 1;
                }

                if ($page && $page > 0) {
                    $start = ($page - 1) * $reportsPerPage;
                } else {
                    $start = 0;
                    $page = 1;
                }
                $upper = $start + $reportsPerPage;

                $pagination = multipage($postcount, $reportsPerPage, $page, $me->mainFile . '?action=allreports');
                if ($postcount > $reportsPerPage) {
                    $reportspages = eval(getTemplate('reports_multipage'));
                }

                $reports = '';
                $query = $db->query(
                    '
			SELECT r.*, u.username as reportername, u.uid as reporteruid, ua.username as authorname, ua.uid as authoruid, c.name, c.mainfile, c.f2gpath
			FROM ' . TABLE_PREFIX . 'myshowcase_reports r
			LEFT JOIN ' . TABLE_PREFIX . 'myshowcase_config c ON (r.id=c.id)
			LEFT JOIN ' . TABLE_PREFIX . 'users u ON (r.reporteruid=u.uid)
			LEFT JOIN ' . TABLE_PREFIX . "users ua ON (r.authoruid=ua.uid)
			WHERE  r.id={$me->id}
			ORDER BY r.dateline DESC
			LIMIT {$start}, {$reportsPerPage}
		"
                );

                while ($report = $db->fetch_array($query)) {
                    if ($report['status'] == 0) {
                        $trow = 'trow_shaded';
                    } else {
                        $trow = alt_trow();
                    }

                    $report['showcaselink'] = str_replace('{gid}', $report['gid'], SHOWCASE_URL_VIEW);

                    $report['reporterlink'] = build_profile_link(
                        $report['reportername'],
                        $report['reporteruid'],
                        '',
                        '',
                        $forumdir . '/'
                    );
                    $report['authorlink'] = build_profile_link(
                        $report['authorname'],
                        $report['authoruid'],
                        '',
                        '',
                        $forumdir . '/'
                    );

                    $entryReportDate = my_date($mybb->settings['dateformat'], $report['dateline']);
                    $entryReportTime = my_date($mybb->settings['timeformat'], $report['dateline']);
                    $reports .= eval(getTemplate('reports_allreport'));
                }
                if (!$reports) {
                    $reports = eval($templates->render('modcp_reports_noreports'));
                }

                $showcase_file = SHOWCASE_URL;
                $pageContents = eval(getTemplate('allreports'));

                break;
            }
        }
    }

    public function commentPost(): void
    {
        if (!$currentUserID) {
            error($lang->myshowcase_comments_not_logged_in);
        }

        if ($this->showcaseObject->userPermissions[UserPermissions::CanAddComments] && $mybb->request_method == 'post') {
            verify_post_check($mybb->get_input('my_post_key'));

            $plugins->run_hooks('myshowcase_add_comment_start');


            if (!$this->showcaseObject->entryID) {
                error($lang->myshowcase_invalid_id);
            }

            if ($mybb->get_input('comments') == '') {
                error($lang->myshowcase_comment_empty);
            }

            $showcaseUserData = showcaseDataGet(
                $this->showcaseObject->id,
                ["gid='{$this->showcaseObject->entryID}'"],
                ['uid']
            );

            if (!$showcaseUserData) {
                error($lang->myshowcase_invalid_id);
            }

            $authorid = $showcaseUserData['uid'];

            //don't trust the myshowcase_data comment count, get the real count at time of insert to cover deletions and edits at same time.
            $totalComments = commentGet(
                ["gid='{$this->showcaseObject->entryID}'", "id='{$this->showcaseObject->id}'"],
                ['COUNT(cid) AS totalComments'],
                ['group_by' => 'gid'],
                ['group_by' => 'gid', 'limit' => 1]
            )['totalComments'] ?? 0;

            $mybb->input['comments'] = $db->escape_string($mybb->get_input('comments'));

            if ($mybb->get_input('comments') != '') {
                $comment_insert_data = [
                    'id' => $this->showcaseObject->id,
                    'gid' => $this->showcaseObject->entryID,
                    'uid' => $currentUserID,
                    'ipaddress' => $mybb->session->packedip,
                    'comment' => '[b]Hi there[/b] just a comment :D',
                    'dateline' => TIME_NOW
                ];

                $plugins->run_hooks('myshowcase_add_comment_commit');

                $commentID = commentInsert($comment_insert_data);

                $commentID = $this->showcaseObject->entryID;

                showcaseDataUpdate(
                    $this->showcaseObject->id,
                    $this->showcaseObject->entryID,
                    ['comments' => $totalComments + 1]
                );

                //notify showcase owner of new comment by others
                $author = get_user($authorid);
                if ($author['allownotices'] && (int)$author['uid'] !== $currentUserID) {
                    $excerpt = postParser()->text_parse_message(
                        $mybb->get_input('comments'),
                        ['me_username' => $mybb->user['username']]
                    );

                    $excerpt = my_substr(
                            $excerpt,
                            0,
                            $mybb->settings['subscribeexcerpt']
                        ) . $lang->myshowcase_comment_more;

                    $entryUrl = str_replace('{gid}', $mybb->get_input('gid'), SHOWCASE_URL_VIEW);

                    if ($forumdir == '' || $forumdir == './') {
                        $showcase_url = $mybb->settings['bburl'] . '/' . $entryUrl;
                    } else {
                        $forumdir = str_replace('.', '', $forumdir);
                        $showcase_url = str_replace($forumdir, '', $mybb->settings['bburl']) . '/' . $entryUrl;
                    }


                    $emailsubject = $lang->sprintf($lang->myshowcase_comment_emailsubject, $this->showcaseObject->name);

                    $emailmessage = $lang->sprintf(
                        $lang->myshowcase_comment_email,
                        $author['username'],
                        $mybb->user['username'],
                        $this->showcaseObject->name,
                        $excerpt,
                        $showcase_url,
                        $mybb->settings['bbname'],
                        $mybb->settings['bburl']
                    );

                    $new_email = [
                        'mailto' => $db->escape_string($author['email']),
                        'mailfrom' => '',
                        'subject' => $db->escape_string($emailsubject),
                        'message' => $db->escape_string($emailmessage),
                        'headers' => ''
                    ];

                    $db->insert_query('mailqueue', $new_email);
                    $cache->update_mailqueue();
                }

                $entryUrl = str_replace('{gid}', (string)$this->showcaseObject->entryID, SHOWCASE_URL_VIEW);

                redirect($entryUrl . '#comments', $lang->myshowcase_comment_added);
            }
        } else {
            error($lang->myshowcase_not_authorized);
        }
    }

    public function entryDelete(): void
    {
        global $mybb;

        if ($mybb->request_method == 'post') {
            verify_post_check($mybb->get_input('my_post_key'));

            if (!$currentUserID || !$this->showcaseObject->userPermissions[UserPermissions::CanEditEntries]) {
                error($lang->myshowcase_not_authorized);
            }

            $plugins->run_hooks('myshowcase_delete_start');


            if (!$this->showcaseObject->entryID) {
                error($lang->myshowcase_invalid_id);
            }

            $dataTableStructure = dataTableStructureGet();

            $showcaseUserData = showcaseDataGet(
                $this->showcaseObject->id,
                ["gid='{$this->showcaseObject->entryID}'"],
                array_keys($dataTableStructure)
            );

            if (!$showcaseUserData) {
                error($lang->myshowcase_invalid_id);
            }

            if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries] && $currentUserID !== (int)$showcaseUserData['uid']) {
                error($lang->myshowcase_not_authorized);
            }

            $gid = $showcaseUserData['gid'];
            $this->showcaseObject->entryDelete($gid);

            //log_moderator_action($modlogdata, $lang->multi_deleted_threads);

            $plugins->run_hooks('myshowcase_delete_end');
        }

        redirect(SHOWCASE_URL, $lang->redirect_myshowcase_delete);

        exit;
    }

    public function commentDelete(): void
    {
        $plugins->run_hooks('myshowcase_del_comment_start');

        if (!$commentID) {
            error($lang->myshowcase_invalid_cid);
        }

        $commentData = commentGet(
            ["cid='{$commentID}"],
            ['uid', 'gid'],
            ['limit' => 1]
        );

        if (!$commentData) {
            error($lang->myshowcase_invalid_cid);
        }

        $entryID = (int)$commentData['gid'];

        $entryData = showcaseDataGet($this->showcaseObject->id, ["gid='{$entryID}'"], ['uid']);

        if (!$entryData) {
            error($lang->myshowcase_invalid_id);
        }

        if (
            (($currentUserID === (int)$commentData['uid'] && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteComments]) ||
                ($currentUserID === (int)$entryData['uid'] && $this->showcaseObject->userPermissions[UserPermissions::CanDeleteAuthorComments]) ||
                ($this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteComments]) && $mybb->request_method == 'post')
        ) {
            verify_post_check($mybb->get_input('my_post_key'));

            commentsDelete(["id='{$this->showcaseObject->id}'", "cid='{$commentID}'"]);

            $totalComments = commentGet(
                ["gid='{$entryID}'", "id='{$this->showcaseObject->id}'"],
                ['COUNT(cid) AS totalComments'],
                ['group_by' => 'gid', 'limit' => 1]
            )['totalComments'] ?? 0;

            $plugins->run_hooks('myshowcase_del_comment_commit');

            showcaseDataUpdate($this->showcaseObject->id, $entryID, ['comments' => $totalComments]);

            $entryUrl = str_replace('{gid}', (string)$entryID, SHOWCASE_URL_VIEW);

            redirect($entryUrl . '#comments', $lang->myshowcase_comment_deleted);
        } else {
            error($lang->myshowcase_not_authorized);
        }
    }

    #[NoReturn] public function entryPost(bool $isEditPage = false): void
    {
        global $lang, $mybb, $db, $cache;
        global $header, $headerinclude, $footer, $theme;

        global $showcaseFieldsOrder, $showcaseFieldsSearchable, $currentPage, $amp, $urlSort, $showcaseInputSortBy;
        global $showcaseFieldsShow, $showcaseFields, $unapproved;
        global $urlParams, $urlBase, $showcaseInputSearchField, $showcaseColumnsCount;
        global $showcaseFieldsFormat, $showcaseName, $showcaseTableTheadInlineModeration, $showcase_url;
        global $showcaseInlineModeration, $buttonGo, $orderInput, $showcaseInputOrder, $showcaseFieldsParseable;

        global $entryFieldsData, $entryHash, $showcaseFieldEnabled;
        global $showcaseFieldsMaximumLength, $showcaseFieldsMinimumLength, $showcaseFieldsRequired;

        global $mybb, $lang, $db, $templates, $plugins;
        global $showcaseFieldsMaximumLength, $showcaseFieldsFormat, $showcaseFieldsRequired, $showcaseFieldsMinimumLength, $showcase_url;

        global $entryHash, $entryFieldsData;

        $currentUserID = (int)$mybb->user['uid'];

        $hookArguments = [];

        $entryUserData = get_user($this->showcaseObject->entryUserID);

        $showcaseUserPermissions = $this->showcaseObject->userPermissionsGet($this->showcaseObject->entryUserID);

        if ($mybb->request_method === 'post') {
            verify_post_check($mybb->get_input('my_post_key'));

            if ($isEditPage) {
                if (!$entryUserData) {
                    error($lang->myshowcase_invalid_id);
                }
            } else {
                if ($mybb->get_input('action') == 'new') {
                    add_breadcrumb($lang->myShowcaseButtonNewEntry, SHOWCASE_URL);
                    $showcase_action = 'new';

                    //need to populated a default user value here for new entries
                    $entryFieldsData['uid'] = $currentUserID;
                } else {
                    $showcase_editing_user = str_replace(
                        '{username}',
                        $entryUserData['username'],
                        $lang->myshowcase_editing_user
                    );
                    add_breadcrumb($showcase_editing_user, SHOWCASE_URL);
                    $showcase_action = 'edit';
                }

                // Get a listing of the current attachments.
                if (!empty($showcaseUserPermissions[UserPermissions::CanAttachFiles])) {
                    $attachcount = 0;

                    $attachments = '';

                    $attachmentObjects = attachmentGet(
                        ["posthash='{$db->escape_string($entryHash)}'", "id={$this->showcaseObject->id}"],
                        array_keys(TABLES_DATA['myshowcase_attachments'])
                    );

                    foreach ($attachmentObjects as $attachmentID => $attachment) {
                        $attachment['size'] = get_friendly_size($attachment['filesize']);
                        $attachment['icon'] = get_attachment_icon(get_extension($attachment['filename']));
                        $attachment['icon'] = str_replace(
                            '<img src="',
                            '<img src="' . $mybb->settings['bburl'] . '/',
                            $attachment['icon']
                        );


                        $attach_mod_options = '';
                        if ($attachment['visible'] != 1) {
                            $attachments .= eval(getTemplate('new_attachments_attachment_unapproved'));
                        } else {
                            $attachments .= eval(getTemplate('new_attachments_attachment'));
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
                            $showcase_watermark = eval(getTemplate('watermark'));
                        }
                        $showcase_new_attachments_input = eval(getTemplate('new_attachments_input'));
                    }
                    $showcase_attachments = eval(getTemplate('new_attachments'));
                }

                if ($mybb->request_method == 'post' && $mybb->get_input('submit')) {
                    // Decide on the visibility of this post.
                    if ($this->showcaseObject->moderateEdits && !$this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries]) {
                        $approved = 0;
                        $approved_by = 0;
                    } else {
                        $approved = 1;
                        $approved_by = $currentUserID;
                    }

                    $plugins->run_hooks('myshowcase_do_newedit_start');

                    // Set up showcase handler.
                    require_once MYBB_ROOT . 'inc/datahandlers/myshowcase_dh.php';
                    if ($mybb->get_input('action') == 'edit') {
                        $showcasehandler = new MyShowcaseDataHandler($this->showcaseObject, 'update');
                        $showcasehandler->action = 'edit';
                    } else {
                        $showcasehandler = new MyShowcaseDataHandler($this->showcaseObject);
                        $showcasehandler->action = 'new';
                    }

                    //This is where the work is done

                    // Verify incoming POST request
                    verify_post_check($mybb->get_input('my_post_key'));

                    // Set the post data that came from the input to the $post array.
                    $default_data = [
                        'uid' => $entryFieldsData['uid'],
                        'dateline' => TIME_NOW,
                        'approved' => $approved,
                        'approved_by' => $approved_by,
                        'posthash' => $entryHash
                    ];

                    //add showcase id if editing so we know what to update
                    if ($mybb->get_input('action') == 'edit') {
                        $default_data = array_merge(
                            $default_data,
                            ['gid' => $this->showcaseObject->entryData]
                        );
                    }

                    //add showcase specific fields
                    reset($showcaseFieldEnabled);
                    $submitted_data = [];
                    foreach ($showcaseFieldEnabled as $fname => $ftype) {
                        if ($ftype == FIELD_TYPE_HTML_DB || $ftype == FIELD_TYPE_HTML_RADIO) {
                            $submitted_data[$fname] = intval($mybb->get_input('myshowcase_field_' . $fname));
                        } elseif ($ftype == FIELD_TYPE_HTML_CHECK_BOX) {
                            $submitted_data[$fname] = (isset($mybb->input['myshowcase_field_' . $fname]) ? 1 : 0);
                        } elseif ($ftype == FIELD_TYPE_HTML_DATE) {
                            $m = $db->escape_string($mybb->get_input('myshowcase_field_' . $fname . '_m'));
                            $d = $db->escape_string($mybb->get_input('myshowcase_field_' . $fname . '_d'));
                            $y = $db->escape_string($mybb->get_input('myshowcase_field_' . $fname . '_y'));
                            $submitted_data[$fname] = $m . '|' . $d . '|' . $y;
                        } else {
                            $submitted_data[$fname] = $db->escape_string(
                                $mybb->get_input('myshowcase_field_' . $fname)
                            );
                        }
                    }

                    //redefine the showcase_data
                    $entryFieldsData = array_merge($default_data, $submitted_data);

                    //send data to handler
                    $showcasehandler->set_data($entryFieldsData);

                    // Now let the showcase handler do all the hard work.
                    $valid_showcase = $showcasehandler->validate_showcase();

                    $showcase_errors = [];

                    // Fetch friendly error messages if this is an invalid showcase
                    if (!$valid_showcase) {
                        $showcase_errors = $showcasehandler->get_friendly_errors();
                    }
                    if (count($showcase_errors) > 0) {
                        $error = inline_error($showcase_errors);
                        $pageContents = eval($templates->render('error'));
                    } else {
                        //update showcase
                        if ($mybb->get_input('action') == 'edit') {
                            $insert_showcase = $showcasehandler->update_showcase();
                            $showcaseid = $this->showcaseObject->entryData;
                        } //insert showcase
                        else {
                            $insert_showcase = $showcasehandler->insert_showcase();
                            $showcaseid = $insert_showcase['gid'];
                        }

                        $plugins->run_hooks('myshowcase_do_newedit_end');

                        //fix url insert variable to update results
                        $entryUrl = str_replace('{gid}', (string)$showcaseid, SHOWCASE_URL_VIEW);

                        $redirect_newshowcase = $lang->redirect_myshowcase_new . '' . $lang->redirect_myshowcase . '' . $lang->sprintf(
                                $lang->redirect_myshowcase_return,
                                $showcase_url
                            );
                        redirect($entryUrl, $redirect_newshowcase);
                        exit;
                    }
                } else {
                    $plugins->run_hooks('myshowcase_newedit_start');

                    $pageContents .= eval(getTemplate('new_top'));

                    reset($showcaseFieldEnabled);
                    foreach ($showcaseFieldEnabled as $fname => $ftype) {
                        $temp = 'myshowcase_field_' . $fname;
                        $field_header = !empty($lang->$temp) ? $lang->$temp : $fname;

                        $alternativeBackground = ($alternativeBackground == 'trow1' ? 'trow2' : 'trow1');

                        if ($mybb->get_input('action') == 'edit') {
                            $mybb->input['myshowcase_field_' . $fname] = htmlspecialchars_uni(
                                stripslashes($entryFieldsData[$fname])
                            );
                        }

                        switch ($ftype) {
                            case FIELD_TYPE_HTML_TEXT_BOX:
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fname;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                                $showcase_field_enabled = '';//($showcaseFieldEnabled[$fname] != 1 ? 'disabled' : '');
                                $showcase_field_checked = '';
                                $showcase_field_options = 'maxlength="' . $showcaseFieldsMaximumLength[$fname] . '"';
                                $showcase_field_input = eval(getTemplate('field_textbox'));

                                if ($showcaseFieldsFormat[$fname] !== FORMAT_TYPE_NONE) {
                                    $showcase_field_input .= '&nbsp;' . $lang->myshowcase_editing_number;
                                }
                                break;

                            case FIELD_TYPE_HTML_URL:
                                $showcase_field_width = 150;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fname;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                                $showcase_field_enabled = '';//($showcaseFieldEnabled[$fname] != 1 ? 'disabled' : '');
                                $showcase_field_checked = '';
                                $showcase_field_options = 'maxlength="' . $showcaseFieldsMaximumLength[$fname] . '"';
                                $showcase_field_input = eval(getTemplate('field_textbox'));
                                break;

                            case 'textarea':
                                $showcase_field_width = 100;
                                $showcase_field_rows = $showcaseFieldsMaximumLength[$fname];
                                $showcase_field_name = 'myshowcase_field_' . $fname;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                                $showcase_field_enabled = '';//($showcaseFieldEnabled[$fname] != 1 ? 'disabled' : '');
                                $showcase_field_checked = '';
                                $showcase_field_options = '';
                                $showcase_field_input = eval(getTemplate('field_textarea'));
                                break;

                            case FIELD_TYPE_HTML_RADIO:
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fname;
                                $showcase_field_enabled = '';//($showcaseFieldEnabled[$fname] != 1 ? 'disabled' : '');
                                $showcase_field_options = '';

                                $fieldDataObjects = fieldDataGet(
                                    ["setid='{$this->showcaseObject->fieldSetID}'", "name='{$fname}'", "valueid!='0'"],
                                    ['valueid', 'value'],
                                    ['order_by' => 'disporder']
                                );

                                if (!$fieldDataObjects) {
                                    error($lang->myshowcase_db_no_data);
                                }

                                $showcase_field_input = '';
                                foreach ($fieldDataObjects as $fieldDataID => $results) {
                                    $showcase_field_value = $results['valueid'];
                                    $showcase_field_checked = ($mybb->get_input(
                                        'myshowcase_field_' . $fname
                                    ) == $results['valueid'] ? ' checked' : '');
                                    $showcase_field_text = $results['value'];
                                    $showcase_field_input .= eval(getTemplate('field_radio'));
                                }
                                break;

                            case FIELD_TYPE_HTML_CHECK_BOX:
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fname;
                                $showcase_field_value = '1';
                                $showcase_field_enabled = '';//($showcaseFieldEnabled[$fname] != 1 ? 'disabled' : '');
                                $showcase_field_checked = ($mybb->get_input(
                                    'myshowcase_field_' . $fname
                                ) == 1 ? ' checked="checked"' : '');
                                $showcase_field_options = '';
                                $showcase_field_input = eval(getTemplate('field_checkbox'));
                                break;

                            case FIELD_TYPE_HTML_DB:
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fname;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                                $showcase_field_enabled = '';//($showcaseFieldEnabled[$fname] != 1 ? 'disabled' : '');
                                $showcase_field_checked = '';

                                $fieldDataObjects = fieldDataGet(
                                    ["setid='{$this->showcaseObject->fieldSetID}'", "name='{$fname}'", "valueid!='0'"],
                                    ['valueid', 'value'],
                                    ['order_by' => 'disporder']
                                );

                                if (!$fieldDataObjects) {
                                    error($lang->myshowcase_db_no_data);
                                }

                                $showcase_field_options = ($mybb->get_input(
                                    'action'
                                ) == 'new' ? '<option value=0>&lt;Select&gt;</option>' : '');
                                foreach ($fieldDataObjects as $fieldDataID => $results) {
                                    $showcase_field_options .= '<option value="' . $results['valueid'] . '" ' . ($showcase_field_value == $results['valueid'] ? ' selected' : '') . '>' . $results['value'] . '</option>';
                                }
                                $showcase_field_input = eval(getTemplate('field_db'));
                                break;

                            case FIELD_TYPE_HTML_DATE:
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fname;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fname);
                                $showcase_field_enabled = '';//($showcaseFieldEnabled[$fname] != 1 ? 'disabled' : '');
                                $showcase_field_checked = '';

                                $showcase_field_value_m = ($mybb->get_input(
                                    'myshowcase_field_' . $fname . '_m'
                                ) == '00' ? '00' : $mybb->get_input('myshowcase_field_' . $fname . '_m'));
                                $showcase_field_value_d = ($mybb->get_input(
                                    'myshowcase_field_' . $fname . '_d'
                                ) == '00' ? '00' : $mybb->get_input('myshowcase_field_' . $fname . '_d'));
                                $showcase_field_value_y = ($mybb->get_input(
                                    'myshowcase_field_' . $fname . '_y'
                                ) == '0000' ? '0000' : $mybb->get_input('myshowcase_field_' . $fname . '_y'));

                                $showcase_field_options_m = '<option value=0' . ($showcase_field_value_m == '00' ? ' selected' : '') . '>&nbsp;</option>';
                                $showcase_field_options_d = '<option value=0' . ($showcase_field_value_d == '00' ? ' selected' : '') . '>&nbsp;</option>';
                                $showcase_field_options_y = '<option value=0' . ($showcase_field_value_y == '0000' ? ' selected' : '') . '>&nbsp;</option>';
                                for ($i = 1; $i <= 12; $i++) {
                                    $showcase_field_options_m .= '<option value="' . substr(
                                            '0' . $i,
                                            -2
                                        ) . '" ' . ($showcase_field_value_m == substr(
                                            '0' . $i,
                                            -2
                                        ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                                }
                                for ($i = 1; $i <= 31; $i++) {
                                    $showcase_field_options_d .= '<option value="' . substr(
                                            '0' . $i,
                                            -2
                                        ) . '" ' . ($showcase_field_value_d == substr(
                                            '0' . $i,
                                            -2
                                        ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                                }
                                for ($i = $showcaseFieldsMaximumLength[$fname]; $i >= $showcaseFieldsMinimumLength[$fname]; $i--) {
                                    $showcase_field_options_y .= '<option value="' . $i . '" ' . ($showcase_field_value_y == $i ? ' selected' : '') . '>' . $i . '</option>';
                                }
                                $showcase_field_input = eval(getTemplate('field_date'));
                                break;
                        }

                        $field_header = ($showcaseFieldsRequired[$fname] ? '<strong>' . $field_header . ' *</strong>' : $field_header);
                        $pageContents .= eval(getTemplate('new_fields'));
                    }

                    $plugins->run_hooks('myshowcase_newedit_end');

                    $pageContents .= eval(getTemplate('new_bottom'));
                }
            }
        }

        if ($mybb->get_input('action') == 'new') {
            add_breadcrumb($lang->myShowcaseButtonNewEntry, SHOWCASE_URL);
        } elseif ($mybb->get_input('action') == 'edit') {
            $showcase_editing_user = str_replace(
                '{username}',
                $entryUserData['username'],
                $lang->myshowcase_editing_user
            );
            add_breadcrumb($showcase_editing_user, SHOWCASE_URL);
        }

        if ($isEditPage) {
            if (!$this->showcaseObject->userPermissions[UserPermissions::CanEditEntries]) {
                error($lang->myshowcase_not_authorized);
            }
        } elseif (!$this->showcaseObject->userPermissions[UserPermissions::CanAddEntries]) {
            error($lang->myshowcase_not_authorized);
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
                ["posthash='{$db->escape_string($entryHash)}'"],
                array_keys(TABLES_DATA['myshowcase_attachments'])
            );

            foreach ($attachmentObjects as $attachmentID => $attachment) {
                $attachment['size'] = get_friendly_size($attachment['filesize']);
                $attachment['icon'] = get_attachment_icon(get_extension($attachment['filename']));
                $attachment['icon'] = str_replace(
                    '<img src="',
                    '<img src="' . $mybb->settings['bburl'] . '/',
                    $attachment['icon']
                );

                $attach_mod_options = '';
                if ($attachment['visible'] != 1) {
                    $attachments .= eval(getTemplate('new_attachments_attachment_unapproved'));
                } else {
                    $attachments .= eval(getTemplate('new_attachments_attachment'));
                }
                $attachcount++;
            }
            $lang->myshowcase_attach_quota = $lang->sprintf(
                    $lang->myshowcase_attach_quota,
                    $attachcount,
                    ($showcaseUserPermissions[UserPermissions::AttachmentsLimit] === ATTACHMENT_UNLIMITED ? $lang->myshowcase_unlimited : $showcaseUserPermissions[UserPermissions::AttachmentsLimit])
                ) . '<br>';
            if ($showcaseUserPermissions[UserPermissions::AttachmentsLimit] === ATTACHMENT_UNLIMITED || ($showcaseUserPermissions[UserPermissions::AttachmentsLimit] !== ATTACHMENT_UNLIMITED && $attachcount < $showcaseUserPermissions[UserPermissions::AttachmentsLimit])) {
                if ($this->showcaseObject->userPermissions[UserPermissions::CanWaterMarkAttachments] && $this->showcaseObject->waterMarkImage != '' && file_exists(
                        $this->showcaseObject->waterMarkImage
                    )) {
                    $showcase_watermark = eval(getTemplate('watermark'));
                }
                $showcase_new_attachments_input = eval(getTemplate('new_attachments_input'));
            }

            if (!empty($showcase_new_attachments_input) || $attachments != '') {
                $attachmentsTable = eval(getTemplate('pageNewAttachments'));
            }
        }

        $alternativeBackground = 'trow2';

        reset($showcaseFieldEnabled);

        $showcaseFields = '';

        $fieldTabIndex = 1;

        foreach ($showcaseFieldEnabled as $fieldName => $fieldType) {
            $fieldKey = "myshowcase_field_{$fieldName}";

            $fieldTitle = $fieldName;

            if (isset($lang->{$fieldKey})) {
                $fieldTitle = $lang->{$fieldKey};
            }

            $alternativeBackground = ($alternativeBackground == 'trow1' ? 'trow2' : 'trow1');

            if ($mybb->get_input('action') == 'edit') {
                _dump(147, $entryFieldsData);
                $mybb->input[$fieldKey] = htmlspecialchars_uni(
                    stripslashes($entryFieldsData[$fieldName])
                );
            }

            $fieldElementRequired = '';

            if (isset($showcaseFieldsRequired[$fieldName])) {
                $fieldElementRequired = 'required="required"';
            }

            $minimumLength = $showcaseFieldsMinimumLength[$fieldName] ?? '';

            $maximumLength = $showcaseFieldsMaximumLength[$fieldName] ?? '';

            switch ($fieldType) {
                case FIELD_TYPE_HTML_TEXT_BOX:
                    $fieldValue = $mybb->get_input($fieldKey);

                    $fieldInput = eval(getTemplate('pageNewFieldTextBox'));
                    break;
                case FIELD_TYPE_HTML_URL:
                    $fieldValue = $mybb->get_input($fieldKey);

                    $fieldInput = eval(getTemplate('pageNewFieldUrl'));
                    break;
                case FIELD_TYPE_HTML_TEXTAREA:
                    $fieldValue = $mybb->get_input($fieldKey);

                    $fieldInput = eval(getTemplate('pageNewFieldTextArea'));
                    break;
                case FIELD_TYPE_HTML_RADIO:
                    $showcase_field_width = 50;
                    $showcase_field_rows = '';
                    $showcase_field_enabled = '';// ($showcaseFieldEnabled[$fieldName] != 1 ? 'disabled' : '');
                    $showcase_field_options = '';

                    $fieldObjects = fieldDataGet(
                        ["setid='{$this->showcaseObject->fieldSetID}'", "name='{$fieldName}'", "valueid!='0'"],
                        ['valueid', 'value'],
                        ['order_by' => 'disporder']
                    );

                    if (!$fieldObjects) {
                        error($lang->myshowcase_db_no_data);
                    }

                    $fieldInput = '';

                    foreach ($fieldObjects as $fieldDataID => $fieldData) {
                        $fieldValue = $fieldData['valueid'];

                        $showcase_field_checked = ($mybb->get_input(
                            $fieldKey
                        ) == $fieldData['valueid'] ? ' checked' : '');

                        $showcase_field_text = $fieldData['value'];

                        $fieldInput .= eval(getTemplate('field_radio'));
                    }

                    break;

                case FIELD_TYPE_HTML_CHECK_BOX:
                    $showcase_field_width = 50;
                    $showcase_field_rows = '';
                    $fieldValue = '1';

                    $showcase_field_checked = ($mybb->get_input($fieldKey) == 1 ? ' checked="checked"' : '');
                    $showcase_field_options = '';
                    $fieldInput = eval(getTemplate('field_checkbox'));
                    break;

                case FIELD_TYPE_HTML_DB:
                    $showcase_field_width = 50;
                    $showcase_field_rows = '';
                    $fieldValue = $mybb->get_input($fieldKey);

                    $showcase_field_checked = '';

                    $fieldDataObjects = fieldDataGet(
                        ["setid='{$this->showcaseObject->fieldSetID}'", "name='{$fieldName}'", "valueid!='0'"],
                        ['valueid', 'value'],
                        ['order_by' => 'disporder']
                    );

                    if (!$fieldDataObjects) {
                        error($lang->myshowcase_db_no_data);
                    }

                    $showcase_field_options = ($mybb->get_input(
                        'action'
                    ) == 'new' ? '<option value=0>&lt;Select&gt;</option>' : '');
                    foreach ($fieldDataObjects as $fieldDataID => $results) {
                        $showcase_field_options .= '<option value="' . $results['valueid'] . '" ' . ($fieldValue == $results['valueid'] ? ' selected' : '') . '>' . $results['value'] . '</option>';
                    }
                    $fieldInput = eval(getTemplate('field_db'));
                    break;

                case FIELD_TYPE_HTML_DATE:
                    $showcase_field_width = 50;
                    $showcase_field_rows = '';
                    $fieldValue = $mybb->get_input($fieldKey);

                    $showcase_field_checked = '';

                    $date_bits = explode('|', $fieldValue);
                    $mybb->input[$fieldKey . '_m'] = $date_bits[0];
                    $mybb->input[$fieldKey . '_d'] = $date_bits[1];
                    $mybb->input[$fieldKey . '_y'] = $date_bits[2];

                    $showcase_field_value_m = ($mybb->get_input(
                        $fieldKey . '_m'
                    ) == '00' ? '00' : $mybb->get_input($fieldKey . '_m'));
                    $showcase_field_value_d = ($mybb->get_input(
                        $fieldKey . '_d'
                    ) == '00' ? '00' : $mybb->get_input($fieldKey . '_d'));
                    $showcase_field_value_y = ($mybb->get_input(
                        $fieldKey . '_y'
                    ) == '0000' ? '0000' : $mybb->get_input($fieldKey . '_y'));

                    $showcase_field_options_m = '<option value=00' . ($showcase_field_value_m == '00' ? ' selected' : '') . '>&nbsp;</option>';
                    $showcase_field_options_d = '<option value=00' . ($showcase_field_value_d == '00' ? ' selected' : '') . '>&nbsp;</option>';
                    $showcase_field_options_y = '<option value=0000' . ($showcase_field_value_y == '0000' ? ' selected' : '') . '>&nbsp;</option>';
                    for ($i = 1; $i <= 12; $i++) {
                        $showcase_field_options_m .= '<option value="' . substr(
                                '0' . $i,
                                -2
                            ) . '" ' . ($showcase_field_value_m == substr(
                                '0' . $i,
                                -2
                            ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                    }
                    for ($i = 1; $i <= 31; $i++) {
                        $showcase_field_options_d .= '<option value="' . substr(
                                '0' . $i,
                                -2
                            ) . '" ' . ($showcase_field_value_d == substr(
                                '0' . $i,
                                -2
                            ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                    }
                    for ($i = $showcaseFieldsMaximumLength[$fieldName]; $i >= $showcaseFieldsMinimumLength[$fieldName]; $i--) {
                        $showcase_field_options_y .= '<option value="' . $i . '" ' . ($showcase_field_value_y == $i ? ' selected' : '') . '>' . $i . '</option>';
                    }
                    $fieldInput = eval(getTemplate('field_date'));
                    break;
            }

            $showcaseFields .= eval(getTemplate('pageNewField'));

            ++$fieldTabIndex;
        }

        $pageTitle = $this->showcaseObject->name;

        $pageContents = eval(getTemplate('pageNewContents'));

        $pageContents = eval(getTemplate('page'));

        $hookArguments = hooksRun('output_new_end', $hookArguments);

        output_page($pageContents);

        exit;
    }

    public function inlineModeration(): void
    {
        global $mybb, $lang, $db, $templates, $plugins;
        global $me;

        global $showcaseInputOrder, $showcaseInputSortBy, $currentPage;

        $currentUserID = (int)$mybb->user['uid'];

        switch ($mybb->get_input('action')) {
            case 'multiapprove';
            {
            } //no break since the code is the same except for the value being assigned
            case 'multiunapprove';
            {
                //verify if moderator and coming in from a click
                if (!$me->userPermissions[ModeratorPermissions::CanApproveEntries] && $mybb->get_input(
                        'modtype'
                    ) != 'inlineshowcase') {
                    error($lang->myshowcase_not_authorized);
                }

                // Verify incoming POST request
                verify_post_check($mybb->get_input('my_post_key'));

                $gids = $me->inlineGetIDs();
                array_map('intval', $gids);

                if (count($gids) < 1) {
                    error($lang->myshowcase_no_showcaseselected);
                }

                $groupIDs = implode(',', $gids);

                foreach ($gids as $entryID) {
                    showcaseDataUpdate($me->id, $entryID, [
                        'approved' => $mybb->get_input('action') === 'multiapprove' ? 1 : 0,
                        'approved_by' => $currentUserID,
                    ]);
                }

                $modlogdata = [
                    'id' => $me->id,
                    'gids' => implode(',', $gids)
                ];
                log_moderator_action(
                    $modlogdata,
                    ($mybb->get_input(
                        'action'
                    ) == 'multiapprove' ? $lang->myshowcase_mod_approve : $lang->myshowcase_mod_unapprove)
                );

                $me->inlineClear();

                //build URL to get back to where mod action happened
                $showcaseInputSortBy = $db->escape_string($showcaseInputSortBy);
                if ($showcaseInputSortBy != '') {
                    $url_params[] = 'sort_by=' . $showcaseInputSortBy;
                }

                if ($showcaseInputOrder) {
                    $url_params[] = 'order=' . $showcaseInputOrder;
                }

                $currentPage = intval($mybb->get_input('page', MyBB::INPUT_INT));
                if ($currentPage != '') {
                    $url_params[] = 'page=' . $currentPage;
                }

                $url = SHOWCASE_URL . (count($url_params) > 0 ? '?' . implode('&amp;', $url_params) : '');

                $redirtext = ($mybb->get_input(
                    'action'
                ) == 'multiapprove' ? $lang->redirect_myshowcase_approve : $lang->redirect_myshowcase_unapprove);
                redirect($url, $redirtext);
                exit;
                break;
            }

            case 'multidelete';
            {
                add_breadcrumb($lang->myshowcase_nav_multidelete);

                if (!$me->userPermissions[ModeratorPermissions::CanDeleteEntries] && $mybb->get_input(
                        'modtype'
                    ) != 'inlineshowcase') {
                    error($lang->myshowcase_not_authorized);
                }

                // Verify incoming POST request
                verify_post_check($mybb->get_input('my_post_key'));

                $gids = $me->inlineGetIDs();

                if (count($gids) < 1) {
                    error($lang->myshowcase_no_myshowcaseselected);
                }

                $inlineids = implode('|', $gids);

                $me->inlineClear();

                //build URl to get back to where mod action happened
                $showcaseInputSortBy = $db->escape_string($showcaseInputSortBy);
                if ($showcaseInputSortBy != '') {
                    $url_params[] = 'sort_by=' . $showcaseInputSortBy;
                }

                if ($showcaseInputOrder) {
                    $url_params[] = 'order=' . $showcaseInputOrder;
                }

                $currentPage = intval($currentPage);
                if ($currentPage != '') {
                    $url_params[] = 'page=' . $currentPage;
                }

                $return_url = SHOWCASE_URL . (count($url_params) > 0 ? '?' . implode('&amp;', $url_params) : '');
                //$return_url = htmlspecialchars_uni($mybb->get_input('url'));
                $multidelete = eval(getTemplate('inline_deleteshowcases'));
                output_page($multidelete);
                break;
            }
            case 'do_multidelete';
            {
                if (!$me->userPermissions[ModeratorPermissions::CanDeleteEntries]) {
                    error($lang->myshowcase_not_authorized);
                }

                // Verify incoming POST request
                verify_post_check($mybb->get_input('my_post_key'));

                $gids = explode('|', $mybb->get_input('showcases'));

                foreach ($gids as $gid) {
                    $gid = intval($gid);
                    $me->entryDelete($gid);
                    $glist[] = $gid;
                }

                //log_moderator_action($modlogdata, $lang->multi_deleted_threads);

                $me->inlineClear();

                //build URl to get back to where mod action happened
                $showcaseInputSortBy = $db->escape_string($showcaseInputSortBy);
                if ($showcaseInputSortBy != '') {
                    $url_params[] = 'sort_by=' . $showcaseInputSortBy;
                }

                if ($showcaseInputOrder) {
                    $url_params[] = 'order=' . $showcaseInputOrder;
                }

                $currentPage = intval($currentPage);
                if ($currentPage != '') {
                    $url_params[] = 'page=' . $currentPage;
                }

                $url = SHOWCASE_URL . (count($url_params) > 0 ? '?' . implode('&amp;', $url_params) : '');

                redirect($url, $lang->redirect_myshowcase_delete);
                exit;
                break;
            }
        }
    }

    #[NoReturn] public function edit(): void
    {
        global $mybb, $lang;

        $currentUserID = (int)$mybb->user['uid'];

        if (!$this->showcaseObject->entryData) {
            error($lang->myshowcase_invalid_id);
        }

        $entryFieldData = showcaseDataGet(
            $this->showcaseObject->id,
            ["gid='{$this->showcaseObject->entryData}'"],
            ['uid', 'posthash']
        );

        if (!$entryFieldData) {
            error($lang->myshowcase_invalid_id);
        }

        //make sure current user is moderator or the myshowcase author
        if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanEditEntries] && $currentUserID != $entryFieldData['uid']) {
            error($lang->myshowcase_not_authorized);
        }

        //get permissions for user
        $entryUserPermissions = $this->showcaseObject->userPermissionsGet($this->showcaseObject->entryUserID);

        $entryHash = $entryFieldData['posthash'];
        //no break since edit will share NEW code
    }

    #[NoReturn] public function main(): string
    {
        global $lang, $mybb, $db, $cache;
        global $header, $headerinclude, $footer, $theme;

        global $showcaseFieldsOrder, $showcaseFieldsSearchable, $currentPage, $amp, $urlSort, $showcaseInputSortBy;
        global $showcaseFieldsShow, $showcaseFields, $unapproved;
        global $urlParams, $urlBase, $showcaseInputSearchField, $showcaseColumnsCount;
        global $showcaseFieldsFormat, $showcaseName, $showcaseTableTheadInlineModeration, $showcase_url;
        global $showcaseInlineModeration, $buttonGo, $orderInput, $showcaseInputOrder, $showcaseFieldsParseable;

        $hookArguments = [];

        $hookArguments = hooksRun('output_main_start', $hookArguments);

        $buttonNewEntry = '';

        if ($this->showcaseObject->userPermissions[UserPermissions::CanAddEntries]) {
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

        $inputElementExactMatch = '';

        if ($this->renderObject->searchExactMatch) {
            $inputElementExactMatch = 'checked="checked"';
        }

        $urlSortRow = urlHandlerBuild(array_merge($urlParams, ['order' => $showcaseInputSortByNew]));

        $orderInput[$showcaseInputSortBy] = eval(getTemplate('pageMainTableTheadFieldSort'));

        if ($this->showcaseObject->friendlyUrlsEnabled) {
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

        $queryTables = ["{$this->showcaseObject->dataTableName} g"];

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
            if ($fieldType == FIELD_TYPE_HTML_DB || $fieldType == FIELD_TYPE_HTML_RADIO) {
                $queryTables[] = "myshowcase_field_data tbl_{$fieldName} ON (tbl_{$fieldName}.valueid = g.{$fieldName} AND tbl_{$fieldName}.name = '{$fieldName}')";

                $queryFields[] = "tbl_{$fieldName}.value AS `{$fieldName}`";

                if ($this->renderObject->searchKeyWords && $showcaseInputSearchField == $fieldName) {
                    if ($this->renderObject->searchExactMatch) {
                        $whereClauses[] = "tbl_{$fieldName}.value='{$db->escape_string($this->renderObject->searchKeyWords)}'";
                    } else {
                        $whereClauses[] = "tbl_{$fieldName}.value LIKE '%{$db->escape_string($this->renderObject->searchKeyWords)}%'";
                    }

                    $whereClauses[] = "tbl_{$fieldName}.setid='{$this->showcaseObject->fieldSetID}'";
                }
            } elseif ($showcaseInputSearchField == 'username' && !$searchDone) {
                $queryTables[] = 'users us ON (g.uid = us.uid)';

                $queryFields[] = "`{$fieldName}`";

                if ($this->renderObject->searchKeyWords) {
                    if ($this->renderObject->searchExactMatch) {
                        $whereClauses[] = "us.username='{$db->escape_string($this->renderObject->searchKeyWords)}'";
                    } else {
                        $whereClauses[] = "us.username LIKE '%{$db->escape_string($this->renderObject->searchKeyWords)}%'";
                    }
                }

                $searchDone = true;
            } else {
                $queryFields[] = "`{$fieldName}`";

                if ($this->renderObject->searchKeyWords && $showcaseInputSearchField == $fieldName) {
                    if ($this->renderObject->searchExactMatch) {
                        $whereClauses[] = "g.{$fieldName}='{$db->escape_string($this->renderObject->searchKeyWords)}'";
                    } else {
                        $whereClauses[] = "g.{$fieldName} LIKE '%{$db->escape_string($this->renderObject->searchKeyWords)}%'";
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

            if ($this->showcaseObject->userEntryAttachmentAsImage) {
                $entryIDs = [];

                while ($entryFieldData = $db->fetch_array($query)) {
                    $entryIDs[] = (int)$entryFieldData['gid'];
                }

                $entryIDs = implode("','", $entryIDs);

                $attachmentObjects = attachmentGet(
                    ["id='{$this->showcaseObject->id}'", "gid IN ('{$entryIDs}')", "visible='1'"],
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
                if ($this->renderObject->searchKeyWords) {
                    $urlBackup = urlHandlerGet();

                    urlHandlerSet($entryUrl);

                    $entryUrl = urlHandlerBuild([
                        //'search_field' => $showcaseInputSearchField,
                        'highlight' => urlencode($this->renderObject->searchKeyWords)
                    ]);

                    urlHandlerSet($urlBackup);
                }

                //build link for list view, starting with basic text
                $entryImage = $lang->myShowcaseMainTableTheadView;

                $entryImageText = str_replace('{username}', $entryUsername, $lang->myshowcase_view_user);

                //use default image is specified
                if ($this->showcaseObject->defaultImage != '' && (file_exists(
                            $theme['imgdir'] . '/' . $this->showcaseObject->defaultImage
                        ) || stristr(
                            $theme['imgdir'],
                            'http://'
                        ))) {
                    $urlImage = $mybb->get_asset_url($theme['imgdir'] . '/' . $this->showcaseObject->defaultImage);

                    $entryImage = eval(getTemplate('pageMainTableRowsImage'));
                }

                //use showcase attachment if one exists, scaled of course
                if ($this->showcaseObject->userEntryAttachmentAsImage) {
                    if (stristr($entryAttachmentsCache[$entryFieldData['gid']]['filetype'], 'image/')) {
                        $imagePath = $this->showcaseObject->imageFolder . '/' . $entryAttachmentsCache[$entryFieldData['gid']]['attachname'];

                        if ($entryAttachmentsCache[$entryFieldData['gid']]['aid'] && file_exists($imagePath)) {
                            if ($entryAttachmentsCache[$entryFieldData['gid']]['thumbnail'] == 'SMALL') {
                                $urlImage = $mybb->get_asset_url($imagePath);

                                $entryImage = eval(getTemplate('pageMainTableRowsImage'));
                            } else {
                                $urlImage = $mybb->get_asset_url(
                                    $this->showcaseObject->imageFolder . '/' . $entryAttachmentsCache[$entryFieldData['gid']]['thumbnail']
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
                        match ($showcaseFieldsFormat[$fieldName]) {
                            FORMAT_TYPE_MY_NUMBER_FORMAT => $showcaseFieldsFormat[$fieldName] = FORMAT_TYPE_MY_NUMBER_FORMAT,
                            'decimal1' => $showcaseFieldsFormat[$fieldName] = 2,
                            'decimal2' => $showcaseFieldsFormat[$fieldName] = 3,
                            default => $showcaseFieldsFormat[$fieldName] = 0
                        };

                        $formatTypes = FORMAT_TYPES;

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
                            case FIELD_TYPE_HTML_DATE:
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
                        $entryFieldText = $this->showcaseObject->parseMessage($entryFieldText);
                    }

                    $showcaseTableRowExtra .= eval(getTemplate('pageMainTableRowsExtra'));
                }

                $showcaseTableRowInlineModeration = '';

                if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries] &&
                    $this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries]) {
                    $inlineModerationCheckElement = '';

                    if (isset($mybb->cookies['inlinemod_showcase' . $this->showcaseObject->id]) &&
                        my_strpos(
                            "|{$mybb->cookies['inlinemod_showcase' . $this->showcaseObject->id]}|",
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

            if ($this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries] &&
                $this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries]) {
                // ++$colcount;
            }

            //$showcaseColumnsCount = $colcount + count($showcaseFieldsShow);

            if (!$this->renderObject->searchKeyWords) {
                $message = $lang->myShowcaseMainTableEmpty;
            } else {
                $message = $lang->myShowcaseMainTableEmptySearch;
            }

            $showcaseEntriesList .= eval(getTemplate('pageMainTableEmpty'));
        }

        $pageTitle = $this->showcaseObject->name;

        $urlSortByUsername = urlHandlerBuild(array_merge($urlParams, ['sort_by' => 'username']));

        $urlSortByComments = urlHandlerBuild(array_merge($urlParams, ['sort_by' => 'comments']));

        $urlSortByViews = urlHandlerBuild(array_merge($urlParams, ['sort_by' => 'views']));

        $urlSortByCreateDate = urlHandlerBuild(array_merge($urlParams, ['sort_by' => 'dateline']));

        return eval(getTemplate('pageMainContents'));
    }
}