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

class BdroppyRemoteCategory extends ObjectModel
{
    public $id;
    public $rewix_category_id;
    public $ps_category_id;

    const REWIX_BRAND_ID = 1;
    const REWIX_CATEGORY_ID = 4;
    const REWIX_SUBCATEGORY_ID = 5;
    const REWIX_COLOR_ID = 13;
    const REWIX_GENDER_ID = 26;
    const REWIX_SEASON_ID = 11;

    public static $definition = array(
        'table' => 'bdroppy_remotecategory',
        'primary' => 'id',
        'multilang' => false,
        'fields' => array(
            'id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
            ),
            'rewix_category_id' => array('type' => self::TYPE_STRING),
            'ps_category_id' => array(
                'type' => self::TYPE_INT,
                'validate' => 'isInt',
            ),
        ),
    );

    /**
     * @param string $tag - e.g. 4-shoes-5-highheels
     *
     * @return BdroppyRemoteCategory
     */
    public static function fromRewixTag($tag)
    {
        $remoteCategory = new self(self::getIdByRewixId($tag));

        return $remoteCategory;
    }

    /**
     * @param int $id
     * @return int
     */
    public static function getIdByPsId($id)
    {
        $query = new DbQuery();
        $query->select('id');
        $query->from('bdroppy_remotecategory');
        $query->where('ps_category_id = '.(int)$id);

        $result = Db::getInstance()->getValue($query);

        if ($result > 0) {
            return $result;
        }

        return 0;
    }

    /**
     * @param int $id
     * @return int
     */
    public static function getIdByRewixId($id)
    {
        $query = new DbQuery();
        $query->select('id');
        $query->from('bdroppy_remotecategory');
        $query->where("rewix_category_id = '$id'");

        $result = Db::getInstance()->getValue($query);

        if ($result > 0) {
            return $result;
        }

        return 0;
    }

    /**
     * @param $parent - Category object
     * @param $tagId - The rewix tag id
     * @param $value - The rewix value
     * @param $translation - Rewix tag translation
     *
     * @return Category
     */
    private static function getTagValue($product, $name, $lang)
    {
        foreach ($product->tags as $tag) {
            if ($tag->name === $name) {
                if (isset($tag->value->translations->{$lang})) {
                    return $tag->value->translations->{$lang};
                } else {
                    return $tag->value->value;
                }
            }
        }
    }

    public static function getCategory($parent, $tagId, $value, $xmlProduct)
    {
        $tag = $tagId.'-'.$value;
        $parentTag = new self(self::getIdByPsId($parent->id));

        if ($parentTag->id > 0) {
            $tag = $parentTag->rewix_category_id.'-'.$tag;
        }

        $remoteCategory = self::fromRewixTag($tag);
        $category = new Category($remoteCategory->ps_category_id);

        if ($category->id < 1) {
            $category->id_parent = $parent->id;
            $langs = [];
            $langs['en'] = 'en_US';
            $langs['gb'] = 'en_US';
            $langs['it'] = 'it_IT';
            $langs['fr'] = 'fr_FR';
            $langs['pl'] = 'pl_PL';
            $langs['es'] = 'es_ES';
            $langs['de'] = 'de_DE';
            $langs['ru'] = 'ru_RU';
            $langs['nl'] = 'nl_NL';
            $langs['ro'] = 'ro_RO';
            $langs['et'] = 'et_EE';
            $langs['hu'] = 'hu_HU';
            $langs['sv'] = 'sv_SE';
            $langs['sk'] = 'sk_SK';
            $langs['cs'] = 'cs_CZ';
            $langs['pt'] = 'pt_PT';
            $langs['fi'] = 'fi_FI';
            $langs['bg'] = 'bg_BG';
            $langs['da'] = 'da_DK';
            $langs['lt'] = 'lt_LT';
            $langs['el'] = 'el_GR';

            $languages = Language::getLanguages();
            foreach ($languages as $lang) {
                $langCode = $langs[$lang['iso_code']];
                $catTxt = '';
                if ($tagId == self::REWIX_GENDER_ID) {
                    $catTxt = self::getTagValue($xmlProduct, 'gender', $langCode);
                }
                if ($tagId == self::REWIX_CATEGORY_ID) {
                    $catTxt = self::getTagValue($xmlProduct, 'category', $langCode);
                }
                if ($tagId == self::REWIX_SUBCATEGORY_ID) {
                    $catTxt = self::getTagValue($xmlProduct, 'subcategory', $langCode);
                }
                $category->name[$lang['id_lang']] = $catTxt;
                $category->link_rewrite[$lang['id_lang']] = Tools::link_rewrite($catTxt);
            }
            $category->save();

            $remoteCategory->ps_category_id = $category->id;
            $remoteCategory->rewix_category_id = $tag;
            $remoteCategory->save();
        }

        return $category;
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
        $query->from('bdroppy_remotecategory');
        $query->where('ps_category_id = '.(int)$id);
        $query->limit(1);

        return Db::getInstance()->execute($query);
    }
}
