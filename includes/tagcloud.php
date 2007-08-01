<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.0.1 (Loch Ness)
 * Copyright (C) 2006-2007 Dan Fuhry
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */

/**
 * Class for formatting and displaying tag clouds. Loosely based the reference cloud engine from <http://www.lotsofcode.com/php/tutorials/tag-cloud>.
 * @package Enano
 * @subpackage Presentation/UI
 * @copyright (C) 2007 Dan Fuhry
 * @license GNU General Public License, version 2 or at your option any later versionc
 */

class TagCloud
{
  
  /**
   * The list of words in the cloud.
   * @var array
   */
  
  var $words = array();
  
  /**
   * Constructor.
   * @param array Optional. An initial list of words, just a plain old array.
   */
  
  function __construct($words)
  {
    if ( count($words) > 0 )
    {
      foreach ( $words as $word )
        $this->add_word($word);
    }
  }
  
  /**
   * Adds a word into the word list.
   * @param string The word to add
   */
  
  function add_word($word)
  {
    $word = strtolower($word);
    
    if ( isset($this->words[$word]) )
      $this->words[$word] += 1;
    else
      $this->words[$word] = 1;
  }
  
  /**
   * Returns the total size of the cloud.
   * @return int
   */
  
  function get_cloud_size()
  {
    return array_sum($this->words);
  }
  
  /**
   * Shuffles the cloud.
   */
  
  function shuffle_cloud()
  {
    $keys = array_keys($this->words);
    if ( !$keys || empty($keys) || !is_array($keys) )
      return null;
    
    shuffle($keys);
    if ( !$keys || empty($keys) || !is_array($keys) )
      return null;
    
    $temp = $this->words;
    $this->words = array();
    foreach ( $keys as $word )
    {
      $this->words[$word] = $temp[$word];
    }
    
    unset($temp);
  }
  
  /**
   * Returns the popularity index (scale class) for a 1-100 number.
   * @param int
   * @return int
   */
  
  function get_scale_class($val)
  {
    $ret = 0;
    if ( $val >= 99 )
      $ret = 1;
    else if ( $val >= 70 )
      $ret = 2;
    else if ( $val >= 60 )
      $ret = 3;
    else if ( $val >= 50 )
      $ret = 4;
    else if ( $val >= 40 )
      $ret = 5;
    else if ( $val >= 30 )
      $ret = 6;
    else if ( $val >= 20 )
      $ret = 7;
    else if ( $val >= 10 )
      $ret = 8;
    else if ( $val >= 5 )
      $ret = 9;
    return $ret;
  }
  
  /**
   * Generates and returns HTML for the cloud.
   * @return string
   */
   
  function make_html()
  {
    $html = array();
    $max  = max($this->words);
    $size = $this->get_cloud_size();
    if ( count($this->words) > 0 )
    {
      foreach ( $this->words as $word => $popularity )
      {
        $word = htmlspecialchars($word);
        $percent = ( $popularity / $max ) * 100;
        $index = $this->get_scale_class($percent);
        $html[] = "<span class='tc_word tc_index_$index'>$word</span>";
      }
    }
    $html = implode("\n", $html);
    return $html;
  }
   
  
}

?>
