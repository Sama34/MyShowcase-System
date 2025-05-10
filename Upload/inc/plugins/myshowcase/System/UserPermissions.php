<?php

namespace MyShowcase\System;

class UserPermissions
{
    public const CanView = 'canview';
    public const CanViewEntries = 'can_view_entries';
    public const CanCreateEntries = 'canadd';
    public const CanUpdateEntries = 'canedit';
    public const CanDeleteEntries = 'canedit';
    public const CanViewComments = 'canviewcomment';
    public const CanCreateComments = 'cancomment';
    public const CanUpdateComments = 'can_update_comments';
    public const CanDeleteComments = 'candelowncomment';
    public const CanViewAttachments = 'canviewattach';
    public const CanCreateAttachments = 'canattach';
    public const CanUpdateAttachments = 'can_update_attachments';
    public const CanDeleteAttachments = 'can_delete_attachments';
    public const CanDownloadAttachments = 'canviewattach';
    public const AttachmentsUploadQuote = 'attachments_upload_quote'; // per day ?
    public const AttachmentsDownloadQuote = 'attachments_download_quote'; // per day ?
    public const CanWaterMarkAttachments = 'canwatermark';
    public const AttachmentsLimit = 'attachlimit'; // # of files
    public const CanViewSoftDeletedNotice = 'can_view_soft_deleted_notice';
    public const ModerateNewEntries = 'moderate_new_entries';
    public const ModerateUpdatedEntries = 'moderate_updated_entries';
    public const ModerateNewComments = 'moderate_new_comments';
    public const ModerateUpdatedComments = 'moderate_edited_comments';
    public const ModerateNewAttachments = 'moderate_new_attachments';
    public const ModerateUpdatedAttachments = 'moderate_updated_attachments';
    public const CanSearch = 'cansearch';
    public const CanDeleteAuthorComments = 'candelauthcomment';
    public const NewPointsCanGetIncome = 'newpoints_can_get_points';
    public const NewPointsIncomeEntry = 'newpoints_income_entry';// highest
    public const NewPointsIncomeComment = 'newpoints_income_comment';// highest
    public const NewPointsIncomeAttachment = 'newpoints_income_attachment';// highest
    public const NewPointsChargeEntry = 'newpoints_income_entry'; // lowest
    public const NewPointsChargeComment = 'newpoints_income_comment';// lowest
    public const NewPointsChargeAttachment = 'newpoints_income_attachment';// lowest
}

