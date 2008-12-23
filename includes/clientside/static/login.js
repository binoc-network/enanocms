/*
 * AJAX-based intelligent login interface
 */

/*
 * FRONTEND
 */

/**
 * Performs a logon as a regular member.
 */

window.ajaxLogonToMember = function()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  if ( auth_level >= USER_LEVEL_MEMBER )
    return true;
  ajaxLoginInit(function(k)
    {
      if ( on_main_page )
      {
        window.location = makeUrl(main_page_members);
      }
      else
      {
        window.location.reload();
      }
    }, USER_LEVEL_MEMBER);
}

/**
 * Authenticates to the highest level the current user is allowed to go to.
 */

window.ajaxLogonToElev = function()
{
  if ( auth_level == user_level )
    return true;
  
  ajaxLoginInit(function(k)
    {
      ENANO_SID = k;
      var url = String(' ' + window.location).substr(1);
      url = append_sid(url);
      window.location = url;
    }, user_level);
}

/*
 * BACKEND
 */

/**
 * Holding object for various AJAX authentication information.
 * @var object
 */

var logindata = {};

/**
 * Path to the image used to indicate loading progress
 * @var string
 */

if ( !ajax_login_loadimg_path )
  var ajax_login_loadimg_path = false;

if ( !ajax_login_successimg_path )
  var ajax_login_successimg_path = false;

/**
 * Status variables
 * @var int
 */

var AJAX_STATUS_LOADING_KEY = 1;
var AJAX_STATUS_GENERATING_KEY = 2;
var AJAX_STATUS_LOGGING_IN = 3;
var AJAX_STATUS_SUCCESS = 4;
var AJAX_STATUS_ERROR = 5;
var AJAX_STATUS_DESTROY = 65535;

/**
 * State constants
 * @var int
 */

var AJAX_STATE_EARLY_INIT = 1;
var AJAX_STATE_LOADING_KEY = 2;

/**
 * Performs the AJAX request to get an encryption key and from there spawns the login form.
 * @param function The function that will be called once authentication completes successfully.
 * @param int The security level to authenticate at - see http://docs.enanocms.org/Help:Appendix_B
 */

window.ajaxLoginInit = function(call_on_finish, user_level)
{
  load_component(['messagebox', 'flyin', 'fadefilter', 'jquery', 'jquery-ui', 'l10n', 'crypto']);
  
  logindata = {};
  
  var title = ( user_level > USER_LEVEL_MEMBER ) ? $lang.get('user_login_ajax_prompt_title_elev') : $lang.get('user_login_ajax_prompt_title');
  logindata.mb_object = new MessageBox(MB_OKCANCEL | MB_ICONLOCK, title, '');
  
  logindata.mb_object.onclick['Cancel'] = function()
  {
    // Hide the error message and captcha
    if ( document.getElementById('ajax_login_error_box') )
    {
      document.getElementById('ajax_login_error_box').parentNode.removeChild(document.getElementById('ajax_login_error_box'));
    }
    if ( document.getElementById('autoCaptcha') )
    {
      var to = fly_out_top(document.getElementById('autoCaptcha'), false, true);
      setTimeout(function() {
          var d = document.getElementById('autoCaptcha');
          d.parentNode.removeChild(d);
        }, to);
    }
    // Ask the server to clean our key
    ajaxLoginPerformRequest({
        mode: 'clean_key',
        key_aes: logindata.key_aes,
        key_dh: logindata.key_dh
    });
  };
  
  logindata.mb_object.onbeforeclick['OK'] = function()
  {
    ajaxLoginSubmitForm();
    return true;
  }
  
  // Fetch the inner content area
  logindata.mb_inner = document.getElementById('messageBox').getElementsByTagName('div')[0];
  
  // Initialize state
  logindata.showing_status = false;
  logindata.user_level = user_level;
  logindata.successfunc = call_on_finish;
  
  // Build the "loading" window
  ajaxLoginSetStatus(AJAX_STATUS_LOADING_KEY);
  
  // Request the key
  ajaxLoginPerformRequest({ mode: 'getkey' });
}

/**
 * For compatibility only.
 */

window.ajaxLogonInit = function(call_on_finish, user_level)
{
  return ajaxLoginInit(call_on_finish, user_level);
}

/**
 * Sets the contents of the AJAX login window to the appropriate status message.
 * @param int One of AJAX_STATUS_*
 */

window.ajaxLoginSetStatus = function(status)
{
  if ( !logindata.mb_inner )
    return false;
  if ( logindata.showing_status )
  {
    var div = document.getElementById('ajax_login_status');
    if ( div )
      logindata.mb_inner.removeChild(div);
  }
  switch(status)
  {
    case AJAX_STATUS_LOADING_KEY:
      
      // Create the status div
      var div = document.createElement('div');
      div.id = 'ajax_login_status';
      div.style.marginTop = '10px';
      div.style.textAlign = 'center';
      
      // The circly ball ajaxy image + status message
      var status_msg = $lang.get('user_login_ajax_fetching_key');
      
      // Insert the status message
      div.appendChild(document.createTextNode(status_msg));
      
      // Append a br or two to space things properly
      div.appendChild(document.createElement('br'));
      div.appendChild(document.createElement('br'));
      
      var img = document.createElement('img');
      img.src = ( ajax_login_loadimg_path ) ? ajax_login_loadimg_path : scriptPath + '/images/loading-big.gif';
      div.appendChild(img);
      
      // Another coupla brs
      div.appendChild(document.createElement('br'));
      div.appendChild(document.createElement('br'));
      
      // The link to the full login form
      var small = document.createElement('small');
      small.innerHTML = $lang.get('user_login_ajax_link_fullform', { link_full_form: makeUrlNS('Special', 'Login/' + title) });
      div.appendChild(small);
      
      // Insert the entire message into the login window
      logindata.mb_inner.innerHTML = '';
      logindata.mb_inner.appendChild(div);
      
      break;
    case AJAX_STATUS_GENERATING_KEY:
      
      // Create the status div
      var div = document.createElement('div');
      div.id = 'ajax_login_status';
      div.style.marginTop = '10px';
      div.style.textAlign = 'center';
      
      // The circly ball ajaxy image + status message
      var status_msg = $lang.get('user_login_ajax_generating_key');
      
      // Insert the status message
      div.appendChild(document.createTextNode(status_msg));
      
      // Append a br or two to space things properly
      div.appendChild(document.createElement('br'));
      div.appendChild(document.createElement('br'));
      
      var img = document.createElement('img');
      img.src = ( ajax_login_loadimg_path ) ? ajax_login_loadimg_path : scriptPath + '/images/loading-big.gif';
      div.appendChild(img);
      
      // Another coupla brs
      div.appendChild(document.createElement('br'));
      div.appendChild(document.createElement('br'));
      
      // The link to the full login form
      var small = document.createElement('small');
      small.innerHTML = $lang.get('user_login_ajax_link_fullform_dh', { link_full_form: makeUrlNS('Special', 'Login/' + title) });
      div.appendChild(small);
      
      // Insert the entire message into the login window
      logindata.mb_inner.innerHTML = '';
      logindata.mb_inner.appendChild(div);
      
      break;
    case AJAX_STATUS_LOGGING_IN:
      
      // Create the status div
      var div = document.createElement('div');
      div.id = 'ajax_login_status';
      div.style.marginTop = '10px';
      div.style.textAlign = 'center';
      
      // The circly ball ajaxy image + status message
      var status_msg = $lang.get('user_login_ajax_loggingin');
      
      // Insert the status message
      div.appendChild(document.createTextNode(status_msg));
      
      // Append a br or two to space things properly
      div.appendChild(document.createElement('br'));
      div.appendChild(document.createElement('br'));
      
      var img = document.createElement('img');
      img.src = ( ajax_login_loadimg_path ) ? ajax_login_loadimg_path : scriptPath + '/images/loading-big.gif';
      div.appendChild(img);
      
      // Insert the entire message into the login window
      logindata.mb_inner.innerHTML = '';
      logindata.mb_inner.appendChild(div);
      
      break;
    case AJAX_STATUS_SUCCESS:
      
      // Create the status div
      var div = document.createElement('div');
      div.id = 'ajax_login_status';
      div.style.marginTop = '10px';
      div.style.textAlign = 'center';
      
      // The circly ball ajaxy image + status message
      var status_msg = $lang.get('user_login_success_short');
      
      // Insert the status message
      div.appendChild(document.createTextNode(status_msg));
      
      // Append a br or two to space things properly
      div.appendChild(document.createElement('br'));
      div.appendChild(document.createElement('br'));
      
      var img = document.createElement('img');
      img.src = ( ajax_login_successimg_path ) ? ajax_login_successimg_path : scriptPath + '/images/check.png';
      div.appendChild(img);
      
      // Insert the entire message into the login window
      logindata.mb_inner.innerHTML = '';
      logindata.mb_inner.appendChild(div);
      
      break;
      
    case AJAX_STATUS_ERROR:
      // Create the status div
      var div = document.createElement('div');
      div.id = 'ajax_login_status';
      div.style.marginTop = '10px';
      div.style.textAlign = 'center';
      
      // The circly ball ajaxy image + status message
      var status_msg = $lang.get('user_login_ajax_err_crypto');
      
      // Insert the status message
      div.appendChild(document.createTextNode(status_msg));
      
      // Append a br or two to space things properly
      div.appendChild(document.createElement('br'));
      div.appendChild(document.createElement('br'));
      
      var img = document.createElement('img');
      img.src = ( ajax_login_successimg_path ) ? ajax_login_successimg_path : scriptPath + '/images/checkbad.png';
      div.appendChild(img);
      
      // Append a br or two to space things properly
      div.appendChild(document.createElement('br'));
      div.appendChild(document.createElement('br'));
      
      // The circly ball ajaxy image + status message
      var detail_msg = $lang.get('user_login_ajax_err_crypto_details');
      var full_link = $lang.get('user_login_ajax_err_crypto_link');
      var link = document.createElement('a');
      link.href = makeUrlNS('Special', 'Login/' + title);
      link.appendChild(document.createTextNode(full_link));
      var span = document.createElement('span');
      span.style.fontSize = 'smaller';
      
      // Insert the message
      span.appendChild(document.createTextNode(detail_msg + ' '));
      span.appendChild(link);
      div.appendChild(span);
      
      // Insert the entire message into the login window
      logindata.mb_inner.innerHTML = '';
      logindata.mb_inner.appendChild(div);
      
      break;
      
    case AJAX_STATUS_DESTROY:
    case null:
    case undefined:
      logindata.showing_status = false;
      return null;
      break;
  }
  logindata.showing_status = true;
}

/**
 * Performs an AJAX logon request to the server and calls ajaxLoginProcessResponse() on the result.
 * @param object JSON packet to send
 */

window.ajaxLoginPerformRequest = function(json)
{
  json = toJSONString(json);
  json = ajaxEscape(json);
  ajaxPost(makeUrlNS('Special', 'Login/action.json'), 'r=' + json, function()
    {
      if ( ajax.readyState == 4 && ajax.status == 200 )
      {
        // parse response
        var response = String(ajax.responseText + '');
        if ( !check_json_response(response) )
        {
          handle_invalid_json(response);
          return false;
        }
        response = parseJSON(response);
        ajaxLoginProcessResponse(response);
      }
    }, true);
}

/**
 * Processes a response from the login server
 * @param object JSON response
 */

window.ajaxLoginProcessResponse = function(response)
{
  // Did the server send a plaintext error?
  if ( response.mode == 'error' )
  {
    logindata.mb_object.destroy();
    var error_msg = $lang.get('user_' + ( response.error.toLowerCase() ));
    new MessageBox(MB_ICONSTOP | MB_OK, $lang.get('user_err_login_generic_title'), error_msg);
    return false;
  }
  // Main mode switch
  switch ( response.mode )
  {
    case 'build_box':
      // Rid ourselves of any loading windows
      ajaxLoginSetStatus(AJAX_STATUS_DESTROY);
      // The server wants us to build the login form, all the information is there
      ajaxLoginBuildForm(response);
      break;
    case 'login_success':
      ajaxLoginSetStatus(AJAX_STATUS_SUCCESS);
      logindata.successfunc(response.key);
      break;
    case 'login_failure':
      // Rid ourselves of any loading windows
      ajaxLoginSetStatus(AJAX_STATUS_DESTROY);
      document.getElementById('messageBox').style.backgroundColor = '#C0C0C0';
      var mb_parent = document.getElementById('messageBox').parentNode;
      $(mb_parent).effect("shake", {}, 200);
      setTimeout(function()
        {
          document.getElementById('messageBox').style.backgroundColor = '#FFF';
          ajaxLoginBuildForm(response.respawn_info);
          ajaxLoginShowFriendlyError(response);
        }, 2500);
      break;
    case 'login_success_reset':
      var conf = confirm($lang.get('user_login_ajax_msg_used_temp_pass'));
      if ( conf )
      {
        var url = makeUrlNS('Special', 'PasswordReset/stage2/' + response.user_id + '/' + response.temp_password);
        window.location = url;
      }
      else
      {
        // treat as a failure
        ajaxLoginSetStatus(AJAX_STATUS_DESTROY);
        document.getElementById('messageBox').style.backgroundColor = '#C0C0C0';
        var mb_parent = document.getElementById('messageBox').parentNode;
        $(mb_parent).effect("shake", {}, 1500);
        setTimeout(function()
          {
            document.getElementById('messageBox').style.backgroundColor = '#FFF';
            ajaxLoginBuildForm(response.respawn_info);
            // don't show an error here, just silently respawn
          }, 2500);
      }
      break;
    case 'noop':
      break;
  }
}

/*
 * RESPONSE HANDLERS
 */

/**
 * Builds the login form.
 * @param object Metadata to build off of
 */

window.ajaxLoginBuildForm = function(data)
{
  // let's hope this effectively preloads the image...
  var _ = document.createElement('img');
  _.src = ( ajax_login_successimg_path ) ? ajax_login_successimg_path : scriptPath + '/images/check.png';
  
  var div = document.createElement('div');
  div.id = 'ajax_login_form';
  
  var show_captcha = ( data.locked_out && data.lockout_info.lockout_policy == 'captcha' ) ? data.lockout_info.captcha : false;
  
  // text displayed on re-auth
  if ( logindata.user_level > USER_LEVEL_MEMBER )
  {
    div.innerHTML += $lang.get('user_login_ajax_prompt_body_elev') + '<br /><br />';
  }
  
  // Create the form
  var form = document.createElement('form');
  form.action = 'javascript:void(ajaxLoginSubmitForm());';
  form.onsubmit = function()
  {
    ajaxLoginSubmitForm();
    return false;
  }
  if ( IE )
  {
    form.style.marginTop = '-20px';
  }
  
  // Using tables to wrap form elements because it results in a
  // more visually appealing form. Yes, tables suck. I don't really
  // care - they make forms look good.
  
  var table = document.createElement('table');
  table.style.margin = '0 auto';
  
  // Field - username
  var tr1 = document.createElement('tr');
  var td1_1 = document.createElement('td');
  td1_1.appendChild(document.createTextNode($lang.get('user_login_field_username') + ':'));
  tr1.appendChild(td1_1);
  var td1_2 = document.createElement('td');
  var f_username = document.createElement('input');
  f_username.id = 'ajax_login_field_username';
  f_username.name = 'ajax_login_field_username';
  f_username.type = 'text';
  f_username.size = '25';
  if ( data.username )
    f_username.value = data.username;
  td1_2.appendChild(f_username);
  tr1.appendChild(td1_2);
  table.appendChild(tr1);
  
  // Field - password
  var tr2 = document.createElement('tr');
  var td2_1 = document.createElement('td');
  td2_1.appendChild(document.createTextNode($lang.get('user_login_field_password') + ':'));
  tr2.appendChild(td2_1);
  var td2_2 = document.createElement('td');
  var f_password = document.createElement('input');
  f_password.id = 'ajax_login_field_password';
  f_password.name = 'ajax_login_field_username';
  f_password.type = 'password';
  f_password.size = '25';
  if ( !show_captcha )
  {
    f_password.onkeyup = function(e)
    {
      if ( !e )
        e = window.event;
      if ( !e && IE )
        return true;
      if ( e.keyCode == 13 )
      {
        ajaxLoginSubmitForm();
      }
    }
  }
  td2_2.appendChild(f_password);
  tr2.appendChild(td2_2);
  table.appendChild(tr2);
  
  // Field - captcha
  if ( show_captcha )
  {
    var tr3 = document.createElement('tr');
    var td3_1 = document.createElement('td');
    td3_1.appendChild(document.createTextNode($lang.get('user_login_field_captcha') + ':'));
    tr3.appendChild(td3_1);
    var td3_2 = document.createElement('td');
    var f_captcha = document.createElement('input');
    f_captcha.id = 'ajax_login_field_captcha';
    f_captcha.name = 'ajax_login_field_username';
    f_captcha.type = 'text';
    f_captcha.size = '25';
    f_captcha.onkeyup = function(e)
    {
      if ( !e )
        e = window.event;
      if ( !e.keyCode )
        return true;
      if ( e.keyCode == 13 )
      {
        ajaxLoginSubmitForm();
      }
    }
    td3_2.appendChild(f_captcha);
    tr3.appendChild(td3_2);
    table.appendChild(tr3);
  }
  
  // Done building the main part of the form
  form.appendChild(table);
  
  // Field: remember login
  if ( logindata.user_level <= USER_LEVEL_MEMBER )
  {
    var lbl_remember = document.createElement('label');
    lbl_remember.style.fontSize = 'smaller';
    lbl_remember.style.display = 'block';
    lbl_remember.style.textAlign = 'center';
    
    // figure out what text to put in the "remember me" checkbox
    // infinite session length?
    if ( data.extended_time == 0 )
    {
      // yes, infinite
      var txt_remember = $lang.get('user_login_ajax_check_remember_infinite');
    }
    else
    {
      if ( data.extended_time % 7 == 0 )
      {
        // number of days is a multiple of 7
        // use weeks as our unit
        var sess_time = data.extended_time / 7;
        var unit = 'week';
      }
      else
      {
        // use days as our unit
        var sess_time = data.extended_time;
        var unit = 'day';
      }
      // more than one week or day?
      if ( sess_time != 1 )
        unit += 's';
      
      // assemble the string
      var txt_remember = $lang.get('user_login_ajax_check_remember', {
          session_length: sess_time,
          length_units: $lang.get('etc_unit_' + unit)
        });
    }
    var check_remember = document.createElement('input');
    check_remember.type = 'checkbox';
    // this onclick attribute changes the cookie whenever the checkbox or label is clicked
    check_remember.setAttribute('onclick', 'var ck = ( this.checked ) ? "enable" : "disable"; createCookie("login_remember", ck, 3650);');
    if ( readCookie('login_remember') != 'disable' )
      check_remember.setAttribute('checked', 'checked');
    check_remember.id = 'ajax_login_field_remember';
    lbl_remember.appendChild(check_remember);
    lbl_remember.innerHTML += ' ' + txt_remember;
    
    form.appendChild(lbl_remember);
  }
  
  // Field: enable Diffie Hellman
  if ( IE || is_iPhone )
  {
    var lbl_dh = document.createElement('span');
    lbl_dh.style.fontSize = 'smaller';
    lbl_dh.style.display = 'block';
    lbl_dh.style.textAlign = 'center';
    lbl_dh.innerHTML = $lang.get('user_login_ajax_check_dh_ie');
    form.appendChild(lbl_dh);
  }
  else if ( !data.allow_diffiehellman )
  {
    // create hidden control - server requested that DiffieHellman be disabled (usually means not supported)
    var check_dh = document.createElement('input');
    check_dh.type = 'hidden';
    check_dh.id = 'ajax_login_field_dh';
    form.appendChild(check_dh);
  }
  else
  {
    var lbl_dh = document.createElement('label');
    lbl_dh.style.fontSize = 'smaller';
    lbl_dh.style.display = 'block';
    lbl_dh.style.textAlign = 'center';
    var check_dh = document.createElement('input');
    check_dh.type = 'checkbox';
    // this onclick attribute changes the cookie whenever the checkbox or label is clicked
    check_dh.setAttribute('onclick', 'var ck = ( this.checked ) ? "enable" : "disable"; createCookie("diffiehellman_login", ck, 3650);');
    if ( readCookie('diffiehellman_login') != 'disable' )
      check_dh.setAttribute('checked', 'checked');
    check_dh.id = 'ajax_login_field_dh';
    lbl_dh.appendChild(check_dh);
    lbl_dh.innerHTML += ' ' + $lang.get('user_login_ajax_check_dh');
    form.appendChild(lbl_dh);
  }
  
  if ( IE )
  {
    div.innerHTML += form.outerHTML;
  }
  else
  {
    div.appendChild(form);
  }
  
  // Diagnostic / help links
  // (only displayed in login, not in re-auth)
  if ( logindata.user_level == USER_LEVEL_MEMBER )
  {
    form.style.marginBottom = '10px';
    var links = document.createElement('small');
    links.style.display = 'block';
    links.style.textAlign = 'center';
    links.innerHTML = '';
    if ( !show_captcha )
      links.innerHTML += $lang.get('user_login_ajax_link_fullform', { link_full_form: makeUrlNS('Special', 'Login/' + title) }) + '<br />';
    // Always shown
    links.innerHTML += $lang.get('user_login_ajax_link_forgotpass', { forgotpass_link: makeUrlNS('Special', 'PasswordReset') }) + '<br />';
    if ( !show_captcha )
      links.innerHTML += $lang.get('user_login_createaccount_blurb', { reg_link: makeUrlNS('Special', 'Register') });
    div.appendChild(links);
  }
  
  // Insert the entire form into the login window
  logindata.mb_inner.innerHTML = '';
  logindata.mb_inner.appendChild(div);
  
  // Post operations: field focus
  if ( IE )
  {
    setTimeout(
      function()
      {
        if ( logindata.loggedin_username )
          document.getElementById('ajax_login_field_password').focus();
        else
          document.getElementById('ajax_login_field_username').focus();
      }, 200);        
  }
  else
  {
    if ( data.username )
      f_password.focus();
    else
      f_username.focus();
  }
  
  // Post operations: show captcha window
  if ( show_captcha )
    ajaxShowCaptcha(show_captcha);
  
  // Post operations: stash encryption keys and All That Jazz(TM)
  logindata.key_aes = data.aes_key;
  logindata.key_dh = data.dh_public_key;
  logindata.captcha_hash = show_captcha;
  logindata.loggedin_username = data.username
  
  // Are we locked out? If so simulate an error and disable the controls
  if ( data.lockout_info.lockout_policy == 'lockout' && data.locked_out )
  {
    f_username.setAttribute('disabled', 'disabled');
    f_password.setAttribute('disabled', 'disabled');
    var fake_packet = {
      error_code: 'locked_out',
      respawn_info: data
    };
    ajaxLoginShowFriendlyError(fake_packet);
  }
}

window.ajaxLoginSubmitForm = function(real, username, password, captcha, remember)
{
  // Perform AES test to make sure it's all working
  if ( !aes_self_test() )
  {
    alert('BUG: AES self-test failed');
    login_cache.mb_object.destroy();
    return false;
  }
  // Hide the error message and captcha
  if ( document.getElementById('ajax_login_error_box') )
  {
    document.getElementById('ajax_login_error_box').parentNode.removeChild(document.getElementById('ajax_login_error_box'));
  }
  if ( document.getElementById('autoCaptcha') )
  {
    var to = fly_out_top(document.getElementById('autoCaptcha'), false, true);
    setTimeout(function() {
        var d = document.getElementById('autoCaptcha');
        d.parentNode.removeChild(d);
      }, to);
  }
  // "Remember session" switch
  if ( typeof(remember) == 'boolean' )
  {
    var remember_session = remember;
  }
  else
  {
    if ( document.getElementById('ajax_login_field_remember') )
    {
      var remember_session = ( document.getElementById('ajax_login_field_remember').checked ) ? true : false;
    }
    else
    {
      var remember_session = false;
    }
  }
  // Encryption: preprocessor
  if ( real )
  {
    var do_dh = true;
  }
  else if ( document.getElementById('ajax_login_field_dh') )
  {
    var do_dh = document.getElementById('ajax_login_field_dh').checked;
  }
  else
  {
    if ( IE || is_iPhone )
    {
      // IE/MobileSafari doesn't have this control, continue silently IF the rest
      // of the login form is there
      if ( !document.getElementById('ajax_login_field_username') )
      {
        return false;
      }
    }
    else
    {
      // The user probably clicked ok when the form wasn't in there.
      return false;
    }
  }
  
  if ( !username )
  {
    var username = document.getElementById('ajax_login_field_username').value;
  }
  if ( !password )
  {
    var password = document.getElementById('ajax_login_field_password').value;
  }
  if ( !captcha && document.getElementById('ajax_login_field_captcha') )
  {
    var captcha = document.getElementById('ajax_login_field_captcha').value;
  }
  
  try
  {
  
  if ( do_dh )
  {
    ajaxLoginSetStatus(AJAX_STATUS_GENERATING_KEY);
    if ( !real )
    {
      // Wait while the browser updates the login window
      setTimeout(function()
        {
          ajaxLoginSubmitForm(true, username, password, captcha, remember_session);
        }, 200);
      return true;
    }
    // Perform Diffie Hellman stuff
    var dh_priv = dh_gen_private();
    var dh_pub = dh_gen_public(dh_priv);
    var secret = dh_gen_shared_secret(dh_priv, logindata.key_dh);
    // secret_hash is used to verify that the server guesses the correct secret
    var secret_hash = hex_sha1(secret);
    // crypt_key is the actual AES key
    var crypt_key = (hex_sha256(secret)).substr(0, (keySizeInBits / 4));
  }
  else
  {
    var crypt_key = logindata.key_aes;
  }
  
  ajaxLoginSetStatus(AJAX_STATUS_LOGGING_IN);
  
  // Encrypt the password and username
  var userinfo = toJSONString({
      username: username,
      password: password
    });
  var crypt_key_ba = hexToByteArray(crypt_key);
  userinfo = stringToByteArray(userinfo);
  
  userinfo = rijndaelEncrypt(userinfo, crypt_key_ba, 'ECB');
  userinfo = byteArrayToHex(userinfo);
  // Encrypted username and password (serialized with JSON) are now in the userinfo string
  
  // Collect other needed information
  if ( logindata.captcha_hash )
  {
    var captcha_hash = logindata.captcha_hash;
    var captcha_code = captcha;
  }
  else
  {
    var captcha_hash = false;
    var captcha_code = false;
  }
  
  // Ship it across the 'net
  if ( do_dh )
  {
    var json_packet = {
      mode: 'login_dh',
      userinfo: userinfo,
      captcha_code: captcha_code,
      captcha_hash: captcha_hash,
      dh_public_key: logindata.key_dh,
      dh_client_key: dh_pub,
      dh_secret_hash: secret_hash,
      level: logindata.user_level,
      remember: remember_session
    }
  }
  else
  {
    var json_packet = {
      mode: 'login_aes',
      userinfo: userinfo,
      captcha_code: captcha_code,
      captcha_hash: captcha_hash,
      key_aes: hex_md5(crypt_key),
      level: logindata.user_level,
      remember: remember_session
    }
  }
  }
  catch(e)
  {
    ajaxLoginSetStatus(AJAX_STATUS_ERROR);
    console.error('Exception caught in login process; backtrace follows');
    console.debug(e);
    return false;
  }
  ajaxLoginPerformRequest(json_packet);
}

window.ajaxLoginShowFriendlyError = function(response)
{
  if ( !response.respawn_info )
    return false;
  if ( !response.error_code )
    return false;
  var text = ajaxLoginGetErrorText(response);
  if ( document.getElementById('ajax_login_error_box') )
  {
    // console.info('Reusing existing error-box');
    document.getElementById('ajax_login_error_box').innerHTML = text;
    return true;
  }
  
  // console.info('Drawing new error-box');
  
  // calculate position for the top of the box
  var mb_bottom = $dynano('messageBoxButtons').Top() + $dynano('messageBoxButtons').Height();
  // if the box isn't done flying in yet, just estimate
  if ( mb_bottom < ( getHeight() / 2 ) )
  {
    mb_bottom = ( getHeight() / 2 ) + 120;
  }
  var win_bottom = getHeight() + getScrollOffset();
  var top = mb_bottom + ( ( win_bottom - mb_bottom ) / 2 ) - 32;
  // left position = 0.2 * window_width, seeing as the box is 60% width this works hackishly but nice and quick
  var left = getWidth() * 0.2;
  
  // create the div
  var errbox = document.createElement('div');
  errbox.className = 'error-box-mini';
  errbox.style.position = 'absolute';
  errbox.style.width = '60%';
  errbox.style.top = top + 'px';
  errbox.style.left = left + 'px';
  errbox.style.zIndex = getHighestZ();
  errbox.innerHTML = text;
  errbox.id = 'ajax_login_error_box';
  
  var body = document.getElementsByTagName('body')[0];
  body.appendChild(errbox);
}

window.ajaxLoginGetErrorText = function(response)
{
  switch ( response.error_code )
  {
    default:
      return $lang.get('user_err_' + response.error_code);
      break;
    case 'locked_out':
      if ( response.respawn_info.lockout_info.lockout_policy == 'lockout' )
      {
        return $lang.get('user_err_locked_out', { 
                  lockout_threshold: response.respawn_info.lockout_info.lockout_threshold,
                  lockout_duration: response.respawn_info.lockout_info.lockout_duration,
                  time_rem: response.respawn_info.lockout_info.time_rem,
                  plural: ( response.respawn_info.lockout_info.time_rem == 1 ) ? '' : $lang.get('meta_plural'),
                  captcha_blurb: ''
                });
        break;
      }
    case 'invalid_credentials':
      var base = $lang.get('user_err_invalid_credentials');
      if ( response.respawn_info.locked_out )
      {
        base += ' ';
        var captcha_blurb = '';
        switch(response.respawn_info.lockout_info.lockout_policy)
        {
          case 'captcha':
            captcha_blurb = $lang.get('user_err_locked_out_captcha_blurb');
            break;
          case 'lockout':
            break;
          default:
            base += 'WTF? Shouldn\'t be locked out with lockout policy set to disable.';
            break;
        }
        base += $lang.get('user_err_locked_out', { 
                  captcha_blurb: captcha_blurb,
                  lockout_threshold: response.respawn_info.lockout_info.lockout_threshold,
                  lockout_duration: response.respawn_info.lockout_info.lockout_duration,
                  time_rem: response.respawn_info.lockout_info.time_rem,
                  plural: ( response.respawn_info.lockout_info.time_rem == 1 ) ? '' : $lang.get('meta_plural')
                });
      }
      else if ( response.respawn_info.lockout_info.lockout_policy == 'lockout' || response.respawn_info.lockout_info.lockout_policy == 'captcha' )
      {
        // if we have a lockout policy of captcha or lockout, then warn the user
        switch ( response.respawn_info.lockout_info.lockout_policy )
        {
          case 'captcha':
            base += $lang.get('user_err_invalid_credentials_lockout', { 
                fails: response.respawn_info.lockout_info.lockout_fails,
                lockout_threshold: response.respawn_info.lockout_info.lockout_threshold,
                lockout_duration: response.respawn_info.lockout_info.lockout_duration
              });
            break;
          case 'lockout':
            break;
        }
      }
      return base;
      break;
  }
}

window.ajaxShowCaptcha = function(code)
{
  var mydiv = document.createElement('div');
  mydiv.style.backgroundColor = '#FFFFFF';
  mydiv.style.padding = '10px';
  mydiv.style.position = 'absolute';
  mydiv.style.top = '0px';
  mydiv.id = 'autoCaptcha';
  mydiv.style.zIndex = String( getHighestZ() + 1 );
  var img = document.createElement('img');
  img.onload = function()
  {
    if ( this.loaded )
      return true;
    var mydiv = document.getElementById('autoCaptcha');
    var width = getWidth();
    var divw = $dynano(mydiv).Width();
    var left = ( width / 2 ) - ( divw / 2 );
    mydiv.style.left = left + 'px';
    fly_in_top(mydiv, false, true);
    this.loaded = true;
  };
  img.src = makeUrlNS('Special', 'Captcha/' + code);
  img.onclick = function() { this.src = this.src + '/a'; };
  img.style.cursor = 'pointer';
  mydiv.appendChild(img);
  domObjChangeOpac(0, mydiv);
  var body = document.getElementsByTagName('body')[0];
  body.appendChild(mydiv);
}

window.ajaxInitLogout = function()
{
  load_component(['messagebox', 'l10n', 'flyin', 'fadefilter']);
  var mb = new MessageBox(MB_YESNO|MB_ICONQUESTION, $lang.get('user_logout_confirm_title'), $lang.get('user_logout_confirm_body'));
  mb.onclick['Yes'] = function()
    {
      window.location = makeUrlNS('Special', 'Logout/' + csrf_token + '/' + title);
    }
}

window.mb_logout = function()
{
  ajaxInitLogout();
}

window.ajaxStartLogin = function()
{
  ajaxLogonToMember();
}

window.ajaxStartAdminLogin = function()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  if ( auth_level < USER_LEVEL_ADMIN )
  {
    ajaxLoginInit(function(k) {
      ENANO_SID = k;
      auth_level = USER_LEVEL_ADMIN;
      var loc = makeUrlNS('Special', 'Administration');
      if ( (ENANO_SID + ' ').length > 1 )
        window.location = loc;
    }, USER_LEVEL_ADMIN);
    return false;
  }
  var loc = makeUrlNS('Special', 'Administration');
  window.location = loc;
}

window.ajaxAdminPage = function()
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  if ( auth_level < USER_LEVEL_ADMIN )
  {
    ajaxPromptAdminAuth(function(k) {
      ENANO_SID = k;
      auth_level = USER_LEVEL_ADMIN;
      var loc = String(window.location + '');
      window.location = append_sid(loc);
      var loc = makeUrlNS('Special', 'Administration', 'module=' + namespace_list['Admin'] + 'PageManager&source=ajax&page_id=' + ajaxEscape(title));
      if ( (ENANO_SID + ' ').length > 1 )
        window.location = loc;
    }, 9);
    return false;
  }
  var loc = makeUrlNS('Special', 'Administration', 'module=' + namespace_list['Admin'] + 'PageManager&source=ajax&page_id=' + ajaxEscape(title));
  window.location = loc;
}

var navto_ns;
var navto_pg;
var navto_ul;

window.ajaxLoginNavTo = function(namespace, page_id, min_level)
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  navto_pg = page_id;
  navto_ns = namespace;
  navto_ul = min_level;
  if ( auth_level < min_level )
  {
    ajaxPromptAdminAuth(function(k) {
      ENANO_SID = k;
      auth_level = navto_ul;
      var loc = makeUrlNS(navto_ns, navto_pg);
      if ( (ENANO_SID + ' ').length > 1 )
        window.location = loc;
    }, min_level);
    return false;
  }
  var loc = makeUrlNS(navto_ns, navto_pg);
  window.location = loc;
}

window.ajaxAdminUser = function(username)
{
  // IE <6 pseudo-compatibility
  if ( KILL_SWITCH )
    return true;
  if ( auth_level < USER_LEVEL_ADMIN )
  {
    ajaxPromptAdminAuth(function(k) {
      ENANO_SID = k;
      auth_level = USER_LEVEL_ADMIN;
      var loc = String(window.location + '');
      window.location = append_sid(loc);
      var loc = makeUrlNS('Special', 'Administration', 'module=' + namespace_list['Admin'] + 'UserManager&src=get&user=' + ajaxEscape(username));
      if ( (ENANO_SID + ' ').length > 1 )
        window.location = loc;
    }, 9);
    return false;
  }
  var loc = makeUrlNS('Special', 'Administration', 'module=' + namespace_list['Admin'] + 'UserManager&src=get&user=' + ajaxEscape(username));
  window.location = loc;
}

window.ajaxDynamicReauth = function(adminpage)
{
  var old_sid = ENANO_SID;
  var targetpage = adminpage;
  ajaxLogonInit(function(k)
    {
      var body = document.getElementsByTagName('body')[0];
      var replace = new RegExp(old_sid, 'g');
      body.innerHTML = body.innerHTML.replace(replace, k);
      ENANO_SID = k;
      if ( targetpage )
      {
        mb_current_obj.destroy();
        ajaxPage(targetpage);
      }
    }, USER_LEVEL_ADMIN);
  ajaxLoginShowFriendlyError({
      error_code: 'admin_session_timed_out',
      respawn_info: {}
  });
}
