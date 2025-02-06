<?php
/**
 * MyShowcase Plugin for MyBB - Main Plugin
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\plugins\myshowcase.php
 *
 */

declare(strict_types=1);

use function MyShowcase\Admin\pluginActivation;
use function MyShowcase\Admin\pluginDeactivation;
use function MyShowcase\Admin\pluginInformation;
use function MyShowcase\Admin\pluginInstallation;
use function MyShowcase\Admin\pluginIsInstalled;
use function MyShowcase\Admin\pluginUninstallation;
use function MyShowcase\Core\addHooks;

use const MyShowcase\ROOT;

defined('IN_MYBB') || die('This file cannot be accessed directly.');

// You can uncomment the lines below to avoid storing some settings in the DB
define('MyShowcase\Core\SETTINGS', [
    //'key' => '',
    'moderatorGroups' => '3,4',
]);

define('MyShowcase\Core\DEBUG', false);

define('MyShowcase\ROOT', constant('MYBB_ROOT') . 'inc/plugins/myshowcase');

require_once ROOT . '/core.php';

defined('PLUGINLIBRARY') || define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');

if (defined('IN_ADMINCP')) {
    require_once ROOT . '/admin.php';

    require_once ROOT . '/admin_hooks.php';

    addHooks('MyShowcase\AdminHooks');
} else {
    require_once ROOT . '/forum_hooks.php';

    addHooks('MyShowcase\ForumHooks');
}

function myshowcase_info(): array
{
    return pluginInformation();
}

function myshowcase_activate(): bool
{
    return pluginActivation();
}

function myshowcase_deactivate(): bool
{
    return pluginDeactivation();
}

function myshowcase_install(): bool
{
    return pluginInstallation();
}

function myshowcase_is_installed(): bool
{
    return pluginIsInstalled();
}

function myshowcase_uninstall(): bool
{
    return pluginUninstallation();
}