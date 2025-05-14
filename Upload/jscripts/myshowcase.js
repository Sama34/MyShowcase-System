var Showcase = {
	init: function()
	{
		return true;
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
	},

	reportComment: function(commentSlug, showcaseID) {
		MyBB.popupWindow("/report.php?modal=1&type=showcase_comments&pid=" + commentSlug + "&showcaseID=" + showcaseID);
	},

	showDeletedEntry: function(entryID)
	{
		$('#deleted_entry_' + entryID).slideToggle("slow");
		$('#entryBody' + entryID).slideToggle("slow");
	},

	showDeletedComment: function(commentSlug)
	{
		$('#deleted_comment_' + commentSlug).slideToggle("slow");
		$('#commentBody' + commentSlug).slideToggle("slow");
	},

	showIgnoredEntry: function(entryID)
	{
		$('#ignored_entry_' + entryID).slideToggle("slow");
		$('#entryBody' + entryID).slideToggle("slow");
	},

	showIgnoredComment: function(commentSlug)
	{
		$('#ignored_comment_' + commentSlug).slideToggle("slow");
		$('#commentBody' + commentSlug).slideToggle("slow");
	},

};
Event.observe(document, 'dom:loaded', Showcase.init);
