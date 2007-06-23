<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0 release candidate 3 (Druid)
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
class template {
  var $tpl_strings, $tpl_bool, $theme, $style, $no_headers, $additional_headers, $sidebar_extra, $sidebar_widgets, $toolbar_menu, $theme_list, $named_theme_list, $default_theme, $default_style, $plugin_blocks, $namespace_string, $style_list, $theme_loaded;
  function __construct()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    dc_here('template: initializing all class variables');
    $this->tpl_bool    = Array();
    $this->tpl_strings = Array();
    $this->sidebar_extra = '';
    $this->toolbar_menu = '';
    $this->additional_headers = '';
    $this->plugin_blocks = Array();
    $this->theme_loaded = false;
    
    $this->theme_list = Array();
    $this->named_theme_list = Array();
    $e = $db->sql_query('SELECT theme_id,theme_name,enabled,default_style FROM '.table_prefix.'themes WHERE enabled=1 ORDER BY theme_order;');
    if(!$e) $db->_die('The list of themes could not be selected.');
    for($i=0;$i < $db->numrows(); $i++)
    {
      $this->theme_list[$i] = $db->fetchrow();
      $this->named_theme_list[$this->theme_list[$i]['theme_id']] = $this->theme_list[$i];
    }
    $db->free_result();
    $this->default_theme = $this->theme_list[0]['theme_id'];
    $dir = ENANO_ROOT.'/themes/'.$this->default_theme.'/css/';
    $list = Array();
    // Open a known directory, and proceed to read its contents
    if (is_dir($dir)) {
      if ($dh = opendir($dir)) {
        while (($file = readdir($dh)) !== false) {
          if(preg_match('#^(.*?)\.css$#i', $file) && $file != '_printable.css') {
            $list[] = substr($file, 0, strlen($file)-4);
          }
        }
        closedir($dh);
      }
    }
    
    $def = ENANO_ROOT.'/themes/'.$this->default_theme.'/css/'.$this->named_theme_list[$this->default_theme]['default_style'];
    if(file_exists($def))
    {
      $this->default_style = substr($this->named_theme_list[$this->default_theme]['default_style'], 0, strlen($this->named_theme_list[$this->default_theme]['default_style'])-4);
    } else {
      $this->default_style = $list[0];
    }
    
    $this->style_list = $list;
    
  }
  function template()
  {
    $this->__construct();
  }
  function sidebar_widget($t, $h)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!defined('ENANO_TEMPLATE_LOADED'))
    {
      $this->load_theme($session->theme, $session->style);
    }
    if(!$this->sidebar_widgets)
      $this->sidebar_widgets = '';
    $tplvars = $this->extract_vars('elements.tpl');
    $parser = $this->makeParserText($tplvars['sidebar_section_raw']);
    $parser->assign_vars(Array('TITLE'=>$t,'CONTENT'=>$h));
    $this->plugin_blocks[$t] = $h;
    $this->sidebar_widgets .= $parser->run();
  }
  function add_header($html)
  {
    $this->additional_headers .= "\n" . $html;
  }
  function get_css($s = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!defined('ENANO_TEMPLATE_LOADED'))
      $this->load_theme($session->theme, $session->style);
    $path = ( $s ) ? 'css/'.$s : 'css/'.$this->style.'.css';
    if ( !file_exists(ENANO_ROOT . '/themes/' . $this->theme . '/' . $path) )
    {
      echo "/* WARNING: Falling back to default file because file $path does not exist */\n";
      $path = 'css/' . $this->style_list[0] . '.css';
    }
    return $this->process_template($path);
  }
  function load_theme($name = false, $css = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $this->theme = ( $name ) ? $name : $session->theme;
    $this->style = ( $css ) ? $css : $session->style;
    if ( !$this->theme )
    {
      $this->theme = $this->theme_list[0]['theme_id'];
      $this->style = substr($this->theme_list[0]['default_style'], 0, strlen($this->theme_list[0]['default_style'])-4);
    }
    $this->theme_loaded = true;
  }
  
  function init_vars()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    global $email;
    
    dc_here("template: initializing all variables");
    
    if(!$this->theme || !$this->style)
    {
      $this->load_theme();
    }
    
    if(defined('ENANO_TEMPLATE_LOADED'))
    {
      dc_here('template: access denied to call template::init_vars(), bailing out');
      die_semicritical('Illegal call', '<p>$template->load_theme was called multiple times, this is not supposed to happen. Exiting with fatal error.</p>');
    }
    
    define('ENANO_TEMPLATE_LOADED', '');
    
    $tplvars = $this->extract_vars('elements.tpl');
    
    dc_here('template: setting all template vars');
    
    if(isset($_SERVER['HTTP_USER_AGENT']) && strstr($_SERVER['HTTP_USER_AGENT'], 'MSIE'))
    {
      $this->add_header('
        <!--[if lt IE 7]>
        <script language="JavaScript">
        function correctPNG() // correctly handle PNG transparency in Win IE 5.5 & 6.
        {
           var arVersion = navigator.appVersion.split("MSIE")
           var version = parseFloat(arVersion[1])
           if (version >= 5.5 && typeof(document.body.filters) == "object")
           {
              for(var i=0; i<document.images.length; i++)
              {
                 var img = document.images[i];
                 continue;
                 var imgName = img.src.toUpperCase();
                 if (imgName.substring(imgName.length-3, imgName.length) == "PNG")
                 {
                    var imgID = (img.id) ? "id=\'" + img.id + "\' " : "";
                    var imgClass = (img.className) ? "class=\'" + img.className + "\' " : "";
                    var imgTitle = (img.title) ? "title=\'" + img.title + "\' " : "title=\'" + img.alt + "\' ";
                    var imgStyle = "display:inline-block;" + img.style.cssText;
                    if (img.align == "left") imgStyle = "float:left;" + imgStyle;
                    if (img.align == "right") imgStyle = "float:right;" + imgStyle;
                    if (img.parentElement.href) imgStyle = "cursor:hand;" + imgStyle;
                    var strNewHTML = "<span " + imgID + imgClass + imgTitle + " style=\\"" + "width:" + img.width + "px; height:" + img.height + "px;" + imgStyle + ";" + "filter:progid:DXImageTransform.Microsoft.AlphaImageLoader" + "(src=\\\'" + img.src + "\\\', sizingMethod=\'scale\');\\"></span>";
                    img.outerHTML = strNewHTML;
                    i = i-1;
                 }
              }
           }   
        }
        window.attachEvent("onload", correctPNG);
        </script>
        <![endif]-->
        ');
    }
    
    // Get the "article" button text (depends on namespace)
    switch($paths->namespace) {
      case "Article":
      default:
        $ns = 'article';
        break;
      case "Admin":
        $ns = 'administration page';
        break;
      case "System":
        $ns = 'system message';
        break;
      case "File":
        $ns = 'uploaded file';
        break;
      case "Help":
        $ns = 'documentation page';
        break;
      case "User":
        $ns = 'user page';
        break;
      case "Special":
        $ns = 'special page';
        break;
      case "Template":
        $ns = 'template';
        break;
      case "Project":
        $ns = 'project page';
        break;
      case "Category":
        $ns = 'category';
        break;
    }
    $this->namespace_string = $ns;
    $code = $plugins->setHook('page_type_string_set');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    $ns =& $this->namespace_string;
    
    // Initialize the toolbar
    $tb = '';
    
    // Create "xx page" button
    
    $btn_selected = ( isset($tplvars['toolbar_button_selected'])) ? $tplvars['toolbar_button_selected'] : $tplvars['toolbar_button'];
    $parser = $this->makeParserText($btn_selected);
    
    $parser->assign_vars(array(
        'FLAGS' => 'onclick="void(ajaxReset()); return false;" title="View the page contents, all of the page contents, and nothing but the page contents (alt-a)" accesskey="a"',
        'PARENTFLAGS' => 'id="mdgToolbar_article"',
        'HREF' => makeUrl($paths->page, null, true),
        'TEXT' => $this->namespace_string
      ));
    
    $tb .= $parser->run();
    
    $button = $this->makeParserText($tplvars['toolbar_button']);
    
    // Page toolbar
    // Comments button
    if ( $session->get_permissions('read') && getConfig('enable_comments')=='1' && $paths->namespace != 'Special' && $paths->namespace != 'Admin' && $paths->cpage['comments_on'] == 1 )
    {
      
      $e = $db->sql_query('SELECT approved FROM '.table_prefix.'comments WHERE page_id=\''.$paths->cpage['urlname_nons'].'\' AND namespace=\''.$paths->namespace.'\';');
      if ( !$e )
      {
        $db->_die();
      }
      $nc = $db->numrows();
      $nu = 0;
      $na = 0;
      
      while ( $r = $db->fetchrow() )
      {  
        if ( !$r['approved'] )
        {
          $nu++;
        }
        else
        {
          $na++;
        }
      }
      
      $db->free_result();
      $n = ( $session->get_permissions('mod_comments') ) ? (string)$nc : (string)$na;
      if ( $session->get_permissions('mod_comments') && $nu > 0 )
      {
        $n .= ' total/'.$nu.' unapp.';
      }
      
      $button->assign_vars(array(
          'FLAGS' => 'onclick="void(ajaxComments()); return false;" title="View the comments that other users have posted about this page (alt-c)" accesskey="c"',
          'PARENTFLAGS' => 'id="mdgToolbar_discussion"',
          'HREF' => makeUrl($paths->page, 'do=comments', true),
          'TEXT' => 'discussion ('.$n.')',
        ));
      
      $tb .= $button->run();
    }
    // Edit button
    if($session->get_permissions('read') && ($paths->namespace != 'Special' && $paths->namespace != 'Admin') && ( $session->get_permissions('edit_page') && ( ( $paths->page_protected && $session->get_permissions('even_when_protected') ) || !$paths->page_protected ) ) )
    {
      $button->assign_vars(array(
        'FLAGS' => 'onclick="void(ajaxEditor()); return false;" title="Edit the contents of this page (alt-e)" accesskey="e"',
        'PARENTFLAGS' => 'id="mdgToolbar_edit"',
        'HREF' => makeUrl($paths->page, 'do=edit', true),
        'TEXT' => 'edit this page'
        ));
      $tb .= $button->run();
    // View source button
    }
    else if($session->get_permissions('view_source') && ( !$session->get_permissions('edit_page') || !$session->get_permissions('even_when_protected') && $paths->page_protected ) && $paths->namespace != 'Special' && $paths->namespace != 'Admin') 
    {
      $button->assign_vars(array(
        'FLAGS' => 'onclick="void(ajaxViewSource()); return false;" title="View the source code (wiki markup) that this page uses (alt-e)" accesskey="e"',
        'PARENTFLAGS' => 'id="mdgToolbar_edit"',
        'HREF' => makeUrl($paths->page, 'do=viewsource', true),
        'TEXT' => 'view source'
        ));
      $tb .= $button->run();
    }
    // History button
    if ( $session->get_permissions('read') /* && $paths->wiki_mode */ && $paths->page_exists && $paths->namespace != 'Special' && $paths->namespace != 'Admin' && $session->get_permissions('history_view') )
    {
      $button->assign_vars(array(
        'FLAGS'       => 'onclick="void(ajaxHistory()); return false;" title="View a log of actions taken on this page (alt-h)" accesskey="h"',
        'PARENTFLAGS' => 'id="mdgToolbar_history"',
        'HREF'        => makeUrl($paths->page, 'do=history', true),
        'TEXT'        => 'history'
        ));
      $tb .= $button->run();
    }
    
    $menubtn = $this->makeParserText($tplvars['toolbar_menu_button']);
    
    // Additional actions menu
    // Rename button
    if ( $session->get_permissions('read') && $paths->page_exists && ( $session->get_permissions('rename') && ( $paths->page_protected && $session->get_permissions('even_when_protected') || !$paths->page_protected ) ) && $paths->namespace != 'Special' && $paths->namespace != 'Admin' )
    {
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="void(ajaxRename()); return false;" title="Change the display name of this page (alt-r)" accesskey="r"',
          'HREF'  => makeUrl($paths->page, 'do=rename', true),
          'TEXT'  => 'rename',
        ));
      $this->toolbar_menu .= $menubtn->run();
    }
    
    // Vote-to-delete button
    if ( $paths->wiki_mode && $session->get_permissions('vote_delete') && $paths->page_exists && $paths->namespace != 'Special' && $paths->namespace != 'Admin')
    {
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="void(ajaxDelVote()); return false;" title="Vote to have this page deleted (alt-d)" accesskey="d"',
          'HREF'  => makeUrl($paths->page, 'do=delvote', true),
          'TEXT'  => 'vote to delete this page',
        ));
      $this->toolbar_menu .= $menubtn->run();
    }
    
    // Clear-votes button
    if ( $session->get_permissions('read') && $paths->wiki_mode && $paths->page_exists && $paths->namespace != 'Special' && $paths->namespace != 'Admin' && $session->get_permissions('vote_reset') && $paths->cpage['delvotes'] > 0)
    {
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="void(ajaxResetDelVotes()); return false;" title="Vote to have this page deleted (alt-y)" accesskey="y"',
          'HREF'  => makeUrl($paths->page, 'do=resetvotes', true),
          'TEXT'  => 'reset deletion votes',
        ));
      $this->toolbar_menu .= $menubtn->run();
    }
    
    // Printable page button
    if ( $paths->page_exists && $paths->namespace != 'Special' && $paths->namespace != 'Admin' )
    {
      $menubtn->assign_vars(array(
          'FLAGS' => 'title="View a version of this page that is suitable for printing"',
          'HREF'  => makeUrl($paths->page, 'printable=yes', true),
          'TEXT'  => 'view printable version',
        ));
      $this->toolbar_menu .= $menubtn->run();
    }
    
    // Protect button
    if($session->get_permissions('read') && $paths->wiki_mode && $paths->page_exists && $paths->namespace != 'Special' && $paths->namespace != 'Admin' && $session->get_permissions('protect'))
    {
      
      $label = $this->makeParserText($tplvars['toolbar_label']);
      $label->assign_vars(array('TEXT' => 'protection:'));
      $t0 = $label->run();
      
      $ctmp = ''; 
      if ( $paths->cpage['protected'] == 1 )
      {
        $ctmp=' style="text-decoration: underline;"';
      }
      $menubtn->assign_vars(array(
          'FLAGS' => 'accesskey="i" onclick="ajaxProtect(1); return false;" id="protbtn_1" title="Prevents all non-administrators from editing this page. [alt-i]"'.$ctmp,
          'HREF'  => makeUrl($paths->page, 'do=protect&level=1', true),
          'TEXT'  => 'on'
        ));
      $t1 = $menubtn->run();
      
      $ctmp = '';
      if ( $paths->cpage['protected'] == 0 )
      {
        $ctmp=' style="text-decoration: underline;"';
      }
      $menubtn->assign_vars(array(
          'FLAGS' => 'accesskey="o" onclick="ajaxProtect(0); return false;" id="protbtn_0" title="Allows everyone to edit this page. [alt-o]"'.$ctmp,
          'HREF'  => makeUrl($paths->page, 'do=protect&level=0', true),
          'TEXT'  => 'off'
        ));
      $t2 = $menubtn->run();
      
      $ctmp = '';
      if ( $paths->cpage['protected'] == 2 )
      {
        $ctmp = ' style="text-decoration: underline;"';
      }
      $menubtn->assign_vars(array(
          'FLAGS' => 'accesskey="p" onclick="ajaxProtect(2); return false;" id="protbtn_2" title="Allows only users who have been registered for 4 days to edit this page. [alt-p]"'.$ctmp,
          'HREF'  => makeUrl($paths->page, 'do=protect&level=2', true),
          'TEXT'  => 'semi'
        ));
      $t3 = $menubtn->run();
      
      $this->toolbar_menu .= '        <table border="0" cellspacing="0" cellpadding="0">
          <tr>
            <td>'.$t0.'</td>
            <td>'.$t1.'</td>
            <td>'.$t2.'</td>
            <td>'.$t3.'</td>
          </tr>
        </table>';
    }
    
    // Wiki mode button
    if($session->get_permissions('read') && $paths->page_exists && $session->get_permissions('set_wiki_mode') && $paths->namespace != 'Special' && $paths->namespace != 'Admin')
    {
      // label at start
      $label = $this->makeParserText($tplvars['toolbar_label']);
      $label->assign_vars(array('TEXT' => 'page wiki mode:'));
      $t0 = $label->run();
      
      // on button
      $ctmp = '';
      if ( $paths->cpage['wiki_mode'] == 1 )
      {
        $ctmp = ' style="text-decoration: underline;"';
      }
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="ajaxSetWikiMode(1); return false;" id="wikibtn_1" title="Forces wiki functions to be allowed on this page."'.$ctmp,
          'HREF' => makeUrl($paths->page, 'do=setwikimode&level=1', true),
          'TEXT' => 'on'
        ));
      $t1 = $menubtn->run();
      
      // off button
      $ctmp = '';
      if ( $paths->cpage['wiki_mode'] == 0 )
      {
        $ctmp=' style="text-decoration: underline;"';
      }
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="ajaxSetWikiMode(0); return false;" id="wikibtn_0" title="Forces wiki functions to be disabled on this page."'.$ctmp,
          'HREF' => makeUrl($paths->page, 'do=setwikimode&level=0', true),
          'TEXT' => 'off'
        ));
      $t2 = $menubtn->run();
      
      // global button
      $ctmp = ''; 
      if ( $paths->cpage['wiki_mode'] == 2 )
      {
        $ctmp=' style="text-decoration: underline;"';
      }
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="ajaxSetWikiMode(2); return false;" id="wikibtn_2" title="Causes this page to use the global wiki mode setting (default)"'.$ctmp,
          'HREF' => makeUrl($paths->page, 'do=setwikimode&level=2', true),
          'TEXT' => 'global'
        ));
      $t3 = $menubtn->run();
      
      // Tack it onto the list of buttons that are already there...
      $this->toolbar_menu .= '        <table border="0" cellspacing="0" cellpadding="0">
          <tr>
            <td>'.$t0.'</td>
            <td>'.$t1.'</td>
            <td>'.$t2.'</td>
            <td>'.$t3.'</td>
          </tr>
        </table>';
    }
    
    // Clear logs button
    if ( $session->get_permissions('read') && $session->get_permissions('clear_logs') && $paths->namespace != 'Special' && $paths->namespace != 'Admin' )
    {
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="void(ajaxClearLogs()); return false;" title="Remove all edit and action logs for this page from the database. IRREVERSIBLE! (alt-l)" accesskey="l"',
          'HREF'  => makeUrl($paths->page, 'do=flushlogs', true),
          'TEXT'  => 'clear page logs',
        ));
      $this->toolbar_menu .= $menubtn->run();
    }
    
    // Delete page button
    if ( $session->get_permissions('read') && $session->get_permissions('delete_page') && $paths->page_exists && $paths->namespace != 'Special' && $paths->namespace != 'Admin' )
    {
      $s = 'delete this page';
      if ( $paths->cpage['delvotes'] == 1 )
      {
        $s .= ' (<b>'.$paths->cpage['delvotes'].'</b> vote)';
      }
      else if ( $paths->cpage['delvotes'] > 1 )
      {
        $s .= ' (<b>'.$paths->cpage['delvotes'].'</b> votes)';
      }
      
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="void(ajaxDeletePage()); return false;" title="Delete this page. This is always reversible unless the logs are cleared. (alt-k)" accesskey="k"',
          'HREF'  => makeUrl($paths->page, 'do=deletepage', true),
          'TEXT'  => $s,
        ));
      $this->toolbar_menu .= $menubtn->run();
      
    }
    
    // Password-protect button
    if(isset($paths->cpage['password']))
    {
      if ( $paths->cpage['password'] == '' )
      {
        $a = $session->get_permissions('password_set');
      }
      else
      {
        $a = $session->get_permissions('password_reset');
      }
    }
    else
    {
      $a = $session->get_permissions('password_set');
    }
    if ( $a && $session->get_permissions('read') && $paths->page_exists && $paths->namespace != 'Special' && $paths->namespace != 'Admin' )
    {
      // label at start
      $label = $this->makeParserText($tplvars['toolbar_label']);
      $label->assign_vars(array('TEXT' => 'page password:'));
      $t0 = $label->run();
      
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="void(ajaxSetPassword()); return false;" title="Require a password in order for this page to be viewed"',
          'HREF'  => '#',
          'TEXT'  => 'set',
        ));
      $t = $menubtn->run();
      
      $this->toolbar_menu .= '<table border="0" cellspacing="0" cellpadding="0"><tr><td>'.$t0.'</td><td><input type="password" id="mdgPassSetField" size="10" /></td><td>'.$t.'</td></tr></table>';
    }
    
    // Manage ACLs button
    if($session->get_permissions('edit_acl') || $session->user_level >= USER_LEVEL_ADMIN)
    {
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="return ajaxOpenACLManager();" title="Manage who can do what with this page (alt-m)" accesskey="m"',
          'HREF'  => makeUrl($paths->page, 'do=aclmanager', true),
          'TEXT'  => 'manage page access',
        ));
      $this->toolbar_menu .= $menubtn->run();
    }
    
    // Administer page button
    if ( $session->user_level >= USER_LEVEL_ADMIN && $paths->page_exists && $paths->namespace != 'Special' && $paths->namespace != 'Admin' )
    {
      $menubtn->assign_vars(array(
          'FLAGS' => 'onclick="void(ajaxAdminPage()); return false;" title="Administrative options for this page" accesskey="g"',
          'HREF'  => makeUrlNS('Special', 'Administration', 'module='.$paths->nslist['Admin'].'PageManager', true),
          'TEXT'  => 'administrative options',
        ));
      $this->toolbar_menu .= $menubtn->run();
    }
    
    if ( strlen($this->toolbar_menu) > 0 )
    {
      $button->assign_vars(array(
        'FLAGS'       => 'id="mdgToolbar_moreoptions" onclick="return false;" title="Additional options for working with this page"',
        'PARENTFLAGS' => '',
        'HREF'        => makeUrl($paths->page, 'do=moreoptions', true),
        'TEXT'        => 'more options'
        ));
      $tb .= $button->run();
    }
    
    $is_opera = (isset($_SERVER['HTTP_USER_AGENT']) && strstr($_SERVER['HTTP_USER_AGENT'], 'Opera')) ? true : false;
    
    $this->tpl_bool = Array(
      'auth_admin'=>$session->user_level >= USER_LEVEL_ADMIN ? true : false,
      'user_logged_in'=>$session->user_logged_in,
      'opera'=>$is_opera,
      );
    
    if($session->sid_super) { $ash = '&amp;auth='.$session->sid_super; $asq = "?auth=".$session->sid_super; $asa = "&auth=".$session->sid_super; $as2 = htmlspecialchars(urlSeparator).'auth='.$session->sid_super; }
    else { $asq=''; $asa=''; $as2 = ''; $ash = ''; }
    
    $code = $plugins->setHook('compile_template');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    // Some additional sidebar processing
    if($this->sidebar_extra != '') {
      $se = $this->sidebar_extra;
      $parser = $this->makeParserText($tplvars['sidebar_section_raw']);
      $parser->assign_vars(Array('TITLE'=>'Links','CONTENT'=>$se));
      $this->sidebar_extra = $parser->run();
    }
    
    $this->sidebar_extra = $this->sidebar_extra.$this->sidebar_widgets;
    
    $this->tpl_bool['fixed_menus'] = false;
    /* if($this->sidebar_extra == '') $this->tpl_bool['right_sidebar'] = false;
    else */ $this->tpl_bool['right_sidebar'] = true;
    
    $this->tpl_bool['auth_rename'] = ( $paths->page_exists && ( $session->get_permissions('rename') && ( $paths->page_protected && $session->get_permissions('even_when_protected') || !$paths->page_protected ) ) && $paths->namespace != 'Special' && $paths->namespace != 'Admin');
    
    $this->tpl_bool['enable_uploads'] = ( getConfig('enable_uploads') == '1' && $session->get_permissions('upload_files') ) ? true : false;
    
    $this->tpl_bool['stupid_mode'] = false;
    
    if($paths->page == $paths->nslist['Special'].'Administration') $this->tpl_bool['in_admin'] = true;
    else $this->tpl_bool['in_admin'] = false;
    
    $p = ( isset($_GET['printable']) ) ? '/printable' : '';
    
    // Add the e-mail address client code to the header
    $this->add_header($email->jscode());
    
    // Generate the code for the Log out and Change theme sidebar buttons
    // Once again, the new template parsing system can be used here
    
    $parser = $this->makeParserText($tplvars['sidebar_button']);
    
    $parser->assign_vars(Array(
        'HREF'=>makeUrlNS('Special', 'Logout'),
        'FLAGS'=>'onclick="mb_logout(); return false;"',
        'TEXT'=>'Log out',
      ));
    
    $logout_link = $parser->run();
    
    $parser->assign_vars(Array(
        'HREF'=>makeUrlNS('Special', 'Login/' . $paths->page),
        'FLAGS'=>'onclick="ajaxStartLogin(); return false;"',
        'TEXT'=>'Log in',
      ));
    
    $login_link = $parser->run();
    
    $parser->assign_vars(Array(
        'HREF'=>makeUrlNS('Special', 'ChangeStyle/'.$paths->page),
        'FLAGS'=>'onclick="ajaxChangeStyle(); return false;"',
        'TEXT'=>'Change theme',
      ));
    
    $theme_link = $parser->run();
    
    $SID = ($session->sid_super) ? $session->sid_super : '';
    
    $urlname_clean = str_replace('\'', '\\\'', str_replace('\\', '\\\\', dirtify_page_id($paths->fullpage)));
    $urlname_clean = strtr( $urlname_clean, array( '<' => '&lt;', '>' => '&gt;' ) );
    
    // Generate the dynamic javascript vars
    $js_dynamic = '    <script type="text/javascript">// <![CDATA[
      // This section defines some basic and very important variables that are used later in the static Javascript library.
      // SKIN DEVELOPERS: The template variable for this code block is {JS_DYNAMIC_VARS}. This MUST be inserted BEFORE the tag that links to the main Javascript lib.
      var title=\''. $urlname_clean .'\';
      var page_exists='. ( ( $paths->page_exists) ? 'true' : 'false' ) .';
      var scriptPath=\''. scriptPath .'\';
      var contentPath=\''.contentPath.'\';
      var ENANO_SID =\'' . $SID . '\';
      var auth_level=' . $session->auth_level . ';
      var USER_LEVEL_GUEST = ' . USER_LEVEL_GUEST . ';
      var USER_LEVEL_MEMBER = ' . USER_LEVEL_MEMBER . ';
      var USER_LEVEL_CHPREF = ' . USER_LEVEL_CHPREF . ';
      var USER_LEVEL_MOD = ' . USER_LEVEL_MOD . ';
      var USER_LEVEL_ADMIN = ' . USER_LEVEL_ADMIN . ';
      var editNotice = \'' . ( (getConfig('wiki_edit_notice')=='1') ? str_replace("\n", "\\\n", RenderMan::render(getConfig('wiki_edit_notice_text'))) : '' ) . '\';
      var prot = ' . ( ($paths->page_protected && !$session->get_permissions('even_when_protected')) ? 'true' : 'false' ) .'; // No, hacking this var won\'t work, it\'s re-checked on the server
      var ENANO_SPECIAL_CREATEPAGE = \''. makeUrl($paths->nslist['Special'].'CreatePage') .'\';
      var ENANO_CREATEPAGE_PARAMS = \'_do=&pagename='. $urlname_clean .'&namespace=' . $paths->namespace . '\';
      var ENANO_SPECIAL_CHANGESTYLE = \''. makeUrlNS('Special', 'ChangeStyle') .'\';
      var namespace_list = new Array();
      var AES_BITS = '.AES_BITS.';
      var AES_BLOCKSIZE = '.AES_BLOCKSIZE.';
      var pagepass = \''. ( ( isset($_REQUEST['pagepass']) ) ? sha1($_REQUEST['pagepass']) : '' ) .'\';
      var ENANO_THEME_LIST = \'';
          foreach($this->theme_list as $t) {
            if($t['enabled'])
            {
              $js_dynamic .= '<option value="'.$t['theme_id'].'"';
              if($t['theme_id'] == $session->theme) $js_dynamic .= ' selected="selected"';
              $js_dynamic .= '>'.$t['theme_name'].'</option>';
            }
          }
      $js_dynamic .= '\';
      var ENANO_CURRENT_THEME = \''. $session->theme .'\';';
      foreach($paths->nslist as $k => $c)
      {
        $js_dynamic .= "namespace_list['{$k}'] = '$c';";
      }
      $js_dynamic .= "\n    //]]>\n    </script>";
      
    $tpl_strings = Array(
      'PAGE_NAME'=>htmlspecialchars($paths->cpage['name']),
      'PAGE_URLNAME'=> $urlname_clean,
      'SITE_NAME'=>getConfig('site_name'),
      'USERNAME'=>$session->username,
      'SITE_DESC'=>getConfig('site_desc'),
      'TOOLBAR'=>$tb,
      'SCRIPTPATH'=>scriptPath,
      'CONTENTPATH'=>contentPath,
      'ADMIN_SID_QUES'=>$asq,
      'ADMIN_SID_AMP'=>$asa,
      'ADMIN_SID_AMP_HTML'=>$ash,
      'ADMIN_SID_AUTO'=>$as2,
      'ADDITIONAL_HEADERS'=>$this->additional_headers,
      'COPYRIGHT'=>getConfig('copyright_notice'),
      'TOOLBAR_EXTRAS'=>$this->toolbar_menu,
      'REQUEST_URI'=>$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],
      'STYLE_LINK'=>makeUrlNS('Special', 'CSS'.$p, null, true), //contentPath.$paths->nslist['Special'].'CSS' . $p,
      'LOGIN_LINK'=>$login_link,
      'LOGOUT_LINK'=>$logout_link,
      'THEME_LINK'=>$theme_link,
      'TEMPLATE_DIR'=>scriptPath.'/themes/'.$this->theme,
      'THEME_ID'=>$this->theme,
      'STYLE_ID'=>$this->style,
      'JS_DYNAMIC_VARS'=>$js_dynamic,
      'UNREAD_PMS'=>$session->unread_pms
      );
    
    foreach ( $paths->nslist as $ns_id => $ns_prefix )
    {
      $tpl_strings[ 'NS_' . strtoupper($ns_id) ] = $ns_prefix;
    }
    
    $this->tpl_strings = array_merge($tpl_strings, $this->tpl_strings);
    list($this->tpl_strings['SIDEBAR_LEFT'], $this->tpl_strings['SIDEBAR_RIGHT'], $min) = $this->fetch_sidebar();
    $this->tpl_bool['sidebar_left']  = ( $this->tpl_strings['SIDEBAR_LEFT']  != $min) ? true : false;
    $this->tpl_bool['sidebar_right'] = ( $this->tpl_strings['SIDEBAR_RIGHT'] != $min) ? true : false;
    $this->tpl_bool['right_sidebar'] = $this->tpl_bool['sidebar_right']; // backward compatibility
  }
  
  function header($simple = false) 
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    ob_start();
    
    if(!$this->theme_loaded)
    {
      $this->load_theme($session->theme, $session->style);
    }
    
    $headers_sent = true;
    dc_here('template: generating and sending the page header');
    if(!defined('ENANO_HEADERS_SENT'))
      define('ENANO_HEADERS_SENT', '');
    if(!$this->no_headers) echo ( $simple ) ? $this->process_template('simple-header.tpl') : $this->process_template('header.tpl');
    if ( !$simple && $session->user_logged_in && $session->unread_pms > 0 )
    {
      echo $this->notify_unread_pms();
    }
    if ( !$simple && $session->sw_timed_out )
    {
      $login_link = makeUrlNS('Special', 'Login/' . $paths->fullpage, 'level=' . $session->user_level, true);
      echo '<div class="usermessage">';
      echo '<b>Your administrative session has timed out.</b> <a href="' . $login_link . '">Log in again</a>';
      echo '</div>';
    }
  }
  function footer($simple = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    dc_here('template: generating and sending the page footer');
    if(!$this->no_headers) {
      
      if(!defined('ENANO_HEADERS_SENT'))
        $this->header();
      
      global $_starttime;
      if(isset($_GET['sqldbg']) && $session->get_permissions('mod_misc'))
      {
        echo '<h3>Query list as requested on URI</h3><pre style="margin-left: 1em">';
        echo $db->sql_backtrace();
        echo '</pre>';
      }
      
      $f = microtime_float();
      $f = $f - $_starttime;
      $f = round($f, 4);
      $dbg = 'Time: '.$f.'s  |  Queries: '.$db->num_queries;
      $t = ( $simple ) ? $this->process_template('simple-footer.tpl') : $this->process_template('footer.tpl');
      $t = str_replace('[[Stats]]', $dbg, $t);
      $t = str_replace('[[NumQueries]]', (string)$db->num_queries, $t);
      $t = str_replace('[[GenTime]]', (string)$f, $t);
      echo $t;
      
      ob_end_flush();
    }
    else return '';
  }
  function getHeader()
  {
    $headers_sent = true;
    dc_here('template: generating and sending the page header');
    if(!defined('ENANO_HEADERS_SENT'))
      define('ENANO_HEADERS_SENT', '');
    if(!$this->no_headers) return $this->process_template('header.tpl');
  }
  function getFooter()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    dc_here('template: generating and sending the page footer');
    if(!$this->no_headers) {
      global $_starttime;
      $t = '';
      
      if(isset($_GET['sqldbg']) && $session->get_permissions('mod_misc'))
      {
        $t .= '<h3>Query list as requested on URI</h3><pre style="margin-left: 1em">';
        $t .= $db->sql_backtrace();
        $t .= '</pre>';
      }
      
      $f = microtime_float();
      $f = $f - $_starttime;
      $f = round($f, 4);
      $dbg = 'Time: '.$f.'s  |  Queries: '.$db->num_queries;
      $t.= $this->process_template('footer.tpl');
      $t = str_replace('[[Stats]]', $dbg, $t);
      $t = str_replace('[[NumQueries]]', (string)$db->num_queries, $t);
      $t = str_replace('[[GenTime]]', (string)$f, $t);
      return $t;
    }
    else return '';
  }
  
  function process_template($file) {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!defined('ENANO_TEMPLATE_LOADED'))
    {
      $this->load_theme();
      $this->init_vars();
    }
    eval($this->compile_template($file));
    return $tpl_code;
  }
  
  function extract_vars($file) {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$this->theme)
    {
      die('$template->extract_vars(): theme not yet loaded, so we can\'t open template files yet...this is a bug and should be reported.<br /><br />Backtrace, most recent call first:<pre>'.enano_debug_print_backtrace(true).'</pre>');
    }
    if(!is_file(ENANO_ROOT . '/themes/'.$this->theme.'/'.$file)) die('Cannot find '.$file.' file for style "'.$this->theme.'", exiting');
    $text = file_get_contents(ENANO_ROOT . '/themes/'.$this->theme.'/'.$file);
    preg_match_all('#<\!-- VAR ([A-z0-9_-]*) -->(.*?)<\!-- ENDVAR \\1 -->#is', $text, $matches);
    $tplvars = Array();
    for($i=0;$i<sizeof($matches[1]);$i++)
    {
      $tplvars[$matches[1][$i]] = $matches[2][$i];
    }
    return $tplvars;
  }
  function compile_template($text) {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!is_file(ENANO_ROOT . '/themes/'.$this->theme.'/'.$text)) die('Cannot find '.$text.' file for style, exiting');
    $n = $text;
    $tpl_filename = ENANO_ROOT . '/cache/' . $this->theme . '-' . str_replace('/', '-', $n) . '.php';
    if(!is_file(ENANO_ROOT . '/themes/'.$this->theme.'/'.$text)) die('Cannot find '.$text.' file for style, exiting');
    if(file_exists($tpl_filename) && getConfig('cache_thumbs')=='1')
    {
      include($tpl_filename);
      $text = file_get_contents(ENANO_ROOT . '/themes/'.$this->theme.'/'.$text);
      if(isset($md5) && $md5 == md5($text)) {
        return str_replace('\\"', '"', $tpl_text);
      }
    }
    $text = file_get_contents(ENANO_ROOT . '/themes/'.$this->theme.'/'.$n);
    
    $md5 = md5($text);
    
    $seed = md5 ( microtime() . mt_rand() );
    preg_match_all("/<\?php(.*?)\?>/is", $text, $m);
    //die('<pre>'.htmlspecialchars(print_r($m, true)).'</pre>');
    for($i = 0; $i < sizeof($m[1]); $i++)
    {
      $text = str_replace("<?php{$m[1][$i]}?>", "{PHPCODE:{$i}:{$seed}}", $text);
    }
    //die('<pre>'.htmlspecialchars($text).'</pre>');
    $text = 'ob_start(); echo \''.str_replace('\'', '\\\'', $text).'\'; $tpl_code = ob_get_contents(); ob_end_clean();';
    $text = preg_replace('#<!-- BEGIN (.*?) -->#is', '\'; if(isset($this->tpl_bool[\'\\1\']) && $this->tpl_bool[\'\\1\']) { echo \'', $text);
    $text = preg_replace('#<!-- IFSET (.*?) -->#is', '\'; if(isset($this->tpl_strings[\'\\1\'])) { echo \'', $text);
    $text = preg_replace('#<!-- IFPLUGIN (.*?) -->#is', '\'; if(getConfig(\'plugin_\\1\')==\'1\') { echo \'', $text);
    $text = preg_replace('#<!-- SYSMSG (.*?) -->#is', '\'; echo $template->tplWikiFormat($paths->sysMsg(\'\\1\')); echo \'', $text);
    $text = preg_replace('#<!-- BEGINNOT (.*?) -->#is', '\'; if(!$this->tpl_bool[\'\\1\']) { echo \'', $text);
    $text = preg_replace('#<!-- BEGINELSE (.*?) -->#is', '\'; } else { echo \'', $text);
    $text = preg_replace('#<!-- END (.*?) -->#is', '\'; } echo \'', $text);
    $text = preg_replace('#\{([A-z0-9]*)\}#is', '\'.$this->tpl_strings[\'\\1\'].\'', $text);
    for($i = 0; $i < sizeof($m[1]); $i++)
    {
      $text = str_replace("{PHPCODE:{$i}:{$seed}}", "'; {$m[1][$i]} echo '", $text);
    }
    if(is_writable(ENANO_ROOT.'/cache/') && getConfig('cache_thumbs')=='1')
    {
      //die($tpl_filename);
      $h = fopen($tpl_filename, 'w');
      if(!$h) return $text;
      $t = addslashes($text);
      fwrite($h, '<?php $md5 = \''.$md5.'\'; $tpl_text = \''.$t.'\'; ?>');
      fclose($h);
    }
    return $text; //('<pre>'.htmlspecialchars($text).'</pre>');
  }
  
  function compile_template_text($text) {
    $seed = md5 ( microtime() . mt_rand() );
    preg_match_all("/<\?php(.*?)\?>/is", $text, $m);
    //die('<pre>'.htmlspecialchars(print_r($m, true)).'</pre>');
    for($i = 0; $i < sizeof($m[1]); $i++)
    {
      $text = str_replace("<?php{$m[1][$i]}?>", "{PHPCODE:{$i}:{$seed}}", $text);
    }
    //die('<pre>'.htmlspecialchars($text).'</pre>');
    $text = 'ob_start(); echo \''.str_replace('\'', '\\\'', $text).'\'; $tpl_code = ob_get_contents(); ob_end_clean(); return $tpl_code;';
    $text = preg_replace('#<!-- BEGIN (.*?) -->#is', '\'; if(isset($this->tpl_bool[\'\\1\']) && $this->tpl_bool[\'\\1\']) { echo \'', $text);
    $text = preg_replace('#<!-- IFSET (.*?) -->#is', '\'; if(isset($this->tpl_strings[\'\\1\'])) { echo \'', $text);
    $text = preg_replace('#<!-- IFPLUGIN (.*?) -->#is', '\'; if(getConfig(\'plugin_\\1\')==\'1\') { echo \'', $text);
    $text = preg_replace('#<!-- SYSMSG (.*?) -->#is', '\'; echo $template->tplWikiFormat($paths->sysMsg(\'\\1\')); echo \'', $text);
    $text = preg_replace('#<!-- BEGINNOT (.*?) -->#is', '\'; if(!$this->tpl_bool[\'\\1\']) { echo \'', $text);
    $text = preg_replace('#<!-- BEGINELSE (.*?) -->#is', '\'; } else { echo \'', $text);
    $text = preg_replace('#<!-- END (.*?) -->#is', '\'; } echo \'', $text);
    $text = preg_replace('#\{([A-z0-9]*)\}#is', '\'.$this->tpl_strings[\'\\1\'].\'', $text);
    for($i = 0; $i < sizeof($m[1]); $i++)
    {
      $text = str_replace("{PHPCODE:{$i}:{$seed}}", "'; {$m[1][$i]} echo '", $text);
    }
    return $text; //('<pre>'.htmlspecialchars($text).'</pre>');
  }
  
  function parse($text)
  {
    $text = $this->compile_template_text($text);
    return eval($text);
  }
  
  // Steps to turn this:
  //   [[Project:Community Portal]]
  // into this:
  //   <a href="/Project:Community_Portal">Community Portal</a>
  // Must be done WITHOUT creating eval'ed code!!!
  
  // 1. preg_replace \[\[([a-zA-Z0-9 -_:]*?)\]\] with <a href="'.contentPath.'\\1">\\1</a>
  // 2. preg_match_all <a href="'.preg_quote(contentPath).'([a-zA-Z0-9 -_:]*?)">
  // 3. For each match, replace matches with identifiers
  // 4. For each match, str_replace ' ' with '_'
  // 5. For each match, str_replace match_id:random_val with $matches[$match_id]
  
  // The template language is really a miniature programming language; with variables, conditionals, everything!
  // So you can implement custom logic into your sidebar if you wish.
  // "Real" PHP support coming soon :-D
  
  function tplWikiFormat($message, $filter_links = false, $filename = 'elements.tpl') {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $filter_links = false;
    $tplvars = $this->extract_vars($filename);
    if($session->sid_super) $as = htmlspecialchars(urlSeparator).'auth='.$session->sid_super;
    else $as = '';
    error_reporting(E_ALL);
    $random_id = sha1(microtime().''); // A temp value
    
    /*
     * PREPROCESSOR
     */
    
    // Variables
    
    preg_match_all('#\$([A-Z_-]+)\$#', $message, $links);
    $links = $links[1];
    
    for($i=0;$i<sizeof($links);$i++)
    {
      $message = str_replace('$'.$links[$i].'$', $this->tpl_strings[$links[$i]], $message);
    }
    
    // Conditionals
    
    preg_match_all('#\{if ([A-Za-z0-9_ &\|\!-]*)\}(.*?)\{\/if\}#is', $message, $links);
    
    for($i=0;$i<sizeof($links[1]);$i++)
    {
      $message = str_replace('{if '.$links[1][$i].'}'.$links[2][$i].'{/if}', '{CONDITIONAL:'.$i.':'.$random_id.'}', $message);
      
      // Time for some manual parsing...
      $chk = false;
      $current_id = '';
      $prn_level = 0;
      // Used to keep track of where we are in the conditional
      // Object of the game: turn {if this && ( that OR !something_else )} ... {/if} into if( ( isset($this->tpl_bool['that']) && $this->tpl_bool['that'] ) && ...
      // Method of attack: escape all variables, ignore all else. Non-valid code is filtered out by a regex above.
      $in_var_now = true;
      $in_var_last = false;
      $current_var = '';
      $current_var_start_pos = 0;
      $current_var_end_pos   = 0;
      $j = -1;
      $links[1][$i] = $links[1][$i] . ' ';
      $d = strlen($links[1][$i]);
      while($j < $d)
      {
        $j++;
        $in_var_last = $in_var_now;
        
        $char = substr($links[1][$i], $j, 1);
        $in_var_now = ( preg_match('#^([A-z0-9_]*){1}$#', $char) ) ? true : false;
        if(!$in_var_last && $in_var_now)
        {
          $current_var_start_pos = $j;
        }
        if($in_var_last && !$in_var_now)
        {
          $current_var_end_pos = $j;
        }
        if($in_var_now)
        {
          $current_var .= $char;
          continue;
        }
        // OK we are not inside of a variable. That means that we JUST hit the end because the counter ($j) will be advanced to the beginning of the next variable once processing here is complete.
        if($char != ' ' && $char != '(' && $char != ')' && $char != 'A' && $char != 'N' && $char != 'D' && $char != 'O' && $char != 'R' && $char != '&' && $char != '|' && $char != '!' && $char != '<' && $char != '>' && $char != '0' && $char != '1' && $char != '2' && $char != '3' && $char != '4' && $char != '5' && $char != '6' && $char != '7' && $char != '8' && $char != '9')
        {
          // XSS attack! Bail out
          echo '<p><b>Error:</b> Syntax error (possibly XSS attack) caught in template code:</p>';
          echo '<pre>';
          echo '{if '.$links[1][$i].'}';
          echo "\n    ";
          for($k=0;$k<$j;$k++) echo " ";
          echo '<span style="color: red;">^</span>';
          echo '</pre>';
          continue 2;
        }
        if($current_var != '')
        {
          $cd = '( isset($this->tpl_bool[\''.$current_var.'\']) && $this->tpl_bool[\''.$current_var.'\'] )';
          $cvt = substr($links[1][$i], 0, $current_var_start_pos) . $cd . substr($links[1][$i], $current_var_end_pos, strlen($links[1][$i]));
          $j = $j + strlen($cd) - strlen($current_var);
          $current_var = '';
          $links[1][$i] = $cvt;
          $d = strlen($links[1][$i]);
        }
      }
      $links[1][$i] = substr($links[1][$i], 0, strlen($links[1][$i])-1);
      $links[1][$i] = '$chk = ( '.$links[1][$i].' ) ? true : false;';
      eval($links[1][$i]);
      
      if($chk) { // isset($this->tpl_bool[$links[1][$i]]) && $this->tpl_bool[$links[1][$i]]
        if(strstr($links[2][$i], '{else}')) $c = substr($links[2][$i], 0, strpos($links[2][$i], '{else}'));
        else $c = $links[2][$i];
        $message = str_replace('{CONDITIONAL:'.$i.':'.$random_id.'}', $c, $message);
      } else {
        if(strstr($links[2][$i], '{else}')) $c = substr($links[2][$i], strpos($links[2][$i], '{else}')+6, strlen($links[2][$i]));
        else $c = '';
        $message = str_replace('{CONDITIONAL:'.$i.':'.$random_id.'}', $c, $message);
      }
    }
    
    preg_match_all('#\{!if ([A-Za-z_-]*)\}(.*?)\{\/if\}#is', $message, $links);
    
    for($i=0;$i<sizeof($links[1]);$i++)
    {
      $message = str_replace('{!if '.$links[1][$i].'}'.$links[2][$i].'{/if}', '{CONDITIONAL:'.$i.':'.$random_id.'}', $message);
      if(isset($this->tpl_bool[$links[1][$i]]) && $this->tpl_bool[$links[1][$i]]) {
        if(strstr($links[2][$i], '{else}')) $c = substr($links[2][$i], strpos($links[2][$i], '{else}')+6, strlen($links[2][$i]));
        else $c = '';
        $message = str_replace('{CONDITIONAL:'.$i.':'.$random_id.'}', $c, $message);
      } else {
        if(strstr($links[2][$i], '{else}')) $c = substr($links[2][$i], 0, strpos($links[2][$i], '{else}'));
        else $c = $links[2][$i];
        $message = str_replace('{CONDITIONAL:'.$i.':'.$random_id.'}', $c, $message);
      }
    }
    
    /*
     * HTML RENDERER
     */
     
    // Images
    $j = preg_match_all('#\[\[:'.$paths->nslist['File'].'([\w\s0-9_\(\)!@%\^\+\|\.-]+?)\]\]#is', $message, $matchlist);
    $matches = Array();
    $matches['images'] = $matchlist[1];
    for($i=0;$i<sizeof($matchlist[1]);$i++)
    {
      if(isPage($paths->nslist['File'].$matches['images'][$i]))
      {
        $message = str_replace('[[:'.$paths->nslist['File'].$matches['images'][$i].']]',
                               '<img alt="'.$matches['images'][$i].'" style="border: 0" src="'.makeUrlNS('Special', 'DownloadFile/'.$matches['images'][$i]).'" />',
                               $message);
      }
    }
    
    // Internal links
    
    $text_parser = $this->makeParserText($tplvars['sidebar_button']);
    
    preg_match_all('#\[\[([a-zA-Z0-9 -_]*?)\]\]#is', $message, $il);
    for($i=0;$i<sizeof($il[1]);$i++)
    {
      $href = makeUrl(str_replace(' ', '_', $il[1][$i]), null, true);
      $text_parser->assign_vars(Array(
          'HREF'  => $href,
          'FLAGS' => '',
          'TEXT'  => $il[1][$i]
        ));
      $message = str_replace("[[{$il[1][$i]}]]", $text_parser->run(), $message);
    }
    
    preg_match_all('#\[\[([a-zA-Z0-9 -_]*?)\|([a-zA-Z0-9!@\#\$%\^&\*\(\)\{\} -_]*?)\]\]#is', $message, $il);
    for($i=0;$i<sizeof($il[1]);$i++)
    {
      $href = makeUrl(str_replace(' ', '_', $il[1][$i]), null, true);
      $text_parser->assign_vars(Array(
          'HREF'  => $href,
          'FLAGS' => '',
          'TEXT'  => $il[2][$i]
        ));
      $message = str_replace("[[{$il[1][$i]}|{$il[2][$i]}]]", $text_parser->run(), $message);
    }
    
    // External links
    $message = preg_replace('#\[(http|ftp|irc):\/\/([a-z0-9\/:_\.\?&%\#@_\\\\-]+?)\\ ([^\]]+)]#', '<a href="\\1://\\2">\\3</a><br style="display: none;" />', $message);
    $message = preg_replace('#\[(http|ftp|irc):\/\/([a-z0-9\/:_\.\?&%\#@_\\\\-]+?)\\]#', '<a href="\\1://\\2">\\1://\\2</a><br style="display: none;" />', $message);
    
    $parser1 = $this->makeParserText($tplvars['sidebar_section']);
    $parser2 = $this->makeParserText($tplvars['sidebar_section_raw']);
                            
    preg_match_all('#\{slider(2|)=(.*?)\}(.*?)\{\/slider(2|)\}#is',  $message, $sb);
    
    // Modified to support the sweet new template var system
    for($i=0;$i<sizeof($sb[1]);$i++)
    {
      $p = ($sb[1][$i] == '2') ? $parser2 : $parser1;
      $p->assign_vars(Array('TITLE'=>$sb[2][$i],'CONTENT'=>$sb[3][$i]));
      $message = str_replace("{slider{$sb[1][$i]}={$sb[2][$i]}}{$sb[3][$i]}{/slider{$sb[4][$i]}}", $p->run(), $message);
    }
    
    /*
    Extras ;-)
    $message = preg_replace('##is', '', $message);
    $message = preg_replace('##is', '', $message);
    $message = preg_replace('##is', '', $message);
    $message = preg_replace('##is', '', $message);
    $message = preg_replace('##is', '', $message);
    */
    
    //die('<pre>'.htmlspecialchars($message).'</pre>');
    //eval($message); exit;
    return $message;
  }
  
  /**
   * Print a text field that auto-completes a username entered into it.
   * @param string $name - the name of the form field
   * @return string
   */
   
  function username_field($name, $value = false)
  {
    $randomid = md5( time() . microtime() . mt_rand() );
    $text = '<input name="'.$name.'" onkeyup="ajaxUserNameComplete(this)" autocomplete="off" type="text" size="30" id="userfield_'.$randomid.'"';
    if($value) $text .= ' value="'.$value.'"';
    $text .= ' />';
    return $text;
  }
  
  /**
   * Print a text field that auto-completes a page name entered into it.
   * @param string $name - the name of the form field
   * @return string
   */
   
  function pagename_field($name, $value = false)
  {
    $randomid = md5( time() . microtime() . mt_rand() );
    $text = '<input name="'.$name.'" onkeyup="ajaxPageNameComplete(this)" type="text" size="30" id="pagefield_'.$randomid.'"';
    if($value) $text .= ' value="'.$value.'"';
    $text .= ' />';
    $text .= '<script type="text/javascript">
        var inp = document.getElementById(\'pagefield_' . $randomid . '\');
        var f = get_parent_form(inp);
        if ( f )
        {
          if ( typeof(f.onsubmit) != \'function\' )
          {
            f.onsubmit = function() {
              if ( !submitAuthorized )
              {
                return false;
              }
            }
          }
        }</script>';
    return $text;
  }
  
  /**
   * Sends a textarea that can be converted to and from a TinyMCE widget on the fly.
   * @param string The name of the form element
   * @param string The initial content. Optional, defaults to blank
   * @param int Rows in textarea
   * @param int Columns in textarea
   * @return string HTML and Javascript code.
   */
  
  function tinymce_textarea($name, $content = '', $rows = 20, $cols = 60)
  {
    $randomid = md5(microtime() . mt_rand());
    $html = '';
    $html .= '<textarea name="' . $name . '" rows="'.$rows.'" cols="'.$cols.'" style="width: 100%;" id="toggleMCEroot_'.$randomid.'">' . $content . '</textarea>';
    $html .= '<div style="float: right; display: table;" id="mceSwitchAgent_' . $randomid . '">text editor&nbsp;&nbsp;|&nbsp;&nbsp;<a href="#" onclick="toggleMCE_'.$randomid.'(); return false;">graphical editor</a></div>';
    $html .= '<script type="text/javascript">
                // <![CDATA[
                function toggleMCE_'.$randomid.'()
                {
                  var the_obj = document.getElementById(\'toggleMCEroot_' . $randomid . '\');
                  var panel = document.getElementById(\'mceSwitchAgent_' . $randomid . '\');
                  if ( the_obj.dnIsMCE == "yes" )
                  {
                    $dynano(the_obj).destroyMCE();
                    panel.innerHTML = \'text editor&nbsp;&nbsp;|&nbsp;&nbsp;<a href="#" onclick="toggleMCE_'.$randomid.'(); return false;">graphical editor</a>\';
                  }
                  else
                  {
                    $dynano(the_obj).switchToMCE();
                    panel.innerHTML = \'<a href="#" onclick="toggleMCE_'.$randomid.'(); return false;">text editor</a>&nbsp;&nbsp;|&nbsp;&nbsp;graphical editor\';
                  }
                }
                // ]]>
              </script>';
    return $html;
  }
  
  /**
   * Allows individual parsing of template files. Similar to phpBB but follows the spirit of object-oriented programming ;)
   * Returns on object of class templateIndividual. Usage instructions can be found in the inline docs for that class.
   * @param $filename the filename of the template to be parsed
   * @return object
   */
   
  function makeParser($filename)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $filename = ENANO_ROOT.'/themes/'.$template->theme.'/'.$filename;
    if(!file_exists($filename)) die('templateIndividual: file '.$filename.' does not exist');
    $code = file_get_contents($filename);
    $parser = new templateIndividual($code);
    return $parser;
  }
  
  /**
   * Same as $template->makeParser(), but takes a string instead of a filename.
   * @param $text the text to parse
   * @return object
   */
   
  function makeParserText($code)
  {
    $parser = new templateIndividual($code);
    return $parser;
  }
  
  /**
   * Fetch the HTML for a plugin-added sidebar block
   * @param $name the plugin name
   * @return string
   */
   
  function fetch_block($id)
  {
    if(isset($this->plugin_blocks[$id])) return $this->plugin_blocks[$id];
    else return false;
  }
  
  /**
   * Fetches the contents of both sidebars.
   * @return array - key 0 is left, key 1 is right
   * @example list($left, $right) = $template->fetch_sidebar();
   */
   
  function fetch_sidebar()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $left = '';
    $right = '';
    
    if ( !$this->fetch_block('Links') )
      $this->initLinksWidget();
    
    $q = $db->sql_query('SELECT item_id,sidebar_id,block_name,block_type,block_content FROM '.table_prefix.'sidebar WHERE item_enabled=1 ORDER BY sidebar_id ASC, item_order ASC;');
    if(!$q) $db->_die('The sidebar text data could not be selected.');
    
    $vars = $this->extract_vars('elements.tpl');
    
    if(isset($vars['sidebar_top'])) 
    {
      $left  .= $this->parse($vars['sidebar_top']);
      $right .= $this->parse($vars['sidebar_top']);
    }
    while($row = $db->fetchrow())
    {
      switch($row['block_type'])
      {
        case BLOCK_WIKIFORMAT:
        default:
          $parser = $this->makeParserText($vars['sidebar_section']);
          $c = RenderMan::render($row['block_content']);
          break;
        case BLOCK_TEMPLATEFORMAT:
          $parser = $this->makeParserText($vars['sidebar_section']);
          $c = $this->tplWikiFormat($row['block_content']);
          break;
        case BLOCK_HTML:
          $parser = $this->makeParserText($vars['sidebar_section_raw']);
          $c = $row['block_content'];
          break;
        case BLOCK_PHP:
          $parser = $this->makeParserText($vars['sidebar_section_raw']);
          ob_start();
          @eval($row['block_content']);
          $c = ob_get_contents();
          ob_end_clean();
          break;
        case BLOCK_PLUGIN:
          $parser = $this->makeParserText($vars['sidebar_section_raw']);
          $c = (gettype($this->fetch_block($row['block_content'])) == 'string') ? $this->fetch_block($row['block_content']) : 'Can\'t find plugin block';
          break;
      }
      $parser->assign_vars(Array( 'TITLE'=>$this->tplWikiFormat($row['block_name']), 'CONTENT'=>$c ));
      if    ($row['sidebar_id'] == SIDEBAR_LEFT ) $left  .= $parser->run();
      elseif($row['sidebar_id'] == SIDEBAR_RIGHT) $right .= $parser->run();
      unset($parser);
    }
    $db->free_result();
    if(isset($vars['sidebar_bottom'])) 
    {
      $left  .= $this->parse($vars['sidebar_bottom']);
      $right .= $this->parse($vars['sidebar_bottom']);
    }
    $min = '';
    if(isset($vars['sidebar_top'])) 
    {
      $min .= $this->parse($vars['sidebar_top']);
    }
    if(isset($vars['sidebar_bottom']))
    {
      $min .= $this->parse($vars['sidebar_bottom']);
    }
    return Array($left, $right, $min);
  }
  
  function initLinksWidget()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    // SourceForge/W3C buttons
    $ob = Array();
    if(getConfig('powered_btn') =='1') $ob[] = '<a style="text-align: center;" href="http://www.enanocms.org/"                  onclick="window.open(this.href);return false;"><img alt="Powered by Enano" src="'.scriptPath.'/images/about-powered-enano.png" onmouseover="this.src=\''.scriptPath.'/images/about-powered-enano-hover.png\';" onmouseout="this.src=\''.scriptPath.'/images/about-powered-enano.png\';" style="border-width: 0px;" width="88" height="31" /></a>';
    if(getConfig('sflogo_enabled')=='1')
    {
      $ob[] = '<a style="text-align: center;" href="http://sourceforge.net/" onclick="window.open(this.href);return false;"><img style="border-width: 0px;" alt="SourceForge.net Logo" src="http://sflogo.sourceforge.net/sflogo.php?group_id='.getConfig('sflogo_groupid').'&amp;type='.getConfig('sflogo_type').'" /></a>';
    }
    if(getConfig('w3c_v32')     =='1') $ob[] = '<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="window.open(this.href);return false;"><img style="border: 0px solid #FFFFFF;" alt="Valid HTML 3.2"  src="http://www.w3.org/Icons/valid-html32" /></a>';
    if(getConfig('w3c_v40')     =='1') $ob[] = '<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="window.open(this.href);return false;"><img style="border: 0px solid #FFFFFF;" alt="Valid HTML 4.0"  src="http://www.w3.org/Icons/valid-html40" /></a>';
    if(getConfig('w3c_v401')    =='1') $ob[] = '<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="window.open(this.href);return false;"><img style="border: 0px solid #FFFFFF;" alt="Valid HTML 4.01" src="http://www.w3.org/Icons/valid-html401" /></a>';
    if(getConfig('w3c_vxhtml10')=='1') $ob[] = '<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="window.open(this.href);return false;"><img style="border: 0px solid #FFFFFF;" alt="Valid XHTML 1.0" src="http://www.w3.org/Icons/valid-xhtml10" /></a>';
    if(getConfig('w3c_vxhtml11')=='1') $ob[] = '<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="window.open(this.href);return false;"><img style="border: 0px solid #FFFFFF;" alt="Valid XHTML 1.1" src="http://www.w3.org/Icons/valid-xhtml11" /></a>';
    if(getConfig('w3c_vcss')    =='1') $ob[] = '<a style="text-align: center;" href="http://validator.w3.org/check?uri=referer" onclick="window.open(this.href);return false;"><img style="border: 0px solid #FFFFFF;" alt="Valid CSS"       src="http://www.w3.org/Icons/valid-css" /></a>';
    if(getConfig('dbd_button')  =='1') $ob[] = '<a style="text-align: center;" href="http://www.defectivebydesign.org/join/button" onclick="window.open(this.href);return false;"><img style="border: 0px solid #FFFFFF;" alt="DRM technology restricts what you can do with your computer" src="http://defectivebydesign.org/sites/nodrm.civicactions.net/files/images/dbd_sm_btn.gif" /><br /><small>Protect your freedom >></small></a>';
    
    $code = $plugins->setHook('links_widget');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    if(count($ob) > 0) $sb_links = '<div style="text-align: center; padding: 5px 0;">'.implode('<br />', $ob).'</div>';
    else $sb_links = '';
    
    $this->sidebar_widget('Links', $sb_links);
  }
  
  /**
   * Builds a box showing unread private messages.
   */
  
  function notify_unread_pms()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if ( ( $paths->cpage['urlname_nons'] == 'PrivateMessages' || $paths->cpage['urlname_nons'] == 'Preferences' ) && $paths->namespace == 'Special' )
    {
      return '';
    }
    $ob = '<div class="usermessage">'."\n";
    $s = ( $session->unread_pms == 1 ) ? '' : 's';
    $ob .= "  <b>You have $session->unread_pms <a href=" . '"' . makeUrlNS('Special', 'PrivateMessages' ) . '"' . ">unread private message$s</a>.</b><br />\n  Messages: ";
    $q = $db->sql_query('SELECT message_id,message_from,subject,date FROM '.table_prefix.'privmsgs WHERE message_to=\'' . $session->username . '\' AND message_read=0 ORDER BY date DESC;');
    if ( !$q )
      $db->_die();
    $messages = array();
    while ( $row = $db->fetchrow() )
    {
      $messages[] = '<a href="' . makeUrlNS('Special', 'PrivateMessages/View/' . $row['message_id']) . '" title="Sent ' . date('F d, Y h:i a', $row['date']) . ' by ' . $row['message_from'] . '">' . $row['subject'] . '</a>';
    }
    $ob .= implode(",\n    " , $messages)."\n";
    $ob .= '</div>'."\n";
    return $ob;
  }
  
} // class template

/**
 * Handles parsing of an individual template file. Instances should only be created through $template->makeParser(). To use:
 *   - Call $template->makeParser(template file name) - file name should be something.tpl, css/whatever.css, etc.
 *   - Make an array of strings you want the template to access. $array['STRING'] would be referenced in the template like {STRING}
 *   - Make an array of boolean values. These can be used for conditionals in the template (<!-- IF something --> whatever <!-- ENDIF something -->)
 *   - Call assign_vars() to pass the strings to the template parser. Same thing with assign_bool().
 *   - Call run() to parse the template and get your fully compiled HTML.
 * @access private
 */

class templateIndividual extends template {
  var $tpl_strings, $tpl_bool, $tpl_code;
  var $compiled = false;
  /**
   * Constructor.
   */
  function __construct($text)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $this->tpl_code = $text;
    $this->tpl_strings = $template->tpl_strings;
    $this->tpl_bool = $template->tpl_bool;
  }
  /**
   * PHP 4 constructor.
   */
  function templateIndividual($text)
  {
    $this->__construct($text);
  }
  /**
   * Assigns an array of string values to the template. Strings can be accessed from the template by inserting {KEY_NAME} in the template file.
   * @param $vars array
   */
  function assign_vars($vars)
  {
    $this->tpl_strings = array_merge($this->tpl_strings, $vars);
  }
  /**
   * Assigns an array of boolean values to the template. These can be used for <!-- IF ... --> statements.
   * @param $vars array
   */
  function assign_bool($vars)
  {
    $this->tpl_bool = array_merge($this->tpl_bool, $vars);
  }
  /**
   * Compiles and executes the template code.
   * @return string
   */
  function run()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$this->compiled)
    {
      $this->tpl_code = $this->compile_template_text($this->tpl_code);
      $this->compiled = true;
    }
    return eval($this->tpl_code);
  }
}

/**
 * A version of the template compiler that does not rely at all on the other parts of Enano. Used during installation and for showing
 * "critical error" messages. ** REQUIRES ** the Oxygen theme.
 */

class template_nodb {
  var $tpl_strings, $tpl_bool, $theme, $style, $no_headers, $additional_headers, $sidebar_extra, $sidebar_widgets, $toolbar_menu, $theme_list;
  function __construct() {
    
    $this->tpl_bool    = Array();
    $this->tpl_strings = Array();
    $this->sidebar_extra = '';
    $this->sidebar_widgets = '';
    $this->toolbar_menu = '';
    $this->additional_headers = '';
    
    $this->theme_list = Array(Array(
      'theme_id'=>'oxygen',
      'theme_name'=>'Oxygen',
      'theme_order'=>1,
      'enabled'=>1,
      ));
  }
  function template() {
    $this->__construct();
  }
  function get_css($s = false) {
    if($s)
      return $this->process_template('css/'.$s);
    else
      return $this->process_template('css/'.$this->style.'.css');
  }
  function load_theme($name, $css, $auto_init = true) {
    $this->theme = $name;
    $this->style = $css;
    
    $this->tpl_strings['SCRIPTPATH'] = scriptPath;
    if ( $auto_init )
      $this->init_vars();
  }
  function init_vars()
  {
    global $sideinfo;
    global $this_page;
    global $db, $session, $paths, $template, $plugins; // Common objects
    $tplvars = $this->extract_vars('elements.tpl');
    $tb = '';
    // Get the "article" button text (depends on namespace)
    if(defined('IN_ENANO_INSTALL')) $ns = 'installation page';
    else $ns = 'system error page';
    $t = str_replace('{FLAGS}', 'onclick="return false;" title="Hey! A button that doesn\'t do anything. Clever..." accesskey="a"', $tplvars['toolbar_button']);
    $t = str_replace('{HREF}', '#', $t);
    $t = str_replace('{TEXT}', $ns, $t);
    $tb .= $t;
    
    // Page toolbar
    
    $this->tpl_bool = Array(
      'auth_admin'=>true,
      'user_logged_in'=>true,
      'right_sidebar'=>false,
      );
    $this->tpl_bool['in_sidebar_admin'] = false;
    
    $this->tpl_bool['auth_rename'] = false;
    
    $asq = $asa = '';
    
    $this->tpl_bool['fixed_menus'] = false;
    $slink = defined('IN_ENANO_INSTALL') ? scriptPath.'/install.php?mode=css' : makeUrlNS('Special', 'CSS');
    
    $title = ( is_object($paths) ) ? $paths->page : 'Critical error';
    
    // The rewritten template engine will process all required vars during the load_template stage instead of (cough) re-processing everything each time around.
    $tpl_strings = Array(
      'PAGE_NAME'=>$this_page,
      'PAGE_URLNAME'=>'Null',
      'SITE_NAME'=>'Enano Installation',
      'USERNAME'=>'admin',
      'SITE_DESC'=>'Install Enano on your server.',
      'TOOLBAR'=>$tb,
      'SCRIPTPATH'=>scriptPath,
      'CONTENTPATH'=>contentPath,
      'ADMIN_SID_QUES'=>$asq,
      'ADMIN_SID_AMP'=>$asa,
      'ADMIN_SID_AMP_HTML'=>'',
      'ADDITIONAL_HEADERS'=>'<style type="text/css">div.pagenav { border-top: 1px solid #CCC; padding-top: 7px; margin-top: 10px; }</style>',
      'SIDEBAR_EXTRA'=>'',
      'COPYRIGHT'=>'Enano and all of its code, graphics, and more code is copyright &copy; 2006 Dan Fuhry.<br />This program is Free Software; see the file "GPL" included with this package for details.',
      'TOOLBAR_EXTRAS'=>'',
      'REQUEST_URI'=>$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],
      'STYLE_LINK'=>$slink,
      'LOGOUT_LINK'=>'',
      'THEME_LINK'=>'',
      'TEMPLATE_DIR'=>scriptPath.'/themes/'.$this->theme,
      'THEME_ID'=>$this->theme,
      'STYLE_ID'=>$this->style,
      'JS_DYNAMIC_VARS'=>'<script type="text/javascript">var title="'. $title .'"; var scriptPath="'.scriptPath.'"; var ENANO_SID=""; var AES_BITS='.AES_BITS.'; var AES_BLOCKSIZE=' . AES_BLOCKSIZE . '; var pagepass=\'\';</script>',
      'SIDEBAR_RIGHT'=>'',
      );
    $this->tpl_strings = array_merge($tpl_strings, $this->tpl_strings);
    
    $sidebar = ( gettype($sideinfo) == 'string' ) ? $sideinfo : '';
    if($sidebar != '')
    {
      if(isset($tplvars['sidebar_top']))
      {
        $text = $this->makeParserText($tplvars['sidebar_top']);
        $top = $text->run();
      } else {
        $top = '';
      }
      $p = $this->makeParserText($tplvars['sidebar_section']);
      $p->assign_vars(Array(
          'TITLE'=>'Installation progress',
          'CONTENT'=>$sidebar,
        ));
      $sidebar = $p->run();
      if(isset($tplvars['sidebar_bottom']))
      {
        $text = $this->makeParserText($tplvars['sidebar_bottom']);
        $bottom = $text->run();
      } else {
        $bottom = '';
      }
      $sidebar = $top . $sidebar . $bottom;
    }
    $this->tpl_strings['SIDEBAR_LEFT'] = $sidebar;
    
    $this->tpl_bool['sidebar_left']  = ( $this->tpl_strings['SIDEBAR_LEFT']  != '') ? true : false;
    $this->tpl_bool['sidebar_right'] = ( $this->tpl_strings['SIDEBAR_RIGHT'] != '') ? true : false;
    $this->tpl_bool['right_sidebar'] = $this->tpl_bool['sidebar_right']; // backward compatibility
    $this->tpl_bool['stupid_mode'] = true;
  }
  function header() 
  {
    if(!$this->no_headers) echo $this->process_template('header.tpl');
  }
  function footer()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$this->no_headers) {
      global $_starttime;
      $f = microtime(true);
      $f = $f - $_starttime;
      $f = round($f, 4);
      if(defined('IN_ENANO_INSTALL')) $nq = 'N/A';
      else $nq = $db->num_queries;
      if($nq == 0) $nq = 'N/A';
      $dbg = 'Time: '.$f.'s  |  Queries: '.$nq;
      $t = $this->process_template('footer.tpl');
      $t = str_replace('[[Stats]]', $dbg, $t);
      echo $t;
    }
    else return '';
  }
  function getHeader()
  {
    if(!$this->no_headers) return $this->process_template('header.tpl');
    else return '';
  }
  function getFooter()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$this->no_headers) {
      global $_starttime;
      $f = microtime(true);
      $f = $f - $_starttime;
      $f = round($f, 4);
      if(defined('IN_ENANO_INSTALL')) $nq = 'N/A';
      else $nq = $db->num_queries;
      if($nq == 0) $nq = 'N/A';
      $dbg = 'Time: '.$f.'s  |  Queries: '.$nq;
      if($nq == 0) $nq = 'N/A';
      $t = $this->process_template('footer.tpl');
      $t = str_replace('[[Stats]]', $dbg, $t);
      return $t;
    }
    else return '';
  }
  
  function process_template($file) {
    
    eval($this->compile_template($file));
    return $tpl_code;
  }
  
  function extract_vars($file) {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!is_file(ENANO_ROOT . '/themes/'.$this->theme.'/'.$file)) die('Cannot find '.$file.' file for style "'.$this->theme.'", exiting');
    $text = file_get_contents(ENANO_ROOT . '/themes/'.$this->theme.'/'.$file);
    preg_match_all('#<\!-- VAR ([A-z0-9_-]*) -->(.*?)<\!-- ENDVAR \\1 -->#is', $text, $matches);
    $tplvars = Array();
    for($i=0;$i<sizeof($matches[1]);$i++)
    {
      $tplvars[$matches[1][$i]] = $matches[2][$i];
    }
    return $tplvars;
  }
  function compile_template($text) {
    global $sideinfo;
    $text = file_get_contents(ENANO_ROOT . '/themes/'.$this->theme.'/'.$text);
    $text = str_replace('<script type="text/javascript" src="{SCRIPTPATH}/ajax.php?title={PAGE_URLNAME}&amp;_mode=jsres"></script>', '', $text); // Remove the AJAX code - we don't need it, and it requires a database connection
    $text = '$tpl_code = \''.str_replace('\'', '\\\'', $text).'\'; return $tpl_code;';
    $text = preg_replace('#<!-- BEGIN (.*?) -->#is', '\'; if($this->tpl_bool[\'\\1\']) { $tpl_code .= \'', $text);
    $text = preg_replace('#<!-- IFPLUGIN (.*?) -->#is', '\'; if(getConfig(\'plugin_\\1\')==\'1\') { $tpl_code .= \'', $text);
    if(defined('IN_ENANO_INSTALL')) $text = str_replace('<!-- SYSMSG Sidebar -->', '<div class="slider"><div class="heading"><a class="head">Installation progress</a></div><div class="slideblock">'.$sideinfo.'</div></div>', $text);
    else $text = str_replace('<!-- SYSMSG Sidebar -->', '<div class="slider"><div class="heading"><a class="head">System error</a></div><div class="slideblock"><a href="#" onclick="return false;">Enano critical error page</a></div></div>', $text);
    $text = preg_replace('#<!-- SYSMSG (.*?) -->#is', '', $text);
    $text = preg_replace('#<!-- BEGINNOT (.*?) -->#is', '\'; if(!$this->tpl_bool[\'\\1\']) { $tpl_code .= \'', $text);
    $text = preg_replace('#<!-- BEGINELSE (.*?) -->#is', '\'; } else { $tpl_code .= \'', $text);
    $text = preg_replace('#<!-- END (.*?) -->#is', '\'; } $tpl_code .= \'', $text);
    $text = preg_replace('#{([A-z0-9]*)}#is', '\'.$this->tpl_strings[\'\\1\'].\'', $text);
    return $text; //('<pre>'.htmlspecialchars($text).'</pre>');
  }
  
  function compile_template_text($text) {
    global $sideinfo;
    $text = str_replace('<script type="text/javascript" src="{SCRIPTPATH}/ajax.php?title={PAGE_URLNAME}&amp;_mode=jsres"></script>', '', $text); // Remove the AJAX code - we don't need it, and it requires a database connection
    $text = '$tpl_code = \''.str_replace('\'', '\\\'', $text).'\'; return $tpl_code;';
    $text = preg_replace('#<!-- BEGIN (.*?) -->#is', '\'; if($this->tpl_bool[\'\\1\']) { $tpl_code .= \'', $text);
    $text = preg_replace('#<!-- IFPLUGIN (.*?) -->#is', '\'; if(getConfig(\'plugin_\\1\')==\'1\') { $tpl_code .= \'', $text);
    if(defined('IN_ENANO_INSTALL')) $text = str_replace('<!-- SYSMSG Sidebar -->', '<div class="slider"><div class="heading"><a class="head">Installation progress</a></div><div class="slideblock">'.$sideinfo.'</div></div>', $text);
    else $text = str_replace('<!-- SYSMSG Sidebar -->', '<div class="slider"><div class="heading"><a class="head">System error</a></div><div class="slideblock"><a href="#" onclick="return false;">Enano critical error page</a></div></div>', $text);
    $text = preg_replace('#<!-- SYSMSG (.*?) -->#is', '', $text);
    $text = preg_replace('#<!-- BEGINNOT (.*?) -->#is', '\'; if(!$this->tpl_bool[\'\\1\']) { $tpl_code .= \'', $text);
    $text = preg_replace('#<!-- BEGINELSE (.*?) -->#is', '\'; } else { $tpl_code .= \'', $text);
    $text = preg_replace('#<!-- END (.*?) -->#is', '\'; } $tpl_code .= \'', $text);
    $text = preg_replace('#{([A-z0-9]*)}#is', '\'.$this->tpl_strings[\'\\1\'].\'', $text);
    return $text; //('<pre>'.htmlspecialchars($text).'</pre>');
  }
  
  /**
   * Allows individual parsing of template files. Similar to phpBB but follows the spirit of object-oriented programming ;)
   * Returns on object of class templateIndividual. Usage instructions can be found in the inline docs for that class.
   * @param $filename the filename of the template to be parsed
   * @return object
   */
   
  function makeParser($filename)
  {
    $filename = ENANO_ROOT.'/themes/'.$this->theme.'/'.$filename;
    if(!file_exists($filename)) die('templateIndividual: file '.$filename.' does not exist');
    $code = file_get_contents($filename);
    $parser = new templateIndividualSafe($code, $this);
    return $parser;
  }
  
  /**
   * Same as $template->makeParser(), but takes a string instead of a filename.
   * @param $text the text to parse
   * @return object
   */
   
  function makeParserText($code)
  {
    $parser = new templateIndividualSafe($code, $this);
    return $parser;
  }
   
} // class template_nodb

/**
 * Identical to templateIndividual, except extends template_nodb instead of template
 * @see class template
 */
 
class templateIndividualSafe extends template_nodb {
  var $tpl_strings, $tpl_bool, $tpl_code;
  var $compiled = false;
  /**
   * Constructor.
   */
  function __construct($text, $parent)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $this->tpl_code = $text;
    $this->tpl_strings = $parent->tpl_strings;
    $this->tpl_bool = $parent->tpl_bool;
  }
  /**
   * PHP 4 constructor.
   */
  function templateIndividual($text)
  {
    $this->__construct($text);
  }
  /**
   * Assigns an array of string values to the template. Strings can be accessed from the template by inserting {KEY_NAME} in the template file.
   * @param $vars array
   */
  function assign_vars($vars)
  {
    if(is_array($this->tpl_strings))
      $this->tpl_strings = array_merge($this->tpl_strings, $vars);
    else
      $this->tpl_strings = $vars;
  }
  /**
   * Assigns an array of boolean values to the template. These can be used for <!-- IF ... --> statements.
   * @param $vars array
   */
  function assign_bool($vars)
  {
    $this->tpl_bool = array_merge($this->tpl_bool, $vars);
  }
  /**
   * Compiles and executes the template code.
   * @return string
   */
  function run()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$this->compiled)
    {
      $this->tpl_code = $this->compile_template_text($this->tpl_code);
      $this->compiled = true;
    }
    return eval($this->tpl_code);
  }
}

?>
