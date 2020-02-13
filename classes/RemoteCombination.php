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

/**
 * @class DropshippingRemoteCombination
 */
class DropshippingRemoteCombination extends ObjectModel
{
    public static $definition = array(
        'table' => 'dropshipping_remotecombination',
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
     * @return DropshippingRemoteCombination
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
        $query->from('dropshipping_remotecombination');
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
        $query->from('dropshipping_remotecombination');
        $query->where('`rewix_product_id` = '.pSQL($rewix_product_id));

        return Db::getInstance()->executeS($query);
    }
  
    /**
     * @param int $rewix_product_id
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public static function getRewixModelIdByProductAndModelId($ps_product_id, $ps_attribute_id)
    {
        $sql = "SELECT rewix_product_id FROM `" . _DB_PREFIX_ . "dropshipping_products` WHERE ps_product_id=$ps_product_id;";

        return Db::getInstance()->getValue($sql);
    }

    /**
     * @param int $rewix_product_id
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public static function getPsModelIdByRewixProductAndModelId($rewix_product_id, $rewix_model_id)
    {
        $sql =  "select r.ps_model_id " .
                "from " . _DB_PREFIX_ . "dropshipping_remotecombination r " .
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
        $query->from('dropshipping_remotecombination');
        $query->where('rewix_product_id = ' . (int)pSQL($id));

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
        $query->from('dropshipping_remotecombination');
        $query->where('`rewix_product_id` = '.pSQL($rewix_product_id));

        return Db::getInstance()->getValue($query);
    }
}
