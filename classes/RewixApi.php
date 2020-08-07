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

use BDroppy\Includes\WC\Models\ProductModel;

include_once dirname(__FILE__).'/ImportTools.php';
include_once dirname(__FILE__).'/RemoteOrder.php';
include_once dirname(__FILE__).'/ConfigKeys.php';
include_once dirname(__FILE__).'/Logger.php';

class BdroppyRewixApi
{
    const SOLD_API_LOCK_OP   = 'lock';
    const SOLD_API_SET_OP    = 'set';
    const SOLD_API_UNLOCK_OP = 'unlock';

    private $processingCache;
    private $pendingCache;

    public function __construct()
    {
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

    public function getProductJson($product_id, $catalog_id)
    {
        $ret = [];
        $api_token = Configuration::get('BDROPPY_TOKEN');
        $header = "Authorization: Bearer " . $api_token;

        $url = Configuration::get('BDROPPY_API_URL') . "/restful/product/$product_id/usercatalog/$catalog_id";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'accept: application/json', 'Content-Type: application/json', $header));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5000);
        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //$curl_error = curl_error($ch);
        curl_close($ch);
        if ($http_code != 200) {
            $logMsg = 'getProduct - http_code : ' . $http_code . ' - url : ' . $url . ' data : ' . $data;
            BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
        }
        $ret['http_code'] = $http_code;
        $ret['data'] = $data;
        return $ret;
    }

    public function getProductsFull($acceptedlocales)
    {
        try {
            ini_set('max_execution_time', 0);
            set_time_limit(0);
            $ids = [];
            $db = Db::getInstance();
            $sql = "SELECT p.id_product FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` br RIGHT JOIN 
            `" . _DB_PREFIX_ . "product` p ON (br.ps_product_id = p.id_product) 
            WHERE br.rewix_product_id IS NULL AND p.unity <> '';";
            $items = $db->ExecuteS($sql);
            foreach ($items as $item) {
                $dp = new Product($item['id_product']);
                $dp->delete();
            }
            $pageSize = 100;
            $base_url = Configuration::get('BDROPPY_API_URL');
            $api_token = Configuration::get('BDROPPY_TOKEN');
            $api_catalog = Configuration::get('BDROPPY_CATALOG');
            $url = $base_url . "/restful/export/api/products.json?pageSize=".
                "$pageSize&page=1&acceptedlocales=$acceptedlocales&user_catalog=$api_catalog";

            $header = "Authorization: Bearer " . $api_token;

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt(
                $ch,
                CURLOPT_HTTPHEADER,
                array('accept: application/json', 'Content-Type: application/json', $header)
            );
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

            $data = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            //$curl_error = curl_error($ch);
            curl_close($ch);

            if ($http_code == 200) {
                $json = json_decode($data);
                foreach ($json->items as $item) {
                    $ids[] = $item->id;
                    $jsonProduct = json_encode(
                        $item,
                        JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                    );
                    $remoteProduct = BdroppyRemoteProduct::fromRewixId($item->id);
                    $remoteProduct->reference = self::fitReference($item->code, $item->id);
                    $remoteProduct->rewix_catalog_id = $api_catalog;
                    $remoteProduct->last_sync_date = date('Y-m-d H:i:s');
                    if ($remoteProduct->sync_status == '' || $remoteProduct->reason != $item->lastUpdate) {
                        $remoteProduct->sync_status = 'queued';
                    }
                    $remoteProduct->reason = $item->lastUpdate;
                    $remoteProduct->data = $jsonProduct;
                    $remoteProduct->save();
                }
                if ($json->totalPages >= 2) {
                    for ($i = 2; $i <= $json->totalPages; $i++) {
                        $url = $base_url . "/restful/export/api/products.json?pageSize=$pageSize&page=$i" .
                            "&acceptedlocales=$acceptedlocales&user_catalog=$api_catalog";

                        $header = "Authorization: Bearer " . $api_token;

                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                        curl_setopt(
                            $ch,
                            CURLOPT_HTTPHEADER,
                            array('accept: application/json', 'Content-Type: application/json', $header)
                        );
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

                        $data = curl_exec($ch);
                        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        //$curl_error = curl_error($ch);
                        curl_close($ch);
                        if ($http_code == 200) {
                            $json = json_decode($data);
                            foreach ($json->items as $item) {
                                $ids[] = $item->id;
                                $jsonProduct = json_encode(
                                    $item,
                                    JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                                );
                                $remoteProduct = BdroppyRemoteProduct::fromRewixId($item->id);
                                $remoteProduct->reference = self::fitReference($item->code, $item->id);
                                $remoteProduct->rewix_catalog_id = $api_catalog;
                                $remoteProduct->last_sync_date = date('Y-m-d H:i:s');
                                if ($remoteProduct->sync_status == '' || $remoteProduct->reason != $item->lastUpdate) {
                                    $remoteProduct->sync_status = 'queued';
                                }
                                $remoteProduct->reason = $item->lastUpdate;
                                $remoteProduct->data = $jsonProduct;
                                $remoteProduct->save();
                            }
                        } else {
                            $logMsg = 'getProductsFull - http_code : '.$http_code.' - url : '.$url.' data : '.$data;
                            BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
                        }
                    }
                }

                $sql = "SELECT rewix_product_id FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct`;";
                $prds = $db->ExecuteS($sql);

                $products = array_map(function ($item) {
                    return (integer)$item['rewix_product_id'];
                }, $prds);
                $delete_products = array_diff($products, $ids);

                if (count($delete_products) > 0) {
                    $db->update(
                        'bdroppy_remoteproduct',
                        array('sync_status' => 'delete'),
                        "rewix_product_id IN (" . pSQL(implode(',', $delete_products)) . ")"
                    );
                }
                $logMsg = 'getProductsFull - done';
                BdroppyLogger::addLog(__METHOD__, $logMsg, 2);
                Configuration::updateValue('BDROPPY_LAST_IMPORT_SYNC', (int)time());
            } else {
                $logMsg = 'getProductsFull - http_code : ' . $http_code . ' - url : ' . $url . ' data : ' . $data;
                BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
            }
            return $http_code;
        } catch (PrestaShopException $e) {
            return $e->getMessage();
        }
    }

    public function getProductsJsonSince($catalog_id, $acceptedlocales, $lastQuantitiesSync)
    {
        ini_set('max_execution_time', 0);
        set_time_limit(0);
        $api_token = Configuration::get('BDROPPY_TOKEN');
        $api_catalog = Configuration::get('BDROPPY_CATALOG');
        $header = "Authorization: Bearer " . $api_token;


        $url = Configuration::get('BDROPPY_API_URL') . "/restful/export/api/products.json?acceptedlocales=".
            "$acceptedlocales&user_catalog=$catalog_id&since=$lastQuantitiesSync";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'accept: application/json',
            'Content-Type: application/json',
            $header));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //$curl_error = curl_error($ch);
        curl_close($ch);
        if ($http_code == 200) {
            $json = json_decode($data);
            foreach ($json->items as $item) {
                $jsonProduct = json_encode(
                    $item,
                    JSON_PRETTY_PRINT | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
                );
                $remoteProduct = BdroppyRemoteProduct::fromRewixId($item->id);
                $remoteProduct->reference = self::fitReference($item->code, $item->id);
                $remoteProduct->rewix_catalog_id = $api_catalog;
                $remoteProduct->last_sync_date = date('Y-m-d H:i:s');
                if ($remoteProduct->sync_status == '' || $remoteProduct->reason != $item->lastUpdate) {
                    $remoteProduct->sync_status = 'queued';
                }
                $remoteProduct->reason = $item->lastUpdate;
                $remoteProduct->data = $jsonProduct;
                $remoteProduct->save();
            }
            $logMsg = 'getProductsJsonSince - done';
            BdroppyLogger::addLog(__METHOD__, $logMsg, 2);
            Configuration::updateValue('BDROPPY_LAST_QUANTITIES_SYNC', (int)time());
        } else {
            BdroppyLogger::addLog(__METHOD__, 'http_code : ' . $http_code . ' - url : ' . $url .
                ' data : '.$data, 2);
        }
        return $http_code;
    }

    public function getProduct($file, $product_id)
    {
        $xml = new XMLReader();
        if (!$xml->open($file)) {
            return false;
        }
        while ($xml->read()) {
            if ($xml->nodeType==XMLReader::ELEMENT && $xml->name == 'item') {
                $product_xml = $xml->readOuterXml();
                $xmlProduct = simplexml_load_string(
                    $product_xml,
                    'SimpleXMLElement',
                    LIBXML_NOBLANKS && LIBXML_NOWARNING
                );
                $json = json_encode($xmlProduct);
                $product = json_decode($json);

                if ((string)$product->id == $product_id) {
                    $xml->close();
                    return $product;
                }
            }
        }
        $xml->close();
        return false;
    }

    public function getProductsJson($api_catalog)
    {
        $ret = [];
        $base_url = Configuration::get('BDROPPY_API_URL');
        $api_token = Configuration::get('BDROPPY_TOKEN');
        $url = "$base_url /restful/export/api/products.json?user_catalog=" .
            "$api_catalog&acceptedlocales=en_US&onlyid=true";

        $header = "Authorization: Bearer " . $api_token;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'accept: application/json',
            'Content-Type: application/json',
            $header));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");

        $data = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //$curl_error = curl_error($ch);
        curl_close($ch);
        if ($http_code != 200) {
            $logMsg = 'getProductsJson - http_code : ' . $http_code . ' - url : ' . $url . ' data : ' . $data;
            BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
        }
        $ret['http_code'] = $http_code;
        $ret['data'] = $data;
        return $ret;
    }

    public function getUserInfo()
    {
        $ret = [];
        $base_url = Configuration::get('BDROPPY_API_URL');
        $api_token = Configuration::get('BDROPPY_TOKEN');

        $url = $base_url . '/api/user/me';

        $header = "Authorization: Bearer " . $api_token;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'accept: application/json',
            'Content-Type: application/json',
            $header));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        $data  = curl_exec($ch);
        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //$curl_error = curl_error($ch);
        curl_close($ch);
        if ($http_code != 200) {
            $logMsg = 'getUserInfo - http_code : ' . $http_code . ' - url : ' . $url . ' data : ' . $data;
            BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
        }
        if ($http_code === 200) {
            $data = json_decode($data);
        }
        $ret['http_code'] = $http_code;
        $ret['data'] = $data;
        return $ret;
    }

    public function setCronJob($cron_url)
    {
        $ret = [];
        $base_url = Configuration::get('BDROPPY_API_URL');
        $api_token = Configuration::get('BDROPPY_TOKEN');

        $header = "Authorization: Bearer " . $api_token;
        $url = $base_url . '/restful/user_cron';
        $data = array(
            'name' => Configuration::get('PS_SHOP_NAME'),
            'description' => 'auto created by ps module',
            'url' => $cron_url,
            'interval' => 2,
            'status' => 1
        );
        $data_string = json_encode($data);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $header));

        curl_setopt($ch, CURLOPT_POST, count($data));
        $data = curl_exec($ch);

        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //$curl_error = curl_error($ch);
        curl_close($ch);
        if ($http_code != 200) {
            $logMsg = 'loginUser - http_code : ' . $http_code . ' - url : ' . $url . ' data : ' . $data;
            BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
        }
        if ($http_code === 200) {
            $data = json_decode($data);
        }
        $ret['http_code'] = $http_code;
        $ret['data'] = $data;
        return $ret;
    }

    public function loginUser()
    {
        $ret = [];
        $base_url = Configuration::get('BDROPPY_API_URL');
        $api_key = Configuration::get('BDROPPY_API_KEY');
        $api_password = Configuration::get('BDROPPY_API_PASSWORD');

        $url = $base_url . '/api/auth/login';

        $data = array(
            'email' => $api_key,
            'password' => $api_password
        );
        $data_string = json_encode($data);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));

        curl_setopt($ch, CURLOPT_POST, count($data));
        $data = curl_exec($ch);

        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //$curl_error = curl_error($ch);
        curl_close($ch);
        if ($http_code != 200) {
            BdroppyLogger::addLog(__METHOD__, 'loginUser - http_code : ' . $http_code . ' - url : ' .
                $url . ' data : ' . $data, 1);
        }
        if ($http_code === 200) {
            $data = json_decode($data);
        }
        $ret['http_code'] = $http_code;
        $ret['data'] = $data;
        return $ret;
    }

    public function connectUserCatalog()
    {
        $ret = [];
        $base_url = Configuration::get('BDROPPY_API_URL');
        $api_catalog = Configuration::get('BDROPPY_CATALOG');
        $api_token = Configuration::get('BDROPPY_TOKEN');

        $url = $base_url . '/restful/user_catalog/'.$api_catalog.'/prestashopPlugin';

        $header = "Authorization: Bearer " . $api_token;

        $data = array(
            'shopName' => Configuration::get('PS_SHOP_NAME'),
            'url' => _PS_BASE_URL_.__PS_BASE_URI__
        );
        $data_string = json_encode($data);
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', $header));

        curl_setopt($ch, CURLOPT_POST, count($data));
        $data = curl_exec($ch);

        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //$curl_error = curl_error($ch);
        curl_close($ch);
        if ($http_code != 200) {
            $logMsg = 'connectUserCatalog - http_code : ' . $http_code . ' - url : ' . $url . ' data : ' . $data;
            BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
        }
        if ($http_code === 200) {
            $data = json_decode($data);
        }
        $ret['http_code'] = $http_code;
        $ret['data'] = $data;
        return $ret;
    }

    public function getUserCatalogs()
    {
        $ret = [];
        $catalogs = [];
        $base_url = Configuration::get('BDROPPY_API_URL');
        $api_token = Configuration::get('BDROPPY_TOKEN');

        $url = $base_url . '/restful/user_catalog/list';

        $header = "Authorization: Bearer " . $api_token;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'accept: application/json',
            'Content-Type: application/json',
            $header));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        $data  = curl_exec($ch);
        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //$curl_error = curl_error($ch);
        curl_close($ch);
        if ($http_code != 200) {
            $logMsg = 'getUserCatalogs - http_code : ' . $http_code . ' - url : ' . $url . ' data : ' . $data;
            BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
        }
        if ($http_code === 200) {
            $catalogs = json_decode($data);
        }
        $ret['http_code'] = $http_code;
        $ret['catalogs'] = $catalogs;
        return $ret;
    }

    public function getCatalogById2($catalog = null)
    {
        if (is_null($catalog)) {
            return null;
        }

        $base_url = Configuration::get('BDROPPY_API_URL');
        $api_token = Configuration::get('BDROPPY_TOKEN');

        $url = "$base_url/restful/export/api/products.json?user_catalog=$catalog&acceptedlocales=en_US&onlyid=true";

        $header = "Authorization: Bearer " . $api_token;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'accept: application/json',
            'Content-Type: application/json',
            $header));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        $data  = curl_exec($ch);
        $http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        //$curl_error = curl_error($ch);
        curl_close($ch);
        $response = json_decode($data);

        if ($http_code != 200) {
            $logMsg = 'getCatalogById2 - http_code : ' . $http_code . ' - url : ' . $url . ' data : ' . $data;
            BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
        }
        if ($response === 500 || empty($response->userCatalog)) {
            return 500;
        }

        $ids=[];
        foreach ($response->items as $item) {
            $ids[] = $item->refId;
        }
        $response = $response->userCatalog;
        $response->ids = $ids;
        return $response;
    }

    public function modifyGrowingOrder($operations)
    {
        $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><root></root>');
        $operationLock = $xml->addChild('operation');
        $operationLock->addAttribute('type', self::SOLD_API_LOCK_OP);
        $operationSet = $xml->addChild('operation');
        $operationSet->addAttribute('type', self::SOLD_API_SET_OP);
        $operationUnlock = $xml->addChild('operation');
        $operationUnlock->addAttribute('type', self::SOLD_API_UNLOCK_OP);

        foreach ($operations as $op) {
            switch ($op['type']) {
                case self::SOLD_API_LOCK_OP:
                    $model = $operationLock->addChild('model');
                    break;
                case self::SOLD_API_SET_OP:
                    $model = $operationSet->addChild('model');
                    break;
                case self::SOLD_API_UNLOCK_OP:
                    $model = $operationUnlock->addChild('model');
                    break;
            }
            if (isset($model)) {
                $model->addAttribute('stock_id', $op['model_id']);
                $model->addAttribute('quantity', $op['qty']);
                $logMsg = 'Model Ref.ID #'.$op['model_id'].', qty: '.$op['qty'].', operation type: '.$op['type'];
                BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
            } else {
                $logMsg = 'Invalid operation type: ' . $op['type'];
                BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
            }
        }
        $xmlText = $xml->asXML();

        $api_token = Configuration::get('BDROPPY_TOKEN');
        $url = Configuration::get('BDROPPY_API_URL') . '/restful/ghost/orders/sold';
        $header = "Authorization: Bearer " . $api_token;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/xml',
            'Accept: application/xml',
            $header));
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlText);
        $data = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!$this->handleCurlError($httpCode)) {
            return array('curl_error' => 1,'message' => $data);
        }

        $reader = new XMLReader();
        $reader->xml($data);
        $reader->read();
        $this->setGrowingOrderId($reader->getAttribute('order_id'));

        $errors = array();
        $growingOrder = array();
        while ($reader->read()) {
            if ($reader->nodeType == \XMLReader::ELEMENT && $reader->name == 'model') {
                $stock_id                   = $reader->getAttribute('stock_id');
                $growingOrder[ $stock_id ] = array(
                    'stock_id'  => $stock_id,
                    'locked'    => $reader->getAttribute('locked'),
                    'available' => $reader->getAttribute('available'),
                );
            }
        }
        foreach ($operations as $op) {
            if (isset($growingOrder[ $op['model_id'] ])) {
                $product             = $growingOrder[ $op['model_id'] ];
                $success             = true;
                $pending_quantity    = $this->getPendingQtyByRewixModel((int) $op['model_id']);
                $processing_quantity = $this->getProcessingQtyByRewixModel((int) $op['model_id']);

                if ($op['type'] == self::SOLD_API_LOCK_OP &&
                    $product['locked'] < ($pending_quantity + $processing_quantity + $op['qty'])) {
                    $success = false;
                } elseif ($op['type'] == self::SOLD_API_UNLOCK_OP &&
                    $product['locked'] < ($pending_quantity + $processing_quantity - $op['qty'])) {
                    $success = false;
                } elseif ($op['type'] == self::SOLD_API_SET_OP && $product['locked'] < $op['qty']) {
                    $success = false;
                }

                if (! $success) {
                    $logMsg = 'Model Ref.ID #' . $op['model_id'] . ', looked: ' . $product['locked'] .
                        ', qty: ' . $op['qty'] . ', pending: ' . $this->getPendingQtyByRewixModel($stock_id) .
                        ', processing: ' . $this->getPendingQtyByRewixModel($stock_id) . ', operation type: ' .
                        $op['type'] . ' : OPERATION FAILED!';
                    BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
                    $errors[ $op['model_id'] ] = $op['qty'];
                } else {
                    $logMsg = 'Model Ref.ID #' . $op['model_id'] . ', looked: ' . $product['locked'] .
                        ', qty: ' . $op['qty'] . ', pending: ' . $this->getPendingQtyByRewixModel($stock_id) .
                        ', processing: ' . $this->getPendingQtyByRewixModel($stock_id) . ', operation type: ' .
                        $op['type'];
                    BdroppyLogger::addLog(__METHOD__, $logMsg, 3);
                }
            } else {
                $errors[ $op['model_id'] ] = $op['qty'];
            }
        }

        return $errors;
    }

    public function validateOrder($cart)
    {
        $catalog_id =  Configuration::get('BDROPPY_CATALOG');
        if ($catalog_id != '' && $catalog_id != '-1') {
            $logMsg = 'Validating order';
            BdroppyLogger::addLog(__METHOD__, $logMsg, 3);
            $lines = $cart->getProducts(true);

            $operations = array();
            foreach ($lines as $line) {
                if ($line['unity'] == $catalog_id || $line['unity'] == "bdroppy-$catalog_id") {
                    $modelId = 0;
                    $attributeId = (int)$line['product_attribute_id'];
                    $product_isbn = (int)$line['isbn'];
                    $sql = "SELECT * FROM `" . _DB_PREFIX_ . "product_attribute` ".
                        "WHERE id_product_attribute = '$attributeId';";
                    $product_attribute = Db::getInstance()->ExecuteS($sql);
                    if (count($product_attribute)) {
                        $modelId = $product_attribute[0]['isbn'];
                    }
                    if ($product_isbn <= 0) {
                        $product_isbn = (int)$line['product_isbn'];
                    }
                    $rewixId = $modelId > 0 ? $modelId : $product_isbn;
                    //$rewixId = BdroppyRemoteCombination::getRewixModelIdByProductAndModelId($productId);
                    if ($rewixId) {
                        $operation = array(
                            'type' => BdroppyRewixApi::SOLD_API_LOCK_OP,
                            'model_id' => $rewixId,
                            'qty' => (int)$line['cart_quantity']
                        );
                        $operations[] = $operation;
                    } else {
                        $err = 'Model ID Not Found!';
                        throw new Exception($err);
                    }
                }
            }
            $errors = $this->modifyGrowingOrder($operations);
            if (count($operations) > 0) {
                if (isset($errors['curl_error'])) {
                    throw new Exception('Error while placing order (' . $errors['message'] . ').');
                } elseif (count($errors) > 0) {
                    foreach ($errors as $model_id => $qty) {
                        throw new Exception(
                            sprintf(
                                'Error while placing order. Product %s '.
                                'is not available in quantity requested (%d).',
                                $this->getProductNameFromRewixModelId((int)$model_id),
                                $qty
                            )
                        );
                    }
                }
            }
        }
        return true;
    }

    public function sendBdroppyOrder($order)
    {
        $catalog_id =  Configuration::get('BDROPPY_CATALOG');
        if ($catalog_id != '' && $catalog_id != '-1') {
            $logMsg = 'Sending bdroppy order ' . $order->id;
            BdroppyLogger::addLog(__METHOD__, $logMsg, 3);
            $mixed = false;
            $lines = $order->getProductsDetail();
            $rewix_order_key = $catalog_id .$order->id . time();

            $currency = new CurrencyCore($order->id_currency);
            $xml = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8" standalone="yes"?><root></root>');
            $order_list = $xml->addChild('order_list');
            $xmlOrder = $order_list->addChild('order');
            $item_list = $xmlOrder->addChild('item_list');
            $xmlOrder->addChild('key', $rewix_order_key);
            $xmlOrder->addChild('date', str_replace('-', '/', $order->date_add) . ' +0000');
            $xmlOrder->addChild('user_catalog_id', $catalog_id);
            $xmlOrder->addChild('shipping_taxable', $order->total_shipping);
            $xmlOrder->addChild('shipping_currency', $currency->iso_code);
            $xmlOrder->addChild('price_total', $order->total_paid);
            $xmlOrder->addChild('price_currency', $currency->iso_code);

            $rewixProduct = 0;
            foreach ($lines as $line) {
                if ($line['unity'] == $catalog_id || $line['unity'] == "bdroppy-$catalog_id") {
                    $modelId = 0;
                    $attributeId = (int)$line['product_attribute_id'];
                    $product_isbn = (int)$line['isbn'];
                    $sql = "SELECT * FROM `" . _DB_PREFIX_ . "product_attribute` ".
                        "WHERE id_product_attribute = '$attributeId';";
                    $product_attribute = Db::getInstance()->ExecuteS($sql);
                    if (count($product_attribute)) {
                        $modelId = $product_attribute[0]['isbn'];
                    }
                    if ($product_isbn <= 0) {
                        $product_isbn = (int)$line['product_isbn'];
                    }
                    $rewixId = $modelId > 0 ? $modelId : $product_isbn;
                    //$rewixId = BdroppyRemoteCombination::getRewixModelIdByProductAndModelId($productId);
                    if (!$rewixId && $rewixProduct > 0) {
                        $logMsg = 'Order #' . $order->id . ': Mixed Order';
                        BdroppyLogger::addLog(__METHOD__, $logMsg, 3);
                        $mixed = true;
                    }
                    if ($rewixId) {
                        $rewixProduct++;
                        $orderedQty = (int)$line['product_quantity'];
                        $item = $item_list->addChild('item');
                        $item->addChild('price_taxable', $line['price']);
                        $item->addChild('price_currency', $currency->iso_code);
                        $item->addChild('stock_id', $rewixId);
                        $item->addChild('quantity', $orderedQty);
                        $logMsg = 'Creating bdroppy order with model ID#' . $rewixId .' with quantity ' . $orderedQty;
                        BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
                    }
                }
            }
            if ($rewixProduct == 0) {
                return false;
            }
            if ($mixed) {
                $logMsg = 'Order #' . $order->get_order_number() . ': Mixed Order!!!';
                BdroppyLogger::addLog(__METHOD__, $logMsg, 4);
                return false;
            }

            $shippingAddress = new Address($order->id_address_delivery);
            $customer = new Customer((int)($shippingAddress->id_customer));
            $recipient_details = $xmlOrder->addChild('recipient_details');
            $recipient_details->addChild('email', $customer->email);
            $recipient_details->addChild('recipient', $shippingAddress->firstname.' '.
                $shippingAddress->lastname);
            $recipient_details->addChild('careof', $shippingAddress->company);
            $recipient_details->addChild('cfpiva');
            $recipient_details->addChild('customer_key', (int)($order->id_customer));
            $recipient_details->addChild('notes', $order->getFirstMessage());

            $phone = $recipient_details->addChild('phone');
            $phone->addChild('prefix');
            $phone->addChild('number', $shippingAddress->phone);

            $address = $recipient_details->addChild('address');
            $address->addChild('street_type');
            $address->addChild('street_name', $shippingAddress->address1.' '.$shippingAddress->address2);
            $address->addChild('address_number');
            $address->addChild('zip', $shippingAddress->postcode);
            $address->addChild('city', $shippingAddress->city);

            $state = new State($shippingAddress->id_state);
            $address->addChild('province', $state->iso_code);

            $country = new Country($shippingAddress->id_country);
            $address->addChild('countrycode', $country->iso_code);

            $xmlText = $xml->asXML();

            $url = Configuration::get('BDROPPY_API_URL') . '/restful/ghost/orders/0/dropshipping';
            $api_token = Configuration::get('BDROPPY_TOKEN');
            $header = "Authorization: Bearer " . $api_token;

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/xml',
                'Accept: application/xml',
                $header));
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xmlText);
            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $reader = new XMLReader();
            $reader->xml($data);
            $reader->read();
            //$rewixOrder = json_decode($data);

            if (!$this->handleCurlError($httpCode)) {
                return false;
            }
            $logMsg = 'Rewix order key: ' . $rewix_order_key . ' ' . $data;
            BdroppyLogger::addLog(__METHOD__, $logMsg, 3);

            $url = Configuration::get('BDROPPY_API_URL')  . '/restful/ghost/clientorders/clientkey/'.$rewix_order_key;
            $api_token = Configuration::get('BDROPPY_TOKEN');
            $header = "Authorization: Bearer " . $api_token;

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/xml', $header));
            $data = curl_exec($ch);

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpCode == 401) {
                $logMsg = 'Send dropshipping order: UNAUTHORIZED!!';
                BdroppyLogger::addLog(__METHOD__, $logMsg, 4);
                return false;
            } elseif ($httpCode == 500) {
                $logMsg = 'Exception: Order #'.$order->id.' does not exists on rewix platform';
                BdroppyLogger::addLog(__METHOD__, $logMsg, 4);
                $logMsg = 'Bdroppy operation for order #'.$order->id.' failed!!';
                BdroppyLogger::addLog(__METHOD__, $logMsg, 4);

                $association    = new BdroppyRemoteOrder();
                $association->rewix_order_key = $rewix_order_key;
                $association->rewix_order_id = (int) 0;
                $association->ps_order_id = (int) $order->id;
                $association->status = (int) BdroppyRemoteOrder::STATUS_FAILED;
                $association->save();
                return false;
            } elseif ($httpCode != 200) {
                $logMsg = 'Send dropshipping order - Url : ' . $rewix_order_key. ' - ERROR ' . $httpCode;
                BdroppyLogger::addLog(__METHOD__, $logMsg, 4);
                return false;
            }

            $logMsg = 'Bdroppy order created successfully';
            BdroppyLogger::addLog(__METHOD__, $logMsg, 3);

            $reader = new XMLReader();
            $reader->xml($data);
            $doc = new DOMDocument('1.0', 'UTF-8');

            while ($reader->read()) {
                if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'order') {
                    $xmlOrder = simplexml_import_dom($doc->importNode($reader->expand(), true));
                    $rewixOrderId   = (int) $xmlOrder->order_id;
                    $status         = (int) $xmlOrder->status;
                    $orderId        = (int) $order->id;
                    $association    = new BdroppyRemoteOrder();
                    $association->rewix_order_key = $rewix_order_key;
                    $association->rewix_order_id = $rewixOrderId;
                    $association->ps_order_id = $orderId;
                    $association->status = $status;
                    $association->save();
                    $logMsg = "Entry ($rewixOrderId, $orderId) in association table created";
                    BdroppyLogger::addLog(__METHOD__, $logMsg, 3);
                    $logMsg = "Supplier order $rewixOrderId created successfully";
                    BdroppyLogger::addLog(__METHOD__, $logMsg, 3);
                }
            }
        }
    }

    private function handleCurlError($httpCode)
    {
        if ($httpCode == 401) {
            $logMsg = 'UNAUTHORIZED!!';
            BdroppyLogger::addLog(__METHOD__, $logMsg, 4);
            Tools::displayError('You are NOT authorized to access this service. <br/> Please check your configuration'.
                ' in System -> Configuration or contact your supplier.');
            return false;
        } elseif ($httpCode == 0) {
            $logMsg = 'HTTP Error 0!!';
            BdroppyLogger::addLog(__METHOD__, $logMsg, 4);
            Tools::displayError('There has been an error executing the request.<br/> Please check your configuration'.
                ' in System -> Configuration');
            return false;
        } elseif ($httpCode != 200) {
            $logMsg = 'HTTP Error '.$httpCode.'!!';
            BdroppyLogger::addLog(__METHOD__, $logMsg, 4);
            Tools::displayError('There has been an error executing the request.<br/> HTTP Error Code: '.$httpCode);
            return false;
        }
        return true;
    }

    public function setGrowingOrderId($orderId)
    {
        Configuration::updateValue(BdroppyConfigKeys::GROWING_ORDER_ID, $orderId);
    }

    public function getPendingQtyByRewixModel($modelId)
    {
        return $this->getPendingQty($modelId);
    }

    public function getPendingQty($productId)
    {

        if (is_null($this->pendingCache)) {
            $query = "select od.product_id, sum(od.product_quantity) as ordered_qty " .
                "from `"._DB_PREFIX_."order_detail` od " .
                "where od.id_order in (select id_order from `"._DB_PREFIX_."orders` where current_state in " .
                "(select value from `"._DB_PREFIX_."configuration` where name in ('PS_OS_CHEQUE', 'PS_OS_PAYMENT', " .
                "'PS_OS_BANKWIRE', 'PS_OS_WS_PAYMENT', 'PS_OS_COD_VALIDATION'))) " .
                "and od.id_order not in (select x.ps_order_id from `"._DB_PREFIX_."bdroppy_remoteorder` x) " .
                "group by od.product_id ";
            $this->pendingCache = Db::getInstance()->ExecuteS($query);
        }

        foreach ($this->pendingCache as $pendingItem) {
            if ($pendingItem['product_id'] == $productId) {
                return (int) $pendingItem['ordered_qty'];
            }
        }
        return 0;
    }

    private function getPsModelId($modelId)
    {
        $query = 'select p.ps_product_id ' .
            'from `'._DB_PREFIX_.'bdroppy_remoteproduct` p ' .
            'where p.rewix_product_id = ' . (int) $modelId;

        $rewixProducts = Db::getInstance()->ExecuteS($query);

        foreach ($rewixProducts as $rewixProduct) {
            return $rewixProduct['ps_product_id'];
        }

        $logMsg = $modelId . " not found.";
        BdroppyLogger::addLog(__METHOD__, $logMsg, 4);
    }

    private function getRewixModelId($modelId)
    {
        $query = 'select p.rewix_product_id ' .
            'from `'._DB_PREFIX_.'bdroppy_remoteproduct` p ' .
            'where p.ps_product_id = ' . (int) $modelId;

        $products = Db::getInstance()->ExecuteS($query);

        foreach ($products as $product) {
            return $product['rewix_product_id'];
        }

        $logMsg = "Rewix " . $modelId . " not found.";
        BdroppyLogger::addLog(__METHOD__, $logMsg, 4);
    }

    private function getProductNameFromRewixModelId($modelId)
    {
        $query = 'select p.rewix_product_id ' .
            'from `'._DB_PREFIX_.'bdroppy_remoteproduct` p ' .
            'where p.ps_product_id = ' . (int) $modelId;

        $products = Db::getInstance()->ExecuteS($query);

        foreach ($products as $product) {
            $prd = new Product($product['rewix_product_id']);
            if ($prd) {
                return $prd->name;
            }
        }

        $logMsg = "Rewix " . $modelId . " not found.";
        BdroppyLogger::addLog(__METHOD__, $logMsg, 4);
    }

    public function getProcessingQtyByRewixModel($modelId)
    {
        return $this->getProcessingQty($modelId);
    }

    public function getProcessingQty($productId)
    {
        if (is_null($this->pendingCache)) {
            $query = "select od.product_id, sum(od.product_quantity) as ordered_qty " .
                "from `"._DB_PREFIX_."order_detail` od " .
                "where od.id_order in (select id_order from `"._DB_PREFIX_."orders` where current_state in " .
                "(select value from `"._DB_PREFIX_."configuration` where name in ('PS_OS_PREPARATION'))) " .
                "and od.id_order not in (select r.ps_order_id from `"._DB_PREFIX_."bdroppy_remoteorder` r) " .
                "group by od.product_id ";
            $this->pendingCache = Db::getInstance()->ExecuteS($query);
        }

        foreach ($this->pendingCache as $pendingItem) {
            if ($pendingItem['product_id'] == $productId) {
                return (int) $pendingItem['ordered_qty'];
            }
        }
        return 0;
    }

    public function updateOrderStatuses()
    {
        $logMsg = 'Order statuses update procedures STARTED!';
        BdroppyLogger::addLog(__METHOD__, $logMsg, 3);

        $orders = BdroppyRemoteOrder::getOrdersByNotStatus((int) BdroppyRemoteOrder::STATUS_DISPATCHED);

        foreach ($orders as $order) {
            $ps_order = new Order($order['ps_order_id']);
            if (!isset($ps_order->id_cart) && !isset($ps_order->id_customer)) {
                continue;
            }

            $logMsg = 'Processing Order_id: #' . (int) $order['ps_order_id'];
            BdroppyLogger::addLog(__METHOD__, $logMsg, 3);

            $url = Configuration::get('BDROPPY_API_URL')  . '/restful/ghost/clientorders/clientkey/'.
                $order['rewix_order_key'];
            $api_token = Configuration::get('BDROPPY_TOKEN');
            $header = "Authorization: Bearer " . $api_token;

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array('Accept: application/xml', $header));
            $data = curl_exec($ch);

            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode == 401) {
                $logMsg = 'updateOrderStatuses - UNAUTHORIZED!!';
                BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
                return false;
            } elseif ($httpCode == 500) {
                $logMsg = 'Exception: Order #' . $order['ps_order_id'] . ' does not exists on rewix platform';
                BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
            } elseif ($httpCode != 200) {
                $logMsg = 'ERROR ' . $httpCode . ' ' . $data . ' - Exception: Order #' . $order['ps_order_id'];
                BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
            } else {
                $reader = new \XMLReader();
                $reader->xml($data);
                $doc = new \DOMDocument('1.0', 'UTF-8');

                while ($reader->read()) {
                    if ($reader->nodeType == \XMLReader::ELEMENT && $reader->name == 'order') {
                        $xml_order = simplexml_import_dom($doc->importNode($reader->expand(), true));
                        $status    = (int) $xml_order->status;
                        $order_id  = (int) $xml_order->order_id;
                        $logMsg = 'Order_id: #'.$order_id.' NEW Status:'.$status.' OLD Status '.$order['status'];
                        BdroppyLogger::addLog(__METHOD__, $logMsg, 3);
                        if ((int) $order['status'] != $status) {
                            $association    = new BdroppyRemoteOrder($order['id']);
                            $association->rewix_order_id = $order_id;
                            $association->status = $status;
                            $association->save();

                            $logMsg = 'Order status Update: WC ID #' .
                                $order->wc_order_id . ': new status [' . $status . ']';
                            BdroppyLogger::addLog(__METHOD__, $logMsg, 3);

                            if ($status == BdroppyRemoteOrder::STATUS_DISPATCHED) {
                                $carrier = new Carrier($ps_order->id_carrier, $ps_order->id_lang);
                                $arr = explode("=", $xml_order->tracking_url);
                                $tracking_number = pSQL($arr[count($arr)-1]);
                                $carrier_url = str_replace($tracking_number, "@", $xml_order->tracking_url);
                                if ($carrier->url == '') {
                                    $carrier->url = $carrier_url;
                                    $carrier->update();
                                }

                                $ps_order->setCurrentState((int)Configuration::get('PS_OS_SHIPPING'));
                                //$shipping_number = pSQL($xml_order->tracking_url);
                                $ps_order->setWsShippingNumber($tracking_number);
                                $ps_order->save();
                            }
                        }
                    }
                }
            }
        }
        $logMsg = 'Order statuses update procedures COMPLETED!';
        BdroppyLogger::addLog(__METHOD__, $logMsg, 3);
        return true;
    }

    public function syncBookedProducts()
    {
        $bookedProducts = $this->getGrowingOrderProducts();
        var_dump('syncBookedProducts', $bookedProducts, '*********************************');

        $logMsg = 'Syncing booked products';
        BdroppyLogger::addLog(__METHOD__, $logMsg, 1);

        $locked = 0;
        $operations = array();
        if ($bookedProducts) {
            foreach ($bookedProducts as $bookedProduct) {
                $productId = BdroppyRemoteCombination::getIdByRewixId($bookedProduct['stock_id']);
                $productId = 0;
                $sql = "SELECT id_product FROM `" . _DB_PREFIX_ . "product_attribute` " .
                    "WHERE isbn = '". (int)$bookedProduct['stock_id'] ."';";
                $product_attribute = Db::getInstance()->ExecuteS($sql);
                if ($product_attribute) {
                    $productId = $product_attribute[0]['id_product'];
                }
                if ($productId > 0) {
                    $locked = $bookedProduct['locked'];
                    //$available = $bookedProduct['available'];

                    $processingQty = $this->getProcessingQty($productId);
                    $pendingQty = $this->getPendingQty($productId);

                    if ($processingQty+$pendingQty > 0 || $locked > 0) {
                        $operation = array(
                            'type' => self::SOLD_API_SET_OP,
                            'model_id' => $bookedProduct['stock_id'],
                            'qty' => $processingQty + $pendingQty
                        );
                        $operations[] = $operation;
                    }
                }
            }

            $logMsg = 'Applying following operations to growing order '.print_r($operations, true);
            BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
            if (sizeof($operations) > 0) {
                $this->modifyGrowingOrder($operations);
            }
        }
        return true;
    }

    public function sendMissingOrders()
    {
        $orderIds = BdroppyRemoteOrder::getMissingOrdersId();
        var_dump('sendMissingOrders', $orderIds, '*********************************');

        foreach ($orderIds as $orderId) {
            $order = new Order(isset($orderId['id_order']) ? $orderId['id_order'] : $orderId);
            $this->sendBdroppyOrder($order);
        }
    }

    public function getGrowingOrderProducts()
    {
        $url = Configuration::get('BDROPPY_API_URL')  . '/restful/ghost/orders/dropshipping/locked/';
        $logMsg = 'Retrieving growing order ' . $url;
        BdroppyLogger::addLog(__METHOD__, $logMsg, 1);
        $api_token = Configuration::get('BDROPPY_TOKEN');
        $header = "Authorization: Bearer " . $api_token;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/xml',
            'Accept: application/xml',
            $header));
        $data = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!$this->handleCurlError($httpCode)) {
            return array('curl_error' => 1,'message' => $data);
        }

        $reader = new XMLReader();
        $reader->xml($data);

        //$doc = new DOMDocument('1.0', 'UTF-8');
        $reader->read();
        $this->setGrowingOrderId($reader->getAttribute('order_id'));

        $bookedProducts = array();

        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'model') {
                $bookedProducts[] = array(
                    'stock_id' => $reader->getAttribute('stock_id'),
                    'locked' => $reader->getAttribute('locked'),
                    'available' => $reader->getAttribute('available')
                );
            }
        }
        return $bookedProducts;
    }
}
