              </div>
              <div id="mdgCommentContainer">
              </div>
            </div></div>
          </td><td id="mdg-mr"></td></tr>
          <tr><td id="mdg-btl"></td><td>
            <!-- We strongly request that you leave the notice below in its place; it helps to attract users to Enano in exchange for providing you
                 with your CMS. Enano is still new; therefore we are looking to attract users, and we feel that this notice will help. If you refuse
                 to include even this tiny little notice, support on the Enano forums may be affected. Thanks guys.
                 
                 -Dan
                 -->
            <div id="credits">
              <b>{COPYRIGHT}</b><br />
              [[EnanoPoweredLinkLong]]&nbsp;&nbsp;|&nbsp;&nbsp;<!-- BEGINNOT stupid_mode --><a href="http://validator.w3.org/check?uri=referer">{lang:page_w3c_valid_xhtml11}</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href="http://jigsaw.w3.org/css-validator/validator?uri=referer">{lang:page_w3c_valid_css}</a>&nbsp;&nbsp;|&nbsp;&nbsp;<!-- END stupid_mode -->[[StatsLong]]
              <!-- Do not remove this line or scheduled tasks will not run. -->
              <img alt=" " src="{SCRIPTPATH}/cron.php" width="1" height="1" />
            </div>
          
          </td><td id="mdg-btr"></td></tr>
          <tr><td id="mdg-btcl"></td><td id="mdg-btm"></td><td id="mdg-btcr"></td></tr>
          </table>
            
        </td>
        
        <!-- BEGIN sidebar_right -->
        <!-- BEGINNOT in_admin -->
        <td class="mdgSidebarHolder" valign="top">
          <div id="right-sidebar">
            
            {SIDEBAR_RIGHT}
              
          </div>
          <div id="right-sidebar-showbutton" style="display: none; position: fixed; top: 3px; right: 3px;">
            <input type="button" onclick="collapseSidebar('right');" value="&lt;&lt;" />
          </div>
        </td>
        <!-- END in_admin -->
        <!-- END sidebar_right -->
      
      </tr>
    </table>
    <div style="display: none;">
    <h2>Your browser does not support CSS.</h2>
     <p>If you can see this text, it means that your browser does not support Cascading Style Sheets (CSS). CSS is a fundemental aspect of XHTML, and as a result it is becoming very widely adopted by websites, including this one. You should consider switching to a more modern web browser, such as Mozilla Firefox or Opera 9.</p>
     <p>Because of this, there are a few minor issues that you may experience while browsing this site, not the least of which is some visual elements below that would normally be hidden in most browsers. Please excuse these minor inconveniences.</p>
    </div>
    <div id="root3" class="jswindow" style="display: none;">
      <div id="tb3" class="titlebar">Wiki formatting help</div>
      <div class="content" id="cn3">
        Loading...
      </div>
    </div>
    <script type="text/javascript">
      // This initializes the Javascript runtime when the DOM is ready - not when the page is
      // done loading, because enano-lib-basic still has to load some 15 other script files
      // check for the init function - this is a KHTML fix
      // This doesn't seem to work properly in IE in 1.1.x - there are some problems with
      // tinyMCE and l10n.
      if ( typeof ( enano_init ) == 'function' && !IE )
      {
        enano_init();
        window.onload = function(e) {  };
      }
    </script>
  </body>
</html>
