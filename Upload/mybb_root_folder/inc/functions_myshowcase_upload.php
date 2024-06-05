<?php
/**
 * MyShowcase Plugin for MyBB - Attachment support
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
 * http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\functions_myshowcase_upload.php
 *
 */

/**
 * Remove an attachment from a specific showcase
 *
 * @param int The showcase ID
 * @param string The posthash if available
 * @param int The attachment ID
 */
function myshowcase_remove_attachment($gid, $posthash, $aid)
{
    global $db, $mybb, $plugins, $me;
    $aid = intval($aid);
    $posthash = $db->escape_string($posthash);
    if ($posthash != '') {
        $query = $db->simple_select(
            'myshowcase_attachments',
            'aid, attachname, thumbnail, visible',
            "id='{$me->id}' AND aid='{$aid}' AND posthash='{$posthash}'"
        );
        $attachment = $db->fetch_array($query);
    } else {
        $query = $db->simple_select(
            'myshowcase_attachments',
            'aid, attachname, thumbnail, visible',
            "id='{$me->id}' AND aid='{$aid}' AND gid='{$gid}'"
        );
        $attachment = $db->fetch_array($query);
    }

    $plugins->run_hooks('myshowcase_remove_attachment_do_delete', $attachment);

    $db->delete_query('myshowcase_attachments', "aid='{$attachment['aid']}'");

    @unlink($me->imgfolder . '/' . $attachment['attachname']);
    if ($attachment['thumbnail']) {
        @unlink($me->imgfolder . '/' . $attachment['thumbnail']);
    }

    $date_directory = explode('/', $attachment['attachname']);
    if (@is_dir($me->imgfolder . '/' . $date_directory[0])) {
        @rmdir($me->imgfolder . '/' . $date_directory[0]);
    }
}

/**
 * Remove all of the attachments from a specific showcase
 *
 * @param int The showcase ID
 * @param string The posthash if available
 */
function myshowcase_remove_attachments($gid, $posthash = '')
{
    global $db, $mybb, $plugins, $me;

    $gid = intval($gid);
    $posthash = $db->escape_string($posthash);

    if ($posthash != '' && !$gid) {
        $query = $db->simple_select('myshowcase_attachments', '*', "id='{$me->id}' AND posthash='{$posthash}'");
    } else {
        $query = $db->simple_select('myshowcase_attachments', '*', "id='{$me->id}' AND gid='{$gid}'");
    }

    $num_attachments = 0;
    while ($attachment = $db->fetch_array($query)) {
        $plugins->run_hooks('myshowcase_remove_attachments_do_delete', $attachment);

        $db->delete_query('myshowcase_attachments', "aid='" . $attachment['aid'] . "'");

        @unlink($me->imgfolder . '/' . $attachment['attachname']);
        if ($attachment['thumbnail']) {
            @unlink($me->imgfolder . '/' . $attachment['thumbnail']);
        }

        $date_directory = explode('/', $attachment['attachname']);
        if (@is_dir($me->imgfolder . '/' . $date_directory[0])) {
            @rmdir($me->imgfolder . '/' . $date_directory[0]);
        }
    }
}

/**
 * Upload an attachment in to the file system
 *
 * @param array Attachment data (as fed by PHPs $_FILE)
 * @param bool Whether or not we are updating a current attachment or inserting a new one
 * @return array Array of attachment data if successful, otherwise array of error data
 */
function myshowcase_upload_attachment($attachment, $update_attachment = false, $dowatermark = false)
{
    global $db, $posthash, $mybb, $lang, $plugins, $cache, $me, $showcase_uid;

    $posthash = $db->escape_string($mybb->input['posthash']);

    if (isset($attachment['error']) && $attachment['error'] != 0) {
        $ret['error'] = $lang->error_uploadfailed . $lang->error_uploadfailed_detail;
        switch ($attachment['error']) {
            case 1: // UPLOAD_ERR_INI_SIZE
                $ret['error'] .= $lang->error_uploadfailed_php1;
                break;
            case 2: // UPLOAD_ERR_FORM_SIZE
                $ret['error'] .= $lang->error_uploadfailed_php2;
                break;
            case 3: // UPLOAD_ERR_PARTIAL
                $ret['error'] .= $lang->error_uploadfailed_php3;
                break;
            case 4: // UPLOAD_ERR_NO_FILE
                $ret['error'] .= $lang->error_uploadfailed_php4;
                break;
            case 6: // UPLOAD_ERR_NO_TMP_DIR
                $ret['error'] .= $lang->error_uploadfailed_php6;
                break;
            case 7: // UPLOAD_ERR_CANT_WRITE
                $ret['error'] .= $lang->error_uploadfailed_php7;
                break;
            default:
                $ret['error'] .= $lang->sprintf($lang->error_uploadfailed_phpx, $attachment['error']);
                break;
        }
        return $ret;
    }

    if (!is_uploaded_file($attachment['tmp_name']) || empty($attachment['tmp_name'])) {
        $ret['error'] = $lang->error_uploadfailed . $lang->error_uploadfailed_php4;
        return $ret;
    }

    $ext = get_extension($attachment['name']);
    // Check if we have a valid extension
    $query = $db->simple_select('attachtypes', '*', "extension='" . $db->escape_string($ext) . "'");
    $attachtype = $db->fetch_array($query);
    if (!$attachtype['atid']) {
        $ret['error'] = $lang->error_attachtype;
        return $ret;
    }

    // Check the size
    if ($attachment['size'] > $attachtype['maxsize'] * 1024 && $attachtype['maxsize'] != '') {
        $ret['error'] = $lang->sprintf($lang->error_attachsize, $attachtype['maxsize']);
        return $ret;
    }

    // Double check attachment space usage
    if ($mybb->usergroup['attachquota'] > 0) {
        $query = $db->simple_select(
            'myshowcase_attachments',
            'SUM(filesize) AS ausage',
            "uid='" . intval($showcase_uid) . "'"
        );
        $usage = $db->fetch_array($query);
        $usage = $usage['ausage'] + $attachment['size'];
        if ($usage > ($mybb->usergroup['attachquota'] * 1024)) {
            $friendlyquota = get_friendly_size($mybb->usergroup['attachquota'] * 1024);
            $ret['error'] = $lang->sprintf($lang->error_reachedattachquota, $friendlyquota);
            return $ret;
        }
    }

    // Check if an attachment with this name is already in the post
    $query = $db->simple_select(
        'myshowcase_attachments',
        '*',
        "filename='" . $db->escape_string(
            $attachment['name']
        ) . "' AND id='{$me->id}' AND (posthash='$posthash' OR (gid='" . intval($gid) . "' AND gid!='0'))"
    );
    $prevattach = $db->fetch_array($query);
    if ($prevattach['aid'] && $update_attachment == false) {
        $ret['error'] = $lang->error_alreadyuploaded;
        return $ret;
    }

    // Check if the attachment directory (YYYYMM) exists, if not, create it
    $month_dir = gmdate('Ym');
    if (!@is_dir($me->imgfolder . '/' . $month_dir)) {
        @mkdir($me->imgfolder . '/' . $month_dir);
        // Still doesn't exist - oh well, throw it in the main directory
        if (!@is_dir($me->imgfolder . '/' . $month_dir)) {
            $month_dir = '';
        }
    }

    // If safe_mode is enabled, don't attempt to use the monthly directories as it won't work
    if (ini_get('safe_mode') == 1 || strtolower(ini_get('safe_mode')) == 'on') {
        $month_dir = '';
    }

    // All seems to be good, lets move the attachment!
    $filename = 'post_' . $showcase_uid . '_' . TIME_NOW . '_' . md5(random_str()) . '.attach';

    $file = myshowcase_upload_file($attachment, $me->imgfolder . '/' . $month_dir, $filename);

    // Failed to create the attachment in the monthly directory, just throw it in the main directory
    if ($file['error'] && $month_dir) {
        $file = myshowcase_upload_file($attachment, $me->imgfolder . '/', $filename);
    }

    if ($month_dir) {
        $filename = $month_dir . '/' . $filename;
    }

    if ($file['error']) {
        $ret['error'] = $lang->error_uploadfailed . $lang->error_uploadfailed_detail;
        switch ($file['error']) {
            case 1:
                $ret['error'] .= $lang->error_uploadfailed_nothingtomove;
                break;
            case 2:
                $ret['error'] .= $lang->error_uploadfailed_movefailed;
                break;
        }
        return $ret;
    }

    // Lets just double check that it exists
    if (!file_exists($me->imgfolder . '/' . $filename)) {
        $ret['error'] = $lang->error_uploadfailed . $lang->error_uploadfailed_detail . $lang->error_uploadfailed_lost;
        return $ret;
    }

    // Generate the array for the insert_query
    $attacharray = array(
        'id' => intval($me->id),
        'gid' => intval($gid),
        'posthash' => $posthash,
        'uid' => intval($showcase_uid),
        'filename' => $db->escape_string($file['original_filename']),
        'filetype' => $db->escape_string($file['type']),
        'filesize' => intval($file['size']),
        'attachname' => $filename,
        'downloads' => 0,
        'visible' => 1,
        'dateuploaded' => TIME_NOW
    );

    // If we're uploading an image, check the MIME type compared to the image type and attempt to generate a thumbnail
    if ($ext == 'gif' || $ext == 'png' || $ext == 'jpg' || $ext == 'jpeg' || $ext == 'jpe') {
        // Check a list of known MIME types to establish what kind of image we're uploading
        switch (my_strtolower($file['type'])) {
            case 'image/gif':
                $img_type = 1;
                break;
            case 'image/jpeg':
            case 'image/x-jpg':
            case 'image/x-jpeg':
            case 'image/pjpeg':
            case 'image/jpg':
                $img_type = 2;
                break;
            case 'image/png':
            case 'image/x-png':
                $img_type = 3;
                break;
            default:
                $img_type = 0;
        }

        $supported_mimes = array();
        $attachtypes = $cache->read('attachtypes');
        foreach ($attachtypes as $attachtype) {
            if (!empty($attachtype['mimetype'])) {
                $supported_mimes[] = $attachtype['mimetype'];
            }
        }

        // Check if the uploaded file type matches the correct image type (returned by getimagesize)
        $img_dimensions = @getimagesize($me->imgfolder . '/' . $filename);

        $mime = '';
        $file_path = $me->imgfolder . '/' . $filename;
        if (function_exists('finfo_open')) {
            $file_info = finfo_open(FILEINFO_MIME);
            list($mime,) = explode(';', finfo_file($file_info, $file_path), 1);
            finfo_close($file_info);
        } elseif (function_exists('mime_content_type')) {
            $mime = mime_content_type($file_path);
        }

        if (!is_array($img_dimensions) || ($img_dimensions[2] != $img_type && !in_array($mime, $supported_mimes))) {
            @unlink($me->imgfolder . '/' . $filename);
            $ret['error'] = $lang->error_uploadfailed;
            return $ret;
        }

        //if requested and enabled, watermark the master image
        if ($me->userperms['canwatermark'] && $dowatermark && @file_exists($me->watermarkimage)) {
            //get watermark image object
            $format = strtolower(get_extension($me->watermarkimage));
            switch ($format) {
                case 'gif':
                    $watermark = @imagecreatefromgif($me->watermarkimage);
                    break;
                case 'jpg':
                case 'jpeg':
                case 'jpe':
                    $watermark = @imagecreatefromjpeg($me->watermarkimage);
                    break;
                case 'png':
                    $watermark = @imagecreatefrompng($me->watermarkimage);
                    break;
            }

            //check if we have an image
            if ($watermark) {
                //get watermark size
                $wmwidth = imagesx($watermark);
                $wmheight = imagesy($watermark);


                //get size of base image
                $size = getimagesize($me->imgfolder . '/' . $filename);

                //set watermark location
                switch ($me->watermarkloc) {
                    case 'lower-left':
                        $dest_x = 5;
                        $dest_y = $size[1] - $wmheight - 5;
                        break;
                    case 'lower-right':
                        $dest_x = $size[0] - $wmwidth - 5;
                        $dest_y = $size[1] - $wmheight - 5;
                        break;
                    case 'center':
                        $dest_x = $size[0] / 2 - $wmwidth / 2;
                        $dest_y = $size[1] / 2 - $wmheight / 2;
                        break;
                    case 'upper-left':
                        $dest_x = 5;
                        $dest_y = 5;
                        break;
                    case 'upper-right':
                        $dest_x = $size[0] - $wmwidth - 5;
                        $dest_y = 5;
                        break;
                }

                //get base image object
                switch ($img_type) {
                    case 1:
                        $image = @imagecreatefromgif($me->imgfolder . '/' . $filename);
                        break;
                    case 2:
                        $image = @imagecreatefromjpeg($me->imgfolder . '/' . $filename);
                        break;
                    case 3:
                        $image = @imagecreatefrompng($me->imgfolder . '/' . $filename);
                        break;
                }

                if ($image) {
                    //merge applying watermark
                    imagealphablending($image, true);
                    imagealphablending($watermark, true);
                    imagecopy(
                        $image,
                        $watermark,
                        $dest_x,
                        $dest_y,
                        0,
                        0,
                        min($wmwidth, $size[0]),
                        min($wmheight, $size[1])
                    );

                    //remove watermark from memory
                    imagedestroy($watermark);

                    //write modified file

                    $f = @fopen($me->imgfolder . '/' . $filename, 'w');
                    if ($f) {
                        ob_start();
                        switch ($img_type) {
                            case 1:
                                imagegif($image);
                                break;
                            case 2:
                                imagejpeg($image);
                                break;
                            case 3:
                                imagepng($image);
                                break;
                        }
                        $content = ob_get_clean();
                        ob_end_clean();

                        fwrite($f, $content);
                        fclose($f);
                        imagedestroy($image);
                    }
                }
            }
        }

        require_once MYBB_ROOT . 'inc/functions_image.php';
        $thumbname = str_replace('.attach', "_thumb.$ext", $filename);
        $thumbnail = generate_thumbnail(
            $me->imgfolder . '/' . $filename,
            $me->imgfolder,
            $thumbname,
            $me->thumb_height,
            $me->thumb_width
        );

        if ($thumbnail['filename']) {
            $attacharray['thumbnail'] = $thumbnail['filename'];
        } elseif ($thumbnail['code'] == 4) {
            $attacharray['thumbnail'] = 'SMALL';
        }
    }

    $plugins->run_hooks('myshowcase_upload_attachment_do_insert', $attacharray);

    if ($prevattach['aid'] && $update_attachment == true) {
        unset($attacharray['downloads']); // Keep our download count if we're updating an attachment
        $db->update_query(
            'myshowcase_attachments',
            $attacharray,
            "aid='" . $db->escape_string($prevattach['aid']) . "'"
        );
        $aid = $prevattach['aid'];
    } else {
        $aid = $db->insert_query('myshowcase_attachments', $attacharray);
    }

    return $ret;
}

/**
 * Actually move a file to the uploads directory
 *
 * @param array The PHP $_FILE array for the file
 * @param string The path to save the file in
 * @param string The filename for the file (if blank, current is used)
 */
function myshowcase_upload_file($file, $path, $filename = '')
{
    global $plugins;

    if (empty($file['name']) || $file['name'] == 'none' || $file['size'] < 1) {
        $upload['error'] = 1;
        return $upload;
    }

    if (!$filename) {
        $filename = $file['name'];
    }

    $upload['original_filename'] = preg_replace('#/$#', '', $file['name']); // Make the filename safe
    $filename = preg_replace('#/$#', '', $filename); // Make the filename safe
    $moved = @move_uploaded_file($file['tmp_name'], $path . '/' . $filename);

    if (!$moved) {
        $upload['error'] = 2;
        return $upload;
    }
    @my_chmod($path . '/' . $filename, '0644');
    $upload['filename'] = $filename;
    $upload['path'] = $path;
    $upload['type'] = $file['type'];
    $upload['size'] = $file['size'];
    $plugins->run_hooks('myshowcase_upload_file_end', $upload);
    return $upload;
}

?>
