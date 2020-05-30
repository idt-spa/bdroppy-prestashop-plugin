<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
if (!defined('_PS_ROOT_DIR_')) {
    define('_PS_ROOT_DIR_', (dirname(dirname(dirname(__FILE__)))));
}

include_once _PS_ROOT_DIR_ . '/config/config.inc.php';
include_once _PS_ROOT_DIR_ . '/init.php';
include_once dirname(__FILE__) . '/classes/ImportTools.php';
include_once dirname(__FILE__) . '/classes/RewixApi.php';

$importFlag = true;
if(isset($_GET['no_import']))
    if($_GET['no_import'] == '1')
        $importFlag = false;
if($importFlag)
    BdroppyCron::importProducts();
//BdroppyCron::syncProducts();
//BdroppyCron::syncQuantities();
BdroppyCron::syncOrders();
class BdroppyCron
{
    public static $logger = null;
    public static function getLogger()
    {
        if (self::$logger == null) {
            $verboseLog = true;
            self::$logger = new FileLogger($verboseLog ? FileLogger::DEBUG : FileLogger::ERROR);
            $filename = _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'bdroppy-cron-'.date('y-m-d').'.log';
            self::$logger->setFilename($filename);
        }

        return self::$logger;
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

    public static function importProducts() {
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
            );

            $db = Db::getInstance();
            $base_url = isset($configurations['BDROPPY_API_URL']) ? $configurations['BDROPPY_API_URL'] : '';
            $api_key = isset($configurations['BDROPPY_API_KEY']) ? $configurations['BDROPPY_API_KEY'] : '';
            $api_password = isset($configurations['BDROPPY_API_PASSWORD']) ? $configurations['BDROPPY_API_PASSWORD'] : '';
            $api_token = isset($configurations['BDROPPY_TOKEN']) ? $configurations['BDROPPY_TOKEN'] : '';
            $api_catalog = isset($configurations['BDROPPY_CATALOG']) ? $configurations['BDROPPY_CATALOG'] : '';
            $api_catalog_bu = isset($configurations['BDROPPY_CATALOG_BU']) ? $configurations['BDROPPY_CATALOG_BU'] : '';
            $api_catalog_changed = isset($configurations['BDROPPY_CATALOG_CHANGED']) ? $configurations['BDROPPY_CATALOG_CHANGED'] : false;
            $api_active_product = isset($configurations['BDROPPY_ACTIVE_PRODUCT']) ? $configurations['BDROPPY_ACTIVE_PRODUCT'] : '';
            $api_custom_feature = isset($configurations['BDROPPY_CUSTOM_FEATURE']) ? $configurations['BDROPPY_CUSTOM_FEATURE'] : '';
            $api_size = isset($configurations['BDROPPY_SIZE']) ? $configurations['BDROPPY_SIZE'] : '';
            $api_gender = isset($configurations['BDROPPY_GENDER']) ? $configurations['BDROPPY_GENDER'] : '';
            $api_color = isset($configurations['BDROPPY_COLOR']) ? $configurations['BDROPPY_COLOR'] : '';
            $api_season = isset($configurations['BDROPPY_SEASON']) ? $configurations['BDROPPY_SEASON'] : '';
            $api_category_structure = isset($configurations['BDROPPY_CATEGORY_STRUCTURE']) ? $configurations['BDROPPY_CATEGORY_STRUCTURE'] : '';
            $api_import_image = isset($configurations['BDROPPY_IMPORT_IMAGE']) ? $configurations['BDROPPY_IMPORT_IMAGE'] : '';
            $api_reimport_image = isset($configurations['BDROPPY_REIMPORT_IMAGE']) ? $configurations['BDROPPY_REIMPORT_IMAGE'] : 0;
            $api_limit_count = isset($configurations['BDROPPY_LIMIT_COUNT']) ? $configurations['BDROPPY_LIMIT_COUNT'] : 5;
            $bdroppy_import_brand_to_title = isset($configurations['BDROPPY_IMPORT_BRAND_TO_TITLE']) ? $configurations['BDROPPY_IMPORT_BRAND_TO_TITLE'] : '';
            $bdroppy_import_tag_to_title = isset($configurations['BDROPPY_IMPORT_TAG_TO_TITLE']) ? $configurations['BDROPPY_IMPORT_TAG_TO_TITLE'] : '';
            $bdroppy_auto_update_prices = isset($configurations['BDROPPY_AUTO_UPDATE_PRICES']) ? $configurations['BDROPPY_AUTO_UPDATE_PRICES'] : '';
            $db = Db::getInstance();

            $acceptedlocales = '';
            $languages = Language::getLanguages();
            foreach ($languages as $lang) {
                $acceptedlocales .= $langs[$lang['iso_code']] . ',';
            }
            $acceptedlocales = rtrim($acceptedlocales, ',');


            if(isset($_GET['ps_product_id']) || isset($_GET['rewix_product_id']) || isset($_GET['reference'])) {
                $updateFlag = true;
                $sql = "";
                $items = [];
                if(isset($_GET['ps_product_id'])) {
                    $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` WHERE ps_product_id = '". $_GET['ps_product_id'] ."';";
                    $items = $db->ExecuteS($sql);
                }
                if(count($items) == 0 && isset($_GET['rewix_product_id'])) {
                    $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` WHERE rewix_product_id = '". $_GET['rewix_product_id'] ."';";
                    $items = $db->ExecuteS($sql);
                }
                if(count($items) == 0 && isset($_GET['reference'])) {
                    $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` WHERE reference = '". $_GET['reference'] ."';";
                    $items = $db->ExecuteS($sql);
                }
                foreach ($items as $item) {
                    $res = BdroppyImportTools::importProduct($item, $default_lang, $updateFlag, $acceptedlocales);
                    echo($res);
                    //BdroppyImportTools::updateProductPrices($item, $default_lang);
                }
                die;
            }

            if($api_catalog == "-1") {
                $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct`;";
                $delete_products = $db->ExecuteS($sql);
                if($delete_products) {
                    foreach ($delete_products as $item) {
                        if($item['ps_product_id'] != '0') {
                            $dp = new Product($item['ps_product_id']);
                            $dp->delete();
                        }
                        BdroppyRemoteProduct::deleteByRewixId($item['rewix_product_id']);
                    }
                } else {
                    $sql = "SELECT * FROM `" . _DB_PREFIX_ . "product` WHERE unity='$api_catalog_bu';";
                    $delete_products = $db->ExecuteS($sql);
                    foreach ($delete_products as $item) {
                        $product = new Product($item['id_product']);
                        $product->delete();
                    }
                }
            } else {
                if ($api_catalog != "" && $api_catalog != "0" && $api_catalog != "-1" && strlen($api_catalog) > 1) {
                    $lastImportSync = (int)Configuration::get('BDROPPY_LAST_IMPORT_SYNC');
                    if ($lastImportSync == 0) {
                        $lastImportSync = time();
                        Configuration::updateValue('BDROPPY_LAST_IMPORT_SYNC', $lastImportSync);
                    }

                    $devFlag = false;
                    if(isset($_GET['dev']))
                        if($_GET['dev'] == 'isaac')
                            $devFlag = true;
                    $sql = "SELECT COUNT(id) as total FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct`;";
                    $total = $db->ExecuteS($sql);
                    if ((time() - $lastImportSync) > 4 * 3600 || $devFlag || $total[0]['total'] == 0 || $api_catalog_changed) {
                        $rewixApi = new BdroppyRewixApi();
                        $r = $rewixApi->getProductsFull($acceptedlocales);
                        Configuration::updateValue('BDROPPY_CATALOG_CHANGED', false);
                        echo "Full Import";
                    } else {
                        if (!$api_limit_count)
                            $api_limit_count = 5;
                        $api_limit_count = $api_limit_count;

                        //delete products
                        $hourAgo = date('Y-m-d H:i:s', strtotime("-15 minutes"));
                        $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` WHERE sync_status='delete' AND last_sync_date <= '$hourAgo' LIMIT " . $api_limit_count . ";";
                        $items = $db->ExecuteS($sql);
                        foreach ($items as $item) {
                            if($item['ps_product_id'] != '0') {
                                $dp = new Product($item['ps_product_id']);
                                $dp->delete();
                            }
                            BdroppyRemoteProduct::deleteByRewixId($item['rewix_product_id']);
                        }

                        // change status of products
                        $fiveago = date('Y-m-d H:i:s', strtotime("-5 minutes"));
                        $res = $db->update('bdroppy_remoteproduct', array('sync_status' => 'queued'), "sync_status = 'importing' AND last_sync_date <= '$fiveago'");

                        // products to import
                        $updateFlag = false;
                        $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` WHERE sync_status='queued' LIMIT " . $api_limit_count . ";";
                        $items = $db->ExecuteS($sql);
                        foreach ($items as $item) {
                            $res = $db->update('bdroppy_remoteproduct', array('sync_status' => 'importing', 'last_sync_date' => date('Y-m-d H:i:s')), 'id = ' . $item['id']);
                        }
                        echo "<pre>";
                        foreach ($items as $item) {
                            if ($item['sync_status'] == 'queued') {
                                $res = BdroppyImportTools::importProduct($item, $default_lang, $updateFlag, $acceptedlocales);
                                var_dump($item['rewix_product_id'] .':'.$res);
                            }
                        }

                        //get update products since
                        if ($bdroppy_auto_update_prices) {
                            $sql = "SELECT COUNT(id) as total FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` WHERE sync_status = 'queued' OR sync_status = 'importing' OR sync_status = 'failed';";
                            $total = $db->ExecuteS($sql);
                            if($total[0]['total'] == 0 || isset($_GET['dev'])) {
                                $lastQuantitiesSync = (int)Configuration::get('BDROPPY_LAST_IMPORT_SYNC');
                                if ($lastQuantitiesSync == 0) {
                                    $lastQuantitiesSync = time();
                                    Configuration::updateValue('BDROPPY_LAST_IMPORT_SYNC', $lastQuantitiesSync);
                                }
                                $rewixApi = new BdroppyRewixApi();
                                $iso8601 = date('Y-m-d\TH:i:s.u', $lastQuantitiesSync);

                                if ((time() - $lastQuantitiesSync) > 1800) {
                                    $res = $rewixApi->getProductsJsonSince($api_catalog, $acceptedlocales, $iso8601);
                                }
                            }
                        }
                    }
                }
            }
        } catch (PrestaShopException $e) {
            self::getLogger()->logDebug( 'importProducts : ' . $e->getMessage() );
            return false;
        }
    }

    public static function syncProducts()
    {
        $logger = BdroppyImportTools::getLogger();
        $lock = BdroppyImportTools::tryLock();

        if (!$lock) {
            $logger->logDebug('SYNCPRODUCTS: Cannot get lock file');
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
        $logger = BdroppyImportTools::getLogger();
        $lock = BdroppyImportTools::tryLock();

        if (!$lock) {
            $logger->logDebug('SYNCPRODUCTS: Cannot get lock file');
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
        if (isset($_GET['no_import'])) {
            if ($_GET['no_import'] == '1') {
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
}