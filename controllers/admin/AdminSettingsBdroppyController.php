<?php
/**
 * @class AdminBdroppyStatusController
 */
class AdminSettingsBdroppyController extends ModuleAdminController
{
    public function __construct() {
        parent::__construct();
        if(!Tools::redirectAdmin('index.php?controller=AdminModules&token='.Tools::getAdminTokenLite('AdminModules').'&configure=bdroppy')) {
            return false;
        }
        return true;
    }
}
