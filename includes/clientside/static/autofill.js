/**
 * Javascript auto-completion for form fields. jQuery based in 1.1.5.
 * Different types of auto-completion fields can be defined (e.g. with different data sets). For each one, a schema
 * can be created describing how to draw each row.
 */

var autofill_schemas = window.autofill_schemas || {};

/**
 * SCHEMA - GENERIC
 */

autofill_schemas.generic = {
  init: function(element, fillclass, params)
  {
    $(element).autocomplete(makeUrlNS('Special', 'Autofill', 'type=' + fillclass) + '&userinput=', {
        minChars: 3
    });
  }
}

/**
 * SCHEMA - USERNAME
 */

autofill_schemas.username = {
  init: function(element, fillclass, params)
  {
    params = params || {};
    var allow_anon = params.allow_anon ? '1' : '0';
    $(element).autocomplete(makeUrlNS('Special', 'Autofill', 'type=' + fillclass + '&allow_anon=' + allow_anon) + '&userinput=', {
        minChars: 3,
        formatItem: function(row, _, __)
        {
          var html = row.name_highlight + ' &ndash; ';
          html += '<span style="' + row.rank_style + '">' + row.rank_title + '</span>';
          return html;
        },
        tableHeader: '<tr><th>' + $lang.get('user_autofill_heading_suggestions') + '</th></tr>',
        showWhenNoResults: true,
        noResultsHTML: '<tr><td class="row1" style="font-size: smaller;">' + $lang.get('user_autofill_msg_no_suggestions') + '</td></tr>',
    });
  }
}

autofill_schemas.page = {
  init: function(element, fillclass, params)
  {
    $(element).autocomplete(makeUrlNS('Special', 'Autofill', 'type=' + fillclass) + '&userinput=', {
        minChars: 3,
        formatItem: function(row, _, __)
        {
          var html = '<u>' + row.name_highlight + '</u>';
          html += ' &ndash; ' + row.pid_highlight;
          return html;
        },
        showWhenNoResults: true,
        noResultsHTML: '<tr><td class="row1" style="font-size: smaller;">' + $lang.get('user_autofill_msg_no_suggestions') + '</td></tr>',
    });
  }
}

window.autofill_onload = function()
{
  if ( this.loaded )
  {
    return true;
  }
  
  var inputs = document.getElementsByClassName('input', 'autofill');
  
  if ( inputs.length > 0 )
  {
    // we have at least one input that needs to be made an autofill element.
    // is spry data loaded?
    load_component('l10n');
  }
  
  this.loaded = true;
  
  for ( var i = 0; i < inputs.length; i++ )
  {
    autofill_init_element(inputs[i]);
  }
}

window.autofill_init_element = function(element, params)
{
  if ( element.af_initted )
    return false;
  
  params = params || {};
  // assign an ID if it doesn't have one yet
  if ( !element.id )
  {
    element.id = 'autofill_' + Math.floor(Math.random() * 100000);
  }
  var id = element.id;
  
  // get the fill type
  var fillclass = element.className;
  fillclass = fillclass.split(' ');
  fillclass = fillclass[1];
  
  var schema = ( autofill_schemas[fillclass] ) ? autofill_schemas[fillclass] : autofill_schemas['generic'];
  if ( typeof(schema.init) != 'function' )
  {
    schema.init = autofill_schemas.generic.init;
  }
  schema.init(element, fillclass, params);
  
  element.af_initted = true;
}

window.AutofillUsername = function(el, allow_anon)
{
  el.onkeyup = null;
  el.className = 'autofill username';
  autofill_init_element(el, { allow_anon: allow_anon });
}

window.AutofillPage = function(el)
{
  el.onkeyup = null;
  el.className = 'autofill page';
  autofill_init_element(el, {});
}

addOnloadHook(function()
  {
    load_component('l10n');
    load_component('jquery');
    load_component('jquery-ui');
    
    if ( !window.jQuery )
    {
      throw('jQuery didn\'t load properly. Aborting auto-complete init.');
    }
    
    jQuery.autocomplete = function(input, options) {
      // Create a link to self
      var me = this;
    
      // Create jQuery object for input element
      var $input = $(input).attr("autocomplete", "off");
    
      // Apply inputClass if necessary
      if (options.inputClass) {
        $input.addClass(options.inputClass);
      }
    
      // Create results
      var results = document.createElement("div");
      $(results).addClass('tblholder').css('z-index', getHighestZ() + 1).css('margin-top', 0);
      $(results).css('clip', 'rect(0px,auto,auto,0px)').css('overflow', 'auto').css('max-height', '300px');
    
      // Create jQuery object for results
      // var $results = $(results);
      var $results = $(results).hide().addClass(options.resultsClass).css("position", "absolute");
      if( options.width > 0 ) {
        $results.css("width", options.width);
      }
    
      // Add to body element
      $("body").append(results);
    
      input.autocompleter = me;
    
      var timeout = null;
      var prev = "";
      var active = -1;
      var cache = {};
      var keyb = false;
      // hasFocus was false by default, see if making it true helps
      var hasFocus = true;
      var hasNoResults = false;
      var lastKeyPressCode = null;
      var mouseDownOnSelect = false;
      var hidingResults = false;
    
      // flush cache
      function flushCache(){
        cache = {};
        cache.data = {};
        cache.length = 0;
      };
    
      // flush cache
      flushCache();
    
      // if there is a data array supplied
      if( options.data != null ){
        var sFirstChar = "", stMatchSets = {}, row = [];
    
        // no url was specified, we need to adjust the cache length to make sure it fits the local data store
        if( typeof options.url != "string" ) {
          options.cacheLength = 1;
        }
    
        // loop through the array and create a lookup structure
        for( var i=0; i < options.data.length; i++ ){
          // if row is a string, make an array otherwise just reference the array
          row = ((typeof options.data[i] == "string") ? [options.data[i]] : options.data[i]);
    
          // if the length is zero, don't add to list
          if( row[0].length > 0 ){
            // get the first character
            sFirstChar = row[0].substring(0, 1).toLowerCase();
            // if no lookup array for this character exists, look it up now
            if( !stMatchSets[sFirstChar] ) stMatchSets[sFirstChar] = [];
            // if the match is a string
            stMatchSets[sFirstChar].push(row);
          }
        }
    
        // add the data items to the cache
        if ( options.cacheLength )
        {
          for( var k in stMatchSets ) {
            // increase the cache size
            options.cacheLength++;
            // add to the cache
            addToCache(k, stMatchSets[k]);
          }
        }
      }
    
      $input
      .keydown(function(e) {
        // track last key pressed
        lastKeyPressCode = e.keyCode;
        switch(e.keyCode) {
          case 38: // up
            e.preventDefault();
            moveSelect(-1);
            break;
          case 40: // down
            e.preventDefault();
            moveSelect(1);
            break;
          case 9:  // tab
          case 13: // return
            if( selectCurrent() ){
              // make sure to blur off the current field
              // (Enano edit - why do we want this, again?)
              // $input.get(0).blur();
              e.preventDefault();
            }
            break;
          default:
            active = -1;
            if (timeout) clearTimeout(timeout);
            timeout = setTimeout(function(){onChange();}, options.delay);
            break;
        }
      })
      .focus(function(){
        // track whether the field has focus, we shouldn't process any results if the field no longer has focus
        hasFocus = true;
      })
      .blur(function() {
        // track whether the field has focus
        hasFocus = false;
        if (!mouseDownOnSelect) {
          hideResults();
        }
      });
    
      hideResultsNow();
    
      function onChange() {
        // ignore if the following keys are pressed: [del] [shift] [capslock]
        if( lastKeyPressCode == 46 || (lastKeyPressCode > 8 && lastKeyPressCode < 32) ) return $results.hide();
        var v = $input.val();
        if (v == prev) return;
        prev = v;
        if (v.length >= options.minChars) {
          $input.addClass(options.loadingClass);
          requestData(v);
        } else {
          $input.removeClass(options.loadingClass);
          $results.hide();
        }
      };
    
      function moveSelect(step) {
    
        var lis = $("td", results);
        if (!lis || hasNoResults) return;
    
        active += step;
    
        if (active < 0) {
          active = 0;
        } else if (active >= lis.size()) {
          active = lis.size() - 1;
        }
    
        lis.removeClass("row2");
    
        $(lis[active]).addClass("row2");
        
        // scroll the results div
        // are we going up or down?
        var td_top = $dynano(lis[active]).Top() - $dynano(results).Top();
        var td_height = $dynano(lis[active]).Height();
        var td_bottom = td_top + td_height;
        var visibleTopBoundary = getScrollOffset(results);
        var results_height = $dynano(results).Height();
        var visibleBottomBoundary = visibleTopBoundary + results_height;
        var scrollTo = false;
        console.debug('td top = %d, td height = %d, td bottom = %d, visibleTopBoundary = %d, results_height = %d, visibleBottomBoundary = %d, step = %d',
                       td_top, td_height, td_bottom, visibleTopBoundary, results_height, visibleBottomBoundary, step);
        if ( td_top < visibleTopBoundary && step < 0 )
        {
          // going up: scroll the results div to just higher than the result we're trying to see
          scrollTo = td_top - 7;
        }
        else if ( td_bottom > visibleBottomBoundary && step > 0 )
        {
          // going down is a little harder, we want the result to be at the bottom
          scrollTo = td_top - results_height + td_height + 7;
        }
        if ( scrollTo )
        {
          console.debug('scrolling the results div to %d', scrollTo);
          results.scrollTop = scrollTo;
        }
    
        // Weird behaviour in IE
        // if (lis[active] && lis[active].scrollIntoView) {
        // 	lis[active].scrollIntoView(false);
        // }
    
      };
    
      function selectCurrent() {
        var li = $("td.row2", results)[0];
        if (!li) {
          var $li = $("td", results);
          if (options.selectOnly) {
            if ($li.length == 1) li = $li[0];
          } else if (options.selectFirst) {
            li = $li[0];
          }
        }
        if (li) {
          selectItem(li);
          return true;
        } else {
          return false;
        }
      };
    
      function selectItem(li) {
        if (!li) {
          li = document.createElement("li");
          li.extra = [];
          li.selectValue = "";
        }
        var v = $.trim(li.selectValue ? li.selectValue : li.innerHTML);
        input.lastSelected = v;
        prev = v;
        $results.html("");
        $input.val(v);
        hideResultsNow();
        if (options.onItemSelect) {
          setTimeout(function() { options.onItemSelect(li) }, 1);
        }
      };
    
      // selects a portion of the input string
      function createSelection(start, end){
        // get a reference to the input element
        var field = $input.get(0);
        if( field.createTextRange ){
          var selRange = field.createTextRange();
          selRange.collapse(true);
          selRange.moveStart("character", start);
          selRange.moveEnd("character", end);
          selRange.select();
        } else if( field.setSelectionRange ){
          field.setSelectionRange(start, end);
        } else {
          if( field.selectionStart ){
            field.selectionStart = start;
            field.selectionEnd = end;
          }
        }
        field.focus();
      };
    
      // fills in the input box w/the first match (assumed to be the best match)
      function autoFill(sValue){
        // if the last user key pressed was backspace, don't autofill
        if( lastKeyPressCode != 8 ){
          // fill in the value (keep the case the user has typed)
          $input.val($input.val() + sValue.substring(prev.length));
          // select the portion of the value not typed by the user (so the next character will erase)
          createSelection(prev.length, sValue.length);
        }
      };
    
      function showResults() {
        // get the position of the input field right now (in case the DOM is shifted)
        var pos = findPos(input);
        // either use the specified width, or autocalculate based on form element
        var iWidth = (options.width > 0) ? options.width : $input.width();
        // reposition
        $results.css({
          width: parseInt(iWidth) + "px",
          top: (pos.y + input.offsetHeight) + "px",
          left: pos.x + "px"
        });
        if ( !$results.is(":visible") )
        {
          $results.show("blind", {}, 200);
        }
        else
        {
          $results.show();
        }
      };
    
      function hideResults() {
        if (timeout) clearTimeout(timeout);
        timeout = setTimeout(hideResultsNow, 200);
      };
    
      function hideResultsNow() {
        if (hidingResults) {
          return;
        }
        hidingResults = true;
      
        if (timeout) {
          clearTimeout(timeout);
        }
        
        var v = $input.removeClass(options.loadingClass).val();
        
        if ($results.is(":visible")) {
          $results.hide();
        }
        
        if (options.mustMatch) {
          if (!input.lastSelected || input.lastSelected != v) {
            selectItem(null);
          }
        }
    
        hidingResults = false;
      };
    
      function receiveData(q, data) {
        if (data) {
          $input.removeClass(options.loadingClass);
          results.innerHTML = "";
    
          // if the field no longer has focus or if there are no matches, do not display the drop down
          if( !hasFocus )
          {
            return hideResultsNow();
          }
          if ( data.length == 0 && !options.showWhenNoResults )
          {
            return hideResultsNow();
          }
          hasNoResults = false;
    
          if ($.browser.msie) {
            // we put a styled iframe behind the calendar so HTML SELECT elements don't show through
            $results.append(document.createElement('iframe'));
          }
          results.appendChild(dataToDom(data));
          // autofill in the complete box w/the first match as long as the user hasn't entered in more data
          if( options.autoFill && ($input.val().toLowerCase() == q.toLowerCase()) ) autoFill(data[0][0]);
          showResults();
        } else {
          hideResultsNow();
        }
      };
    
      function parseData(data) {
        if (!data) return null;
        var parsed = parseJSON(data);
        return parsed;
      };
    
      function dataToDom(data) {
        var ul = document.createElement("table");
        $(ul).attr("border", "0").attr("cellspacing", "1").attr("cellpadding", "3");
        var num = data.length;
        
        if ( options.tableHeader )
        {
          ul.innerHTML = options.tableHeader;
        }
        
        if ( num == 0 )
        {
          // not showing any results
          if ( options.noResultsHTML )
            ul.innerHTML += options.noResultsHTML;
          
          hasNoResults = true;
          return ul;
        }
        
        // limited results to a max number
        if( (options.maxItemsToShow > 0) && (options.maxItemsToShow < num) ) num = options.maxItemsToShow;
        
        for (var i=0; i < num; i++) {
          var row = data[i];
          if (!row) continue;
          
          if ( typeof(row[0]) != 'string' )
          {
            // last ditch resort if it's a 1.1.4 autocomplete plugin that doesn't provide an automatic result.
            // hopefully this doesn't slow it down a lot.
            for ( var i in row )
            {
              if ( i == "0" || i == 0 )
                break;
              row[0] = row[i];
              break;
            }
          }
          
          var li = document.createElement("tr");
          var td = document.createElement("td");
          td.selectValue = row[0];
          $(td).addClass('row1');
          $(td).css("font-size", "smaller");
          
          if ( options.formatItem )
          {
            td.innerHTML = options.formatItem(row, i, num);
          }
          else
          {
            td.innerHTML = row[0];
          }
          li.appendChild(td);
          ul.appendChild(li);
          
          $(td).hover(
            function() { $("tr", ul).removeClass("row2"); $(this).addClass("row2"); active = $("tr", ul).indexOf($(this).get(0)); },
            function() { $(this).removeClass("row2"); }
          ).click(function(e) { 
            e.preventDefault();
            e.stopPropagation();
            selectItem(this)
          });
        }
        
        $(ul).mousedown(function() {
          mouseDownOnSelect = true;
        }).mouseup(function() {
          mouseDownOnSelect = false;
        });
        return ul;
      };
    
      function requestData(q) {
        if (!options.matchCase) q = q.toLowerCase();
        var data = options.cacheLength ? loadFromCache(q) : null;
        // recieve the cached data
        if (data) {
          receiveData(q, data);
        // if an AJAX url has been supplied, try loading the data now
        } else if( (typeof options.url == "string") && (options.url.length > 0) ){
          $.get(makeUrl(q), function(data) {
            data = parseData(data);
            addToCache(q, data);
            receiveData(q, data);
          });
        // if there's been no data found, remove the loading class
        } else {
          $input.removeClass(options.loadingClass);
        }
      };
    
      function makeUrl(q) {
        var sep = options.url.indexOf('?') == -1 ? '?' : '&'; 
        var url = options.url + encodeURI(q);
        for (var i in options.extraParams) {
          url += "&" + i + "=" + encodeURI(options.extraParams[i]);
        }
        return url;
      };
    
      function loadFromCache(q) {
        if (!q) return null;
        if (cache.data[q]) return cache.data[q];
        if (options.matchSubset) {
          for (var i = q.length - 1; i >= options.minChars; i--) {
            var qs = q.substr(0, i);
            var c = cache.data[qs];
            if (c) {
              var csub = [];
              for (var j = 0; j < c.length; j++) {
                var x = c[j];
                var x0 = x[0];
                if (matchSubset(x0, q)) {
                  csub[csub.length] = x;
                }
              }
              return csub;
            }
          }
        }
        return null;
      };
    
      function matchSubset(s, sub) {
        if (!options.matchCase) s = s.toLowerCase();
        var i = s.indexOf(sub);
        if (i == -1) return false;
        return i == 0 || options.matchContains;
      };
    
      this.flushCache = function() {
        flushCache();
      };
    
      this.setExtraParams = function(p) {
        options.extraParams = p;
      };
    
      this.findValue = function(){
        var q = $input.val();
    
        if (!options.matchCase) q = q.toLowerCase();
        var data = options.cacheLength ? loadFromCache(q) : null;
        if (data) {
          findValueCallback(q, data);
        } else if( (typeof options.url == "string") && (options.url.length > 0) ){
          $.get(makeUrl(q), function(data) {
            data = parseData(data)
            addToCache(q, data);
            findValueCallback(q, data);
          });
        } else {
          // no matches
          findValueCallback(q, null);
        }
      }
    
      function findValueCallback(q, data){
        if (data) $input.removeClass(options.loadingClass);
    
        var num = (data) ? data.length : 0;
        var li = null;
    
        for (var i=0; i < num; i++) {
          var row = data[i];
    
          if( row[0].toLowerCase() == q.toLowerCase() ){
            li = document.createElement("li");
            if (options.formatItem) {
              li.innerHTML = options.formatItem(row, i, num);
              li.selectValue = row[0];
            } else {
              li.innerHTML = row[0];
              li.selectValue = row[0];
            }
            var extra = null;
            if( row.length > 1 ){
              extra = [];
              for (var j=1; j < row.length; j++) {
                extra[extra.length] = row[j];
              }
            }
            li.extra = extra;
          }
        }
    
        if( options.onFindValue ) setTimeout(function() { options.onFindValue(li) }, 1);
      }
    
      function addToCache(q, data) {
        if (!data || !q || !options.cacheLength) return;
        if (!cache.length || cache.length > options.cacheLength) {
          flushCache();
          cache.length++;
        } else if (!cache[q]) {
          cache.length++;
        }
        cache.data[q] = data;
      };
    
      function findPos(obj) {
        var curleft = obj.offsetLeft || 0;
        var curtop = obj.offsetTop || 0;
        while (obj = obj.offsetParent) {
          curleft += obj.offsetLeft
          curtop += obj.offsetTop
        }
        return {x:curleft,y:curtop};
      }
    }
    
    jQuery.fn.autocomplete = function(url, options, data) {
      // Make sure options exists
      options = options || {};
      // Set url as option
      options.url = url;
      // set some bulk local data
      options.data = ((typeof data == "object") && (data.constructor == Array)) ? data : null;
    
      // Set default values for required options
      options = $.extend({
        inputClass: "ac_input",
        resultsClass: "ac_results",
        lineSeparator: "\n",
        cellSeparator: "|",
        minChars: 1,
        delay: 400,
        matchCase: 0,
        matchSubset: 1,
        matchContains: 0,
        cacheLength: false,
        mustMatch: 0,
        extraParams: {},
        loadingClass: "ac_loading",
        selectFirst: false,
        selectOnly: false,
        maxItemsToShow: -1,
        autoFill: false,
        showWhenNoResults: false,
        width: 0
      }, options);
      options.width = parseInt(options.width, 10);
    
      this.each(function() {
        var input = this;
        new jQuery.autocomplete(input, options);
      });
    
      // Don't break the chain
      return this;
    }
    
    jQuery.fn.autocompleteArray = function(data, options) {
      return this.autocomplete(null, options, data);
    }
    
    jQuery.fn.indexOf = function(e){
      for( var i=0; i<this.length; i++ ){
        if( this[i] == e ) return i;
      }
      return -1;
    };
    
    autofill_onload();
  });
