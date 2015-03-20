<?php
/**
 * MyShowcase Plugin for MyBB - Language file for ACP, MyShowcase Help
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
 * License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
				http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\languages\<language>\admin\myshowcase_help.lang.php
 *
 */

 $l['myshowcase_help_main'] = <<<EOT
<h3>Welcome</h3>
Welcome to the CommunityForums.com MyShowcase System and Plugin for MyBB 1.6. The MyShowcase System allows admins to add multiple showcases to thier MyBB installation so that visitors can insert information about and images of nearly any item for others to view. The MyShowcase System is fully customizable and supports SEO, multiple showcase types, permissions, custom data fields, custom searches, images, comments and moderation. The content below outlines many of the uses of and usage of the MyShowcase System.
<p />
<strong><font color="red">Please read this entire document before using the MyShowcase System. There are many details to consider and understand.</font></strong>
<h3>Getting Started</h3>
<p />
<h4>What is a Showcase?</h4><br />
In direct terms, a showcase is place where you display your property. In website terms, a showcase is a place where a user can post and share information and images about their property. However, this MyShowcase System is so customizable, that it can be used for trucks, cars, boats, homes, guns, bikes, computers, home entertainment setups, RPG character profiles, toy collections, artwork and anything else you can think of. It can even be used as a link directory.<br />
<br />
The MyShowcase System include sample data for setting up a Showcase for 4x4 Trucks. As you look at the sample data, you will notice fields for year, make, model, and other features including space for custom content. These are customizable and extensible attributes that can be altered to meet any content type.
<p />
<h4>How the MyShowcase System works in general</h4><br />
The system works by combining a showcase with a field set, which in turn contains field definitions and in the case of dynamic fields, the appropriate field data. Each field set that is created can be used more than one time by an unlimited number of showcase systems on your site. Once a showcase is created and field set assigned to it, the showcase can be edited to set the permissions and features. A main file is assigned to the showcase and this file is uploaded by the admin and a linked in the site navigation to be found by and accessible to visitors.<br />
<br />
When a visitor browses a showcase, depending on that visitors usergroup assignments and the permissions assigned to the showcase, that visitor may be able to view existing showcases, create their own showcase, edit their own showcase, comment on showcases, add attachments to their own showcases and possibly even moderate comments on their showcase.  When creating a user showcase, the visitor will add information to whatever fields the admin has required and any optional fields. If enabled, attachments can be added. <br />
<br />
The system will store the user entered data and process the attachments. When another user visits the showcase entry they will see the information the author provided and if enabled, have the ability to comment on the entry.
<h3>What the MyShowcase System WILL NOT DO</h3>
<p />
While the MyShowcase System is very advanced and very flexible, there are still some things that it can not easily automate. 
<ul>
<li>Templates are not updated to include links to a new MyShowcase, such as in TopLinks within Header templates
<li>Create or edit secondary language files for field sets
<li>Create or edit secondary language files that are customized for showcase specific content
<li>Change file/folder permissions, these need to be set by the admin
<li>Add .htaccess entries for SEO firendly URLs, though the MyShowcase System will provide content for the admin to copy/paste into .htaccess
</ul>
<h3>Miscellaneous</h3>
<p />
There are a large number of templates included with the MyShowcase System. These templates are installed when your Activate & Install the MyShowcase System plugin. These templates are installed as Master Templates into a new MyShowcase template group. This allows the templates to be applicable to any template set and customized independently within each set. During an upgrade of the MyShowcase System, these master templates may be updated and thus any modifed templates may become out of date and some fuctionality lost. If this occurs, you should make note of your customizations, revert the template and reapply the customizations.
<p />
FileSys permission requirements are rather simple. The folder you specify to store attachments needs to have the same permissions and owner as the /Uploads folder that is part of MyBB.
<p />
EOT;

$l['myshowcase_help_summary'] = <<<EOT
<h3>Summary Sub-Menu</h3>
<h4>Information Displayed</h4><br />
The Summary sub-menu displays all current showcases and the primary settings for identifying the showcase and its current usage statistics and it's status. Available information is the name, description, main file, image folder, and relative paths for each showcase. Also presented are the number of entries, the total number of comments, the total number of attachments, the total size of all attachments assigned to the showcase. There is also the name and ID of the field set assigned to the showcase as well as the status of the showcase. Status icons include enabled, disabled, and database table not present. <br />
<br />
There is also a Controls menu for each showcase. From this drop-down menu, each individual showcase can be edited, deleted, enabled, disabled or have its database table created or deleted.
<h4>Creating a New Showcase</h4><br />
It is highly recommended that a field set be created prior to creating the actual showcase since a field set with all the fields expected is required when creating a new showcase and can not be edited once the database table for the showcase is present.<br />
<br />
From the Summary sub-menu, enter a Name, Description, Main File, Image Folder, Relative Path and select a Field Set. Click Add a Showcase.
<ul>
<li><strong>Name:</strong> is used on the visitor side for the navigation and a few other keywords in the default language file.
<li><strong>Description:</strong> is not used on the visitor side, it is purely for admin reference.
<li><strong>Main File:</strong> is the name of the PHP file that will represent the Showcase and to which you will link your visitors to. The MyShowcase System comes with a file called showcase.php that is uploaded to the MyBB root folder. To use this file as-is for a single Showcase install, specify the Main File field as 'showcase.php' (without the quotes). To use multiple showcases, copy and rename this file, for example, dock.php for boating use or user_pc.php for computer details, and use that as the Main File. You do <strong>NOT</strong> include path information here.
<li><strong>Image Folder:</strong> is the path, relative to the specified Main File, that will be used for storing attachments. You must create this folder and set the appropriate write permissions so images can be uploaded there. You <strong>MUST</strong> use a separate Image Folder for each showcase you create in order to prevent overwriting or corrupting data.
<li><strong>Relative Path from Forum:</strong> is the path from the MyBB root folder to the Main File. This allows the showcase main file to be located outside the MyBB root folder and still be linked inside MyBB postbits and portal. If you setup the Main File to be located outside the MyBB root folder, you must edit the Main File directly and specify the relative path to the forums. Any required edits are commented as such in the file.
</ul>
As each showcase created can have any table schema given the field set assigned, once you have created the showcase in the MyShowcase Admin and assigned a complete field set, you need to create the data table. In the Summary sub-menu, for the showcase you want to create, click the drop down menu in the Controls field and select Create Table. Once you create the table, which will be named <mybb_prefix>_myshowcase_dataXX where XX is the ID of the given myshowcase, you can then enable the showcase for use via the same Controls menu. This will not create the link to the showcase, only enable it for use. You need to insert a link to the showcase yourself via the MyBB template editor. This same menu will allow you to disable a specific showcase, such as for maintenance.
<h4>Controls for an Existing MyShowcase</h4><br />
For each available showcase, a drop-down controls menu is available for several operations. A showcase can be enabled and disabled without impacting any data, sent to the Edit Existing sub-menu via the edit option, deleted from the system including all entries, comments and attachments for that showcase, and finally create or delete the database table for the showcase. The ability to delete the database table is available only when there are no entries in it. These two options are great for testing a new showcase and the fields and options for it.<br />

EOT;

$l['myshowcase_help_fields'] = <<<EOT
<h3>Field Sets, Fields and Field Data</h3>
<h4>Creating or Editing a Field Set</h4><br />
To get started, you should really begin by creating at least one field set. The field set is where you assign the various fields for which the showcase will accept and store data. To create a field set, enter the Field Settings sub-menu and provide a name in the form and click Save Changes.<br />
<br />
If you have any existing field sets, you can change the name of any of them by editing the data in the form field and clicking Save Changes. You can create a new field set at the same time by providing a name in the bottom form field.
<h4>Creating or Editing a Field</h4><br />
Within each field set, are fields. You must create a field for each of the attributes you want to make available to your users. The system required fields such as date, user, ID, etc. are automatically accounted for, so there is no need to worry about those. The MyShowcase System supports various field types and their HTML representations. Supported field types (the format the data is actually stored as) are:
<ul>
<li>Integer (integers only, ranging from -2,147,483,648 to 2,147,483,648)
<li>Bigint (integers only, ranging from -9,223,372,036,854,775,808 to 9,223,372,036,854,775,808)
<li>Text (allows nearly any character up to 65,535 characters)
<li>Varchar (alphanumeric, pretty much any character is allowed. the typical max length allowed is 255 however some databases accept up to 65,535 characters)
<li>Timestamp (not a real field type, but it assumes type that supports unix style timestamps in integer format)
</ul><br />
These fields can be represented in the showcase form as
<ul>
<li>Textbox - basic text input box (like the subject of a new reply form) during an edit and is displayed as a single line during a view
<li>Textarea - basic textarea input box (like the message body of a new reply form) during an edit and is displayed as multiline text during a view
<li>DB - a select box (like thread prefix of a new thread form) during edit and is displayed as a single line item during a view. These fields store their contents in the database and are editable during setup. This type forces the field type to Integer in order to support the required lookups.
<li>Radio buttons - a simple radio button control. This type forces the field type to Integer
<li>Check boxes - a simple check box control. This type forces the field type to Integer. 
<li>URL - a textbox box during edit, but output is as a clickable link. Forces a varchar(255) field type
<li>Date - a set of select boxes that have month, day and year showing during edit for the user to pick a data. during view, its output as a US style date (MM/DD/YYYY). Forces a varchar(10) field type
</ul><br />
These are the supported HTML types (how the data is displayed or input by the user). The URL and Data types are not actual HTML form inputs, but are listed here in order to support the expected input and proper disply of these type of data as well as provide the required input form objects to make use of them.<br />
<br />
To edit the fields of a field set, browse to the Field Settings sub-menu and for the field set you want to edit the fields for, click Edit in the Control menu. When creating or editing a field, you can specify the field name (up until the table is created), the label for the field, its size, whether or not the field is required, searchable and enabled in the form. Required fields are those that must have data entered into them when saving a showcase entry, enabled fields are those displayed to the user in a showcase entry and searchable fields are those that the MyShowcase search functionality will support. Please note that the Field Name must be a valid field name for use in a database table and as such any entry for Field Name will be sanitzed to a valid string. <br />
<br />
There are also settings for minimum and maximum data lengths for each field. In cases of required fields, for example a 'Year' field should have minimum and maximum values for 4 in order to to force users to enter a 4-digit year. All fields are validated against these values, so the minumum length of non-required fields should be set to 0.<br />
<br />
The parser setting is to make the field run through the MyBB parser so that, if enabled, smilies, BBCode and HTML are parsed and converted as required. This applies to text based fields only. Each showcase has settings for allowing smilies, BBCode and HTML. Edit a Showcase from the Summary menu and switch to the 'Other' Tab and check the items you wish to be supported in the particular showcase.<br />
<br />
<font color="blue"><strong>Special Case: </strong>Please note that for textarea HTML types, the minimum value is used to determine the minimum length of the content. However, the maximum value is an HTML formatting value that is used to define the number of rows in the textarea form object.</font><br />
<br />
<font color="blue"><strong>Special Case: </strong>Please note that for date HTML types, the minimum and maximum data lengths are used to populate the Year control. For example, specifying 1950 as the minimum and 2050 as the maximum lengths will result in a Year control with 101 options.</font><br />
<br />
The Field Order options are there to change the display order of the fields, regardless of the order in which they were created. The (View) option is the order in which the fields are shown in the view and edit pages for a showcase entry. The (List) option is the order in which the fields are shown in the main showcase list view. Setting the (List) field to -1 leaves the field out of the primary list view. All other values are used to order the fields in between the fixed fields (view link, username, <showcase fields>, comments, views, timestamp). The Field Order settings do not need to match between the (View) and (List) versions.<br />
<br />
The Format? options can be set for any field type, however they are only applied to numeric fields. Simply select the format style, if any, for the field. <br />
<br />
The MyShowcase System will handle labels for the fields you create, at least for the default language of the MyBB installation. These labels are used in the table header of the list view as well as the field prompts when creating, editing and viewing a showcase entry. The MyShowcase System will create a new language file for each field set and populate the file with the labels you enter/save.  If your MyBB installation supports multiple languages, you can then copy these generated files and translate them as you would with any other languge file. MyShowcase System generated language files are located in the <mybb_root>\inc\languages\<default_lang>\ folder and use the naming convention 'myshowcase_fsXX.lang.php' where XX is the ID of the field set.<br />
<br />
The ability to modify a field label at any time allows for minor modification to a showcase's configuration by changing a field's label but leaving the field itself alone. This allows an admin to slightly alter the use of a field without requiring a showcase to be rebuilt.<br />
<br />
Emtpy labels are populated with the cleaned field name during a Save Changes operation, but can later be modified at any time. 
<h4>Editing Field Data</h4><br />
Fields of 'db' (select/option), 'radio' and 'checkbox' HTML types are data aware and thus need to know what data to display in them for the user to choose from. Once such a field has been added to the field set, the options for that field can be added. In the Controls field, expand the menu for that field and choose Edit Options. This will allow you to add, remove, edit and reorder options for the users to select from. Data aware fields are NOT language independent and will be stored and displayed in the system language only.<br />
<h4>Field Set Additional Infomation</h4><br />
A field set can be applied to more than one showcase. Once assigned to a showcase and that showcase's database table has been created, the field set can not be significantly changed, though it can still be assigned to additional showcases. The allowable edits would be the label, numeric formatting, parsed, enabled, required and searchable as these are not directly related to the database schema.<br />
<br />
On the main Field Setting page, there are three fields, 'Assiged To', 'In Use By' and 'Language File'. Assigned To shows how many showcases have that field set assigned to it. In Use By is the number of the Assigned To showcases that have the database table created. Language File is a status field, indicating if the default language file for the field set is present and writable, present and not writable or not found.
EOT;

$l['myshowcase_help_edit'] = <<<EOT
<h3>Editing an Existing MyShowcase</h3><br />
The MyShowcase System allows any showcase to be edited, the level to which it can be modifed is dependent on the current status of the showcase. If the showcase has had it database table created, the field set can not be changed.<br />
<br />
To edit a showcase, select Edit from the controls drop-down on the Summary sub-menu. Direct access to the Edit Existing sub-menu is not supported. From this point there are several tabs, each containing its own form. <strong>So edits to one tab need to be saved before attempting to save settings on another tab.</strong>. The tabs include:
<ul>
<li><strong>Main Settings:</strong> Primary settings, most of these are the same as when the showcase was created.
<li><strong>Other:</strong> Additional settings covering moderation, comments, attachments and display options.
<li><strong>Permissions:</strong> This tab controls usergroup based permissions.
<li><strong>Moderators:</strong> Additional moderators or moderator groups and thier permissions.
</ul>
<br />
<h3>Main Settings Tab</h3><br />
This tab includes the main settings for the showcase. Several items are the same as when the showcase was first created.
<ul>
<li><strong>Showcase Name:</strong> The display name of the showcase. Used in titles, breadcrumbs and Who's Online.
<li><strong>Showcase Description:</strong> Simple description. Used in the page title in the browser.
<li><strong>Main File:</strong> This is the PHP file to be used for the showcase. There should be no path information here, filename only.
<li><strong>Folder to Store Attachments:</strong> This is the location on the server that will be used to store attachments for the shwocase. This should be relative to the Main File and must be writable by the webserver.
<li><strong>Default List Image:</strong> This image will be displayed in the View column on the showcase index view and will be linked to view the particular showcase entry. Leaving this blank, will simply show 'View' as the link text.
<li><strong>Watermark Image:</strong> If you wish to allow usergroups to watermark their attachments, specify the image here. A transparent PNG is highly recommended. This image will be resized to fit attachments that are smaller.
<li><strong>Watermark Location:</strong> Use this to specify the location of the watermark.
<li><strong>List View Link:</strong> This option will attempt to replace the 'View' link text or the Default List Image with the first attachment found for the showcase entry. This option may have a performance impact. Use with caution.
<li><strong>Path to Forums:</strong> This is the relative path from the Forum Index to the Main File. This is used when 'Link this showcase in user details of postbit' option is enabled.
<li><strong>Field Set:</strong> This is the Field Set assigned to the showcase. If the showcase data table has been created, this setting can not be altered.
</ul>
<br />
<h3>Other Tab</h3><br />
This tab includes the optional settings for the showcase.
<ul>
<li><strong>Pruning:</strong> MyShowcase includes a task item to prune a showcase based on tiem since last edit. To enable this feature, specify a value and interval. To disable, specify '0' value and any interval.
<li><strong>Moderation:</strong> Setting this option will make all new and edited showcases unapproved upon saving. Staff and moderators for the showcase will see a global notice indicating there are pending entries.
<li><strong>Text Type Fields:</strong> This is the maximum number of characters allowed in a TextArea input field. In order to support mulitple line input (think of a post message body) a text area form is required, but you can limit the number of characters input in order to preserve formatting or simply minimize content.
<li><strong>Allow Attachments:</strong> This is the master switch for allowing attachments to the showcase entries.
<li><strong>Thumbnail Width:</strong> This is the max width of a thumbnail when they are created.
<li><strong>Thumbnail Height:</strong> This is the max height of a thumbnail when they are created.
<li><strong>Allow Comments:</strong> This is the master switch for allowing comments to the showcase entries.
<li><strong>Max characters for Comments:</strong> This is the maximum number of characters allowed in an individual comment.
<li><strong>Allow Smilies:</strong> If a field is set to use the Parser, are smilies allowed?
<li><strong>Allow BBCode:</strong> If a field is set to use the Parser, is BBCode allowed?
<li><strong>Allow HTML:</strong> If a field is set to use the Parser, is HTML allowed? This is option can be dangerous. It is highly recommended that this be left unchecked.
<li><strong>Number of attachments to display per row:</strong> This setting will force a fixed number of attachment thumbnails to be shown before a new line is output. Setting this to '0' allows the browser to handle the layout directly.
<li><strong>Initial number of comments to display in each showcase.:</strong> The number of comments can be large for a showcase. Upon first viewing a shwocase, limit the number of comments to this value. If there are more comments a link will be provided to reload the page with all the comments shown.
<li><strong>Show all fields, even if empty, in showcase view:</strong> This setting will output all the fields for a shwocase, even if the user has not supplied data for it. If a consistent view/layout matters, enable this option.
<li><strong>Link this showcase in user details of postbit:</strong> Creates a link in the user details area of the postbit that disaply's that users entries in the showcase. This used the Showcase Name and the number of entries the user has as the link.
<li><strong>Try to display a random entry in this showcase that has attachments on the portal:</strong> Attempt to find a random showcase that has an image attachment and disaply it on the portal. This setting requires some manual template edits to the portal as well as possible code changes to MYBB_ROOT/inc/plugins/myshowcase.php in order to properly display the contents to your liking. For assistance with this, visit the <a href="http://www.communityplugins.com/" target="_blank">Community Plugins</a> site for help.
</ul>
<br />
<h3>Permissions Tab</h3><br />
The MyShowcase System supports group based permissions and honors the built-in MyBB usergroup assignments, including additional groups. The MyShowcase System permissions setup uses the MyBB 1.4 style check boxes and is group specifc. Permissions include:
<ul>
<li><strong>Can View:</strong> Can the group members view the showcase.
<li><strong>Can Add:</strong> Can the group members create new showcase entries.
<li><strong>Can Edit:</strong> Can the group members edit their own showcase entries.
<li><strong>Can Attach:</strong> Can the group members add attachments to their showcase entries, if attachments are allowed for the showcase.
<li><strong>Can View Comments:</strong> Can the group members view a showcase entry's comments.
<li><strong>Can View Attachments:</strong> Can the group members view a showcase entry's attachments.
<li><strong>Can Comment:</strong> Can the group members add comments to a showcase entry.
<li><strong>Can Delete Own Comments:</strong> Can the group members delete their own comments in any showcase entry.
<li><strong>Can Delete Author Comments:</strong> Can the group members delete comments of others in the user's own showcase entries.
<li><strong>Can Search MyShowcases:</strong> Can the group members search the showcase.
<li><strong>Can Watermark Images:</strong> Can the group members add a watermark to image uploads. This only applies when the Watermark Image is set on the Main Settings tab and a valid file.
<li><strong>Max Attachments per MyShowcase:</strong> The maximum number of attachments group members can add to their own showcase entries. This is per showcase entry, not a total. Use -1 for unlimited attachements.
</ul>
Also, similar to how MyBB works, permissions are inclusive, that means 'yes' will always win over a 'no' setting when combining usergroups and additional groups settings. The maximum attachments will use the largest of the groups settings for a given user (with the exception being a -1 value meaning unlimited). Each set of permissions are specific to the showcase being modified. Thus, it is possible to have differing permissions for each showcase setup.<br />
<br />
<font color="blue">If you add a group after a showcase has been created, you will need to provide the permissions for any existing showcase for each new group. However, guest level permissions will be automatically applied until specific permissions are supplied.</font>
<br />
<h3>Moderators Tab</h3><br />
The MyShowcase System assumes full moderator permissions for the default MyBB Moderator, Super Moderator and Administrator groups. It also supports additional user and group based moderators, just like MyBB 1.6 forum permissions and honors the built-in MyBB usergroup assignments, including additional groups. The MyShowcase System permissions setup uses the MyBB 1.4 style check boxes and is group and showcase specifc. Permissions include:
<ul>
<li><strong>Can Approve:</strong> Can the moderator or moderator group members approve and unapprove showcases.
<li><strong>Can Edit:</strong> Can the moderator or moderator group members edit any showcase entries.
<li><strong>Can Delete:</strong> Can the moderator or moderator group members delete any showcase entries.
<li><strong>Can Delete Comments:</strong> Can the moderator or moderator group members delete comments.
</ul>
However, it is possible to assign any usergroup as a moderator to a specific showcase and then reduce the permissions for that group. For example, the Adminsitrator group can be added as a moderator group and then have all the moderation permissions revoked. The MyShowcase System will then not allow Administrators to moderate that specific showcase. Additionally, a specific user assigned as a moderator can have any of the permissions applied or revoked and those permissions will be used regardless of the usergroup that user is in and the permissions assigned to the group, either as default or specifically in the additional moderators section. 
<br />
EOT;

$l['myshowcase_help_cache'] = <<<EOT
<h3>Rebuild Cache</h3><br />
The MyShowcase System piggybacks on the built-in MyBB cache system in order to improve performace and reduce server load by minimizing the queries that are required. While it is expected that most operations to the MyShowcase System via the ACP will result in the cache being updated, it is possible that the cache will become out of date, expecially after manual database edits. In this case, simply click Rebuild Cache in the sub-menu of the MyShowcase Admin and all MyShowcase related caches will be updated. You can view the current cache contents via the standard ACP functionality.<br />
<br />
There is no actual HTML output for this sub-menu. Clicking Rebuild Cache will rebuild the cache and then return the user to the Summary sub-menu.
EOT;

$l['myshowcase_help_other'] = <<<EOT
<h3>Language Support</h3><br />
The MyShowcase System has built-in language support. In fact, the system will create language files on the fly for fields in a field set in the board's default language. There are many English language files supplied with the MyShowcase System so it can run right away using the English language. <br />
<br />
The default language file used for all showcases is /inc/language/english/myshowcase.lang.php however a language file for an individual showcase can be created using the default file as a template. Simply create copy of the default file and rename it to myshowcaseXX.lang.php where XX is the ID of the showcase it applies to. For example, if there are two showcases and a need for customized language support for the second showcase, which for example has an ID of 5, the required filename would be myshowcase5.lang.php. The MyShowcase System will automatically attempt to use the customized language file first and if not loaded, will fallback to the default language file.<br />
<br />
Fieldset language files are used to define the labels for the individual showcase fields in the MyShowcase System. These language files are created on-the-fly when creating and editing the various fieldsets. These files are created in the /inc/language/&lt;language&gt;/ folder and are named myshowcase_fsXX.lang.php. Similar to the showcase level files, XX is the ID of the fieldset being modified. Again, &lt;language&gt; is the board's default language. <br />
<br />
If the MyBB installation supports multiple languages, the non-default languages will need to have thier own language files created manually. Simple copy/paste the 'myshowcase' files from the default language folder to the other language folders. Then edit those file and translate as needed. There are language files in the main language folder as well as the /admin folder.<br />
<br />
All language files are also editable via the built-in MyBB language modification functionality. However, the on-the-fly language edits within the MyShowcase System require the proper filesystem permissions on the langauge folders in order to create/modify them. This is the same permissions required for the built-in MyBB language editor to work.
</ul>
EOT;
?>
