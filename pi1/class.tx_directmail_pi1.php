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
 */

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\MailUtility;
use DirectMailTeam\DirectMail\DirectMailUtility;
use TYPO3\CMS\Frontend\Plugin\AbstractPlugin;

/**
 * Generating plain text rendering of content elements for inclusion as plain text content in Direct Mails
 * That means text-only output. No HTML at all.
 * To use and configure this plugin, you may include static template "Direct Mail Plain Text".
 * If you do so, the plain text output will appear with type=99.
 *
 * @author Ivan Kartolo <ivan.kartolo@gmail.com>
 */
class tx_directmail_pi1 extends AbstractPlugin
{

    /**
     * ObjectRenderer
     *
     * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
     */
    public $cObj;
    public $conf = array();
    public $prefixId = 'tx_directmail_pi1';
    public $scriptRelPath = 'pi1/class.tx_directmail_pi1.php';
    public $extKey = 'direct_mail';
    public $charWidth = 76;
    public $linebreak = LF;
    public $siteUrl;
    public $labelsList = 'header_date_prefix,header_link_prefix,uploads_header,images_header,image_link_prefix,caption_header,unrendered_content,link_prefix';

    /**
     * Main function, called from TypoScript
     * A content object that renders "tt_content" records. See the comment to this class for TypoScript example of how to trigger it.
     * This detects the CType of the current content element and renders it accordingly. Only wellknown types are rendered.
     *
     * @param	string	$content Empty, ignore.
     * @param	array	$conf TypoScript properties for this content object/function call
     *
     * @return	string
     */
    public function main($content, array $conf)
    {
        global $TYPO3_CONF_VARS;

        $this->init($conf);

        $lines = array();
        $cType = (string)$this->cObj->data['CType'];
        switch ($cType) {
            case 'header':
                $lines[] = $this->getHeader();
                if ($this->cObj->data['subheader']) {
                    $lines[] = $this->breakContent(strip_tags($this->cObj->data['subheader']));
                }
                break;
            case 'text':
                // same as textpic
            case 'textpic':
                $lines[] = $this->getHeader();
                if (($cType == 'textpic') && !($this->cObj->data['imageorient']&24)) {
                    $lines[] = $this->getImages();
                    $lines[] = '';
                }
                $lines[] = $this->breakContent(strip_tags($this->parseBody($this->cObj->data['bodytext'])));
                if (($cType == 'textpic') && ($this->cObj->data['imageorient']&24)) {
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
                $lines[] = $this->getHtml();
                break;
            default:
                    // Hook for processing other content types
                if (is_array($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['renderCType'])) {
                    foreach ($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['renderCType'] as $classRef) {
                        $procObj = &GeneralUtility::getUserObj($classRef);
                        $lines = array_merge($lines, $procObj->renderPlainText($this, $content));
                    }
                }
                if (empty($lines)) {
                    $defaultOutput = $this->getString($this->conf['defaultOutput']);
                    if ($defaultOutput) {
                        $lines[] = str_replace('###CType###', $cType, $defaultOutput);
                    }
                }
        }

        // First break.
        $lines[] = '';
        $content = implode(LF, $lines);

            // Substitute labels
        $markerArray = array();
        $markerArray = $this->addLabelsMarkers($markerArray);
        $content = $this->cObj->substituteMarkerArray($content, $markerArray);

            // User processing:
        $content = $this->userProcess('userProc', $content);
        return $content;
    }

    /**
     * Initializing the parent class
     *
     * @param	array		$conf TS conf
     *
     * @return	void
     */
    public function init(array $conf)
    {
        AbstractPlugin::__construct();

        $this->conf = $conf;
        $this->pi_loadLL();
        $this->siteUrl = $this->conf['siteUrl'];

            // Default linebreak;
        if ($this->conf['flowedFormat']) {
            $this->linebreak = chr(32) . LF;
        }
    }

    /**
     * Creates a menu/sitemap
     *
     * @return	string		$str: Content
     */
    public function getMenuSitemap()
    {
        $str = $this->cObj->cObjGetSingle($this->conf['menu'], $this->conf['menu.']);
        $str = $this->breakBulletlist(trim(strip_tags(preg_replace('/<br\s*\/?>/i', LF, $this->parseBody($str)))));
        return $str;
    }

    /**
     * Creates a shortcut ("Insert Records")
     *
     * @return	string		Plain Content without HTML comments
     */
    public function getShortcut()
    {
        $str = $this->cObj->cObjGetSingle($this->conf['shortcut'], $this->conf['shortcut.']);

        // Remove html comment reporting shortcut inclusion
        return preg_replace('/<![ \r\n\t]*(--([^\-]|[\r\n]|-[^\-])*--[ \r\n\t]*)\>/', '', $str);
    }

    /**
     * Creates an HTML element (stripping tags of course)
     *
     * @param	mixed	$str HTML content (as string or in an array) to process. If not passed along, the bodytext field is used.
     *
     * @return	string		Plain content.
     */
    public function getHtml($str = array())
    {
        return $this->breakContent(strip_tags(preg_replace('/<br\s*\/?>/i', LF, $this->parseBody(is_string($str)?$str:$this->cObj->data['bodytext']))));
    }

    /**
     * Creates a header (used for most elements)
     *
     * @return	string		Content
     * @see renderHeader()
     */
    public function getHeader()
    {
        // links...
        return $this->renderHeader($this->cObj->data['header'], $this->cObj->data['header_layout']);
    }

    /**
     * Get images found in the "image" field of "tt_content"
     *
     * @return	string		Content
     */
    public function getImages()
    {
        $imagesArray = array();
        $this->getImagesStandard($imagesArray);
        if (ExtensionManagementUtility::isLoaded('dam')) {
            $this->getImagesFromDam($imagesArray);
        }

        $images = $this->renderImages($imagesArray, !$this->cObj->data['image_zoom']?$this->cObj->data['image_link']:'', $this->cObj->data['imagecaption']);

        return $images;
    }

    /**
     * Get images from image field and store this images to $images_arr
     *
     * @param array $imagesArray The image array
     * @param string $upload_path The upload path
     *
     * @return	void
     */
    public function getImagesStandard(array &$imagesArray, $uploadPath='uploads/pics/')
    {
        $images = explode(',', $this->cObj->data['image']);
        foreach ($images as $file) {
            if (strlen(trim($file)) > 0) {
                $imagesArray[] = $this->siteUrl . $uploadPath . $file;
            }
        }
    }

    /**
     * Get images from DAM and store this images to $images_arr
     * TODO: rewrite sql?
     * @param array $imagesArray
     * @return	string		Content
     */
    public function getImagesFromDam(array &$imagesArray)
    {
        $sql = 'SELECT tx_dam.* FROM tx_dam_mm_ref,tx_dam WHERE tx_dam_mm_ref.tablenames="tt_content" AND tx_dam_mm_ref.ident="tx_damttcontent_files" AND tx_dam_mm_ref.uid_foreign="' . $this->cObj->data['uid'] . '" AND tx_dam_mm_ref.uid_local=tx_dam.uid AND tx_dam.deleted=0 ORDER BY sorting_foreign';
        $res = mysql_query($sql);
        if (mysql_num_rows($res)>0) {
            while (($row = mysql_fetch_assoc($res))) {
                $imagesArray[] = $this->siteUrl . $row['file_path'] . $row['file_name'];
            }
        }
    }

    /**
     * Parsing the bodytext field content, removing typical entities and <br /> tags.
     *
     * @param	string		$str Field content from "bodytext" or other text field
     * @param	string		$altConf Altername conf name (especially when bodyext field in other table then tt_content)
     *
     * @return	string		Processed content
     */
    public function parseBody($str, $altConf='bodytext')
    {
        if ($this->conf[$altConf . '.']['doubleLF']) {
            $str = preg_replace("/\n/", "\n\n", $str);
        }
            // Regular parsing:
        $str = preg_replace('/<br\s*\/?>/i', LF, $str);
        $str = $this->cObj->stdWrap($str, $this->conf[$altConf . '.']['stdWrap.']);

            // Then all a-tags:
        $aConf = array();
        $aConf['parseFunc.']['tags.']['a'] = 'USER';
        $aConf['parseFunc.']['tags.']['a.']['userFunc'] = 'tx_directmail_pi1->atag_to_http';
        $aConf['parseFunc.']['tags.']['a.']['siteUrl'] = $this->siteUrl;
        $str = $this->cObj->stdWrap($str, $aConf);
        $str = str_replace('&nbsp;', ' ', htmlspecialchars_decode($str));

        if ($this->conf[$altConf . '.']['header']) {
            $str = $this->getString($this->conf[$altConf . '.']['header']) . LF . $str;
        }

        return LF . $str;
    }

    /**
     * Creates a list of links to uploaded files.
     *
     * @param	string		$str List of uploaded filenames from "uploads/media/" (or $upload_path)
     * @param	string		$uploadPath Alternative path value
     *
     * @return	string		Content
     */
    public function renderUploads($str, $uploadPath='uploads/media/')
    {
        $files = explode(',', $str);
        $lines = array();

        if (count($files) > 0 && strlen($files[0])) {
            if ($this->conf['uploads.']['header']) {
                $lines[] = $this->getString($this->conf['uploads.']['header']);
            }
            foreach ($files as $file) {
                $lines[] = $this->siteUrl . $uploadPath . $file;
            }
        }
        return LF . implode(LF, $lines);
    }

    /**
     * Renders a content element header, observing the layout type giving different header formattings
     *
     * @param	string		$str The header string
     * @param	integer		$type The layout type of the header (in the content element)
     *
     * @return	string		Content
     */
    public function renderHeader($str, $type = 0)
    {
        if ($str) {
            $hConf = $this->conf['header.'];
            $defaultType = DirectMailUtility::intInRangeWrapper($hConf['defaultType'], 1, 5);
            $type = DirectMailUtility::intInRangeWrapper($type, 0, 6);
            if (!$type) {
                $type = $defaultType;
            }
            if ($type != 6) {
                // not hidden
                $tConf = $hConf[$type . '.'];

                if ($tConf['removeSplitChar']) {
                    $str = preg_replace('/' . preg_quote($tConf['removeSplitChar'], '/') . '/', '', $str);
                }

                $lines = array();

                $blanks = DirectMailUtility::intInRangeWrapper($tConf['preBlanks'], 0, 1000);
                if ($blanks) {
                    $lines[] = str_pad('', $blanks-1, LF);
                }

                $lines = $this->pad($lines, $tConf['preLineChar'], $tConf['preLineLen']);

                $blanks = DirectMailUtility::intInRangeWrapper($tConf['preLineBlanks'], 0, 1000);
                if ($blanks) {
                    $lines[] = str_pad('', $blanks-1, LF);
                }

                if ($this->cObj->data['date']) {
                    $lines[] = $this->getString($hConf['datePrefix']) . date($hConf['date']?$hConf['date']:'d-m-Y', $this->cObj->data['date']);
                }

                $prefix = '';
                $str = $this->getString($tConf['prefix']) . $str;
                if ($tConf['autonumber']) {
                    $str = $this->cObj->parentRecordNumber . $str;
                }
                if ($this->cObj->data['header_position'] == 'right') {
                    $prefix = str_pad(' ', ($this->charWidth - strlen($str)));
                }
                if ($this->cObj->data['header_position'] == 'center') {
                    $prefix = str_pad(' ', floor(($this->charWidth-strlen($str))/2));
                }
                $lines[] = $this->cObj->stdWrap($prefix . $str, $tConf['stdWrap.']);

                if ($this->cObj->data['header_link']) {
                    $lines[] = $this->getString($hConf['linkPrefix']) . $this->getLink($this->cObj->data['header_link']);
                }

                $blanks = DirectMailUtility::intInRangeWrapper($tConf['postLineBlanks'], 0, 1000);
                if ($blanks) {
                    $lines[] = str_pad('', $blanks-1, LF);
                }

                $lines = $this->pad($lines, $tConf['postLineChar'], $tConf['postLineLen']);

                $blanks = DirectMailUtility::intInRangeWrapper($tConf['postBlanks'], 0, 1000);
                if ($blanks) {
                    $lines[] = str_pad('', $blanks-1, LF);
                }
                return implode(LF, $lines);
            }
        }

        return "";
    }

    /**
     * Function used to repeat a char pattern in head lines (like if you want "********" above/below a header)
     *
     * @param	array		$lines Array of existing lines to which the new char-pattern should be added
     * @param	string		$preLineChar The character pattern to repeat. Default is "-"
     * @param	integer		$len The length of the line. $preLineChar will be repeated to fill in this length.
     *
     * @return	array		The input array with a new line added.
     * @see renderHeader()
     */
    public function pad(array $lines, $preLineChar, $len)
    {
        $strPad = DirectMailUtility::intInRangeWrapper($len, 0, 1000);
        $strPadChar = $preLineChar?$preLineChar:'-';
        if ($strPad) {
            $lines[] = str_pad('', $strPad, $strPadChar);
        }
        return $lines;
    }

    /**
     * Function used to wrap the bodytext field content (or image caption) into lines of a max length of
     *
     * @param	string		$str The content to break
     *
     * @return	string		Processed value.
     * @see main_plaintext(), breakLines()
     */
    public function breakContent($str)
    {
        $cParts = explode(LF, $str);
        $lines = array();
        foreach ($cParts as $substrs) {
            $lines[] = $this->breakLines($substrs, "");
        }
        return implode(LF, $lines);
    }

    /**
     * Breaks content lines into a bullet list
     *
     * @param	string		$str Content string to make into a bullet list
     *
     * @return	string		Processed value
     */
    public function breakBulletlist($str)
    {
        $type = $this->cObj->data['layout'];
        $type = DirectMailUtility::intInRangeWrapper($type, 0, 3);

        $tConf = $this->conf['bulletlist.'][$type . '.'];

        $cParts = explode(LF, $str);
        $lines = array();
        $c = 0;

        foreach ($cParts as $substrs) {
            if (!strlen($substrs)) {
                continue;
            }
            $c++;
            $bullet = $tConf['bullet'] ? $this->getString($tConf['bullet']) : ' - ';
            $bLen = strlen($bullet);
            $bullet = substr(str_replace('#', $c, $bullet), 0, $bLen);
            $secondRow = substr($tConf['secondRow']?$this->getString($tConf['secondRow']):str_pad('', strlen($bullet), ' '), 0, $bLen);

            $lines[] = $bullet . $this->breakLines($substrs, LF . $secondRow, $this->charWidth-$bLen);

            $blanks = DirectMailUtility::intInRangeWrapper($tConf['blanks'], 0, 1000);
            if ($blanks) {
                $lines[] = str_pad('', $blanks-1, LF);
            }
        }
        return implode(LF, $lines);
    }

    /**
     * Formatting a table in plain text (based on the paradigm of lines being content rows and cells separated by "|")
     *
     * @param	string		$str Content string
     *
     * @return	string		Processed value
     */
    public function breakTable($str)
    {
        $cParts = explode(LF, $str);

        $lines = array();
        $cols = intval($this->conf['cols']) ? intval($this->conf['cols']) : 0 ;
        $c = 0;
        foreach ($cParts as $substrs) {
            $c++;
            if (trim($substrs)) {
                $lineParts = explode('|', $substrs);
                if (!$cols) {
                    $cols = count($lineParts);
                }

                for ($a=0;$a<$cols;$a++) {
                    $jdu = explode(LF, $this->breakLines($lineParts[$a], LF, ceil($this->charWidth/$cols)));
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
        $outLines[] = $this->addDiv($messure, '', $divChar, $joinChar, $cols);

        foreach ($lines as $k => $v) {
            $top = intval($messure[1][$k]);
            for ($aa = 0; $aa < $top; $aa++) {
                $tempArr = array();
                for ($bb = 0; $bb < $cols; $bb++) {
                    $tempArr[$bb] = str_pad($v[$bb][$aa], $messure[0][$bb], ' ');
                }
                $outLines[] = $colChar . implode($colChar, $tempArr) . $colChar;
            }
            $outLines[] = $this->addDiv($messure, '', $divChar, $joinChar, $cols);
        }
        return implode(LF, $outLines);
    }

    /**
     * Subfunction for breakTable(): Adds a divider line between table rows.
     *
     * @param	array		$messure Some information about sizes
     * @param	string		$content Empty string.
     * @param	string		$divChar Character to use for the divider line, typically "-"
     * @param	string		$joinChar Join character, typically "+"
     * @param	int			$cols Number of table columns
     *
     * @return	string		Divider line for the table
     * @access private
     * @see breakTable()
     */
    public function addDiv(array $messure, $content, $divChar, $joinChar, $cols)
    {
        $tempArr = array();
        for ($a = 0; $a < $cols; $a++) {
            $tempArr[$a] = str_pad($content, $messure[0][$a], $divChar);
        }
        return $joinChar . implode($joinChar, $tempArr) . $joinChar;
    }

    /**
     * Traverses the table lines/cells and creates arrays with statistics for line numbers and lengths
     *
     * @param	array		$tableLines Array with [table rows] [table cells] [lines in cell]
     *
     * @return	array		Statistics (max lines/lengths)
     * @access private
     * @see breakTable()
     */
    public function traverseTable(array $tableLines)
    {
        $maxLen = array();
        $maxLines = array();

        foreach ($tableLines as $k => $v) {
            foreach ($v as $kk => $vv) {
                foreach ($vv as $lv) {
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
     * @param	 array		$imagesArray The image array
     * @param	string		$links Link value from the "image_link" field in tt_content records
     * @param	string		$caption Caption text
     *
     * @return	string		Content
     * @see getImages()
     */
    public function renderImages(array $imagesArray, $links, $caption)
    {
        $linksArr = explode(',', $links);
        $lines = array();
        $imageExists = false;

        foreach ($imagesArray as $k => $file) {
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
                        $lines[] = $this->getString($this->conf['images.']['linkPrefix']) . $theLink;
                    }
                }
                $imageExists = true;
            }
        }
        if ($this->conf['images.']['header'] && $imageExists) {
            array_unshift($lines, $this->getString($this->conf['images.']['header']));
        }
        if ($links && count($linksArr) == 1) {
            $theLink = $this->getLink($links);
            if ($theLink) {
                $lines[] = $this->getString($this->conf['images.']['linkPrefix']) . $theLink;
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

        return LF . implode(LF, $lines);
    }

    /**
     * Returns a typolink URL based on input.
     *
     * @param	string		$ll Parameter to typolink
     *
     * @return	string		The URL returned from $this->cObj->getTypoLink_URL(); - possibly it prefixed with the URL of the site if not present already
     */
    public function getLink($ll)
    {
        $theLink = $this->cObj->getTypoLink_URL($ll);
        if (substr($theLink, 0, 4) != 'http') {
            $theLink = $this->siteUrl . $theLink;
        }
        return $theLink;
    }

    /**
     * Breaking lines into fixed length lines, using MailUtility::breakLinesForEmail()
     *
     * @param	string		$str The string to break
     * @param	string		$implChar Line break character
     * @param	int			$charWidth Length of lines, default is $this->charWidth
     *
     * @return	string		Processed string
     * @see MailUtility::breakLinesForEmail()
     */
    public function breakLines($str, $implChar, $charWidth=0)
    {
        $cW = $charWidth ? $charWidth : $this->charWidth;

        $linebreak = $implChar ? $implChar : $this->linebreak;

        return MailUtility::breakLinesForEmail($str, $linebreak, $cW);
    }

    /**
     * Explodes a string with "|" and if the second part is found it will return this, otherwise the first part.
     * Used for many TypoScript properties used in this class since they need preceeding whitespace to be preserved.
     *
     * @param	string		$str Input string
     *
     * @return	string		Output string
     * @access private
     */
    public function getString($str)
    {
        $parts = explode('|', $str);
        return strcmp($parts[1], '')?$parts[1]:$parts[0];
    }

    /**
     * Calls a user function for processing of data
     *
     * @param	string		$mConfKey TypoScript property name, pointing to the definition of the user function to call (from the TypoScript array internally in this class). This array is passed to the user function. Notice that "parentObj" property is a reference to this class ($this)
     * @param	mixed		$passVar Variable to process
     *
     * @return	mixed		The processed $passVar as returned by the function call
     */
    public function userProcess($mConfKey, $passVar)
    {
        if ($this->conf[$mConfKey]) {
            $funcConf = $this->conf[$mConfKey . '.'];
            $funcConf['parentObj']=&$this;
            $passVar = $GLOBALS['TSFE']->cObj->callUserFunction($this->conf[$mConfKey], $funcConf, $passVar);
        }
        return $passVar;
    }

    /**
     * Function used by TypoScript "parseFunc" to process links in the bodytext.
     * Extracts the link and shows it in plain text in a parathesis next to the link text. If link was relative the site URL was prepended.
     *
     * @param	string		$content Empty, ignore.
     * @param	array		$conf TypoScript parameters
     *
     * @return	string		Processed output.
     * @see parseBody()
     */
    public function atag_to_http($content, $conf)
    {
        $this->conf = $conf;
        $this->siteUrl = $conf['siteUrl'];
        $theLink = trim($this->cObj->parameters['href']);
        if (strtolower(substr($theLink, 0, 7)) == 'mailto:') {
            $theLink = substr($theLink, 7);
        } elseif (substr($theLink, 0, 4) != 'http') {
            $theLink = $this->siteUrl . $theLink;
        }
        return $this->cObj->getCurrentVal() . ' (###LINK_PREFIX### ' . $theLink . ' )';
    }

    /**
     * User function (called from TypoScript) for generating a bullet list (used in parsefunc)
     *
     * @param	string		$content Empty, ignore.
     * @param	array		$conf TypoScript parameters
     *
     * @return	string		Processed output.
     */
    public function typolist($content, $conf)
    {
        $this->conf = $this->cObj->mergeTSRef($conf, 'bulletlist');
        $this->siteUrl = $conf['siteUrl'];
        $str = trim($this->cObj->getCurrentVal());
        $this->cObj->data['layout'] = $this->cObj->parameters['type'];
        return $this->breakBulletlist($str);
    }

    /**
     * User function (called from TypoScript) for generating a typo header tag (used in parsefunc)
     *
     * @param	string		$content Empty, ignore.
     * @param	array		$conf TypoScript parameters
     *
     * @return	string		Processed output.
     */
    public function typohead($content, $conf)
    {
        $this->conf = $this->cObj->mergeTSRef($conf, 'header');

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
     * @param	string		$content Empty, ignore.
     * @param	array		$conf TypoScript parameters
     *
     * @return	string		Processed output.
     */
    public function typocode($content, $conf)
    {
        // Nothing is really done here...
        $this->conf = $conf;
        $this->siteUrl = $conf['siteUrl'];
        return $this->cObj->getCurrentVal();
    }

    /**
     * Adds language-dependent label markers
     *
     * @param	array		$markerArray the input marker array
     *
     * @return	array		the output marker array
     */
    public function addLabelsMarkers(array $markerArray)
    {
        $labels = GeneralUtility::trimExplode(',', $this->labelsList);
        foreach ($labels as $labelName) {
            $markerArray['###' . strtoupper($labelName) . '###'] = $this->pi_getLL($labelName);
        }
        return $markerArray;
    }
}
