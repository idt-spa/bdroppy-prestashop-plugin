<?php
/**
 * Licence management
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this module to newer
 * versions in the future. If you wish to customize this module for your
 * needs please refer to http://doc.prestashop.com/display/PS15/Overriding+default+behaviors
 * #Overridingdefaultbehaviors-Overridingamodule%27sbehavior for more information.
 *
 * @author    Samdha <contact@samdha.net>
 * @copyright Samdha
 * @license   commercial license see license.txt
 * @category  Prestashop
 * @category  Module
 */

class Samdha_Commons_Licence
{
    public $module;
    public $licence_number = false;

    public function __construct($module)
    {
        $this->module = $module;
    }

    /**
     * check if module is up to date
     * @return string/boolean false on fail, 'NEED_UPDATE' or 'OK'
     */
    public function checkModuleVersion()
    {
        $cookie = $this->module->context->cookie;

        if ($this->licence_number && $this->module->samdha_tools->canAccessInternet()) {
            $domain = $this->module->samdha_tools->getHttpHost();
            $iso_lang = Language::getIsoById($cookie->id_lang);
            try {
                return $this->module->samdha_tools->jsonRPCCall(
                    'support~module:check',
                    array(
                        'module_name'=>$this->module->name,
                        'lang' => $iso_lang,
                        'domain' => $domain,
                        'version' => $this->module->version,
                        'licence_number' => $this->licence_number
                    )
                );
            } catch (Exception $e) {
                if (class_exists('PrestaShopModuleException')) {
                    throw new PrestaShopModuleException('Error checking module version: '.$e->getMessage(), 1);
                } else {
                    throw new Exception('Error checking module version: '.$e->getMessage(), 1);
                }
            }
        }
        return false;
    }

    /**
     * check if licence number is valid
     * @return boolean
     */
    public function checkLicence()
    {
        $cookie = $this->module->context->cookie;

        static $done = false;
        if (!$done && $this->module->samdha_tools->canAccessInternet()) {
            $done = true;
            $this->licence_number = Configuration::get($this->module->short_name.'_licence');
            if ($this->licence_number) {
                $domain = $this->module->samdha_tools->getHttpHost();
                $iso_lang = Language::getIsoById($cookie->id_lang);
                try {
                    $valid_licence = $this->module->samdha_tools->jsonRPCCall(
                        'support~licence:check',
                        array(
                            'module_name'    => $this->module->name,
                            'lang'           => $iso_lang,
                            'domain'         => $domain,
                            'licence_number' => $this->licence_number
                        )
                    );
                    if ($valid_licence != 'OK') {
                        $this->licence_number = false;
                    }
                } catch (Exception $e) {
                    $done = false;
                }
            }
        }
        return $done && ((bool) $this->licence_number !== false);
    }

    /**
     * save licence number to database
     */
    public function saveLicence($licence_number)
    {
        Configuration::updateValue($this->module->short_name.'_licence', trim($licence_number));
    }

    /**
     * Update module
     */
    public function update()
    {
        $cookie = $this->module->context->cookie;

        $result = false;
        if ($this->checkLicence()) {
            $domain = $this->module->samdha_tools->getHttpHost();
            $iso_lang = Language::getIsoById($cookie->id_lang);
            $params = array(
                'module_name'=>$this->module->name,
                'lang' => $iso_lang,
                'domain' => $domain,
                'version' => $this->module->version,
                'licence_number' => $this->licence_number
            );

            $file = _PS_MODULE_DIR_.$this->module->name.'.zip';
            if (file_exists($file)) {
                unlink($file);
            }

            $this->module->samdha_tools->copyUrl(
                $this->module->update_url.'?'.http_build_query($params, '', '&'),
                $file
            );
            if (file_exists($file) && filesize($file)) {
                if (method_exists($this->module, 'uninstallOverrides')) {
                    $this->module->uninstallOverrides();
                }
                $this->module->samdha_tools->extractZip($file, _PS_MODULE_DIR_);
                if (method_exists($this->module, 'installOverrides')) {
                    $this->module->installOverrides();
                }
            } else {
                $error = error_get_last();
                throw new Exception('Error while downloading module.'.($error?' '.$error['message']:''));
            }
            if (file_exists($file)) {
                unlink($file);
            }
        }
        return $result;
    }

    public function getLicenceURL()
    {
        $cookie = $this->module->context->cookie;

        $domain = $this->module->samdha_tools->getHttpHost();
        $iso_lang = Language::getIsoById($cookie->id_lang);
        $employee = new Employee($cookie->id_employee);
        $params = array(
            'module_name'=>$this->module->name,
            'lang' => $iso_lang,
            'domain' => $domain,
            'email' => $employee->email
        );

        $url = $this->module->licence_url;

        return $url.'?'.http_build_query($params, '', '&');
    }

    public function getSupportURL()
    {
        $iso_lang = Language::getIsoById($this->module->context->cookie->id_lang);
        return sprintf(
            $this->module->support_url,
            $this->module->id_addons,
            $this->licence_number,
            $iso_lang
        );
    }

    public function getBoxContent()
    {
        $cookie = $this->module->context->cookie;

        $result = '';
        $registered = $this->checkLicence();
        if ($registered) {
            $domain = $this->module->samdha_tools->getHttpHost();
            $iso_lang = Language::getIsoById($cookie->id_lang);
            try {
                $result = $this->module->samdha_tools->jsonRPCCall(
                    'support~module:supportBox',
                    array(
                        'module_name'=>$this->module->name,
                        'domain' => $domain,
                        'licence_number' => $this->licence_number,
                        'lang' => $iso_lang
                    )
                );
            } catch (Exception $e) {
                if (class_exists('PrestaShopModuleException')) {
                    throw new PrestaShopModuleException('Error getting box content: '.$e->getMessage(), 1);
                } else {
                    throw new Exception('Error checking module getting box content: '.$e->getMessage(), 1);
                }
            }
        }
        return $result;
    }
}
