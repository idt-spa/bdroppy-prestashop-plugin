{*
* NOTICE OF LICENSE.
*
* This file is licenced under the Software License Agreement.
* With the purchase or the installation of the software in your application
* you accept the licence agreement.
*
* You must not modify, adapt or create derivative works of this source code
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2020 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*}
<script type="text/javascript">
	$(document).ready(function() {
		$(".btn_change_token").click(function(){
			$("#div_status").slideToggle();
			$("#div_change_token").slideToggle();
			return false;
		});
		$(".stripe-module-wrapper .list-group .list-group-item").click(function(){
			$(".list-group .list-group-item").removeClass("active");
			$(this).addClass("active");
			var ID = $(this).data("id");
			$(".stripe-module-wrapper fieldset").removeClass("show");
			$("#"+ID).addClass("show");
			$("html, body").animate({ scrollTop: 0 });
		});
		$('input[name*="bdroppy_custom_feature"]').on('input', function() {
			$('#custom_feature_msg').html('<div class=""></div>');
			if ($(this).val() == '1') {
				$('#custom_feature_msg').html('<div class="alert alert-danger">Custom Features will add new features for every products, are you sure?</div>');
			}
		});
	});
</script>
{$confirmations_tab = ''}
{$confirmations_form = ''}
{$my_catalogs_tab = ''}
{$my_catalogs_form = ''}
{$orders_tab = ''}
{$orders_form = ''}
{if $active_tab == 'configurations'}
	{$confirmations_tab = ' active'}
	{$confirmations_form = 'show'}
{/if}
{if $active_tab == 'my_catalogs'}
	{$my_catalogs_tab = ' active'}
	{$my_catalogs_form = 'show'}
{/if}
{if $active_tab == 'orders'}
	{$orders_tab = ' active'}
	{$orders_form = 'show'}
{/if}
<link href="{$stripeBOCssUrl|escape:'htmlall':'UTF-8'}" rel="stylesheet" type="text/css">

<div class="tabs stripe-module-wrapper">
	{if !empty($confirmations)}
		{$confirmations}
	{/if}
	{if !empty($errors)}
		<fieldset style="display:block;">
			<legend>Errors</legend>
			<table cellspacing="0" cellpadding="0" class="stripe-technical">
				<tbody>
				{foreach $errors as $error}
					<tr>
						<td><img src="../img/admin/status_red.png" alt=""></td>
						<td>{$error|escape:'htmlall':'UTF-8'}</td>
					</tr>
				{/foreach}
				</tbody></table>
		</fieldset>
	{/if}

	<div class="row">
		<div class="sidebar navigation col-md-2">
			<nav class="list-group categorieList">
				<a class="list-group-item{$confirmations_tab|escape:'htmlall':'UTF-8'}" data-id="configurations" href="#configurations"><i class="icon-gear"></i> {l s='Configurations' mod='bdroppy'}</a>
				<a class="list-group-item{$my_catalogs_tab|escape:'htmlall':'UTF-8'}" data-id="my_catalogs" href="#my_catalogs"><i class="icon-ok-sign"></i> {l s='My Catalogs' mod='bdroppy'}</a>
				<a class="list-group-item{$orders_tab|escape:'htmlall':'UTF-8'}" data-id="orders" href="#orders"><i class="icon-list"></i> {l s='Orders' mod='bdroppy'}</a>
				<a class="list-group-item" data-id="status" href="#status"><i class="icon-question-sign"></i> {l s='Status' mod='bdroppy'}</a>
				<a class="list-group-item" data-id="about" href="#about"><i class="icon-info-sign"></i> {l s='About' mod='bdroppy'}</a>
			</nav>
		</div>
		<div class="panel content-wrap form-horizontal col-lg-10">

			<form action="" method="post">
				<fieldset id="configurations" class="{$confirmations_form|escape:'htmlall':'UTF-8'}">
					<h3 class="tab"> <i class="icon-gear"></i>&nbsp;{l s='Configurations' mod='bdroppy'}</h3>
					<div class="row">
						<div class="col-lg-8">
							<p style="font-size: 1.5em; font-weight: bold; padding-bottom: 0">{l s='API Connection Status' mod='bdroppy'}</p>
							<div id="div_status" style="display: inherit">
								<p><strong>URL</strong> {$base_url|escape:'htmlall':'UTF-8'}</p>
								<p><strong>Email</strong> {Configuration::get('BDROPPY_API_KEY')|escape:'htmlall':'UTF-8'}</p>
								<p><strong>Status</strong> {$txtStatus}</p>
								<p><button class="btn_change_token"><i class="icon-signout"></i> {l s='Disconnect' mod='bdroppy'}</button></p>
							</div>

							<div id="div_change_token" style="display: none">
								<input type="hidden" name="bdroppy_api_url" value="https://prod.bdroppy.com" />
								<div class="form-group">
									<label class="control-label col-lg-3" for="simple_product">{l s='Email' mod='bdroppy'}:</label>
									<div class="col-lg-7">
										<input type="text" name="bdroppy_api_key" value="{Configuration::get('BDROPPY_API_KEY')|escape:'htmlall':'UTF-8'}" />
									</div>
								</div>
								<div class="form-group">
									<label class="control-label col-lg-3" for="simple_product">{l s='Password' mod='bdroppy'}:</label>
									<div class="col-lg-7">
										<input type="password" name="bdroppy_api_password" value="" />
									</div>
								</div>
								<div class="form-group">
									<label class="control-label col-lg-3" for="simple_product"></label>
									<div class="col-lg-7">
										<button type="submit" name="submitApiConfig" class="btn btn-default pull-right"><i class="icon-signin"></i> {l s='Login' mod='bdroppy'}</button>
										<button class="btn_change_token btn btn-default pull-right"><i class="icon-remove"></i> {l s='Cancel' mod='bdroppy'}</button>
									</div>
								</div>
							</div>
							<hr/>
							{if $warnings}
								<ul class="alert alert-danger" style="padding-left:70px;">
									{foreach $warnings as $warning}
										<li>{$warning|escape:'htmlall':'UTF-8'}</li>
									{/foreach}
								</ul>
							{/if}
							{if $successes}
								<ul class="alert alert-success" style="padding-left:70px;">
									{foreach $successes as $success}
										<li>{$success|escape:'htmlall':'UTF-8'}</li>
									{/foreach}
								</ul>
							{/if}
						</div>
						<div class="col-lg-4">
							<p style="font-size: 1.5em; font-weight: bold; padding-bottom: 0"><img src="{$module_path|escape:'htmlall':'UTF-8'}logo.png" alt="{$module_display_name|escape:'htmlall':'UTF-8'}" style="float: left; padding-right: 1em"/>Bdroppy</p>
							<br>
							<p>Version : {$module_version|escape:'htmlall':'UTF-8'}</p>
							<br>
							<p style="clear: left;">{l s='Thanks for installing this module on your website.' mod='bdroppy'}</p>
							{if $description_big_html}{$description_big_html|escape:'htmlall':'UTF-8'}{else}<p>{$description|escape:'htmlall':'UTF-8'}</p>{/if}
							<p>
								{l s='Made with by' mod='bdroppy'} <a style="color: #7ba45b; text-decoration: underline;" target="_blank" href="{$home_url|escape:'htmlall':'UTF-8'}">Brandsdistribution</a>
							</p>
						</div>
					</div>
				</fieldset>
			</form>

			<form action="" method="post">
				<fieldset id="my_catalogs" class="{$my_catalogs_form|escape:'htmlall':'UTF-8'}">
					<h3 class="tab"> <i class="icon-ok-sign"></i>&nbsp;{l s='My Catalogs' mod='bdroppy'}</h3>
					<div class="form-group">
						<label class="control-label col-lg-3" for="simple_product">{l s='Catalog' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<select name="bdroppy_catalog" id="bdroppy_catalog">
								{html_options options=$catalogs selected=Configuration::get('BDROPPY_CATALOG')}
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="active_product">{l s='Active Product' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<span class="switch prestashop-switch fixed-width-lg">
								<input name="bdroppy_active_product" id="bdroppy_active_product_on" value="1" {if $bdroppy_active_product}checked="checked"{/if} type="radio">
								<label for="bdroppy_active_product_on" class="radioCheck">
								Yes
								</label>
								<input name="bdroppy_active_product" id="bdroppy_active_product_off" value="0" {if !$bdroppy_active_product}checked="checked"{/if} type="radio">
								<label for="bdroppy_active_product_off" class="radioCheck">
								No
								</label>
								<a class="slide-button btn"></a>
							</span>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="custom_feature">{l s='Custom Feature' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<span class="switch prestashop-switch fixed-width-lg">
								<input name="bdroppy_custom_feature" id="bdroppy_custom_feature_on" value="1" {if $bdroppy_custom_feature}checked="checked"{/if} type="radio">
								<label for="bdroppy_custom_feature_on" class="radioCheck">
								Yes
								</label>
								<input name="bdroppy_custom_feature" id="bdroppy_custom_feature_off" value="0" {if !$bdroppy_custom_feature}checked="checked"{/if} type="radio">
								<label for="bdroppy_custom_feature_off" class="radioCheck">
								No
								</label>
								<a class="slide-button btn"></a>
							</span>
							<br/>
							<span id="custom_feature_msg">
								{if $bdroppy_custom_feature}
									<div class="alert alert-danger">Custom Features will add new features for every products, are you sure?</div>
								{/if}
							</span>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="simple_product">{l s='Size' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<select name="bdroppy_size" id="bdroppy_size">
								{html_options options=$attributes selected=Configuration::get('BDROPPY_SIZE')}
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="simple_product">{l s='Gender' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<select name="bdroppy_gender" id="bdroppy_gender">
								{html_options options=$attributes selected=Configuration::get('BDROPPY_GENDER')}
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="simple_product">{l s='Color' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<select name="bdroppy_color" id="bdroppy_color">
								{html_options options=$attributes selected=Configuration::get('BDROPPY_COLOR')}
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="simple_product">{l s='Season' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<select name="bdroppy_season" id="bdroppy_season">
								{html_options options=$attributes selected=Configuration::get('BDROPPY_SEASON')}
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="simple_product">{l s='Category Structure' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<select name="bdroppy_category_structure" id="bdroppy_category_structure">
								{html_options options=$category_structure selected=Configuration::get('BDROPPY_CATEGORY_STRUCTURE')}
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="simple_product">{l s='Import Image' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<select name="bdroppy_import_image" id="bdroppy_import_image">
								{html_options options=$import_image selected=Configuration::get('BDROPPY_IMPORT_IMAGE')}
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="simple_product">{l s='Reimport Images On Updating' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<span class="switch prestashop-switch fixed-width-lg">
								<input name="bdroppy_reimport_image" id="bdroppy_reimport_image_on" value="1" {if $bdroppy_reimport_image}checked="checked"{/if} type="radio">
								<label for="bdroppy_reimport_image_on" class="radioCheck">
								Yes
								</label>
								<input name="bdroppy_reimport_image" id="bdroppy_reimport_image_off" value="0" {if !$bdroppy_reimport_image}checked="checked"{/if} type="radio">
								<label for="bdroppy_reimport_image_off" class="radioCheck">
								No
								</label>
								<a class="slide-button btn"></a>
							</span>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="simple_product">{l s='Import Brand To Title' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<span class="switch prestashop-switch fixed-width-lg">
								<input name="bdroppy_import_brand_to_title" id="bdroppy_import_brand_to_title_on" value="1" {if $bdroppy_import_brand_to_title}checked="checked"{/if} type="radio">
								<label for="bdroppy_import_brand_to_title_on" class="radioCheck">
								Yes
								</label>
								<input name="bdroppy_import_brand_to_title" id="bdroppy_import_brand_to_title_off" value="0" {if !$bdroppy_import_brand_to_title}checked="checked"{/if} type="radio">
								<label for="bdroppy_import_brand_to_title_off" class="radioCheck">
								No
								</label>
								<a class="slide-button btn"></a>
							</span>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="simple_product">{l s='Import Tag To Title' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<select name="bdroppy_import_tag_to_title" id="bdroppy_import_tag_to_title">
								{html_options options=$bdroppy_import_tag_to_title selected=Configuration::get('BDROPPY_IMPORT_TAG_TO_TITLE')}
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="simple_product">{l s='Auto Update Prices' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<span class="switch prestashop-switch fixed-width-lg">
								<input name="bdroppy_auto_update_prices" id="bdroppy_auto_update_prices_on" value="1" {if $bdroppy_auto_update_prices}checked="checked"{/if} type="radio">
								<label for="bdroppy_auto_update_prices_on" class="radioCheck">
								Yes
								</label>
								<input name="bdroppy_auto_update_prices" id="bdroppy_auto_update_prices_off" value="0" {if !$bdroppy_auto_update_prices}checked="checked"{/if} type="radio">
								<label for="bdroppy_auto_update_prices_off" class="radioCheck">
								No
								</label>
								<a class="slide-button btn"></a>
							</span>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="simple_product">{l s='Auto Update Products Name' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<span class="switch prestashop-switch fixed-width-lg">
								<input name="bdroppy_auto_update_name" id="bdroppy_auto_update_name_on" value="1" {if $bdroppy_auto_update_name}checked="checked"{/if} type="radio">
								<label for="bdroppy_auto_update_name_on" class="radioCheck">
								Yes
								</label>
								<input name="bdroppy_auto_update_name" id="bdroppy_auto_update_name_off" value="0" {if !$bdroppy_auto_update_name}checked="checked"{/if} type="radio">
								<label for="bdroppy_auto_update_name_off" class="radioCheck">
								No
								</label>
								<a class="slide-button btn"></a>
							</span>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="simple_product">{l s='Tax Rule' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<select name="bdroppy_tax_rule" id="bdroppy_tax_rule">
								{html_options options=$tax_rule selected=Configuration::get('BDROPPY_TAX_RULE')}
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="simple_product">{l s='Tax Rate' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<select name="bdroppy_tax_rate" id="bdroppy_tax_rate">
								{html_options options=$tax_rate selected=Configuration::get('BDROPPY_TAX_RATE')}
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="simple_product">{l s='Import Product Per Minute' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<select name="bdroppy_limit_count" id="bdroppy_limit_count">
								{html_options options=$limit_counts selected=Configuration::get('BDROPPY_LIMIT_COUNT')}
							</select>
						</div>
					</div>
					<div class="form-group">
						<label class="control-label col-lg-3" for="simple_product">{l s='Show debug messages in logs' mod='bdroppy'}:</label>
						<div class="col-lg-7">
							<span class="switch prestashop-switch fixed-width-lg">
								<input name="bdroppy_log" id="bdroppy_log_on" value="1" {if $bdroppy_log}checked="checked"{/if} type="radio">
								<label for="bdroppy_log_on" class="radioCheck">
								Yes
								</label>
								<input name="bdroppy_log" id="bdroppy_log_off" value="0" {if !$bdroppy_log}checked="checked"{/if} type="radio">
								<label for="bdroppy_log_off" class="radioCheck">
								No
								</label>
								<a class="slide-button btn"></a>
							</span>
						</div>
					</div>
					<div class="panel-footer">
						<button type="submit" name="submitCatalogConfig" class="btn btn-default pull-right"><i class="icon-link"></i> {l s='Connect' mod='bdroppy'}</button>
					</div>
				</fieldset>
			</form>

			<form action="" method="post">
				<fieldset id="orders" class="{$orders_form|escape:'htmlall':'UTF-8'}">
					<h3 class="tab"> <i class="icon-list"></i>&nbsp;{l s='Orders' mod='bdroppy'}</h3>
					{$ordersHtml}
				</fieldset>
			</form>

			<form action="" method="post">
				<fieldset id="status" class="">
					<h3 class="tab"> <i class="icon-question-sign"></i>&nbsp;{l s='Status' mod='bdroppy'}</h3>
					<div class="card">
						<h2>{l s='Import Queue Status' mod='bdroppy'}</h2>
						<p><strong>{l s='Queued' mod='bdroppy'}</strong> {$queue_queued|escape:'htmlall':'UTF-8'}</p>
						<p><strong>{l s='Importing' mod='bdroppy'}</strong> {$queue_importing|escape:'htmlall':'UTF-8'}</p>
						<p><strong>{l s='Imported' mod='bdroppy'}</strong> {$queue_imported|escape:'htmlall':'UTF-8'}</p>
						<p><strong>{l s='All' mod='bdroppy'}</strong> {$queue_all|escape:'htmlall':'UTF-8'}</p>
						<hr/>
						<p><strong>{l s='Last Cron Sync' mod='bdroppy'}</strong> {$last_cron_sync|escape:'htmlall':'UTF-8'}</p>
						<p><strong>{l s='Last Import Sync' mod='bdroppy'}</strong> {$last_import_sync|escape:'htmlall':'UTF-8'}</p>
						<p><strong>{l s='Last Update Sync' mod='bdroppy'}</strong> {$last_update_sync|escape:'htmlall':'UTF-8'}</p>
						<p><strong>{l s='Last Orders Sync' mod='bdroppy'}</strong> {$last_orders_sync|escape:'htmlall':'UTF-8'}</p>
					</div>
				</fieldset>
			</form>

			<form action="" method="post">
				<fieldset id="about" class="">
					<h3 class="tab"> <i class="icon-info-sign"></i>&nbsp;{l s='About' mod='bdroppy'}</h3>
					<p style="font-size: 1.5em; font-weight: bold; padding-bottom: 0"><img src="{$module_path|escape:'htmlall':'UTF-8'}logo.png" alt="{$module_display_name|escape:'htmlall':'UTF-8'}" style="float: left; padding-right: 1em"/>Bdroppy</p>
					<br>
					<p>Version : {$module_version|escape:'htmlall':'UTF-8'}</p>
					<br>
					<p style="clear: left;">{l s='Thanks for installing this module on your website.' mod='bdroppy'}</p>
					{if $description_big_html}{$description_big_html|escape:'htmlall':'UTF-8'}{else}<p>{$description|escape:'htmlall':'UTF-8'}</p>{/if}
					<p>
						{l s='Made with by' mod='bdroppy'} <a style="color: #7ba45b; text-decoration: underline;" target="_blank" href="{$home_url|escape:'htmlall':'UTF-8'}">Brandsdistribution</a>
					</p>
				</fieldset>
			</form>

		</div>
	</div>
</div>
