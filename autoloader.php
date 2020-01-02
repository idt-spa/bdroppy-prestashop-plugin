<?php
/**
 * Translate module
 *
 * @category  Prestashop
 * @category  Module
 * @author    Samdha <contact@samdha.net>
 * @copyright Samdha
 * @license   commercial license see license.txt
 */

/**
 * Autoloader for this module classes
 */
function sdCronAutoload($class_name)
{
    $module_name = 'cron';
    $class_name = ltrim($class_name, '\\');
    $file_name  = '';
    $namespace = '';
    if ($last_ns_post = strrpos($class_name, '\\')) {
        $namespace = Tools::substr($class_name, 0, $last_ns_post);
        $class_name = Tools::substr($class_name, $last_ns_post + 1);
        $file_name  = str_replace('\\', DIRECTORY_SEPARATOR, $namespace).DIRECTORY_SEPARATOR;
    }
    $file_name .= str_replace('_', DIRECTORY_SEPARATOR, $class_name).'.php';
    if (!defined('_PS_MODULE_DIR_')) {
        $file_name = _PS_ROOT_DIR_.'/modules/'.$module_name
            .DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.$file_name;
    } else {
        $file_name = _PS_MODULE_DIR_.$module_name
        .DIRECTORY_SEPARATOR.'classes'.DIRECTORY_SEPARATOR.$file_name;
    }
    if (file_exists($file_name)) {
        return require_once($file_name);
    } elseif (version_compare(_PS_VERSION_, '1.4.0.0', '>=') && function_exists('__autoload')) {
        return __autoload($class_name);
    } elseif (version_compare(_PS_VERSION_, '1.4.0.0', '<')
        && is_readable(_PS_ROOT_DIR_.'/classes/'.$class_name.'.php')
    ) {
        require_once _PS_ROOT_DIR_.'/classes/'.$class_name.'.php';
    }
}
