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

BdroppyCron::importProducts();
BdroppyCron::updatePrices();
//BdroppyCron::syncProducts();
//BdroppyCron::syncQuantities();
//BdroppyCron::syncCarts();
class BdroppyCron
{
    public static $logger = null;
    public static function getLogger()
    {
        if (self::$logger == null) {
            $verboseLog = true;
            self::$logger = new FileLogger($verboseLog ? FileLogger::DEBUG : FileLogger::ERROR);
            $filename = _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'bdroppy-cron.log';
            self::$logger->setFilename($filename);
        }

        return self::$logger;
    }
    public static function importProducts() {
        try {
            header('Access-Control-Allow-Origin: *');
            @ini_set('max_execution_time', 100000);

            $context = Context::getContext();
            $default_lang = str_replace('-', '_', $context->language->locale);

            $configurations = array(
                'BDROPPY_API_URL' => Configuration::get('BDROPPY_API_URL', true),
                'BDROPPY_API_KEY' => Configuration::get('BDROPPY_API_KEY', null),
                'BDROPPY_API_PASSWORD' => Configuration::get('BDROPPY_API_PASSWORD', null),
                'BDROPPY_TOKEN' => Configuration::get('BDROPPY_TOKEN', null),
                'BDROPPY_CATALOG' => Configuration::get('BDROPPY_CATALOG', null),
                'BDROPPY_SIZE' => Configuration::get('BDROPPY_SIZE', null),
                'BDROPPY_GENDER' => Configuration::get('BDROPPY_GENDER', null),
                'BDROPPY_COLOR' => Configuration::get('BDROPPY_COLOR', null),
                'BDROPPY_SEASON' => Configuration::get('BDROPPY_SEASON', null),
                'BDROPPY_CATEGORY_STRUCTURE' => Configuration::get('BDROPPY_CATEGORY_STRUCTURE', null),
                'BDROPPY_IMPORT_IMAGE' => Configuration::get('BDROPPY_IMPORT_IMAGE', null),
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
            $api_size = isset($configurations['BDROPPY_SIZE']) ? $configurations['BDROPPY_SIZE'] : '';
            $api_gender = isset($configurations['BDROPPY_GENDER']) ? $configurations['BDROPPY_GENDER'] : '';
            $api_color = isset($configurations['BDROPPY_COLOR']) ? $configurations['BDROPPY_COLOR'] : '';
            $api_season = isset($configurations['BDROPPY_SEASON']) ? $configurations['BDROPPY_SEASON'] : '';
            $api_category_structure = isset($configurations['BDROPPY_CATEGORY_STRUCTURE']) ? $configurations['BDROPPY_CATEGORY_STRUCTURE'] : '';
            $api_import_image = isset($configurations['BDROPPY_IMPORT_IMAGE']) ? $configurations['BDROPPY_IMPORT_IMAGE'] : '';
            $api_limit_count = isset($configurations['BDROPPY_LIMIT_COUNT']) ? $configurations['BDROPPY_LIMIT_COUNT'] : 5;
            $bdroppy_import_brand_to_title = isset($configurations['BDROPPY_IMPORT_BRAND_TO_TITLE']) ? $configurations['BDROPPY_IMPORT_BRAND_TO_TITLE'] : '';
            $bdroppy_import_tag_to_title = isset($configurations['BDROPPY_IMPORT_TAG_TO_TITLE']) ? $configurations['BDROPPY_IMPORT_TAG_TO_TITLE'] : '';
            $bdroppy_auto_update_prices = isset($configurations['BDROPPY_AUTO_UPDATE_PRICES']) ? $configurations['BDROPPY_AUTO_UPDATE_PRICES'] : '';
            $db = Db::getInstance();

            if($api_catalog == "-1") {
                $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct`;";
                $delete_products = $db->ExecuteS($sql);
                foreach ($delete_products as $item) {
                    switch ($item['sync_status']) {
                        case 'queued':
                        case 'delete':
                            $db->delete('bdroppy_remoteproduct', "rewix_product_id = '" . $item['rewix_product_id'] . "'");
                            break;
                        case 'updated':
                            $product = new Product($item['ps_product_id']);
                            $product->delete();
                            $db->delete('bdroppy_remoteproduct', "rewix_product_id = '" . $item['rewix_product_id'] . "'");
                            break;
                        case 'importing':
                            if($item['rewix_product_id'] == 0) {
                                $db->delete('bdroppy_remoteproduct', "rewix_product_id = '" . $item['rewix_product_id'] . "'");
                            } else {
                                $product = new Product($item['ps_product_id']);
                                $product->delete();
                                $db->delete('bdroppy_remoteproduct', "rewix_product_id = '" . $item['rewix_product_id'] . "'");
                            }
                            break;
                    }
                }
            }
            if($api_catalog != "" && $api_catalog != "0" && $api_catalog != "-1" && strlen($api_catalog)>1) {
                $fiveago = date('Y-m-d H:i:s', strtotime("-3 minutes"));
                $res = $db->update('bdroppy_remoteproduct', array('sync_status' => 'queued'), "sync_status = 'importing' AND last_sync_date <= '$fiveago'");

                if (!$api_limit_count)
                    $api_limit_count = 5;
                $min = date('i') % 5;
                if($min == 0 || $min == 5 || $_GET['dev'] == 'isaac') {
                    $rewixApi = new BdroppyRewixApi();
                    $res = $rewixApi->getProductsJson($api_catalog);
                    if ($res['http_code'] === 200 && $res['data'] != "null") {
                        $ids = [];
                        $catalog = json_decode($res['data']);
                        $sql = "SELECT rewix_product_id FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` WHERE (sync_status = 'queued' OR sync_status = 'updated' OR sync_status = 'importing');";
                        $prds = $db->ExecuteS($sql);
                        foreach ($catalog->items as $item) {
                            $ids[] = $item->refId;
                        }
                        $products = array_map(function ($item) {
                            return (integer)$item['rewix_product_id'];
                        }, $prds);
                        $add_products = array_diff($ids, $products);
                        $delete_products = array_diff($products,$ids);

                        foreach ($delete_products as $id) {
                            $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` WHERE rewix_product_id = '$id';";
                            $item = $db->ExecuteS($sql);
                            switch ($item[0]['sync_status']) {
                                case 'queued':
                                case 'delete':
                                    $res = $db->delete('bdroppy_remoteproduct', "rewix_product_id = '" . $item[0]['rewix_product_id'] . "'");
                                    break;
                                case 'updated':
                                    $product = new Product($item[0]['ps_product_id']);
                                    $product->delete();
                                    $res = $db->delete('bdroppy_remoteproduct', "rewix_product_id = '" . $item[0]['rewix_product_id'] . "'");
                                    break;
                            }
                        }

                        foreach ($add_products as $item) {
                            $db->insert('bdroppy_remoteproduct', array(
                                'rewix_product_id' => pSQL($item),
                                'rewix_catalog_id' => pSQL($api_catalog),
                                'sync_status' => pSQL('queued'),
                            ));
                        }
                    }
                }

                // select 10 products to import
                $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` WHERE sync_status='queued' LIMIT " . $api_limit_count . ";";
                $items = $db->ExecuteS($sql);
                foreach ($items as $item) {
                    $res = $db->update('bdroppy_remoteproduct', array('sync_status' => 'importing', 'last_sync_date' => date('Y-m-d H:i:s')), 'id = ' . $item['id']);
                }
                foreach ($items as $item) {
                    if ($item['sync_status'] == 'queued') {
                        if ($default_lang == 'en_GB')
                            $default_lang = 'en_US';
                        $res = BdroppyImportTools::importProduct($item, $default_lang);
                    }
                    if ($item['sync_status'] == 'delete') {
                        $res = $db->update('bdroppy_remoteproduct', array('sync_status' => 'deleted', 'imported' => 0), 'id = ' . $item['id']);
                    }
                }
            }
        } catch (PrestaShopException $e) {
            self::getLogger()->logDebug( 'importProducts : ' . $e->getMessage() );
            return false;
        }
    }

    public static function updatePrices() {
        try {
            header('Access-Control-Allow-Origin: *');
            @ini_set('max_execution_time', 100000);

            $context = Context::getContext();
            $default_lang = str_replace('-', '_', $context->language->locale);

            $configurations = array(
                'BDROPPY_API_URL' => Configuration::get('BDROPPY_API_URL', true),
                'BDROPPY_API_KEY' => Configuration::get('BDROPPY_API_KEY', null),
                'BDROPPY_API_PASSWORD' => Configuration::get('BDROPPY_API_PASSWORD', null),
                'BDROPPY_TOKEN' => Configuration::get('BDROPPY_TOEKN', null),
                'BDROPPY_CATALOG' => Configuration::get('BDROPPY_CATALOG', null),
                'BDROPPY_SIZE' => Configuration::get('BDROPPY_SIZE', null),
                'BDROPPY_GENDER' => Configuration::get('BDROPPY_GENDER', null),
                'BDROPPY_COLOR' => Configuration::get('BDROPPY_COLOR', null),
                'BDROPPY_SEASON' => Configuration::get('BDROPPY_SEASON', null),
                'BDROPPY_CATEGORY_STRUCTURE' => Configuration::get('BDROPPY_CATEGORY_STRUCTURE', null),
                'BDROPPY_IMPORT_IMAGE' => Configuration::get('BDROPPY_IMPORT_IMAGE', null),
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
            $api_size = isset($configurations['BDROPPY_SIZE']) ? $configurations['BDROPPY_SIZE'] : '';
            $api_gender = isset($configurations['BDROPPY_GENDER']) ? $configurations['BDROPPY_GENDER'] : '';
            $api_color = isset($configurations['BDROPPY_COLOR']) ? $configurations['BDROPPY_COLOR'] : '';
            $api_season = isset($configurations['BDROPPY_SEASON']) ? $configurations['BDROPPY_SEASON'] : '';
            $api_category_structure = isset($configurations['BDROPPY_CATEGORY_STRUCTURE']) ? $configurations['BDROPPY_CATEGORY_STRUCTURE'] : '';
            $api_import_image = isset($configurations['BDROPPY_IMPORT_IMAGE']) ? $configurations['BDROPPY_IMPORT_IMAGE'] : '';
            $api_limit_count = isset($configurations['BDROPPY_LIMIT_COUNT']) ? $configurations['BDROPPY_LIMIT_COUNT'] : 5;
            $bdroppy_import_brand_to_title = isset($configurations['BDROPPY_IMPORT_BRAND_TO_TITLE']) ? $configurations['BDROPPY_IMPORT_BRAND_TO_TITLE'] : '';
            $bdroppy_import_tag_to_title = isset($configurations['BDROPPY_IMPORT_TAG_TO_TITLE']) ? $configurations['BDROPPY_IMPORT_TAG_TO_TITLE'] : '';
            $bdroppy_auto_update_prices = isset($configurations['BDROPPY_AUTO_UPDATE_PRICES']) ? $configurations['BDROPPY_AUTO_UPDATE_PRICES'] : '';

            if($api_catalog != "" && $api_catalog != "0" && $api_catalog != "-1" && strlen($api_catalog)>1) {
                if (!$api_limit_count)
                    $api_limit_count = 5;
                if ($bdroppy_auto_update_prices) {
                    $yesterday = date('Y-m-d H:i:s', strtotime("-1 day"));
                    $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` WHERE sync_status = 'updated' AND last_sync_date <= '$yesterday' LIMIT $api_limit_count;";
                    $items = $db->ExecuteS($sql);
                    foreach ($items as $item) {
                        BdroppyImportTools::updateProductPrices($item, $default_lang);
                    }
                }
            }
        } catch (PrestaShopException $e) {
            self::getLogger()->logDebug( 'updatePrices : ' . $e->getMessage() );
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

    public static function syncCarts()
    {
        $logger = BdroppyImportTools::getLogger();
        $lock = BdroppyImportTools::tryLock();

        if (!$lock) {
            $logger->logDebug('SYNCCARTS: Cannot get lock file');
            throw new Exception('Sync failed. Check logs from more detail.');
        }

        $lastSync = (int) Configuration::get(BdroppyConfigKeys::LAST_CART_SYNC);
        if ($lastSync == 0) {
            $lastSync = time();
            Configuration::updateValue(BdroppyConfigKeys::LAST_CART_SYNC, $lastSync, null, 0, 0);
        }

        if ((time() - $lastSync) > 30 * 60) {
            BdroppyImportTools::syncWithSupplier();
            Configuration::updateValue(BdroppyConfigKeys::LAST_CART_SYNC, (int) time(), null, 0, 0);
        }
    }
}