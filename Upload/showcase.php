<?php
/**
 * MyShowcase Plugin for MyBB - Frontend File
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: <showcase>.php (this file is renamed for multiple showcase versions)
 *
 */

declare(strict_types=1);

/*
 * Only user edits required
*/

use inc\plugins\myshowcase\System\Output;
use inc\plugins\myshowcase\System\Render;
use inc\plugins\myshowcase\Showcase;

use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\attachmentRemove;
use function MyShowcase\Core\attachmentUpdate;
use function MyShowcase\Core\attachmentUpload;
use function MyShowcase\Core\cacheGet;
use function MyShowcase\Core\commentDelete;
use function MyShowcase\Core\commentGet;
use function MyShowcase\Core\commentInsert;
use function MyShowcase\Core\dataTableStructureGet;
use function MyShowcase\Core\loadLanguage;
use function MyShowcase\Core\getTemplate;
use function MyShowcase\Core\showcaseDataGet;
use function MyShowcase\Core\showcaseDataUpdate;
use function MyShowcase\Core\urlHandlerSet;

use const MyShowcase\Core\TABLES_DATA;
use const MyShowcase\Core\CACHE_TYPE_FIELDS;
use const MyShowcase\Core\VERSION_CODE;
use const MyShowcase\ROOT;

$forumdir = ''; //no trailing slash

/*
 * Stop editing
*/

define('IN_MYBB', 1);
define('IN_SHOWCASE', 1);
define('VERSION_OF_FILE', 3000);

$filename = substr($_SERVER['SCRIPT_NAME'], -mb_strpos(strrev($_SERVER['SCRIPT_NAME']), '/'));

define('THIS_SCRIPT', $filename);

$current_dir = getcwd();

//change working directory to allow board includes to work
$forumdirslash = ($forumdir == '' ? '' : $forumdir . '/');
$change_dir = './';

if (!chdir($forumdir) && !empty($forumdir)) {
    if (is_dir($forumdir)) {
        $change_dir = $forumdir;
    } else {
        die("\$forumdir is invalid!");
    }
}

//setup templates
$templatelist = 'myshowcase_list, myshowcase_list_empty, myshowcase_list_items, myshowcase_list_no_results, myshowcase_orderarrow, ';
$templatelist .= 'myshowcase_view, myshowcase_view_attachments, myshowcase_view_comments, myshowcase_view_comments_add, myshowcase_view_comments_add_login, ';
$templatelist .= 'myshowcase_view_comments_admin, myshowcase_view_comments_none, myshowcase_view_data, myshowcase_pageMainTableTheadRowInlineModeration, myshowcase_inlinemod, ';
$templatelist .= 'myshowcase_orderarrow, myshowcase_pageMainTableTheadRowField, multipage_page_current, multipage_page, multipage_end, ';
$templatelist .= 'multipage_nextpage, multipage, myshowcase_pageMainTableRowsExtra, myshowcase_inlinemod_item, myshowcase_list_items, myshowcase_list, ';
$templatelist .= 'myshowcase_top, myshowcase_new_button, myshowcase_field_date, myshowcase_js_header, ';
$templatelist .= 'myshowcase_view_admin_edit, myshowcase_view_admin_delete, myshowcase_view_admin, myshowcase_table_header, myshowcase_view_data_1, myshowcase_view_data_2, myshowcase_view_data_3, myshowcase_view_attachments_image, ';
$templatelist .= 'myshowcase_new_attachments_input, myshowcase_new_attachments, myshowcase_new_top, myshowcase_field_textbox, myshowcase_new_fields, myshowcase_field_db, myshowcase_field_textarea, myshowcase_new_bottom, ';

//get MyBB stuff
require_once $change_dir . '/global.php';

//change directory back to current where script is
chdir($current_dir);

//make sure this file is current
if (VERSION_OF_FILE < VERSION_CODE) {
    error(
        'This file is not the same version as the MyShowcase System. Please be sure to upload and configure ALL files.'
    );
}

//adjust theme settings in case this file is outside mybb_root
global $theme, $templates;
$theme['imgdir'] = $forumdirslash . substr($theme['imgdir'], 0);
$theme['imglangdir'] = $forumdirslash . substr($theme['imglangdir'], 0);

//start by constructing the showcase
require_once ROOT . '/class_showcase.php';
$me = new Showcase();

urlHandlerSet($me->mainfile);

// Load global language phrases
//global $showcaseName, $showcase_lower;

//try to load showcase specific language file
loadLanguage();
loadLanguage('myshowcase' . $me->id, false, true);
/*
//if loaded then this will be set, if not load generic lang file
if($lang->myshowcase == '')
{
                \MyShowcase\Core\loadLanguage();
}*/

global $lang, $mybb, $cache, $db, $plugins;

$buttonGo = &$gobutton;

$mybb->settings['myshowcase_file'] = THIS_SCRIPT;

$entryID = $mybb->input['gid'] = $mybb->get_input('gid', MyBB::INPUT_INT);

$attachmentID = $mybb->input['aid'] = $mybb->get_input('aid', MyBB::INPUT_INT);

$commentID = $mybb->input['cid'] = $mybb->get_input('cid', MyBB::INPUT_INT);

$entryHash = $mybb->input['posthash'] = $mybb->get_input('posthash');

$currentUserID = (int)$mybb->user['uid'];

$lang->nav_myshowcase = $lang->myshowcase = $showcaseName = ucwords(strtolower($me->name));
$showcase_lower = strtolower($me->name);

//check if this showcase is enabled
if (!$me->enabled) {
    error($lang->myshowcase_disabled);
}

$orderInput = [
    'username' => '',
    'comments' => '',
    'views' => '',
    'dateline' => ''
];

//get this showcase's field info
$fieldcache = cacheGet(CACHE_TYPE_FIELDS);

$showcaseFieldsShow =
$showcaseFieldsFormat =
$showcaseFieldsParseable =
$showcaseFields = [];

$showcaseFieldsSearchable = [
    'username' => 'username',
    'views' => 'views',
    'comments' => 'comments',
    'dateline' => 'dateline'
];

foreach ($fieldcache[$me->fieldsetid] as $field) {
    $showcaseFields[$field['name']] = $field['html_type'];

    $showcaseFieldsFormat[$field['name']] = $field['format'];

    $showcaseFieldsMaximumLength[$field['name']] = $field['max_length'];
    $showcaseFieldsMinimumLength[$field['name']] = $field['min_length'];

    //limit array only to those fields that are required
    if (!empty($field['enabled']) || !empty($field['requiredField'])) {
        $showcaseFieldEnabled[$field['name']] = $field['html_type'];
    }

    //limit array only to those fields that are required
    if (!empty($field['requiredField'])) {
        $showcaseFieldsRequired[$field['name']] = 1;
    } else {
        $showcaseFieldsRequired[$field['name']] = 0;
    }

    //limit array to those fields to show in the list of showcases
    if ($field['list_table_order'] != -1) {
        //$showcaseFieldsShow[$field['list_table_order']] = $field['name'];
    }

    $showcaseFieldsShow[$field['list_table_order']] = $field['name'];

    //limit array to searchable fields
    if (!empty($field['searchable'])) {
        $showcaseFieldsSearchable[$field['field_order']] = $field['name'];
    }

    //limit array to searchable fields
    if (!empty($field['parse'])) {
        $showcaseFieldsParseable[$field['name']] = 1;
    } else {
        $showcaseFieldsParseable[$field['name']] = 0;
    }

    $orderInput[$field['name']] = '';
}

//sort array of searchable fields by their field order
ksort($showcaseFieldsSearchable);

//sort array of header fields by their list display order
ksort($showcaseFieldsShow);

$showcaseFieldsOrder = [
    'dateline' => $lang->myShowcaseMainSortDateline,
    'dateline' => $lang->myShowcaseMainSortEditDate,
    'username' => $lang->myShowcaseMainSortUsername,
    'views' => $lang->myShowcaseMainSortViews,
    'comments' => $lang->myShowcaseMainSortComments
];

foreach ($showcaseFieldsShow as $forder => $fname) {
    $showcaseFieldsOrder[$fname] = $lang->{"myshowcase_field_{$fname}"} ?? ucfirst($fname);
}

$urlParams = [];

$unapproved = $mybb->input['unapproved'] = $mybb->get_input('unapproved', MyBB::INPUT_INT);

if ($unapproved) {
    $urlParams['unapproved'] = $unapproved;
}

$showcaseInputSortBy = $mybb->get_input('sort_by');

if (!array_key_exists($showcaseInputSortBy, $showcaseFieldsOrder)) {
    $showcaseInputSortBy = 'dateline';
}

if (array_key_exists($showcaseInputSortBy, $showcaseFieldsOrder)) {
    $urlParams['sort_by'] = $showcaseInputSortBy;
}

$showcaseInputSearchExactMatch = $mybb->input['exact_match'] = $mybb->get_input('exact_match');

if ($showcaseInputSearchExactMatch) {
    $urlParams['exact_match'] = $showcaseInputSearchExactMatch;
}

$showcaseInputSearchKeywords = $mybb->get_input('keywords');

if ($showcaseInputSearchKeywords) {
    $urlParams['keywords'] = $showcaseInputSearchKeywords;
}

$showcaseInputSearchField = $mybb->input['search_field'] = $mybb->get_input('search_field');

if (in_array($showcaseInputSearchField, $showcaseFieldsSearchable)) {
    $urlParams['search_field'] = $showcaseInputSearchField;
}

$showcaseInputOrder = $mybb->input['order'] = $mybb->get_input('order');

if (in_array($showcaseInputOrder, ['asc', 'desc'])) {
    $urlParams['order'] = $showcaseInputOrder;
} else {
    $showcaseInputOrder = '';
}

$currentPage = $mybb->input['page'] = $mybb->get_input('page', MyBB::INPUT_INT);

if ($currentPage) {
    $urlParams['page'] = $currentPage;
}

//if we have search_field handle search_field term highlighting
$showcaseInputHighlight = $mybb->input['highlight'] = $mybb->get_input('highlight');

/* URL Definitions */

$URLStart = $mybb->settings['bburl'] . '/';
if ($forumdir != '' && $forumdir != '.') {
    $URLStart = $mybb->settings['homeurl'] . '/';
}

if ($me->seo_support) {
    $showcase_name = strtolower($me->name);
    define('SHOWCASE_URL', $me->clean_name . '.html');
    define('SHOWCASE_URL_PAGED', $me->clean_name . '-page-{page}.html');
    define('SHOWCASE_URL_VIEW', $me->clean_name . '-view-{gid}.html');
    define('SHOWCASE_URL_COMMENT', $me->clean_name . '-view-{gid}-last-comment.html');
    define('SHOWCASE_URL_NEW', $me->clean_name . '-new.html');
    define('SHOWCASE_URL_VIEW_ATTACH', $me->clean_name . '-attachment-{aid}.html');
    define('SHOWCASE_URL_ITEM', $me->clean_name . '-item-{aid}.php');
    $amp = '?';
} else {
    define('SHOWCASE_URL', $me->prefix . '.php');
    define('SHOWCASE_URL_PAGED', $me->prefix . '.php?page={page}');
    define('SHOWCASE_URL_VIEW', $me->prefix . '.php?action=view&gid={gid}');
    define('SHOWCASE_URL_COMMENT', $me->prefix . '.php?action=view&gid={gid}&action=lastComment');
    define('SHOWCASE_URL_NEW', $me->prefix . '.php?action=new');
    define('SHOWCASE_URL_VIEW_ATTACH', $me->prefix . '.php?action=attachment&aid={aid}');
    define('SHOWCASE_URL_ITEM', $me->prefix . '.php?action=item&aid={aid}');
    $amp = '&amp;';
}

$urlBase = $mybb->settings['bburl'];

$urlShowcase = $me->mainfile;

$urlSort = SHOWCASE_URL . (my_strpos(SHOWCASE_URL, '?') ? $amp : '?');

// Check if the active user is a moderator and get the inline moderation tools.
$showcaseColumnsCount = 5;

if ($me->userperms['canmodapprove'] || $me->userperms['canmoddelete']) {
    ++$showcaseColumnsCount;
}

if ($me->userperms['canmodapprove']) {
    $list_where_clause = '(g.approved=0 or g.approved=1)';
    if ($unapproved == 1) {
        $list_where_clause = 'g.approved=0';
    }
    $inlinecount = 0;
    $showcaseTableTheadInlineModeration = eval(getTemplate('pageMainTableTheadRowInlineModeration'));

    ++$showcaseColumnsCount;

    $customthreadtools = '';

    $showcaseInlineModeration = eval(getTemplate('inlinemod'));
} else {
    $ismod = false;
    $list_where_clause = '(g.approved=1 OR g.uid=' . $currentUserID . ')';
}

//handle image output here for performance reasons since we dont need fields and stuff
if ($mybb->get_input('action') == 'item') {
    $aid = intval($attachmentID);

    $attachment = attachmentGet(["aid='{$aid}'"], array_keys(TABLES_DATA['myshowcase_attachments']), ['limit' => 1]);

    // Error if attachment is invalid or not visible
    if (!$attachment['aid'] || !$attachment['attachname'] || (!$ismod && $attachment['visible'] != 1)) {
        error($lang->error_invalidattachment);
    }

    if (!$me->allow_attachments || !$me->userperms['canviewattach']) {
        error_no_permission();
    }

    $attachmentExtension = get_extension($attachment['filename']);

    switch ($attachment['filetype']) {
        case 'application/pdf':
        case 'image/bmp':
        case 'image/gif':
        case 'image/jpeg':
        case 'image/pjpeg':
        case 'image/png':
        case 'text/plain':
            header("Content-type: {$attachment['filetype']}");
            $disposition = 'inline';
            break;

        default:
            header('Content-type: application/force-download');
            $disposition = 'attachment';
    }

    if (my_strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'msie') !== false) {
        header("Content-disposition: attachment; filename=\"{$attachment['filename']}\"");
    } else {
        header("Content-disposition: {$disposition}; filename=\"{$attachment['filename']}\"");
    }

    if (my_strpos(strtolower($_SERVER['HTTP_USER_AGENT']), 'msie 6.0') !== false) {
        header('Expires: -1');
    }

    header("Content-length: {$attachment['filesize']}");
    header('Content-range: bytes=0-' . ($attachment['filesize'] - 1) . '/' . $attachment['filesize']);

    $plugins->run_hooks('myshowcase_image');

    echo file_get_contents($me->imgfolder . '/' . $attachment['attachname']);
    die();
}

//here for performance since we dont need the fields and other stuff
//this block is only used if user disables JS or if admin removes FancyBox code
if ($mybb->get_input('action') == 'attachment') {
    $aid = intval($attachmentID);

    $attachment = attachmentGet(["aid='{$aid}'"], array_keys(TABLES_DATA['myshowcase_attachments']), ['limit' => 1]);

    // Error if attachment is invalid or not visible
    if (!$attachment['aid'] || !$attachment['attachname'] || (!$ismod && $attachment['visible'] != 1)) {
        error($lang->error_invalidattachment);
    }

    if (!$me->allow_attachments || !$me->userperms['canviewattach']) {
        error_no_permission();
    }

    $plugins->run_hooks('myshowcase_attachment_start');

    attachmentUpdate(["aid='{$aid}'"], ['downloads' => $attachment['downloads'] + 1]);

    if (stristr($attachment['filetype'], 'image/')) {
        $posterdata = get_user($attachment['uid']);

        $showcase_viewing_user = str_replace('{username}', $posterdata['username'], $lang->myshowcase_viewing_user);

        add_breadcrumb($showcase_viewing_user, str_replace('{gid}', $attachment['gid'], SHOWCASE_URL_VIEW));

        $attachment['filename'] = rawurlencode($attachment['filename']);

        $plugins->run_hooks('myshowcase_attachment_end');

        $showcase_viewing_attachment = str_replace(
            '{username}',
            $posterdata['username'],
            $lang->myshowcase_viewing_attachment
        );
        add_breadcrumb($showcase_viewing_attachment, str_replace('{gid}', $attachment['gid'], SHOWCASE_URL_VIEW));

        $showcase_header_label = $showcase_viewing_attachment;

        $lasteditdate = my_date($mybb->settings['dateformat'], $attachment['dateuploaded']);
        $lastedittime = my_date($mybb->settings['timeformat'], $attachment['dateuploaded']);
        $entryDateline = $lasteditdate . '&nbsp;' . $lastedittime;

        $showcase_attachment_description = $lang->myshowcase_attachment_filename . $attachment['filename'] . '<br />' . $lang->myshowcase_attachment_uploaded . $entryDateline;
        $showcase_table_header = eval(getTemplate('table_header'));
        $showcase_attachment = str_replace(
            '{aid}',
            $attachment['aid'],
            SHOWCASE_URL_ITEM
        );//$me->imgfolder."/".$attachment['attachname'];
        $pageContents = eval(getTemplate('attachment_view'));

        $plugins->run_hooks('myshowcase_attachment_end');
        output_page($pageContents);
        die();
    } else //should never really be called, but just incase, support inline output
    {
        header('Cache-Control: private', false);
        header('Content-Type: ' . $attachment['filetype']);
        header('Content-Description: File Transfer');
        header('Content-Disposition: inline; filename=' . $attachment['filename']);
        header('Content-Length: ' . $attachment['filesize']);
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        ob_clean();
        flush();
        readfile($me->imgfolder . '/' . $attachment['attachname']);
        die();
    }
}

//need a few items from the index language file
loadLanguage('index');

//load language file specific to this showcase's assigned fieldset
loadLanguage('myshowcase_fs' . $me->fieldsetid, false, true); // 3.0.0 TODO

//see if current user can view this showcase
if (!$me->userperms['canview']) {
    error_no_permission();
}

//init time
$dateline = TIME_NOW;

//make var for JS in template
$showcase_url = SHOWCASE_URL;

//add initial showcase breadcrumb
//$navbits = array();
add_breadcrumb($lang->nav_myshowcase, SHOWCASE_URL);

//process cancel button
if (isset($mybb->input['cancel']) && $mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    if (!$entryID) {
        attachmentRemove($me, $entryHash);
    }

    if ($mybb->get_input('action') == 'do_editshowcase' || $mybb->get_input('action') == 'do_newshowcase') {
        $mybb->input['action'] = 'view';
    }
}

//get count of existing attachments if editing (posthash sent)
$current_attach_count = 0;
if ($entryHash != '' && $mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $current_attach_count = attachmentGet(
        ["posthash='{$db->escape_string($entryHash)}'"],
        ['COUNT(aid) as totalAttachments'],
        ['limit' => 1]
    )['totalAttachments'] ?? 0;
}

$plugins->run_hooks('myshowcase_start');

//process new/updated attachments
if (!$mybb->get_input(
        'attachmentaid',
        MyBB::INPUT_INT
    ) && ($mybb->get_input('newattachment') || $mybb->get_input('updateattachment') || (($mybb->get_input(
                    'action'
                ) == 'do_newshowcase' || $mybb->get_input(
                    'action'
                ) == 'do_editshowcase') && $mybb->get_input(
                'submit'
            ) && isset($_FILES['attachment']))) && $mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $can_add_attachments = $me->userperms['canattach'];
    $attach_limit = $me->userperms['attachlimit'];
    $showcase_uid = $currentUserID;

    //if a mod is editing someone elses showcase, get orig authors perms
    if ($mybb->get_input('action') == 'do_editshowcase' && $currentUserID != $mybb->get_input(
            'authid',
            MyBB::INPUT_INT
        )) {
        //get showcase author info
        $showcase_uid = (int)$mybb->get_input('authid', MyBB::INPUT_INT);
        $showcase_user = get_user($showcase_uid);

        //get permissions for author
        $showcase_authorperms = $me->get_user_permissions($showcase_user);

        $can_add_attachments = $showcase_authorperms['canattach'];
        $attach_limit = $showcase_authorperms['attachlimit'];
    }

    // If there's an attachment, check it and upload it.
    if (($attach_limit == -1 || ($attach_limit != -1 && $current_attach_count < $attach_limit)) && $can_add_attachments) {
        if ($_FILES['attachment']['size'] > 0) {
            $update_attachment = false;
            if ($mybb->get_input('updateattachment')) {
                $update_attachment = true;
            }
            $attachedfile = attachmentUpload(
                $me,
                $_FILES['attachment'],
                $entryHash,
                $update_attachment,
                (bool)$mybb->get_input('watermark', MyBB::INPUT_INT),
                $entryID
            );
        }
        if ($attachedfile['error']) {
            $attacherror = eval($templates->render('error_attacherror'));
            $mybb->input['action'] = 'new';
        }
    }

    if (!$mybb->get_input('submit')) {
        if ($entryID && $entryID != '') {
            $mybb->input['action'] = 'do_editshowcase';
        } else {
            $mybb->input['action'] = 'do_newshowcase';
        }
    }
}

// Remove an attachment.
if ($mybb->get_input('attachmentaid', MyBB::INPUT_INT) && $entryHash &&
    ($me->userperms['canedit'] || $me->userperms['canmodedit']) && $mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    attachmentRemove(
        $me,
        $entryHash,
        $mybb->get_input('attachmentaid', MyBB::INPUT_INT)
    );

    if (!$mybb->get_input('submit')) {
        if ($entryID && $entryID != '') {
            $mybb->input['action'] = 'do_editshowcase';
        } else {
            $mybb->input['action'] = 'do_newshowcase';
        }
    }
}

//setup add comment
if (!$mybb->get_input('commentcid', MyBB::INPUT_INT) && $mybb->get_input('addcomment') && $entryHash) {
    $mybb->input['action'] = 'addcomment';
}

//setup remove comment
if ($mybb->get_input('commentcid', MyBB::INPUT_INT) && $mybb->get_input('remcomment') && $entryHash) {
    $mybb->input['action'] = 'delcomment';
    $mybb->input['cid'] = $mybb->get_input('commentcid', MyBB::INPUT_INT);
}

//deal with admin buttons from view page
if ($mybb->get_input('showcasegid') && $entryHash) {
    if ($mybb->get_input('showcaseact') == 'remove') {
        $mybb->input['action'] = 'delete';
    }

    if ($mybb->get_input('showcaseact') == 'edit') {
        $mybb->input['action'] = 'edit';
    }
}

//init dynamic field info
$showcaseFieldEnabled = [];
$showcaseFieldsMaximumLength = [];
$showcaseFieldsRequired = [
    'uid' => 1,
];

$showcaseFieldsMinimumLength = [];

//clean up/default expected inputs
if (empty($mybb->get_input('action'))) {
    $mybb->input['action'] = 'list';
}
$mybb->input['action'] = $db->escape_string($mybb->get_input('action'));


if (!$mybb->get_input('showall', MyBB::INPUT_INT) || $mybb->get_input('showall', MyBB::INPUT_INT) != 1) {
    $mybb->input['showall'] = 0;
}

// Setup our posthash for managing attachments.
if (!$entryHash) {
    $entryHash = md5(($entryID . $currentUserID) . random_str());
}

//init form action

//get FancyBox JS for header if viewing
$myshowcase_js_header = '';

if ($mybb->get_input('action') == 'view') {
    $myshowcase_js_header = eval(getTemplate('js_header'));
}

$showcase_top = eval(getTemplate('top'));

$pagination = '';

require_once ROOT . '/System/Render.php';
require_once ROOT . '/System/Output.php';

$myShowcaseRender = new Render($me);

$myShowcaseOutput = new Output($me);

//main showcase code
switch ($mybb->get_input('action')) {
    case 'view':
    {
        $plugins->run_hooks('myshowcase_view_start');

        if ($entryID == '' || $entryID == 0) {
            error($lang->myshowcase_invalid_id);
        }

        $addon_join = '';
        $addon_fields = '';
        reset($showcaseFields);

        $view_where_clause = '';

        foreach ($showcaseFieldEnabled as $fname => $ftype) {
            if ($ftype == 'db' || $ftype == 'radio') {
                $addon_join .= ' LEFT JOIN ' . TABLE_PREFIX . 'myshowcase_field_data tbl_' . $fname . ' ON (tbl_' . $fname . '.valueid = g.' . $fname . ' AND tbl_' . $fname . ".name = '" . $fname . "') ";
                $addon_fields .= ', tbl_' . $fname . '.value AS `' . $fname . '`';
                $view_where_clause .= ' AND tbl_' . $fname . '.setid = ' . $me->fieldsetid;
            } else {
                $addon_fields .= ', `' . $fname . '`';
            }
        }
        // start getting showcase base data
        $query = $db->query(
            '
			SELECT `gid`, g.uid, `username`, `views`, `comments`, `dateline`, `approved`, `approved_by`, `posthash`' . $addon_fields . '
			FROM ' . TABLE_PREFIX . $me->table_name . ' g
			LEFT JOIN ' . TABLE_PREFIX . 'users u ON (u.uid = g.uid)
			' . $addon_join . '
			WHERE g.gid=' . $entryID . $view_where_clause
        );

        if ($db->num_rows($query) == 0) {
            error($lang->myshowcase_invalid_id);
        }

        $showcase = $db->fetch_array($query);

        if ($showcase['username'] == '') {
            $showcase['username'] = $lang->guest;
            $showcase['uid'] = 0;
        }

        $showcase_viewing_user = str_replace('{username}', $showcase['username'], $lang->myshowcase_viewing_user);
        add_breadcrumb($showcase_viewing_user, SHOWCASE_URL);

        //set up jump to links
        $jumpto = $lang->myshowcase_jumpto;

        $entryUrl = str_replace('{gid}', (string)$mybb->get_input('gid'), SHOWCASE_URL_VIEW);
        if ($me->allow_attachments && $me->userperms['canviewattach']) {
            $jumpto .= ' <a href="' . $entryUrl . ($mybb->get_input(
                    'showall',
                    MyBB::INPUT_INT
                ) == 1 ? '&showall=1' : '') . '#images">' . $lang->myshowcase_attachments . '</a>';
        }

        if ($me->allow_comments && $me->userperms['canviewcomment']) {
            $jumpto .= ' <a href="' . $entryUrl . ($mybb->get_input(
                    'showall',
                    MyBB::INPUT_INT
                ) == 1 ? '&showall=1' : '') . '#comments">' . $lang->myShowcaseMainTableTheadComments . '</a>';
        }

        $jumptop = '(<a href="' . $entryUrl . ($mybb->get_input(
                'showall',
                MyBB::INPUT_INT
            ) == 1 ? '&showall=1' : '') . '#top">' . $lang->myshowcase_top . '</a>)';

        $entryHash = $showcase['posthash'];

        $showcase_gid = $entryID;
        $showcase_views = $showcase['views'];
        $showcase_numcomments = $showcase['comments'];

        $showcase_header_label = $lang->myshowcase_specifications;
        $showcase_header_jumpto = $jumpto;

        $showcase_admin_url = SHOWCASE_URL;

        if ($me->userperms['canmodedit'] || ($showcase['uid'] == $currentUserID && $me->userperms['canedit'])) {
            $showcase_view_admin_edit = eval(getTemplate('view_admin_edit'));
        }

        if ($me->userperms['canmoddelete'] || ($showcase['uid'] == $currentUserID && $me->userperms['canedit'])) {
            $showcase_view_admin_delete = eval(getTemplate('view_admin_delete'));
        }

        if ($showcase_view_admin_edit != '' || $showcase_view_admin_delete != '') {
            $showcase_header_special = eval(getTemplate('view_admin'));
        }

        $showcase_data_header = eval(getTemplate('table_header'));

        //trick MyBB into thinking its in archive so it adds bburl to smile link inside parser
        //doing this now should not impact anyhting. no issues with gomobile beta4
        define('IN_ARCHIVE', 1);

        reset($showcaseFieldEnabled);
        $alternativeBackground = 'trow2';

        $showcase_data = '';

        foreach ($showcaseFieldEnabled as $fname => $ftype) {
            $temp = 'myshowcase_field_' . $fname;
            $field_header = !empty($lang->$temp) ? $lang->$temp : $fname;

            $alternativeBackground = ($alternativeBackground == 'trow1' ? 'trow2' : 'trow1');

            //set parser options for current field

            switch ($ftype) {
                case 'textarea':
                    $field_data = $showcase[$fname];
                    if ($field_data != '' || $me->disp_empty == 1) {
                        if ($showcaseFieldsParseable[$fname] || $showcaseInputHighlight) {
                            $field_data = $me->parse_message($field_data, ['highlight' => $showcaseInputHighlight]);
                        } else {
                            $field_data = htmlspecialchars_uni($field_data);
                            $field_data = nl2br($field_data);
                        }
                        $showcase_data .= eval(getTemplate('view_data_2'));
                    }
                    break;

                case 'textbox':
                    $field_data = $showcase[$fname];

                    //format numbers as requested
                    switch ($showcaseFieldsFormat[$fname]) {
                        case 'no':
                            $field_data = $field_data;
                            break;

                        case 'decimal0':
                            $field_data = number_format(floatval($field_data));
                            break;

                        case 'decimal1':
                            $field_data = number_format(floatval($field_data), 1);
                            break;

                        case 'decimal2':
                            $field_data = number_format(floatval($field_data), 2);
                            break;
                    }

                    if ($field_data != '' || $me->disp_empty == 1) {
                        $field_data = htmlspecialchars_uni($field_data);
                        if ($showcaseFieldsParseable[$fname] || $showcaseInputHighlight) {
                            $field_data = $me->parse_message($field_data, ['highlight' => $showcaseInputHighlight]);
                        }
                        $showcase_data .= eval(getTemplate('view_data_1'));
                    }
                    break;

                case 'url':
                    $field_data = $showcase[$fname];
                    if ($field_data != '' || $me->disp_empty == 1) {
                        $field_data = $parser->mycode_parse_url($showcase[$fname]);
                        $showcase_data .= eval(getTemplate('view_data_2'));
                    }
                    break;

                case 'date':
                    $field_data = $showcase[$fname];
                    if ($field_data != '') {
                        $date_bits = explode('|', $showcase[$fname]);
                        $date_bits = array_map('intval', $date_bits);

                        if ($date_bits[0] > 0 && $date_bits[1] > 0 && $date_bits[2] > 0) {
                            $field_data = my_date(
                                $mybb->settings['dateformat'],
                                mktime(0, 0, 0, $date_bits[0], $date_bits[1], $date_bits[2])
                            );
                        } else {
                            $field_data = '';
                            if ($date_bits[0]) {
                                $field_data .= $date_bits[0];
                            }
                            if ($date_bits[1]) {
                                $field_data .= ($field_data != '' ? '-' : '') . $date_bits[1];
                            }
                            if ($date_bits[2]) {
                                $field_data .= ($field_data != '' ? '-' : '') . $date_bits[2];
                            }
                        }
                    } else {
                        $field_data = '';
                    }
                    if (($field_data != '') || $me->disp_empty == 1) {
                        $showcase_data .= eval(getTemplate('view_data_1'));
                    }
                    break;

                case 'db':
                    $field_data = $showcase[$fname];
                    if (($field_data != '') || $me->disp_empty == 1) {
                        if ($showcaseFieldsParseable[$fname] || $showcaseInputHighlight) {
                            $field_data = $me->parse_message($field_data, ['highlight' => $showcaseInputHighlight]);
                        }
                        $showcase_data .= eval(getTemplate('view_data_1'));
                    }
                    break;

                case 'radio':
                    $field_data = $showcase[$fname];
                    if (($field_data != '') || $me->disp_empty == 1) {
                        $showcase_data .= eval(getTemplate('view_data_1'));
                    }
                    break;

                case 'checkbox':
                    if (($showcase[$fname] != '') || $me->disp_empty == 1) {
                        if ($showcase[$fname] == 1) {
                            $field_data = '<img src="' . $mybb->settings['bburl'] . '/images/valid.gif" alt="Yes">';
                        } else {
                            $field_data = '<img src="' . $mybb->settings['bburl'] . '/images/invalid.gif" alt="No">';
                        }
                        $showcase_data .= eval(getTemplate('view_data_1'));
                    }
                    break;
            }
        }

        //for moderators, show who last approved the entry
        if ($me->userperms['canmodapprove'] && $showcase['approved']) {
            $field_header = $lang->myshowcase_last_approved;
            $modapproved = get_user($showcase['approved_by']);
            $field_data = build_profile_link($modapproved['username'], $modapproved['uid'], '', '', $forumdir . '/');
            $showcase_data .= eval(getTemplate('view_data_1'));
        }

        //output bottom row for report button and future add-ons
//		$entry_final_row = '<a href="'.SHOWCASE_URL.'?action=report&gid='.$mybb->get_input('gid', \MyBB::INPUT_INT).'"><img src="'.$theme['imglangdir'].'/postbit_report.gif"></a>';
        $entry_final_row = '<a href="javascript:Showcase.reportShowcase(' . $mybb->get_input(
                'gid',
                MyBB::INPUT_INT
            ) . ');"><img src="' . $theme['imglangdir'] . '/postbit_report.gif"></a>';
        $showcase_data .= eval(getTemplate('view_data_3'));

        if ($me->allow_comments && $me->userperms['canviewcomment']) {
            $queryOptions = ['order_by' => 'dateline', 'order_dir' => 'DESC'];

            if (!$mybb->get_input('showall', MyBB::INPUT_INT)) {
                $queryOptions['limit'] = $me->comment_dispinit;
            }

            $commentObjects = commentGet(
                ["gid='{$entryID}'", "id='{$me->id}'"],
                ['uid', 'comment', 'dateline'],
                $queryOptions
            );

            // start getting comments
            $alternativeBackground = 'trow2';

            $showcase_comments = '';

            foreach ($commentObjects as $commentID => $commentData) {
                $alternativeBackground = ($alternativeBackground == 'trow1' ? 'trow2' : 'trow1');

                //clean up comment and timestamp
                $comment_date = my_date($mybb->settings['dateformat'], $commentData['dateline']);
                $comment_time = my_date($mybb->settings['timeformat'], $commentData['dateline']);
                $comment_posted = $comment_date . ' ' . $comment_time;

                $userData = get_user($commentData['uid']);

                $comment_poster = $entryUsername = build_profile_link(
                    $userData['username'],
                    $commentData['uid'],
                    '',
                    '',
                    $forumdir . '/'
                );
                $comment_data = $me->parse_message($commentData['comment'], ['highlight' => $showcaseInputHighlight]);

                //setup comment admin options
                //only mods, original author (if allowed) or owner (if allowed) can delete comments
                if (
                    ($me->userperms['canmoddelcomment']) ||
                    ($commentData['uid'] == $currentUserID && $me->userperms['candelowncomment']) ||
                    ($showcase['uid'] == $currentUserID && $me->userperms['candelauthcomment'])
                ) {
                    $showcase_comments_admin = eval(getTemplate('view_comments_admin'));
                }

                $showcase_comments .= eval(getTemplate('view_comments'));
            }

            $showcase_show_all = '';
            if ($mybb->get_input('showall', MyBB::INPUT_INT) != 1 && $showcase_numcomments > $me->comment_dispinit) {
                $showcase_show_all = '(<a href="' . $entryUrl . $amp . 'showall=1#comments">' . str_replace(
                        '{count}',
                        $showcase['comments'],
                        $lang->myshowcase_comment_show_all
                    ) . '</a>)' . '<br>';
            }

            $showcase_comment_form_url = SHOWCASE_URL;//.'?action=view&gid='.$mybb->get_input('gid', \MyBB::INPUT_INT);
            $showcase_header_label = '<a name="comments"><form action="' . $showcase_comment_form_url . '" method="post" name="comment">' . $lang->myShowcaseMainTableTheadComments . '</a>';
            $showcase_header_jumpto = $jumptop;
            $showcase_header_special = $showcase_show_all;
            $showcase_comment_header = eval(getTemplate('table_header'));

            $alternativeBackground = ($alternativeBackground == 'trow1' ? 'trow2' : 'trow1');
            if ($showcase_comments == '') {
                $showcase_comments = eval(getTemplate('view_comments_none'));
            }

            //check if logged in for ability to add comments
            $alternativeBackground = ($alternativeBackground == 'trow1' ? 'trow2' : 'trow1');
            if (!$currentUserID) {
                $showcase_comments .= eval(getTemplate('view_comments_add_login'));
            } elseif ($me->userperms['cancomment']) {
                $comment_text_limit = str_replace(
                    '{text_limit}',
                    (string)$me->comment_length,
                    $lang->myshowcase_comment_text_limit
                );
                $showcase_comments .= eval(getTemplate('view_comments_add'));
            }
        }

        if ($me->allow_attachments && $me->userperms['canviewattach']) {
            $attachmentObjects = attachmentGet(
                ["gid='{$entryID}'", "id='{$me->id}'"],
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
                            $me->imgfolder . '/' . $gattachments['thumbnail']
                        )) {
                        $item_image = './' . $me->imgfolder . '/' . $gattachments['thumbnail'];
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
                    $showcase['username']
                );

                $showcase_attachment_data .= eval(getTemplate('view_attachments_image'));

                $attach_count++;
                if ($attach_count == $me->disp_attachcols && $me->disp_attachcols != 0) {
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
            'UPDATE ' . TABLE_PREFIX . $me->table_name . ' SET views=views+1 WHERE gid=' . $entryID
        );

        $plugins->run_hooks('myshowcase_view_end');

        $pageContents = eval(getTemplate('view'));

        break;
    }
    case 'report':
    case 'do_report':
    case 'reports':
    case 'do_reports':
    case 'allreports':
        {
            require_once(MYBB_ROOT . 'inc/plugins/myshowcase/report.php');
        }
        break;
    case 'addcomment':
    {
        if (!$currentUserID) {
            error($lang->myshowcase_comments_not_logged_in);
        }

        if ($me->userperms['cancomment'] && $mybb->request_method == 'post') {
            verify_post_check($mybb->get_input('my_post_key'));

            $plugins->run_hooks('myshowcase_add_comment_start');


            if ($entryID == '' || $entryID == 0) {
                error($lang->myshowcase_invalid_id);
            }

            if ($mybb->get_input('comments') == '') {
                error($lang->myshowcase_comment_empty);
            }

            $showcaseUserData = showcaseDataGet($me->id, ["gid='{$entryID}'"], ['uid']);

            if (!$showcaseUserData) {
                error($lang->myshowcase_invalid_id);
            }

            $authorid = $showcaseUserData['uid'];

            //don't trust the myshowcase_data comment count, get the real count at time of insert to cover deletions and edits at same time.
            $totalComments = commentGet(
                ["gid='{$entryID}'", "id='{$me->id}'"],
                ['COUNT(cid) AS totalComments'],
                ['group_by' => 'gid'],
                ['group_by' => 'gid', 'limit' => 1]
            )['totalComments'] ?? 0;

            $mybb->input['comments'] = $db->escape_string($mybb->get_input('comments'));

            if ($mybb->get_input('comments') != '') {
                $comment_insert_data = [
                    'id' => $me->id,
                    'gid' => $entryID,
                    'uid' => $currentUserID,
                    'ipaddress' => get_ip(),
                    'comment' => $mybb->get_input('comments'),
                    'dateline' => TIME_NOW
                ];

                $plugins->run_hooks('myshowcase_add_comment_commit');

                $commentID = commentInsert($comment_insert_data);

                $commentID = $entryID;

                showcaseDataUpdate($this->id, ["gid='{$commentID}'"], ['comments' => $totalComments + 1]);

                //notify showcase owner of new comment by others
                $author = get_user($authorid);
                if ($author['allownotices'] && $author['uid'] != $currentUserID) {
                    $excerpt = $me->parser()->text_parse_message(
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


                    $emailsubject = $lang->sprintf($lang->myshowcase_comment_emailsubject, $me->name);

                    $emailmessage = $lang->sprintf(
                        $lang->myshowcase_comment_email,
                        $author['username'],
                        $mybb->user['username'],
                        $me->name,
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

                $entryUrl = str_replace('{gid}', $entryID, SHOWCASE_URL_VIEW);

                redirect($entryUrl . '#comments', $lang->myshowcase_comment_added);
            }
        } else {
            error($lang->myshowcase_not_authorized);
        }


        break;
    }
    case 'delcomment':
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

        $entryData = showcaseDataGet($me->id, ["gid='{$entryID}'"], ['uid']);

        if (!$entryData) {
            error($lang->myshowcase_invalid_id);
        }

        if (
            (($currentUserID == $commentData['uid'] && $me->userperms['candelowncomment']) ||
                ($currentUserID == $entryData['uid'] && $me->userperms['candelauthcomment']) ||
                ($me->userperms['canmoddelcomment']) && $mybb->request_method == 'post')
        ) {
            verify_post_check($mybb->get_input('my_post_key'));

            commentDelete(["id='{$me->id}'", "cid='{$commentID}'"]);

            $totalComments = commentGet(
                ["gid='{$entryID}'", "id='{$me->id}'"],
                ['COUNT(cid) AS totalComments'],
                ['group_by' => 'gid', 'limit' => 1]
            )['totalComments'] ?? 0;

            $plugins->run_hooks('myshowcase_del_comment_commit');

            showcaseDataUpdate($this->id, ["gid='{$entryID}'"], ['comments' => $totalComments]);

            $entryUrl = str_replace('{gid}', (string)$entryID, SHOWCASE_URL_VIEW);

            redirect($entryUrl . '#comments', $lang->myshowcase_comment_deleted);
        } else {
            error($lang->myshowcase_not_authorized);
        }

        break;
    }
    case 'delete';
    {
        if ($mybb->request_method == 'post') {
            verify_post_check($mybb->get_input('my_post_key'));

            if (!$currentUserID || !$me->userperms['canedit']) {
                error($lang->myshowcase_not_authorized);
            }

            $plugins->run_hooks('myshowcase_delete_start');


            if ($entryID == '' || $entryID == 0) {
                error($lang->myshowcase_invalid_id);
            }

            $dataTableStructure = dataTableStructureGet();

            $showcaseUserData = showcaseDataGet($me->id, ["gid='{$entryID}'"], array_keys($dataTableStructure));

            if (!$showcaseUserData) {
                error($lang->myshowcase_invalid_id);
            }

            if (!$me->userperms['canmoddelete'] && $currentUserID != $showcaseUserData['uid']) {
                error($lang->myshowcase_not_authorized);
            }

            $gid = $showcaseUserData['gid'];
            $me->delete($gid);

            //log_moderator_action($modlogdata, $lang->multi_deleted_threads);

            $plugins->run_hooks('myshowcase_delete_end');
        }
        redirect(SHOWCASE_URL, $lang->redirect_myshowcase_delete);
        exit;
        break;
    }
    case 'edit':
    case 'new':
    case 'do_editshowcase':
    case 'do_newshowcase':
        {
            require_once(MYBB_ROOT . 'inc/plugins/myshowcase/newedit.php');
        }
        break;
    case 'multiapprove':
    case 'multiunapprove':
    case 'multidelete':
    case 'do_multidelete':
        {
            require_once(MYBB_ROOT . 'inc/plugins/myshowcase/inlinemod.php');
        }
        break;
    default:
    {
        $myShowcaseOutput->main();
    }
}

$pageContents = eval(getTemplate('page'));

$plugins->run_hooks('myshowcase_end');

output_page($pageContents);

exit;


//query to get templates

/*
SELECT title, template, -2 as sid , 1600 as version , status, unix_timestamp() as dateline FROM `myforum_templates` WHERE tid in (SELECT distinct max(tid) as tid FROM `myforum_templates` WHERE title like '%showcase%'  group by title order by title, dateline desc)
*/