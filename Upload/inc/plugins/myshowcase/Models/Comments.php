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

namespace myshowcase\Models;

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