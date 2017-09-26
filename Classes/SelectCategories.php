<?php
namespace DirectMailTeam\DirectMail;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Localize categories for backend forms
 *
 * @author		Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 */
class SelectCategories
{
    public $sys_language_uid = 0;
    public $collate_locale = 'C';

    /**
     * Get the localization of the select field items (right-hand part of form)
     * Referenced by TCA
     *
     * @param	array $params Array of searched translation
     *
     * @return	void
     */
    public function get_localized_categories(array $params)
    {
        global $LANG;

/*
        $params['items'] = &$items;
        $params['config'] = $config;
        $params['TSconfig'] = $iArray;
        $params['table'] = $table;
        $params['row'] = $row;
        $params['field'] = $field;
*/
        $config = $params['config'];
        $table = $config['itemsProcFunc_config']['table'];

            // initialize backend user language
        if ($LANG->lang && ExtensionManagementUtility::isLoaded('static_info_tables')) {
            $sysPage = GeneralUtility::makeInstance('TYPO3\CMS\Frontend\Page\PageRepository');
            $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
                'sys_language.uid',
                'sys_language LEFT JOIN static_languages ON sys_language.static_lang_isocode = static_languages.uid',
                'static_languages.lg_typo3 = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr($LANG->lang, 'static_languages') .
                    $sysPage->enableFields('sys_language') .
                    $sysPage->enableFields('static_languages')
                );
            while (($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))) {
                $this->sys_language_uid = $row['uid'];
                $this->collate_locale = $row['lg_collate_locale'];
            }
            $GLOBALS['TYPO3_DB']->sql_free_result($res);
        }

        if (is_array($params['items']) && !empty($params['items'])) {
            foreach ($params['items'] as $k => $item) {
                $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
                    '*',
                    $table,
                    'uid=' . intval($item[1])
                );
                while (($rowCat = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))) {
                    if (($localizedRowCat = DirectMailUtility::getRecordOverlay($table, $rowCat, $this->sys_language_uid, ''))) {
                        $params['items'][$k][0] = $localizedRowCat['category'];
                    }
                }
                $GLOBALS['TYPO3_DB']->sql_free_result($res);
            }
        }
    }
}
