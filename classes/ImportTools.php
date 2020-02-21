<?php
/**
 * NOTICE OF LICENSE.
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    Zero11
 * @copyright 2015-2016 Zero11 S.r.l.
 * @license   Proprietary
 */

include_once dirname(__FILE__) . '/ConfigKeys.php';
include_once dirname(__FILE__) . '/RemoteProduct.php';
include_once dirname(__FILE__) . '/RemoteCategory.php';
include_once dirname(__FILE__) . '/RemoteCombination.php';
include_once dirname(__FILE__) . '/ImportHelper.php';
include_once dirname(__FILE__) . '/RewixApi.php';

class BdroppyImportTools
{
    const DATA_SOURCE_PATH = 'rewix-sync-products.xml';
    const DATA_SOURCE_INCREMENTAL_PATH = 'rewix-sync-products-incremental.xml';
    
    public static $logger = null;
    public static $products = array();
    public static $brands = array();
    public static $categories = array();
    public static $subcategories = array();
    public static $genders = array();
    public static $partners = array();

    private static $categoryStructure = null;

    /**
     * @return FileLogger
     */
    public static function getLogger()
    {
        if (self::$logger == null) {
            $verboseLog = (bool)Configuration::get(BdroppyConfigKeys::VERBOSE_LOG);
            self::$logger = new FileLogger($verboseLog ? FileLogger::DEBUG : FileLogger::ERROR);
            $filename = _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'bdroppy-import.log';
            self::$logger->setFilename($filename);
        }

        return self::$logger;
    }

    public static function getProducts()
    {
        return self::$products;
    }

    public static function getBrands()
    {
        return self::$brands;
    }

    public static function getCategories()
    {
        return self::$categories;
    }

    public static function getSubcategories()
    {
        return self::$subcategories;
    }

    public static function getGenders()
    {
        return self::$genders;
    }

    public static function getPartners()
    {
        return self::$partners;
    }

    public static function tryLock()
    {
        //get XML path and use 'lock':
        $lock = self::getLockPath();

        $lockFile = fopen($lock, 'w+');
        if (flock($lockFile, LOCK_EX)) {
            return $lockFile;
        } else {
            fclose($lockFile);

            return false;
        }
    }

    private static function getLockPath()
    {
        $dir = _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'download' . DIRECTORY_SEPARATOR . 'import';
        if (!is_dir($dir)) {
            mkdir($dir);
        }

        $xmlPath = $dir . DIRECTORY_SEPARATOR . 'rewix.lock';

        return $xmlPath;
    }

    /**
     * @return string
     */
    public static function getXmlPath($incremental = false)
    {
        $dir = _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'download' . DIRECTORY_SEPARATOR . 'import';
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        
        if ($incremental == false) {
            $path = $dir . DIRECTORY_SEPARATOR . self::DATA_SOURCE_PATH;
        } else {
            $path = $dir . DIRECTORY_SEPARATOR . self::DATA_SOURCE_INCREMENTAL_PATH;
        }
        
        return $path;
    }

    /** METHODS ABOUT DOWNLOAD/UPDATE XML **/

    /**
     * @param int $time
     *
     * @return bool|string if successful the path of newly downloaded xml source file
     */
    public static function getXmlSource($since = null)
    {
        $path = self::getXmlPath(!empty($since));
        $filemtime = @filemtime($path); // returns false if file doesn't exist
        if (empty($since)) {
            $life = 7200; // update full catalog every two hours
        } else {
            $life = 2 * 60; // update incremental data every 2 minutes
        }
        if (!$filemtime || (time() - $filemtime) >= $life) {
            //self::getLogger()->logError('XML Source is too old or does not exist. Downloading a new source');
            $path = self::downloadXmlSource($since);
        }
        if (!$path) {
            return false;
        }
        $copy = substr_replace($path, uniqid('-') . '.xml', -4);
        if (copy($path, $copy)) {
            return $copy;
        }
        return false;
    }

    /**
     * @return string
     */
    private static function downloadXmlSource($since = null)
    {
        $logger = self::getLogger();
        
        @set_time_limit(3600);
        @ini_set('memory_limit', '1024M');

        $path = self::getXmlPath(!empty($since));

        //$logger->logInfo('Loading XML Data: ' . $path);
        //$logger->logInfo('Removing old XML Data.');
        $filemtime = @filemtime($path); // returns false if file doesn't exist
        if (empty($since)) {
            $life = 5400; // allow update full catalog every 1.5 hours
        } else {
            $life = 60; // allow update incremental data every 1 minutes
        }
        
        if (!$filemtime || (time() - $filemtime) >= $life) {
            // remove old .xml data
            if (file_exists($path)) {
                unlink($path);
            }
        } else {
            return $path;
        }
        
        $locale = Configuration::get(BdroppyConfigKeys::LOCALE);
        $username = Configuration::get(BdroppyConfigKeys::APIKEY);
        $password = Configuration::get(BdroppyConfigKeys::PASSWORD);
        $websiteUrl = Configuration::get(BdroppyConfigKeys::WEBSITE_URL);
        $url = "{$websiteUrl}/restful/export/api/products.xml?acceptedlocales={$locale}&addtags=true" . ( empty($since) ? '' : '&since=' . urlencode($since) );
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERPWD, $username . ':' . $password);
        $data = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        if ($httpCode == 401) {
            //$logger->logDebug('Error loading XML Data: You are NOT authorized to access this service.');
            return false;
        } elseif ($httpCode == 0) {
            //$logger->logDebug('Error loading XML Data: There has been an error executing the request. Error:' . $curlError);
            return false;
        } elseif ($httpCode == 412) {
            Configuration::updateValue(BdroppyConfigKeys::LAST_QUANTITIES_SYNC, null, null, 0, 0);
            //$logger->logDebug('Error loading XML Data: Incremental sync too late. Running full sync at next execution');
            return false;
        } elseif ($httpCode != 200) {
            //$logger->logDebug('Error loading XML Data: There has been an error executing the request: ' . $httpCode);
            return false;
        }
        
        if (file_put_contents($path, $data) === false) {
            //$logger->logError('Error saving XML Data.');
        }

        // remove files older than 1 hour
        $globs = substr_replace($path, '-*', -4);
        foreach (glob($globs) as $filename) {
            if ((time() - filemtime($filename)) > 3600) {
                unlink($filename);
            }
        }
        
        return $path;
    }

    private static function getProductXML(XMLReader $reader)
    {
        $doc = new DOMDocument('1.0', 'UTF-8');
        $xmlProduct = simplexml_import_dom($doc->importNode($reader->expand(), true));

        return $xmlProduct;
    }

    public static function updateAllQuantities()
    {
        set_time_limit(3600);
        $path = self::getXmlSource();
        $lastUpdate = null;
        
        $logger = self::getLogger();
        //$logger->logDebug('Starting update quantity procedure for all products from source file ' . $path);

        try {
            if (!$path) {
                throw new Exception('Cannot find xml source file.');
            }

            $reader = new XMLReader();
            $reader->open($path);

            $xmlProducts = array();
            $xmlModels = array();

            while ($reader->read()) {
                if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'item') {
                    $xmlProduct = self::getProductXML($reader);
                    
                    $id = BdroppyRemoteProduct::getIdByRewixId((int) $xmlProduct->id, true);
                    if ($id != 0) {
                            $rewixProduct = new BdroppyRemoteProduct($id);

                            if ($rewixProduct && $rewixProduct->simple) {
                                $product = new Product($rewixProduct->ps_product_id);
                                self::checkNosizeModel($xmlProduct, $product);
                        }
                    }
                       
                    $xmlProducts[(int)$xmlProduct->id] = (int)$xmlProduct->availability;
                    $models = $xmlProduct->models;
                    foreach ($models->model as $xmlModel) {
                        $xmlModels[(int)$xmlModel->id] = (int)$xmlModel->availability;
                    }
                } elseif ($reader->name == 'page') {
                    $lastUpdate = $reader->getAttribute('lastUpdate');
                }
            }

            $remoteProducts = BdroppyRemoteProduct::getByStatus(BdroppyRemoteProduct::SYNC_STATUS_UPDATED, 0);
            //$logger->logDebug(count($remoteProducts) . ' products which quantities will be updated.');
            $productsCount = 0;

            foreach ($remoteProducts as $remoteProduct) {
                if ($remoteProduct['imported'] == 1) {
                    $models = BdroppyRemoteCombination::getByRewixProductId($remoteProduct['rewix_product_id']);
                    $productsCount += 1;
                
                    if ($remoteProduct['simple']) {
                        $availability = StockAvailable::getQuantityAvailableByProduct($remoteProduct['ps_product_id']);
                        if (! array_key_exists($remoteProduct['rewix_product_id'], $xmlProducts)) {
                            StockAvailable::setQuantity(
                                $remoteProduct['ps_product_id'],
                                0,
                                0
                            );
                            //$logger->logDebug('Product ' . $remoteProduct['ps_product_id'] . ' is no more available');
                        } else if ($availability != $xmlProducts[$remoteProduct['rewix_product_id']]) {
                            StockAvailable::setQuantity(
                                $remoteProduct['ps_product_id'],
                                0,
                                $xmlProducts[$remoteProduct['rewix_product_id']]
                            );
                            //$logger->logDebug('Product ' . $remoteProduct['ps_product_id'] . ' quantity updated: ' . $availability . ' -> ' . $xmlProducts[$remoteProduct['rewix_product_id']]);
                        }
                    } else {
                        $productQuantity = 0;
                        $oldQuantity = 0;
                        $qtyChanged = false;
                        foreach ($models as $model) {
                            $modelId = $model['rewix_model_id'];
                            $quantity = 0;
                            if (isset($xmlModels[$modelId]) && $xmlModels[$modelId] > 0) {
                                $quantity = (int) $xmlModels[$modelId];
                            }
                            $availability = StockAvailable::getQuantityAvailableByProduct($remoteProduct['ps_product_id'], $model['ps_model_id']);
                            if ($availability != $quantity) {
                                $qtyChanged = true;
                                StockAvailable::setQuantity(
                                    $remoteProduct['ps_product_id'],
                                    $model['ps_model_id'],
                                    $quantity
                                );
                                //$logger->logDebug('Product ' . $remoteProduct['ps_product_id'] . ' - ' . $model['ps_model_id'] . ' quantity updated: ' . $availability . ' -> ' . $quantity);
                            }
                            $oldQuantity += $availability;
                            $productQuantity += $quantity;
                        }
                        if ($qtyChanged) {
                            StockAvailable::setQuantity(
                                $remoteProduct['ps_product_id'],
                                0,
                                $productQuantity
                            );
                            //$logger->logDebug('Product ' . $remoteProduct['ps_product_id'] . ' quantity updated: ' . $oldQuantity . ' -> ' . $productQuantity);
                        }
                    }
                }
            }

            //$logger->logDebug('All products (' . $productsCount . ') have been successfully updated. Update lastUpdate ' . $lastUpdate);

            Configuration::updateValue(BdroppyConfigKeys::LAST_QUANTITIES_SYNC, $lastUpdate, null, 0, 0);

            if (file_exists($path)) {
                unlink($path);
            }
        } catch (Exception $e) {
            //$logger->logError('Error during the update quantity procedure: ' . $e->getMessage());
        }
    }

    public static function updateAllQuantitiesIncremental()
    {
        $logger = self::getLogger();

        //$logger->logDebug('Starting Incremental Sync Quantities');

        $path =  self::getXmlSource(Configuration::get(BdroppyConfigKeys::LAST_QUANTITIES_SYNC));
        if ($path == false) {
            return false;
        }
        $lastUpdate = null;
        $reader = new XMLReader();
        $read = $reader->open($path);
        if ($read == false) {
            if (file_exists($path)) {
                unlink($path);
            }
            Configuration::updateValue(BdroppyConfigKeys::LAST_QUANTITIES_SYNC, null, null, 0, 0);
            throw new Exception('Cannot read xml file ' . $path);
        }
        
        //$logger->logInfo('Loading Products in Prestashop');
        
        $xmlProducts = array();
        //$xmlModels = array();

        //$logger->logDebug('Reading Supplier XML Data');

        while ($reader -> read()) {
            if ($reader -> nodeType == XMLReader::ELEMENT) {
                if ($reader->name == 'item') {
                    $xmlProduct = self::getProductXML($reader);
                    $xmlProducts[(int)$xmlProduct->id] = array(
                        'stock' => (int)$xmlProduct->availability,
                        'models' => array()
                    );
                    $models = $xmlProduct->models;
                    foreach ($models->model as $xmlModel) {
                        $xmlProducts[(int)$xmlProduct->id]['models'][(int)$xmlModel->id] = (int)$xmlModel->availability;
                    }
                } elseif ($reader->name == 'page') {
                    $lastUpdate = $reader->getAttribute('lastUpdate');
                }
            }
        }
        $reader->close();
        if (file_exists($path)) {
            unlink($path);
        }

        //$logger->logDebug('Syncing Models');

        //self::getLogger()->logDebug(count($xmlProducts) . ' products which quantities will be updated.');
        $productsCount = 0;

        foreach ($xmlProducts as $key => $xmlProduct) {
            $id = BdroppyRemoteProduct::getIdByRewixId($key);
            if ($id) {
                $productsCount += 1;
                $product = new BdroppyRemoteProduct($id);
                if ($product->simple) {
                    $availability = StockAvailable::getQuantityAvailableByProduct($product->ps_product_id);
                    if ($availability != $xmlProduct['stock']) {
                        StockAvailable::setQuantity(
                            $product->ps_product_id,
                            0,
                            $xmlProduct['stock']
                        );
                        //$logger->logDebug('Product ' . $product->ps_product_id . ' quantity updated: ' . $availability . ' -> ' .$xmlProduct['stock']);
                    }
                } else {
                    $productQuantity = 0;
                    $oldQuantity = 0;
                    $qtyChanged = false;
                    $models = $xmlProduct['models'];
                    foreach ($models as $mkey => $qty) {
                        $modelId = BdroppyRemoteCombination::getPsModelIdByRewixProductAndModelId($key, $mkey);
                        if ($modelId) {
                            $availability = StockAvailable::getQuantityAvailableByProduct($product->ps_product_id, $modelId);
                            if ($availability != $qty) {
                                $qtyChanged = true;
                                StockAvailable::setQuantity(
                                    $product->ps_product_id,
                                    $modelId,
                                    $qty
                                );
                                //$logger->logDebug('Model ID #'.$key.' new quantity = '.$qty);
                            }
                            $oldQuantity += $availability;
                            $productQuantity += $qty;
                        }
                    }
                    if ($qtyChanged) {
                        StockAvailable::setQuantity(
                            $product->ps_product_id,
                            0,
                            $productQuantity
                        );
                        //$logger->logDebug('Product ' . $product->ps_product_id . ' quantity updated: ' . $oldQuantity . ' -> ' . $productQuantity);
                    }
                }
            }
        }

        //$logger->logInfo('Competed Incremental Sync Quantities');

        Configuration::updateValue(BdroppyConfigKeys::LAST_QUANTITIES_SYNC, $lastUpdate, null, 0, 0);

        if (file_exists($path)) {
            unlink($path);
        }
        return true;
    }
    
    public static function processImportQueue()
    {
        $productIds = BdroppyRemoteProduct::getIdsByStatus(BdroppyRemoteProduct::SYNC_STATUS_QUEUED, 30, 4);
        if (count($productIds) > 0) {
            self::importProducts($productIds);
        }
    }

    /**
     * @param $productIds array product IDs to be imported
     */
    public static function importProducts($productIds)
    {
        set_time_limit(3600);
        $path = self::getXmlSource();
        $failedProducts = array();
        $lastUpdate = null;
        
        //self::getLogger()->logDebug('Starting import procedure for ' . count($productIds) . ' from source file ' . $path);

        try {
            if (!$path) {
                throw new Exception('Cannot find xml source file.');
            }

            $reader = new XMLReader();
            $reader->open($path);

            $counterProduct = 0;
            $counterModel = 0;

            while ($reader->read()) {
                if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'item') {
                    $xmlProduct = self::getProductXML($reader);
                    if (in_array((int)$xmlProduct->id, $productIds)) {
                        // Product to import founded!
                        //self::getLogger()->logDebug($xmlProduct->id . ' is in queue ready to be imported.');

                        try {
                            $result = self::importProduct($xmlProduct);
                            if ($result > 0) {
                                $counterModel += $result;
                                ++$counterProduct;
                                //self::getLogger()->logDebug($xmlProduct->id . ' has been successfully imported.');
                                Configuration::updateValue(BdroppyConfigKeys::LAST_QUANTITIES_SYNC, $lastUpdate, null, 0, 0);
                            }
                        } catch (Exception $e) {
                            //self::getLogger()->logError('Error import product ' . $xmlProduct->id . ': ' . $e->getMessage());
                            $failedProducts[] = array('id' => (int)$xmlProduct->id, 'message' => $e->getMessage());
                        }
                        // Remove the imported id from the list
                        unset($productIds[array_search((int)$xmlProduct->id, $productIds)]);
                    }
                } elseif ($reader->name == 'page') {
                    $lastUpdate = $reader->getAttribute('lastUpdate');
                    $currentLastUpdate = Configuration::get(BdroppyConfigKeys::LAST_QUANTITIES_SYNC);
                    //If we are out of sync compared to full catalog download we do not override the update date
                    if (empty($currentLastUpdate)) {
                        $lastUpdate = null;
                    } elseif (strcmp($currentLastUpdate, $lastUpdate) < 0) {
                        $lastUpdate = $currentLastUpdate;
                    }
                }
            }
            //self::getLogger()->logDebug($counterProduct . ' products have been successfully imported');
        } catch (Exception $e) {
            //self::getLogger()->logError('Error during the import procedure: ' . $e->getMessage());
        }

        // TODO: delete all imported images

        // if a product is ignored and it is already imported, it's not available from upstream anymore
        self::dequeueProducts($productIds);
        // if a product fails to be import, increments priority by 1 and save.
        self::incrementPriority($failedProducts);
        self::$categoryStructure = null;
        
        Configuration::updateValue(BdroppyConfigKeys::LAST_IMPORT_SYNC, (int) time(), null, 0, 0);
    }

    public static function importProduct($item, $default_lang)
    {
        try {
            @set_time_limit(3600);
            @ini_set('memory_limit', '1024M');

            $xmlProduct = false;
            $product_id = $item['rewix_product_id'];
            $catalog_id = $item['rewix_catalog_id'];

            $url = Configuration::get('BDROPPY_API_URL') . "/restful/product/$product_id/usercatalog/$catalog_id";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5000);
            curl_setopt($ch, CURLOPT_USERPWD, Configuration::get('BDROPPY_API_KEY') . ':' . Configuration::get('BDROPPY_API_PASSWORD'));
            $data = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            //var_dump($product_id, $catalog_id, $http_code, $data);die;
            if ($http_code === 200) {
                $xmlProduct = json_decode($data);
            }
            if($xmlProduct) {
                $refId = (int)$xmlProduct->id;
                $sku = (string)$xmlProduct->code;
                $remoteProduct = BdroppyRemoteProduct::fromRewixId($refId);

                $product = new Product($remoteProduct->ps_product_id);
                //echo "<pre>";var_dump($refId, $sku, $remoteProduct, $product);die;

                //self::getLogger()->logDebug('Importing parent product ' . $sku . ' with id ' . $xmlProduct->id);

                // populate general common fields
                $product = self::populateProductAttributes($xmlProduct, $product, $default_lang);
                if (self::checkSimpleImport($xmlProduct)) {
                    //self::getLogger()->logDebug('Product ' . $sku . ' with id ' . $xmlProduct->id . ' will be imported as simple product');
                    $product = self::importSimpleProduct($xmlProduct, $product);
                    $remoteProduct->simple = 1;
                    $remoteProduct->save();
                } else {
                    $product = self::importModels($xmlProduct, $product);
                }
                $product->save();
                $res = Db::getInstance()->update('bdroppy_remoteproduct', array('ps_product_id'=>$product->id), 'id = '.$item['id']);

                if (Configuration::get('BDROPPY_IMPORT_IMAGE')) {
                    //self::getLogger()->logDebug('Importing images for product ' . $product->id . ' (' . $xmlProduct->id . ')');
                    self::importProductImages($xmlProduct, $product, Configuration::get('BDROPPY_IMPORT_IMAGE'));
                }

                self::updateImportedProduct($refId, $product->id);

                return 1;
            }
        } catch (PrestaShopException $e) {
            var_dump(1, $e->getMessage(), $e);
        }
    }

    private static function incrementPriority($products)
    {
        $prods = '';
        foreach ($products as $product) {
            $p = BdroppyRemoteProduct::fromRewixId($product['id']);
            $p->priority = $p->priority + 1;
            $p->reason = $product['message'];
            $p->imported = 0;
            $p->last_sync_date = date('Y-m-d H:i:s');
            $p->save();

            $prods .= $product['id'] . ', ';
        }
        if (Tools::strlen($prods) > 0) {
            //self::getLogger()->logWarning('Incremented priority to products ' . $prods . 'due to errors in import procedure');
        }
    }

    private static function dequeueProducts($products)
    {
        $prods = '';
        foreach ($products as $id) {
            $prods .= $id . ', ';
            BdroppyRemoteProduct::deleteByRewixId($id);
        }
        if (Tools::strlen($prods) > 0) {
            //self::getLogger()->logWarning('Removed from queue ' . $prods . 'not available upstream.');
        }
    }

    private static function getTagValues($tag, $default_lang)
    {
        $value = self::stripTagValues((string)$tag->value);
        $translation = "";
        if(isset($tag->translations->{$default_lang}))
            $translation = self::stripTagValues((string)$tag->translations->{$default_lang});

        return array(
            'value'       => $value,
            'translation' => Tools::strlen($translation) > 0 ? $translation : $value,
        );
    }

    protected static function get_best_path($tgt_width, $tgt_height, $path_infos)
    {
        $path_infos = array_reverse($path_infos);
        $path = '';
        foreach ($path_infos as $path_info) {
            list($width, $height, $path) = $path_info;
            if ($width >= $tgt_width && $height >= $tgt_height) {
                return $path;
            }
        }

        return $path;
    }

    function copyImg($id_entity, $id_image, $url, $entity = 'products', $regenerate = true) {
        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'ps_import');
        $watermark_types = explode(',', Configuration::get('WATERMARK_TYPES'));

        switch ($entity) {
            default:
            case 'products':
                $image_obj = new Image($id_image);
                $path = $image_obj->getPathForCreation();

                break;
            case 'categories':
                $path = _PS_CAT_IMG_DIR_ . (int) $id_entity;

                break;
            case 'manufacturers':
                $path = _PS_MANU_IMG_DIR_ . (int) $id_entity;

                break;
            case 'suppliers':
                $path = _PS_SUPP_IMG_DIR_ . (int) $id_entity;

                break;
            case 'stores':
                $path = _PS_STORE_IMG_DIR_ . (int) $id_entity;

                break;
        }

        $url = urldecode(trim($url));
        $parced_url = parse_url($url);

        if (isset($parced_url['path'])) {
            $uri = ltrim($parced_url['path'], '/');
            $parts = explode('/', $uri);
            foreach ($parts as &$part) {
                $part = rawurlencode($part);
            }
            unset($part);
            $parced_url['path'] = '/' . implode('/', $parts);
        }

        if (isset($parced_url['query'])) {
            $query_parts = array();
            parse_str($parced_url['query'], $query_parts);
            $parced_url['query'] = http_build_query($query_parts);
        }

        if (!function_exists('http_build_url')) {
            require_once _PS_TOOL_DIR_ . 'http_build_url/http_build_url.php';
        }

        $url = http_build_url('', $parced_url);

        $orig_tmpfile = $tmpfile;

        if (Tools::copy($url, $tmpfile)) {
            // Evaluate the memory required to resize the image: if it's too much, you can't resize it.
            if (!ImageManager::checkImageMemoryLimit($tmpfile)) {
                @unlink($tmpfile);

                return false;
            }

            $tgt_width = $tgt_height = 0;
            $src_width = $src_height = 0;
            $error = 0;
            ImageManager::resize($tmpfile, $path . '.jpg', null, null, 'jpg', false, $error, $tgt_width, $tgt_height, 5, $src_width, $src_height);
            $images_types = ImageType::getImagesTypes($entity, true);

            if ($regenerate) {
                $previous_path = null;
                $path_infos = array();
                $path_infos[] = array($tgt_width, $tgt_height, $path . '.jpg');
                foreach ($images_types as $image_type) {
                    $tmpfile = self::get_best_path($image_type['width'], $image_type['height'], $path_infos);

                    if (ImageManager::resize(
                        $tmpfile,
                        $path . '-' . stripslashes($image_type['name']) . '.jpg',
                        $image_type['width'],
                        $image_type['height'],
                        'jpg',
                        false,
                        $error,
                        $tgt_width,
                        $tgt_height,
                        5,
                        $src_width,
                        $src_height
                    )) {
                        // the last image should not be added in the candidate list if it's bigger than the original image
                        if ($tgt_width <= $src_width && $tgt_height <= $src_height) {
                            $path_infos[] = array($tgt_width, $tgt_height, $path . '-' . stripslashes($image_type['name']) . '.jpg');
                        }
                        if ($entity == 'products') {
                            if (is_file(_PS_TMP_IMG_DIR_ . 'product_mini_' . (int) $id_entity . '.jpg')) {
                                unlink(_PS_TMP_IMG_DIR_ . 'product_mini_' . (int) $id_entity . '.jpg');
                            }
                            if (is_file(_PS_TMP_IMG_DIR_ . 'product_mini_' . (int) $id_entity . '_' . (int) Context::getContext()->shop->id . '.jpg')) {
                                unlink(_PS_TMP_IMG_DIR_ . 'product_mini_' . (int) $id_entity . '_' . (int) Context::getContext()->shop->id . '.jpg');
                            }
                        }
                    }
                    if (in_array($image_type['id_image_type'], $watermark_types)) {
                        Hook::exec('actionWatermark', array('id_image' => $id_image, 'id_product' => $id_entity));
                    }
                }
            }
        } else {
            @unlink($orig_tmpfile);

            return false;
        }
        unlink($orig_tmpfile);

        return true;
    }

    private static function importProductImages($xmlProduct, $product, $count)
    {
        try {
            $imageCount = 1;
            $websiteUrl = 'https://branddistributionproddia.blob.core.windows.net/storage-foto-dev/prod/';
            if(strpos(Configuration::get('BDROPPY_API_URL'),'dev') !== false){
                $websiteUrl = "https://branddistributionproddia.blob.core.windows.net/storage-foto-dev/prod/";
            }else{
                $websiteUrl = "https://branddistributionproddia.blob.core.windows.net/storage-foto/prod/";
            }
            $product->deleteImages();

            $i = 0;
            foreach ($xmlProduct->pictures as $image) {
                $imageUrl = "{$websiteUrl}{$image->url}";

                $ch = curl_init($imageUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $content = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                if ($httpCode != 200) {
                    //self::getLogger()->logDebug('Error loading Image: There has been an error executing the request: ' . $httpCode . '. Error:' . $curlError);
                } else {
                    $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'bdroppy_import');
                    $handle = fopen($tmpfile, "w");
                    fwrite($handle, $content);
                    fclose($handle);
                    $image = new Image();
                    $image->id_product = $product->id;
                    $image->position = $imageCount;
                    $image->cover = (int)$imageCount === 1;

                    if (($image->validateFields(false, true)) === true && ($image->validateFieldsLang(
                            false,
                            true
                        )) === true && $image->add()
                    ) {
                        if (!BdroppyImportHelper::copyImg($product->id, $image->id, $tmpfile, 'products', true)) {
                            $image->delete();
                        } else {
                            $imageCount++;
                        }
                    }
                    @unlink($tmpfile);
                }
                $i++;
                if($count != 'all') {
                    if($i >= $count)
                        break;
                }
            }
        } catch (PrestaShopException $e) {
            var_dump(5, $e->getMessage(), $e);
        }
    }

    /**
     * @param $tags
     *
     * @return array
     */
    private static function getCategoryIds($tags)
    {
        $rootCategory = Category::getRootCategory();
        $categoryIds = array($rootCategory->id);
        $deepestCategory = $rootCategory->id;
        $currentDeepness = $maxDeepness = 0;

        $categoryStructure = self::getCategoryStructure();

        foreach ($categoryStructure as $catConfig) {
            $category = $rootCategory; // first level category this row
            $currentDeepness = 0;

            for ($i = 0; $i < count($catConfig); ++$i) {
                $id = $catConfig[$i];
                $currentDeepness++;

                if ($id == 'brand') {
                    // TODO: implement brand category selection
                    break;
                } else {
                    if (Tools::strlen($tags[$id]['translation']) == 0) {
                        // skip this category/subcategory tree, which is an empty value
                        break;
                    }
                }

                $category = BdroppyRemoteCategory::getCategory(
                    $category,
                    $id,
                    $tags[$id]['value'],
                    $tags[$id]['translation']
                );
                $categoryIds[] = $category->id;
                if ($currentDeepness > $maxDeepness) {
                    $maxDeepness = $currentDeepness;
                    $deepestCategory = $category->id;
                }
            }
        }

        return array($categoryIds, $deepestCategory);
    }

    /** Update the products just imported **/
    private static function updateImportedProduct($refId, $productId)
    {
        try {
            $remoteProduct = BdroppyRemoteProduct::fromRewixId($refId);
            if ($remoteProduct->ps_product_id < 1) {
                $remoteProduct->ps_product_id = $productId;
            }

            $remoteProduct->sync_status = BdroppyRemoteProduct::SYNC_STATUS_UPDATED;
            $remoteProduct->imported = 1;
            $remoteProduct->last_sync_date = date('Y-m-d H:i:s');
            $remoteProduct->priority = 0;
            $remoteProduct->reason = '';
            $remoteProduct->save();
        } catch (PrestaShopException $e) {
            var_dump(6, $e->getMessage(), $e);
        }
    }

    /**
     * @param $xmlPath string path to the xml file containing data
     * @return bool (true when loads all products, or false when fails)
     */
    public static function getFilterData($xmlPath)
    {
        $reader = new XMLReader();
        $reader->open($xmlPath);

        while ($reader->read()) {
            if ($reader->nodeType == XMLReader::ELEMENT && $reader->name == 'item') {
                $xmlProduct = self::getProductXML($reader);
                $product = self::populateProduct($xmlProduct);

                if (!$product) {
                    //self::getLogger()->logDebug('Fail to load product!');

                    return false;
                } else {
                    //save current product:
                    self::$products[] = $product;

                    //save 'combos' lists:
                    if (!in_array($product['brand'], self::$brands) && $product['brand'] != '') {
                        self::$brands[] = $product['brand'];
                    }
                    if (!in_array($product['category'], self::$categories) && $product['category'] != '') {
                        self::$categories[] = $product['category'];
                    }
                    if (!in_array($product['subcategory'], self::$subcategories) && $product['subcategory'] != '') {
                        self::$subcategories[] = $product['subcategory'];
                    }
                    if (!in_array($product['gender'], self::$genders) && $product['gender'] != '') {
                        self::$genders[] = $product['gender'];
                    }
                }
            }
        }

        return true;
    }

    private static function getTagValue($product, $name, $lang)
    {
        foreach ($product->tags as $tag)
        {
            if($tag->name === $name)
            {
                if (isset($tag->value->translations->{$lang})){
                    return $tag->value->translations->{$lang};
                }else{
                    return $tag->value->value;
                }
            }
        }
    }

    private static function getCategory($product, $lang){
        return self::getTagValue($product, 'category','$lang');
    }

    private static function getSubCategory($product, $lang){
        return self::getTagValue($product, 'subcategory',$lang);
    }

    private static function getDescriptions($product, $lang)
    {
        if (isset($product->descriptions->{$lang}))
        {
            return @$product->descriptions->{$lang};
        }else{
            return "";
        }

    }

    private static function getBrand($product, $lang){
        return self::getTagValue($product, 'brand',$lang);
    }
    private static function getGender($product, $lang){
        return self::getTagValue($product, 'gender',$lang);
    }
    private static function getSeason($product, $lang){
        return self::getTagValue($product, 'season',$lang);
    }

    private static function getColor($product, $lang){
        return self::getTagValue($product, 'color',$lang);
    }

    private static function getName($product, $lang){
        if(!empty(self::getTagValue($product, 'productname',$lang)))
        {
            return self::getTagValue($product, 'productname',$lang);
        }else{
            return $product->name;
        }
    }

    /**
     * @param $xml SimpleXMLElement node element
     * @param bool $checkImported , default true
     *
     * @return array
     */
    private static function populateProduct($xml, $default_lang, $checkImported = true)
    {
        $sku = (string)$xml->code;
        $refId = (int)$xml->id;

        // fixme: use another initialization method
        $tags = array(
            BdroppyRemoteCategory::REWIX_BRAND_ID       => array(
                'value'       => '',
                'translation' => '',
            ),
            BdroppyRemoteCategory::REWIX_CATEGORY_ID    => array(
                'value'       => '',
                'translation' => '',
            ),
            BdroppyRemoteCategory::REWIX_SUBCATEGORY_ID => array(
                'value'       => '',
                'translation' => '',
            ),
            BdroppyRemoteCategory::REWIX_GENDER_ID      => array(
                'value'       => '',
                'translation' => '',
            ),
            BdroppyRemoteCategory::REWIX_COLOR_ID       => array(
                'value'       => '',
                'translation' => '',
            ),
            BdroppyRemoteCategory::REWIX_SEASON_ID      => array(
                'value'       => '',
                'translation' => '',
            ),
        );

        //check for tag-ID:
        foreach ($xml->tags as $tag) {
            if (in_array((int)$tag->id, array_keys($tags))) {
                $tags[(int)$tag->id] = self::getTagValues($tag->value, $default_lang);
            }
        }

        $name = (string)$xml->name;
        $price = (float)$xml->sellPrice;
        $priceTax = (float)$xml->taxable;
        $bestTaxable = (float)$xml->bestTaxable;
        $taxable = (float)$xml->taxable;
        $suggested = (float)$xml->suggestedPrice;
        $streetPrice = (float)$xml->streetPrice;
        $availability = (int)$xml->availability;

        if ($checkImported) {
            $remoteProduct = BdroppyRemoteProduct::fromRewixId($refId);
            $imported = (bool)$remoteProduct->imported;
        } else {
            $imported = false;
        }

        $description = $xml->descriptions;

        $product = array(
            'remote_id'             => $refId,
            'name'                  => $name,
            'pictures'              => $xml->pictures,
            'imported'              => $imported,
            'brand'                 => self::getBrand($xml, $default_lang),
            'category'              => self::getCategory($xml, $default_lang),
            'subcategory'           => self::getSubcategory($xml, $default_lang),
            'gender'                => self::getGender($xml, $default_lang),
            'color'                 => self::getColor($xml, $default_lang),
            'season'                => self::getSeason($xml, $default_lang),
            'code'                  => $sku,
            'availability'          => $availability,
            'best_taxable'          => $bestTaxable,
            'taxable'               => $taxable,
            'suggested'             => $suggested,
            'street_price'          => $streetPrice,
            'proposed_price'        => $price,
            'proposed_price_tax'    => $priceTax,
            'description'           => $description,
            'tags'                  => $tags,
            'weight'                => $xml->weight,
        );

        if (!empty($product)) {
            return $product;
        }

        return false;
    }

    /**
     * @param $bestTaxable integer
     * @param $taxable integer
     * @param $streetPrice integer
     * @return float (the price based on the prices' settings)
     */
    private static function calculatePrice($suggestedPrice, $bestTaxable, $taxable, $streetPrice)
    {
        $taxRule = new Tax(Configuration::get(BdroppyConfigKeys::TAX_RATE));
        $conversion = Configuration::get(BdroppyConfigKeys::CONVERSION_COEFFICIENT);
        $markup = (1 + Configuration::get(BdroppyConfigKeys::MARKUP) / 100) * $conversion;

        if (Configuration::get(BdroppyConfigKeys::PRICE_BASE) == 'suggested') {
            try {
                $price = $suggestedPrice * $markup;
            } catch (Exception $e) {
                //self::getLogger()->logError('Error during the import, suggested price missing: ' . $e->getMessage());
            }
        } elseif (Configuration::get(BdroppyConfigKeys::PRICE_BASE) == 'best_taxable') {
            $price = $bestTaxable * $markup;
        } else {
            if (Configuration::get(BdroppyConfigKeys::PRICE_BASE) == 'taxable') {
                $price = $taxable * $markup;
            } else {
                if (Configuration::get(BdroppyConfigKeys::PRICE_BASE) == 'street_price') {
                    $price = $streetPrice * $markup;
                } else {
                    $price = $bestTaxable;
                }
            }
        }

        $price = ceil($price) - 0.01;

        return $price / (1 + $taxRule->rate / 100);
    }
    /**
     * @param $bestTaxable integer
     * @param $taxable integer
     * @param $streetPrice integer
     * @return float (the price based on the prices' settings)
     */
    private static function calculatePriceTax($suggestedPrice, $bestTaxable, $taxable, $streetPrice)
    {
        $conversion = Configuration::get(BdroppyConfigKeys::CONVERSION_COEFFICIENT);
        $markup = (1 + Configuration::get(BdroppyConfigKeys::MARKUP) / 100) * $conversion;

        if (Configuration::get(BdroppyConfigKeys::PRICE_BASE) == 'suggested') {
            try {
                $price = $suggestedPrice * $markup;
            } catch (Exception $e) {
                //self::getLogger()->logError('Error during the import, suggested price missing: ' . $e->getMessage());
            }
        } elseif (Configuration::get(BdroppyConfigKeys::PRICE_BASE) == 'best_taxable') {
            $price = $bestTaxable * $markup;
        } else {
            if (Configuration::get(BdroppyConfigKeys::PRICE_BASE) == 'taxable') {
                $price = $taxable * $markup;
            } else {
                if (Configuration::get(BdroppyConfigKeys::PRICE_BASE) == 'street_price') {
                    $price = $streetPrice * $markup;
                } else {
                    $price = $bestTaxable;
                }
            }
        }

        $price = ceil($price) - 0.01;

        return $price;
    }

    private static function stripTagValues($value)
    {
        return html_entity_decode(str_replace('\n', '', trim($value)));
    }

    private static function populateProductAttributes($xmlProduct, Product $product, $default_lang)
    {
        try {
            $productData = self::populateProduct($xmlProduct, $default_lang);
            $product->reference = self::fitReference($productData['code'], (string)$xmlProduct->id);
            $product->active = (int)true;
            $product->weight = (float)$xmlProduct->weight;

            $product->wholesale_price = $productData['best_taxable'];
            $product->price = round($productData['proposed_price'], 3);
            //$product->id_tax_rules_group = Configuration::get(BdroppyConfigKeys::TAX_RULE);

            $languages = Language::getLanguages();
            foreach ($languages as $lang) {
                $langCode = str_replace('-', '_', $lang['locale']);
                if($langCode == 'en_GB')
                    $langCode = 'en_US';
                $name = '';
                if(Configuration::get('BDROPPY_IMPORT_BRAND_TO_TITLE')) {
                    $name = $productData['brand'] . ' - ' . $productData['name'];
                } else {
                    $name = $productData['name'];
                }
                $pname_tag = Configuration::get('BDROPPY_IMPORT_TAG_TO_TITLE');
                if($pname_tag) {
                    $tag = $productData[$pname_tag];
                    if (!empty($tag)) {
                        $name .= ' - ' . $tag;
                    }
                }
                $product->name[$lang['id_lang']] = $name;
                $product->link_rewrite[$lang['id_lang']] = Tools::link_rewrite("{$productData['brand']}-{$productData['code']}");
                $product->description[$lang['id_lang']] = self::getDescriptions($xmlProduct, $langCode);
                $product->description_short[$lang['id_lang']] = substr(self::getDescriptions($xmlProduct, $langCode), 0, 800);
            }

            if (!isset($product->date_add) || empty($product->date_add)) {
                $product->date_add = date('Y-m-d H:i:s');
            }
            $product->date_upd = date('Y-m-d H:i:s');

            $product->id_manufacturer = self::getManufacturer($productData['brand']);
            list($categories, $categoryDefaultId) = self::getCategoryIds($productData['tags']);
            $product->id_category_default = $categoryDefaultId;
            $product->save();

            // updateCategories requires the product to have an id already set
            $product->updateCategories($categories);

            $sizeFeatureId = Configuration::get('BDROPPY_SIZE');
            $colorFeatureId = Configuration::get('BDROPPY_COLOR');
            $genderFeatureId = Configuration::get('BDROPPY_GENDER');
            $seasonFeatureId = Configuration::get('BDROPPY_SEASON');

            if (Tools::strlen($sizeFeatureId) > 0 && $sizeFeatureId > 0 && Tools::strlen($productData['size']) > 0) {
                $featureValueId = FeatureValue::addFeatureValueImport(
                    $sizeFeatureId,
                    $productData['size'],
                    $product->id,
                    Configuration::get('PS_LANG_DEFAULT')
                );
                Product::addFeatureProductImport($product->id, $sizeFeatureId, $featureValueId);
            }
            if (Tools::strlen($colorFeatureId) > 0 && $colorFeatureId > 0 && Tools::strlen($productData['color']) > 0) {
                $featureValueId = FeatureValue::addFeatureValueImport(
                    $colorFeatureId,
                    $productData['color'],
                    $product->id,
                    Configuration::get('PS_LANG_DEFAULT')
                );
                Product::addFeatureProductImport($product->id, $colorFeatureId, $featureValueId);
            }
            if (Tools::strlen($genderFeatureId) > 0 && $genderFeatureId > 0 && Tools::strlen($productData['gender']) > 0) {
                $featureValueId = FeatureValue::addFeatureValueImport(
                    $genderFeatureId,
                    $productData['gender'],
                    $product->id,
                    Configuration::get('PS_LANG_DEFAULT')
                );
                Product::addFeatureProductImport($product->id, $genderFeatureId, $featureValueId);
            }
            if (Tools::strlen($seasonFeatureId) > 0 && $seasonFeatureId > 0 && Tools::strlen($productData['season']) > 0) {
                $featureValueId = FeatureValue::addFeatureValueImport(
                    $seasonFeatureId,
                    $productData['season'],
                    $product->id,
                    Configuration::get('PS_LANG_DEFAULT')
                );
                Product::addFeatureProductImport($product->id, $seasonFeatureId, $featureValueId);
            }

            return $product;
        } catch (PrestaShopException $e) {
            var_dump(2, $e->getMessage(), $e);
        }

    }

    /**
     * Checks if the manufacturer already exists, or creates it.
     *
     * @param $brand string name of the manufacturer
     *
     * @return int manufacturerId just created/checked
     */
    private static function getManufacturer($brand)
    {
        $brandId = Manufacturer::getIdByName($brand);

        if ($brandId == false) {
            $manufacturer = new Manufacturer();
            $manufacturer->name = $brand;
            $manufacturer->active = true;
            $manufacturer->save();
            $brandId = $manufacturer->id;
        }

        return $brandId;
    }

    private static function importModels($xmlProduct, Product $product)
    {
        try {
            $xmlModels = $xmlProduct->models;
            $modelCount = 0;

            $languages = Language::getLanguages(false);
            $first = true;
            foreach ($xmlModels as $model) {
                $sizeAttribute = self::getSizeAttributeFromValue((string)$model->size);
                $quantity = (int)$model->availability;
                $reference = self::fitModelReference((string)$model->code, (string)$model->size);
                $ean13 = trim((string)$model->barcode);
                if(strlen($ean13)>13) {
                    $ean13 = substr($ean13, 0, 13);
                }

                $combinationAttributes = array();
                if($model->color) {
                    $sql = "SELECT * FROM "._DB_PREFIX_."attribute a LEFT JOIN "._DB_PREFIX_."attribute_lang al ON (a.id_attribute = al.id_attribute) WHERE a.id_attribute_group = ".Configuration::get('BDROPPY_COLOR')." AND al.name = '" . $model->color . "';";
                    $r = Db::getInstance()->executeS($sql);
                    if ($r) {
                        $attribute = (object)$r[0];
                    } else {
                        $attribute = new Attribute();
                        foreach ($languages as $lang) {
                            $langCode = str_replace('-', '_', $lang['locale']);
                            if($langCode == 'en_GB')
                                $langCode = 'en_US';
                            $attribute->name[$lang['id_lang']] = self::getColor($xmlProduct, $langCode);
                        }
                        $attribute->id_attribute_group = Configuration::get('BDROPPY_COLOR');
                        $attribute->save();
                        $sql = "SELECT * FROM "._DB_PREFIX_."attribute a LEFT JOIN "._DB_PREFIX_."attribute_lang al ON (a.id_attribute = al.id_attribute) WHERE a.id_attribute_group = ".Configuration::get('BDROPPY_COLOR')." AND al.name = '" . $model->color . "';";
                        $r = Db::getInstance()->executeS($sql);
                        if ($r) {
                            $attribute = (object)$r[0];
                        }
                    }
                    $combinationAttributes[] = $attribute->id_attribute;
                }
                if($model->size) {
                    $sql = "SELECT * FROM "._DB_PREFIX_."attribute a LEFT JOIN "._DB_PREFIX_."attribute_lang al ON (a.id_attribute = al.id_attribute) WHERE a.id_attribute_group = " . Configuration::get('BDROPPY_SIZE') . " AND al.name = '" . $model->size . "';";
                    $r = Db::getInstance()->executeS($sql);

                    if ($r) {
                        $attribute = (object)$r[0];
                    } else {
                        $attribute = new Attribute();
                        foreach ($languages as $lang) {
                            $attribute->name[$lang['id_lang']] = $model->size;
                        }
                        $attribute->id_attribute_group = Configuration::get('BDROPPY_SIZE');
                        $attribute->save();
                        $sql = "SELECT * FROM "._DB_PREFIX_."attribute a LEFT JOIN "._DB_PREFIX_."attribute_lang al ON (a.id_attribute = al.id_attribute) WHERE a.id_attribute_group = " . Configuration::get('BDROPPY_SIZE') . " AND al.name = '" . $model->size . "';";
                        $r = Db::getInstance()->executeS($sql);

                        if ($r) {
                            $attribute = (object)$r[0];
                        }
                    }
                    $combinationAttributes[] = $attribute->id_attribute;
                }

                $impact_on_price_per_unit = 0;
                $impact_on_price = 0;
                $impact_on_weight = $xmlProduct->weight;
                $isbn_code = $model->id;
                $id_supplier = null;
                $default = $first;
                $location = null;
                $id_images = null;
                $upc = null;
                $minimal_quantity = 1;
                $idProductAttribute = $product->addProductAttribute((float)$impact_on_price, (float)$impact_on_weight, $impact_on_price_per_unit, null, (int)$quantity, $id_images, $reference, $id_supplier, $ean13, $default, $location, $upc, null, $isbn_code, $minimal_quantity);
                $r = $product->addAttributeCombinaison($idProductAttribute, $combinationAttributes);
                Db::getInstance()->update('product_attribute', array('wholesale_price'=>(float) $xmlProduct->bestTaxable), 'id_product_attribute = '.(int)$idProductAttribute );
                $first = false;
            }

            return $product;
        } catch (PrestaShopException $e) {
            var_dump(4, $e->getMessage(), $e);
        }
    }

    private static function checkNosizeModel($xmlProduct, Product $product)
    {
        if (BdroppyRemoteCombination::countByRewixProductId($product->id) == 0) {
            self::insertNosizeModel($xmlProduct);
        }
    }

    /** In case of simple product, nosize combination is stored in remote_combination table for sending right variation with orders */
    private static function insertNosizeModel($xmlProduct)
    {
        $xmlModel = $xmlProduct->models[0];

        $remoteCombination = BdroppyRemoteCombination::fromRewixId((int)$xmlModel->id);
        $remoteCombination->rewix_product_id = (int) $xmlProduct->id;

        //$reference = self::fitModelReference((string)$xmlModel->code, (string)$xmlModel->size);
        //$ean13 = trim((string)$xmlModel->barcode);

        $remoteCombination->ps_model_id = 0;
        $remoteCombination->save();
    }

    private static function importSimpleProduct($xmlProduct, Product $product)
    {
        try {
            $xmlModel = $xmlProduct->models[0];
            $product->minimal_quantity = 1;
            $product->ean13 = (string)$xmlModel->barcode;
            $product->reference = self::fitReference((string)$xmlModel->code, $xmlProduct->id);
            StockAvailable::setQuantity($product->id, 0, (int)$xmlModel->availability);

            self::insertNosizeModel($xmlProduct);

            return $product;
        } catch (PrestaShopException $e) {
            var_dump(3, $e->getMessage(), $e);
        }
    }

    private static function getSizeAttributeFromValue($value)
    {
        $sizeAttrGroupId = Configuration::get('BDROPPY_SIZE');
        if (Tools::strlen($sizeAttrGroupId) == 0 || $sizeAttrGroupId == 0) {
            return false;
        }
        $attributes = AttributeGroup::getAttributes(Configuration::get('PS_LANG_DEFAULT'), $sizeAttrGroupId);
        $sizeAttribute = null;

        foreach ($attributes as $attribute) {
            if ($attribute['name'] == $value) {
                return new Attribute($attribute['id_attribute']);
            }
        }

        if ($sizeAttribute == null) {
            $sizeAttribute = new Attribute();
            $sizeAttribute->id_attribute_group = $sizeAttrGroupId;
            $sizeAttribute->name = array_fill_keys(Language::getIDs(), (string)$value);

            $sizeAttribute->add();
        }

        return $sizeAttribute;
    }

    private static function checkSimpleImport($xmlProduct)
    {
        if (count($xmlProduct->models) == 1) {
            $xmlModel = $xmlProduct->models[0];
            $size = (string)$xmlModel->size;
            if ($size == 'NOSIZE') {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    private static function getCategoryStructure()
    {
        if (self::$categoryStructure != null) {
            return self::$categoryStructure;
        }
        if (Configuration::get('BDROPPY_CATEGORY_STRUCTURE') == '2') {
            self::$categoryStructure = array(
                array(
                    BdroppyRemoteCategory::REWIX_GENDER_ID,
                    BdroppyRemoteCategory::REWIX_CATEGORY_ID,
                    BdroppyRemoteCategory::REWIX_SUBCATEGORY_ID,
                ),
                array(
                    BdroppyRemoteCategory::REWIX_CATEGORY_ID,
                    BdroppyRemoteCategory::REWIX_SUBCATEGORY_ID,
                ),
            );
        } else {
            self::$categoryStructure = array(
                array(
                    BdroppyRemoteCategory::REWIX_CATEGORY_ID,
                    BdroppyRemoteCategory::REWIX_SUBCATEGORY_ID,
                ),
            );
        }

        return self::$categoryStructure;
    }

    /**
     * @return array the list of the available attributes in the shop of group_type = select
     */
    public static function getAvailableAttributes()
    {
        $attrGroups = AttributeGroup::getAttributesGroups(Configuration::get('PS_LANG_DEFAULT'));
        $availAttrGroups = array();
        // filter out non-dropdown groups
        foreach ($attrGroups as $group) {
            if ($group['group_type'] == 'select') {
                $availAttrGroups[] = $group;
            }
        }

        return $availAttrGroups;
    }

    public static function getAvailableFeatures()
    {
        return Feature::getFeatures(Configuration::get('PS_LANG_DEFAULT'));
    }

    public static function getAvailableTaxRules()
    {
        return TaxRulesGroup::getTaxRulesGroups();
    }

    public static function getAvailableTaxes()
    {
        return Tax::getTaxes(Configuration::get('PS_LANG_DEFAULT'));
    }

    /**
     * @param string $ean
     * @param string $size
     * @return string
     */
    private static function fitModelReference($ean, $size)
    {
        $len = Tools::strlen($ean) + Tools::strlen($size) + 1;
        if ($len > 32) {
            $ean = Tools::substr($ean, 0, -($len % 32));
        }
        return $ean . '-' . $size;
    }

    /**
     * @param string $ean
     * @param string $id
     * @return string
     */
    private static function fitReference($ean, $id)
    {
        if (Tools::strlen($ean) > 32) {
            $ean = Tools::substr($ean, 0, 32 - Tools::strlen($id));
            $ean .= $id;
        }
        return $ean;
    }

    public static function syncWithSupplier()
    {
        $rewixApi = new BdroppyRewixApi();
                
        $rewixApi->syncBookedProducts();
        $rewixApi->sendMissingOrders();
    }
}
