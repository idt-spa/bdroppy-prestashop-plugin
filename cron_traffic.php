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

/* generate empty picture http://www.nexen.net/articles/dossier/16997-une_image_vide_sans_gd.php */
$hex = '47494638396101000100800000ffffff00000021f90401000000002c00000000010001000002024401003b';
$img = '';
$t = strlen($hex) / 2;
for ($i = 0; $i < $t; $i++)
	$img .= chr(hexdec(substr($hex, $i * 2, 2)));
header('Last-Modified: Fri, 01 Jan 1999 00:00 GMT', true, 200);
header('Content-Length: '.strlen($img));
header('Content-Type: image/gif');
echo $img;

if (Configuration::get('cron_method') == 'traffic')
	Module::getInstanceByName('cron')->runJobs();
?>
