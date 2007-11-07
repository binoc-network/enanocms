<?php

/*
 * Enano - an open-source CMS capable of wiki functions, Drupal-like sidebar blocks, and everything in between
 * Version 1.1.1
 * Copyright (C) 2006-2007 Dan Fuhry
 * install.php - handles everything related to installation and initial configuration
 *
 * This program is Free Software; you can redistribute and/or modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
 * warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for details.
 */
 
@include('config.php');
if( ( defined('ENANO_INSTALLED') || defined('MIDGET_INSTALLED') ) && ((isset($_GET['mode']) && ($_GET['mode']!='finish' && $_GET['mode']!='css')) || !isset($_GET['mode'])))
{
  $_GET['title'] = 'Enano:Installation_locked';
  require('includes/common.php');
  die_friendly('Installation locked', '<p>The Enano installer has found a Enano installation in this directory. You MUST delete config.php if you want to re-install Enano.</p><p>If you wish to upgrade an older Enano installation to this version, please use the <a href="upgrade.php">upgrade script</a>.</p>');
  exit;
}

define('IN_ENANO_INSTALL', 'true');

define('ENANO_VERSION', '1.1.1');
define('ENANO_CODE_NAME', 'Germination');
// In beta versions, define ENANO_BETA_VERSION here

// This is required to make installation work right
define("ENANO_ALLOW_LOAD_NOLANG", 1);

if(!defined('scriptPath')) {
  $sp = dirname($_SERVER['REQUEST_URI']);
  if($sp == '/' || $sp == '\\') $sp = '';
  define('scriptPath', $sp);
}

if(!defined('contentPath')) {
  $sp = dirname($_SERVER['REQUEST_URI']);
  if($sp == '/' || $sp == '\\') $sp = '';
  define('contentPath', $sp);
}
global $_starttime, $this_page, $sideinfo;
$_starttime = microtime(true);

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

function is_page($p)
{
  return true;
}

require('includes/wikiformat.php');
require('includes/constants.php');
require('includes/rijndael.php');
require('includes/functions.php');
require('includes/dbal.php');
require('includes/lang.php');
require('includes/json.php');

strip_magic_quotes_gpc();

//
// INSTALLER LIBRARY
//

$neutral_color = 'C';

function run_installer_stage($stage_id, $stage_name, $function, $failure_explanation, $allow_skip = true)
{
  static $resumed = false;
  static $resume_stack = array();
  
  if ( empty($resume_stack) && isset($_POST['resume_stack']) && preg_match('/[a-z_]+((\|[a-z_]+)+)/', $_POST['resume_stack']) )
  {
    $resume_stack = explode('|', $_POST['resume_stack']);
  }
  
  $already_run = false;
  if ( in_array($stage_id, $resume_stack) )
  {
    $already_run = true;
  }
  
  if ( !$resumed )
  {
    if ( !isset($_GET['stage']) )
      $resumed = true;
    if ( isset($_GET['stage']) && $_GET['stage'] == $stage_id )
    {
      $resumed = true;
    }
  }
  if ( !$resumed && $allow_skip )
  {
    echo_stage_success($stage_id, "[dbg: skipped] $stage_name");
    return false;
  }
  if ( !function_exists($function) )
    die('libenanoinstall: CRITICAL: function "' . $function . '" for ' . $stage_id . ' doesn\'t exist');
  $result = @call_user_func($function, false, $already_run);
  if ( $result )
  {
    echo_stage_success($stage_id, $stage_name);
    $resume_stack[] = $stage_id;
    return true;
  }
  else
  {
    echo_stage_failure($stage_id, $stage_name, $failure_explanation, $resume_stack);
    return false;
  }
}

function start_install_table()
{
  echo '<table border="0" cellspacing="0" cellpadding="0">' . "\n";
  ob_start();
}

function close_install_table()
{
  echo '</table>' . "\n\n";
  ob_end_flush();
}

function echo_stage_success($stage_id, $stage_name)
{
  global $neutral_color;
  $neutral_color = ( $neutral_color == 'A' ) ? 'C' : 'A';
  echo '<tr><td style="width: 500px; background-color: #' . "{$neutral_color}{$neutral_color}FF{$neutral_color}{$neutral_color}" . '; padding: 0 5px;">' . htmlspecialchars($stage_name) . '</td><td style="padding: 0 5px;"><img alt="Done" src="images/good.gif" /></td></tr>' . "\n";
  ob_flush();
  flush();
}

function echo_stage_failure($stage_id, $stage_name, $failure_explanation, $resume_stack)
{
  global $neutral_color;
  
  $neutral_color = ( $neutral_color == 'A' ) ? 'C' : 'A';
  echo '<tr><td style="width: 500px; background-color: #' . "FF{$neutral_color}{$neutral_color}{$neutral_color}{$neutral_color}" . '; padding: 0 5px;">' . htmlspecialchars($stage_name) . '</td><td style="padding: 0 5px;"><img alt="Failed" src="images/bad.gif" /></td></tr>' . "\n";
  ob_flush();
  flush();
  close_install_table();
  $post_data = '';
  $mysql_error = mysql_error();
  foreach ( $_POST as $key => $value )
  {
    $value = htmlspecialchars($value);
    $key = htmlspecialchars($key);
    $post_data .= "          <input type=\"hidden\" name=\"$key\" value=\"$value\" />\n";
  }
  echo '<form action="install.php?mode=install&amp;stage=' . $stage_id . '" method="post">
          ' . $post_data . '
          <input type="hidden" name="resume_stack" value="' . htmlspecialchars(implode('|', $resume_stack)) . '" />
          <h3>Enano installation failed.</h3>
           <p>' . $failure_explanation . '</p>
           ' . ( !empty($mysql_error) ? "<p>The error returned from MySQL was: $mysql_error</p>" : '' ) . '
           <p>When you have corrected the error, click the button below to attempt to continue the installation.</p>
           <p style="text-align: center;"><input type="submit" value="Retry installation" /></p>
        </form>';
  global $template, $template_bak;
  if ( is_object($template_bak) )
    $template_bak->footer();
  else
    $template->footer();
  exit;
}

//
// INSTALLER STAGES
//

function stg_mysql_connect($act_get = false)
{
  static $conn = false;
  if ( $act_get )
    return $conn;
  
  $db_user =& $_POST['db_user'];
  $db_pass =& $_POST['db_pass'];
  $db_name =& $_POST['db_name'];
  
  if ( !preg_match('/^[a-z0-9_]+$/', $db_name) )
  {
    die('<pre>' . htmlspecialchars(print_r($_POST, true)) . '</pre>');
    $db_name = htmlspecialchars($db_name);
    die("<p>SECURITY: malformed database name \"$db_name\"</p>");
  }
  
  // First, try to connect using the normal credentials
  $conn = @mysql_connect($_POST['db_host'], $_POST['db_user'], $_POST['db_pass']);
  if ( !$conn )
  {
    // Connection failed. Do we have the root username and password?
    if ( !empty($_POST['db_root_user']) && !empty($_POST['db_root_pass']) )
    {
      $conn_root = @mysql_connect($_POST['db_host'], $_POST['db_root_user'], $_POST['db_root_pass']);
      if ( !$conn_root )
      {
        // Couldn't connect using either set of credentials. Bail out.
        return false;
      }
      unset($db_user, $db_pass);
      $db_user = mysql_real_escape_string($_POST['db_user']);
      $db_pass = mysql_real_escape_string($_POST['db_pass']);
      // Create the user account
      $q = @mysql_query("GRANT ALL PRIVILEGES ON test.* TO '{$db_user}'@'localhost' IDENTIFIED BY '$db_pass' WITH GRANT OPTION;", $conn_root);
      if ( !$q )
      {
        return false;
      }
      // Revoke privileges from test, we don't need them
      $q = @mysql_query("REVOKE ALL PRIVILEGES ON test.* FROM '{$db_user}'@'localhost';", $conn_root);
      if ( !$q )
      {
        return false;
      }
      if ( $_POST['db_host'] != 'localhost' && $_POST['db_host'] != '127.0.0.1' && $_POST['db_host'] != '::1' )
      {
        // If not connecting to a server running on localhost, allow from any host
        // this is safer than trying to detect the hostname of the webserver, but less secure
        $q = @mysql_query("GRANT ALL PRIVILEGES ON test.* TO '{$db_user}'@'%' IDENTIFIED BY '$db_pass' WITH GRANT OPTION;", $conn_root);
        if ( !$q )
        {
          return false;
        }
        // Revoke privileges from test, we don't need them
        $q = @mysql_query("REVOKE ALL PRIVILEGES ON test.* FROM '{$db_user}'@'%';", $conn_root);
        if ( !$q )
        {
          return false;
        }
      }
    }
  }
  $q = @mysql_query("USE $db_name;", $conn);
  if ( !$q )
  {
    // access denied to the database; try the whole root schenanegan again
    if ( !empty($_POST['db_root_user']) && !empty($_POST['db_root_pass']) )
    {
      $conn_root = @mysql_connect($_POST['db_host'], $_POST['db_root_user'], $_POST['db_root_pass']);
      if ( !$conn_root )
      {
        // Couldn't connect as root; bail out
        return false;
      }
      // create the database, if it doesn't exist
      $q = @mysql_query("CREATE DATABASE IF NOT EXISTS $db_name;", $conn_root);
      if ( !$q )
      {
        // this really should never fail, so don't give any tolerance to it
        return false;
      }
      unset($db_user, $db_pass);
      $db_user = mysql_real_escape_string($_POST['db_user']);
      $db_pass = mysql_real_escape_string($_POST['db_pass']);
      // we're in with root rights; grant access to the database
      $q = @mysql_query("GRANT ALL PRIVILEGES ON $db_name.* TO '{$db_user}'@'localhost';", $conn_root);
      if ( !$q )
      {
        return false;
      }
      if ( $_POST['db_host'] != 'localhost' && $_POST['db_host'] != '127.0.0.1' && $_POST['db_host'] != '::1' )
      {
        $q = @mysql_query("GRANT ALL PRIVILEGES ON $db_name.* TO '{$db_user}'@'%';", $conn_root);
        if ( !$q )
        {
          return false;
        }
      }
    }
    else
    {
      return false;
    }
    // try again
    $q = @mysql_query("USE $db_name;", $conn);
    if ( !$q )
    {
      // really failed this time; bail out
      return false;
    }
  }
  // connected and database exists
  return true;
}

function stg_drop_tables()
{
  $conn = stg_mysql_connect(true);
  if ( !$conn )
    return false;
  // Our list of tables included in Enano
  $tables = Array( 'categories', 'comments', 'config', 'logs', 'page_text', 'session_keys', 'pages', 'users', 'users_extra', 'themes', 'buddies', 'banlist', 'files', 'privmsgs', 'sidebar', 'hits', 'search_index', 'groups', 'group_members', 'acl', 'search_cache', 'tags', 'page_groups', 'page_group_members' );
  
  // Drop each table individually; if it fails, it probably means we're trying to drop a
  // table that didn't exist in the Enano version we're deleting the database for.
  foreach ( $tables as $table )
  {
    // Remember that table_prefix is sanitized.
    $table = "{$_POST['table_prefix']}$table";
    @mysql_query("DROP TABLE $table;", $conn);
  }
  return true;
}

function stg_decrypt_admin_pass($act_get = false)
{
  static $decrypted_pass = false;
  if ( $act_get )
    return $decrypted_pass;
  
  $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
  
  if ( !empty($_POST['crypt_data']) )
  {
    require('config.new.php');
    if ( !isset($cryptkey) )
    {
      return false;
    }
    define('_INSTRESUME_AES_KEYBACKUP', $key);
    $key = hexdecode($cryptkey);
    
    $decrypted_pass = $aes->decrypt($_POST['crypt_data'], $key, ENC_HEX);
    
  }
  else
  {
    $decrypted_pass = $_POST['admin_pass'];
  }
  if ( empty($decrypted_pass) )
    return false;
  return true;
}

function stg_generate_aes_key($act_get = false)
{
  static $key = false;
  if ( $act_get )
    return $key;
  
  $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
  $key = $aes->gen_readymade_key();
  return true;
}

function stg_parse_schema($act_get = false)
{
  static $schema;
  if ( $act_get )
    return $schema;
  
  $admin_pass = stg_decrypt_admin_pass(true);
  $key = stg_generate_aes_key(true);
  $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
  $key = $aes->hextostring($key);
  $admin_pass = $aes->encrypt($admin_pass, $key, ENC_HEX);
  
  $cacheonoff = is_writable(ENANO_ROOT.'/cache/') ? '1' : '0';
  
  $schema = file_get_contents('schema.sql');
  $schema = str_replace('{{SITE_NAME}}',    mysql_real_escape_string($_POST['sitename']   ), $schema);
  $schema = str_replace('{{SITE_DESC}}',    mysql_real_escape_string($_POST['sitedesc']   ), $schema);
  $schema = str_replace('{{COPYRIGHT}}',    mysql_real_escape_string($_POST['copyright']  ), $schema);
  $schema = str_replace('{{ADMIN_USER}}',   mysql_real_escape_string($_POST['admin_user'] ), $schema);
  $schema = str_replace('{{ADMIN_PASS}}',   mysql_real_escape_string($admin_pass          ), $schema);
  $schema = str_replace('{{ADMIN_EMAIL}}',  mysql_real_escape_string($_POST['admin_email']), $schema);
  $schema = str_replace('{{ENABLE_CACHE}}', mysql_real_escape_string($cacheonoff          ), $schema);
  $schema = str_replace('{{REAL_NAME}}',    '',                                              $schema);
  $schema = str_replace('{{TABLE_PREFIX}}', $_POST['table_prefix'],                          $schema);
  $schema = str_replace('{{VERSION}}',      ENANO_VERSION,                                   $schema);
  $schema = str_replace('{{ADMIN_EMBED_PHP}}', $_POST['admin_embed_php'],                    $schema);
  // Not anymore!! :-D
  // $schema = str_replace('{{BETA_VERSION}}', ENANO_BETA_VERSION,                              $schema);
  
  if(isset($_POST['wiki_mode']))
  {
    $schema = str_replace('{{WIKI_MODE}}', '1', $schema);
  }
  else
  {
    $schema = str_replace('{{WIKI_MODE}}', '0', $schema);
  }
  
  // Build an array of queries      
  $schema = explode("\n", $schema);
  
  foreach ( $schema as $i => $sql )
  {
    $query =& $schema[$i];
    $t = trim($query);
    if ( empty($t) || preg_match('/^(\#|--)/i', $t) )
    {
      unset($schema[$i]);
      unset($query);
    }
  }
  
  $schema = array_values($schema);
  $schema = implode("\n", $schema);
  $schema = explode(";\n", $schema);
  
  foreach ( $schema as $i => $sql )
  {
    $query =& $schema[$i];
    if ( substr($query, ( strlen($query) - 1 ), 1 ) != ';' )
    {
      $query .= ';';
    }
  }
  
  return true;
}

function stg_install($_unused, $already_run)
{
  // This one's pretty easy.
  $conn = stg_mysql_connect(true);
  if ( !is_resource($conn) )
    return false;
  $schema = stg_parse_schema(true);
  if ( !is_array($schema) )
    return false;
  
  // If we're resuming installation, the encryption key was regenerated.
  // This means we'll have to update the encrypted password in the database.
  if ( $already_run )
  {
    $admin_pass = stg_decrypt_admin_pass(true);
    $key = stg_generate_aes_key(true);
    $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
    $key = $aes->hextostring($key);
    $admin_pass = $aes->encrypt($admin_pass, $key, ENC_HEX);
    $admin_user = mysql_real_escape_string($_POST['admin_user']);
    
    $q = @mysql_query("UPDATE {$_POST['table_prefix']}users SET password='$admin_pass' WHERE username='$admin_user';");
    if ( !$q )
    {
      echo '<p><tt>MySQL return: ' . mysql_error() . '</tt></p>';
      return false;
    }
    
    return true;
  }
  
  // OK, do the loop, baby!!!
  foreach($schema as $q)
  {
    $r = mysql_query($q, $conn);
    if ( !$r )
    {
      echo '<p><tt>MySQL return: ' . mysql_error() . '</tt></p>';
      return false;
    }
  }
  
  return true;
}

function stg_write_config()
{
  $privkey = stg_generate_aes_key(true);
  
  switch($_POST['urlscheme'])
  {
    case "ugly":
    default:
      $cp = scriptPath.'/index.php?title=';
      break;
    case "short":
      $cp = scriptPath.'/index.php/';
      break;
    case "tiny":
      $cp = scriptPath.'/';
      break;
  }
  
  if ( $_POST['urlscheme'] == 'tiny' )
  {
    $contents = '# Begin Enano rules
RewriteEngine on
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.+) '.scriptPath.'/index.php?title=$1 [L,QSA]
RewriteRule \.(php|html|gif|jpg|png|css|js)$ - [L]
# End Enano rules
';
    if ( file_exists('./.htaccess') )
      $ht = fopen(ENANO_ROOT.'/.htaccess', 'a+');
    else
      $ht = fopen(ENANO_ROOT.'/.htaccess.new', 'w');
    if ( !$ht )
      return false;
    fwrite($ht, $contents);
    fclose($ht);
  }

  $config_file = '<?php
/* Enano auto-generated configuration file - editing not recommended! */
$dbhost   = \''.addslashes($_POST['db_host']).'\';
$dbname   = \''.addslashes($_POST['db_name']).'\';
$dbuser   = \''.addslashes($_POST['db_user']).'\';
$dbpasswd = \''.addslashes($_POST['db_pass']).'\';
if ( !defined(\'ENANO_CONSTANTS\') )
{
define(\'ENANO_CONSTANTS\', \'\');
define(\'table_prefix\', \''.addslashes($_POST['table_prefix']).'\');
define(\'scriptPath\', \''.scriptPath.'\');
define(\'contentPath\', \''.$cp.'\');
define(\'ENANO_INSTALLED\', \'true\');
}
$crypto_key = \''.$privkey.'\';
?>';

  $cf_handle = fopen(ENANO_ROOT.'/config.new.php', 'w');
  if ( !$cf_handle )
    return false;
  fwrite($cf_handle, $config_file);
  
  fclose($cf_handle);
  
  return true;
}

function _stg_rename_config_revert()
{
  if ( file_exists('./config.php') )
  {
    @rename('./config.php', './config.new.php');
  }
  
  $handle = @fopen('./config.php.new', 'w');
  if ( !$handle )
    return false;
  $contents = '<?php $cryptkey = \'' . _INSTRESUME_AES_KEYBACKUP . '\'; ?>';
  fwrite($handle, $contents);
  fclose($handle);
  return true;
}

function stg_rename_config()
{
  if ( !@rename('./config.new.php', './config.php') )
  {
    echo '<p>Can\'t rename config.php</p>';
    _stg_rename_config_revert();
    return false;
  }
  
  if ( $_POST['urlscheme'] == 'tiny' && !file_exists('./.htaccess') )
  {
    if ( !@rename('./.htaccess.new', './.htaccess') )
    {
      echo '<p>Can\'t rename .htaccess</p>';
      _stg_rename_config_revert();
      return false;
    }
  }
  return true;
}

function stg_start_api_success()
{
  return true;
}

function stg_start_api_failure()
{
  return false;
}

function stg_import_language()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $lang_file = ENANO_ROOT . "/language/english/enano.json";
  install_language("eng", "English", "English", $lang_file);
  
  return true;
}

function stg_init_logs()
{
  global $db, $session, $paths, $template, $plugins; // Common objects
  
  $q = $db->sql_query('INSERT INTO ' . table_prefix . 'logs(log_type,action,time_id,date_string,author,page_text,edit_summary) VALUES(\'security\', \'install_enano\', ' . time() . ', \'' . date('d M Y h:i a') . '\', \'' . mysql_real_escape_string($_POST['admin_user']) . '\', \'' . mysql_real_escape_string(ENANO_VERSION) . '\', \'' . mysql_real_escape_string($_SERVER['REMOTE_ADDR']) . '\');');
  if ( !$q )
  {
    echo '<p><tt>MySQL return: ' . mysql_error() . '</tt></p>';
    return false;
  }
  
  if ( !$session->get_permissions('clear_logs') )
  {
    echo '<p><tt>$session: denied clear_logs</tt></p>';
    return false;
  }
  
  PageUtils::flushlogs('Main_Page', 'Article');
  
  return true;
}

//die('Key size: ' . AES_BITS . '<br />Block size: ' . AES_BLOCKSIZE);

if(!function_exists('wikiFormat'))
{
  function wikiFormat($message, $filter_links = true)
  {
    $wiki = & Text_Wiki::singleton('Mediawiki');
    $wiki->setRenderConf('Xhtml', 'code', 'css_filename', 'codefilename');
    $wiki->setRenderConf('Xhtml', 'wikilink', 'view_url', contentPath);
    $result = $wiki->transform($message, 'Xhtml');
    
    // HTML fixes
    $result = preg_replace('#<tr>([\s]*?)<\/tr>#is', '', $result);
    $result = preg_replace('#<p>([\s]*?)<\/p>#is', '', $result);
    $result = preg_replace('#<br />([\s]*?)<table#is', '<table', $result);
    
    return $result;
  }
}

global $failed, $warned;

$failed = false;
$warned = false;

function not($var)
{
  if($var)
  {
    return false;
  } 
  else
  {
    return true;
  }
}

function run_test($code, $desc, $extended_desc, $warn = false)
{
  global $failed, $warned;
  static $cv = true;
  $cv = not($cv);
  $val = eval($code);
  if($val)
  {
    if($cv) $color='CCFFCC'; else $color='AAFFAA';
    echo "<tr><td style='background-color: #$color; width: 500px; padding: 5px;'>$desc</td><td style='padding-left: 10px;'><img alt='Test passed' src='images/good.gif' /></td></tr>";
  } elseif(!$val && $warn) {
    if($cv) $color='FFFFCC'; else $color='FFFFAA';
    echo "<tr><td style='background-color: #$color; width: 500px; padding: 5px;'>$desc<br /><b>$extended_desc</b></td><td style='padding-left: 10px;'><img alt='Test passed with warning' src='images/unknown.gif' /></td></tr>";
    $warned = true;
  } else {
    if($cv) $color='FFCCCC'; else $color='FFAAAA';
    echo "<tr><td style='background-color: #$color; width: 500px; padding: 5px;'>$desc<br /><b>$extended_desc</b></td><td style='padding-left: 10px;'><img alt='Test failed' src='images/bad.gif' /></td></tr>";
    $failed = true;
  }
}
function is_apache()
{
  return strstr($_SERVER['SERVER_SOFTWARE'], 'Apache') ? true : false;
}

require_once('includes/template.php');

//
// Startup localization
//

// We need $db just for the _die function
$db = new mysql();

$lang = new Language('eng');
$lang->load_file('./language/english/install.json');

if ( !isset($_GET['mode']) )
  $_GET['mode'] = 'welcome';

switch($_GET['mode'])
{
  case 'mysql_test':
    error_reporting(0);
    $dbhost     = rawurldecode($_POST['host']);
    $dbname     = rawurldecode($_POST['name']);
    $dbuser     = rawurldecode($_POST['user']);
    $dbpass     = rawurldecode($_POST['pass']);
    $dbrootuser = rawurldecode($_POST['root_user']);
    $dbrootpass = rawurldecode($_POST['root_pass']);
    if($dbrootuser != '')
    {
      $conn = mysql_connect($dbhost, $dbrootuser, $dbrootpass);
      if(!$conn)
      {
        $e = mysql_error();
        if(strstr($e, "Lost connection"))
          die('host'.$e);
        else
          die('root'.$e);
      }
      $rsp = 'good';
      $q = mysql_query('USE '.$dbname, $conn);
      if(!$q)
      {
        $e = mysql_error();
        if(strstr($e, 'Unknown database'))
        {
          $rsp .= '_creating_db';
        }
      }
      mysql_close($conn);
      $conn = mysql_connect($dbhost, $dbuser, $dbpass);
      if(!$conn)
      {
        $e = mysql_error();
        if(strstr($e, "Lost connection"))
          die('host'.$e);
        else
          $rsp .= '_creating_user';
      }
      mysql_close($conn);
      die($rsp);
    }
    else
    {
      $conn = mysql_connect($dbhost, $dbuser, $dbpass);
      if(!$conn)
      {
        $e = mysql_error();
        if(strstr($e, "Lost connection"))
          die('host'.$e);
        else
          die('auth'.$e);
      }
      $q = mysql_query('USE '.$dbname, $conn);
      if(!$q)
      {
        $e = mysql_error();
        if(strstr($e, 'Unknown database'))
        {
          die('name'.$e);
        }
        else
        {
          die('perm'.$e);
        }
      }
    }
    $v = mysql_get_server_info();
    if(version_compare($v, '4.1.17', '<')) die('vers'.$v);
    mysql_close($conn);
    die('good');
    break;
  case 'pophelp':
    $topic = ( isset($_GET['topic']) ) ? $_GET['topic'] : 'invalid';
    switch($topic)
    {
      case 'admin_embed_php':
        $title = 'Allow administrators to embed PHP';
        $content = '<p>This option allows you to control whether anything between the standard &lt;?php and ?&gt; tags will be treated as
                        PHP code by Enano. If this option is enabled, and members of the Administrators group use these tags, Enano will
                        execute that code when the page is loaded. There are obvious potential security implications here, which should
                        be carefully considered before enabling this option.</p>
                    <p>If you are the only administrator of this site, or if you have a high level of trust for those will be administering
                       the site with you, you should enable this to allow extreme customization of pages.</p>
                    <p>Leave this option off if you are at all concerned about security – if your account is compromised and PHP embedding
                       is enabled, an attacker can run arbitrary code on your server! Enabling this will also allow administrators to
                       embed Javascript and arbitrary HTML and CSS.</p>
                    <p>If you don\'t have experience coding in PHP, you can safely disable this option. You may change this at any time
                       using the ACL editor by selecting the Administrators group and This Entire Website under the scope selection. <!-- , or by
                       using the "embedded PHP kill switch" in the administration panel. --></p>';
        break;
      default:
        $title = 'Invalid topic';
        $content = 'Invalid help topic.';
        break;
    }
    echo <<<EOF
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">
<html>
  <head>
    <title>Enano installation quick help &bull; {$title}</title>
    <meta http-equiv="Content-type" content="text/html; charset=utf-8" />
    <style type="text/css">
      body {
        font-family: trebuchet ms, verdana, arial, helvetica, sans-serif;
        font-size: 9pt;
      }
      h2          { border-bottom: 1px solid #90B0D0; margin-bottom: 0; }
      h3          { font-size: 11pt; font-weight: bold; }
      li          { list-style: url(../images/bullet.gif); }
      p           { margin: 1.0em; }
      blockquote  { background-color: #F4F4F4; border: 1px dotted #406080; margin: 1em; padding: 10px; max-height: 250px; overflow: auto; }
      a           { color: #7090B0; }
      a:hover     { color: #90B0D0; }
    </style>
  </head>
  <body>
    <h2>{$title}</h2>
    {$content}
    <p style="text-align: right;">
      <a href="#" onclick="window.close(); return false;">Close window</a>
    </p>
  </body>
</html>
EOF;
    exit;
    break;
  case 'langjs':
    header('Content-type: text/javascript');
    $json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
    $lang_js = $json->encode($lang->strings);
    // use EEOF here because jEdit misinterprets "typ'eof'"
    echo <<<EEOF
if ( typeof(enano_lang) != 'object' )
  var enano_lang = new Object();

enano_lang[1] = $lang_js;

EEOF;
    exit;
    break;
  default:
    break;
}

$template = new template_nodb();
$template->load_theme('stpatty', 'shamrock', false);

$modestrings = Array(
              'welcome' => $lang->get('welcome_modetitle'),
              'license' => $lang->get('license_modetitle'),
              'sysreqs' => $lang->get('sysreqs_modetitle'),
              'database'=> $lang->get('database_modetitle'),
              'website' => $lang->get('website_modetitle'),
              'login'   => $lang->get('login_modetitle'),
              'confirm' => $lang->get('confirm_modetitle'),
              'install' => $lang->get('install_modetitle'),
              'finish'  => $lang->get('finish_modetitle')
            );

$sideinfo = '';
$vars = $template->extract_vars('elements.tpl');
$p = $template->makeParserText($vars['sidebar_button']);
foreach ( $modestrings as $id => $str )
{
  if ( $_GET['mode'] == $id )
  {
    $flags = 'style="font-weight: bold; text-decoration: underline;"';
    $this_page = $str;
  }
  else
  {
    $flags = '';
  }
  $p->assign_vars(Array(
      'HREF' => '#',
      'FLAGS' => $flags . ' onclick="return false;"',
      'TEXT' => $str
    ));
  $sideinfo .= $p->run();
}

$template->init_vars();

if(isset($_GET['mode']) && $_GET['mode'] == 'css')
{
  header('Content-type: text/css');
  echo $template->get_css();
  exit;
}

if ( defined('ENANO_IS_STABLE') )
  $branch = 'stable';
else if ( defined('ENANO_IS_UNSTABLE') )
  $branch = 'unstable';
else
{
  $version = explode('.', ENANO_VERSION);
  if ( !isset($version[1]) )
    // unknown branch, really
    $branch = 'unstable';
  else
  {
    $version[1] = intval($version[1]);
    if ( $version[1] % 2 == 1 )
      $branch = 'unstable';
    else
      $branch = 'stable';
  }
}

$template->header();
if(!isset($_GET['mode'])) $_GET['mode'] = 'license';
switch($_GET['mode'])
{ 
  default:
  case 'welcome':
    ?>
    <div style="text-align: center; margin-top: 10px;">
      <img alt="[ Enano CMS Project logo ]" src="images/enano-artwork/installer-greeting-green.png" style="display: block; margin: 0 auto; padding-left: 100px;" />
      <h2><?php echo $lang->get('welcome_heading'); ?></h2>
      <h3>
        <?php
        $branch_l = $lang->get("welcome_branch_$branch");
        
        $v_string = sprintf('%s %s &ndash; %s', $lang->get('welcome_version'), ENANO_VERSION, $branch_l);
        echo $v_string;
        ?>
      </h3>
      <?php
        if ( defined('ENANO_CODE_NAME') )
        {
          echo '<p>';
          echo $lang->get('welcome_aka', array(
              'codename' => strtolower(ENANO_CODE_NAME)
            ));
          echo '</p>';
        }
      ?>
      <form action="install.php?mode=license" method="post">
        <input type="submit" value="<?php echo $lang->get('welcome_btn_start'); ?>" />
      </form>
    </div>
    <?php
    break;
  case "license":
    ?>
    <h3><?php echo $lang->get('license_heading'); ?></h3>
     <p><?php echo $lang->get('license_blurb_thankyou'); ?></p>
     <p><?php echo $lang->get('license_blurb_pleaseread'); ?></p>
     <div style="height: 500px; clip: rect(0px,auto,500px,auto); overflow: auto; padding: 10px; border: 1px dashed #456798; margin: 1em;">
       <?php
       if ( !file_exists('./GPL') || !file_exists('./language/english/install/license-deed.html') )
       {
         echo 'Cannot find the license files.';
       }
       echo file_get_contents('./language/english/install/license-deed.html');
       if ( defined('ENANO_BETA_VERSION') || $branch == 'unstable' )
       {
         ?>
         <h3><?php echo $lang->get('license_info_unstable_title'); ?></h3>
         <p><?php echo $lang->get('license_info_unstable_body'); ?></p>
         <?php
       }
       ?>
       <h3><?php echo $lang->get('license_section_gpl_heading'); ?></h3>
       <?php if ( $lang->lang_code != 'eng' ): ?>
       <p><i><?php echo $lang->get('license_gpl_blurb_inenglish'); ?></i></p>
       <?php endif; ?>
       <?php echo wikiFormat(file_get_contents(ENANO_ROOT . '/GPL')); ?>
     </div>
     <div class="pagenav">
       <form action="install.php?mode=sysreqs" method="post">
         <table border="0">
         <tr>
           <td>
             <input type="submit" value="<?php echo $lang->get('license_btn_i_agree'); ?>" />
           </td>
           <td>
             <p>
               <span style="font-weight: bold;"><?php echo $lang->get('meta_lbl_before_continue'); ?></span><br />
               &bull; <?php echo $lang->get('license_objective_ensure_agree'); ?><br />
               &bull; <?php echo $lang->get('license_objective_have_db_info'); ?>
             </p>
           </td>
         </tr>
         </table>
       </form>
     </div>
    <?php
    break;
  case "sysreqs":
    error_reporting(E_ALL);
    ?>
    <h3><?php echo $lang->get('sysreqs_heading'); ?></h3>
     <p><?php echo $lang->get('sysreqs_blurb'); ?></p>
    <table border="0" cellspacing="0" cellpadding="0">
    <?php
    run_test('return version_compare(\'4.3.0\', PHP_VERSION, \'<\');', $lang->get('sysreqs_req_php'), $lang->get('sysreqs_req_desc_php') );
    run_test('return function_exists(\'mysql_connect\');', $lang->get('sysreqs_req_mysql'), $lang->get('sysreqs_req_desc_mysql') );
    run_test('return @ini_get(\'file_uploads\');', $lang->get('sysreqs_req_uploads'), $lang->get('sysreqs_req_desc_uploads') );
    run_test('return is_apache();', $lang->get('sysreqs_req_apache'), $lang->get('sysreqs_req_desc_apache'), true);
    run_test('return is_writable(ENANO_ROOT.\'/config.new.php\');', $lang->get('sysreqs_req_config'), $lang->get('sysreqs_req_desc_config') );
    run_test('return file_exists(\'/usr/bin/convert\');', $lang->get('sysreqs_req_magick'), $lang->get('sysreqs_req_desc_magick'), true);
    run_test('return is_writable(ENANO_ROOT.\'/cache/\');', $lang->get('sysreqs_req_cachewriteable'), $lang->get('sysreqs_req_desc_cachewriteable'), true);
    run_test('return is_writable(ENANO_ROOT.\'/files/\');', $lang->get('sysreqs_req_fileswriteable'), $lang->get('sysreqs_req_desc_fileswriteable'), true);
    echo '</table>';
    if(!$failed)
    {
      ?>
      
      <div class="pagenav">
      <?php
      if($warned) {
        echo '<table border="0" cellspacing="0" cellpadding="0">';
        run_test('return false;', $lang->get('sysreqs_summary_warn_title'), $lang->get('sysreqs_summary_warn_body'), true);
        echo '</table>';
      } else {
        echo '<table border="0" cellspacing="0" cellpadding="0">';
        run_test('return true;', '<b>' . $lang->get('sysreqs_summary_success_title') . '</b><br />' . $lang->get('sysreqs_summary_success_body'), 'You should never see this text. Congratulations for being an Enano hacker!');
        echo '</table>';
      }
      ?>
      <form action="install.php?mode=database" method="post">
        <table border="0">
        <tr>
          <td>
            <input type="submit" value="<?php echo $lang->get('meta_btn_continue'); ?>" />
          </td>
          <td>
            <p>
              <span style="font-weight: bold;"><?php echo $lang->get('meta_lbl_before_continue'); ?></span><br />
              &bull; <?php echo $lang->get('sysreqs_objective_scalebacks'); ?><br />
              &bull; <?php echo $lang->get('license_objective_have_db_info'); ?>
            </p>
          </td>
        </tr>
        </table>
      </form>
      </div>
    <?php
    }
    else
    {
      if ( $failed )
      {
        echo '<div class="pagenav"><table border="0" cellspacing="0" cellpadding="0">';
        run_test('return false;', $lang->get('sysreqs_summary_fail_title'), $lang->get('sysreqs_summary_fail_body'));
        echo '</table></div>';
      }
    }
    ?>
    <?php
    break;
  case "database":
    ?>
    <script type="text/javascript">
      function ajaxGet(uri, f) {
        if (window.XMLHttpRequest) {
          ajax = new XMLHttpRequest();
        } else {
          if (window.ActiveXObject) {           
            ajax = new ActiveXObject("Microsoft.XMLHTTP");
          } else {
            alert('Enano client-side runtime error: No AJAX support, unable to continue');
            return;
          }
        }
        ajax.onreadystatechange = f;
        ajax.open('GET', uri, true);
        ajax.send(null);
      }
      
      function ajaxPost(uri, parms, f) {
        if (window.XMLHttpRequest) {
          ajax = new XMLHttpRequest();
        } else {
          if (window.ActiveXObject) {           
            ajax = new ActiveXObject("Microsoft.XMLHTTP");
          } else {
            alert('Enano client-side runtime error: No AJAX support, unable to continue');
            return;
          }
        }
        ajax.onreadystatechange = f;
        ajax.open('POST', uri, true);
        ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
        ajax.setRequestHeader("Content-length", parms.length);
        ajax.setRequestHeader("Connection", "close");
        ajax.send(parms);
      }
      function ajaxTestConnection()
      {
        v = verify();
        if(!v)
        {
          alert($lang.get('meta_msg_err_verification'));
          return false;
        }
        var frm = document.forms.dbinfo;
        db_host      = escape(frm.db_host.value.replace('+', '%2B'));
        db_name      = escape(frm.db_name.value.replace('+', '%2B'));
        db_user      = escape(frm.db_user.value.replace('+', '%2B'));
        db_pass      = escape(frm.db_pass.value.replace('+', '%2B'));
        db_root_user = escape(frm.db_root_user.value.replace('+', '%2B'));
        db_root_pass = escape(frm.db_root_pass.value.replace('+', '%2B'));
        
        parms = 'host='+db_host+'&name='+db_name+'&user='+db_user+'&pass='+db_pass+'&root_user='+db_root_user+'&root_pass='+db_root_pass;
        ajaxPost('<?php echo scriptPath; ?>/install.php?mode=mysql_test', parms, function() {
            if(ajax.readyState==4)
            {
              s = ajax.responseText.substr(0, 4);
              t = ajax.responseText.substr(4, ajax.responseText.length);
              if(s.substr(0, 4)=='good')
              {
                document.getElementById('s_db_host').src='images/good.gif';
                document.getElementById('s_db_name').src='images/good.gif';
                document.getElementById('s_db_auth').src='images/good.gif';
                document.getElementById('s_db_root').src='images/good.gif';
                if(t.match(/_creating_db/)) document.getElementById('e_db_name').innerHTML = $lang.get('database_msg_warn_creating_db');
                if(t.match(/_creating_user/)) document.getElementById('e_db_auth').innerHTML = $lang.get('database_msg_warn_creating_user');
                document.getElementById('s_mysql_version').src='images/good.gif';
                document.getElementById('e_mysql_version').innerHTML = $lang.get('database_msg_info_mysql_good');
              }
              else
              {
                switch(s)
                {
                case 'host':
                  document.getElementById('s_db_host').src='images/bad.gif';
                  document.getElementById('s_db_name').src='images/unknown.gif';
                  document.getElementById('s_db_auth').src='images/unknown.gif';
                  document.getElementById('s_db_root').src='images/unknown.gif';
                  document.getElementById('e_db_host').innerHTML = $lang.get('database_msg_err_mysql_connect', { db_host: document.forms.dbinfo.db_host.value, mysql_error: t });
                  document.getElementById('e_mysql_version').innerHTML = $lang.get('database_msg_warn_mysql_version');
                  break;
                case 'auth':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/unknown.gif';
                  document.getElementById('s_db_auth').src='images/bad.gif';
                  document.getElementById('s_db_root').src='images/unknown.gif';
                  document.getElementById('e_db_auth').innerHTML = $lang.get('database_msg_err_mysql_auth', { mysql_error: t });
                  document.getElementById('e_mysql_version').innerHTML = $lang.get('database_msg_warn_mysql_version');
                  break;
                case 'perm':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/bad.gif';
                  document.getElementById('s_db_auth').src='images/good.gif';
                  document.getElementById('s_db_root').src='images/unknown.gif';
                  document.getElementById('e_db_name').innerHTML = $lang.get('database_msg_err_mysql_dbperm', { mysql_error: t });
                  document.getElementById('e_mysql_version').innerHTML = $lang.get('database_msg_warn_mysql_version');
                  break;
                case 'name':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/bad.gif';
                  document.getElementById('s_db_auth').src='images/good.gif';
                  document.getElementById('s_db_root').src='images/unknown.gif';
                  document.getElementById('e_db_name').innerHTML = $lang.get('database_msg_err_mysql_dbexist', { mysql_error: t });
                  document.getElementById('e_mysql_version').innerHTML = $lang.get('database_msg_warn_mysql_version');
                  break;
                case 'root':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/unknown.gif';
                  document.getElementById('s_db_auth').src='images/unknown.gif';
                  document.getElementById('s_db_root').src='images/bad.gif';
                  document.getElementById('e_db_root').innerHTML = $lang.get('database_msg_err_mysql_auth', { mysql_error: t });
                  document.getElementById('e_mysql_version').innerHTML = $lang.get('database_msg_warn_mysql_version');
                  break;
                case 'vers':
                  document.getElementById('s_db_host').src='images/good.gif';
                  document.getElementById('s_db_name').src='images/good.gif';
                  document.getElementById('s_db_auth').src='images/good.gif';
                  document.getElementById('s_db_root').src='images/good.gif';
                  if(t.match(/_creating_db/)) document.getElementById('e_db_name').innerHTML = $lang.get('database_msg_warn_creating_db');
                  if(t.match(/_creating_user/)) document.getElementById('e_db_auth').innerHTML = $lang.get('database_msg_warn_creating_user');
                  
                  document.getElementById('e_mysql_version').innerHTML = $lang.get('database_msg_err_mysql_version', { mysql_version: t });
                  document.getElementById('s_mysql_version').src='images/bad.gif';
                default:
                  alert(t);
                  break;
                }
              }
            }
          });
      }
      function verify()
      {
        document.getElementById('e_db_host').innerHTML = '';
        document.getElementById('e_db_auth').innerHTML = '';
        document.getElementById('e_db_name').innerHTML = '';
        document.getElementById('e_db_root').innerHTML = '';
        var frm = document.forms.dbinfo;
        ret = true;
        if(frm.db_host.value != '')
        {
          document.getElementById('s_db_host').src='images/unknown.gif';
        }
        else
        {
          document.getElementById('s_db_host').src='images/bad.gif';
          ret = false;
        }
        if(frm.db_name.value.match(/^([a-z0-9_]+)$/g))
        {
          document.getElementById('s_db_name').src='images/unknown.gif';
        }
        else
        {
          document.getElementById('s_db_name').src='images/bad.gif';
          ret = false;
        }
        if(frm.db_user.value != '')
        {
          document.getElementById('s_db_auth').src='images/unknown.gif';
        }
        else
        {
          document.getElementById('s_db_auth').src='images/bad.gif';
          ret = false;
        }
        if(frm.table_prefix.value.match(/^([a-z0-9_]*)$/g))
        {
          document.getElementById('s_table_prefix').src='images/good.gif';
        }
        else
        {
          document.getElementById('s_table_prefix').src='images/bad.gif';
          ret = false;
        }
        if(frm.db_root_user.value == '')
        {
          document.getElementById('s_db_root').src='images/good.gif';
        }
        else if(frm.db_root_user.value != '' && frm.db_root_pass.value == '')
        {
          document.getElementById('s_db_root').src='images/bad.gif';
          ret = false;
        }
        else
        {
          document.getElementById('s_db_root').src='images/unknown.gif';
        }
        if(ret) frm._cont.disabled = false;
        else    frm._cont.disabled = true;
        return ret;
      }
      window.onload = verify;
    </script>
    <p><?php echo $lang->get('database_blurb_needdb'); ?></p>
    <p><?php echo $lang->get('database_blurb_howtomysql'); ?></p>
    <?php
    if ( file_exists('/etc/enano-is-virt-appliance') )
    {
      echo '<p>
              ' . $lang->get('database_vm_login_info', array( 'host' => 'localhost', 'user' => 'enano', 'pass' => 'clurichaun', 'name' => 'enano_www1' )) . '
            </p>';
    }
    ?>
    <form name="dbinfo" action="install.php?mode=website" method="post">
      <table border="0">
        <tr>
          <td colspan="3" style="text-align: center">
            <h3><?php echo $lang->get('database_table_title'); ?></h3>
          </td>
        </tr>
        <tr>
          <td>
            <b><?php echo $lang->get('database_field_hostname_title'); ?></b>
            <br /><?php echo $lang->get('database_field_hostname_body'); ?>
            <br /><span style="color: #993300" id="e_db_host"></span>
          </td>
          <td>
            <input onkeyup="verify();" name="db_host" size="30" type="text" />
          </td>
          <td>
            <img id="s_db_host" alt="Good/bad icon" src="images/bad.gif" />
          </td>
        </tr>
        <tr>
          <td>
            <b><?php echo $lang->get('database_field_dbname_title'); ?></b><br />
            <?php echo $lang->get('database_field_dbname_body'); ?><br />
            <span style="color: #993300" id="e_db_name"></span>
          </td>
          <td>
            <input onkeyup="verify();" name="db_name" size="30" type="text" />
          </td>
          <td>
            <img id="s_db_name" alt="Good/bad icon" src="images/bad.gif" />
          </td>
        </tr>
        <tr>
          <td rowspan="2">
            <b><?php echo $lang->get('database_field_dbauth_title'); ?></b><br />
            <?php echo $lang->get('database_field_dbauth_body'); ?><br />
            <span style="color: #993300" id="e_db_auth"></span>
          </td>
          <td>
            <input onkeyup="verify();" name="db_user" size="30" type="text" />
          </td>
          <td rowspan="2">
            <img id="s_db_auth" alt="Good/bad icon" src="images/bad.gif" />
          </td>
        </tr>
        <tr>
          <td>
            <input name="db_pass" size="30" type="password" />
          </td>
        </tr>
        <tr>
          <td colspan="3" style="text-align: center">
            <h3><?php echo $lang->get('database_heading_optionalinfo'); ?></h3>
          </td>
        </tr>
        <tr>
          <td>
            <b><?php echo $lang->get('database_field_tableprefix_title'); ?></b><br />
            <?php echo $lang->get('database_field_tableprefix_body'); ?>
          </td>
          <td>
            <input onkeyup="verify();" name="table_prefix" size="30" type="text" />
          </td>
          <td>
            <img id="s_table_prefix" alt="Good/bad icon" src="images/good.gif" />
          </td>
        </tr>
        <tr>
          <td rowspan="2">
            <b><?php echo $lang->get('database_field_rootauth_title'); ?></b><br />
            <?php echo $lang->get('database_field_rootauth_body'); ?><br />
            <span style="color: #993300" id="e_db_root"></span>
          </td>
          <td>
            <input onkeyup="verify();" name="db_root_user" size="30" type="text" />
          </td>
          <td rowspan="2">
            <img id="s_db_root" alt="Good/bad icon" src="images/good.gif" />
          </td>
        </tr>
        <tr>
          <td>
            <input onkeyup="verify();" name="db_root_pass" size="30" type="password" />
          </td>
        </tr>
        <tr>
          <td>
            <b><?php echo $lang->get('database_field_mysqlversion_title'); ?></b>
          </td>
          <td id="e_mysql_version">
            <?php echo $lang->get('database_field_mysqlversion_blurb_willbechecked'); ?>
          </td>
          <td>
            <img id="s_mysql_version" alt="Good/bad icon" src="images/unknown.gif" />
          </td>
        </tr>
        <tr>
          <td>
            <b><?php echo $lang->get('database_field_droptables_title'); ?></b><br />
            <?php echo $lang->get('database_field_droptables_body'); ?>
          </td>
          <td>
            <input type="checkbox" name="drop_tables" id="dtcheck" />  <label for="dtcheck"><?php echo $lang->get('database_field_droptables_lbl'); ?></label>
          </td>
        </tr>
        <tr>
          <td colspan="3" style="text-align: center">
            <input type="button" value="<?php echo $lang->get('database_btn_testconnection'); ?>" onclick="ajaxTestConnection();" />
          </td>
        </tr>
      </table>
      <div class="pagenav">
        <table border="0">
          <tr>
            <td>
              <input type="submit" value="<?php echo $lang->get('meta_btn_continue'); ?>" onclick="return verify();" name="_cont" />
            </td>
            <td>
              <p>
                <span style="font-weight: bold;"><?php echo $lang->get('meta_lbl_before_continue'); ?></span><br />
                &bull; <?php echo $lang->get('database_objective_test'); ?><br />
                &bull; <?php echo $lang->get('database_objective_uncrypt'); ?>
              </p>
            </td>
          </tr>
        </table>
      </div>
    </form>
    <?php
    break;
  case "website":
    if ( !isset($_POST['_cont']) )
    {
      echo 'No POST data signature found. Please <a href="install.php?mode=license">restart the installation</a>.';
      $template->footer();
      exit;
    }
    unset($_POST['_cont']);
    ?>
    <script type="text/javascript">
      function verify()
      {
        var frm = document.forms.siteinfo;
        ret = true;
        if(frm.sitename.value.match(/^(.+)$/g) && frm.sitename.value != 'Enano')
        {
          document.getElementById('s_name').src='images/good.gif';
        }
        else
        {
          document.getElementById('s_name').src='images/bad.gif';
          ret = false;
        }
        if(frm.sitedesc.value.match(/^(.+)$/g))
        {
          document.getElementById('s_desc').src='images/good.gif';
        }
        else
        {
          document.getElementById('s_desc').src='images/bad.gif';
          ret = false;
        }
        if(frm.copyright.value.match(/^(.+)$/g))
        {
          document.getElementById('s_copyright').src='images/good.gif';
        }
        else
        {
          document.getElementById('s_copyright').src='images/bad.gif';
          ret = false;
        }
        if(ret) frm._cont.disabled = false;
        else    frm._cont.disabled = true;
        return ret;
      }
      window.onload = verify;
    </script>
    <form name="siteinfo" action="install.php?mode=login" method="post">
      <?php
        $k = array_keys($_POST);
        for($i=0;$i<sizeof($_POST);$i++) {
          echo '<input type="hidden" name="'.htmlspecialchars($k[$i]).'" value="'.htmlspecialchars($_POST[$k[$i]]).'" />'."\n";
        }
      ?>
      <p>The next step is to enter some information about your website. You can always change this information later, using the administration panel.</p>
      <table border="0">
        <tr><td><b>Website name</b><br />The display name of your website. Allowed characters are uppercase and lowercase letters, numerals, and spaces. This must not be blank or "Enano".</td><td><input onkeyup="verify();" name="sitename" type="text" size="30" /></td><td><img id="s_name" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr><td><b>Website description</b><br />This text will be shown below the name of your website.</td><td><input onkeyup="verify();" name="sitedesc" type="text" size="30" /></td><td><img id="s_desc" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr><td><b>Copyright info</b><br />This should be a one-line legal notice that will appear at the bottom of all your pages.</td><td><input onkeyup="verify();" name="copyright" type="text" size="30" /></td><td><img id="s_copyright" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr><td><b>Wiki mode</b><br />This feature allows people to create and edit pages on your site. Enano keeps a history of all page modifications, and you can protect pages to prevent editing.</td><td><input name="wiki_mode" type="checkbox" id="wmcheck" />  <label for="wmcheck">Yes, make my website a wiki.</label></td><td></td></tr>
        <tr><td><b>URL scheme</b><br />Choose how the page URLs will look. Depending on your server configuration, you may need to select the first option. If you don't know, select the first option, and you can always change it later.</td><td colspan="2"><input type="radio" <?php if(!is_apache()) echo 'checked="checked" '; ?>name="urlscheme" value="ugly" id="ugly">  <label for="ugly">Standard URLs - compatible with any web server (www.example.com/index.php?title=Page_name)</label><br /><input type="radio" <?php if(is_apache()) echo 'checked="checked" '; ?>name="urlscheme" value="short" id="short">  <label for="short">Short URLs - requires Apache with a PHP module (www.example.com/index.php/Page_name)</label><br /><input type="radio" name="urlscheme" value="tiny" id="petite">  <label for="petite">Tiny URLs - requires Apache on Linux/Unix/BSD with PHP module and mod_rewrite enabled (www.example.com/Page_name)</label></td></tr>
      </table>
      <div class="pagenav">
       <table border="0">
       <tr>
       <td><input type="submit" value="Continue" onclick="return verify();" name="_cont" /></td><td><p><span style="font-weight: bold;"><?php echo $lang->get('meta_lbl_before_continue'); ?></span><br />&bull; Verify that your site information is correct. Again, all of the above settings can be changed from the administration panel.</p></td>
       </tr>
       </table>
     </div>
    </form>
    <?php
    break;
  case "login":
    if(!isset($_POST['_cont'])) {
      echo 'No POST data signature found. Please <a href="install.php?mode=license">restart the installation</a>.';
      $template->footer();
      exit;
    }
    unset($_POST['_cont']);
    require('config.new.php');
    $aes = new AESCrypt(AES_BITS, AES_BLOCKSIZE);
    if ( isset($crypto_key) )
    {
      $cryptkey = $crypto_key;
    }
    if(!isset($cryptkey) || ( isset($cryptkey) && strlen($cryptkey) != AES_BITS / 4) )
    {
      $cryptkey = $aes->gen_readymade_key();
      $handle = @fopen(ENANO_ROOT.'/config.new.php', 'w');
      if(!$handle)
      {
        echo '<p>ERROR: Cannot open config.php for writing - exiting!</p>';
        $template->footer();
        exit;
      }
      fwrite($handle, '<?php $cryptkey = \''.$cryptkey.'\'; ?>');
      fclose($handle);
    }
    // Sorry for the ugly hack, but this f***s up jEdit badly.
    echo '
    <script type="text/javascript">
      function verify()
      {
        var frm = document.forms.login;
        ret = true;
        if ( frm.admin_user.value.match(/^([A-z0-9 \\-\\.]+)$/) && !frm.admin_user.value.match(/^(?:(?:\\d{1,2}|1\\d\\d|2[0-4]\\d|25[0-5])\\.){3}(?:\\d{1,2}|1\\d\\d|2[0-4]\\d|25[0-5])$/) && frm.admin_user.value.toLowerCase() != \'anonymous\' )
        {
          document.getElementById(\'s_user\').src = \'images/good.gif\';
        }
        else
        {
          document.getElementById(\'s_user\').src = \'images/bad.gif\';
          ret = false;
        }
        if(frm.admin_pass.value.length >= 6 && frm.admin_pass.value == frm.admin_pass_confirm.value)
        {
          document.getElementById(\'s_password\').src = \'images/good.gif\';
        }
        else
        {
          document.getElementById(\'s_password\').src = \'images/bad.gif\';
          ret = false;
        }
        if(frm.admin_email.value.match(/^(?:[\\w\\d]+\\.?)+@(?:(?:[\\w\\d]\\-?)+\\.)+\\w{2,4}$/))
        {
          document.getElementById(\'s_email\').src = \'images/good.gif\';
        }
        else
        {
          document.getElementById(\'s_email\').src = \'images/bad.gif\';
          ret = false;
        }
        if(ret) frm._cont.disabled = false;
        else    frm._cont.disabled = true;
        return ret;
      }
      window.onload = verify;
      
      function cryptdata() 
      {
        if(!verify()) return false;
      }
    </script>
    ';
    ?>
    <form name="login" action="install.php?mode=confirm" method="post" onsubmit="runEncryption();">
      <?php
        $k = array_keys($_POST);
        for($i=0;$i<sizeof($_POST);$i++) {
          echo '<input type="hidden" name="'.htmlspecialchars($k[$i]).'" value="'.htmlspecialchars($_POST[$k[$i]]).'" />'."\n";
        }
      ?>
      <p>Next, enter your desired username and password. The account you create here will be used to administer your site.</p>
      <table border="0">
        <tr><td><b>Administration username</b><br /><small>The administration username you will use to log into your site.<br />This cannot be "anonymous" or in the form of an IP address.</small></td><td><input onkeyup="verify();" name="admin_user" type="text" size="30" /></td><td><img id="s_user" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr><td>Administration password:</td><td><input onkeyup="verify();" name="admin_pass" type="password" size="30" /></td><td rowspan="2"><img id="s_password" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr><td>Enter it again to confirm:</td><td><input onkeyup="verify();" name="admin_pass_confirm" type="password" size="30" /></td></tr>
        <tr><td>Your e-mail address:</td><td><input onkeyup="verify();" name="admin_email" type="text" size="30" /></td><td><img id="s_email" alt="Good/bad icon" src="images/bad.gif" /></td></tr>
        <tr>
          <td>
            Allow administrators to embed PHP code into pages:<br />
            <small><span style="color: #D84308">Do not under any circumstances enable this option without reading these
                   <a href="install.php?mode=pophelp&amp;topic=admin_embed_php"
                      onclick="window.open(this.href, 'pophelpwin', 'width=550,height=400,status=no,toolbars=no,toolbar=no,address=no,scroll=yes'); return false;"
                      style="color: #D84308; text-decoration: underline;">important security implications</a>.
            </span></small>
          </td>
          <td>
            <label><input type="radio" name="admin_embed_php" value="2" checked="checked" /> Disabled</label>&nbsp;&nbsp;
            <label><input type="radio" name="admin_embed_php" value="4" /> Enabled</label>
          </td>
          <td></td>
        </tr>
        <tr><td colspan="3">If your browser supports Javascript, the password you enter here will be encrypted with AES before it is sent to the server.</td></tr>
      </table>
      <div class="pagenav">
       <table border="0">
       <tr>
       <td><input type="submit" value="Continue" onclick="return cryptdata();" name="_cont" /></td><td><p><span style="font-weight: bold;"><?php echo $lang->get('meta_lbl_before_continue'); ?></span><br />&bull; Remember the username and password you enter here! You will not be able to administer your site without the information you enter on this page.</p></td>
       </tr>
       </table>
      </div>
      <div id="cryptdebug"></div>
     <input type="hidden" name="use_crypt" value="no" />
     <input type="hidden" name="crypt_key" value="<?php echo $cryptkey; ?>" />
     <input type="hidden" name="crypt_data" value="" />
    </form>
    <script type="text/javascript">
    // <![CDATA[
      var frm = document.forms.login;
      frm.admin_user.focus();
      function runEncryption()
      {
        str = '';
        for(i=0;i<keySizeInBits/4;i++) str+='0';
        var key = hexToByteArray(str);
        var pt = hexToByteArray(str);
        var ct = rijndaelEncrypt(pt, key, "ECB");
        var ect = byteArrayToHex(ct);
        switch(keySizeInBits)
        {
          case 128:
            v = '66e94bd4ef8a2c3b884cfa59ca342b2e';
            break;
          case 192:
            v = 'aae06992acbf52a3e8f4a96ec9300bd7aae06992acbf52a3e8f4a96ec9300bd7';
            break;
          case 256:
            v = 'dc95c078a2408989ad48a21492842087dc95c078a2408989ad48a21492842087';
            break;
        }
        var testpassed = ( ect == v && md5_vm_test() );
        var frm = document.forms.login;
        if(testpassed)
        {
          // alert('encryption self-test passed');
          frm.use_crypt.value = 'yes';
          var cryptkey = frm.crypt_key.value;
          frm.crypt_key.value = '';
          if(cryptkey != byteArrayToHex(hexToByteArray(cryptkey)))
          {
            alert('Byte array conversion SUCKS');
            testpassed = false;
          }
          cryptkey = hexToByteArray(cryptkey);
          if(!cryptkey || ( ( typeof cryptkey == 'string' || typeof cryptkey == 'object' ) ) && cryptkey.length != keySizeInBits / 8 )
          {
            frm._cont.disabled = true;
            len = ( typeof cryptkey == 'string' || typeof cryptkey == 'object' ) ? '\nLen: '+cryptkey.length : '';
            alert('The key is messed up\nType: '+typeof(cryptkey)+len);
          }
        }
        else
        {
          // alert('encryption self-test FAILED');
        }
        if(testpassed)
        {
          pass = frm.admin_pass.value;
          pass = stringToByteArray(pass);
          cryptstring = rijndaelEncrypt(pass, cryptkey, 'ECB');
          //decrypted = rijndaelDecrypt(cryptstring, cryptkey, 'ECB');
          //decrypted = byteArrayToString(decrypted);
          //return false;
          if(!cryptstring)
          {
            return false;
          }
          cryptstring = byteArrayToHex(cryptstring);
          // document.getElementById('cryptdebug').innerHTML = '<pre>Data: '+cryptstring+'<br />Key:  '+byteArrayToHex(cryptkey)+'</pre>';
          frm.crypt_data.value = cryptstring;
          frm.admin_pass.value = '';
          frm.admin_pass_confirm.value = '';
        }
        return false;
      }
      // ]]>
    </script>
    <?php
    break;
  case "confirm":
    if(!isset($_POST['_cont'])) {
      echo 'No POST data signature found. Please <a href="install.php?mode=sysreqs">restart the installation</a>.';
      $template->footer();
      exit;
    }
    unset($_POST['_cont']);
    ?>
    <form name="confirm" action="install.php?mode=install" method="post">
      <?php
        $k = array_keys($_POST);
        for($i=0;$i<sizeof($_POST);$i++) {
          echo '<input type="hidden" name="'.htmlspecialchars($k[$i]).'" value="'.htmlspecialchars($_POST[$k[$i]]).'" />'."\n";
        }
      ?>
      <h3>Enano is ready to install.</h3>
       <p>The wizard has finished collecting information and is ready to install the database schema. Please review the information below,
          and then click the button below to install the database.</p>
      <ul>
        <li>Database hostname: <?php echo $_POST['db_host']; ?></li>
        <li>Database name: <?php echo $_POST['db_name']; ?></li>
        <li>Database user: <?php echo $_POST['db_user']; ?></li>
        <li>Database password: &lt;hidden&gt;</li>
        <li>Site name: <?php echo $_POST['sitename']; ?></li>
        <li>Site description: <?php echo $_POST['sitedesc']; ?></li>
        <li>Administration username: <?php echo $_POST['admin_user']; ?></li>
        <li>Cipher strength: <?php echo (string)AES_BITS; ?>-bit AES<br /><small>Cipher strength is defined in the file constants.php; if you desire to change the cipher strength, you may do so and then restart installation. Unless your site is mission-critical, changing the cipher strength is not necessary.</small></li>
      </ul>
      <div class="pagenav">
        <table border="0">
          <tr>
            <td><input type="submit" value="Install Enano!" name="_cont" /></td><td><p><span style="font-weight: bold;"><?php echo $lang->get('meta_lbl_before_continue'); ?></span><br />&bull; Pray.</p></td>
          </tr>
        </table>
      </div>
    </form>
    <?php
    break;
  case "install":
    if(!isset($_POST['db_host']) ||
       !isset($_POST['db_name']) ||
       !isset($_POST['db_user']) ||
       !isset($_POST['db_pass']) ||
       !isset($_POST['sitename']) ||
       !isset($_POST['sitedesc']) ||
       !isset($_POST['copyright']) ||
       !isset($_POST['admin_user']) ||
       !isset($_POST['admin_pass']) ||
       !isset($_POST['admin_embed_php']) || ( isset($_POST['admin_embed_php']) && !in_array($_POST['admin_embed_php'], array('2', '4')) ) ||
       !isset($_POST['urlscheme'])
       )
    {
      echo 'The installer has detected that one or more required form values is not set. Please <a href="install.php?mode=license">restart the installation</a>.';
      $template->footer();
      exit;
    }
    switch($_POST['urlscheme'])
    {
      case "ugly":
      default:
        $cp = scriptPath.'/index.php?title=';
        break;
      case "short":
        $cp = scriptPath.'/index.php/';
        break;
      case "tiny":
        $cp = scriptPath.'/';
        break;
    }
    function err($t) { global $template; echo $t; $template->footer(); exit; }
    
    // $stages = array('connect', 'decrypt', 'genkey', 'parse', 'sql', 'writeconfig', 'renameconfig', 'startapi', 'initlogs');
    
    if ( !preg_match('/^[a-z0-9_]*$/', $_POST['table_prefix']) )
      err('Hacking attempt was detected in table_prefix.');
    
      start_install_table();
      // The stages connect, decrypt, genkey, and parse are preprocessing and don't do any actual data modification.
      // Thus, they need to be run on each retry, e.g. never skipped.
      run_installer_stage('connect', 'Connect to MySQL', 'stg_mysql_connect', 'MySQL denied our attempt to connect to the database. This is most likely because your login information was incorrect. You will most likely need to <a href="install.php?mode=license">restart the installation</a>.', false);
      if ( isset($_POST['drop_tables']) )
      {
        // Are we supposed to drop any existing tables? If so, do it now
        run_installer_stage('drop', 'Drop existing Enano tables', 'stg_drop_tables', 'This step never returns failure');
      }
      run_installer_stage('decrypt', 'Decrypt administration password', 'stg_decrypt_admin_pass', 'The administration password you entered couldn\'t be decrypted. It is possible that your server did not properly store the encryption key in the configuration file. Please check the file permissions on config.new.php. You may have to return to the login stage of the installation, clear your browser cache, and then rerun this installation.', false);
      run_installer_stage('genkey', 'Generate ' . AES_BITS . '-bit AES private key', 'stg_generate_aes_key', 'Enano encountered an internal error while generating the site encryption key. Please contact the Enano team for support.', false);
      run_installer_stage('parse', 'Prepare to execute schema file', 'stg_parse_schema', 'Enano encountered an internal error while parsing the SQL file that contains the database structure and initial data. Please contact the Enano team for support.', false);
      run_installer_stage('sql', 'Execute installer schema', 'stg_install', 'The installation failed because an SQL query wasn\'t quite correct. It is possible that you entered malformed data into a form field, or there may be a bug in Enano with your version of MySQL. Please contact the Enano team for support.', false);
      run_installer_stage('writeconfig', 'Write configuration files', 'stg_write_config', 'Enano was unable to write the configuration file with your site\'s database credentials. This is almost always because your configuration file does not have the correct permissions. On Windows servers, you may see this message even if the check on the System Requirements page passed. Temporarily running IIS as the Administrator user may help.');
      run_installer_stage('renameconfig', 'Rename configuration files', 'stg_rename_config', 'Enano couldn\'t rename the configuration files to their correct production names. On some UNIX systems, you need to CHMOD the directory with your Enano files to 777 in order for this stage to succeed.');
      
      // Mainstream installation complete - Enano should be usable now
      // The stage of starting the API is special because it has to be called out of function context.
      // To alleviate this, we have two functions, one that returns success and one that returns failure
      // If the Enano API load is successful, the success function is called to report the action to the user
      // If unsuccessful, the failure report is sent
      
      $template_bak = $template;
      
      $_GET['title'] = 'Main_Page';
      require('includes/common.php');
      
      if ( is_object($db) && is_object($session) )
      {
        run_installer_stage('startapi', 'Start the Enano API', 'stg_start_api_success', '...', false);
      }
      else
      {
        run_installer_stage('startapi', 'Start the Enano API', 'stg_start_api_failure', 'The Enano API could not be started. This is an error that should never occur; please contact the Enano team for support.', false);
      }
      
      // We need to be logged in (with admin rights) before logs can be flushed
      $admin_password = stg_decrypt_admin_pass(true);
      $session->login_without_crypto($_POST['admin_user'], $admin_password, false);
      
      // Now that login cookies are set, initialize the session manager and ACLs
      $session->start();
      $paths->init();
      
      run_installer_stage('importlang', 'Import default language', 'stg_import_language', 'Enano couldn\'t import the English language file.');
      
      run_installer_stage('initlogs', 'Initialize logs', 'stg_init_logs', '<b>The session manager denied the request to flush logs for the main page.</b><br />
                           While under most circumstances you can still <a href="install.php?mode=finish">finish the installation</a>, you should be aware that some servers cannot
                           properly set cookies due to limitations with PHP. These limitations are exposed primarily when this issue is encountered during installation. If you choose
                           to finish the installation, please be aware that you may be unable to log into your site.');
      close_install_table();
      
      unset($template);
      $template =& $template_bak;
    
      echo '<h3>Installation of Enano is complete.</h3><p>Review any warnings above, and then <a href="install.php?mode=finish">click here to finish the installation</a>.';
      
      // echo '<script type="text/javascript">window.location="'.scriptPath.'/install.php?mode=finish";</script>';
      
    break;
  case "finish":
    echo '<h3>Congratulations!</h3>
           <p>You have finished installing Enano on this server.</p>
          <h3>Now what?</h3>
           <p>Click the link below to see the main page for your website. Where to go from here:</p>
           <ul>
             <li>The first thing you should do is log into your site using the Log in link on the sidebar.</li>
             <li>Go into the Administration panel, expand General, and click General Configuration. There you will be able to configure some basic information about your site.</li>
             <li>Visit the <a href="http://enanocms.org/Category:Plugins" onclick="window.open(this.href); return false;">Enano Plugin Gallery</a> to download and use plugins on your site.</li>
             <li>Periodically create a backup of your database and filesystem, in case something goes wrong. This should be done at least once a week &ndash; more for wiki-based sites.</li>
             <li>Hire some moderators, to help you keep rowdy users tame.</li>
             <li>Tell the <a href="http://enanocms.org/Contact_us">Enano team</a> what you think.</li>
             <li><b>Spread the word about Enano by adding a link to the Enano homepage on your sidebar!</b> You can enable this option in the General Configuration section of the administration panel.</li>
           </ul>
           <p><a href="index.php">Go to your website...</a></p>';
    break;
}
$template->footer();
 
?>
