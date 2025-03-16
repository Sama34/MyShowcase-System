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

namespace inc\plugins\myshowcase;

use MyShowcase\System\Array;

use function MyShowcase\Core\attachmentGet;
use function MyShowcase\Core\attachmentRemove;
use function MyShowcase\Core\cacheGet;
use function MyShowcase\Core\commentDelete;
use function MyShowcase\Core\getSetting;
use function MyShowcase\Core\showcaseDataDelete;
use function MyShowcase\Core\showcaseDataTableExists;
use function MyShowcase\Core\showcasePermissions;

use const MyShowcase\Core\CACHE_TYPE_CONFIG;
use const MyShowcase\Core\CACHE_TYPE_MODERATORS;
use const MyShowcase\Core\CACHE_TYPE_PERMISSIONS;

const PERMISSION_USER_CAN_NEW_ENTRY = 'canadd';

const PERMISSION_USER_CAN_EDIT_ENTRY = 'canedit';

const PERMISSION_MODERATOR_CAN_APPROVE = 'canmodapprove';

const PERMISSION_MODERATOR_CAN_DELETE = 'canmoddelete';

class Showcase
{
    /**
     * The ID of the current showcase.
     * @var int
     */
    public int $id;

    /**
     * The name of the current showcase
     * @var string
     */
    public string $name;

    /**
     * The description of the current showcase
     * @var string
     */
    public string $description;

    /**
     * The main PHP file of the current showcase
     * @var string
     */
    public string $mainfile;

    /**
     * The ID of the fieldset used in the current showcase
     * @var int
     */
    public int $fieldsetid;

    /**
     * The image folder of the current showcase
     * @var string
     */
    public string $imgfolder;

    /**
     * The default image for each record in the list view of the current showcase
     * @var string
     */
    public string $defaultimage;

    /**
     * The watermark image to use as a watermark
     * @var string
     */
    public string $watermarkimage;

    /**
     * The watermark location
     * @var string
     */
    public string $watermarkloc;

    /**
     * The option to use an attachment in list view or not
     * @var string
     */
    public bool $use_attach;

    /**
     * The relative path from the forum to the current showcase
     * @var string
     */
    public string $f2gpath;

    /**
     * The status of the current showcase
     * @var int
     */
    public bool $enabled;

    /**
     * The number of seconds from last edit to remove entry of the current showcase
     * @var int
     */
    public int $prunetime;

    /**
     * The moderation status of the current showcase
     * @var int
     */
    public $modnewedit;

    /**
     * Allow smilies in the current showcase
     * @var bool
     */
    public bool $allowsmilies;

    /**
     * Allow BBCode the current showcase
     * @var bool
     */
    public bool $allowbbcode;

    /**
     * Allow HTML the current showcase
     * @var bool
     */
    public bool $allowhtml;

    /**
     * The maxlength of the 'other' field of the current showcase
     * @var int
     */
    public int $othermaxlength;

    /**
     * Allow attachments in the current showcase
     * @var bool
     */
    public bool $allow_attachments;

    /**
     * Allow comments in the current showcase
     * @var int
     */
    public bool $allow_comments;

    /**
     * The thumbnail width of the current showcase
     * @var int
     */
    public int $thumb_width;

    /**
     * The thumbnail height of the current showcase
     * @var int
     */
    public int $thumb_height;

    /**
     * The max comment length of the current showcase
     * @var int
     */
    public int $comment_length;

    /**
     * The number of comments to display initially of the current showcase
     * @var int
     */
    public int $comment_dispinit;

    /**
     * The number of columns of attachments to disaply in the current showcase
     * @var int
     */
    public int $disp_attachcols;

    /**
     * Dispaly empty fields in the current showcase
     * @var int
     */
    public bool $disp_empty;

    /**
     * Try to display and entry with attachment in this showcase on the portal
     * @var int
     */
    public bool $portal_random;

    /**
     * Table name of the showcase data
     * @var string
     */
    public string $table_name;

    /**
     * Basename of the calling file
     * @var string
     */
    public string $prefix;

    /**
     * Clean name for URL/SEO
     * @var string
     */
    public string $clean_name;

    /**
     * User permissions array for this showcase
     * @var Array
     */
    public array $userperms;

    /**
     * Mod permissions array for this showcase
     * @var Array
     */
    public array $modperms;

    public bool $seo_support;

    public array $parser_options;

    /**
     * Constructor of class.
     *
     * @return Showcase
     */
    public function __construct(string $filename = THIS_SCRIPT)
    {
        global $db, $mybb, $cache;

        if ($mybb->settings['seourls'] == 'yes' || ($mybb->settings['seourls'] == 'auto' && isset($_SERVER['SEO_SUPPORT']) && $_SERVER['SEO_SUPPORT'] == 1)) {
            $this->seo_support = true;
        } else {
            $this->seo_support = false;
        }

        //make sure plugin is installed and active
        $plugin_cache = $cache->read('plugins');
        if (!$db->table_exists('myshowcase_config') || !array_key_exists('myshowcase', $plugin_cache['active'])) {
            error('The MyShowcase System has not been installed and activated yet.');
        }

        //get this showcase's config info
        $showcases = cacheGet(CACHE_TYPE_CONFIG);

        //check if the requesting file is in the cache
        foreach ($showcases as $showcase) {
            if ($showcase['mainfile'] == $filename)//THIS_SCRIPT)
            {
                foreach ($showcase as $key => $value) {
                    $this->$key = $value;
                }
                continue;
            }
        }

        //clean the name and make it suitable for SEO
        //cleaning borrowed from Google SEO plugin
        $pattern = '!"#$%&\'( )*+,-./:;<=>?@[\]^_`{|}~';
        $pattern = preg_replace(
            "/[\\\\\\^\\-\\[\\]\\/]/u",
            "\\\\\\0",
            $pattern
        );

        // Cut off punctuation at beginning and end.
        $this->clean_name = preg_replace(
            "/^[$pattern]+|[$pattern]+$/u",
            '',
            strtolower($this->name)
        );

        // Replace middle punctuation with one separator.
        $this->clean_name = preg_replace(
            "/[$pattern]+/u",
            '-',
            $this->clean_name
        );

        //make sure data table exists and assign table name var if it does
        if (showcaseDataTableExists($this->id)) {
            $this->table_name = 'myshowcase_data' . $this->id;
        } else {
            $this->table_name = '';
        }

        if (!$this->id || !$this->table_name || $this->fieldsetid == 0) {
            error('This file is not properly configured in the MyShowcase Admin section of the ACP');
        }

        //get basename of the calling file. This is used later for SEO support
        $temp = explode('.', $this->mainfile);
        $this->prefix = $temp[0];

        //get group permissions now
        $this->userperms = $this->get_user_permissions($mybb->user);

        $this->parser_options = [
            'filter_badwords' => true,
            'allow_html' => $this->allowhtml,
            'allow_mycode' => $this->allowbbcode,
            'me_username' => '',
            'highlight' => '',
            'allow_smilies' => $this->allowsmilies,
            'nl2br' => true
        ];
    }

    /**
     * get group permissions for a specific showcase
     *
     * @return array group permissions for the specific showcase
     */
    public function get_group_permissions(): array
    {
        global $db, $cache, $config;

        require_once(MYBB_ROOT . $config['admin_dir'] . '/modules/myshowcase/module_meta.php');
        $showcase_group_perms = [];

        $usergroups = $cache->read('usergroups');

        $permcache = cacheGet(CACHE_TYPE_PERMISSIONS);

        foreach ($permcache[$this->id] as $id => $showperms) {
            $showcase_group_perms[$showperms['gid']]['id'] = $showperms['gid'];
            $showcase_group_perms[$showperms['gid']]['name'] = $usergroups[$showperms['gid']]['title'];
            $showcase_group_perms[$showperms['gid']]['canview'] = $showperms['canview'];
            $showcase_group_perms[$showperms['gid']]['canadd'] = $showperms['canadd'];
            $showcase_group_perms[$showperms['gid']]['canedit'] = $showperms['canedit'];
            $showcase_group_perms[$showperms['gid']]['cancomment'] = $showperms['cancomment'];
            $showcase_group_perms[$showperms['gid']]['canattach'] = $showperms['canattach'];
            $showcase_group_perms[$showperms['gid']]['candelowncomment'] = $showperms['candelowncomment'];
            $showcase_group_perms[$showperms['gid']]['candelauthcomment'] = $showperms['candelauthcomment'];
            $showcase_group_perms[$showperms['gid']]['canviewcomment'] = $showperms['canviewcomment'];
            $showcase_group_perms[$showperms['gid']]['canviewattach'] = $showperms['canviewattach'];
            $showcase_group_perms[$showperms['gid']]['cansearch'] = $showperms['cansearch'];
            $showcase_group_perms[$showperms['gid']]['canwatermark'] = $showperms['canwatermark'];
            $showcase_group_perms[$showperms['gid']]['attachlimit'] = $showperms['attachlimit'];
            $showcase_group_perms[$showperms['gid']]['intable'] = 1;
        }

        //load defaults if group not already in cache (e.g. group added since myshowcase created)
        foreach ($usergroups as $group) {
            if (!array_key_exists($group['gid'], $showcase_group_perms)) {
                $showcase_group_perms[$group['gid']]['id'] = $group['gid'];
                $showcase_group_perms[$group['gid']]['name'] = $group['title'];
                $showcase_group_perms[$group['gid']]['canview'] = showcasePermissions()['canview'];
                $showcase_group_perms[$group['gid']]['canadd'] = showcasePermissions()['canadd'];
                $showcase_group_perms[$group['gid']]['canedit'] = showcasePermissions()['canedit'];
                $showcase_group_perms[$group['gid']]['cancomment'] = showcasePermissions()['cancomment'];
                $showcase_group_perms[$group['gid']]['canattach'] = showcasePermissions()['canattach'];
                $showcase_group_perms[$group['gid']]['candelowncomment'] = showcasePermissions()['candelowncomment'];
                $showcase_group_perms[$group['gid']]['candelauthcomment'] = showcasePermissions()['candelauthcomment'];
                $showcase_group_perms[$group['gid']]['canviewcomment'] = showcasePermissions()['canviewcomment'];
                $showcase_group_perms[$group['gid']]['canviewattach'] = showcasePermissions()['canviewattach'];
                $showcase_group_perms[$group['gid']]['cansearch'] = showcasePermissions()['cansearch'];
                $showcase_group_perms[$group['gid']]['canwatermark'] = showcasePermissions()['canwatermark'];
                $showcase_group_perms[$group['gid']]['attachlimit'] = showcasePermissions()['attachlimit'];
                $showcase_group_perms[$group['gid']]['intable'] = 0;
            }
        }

        return $showcase_group_perms;
    }

    /**
     * get user permissions for a specific showcase
     *
     * @param int The User array for the user to build permissions for
     * @return array user permissions for the specific showcase
     */
    public function get_user_permissions(array $user): array
    {
        global $cache, $mybb, $currentUserID;

        //basic user permissions

        $showcase_group_perms = $this->get_group_permissions();

        //init to guest permissions
        $showcase_user_perms['canview'] = $showcase_group_perms[1]['canview'];
        $showcase_user_perms['canadd'] = $showcase_group_perms[1]['canadd'];
        $showcase_user_perms['canedit'] = $showcase_group_perms[1]['canedit'];
        $showcase_user_perms['cancomment'] = $showcase_group_perms[1]['cancomment'];
        $showcase_user_perms['canattach'] = $showcase_group_perms[1]['canattach'];
        $showcase_user_perms['candelowncomment'] = $showcase_group_perms[1]['candelowncomment'];
        $showcase_user_perms['candelauthcomment'] = $showcase_group_perms[1]['candelauthcomment'];
        $showcase_user_perms['canviewcomment'] = $showcase_group_perms[1]['canviewcomment'];
        $showcase_user_perms['canviewattach'] = $showcase_group_perms[1]['canviewattach'];
        $showcase_user_perms['cansearch'] = $showcase_group_perms[1]['cansearch'];
        $showcase_user_perms['canwatermark'] = $showcase_group_perms[1]['canwatermark'];
        $showcase_user_perms['attachlimit'] = $showcase_group_perms[1]['attachlimit'];

        //set default mod perms
        $modperms = [
            'canmodapprove' => 0,
            'canmodedit' => 0,
            'canmoddelete' => 0,
            'canmoddelcomment' => 0
        ];

        //if not a guest, keep going....
        if ($user['uid'] > 0) {
            //user permissions for user's groups
            $groups_csv = $user['usergroup'] . ($user['additionalgroups'] ? ',' . $user['additionalgroups'] : '');
            $groups = explode(',', $groups_csv);

            foreach ($showcase_user_perms as $field => $value) {
                foreach ($groups as $gid) {
                    $showcase_user_perms[$field] = ($showcase_group_perms[$gid][$field] == -1 ? -1 : max(
                        $showcase_user_perms[$field],
                        $showcase_group_perms[$gid][$field]
                    ));
                }
            }

            //check moderator perms

            //assign full mod perms as default for supermod, admin groups if user in those groups
            if (is_member(getSetting('moderatorGroups'), $user)) {
                $modperms = [
                    'canmodapprove' => 1,
                    'canmodedit' => 1,
                    'canmoddelete' => 1,
                    'canmoddelcomment' => 1
                ];
            }

            //get showcase moderator cache to handle additional mods/modgroups
            $modcache = cacheGet(CACHE_TYPE_MODERATORS);
            if (!empty($modcache[$this->id])) {
                //get moderators specific to this myshowcase
                $mods = $modcache[$this->id];

                $modperms2 = [];

                //check if user in additional moderator usergroup and use those perms instead (in case admin sets lower than full perms for  mod/super/admin groups)
                foreach ($mods as $mid => $moddata) {
                    if ($moddata['isgroup'] && in_array($moddata['uid'], $groups)) {
                        $modperms2['canmodapprove'] = max($modperms2['canmodapprove'], $moddata['canmodapprove']);
                        $modperms2['canmodedit'] = max($modperms2['canmodedit'], $moddata['canmodedit']);
                        $modperms2['canmoddelete'] = max($modperms2['canmoddelete'], $moddata['canmoddelete']);
                        $modperms2['canmoddelcomment'] = max(
                            $modperms2['canmoddelcomment'],
                            $moddata['canmoddelcomment']
                        );
                    }

                    //check for specific user and use those permissions regardless of group perms
                    if (!$moddata['isgroup'] && $moddata['uid'] == $currentUserID) {
                        $modperms3 = [
                            'canmodapprove' => $moddata['canmodapprove'],
                            'canmodedit' => $moddata['canmodedit'],
                            'canmoddelete' => $moddata['canmoddelete'],
                            'canmoddelcomment' => $moddata['canmoddelcomment']
                        ];

                        //since we want user specific perms first, might as well continue here and skip the rest of the checks
                        continue;
                    }
                }

                //if user is in assigned moderator group, $modperms2 is an array so use those permissions
                if (!empty($modperms2)) {
                    $modperms = $modperms2;
                }

                //if user is in assigned as moderator , $modperms3 is an array so use those permissions
                if (is_array($modperms3)) {
                    $modperms = $modperms3;
                }
            }
        }

        //insert mod perms into user perms
        $showcase_user_perms = array_merge($showcase_user_perms, $modperms);

        return $showcase_user_perms;
    }

    /**
     * get ids from cookie inline moderation
     */
    public function getids(int|string $id, string $type): array
    {
        global $mybb;
        $cookie = 'inlinemod_' . $type . $id;
        $ids = explode('|', $mybb->cookies[$cookie]);
        foreach ($ids as $id) {
            if ($id != '') {
                $newids[] = intval($id);
            }
        }
        return $newids;
    }

    /**
     * delete a showcase entry
     */
    public function delete(int $gid): bool
    {
        global $db;

        $this->delete_attachments($gid, $this->id);

        $this->delete_comments($gid, $this->id);

        showcaseDataDelete($this->id, ["gid='{$gid}'"]);

        return true;
    }

    /**
     * delete attachments from a showcase
     */
    public function delete_attachments(int $entryID, int $id): bool
    {
        foreach (
            attachmentGet(["gid='{$entryID}'", "id='{$id}'"]) as $attachmentID => $attachmentData
        ) {
            attachmentRemove($this, '', (int)$attachmentID);
        }

        return true;
    }

    /**
     * delete a comment
     */
    public function delete_comments(int $gid, int $id): bool
    {
        global $db;

        commentDelete(["gid='{$gid}'", "id='{$id}'"]);

        return true;
    }

    /**
     * clear cookie inline moderation
     */
    public function clearinline(int|string $id, string $type): bool
    {
        my_unsetcookie('inlinemod_' . $type . $id);

        return true;
    }

    /**
     * add to cookie inline moderation
     */
    public function extendinline(int $id, string $type): bool
    {
        global $mybb;

        my_setcookie("inlinemod_$type.$id", '', TIME_NOW + 3600);

        return true;
    }

    public function permissionCheck(string $permissionType): bool
    {
        return !empty($this->userperms[$permissionType]);
    }

    public function parser(): \postParser
    {
        global $parser;

        if (!($parser instanceof \postParser)) {
            require_once MYBB_ROOT . 'inc/class_parser.php';

            $parser = new \Postparser();
        }

        return $parser;
    }

    public function parse_message(string $message, array $parserOptions = []): string
    {
        return $this->parser()->parse_message($message, array_merge($this->parser_options, $parserOptions));
    }
}