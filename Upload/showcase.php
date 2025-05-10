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
use MyShowcase\System\Router;
use MyShowcase\System\UserPermissions;

use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\attachmentUpload;
use function MyShowcase\Core\loadLanguage;
use function MyShowcase\Core\urlHandlerGet;
use function MyShowcase\Core\urlHandlerSet;

use const MyShowcase\ROOT;
use const MyShowcase\Core\ALL_UNLIMITED_VALUE;

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

//setup templates
$templatelist = '';

//get MyBB stuff
require_once $change_dir . '/global.php';
require_once MYBB_ROOT . 'inc/functions_user.php';

//change directory back to current where script is
chdir($currentWorkingDirectoryPath);

require_once ROOT . '/System/Router.php';
require_once ROOT . '/Controllers/Base.php';
require_once ROOT . '/Models/Base.php';

$router = new Router();

$router->get('/{showcase_slug}', 'Entries', 'listEntries');

$router->get('/{showcase_slug}/user/{user_id}', 'Entries', 'listEntriesUser');

$router->get('/{showcase_slug}/unapproved', 'Entries', 'listEntriesUnapproved');

$router->get('/{showcase_slug}/create', 'Entries', 'createEntry');

$router->post('/{showcase_slug}/create', 'Entries', 'createEntry');

$router->get('/{showcase_slug}/view/{entry_slug}', 'Entries', 'viewEntry');

$router->get('/{showcase_slug}/view/{entry_slug}/page/{current_page}', 'Entries', 'viewEntryPage');

$router->get('/{showcase_slug}/view/{entry_slug}/update', 'Entries', 'updateEntry');

$router->post('/{showcase_slug}/view/{entry_slug}/update', 'Entries', 'updateEntry');

$router->post('/{showcase_slug}/view/{entry_slug}/approve', 'Entries', 'approveEntry');

$router->post('/{showcase_slug}/view/{entry_slug}/unapprove', 'Entries', 'unapproveEntry');

$router->post('/{showcase_slug}/view/{entry_slug}/soft_delete', 'Entries', 'softDeleteEntry');

$router->post('/{showcase_slug}/view/{entry_slug}/restore', 'Entries', 'restoreEntry');

$router->post('/{showcase_slug}/view/{entry_slug}/delete', 'Entries', 'deleteEntry');

$router->post('/{showcase_slug}/view/{entry_slug}/unapprove', 'Entries', 'unapproveEntry');

$router->post('/{showcase_slug}/view/{entry_slug}/comment/create', 'Comments', 'createComment');

$router->get('/{showcase_slug}/view/{entry_slug}/comment/{comment_id}', 'Comments', 'viewComment');

$router->get('/{showcase_slug}/view/{entry_slug}/comment/{comment_id}/update', 'Comments', 'updateComment');

$router->post('/{showcase_slug}/view/{entry_slug}/comment/{comment_id}/update', 'Comments', 'updateComment');

$router->post('/{showcase_slug}/view/{entry_slug}/comment/{comment_id}/approve', 'Comments', 'approveComment');

$router->post('/{showcase_slug}/view/{entry_slug}/comment/{comment_id}/unapprove', 'Comments', 'unapproveComment');

$router->post('/{showcase_slug}/view/{entry_slug}/comment/{comment_id}/soft_delete', 'Comments', 'SoftDeleteComment');

$router->post('/{showcase_slug}/view/{entry_slug}/comment/{comment_id}/restore', 'Comments', 'restoreComment');

$router->post('/{showcase_slug}/view/{entry_slug}/comment/{comment_id}/delete', 'Comments', 'deleteComment');

$router->get('/{showcase_slug}/search', 'Search', 'searchForm');

$router->run();

