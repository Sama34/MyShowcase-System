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

use Pecee\Http\Middleware\Exceptions\TokenMismatchException;
use Pecee\SimpleRouter\Exceptions\NotFoundHttpException;
use Pecee\SimpleRouter\SimpleRouter;

use function MyShowcase\Core\outputGetObject;
use function MyShowcase\Core\renderGetObject;
use function MyShowcase\Core\showcaseGetObjectByScriptName;

use const MyShowcase\ROOT;
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

//get MyBB stuff
require_once $change_dir . '/global.php';
require_once MYBB_ROOT . 'inc/functions_user.php';

//change directory back to current where script is
chdir($currentWorkingDirectoryPath);

require_once ROOT . '/System/Router.php';
require_once ROOT . '/Controllers/Base.php';
require_once ROOT . '/Models/Base.php';
require_once ROOT . '/vendor/autoload.php'; // router
require_once ROOT . '/vendor/pecee/simple-router/helpers.php';

require_once ROOT . '/Controllers/Entries.php';
require_once ROOT . '/Controllers/Comments.php';

$showcaseObject = showcaseGetObjectByScriptName(THIS_SCRIPT);

$renderObject = renderGetObject($showcaseObject);

$outputObject = outputGetObject($showcaseObject, $renderObject);

if ($showcaseObject->friendlyUrlsEnabled) {
    $requestBaseUri = str_replace('.php', '/', $_SERVER['PHP_SELF']);
} else {
    $requestBaseUri = $_SERVER['PHP_SELF'];
}

$requestBaseUriExtra = '';

switch ($showcaseObject->config['filter_force_field']) {
    case FILTER_TYPE_USER_ID:
        $requestBaseUriExtra = '/user/{user_id}';

        break;
    default:
        break;
}

SimpleRouter::get(
    $requestBaseUri . $requestBaseUriExtra . '/',
    ['MyShowcase\Controllers\Entries', 'listEntries']
)->name(URL_TYPE_MAIN);

SimpleRouter::get(
    $requestBaseUri . '/user/{user_id}',
    ['MyShowcase\Controllers\Entries', 'listEntriesUser']
)->name(URL_TYPE_MAIN_USER)->where(['id' => '[0-9]+']);

SimpleRouter::get(
    $requestBaseUri . $requestBaseUriExtra . '/unapproved',
    ['MyShowcase\Controllers\Entries', 'listEntriesUnapproved']
)->name('main_unapproved');

SimpleRouter::form(
    $requestBaseUri . '/create',
    ['MyShowcase\Controllers\Entries', 'createEntry']
)->name(URL_TYPE_ENTRY_CREATE);

SimpleRouter::get(
    $requestBaseUri . '/view/{entry_slug}',
    ['MyShowcase\Controllers\Entries', 'viewEntry']
)->name(URL_TYPE_ENTRY_VIEW);

SimpleRouter::form(
    $requestBaseUri . '/view/{entry_slug}/update',
    ['MyShowcase\Controllers\Entries', 'updateEntry']
)->name(URL_TYPE_ENTRY_UPDATE);

SimpleRouter::post(
    $requestBaseUri . '/view/{entry_slug}/approve',
    ['MyShowcase\Controllers\Entries', 'approveEntry']
)->name(URL_TYPE_ENTRY_UNAPPROVE);

SimpleRouter::post(
    $requestBaseUri . '/view/{entry_slug}/unapprove',
    ['MyShowcase\Controllers\Entries', 'unapproveEntry']
)->name(URL_TYPE_ENTRY_APPROVE);

SimpleRouter::post(
    $requestBaseUri . '/view/{entry_slug}/soft_delete',
    ['MyShowcase\Controllers\Entries', 'softDeleteEntry']
)->name(URL_TYPE_ENTRY_SOFT_DELETE);

SimpleRouter::post(
    $requestBaseUri . '/view/{entry_slug}/restore',
    ['MyShowcase\Controllers\Entries', 'restoreEntry']
)->name(URL_TYPE_ENTRY_RESTORE);

SimpleRouter::post(
    $requestBaseUri . '/view/{entry_slug}/delete',
    ['MyShowcase\Controllers\Entries', 'deleteEntry']
)->name(URL_TYPE_ENTRY_DELETE);

SimpleRouter::get(
    $requestBaseUri . '/view/{entry_slug}/comment/{comment_id}',
    ['MyShowcase\Controllers\Comments', 'viewComment']
)->name(URL_TYPE_COMMENT_VIEW);

SimpleRouter::form(
    $requestBaseUri . '/view/{entry_slug}/comment/create',
    ['MyShowcase\Controllers\Comments', 'createComment']
)->name(URL_TYPE_COMMENT_CREATE);

SimpleRouter::form(
    $requestBaseUri . '/view/{entry_slug}/comment/{comment_id}/update',
    ['MyShowcase\Controllers\Comments', 'updateComment']
)->name(URL_TYPE_COMMENT_UPDATE);

SimpleRouter::post(
    $requestBaseUri . '/view/{entry_slug}/comment/{comment_id}/approve',
    ['MyShowcase\Controllers\Comments', 'approveComment']
)->name(URL_TYPE_COMMENT_APPROVE);

SimpleRouter::post(
    $requestBaseUri . '/view/{entry_slug}/comment/{comment_id}/unapprove',
    ['MyShowcase\Controllers\Comments', 'unapproveComment']
)->name(URL_TYPE_COMMENT_UNAPPROVE);

SimpleRouter::post(
    $requestBaseUri . '/view/{entry_slug}/comment/{comment_id}/soft_delete',
    ['MyShowcase\Controllers\Comments', 'SoftDeleteComment']
)->name(URL_TYPE_COMMENT_SOFT_DELETE);

SimpleRouter::post(
    $requestBaseUri . '/view/{entry_slug}/comment/{comment_id}/restore',
    ['MyShowcase\Controllers\Comments', 'restoreComment']
)->name(URL_TYPE_COMMENT_RESTORE);

SimpleRouter::post(
    $requestBaseUri . '/view/{entry_slug}/comment/{comment_id}/delete',
    ['MyShowcase\Controllers\Comments', 'deleteComment']
)->name(URL_TYPE_COMMENT_DELETE);

SimpleRouter::form(
    $requestBaseUri . $requestBaseUriExtra . '/search',
    ['MyShowcase\Controllers\Search', 'searchForm']
)->name(URL_TYPE_MAIN_USER);

try {
    SimpleRouter::start();
} catch (TokenMismatchException|NotFoundHttpException|\Pecee\SimpleRouter\Exceptions\HttpException|Exception $e) {
    error($e->getMessage());
}

exit;