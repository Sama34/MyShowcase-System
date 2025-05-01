<?php
/**
 * MyShowcase Plugin for MyBB - Main Plugin Controls
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \MyShowcase\plugin.php
 *
 */

declare(strict_types=1);

namespace MyShowcase\Hooks\Shared;

use UserDataHandler;

function datahandler_user_delete_content(UserDataHandler &$dataHandler): UserDataHandler
{
    return $dataHandler;
}