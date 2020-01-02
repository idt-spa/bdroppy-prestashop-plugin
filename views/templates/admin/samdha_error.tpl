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
{foreach from=$messages_html item=message_html}
    <div class="warning clear" style="margin-bottom: 10px; width: auto">
        <img src="{$ps_admin_img|escape:'htmlall':'UTF-8'}error.png"> {$message_html}
    </div>
{/foreach}
