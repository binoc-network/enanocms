<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.5 (Caoineag alpha 5)
 * Copyright (C) 2006-2008 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

class Namespace_Special extends Namespace_Default
{
  public function __construct($page_id, $namespace, $revision_id = 0)
  {
    global $db, $session, $paths, $template, $plugins; // Common objects
    
    $this->page_id = sanitize_page_id($page_id);
    $this->namespace = $namespace;
    $this->revision_id = intval($revision_id);
    
    $this->exists = function_exists("page_{$this->namespace}_{$this->page_id}");
  }
  
  function send()
  {
    global $output;
    
    if ( $this->exists )
    {
      @call_user_func("page_{$this->namespace}_{$this->page_id}");
    }
    else
    {
      $output->header();
      $this->error_404();
      $output->footer();
    }
  }
  
  function error_404()
  {
    global $lang, $output;
    $func_name = "page_{$this->namespace}_{$this->page_id}";
    
    if ( $this->namespace == 'Admin' )
      die_semicritical($lang->get('page_msg_admin_404_title'), $lang->get('page_msg_admin_404_body', array('func_name' => $func_name)), true);
    
    $title = $lang->get('page_err_custompage_function_missing_title');
    $message = $lang->get('page_err_custompage_function_missing_body', array( 'function_name' => $fname ));
                
    $output->set_title($title);
    $output->header();
    echo "<p>$message</p>";
    $output->footer();
  }
}
