<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
	<head>
		<title>{PAGE_NAME} &bull; {SITE_NAME}</title>
		<meta http-equiv="Content-type" content="text/html; charset=utf-8" />
		{JS_DYNAMIC_VARS}
		<link rel="stylesheet" type="text/css" href="{CDNPATH}/includes/clientside/css/enano-shared.css?{ENANO_VERSION}" />
		<link rel="stylesheet" type="text/css" href="{CDNPATH}/themes/{THEME_ID}/css-extra/structure.css?{ENANO_VERSION}" />
		<link id="mdgCss" rel="stylesheet" type="text/css" href="{CDNPATH}/themes/{THEME_ID}/css/{STYLE_ID}.css?{ENANO_VERSION}" />
		{JS_HEADER}
		<!--[if lt IE 7]>
		<link rel="stylesheet" type="text/css" href="{CDNPATH}/themes/{THEME_ID}/css-extra/ie-fixes-{STYLE_ID}.css" />
		<![endif]-->
		<script type="text/javascript">
		// <![CDATA[
		
			// Disable transition effects for the ACL editor
			// (they're real slow in this theme, at least in fx/opera/IE)
			var aclDisableTransitionFX = true;
		
			function ajaxRenameInline()
			{
				// This trick is _so_ vBulletin...
				elem = document.getElementById('pagetitle');
				if(!elem) return;
				elem.style.display = 'none';
				name = elem.firstChild.nodeValue;
				textbox = document.createElement('input');
				textbox.type = 'text';
				textbox.value = name;
				textbox.id = 'pageheading';
				textbox.size = name.length + 7;
				textbox.onkeyup = function(e) { if(!e) return; if(e.keyCode == 13) ajaxRenameInlineSave(); if(e.keyCode == 27) ajaxRenameInlineCancel(); };
				elem.parentNode.insertBefore(textbox, elem);
				document.onclick = ajaxRenameInlineCancel;
			}
			function ajaxRenameInlineSave()
			{
				elem1 = document.getElementById('pagetitle');
				elem2 = document.getElementById('pageheading');
				if(!elem1 || !elem2) return;
				value = elem2.value;
				elem2.parentNode.removeChild(elem2); // just destroy the thing
				elem1.removeChild(elem1.firstChild);
				elem1.appendChild(document.createTextNode(value));
				elem1.style.display = 'block';
				if(!value || value=='') return;
				ajaxPost(stdAjaxPrefix+'&_mode=rename', 'newtitle='+ajaxEscape(value), function() {
					if(ajax.readyState == 4) {
						alert(ajax.responseText);
					}
				});
			}
			function ajaxRenameInlineCancel(e)
			{
				if ( !e )
					e = window.event;
				elem1 = document.getElementById('pagetitle');
				elem2 = document.getElementById('pageheading');
				if(!elem1 || !elem2) return;
				if ( e && e.target )
				{
					if(e.target == elem2)
						return;
				}
				//value = elem2.value;
				elem2.parentNode.removeChild(elem2); // just destroy the thing
				//elem1.innerHTML = value;
				elem1.style.display = 'block';
				document.onclick = null;
			}
			// ]]>
		</script>
		{ADDITIONAL_HEADERS}
	</head>
	<body>
		<div id="bg">
			<div id="rap">
				<div id="title">
					<h1>{SITE_NAME}</h1>
					<h2>{SITE_DESC}</h2>
				</div>
				<div class="menu_nojs" id="pagebar_main">
					<div class="label">{lang:onpage_lbl_pagetools}</div>
					{TOOLBAR}
					<ul>
						{TOOLBAR_EXTRAS}
					</ul>
					<span class="menuclear">&nbsp;</span>
				</div>
				<div id="sidebar">
					<!-- BEGIN sidebar_left -->
					{SIDEBAR_LEFT}
					<!-- END sidebar_left -->
					<!-- BEGIN sidebar_right -->
					<!-- BEGINNOT in_admin -->
					{SIDEBAR_RIGHT}
					<!-- END in_admin -->
					<!-- END sidebar_right -->
				</div>
				<div id="maincontent">
					<div style="float: right;">
						<img alt=" " src="{CDNPATH}/images/spacer.gif" id="ajaxloadicon" />
					</div>
					<h2 id="pagetitle" <!-- BEGIN auth_rename --> ondblclick="ajaxRenameInline();" title="Double-click to rename this page" <!-- END auth_rename -->>{PAGE_NAME}</h2>
					<div id="ajaxEditContainer">
						
