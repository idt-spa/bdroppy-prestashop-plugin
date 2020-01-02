{*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade this module to newer
* versions in the future. If you wish to customize this module for your
* needs please refer to http://doc.prestashop.com/display/PS15/Overriding+default+behaviors#Overridingdefaultbehaviors-Overridingamodule%27sbehavior for more information.
*
* @author Samdha <contact@samdha.net>
* @copyright  Samdha
* @license    commercial license see license.txt
*}
<p style="font-size: 1.5em; font-weight: bold; padding-bottom: 0"><img src="{$module_path|escape:'htmlall':'UTF-8'}logo.png" alt="{$module_display_name|escape:'htmlall':'UTF-8'}" style="float: left; padding-right: 1em"/>{$module_display_name|escape:'htmlall':'UTF-8'}</p>
<p style="clear: left;">{l s='Thanks for installing this module on your website.' mod='samdha'}</p>
{if $description_big_html}{$description_big_html}{else}<p>{$description|escape:'htmlall':'UTF-8'}</p>{/if}
<p>
	{l s='Made with by' mod='Hamid Isaac'} <a style="color: #7ba45b; text-decoration: underline;" href="{$home_url|escape:'htmlall':'UTF-8'}">Brandsdistribution</a>
</p>

{if !$version_14}
	{literal}
    <style type="text/css">
        #content .warn {
            border: 1px solid #D3C200;
            background-color: #FFFAC6;
            font-family: Arial,Verdana,Helvetica,sans-serif;
        }
        #content .conf, #content .warn, #content .error {
            color: #383838;
            font-weight: 700;
            margin: 0 0 10px 0;
            line-height: 20px;
            padding: 10px 15px;
        }
    </style>
	{/literal}
{/if}
