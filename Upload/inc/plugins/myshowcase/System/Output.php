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
use function MyShowcase\Core\attachmentUpdate;
use function MyShowcase\Core\dataHandlerGetObject;
use function MyShowcase\Core\getTemplate;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\Core\fieldDataGet;
use function MyShowcase\Core\showcaseDataGet;
use function MyShowcase\Core\cacheUpdate;
use function MyShowcase\Core\loadLanguage;
use function MyShowcase\Core\reportGet;
use function MyShowcase\Core\reportInsert;
use function MyShowcase\Core\reportUpdate;
use function MyShowcase\Core\entryDataUpdate;

use const MyShowcase\Core\ATTACHMENT_UNLIMITED;
use const MyShowcase\Core\ATTACHMENT_ZERO;
use const MyShowcase\Core\DATA_HANDLERT_METHOD_UPDATE;
use const MyShowcase\Core\FIELD_TYPE_HTML_CHECK_BOX;
use const MyShowcase\Core\FIELD_TYPE_HTML_DB;
use const MyShowcase\Core\FIELD_TYPE_HTML_DATE;
use const MyShowcase\Core\FIELD_TYPE_HTML_RADIO;
use const MyShowcase\Core\FIELD_TYPE_HTML_TEXT_BOX;
use const MyShowcase\Core\FIELD_TYPE_HTML_TEXTAREA;
use const MyShowcase\Core\FIELD_TYPE_HTML_URL;
use const MyShowcase\Core\FORMAT_TYPE_NONE;
use const MyShowcase\Core\TABLES_DATA;
use const MyShowcase\Core\CACHE_TYPE_REPORTS;

class Output
{
    public function __construct(
        public Showcase &$showcaseObject,
        public Render &$renderObject,
        public array $urlParams = [],
        public int $page = 0,
        public int $pageCurrent = 0,
    ) {
        global $mybb;

        $this->urlParams = [];

        if ($mybb->get_input('unapproved', MyBB::INPUT_INT)) {
            $this->urlParams['unapproved'] = $mybb->get_input('unapproved', MyBB::INPUT_INT);
        }

        if (array_key_exists($this->showcaseObject->sortByField, $this->showcaseObject->fieldSetFieldsDisplayFields)) {
            $this->urlParams['sort_by'] = $this->showcaseObject->sortByField;
        }

        if ($renderObject->searchExactMatch) {
            $this->urlParams['exact_match'] = $renderObject->searchExactMatch;
        }

        if ($renderObject->searchKeyWords) {
            $this->urlParams['keywords'] = $renderObject->searchKeyWords;
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
    }

    public function report(): void
    {
        global $mybb, $lang, $db, $templates;
        global $forumdir;

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

                add_breadcrumb(
                    $lang->myshowcase_report,
                    $this->showcaseObject->urlBuild($this->showcaseObject->urlMain)
                );


                if (!$this->showcaseObject->entryID) {
                    error($lang->myshowcase_invalid_id);
                }

                $report_url = $this->showcaseObject->urlBuild($this->showcaseObject->urlMain);
                $pageContents = eval(getTemplate('report'));

                break;
            }

            case 'do_report':
            {
                if (!$mybb->request_method === 'post') {
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
                //add_breadcrumb($lang->myshowcase_report, $this->showcaseObject->urlBuild($this->showcaseObject->urlMain));

                $showcaseUserData = showcaseDataGet(
                    $this->showcaseObject->id,
                    ["gid='{$this->showcaseObject->entryID}'"],
                    ['uid']
                );

                if (!$showcaseUserData) {
                    error($lang->myshowcase_invalid_id);
                }

                $insert_array = [
                    'id' => $this->showcaseObject->id,
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
//			$entryUrl = str_replace('{gid}', $mybb->get_input('gid', \MyBB::INPUT_INT), $this->showcaseObject->urlViewEntry);
//			$redirect_newshowcase = $lang->myshowcase_report_success.''.$lang->redirect_myshowcase_back.''.$lang->sprintf($lang->redirect_myshowcase_return, $this->showcaseObject->urlBuild($this->showcaseObject->urlMain));
//			redirect($entryUrl, $redirect_newshowcase);
//			exit;
                }
                break;
            }

            case 'reports':
            {
                add_breadcrumb(
                    $lang->myshowcase_reports,
                    $this->showcaseObject->urlBuild($this->showcaseObject->urlMain)
                );

                if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanEditEntries] &&
                    !$this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries] &&
                    !$this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteComments]) {
                    error_no_permission();
                }

                loadLanguage('modcp');

                if (!$mybb->settings['threadsperpage']) {
                    $mybb->settings['threadsperpage'] = 20;
                }

                // Figure out if we need to display multiple pages.
                $reportsPerPage = $mybb->settings['threadsperpage'];

                $this->page = $this->pageCurrent;

                $report_count = reportGet(
                    ["status='0'", "id={$this->showcaseObject->id}"],
                    ['COUNT(rid) AS totalReports'],
                    ['limit' => 1]
                )['totalReports'] ?? 0;

                $mybb->input['rid'] = intval($mybb->get_input('rid', MyBB::INPUT_INT));

                if ($mybb->get_input('rid', MyBB::INPUT_INT)) {
                    $report_count = reportGet(
                        ["rid<='{$mybb->get_input('rid', MyBB::INPUT_INT)}'", "id={$this->showcaseObject->id}"],
                        ['COUNT(rid) AS totalReports'],
                        ['limit' => 1]
                    )['totalReports'] ?? 0;

                    if (($result % $reportsPerPage) === 0) {
                        $this->page = $result / $reportsPerPage;
                    } else {
                        $this->page = intval($result / $reportsPerPage) + 1;
                    }
                }

                $postcount = intval($report_count);

                $pages = $postcount / $reportsPerPage;

                $pages = ceil($pages);

                if ($this->page > $pages || $this->page <= 0) {
                    $this->page = 1;
                }

                if ($this->page && $this->page > 0) {
                    $start = ($this->page - 1) * $reportsPerPage;
                } else {
                    $start = 0;
                    $this->page = 1;
                }
                $upper = $start + $reportsPerPage;

                $pagination = multipage(
                    $postcount,
                    $reportsPerPage,
                    $this->page,
                    $this->showcaseObject->mainFile . '?action=reports'
                );
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
			WHERE r.status='0' AND r.id={$this->showcaseObject->id}
			ORDER BY r.dateline DESC
			LIMIT {$start}, {$reportsPerPage}
		"
                );

                while ($report = $db->fetch_array($query)) {
                    $trow = 'trow_shaded';

                    $report['showcaselink'] = str_replace('{gid}', $report['gid'], $this->showcaseObject->urlViewEntry);

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

                $showcase_file = $this->showcaseObject->urlBuild($this->showcaseObject->urlMain);
                $pageContents = eval(getTemplate('reports'));

                break;
            }

            case 'do_reports':
            {
                if (!$mybb->request_method === 'post') {
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

                reportUpdate(['status' => 1], ["rid IN ({$rids})", "id={$this->showcaseObject->id}"]);

                $this->page = $this->pageCurrent;

                redirect(
                    $this->showcaseObject->urlBuild(
                        $this->showcaseObject->urlMain
                    ) . "?action=reports&page={$this->page}",
                    $lang->redirect_reportsmarked
                );

                break;
            }

            case 'allreports':
            {
                add_breadcrumb(
                    $lang->myshowcase_reports,
                    $this->showcaseObject->urlBuild($this->showcaseObject->urlMain)
                );

                if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanEditEntries] &&
                    !$this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries] &&
                    !$this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteComments]) {
                    error_no_permission();
                }

                loadLanguage('modcp');

                if (!$mybb->settings['threadsperpage']) {
                    $mybb->settings['threadsperpage'] = 20;
                }

                // Figure out if we need to display multiple pages.
                $reportsPerPage = $mybb->settings['threadsperpage'];

                $this->page = $this->pageCurrent;

                $report_count = reportGet(
                    ["id='{$this->showcaseObject->id}'"],
                    ['COUNT(rid) AS totalReports'],
                    ['limit' => 1]
                )['totalReports'] ?? 0;

                $mybb->input['rid'] = intval($mybb->get_input('rid', MyBB::INPUT_INT));

                if ($mybb->get_input('rid', MyBB::INPUT_INT)) {
                    $totalReports = reportGet(
                        ["rid<='{$mybb->get_input('rid', MyBB::INPUT_INT)}'", "id={$this->showcaseObject->id}"],
                        ['COUNT(rid) AS totalReports'],
                        ['limit' => 1]
                    )['totalReports'] ?? 0;

                    if (($totalReports % $reportsPerPage) === 0) {
                        $this->page = $totalReports / $reportsPerPage;
                    } else {
                        $this->page = intval($totalReports / $reportsPerPage) + 1;
                    }
                }
                $postcount = intval($report_count);
                $pages = $postcount / $reportsPerPage;
                $pages = ceil($pages);

                if ($this->page > $pages || $this->page <= 0) {
                    $this->page = 1;
                }

                if ($this->page && $this->page > 0) {
                    $start = ($this->page - 1) * $reportsPerPage;
                } else {
                    $start = 0;

                    $this->page = 1;
                }
                $upper = $start + $reportsPerPage;

                $pagination = multipage(
                    $postcount,
                    $reportsPerPage,
                    $this->page,
                    $this->showcaseObject->mainFile . '?action=allreports'
                );
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
			WHERE  r.id={$this->showcaseObject->id}
			ORDER BY r.dateline DESC
			LIMIT {$start}, {$reportsPerPage}
		"
                );

                while ($report = $db->fetch_array($query)) {
                    if ($report['status'] === 0) {
                        $trow = 'trow_shaded';
                    } else {
                        $trow = alt_trow();
                    }

                    $report['showcaselink'] = str_replace('{gid}', $report['gid'], $this->showcaseObject->urlViewEntry);

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

                $showcase_file = $this->showcaseObject->urlBuild($this->showcaseObject->urlMain);
                $pageContents = eval(getTemplate('allreports'));

                break;
            }
        }
    }

    #[NoReturn] public function entryPost(bool $isEditPage = false): void
    {
        global $lang, $mybb, $db, $cache;
        global $header, $headerinclude, $footer, $theme;

        global $showcaseName;
        global $buttonGo;

        global $entryHash;

        global $templates, $plugins;

        global $entryHash;

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
                if ($mybb->get_input('action') === 'new') {
                    add_breadcrumb(
                        $lang->myShowcaseButtonNewEntry,
                        $this->showcaseObject->urlBuild($this->showcaseObject->urlMain)
                    );
                    $showcase_action = 'new';

                    //need to populated a default user value here for new entries
                    $entryFieldsData['uid'] = $currentUserID;
                } else {
                    $showcase_editing_user = str_replace(
                        '{username}',
                        $entryUserData['username'],
                        $lang->myshowcase_editing_user
                    );
                    add_breadcrumb(
                        $showcase_editing_user,
                        $this->showcaseObject->urlBuild($this->showcaseObject->urlMain)
                    );
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

                    foreach ($attachmentObjects as $attachmentID => $attachmentData) {
                        $attachmentData['size'] = get_friendly_size($attachmentData['filesize']);
                        $attachmentData['icon'] = get_attachment_icon(get_extension($attachmentData['filename']));
                        $attachmentData['icon'] = str_replace(
                            '<img src="',
                            '<img src="' . $this->showcaseObject->urlBase . '/',
                            $attachmentData['icon']
                        );


                        $attach_mod_options = '';
                        if ($attachmentData['visible'] !== 1) {
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

                if ($mybb->request_method === 'post' && $mybb->get_input('submit')) {
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
                    //require_once MYBB_ROOT . 'inc/datahandlers/myshowcase_dh.php';

                    if ($mybb->get_input('action') === 'edit') {
                        $showcasehandler = dataHandlerGetObject($this->showcaseObject, DATA_HANDLERT_METHOD_UPDATE);
                        $showcasehandler->action = 'edit';
                    } else {
                        $showcasehandler = dataHandlerGetObject($this->showcaseObject);
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
                    if ($mybb->get_input('action') === 'edit') {
                        $default_data = array_merge(
                            $default_data,
                            ['gid' => $this->showcaseObject->entryData]
                        );
                    }

                    //add showcase specific fields
                    reset($this->showcaseObject->fieldSetEnabledFields);

                    $submitted_data = [];

                    foreach ($this->showcaseObject->fieldSetEnabledFields as $fieldName => $htmlType) {
                        if ($htmlType === FIELD_TYPE_HTML_DB || $htmlType === FIELD_TYPE_HTML_RADIO) {
                            $submitted_data[$fieldName] = intval($mybb->get_input('myshowcase_field_' . $fieldName));
                        } elseif ($htmlType === FIELD_TYPE_HTML_CHECK_BOX) {
                            $submitted_data[$fieldName] = (isset($mybb->input['myshowcase_field_' . $fieldName]) ? 1 : 0);
                        } elseif ($htmlType === FIELD_TYPE_HTML_DATE) {
                            $m = $db->escape_string($mybb->get_input('myshowcase_field_' . $fieldName . '_m'));
                            $d = $db->escape_string($mybb->get_input('myshowcase_field_' . $fieldName . '_d'));
                            $y = $db->escape_string($mybb->get_input('myshowcase_field_' . $fieldName . '_y'));
                            $submitted_data[$fieldName] = $m . '|' . $d . '|' . $y;
                        } else {
                            $submitted_data[$fieldName] = $db->escape_string(
                                $mybb->get_input('myshowcase_field_' . $fieldName)
                            );
                        }
                    }

                    //send data to handler
                    $showcasehandler->set_data(array_merge($default_data, $submitted_data));

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
                        if ($mybb->get_input('action') === 'edit') {
                            $insert_showcase = $showcasehandler->update_showcase();
                            $showcaseid = $this->showcaseObject->entryData;
                        } //insert showcase
                        else {
                            $insert_showcase = $showcasehandler->insert_showcase();
                            $showcaseid = $insert_showcase['gid'];
                        }

                        $plugins->run_hooks('myshowcase_do_newedit_end');

                        //fix url insert variable to update results
                        $entryUrl = str_replace('{gid}', (string)$showcaseid, $this->showcaseObject->urlViewEntry);

                        $redirect_newshowcase = $lang->redirect_myshowcase_new . '' . $lang->redirect_myshowcase . '' . $lang->sprintf(
                                $lang->redirect_myshowcase_return,
                                $this->showcaseObject->urlBuild($this->showcaseObject->urlMain)
                            );
                        redirect($entryUrl, $redirect_newshowcase);
                        exit;
                    }
                } else {
                    $plugins->run_hooks('myshowcase_newedit_start');

                    $pageContents .= eval(getTemplate('new_top'));

                    reset($this->showcaseObject->fieldSetEnabledFields);

                    foreach ($this->showcaseObject->fieldSetEnabledFields as $fieldName => $htmlType) {
                        $temp = 'myshowcase_field_' . $fieldName;
                        $field_header = !empty($lang->$temp) ? $lang->$temp : $fieldName;

                        $alternativeBackground = ($alternativeBackground === 'trow1' ? 'trow2' : 'trow1');

                        if ($mybb->get_input('action') === 'edit') {
                            $mybb->input['myshowcase_field_' . $fieldName] = htmlspecialchars_uni(
                                stripslashes($entryFieldsData[$fieldName])
                            );
                        }

                        switch ($htmlType) {
                            case FIELD_TYPE_HTML_TEXT_BOX:
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fieldName;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fieldName);
                                $showcase_field_enabled = '';//($this->showcaseObject->fieldSetEnabledFields[$fieldName] !== 1 ? 'disabled' : '');
                                $showcase_field_checked = '';
                                $showcase_field_options = 'maxlength="' . $this->showcaseObject->fieldSetFieldsMaximumLenght[$fieldName] . '"';
                                $showcase_field_input = eval(getTemplate('field_textbox'));

                                if ($this->showcaseObject->fieldSetFormatableFields[$fieldName] !== FORMAT_TYPE_NONE) {
                                    $showcase_field_input .= '&nbsp;' . $lang->myshowcase_editing_number;
                                }
                                break;

                            case FIELD_TYPE_HTML_URL:
                                $showcase_field_width = 150;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fieldName;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fieldName);
                                $showcase_field_enabled = '';//($this->showcaseObject->fieldSetEnabledFields[$fieldName] !== 1 ? 'disabled' : '');
                                $showcase_field_checked = '';
                                $showcase_field_options = 'maxlength="' . $this->showcaseObject->fieldSetFieldsMaximumLenght[$fieldName] . '"';
                                $showcase_field_input = eval(getTemplate('field_textbox'));
                                break;

                            case 'textarea':
                                $showcase_field_width = 100;
                                $showcase_field_rows = $this->showcaseObject->fieldSetFieldsMaximumLenght[$fieldName];
                                $showcase_field_name = 'myshowcase_field_' . $fieldName;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fieldName);
                                $showcase_field_enabled = '';//($this->showcaseObject->fieldSetEnabledFields[$fieldName] !== 1 ? 'disabled' : '');
                                $showcase_field_checked = '';
                                $showcase_field_options = '';
                                $showcase_field_input = eval(getTemplate('field_textarea'));
                                break;

                            case FIELD_TYPE_HTML_RADIO:
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fieldName;
                                $showcase_field_enabled = '';//($this->showcaseObject->fieldSetEnabledFields[$fieldName] !== 1 ? 'disabled' : '');
                                $showcase_field_options = '';

                                $fieldDataObjects = fieldDataGet(
                                    [
                                        "setid='{$this->showcaseObject->fieldSetID}'",
                                        "name='{$fieldName}'",
                                        "valueid!='0'"
                                    ],
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
                                        'myshowcase_field_' . $fieldName
                                    ) === $results['valueid'] ? ' checked' : '');
                                    $showcase_field_text = $results['value'];
                                    $showcase_field_input .= eval(getTemplate('field_radio'));
                                }
                                break;

                            case FIELD_TYPE_HTML_CHECK_BOX:
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fieldName;
                                $showcase_field_value = '1';
                                $showcase_field_enabled = '';//($this->showcaseObject->fieldSetEnabledFields[$fieldName] !== 1 ? 'disabled' : '');
                                $showcase_field_checked = ($mybb->get_input(
                                    'myshowcase_field_' . $fieldName
                                ) === 1 ? ' checked="checked"' : '');
                                $showcase_field_options = '';
                                $showcase_field_input = eval(getTemplate('field_checkbox'));
                                break;

                            case FIELD_TYPE_HTML_DB:
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fieldName;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fieldName);
                                $showcase_field_enabled = '';//($this->showcaseObject->fieldSetEnabledFields[$fieldName] !== 1 ? 'disabled' : '');
                                $showcase_field_checked = '';

                                $fieldDataObjects = fieldDataGet(
                                    [
                                        "setid='{$this->showcaseObject->fieldSetID}'",
                                        "name='{$fieldName}'",
                                        "valueid!='0'"
                                    ],
                                    ['valueid', 'value'],
                                    ['order_by' => 'disporder']
                                );

                                if (!$fieldDataObjects) {
                                    error($lang->myshowcase_db_no_data);
                                }

                                $showcase_field_options = ($mybb->get_input(
                                    'action'
                                ) === 'new' ? '<option value=0>&lt;Select&gt;</option>' : '');
                                foreach ($fieldDataObjects as $fieldDataID => $results) {
                                    $showcase_field_options .= '<option value="' . $results['valueid'] . '" ' . ($showcase_field_value === $results['valueid'] ? ' selected' : '') . '>' . $results['value'] . '</option>';
                                }
                                $showcase_field_input = eval(getTemplate('field_db'));
                                break;

                            case FIELD_TYPE_HTML_DATE:
                                $showcase_field_width = 50;
                                $showcase_field_rows = '';
                                $showcase_field_name = 'myshowcase_field_' . $fieldName;
                                $showcase_field_value = $mybb->get_input('myshowcase_field_' . $fieldName);
                                $showcase_field_enabled = '';//($this->showcaseObject->fieldSetEnabledFields[$fieldName] !== 1 ? 'disabled' : '');
                                $showcase_field_checked = '';

                                $showcase_field_value_m = ($mybb->get_input(
                                    'myshowcase_field_' . $fieldName . '_m'
                                ) === '00' ? '00' : $mybb->get_input('myshowcase_field_' . $fieldName . '_m'));
                                $showcase_field_value_d = ($mybb->get_input(
                                    'myshowcase_field_' . $fieldName . '_d'
                                ) === '00' ? '00' : $mybb->get_input('myshowcase_field_' . $fieldName . '_d'));
                                $showcase_field_value_y = ($mybb->get_input(
                                    'myshowcase_field_' . $fieldName . '_y'
                                ) === '0000' ? '0000' : $mybb->get_input('myshowcase_field_' . $fieldName . '_y'));

                                $showcase_field_options_m = '<option value=0' . ($showcase_field_value_m === '00' ? ' selected' : '') . '>&nbsp;</option>';
                                $showcase_field_options_d = '<option value=0' . ($showcase_field_value_d === '00' ? ' selected' : '') . '>&nbsp;</option>';
                                $showcase_field_options_y = '<option value=0' . ($showcase_field_value_y === '0000' ? ' selected' : '') . '>&nbsp;</option>';
                                for ($i = 1; $i <= 12; $i++) {
                                    $showcase_field_options_m .= '<option value="' . substr(
                                            '0' . $i,
                                            -2
                                        ) . '" ' . ($showcase_field_value_m === substr(
                                            '0' . $i,
                                            -2
                                        ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                                }
                                for ($i = 1; $i <= 31; $i++) {
                                    $showcase_field_options_d .= '<option value="' . substr(
                                            '0' . $i,
                                            -2
                                        ) . '" ' . ($showcase_field_value_d === substr(
                                            '0' . $i,
                                            -2
                                        ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                                }
                                for ($i = $this->showcaseObject->fieldSetFieldsMaximumLenght[$fieldName]; $i >= $this->showcaseObject->fieldSetFieldsMinimumLenght[$fieldName]; $i--) {
                                    $showcase_field_options_y .= '<option value="' . $i . '" ' . ($showcase_field_value_y === $i ? ' selected' : '') . '>' . $i . '</option>';
                                }
                                $showcase_field_input = eval(getTemplate('field_date'));
                                break;
                        }

                        $field_header = ($this->showcaseObject->fieldSetFieldsRequired[$fieldName] ? '<strong>' . $field_header . ' *</strong>' : $field_header);
                        $pageContents .= eval(getTemplate('new_fields'));
                    }

                    $plugins->run_hooks('myshowcase_newedit_end');

                    $pageContents .= eval(getTemplate('new_bottom'));
                }
            }
        }

        if ($mybb->get_input('action') === 'new') {
            add_breadcrumb(
                $lang->myShowcaseButtonNewEntry,
                $this->showcaseObject->urlBuild($this->showcaseObject->urlMain)
            );
        } elseif ($mybb->get_input('action') === 'edit') {
            $showcase_editing_user = str_replace(
                '{username}',
                $entryUserData['username'],
                $lang->myshowcase_editing_user
            );
            add_breadcrumb($showcase_editing_user, $this->showcaseObject->urlBuild($this->showcaseObject->urlMain));
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

            foreach ($attachmentObjects as $attachmentID => $attachmentData) {
                $attachmentData['size'] = get_friendly_size($attachmentData['filesize']);
                $attachmentData['icon'] = get_attachment_icon(get_extension($attachmentData['filename']));
                $attachmentData['icon'] = str_replace(
                    '<img src="',
                    '<img src="' . $this->showcaseObject->urlBase . '/',
                    $attachmentData['icon']
                );

                $attach_mod_options = '';
                if ($attachmentData['visible'] !== 1) {
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
                if ($this->showcaseObject->userPermissions[UserPermissions::CanWaterMarkAttachments] && $this->showcaseObject->waterMarkImage !== '' && file_exists(
                        $this->showcaseObject->waterMarkImage
                    )) {
                    $showcase_watermark = eval(getTemplate('watermark'));
                }
                $showcase_new_attachments_input = eval(getTemplate('new_attachments_input'));
            }

            if (!empty($showcase_new_attachments_input) || $attachments !== '') {
                $attachmentsTable = eval(getTemplate('pageNewAttachments'));
            }
        }

        $alternativeBackground = 'trow2';

        reset($this->showcaseObject->fieldSetEnabledFields);

        $showcaseFields = '';

        $fieldTabIndex = 1;

        foreach ($this->showcaseObject->fieldSetEnabledFields as $fieldName => $htmlType) {
            $fieldKey = "myshowcase_field_{$fieldName}";

            $fieldTitle = $fieldName;

            if (isset($lang->{$fieldKey})) {
                $fieldTitle = $lang->{$fieldKey};
            }

            $alternativeBackground = ($alternativeBackground === 'trow1' ? 'trow2' : 'trow1');

            if ($mybb->get_input('action') === 'edit') {
                $mybb->input[$fieldKey] = htmlspecialchars_uni(
                    stripslashes($entryFieldsData[$fieldName])
                );
            }

            $fieldElementRequired = '';

            if ($this->showcaseObject->fieldSetFieldsRequired[$fieldName]) {
                $fieldElementRequired = 'required="required"';
            }

            $minimumLength = $this->showcaseObject->fieldSetFieldsMinimumLenght[$fieldName] ?? '';

            $maximumLength = $this->showcaseObject->fieldSetFieldsMaximumLenght[$fieldName] ?? '';

            switch ($htmlType) {
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
                    $showcase_field_enabled = '';// ($this->showcaseObject->fieldSetEnabledFields[$fieldName] !== 1 ? 'disabled' : '');
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
                        ) === $fieldData['valueid'] ? ' checked' : '');

                        $showcase_field_text = $fieldData['value'];

                        $fieldInput .= eval(getTemplate('field_radio'));
                    }

                    break;

                case FIELD_TYPE_HTML_CHECK_BOX:
                    $showcase_field_width = 50;
                    $showcase_field_rows = '';
                    $fieldValue = '1';

                    $showcase_field_checked = ($mybb->get_input($fieldKey) === 1 ? ' checked="checked"' : '');
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
                    ) === 'new' ? '<option value=0>&lt;Select&gt;</option>' : '');
                    foreach ($fieldDataObjects as $fieldDataID => $results) {
                        $showcase_field_options .= '<option value="' . $results['valueid'] . '" ' . ($fieldValue === $results['valueid'] ? ' selected' : '') . '>' . $results['value'] . '</option>';
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
                    ) === '00' ? '00' : $mybb->get_input($fieldKey . '_m'));
                    $showcase_field_value_d = ($mybb->get_input(
                        $fieldKey . '_d'
                    ) === '00' ? '00' : $mybb->get_input($fieldKey . '_d'));
                    $showcase_field_value_y = ($mybb->get_input(
                        $fieldKey . '_y'
                    ) === '0000' ? '0000' : $mybb->get_input($fieldKey . '_y'));

                    $showcase_field_options_m = '<option value=00' . ($showcase_field_value_m === '00' ? ' selected' : '') . '>&nbsp;</option>';
                    $showcase_field_options_d = '<option value=00' . ($showcase_field_value_d === '00' ? ' selected' : '') . '>&nbsp;</option>';
                    $showcase_field_options_y = '<option value=0000' . ($showcase_field_value_y === '0000' ? ' selected' : '') . '>&nbsp;</option>';
                    for ($i = 1; $i <= 12; $i++) {
                        $showcase_field_options_m .= '<option value="' . substr(
                                '0' . $i,
                                -2
                            ) . '" ' . ($showcase_field_value_m === substr(
                                '0' . $i,
                                -2
                            ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                    }
                    for ($i = 1; $i <= 31; $i++) {
                        $showcase_field_options_d .= '<option value="' . substr(
                                '0' . $i,
                                -2
                            ) . '" ' . ($showcase_field_value_d === substr(
                                '0' . $i,
                                -2
                            ) ? ' selected' : '') . '>' . substr('0' . $i, -2) . '</option>';
                    }
                    for ($i = $this->showcaseObject->fieldSetFieldsMaximumLenght[$fieldName]; $i >= $this->showcaseObject->fieldSetFieldsMinimumLenght[$fieldName]; $i--) {
                        $showcase_field_options_y .= '<option value="' . $i . '" ' . ($showcase_field_value_y === $i ? ' selected' : '') . '>' . $i . '</option>';
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
        global $mybb, $lang;

        $currentUserID = (int)$mybb->user['uid'];

        switch ($mybb->get_input('action')) {
            case 'multiapprove';
            {
            } //no break since the code is the same except for the value being assigned
            case 'multiunapprove';
            {
                //verify if moderator and coming in from a click
                if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries] && $mybb->get_input(
                        'modtype'
                    ) !== 'inlineshowcase') {
                    error($lang->myshowcase_not_authorized);
                }

                // Verify incoming POST request
                verify_post_check($mybb->get_input('my_post_key'));

                $gids = $this->showcaseObject->inlineGetIDs();
                array_map('intval', $gids);

                if (count($gids) < 1) {
                    error($lang->myshowcase_no_showcaseselected);
                }

                $groupIDs = implode(',', $gids);

                foreach ($gids as $entryID) {
                    entryDataUpdate($this->showcaseObject->id, $entryID, [
                        'approved' => $mybb->get_input('action') === 'multiapprove' ? 1 : 0,
                        'approved_by' => $currentUserID,
                    ]);
                }

                $modlogdata = [
                    'id' => $this->showcaseObject->id,
                    'gids' => implode(',', $gids)
                ];
                log_moderator_action(
                    $modlogdata,
                    ($mybb->get_input(
                        'action'
                    ) === 'multiapprove' ? $lang->myshowcase_mod_approve : $lang->myshowcase_mod_unapprove)
                );

                $this->showcaseObject->inlineClear();

                if ($this->showcaseObject->sortByField) {
                    $url_params['sort_by'] = $this->showcaseObject->sortByField;
                }

                if ($this->showcaseObject->orderBy) {
                    $url_params[] = 'order_by=' . $this->showcaseObject->orderBy;
                }

                if ($this->pageCurrent) {
                    $url_params[] = 'page=' . $this->pageCurrent;
                }

                $url = $this->showcaseObject->urlBuild($this->showcaseObject->urlMain) . (count(
                        $url_params
                    ) > 0 ? '?' . implode(
                            '&amp;',
                            $url_params
                        ) : '');

                $redirtext = ($mybb->get_input(
                    'action'
                ) === 'multiapprove' ? $lang->redirect_myshowcase_approve : $lang->redirect_myshowcase_unapprove);
                redirect($url, $redirtext);
                exit;
                break;
            }

            case 'multidelete';
            {
                add_breadcrumb($lang->myshowcase_nav_multidelete);

                if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries] && $mybb->get_input(
                        'modtype'
                    ) !== 'inlineshowcase') {
                    error($lang->myshowcase_not_authorized);
                }

                // Verify incoming POST request
                verify_post_check($mybb->get_input('my_post_key'));

                $gids = $this->showcaseObject->inlineGetIDs();

                if (count($gids) < 1) {
                    error($lang->myshowcase_no_myshowcaseselected);
                }

                $inlineids = implode('|', $gids);

                $this->showcaseObject->inlineClear();

                if ($this->showcaseObject->sortByField) {
                    $url_params['sort_by'] = $this->showcaseObject->sortByField;
                }

                if ($this->showcaseObject->orderBy) {
                    $url_params[] = 'order_by=' . $this->showcaseObject->orderBy;
                }

                if ($this->pageCurrent) {
                    $url_params[] = 'page=' . $this->pageCurrent;
                }

                $return_url = $this->showcaseObject->urlBuild($this->showcaseObject->urlMain) . (count(
                        $url_params
                    ) > 0 ? '?' . implode(
                            '&amp;',
                            $url_params
                        ) : '');
                //$return_url = htmlspecialchars_uni($mybb->get_input('url'));
                $multidelete = eval(getTemplate('inline_deleteshowcases'));
                output_page($multidelete);
                break;
            }
            case 'do_multidelete';
            {
                if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries]) {
                    error($lang->myshowcase_not_authorized);
                }

                // Verify incoming POST request
                verify_post_check($mybb->get_input('my_post_key'));

                $gids = explode('|', $mybb->get_input('showcases'));

                foreach ($gids as $gid) {
                    $gid = intval($gid);
                    $this->showcaseObject->entryDelete($gid);
                    $glist[] = $gid;
                }

                //log_moderator_action($modlogdata, $lang->multi_deleted_threads);

                $this->showcaseObject->inlineClear();

                //build URl to get back to where mod action happened
                if ($this->showcaseObject->sortByField) {
                    $url_params['sort_by'] = $this->showcaseObject->sortByField;
                }

                if ($this->showcaseObject->orderBy) {
                    $url_params[] = 'order_by=' . $this->showcaseObject->orderBy;
                }

                if ($this->pageCurrent) {
                    $url_params[] = 'page=' . $this->pageCurrent;
                }

                $url = $this->showcaseObject->urlBuild($this->showcaseObject->urlMain) . (count(
                        $url_params
                    ) > 0 ? '?' . implode(
                            '&amp;',
                            $url_params
                        ) : '');

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
        if (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanEditEntries] && $currentUserID !== $entryFieldData['uid']) {
            error($lang->myshowcase_not_authorized);
        }

        //get permissions for user
        $entryUserPermissions = $this->showcaseObject->userPermissionsGet($this->showcaseObject->entryUserID);

        $entryHash = $entryFieldData['posthash'];
        //no break since edit will share NEW code
    }

    #[NoReturn] public function attachmentDownload(int $attachmentID): void
    {
        global $mybb, $lang, $plugins;

        $attachmentData = attachmentGet(["aid='{$attachmentID}'"],
            array_keys(TABLES_DATA['myshowcase_attachments']),
            ['limit' => 1]);

        // Error if attachment is invalid or not visible
        if (!$attachmentData['aid'] || !$attachmentData['attachname'] || (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries] && $attachmentData['visible'] !== 1)) {
            error($lang->error_invalidattachment);
        }

        if (!$this->showcaseObject->allowAttachments || !$this->showcaseObject->userPermissions[UserPermissions::CanViewAttachments]) {
            error_no_permission();
        }

        $filePath = MYBB_ROOT . $this->showcaseObject->imageFolder . '/' . $attachmentData['attachname'];

        if (!file_exists($filePath)) {
            error($lang->error_invalidattachment);
        }

        $plugins->run_hooks('myshowcase_attachment_start');

        attachmentUpdate(['downloads' => $attachmentData['downloads'] + 1], $attachmentID);

        if (stristr($attachmentData['filetype'], 'image/')) {
            $posterdata = get_user($attachmentData['uid']);

            $showcase_viewing_user = str_replace('{username}', $posterdata['username'], $lang->myshowcase_viewing_user);

            add_breadcrumb(
                $showcase_viewing_user,
                str_replace('{gid}', $attachmentData['gid'], $this->showcaseObject->urlViewEntry)
            );

            $attachmentData['filename'] = rawurlencode($attachmentData['filename']);

            $plugins->run_hooks('myshowcase_attachment_end');

            $showcase_viewing_attachment = str_replace(
                '{username}',
                $posterdata['username'],
                $lang->myshowcase_viewing_attachment
            );

            add_breadcrumb(
                $showcase_viewing_attachment,
                str_replace('{gid}', $attachmentData['gid'], $this->showcaseObject->urlViewEntry)
            );

            $lasteditdate = my_date($mybb->settings['dateformat'], $attachmentData['dateuploaded']);

            $lastedittime = my_date($mybb->settings['timeformat'], $attachmentData['dateuploaded']);

            $entryDateline = $lasteditdate . '&nbsp;' . $lastedittime;

            $showcase_attachment_description = $lang->myshowcase_attachment_filename . $attachmentData['filename'] . '<br />' . $lang->myshowcase_attachment_uploaded . $entryDateline;

            $showcase_attachment = str_replace(
                '{aid}',
                $attachmentData['aid'],
                $this->showcaseObject->urlViewAttachmentItem
            );//$this->showcaseObject->imgfolder."/".$attachmentData['attachname'];

            $pageContents = eval(getTemplate('attachment_view'));

            $plugins->run_hooks('myshowcase_attachment_end');

            output_page($pageContents);
        } else //should never really be called, but just incase, support inline output
        {
            header('Cache-Control: private', false);

            header('Content-Type: ' . $attachmentData['filetype']);

            header('Content-Description: File Transfer');

            header('Content-Disposition: inline; filename=' . $attachmentData['filename']);

            header('Content-Length: ' . $attachmentData['filesize']);

            header('Expires: 0');

            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');

            header('Pragma: public');

            ob_clean();

            flush();

            readfile($filePath);
        }

        exit;
    }

    #[NoReturn] public function attachmentDownloadItem(int $attachmentID): void
    {
        global $lang, $plugins;

        $attachmentData = attachmentGet(["aid='{$attachmentID}'"],
            array_keys(TABLES_DATA['myshowcase_attachments']),
            ['limit' => 1]);

        // Error if attachment is invalid or not visible
        if (empty($attachmentData['aid']) ||
            empty($attachmentData['attachname']) ||
            (!$this->showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries] && empty($attachmentData['visible']))) {
            error($lang->error_invalidattachment);
        }

        if (!$this->showcaseObject->allowAttachments || !$this->showcaseObject->userPermissions[UserPermissions::CanDownloadAttachments]) {
            error_no_permission();
        }

        //$attachmentExtension = get_extension($attachmentData['filename']);

        $filePath = MYBB_ROOT . $this->showcaseObject->imageFolder . '/' . $attachmentData['attachname'];

        if (!file_exists($filePath)) {
            error($lang->error_invalidattachment);
        }

        switch ($attachmentData['filetype']) {
            case 'application/pdf':
            case 'image/bmp':
            case 'image/gif':
            case 'image/jpeg':
            case 'image/pjpeg':
            case 'image/png':
            case 'text/plain':
                header("Content-type: {$attachmentData['filetype']}");

                $disposition = 'inline';
                break;

            default:
                header('Content-type: application/force-download');

                $disposition = 'attachment';
        }

        if (my_strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'msie') !== false) {
            header("Content-disposition: attachment; filename=\"{$attachmentData['filename']}\"");
        } else {
            header("Content-disposition: {$disposition}; filename=\"{$attachmentData['filename']}\"");
        }

        if (my_strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'msie 6.0') !== false) {
            header('Expires: -1');
        }

        header("Content-length: {$attachmentData['filesize']}");

        header('Content-range: bytes=0-' . ($attachmentData['filesize'] - 1) . '/' . $attachmentData['filesize']);

        $plugins->run_hooks('myshowcase_image');

        echo file_get_contents($filePath);

        exit;
    }
}