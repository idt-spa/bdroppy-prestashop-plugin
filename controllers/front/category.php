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
include_once dirname(__FILE__) . '/../../classes/RewixApi.php';

class BdroppyCategoryModuleFrontController extends ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();
        $this->context = Context::getContext();
        $this->ssl = true;
        $this->ajax = true;
    }
    

    public function initContent()
    {
        $rewixApi = new BdroppyRewixApi();
        $data = Tools::getValue('data');
        switch (Tools::getValue('type'))
        {
            case 'getBdSubCategory' :
                $subcategories = $rewixApi->getSubCategories($data['category']) ;
                header('Content-Type: application/json');
                echo json_encode($subcategories);
                break;

            case 'getBdCategory' :
                $subcategories = $rewixApi->getCategories() ;
                header('Content-Type: application/json');
                echo $subcategories;
                break;

            case 'getSubCategory' :
                $subcategories = get_terms( 'product_cat', ['hide_empty' => false,'parent'     => $data['category']]);
                return new \WP_REST_Response($subcategories, 200 );

            case 'addCategory' :
                $key = str_replace(' ','_',implode('-',$data['bdroppyIds']));
                $categoriesMapping = unserialize(Configuration::get('bdroppy-category-mapping'));
                $categoriesMapping[$key] = $data;
                Configuration::updateValue('bdroppy-category-mapping', serialize($categoriesMapping),false);
                break;

            case 'getCategoryList' :
                $categories = unserialize(Configuration::get('bdroppy-category-mapping'));
                header('Content-Type: application/json');
                echo json_encode($categories);
                break;

            case 'deleteItemByKey' :
                $categoriesMapping = get_option('bdroppy-category-mapping');
                unset($categoriesMapping[$data['key']]) ;
                update_option('bdroppy-category-mapping',$categoriesMapping);
                return new \WP_REST_Response($categoriesMapping, 200 );
        }

        return 1;
    }

    public function assignTpl()
    {
        echo 12;
        return 1;
    }
}
