var Showcase = {
	init: function()
	{
		return true;
	},

	removeAttachment: function(attachment_id)
	{
		if(confirm(removeshowcaseattach_confirm) == true)
		{
			document.input.attachmentaid.value = attachment_id;
			document.input.attachmentact.value = "remove";
		}
		else
		{
			document.input.attachmentaid.value = 0;
			document.input.attachmentact.value = "";
			return false;
		}
	},

	removeShowcase: function(entry_id)
	{
		if(confirm(removeshowcase_confirm) == true)
		{
			document.admin.showcasegid.value = entry_id;
			document.admin.showcaseact.value = "remove";
		}
		else
		{
			document.admin.showcasegid.value = 0;
			document.admin.showcaseact.value = "";
			return false;
		}
	},

	editShowcase: function(entry_id)
	{
		document.admin.showcasegid.value = entry_id;
		document.admin.showcaseact.value = "edit";
	},

    confirmSubmit: function(confirmationText,  formID) {
        if (confirm(confirmationText)) {
            document.getElementById(formID).submit();

            return true;
        } else {
            return false;
        }
    },

	reportEntry: function(entryID, showcaseID) {
		MyBB.popupWindow("/report.php?modal=1&type=showcase_entries&pid=" + entryID + "&showcaseID=" + showcaseID);
	}

	reportComment: function(commentID, showcaseID) {
		MyBB.popupWindow("/report.php?modal=1&type=showcase_comments&pid=" + commentID + "&showcaseID=" + showcaseID);
	}

};
Event.observe(document, 'dom:loaded', Showcase.init);
