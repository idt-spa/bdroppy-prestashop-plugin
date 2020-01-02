<?php
/**
 * Cron module
 * Cron is a time-based job scheduler in Unix-like computer operating systems.
 * This module automaticaly executes jobs like Cron
 *
 * @category Prestashop
 * @category Module
 * @author Samdha <contact@samdha.net>
 * @copyright Samdha
 * @license commercial license see license.txt
 * @author logo Alessandro Rei
 * @license logo http://www.gnu.org/copyleft/gpl.html GPLv3
 * @version 1.3
**/

if (isset($argv) && !empty($argv))
	foreach ($argv as $k => $v)
		if ($k != 0)
		{
			$it = explode('=', $v);
			if (isset($it[1]))
				$_GET[$it[0]] = $it[1];
			else
				$_GET[$it[0]] = null;
		}

define('_PS_ADMIN_DIR_', getcwd());
if (!defined('STDIN'))
	define('STDIN', 1);

require_once(dirname(__FILE__).'/../../config/config.inc.php');
if (version_compare(_PS_VERSION_, '1.5.0.0', '>='))
{
	Context::getContext()->controller = new stdClass();
	Context::getContext()->controller->controller_type = 'cron'; /* avoid notice */
}
else
{
	if (!defined('_PS_BASE_URL_') && method_exists('Tools', 'getShopDomain'))
		define('_PS_BASE_URL_', Tools::getShopDomain(true));
	if (!defined('_PS_BASE_URL_SSL_') && method_exists('Tools', 'getShopDomainSsl'))
		define('_PS_BASE_URL_SSL_', Tools::getShopDomainSsl(true));
	if (!isset($link))
		$link = new Link();

	if (!isset($cookie))
		$cookie = new Cookie('ps', '');
}

if (Configuration::get('cron_method') == 'crontab')
	Module::getInstanceByName('cron')->runJobs();
