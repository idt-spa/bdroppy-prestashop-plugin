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
$sql = array();

$sql[] = "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."dropshipping_products` (
    `id` INT(10) UNSIGNED AUTO_INCREMENT,
    `rewix_product_id` INT(10) UNSIGNED NOT NULL,
    `rewix_catalog_id` VARCHAR(128) NOT NULL,
    `ps_product_id` INT(10) UNSIGNED NOT NULL,
    `sync_status` VARCHAR(128) NOT NULL,
    `sync_message` TEXT,
    `last_sync_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `imported` BOOLEAN NOT NULL DEFAULT FALSE,
    PRIMARY KEY (`id`),
    UNIQUE (`rewix_product_id`,`rewix_catalog_id`),
    INDEX (`ps_product_id`)
) CHARSET=utf8;";
$sql[] = "CREATE TABLE IF NOT EXISTS `"._DB_PREFIX_."dropshipping_catalogs` (
    `id` INT(10) UNSIGNED AUTO_INCREMENT,
    `catalog` VARCHAR(128) DEFAULT NULL,
    `locales` VARCHAR(128) DEFAULT NULL,
    `status` VARCHAR(128) DEFAULT NULL,
    `page` INT(10) UNSIGNED DEFAULT 0,
    `imported` INT(10) UNSIGNED DEFAULT 0,
    `locked` INT(10) UNSIGNED DEFAULT 0,
    `products_count` INT(10) UNSIGNED DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP,
    PRIMARY KEY (`id`)
) CHARSET=utf8;";

$sql[] = 'CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'dropshipping_remoteorder` (
            `id` INT(10) UNSIGNED AUTO_INCREMENT,
            `rewix_order_id` INT(10) UNSIGNED DEFAULT NULL,
            `rewix_order_key` VARCHAR(128) DEFAULT NULL,
            `ps_order_id` INT(10) UNSIGNED NOT NULL,
            `status` INT(10) UNSIGNED NOT NULL,           
            PRIMARY KEY (`id`),
            UNIQUE (`ps_order_id`)
        ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        echo "false";
    }
}
