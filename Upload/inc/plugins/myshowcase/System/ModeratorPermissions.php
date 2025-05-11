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

namespace MyShowcase\System;

class ModeratorPermissions
{
    public const CanManageEntries = 'canmodapprove';
    public const CanManageComments = 'canmoddelcomment';
    public const CanManageAttachments = 'canmodapprove';
    public const CanManageReports = 'can_manage_reports';
    public const CaManageLogs = 'can_manage_logs';
    public const CanEditEntries = 'canmodedit';
    public const CanDeleteEntries = 'canmoddelete';
    public const CanDeleteComments = 'canmoddelcomment';
    public const CanEditComments = 'canmodedit';
}