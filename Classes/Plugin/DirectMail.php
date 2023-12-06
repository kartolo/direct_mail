<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Plugin;

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
 *
 *
 * Generating plain text rendering of content elements for inclusion as plain text content in Direct Mails
 * That means text-only output. No HTML at all.
 * To use and configure this plugin, you may include static template "Direct Mail Plain Text".
 * If you do so, the plain text output will appear with type=99.
 */

/**
 * @author  Kasper Skårhøj <kasperYYYY@typo3.com>
 * @author  Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author  Ivan Kartolo <ivan.kartolo@gmail.com>
 */

use DirectMailTeam\DirectMail\DirectMailUtility;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\MailUtility;
use TYPO3\CMS\Frontend\DataProcessing\FilesProcessor;
use Doctrine\DBAL\Result;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryHelper;
use TYPO3\CMS\Core\Database\Query\Restriction\FrontendRestrictionContainer;
use TYPO3\CMS\Core\Domain\Repository\PageRepository;
use TYPO3\CMS\Core\Localization\Locales;
use TYPO3\CMS\Core\Localization\LocalizationFactory;
use TYPO3\CMS\Core\Page\DefaultJavaScriptAssetTrait;
use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;
use TYPO3\CMS\Core\TypoScript\TypoScriptService;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\HttpUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * https://docs.typo3.org/m/typo3/reference-coreapi/12.4/en-us/ExtensionArchitecture/HowTo/FrontendPlugin/AbstractPlugin.html
 * https://api.typo3.org/main/_abstract_plugin_8php_source.html
 */
class DirectMail
{
    use DefaultJavaScriptAssetTrait;

    protected ?ContentObjectRenderer $cObj = null;

    //public $prefixId;
    public $prefixId = 'tx_directmail_pi1';

    //public $scriptRelPath;
    public $scriptRelPath = 'pi1/class.tx_directmail_pi1.php';

    //public $extKey;
    public $extKey = 'direct_mail';

    public $piVars = [
         'pointer' => '',
         // Used as a pointer for lists
         'mode' => '',
         // List mode
         'sword' => '',
         // Search word
         'sort' => '',
     ];

    public $internal = [
        'res_count' => 0,
        'results_at_a_time' => 20,
        'maxPages' => 10,
        'currentRow' => [],
        'currentTable' => ''
    ];
    public $LOCAL_LANG = [];
    protected $LOCAL_LANG_UNSET = [];
    public $LOCAL_LANG_loaded = false;
    public $LLkey = 'default';
    public $altLLkey = '';
    public $LLtestPrefix = '';
    public $LLtestPrefixAlt = '';
    public $pi_isOnlyFields = 'mode,pointer';
    public $pi_alwaysPrev = 0;
    public $pi_lowerThan = 5;
    public $pi_moreParams = '';
    public $pi_listFields = '*';
    public $pi_autoCacheFields = [];
    public $pi_autoCacheEn = false;
    public $conf = [];
    public $pi_tmpPageId = 0;
    protected $frontendController;
    protected $templateService;


    public $charWidth = 76;
    public $linebreak = LF;
    public $siteUrl;
    public $labelsList = 'header_date_prefix,header_link_prefix,uploads_header,media_header,images_header,image_link_prefix,caption_header,unrendered_content,link_prefix';

    public function __construct($_ = null, TypoScriptFrontendController $frontendController = null)
    {
        $this->frontendController = $frontendController ?: $GLOBALS['TSFE'];
        $this->templateService = GeneralUtility::makeInstance(MarkerBasedTemplateService::class);
        // Setting piVars:
        if ($this->prefixId) {
            $this->piVars = self::getRequestPostOverGetParameterWithPrefix($this->prefixId);
        }
        $this->LLkey = $this->frontendController->getLanguage()->getTypo3Language();

        $locales = GeneralUtility::makeInstance(Locales::class);
        if ($locales->isValidLanguageKey($this->LLkey)) {
            $alternativeLanguageKeys = $locales->getLocaleDependencies($this->LLkey);
            $alternativeLanguageKeys = array_reverse($alternativeLanguageKeys);
            $this->altLLkey = implode(',', $alternativeLanguageKeys);
        }
    }

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
    public function main(string $content, array $conf): string
    {
        global $TYPO3_CONF_VARS;

        $this->__construct();

        $this->conf = $conf;
        $this->pi_loadLL('EXT:direct_mail/Resources/Private/Language/Plaintext/locallang.xlf');
        $this->siteUrl = $this->conf['siteUrl'];

        // Default linebreak;
        if ($this->conf['flowedFormat']) {
            $this->linebreak = chr(32) . LF;
        }

        $lines = [];
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
            case 'textmedia':
                if ($cType === 'textmedia') {
                    $field = 'assets';
                } else {
                    $field = 'image';
                }
                $lines[] = $this->getHeader();
                $list = 'textpic,textmedia';

                if (GeneralUtility::inList($list, $cType) && !($this->cObj->data['imageorient']&24)) {
                    $lines[] = $this->getImages($field);
                    $lines[] = '';
                }
                $lines[] = $this->breakContent(strip_tags($this->parseBody($this->cObj->data['bodytext'])));
                if (GeneralUtility::inList($list, $cType) && ($this->cObj->data['imageorient']&24)) {
                    $lines[] = '';
                    $lines[] = $this->getImages($field);
                }
                break;
            case 'image':
                $lines[] = $this->getHeader();
                $lines[] = $this->getImages('image');
                break;
            case 'uploads':
                $lines[] = $this->getHeader();
                $lines[] = $this->renderUploads($this->cObj->data['media']);
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
            case (bool)preg_match('/menu_.*/', $cType):
                $lines[] = $this->getHeader();
                $lines[] = $this->getMenuContent($cType);
                break;
            default:
                // Hook for processing other content types
                if (is_array($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['renderCType'])) {
                    foreach ($TYPO3_CONF_VARS['EXTCONF']['direct_mail']['renderCType'] as $classRef) {
                        $procObj = GeneralUtility::makeInstance($classRef);
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
        $markerArray = [];
        $markerArray = $this->addLabelsMarkers($markerArray);
        $this->templateService = GeneralUtility::makeInstance(MarkerBasedTemplateService::class);
        $content = $this->templateService->substituteMarkerArray($content, $markerArray);

        // User processing:
        $content = $this->userProcess('userProc', $content);
        return $content;
    }

    public function pi_loadLL(string $languageFilePath = '')
    {
        if ($this->LOCAL_LANG_loaded) {
            return;
        }

        if ($languageFilePath === '' && $this->scriptRelPath) {
            $languageFilePath = 'EXT:' . $this->extKey . '/' . PathUtility::dirname($this->scriptRelPath) . '/locallang.xlf';
        }
        if ($languageFilePath !== '') {
            $languageFactory = GeneralUtility::makeInstance(LocalizationFactory::class);
            $this->LOCAL_LANG = $languageFactory->getParsedData($languageFilePath, $this->LLkey);
            $alternativeLanguageKeys = GeneralUtility::trimExplode(',', $this->altLLkey, true);
            foreach ($alternativeLanguageKeys as $languageKey) {
                $tempLL = $languageFactory->getParsedData($languageFilePath, $languageKey);
                if ($this->LLkey !== 'default' && isset($tempLL[$languageKey])) {
                    $this->LOCAL_LANG[$languageKey] = $tempLL[$languageKey];
                }
            }
            // Overlaying labels from TypoScript (including fictitious language keys for non-system languages!):
            if (isset($this->conf['_LOCAL_LANG.'])) {
                // Clear the "unset memory"
                $this->LOCAL_LANG_UNSET = [];
                foreach ($this->conf['_LOCAL_LANG.'] as $languageKey => $languageArray) {
                    // Remove the dot after the language key
                    $languageKey = substr($languageKey, 0, -1);
                    // Don't process label if the language is not loaded
                    if (is_array($languageArray) && isset($this->LOCAL_LANG[$languageKey])) {
                        foreach ($languageArray as $labelKey => $labelValue) {
                            if (!is_array($labelValue)) {
                                $this->LOCAL_LANG[$languageKey][$labelKey][0]['target'] = $labelValue;
                                if ($labelValue === '') {
                                    $this->LOCAL_LANG_UNSET[$languageKey][$labelKey] = '';
                                }
                            }
                        }
                    }
                }
            }
        }
        $this->LOCAL_LANG_loaded = true;
    }

    /**
     * Creates a menu/sitemap
     *
     * @param string $cType: menu type
     * @return	string		$str: Content
     */
    public function getMenuContent(string $cType): string
    {
        $str = $this->cObj->cObjGetSingle(
            $GLOBALS['TSFE']->tmpl->setup['tt_content.'][$cType],
            $GLOBALS['TSFE']->tmpl->setup['tt_content.'][$cType . '.']
        );

        return $str;
    }

    /**
     * Creates a shortcut ("Insert Records")
     *
     * @return	string		Plain Content without HTML comments
     */
    public function getShortcut(): string
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
    public function getHtml($str = []): string
    {
        return $this->breakContent(
            strip_tags(
                preg_replace(
                    '/<br\s*\/?>/i',
                    LF,
                    $this->parseBody(is_string($str) ? $str: $this->cObj->data['bodytext'])
                )
            )
        );
    }

    /**
     * Creates a header (used for most elements)
     *
     * @return	string		Content
     * @see renderHeader()
     */
    public function getHeader(): string
    {
        // links...
        return $this->renderHeader($this->cObj->data['header'], $this->cObj->data['header_layout']);
    }

    /**
     * Get images found in the "image" field of "tt_content"
     *
     * @param   string  fieldname
     * @return  string  Content
     */
    public function getImages(string $fieldname): string
    {
        $configuration = [
            '10' => 'TYPO3\CMS\Frontend\DataProcessing\FilesProcessor',
            '10.' => [
                'references.' => [
                    'fieldName' => $fieldname,
                ],
                'folders.' => [
                    'field' => 'file_folder',
                ],
                'sorting.' => [
                    'field' => 'filelink_sorting',
                ],
            ],
        ];

        $images = GeneralUtility::makeInstance(FilesProcessor::class)->process(
            $this->cObj,
            $configuration,
            $configuration['10.'],
            []
        );

        if (is_array($images['files']) && count($images['files'])) {
            foreach ($images['files'] as $image) {
                /** @var FileReference $image */
                $imagesArray[] = [
                    'image' => $this->getLink($image->getPublicUrl()),
                    'link' => $this->getLink($image->getLink()),
                    'caption' => $image->getDescription(),
                ];
            }

            $images = $this->renderImages($imagesArray, $fieldname);
        } else {
            $images = '';
        }

        return $images;
    }

    /**
     * Parsing the bodytext field content, removing typical entities and <br /> tags.
     *
     * @param	string		$str Field content from "bodytext" or other text field
     * @param	string		$altConf Altername conf name (especially when bodyext field in other table then tt_content)
     *
     * @return	string		Processed content
     */
    public function parseBody(string $str, string $altConf = 'bodytext'): string
    {
        if ($this->conf[$altConf . '.']['doubleLF']) {
            $str = preg_replace("/\n/", "\n\n", $str);
        }
        // Regular parsing:
        $str = preg_replace('/<br\s*\/?>/i', LF, $str);
        $str = $this->cObj->stdWrap($str, $this->conf[$altConf . '.']['stdWrap.']);

        // Then all a-tags:
        $aConf = [];
        $aConf['parseFunc.']['tags.']['a'] = 'USER';
        $aConf['parseFunc.']['tags.']['a.']['userFunc'] = 'DirectMailTeam\DirectMail\Plugin\DirectMail->atagToHttp';
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
    public function renderUploads(string $str, string $uploadPath = 'uploads/media/'): string
    {
        $files = explode(',', $str);
        $lines = [];

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
     * @param	int		$type The layout type of the header (in the content element)
     *
     * @return	string		Content
     */
    public function renderHeader(string $str, int $type = 0): string
    {
        if ($str) {
            $hConf = $this->conf['header.'];
            $defaultType = DirectMailUtility::intInRangeWrapper((int)$hConf['defaultType'], 1, 5);
            $type = DirectMailUtility::intInRangeWrapper((int)$type, 0, 6);
            if (!$type) {
                $type = $defaultType;
            }
            if ($type != 6) {
                // not hidden
                $tConf = $hConf[$type . '.'];

                if ($tConf['removeSplitChar']) {
                    $str = preg_replace('/' . preg_quote($tConf['removeSplitChar'], '/') . '/', '', $str);
                }

                $lines = [];

                $blanks = DirectMailUtility::intInRangeWrapper((int)$tConf['preBlanks'], 0, 1000);
                if ($blanks) {
                    $lines[] = str_pad('', $blanks-1, LF);
                }

                $lines = $this->pad($lines, $tConf['preLineChar'], $tConf['preLineLen']);

                $blanks = DirectMailUtility::intInRangeWrapper((int)$tConf['preLineBlanks'], 0, 1000);
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
                if ($this->cObj->data['header_position'] === 'right') {
                    $prefix = str_pad(' ', ($this->charWidth - strlen($str)));
                }
                if ($this->cObj->data['header_position'] === 'center') {
                    $prefix = str_pad(' ', floor(($this->charWidth-strlen($str))/2));
                }
                $lines[] = $this->cObj->stdWrap($prefix . $str, $tConf['stdWrap.']);

                if ($this->cObj->data['header_link']) {
                    $lines[] = $this->getString($hConf['linkPrefix']) . $this->getLink($this->cObj->data['header_link']);
                }

                $blanks = DirectMailUtility::intInRangeWrapper((int)$tConf['postLineBlanks'], 0, 1000);
                if ($blanks) {
                    $lines[] = str_pad('', $blanks-1, LF);
                }

                $lines = $this->pad($lines, $tConf['postLineChar'], $tConf['postLineLen']);

                $blanks = DirectMailUtility::intInRangeWrapper((int)$tConf['postBlanks'], 0, 1000);
                if ($blanks) {
                    $lines[] = str_pad('', $blanks-1, LF);
                }
                return implode(LF, $lines);
            }
        }

        return '';
    }

    /**
     * Function used to repeat a char pattern in head lines (like if you want "********" above/below a header)
     *
     * @param	array		$lines Array of existing lines to which the new char-pattern should be added
     * @param	string		$preLineChar The character pattern to repeat. Default is "-"
     * @param	int		$len The length of the line. $preLineChar will be repeated to fill in this length.
     *
     * @return	array		The input array with a new line added.
     * @see renderHeader()
     */
    public function pad(array $lines, string $preLineChar, int $len): array
    {
        $strPad = DirectMailUtility::intInRangeWrapper((int)$len, 0, 1000);
        $strPadChar = $preLineChar ?: '-';
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
    public function breakContent(string $str): string
    {
        $cParts = explode(LF, $str);
        $lines = [];
        foreach ($cParts as $substrs) {
            $lines[] = $this->breakLines($substrs, '');
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
    public function breakBulletlist(string $str): string
    {
        $type = $this->cObj->data['layout'];
        $type = DirectMailUtility::intInRangeWrapper((int)$type, 0, 3);

        $tConf = $this->conf['bulletlist.'][$type . '.'];

        $cParts = explode(LF, $str);
        $lines = [];
        $c = 0;

        foreach ($cParts as $substrs) {
            if ($substrs === '') {
                continue;
            }
            $c++;
            $bullet = $tConf['bullet'] ? $this->getString($tConf['bullet']) : ' - ';
            $bLen = strlen($bullet);
            $bullet = substr(str_replace('#', $c, $bullet), 0, $bLen);
            $secondRow = substr($tConf['secondRow']?$this->getString($tConf['secondRow']):str_pad('', strlen($bullet), ' '), 0, $bLen);

            $lines[] = $bullet . $this->breakLines($substrs, LF . $secondRow, $this->charWidth-$bLen);

            $blanks = DirectMailUtility::intInRangeWrapper((int)$tConf['blanks'], 0, 1000);
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
    public function breakTable(string $str): string
    {
        $cParts = explode(LF, $str);

        $lines = [];
        $cols = (int)$this->conf['cols'] ?: 0;
        $c = 0;
        foreach ($cParts as $substrs) {
            $c++;
            if (trim($substrs)) {
                $lineParts = explode('|', $substrs);
                if (!$cols) {
                    $cols = count($lineParts);
                }

                for ($a = 0; $a < $cols; $a++) {
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
        $outLines = [];
        $outLines[] = $this->addDiv($messure, '', $divChar, $joinChar, $cols);

        foreach ($lines as $k => $v) {
            $top = (int)$messure[1][$k];
            for ($aa = 0; $aa < $top; $aa++) {
                $tempArr = [];
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
     * @see breakTable()
     */
    public function addDiv(
        array $messure,
        string $content,
        string $divChar,
        string $joinChar,
        int $cols
        ): string
    {
        $tempArr = [];
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
     * @see breakTable()
     */
    public function traverseTable(array $tableLines): array
    {
        $maxLen = [];
        $maxLines = [];

        foreach ($tableLines as $k => $v) {
            foreach ($v as $kk => $vv) {
                foreach ($vv as $lv) {
                    if (strlen($lv) > (int)$maxLen[$kk]) {
                        $maxLen[$kk] = strlen($lv);
                    }
                }
                if (count($vv) > (int)$maxLines[$k]) {
                    $maxLines[$k] = count($vv);
                }
            }
        }
        return [$maxLen, $maxLines];
    }

    /**
     * Render block of images - which means creating lines with links to the images.
     *
     * @param   array   $imagesArray The image array*
     * @param   string  $fieldname
     * @return  string  Content
     * @see getImages()
     */
    public function renderImages(array $imagesArray, string $fieldname): string
    {
        if ($fieldname === 'assets') {
            $fieldname = 'textmedia';
        }
        $lines = [];
        $imageExists = false;

        // create the image, imagelink and image caption block
        foreach ($imagesArray as $k => $image) {
            if (strlen(trim($image['image'])) > 0) {
                $lines[] = $image['image'];
                if ($image['link']) {
                    $theLink = $this->getLink($image['link']);
                    if ($theLink) {
                        $lines[] = $this->getString($this->conf[$fieldname . '.']['linkPrefix']) . $theLink;
                    }
                }
                if ($image['caption']) {
                    $cHeader = trim($this->getString($this->conf[$fieldname . '.']['captionHeader']));
                    $lines[] = $cHeader . ' ' . $this->breakContent($image['caption']);
                }
                // add newline
                $lines[] = '';
                $imageExists = true;
            }
        }
        if ($this->conf[$fieldname . '.']['header'] && $imageExists) {
            array_unshift($lines, $this->getString($this->conf[$fieldname . '.']['header']));
        }

        return LF . implode(LF, $lines);
    }

    /**
     * Returns a typolink URL based on input.
     *
     * @param	string		$link Parameter to typolink
     *
     * @return	string		The URL returned from $this->cObj->getTypoLink_URL(); - possibly it prefixed with the URL of the site if not present already
     */
    public function getLink(string $link): string
    {
        return $this->cObj->typoLink_URL([
            'parameter' => $link,
            'forceAbsoluteUrl' => '1',
            'forceAbsoluteUrl.' => [
                'scheme' => GeneralUtility::getIndpEnv('TYPO3_SSL')?'https':'http',
            ],
        ]);
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
    public function breakLines(string $str, string $implChar, int $charWidth = 0): string
    {
        $cW = $charWidth ?: $this->charWidth;
        $linebreak = $implChar ?: $this->linebreak;

        return MailUtility::breakLinesForEmail($str, $linebreak, $cW);
    }

    /**
     * Explodes a string with "|" and if the second part is found it will return this, otherwise the first part.
     * Used for many TypoScript properties used in this class since they need preceeding whitespace to be preserved.
     *
     * @param	string		$str Input string
     *
     * @return	string		Output string
     */
    public function getString(string $str): string
    {
        $parts = explode('|', $str);
        return strcmp($parts[1], '') ? $parts[1] : $parts[0];
    }

    /**
     * Calls a user function for processing of data
     *
     * @param	string		$mConfKey TypoScript property name, pointing to the definition of the user function to call (from the TypoScript array internally in this class). This array is passed to the user function. Notice that "parentObj" property is a reference to this class ($this)
     * @param	mixed		$passVar Variable to process
     *
     * @return	mixed		The processed $passVar as returned by the function call
     */
    public function userProcess(string $mConfKey, $passVar)
    {
        if ($this->conf[$mConfKey]) {
            $funcConf = $this->conf[$mConfKey . '.'];
            $funcConf['parentObj']=&$this;
            $passVar = $GLOBALS['TSFE']->cObj->callUserFunction(
                $this->conf[$mConfKey],
                $funcConf,
                $passVar
            );
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
    public function atagToHttp(string $content, array $conf): string
    {
        $this->conf = $conf;
        $this->siteUrl = $conf['siteUrl'];
        $theLink = trim($this->cObj->parameters['href']);

        $theLink = $this->getLink($theLink);

        // remove mailto if it's an email link
        if (strtolower(substr($theLink, 0, 7)) === 'mailto:') {
            $theLink = substr($theLink, 7);
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
    public function typolist(string $content, array $conf): string
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
    public function typohead(string $content, array $conf): string
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
    public function typocode(string $content, array $conf): string
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
    public function addLabelsMarkers(array $markerArray): array
    {
        $labels = GeneralUtility::trimExplode(',', $this->labelsList);
        foreach ($labels as $labelName) {
            $markerArray['###' . strtoupper($labelName) . '###'] = (string)LocalizationUtility::translate($labelName, 'direct_mail');
        }
        return $markerArray;
    }

    private static function getRequestPostOverGetParameterWithPrefix($parameter)
    {
        $postParameter = isset($_POST[$parameter]) && is_array($_POST[$parameter]) ? $_POST[$parameter] : [];
        $getParameter = isset($_GET[$parameter]) && is_array($_GET[$parameter]) ? $_GET[$parameter] : [];
        $mergedParameters = $getParameter;
        ArrayUtility::mergeRecursiveWithOverrule($mergedParameters, $postParameter);
        return $mergedParameters;
    }
}
