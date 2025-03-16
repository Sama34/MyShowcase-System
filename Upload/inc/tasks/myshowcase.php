<?php

/**
 * MyShowcase Plugin for MyBB - Task File
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\tasks\myshowcase.php
 *
 */

declare(strict_types=1);

use inc\plugins\myshowcase\Showcase;

use function MyShowcase\Core\cacheGet;
use function MyShowcase\Core\showcaseDataGet;

use const MyShowcase\Core\CACHE_TYPE_CONFIG;
use const MyShowcase\Core\SHOWCASE_STATUS_ENABLED;
use const MyShowcase\ROOT;

function task_myshowcase(array $taskData): array
{
    global $db;

    require_once ROOT . '/class_showcase.php';

    foreach (cacheGet(CACHE_TYPE_CONFIG) as $showcaseID => $showcaseData) {
        $showcasePruneTime = explode('|', $showcaseData['prunetime']);

        if ($showcasePruneTime[0] > 0 && $showcaseData['enabled'] === SHOWCASE_STATUS_ENABLED) {
            $pruneTime = (int)strtotime('-' . $showcasePruneTime[0] . ' ' . $showcasePruneTime[1], TIME_NOW);

            $showcase = new Showcase($showcaseData['mainfile']);

            $showcaseObjects = showcaseDataGet($showcaseID, ["dateline<='{$pruneTime}'"]);

            foreach ($showcaseObjects as $entryID => $entryData) {
                $showcase->delete((int)$entryID);
            }

            unset($showcase);
        }
    }

    add_task_log($taskData, 'Showcase Pruning Run');

    return $taskData;
}