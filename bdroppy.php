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

class Bdroppy extends Module
{
    protected $config_form = false;
    private $errors = null;
    public $current_tab = null;
    public $categories = [];

    public function __construct()
    {
        $this->module_key = 'cf377ace94aa4ea3049a648914110eb6';
        $this->name = 'bdroppy';
        $this->tab = 'administration';
        $this->version = '2.2.0';
        $this->author = 'Bdroppy';
        $this->need_instance = 1;

        /**
         * Set $this->bootstrap to true if your module is compliant with bootstrap (PrestaShop 1.6)
         */
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Bdroppy');
        $this->description = $this->l('Bdroppy of Brandsdistribution');

        $this->confirmUninstall = $this->l('Are you sure you want to delete the Bdroppy module?');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
        $this->errors = array();
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function installTabs()
    {
        try {
            $languages = Language::getLanguages();
            // Install Tabs:
            // parent tab
            $parentTab = new Tab();
            foreach ($languages as $lang) {
                $parentTab->name[$lang['id_lang']] = "Bdroppy";
            }
            $parentTab->class_name = 'AdminBdroppy';
            $parentTab->id_parent = 0; // Home tab
            $parentTab->module = $this->name;
            $parentTab->add();

            // child tab settings
            $importTab = new Tab();
            foreach ($languages as $lang) {
                $importTab->name[$lang['id_lang']] = "Settings";
            }
            $importTab->class_name = 'AdminSettingsBdroppy';
            $importTab->id_parent = $parentTab->id;
            $importTab->module = $this->name;
            $importTab->add();
        } catch (Exception $exception) {
        }
    }

    public function createFeature($name)
    {
        $default_language = Language::getLanguage(Configuration::get('PS_LANG_DEFAULT'));

        $query = new DbQuery();
        $query->select('name');
        $query->from('feature_lang');
        $query->where('`id_lang` = ' . (int) $default_language['id_lang']);
        $query->where('`name` = "' . pSQL($this->{'get'.$name.'NameLanguage'}($default_language['iso_code'])).'"');

        if (Db::getInstance()->getValue($query) === false) {
            $languages = Language::getLanguages();

            $feature = new Feature();
            foreach ($languages as $language) {
                $feature->name[$language['id_lang']] = $this->{'get'.$name.'NameLanguage'}($language['iso_code']);
            }
            $feature->add();
        }
    }

    public function installFeatures()
    {
        try {
            $this->createFeature('Size');
            $this->createFeature('Gender');
            $this->createFeature('Color');
            $this->createFeature('Season');
        } catch (Exception $exception) {
        }
    }

    public function getSizeNameLanguage($lang)
    {
        $lngSize = [];
        $lngSize['it'] = 'Taglia';
        $lngSize['en'] = 'Size';
        $lngSize['gb'] = 'Size';
        $lngSize['fr'] = 'Taille';
        $lngSize['pl'] = 'Rozmiar';
        $lngSize['es'] = 'Talla';
        $lngSize['de'] = 'Größe';
        $lngSize['ru'] = 'Размер';
        $lngSize['nl'] = 'Grootte';
        $lngSize['ro'] = 'Mărimea';
        $lngSize['et'] = 'Suurus';
        $lngSize['hu'] = 'Méret';
        $lngSize['sv'] = 'Storlek';
        $lngSize['sk'] = 'veľkosť';
        $lngSize['cs'] = 'Velikost';
        $lngSize['pt'] = 'Tamanho';
        $lngSize['fi'] = 'Koko';
        $lngSize['bg'] = 'размер';
        $lngSize['da'] = 'Størrelse';
        $lngSize['lt'] = 'Dydis';

        $lang = Tools::strtolower($lang);
        return isset($lngSize[$lang])? $lngSize[$lang] : $lngSize['en'];
    }

    public function getGenderNameLanguage($lang)
    {
        $lngGender = [];
        $lngGender['it'] = 'Genere';
        $lngGender['en'] = 'Gender';
        $lngGender['gb'] = 'Gender';
        $lngGender['fr'] = 'Le sexe';
        $lngGender['pl'] = 'Płeć';
        $lngGender['es'] = 'Género';
        $lngGender['de'] = 'Geschlecht';
        $lngGender['ru'] = 'Пол';
        $lngGender['nl'] = 'Geslacht';
        $lngGender['ro'] = 'Sex';
        $lngGender['et'] = 'Sugu';
        $lngGender['hu'] = 'Nem';
        $lngGender['sv'] = 'Kön';
        $lngGender['sk'] = 'Rod';
        $lngGender['cs'] = 'Rod';
        $lngGender['pt'] = 'Gênero';
        $lngGender['fi'] = 'sukupuoli';
        $lngGender['bg'] = 'пол';
        $lngGender['da'] = 'Køn';
        $lngGender['lt'] = 'Lytis';

        $lang = Tools::strtolower($lang);
        return isset($lngGender[$lang])? $lngGender[$lang] : $lngGender['en'];
    }

    public function getColorNameLanguage($lang)
    {
        $lngColor = [];
        $lngColor['it'] = 'Colore';
        $lngColor['en'] = 'Color';
        $lngColor['gb'] = 'Color';
        $lngColor['fr'] = 'Couleur';
        $lngColor['pl'] = 'Kolor';
        $lngColor['es'] = 'Color';
        $lngColor['de'] = 'Farbe';
        $lngColor['ru'] = 'цвет';
        $lngColor['nl'] = 'Kleur';
        $lngColor['ro'] = 'Culoare';
        $lngColor['et'] = 'Värv';
        $lngColor['hu'] = 'Szín';
        $lngColor['sv'] = 'Färg';
        $lngColor['sk'] = 'Farba';
        $lngColor['cs'] = 'Barva';
        $lngColor['pt'] = 'Cor';
        $lngColor['fi'] = 'Väri';
        $lngColor['bg'] = 'цвят';
        $lngColor['da'] = 'Farve';
        $lngColor['lt'] = 'Spalva';

        $lang = Tools::strtolower($lang);
        return isset($lngColor[$lang])? $lngColor[$lang] : $lngColor['en'];
    }

    public function getSeasonNameLanguage($lang)
    {
        $lngSeason = [];
        $lngSeason['it'] = 'Stagione';
        $lngSeason['en'] = 'Season';
        $lngSeason['gb'] = 'Season';
        $lngSeason['fr'] = 'Saison';
        $lngSeason['pl'] = 'Pora roku';
        $lngSeason['es'] = 'Temporada';
        $lngSeason['de'] = 'Jahreszeit';
        $lngSeason['ru'] = 'Время года';
        $lngSeason['nl'] = 'Seizoen';
        $lngSeason['ro'] = 'Sezon';
        $lngSeason['et'] = 'Hooaeg';
        $lngSeason['hu'] = 'Évszak';
        $lngSeason['sv'] = 'Säsong';
        $lngSeason['sk'] = 'Sezóna';
        $lngSeason['cs'] = 'Sezóna';
        $lngSeason['pt'] = 'Estação';
        $lngSeason['fi'] = 'Kausi';
        $lngSeason['bg'] = 'сезон';
        $lngSeason['da'] = 'Sæson';
        $lngSeason['lt'] = 'Sezonas';

        $lang = Tools::strtolower($lang);
        return isset($lngSeason[$lang])? $lngSeason[$lang] : $lngSeason['en'];
    }

    public function createAttribute($name)
    {
        $default_language = Language::getLanguage(Configuration::get('PS_LANG_DEFAULT'));

        $query = new DbQuery();
        $query->select('name');
        $query->from('attribute_group_lang');
        $query->where('`id_lang` = ' . (int) $default_language['id_lang']);
        $query->where('`name` = "' . pSQL($this->{'get'.$name.'NameLanguage'}($default_language['iso_code'])).'"');

        if (Db::getInstance()->getValue($query) === false) {
            $languages = Language::getLanguages();
            $newGroup = new AttributeGroup();
            foreach ($languages as $lang) {
                $newGroup->name[$lang['id_lang']] = $this->{'get'.$name.'NameLanguage'}($lang['iso_code']);
                $newGroup->public_name[$lang['id_lang']] = $this->{'get'.$name.'NameLanguage'}($lang['iso_code']);
            }
            $newGroup->group_type = 'select';
            $newGroup->save();
        }
    }

    public function installAttributes()
    {
        try {
            $this->createAttribute('Size');
            $this->createAttribute('Gender');
            $this->createAttribute('Color');
            $this->createAttribute('Season');
        } catch (Exception $exception) {
        }
    }

    public function install()
    {
        try {
            $this->installAttributes();
            $this->installFeatures();
            $this->installTabs();

            //Init default value:

            include(dirname(__FILE__) . '/sql/install.php');

            if (!parent::install() ||
                !$this->registerHook('header') &&
                !$this->registerHook('backOfficeHeader') ||
                !$this->registerHook('displayBackOfficeHeader') ||
                !$this->registerHook('actionProductDelete') ||
                !$this->registerHook('actionCategoryDelete') ||
                !$this->registerHook('actionPaymentConfirmation') ||
                !$this->registerHook('actionAdminProductsListingFieldsModifier') ||
                !$this->registerHook('actionObjectOrderAddBefore')
            ) {
                return false;
            }
        } catch (Exception $exception) {
        }
        return true;
    }

    public function uninstall()
    {
        try {
            // Uninstall Tabs
            $moduleTabs = Tab::getCollectionFromModule($this->name);
            if (!empty($moduleTabs)) {
                foreach ($moduleTabs as $moduleTab) {
                    $moduleTab->delete();
                }
            }

            //include(dirname(__FILE__).'/sql/uninstall.php');

            if (!parent::uninstall()) {
                return false;
            }
        } catch (Exception $exception) {
        }
        return true;
    }

    public function getCronURL()
    {
        return _PS_BASE_URL_ . __PS_BASE_URI__ . "index.php?fc=module&module=bdroppy&controller=cron";
    }

    private function getCatalogs()
    {
        $ret = [];
        $catalogs = [];
        $catalogs[0] = $this->l('Please Select', 'main');
        $rewixApi = new BdroppyRewixApi();
        $res = $rewixApi->getUserCatalogs();
        if ($res['catalogs']) {
            $catalogs[-1] = 'No Catalog';
        }
        foreach ($res['catalogs'] as $r) {
            $catalogs[$r->_id] = "";
            if (isset($r->name)) {
                $catalogs[$r->_id] = $r->name . " ( $r->currenct ) ( " . $r->count . " products )";
            } else {
                $catalogs[$r->_id] = null;
            }
        }
        $ret['http_code'] = $res['http_code'];
        $ret['catalogs'] = $catalogs;
        return $ret;
    }

    public function getCronCommand()
    {
        $result = '"' . _PS_MODULE_DIR_ . $this->name . DIRECTORY_SEPARATOR . 'cron" ';
        return $result;
    }

    public function getOrders()
    {
        $status = [
            '0' => ['value' => 'PENDING', 'desc' => 'User is managing the cart'],
            '1' => ['value' => 'MONEY WAITING', 'desc' => 'Awaiting for payment gateway response'],
            '2' => ['value' => 'TO DISPATCH	', 'desc' => 'Ready to be dispatched'],
            '3' => ['value' => 'DISPATCHED', 'desc' => 'Shipment has been picked up by carrier'],
            '5' => ['value' => 'BOOKED', 'desc' => 'Order created by API Acquire or booked by bank transfer'],
            '2000' => ['value' => 'CANCELLED', 'desc' => 'Order cancelled'],
            '2002' => ['value' => 'VERIFY FAILED', 'desc' => 'Payment was not accepted by payment gateway'],
            '3001' => ['value' => 'WORKING ON', 'desc' => 'Logistics office is working on the order'],
            '3002' => ['value' => 'READY', 'desc' => 'Order is ready for pick up'],
            '5003' => ['value' => 'DROPSHIPPER GROWING', 'desc' => 'Virtual order for growing cart'],
        ];
        $logs = array();
        $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'bdroppy_remoteorder;';
        if ($results = Db::getInstance()->ExecuteS($sql)) {
            foreach ($results as $row) {
                $row['status_value'] = $status[$row['status']]['value'];
                $row['status_desc'] = $status[$row['status']]['desc'];
                array_push($logs, $row);
            }
        }
        return $logs;
    }

    public function paginateUsers($users, $page = 1, $pagination = 20)
    {
        if (count($users) > $pagination) {
            $users = array_slice($users, $pagination * ($page - 1), $pagination);
        }
        return $users;
    }

    protected function renderOrdersList()
    {
        $fields_list = array(

            'id' => array(
                'title' => $this->l('ID'),
                'search' => false,
                'type' => 'text',
                'width' => 50,
            ),
            'rewix_order_id' => array(
                'title' => $this->l('Bdroppy Order ID'),
                'search' => false,
                'type' => 'text',
                'width' => 50,
            ),
            'rewix_order_key' => array(
                'title' => $this->l('Order Key'),
                'search' => false,
                'type' => 'text',
                'width' => 100,
            ),
            'ps_order_id' => array(
                'title' => $this->l('Prestashop Order ID'),
                'search' => false,
                'type' => 'text',
                'width' => 200,
            ),
            'status_value' => array(
                'title' => $this->l('Status'),
                'search' => false,
                'type' => 'text',
                'width' => 200,
            ),
            'status_desc' => array(
                'title' => $this->l('Description'),
                'search' => false,
                'type' => 'text',
                'width' => 200,
            )
        );

        $helper_list = new HelperList();
        $helper_list->module = $this;
        $helper_list->title = $this->l('Orders List');
        $helper_list->shopLinkType = '';
        $helper_list->no_link = true;
        $helper_list->show_toolbar = true;
        $helper_list->simple_header = false;
        $helper_list->identifier = 'id';
        $helper_list->table = 'merged';
        $helper_list->token = Tools::getAdminTokenLite('AdminModules');
        $helper_list->currentIndex = $this->context->link->getAdminLink('AdminModules', false) .
            '&configure=' . $this->name;
        $this->_helperlist = $helper_list;

        /* Retrieve list data */
        $users = $this->getOrders();
        $helper_list->listTotal = count($users);

        if (Tools::getValue('submitFilter' . $helper_list->table)) {
            $this->current_tab = 'orders';
        }
        /* Paginate the result */
        $page = ($page = Tools::getValue('submitFilter' . $helper_list->table)) ? $page : 1;
        $pagination = ($pagination = Tools::getValue($helper_list->table . '_pagination')) ? $pagination : 20;
        $users = $this->paginateUsers($users, $page, $pagination);

        return $helper_list->generateList($users, $fields_list);
    }

    public function getNestedCategories($parentName, $categories)
    {
        foreach ($categories as $category) {
            $this->categories[$category['id_category']] = $parentName . ' > ' . $category['name'];
            if ($category['children']) {
                if (count($category['children']) > 0) {
                    $this->getNestedCategories($parentName . ' > ' . $category['name'], $category['children']);
                }
            }
        }
    }

    public function getContent()
    {
        $output = '';
        $saved = false;
        $connectCatalog = false;
        $connectCronTxt = '';
        $cron_url = $this->getCronURL();

        // check if a FORM was submitted using the 'Save Config' button
        if (Tools::isSubmit('submitApiConfig')) {
            $apiUrl = (string)Tools::getValue('bdroppy_api_url');
            $apiKey = (string)Tools::getValue('bdroppy_api_key');
            $apiPassword = (string)Tools::getValue('bdroppy_api_password');
            //$apiToken = (string)Tools::getValue('bdroppy_token');

            Configuration::updateValue('BDROPPY_API_URL', $apiUrl);
            Configuration::updateValue('BDROPPY_API_KEY', $apiKey);
            Configuration::updateValue('BDROPPY_API_PASSWORD', $apiPassword);
            Configuration::updateValue('BDROPPY_TOKEN', '');
            if ($apiKey != Configuration::get('BDROPPY_API_KEY') ||
                $apiPassword != Configuration::get('BDROPPY_API_PASSWORD') ||
                Configuration::get('BDROPPY_TOKEN') == '') {
                Configuration::updateValue('BDROPPY_CATALOG', '');
                $rewixApi = new BdroppyRewixApi();
                $res = $rewixApi->loginUser();
                if ($res['http_code'] == 200) {
                    Configuration::updateValue('BDROPPY_TOKEN', $res['data']->token);
                }
            }
            $this->current_tab = 'configurations';
            $saved = true;
        } elseif (Tools::isSubmit('submitCatalogConfig')) {
            $bdroppy_catalog_changed = false;
            $bdroppy_catalog = (string)Tools::getValue('bdroppy_catalog');
            if (Configuration::get('BDROPPY_CATALOG') != $bdroppy_catalog) {
                $bdroppy_catalog_changed = true;
            }

            Configuration::updateValue('BDROPPY_CATALOG_CHANGED', $bdroppy_catalog_changed);

            Configuration::updateValue('BDROPPY_CATALOG', $bdroppy_catalog);

            if ($bdroppy_catalog != '0' && $bdroppy_catalog != '-1') {
                Configuration::updateValue('BDROPPY_CATALOG_BU', $bdroppy_catalog);
            }

            $bdroppy_active_product = (string)Tools::getValue('bdroppy_active_product');
            Configuration::updateValue('BDROPPY_ACTIVE_PRODUCT', $bdroppy_active_product);

            $bdroppy_custom_feature = (string)Tools::getValue('bdroppy_custom_feature');
            Configuration::updateValue('BDROPPY_CUSTOM_FEATURE', $bdroppy_custom_feature);

            $bdroppy_size = (string)Tools::getValue('bdroppy_size');
            Configuration::updateValue('BDROPPY_SIZE', $bdroppy_size);

            $bdroppy_gender = (string)Tools::getValue('bdroppy_gender');
            Configuration::updateValue('BDROPPY_GENDER', $bdroppy_gender);

            $bdroppy_color = (string)Tools::getValue('bdroppy_color');
            Configuration::updateValue('BDROPPY_COLOR', $bdroppy_color);

            $bdroppy_season = (string)Tools::getValue('bdroppy_season');
            Configuration::updateValue('BDROPPY_SEASON', $bdroppy_season);

            $bdroppy_category_root = (string)Tools::getValue('bdroppy_category_root');
            Configuration::updateValue('BDROPPY_CATEGORY_ROOT', $bdroppy_category_root);

            $bdroppy_category_structure = (string)Tools::getValue('bdroppy_category_structure');
            Configuration::updateValue('BDROPPY_CATEGORY_STRUCTURE', $bdroppy_category_structure);

            $bdroppy_import_image = (string)Tools::getValue('bdroppy_import_image');
            Configuration::updateValue('BDROPPY_IMPORT_IMAGE', $bdroppy_import_image);

            $bdroppy_reimport_image = (int)Tools::getValue('bdroppy_reimport_image');
            Configuration::updateValue('BDROPPY_REIMPORT_IMAGE', $bdroppy_reimport_image);

            $bdroppy_tax_rate = (string)Tools::getValue('bdroppy_tax_rate');
            Configuration::updateValue('BDROPPY_TAX_RATE', $bdroppy_tax_rate);

            $bdroppy_tax_rule = (string)Tools::getValue('bdroppy_tax_rule');
            Configuration::updateValue('BDROPPY_TAX_RULE', $bdroppy_tax_rule);

            $bdroppy_limit_count = (string)Tools::getValue('bdroppy_limit_count');
            Configuration::updateValue('BDROPPY_LIMIT_COUNT', $bdroppy_limit_count);

            $bdroppy_import_brand_to_title = (int)Tools::getValue('bdroppy_import_brand_to_title');
            Configuration::updateValue('BDROPPY_IMPORT_BRAND_TO_TITLE', $bdroppy_import_brand_to_title);

            $bdroppy_import_tag_to_title = (string)Tools::getValue('bdroppy_import_tag_to_title');
            Configuration::updateValue('BDROPPY_IMPORT_TAG_TO_TITLE', $bdroppy_import_tag_to_title);

            $bdroppy_auto_update_categories = (int)Tools::getValue('bdroppy_auto_update_categories');
            Configuration::updateValue('BDROPPY_AUTO_UPDATE_CATEGORIES', $bdroppy_auto_update_categories);

            $bdroppy_auto_update_prices = (int)Tools::getValue('bdroppy_auto_update_prices');
            Configuration::updateValue('BDROPPY_AUTO_UPDATE_PRICES', $bdroppy_auto_update_prices);

            $bdroppy_auto_update_name = (int)Tools::getValue('bdroppy_auto_update_name');
            Configuration::updateValue('BDROPPY_AUTO_UPDATE_NAME', $bdroppy_auto_update_name);

            $bdroppy_log = (int)Tools::getValue('bdroppy_log');
            Configuration::updateValue('BDROPPY_LOG', $bdroppy_log);

            $bdroppy_online_only = (int)Tools::getValue('bdroppy_online_only');
            Configuration::updateValue('BDROPPY_ONLINE_ONLY', $bdroppy_online_only);

            $rewixApi = new BdroppyRewixApi();
            $res = $rewixApi->connectUserCatalog();
            if ($res['http_code'] == 200) {
                Configuration::updateValue('BDROPPY_CONNECT', true);
                $connectCatalog = true;
            } else {
                Configuration::updateValue('BDROPPY_CONNECT', false);
            }
            Configuration::updateValue('BDROPPY_CRON', '');
            $cron = $rewixApi->setCronJob($cron_url);
            if ($cron['http_code'] == 200) {
                Configuration::updateValue('BDROPPY_CRON', $cron_url);
                if ($cron['data'] == 'url_already_exists') {
                    $connectCronTxt = 'Your CronJob Already Added, For Change Contact Please';
                } else {
                    $connectCronTxt = 'CronJob Added (' . $cron_url . ')';
                }
            }
            $this->current_tab = 'my_catalogs';
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
            if ($connectCatalog) {
                $confirmations .= $this->displayConfirmation($this->l('Catalog Connected'));
            }
        }
        if ($connectCronTxt != '') {
            $confirmations .= $this->displayConfirmation($this->l($connectCronTxt));
        }

        $res = AttributeGroup::getAttributesGroups($this->context->language->id);
        //$res = Feature::getFeatures($this->context->language->id);
        $attributes = array(
            '0' => $this->l('Select', 'main'),
        );
        foreach ($res as $attribute) {
            $attributes[$attribute['id_attribute_group']] = $attribute['name'];
        }

        $tax_rules = [];
        $taxes = TaxRulesGroup::getTaxRulesGroups();
        foreach ($taxes as $tax) {
            $tax_rules[$tax['id_tax_rules_group']] = $tax['name'];
        }
        $tax_rates = [];
        $rates = Tax::getTaxes(Configuration::get('PS_LANG_DEFAULT'));
        foreach ($rates as $rate) {
            $tax_rates[$rate['id_tax']] = $rate['name'];
        }
        //return $output . $this->displayForm() . $this->displayPriceForm();
        $catalogs = $this->getCatalogs();

        $stripeBOCssUrl = str_replace('http://', 'https://', _PS_BASE_URL_ . __PS_BASE_URI__) .
            'modules/bdroppy/views/css/bdroppy.css';
        $base_url = "Unkown";
        $api_key = "Unkown";
        $api_token = "Unkown";
        $base_url = Configuration::get('BDROPPY_API_URL');
        $api_key = Configuration::get('BDROPPY_API_KEY');
        $api_token = Configuration::get('BDROPPY_TOKEN');
        $bdroppy_active_product = Configuration::get('BDROPPY_ACTIVE_PRODUCT');
        $bdroppy_custom_feature = Configuration::get('BDROPPY_CUSTOM_FEATURE');
        if ($bdroppy_custom_feature == '') {
            $bdroppy_custom_feature = '0';
        }
        $bdroppy_log = Configuration::get('BDROPPY_LOG');
        if ($bdroppy_log == '') {
            $bdroppy_log = '0';
        }
        $bdroppy_online_only = Configuration::get('BDROPPY_ONLINE_ONLY');
        if ($bdroppy_online_only == '') {
            $bdroppy_online_only = '0';
        }
        $bdroppy_auto_update_name = Configuration::get('BDROPPY_AUTO_UPDATE_NAME');
        if ($bdroppy_auto_update_name == '') {
            $bdroppy_auto_update_name = '0';
        }
        $bdroppy_reimport_image = Configuration::get('BDROPPY_REIMPORT_IMAGE');
        if ($bdroppy_reimport_image == '') {
            $bdroppy_reimport_image = '0';
        }
        $bdroppy_import_brand_to_title = Configuration::get('BDROPPY_IMPORT_BRAND_TO_TITLE');
        $bdroppy_auto_update_prices = Configuration::get('BDROPPY_AUTO_UPDATE_PRICES');
        $bdroppy_auto_update_categories = Configuration::get('BDROPPY_AUTO_UPDATE_CATEGORIES');
        if ($bdroppy_auto_update_categories == '') {
            $bdroppy_auto_update_categories = false;
        }

        $txtStatus = '<span style="color: red;">Error Code : ' . $catalogs['http_code'] . '</span>';
        $flgStatus = false;
        if ($catalogs['http_code'] == 200) {
            $txtStatus = '<span style="color: green;">Ok</span>';
            $flgStatus = true;
        }
        $urls = array(
            'https://dev.bdroppy.com' => $this->l('Sandbox mode', 'main'),
            'https://prod.bdroppy.com' => $this->l('Live mode', 'main')
        );
        $bdroppy_import_tag_to_title = array(
            '0' => $this->l('No Tag', 'main'),
            'color' => $this->l('Color', 'main')
        );

        $import_image = array(
            '0' => $this->l('No import', 'main'),
            '1' => $this->l('1 Picture', 'main'),
            '2' => $this->l('2 Picture', 'main'),
            '3' => $this->l('3 Picture', 'main'),
            '4' => $this->l('4 Picture', 'main'),
            'all' => $this->l('All Pictures', 'main'),
        );
        $bdroppy_category_root = Configuration::get('BDROPPY_CATEGORY_ROOT');
        if ($bdroppy_category_root == '') {
            $bdroppy_category_root = '0';
        }

        $category_structure = array(
            '1' => $this->l('Category > Subcategory', 'main'),
            '2' => $this->l('Gender > Category > Subcategory', 'main'),
        );
        $limit_counts = array(
            '' => $this->l('Select', 'main'),
            '1' => '1',
            '5' => '5',
            '10' => '10',
            '15' => '15',
            '20' => '20',
            '25' => '25',
            '30' => '30',
            '35' => '35',
            '40' => '40',
            '45' => '45',
            '50' => '50'
        );

        $iso_lang = Language::getIsoById($this->context->cookie->id_lang);
        if (!in_array($iso_lang, array('en', 'fr', 'es', 'de', 'it', 'nl', 'pl', 'pt', 'ru'))) {
            $iso_lang = 'en';
        }
        $home_url = sprintf('https://www.brandsdistribution.com', $iso_lang, urlencode($this->name));

        $queue_queued = BdroppyRemoteProduct::getCountByStatus(BdroppyRemoteProduct::SYNC_STATUS_QUEUED);
        $queue_importing = BdroppyRemoteProduct::getCountByStatus(BdroppyRemoteProduct::SYNC_STATUS_IMPORTING);
        $queue_deleting = BdroppyRemoteProduct::getCountByStatus(BdroppyRemoteProduct::SYNC_STATUS_DELETE);
        $queue_imported = BdroppyRemoteProduct::getCountByStatus(BdroppyRemoteProduct::SYNC_STATUS_UPDATED);
        $queue_all = BdroppyRemoteProduct::getCountByStatus('');
        $renderedOrders = $this->renderOrdersList();
        if ($this->current_tab == '') {
            $this->current_tab = 'configurations';
        }
        $warnings = [];
        $successes = [];
        if ((bool)Configuration::get('PS_SHOP_ENABLE')) {
            $successes[] = "Maintenance Mode Is Off";
        } else {
            $warnings[] = "Catalog Update Don't Works In Maintenance Mode";
        }
        if ((bool)Configuration::get('PS_STOCK_MANAGEMENT')) {
            $successes[] = "Stock Management Is Enabled";
        } else {
            $warnings[] = "Stock Management Is Disabled";
        }
        if ($flgStatus) {
            $successes[] = "You Are Login To Bdroppy";
        } else {
            $warnings[] = "You Are Not Login To Bdroppy Yet";
        }
        if (Configuration::get('BDROPPY_CATALOG') != '-1' && Configuration::get('BDROPPY_CATALOG') != '') {
            $successes[] = "Catalog Selected";
        } else {
            $warnings[] = "Catalog Not Selected";
        }
        if ((bool)Configuration::get('BDROPPY_CONNECT')) {
            $successes[] = "You Are Connected To Bdroppy";
        } else {
            $warnings[] = "You Are Not Connected To Bdroppy";
        }
        if (Configuration::get('BDROPPY_CRON') != '' && Configuration::get('BDROPPY_CRON') != false) {
            $successes[] = "Cronjob Set In Bdroppy (" . Configuration::get('BDROPPY_CRON') . ")";
        } else {
            $warnings[] = "Cronjob Not Set In Bdroppy";
        }

        $li = round(abs((int)Configuration::get('BDROPPY_LAST_CRON_TIME') - time()) / 60, 0);
        $last_cron_sync = $li. " Minutes Ago";
        if ($li>1440) {
            $last_cron_sync = "Many Times Ago";
        }
        $last_cron_sync .= " (" . date('Y-m-d H:i:s', (int)Configuration::get('BDROPPY_LAST_CRON_TIME')) .")";
        if ((int)Configuration::get('BDROPPY_LAST_CRON_TIME') == 0) {
            $last_cron_sync = "Never";
        }

        $li = round(abs((int)Configuration::get('BDROPPY_LAST_IMPORT_SYNC') - time()) / 60, 0);
        $last_import_sync = $li. " Minutes Ago";
        if ($li>1440) {
            $last_import_sync = "Many Times Ago";
        }
        $last_import_sync .= " (" . date('Y-m-d H:i:s', (int)Configuration::get('BDROPPY_LAST_IMPORT_SYNC')) .")";
        if ((int)Configuration::get('BDROPPY_LAST_IMPORT_SYNC') == 0) {
            $last_import_sync = "Never";
        }

        $lu = round(abs((int)Configuration::get('BDROPPY_LAST_QUANTITIES_SYNC') - time()) / 60, 0);
        $last_update_sync = $lu. " Minutes Ago";
        if ($lu>1440) {
            $last_update_sync = "Many Times Ago";
        }
        $last_update_sync .= " (" . date('Y-m-d H:i:s', (int)Configuration::get('BDROPPY_LAST_QUANTITIES_SYNC')) .")";
        if ((int)Configuration::get('BDROPPY_LAST_QUANTITIES_SYNC') == 0) {
            $last_update_sync = "Never";
        }

        $lc = round(abs((int)Configuration::get('BDROPPY_LAST_CART_SYNC') - time()) / 60, 0);
        $last_orders_sync = $lc. " Minutes Ago";
        if ($lc>1440) {
            $last_orders_sync = "Many Times Ago";
        }
        $last_orders_sync .= " (" . date('Y-m-d H:i:s', (int)Configuration::get('BDROPPY_LAST_CART_SYNC')) .")";
        if ((int)Configuration::get('BDROPPY_LAST_CART_SYNC') == 0) {
            $last_orders_sync = "Never";
        }

        $rootCategory = Category::getRootCategory();
        $this->categories[$rootCategory->id] = $rootCategory->name;
        $cats = Category::getNestedCategories();
        $this->getNestedCategories($rootCategory->name, $cats[$rootCategory->id]['children']);

        $base_uri = '';
        if (__PS_BASE_URI__ != '/') {
            $base_uri = __PS_BASE_URI__;
        }
        $tplVars = array(
            'module_display_name' => $this->displayName,
            'module_version' => $this->version,
            'description_big_html' => '',
            'description' => '',
            'home_url' => $home_url,
            'urls' => $urls,
            'categories' => $this->categories,
            'erros' => $errors,
            'confirmations' => $confirmations,
            'module_path' => $base_uri . '/modules/bdroppy/',
            'base_url' => $base_url,
            'api_key' => $api_key,
            'ordersHtml' => $renderedOrders,
            'active_tab' => $this->current_tab,
            'api_token' => $api_token,
            'cron_url' => $cron_url,
            'catalogs' => $catalogs['catalogs'],
            'attributes' => $attributes,
            'import_image' => $import_image,
            'tax_rule' => $tax_rules,
            'tax_rate' => $tax_rates,
            'bdroppy_category_root' => $bdroppy_category_root,
            'category_structure' => $category_structure,
            'stripeBOCssUrl' => $stripeBOCssUrl,
            'base_url' => $base_url,
            'api_key' => $api_key,
            'txtStatus' => $txtStatus,
            'queue_queued' => $queue_queued,
            'queue_importing' => $queue_importing,
            'queue_imported' => $queue_imported,
            'queue_deleting' => $queue_deleting,
            'queue_all' => $queue_all,
            'limit_counts' => $limit_counts,
            'bdroppy_active_product' => $bdroppy_active_product,
            'bdroppy_custom_feature' => $bdroppy_custom_feature,
            'bdroppy_log' => $bdroppy_log,
            'bdroppy_online_only' => $bdroppy_online_only,
            'bdroppy_import_brand_to_title' => $bdroppy_import_brand_to_title,
            'bdroppy_reimport_image' => $bdroppy_reimport_image,
            'bdroppy_import_tag_to_title' => $bdroppy_import_tag_to_title,
            'bdroppy_auto_update_prices' => $bdroppy_auto_update_prices,
            'bdroppy_auto_update_categories' => $bdroppy_auto_update_categories,
            'bdroppy_auto_update_name' => $bdroppy_auto_update_name,
            'warnings' => $warnings,
            'successes' => $successes,
            'last_cron_sync' => $last_cron_sync,
            'last_import_sync' => $last_import_sync,
            'last_update_sync' => $last_update_sync,
            'last_orders_sync' => $last_orders_sync,
        );
        $this->context->smarty->assign($tplVars);
        $output = $this->context->smarty->fetch($this->local_path . 'views/templates/admin/configure.tpl');

        return $output;
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'BDROPPY_LIVE_MODE' => Configuration::get('BDROPPY_LIVE_MODE', true),
            'BDROPPY_ACCOUNT_EMAIL' => Configuration::get('BDROPPY_ACCOUNT_EMAIL', 'contact@prestashop.com'),
            'BDROPPY_ACCOUNT_PASSWORD' => Configuration::get('BDROPPY_ACCOUNT_PASSWORD', null),
        );
    }

    /**
     * Add the CSS & JavaScript files you want to be loaded in the BO.
     */
    public function hookBackOfficeHeader()
    {

        if (Tools::getValue('configure') == $this->name) {
            $link = new Link;
            $ajax_link = $link->getModuleLink('bdroppy', 'category');
            Media::addJsDef(array(
                "category_url" => $ajax_link
            ));

            $this->context->controller->addJquery();

            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path . '/views/js/front.js');
        $this->context->controller->addCSS($this->_path . '/views/css/front.css');
    }

    public function hookActionObjectOrderAddBefore($params)
    {
        if (!isset($params)) {
            return;
        }

        $rewixApi = new BdroppyRewixApi();

        try {
            return $rewixApi->validateOrder($params['cart']);
        } catch (Exception $e) {
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
        $rewixApi = new BdroppyRewixApi();

        try {
            $r = $rewixApi->sendBdroppyOrder($order);
            if (isset($r['module_error'])) {
                $this->errors[] = Tools::displayError($r['message']);
                $this->context->controller->errors[] = Tools::displayError($r['message']);
                return false;
            }
            return true;
        } catch (Exception $e) {
            $this->context->controller->errors[] = $this->l($e->getMessage());
            $this->context->controller->redirectWithNotifications("index.php");
        }
    }

    public function hookActionProductDelete($params)
    {
        $id = $params['id_product'];
        $rewixId = BdroppyRemoteProduct::getRewixIdByPsId($id);
        BdroppyRemoteCombination::deleteByRewixId($rewixId);
        BdroppyRemoteProduct::deleteByPsId($id);
    }

    public function hookActionCategoryDelete($params)
    {
        $category = $params['category'];
        $subcategories = $params['deleted_children'];

        BdroppyRemoteCategory::deleteByPsId($category->id);
        foreach ($subcategories as $cat) {
            BdroppyRemoteCategory::deleteByPsId($cat->id);
        }
    }

    public function hookActionAdminProductsListingFieldsModifier($list)
    {
        if (isset($list['sql_select'])) {
            $list['sql_select']['unity'] = array(
                "table"=>"p",
                "field"=>"unity",
                "filtering"=>" %s "
            );
            $list['sql_select']['rewix_product_id'] = array(
                "table"=>"br",
                "field"=>"rewix_product_id",
                "filtering"=>" %s "
            );
            $list['sql_select']['rewix_catalog_id'] = array(
                "table"=>"br",
                "field"=>"rewix_catalog_id",
                "filtering"=>" %s "
            );
        }
        if (isset($list['sql_table'])) {
            $list['sql_table']['br'] = array(
                "table"=>"bdroppy_remoteproduct",
                "join"=>"LEFT JOIN",
                "on"=>"br.`ps_product_id` = p.`id_product`"
            );
        }
    }
}
