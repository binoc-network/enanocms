<?php

/**
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 * paths.php - The part of Enano that actually manages content. Everything related to page handling and namespaces is in here.
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 *
 * @package Enano
 * @subpackage PathManager
 * @see http://enanocms.org/Help:API_Documentation
 */
 
class pathManager {
  var $pages, $custom_page, $cpage, $page, $fullpage, $page_exists, $page_id, $namespace, $nslist, $admin_tree, $wiki_mode, $page_protected, $template_cache, $anonymous_page;
  function __construct()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $GLOBALS['paths'] =& $this;
    $this->pages = Array();
    
    // DEFINE NAMESPACES HERE
    // The key names should NOT EVER be changed, or Enano will be very broken
    $this->nslist = Array(
      'Article' =>'',
      'User'    =>'User:',
      'File'    =>'File:',
      'Help'    =>'Help:',
      'Admin'   =>'Admin:',
      'Special' =>'Special:',
      'System'  =>'Enano:',
      'Template'=>'Template:',
      'Category'=>'Category:',
      'Anonymous'=>'PhysicalRedirect:',
      'Project' =>sanitize_page_id(getConfig('site_name')).':',
      );
    
    // ACL types
    // These can also be added from within plugins
    
    $session->register_acl_type('read',                   AUTH_ALLOW,    'perm_read');
    $session->register_acl_type('post_comments',          AUTH_ALLOW,    'perm_post_comments',          Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('edit_comments',          AUTH_ALLOW,    'perm_edit_comments',          Array('post_comments'),                                   'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('edit_page',              AUTH_WIKIMODE, 'perm_edit_page',              Array('view_source'),                                     'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('view_source',            AUTH_WIKIMODE, 'perm_view_source',            Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category'); // Only used if the page is protected
    $session->register_acl_type('mod_comments',           AUTH_DISALLOW, 'perm_mod_comments',           Array('edit_comments'),                                   'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('history_view',           AUTH_WIKIMODE, 'perm_history_view',           Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('history_rollback',       AUTH_DISALLOW, 'perm_history_rollback',       Array('history_view'),                                    'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('history_rollback_extra', AUTH_DISALLOW, 'perm_history_rollback_extra', Array('history_rollback'),                                'Article|User|Project|Template|File|Help|System|Category|Special');
    $session->register_acl_type('protect',                AUTH_DISALLOW, 'perm_protect',                Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('rename',                 AUTH_WIKIMODE, 'perm_rename',                 Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('clear_logs',             AUTH_DISALLOW, 'perm_clear_logs',             Array('read', 'protect', 'even_when_protected'),          'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('vote_delete',            AUTH_ALLOW,    'perm_vote_delete',            Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('vote_reset',             AUTH_DISALLOW, 'perm_vote_reset',             Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('delete_page',            AUTH_DISALLOW, 'perm_delete_page',            Array(),                                                  'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('tag_create',             AUTH_ALLOW,    'perm_tag_create',             Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('tag_delete_own',         AUTH_ALLOW,    'perm_tag_delete_own',         Array('read', 'tag_create'),                              'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('tag_delete_other',       AUTH_DISALLOW, 'perm_tag_delete_other',       Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('set_wiki_mode',          AUTH_DISALLOW, 'perm_set_wiki_mode',          Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('password_set',           AUTH_DISALLOW, 'perm_password_set',           Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('password_reset',         AUTH_DISALLOW, 'perm_password_reset',         Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('mod_misc',               AUTH_DISALLOW, 'perm_mod_misc',               Array(),                                                  'All');
    $session->register_acl_type('edit_cat',               AUTH_WIKIMODE, 'perm_edit_cat',               Array('read'),                                            'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('even_when_protected',    AUTH_DISALLOW, 'perm_even_when_protected',    Array('edit_page', 'rename', 'mod_comments', 'edit_cat'), 'Article|User|Project|Template|File|Help|System|Category');
    $session->register_acl_type('upload_files',           AUTH_DISALLOW, 'perm_upload_files',           Array('create_page'),                                     'Article|User|Project|Template|File|Help|System|Category|Special');
    $session->register_acl_type('upload_new_version',     AUTH_WIKIMODE, 'perm_upload_new_version',     Array('upload_files'),                                    'Article|User|Project|Template|File|Help|System|Category|Special');
    $session->register_acl_type('create_page',            AUTH_WIKIMODE, 'perm_create_page',            Array(),                                                  'Article|User|Project|Template|File|Help|System|Category|Special');
    $session->register_acl_type('php_in_pages',           AUTH_DISALLOW, 'perm_php_in_pages',           Array('edit_page'),                                       'Article|User|Project|Template|File|Help|System|Category|Admin');
    $session->register_acl_type('edit_acl',               AUTH_DISALLOW, 'perm_edit_acl',               Array('read', 'post_comments', 'edit_comments', 'edit_page', 'view_source', 'mod_comments', 'history_view', 'history_rollback', 'history_rollback_extra', 'protect', 'rename', 'clear_logs', 'vote_delete', 'vote_reset', 'delete_page', 'set_wiki_mode', 'password_set', 'password_reset', 'mod_misc', 'edit_cat', 'even_when_protected', 'upload_files', 'upload_new_version', 'create_page', 'php_in_pages'));
    
    // DO NOT add new admin pages here! Use a plugin to call $paths->addAdminNode();
    $this->addAdminNode('adm_cat_general',    'adm_page_general_config', 'GeneralConfig');
    $this->addAdminNode('adm_cat_general',    'adm_page_file_uploads',   'UploadConfig');
    $this->addAdminNode('adm_cat_general',    'adm_page_file_types',     'UploadAllowedMimeTypes');
    $this->addAdminNode('adm_cat_general',    'adm_page_plugins',        'PluginManager');
    $this->addAdminNode('adm_cat_general',    'adm_page_db_backup',      'DBBackup');
    $this->addAdminNode('adm_cat_general',    'adm_page_lang_manager',   'LangManager');
    $this->addAdminNode('adm_cat_content',    'adm_page_manager',        'PageManager');
    $this->addAdminNode('adm_cat_content',    'adm_page_editor',         'PageEditor');
    $this->addAdminNode('adm_cat_content',    'adm_page_pg_groups',      'PageGroups');
    $this->addAdminNode('adm_cat_appearance', 'adm_page_themes',         'ThemeManager');
    $this->addAdminNode('adm_cat_users',      'adm_page_users',          'UserManager');
    $this->addAdminNode('adm_cat_users',      'adm_page_user_groups',    'GroupManager');
    $this->addAdminNode('adm_cat_users',      'adm_page_coppa',          'COPPA');
    $this->addAdminNode('adm_cat_users',      'adm_page_mass_email',     'MassEmail');
    $this->addAdminNode('adm_cat_security',   'adm_page_security_log',   'SecurityLog');
    $this->addAdminNode('adm_cat_security',   'adm_page_ban_control',    'BanControl');
    
    $code = $plugins->setHook('acl_rule_init');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    $this->wiki_mode = (int)getConfig('wiki_mode')=='1';
    $this->template_cache = Array();
  }
  function init()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $code = $plugins->setHook('paths_init_before');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    $e = $db->sql_query('SELECT name,urlname,namespace,special,visible,comments_on,protected,delvotes,' . "\n"
                        . '  delvote_ips,wiki_mode,password FROM '.table_prefix.'pages ORDER BY name;');
    if( !$e )
    {
      $db->_die('The error seems to have occured while selecting the page information. File: includes/paths.php; line: '.__LINE__);
    }
    while($r = $db->fetchrow())
    {
      
      $r['urlname_nons'] = $r['urlname'];
      $r['urlname'] = $this->nslist[$r['namespace']] . $r['urlname']; // Applies the User:/File:/etc prefixes to the URL names
      
      if ( $r['delvotes'] == null)
      {
        $r['delvotes'] = 0;
      }
      if ( $r['protected'] == 0 || $r['protected'] == 1 )
      {
        $r['really_protected'] = (int)$r['protected'];
      }
      else if ( $r['protected'] == 2 && getConfig('wiki_mode') == '1')
      {
        $r['really_protected'] = 1;
      }
      else if ( $r['protected'] == 2 && getConfig('wiki_mode') == '0' )
      {
        $r['really_protected'] = 0;
      }
      
      $this->pages[$r['urlname']] = $r;
      $this->pages[] =& $this->pages[$r['urlname']];
      
    }
    $db->free_result();
    if ( defined('ENANO_INTERFACE_INDEX') || defined('ENANO_INTERFACE_AJAX') || defined('IN_ENANO_UPGRADE') )
    {
      if( isset($_GET['title']) )
      {
        if ( $_GET['title'] == '' && getConfig('main_page') != '' )
        {
          $this->main_page();
        }
        if(strstr($_GET['title'], ' '))
        {
          $loc = urldecode(rawurldecode($_SERVER['REQUEST_URI']));
          $loc = str_replace(' ', '_', $loc);
          $loc = str_replace('+', '_', $loc);
          $loc = str_replace('%20', '_', $loc);
          redirect($loc, 'Redirecting...', 'Space detected in the URL, please wait whilst you are redirected', 0);
          exit;
        }
        $url_namespace_special = substr($_GET['title'], 0, strlen($this->nslist['Special']) );
        $url_namespace_template = substr($_GET['title'], 0, strlen($this->nslist['Template']) );
        if($url_namespace_special == $this->nslist['Special'] || $url_namespace_template == $this->nslist['Template'] )
        {
          $ex = explode('/', $_GET['title']);
          $this->page = $ex[0];
        }
        else
        {
          $this->page = $_GET['title'];
        }
        $this->fullpage = $_GET['title'];
      }
      elseif( isset($_SERVER['PATH_INFO']) )
      {
        $pi = explode('/', $_SERVER['PATH_INFO']);
        
        if( !isset($pi[1]) || (isset($pi[1]) && $pi[1] == '' && getConfig('main_page') != '') )
        {
          $this->main_page();
        }
        if( strstr($pi[1], ' ') )
        {
          $loc = str_replace(' ', '_', urldecode(rawurldecode($_SERVER['REQUEST_URI'])));
          $loc = str_replace('+', '_', $loc);
          $loc = str_replace('%20', '_', $loc);
          redirect($loc, 'Redirecting...', 'Please wait whilst you are redirected', 3);
          exit;
        }
        unset($pi[0]);
        if( substr($pi[1], 0, strlen($this->nslist['Special'])) == $this->nslist['Special'] || substr($pi[1], 0, strlen($this->nslist['Template'])) == $this->nslist['Template'] )
        {
          $pi2 = $pi[1];
        }
        else
        {
          $pi2 = implode('/', $pi);
        }
        $this->page = $pi2;
        $this->fullpage = implode('/', $pi);
      }
      else
      {
        $k = array_keys($_GET);
        foreach($k as $c)
        {
          if(substr($c, 0, 1) == '/')
          {
            $this->page = substr($c, 1, strlen($c));
            
            // Bugfix for apache somehow passing dots as underscores
            global $mime_types;
            
            $exts = array_keys($mime_types);
            $exts = '(' . implode('|', $exts) . ')';
            
            if ( preg_match( '#_'.$exts.'#i', $this->page ) )
            {
              $this->page = preg_replace( '#_'.$exts.'#i', '.\\1', $this->page );
            }
            
            $this->fullpage = $this->page;
            
            if(substr($this->page, 0, strlen($this->nslist['Special']))==$this->nslist['Special'] || substr($this->page, 0, strlen($this->nslist['Template']))==$this->nslist['Template'])
            {
              $ex = explode('/', $this->page);
              $this->page = $ex[0];
            }
            if(strstr($this->page, ' '))
            {
              $loc = str_replace(' ', '_', urldecode(rawurldecode($_SERVER['REQUEST_URI'])));
              $loc = str_replace('+', '_', $loc);
              $loc = str_replace('%20', '_', $loc);
              redirect($loc, 'Redirecting...', 'Space in the URL detected, please wait whilst you are redirected', 0);
              exit;
            }
            break;
          }
        }
        if(!$this->page && !($this->page == '' && getConfig('main_page') == ''))
        {
          $this->main_page();
        }
      }
    }
    else
    {
      // Starting up Enano with the API from a page that wants to do its own thing. Generate
      // metadata for an anonymous page and avoid redirection at all costs.
      if ( isset($GLOBALS['title']) )
      {
        $title =& $GLOBALS['title'];
      }
      else
      {
        $title = basename($_SERVER['SCRIPT_NAME']);
      }
      $base_uri = str_replace( scriptPath . '/', '', $_SERVER['SCRIPT_NAME'] );
      $this->page = $this->nslist['Anonymous'] . sanitize_page_id($base_uri);
      $this->fullpage = $this->nslist['Anonymous'] . sanitize_page_id($base_uri);
      $this->namespace = 'Anonymous';
      $this->cpage = array(
          'name' => $title,
          'urlname' => sanitize_page_id($base_uri),
          'namespace' => 'Anonymous',
          'special' => 1,
          'visible' => 1,
          'comments_on' => 1,
          'protected' => 1,
          'delvotes' => 0,
          'delvote_ips' => ''
        );
      $this->anonymous_page = true;
      $code = $plugins->setHook('paths_anonymous_page');
      foreach ( $code as $cmd )
      {
        eval($cmd);
      }
    }
    
    $this->page = sanitize_page_id($this->page);
    $this->fullpage = sanitize_page_id($this->fullpage);
    
    if(isset($this->pages[$this->page]))
    {
      $this->page_exists = true;
      $this->cpage = $this->pages[$this->page];
      $this->page_id =& $this->cpage['urlname_nons'];
      $this->namespace = $this->cpage['namespace'];
      if(!isset($this->cpage['wiki_mode'])) $this->cpage['wiki_mode'] = 2;
      
      // Determine the wiki mode for this page, now that we have this->cpage established
      if($this->cpage['wiki_mode'] == 2)
      {
        $this->wiki_mode = (int)getConfig('wiki_mode');
      }
      else
      {
        $this->wiki_mode = $this->cpage['wiki_mode'];
      }
      // Allow the user to create/modify his user page uncondtionally (admins can still protect the page)
      if($this->page == $this->nslist['User'].str_replace(' ', '_', $session->username))
      {
        $this->wiki_mode = true;
      }
      // And above all, if the site requires wiki mode to be off for non-logged-in users, disable it now
      if(getConfig('wiki_mode_require_login')=='1' && !$session->user_logged_in)
      {
        $this->wiki_mode = false;
      }
      if($this->cpage['protected'] == 2)
      {
        // The page is semi-protected, determine permissions
        if($session->user_logged_in && $session->reg_time + 60*60*24*4 < time()) 
        {
          $this->page_protected = 0;
        }
        else
        {
          $this->page_protected = 1;
        }
      }
      else
      {
        $this->page_protected = $this->cpage['protected'];
      }
    }
    else
    {
      $this->page_exists = false;
      $page_name = dirtify_page_id($this->page);
      $page_name = str_replace('_', ' ', $page_name);
      
      $pid_cleaned = sanitize_page_id($this->page);
      if ( $pid_cleaned != $this->page )
      {
        redirect(makeUrl($pid_cleaned), 'Sanitizer message', 'page id sanitized', 0);
      }
      
      if ( !is_array($this->cpage) )
      {
        $this->cpage = Array(
          'name'=>$page_name,
          'urlname'=>$this->page,
          'namespace'=>'Article',
          'special'=>0,
          'visible'=>0,
          'comments_on'=>1,
          'protected'=>0,
          'delvotes'=>0,
          'delvote_ips'=>'',
          'wiki_mode'=>2,
          );
      }
      // Look for a namespace prefix in the urlname, and assign a different namespace, if necessary
      $k = array_keys($this->nslist);
      for($i=0;$i<sizeof($this->nslist);$i++)
      {
        $ln = strlen($this->nslist[$k[$i]]);
        if( substr($this->page, 0, $ln) == $this->nslist[$k[$i]] )
        {
          $this->cpage['namespace'] = $k[$i];
          $this->cpage['urlname_nons'] = substr($this->page, strlen($this->nslist[$this->cpage['namespace']]), strlen($this->page));
          if(!isset($this->cpage['wiki_mode'])) 
          {
            $this->cpage['wiki_mode'] = 2;
          }
        }
      }
      $this->namespace = $this->cpage['namespace'];
      $this->page_id =& $this->cpage['urlname_nons'];
      
      if($this->namespace=='System') 
      {
        $this->cpage['protected'] = 1;
      }
      if($this->namespace == 'Special' && !$this->anonymous_page)
      {
        // Can't load nonexistent pages
        if( is_string(getConfig('main_page')) )
        {
          $main_page = makeUrl(getConfig('main_page'));
        }
        else
        {
          $main_page = makeUrl($this->pages[0]['urlname']);
        }
        $sp_link = '<a href="' . makeUrlNS('Special', 'SpecialPages') . '">here</a>';
        redirect($main_page, 'Can\'t load special page', 'The special page you requested could not be found. This may be due to a plugin failing to load. A list of all special pages on this website can be viewed '.$sp_link.'. You will be redirected to the main page in 15 seconds.', 14);
        exit;
      }
      // Allow the user to create/modify his user page uncondtionally (admins can still protect the page)
      if($this->page == $this->nslist['User'].str_replace(' ', '_', $session->username)) 
      {
        $this->wiki_mode = true;
      }
    }
    // This is used in the admin panel to keep track of form submission targets
    $this->cpage['module'] = $this->cpage['urlname'];
    
    // Page is set up, call any hooks
    $code = $plugins->setHook('page_set');
    foreach ( $code as $cmd )
    {
      eval($cmd);
    }
    
    $session->init_permissions();
    profiler_log('Paths and CMS core initted');
  }
  
  function add_page($flags)
  {
    global $lang;
    $flags['urlname_nons'] = $flags['urlname'];
    $flags['urlname'] = $this->nslist[$flags['namespace']] . $flags['urlname']; // Applies the User:/File:/etc prefixes to the URL names
    
    if ( is_object($lang) )
    {
      if ( preg_match('/^[a-z0-9]+_[a-z0-9_]+$/', $flags['name']) )
        $flags['name'] = $lang->get($flags['name']);
    }
    
    $pages_len = sizeof($this->pages)/2;
    $this->pages[$pages_len] = $flags;
    $this->pages[$flags['urlname']] =& $this->pages[$pages_len];
  }
  
  function main_page()
  {
    if( is_string(getConfig('main_page')) )
    {
      $main_page = makeUrl(getConfig('main_page'));
    }
    else
    {
      $main_page = makeUrl($this->pages[0]['urlname']);
    }
    redirect($main_page, 'Redirecting...', 'Invalid request, redirecting to main page', 0);
    exit;
  }
  
  function sysmsg($n)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $q = $db->sql_query('SELECT page_text, char_tag FROM '.table_prefix.'page_text WHERE page_id=\''.$db->escape(sanitize_page_id($n)).'\' AND namespace=\'System\'');
    if( !$q )
    {
      $db->_die('Error during generic selection of system page data.');
    }
    if($db->numrows() < 1)
    {
      return false;
      //$db->_die('Error during generic selection of system page data: there were no rows in the text table that matched the page text query.');
    }
    $r = $db->fetchrow();
    $db->free_result();
    $message = $r['page_text'];
    
    $message = preg_replace('/<noinclude>(.*?)<\/noinclude>/is', '', $message);
    $message = preg_replace('/<nodisplay>(.*?)<\/nodisplay>/is', '\\1', $message);
    
    return $message;
  }
  function get_pageid_from_url()
  {
    if(isset($_GET['title']))
    {
      if( $_GET['title'] == '' && getConfig('main_page') != '' )
      {
        $this->main_page();
      }
      if(strstr($_GET['title'], ' '))
      {
        $loc = urldecode(rawurldecode($_SERVER['REQUEST_URI']));
        $loc = str_replace(' ', '_', $loc);
        $loc = str_replace('+', '_', $loc);
        header('Location: '.$loc);
        exit;
      }
      $ret = $_GET['title'];
      if ( substr($ret, 0, strlen($this->nslist['Special'])) === $this->nslist['Special'] ||
           substr($ret, 0, strlen($this->nslist['Admin'])) === $this->nslist['Admin'] )
      {
        list($ret) = explode('/', $ret);
      }
    }
    elseif(isset($_SERVER['PATH_INFO']))
    {
      $pi = explode('/', $_SERVER['PATH_INFO']);
      
      if(!isset($pi[1]) || (isset($pi[1]) && $pi[1] == ''))
      {
        return false;
      }
      
      if(strstr($pi[1], ' '))
      {
        $loc = urldecode(rawurldecode($_SERVER['REQUEST_URI']));
        $loc = str_replace(' ', '_', $loc);
        $loc = str_replace('+', '_', $loc);
        header('Location: '.$loc);
        exit;
      }
      if( !( substr($pi[1], 0, strlen($this->nslist['Special'])) == $this->nslist['Special'] ) )
      {
        unset($pi[0]);
        $pi[1] = implode('/', $pi);
      }
      $ret = $pi[1];
    }
    else
    {
      $k = array_keys($_GET);
      foreach($k as $c)
      {
        if(substr($c, 0, 1) == '/')
        {
          $ret = substr($c, 1, strlen($c));
          if(substr($ret, 0, strlen($this->nslist['Special'])) == $this->nslist['Special'] ||
             substr($ret, 0, strlen($this->nslist['Admin'])) == $this->nslist['Admin'])
          {
            $ret = explode('/', $ret);
            $ret = $ret[0];
          }
          break;
        }
      }
    }
    
    return ( isset($ret) ) ? $ret : false;
  }
  // Parses a (very carefully formed) array into Javascript code compatible with the Tigra Tree Menu used in the admin menu
  function parseAdminTree() 
  {
    global $lang;
    
    $k = array_keys($this->admin_tree);
    $i = 0;
    $ret = '';
    $ret .= "var TREE_ITEMS = [\n  ['" . $lang->get('adm_btn_home') . "', 'javascript:ajaxPage(\'".$this->nslist['Admin']."Home\');',\n    ";
    foreach($k as $key)
    {
      $i++;
      $name = ( preg_match('/^[a-z0-9_]+$/', $key) ) ? $lang->get($key) : $key;
      $ret .= "['".$name."', 'javascript:trees[0].toggle($i)', \n";
      foreach($this->admin_tree[$key] as $c)
      {
        $i++;
        $name = ( preg_match('/^[a-z0-9_]+$/', $key) ) ? $lang->get($c['name']) : $c['name'];
        
        $ret .= "        ['".$name."', 'javascript:ajaxPage(\\'".$this->nslist['Admin'].$c['pageid']."\\');'],\n";
      }
      $ret .= "      ],\n";
    }
    $ret .= "    ['" . $lang->get('adm_btn_logout') . "', 'javascript:ajaxPage(\\'".$this->nslist['Admin']."AdminLogout\\');'],\n";
    $ret .= "    ['<span id=\\'keepalivestat\\'>" . $lang->get('adm_btn_keepalive_loading') . "</span>', 'javascript:ajaxToggleKeepalive();', 
                   ['" . $lang->get('adm_btn_keepalive_about') . "', 'javascript:aboutKeepAlive();']
                 ],\n";
    // I used this while I painstakingly wrote the Runt code that auto-expands certain nodes based on the value of a bitfield stored in a cookie. *shudders*
    // $ret .= "    ['(debug) Clear menu bitfield', 'javascript:createCookie(\\'admin_menu_state\\', \\'1\\', 365);'],\n";
    $ret .= "]\n];";
    return $ret;
  }
  function addAdminNode($section, $page_title, $url)
  {
    if(!isset($this->admin_tree[$section]))
    {
      $this->admin_tree[$section] = Array();
    }
    $this->admin_tree[$section][] = Array(
        'name'=>$page_title,
        'pageid'=>$url
      );
  }
  function getParam($id = 0)
  {
    // using !empty here is a bugfix for IIS 5.x on Windows 2000 Server
    // It may affect other IIS versions as well
    if(isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO']))
    {
      $pi = explode('/', $_SERVER['PATH_INFO']);
      $id = $id + 2;
      return isset($pi[$id]) ? $pi[$id] : false;
    }
    else if( isset($_GET['title']) )
    {
      $pi = explode('/', $_GET['title']);
      $id = $id + 1;
      return isset($pi[$id]) ? $pi[$id] : false;
    }
    else
    {
      $k = array_keys($_GET);
      foreach($k as $c)
      {
        if(substr($c, 0, 1) == '/')
        {
          // Bugfix for apache somehow passing dots as underscores
          global $mime_types;
          $exts = array_keys($mime_types);
          $exts = '(' . implode('|', $exts) . ')';
          if ( preg_match( '#_'.$exts.'#i', $c ) )
            $c = preg_replace( '#_'.$exts.'#i', '.\\1', $c );
          
          $pi = explode('/', $c);
          $id = $id + 2;
          return isset($pi[$id]) ? $pi[$id] : false;
        }
      }
      return false;
    }
  }
  
  function getAllParams()
  {
    // using !empty here is a bugfix for IIS 5.x on Windows 2000 Server
    // It may affect other IIS versions as well
    if(isset($_SERVER['PATH_INFO']) && !empty($_SERVER['PATH_INFO']))
    {
      $pi = explode('/', $_SERVER['PATH_INFO']);
      unset($pi[0], $pi[1]);
      return implode('/', $pi);
    }
    else if( isset($_GET['title']) )
    {
      $pi = explode('/', $_GET['title']);
      unset($pi[0]);
      return implode('/', $pi);
    }
    else
    {
      $k = array_keys($_GET);
      foreach($k as $c)
      {
        if(substr($c, 0, 1) == '/')
        {
          // Bugfix for apache somehow passing dots as underscores
          global $mime_types;
          $exts = array_keys($mime_types);
          $exts = '(' . implode('|', $exts) . ')';
          if ( preg_match( '#_'.$exts.'#i', $c ) )
            $c = preg_replace( '#_'.$exts.'#i', '.\\1', $c );
          
          $pi = explode('/', $c);
          unset($pi[0], $pi[1]);
          return implode('/', $pi);
        }
      }
      return false;
    }
  }
  
  /**
   * Creates a new namespace in memory
   * @param string $id the namespace ID
   * @param string $prefix the URL prefix, must not be blank or already used
   * @return bool true on success false on failure
   */
  
  function create_namespace($id, $prefix)
  {
    if(in_array($prefix, $this->nslist))
    {
      // echo '<b>Warning:</b> pathManager::create_namespace: Prefix "'.$prefix.'" is already taken<br />';
      return false;
    }
    if( isset($this->nslist[$id]) )
    {
      // echo '<b>Warning:</b> pathManager::create_namespace: Namespace ID "'.$prefix.'" is already taken<br />';
      return false;
    }
    $this->nslist[$id] = $prefix;
  }
  
  /**
   * Fetches the page texts for searching
   */
   
  function fetch_page_search_texts()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $texts = Array();
    $q = $db->sql_query('SELECT t.page_id,t.namespace,t.page_text,t.char_tag FROM '.table_prefix.'page_text AS t
                           LEFT JOIN '.table_prefix.'pages AS p
                             ON t.page_id=p.urlname
                           WHERE p.namespace=t.namespace
                             AND ( p.password=\'\' OR p.password=\'da39a3ee5e6b4b0d3255bfef95601890afd80709\' )
                             AND p.visible=1;'); // Only indexes "visible" pages
    
    if( !$q )
    {
      return false;
    }
    while($row = $db->fetchrow())
    {
      $pid = $this->nslist[$row['namespace']] . $row['page_id'];
      $texts[$pid] = $row['page_text'];
    }
    $db->free_result();
    
    return $texts;
  }
  
  /**
   * Generates an SQL query to grab all of the text
   */
   
  function fetch_page_search_resource()
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    // sha1('') returns "da39a3ee5e6b4b0d3255bfef95601890afd80709"
    
    $concat_column = ( ENANO_DBLAYER == 'MYSQL' ) ?
      'CONCAT(\'ns=\',t.namespace,\';pid=\',t.page_id)' :
      "'ns=' || t.namespace || ';pid=' || t.page_id";
    
    $texts = 'SELECT t.page_text, ' . $concat_column . ' AS page_idstring, t.page_id, t.namespace FROM '.table_prefix.'page_text AS t
                           LEFT JOIN '.table_prefix.'pages AS p
                             ON ( t.page_id=p.urlname AND t.namespace=p.namespace )
                           WHERE p.namespace=t.namespace
                             AND ( p.password=\'\' OR p.password=\'da39a3ee5e6b4b0d3255bfef95601890afd80709\' )
                             AND p.visible=1;'; // Only indexes "visible" pages
    return $texts;
  }
  
  /**
   * Rebuilds the search index
   * @param bool If true, prints out status messages
   */
   
  function rebuild_search_index($verbose = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $search = new Searcher();
    if ( $verbose )
    {
      echo '<p>';
    }
    $texts = Array();
    $textq = $db->sql_unbuffered_query($this->fetch_page_search_resource());
    if(!$textq) $db->_die('');
    while($row = $db->fetchrow())
    {
      if ( $verbose )
      {
        ob_start();
        echo "Indexing page " . $this->nslist[$row['namespace']] . sanitize_page_id($row['page_id']) . "<br />";
        ob_flush();
        while (@ob_end_flush());
        flush();
      }
      if ( isset($this->nslist[$row['namespace']]) )
      {
        $idstring = $this->nslist[$row['namespace']] . sanitize_page_id($row['page_id']);
        if ( isset($this->pages[$idstring]) )
        {
          $page = $this->pages[$idstring];
        }
        else
        {
          $page = array('name' => dirtify_page_id($row['page_id']));
        }
      }
      else
      {
        $page = array('name' => dirtify_page_id($row['page_id']));
      }
      $texts[(string)$row['page_idstring']] = $row['page_text'] . ' ' . $page['name'];
    }
    if ( $verbose )
    {
      ob_start();
      echo "Calculating word list...";
      ob_flush();
      while (@ob_end_flush());
      flush();
    }
    $search->buildIndex($texts);
    if ( $verbose )
    {
      echo '</p>';
    }
    // echo '<pre>'.print_r($search->index, true).'</pre>';
    // return;
    $q = $db->sql_query('DELETE FROM '.table_prefix.'search_index');
    if(!$q) return false;
    $secs = Array();
    $q = 'INSERT INTO '.table_prefix.'search_index(word,page_names) VALUES';
    foreach($search->index as $word => $pages)
    {
      $secs[] = '(\''.$db->escape($word).'\', \''.$db->escape($pages).'\')';
    }
    $q .= implode(',', $secs);
    unset($secs);
    $q .= ';';
    $result = $db->sql_query($q);
    $db->free_result();
    if($result)
      return true;
    else
      $db->_die('The search index was trying to rebuild itself when the error occured.');
  }
  
  /**
   * Partially rebuilds the search index, removing/inserting entries only for the current page
   * @param string $page_id
   * @param string $namespace
   */
  
  function rebuild_page_index($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    if(!$db->sql_query('SELECT page_text FROM '.table_prefix.'page_text
      WHERE page_id=\''.$db->escape($page_id).'\' AND namespace=\''.$db->escape($namespace).'\';'))
    {
      return $db->get_error();
    }
    if ( $db->numrows() < 1 )
      return 'E: No rows';
    $idstring = $this->nslist[$namespace] . sanitize_page_id($page_id);
    if ( !isset($this->pages[$idstring]) )
    {
      return 'E: Can\'t find page metadata';
    }
    $row = $db->fetchrow();
    $db->free_result();
    $search = new Searcher();
    $search->buildIndex(Array("ns={$namespace};pid={$page_id}"=>$row['page_text'] . ' ' . $this->pages[$idstring]['name']));
    $new_index = $search->index;
    
    if ( ENANO_DBLAYER == 'MYSQL' )
    {
      $keys = array_keys($search->index);
      foreach($keys as $i => $k)
      {
        $c =& $keys[$i];
        $c = hexencode($c, '', '');
      }
      $keys = "word=0x" . implode ( " OR word=0x", $keys ) . "";
    }
    else
    {
      $keys = array_keys($search->index);
      foreach($keys as $i => $k)
      {
        $c =& $keys[$i];
        $c = $db->escape($c);
      }
      $keys = "word='" . implode ( "' OR word='", $keys ) . "'";
    }
    
    $query = $db->sql_query('SELECT word,page_names FROM '.table_prefix.'search_index WHERE '.$keys.';');
    
    while($row = $db->fetchrow())
    {
      $row['word'] = rtrim($row['word'], "\0");
      $new_index[ $row['word'] ] = $row['page_names'] . ',' . $search->index[ $row['word'] ];
    }
    $db->free_result();
    
    $db->sql_query('DELETE FROM '.table_prefix.'search_index WHERE '.$keys.';');
    
    $secs = Array();
    $q = 'INSERT INTO '.table_prefix.'search_index(word,page_names) VALUES';
    foreach($new_index as $word => $pages)
    {
      $secs[] = '(\''.$db->escape($word).'\', \''.$db->escape($pages).'\')';
    }
    $q .= implode(',', $secs);
    unset($secs);
    $q .= ';';
    if(!$db->check_query($q))
    {
      die('BUG: PathManager::rebuild_page_index: Query rejected by SQL parser:<pre>'.$q.'</pre>');
    }
    $result = $db->sql_query($q);
    if($result)
      return true;
    else
      $db->_die('The search index was trying to rebuild itself when the error occured.');
    
  }
  
  /**
   * Creates an instance of the Searcher class, including index info
   * @return object
   */
   
  function makeSearcher($match_case = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $search = new Searcher();
    $q = $db->sql_query('SELECT word,page_names FROM '.table_prefix.'search_index;');
    if(!$q)
    {
      echo $db->get_error();
      return false;
    }
    $idx = Array();
    while($row = $db->fetchrow($q))
    {
      $row['word'] = rtrim($row['word'], "\0");
      $idx[$row['word']] = $row['page_names'];
    }
    $db->free_result();
    $search->index = $idx;
    if($match_case)
      $search->match_case = true;
    return $search;
  }
  
  /**
   * Creates an associative array filled with the values of all the page titles
   * @return array
   */
   
  function get_page_titles()
  {
    $texts = Array();
    for ( $i = 0; $i < sizeof($this->pages) / 2; $i++ )
    {
      $texts[$this->pages[$i]['urlname']] = $this->pages[$i]['name'];
    }
    return $texts;
  }
  
  /**
   * Creates an instance of the Searcher class, including index info for page titles
   * @return object
   */
   
  function makeTitleSearcher($match_case = false)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    $search = new Searcher();
    $texts = $this->get_page_titles();
    $search->buildIndex($texts);
    if($match_case)
      $search->match_case = true;
    return $search;
  }
  
  /**
   * Returns a list of groups that a given page is a member of.
   * @param string Page ID
   * @param string Namespace
   * @return array
   */
  
  function get_page_groups($page_id, $namespace)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    static $cache = array();
    
    if ( count($cache) == 0 )
    {
      foreach ( $this->nslist as $key => $_ )
      {
        $cache[$key] = array();
      }
    }
    
    if ( !isset($this->nslist[$namespace]) )
      die('$paths->get_page_groups(): HACKING ATTEMPT: namespace "'. htmlspecialchars($namespace) .'" doesn\'t exist');
    
    $page_id_unescaped = $paths->nslist[$namespace] .
                         dirtify_page_id($page_id);
    $page_id_str       = $paths->nslist[$namespace] .
                         sanitize_page_id($page_id);
    
    $page_id = $db->escape(sanitize_page_id($page_id));
    
    if ( isset($cache[$namespace][$page_id]) )
    {
      return $cache[$namespace][$page_id];
    }
    
    $group_list = array();
    
    // What linked categories have this page?
    $q = $db->sql_unbuffered_query('SELECT g.pg_id, g.pg_type, g.pg_target FROM '.table_prefix.'page_groups AS g
  LEFT JOIN '.table_prefix.'categories AS c
    ON ( ( c.category_id = g.pg_target AND g.pg_type = ' . PAGE_GRP_CATLINK . ' ) OR c.category_id IS NULL )
  LEFT JOIN '.table_prefix.'page_group_members AS m
    ON ( ( g.pg_id = m.pg_id AND g.pg_type = ' . PAGE_GRP_NORMAL . ' ) OR ( m.pg_id IS NULL ) )
  LEFT JOIN '.table_prefix.'tags AS t
    ON ( ( t.tag_name = g.pg_target AND pg_type = ' . PAGE_GRP_TAGGED . ' ) OR t.tag_name IS NULL )
  WHERE
    ( c.page_id=\'' . $page_id . '\' AND c.namespace=\'' . $namespace . '\' ) OR
    ( t.page_id=\'' . $page_id . '\' AND t.namespace=\'' . $namespace . '\' ) OR
    ( m.page_id=\'' . $page_id . '\' AND m.namespace=\'' . $namespace . '\' ) OR
    ( g.pg_type = ' . PAGE_GRP_REGEX . ' );');
    if ( !$q )
      $db->_die();
    
    while ( $row = $db->fetchrow() )
    {
      if ( $row['pg_type'] == PAGE_GRP_REGEX )
      {
        //echo "&lt;debug&gt; matching page " . htmlspecialchars($page_id_unescaped) . " against regex <tt>" . htmlspecialchars($row['pg_target']) . "</tt>.";
        if ( @preg_match($row['pg_target'], $page_id_unescaped) || @preg_match($row['pg_target'], $page_id_str) )
        {
          //echo "..matched";
          $group_list[] = $row['pg_id'];
        }
        //echo "<br />";
      }
      else
      {
        $group_list[] = $row['pg_id'];
      }
    }
    
    $db->free_result();
    
    $cache[$namespace][$page_id] = $group_list;
    
    return $group_list;
    
  }
  
}

?>
