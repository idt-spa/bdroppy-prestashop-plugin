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

class BdroppyLogger extends ObjectModel
{
    public $id;
    public $title;
    public $message;
    public $type;
    public $created_at;

    public static $definition = array(
        'table'     => 'bdroppy_log',
        'primary'   => 'id',
        'multilang' => false,
        'fields'    => array(
            'id'               => array(
                'type'     => self::TYPE_INT,
                'validate' => 'isInt',
            ),
            'title'      => array('type' => self::TYPE_STRING),
            'message'      => array('type' => self::TYPE_STRING),
            'type'         => array(
                'type'     => self::TYPE_INT,
                'validate' => 'isInt',
            ),
        ),
    );
    public static function addLog($method, $msg, $type)
    {
        if ((bool)Configuration::get('BDROPPY_LOG')) {
            $log = new BdroppyLogger();
            $log->title = $method;
            $log->message = $msg;
            $log->type = $type;
            $log->created_at = date('Y-m-d H:i:s');
            $log->save();
        }
    }
}
