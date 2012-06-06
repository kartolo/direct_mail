<?php
/***************************************************************
*  Copyright notice
*
*  (c) 1999-2005 Kasper Skaarhoj (kasperYYYY@typo3.com)
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
 * @author		Kasper Skaarhoj <kasperYYYY@typo3.com>
 * @author		Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * @package 	TYPO3
 * @subpackage 	direct_mail
 *
 * @version		$Id: class.tx_directmail_pi1.php 30973 2010-03-10 17:41:28Z ivankartolo $
 */


require_once(PATH_tslib.'class.tslib_pibase.php');

/**
 * Generating plain text rendering of content elements for inclusion as plain text content in Direct Mails
 * That means text-only output. No HTML at all.
 * To use and configure this plugin, you may include static template "Direct Mail Plain Text".
 * If you do so, the plain text output will appear with type=99.
 *
 */
class tx_directmail_pi1 extends tslib_pibase {
	/**
	 * @var tslib_cObj
	 */
	var $cObj;
	var $conf = array();
	var $prefixId = 'tx_directmail_pi1';
	var $scriptRelPath = 'pi1/class.tx_directmail_pi1.php';
	var $extKey = 'direct_mail';
	var $charWidth = 76;
	var $linebreak;
	var $siteUrl;
	var $labelsList = 'header_date_prefix,header_link_prefix,uploads_header,images_header,image_link_prefix,caption_header,unrendered_content,link_prefix';

	/**
	 * Main function, called from TypoScript
	 * A content object that renders "tt_content" records. See the comment to this class for TypoScript example of how to trigger it.
	 * This detects the CType of the current content element and renders it accordingly. Only wellknown types are rendered.
	 *
	 * @param	string		$content: Empty, ignore.
	 * @param	array		$conf: TypoScript properties for this content object/function call
	 * @return	string		$content: Plain text content
	 */
	function main($content,$conf)	{
		global $TYPO3_CONF_VARS;

		$this->init($conf);

		$lines = array();
		$CType = (string)$this->cObj->data['CType'];
		switch($CType)	{
			case 'header':
				$lines[] = $this->getHeader();
				if ($this->cObj->data['subheader'])	{
					$lines[] = $this->breakContent(strip_tags($this->cObj->data['subheader']));
				}
			break;
			case 'text':
			case 'textpic':
				$lines[] = $this->getHeader();
				if (($CType == 'textpic') && !($this->cObj->data['imageorient']&24))	{
					$lines[] = $this->getImages();
					$lines[] = '';
				}
				$lines[] = $this->breakContent(strip_tags($this->parseBody($this->cObj->data['bodytext'])));
				if (($CType == 'textpic') && ($this->cObj->data['imageorient']&24))	{
					$lines[] = '';
					$lines[] = $this->getImages();
				}
			break;
			case 'image':
				$lines[] = $this->getHeader();
				$lines[] = $this->getImages();
			break;
			case 'uploads':
				$lines[] = $this->getHeader();
				$lines[] = $this->renderUploads($this->cObj->data['media']);
			break;
			case 'menu':
				$lines[] = $this->getHeader();
				$lines[] = $this->getMenuSitemap();
			break;
			case 'shortcut':
				$lines[] = $this->getShortcut();
			break;
			case 'bullets':
				$lines[] = $this->getHeader();
				$lines[] = $this->breakBulletlist(strip_tags($this->parseBody($this->cObj->data['bodytext'])));
			break;
			case 'table':
				$lines[] = $this->getHeader();
				$lines[] = $this->breakTable(strip_tags($this->parseBody($this->cObj->data['bodytext'])));
			break;
			case 'html':
				$lines[] = $this->getHTML();
			break;
			default:
					// Hook for processing other content types
				if (is_array($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['renderCType'])) {
					foreach($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['renderCType'] as $_classRef) {
						$_procObj = &t3lib_div::getUserObj($_classRef);
						$lines = array_merge($lines, $_procObj->renderPlainText($this,$content));
					}
				}
				if (empty($lines)) {
					$defaultOutput = $this->getString($this->conf['defaultOutput']);
					if ($defaultOutput) {
						$lines[] = str_replace('###CType###',$CType,$defaultOutput);
					}
				}
			break;
		}

		$lines[] = '';	// First break.
		$content = implode(chr(10),$lines);

			// Substitute labels
		$markerArray = array();
		$markerArray = $this->addLabelsMarkers($markerArray);
		$content = $this->cObj->substituteMarkerArray($content, $markerArray);

			// User processing:
		$content = $this->userProcess('userProc',$content);
		return $content;
	}

	/**
	 * initializing the parent class
	 *
	 * @param	array		$conf: TS conf
	 * @return	void		...
	 */
	function init($conf) {
		tslib_pibase::__construct();

		$this->conf = $conf;
		$this->pi_loadLL();
		$this->siteUrl = $this->conf['siteUrl'];

			// Default linebreak;
		$this->linebreak = chr(10);
		if ($this->conf['flowedFormat']) {
			$this->linebreak = chr(32).chr(10);
		}
	}

	/**
	 * Creates a menu/sitemap
	 *
	 * @return	string		$str: Content
	 */
	function getMenuSitemap()	{
		$str = $this->cObj->cObjGetSingle($this->conf['menu'],$this->conf['menu.']);
		$str = $this->breakBulletlist(trim(strip_tags(preg_replace('/<br\s*\/?>/i', chr(10), $this->parseBody($str)))));
		return $str;
	}

	/**
	 * Creates a shortcut ("Insert Records")
	 *
	 * @return	string		Plain Content without HTML comments
	 */
	function getShortcut()	{
		$str = $this->cObj->cObjGetSingle($this->conf['shortcut'],$this->conf['shortcut.']);
			//Remove html comment reporting shortcut inclusion
		return preg_replace('/<![ \r\n\t]*(--([^\-]|[\r\n]|-[^\-])*--[ \r\n\t]*)\>/', '', $str);
	}

	/**
	 * Creates an HTML element (stripping tags of course)
	 *
	 * @param	mixed		$str: HTML content (as string or in an array) to process. If not passed along, the bodytext field is used.
	 * @return	string		Plain content.
	 */
	function getHTML($str=array())	{
		return $this->breakContent(strip_tags(preg_replace('/<br\s*\/?>/i',chr(10),$this->parseBody(is_string($str)?$str:$this->cObj->data['bodytext']))));
	}

	/**
	 * Creates a header (used for most elements)
	 *
	 * @return	string		Content
	 * @see renderHeader()
	 */
	function getHeader() {
		// links...
		return $this->renderHeader($this->cObj->data['header'], $this->cObj->data['header_layout']);
	}

	/**
	 * Get images found in the "image" field of "tt_content"
	 *
	 * @return	string		Content
	 */
	function getImages() {
		$images_arr = array();
		$this->getImagesStandard($images_arr);
		if(t3lib_extMgm::isLoaded('dam')){
			$this->getImagesFromDam($images_arr);
		}

		$images = $this->renderImages($images_arr, !$this->cObj->data['image_zoom']?$this->cObj->data['image_link']:'', $this->cObj->data['imagecaption']);

		return $images;
	}

	/**
	 * Get images from image field and store this images to $images_arr
	 *
	 * @param array $images_arr
	 * @param string $upload_path
	 * @return	string		Content
	 */
	function getImagesStandard(&$images_arr, $upload_path='uploads/pics/'){
		$images = explode(',',$this->cObj->data['image']);
		foreach($images as $file) {
			if (strlen(trim($file)) > 0) {
				$images_arr[] = $this->siteUrl.$upload_path.$file;
			}
		}

	}

	/**
	 * Get images from DAM and store this images to $images_arr
	 * TODO: rewrite sql?
	 * @param array $images_arr
	 * @return	string		Content
	 */
	function getImagesFromDam(&$images_arr){
		$sql = 'SELECT tx_dam.* FROM tx_dam_mm_ref,tx_dam WHERE tx_dam_mm_ref.tablenames="tt_content" AND tx_dam_mm_ref.ident="tx_damttcontent_files" AND tx_dam_mm_ref.uid_foreign="'.$this->cObj->data['uid'].'" AND tx_dam_mm_ref.uid_local=tx_dam.uid AND tx_dam.deleted=0 ORDER BY sorting_foreign';
		$res = mysql_query($sql);
		if(mysql_num_rows($res)>0){
			while($row = mysql_fetch_assoc($res)){
				$images_arr[] = $this->siteUrl.$row['file_path'].$row['file_name'];
			}
		}
	}

	/**
	 * Parsing the bodytext field content, removing typical entities and <br /> tags.
	 *
	 * @param	string		$str: Field content from "bodytext" or other text field
	 * @param	string		$altConf: Altername conf name (especially when bodyext field in other table then tt_content)
	 * @return	string		Processed content
	 */
	function parseBody($str, $altConf='bodytext') {
		if ($this->conf[$altConf.'.']['doubleLF']) {
			$str = preg_replace("/\n/", "\n\n", $str);
		}
			// Regular parsing:
		$str = preg_replace('/<br\s*\/?>/i', chr(10), $str);
		$str = $this->cObj->stdWrap($str,$this->conf[$altConf.'.']['stdWrap.']);

			// Then all a-tags:
		$aConf = array();
		$aConf['parseFunc.']['tags.']['a'] = 'USER';
		$aConf['parseFunc.']['tags.']['a.']['userFunc'] = 'tx_directmail_pi1->atag_to_http';
		$aConf['parseFunc.']['tags.']['a.']['siteUrl'] = $this->siteUrl;
		$str = $this->cObj->stdWrap($str,$aConf);
		$str = str_replace('&nbsp;',' ',t3lib_div::htmlspecialchars_decode($str));

		if ($this->conf[$altConf.'.']['header']) {
			$str = $this->getString($this->conf[$altConf.'.']['header']).chr(10).$str;
		}

		return chr(10).$str;
	}

	/**
	 * Creates a list of links to uploaded files.
	 *
	 * @param	string		$str: List of uploaded filenames from "uploads/media/" (or $upload_path)
	 * @param	string		$upload_path: Alternative path value
	 * @return	string		Content
	 */
	function renderUploads($str,$upload_path='uploads/media/')	{
		$files = explode(',',$str);
		$lines = array();

		if (count($files) > 0 && strlen($files[0])) {
			if ($this->conf['uploads.']['header']) {
				$lines[] = $this->getString($this->conf['uploads.']['header']);
			}
			foreach($files as $file) {
				$lines[] = $this->siteUrl.$upload_path.$file;
			}
 		}
		return chr(10).implode(chr(10),$lines);
	}

	/**
	 * Renders a content element header, observing the layout type giving different header formattings
	 *
	 * @param	string		$str: The header string
	 * @param	integer		$type: The layout type of the header (in the content element)
	 * @return	string		Content
	 */
	function renderHeader($str,$type=0)	{
		if ($str) {
			$hConf = $this->conf['header.'];
			$defaultType = tx_directmail_static::intInRangeWrapper($hConf['defaultType'],1,5);
			$type = tx_directmail_static::intInRangeWrapper($type,0,6);
			if (!$type) {
				$type = $defaultType;
			}
			if ($type != 6)	{
				// not hidden
				$tConf = $hConf[$type.'.'];

				if ($tConf['removeSplitChar']) {
					$str = preg_replace('/'.preg_quote($tConf['removeSplitChar'],'/').'/', '', $str);
				}

				$lines = array();

				$blanks = tx_directmail_static::intInRangeWrapper($tConf['preBlanks'],0,1000);
				if ($blanks) {
					$lines[] = str_pad('', $blanks-1, chr(10));
				}

				$lines = $this->pad($lines,$tConf['preLineChar'],$tConf['preLineLen']);

				$blanks = tx_directmail_static::intInRangeWrapper($tConf['preLineBlanks'],0,1000);
				if ($blanks) {
					$lines[] = str_pad('', $blanks-1, chr(10));
				}

				if ($this->cObj->data['date']) {
					$lines[] = $this->getString($hConf['datePrefix']).date($hConf['date']?$hConf['date']:'d-m-Y',$this->cObj->data['date']);
				}

				$prefix = '';
				$str = $this->getString($tConf['prefix']).$str;
				if ($tConf['autonumber']) {
					$str = $this->cObj->parentRecordNumber.$str;
				}
				if ($this->cObj->data['header_position']=='right') {
					$prefix = str_pad(' ',($this->charWidth-strlen($str)));
				}
				if ($this->cObj->data['header_position']=='center') {
					$prefix = str_pad(' ',floor(($this->charWidth-strlen($str))/2));
				}
				$lines[] = $this->cObj->stdWrap($prefix.$str,$tConf['stdWrap.']);

				if ($this->cObj->data['header_link']) {
					$lines[] = $this->getString($hConf['linkPrefix']).$this->getLink($this->cObj->data['header_link']);
				}

				$blanks = tx_directmail_static::intInRangeWrapper($tConf['postLineBlanks'],0,1000);
				if ($blanks) {
					$lines[] = str_pad('', $blanks-1, chr(10));
				}

				$lines = $this->pad($lines,$tConf['postLineChar'],$tConf['postLineLen']);

				$blanks = tx_directmail_static::intInRangeWrapper($tConf['postBlanks'],0,1000);
				if ($blanks) {
					$lines[] = str_pad('', $blanks-1, chr(10));
				}
				return implode(chr(10),$lines);
			}
		}

		return "";
	}

	/**
	 * Function used to repeat a char pattern in head lines (like if you want "********" above/below a header)
	 *
	 * @param	array		$lines: Array of existing lines to which the new char-pattern should be added
	 * @param	string		$preLineChar: The character pattern to repeat. Default is "-"
	 * @param	integer		$len: The length of the line. $preLineChar will be repeated to fill in this length.
	 * @return	array		The input array with a new line added.
	 * @see renderHeader()
	 */
	function pad($lines,$preLineChar,$len)	{
		$strPad = tx_directmail_static::intInRangeWrapper($len,0,1000);
		$strPadChar = $preLineChar?$preLineChar:'-';
		if ($strPad) {
			$lines[] = str_pad('', $strPad, $strPadChar);
		}
		return $lines;
	}

	/**
	 * Function used to wrap the bodytext field content (or image caption) into lines of a max length of
	 *
	 * @param	string		$str: The content to break
	 * @return	string		Processed value.
	 * @see main_plaintext(), breakLines()
	 */
	function breakContent($str)	{
		$cParts = explode(chr(10),$str);
		$lines = array();
		foreach($cParts as $substrs) {
			$lines[] = $this->breakLines($substrs,"");
		}
		return implode(chr(10),$lines);
	}

	/**
	 * Breaks content lines into a bullet list
	 *
	 * @param	string		$str: Content string to make into a bullet list
	 * @return	string		Processed value
	 */
	function breakBulletlist($str)	{
		$type = $this->cObj->data['layout'];
		$type = tx_directmail_static::intInRangeWrapper($type,0,3);

		$tConf = $this->conf['bulletlist.'][$type.'.'];

		$cParts = explode(chr(10),$str);
		$lines = array();
		$c = 0;

		foreach ($cParts as $substrs) {
			if (!strlen($substrs)) {
				continue;
			}
			$c++;
			$bullet = $tConf['bullet'] ? $this->getString($tConf['bullet']) : ' - ';
			$bLen = strlen($bullet);
			$bullet = substr(str_replace('#',$c,$bullet),0,$bLen);
			$secondRow = substr($tConf['secondRow']?$this->getString($tConf['secondRow']):str_pad('',strlen($bullet),' '),0,$bLen);

			$lines[] = $bullet.$this->breakLines($substrs,chr(10).$secondRow,$this->charWidth-$bLen);

			$blanks = tx_directmail_static::intInRangeWrapper($tConf['blanks'],0,1000);
			if ($blanks) {
				$lines[] = str_pad('', $blanks-1, chr(10));
			}
		}
		return implode(chr(10),$lines);
	}

	/**
	 * Formatting a table in plain text (based on the paradigm of lines being content rows and cells separated by "|")
	 *
	 * @param	string		$str: Content string
	 * @return	string		Processed value
	 */
	function breakTable($str) {
		$cParts = explode(chr(10),$str);

		$lines = array();
		$cols = intval($this->conf['cols']) ? intval($this->conf['cols']) : 0 ;
		$c = 0;
		foreach ($cParts as $substrs) {
			$c++;
			if (trim($substrs))	{
				$lineParts = explode('|',$substrs);
				if (!$cols) {
					$cols = count($lineParts);
				}

				for ($a=0;$a<$cols;$a++) {
					$jdu = explode(chr(10),$this->breakLines($lineParts[$a],chr(10),ceil($this->charWidth/$cols)));
					$lines[$c][$a] = $jdu;
				}
			}
		}
		$messure = $this->traverseTable($lines);

		$divChar = '-';
		$joinChar = '+';
		$colChar = '|';

		// Make table:
		$outLines = array();
		$outLines[] = $this->addDiv($messure,'',$divChar,$joinChar,$cols);

		foreach ($lines as $k => $v) {
			$top = intval($messure[1][$k]);
			for ($aa = 0; $aa < $top; $aa++) {
				$tempArr = array();
				for ($bb = 0; $bb < $cols; $bb++)	{
					$tempArr[$bb] = str_pad($v[$bb][$aa],$messure[0][$bb],' ');
				}
				$outLines[] = $colChar.implode($colChar,$tempArr).$colChar;
			}
			$outLines[] = $this->addDiv($messure,'',$divChar,$joinChar,$cols);
		}
		return implode(chr(10),$outLines);
	}

	/**
	 * Subfunction for breakTable(): Adds a divider line between table rows.
	 *
	 * @param	array		$messure: Some information about sizes
	 * @param	string		$content: Empty string.
	 * @param	string		$divChar: Character to use for the divider line, typically "-"
	 * @param	string		$joinChar: Join character, typically "+"
	 * @param	integer		$cols: Number of table columns
	 * @return	string		Divider line for the table
	 * @access private
	 * @see breakTable()
	 */
	function addDiv($messure,$content,$divChar,$joinChar,$cols) {
		$tempArr = array();
		for ($a = 0; $a < $cols; $a++)	{
			$tempArr[$a] = str_pad($content,$messure[0][$a],$divChar);
		}
		return $joinChar.implode($joinChar,$tempArr).$joinChar;
	}

	/**
	 * Traverses the table lines/cells and creates arrays with statistics for line numbers and lengths
	 *
	 * @param	array		$tableLines: Array with [table rows] [table cells] [lines in cell]
	 * @return	array		Statistics (max lines/lengths)
	 * @access private
	 * @see breakTable()
	 */
	function traverseTable($tableLines) {
		$maxLen = array();
		$maxLines = array();

		foreach($tableLines as $k => $v){
			foreach($v as $kk => $vv) {
				foreach($vv as $lv) {
					if (strlen($lv) > intval($maxLen[$kk])) {
						$maxLen[$kk] = strlen($lv);
					}
				}
				if (count($vv) > intval($maxLines[$k])) {
					$maxLines[$k] = count($vv);
				}
			}
		}
		return array($maxLen,$maxLines);
	}

	/**
	 * Render block of images - which means creating lines with links to the images.
	 *
	 * @param	 array		$images_arr: the image array
	 * @param	string		$links: Link value from the "image_link" field in tt_content records
	 * @param	string		$caption: Caption text
	 * @return	string		Content
	 * @see getImages()
	 */
	function renderImages($images_arr, $links, $caption) {
		$linksArr = explode(',',$links);
		$lines = array();
		$imageExists = FALSE;

		foreach($images_arr as $k => $file){
			if (strlen(trim($file)) > 0) {
				$lines[] = $file;
				if ($links && count($linksArr) > 1) {
					if (isset($linksArr[$k])) {
						$ll = $linksArr[$k];
					} else {
						$ll = $linksArr[0];
					}

					$theLink = $this->getLink($ll);
					if ($theLink) {
						$lines[] = $this->getString($this->conf['images.']['linkPrefix']).$theLink;
					}
				}
				$imageExists = TRUE;
			}
		}
		if ($this->conf['images.']['header'] && $imageExists) {
			array_unshift($lines, $this->getString($this->conf['images.']['header']));
		}
		if ($links && count($linksArr) == 1) {
			$theLink = $this->getLink($links);
			if ($theLink) {
				$lines[] = $this->getString($this->conf['images.']['linkPrefix']).$theLink;
			}
		}
		if ($caption) {
			$lines[] = '';
			$cHeader = trim($this->getString($this->conf['images.']['captionHeader']));
			if ($cHeader) {
				$lines[] = $cHeader;
			}
			$lines[] = $this->breakContent($caption);
		}

		return chr(10).implode(chr(10),$lines);
	}

	/**
	 * Returns a typolink URL based on input.
	 *
	 * @param	string		$ll: Parameter to typolink
	 * @return	string		The URL returned from $this->cObj->getTypoLink_URL(); - possibly it prefixed with the URL of the site if not present already
	 */
	function getLink($ll) {
		$theLink = $this->cObj->getTypoLink_URL($ll);
		if (substr($theLink,0,4) != 'http') {
			$theLink = $this->siteUrl.$theLink;
		}
		return $theLink;
	}

	/**
	 * Breaking lines into fixed length lines, using t3lib_div::breakLinesForEmail()
	 *
	 * @param	string		$str: The string to break
	 * @param	string		$implChar: Line break character
	 * @param	integer		$charWidth: Length of lines, default is $this->charWidth
	 * @return	string		Processed string
	 * @see t3lib_div::breakLinesForEmail()
	 */
	function breakLines($str, $implChar, $charWidth=0) {
		$cW = $charWidth ? $charWidth : $this->charWidth;

		$linebreak = $implChar ? $implChar : $this->linebreak;

		if (t3lib_div::compat_version('4.6')) {
			return t3lib_utility_Mail::breakLinesForEmail($str, $linebreak, $cW);
		} else {
			return t3lib_div::breakLinesForEmail($str, $linebreak, $cW);
		}
	}

	/**
	 * Explodes a string with "|" and if the second part is found it will return this, otherwise the first part.
	 * Used for many TypoScript properties used in this class since they need preceeding whitespace to be preserved.
	 *
	 * @param	string		$str: Input string
	 * @return	string		Output string
	 * @access private
	 */
	function getString($str) {
		$parts = explode('|',$str);
		return strcmp($parts[1],'')?$parts[1]:$parts[0];
	}

	/**
	 * Calls a user function for processing of data
	 *
	 * @param	string		$mConfKey: TypoScript property name, pointing to the definition of the user function to call (from the TypoScript array internally in this class). This array is passed to the user function. Notice that "parentObj" property is a reference to this class ($this)
	 * @param	mixed		$passVar: Variable to process
	 * @return	mixed		The processed $passVar as returned by the function call
	 */
	function userProcess($mConfKey,$passVar) {
		if ($this->conf[$mConfKey]) {
			$funcConf = $this->conf[$mConfKey.'.'];
			$funcConf['parentObj']=&$this;
			$passVar = $GLOBALS['TSFE']->cObj->callUserFunction($this->conf[$mConfKey], $funcConf, $passVar);
		}
		return $passVar;
	}

	/**
	 * Function used by TypoScript "parseFunc" to process links in the bodytext.
	 * Extracts the link and shows it in plain text in a parathesis next to the link text. If link was relative the site URL was prepended.
	 *
	 * @param	string		$content: Empty, ignore.
	 * @param	array		$conf: TypoScript parameters
	 * @return	string		Processed output.
	 * @see parseBody()
	 */
	function atag_to_http($content,$conf) {
		$this->conf = $conf;
		$this->siteUrl = $conf['siteUrl'];
		$theLink = trim($this->cObj->parameters['href']);
		if (strtolower(substr($theLink,0,7)) == 'mailto:') {
			$theLink = substr($theLink,7);
		} elseif (substr($theLink,0,4) != 'http') {
			$theLink = $this->siteUrl.$theLink;
		}
		return $this->cObj->getCurrentVal().' (###LINK_PREFIX### '.$theLink.' )';
	}

	/**
	 * User function (called from TypoScript) for generating a bullet list (used in parsefunc)
	 *
	 * @param	string		$content: Empty, ignore.
	 * @param	array		$conf: TypoScript parameters
	 * @return	string		Processed output.
	 */
	function typolist($content,$conf) {
		$this->conf = $this->cObj->mergeTSRef($conf,'bulletlist');
		$this->siteUrl = $conf['siteUrl'];
		$str = trim($this->cObj->getCurrentVal());
		$this->cObj->data['layout'] = $this->cObj->parameters['type'];
		return $this->breakBulletlist($str);
	}

	/**
	 * User function (called from TypoScript) for generating a typo header tag (used in parsefunc)
	 *
	 * @param	string		$content: Empty, ignore.
	 * @param	array		$conf: TypoScript parameters
	 * @return	string		Processed output.
	 */
	function typohead($content,$conf) {
		$this->conf = $this->cObj->mergeTSRef($conf,'header');

		$this->siteUrl = $conf['siteUrl'];
		$str = trim($this->cObj->getCurrentVal());
		$this->cObj->data['header_layout'] = $this->cObj->parameters['type'];
		$this->cObj->data['header_position'] = $this->cObj->parameters['align'];
		$this->cObj->data['header'] = $str;

		return $this->getHeader();
	}

	/**
	 * User function (called from TypoScript) for generating a code listing (used in parsefunc)
	 *
	 * @param	string		$content: Empty, ignore.
	 * @param	array		$conf: TypoScript parameters
	 * @return	string		Processed output.
	 */
	function typocode($content,$conf) {
			// Nothing is really done here...
		$this->conf = $conf;
		$this->siteUrl = $conf['siteUrl'];
		return $this->cObj->getCurrentVal();
	}

	/**
	 * Adds language-dependent label markers
	 *
	 * @param	array		$markerArray: the input marker array
	 * @return	array		the output marker array
	 */
	function addLabelsMarkers($markerArray) {

		$labels = t3lib_div::trimExplode(',', $this->labelsList);
		foreach($labels as $labelName) {
			$markerArray['###'.strtoupper($labelName).'###'] = $this->pi_getLL($labelName);
		}
		return $markerArray;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/pi1/class.tx_directmail_pi1.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/pi1/class.tx_directmail_pi1.php']);
}
?>