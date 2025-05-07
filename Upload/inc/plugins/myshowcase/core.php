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

namespace MyShowcase\Core;

use MyShowcase\System\FieldDefaultTypes;
use MyShowcase\System\FieldHtmlTypes;
use MyShowcase\System\FieldTypes;
use myshowcase\System\FormatTypes;
use Postparser;
use DirectoryIterator;
use MyShowcase\System\DataHandler;
use MyShowcase\System\Output;
use MyShowcase\System\Render;
use MyShowcase\System\ModeratorPermissions;
use MyShowcase\System\UserPermissions;
use MyShowcase\System\Showcase;

use function MyShowcase\Admin\languageModify;

use const MyShowcase\ROOT;

const VERSION = '3.0.0';

const VERSION_CODE = 3000;

const SHOWCASE_STATUS_DISABLED = 0;

const SHOWCASE_STATUS_ENABLED = 1;

const UPLOAD_STATUS_INVALID = 1;

const UPLOAD_STATUS_FAILED = 2;

const CACHE_TYPE_CONFIG = 'config';

const CACHE_TYPE_FIELDS = 'fields';

const CACHE_TYPE_FIELD_SETS = 'fieldsets';

const CACHE_TYPE_MODERATORS = 'moderators';

const CACHE_TYPE_PERMISSIONS = 'permissions';

const CACHE_TYPE_FIELD_DATA = 'field_data'; // todo, add cache update method

const MODERATOR_TYPE_USER = 0;

const MODERATOR_TYPE_GROUP = 1;

const ATTACHMENT_UNLIMITED = -1;

const ATTACHMENT_ZERO = 0;

const URL = 'index.php?module=myshowcase-summary';

const ALL_UNLIMITED_VALUE = -1;

const REPORT_STATUS_PENDING = 0;

const ERROR_TYPE_NOT_INSTALLED = 1;

const ERROR_TYPE_NOT_CONFIGURED = 1;

const CHECK_BOX_IS_CHECKED = 1;

const ORDER_DIRECTION_ASCENDING = 'asc';

const ORDER_DIRECTION_DESCENDING = 'desc';

const COMMENT_STATUS_PENDING_APPROVAL = 0;

const COMMENT_STATUS_VISIBLE = 1;

const COMMENT_STATUS_SOFT_DELETED = 2;

const ENTRY_STATUS_PENDING_APPROVAL = 0;

const ENTRY_STATUS_VISIBLE = 1;

const ENTRY_STATUS_SOFT_DELETED = 2;

const DATA_HANDLER_METHOD_INSERT = 'insert';

const DATA_HANDLER_METHOD_UPDATE = 'update';

const GUEST_GROUP_ID = 1;

const TABLES_DATA = [
    'myshowcase_attachments' => [
        'attachment_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'showcase_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 1
        ],
        'entry_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
        ],
        'entry_hash' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => '',
        ],
        'user_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
        ],
        'file_name' => [
            'type' => 'VARCHAR',
            'size' => 120,
            'default' => ''
        ],
        'file_type' => [
            'type' => 'VARCHAR',
            'size' => 120,
            'default' => ''
        ],
        'file_size' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'attachment_name' => [
            'type' => 'VARCHAR',
            'size' => 120,
            'default' => ''
        ],
        'downloads' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'dateline' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'thumbnail' => [
            'type' => 'VARCHAR',
            'size' => 120,
            'default' => ''
        ],
        'status' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
    ],
    'myshowcase_comments' => [
        'comment_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'showcase_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 1
        ],
        'entry_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'user_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        // todo, update old data from varchar to varbinary
        'ipaddress' => [
            'type' => 'VARBINARY',
            'size' => 16,
            'default' => ''
        ],
        'comment' => [
            'type' => 'TEXT',
            'null' => true
        ],
        'dateline' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'status' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'moderator_user_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
    ],
    'myshowcase_config' => [
        'showcase_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'name' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => '',
        ],
        'showcase_slug' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => '',
            'unique_value' => true
        ],
        'description' => [
            'type' => 'VARCHAR',
            'size' => 255,
            'default' => ''
        ],
        'mainfile' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => ''
        ],
        'field_set_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 1
        ],
        'images_directory' => [
            'type' => 'VARCHAR',
            'size' => 255,
            'default' => ''
        ],
        'default_image' => [
            'type' => 'VARCHAR',
            'null' => true,
            'size' => 50,
        ],
        'water_mark_image' => [
            'type' => 'VARCHAR',
            'null' => true,
            'size' => 50,
        ],
        'water_mark_image_location' => [
            'type' => 'VARCHAR',
            'size' => 12,
            'default' => 'lower-right'
        ],
        'use_attach' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'relative_path' => [
            'type' => 'VARCHAR',
            'size' => 255,
            'default' => ''
        ],
        'enabled' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'allow_smilies' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'allow_mycode' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'allow_html' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'prune_time' => [
            'type' => 'VARCHAR',
            'size' => 10,
            'default' => 0
        ],
        'moderate_edits' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'maximum_text_field_length' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 500
        ],
        'allow_attachments' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'allow_comments' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'thumb_width' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 200
        ],
        'thumb_height' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 200
        ],
        'comment_length' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 200
        ],
        'comments_per_page' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 5
        ],
        'attachments_per_row' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'display_empty_fields' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'display_in_posts' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'build_random_entry_widget' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'display_signatures' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        // todo, trigger notification (user, group), pm, or alert
        // todo, DVZ Stream
        // todo, latest entries helper
    ],
    'myshowcase_fieldsets' => [
        'set_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'set_name' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => ''
        ],
    ],
    'myshowcase_permissions' => [
        'permission_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'showcase_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'group_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        UserPermissions::CanAddEntries => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        UserPermissions::CanEditEntries => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        UserPermissions::CanAttachFiles => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        UserPermissions::CanView => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        UserPermissions::CanViewComments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        UserPermissions::CanViewAttachments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        UserPermissions::CanCreateComments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        UserPermissions::CanDeleteComments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        UserPermissions::CanDeleteAuthorComments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        UserPermissions::CanSearch => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        UserPermissions::CanWaterMarkAttachments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        UserPermissions::AttachmentsLimit => [
            'type' => 'INT',
            'default' => 0
        ],
    ],
    'myshowcase_moderators' => [
        'moderator_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'showcase_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'user_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'is_group' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        ModeratorPermissions::CanApproveEntries => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        ModeratorPermissions::CanEditEntries => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        ModeratorPermissions::CanDeleteEntries => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        ModeratorPermissions::CanDeleteComments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        //'unique_keys' => ['id_uid_isgroup' => ['showcase_id', 'showcasuser_ide_id', 'is_group']]
    ],
    'myshowcase_fields' => [
        'field_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'set_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'field_key' => [
            'type' => 'VARCHAR',
            'size' => 30,
            'default' => ''
        ],
        'html_type' => [
            'type' => 'VARCHAR',
            'size' => 10,
            'default' => ''
        ],
        'enabled' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'field_type' => [
            'type' => 'VARCHAR',
            'size' => 10,
            'default' => FieldTypes::VarChar
        ],
        'display_in_view_page' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'display_in_main_page' => [
        ],
        'minimum_length' => [
            'type' => 'MEDIUMINT',
            'unsigned' => true,
            'default' => 0
        ],
        'maximum_length' => [
            'type' => 'MEDIUMINT',
            'unsigned' => true,
            'default' => 0
        ],
        'is_required' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'default_value' => [
            'type' => 'VARCHAR',
            'size' => 10,
            'default' => ''
        ],
        'default_type' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'parse' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'display_order' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'render_order' => [
            'type' => 'SMALLINT',
            'default' => ALL_UNLIMITED_VALUE
        ],
        'enable_search' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'enable_slug' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'enable_subject' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        // todo, remove this legacy updating the database and updating the format field to TINYINT
        'format' => [
            'type' => 'VARCHAR',
            'size' => 10,
            'default' => 0
        ],
        'enable_editor' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'unique_keys' => ['set_field_name' => ['set_id', 'field_key']]
        //'unique_keys' => ['setid_fid' => ['set_id', 'field_id']]
        // todo, add view permission
        // todo, add edit permission
        // todo, validation regex for text fields
    ],
    'myshowcase_field_data' => [
        'field_data_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'set_id' => [
            'type' => 'INT',
            'unsigned' => true,
        ],
        'field_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'name' => [
            'type' => 'VARCHAR',
            'size' => 15,
            'default' => '',
            //'unique_keys' => true
        ],
        'value_id' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 0
        ],
        'value' => [
            'type' => 'VARCHAR',
            'size' => 120,
            'default' => ''
        ],
        'display_style' => [
            'type' => 'VARCHAR',
            'default' => ''
        ],
        'display_order' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 0
        ],
        'unique_key' => ['setid_fid_valueid' => 'set_id,field_id,value_id']
            'unsigned' => true
        ],
        'log_time' => [
            'type' => 'INT',
            'unsigned' => true
        ],
        'log_data' => [ // comments old message, etc
            'type' => 'TEXT',
            'null' => true
        ],
    ],
];

const FIELDS_DATA = [
];

// todo, add field setting to order entries by (i.e: sticky)
// todo, add field setting to block entries by (i.e: closed)
// todo, add field setting to record changes by (i.e: history)
// todo, add field setting to search fields data (i.e: enable_search)
// todo, integrate Feedback plugin into entries, per showcase
// todo, integrate Custom Rates plugin into entries, per showcase
const DATA_TABLE_STRUCTURE = [
    'myshowcase_data' => [
        'entry_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'entry_slug' => [
            'type' => 'VARCHAR',
            'size' => 250,
        'user_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'views' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
        ],
        'comments' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
        ],
        'dateline' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'status' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'moderator_user_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'edit_stamp' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'approved' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'approved_by' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'entry_hash' => [
            'type' => 'VARCHAR',
            'size' => 32,
            'default' => ''
        ],
        'ipaddress' => [
            'type' => 'VARBINARY',
            'size' => 16,
            'default' => ''
        ],
        //'unique_keys' => ['entry_slug' => 'entry_slug']
    ],
];

function loadLanguage(
    string $languageFileName = 'myshowcase',
    bool $forceUserArea = false,
    bool $suppressError = false
): bool {
    global $lang;

    $lang->load(
        $languageFileName,
        $forceUserArea,
        $suppressError
    );

    return true;
}

function addHooks(string $namespace): bool
{
    global $plugins;

    $namespaceLowercase = strtolower($namespace);
    $definedUserFunctions = get_defined_functions()['user'];

    foreach ($definedUserFunctions as $callable) {
        $namespaceWithPrefixLength = strlen($namespaceLowercase) + 1;

        if (substr($callable, 0, $namespaceWithPrefixLength) == $namespaceLowercase . '\\') {
            $hookName = substr_replace($callable, '', 0, $namespaceWithPrefixLength);

            $priority = substr($callable, -2);

            if (is_numeric(substr($hookName, -2))) {
                $hookName = substr($hookName, 0, -2);
            } else {
                $priority = 10;
            }

            $plugins->add_hook($hookName, $callable, $priority);
        }
    }

    return true;
}

function hooksRun(string $hookName, array &$hookArguments = []): array
{
    global $plugins;

    return $plugins->run_hooks('myshowcase_system_' . $hookName, $hookArguments);
}

function urlHandler(string $newUrl = ''): string
{
    static $setUrl = URL;

    if (($newUrl = trim($newUrl))) {
        $setUrl = $newUrl;
    }

    return $setUrl;
}

function urlHandlerSet(string $newUrl): bool
{
    urlHandler($newUrl);

    return true;
}

function urlHandlerGet(): string
{
    return urlHandler();
}

function urlHandlerBuild(array $urlAppend = [], string $separator = '&amp;', bool $encode = true): string
{
    global $PL;

    if (!is_object($PL)) {
        $PL or require_once PLUGINLIBRARY;
    }

    if ($urlAppend && !is_array($urlAppend)) {
        $urlAppend = explode('=', $urlAppend);
        $urlAppend = [$urlAppend[0] => $urlAppend[1]];
    }

    return $PL->url_append(urlHandlerGet(), $urlAppend, $separator, $encode);
}

function getSetting(string $settingKey = '')
{
    global $mybb;

    return SETTINGS[$settingKey] ?? (
        $mybb->settings['myshowcase_' . $settingKey] ?? false
    );
}

function getTemplateName(string $templateName = '', int $showcaseID = 0): string
{
    $templatePrefix = '';

    if ($templateName) {
        $templatePrefix = '_';
    }

    if ($showcaseID) {
        return "myShowcase{$showcaseID}{$templatePrefix}{$templateName}";
    }

    return "myShowcase{$templatePrefix}{$templateName}";
}

function getTemplate(string $templateName = '', bool $enableHTMLComments = true, int $showcaseID = 0): string
{
    global $templates;

    if (DEBUG && file_exists($filePath = ROOT . "/templates/{$templateName}.html")) {
        $templateContents = file_get_contents($filePath);

        $templates->cache[getTemplateName($templateName)] = $templateContents;
    } elseif (my_strpos($templateName, '/') !== false) {
        $templateName = substr($templateName, my_strpos($templateName, '/') + 1);
    }

    if ($showcaseID && isset($templates->cache[getTemplateName($templateName, $showcaseID)])) {
        return $templates->render(getTemplateName($templateName, $showcaseID), true, $enableHTMLComments);
    } elseif ($showcaseID) {
        return getTemplate($templateName, $enableHTMLComments);
    }

    return $templates->render(getTemplateName($templateName, $showcaseID), true, $enableHTMLComments);
}

//set default permissions for all groups in all myshowcases
//if you edit or reorder these, you need to also edit
//the summary.php file (starting line 156) so the fields match this order
function showcaseDefaultPermissions(): array
{
    return [
        UserPermissions::CanAddEntries => false,
        UserPermissions::CanEditEntries => false,
        UserPermissions::CanAttachFiles => false,
        UserPermissions::CanView => true,
        UserPermissions::CanViewComments => true,
        UserPermissions::CanViewAttachments => true,
        UserPermissions::CanCreateComments => false,
        UserPermissions::CanDeleteComments => false,
        UserPermissions::CanDeleteAuthorComments => false,
        UserPermissions::CanSearch => true,
        UserPermissions::CanWaterMarkAttachments => false,
        UserPermissions::AttachmentsLimit => 0
    ];
}

function showcaseDefaultModeratorPermissions(): array
{
    return [
        ModeratorPermissions::CanApproveEntries => false,
        ModeratorPermissions::CanEditEntries => false,
        ModeratorPermissions::CanDeleteEntries => false,
        ModeratorPermissions::CanDeleteComments => false
    ];
}

function getTemplatesList(): array
{
    $templatesDirIterator = new DirectoryIterator(ROOT . '/templates');

    $templatesList = [];

    foreach ($templatesDirIterator as $template) {
        if (!$template->isFile()) {
            continue;
        }

        $pathName = $template->getPathname();

        $pathInfo = pathinfo($pathName);

        if ($pathInfo['extension'] === 'html') {
            $templatesList[$pathInfo['filename']] = file_get_contents($pathName);
        }
    }

    return $templatesList;
}

/**
 * Update the cache.
 *
 * @param string The cache item.
 * @param bool Clear the cache item.
 */
function cacheUpdate(string $cacheKey): array
{
    global $db, $cache;

    $cacheData = [];

    switch ($cacheKey) {
        case CACHE_TYPE_CONFIG:
            $showcaseObjects = showcaseGet(
                queryFields: array_keys(TABLES_DATA['myshowcase_config'])
            );

            foreach ($showcaseObjects as $showcaseID => $showcaseData) {
                $cacheData[$showcaseID] = [
                    'showcase_id' => $showcaseID,
                    'name' => (string)$showcaseData['name'],
                    'showcase_slug' => (string)$showcaseData['showcase_slug'],
                    'description' => (string)$showcaseData['description'],
                    'mainfile' => (string)$showcaseData['mainfile'],
                    'field_set_id' => (int)$showcaseData['field_set_id'],
                    'images_directory' => (string)$showcaseData['images_directory'],
                    'default_image' => (string)$showcaseData['default_image'],
                    'water_mark_image' => (string)$showcaseData['water_mark_image'],
                    'water_mark_image_location' => (string)$showcaseData['water_mark_image_location'],
                    'use_attach' => (bool)$showcaseData['use_attach'],
                    'relative_path' => (string)$showcaseData['relative_path'],
                    'enabled' => (bool)$showcaseData['enabled'],
                    'allow_smilies' => (bool)$showcaseData['allow_smilies'],
                    'allow_mycode' => (bool)$showcaseData['allow_mycode'],
                    'allow_html' => (bool)$showcaseData['allow_html'],
                    'prune_time' => (int)$showcaseData['prune_time'],
                    'moderate_edits' => (bool)$showcaseData['moderate_edits'],
                    'maximum_text_field_length' => (int)$showcaseData['maximum_text_field_length'],
                    'allow_attachments' => (bool)$showcaseData['allow_attachments'],
                    'allow_comments' => (bool)$showcaseData['allow_comments'],
                    'thumb_width' => (int)$showcaseData['thumb_width'],
                    'thumb_height' => (int)$showcaseData['thumb_height'],
                    'comment_length' => (int)$showcaseData['comment_length'],
                    'comments_per_page' => (int)$showcaseData['comments_per_page'],
                    'attachments_per_row' => (int)$showcaseData['attachments_per_row'],
                    'display_empty_fields' => (bool)$showcaseData['display_empty_fields'],
                    'display_in_posts' => (bool)$showcaseData['display_in_posts'],
                    'build_random_entry_widget' => (bool)$showcaseData['build_random_entry_widget'],
                    'display_signatures' => (bool)$showcaseData['display_signatures'],
                ];
            }

            break;
        case CACHE_TYPE_PERMISSIONS:
            $permissionsObjects = permissionsGet(
                [],
                array_keys(TABLES_DATA['myshowcase_permissions'])
            );

            foreach ($permissionsObjects as $permissionID => $permissionData) {
                $cacheData[(int)$permissionData['showcase_id']][(int)$permissionData['group_id']] = [
                    'permission_id' => (int)$permissionData['permission_id'],
                    'showcase_id' => (int)$permissionData['showcase_id'],
                    'group_id' => (int)$permissionData['group_id'],
                    UserPermissions::CanAddEntries => !empty($permissionData[UserPermissions::CanAddEntries]),
                    UserPermissions::CanEditEntries => !empty($permissionData[UserPermissions::CanEditEntries]),
                    UserPermissions::CanAttachFiles => !empty($permissionData[UserPermissions::CanAttachFiles]),
                    UserPermissions::CanView => !empty($permissionData[UserPermissions::CanView]),
                    UserPermissions::CanViewComments => !empty($permissionData[UserPermissions::CanViewComments]),
                    UserPermissions::CanViewAttachments => !empty($permissionData[UserPermissions::CanViewAttachments]),
                    UserPermissions::CanCreateComments => !empty($permissionData[UserPermissions::CanCreateComments]),
                    UserPermissions::CanDeleteComments => !empty($permissionData[UserPermissions::CanDeleteComments]),
                    UserPermissions::CanDeleteAuthorComments => !empty($permissionData[UserPermissions::CanDeleteAuthorComments]),
                    UserPermissions::CanSearch => !empty($permissionData[UserPermissions::CanSearch]),
                    UserPermissions::CanWaterMarkAttachments => !empty($permissionData[UserPermissions::CanWaterMarkAttachments]),
                    UserPermissions::AttachmentsLimit => (int)$permissionData[UserPermissions::AttachmentsLimit]
                ];
            }

            break;
        case CACHE_TYPE_FIELD_SETS:
            $fieldsetObjects = fieldsetGet(
                [],
                ['set_id', 'set_name']
            );

            foreach ($fieldsetObjects as $fieldsetID => $fieldsetData) {
                $cacheData[$fieldsetID] = [
                    'set_id' => (int)$fieldsetData['set_id'],
                    'set_name' => (string)$fieldsetData['set_name'],
                ];
            }

            break;
        case CACHE_TYPE_FIELDS:
            $queryFields = TABLES_DATA['myshowcase_fields'];

            unset($queryFields['unique_keys']);

            $fieldObjects = fieldsGet(
                [],
                array_keys($queryFields),
                ['order_by' => 'set_id, display_order']
            );

            foreach ($fieldObjects as $fieldID => $fieldData) {
                $cacheData[(int)$fieldData['set_id']][$fieldID] = [
                    'field_id' => (int)$fieldData['field_id'],
                    'set_id' => (int)$fieldData['set_id'],
                    'field_key' => (string)$fieldData['field_key'],
                    'html_type' => (string)$fieldData['html_type'],
                    'enabled' => (bool)$fieldData['enabled'],
                    'field_type' => (string)$fieldData['field_type'],
                    'display_in_create_update_page' => (bool)$fieldData['display_in_create_update_page'],
                    'display_in_view_page' => (bool)$fieldData['display_in_view_page'],
                    'display_in_main_page' => (bool)$fieldData['display_in_main_page'],
                    'minimum_length' => (int)$fieldData['minimum_length'],
                    'maximum_length' => (int)$fieldData['maximum_length'],
                    'is_required' => (bool)$fieldData['is_required'],
                    'allowed_groups_fill' => (string)$fieldData['allowed_groups_fill'],
                    'allowed_groups_view' => (string)$fieldData['allowed_groups_view'],
                    'default_value' => (string)$fieldData['default_value'],
                    'default_type' => (int)$fieldData['default_type'],
                    'parse' => (bool)$fieldData['parse'],
                    'display_order' => (int)$fieldData['display_order'],
                    'render_order' => (int)$fieldData['render_order'],
                    'enable_search' => (bool)$fieldData['enable_search'],
                    'enable_slug' => (bool)$fieldData['enable_slug'],
                    'enable_subject' => (bool)$fieldData['enable_subject'],
                    'format' => (string)$fieldData['format'],
                    'enable_editor' => (bool)$fieldData['enable_editor'],
                ];
            }

            break;
        case CACHE_TYPE_FIELD_DATA;
            $fieldDataObjects = fieldDataGet(
                [],
                ['set_id', 'field_id', 'value_id', 'value', 'value_id', 'display_order'],
                ['order_by' => 'set_id, field_id, display_order']
            );

            foreach ($fieldDataObjects as $fieldDataID => $fieldData) {
                $cacheData[(int)$fieldData['set_id']][$fieldDataID][(int)$fieldData['value_id']] = $fieldData;
            }

            break;
        case CACHE_TYPE_MODERATORS;
            $moderatorObjects = moderatorGet(
                [],
                [
                    'moderator_id',
                    'showcase_id',
                    'user_id',
                    'is_group',
                    ModeratorPermissions::CanApproveEntries,
                    ModeratorPermissions::CanEditEntries,
                    ModeratorPermissions::CanDeleteEntries,
                    ModeratorPermissions::CanDeleteComments
                ]
            );

            foreach ($moderatorObjects as $moderatorID => $moderatorData) {
                $cacheData[(int)$moderatorData['showcase_id']][$moderatorID] = $moderatorData;
            }

            break;
    }

    if ($cacheData) {
        $cache->update("myshowcase_{$cacheKey}", $cacheData);
    }

    return $cacheData;
}

function cacheGet(string $cacheKey, bool $forceReload = false): array
{
    global $cache;

    $cacheData = $cache->read("myshowcase_{$cacheKey}");

    if (!is_array($cacheData) && $forceReload || DEBUG) {
        $cacheData = cacheUpdate($cacheKey);
    }

    return $cacheData ?? [];
}

function showcaseInsert(array $showcaseData): int
{
    global $db;

    $db->insert_query('myshowcase_config', $showcaseData);

    return (int)$db->insert_id();
}

function showcaseUpdate(int $showcaseID, array $showcaseData): bool
{
    global $db;

    $db->update_query(
        'myshowcase_config',
        $showcaseData,
        "showcase_id='{$showcaseID}'"
    );

    return true;
}

function showcaseDelete(array $whereClauses = []): bool
{
    global $db;

    $db->delete_query('myshowcase_config', implode(' AND ', $whereClauses));

    return true;
}

function showcaseGet(array $whereClauses = [], array $queryFields = [], array $queryOptions = []): array
{
    global $db;

    $query = $db->simple_select(
        'myshowcase_config',
        implode(',', array_merge(['showcase_id'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $showcaseObjects = [];

    while ($showcaseData = $db->fetch_array($query)) {
        $showcaseObjects[(int)$showcaseData['showcase_id']] = $showcaseData;
    }

    return $showcaseObjects;
}

function showcaseDataTableExists(int $showcaseID): bool
{
    static $dataTableExists = [];

    if (!isset($dataTableExists[$showcaseID])) {
        global $db;

        $dataTableExists[$showcaseID] = (bool)$db->table_exists('myshowcase_data' . $showcaseID);
    }

    return $dataTableExists[$showcaseID];
}

function showcaseDataTableFieldExists(int $showcaseID, string $fieldName): bool
{
    global $db;

    return $db->field_exists($fieldName, 'myshowcase_data' . $showcaseID);
}

function showcaseDataTableFieldRename(
    int $showcaseID,
    string $oldFieldName,
    string $newFieldName,
    string $newDefinition
): bool {
    global $db;
    return $db->rename_column('myshowcase_data' . $showcaseID, $oldFieldName, $newFieldName, $newDefinition);
}

function showcaseDataTableDrop(int $showcaseID): bool
{
    global $db;

    $db->drop_table('myshowcase_data' . $showcaseID);

    return true;
}

function showcaseDataTableFieldDrop(int $showcaseID, string $fieldName): bool
{
    global $db;

    $db->drop_column('myshowcase_data' . $showcaseID, $fieldName);

    return true;
}

function entryDataInsert(int $showcaseID, array $entryData, bool $isUpdate = false, int $entryID = 0): int
{
    global $db;

    $showcaseFieldSetID = (int)(showcaseGet(["showcase_id='{$showcaseID}'"], ['field_set_id'], ['limit' => 1]
    )['field_set_id'] ?? 0);

    $fieldObjects = fieldsGet(
        ["set_id='{$showcaseFieldSetID}'", "enabled='1'"],
        [
            'field_id',
            'field_key',
            'field_type'
        ]
    );

    $showcaseInsertData = [];

    if (isset($entryData['entry_slug'])) {
        $showcaseInsertData['entry_slug'] = $db->escape_string($entryData['entry_slug']);
    }

    if (isset($entryData['user_id'])) {
        $showcaseInsertData['user_id'] = (int)$entryData['user_id'];
    }

    if (isset($entryData['views'])) {
        $showcaseInsertData['views'] = (int)$entryData['views'];
    }

    if (isset($entryData['comments'])) {
        $showcaseInsertData['comments'] = (int)$entryData['comments'];
    }

    if (isset($entryData['submit_data'])) {
        $showcaseInsertData['submit_data'] = (int)$entryData['submit_data'];
    }

    if (isset($entryData['dateline'])) {
        $showcaseInsertData['dateline'] = (int)$entryData['dateline'];
    } elseif (!$isUpdate) {
        $showcaseInsertData['dateline'] = TIME_NOW;
    }

    if (isset($entryData['status'])) {
        $showcaseInsertData['status'] = (int)$entryData['status'];
    }

    if (isset($entryData['moderator_user_id'])) {
        $showcaseInsertData['moderator_user_id'] = (int)$entryData['moderator_user_id'];
    }

    if (isset($entryData['dateline'])) {
        $showcaseInsertData['dateline'] = (int)$entryData['dateline'];
    }

    if (isset($entryData['edit_stamp'])) {
        $showcaseInsertData['edit_stamp'] = (int)$entryData['edit_stamp'];
    }

    if (isset($entryData['approved'])) {
        $showcaseInsertData['approved'] = (int)$entryData['approved'];
    }

    if (isset($entryData['approved_by'])) {
        $showcaseInsertData['approved_by'] = (int)$entryData['approved_by'];
    }

    if (isset($entryData['entry_hash'])) {
        $showcaseInsertData['entry_hash'] = $db->escape_string($entryData['entry_hash']);
    }

    foreach ($fieldObjects as $fieldID => $fieldData) {
        if (isset($entryData[$fieldData['field_key']])) {
            if (fieldTypeMatchInt($fieldData['field_type'])) {
                $showcaseInsertData[$fieldData['field_key']] = (int)$entryData[$fieldData['field_key']];
            } elseif (fieldTypeMatchFloat($fieldData['field_type'])) {
                $showcaseInsertData[$fieldData['field_key']] = (float)$entryData[$fieldData['field_key']];
            } elseif (fieldTypeMatchChar($fieldData['field_type']) ||
                fieldTypeMatchText($fieldData['field_type']) ||
                fieldTypeMatchDateTime($fieldData['field_type'])) {
                $showcaseInsertData[$fieldData['field_key']] = $db->escape_string($entryData[$fieldData['field_key']]);
            } elseif (fieldTypeMatchBinary($fieldData['field_type'])) {
                $showcaseInsertData[$fieldData['field_key']] = $db->escape_binary($entryData[$fieldData['field_key']]);
            }
        }
    }

    if ($isUpdate) {
        $db->update_query('myshowcase_data' . $showcaseID, $showcaseInsertData, "entry_id='{$entryID}'");
    } elseif ($showcaseInsertData) {
        $entryID = $db->insert_query('myshowcase_data' . $showcaseID, $showcaseInsertData);
    }

    return $entryID;
}

function entryDataUpdate(int $showcaseID, int $entryID, array $entryData): int
{
    return entryDataInsert($showcaseID, $entryData, true, $entryID);
}

function entryDataGet(
    int $showcaseID,
    array $whereClauses = [],
    array $queryFields = [],
    array $queryOptions = []
): array {
    global $db;

    $query = $db->simple_select(
        'myshowcase_data' . $showcaseID,
        implode(',', array_merge(['entry_id'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $entriesObjects = [];

    while ($fieldValueData = $db->fetch_array($query)) {
        $entriesObjects[(int)$fieldValueData['entry_id']] = $fieldValueData;

        foreach (DATA_TABLE_STRUCTURE['myshowcase_data'] as $defaultFieldKey => $defaultFieldData) {
            if (isset($entriesObjects[(int)$fieldValueData['entry_id']][$defaultFieldKey])) {
                //$entriesObjects[(int)$fieldValueData['entry_id']][$defaultFieldKey] = 123;
            }
        }
    }

    return $entriesObjects;
}

function permissionsInsert(array $permissionData): int
{
    global $db;

    $db->insert_query('myshowcase_permissions', $permissionData);

    return (int)$db->insert_id();
}

function permissionsUpdate(array $whereClauses = [], array $permissionData = []): bool
{
    global $db;

    $db->update_query(
        'myshowcase_permissions',
        $permissionData,
        implode(' AND ', $whereClauses)
    );

    return true;
}

function permissionsDelete(array $whereClauses = []): bool
{
    global $db;

    $db->delete_query('myshowcase_permissions', implode(' AND ', $whereClauses));

    return true;
}

function permissionsGet(array $whereClauses = [], array $queryFields = [], array $queryOptions = []): array
{
    global $db;

    $query = $db->simple_select(
        'myshowcase_permissions',
        implode(',', array_merge(['permission_id'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $permissionData = [];

    while ($permission = $db->fetch_array($query)) {
        $permissionData[(int)$permission['permission_id']] = $permission;
    }

    return $permissionData;
}

function moderatorsInsert(array $moderatorData): bool
{
    global $db;

    $db->insert_query('myshowcase_moderators', $moderatorData);

    return true;
}

function moderatorsUpdate(array $whereClauses = [], array $moderatorData = []): bool
{
    global $db;

    $db->update_query(
        'myshowcase_moderators',
        $moderatorData,
        implode(' AND ', $whereClauses)
    );

    return true;
}

function moderatorGet(array $whereClauses = [], array $queryFields = [], array $queryOptions = []): array
{
    global $db;

    $query = $db->simple_select(
        'myshowcase_moderators',
        implode(',', array_merge(['moderator_id'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $moderatorData = [];

    while ($moderator = $db->fetch_array($query)) {
        $moderatorData[(int)$moderator['moderator_id']] = $moderator;
    }

    return $moderatorData;
}

function moderatorsDelete(array $whereClauses = []): bool
{
    global $db;

    $db->delete_query('myshowcase_moderators', implode(' AND ', $whereClauses));

    return true;
}

function fieldsetInsert(array $fieldsetData): int
{
    global $db;

    $db->insert_query('myshowcase_fieldsets', $fieldsetData);

    return (int)$db->insert_id();
}

function fieldsetUpdate(int $fieldsetID, array $fieldsetData): bool
{
    global $db;

    $db->update_query(
        'myshowcase_fieldsets',
        $fieldsetData,
        "set_id='{$fieldsetID}'"
    );

    return true;
}

function fieldsetGet(array $whereClauses = [], array $queryFields = [], array $queryOptions = []): array
{
    global $db;

    $query = $db->simple_select(
        'myshowcase_fieldsets',
        implode(',', array_merge(['set_id'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $fieldsetData = [];

    while ($fieldset = $db->fetch_array($query)) {
        $fieldsetData[(int)$fieldset['set_id']] = $fieldset;
    }

    return $fieldsetData;
}

function fieldsetDelete(array $whereClauses = []): bool
{
    global $db;

    $db->delete_query('myshowcase_fieldsets', implode(' AND ', $whereClauses));

    return true;
}

function fieldsInsert(array $fieldData, bool $isUpdate = false, int $fieldID = 0): int
{
    global $db;

    $insertData = [];

    if (isset($fieldData['set_id'])) {
        $insertData['set_id'] = (int)$fieldData['set_id'];
    }

    if (isset($fieldData['field_key'])) {
        $insertData['field_key'] = $db->escape_string($fieldData['field_key']);
    }

    if (isset($fieldData['html_type'])) {
        $insertData['html_type'] = $db->escape_string($fieldData['html_type']);
    }

    if (isset($fieldData['enabled'])) {
        $insertData['enabled'] = (int)$fieldData['enabled'];
    }

    if (isset($fieldData['field_type'])) {
        $insertData['field_type'] = $db->escape_string($fieldData['field_type']);
    }

    if (isset($fieldData['display_in_create_update_page'])) {
        $insertData['display_in_create_update_page'] = $db->escape_string($fieldData['display_in_create_update_page']);
    }

    if (isset($fieldData['display_in_view_page'])) {
        $insertData['display_in_view_page'] = $db->escape_string($fieldData['display_in_view_page']);
    }

    if (isset($fieldData['display_in_main_page'])) {
        $insertData['display_in_main_page'] = $db->escape_string($fieldData['display_in_main_page']);
    }

    if (isset($fieldData['minimum_length'])) {
        $insertData['minimum_length'] = (int)$fieldData['minimum_length'];
    }

    if (isset($fieldData['maximum_length'])) {
        $insertData['maximum_length'] = (int)$fieldData['maximum_length'];
    }

    if (isset($fieldData['is_required'])) {
        $insertData['is_required'] = (int)$fieldData['is_required'];
    }

    if (isset($fieldData['allowed_groups_fill'])) {
        $insertData['allowed_groups_fill'] = $db->escape_string(
            is_array($fieldData['allowed_groups_fill']) ? implode(
                ',',
                $fieldData['allowed_groups_fill']
            ) : $fieldData['allowed_groups_fill']
        );
    }

    if (isset($fieldData['allowed_groups_view'])) {
        $insertData['allowed_groups_view'] = $db->escape_string(
            is_array($fieldData['allowed_groups_view']) ? implode(
                ',',
                $fieldData['allowed_groups_view']
            ) : $fieldData['allowed_groups_view']
        );
    }

    if (isset($fieldData['default_value'])) {
        $insertData['default_value'] = $db->escape_string($fieldData['default_value']);
    }

    if (isset($fieldData['default_type'])) {
        $insertData['default_type'] = (int)$fieldData['default_type'];
    }

    if (isset($fieldData['parse'])) {
        $insertData['parse'] = (int)$fieldData['parse'];
    }

    if (isset($fieldData['display_order'])) {
        $insertData['display_order'] = (int)$fieldData['display_order'];
    }

    if (isset($fieldData['render_order'])) {
        $insertData['render_order'] = (int)$fieldData['render_order'];
    }

    if (isset($fieldData['enable_search'])) {
        $insertData['enable_search'] = (int)$fieldData['enable_search'];
    }

    if (isset($fieldData['enable_slug'])) {
        $insertData['enable_slug'] = (int)$fieldData['enable_slug'];
    }

    if (isset($fieldData['enable_subject'])) {
        $insertData['enable_subject'] = (int)$fieldData['enable_subject'];
    }

    if (isset($fieldData['format'])) {
        $insertData['format'] = $db->escape_string($fieldData['format']);
    }

    if (isset($fieldData['enable_editor'])) {
        $insertData['enable_editor'] = (int)$fieldData['enable_editor'];
    }

    if ($isUpdate) {
        $db->update_query('myshowcase_fields', $insertData, "field_id='{$fieldID}'");
    } else {
        $db->insert_query('myshowcase_fields', $insertData);
    }

    return $fieldID;
}

function fieldsUpdate(array $fieldData, int $fieldID): int
{
    return fieldsInsert($fieldData, true, $fieldID);
}

function fieldsGet(array $whereClauses = [], array $queryFields = [], array $queryOptions = []): array
{
    global $db;

    $query = $db->simple_select(
        'myshowcase_fields',
        implode(',', array_merge(['field_id'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $fieldData = [];

    while ($field = $db->fetch_array($query)) {
        $fieldData[(int)$field['field_id']] = $field;
    }

    return $fieldData;
}

function fieldsDelete(array $whereClauses = []): bool
{
    global $db;

    $db->delete_query('myshowcase_fields', implode(' AND ', $whereClauses));

    return true;
}

function fieldDataInsert(array $fieldData, bool $isUpdate = false, int $fieldDataID): int
{
    global $db;

    $db->insert_query('myshowcase_field_data', $fieldData);

    return (int)$db->insert_id();
}

function fieldDataUpdate(array $whereClauses, array $fieldData): int
{
    global $db;

    $db->update_query(
        'myshowcase_field_data',
        $fieldData,
        implode(' AND ', $whereClauses)
    );

    return fieldDataInsert($fieldData, true, $fieldDataID);
}

function fieldDataGet(array $whereClauses = [], array $queryFields = [], array $queryOptions = []): array
{
    global $db;

    $query = $db->simple_select(
        'myshowcase_field_data',
        implode(',', array_merge(['field_data_id'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $fieldData = [];

    while ($field = $db->fetch_array($query)) {
        $fieldData[(int)$field['field_data_id']] = $field;
    }

    return $fieldData;
}

function fieldDataDelete(array $whereClauses = []): bool
{
    global $db;

    $db->delete_query('myshowcase_field_data', implode(' AND ', $whereClauses));

    return true;
}

function attachmentInsert(array $attachmentData, bool $isUpdate = false, int $attachmentID = 0): int
{
    global $db;

    $insertData = [];

    if (isset($attachmentData['showcase_id'])) {
        $insertData['showcase_id'] = (int)$attachmentData['showcase_id'];
    }

    if (isset($attachmentData['entry_id'])) {
        $insertData['entry_id'] = (int)$attachmentData['entry_id'];
    }

    if (isset($attachmentData['entry_hash'])) {
        $insertData['entry_hash'] = $db->escape_string($attachmentData['entry_hash']);
    }

    if (isset($attachmentData['user_id'])) {
        $insertData['user_id'] = (int)$attachmentData['user_id'];
    }

    if (isset($attachmentData['file_name'])) {
        $insertData['file_name'] = $db->escape_string($attachmentData['file_name']);
    }

    if (isset($attachmentData['file_type'])) {
        $insertData['file_type'] = $db->escape_string($attachmentData['file_type']);
    }

    if (isset($attachmentData['file_size'])) {
        $insertData['file_size'] = (int)$attachmentData['file_size'];
    }

    if (isset($attachmentData['attachment_name'])) {
        $insertData['attachment_name'] = $db->escape_string($attachmentData['attachment_name']);
    }

    if (isset($attachmentData['downloads'])) {
        $insertData['downloads'] = (int)$attachmentData['downloads'];
    }

    if (isset($attachmentData['dateline'])) {
        $insertData['dateline'] = (int)$attachmentData['dateline'];
    }

    if (isset($attachmentData['thumbnail'])) {
        $insertData['thumbnail'] = $db->escape_string($attachmentData['thumbnail']);
    }

    /*if (isset($attachmentData['dimensions'])) {
        $insertData['dimensions'] = $db->escape_string($attachmentData['dimensions']);
    }

    if (isset($attachmentData['md5_hash'])) {
        $insertData['md5_hash'] = $db->escape_string($attachmentData['md5_hash']);
    }

    if (isset($attachmentData['edit_stamp'])) {
        $insertData['edit_stamp'] = (int)$attachmentData['edit_stamp'];
    }*/

    if (isset($attachmentData['status'])) {
        $insertData['status'] = (int)$attachmentData['status'];
    }

    /*if (isset($attachmentData['cdn_file'])) {
        $insertData['cdn_file'] = (int)$attachmentData['cdn_file'];
    }*/

    if ($isUpdate) {
        $db->update_query('myshowcase_attachments', $insertData, "attachment_id='{$attachmentID}'");
    } else {
        $attachmentID = (int)$db->insert_query('myshowcase_attachments', $insertData);
    }

    return $attachmentID;
}

function attachmentUpdate(array $attachmentData, int $attachmentID): int
{
    return attachmentInsert($attachmentData, true, $attachmentID);
}

function attachmentGet(array $whereClauses = [], array $queryFields = [], array $queryOptions = []): array
{
    global $db;

    $query = $db->simple_select(
        'myshowcase_attachments',
        implode(',', array_merge(['attachment_id'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $attachmentObjects = [];

    while ($attachment = $db->fetch_array($query)) {
        $attachmentObjects[(int)$attachment['attachment_id']] = $attachment;
    }

    return $attachmentObjects;
}

function attachmentDelete(array $whereClauses = []): bool
{
    global $db;

    $db->delete_query('myshowcase_attachments', implode(' AND ', $whereClauses));

    return true;
}

function commentInsert(array $commentData, bool $isUpdate = false, int $commentID = 0): int
{
    global $db;

    $insertData = [];

    if (isset($commentData['showcase_id'])) {
        $insertData['showcase_id'] = (int)$commentData['showcase_id'];
    }

    if (isset($commentData['entry_id'])) {
        $insertData['entry_id'] = (int)$commentData['entry_id'];
    }

    if (isset($commentData['user_id'])) {
        $insertData['user_id'] = (int)$commentData['user_id'];
    }

    if (isset($commentData['ipaddress'])) {
        $insertData['ipaddress'] = $db->escape_string($commentData['ipaddress']);
    }

    if (isset($commentData['comment'])) {
        $insertData['comment'] = $db->escape_string($commentData['comment']);
    }

    if (isset($commentData['dateline'])) {
        $insertData['dateline'] = (int)$commentData['dateline'];
    }

    if (isset($commentData['status'])) {
        $insertData['status'] = (int)$commentData['status'];
    }

    if (isset($commentData['moderator_user_id'])) {
        $insertData['moderator_user_id'] = (int)$commentData['moderator_user_id'];
    }

    if ($isUpdate) {
        $db->update_query('myshowcase_comments', $insertData, "comment_id='{$commentID}'");
    } else {
        $commentID = (int)$db->insert_query('myshowcase_comments', $insertData);
    }

    return $commentID;
}

function commentUpdate(array $commentData, int $commentID = 0): int
{
    return commentInsert($commentData, true, $commentID);
}

function commentsGet(array $whereClauses = [], array $queryFields = [], array $queryOptions = []): array
{
    global $db;

    $query = $db->simple_select(
        'myshowcase_comments',
        implode(', ', array_merge(['comment_id'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $commentObjects = [];

    while ($comment = $db->fetch_array($query)) {
        $commentObjects[(int)$comment['comment_id']] = $comment;
    }

    return $commentObjects;
}

function commentsDelete(array $whereClauses = []): bool
{
    global $db;

    $db->delete_query('myshowcase_comments', implode(' AND ', $whereClauses));

    return true;
}

/**
 * Remove an attachment from a specific showcase
 *
 * @param int The showcase ID
 * @param string The entry_hash if available
 * @param int The attachment ID
 */
function attachmentRemove(
    Showcase $showcase,
    string $entryHash = '',
    int $attachmentID = 0,
    int $entryID = 0
): bool {
    $whereClauses = ["showcase_id='{$showcase->showcase_id}'", "attachment_id='{$attachmentID}'"];

    if (!empty($entryHash)) {
        global $db;

        $whereClauses[] = "entry_hash='{$db->escape_string($entryHash)}'";
    } else {
        $whereClauses[] = "entry_id='{$entryID}'";
    }

    $attachmentData = attachmentGet($whereClauses, ['attachment_name', 'thumbnail', 'status'], ['limit' => 1]);

    $attachmentData = hooksRun('remove_attachment_do_delete', $attachmentData);

    attachmentDelete(["attachment_id='{$attachmentID}'"]);

    unlink($showcase->images_directory . '/' . $attachmentData['attachment_name']);

    if (!empty($attachmentData['thumbnail'])) {
        unlink($showcase->images_directory . '/' . $attachmentData['thumbnail']);
    }

    $dateDirectory = explode('/', $attachmentData['attachment_name']);

    if (!empty($dateDirectory[0]) && is_dir($showcase->images_directory . '/' . $dateDirectory[0])) {
        rmdir($showcase->images_directory . '/' . $dateDirectory[0]);
    }

    return true;
}

/**
 * Upload an attachment in to the file system
 *
 * @param array Attachment data (as fed by PHPs $_FILE)
 * @param bool Whether or not we are updating a current attachment or inserting a new one
 * @return array Array of attachment data if successful, otherwise array of error data
 */
function attachmentUpload(
    Showcase $showcase,
    array $attachmentData,
    string $entryHash = '',
    bool $isUpdate = false,
    bool $addWaterMark = false,
    int $gid = 0
): array {
    global $db, $mybb, $lang, $cache, $showcase_uid;

    $returnData = [];

    if (isset($attachmentData['error']) && $attachmentData['error'] !== 0) {
        $returnData['error'] = $lang->error_uploadfailed . $lang->error_uploadfailed_detail;

        switch ($attachmentData['error']) {
            case 1: // UPLOAD_ERR_INI_SIZE
                $returnData['error'] .= $lang->error_uploadfailed_php1;
                break;
            case 2: // UPLOAD_ERR_FORM_SIZE
                $returnData['error'] .= $lang->error_uploadfailed_php2;
                break;
            case 3: // UPLOAD_ERR_PARTIAL
                $returnData['error'] .= $lang->error_uploadfailed_php3;
                break;
            case 4: // UPLOAD_ERR_NO_FILE
                $returnData['error'] .= $lang->error_uploadfailed_php4;
                break;
            case 6: // UPLOAD_ERR_NO_TMP_DIR
                $returnData['error'] .= $lang->error_uploadfailed_php6;
                break;
            case 7: // UPLOAD_ERR_CANT_WRITE
                $returnData['error'] .= $lang->error_uploadfailed_php7;
                break;
            default:
                $returnData['error'] .= $lang->sprintf($lang->error_uploadfailed_phpx, $attachmentData['error']);
                break;
        }
        return $returnData;
    }

    if (!is_uploaded_file($attachmentData['tmp_name']) || empty($attachmentData['tmp_name'])) {
        $returnData['error'] = $lang->error_uploadfailed . $lang->error_uploadfailed_php4;

        return $returnData;
    }

    $fileExtension = get_extension($attachmentData['name']);

    $query = $db->simple_select('attachtypes', '*', "extension='{$db->escape_string($fileExtension)}'");

    $attachmentType = $db->fetch_array($query);

    if (empty($attachmentType['atid'])) {
        $returnData['error'] = $lang->error_attachtype;

        return $returnData;
    }

    // Check the size
    if ($attachmentData['size'] > $attachmentType['maxsize'] * 1024 && !empty($attachmentType['maxsize'])) {
        $returnData['error'] = $lang->sprintf($lang->error_attachsize, $attachmentType['maxsize']);

        return $returnData;
    }

    // Double check attachment space usage
    if ($mybb->usergroup['attachquota'] > 0) {
        $showcase_uid = (int)$showcase_uid;

        $userTotalUsage = attachmentGet(
            ["user_id='{$showcase_uid}'"],
            ['SUM(file_size) AS userTotalUsage'],
            ['group_by' => 'showcase_id']
        )['userTotalUsage'] ?? 0;

        $userTotalUsage = $userTotalUsage + $attachmentData['size'];

        if ($userTotalUsage > ($mybb->usergroup['attachquota'] * 1024)) {
            $returnData['error'] = $lang->sprintf(
                $lang->error_reachedattachquota,
                get_friendly_size($mybb->usergroup['attachquota'] * 1024)
            );

            return $returnData;
        }
    }

    $existingAttachment = attachmentGet(
        [
            "file_name='{$db->escape_string($attachmentData['name'])}'",
            "showcase_id='{$showcase->showcase_id}'",
            "(entry_hash='{$db->escape_string($entryHash)}' OR (entry_id='{$gid}' AND entry_id!='0'))"
        ],
        [],
        ['limit' => 1]
    );

    $attachmentID = (int)($existingAttachment['attachment_id'] ?? 0);

    if ($attachmentID && !$isUpdate) {
        $returnData['error'] = $lang->error_alreadyuploaded;

        return $returnData;
    }

    // Check if the attachment directory (YYYYMM) exists, if not, create it
    $directoryMonthName = gmdate('Ym');

    if (!is_dir($showcase->images_directory . '/' . $directoryMonthName)) {
        mkdir($showcase->images_directory . '/' . $directoryMonthName);

        if (!is_dir($showcase->images_directory . '/' . $directoryMonthName)) {
            $directoryMonthName = '';
        }
    }

    // If safe_mode is enabled, don't attempt to use the monthly directories as it won't work
    if (ini_get('safe_mode') || strtolower(ini_get('safe_mode')) == 'on') {
        $directoryMonthName = '';
    }

    // All seems to be good, lets move the attachment!
    $fileName = "post_{$showcase_uid}_" . TIME_NOW . '_' . md5(random_str()) . '.attach';

    $fileData = fileUpload($attachmentData, $showcase->images_directory . '/' . $directoryMonthName, $fileName);

    // Failed to create the attachment in the monthly directory, just throw it in the main directory
    if ($fileData['error'] && $directoryMonthName) {
        $fileData = fileUpload($attachmentData, $showcase->images_directory . '/', $fileName);
    }

    if ($directoryMonthName) {
        $fileName = $directoryMonthName . '/' . $fileName;
    }

    if ($fileData['error']) {
        $returnData['error'] = $lang->error_uploadfailed . $lang->error_uploadfailed_detail;

        switch ($fileData['error']) {
            case UPLOAD_STATUS_INVALID:
                $returnData['error'] .= $lang->error_uploadfailed_nothingtomove;
                break;
            case UPLOAD_STATUS_FAILED:
                $returnData['error'] .= $lang->error_uploadfailed_movefailed;
                break;
        }

        return $returnData;
    }

    // Lets just double check that it exists
    if (!file_exists($showcase->images_directory . '/' . $fileName)) {
        $returnData['error'] = $lang->error_uploadfailed . $lang->error_uploadfailed_detail . $lang->error_uploadfailed_lost;

        return $returnData;
    }

    $insertData = [
        'showcase_id' => intval($showcase->showcase_id),
        'entry_id' => intval($gid),
        'entry_hash' => $db->escape_string($entryHash),
        'user_id' => intval($showcase_uid),
        'file_name' => $db->escape_string($fileData['original_filename']),
        'file_type' => $db->escape_string($fileData['type']),
        'file_size' => intval($fileData['size']),
        'attachment_name' => $fileName,
        'downloads' => 0,
        'status' => 1,
        'dateline' => TIME_NOW
    ];

    // If we're uploading an image, check the MIME type compared to the image type and attempt to generate a thumbnail
    if (in_array($fileExtension, ['gif', 'png', 'jpg', 'jpeg', 'jpe'])) {
        // Check a list of known MIME types to establish what kind of image we're uploading
        switch (my_strtolower($fileData['type'])) {
            case 'image/gif':
                $fileType = 1;
                break;
            case 'image/jpeg':
            case 'image/x-jpg':
            case 'image/x-jpeg':
            case 'image/pjpeg':
            case 'image/jpg':
                $fileType = 2;
                break;
            case 'image/png':
            case 'image/x-png':
                $fileType = 3;
                break;
            default:
                $fileType = 0;
        }

        $supportedMimeTypes = [];

        foreach ((array)$cache->read('attachtypes') as $attachmentType) {
            if (!empty($attachmentType['mimetype'])) {
                $supportedMimeTypes[] = $attachmentType['mimetype'];
            }
        }

        // Check if the uploaded file type matches the correct image type (returned by getimagesize)
        $imageDimensions = getimagesize($showcase->images_directory . '/' . $fileName);

        $fileMimeType = '';

        $filePath = $showcase->images_directory . '/' . $fileName;

        if (function_exists('finfo_open')) {
            $fileInformation = finfo_open(FILEINFO_MIME);

            list($fileMimeType,) = explode(';', finfo_file($fileInformation, $filePath), 1);

            finfo_close($fileInformation);
        } elseif (function_exists('mime_content_type')) {
            $fileMimeType = mime_content_type($filePath);
        }

        if (!is_array($imageDimensions) || ($imageDimensions[2] != $fileType && !in_array(
                    $fileMimeType,
                    $supportedMimeTypes
                ))) {
            unlink($showcase->images_directory . '/' . $fileName);

            $returnData['error'] = $lang->error_uploadfailed;

            return $returnData;
        }

        //if requested and enabled, watermark the master image
        if ($showcase->userPermissions[UserPermissions::CanWaterMarkAttachments] && $addWaterMark && file_exists(
                $showcase->water_mark_image
            )) {
            //get watermark image object
            switch (strtolower(get_extension($showcase->water_mark_image))) {
                case 'gif':
                    $waterMarkImage = imagecreatefromgif($showcase->water_mark_image);
                    break;
                case 'jpg':
                case 'jpeg':
                case 'jpe':
                    $waterMarkImage = imagecreatefromjpeg($showcase->water_mark_image);
                    break;
                case 'png':
                    $waterMarkImage = imagecreatefrompng($showcase->water_mark_image);
                    break;
            }

            //check if we have an image
            if (!empty($waterMarkImage)) {
                //get watermark size
                $waterMarkImageWidth = imagesx($waterMarkImage);

                $waterMarkImageHeight = imagesy($waterMarkImage);

                //get size of base image
                $fileSize = getimagesize($showcase->images_directory . '/' . $fileName);

                //set watermark location
                switch ($showcase->water_mark_image_location) {
                    case 'lower-left':
                        $waterMarkPositionX = 5;

                        $waterMarkPositionY = $fileSize[1] - $waterMarkImageHeight - 5;
                        break;
                    case 'lower-right':
                        $waterMarkPositionX = $fileSize[0] - $waterMarkImageWidth - 5;

                        $waterMarkPositionY = $fileSize[1] - $waterMarkImageHeight - 5;
                        break;
                    case 'center':
                        $waterMarkPositionX = $fileSize[0] / 2 - $waterMarkImageWidth / 2;

                        $waterMarkPositionY = $fileSize[1] / 2 - $waterMarkImageHeight / 2;
                        break;
                    case 'upper-left':
                        $waterMarkPositionX = 5;

                        $waterMarkPositionY = 5;
                        break;
                    case 'upper-right':
                        $waterMarkPositionX = $fileSize[0] - $waterMarkImageWidth - 5;

                        $waterMarkPositionY = 5;
                        break;
                }

                //get base image object
                switch ($fileType) {
                    case 1:
                        $fileImage = imagecreatefromgif($showcase->images_directory . '/' . $fileName);
                        break;
                    case 2:
                        $fileImage = imagecreatefromjpeg($showcase->images_directory . '/' . $fileName);
                        break;
                    case 3:
                        $fileImage = imagecreatefrompng($showcase->images_directory . '/' . $fileName);
                        break;
                }

                if (!empty($fileImage) && isset($waterMarkPositionX) && isset($waterMarkPositionY)) {
                    imagealphablending($fileImage, true);

                    imagealphablending($waterMarkImage, true);

                    imagecopy(
                        $fileImage,
                        $waterMarkImage,
                        $waterMarkPositionX,
                        $waterMarkPositionY,
                        0,
                        0,
                        min($waterMarkImageWidth, $fileSize[0]),
                        min($waterMarkImageHeight, $fileSize[1])
                    );

                    //remove watermark from memory
                    imagedestroy($waterMarkImage);

                    //write modified file

                    $f = fopen($showcase->images_directory . '/' . $fileName, 'w');

                    if ($f) {
                        ob_start();

                        switch ($fileType) {
                            case 1:
                                imagegif($fileImage);
                                break;
                            case 2:
                                imagejpeg($fileImage);
                                break;
                            case 3:
                                imagepng($fileImage);
                                break;
                        }

                        $content = ob_get_clean();

                        ob_end_clean();

                        fwrite($f, $content);

                        fclose($f);

                        imagedestroy($fileImage);
                    }
                }
            }
        }

        require_once MYBB_ROOT . 'inc/functions_image.php';

        $thumbnailName = str_replace('.attach', "_thumb.$fileExtension", $fileName);

        $fileThumbnail = generate_thumbnail(
            $showcase->images_directory . '/' . $fileName,
            $showcase->images_directory,
            $thumbnailName,
            $showcase->thumb_height,
            $showcase->thumb_width
        );

        if ($fileThumbnail['file_name']) {
            $insertData['thumbnail'] = $fileThumbnail['file_name'];
        } elseif ($fileThumbnail['code'] === 4) {
            $insertData['thumbnail'] = 'SMALL';
        }
    }

    $insertData = hooksRun('upload_attachment_do_insert', $insertData);

    if ($attachmentID && $isUpdate) {
        unset($insertData['downloads']); // Keep our download count if we're updating an attachment

        attachmentUpdate($insertData, $attachmentID);
    } else {
        $attachmentID = attachmentInsert($insertData);
    }

    return $returnData;
}

/**
 * Actually move a file to the uploads directory
 *
 * @param array The PHP $_FILE array for the file
 * @param string The path to save the file in
 * @param string The file_name for the file (if blank, current is used)
 */
function fileUpload(array $fileData, string $uploadsPath, string $fileName = ''): array
{
    $returnData = [];

    if (empty($fileData['field_key']) || $fileData['field_key'] === 'none' || $fileData['size'] < 1) {
        $returnData['error'] = UPLOAD_STATUS_INVALID;

        return $returnData;
    }

    if (!$fileName) {
        $fileName = $fileData['field_key'];
    }

    $returnData['original_filename'] = preg_replace('#/$#', '', $fileData['field_key']);

    $fileName = preg_replace('#/$#', '', $fileName);

    if (!move_uploaded_file($fileData['tmp_name'], $uploadsPath . '/' . $fileName)) {
        $returnData['error'] = UPLOAD_STATUS_FAILED;

        return $returnData;
    }

    my_chmod($uploadsPath . '/' . $fileName, '0644');

    $returnData['file_name'] = $fileName;

    $returnData['path'] = $uploadsPath;

    $returnData['type'] = $fileData['type'];

    $returnData['size'] = $fileData['size'];

    return hooksRun('upload_file_end', $returnData);
}

function entryGetRandom(): string
{
    global $db, $lang, $mybb, $cache, $templates;

    //get list of enabled myshowcases with random in portal turned on
    $showcase_list = [];

    $myshowcases = cacheGet(CACHE_TYPE_CONFIG);
    foreach ($myshowcases as $id => $myshowcase) {
        //$myshowcase['build_random_entry_widget'] == 1;
        if ($myshowcase['enabled'] == 1 && $myshowcase['build_random_entry_widget'] == 1) {
            $showcase_list[$id]['name'] = $myshowcase['name'];
            $showcase_list[$id]['mainfile'] = $myshowcase['mainfile'];
            $showcase_list[$id]['images_directory'] = $myshowcase['images_directory'];
            $showcase_list[$id]['field_set_id'] = $myshowcase['field_set_id'];
        }
    }

    //if no showcases set to show on portal return
    if (count($showcase_list) == 0) {
        return '';
    } else {
        //get a random showcase showcase_id of those enabled
        $rand_id = array_rand($showcase_list, 1);
        $rand_showcase = $showcase_list[$rand_id];

        /* URL Definitions */
        if ($mybb->settings['seourls'] == 'yes' || ($mybb->settings['seourls'] == 'auto' && $_SERVER['SEO_SUPPORT'] == 1)) {
            $showcase_file = strtolower($rand_showcase['name']) . '-view-{entry_id}.html';
        } else {
            $showcase_file = $rand_showcase['mainfile'] . '?action=view&entry_id={entry_id}';
        }

        //init fixed fields
        $fields_fixed = [];
        $fields_fixed[0]['name'] = 'g.user_id';
        $fields_fixed[0]['type'] = 'default';
        $fields_fixed[1]['name'] = 'dateline';
        $fields_fixed[1]['type'] = 'default';

        //get dynamic field info for the random showcase
        $field_list = [];
        $fields = cacheGet(CACHE_TYPE_FIELD_SETS);

        //get subset specific to the showcase given assigned field set
        $fields = $fields[$rand_showcase['field_set_id']];

        //get fields that are enabled and set for list display with pad to help sorting fixed fields)
        $description_list = [];
        foreach ($fields as $id => $field) {
            if (/*(int)$field['render_order'] !== \MyShowcase\Core\ALL_UNLIMITED_VALUE && */ $field['enabled'] == 1) {
                $field_list[$field['render_order'] + 10]['field_key'] = $field['field_key'];
                $field_list[$field['render_order'] + 10]['type'] = $field['html_type'];
                $description_list[$field['render_order']] = $field['field_key'];
            }
        }

        //merge dynamic and fixed fields
        $fields_for_search = array_merge($fields_fixed, $field_list);

        //build where clause based on search_field terms
        $addon_join = '';
        $addon_fields = '';
        foreach ($fields_for_search as $id => $field) {
            if ($field['type'] == FieldHtmlTypes::SelectSingle || $field['type'] == FieldHtmlTypes::Radio) {
                $addon_join .= ' LEFT JOIN ' . TABLE_PREFIX . 'myshowcase_field_data tbl_' . $field['field_key'] . ' ON (tbl_' . $field['field_key'] . '.value_id = g.' . $field['field_key'] . ' AND tbl_' . $field['field_key'] . ".field_id = '" . $field['field_id'] . "') ";
                $addon_fields .= ', tbl_' . $field['field_key'] . '.value AS ' . $field['field_key'];
            } else {
                $addon_fields .= ', ' . $field['field_key'];
            }
        }


        $rand_entry = 0;
        while ($rand_entry == 0) {
            $attachmentData = attachmentGet(
                ["showcase_id='{$rand_id}'", "file_type LIKE 'image%'", "status='1'", "entry_id!='0'"],
                ['entry_id', 'attachment_name', 'thumbnail'],
                ['limit' => 1, 'order_by' => 'RAND()']
            );

            $rand_entry = $attachmentData['entry_id'];
            $rand_entry_img = $attachmentData['attachment_name'];
            $rand_entry_thumb = $attachmentData['thumbnail'];

            if ($rand_entry) {
                $query = $db->query(
                    '
					SELECT entry_id, username, g.views, comments' . $addon_fields . '
					FROM ' . TABLE_PREFIX . 'myshowcase_data' . $rand_id . ' g
					LEFT JOIN ' . TABLE_PREFIX . 'users u ON (u.uid = g.user_id)
					' . $addon_join . '
					WHERE approved = 1 AND entry_id=' . $rand_entry . '
					LIMIT 0, 1'
                );

                if ($db->num_rows($query) == 0) {
                    $rand_entry = 0;
                }
            }
        }

        if (!$rand_entry || !isset($query)) {
            return '';
        }

        $alternativeBackground = 'trow2';
        $entry = $db->fetch_array($query);

        $lasteditdate = my_date($mybb->settings['dateformat'], $entry['dateline']);
        $lastedittime = my_date($mybb->settings['timeformat'], $entry['dateline']);
        $item_lastedit = $lasteditdate . '<br>' . $lastedittime;

        $item_member = build_profile_link(
            $entry['username'],
            $entry['user_id'],
        );

        $item_view_user = str_replace('{username}', $entry['username'], $lang->myshowcase_view_user);

        $entryUrl = str_replace('{entry_id}', $entry['entry_id'], $showcase_file);

        $entry['description'] = '';
        foreach ($description_list as $order => $name) {
            $entry['description'] .= $entry[$name] . ' ';
        }

        $alternativeBackground = ($alternativeBackground == 'trow1' ? 'trow2' : 'trow1');

        if ($rand_entry_thumb == 'SMALL') {
            $rand_img = $rand_showcase['images_directory'] . '/' . $rand_entry_img;
        } else {
            $rand_img = $rand_showcase['images_directory'] . '/' . $rand_entry_thumb;
        }

        return eval($templates->render('portal_rand_showcase'));
    }
}

function dataTableStructureGet(int $showcaseID = 0): array
{
    global $db;

    $dataTableStructure = DATA_TABLE_STRUCTURE['myshowcase_data'];

    if ($showcaseID &&
        ($showcaseData = showcaseGet(["showcase_id='{$showcaseID}'"], ['field_set_id'], ['limit' => 1]))) {
        $fieldsetID = (int)$showcaseData['field_set_id'];

        hooksRun('admin_summary_table_create_rebuild_start');

        foreach (
            fieldsGet(
                ["set_id='{$fieldsetID}'"],
                ['field_key', 'field_type', 'maximum_length', 'is_required', 'default_value', 'default_type']
            ) as $fieldID => $fieldData
        ) {
            $dataTableStructure[$fieldData['field_key']] = [];

            if (fieldTypeMatchInt($fieldData['field_type'])) {
                $dataTableStructure[$fieldData['field_key']]['type'] = $fieldData['field_type'];

                $dataTableStructure[$fieldData['field_key']]['size'] = (int)$fieldData['maximum_length'];

                if ($fieldData['default_value'] !== '') {
                    $defaultValue = (int)$fieldData['default_value'];
                } else {
                    $defaultValue = 0;
                }
            } elseif (fieldTypeMatchFloat($fieldData['field_type'])) {
                $dataTableStructure[$fieldData['field_key']]['type'] = $fieldData['field_type'];

                $dataTableStructure[$fieldData['field_key']]['size'] = (float)$fieldData['maximum_length'];

                if ($fieldData['default_value'] !== '') {
                    $defaultValue = (float)$fieldData['default_value'];
                } else {
                    $defaultValue = 0;
                }
            } elseif (fieldTypeMatchChar($fieldData['field_type'])) {
                $dataTableStructure[$fieldData['field_key']]['type'] = $fieldData['field_type'];

                $dataTableStructure[$fieldData['field_key']]['size'] = (int)$fieldData['maximum_length'];

                if ($fieldData['default_value'] !== '') {
                    $defaultValue = $db->escape_string($fieldData['default_value']);
                } else {
                    $defaultValue = '';
                }
            } elseif (fieldTypeMatchText($fieldData['field_type'])) {
                $dataTableStructure[$fieldData['field_key']]['type'] = $fieldData['field_type'];

                // todo, TEXT fields cannot have default values, should validate in front end
                $fieldData['default_type'] = FieldDefaultTypes::IsNull;
            } elseif (fieldTypeMatchDateTime($fieldData['field_type'])) {
                $dataTableStructure[$fieldData['field_key']]['type'] = $fieldData['field_type'];

                if ($fieldData['default_value'] !== '') {
                    $defaultValue = $db->escape_string($fieldData['default_value']);
                } else {
                    $defaultValue = '';
                }
            } elseif (fieldTypeMatchBinary($fieldData['field_type'])) {
                $dataTableStructure[$fieldData['field_key']]['type'] = $fieldData['field_type'];

                $dataTableStructure[$fieldData['field_key']]['size'] = (int)$fieldData['maximum_length'];

                if ($fieldData['default_value'] !== '') {
                    $defaultValue = $db->escape_string($fieldData['default_value']);
                } else {
                    $defaultValue = '';
                }
            }

            switch ($fieldData['default_type']) {
                case FieldDefaultTypes::AsDefined:
                    if ($fieldData['default_value'] !== '' && isset($defaultValue)) {
                        $dataTableStructure[$fieldData['field_key']]['default'] = $defaultValue;
                    }
                    break;
                case FieldDefaultTypes::IsNull:
                    unset($dataTableStructure[$fieldData['field_key']]['default']);

                    $dataTableStructure[$fieldData['field_key']]['null'] = true;
                    break;
                case FieldDefaultTypes::CurrentTimestamp:
                    unset($dataTableStructure[$fieldData['field_key']]['default']);

                    $dataTableStructure[$fieldData['field_key']]['default'] = 'TIMESTAMP';
                    break;
                case FieldDefaultTypes::UUID:
                    unset($dataTableStructure[$fieldData['field_key']]['default']);

                    $dataTableStructure[$fieldData['field_key']]['default'] = 'UUID';
                    break;
            }

            if (!empty($fieldData['is_required'])) {
                global $mybb;

                if (fieldTypeSupportsFullText($fieldData['field_type']) &&
                    $mybb->settings['searchtype'] === 'fulltext') {
                    isset($dataTableStructure['full_keys']) || $dataTableStructure['full_keys'] = [];

                    $dataTableStructure['full_keys'][$fieldData['field_key']] = $fieldData['field_key'];
                } else {
                    isset($dataTableStructure['keys']) || $dataTableStructure['keys'] = [];

                    $dataTableStructure['keys'][$fieldData['field_key']] = $fieldData['field_key'];
                }
                // todo: add key for uid & approved
            }
        }
    }

    return hooksRun('admin_data_table_structure', $dataTableStructure);
}

function postParser(): postParser
{
    global $parser;

    if (!($parser instanceof postParser)) {
        require_once MYBB_ROOT . 'inc/class_parser.php';

        $parser = new Postparser();
    }

    return $parser;
}

function showcaseGetObject(int $selectedShowcaseID): Showcase
{
    $showcaseObjects = showcaseGet(
        queryFields: array_keys(TABLES_DATA['myshowcase_config'])
    );

    foreach ($showcaseObjects as $showcaseID => $showcaseData) {
        if ($selectedShowcaseID === $showcaseID) {
            $showcaseSlug = $showcaseData['showcase_slug'];
        }
    }

    return showcaseGetObjectBySlug($showcaseSlug);
}

function showcaseGetObjectBySlug(string $showcaseSlug): Showcase
{
    require_once ROOT . '/System/Showcase.php';

    static $showcaseObjects = [];

    if (!isset($showcaseObjects[$showcaseSlug])) {
        $showcaseObjects[$showcaseSlug] = new Showcase($showcaseSlug);
    }

    return $showcaseObjects[$showcaseSlug];
}

function renderGetObject(Showcase $showcaseObject): Render
{
    require_once ROOT . '/System/Render.php';

    static $renderObjects = [];

    if (!isset($renderObjects[$showcaseObject->showcase_id])) {
        $renderObjects[$showcaseObject->showcase_id] = new Render($showcaseObject);
    }

    return $renderObjects[$showcaseObject->showcase_id];
}

function dataHandlerGetObject(Showcase $showcaseObject, string $method = DATA_HANDLER_METHOD_INSERT): DataHandler
{
    require_once MYBB_ROOT . 'inc/datahandler.php';
    require_once ROOT . '/System/DataHandler.php';

    static $dataHandlerObjects = [];

    if (!isset($dataHandlerObjects[$showcaseObject->showcase_id])) {
        $dataHandlerObjects[$showcaseObject->showcase_id] = new DataHandler($showcaseObject, $method);
    }

    return $dataHandlerObjects[$showcaseObject->showcase_id];
}

function outputGetObject(Showcase $showcaseObject, Render $renderObject): Output
{
    require_once ROOT . '/System/Output.php';

    static $outputObjects = [];

    if (!isset($outputObjects[$showcaseObject->showcase_id])) {
        $outputObjects[$showcaseObject->showcase_id] = new Output($showcaseObject, $renderObject);
    }

    return $outputObjects[$showcaseObject->showcase_id];
}

function formatTypes()
{
    return [
        FormatTypes::noFormat => '',
        FormatTypes::numberFormat => 'my_number_format(#,###)',
        FormatTypes::numberFormat1 => 'my_number_format(#,###.#)',
        FormatTypes::numberFormat2 => 'my_number_format(#,###.##)',
        FormatTypes::htmlSpecialCharactersUni => 'htmlspecialchars_uni',
        FormatTypes::stripTags => 'strip_tags',
    ];
}

function formatField(int $formatType, string &$fieldValue)
{
    $fieldValue = match ($formatType) {
        FormatTypes::numberFormat => $value = my_number_format((int)$value),
        FormatTypes::numberFormat1 => number_format((float)$fieldValue, 1),
        FormatTypes::numberFormat2 => number_format((float)$fieldValue, 2),
        FormatTypes::htmlSpecialCharactersUni => $fieldValue = htmlspecialchars_uni($fieldValue),
        FormatTypes::stripTags => $fieldValue = strip_tags($fieldValue),
        default => $fieldValue
    };
}

function fieldTypesGet(): array
{
    return [
        FieldTypes::TinyInteger => FieldTypes::TinyInteger,
        FieldTypes::SmallInteger => FieldTypes::SmallInteger,
        FieldTypes::MediumInteger => FieldTypes::MediumInteger,
        FieldTypes::BigInteger => FieldTypes::BigInteger,
        FieldTypes::Integer => FieldTypes::Integer,

        FieldTypes::Decimal => FieldTypes::Decimal,
        FieldTypes::Float => FieldTypes::Float,
        FieldTypes::Double => FieldTypes::Double,

        FieldTypes::Char => FieldTypes::Char,
        FieldTypes::VarChar => FieldTypes::VarChar,

        FieldTypes::TinyText => FieldTypes::TinyText,
        FieldTypes::Text => FieldTypes::Text,
        FieldTypes::MediumText => FieldTypes::MediumText,

        FieldTypes::Date => FieldTypes::Date,
        FieldTypes::Time => FieldTypes::Time,
        FieldTypes::DateTime => FieldTypes::DateTime,
        FieldTypes::TimeStamp => FieldTypes::TimeStamp,

        FieldTypes::Binary => FieldTypes::Binary,
        FieldTypes::VarBinary => FieldTypes::VarBinary,
    ];
}

function fieldTypeMatchInt(string $fieldType): bool
{
    return in_array($fieldType, [
        FieldTypes::TinyInteger => FieldTypes::TinyInteger,
        FieldTypes::SmallInteger => FieldTypes::SmallInteger,
        FieldTypes::MediumInteger => FieldTypes::MediumInteger,
        FieldTypes::BigInteger => FieldTypes::BigInteger,
        FieldTypes::Integer => FieldTypes::Integer,
    ], true);
}

function fieldTypeMatchFloat(string $fieldType): bool
{
    return in_array($fieldType, [
        FieldTypes::Decimal => FieldTypes::Decimal,
        FieldTypes::Float => FieldTypes::Float,
        FieldTypes::Double => FieldTypes::Double,
    ], true);
}

function fieldTypeMatchChar(string $fieldType): bool
{
    return in_array($fieldType, [
        FieldTypes::Char => FieldTypes::Char,
        FieldTypes::VarChar => FieldTypes::VarChar,
    ], true);
}

function fieldTypeMatchText(string $fieldType): bool
{
    return in_array($fieldType, [
        FieldTypes::TinyText => FieldTypes::TinyText,
        FieldTypes::Text => FieldTypes::Text,
        FieldTypes::MediumText => FieldTypes::MediumText,
    ], true);
}

function fieldTypeMatchDateTime(string $fieldType): bool
{
    return in_array($fieldType, [
        FieldTypes::Date => FieldTypes::Date,
        FieldTypes::Time => FieldTypes::Time,
        FieldTypes::DateTime => FieldTypes::DateTime,
        FieldTypes::TimeStamp => FieldTypes::TimeStamp,
    ], true);
}

function fieldTypeMatchBinary(string $fieldType): bool
{
    return in_array($fieldType, [
        FieldTypes::Binary => FieldTypes::Binary,
        FieldTypes::VarBinary => FieldTypes::VarBinary,
    ], true);
}

function fieldTypeSupportsFullText(string $fieldType): bool
{
    return in_array($fieldType, [
        FieldTypes::Char => FieldTypes::Char,
        FieldTypes::VarChar => FieldTypes::VarChar,

        FieldTypes::Text => FieldTypes::Text,
    ], true);
}

function fieldHtmlTypes(): array
{
    return [
        FieldHtmlTypes::Text => FieldHtmlTypes::Text,
        FieldHtmlTypes::TextArea => FieldHtmlTypes::TextArea,
        FieldHtmlTypes::Radio => FieldHtmlTypes::Radio,
        FieldHtmlTypes::CheckBox => FieldHtmlTypes::CheckBox,
        FieldHtmlTypes::Url => FieldHtmlTypes::Url,
        FieldHtmlTypes::Date => FieldHtmlTypes::Date,
        FieldHtmlTypes::SelectSingle => FieldHtmlTypes::SelectSingle,
        FieldHtmlTypes::SelectMultiple => FieldHtmlTypes::SelectMultiple,
    ];
}

function fieldDefaultTypes(): array
{
    global $lang;

    loadLanguage();

    return [
        FieldDefaultTypes::AsDefined => $lang->myShowcaseAdminFieldsNewFormDefaultTypeAsDefined,
        FieldDefaultTypes::IsNull => $lang->myShowcaseAdminFieldsNewFormDefaultTypeNull,
        FieldDefaultTypes::CurrentTimestamp => $lang->myShowcaseAdminFieldsNewFormDefaultTypeTimeStamp,
        FieldDefaultTypes::UUID => $lang->myShowcaseAdminFieldsNewFormDefaultTypeUUID,
    ];
}