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

use MyShowcase\System\ModeratorPermissions;
use MyShowcase\System\UserPermissions;

use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\attachmentUpdate;
use function MyShowcase\Core\attachmentUpload;
use function MyShowcase\Core\cacheGet;
use function MyShowcase\Core\loadLanguage;
use function MyShowcase\Core\getTemplate;
use function MyShowcase\Core\outputGetObject;
use function MyShowcase\Core\renderGetObject;
use function MyShowcase\Core\showcaseGetObject;
use function MyShowcase\Core\urlHandlerGet;
use function MyShowcase\Core\urlHandlerSet;

use const MyShowcase\Core\ALL_UNLIMITED_VALUE;
use const MyShowcase\Core\TABLES_DATA;
use const MyShowcase\Core\CACHE_TYPE_FIELDS;
use const MyShowcase\Core\VERSION_CODE;
use const MyShowcase\Core\ERROR_TYPE_NOT_CONFIGURED;
use const MyShowcase\Core\ERROR_TYPE_NOT_INSTALLED;

$forumdir = ''; //no trailing slash

/*
 * Stop editing
*/

const IN_MYBB = true;
const IN_SHOWCASE = true;
const VERSION_OF_FILE = 3000;

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
$templatelist = '';

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

global $mybb;

//start by constructing the showcase
$showcaseObject = showcaseGetObject(THIS_SCRIPT);

$renderObject = renderGetObject($showcaseObject);

$outputObject = outputGetObject($showcaseObject, $renderObject);

if (!$showcaseObject->enabled) {
    match ($this->errorType) {
        ERROR_TYPE_NOT_INSTALLED => error(
            'The MyShowcase System has not been installed and activated yet.'
        ),
        ERROR_TYPE_NOT_CONFIGURED => error(
            'This file is not properly configured in the MyShowcase Admin section of the ACP'
        ),
        default => error_no_permission()
    };
}

// Load global language phrases
//global $showcaseName, $showcase_lower;

//try to load showcase specific language file
loadLanguage();
loadLanguage('myshowcase' . $showcaseObject->id, false, true);
/*
//if loaded then this will be set, if not load generic lang file
if($lang->myshowcase == '')
{
                \MyShowcase\Core\loadLanguage();
}*/

global $lang, $mybb, $cache, $db, $plugins;

$buttonGo = &$gobutton;

$mybb->settings['myshowcase_file'] = $showcaseObject->fileName;

$entryID = $mybb->input['gid'] = $mybb->get_input('gid', MyBB::INPUT_INT);

$attachmentID = $mybb->input['aid'] = $mybb->get_input('aid', MyBB::INPUT_INT);

$commentID = $mybb->input['cid'] = $mybb->get_input('cid', MyBB::INPUT_INT);

$entryHash = $mybb->input['posthash'] = $mybb->get_input('posthash');

$lang->nav_myshowcase = $lang->myshowcase = $showcaseName = ucwords(strtolower($showcaseObject->name));
$showcase_lower = strtolower($showcaseObject->name);

//check if this showcase is enabled
if (!$showcaseObject->enabled) {
    error($lang->myshowcase_disabled);
}

$orderInput = [
    'username' => '',
    'comments' => '',
    'views' => '',
    'dateline' => ''
];

//get this showcase's field info
$showcaseFieldsCache = cacheGet(CACHE_TYPE_FIELDS);

$showcaseFieldsSearchable = [
    'username' => 'username',
    'views' => 'views',
    'comments' => 'comments',
    'dateline' => 'dateline'
];

foreach ($showcaseFieldsCache[$showcaseObject->fieldSetID] as $field) {
    $showcaseFieldsMaximumLength[$field['name']] = $field['max_length'];
    $showcaseFieldsMinimumLength[$field['name']] = $field['min_length'];

    //limit array only to those fields that are required

    //limit array only to those fields that are required
    if (!empty($field['requiredField'])) {
        $showcaseFieldsRequired[$field['name']] = 1;
    } else {
        $showcaseFieldsRequired[$field['name']] = 0;
    }

    //limit array to searchable fields
    if (!empty($field['searchable'])) {
        $showcaseFieldsSearchable[$field['field_order']] = $field['name'];
    }

    $orderInput[$field['name']] = '';
}

//sort array of searchable fields by their field order
ksort($showcaseFieldsSearchable);

$urlParams = [];

$unapproved = $mybb->input['unapproved'] = $mybb->get_input('unapproved', MyBB::INPUT_INT);

if ($unapproved) {
    $urlParams['unapproved'] = $unapproved;
}

$showcaseInputSortBy = $mybb->get_input('sort_by');

if (!array_key_exists($showcaseInputSortBy, $renderObject->fieldSetFieldsDisplayFields)) {
    $showcaseInputSortBy = 'dateline';
}

if (array_key_exists($showcaseInputSortBy, $renderObject->fieldSetFieldsDisplayFields)) {
    $urlParams['sort_by'] = $showcaseInputSortBy;
}

if ($renderObject->searchExactMatch) {
    $urlParams['exact_match'] = $renderObject->searchExactMatch;
}

if ($renderObject->searchKeyWords) {
    $urlParams['keywords'] = $renderObject->searchKeyWords;
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

/* URL Definitions */

$URLStart = $mybb->settings['bburl'] . '/';
if ($forumdir != '' && $forumdir != '.') {
    $URLStart = $mybb->settings['homeurl'] . '/';
}

if ($showcaseObject->friendlyUrlsEnabled) {
    $showcase_name = strtolower($showcaseObject->name);
    define('SHOWCASE_URL', $showcaseObject->cleanName . '.html');
    define('SHOWCASE_URL_PAGED', $showcaseObject->cleanName . '-page-{page}.html');
    define('SHOWCASE_URL_VIEW', $showcaseObject->cleanName . '-view-{gid}.html');
    define('SHOWCASE_URL_COMMENT', $showcaseObject->cleanName . '-view-{gid}-last-comment.html');
    define('SHOWCASE_URL_NEW', $showcaseObject->cleanName . '-new.html');
    define('SHOWCASE_URL_VIEW_ATTACH', $showcaseObject->cleanName . '-attachment-{aid}.html');
    define('SHOWCASE_URL_ITEM', $showcaseObject->cleanName . '-item-{aid}.php');
    $amp = '?';
} else {
    define('SHOWCASE_URL', $showcaseObject->prefix . '.php');
    define('SHOWCASE_URL_PAGED', $showcaseObject->prefix . '.php?page={page}');
    define('SHOWCASE_URL_VIEW', $showcaseObject->prefix . '.php?action=view&gid={gid}');
    define('SHOWCASE_URL_COMMENT', $showcaseObject->prefix . '.php?action=view&gid={gid}&action=lastComment');
    define('SHOWCASE_URL_NEW', $showcaseObject->prefix . '.php?action=new');
    define('SHOWCASE_URL_VIEW_ATTACH', $showcaseObject->prefix . '.php?action=attachment&aid={aid}');
    define('SHOWCASE_URL_ITEM', $showcaseObject->prefix . '.php?action=item&aid={aid}');
    $amp = '&amp;';
}

urlHandlerSet(SHOWCASE_URL);

$showcaseObject->urlSet(urlHandlerGet());

$urlBase = $mybb->settings['bburl'];

$urlSort = SHOWCASE_URL . (my_strpos(SHOWCASE_URL, '?') ? $amp : '?');

// Check if the active user is a moderator and get the inline moderation tools.
$showcaseColumnsCount = 5;

if ($showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries] || $showcaseObject->userPermissions[ModeratorPermissions::CanDeleteEntries]) {
    ++$showcaseColumnsCount;
}

$currentUserID = (int)$mybb->user['uid'];

if ($showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries]) {
    $list_where_clause = '(entryData.approved=0 or entryData.approved=1)';
    if ($unapproved == 1) {
        $list_where_clause = 'entryData.approved=0';
    }
    $inlinecount = 0;
    $showcaseTableTheadInlineModeration = eval(getTemplate('pageMainTableTheadRowInlineModeration'));

    ++$showcaseColumnsCount;

    $customthreadtools = '';

    $showcaseInlineModeration = eval(getTemplate('inlinemod'));
} else {
    $list_where_clause = '(entryData.approved=1 OR entryData.uid=' . $currentUserID . ')';
}

//handle image output here for performance reasons since we dont need fields and stuff
if ($mybb->get_input('action') == 'item') {
    $outputObject->attachmentDownload($attachmentID);
}

//here for performance since we dont need the fields and other stuff
//this block is only used if user disables JS or if admin removes FancyBox code
if ($mybb->get_input('action') == 'attachment') {
    $attachment = attachmentGet(["aid='{$attachmentID}'"],
        array_keys(TABLES_DATA['myshowcase_attachments']),
        ['limit' => 1]);

    // Error if attachment is invalid or not visible
    if (!$attachment['aid'] || !$attachment['attachname'] || (!$showcaseObject->userPermissions[ModeratorPermissions::CanApproveEntries] && $attachment['visible'] != 1)) {
        error($lang->error_invalidattachment);
    }

    if (!$showcaseObject->allowAttachments || !$showcaseObject->userPermissions[UserPermissions::CanViewAttachments]) {
        error_no_permission();
    }

    $plugins->run_hooks('myshowcase_attachment_start');

    attachmentUpdate(['downloads' => $attachment['downloads'] + 1], $attachmentID);

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
        );//$showcaseObject->imgfolder."/".$attachment['attachname'];
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
        readfile($showcaseObject->imageFolder . '/' . $attachment['attachname']);
        die();
    }
}

//need a few items from the index language file
loadLanguage('index');

//load language file specific to this showcase's assigned fieldset
loadLanguage('myshowcase_fs' . $showcaseObject->fieldSetID, false, true); // 3.0.0 TODO

//see if current user can view this showcase
if (!$showcaseObject->userPermissions[UserPermissions::CanView]) {
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
        $showcaseObject->attachmentsRemove(["posthash='{$db->escape_string($entryHash)}'"]);
    }

    if ($mybb->get_input('action') == 'edit' || $mybb->get_input('action') == 'new') {
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
                ) == 'new' || $mybb->get_input(
                    'action'
                ) == 'edit') && $mybb->get_input(
                'submit'
            ) && isset($_FILES['attachment']))) && $mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $can_add_attachments = $showcaseObject->userPermissions[UserPermissions::CanAttachFiles];

    $attach_limit = $showcaseObject->userPermissions[UserPermissions::AttachmentsLimit];

    //if a mod is editing someone elses showcase, get orig authors perms
    if ($mybb->get_input('action') == 'edit' && $currentUserID !== $mybb->get_input(
            'entryUserID',
            MyBB::INPUT_INT
        )) {
        //get permissions for author
        $entryUserPermissions = $showcaseObject->userPermissionsGet($showcaseObject->entryUserID);

        $can_add_attachments = $entryUserPermissions[UserPermissions::CanAttachFiles];
        $attach_limit = $entryUserPermissions[UserPermissions::AttachmentsLimit];
    }

    // If there's an attachment, check it and upload it.
    if (($attach_limit === ALL_UNLIMITED_VALUE || ($current_attach_count < $attach_limit)) && $can_add_attachments) {
        if ($_FILES['attachment']['size'] > 0) {
            $update_attachment = false;
            if ($mybb->get_input('updateattachment')) {
                $update_attachment = true;
            }
            $attachedfile = attachmentUpload(
                $showcaseObject,
                $_FILES['attachment'],
                $entryHash,
                $update_attachment,
                (bool)$mybb->get_input('watermark', MyBB::INPUT_INT),
                $entryID
            );
        }
        if (!empty($attachedfile['error'])) {
            $attacherror = eval($templates->render('error_attacherror'));
            $mybb->input['action'] = 'new';
        }
    }

    if (!$mybb->get_input('submit')) {
        if ($entryID && $entryID != '') {
            $mybb->input['action'] = 'edit';
        } else {
            $mybb->input['action'] = 'new';
        }
    }
}

// Remove an attachment.
if ($mybb->get_input('attachmentaid', MyBB::INPUT_INT) && $entryHash &&
    ($showcaseObject->userPermissions[UserPermissions::CanEditEntries] || $showcaseObject->userPermissions[ModeratorPermissions::CanEditEntries]) && $mybb->request_method == 'post') {
    verify_post_check($mybb->get_input('my_post_key'));

    $showcaseObject->attachmentsRemove(
        ["posthash='{$db->escape_string($entryHash)}'", "aid='{$mybb->get_input('attachmentaid', MyBB::INPUT_INT)}'"]
    );

    if (!$mybb->get_input('submit')) {
        if ($entryID && $entryID != '') {
            $mybb->input['action'] = 'edit';
        } else {
            $mybb->input['action'] = 'new';
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

$showcase_top = eval(getTemplate('top'));

$pagination = '';

//main showcase code
switch ($mybb->get_input('action')) {
    case 'view':
        $pageContents = $outputObject->entryView();
        break;
    case 'report':
    case 'do_report':
    case 'reports':
    case 'do_reports':
    case 'allreports':
        $outputObject->report();
        break;
    case 'addcomment':
        $outputObject->commentPost();
        break;
    case 'delcomment':
        $outputObject->commentDelete();
        break;
    case 'delete';
        $outputObject->entryDelete();
        break;
    case 'edit':
    case 'new':
        $outputObject->entryPost();
        break;
    case 'multiapprove':
    case 'multiunapprove':
    case 'multidelete':
    case 'do_multidelete':
        $outputObject->inlineModeration();
        break;
    default:
    {
        $pageContents = $outputObject->main();
    }
}

$pageTitle = $showcaseObject->name;

$pageContents = eval(getTemplate('page'));

$plugins->run_hooks('myshowcase_end');

output_page($pageContents);

exit;