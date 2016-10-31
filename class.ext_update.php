<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2012 Ivan Kartolo <ivan at kartolo dot de)
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*  A copy is found in the textfile GPL.txt and important notices to the license
*  from the author is found in LICENSE.txt distributed with these scripts.
*
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

/**
 * Class for updating Direct Mail to version 3.
 *
 * @author		Ivan Kartolo <ivan at kartolo dot de>
 */
class ext_update
{
    /**
     * Main function, returning the HTML content of the module.
     *
     * @return string HTML
     */
    public function main()
    {
        $GLOBALS['LANG']->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf');

        $content = $this->displayWarning();
        if (!GeneralUtility::_GP('do_update')) {
            $onClick = "document.location='".GeneralUtility::linkThisScript(array('do_update' => 1))."'; return false;";
            $content .= htmlspecialchars($GLOBALS['LANG']->getLL('update_convert_now')).'
				<br /><br />
				<form action=""><input type="submit" value="' .htmlspecialchars($GLOBALS['LANG']->getLL('update_convert_do_it_now')).'" onclick="'.htmlspecialchars($onClick).'"></form>
			';
        } else {
            $updated = $this->convertTable();
            $content .= sprintf($GLOBALS['LANG']->getLL('update_convert_result'), $updated);
        }

        return $content;
    }

    /**
     * Convert the mailcontent data to base64 coded.
     *
     * @return int $i: the counter
     */
    public function convertTable()
    {
        $dmailRows = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
            'uid',
            'sys_dmail',
            '1=1'
        );

        $i = 0;
        foreach ($dmailRows as $row) {
            // get the mailContent
            $mailContent = $GLOBALS['TYPO3_DB']->exec_SELECTgetRows(
                'mailContent',
                'sys_dmail',
                'uid = '.$row['uid']
            );

            if (is_array(unserialize($mailContent[0]['mailContent']))) {
                // update the table
                $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                    'sys_dmail',
                    'uid = '.$row['uid'],
                    array('mailContent' => base64_encode($mailContent[0]['mailContent']))
                );

                // add the counter
                ++$i;
            }
        }

        return $i;
    }
    /**
     * Checks how many rows are found and returns true if there are any.
     *
     * @return bool true if user have access, otherwise false
     */
    public function access()
    {
        // We cannot update before the extension is installed: required tables are not yet in TCA
        if (ExtensionManagementUtility::isLoaded('direct_mail')) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Enter description here ...
     *
     * @return string
     */
    public function displayWarning()
    {
        $out = '
			<div style="padding:15px 15px 20px 0;">
				<div class="typo3-message message-warning">
						<div class="message-header">' .$GLOBALS['LANG']->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf:update_warningHeader').'</div>
						<div class="message-body">
						' .$GLOBALS['LANG']->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf:update_warningMsg').'
					</div>
				</div>
			</div>';

        return $out;
    }
}
