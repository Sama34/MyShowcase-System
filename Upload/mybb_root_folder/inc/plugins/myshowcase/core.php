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

use function MyShowcase\Admin\_info;

use const MyShowcase\ROOT;

const SHOWCASE_STATUS_ENABLED = 1;

const SHOWCASE_UPLOAD_STATUS_INVALID = 1;

const SHOWCASE_UPLOAD_STATUS_FAILED = 2;

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

function loadPluginLibrary(bool $check = true): bool
{
    global $PL, $lang;

    loadLanguage();

    if ($file_exists = file_exists(PLUGINLIBRARY)) {
        global $PL;

        $PL or require_once PLUGINLIBRARY;
    }

    if (!$check) {
        return false;
    }

    $_info = _info();

    if (!$file_exists || $PL->version < $_info['pl']['version']) {
        flash_message(
            $lang->sprintf($lang->MyShowcaseSystemPluginLibrary, $_info['pl']['url'], $_info['pl']['version']),
            'error'
        );

        admin_redirect('index.php?module=config-plugins');
    }

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
        $templateName = substr($templateName, strpos($templateName, '/') + 1);
    }

    return $templates->render(getTemplateName($templateName), true, $enableHTMLComments);
}

//set default permissions for all groups in all myshowcases
//if you edit or reorder these, you need to also edit
//the edit.php file (starting line 225) so the fields match this order
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

function showcaseDataTableExists(int $showcaseID): bool
{
    global $db;

    return $db->table_exists('myshowcase_data' . $showcaseID);
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

function cacheGet(): array
{
    global $cache;

    return $cache->read('myshowcase_config') ?? [];
}

function attachmentGet(array $queryFields = ['aid'], array $whereClauses = []): array
{
    global $db;

    $query = $db->simple_select(
        'myshowcase_attachments',
        implode(', ', $queryFields),
        implode(' AND ', $whereClauses)
    );

    if ($db->num_rows($query)) {
        return (array)$db->fetch_array($query);
    }

    return [];
}

function attachmentDelete(int $attachmentID): bool
{
    global $db;

    $db->delete_query('myshowcase_attachments', "aid='{$attachmentID}'");

    return true;
}

/**
 * Remove an attachment from a specific showcase
 *
 * @param int The showcase ID
 * @param string The posthash if available
 * @param int The attachment ID
 */
function attachmentRemove(
    System $showcase,
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

    $attachmentData = attachmentGet(['aid', 'attachname', 'thumbnail', 'visible'], $whereClauses);

    $attachmentData = hooksRun('remove_attachment_do_delete', $attachmentData);

    attachmentDelete((int)$attachmentData['aid']);

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
    System $showcase,
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
        $query = $db->simple_select(
            'myshowcase_attachments',
            'SUM(filesize) AS userTotalUsage',
            "uid='" . intval($showcase_uid) . "'"
        );

        $userTotalUsage = $db->fetch_field($query, 'userTotalUsage') + $attachmentData['size'];

        if ($userTotalUsage > ($mybb->usergroup['attachquota'] * 1024)) {
            $returnData['error'] = $lang->sprintf(
                $lang->error_reachedattachquota,
                get_friendly_size($mybb->usergroup['attachquota'] * 1024)
            );

            return $returnData;
        }
    }

    $existingAttachment = attachmentGet(['aid'], [
        "filename='{$db->escape_string($attachmentData['name'])}'",
        "id='{$showcase->id}'",
        "(posthash='{$db->escape_string($entryHash)}' OR (gid='{$gid}' AND gid!='0'))"
    ]);

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
            case SHOWCASE_UPLOAD_STATUS_INVALID:
                $returnData['error'] .= $lang->error_uploadfailed_nothingtomove;
                break;
            case SHOWCASE_UPLOAD_STATUS_FAILED:
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

        $db->update_query(
            'myshowcase_attachments',
            $insertData,
            "aid='{$attachmentID}'"
        );
    } else {
        $attachmentID = (int)$db->insert_query('myshowcase_attachments', $insertData);
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
        $returnData['error'] = SHOWCASE_UPLOAD_STATUS_INVALID;

        return $returnData;
    }

    if (!$fileName) {
        $fileName = $fileData['name'];
    }

    $returnData['original_filename'] = preg_replace('#/$#', '', $fileData['name']);

    $fileName = preg_replace('#/$#', '', $fileName);

    if (!move_uploaded_file($fileData['tmp_name'], $uploadsPath . '/' . $fileName)) {
        $returnData['error'] = SHOWCASE_UPLOAD_STATUS_FAILED;

        return $returnData;
    }

    my_chmod($uploadsPath . '/' . $fileName, '0644');

    $returnData['filename'] = $fileName;

    $returnData['path'] = $uploadsPath;

    $returnData['type'] = $fileData['type'];

    $returnData['size'] = $fileData['size'];

    return hooksRun('upload_file_end', $returnData);
}