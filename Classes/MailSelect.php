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

use TYPO3\CMS\Core\Database\QueryGenerator;

/**
 * Used to generate queries for selecting users in the database
 *
 * @author		Kasper Sk�rh�j <kasper@typo3.com>
 * @author		Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 */
class MailSelect extends QueryGenerator
{
    public $allowedTables = ['tt_address','fe_users'];

    /**
     * Build a dropdown box. override function from parent class. Limit only to 2 tables.
     *
     * @param	string		$name Name of the select-field
     * @param	string		$cur Table name, which is currently selected
     *
     * @return	string		HTML select-field
     * @see t3lib_queryGenerator::mkTableSelect()
     */
    public function mkTableSelect($name, $cur)
    {
        $out = '<select name="' . $name . '" onChange="submit();">';
        $out .= '<option value=""></option>';
        reset($GLOBALS['TCA']);
        foreach ($GLOBALS['TCA'] as $tN => $_) {
            if ($GLOBALS['BE_USER']->check('tables_select', $tN) && in_array($tN, $this->allowedTables)) {
                $out .='<option value="' . $tN . '"' . ($tN == $cur ? ' selected':'') . '>' .
                    $GLOBALS['LANG']->sl($GLOBALS['TCA'][$tN]['ctrl']['title']) .
                    '</option>';
            }
        }
        $out .= '</select>';
        return $out;
    }
}
