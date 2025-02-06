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

// Disallow direct access to this file for security reasons
use function MyShowcase\Core\showcaseDataTableExists;
use function MyShowcase\Core\showcasePermissions;

if (!defined('IN_MYBB')) {
    die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

$full_path = $_SERVER['SCRIPT_FILENAME'];
$base_name = $_SERVER['SCRIPT_NAME'];

global $lang, $cache, $db, $plugins, $mybb;
global $page;

$page->add_breadcrumb_item($lang->myshowcase_admin_summary, 'index.php?module=myshowcase-summary');

//make sure plugin is installed and active
$plugin_cache = $cache->read('plugins');
if (!$db->table_exists('myshowcase_config') || !array_key_exists('myshowcase', $plugin_cache['active'])) {
    flash_message($lang->myshowcase_plugin_not_installed, 'error');
    admin_redirect('index.php?module=config-plugins');
}

$plugins->run_hooks('admin_myshowcase_summary_begin');

if ($mybb->get_input('action') == 'new') {
    if (!empty($mybb->get_input('newname')) && $mybb->request_method == 'post') {
        $newname = $db->escape_string($mybb->get_input('newname'));
        $newfile = $db->escape_string($mybb->get_input('newfile'));
        $newdesc = $db->escape_string($mybb->get_input('newdesc'));
        $newfolder = $db->escape_string($mybb->get_input('newfolder'));
        $f2gpath = $db->escape_string($mybb->get_input('f2gpath'));

        if ($newfile == '' || $newname == '' || $newfolder == '') {
            flash_message($lang->myshowcase_summary_missing_required, 'error');
            admin_redirect('index.php?module=myshowcase-summary');
        }

        $query = $db->simple_select('myshowcase_config', '*', "name='{$newname}'");
        if ($db->num_rows($query) != 0) {
            flash_message($lang->myshowcase_summary_already_exists, 'error');
            admin_redirect('index.php?module=myshowcase-summary');
        }

        $query = $db->simple_select('myshowcase_config', '*', "mainfile='{$newfile}'");
        if ($db->num_rows($query) != 0) {
            flash_message($lang->myshowcase_summary_already_exists, 'error');
            admin_redirect('index.php?module=myshowcase-summary');
        } else {
            $plugins->run_hooks('admin_myshowcase_summary_insert_begin');

            $insert_array = array(
                'name' => $newname,
                'description' => $newdesc,
                'mainfile' => $newfile,
                'imgfolder' => $newfolder,
                'f2gpath' => $f2gpath,
                'fieldsetid' => $db->escape_string($mybb->get_input('newfieldset')),
                'enabled' => 0
            );
            $db->insert_query('myshowcase_config', $insert_array);
            $newid = $db->insert_id();

            // Reset new myshowcase info
            unset($mybb->input['newname']);
            unset($mybb->input['newdesc']);
            unset($mybb->input['newfile']);
            unset($mybb->input['newfolder']);
            unset($mybb->input['f2gpath']);
            unset($mybb->input['newfieldset']);

            $plugins->run_hooks('admin_myshowcase_summary_insert_end');

            //insert default permissions
            require_once(MYBB_ROOT . $mybb->config['admin_dir'] . '/modules/myshowcase/module_meta.php');

            $defaultShowcasePermissions = showcasePermissions();

            $curgroups = $cache->read('usergroups');
            ksort($curgroups);
            foreach ($curgroups as $group) {
                $defaultShowcasePermissions['id'] = $newid;
                $defaultShowcasePermissions['gid'] = $group['gid'];

                $db->insert_query('myshowcase_permissions', $defaultShowcasePermissions);
            }

            myshowcase_update_cache('config');
            myshowcase_update_cache('permissions');

            // Log admin action
            $log = array('id' => $newid, 'myshowcase' => $mybb->get_input('newname'));
            log_admin_action($log);

            flash_message($lang->myshowcase_summary_add_success, 'success');
            admin_redirect("index.php?module=myshowcase-edit&action=edit-main&id={$newid}");
        }
    } else {
        flash_message($lang->myshowcase_summary_invalid_name, 'error');
    }
}

if ($mybb->get_input('action') == 'enable') {
    if ($mybb->get_input('id', \MyBB::INPUT_INT) && is_numeric($mybb->get_input('id', \MyBB::INPUT_INT))) {
        $query = $db->simple_select('myshowcase_config', '*', 'id=' . $mybb->get_input('id', \MyBB::INPUT_INT));
        $result = $db->fetch_array($query);
        if ($db->num_rows($query) == 0) {
            flash_message($lang->myshowcase_summary_invalid_id, 'error');
        } else {
            //check if image folder exists and do not enable if folder does not exist
            if (!@is_dir(MYBB_ROOT . $result['f2gpath'] . $result['imgfolder']) || !@is_writable(
                    MYBB_ROOT . $result['f2gpath'] . $result['imgfolder']
                )) {
                flash_message($lang->myshowcase_summary_no_folder, 'error');
            } else {
                $plugins->run_hooks('admin_myshowcase_summary_enable_begin');

                $update_array = array(
                    'enabled' => 1
                );
                $db->update_query('myshowcase_config', $update_array, 'id=' . $mybb->get_input('id', \MyBB::INPUT_INT));

                if ($db->affected_rows()) {
                    $log = array('id' => $mybb->get_input('id', \MyBB::INPUT_INT));
                    log_admin_action($log);

                    flash_message($lang->myshowcase_summary_enable_success, 'success');
                } else {
                    flash_message($lang->myshowcase_summary_enable_failed, 'error');
                }
            }
        }
        myshowcase_update_cache('config');
    }
}

if ($mybb->get_input('action') == 'disable') {
    if ($mybb->get_input('id', \MyBB::INPUT_INT) && is_numeric($mybb->get_input('id', \MyBB::INPUT_INT))) {
        $query = $db->simple_select('myshowcase_config', '*', 'id=' . $mybb->get_input('id', \MyBB::INPUT_INT));
        if ($db->num_rows($query) == 0) {
            flash_message($lang->myshowcase_summary_invalid_id, 'error');
        } else {
            $plugins->run_hooks('admin_myshowcase_summary_disable_begin');

            $update_array = array(
                'enabled' => 0
            );
            $db->update_query('myshowcase_config', $update_array, 'id=' . $mybb->get_input('id', \MyBB::INPUT_INT));

            if ($db->affected_rows()) {
                $log = array('id' => $mybb->get_input('id', \MyBB::INPUT_INT));
                log_admin_action($log);

                flash_message($lang->myshowcase_summary_disable_success, 'success');
            } else {
                flash_message($lang->myshowcase_summary_disable_failed, 'error');
            }
        }
        myshowcase_update_cache('config');
    }
}

if ($mybb->get_input('action') == 'createtable') {
    if ($mybb->get_input('id', \MyBB::INPUT_INT) && is_numeric($mybb->get_input('id', \MyBB::INPUT_INT))) {
        $query = $db->simple_select(
            'myshowcase_config',
            'fieldsetid',
            'id=' . $mybb->get_input('id', \MyBB::INPUT_INT)
        );
        $showcase = $db->fetch_array($query);
        if ($db->num_rows($query) == 0) {
            flash_message($lang->myshowcase_summary_invalid_id, 'error');
        } else {
            //get custom fields
            $query = $db->simple_select(
                'myshowcase_fields',
                '`name`, `field_type`, `max_length`, `require`',
                'setid=' . $showcase['fieldsetid']
            );

            $plugins->run_hooks('admin_myshowcase_summary_create_begin');

            //required/fixed fields
            $create_sql = 'CREATE TABLE `' . TABLE_PREFIX . 'myshowcase_data' . $mybb->get_input(
                    'id',
                    \MyBB::INPUT_INT
                ) . "` (
			`gid` smallint(10) NOT NULL auto_increment,
			`uid` int(10) NOT NULL,
			`views` int(11) NOT NULL default '0',
			`comments` int(11) NOT NULL default '0',
			`submit_date` varchar(20) default '',
			`dateline` bigint(30) NOT NULL,
			`createdate` bigint(30) NOT NULL default '0',
			`approved` tinyint(1) NOT NULL default '0',
			`approved_by` mediumint(10) NOT NULL default '0',
			`posthash` varchar(32) NOT NULL default '',";

            //basic index
            $create_index = ' PRIMARY KEY  (`gid`),
			KEY `uid` (`uid`),
			KEY `approved` (`approved`)';

            //add custom fields
            while ($result = $db->fetch_array($query)) {
                if ($result['field_type'] == 'int') {
                    $create_sql .= '`' . $result['name'] . '` int(' . $result['max_length'] . ') default \'0\',';
                }

                if ($result['field_type'] == 'bigint') {
                    $create_sql .= '`' . $result['name'] . '` bigint(' . $result['max_length'] . ') default \'0\',';
                }

                if ($result['field_type'] == 'varchar') {
                    $create_sql .= '`' . $result['name'] . '` varchar(' . $result['max_length'] . ') default \'\',';
                }

                if ($result['field_type'] == 'text') {
                    $create_sql .= '`' . $result['name'] . '` text,';
                }

                if ($result['field_type'] == 'timestamp') {
                    $create_sql .= '`' . $result['name'] . '` timestamp,';
                }

                //add index for required fields
                if ($result['require'] == 1) {
                    if ($result['field_type'] == 'text' && $mybb->settings['searchtype'] == 'fulltext') {
                        $create_index .= ', FULLTEXT KEY `' . $result['name'] . '` (`' . $result['name'] . '`)';
                    } else {
                        $create_index .= ', KEY `' . $result['name'] . '` (`' . $result['name'] . '`)';
                    }
                }
            }

            $create_index .= ')';

            //add engine type
            if ($db->type == 'mysql' || $db->type == 'mysqli') {
                $create_mysql = ' ENGINE=MyISAM  DEFAULT CHARSET=utf8 PACK_KEYS=1 AUTO_INCREMENT=1';
            }

            $create_sql = $create_sql . $create_index . $create_mysql . ';';

            //create the table
            $db->write_query($create_sql);

            if (showcaseDataTableExists($mybb->get_input('id', \MyBB::INPUT_INT))) {
                $log = array('id' => $mybb->get_input('id', \MyBB::INPUT_INT));
                log_admin_action($log);

                flash_message($lang->myshowcase_summary_create_success, 'success');
            } else {
                flash_message($lang->myshowcase_summary_create_failed, 'error');
            }
        }
        myshowcase_update_cache('config');
    }
}

if ($mybb->get_input('action') == 'deletetable') {
    if ($mybb->get_input('id', \MyBB::INPUT_INT) && is_numeric($mybb->get_input('id', \MyBB::INPUT_INT))) {
        $page->output_header($lang->myshowcase_admin_edit_existing);

        $plugins->run_hooks('admin_myshowcase_deletetable_start');

        $query = $db->simple_select('myshowcase_config', '*', 'id=' . $mybb->get_input('id', \MyBB::INPUT_INT));
        $num_myshowcases = $db->num_rows($query);
        if ($num_myshowcases == 0) {
            flash_message($lang->myshowcase_summary_invalid_id, 'error');
            admin_redirect('index.php?module=myshowcase-summary');
        } else {
            $result = $db->fetch_array($query);
            $showcase_name = $result['name'];

            //make sure table does not already contain data
            $query = $db->simple_select('myshowcase_data' . $mybb->get_input('id', \MyBB::INPUT_INT), 'gid', '1=1');
            $num_myshowcases = $db->num_rows($query);
            if ($num_myshowcases > 0) {
                flash_message($lang->myshowcase_summary_deletetable_not_allowed, 'error');
                admin_redirect('index.php?module=myshowcase-summary');
            }

            //confirm delete
            echo $lang->sprintf($lang->myshowcase_summary_confirm_deletetable_long, $showcase_name);
            $form = new Form(
                'index.php?module=myshowcase-summary&amp;action=do_deletetable&amp;id=' . $mybb->get_input(
                    'id',
                    \MyBB::INPUT_INT
                ),
                'post',
                'do_deletetable'
            );

            $buttons[] = $form->generate_submit_button($lang->myshowcase_summary_confirm_delete);
            $form->output_submit_wrapper($buttons);

            $form->end();
        }

        $page->output_footer();
    }
}

if ($mybb->get_input('action') == 'do_deletetable') {
    if ($mybb->get_input('id', \MyBB::INPUT_INT) && is_numeric(
            $mybb->get_input('id', \MyBB::INPUT_INT)
        ) && $mybb->request_method == 'post') {
        $query = $db->simple_select('myshowcase_config', '*', 'id=' . $mybb->get_input('id', \MyBB::INPUT_INT));
        $num_myshowcases = $db->num_rows($query);

        myshowcase_update_cache('config');

        if ($num_myshowcases == 0) {
            flash_message($lang->myshowcase_edit_invalid_id, 'error');
            admin_redirect('index.php?module=myshowcase-summary');
        } else {
            if (showcaseDataTableExists($mybb->get_input('id', \MyBB::INPUT_INT))) {
                $query = $db->query(
                    'DROP TABLE ' . TABLE_PREFIX . 'myshowcase_data' . $mybb->get_input('id', \MyBB::INPUT_INT)
                );
                $update_array = array(
                    'enabled' => 0
                );
                $db->update_query('myshowcase_config', $update_array, 'id=' . $mybb->get_input('id', \MyBB::INPUT_INT));
            }

            $plugins->run_hooks('admin_myshowcase_deletetable_commit');

            flash_message($lang->myshowcase_summary_deletetable_success, 'success');
            admin_redirect('index.php?module=myshowcase-summary');
        }
    }
}

if ($mybb->get_input('action') == 'show_seo') {
    if ($mybb->get_input('id', \MyBB::INPUT_INT) && is_numeric($mybb->get_input('id', \MyBB::INPUT_INT))) {
        $page->output_header($lang->myshowcase_admin_show_seo);

        $query = $db->simple_select('myshowcase_config', '*', 'id=' . $mybb->get_input('id', \MyBB::INPUT_INT));
        $num_myshowcases = $db->num_rows($query);
        if ($num_myshowcases == 0) {
            flash_message($lang->myshowcase_summary_invalid_id, 'error');
            admin_redirect('index.php?module=myshowcase-summary');
        } else {
            $result = $db->fetch_array($query);
            $showcase_name = strtolower($result['name']);
            //cleaning showcase name for redirect
            //some cleaning borrowed from Google SEO plugin
            $pattern = '!"#$%&\'( )*+,-./:;<=>?@[\]^_`{|}~';
            $pattern = preg_replace(
                "/[\\\\\\^\\-\\[\\]\\/]/u",
                "\\\\\\0",
                $pattern
            );

            // Cut off punctuation at beginning and end.
            $showcase_name = preg_replace(
                "/^[$pattern]+|[$pattern]+$/u",
                '',
                $showcase_name
            );

            // Replace middle punctuation with one separator.
            $showcase_name = preg_replace(
                "/[$pattern]+/u",
                '-',
                $showcase_name
            );

            echo "If you are using the built-in MyBB SEO or other SEO plugin, you will need to enter the information below into your .htaccess file. These settings are specific to the {$result['name']} Showcase. If there are other showcases, the SEO settings for those will need to be added as well. If the {$result['name']} Showcase name is changed, these .htaccess settings will need to be updated. After changing the name, return to this page to obtain the new settings and update the .htaccess file as appropriate.<br />";
            echo '<br />';
            echo "RewriteRule ^{$showcase_name}\.html$ {$result['mainfile']} [L,QSA]<br />";
            echo "RewriteRule ^{$showcase_name}-page-([0-9]+)\.html$ {$result['mainfile']}?page=$1 [L,QSA]<br />";
            echo "RewriteRule ^{$showcase_name}-view-([0-9]+)\.html$ {$result['mainfile']}?action=view&gid=$1 [L,QSA]<br />";
            echo "RewriteRule ^{$showcase_name}-new\.html$ {$result['mainfile']}?action=new [L,QSA]<br />";
            echo "RewriteRule ^{$showcase_name}-attachment-([0-9]+)\.html$ {$result['mainfile']}?action=attachment&aid=$1 [L,QSA]<br />";
            echo "RewriteRule ^{$showcase_name}-item-([0-9]+)\.php$ {$result['mainfile']}?action=item&aid=$1 [L,QSA]<br />";

            $showcase_info = myshowcase_info();
            echo '<p /><small>' . $showcase_info['name'] . ' version ' . $showcase_info['version'] . ' &copy; 2006-' . COPY_YEAR . ' <a href="' . $showcase_info['website'] . '">' . $showcase_info['author'] . '</a>.</small>';
            $page->output_footer();
        }
    }
}

//basic summary page, output regardless of action
{
    $plugins->run_hooks('admin_myshowcase_summary_start');

    $page->output_header($lang->myshowcase_admin_summary);

    $form = new Form('index.php?module=myshowcase-summary', 'post', 'summary');
    $form_container = new FormContainer($lang->myshowcase_summary_existing);

    $form_container->output_row_header($lang->myshowcase_summary_id, array('width' => '2%', 'class' => 'align_center'));
    $form_container->output_row_header(
        $lang->myshowcase_summary_name,
        array('width' => '10%', 'class' => 'align_center')
    );
    $form_container->output_row_header(
        $lang->myshowcase_summary_description,
        array('width' => '17%', 'class' => 'align_center')
    );
    $form_container->output_row_header(
        $lang->myshowcase_summary_entries_count,
        array('width' => '5%', 'class' => 'align_center')
    );
    $form_container->output_row_header(
        $lang->myshowcase_summary_comment_count,
        array('width' => '5%', 'class' => 'align_center')
    );
    $form_container->output_row_header(
        $lang->myshowcase_summary_attachments_count,
        array('width' => '5%', 'class' => 'align_center')
    );
    $form_container->output_row_header(
        $lang->myshowcase_summary_attachments_size,
        array('width' => '5%', 'class' => 'align_center')
    );
    $form_container->output_row_header(
        $lang->myshowcase_summary_main_file,
        array('width' => '5%', 'class' => 'align_center')
    );
    $form_container->output_row_header(
        $lang->myshowcase_summary_image_folder,
        array('width' => '5%', 'class' => 'align_center')
    );
    $form_container->output_row_header(
        $lang->myshowcase_summary_forum_folder,
        array('width' => '5%', 'class' => 'align_center')
    );
    $form_container->output_row_header(
        $lang->myshowcase_summary_field_set,
        array('width' => '5%', 'class' => 'align_center')
    );
    $form_container->output_row_header(
        $lang->myshowcase_summary_status,
        array('width' => '2%', 'class' => 'align_center')
    );
    $form_container->output_row_header($lang->controls, array('width' => '5%', 'class' => 'align_center'));

    $query = $db->simple_select('myshowcase_config', '*', '');
    $num_myshowcases = $db->num_rows($query);
    if ($num_myshowcases == 0) {
        $form_container->output_cell($lang->myshowcase_summary_no_myshowcases, array('colspan' => 9));
    } else {
        while ($result = $db->fetch_array($query)) {
            $num_entries = 0;
            $num_attach = 0;
            $attach_size = 0;
            $num_comments = 0;

            $query_fieldset = $db->simple_select('myshowcase_fieldsets', 'setname', 'setid=' . $result['fieldsetid']);
            $result_fieldset = $db->fetch_array($query_fieldset);

            $table_ready = showcaseDataTableExists($result['id']);

            if ($table_ready) {
                $query_myshowcase = $db->query(
                    'SELECT count(*) AS total FROM ' . TABLE_PREFIX . 'myshowcase_data' . $result['id']
                );
                $result2 = $db->fetch_array($query_myshowcase);
                $num_entries = ($result2 ? $result2['total'] : 0);

                $query_attach = $db->query(
                    'SELECT count(*) AS total, sum(filesize) AS totalsize FROM ' . TABLE_PREFIX . 'myshowcase_attachments a, ' . TABLE_PREFIX . 'myshowcase_data' . $result['id'] . ' d WHERE a.gid=d.gid AND a.id=' . $result['id'] . ' GROUP BY a.id'
                );
                $result2 = $db->fetch_array($query_attach);
                $num_attach = ($result2 ? $result2['total'] : 0);
                $attach_size = ($result2 ? $result2['totalsize'] : 0);

                $query_comments = $db->query(
                    'SELECT count(*) AS total FROM ' . TABLE_PREFIX . 'myshowcase_comments c, ' . TABLE_PREFIX . 'myshowcase_data' . $result['id'] . ' d WHERE c.gid=d.gid AND c.id=' . $result['id'] . ' GROUP BY c.id'
                );
                $result2 = $db->fetch_array($query_comments);
                $num_comments = ($result2 ? $result2['total'] : 0);
            } else {
                $num_entries = 0;
                $num_attach = 0;
                $attach_size = 0;
                $num_comments = 0;
            }
            // Build popup menu
            $popup = new PopupMenu("myshowcase_{$result['id']}", $lang->options);
            $popup->add_item(
                $lang->myshowcase_summary_edit,
                "index.php?module=myshowcase-edit&amp;action=edit-main&amp;id={$result['id']}"
            );

            //grab status images at same time
            if ($result['enabled'] == 1) {
                $status_image = "styles/{$page->style}/images/icons/bullet_on.png";
                $status_alt = $lang->myshowcase_summary_status_enabled;
                if ($table_ready) {
                    $popup->add_item(
                        $lang->myshowcase_summary_disable,
                        "index.php?module=myshowcase-summary&amp;action=disable&amp;id={$result['id']}"
                    );
                }
            } else {
                $status_image = "styles/{$page->style}/images/icons/bullet_off.png";
                $status_alt = $lang->myshowcase_summary_status_disabled;
                if ($table_ready) {
                    $popup->add_item(
                        $lang->myshowcase_summary_enable,
                        "index.php?module=myshowcase-summary&amp;action=enable&amp;id={$result['id']}"
                    );
                }
            }

            //override status if table does not exist
            if (!$table_ready) {
                $status_image = "styles/{$page->style}/images/icons/error.png";
                $status_alt = $lang->myshowcase_summary_status_notable;
                $popup->add_item(
                    $lang->myshowcase_summary_createtable,
                    "index.php?module=myshowcase-summary&amp;action=createtable&amp;id={$result['id']}"
                );
            } else //add delete table popup item
            {
                $popup->add_item(
                    $lang->myshowcase_summary_deletetable,
                    "index.php?module=myshowcase-summary&amp;action=deletetable&amp;id={$result['id']}"
                );
            }

            $popup->add_item(
                $lang->myshowcase_summary_seo,
                "index.php?module=myshowcase-summary&amp;action=show_seo&amp;id={$result['id']}"
            );
            $popup->add_item(
                $lang->myshowcase_summary_delete,
                "index.php?module=myshowcase-edit&amp;action=delete&amp;id={$result['id']}"
            );

            $result['imgfolder'] = ($result['imgfolder'] == '' ? $lang->myshowcase_summary_not_specified : $result['imgfolder']);

            $form_container->output_cell($result['id'], array('class' => 'align_center'));
            $form_container->output_cell($result['name']);
            $form_container->output_cell($result['description']);
            $form_container->output_cell($num_entries, array('class' => 'align_center'));
            $form_container->output_cell($num_comments, array('class' => 'align_center'));
            $form_container->output_cell($num_attach, array('class' => 'align_center'));
            $form_container->output_cell(
                number_format($attach_size / 1024 / 1024, 2, '.', ','),
                array('class' => 'align_center')
            );
            $form_container->output_cell($result['mainfile'], array('class' => 'align_center'));
            $form_container->output_cell($result['imgfolder'], array('class' => 'align_center'));
            $form_container->output_cell($result['f2gpath'], array('class' => 'align_center'));
            $form_container->output_cell(
                $result_fieldset['setname'] . '<br />(ID=' . $result['fieldsetid'] . ')',
                array('class' => 'align_center')
            );
            $form_container->output_cell(
                '<img src="' . $status_image . '" title="' . $status_alt . '">',
                array('class' => 'align_center')
            );
            $form_container->output_cell($popup->fetch(), array('class' => 'align_center'));
            $form_container->construct_row();
        }
    }
    $form_container->end();
    $form->end();

    unset($fieldsets);

    $fieldsets = [];

    $query = $db->simple_select('myshowcase_fieldsets', '*', '');
    while ($result = $db->fetch_array($query)) {
        $fieldsets[$result['setid']] = $result['setname'];
    }

    if (count($fieldsets) == 0) {
        $form = new Form('index.php?module=myshowcase-summary&amp;action=new', 'post', 'new');
        $form_container = new FormContainer($lang->myshowcase_summary_new);

        $form_container->output_row_header(
            $lang->myshowcase_summary_name,
            array('width' => '25%', 'class' => 'align_center')
        );
        $form_container->output_row_header($lang->myshowcase_summary_description, array('class' => 'align_center'));
        $form_container->output_row_header($lang->myshowcase_summary_main_file, array('class' => 'align_center'));
        $form_container->output_row_header($lang->myshowcase_summary_image_folder, array('class' => 'align_center'));
        $form_container->output_row_header($lang->myshowcase_summary_forum_folder, array('class' => 'align_center'));
        $form_container->output_row_header(
            $lang->myshowcase_summary_field_set,
            array('width' => '8%', 'class' => 'align_center')
        );

        $form_container->output_cell(
            $lang->myshowcase_summary_nofieldsets,
            array('colspan' => '6', 'class' => 'align_center')
        );
        $form_container->construct_row();
        $form_container->end();

        $form->end();
    } else {
        $form = new Form('index.php?module=myshowcase-summary&amp;action=new', 'post', 'new');
        $form_container = new FormContainer($lang->myshowcase_summary_new);

        $form_container->output_row_header(
            $lang->myshowcase_summary_name,
            array('width' => '25%', 'class' => 'align_center')
        );
        $form_container->output_row_header($lang->myshowcase_summary_description, array('class' => 'align_center'));
        $form_container->output_row_header($lang->myshowcase_summary_main_file, array('class' => 'align_center'));
        $form_container->output_row_header($lang->myshowcase_summary_image_folder, array('class' => 'align_center'));
        $form_container->output_row_header($lang->myshowcase_summary_forum_folder, array('class' => 'align_center'));
        $form_container->output_row_header(
            $lang->myshowcase_summary_field_set,
            array('width' => '8%', 'class' => 'align_center')
        );

        $form_container->output_cell($form->generate_text_box('newname', ''));
        $form_container->output_cell($form->generate_text_box('newdesc', '', array('style' => 'width: 95%')));
        $form_container->output_cell($form->generate_text_box('newfile', '', array('style' => 'width: 95%')));
        $form_container->output_cell($form->generate_text_box('newfolder', '', array('style' => 'width: 95%')));
        $form_container->output_cell($form->generate_text_box('f2gpath', '', array('style' => 'width: 95%')));
        $form_container->output_cell(
            $form->generate_select_box('newfieldset', $fieldsets, '', array('id' => 'fieldsets')),
            array('class' => 'align_center')
        );
        $form_container->construct_row();
        $form_container->end();

        $buttons[] = $form->generate_submit_button($lang->myshowcase_summary_add);
        $form->output_submit_wrapper($buttons);

        $form->end();
    }

    $myshowcase_info = myshowcase_info();
    echo '<p /><small>' . $myshowcase_info['name'] . ' version ' . $myshowcase_info['version'] . ' &copy; 2006-' . COPY_YEAR . ' <a href="' . $myshowcase_info['website'] . '">' . $myshowcase_info['author'] . '</a>.</small>';

    $page->output_footer();
}