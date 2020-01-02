<?php
/**
 * Cron module
 *
 * @author    Samdha <contact@samdha.net>
 * @copyright Samdha
 * @license   commercial license see license.txt
 * @category  Prestashop
 * @category  Module
 * @link      http://www.gnu.org/licenses/lgpl.html logo license
 * @link      http://www.icon-king.com/ - David Vignoni - logo author
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

include(_PS_MODULE_DIR_.'cron/autoloader.php');
spl_autoload_register('sdCronAutoload');

class Cron extends Samdha_Cron_Main
{
    public function __construct()
    {
        $this->author = 'Hamid Isaac';
        $this->tab = 'administration';
        $this->version = '0.0.1';
        $this->module_key = '';
        $this->name = 'cron';

        parent::__construct();

        $this->displayName = $this->l('Dropshipping');
        $this->description = $this->l('Dropshipping of Brandsdistribution');
    }
}
