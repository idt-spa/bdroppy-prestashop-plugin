<?php
/**
 * 2007-2020 PrestaShop
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
 *  @author    PrestaShop SA <contact@prestashop.com>
 *  @copyright 2007-2020 PrestaShop SA
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *  International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

include_once dirname(__FILE__) . '/classes/ConfigKeys.php';
include_once dirname(__FILE__) . '/classes/ImportTools.php';
include_once dirname(__FILE__) . '/classes/RemoteProduct.php';
include_once dirname(__FILE__) . '/classes/RemoteCategory.php';
include_once dirname(__FILE__) . '/classes/RewixApi.php';

class Dropshipping extends Module
{
    protected $config_form = false;
    private $errors = null;
    private $logger;
    private $orderLogger;
    public $tab = null;

    public function __construct()
    {
        $this->name = 'dropshipping';
        $this->tab = 'administration';
        $this->version = '1.0.1';
        $this->author = 'Hamid Isaac';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Dropshipping');
        $this->description = $this->l('Dropshipping of Brandsdistributions');

        $this->confirmUninstall = $this->l('Are you sure you want to delete the Dropshipping module?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->errors = array();
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function installTabs() {
        $languages = Language::getLanguages();
        // Install Tabs:
        // parent tab
        $parentTab = new Tab();
        foreach ($languages as $lang) {
            $parentTab->name[$lang['id_lang']] = $this->l('Dropshipping');
        }
        $parentTab->class_name = 'AdminDropshipping';
        $parentTab->id_parent = 0; // Home tab
        $parentTab->module = $this->name;
        $parentTab->add();

        // child tab settings
        $importTab = new Tab();
        foreach ($languages as $lang) {
            $importTab->name[$lang['id_lang']] = $this->l('Settings');
        }
        $importTab->class_name = 'AdminSettingsDropshipping';
        $importTab->id_parent = $parentTab->id;
        $importTab->module = $this->name;
        $importTab->add();
    }

    public function installAttributes() {
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
    }

    public function install()
    {
        $this->installAttributes();
        $this->installTabs();

        //Init default value:
        Configuration::updateValue('DROPSHIPPING_API_URL', '');
        Configuration::updateValue('DROPSHIPPING_API_KEY', '');
        Configuration::updateValue('DROPSHIPPING_API_PASSWORD', '');
        Configuration::updateValue('DROPSHIPPING_CATALOG', '');
        Configuration::updateValue('DROPSHIPPING_SIZE', '');
        Configuration::updateValue('DROPSHIPPING_GENDER', '');
        Configuration::updateValue('DROPSHIPPING_COLOR', '');
        Configuration::updateValue('DROPSHIPPING_SEASON', '');
        Configuration::updateValue('DROPSHIPPING_CATEGORY_STRUCTURE', '');
        Configuration::updateValue('DROPSHIPPING_IMPORT_IMAGE', '');
        Configuration::updateValue('DROPSHIPPING_LIMIT_COUNT', '');

        include(dirname(__FILE__).'/sql/install.php');

        if (!parent::install() ||
            !$this->registerHook('header') &&
            !$this->registerHook('displayBackOfficeHeader') ||
            !$this->registerHook('actionProductDelete') ||
            !$this->registerHook('actionCategoryDelete') ||
            !$this->registerHook('actionPaymentConfirmation') ||
            !$this->registerHook('actionObjectOrderAddBefore')
        ) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        Configuration::deleteByName('DROPSHIPPING_API_URL');
        Configuration::deleteByName('DROPSHIPPING_API_KEY');
        Configuration::deleteByName('DROPSHIPPING_API_PASSWORD');
        Configuration::deleteByName('DROPSHIPPING_CATALOG');
        Configuration::deleteByName('DROPSHIPPING_SIZE');
        Configuration::deleteByName('DROPSHIPPING_GENDER');
        Configuration::deleteByName('DROPSHIPPING_COLOR');
        Configuration::deleteByName('DROPSHIPPING_SEASON');
        Configuration::deleteByName('DROPSHIPPING_CATEGORY_STRUCTURE');
        Configuration::deleteByName('DROPSHIPPING_IMPORT_IMAGE');
        Configuration::deleteByName('DROPSHIPPING_LIMIT_COUNT');

        // Uninstall Tabs
        $moduleTabs = Tab::getCollectionFromModule($this->name);
        if (!empty($moduleTabs)) {
            foreach ($moduleTabs as $moduleTab) {
                $moduleTab->delete();
            }
        }

        include(dirname(__FILE__).'/sql/uninstall.php');

        if (!parent::uninstall()) {
            return false;
        }

        return true;
    }

    public function getCronURL()
    {
        if (version_compare(_PS_VERSION_, '1.5.0.0', '>=')) {
            $result = $this->context->link->getModuleLink(
                $this->name,
                'dropshipping',
                array('token' => $this->config->_token)
            );
        } else {
            $result = $this->samdha_tools->getHttpHost(true).$this->_path.'cron.php?token='.$this->config->_token;
        }
        return $result;
    }

    private function getCatalogs() {
        $catalogs = [];
        $catalogs[0] = 'No Catalog';
        $rewixApi = new DropshippingRewixApi();
        $res = $rewixApi->getUserCatalogs();
        if(is_array($res)) {
            foreach ($res as $r) {
                $r = $rewixApi->getCatalogById2($r->_id);
                $catalogs[$r->_id] = isset($r->name) ? $r->name . " ( $r->currency ) ( " . count($r->ids) . " products )" : null;
            }
            return $catalogs;
        } else {
            return $res;
        }
    }

    public function getContent()
    {
        $output = '';
        $saved = false;

        // check if a FORM was submitted using the 'Save Config' button
        if (Tools::isSubmit('submitApiConfig')) {
            $apiUrl = (string)Tools::getValue('dropshipping_api_url');
            $apiKey = (string)Tools::getValue('dropshipping_api_key');
            $apiPassword = (string)Tools::getValue('dropshipping_api_password');

            Configuration::updateValue('DROPSHIPPING_API_URL', $apiUrl);
            Configuration::updateValue('DROPSHIPPING_API_KEY', $apiKey);
            if ($apiPassword) {
                Configuration::updateValue('DROPSHIPPING_API_PASSWORD', $apiPassword);
            }

            $saved = true;
        } elseif (Tools::isSubmit('submitCatalogConfig')) {
            $dropshipping_catalog = (string)Tools::getValue('dropshipping_catalog');
            Configuration::updateValue('DROPSHIPPING_CATALOG', $dropshipping_catalog);

            $dropshipping_size = (string)Tools::getValue('dropshipping_size');
            Configuration::updateValue('DROPSHIPPING_SIZE', $dropshipping_size);

            $dropshipping_gender = (string)Tools::getValue('dropshipping_gender');
            Configuration::updateValue('DROPSHIPPING_GENDER', $dropshipping_gender);

            $dropshipping_color = (string)Tools::getValue('dropshipping_color');
            Configuration::updateValue('DROPSHIPPING_COLOR', $dropshipping_color);

            $dropshipping_season = (string)Tools::getValue('dropshipping_season');
            Configuration::updateValue('DROPSHIPPING_SEASON', $dropshipping_season);

            $dropshipping_category_structure = (string)Tools::getValue('dropshipping_category_structure');
            Configuration::updateValue('DROPSHIPPING_CATEGORY_STRUCTURE', $dropshipping_category_structure);

            $dropshipping_import_image = (string)Tools::getValue('dropshipping_import_image');
            Configuration::updateValue('DROPSHIPPING_IMPORT_IMAGE', $dropshipping_import_image);

            $dropshipping_limit_count = (string)Tools::getValue('dropshipping_limit_count');
            Configuration::updateValue('DROPSHIPPING_LIMIT_COUNT', $dropshipping_limit_count);

            $saved = true;
        }
        $errors = "";
        $confirmations = "";
        // Control on variable putted in!
        if (isset($this->errors) && count($this->errors)) {
            $errors = $this->displayError(implode('<br />', $this->errors));
        } else {
            if ($saved) {
                $confirmations = $this->displayConfirmation($this->l('Settings updated'));
            }
        }

        $res = AttributeGroup::getAttributesGroups($this->context->language->id);
        $attributes = array(
            '0' => $this->l('Select', 'main'),
        );
        foreach ($res as $attribute) {
            $attributes[$attribute['id_attribute_group']] = $attribute['public_name'];
        }

        //return $output . $this->displayForm() . $this->displayPriceForm();
        $catalogs = $this->getCatalogs();

        $shopDomainSsl = Tools::getShopDomainSsl(true, true);
        $stripeBOCssUrl = $shopDomainSsl.__PS_BASE_URI__.'modules/'.$this->name.'/views/css/dropshipping.css';
        $base_url = "Unkown";
        $api_key = "Unkown";
        $base_url = Configuration::get('DROPSHIPPING_API_URL');
        $api_key = Configuration::get('DROPSHIPPING_API_KEY');

        $txtStatus = '<span style="color: red;">Error</span>';
        if(is_array($catalogs)) {
            if(count($catalogs) > 1) {
                $txtStatus = '<span style="color: green;">Ok</span>';
            }
        } else {
            $txtStatus = '<span style="color: red;">Error Code : ' . $catalogs . '</span>';
        }
        $urls = array(
            'https://dev.bdroppy.com' => $this->l('Sandbox mode', 'main'),
            'https://prod.bdroppy.com' => $this->l('Live mode', 'main')
        );
        $import_image = array(
            '0' => $this->l('No import', 'main'),
            '1' => $this->l('1 Picture', 'main'),
            '2' => $this->l('2 Picture', 'main'),
            '3' => $this->l('3 Picture', 'main'),
            '4' => $this->l('4 Picture', 'main'),
            'all' => $this->l('All Pictures', 'main'),
        );
        $category_structure = array(
            '1' => $this->l('Category > Subcategory', 'main'),
            '2' => $this->l('Gender > Category > Subcategory', 'main'),
        );

        $tplVars = array(
            'urls'                  => $urls,
            'erros'                 => $errors,
            'confirmations'         => $confirmations,
            'module_path'           => '/modules/dropshipping/',
            'base_url'              => $base_url,
            'api_key'               => $api_key,
            'cron_url'              => $this->getCronURL(),
            'catalogs'              => $catalogs,
            'attributes'            => $attributes,
            'import_image'          => $import_image,
            'category_structure'    => $category_structure,
            'stripeBOCssUrl'        => $stripeBOCssUrl,
            'base_url'              => $base_url,
            'api_key'               => $api_key,
            'txtStatus'             => $txtStatus
        );
        $this->context->smarty->assign($tplVars);
        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output;
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'DROPSHIPPING_LIVE_MODE' => Configuration::get('DROPSHIPPING_LIVE_MODE', true),
            'DROPSHIPPING_ACCOUNT_EMAIL' => Configuration::get('DROPSHIPPING_ACCOUNT_EMAIL', 'contact@prestashop.com'),
            'DROPSHIPPING_ACCOUNT_PASSWORD' => Configuration::get('DROPSHIPPING_ACCOUNT_PASSWORD', null),
        );
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    public function hookActionObjectOrderAddBefore($params)
    {
        if (!isset($params)) {
            return;
        }

        $rewixApi = new DropshippingRewixApi();

        try {
            return $rewixApi->validateOrder($params['cart']);
        } catch (Exception $e) {
            //$this->orderLogger->logDebug($e->getMessage());
            $this->context->controller->errors[] = $this->l($e->getMessage());
            $this->context->controller->redirectWithNotifications("index.php");
        }
    }

    /**
     * Send order to Rewix
     */
    public function hookActionPaymentConfirmation($params)
    {
        $order = new Order((int)$params['id_order']);
        $rewixApi = new DropshippingRewixApi();

        try {
            return $rewixApi->sendDropshippingOrder($order);
        } catch (Exception $e) {
            //$this->orderLogger->logDebug($e->getMessage());
            $this->context->controller->errors[] = $this->l($e->getMessage());
            $this->context->controller->redirectWithNotifications("index.php");
        }
    }

    public function hookActionProductDelete($params)
    {
        $id = $params['id_product'];
        $rewixId = DropshippingRemoteProduct::getRewixIdByPsId($id);
        DropshippingRemoteCombination::deleteByRewixId($rewixId);
        DropshippingRemoteProduct::deleteByPsId($id);
    }

    public function hookActionCategoryDelete($params)
    {
        $category = $params['category'];
        $subcategories = $params['deleted_children'];

        DropshippingRemoteCategory::deleteByPsId($category->id);
        foreach ($subcategories as $cat) {
            DropshippingRemoteCategory::deleteByPsId($cat->id);
        }
    }

}
