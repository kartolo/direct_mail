<?php
/***************************************************************
*  Copyright notice
*
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

/**
 * @author		Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 * @version		$Id: class.tx_directmail_ttnews_plaintext.php 15583 2009-01-10 17:59:30Z ivankartolo $
 */

require_once(t3lib_extMgm::extPath('direct_mail').'pi1/class.tx_directmail_pi1.php');

/**
 * Generating plain text content of tt_news records for Direct Mails
 * Implements hook $TYPO3_CONF_VARS['EXTCONF']['tt_news']['extraCodesHook']
 *
 */
class tx_directmail_ttnews_plaintext {
	/**
	 * @var tslib_cObj
	 */
	var $cObj;

	/**
	 * ts array
	 * @var array
	 */
	var $conf = array();

	/**
	 * @var array
	 */
	var $config = array();
	var $charWidth = 76;
	/**
	 * @var tx_directmail_pi1
	 */
	var $renderPlainText;

	/**
	 * @var int
	 */
	var $tt_news_uid;

	/**
	 * @var bool
	 */
	var $enableFields;

	/**
	 * @var string
	 */
	var $sys_language_mode;

	/**
	 * @var string
	 */
	var $templateCode;

	/**
	 * Main function, called from TypoScript
	 * A content object that renders "tt_content" records. See the comment to this class for TypoScript example of how to trigger it.
	 * This detects the CType of the current content element and renders it accordingly. Only wellknown types are rendered.
	 *
	 * @param	tslib_pibase	$invokingObj the tt_news object
	 * @return	string			Plain text content
	 */
	function extraCodesProcessor(&$invokingObj) {
		$content = '';
		$this->conf = $invokingObj->conf;

		if ($this->conf['code'] == 'PLAINTEXT') {

			$this->cObj = $invokingObj->cObj;
			$this->config = $invokingObj->config;
			$this->tt_news_uid = $invokingObj->tt_news_uid;
			$this->enableFields = $invokingObj->enableFields;
			$this->sys_language_mode = $invokingObj->sys_language_mode;
			$this->templateCode = $invokingObj->templateCode;

			$this->renderPlainText = t3lib_div::makeInstance('tx_directmail_pi1');
			$this->renderPlainText->init($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_directmail_pi1.']);
			$this->renderPlainText->cObj = $this->cObj;
			$this->renderPlainText->labelsList = 'tt_news_author_prefix,tt_news_author_date_prefix,tt_news_author_email_prefix,tt_news_short_header,tt_news_bodytext_header';

			$lines = array();
			$singleWhere = 'tt_news.uid=' . intval($this->tt_news_uid);
			$singleWhere .= ' AND type=0' . $this->enableFields; // type=0->only real news.
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'*',
				'tt_news',
				$singleWhere
				);
			$row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
				// get the translated record if the content language is not the default language
			if ($GLOBALS['TSFE']->sys_language_content) {
				$OLmode = ($this->sys_language_mode == 'strict'?'hideNonTranslated':'');
				$row = $GLOBALS['TSFE']->sys_page->getRecordOverlay('tt_news', $row, $GLOBALS['TSFE']->sys_language_content, $OLmode);
			}
			if (is_array($row)) {
					// Render the title
				$lines[] = $this->renderPlainText->renderHeader($row['title']);

					// Render author of the tt_news record
				$lines[] = $this->renderAuthor($row);

					// Render the short version of the tt_news record
				$lines[] = $this->renderPlainText->breakContent(strip_tags($this->renderPlainText->parseBody($row['short'],'tt_news_short')));

					// Render the main text of the tt_news record
				$lines[] = $this->renderPlainText->breakContent(strip_tags($this->renderPlainText->parseBody($row['bodytext'],'tt_news_bodytext')));

					// Render the images of the tt_news record.
				$lines[] = $this->getImages($row);

					// Render the downloads of the tt_news record.
				$lines[] = $this->renderPlainText->renderUploads($row['news_files']);

			} elseif ($this->sys_language_mode == 'strict' && $this->tt_news_uid) {
				$noTranslMsg = $this->cObj->stdWrap($invokingObj->pi_getLL('noTranslMsg','Sorry, there is no translation for this news-article'), $this->conf['noNewsIdMsg_stdWrap.']);
				$content .= $noTranslMsg;
			}

			if (!empty($lines)) {
				$content = implode(chr(10),$lines).$content;
			}

				// Substitute labels
			if (!empty($content)) {
				$markerArray = array();
				$markerArray = $this->renderPlainText->addLabelsMarkers($markerArray);
				$content = $this->cObj->substituteMarkerArray($content, $markerArray);
			}
		}

		return $content;
	}

	/**
	 * Get images found in the "image" field of "tt_news"
	 *
	 * @param	array	$row: tt_news record
	 * @return	string	Content
	 */
	function getImages($row) {
		$images_arr = explode(',',$row['image']);
		$images = $this->renderPlainText->renderImages($images_arr, '', $row['imagecaption']);
		return $images;
	}

	/**
	 * Renders the author and date columns of the tt_news record
	 *
	 * @param	string	$row: The tt_news record
	 * @param	int		$type:
	 * @return	string	Content
	 */
	function renderAuthor($row, $type=0) {
		if ($row['author']) {
			$hConf = $this->renderPlainText->conf['tt_news_author.'];
			$str = $this->renderPlainText->getString($hConf['prefix']).$row['author'].$this->renderPlainText->getString($hConf['emailPrefix']).'<'.$row['author_email'].'>';
			$defaultType = tx_directmail_static::intInRangeWrapper($hConf['defaultType'],1,5);
			$type = tx_directmail_static::intInRangeWrapper($type,0,6);

			if (!$type) {
				$type = $defaultType;
			}

			if ($type != 6)	{	// not hidden
				$tConf = $hConf[$type.'.'];

				$lines = array();

				$blanks = tx_directmail_static::intInRangeWrapper($tConf['preBlanks'],0,1000);
				if ($blanks) {
					$lines[] = str_pad('', $blanks-1, chr(10));
				}

				$lines = $this->renderPlainText->pad($lines,$tConf['preLineChar'],$tConf['preLineLen']);

				$blanks = tx_directmail_static::intInRangeWrapper($tConf['preLineBlanks'],0,1000);
				if ($blanks) {
					$lines[] = str_pad('', $blanks-1, chr(10));
				}

				if ($row['datetime']) {
					$lConf = $this->conf['displaySingle.'];
					$lines[] = $this->renderPlainText->getString($hConf['datePrefix']).
						$this->cObj->stdWrap($row['datetime'], $lConf['date_stdWrap.']).
						' '.
						$this->cObj->stdWrap($row['datetime'], $lConf['time_stdWrap.']);
				}

				$lines[]=$this->cObj->stdWrap($str,$tConf['stdWrap.']);

				$blanks = tx_directmail_static::intInRangeWrapper($tConf['postLineBlanks'],0,1000);
				if ($blanks) {
					$lines[]=str_pad('', $blanks-1, chr(10));
				}

				$lines = $this->renderPlainText->pad($lines,$tConf['postLineChar'],$tConf['postLineLen']);

				$blanks = tx_directmail_static::intInRangeWrapper($tConf['postBlanks'],0,1000);
				if ($blanks) {
					$lines[]=str_pad('', $blanks-1, chr(10));
				}
				return implode(chr(10),$lines);
			}
		}
		return "";
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.tx_directmail_ttnews_plaintext.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/res/scripts/class.tx_directmail_ttnews_plaintext.php']);
}
?>