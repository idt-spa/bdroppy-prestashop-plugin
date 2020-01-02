/**
 * Common behaviour for modules configuration
 *
 * @category Prestashop
 * @category Module
 * @author Samdha <contact@samdha.net>
 * @copyright Samdha
 * @license commercial license see license.txt
**/

; samdha_module.postInit = function () {
  'use strict';

  var $ = samdha_module.$;
  var config = samdha_module.config;
  var messages = samdha_module.messages;

  $('#samdha_content input[type=radio]').change(function() {
      if ($('input[type=radio][name="' + $(this).attr('name') + '"]:checked').attr('value') == '1') {
          $('.' + $('input[type=radio][name="' + $(this).attr('name') + '"][value=1]').attr('id')).show();
          $('.' + $('input[type=radio][name="' + $(this).attr('name') + '"][value=0]').attr('id')).hide();
      } else {
          $('.' + $('input[type=radio][name="' + $(this).attr('name') + '"][value=1]').attr('id')).hide();
          $('.' + $('input[type=radio][name="' + $(this).attr('name') + '"][value=0]').attr('id')).show();
      }
  }).change();

  $('select.nochosen[multiple=\'multiple\']').each(function() {
    $(this).multiSelect({
    selectableHeader: "<div class='selectableHeader'></div>",
    selectionHeader: "<div class='selectionHeader'></div>"
  });
    $('.ms-container', $(this).parent())
      .css('position', 'relative')
      .append('<span class="samdha_button multiselect_addall">⇉</span>')
      .append('<span class="samdha_button multiselect_removeall">⇇</span>')
      .find('.samdha_button').button();
    $('.multiselect_addall', $(this).parent())
      .attr('title', messages.select_all)
      .on('click', function() {
        $('select', $(this).parent().parent()).multiSelect('select_all');
      });
    $('.multiselect_removeall', $(this).parent())
      .attr('title', messages.unselect_all)
      .on('click', function() {
        $('select', $(this).parent().parent()).multiSelect('deselect_all');
      });
  });
  $('.selectableHeader').text(messages.selectable_header);
  $('.selectionHeader').text(messages.selection_header);

  $("#cron_mhdmd2").change(function() {
    if ($(this).val() == "other") {
      $("#cron_table").show();
    } else {
      $("#cron_table").hide();
    }
  });

  $("#cron_add").submit(function() {
    // http://www.php.net/manual/fr/function.preg-match.php#93824
    var reg = /^https?\:\/\/([a-z0-9+!*(),;?&=\$_.-]+(\:[a-z0-9+!*(),;?&=\$_.-]+)?@)?([a-z0-9-.]*)(\.([a-z]{2,3}))?(\:[0-9]{2,5})?(\/([a-z0-9+\$_-]\.?)+)*\/?(\?[a-z+&\$_.-][a-z0-9;:@&%=+\/\$_.-]*)?(#[a-z_.-][a-z0-9+\$_.-]*)?$/i;
    if ( !reg.test($("#cron_url").val() ) ) {
      alert (messages.invalid_url);
      return false;
    }

    var mhdmd = "";
    if ($("#cron_mhdmd2").val() != "other")
      mhdmd = $("#cron_mhdmd2").val();
    else {
      // minutes
      if ($("input:radio[name=all_mins]:checked").val() == "1")
        mhdmd = "*";
      else {
        var tmp = "";
        $("select[name=mins]").each(function(){
          if ($(this).val())
            tmp = tmp + "," + $(this).val().join(",");
        });
        if (tmp == "")
          tmp = ",*";
        mhdmd = tmp.slice(1);
      }

      // hours
      mhdmd = mhdmd + " ";
      if ($("input:radio[name=all_hours]:checked").val() == "1")
        mhdmd = mhdmd + "*";
      else {
        var tmp = "";
        $("select[name=hours]").each(function(){
          if ($(this).val())
            tmp = tmp + "," + $(this).val().join(",");
        });
        if (tmp == "")
          tmp = ",*";
        mhdmd = mhdmd + tmp.slice(1);
      }

      // days
      mhdmd = mhdmd + " ";
      if ($("input:radio[name=all_days]:checked").val() == "1")
        mhdmd = mhdmd + "*";
      else {
        var tmp = "";
        $("select[name=days]").each(function(){
          if ($(this).val())
            tmp = tmp + "," + $(this).val().join(",");
        });
        if (tmp == "")
          tmp = ",*";
        mhdmd = mhdmd + tmp.slice(1);
      }

      // months
      mhdmd = mhdmd + " ";
      if ($("input:radio[name=all_months]:checked").val() == "1")
        mhdmd = mhdmd + "*";
      else {
        var tmp = "";
        $("select[name=months]").each(function(){
          if ($(this).val())
            tmp = tmp + "," + $(this).val().join(",");
        });
        if (tmp == "")
          tmp = ",*";
        mhdmd = mhdmd + tmp.slice(1);
      }

      // weekdays
      mhdmd = mhdmd + " ";
      if ($("input:radio[name=all_weekdays]:checked").val() == "1")
        mhdmd = mhdmd + "*";
      else {
        var tmp = "";
        $("select[name=weekdays]").each(function(){
          if ($(this).val())
            tmp = tmp + "," + $(this).val().join(",");
        });
        if (tmp == "")
          tmp = ",*";
        mhdmd = mhdmd + tmp.slice(1);
      }
    }

    $("#cron_mhdmd").val(mhdmd);
  });
};
