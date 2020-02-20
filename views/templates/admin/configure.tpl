<script type="text/javascript">
	$(document).ready(function() {
		$(".stripe-module-wrapper .list-group .list-group-item").click(function(){
			$(".list-group .list-group-item").removeClass("active");
			$(this).addClass("active");
			var ID = $(this).data("id");
			$(".stripe-module-wrapper fieldset").removeClass("show");
			$("#"+ID).addClass("show");
			$("html, body").animate({ scrollTop: 0 });
		});
	});
</script>
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

	<div class="sidebar navigation col-md-2">
		<nav class="list-group categorieList">
			<a class="list-group-item active" data-id="configurations" href="#configurations"><i class="icon-gear"></i> {l s='Configurations' mod='bdroppy'}</a>
			<a class="list-group-item" data-id="my_catalogs" href="#my_catalogs"><i class="icon-ok-sign"></i> {l s='My Catalogs' mod='bdroppy'}</a>
			<a class="list-group-item" data-id="status" href="#status"><i class="icon-question-sign"></i> {l s='Status' mod='bdroppy'}</a>
			<a class="list-group-item" data-id="about" href="#about"><i class="icon-info-sign"></i> {l s='About' mod='bdroppy'}</a>
		</nav>
	</div>
	<div class="panel content-wrap form-horizontal col-lg-10">

		<form action="" method="post">
			<fieldset id="configurations" class="show">
				<h3 class="tab"> <i class="icon-gear"></i>&nbsp;{l s='Configurations' mod='bdroppy'}</h3>
				<div class="form-group">
					<label class="control-label col-lg-3" for="simple_product">{l s='API URL' mod='bdroppy'}:</label>
					<div class="col-lg-7">
						<select name="bdroppy_api_url" id="bdroppy_api_url">
							{html_options options=$urls selected=Configuration::get('BDROPPY_API_URL')}
						</select>
					</div>
				</div>
				<div class="form-group">
					<label class="control-label col-lg-3" for="simple_product">{l s='API Key' mod='bdroppy'}:</label>
					<div class="col-lg-7">
						<input type="text" name="bdroppy_api_key" value="{Configuration::get('BDROPPY_API_KEY')|escape:'htmlall':'UTF-8'}" />
					</div>
				</div>
				<div class="form-group">
					<label class="control-label col-lg-3" for="simple_product">{l s='API Password' mod='bdroppy'}:</label>
					<div class="col-lg-7">
						<input type="text" name="bdroppy_api_password" value="{Configuration::get('BDROPPY_API_PASSWORD')|escape:'htmlall':'UTF-8'}" />
					</div>
				</div>
				<div class="panel-footer">
					<button type="submit" name="submitApiConfig" class="btn btn-default pull-right"><i class="process-icon-save"></i> {l s='Save' mod='bdroppy'}</button>
				</div>
			</fieldset>
		</form>

		<form action="" method="post">
			<fieldset id="my_catalogs" class="">
				<h3 class="tab"> <i class="icon-ok-sign"></i>&nbsp;{l s='My Catalogs' mod='bdroppy'}</h3>
				<div class="alert alert-info">
					{l s='Please choose the method used to determine when executing jobs.' mod='cron'}
					<ol style="margin-top: 1em">
						<li style="list-style:decimal">
							{l s='"Webcron service" is a good alternative to crontab but is often not free. Register to a webcron service and configure it to visit the URL below every minutes or the nearest.' mod='cron'}
							<br/><pre>{$cron_url|escape:'htmlall':'UTF-8'}</pre><br/>
						</li>
					</ol>
				</div>
				<div class="form-group">
					<label class="control-label col-lg-3" for="simple_product">{l s='Catalog' mod='bdroppy'}:</label>
					<div class="col-lg-7">
						<select name="bdroppy_catalog" id="bdroppy_catalog">
							{html_options options=$catalogs selected=Configuration::get('BDROPPY_CATALOG')}
						</select>
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
					<label class="control-label col-lg-3" for="simple_product">{l s='Import Product Per Minute' mod='bdroppy'}:</label>
					<div class="col-lg-7">
						<input type="text" name="bdroppy_limit_count" value="{Configuration::get('BDROPPY_LIMIT_COUNT')|escape:'htmlall':'UTF-8'}" />
					</div>
				</div>
				<div class="panel-footer">
					<button type="submit" name="submitCatalogConfig" class="btn btn-default pull-right"><i class="process-icon-save"></i> {l s='Save' mod='bdroppy'}</button>
				</div>
			</fieldset>
		</form>

		<form action="" method="post">
			<fieldset id="status" class="">
				<h3 class="tab"> <i class="icon-question-sign"></i>&nbsp;{l s='Status' mod='bdroppy'}</h3>
				<div class="card">
					<h2>API Connection Status</h2>
					<p><strong>URL</strong> {$base_url}</p>
					<p><strong>API Key</strong> {$api_key}</p>
					<p><strong>Status</strong> {$txtStatus}</p>
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
				<p style="clear: left;">{l s='Thanks for installing this module on your website.' mod='samdha'}</p>
				{if $description_big_html}{$description_big_html}{else}<p>{$description|escape:'htmlall':'UTF-8'}</p>{/if}
				<p>
					{l s='Made with by' mod='Hamid Isaac'} <a style="color: #7ba45b; text-decoration: underline;" href="{$home_url|escape:'htmlall':'UTF-8'}">Brandsdistribution</a>
				</p>
			</fieldset>
		</form>

	</div>
</div>