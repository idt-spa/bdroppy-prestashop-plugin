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

    public static function getLogger()
    {
        if (self::$logger == null) {
            $verboseLog = true;
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

    public static function processImportQueue()
    {
        $productIds = BdroppyRemoteProduct::getIdsByStatus(BdroppyRemoteProduct::SYNC_STATUS_QUEUED, 30, 4);
        if (count($productIds) > 0) {
            self::importProducts($productIds);
        }
    }

    public static function updateProductPrices($item, $default_lang) {
        $rewixApi = new BdroppyRewixApi();
        $res = $rewixApi->getProduct($item['rewix_product_id'], $item['rewix_catalog_id']);
        if ($res['http_code'] === 200) {
            $xmlProduct = json_decode($res['data']);
            $productData = self::populateProduct($xmlProduct, $default_lang, true);
            $product = new Product($item['ps_product_id']);
            $tax = new Tax(Configuration::get('BDROPPY_TAX_RATE'));
            $product->wholesale_price = round($productData['best_taxable'], 3);
            $price = $productData['proposed_price'];
            $price = $price / (1 + $tax->rate / 100);
            $product->price = round($price, 3);
            $product->id_tax_rules_group = Configuration::get('BDROPPY_TAX_RULE');
            $product->tax_rate = $tax->rate;
            $product->save();
            $refId = (int)$xmlProduct->id;
            self::updateImportedProduct($refId, $product->id);
        }
    }

    public static function importProduct($item, $default_lang)
    {
        try {
            @set_time_limit(3600);
            @ini_set('memory_limit', '1024M');

            $xmlProduct = false;
            $rewixApi = new BdroppyRewixApi();
            $res = $rewixApi->getProduct($item['rewix_product_id'], $item['rewix_catalog_id']);

            if ($res['http_code'] === 200) {
                $xmlProduct = json_decode($res['data']);
            }
            if($xmlProduct) {
                $refId = (int)$xmlProduct->id;
                $sku = (string)$xmlProduct->code;
                $remoteProduct = BdroppyRemoteProduct::fromRewixId($refId);

                $product = new Product($remoteProduct->ps_product_id);

                self::getLogger()->logDebug('Importing parent product ' . $sku . ' with id ' . $xmlProduct->id);

                // populate general common fields
                $product1 = self::populateProductAttributes($xmlProduct, $product, $default_lang);
                if (self::checkSimpleImport($xmlProduct)) {
                    self::getLogger()->logDebug('Product ' . $sku . ' with id ' . $xmlProduct->id . ' will be imported as simple product');
                    $product2 = self::importSimpleProduct($xmlProduct, $product);
                    $remoteProduct->simple = 1;
                    $remoteProduct->save();
                } else {
                    $product3 = self::importModels($xmlProduct, $product, $default_lang);
                }
                $product->unity = Configuration::get('BDROPPY_CATALOG');
                $product->active = (int)Configuration::get('BDROPPY_ACTIVE_PRODUCT');
                $product->save();
                $res = Db::getInstance()->update('bdroppy_remoteproduct', array('ps_product_id'=>$product->id), 'id = '.$item['id']);

                if (Configuration::get('BDROPPY_IMPORT_IMAGE')) {
                    self::getLogger()->logDebug('Importing images for product ' . $product->id . ' (' . $xmlProduct->id . ')');
                    self::importProductImages($xmlProduct, $product, Configuration::get('BDROPPY_IMPORT_IMAGE'));
                }

                self::updateImportedProduct($refId, $product->id);

                return 1;
            }
        } catch (PrestaShopException $e) {
            self::getLogger()->logDebug( 'import - importProduct : ' . $e->getMessage() );
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
                    self::getLogger()->logDebug('Error loading Image: There has been an error executing the request: ' . $httpCode . '. Error:' . $curlError);
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
            self::getLogger()->logDebug( 'importProductImages : ' . $e->getMessage() );
        }
    }

    /**
     * @param $tags
     *
     * @return array
     */
    private static function getCategoryIds($tags, $xmlProduct)
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
                    $xmlProduct
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
            self::getLogger()->logDebug( 'updateImportedProduct : ' . $e->getMessage() );
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
                    self::getLogger()->logDebug('Fail to load product!');

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
        $priceTax = (float)$xml->sellPrice;
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

    private static function stripTagValues($value)
    {
        return html_entity_decode(str_replace('\n', '', trim($value)));
    }

    private static function populateProductAttributes($xmlProduct, Product $product, $default_lang)
    {
        try {
            $productData = self::populateProduct($xmlProduct, $default_lang, true);
            $product->reference = self::fitReference($productData['code'], (string)$xmlProduct->id);
            $product->weight = (float)$xmlProduct->weight;

            $tax = new Tax(Configuration::get('BDROPPY_TAX_RATE'));
            $product->wholesale_price = round($productData['best_taxable'], 3);
            $price = $productData['proposed_price'];
            $price = $price / (1 + $tax->rate / 100);
            $product->price = round($price, 3);
            $product->id_tax_rules_group = Configuration::get('BDROPPY_TAX_RULE');
            $product->tax_rate = $tax->rate;

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

            $languages = Language::getLanguages();
            foreach ($languages as $lang) {
                $langCode = $langs[$lang['iso_code']];
                $name = '';
                if(Configuration::get('BDROPPY_IMPORT_BRAND_TO_TITLE')) {
                    $name = $productData['brand'] . ' - ' . $productData['name'];
                } else {
                    $name = $productData['name'];
                }
                $pname_tag = Configuration::get('BDROPPY_IMPORT_TAG_TO_TITLE');
                if($pname_tag == 'color') {
                    $tag = self::getColor($xmlProduct, $langCode);
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
            list($categories, $categoryDefaultId) = self::getCategoryIds($productData['tags'], $xmlProduct);
            $product->id_category_default = $categoryDefaultId;
            $product->active = (int)Configuration::get('BDROPPY_ACTIVE_PRODUCT');
            $product->save();

            // updateCategories requires the product to have an id already set
            $product->updateCategories($categories);

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

            foreach ($languages as $lang) {
                $langCode = $langs[$lang['iso_code']];
                $sql = "SELECT * FROM `" . _DB_PREFIX_ . "feature` f LEFT JOIN `" . _DB_PREFIX_ . "feature_lang` fl ON (f.id_feature = fl.id_feature AND fl.`id_lang` = " . $lang['id_lang'] . ") WHERE fl.name = '".$lngSize[$lang['iso_code']]."';";
                $sizeFeature = Db::getInstance()->executeS($sql);

                $sql = "SELECT * FROM `" . _DB_PREFIX_ . "feature` f LEFT JOIN `" . _DB_PREFIX_ . "feature_lang` fl ON (f.id_feature = fl.id_feature AND fl.`id_lang` = " . $lang['id_lang'] . ") WHERE fl.name = '".$lngColor[$lang['iso_code']]."';";
                $colorFeature = Db::getInstance()->executeS($sql);

                $sql = "SELECT * FROM `" . _DB_PREFIX_ . "feature` f LEFT JOIN `" . _DB_PREFIX_ . "feature_lang` fl ON (f.id_feature = fl.id_feature AND fl.`id_lang` = " . $lang['id_lang'] . ") WHERE fl.name = '".$lngGender[$lang['iso_code']]."';";
                $genderFeature = Db::getInstance()->executeS($sql);

                $sql = "SELECT * FROM `" . _DB_PREFIX_ . "feature` f LEFT JOIN `" . _DB_PREFIX_ . "feature_lang` fl ON (f.id_feature = fl.id_feature AND fl.`id_lang` = " . $lang['id_lang'] . ") WHERE fl.name = '".$lngSeason[$lang['iso_code']]."';";
                $seasonFeature = Db::getInstance()->executeS($sql);

                $sizeFeatureId = $sizeFeature[0]['id_feature'];
                $colorFeatureId = $colorFeature[0]['id_feature'];
                $genderFeatureId = $genderFeature[0]['id_feature'];
                $seasonFeatureId = $seasonFeature[0]['id_feature'];
                $customFeature = Configuration::get('BDROPPY_CUSTOM_FEATURE');
                if (isset($productData['size'])) {
                    if (Tools::strlen($sizeFeatureId) > 0 && $sizeFeatureId > 0 && Tools::strlen($xmlProduct['size']) > 0) {
                        $featureValueId = FeatureValue::addFeatureValueImport(
                            $sizeFeatureId,
                            $productData['size'],
                            $product->id,
                            $lang['id_lang'],
                            $customFeature
                        );
                        Product::addFeatureProductImport($product->id, $sizeFeatureId, $featureValueId);
                    }
                }
                if (Tools::strlen($colorFeatureId) > 0 && $colorFeatureId > 0 && Tools::strlen(self::getColor($xmlProduct, $langCode)) > 0) {
                    $featureValueId = FeatureValue::addFeatureValueImport(
                        $colorFeatureId,
                        self::getColor($xmlProduct, $langCode),
                        $product->id,
                        $lang['id_lang'],
                        $customFeature
                    );
                    Product::addFeatureProductImport($product->id, $colorFeatureId, $featureValueId);
                }
                if (Tools::strlen($genderFeatureId) > 0 && $genderFeatureId > 0 && Tools::strlen(self::getGender($xmlProduct, $langCode)) > 0) {
                    $featureValueId = FeatureValue::addFeatureValueImport(
                        $genderFeatureId,
                        self::getGender($xmlProduct, $langCode),
                        $product->id,
                        $lang['id_lang'],
                        $customFeature
                    );
                    Product::addFeatureProductImport($product->id, $genderFeatureId, $featureValueId);
                }
                if (Tools::strlen($seasonFeatureId) > 0 && $seasonFeatureId > 0 && Tools::strlen(self::getSeason($xmlProduct, $langCode)) > 0) {
                    $featureValueId = FeatureValue::addFeatureValueImport(
                        $seasonFeatureId,
                        self::getSeason($xmlProduct, $langCode),
                        $product->id,
                        $lang['id_lang'],
                        $customFeature
                    );
                    Product::addFeatureProductImport($product->id, $seasonFeatureId, $featureValueId);
                }
            }

            return $product;
        } catch (PrestaShopException $e) {
            self::getLogger()->logDebug( 'populateProductAttributes : ' . $e->getMessage() );
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

    private static function importModels($xmlProduct, Product $product, $default_lang)
    {
        try {
            $xmlModels = $xmlProduct->models;
            $modelCount = 0;

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
                    $sql = "SELECT * FROM "._DB_PREFIX_."attribute a LEFT JOIN "._DB_PREFIX_."attribute_lang al ON (a.id_attribute = al.id_attribute) WHERE a.id_attribute_group = ".Configuration::get('BDROPPY_COLOR')." AND al.name = '" . self::getColor($xmlProduct, $default_lang) . "';";
                    $r = Db::getInstance()->executeS($sql);
                    if ($r) {
                        $attribute = (object)$r[0];
                    } else {
                        $attribute = new Attribute();
                        foreach ($languages as $lang) {
                            $langCode = $langs[$lang['iso_code']];
                            $attribute->name[$lang['id_lang']] = self::getColor($xmlProduct, $langCode);
                        }
                        $attribute->color = self::getColor($xmlProduct, 'en_US');
                        $attribute->id_attribute_group = Configuration::get('BDROPPY_COLOR');
                        $attribute->save();
                        $sql = "SELECT * FROM "._DB_PREFIX_."attribute a LEFT JOIN "._DB_PREFIX_."attribute_lang al ON (a.id_attribute = al.id_attribute) WHERE a.id_attribute_group = ".Configuration::get('BDROPPY_COLOR')." AND al.name = '" . self::getColor($xmlProduct, $default_lang) . "';";
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

                $tax = new Tax(Configuration::get('BDROPPY_TAX_RULE'));
                $rate = 1+$tax->rate/100;
                $user_tax = Configuration::get('BDROPPY_USER_TAX');
                $wholesale_price = round($xmlProduct->bestTaxable, 3);

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
                Db::getInstance()->update('product_attribute', array('wholesale_price'=>$wholesale_price), 'id_product_attribute = '.(int)$idProductAttribute );
                $first = false;
            }

            return $product;
        } catch (PrestaShopException $e) {
            self::getLogger()->logDebug( 'importModels : ' . $e->getMessage() );
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
            $product->isbn = $xmlModel->id;
            $product->reference = self::fitReference((string)$xmlModel->code, $xmlProduct->id);
            StockAvailable::setQuantity($product->id, 0, (int)$xmlModel->availability);

            self::insertNosizeModel($xmlProduct);

            return $product;
        } catch (PrestaShopException $e) {
            self::getLogger()->logDebug( 'importSimpleProduct : ' . $e->getMessage() );
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
                )
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