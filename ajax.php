<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
  define('ENANO_INTERFACE_AJAX', '');
 
  // fillusername should be done without the help of the rest of Enano - all we need is the DBAL
  if ( isset($_GET['_mode']) && $_GET['_mode'] == 'fillusername' )
  {
    // setup and load a very basic, specialized instance of the Enano API
    function microtime_float()
    {
      list($usec, $sec) = explode(" ", microtime());
      return ((float)$usec + (float)$sec);
    }
    // Determine directory (special case for development servers)
    if ( strpos(__FILE__, '/repo/') && file_exists('.enanodev') )
    {
      $filename = str_replace('/repo/', '/', __FILE__);
    }
    else
    {
      $filename = __FILE__;
    }
    define('ENANO_ROOT', dirname($filename));
    require(ENANO_ROOT.'/includes/functions.php');
    require(ENANO_ROOT.'/includes/dbal.php');
    require(ENANO_ROOT.'/includes/json2.php');
    
    require(ENANO_ROOT . '/config.php');
    unset($dbuser, $dbpasswd);
    if ( !isset($dbdriver) )
      $dbdriver = 'mysql';
    
    $db = new $dbdriver();
    
    $db->connect();
    
    // result is sent using JSON
    $return = Array(
        'mode' => 'success',
        'users_real' => Array()
      );
    
    // should be connected to the DB now
    $name = (isset($_GET['name'])) ? $db->escape($_GET['name']) : false;
    if ( !$name )
    {
      $return = array(
        'mode' => 'error',
        'error' => 'Invalid URI'
      );
      die( enano_json_encode($return) );
    }
    $allowanon = ( isset($_GET['allowanon']) && $_GET['allowanon'] == '1' ) ? '' : ' AND user_id > 1';
    $q = $db->sql_query('SELECT username FROM '.table_prefix.'users WHERE ' . ENANO_SQLFUNC_LOWERCASE . '(username) LIKE ' . ENANO_SQLFUNC_LOWERCASE . '(\'%'.$name.'%\')' . $allowanon . ' ORDER BY username ASC;');
    if ( !$q )
    {
      $db->die_json();
    }
    $i = 0;
    while($r = $db->fetchrow())
    {
      $return['users_real'][] = $r['username'];
      $i++;
    }
    $db->free_result();
    
    // all done! :-)
    $db->close();
    
    echo enano_json_encode( $return );
    
    exit;
  }
 
  require('includes/common.php');
  
  global $db, $session, $paths, $template, $plugins; // Common objects
  if(!isset($_GET['_mode'])) die('This script cannot be accessed directly.');
  
  $_ob = '';
  
  switch($_GET['_mode']) {
    case "checkusername":
      echo PageUtils::checkusername($_GET['name']);
      break;
    case "getsource":
      header('Content-type: text/plain');
      $password = ( isset($_GET['pagepass']) ) ? $_GET['pagepass'] : false;
      $revid = ( isset($_GET['revid']) ) ? intval($_GET['revid']) : 0;
      $page = new PageProcessor($paths->page_id, $paths->namespace, $revid);
      $page->password = $password;
      $have_draft = false;
      if ( $src = $page->fetch_source() )
      {
        $allowed = true;
        $q = $db->sql_query('SELECT author, time_id, page_text, edit_summary FROM ' . table_prefix . 'logs WHERE log_type = \'page\' AND action = \'edit\'
                               AND page_id = \'' . $db->escape($paths->page_id) . '\'
                               AND namespace = \'' . $db->escape($paths->namespace) . '\'
                               AND is_draft = 1;');
        if ( !$q )
          $db->die_json();
        
        if ( $db->numrows() > 0 )
        {
          $have_draft = true;
        }
      }
      else if ( $src !== false )
      {
        $allowed = true;
        $src = '';
      }
      else
      {
        $allowed = false;
        $src = '';
      }
      
      $auth_edit = ( $session->get_permissions('edit_page') && ( $session->get_permissions('even_when_protected') || !$paths->page_protected ) );
      $auth_wysiwyg = ( $session->get_permissions('edit_wysiwyg') );
      
      $return = array(
          'mode' => 'editor',
          'src' => $src,
          'auth_view_source' => $allowed,
          'auth_edit' => $auth_edit,
          'time' => time(),
          'require_captcha' => false,
          'allow_wysiwyg' => $auth_wysiwyg,
          'revid' => $revid,
          'have_draft' => false
        );
      
      if ( $have_draft )
      {
        $row = $db->fetchrow($q);
        $return['have_draft'] = true;
        $return['draft_author'] = $row['author'];
        $return['draft_time'] = enano_date('d M Y h:i a', intval($row['time_id']));
        if ( isset($_GET['get_draft']) && @$_GET['get_draft'] === '1' )
        {
          $return['src'] = $row['page_text'];
          $return['edit_summary'] = $row['edit_summary'];
        }
      }
      
      if ( $revid > 0 )
      {
        // Retrieve information about this revision and the current one
        $q = $db->sql_query('SELECT l1.author AS currentrev_author, l2.author AS oldrev_author FROM ' . table_prefix . 'logs AS l1
  LEFT JOIN ' . table_prefix . 'logs AS l2
    ON ( l2.time_id = ' . $revid . '
         AND l2.log_type  = \'page\'
         AND l2.action    = \'edit\'
         AND l2.page_id   = \'' . $db->escape($paths->page_id)   . '\'
         AND l2.namespace = \'' . $db->escape($paths->namespace) . '\'
        )
  WHERE l1.log_type  = \'page\'
    AND l1.action    = \'edit\'
    AND l1.page_id   = \'' . $db->escape($paths->page_id)   . '\'
    AND l1.namespace = \'' . $db->escape($paths->namespace) . '\'
    AND l1.time_id >= ' . $revid . '
  ORDER BY l1.time_id DESC;');
        if ( !$q )
          $db->die_json();
        
        $rev_count = $db->numrows() - 1;
        if ( $rev_count == -1 )
        {
          $return = array(
              'mode' => 'error',
              'error' => '[Internal] No rows returned by revision info query. SQL:<pre>' . $db->latest_query . '</pre>'
            );
        }
        else
        {
          $row = $db->fetchrow();
          $return['undo_info'] = array(
            'old_author'     => $row['oldrev_author'],
            'current_author' => $row['currentrev_author'],
            'undo_count'     => $rev_count
          );
        }
      }
      
      if ( $auth_edit && !$session->user_logged_in && getConfig('guest_edit_require_captcha') == '1' )
      {
        $return['require_captcha'] = true;
        $return['captcha_id'] = $session->make_captcha();
      }
      
      $template->load_theme();
      $return['toolbar_templates'] = $template->extract_vars('toolbar.tpl');
      
      echo enano_json_encode($return);
      break;
    case "getpage":
      // echo PageUtils::getpage($paths->page, false, ( (isset($_GET['oldid'])) ? $_GET['oldid'] : false ));
      $revision_id = ( (isset($_GET['oldid'])) ? intval($_GET['oldid']) : 0 );
      $page = new PageProcessor( $paths->page_id, $paths->namespace, $revision_id );
      
      $pagepass = ( isset($_REQUEST['pagepass']) ) ? $_REQUEST['pagepass'] : '';
      $page->password = $pagepass;
            
      $page->send();
      break;
    case "savepage":
      $summ = ( isset($_POST['summary']) ) ? $_POST['summary'] : '';
      $minor = isset($_POST['minor']);
      $e = PageUtils::savepage($paths->page_id, $paths->namespace, $_POST['text'], $summ, $minor);
      if ( $e == 'good' )
      {
        $page = new PageProcessor($paths->page_id, $paths->namespace);
        $page->send();
      }
      else
      {
        echo '<p>Error saving the page: '.$e.'</p>';
      }
      break;
    case "savepage_json":
      header('Content-type: application/json');
      if ( !isset($_POST['r']) )
        die('Invalid request [1]');
      
      $request = enano_json_decode($_POST['r']);
      if ( !isset($request['src']) || !isset($request['summary']) || !isset($request['minor_edit']) || !isset($request['time']) || !isset($request['draft']) )
        die('Invalid request [2]<pre>' . htmlspecialchars(print_r($request, true)) . '</pre>');
      
      $time = intval($request['time']);
      
      if ( $request['draft'] )
      {
        //
        // The user wants to save a draft version of the page.
        //
        
        // Delete any draft copies if they exist
        $q = $db->sql_query('DELETE FROM ' . table_prefix . 'logs WHERE log_type = \'page\' AND action = \'edit\'
                               AND page_id = \'' . $db->escape($paths->page_id) . '\'
                               AND namespace = \'' . $db->escape($paths->namespace) . '\'
                               AND is_draft = 1;');
        if ( !$q )
          $db->die_json();
        
        $src = RenderMan::preprocess_text($request['src'], false, false);
        
        // Save the draft
        $q = $db->sql_query('INSERT INTO ' . table_prefix . 'logs ( log_type, action, page_id, namespace, author, edit_summary, page_text, is_draft, time_id )
                               VALUES (
                                 \'page\',
                                 \'edit\',
                                 \'' . $db->escape($paths->page_id) . '\',
                                 \'' . $db->escape($paths->namespace) . '\',
                                 \'' . $db->escape($session->username) . '\',
                                 \'' . $db->escape($request['summary']) . '\',
                                 \'' . $db->escape($src) . '\',
                                 1,
                                 ' . time() . '
                               );');
        
        // Done!
        $return = array(
            'mode' => 'success',
            'is_draft' => true
          );
      }
      else
      {
        // Verify that no edits have been made since the editor was requested
        $q = $db->sql_query('SELECT time_id, author FROM ' . table_prefix . "logs WHERE log_type = 'page' AND action = 'edit' AND page_id = '{$paths->page_id}' AND namespace = '{$paths->namespace}' AND is_draft != 1 ORDER BY time_id DESC LIMIT 1;");
        if ( !$q )
          $db->die_json();
        
        $row = $db->fetchrow();
        $db->free_result();
        
        if ( $row['time_id'] > $time )
        {
          $return = array(
            'mode' => 'obsolete',
            'author' => $row['author'],
            'date_string' => enano_date('d M Y h:i a', $row['time_id']),
            'time' => $row['time_id'] // time() ???
            );
          echo enano_json_encode($return);
          break;
        }
        
        // Verify captcha, if needed
        if ( !$session->user_logged_in && getConfig('guest_edit_require_captcha') == '1' )
        {
          if ( !isset($request['captcha_id']) || !isset($request['captcha_code']) )
          {
            die('Invalid request, need captcha metadata');
          }
          $code_correct = strtolower($session->get_captcha($request['captcha_id']));
          $code_input = strtolower($request['captcha_code']);
          if ( $code_correct !== $code_input )
          {
            $return = array(
              'mode' => 'errors',
              'errors' => array($lang->get('editor_err_captcha_wrong')),
              'new_captcha' => $session->make_captcha()
            );
            echo enano_json_encode($return);
            break;
          }
        }
        
        // Verification complete. Start the PageProcessor and let it do the dirty work for us.
        $page = new PageProcessor($paths->page_id, $paths->namespace);
        if ( $page->update_page($request['src'], $request['summary'], ( $request['minor_edit'] == 1 )) )
        {
          $return = array(
              'mode' => 'success',
              'is_draft' => false
            );
        }
        else
        {
          $errors = array();
          while ( $err = $page->pop_error() )
          {
            $errors[] = $err;
          }
          $return = array(
            'mode' => 'errors',
            'errors' => array_values($errors)
            );
          if ( !$session->user_logged_in && getConfig('guest_edit_require_captcha') == '1' )
          {
            $return['new_captcha'] = $session->make_captcha();
          }
        }
        
        // If this is based on a draft version, delete the draft - we no longer need it.
        if ( @$request['used_draft'] )
        {
          $q = $db->sql_query('DELETE FROM ' . table_prefix . 'logs WHERE log_type = \'page\' AND action = \'edit\'
                                 AND page_id = \'' . $db->escape($paths->page_id) . '\'
                                 AND namespace = \'' . $db->escape($paths->namespace) . '\'
                                 AND is_draft = 1;');
        }
      }
      
      echo enano_json_encode($return);
      
      break;
    case "diff_cur":
      
      // Lie about our content type to fool ad scripts
      header('Content-type: application/xhtml+xml');
      
      if ( !isset($_POST['text']) )
        die('Invalid request');
      
      $page = new PageProcessor($paths->page_id, $paths->namespace);
      if ( !($src = $page->fetch_source()) )
      {
        die('Access denied');
      }
      
      $diff = RenderMan::diff($src, $_POST['text']);
      if ( $diff == '<table class="diff"></table>' )
      {
        $diff = '<p>' . $lang->get('editor_msg_diff_empty') . '</p>';
      }
      
      echo '<div class="info-box">' . $lang->get('editor_msg_diff') . '</div>';
      echo $diff;
      
      break;
    case "protect":
      echo PageUtils::protect($paths->page_id, $paths->namespace, (int)$_POST['level'], $_POST['reason']);
      break;
    case "histlist":
      echo PageUtils::histlist($paths->page_id, $paths->namespace);
      break;
    case "rollback":
      echo PageUtils::rollback( (int)$_GET['id'] );
      break;
    case "comments":
      $comments = new Comments($paths->page_id, $paths->namespace);
      if ( isset($_POST['data']) )
      {
        $comments->process_json($_POST['data']);
      }
      else
      {
        die('{ "mode" : "error", "error" : "No input" }');
      }
      break;
    case "rename":
      echo PageUtils::rename($paths->page_id, $paths->namespace, $_POST['newtitle']);
      break;
    case "flushlogs":
      echo PageUtils::flushlogs($paths->page_id, $paths->namespace);
      break;
    case "deletepage":
      $reason = ( isset($_POST['reason']) ) ? $_POST['reason'] : false;
      if ( empty($reason) )
        die($lang->get('page_err_need_reason'));
      echo PageUtils::deletepage($paths->page_id, $paths->namespace, $reason);
      break;
    case "delvote":
      echo PageUtils::delvote($paths->page_id, $paths->namespace);
      break;
    case "resetdelvotes":
      echo PageUtils::resetdelvotes($paths->page_id, $paths->namespace);
      break;
    case "getstyles":
      echo PageUtils::getstyles($_GET['id']);
      break;
    case "catedit":
      echo PageUtils::catedit($paths->page_id, $paths->namespace);
      break;
    case "catsave":
      echo PageUtils::catsave($paths->page_id, $paths->namespace, $_POST);
      break;
    case "setwikimode":
      echo PageUtils::setwikimode($paths->page_id, $paths->namespace, (int)$_GET['mode']);
      break;
    case "setpass":
      echo PageUtils::setpass($paths->page_id, $paths->namespace, $_POST['password']);
      break;
    case "fillusername":
      break;
    case "fillpagename":
      $name = (isset($_GET['name'])) ? $_GET['name'] : false;
      if(!$name) die('userlist = new Array(); namelist = new Array(); errorstring=\'Invalid URI\'');
      $nd = RenderMan::strToPageID($name);
      $c = 0;
      $u = Array();
      $n = Array();
      
      $name = sanitize_page_id($name);
      $name = str_replace('_', ' ', $name);
      
      for($i=0;$i<sizeof($paths->pages)/2;$i++)
      {
        if( ( 
            preg_match('#'.preg_quote($name).'(.*)#i', $paths->pages[$i]['name']) ||
            preg_match('#'.preg_quote($name).'(.*)#i', $paths->pages[$i]['urlname']) ||
            preg_match('#'.preg_quote($name).'(.*)#i', $paths->pages[$i]['urlname_nons']) ||
            preg_match('#'.preg_quote(str_replace(' ', '_', $name)).'(.*)#i', $paths->pages[$i]['name']) ||
            preg_match('#'.preg_quote(str_replace(' ', '_', $name)).'(.*)#i', $paths->pages[$i]['urlname']) ||
            preg_match('#'.preg_quote(str_replace(' ', '_', $name)).'(.*)#i', $paths->pages[$i]['urlname_nons'])
            ) &&
           ( ( $nd[1] != 'Article' && $paths->pages[$i]['namespace'] == $nd[1] ) || $nd[1] == 'Article' )
            && $paths->pages[$i]['visible']
           )
        {
          $c++;
          $u[] = $paths->pages[$i]['name'];
          $n[] = $paths->pages[$i]['urlname'];
        }
      }
      if($c > 0)
      {
        echo 'userlist = new Array(); namelist = new Array(); errorstring = false; '."\n";
        for($i=0;$i<sizeof($u);$i++) // Can't use foreach because we need the value of $i and we need to use both $u and $n
        {
          echo "userlist[$i] = '".addslashes($n[$i])."';\n";
          echo "namelist[$i] = '".addslashes(htmlspecialchars($u[$i]))."';\n";
        }
      } else {
        die('userlist = new Array(); namelist = new Array(); errorstring=\'No page matches found.\'');
      }
      break;
    case "preview":
      echo PageUtils::genPreview($_POST['text']);
      break;
    case "pagediff":
      $id1 = ( isset($_GET['diff1']) ) ? (int)$_GET['diff1'] : false;
      $id2 = ( isset($_GET['diff2']) ) ? (int)$_GET['diff2'] : false;
      if(!$id1 || !$id2) { echo '<p>Invalid request.</p>'; $template->footer(); break; }
      if(!preg_match('#^([0-9]+)$#', (string)$_GET['diff1']) ||
         !preg_match('#^([0-9]+)$#', (string)$_GET['diff2']  )) { echo '<p>SQL injection attempt</p>'; $template->footer(); break; }
      echo PageUtils::pagediff($paths->page_id, $paths->namespace, $id1, $id2);
      break;
    case "jsres":
      die('// ERROR: this section is deprecated and has moved to includes/clientside/static/enano-lib-basic.js.');
      break;
    case "rdns":
      if(!$session->get_permissions('mod_misc')) die('Go somewhere else for your reverse DNS info!');
      $ip = $_GET['ip'];
      $rdns = gethostbyaddr($ip);
      if($rdns == $ip) echo 'Unable to get reverse DNS information. Perhaps the DNS server is down or the PTR record no longer exists.';
      else echo $rdns;
      break;
    case 'acljson':
      $parms = ( isset($_POST['acl_params']) ) ? rawurldecode($_POST['acl_params']) : false;
      echo PageUtils::acl_json($parms);
      break;
    case "change_theme":
      if ( !isset($_POST['theme_id']) || !isset($_POST['style_id']) )
      {
        die('Invalid input');
      }
      if ( !preg_match('/^([a-z0-9_-]+)$/i', $_POST['theme_id']) || !preg_match('/^([a-z0-9_-]+)$/i', $_POST['style_id']) )
      {
        die('Invalid input');
      }
      if ( !file_exists(ENANO_ROOT . '/themes/' . $_POST['theme_id'] . '/css/' . $_POST['style_id'] . '.css') )
      {
        die('Can\'t find theme file: ' . ENANO_ROOT . '/themes/' . $_POST['theme_id'] . '/css/' . $_POST['style_id'] . '.css');
      }
      if ( !$session->user_logged_in )
      {
        die('You must be logged in to change your theme');
      }
      // Just in case something slipped through...
      $theme_id = $db->escape($_POST['theme_id']);
      $style_id = $db->escape($_POST['style_id']);
      $e = $db->sql_query('UPDATE ' . table_prefix . "users SET theme='$theme_id', style='$style_id' WHERE user_id=$session->user_id;");
      if ( !$e )
        die( $db->get_error() );
      die('GOOD');
      break;
    case 'get_tags':
      
      $ret = array('tags' => array(), 'user_level' => $session->user_level, 'can_add' => $session->get_permissions('tag_create'));
      $q = $db->sql_query('SELECT t.tag_id, t.tag_name, pg.pg_target IS NOT NULL AS used_in_acl, t.user_id FROM '.table_prefix.'tags AS t
        LEFT JOIN '.table_prefix.'page_groups AS pg
          ON ( ( pg.pg_type = ' . PAGE_GRP_TAGGED . ' AND pg.pg_target=t.tag_name ) OR ( pg.pg_type IS NULL AND pg.pg_target IS NULL ) )
        WHERE t.page_id=\'' . $db->escape($paths->page_id) . '\' AND t.namespace=\'' . $db->escape($paths->namespace) . '\';');
      if ( !$q )
        $db->_die();
      
      while ( $row = $db->fetchrow() )
      {
        $can_del = true;
        
        $perm = ( $row['user_id'] != $session->user_id ) ?
                'tag_delete_other' :
                'tag_delete_own';
        
        if ( $row['user_id'] == 1 && !$session->user_logged_in )
          // anonymous user trying to delete tag (hardcode blacklisted)
          $can_del = false;
          
        if ( !$session->get_permissions($perm) )
          $can_del = false;
        
        if ( $row['used_in_acl'] == 1 && !$session->get_permissions('edit_acl') && $session->user_level < USER_LEVEL_ADMIN )
          $can_del = false;
        
        $ret['tags'][] = array(
          'id' => $row['tag_id'],
          'name' => $row['tag_name'],
          'can_del' => $can_del,
          'acl' => ( $row['used_in_acl'] == 1 )
        );
      }
      
      echo enano_json_encode($ret);
      
      break;
    case 'addtag':
      $resp = array(
          'success' => false,
          'error' => 'No error',
          'can_del' => ( $session->get_permissions('tag_delete_own') && $session->user_logged_in ),
          'in_acl' => false
        );
      
      // first of course, are we allowed to tag pages?
      if ( !$session->get_permissions('tag_create') )
      {
        $resp['error'] = 'You are not permitted to tag pages.';
        die(enano_json_encode($resp));
      }
      
      // sanitize the tag name
      $tag = sanitize_tag($_POST['tag']);
      $tag = $db->escape($tag);
      
      if ( strlen($tag) < 2 )
      {
        $resp['error'] = 'Tags must consist of at least 2 alphanumeric characters.';
        die(enano_json_encode($resp));
      }
      
      // check if tag is already on page
      $q = $db->sql_query('SELECT 1 FROM '.table_prefix.'tags WHERE page_id=\'' . $db->escape($paths->page_id) . '\' AND namespace=\'' . $db->escape($paths->namespace) . '\' AND tag_name=\'' . $tag . '\';');
      if ( !$q )
        $db->_die();
      if ( $db->numrows() > 0 )
      {
        $resp['error'] = 'This page already has this tag.';
        die(enano_json_encode($resp));
      }
      $db->free_result();
      
      // tricky: make sure this tag isn't being used in some page group, and thus adding it could affect page access
      $can_edit_acl = ( $session->get_permissions('edit_acl') || $session->user_level >= USER_LEVEL_ADMIN );
      $q = $db->sql_query('SELECT 1 FROM '.table_prefix.'page_groups WHERE pg_type=' . PAGE_GRP_TAGGED . ' AND pg_target=\'' . $tag . '\';');
      if ( !$q )
        $db->_die();
      if ( $db->numrows() > 0 && !$can_edit_acl )
      {
        $resp['error'] = 'This tag is used in an ACL page group, and thus can\'t be added to a page by people without administrator privileges.';
        die(enano_json_encode($resp));
      }
      $resp['in_acl'] = ( $db->numrows() > 0 );
      $db->free_result();
      
      // we're good
      $q = $db->sql_query('INSERT INTO '.table_prefix.'tags(tag_name,page_id,namespace,user_id) VALUES(\'' . $tag . '\', \'' . $db->escape($paths->page_id) . '\', \'' . $db->escape($paths->namespace) . '\', ' . $session->user_id . ');');
      if ( !$q )
        $db->_die();
      
      $resp['success'] = true;
      $resp['tag'] = $tag;
      $resp['tag_id'] = $db->insert_id();
      
      echo enano_json_encode($resp);
      break;
    case 'deltag':
      
      $tag_id = intval($_POST['tag_id']);
      if ( empty($tag_id) )
        die('Invalid tag ID');
      
      $q = $db->sql_query('SELECT t.tag_id, t.user_id, t.page_id, t.namespace, pg.pg_target IS NOT NULL AS used_in_acl FROM '.table_prefix.'tags AS t
  LEFT JOIN '.table_prefix.'page_groups AS pg
    ON ( pg.pg_id IS NULL OR ( pg.pg_target = t.tag_name AND pg.pg_type = ' . PAGE_GRP_TAGGED . ' ) )
  WHERE t.tag_id=' . $tag_id . ';');
      
      if ( !$q )
        $db->_die();
      
      if ( $db->numrows() < 1 )
        die('Could not find a tag with that ID');
      
      $row = $db->fetchrow();
      $db->free_result();
      
      if ( $row['page_id'] == $paths->page_id && $row['namespace'] == $paths->namespace )
        $perms =& $session;
      else
        $perms = $session->fetch_page_acl($row['page_id'], $row['namespace']);
        
      $perm = ( $row['user_id'] != $session->user_id ) ?
                'tag_delete_other' :
                'tag_delete_own';
      
      if ( $row['user_id'] == 1 && !$session->user_logged_in )
        // anonymous user trying to delete tag (hardcode blacklisted)
        die('You are not authorized to delete this tag.');
        
      if ( !$perms->get_permissions($perm) )
        die('You are not authorized to delete this tag.');
      
      if ( $row['used_in_acl'] == 1 && !$perms->get_permissions('edit_acl') && $session->user_level < USER_LEVEL_ADMIN )
        die('You are not authorized to delete this tag.');
      
      // We're good
      $q = $db->sql_query('DELETE FROM '.table_prefix.'tags WHERE tag_id = ' . $tag_id . ';');
      if ( !$q )
        $db->_die();
      
      echo 'success';
      
      break;
    case 'ping':
      echo 'pong';
      break;
    default:
      die('Hacking attempt');
      break;
  }
  
?>