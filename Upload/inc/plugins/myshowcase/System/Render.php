<?php
/**
 * MyShowcase Plugin for MyBB - MyShowcase Class
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\class_showcase.php
 *
 */

declare(strict_types=1);

namespace inc\plugins\myshowcase\System;

use inc\plugins\myshowcase\Showcase;

use function MyShowcase\Core\getTemplate;
use function MyShowcase\Core\urlHandlerBuild;

class Render
{
    protected Showcase $showcase;

    public function __construct(Showcase $showcase)
    {
        $this->showcase = $showcase;
    }
}