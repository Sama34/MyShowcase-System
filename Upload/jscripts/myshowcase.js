var Showcase = {
	init: function()
	{
		return true;
	},

	removeAttachment: function(aid)
	{
		if(confirm(removeshowcaseattach_confirm) == true)
		{
			document.input.attachmentaid.value = aid;
			document.input.attachmentact.value = "remove";
		}
		else
		{
			document.input.attachmentaid.value = 0;
			document.input.attachmentact.value = "";
			return false;
		}
	},

	removeShowcase: function(gid)
	{
		if(confirm(removeshowcase_confirm) == true)
		{
			document.admin.showcasegid.value = gid;
			document.admin.showcaseact.value = "remove";
		}
		else
		{
			document.admin.showcasegid.value = 0;
			document.admin.showcaseact.value = "";
			return false;
		}
	},

	editShowcase: function(gid)
	{
		document.admin.showcasegid.value = gid;
		document.admin.showcaseact.value = "edit";
	},

	reportShowcase: function(gid)
	{
		MyBB.popupWindow(showcase_url+"?action=report&gid="+gid, "reportShowcase", 400, 300)
	}

};
Event.observe(document, 'dom:loaded', Showcase.init);
