<?php
/**
 * @class AdminDropshippingStatusController
 */
class AdminSettingsDropshippingController extends ModuleAdminController
{
    public function __construct() {
        parent::__construct();
        if(!Tools::redirectAdmin('index.php?controller=AdminModules&token='.Tools::getAdminTokenLite('AdminModules').'&configure=dropshipping')) {
            return false;
        }
        return true;
    }
}
