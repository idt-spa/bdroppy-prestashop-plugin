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

class BdroppyRemoteCombination extends ObjectModel
{
    public static $definition = array(
        'table' => 'bdroppy_remotecombination',
        'primary' => 'id',
        'multilang' => false,
        'fields' => array(
            'id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
            ),
            'rewix_product_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
            ),
            'rewix_model_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
            ),
            'ps_model_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
            ),
        ),
    );
    public $id; // parent id
    public $rewix_product_id;
    public $rewix_model_id; // combination id
    public $ps_model_id;

    /**
     * @param int $id
     * @return BdroppyRemoteCombination
     */
    public static function fromRewixId($id)
    {
        $product = new self(self::getIdByRewixId($id));
        $product->rewix_model_id = $id;

        return $product;
    }

    /**
     * @param int $id
     * @return int
     */
    public static function getIdByRewixId($id)
    {
        $query = new DbQuery();
        $query->select('id');
        $query->from('bdroppy_remotecombination');
        $query->where('rewix_model_id = '.(int) $id);

        $result = Db::getInstance()->getValue($query);

        if ($result > 0) {
            return $result;
        }

        return 0;
    }

    /**
     * @param int $rewix_product_id
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public static function getByRewixProductId($rewix_product_id)
    {
        $query = new DbQuery();
        $query->select('*');
        $query->from('bdroppy_remotecombination');
        $query->where('`rewix_product_id` = '.(int)$rewix_product_id);

        return Db::getInstance()->executeS($query);
    }

    /**
     * @param int $rewix_product_id
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public static function getRewixModelIdByProductAndModelId($ps_product_id)
    {
        return $ps_product_id;
        //$sql = "SELECT rewix_product_id FROM `" . _DB_PREFIX_ . "bdroppy_products` WHERE ps_product_id=".
        //    (int)$ps_product_id.";";
        //return Db::getInstance()->getValue($sql);
    }

    /**
     * @param int $rewix_product_id
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public static function getPsModelIdByRewixProductAndModelId($rewix_product_id, $rewix_model_id)
    {
        $sql =  "select r.ps_model_id " .
            "from " . _DB_PREFIX_ . "bdroppy_remotecombination r " .
            "where r.rewix_product_id = " . (int) $rewix_product_id .
            " and r.rewix_model_id = " . (int) $rewix_model_id;

        return Db::getInstance()->getValue($sql);
    }

    /**
     * @param int $id
     * @return bool
     */
    public static function deleteByRewixId($id)
    {
        $query = new DbQuery();
        $query->type('DELETE');
        $query->from('bdroppy_remotecombination');
        $query->where('rewix_product_id = ' . (int)$id);

        return Db::getInstance()->execute($query);
    }

    /*
     * @param int $rewix_product_id
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public static function countByRewixProductId($rewix_product_id)
    {
        $query = new DbQuery();
        $query->select('count(*)');
        $query->from('bdroppy_remotecombination');
        $query->where('`rewix_product_id` = '. (int) $rewix_product_id);

        return Db::getInstance()->getValue($query);
    }
}
