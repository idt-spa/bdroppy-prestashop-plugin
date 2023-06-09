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

class BdroppyRemoteProduct extends ObjectModel
{
    public $id;
    public $rewix_product_id;
    public $rewix_catalog_id;
    public $ps_product_id;
    public $sync_status;
    public $simple;
    public $last_sync_date;
    public $priority;
    public $imported;
    public $reason;
    public $reference;
    public $data;

    const SYNC_STATUS_QUEUED = 'queued';
    const SYNC_STATUS_FAILED = 'failed';
    const SYNC_STATUS_UPDATED = 'updated';
    const SYNC_STATUS_DELETE = 'delete';
    const SYNC_STATUS_IMPORTING = 'importing';

    public static $definition = array(
        'table'     => 'bdroppy_remoteproduct',
        'primary'   => 'id',
        'multilang' => false,
        'fields'    => array(
            'id'               => array(
                'type'     => self::TYPE_INT,
                'validate' => 'isInt',
            ),
            'rewix_product_id' => array(
                'type'     => self::TYPE_INT,
                'validate' => 'isInt',
            ),
            'ps_product_id'    => array(
                'type'     => self::TYPE_INT,
                'validate' => 'isInt',
            ),
            'simple'    => array(
                'type'     => self::TYPE_INT,
                'validate' => 'isInt',
            ),
            'sync_status'      => array('type' => self::TYPE_STRING),
            'rewix_catalog_id'      => array('type' => self::TYPE_STRING),
            'reference'      => array('type' => self::TYPE_STRING),
            'data'      => array('type' => self::TYPE_STRING),
            'last_sync_date'   => array(
                'type'     => self::TYPE_DATE,
                'validate' => 'isDateFormat',
            ),
            'priority'         => array(
                'type'     => self::TYPE_INT,
                'validate' => 'isInt',
            ),
            'reason'           => array('type' => self::TYPE_STRING),
            'imported'         => array(
                'type'     => self::TYPE_INT,
                'validate' => 'isInt',
            ),
        ),
    );

    /**
     * @param int $id
     * @return BdroppyRemoteProduct
     */
    public static function fromRewixId($id)
    {
        $product = new self(self::getIdByRewixId($id));
        $product->rewix_product_id = $id;

        return $product;
    }

    /**
     * @param int $id
     * @return int
     */
    public static function getRewixIdByPsId($id)
    {
        $query = new DbQuery();
        $query->select('rewix_product_id');
        $query->from('bdroppy_remoteproduct');
        $query->where('`ps_product_id` = ' . (int)$id);

        return (int)Db::getInstance()->getValue($query);
    }

    /**
     * @param int $id
     * @return int
     */
    public static function getPsIdByRewixId($id)
    {
        $query = new DbQuery();
        $query->select('ps_product_id');
        $query->from('bdroppy_remoteproduct');
        $query->where('`rewix_product_id` = ' . (int)$id);

        return (int)Db::getInstance()->getValue($query);
    }

    /**
     * @param string $status
     * @param int $limit
     * @param int $maxPriority
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public static function getIdsByStatus($status, $limit = 30, $maxPriority = 0)
    {
        $query = new DbQuery();
        $query->select('rewix_product_id');
        $query->from('bdroppy_remoteproduct');
        $query->where('`sync_status` = \'' . pSQL($status) . '\'');
        if ($maxPriority > 0) {
            $query->where('`priority` < ' . $maxPriority);
        }
        $query->orderBy('priority ASC');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $results = Db::getInstance()->executeS($query);
        $ids = array();

        if ($results) {
            foreach ($results as $product) {
                $ids[] = $product['rewix_product_id'];
            }
        }

        return $ids;
    }

    /**
     * @param string $status
     * @param int $limit
     * @return array
     * @throws PrestaShopDatabaseException
     */
    public static function getByStatus($status, $limit = 30)
    {
        $query = new DbQuery();
        $query->select('*');
        $query->from('bdroppy_remoteproduct');
        $query->where('`sync_status` = \'' . pSQL($status) . '\'');
        if ($limit > 0) {
            $query->limit($limit);
        }

        $results = Db::getInstance()->executeS($query);

        return $results;
    }

    /**
     * @param int $id
     * @param bool $imported
     * @return int
     */
    public static function getIdByRewixId($id, $imported = false)
    {
        $query = new DbQuery();
        $query->select('id');
        $query->from('bdroppy_remoteproduct');
        $query->where('`rewix_product_id` = ' . (int)$id);

        if ($imported) {
            $query->where('imported = ' . (int)$imported);
        }
        $result = Db::getInstance()->getValue($query);

        if ($result > 0) {
            return $result;
        }

        return 0;
    }

    /**
     * @param int $id
     * @return bool
     */
    public static function deleteByPsId($id)
    {
        // all the models (combinations) should be automatically be deleted by the constraint
        $query = new DbQuery();
        $query->type('DELETE');
        $query->from('bdroppy_remoteproduct');
        $query->where('ps_product_id = ' . (int)$id);
        $query->limit(1);

        return Db::getInstance()->execute($query);
    }

    /**
     * @param string $status
     * @return int
     */
    public static function getCountByStatus($status)
    {
        $query = new DbQuery();
        $query->select('COUNT(*)');
        $query->from('bdroppy_remoteproduct');
        if ($status != '') {
            $query->where('`sync_status` = \'' . pSQL($status) . '\'');
        }
        $result = Db::getInstance()->getValue($query);
        return $result;
    }

    /**
     * @param int $id
     * @return bool
     */
    public static function deleteByRewixId($id)
    {
        // all the models (combinations) should be automatically be deleted by the constraint
        $query = new DbQuery();
        $query->type('DELETE');
        $query->from('bdroppy_remoteproduct');
        $query->where('rewix_product_id = ' . (int)$id);
        $query->limit(1);
        return Db::getInstance()->execute($query);
    }
}
