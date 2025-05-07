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

use MyShowcase\System\FieldHtmlTypes;

use function MyShowcase\Admin\buildDbFieldDefinition;
use function MyShowcase\Admin\languageModify;
use function MyShowcase\Core\cacheGet;
use function MyShowcase\Core\cacheUpdate;
use function MyShowcase\Core\dataTableStructureGet;
use function MyShowcase\Core\fieldDataDelete;
use function MyShowcase\Core\fieldDataGet;
use function MyShowcase\Core\fieldDataInsert;
use function MyShowcase\Core\fieldDataUpdate;
use function MyShowcase\Core\fieldDefaultTypes;
use function MyShowcase\Core\fieldHtmlTypes;
use function MyShowcase\Core\fieldsDelete;
use function MyShowcase\Core\fieldsetDelete;
use function MyShowcase\Core\fieldsetGet;
use function MyShowcase\Core\fieldsetInsert;
use function MyShowcase\Core\fieldsetUpdate;
use function MyShowcase\Core\fieldsGet;
use function MyShowcase\Core\fieldsInsert;
use function MyShowcase\Core\fieldsUpdate;
use function MyShowcase\Core\fieldTypesGet;
use function MyShowcase\Core\formatTypes;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\Core\loadLanguage;
use function MyShowcase\Core\showcaseDataTableExists;
use function MyShowcase\Core\showcaseDataTableFieldDrop;
use function MyShowcase\Core\showcaseDataTableFieldExists;
use function MyShowcase\Core\showcaseDataTableFieldRename;
use function MyShowcase\Core\showcaseGet;
use function MyShowcase\Core\urlHandlerBuild;
use function MyShowcase\Core\urlHandlerSet;

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

$fieldID = $mybb->get_input('field_id', MyBB::INPUT_INT);

$fieldsetID = $mybb->get_input('set_id', MyBB::INPUT_INT);

$fieldOptionValue = $mybb->get_input('value_id', MyBB::INPUT_INT);

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
        'link' => urlHandlerBuild(['action' => 'viewFields', 'set_id' => $fieldsetID]),
        'description' => $lang->myShowcaseAdminFieldsDescription
    ];

    if (in_array($pageAction, ['editOption'])) {
        $pageTabs['myShowcaseAdminFieldsOptions'] = [
            'title' => $lang->myShowcaseAdminFieldsOptions,
            'link' => urlHandlerBuild(['action' => 'editOption', 'set_id' => $fieldsetID, 'field_id' => $fieldID]),
            'description' => $lang->myShowcaseAdminFieldsOptionsDescription
        ];
    } else {
        $pageTabs['myShowcaseAdminFieldsNew'] = [
            'title' => $lang->myShowcaseAdminFieldsNew,
            'link' => urlHandlerBuild(['action' => 'newField', 'set_id' => $fieldsetID]),
            'description' => $lang->myShowcaseAdminFieldsNewDescription
        ];

        if ($pageAction == 'editField') {
            $pageTabs['myShowcaseAdminFieldsEdit'] = [
                'title' => $lang->myShowcaseAdminFieldsEdit,
                'link' => urlHandlerBuild(['action' => 'editField', 'set_id' => $fieldsetID, 'field_id' => $fieldID]),
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
            'link' => urlHandlerBuild(['action' => 'edit', 'set_id' => $fieldsetID]),
            'description' => $lang->myShowcaseAdminFieldSetsEditDescription
        ];
    }
}

if (in_array($pageAction, ['newField', 'editField'])) {
    $newPage = $pageAction === 'newField';

    hooksRun('admin_field_new_edit_start');

    $fieldsetData = fieldsetGet(["set_id='{$fieldsetID}'"], ['set_name'], ['limit' => 1]);

    if (!$newPage && empty($fieldsetData)) {
        flash_message($lang->myShowcaseAdminErrorInvalidFieldset, 'error');

        admin_redirect($pageTabs['myShowcaseAdminFieldSets']['link']);
    }

    $queryFields = TABLES_DATA['myshowcase_fields'];

    unset($queryFields['unique_keys']);

    $fieldData = fieldsGet(["field_id='{$fieldID}'"], array_keys($queryFields), ['limit' => 1]);

    if (!$newPage && empty($fieldData)) {
        flash_message($lang->myShowcaseAdminErrorInvalidField, 'error');

        admin_redirect(urlHandlerBuild(['action' => 'viewFields', 'set_id' => $fieldsetID]));
    }

    $page->output_header($pageTabs[$newPage ? 'myShowcaseAdminFieldsNew' : 'myShowcaseAdminFieldsEdit']['title']);

    $page->output_nav_tabs($pageTabs, $newPage ? 'myShowcaseAdminFieldsNew' : 'myShowcaseAdminFieldsEdit');

    $canEditLanguageFile = true;

    if (!is_writable($languagePath) ||
        (!is_writable($languagePath . '/myshowcase_fs' . $fieldsetID . '.lang.php') &&
            file_exists($languagePath . '/myshowcase_fs' . $fieldsetID . '.lang.php'))) {
        $canEditLanguageFile = false;
    }

    $fieldIsInUse = false;

    $showcaseObjects = showcaseGet(["field_set_id='{$fieldsetID}'"]);

    if (!$newPage && $showcaseObjects) {
        foreach ($showcaseObjects as $showcaseID => $showcaseData) {
            if (!showcaseDataTableExists($showcaseID)) {
                break;
            }

            if (showcaseDataTableFieldExists($showcaseID, $fieldData['field_key'])) {
                $fieldIsInUse = true;

                break;
            }
        }
    }

    if ($mybb->request_method === 'post') {
        if ($mybb->get_input('allowed_groups_fill_type') === 'all') {
            $mybb->input['allowed_groups_fill'] = [-1];
        } elseif ($mybb->get_input('allowed_groups_fill_type') !== 'custom') {
            $mybb->input['allowed_groups_fill'] = [];
        }

        if ($mybb->get_input('allowed_groups_view_type') === 'all') {
            $mybb->input['allowed_groups_view'] = [-1];
        } elseif ($mybb->get_input('allowed_groups_view_type') !== 'custom') {
            $mybb->input['allowed_groups_view'] = [];
        }

        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            flash_message($lang->myShowcaseAdminErrorInvalidPostKey, 'error');

            admin_redirect(urlHandlerBuild());
        }

        $errorMessages = [];

        $mybb->input['field_key'] = trim(
            my_strtolower(
                preg_replace(
                    '#[^\w]#',
                    '_',
                    $mybb->get_input('field_key')
                )
            ),
            '_'
        );

        if (!$mybb->get_input('field_key')) {
            $errorMessages[] = $lang->myShowcaseAdminErrorMissingRequiredFields;
        }

        if ($mybb->get_input('minimum_length', MyBB::INPUT_INT) >
            $mybb->get_input('maximum_length', MyBB::INPUT_INT)) {
            $errorMessages[] = $lang->myShowcaseAdminErrorInvalidMinMax;
        }

        $existingFields = cacheGet(CACHE_TYPE_FIELDS)[$fieldsetID] ?? [];

        if (in_array($mybb->get_input('field_key'), array_column($existingFields, 'field_key')) &&
            (function (string $fieldKey) use ($fieldsetID, $existingFields): bool {
                $duplicatedName = false;

                foreach ($existingFields as $fieldData) {
                    if ($fieldData['field_key'] === $fieldKey && $fieldData['set_id'] !== $fieldsetID) {
                        $duplicatedName = true;
                    }
                }

                return $duplicatedName;
            })(
                $mybb->get_input('field_key')
            )) {
            $errorMessages[] = $lang->myShowcaseAdminErrorDuplicatedName;
        }

        $fieldDefaultOption = 0;

        /*switch ($mybb->get_input('html_type')) {
            case \MyShowcase\System\FieldHtmlTypes::SelectSingle:
                $mybb->input['field_type'] = \MyShowcase\System\FieldTypes::Integer;

                $mybb->input['maximum_length'] = 3;

                $fieldDefaultOption = 1;
                break;
            case \MyShowcase\System\FieldHtmlTypes::Radio:
                $mybb->input['field_type'] = \MyShowcase\System\FieldTypes::Integer;

                $mybb->input['maximum_length'] = 1;

                $fieldDefaultOption = 1;
                break;
            case \MyShowcase\System\FieldHtmlTypes::CheckBox:
                $mybb->input['field_type'] = \MyShowcase\System\FieldTypes::Integer;

                $mybb->input['maximum_length'] = 1;
                break;
            case \MyShowcase\System\FieldHtmlTypes::Date:
                $mybb->input['field_type'] = \MyShowcase\System\FieldTypes::VarChar;

                $mybb->input['minimum_length'] = max(
                    $mybb->get_input('minimum_length', MyBB::INPUT_INT),
                    1901
                );

                $mybb->input['maximum_length'] = min(
                    $mybb->get_input('maximum_length', MyBB::INPUT_INT),
                    2038
                );

                break;
            case \MyShowcase\System\FieldHtmlTypes::Url:
                $mybb->input['field_type'] = \MyShowcase\System\FieldTypes::VarChar;

                $mybb->input['maximum_length'] = 255;
                break;
        }*/

        $insertData = [
            'set_id' => $fieldsetID,
            'html_type' => $mybb->get_input('html_type'),
            //'enabled' => $mybb->get_input('enabled', MyBB::INPUT_INT),
            'field_type' => $mybb->get_input('field_type'),
            'display_in_create_update_page' => $mybb->get_input('display_in_create_update_page', MyBB::INPUT_INT),
            'display_in_view_page' => $mybb->get_input('display_in_view_page', MyBB::INPUT_INT),
            'display_in_main_page' => $mybb->get_input('display_in_main_page', MyBB::INPUT_INT),
            'minimum_length' => $mybb->get_input('minimum_length', MyBB::INPUT_INT),
            'maximum_length' => $mybb->get_input('maximum_length', MyBB::INPUT_INT),
            'is_required' => $mybb->get_input('is_required', MyBB::INPUT_INT),
            'allowed_groups_fill' => $mybb->get_input('allowed_groups_fill', MyBB::INPUT_ARRAY),
            'allowed_groups_view' => $mybb->get_input('allowed_groups_view', MyBB::INPUT_ARRAY),
            'default_value' => $mybb->get_input('default_value'),
            'default_type' => $mybb->get_input('default_type'),
            //'parse' => $mybb->get_input('parse', MyBB::INPUT_INT),
            'display_order' => $mybb->get_input('display_order', MyBB::INPUT_INT),
            'render_order' => $mybb->get_input('render_order', MyBB::INPUT_INT),
            //'enable_search' => $mybb->get_input('enable_search', MyBB::INPUT_INT),
            'enable_slug' => $mybb->get_input('enable_slug', MyBB::INPUT_INT),
            'enable_subject' => $mybb->get_input('enable_subject', MyBB::INPUT_INT),
            'format' => $mybb->get_input('format'),
            'enable_editor' => $mybb->get_input('enable_editor', MyBB::INPUT_INT),
        ];

        $keyUpdate = !$newPage && $mybb->get_input('field_key') !== $fieldData['field_key'];

        if ($keyUpdate) {
            $insertData['field_key'] = $mybb->get_input('field_key');
        }

        if ($errorMessages) {
            $page->output_inline_error($errorMessages);
        } else {
            $insertData = hooksRun('admin_field_new_edit_post', $insertData);

            if ($newPage) {
                $fieldID = fieldsInsert($insertData);
            } else {
                if ($fieldIsInUse && $insertData['field_key'] !== $fieldData['field_key']) {
                    foreach (showcaseGet(["field_set_id='{$fieldsetID}'"]) as $showcaseID => $showcaseData) {
                        if (showcaseDataTableExists($showcaseID)) {
                            $dataTableStructure = dataTableStructureGet($showcaseID);

                            if (showcaseDataTableFieldExists($showcaseID, $fieldData['field_key'])) {
                                showcaseDataTableFieldRename(
                                    $showcaseID,
                                    $fieldData['field_key'],
                                    $insertData['field_key'],
                                    buildDbFieldDefinition($dataTableStructure[$fieldData['field_key']])
                                );
                            }
                        }
                    }
                }

                fieldsUpdate($insertData, $fieldID);

                if (isset($fieldData['field_key']) &&
                    in_array(
                        $fieldData['html_type'],
                        [FieldHtmlTypes::SelectSingle, FieldHtmlTypes::Radio]
                    )) {
                    fieldDataUpdate(
                        ["field_id='{$fieldID}'", "set_id='{$fieldsetID}'"],
                        ['field_key' => $fieldData['field_key']]
                    );
                }
            }

            $fieldOptionID = 0;

            if ($fieldDefaultOption) {
                $fieldOptionData = [
                    'set_id' => $fieldsetID,
                    'field_id' => $fieldID,
                    'field_key' => $db->escape_string($mybb->get_input('field_key')),
                    'value' => 'Not Specified'
                ];

                $fieldOptionID = fieldDataInsert($fieldOptionData);
            }

            if ($canEditLanguageFile) {
                languageModify(
                    'myshowcase_fs' . $fieldsetID,
                    [
                        'myshowcase_field_' . ($insertData['field_key'] ?? $fieldData['field_key']) => ucfirst(
                            $mybb->get_input('label') ?? $mybb->get_input('field_key')
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

            admin_redirect(urlHandlerBuild(['action' => 'viewFields', 'set_id' => $fieldsetID]));
        }
    } elseif (!empty($fieldData)) {
        foreach (['allowed_groups_fill', 'allowed_groups_view'] as $settingKey) {
            $mybb->input[$settingKey] = explode(',', $fieldData[$settingKey] ?? '');
        }
    }

    $mybb->input['allowed_groups_fill'] = array_filter(array_map('intval', $mybb->input['allowed_groups_fill']));

    $mybb->input['allowed_groups_view'] = array_filter(array_map('intval', $mybb->input['allowed_groups_view']));

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
        $lang->myShowcaseAdminFieldsNewFormKey . '<em>*</em>',
        $lang->myShowcaseAdminFieldsNewFormKeyDescription,
        $form->generate_text_box(
            'field_key',
            $mybb->get_input('field_key'),
            ['id' => 'field_key']
        ),
        'field_key'
    );

    if (!$newPage) {
        include_once $languagePath . '/myshowcase_fs' . $fieldsetID . '.lang.php';

        $mybb->input['label'] = $l['myshowcase_field_' . $mybb->get_input('field_key')] ?? $mybb->get_input('label');
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
            fieldHtmlTypes(),
            $mybb->get_input('html_type'),
            ['id' => 'html_type']
        ),
        'html_type'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormFieldType,
        $lang->myShowcaseAdminFieldsNewFormFieldTypeDescription . ($fieldIsInUse ? $lang->myShowcaseAdminFieldsNewFormFieldTypeExisting : ''),
        $form->generate_select_box(
            'field_type',
            fieldTypesGet(),
            $mybb->get_input('field_type'),
            ['id' => 'field_type']
        ),
        'field_type'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormDisplayInCreateUpdatePage,
        $lang->myShowcaseAdminFieldsNewFormDisplayInCreateUpdatePageDescription,
        $form->generate_yes_no_radio(
            'display_in_create_update_page',
            $mybb->get_input('display_in_create_update_page', MyBB::INPUT_INT)
        ),
        'display_in_create_update_page'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormDisplayInViewPage,
        $lang->myShowcaseAdminFieldsNewFormDisplayInViewPageDescription,
        $form->generate_yes_no_radio(
            'display_in_view_page',
            $mybb->get_input('display_in_view_page', MyBB::INPUT_INT)
        ),
        'display_in_view_page'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormDisplayInMainPage,
        $lang->myShowcaseAdminFieldsNewFormDisplayInMainPageDescription,
        $form->generate_yes_no_radio(
            'display_in_main_page',
            $mybb->get_input('display_in_main_page', MyBB::INPUT_INT)
        ),
        'display_in_main_page'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormDefaultValue,
        $lang->myShowcaseAdminFieldsNewFormDefaultValueDescription,
        $form->generate_text_box(
            'default_value',
            $mybb->get_input('default_value'),
            ['id' => 'default_value']
        ),
        'default_value'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormDefaultType,
        $lang->myShowcaseAdminFieldsNewFormDefaultTypeDescription,
        $form->generate_select_box(
            'default_type',
            fieldDefaultTypes(),
            $mybb->get_input('default_type', MyBB::INPUT_INT),
            ['id' => 'default_type']
        ),
        'default_type'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormMinimumLength,
        $lang->myShowcaseAdminFieldsNewFormMinimumLengthDescription,
        $form->generate_numeric_field(
            'minimum_length',
            $mybb->get_input('minimum_length'),
            ['id' => 'minimum_length', 'class' => 'field150', 'min' => 0],
        ),
        'minimum_length'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormMaximumLength,
        $lang->myShowcaseAdminFieldsNewFormMaximumLengthDescription,
        $form->generate_numeric_field(
            'maximum_length',
            $mybb->get_input('maximum_length'),
            ['id' => 'maximum_length', 'class' => 'field150', 'min' => 0],
        ),
        'maximum_length'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormRequired,
        $lang->myShowcaseAdminFieldsNewFormRequiredDescription,
        $form->generate_yes_no_radio(
            'is_required',
            $mybb->get_input('is_required', MyBB::INPUT_INT)
        ),
        'is_required'
    );

    $groupsSelectFunction = function (string $settingKey) use ($mybb, $lang, $form): string {
        $selectedValues = $mybb->get_input($settingKey, MyBB::INPUT_ARRAY);

        $groupChecked = ['all' => '', 'custom' => '', 'none' => ''];

        if (in_array(-1, $selectedValues)) {
            $groupChecked['all'] = 'checked="checked"';
        } elseif (count($selectedValues) > 0) {
            $groupChecked['custom'] = 'checked="checked"';
        } else {
            $groupChecked['none'] = 'checked="checked"';
        }

        print_selection_javascript();

        $selectField = <<<EOL
				<dl style="margin-top: 0; margin-bottom: 0; width: 100%">
					<dt>
                        <label style="display: block;">
                            <input type="radio" name="{$settingKey}_type" value="all" {$groupChecked['all']} class="{$settingKey}_forums_groups_check" onclick="checkAction('{$settingKey}');" style="vertical-align: middle;" />
                            <strong>{$lang->all_groups}</strong>
                        </label>
					</dt>
					<dt>
                        <label style="display: block;">
                            <input type="radio" name="{$settingKey}_type" value="custom" {$groupChecked['custom']} class="{$settingKey}_forums_groups_check" onclick="checkAction('{$settingKey}');" style="vertical-align: middle;" />
                            <strong>{$lang->select_groups}</strong>
                        </label>
					</dt>
					<dd style="margin-top: 4px;" id="{$settingKey}_forums_groups_custom" class="{$settingKey}_forums_groups">
						<table cellpadding="4">
							<tr>
								<td valign="top"><small>{$lang->groups_colon}</small></td>
								<td>{$form->generate_group_select(
            "{$settingKey}[]",
            $selectedValues,
            ['id' => $settingKey, 'multiple' => true, 'size' => 5]
        )}</td>
							</tr>
						</table>
					</dd>
					<dt>
                        <label style="display: block;"><input type="radio" name="{$settingKey}_type" value="none" {$groupChecked['none']} class="{$settingKey}_forums_groups_check" onclick="checkAction('{$settingKey}');" style="vertical-align: middle;" /> 
                        <strong>{$lang->none}</strong></label>
					</dt>
				</dl>
				<script type="text/javascript">
					checkAction('{$settingKey}');
				</script>
EOL;

        return $selectField;
    };

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewAllowedGroupsFill,
        $lang->myShowcaseAdminFieldsNewAllowedGroupsFillDescription,
        $groupsSelectFunction('allowed_groups_fill'),
        'allowed_groups_fill'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormAllowedGroupsView,
        $lang->myShowcaseAdminFieldsNewFormAllowedGroupsViewDescription,
        $groupsSelectFunction('allowed_groups_view'),
        'allowed_groups_view'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormOrderList,
        $lang->myShowcaseAdminFieldsNewFormOrderListDescription,
        $form->generate_numeric_field(
            'render_order',
            $mybb->get_input('render_order'),
            ['id' => 'render_order', 'class' => 'align_center field50', 'min' => 0],
        ),
        'render_order'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormEnableSubject,
        $lang->myShowcaseAdminFieldsNewFormEnableSubjectDescription,
        $form->generate_yes_no_radio(
            'enable_subject',
            $mybb->get_input('enable_subject', MyBB::INPUT_INT)
        ),
        'enable_subject'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormEnableSlug,
        $lang->myShowcaseAdminFieldsNewFormEnableSlugDescription,
        $form->generate_yes_no_radio(
            'enable_slug',
            $mybb->get_input('enable_slug', MyBB::INPUT_INT)
        ),
        'enable_slug'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormFormat,
        $lang->myShowcaseAdminFieldsNewFormFormatDescription,
        $form->generate_select_box(
            'format',
            (function (): array {
                $selectOptions = [];

                foreach (formatTypes() as $formatTypeKey => $formatTypeName) {
                    $selectOptions[$formatTypeKey] = $formatTypeName;
                }

                return $selectOptions;
            })(),
            $mybb->get_input('format'),
            ['id' => 'format']
        ),
        'format'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminFieldsNewFormEnableEditor,
        $lang->myShowcaseAdminFieldsNewFormEnableEditorDescription,
        $form->generate_yes_no_radio(
            'enable_editor',
            $mybb->get_input('enable_editor', MyBB::INPUT_INT)
        ),
        'enable_editor'
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

    $fieldsetData = fieldsetGet(["set_id='{$fieldsetID}'"], ['set_name'], ['limit' => 1]);

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

        if (!$mybb->get_input('set_name')) {
            $errorMessages[] = $lang->myShowcaseAdminErrorMissingRequiredFields;
        }

        $existingFieldSets = cacheGet(CACHE_TYPE_FIELD_SETS);

        if (in_array($mybb->get_input('set_name'), array_column($existingFieldSets, 'set_name')) && (function (
                string $showcaseName
            ) use ($fieldsetID, $existingFieldSets): bool {
                $duplicatedName = false;

                foreach ($existingFieldSets as $fieldsetData) {
                    if ($fieldsetData['set_name'] === $showcaseName && $fieldsetData['set_id'] !== $fieldsetID) {
                        $duplicatedName = true;
                    }
                }

                return $duplicatedName;
            })(
                $mybb->get_input('set_name')
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
            'set_name' => $db->escape_string($mybb->get_input('set_name')),
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
        $form->generate_text_box('set_name', $mybb->get_input('set_name'), ['id' => 'set_name']),
        'set_name'
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
    $fieldsetData = fieldsetGet(["set_id='{$fieldsetID}'"], ['set_name'], ['limit' => 1]);

    if (empty($fieldsetData)) {
        flash_message($lang->myshowcase_fields_invalid_id, 'error');

        admin_redirect(urlHandlerBuild());
    }

    $page->add_breadcrumb_item(
        $lang->sprintf($lang->myshowcase_admin_edit_fieldset, $fieldsetData['set_name']),
        urlHandlerBuild()
    );

    $page->output_header($lang->myshowcase_admin_fields);

    $page->output_nav_tabs($pageTabs, 'myShowcaseAdminFields');

    hooksRun('admin_view_fields_start');

    if ($mybb->request_method === 'post') {
        foreach (fieldsGet(["set_id='{$fieldsetID}'"]) as $fieldID => $insertData) {
            $insertData = [
                'display_order' => (int)($mybb->get_input('display_order', MyBB::INPUT_ARRAY)[$fieldID] ?? 0),
                'enabled' => (int)!empty($mybb->get_input('enabled', MyBB::INPUT_ARRAY)[$fieldID]),
                'parse' => (int)!empty($mybb->get_input('parse', MyBB::INPUT_ARRAY)[$fieldID]),
                'enable_search' => (int)!empty($mybb->get_input('enable_search', MyBB::INPUT_ARRAY)[$fieldID]),
            ];

            $insertData = hooksRun('admin_view_fields_update_post', $insertData);

            fieldsUpdate($insertData, $fieldID);
        }

        flash_message($lang->myshowcase_field_update_success, 'success');

        admin_redirect(urlHandlerBuild(['action' => 'viewFields', 'set_id' => $fieldsetID]));
    }

    $form = new Form(
        urlHandlerBuild(['action' => 'viewFields', 'set_id' => $fieldsetID]),
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

    unset($queryFields['unique_keys']);

    $fieldObjects = fieldsGet(["set_id='{$fieldsetID}'"], array_keys($queryFields), ['order_by' => 'display_order']);

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

            if ($fieldData['html_type'] == FieldHtmlTypes::SelectSingle || $fieldData['html_type'] == FieldHtmlTypes::Radio) {
                $viewOptionsUrl = urlHandlerBuild(
                    ['action' => 'editOption', 'field_id' => $fieldID, 'set_id' => $fieldsetID]
                );
            }

            $formContainer->output_cell(
                $viewOptionsUrl ? "<a href='{$viewOptionsUrl}'>{$fieldData['field_key']}</a>" : $fieldData['field_key'],
                ['class' => 'align_left']
            );

            $formContainer->output_cell(
                $l['myshowcase_field_' . $fieldData['field_key']] ?? '',
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
                    "display_order[{$fieldID}]",
                    $fieldData['display_order'],
                    ['id' => "display_order[{$fieldID}]", 'class' => 'align_center field50', 'min' => 0],
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
                    'enable_search[' . $fieldID . ']',
                    'true',
                    '',
                    ['checked' => $fieldData['enable_search']],
                    ''
                ),
                ['class' => 'align_center']
            );

            $popup = new PopupMenu("field_{$fieldID}", $lang->options);

            $popup->add_item(
                $lang->edit,
                urlHandlerBuild(['action' => 'editField', 'field_id' => $fieldID, 'set_id' => $fieldsetID]),
            );

            $popup->add_item(
                $lang->myshowcase_field_delete,
                urlHandlerBuild(['action' => 'deleteField', 'field_id' => $fieldID, 'set_id' => $fieldsetID]),
            );

            //add option to edit list items if db type
            if ($fieldData['html_type'] == FieldHtmlTypes::SelectSingle || $fieldData['html_type'] == FieldHtmlTypes::Radio) {
                $popup->add_item(
                    $lang->myshowcase_field_edit_options,
                    urlHandlerBuild(['action' => 'editOption', 'field_id' => $fieldID, 'set_id' => $fieldsetID]),
                );
            }

            $maximumViewOrder = max($maximumViewOrder, $fieldData['display_order']);

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
    $fieldsetData = fieldsetGet(["set_id='{$fieldsetID}'"], ['set_name'], ['limit' => 1]);

    if (!$fieldsetData) {
        flash_message($lang->myShowcaseAdminErrorInvalidFieldset, 'error');

        admin_redirect(urlHandlerBuild());
    }

    $showcaseObjects = showcaseGet(["field_set_id='{$fieldsetID}'"], [], ['limit' => 1]);

    if (!empty($showcaseObjects)) {
        flash_message($lang->myShowcaseAdminErrorFieldsetDeleteFailed, 'error');

        admin_redirect(urlHandlerBuild());
    }

    hooksRun('admin_fieldset_delete_start');

    $fieldsetName = $fieldsetData['set_name'];

    if ($mybb->request_method === 'post') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            flash_message($lang->myShowcaseAdminErrorInvalidPostKey, 'error');

            admin_redirect(urlHandlerBuild());
        }

        if (isset($mybb->input['no'])) {
            admin_redirect(urlHandlerBuild());
        }

        hooksRun('admin_fieldset_delete_post');

        fieldsDelete(["set_id='{$fieldsetID}'"]);

        fieldDataDelete(["set_id='{$fieldsetID}'"]);

        fieldsetDelete(["set_id='{$fieldsetID}'"]);

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

        if (fieldsetGet(["set_id='{$fieldsetID}'"])) {
            flash_message($lang->myShowcaseAdminErrorFieldsetDelete, 'error');
        } else {
            flash_message($lang->myShowcaseAdminSuccessFieldsetDeleted, 'success');
        }

        admin_redirect(urlHandlerBuild());
    }

    $page->output_confirm_action(
        urlHandlerBuild(['action' => 'delete', 'set_id' => $fieldsetID]),
        $lang->sprintf($lang->myShowcaseAdminConfirmFieldsetDelete, $fieldsetName)
    );
} elseif ($pageAction == 'editOption') {
    $fieldData = fieldsGet(
        ["field_id='{$fieldID}'", "set_id='{$fieldsetID}'", "html_type In ('db', 'radio')"],
        ['field_key'],
        ['limit' => 1]
    );

    if (!$fieldData) {
        flash_message($lang->myshowcase_field_invalid_id, 'error');

        admin_redirect(urlHandlerBuild(['action' => 'viewFields', 'set_id' => $fieldsetID]));
    }

    $can_edit = true;

    foreach (showcaseGet(["field_set_id='{$fieldsetID}'"]) as $showcaseID => $showcaseData) {
        if (showcaseDataTableExists($showcaseID)) {
            $can_edit = false;
        }
    }

    $fieldOptionData = fieldsetGet(["set_id='{$fieldsetID}'"], ['set_name'], ['limit' => 1]);

    $page->add_breadcrumb_item(
        $lang->sprintf($lang->myshowcase_admin_edit_fieldopt, $fieldData['field_key'], $fieldOptionData['set_name']),
        urlHandlerBuild()
    );

    $page->output_header($lang->myshowcase_admin_fields);

    $page->output_nav_tabs($pageTabs, 'myShowcaseAdminFieldsOptions');

    hooksRun('admin_option_edit_start');

    //user clicked Save button
    if ($mybb->request_method === 'post') {
        //apply changes to existing fields first
        $fieldOptionObjects = fieldDataGet(
            ["set_id='{$fieldsetID}'", "field_id='{$fieldID}'"],
            ['field_data_id']
        );

        $valueInput = $mybb->get_input('value', MyBB::INPUT_ARRAY);

        $displayStyleInput = $mybb->get_input('display_style', MyBB::INPUT_ARRAY);

        $displayOrderInput = $mybb->get_input('display_order', MyBB::INPUT_ARRAY);

        foreach ($fieldOptionObjects as $fieldOptionID => $fieldOptionData) {
            $fieldDataID = (int)$fieldOptionData['field_data_id'];

            $updateData = [];

            if (isset($valueInput[$fieldDataID])) {
                $updateData['value'] = $valueInput[$fieldDataID];
            }

            if (isset($displayStyleInput[$fieldDataID])) {
                $updateData['display_style'] = $displayStyleInput[$fieldDataID];
            }

            if (isset($displayOrderInput[$fieldDataID])) {
                $updateData['display_order'] = $displayOrderInput[$fieldDataID];
            }

            fieldDataUpdate(
                ["field_data_id='{$fieldDataID}'"],
                $updateData
            );
        }

        if ($mybb->get_input('value') &&
            $fieldOptionValue &&
            $mybb->get_input('display_order', MyBB::INPUT_INT)) {
            $fieldOptionData = [
                'set_id' => $fieldsetID,
                'field_id' => $fieldID,
                'field_key' => $db->escape_string($mybb->get_input('field_key')),
                'value' => $db->escape_string($mybb->get_input('value')),
                'display_style' => $db->escape_string($mybb->get_input('display_style')),
                'value_id' => $fieldOptionValue,
                'display_order' => $mybb->get_input('display_order', MyBB::INPUT_INT)
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
            urlHandlerBuild(['action' => 'editOption', 'set_id' => $fieldsetID, 'field_id' => $fieldID]),
        );
    }

    $form = new Form(
        urlHandlerBuild(['action' => 'editOption', 'set_id' => $fieldsetID, 'field_id' => $fieldID]),
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
        $lang->myshowcase_field_display_style,
        ['width' => '10%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_field_disporder,
        ['width' => '5%', 'class' => 'align_center']
    );

    $formContainer->output_row_header($lang->controls, ['width' => '10%', 'class' => 'align_center']);

    $maximumViewOrder = 0;

    $fieldOptionObjects = fieldDataGet(
        ["set_id='{$fieldsetID}'", "field_id='{$fieldID}'"],
        ['value_id', 'value', 'display_style', 'display_order', 'field_data_id'],
        ['order_by' => 'value_id']
    );

    if (!$fieldOptionObjects) {
        $formContainer->output_cell(
            $lang->myshowcase_field_no_options,
            ['class' => 'align_center', 'colspan' => 6]
        );

        $formContainer->construct_row();
    } else {
        $maximumViewOrder = 1;

        $maximumViewOrder = 0;

        foreach ($fieldOptionObjects as $fieldOptionID => $fieldOptionData) {
            $fieldDataID = (int)$fieldOptionData['field_data_id'];

            $maximumViewOrder = max($maximumViewOrder, $fieldOptionData['display_order']);

            $maximumViewOrder = max($maximumViewOrder, $fieldOptionData['value_id']);

            $formContainer->output_cell(
                my_number_format($fieldDataID),
                ['class' => 'align_center']
            );

            $formContainer->output_cell(
                $form->generate_text_box(
                    "value[{$fieldDataID}]",
                    $fieldOptionData['value'],
                    ['id' => "value[{$fieldDataID}]", 'class' => 'field150']
                ),
                ['class' => 'align_center']
            );

            $formContainer->output_cell(
                $form->generate_text_box(
                    "display_style[{$fieldDataID}]",
                    $fieldOptionData['display_style'],
                    ['id' => "display_style[{$fieldDataID}]", 'class' => 'field250']
                ),
                ['class' => 'align_center']
            );

            $formContainer->output_cell(
                $form->generate_numeric_field(
                    "display_order[{$fieldDataID}]",
                    $fieldOptionData['display_order'],
                    ['id' => "display_order[{$fieldDataID}]", 'class' => 'align_center field50', 'min' => 0]
                ),
                ['class' => 'align_center']
            );

            $popup = new PopupMenu("field_{$fieldDataID}", $lang->options);

            if ($can_edit) {
                $popup->add_item(
                    $lang->myshowcase_field_delete,
                    urlHandlerBuild([
                        'action' => 'deleteOption',
                        'field_data_id' => $fieldDataID
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
        urlHandlerBuild(['action' => 'editOption', 'set_id' => $fieldsetID, 'field_id' => $fieldID]),
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

    echo $form->generate_hidden_field('field_key', $fieldData['field_key']);

    echo $form->generate_hidden_field('value_id', ++$maximumViewOrder);

    $formContainer->output_cell(
        $form->generate_numeric_field(
            'display_order',
            ++$maximumViewOrder,
            ['id' => 'display_order', 'class' => 'align_center field50', 'min' => 0]
        ),
        ['class' => 'align_center']
    );

    $formContainer->construct_row();

    hooksRun('admin_option_edit_end');

    $formContainer->end();

    $form->output_submit_wrapper([$form->generate_submit_button($lang->myshowcase_fields_save_changes)]);

    $form->end();
} elseif ($pageAction == 'deleteOption') {
    $fieldDataID = $mybb->get_input('field_data_id', MyBB::INPUT_INT);

    $fieldsetData = fieldsetGet(["field_data_id='{$fieldDataID}'"], ['set_name'], ['limit' => 1]);

    if (!$fieldsetData) {
        flash_message($lang->myShowcaseAdminErrorInvalidFieldset, 'error');

        admin_redirect(urlHandlerBuild());
    }

    $fieldOptionData = fieldDataGet(["field_data_id='{$fieldDataID}'"], ['field_key']);

    if (!$fieldOptionData) {
        flash_message($lang->myshowcase_field_invalid_opt, 'error');

        admin_redirect(
            urlHandlerBuild(['action' => 'editOption', 'field_data_id' => $fieldDataID]),
        );
    }

    $showcaseObjects = showcaseGet(["field_data_id='{$fieldDataID}'"], [], ['limit' => 1]);

    if (!empty($showcaseObjects)) {
        flash_message($lang->myshowcase_fields_in_use, 'error');

        admin_redirect(
            urlHandlerBuild(['action' => 'editOption', 'field_data_id' => $fieldDataID]),
        );
    }

    hooksRun('admin_option_delete_start');

    if ($mybb->request_method === 'post') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            flash_message($lang->myShowcaseAdminErrorInvalidPostKey, 'error');

            admin_redirect(
                urlHandlerBuild(['action' => 'editOption', 'field_data_id' => $fieldDataID]),
            );
        }

        if (isset($mybb->input['no'])) {
            admin_redirect(
                urlHandlerBuild(['action' => 'editOption', 'field_data_id' => $fieldDataID]),
            );
        }

        hooksRun('admin_option_delete_post');

        fieldDataDelete(["field_data_id='{$fieldDataID}'"]);

        cacheUpdate(CACHE_TYPE_CONFIG);

        cacheUpdate(CACHE_TYPE_FIELDS);

        cacheUpdate(CACHE_TYPE_FIELD_DATA);

        cacheUpdate(CACHE_TYPE_FIELD_SETS);

        log_admin_action(['field_data_id' => $fieldDataID]);

        flash_message($lang->myshowcase_field_delete_opt_success, 'success');

        admin_redirect(
            urlHandlerBuild(['action' => 'editOption', 'field_data_id' => $fieldDataID]),
        );
    }

    $page->output_confirm_action(
        urlHandlerBuild([
            'action' => 'deleteOption',
            'field_data_id' => $fieldDataID
        ]),
        $lang->sprintf($lang->myShowcaseAdminConfirmFieldsetDelete, $fieldsetData['set_name'])
    );
} elseif ($pageAction == 'deleteField') {
    $fieldsetData = fieldsetGet(["set_id='{$fieldsetID}'"], ['set_name'], ['limit' => 1]);

    if (!$fieldsetData) {
        flash_message($lang->myshowcase_fields_invalid_id, 'error');

        admin_redirect(urlHandlerBuild());
    }

    $fieldData = fieldsGet(["set_id='{$fieldsetID}'", "field_id='{$fieldID}'"], ['field_key'], ['limit' => 1]);

    if (empty($fieldData['field_id'])) {
        flash_message($lang->myshowcase_field_invalid_id, 'error');
        admin_redirect(
            urlHandlerBuild(['action' => 'viewFields', 'set_id' => $fieldsetID]),
        );
    }

    $showcaseObjects = showcaseGet(["field_set_id='{$fieldsetID}'"]);

    $fieldKey = $fieldData['field_key'] ?? $fieldData['field_id'];

    $fieldIsInUse = false;

    foreach ($showcaseObjects as $showcaseID => $showcaseData) {
        if (!showcaseDataTableExists($showcaseID)) {
            break;
        }

        if (showcaseDataTableFieldExists($showcaseID, $fieldKey)) {
            $fieldIsInUse = true;

            break;
        }
    }

    hooksRun('admin_field_delete_start');

    if ($mybb->request_method === 'post') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            flash_message($lang->myShowcaseAdminErrorInvalidPostKey, 'error');

            admin_redirect(urlHandlerBuild());
        }

        if (isset($mybb->input['no'])) {
            admin_redirect(urlHandlerBuild(['action' => 'viewFields', 'set_id' => $fieldsetID]));
        }

        hooksRun('admin_field_delete_post');

        languageModify(
            'myshowcase_fs' . $fieldsetID,
            [],
            ['myshowcase_field_' . $fieldKey => '']
        );

        if ($fieldIsInUse) {
            foreach ($showcaseObjects as $showcaseID => $showcaseData) {
                if (!showcaseDataTableExists($showcaseID)) {
                    break;
                }

                if (showcaseDataTableFieldExists($showcaseID, $fieldKey)) {
                    showcaseDataTableFieldDrop($showcaseID, $fieldKey);
                }
            }
        }

        if (fieldDataGet(["set_id='{$fieldsetID}'", "field_id='{$fieldID}'"])) {
            fieldDataDelete(["set_id='{$fieldsetID}'", "field_id='{$fieldID}'"]);
        }

        fieldsDelete(["set_id='{$fieldsetID}'", "field_id='{$fieldID}'"]);

        cacheUpdate(CACHE_TYPE_CONFIG);

        cacheUpdate(CACHE_TYPE_FIELDS);

        cacheUpdate(CACHE_TYPE_FIELD_DATA);

        cacheUpdate(CACHE_TYPE_FIELD_SETS);

        log_admin_action(['fieldsetID' => $fieldsetID, 'fieldID' => $fieldID]);

        if (fieldsGet(["set_id='{$fieldsetID}'", "field_id='{$fieldID}'"])) {
            flash_message($lang->myShowcaseAdminErrorFieldDeleteFailed, 'error');
        } else {
            flash_message($lang->myShowcaseAdminSuccessFieldDeleted, 'success');
        }

        admin_redirect(urlHandlerBuild(['action' => 'viewFields', 'set_id' => $fieldsetID]));
    }

    $page->output_confirm_action(
        urlHandlerBuild(['action' => 'deleteField', 'field_id' => $fieldID, 'set_id' => $fieldsetID]),
        $lang->sprintf(
            $lang->myShowcaseAdminConfirmFieldDelete,
            $fieldKey
        ) . ($fieldIsInUse ? $lang->myShowcaseAdminConfirmFieldDeleteExisting : '')
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

    $fieldsetObjects = fieldsetGet([], ['set_id', 'set_name'], ['order_by' => 'set_name']);

    if (!$fieldsetObjects) {
        $formContainer->output_cell($lang->myshowcase_fields_no_fieldsets, ['colspan' => 6]);

        $formContainer->construct_row();
    } else {
        foreach ($fieldsetObjects as $fieldsetID => $result) {
            $viewOptionsUrl = urlHandlerBuild(['action' => 'viewFields', 'set_id' => $fieldsetID]);

            foreach (showcaseGet(["field_set_id='{$fieldsetID}'"]) as $showcaseID => $showcaseData) {
                if (showcaseDataTableExists($showcaseID)) {
                    $can_edit = false;
                }
            }

            $totalUsedOn = showcaseGet(
                ["field_set_id='{$fieldsetID}'"],
                ['COUNT(showcase_id) AS totalUsedOn'],
                ['limit' => 1, 'group_by' => 'showcase_id']
            )['totalUsedOn'] ?? 0;

            $totalFields = fieldsGet(
                ["set_id='{$fieldsetID}'"],
                ['COUNT(field_id) AS totalFields'],
                ['limit' => 1, 'group_by' => 'field_id']
            )['totalFields'] ?? 0;

            $totalTablesUsedOn = 0;

            foreach (showcaseGet(["field_set_id='{$fieldsetID}'"]) as $showcaseID => $showcaseData) {
                if (showcaseDataTableExists($showcaseID)) {
                    ++$totalTablesUsedOn;
                }
            }

            // Build popup menu
            $popup = new PopupMenu("fieldset_{$fieldsetID}", $lang->options);

            $popup->add_item(
                $lang->edit,
                urlHandlerBuild(['action' => 'edit', 'set_id' => $fieldsetID]),
            );

            $popup->add_item(
                'View Fields',
                $viewOptionsUrl,
            );

            $popup->add_item(
                $lang->delete,
                urlHandlerBuild(['action' => 'delete', 'set_id' => $fieldsetID]),
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
                "<a href='{$viewOptionsUrl}'>{$result['set_name']}</a>",
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