<?php
namespace DirectMailTeam\DirectMail\Module;

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

use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\IconFactory;

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

class NavFrame {

	/**
	 * The template object
	 * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
	 */
	public $doc;

	/**
	 * Set highlight
	 * @var	string
	 */
	protected $doHighlight;

	/**
	 * HTML output
	 * @var string
	 */
	protected $content;

	var $pageinfo;

	/**
	 * First initialization of the global variables. Set some JS-code
	 *
	 * @return	void
	 */
	function init() {
		global $BE_USER, $BACK_PATH;

		$this->doc = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
		$this->doc->setModuleTemplate('EXT:direct_mail/mod1/mod_template.html');
		$this->doc->showFlashMessages = FALSE;

		$currentModule = GeneralUtility::_GP('currentModule');
		$currentSubScript = BackendUtility::getModuleUrl($currentModule);

		// Setting highlight mode:
		$this->doHighlight = !$BE_USER->getTSConfigVal('options.pageTree.disableTitleHighlight');

		$this->doc->inDocStylesArray[] = '#typo3-docheader-row2 { line-height: 14px !important; }
		#typo3-docheader-row2 span { font-weight: bold; margin-top: -3px; color: #000; margin-top: 0; padding-left: 20px; }';

		// Setting JavaScript for menu.
		$this->doc->JScode = $this->doc->wrapScriptTags(
			($currentModule ? 'top.currentSubScript=unescape("' . rawurlencode($currentSubScript) . '");' : '') . '

			function jumpTo(params,linkObj,highLightID)	{ //
				var theUrl = top.currentSubScript+"&"+params;

				if (top.condensedMode)	{
					top.content.document.location=theUrl;
				} else {
					parent.list_frame.document.location=theUrl;
				}
				' . ($this->doHighlight ? 'hilight_row("row"+top.fsMod.recentIds["txdirectmailM1"],highLightID);' : '') . '
				' . (!$GLOBALS['CLIENT']['FORMSTYLE'] ? '' : 'if (linkObj) {linkObj.blur();}') . '
				return false;
			}


				// Call this function, refresh_nav(), from another script in the backend if you want to refresh the navigation frame (eg. after having changed a page title or moved pages etc.)
				// See t3lib_BEfunc::getSetUpdateSignal()
			function refresh_nav() { //
				window.setTimeout("_refresh_nav();",0);
			}


			function _refresh_nav()	{ //
				document.location="' . htmlspecialchars(GeneralUtility::getIndpEnv('SCRIPT_NAME') . '?unique=' . time()) . '";
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
					theObj.style.backgroundColor="' . GeneralUtility::modifyHTMLColor($this->doc->bgColor, -5, -5, -5) . '";
				}
			}
		');
	}

	/**
	 * Main function, rendering the browsable page tree
	 *
	 * @return	void
	 */
	public function main() {
		$iconFactory = GeneralUtility::makeInstance(IconFactory::class);

		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'*',
			'pages',
			'doktype = 254 AND module in (\'dmail\')' . BackendUtility::deleteClause('pages'),
			'',
			'title'
		);
		$out = '';

		while (($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))) {
			if(BackendUtility::readPageAccess($row['uid'],$GLOBALS['BE_USER']->getPagePermsClause(1))){
				$icon = $iconFactory->getIconForRecord('pages', $row, Icon::SIZE_SMALL)->render();

				$out .= '<tr onmouseover="this.style.backgroundColor=\'' . GeneralUtility::modifyHTMLColorAll($this->doc->bgColor,-5) . '\'" onmouseout="this.style.backgroundColor=\'\'">' .
					'<td id="dmail_' . $row['uid'] . '" >
						<a href="#" onclick="top.fsMod.recentIds[\'txdirectmailM1\']=' . $row['uid'] . ';jumpTo(\'id=' . $row['uid'] . '\',this,\'dmail_' . $row['uid'] . '\');">' .
					$icon .
					'&nbsp;' . htmlspecialchars($row['title']) . '</a></td></tr>';
			}
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);
		$content = '<table cellspacing="0" cellpadding="0" border="0" width="100%">' . $out . '</table>';

		// Adding highlight - JavaScript
		if ($this->doHighlight)	$content .=$this->doc->wrapScriptTags('
			hilight_row("",top.fsMod.navFrameHighlightedID["web"]);
		');


		$docHeaderButtons = array(
			'CSH' => BackendUtility::cshItem('_MOD_txdirectmailM1', 'folders', $GLOBALS['BACK_PATH'], TRUE),
			'REFRESH' => '<a href="' . htmlspecialchars(GeneralUtility::linkThisScript(array('unique' => uniqid('directmail_navframe')))) . '">' .
				$iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL) . '</a>'
		);

		$markers = array(
			'HEADLINE' => '',
			'CONTENT' => $this->getLanguageService()->getLL('dmail_folders') . $content
		);
		// Build the <body> for the module
		$this->content = $this->doc->startPage('TYPO3 Direct Mail Navigation');
		$this->content .= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers);



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

	/**
	 * Returns LanguageService
	 *
	 * @return \TYPO3\CMS\Lang\LanguageService
	 */
	protected function getLanguageService() {
		return $GLOBALS['LANG'];
	}
}
