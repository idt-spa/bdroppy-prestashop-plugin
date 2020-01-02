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
<div id="tabConfigurations" class="col-lg-10 col-md-9">
    <div class="panel">
        <h3 class="tab"> <i class="icon-gear"></i> {l s='Configurations' mod='cron'}</h3>
        <form action="{$module_url|escape:'htmlall':'UTF-8'}" method="post" enctype="multipart/form-data">
            <div class="form-group clear">
                <label for="{$module_short_name|escape:'htmlall':'UTF-8'}_method">{l s='API URL' mod='cron'}</label>
                <div class="{if $version_16 && $bootstrap}input-group{else}margin-form{/if}">
                    <input type="text" name="config[DROPSHIPPING_URL]" value="{$configurations['DROPSHIPPING_URL']}">
                </div>
            </div>

            <div class="form-group clear">
                <label for="{$module_short_name|escape:'htmlall':'UTF-8'}_method">{l s='API Key' mod='cron'}</label>
                <div class="{if $version_16 && $bootstrap}input-group{else}margin-form{/if}">
                    <input type="text" name="config[DROPSHIPPING_KEY]" value="{$configurations['DROPSHIPPING_KEY']}">
                </div>
            </div>

            <div class="form-group clear">
                <label for="{$module_short_name|escape:'htmlall':'UTF-8'}_method">{l s='API Password' mod='cron'}</label>
                <div class="{if $version_16 && $bootstrap}input-group{else}margin-form{/if}">
                    <input type="text" name="config[DROPSHIPPING_PASSWORD]" value="{$configurations['DROPSHIPPING_PASSWORD']}">
                </div>
            </div>

            <div class="clear panel-footer">
                <input type="hidden" name="active_tab" value="tabConfigurations" />
                <input type="hidden" name="saveConfigurations" value="1">
                <p>
                    <input type="submit" class="button" value="{l s='Save' mod='cron'}" />
                </p>
            </div>
        </form>
    </div>
</div>
<div id="tabCatalogs" class="col-lg-10 col-md-9">
    <div class="panel">
        <h3 class="tab"> <i class="icon-ok-sign"></i> {l s='My Catalogs' mod='cron'}</h3>

        <div class="{if $version_16 && $bootstrap}alert alert-info{else}hint solid_hint{/if}">
            {l s='Please choose the method used to determine when executing jobs.' mod='cron'}
            <ol style="margin-top: 1em">
            	<li style="list-style:decimal">
                    {l s='"Shop traffic" method doesn\'t need configuration but is not sure. It depends of your website frequentation so when it isn\'t visited, jobs are not executed.' mod='cron'}
                </li>
                <li style="list-style:decimal">
                    {l s='"Server crontab" is the best method but only if your server uses Linux and you have access to crontab. In that case add the line below to your crontab file.' mod='cron'}
                    <br/><pre>* * * * * {$php_dir|escape:'htmlall':'UTF-8'} {$cron_command|escape:'htmlall':'UTF-8'}</pre>
                    <p>"{$php_dir|escape:'htmlall':'UTF-8'}" {l s='is the PHP path on your server. It has been automatically detected and may be wrong. If it doesn\'t work, contact your host.' mod='cron'}</p>
                </li>
                <li style="list-style:decimal">
                    {l s='"Webcron service" is a good alternative to crontab but is often not free. Register to a webcron service and configure it to visit the URL below every minutes or the nearest.' mod='cron'}
                    <br/><pre>{$cron_url|escape:'htmlall':'UTF-8'}</pre><br/>
             		<img src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAABYAAAAWCAYAAADEtGw7AAAErklEQVQ4T6WUeUyTZxzHv+/bgx4cpbRccogtggULDFzGlITDRUycZMumuENlcyM7Y+YfMrNljUt2uLCZLDrj/nAJcyNhKnMhuuzQxTjnOMYAC6VQFMaxcbWF0tLjffd7CzgQNEt80qdp0t/zeb7P9/d9HgZ3GadMkG3TIVLGI0osgprhIQMDMc/Axfng9Iox0WrHeNErmCEEdyeGWYHLXjoGxcNRWA0e6SKWLbnRrSyRKDVqsBKW84xOeaanbhkz/DW0UesYh76nTsJ++TL8i1nLwAQN3aiFnhYVWvviD0qStsembq4AFHEAIwY8o4CjE10NR2b02pbPCXZu3IPO2N0YW6x8Cdi0F7Kq7UhiAyiy/qX/wLDzhAqRawB+lqZvThAjoi8p4JuFpfY1v0596SgfwJmWf2B+6HU4F1QvAQ99BY1ajDyLNfTo+h2fpjHqVVTnAlie5vwS+gmOlnES2o+BpW7/tC7B+kaAw49bj2NgwZLb4MJCiC++ihSRCE/Y+o3vppa9LGIkbhJHfRHTFOBCtQD10/TRTgEFui/WQ6f9oY7jA9WnW/FnhQme4MEWpJuehPTQHqxva2SO5GwqKGZS8oEQAssCYBbgQjGBeS9BZ8kSTwgwbof5p7qRtHWO3c23cH3Bjtvg70xQbHkARnO79DPjBkM2EvWAchaMkpotD8wpp8wFlRKUdxHYJQNGx2C52jaVaph81jKMKxn7YJ8/15xmIbdP5yOro0V6LDt3VS5iwwAVD0ZBjZN4aQrm0hBC5SV/faTWQU38exKWJoczNXPymfYhXMmpCDZQMGxuBK14AYbW3/Be3kb9VkRNA5FuMH6yTEFgJRUJbCEc/aQ4XkE2SIBJLczXBvrTslzPNXehcZkVVM66G5AoCsE2mzn247WPpEmhMFN0KZ4BIibOK6AYC3eNlxLYlQnrpTHoUm6e4MCdPH0VncuaJyz74xxU6RHItTSLPzKWbsmBJhRMyE1SNgTop4QTAjY5nUQL3p8CTCnQ2XBhItXo3O8M4GdNCYbnihalImgHRa7qbSSzLDb33NBUpz++SwmlFIyb7AgjD+jksFPT5CGUDBms357l1iR3f0LvR933VrQ/Whl8N4JjxSu9KR1GhsPzvQNrKtY+tpfyRQa7KBksiZHTtaYPP/ILOs43dKVlc2/aXbgWUwbBpNuP0TKwcFHqqqANZ1HS0yapXleaEM2ocinTWwF3ByBygHe3oPuCxafTuw9zPM6+fwo9pjpQh/8bK71uc9HbgCzWL3mpb1C5R1+cSjmmOf4NJSQX1uvddNvcHT7RzIHeaTRl7MDEYuiKVswXsKNnEKNiYeidzquXSkJD4xIiIZN64fdI4AxEQzr55WGxeOaLD2sweKfae4GDqh80gAtRHxwYcCdHx8UlISpGh7HBVnDeqUDSyIvGJjNsRaagBf/roV9yqqbz79hmVEUpUapQhKvUcDoc8Nst7qyCcgry3ceKHi8uP/TWgV937azIl8tliIiIgNfrh83y+1hBcZn2vsDx8fGa8vKdjRkZmas1Gi1stl6fQhG2o7JyX/19gYXFGo1mbWlp6ddarTa8trb28PDwcM29oMJ//wLNGcAmi6ehdgAAAABJRU5ErkJggg=="> {l s='Schedule it in one click with' mod='cron'} <a style="text-decoration:underline" href="https://www.easycron.com/cron-job-scheduler?specifiedBy=1&specifiedValue=1&url={$cron_url|escape:'url':'UTF-8'}" target="_blank">EasyCron</a> {l s='(it\'s free)' mod='cron'}<br/><br/>
                </li>
            </ol>
        </div>

        <form action="{$module_url|escape:'htmlall':'UTF-8'}" method="post" enctype="multipart/form-data">
            <div class="form-group clear">
                <label for="{$module_short_name|escape:'htmlall':'UTF-8'}_method">{l s='Method' mod='cron'}</label>
                <div class="{if $version_16 && $bootstrap}input-group{else}margin-form{/if}">
                    <select name="catalogs[DROPSHIPPING_METHOD]" id="{$module_short_name|escape:'htmlall':'UTF-8'}_method">
                        {html_options options=$methods selected=$configurations['DROPSHIPPING_METHOD']}
                    </select>
                </div>
            </div>

            <div class="form-group clear">
                <label for="{$module_short_name|escape:'htmlall':'UTF-8'}_method">{l s='Catalog' mod='cron'}</label>
                <div class="{if $version_16 && $bootstrap}input-group{else}margin-form{/if}">
                    <select name="catalogs[DROPSHIPPING_CATALOG]" id="{$module_short_name|escape:'htmlall':'UTF-8'}_catalog">
                        {html_options options=$catalogs selected=$configurations['DROPSHIPPING_CATALOG']}
                    </select>
                </div>
            </div>

            <div class="form-group clear">
                <label for="{$module_short_name|escape:'htmlall':'UTF-8'}_size">{l s='Size' mod='cron'}</label>
                <div class="{if $version_16 && $bootstrap}input-group{else}margin-form{/if}">
                    <select name="catalogs[DROPSHIPPING_SIZE]" id="{$module_short_name|escape:'htmlall':'UTF-8'}_size">
                        {html_options options=$attributes selected=$configurations['DROPSHIPPING_SIZE']}
                    </select>
                </div>
            </div>

            <div class="form-group clear">
                <label for="{$module_short_name|escape:'htmlall':'UTF-8'}_gender">{l s='Gender' mod='cron'}</label>
                <div class="{if $version_16 && $bootstrap}input-group{else}margin-form{/if}">
                    <select name="catalogs[DROPSHIPPING_GENDER]" id="{$module_short_name|escape:'htmlall':'UTF-8'}_gender">
                        {html_options options=$attributes selected=$configurations['DROPSHIPPING_GENDER']}
                    </select>
                </div>
            </div>

            <div class="form-group clear">
                <label for="{$module_short_name|escape:'htmlall':'UTF-8'}_color">{l s='Color' mod='cron'}</label>
                <div class="{if $version_16 && $bootstrap}input-group{else}margin-form{/if}">
                    <select name="catalogs[DROPSHIPPING_COLOR]" id="{$module_short_name|escape:'htmlall':'UTF-8'}_color">
                        {html_options options=$attributes selected=$configurations['DROPSHIPPING_COLOR']}
                    </select>
                </div>
            </div>

            <div class="form-group clear">
                <label for="{$module_short_name|escape:'htmlall':'UTF-8'}_season">{l s='Season' mod='cron'}</label>
                <div class="{if $version_16 && $bootstrap}input-group{else}margin-form{/if}">
                    <select name="catalogs[DROPSHIPPING_SEASON]" id="{$module_short_name|escape:'htmlall':'UTF-8'}_season">
                        {html_options options=$attributes selected=$configurations['DROPSHIPPING_SEASON']}
                    </select>
                </div>
            </div>

            <div class="form-group clear">
                <label for="{$module_short_name|escape:'htmlall':'UTF-8'}_category_structure">{l s='Category Structure' mod='cron'}</label>
                <div class="{if $version_16 && $bootstrap}input-group{else}margin-form{/if}">
                    <select name="catalogs[DROPSHIPPING_CATEGORY_STRUCTURE]" id="{$module_short_name|escape:'htmlall':'UTF-8'}_category_structure">
                        {html_options options=$category_structure selected=$configurations['DROPSHIPPING_CATEGORY_STRUCTURE']}
                    </select>
                </div>
            </div>

            <div class="form-group clear">
                <label for="{$module_short_name|escape:'htmlall':'UTF-8'}_import_image">{l s='Import Image' mod='cron'}</label>
                <div class="{if $version_16 && $bootstrap}input-group{else}margin-form{/if}">
                    <select name="catalogs[DROPSHIPPING_IMPORT_IMAGE]" id="{$module_short_name|escape:'htmlall':'UTF-8'}_import_image">
                        {html_options options=$import_image selected=$configurations['DROPSHIPPING_IMPORT_IMAGE']}
                    </select>
                </div>
            </div>

            <div class="form-group clear">
                <label>{l s='Import Retail Price' mod='cron'}</label>
                <div class="margin-form">
                    <span class="radio">
                        <input type="radio" name="catalogs[DROPSHIPPING_IMPORT_RETAIL]" id="{$module_short_name|escape:'htmlall':'UTF-8'}_import_retail_on" value="1" {if $configurations['DROPSHIPPING_IMPORT_RETAIL']}checked="checked"{/if} />
                        <label for="{$module_short_name|escape:'htmlall':'UTF-8'}_import_retail_on">{l s='Yes' mod='cron'}</label>
                        <input type="radio" name="catalogs[DROPSHIPPING_IMPORT_RETAIL]" id="{$module_short_name|escape:'htmlall':'UTF-8'}_import_retail_off" value="0" {if !$configurations['DROPSHIPPING_IMPORT_RETAIL']}checked="checked"{/if} />
                        <label for="{$module_short_name|escape:'htmlall':'UTF-8'}_import_retail_off">{l s='No' mod='cron'}</label>
                    </span>
                    <p {if $version_16}class="help-block"{/if}>
                        {l s='import retail price and sell price' mod='cron'}
                    </p>
                </div>
            </div>

            <div class="form-group clear">
                <label for="{$module_short_name|escape:'htmlall':'UTF-8'}_limit_count">{l s='Import Product Per Minute' mod='cron'}</label>
                <div class="{if $version_16 && $bootstrap}input-group{else}margin-form{/if}">
                    <input type="text" name="catalogs[DROPSHIPPING_LIMIT_COUNT]" value="{$configurations['DROPSHIPPING_LIMIT_COUNT']}">
                </div>
            </div>

            <div class="clear panel-footer">
                <input type="hidden" name="active_tab" value="tabCatalogs" />
                <input type="hidden" name="saveCatalogs" value="1">
                <p>
                    <input type="submit" class="samdha_button" value="{l s='Save' mod='cron'}" />
                </p>
            </div>
        </form>
    </div>
</div>

<div id="tabStatus" class="col-lg-10 col-md-9">
    <div class="panel">
        <h3 class="tab"> <i class="icon-question-sign"></i> {l s='Status' mod='cron'}</h3>
        <div class="card">
            <h2>API Connection Status</h2>
            <p><strong>URL</strong> {$base_url}</p>
            <p><strong>API Key</strong> {$api_key}</p>
            <p><strong>Status</strong> {$txtStatus}</p>
        </div>
    </div>
</div>
