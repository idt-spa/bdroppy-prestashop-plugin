/**
 * Common behaviour for modules configuration
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please refer to http://doc.prestashop.com/display/PS15/Overriding+default+behaviors
 * #Overridingdefaultbehaviors-Overridingamodule%27sbehavior for more information.
 *
 * @category Prestashop
 * @category Module
 * @author Samdha <contact@samdha.net>
 * @copyright Samdha
 * @license commercial license see license.txt
**/

/*! https://mths.be/startswith v0.2.0 by @mathias */
if (!String.prototype.startsWith) {
  (function() {
    'use strict'; // needed to support `apply`/`call` with `undefined`/`null`
    var defineProperty = (function() {
      // IE 8 only supports `Object.defineProperty` on DOM elements
      try {
        var object = {};
        var $defineProperty = Object.defineProperty;
        var result = $defineProperty(object, object, object) && $defineProperty;
      } catch(error) {}
      return result;
    }());
    var toString = {}.toString;
    var startsWith = function(search) {
      if (this == null) {
        throw TypeError();
      }
      var string = String(this);
      if (search && toString.call(search) == '[object RegExp]') {
        throw TypeError();
      }
      var stringLength = string.length;
      var searchString = String(search);
      var searchLength = searchString.length;
      var position = arguments.length > 1 ? arguments[1] : undefined;
      // `ToInteger`
      var pos = position ? Number(position) : 0;
      if (pos != pos) { // better `isNaN`
        pos = 0;
      }
      var start = Math.min(Math.max(pos, 0), stringLength);
      // Avoid the `indexOf` call if no match is possible
      if (searchLength + start > stringLength) {
        return false;
      }
      var index = -1;
      while (++index < searchLength) {
        if (string.charCodeAt(start + index) != searchString.charCodeAt(index)) {
          return false;
        }
      }
      return true;
    };
    if (defineProperty) {
      defineProperty(String.prototype, 'startsWith', {
        'value': startsWith,
        'configurable': true,
        'writable': true
      });
    } else {
      String.prototype.startsWith = startsWith;
    }
  }());
}

/* global $, jQuery, samdhaAdminPreInit, samdhaAdminPostInit, messages */
var samdha_jquery_save = $

/**
 * Init module interface
 *
 * @param  object $        jQuery
 * @param  object config   module parameters
 * @param  object messages messages to display
 * @return object
 */
var samdha_module = (function ($, config, messages) { // eslint-disable-line no-unused-vars
  'use strict'

  var samdha_module = {}
  samdha_module.$ = $
  samdha_module.config = config
  samdha_module.messages = messages
  samdha_module.preInit = function () {}
  samdha_module.postInit = function () {}

  /**
   * Display an error message
   *
   * @param string message
   * @returns void
   */
  samdha_module.displayError = function (message) {
    $('#samdha_warper').before('<div class="module_error alert error alert-danger"><span class="alert_close"></span>' + message + '</div>')
  }

  function getDocHeight (doc) {
    doc = doc || document
    // stackoverflow.com/questions/1145850/
    var body = doc.body
    var html = doc.documentElement
    var height = Math.max(body.scrollHeight, body.offsetHeight, html.clientHeight, html.scrollHeight, html.offsetHeight)
    return height
  }

  samdha_module.setIframeHeight = function (id) {
    var ifrm = document.getElementById(id)
    var doc = ifrm.contentDocument ? ifrm.contentDocument : ifrm.contentWindow.document
    ifrm.style.visibility = 'hidden'
    ifrm.style.height = '10px' // reset to minimal height ...
    // IE opt. for bing/msn needs a bit added or scrollbar appears
    ifrm.style.height = getDocHeight(doc) + 4 + 'px'
    ifrm.style.minHeight = '0px'
    ifrm.style.visibility = 'visible'
  }

  $(document).ready(function () {
    var $ = samdha_module.$

    $(document).trigger('ajaxStart')

    samdha_module.preInit()
    // backward compatibility
    if (typeof samdhaAdminPreInit === 'function') {
      samdhaAdminPreInit($, config, messages, samdha_module)
    }

    // spinner
    var input = document.createElement('input')
    input.setAttribute('type', 'number')

    if (input.type === 'text') {
      $('#samdha_content input[type=number]').spinner()
    }

    // select
    if ($('#samdha_content select:not(.nochosen)').length > 0) {
      // $('#samdha_warper').css('visibility', 'hidden').css('display', 'block')
      $('#samdha_content select:not(.nochosen)')
        .chosen('destroy')
        .chosen({disable_search_threshold: 5})
        .on('chosen:showing_dropdown', function (event, args) {
          var $container = $(args.chosen.container)

          var $replacement = $('<div style="display: inline-block;vertical-align: middle;">')
              .width($container.width())
              .height($('.chosen-single', $container).outerHeight())
              .uniqueId()
          $replacement.insertAfter($container)
          $(this).data('chosen_replacement_id', $replacement.attr('id'))

          $container.uniqueId().appendTo('body').css('position', 'absolute').offset($replacement.offset())
          $('.chosen-drop', $container).css('display', 'block')
          $(this).data('chosen_id', $container.attr('id'))
        })
        .on('chosen:hiding_dropdown', function (event, args) {
          var $replacement = $('#' + $(this).data('chosen_replacement_id'))
          $replacement.remove()
          var $container = $('#' + $(this).data('chosen_id'))
          $('.chosen-drop', $container).css('display', 'none')
          $container.css('position', 'relative').css('top', '').css('left', '').insertAfter(this)
        })
      $('#samdha_content .chosen-container').each(function () {
        $(this).width($(this).width() + 10)
      })
      // $('#samdha_warper').css('display', 'none').css('visibility', 'visible')
    }

    // table
    $('#samdha_content table').each(function () {
      $(this).addClass('ui-widget')
      $('th', this).addClass('ui-widget-header')
      $('tbody', this)
        .addClass('ui-widget-content')
        .on('mouseenter mouseleave', 'tr', function () {
          $('td', this).toggleClass('ui-state-hover')
        })
      // $('tbody>tr', this).filter(":odd").find('td').css('background-color', '#EFEFEF')
    })

    // radio
    if (typeof $.ui !== 'undefined' && typeof $.ui.buttonset !== 'undefined') {
      $('#samdha_content .radio').buttonset()
    }

    // Tooltips
    if (typeof $.ui !== 'undefined' && typeof $.ui.tooltip !== 'undefined') {
      $('#samdha_content *[title]').tooltip({
        position: {
          my: 'center bottom-20',
          at: 'center top',
          using: function (position, feedback) {
            $(this).css(position)
            $('<div>')
              .addClass('arrow')
              .addClass(feedback.vertical)
              .addClass(feedback.horizontal)
              .appendTo(this)
          }
        }
      })
    }

    // Buttons
    if (typeof $.ui !== 'undefined' && typeof $.ui.button !== 'undefined') {
      $('#samdha_warper input[type=submit], #samdha_warper input[type=button], #samdha_warper input[type=reset], #samdha_warper .samdha_button, #samdha_warper button').button()
    }

    // errors/warn
    $('#content div.warn, #content div.error, #content div.solid_hint, #content div.conf, .bootstrap div.alert').each(function () {
      $('#hideWarn, button.close', this).remove()
      $(this).prepend('<span class="alert_close"></span>')
    })

    // Accordions
    if (typeof $.ui !== 'undefined' && typeof $.ui.accordion !== 'undefined') {
      $('#samdha_content .accordion').accordion({heightStyle: 'content'})
    }

    // Tabs
    if (typeof $.ui !== 'undefined' && typeof $.ui.tabs !== 'undefined') {
      var active_tab = config.active_tab
      /* test for not a number http://stackoverflow.com/a/1830844 */
      if (!(!isNaN(parseFloat(active_tab)) && isFinite(active_tab))) {
        if ($('#' + active_tab).length) {
          active_tab = $('#' + active_tab).index() - 1
        } else {
          active_tab = 0
        }
      }
      $('#samdha_tab').tabs({
        active: active_tab,
        beforeLoad: function (event, ui) {
          /**
           * create an iframe instead of loading the content
           */
          if ($('a', ui.tab).attr('rel') === 'iframe') {
            if (!$(ui.panel).html()) {
              ui.panel.addClass('col-lg-10 col-md-9')
              var iframe = ''
              if (config.version_16) {
                iframe = iframe + "<div class='panel'><h3 class='tab'>" + ui.tab.text() + '</h3>'
              }
              iframe = iframe + "<iframe style='height: 800px; border: none; width: 100%"
              iframe = iframe + "' src='" + $('a', ui.tab).attr('href')
              iframe = iframe + "' onload='samdha_module.setIframeHeight(this.id)'></iframe>"
              if (config.version_16) {
                iframe = iframe + '</div>'
              }
              $(ui.panel)
                  .css({
                    paddingLeft: 0,
                    paddingRight: 0
                  })
                  .html(iframe)
              $('iframe', ui.panel).uniqueId()
            }
            return false
          }
        }
      })
    }
    if (config.version_16) {
      $('#samdha_tab').addClass('ui-tabs-vertical ui-helper-clearfix')
      $('#samdha_tab>ul').addClass('productTabs col-lg-2 col-md-3')
      $('#samdha_tab>ul li').removeClass('ui-corner-top').addClass('ui-corner-left')
    }

    // display error message when ajax request fails
    $(document).ajaxError(function (event, jqXHR, settings, exception) {
      if (settings.url && settings.url.startsWith('ajax.php?rand=')) {
        return
      }
      if ((jqXHR.status >= 300) && (jqXHR.status != 304)) {
        var message = 'Request failed\n'
        message += '\nStatus: ' + jqXHR.status + ' ' + jqXHR.statusText
        if (settings.url) {
          message += '\nURL: ' + settings.url
        }
        if (jqXHR.responseText) {
          var responseText = jqXHR.responseText
          if (responseText.indexOf('<body>') !== -1) {
            responseText = $(responseText.substring(responseText.indexOf('<body>'), responseText.indexOf('</body>') + 7)).text()
          } else if (jqXHR.responseText.indexOf('<BODY>') !== -1) {
            responseText = $(responseText.substring(responseText.indexOf('<BODY>'), responseText.indexOf('</BODY>') + 7)).text()
          }
          message += '\nResponse: ' + $.trim(responseText)
        }
        samdha_module.displayError(message.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, '$1<br/>$2'))
      }
    })

    // Help
    $('.module_help').on('click', function (event) {
      event.preventDefault()
      // open help links in help tab
      var $help_panel = $('div[aria-labelledby="tabHelp"]')
      if ($help_panel.html()) {
        // iframe already created
        $('iframe', $help_panel).attr('src', $(this).attr('href'))
      } else {
        $('#tabHelp').attr('href', $(this).attr('href'))
      }

      if (typeof $.ui !== 'undefined' && typeof $.ui.tabs !== 'undefined') {
        $('#samdha_tab').tabs('option', 'active', $('#tabHelp').parent().index())
      }
      $('html, body').animate({ scrollTop: $('#samdha_tab').position().top }, 500)
    })

    // Support
    $('.module_support, #desc-' + module.short_name + '-register').on('click', function (event) {
      event.preventDefault()
      if (typeof $.ui !== 'undefined' && typeof $.ui.tabs !== 'undefined') {
        $('#samdha_tab').tabs('option', 'active', $('#tabSupport').parent().index())
      }
      $('html, body').animate({ scrollTop: $('#samdha_tab').position().top }, 500)
    })

    $('#content').on('click', '.alert_close', function () {
      $(this).parent().hide({
        'effect': 'slide',
        'direction': 'up',
        'complete': function () {
          $(this).remove()
        }
      })
    })

    // forms
    $('#samdha_tab .ui-tabs-panel').on('submit', 'form', function () {
      if ($('input[name="active_tab"]', this).length === 0) {
        $(this).append(
          $('<input/>').prop(
            {
              type: 'hidden',
              name: 'active_tab',
              value: $(this).parent('.ui-tabs-panel').prop('id')
            }
          )
        )
      }
    })

    samdha_module.postInit()
    // backward compatibility
    if (typeof samdhaAdminPostInit === 'function') {
      samdhaAdminPostInit($, config, messages, samdha_module)
    }
    $('#samdha_wait').hide()
    $('#samdha_warper').css('visibility', 'visible')
    $(document).trigger('ajaxStop')
  })

  return samdha_module
})($.noConflict(true), module, messages)

if (typeof jQuery === 'undefined') {
  jQuery = samdha_jquery_save // eslint-disable-line no-native-reassign
  $ = samdha_jquery_save // eslint-disable-line no-native-reassign
}
