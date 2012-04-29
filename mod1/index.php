<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 1999-2004 Kasper Skaarhoj (kasper@typo3.com)
 *  (c) 2005-2006 Jan-Erik Revsbech <jer@moccompany.com>
 *  (c) 2006 Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
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

unset($MCONF);
include ('conf.php');
include ($BACK_PATH.'init.php');
include ($BACK_PATH.'template.php');
$LANG->includeLLFile('EXT:direct_mail/locallang/locallang_mod2-6.xml');
$LANG->includeLLFile('EXT:direct_mail/locallang/locallang_csh_sysdmail.xml');

/**
 * Class to producing navigation frame of the tx_directmail extension
 *
 * @author		Kasper Skårhøj <kasper@typo3.com>
 * @author		Ivan-Dharma Kartolo	<ivan.kartolo@dkd.de>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 * @version 	$Id: index.php 30331 2010-02-22 22:27:07Z ivankartolo $
 */

class tx_directmail_navframe{

	/**
	 * the template object
	 * @var	template
	 */
	var $doc;

	/**
	 * set highlight
	 * @var	string
	 */
	var $doHighlight;

	/**
	 * html output
	 * @var string
	 */
	var $content;

	var $cshTable;
	var $pageinfo;

	/**
 	 * first initialization of the global variables. Set some JS-code
 	 *
 	 * @return	void		...
 	 */
	function init()	{
		global $BE_USER,$LANG,$BACK_PATH,$TYPO3_CONF_VARS;

		$this->doc = t3lib_div::makeInstance('template');
		$this->doc->backPath = $BACK_PATH;
		$this->doc->setModuleTemplate('EXT:direct_mail/mod1/mod_template.html');
		$this->doc->showFlashMessages = FALSE;

		$currentSubScript = t3lib_div::_GP('currentSubScript');

			// Setting highlight mode:
		$this->doHighlight = !$BE_USER->getTSConfigVal('options.pageTree.disableTitleHighlight');
		$this->doc->inDocStyles = '
		#typo3-docheader-row2 { line-height: 14px !important; }
		#typo3-docheader-row2 span { font-weight: bold; margin-top: -3px; color: #000; margin-top: 0; padding-left: 20px; }
';

			// Setting JavaScript for menu.
		$this->doc->JScode=$this->doc->wrapScriptTags(
			($currentSubScript?'top.currentSubScript=unescape("'.rawurlencode($currentSubScript).'");':'').'

			function jumpTo(params,linkObj,highLightID)	{ //
				var theUrl = top.TS.PATH_typo3+top.currentSubScript+"?"+params;

				if (top.condensedMode)	{
					top.content.document.location=theUrl;
				} else {
					parent.list_frame.document.location=theUrl;
				}
				'.($this->doHighlight?'hilight_row("row"+top.fsMod.recentIds["txdirectmailM1"],highLightID);':'').'
				'.(!$GLOBALS['CLIENT']['FORMSTYLE'] ? '' : 'if (linkObj) {linkObj.blur();}').'
				return false;
			}


				// Call this function, refresh_nav(), from another script in the backend if you want to refresh the navigation frame (eg. after having changed a page title or moved pages etc.)
				// See t3lib_BEfunc::getSetUpdateSignal()
			function refresh_nav() { //
				window.setTimeout("_refresh_nav();",0);
			}


			function _refresh_nav()	{ //
				document.location="'.htmlspecialchars(t3lib_div::getIndpEnv('SCRIPT_NAME').'?unique='.time()).'";
			}

				// Highlighting rows in the page tree:
			function hilight_row(frameSetModule,highLightID) { //
					// Remove old:
				theObj = document.getElementById(top.fsMod.navFrameHighlightedID[frameSetModule]);
				if (theObj)	{
					theObj.style.backgroundColor="";
				}

					// Set new:
				top.fsMod.navFrameHighlightedID[frameSetModule] = highLightID;
				theObj = document.getElementById(highLightID);
				if (theObj)	{
					theObj.style.backgroundColor="'.t3lib_div::modifyHTMLColorAll($this->doc->bgColor,-5).'";
				}
			}
		');
	}

	/**
	 * Main function, rendering the browsable page tree
	 *
	 * @return	void		...
	 */
	function main()	{
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'pages',
			'doktype = 254 AND module in (\'dmail\')'. t3lib_BEfunc::deleteClause('pages'),
			'',
			'title'
		);
		$out = '';
		while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)){
			if(t3lib_BEfunc::readPageAccess($row['uid'],$GLOBALS['BE_USER']->getPagePermsClause(1))){
				$out .= '<tr onmouseover="this.style.backgroundColor=\''.t3lib_div::modifyHTMLColorAll($this->doc->bgColor,-5).'\'" onmouseout="this.style.backgroundColor=\'\'">'.
					'<td id="dmail_'.$row['uid'].'" ><a href="#" onclick="top.fsMod.recentIds[\'txdirectmailM1\']='.$row['uid'].';jumpTo(\'id='.$row['uid'].'\',this,\'dmail_'.$row['uid'].'\');">&nbsp;&nbsp;'.
					//t3lib_iconWorks::getIconImage('pages',$row,$BACK_PATH,'title="'.htmlspecialchars(t3lib_BEfunc::getRecordPath($row['uid'], ' 1=1',20)).'" align="top"').
					t3lib_iconWorks::getSpriteIconForRecord('pages',$row,array('title' => htmlspecialchars(t3lib_BEfunc::getRecordPath($row['uid'], ' 1=1',20)), 'align'=>'top')).
					htmlspecialchars($row['title']).'</a></td></tr>';
			}
		}

		$content = '<table cellspacing="0" cellpadding="0" border="0" width="100%">'.$out.'</table>';

			// Adding highlight - JavaScript
		if ($this->doHighlight)	$content .=$this->doc->wrapScriptTags('
			hilight_row("",top.fsMod.navFrameHighlightedID["web"]);
		');


		$docHeaderButtons = array(
			'CSH' => t3lib_BEfunc::cshItem($this->cshTable,'folders',$GLOBALS['BACK_PATH'], TRUE),
			'REFRESH' => '<a href="'.htmlspecialchars(t3lib_div::linkThisScript(array('unique' => uniqid('directmail_navframe')))).'">'.
				'<img' . t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/refresh_n.gif','width="14" height="14"').' title="'.$GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.xml:labels.refresh',1).'" alt="" /></a>'
		);


		$markers = array(
			'HEADLINE' => $GLOBALS['LANG']->getLL('dmail_folders'),
			'CONTENT' => $content
		);
			// Build the <body> for the module
		$this->content = $this->doc->startPage('TYPO3 Direct Mail Navigation');
		$this->content.= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers);



	}

	/**
	 * Outputting the accumulated content to screen
	 *
	 * @return	void
	 */
	function printContent()	{
		$this->content.= $this->doc->endPage();
		$this->content = $this->doc->insertStylesAndJS($this->content);
		echo $this->content;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod1/index.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod1/index.php']);
}

// Make instance:
/** @var $SOBE tx_directmail_navframe */
$SOBE = t3lib_div::makeInstance('tx_directmail_navframe');
$SOBE->init();
$SOBE->main();
$SOBE->printContent();

?>
