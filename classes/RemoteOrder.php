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
 * @class BdroppyRemoteOrder
 */
class BdroppyRemoteOrder extends ObjectModel
{
    public $id;
    public $rewix_order_id;
    public $rewix_order_key;
    public $ps_order_id;
    public $status;
    
    const STATUS_FAILED = 2000;
    const STATUS_NOAVAILABILITY = 2001;
    const STATUS_UNKNOWN = 2002;
    const STATUS_API_ERROR = 2003;
    const STATUS_BOOKED = 5;
    const STATUS_CONFIRMED = 2;
    const STATUS_WORKING_ON = 3001;
    const STATUS_READY = 3002;
    const STATUS_DISPATCHED = 3;

    public static $definition = array(
        'table'     => 'bdroppy_remoteorder',
        'primary'   => 'id',
        'multilang' => false,
        'fields'    => array(
            'id'               => array(
                'type'     => self::TYPE_INT,
                'validate' => 'isInt',
            ),
            'rewix_order_key' => array(
                'type' => self::TYPE_STRING
            ),
            'rewix_order_id' => array(
                'type'     => self::TYPE_INT,
                'validate' => 'isInt',
            ),
            'ps_order_id'    => array(
                'type'     => self::TYPE_INT,
                'validate' => 'isInt',
            ),
            'status'     => array(
                'type'     => self::TYPE_INT,
                'validate' => 'isInt',
            ),
        ),
    );

    /**
     * @param int $rewixOrderId
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public static function getIdByRewixOrderId($rewixOrderId)
    {
        $query = new DbQuery();
        $query->select('id');
        $query->from('bdroppy_remoteorder');
        $query->where('`rewix_order_id` = ' . pSQL($rewixOrderId));
        
        return Db::getInstance()->getValue($query);
    }

    /**
     * @param int $psOrderId
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public static function getRewixIdByPsOrderId($psOrderId)
    {
        $query = new DbQuery();
        $query->select('rewix_order_id');
        $query->from('bdroppy_remoteorder');
        $query->where('`id` = ' . pSQL($psOrderId));
        
        return Db::getInstance()->getValue($query);
    }

     /**
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public static function getMissingOrdersId()
    {
        $query = 'select o.id_order ' .
        'from `'._DB_PREFIX_.'orders` o ' .
        'where o.current_state in (select value from `'._DB_PREFIX_.'configuration` where name in (\'PS_OS_PREPARATION\') ) ' .
        'and o.id_order not in (select r.ps_order_id from `'._DB_PREFIX_.'bdroppy_remoteorder` r)';
        return Db::getInstance()->ExecuteS($query);
    }

    public static function getOrders()
    {
        $orders = new Collection('BdroppyRemoteOrder');
 
        return $orders;
    }

    public static function getOrdersByNotStatus($staus)
    {
        $query = new DbQuery();
        $query->select('*');
        $query->from('bdroppy_remoteorder');
        $query->where('`status` != ' . ($staus));

        return Db::getInstance()->ExecuteS($query);
    }
}
