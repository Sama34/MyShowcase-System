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

use ErrorException;
use JetBrains\PhpStorm\NoReturn;
use mysqli_sql_exception;

class Entries extends Base
{
    #[NoReturn] public function getEntries(
        array $queryTables,
        array $queryFields = [],
        array $whereClauses = [],
        array $queryOptions = [],
    ): array {
        global $db;

        try {
        } catch (ErrorException|mysqli_sql_exception $errorException) {
            //error($errorException->getMessage());
            //_dump($errorException->getMessage());
            //$this->outputError($errorException->getMessage());
        }
        $query = $db->simple_select(
            implode(" LEFT JOIN {$db->table_prefix}", $queryTables),
            implode(',', $queryFields),
            implode(' AND ', $whereClauses),
            $queryOptions,
        );

        if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
            return (array)$db->fetch_array($query);
        }

        $entriesObjects = [];

        while ($entryData = $db->fetch_array($query)) {
            $entriesObjects[(int)$entryData['entry_id']] = $entryData;
        }

        return $entriesObjects;
    }
}