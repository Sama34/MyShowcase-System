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

namespace MyShowcase\Models;

use JetBrains\PhpStorm\NoReturn;

use function MyShowcase\Core\commentsGet;

class Comments extends Base
{
    #[NoReturn] public function getComment(
        int $commentID,
        array $queryFields = [],
        array $queryOptions = []
    ): array {
        $queryOptions['limit'] = 1;

        return commentsGet(["comment_id='{$commentID}'"], $queryFields, $queryOptions);
    }
}