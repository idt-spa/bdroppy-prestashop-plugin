{*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade this module to newer
* versions in the future. If you wish to customize this module for your
* needs please refer to http://doc.prestashop.com/display/PS15/Overriding+default+behaviors
* #Overridingdefaultbehaviors-Overridingamodule%27sbehavior for more information.
*
* @author Samdha <contact@samdha.net>
* @copyright  Samdha
* @license    commercial license see license.txt
*}
<script type="text/javascript">
	var messages = {ldelim}
		invalid_url : '{capture name=temp}{l s='Invalid URL' mod='export_catalog' js=1}{/capture}{$smarty.capture.temp|replace:'\\\'':'\''|escape:'javascript':'UTF-8'}',
		selectable_header : '{capture name=temp}{l s='Selectable item' mod='export_catalog' js=1}{/capture}{$smarty.capture.temp|replace:'\\\'':'\''|escape:'javascript':'UTF-8'}',
		selection_header : '{capture name=temp}{l s='Selection items' mod='export_catalog' js=1}{/capture}{$smarty.capture.temp|replace:'\\\'':'\''|escape:'javascript':'UTF-8'}'
	{rdelim};
</script>
<link rel="stylesheet" type="text/css" href="{$module_path|escape:'htmlall':'UTF-8'}views/css/admin.css?v={$module_version|escape:'htmlall':'UTF-8'}">
<link rel="stylesheet" type="text/css" href="{$module_path|escape:'htmlall':'UTF-8'}views/css/multi-select.css?v={$module_version|escape:'htmlall':'UTF-8'}">
<script src="{$module_path|escape:'htmlall':'UTF-8'}views/js/jquery.multi-select.js?v={$module_version|escape:'htmlall':'UTF-8'}" type="text/javascript"></script>
