<?php

namespace DirectMailTeam\DirectMail\Hooks;

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

use DirectMailTeam\DirectMail\DirectMailUtility;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

require_once ExtensionManagementUtility::extPath('direct_mail').'pi1/class.tx_directmail_pi1.php';

/**
 * Generating plain text content of tt_news records for Direct Mails
 * Implements hook $TYPO3_CONF_VARS['EXTCONF']['tt_news']['extraCodesHook'].
 *
 * @author		Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 */
class TtnewsPlaintextHook
{
    /**
     * @var \TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer
     */
    public $cObj;

    /**
     * ts array.
     *
     * @var array
     */
    public $conf = array();

    /**
     * @var array
     */
    public $config = array();
    public $charWidth = 76;
    /**
     * @var tx_directmail_pi1
     */
    public $renderPlainText;

    /**
     * @var int
     */
    public $tt_news_uid;

    /**
     * @var bool
     */
    public $enableFields;

    /**
     * @var string
     */
    public $sys_language_mode;

    /**
     * @var string
     */
    public $templateCode;

    /**
     * Main function, called from TypoScript
     * A content object that renders "tt_content" records. See the comment to this class for TypoScript example of how to trigger it.
     * This detects the CType of the current content element and renders it accordingly. Only wellknown types are rendered.
     *
     * @param \TYPO3\CMS\Frontend\Plugin\AbstractPlugin $invokingObj the tt_news object
     *
     * @return string Plain text content
     */
    public function extraCodesProcessor(&$invokingObj)
    {
        $content = '';
        $this->conf = $invokingObj->conf;

        if ($this->conf['code'] == 'PLAINTEXT') {
            $this->cObj = $invokingObj->cObj;
            $this->config = $invokingObj->config;
            $this->tt_news_uid = $invokingObj->tt_news_uid;
            $this->enableFields = $invokingObj->enableFields;
            $this->sys_language_mode = $invokingObj->sys_language_mode;
            $this->templateCode = $invokingObj->templateCode;

            $this->renderPlainText = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('tx_directmail_pi1');
            $this->renderPlainText->init($GLOBALS['TSFE']->tmpl->setup['plugin.']['tx_directmail_pi1.']);
            $this->renderPlainText->cObj = $this->cObj;
            $this->renderPlainText->labelsList = 'tt_news_author_prefix,tt_news_author_date_prefix,tt_news_author_email_prefix,tt_news_short_header,tt_news_bodytext_header';

            $lines = array();
            $singleWhere = 'tt_news.uid='.intval($this->tt_news_uid);
            $singleWhere .= ' AND type=0'.$this->enableFields; // type=0->only real news.
            $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
                '*',
                'tt_news',
                $singleWhere
                );
            $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
            $GLOBALS['TYPO3_DB']->sql_free_result($res);
                // get the translated record if the content language is not the default language
            if ($GLOBALS['TSFE']->sys_language_content) {
                $OLmode = ($this->sys_language_mode == 'strict' ? 'hideNonTranslated' : '');
                $row = $GLOBALS['TSFE']->sys_page->getRecordOverlay('tt_news', $row, $GLOBALS['TSFE']->sys_language_content, $OLmode);
            }
            if (is_array($row)) {
                // Render the title
                $lines[] = $this->renderPlainText->renderHeader($row['title']);

                    // Render author of the tt_news record
                $lines[] = $this->renderAuthor($row);

                    // Render the short version of the tt_news record
                $lines[] = $this->renderPlainText->breakContent(strip_tags($this->renderPlainText->parseBody($row['short'], 'tt_news_short')));

                    // Render the main text of the tt_news record
                $lines[] = $this->renderPlainText->breakContent(strip_tags($this->renderPlainText->parseBody($row['bodytext'], 'tt_news_bodytext')));

                    // Render the images of the tt_news record.
                $lines[] = $this->getImages($row);

                    // Render the downloads of the tt_news record.
                $lines[] = $this->renderPlainText->renderUploads($row['news_files']);
            } elseif ($this->sys_language_mode == 'strict' && $this->tt_news_uid) {
                $noTranslMsg = $this->cObj->stdWrap($invokingObj->pi_getLL('noTranslMsg', 'Sorry, there is no translation for this news-article'), $this->conf['noNewsIdMsg_stdWrap.']);
                $content .= $noTranslMsg;
            }

            if (!empty($lines)) {
                $content = implode(LF, $lines).$content;
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
     * Get images found in the "image" field of "tt_news".
     *
     * @param array $row: tt_news record
     *
     * @return string Content
     */
    public function getImages($row)
    {
        $images_arr = explode(',', $row['image']);
        $images = $this->renderPlainText->renderImages($images_arr, '', $row['imagecaption']);

        return $images;
    }

    /**
     * Renders the author and date columns of the tt_news record.
     *
     * @param string $row:  The tt_news record
     * @param int    $type:
     *
     * @return string Content
     */
    public function renderAuthor($row, $type = 0)
    {
        if ($row['author']) {
            $hConf = $this->renderPlainText->conf['tt_news_author.'];
            $str = $this->renderPlainText->getString($hConf['prefix']).$row['author'].$this->renderPlainText->getString($hConf['emailPrefix']).'<'.$row['author_email'].'>';
            $defaultType = DirectMailUtility::intInRangeWrapper($hConf['defaultType'], 1, 5);
            $type = DirectMailUtility::intInRangeWrapper($type, 0, 6);

            if (!$type) {
                $type = $defaultType;
            }

            if ($type != 6) {    // not hidden
                $tConf = $hConf[$type.'.'];

                $lines = array();

                $blanks = DirectMailUtility::intInRangeWrapper($tConf['preBlanks'], 0, 1000);
                if ($blanks) {
                    $lines[] = str_pad('', $blanks - 1, LF);
                }

                $lines = $this->renderPlainText->pad($lines, $tConf['preLineChar'], $tConf['preLineLen']);

                $blanks = DirectMailUtility::intInRangeWrapper($tConf['preLineBlanks'], 0, 1000);
                if ($blanks) {
                    $lines[] = str_pad('', $blanks - 1, LF);
                }

                if ($row['datetime']) {
                    $lConf = $this->conf['displaySingle.'];
                    $lines[] = $this->renderPlainText->getString($hConf['datePrefix']).
                        $this->cObj->stdWrap($row['datetime'], $lConf['date_stdWrap.']).
                        ' '.
                        $this->cObj->stdWrap($row['datetime'], $lConf['time_stdWrap.']);
                }

                $lines[] = $this->cObj->stdWrap($str, $tConf['stdWrap.']);

                $blanks = DirectMailUtility::intInRangeWrapper($tConf['postLineBlanks'], 0, 1000);
                if ($blanks) {
                    $lines[] = str_pad('', $blanks - 1, LF);
                }

                $lines = $this->renderPlainText->pad($lines, $tConf['postLineChar'], $tConf['postLineLen']);

                $blanks = DirectMailUtility::intInRangeWrapper($tConf['postBlanks'], 0, 1000);
                if ($blanks) {
                    $lines[] = str_pad('', $blanks - 1, LF);
                }

                return implode(LF, $lines);
            }
        }

        return '';
    }
}
