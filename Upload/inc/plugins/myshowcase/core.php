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

namespace MyShowcase\Core;

use Postparser;
use DirectoryIterator;
use MyShowcase\System\FieldDefaultTypes;
use MyShowcase\System\FieldHtmlTypes;
use MyShowcase\System\FieldTypes;
use MyShowcase\System\FormatTypes;
use MyShowcase\System\DataHandler;
use MyShowcase\System\Output;
use MyShowcase\System\Render;
use MyShowcase\System\ModeratorPermissions;
use MyShowcase\System\UserPermissions;
use MyShowcase\System\Showcase;

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

const CACHE_TYPE_ATTACHMENT_TYPES = 'attachment_types';

const MODERATOR_TYPE_USER = 0;

const MODERATOR_TYPE_GROUP = 1;

const ATTACHMENT_UNLIMITED = -1;

const ATTACHMENT_ZERO = 0;

const ATTACHMENT_THUMBNAIL_ERROR = 3;

const ATTACHMENT_IMAGE_TOO_SMALL_FOR_THUMBNAIL = 4;

const ATTACHMENT_THUMBNAIL_SMALL = 1;

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

const ATTACHMENT_STATUS_PENDING_APPROVAL = 0;

const ATTACHMENT_STATUS_VISIBLE = 1;

const ATTACHMENT_STATUS_SOFT_DELETED = 2;

const DATA_HANDLER_METHOD_INSERT = 'insert';

const DATA_HANDLER_METHOD_UPDATE = 'update';

const GUEST_GROUP_ID = 1;

const FORM_TYPE_CHECK_BOX = 'checkBox';

const FORM_TYPE_NUMERIC_FIELD = 'numericField';

const FORM_TYPE_SELECT_FIELD = 'selectField';

const FORM_TYPE_TEXT_FIELD = 'textField';

const FORM_TYPE_YES_NO_FIELD = 'yesNoField';

const FORM_TYPE_PHP_CODE = 'phpFunction';

const URL_TYPE_MAIN = 'main';

const URL_TYPE_MAIN_UNAPPROVED = 'main_unapproved';

const URL_TYPE_MAIN_USER = 'main_user';

const URL_TYPE_SEARCH = 'search';

const URL_TYPE_ENTRY_VIEW = 'entry_view';

const URL_TYPE_ENTRY_CREATE = 'entry_create';

const URL_TYPE_ENTRY_UPDATE = 'entry_update';

const URL_TYPE_ENTRY_APPROVE = 'entry_approve';

const URL_TYPE_ENTRY_UNAPPROVE = 'entry_unapprove';

const URL_TYPE_ENTRY_SOFT_DELETE = 'entry__soft_delete';

const URL_TYPE_ENTRY_RESTORE = 'entry_restore';

const URL_TYPE_ENTRY_DELETE = 'entry_delete';

const URL_TYPE_COMMENT_VIEW = 'comment_view';

const URL_TYPE_COMMENT_CREATE = 'comment_create';

const URL_TYPE_COMMENT_UPDATE = 'comment_update';

const URL_TYPE_COMMENT_APPROVE = 'comment_approve';

const URL_TYPE_COMMENT_UNAPPROVE = 'comment_unapprove';

const URL_TYPE_COMMENT_SOFT_DELETE = 'comment_soft_delete';

const URL_TYPE_COMMENT_RESTORE = 'comment_restore';

const URL_TYPE_COMMENT_DELETE = 'comment_delete';

const URL_TYPE_ATTACHMENT_VIEW = 'attachment_view';

const URL_TYPE_THUMBNAIL_VIEW = 'thumbnail_view';

const FILTER_TYPE_NONE = 0;

const FILTER_TYPE_USER_ID = 1;

const UPLOAD_ERROR_FAILED = 1;

const WATERMARK_LOCATION_LOWER_LEFT = 1;

const WATERMARK_LOCATION_LOWER_RIGHT = 2;

const WATERMARK_LOCATION_CENTER = 3;

const WATERMARK_LOCATION_UPPER_LEFT = 4;

const WATERMARK_LOCATION_UPPER_RIGHT = 5;

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
        'attachment_hash' => [
            'type' => 'VARCHAR',
            'size' => 36,
            'default' => '',
        ],
        'entry_hash' => [
            'type' => 'VARCHAR',
            'size' => 36,
            'default' => '',
        ],
        'comment_hash' => [
            'type' => 'VARCHAR',
            'size' => 36,
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
        'mime_type' => [
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
        'thumbnail_name' => [
            'type' => 'VARCHAR',
            'size' => 120,
            'default' => ''
        ],
        'thumbnail_dimensions' => [
            'type' => 'VARCHAR',
            'size' => 20,
            'default' => ''
        ],
        'dimensions' => [
            'type' => 'VARCHAR',
            'size' => 20,
            'default' => ''
        ],
        'edit_stamp' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0
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
            'formCategory' => 'main',
            'formType' => FORM_TYPE_TEXT_FIELD,
        ],
        'description' => [
            'type' => 'VARCHAR',
            'size' => 255,
            'default' => '',
            'formCategory' => 'main',
            'formType' => FORM_TYPE_TEXT_FIELD,
        ],
        'script_name' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => '',
            'formCategory' => 'main',
            'formType' => FORM_TYPE_TEXT_FIELD,
        ],
        'field_set_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'main',
            'formType' => FORM_TYPE_SELECT_FIELD,
            'form_function' => '\MyShowcase\Core\generateFieldSetSelectArray',
        ],
        /*'relative_path' => [
            'type' => 'VARCHAR',
            'size' => 255,
            'default' => '',
            'formCategory' => 'main',
            'formType' => FORM_TYPE_TEXT_FIELD,
        ],*/
        'enabled' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'main',
            'formType' => FORM_TYPE_YES_NO_FIELD,
        ],
        'enable_friendly_urls' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'main',
            'formType' => FORM_TYPE_YES_NO_FIELD,
        ],
        'display_order' => [ // mean to be useful for building a header link, etc
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'main',
            'formType' => FORM_TYPE_NUMERIC_FIELD,
        ],
        /*'enable_dvz_stream_entries' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'main',
            'formType' => \MyShowcase\Core\FORM_TYPE_YES_NO_FIELD,
        ],
        'enable_dvz_stream_comments' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'main',
            'formType' => \MyShowcase\Core\FORM_TYPE_YES_NO_FIELD,
        ],
        'custom_theme_force' => [ // if force & no custom theme selected, force default theme
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'main',
            'formType' => \MyShowcase\Core\FORM_TYPE_YES_NO_FIELD,
        ],
        'custom_theme_id' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'main',
            'formType' => \MyShowcase\Core\FORM_TYPE_SELECT_FIELD,
        ],*/
        'custom_theme_template_prefix' => [
            'type' => 'VARCHAR',
            'size' => 50,
            'default' => '',
            'formCategory' => 'main',
            'formType' => FORM_TYPE_TEXT_FIELD,
        ],
        /*'order_default_field' => [ // dateline, username, custom fields, etc
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'main',
            'formType' => \MyShowcase\Core\FORM_TYPE_SELECT_FIELD,
            'form_function' => '\MyShowcase\Core\generateFilterFieldsSelectArray',
        ],*/
        'filter_force_field' => [ // force view entries by uid, etc
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'main',
            'formType' => FORM_TYPE_SELECT_FIELD,
            'form_function' => '\MyShowcase\Core\generateFilterFieldsSelectArray',
        ],
        /*'order_default_direction' => [ // asc, desc
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'main',
            'formType' => \MyShowcase\Core\FORM_TYPE_YES_NO_FIELD,
        ],
        'entries_grouping' => [ // inserts template between entry rows in the main page
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'main',
            'formSection' => 'entries',
            'formType' => FORM_TYPE_NUMERIC_FIELD,
        ],*/
        'entries_per_page' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'entries',
            'formType' => FORM_TYPE_NUMERIC_FIELD,
            'form_class' => 'field150',
        ],
        'parser_allow_html' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'parser',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'parser_allow_mycode' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'parser',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'parser_allow_smiles' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'parser',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'parser_allow_image_code' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'parser',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'parser_allow_video_code' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'parser',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        /*'display_moderators_list' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'display_stats' => [ // a duplicate of the index table 'showindexstats'
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'display_users_browsing_main' => [ // 'showforumviewing'
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'display_users_browsing_entries' => [ // 'showforumviewing'
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],*/
        'display_empty_fields' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        /*'display_in_posts' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'display_profile_fields' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],*/
        'display_avatars_entries' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'display_avatars_comments' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'display_stars_entries' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'display_stars_comments' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'display_group_image_entries' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'display_group_image_comments' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'display_user_details_entries' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'display_user_details_comments' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'display_signatures_entries' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'display_signatures_comments' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'moderate_entries_create' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'moderation',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'moderate_entries_update' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'moderation',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'moderate_comments_create' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'moderation',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'moderate_comments_update' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'moderation',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'moderate_attachments_upload' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'moderation',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'moderate_attachments_update' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'moderation',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'comments_allow' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'comments',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        /*
        'display_recursive_comments' => [ // 'showforumviewing'
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'comments_allow_quotes' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'comments',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],*/
        /*'comments_quick_form' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'display',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],*/
        'comments_build_editor' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'comments',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        'comments_minimum_length' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'comments',
            'formType' => FORM_TYPE_NUMERIC_FIELD,
            'form_class' => 'field150',
        ],
        'comments_maximum_length' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'comments',
            'formType' => FORM_TYPE_NUMERIC_FIELD,
            'form_class' => 'field150',
        ],
        'comments_per_page' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'comments',
            'formType' => FORM_TYPE_NUMERIC_FIELD,
            'form_class' => 'field150',
        ],
        /*'comments_direction' => [ // reverse order
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'comments',,
            'formType' => FORM_TYPE_CHECK_BOX,
        ],*/
        'attachments_allow_entries' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'attachments',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        /*'attachments_allow_comments' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'attachments',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],*/
        'attachments_uploads_path' => [
            'type' => 'VARCHAR',
            'size' => 255,
            'default' => '',
            'formCategory' => 'other',
            'formSection' => 'attachments',
            'formType' => FORM_TYPE_TEXT_FIELD,
            'form_class' => 'field150',
        ],
        /*'attachments_limit_comments' => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'attachments',
            'formType' => FORM_TYPE_NUMERIC_FIELD,
            'form_class' => 'field150',
        ],*/
        /*'attachments_enable_sharing' => [ // allow using attachments from other entries
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'attachments',
            'formType' => FORM_TYPE_NUMERIC_FIELD,
            'form_class' => 'field150',
        ],*/
        'attachments_grouping' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'attachments',
            'formType' => FORM_TYPE_NUMERIC_FIELD,
            'form_class' => 'field150',
        ],
        'attachments_main_render_first' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'attachments',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],
        /*'attachments_main_render_default_image' => [
            'type' => 'VARCHAR',
            'null' => true,
            'size' => 50,
            'default' => '',
            'formCategory' => 'other',
            'formSection' => 'attachments',
            'formType' => FORM_TYPE_TEXT_FIELD,
            'form_class' => 'field150',
        ],*/
        'attachments_watermark_file' => [
            'type' => 'VARCHAR',
            'null' => true,
            'size' => 50,
            'default' => '',
            'formCategory' => 'other',
            'formSection' => 'attachments',
            'formType' => FORM_TYPE_TEXT_FIELD,
            'form_class' => 'field150',
        ],
        'attachments_watermark_location' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'attachments',
            'formType' => FORM_TYPE_SELECT_FIELD,
            'form_function' => '\MyShowcase\Core\generateWatermarkLocationsSelectArray',
        ],
        /*'attachments_portal_build_widget' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'attachments',
            'formType' => FORM_TYPE_NUMERIC_FIELD,
            'form_class' => 'field150',
        ],
        'attachments_parse_in_content' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'attachments',
            'formType' => FORM_TYPE_CHECK_BOX,
        ],*/
        'attachments_thumbnails_width' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'attachments',
            'formType' => FORM_TYPE_NUMERIC_FIELD,
            'form_class' => 'field150',
        ],
        'attachments_thumbnails_height' => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'formCategory' => 'other',
            'formSection' => 'attachments',
            'formType' => FORM_TYPE_NUMERIC_FIELD,
            'form_class' => 'field150',
        ],
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
        UserPermissions::CanView => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'draggingPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'general',
        ],
        UserPermissions::CanSearch => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'general',
        ],
        UserPermissions::CanViewEntries => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'draggingPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'entries',
        ],
        UserPermissions::CanCreateEntries => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'draggingPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'entries',
        ],
        UserPermissions::CanUpdateEntries => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'entries',
        ],
        UserPermissions::CanDeleteEntries => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'entries',
        ],
        UserPermissions::CanViewComments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'draggingPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'comments',
        ],
        UserPermissions::CanCreateComments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'draggingPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'comments',
        ],
        UserPermissions::CanUpdateComments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'comments',
        ],
        UserPermissions::CanDeleteComments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'comments',
        ],
        UserPermissions::CanViewAttachments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'draggingPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'attachments',
        ],
        UserPermissions::CanUploadAttachments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'draggingPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'attachments',
        ],
        UserPermissions::CanUpdateAttachments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'attachments',
        ],
        UserPermissions::CanDeleteAttachments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'attachments',
        ],
        UserPermissions::CanDownloadAttachments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'draggingPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'attachments',
        ],
        UserPermissions::AttachmentsUploadQuote => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'zeroUnlimited' => true,
            'formType' => FORM_TYPE_NUMERIC_FIELD,
            'formCategory' => 'attachments',
        ],
        UserPermissions::CanWaterMarkAttachments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'attachments',
        ],
        UserPermissions::AttachmentsFilesLimit => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'zeroUnlimited' => true,
            'formType' => FORM_TYPE_NUMERIC_FIELD,
            'formCategory' => 'attachments',
        ],
        UserPermissions::CanViewSoftDeletedNotice => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'moderation',
        ],
        UserPermissions::ModerateEntryCreate => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'lowest' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'moderation',
        ],
        UserPermissions::ModerateEntryUpdate => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'lowest' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'moderation',
        ],
        UserPermissions::ModerateCommentsCreate => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'lowest' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'moderation',
        ],
        UserPermissions::ModerateCommentsUpdate => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'lowest' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'moderation',
        ],
        UserPermissions::ModerateAttachmentsUpload => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'lowest' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'moderation',
        ],
        UserPermissions::ModerateAttachmentsUpdate => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
            'isPermission' => true,
            'lowest' => true,
            'formType' => FORM_TYPE_CHECK_BOX,
            'formCategory' => 'moderation',
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
        ModeratorPermissions::CanManageEntries => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        ModeratorPermissions::CanManageEntries => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        ModeratorPermissions::CanManageEntries => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0
        ],
        ModeratorPermissions::CanManageComments => [
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
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 1
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
            'size' => 200,
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
// todo, Profile Activity integration
// todo, Extra Forum Permissions integration
// todo, integrate to Forum Logo plugin
// todo, integrate with ougc Online Users List
// todo, attachment display type, thumbnail_name, full, link

const FIELDS_DATA = [
    'usergroups' => [
        'myshowcase_' . UserPermissions::CanView => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::CanSearch => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::CanViewEntries => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::CanCreateEntries => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::CanUpdateEntries => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::CanDeleteEntries => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::CanViewComments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::CanCreateComments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::CanUpdateComments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::CanDeleteComments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::CanViewAttachments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::CanUploadAttachments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::CanUpdateAttachments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::CanDeleteAttachments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::CanDownloadAttachments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::AttachmentsUploadQuote => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::CanWaterMarkAttachments => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::AttachmentsFilesLimit => [
            'type' => 'INT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::CanViewSoftDeletedNotice => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::ModerateEntryCreate => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::ModerateEntryUpdate => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::ModerateCommentsCreate => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::ModerateCommentsUpdate => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::ModerateAttachmentsUpload => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
        'myshowcase_' . UserPermissions::ModerateAttachmentsUpdate => [
            'type' => 'TINYINT',
            'unsigned' => true,
            'default' => 0,
        ],
    ],
    'attachtypes' => [
        'myshowcase_ids' => [
            'type' => 'TEXT',
            'null' => true
        ],
        'myshowcase_image_minimum_dimensions' => [
            'type' => 'VARCHAR',
            'size' => 20,
            'null' => true
        ],
        'myshowcase_image_maximum_dimensions' => [
            'type' => 'VARCHAR',
            'size' => 20,
            'null' => true
        ],
    ]
];

// todo, add field setting to order entries by (i.e: sticky)
// todo, add field setting to block entries by (i.e: closed)
// todo, add field setting to record changes by (i.e: history)
// todo, add field setting to search fields data (i.e: enable_search)
// todo, integrate Feedback plugin into entries, per showcase
// todo, integrate Custom Rates plugin into entries, per showcase
// todo, check integration with Signature Image & Signature Control
// todo, trigger notification (user, group), pm, or alert
// todo, DVZ Stream
// todo, latest entries helper
// todo, NewPoints integration, income,
// todo, add preview entry/comment input
// todo, tree comments
// todo, browsingthisthread
// todo, threadviews_countspiders
// todo, threadviews_countguests
// todo, threadviews_countthreadauthor
// todo,mycodemessagelength
// integrate to AdRem
// todo, MyAlerts integration
// todo, users see own unapproved content
// todo, Integrate to Rates, Feedback,
// todo, build Pages menu as if inside a showcase (add_breadcrum, force theme, etc)
// ougc Private Threads integration
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
            'default' => '',
            'unique_key' => true
        ],
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
            'size' => 36,
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

function hooksRun(string $hookName, array|object &$hookArguments = []): array|object
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

function getTemplateName(string $templateName = '', string $showcasePrefix = '', bool $addPrefix = true): string
{
    $templatePrefix = $showcasePrefix !== '' ? $showcasePrefix . '_' : '';

    if ($templateName && $addPrefix) {
        $templatePrefix = '_';
    }

    if ($addPrefix) {
        $templatePrefix = 'myShowcase' . $templatePrefix;
    }

    return $templatePrefix . $templateName;
}

function getTemplate(string $templateName = '', bool $enableHTMLComments = true, string $showcasePrefix = ''): string
{
    global $templates;

    if (DEBUG) {
        //$templates->get(getTemplateName($templateName));

        //$templates->get(getTemplateName($templateName, $showcasePrefix));
    }

    if (DEBUG && file_exists($filePath = ROOT . "/templates/{$templateName}.html")) {
        $templateContents = file_get_contents($filePath);

        $templates->cache[getTemplateName($templateName)] = $templateContents;
    } elseif (my_strpos($templateName, '/') !== false) {
        $templateName = substr($templateName, my_strpos($templateName, '/') + 1);
    }

    if ($showcasePrefix !== '' && isset($templates->cache[getTemplateName($templateName, $showcasePrefix)])) {
        return $templates->render(getTemplateName($templateName, $showcasePrefix), true, $enableHTMLComments);
    } elseif ($showcasePrefix) {
        return getTemplate($templateName, $enableHTMLComments);
    }

    return $templates->render(getTemplateName($templateName, $showcasePrefix), true, $enableHTMLComments);
}

function templateGetCachedName(string $templateName = '', string $showcasePrefix = '', bool $addPrefix = false): string
{
    global $templates;

    if (DEBUG) {
        //$templates->get(getTemplateName($templateName));

        //$templates->get(getTemplateName($templateName, $showcasePrefix));
    }

    if (DEBUG && file_exists($filePath = ROOT . "/templates/{$templateName}.html")) {
        $templateContents = file_get_contents($filePath);

        $templates->cache[getTemplateName($templateName)] = $templateContents;
    } elseif (my_strpos($templateName, '/') !== false) {
        $templateName = substr($templateName, my_strpos($templateName, '/') + 1);
    }

    if ($showcasePrefix !== '' && isset($templates->cache[getTemplateName($templateName, $showcasePrefix)])) {
        return getTemplateName($templateName, $showcasePrefix, $addPrefix);
    } elseif ($showcasePrefix) {
        return templateGetCachedName($templateName, addPrefix: $addPrefix);
    }

    return getTemplateName($templateName, $showcasePrefix, $addPrefix);
}

//set default permissions for all groups in all myshowcases
//if you edit or reorder these, you need to also edit
//the summary.php file (starting line 156) so the fields match this order
function showcaseDefaultPermissions(): array
{
    return [
        UserPermissions::CanCreateEntries => false,
        UserPermissions::CanUpdateEntries => false,
        UserPermissions::CanUploadAttachments => false,
        UserPermissions::CanView => true,
        UserPermissions::CanViewComments => true,
        UserPermissions::CanViewAttachments => true,
        UserPermissions::CanCreateComments => false,
        UserPermissions::CanDeleteComments => false,
        //UserPermissions::CanDeleteAuthorComments => false,
        UserPermissions::CanSearch => true,
        UserPermissions::CanWaterMarkAttachments => false,
        UserPermissions::AttachmentsFilesLimit => 0
    ];
}

function showcaseDefaultModeratorPermissions(): array
{
    return [
        ModeratorPermissions::CanManageEntries => false,
        ModeratorPermissions::CanManageEntries => false,
        ModeratorPermissions::CanManageEntries => false,
        ModeratorPermissions::CanManageComments => false
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

    $tableFields = TABLES_DATA;

    switch ($cacheKey) {
        case CACHE_TYPE_CONFIG:
            $showcaseObjects = showcaseGet(
                queryFields: array_keys($tableFields['myshowcase_config']),
                queryOptions: ['order_by' => 'display_order']
            );

            foreach ($showcaseObjects as $showcaseID => $showcaseData) {
                $cacheData[$showcaseID] = [];

                foreach ($tableFields['myshowcase_config'] as $fieldName => $fieldDefinition) {
                    if (isset($showcaseData[$fieldName])) {
                        $cacheData[$showcaseID][$fieldName] = castTableFieldValue(
                            $showcaseData[$fieldName],
                            $fieldDefinition['type']
                        );
                    }
                }
            }

            break;
        case CACHE_TYPE_PERMISSIONS:
            $permissionsObjects = permissionsGet(
                [],
                array_keys($tableFields['myshowcase_permissions'])
            );

            foreach ($permissionsObjects as $permissionID => $permissionData) {
                $showcaseID = (int)$permissionData['showcase_id'];

                $groupID = (int)$permissionData['group_id'];

                $cacheData[$showcaseID][$groupID] = [];

                foreach ($tableFields['myshowcase_permissions'] as $fieldName => $fieldDefinition) {
                    if (isset($permissionData[$fieldName])) {
                        $cacheData[$showcaseID][$groupID][$fieldName] = castTableFieldValue(
                            $permissionData[$fieldName],
                            $fieldDefinition['type']
                        );
                    }
                }
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
            $queryFields = $tableFields['myshowcase_fields'];

            unset($queryFields['unique_keys']);

            $fieldObjects = fieldsGet(
                [],
                array_keys($queryFields),
                ['order_by' => 'display_order']
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
                ['order_by' => 'display_order']
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
                    ModeratorPermissions::CanManageEntries,
                    ModeratorPermissions::CanManageEntries,
                    ModeratorPermissions::CanManageEntries,
                    ModeratorPermissions::CanManageComments
                ]
            );

            foreach ($moderatorObjects as $moderatorID => $moderatorData) {
                $cacheData[(int)$moderatorData['showcase_id']][$moderatorID] = $moderatorData;
            }

            break;
        case CACHE_TYPE_ATTACHMENT_TYPES;
            $query = $db->simple_select(
                'attachtypes',
                'atid AS attachment_type_id, name AS type_name, mimetype AS mime_type, extension AS file_extension, maxsize AS maximum_size, icon AS type_icon, forcedownload AS force_download, groups AS allowed_groups, myshowcase_ids, myshowcase_image_minimum_dimensions, myshowcase_image_maximum_dimensions',
                "myshowcase_ids!=''"
            );

            while ($attachmentTypeData = $db->fetch_array($query)) {
                $attachmentTypeID = (int)$attachmentTypeData['attachment_type_id'];

                foreach (explode(',', $attachmentTypeData['myshowcase_ids']) as $showcaseID) {
                    $showcaseID = (int)$showcaseID;

                    list($minimumWith, $minimumHeight) = array_pad(
                        array_map(
                            'intval',
                            explode('x', $attachmentTypeData['myshowcase_image_minimum_dimensions'] ?? '')
                        ),
                        2,
                        0
                    );

                    if ($minimumWith < 1 || $minimumHeight < 1) {
                        $minimumWith = $minimumHeight = 0;
                    }

                    list($maximumWidth, $maximumHeight) = array_pad(
                        array_map(
                            'intval',
                            explode('x', $attachmentTypeData['myshowcase_image_maximum_dimensions'] ?? '')
                        ),
                        2,
                        0
                    );

                    if ($maximumWidth < 1 || $maximumHeight < 1) {
                        $maximumWidth = $maximumHeight = 0;
                    }

                    $cacheData[$showcaseID][$attachmentTypeID] = [
                        'type_name' => $attachmentTypeData['type_name'],
                        'mime_type' => my_strtolower($attachmentTypeData['mime_type']),
                        'file_extension' => my_strtolower($attachmentTypeData['file_extension']),
                        'maximum_size' => (int)$attachmentTypeData['maximum_size'],
                        'type_icon' => $attachmentTypeData['type_icon'],
                        'force_download' => (int)$attachmentTypeData['force_download'],
                        'allowed_groups' => $attachmentTypeData['allowed_groups'],
                        'image_minimum_dimensions_width' => $minimumWith,
                        'image_minimum_dimensions_height' => $minimumHeight,
                        'image_maximum_dimensions_width' => $maximumWidth,
                        'image_maximum_dimensions_height' => $maximumHeight,
                    ];
                }
            }

            break;
    }

    $cache->update("myshowcase_{$cacheKey}", $cacheData);

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

function showcaseInsert(array $showcaseData, bool $isUpdate, int $showcaseID = 0): int
{
    global $db;

    $tableFields = TABLES_DATA['myshowcase_config'];

    $insertData = [];

    foreach ($tableFields as $fieldName => $fieldDefinition) {
        if (isset($showcaseData[$fieldName])) {
            $insertData[$fieldName] = sanitizeTableFieldValue($showcaseData[$fieldName], $fieldDefinition['type']);
        }
    }

    if ($isUpdate) {
        $db->update_query('myshowcase_config', $insertData, "showcase_id='{$showcaseID}'");
    } else {
        $showcaseID = (int)$db->insert_query('myshowcase_config', $showcaseData);
    }

    return $showcaseID;
}

function showcaseUpdate(array $showcaseData, int $showcaseID): int
{
    return showcaseInsert($showcaseData, true, $showcaseID);
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

function permissionsInsert(array $permissionData, bool $isUpdate = false, int $permissionID = 0): int
{
    $tableFields = TABLES_DATA['myshowcase_permissions'];

    $insertData = [];

    foreach ($tableFields as $fieldName => $fieldDefinition) {
        if (isset($permissionData[$fieldName])) {
            $insertData[$fieldName] = sanitizeTableFieldValue($permissionData[$fieldName], $fieldDefinition['type']);
        }
    }

    global $db;

    if ($isUpdate) {
        $db->update_query('myshowcase_permissions', $insertData, "permission_id='{$permissionID}'");
    } else {
        $permissionID = (int)$db->insert_query('myshowcase_permissions', $insertData);
    }

    return $permissionID;
}

function permissionsUpdate(array $permissionData, int $permissionID): int
{
    return permissionsInsert($permissionData, true, $permissionID);
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

    $tableFields = TABLES_DATA['myshowcase_attachments'];

    $insertData = [];

    foreach ($tableFields as $fieldName => $fieldDefinition) {
        if (isset($attachmentData[$fieldName])) {
            $insertData[$fieldName] = sanitizeTableFieldValue($attachmentData[$fieldName], $fieldDefinition['type']);
        }
    }

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

    $attachmentData = attachmentGet($whereClauses, ['attachment_name', 'thumbnail_name', 'status'], ['limit' => 1]);

    $attachmentData = hooksRun('remove_attachment_do_delete', $attachmentData);

    attachmentDelete(["attachment_id='{$attachmentID}'"]);

    unlink($showcase->attachments_uploads_path . '/' . $attachmentData['attachment_name']);

    if (!empty($attachmentData['thumbnail_name'])) {
        unlink($showcase->attachments_uploads_path . '/' . $attachmentData['thumbnail_name']);
    }

    $dateDirectory = explode('/', $attachmentData['attachment_name']);

    if (!empty($dateDirectory[0]) && is_dir($showcase->attachments_uploads_path . '/' . $dateDirectory[0])) {
        rmdir($showcase->attachments_uploads_path . '/' . $dateDirectory[0]);
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
    bool $isUpdate = false,
    bool $watermarkImage = false
): array {
    global $db, $mybb, $lang, $cache;

    $returnData = [];

    if (isset($attachmentData['error']) && $attachmentData['error'] !== 0) {
        $returnData['error'] = $lang->myShowcaseAttachmentsUploadErrorUploadFailed . $lang->error_uploadfailed_detail;

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
        $returnData['error'] = $lang->myShowcaseAttachmentsUploadErrorUploadFailed . $lang->error_uploadfailed_php4;

        return $returnData;
    }

    $attachmentFileExtension = my_strtolower(get_extension($attachmentData['name']));

    $attachmentMimeType = my_strtolower($attachmentData['type']);

    $attachmentTypes = cacheGet(CACHE_TYPE_ATTACHMENT_TYPES)[$showcase->showcase_id] ?? [];

    $attachmentType = false;

    foreach ($attachmentTypes as $attachmentTypeID => $attachmentTypeData) {
        if ($attachmentTypeData['file_extension'] === $attachmentFileExtension &&
            $attachmentTypeData['mime_type'] === $attachmentMimeType) {
            $attachmentType = $attachmentTypeData;

            break;
        }
    }

    if ($attachmentType === false) {
        $returnData['error'] = $lang->error_attachtype;

        return $returnData;
    }

    if ($attachmentData['size'] > $attachmentType['maximum_size'] * 1024 && !empty($attachmentType['maximum_size'])) {
        $returnData['error'] = $lang->sprintf($lang->error_attachsize, $attachmentType['maximum_size']);

        return $returnData;
    }

    if ($showcase->userPermissions[UserPermissions::AttachmentsUploadQuote] > 0) {
        $totalUserUsage = attachmentGet(
            ["user_id='{$showcase->entryUserID}'"],
            ['SUM(file_size) AS total_user_usage'],
            ['group_by' => 'showcase_id']
        )['total_user_usage'] ?? 0;

        $totalUserUsage = $totalUserUsage + $attachmentData['size'];

        if ($totalUserUsage > ($showcase->userPermissions[UserPermissions::AttachmentsUploadQuote] * 1024)) {
            $returnData['error'] = $lang->sprintf(
                $lang->error_reachedattachquota,
                get_friendly_size($showcase->userPermissions[UserPermissions::AttachmentsUploadQuote] * 1024)
            );

            return $returnData;
        }
    }

    $existingAttachment = attachmentGet(
        [
            "file_name='{$db->escape_string($attachmentData['name'])}'",
            "showcase_id='{$showcase->showcase_id}'",
            "(entry_hash='{$db->escape_string($showcase->entryHash)}' OR (entry_id='{$showcase->entryID}' AND entry_id!='0'))"
        ],
        queryOptions: ['limit' => 1]
    );

    $existingAttachmentID = (int)($existingAttachment['attachment_id'] ?? 0);

    if ($existingAttachmentID && !$isUpdate) {
        $returnData['error'] = $lang->error_alreadyuploaded;

        return $returnData;
    }

    // Check if the attachment directory (YYYYMM) exists, if not, create it
    $directoryMonthName = gmdate('Ym');

    if (!is_dir($showcase->config['attachments_uploads_path'] . '/' . $directoryMonthName)) {
        mkdir($showcase->config['attachments_uploads_path'] . '/' . $directoryMonthName);

        if (!is_dir($showcase->config['attachments_uploads_path'] . '/' . $directoryMonthName)) {
            $directoryMonthName = '';
        }
    }

    // If safe_mode is enabled, don't attempt to use the monthly directories as it won't work
    if (ini_get('safe_mode') || my_strtolower(ini_get('safe_mode')) === 'on') {
        $directoryMonthName = '';
    }

    // All seems to be good, lets move the attachment!
    $timeNow = TIME_NOW;

    $attachmentHas = createUUIDv4();

    $fileName = "attachment_{$showcase->entryUserID}_{$showcase->entryID}_{$timeNow}_{$attachmentHas}.attach";

    $fileDataResult = fileUpload(
        $attachmentData,
        $showcase->config['attachments_uploads_path'] . '/' . $directoryMonthName,
        $fileName
    );

    // Failed to create the attachment in the monthly directory, just throw it in the main directory
    if ($fileDataResult['error'] && $directoryMonthName) {
        $fileDataResult = fileUpload($attachmentData, $showcase->config['attachments_uploads_path'] . '/', $fileName);
    }

    if ($directoryMonthName) {
        $fileName = $directoryMonthName . '/' . $fileName;
    }

    if ($fileDataResult['error']) {
        $returnData['error'] = $lang->myShowcaseAttachmentsUploadErrorUploadFailed . $lang->error_uploadfailed_detail;

        switch ($fileDataResult['error']) {
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
    if (!file_exists($showcase->config['attachments_uploads_path'] . '/' . $fileName)) {
        $returnData['error'] = $lang->myShowcaseAttachmentsUploadErrorUploadFailed . $lang->error_uploadfailed_detail . $lang->error_uploadfailed_lost;

        return $returnData;
    }

    $insertData = [
        'showcase_id' => $showcase->showcase_id,
        'entry_id' => $showcase->entryID,
        'attachment_hash' => createUUIDv4(),
        'entry_hash' => $showcase->entryHash,
        //'comment_hash' => $showcase->commentHash,
        'user_id' => $showcase->entryUserID,
        'file_name' => $fileName,
        'mime_type' => my_strtolower($fileDataResult['type']),
        'file_size' => $fileDataResult['size'],
        'attachment_name' => $fileDataResult['original_filename'],
        'dateline' => TIME_NOW,
        'status' => ATTACHMENT_STATUS_VISIBLE,
        /*'cdn_file' => 0,*/

    ];

    if ($isUpdate) {
        $insertData['edit_stamp'] = TIME_NOW;
    }

    if (!$isUpdate && (
            $showcase->config['moderate_attachments_upload'] ||
            $showcase->userPermissions[UserPermissions::ModerateAttachmentsUpload]
        )) {
        $insertData['status'] = ATTACHMENT_STATUS_PENDING_APPROVAL;
    } elseif ($isUpdate && (
            $showcase->config['moderate_attachments_update'] ||
            $showcase->userPermissions[UserPermissions::ModerateAttachmentsUpdate]
        )) {
        $insertData['status'] = ATTACHMENT_STATUS_PENDING_APPROVAL;
    }

    // If we're uploading an image, check the MIME type compared to the image type and attempt to generate a thumbnail
    if (in_array($attachmentFileExtension, ['gif', 'png', 'jpg', 'jpeg', 'jpe', 'webp'])) {
        $fullImageDimensions = getimagesize($showcase->config['attachments_uploads_path'] . '/' . $fileName);

        if (!is_array($fullImageDimensions)) {
            delete_uploaded_file($showcase->config['attachments_uploads_path'] . '/' . $fileName);

            $returnData['error'] = $lang->myShowcaseAttachmentsUploadErrorUploadFailed;

            return $returnData;
        }

        if (!empty($attachmentType['image_minimum_dimensions_width'])) {
            if ($fullImageDimensions[0] < $attachmentType['image_minimum_dimensions_width'] ||
                $fullImageDimensions[1] < $attachmentType['image_minimum_dimensions_height']) {
                delete_uploaded_file($showcase->config['attachments_uploads_path'] . '/' . $fileName);

                $returnData['error'] = $lang->sprintf(
                    $lang->myShowcaseAttachmentsUploadErrorMinimumDimensions,
                    htmlspecialchars_uni($insertData['attachment_name']),
                    $attachmentType['image_minimum_dimensions_width'],
                    $attachmentType['image_minimum_dimensions_height']
                );

                return $returnData;
            }
        }

        if (!empty($attachmentType['image_maximum_dimensions_width'])) {
            if ($fullImageDimensions[0] > $attachmentType['image_maximum_dimensions_width'] ||
                $fullImageDimensions[1] > $attachmentType['image_maximum_dimensions_height']) {
                delete_uploaded_file($showcase->config['attachments_uploads_path'] . '/' . $fileName);

                $returnData['error'] = $lang->sprintf(
                    $lang->myShowcaseAttachmentsUploadErrorMaximumDimensions,
                    htmlspecialchars_uni($insertData['attachment_name']),
                    $attachmentType['image_maximum_dimensions_width'],
                    $attachmentType['image_maximum_dimensions_height']
                );

                return $returnData;
            }
        }

        // Check a list of known MIME types to establish what kind of image we're uploading
        $imageType = match ($insertData['mime_type']) {
            'image/gif' => IMAGETYPE_GIF,
            'image/jpeg', 'image/x-jpg', 'image/x-jpeg', 'image/pjpeg', 'image/jpg' => IMAGETYPE_JPEG,
            'image/png', 'image/x-png' => IMAGETYPE_PNG,
            'image/bmp', 'image/x-bmp', 'image/x-windows-bmp' => IMAGETYPE_BMP,
            'image/webp' => IMAGETYPE_WEBP,
            default => 0,
        };

        // todo, https://github.com/JamesHeinrich/phpThumb
        // todo, https://github.com/jamiebicknell/Thumb

        if (function_exists('finfo_open')) {
            $file_info = finfo_open(FILEINFO_MIME);

            $attachmentMimeType = my_strtolower(
                explode(
                    ';',
                    finfo_file($file_info, $showcase->config['attachments_uploads_path'] . '/' . $fileName)
                )[0] ?? ''
            );

            finfo_close($file_info);
        } elseif (function_exists('mime_content_type')) {
            $attachmentMimeType = my_strtolower(
                mime_content_type(
                    MYBB_ROOT . $showcase->config['attachments_uploads_path'] . '/' . $fileName
                )
            );
        }

        $returnData['mime_type'] = $attachmentMimeType;

        // we check again just in case
        if ($attachmentType['mime_type'] !== $attachmentMimeType) {
            $returnData['error'] = $lang->error_attachtype;

            return $returnData;
        }

        if ($fullImageDimensions[2] !== $imageType || !$imageType) {
            delete_uploaded_file($showcase->config['attachments_uploads_path'] . '/' . $fileName);

            $returnData['error'] = $lang->myShowcaseAttachmentsUploadErrorInvalidType;

            return $returnData;
        }

        require_once MYBB_ROOT . 'inc/functions_image.php';

        if ($showcase->config['attachments_thumbnails_width'] > 0 &&
            $showcase->config['attachments_thumbnails_height'] > 0 &&
            in_array($imageType, [IMAGETYPE_GIF, IMAGETYPE_JPEG, IMAGETYPE_PNG])) {
            $thumbnailImage = generate_thumbnail(
                $showcase->config['attachments_uploads_path'] . '/' . $fileName,
                $showcase->config['attachments_uploads_path'],
                str_replace('.attach', "_thumb.{$attachmentFileExtension}", $fileName),
                $showcase->config['attachments_thumbnails_width'],
                $showcase->config['attachments_thumbnails_height']
            );

            // maybe should just ignore ?
            if ($thumbnailImage['code'] === ATTACHMENT_THUMBNAIL_ERROR) {
                delete_uploaded_file($showcase->config['attachments_uploads_path'] . '/' . $fileName);

                $returnData['error'] = $lang->myShowcaseAttachmentsUploadErrorUploadFailed;

                return $returnData;
            }

            // we only generate thumbnails for large images
            if ($thumbnailImage['code'] !== ATTACHMENT_IMAGE_TOO_SMALL_FOR_THUMBNAIL) {
                if (empty($thumbnailImage['filename'])) {
                    delete_uploaded_file($showcase->config['attachments_uploads_path'] . '/' . $fileName);

                    $returnData['error'] = $lang->ougc_fileprofilefields_errors_upload_failed_thumbnail_creation;

                    return $returnData;
                }

                $insertData['thumbnail_name'] = $returnData['thumbnail_name'] = $thumbnailImage['filename'];

                $thumbnailImageDimensions = getimagesize(
                    $showcase->config['attachments_uploads_path'] . '/' . $thumbnailImage['filename']
                );

                if (!is_array($thumbnailImageDimensions)) {
                    delete_uploaded_file($showcase->config['attachments_uploads_path'] . '/' . $fileName);

                    $returnData['error'] = $lang->myShowcaseAttachmentsUploadErrorThumbnailFailure;

                    return $returnData;
                }

                $insertData['thumbnail_dimensions'] = $returnData['thumbnail_dimensions'] = "{$thumbnailImageDimensions[0]}x{$thumbnailImageDimensions[1]}";
            }

            if ($thumbnailImage['code'] === ATTACHMENT_IMAGE_TOO_SMALL_FOR_THUMBNAIL) {
                $insertData['thumbnail_dimensions'] = $returnData['thumbnail_dimensions'] = ATTACHMENT_THUMBNAIL_SMALL;
            }
        }

        $watermarkImagePath = MYBB_ROOT . $showcase->config['attachments_watermark_file'];

        //if requested and enabled, watermark the master image
        if ($showcase->userPermissions[UserPermissions::CanWaterMarkAttachments] &&
            $watermarkImage &&
            file_exists($watermarkImagePath)) {
            //get watermark image object
            switch (strtolower(get_extension($showcase->config['attachments_watermark_file']))) {
                case 'gif':
                    $watermarkImageObject = imagecreatefromgif($watermarkImagePath);
                    break;
                case 'jpg':
                case 'jpeg':
                case 'jpe':
                    $watermarkImageObject = imagecreatefromjpeg($watermarkImagePath);
                    break;
                case 'png':
                    $watermarkImageObject = imagecreatefrompng($watermarkImagePath);
                    break;
            }

            if (!empty($watermarkImageObject)) {
                //get watermark size
                $waterMarkImageWidth = imagesx($watermarkImageObject);

                $waterMarkImageHeight = imagesy($watermarkImageObject);

                //get size of base image
                $fullImageDetails = getimagesize($showcase->config['attachments_uploads_path'] . '/' . $fileName);

                //set watermark location
                switch ($showcase->config['attachments_watermark_location']) {
                    case WATERMARK_LOCATION_LOWER_LEFT:
                        $waterMarkPositionX = 5;

                        $waterMarkPositionY = $fullImageDetails[1] - $waterMarkImageHeight - 5;
                        break;
                    case WATERMARK_LOCATION_LOWER_RIGHT:
                        $waterMarkPositionX = $fullImageDetails[0] - $waterMarkImageWidth - 5;

                        $waterMarkPositionY = $fullImageDetails[1] - $waterMarkImageHeight - 5;
                        break;
                    case WATERMARK_LOCATION_CENTER:
                        $waterMarkPositionX = $fullImageDetails[0] / 2 - $waterMarkImageWidth / 2;

                        $waterMarkPositionY = $fullImageDetails[1] / 2 - $waterMarkImageHeight / 2;
                        break;
                    case WATERMARK_LOCATION_UPPER_LEFT:
                        $waterMarkPositionX = 5;

                        $waterMarkPositionY = 5;
                        break;
                    case WATERMARK_LOCATION_UPPER_RIGHT:
                        $waterMarkPositionX = $fullImageDetails[0] - $waterMarkImageWidth - 5;

                        $waterMarkPositionY = 5;
                        break;
                }

                //get base image object
                switch ($imageType) {
                    case IMAGETYPE_GIF:
                        $uploadedAttachmentFile = imagecreatefromgif(
                            $showcase->config['attachments_uploads_path'] . '/' . $fileName
                        );
                        break;
                    case IMAGETYPE_JPEG:
                        $uploadedAttachmentFile = imagecreatefromjpeg(
                            $showcase->config['attachments_uploads_path'] . '/' . $fileName
                        );
                        break;
                    case IMAGETYPE_PNG:
                        $uploadedAttachmentFile = imagecreatefrompng(
                            $showcase->config['attachments_uploads_path'] . '/' . $fileName
                        );
                        break;
                }

                if (!empty($uploadedAttachmentFile) && isset($waterMarkPositionX) && isset($waterMarkPositionY)) {
                    imagealphablending($uploadedAttachmentFile, true);

                    imagealphablending($watermarkImageObject, true);

                    imagecopy(
                        $uploadedAttachmentFile,
                        $watermarkImageObject,
                        $waterMarkPositionX,
                        $waterMarkPositionY,
                        0,
                        0,
                        min($waterMarkImageWidth, $fullImageDetails[0]),
                        min($waterMarkImageHeight, $fullImageDetails[1])
                    );

                    //remove watermark from memory
                    imagedestroy($watermarkImageObject);

                    //write modified file

                    $fileOpen = fopen($showcase->config['attachments_uploads_path'] . '/' . $fileName, 'w');

                    if ($fileOpen) {
                        ob_start();

                        switch ($imageType) {
                            case IMAGETYPE_GIF:
                                imagegif($uploadedAttachmentFile);
                                break;
                            case IMAGETYPE_JPEG:
                                imagejpeg($uploadedAttachmentFile);
                                break;
                            case IMAGETYPE_PNG:
                                imagepng($uploadedAttachmentFile);
                                break;
                        }

                        $content = ob_get_clean();

                        ob_end_clean();

                        fwrite($fileOpen, $content);

                        fclose($fileOpen);

                        imagedestroy($uploadedAttachmentFile);
                    }
                }
            }
        }

        $returnData['dimensions'] = $insertData['dimensions'] = "{$fullImageDimensions[0]}x{$fullImageDimensions[1]}";
    }

    $insertData = hooksRun('upload_attachment_do_insert', $insertData);

    if ($existingAttachmentID && $isUpdate) {
        attachmentUpdate($insertData, $existingAttachmentID);
    } else {
        $existingAttachmentID = attachmentInsert($insertData);
    }

    $returnData['attachment_id'] = $existingAttachmentID;

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
        //$myshowcase['attachments_portal_build_widget'] == 1;
        if ($myshowcase['enabled'] == 1 && $myshowcase['attachments_portal_build_widget'] == 1) {
            $showcase_list[$id]['name'] = $myshowcase['name'];
            $showcase_list[$id]['script_name'] = $myshowcase['script_name'];
            $showcase_list[$id]['attachments_uploads_path'] = $myshowcase['attachments_uploads_path'];
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
            $showcase_file = $rand_showcase['script_name'] . '?action=view&entry_id={entry_id}';
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
                ["showcase_id='{$rand_id}'", "mime_type LIKE 'image%'", "status='1'", "entry_id!='0'"],
                ['entry_id', 'attachment_name', 'thumbnail_name'],
                ['limit' => 1, 'order_by' => 'RAND()']
            );

            $rand_entry = $attachmentData['entry_id'];
            $rand_entry_img = $attachmentData['attachment_name'];
            $rand_entry_thumb = $attachmentData['thumbnail_name'];

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

        if ((int)$rand_entry_thumb === ATTACHMENT_THUMBNAIL_SMALL) {
            $rand_img = $rand_showcase['attachments_uploads_path'] . '/' . $rand_entry_img;
        } else {
            $rand_img = $rand_showcase['attachments_uploads_path'] . '/' . $rand_entry_thumb;
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

function showcaseGetObject(int $selectedShowcaseID): ?Showcase
{
    $showcaseObjects = showcaseGet(
        queryFields: array_keys(TABLES_DATA['myshowcase_config'])
    );

    $scriptName = '';

    foreach ($showcaseObjects as $showcaseID => $showcaseData) {
        if ($selectedShowcaseID === $showcaseID) {
            $scriptName = $showcaseData['script_name'];
        }
    }

    return showcaseGetObjectByScriptName($scriptName);
}

function showcaseGetObjectByScriptName(string $scriptName): Showcase
{
    require_once ROOT . '/System/Showcase.php';

    static $showcaseObjects = [];

    if (!isset($showcaseObjects[$scriptName])) {
        $showcaseObjects[$scriptName] = new Showcase($scriptName);
    }

    return $showcaseObjects[$scriptName];
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

function formatField(int $formatType, string &$fieldValue): string|int
{
    $fieldValue = match ($formatType) {
        FormatTypes::numberFormat => $fieldValue = my_number_format((int)$fieldValue),
        FormatTypes::numberFormat1 => $fieldValue = number_format((float)$fieldValue, 1),
        FormatTypes::numberFormat2 => $fieldValue = number_format((float)$fieldValue, 2),
        FormatTypes::htmlSpecialCharactersUni => $fieldValue = htmlspecialchars_uni($fieldValue),
        FormatTypes::stripTags => $fieldValue = strip_tags($fieldValue),
        default => $fieldValue
    };

    return $fieldValue;
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
    return in_array(my_strtolower($fieldType), [
        FieldTypes::TinyInteger => FieldTypes::TinyInteger,
        FieldTypes::SmallInteger => FieldTypes::SmallInteger,
        FieldTypes::MediumInteger => FieldTypes::MediumInteger,
        FieldTypes::BigInteger => FieldTypes::BigInteger,
        FieldTypes::Integer => FieldTypes::Integer,
    ], true);
}

function fieldTypeMatchFloat(string $fieldType): bool
{
    return in_array(my_strtolower($fieldType), [
        FieldTypes::Decimal => FieldTypes::Decimal,
        FieldTypes::Float => FieldTypes::Float,
        FieldTypes::Double => FieldTypes::Double,
    ], true);
}

function fieldTypeMatchChar(string $fieldType): bool
{
    return in_array(my_strtolower($fieldType), [
        FieldTypes::Char => FieldTypes::Char,
        FieldTypes::VarChar => FieldTypes::VarChar,
    ], true);
}

function fieldTypeMatchText(string $fieldType): bool
{
    return in_array(my_strtolower($fieldType), [
        FieldTypes::TinyText => FieldTypes::TinyText,
        FieldTypes::Text => FieldTypes::Text,
        FieldTypes::MediumText => FieldTypes::MediumText,
    ], true);
}

function fieldTypeMatchDateTime(string $fieldType): bool
{
    return in_array(my_strtolower($fieldType), [
        FieldTypes::Date => FieldTypes::Date,
        FieldTypes::Time => FieldTypes::Time,
        FieldTypes::DateTime => FieldTypes::DateTime,
        FieldTypes::TimeStamp => FieldTypes::TimeStamp,
    ], true);
}

function fieldTypeMatchBinary(string $fieldType): bool
{
    return in_array(my_strtolower($fieldType), [
        FieldTypes::Binary => FieldTypes::Binary,
        FieldTypes::VarBinary => FieldTypes::VarBinary,
    ], true);
}

function fieldTypeSupportsFullText(string $fieldType): bool
{
    return in_array(my_strtolower($fieldType), [
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

/**
 * @throws RandomException
 */
//https://stackoverflow.com/a/15875555
function createUUIDv4(): string
{
    $data = random_bytes(16);

    $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function castTableFieldValue(mixed $value, string $fieldType): mixed
{
    if (fieldTypeMatchInt($fieldType)) {
        $value = (int)$value;
    } elseif (fieldTypeMatchFloat($fieldType)) {
        $value = (float)$value;
    } elseif (fieldTypeMatchChar($fieldType) ||
        fieldTypeMatchText($fieldType) ||
        fieldTypeMatchDateTime($fieldType)) {
        $value = (string)$value;
    }

    return $value;
}

function sanitizeTableFieldValue(mixed $value, string $fieldType)
{
    global $db;

    if (fieldTypeMatchInt($fieldType)) {
        $value = (int)$value;
    } elseif (fieldTypeMatchFloat($fieldType)) {
        $value = (float)$value;
    } elseif (fieldTypeMatchChar($fieldType) ||
        fieldTypeMatchText($fieldType) ||
        fieldTypeMatchDateTime($fieldType)) {
        $value = $db->escape_string($value);
    } elseif (fieldTypeMatchBinary($fieldType)) {
        $value = $db->escape_binary($value);
    }

    return $value;
}

function cleanSlug(string $slug): string
{
    return str_replace(['---', '--'],
        '-',
        preg_replace(
            '/[^\da-z]/i',
            '-',
            my_strtolower($slug)
        ));
}

function generateFieldSetSelectArray(): array
{
    return array_map(function ($fieldsetData) {
        return $fieldsetData['set_name'];
    }, cacheGet(CACHE_TYPE_FIELD_SETS));
}

function generateFilterFieldsSelectArray(): array
{
    global $lang;

    return [
        FILTER_TYPE_NONE => $lang->none,
        FILTER_TYPE_USER_ID => $lang->myShowcaseAdminSummaryAddEditFilterForceFieldUserID,
    ];
}


function generateWatermarkLocationsSelectArray(): array
{
    return [
        WATERMARK_LOCATION_LOWER_LEFT => 'lower-left',
        WATERMARK_LOCATION_LOWER_RIGHT => 'lower-right',
        WATERMARK_LOCATION_CENTER => 'center',
        WATERMARK_LOCATION_UPPER_LEFT => 'upper-left',
        WATERMARK_LOCATION_UPPER_RIGHT => 'upper-right',
    ];
}