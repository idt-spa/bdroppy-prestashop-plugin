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
        $query = "select o.id_order from `"._DB_PREFIX_."orders` o where o.current_state in 
            (select value from `"._DB_PREFIX_."configuration` where name in 
            ('PS_OS_PREPARATION', 'PS_OS_PAYMENT', 'PS_OS_WS_PAYMENT') ) 
            and o.id_order not in (select r.ps_order_id from `"._DB_PREFIX_."bdroppy_remoteorder` r)";
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
        $query->where('`status` != ' . pSQL($staus));

        return Db::getInstance()->ExecuteS($query);
    }
}
