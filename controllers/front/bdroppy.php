<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
include_once dirname(__FILE__).'/../../classes/ImportTools.php';

class bdroppyBdroppyModuleFrontController extends ModuleFrontController
{
    private $base_url;
    private $api_key;
    private $api_password;
    private $api_method;
    private $api_catalog;
    private $api_size;
    private $api_gender;
    private $api_color;
    private $api_season;
    private $api_import_image;
    private $api_import_retail;
    private $api_category_structure;
    private $api_limit_count;
    private $default_lang;
    private $product;
    private $category;
    private $subcategory;
    private $color;
    private $season;
    private $brand;
    private $gender;
    private $db;
    private $debugImportFile = 'bdroppy_import_debug.txt';
    public function __construct()
    {
            $this->context = Context::getContext();
            $this->context->controller = $this;
            $this->default_lang = str_replace('-', '_', $this->context->language->locale);

            //$res = Db::getInstance()->update('bdroppy_remoteproduct', array('sync_status'=>'queued', 'imported'=>0), 'id = 6706');
            //var_dump($res); die;

            $configurations = array(
                'BDROPPY_API_URL' => Configuration::get('BDROPPY_API_URL', true),
                'BDROPPY_API_KEY' => Configuration::get('BDROPPY_API_KEY', null),
                'BDROPPY_API_PASSWORD' => Configuration::get('BDROPPY_API_PASSWORD', null),
                'BDROPPY_CATALOG' => Configuration::get('BDROPPY_CATALOG', null),
                'BDROPPY_SIZE' => Configuration::get('BDROPPY_SIZE', null),
                'BDROPPY_GENDER' => Configuration::get('BDROPPY_GENDER', null),
                'BDROPPY_COLOR' => Configuration::get('BDROPPY_COLOR', null),
                'BDROPPY_SEASON' => Configuration::get('BDROPPY_SEASON', null),
                'BDROPPY_CATEGORY_STRUCTURE' => Configuration::get('BDROPPY_CATEGORY_STRUCTURE', null),
                'BDROPPY_IMPORT_IMAGE' => Configuration::get('BDROPPY_IMPORT_IMAGE', null),
                'BDROPPY_LIMIT_COUNT' => Configuration::get('BDROPPY_LIMIT_COUNT', null),
            );

            $this->db = Db::getInstance();
            $this->base_url = isset($configurations['BDROPPY_API_URL']) ? $configurations['BDROPPY_API_URL'] : '';
            $this->api_key = isset($configurations['BDROPPY_API_KEY']) ? $configurations['BDROPPY_API_KEY'] : '';
            $this->api_password = isset($configurations['BDROPPY_API_PASSWORD']) ? $configurations['BDROPPY_API_PASSWORD'] : '';
            $this->api_catalog = isset($configurations['BDROPPY_CATALOG']) ? $configurations['BDROPPY_CATALOG'] : '';
            $this->api_size = isset($configurations['BDROPPY_SIZE']) ? $configurations['BDROPPY_SIZE'] : '';
            $this->api_gender = isset($configurations['BDROPPY_GENDER']) ? $configurations['BDROPPY_GENDER'] : '';
            $this->api_color = isset($configurations['BDROPPY_COLOR']) ? $configurations['BDROPPY_COLOR'] : '';
            $this->api_season = isset($configurations['BDROPPY_SEASON']) ? $configurations['BDROPPY_SEASON'] : '';
            $this->api_category_structure = isset($configurations['BDROPPY_CATEGORY_STRUCTURE']) ? $configurations['BDROPPY_CATEGORY_STRUCTURE'] : '';
            $this->api_import_image = isset($configurations['BDROPPY_IMPORT_IMAGE']) ? $configurations['BDROPPY_IMPORT_IMAGE'] : '';
            $this->api_limit_count = isset($configurations['BDROPPY_LIMIT_COUNT']) ? $configurations['BDROPPY_LIMIT_COUNT'] : 5;

    }

    public function initContent() {
        try {
            header('Access-Control-Allow-Origin: *');
            @ini_set('max_execution_time', 100000);
            if($this->api_catalog == "" || $this->api_catalog == "0") {
                $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct`;";
                $delete_products = $this->db->ExecuteS($sql);
                foreach ($delete_products as $item) {
                    switch ($item['sync_status']) {
                        case 'queued':
                        case 'delete':
                            Db::getInstance()->delete('bdroppy_remoteproduct', "rewix_product_id = '" . $item['rewix_product_id'] . "'");
                            break;
                        case 'updated':
                            $product = new Product($item['ps_product_id']);
                            $product->delete();
                            Db::getInstance()->delete('bdroppy_remoteproduct', "rewix_product_id = '" . $item['rewix_product_id'] . "'");
                            break;
                    }
                }
            } else {
                if (!$this->api_limit_count)
                    $this->api_limit_count = 5;
                $url = $this->base_url . '/restful/export/api/products.json?user_catalog=' . $this->api_catalog . '&acceptedlocales=en_US&onlyid=true';

                $header = "authorization: Basic " . base64_encode($this->api_key . ':' . $this->api_password);
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('accept: application/json', 'Content-Type: application/json', $header));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
                $data = curl_exec($ch);
                $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curl_error = curl_error($ch);
                curl_close($ch);

                //echo "<pre>";var_dump($this->api_catalog, $url, $http_code, $data);die;
                if ($http_code === 200 && $data != "null") {
                    $ids = [];
                    $catalog = json_decode($data);
                    $sql = "SELECT rewix_product_id FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` WHERE (sync_status = 'queued' OR sync_status = 'updated' OR sync_status = 'importing');";
                    $prds = Db::getInstance()->ExecuteS($sql);
                    foreach ($catalog->items as $item) {
                        $ids[] = $item->refId;
                    }
                    $products = array_map(function ($item) {
                        return (integer)$item['rewix_product_id'];
                    }, $prds);
                    $add_products = array_diff($ids, $products);

                    $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` WHERE rewix_catalog_id <> '" . $this->api_catalog . "';";
                    $delete_products = Db::getInstance()->ExecuteS($sql);
                    //echo"<pre>";var_dump($ids, $prds, $add_products, $delete_products);die;

                    foreach ($delete_products as $item) {
                        switch ($item['sync_status']) {
                            case 'queued':
                            case 'delete':
                                Db::getInstance()->delete('bdroppy_remoteproduct', "rewix_product_id = '" . $item['rewix_product_id'] . "'");
                                break;
                            case 'updated':
                                $product = new Product($item['ps_product_id']);
                                $product->delete();
                                Db::getInstance()->delete('bdroppy_remoteproduct', "rewix_product_id = '" . $item['rewix_product_id'] . "'");
                                break;
                        }
                    }
                    foreach ($add_products as $item) {
                        Db::getInstance()->insert('bdroppy_remoteproduct', array(
                            'rewix_product_id' => pSQL($item),
                            'rewix_catalog_id' => pSQL($this->api_catalog),
                            'sync_status' => pSQL('queued'),
                        ));
                    }
                }

                // select 10 products to import
                $sql = "SELECT * FROM `" . _DB_PREFIX_ . "bdroppy_remoteproduct` WHERE sync_status='queued' LIMIT " . $this->api_limit_count . ";";
                $items = Db::getInstance()->ExecuteS($sql);
                foreach ($items as $item) {
                    $res = Db::getInstance()->update('bdroppy_remoteproduct', array('sync_status' => 'importing'), 'id = ' . $item['id']);
                }
                foreach ($items as $item) {
                    if ($item['sync_status'] == 'queued') {
                        if ($this->default_lang == 'en_GB')
                            $this->default_lang = 'en_US';;
                        $this->xmlPath = BdroppyImportTools::importProduct($item, $this->default_lang);
                        //$this->importToPS($item);
                    }
                    if ($item['sync_status'] == 'delete') {
                        $res = Db::getInstance()->update('bdroppy_remoteproduct', array('sync_status' => 'deleted', 'imported' => 0), 'id = ' . $item['id']);
                        //$this->removeFromPS($item);
                    }
                }
            }
            parent::initContent();
            $this->context->smarty->assign(array(
                'msg' => 'Importing...',
            ));
            $this->setTemplate('module:mk_formpage/views/templates/front/import.tpl');
        } catch (PrestaShopException $e) {
            echo "<pre>";var_dump('000', $e->getMessage(), $e);
            return false;
        }
    }
    private function removeFromPS($item) {
        //TODO remove product function
    }

    protected static function _addFeatureValue($featureId, $langFeaturesValues)
    {
        if (!$featureId || $featureId == 0 || !is_array($langFeaturesValues))
            return false;

        $sql = '
            SELECT fv.`id_feature_value`
            FROM ' . _DB_PREFIX_ . 'feature_value fv
            LEFT JOIN ' . _DB_PREFIX_ . 'feature_value_lang fvl ON (fvl.`id_feature_value` = fv.`id_feature_value`)
            WHERE fvl.`value` = \'' . pSQL(current($langFeaturesValues)) . '\'
                AND fv.`id_feature` = ' . (int)$featureId . '
                AND fvl.`id_lang` = ' . pSQL(key($langFeaturesValues)) . '
            GROUP BY fv.`id_feature_value` LIMIT 1
        ';

        $result = Db::getInstance()->executeS($sql);
        if (!$result || empty($result)) {
            $featureValue             = new FeatureValue();
            $featureValue->id_feature = (int)$featureId;
        } else {
            $featureValue             = new FeatureValue((int)$result[0]['id_feature_value']);
        }

        $featureValue->value  = $langFeaturesValues;
        $featureValue->custom = 0;

        if (!$featureValue->save()) {
            return false;
        } else {
            return $featureValue->id;
        }
    }

    protected function _saveCustomFeature($product, $featureId, $featureCode, $values)
    {
        try {
            $featureValueId = $this->_addFeatureValue($featureId, $values);
        } catch (PrestaShopException $e) {
            var_dump('Error while saving feature value for ' . $featureCode . ' : ' . $e->getMessage());
            return false;
        }
        if (!$featureValueId) {
            var_dump($product->reference . ' : could not save feature value for ' . $featureCode);
            return false;
        }

        if (!Product::addFeatureProductImport($product->id, $featureId, $featureValueId)) {
            var_dump($product->reference . ' : could not associate to feature value ' . $featureCode . ' for feature ' . $featureCode);
            return false;
        }

        return true;
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
    function copyImg2($id_entity, $id_image, $url, $entity = 'products', $regenerate = true) {
        try {
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
            }
            $url = str_replace(' ', '%20', trim($url));


            // Evaluate the memory required to resize the image: if it's too much, you can't resize it.
            file_put_contents($this->debugImportFile, "checkImageMemoryLimit : " . ImageManager::checkImageMemoryLimit($url) . "\n", FILE_APPEND);
            if (!ImageManager::checkImageMemoryLimit($url))
                return false;


            // 'file_exists' doesn't work on distant file, and getimagesize makes the import slower.
            // Just hide the warning, the processing will be the same.
            $flg = Tools::copy($url, $tmpfile);
            file_put_contents($this->debugImportFile, "Tools::copy : " . $flg . "\n", FILE_APPEND);
            if ($flg) {
                $r = ImageManager::resize($tmpfile, $path . '.jpg');
                file_put_contents($this->debugImportFile, "ImageManager::resize : " . var_export($r, true) . "\n", FILE_APPEND);
                $images_types = ImageType::getImagesTypes($entity);

                if ($regenerate)
                    foreach ($images_types as $image_type) {
                        file_put_contents($this->debugImportFile, "tmpfile : $tmpfile - path : $path - image_type : " . var_export($image_type, true) . " - result : ", FILE_APPEND);
                        $r2 = ImageManager::resize($tmpfile, $path . '-' . stripslashes($image_type['name']) . '.jpg', $image_type['width'], $image_type['height']);
                        file_put_contents($this->debugImportFile, "$r2\n", FILE_APPEND);
                        if (in_array($image_type['id_image_type'], $watermark_types))
                            Hook::exec('actionWatermark', array('id_image' => $id_image, 'id_product' => $id_entity));
                    }
            } else {
                unlink($tmpfile);
                file_put_contents($this->debugImportFile, date('Y-m-d H:i:s') . " - Error in Copying Image : $url \n", FILE_APPEND);
                return false;
            }
            unlink($tmpfile);
            file_put_contents($this->debugImportFile, date('Y-m-d H:i:s') . " - Image Attached : $url\n", FILE_APPEND);
            return true;
        } catch (PrestaShopException $e) {
            file_put_contents($this->debugImportFile, date('Y-m-d H:i:s') . " - Error in Attaching Image : $url - " . var_export($e, true). "\n", FILE_APPEND);
            return false;
        }
    }


    public function getTagValue($name,$lang)
    {
        foreach ($this->product->tags as $tag)
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

    public function getCategory($lang){
        return $this->getTagValue('category','$lang');
    }

    public function getSubCategory($lang){
        return $this->getTagValue('subcategory',$lang);
    }

    public function getBrand($lang){
        return $this->getTagValue('brand',$lang);
    }
    public function getGender($lang){
        return $this->getTagValue('gender',$lang);
    }
    public function getSeason($lang){
        return $this->getTagValue('season',$lang);
    }

    public function getColor($lang){
        return $this->getTagValue('color',$lang);
    }

    public function getName($lang){
        if(!empty($this->getTagValue('productname',$lang)))
        {
            return $this->getTagValue('productname',$lang);
        }else{
            return $this->name;
        }
    }

    public function getImage()
    {
        if(isset($this->product->pictures[0]->url))
            return 'https://www.brandsdistribution.com'.$this->product->pictures[0]->url;
        else
            return BDROPPY_IMG .'no_image.png';
    }

    public function getDescriptions($lang)
    {
        if (isset($this->product->descriptions->{$lang}))
        {
            return @$this->product->descriptions->{$lang};
        }else{
            return "";
        }

    }
    public function isSimpleProduct( )
    {
        if ( count( $this->product->models ) == 1 ) {
            if ( (string) $this->product->models[0]->size == 'NOSIZE' ) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    private function importToPS($item) {
        try {
            $product_id = $item['rewix_product_id'];
            $catalog_id = $item['rewix_catalog_id'];

            //$product_id = '81920';
            //$catalog_id = '5dfb91a2fafa824681ac2092';

            $url = $this->base_url . "/restful/product/$product_id/usercatalog/$catalog_id";
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 5000);
            curl_setopt($ch, CURLOPT_USERPWD, $this->api_key . ':' . $this->api_password);
            $data = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            file_put_contents($this->debugImportFile, "************************************************************\n", FILE_APPEND);
            file_put_contents($this->debugImportFile, date('Y-m-d H:i:s') . " - 1 - $product_id - http_code: $http_code\n", FILE_APPEND);
            //var_dump($product_id, $catalog_id, $http_code, $data);die;
            if ($http_code === 200) {
                $this->product = json_decode($data);
                echo "<pre>";
                $languages = Language::getLanguages(false);
                $this->color = $this->getColor($this->default_lang);
                $this->brand = $this->getBrand($this->default_lang);
                $this->gender = $this->getGender($this->default_lang);
                $this->category = $this->getCategory($this->default_lang);
                $this->subcategory = $this->getSubCategory($this->default_lang);
                $this->season = $this->getSeason($this->default_lang);

                file_put_contents($this->debugImportFile, date('Y-m-d H:i:s') . " - 2 - color : " . $this->color . "\n", FILE_APPEND);
                file_put_contents($this->debugImportFile, date('Y-m-d H:i:s') . " - 3 - brand : " . $this->brand . "\n", FILE_APPEND);
                file_put_contents($this->debugImportFile, date('Y-m-d H:i:s') . " - 4 - gender : " . $this->gender . "\n", FILE_APPEND);
                file_put_contents($this->debugImportFile, date('Y-m-d H:i:s') . " - 5 - category : " . $this->category . "\n", FILE_APPEND);
                file_put_contents($this->debugImportFile, date('Y-m-d H:i:s') . " - 6 - subcategory : " . $this->subcategory . "\n", FILE_APPEND);
                file_put_contents($this->debugImportFile, date('Y-m-d H:i:s') . " - 7 - season : " . $this->season . "\n", FILE_APPEND);
                //var_dump($this->product, $this->color, $this->brand, $this->gender, $this->category, $this->subcategory, $this->season);die;
                if($this->api_category_structure == '1') {
                    // Category > Subcategory
                    $sql = "SELECT * FROM "._DB_PREFIX_."category c LEFT JOIN "._DB_PREFIX_."category_lang cl ON (c.id_category = cl.id_category AND cl.id_shop = c.id_shop_default) WHERE cl.name = '".$this->category."' AND c.id_parent = '".Configuration::get('PS_HOME_CATEGORY')."';";
                    $r = Db::getInstance()->executeS($sql);
                    if($r) {
                        $cat = (object) $r[0];
                    } else {
                        $cat = new Category();
                        $cat->id_parent = Configuration::get('PS_HOME_CATEGORY');
                        foreach ($languages as $lang){
                            $langCode = str_replace('-', '_', $lang['locale']);
                            $name = $this->getCategory($langCode);
                            $cat->name[$lang['id_lang']] = $name;
                            $cat->description[$lang['id_lang']] = $name;
                            $cat->link_rewrite[$lang['id_lang']] = Tools::link_rewrite($name);
                        }
                        $cat->save();
                        $cat->id_category = $cat->id;
                    }
                    $sql = "SELECT * FROM "._DB_PREFIX_."category c LEFT JOIN "._DB_PREFIX_."category_lang cl ON (c.id_category = cl.id_category AND cl.id_shop = c.id_shop_default) WHERE cl.name = '".$this->subcategory."' AND c.id_parent = '".$cat->id_category."';";
                    $r = Db::getInstance()->executeS($sql);
                    if($r) {
                        $subCat = (object) $r[0];
                    } else {
                        $subCat = new Category();
                        $subCat->id_parent = $cat->id_category;
                        foreach ($languages as $lang){
                            $langCode = str_replace('-', '_', $lang['locale']);
                            $name = $this->getSubCategory($langCode);
                            $subCat->name[$lang['id_lang']] = $name;
                            $subCat->description[$lang['id_lang']] = $name;
                            $subCat->link_rewrite[$lang['id_lang']] = Tools::link_rewrite($name);
                        }
                        $subCat->save();
                        $subCat->id_category = $subCat->id;
                    }
                }
                if($this->api_category_structure == '2') {
                    // Gender > Category > Subcategory
                    if($this->gender != null) {
                        $sql = "SELECT * FROM "._DB_PREFIX_."category c LEFT JOIN "._DB_PREFIX_."category_lang cl ON (c.id_category = cl.id_category AND cl.id_shop = c.id_shop_default) WHERE cl.name = '".$this->gender."' AND c.id_parent = ".Configuration::get('PS_HOME_CATEGORY').";";
                        $r = Db::getInstance()->executeS($sql);
                        if($r) {
                            $gender = (object) $r[0];
                        } else {
                            // not exist make gender category
                            $gender = new Category();
                            $gender->id_parent = Configuration::get('PS_HOME_CATEGORY');
                            foreach ($languages as $lang){
                                $langCode = str_replace('-', '_', $lang['locale']);
                                $name = $this->getGender($langCode);
                                $gender->name[$lang['id_lang']] = $name;
                                $gender->description[$lang['id_lang']] = $name;
                                $gender->link_rewrite[$lang['id_lang']] = Tools::link_rewrite($name);
                            }
                            $gender->save();
                            $gender->id_category = $gender->id;
                        }
                    } else {
                        $gender = new Category();
                        $gender->id_category = Configuration::get('PS_HOME_CATEGORY');
                    }
                    $sql = "SELECT * FROM "._DB_PREFIX_."category c LEFT JOIN "._DB_PREFIX_."category_lang cl ON (c.id_category = cl.id_category AND cl.id_shop = c.id_shop_default) WHERE cl.name = '".$this->category."' AND c.id_parent = '".$gender->id_category."';";
                    $r = Db::getInstance()->executeS($sql);
                    if($r) {
                        $cat = (object) $r[0];
                    } else {
                        $cat = new Category();
                        $cat->id_parent = $gender->id_category;
                        foreach ($languages as $lang){
                            $langCode = str_replace('-', '_', $lang['locale']);
                            $name = $this->getCategory($langCode);
                            $cat->name[$lang['id_lang']] = $name;
                            $cat->description[$lang['id_lang']] = $name;
                            $cat->link_rewrite[$lang['id_lang']] = Tools::link_rewrite($name);
                        }
                        $cat->save();
                        $cat->id_category = $cat->id;
                    }
                    $sql = "SELECT * FROM "._DB_PREFIX_."category c LEFT JOIN "._DB_PREFIX_."category_lang cl ON (c.id_category = cl.id_category AND cl.id_shop = c.id_shop_default) WHERE cl.name = '".$this->subcategory."' AND c.id_parent = '".$cat->id_category."';";
                    $r = Db::getInstance()->executeS($sql);
                    if($r) {
                        $subCat = (object) $r[0];
                    } else {
                        try{
                            $subCat = new Category();
                            $subCat->id_parent = (int)$cat->id_category;
                            foreach ($languages as $lang){
                                $langCode = str_replace('-', '_', $lang['locale']);
                                $name = $this->getSubCategory($langCode);
                                $name = trim($name);
                                $subCat->name[$lang['id_lang']] = $name;
                                $subCat->description[$lang['id_lang']] = $name;
                                $subCat->link_rewrite[$lang['id_lang']] = Tools::link_rewrite($name);
                            }
                            $subCat->save();
                            $subCat->id_category = $subCat->id;
                        } catch (PrestaShopException $e) {
                            file_put_contents($this->debugImportFile, date('Y-m-d H:i:s') . " - Error On Saving Subcategory\n", FILE_APPEND);
                        }
                    }
                }

                $id_manufacturer = 0;
                $manufacturer = Db::getInstance()->executeS("SELECT * FROM `"._DB_PREFIX_."manufacturer` WHERE name='".$this->brand."'");
                if($manufacturer) {
                    $id_manufacturer = $manufacturer[0]['id_manufacturer'];
                } else {
                    $manufacturer = new Manufacturer();
                    $manufacturer->name = $this->brand;
                    $manufacturer->active = true;
                    $manufacturer->save();
                    $id_manufacturer = $manufacturer->id;
                }

                $product = new Product();
                foreach ($languages as $lang) {
                    $langCode = str_replace('-', '_', $lang['locale']);
                    $product->name[$lang['id_lang']] =  $this->getName($langCode);
                    $product->description[$lang['id_lang']] = $this->getDescriptions($langCode);
                    $product->description_short[$lang['id_lang']] = $this->getDescriptions($langCode);
                    $product->link_rewrite[$lang['id_lang']] = Tools::link_rewrite($this->getName($langCode));
                }

                $product->weight = $this->product->weight;
                $product->reference = $this->product->code;
                $product->id_category[] = $subCat->id_category;
                $product->ean13 = "0";
                $product->upc = "";
                $product->id_tax_rules_group = 0;
                $product->id_category_default = $subCat->id_category;
                $product->is_virtual = "0";
                $product->price = $this->product->sellPrice;
                $product->quantity = $this->product->availability;
                $product->id_manufacturer = $id_manufacturer;
                $product->save();
                $product->addToCategories(array($subCat->id_category));
                file_put_contents($this->debugImportFile, date('Y-m-d H:i:s') . " - Product Saved(".$product->id.")\n", FILE_APPEND);
                $sql = "UPDATE `" . _DB_PREFIX_ . "bdroppy_remoteproduct` SET sync_status = 'updated', ps_product_id='".$product->id."', imported=1 WHERE id=".$item['id'].";";
                $r = Db::getInstance()->ExecuteS($sql);

                echo "Product ID : $product_id From $catalog_id Imported \n";

                $photo_base = 'https://branddistributionproddia.blob.core.windows.net/storage-foto-dev/prod/';
                if(strpos($this->base_url,'dev') !== false){
                    $photo_base = "https://branddistributionproddia.blob.core.windows.net/storage-foto-dev/prod/";
                }else{
                    $photo_base = "https://branddistributionproddia.blob.core.windows.net/storage-foto/prod/";
                }
                $imgCoverFlag = true;
                $images = array();
                foreach($this->product->pictures as $pic) {
                    try {
                        $image = new Image();
                        $image->id_product = (int)$product->id;
                        $image->position = Image::getHighestPosition($product->id) + 1;
                        $image->cover = $imgCoverFlag;
                        $image->add();
                        file_put_contents($this->debugImportFile, "Attaching Image : " . $photo_base . $pic->url . "\n", FILE_APPEND);
                        $cpyImgFlg = $this->copyImg($product->id, $image->id, $photo_base . $pic->url, 'products', true);
                        if (!$cpyImgFlg) {
                            array_push($images, $image->id);
                            $image->delete();
                        }
                        $imgCoverFlag = false;
                    } catch (PrestaShopException $e) {
                        file_put_contents($this->debugImportFile, "Error in Attaching Image : " . var_export($e, true) . "\n", FILE_APPEND);
                    }
                }
                $sql = "UPDATE `" . _DB_PREFIX_ . "bdroppy_remoteproduct` SET sync_status = 'updated', ps_product_id='".$product->id."', imported=2 WHERE id=".$item['id'].";";
                $r = Db::getInstance()->ExecuteS($sql);
                file_put_contents($this->debugImportFile, date('Y-m-d H:i:s') . " - Images Attached to Product " . var_export($images, true) . "\n", FILE_APPEND);
                echo "Images Attached \n";

                $first = true;
                foreach($this->product->models as $model) {
                    $combinationAttributes = array();
                    if($model->color) {
                        $sql = "SELECT * FROM "._DB_PREFIX_."attribute a LEFT JOIN "._DB_PREFIX_."attribute_lang al ON (a.id_attribute = al.id_attribute) WHERE a.id_attribute_group = ".$this->api_color." AND al.name = '" . $model->color . "';";
                        $r = Db::getInstance()->executeS($sql);
                        if ($r) {
                            $attribute = (object)$r[0];
                        } else {
                            $attribute = new Attribute();
                            foreach ($languages as $lang) {
                                $langCode = str_replace('-', '_', $lang['locale']);
                                $attribute->name[$lang['id_lang']] = $this->getColor($langCode);
                            }
                            $attribute->id_attribute_group = $this->api_color;
                            $attribute->save();
                            $sql = "SELECT * FROM "._DB_PREFIX_."attribute a LEFT JOIN "._DB_PREFIX_."attribute_lang al ON (a.id_attribute = al.id_attribute) WHERE a.id_attribute_group = ".$this->api_color." AND al.name = '" . $model->color . "';";
                            $r = Db::getInstance()->executeS($sql);
                            if ($r) {
                                $attribute = (object)$r[0];
                            }
                        }
                        $combinationAttributes[] = $attribute->id_attribute;
                    }
                    if($model->size) {
                        $sql = "SELECT * FROM "._DB_PREFIX_."attribute a LEFT JOIN "._DB_PREFIX_."attribute_lang al ON (a.id_attribute = al.id_attribute) WHERE a.id_attribute_group = " . $this->api_size . " AND al.name = '" . $model->size . "';";
                        $r = Db::getInstance()->executeS($sql);

                        if ($r) {
                            $attribute = (object)$r[0];
                        } else {
                            $attribute = new Attribute();
                            foreach ($languages as $lang) {
                                $attribute->name[$lang['id_lang']] = $model->size;
                            }
                            $attribute->id_attribute_group = $this->api_size;
                            $attribute->save();
                            $sql = "SELECT * FROM "._DB_PREFIX_."attribute a LEFT JOIN "._DB_PREFIX_."attribute_lang al ON (a.id_attribute = al.id_attribute) WHERE a.id_attribute_group = " . $this->api_size . " AND al.name = '" . $model->size . "';";
                            $r = Db::getInstance()->executeS($sql);

                            if ($r) {
                                $attribute = (object)$r[0];
                            }
                        }
                        $combinationAttributes[] = $attribute->id_attribute;
                    }

                    $quantity = $model->availability;
                    $impact_on_price_per_unit = 0;
                    $impact_on_price = 0;
                    $impact_on_weight = $this->product->weight;
                    $isbn_code = $model->id;
                    $reference = $model->code;
                    $id_images = $images;
                    $id_supplier = null;
                    $ean13 = $model->barcode;
                    $default = $first;
                    $location = null;
                    $upc = null;
                    $minimal_quantity = 1;
                    $idProductAttribute = $product->addProductAttribute((float)$impact_on_price, (float)$impact_on_weight, $impact_on_price_per_unit, null, (int)$quantity, $id_images, $reference, $id_supplier, $ean13, $default, $location, $upc, null, $isbn_code, $minimal_quantity);
                    $r = $product->addAttributeCombinaison($idProductAttribute, $combinationAttributes);
                    file_put_contents($this->debugImportFile, date('Y-m-d H:i:s') . " - Product ID : " . $product->id . " - model : " . var_export($model, true) . " - idProductAttribute : $idProductAttribute - Combinations Save ($r)\n", FILE_APPEND);
                    $first = false;
                }
                echo "Combinations Saved \n*********************************\n";
                $sql = "UPDATE `" . _DB_PREFIX_ . "bdroppy_remoteproduct` SET sync_status = 'updated', ps_product_id='".$product->id."', imported=9 WHERE id=".$item['id'].";";
                $r = Db::getInstance()->ExecuteS($sql);
            }
        } catch (PrestaShopException $e) {
            file_put_contents($this->debugImportFile, var_export($e, true) . "\n", FILE_APPEND);
            return false;
        }
        file_put_contents($this->debugImportFile, "************************************************************\n", FILE_APPEND);
    }
}
