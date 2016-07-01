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

    public $allowedTables = array('tt_address','fe_users');
    
    public function __construct()
    {
        // selecting tt_address by MM categories
        $this->lang['comparison']['74_'] = 'Direct Mail Category tt_address';
        $this->compSQL[74] = 'uid IN (SELECT tt.uid FROM tt_address tt LEFT JOIN sys_dmail_ttaddress_category_mm mm ON tt.uid = mm.uid_local WHERE mm.uid_foreign = #VALUE#)';
        $this->lang['comparison']['75_'] = 'Direct Mail Category tt_address';
        $this->compSQL[75] = 'uid NOT IN (SELECT tt.uid FROM tt_address tt LEFT JOIN sys_dmail_ttaddress_category_mm mm ON tt.uid = mm.uid_local WHERE mm.uid_foreign = #VALUE#)';

        // selecting fe_users by MM categories
        $this->lang['comparison']['76_'] = 'Direct Mail Category fe_users';
        $this->compSQL[76] = 'uid IN (SELECT fe.uid FROM fe_users fe LEFT JOIN sys_dmail_feuser_category_mm mm ON fe.uid = mm.uid_local WHERE mm.uid_foreign = #VALUE#)';
        $this->lang['comparison']['77_'] = 'Direct Mail Category fe_users';
        $this->compSQL[77] = 'uid NOT IN (SELECT fe.uid FROM fe_users fe LEFT JOIN sys_dmail_feuser_category_mm mm ON fe.uid = mm.uid_local WHERE mm.uid_foreign = #VALUE#)';
        
        // selecting tt_content by MM categories
        $this->lang['comparison']['78_'] = 'Direct Mail Category tt_content';
        $this->compSQL[78] = 'uid IN (SELECT tt.uid FROM tt_content tt LEFT JOIN sys_dmail_ttcontent_category_mm mm ON tt.uid = mm.uid_local WHERE mm.uid_foreign = #VALUE#)';
        $this->lang['comparison']['79_'] = 'Direct Mail Category tt_content';
        $this->compSQL[79] = 'uid NOT IN (SELECT tt.uid FROM tt_content tt LEFT JOIN sys_dmail_ttcontent_category_mm mm ON tt.uid = mm.uid_local WHERE mm.uid_foreign = #VALUE#)';
    }

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
        reset($GLOBALS["TCA"]);
        foreach ($GLOBALS["TCA"] as $tN => $_) {
            if ($GLOBALS["BE_USER"]->check('tables_select', $tN) && in_array($tN, $this->allowedTables)) {
                $out .='<option value="' . $tN . '"' . ($tN == $cur ? ' selected':'') . '>' .
                    $GLOBALS["LANG"]->sl($GLOBALS["TCA"][$tN]['ctrl']['title']) .
                    '</option>';
            }
        }
        $out.='</select>';
        return $out;
    }
}
