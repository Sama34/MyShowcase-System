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

namespace MyShowcase\Controllers;

use JetBrains\PhpStorm\NoReturn;
use MyShowcase\System\Output;
use MyShowcase\System\Render;
use MyShowcase\System\Router;
use MyShowcase\System\Showcase;

use function MyShowcase\Core\loadLanguage;
use function MyShowcase\Core\outputGetObject;
use function MyShowcase\Core\renderGetObject;
use function MyShowcase\Core\showcaseGetObject;

use const MyShowcase\Core\VERSION_CODE;

abstract class Base
{
    public function __construct(
        public Router $router,
        public ?Showcase &$showcaseObject = null,
        public ?Render &$renderObject = null,
        public ?Output &$outputObject = null,
    ) {
//make sure this file is current
        if (SHOWCASE_FILE_VERSION_CODE < VERSION_CODE) {
            error(
                'This file is not the same version as the MyShowcase System. Please be sure to upload and configure ALL files.'
            );
        }

        global $theme, $templates;
        global $forumDirectoryPathTrailing;

//adjust theme settings in case this file is outside mybb_root

        $theme['imgdir'] = $forumDirectoryPathTrailing . substr($theme['imgdir'], 0);

        $theme['imglangdir'] = $forumDirectoryPathTrailing . substr($theme['imglangdir'], 0);

        global $mybb;

        //\MyShowcase\Core\cacheUpdate(\MyShowcase\Core\CACHE_TYPE_CONFIG);
//start by constructing the showcase
        $showcaseObject = showcaseGetObject($router->params['showcase_slug'] ?? '');

        $renderObject = renderGetObject($showcaseObject);

        $outputObject = outputGetObject($showcaseObject, $renderObject);

        if (!$showcaseObject->enabled) {
            match ($this->errorType) {
                ERROR_TYPE_NOT_INSTALLED => error(
                    'The MyShowcase System has not been installed and activated yet.'
                ),
                ERROR_TYPE_NOT_CONFIGURED => error(
                    'This file is not properly configured in the MyShowcase Admin section of the ACP'
                ),
                default => error_no_permission()
            };
        }

//try to load showcase specific language file
        loadLanguage();

        loadLanguage('myshowcase' . $showcaseObject->id, false, true);

        ini_set('display_errors', 1);

        error_reporting(E_ALL);

        return $this;
    }

    #[NoReturn] public function outputError(string $errorMessage): void
    {
    }

    #[NoReturn] public function outputSuccess(string $pageContents): void
    {
        global $mybb, $lang;
        global $headerinclude, $header, $footer;

        $pageTitle = $this->showcaseObject->name;

        $errorMessages = empty($this->showcaseObject->errorMessages) ? '' : inline_error(
            $this->showcaseObject->errorMessages
        );

        $pageContents = eval($this->renderObject->templateGet('page'));

        //$plugins->run_hooks('myshowcase_end');

        output_page($pageContents);

        exit;
    }

    #[NoReturn] public function outputErrorJson(string $errorMessage): void
    {
        global $lang;

        http_response_code(404);

        $data = [
            'error' => true,
            'message' => $errorMessage
        ];

        header("Content-type: application/json; charset={$lang->settings['charset']}");

        $data = json_encode($data);

        exit($data);
    }

    #[NoReturn] public function outputSuccessJson(array $data): void
    {
        global $lang;

        http_response_code(201);

        header("Content-type: application/json; charset={$lang->settings['charset']}");

        $data = json_encode($data);

        exit($data);
    }
}