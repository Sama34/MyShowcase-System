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

use MyShowcase\System\Router;

use const MyShowcase\ROOT;

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

$router = new Router();

$router->get('/', 'Entries', 'listEntries');

$router->get('/user/{user_id}', 'Entries', 'listEntriesUser');

$router->get('/unapproved', 'Entries', 'listEntriesUnapproved');

$router->get('/create', 'Entries', 'createEntry');

$router->post('/create', 'Entries', 'createEntry');

$router->get('/view/{entry_slug}', 'Entries', 'viewEntry');

$router->get('/view/{entry_slug}/page/{current_page}', 'Entries', 'viewEntryPage');

$router->get('/view/{entry_slug}/update', 'Entries', 'updateEntry');

$router->post('/view/{entry_slug}/update', 'Entries', 'updateEntry');

$router->post('/view/{entry_slug}/approve', 'Entries', 'approveEntry');

$router->post('/view/{entry_slug}/unapprove', 'Entries', 'unapproveEntry');

$router->post('/view/{entry_slug}/soft_delete', 'Entries', 'softDeleteEntry');

$router->post('/view/{entry_slug}/restore', 'Entries', 'restoreEntry');

$router->post('/view/{entry_slug}/delete', 'Entries', 'deleteEntry');

$router->post('/view/{entry_slug}/unapprove', 'Entries', 'unapproveEntry');

$router->post('/view/{entry_slug}/comment/create', 'Comments', 'createComment');

$router->get('/view/{entry_slug}/comment/{comment_id}', 'Comments', 'viewComment');

$router->get('/view/{entry_slug}/comment/{comment_id}/update', 'Comments', 'updateComment');

$router->post('/view/{entry_slug}/comment/{comment_id}/update', 'Comments', 'updateComment');

$router->post('/view/{entry_slug}/comment/{comment_id}/approve', 'Comments', 'approveComment');

$router->post('/view/{entry_slug}/comment/{comment_id}/unapprove', 'Comments', 'unapproveComment');

$router->post('/view/{entry_slug}/comment/{comment_id}/soft_delete', 'Comments', 'SoftDeleteComment');

$router->post('/view/{entry_slug}/comment/{comment_id}/restore', 'Comments', 'restoreComment');

$router->post('/view/{entry_slug}/comment/{comment_id}/delete', 'Comments', 'deleteComment');

$router->get('/search', 'Search', 'searchForm');

$router->run();