<?php
/**
 * MyShowcase Plugin for MyBB - Main Plugin Controls
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\plugins\myshowcase\plugin.php
 *
 */

declare(strict_types=1);

namespace MyShowcase\Core;

use DirectoryIterator;

use inc\plugins\myshowcase\Showcase;

use const MyShowcase\Admin\FIELD_TYPE_BIGINT;
use const MyShowcase\Admin\FIELD_TYPE_INT;
use const MyShowcase\Admin\FIELD_TYPE_TEXT;
use const MyShowcase\Admin\FIELD_TYPE_TIMESTAMP;
use const MyShowcase\Admin\FIELD_TYPE_VARCHAR;
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

const CACHE_TYPE_REPORTS = 'reports';

const CACHE_TYPE_FIELD_DATA = 'field_data'; // todo, add cache update method

const MODERATOR_TYPE_USER = 0;

const MODERATOR_TYPE_GROUP = 1;

const URL = 'index.php?module=myshowcase-summary';

const TABLES_DATA = [
    'myshowcase_attachments' => [
        'aid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 1
        ],
        'gid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
        ],
        'posthash' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => '',
        ],
        'uid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
        ],
        'filename' => [
            'type' => 'VARCHAR',
            'size' => 120,
            'default' => ''
        ],
        'filetype' => [
            'type' => 'VARCHAR',
            'size' => 120,
            'default' => ''
        ],
        'filesize' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'attachname' => [
            'type' => 'VARCHAR',
            'size' => 120,
            'default' => ''
        ],
        'downloads' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'dateuploaded' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'thumbnail' => [
            'type' => 'VARCHAR',
            'size' => 120,
            'default' => ''
        ],
        'visible' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
    ],
    'myshowcase_comments' => [
        'cid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 1
        ],
        'gid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'uid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'ipaddress' => [
            'type' => 'VARCHAR',
            'size' => 30,
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
        'unique_key' => ['cid_gid' => 'cid,gid']
    ],
    'myshowcase_config' => [
        'id' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'name' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => '',
            'unique_key' => true
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
        'fieldsetid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 1
        ],
        'imgfolder' => [
            'type' => 'VARCHAR',
            'size' => 255,
            'default' => ''
        ],
        'defaultimage' => [
            'type' => 'VARCHAR',
            'null' => true,
            'size' => 50,
        ],
        'watermarkimage' => [
            'type' => 'VARCHAR',
            'null' => true,
            'size' => 50,
        ],
        'watermarkloc' => [
            'type' => 'VARCHAR',
            'size' => 12,
            'default' => 'lower-right'
        ],
        'use_attach' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'f2gpath' => [
            'type' => 'VARCHAR',
            'size' => 255,
            'default' => ''
        ],
        'enabled' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'allowsmilies' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'allowbbcode' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'allowhtml' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'prunetime' => [
            'type' => 'VARCHAR',
            'size' => 10,
            'default' => 0
        ],
        'modnewedit' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'othermaxlength' => [
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
        'comment_dispinit' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 5
        ],
        'disp_attachcols' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'disp_empty' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
        ],
        'link_in_postbit' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'portal_random' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        // todo, trigger notification (user, group), pm, or alert
        // todo, DVZ Stream
        // todo, latest entries helper
    ],
    'myshowcase_fieldsets' => [
        'setid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'setname' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => ''
        ],
    ],
    'myshowcase_permissions' => [
        'pid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'gid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'canview' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canadd' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canedit' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'cancomment' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canattach' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canviewcomment' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canviewattach' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'candelowncomment' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'candelauthcomment' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'cansearch' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canwatermark' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'attachlimit' => [
            'type' => 'INT',
            'default' => 0
        ],
    ],
    'myshowcase_moderators' => [
        'mid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'uid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'isgroup' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canmodapprove' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canmodedit' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canmoddelete' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'canmoddelcomment' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'unique_key' => ['id_uid_isgroup' => 'id,uid,isgroup']
    ],
    'myshowcase_reports' => [
        'rid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'gid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'reporteruid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'authoruid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'status' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'reason' => [
            'type' => 'VARCHAR',
            'size' => 250,
            'default' => ''
        ],
        'dateline' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
    ],
    'myshowcase_fields' => [
        'fid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'setid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'name' => [
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
            'default' => 'varchar'
        ],
        'min_length' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 0
        ],
        'max_length' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 0
        ],
        'requiredField' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ], // todo, rename from require to requiredField
        'parse' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'field_order' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'list_table_order' => [
            'type' => 'SMALLINT',
            'default' => -1
        ],
        'searchable' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        'format' => [
            'type' => 'VARCHAR',
            'size' => 10,
            'default' => 'no'
        ],
        'unique_key' => ['setid_fid' => 'setid,fid']
        // todo, add view permission
        // todo, add edit permission
        // todo, validation regex for text fields
    ],
    'myshowcase_field_data' => [
        'fieldDataID' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'setid' => [
            'type' => 'INT',
            'unsigned' => true,
        ],
        'fid' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'name' => [
            'type' => 'VARCHAR',
            'size' => 15,
            'default' => '',
            //'unique_key' => true
        ],
        'valueid' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 0
        ],
        'value' => [
            'type' => 'VARCHAR',
            'size' => 15,
            'default' => ''
        ],
        'disporder' => [
            'type' => 'SMALLINT',
            'unsigned' => true,
            'default' => 0
        ],
        'unique_key' => ['setid_fid_valueid' => 'setid,fid,valueid']
    ],
];

const FIELDS_DATA = [
];

const DATA_TABLE_STRUCTURE = [
    'myshowcase_data' => [
        'gid' => [
            'type' => 'INT',
            'unsigned' => true,
            'auto_increment' => true,
            'primary_key' => true
        ],
        'uid' => [
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
        'submit_date' => [
            'type' => 'VARCHAR',
            'size' => 20,
            'default' => ''
        ],
        'dateline' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
        ],
        'createdate' => [
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
        'posthash' => [
            'type' => 'VARCHAR',
            'size' => 32,
            'default' => ''
        ],
    ],
];

const FORMAT_TYPES = [
    //'no' => 'None',
    //'decimal0' => '#,###',
    //'decimal1' => '#,###.#',
    //'decimal2' => '#,###.##',
    //0 => 'htmlspecialchars_uni',
    0 => '',
    1 => 'number_format',
    2 => '#,###.#',
    3 => '#,###.##',
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

    return $plugins->run_hooks('myshowcase_' . $hookName, $hookArguments);
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

function getTemplateName(string $templateName = ''): string
{
    $templatePrefix = '';

    if ($templateName) {
        $templatePrefix = '_';
    }

    return "myshowcase{$templatePrefix}{$templateName}";
}

function getTemplate(string $templateName = '', bool $enableHTMLComments = true): string
{
    global $templates;

    if (DEBUG) {
        $filePath = ROOT . "/templates/{$templateName}.html";

        $templateContents = file_get_contents($filePath);

        $templates->cache[getTemplateName($templateName)] = $templateContents;
    } elseif (my_strpos($templateName, '/') !== false) {
        $templateName = substr($templateName, my_strpos($templateName, '/') + 1);
    }

    return $templates->render(getTemplateName($templateName), true, $enableHTMLComments);
}

//set default permissions for all groups in all myshowcases
//if you edit or reorder these, you need to also edit
//the summary.php file (starting line 156) so the fields match this order
function showcasePermissions(): array
{
    static $defaultPermissions;

    if ($defaultPermissions === null) {
        $defaultPermissions = [
            'canadd' => 0,
            'canedit' => 0,
            'canattach' => 0,
            'canview' => 1,
            'canviewcomment' => 1,
            'canviewattach' => 1,
            'cancomment' => 0,
            'candelowncomment' => 0,
            'candelauthcomment' => 0,
            'cansearch' => 1,
            'canwatermark' => 0,
            'attachlimit' => 0,
        ];
    }

    return $defaultPermissions;
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
                [],
                array_keys(TABLES_DATA['myshowcase_config'])
            );

            foreach ($showcaseObjects as $showcaseID => $showcaseData) {
                $cacheData[$showcaseID] = [
                    'id' => $showcaseID,
                    'name' => (string)$showcaseData['name'],
                    'description' => (string)$showcaseData['description'],
                    'mainfile' => (string)$showcaseData['mainfile'],
                    'fieldsetid' => (int)$showcaseData['fieldsetid'],
                    'imgfolder' => (string)$showcaseData['imgfolder'],
                    'defaultimage' => (string)$showcaseData['defaultimage'],
                    'watermarkimage' => (string)$showcaseData['watermarkimage'],
                    'watermarkloc' => (string)$showcaseData['watermarkloc'],
                    'use_attach' => (bool)$showcaseData['use_attach'],
                    'f2gpath' => (string)$showcaseData['f2gpath'],
                    'enabled' => (bool)$showcaseData['enabled'],
                    'allowsmilies' => (bool)$showcaseData['allowsmilies'],
                    'allowbbcode' => (bool)$showcaseData['allowbbcode'],
                    'allowhtml' => (bool)$showcaseData['allowhtml'],
                    'prunetime' => (int)$showcaseData['prunetime'],
                    'modnewedit' => (bool)$showcaseData['modnewedit'],
                    'othermaxlength' => (int)$showcaseData['othermaxlength'],
                    'allow_attachments' => (bool)$showcaseData['allow_attachments'],
                    'allow_comments' => (bool)$showcaseData['allow_comments'],
                    'thumb_width' => (int)$showcaseData['thumb_width'],
                    'thumb_height' => (int)$showcaseData['thumb_height'],
                    'comment_length' => (int)$showcaseData['comment_length'],
                    'comment_dispinit' => (int)$showcaseData['comment_dispinit'],
                    'disp_attachcols' => (int)$showcaseData['disp_attachcols'],
                    'disp_empty' => (bool)$showcaseData['disp_empty'],
                    'link_in_postbit' => (bool)$showcaseData['link_in_postbit'],
                    'portal_random' => (bool)$showcaseData['portal_random'],
                ];
            }

            break;
        case CACHE_TYPE_PERMISSIONS:
            $permissionsObjects = permissionsGet(
                [],
                array_keys(TABLES_DATA['myshowcase_permissions'])
            );

            foreach ($permissionsObjects as $permissionID => $permissionData) {
                $cacheData[(int)$permissionData['id']][(int)$permissionData['gid']] = $permissionData;
            }

            break;
        case CACHE_TYPE_FIELD_SETS:
            $fieldsetObjects = fieldsetGet(
                [],
                ['setid', 'setname']
            );

            foreach ($fieldsetObjects as $fieldsetID => $fieldsetData) {
                $cacheData[$fieldsetID] = [
                    'setid' => (int)$fieldsetData['setid'],
                    'setname' => (string)$fieldsetData['setname'],
                ];
            }

            break;
        case CACHE_TYPE_FIELDS:
            $queryFields = TABLES_DATA['myshowcase_fields'];

            unset($queryFields['unique_key']);

            $fieldObjects = fieldsGet(
                [],
                array_keys($queryFields),
                ['order_by' => 'setid, field_order']
            );

            foreach ($fieldObjects as $fieldID => $fieldData) {
                $cacheData[(int)$fieldData['setid']][$fieldID] = [
                    'fid' => (int)$fieldData['fid'],
                    'setid' => (int)$fieldData['setid'],
                    'name' => (string)$fieldData['name'],
                    'html_type' => (string)$fieldData['html_type'],
                    'enabled' => (bool)$fieldData['enabled'],
                    'field_type' => (string)$fieldData['field_type'],
                    'min_length' => (int)$fieldData['min_length'],
                    'max_length' => (int)$fieldData['max_length'],
                    'requiredField' => (bool)$fieldData['requiredField'],
                    'parse' => (bool)$fieldData['parse'],
                    'field_order' => (int)$fieldData['field_order'],
                    'list_table_order' => (int)$fieldData['list_table_order'],
                    'searchable' => (bool)$fieldData['searchable'],
                    'format' => (string)$fieldData['format'],
                ];
            }

            break;
        case CACHE_TYPE_FIELD_DATA;
            $fieldDataObjects = fieldDataGet(
                [],
                ['setid', 'fid', 'valueid', 'value', 'valueid', 'disporder'],
                ['order_by' => 'setid, fid, disporder']
            );

            foreach ($fieldDataObjects as $fieldDataID => $fieldData) {
                $cacheData[(int)$fieldData['setid']][$fieldDataID][(int)$fieldData['valueid']] = $fieldData;
            }

            break;
        case CACHE_TYPE_MODERATORS;
            $moderatorObjects = moderatorGet(
                [],
                ['mid', 'id', 'uid', 'isgroup', 'canmodapprove', 'canmodedit', 'canmoddelete', 'canmoddelcomment']
            );

            foreach ($moderatorObjects as $moderatorID => $moderatorData) {
                $cacheData[(int)$moderatorData['id']][$moderatorID] = $moderatorData;
            }

            break;
        case CACHE_TYPE_REPORTS;
            $reportObjects = reportGet(
                ["status='0'"],
                ['rid', 'id', 'gid', 'uid', 'reporter', 'reason', 'dateline']
            );

            foreach ($reportObjects as $reportID => $reportData) {
                $cacheData[(int)$reportData['id']][(int)$reportData['gid']][$reportID] = $reportData;
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
        "id='{$showcaseID}'"
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
        implode(',', array_merge(['id'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $showcaseObjects = [];

    while ($showcaseData = $db->fetch_array($query)) {
        $showcaseObjects[(int)$showcaseData['id']] = $showcaseData;
    }

    return $showcaseObjects;
}

function showcaseDataTableExists(int $showcaseID): bool
{
    global $db;

    return $db->table_exists('myshowcase_data' . $showcaseID);
}

function showcaseDataTableFieldExists(int $showcaseID, string $fieldName): bool
{
    global $db;

    return $db->field_exists($fieldName, 'myshowcase_data' . $showcaseID);
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

function showcaseDataInsert(int $showcaseID, array $fieldData): int
{
    global $db;

    $db->insert_query('myshowcase_data' . $showcaseID, $fieldData);

    return (int)$db->insert_id();
}

function showcaseDataUpdate(int $showcaseID, array $whereClauses = [], array $fieldData = []): bool
{
    global $db;

    $db->update_query(
        'myshowcase_data' . $showcaseID,
        $fieldData,
        implode(' AND ', $whereClauses)
    );

    return true;
}

function showcaseDataDelete(int $showcaseID, array $whereClauses = []): bool
{
    global $db;

    $db->delete_query('myshowcase_data' . $showcaseID, implode(' AND ', $whereClauses));

    return true;
}

function showcaseDataGet(int $showcaseID, array $whereClauses, array $queryFields = [], array $queryOptions = []): array
{
    global $db;

    $query = $db->simple_select(
        'myshowcase_data' . $showcaseID,
        implode(',', array_merge(['gid'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    $fieldData = [];

    while ($fieldValueData = $db->fetch_array($query)) {
        $fieldData[(int)$fieldValueData['gid']] = $fieldValueData;

        foreach (\MyShowcase\Core\DATA_TABLE_STRUCTURE['myshowcase_data'] as $defaultFieldKey => $defaultFieldData) {
            if (isset($fieldData[(int)$fieldValueData['gid']][$defaultFieldKey])) {
                //$fieldData[(int)$fieldValueData['gid']][$defaultFieldKey] = 123;
            }
        }
    }

    return $fieldData;
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
        implode(',', array_merge(['pid'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $permissionData = [];

    while ($permission = $db->fetch_array($query)) {
        $permissionData[(int)$permission['pid']] = $permission;
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
        implode(',', array_merge(['mid'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $moderatorData = [];

    while ($moderator = $db->fetch_array($query)) {
        $moderatorData[(int)$moderator['mid']] = $moderator;
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
        "setid='{$fieldsetID}'"
    );

    return true;
}

function fieldsetGet(array $whereClauses = [], array $queryFields = [], array $queryOptions = []): array
{
    global $db;

    $query = $db->simple_select(
        'myshowcase_fieldsets',
        implode(',', array_merge(['setid'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $fieldsetData = [];

    while ($fieldset = $db->fetch_array($query)) {
        $fieldsetData[(int)$fieldset['setid']] = $fieldset;
    }

    return $fieldsetData;
}

function fieldsetDelete(array $whereClauses = []): bool
{
    global $db;

    $db->delete_query('myshowcase_fieldsets', implode(' AND ', $whereClauses));

    return true;
}

function fieldsInsert(array $fieldData): int
{
    global $db;

    $db->insert_query('myshowcase_fields', $fieldData);

    return (int)$db->insert_id();
}

function fieldsUpdate(array $whereClauses, array $fieldData): bool
{
    global $db;

    $db->update_query(
        'myshowcase_fields',
        $fieldData,
        implode(' AND ', $whereClauses)
    );

    return true;
}

function fieldsGet(array $whereClauses = [], array $queryFields = [], array $queryOptions = []): array
{
    global $db;

    $query = $db->simple_select(
        'myshowcase_fields',
        implode(',', array_merge(['fid'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $fieldData = [];

    while ($field = $db->fetch_array($query)) {
        $fieldData[(int)$field['fid']] = $field;
    }

    return $fieldData;
}

function fieldsDelete(array $whereClauses = []): bool
{
    global $db;

    $db->delete_query('myshowcase_fields', implode(' AND ', $whereClauses));

    return true;
}

function fieldDataInsert(array $fieldData): int
{
    global $db;

    $db->insert_query('myshowcase_field_data', $fieldData);

    return (int)$db->insert_id();
}

function fieldDataUpdate(array $whereClauses, array $fieldData): bool
{
    global $db;

    $db->update_query(
        'myshowcase_field_data',
        $fieldData,
        implode(' AND ', $whereClauses)
    );

    return true;
}

function fieldDataGet(array $whereClauses = [], array $queryFields = [], array $queryOptions = []): array
{
    global $db;

    $query = $db->simple_select(
        'myshowcase_field_data',
        implode(',', array_merge(['fieldDataID'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $fieldData = [];

    while ($field = $db->fetch_array($query)) {
        $fieldData[(int)$field['fieldDataID']] = $field;
    }

    return $fieldData;
}

function fieldDataDelete(array $whereClauses = []): bool
{
    global $db;

    $db->delete_query('myshowcase_field_data', implode(' AND ', $whereClauses));

    return true;
}

function attachmentInsert(array $attachmentData): int
{
    global $db;

    $db->insert_query('myshowcase_attachments', $attachmentData);

    return (int)$db->insert_id();
}

function attachmentUpdate(array $whereClauses, array $attachmentData): bool
{
    global $db;

    $db->update_query(
        'myshowcase_attachments',
        $attachmentData,
        implode(' AND ', $whereClauses)
    );

    return true;
}

function attachmentGet(array $whereClauses = [], array $queryFields = [], array $queryOptions = []): array
{
    global $db;

    $query = $db->simple_select(
        'myshowcase_attachments',
        implode(', ', array_merge(['aid'], $queryFields)),
        implode(' AND ', $whereClauses)
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $attachmentObjects = [];

    while ($attachment = $db->fetch_array($query)) {
        $attachmentObjects[(int)$attachment['aid']] = $attachment;
    }

    return $attachmentObjects;
}

function attachmentDelete(array $whereClauses = []): bool
{
    global $db;

    $db->delete_query('myshowcase_attachments', implode(' AND ', $whereClauses));

    return true;
}

function commentInsert(array $commentData): int
{
    global $db;

    $db->insert_query('myshowcase_comments', $commentData);

    return (int)$db->insert_id();
}

function commentUpdate(array $whereClauses, array $commentData): bool
{
    global $db;

    $db->update_query(
        'myshowcase_comments',
        $commentData,
        implode(' AND ', $whereClauses)
    );

    return true;
}

function commentGet(array $whereClauses = [], array $queryFields = [], array $queryOptions = []): array
{
    global $db;

    $query = $db->simple_select(
        'myshowcase_comments',
        implode(', ', array_merge(['cid'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $commentObjects = [];

    while ($comment = $db->fetch_array($query)) {
        $commentObjects[(int)$comment['cid']] = $comment;
    }

    return $commentObjects;
}

function commentDelete(array $whereClauses = []): bool
{
    global $db;

    $db->delete_query('myshowcase_comments', implode(' AND ', $whereClauses));

    return true;
}

function reportInsert(array $reportData): int
{
    global $db;

    $db->insert_query('myshowcase_reports', $reportData);

    return (int)$db->insert_id();
}

function reportUpdate(array $whereClauses, array $reportData): bool
{
    global $db;

    $db->update_query(
        'myshowcase_reports',
        $reportData,
        implode(' AND ', $whereClauses)
    );

    return true;
}

function reportDelete(array $whereClauses = []): bool
{
    global $db;

    $db->delete_query('myshowcase_reports', implode(' AND ', $whereClauses));

    return true;
}

function reportGet(array $whereClauses = [], array $queryFields = [], array $queryOptions = []): array
{
    global $db;

    $query = $db->simple_select(
        'myshowcase_reports',
        implode(', ', array_merge(['rid'], $queryFields)),
        implode(' AND ', $whereClauses),
        $queryOptions
    );

    if (isset($queryOptions['limit']) && $queryOptions['limit'] === 1) {
        return (array)$db->fetch_array($query);
    }

    $reportObjects = [];

    while ($report = $db->fetch_array($query)) {
        $reportObjects[(int)$report['rid']] = $report;
    }

    return $reportObjects;
}

/**
 * Remove an attachment from a specific showcase
 *
 * @param int The showcase ID
 * @param string The posthash if available
 * @param int The attachment ID
 */
function attachmentRemove(
    Showcase $showcase,
    string $entryHash = '',
    int $attachmentID = 0,
    int $entryID = 0
): bool {
    $whereClauses = ["id='{$showcase->id}'", "aid='{$attachmentID}'"];

    if (!empty($entryHash)) {
        global $db;

        $whereClauses[] = "posthash='{$db->escape_string($entryHash)}'";
    } else {
        $whereClauses[] = "gid='{$entryID}'";
    }

    $attachmentData = attachmentGet($whereClauses, ['attachname', 'thumbnail', 'visible'], ['limit' => 1]);

    $attachmentData = hooksRun('remove_attachment_do_delete', $attachmentData);

    attachmentDelete(["aid='{$attachmentID}'"]);

    unlink($showcase->imgfolder . '/' . $attachmentData['attachname']);

    if (!empty($attachmentData['thumbnail'])) {
        unlink($showcase->imgfolder . '/' . $attachmentData['thumbnail']);
    }

    $dateDirectory = explode('/', $attachmentData['attachname']);

    if (!empty($dateDirectory[0]) && is_dir($showcase->imgfolder . '/' . $dateDirectory[0])) {
        rmdir($showcase->imgfolder . '/' . $dateDirectory[0]);
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
            ["uid='{$showcase_uid}'"],
            ['SUM(filesize) AS userTotalUsage'],
            ['group_by' => 'id']
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
            "filename='{$db->escape_string($attachmentData['name'])}'",
            "id='{$showcase->id}'",
            "(posthash='{$db->escape_string($entryHash)}' OR (gid='{$gid}' AND gid!='0'))"
        ],
        [],
        ['limit' => 1]
    );

    $attachmentID = (int)($existingAttachment['aid'] ?? 0);

    if ($attachmentID && !$isUpdate) {
        $returnData['error'] = $lang->error_alreadyuploaded;

        return $returnData;
    }

    // Check if the attachment directory (YYYYMM) exists, if not, create it
    $directoryMonthName = gmdate('Ym');

    if (!is_dir($showcase->imgfolder . '/' . $directoryMonthName)) {
        mkdir($showcase->imgfolder . '/' . $directoryMonthName);

        if (!is_dir($showcase->imgfolder . '/' . $directoryMonthName)) {
            $directoryMonthName = '';
        }
    }

    // If safe_mode is enabled, don't attempt to use the monthly directories as it won't work
    if (ini_get('safe_mode') || strtolower(ini_get('safe_mode')) == 'on') {
        $directoryMonthName = '';
    }

    // All seems to be good, lets move the attachment!
    $fileName = "post_{$showcase_uid}_" . TIME_NOW . '_' . md5(random_str()) . '.attach';

    $fileData = fileUpload($attachmentData, $showcase->imgfolder . '/' . $directoryMonthName, $fileName);

    // Failed to create the attachment in the monthly directory, just throw it in the main directory
    if ($fileData['error'] && $directoryMonthName) {
        $fileData = fileUpload($attachmentData, $showcase->imgfolder . '/', $fileName);
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
    if (!file_exists($showcase->imgfolder . '/' . $fileName)) {
        $returnData['error'] = $lang->error_uploadfailed . $lang->error_uploadfailed_detail . $lang->error_uploadfailed_lost;

        return $returnData;
    }

    $insertData = [
        'id' => intval($showcase->id),
        'gid' => intval($gid),
        'posthash' => $db->escape_string($entryHash),
        'uid' => intval($showcase_uid),
        'filename' => $db->escape_string($fileData['original_filename']),
        'filetype' => $db->escape_string($fileData['type']),
        'filesize' => intval($fileData['size']),
        'attachname' => $fileName,
        'downloads' => 0,
        'visible' => 1,
        'dateuploaded' => TIME_NOW
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
        $imageDimensions = getimagesize($showcase->imgfolder . '/' . $fileName);

        $fileMimeType = '';

        $filePath = $showcase->imgfolder . '/' . $fileName;

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
            unlink($showcase->imgfolder . '/' . $fileName);

            $returnData['error'] = $lang->error_uploadfailed;

            return $returnData;
        }

        //if requested and enabled, watermark the master image
        if ($showcase->userperms['canwatermark'] && $addWaterMark && file_exists($showcase->watermarkimage)) {
            //get watermark image object
            switch (strtolower(get_extension($showcase->watermarkimage))) {
                case 'gif':
                    $waterMarkImage = imagecreatefromgif($showcase->watermarkimage);
                    break;
                case 'jpg':
                case 'jpeg':
                case 'jpe':
                    $waterMarkImage = imagecreatefromjpeg($showcase->watermarkimage);
                    break;
                case 'png':
                    $waterMarkImage = imagecreatefrompng($showcase->watermarkimage);
                    break;
            }

            //check if we have an image
            if (!empty($waterMarkImage)) {
                //get watermark size
                $waterMarkImageWidth = imagesx($waterMarkImage);

                $waterMarkImageHeight = imagesy($waterMarkImage);

                //get size of base image
                $fileSize = getimagesize($showcase->imgfolder . '/' . $fileName);

                //set watermark location
                switch ($showcase->watermarkloc) {
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
                        $fileImage = imagecreatefromgif($showcase->imgfolder . '/' . $fileName);
                        break;
                    case 2:
                        $fileImage = imagecreatefromjpeg($showcase->imgfolder . '/' . $fileName);
                        break;
                    case 3:
                        $fileImage = imagecreatefrompng($showcase->imgfolder . '/' . $fileName);
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

                    $f = fopen($showcase->imgfolder . '/' . $fileName, 'w');

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
            $showcase->imgfolder . '/' . $fileName,
            $showcase->imgfolder,
            $thumbnailName,
            $showcase->thumb_height,
            $showcase->thumb_width
        );

        if ($fileThumbnail['filename']) {
            $insertData['thumbnail'] = $fileThumbnail['filename'];
        } elseif ($fileThumbnail['code'] === 4) {
            $insertData['thumbnail'] = 'SMALL';
        }
    }

    $insertData = hooksRun('upload_attachment_do_insert', $insertData);

    if ($attachmentID && $isUpdate) {
        unset($insertData['downloads']); // Keep our download count if we're updating an attachment

        attachmentUpdate(
            ["aid='{$attachmentID}'"],
            $insertData
        );
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
 * @param string The filename for the file (if blank, current is used)
 */
function fileUpload(array $fileData, string $uploadsPath, string $fileName = ''): array
{
    $returnData = [];

    if (empty($fileData['name']) || $fileData['name'] === 'none' || $fileData['size'] < 1) {
        $returnData['error'] = UPLOAD_STATUS_INVALID;

        return $returnData;
    }

    if (!$fileName) {
        $fileName = $fileData['name'];
    }

    $returnData['original_filename'] = preg_replace('#/$#', '', $fileData['name']);

    $fileName = preg_replace('#/$#', '', $fileName);

    if (!move_uploaded_file($fileData['tmp_name'], $uploadsPath . '/' . $fileName)) {
        $returnData['error'] = UPLOAD_STATUS_FAILED;

        return $returnData;
    }

    my_chmod($uploadsPath . '/' . $fileName, '0644');

    $returnData['filename'] = $fileName;

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
        //$myshowcase['portal_random'] == 1;
        if ($myshowcase['enabled'] == 1 && $myshowcase['portal_random'] == 1) {
            $showcase_list[$id]['name'] = $myshowcase['name'];
            $showcase_list[$id]['mainfile'] = $myshowcase['mainfile'];
            $showcase_list[$id]['imgfolder'] = $myshowcase['imgfolder'];
            $showcase_list[$id]['fieldsetid'] = $myshowcase['fieldsetid'];
        }
    }

    //if no showcases set to show on portal return
    if (count($showcase_list) == 0) {
        return '';
    } else {
        //get a random showcase id of those enabled
        $rand_id = array_rand($showcase_list, 1);
        $rand_showcase = $showcase_list[$rand_id];

        /* URL Definitions */
        if ($mybb->settings['seourls'] == 'yes' || ($mybb->settings['seourls'] == 'auto' && $_SERVER['SEO_SUPPORT'] == 1)) {
            $showcase_file = strtolower($rand_showcase['name']) . '-view-{gid}.html';
        } else {
            $showcase_file = $rand_showcase['mainfile'] . '?action=view&gid={gid}';
        }

        //init fixed fields
        $fields_fixed = [];
        $fields_fixed[0]['name'] = 'g.uid';
        $fields_fixed[0]['type'] = 'default';
        $fields_fixed[1]['name'] = 'dateline';
        $fields_fixed[1]['type'] = 'default';

        //get dynamic field info for the random showcase
        $field_list = [];
        $fields = cacheGet(CACHE_TYPE_FIELD_SETS);

        //get subset specific to the showcase given assigned field set
        $fields = $fields[$rand_showcase['fieldsetid']];

        //get fields that are enabled and set for list display with pad to help sorting fixed fields)
        $description_list = [];
        foreach ($fields as $id => $field) {
            if (/*$field['list_table_order'] != -1 && */ $field['enabled'] == 1) {
                $field_list[$field['list_table_order'] + 10]['name'] = $field['name'];
                $field_list[$field['list_table_order'] + 10]['type'] = $field['html_type'];
                $description_list[$field['list_table_order']] = $field['name'];
            }
        }

        //merge dynamic and fixed fields
        $fields_for_search = array_merge($fields_fixed, $field_list);

        //sort array of header fields by their list display order
        ksort($fields_for_search);

        //build where clause based on search_field terms
        $addon_join = '';
        $addon_fields = '';
        reset($fields_for_search);
        foreach ($fields_for_search as $id => $field) {
            if ($field['type'] == 'db' || $field['type'] == 'radio') {
                $addon_join .= ' LEFT JOIN ' . TABLE_PREFIX . 'myshowcase_field_data tbl_' . $field['name'] . ' ON (tbl_' . $field['name'] . '.valueid = g.' . $field['name'] . ' AND tbl_' . $field['name'] . ".name = '" . $field['name'] . "') ";
                $addon_fields .= ', tbl_' . $field['name'] . '.value AS ' . $field['name'];
            } else {
                $addon_fields .= ', ' . $field['name'];
            }
        }


        $rand_entry = 0;
        while ($rand_entry == 0) {
            $attachmentData = attachmentGet(
                ["id='{$rand_id}'", "filetype LIKE 'image%'", "visible='1'", "gid!='0'"],
                ['gid', 'attachname', 'thumbnail'],
                ['limit' => 1, 'order_by' => 'RAND()']
            );

            $rand_entry = $attachmentData['gid'];
            $rand_entry_img = $attachmentData['attachname'];
            $rand_entry_thumb = $attachmentData['thumbnail'];

            if ($rand_entry) {
                $query = $db->query(
                    '
					SELECT gid, username, g.views, comments' . $addon_fields . '
					FROM ' . TABLE_PREFIX . 'myshowcase_data' . $rand_id . ' g
					LEFT JOIN ' . TABLE_PREFIX . 'users u ON (u.uid = g.uid)
					' . $addon_join . '
					WHERE approved = 1 AND gid=' . $rand_entry . '
					LIMIT 0, 1'
                );

                if ($db->num_rows($query) == 0) {
                    $rand_entry = 0;
                }
            } else {
                return '';
            }
        }

        $alternativeBackground = 'trow2';
        $entry = $db->fetch_array($query);

        $lasteditdate = my_date($mybb->settings['dateformat'], $entry['dateline']);
        $lastedittime = my_date($mybb->settings['timeformat'], $entry['dateline']);
        $item_lastedit = $lasteditdate . '<br>' . $lastedittime;

        $item_member = build_profile_link($entry['username'], $entry['uid'], '', '', $mybb->settings['bburl'] . '/');

        $item_view_user = str_replace('{username}', $entry['username'], $lang->myshowcase_view_user);

        $entryUrl = str_replace('{gid}', $entry['gid'], $showcase_file);

        $entry['description'] = '';
        foreach ($description_list as $order => $name) {
            $entry['description'] .= $entry[$name] . ' ';
        }

        $alternativeBackground = ($alternativeBackground == 'trow1' ? 'trow2' : 'trow1');

        if ($rand_entry_thumb == 'SMALL') {
            $rand_img = $rand_showcase['imgfolder'] . '/' . $rand_entry_img;
        } else {
            $rand_img = $rand_showcase['imgfolder'] . '/' . $rand_entry_thumb;
        }

        return eval($templates->render('portal_rand_showcase'));
    }
}

function dataTableStructureGet(int $showcaseID = 0): array
{
    $dataTableStructure = DATA_TABLE_STRUCTURE['myshowcase_data'];

    if ($showcaseID && ($showcaseData = showcaseGet(["id='{$showcaseID}'"], ['fieldsetid'], ['limit' => 1]))) {
        $fieldsetID = (int)$showcaseData['fieldsetid'];

        $showcaseFields = fieldsGet(
            ["setid='{$fieldsetID}'"],
            ['name', 'field_type', 'max_length', 'requiredField']
        );

        hooksRun('admin_summary_table_create_rebuild_start');

        foreach ($showcaseFields as $fieldID => $fieldData) {
            $dataTableStructure[$fieldData['name']] = [];

            $field = &$dataTableStructure[$fieldData['name']];

            switch ($fieldData['field_type']) {
                case FIELD_TYPE_VARCHAR:
                    $field['type'] = 'VARCHAR';

                    $field['size'] = (int)$fieldData['max_length'];

                    if (empty($fieldData['requiredField'])) {
                        $field['default'] = '';
                    }
                    break;
                case FIELD_TYPE_TEXT:
                    $field['type'] = 'TEXT';

                    if (empty($fieldData['requiredField'])) {
                        $field['null'] = true;
                    }
                    break;
                case FIELD_TYPE_INT:
                case FIELD_TYPE_BIGINT:
                    $field['type'] = my_strtoupper($fieldData['field_type']);

                    $field['size'] = (int)$fieldData['max_length'];

                    if (empty($fieldData['requiredField'])) {
                        $field['default'] = 0;
                    }
                    break;
                case FIELD_TYPE_TIMESTAMP:
                    $field['type'] = 'TIMESTAMP';
                    break;
            }

            if (!empty($fieldData['requiredField'])) {
                global $mybb;

                if ($fieldData['field_type'] === FIELD_TYPE_TEXT && $mybb->settings['searchtype'] == 'fulltext') {
                    $create_index = ', FULLTEXT KEY `' . $fieldData['name'] . '` (`' . $fieldData['name'] . '`)';
                } else {
                    $create_index = ', KEY `' . $fieldData['name'] . '` (`' . $fieldData['name'] . '`)';
                }
                // todo: add key for uid & approved
            }

            unset($field);
        }
    }

    return hooksRun('admin_data_table_structure', $dataTableStructure);
}