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

class DropshippingImportTools
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
            $verboseLog = (bool)Configuration::get(DropshippingConfigKeys::VERBOSE_LOG);
            self::$logger = new FileLogger($verboseLog ? FileLogger::DEBUG : FileLogger::ERROR);
            $filename = _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'dropshipping-import.log';
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
        
        $locale = Configuration::get(DropshippingConfigKeys::LOCALE);
        $username = Configuration::get(DropshippingConfigKeys::APIKEY);
        $password = Configuration::get(DropshippingConfigKeys::PASSWORD);
        $websiteUrl = Configuration::get(DropshippingConfigKeys::WEBSITE_URL);
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
            Configuration::updateValue(DropshippingConfigKeys::LAST_QUANTITIES_SYNC, null, null, 0, 0);
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
                    
                    $id = DropshippingRemoteProduct::getIdByRewixId((int) $xmlProduct->id, true);
                    if ($id != 0) {
                            $rewixProduct = new DropshippingRemoteProduct($id);

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

            $remoteProducts = DropshippingRemoteProduct::getByStatus(DropshippingRemoteProduct::SYNC_STATUS_UPDATED, 0);
            //$logger->logDebug(count($remoteProducts) . ' products which quantities will be updated.');
            $productsCount = 0;

            foreach ($remoteProducts as $remoteProduct) {
                if ($remoteProduct['imported'] == 1) {
                    $models = DropshippingRemoteCombination::getByRewixProductId($remoteProduct['rewix_product_id']);
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

            Configuration::updateValue(DropshippingConfigKeys::LAST_QUANTITIES_SYNC, $lastUpdate, null, 0, 0);

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

        $path =  self::getXmlSource(Configuration::get(DropshippingConfigKeys::LAST_QUANTITIES_SYNC));
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
            Configuration::updateValue(DropshippingConfigKeys::LAST_QUANTITIES_SYNC, null, null, 0, 0);
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
            $id = DropshippingRemoteProduct::getIdByRewixId($key);
            if ($id) {
                $productsCount += 1;
                $product = new DropshippingRemoteProduct($id);
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
                        $modelId = DropshippingRemoteCombination::getPsModelIdByRewixProductAndModelId($key, $mkey);
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

        Configuration::updateValue(DropshippingConfigKeys::LAST_QUANTITIES_SYNC, $lastUpdate, null, 0, 0);

        if (file_exists($path)) {
            unlink($path);
        }
        return true;
    }
    
    public static function processImportQueue()
    {
        $productIds = DropshippingRemoteProduct::getIdsByStatus(DropshippingRemoteProduct::SYNC_STATUS_QUEUED, 30, 4);
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
                                Configuration::updateValue(DropshippingConfigKeys::LAST_QUANTITIES_SYNC, $lastUpdate, null, 0, 0);
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
                    $currentLastUpdate = Configuration::get(DropshippingConfigKeys::LAST_QUANTITIES_SYNC);
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
        
        Configuration::updateValue(DropshippingConfigKeys::LAST_IMPORT_SYNC, (int) time(), null, 0, 0);
    }

    private static function importProduct($xmlProduct)
    {
        @set_time_limit(3600);
        @ini_set('memory_limit', '1024M');

        $refId = (int) $xmlProduct->id;
        $sku = (string) $xmlProduct->code;
        $remoteProduct = DropshippingRemoteProduct::fromRewixId($refId);

        $product = new Product($remoteProduct->ps_product_id);

        //self::getLogger()->logDebug('Importing parent product ' . $sku . ' with id ' . $xmlProduct->id);

        // populate general common fields
        $product = self::populateProductAttributes($xmlProduct, $product);
        if (self::checkSimpleImport($xmlProduct)) {
            //self::getLogger()->logDebug('Product ' . $sku . ' with id ' . $xmlProduct->id . ' will be imported as simple product');
            $product = self::importSimpleProduct($xmlProduct, $product);
            $remoteProduct->simple = 1;
            $remoteProduct->save();
        } else {
            $product = self::importModels($xmlProduct, $product);
        }
        $product->save();
       
        if (Configuration::get(DropshippingConfigKeys::IMPORT_IMAGES)) {
            //self::getLogger()->logDebug('Importing images for product ' . $product->id . ' (' . $xmlProduct->id . ')');
            self::importProductImages($xmlProduct, $product);
        }

        self::updateImportedProduct($refId, $product->id);

        return 1;
    }

    private static function incrementPriority($products)
    {
        $prods = '';
        foreach ($products as $product) {
            $p = DropshippingRemoteProduct::fromRewixId($product['id']);
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
            DropshippingRemoteProduct::deleteByRewixId($id);
        }
        if (Tools::strlen($prods) > 0) {
            //self::getLogger()->logWarning('Removed from queue ' . $prods . 'not available upstream.');
        }
    }

    private static function getTagValues($tag)
    {
        $value = self::stripTagValues((string)$tag->value);
        $translation = self::stripTagValues((string)$tag->translations->translation->description);

        return array(
            'value'       => $value,
            'translation' => Tools::strlen($translation) > 0 ? $translation : $value,
        );
    }

    private static function importProductImages($xmlProduct, $product)
    {
        $imageCount = 1;
        $websiteUrl = Configuration::get(DropshippingConfigKeys::WEBSITE_URL);
        $product->deleteImages();

        foreach ($xmlProduct->pictures->image as $image) {
            $imageUrl = "{$websiteUrl}{$image->url}?x=1300&y=1300&pad=1&fill=white";

            $ch = curl_init($imageUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $content = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);
            if ($httpCode != 200) {
                //self::getLogger()->logDebug('Error loading Image: There has been an error executing the request: ' . $httpCode . '. Error:' . $curlError);
            } else {
                $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'dropshipping_import');
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
                    if (!DropshippingImportHelper::copyImg($product->id, $image->id, $tmpfile, 'products', true)) {
                        $image->delete();
                    } else {
                        $imageCount++;
                    }
                }
                @unlink($tmpfile);
            }
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

                $category = DropshippingRemoteCategory::getCategory(
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
        $remoteProduct = DropshippingRemoteProduct::fromRewixId($refId);
        if ($remoteProduct->ps_product_id < 1) {
            $remoteProduct->ps_product_id = $productId;
        }

        $remoteProduct->sync_status = DropshippingRemoteProduct::SYNC_STATUS_UPDATED;
        $remoteProduct->imported = 1;
        $remoteProduct->last_sync_date = date('Y-m-d H:i:s');
        $remoteProduct->priority = 0;
        $remoteProduct->reason = '';
        $remoteProduct->save();
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

    /**
     * @param $xml SimpleXMLElement node element
     * @param bool $checkImported , default true
     *
     * @return array
     */
    private static function populateProduct($xml, $checkImported = true)
    {
        $sku = (string)$xml->code;
        $refId = (int)$xml->id;

        // fixme: use another initialization method
        $tags = array(
            DropshippingRemoteCategory::REWIX_BRAND_ID       => array(
                'value'       => '',
                'translation' => '',
            ),
            DropshippingRemoteCategory::REWIX_CATEGORY_ID    => array(
                'value'       => '',
                'translation' => '',
            ),
            DropshippingRemoteCategory::REWIX_SUBCATEGORY_ID => array(
                'value'       => '',
                'translation' => '',
            ),
            DropshippingRemoteCategory::REWIX_GENDER_ID      => array(
                'value'       => '',
                'translation' => '',
            ),
            DropshippingRemoteCategory::REWIX_COLOR_ID       => array(
                'value'       => '',
                'translation' => '',
            ),
            DropshippingRemoteCategory::REWIX_SEASON_ID      => array(
                'value'       => '',
                'translation' => '',
            ),
        );

        //check for tag-ID:
        foreach ($xml->tags->tag as $tag) {
            if (in_array((int)$tag->id, array_keys($tags))) {
                $tags[(int)$tag->id] = self::getTagValues($tag->value);
            }
        }

        $name = $tags[DropshippingRemoteCategory::REWIX_BRAND_ID]['translation'] . ' - ' . (string)$xml->name;
        $price = self::calculatePrice((float)$xml->suggestedPrice, (float)$xml->bestTaxable, (float)$xml->taxable, (float)$xml->streetPrice);
        $priceTax = self::calculatePriceTax((float)$xml->suggestedPrice, (float)$xml->bestTaxable, (float)$xml->taxable, (float)$xml->streetPrice);
        $bestTaxable = ((float)$xml->bestTaxable) * Configuration::get(DropshippingConfigKeys::CONVERSION_COEFFICIENT);
        $taxable = ((float)$xml->taxable) * Configuration::get(DropshippingConfigKeys::CONVERSION_COEFFICIENT);
        $suggested = ((float)$xml->suggestedPrice) * Configuration::get(DropshippingConfigKeys::CONVERSION_COEFFICIENT);
        $streetPrice = ((float)$xml->streetPrice) * Configuration::get(DropshippingConfigKeys::CONVERSION_COEFFICIENT);
        $availability = (int)$xml->availability;
        $imageURL = Configuration::get(DropshippingConfigKeys::WEBSITE_URL) . $xml->pictures->image->url;

        if ($checkImported) {
            $remoteProduct = DropshippingRemoteProduct::fromRewixId($refId);
            $imported = (bool)$remoteProduct->imported;
        } else {
            $imported = false;
        }

        $description = (string)$xml->descriptions->description->description;

        $product = array(
            'remote_id'             => $refId,
            'name'                  => $name,
            'image'                 => $imageURL,
            'imported'              => $imported,
            'brand'                 => $tags[DropshippingRemoteCategory::REWIX_BRAND_ID]['translation'],
            'category'              => $tags[DropshippingRemoteCategory::REWIX_CATEGORY_ID]['translation'],
            'subcategory'           => $tags[DropshippingRemoteCategory::REWIX_SUBCATEGORY_ID]['translation'],
            'gender'                => $tags[DropshippingRemoteCategory::REWIX_GENDER_ID]['translation'],
            'color'                 => $tags[DropshippingRemoteCategory::REWIX_COLOR_ID]['translation'],
            'season'                => $tags[DropshippingRemoteCategory::REWIX_SEASON_ID]['translation'],
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
        $taxRule = new Tax(Configuration::get(DropshippingConfigKeys::TAX_RATE));
        $conversion = Configuration::get(DropshippingConfigKeys::CONVERSION_COEFFICIENT);
        $markup = (1 + Configuration::get(DropshippingConfigKeys::MARKUP) / 100) * $conversion;

        if (Configuration::get(DropshippingConfigKeys::PRICE_BASE) == 'suggested') {
            try {
                $price = $suggestedPrice * $markup;
            } catch (Exception $e) {
                //self::getLogger()->logError('Error during the import, suggested price missing: ' . $e->getMessage());
            }
        } elseif (Configuration::get(DropshippingConfigKeys::PRICE_BASE) == 'best_taxable') {
            $price = $bestTaxable * $markup;
        } else {
            if (Configuration::get(DropshippingConfigKeys::PRICE_BASE) == 'taxable') {
                $price = $taxable * $markup;
            } else {
                if (Configuration::get(DropshippingConfigKeys::PRICE_BASE) == 'street_price') {
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
        $conversion = Configuration::get(DropshippingConfigKeys::CONVERSION_COEFFICIENT);
        $markup = (1 + Configuration::get(DropshippingConfigKeys::MARKUP) / 100) * $conversion;

        if (Configuration::get(DropshippingConfigKeys::PRICE_BASE) == 'suggested') {
            try {
                $price = $suggestedPrice * $markup;
            } catch (Exception $e) {
                //self::getLogger()->logError('Error during the import, suggested price missing: ' . $e->getMessage());
            }
        } elseif (Configuration::get(DropshippingConfigKeys::PRICE_BASE) == 'best_taxable') {
            $price = $bestTaxable * $markup;
        } else {
            if (Configuration::get(DropshippingConfigKeys::PRICE_BASE) == 'taxable') {
                $price = $taxable * $markup;
            } else {
                if (Configuration::get(DropshippingConfigKeys::PRICE_BASE) == 'street_price') {
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

    private static function populateProductAttributes($xmlProduct, Product $product)
    {
        $productData = self::populateProduct($xmlProduct);
        $product->reference = self::fitReference($productData['code'], (string)$xmlProduct->id);
        $product->active = (int)true;
        $product->weight = (float)$xmlProduct->weight;

        $product->wholesale_price = $productData['taxable'];
        $product->price = round($productData['proposed_price'], 3);
        $product->id_tax_rules_group = Configuration::get(DropshippingConfigKeys::TAX_RULE);

        $languages = Language::getLanguages();
        foreach ($languages as $lang) {
            $product->name[$lang['id_lang']] = $productData['name'];
            $product->link_rewrite[$lang['id_lang']] = Tools::link_rewrite(
                "{$productData['brand']}-{$productData['code']}"
            );
            $product->description[$lang['id_lang']] = $productData['description'];
            $product->description_short[$lang['id_lang']] = $productData['description'];
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

        $colorFeatureId = Configuration::get(DropshippingConfigKeys::COLOR_ATTRIBUTE);
        $genderFeatureId = Configuration::get(DropshippingConfigKeys::GENDER_ATTRIBUTE);
        $seasonFeatureId = Configuration::get(DropshippingConfigKeys::SEASON_ATTRIBUTE);
        
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
        $xmlModels = $xmlProduct->models;
        $modelCount = 0;

        foreach ($xmlModels->model as $xmlModel) {
            $sizeAttribute = self::getSizeAttributeFromValue((string)$xmlModel->size);
            $quantity = (int)$xmlModel->availability;

            if ($sizeAttribute == false) {
                return $product;
            }
            $remoteCombination = DropshippingRemoteCombination::fromRewixId((int)$xmlModel->id);
            $remoteCombination->rewix_product_id = (int) $xmlProduct->id;

            $reference = self::fitModelReference((string)$xmlModel->code, (string)$xmlModel->size);
            $ean13 = trim((string)$xmlModel->barcode);

            if ($remoteCombination->ps_model_id == 0) {
                $combinationId = $product->addAttribute(
                    0,
                    0,
                    '',
                    0,
                    '',
                    $reference,
                    Tools::strlen($ean13) == 13 ? $ean13 : '',
                    $modelCount == 0
                );
                $remoteCombination->ps_model_id = $combinationId;
                $remoteCombination->save();
            } else {
                $combinationId = $product->updateAttribute(
                    $remoteCombination->ps_model_id,
                    null,
                    0,
                    0,
                    '',
                    0,
                    '',
                    $reference,
                    Tools::strlen($ean13) == 13 ? $ean13 : '',
                    $modelCount == 0
                );
            }

            $combination = new Combination($combinationId);
            $combination->setAttributes(array($sizeAttribute->id));
            //Set the correct quantity for current model
            StockAvailable::setQuantity($product->id, $combinationId, $quantity);

            ++$modelCount;
        }

        return $product;
    }

    private static function checkNosizeModel($xmlProduct, Product $product)
    {
        if (DropshippingRemoteCombination::countByRewixProductId($product->id) == 0) {
            self::insertNosizeModel($xmlProduct);
        }
    }

    /** In case of simple product, nosize combination is stored in remote_combination table for sending right variation with orders */
    private static function insertNosizeModel($xmlProduct)
    {
        $xmlModel = $xmlProduct->models->model[0];

        $remoteCombination = DropshippingRemoteCombination::fromRewixId((int)$xmlModel->id);
        $remoteCombination->rewix_product_id = (int) $xmlProduct->id;

        //$reference = self::fitModelReference((string)$xmlModel->code, (string)$xmlModel->size);
        //$ean13 = trim((string)$xmlModel->barcode);
        
        $remoteCombination->ps_model_id = 0;
        $remoteCombination->save();
    }

    private static function importSimpleProduct($xmlProduct, Product $product)
    {
        $xmlModel = $xmlProduct->models->model[0];
        $product->minimal_quantity = 1;
        $product->ean13 = (string)$xmlModel->barcode;
        $product->reference = self::fitReference((string)$xmlModel->code, $xmlProduct->id);
        StockAvailable::setQuantity($product->id, 0, (int)$xmlModel->availability);

        self::insertNosizeModel($xmlProduct);

        return $product;
    }

    private static function getSizeAttributeFromValue($value)
    {
        $sizeAttrGroupId = Configuration::get(DropshippingConfigKeys::SIZE_ATTRIBUTE);
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
        if (count($xmlProduct->models->model) == 1) {
            $xmlModel = $xmlProduct->models->model[0];
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
        if (Configuration::get(DropshippingConfigKeys::CATEGORY_STRUCTURE) == 'withgender') {
            self::$categoryStructure = array(
                array(
                    DropshippingRemoteCategory::REWIX_GENDER_ID,
                    DropshippingRemoteCategory::REWIX_CATEGORY_ID,
                    DropshippingRemoteCategory::REWIX_SUBCATEGORY_ID,
                ),
                array(
                    DropshippingRemoteCategory::REWIX_CATEGORY_ID,
                    DropshippingRemoteCategory::REWIX_SUBCATEGORY_ID,
                ),
            );
        } else {
            self::$categoryStructure = array(
                array(
                    DropshippingRemoteCategory::REWIX_CATEGORY_ID,
                    DropshippingRemoteCategory::REWIX_SUBCATEGORY_ID,
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
        $rewixApi = new DropshippingRewixApi();
                
        $rewixApi->syncBookedProducts();
        $rewixApi->sendMissingOrders();
    }
}
