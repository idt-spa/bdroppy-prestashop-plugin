/**
* 2007-2020 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*
* Don't forget to prefix your containers with your own identifier
* to avoid any conflicts with others containers.
*/


let siteIds = {
    gender : '#category_select_Gender',
    category : '#category_select_Category',
    subcategory : '#category_select_SubCategory',
};
let bdroppyIds = {
    gender : '#category_select_bdGender',
    category : '#category_select_bdCategory',
    subcategory : '#category_select_bdSubCategory',
};
let categoryType = "#category_type";
let remove_item = "#remove_item";


function getCategoryMappingList()
{
    let ListId = "#categoryMappingList";
    $.ajax({
        type: 'POST',
        url: category_url,
        data : {type :'getCategoryList',data: null},
        cache: false,
        success: function(data) {

            console.log('success');
            console.log(data);
            $(ListId).html('');
            $.each(data,function (key,item) {
                $(ListId).append("<tr>" +
                    "<td>"+$.map(item.bdroppyNames, e => e).join(' > ')+"</td>" +
                    "<td>"+$.map(item.siteNames, e => e).join(' > ')+"</td>" +
                    "<td><a class='deleteItemByKey' data-target='"+ key +"' >Delete</a></td>" +
                    "</tr>");
            });
        },
    });
}


function bdCategory(e)
{
    $.ajax({
        type: 'POST',
        url: category_url,
        data : {type :'getBdCategory',data: null},
        cache: false,
        success: function(data) {

            console.log('getBdCategory success');
            console.log(data);
            $('#category_select_bdCategory').html("");
            $('#category_select_bdCategory').append("<option selected disabled>- - - Select - - -</option>");
            $.each( data, function( key, value ) {
                $('#category_select_bdCategory').append(new Option(value, key));
            });
        },
    });

}

function bdSubCategory(e)
{
    $.ajax({
        type: 'POST',
        url: category_url,
        data : {type :'getBdSubCategory',data: {
                category : e.target.value
            }},
        cache: false,
        success: function(data) {

            console.log('getBdSubCategory success');
            console.log(data);
            $('#category_select_bdSubCategory').html("");
            $('#category_select_bdSubCategory').append("<option selected disabled>- - - Select - - -</option>");
            $.each( data, function( key, value ) {
                $('#category_select_bdSubCategory').append(new Option(value, key));
            });
        },
    });

}




$( document ).ready(function() {

    $('#category_type').change(function () {
        if ($(this).val() == 1){
            $('#category_gender_row').show();
            $(siteIds.gender).html($(siteIds.category).html());
            $(siteIds.category).html("<option selected disabled>- - - Select - - -</option>");
        }else{
            $('#category_gender_row').hide();
            $(siteIds.category).html($(siteIds.gender).html());
            $(siteIds.gender).html("<option selected disabled>- - - Select - - -</option>");
        }
        $("#category_select_SubCategory").html("<option selected disabled>- - - Select - - -</option>");
    });
    bdCategory();
    $(bdroppyIds.category).on('change',bdSubCategory);

    getCategoryMappingList();
});
