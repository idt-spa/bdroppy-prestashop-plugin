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
{if !$version_14}
    {literal}
    <style type="text/css">
        #content .warn {
            border: 1px solid #D3C200;
            background-color: #FFFAC6;
            color: #383838;
            font-weight: 700;
            margin: 0 0 10px 0;
            line-height: 20px;
            padding: 10px 15px;
        }
    </style>
    {/literal}
{/if}
{if !$version_15}
    {foreach from=$messages_html item=message_html}
        <div class="warn clear" style="margin-bottom: 10px;">
            <img src="{$ps_admin_img|escape:'htmlall':'UTF-8'}warn2.png"> {$message_html}
        </div>
    {/foreach}
{else}
    {foreach from=$messages_html item=message_html}
        <div class="bootstrap">
            <div class="alert alert-warning">
                <button type="button" class="close" data-dismiss="alert">×</button>
                {$message_html}
            </div>
        </div>
    {/foreach}
{/if}
