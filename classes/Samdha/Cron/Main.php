<?php
/**
 * Cron module
 * Cron is a time-based job scheduler in Unix-like computer operating systems.
 * This module automaticaly executes jobs like Cron
 *
 * Add a cron job
 * Module::getInstanceByName('cron')->addCron($this->id, 'myMethod', '5 * * * *');
 *
 * last parameter details :
 * .---------------- minute (0 - 59)
 * |  .------------- hour (0 - 23)
 * |  |  .---------- day of month (1 - 31)
 * |  |  |  .------- month (1 - 12)
 * |  |  |  |  .---- day of week (0 - 6) (Sunday=0 )
 * |  |  |  |  |
 * *  *  *  *  *
 *
 * remarks :
 * It accepts the standard crontab format except steps ('/') :  0-50/5 * * * * isn't valid
 *
 * exemples :
 * - 1 0 * * * : 00:01 of every day of the month, of every day of the week
 * - 15 3 * * 1-5 : every weekday morning at 3:15 am
 * - 0 0 1,15-17 * * : the first, fifteenth, sixteenth and seventeenth of each month at 00:00
 * - 0 0 * * 1 : every Monday at 00:00
 *
 * Delete a job
 * Module::getInstanceByName('cron')->deleteCron($this->id, 'myMethod');
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author Samdha <contact@samdha.net>
 * @copyright Samdha
 * @license commercial license see license.txt
 * @category Prestashop
 * @category Module
 * @author logo Alessandro Rei http://www.kde-look.org/content/show.php/Dark-Glass+reviewed?content=67902
 * @license logo http://www.gnu.org/copyleft/gpl.html GPLv3
 * @version 2.0.0
**/

class Samdha_Cron_Main extends Samdha_Commons_Module
{
    public $short_name = 'cron';
    public function __construct()
    {
        if (version_compare(_PS_VERSION_, '1.4.0.0', '<')) {
            $this->tab = 'Tools';
        }

        parent::__construct();
        parent::__construct();

        $this->cron = new Samdha_Cron_Cron($this);
        $this->tools = new Samdha_Cron_Tools($this);
    }

    /**
     * install the module
     *
     * create table in BDD
     * hook the module to footer
    **/
    public function install()
    {
        $languages = Language::getLanguages(false);
        $sql = "SELECT * FROM `" . _DB_PREFIX_ . "attribute_group_lang` WHERE name='Size';";
        $r = Db::getInstance()->executeS($sql);
        if (!$r) {
            $newGroup = new AttributeGroup();
            foreach ($languages as $lang) {
                $newGroup->name[$lang['id_lang']] = 'Size';
                $newGroup->public_name[$lang['id_lang']] = 'Size';
            }
            $newGroup->is_color_group = 1;
            $newGroup->group_type = 'test';
            $newGroup->save();
        }

        $sql = "SELECT * FROM `" . _DB_PREFIX_ . "attribute_group_lang` WHERE name='Gender';";
        $r = Db::getInstance()->executeS($sql);
        if (!$r) {
            $newGroup = new AttributeGroup();
            foreach ($languages as $lang) {
                $newGroup->name[$lang['id_lang']] = 'Gender';
                $newGroup->public_name[$lang['id_lang']] = 'Gender';
            }
            $newGroup->is_color_group = 1;
            $newGroup->group_type = 'test';
            $newGroup->save();
        }

        $sql = "SELECT * FROM `" . _DB_PREFIX_ . "attribute_group_lang` WHERE name='Color';";
        $r = Db::getInstance()->executeS($sql);
        if (!$r) {
            $newGroup = new AttributeGroup();
            foreach ($languages as $lang) {
                $newGroup->name[$lang['id_lang']] = 'Color';
                $newGroup->public_name[$lang['id_lang']] = 'Color';
            }
            $newGroup->is_color_group = 1;
            $newGroup->group_type = 'test';
            $newGroup->save();
        }

        $sql = "SELECT * FROM `" . _DB_PREFIX_ . "attribute_group_lang` WHERE name='Season';";
        $r = Db::getInstance()->executeS($sql);
        if (!$r) {
            $newGroup = new AttributeGroup();
            foreach ($languages as $lang) {
                $newGroup->name[$lang['id_lang']] = 'Season';
                $newGroup->public_name[$lang['id_lang']] = 'Season';
            }
            $newGroup->is_color_group = 1;
            $newGroup->group_type = 'test';
            $newGroup->save();
        }

        $sql = "SELECT * FROM `" . _DB_PREFIX_ . "attribute_group_lang` WHERE name='Brand';";
        $r = Db::getInstance()->executeS($sql);
        if (!$r) {
            $newGroup = new AttributeGroup();
            foreach ($languages as $lang) {
                $newGroup->name[$lang['id_lang']] = 'Brand';
                $newGroup->public_name[$lang['id_lang']] = 'Brand';
            }
            $newGroup->is_color_group = 1;
            $newGroup->group_type = 'test';
            $newGroup->save();
        }

        include(_PS_ROOT_DIR_.'/modules/dropshipping/sql/install.php');
        return (parent::install()
            && $this->registerHook('footer'));
    }

    /**
     * module uninstallation
     */
    public function uninstall()
    {
        include(_PS_ROOT_DIR_.'/modules/dropshipping/sql/uninstall.php');
        return (parent::uninstall());
    }

    /**
    * set default config
    **/
    public function getDefaultConfig()
    {
        return array(
            '_method'   => 'traffic',
            '_test'     => 0,
            '_lasttime' => 0,
            '_lasttest' => 0,
            '_token'    => null,
        );
    }

    /**
     * display admin form
     *
     * @param string $token
     * @return string The from
     */
    public function displayForm($token)
    {
        $configurations = $this->getConfigFormValues();
        $tabs = array();
        $tabs[] = array('href' => '#tabConfigurations', 'display_name' => $this->l('Configurations', 'main'));
        $tabs[] = array('href' => '#tabCatalogs', 'display_name' => $this->l('My Catalogs', 'main'));
        $tabs[] = array('href' => '#tabStatus', 'display_name' => $this->l('Status', 'main'));

        $base_url      = isset($configurations['DROPSHIPPING_URL']) ? $configurations['DROPSHIPPING_URL'] : '';
        $api_key      = isset($configurations['DROPSHIPPING_KEY']) ? $configurations['DROPSHIPPING_KEY'] : '';
        $api_password = isset($configurations['DROPSHIPPING_PASSWORD'] ) ? $configurations['DROPSHIPPING_PASSWORD'] : '';

        $url            = $base_url . '/restful/user_catalog/user/username/'.$api_key;
        $header = "authorization: Basic " . base64_encode($api_key . ':' . $api_password);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json', 'Content-Type: application/json', $header));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        $catalogs  = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $txtStatus = '<span style="color: red;">Error Code : ' . $httpCode . '</span>';
        if($httpCode == 200) {
            $txtStatus = '<span style="color: green;">Ok</span>';
        }

        $catalogs = array();
        $url = $base_url . "/restful/user_catalog/user/username/".$api_key;
        $ch = curl_init( $url );
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
        curl_setopt( $ch, CURLOPT_TIMEOUT, 5000 );
        curl_setopt( $ch, CURLOPT_USERPWD, $api_key . ':' . $api_password );
        $data = curl_exec( $ch );

        $http_code  = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
        $curl_error = curl_error( $ch );
        curl_close( $ch );
        if ($http_code === 200)
        {
            foreach(json_decode($data) as $catalog) {
                $catalogs[$catalog->_id]  = $this->l($catalog->name, 'main');
            }
        }

        //$sql = "SELECT SQL_CALC_FOUND_ROWS b.*, a.* FROM `ps_feature` a LEFT JOIN `ps_feature_lang` b ON (b.`id_feature` = a.`id_feature` AND b.`id_lang` = ".$this->context->language->id.") WHERE 1 ORDER BY a.`position` ASC";
        $sql = "SELECT SQL_CALC_FOUND_ROWS b.*, a.* FROM `ps_attribute_group` a LEFT JOIN `ps_attribute_group_lang` b ON (b.`id_attribute_group` = a.`id_attribute_group` AND b.`id_lang` = ".$this->context->language->id.") WHERE 1 ORDER BY a.`position` ASC";
        $res = Db::getInstance()->ExecuteS($sql);
        $attributes = array(
            '0' => $this->l('Select', 'main'),
        );
        foreach ($res as $attribute) {
            $attributes[$attribute['id_attribute_group']] = $attribute['name'];
        }
        $import_image = array(
            '0' => $this->l('No import', 'main'),
            '1' => $this->l('1 Picture', 'main'),
            '2' => $this->l('2 Picture', 'main'),
            '3' => $this->l('3 Picture', 'main'),
            '4' => $this->l('4 Picture', 'main'),
            'all' => $this->l('All Pictures', 'main'),
        );

        $methods = array(
            'traffic' => $this->l('Shop traffic', 'main'),
            'crontab' => $this->l('Server crontab', 'main'),
            'webcron' => $this->l('Webcron service', 'main'),
        );

        $category_structure = array(
            '1' => $this->l('Category > Subcategory', 'main'),
            '2' => $this->l('Gender > Category > Subcategory', 'main'),
        );

        $sql = '
            SELECT c.*, m.name
            FROM `'._DB_PREFIX_.'cron` c
            LEFT JOIN `'._DB_PREFIX_.'module` m ON m.`id_module` = c.`id_module`';
        $crons = Db::getInstance()->executeS($sql);
        $sql = 'SELECT * FROM `'._DB_PREFIX_.'cron_url`';
        $crons_url = Db::getInstance()->executeS($sql);

        $cron_url = $this->getCronURL();
        $cron_command = $this->getCronCommand();

        $this->smarty->assign(array(
            'tabs'              => $tabs,
            'configurations'    => $configurations,
            'methods'           => $methods,
            'attributes'           => $attributes,
            'catalogs'          => $catalogs,
            'category_structure'      => $category_structure,
            'import_image'      => $import_image,
            'php_dir'         => $this->tools->getPHPExecutableFromPath(),
            'cron_url'        => $cron_url,
            'cron_command'    => $cron_command,
            'testing'         => $this->cronExists($this->id, 'test'),
            'crons'           => $crons,
            'crons_url'       => $crons_url,
            'base_url'       => $base_url,
            'api_key'       => $api_key,
            'txtStatus'       => $txtStatus,
        ));
        // Display Form
        return parent::displayForm($token);
    }

    protected function getConfigFormValues()
    {
        return array(
            'DROPSHIPPING_URL'                  => Configuration::get('DROPSHIPPING_URL', true),
            'DROPSHIPPING_KEY'                  => Configuration::get('DROPSHIPPING_KEY', null),
            'DROPSHIPPING_PASSWORD'             => Configuration::get('DROPSHIPPING_PASSWORD', null),
            'DROPSHIPPING_METHOD'               => Configuration::get('DROPSHIPPING_METHOD', null),
            'DROPSHIPPING_CATALOG'              => Configuration::get('DROPSHIPPING_CATALOG', null),
            'DROPSHIPPING_SIZE'                 => Configuration::get('DROPSHIPPING_SIZE', null),
            'DROPSHIPPING_GENDER'               => Configuration::get('DROPSHIPPING_GENDER', null),
            'DROPSHIPPING_COLOR'                => Configuration::get('DROPSHIPPING_COLOR', null),
            'DROPSHIPPING_SEASON'               => Configuration::get('DROPSHIPPING_SEASON', null),
            'DROPSHIPPING_CATEGORY_STRUCTURE'   => Configuration::get('DROPSHIPPING_CATEGORY_STRUCTURE', null),
            'DROPSHIPPING_IMPORT_IMAGE'         => Configuration::get('DROPSHIPPING_IMPORT_IMAGE', null),
            'DROPSHIPPING_IMPORT_RETAIL'        => Configuration::get('DROPSHIPPING_IMPORT_RETAIL', null),
            'DROPSHIPPING_LIMIT_COUNT'          => Configuration::get('DROPSHIPPING_LIMIT_COUNT', null),
        );
    }

    public function postProcess($token)
    {
        if ($id_cron = Tools::getValue('delete')) {
            if ($this->cron->deleteByID($id_cron)) {
                Tools::redirectAdmin(AdminController::$currentIndex.'&configure='.$this->name.'&conf=1&token='.$token);
            }
        }

        if ($id_cron_url = Tools::getValue('delete_url')) {
            if ($this->cron->deleteURLByID($id_cron_url)) {
                Tools::redirectAdmin(AdminController::$currentIndex.'&configure='.$this->name.'&conf=1&token='.$token);
            }
        }

        if (Tools::isSubmit('submitAddCron')) {
            if ($this->cron->addURL(Tools::getValue('cron_url'), Tools::getValue('cron_mhdmd'))) {
                Tools::redirectAdmin(AdminController::$currentIndex.'&configure='.$this->name.'&conf=3&token='.$token);
            }
        }

        if (Tools::isSubmit('saveConfigurations')) {
            $configurations = Tools::getValue('config');
            foreach (array_keys($configurations) as $key) {
                Configuration::updateValue($key, $configurations[$key]);
            }
        }

        if (Tools::isSubmit('saveCatalogs')) {
            $configurations = Tools::getValue('catalogs');
            foreach (array_keys($configurations) as $key) {
                Configuration::updateValue($key, $configurations[$key]);
            }
        }

        if (Tools::isSubmit('saveSettings')) {
            $cron_test = $this->cron->exists($this->id, 'test');
            $setting = Tools::getValue('setting');
            if ($cron_test != $setting['_test']) {
                if ($setting['_test']) {
                    $this->addTest();
                } else {
                    $this->deleteTest();
                }
            }
        }

        /*$cron_test = $this->cron->exists($this->id, 'test');
        if ($cron_test) {
            if ($this->config->_lasttest) {
                $text = $this->l('Last test have been successfully executed on', 'main').' ';
                $text .= Tools::DisplayDate(
                    date('Y-m-d H:i:s', $this->config->_lasttest),
                    $this->context->cookie->id_lang,
                    true
                );
                $this->confirmations[] = $text;
            } else {
                $this->errors[] = $this->l('Test have not been executed yet.', 'main');
            }
        }*/

        if (!$this->config->_token) {
            $this->config->_token = Tools::passwdGen();
        }

        return parent::postProcess($token);
    }

    /**
     * add a false picture to run task in background
    **/
    public function hookFooter($params)
    {
        if (Configuration::get('cron_method') == 'traffic' &&
            (!Configuration::get('cron_lasttime') ||
             (Configuration::get('cron_lasttime') + 60 <= time())
            )) {
                return '<img
                    src="'.$this->getCronURL().'&time='.time().'"
                    alt=""
                    width="0"
                    height="0"
                    style="border:none;margin:0; padding:0"
                />';
        }
    }

    /**
     * add a cron job
     *
     * usage Module::getInstanceByName('cron')->addCron($this->id, 'myMethod', '5 * * * *');
     *
     * $mhdmd details :
     * .---------------- minute (0 - 59)
     * |  .------------- hour (0 - 23)
     * |  |  .---------- day of month (1 - 31)
     * |  |  |  .------- month (1 - 12)
     * |  |  |  |  .---- day of week (0 - 6) (Sunday=0 )
     * |  |  |  |  |
     * *  *  *  *  *
     *
     * @param int $id_module Module ID
     * @param string $method method of the module to call
     * @param string $mhdmd when call this cron
     * @return boolean
    **/
    public function addCron($id_module, $method, $mhdmd = '0 * * * *')
    {
        return $this->cron->add($id_module, $method, $mhdmd);
    }

    /**
     * delete a cron job
     *
     * @param int $id_module Module ID
     * @param string $method method of the module to call
     * @return boolean
    **/
    public function deleteCron($id_module, $method)
    {
        return $this->cron->delete($id_module, $method);
    }

    /**
     * delete a cron job
     *
     * @param int $id_cron cron job ID
     * @return boolean
    **/
    public function deleteCronByID($id_cron)
    {
        return $this->cron->deleteByID($id_cron);
    }

    /**
     * test if a cron job exists
     *
     * @param int $id_module Module ID
     * @param string $method method of the module to call
     * @return boolean
    **/
    public function cronExists($id_module, $method)
    {
        return $this->cron->exists($id_module, $method);
    }

    /**
     * add a cron job
     *
     * usage Module::getInstanceByName('cron')->addURLCron($url', '5 * * * *');
     *
     * $mhdmd details :
     * .---------------- minute (0 - 59)
     * |  .------------- hour (0 - 23)
     * |  |  .---------- day of month (1 - 31)
     * |  |  |  .------- month (1 - 12)
     * |  |  |  |  .---- day of week (0 - 6) (Sunday=0 )
     * |  |  |  |  |
     * *  *  *  *  *
     *
     * @param string $url url to visit
     * @param string $mhdmd when call this cron
     * @return boolean
    **/
    public function addCronURL($url, $mhdmd = '0 * * * *')
    {
        return $this->cron->addURL($url, $mhdmd);
    }

    /**
     * delete a cron job
     *
     * @param int $id_module Module ID
     * @param string $method method of the module to call
     * @return boolean
    **/
    public function deleteCronURL($url)
    {
        return $this->cron->deleteURL($url);
    }

    /**
     * delete a cron job
     *
     * @param int $id_cron cron job ID
     * @return boolean
    **/
    public function deleteURLByID($id_cron_url)
    {
        return $this->cron->deleteURLByID($id_cron_url);
    }

    /**
     * test if a cron job exists
     *
     * @param int $id_module Module ID
     * @param string $method method of the module to call
     * @return boolean
    **/
    public function cronURLExists($url)
    {
        return $this->cron->URLExists($url);
    }

    /**
     * execute cron jobs
     * invalide job will be deleted
     *
     * @return void
    **/
    public function runJobs()
    {
        if ($this->active && ($this->config->_lasttime + 60 <= time())) {
            $this->config->_lasttime = time();
            return $this->cron->runJobs();
        }
    }

    /**
     * tests method
     * to show how to add/delete jobs
    **/
    public function addTest()
    {
        $this->config->_lasttest = 0;
        return $this->addCron(
            $this->id,
            'test',
            '* * * * *'
        );
    }

    public function deleteTest()
    {
        $this->config->_lasttest = 0;
        return $this->deleteCron(
            $this->id,
            'test'
        );
    }

    public function test()
    {
        // store the last time the test was executed
        $this->config->_lasttest = time();
        return '['.date('r')."]\tTest Ok".PHP_EOL;
    }

    public function getCronURL()
    {
        if (version_compare(_PS_VERSION_, '1.5.0.0', '>=')) {
            $result = $this->context->link->getModuleLink(
                $this->name,
                'cron',
                array('token' => $this->config->_token)
            );
        } else {
            $result = $this->samdha_tools->getHttpHost(true).$this->_path.'cron.php?token='.$this->config->_token;
        }
        return $result;
    }

    public function getCronCommand()
    {
        if (version_compare(_PS_VERSION_, '1.5.0.0', '>=')) {
            $result = '"'._PS_ROOT_DIR_.DIRECTORY_SEPARATOR.'index.php"';
            $result .= ' "fc=module&module='.$this->name.'&controller=cron&token='.$this->config->_token.'"';
        } else {
            $result = '"'._PS_MODULE_DIR_.$this->name.DIRECTORY_SEPARATOR.'cron.php" ';
            $result .= ' "token='.$this->config->_token.'"';
        }
        return $result;
    }
}
