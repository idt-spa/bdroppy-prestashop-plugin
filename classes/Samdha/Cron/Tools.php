<?php
/**
 * Tools used by the module UpShippingNumber
 *
 * @category  Prestashop
 * @category  Module
 * @author    Samdha <contact@samdha.net>
 * @copyright Samdha
 * @license   commercial license see license.txt
 */

class Samdha_Cron_Tools
{
    public $module;

    public function __construct($module)
    {
        $this->module = $module;
    }

    /**
     * return running php path
     * @see http://stackoverflow.com/a/3889630
     *
     * @return string
     */
    public function getPHPExecutableFromPath()
    {
        $paths = explode(PATH_SEPARATOR, getenv('PATH'));
        try {
            foreach ($paths as $path) {
            // we need this for XAMPP (Windows)
                if (strstr($path, 'php.exe')
                    && isset($_SERVER['WINDIR'])
                    && file_exists($path) && is_file($path)
                ) {
                    return $path;
                } else {
                    $php_executable = $path.DIRECTORY_SEPARATOR.'php'.(isset($_SERVER['WINDIR']) ? '.exe' : '');
                    if (file_exists($php_executable)
                        && is_file($php_executable)) {
                        return $php_executable;
                    }

                    $php_executable = $path.DIRECTORY_SEPARATOR.'php5'.(isset($_SERVER['WINDIR']) ? '.exe' : '');
                    if (file_exists($php_executable)
                        && is_file($php_executable)) {
                        return $php_executable;
                    }
                }
            }
        } catch (Exception $e) {
            // not found
            return '/usr/bin/env php';
        }

        return '/usr/bin/env php'; // not found
    }
}
