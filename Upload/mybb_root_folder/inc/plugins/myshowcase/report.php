<?php
/**
 * MyShowcase Plugin for MyBB - Code for Reports
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\plugins\myshowcase\report.php
 *
 */

switch ($mybb->input['action']) {
    case 'report':
    {
        //load report lang and update with our items
        $lang->load('report');
        $lang->only_report = $lang->myshowcase_report_warning;
        $lang->report_error = $lang->myshowcase_report_error;
        $lang->report_reason = $lang->myshowcase_report_reason;
        $lang->report_post = $lang->myshowcase_report;
        $lang->only_report = $lang->myshowcase_report_warning;
        $lang->report_to_mod = $lang->myshowcase_report_label;

        add_breadcrumb($lang->myshowcase_report, SHOWCASE_URL);

        $mybb->input['gid'] = intval($mybb->input['gid']);

        if ($mybb->input['gid'] == '' || $mybb->input['gid'] == 0) {
            error($lang->myshowcase_invalid_id);
        }

        $report_url = SHOWCASE_URL;
        eval("\$showcase_page = \"" . $templates->get('myshowcase_report') . "\";");

        break;
    }

    case 'do_report':
    {
        if (!$mybb->request_method == 'post') {
            error_no_permission();
        }

        //load report lang and update with our items
        $lang->load('report');
        $lang->report_error = $lang->myshowcase_report_error;
        $lang->post_reported = $lang->myshowcase_report_success;

        verify_post_check($mybb->input['my_post_key']);

        if (!trim($mybb->input['reason'])) {
            eval("\$report = \"" . $templates->get('report_noreason') . "\";");
            output_page($report);
            exit;
        }
        //add_breadcrumb($lang->myshowcase_report, SHOWCASE_URL);

        $mybb->input['gid'] = intval($mybb->input['gid']);

        $query = $db->simple_select($me->table_name, 'gid,uid', "gid={$mybb->input['gid']}");
        $result = $db->fetch_array($query);
        if (!$result['gid']) {
            error($lang->myshowcase_invalid_id);
        }

        $insert_array = array(
            'id' => $me->id,
            'gid' => $result['gid'],
            'reporteruid' => $mybb->user['uid'],
            'authoruid' => $result['uid'],
            'status' => 0,
            'reason' => $db->escape_string($mybb->input['reason']),
            'dateline' => TIME_NOW
        );

        $rid = $db->insert_query('myshowcase_reports', $insert_array);

        myshowcase_update_cache('reports');

        if (!$rid) {
            eval("\$report = \"" . $templates->get('report_error') . "\";");
            output_page($report);
            exit;
//			error($lang->myshowcase_report_error);
        } else {
            eval("\$report = \"" . $templates->get('report_thanks') . "\";");
            output_page($report);
            exit;
//			$item_viewcode = str_replace('{gid}', $mybb->input['gid'], SHOWCASE_URL_VIEW);
//			$redirect_newshowcase = $lang->myshowcase_report_success.''.$lang->redirect_myshowcase_back.''.$lang->sprintf($lang->redirect_myshowcase_return, $showcase_url);
//			redirect($item_viewcode, $redirect_newshowcase);
//			exit;
        }
        break;
    }

    case 'reports':
    {
        add_breadcrumb($lang->myshowcase_reports, SHOWCASE_URL);

        if (!$me->userperms['canmodedit'] && !$me->userperms['canmoddelete'] && !$me->userperms['canmoddelcomment']) {
            error_no_permission();
        }

        $lang->load('modcp');

        if (!$mybb->settings['threadsperpage']) {
            $mybb->settings['threadsperpage'] = 20;
        }

        // Figure out if we need to display multiple pages.
        $perpage = $mybb->settings['threadsperpage'];
        if ($mybb->input['page'] != 'last') {
            $page = intval($mybb->input['page']);
        }

        $query = $db->simple_select('myshowcase_reports', 'COUNT(rid) AS count', "status ='0' AND id=" . $me->id);
        $report_count = $db->fetch_field($query, 'count');

        $mybb->input['rid'] = intval($mybb->input['rid']);

        if ($mybb->input['rid']) {
            $query = $db->simple_select(
                'myshowcase_reports',
                'COUNT(rid) AS count',
                "rid <= '" . $mybb->input['rid'] . "' AND id=" . $me->id
            );
            $result = $db->fetch_field($query, 'count');
            if (($result % $perpage) == 0) {
                $page = $result / $perpage;
            } else {
                $page = intval($result / $perpage) + 1;
            }
        }
        $postcount = intval($report_count);
        $pages = $postcount / $perpage;
        $pages = ceil($pages);

        if ($mybb->input['page'] == 'last') {
            $page = $pages;
        }

        if ($page > $pages || $page <= 0) {
            $page = 1;
        }

        if ($page && $page > 0) {
            $start = ($page - 1) * $perpage;
        } else {
            $start = 0;
            $page = 1;
        }
        $upper = $start + $perpage;

        $multipage = multipage($postcount, $perpage, $page, $me->mainfile . '?action=reports');
        if ($postcount > $perpage) {
            eval("\$reportspages = \"" . $templates->get('myshowcase_reports_multipage') . "\";");
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
			LIMIT {$start}, {$perpage}
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

            $reportdate = my_date($mybb->settings['dateformat'], $report['dateline']);
            $reporttime = my_date($mybb->settings['timeformat'], $report['dateline']);
            eval("\$reports .= \"" . $templates->get('myshowcase_reports_report') . "\";");
        }
        if (!$reports) {
            eval("\$reports = \"" . $templates->get('modcp_reports_noreports') . "\";");
        }

        $showcase_file = SHOWCASE_URL;
        eval("\$showcase_page = \"" . $templates->get('myshowcase_reports') . "\";");

        break;
    }

    case 'do_reports':
    {
        if (!$mybb->request_method == 'post') {
            error_no_permission();
        }

        $lang->load('modcp');

        // Verify incoming POST request
        verify_post_check($mybb->input['my_post_key']);

        if (!is_array($mybb->input['reports'])) {
            error($lang->error_noselected_reports);
        }

        $mybb->input['reports'] = array_map('intval', $mybb->input['reports']);
        $rids = implode($mybb->input['reports'], "','");
        $rids = "'0','{$rids}'";

        $db->update_query('myshowcase_reports', array('status' => 1), "rid IN ({$rids}) AND id=" . $me->id);

        $page = intval($mybb->input['page']);

        redirect(SHOWCASE_URL . "?action=reports&page={$page}", $lang->redirect_reportsmarked);

        break;
    }

    case 'allreports':
    {
        add_breadcrumb($lang->myshowcase_reports, SHOWCASE_URL);

        if (!$me->userperms['canmodedit'] && !$me->userperms['canmoddelete'] && !$me->userperms['canmoddelcomment']) {
            error_no_permission();
        }

        $lang->load('modcp');

        if (!$mybb->settings['threadsperpage']) {
            $mybb->settings['threadsperpage'] = 20;
        }

        // Figure out if we need to display multiple pages.
        $perpage = $mybb->settings['threadsperpage'];
        if ($mybb->input['page'] != 'last') {
            $page = intval($mybb->input['page']);
        }

        $query = $db->simple_select('myshowcase_reports', 'COUNT(rid) AS count', 'id=' . $me->id);
        $report_count = $db->fetch_field($query, 'count');

        $mybb->input['rid'] = intval($mybb->input['rid']);

        if ($mybb->input['rid']) {
            $query = $db->simple_select(
                'myshowcase_reports',
                'COUNT(rid) AS count',
                "rid <= '" . $mybb->input['rid'] . "' AND id=" . $me->id
            );
            $result = $db->fetch_field($query, 'count');
            if (($result % $perpage) == 0) {
                $page = $result / $perpage;
            } else {
                $page = intval($result / $perpage) + 1;
            }
        }
        $postcount = intval($report_count);
        $pages = $postcount / $perpage;
        $pages = ceil($pages);

        if ($mybb->input['page'] == 'last') {
            $page = $pages;
        }

        if ($page > $pages || $page <= 0) {
            $page = 1;
        }

        if ($page && $page > 0) {
            $start = ($page - 1) * $perpage;
        } else {
            $start = 0;
            $page = 1;
        }
        $upper = $start + $perpage;

        $multipage = multipage($postcount, $perpage, $page, $me->mainfile . '?action=allreports');
        if ($postcount > $perpage) {
            eval("\$reportspages = \"" . $templates->get('myshowcase_reports_multipage') . "\";");
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
			LIMIT {$start}, {$perpage}
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

            $reportdate = my_date($mybb->settings['dateformat'], $report['dateline']);
            $reporttime = my_date($mybb->settings['timeformat'], $report['dateline']);
            eval("\$reports .= \"" . $templates->get('myshowcase_reports_allreport') . "\";");
        }
        if (!$reports) {
            eval("\$reports = \"" . $templates->get('modcp_reports_noreports') . "\";");
        }

        $showcase_file = SHOWCASE_URL;
        eval("\$showcase_page = \"" . $templates->get('myshowcase_allreports') . "\";");

        break;
    }
}
?>
