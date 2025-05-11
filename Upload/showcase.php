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

use Pecee\Http\Middleware\Exceptions\TokenMismatchException;
use Pecee\SimpleRouter\Exceptions\NotFoundHttpException;
use Pecee\SimpleRouter\SimpleRouter;

use function MyShowcase\Core\outputGetObject;
use function MyShowcase\Core\renderGetObject;
use function MyShowcase\Core\showcaseGetObjectByScriptName;

use const MyShowcase\ROOT;
use const MyShowcase\Core\URL_TYPE_ATTACHMENT_VIEW;
use const MyShowcase\Core\URL_TYPE_THUMBNAIL_VIEW;
use const MyShowcase\Core\FILTER_TYPE_USER_ID;
use const MyShowcase\Core\URL_TYPE_COMMENT_APPROVE;
use const MyShowcase\Core\URL_TYPE_COMMENT_CREATE;
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
use const MyShowcase\Core\URL_TYPE_MAIN;
use const MyShowcase\Core\URL_TYPE_MAIN_USER;

/*
 * Only user edits required
*/

$forumDirectoryPath = ''; //no trailing slash

/*
 * Stop editing
*/

const IN_MYBB = true;

const IN_SHOWCASE = true;

const SHOWCASE_FILE_VERSION_CODE = 3000;

define('THIS_SCRIPT', substr($_SERVER['SCRIPT_NAME'], -mb_strpos(strrev($_SERVER['SCRIPT_NAME']), '/')));

$currentWorkingDirectoryPath = getcwd();

$change_dir = './';

if (!chdir($forumDirectoryPath) && !empty($forumDirectoryPath)) {
    if (is_dir($forumDirectoryPath)) {
        $change_dir = $forumDirectoryPath;
    } else {
        exit("{$forumDirectoryPath} is invalid!");
    }
}

//change working directory to allow board includes to work
$forumDirectoryPathTrailing = ($forumDirectoryPath === '' ? '' : $forumDirectoryPath . '/');

$templatelist = '';

if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/attachment/') ||
    str_contains($_SERVER['REQUEST_URI'] ?? '', '/thumbnail/')) {
    $minimalLoad = true;

    require_once $change_dir . '/inc/init.php';
    require_once MYBB_ROOT . 'inc/class_session.php';

    $shutdown_queries = $shutdown_functions = [];

    global $mybb, $lang, $db, $templates;

    header('Expires: Sat, 1 Jan 2000 01:00:00 GMT');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');

    $session = new session();

    $session->init();

    if (!isset($mybb->settings['bblanguage'])) {
        $mybb->settings['bblanguage'] = 'english';
    }
    if (isset($mybb->user['language']) && $lang->language_exists($mybb->user['language'])) {
        $mybb->settings['bblanguage'] = $mybb->user['language'];
    }
    $lang->set_language($mybb->settings['bblanguage']);

    if (function_exists('mb_internal_encoding') && !empty($lang->settings['charset'])) {
        @mb_internal_encoding($lang->settings['charset']);
    }

    $templateList = '';

    if ($templateList) {
        $templates->cache($db->escape_string($templateList));
    }

    if ($lang->settings['charset']) {
        $charset = $lang->settings['charset'];
    } else {
        $charset = 'UTF-8';
    }

    $lang->load('global');

    $lang->load('xmlhttp');

    $closed_bypass = ['refresh_captcha', 'validate_captcha'];

    $mybb->input['action'] = $mybb->get_input('action');
} else {
    $minimalLoad = false;

    require_once $change_dir . '/global.php';
    require_once MYBB_ROOT . 'inc/functions_user.php';
}

//change directory back to current where script is
chdir($currentWorkingDirectoryPath);

require_once ROOT . '/System/Router.php';
require_once ROOT . '/Controllers/Base.php';
require_once ROOT . '/Models/Base.php';
require_once ROOT . '/vendor/autoload.php'; // router
require_once ROOT . '/vendor/pecee/simple-router/helpers.php';

$showcaseObject = showcaseGetObjectByScriptName(THIS_SCRIPT);

$renderObject = renderGetObject($showcaseObject);

$outputObject = outputGetObject($showcaseObject, $renderObject);

$requestBaseUriExtra = '';

switch ($showcaseObject->config['filter_force_field']) {
    case FILTER_TYPE_USER_ID:
        $requestBaseUriExtra = '/user/{user_id}';

        break;
    default:
        break;
}

if ($minimalLoad) {
    require_once ROOT . '/Controllers/Attachments.php';

    SimpleRouter::get(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}/attachment/{attachment_hash}',
        ['MyShowcase\Controllers\Attachments', 'viewAttachment']
    )->name(URL_TYPE_ATTACHMENT_VIEW);

    SimpleRouter::get(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}/thumbnail/{attachment_hash}',
        ['MyShowcase\Controllers\Attachments', 'viewThumbnail']
    )->name(URL_TYPE_THUMBNAIL_VIEW);
} else {
    require_once ROOT . '/Controllers/Entries.php';
    require_once ROOT . '/Controllers/Comments.php';

    SimpleRouter::get(
        $showcaseObject->selfPhpScript . $requestBaseUriExtra . '/',
        ['MyShowcase\Controllers\Entries', 'listEntries']
    )->name(URL_TYPE_MAIN);

    SimpleRouter::get(
        $showcaseObject->selfPhpScript . $requestBaseUriExtra . '/unapproved',
        ['MyShowcase\Controllers\Entries', 'listEntriesUnapproved']
    )->name(\MyShowcase\Core\URL_TYPE_MAIN_UNAPPROVED);

    SimpleRouter::get(
        $showcaseObject->selfPhpScript . '/user/{user_id}',
        ['MyShowcase\Controllers\Entries', 'listEntriesUser']
    )->name(URL_TYPE_MAIN_USER)->where(['id' => '[0-9]+']);

    SimpleRouter::form(
        $showcaseObject->selfPhpScript . $requestBaseUriExtra . '/search',
        ['MyShowcase\Controllers\Search', 'searchForm']
    )->name(\MyShowcase\Core\URL_TYPE_SEARCH);

    SimpleRouter::form(
        $showcaseObject->selfPhpScript . '/create',
        ['MyShowcase\Controllers\Entries', 'createEntry']
    )->name(URL_TYPE_ENTRY_CREATE);

    SimpleRouter::get(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}',
        ['MyShowcase\Controllers\Entries', 'viewEntry']
    )->name(URL_TYPE_ENTRY_VIEW);

    SimpleRouter::form(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}/update',
        ['MyShowcase\Controllers\Entries', 'updateEntry']
    )->name(URL_TYPE_ENTRY_UPDATE);

    SimpleRouter::post(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}/approve',
        ['MyShowcase\Controllers\Entries', 'approveEntry']
    )->name(URL_TYPE_ENTRY_UNAPPROVE);

    SimpleRouter::post(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}/unapprove',
        ['MyShowcase\Controllers\Entries', 'unapproveEntry']
    )->name(URL_TYPE_ENTRY_APPROVE);

    SimpleRouter::post(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}/soft_delete',
        ['MyShowcase\Controllers\Entries', 'softDeleteEntry']
    )->name(URL_TYPE_ENTRY_SOFT_DELETE);

    SimpleRouter::post(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}/restore',
        ['MyShowcase\Controllers\Entries', 'restoreEntry']
    )->name(URL_TYPE_ENTRY_RESTORE);

    SimpleRouter::post(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}/delete',
        ['MyShowcase\Controllers\Entries', 'deleteEntry']
    )->name(URL_TYPE_ENTRY_DELETE);

    SimpleRouter::get(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}/comment/{comment_id}',
        ['MyShowcase\Controllers\Comments', 'viewComment']
    )->name(URL_TYPE_COMMENT_VIEW);

    SimpleRouter::form(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}/comment/create',
        ['MyShowcase\Controllers\Comments', 'createComment']
    )->name(URL_TYPE_COMMENT_CREATE);

    SimpleRouter::form(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}/comment/{comment_id}/update',
        ['MyShowcase\Controllers\Comments', 'updateComment']
    )->name(URL_TYPE_COMMENT_UPDATE);

    SimpleRouter::post(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}/comment/{comment_id}/approve',
        ['MyShowcase\Controllers\Comments', 'approveComment']
    )->name(URL_TYPE_COMMENT_APPROVE);

    SimpleRouter::post(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}/comment/{comment_id}/unapprove',
        ['MyShowcase\Controllers\Comments', 'unapproveComment']
    )->name(URL_TYPE_COMMENT_UNAPPROVE);

    SimpleRouter::post(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}/comment/{comment_id}/soft_delete',
        ['MyShowcase\Controllers\Comments', 'SoftDeleteComment']
    )->name(URL_TYPE_COMMENT_SOFT_DELETE);

    SimpleRouter::post(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}/comment/{comment_id}/restore',
        ['MyShowcase\Controllers\Comments', 'restoreComment']
    )->name(URL_TYPE_COMMENT_RESTORE);

    SimpleRouter::post(
        $showcaseObject->selfPhpScript . '/view/{entry_slug}/comment/{comment_id}/delete',
        ['MyShowcase\Controllers\Comments', 'deleteComment']
    )->name(URL_TYPE_COMMENT_DELETE);
}

try {
    SimpleRouter::start();
} catch (TokenMismatchException|NotFoundHttpException|\Pecee\SimpleRouter\Exceptions\HttpException|Exception $e) {
    error($e->getMessage());
}

exit;