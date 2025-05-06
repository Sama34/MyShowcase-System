<?php

/***************************************************************************
 *
 *    ougc REST API plugin (/inc/plugins/ougc/RestApi/core.php)
 *    Author: Omar Gonzalez
 *    Copyright: © 2024 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    Implements a REST Api system to your forum.
 *
 ***************************************************************************
 ****************************************************************************
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

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
            $entriesObjects[(int)$entryData['gid']] = $entryData;
        }

        return $entriesObjects;
    }
}