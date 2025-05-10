<?php
/**
 * MyShowcase Plugin for MyBB - Admin module for Summary actions
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \admin\modules\myshowcase\summary.php
 *
 */

declare(strict_types=1);

use MyShowcase\System\ModeratorPermissions;
use MyShowcase\System\UserPermissions;

use function MyShowcase\Admin\dbVerifyTables;
use function MyShowcase\Core\attachmentDelete;
use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\cacheGet;
use function MyShowcase\Core\cacheUpdate;
use function MyShowcase\Core\castTableFieldValue;
use function MyShowcase\Core\cleanSlug;
use function MyShowcase\Core\commentsDelete;
use function MyShowcase\Core\commentsGet;
use function MyShowcase\Core\dataTableStructureGet;
use function MyShowcase\Core\fieldsetGet;
use function MyShowcase\Core\hooksRun;
use function MyShowcase\Core\loadLanguage;
use function MyShowcase\Core\moderatorGet;
use function MyShowcase\Core\moderatorsDelete;
use function MyShowcase\Core\moderatorsInsert;
use function MyShowcase\Core\moderatorsUpdate;
use function MyShowcase\Core\permissionsDelete;
use function MyShowcase\Core\permissionsInsert;
use function MyShowcase\Core\permissionsUpdate;
use function MyShowcase\Core\showcaseDataTableDrop;
use function MyShowcase\Core\showcaseDataTableExists;
use function MyShowcase\Core\showcaseDelete;
use function MyShowcase\Core\showcaseGet;
use function MyShowcase\Core\entryDataGet;
use function MyShowcase\Core\showcaseInsert;
use function MyShowcase\Core\showcaseDefaultPermissions;
use function MyShowcase\Core\showcaseUpdate;
use function MyShowcase\Core\urlHandlerBuild;

use const MyShowcase\Core\TABLES_DATA;
use const MyShowcase\Core\CACHE_TYPE_CONFIG;
use const MyShowcase\Core\CACHE_TYPE_FIELD_SETS;
use const MyShowcase\Core\CACHE_TYPE_MODERATORS;
use const MyShowcase\Core\CACHE_TYPE_PERMISSIONS;
use const MyShowcase\Core\MODERATOR_TYPE_GROUP;
use const MyShowcase\Core\MODERATOR_TYPE_USER;
use const MyShowcase\Core\SHOWCASE_STATUS_DISABLED;
use const MyShowcase\Core\SHOWCASE_STATUS_ENABLED;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

global $lang, $cache, $db, $plugins, $mybb;
global $page;

loadLanguage();

$page->add_breadcrumb_item($lang->myShowcaseSystem, urlHandlerBuild());

$page->add_breadcrumb_item($lang->myShowcaseAdminSummary, urlHandlerBuild());

$showcaseID = $mybb->get_input('showcase_id', MyBB::INPUT_INT);

$pageTabs = [
    'myShowcaseAdminSummary' => [
        'title' => $lang->myShowcaseAdminSummary,
        'link' => urlHandlerBuild(),
        'description' => $lang->myShowcaseAdminSummaryDescription
    ],
    'myShowcaseAdminSummaryNew' => [
        'title' => $lang->myShowcaseAdminSummaryNew,
        'link' => urlHandlerBuild(['action' => 'add']),
        'description' => $lang->myShowcaseAdminSummaryNewDescription
    ],
];

if ($mybb->get_input('action') == 'edit') {
    $pageTabs['myShowcaseAdminSummaryEdit'] = [
        'title' => $lang->myShowcaseAdminSummaryEdit,
        'link' => urlHandlerBuild(['action' => 'edit', 'showcase_id' => $showcaseID]),
        'description' => $lang->myShowcaseAdminSummaryEditDescription
    ];
}

$groupsCache = $cache->read('usergroups') ?? [];

$fieldsetObjects = (function (): array {
    return array_map(function ($fieldsetData) {
        return $fieldsetData['set_name'];
    }, cacheGet(CACHE_TYPE_FIELD_SETS));
})();

hooksRun('admin_summary_start');

if ($mybb->get_input('action') === 'add' || $mybb->get_input('action') === 'edit') {
    $isAddPage = $mybb->get_input('action') === 'add';

    $isEditPage = $mybb->get_input('action') === 'edit';

    if ($isEditPage) {
        $showcaseData = showcaseGet(["showcase_id='{$showcaseID}'"],
            array_keys(TABLES_DATA['myshowcase_config']),
            ['limit' => 1]);
    }

    if ($isEditPage && !$showcaseData) {
        flash_message($lang->myShowcaseAdminErrorInvalidShowcase, 'error');

        admin_redirect($pageTabs['myShowcaseAdminSummary']['link']);
    }

    $tablesData = TABLES_DATA;

    $existingShowcases = cacheGet(CACHE_TYPE_CONFIG);

    $permissionsCache = cacheGet(CACHE_TYPE_PERMISSIONS);

    $errorMessages = [];

    if ($mybb->request_method === 'post') {
        $defaultPermissions = [];

        foreach ($tablesData['myshowcase_permissions'] as $fieldName => $fieldDefinition) {
            if (isset($fieldDefinition['is_permission'])) {
                $defaultPermissions[$fieldName] = $fieldDefinition;
            }
        }

        switch ($mybb->get_input('type')) {
            case 'main':
            case 'other':
                if (isset($mybb->input['showcase_slug'])) {
                    $mybb->input['showcase_slug'] = cleanSlug($mybb->get_input('showcase_slug'));
                }

                $insertData = [];

                foreach ($tablesData['myshowcase_config'] as $fieldName => $fieldDefinition) {
                    if (!isset($fieldDefinition['form_type']) ||
                        $fieldDefinition['form_category'] !== $mybb->get_input('type')) {
                        continue;
                    }

                    if (isset($mybb->input[$fieldName])) {
                        $insertData[$fieldName] = castTableFieldValue(
                            $mybb->input[$fieldName],
                            $fieldDefinition['type']
                        );
                    } elseif ($fieldDefinition['form_type'] === 'check_box') {
                        $insertData[$fieldName] = $fieldDefinition['default'];
                    }
                }

                if (isset($insertData['field_set_id']) && showcaseDataTableExists($showcaseID)) {
                    unset($insertData['field_set_id']);
                }

                if (isset($insertData['name']) && (!$insertData['name'] || in_array(
                            $insertData['name'],
                            array_column($existingShowcases, 'name')
                        ) && (function (
                            string $showcaseName
                        ) use ($showcaseID, $existingShowcases): bool {
                            $duplicatedName = false;

                            foreach ($existingShowcases as $showcaseData) {
                                if ($showcaseData['name'] === $showcaseName && $showcaseData['showcase_id'] !== $showcaseID) {
                                    $duplicatedName = true;
                                }
                            }

                            return $duplicatedName;
                        })(
                            $insertData['name']
                        ))) {
                    $errorMessages[] = $lang->myShowcaseAdminErrorDuplicatedName;
                }

                /*if (isset($insertData['showcase_slug']) && (!$insertData['showcase_slug'] || in_array(
                            $insertData['showcase_slug'],
                            array_column($existingShowcases, 'showcase_slug')
                        ) && (function (
                            string $showcaseName
                        ) use ($showcaseID, $existingShowcases): bool {
                            $duplicatedName = false;

                            foreach ($existingShowcases as $showcaseData) {
                                if ($showcaseData['showcase_slug'] === $showcaseName && $showcaseData['showcase_id'] !== $showcaseID) {
                                    $duplicatedName = true;
                                }
                            }

                            return $duplicatedName;
                        })(
                            $insertData['showcase_slug']
                        ))) {
                    $errorMessages[] = $lang->myShowcaseAdminErrorDuplicatedShowcaseSlug;
                }*/

                if (isset($insertData['script_name']) && (!$insertData['script_name'] || in_array(
                            $insertData['script_name'],
                            array_column($existingShowcases, 'script_name')
                        ) && (function (
                            string $scriptName
                        ) use ($showcaseID, $existingShowcases): bool {
                            $duplicateScript = false;

                            foreach ($existingShowcases as $showcaseData) {
                                if ($showcaseData['script_name'] === $scriptName && $showcaseData['showcase_id'] !== $showcaseID) {
                                    $duplicateScript = true;
                                }
                            }

                            return $duplicateScript;
                        })(
                            $insertData['script_name']
                        ))) {
                    $errorMessages[] = $lang->myShowcaseAdminErrorDuplicatedScriptFile;
                }

                if (isset($insertData['custom_theme_template_prefix']) && (!$insertData['custom_theme_template_prefix'] || in_array(
                            $insertData['custom_theme_template_prefix'],
                            array_column($existingShowcases, 'custom_theme_template_prefix')
                        ) && (function (
                            string $templatePrefix
                        ) use ($showcaseID, $existingShowcases): bool {
                            $duplicatedTemplatePrefix = false;

                            foreach ($existingShowcases as $showcaseData) {
                                if ($showcaseData['custom_theme_template_prefix'] === $templatePrefix && $showcaseData['showcase_id'] !== $showcaseID) {
                                    $duplicatedTemplatePrefix = true;
                                }
                            }

                            return $duplicatedTemplatePrefix;
                        })(
                            $insertData['custom_theme_template_prefix']
                        ))) {
                    $errorMessages[] = $lang->myShowcaseAdminErrorDuplicatedShowcaseSlug;
                }

                hooksRun('admin_summary_add_edit_post_main_other');

                if (!$errorMessages) {
                    if ($isAddPage) {
                        $showcaseID = showcaseInsert($insertData);

                        foreach ($groupsCache as $groupData) {
                            $groupID = (int)$groupData['gid'];

                            $permissionsData = [];

                            foreach ($defaultPermissions as $permissionKey => $fieldDefinition) {
                                if (isset($permissionsInput[$groupID][$permissionKey])) {
                                    $permissionsData[$permissionKey] = castTableFieldValue(
                                        $permissionsInput[$groupID][$permissionKey],
                                        $fieldDefinition['type']
                                    );
                                } else {
                                    $permissionsData[$permissionKey] = $fieldDefinition['default'];
                                }
                            }

                            hooksRun('admin_summary_add_edit_post_permissions');

                            permissionsUpdate($permissionsData, $groupID, $showcaseID);
                        }
                    } else {
                        showcaseUpdate($insertData, $showcaseID);
                    }

                    cacheUpdate(CACHE_TYPE_CONFIG);

                    if ($isAddPage) {
                        log_admin_action(['showcaseID' => $showcaseID]);

                        flash_message($lang->myShowcaseAdminSuccessNewShowcase, 'success');
                    } else {
                        log_admin_action(['showcaseID' => $showcaseID, 'type' => $mybb->get_input('type')]);

                        flash_message($lang->myShowcaseAdminSuccessShowcaseUpdated, 'success');
                    }

                    admin_redirect(
                        urlHandlerBuild(
                            ['action' => 'edit', 'type' => $mybb->get_input('type'), 'showcase_id' => $showcaseID]
                        ) . '#tab_' . $mybb->get_input('type')
                    );
                }
                break;
            case 'permissions':
                $insertData = [];

                $permissionsInput = $mybb->get_input('permissions', MyBB::INPUT_ARRAY);

                foreach ($groupsCache as $groupData) {
                    $groupID = (int)$groupData['gid'];

                    $permissionsData = [];

                    foreach ($defaultPermissions as $permissionKey => $fieldDefinition) {
                        if (isset($permissionsInput[$groupID][$permissionKey])) {
                            $permissionsData[$permissionKey] = castTableFieldValue(
                                $permissionsInput[$groupID][$permissionKey],
                                $fieldDefinition['type']
                            );
                        } else {
                            $permissionsData[$permissionKey] = $fieldDefinition['default'];
                        }
                    }

                    hooksRun('admin_summary_add_edit_post_permissions');

                    permissionsUpdate($permissionsData, $groupID, $showcaseID);
                }

                cacheUpdate(CACHE_TYPE_PERMISSIONS);

                log_admin_action(['showcaseID' => $showcaseID, 'type' => $mybb->get_input('type')]);

                flash_message($lang->myShowcaseAdminSuccessShowcaseEditPermissions, 'success');

                admin_redirect(
                    urlHandlerBuild(
                        ['action' => 'edit', 'type' => 'permissions', 'showcase_id' => $showcaseID]
                    ) . '#tab_permissions'
                );

                break;
            case 'moderators':
                if ($mybb->get_input('edit') == 'permissions') {
                    $moderatorPermissions = $mybb->get_input('permissions', MyBB::INPUT_ARRAY);

                    foreach ($moderatorPermissions as $moderatorID => $permissions) {
                        $permissions = array_map('intval', $permissions);

                        $moderatorData = [
                            ModeratorPermissions::CanManageEntries => $permissions[ModeratorPermissions::CanManageEntries] ?? 0,
                            ModeratorPermissions::CanManageEntries => $permissions[ModeratorPermissions::CanManageEntries] ?? 0,
                            ModeratorPermissions::CanManageEntries => $permissions[ModeratorPermissions::CanManageEntries] ?? 0,
                            ModeratorPermissions::CanManageComments => $permissions[ModeratorPermissions::CanManageComments] ?? 0,
                        ];

                        moderatorsUpdate(["showcase_id='{$showcaseID}'", "moderator_id='{$moderatorID}'"],
                            $moderatorData);
                    }
                } elseif ($mybb->get_input('add') == 'group') {
                    $groupID = $mybb->get_input('usergroup', MyBB::INPUT_INT);

                    $query = $db->simple_select('usergroups', 'gid', "gid='{$groupID}'");

                    if (!$db->num_rows($query)) {
                        flash_message($lang->myShowcaseAdminErrorInvalidGroup, 'error');

                        admin_redirect(
                            urlHandlerBuild(
                                ['action' => 'edit', 'type' => 'moderators', 'showcase_id' => $showcaseID]
                            ) . '#tab_moderators'
                        );
                    }

                    $moderatorData = [
                        'showcase_id' => $showcaseID,
                        'user_id' => $groupID,
                        'is_group' => MODERATOR_TYPE_GROUP,
                        ModeratorPermissions::CanManageEntries => $mybb->get_input(
                            'gcanmodapprove',
                            MyBB::INPUT_INT
                        ),
                        ModeratorPermissions::CanManageEntries => $mybb->get_input(
                            'gcanmodedit',
                            MyBB::INPUT_INT
                        ),
                        ModeratorPermissions::CanManageEntries => $mybb->get_input(
                            'gcanmoddelete',
                            MyBB::INPUT_INT
                        ),
                        ModeratorPermissions::CanManageComments => $mybb->get_input(
                            'gcanmoddelcomment',
                            MyBB::INPUT_INT
                        ),
                    ];

                    moderatorsInsert($moderatorData);
                } elseif ($mybb->get_input('add') == 'user') {
                    $userData = get_user_by_username($mybb->get_input('username'));

                    if (empty($userData['uid'])) {
                        flash_message($lang->myShowcaseAdminErrorInvalidGroup, 'error');

                        admin_redirect(
                            urlHandlerBuild(
                                ['action' => 'edit', 'type' => 'moderators', 'showcase_id' => $showcaseID]
                            ) . '#tab_moderators'
                        );
                    }

                    $moderatorData = [
                        'showcase_id' => $showcaseID,
                        'user_id' => (int)$userData['uid'],
                        'is_group' => MODERATOR_TYPE_USER,
                        ModeratorPermissions::CanManageEntries => $mybb->get_input(
                            'ucanmodapprove',
                            MyBB::INPUT_INT
                        ),
                        ModeratorPermissions::CanManageEntries => $mybb->get_input(
                            'ucanmodedit',
                            MyBB::INPUT_INT
                        ),
                        ModeratorPermissions::CanManageEntries => $mybb->get_input(
                            'ucanmoddelete',
                            MyBB::INPUT_INT
                        ),
                        ModeratorPermissions::CanManageComments => $mybb->get_input(
                            'ucanmoddelcomment',
                            MyBB::INPUT_INT
                        ),
                    ];

                    moderatorsInsert($moderatorData);
                }

                cacheUpdate(CACHE_TYPE_MODERATORS);

                log_admin_action(['showcaseID' => $showcaseID, 'type' => $mybb->get_input('type')]);

                flash_message($lang->myShowcaseAdminSuccessShowcaseEditModerators, 'success');

                admin_redirect(
                    urlHandlerBuild(['action' => 'edit', 'type' => 'moderators', 'showcase_id' => $showcaseID]
                    ) . '#tab_moderators'
                );

                break;
            case 'delete':
                moderatorsDelete(["moderator_id={$mybb->get_input('moderator_id', MyBB::INPUT_INT)}"]);

                cacheUpdate(CACHE_TYPE_MODERATORS);

                log_admin_action(['showcaseID' => $showcaseID, 'type' => $mybb->get_input('type')]);

                flash_message($lang->myShowcaseAdminSuccessShowcaseEditModeratorDelete, 'success');

                admin_redirect(
                    urlHandlerBuild(['action' => 'edit', 'type' => 'moderators', 'showcase_id' => $showcaseID]
                    ) . '#tab_moderators'
                );
                break;
        }
    }

    $page->add_breadcrumb_item(
        $lang->myShowcaseAdminSummaryEdit,
        urlHandlerBuild(['action' => $isAddPage ? 'add' : 'edit', 'showcase_id' => $showcaseID])
    );

    $page->output_header($lang->myShowcaseAdminSummaryEdit);

    if ($errorMessages) {
        $page->output_inline_error($errorMessages);
    }

    $tabs = [
        'main' => $lang->myshowcase_admin_main_options,
    ];

    if ($isEditPage) {
        $tabs['other'] = $lang->myshowcase_admin_other_options;

        $tabs['permissions'] = $lang->myshowcase_admin_permissions;

        $tabs['moderators'] = $lang->myshowcase_admin_moderators;
    }

    hooksRun('admin_summary_add_edit_start');

    if ($isAddPage) {
        $page->output_nav_tabs($pageTabs, 'myShowcaseAdminSummaryNew');
    } else {
        $page->output_nav_tabs($pageTabs, 'myShowcaseAdminSummaryEdit');
    }

    $page->output_tab_control($tabs);

    //myshowcase_get_group_permissions($showcaseID);
    $groupPermissions = $permissionsCache[$showcaseID] ?? [];

    $mybb->input = array_merge($showcaseData ?? [], $mybb->input);

    $rowObjects = [
        'main' => [
            'single' => [],
            'grouped' => [],
        ],
        'other' => [
            'single' => [],
            'grouped' => [],
        ]
    ];

    foreach ($tablesData['myshowcase_config'] as $fieldName => $fieldData) {
        if (!isset($fieldData['form_category'])) {
            continue;
        }

        if (isset($fieldData['form_section'])) {
            $rowObjects[$fieldData['form_category']]['grouped'][$fieldData['form_section']][$fieldName] = $fieldData;
        } else {
            $rowObjects[$fieldData['form_category']]['single'][$fieldName] = $fieldData;
        }
    }

    $buildRow = function (
        Form $form,
        string $fieldName,
        array $fieldData,
        string $key,
        string $section = 'Main',
        bool $extraText = false
    ) use ($mybb, $lang): string {
        $formInput = '';

        switch ($fieldData['form_type']) {
            case 'text':
                if ($extraText) {
                    $formInput .= '<div class="group_settings_bit">';
                    $formInput .= $lang->{'myShowcaseAdminSummaryAddEdit' . $section . $key};
                    $formInput .= '<br /><small class="input">';
                    $formInput .= $lang->{'myShowcaseAdminSummaryAddEdit' . $section . $key . 'Description'};
                    $formInput .= '</small><br />';
                }

                $formInput .= $form->generate_text_box(
                    $fieldName,
                    $mybb->get_input($fieldName),
                    ['id' => $fieldName, 'max' => $fieldData['size']]
                );

                if ($extraText) {
                    $formInput .= '</div>';
                }
                break;
            case 'numeric':
                if ($extraText) {
                    $formInput .= '<div class="group_settings_bit">';
                    $formInput .= $lang->{'myShowcaseAdminSummaryAddEdit' . $section . $key};
                    $formInput .= '<br /><small class="input">';
                    $formInput .= $lang->{'myShowcaseAdminSummaryAddEdit' . $section . $key . 'Description'};
                    $formInput .= '</small><br />';
                }

                $formInput .= $form->generate_numeric_field(
                    $fieldName,
                    $mybb->get_input($fieldName, MyBB::INPUT_INT),
                    ['id' => $fieldName, 'class' => $fieldData['form_class'] ?? '']
                );

                if ($extraText) {
                    $formInput .= '</div>';
                }
                break;
            case 'yes_no':
                $formInput .= $form->generate_yes_no_radio(
                    $fieldName,
                    $mybb->get_input($fieldName, MyBB::INPUT_INT),
                    ['id' => $fieldName]
                );
                break;
            case 'select':
                if ($extraText) {
                    $formInput .= '<div class="group_settings_bit">';
                    $formInput .= $lang->{'myShowcaseAdminSummaryAddEdit' . $section . $key};
                    $formInput .= '<br /><small class="input">';
                    $formInput .= $lang->{'myShowcaseAdminSummaryAddEdit' . $section . $key . 'Description'};
                    $formInput .= '</small><br />';
                }

                $formInput .= $form->generate_select_box(
                    $fieldName,
                    isset($fieldData['form_function']) ? $fieldData['form_function']() : $fieldData['form_array'],
                    [$mybb->get_input($fieldName, MyBB::INPUT_INT)],
                    ['id' => $fieldName]
                );

                if ($extraText) {
                    $formInput .= '</div>';
                }
                break;
            case 'check_box':
                $formInput .= '<div class="group_settings_bit">';

                $formInput .= $form->generate_check_box(
                    $fieldName,
                    1,
                    $lang->{'myShowcaseAdminSummaryAddEdit' . $section . $key},
                    ['checked' => $mybb->get_input($fieldName, MyBB::INPUT_INT)]
                );

                $formInput .= '</div>';
                break;
        }

        return $formInput;
    };

    //main options tab
    echo "<div id=\"tab_main\">\n";

    $form = new Form(
        urlHandlerBuild(
            ['action' => $isAddPage ? 'add' : 'edit', 'type' => 'main', 'showcase_id' => $showcaseID]
        ) . '#tab_main',
        'post',
        $isAddPage ? 'add' : 'edit'
    );

    $formContainer = new FormContainer($lang->myshowcase_admin_main_options);

    foreach ($rowObjects['main']['single'] as $fieldName => $fieldData) {
        $key = str_replace(' ', '', ucwords(str_replace('_', ' ', $fieldName)));

        $formContainer->output_row(
            $lang->{'myShowcaseAdminSummaryAddEditMain' . $key},
            $lang->{'myShowcaseAdminSummaryAddEditMain' . $key . 'Description'},
            $buildRow($form, $fieldName, $fieldData, $key, 'Main')
        );
    }

    foreach ($rowObjects['main']['grouped'] as $formSection => $fieldObjects) {
        $sectionKey = ucfirst($formSection);

        $settingCode = '';

        foreach ($fieldObjects as $fieldName => $fieldData) {
            $key = str_replace(' ', '', ucwords(str_replace('_', ' ', $fieldName)));

            $settingCode .= $buildRow($form, $fieldName, $fieldData, $key, 'Main', true);
        }

        $formContainer->output_row(
            $lang->{'myShowcaseAdminSummaryAddEditMain' . $sectionKey},
            '',
            $settingCode
        );
    }

    $formContainer->end();

    $form->output_submit_wrapper([
        $form->generate_submit_button($lang->myShowcaseAdminButtonSubmit),
        $form->generate_reset_button($lang->myShowcaseAdminButtonReset)
    ]);

    $form->end();

    echo "</div>\n";

    if ($isEditPage) {
        //other options tab
        echo "<div id=\"tab_other\">\n";

        $form = new Form(
            urlHandlerBuild(['action' => 'edit', 'type' => 'other', 'showcase_id' => $showcaseID]) . '#tab_other',
            'post',
            'edit'
        );

        $formContainer = new FormContainer($lang->myshowcase_admin_other_options);

        foreach ($rowObjects['other']['single'] as $fieldName => $fieldData) {
            $key = str_replace(' ', '', ucwords(str_replace('_', ' ', $fieldName)));

            $formContainer->output_row(
                $lang->{'myShowcaseAdminSummaryAddEditOther' . $key},
                $lang->{'myShowcaseAdminSummaryAddEditOther' . $key . 'Description'},
                $buildRow($form, $fieldName, $fieldData, $key, 'Other')
            );
        }

        foreach ($rowObjects['other']['grouped'] as $formSection => $fieldObjects) {
            $sectionKey = ucfirst($formSection);

            $settingCode = '';

            foreach ($fieldObjects as $fieldName => $fieldData) {
                $key = str_replace(' ', '', ucwords(str_replace('_', ' ', $fieldName)));

                $settingCode .= $buildRow($form, $fieldName, $fieldData, $key, $sectionKey, true);
            }

            $formContainer->output_row(
                $lang->{'myShowcaseAdminSummaryAddEditOther' . $sectionKey},
                '',
                $settingCode
            );
        }

        $formContainer->end();

        $form->output_submit_wrapper([
            $form->generate_submit_button($lang->myShowcaseAdminButtonSubmitOther),
            $form->generate_reset_button($lang->myShowcaseAdminButtonReset)
        ]);

        $form->end();

        echo "</div>\n";

        //permissions tab
        echo "<div id=\"tab_permissions\">\n";

        $form = new Form(
            urlHandlerBuild(['action' => 'edit', 'type' => 'permissions', 'showcase_id' => $showcaseID]
            ) . '#tab_permissions',
            'post',
            'edit'
        );

        $formContainer = new FormContainer($lang->myshowcase_admin_permissions);

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditPermissionsGroup,
            ['width' => '23%', 'class' => 'align_center']
        );

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditPermissionsCanAdd,
            ['width' => '6%', 'class' => 'align_center']
        );

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditPermissionsCanEdit,
            ['width' => '6%', 'class' => 'align_center']
        );

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditPermissionsCanAttach,
            ['width' => '6%', 'class' => 'align_center']
        );

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditPermissionsCanView,
            ['width' => '6%', 'class' => 'align_center']
        );

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditPermissionsCanViewComments,
            ['width' => '6%', 'class' => 'align_center']
        );

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditPermissionsCanViewAttachments,
            ['width' => '6%', 'class' => 'align_center']
        );

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditPermissionsCanComment,
            ['width' => '6%', 'class' => 'align_center']
        );

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditPermissionsCanDeleteOwnComment,
            ['width' => '6%', 'class' => 'align_center']
        );

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditPermissionsCanDeleteAuthorComment,
            ['width' => '6%', 'class' => 'align_center']
        );

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditPermissionsCanSearch,
            ['width' => '6%', 'class' => 'align_center']
        );

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditPermissionsCanWatermark,
            ['width' => '6%', 'class' => 'align_center']
        );

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditPermissionsAttachmentLimit,
            ['class' => 'align_center']
        );

        $defaultShowcasePermissions = showcaseDefaultPermissions();

        foreach ($groupsCache as $group) {
            $formContainer->output_cell(
                '<strong>' . $group['title'] . '</strong>',
                ['class' => 'align_left']
            );

            foreach ($defaultShowcasePermissions as $permissionKey => $permissionValue) {
                $lang_field = 'myshowcase_' . $permissionKey;

                if ($permissionKey == UserPermissions::AttachmentsLimit) {
                    $formContainer->output_cell(
                        $form->generate_numeric_field(
                            "permissions[{$group['gid']}][{$permissionKey}]",
                            $groupPermissions[$group['gid']][$permissionKey] ?? 0,
                            ['id' => $permissionKey . $group['gid'], 'class' => 'field50', 'min' => 0]
                        ),
                        ['class' => 'align_center']
                    );
                } else {
                    $formContainer->output_cell(
                        $form->generate_check_box(
                            "permissions[{$group['gid']}][{$permissionKey}]",
                            1,
                            '',
                            [
                                'checked' => $groupPermissions[$group['gid']][$permissionKey] ?? 0,
                                'id' => $permissionKey . $group['gid']
                            ]
                        ),
                        ['class' => 'align_center']
                    );
                }
            }

            $formContainer->construct_row();
        }

        $formContainer->end();

        $form->output_submit_wrapper([
            $form->generate_submit_button($lang->myShowcaseAdminButtonSubmitPermissions),
            $form->generate_reset_button($lang->myShowcaseAdminButtonReset)
        ]);

        $form->end();

        echo "</div>\n";

        echo "<div id=\"tab_moderators\">\n";

        $form = new Form(
            urlHandlerBuild(['action' => 'edit', 'type' => 'moderators', 'showcase_id' => $showcaseID]
            ) . '#tab_moderators',
            'post',
            'management'
        );

        echo $form->generate_hidden_field('edit', 'permissions');

        $formContainer = new FormContainer(
            $lang->sprintf($lang->myShowcaseAdminSummaryEditModeratorPermissionsAssigned, $showcaseData['name'])
        );

        $formContainer->output_row_header($lang->myShowcaseAdminSummaryEditModeratorPermissionsName);

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditModeratorPermissionsCanApprove,
            ['width' => '10%', 'class' => 'align_center']
        );

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditModeratorPermissionsCanEdit,
            ['width' => '10%', 'class' => 'align_center']
        );

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditModeratorPermissionsCanDelete,
            ['width' => '10%', 'class' => 'align_center']
        );

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditModeratorPermissionsCanDeleteAuthorComment,
            ['width' => '15%', 'class' => 'align_center']
        );

        $formContainer->output_row_header(
            $lang->myShowcaseAdminSummaryEditModeratorPermissionsControls,
            ['width' => '10%', 'class' => 'align_center']
        );

        $fieldObjects = moderatorGet(
            ["showcase_id='{$showcaseID}'"],
            [
                'moderator_id',
                'user_id',
                'is_group',
                ModeratorPermissions::CanManageEntries,
                ModeratorPermissions::CanManageEntries,
                ModeratorPermissions::CanManageEntries,
                ModeratorPermissions::CanManageComments
            ],
            ['order_by' => 'is_group']
        );

        while ($moderatorData = $db->fetch_array($query)) {
            if (!empty($moderatorData['is_group'])) {
                $moderatorData['img'] = "<img src=\"styles/{$page->style}/images/icons/group.png\" alt=\"{$lang->myshowcase_moderators_group}\" title=\"{$lang->myshowcase_moderators_group}\" />";

                foreach ($groupsCache as $groupData) {
                    if ($groupData['gid'] == $moderatorData['user_id']) {
                        $moderatorData['title'] = $groupData['title'];
                    }
                }

                $formContainer->output_cell(
                    "{$moderatorData['img']} <a href=\"index.php?module=user-groups&amp;action=edit&amp;group_id={$moderatorData['group_id']}\">" . htmlspecialchars_uni(
                        $moderatorData['title']
                    ) . '</a>'
                );
            } else {
                $moderatorData['img'] = "<img src=\"styles/{$page->style}/images/icons/user.png\" alt=\"{$lang->myshowcase_moderators_user}\" title=\"{$lang->myshowcase_moderators_user}\" />";

                $userData = get_user($moderatorData['user_id']);

                $formContainer->output_cell(
                    "{$moderatorData['img']} <a href=\"index.php?module=user-users&amp;action=edit&amp;user_id={$moderatorData['showcase_id']}\">" . htmlspecialchars_uni(
                        $userData['username']
                    ) . '</a>'
                );
            }

            $formContainer->output_cell(
                $form->generate_check_box(
                    "permissions[{$moderatorData['moderator_id']}][canmodapprove]",
                    1,
                    '',
                    [
                        'checked' => $moderatorData[ModeratorPermissions::CanManageEntries],
                        'id' => "modapprove{$moderatorData['moderator_id']}"
                    ]
                ),
                ['class' => 'align_center']
            );

            $formContainer->output_cell(
                $form->generate_check_box(
                    "permissions[{$moderatorData['moderator_id']}][canmodedit]",
                    1,
                    '',
                    [
                        'checked' => $moderatorData[ModeratorPermissions::CanManageEntries],
                        'id' => "modedit{$moderatorData['moderator_id']}"
                    ]
                ),
                ['class' => 'align_center']
            );

            $formContainer->output_cell(
                $form->generate_check_box(
                    "permissions[{$moderatorData['moderator_id']}][canmoddelete]",
                    1,
                    '',
                    [
                        'checked' => $moderatorData[ModeratorPermissions::CanManageEntries],
                        'id' => "moddelete{$moderatorData['moderator_id']}"
                    ]
                ),
                ['class' => 'align_center']
            );

            $formContainer->output_cell(
                $form->generate_check_box(
                    "permissions[{$moderatorData['moderator_id']}][canmoddelcomment]",
                    1,
                    '',
                    [
                        'checked' => $moderatorData[ModeratorPermissions::CanManageComments],
                        'id' => "moddelcomment{$moderatorData['moderator_id']}"
                    ]
                ),
                ['class' => 'align_center']
            );

            $deleteModeratorUrl = urlHandlerBuild([
                    'action' => 'edit',
                    'type' => 'delete',
                    'showcase_id' => $showcaseID,
                    'moderator_id' => $moderatorData['moderator_id'],
                    'my_post_key' => $mybb->post_code
                ]) . '#tab_moderators';

            $formContainer->output_cell(
                "<a href=\"{$deleteModeratorUrl}\" onclick=\"return AdminCP.deleteConfirmation(this, '{$lang->myShowcaseAdminConfirmModeratorDelete}')\">{$lang->myShowcaseAdminButtonDelete}</a>",
                ['class' => 'align_center']
            );

            $formContainer->construct_row();
        }

        if (!$formContainer->num_rows()) {
            $formContainer->output_cell(
                $lang->myShowcaseAdminSummaryEditModeratorPermissionsEmpty,
                ['colspan' => 6, 'class' => 'align_center']
            );

            $formContainer->construct_row();
        }

        $formContainer->end();

        $form->output_submit_wrapper(
            [$form->generate_submit_button($lang->myShowcaseAdminButtonSubmitSaveModeratorPermissions)]
        );

        $form->end();

        echo '<br />';

        // Add Usergropups
        $form = new Form(
            urlHandlerBuild(['action' => 'edit', 'type' => 'moderators', 'showcase_id' => $showcaseID]
            ) . '#tab_moderators',
            'post',
            'management'
        );

        echo $form->generate_hidden_field('add', 'group');

        $formContainer = new FormContainer($lang->myShowcaseAdminSummaryEditModeratorPermissionsAddGroup);

        $formContainer->output_row(
            $lang->myShowcaseAdminSummaryEditModeratorPermissionsAddGroupGroup . ' <em>*</em>',
            $lang->myShowcaseAdminSummaryEditModeratorPermissionsAddGroupGroupDescription,
            $form->generate_select_box(
                'usergroup',
                (function () use ($groupsCache, $lang): array {
                    $groupObjects = [];

                    foreach ($groupsCache as $groupData) {
                        $groupObjects[(int)$groupData['gid']] = $lang->myShowcaseAdminUserGroup . ' ' . $groupData['gid'] . ': ' . htmlspecialchars_uni(
                                $groupData['title']
                            );
                    }

                    return $groupObjects;
                })(),
                $mybb->get_input('usergroup'),
                ['id' => 'usergroup']
            ),
            'usergroup'
        );

        $rowOptions = [
            $form->generate_check_box(
                'gcanmodapprove',
                1,
                $lang->myShowcaseAdminSummaryEditModeratorPermissionsCanApproveEntries,
                ['checked' => 1]
            ) . '<br />',
            $form->generate_check_box(
                'gcanmodedit',
                1,
                $lang->myShowcaseAdminSummaryEditModeratorPermissionsCanEditEntries,
                ['checked' => 1]
            ) . '<br />',
            $form->generate_check_box(
                'gcanmoddelete',
                1,
                $lang->myShowcaseAdminSummaryEditModeratorPermissionsCanDeleteEntries,
                ['checked' => 1]
            ) . '<br />',
            $form->generate_check_box(
                'gcanmoddelcomment',
                1,
                $lang->myShowcaseAdminSummaryEditModeratorPermissionsCanDeleteComments,
                ['checked' => 1]
            )
        ];

        $formContainer->output_row(
            $lang->myShowcaseAdminSummaryEditModeratorPermissions,
            $lang->myShowcaseAdminSummaryEditModeratorPermissionsDescription,
            "<div class=\"group_settings_bit\">" . implode(
                "</div><div class=\"group_settings_bit\">",
                $rowOptions
            ) . '</div>'
        );

        $formContainer->end();

        $form->output_submit_wrapper(
            [$form->generate_submit_button($lang->myShowcaseAdminButtonSubmitAddModeratorGroup)]
        );

        $form->end();

        echo '<br />';

        //add Users
        $form = new Form(
            urlHandlerBuild(['action' => 'edit', 'type' => 'moderators', 'showcase_id' => $showcaseID]
            ) . '#tab_moderators',
            'post',
            'management'
        );

        echo $form->generate_hidden_field('add', 'user');

        $formContainer = new FormContainer($lang->myShowcaseAdminSummaryEditModeratorPermissionsAddUser);

        $formContainer->output_row(
            $lang->myShowcaseAdminSummaryEditModeratorPermissionsAddUserUsername . ' <em>*</em>',
            $lang->myShowcaseAdminSummaryEditModeratorPermissionsAddUserUsernameDescription,
            $form->generate_text_box(
                'username',
                '',
                ['id' => 'username']
            ),
            'username'
        );

        $rowOptions = [
            $form->generate_check_box(
                'ucanmodapprove',
                1,
                $lang->myShowcaseAdminSummaryEditModeratorPermissionsCanApproveEntries,
                ['checked' => 1]
            ) . '<br />',
            $form->generate_check_box(
                'ucanmodedit',
                1,
                $lang->myShowcaseAdminSummaryEditModeratorPermissionsCanEditEntries,
                ['checked' => 1]
            ) . '<br />',
            $form->generate_check_box(
                'ucanmoddelete',
                1,
                $lang->myShowcaseAdminSummaryEditModeratorPermissionsCanDeleteEntries,
                ['checked' => 1]
            ) . '<br />',
            $form->generate_check_box(
                'ucanmoddelcomment',
                1,
                $lang->myShowcaseAdminSummaryEditModeratorPermissionsCanDeleteComments,
                ['checked' => 1]
            )
        ];

        $formContainer->output_row(
            $lang->myShowcaseAdminSummaryEditModeratorPermissions,
            $lang->myShowcaseAdminSummaryEditModeratorPermissionsDescription,
            "<div class=\"group_settings_bit\">" . implode(
                "</div><div class=\"group_settings_bit\">",
                $rowOptions
            ) . '</div>'
        );


        $formContainer->end();

        // Autocompletion for usernames
        echo '
		<link rel="stylesheet" href="../jscripts/select2/select2.css">
		<script type="text/javascript" src="../jscripts/select2/select2.min.js?ver=1804"></script>
		<script type="text/javascript">
		<!--
		$("#username").select2({
			placeholder: "' . $lang->search_for_a_user . '",
			minimumInputLength: 2,
			multiple: false,
			ajax: { // instead of writing the function to execute the request we use Select2\'s convenient helper
				url: "../xmlhttp.php?action=get_users",
				dataType: \'json\',
				data: function (term, page) {
					return {
						query: term, // search_field term
					};
				},
				results: function (data, page) { // parse the results into the format expected by Select2.
					// since we are using custom formatting functions we do not need to alter remote JSON data
					return {results: data};
				}
			},
			initSelection: function(element, callback) {
				var query = $(element).val();
				if (query !== "") {
					$.ajax("../xmlhttp.php?action=get_users&getone=1", {
						data: {
							query: query
						},
						dataType: "json"
					}).done(function(data) { callback(data); });
				}
			},
		});

		$(\'[for=username]\').on(\'click\', function(){
			$("#username").select2(\'open\');
			return false;
		});
		// -->
		</script>';

        $form->output_submit_wrapper([$form->generate_submit_button($lang->myShowcaseAdminButtonSubmitAddModeratorUser)]
        );

        $form->end();

        echo "</div>\n";
    }

    hooksRun('admin_summary_add_edit_end');

    $page->output_footer();
} elseif (in_array($mybb->get_input('action'), ['enable', 'disable'])) {
    $enableShowcase = $mybb->get_input('action') === 'enable';

    $showcaseData = showcaseGet(["showcase_id='{$showcaseID}'"], [], ['limit' => 1]);

    if (!$showcaseData) {
        flash_message($lang->myShowcaseAdminErrorInvalidShowcase, 'error');

        admin_redirect($pageTabs['myShowcaseAdminSummary']['link']);
    }

    $showcaseData = ['enabled' => $enableShowcase ? SHOWCASE_STATUS_ENABLED : SHOWCASE_STATUS_DISABLED];

    hooksRun('admin_summary_enable_disable');

    showcaseUpdate($insertData, $showcaseID);

    log_admin_action(['showcaseID' => $showcaseID, 'enableShowcase' => $enableShowcase]);

    cacheUpdate(CACHE_TYPE_CONFIG);

    flash_message(
        $enableShowcase ? $lang->myShowcaseAdminSuccessEnabledShowcase : $lang->myShowcaseAdminSuccessDisabledShowcase,
        'success'
    );

    admin_redirect($pageTabs['myShowcaseAdminSummary']['link']);
} elseif (in_array($mybb->get_input('action'), ['tableCreate', 'tableRebuild'])) {
    $createTable = $mybb->get_input('action') === 'tableCreate';

    $showcaseData = showcaseGet(["showcase_id='{$showcaseID}'"], ['field_set_id'], ['limit' => 1]);

    if (!$showcaseData || $createTable && showcaseDataTableExists($showcaseID)) {
        flash_message($lang->myShowcaseAdminErrorInvalidShowcase, 'error');

        admin_redirect(urlHandlerBuild());
    }

    $dataTableStructure = dataTableStructureGet($showcaseID);

    hooksRun('admin_summary_table_create_rebuild_start');

    dbVerifyTables(["myshowcase_data{$showcaseID}" => $dataTableStructure]);

    if (showcaseDataTableExists($showcaseID)) {
        log_admin_action(['showcaseID' => $showcaseID, 'createTable' => $createTable]);

        if ($createTable) {
            flash_message($lang->myShowcaseAdminSuccessTableCreated, 'success');
        } else {
            flash_message($lang->myShowcaseAdminSuccessTableRebuilt, 'success');
        }
    } else {
        flash_message($lang->myShowcaseAdminErrorTableCreate, 'error');
    }

    admin_redirect(urlHandlerBuild());
} elseif ($mybb->get_input('action') === 'tableDrop') {
    $showcaseData = showcaseGet(["showcase_id='{$showcaseID}'"], ['name'], ['limit' => 1]);

    if (!$showcaseData) {
        flash_message($lang->myShowcaseAdminErrorInvalidShowcase, 'error');

        admin_redirect(urlHandlerBuild());
    }

    hooksRun('admin_summary_table_drop_start');

    $showcaseName = $showcaseData['name'];

    if (entryDataGet($showcaseID)) {
        flash_message($lang->myShowcaseAdminErrorTableDrop, 'error');

        admin_redirect(urlHandlerBuild());
    }

    if ($mybb->request_method === 'post') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            flash_message($lang->myShowcaseAdminErrorInvalidPostKey, 'error');

            admin_redirect(urlHandlerBuild());
        }

        if (isset($mybb->input['no'])) {
            admin_redirect(urlHandlerBuild());
        }

        hooksRun('admin_summary_table_drop_post');

        if (showcaseDataTableExists($showcaseID)) {
            showcaseDataTableDrop($showcaseID);

            showcaseUpdate(['enabled' => 0], $showcaseID);
        }

        flash_message($lang->myShowcaseAdminSuccessTableDropped, 'success');

        admin_redirect(urlHandlerBuild());
    }

    $page->output_confirm_action(
        urlHandlerBuild(['action' => 'tableDrop', 'showcase_id' => $showcaseID]),
        $lang->sprintf($lang->myShowcaseAdminConfirmTableDrop, $showcaseName)
    );
} elseif ($mybb->get_input('action') === 'viewRewrites') {
    $showcaseData = showcaseGet(["showcase_id='{$showcaseID}'"],
        ['name', 'showcase_slug', 'script_name'],
        ['limit' => 1]);

    if (!$showcaseData) {
        flash_message($lang->myShowcaseAdminErrorInvalidShowcase, 'error');

        admin_redirect($pageTabs['myShowcaseAdminSummary']['link']);
    }

    $page->add_breadcrumb_item(
        $lang->myshowcase_admin_show_seo,
        urlHandlerBuild(['action' => 'viewRewrites', 'showcase_id' => $showcaseID])
    );

    $page->output_header($lang->myShowcaseAdminSummary);

    $showcaseName = strtolower($showcaseData['name']);

    //cleaning showcase name for redirect
    //some cleaning borrowed from Google SEO plugin
    $pattern = '!"#$%&\'( )*+,-./:;<=>?@[\]^_`{|}~';

    $pattern = preg_replace(
        "/[\\\\\\^\\-\\[\\]\\/]/u",
        "\\\\\\0",
        $pattern
    );

    // Cut off punctuation at beginning and end.
    $showcaseName = preg_replace(
        "/^[$pattern]+|[$pattern]+$/u",
        '',
        $showcaseName
    );

    // Replace middle punctuation with one separator.
    $showcaseName = preg_replace(
        "/[$pattern]+/u",
        '-',
        $showcaseName
    );

    echo $lang->sprintf(
        $lang->myShowcaseAdminSummaryViewRewritesDescription,
        $showcaseData['name'],
        $showcaseData['name']
    );

    echo $lang->sprintf(
        $lang->myShowcaseAdminSummaryViewRewritesMain,
        $showcaseName,
        $showcaseData['showcase_slug']
    );

    echo $lang->sprintf(
        $lang->myShowcaseAdminSummaryViewRewritesPage,
        $showcaseName,
        $showcaseData['script_name']
    );

    echo $lang->sprintf(
        $lang->myShowcaseAdminSummaryViewRewritesView,
        $showcaseName,
        $showcaseData['script_name']
    );

    echo $lang->sprintf(
        $lang->myShowcaseAdminSummaryViewRewritesNew,
        $showcaseName,
        $showcaseData['script_name']
    );

    echo $lang->sprintf(
        $lang->myShowcaseAdminSummaryViewRewritesAttachment,
        $showcaseName,
        $showcaseData['script_name']
    );

    echo $lang->sprintf(
        $lang->myShowcaseAdminSummaryViewRewritesEntry,
        $showcaseName,
        $showcaseData['script_name']
    );

    $page->output_footer();
} elseif ($mybb->get_input('action') == 'delete') {
    $showcaseData = showcaseGet(["showcase_id='{$showcaseID}'"], [], ['limit' => 1]);

    if (!$showcaseData) {
        flash_message($lang->myShowcaseAdminErrorInvalidShowcase, 'error');

        admin_redirect(urlHandlerBuild());
    }

    hooksRun('admin_summary_delete_start');

    $showcaseName = $showcaseData['name'];

    if ($mybb->request_method === 'post') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            flash_message($lang->myShowcaseAdminErrorInvalidPostKey, 'error');

            admin_redirect(urlHandlerBuild());
        }

        if (isset($mybb->input['no'])) {
            admin_redirect(urlHandlerBuild());
        }

        hooksRun('admin_summary_delete_post');

        commentsDelete(["showcase_id='{$showcaseID}'"]);

        attachmentDelete(["showcase_id='{$showcaseID}'"]);

        permissionsDelete(["showcase_id='{$showcaseID}'"]);

        moderatorsDelete(["showcase_id='{$showcaseID}'"]);

        if (showcaseDataTableExists($showcaseID)) {
            showcaseDataTableDrop($showcaseID);
        }

        showcaseDelete(["showcase_id='{$showcaseID}'"]);

        cacheUpdate(CACHE_TYPE_CONFIG);

        cacheUpdate(CACHE_TYPE_PERMISSIONS);

        log_admin_action(['showcaseID' => $showcaseID]);

        if (showcaseGet(["showcase_id='{$showcaseID}'"], [], ['limit' => 1])) {
            flash_message($lang->myShowcaseAdminErrorShowcaseDelete, 'error');
        } else {
            flash_message($lang->myShowcaseAdminSuccessShowcaseDeleted, 'success');
        }

        admin_redirect(urlHandlerBuild());
    }

    $page->output_confirm_action(
        urlHandlerBuild(['action' => 'delete', 'showcase_id' => $showcaseID]),
        $lang->sprintf($lang->myShowcaseAdminConfirmShowcaseDelete, $showcaseName)
    );
} else {
    hooksRun('admin_summary_main_start');

    $page->output_header($lang->myShowcaseAdminSummary);

    $page->output_nav_tabs($pageTabs, 'myShowcaseAdminSummary');

    $formContainer = new FormContainer($lang->myshowcase_summary_existing);

    $formContainer->output_row_header($lang->myshowcase_summary_id, ['width' => '2%', 'class' => 'align_center']);

    $formContainer->output_row_header(
        $lang->myshowcase_summary_name,
        ['width' => '10%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_summary_slug,
        ['width' => '10%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_summary_description,
        ['width' => '17%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_summary_entries_count,
        ['width' => '5%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_summary_comment_count,
        ['width' => '5%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_summary_attachments_count,
        ['width' => '5%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_summary_attachments_size,
        ['width' => '5%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_summary_main_file,
        ['width' => '5%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_summary_image_folder,
        ['width' => '5%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_summary_field_set,
        ['width' => '5%', 'class' => 'align_center']
    );

    $formContainer->output_row_header(
        $lang->myshowcase_summary_status,
        ['width' => '2%', 'class' => 'align_center']
    );

    $formContainer->output_row_header($lang->controls, ['width' => '5%', 'class' => 'align_center']);

    $showcaseObjects = showcaseGet([], array_keys(TABLES_DATA['myshowcase_config']));

    if (!$showcaseObjects) {
        $formContainer->output_cell($lang->myshowcase_summary_no_myshowcases, ['colspan' => 9]);

        $formContainer->construct_row();
    } else {
        foreach ($showcaseObjects as $showcaseID => $showcaseData) {
            $showcaseTotalEntries = $showcaseTotalAttachments = $showcaseTotalFilesSize = $showcaseTotalComments = 0;

            $fieldsetID = (int)$showcaseData['field_set_id'];

            $showcaseDataTableExists = showcaseDataTableExists($showcaseID);

            if ($showcaseDataTableExists) {
                $showcaseTotalEntries = entryDataGet(
                    $showcaseID,
                    [],
                    ['COUNT(entry_id) AS showcaseTotalEntries'],
                    ['group_by' => 'entry_id']
                )['showcaseTotalEntries'] ?? 0;

                $showcaseTotalAttachments = attachmentGet(
                    ["showcase_id='{$showcaseID}'"],
                    ['COUNT(attachment_id) AS showcaseTotalAttachments'],
                    ['group_by' => 'showcase_id, attachment_id']
                )['showcaseTotalAttachments'] ?? 0;

                $showcaseTotalFilesSize = attachmentGet(
                    ["showcase_id='{$showcaseID}'"],
                    ['SUM(file_size) AS showcaseTotalFilesSize'],
                    ['group_by' => 'showcase_id, attachment_id, file_size']
                )['showcaseTotalFilesSize'] ?? 0;

                $showcaseTotalComments = commentsGet(
                    ["showcase_id='{$showcaseID}'"],
                    ['COUNT(comment_id) AS showcaseTotalComments'],
                    ['group_by' => 'showcase_id, comment_id']
                )['showcaseTotalComments'] ?? 0;
            }

            // Build popup menu
            $popup = new PopupMenu("myshowcase_{$showcaseID}", $lang->options);

            $popup->add_item(
                $lang->myshowcase_summary_edit,
                urlHandlerBuild(['action' => 'edit', 'showcase_id' => $showcaseID])
            );

            //grab status images at same time
            if (!empty($showcaseData['enabled'])) {
                $statusImage = "styles/{$page->style}/images/icons/bullet_on.png";

                $statusText = $lang->myshowcase_summary_status_enabled;

                if ($showcaseDataTableExists) {
                    $popup->add_item(
                        $lang->myshowcase_summary_disable,
                        urlHandlerBuild(['action' => 'disable', 'showcase_id' => $showcaseID])
                    );
                }
            } else {
                $statusImage = "styles/{$page->style}/images/icons/bullet_off.png";

                $statusText = $lang->myshowcase_summary_status_disabled;

                if ($showcaseDataTableExists) {
                    $popup->add_item(
                        $lang->myshowcase_summary_enable,
                        urlHandlerBuild(['action' => 'enable', 'showcase_id' => $showcaseID])
                    );
                }
            }

            //override status if table does not exist
            if (!$showcaseDataTableExists) {
                $statusImage = "styles/{$page->style}/images/icons/error.png";

                $statusText = $lang->myshowcase_summary_status_notable;

                $popup->add_item(
                    $lang->myshowcase_summary_createtable,
                    urlHandlerBuild(['action' => 'tableCreate', 'showcase_id' => $showcaseID])
                );
            } else //add delete table popup item
            {
                $popup->add_item(
                    $lang->myshowcase_summary_rebuildtable,
                    urlHandlerBuild(['action' => 'tableRebuild', 'showcase_id' => $showcaseID])
                );

                $popup->add_item(
                    $lang->myshowcase_summary_deletetable,
                    urlHandlerBuild(['action' => 'tableDrop', 'showcase_id' => $showcaseID])
                );
            }

            $popup->add_item(
                $lang->myshowcase_summary_seo,
                urlHandlerBuild(['action' => 'viewRewrites', 'showcase_id' => $showcaseData['showcase_id']])
            );

            $popup->add_item(
                $lang->myshowcase_summary_delete,
                urlHandlerBuild(['action' => 'delete', 'showcase_id' => $showcaseID])
            );

            $showcaseData['attachments_uploads_path'] = $showcaseData['attachments_uploads_path'] ?? $lang->myshowcase_summary_not_specified;

            $formContainer->output_cell($showcaseID, ['class' => 'align_center']);

            $formContainer->output_cell($showcaseData['name']);

            $formContainer->output_cell($showcaseData['showcase_slug']);

            $formContainer->output_cell($showcaseData['description']);

            $formContainer->output_cell($showcaseTotalEntries, ['class' => 'align_center']);

            $formContainer->output_cell($showcaseTotalComments, ['class' => 'align_center']);

            $formContainer->output_cell($showcaseTotalAttachments, ['class' => 'align_center']);

            $formContainer->output_cell(
                get_friendly_size($showcaseTotalFilesSize),
                ['class' => 'align_center']
            );

            $formContainer->output_cell($showcaseData['script_name'], ['class' => 'align_center']);

            $formContainer->output_cell($showcaseData['attachments_uploads_path'], ['class' => 'align_center']);

            $formContainer->output_cell(
                fieldsetGet(
                    ["set_id='{$fieldsetID}'"],
                    ['set_name'],
                    ['limit' => 1]
                )['set_name'] . ' (ID=' . $fieldsetID . ')',
                ['class' => 'align_center']
            );

            $formContainer->output_cell(
                '<img src="' . $statusImage . '" title="' . $statusText . '">',
                ['class' => 'align_center']
            );

            $formContainer->output_cell($popup->fetch(), ['class' => 'align_center']);

            $formContainer->construct_row();
        }
    }

    $formContainer->end();

    hooksRun('admin_summary_main_end');

    $page->output_footer();
}