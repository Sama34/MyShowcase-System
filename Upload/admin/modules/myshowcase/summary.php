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
use function MyShowcase\Core\showcaseDataGet;
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

$showcaseID = $mybb->get_input('id', MyBB::INPUT_INT);

$pageTabs = [
    'myShowcaseAdminSummary' => [
        'title' => $lang->myShowcaseAdminSummary,
        'link' => urlHandlerBuild(),
        'description' => $lang->myShowcaseAdminSummaryDescription
    ],
    'myShowcaseAdminSummaryNew' => [
        'title' => $lang->myShowcaseAdminSummaryNew,
        'link' => urlHandlerBuild(['action' => 'new']),
        'description' => $lang->myShowcaseAdminSummaryNewDescription
    ],
];

if ($mybb->get_input('action') == 'edit') {
    $pageTabs['myShowcaseAdminSummaryEdit'] = [
        'title' => $lang->myShowcaseAdminSummaryEdit,
        'link' => urlHandlerBuild(['action' => 'edit', 'id' => $showcaseID]),
        'description' => $lang->myShowcaseAdminSummaryEditDescription
    ];
}

$groupsCache = $cache->read('usergroups') ?? [];

$fieldsetObjects = (function (): array {
    return array_map(function ($fieldsetData) {
        return $fieldsetData['setname'];
    }, cacheGet(CACHE_TYPE_FIELD_SETS));
})();

hooksRun('admin_summary_start');

if ($mybb->get_input('action') === 'new') {
    hooksRun('admin_summary_new_start');

    if (!$fieldsetObjects) {
        flash_message($lang->myShowcaseAdminErrorNoFieldSets, 'error');

        admin_redirect($pageTabs['myShowcaseAdminSummary']['link']);
    }

    $page->output_header($lang->myShowcaseAdminSummary);

    $page->output_nav_tabs($pageTabs, 'myShowcaseAdminSummaryNew');

    if ($mybb->request_method === 'post') {
        if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
            flash_message($lang->myShowcaseAdminErrorInvalidPostKey, 'error');

            admin_redirect($pageTabs['myShowcaseAdminSummaryNew']['link']);
        }

        $errorMessages = [];

        if (!$mybb->get_input('name') ||
            !$mybb->get_input('showcase_slug') ||
            !$mybb->get_input('mainfile') ||
            !$mybb->get_input('imgfolder')) {
            $errorMessages[] = $lang->myShowcaseAdminErrorMissingRequiredFields;
        }

        $existingShowcases = cacheGet(CACHE_TYPE_CONFIG);

        if (in_array($mybb->get_input('name'), array_column($existingShowcases, 'name'))) {
            $errorMessages[] = $lang->myShowcaseAdminErrorDuplicatedName;
        }

        if (in_array($mybb->get_input('showcase_slug'), array_column($existingShowcases, 'showcase_slug'))) {
            $errorMessages[] = $lang->myShowcaseAdminErrorDuplicatedShowcaseSlug;
        }

        if (in_array($mybb->get_input('mainfile'), array_column($existingShowcases, 'mainfile'))) {
            $errorMessages[] = $lang->myShowcaseAdminErrorDuplicatedMainFile;
        }

        $showcaseData = [
            'name' => $db->escape_string($mybb->get_input('name')),
            'showcase_slug' => $db->escape_string($mybb->get_input('showcase_slug')),
            'description' => $db->escape_string($mybb->get_input('description')),
            'mainfile' => $db->escape_string($mybb->get_input('mainfile')),
            'imgfolder' => $db->escape_string($mybb->get_input('imgfolder')),
            'f2gpath' => $db->escape_string($mybb->get_input('f2gpath')),
            'fieldsetid' => $mybb->get_input('newfieldset', MyBB::INPUT_INT),
        ];

        if ($errorMessages) {
            $page->output_inline_error($errorMessages);
        } else {
            $showcaseData = hooksRun('admin_summary_new_post', $showcaseData);

            $showcaseID = showcaseInsert($showcaseData);

            $defaultShowcasePermissions = showcaseDefaultPermissions();

            foreach ($groupsCache as $groupData) {
                $defaultShowcasePermissions['id'] = $showcaseID;

                $defaultShowcasePermissions['gid'] = (int)$groupData['gid'];

                permissionsInsert($defaultShowcasePermissions);
            }

            cacheUpdate(CACHE_TYPE_CONFIG);

            cacheUpdate(CACHE_TYPE_PERMISSIONS);

            log_admin_action(['showcaseID' => $showcaseID]);

            flash_message($lang->myShowcaseAdminSuccessNewShowcase, 'success');

            admin_redirect($pageTabs['myShowcaseAdminSummary']['link']);
        }
    }

    $form = new Form($pageTabs['myShowcaseAdminSummaryNew']['link'], 'post', 'myShowcaseAdminSummaryNew');

    echo $form->generate_hidden_field('my_post_key', $mybb->post_code);

    $formContainer = new FormContainer($pageTabs['myShowcaseAdminSummaryNew']['title']);

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryNewFormName . '<em>*</em>',
        $lang->myShowcaseAdminSummaryNewFormNameDescription,
        $form->generate_text_box('name', $mybb->get_input('name'), ['id' => 'name']),
        'name'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryNewFormMShowcaseSlug . '<em>*</em>',
        $lang->myShowcaseAdminSummaryNewFormMShowcaseSlugDescription,
        $form->generate_text_box('showcase_slug', $mybb->get_input('showcase_slug'), ['id' => 'showcase_slug']),
        'showcase_slug'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryNewFormDescription,
        $lang->myShowcaseAdminSummaryNewFormDescriptionDescription,
        $form->generate_text_box('description', $mybb->get_input('description'), ['id' => 'description']),
        'description'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryNewFormMainFile . '<em>*</em>',
        $lang->myShowcaseAdminSummaryNewFormMainFileDescription,
        $form->generate_text_box('mainfile', $mybb->get_input('mainfile'), ['id' => 'mainfile']),
        'mainfile'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryNewFormImageFolder . '<em>*</em>',
        $lang->myShowcaseAdminSummaryNewFormImageFolderDescription,
        $form->generate_text_box('imgfolder', $mybb->get_input('imgfolder'), ['id' => 'imgfolder']),
        'imgfolder'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryNewFormRelativePath . '<em>*</em>',
        $lang->myShowcaseAdminSummaryNewFormRelativePathDescription,
        $form->generate_text_box('f2gpath', $mybb->get_input('f2gpath'), ['id' => 'f2gpath']),
        'f2gpath'
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryNewFormFieldSet . '<em>*</em>',
        $lang->myShowcaseAdminSummaryNewFormFieldSetDescription,
        $form->generate_select_box(
            'newfieldset',
            $fieldsetObjects,
            $mybb->get_input('newfieldset', MyBB::INPUT_INT),
            ['id' => 'newfieldset']
        ),
        'newfieldset'
    );

    hooksRun('admin_summary_new_end');

    $formContainer->end();

    $form->output_submit_wrapper([
        $form->generate_submit_button($lang->myShowcaseAdminButtonSubmit),
        $form->generate_reset_button($lang->myShowcaseAdminButtonReset)
    ]);

    $form->end();

    $page->output_footer();
} elseif ($mybb->get_input('action') == 'edit') {
    $showcaseData = showcaseGet(["id='{$showcaseID}'"], array_keys(TABLES_DATA['myshowcase_config']), ['limit' => 1]);

    if (!$showcaseData) {
        flash_message($lang->myShowcaseAdminErrorInvalidShowcase, 'error');

        admin_redirect($pageTabs['myShowcaseAdminSummary']['link']);
    }

    $page->add_breadcrumb_item(
        $lang->myShowcaseAdminSummaryEdit,
        urlHandlerBuild(['action' => 'edit', 'id' => $showcaseID])
    );

    $page->output_header($lang->myShowcaseAdminSummaryEdit);

    $page->output_nav_tabs($pageTabs, 'myShowcaseAdminSummaryEdit');

    if ($mybb->request_method == 'post') {
        $mybb->input['showcase_slug'] = preg_replace(
            '/[^\da-z]/i',
            '-',
            my_strtolower($mybb->get_input('showcase_slug'))
        );

        switch ($mybb->get_input('type')) {
            case 'main':
                $errorMessages = [];

                if (!$mybb->get_input('name') ||
                    !$mybb->get_input('showcase_slug') ||
                    !$mybb->get_input('mainfile') ||
                    !$mybb->get_input('imgfolder')) {
                    $errorMessages[] = $lang->myShowcaseAdminErrorMissingRequiredFields;
                }

                $existingShowcases = cacheGet(CACHE_TYPE_CONFIG);

                if (in_array($mybb->get_input('name'), array_column($existingShowcases, 'name')) && (function (
                        string $showcaseName
                    ) use ($showcaseID, $existingShowcases): bool {
                        $duplicatedName = false;

                        foreach ($existingShowcases as $showcaseData) {
                            if ($showcaseData['name'] === $showcaseName && $showcaseData['id'] !== $showcaseID) {
                                $duplicatedName = true;
                            }
                        }

                        return $duplicatedName;
                    })(
                        $mybb->get_input('name')
                    )) {
                    $errorMessages[] = $lang->myShowcaseAdminErrorDuplicatedName;
                }

                if (in_array(
                        $mybb->get_input('showcase_slug'),
                        array_column($existingShowcases, 'showcase_slug')
                    ) && (function (
                        string $showcaseName
                    ) use ($showcaseID, $existingShowcases): bool {
                        $duplicatedName = false;

                        foreach ($existingShowcases as $showcaseData) {
                            if ($showcaseData['showcase_slug'] === $showcaseName && $showcaseData['id'] !== $showcaseID) {
                                $duplicatedName = true;
                            }
                        }

                        return $duplicatedName;
                    })(
                        $mybb->get_input('showcase_slug')
                    )) {
                    $errorMessages[] = $lang->myShowcaseAdminErrorDuplicatedMainFile;
                }

                if (in_array($mybb->get_input('mainfile'), array_column($existingShowcases, 'mainfile')) && (function (
                        string $showcaseName
                    ) use ($showcaseID, $existingShowcases): bool {
                        $duplicatedName = false;

                        foreach ($existingShowcases as $showcaseData) {
                            if ($showcaseData['mainfile'] === $showcaseName && $showcaseData['id'] !== $showcaseID) {
                                $duplicatedName = true;
                            }
                        }

                        return $duplicatedName;
                    })(
                        $mybb->get_input('mainfile')
                    )) {
                    $errorMessages[] = $lang->myShowcaseAdminErrorDuplicatedMainFile;
                }


                if ($errorMessages) {
                    $page->output_inline_error($errorMessages);
                } else {
                    $showcaseData = [
                        'name' => $db->escape_string($mybb->get_input('name')),
                        'showcase_slug' => $db->escape_string($mybb->get_input('showcase_slug')),
                        'description' => $db->escape_string($mybb->get_input('description')),
                        'mainfile' => $db->escape_string($mybb->get_input('mainfile')),
                        'imgfolder' => $db->escape_string($mybb->get_input('imgfolder')),
                        'defaultimage' => $db->escape_string($mybb->get_input('defaultimage')),
                        'watermarkimage' => $db->escape_string($mybb->get_input('watermarkimage')),
                        'watermarkloc' => $mybb->get_input('watermarkloc'),
                        'use_attach' => $mybb->get_input('use_attach', MyBB::INPUT_INT),
                        'f2gpath' => $db->escape_string($mybb->get_input('f2gpath'))
                    ];

                    if (!showcaseDataTableExists($showcaseID)) {
                        $showcaseData = array_merge(
                            $showcaseData,
                            ['fieldsetid' => $mybb->get_input('fieldsetid', MyBB::INPUT_INT)]
                        );
                    }

                    $showcaseData = hooksRun('admin_summary_edit_main', $showcaseData);

                    showcaseUpdate($showcaseID, $showcaseData);

                    cacheUpdate(CACHE_TYPE_CONFIG);

                    log_admin_action(['showcaseID' => $showcaseID, 'type' => $mybb->get_input('type')]);

                    flash_message($lang->myShowcaseAdminSuccessShowcaseEditMain, 'success');

                    admin_redirect(
                        urlHandlerBuild(['action' => 'edit', 'type' => 'main', 'id' => $showcaseID]) . '#tab_main'
                    );
                }

                break;
            case 'other':
                $showcaseData = [
                    'modnewedit' => $mybb->get_input('modnewedit', MyBB::INPUT_INT),
                    'othermaxlength' => $mybb->get_input('othermaxlength', MyBB::INPUT_INT),
                    'allow_attachments' => $mybb->get_input('allow_attachments', MyBB::INPUT_INT),
                    'allow_comments' => $mybb->get_input('allow_comments', MyBB::INPUT_INT),
                    'thumb_width' => $mybb->get_input('thumb_width', MyBB::INPUT_INT),
                    'thumb_height' => $mybb->get_input('thumb_height', MyBB::INPUT_INT),
                    'comment_length' => $mybb->get_input('comment_length', MyBB::INPUT_INT),
                    'comment_dispinit' => $mybb->get_input('comment_dispinit', MyBB::INPUT_INT),
                    'disp_attachcols' => $mybb->get_input('disp_attachcols', MyBB::INPUT_INT),
                    'disp_empty' => $mybb->get_input('disp_empty', MyBB::INPUT_INT),
                    'link_in_postbit' => $mybb->get_input('link_in_postbit', MyBB::INPUT_INT),
                    'portal_random' => $mybb->get_input('portal_random', MyBB::INPUT_INT),
                    'display_signatures' => $mybb->get_input('display_signatures', MyBB::INPUT_INT),
                    'prunetime' => $db->escape_string(
                        $mybb->get_input('prunetime', MyBB::INPUT_INT) . '|' . $mybb->get_input('interval')
                    ),
                    'allowsmilies' => $mybb->get_input('allowsmilies', MyBB::INPUT_INT),
                    'allowbbcode' => $mybb->get_input('allowbbcode', MyBB::INPUT_INT),
                    'allowhtml' => $mybb->get_input('allowhtml', MyBB::INPUT_INT),
                ];

                $showcaseData = hooksRun('admin_summary_edit_other', $showcaseData);

                showcaseUpdate($showcaseID, $showcaseData);

                cacheUpdate(CACHE_TYPE_CONFIG);

                log_admin_action(['showcaseID' => $showcaseID, 'type' => $mybb->get_input('type')]);

                flash_message($lang->myShowcaseAdminSuccessShowcaseEditOther, 'success');

                admin_redirect(
                    urlHandlerBuild(['action' => 'edit', 'type' => 'other', 'id' => $showcaseID]) . '#tab_other'
                );

                break;
            case 'permissions':
                $permissionsInput = $mybb->get_input('permissions', MyBB::INPUT_ARRAY);

                foreach ($groupsCache as $groupData) {
                    $groupPermissions = $permissionsInput[$groupData['gid']] ?? [];

                    $permissionsData = [];

                    foreach (showcaseDefaultPermissions() as $permissionKey => $permissionValue) {
                        $permissionsData[$permissionKey] = (int)($groupPermissions[$permissionKey] ?? 0);
                    }

                    $groupID = (int)$groupData['gid'];

                    $permissionsData = hooksRun('admin_summary_edit_permissions', $permissionsData);

                    permissionsUpdate(["id='{$showcaseID}'", "gid='{$groupID}'"], $permissionsData);
                }

                cacheUpdate(CACHE_TYPE_PERMISSIONS);

                log_admin_action(['showcaseID' => $showcaseID, 'type' => $mybb->get_input('type')]);

                flash_message($lang->myShowcaseAdminSuccessShowcaseEditPermissions, 'success');

                admin_redirect(
                    urlHandlerBuild(
                        ['action' => 'edit', 'type' => 'permissions', 'id' => $showcaseID]
                    ) . '#tab_permissions'
                );

                break;
            case 'moderators':
                if ($mybb->get_input('edit') == 'permissions') {
                    $moderatorPermissions = $mybb->get_input('permissions', MyBB::INPUT_ARRAY);

                    foreach ($moderatorPermissions as $moderatorID => $permissions) {
                        $permissions = array_map('intval', $permissions);

                        $moderatorData = [
                            ModeratorPermissions::CanApproveEntries => $permissions[ModeratorPermissions::CanApproveEntries] ?? 0,
                            ModeratorPermissions::CanEditEntries => $permissions[ModeratorPermissions::CanEditEntries] ?? 0,
                            ModeratorPermissions::CanDeleteEntries => $permissions[ModeratorPermissions::CanDeleteEntries] ?? 0,
                            ModeratorPermissions::CanDeleteComments => $permissions[ModeratorPermissions::CanDeleteComments] ?? 0,
                        ];

                        moderatorsUpdate(["id='{$showcaseID}'", "mid='{$moderatorID}'"], $moderatorData);
                    }
                } elseif ($mybb->get_input('add') == 'group') {
                    $groupID = $mybb->get_input('usergroup', MyBB::INPUT_INT);

                    $query = $db->simple_select('usergroups', 'gid', "gid='{$groupID}'");

                    if (!$db->num_rows($query)) {
                        flash_message($lang->myShowcaseAdminErrorInvalidGroup, 'error');

                        admin_redirect(
                            urlHandlerBuild(
                                ['action' => 'edit', 'type' => 'moderators', 'id' => $showcaseID]
                            ) . '#tab_moderators'
                        );
                    }

                    $moderatorData = [
                        'id' => $showcaseID,
                        'uid' => $groupID,
                        'isgroup' => MODERATOR_TYPE_GROUP,
                        ModeratorPermissions::CanApproveEntries => $mybb->get_input(
                            'gcanmodapprove',
                            MyBB::INPUT_INT
                        ),
                        ModeratorPermissions::CanEditEntries => $mybb->get_input(
                            'gcanmodedit',
                            MyBB::INPUT_INT
                        ),
                        ModeratorPermissions::CanDeleteEntries => $mybb->get_input(
                            'gcanmoddelete',
                            MyBB::INPUT_INT
                        ),
                        ModeratorPermissions::CanDeleteComments => $mybb->get_input(
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
                                ['action' => 'edit', 'type' => 'moderators', 'id' => $showcaseID]
                            ) . '#tab_moderators'
                        );
                    }

                    $moderatorData = [
                        'id' => $showcaseID,
                        'uid' => (int)$userData['uid'],
                        'isgroup' => MODERATOR_TYPE_USER,
                        ModeratorPermissions::CanApproveEntries => $mybb->get_input(
                            'ucanmodapprove',
                            MyBB::INPUT_INT
                        ),
                        ModeratorPermissions::CanEditEntries => $mybb->get_input(
                            'ucanmodedit',
                            MyBB::INPUT_INT
                        ),
                        ModeratorPermissions::CanDeleteEntries => $mybb->get_input(
                            'ucanmoddelete',
                            MyBB::INPUT_INT
                        ),
                        ModeratorPermissions::CanDeleteComments => $mybb->get_input(
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
                    urlHandlerBuild(['action' => 'edit', 'type' => 'moderators', 'id' => $showcaseID]
                    ) . '#tab_moderators'
                );

                break;
            case 'delete':
                moderatorsDelete(["mid={$mybb->get_input('mid', MyBB::INPUT_INT)}"]);

                cacheUpdate(CACHE_TYPE_MODERATORS);

                log_admin_action(['showcaseID' => $showcaseID, 'type' => $mybb->get_input('type')]);

                flash_message($lang->myShowcaseAdminSuccessShowcaseEditModeratorDelete, 'success');

                admin_redirect(
                    urlHandlerBuild(['action' => 'edit', 'type' => 'moderators', 'id' => $showcaseID]
                    ) . '#tab_moderators'
                );
                break;
        }
    }

    $tabs = [
        'main' => $lang->myshowcase_admin_main_options,
        'other' => $lang->myshowcase_admin_other_options,
        'permissions' => $lang->myshowcase_admin_permissions,
        'moderators' => $lang->myshowcase_admin_moderators
    ];

    $page->output_tab_control($tabs);

    $permissionsCache = cacheGet(CACHE_TYPE_PERMISSIONS);

    //myshowcase_get_group_permissions($showcaseID);
    $groupPermissions = $permissionsCache[$showcaseID];

    hooksRun('admin_summary_edit_start');

    $mybb->input = array_merge($showcaseData, $mybb->input);

    //main options tab
    echo "<div id=\"tab_main\">\n";

    $form = new Form(
        urlHandlerBuild(['action' => 'edit', 'type' => 'main', 'id' => $showcaseID]) . '#tab_main',
        'post',
        'edit'
    );

    $formContainer = new FormContainer($lang->myshowcase_admin_main_options);

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryNewFormName,
        $lang->myShowcaseAdminSummaryNewFormNameDescription,
        $form->generate_text_box(
            'name',
            $mybb->get_input('name'),
            ['id' => 'name'],
        )
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryNewFormMShowcaseSlug,
        $lang->myShowcaseAdminSummaryNewFormMShowcaseSlugDescription,
        $form->generate_text_box(
            'showcase_slug',
            $mybb->get_input('showcase_slug'),
            ['id' => 'showcase_slug'],
        )
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryNewFormDescription,
        $lang->myShowcaseAdminSummaryNewFormDescriptionDescription,
        $form->generate_text_box(
            'description',
            $mybb->get_input('description'),
            ['id' => 'description'],
        )
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryNewFormMainFile,
        $lang->myShowcaseAdminSummaryNewFormMainFileDescription,
        $form->generate_text_box(
            'mainfile',
            $mybb->get_input('mainfile'),
            ['id' => 'mainfile'],
        )
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryNewFormImageFolder,
        $lang->myShowcaseAdminSummaryNewFormImageFolderDescription,
        $form->generate_text_box(
            'imgfolder',
            $mybb->get_input('imgfolder'),
            ['id' => 'imgfolder', 'style' => 'width: 250px', 'class' => 'align_left'],
        )
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryEditFormDefaultListImage,
        $lang->myShowcaseAdminSummaryEditFormDefaultListImageDescription,
        $form->generate_text_box(
            'defaultimage',
            $mybb->get_input('defaultimage'),
            ['id' => 'defaultimage'],
        )
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryEditFormWaterMarkImage,
        $lang->myShowcaseAdminSummaryEditFormWaterMarkImageDescription,
        $form->generate_text_box(
            'watermarkimage',
            $mybb->get_input('watermarkimage'),
            ['id' => 'watermarkimage'],
        )
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryEditFormWaterMarkLocation,
        $lang->myShowcaseAdminSummaryEditFormWaterMarkLocationDescription,
        $form->generate_select_box(
            'watermarkloc',
            [
                'upper-left' => $lang->myShowcaseAdminSummaryEditFormWaterMarkLocationUpperLeft,
                'upper-right' => $lang->myShowcaseAdminSummaryEditFormWaterMarkLocationUpperRight,
                'center' => $lang->myShowcaseAdminSummaryEditFormWaterMarkLocationCenter,
                'lower-left' => $lang->myShowcaseAdminSummaryEditFormWaterMarkLocationLowerLeft,
                'lower-right' => $lang->myShowcaseAdminSummaryEditFormWaterMarkLocationLowerRight
            ],
            $mybb->get_input('watermarkloc'),
            ['id' => 'watermarkloc']
        )
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryEditFormListViewLink,
        $lang->myShowcaseAdminSummaryEditFormListViewLinkDescription,
        $form->generate_check_box(
            'use_attach',
            1,
            $lang->myShowcaseAdminSummaryEditFormListViewLinkUseAttach,
            ['checked' => $mybb->get_input('use_attach', MyBB::INPUT_INT)]
        )
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryNewFormRelativePath,
        $lang->myShowcaseAdminSummaryNewFormRelativePathDescription,
        $form->generate_text_box(
            'f2gpath',
            $mybb->get_input('f2gpath'),
            ['id' => 'f2gpath'],
        )
    );

    if (!showcaseDataTableExists($showcaseID)) {
        $formContainer->output_row(
            $lang->myShowcaseAdminSummaryNewFormFieldSet,
            $lang->myShowcaseAdminSummaryNewFormFieldSetDescription,
            $form->generate_select_box(
                'fieldsetid',
                $fieldsetObjects,
                $mybb->get_input('fieldsetid', MyBB::INPUT_INT),
                ['id' => 'fieldsetid']
            )
        );
    }

    $formContainer->end();

    $form->output_submit_wrapper([
        $form->generate_submit_button($lang->myShowcaseAdminButtonSubmit),
        $form->generate_reset_button($lang->myShowcaseAdminButtonReset)
    ]);

    $form->end();

    echo "</div>\n";

    //other options tab
    echo "<div id=\"tab_other\">\n";

    $form = new Form(
        urlHandlerBuild(['action' => 'edit', 'type' => 'other', 'id' => $showcaseID]) . '#tab_other',
        'post',
        'edit'
    );

    $formContainer = new FormContainer($lang->myshowcase_admin_other_options);

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryEditFormPruning,
        $lang->myShowcaseAdminSummaryEditFormPruningDescription,
        $form->generate_numeric_field(
            'prunetime',
            $mybb->get_input('prunetime', MyBB::INPUT_INT),
            ['id' => 'prunetime', 'class' => 'field150', 'min' => 0]
        ) . ' ' . $form->generate_select_box(
            'interval',
            [
                'days' => $lang->days,
                'weeks' => $lang->weeks,
                'months' => $lang->months,
                'years' => $lang->years
            ],
            $mybb->get_input('interval', MyBB::INPUT_INT),
            ['id' => 'interval']
        )
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryEditFormModerationOptions,
        $lang->myShowcaseAdminSummaryEditFormModerationOptionsDescription,
        $form->generate_check_box(
            'modnewedit',
            1,
            $lang->myShowcaseAdminSummaryEditFormModerationOptionsNewEdits,
            ['checked' => $mybb->get_input('modnewedit', MyBB::INPUT_INT)]
        )
    );

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryEditFormTextTypeFields,
        $lang->myShowcaseAdminSummaryEditFormTextTypeFieldsDescription,
        $lang->myShowcaseAdminSummaryEditFormTextTypeFieldsMaxCharacters . "<br />\n" . $form->generate_numeric_field(
            'othermaxlength',
            $mybb->get_input('othermaxlength', MyBB::INPUT_INT),
            ['id' => 'othermaxlength', 'class' => 'field150', 'min' => 0]
        )
    );

    $rowOptions = [
        $form->generate_check_box(
            'allow_attachments',
            1,
            $lang->myShowcaseAdminSummaryEditFormAttachmentsAllow,
            ['checked' => $mybb->get_input('allow_attachments', MyBB::INPUT_INT)]
        ) . '<br /><br />',
        $lang->myShowcaseAdminSummaryEditFormAttachmentsThumbnailWidth . "<br />\n" . $form->generate_numeric_field(
            'thumb_width',
            $mybb->get_input('thumb_width', MyBB::INPUT_INT),
            ['id' => 'thumb_width', 'class' => 'field150', 'min' => 0]
        ),
        $lang->myShowcaseAdminSummaryEditFormAttachmentsThumbnailHeight . "<br />\n" . $form->generate_numeric_field(
            'thumb_height',
            $mybb->get_input('thumb_height', MyBB::INPUT_INT),
            ['id' => 'thumb_height', 'class' => 'field150', 'min' => 0]
        )
    ];

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryEditFormAttachments,
        $lang->myShowcaseAdminSummaryEditFormAttachmentsDescription,
        "<div class=\"group_settings_bit\">" . implode(
            "</div><div class=\"group_settings_bit\">",
            $rowOptions
        ) . '</div>'
    );

    $rowOptions = [
        $form->generate_check_box(
            'allow_comments',
            1,
            $lang->myShowcaseAdminSummaryEditFormCommentsAllow,
            ['checked' => $mybb->get_input('allow_comments', MyBB::INPUT_INT)]
        ) . '<br /><br />',
        $lang->myShowcaseAdminSummaryEditFormCommentsMaxCharacters . "<br />\n" . $form->generate_numeric_field(
            'comment_length',
            $mybb->get_input('comment_length', MyBB::INPUT_INT),
            ['id' => 'comment_length', 'class' => 'field150', 'min' => 0]
        )
    ];

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryEditFormComments,
        $lang->myShowcaseAdminSummaryEditFormCommentsDescription,
        "<div class=\"group_settings_bit\">" . implode(
            "</div><div class=\"group_settings_bit\">",
            $rowOptions
        ) . '</div>'
    );

    $rowOptions = [
        $form->generate_check_box(
            'allowsmilies',
            1,
            $lang->myShowcaseAdminSummaryEditParserOptionsAllowSmiles,
            ['checked' => $mybb->get_input('allowsmilies', MyBB::INPUT_INT)]
        ),
        $form->generate_check_box(
            'allowbbcode',
            1,
            $lang->myShowcaseAdminSummaryEditParserOptionsAllowMyCode,
            ['checked' => $mybb->get_input('allowbbcode', MyBB::INPUT_INT)]
        ),
        $form->generate_check_box(
            'allowhtml',
            1,
            $lang->myShowcaseAdminSummaryEditParserOptionsAllowHtml,
            ['checked' => $mybb->get_input('allowhtml', MyBB::INPUT_INT)]
        ),
    ];

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryEditParserOptions,
        $lang->myShowcaseAdminSummaryEditParserOptionsDescription,
        "<div class=\"group_settings_bit\">" . implode(
            "</div><div class=\"group_settings_bit\">",
            $rowOptions
        ) . '</div>'
    );

    $rowOptions = [
        $lang->myShowcaseAdminSummaryEditDisplaySettingsAttachmentColumns . "<br />\n" . $form->generate_numeric_field(
            'disp_attachcols',
            $mybb->get_input('disp_attachcols', MyBB::INPUT_INT),
            ['id' => 'disp_attachcols', 'class' => 'field150', 'min' => 0]
        ),
        $lang->myShowcaseAdminSummaryEditDisplaySettingsCommentsPerPage . "<br />\n" . $form->generate_numeric_field(
            'comment_dispinit',
            $mybb->get_input('comment_dispinit', MyBB::INPUT_INT),
            ['id' => 'comment_dispinit', 'class' => 'field150', 'min' => 0]
        ) . '<br /><br />',
        $form->generate_check_box(
            'disp_empty',
            1,
            $lang->myShowcaseAdminSummaryEditDisplaySettingsDisplayEmptyFields,
            ['checked' => $mybb->get_input('disp_empty', MyBB::INPUT_INT)]
        ),
        $form->generate_check_box(
            'link_in_postbit',
            1,
            $lang->myShowcaseAdminSummaryEditDisplaySettingsDisplayInPosts,
            ['checked' => $mybb->get_input('link_in_postbit', MyBB::INPUT_INT)]
        ),
        $form->generate_check_box(
            'portal_random',
            1,
            $lang->myShowcaseAdminSummaryEditDisplaySettingsDisplayRandomEntry,
            ['checked' => $mybb->get_input('portal_random', MyBB::INPUT_INT)]
        ),
        $form->generate_check_box(
            'display_signatures',
            1,
            $lang->myShowcaseAdminSummaryEditParserOptionsAllowSignatures,
            ['checked' => $mybb->get_input('display_signatures', MyBB::INPUT_INT)]
        ),
    ];

    $formContainer->output_row(
        $lang->myShowcaseAdminSummaryEditDisplaySettings,
        $lang->myShowcaseAdminSummaryEditDisplaySettingsDescription,
        "<div class=\"group_settings_bit\">" . implode(
            "</div><div class=\"group_settings_bit\">",
            $rowOptions
        ) . '</div>'
    );

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
        urlHandlerBuild(['action' => 'edit', 'type' => 'permissions', 'id' => $showcaseID]) . '#tab_permissions',
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

    reset($groupsCache);

    //reset($defaultShowcasePermissions);

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
        urlHandlerBuild(['action' => 'edit', 'type' => 'moderators', 'id' => $showcaseID]) . '#tab_moderators',
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
        ["id='{$showcaseID}'"],
        [
            'mid',
            'uid',
            'isgroup',
            ModeratorPermissions::CanApproveEntries,
            ModeratorPermissions::CanEditEntries,
            ModeratorPermissions::CanDeleteEntries,
            ModeratorPermissions::CanDeleteComments
        ],
        ['order_by' => 'isgroup']
    );

    while ($moderatorData = $db->fetch_array($query)) {
        if (!empty($moderatorData['isgroup'])) {
            $moderatorData['img'] = "<img src=\"styles/{$page->style}/images/icons/group.png\" alt=\"{$lang->myshowcase_moderators_group}\" title=\"{$lang->myshowcase_moderators_group}\" />";

            foreach ($groupsCache as $groupData) {
                if ($groupData['gid'] == $moderatorData['uid']) {
                    $moderatorData['title'] = $groupData['title'];
                }
            }

            $formContainer->output_cell(
                "{$moderatorData['img']} <a href=\"index.php?module=user-groups&amp;action=edit&amp;gid={$moderatorData['id']}\">" . htmlspecialchars_uni(
                    $moderatorData['title']
                ) . '</a>'
            );
        } else {
            $moderatorData['img'] = "<img src=\"styles/{$page->style}/images/icons/user.png\" alt=\"{$lang->myshowcase_moderators_user}\" title=\"{$lang->myshowcase_moderators_user}\" />";

            $userData = get_user($moderatorData['uid']);

            $formContainer->output_cell(
                "{$moderatorData['img']} <a href=\"index.php?module=user-users&amp;action=edit&amp;uid={$moderatorData['id']}\">" . htmlspecialchars_uni(
                    $userData['username']
                ) . '</a>'
            );
        }

        $formContainer->output_cell(
            $form->generate_check_box(
                "permissions[{$moderatorData['mid']}][canmodapprove]",
                1,
                '',
                [
                    'checked' => $moderatorData[ModeratorPermissions::CanApproveEntries],
                    'id' => "modapprove{$moderatorData['mid']}"
                ]
            ),
            ['class' => 'align_center']
        );

        $formContainer->output_cell(
            $form->generate_check_box(
                "permissions[{$moderatorData['mid']}][canmodedit]",
                1,
                '',
                [
                    'checked' => $moderatorData[ModeratorPermissions::CanEditEntries],
                    'id' => "modedit{$moderatorData['mid']}"
                ]
            ),
            ['class' => 'align_center']
        );

        $formContainer->output_cell(
            $form->generate_check_box(
                "permissions[{$moderatorData['mid']}][canmoddelete]",
                1,
                '',
                [
                    'checked' => $moderatorData[ModeratorPermissions::CanDeleteEntries],
                    'id' => "moddelete{$moderatorData['mid']}"
                ]
            ),
            ['class' => 'align_center']
        );

        $formContainer->output_cell(
            $form->generate_check_box(
                "permissions[{$moderatorData['mid']}][canmoddelcomment]",
                1,
                '',
                [
                    'checked' => $moderatorData[ModeratorPermissions::CanDeleteComments],
                    'id' => "moddelcomment{$moderatorData['mid']}"
                ]
            ),
            ['class' => 'align_center']
        );

        $deleteModeratorUrl = urlHandlerBuild([
                'action' => 'edit',
                'type' => 'delete',
                'id' => $showcaseID,
                'mid' => $moderatorData['mid'],
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
        urlHandlerBuild(['action' => 'edit', 'type' => 'moderators', 'id' => $showcaseID]) . '#tab_moderators',
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

    $form->output_submit_wrapper([$form->generate_submit_button($lang->myShowcaseAdminButtonSubmitAddModeratorGroup)]);

    $form->end();

    echo '<br />';

    //add Users
    $form = new Form(
        urlHandlerBuild(['action' => 'edit', 'type' => 'moderators', 'id' => $showcaseID]) . '#tab_moderators',
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

    $form->output_submit_wrapper([$form->generate_submit_button($lang->myShowcaseAdminButtonSubmitAddModeratorUser)]);

    $form->end();

    echo "</div>\n";

    hooksRun('admin_summary_edit_end');

    $page->output_footer();
} elseif (in_array($mybb->get_input('action'), ['enable', 'disable'])) {
    $enableShowcase = $mybb->get_input('action') === 'enable';

    $showcaseData = showcaseGet(["id='{$showcaseID}'"], [], ['limit' => 1]);

    if (!$showcaseData) {
        flash_message($lang->myShowcaseAdminErrorInvalidShowcase, 'error');

        admin_redirect($pageTabs['myShowcaseAdminSummary']['link']);
    }

    $showcaseData = ['enabled' => $enableShowcase ? SHOWCASE_STATUS_ENABLED : SHOWCASE_STATUS_DISABLED];

    hooksRun('admin_summary_enable_disable');

    showcaseUpdate($showcaseID, $showcaseData);

    log_admin_action(['showcaseID' => $showcaseID, 'enableShowcase' => $enableShowcase]);

    cacheUpdate(CACHE_TYPE_CONFIG);

    flash_message(
        $enableShowcase ? $lang->myShowcaseAdminSuccessEnabledShowcase : $lang->myShowcaseAdminSuccessDisabledShowcase,
        'success'
    );

    admin_redirect($pageTabs['myShowcaseAdminSummary']['link']);
} elseif (in_array($mybb->get_input('action'), ['tableCreate', 'tableRebuild'])) {
    $createTable = $mybb->get_input('action') === 'tableCreate';

    $showcaseData = showcaseGet(["id='{$showcaseID}'"], ['fieldsetid'], ['limit' => 1]);

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
    $showcaseData = showcaseGet(["id='{$showcaseID}'"], ['name'], ['limit' => 1]);

    if (!$showcaseData) {
        flash_message($lang->myShowcaseAdminErrorInvalidShowcase, 'error');

        admin_redirect(urlHandlerBuild());
    }

    hooksRun('admin_summary_table_drop_start');

    $showcaseName = $showcaseData['name'];

    if (showcaseDataGet($showcaseID)) {
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

            showcaseUpdate($showcaseID, ['enabled' => 0]);
        }

        flash_message($lang->myShowcaseAdminSuccessTableDropped, 'success');

        admin_redirect(urlHandlerBuild());
    }

    $page->output_confirm_action(
        urlHandlerBuild(['action' => 'tableDrop', 'id' => $showcaseID]),
        $lang->sprintf($lang->myShowcaseAdminConfirmTableDrop, $showcaseName)
    );
} elseif ($mybb->get_input('action') === 'viewRewrites') {
    $showcaseData = showcaseGet(["id='{$showcaseID}'"], ['name', 'showcase_slug', 'mainfile'], ['limit' => 1]);

    if (!$showcaseData) {
        flash_message($lang->myShowcaseAdminErrorInvalidShowcase, 'error');

        admin_redirect($pageTabs['myShowcaseAdminSummary']['link']);
    }

    $page->add_breadcrumb_item(
        $lang->myshowcase_admin_show_seo,
        urlHandlerBuild(['action' => 'viewRewrites', 'id' => $showcaseID])
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
        $showcaseData['mainfile']
    );

    echo $lang->sprintf(
        $lang->myShowcaseAdminSummaryViewRewritesView,
        $showcaseName,
        $showcaseData['mainfile']
    );

    echo $lang->sprintf(
        $lang->myShowcaseAdminSummaryViewRewritesNew,
        $showcaseName,
        $showcaseData['mainfile']
    );

    echo $lang->sprintf(
        $lang->myShowcaseAdminSummaryViewRewritesAttachment,
        $showcaseName,
        $showcaseData['mainfile']
    );

    echo $lang->sprintf(
        $lang->myShowcaseAdminSummaryViewRewritesEntry,
        $showcaseName,
        $showcaseData['mainfile']
    );

    $page->output_footer();
} elseif ($mybb->get_input('action') == 'delete') {
    $showcaseData = showcaseGet(["id='{$showcaseID}'"], [], ['limit' => 1]);

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

        commentsDelete(["id='{$showcaseID}'"]);

        attachmentDelete(["id='{$showcaseID}'"]);

        permissionsDelete(["id='{$showcaseID}'"]);

        moderatorsDelete(["id='{$showcaseID}'"]);

        if (showcaseDataTableExists($showcaseID)) {
            showcaseDataTableDrop($showcaseID);
        }

        showcaseDelete(["id='{$showcaseID}'"]);

        cacheUpdate(CACHE_TYPE_CONFIG);

        cacheUpdate(CACHE_TYPE_PERMISSIONS);

        log_admin_action(['showcaseID' => $showcaseID]);

        if (showcaseGet(["id='{$showcaseID}'"], [], ['limit' => 1])) {
            flash_message($lang->myShowcaseAdminErrorShowcaseDelete, 'error');
        } else {
            flash_message($lang->myShowcaseAdminSuccessShowcaseDeleted, 'success');
        }

        admin_redirect(urlHandlerBuild());
    }

    $page->output_confirm_action(
        urlHandlerBuild(['action' => 'delete', 'id' => $showcaseID]),
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
        $lang->myshowcase_summary_forum_folder,
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

            $fieldsetID = (int)$showcaseData['fieldsetid'];

            $showcaseDataTableExists = showcaseDataTableExists($showcaseID);

            if ($showcaseDataTableExists) {
                $showcaseTotalEntries = showcaseDataGet(
                    $showcaseID,
                    [],
                    ['COUNT(gid) AS showcaseTotalEntries'],
                    ['group_by' => 'gid']
                )['showcaseTotalEntries'] ?? 0;

                $showcaseTotalAttachments = attachmentGet(
                    ["id='{$showcaseID}'"],
                    ['COUNT(aid) AS showcaseTotalAttachments'],
                    ['group_by' => 'id, aid']
                )['showcaseTotalAttachments'] ?? 0;

                $showcaseTotalFilesSize = attachmentGet(
                    ["id='{$showcaseID}'"],
                    ['SUM(filesize) AS showcaseTotalFilesSize'],
                    ['group_by' => 'id, aid, filesize']
                )['showcaseTotalFilesSize'] ?? 0;

                $showcaseTotalComments = commentsGet(
                    ["id='{$showcaseID}'"],
                    ['COUNT(cid) AS showcaseTotalComments'],
                    ['group_by' => 'id, cid']
                )['showcaseTotalComments'] ?? 0;
            }

            // Build popup menu
            $popup = new PopupMenu("myshowcase_{$showcaseID}", $lang->options);

            $popup->add_item(
                $lang->myshowcase_summary_edit,
                urlHandlerBuild(['action' => 'edit', 'id' => $showcaseID])
            );

            //grab status images at same time
            if (!empty($showcaseData['enabled'])) {
                $statusImage = "styles/{$page->style}/images/icons/bullet_on.png";

                $statusText = $lang->myshowcase_summary_status_enabled;

                if ($showcaseDataTableExists) {
                    $popup->add_item(
                        $lang->myshowcase_summary_disable,
                        urlHandlerBuild(['action' => 'disable', 'id' => $showcaseID])
                    );
                }
            } else {
                $statusImage = "styles/{$page->style}/images/icons/bullet_off.png";

                $statusText = $lang->myshowcase_summary_status_disabled;

                if ($showcaseDataTableExists) {
                    $popup->add_item(
                        $lang->myshowcase_summary_enable,
                        urlHandlerBuild(['action' => 'enable', 'id' => $showcaseID])
                    );
                }
            }

            //override status if table does not exist
            if (!$showcaseDataTableExists) {
                $statusImage = "styles/{$page->style}/images/icons/error.png";

                $statusText = $lang->myshowcase_summary_status_notable;

                $popup->add_item(
                    $lang->myshowcase_summary_createtable,
                    urlHandlerBuild(['action' => 'tableCreate', 'id' => $showcaseID])
                );
            } else //add delete table popup item
            {
                $popup->add_item(
                    $lang->myshowcase_summary_rebuildtable,
                    urlHandlerBuild(['action' => 'tableRebuild', 'id' => $showcaseID])
                );

                $popup->add_item(
                    $lang->myshowcase_summary_deletetable,
                    urlHandlerBuild(['action' => 'tableDrop', 'id' => $showcaseID])
                );
            }

            $popup->add_item(
                $lang->myshowcase_summary_seo,
                urlHandlerBuild(['action' => 'viewRewrites', 'id' => $showcaseData['id']])
            );

            $popup->add_item(
                $lang->myshowcase_summary_delete,
                urlHandlerBuild(['action' => 'delete', 'id' => $showcaseID])
            );

            $showcaseData['imgfolder'] = $showcaseData['imgfolder'] ?? $lang->myshowcase_summary_not_specified;

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

            $formContainer->output_cell($showcaseData['mainfile'], ['class' => 'align_center']);

            $formContainer->output_cell($showcaseData['imgfolder'], ['class' => 'align_center']);

            $formContainer->output_cell($showcaseData['f2gpath'], ['class' => 'align_center']);

            $formContainer->output_cell(
                fieldsetGet(
                    ["setid='{$fieldsetID}'"],
                    ['setname'],
                    ['limit' => 1]
                )['setname'] . ' (ID=' . $fieldsetID . ')',
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