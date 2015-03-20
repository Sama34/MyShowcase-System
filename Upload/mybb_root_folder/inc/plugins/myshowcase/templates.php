<?php
/**
 * MyShowcase Plugin for MyBB - Templates
 * Copyright 2012 CommunityPlugins.com, All Rights Reserved
 *
 * Website: http://www.communityplugins.com
 * Version 2.5.2
* License: Creative Commons Attribution-NonCommerical ShareAlike 3.0
				http://creativecommons.org/licenses/by-nc-sa/3.0/legalcode
 * File: \inc\plugins\myshowcase\templates.php
 *
 */

$myshowcase_templates = array();
$myshowcase_templates['allreports'] = "{\$showcase_top}\r\n<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">\r\n<tr>\r\n<td class=\"thead\" align=\"center\" colspan=\"6\"><strong>{\$lang->myshowcase_reports_note}</strong></td>\r\n</tr>\r\n<tr>\r\n<td class=\"tcat\" align=\"center\" width=\"10%\"><span class=\"smalltext\"><strong>{\$lang->myshowcase_showcase}</strong></span></td>\r\n<td class=\"tcat\" align=\"center\" width=\"15%\"><span class=\"smalltext\"><strong>{\$lang->myshowcase_member}</strong></span></td>\r\n<td class=\"tcat\" align=\"center\" width=\"15%\"><span class=\"smalltext\"><strong>{\$lang->reporter}</strong></span></td>\r\n<td class=\"tcat\" align=\"center\" width=\"35%\"><span class=\"smalltext\"><strong>{\$lang->report_reason}</strong></span></td>\r\n<td class=\"tcat\" align=\"center\" width=\"10%\"><span class=\"smalltext\"><strong>{\$lang->report_time}</strong></span></td>\r\n</tr>\r\n{\$reports}\r\n{\$reportspages}\r\n</table>\r\n<br />\r\n{\$footer}\r\n</body>\r\n</html>";
$myshowcase_templates['attachment_view'] = "<html>\r\n<head>\r\n<title>{\$mybb->settings['bbname']}</title>\r\n{\$headerinclude}\r\n<script type=\"text/javascript\" src=\"{\$forumdirslash}jscripts/myshowcase.js?ver=2502\"></script>\r\n</head>\r\n<body>\r\n{\$header}\r\n{\$showcase_table_header}\r\n<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">\r\n<tr><td align=\"center\" class=\"trow1\"><img src=\"{\$showcase_attachment}\" alt=\"{\$showcase_header_label}\" /><br /><br />{\$showcase_attachment_description}</td></tr>\r\n</table>\r\n{\$footer}\r\n</body>\r\n</html>";
$myshowcase_templates['feature_disabled'] = "<tr><td class=\"trow1\" align=\"center\" width=\"100%\" colspan=2><p>{\$lang->myshowcase_feature_disabled}<p></td></tr>";
$myshowcase_templates['field_checkbox'] = "<input type=\"checkbox\" name=\"{\$showcase_field_name}\" value=\"{\$showcase_field_value}\" {\$showcase_field_checked} {\$showcase_field_enabled}>";
$myshowcase_templates['field_date'] = "{\$lang->myshowcase_month} <select name=\"{\$showcase_field_name}_m\" {\$showcase_field_enabled} style=\"width: 100px\">\r\n{\$showcase_field_options_m}\r\n</select>\r\n&nbsp;\r\n{\$lang->myshowcase_day} <select name=\"{\$showcase_field_name}_d\" {\$showcase_field_enabled} style=\"width: 100px\">\r\n{\$showcase_field_options_d}\r\n</select>\r\n&nbsp;\r\n{\$lang->myshowcase_year} <select name=\"{\$showcase_field_name}_y\" {\$showcase_field_enabled} style=\"width: 100px\">\r\n{\$showcase_field_options_y}\r\n</select>";
$myshowcase_templates['field_db'] = "<select name=\"{\$showcase_field_name}\" width=\"{\$showcase_field_width}\" {\$showcase_field_enabled}>\r\n    {\$showcase_field_options}\r\n</select>";
$myshowcase_templates['field_radio'] = "<input type=\"radio\" name=\"{\$showcase_field_name}\" value=\"{\$showcase_field_value}\" {\$showcase_field_checked} {\$showcase_field_enabled}>{\$showcase_field_text}";
$myshowcase_templates['field_textarea'] = "<textarea cols=\"{\$showcase_field_width}\" rows=\"{\$showcase_field_rows}\" wrap=\"virtual\" name=\"{\$showcase_field_name}\" {\$showcase_field_enabled}>{\$showcase_field_value}</textarea>";
$myshowcase_templates['field_textbox'] = "<input type=\"textbox\" width=\"{\$showcase_field_width}\" name=\"{\$showcase_field_name}\" value=\"{\$showcase_field_value}\" {\$showcase_field_enabled} {\$showcase_field_options}>";
$myshowcase_templates['inlinemod'] = "<script type=\"text/javascript\" src=\"{\$mybb->settings['bburl']}/jscripts/myshowcase_inline.js?ver=2502\"></script>\r\n<form action=\"{\$me->mainfile}\" method=\"post\">\r\n<input type=\"hidden\" name=\"my_post_key\" value=\"{\$mybb->post_code}\" />\r\n<input type=\"hidden\" name=\"modtype\" value=\"inlineshowcase\" />\r\n<input type=\"hidden\" name=\"sortby\" value=\"{\$mybb->input['sortby']}\" />\r\n<input type=\"hidden\" name=\"order\" value=\"{\$mybb->input['order']}\" />\r\n<input type=\"hidden\" name=\"page\" value=\"{\$mybb->input['page']}\" />\r\n<input type=\"hidden\" name=\"search\" value=\"{\$mybb->input['search']}\" />\r\n<input type=\"hidden\" name=\"searchterm\" value=\"{\$mybb->input['searchterm']}\" />\r\n<input type=\"hidden\" name=\"exact\" value=\"{\$mybb->input['exactmatch']}\" />\r\n<span class=\"smalltext\"><strong>{\$lang->myshowcase_inline_moderation}</strong></span>\r\n<select name=\"action\">\r\n	<optgroup label=\"{\$lang->standard_mod_tools}\">\r\n		<option value=\"multiapprove\">{\$lang->myshowcase_mod_approve}</option>\r\n		<option value=\"multiunapprove\">{\$lang->myshowcase_mod_unapprove}</option>\r\n		<option value=\"multidelete\">{\$lang->myshowcase_mod_delete}</option>\r\n	</optgroup>\r\n	{\$customthreadtools}\r\n</select>\r\n<input type=\"submit\" class=\"button\" name=\"go\" value=\"{\$lang->myshowcase_inline_go} ({\$inlinecount})\" id=\"inline_go\" />&nbsp;\r\n<input type=\"button\" onclick=\"javascript:inlineModeration.clearChecked();\" value=\"{\$lang->myshowcase_clear}\" class=\"button\" />\r\n</form>\r\n<script type=\"text/javascript\">\r\n<!--\r\n	var go_text = \"{\$lang->myshowcase_inline_go}\";\r\n	var inlineType = \"showcase\";\r\n	var inlineId = \"all\";\r\n// -->\r\n</script>\r\n<br />";
$myshowcase_templates['inlinemod_col'] = "<td class=\"tcat\" align=\"center\" width=\"1\"><input type=\"checkbox\" name=\"allbox\" onclick=\"inlineModeration.checkAll(this)\" /></td>";
$myshowcase_templates['inlinemod_item'] = "<td class=\"{\$trow_style}\" align=\"center\" style=\"white-space: nowrap\"><input type=\"checkbox\" class=\"checkbox\" name=\"inlinemod_{\$multigid}\" id=\"inlinemod_{\$multigid}\" value=\"1\" {\$inlinecheck}  /></td>";
$myshowcase_templates['inline_deleteshowcases'] = "<html>\r\n<head>\r\n<title>{\$mybb->settings['bbname']} - {\$lang->delete_myshowcases}</title>\r\n{\$headerinclude}\r\n</head>\r\n<body>\r\n{\$header}\r\n<form action=\"{\$me->mainfile}\" method=\"post\">\r\n<input type=\"hidden\" name=\"my_post_key\" value=\"{\$mybb->post_code}\" />\r\n<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">\r\n<tr>\r\n<td class=\"thead\" colspan=\"2\"><strong>{\$lang->delete_myshowcases}</strong></td>\r\n</tr>\r\n<tr>\r\n<td class=\"trow1\" colspan=\"2\" align=\"center\">{\$lang->confirm_delete_myshowcases}\r\n{\$loginbox}\r\n</table>\r\n<br />\r\n<div align=\"center\"><input type=\"submit\" class=\"button\" name=\"submit\" value=\"{\$lang->myshowcase_delete_multi}\" /></div>\r\n<input type=\"hidden\" name=\"action\" value=\"do_multidelete\" />\r\n<input type=\"hidden\" name=\"showcases\" value=\"{\$inlineids}\" />\r\n<input type=\"hidden\" name=\"url\" value=\"{\$return_url}\" />\r\n</form>\r\n{\$footer}\r\n</body>\r\n</html>";
$myshowcase_templates['js_header'] = "<script type=\"text/javascript\" src=\"http://ajax.googleapis.com/ajax/libs/jquery/1.4/jquery.min.js\"></script>\r\n<script>\r\n!window.jQuery && document.write('<script src=\"jquery-1.4.3.min.js\"><\/script>');\r\n</script>\r\n<script type=\"text/javascript\" src=\"{\$mybb->settings['bburl']}/jscripts/fancybox/jquery.mousewheel-3.0.4.pack.js\"></script>\r\n<script type=\"text/javascript\" src=\"{\$mybb->settings['bburl']}/jscripts/fancybox/jquery.fancybox-1.3.4.pack.js\"></script>\r\n<link rel=\"stylesheet\" type=\"text/css\" href=\"{\$mybb->settings['bburl']}/jscripts/fancybox/jquery.fancybox-1.3.4.css\" media=\"screen\" />\r\n<script type=\"text/javascript\">\r\n	jQuery.noConflict();\r\n	jQuery(document).ready(function(\$) {\r\n		\$(\"a[rel=showcase_images]\").fancybox({\r\n			'transitionIn'	: 'none',\r\n			'transitionOut'	: 'none',\r\n			'titlePosition' 	: 'over',\r\n			'titleFormat'	: function(title, currentArray, currentIndex, currentOpts) {\r\n				return '<span id=\"fancybox-title-over\">Image ' + (currentIndex + 1) + ' / ' + currentArray.length + (title.length ? ' &nbsp; ' + title : '') + '</span>';\r\n			}\r\n		});\r\n	});\r\n</script>";
$myshowcase_templates['list'] = "{\$showcase_top}\r\n{\$unapproved}\r\n<div class=\"float_right\" style=\"padding-bottom: 4px;\">\r\n<a href=\"{\$showcase_url_new}\"><img src=\"{\$theme['imglangdir']}/newshowcase.png\" alt=\"{\$lang->myshowcase_new}\" /></a>\r\n</div><br /><br />\r\n<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">\r\n<tr>\r\n<td class=\"thead\"><strong>{\$lang->myshowcase}</strong></td>\r\n</tr>\r\n</table>\r\n<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">\r\n<tr>\r\n<td class=\"tcat\" align=\"center\" width=\"75\"><span class=\"smalltext\"><strong>{\$lang->myshowcase_view}</strong></span></td>\r\n<td class=\"tcat\" align=\"center\" width=\"150\"><span class=\"smalltext\"><strong>{\$lang->myshowcase_member} {\$orderarrow['username']}</strong></span></td>\r\n{\$showcase_list_custom_header}\r\n<td class=\"tcat\" align=\"center\" width=\"50\"><span class=\"smalltext\"><strong>{\$lang->myshowcase_views}  {\$orderarrow['views']}</strong></span></td>\r\n<td class=\"tcat\" align=\"center\" width=\"50\"><span class=\"smalltext\"><strong>{\$lang->myshowcase_comments}  {\$orderarrow['comments']}</strong></span></td>\r\n<td class=\"tcat\" align=\"center\" width=\"150\"><span class=\"smalltext\"><strong>{\$lang->myshowcase_lastedit} {\$orderarrow['dateline']}</strong></span></td>\r\n{\$showcase_inlinemod_col}\r\n</tr>\r\n{\$showcase_list_items}\r\n</table>\r\n<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">\r\n<tr>\r\n<td class=\"thead\"><span class=\"tfoot\"><div style=\"text-align: center;\"><form action=\"{\$form_page}\" method=\"post\">\r\n<input type=\"hidden\" name=\"page\" value=\"{\$page}\" />\r\n{\$lang->myshowcase_sort_by} <select name=\"sortby\">\r\n{\$showcase_orderby}\r\n</select> \r\n{\$lang->myshowcase_sort_in} <select name=\"order\">\r\n<option value=\"ASC\" {\$orderascsel}>{\$lang->myshowcase_sort_asc}</option>\r\n<option value=\"DESC\" {\$orderdescsel}>{\$lang->myshowcase_sort_desc}</option>\r\n</select> \r\n{\$lang->myshowcase_search} <select name=\"search\">\r\n{\$showcase_search}\r\n</select> {\$lang->myshowcase_for} <input type=\"text\" class=\"textbox\" name=\"searchterm\" value=\"{\$mybb->input['searchterm']}\" size=\"20\" /> {\$lang->myshowcase_exact_match} <input type=\"checkbox\" name=\"exactmatch\" checked > {\$gobutton}\r\n</form></div></span>\r\n</td>\r\n</tr>\r\n</table>\r\n<table align=\"center\" width=\"100%\"><tr>\r\n<td align=\"right\">{\$multipage}</td>\r\n</tr></table>\r\n<div align=\"right\"><br>{\$showcase_inlinemod}</div>\r\n{\$footer}\r\n</body>\r\n</html>";
$myshowcase_templates['list_custom_fields'] = "<td class=\"{\$trow_style}\" align=\"center\"><span class=\"smalltext\">{\$item_text}</span></td>";
$myshowcase_templates['list_custom_header'] = "<td class=\"tcat\" align=\"center\" ><span class=\"smalltext\"><strong>{\$custom_header} {\$custom_orderarrow}</strong></span></td>";
$myshowcase_templates['list_empty'] = "<tr>\r\n<td class=\"trow1\" align=\"center\" colspan={\$showcase_num_headers}><span class=\"smalltext\"><p>{\$lang->myshowcase_empty}<p></span></td>\r\n<tr>";
$myshowcase_templates['list_items'] = "<tr>\r\n<td class=\"{\$trow_style}\" align=\"center\" width=\"75\"><span class=\"smalltext\"><a href=\"{\$item_viewcode}\">{\$item_viewimage}</a></span></td>\r\n<td class=\"{\$trow_style}\" align=\"center\" width=\"150\"><span class=\"smalltext\">{\$item_member}</span></td>\r\n{\$showcase_list_custom_fields}\r\n<td class=\"{\$trow_style}\" align=\"center\" width=\"50\"><span class=\"smalltext\">{\$item_numview}</span></td>\r\n<td class=\"{\$trow_style}\" align=\"center\" width=\"50\"><span class=\"smalltext\">{\$item_numcomment}</span></td>\r\n<td class=\"{\$trow_style}\" align=\"center\" width=\"150\"><span class=\"smalltext\">{\$item_lastedit}{\$item_admin}</span></td>\r\n{\$showcase_inlinemod_item}\r\n</tr>";
$myshowcase_templates['list_message'] = "<tr>\r\n<td class=\"{\$trow_style}\" align=\"center\" colspan={\$showcase_num_headers}><span class=\"smalltext\"><p>{\$message}<p></span></td>\r\n<tr>";
$myshowcase_templates['list_no_results'] = "<tr>\r\n<td class=\"trow1\" align=\"center\" colspan={\$showcase_num_headers}><span class=\"smalltext\"><p>{\$lang->myshowcase_no_results}<p></span></td>\r\n<tr>";

$myshowcase_templates['new_attachments_attachment'] = "<tr class=\"trow1\">\r\n<td width=\"5%\" align=\"center\">{\$attachment['icon']}</td>\r\n<td style=\"white-space: nowrap;\"><div style=\"float: right;\"><input type=\"submit\" class=\"button\" name=\"rem\" value=\"{\$lang->remove_attachment}\" onclick=\"return Showcase.removeAttachment({\$attachment['aid']});\" /></div>{\$attachment['filename']} ({\$attachment['size']})</td>\r\n</tr>";

$myshowcase_templates['new_attachments'] = "<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">\r\n<tr>\r\n<td class=\"thead\" colspan=\"2\"><strong>{\$lang->myshowcase_attachments}</strong><br><small>{\$jumptop}</small></td>\r\n</tr>\r\n<tr class=\"trow1\">\r\n<td colspan=\"2\">\r\n<span class=\"smalltext\">{\$lang->myshowcase_attach_quota}</span>\r\n</td>\r\n</tr>\r\n{\$showcase_new_attachments_input}\r\n{\$attachments}\r\n</table>";

$myshowcase_templates['new_attachments_input'] = "<tr class=\"trow1\">\r\n<td colspan=\"3\"><div style=\"float:right;\"><input type=\"submit\" class=\"button\" name=\"updateattachment\" value=\"{\$lang->update_attachment}\" tabindex=\"12\" /> <input type=\"submit\" class=\"button\" name=\"newattachment\" value=\"{\$lang->add_attachment}\"  tabindex=\"13\" /></div>\r\n<strong>{\$lang->new_attachment}</strong> <input type=\"file\" name=\"attachment\" size=\"30\" /> {\$showcase_watermark}\r\n</td>\r\n</tr>";

$myshowcase_templates['new_bottom'] = "</table>\r\n{\$showcase_attachments}\r\n<br />\r\n<div align=\"center\"><input type=\"submit\" class=\"button\" name=\"submit\" value=\"{\$lang->myshowcase_save}\" tabindex=\"3\" accesskey=\"s\" />  <input type=\"submit\" class=\"button\" name=\"cancel\" value=\"{\$lang->myshowcase_cancel}\" tabindex=\"4\" onclick=\"window.location = \"{\$mybb->settings['myshowcase_file']}?action=view&gid={\$mybb->input['gid']}\" \"/></div>\r\n<input type=\"hidden\" name=\"action\" value=\"{\$showcase_action}\" />\r\n<input type=\"hidden\" name=\"posthash\" value=\"{\$posthash}\" />\r\n<input type=\"hidden\" name=\"attachmentaid\" value=\"\" />
<input type=\"hidden\" name=\"attachmentact\" value=\"\" />\r\n<input type=\"hidden\" name=\"gid\" value=\"{\$mybb->input['gid']}\" />\r\n<input type=\"hidden\" name=\"authid\" value=\"{\$showcase_authid}\" />\r\n</form>\r\n{\$footer}\r\n</body>\r\n</html>";

$myshowcase_templates['new_fields'] = "<tr><td class=\"{\$trow_style}\" align=\"right\" width=\"15%\">{\$field_header}:</td><td class=\"{\$trow_style}\" align=\"left\" width=\"*\">{\$showcase_field_input}</td></tr>";
$myshowcase_templates['new_top'] = "{\$showcase_top}\r\n{\$attacherror}\r\n<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">\r\n<tr>\r\n<td class=\"thead\"><strong>{\$lang->myshowcase_specifications}</strong></td>\r\n</tr>\r\n</table>\r\n<form action=\"{\$form_page}\" method=\"post\" enctype=\"multipart/form-data\" name=\"input\">\r\n<input type=\"hidden\" name=\"my_post_key\" value=\"{\$mybb->post_code}\" />\r\n<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">";
$myshowcase_templates['orderarrow'] = "<span class=\"smalltext\">[<a href=\"{\$sorturl}sortby={\$mybb->input['sortby']}&amp;order={\$oppsortnext}&amp;search={\$mybb->input['search']}&amp;searchterm={\$mybb->input['searchterm']}&amp;exactmatch={\$mybb->input['exactmatch']}\">{\$oppsort}</a>]</span>";

$myshowcase_templates['report'] = "<html>\r\n<head>\r\n<title>{\$mybb->settings['bbname']}</title>\r\n{\$headerinclude}\r\n</head>\r\n<body>\r\n <table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">\r\n<tr>\r\n<td class=\"trow1\" align=\"center\">\r\n<strong>{\$lang->myshowcase_report_label}</strong>\r\n<form action=\"{\$report_url}\" method=\"post\">\r\n<input type=\"hidden\" name=\"my_post_key\" value=\"{\$mybb->post_code}\" />\r\n<input type=\"hidden\" name=\"action\" value=\"do_report\" />\r\n<input type=\"hidden\" name=\"gid\" value=\"{\$mybb->input['gid']}\" />\r\n<blockquote>{\$lang->myshowcase_report_warning}</blockquote>\r\n<br />\r\n<br />\r\n<span class=\"smalltext\">{\$lang->myshowcase_report_reason}</span>\r\n<br />\r\n<input type=\"text\" class=\"textbox\" name=\"reason\" size=\"40\" maxlength=\"250\" />\r\n<br />\r\n<br />\r\n<div align=\"center\"><input type=\"submit\" class=\"button\" value=\"{\$lang->myshowcase_report}\" /></div>\r\n</form>\r\n</body>\r\n</html>";


$myshowcase_templates['reported'] = "<div class=\"red_alert\">\r\n{\$reported_notice}\r\n</div>\r\n<br />";
$myshowcase_templates['reports'] = "{\$showcase_top}\r\n<form action=\"{\$showcase_file}\" method=\"post\">\r\n<input type=\"hidden\" name=\"my_post_key\" value=\"{\$mybb->post_code}\" />\r\n<input type=\"hidden\" name=\"page\" value=\"{\$page}\" />\r\n<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">\r\n<tr>\r\n<td class=\"thead\" align=\"center\" colspan=\"6\"><strong>{\$lang->myshowcase_reports_note}</strong></td>\r\n</tr>\r\n<tr>\r\n<td class=\"tcat\" align=\"center\" width=\"10%\"><span class=\"smalltext\"><strong>{\$lang->myshowcase_showcase}</strong></span></td>\r\n<td class=\"tcat\" align=\"center\" width=\"15%\"><span class=\"smalltext\"><strong>{\$lang->myshowcase_member}</strong></span></td>\r\n<td class=\"tcat\" align=\"center\" width=\"15%\"><span class=\"smalltext\"><strong>{\$lang->reporter}</strong></span></td>\r\n<td class=\"tcat\" align=\"center\" width=\"35%\"><span class=\"smalltext\"><strong>{\$lang->report_reason}</strong></span></td>\r\n<td class=\"tcat\" align=\"center\" width=\"10%\"><span class=\"smalltext\"><strong>{\$lang->report_time}</strong></span></td>\r\n</tr>\r\n{\$reports}\r\n{\$reportspages}\r\n<tr>\r\n<td class=\"tfoot\" colspan=\"6\" align=\"right\"><span class=\"smalltext\"><strong><a href=\"{\$showcase_file}?action=allreports\">{\$lang->view_all_reported_posts}</a></strong></span></td>\r\n</tr>\r\n</table>\r\n<br />\r\n<div align=\"center\"><input type=\"hidden\" name=\"action\" value=\"do_reports\" /><input type=\"submit\" class=\"button\" name=\"reportsubmit\" value=\"{\$lang->mark_read}\" /></div>\r\n</form>\r\n{\$footer}\r\n</body>\r\n</html>";
$myshowcase_templates['reports_allreport'] = "<tr>\r\n<td class=\"{\$trow}\" align=\"center\"><a href=\"{\$report['showcaselink']}\" target=\"_blank\">{\$report['gid']}</a></td>\r\n<td class=\"{\$trow}\" align=\"center\">{\$report['authorlink']}</td>\r\n<td class=\"{\$trow}\" align=\"center\">{\$report['reporterlink']}</td>\r\n<td class=\"{\$trow}\">{\$report['reason']}</td>\r\n<td class=\"{\$trow}\" align=\"center\" style=\"white-space: nowrap\"><span class=\"smalltext\">{\$reportdate}<br />{\$reporttime}</small></td>\r\n</tr>";
$myshowcase_templates['reports_multipage'] = "<tr>\r\n<td class=\"tcat\" colspan=\"5\"><span class=\"smalltext\"> {\$multipage}</span></td>\r\n</tr>";
$myshowcase_templates['reports_report'] = "<tr>\r\n<td class=\"{\$trow}\" align=\"center\"><label for=\"reports_{\$report['rid']}\"><input type=\"checkbox\" class=\"checkbox\" name=\"reports[]\" id=\"reports_{\$report['rid']}\" value=\"{\$report['rid']}\" />&nbsp;<a href=\"{\$report['showcaselink']}\" target=\"_blank\">{\$report['gid']}</a></label>\r\n</td>\r\n<td class=\"{\$trow}\" align=\"center\">{\$report['authorlink']}</td>\r\n<td class=\"{\$trow}\" align=\"center\">{\$report['reporterlink']}</td>\r\n<td class=\"{\$trow}\">{\$report['reason']}</td>\r\n<td class=\"{\$trow}\" align=\"center\" style=\"white-space: nowrap\"><span class=\"smalltext\">{\$reportdate}<br />{\$reporttime}</small></td>\r\n</tr>";
$myshowcase_templates['table_header'] = "<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">\r\n<tr>\r\n<td class=\"thead\"><strong>{\$showcase_header_label}</strong> {\$showcase_header_special}<br /><small>{\$showcase_header_jumpto}</small></td>\r\n</tr>\r\n</table>";

$myshowcase_templates['top'] = "<html>\r\n<head>\r\n<title>{\$mybb->settings['bbname']} - {\$me->description}</title>\r\n{\$headerinclude}\r\n{\$myshowcase_js_header}\r\n<script type=\"text/javascript\" src=\"{\$mybb->settings['bburl']}/jscripts/myshowcase.js?ver=2502\"></script>\r\n<script type=\"text/javascript\">\r\n<!--\r\n	var removeshowcase_confirm = \"{\$lang->removeshowcase_confirm}\";\r\n	var removeshowcasecomment_confirm = \"{\$lang->removeshowcasecomment_confirm}\";\r\n	var removeshowcaseattach_confirm = \"{\$lang->removeshowcaseattach_confirm}\";\r\n	var showcase_url = \"{\$showcase_url}\";\r\n// -->\r\n</script>\r\n</head>\r\n<body>\r\n{\$header}";

$myshowcase_templates['unapproved'] = "<div class=\"pm_alert\">\r\n	{\$unapproved_notice}\r\n</div>";
$myshowcase_templates['user_link'] = "<a href=\"{\$showcase_file}?action=list&amp;search=username&amp;searchterm={\$post['username']}&amp;exactuser=1\">{\$showcase_user_link_text} {\$user_num_myshowcases}</a><br />";
$myshowcase_templates['view'] = "{\$showcase_top}\r\n{\$showcase_data_header}\r\n<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">\r\n{\$showcase_data}\r\n</table>\r\n{\$showcase_attachment_header}\r\n<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">\r\n{\$showcase_attachments}\r\n</table>\r\n{\$showcase_comment_header}\r\n<form action=\"{\$showcase_comment_form_url}\" method=\"post\" name=\"comment\">\r\n<input type=\"hidden\" name=\"my_post_key\" value=\"{\$mybb->post_code}\" />\r\n<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">\r\n{\$showcase_comments}\r\n</table>\r\n</form>\r\n{\$footer}\r\n</body>\r\n</html>";
$myshowcase_templates['view_admin'] = "<span style=\"float:right\">\r\n<form name=\"admin\" method=\"post\" action=\"{\$showcase_admin_url}\" />\r\n<input type=\"hidden\" name=\"my_post_key\" value=\"{\$mybb->post_code}\" />\r\n{\$showcase_view_admin_edit}\r\n{\$showcase_view_admin_delete}\r\n<input type=\"hidden\" name=\"posthash\" value=\"{\$posthash}\" />\r\n<input type=\"hidden\" name=\"gid\" value=\"{\$mybb->input['gid']}\" />\r\n<input type=\"hidden\" name=\"showcasegid\" value=\"\" />\r\n<input type=\"hidden\" name=\"showcaseact\" value=\"\" />\r\n</form>\r\n</span>";
$myshowcase_templates['view_admin_delete'] = "<input type=\"submit\" class=\"button\" name=\"delete\" value=\"{\$lang->myshowcase_delete}\" onClick=\"return Showcase.removeShowcase({\$mybb->input['gid']});\"/>";
$myshowcase_templates['view_admin_edit'] = "<input type=\"submit\" class=\"button\" name=\"edit\" value=\"{\$lang->myshowcase_edit}\" onClick=\"return Showcase.editShowcase({\$mybb->input['gid']});\"/>";
$myshowcase_templates['view_attachments'] = "<tr>\r\n<td class=\"trow1\" align=\"center\" width=\"100%\" colspan=\"2\" style=\"word-wrap: break-word;\">{\$showcase_attachment_data}</td>\r\n</tr>";
$myshowcase_templates['view_attachments_none  '] = "<tr><td class=\"trow1\" align=\"center\" width=\"100%\" colspan=2><p>{\$lang->myshowcase_attachments_none}<p></td></tr>";

$myshowcase_templates['view_attachments_image'] = "<SCRIPT LANGUAGE=\"javascript\">document.write(\"<a {\$item_class} href=\\\"{\$item_attachurljs}\\\"><img src=\\\"{\$item_image}\\\" border=\\\"0\\\" alt=\\\"{\$item_alt}\\\" /></a>\");</SCRIPT> 
 <NOSCRIPT><a href=\"{\$item_attachurl}\"><img src=\"{\$item_image}\" border=\"0\" alt=\"{\$item_alt}\" /></a></NOSCRIPT>";

$myshowcase_templates['view_comments'] = "<tr>\r\n<td class=\"{\$trow_style}\" align=\"right\" width=\"15%\" valign=\"top\">\r\n{\$lang->myshowcase_from} {\$comment_poster}<br />{\$comment_posted} \r\n</td>\r\n<td class=\"{\$trow_style}\" align=\"left\" width=\"*\">\r\n{\$comment_data}{\$showcase_comments_admin}\r\n</td>\r\n</tr>";
$myshowcase_templates['view_comments_add'] = "<tr><td class=\"{\$trow_style}\" align=\"center\" colspan=2><p />\r\n<textarea cols=\"75\" rows=\"5\" name=\"comments\"></textarea> <br>{\$comment_text_limit}<br>\r\n<input type=\"submit\" name=\"addcomment\" value=\"Add Comment\" />\r\n<input type=\"hidden\" name=\"action\" value=\"comments\" />\r\n<input type=\"hidden\" name=\"gid\" value=\"{\$mybb->input['gid']}\" />\r\n<input type=\"hidden\" name=\"posthash\" value=\"{\$showcase['posthash']}\" />\r\n<input type=\"hidden\" name=\"commentcid\" value=\"\" />\r\n<input type=\"hidden\" name=\"commentact\" value=\"\" />\r\n</form><p />\r\n</td></tr>";
$myshowcase_templates['view_comments_add_login'] = "<tr><td class=\"{\$trow_style}\" align=\"center\" colspan=\"2\"><p />{\$lang->myshowcase_comments_not_logged_in}<p /></td></tr>";
$myshowcase_templates['view_comments_admin'] = "<div style=\"text-align:right;\"><input type=\"submit\" class=\"button\" name=\"remcomment\" value=\"{\$lang->myshowcase_comment_delete}\" onclick=\"return Showcase.removeComment({\$gcomments['cid']});\" /></div>";
$myshowcase_templates['view_comments_none'] = "<tr><td class=\"{\$trow_style}\" align=\"center\" width=\"100%\" colspan=\"2\"><p />{\$lang->myshowcase_comments_none}<p /></td></tr>";
$myshowcase_templates['view_data_1'] = "<tr><td class=\"{\$trow_style}\" align=\"right\" width=\"15%\">{\$field_header}:</td><td class=\"{\$trow_style}\" align=\"left\" width=\"*\">{\$field_data}</td></tr>";
$myshowcase_templates['view_data_2'] = "<tr><td class=\"{\$trow_style}\" align=\"right\" width=\"15%\" valign=\"top\">{\$field_header}:</td><td class=\"{\$trow_style}\" align=\"left\" width=\"*\">{\$field_data}</td></tr>";
$myshowcase_templates['view_data_3'] = "<tr><td class=\"{\$trow_style}\" align=\"right\" colspan=\"2\">{\$entry_final_row}</td></tr>";
$myshowcase_templates['portal_rand_showcase'] = "<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">\r\n<tr>\r\n<td class=\"thead\"><strong>Random {\$rand_showcase['name']}</strong></td>\r\n</tr>\r\n<tr>\r\n<td class=\"trow1\">\r\n<strong><a href=\"{\$item_viewcode}\">{\$entry['description']}</a><br /></strong>\r\n<span class=\"smalltext\">\r\n<strong>&raquo; </strong>{\$item_member}<br />\r\n<strong>&raquo; </strong>Views: {\$entry['views']}<br />\r\n<strong>&raquo; </strong>Comments: {\$entry['comments']}<br />\r\n</span>\r\n<div style=\"float:right\"><a href=\"{\$item_viewcode}\"><img src=\"{\$rand_img}\" border=\"0\"></a></div>\r\n</td>\r\n</tr>\r\n</table>";
$myshowcase_templates['portal_basic_box'] = "<table border=\"0\" cellspacing=\"{\$theme['borderwidth']}\" cellpadding=\"{\$theme['tablespace']}\" class=\"tborder\">\r\n<tr>\r\n<td class=\"thead\"><strong>{\$portal_box_title}</strong></td>\r\n</tr>\r\n<tr>\r\n<td class=\"trow1\">\r\n{\$portal_box_content}\r\n</td>\r\n</tr>\r\n</table>";

$myshowcase_templates['watermark'] = "<input type=\"checkbox\" name=\"watermark\" value=\"1\"><span class=\"smalltext\"> {\$lang->myshowcase_watermark}</span>";
