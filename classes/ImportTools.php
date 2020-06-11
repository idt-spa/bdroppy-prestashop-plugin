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
            $filename = _PS_ROOT_DIR_ . DIRECTORY_SEPARATOR . 'log' . DIRECTORY_SEPARATOR . 'bdroppy-import-'.date('y-m-d').'.log';
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

    public static function updateProductPrices($default_lang, $acceptedlocales)
    {
        $api_catalog = Configuration::get('BDROPPY_CATALOG');
        $api_limit_count = Configuration::get('BDROPPY_LIMIT_COUNT');
        $i = 1;
        $db = Db::getInstance();
        $json = new XMLReader();
        if (!$json->open($api_catalog.'_since.xml')) {
            return false;
        }
        while ($json->read()) {
            if ($json->nodeType==XMLReader::ELEMENT && $json->name == 'item' && $i <= $api_limit_count) {
                $product_xml = $json->readOuterXml();
                $jsonProduct = simplexml_load_string($product_xml, 'SimpleXMLElement', LIBXML_NOBLANKS && LIBXML_NOWARNING);
                $json = json_encode($jsonProduct);
                $product = json_decode($json);
                $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` WHERE rewix_product_id = '". $product->id ."';";
                $items = $db->ExecuteS($sql);
                $updateFlag = 3;
                if (count($items) == 0) {
                    //add product to queue
                    $db->insert('bdroppy_remoteproduct', array(
                        'rewix_product_id' => pSQL($product->id),
                        'rewix_catalog_id' => pSQL($api_catalog),
                        'reference' => pSQL(self::fitReference($product->code, $product->id)),
                        'sync_status' => pSQL('queued'),
                    ));
                    $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` WHERE rewix_product_id = '". $product->id ."';";
                    $items = $db->ExecuteS($sql);
                    foreach ($items as $item) {
                        self::importProduct($item, $default_lang, false, $acceptedlocales);
                    }
                    $i++;
                } else {
                    foreach ($items as $item) {
                        $past = date('Y-m-d H:i:s', strtotime("-1 hour"));
                        if ($item['last_sync_date'] < $past) {
                            self::importProduct($item, $default_lang, $updateFlag);
                            $i++;
                        }
                    }
                }
            }
        }
        $json->close();
    }

    public static function importProduct($item, $default_lang, $updateFlag)
    {
        try {
            @set_time_limit(3600);
            @ini_set('memory_limit', '1024M');

            $api_catalog = Configuration::get('BDROPPY_CATALOG');
            $jsonProduct = json_decode($item['data']);

            if ($jsonProduct) {
                $refId = (int)$jsonProduct->id;
                $sku = (string)$jsonProduct->code;
                $remoteProduct = BdroppyRemoteProduct::fromRewixId($refId);
                $ps_product_id = 0;
                $reference = self::fitReference($jsonProduct->code, (string)$jsonProduct->id);

                if ($item['ps_product_id'] == '0') {
                    $sql = "SELECT * FROM `" . _DB_PREFIX_ . "product` WHERE reference='".$reference."' AND unity='bdroppy-$api_catalog';";
                    $prds = Db::getInstance()->ExecuteS($sql);
                    if (count($prds)>0) {
                        $ps_product_id = $prds[0]['id_product'];
                    }
                } else {
                    $ps_product_id = $item['ps_product_id'];
                }

                $product = new Product($ps_product_id);
                $sql = "SELECT * FROM `" . _DB_PREFIX_ . "product` WHERE id_product<>'".$ps_product_id."' AND reference='$reference' AND unity='bdroppy-$api_catalog';";
                $dps = Db::getInstance()->ExecuteS($sql);
                foreach ($dps as $d) {
                    $dp = new Product($d['id_product']);
                    $dp->delete();
                }
                $product->unity = 'bdroppy-' . $api_catalog;

                $logTxt = 'Importing product ' . $sku . ' with id ' . $jsonProduct->id;
                if ($updateFlag) {
                    $logTxt = 'Updating product ' . $sku . ' with id ' . $jsonProduct->id;
                }

                self::getLogger()->logDebug($logTxt);

                // populate general common fields
                self::populateProductAttributes($jsonProduct, $product, $default_lang);
                if (self::checkSimpleImport($jsonProduct)) {
                    self::getLogger()->logDebug('Product ' . $sku . ' with id ' . $jsonProduct->id . ' will be imported as simple product');
                    self::importSimpleProduct($jsonProduct, $product);
                    $remoteProduct->simple = 1;
                    $remoteProduct->save();
                } else {
                    $product->save();
                    self::importModels($jsonProduct, $product, $default_lang);
                }
                $product->active = (bool)Configuration::get('BDROPPY_ACTIVE_PRODUCT');
                $product->save();
                Db::getInstance()->update('bdroppy_remoteproduct', array('ps_product_id'=>$product->id), 'id = '.$item['id']);

                if (Configuration::get('BDROPPY_IMPORT_IMAGE') || Configuration::get('BDROPPY_REIMPORT_IMAGE')) {
                    self::getLogger()->logDebug('Importing images for product ' . $product->id . ' (' . $jsonProduct->id . ')');
                    self::importProductImages($jsonProduct, $product, Configuration::get('BDROPPY_IMPORT_IMAGE'));
                }

                self::updateImportedProduct($refId, $product->id, $reference);

                return 1;
            }
        } catch (PrestaShopException $e) {
            $logTxt = 'import - importProduct : ' . $e->getMessage();
            if ($updateFlag) {
                $logTxt = 'update - importProduct : ' . $e->getMessage();
            }
            self::getLogger()->logDebug($logTxt);
        }
    }

    private static function getTagValues($tag, $default_lang)
    {
        $value = self::stripTagValues((string)$tag->value);
        $translation = "";

        foreach ($tag->translations as $localeCode => $tr) {
            if ($localeCode == $default_lang) {
                $translation = self::stripTagValues((string)$tr);
            }
        }
        return array(
            'value'       => $value,
            'translation' => Tools::strlen($translation) > 0 ? $translation : $value,
        );
    }

    private static function importProductImages($jsonProduct, $product, $count)
    {
        try {
            $imageCount = 1;
            $websiteUrl = 'https://media.bdroppy.com/storage-foto/prod/';
            if (strpos(Configuration::get('BDROPPY_API_URL'), 'dev') !== false) {
                $websiteUrl = 'https://media.bdroppy.com/storage-foto-dev/prod/';
            } else {
                $websiteUrl = 'https://media.bdroppy.com/storage-foto/prod/';
            }
            $product->deleteImages();

            $i = 0;
            foreach ($jsonProduct->pictures as $image) {
                $imageUrl = "{$websiteUrl}{$image->url}";

                $ch = curl_init($imageUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                $content = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                if ($httpCode != 200) {
                    self::getLogger()->logDebug('Error loading Image: ' . $imageUrl . ' Code :' . $httpCode . '. Error:' . $curlError);
                    $websiteUrl = 'https://branddistributionproddia.blob.core.windows.net/storage-foto-dev/prod/';
                    if (strpos(Configuration::get('BDROPPY_API_URL'), 'dev') !== false) {
                        $websiteUrl = "https://branddistributionproddia.blob.core.windows.net/storage-foto-dev/prod/";
                    } else {
                        $websiteUrl = "https://branddistributionproddia.blob.core.windows.net/storage-foto/prod/";
                    }
                    $imageUrl = "{$websiteUrl}{$image->url}";

                    $ch = curl_init($imageUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    $content = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlError = curl_error($ch);
                    curl_close($ch);
                    if ($httpCode != 200) {
                        self::getLogger()->logDebug('Error loading Image: ' . $imageUrl . ' Code :' . $httpCode . '. Error:' . $curlError);
                    } else {
                        $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'bdroppy_import');
                        $handle = fopen($tmpfile, "w");
                        fwrite($handle, $content);
                        fclose($handle);
                        $image = new Image();
                        $image->id_product = $product->id;
                        $image->position = $imageCount;
                        $image->cover = (int)$imageCount === 1;

                        if (($image->validateFields(false, true)) === true &&
                            ($image->validateFieldsLang(false, true)) === true &&
                            $image->add()
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
                } else {
                    $tmpfile = tempnam(_PS_TMP_IMG_DIR_, 'bdroppy_import');
                    $handle = fopen($tmpfile, "w");
                    fwrite($handle, $content);
                    fclose($handle);
                    $image = new Image();
                    $image->id_product = $product->id;
                    $image->position = $imageCount;
                    $image->cover = (int)$imageCount === 1;

                    if (($image->validateFields(false, true)) === true &&
                        ($image->validateFieldsLang(false, true)) === true &&
                        $image->add()
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
                if ($count != 'all') {
                    if ($i >= $count) {
                        break;
                    }
                }
            }
        } catch (PrestaShopException $e) {
            self::getLogger()->logDebug('importProductImages : ' . $e->getMessage());
        }
    }

    /**
     * @param $tags
     *
     * @return array
     */
    private static function getCategoryIds($tags, $jsonProduct)
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
                    $jsonProduct
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
    private static function updateImportedProduct($refId, $productId, $reference)
    {
        try {
            $remoteProduct = BdroppyRemoteProduct::fromRewixId($refId);
            if ($remoteProduct->ps_product_id < 1) {
                $remoteProduct->ps_product_id = $productId;
            }

            $remoteProduct->sync_status = BdroppyRemoteProduct::SYNC_STATUS_UPDATED;
            $remoteProduct->reference = $reference;
            $remoteProduct->imported = 1;
            $remoteProduct->last_sync_date = date('Y-m-d H:i:s');
            $remoteProduct->priority = 0;
            $remoteProduct->reason = '';
            $remoteProduct->save();
        } catch (PrestaShopException $e) {
            self::getLogger()->logDebug('updateImportedProduct : ' . $e->getMessage());
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
                $jsonProduct = self::getProductXML($reader);
                $product = self::populateProduct($jsonProduct);

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
        foreach ($product->tags as $tag) {
            if ($tag->name === $name) {
                if (isset($tag->value->translations->{$lang})) {
                    return $tag->value->translations->{$lang};
                } else {
                    return $tag->value->value;
                }
            }
        }
    }

    private static function getCategory($product, $lang)
    {
        return self::getTagValue($product, 'category', $lang);
    }

    private static function getSubCategory($product, $lang)
    {
        return self::getTagValue($product, 'subcategory', $lang);
    }

    private static function getDescriptions($product, $lang)
    {
        if (isset($product->descriptions->{$lang})) {
            return @$product->descriptions->{$lang};
        } else {
            return "";
        }
    }

    private static function getBrand($product, $lang)
    {
        return self::getTagValue($product, 'brand', $lang);
    }

    private static function getGender($product, $lang)
    {
        return self::getTagValue($product, 'gender', $lang);
    }

    private static function getSeason($product, $lang)
    {
        return self::getTagValue($product, 'season', $lang);
    }

    private static function getColor($product, $lang)
    {
        return self::getTagValue($product, 'color', $lang);
    }

    private static function getName($product, $lang)
    {
        if (!empty(self::getTagValue($product, 'productname', $lang))) {
            return self::getTagValue($product, 'productname', $lang);
        } else {
            return $product->name;
        }
    }

    private static function populateProduct($json, $default_lang, $checkImported = true)
    {
        $sku = (string)$json->code;
        $refId = (int)$json->id;

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

        foreach ($json->tags as $tag) {
            if (in_array((int)$tag->id, array_keys($tags))) {
                $tags[(int)$tag->id] = self::getTagValues($tag->value, $default_lang);
            }
        }

        $name = (string)$json->name;
        $price = (float)$json->sellPrice;
        $priceTax = (float)$json->sellPrice;
        $bestTaxable = (float)$json->bestTaxable;
        $taxable = (float)$json->taxable;
        $suggested = (float)$json->suggestedPrice;
        $streetPrice = (float)$json->streetPrice;
        $availability = (int)$json->availability;

        if ($checkImported) {
            $remoteProduct = BdroppyRemoteProduct::fromRewixId($refId);
            $imported = (bool)$remoteProduct->imported;
        } else {
            $imported = false;
        }

        $description = (array)$json->descriptions;

        $product = array(
            'remote_id'             => $refId,
            'name'                  => $name,
            'pictures'              => $json->pictures,
            'imported'              => $imported,
            'brand'                 => self::getBrand($json, $default_lang),
            'category'              => self::getCategory($json, $default_lang),
            'subcategory'           => self::getSubcategory($json, $default_lang),
            'gender'                => self::getGender($json, $default_lang),
            'color'                 => self::getColor($json, $default_lang),
            'season'                => self::getSeason($json, $default_lang),
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
            'weight'                => $json->weight,
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

    private static function populateProductAttributes($jsonProduct, Product $product, $default_lang)
    {
        try {
            $sizeFeatureId = '';
            $colorFeatureId = '';
            $genderFeatureId = '';
            $seasonFeatureId = '';
            $productData = self::populateProduct($jsonProduct, $default_lang, true);
            $product->reference = self::fitReference($productData['code'], $jsonProduct->id);
            $product->weight = (float)$jsonProduct->weight;

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
                if (Configuration::get('BDROPPY_IMPORT_BRAND_TO_TITLE')) {
                    $name = $productData['brand'] . ' - ' . $productData['name'];
                } else {
                    $name = $productData['name'];
                }
                $pname_tag = Configuration::get('BDROPPY_IMPORT_TAG_TO_TITLE');
                if ($pname_tag == 'color') {
                    $tag = self::getColor($jsonProduct, $langCode);
                    if (!empty($tag)) {
                        $name .= ' - ' . $tag;
                    }
                }
                $product->name[$lang['id_lang']] = $name;
                $product->link_rewrite[$lang['id_lang']] = Tools::link_rewrite("{$productData['brand']}-{$productData['code']}");
                $product->description[$lang['id_lang']] = self::getDescriptions($jsonProduct, $langCode);
                $product->description_short[$lang['id_lang']] = Tools::substr(self::getDescriptions($jsonProduct, $langCode), 0, 800);
            }

            if (!isset($product->date_add) || empty($product->date_add)) {
                $product->date_add = date('Y-m-d H:i:s');
            }
            $product->date_upd = date('Y-m-d H:i:s');

            $product->id_manufacturer = self::getManufacturer($productData['brand']);
            list($categories, $categoryDefaultId) = self::getCategoryIds($productData['tags'], $jsonProduct);
            $product->id_category_default = $categoryDefaultId;

            $product->active = (bool)Configuration::get('BDROPPY_ACTIVE_PRODUCT');
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

            $product->deleteFeatures();
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

                $sizeFeatureId = '';
                $colorFeatureId = '';
                $genderFeatureId = '';
                $seasonFeatureId = '';
                if ($sizeFeature) {
                    $sizeFeatureId = $sizeFeature[0]['id_feature'];
                }
                if ($colorFeature) {
                    $colorFeatureId = $colorFeature[0]['id_feature'];
                }
                if ($genderFeature) {
                    $genderFeatureId = $genderFeature[0]['id_feature'];
                }
                if ($seasonFeature) {
                    $seasonFeatureId = $seasonFeature[0]['id_feature'];
                }
                $customFeature = (bool)Configuration::get('BDROPPY_CUSTOM_FEATURE');
                if (isset($productData['size'])) {
                    if (Tools::strlen($sizeFeatureId) > 0 && $sizeFeatureId > 0 && Tools::strlen($jsonProduct['size']) > 0) {
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
                $fColor = self::getColor($jsonProduct, $langCode);
                if (Tools::strlen($colorFeatureId) > 0 && $colorFeatureId > 0 && Tools::strlen($fColor) > 0) {
                    $featureValueId = FeatureValue::addFeatureValueImport(
                        $colorFeatureId,
                        $fColor,
                        $product->id,
                        $lang['id_lang'],
                        $customFeature
                    );
                    Product::addFeatureProductImport($product->id, $colorFeatureId, $featureValueId);
                }
                $fGender = self::getGender($jsonProduct, $langCode);
                if (Tools::strlen($genderFeatureId) > 0 && $genderFeatureId > 0 && Tools::strlen($fGender) > 0) {
                    $featureValueId = FeatureValue::addFeatureValueImport(
                        $genderFeatureId,
                        $fGender,
                        $product->id,
                        $lang['id_lang'],
                        $customFeature
                    );
                    Product::addFeatureProductImport($product->id, $genderFeatureId, $featureValueId);
                }
                $fSeason = self::getSeason($jsonProduct, $langCode);
                if (Tools::strlen($seasonFeatureId) > 0 && $seasonFeatureId > 0 && Tools::strlen($fSeason) > 0) {
                    $featureValueId = FeatureValue::addFeatureValueImport(
                        $seasonFeatureId,
                        $fSeason,
                        $product->id,
                        $lang['id_lang'],
                        $customFeature
                    );
                    Product::addFeatureProductImport($product->id, $seasonFeatureId, $featureValueId);
                }
            }

            return $product;
        } catch (PrestaShopException $e) {
            self::getLogger()->logDebug('populateProductAttributes : ' . $e->getMessage());
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

    private static function importModels($jsonProduct, Product $product, $default_lang)
    {
        try {
            $jsonModels = $jsonProduct->models;

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
            $product->deleteProductAttributes();
            foreach ($jsonModels as $model) {
                self::getSizeAttributeFromValue((string)$model->size);
                $quantity = (int)$model->availability;
                $reference = self::fitModelReference((string)$model->code, (string)$model->size);
                $ean13 = trim((string)$model->barcode);
                if (Tools::strlen($ean13)>13) {
                    $ean13 = Tools::substr($ean13, 0, 13);
                }

                $combinationAttributes = array();

                if ($model->color) {
                    $sql = "SELECT * FROM "._DB_PREFIX_."attribute a LEFT JOIN "._DB_PREFIX_."attribute_lang al ON (a.id_attribute = al.id_attribute) WHERE a.id_attribute_group = ".Configuration::get('BDROPPY_COLOR')." AND al.name = '" . self::getColor($jsonProduct, $default_lang) . "';";
                    $r = Db::getInstance()->executeS($sql);
                    if ($r) {
                        $attribute = (object)$r[0];
                    } else {
                        $attribute = new Attribute();
                        foreach ($languages as $lang) {
                            $langCode = $langs[$lang['iso_code']];
                            $attribute->name[$lang['id_lang']] = self::getColor($jsonProduct, $langCode);
                        }
                        $attribute->color = self::getColor($jsonProduct, 'en_US');
                        $attribute->id_attribute_group = Configuration::get('BDROPPY_COLOR');
                        $attribute->save();
                        $sql = "SELECT * FROM "._DB_PREFIX_."attribute a LEFT JOIN "._DB_PREFIX_."attribute_lang al ON (a.id_attribute = al.id_attribute) WHERE a.id_attribute_group = ".Configuration::get('BDROPPY_COLOR')." AND al.name = '" . self::getColor($jsonProduct, $default_lang) . "';";
                        $r = Db::getInstance()->executeS($sql);
                        if ($r) {
                            $attribute = (object)$r[0];
                        }
                    }
                    $combinationAttributes[] = $attribute->id_attribute;
                }
                if ($model->size) {
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

                //$tax = new Tax(Configuration::get('BDROPPY_TAX_RULE'));
                //$rate = 1+$tax->rate/100;
                //$user_tax = Configuration::get('BDROPPY_USER_TAX');
                $wholesale_price = round($jsonProduct->bestTaxable, 3);

                $impact_on_price_per_unit = 0;
                $impact_on_price = 0;
                $impact_on_weight = $jsonProduct->weight;
                $isbn_code = $model->id;
                $id_supplier = null;
                $default = $first;
                $location = null;
                $id_images = null;
                $upc = null;
                $minimal_quantity = 1;
                $idProductAttribute = $product->addProductAttribute((float)$impact_on_price, (float)$impact_on_weight, $impact_on_price_per_unit, null, (int)$quantity, $id_images, $reference, $id_supplier, $ean13, $default, $location, $upc, null, $isbn_code, $minimal_quantity);
                $r = $product->addAttributeCombinaison($idProductAttribute, $combinationAttributes);
                Db::getInstance()->update('product_attribute', array('wholesale_price'=>$wholesale_price), 'id_product_attribute = '.(int)$idProductAttribute);
                $first = false;
            }

            return $product;
        } catch (PrestaShopException $e) {
            self::getLogger()->logDebug('importModels : ' . $e->getMessage());
        }
    }

    private static function checkNosizeModel($jsonProduct, Product $product)
    {
        if (BdroppyRemoteCombination::countByRewixProductId($product->id) == 0) {
            self::insertNosizeModel($jsonProduct);
        }
    }

    /** In case of simple product, nosize combination is stored in remote_combination table for sending right variation with orders */
    private static function insertNosizeModel($jsonProduct)
    {
        $jsonModel = $jsonProduct->models[0];

        $remoteCombination = BdroppyRemoteCombination::fromRewixId((int)$jsonModel->id);
        $remoteCombination->rewix_product_id = (int) $jsonProduct->id;

        //$reference = self::fitModelReference((string)$jsonModel->code, (string)$jsonModel->size);
        //$ean13 = trim((string)$jsonModel->barcode);

        $remoteCombination->ps_model_id = 0;
        $remoteCombination->save();
    }

    private static function importSimpleProduct($jsonProduct, Product $product)
    {
        try {
            $jsonModel = $jsonProduct->models[0];
            $product->minimal_quantity = 1;
            $product->ean13 = (string)$jsonModel->barcode;
            $product->isbn = $jsonModel->id;
            $product->reference = self::fitReference((string)$jsonModel->code, $jsonProduct->id);
            StockAvailable::setQuantity($product->id, 0, (int)$jsonModel->availability);

            //self::insertNosizeModel($jsonProduct);

            return $product;
        } catch (PrestaShopException $e) {
            self::getLogger()->logDebug('importSimpleProduct : ' . $e->getMessage());
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

    private static function checkSimpleImport($jsonProduct)
    {
        if (count($jsonProduct->models) == 1) {
            $jsonModel = $jsonProduct->models[0];
            $size = (string)$jsonModel->size;
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
        $ean = (string)$ean;
        $id = (string)$id;
        if (Tools::strlen($ean) > 32) {
            $ean = Tools::substr($ean, 0, 32 - Tools::strlen($id));
            $ean .= $id;
        }
        return $ean;
    }

    public static function sendOtherOrders()
    {
        $rewixApi = new BdroppyRewixApi();
        $yesterday = date('Y-m-d H:i:s', strtotime("-1 hour"));
        $query = new DbQuery();
        $query->select("*")
            ->from("orders")
            ->where("date_add >= '$yesterday'");
        $dorders = Db::getInstance()->executeS($query);
        foreach ($dorders as $item) {
            $oquery = new DbQuery();
            $oquery->select("*")
                ->from("bdroppy_remoteorder")
                ->where("ps_order_id = '".$item['id_order']."'");
            $remoteOrder = Db::getInstance()->executeS($oquery);
            if (!$remoteOrder) {
                $rewixApi->sendBdroppyOrder(new Order((int)$item['id_order']));
            }
        }
    }

    public static function syncWithSupplier()
    {
        $rewixApi = new BdroppyRewixApi();
        $rewixApi->updateOrderStatuses();
        $rewixApi->syncBookedProducts();
        $rewixApi->sendMissingOrders();
    }
}
