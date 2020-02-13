<?php
/**
 * NOTICE OF LICENSE.
 *
 * This file is licenced under the Software License Agreement.
 * With the purchase or the installation of the software in your application
 * you accept the licence agreement.
 *
 * You must not modify, adapt or create derivative works of this source code
 *
 * @author    Zero11
 * @copyright 2015-2016 Zero11 S.r.l.
 * @license   Proprietary
 */

include_once dirname(__FILE__) . '/../dropshipping.php';

/**
 * @class DropshippingImportHelper
 */
class DropshippingTranslator extends AdminController
{
    protected $dropshipping;

    public function __construct()
    {
        $this->dropshipping = new Dropshipping();
    }

    public function t($string, $class = null, $addslashes = false, $htmlentities = true)
    {
        if (_PS_VERSION_>= '1.7') {
            return Context::getContext()->getTranslator()->trans($string);
        } else {
            return $this->dropshipping->l($string, $class, $addslashes, $htmlentities);
        }
    }
}
