<?php
/**
 * Cron module
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
 * @category Prestashop
 * @category Module
 * @author Samdha <contact@samdha.net>
 * @copyright Samdha
 * @license commercial license see license.txt
 * @author logo Alessandro Rei http://www.kde-look.org/content/show.php/Dark-Glass+reviewed?content=67902
 * @license logo http://www.gnu.org/copyleft/gpl.html GPLv3
 * @version 1.4.0.0
**/

class Samdha_Cron_Cron
{
    public $module;
    public $parser;

    public function __construct($module)
    {
        $this->module = $module;
        $this->parser = new Samdha_Cron_Parser($module);
    }

    /**
     * add a cron job
     *
     * usage Module::getInstanceByName('cron')->addCron($this->id, 'myMethod', '5 * * * *');
     *
     * $mhdmd details :
     * .---------------- minute (0 - 59)
     * |  .------------- hour (0 - 23)
     * |  |  .---------- day of month (1 - 31)
     * |  |  |  .------- month (1 - 12)
     * |  |  |  |  .---- day of week (0 - 6) (Sunday=0 )
     * |  |  |  |  |
     * *  *  *  *  *
     *
     * @param int $id_module Module ID
     * @param string $method method of the module to call
     * @param string $mhdmd when call this cron
     * @return boolean
    **/
    public function add($id_module, $method, $mhdmd = '0 * * * *')
    {
        if (!$this->module->active) {
            return false;
        }

        if (!$module = Module::getInstanceById($id_module)) {
            $this->module->errors[] = $this->module->l('This module doesn\'t exists.', 'cron');
            return false;
        }
        $classMethods = array_map('strtolower', get_class_methods($module));
        if (!$classMethods || !in_array(strtolower($method), $classMethods)) {
            $this->module->errors[] = $this->module->l('This method doesn\'t exists.', 'cron');
            return false;
        }
        if (!$this->parser->calcLastRan($mhdmd)) {
            $this->module->errors[] = $this->module->l('This shedule isn\'t valid.', 'cron');
            return false;
        }

        $values = array(
            'id_module' => intval($id_module),
            'method' => pSQL($method),
            'mhdmd' => pSQL($mhdmd),
            'last_execution' => 0,
        );
        if (version_compare(_PS_VERSION_, '1.5.0.0', '<')) {
            return Db::getInstance()->autoExecute(_DB_PREFIX_.'cron', $values, 'INSERT');
        } else {
            return Db::getInstance()->insert('cron', $values);
        }
    }

    /**
     * delete a cron job
     *
     * @param int $id_module Module ID
     * @param string $method method of the module to call
     * @return boolean
    **/
    public function delete($id_module, $method)
    {
        if (!$this->module->active) {
            return false;
        }
        return Db::getInstance()->delete(
            _DB_PREFIX_.'cron',
            '`id_module` = '.intval($id_module).' AND `method` = \''.pSQL($method).'\''
        );
    }

    /**
     * delete a cron job
     *
     * @param int $id_cron cron job ID
     * @return boolean
    **/
    public function deleteByID($id_cron)
    {
        if (!$this->module->active) {
            return false;
        }
        return Db::getInstance()->delete(
            _DB_PREFIX_.'cron',
            '`id_cron` = '.intval($id_cron)
        );
    }

    /**
     * test if a cron job exists
     *
     * @param int $id_module Module ID
     * @param string $method method of the module to call
     * @return boolean
    **/
    public function exists($id_module, $method)
    {
        if (!$this->module->active) {
            return false;
        }
        $sql = '
            SELECT id_cron
            FROM `'._DB_PREFIX_.'cron`
            WHERE `id_module` = '.intval($id_module).'
            AND `method` = \''.pSQL($method).'\'';
        $cron = Db::getInstance()->getRow($sql);
        return is_array($cron);
    }

    /**
     * add a cron job
     *
     * usage Module::getInstanceByName('cron')->addURLCron($url', '5 * * * *');
     *
     * $mhdmd details :
     * .---------------- minute (0 - 59)
     * |  .------------- hour (0 - 23)
     * |  |  .---------- day of month (1 - 31)
     * |  |  |  .------- month (1 - 12)
     * |  |  |  |  .---- day of week (0 - 6) (Sunday=0 )
     * |  |  |  |  |
     * *  *  *  *  *
     *
     * @param string $url url to visit
     * @param string $mhdmd when call this cron
     * @return boolean
    **/
    public function addURL($url, $mhdmd = '0 * * * *')
    {
        if (!$this->module->active) {
            return false;
        }
        if (!$this->parser->calcLastRan($mhdmd)) {
            $this->module->errors[] = $this->module->l('This shedule isn\'t valid.', 'cron');
            return false;
        }

        $values = array(
                        'url' => pSQL($url),
                        'mhdmd' => pSQL($mhdmd),
                        'last_execution' => 0
                       );
        if (version_compare(_PS_VERSION_, '1.5.0.0', '<')) {
            return Db::getInstance()->autoExecute(
                _DB_PREFIX_.'cron_url',
                $values,
                'INSERT'
            );
        } else {
            return Db::getInstance()->insert('cron_url', $values);
        }
    }

    /**
     * delete a cron job
     *
     * @param int $id_module Module ID
     * @param string $method method of the module to call
     * @return boolean
    **/
    public function deleteURL($url)
    {
        if (!$this->module->active) {
            return false;
        }
        return Db::getInstance()->delete(
            _DB_PREFIX_.'cron_url',
            '`url` = \''.pSQL($url).'\''
        );
    }

    /**
     * delete a cron job
     *
     * @param int $id_cron cron job ID
     * @return boolean
    **/
    public function deleteURLByID($id_cron_url)
    {
        if (!$this->module->active) {
            return false;
        }
        return Db::getInstance()->delete(
            _DB_PREFIX_.'cron_url',
            '`id_cron_url` = '.intval($id_cron_url)
        );
    }

    /**
     * test if a cron job exists
     *
     * @param int $id_module Module ID
     * @param string $method method of the module to call
     * @return boolean
    **/
    public function URLExists($url)
    {
        if (!$this->module->active) {
            return false;
        }
        $sql = '
            SELECT id_cron_url
            FROM `'._DB_PREFIX_.'cron_url`
            WHERE `url` = \''.pSQL($url).'\'';
        $cron = Db::getInstance()->getRow($sql);
        return is_array($cron);
    }

    public function log($message)
    {
        return '['.date('r')."]\t".$message.PHP_EOL;
    }

    /**
     * execute cron jobs
     * invalide job will be deleted
     *
     * @return void
    **/
    public function runJobs()
    {
        $result = '';
        $result .= $this->log('Start jobs');
        // get the jobs
        $sql = 'SELECT * FROM `'._DB_PREFIX_.'cron`';
        $crons = Db::getInstance()->executeS($sql);
        foreach ($crons as $cron) {
            // When the job should have been executed for the last time ?
            // if it's in the past execute it
            $this->parser->calcLastRan($cron['mhdmd']);

            if ($this->parser->getLastRanUnix() > $cron['last_execution']) {
                // if module doesn't exists delete job
                if (!$module = Module::getInstanceById($cron['id_module'])) {
                    $result .= $this->log('Module '.$cron['id_module'].' not found, delete job.');
                    $this->delete($cron['id_module'], $cron['method']);
                } else {
                    $classMethods = array_map('strtolower', get_class_methods($module));
                    // if method doesn't exists delete job
                    if (!$classMethods || !in_array(strtolower($cron['method']), $classMethods)) {
                        $result .= $this->log('Method "'.$module->name.':'.$cron['method'].'" not found, delete job.');
                        $this->delete($cron['id_module'], $cron['method']);
                    } else {
                        $values = array(
                            'last_execution' => time()
                        );
                        if (version_compare(_PS_VERSION_, '1.5.0.0', '<')) {
                            Db::getInstance()->autoExecute(
                                _DB_PREFIX_.'cron',
                                $values,
                                'UPDATE',
                                'id_cron = '.$cron['id_cron']
                            );
                        } else {
                            Db::getInstance()->update(
                                'cron',
                                $values,
                                'id_cron = '.$cron['id_cron']
                            );
                        }

                        $result .= $this->log('Start execute "'.$module->name.'::'.$cron['method'].'()"');
                        // run job TODO: make it asynchronous
                        $result .= call_user_func(array($module, $cron['method']));
                        $result .= $this->log('End execute "'.$module->name.'::'.$cron['method'].'()"');
                    }
                }
            }
        }

        // get the url to visit
        $sql = 'SELECT * FROM `'._DB_PREFIX_.'cron_url`';
        $crons = Db::getInstance()->executeS($sql);
        foreach ($crons as $cron) {
            // When the job should have been executed for the last time ?
            // if it's in the past execute it
            $this->parser->calcLastRan($cron['mhdmd']);
            if ($this->parser->getLastRanUnix() > $cron['last_execution']) {
                $values = array(
                    'last_execution' => time()
                );
                if (version_compare(_PS_VERSION_, '1.5.0.0', '<')) {
                    Db::getInstance()->autoExecute(
                        _DB_PREFIX_.'cron_url',
                        $values,
                        'UPDATE',
                        'id_cron_url = '.$cron['id_cron_url']
                    );
                } else {
                    Db::getInstance()->update(
                        'cron_url',
                        $values,
                        'id_cron_url = '.$cron['id_cron_url']
                    );
                }

                $result .= $this->log('Start visit "'.$cron['url'].'"');
                // run job TODO: make it asynchronous
                $this->module->samdha_tools->fileGetContents($cron['url']);
                $result .= $this->log('End visit "'.$cron['url'].'"');
            }
        }
        $result .= $this->log('End jobs');
        return $result;
    }
}
