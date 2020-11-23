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

class BdroppyCronModuleFrontController extends ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();
        $this->context = Context::getContext();
        $this->ssl = true;
    }

    public function getLanguages()
    {
        return Language::getLanguages();
    }

    public function initContent()
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);
        if (!defined('_PS_ROOT_DIR_')) {
            define('_PS_ROOT_DIR_', (dirname(dirname(dirname(__FILE__)))));
        }

        include_once _PS_ROOT_DIR_ . '/config/config.inc.php';
//include_once _PS_ROOT_DIR_ . '/init.php';
        include_once dirname(__FILE__) . '/../../classes/ImportTools.php';
        include_once dirname(__FILE__) . '/../../classes/RewixApi.php';

        $importFlag = true;
        if (Tools::getIsset('no_import')) {
            if (Tools::getValue('no_import') == '1') {
                $importFlag = false;
            }
        }
        if ($importFlag) {
            $this->importProducts();
        }

        $this->syncOrders();
        Configuration::updateValue('BDROPPY_LAST_CRON_TIME', time());

        $this->assignTpl();
    }

    private static function fitReference($ean, $id)
    {
        $ean = (string)$ean;
        $id = (string)$id;
        if (Tools::strlen($ean) > 32) {
            $ean = Tools::substr($ean, 0, 32 - Tools::strlen($id));
            $ean .= $id;
        }
        return $ean;
    }

    public static function importProducts()
    {
        try {
            header('Access-Control-Allow-Origin: *');
            @ini_set('max_execution_time', 100000);
            $updateFlag = false;

            $langs = [];
            $langs['en'] = 'en_US';
            $langs['gb'] = 'en_US';
            $langs['it'] = 'it_IT';
            $langs['fr'] = 'fr_FR';
            $langs['pl'] = 'pl_PL';
            $langs['es'] = 'es_ES';
            $langs['de'] = 'de_DE';
            $langs['ru'] = 'ru_RU';
            $langs['nl'] = 'nl_NL';
            $langs['ro'] = 'ro_RO';
            $langs['et'] = 'et_EE';
            $langs['hu'] = 'hu_HU';
            $langs['sv'] = 'sv_SE';
            $langs['sk'] = 'sk_SK';
            $langs['cs'] = 'cs_CZ';
            $langs['pt'] = 'pt_PT';
            $langs['fi'] = 'fi_FI';
            $langs['bg'] = 'bg_BG';
            $langs['da'] = 'da_DK';
            $langs['lt'] = 'lt_LT';
            $langs['el'] = 'el_GR';

            $default_language = Language::getLanguage(Configuration::get('PS_LANG_DEFAULT'));
            $default_lang = $langs[$default_language['iso_code']];

            $configurations = array(
                'BDROPPY_API_URL' => Configuration::get('BDROPPY_API_URL', true),
                'BDROPPY_API_KEY' => Configuration::get('BDROPPY_API_KEY', null),
                'BDROPPY_API_PASSWORD' => Configuration::get('BDROPPY_API_PASSWORD', null),
                'BDROPPY_TOKEN' => Configuration::get('BDROPPY_TOKEN', null),
                'BDROPPY_CATALOG' => Configuration::get('BDROPPY_CATALOG', null),
                'BDROPPY_CATALOG_BU' => Configuration::get('BDROPPY_CATALOG_BU', null),
                'BDROPPY_CATALOG_CHANGED' => Configuration::get('BDROPPY_CATALOG_CHANGED', null),
                'BDROPPY_ACTIVE_PRODUCT' => Configuration::get('BDROPPY_ACTIVE_PRODUCT', null),
                'BDROPPY_CUSTOM_FEATURE' => Configuration::get('BDROPPY_CUSTOM_FEATURE', null),
                'BDROPPY_SIZE' => Configuration::get('BDROPPY_SIZE', null),
                'BDROPPY_GENDER' => Configuration::get('BDROPPY_GENDER', null),
                'BDROPPY_COLOR' => Configuration::get('BDROPPY_COLOR', null),
                'BDROPPY_SEASON' => Configuration::get('BDROPPY_SEASON', null),
                'BDROPPY_CATEGORY_STRUCTURE' => Configuration::get('BDROPPY_CATEGORY_STRUCTURE', null),
                'BDROPPY_IMPORT_IMAGE' => Configuration::get('BDROPPY_IMPORT_IMAGE', null),
                'BDROPPY_REIMPORT_IMAGE' => Configuration::get('BDROPPY_REIMPORT_IMAGE', null),
                'BDROPPY_LIMIT_COUNT' => Configuration::get('BDROPPY_LIMIT_COUNT', null),
                'BDROPPY_IMPORT_BRAND_TO_TITLE' => Configuration::get('BDROPPY_IMPORT_BRAND_TO_TITLE', null),
                'BDROPPY_IMPORT_TAG_TO_TITLE' => Configuration::get('BDROPPY_IMPORT_TAG_TO_TITLE', null),
                'BDROPPY_AUTO_UPDATE_PRICES' => Configuration::get('BDROPPY_AUTO_UPDATE_PRICES', null),
                'BDROPPY_AUTO_UPDATE_NAME' => Configuration::get('BDROPPY_AUTO_UPDATE_NAME', null),
            );

            $db = Db::getInstance();
            $api_catalog = isset($configurations['BDROPPY_CATALOG']) ? $configurations['BDROPPY_CATALOG'] : '';
            $api_catalog_changed = isset($configurations['BDROPPY_CATALOG_CHANGED']) ?
                $configurations['BDROPPY_CATALOG_CHANGED'] : false;
            $api_limit_count = isset($configurations['BDROPPY_LIMIT_COUNT']) ?
                $configurations['BDROPPY_LIMIT_COUNT'] : 5;

            $acceptedlocales = '';
            $languages = Language::getLanguages();
            foreach ($languages as $lang) {
                if (isset($langs[$lang['iso_code']])) {
                    $acceptedlocales .= $langs[$lang['iso_code']] . ',';
                } else {
                    $acceptedlocales .= $langs['en'] . ',';
                }
            }
            $acceptedlocales = rtrim($acceptedlocales, ',');

            if (Tools::getIsset('ps_product_id')||Tools::getIsset('rewix_product_id')||Tools::getIsset('reference')) {
                $updateFlag = true;
                $sql = "";
                $items = [];
                if (Tools::getIsset('ps_product_id')) {
                    $query = new DbQuery();
                    $query->select('*')
                        ->from('bdroppy_remoteproduct')
                        ->where('ps_product_id = ' . (int)Tools::getValue('ps_product_id'));
                    $items = $db->executeS($query);
                }
                if (count($items) == 0 && Tools::getIsset('rewix_product_id')) {
                    $query = new DbQuery();
                    $query->select('*')
                        ->from('bdroppy_remoteproduct')
                        ->where('rewix_product_id = ' . (int)Tools::getValue('rewix_product_id'));
                    $items = $db->executeS($query);
                }
                if (count($items) == 0 && Tools::getIsset('reference')) {
                    $query = new DbQuery();
                    $query->select('*')
                        ->from('bdroppy_remoteproduct')
                        ->where('reference = ' . (int)Tools::getValue('reference'));
                    $items = $db->executeS($query);
                }
                foreach ($items as $item) {
                    $res = BdroppyImportTools::importProduct($item, $default_lang, $updateFlag);
                    echo($res);
                }
                die;
            }

            if ($api_catalog == "-1") {
                $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct`;";
                $delete_products = $db->ExecuteS($sql);
                if ($delete_products) {
                    foreach ($delete_products as $item) {
                        if ($item['ps_product_id'] != '0') {
                            $dp = new Product((int)$item['ps_product_id']);
                            $sql = "SELECT COUNT(id_cart) as total FROM  `" . _DB_PREFIX_ . "cart_product` WHERE " .
                            "id_product='" . (int)$dp->id . "';";
                            $total = $db->ExecuteS($sql);
                            if ($total[0]['total'] == 0) {
                                $dp->delete();
                            } else {
                                $dp->active = false;
                                $dp->save();
                            }
                        }
                        BdroppyRemoteProduct::deleteByRewixId($item['rewix_product_id']);
                    }
                }
                $sql = "SELECT * FROM `" . _DB_PREFIX_ . "product` WHERE unity LIKE ('bdroppy-%');";
                $delete_products = $db->ExecuteS($sql);
                foreach ($delete_products as $item) {
                    $dp = new Product((int)$item['id_product']);
                    $sql = "SELECT COUNT(id_cart) as total FROM  `" . _DB_PREFIX_ . "cart_product` WHERE id_product='" .
                        (int)$dp->id."';";
                    $total = $db->ExecuteS($sql);
                    if ($total[0]['total'] == 0) {
                        $dp->delete();
                    } else {
                        $dp->active = false;
                        $dp->save();
                    }
                }
            } else {
                if ($api_catalog!="" && $api_catalog!="0" && $api_catalog!="-1" && Tools::strlen($api_catalog)>1) {
                    $lastImportSync = (int)Configuration::get('BDROPPY_LAST_IMPORT_SYNC');
                    $devFlag = false;
                    //get update products since
                    $sql = "SELECT COUNT(id) AS total FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` " .
                        "WHERE sync_status <> '".BdroppyRemoteProduct::SYNC_STATUS_UPDATED."';";
                    $total_queue = $db->ExecuteS($sql);
                    if (Tools::getIsset('op')) {
                        if (Tools::getValue('op') == 'restart') {
                            $devFlag = true;
                        }
                    }
                    if ($devFlag || ($total_queue[0]['total'] == 0 && ((time() - $lastImportSync) >  3600 * 12 ||
                        $lastImportSync == 0 ||
                        $api_catalog_changed))) {
                        $rewixApi = new BdroppyRewixApi();
                        $r = $rewixApi->getProductsFull($acceptedlocales);
                        Configuration::updateValue('BDROPPY_CATALOG_CHANGED', false);
                        if ($r == 200) {
                            echo "Full Import";
                        } else {
                            echo $r;
                        }
                    } else {
                        if (!$api_limit_count) {
                            $api_limit_count = 5;
                        }
                        //delete products
                        $hourAgo = date('Y-m-d H:i:s', strtotime("-5 minutes"));
                        $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` 
                            WHERE sync_status = 'delete' AND last_sync_date <= '$hourAgo' LIMIT ".$api_limit_count.";";
                        $items = $db->ExecuteS($sql);
                        foreach ($items as $item) {
                            if ($item['ps_product_id'] != '0') {
                                $dp = new Product((int)$item['ps_product_id']);
                                $sql = "SELECT COUNT(id_cart) as total FROM  `" . _DB_PREFIX_ . "cart_product` WHERE ".
                                    "id_product='" . (int)$dp->id . "';";
                                $total = $db->ExecuteS($sql);
                                if ($total[0]['total'] == 0) {
                                    $dp->delete();
                                } else {
                                    $dp->active = false;
                                    $dp->save();
                                }
                            }
                            BdroppyRemoteProduct::deleteByRewixId($item['rewix_product_id']);
                        }

                        // change status of products
                        $fiveago = date('Y-m-d H:i:s', strtotime("-5 minutes"));
                        $db->update(
                            'bdroppy_remoteproduct',
                            array('sync_status' => 'queued'),
                            "sync_status = 'importing' AND last_sync_date <= '$fiveago'"
                        );

                        // products to import
                        $updateFlag = false;
                        $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` " .
                            "WHERE sync_status='queued' LIMIT " . $api_limit_count . ";";
                        $items = $db->ExecuteS($sql);
                        foreach ($items as $item) {
                            $res = $db->update(
                                'bdroppy_remoteproduct',
                                array(
                                    'sync_status' => 'importing',
                                    'last_sync_date' => date('Y-m-d H:i:s')
                                ),
                                'id = ' . (int)$item['id']
                            );
                        }
                        echo "<pre>";
                        foreach ($items as $item) {
                            if ($item['sync_status'] == 'queued') {
                                $res = BdroppyImportTools::importProduct($item, $default_lang, $updateFlag);
                                var_dump($item['rewix_product_id'] .':'.$res);
                            }
                        }

                        if ($total_queue[0]['total'] == 0) {
                            $lastQuantitiesSync = (int)Configuration::get('BDROPPY_LAST_QUANTITIES_SYNC');
                            if ($lastQuantitiesSync == 0) {
                                $lastQuantitiesSync = (int)Configuration::get('BDROPPY_LAST_IMPORT_SYNC');
                                Configuration::updateValue('BDROPPY_LAST_QUANTITIES_SYNC', $lastQuantitiesSync);
                            }

                            if ((time() - $lastQuantitiesSync) > 3600 || Tools::getIsset('since')) {
                                if (Tools::getIsset('since')) {
                                    $lastQuantitiesSync = Tools::getValue('since');
                                }
                                $iso8601 = date('Y-m-d\TH:i:s.v', $lastQuantitiesSync) . 'Z';
                                $rewixApi = new BdroppyRewixApi();
                                $rewixApi->getProductsJsonSince($api_catalog, $acceptedlocales, $iso8601);
                            }
                        }
                    }
                }
            }
        } catch (PrestaShopException $e) {
            $logMsg = 'importProducts : ' . $e->getMessage();
            BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
            return false;
        }
    }

    public static function syncProducts()
    {
        $lock = BdroppyImportTools::tryLock();

        if (!$lock) {
            $logMsg = 'SYNCPRODUCTS: Cannot get lock file';
            BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
            throw new Exception('Sync failed. Check logs from more detail.');
        }

        $lastSync = Configuration::get(BdroppyConfigKeys::LAST_IMPORT_SYNC);
        if ($lastSync == 0) {
            $lastSync = time();
            Configuration::updateValue(BdroppyConfigKeys::LAST_IMPORT_SYNC, $lastSync, null, 0, 0);
        }

        if ((time() - $lastSync) > 4 * 60) {
            BdroppyImportTools::processImportQueue();
            Configuration::updateValue(BdroppyConfigKeys::LAST_IMPORT_SYNC, (int) time(), null, 0, 0);
        }
    }

    public static function syncQuantities()
    {
        $lock = BdroppyImportTools::tryLock();

        if (!$lock) {
            $logMsg = 'SYNCPRODUCTS: Cannot get lock file';
            BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
            throw new Exception('Sync failed. Check logs from more detail.');
        }

        $lastSync = Configuration::get(BdroppyConfigKeys::LAST_QUANTITIES_SYNC);
        $incremental = true;
        if ($lastSync == 0) {
            $incremental = false;
        }

        if ((time() - $lastSync) > 5 * 60) {
            if ($incremental) {
                BdroppyImportTools::updateAllQuantitiesIncremental();
            } else {
                BdroppyImportTools::updateAllQuantities();
            }
        }
    }

    public static function syncOrders()
    {
        $lastSync = (int)Configuration::get('BDROPPY_LAST_CART_SYNC');
        if ($lastSync == 0) {
            $lastSync = time();
            Configuration::updateValue('BDROPPY_LAST_CART_SYNC', $lastSync);
        }

        $noImportFlag = false;
        if (Tools::getIsset('no_import')) {
            if (Tools::getValue('no_import') == '1') {
                $noImportFlag = true;
            }
        }

        if ((time() - $lastSync) > 10 * 60 || $noImportFlag) {
            $rewixApi = new BdroppyRewixApi();
            $rewixApi->updateOrderStatuses();
            $rewixApi->syncBookedProducts();
            $rewixApi->sendMissingOrders();
            Configuration::updateValue('BDROPPY_LAST_CART_SYNC', (int)time());
        }
    }

    public function assignTpl()
    {
        if (Tools::substr(_PS_VERSION_, 0, 3) == '1.7') {
            $this->setTemplate('module:bdroppy/views/templates/front/cron.tpl');
        } else {
            $this->setTemplate('cron.tpl');
        }
    }
}
