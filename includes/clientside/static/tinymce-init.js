// init TinyMCE
var head = document.getElementsByTagName('head')[0];

if ( document.getElementById('mdgCss') )
{
  var css_url = document.getElementById('mdgCss').href;
}
else
{
  var css_url = scriptPath + '/includes/clientside/css/enano_shared.css';
}

var do_popups = ( is_Safari ) ? '' : ',inlinepopups';
var _skin = ( typeof(tinymce_skin) == 'string' ) ? tinymce_skin : 'default';
var tinymce_initted = false;

var enano_tinymce_options = {
  mode : "none",
  plugins : 'table,save,safari,pagebreak,style,layer,advhr,insertdatetime,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras' + do_popups,
  theme : 'advanced',
  skin : _skin,
  theme_advanced_resize_horizontal : false,
  theme_advanced_resizing : true,
  theme_advanced_toolbar_location : "top",
  theme_advanced_toolbar_align : "left",
  theme_advanced_buttons1 : "save,|,bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull,|,forecolor,backcolor,|,formatselect,|,fontselect,fontsizeselect",
  theme_advanced_buttons3_add_before : "tablecontrols,separator",
  theme_advanced_buttons3_add_after : "|,fullscreen",
  theme_advanced_statusbar_location : 'bottom',
  noneditable_noneditable_class : 'mce_readonly',
  content_css : css_url
};

var enano_tinymce_gz_options = {
	plugins : 'table,save,safari,pagebreak,style,layer,advhr,insertdatetime,searchreplace,print,contextmenu,paste,directionality,fullscreen,noneditable,visualchars,nonbreaking,xhtmlxtras' + do_popups,
	themes : 'advanced',
	languages : 'en',
	disk_cache : true,
	debug : false
};

if ( !KILL_SWITCH && !DISABLE_MCE )
{
  // load MCE with XHR
  var ajax = ajaxMakeXHR();
  var uri = scriptPath + '/includes/clientside/tinymce/tiny_mce_gzip.js';
  ajax.open('GET', uri, false);
  ajax.send(null);
  if ( ajax.readyState == 4 && ajax.status == 200 )
  {
    eval_global(ajax.responseText);
    tinyMCE_GZ.init(enano_tinymce_gz_options);
  }
  else
  {
    console.error('TinyMCE load failed');
  }
}

// Check tinyMCE to make sure its init is finished
window.tinymce_preinit_check = function()
{
  if ( typeof(tinyMCE.init) != 'function' )
    return false;
  if ( typeof(tinymce.DOM) != 'object' )
    return false;
  if ( typeof(tinymce.DOM.get) != 'function' )
    return false;
  if ( typeof(enano_tinymce_gz_options) != 'object' )
    return false;
  return true;
}

var initTinyMCE = function(e)
{
  if ( typeof(tinyMCE) == 'object' )
  {
    if ( !KILL_SWITCH && !DISABLE_MCE )
    {
      if ( !tinymce_preinit_check() )
      {
        setTimeout('initTinyMCE(false);', 200);
        return false;
      }
      tinyMCE.init(enano_tinymce_options);
      tinymce_initted = true;
    }
  }
};