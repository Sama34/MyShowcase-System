<?php
/**
 * MyShowcase Plugin for MyBB - Admin module for Field Editing
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \admin\modules\myshowcase\fields.php
 *
 */

declare(strict_types=1);

use function MyShowcase\Admin\languageModify;
use function MyShowcase\Core\cacheGet;
use function MyShowcase\Core\cacheUpdate;
use function MyShowcase\Core\fieldDataDelete;
use function MyShowcase\Core\fieldDataGet;
use function MyShowcase\Core\fieldDataInsert;
use function MyShowcase\Core\fieldDataUpdate;
use function MyShowcase\Core\fieldsDelete;
use function MyShowcase\Core\fieldsetDelete;
use function MyShowcase\Core\fieldsetGet;
use function MyShowcase\Core\fieldsetInsert;
use function MyShowcase\Core\fieldsetUpdate;
use function MyShowcase\Core\fieldsGet;
use function MyShowcase\Core\fieldsInsert;
use function MyShowcase\Core\fieldsUpdate;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\Core\loadLanguage;
use function MyShowcase\Core\showcaseDataTableExists;
use function MyShowcase\Core\showcaseDataTableFieldExists;
use function MyShowcase\Core\showcaseGet;
use function MyShowcase\Core\urlHandlerBuild;
use function MyShowcase\Core\urlHandlerSet;

use const MyShowcase\Core\FIELD_TYPE_STORAGE_BIGINT;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_BINARY;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_CHAR;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_DATE;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_DATETIME;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_DECIMAL;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_DOUBLE;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_FLOAT;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_INT;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_MEDIUMINT;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_MEDIUMTEXT;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_SMALLINT;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_TEXT;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_TIME;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_TIMESTAMP;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_TINYINT;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_TINYTEXT;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_VARBINARY;
use const MyShowcase\Core\FIELD_TYPE_STORAGE_VARCHAR;
use const MyShowcase\Core\FORMAT_TYPES;
use const MyShowcase\Core\TABLES_DATA;
use const MyShowcase\Core\CACHE_TYPE_CONFIG;
use const MyShowcase\Core\CACHE_TYPE_FIELD_DATA;
use const MyShowcase\Core\CACHE_TYPE_FIELD_SETS;
use const MyShowcase\Core\CACHE_TYPE_FIELDS;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $lang, $cache, $db, $plugins, $mybb;
global $page;

loadLanguage();

urlHandlerSet('index.php?module=myshowcase-fields');

$page->add_breadcrumb_item($lang->myShowcaseSystem, urlHandlerBuild());

$page->add_breadcrumb_item($lang->myShowcaseAdminFieldSets, urlHandlerBuild());

//get path to non-admin language folder currently in use
$languagePath = str_replace('admin', '', $lang->path . '/' . $lang->language);

$fieldID = $mybb->get_input('fid', MyBB::INPUT_INT);

$fieldsetID = $mybb->get_input('setid', MyBB::INPUT_INT);

$fieldOptionValue = $mybb->get_input('valueid', MyBB::INPUT_INT);

$pageAction = $mybb->get_input('action');

$pageTabs = [
    'myShowcaseAdminFieldSets' => [
        'title' => $lang->myShowcaseAdminFieldSets,
        'link' => urlHandlerBuild(),
        'description' => $lang->myShowcaseAdminFieldSetsDescription
    ],
];

if (in_array($pageAction, ['viewFields', 'newField', 'editField', 'editOption'])) {
    $pageTabs['myShowcaseAdminFields'] = [
        'title' => $lang->myShowcaseAdminFields,
        'link' => urlHandlerBuild(['action' => 'viewFields', 'setid' => $fieldsetID]),
        'description' => $lang->myShowcaseAdminFieldsDescription
    ];

    if (in_array($pageAction, ['editOption'])) {
        $pageTabs['myShowcaseAdminFieldsOptions'] = [
            'title' => $lang->myShowcaseAdminFieldsOptions,
            'link' => urlHandlerBuild(['action' => 'editOption', 'setid' => $fieldsetID, 'fid' => $fieldID]),
            'description' => $lang->myShowcaseAdminFieldsOptionsDescription
        ];
    } else {
        $pageTabs['myShowcaseAdminFieldsNew'] = [
            'title' => $lang->myShowcaseAdminFieldsNew,
            'link' => urlHandlerBuild(['action' => 'newField', 'setid' => $fieldsetID]),
            'description' => $lang->myShowcaseAdminFieldsNewDescription
        ];

        if ($pageAction == 'editField') {
            $pageTabs['myShowcaseAdminFieldsEdit'] = [
                'title' => $lang->myShowcaseAdminFieldsEdit,
                'link' => urlHandlerBuild(['action' => 'editField', 'setid' => $fieldsetID, 'fid' => $fieldID]),
                'description' => $lang->myShowcaseAdminFieldsEditDescription
            ];
        }
    }
} else {
    $pageTabs['myShowcaseAdminFieldSetsNew'] = [
        'title' => $lang->myShowcaseAdminFieldSetsNew,
        'link' => urlHandlerBuild(['action' => 'new']),
        'description' => $lang->myShowcaseAdminFieldSetsNewDescription
    ];

    if ($pageAction == 'edit') {
        $pageTabs['myShowcaseAdminFieldSetsEdit'] = [
            'title' => $lang->myShowcaseAdminFieldSetsEdit,
            'link' => urlHandlerBuild(['action' => 'edit', 'setid' => $fieldsetID]),
            'description' => $lang->myShowcaseAdminFieldSetsEditDescription
        ];
    }
}

if (in_array($pageAction, ['newField', 'editField'])) {
    $newPage = $pageAction === 'newField';

    hooksRun('admin_field_new_edit_start');

    $fieldsetData = fieldsetGet(["setid='{$fieldsetID}'"], ['setname'], ['limit' => 1]);

    if (!$newPage && empty($fieldsetData)) {
        flash_message($lang->myShowcaseAdminErrorInvalidFieldset, 'error');

        admin_redirect($pageTabs['myShowcaseAdminFieldSets']['link']);
    }

    $queryFields = TABLES_DATA['myshowcase_fields'];

    unset($queryFields['unique_key']);

    $fieldData = fieldsGet(["fid='{$fieldID}'"], array_keys($queryFields), ['limit' => 1]);

    if (!$newPage && empty($fieldData)) {
        flash_message($lang->myShowcaseAdminErrorInvalidField, 'error');

        admin_redirect(urlHandlerBuild(['action' => 'viewFields', 'setid' => $fieldsetID]));
    }

    $fieldIsEditable = true;

    $showcaseObjects = showcaseGet(["fieldsetid='{$fieldsetID}'"], [], ['limit' => 1]);

    if (!$newPage && $showcaseObjects && showcaseDataTableExists((int)$showcaseObjects['id'])) {
        $fieldIsEditable = false;
    }

    $page->output_header($pageTabs[$newPage ? 'myShowcaseAdminFieldsNew' : 'myShowcaseAdminFieldsEdit']['title']);

    $page->output_nav_tabs($pageTabs, $newPage ? 'myShowcaseAdminFieldsNew' : 'myShowcaseAdminFieldsEdit');

    $canEditLanguageFile = true;

    if (!is_writable($languagePath) || (!is_writable(
                $languagePath . '/myshowcase_fs' . $fieldsetID . '.lang.php'
            ) && file_exists(
                $languagePath . '/myshowcase_fs' . $fieldsetID . '.lang.php'
            ))) {
        $canEditLanguageFile = false;
    }

    if ($mybb->request_method === 'post') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            flash_message($lang->myShowcaseAdminErrorInvalidPostKey, 'error');

            admin_redirect(urlHandlerBuild());
        }

        $errorMessages = [];

        $mybb->input['name'] = trim(
            my_strtolower(
                preg_replace(
                    '#[^\w]#',
                    '_',
                    $mybb->get_input('name')
                )
            ),
            '_'
        );

        if ($fieldIsEditable && !$mybb->get_input('name')) {
            $errorMessages[] = $lang->myShowcaseAdminErrorMissingRequiredFields;
        }

        if ($mybb->get_input('min_length', MyBB::INPUT_INT) >
            $mybb->get_input('max_length', MyBB::INPUT_INT)) {
            $errorMessages[] = $lang->myShowcaseAdminErrorInvalidMinMax;
        }

        $existingFields = cacheGet(CACHE_TYPE_FIELDS)[$fieldsetID] ?? [];

        if ($fieldIsEditable && in_array($mybb->get_input('name'), array_column($existingFields, 'name')) && (function (
                string $fieldName
            ) use ($fieldsetID, $existingFields): bool {
                $duplicatedName = false;

                foreach ($existingFields as $fieldData) {
                    if ($fieldData['name'] === $fieldName && $fieldData['setid'] !== $fieldsetID) {
                        $duplicatedName = true;
                    }
                }

                return $duplicatedName;
            })(
                $mybb->get_input('name')
            )) {
            $errorMessages[] = $lang->myShowcaseAdminErrorDuplicatedName;
        }

        $fieldDefaultOption = 0;

        switch ($mybb->get_input('html_type')) {
            case 'db':
                $mybb->input['field_type'] = 'int';

                $mybb->input['max_length'] = 3;

                $fieldDefaultOption = 1;
                break;
            case 'radio':
                $mybb->input['field_type'] = 'int';

                $mybb->input['max_length'] = 1;

                $fieldDefaultOption = 1;
                break;
            case 'checkbox':
                $mybb->input['field_type'] = 'int';

                $mybb->input['max_length'] = 1;
                break;
            case 'date':
                $mybb->input['field_type'] = 'varchar';

                $mybb->input['min_length'] = max(
                    $mybb->get_input('min_length', MyBB::INPUT_INT),
                    1901
                );

                $mybb->input['max_length'] = min(
                    $mybb->get_input('max_length', MyBB::INPUT_INT),
                    2038
                );

                break;
            case 'url':
                $mybb->input['field_type'] = 'varchar';

                $mybb->input['max_length'] = 255;
                break;
        }

        $fieldDataQuery = [
            'setid' => $fieldsetID,
            'min_length' => $mybb->get_input('min_length', MyBB::INPUT_INT),
            'max_length' => $mybb->get_input('max_length', MyBB::INPUT_INT),
            'list_table_order' => $mybb->get_input('list_table_order', MyBB::INPUT_INT),
            'format' => $mybb->get_input('format'),
        ];

        if ($fieldIsEditable) {
            $fieldDataQuery['name'] = $db->escape_string($mybb->get_input('name'));

            $fieldDataQuery['html_type'] = $db->escape_string($mybb->get_input('html_type'));

            $fieldDataQuery['field_type'] = $db->escape_string($mybb->get_input('field_type'));
        }

        if ($errorMessages) {
            $page->output_inline_error($errorMessages);
        } else {
            $fieldDataQuery = hooksRun('admin_field_new_edit_post', $fieldDataQuery);

            if ($newPage) {
                $fieldID = fieldsInsert($fieldDataQuery);
            } else {
                fieldsUpdate(["fid='{$fieldID}'"], $fieldDataQuery);

                if ($fieldIsEditable &&
                    isset($fieldData['name']) &&
                    in_array($fieldData['html_type'], ['db', 'radio'])) {
                    fieldDataUpdate(
                        ["fid='{$fieldID}'", "setid='{$fieldsetID}'"],
                        ['name' => $fieldData['name']]
                    );
                }
            }

            $fieldOptionID = 0;

            if ($fieldDefaultOption) {
                $fieldOptionData = [
                    'setid' => $fieldsetID,
                    'fid' => $fieldID,
                    'name' => $db->escape_string($mybb->get_input('name')),
                    'value' => 'Not Specified'
                ];

                $fieldOptionID = fieldDataInsert($fieldOptionData);
            }

            if ($canEditLanguageFile) {
                languageModify(
                    'myshowcase_fs' . $fieldsetID,
                    [
                        'myshowcase_field_' . ($fieldDataQuery['name'] ?? $fieldData['name']) => ucfirst(
                            $mybb->get_input('label') ?? $mybb->get_input('name')
                        )
                    ]
                );
            }

            cacheUpdate(CACHE_TYPE_FIELDS);

            cacheUpdate(CACHE_TYPE_FIELD_DATA);

            log_admin_action(['fieldsetID' => $fieldsetID, 'fieldID' => $fieldID, 'fieldOptionID' => $fieldOptionID]);

            if ($newPage) {
                flash_message($lang->myShowcaseAdminSuccessNewField, 'success');
            } else {
                flash_message($lang->myShowcaseAdminSuccessEditField, 'success');
            }

            admin_redirect(urlHandlerBuild(['action' => 'viewFields', 'setid' => $fieldsetID]));
        }
    }

    $mybb->input = array_merge($fieldData, $mybb->input);

    $form = new Form(
        $pageTabs[$newPage ? 'myShowcaseAdminFieldsNew' : 'myShowcaseAdminFieldsEdit']['link'],
        'post',
        $newPage ? 'myShowcaseAdminFieldsNew' : 'myShowcaseAdminFieldsEdit'
    );

    echo $form->generate_hidden_field('my_post_key', $mybb->post_code);

    $formContainer = new FormContainer(
        $pageTabs[$newPage ? 'myShowcaseAdminFieldsNew' : 'myShowcaseAdminFieldsEdit']['title']
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormName . '<em>*</em>',
        $lang->myShowcaseAdminFieldsNewFormNameDescription,
        $form->generate_text_box(
            'name',
            $mybb->get_input('name'),
            ['id' => 'name', 'style' => $fieldIsEditable ? '' : '" disabled="disabled']
        ),
        'name'
    );

    if (!$newPage) {
        include_once $languagePath . '/myshowcase_fs' . $fieldsetID . '.lang.php';

        $mybb->input['label'] = $l['myshowcase_field_' . $mybb->get_input('name')] ?? $mybb->get_input('label');
    }

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormLabel,
        $lang->myShowcaseAdminFieldsNewFormLabelDescription,
        $form->generate_text_box(
            'label',
            $mybb->get_input('label'),
            ['id' => 'label', 'style' => $canEditLanguageFile ? '' : '" disabled="disabled']
        ),
        'label'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormHtmlType,
        $lang->myShowcaseAdminFieldsNewFormHtmlTypeDescription,
        $form->generate_select_box(
            'html_type',
            [
                'textbox' => 'textbox',
                'textarea' => 'textarea',
                //single select
                //multiselect
                //captcha
                //files|attachments
                // todo, implement additional field types
                // todo, add note field for admins to add a note
                'db' => 'db',
                'radio' => 'radio',
                'checkbox' => 'checkbox',
                'url' => 'url',
                'date' => 'date',
            ],
            $mybb->get_input('html_type'),
            ['id' => 'html_type', 'size' => $fieldIsEditable ? '' : '" disabled="disabled']
        ),
        'html_type'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormFieldType,
        $lang->myShowcaseAdminFieldsNewFormFieldTypeDescription,
        $form->generate_select_box(
            'field_type',
            [
                FIELD_TYPE_STORAGE_TINYINT => FIELD_TYPE_STORAGE_TINYINT,
                FIELD_TYPE_STORAGE_SMALLINT => FIELD_TYPE_STORAGE_SMALLINT,
                FIELD_TYPE_STORAGE_MEDIUMINT => FIELD_TYPE_STORAGE_MEDIUMINT,
                FIELD_TYPE_STORAGE_BIGINT => FIELD_TYPE_STORAGE_BIGINT,
                FIELD_TYPE_STORAGE_INT => FIELD_TYPE_STORAGE_INT,

                FIELD_TYPE_STORAGE_DECIMAL => FIELD_TYPE_STORAGE_DECIMAL,
                FIELD_TYPE_STORAGE_FLOAT => FIELD_TYPE_STORAGE_FLOAT,
                FIELD_TYPE_STORAGE_DOUBLE => FIELD_TYPE_STORAGE_DOUBLE,

                FIELD_TYPE_STORAGE_CHAR => FIELD_TYPE_STORAGE_CHAR,
                FIELD_TYPE_STORAGE_VARCHAR => FIELD_TYPE_STORAGE_VARCHAR,

                FIELD_TYPE_STORAGE_TINYTEXT => FIELD_TYPE_STORAGE_TINYTEXT,
                FIELD_TYPE_STORAGE_TEXT => FIELD_TYPE_STORAGE_TEXT,
                FIELD_TYPE_STORAGE_MEDIUMTEXT => FIELD_TYPE_STORAGE_MEDIUMTEXT,

                FIELD_TYPE_STORAGE_DATE => FIELD_TYPE_STORAGE_DATE,
                FIELD_TYPE_STORAGE_TIME => FIELD_TYPE_STORAGE_TIME,
                FIELD_TYPE_STORAGE_DATETIME => FIELD_TYPE_STORAGE_DATETIME,
                FIELD_TYPE_STORAGE_TIMESTAMP => FIELD_TYPE_STORAGE_TIMESTAMP,

                FIELD_TYPE_STORAGE_BINARY => FIELD_TYPE_STORAGE_BINARY,
                FIELD_TYPE_STORAGE_VARBINARY => FIELD_TYPE_STORAGE_VARBINARY,

                //'real' => 'real',
                //'bit' => 'bit',
                //'boolean' => 'boolean',
                //'serial' => 'serial',
                //'date' => 'date',
                //'datetime' => 'datetime',
                //'time' => 'time',
                //'year' => 'year',
                //'tinyblob' => 'tinyblob',
                //'mediumblob' => 'mediumblob',
                //'blob' => 'blob',
                //'longblob' => 'longblob',
                //'enum' => 'enum',
                //'set' => 'set',
            ],
            $mybb->get_input('field_type'),
            ['id' => 'field_type', 'size' => $fieldIsEditable ? '' : '" disabled="disabled']
        ),
        'field_type'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormMinimumLength,
        $lang->myShowcaseAdminFieldsNewFormMinimumLengthDescription,
        $form->generate_numeric_field(
            'min_length',
            $mybb->get_input('min_length'),
            ['id' => 'min_length', 'class' => 'field150', 'min' => 0],
        ),
        'min_length'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormMaximumLength,
        $lang->myShowcaseAdminFieldsNewFormMaximumLengthDescription,
        $form->generate_numeric_field(
            'max_length',
            $mybb->get_input('max_length'),
            ['id' => 'max_length', 'class' => 'field150', 'min' => 0],
        ),
        'max_length'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormOrderList,
        $lang->myShowcaseAdminFieldsNewFormOrderListDescription,
        $form->generate_numeric_field(
            'list_table_order',
            $mybb->get_input('list_table_order'),
            ['id' => 'list_table_order', 'class' => 'align_center field50', 'min' => 0],
        ),
        'list_table_order'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormFormat,
        $lang->myShowcaseAdminFieldsNewFormFormatDescription,
        $form->generate_select_box(
            'format',
            (function (): array {
                $selectOptions = [];

                $formatTypes = FORMAT_TYPES;

                $formatTypes = array_flip($formatTypes);

                ksort($formatTypes);

                foreach ($formatTypes as $formatTypeName => $formatTypeKey) {
                    $selectOptions[$formatTypeKey] = $formatTypeName;
                }

                return $selectOptions;
            })(),
            $mybb->get_input('format'),
            ['id' => 'format']
        ),
        'format'
    );

    hooksRun('admin_field_new_edit_end');

    $formContainer->end();

    $form->output_submit_wrapper([
        $form->generate_submit_button($lang->myShowcaseAdminButtonSubmit),
        $form->generate_reset_button($lang->myShowcaseAdminButtonReset)
    ]);

    $form->end();

    $page->output_footer();
} elseif (in_array($pageAction, ['new', 'edit'])) {
    $newPage = $pageAction === 'new';

    hooksRun('admin_fieldset_new_edit_start');

    $fieldsetData = fieldsetGet(["setid='{$fieldsetID}'"], ['setname'], ['limit' => 1]);

    if (!$newPage && empty($fieldsetData)) {
        flash_message($lang->myShowcaseAdminErrorInvalidShowcase, 'error');

        admin_redirect($pageTabs['myShowcaseAdminFieldSets']['link']);
    }

    $page->output_header($lang->myShowcaseAdminFieldSets);

    $page->output_nav_tabs($pageTabs, $newPage ? 'myShowcaseAdminFieldSetsNew' : 'myShowcaseAdminFieldSetsEdit');

    if ($mybb->request_method === 'post') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            flash_message($lang->myShowcaseAdminErrorInvalidPostKey, 'error');

            admin_redirect(urlHandlerBuild());
        }

        $errorMessages = [];

        if (!$mybb->get_input('setname')) {
            $errorMessages[] = $lang->myShowcaseAdminErrorMissingRequiredFields;
        }

        $existingFieldSets = cacheGet(CACHE_TYPE_FIELD_SETS);

        if (in_array($mybb->get_input('setname'), array_column($existingFieldSets, 'setname')) && (function (
                string $showcaseName
            ) use ($fieldsetID, $existingFieldSets): bool {
                $duplicatedName = false;

                foreach ($existingFieldSets as $fieldsetData) {
                    if ($fieldsetData['setname'] === $showcaseName && $fieldsetData['setid'] !== $fieldsetID) {
                        $duplicatedName = true;
                    }
                }

                return $duplicatedName;
            })(
                $mybb->get_input('setname')
            )) {
            $errorMessages[] = $lang->myShowcaseAdminErrorDuplicatedName;
        }

        if (!is_writable($languagePath)) {
            $errorMessages[] = $lang->sprintf(
                $lang->myshowcase_fields_lang_not_writable,
                $lang->path . '/' . $lang->language . '/'
            );
        }

        $fieldsetData = [
            'setname' => $db->escape_string($mybb->get_input('setname')),
        ];

        if ($errorMessages) {
            $page->output_inline_error($errorMessages);
        } else {
            $fieldsetData = hooksRun('admin_fieldset_new_edit_post', $fieldsetData);

            if ($newPage) {
                $fieldsetID = fieldsetInsert($fieldsetData);
            } else {
                fieldsetUpdate($fieldsetID, $fieldsetData);
            }

            cacheUpdate(CACHE_TYPE_CONFIG);

            cacheUpdate(CACHE_TYPE_FIELDS);

            cacheUpdate(CACHE_TYPE_FIELD_DATA);

            cacheUpdate(CACHE_TYPE_FIELD_SETS);

            log_admin_action(['fieldsetID' => $fieldsetID]);

            if ($newPage) {
                flash_message($lang->myShowcaseAdminSuccessNewFieldset, 'success');
            } else {
                flash_message($lang->myShowcaseAdminSuccessEditFieldset, 'success');
            }

            admin_redirect(urlHandlerBuild());
        }
    }

    $mybb->input = array_merge($fieldsetData, $mybb->input);

    $form = new Form(
        $pageTabs[$newPage ? 'myShowcaseAdminFieldSetsNew' : 'myShowcaseAdminFieldSetsEdit']['link'],
        'post',
        $newPage ? 'myShowcaseAdminFieldSetsNew' : 'myShowcaseAdminFieldSetsEdit'
    );

    echo $form->generate_hidden_field('my_post_key', $mybb->post_code);

    $formContainer = new FormContainer(
        $pageTabs[$newPage ? 'myShowcaseAdminFieldSetsNew' : 'myShowcaseAdminFieldSetsEdit']['title']
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldSetsNewFormName . '<em>*</em>',
        $lang->myShowcaseAdminFieldSetsNewFormNameDescription,
        $form->generate_text_box('setname', $mybb->get_input('setname'), ['id' => 'setname']),
        'setname'
    );

    hooksRun('admin_fieldset_new_edit_end');

    $formContainer->end();

    $form->output_submit_wrapper([
        $form->generate_submit_button($lang->myShowcaseAdminButtonSubmit),
        $form->generate_reset_button($lang->myShowcaseAdminButtonReset)
    ]);

    $form->end();

    $page->output_footer();
} elseif ($pageAction == 'viewFields') {
    $fieldsetData = fieldsetGet(["setid='{$fieldsetID}'"], ['setname'], ['limit' => 1]);

    if (empty($fieldsetData)) {
        flash_message($lang->myshowcase_fields_invalid_id, 'error');

        admin_redirect(urlHandlerBuild());
    }

    $page->add_breadcrumb_item(
        $lang->sprintf($lang->myshowcase_admin_edit_fieldset, $fieldsetData['setname']),
        urlHandlerBuild()
    );

    $page->output_header($lang->myshowcase_admin_fields);

    $page->output_nav_tabs($pageTabs, 'myShowcaseAdminFields');

    hooksRun('admin_view_fields_start');

    if ($mybb->request_method === 'post') {
        foreach (fieldsGet(["setid='{$fieldsetID}'"]) as $fieldID => $fieldData) {
            $fieldData = [
                'field_order' => (int)($mybb->get_input('field_order', MyBB::INPUT_ARRAY)[$fieldID] ?? 0),
                'enabled' => (int)!empty($mybb->get_input('enabled', MyBB::INPUT_ARRAY)[$fieldID]),
                'requiredField' => (int)!empty($mybb->get_input('requiredField', MyBB::INPUT_ARRAY)[$fieldID]),
                'parse' => (int)!empty($mybb->get_input('parse', MyBB::INPUT_ARRAY)[$fieldID]),
                'searchable' => (int)!empty($mybb->get_input('searchable', MyBB::INPUT_ARRAY)[$fieldID]),
            ];

            $fieldData = hooksRun('admin_view_fields_update_post', $fieldData);

            fieldsUpdate(["fid='{$fieldID}'", "setid='{$fieldsetID}'"], $fieldData);
        }

        flash_message($lang->myshowcase_field_update_success, 'success');

        admin_redirect(urlHandlerBuild(['action' => 'viewFields', 'setid' => $fieldsetID]));
    }

    $form = new Form(
        urlHandlerBuild(['action' => 'viewFields', 'setid' => $fieldsetID]),
        'post',
        'viewFields'
    );

    $formContainer = new FormContainer($lang->myshowcase_field_list);

    $formContainer->output_row_header(
        $lang->myshowcase_field_fid,
        ['width' => '1%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_field_name,
        ['class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_field_label,
        ['class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_field_html_type,
        ['width' => '5%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_field_field_type,
        ['width' => '5%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_field_field_order,
        ['width' => '5%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_field_enabled,
        ['width' => '4%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_field_required,
        ['width' => '4%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_field_parse,
        ['width' => '4%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_field_searchable,
        ['width' => '4%', 'class' => 'align_center']
    );

    $formContainer->output_row_header($lang->controls, ['width' => '5%', 'class' => 'align_center']);

    $maximumViewOrder = 0;

    $queryFields = TABLES_DATA['myshowcase_fields'];

    unset($queryFields['unique_key']);

    $fieldObjects = fieldsGet(["setid='{$fieldsetID}'"], array_keys($queryFields), ['order_by' => 'field_order']);

    if (!$fieldObjects) {
        $formContainer->output_cell(
            $lang->myshowcase_fields_no_fields,
            ['class' => 'align_center', 'colspan' => 11]
        );

        $formContainer->construct_row();
    } else {
        include_once $languagePath . '/myshowcase_fs' . $fieldsetID . '.lang.php';

        $maximumViewOrder = 1;

        foreach ($fieldObjects as $fieldID => $fieldData) {
            $formContainer->output_cell(my_number_format($fieldID), ['class' => 'align_center']);

            $viewOptionsUrl = '';

            if ($fieldData['html_type'] == 'db' || $fieldData['html_type'] == 'radio') {
                $viewOptionsUrl = urlHandlerBuild(
                    ['action' => 'editOption', 'fid' => $fieldID, 'setid' => $fieldsetID]
                );
            }

            $formContainer->output_cell(
                $viewOptionsUrl ? "<a href='{$viewOptionsUrl}'>{$fieldData['name']}</a>" : $fieldData['name'],
                ['class' => 'align_left']
            );

            $formContainer->output_cell(
                $l['myshowcase_field_' . $fieldData['name']] ?? '',
                ['class' => 'align_left']
            );

            $formContainer->output_cell(
                $fieldData['html_type'],
                ['class' => 'align_center']
            );

            $formContainer->output_cell(
                $fieldData['field_type'],
                ['class' => 'align_center']
            );

            $formContainer->output_cell(
                $form->generate_numeric_field(
                    "field_order[{$fieldID}]",
                    $fieldData['field_order'],
                    ['id' => "field_order[{$fieldID}]", 'class' => 'align_center field50', 'min' => 0],
                ),
                ['class' => 'align_center']
            );

            $formContainer->output_cell(
                $form->generate_check_box(
                    'enabled[' . $fieldID . ']',
                    'true',
                    '',
                    ['checked' => $fieldData['enabled']],
                    ''
                ),
                ['class' => 'align_center']
            );

            $formContainer->output_cell(
                $form->generate_check_box(
                    'requiredField[' . $fieldID . ']',
                    'true',
                    '',
                    ['checked' => $fieldData['requiredField']],
                    ''
                ),
                ['class' => 'align_center']
            );

            $formContainer->output_cell(
                $form->generate_check_box(
                    'parse[' . $fieldID . ']',
                    'true',
                    '',
                    ['checked' => $fieldData['parse']],
                    ''
                ),
                ['class' => 'align_center']
            );

            $formContainer->output_cell(
                $form->generate_check_box(
                    'searchable[' . $fieldID . ']',
                    'true',
                    '',
                    ['checked' => $fieldData['searchable']],
                    ''
                ),
                ['class' => 'align_center']
            );

            $popup = new PopupMenu("field_{$fieldID}", $lang->options);

            $popup->add_item(
                $lang->edit,
                urlHandlerBuild(['action' => 'editField', 'fid' => $fieldID, 'setid' => $fieldsetID]),
            );

            $popup->add_item(
                $lang->myshowcase_field_delete,
                urlHandlerBuild(['action' => 'deleteField', 'fid' => $fieldID, 'setid' => $fieldsetID]),
            );

            //add option to edit list items if db type
            if ($fieldData['html_type'] == 'db' || $fieldData['html_type'] == 'radio') {
                $popup->add_item(
                    $lang->myshowcase_field_edit_options,
                    urlHandlerBuild(['action' => 'editOption', 'fid' => $fieldID, 'setid' => $fieldsetID]),
                );
            }

            $maximumViewOrder = max($maximumViewOrder, $fieldData['field_order']);

            $formContainer->output_cell($popup->fetch(), ['class' => 'align_center']);

            $formContainer->construct_row();
        }
    }

    $formContainer->end();

    $buttons[] = $form->generate_submit_button($lang->myshowcase_fields_save_changes);

    $form->output_submit_wrapper($buttons);

    $form->end();

    hooksRun('admin_view_fields_end');

    $page->output_footer();
} elseif ($pageAction == 'delete') {
    $fieldsetData = fieldsetGet(["setid='{$fieldsetID}'"], ['setname'], ['limit' => 1]);

    if (!$fieldsetData) {
        flash_message($lang->myShowcaseAdminErrorInvalidFieldset, 'error');

        admin_redirect(urlHandlerBuild());
    }

    $showcaseObjects = showcaseGet(["fieldsetid='{$fieldsetID}'"], [], ['limit' => 1]);

    if (!empty($showcaseObjects)) {
        flash_message($lang->myShowcaseAdminErrorFieldsetDeleteFailed, 'error');

        admin_redirect(urlHandlerBuild());
    }

    hooksRun('admin_fieldset_delete_start');

    $fieldsetName = $fieldsetData['setname'];

    if ($mybb->request_method === 'post') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            flash_message($lang->myShowcaseAdminErrorInvalidPostKey, 'error');

            admin_redirect(urlHandlerBuild());
        }

        if (isset($mybb->input['no'])) {
            admin_redirect(urlHandlerBuild());
        }

        hooksRun('admin_fieldset_delete_post');

        fieldsDelete(["setid='{$fieldsetID}'"]);

        fieldDataDelete(["setid='{$fieldsetID}'"]);

        fieldsetDelete(["setid='{$fieldsetID}'"]);

        foreach ((array)$lang->get_languages() as $langfolder => $langname) {
            $languageFile = $lang->path . '/' . $langfolder . '/myshowcase_fs' . $fieldsetID . '.lang.php';

            if (file_exists($languageFile)) {
                unlink($languageFile);
            }
        }

        cacheUpdate(CACHE_TYPE_CONFIG);

        cacheUpdate(CACHE_TYPE_FIELDS);

        cacheUpdate(CACHE_TYPE_FIELD_DATA);

        cacheUpdate(CACHE_TYPE_FIELD_SETS);

        log_admin_action(['fieldsetID' => $fieldsetID]);

        if (fieldsetGet(["setid='{$fieldsetID}'"])) {
            flash_message($lang->myShowcaseAdminErrorFieldsetDelete, 'error');
        } else {
            flash_message($lang->myShowcaseAdminSuccessFieldsetDeleted, 'success');
        }

        admin_redirect(urlHandlerBuild());
    }

    $page->output_confirm_action(
        urlHandlerBuild(['action' => 'delete', 'setid' => $fieldsetID]),
        $lang->sprintf($lang->myShowcaseAdminConfirmFieldsetDelete, $fieldsetName)
    );
} elseif ($pageAction == 'editOption') {
    $fieldData = fieldsGet(
        ["fid='{$fieldID}'", "setid='{$fieldsetID}'", "html_type In ('db', 'radio')"],
        ['name'],
        ['limit' => 1]
    );

    if (!$fieldData) {
        flash_message($lang->myshowcase_field_invalid_id, 'error');

        admin_redirect(urlHandlerBuild(['action' => 'viewFields', 'setid' => $fieldsetID]));
    }

    $can_edit = true;

    foreach (showcaseGet(["fieldsetid='{$fieldsetID}'"]) as $showcaseID => $showcaseData) {
        if (showcaseDataTableExists($showcaseID)) {
            $can_edit = false;
        }
    }

    $fieldOptionData = fieldsetGet(["setid='{$fieldsetID}'"], ['setname'], ['limit' => 1]);

    $page->add_breadcrumb_item(
        $lang->sprintf($lang->myshowcase_admin_edit_fieldopt, $fieldData['name'], $fieldOptionData['setname']),
        urlHandlerBuild()
    );

    $page->output_header($lang->myshowcase_admin_fields);

    $page->output_nav_tabs($pageTabs, 'myShowcaseAdminFieldsOptions');

    hooksRun('admin_option_edit_start');

    //user clicked Save button
    if ($mybb->request_method === 'post') {
        //apply changes to existing fields first
        $fieldOptionObjects = fieldDataGet(
            ["setid='{$fieldsetID}'", "fid='{$fieldID}'"],
            ['valueid', 'value', 'disporder']
        );

        foreach ($fieldOptionObjects as $fieldOptionID => $fieldOptionData) {
            $fieldOptionValueID = (int)$fieldOptionData['valueid'];

            fieldDataUpdate(
                ["setid='{$fieldsetID}'", "fid='{$fieldID}'", "valueid='{$fieldOptionValueID}'"],
                [
                    'value' => $db->escape_string(
                        $mybb->get_input('value', MyBB::INPUT_ARRAY)[$fieldOptionValueID] ?? $fieldOptionData['value']
                    ),
                    'disporder' => (int)($mybb->get_input(
                        'disporder',
                        MyBB::INPUT_ARRAY
                    )[$fieldOptionValueID] ?? $fieldOptionData['disporder']),
                ]
            );
        }

        if ($mybb->get_input('value') &&
            $fieldOptionValue &&
            $mybb->get_input('disporder', MyBB::INPUT_INT)) {
            $fieldOptionData = [
                'setid' => $fieldsetID,
                'fid' => $fieldID,
                'name' => $db->escape_string($mybb->get_input('name')),
                'value' => $db->escape_string($mybb->get_input('value')),
                'valueid' => $fieldOptionValue,
                'disporder' => $mybb->get_input('disporder', MyBB::INPUT_INT)
            ];

            fieldDataInsert($fieldOptionData);
        }

        hooksRun('admin_option_edit_post');

        cacheUpdate(CACHE_TYPE_CONFIG);

        cacheUpdate(CACHE_TYPE_FIELDS);

        cacheUpdate(CACHE_TYPE_FIELD_DATA);

        cacheUpdate(CACHE_TYPE_FIELD_SETS);

        flash_message($lang->myshowcase_field_update_opt_success, 'success');

        admin_redirect(
            urlHandlerBuild(['action' => 'editOption', 'setid' => $fieldsetID, 'fid' => $fieldID]),
        );
    }

    $form = new Form(
        urlHandlerBuild(['action' => 'editOption', 'setid' => $fieldsetID, 'fid' => $fieldID]),
        'post',
        'editOption'
    );

    $formContainer = new FormContainer($lang->myshowcase_field_list);

    $formContainer->output_row_header(
        $lang->myshowcase_field_fdid,
        ['width' => '5%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_field_option_text,
        ['width' => '10%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_field_disporder,
        ['width' => '5%', 'class' => 'align_center']
    );

    $formContainer->output_row_header($lang->controls, ['width' => '10%', 'class' => 'align_center']);

    $maximumViewOrder = 0;

    $fieldOptionObjects = fieldDataGet(
        ["setid='{$fieldsetID}'", "fid='{$fieldID}'"],
        ['valueid', 'name', 'value', 'disporder'],
        ['order_by' => 'valueid']
    );

    if (!$fieldOptionObjects) {
        $formContainer->output_cell(
            $lang->myshowcase_field_no_options,
            ['class' => 'align_center', 'colspan' => 5]
        );
        $formContainer->construct_row();
    } else {
        $maximumViewOrder = 1;

        $maximumViewOrder = 0;

        foreach ($fieldOptionObjects as $fieldOptionID => $fieldOptionData) {
            $fieldOptionValueID = (int)$fieldOptionData['valueid'];

            $maximumViewOrder = max($maximumViewOrder, $fieldOptionData['disporder']);

            $maximumViewOrder = max($maximumViewOrder, $fieldOptionValueID);

            $formContainer->output_cell(
                my_number_format($fieldOptionValueID),
                ['class' => 'align_center']
            );

            $formContainer->output_cell(
                $form->generate_text_box(
                    "value[{$fieldOptionValueID}]",
                    $fieldOptionData['value'],
                    ['id' => "value[{$fieldOptionValueID}]", 'class' => 'field150']
                ),
                ['class' => 'align_center']
            );

            $formContainer->output_cell(
                $form->generate_numeric_field(
                    "disporder[{$fieldOptionValueID}]",
                    $fieldOptionData['disporder'],
                    ['id' => "disporder[{$fieldOptionValueID}]", 'class' => 'align_center field50', 'min' => 0]
                ),
                ['class' => 'align_center']
            );

            $popup = new PopupMenu("field_{$fieldOptionValueID}", $lang->options);

            if ($can_edit) {
                $popup->add_item(
                    $lang->myshowcase_field_delete,
                    urlHandlerBuild([
                        'action' => 'deleteOption',
                        'setid' => $fieldsetID,
                        'fid' => $fieldID,
                        'valueid' => $fieldOptionValueID
                    ]),
                );
            }

            if ($can_edit) {
                $formContainer->output_cell($popup->fetch(), ['class' => 'align_center']);
            } else {
                $formContainer->output_cell('N/A', ['class' => 'align_center']);
            }

            $formContainer->construct_row();
        }
    }

    $formContainer->end();

    $form->output_submit_wrapper([$form->generate_submit_button($lang->myshowcase_fields_save_changes)]);

    $form->end();

    echo '<br />';

    $form = new Form(
        urlHandlerBuild(['action' => 'editOption', 'setid' => $fieldsetID, 'fid' => $fieldID]),
        'post',
        'editOption'
    );

    $formContainer = new FormContainer($lang->myshowcase_field_new_option);

    $formContainer->output_row_header(
        $lang->myshowcase_field_option_text . ' *',
        ['width' => '65', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_field_disporder,
        ['width' => '65', 'class' => 'align_center']
    );

    $formContainer->output_cell(
        $form->generate_text_box('value', '', ['id' => 'value', 'class' => 'field150']),
        ['class' => 'align_center']
    );

    echo $form->generate_hidden_field('name', $fieldData['name']);

    echo $form->generate_hidden_field('valueid', ++$maximumViewOrder);

    $formContainer->output_cell(
        $form->generate_numeric_field(
            'disporder',
            ++$maximumViewOrder,
            ['id' => 'disporder', 'class' => 'align_center field50', 'min' => 0]
        ),
        ['class' => 'align_center']
    );

    $formContainer->construct_row();

    hooksRun('admin_option_edit_end');

    $formContainer->end();

    $form->output_submit_wrapper([$form->generate_submit_button($lang->myshowcase_fields_save_changes)]);

    $form->end();
} elseif ($pageAction == 'deleteOption') {
    $fieldsetData = fieldsetGet(["setid='{$fieldsetID}'"], ['setname'], ['limit' => 1]);

    if (!$fieldsetData) {
        flash_message($lang->myShowcaseAdminErrorInvalidFieldset, 'error');

        admin_redirect(urlHandlerBuild());
    }

    $fieldOptionData = fieldDataGet(["setid='{$fieldsetID}'", "valueid='{$fieldOptionValue}'"], ['name']);

    if (!$fieldOptionData) {
        flash_message($lang->myshowcase_field_invalid_opt, 'error');

        admin_redirect(
            urlHandlerBuild(['action' => 'editOption', 'setid' => $fieldsetID]),
        );
    }

    $showcaseObjects = showcaseGet(["fieldsetid='{$fieldsetID}'"], [], ['limit' => 1]);

    if (!empty($showcaseObjects)) {
        flash_message($lang->myshowcase_fields_in_use, 'error');

        admin_redirect(
            urlHandlerBuild(['action' => 'editOption', 'setid' => $fieldsetID, 'fid' => $fieldID]),
        );
    }

    hooksRun('admin_option_delete_start');

    if ($mybb->request_method === 'post') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            flash_message($lang->myShowcaseAdminErrorInvalidPostKey, 'error');

            admin_redirect(
                urlHandlerBuild(['action' => 'editOption', 'setid' => $fieldsetID, 'fid' => $fieldID]),
            );
        }

        if (isset($mybb->input['no'])) {
            admin_redirect(
                urlHandlerBuild(['action' => 'editOption', 'setid' => $fieldsetID, 'fid' => $fieldID]),
            );
        }

        hooksRun('admin_option_delete_post');

        fieldDataDelete(["setid='{$fieldsetID}'", "fid='{$fieldID}'", "valueid='{$fieldOptionValue}'"]);

        cacheUpdate(CACHE_TYPE_CONFIG);

        cacheUpdate(CACHE_TYPE_FIELDS);

        cacheUpdate(CACHE_TYPE_FIELD_DATA);

        cacheUpdate(CACHE_TYPE_FIELD_SETS);

        log_admin_action(['fieldsetID' => $fieldsetID, 'fieldID' => $fieldID, 'valueid' => $fieldOptionValue]);

        flash_message($lang->myshowcase_field_delete_opt_success, 'success');

        admin_redirect(
            urlHandlerBuild(['action' => 'editOption', 'setid' => $fieldsetID, 'fid' => $fieldID]),
        );
    }

    $page->output_confirm_action(
        urlHandlerBuild([
            'action' => 'deleteOption',
            'setid' => $fieldsetID,
            'fid' => $fieldID,
            'valueid' => $fieldOptionValue
        ]),
        $lang->sprintf($lang->myShowcaseAdminConfirmFieldsetDelete, $fieldsetData['setname'])
    );
} elseif ($pageAction == 'deleteField') {
    $fieldsetData = fieldsetGet(["setid='{$fieldsetID}'"], ['setname'], ['limit' => 1]);

    if (!$fieldsetData) {
        flash_message($lang->myshowcase_fields_invalid_id, 'error');

        admin_redirect(urlHandlerBuild());
    }

    $fieldData = fieldsGet(["setid='{$fieldsetID}'", "fid='{$fieldID}'"], ['name'], ['limit' => 1]);

    if (empty($fieldData['fid'])) {
        flash_message($lang->myshowcase_field_invalid_id, 'error');
        admin_redirect(
            urlHandlerBuild(['action' => 'viewFields', 'setid' => $fieldsetID]),
        );
    }

    $showcaseObjects = showcaseGet(["fieldsetid='{$fieldsetID}'"]);

    $fieldName = $fieldData['name'] ?? $fieldData['id'];

    $fieldIsInUse = false;

    foreach ($showcaseObjects as $showcaseID => $showcaseData) {
        if (!showcaseDataTableExists($showcaseID)) {
            break;
        }

        if (showcaseDataTableFieldExists($showcaseID, $fieldName)) {
            $fieldIsInUse = true;

            break;
        }
    }

    if ($fieldIsInUse) {
        flash_message($lang->myshowcase_field_in_use, 'error');

        admin_redirect(urlHandlerBuild(['action' => 'viewFields', 'setid' => $fieldsetID]));
    }

    hooksRun('admin_field_delete_start');

    if ($mybb->request_method === 'post') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            flash_message($lang->myShowcaseAdminErrorInvalidPostKey, 'error');

            admin_redirect(urlHandlerBuild());
        }

        if (isset($mybb->input['no'])) {
            admin_redirect(urlHandlerBuild(['action' => 'viewFields', 'setid' => $fieldsetID]));
        }

        hooksRun('admin_field_delete_post');

        languageModify(
            'myshowcase_fs' . $fieldsetID,
            [],
            ['myshowcase_field_' . $fieldName => '']
        );

        if (fieldDataGet(["setid='{$fieldsetID}'", "fid='{$fieldID}'"])) {
            fieldDataDelete(["setid='{$fieldsetID}'", "fid='{$fieldID}'"]);
        }

        fieldsDelete(["setid='{$fieldsetID}'", "fid='{$fieldID}'"]);

        cacheUpdate(CACHE_TYPE_CONFIG);

        cacheUpdate(CACHE_TYPE_FIELDS);

        cacheUpdate(CACHE_TYPE_FIELD_DATA);

        cacheUpdate(CACHE_TYPE_FIELD_SETS);

        log_admin_action(['fieldsetID' => $fieldsetID, 'fieldID' => $fieldID]);

        if (fieldsGet(["setid='{$fieldsetID}'", "fid='{$fieldID}'"])) {
            flash_message($lang->myShowcaseAdminErrorFieldDelete, 'success');
        } else {
            flash_message($lang->myShowcaseAdminSuccessFieldDeleted, 'success');
        }

        admin_redirect(urlHandlerBuild(['action' => 'viewFields', 'setid' => $fieldsetID]));
    }

    $page->output_confirm_action(
        urlHandlerBuild(['action' => 'deleteField', 'fid' => $fieldID, 'setid' => $fieldsetID]),
        $lang->sprintf($lang->myShowcaseAdminConfirmFieldDelete, $fieldName)
    );
} else {
    $page->output_header($lang->myshowcase_admin_fields);

    $page->output_nav_tabs($pageTabs, 'myShowcaseAdminFieldSets');

    hooksRun('admin_fields_start');

    $formContainer = new FormContainer($lang->myshowcase_fields_title);

    $formContainer->output_row_header($lang->myshowcase_fields_id, ['width' => '1%', 'class' => 'align_center']);

    $formContainer->output_row_header($lang->myshowcase_fields_name, ['width' => '15%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_fields_count,
        ['class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_fields_assigned_to,
        ['width' => '10%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_fields_used_by,
        ['width' => '10%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_fields_lang_exists,
        ['width' => '10%', 'class' => 'align_center']
    );

    $formContainer->output_row_header($lang->controls, ['width' => '10%', 'class' => 'align_center']);

    $fieldsetObjects = fieldsetGet([], ['setid', 'setname'], ['order_by' => 'setname']);

    if (!$fieldsetObjects) {
        $formContainer->output_cell($lang->myshowcase_fields_no_fieldsets, ['colspan' => 6]);

        $formContainer->construct_row();
    } else {
        foreach ($fieldsetObjects as $fieldsetID => $result) {
            $viewOptionsUrl = urlHandlerBuild(['action' => 'viewFields', 'setid' => $fieldsetID]);

            foreach (showcaseGet(["fieldsetid='{$fieldsetID}'"]) as $showcaseID => $showcaseData) {
                if (showcaseDataTableExists($showcaseID)) {
                    $can_edit = false;
                }
            }

            $totalUsedOn = showcaseGet(
                ["fieldsetid='{$fieldsetID}'"],
                ['COUNT(id) AS totalUsedOn'],
                ['limit' => 1, 'group_by' => 'id']
            )['totalUsedOn'] ?? 0;

            $totalFields = fieldsGet(
                ["setid='{$fieldsetID}'"],
                ['COUNT(fid) AS totalFields'],
                ['limit' => 1, 'group_by' => 'fid']
            )['totalFields'] ?? 0;

            $totalTablesUsedOn = 0;

            foreach (showcaseGet(["fieldsetid='{$fieldsetID}'"]) as $showcaseID => $showcaseData) {
                if (showcaseDataTableExists($showcaseID)) {
                    ++$totalTablesUsedOn;
                }
            }

            // Build popup menu
            $popup = new PopupMenu("fieldset_{$fieldsetID}", $lang->options);

            $popup->add_item(
                $lang->edit,
                urlHandlerBuild(['action' => 'edit', 'setid' => $fieldsetID]),
            );

            $popup->add_item(
                'View Fields',
                $viewOptionsUrl,
            );

            $popup->add_item(
                $lang->delete,
                urlHandlerBuild(['action' => 'delete', 'setid' => $fieldsetID]),
            );

            //grab status images for language file
            $languageFile = $languagePath . '/myshowcase_fs' . $fieldsetID . '.lang.php';

            if (file_exists($languageFile)) {
                if (is_writable($languageFile)) {
                    $statusImage = "styles/{$page->style}/images/icons/tick.png";

                    $statusText = $lang->myshowcase_fields_lang_exists_yes;
                } else {
                    $statusImage = "styles/{$page->style}/images/icons/warning.png";

                    $statusText = $lang->myshowcase_fields_lang_exists_write;
                }
            } else {
                $statusImage = "styles/{$page->style}/images/icons/error.png";

                $statusText = $lang->myshowcase_fields_lang_exists_no;
            }

            $formContainer->output_cell($fieldsetID, ['class' => 'align_center']);

            $formContainer->output_cell(
                "<a href='{$viewOptionsUrl}'>{$result['setname']}</a>",
            );

            $formContainer->output_cell($totalFields, ['class' => 'align_center']);

            $formContainer->output_cell($totalUsedOn, ['class' => 'align_center']);

            $formContainer->output_cell($totalTablesUsedOn, ['class' => 'align_center']);

            $formContainer->output_cell(
                '<img src="' . $statusImage . '" title="' . $statusText . '">',
                ['class' => 'align_center']
            );

            $formContainer->output_cell($popup->fetch(), ['class' => 'align_center']);

            $formContainer->construct_row();
        }
    }

    hooksRun('admin_fields_end');

    $formContainer->end();

    $page->output_footer();
}