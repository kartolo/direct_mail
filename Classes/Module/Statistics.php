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
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Backend\Utility\IconUtility;
use DirectMailTeam\DirectMail\DirectMailUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Module Statistics of tx_directmail extension
 *
 * @author		Kasper Sk�rh�j <kasper@typo3.com>
 * @author  	Jan-Erik Revsbech <jer@moccompany.com>
 * @author  	Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author		Ivan-Dharma Kartolo	<ivan.kartolo@dkd.de>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 */
class Statistics extends \TYPO3\CMS\Backend\Module\BaseScriptClass
{
    public $extKey = 'direct_mail';
    public $fieldList = 'uid,name,title,email,phone,www,address,company,city,zip,country,fax,module_sys_dmail_category,module_sys_dmail_html';
    // Internal
    public $params = array();
    public $implodedParams = array();
    public $perms_clause = '';
    public $pageinfo = '';
    public $sys_dmail_uid;
    public $CMD;
    public $pages_uid;
    public $id;
    public $urlbase;
    public $noView;
    public $url_plain;
    public $url_html;
    public $sys_language_uid = 0;
    public $allowedTables = array('tt_address','fe_users');
    public $MCONF;
    public $cshTable;
    public $formname = 'dmailform';

    /*
     * @var \TYPO3\CMS\Frontend\Page\PageRepository
     */
    public $sys_page;

    /*
     * @var array
     */
    public $categories;

    /**
     * IconFactory for skinning
     * @var \TYPO3\CMS\Core\Imaging\IconFactory
     */
    protected $iconFactory;

    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = 'DirectMailNavFrame_Statistics';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->MCONF = array(
            'name' => $this->moduleName
        );
    }

    /**
     * First initialization of global variables
     *
     * @return	void
     */
    public function init()
    {
        parent::init();

        // initialize IconFactory
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);

        // get TS Params
        $temp = BackendUtility::getModTSconfig($this->id, 'mod.web_modules.dmail');
        if (!is_array($temp['properties'])) {
            $temp['properties'] = array();
        }
        $this->params = $temp['properties'];
        $this->implodedParams = DirectMailUtility::implodeTSParams($this->params);

        $this->MOD_MENU['dmail_mode'] = BackendUtility::unsetMenuItems($this->params, $this->MOD_MENU['dmail_mode'], 'menu.dmail_mode');

            // initialize the page selector
        $this->sys_page = GeneralUtility::makeInstance('TYPO3\\CMS\\Frontend\\Page\\PageRepository');
        $this->sys_page->init(true);

            // initialize backend user language
        if ($this->getLanguageService()->lang && ExtensionManagementUtility::isLoaded('static_info_tables')) {
            $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
                'sys_language.uid',
                'sys_language LEFT JOIN static_languages ON sys_language.static_lang_isocode=static_languages.uid',
                'static_languages.lg_typo3=' . $GLOBALS["TYPO3_DB"]->fullQuoteStr($this->getLanguageService()->lang, 'static_languages') .
                    BackendUtility::BEenableFields('sys_language') .
                    BackendUtility::deleteClause('sys_language') .
                    BackendUtility::deleteClause('static_languages')
                );
            while (($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res))) {
                $this->sys_language_uid = $row['uid'];
            }
        }
            // load contextual help
        $this->cshTable = '_MOD_'.$this->MCONF['name'];
        if ($GLOBALS["BE_USER"]->uc['edit_showFieldHelp']) {
            $this->getLanguageService()->loadSingleTableDescription($this->cshTable);
        }
    }

    /**
     * Prints out the module HTML
     *
     * @return	void
     */
    public function printContent()
    {
        $this->content.=$this->doc->endPage();
    }

    /**
     * Entrance from the backend module. This replace the _dispatch
     *
     * @param ServerRequestInterface $request The request object from the backend
     * @param ResponseInterface $response The reponse object sent to the backend
     *
     * @return ResponseInterface Return the response object
     */
    public function mainAction(ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf');
        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');

        $this->init();

        $this->main();
        $this->printContent();

        $response->getBody()->write($this->content);
        return $response;
    }

    /**
     * The main function.
     *
     * @return	void
     */
    public function main()
    {
        $this->CMD = GeneralUtility::_GP('CMD');
        $this->pages_uid = intval(GeneralUtility::_GP('pages_uid'));
        $this->sys_dmail_uid = intval(GeneralUtility::_GP('sys_dmail_uid'));
        $this->pageinfo = BackendUtility::readPageAccess($this->id, $this->perms_clause);
        $access = is_array($this->pageinfo) ? 1 : 0;

        if (($this->id && $access) || ($GLOBALS["BE_USER"]->user['admin'] && !$this->id)) {

            // Draw the header.
            $this->doc = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
            $this->doc->backPath = $GLOBALS["BACK_PATH"];
            $this->doc->setModuleTemplate('EXT:direct_mail/Resources/Private/Templates/Module.html');
            $this->doc->form='<form action="" method="post" name="' . $this->formname . '" enctype="multipart/form-data">';

            // Add CSS
            $this->doc->inDocStyles = '
					a.bubble {position:relative; z-index:24; color:#000; text-decoration:none}
					a.bubble:hover {z-index:25; background-color: #e6e8ea;}
					a.bubble span.help {display: none;}
					a.bubble:hover span.help {display:block; position:absolute; top:2em; left:2em; width:25em; border:1px solid #0cf; background-color:#cff; padding: 2px;}
					td { vertical-align: top; }
					';

            // JavaScript
            $this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{ //
						window.location.href = URL;
					}
					function jumpToUrlD(URL) { //
						window.location.href = URL+"&sys_dmail_uid=' . $this->sys_dmail_uid . '";
					}
				</script>
			';

            $this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds[\'web\'] = ' . intval($this->id) . ';
				</script>
			';



            $markers = array(
                'FLASHMESSAGES' => '',
                'CONTENT' => '',
            );

            $docHeaderButtons = array(
                'PAGEPATH' => $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.php:labels.path') . ': ' . GeneralUtility::fixed_lgd_cs($this->pageinfo['_thePath'], 50),
                'SHORTCUT' => '',
                'CSH' => BackendUtility::cshItem($this->cshTable, '', $GLOBALS["BACK_PATH"])
            );
                // shortcut icon
            if ($GLOBALS["BE_USER"]->mayMakeShortcut()) {
                $docHeaderButtons['SHORTCUT'] = $this->doc->makeShortcutIcon('id', implode(',', array_keys($this->MOD_MENU)), $this->MCONF['name']);
            }

            $module = $this->pageinfo['module'];
            if (!$module) {
                $pidrec=BackendUtility::getRecord('pages', intval($this->pageinfo['pid']));
                $module=$pidrec['module'];
            }

            if ($module == 'dmail') {
                // Direct mail module
                    // Render content:
                if ($this->pageinfo['doktype']==254 && $this->pageinfo['module']=='dmail') {
                    $markers['CONTENT'] = '<h1>' . $this->getLanguageService()->getLL('stats_overview_header') . '</h1>' . $this->moduleContent();
                } elseif ($this->id != 0) {
                    /* @var $flashMessage FlashMessage */
                    $flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                        $this->getLanguageService()->getLL('dmail_noRegular'),
                        $this->getLanguageService()->getLL('dmail_newsletters'),
                        FlashMessage::WARNING
                    );
                    $markers['FLASHMESSAGES'] = $flashMessage->render();
                }
            } else {
                $flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                    $this->getLanguageService()->getLL('select_folder'),
                    $this->getLanguageService()->getLL('header_stat'),
                    FlashMessage::WARNING
                );
                $markers['FLASHMESSAGES'] = $flashMessage->render();

                $markers['CONTENT'] = '<h2>' . $this->getLanguageService()->getLL('stats_overview_header') . '</h2>';
            }

            $this->content = $this->doc->startPage($this->getLanguageService()->getLL('stats_overview_header'));
            $this->content.= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers, array());
        } else {
            // If no access or if ID == zero

            $this->doc = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
            $this->doc->backPath = $GLOBALS["BACK_PATH"];

            $this->content .= $this->doc->startPage($this->getLanguageService()->getLL('title'));
            $this->content .= $this->doc->header($this->getLanguageService()->getLL('title'));
            $this->content .= '<div style="padding-top: 15px;"></div>';
        }
    }

    /**
     * Compiled content of the module
     *
     * @return string The compiled content of the module
     */
    public function moduleContent()
    {
        $theOutput = "";

        if (!$this->sys_dmail_uid) {
            $theOutput = $this->cmd_displayPageInfo();
        } else {
            // Here the single dmail record is shown.
            $this->sys_dmail_uid = intval($this->sys_dmail_uid);
            $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
                '*',
                'sys_dmail',
                'pid=' . intval($this->id) .
                    ' AND uid=' . intval($this->sys_dmail_uid) .
                    BackendUtility::deleteClause('sys_dmail')
                );

            $this->noView = 0;

            if (($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res))) {
                // Set URL data for commands
                $this->setURLs($row);

                    // COMMAND:
                switch ($this->CMD) {
                    case 'displayUserInfo':
                        $theOutput = $this->cmd_displayUserInfo();
                        break;
                    case 'stats':
                        $theOutput = $this->cmd_stats($row);
                        break;
                    default:
                        // Hook for handling of custom direct mail commands:
                        if (is_array($GLOBALS["TYPO3_CONF_VARS"]['EXT']['directmail']['handledirectmailcmd-' . $this->CMD])) {
                            foreach ($GLOBALS["TYPO3_CONF_VARS"]['EXT']['directmail']['handledirectmailcmd-' . $this->CMD] as $funcRef) {
                                $params = array('pObj' => &$this);
                                $theOutput = GeneralUtility::callUserFunction($funcRef, $params, $this);
                            }
                        }
                }
            }
        }
        return $theOutput;
    }

    /**
     * Shows user's info and categories
     *
     * @return string HTML showing user's info and the categories
     */
    public function cmd_displayUserInfo()
    {
        $uid = intval(GeneralUtility::_GP('uid'));
        $indata = GeneralUtility::_GP('indata');
        $table = GeneralUtility::_GP('table');

        $mmTable = $GLOBALS["TCA"][$table]['columns']['module_sys_dmail_category']['config']['MM'];

        if (GeneralUtility::_GP('submit')) {
            $indata = GeneralUtility::_GP('indata');
            if (!$indata) {
                $indata['html']= 0;
            }
        }

        switch ($table) {
            case 'tt_address':
                // see fe_users
            case 'fe_users':
                if (is_array($indata)) {
                    $data=array();
                    if (is_array($indata['categories'])) {
                        reset($indata['categories']);
                        foreach ($indata["categories"] as $recValues) {
                            $enabled = array();
                            foreach ($recValues as $k => $b) {
                                if ($b) {
                                    $enabled[] = $k;
                                }
                            }
                            $data[$table][$uid]['module_sys_dmail_category'] = implode(',', $enabled);
                        }
                    }
                    $data[$table][$uid]['module_sys_dmail_html'] = $indata['html'] ? 1 : 0;

                    /* @var $tce \TYPO3\CMS\Core\DataHandling\DataHandler */
                    $tce = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\DataHandling\\DataHandler');
                    $tce->stripslashes_values=0;
                    $tce->start($data, array());
                    $tce->process_datamap();
                }
                break;
            default:
                // do nothing
        }

        switch ($table) {
            case 'tt_address':
                $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
                    'tt_address.*',
                    'tt_address LEFT JOIN pages ON pages.uid=tt_address.pid',
                    'tt_address.uid=' . intval($uid) .
                        ' AND ' . $this->perms_clause .
                        BackendUtility::deleteClause('pages') .
                        BackendUtility::BEenableFields('tt_address') .
                        BackendUtility::deleteClause('tt_address')
                    );
                break;
            case 'fe_users':
                $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
                    'fe_users.*',
                    'fe_users LEFT JOIN pages ON pages.uid=fe_users.pid',
                    'fe_users.uid=' . intval($uid) .
                        ' AND ' . $this->perms_clause .
                        BackendUtility::deleteClause('pages') .
                        BackendUtility::BEenableFields('fe_users') .
                        BackendUtility::deleteClause('fe_users')
                    );
                break;
            default:
                // do nothing
        }

        $row = array();
        if ($res) {
            $row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res);
            $GLOBALS["TYPO3_DB"]->sql_free_result($res);
        }

        $theOutput = "";
        if (is_array($row)) {
            $categories = '';
            $resCat = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
                'uid_foreign',
                $mmTable,
                'uid_local=' . $row['uid']
                );

            while (($rowCat = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($resCat))) {
                $categories .= $rowCat['uid_foreign'] . ',';
            }
            $categories = rtrim($categories, ",");
            $GLOBALS["TYPO3_DB"]->sql_free_result($resCat);

            $editParameters = '&edit[' . $table . '][' . $row['uid'] . ']=edit';
            $out = '';
            $out .= $this->iconFactory->getIconForRecord($table, $row)->render() . htmlspecialchars($row['name'] . ' <' . $row['email'] . '>');
            $out .= '&nbsp;&nbsp;<a href="#" onClick="' . BackendUtility::editOnClick($editParameters, $GLOBALS["BACK_PATH"], '') . '" title="' . $this->getLanguageService()->getLL("dmail_edit") . '">' .
                $this->iconFactory->getIcon('actions-open', Icon::SIZE_SMALL) .
                $this->getLanguageService()->getLL('dmail_edit') . '</b></a>';
            $theOutput = $this->doc->section($this->getLanguageService()->getLL('subscriber_info'), $out);

            $out = '';

            $this->categories = DirectMailUtility::makeCategories($table, $row, $this->sys_language_uid);

            foreach ($this->categories as $pKey => $pVal) {
                $out .='<input type="hidden" name="indata[categories][' . $row['uid'] . '][' . $pKey . ']" value="0" />' .
                    '<input type="checkbox" name="indata[categories][' . $row['uid'] . '][' . $pKey . ']" value="1"' . (GeneralUtility::inList($categories, $pKey)?' checked="checked"':'') . ' /> ' .
                    htmlspecialchars($pVal) . '<br />';
            }
            $out .= '<br /><br /><input type="checkbox" name="indata[html]" value="1"' . ($row['module_sys_dmail_html']?' checked="checked"':'') . ' /> ';
            $out .= $this->getLanguageService()->getLL('subscriber_profile_htmlemail') . '<br />';

            $out .= '<input type="hidden" name="table" value="' . $table . '" />' .
                '<input type="hidden" name="uid" value="' . $uid . '" />' .
                '<input type="hidden" name="CMD" value="' . $this->CMD . '" /><br />' .
                '<input type="submit" name="submit" value="' . htmlspecialchars($this->getLanguageService()->getLL('subscriber_profile_update')) . '" />';
            $theOutput .= '<div style="padding-top: 20px;"></div>';
            $theOutput .= $this->doc->section($this->getLanguageService()->getLL('subscriber_profile'), $this->getLanguageService()->getLL('subscriber_profile_instructions') . '<br /><br />' . $out);
        }

        return $theOutput;
    }

    /**
     * Shows the info of a page
     *
     * @return string The infopage of the sent newsletters
     */
    public function cmd_displayPageInfo()
    {
        // Here the dmail list is rendered:
        $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
            '*',
            'sys_dmail',
            'pid=' . intval($this->id) .
                ' AND type IN (0,1)' .
                ' AND issent = 1' .
                BackendUtility::deleteClause('sys_dmail'),
            '',
            'scheduled DESC, scheduled_begin DESC'
            );

        if ($GLOBALS["TYPO3_DB"]->sql_num_rows($res)) {
            $onClick = ' onClick="return confirm(' . GeneralUtility::quoteJSvalue(sprintf($this->getLanguageService()->getLL('nl_l_warning'), $GLOBALS["TYPO3_DB"]->sql_num_rows($res))) . ');"';
        } else {
            $onClick = '';
        }
        $out = '';

        if ($GLOBALS["TYPO3_DB"]->sql_num_rows($res)) {
            $out .='<table border="0" cellpadding="0" cellspacing="0" class="table table-striped table-hover">';
            $out .='<thead>
					<th>&nbsp;</th>
					<th><b>' . $this->getLanguageService()->getLL('stats_overview_subject') . '</b></th>
					<th><b>' . $this->getLanguageService()->getLL('stats_overview_scheduled') . '</b></th>
					<th><b>' . $this->getLanguageService()->getLL('stats_overview_delivery_begun') . '</b></th>
					<th><b>' . $this->getLanguageService()->getLL('stats_overview_delivery_ended') . '</b></th>
					<th nowrap="nowrap"><b>' . $this->getLanguageService()->getLL('stats_overview_total_sent') . '</b></th>
					<th><b>' . $this->getLanguageService()->getLL('stats_overview_status') . '</b></th>
				</thead>';
            while (($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res))) {
                $countRes = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
                    'count(*)',
                    'sys_dmail_maillog',
                    'mid = ' . $row['uid'] .
                        ' AND response_type=0' .
                        ' AND html_sent>0'
                );
                list($count) = $GLOBALS["TYPO3_DB"]->sql_fetch_row($countRes);

                if (!empty($row['scheduled_begin'])) {
                    if (!empty($row['scheduled_end'])) {
                        $sent = $this->getLanguageService()->getLL('stats_overview_sent');
                    } else {
                        $sent = $this->getLanguageService()->getLL('stats_overview_sending');
                    }
                } else {
                    $sent = $this->getLanguageService()->getLL('stats_overview_queuing');
                }

                $out.='<tr class="db_list_normal">
					<td>' . $this->iconFactory->getIconForRecord('sys_dmail', $row)->render() . '</td>
					<td>' . $this->linkDMail_record(GeneralUtility::fixed_lgd_cs($row['subject'], 30) . '  ', $row['uid'], $row['subject']) . '&nbsp;&nbsp;</td>
					<td>' . BackendUtility::datetime($row["scheduled"]) . '</td>
					<td>' . ($row["scheduled_begin"]?BackendUtility::datetime($row["scheduled_begin"]):'&nbsp;') . '</td>
					<td>' . ($row["scheduled_end"]?BackendUtility::datetime($row["scheduled_end"]):'&nbsp;') . '</td>
					<td>' . ($count?$count:'&nbsp;') . '</td>
					<td>' . $sent . '</td>
				</tr>';
            }
            $out.='</table>';
        }

        $theOutput = $this->doc->section($this->getLanguageService()->getLL('stats_overview_choose'), $out, 1, 1, 0, true);
        $theOutput .= '<div style="padding-top: 20px;"></div>';

        return $theOutput;
    }

    /**
     * Wrap a string with a link
     *
     * @param string $str String to be wrapped with a link
     * @param int $uid Record uid to be link
     * @param string $aTitle Title param of the link tag
     *
     * @return string wrapped string as a link
     */
    public function linkDMail_record($str, $uid, $aTitle='')
    {
        return '<a title="' . htmlspecialchars($aTitle) . '" href="' . BackendUtility::getModuleUrl('DirectMailNavFrame_Statistics') . '&id=' . $this->id . '&sys_dmail_uid=' . $uid . '&SET[dmail_mode]=direct&CMD=stats">' . htmlspecialchars($str) . '</a>';
    }

    /**
     * Get statistics from DB and compile them.
     *
     * @param array $row DB record
     *
     * @return string Statistics of a mail
     */
    public function cmd_stats($row)
    {
        if (GeneralUtility::_GP("recalcCache")) {
            $this->makeStatTempTableContent($row);
        }
        $thisurl = BackendUtility::getModuleUrl('DirectMailNavFrame_Statistics') . '&id=' . $this->id . '&sys_dmail_uid=' . $row['uid'] . '&CMD=' . $this->CMD . '&recalcCache=1';
        $output = $this->directMail_compactView($row);

            // *****************************
            // Mail responses, general:
            // *****************************

        $mailingId = intval($row['uid']);
        $queryArray = array('response_type,count(*) as counter', 'sys_dmail_maillog', 'mid=' . $mailingId, 'response_type');
        $table = $this->getQueryRows($queryArray, 'response_type');

            // Plaintext/HTML
        $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery('html_sent,count(*) as counter', 'sys_dmail_maillog', 'mid=' . $mailingId . ' AND response_type=0', 'html_sent');

        $textHtml = array();
        while (($row2 = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res))) {
            // 0:No mail; 1:HTML; 2:TEXT; 3:HTML+TEXT
            $textHtml[$row2['html_sent']] = $row2['counter'];
        }
        $GLOBALS["TYPO3_DB"]->sql_free_result($res);

            // Unique responses, html
        $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery('count(*) as counter', 'sys_dmail_maillog', 'mid=' . $mailingId . ' AND response_type=1', 'rid,rtbl', 'counter');
        $uniqueHtmlResponses = $GLOBALS["TYPO3_DB"]->sql_num_rows($res);
        $GLOBALS["TYPO3_DB"]->sql_free_result($res);

            // Unique responses, Plain
        $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery('count(*) as counter', 'sys_dmail_maillog', 'mid=' . $mailingId . ' AND response_type=2', 'rid,rtbl', 'counter');
        $uniquePlainResponses = $GLOBALS["TYPO3_DB"]->sql_num_rows($res);
        $GLOBALS["TYPO3_DB"]->sql_free_result($res);

            // Unique responses, pings
        $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery('count(*) as counter', 'sys_dmail_maillog', 'mid=' . $mailingId . ' AND response_type=-1', 'rid,rtbl', 'counter');
        $uniquePingResponses = $GLOBALS["TYPO3_DB"]->sql_num_rows($res);
        $GLOBALS["TYPO3_DB"]->sql_free_result($res);

        $tblLines = array();
        $tblLines[]=array('',$this->getLanguageService()->getLL('stats_total'),$this->getLanguageService()->getLL('stats_HTML'),$this->getLanguageService()->getLL('stats_plaintext'));

        $totalSent = intval($textHtml['1'] + $textHtml['2'] + $textHtml['3']);
        $htmlSent = intval($textHtml['1']+$textHtml['3']);
        $plainSent = intval($textHtml['2']);

        $tblLines[] = array($this->getLanguageService()->getLL('stats_mails_sent'),$totalSent,$htmlSent,$plainSent);
        $tblLines[] = array($this->getLanguageService()->getLL('stats_mails_returned'),$this->showWithPercent($table['-127']['counter'], $totalSent));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_HTML_mails_viewed'),'',$this->showWithPercent($uniquePingResponses, $htmlSent));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_unique_responses'),$this->showWithPercent($uniqueHtmlResponses+$uniquePlainResponses, $totalSent),$this->showWithPercent($uniqueHtmlResponses, $htmlSent),$this->showWithPercent($uniquePlainResponses, $plainSent?$plainSent:$htmlSent));

        $output.='<br /><h2>' . $this->getLanguageService()->getLL('stats_general_information') . '</h2>';
        $output.= DirectMailUtility::formatTable($tblLines, array('nowrap', 'nowrap', 'nowrap', 'nowrap'), 1, array());

            // ******************
            // Links:
            // ******************

            // initialize $urlCounter
        $urlCounter = array(
            'total' => array(),
            'plain' => array(),
            'html' => array(),
        );
            // Most popular links, html:
        $queryArray = array('url_id,count(*) as counter', 'sys_dmail_maillog', 'mid=' . intval($row['uid']) . ' AND response_type=1', 'url_id', 'counter');
        $htmlUrlsTable=$this->getQueryRows($queryArray, 'url_id');

            // Most popular links, plain:
        $queryArray = array('url_id,count(*) as counter', 'sys_dmail_maillog', 'mid=' . intval($row['uid']) . ' AND response_type=2', 'url_id', 'counter');
        $plainUrlsTable=$this->getQueryRows($queryArray, 'url_id');


        // Find urls:
        $unpackedMail = unserialize(base64_decode($row['mailContent']));
        // this array will include a unique list of all URLs that are used in the mailing
        $urlArr = array();

        $urlMd5Map = array();
        if (is_array($unpackedMail['html']['hrefs'])) {
            foreach ($unpackedMail['html']['hrefs'] as $k => $v) {
                // convert &amp; of query params back
                $urlArr[$k] = html_entity_decode($v['absRef']);
                $urlMd5Map[md5($v['absRef'])] = $k;
            }
        }
        if (is_array($unpackedMail['plain']['link_ids'])) {
            foreach ($unpackedMail['plain']['link_ids'] as $k => $v) {
                $urlArr[intval(-$k)] = $v;
            }
        }

        // Traverse plain urls:
        $mappedPlainUrlsTable = array();
        foreach ($plainUrlsTable as $id => $c) {
            $url = $urlArr[intval($id)];
            if (isset($urlMd5Map[md5($url)])) {
                $mappedPlainUrlsTable[$urlMd5Map[md5($url)]] = $c;
            } else {
                $mappedPlainUrlsTable[$id] = $c;
            }
        }

        $urlCounter['total'] = array();
        // Traverse html urls:
        $urlCounter['html'] = array();
        if (count($htmlUrlsTable) > 0) {
            foreach ($htmlUrlsTable as $id => $c) {
                $urlCounter['html'][$id]['counter'] = $urlCounter['total'][$id]['counter'] = $c['counter'];
            }
        }

        // Traverse plain urls:
        $urlCounter['plain'] = array();
        foreach ($mappedPlainUrlsTable as $id => $c) {
            // Look up plain url in html urls
            $htmlLinkFound = false;
            foreach ($urlCounter['html'] as $htmlId => $_) {
                if ($urlArr[$id] == $urlArr[$htmlId]) {
                    $urlCounter['html'][$htmlId]['plainId'] = $id;
                    $urlCounter['html'][$htmlId]['plainCounter'] = $c['counter'];
                    $urlCounter['total'][$htmlId]['counter'] = $urlCounter['total'][$htmlId]['counter'] + $c['counter'];
                    $htmlLinkFound = true;
                    break;
                }
            }
            if (!$htmlLinkFound) {
                $urlCounter['plain'][$id]['counter'] = $c['counter'];
                $urlCounter['total'][$id]['counter'] = $urlCounter['total'][$id]['counter'] + $c['counter'];
            }
        }

        $tblLines = array();
        $tblLines[] = array('',$this->getLanguageService()->getLL('stats_total'),$this->getLanguageService()->getLL('stats_HTML'),$this->getLanguageService()->getLL('stats_plaintext'));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_total_responses'),$table['1']['counter'] + $table['2']['counter'],$table['1']['counter']?$table['1']['counter']:'0',$table['2']['counter']?$table['2']['counter']:'0');
        $tblLines[] = array($this->getLanguageService()->getLL('stats_unique_responses'),$this->showWithPercent($uniqueHtmlResponses+$uniquePlainResponses, $totalSent), $this->showWithPercent($uniqueHtmlResponses, $htmlSent), $this->showWithPercent($uniquePlainResponses, $plainSent?$plainSent:$htmlSent));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_links_clicked_per_respondent'),
            ($uniqueHtmlResponses+$uniquePlainResponses ? number_format(($table['1']['counter']+$table['2']['counter'])/($uniqueHtmlResponses+$uniquePlainResponses), 2) : '-'),
            ($uniqueHtmlResponses  ? number_format(($table['1']['counter'])/($uniqueHtmlResponses), 2)  : '-'),
            ($uniquePlainResponses ? number_format(($table['2']['counter'])/($uniquePlainResponses), 2) : '-')
        );

        $output.='<br /><h2>' . $this->getLanguageService()->getLL('stats_response') . '</h2>';
        $output.=DirectMailUtility::formatTable($tblLines, array('nowrap', 'nowrap', 'nowrap', 'nowrap'), 1, array(0, 0, 0, 0));

        arsort($urlCounter['total']);
        arsort($urlCounter['html']);
        arsort($urlCounter['plain']);
        reset($urlCounter['total']);

        $tblLines = array();
        $tblLines[] = array('',$this->getLanguageService()->getLL('stats_HTML_link_nr'),$this->getLanguageService()->getLL('stats_plaintext_link_nr'),$this->getLanguageService()->getLL('stats_total'),$this->getLanguageService()->getLL('stats_HTML'),$this->getLanguageService()->getLL('stats_plaintext'),'');

            // HTML mails
        if (intval($row['sendOptions']) & 0x2) {
            $htmlContent = $unpackedMail['html']['content'];

            $htmlLinks = array();
            if (is_array($unpackedMail['html']['hrefs'])) {
                foreach ($unpackedMail['html']['hrefs'] as $jumpurlId => $data) {
                    $htmlLinks[$jumpurlId] = array(
                        'url'   => $data['ref'],
                        'label' => ''
                    );
                }
            }

            // Parse mail body
            $dom = new \DOMDocument;
            @$dom->loadHTML($htmlContent);
            $links = array();
            // Get all links
            foreach ($dom->getElementsByTagName('a') as $node) {
                $links[] = $node;
            }

            // Process all links found
            foreach ($links as $link) {
                /* @var \DOMElement $link */
                $url =  $link->getAttribute('href');

                if (empty($url)) {
                    // Drop a tags without href
                    continue;
                }

                if (GeneralUtility::isFirstPartOfStr($url, 'mailto:')) {
                    // Drop mail links
                    continue;
                }

                $parsedUrl = GeneralUtility::explodeUrl2Array($url);

                if (!array_key_exists('jumpurl', $parsedUrl)) {
                    // Ignore non-jumpurl links
                    continue;
                }

                $jumpurlId = $parsedUrl['jumpurl'];
                $targetUrl = $htmlLinks[$jumpurlId]['url'];

                $title = $link->getAttribute('title');

                if (!empty($title)) {
                    // no title attribute
                    $label = '<span title="' . $title . '">' . GeneralUtility::fixed_lgd_cs(substr($targetUrl, -40), 40) . '</span>';
                } else {
                    $label = '<span title="' . $targetUrl . '">' . GeneralUtility::fixed_lgd_cs(substr($targetUrl, -40), 40) . '</span>';
                }

                $htmlLinks[$jumpurlId]['label'] = $label;
            }
        }

        foreach ($urlCounter['total'] as $id => $_) {
            // $id is the jumpurl ID
            $origId = $id;
            $id     = abs(intval($id));
            $url    = $htmlLinks[$id]['url'] ? $htmlLinks[$id]['url'] : $urlArr[$origId];
                // a link to this host?
            $uParts = @parse_url($url);
            $urlstr = $this->getUrlStr($uParts);

            $label = $this->getLinkLabel($url, $urlstr, false, $htmlLinks[$id]['label']);

            $img = '<a href="' . $urlstr . '" target="_blank">' . $this->iconFactory->getIcon('apps-toolbar-menu-search', Icon::SIZE_SMALL) . '</a>';

            if (isset($urlCounter['html'][$id]['plainId'])) {
                $tblLines[] = array(
                    $label,
                    $id,
                    $urlCounter['html'][$id]['plainId'],
                    $urlCounter['total'][$origId]['counter'],
                    $urlCounter['html'][$id]['counter'],
                    $urlCounter['html'][$id]['plainCounter'],
                    $img
                );
            } else {
                $html = (empty($urlCounter['html'][$id]['counter']) ? 0 : 1);
                $tblLines[] = array(
                    $label,
                    ($html ? $id : '-'),
                    ($html ? '-' : $id),
                    ($html ? $urlCounter['html'][$id]['counter'] : $urlCounter['plain'][$origId]['counter']),
                    $urlCounter['html'][$id]['counter'],
                    $urlCounter['plain'][$origId]['counter'],
                    $img
                );
            }
        }


            // go through all links that were not clicked yet and that have a label
        $clickedLinks = array_keys($urlCounter['total']);
        foreach ($urlArr as $id => $link) {
            if (!in_array($id, $clickedLinks) && (isset($htmlLinks['id']))) {
                // a link to this host?
                $uParts = @parse_url($link);
                $urlstr = $this->getUrlStr($uParts);

                $label = $htmlLinks[$id]['label'] . ' (' . ($urlstr ? $urlstr : '/') . ')';
                $img = '<a href="' . htmlspecialchars($link) . '" target="_blank">' . $this->iconFactory->getIcon('apps-toolbar-menu-search', Icon::SIZE_SMALL) . '</a>';
                $tblLines[] = array(
                    $label,
                    ($html ? $id : '-'),
                    ($html ? '-' : abs($id)),
                    ($html ? $urlCounter['html'][$id]['counter'] : $urlCounter['plain'][$id]['counter']),
                    $urlCounter['html'][$id]['counter'],
                    $urlCounter['plain'][$id]['counter'],
                    $img
                );
            }
        }

        if ($urlCounter['total']) {
            $output .= '<br /><h2>' . $this->getLanguageService()->getLL('stats_response_link') . '</h2>';
            $output .= DirectMailUtility::formatTable($tblLines, array('nowrap', 'nowrap width="100"', 'nowrap width="100"', 'nowrap', 'nowrap', 'nowrap', 'nowrap'), 1, array(1, 0, 0, 0, 0, 0, 1));
        }




        // ******************
        // Returned mails
        // ******************

        // The icons:
        $listIcons = $this->iconFactory->getIcon('actions-system-list-open', Icon::SIZE_SMALL);
        $csvIcons = $this->iconFactory->getIcon('actions-document-export-csv', Icon::SIZE_SMALL);
        $hideIcons = $this->iconFactory->getIcon('actions-lock', Icon::SIZE_SMALL);

        // Icons mails returned
        $iconsMailReturned[] = '<a href="' . $thisurl . '&returnList=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_list_returned') . '"> ' . $listIcons . '</span></a>';
        $iconsMailReturned[] = '<a href="' . $thisurl . '&returnDisable=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_disable_returned') . '"> ' . $hideIcons . '</span></a>';
        $iconsMailReturned[] = '<a href="' . $thisurl . '&returnCSV=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_CSV_returned') . '"> ' . $csvIcons . '</span></a>';

        // Icons unknown recip
        $iconsUnknownRecip[] = '<a href="' . $thisurl . '&unknownList=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_list_returned_unknown_recipient') . '"> ' . $listIcons . '</span></a>';
        $iconsUnknownRecip[] = '<a href="' . $thisurl . '&unknownDisable=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_disable_returned_unknown_recipient') . '"> ' . $hideIcons . '</span></a>';
        $iconsUnknownRecip[] = '<a href="' . $thisurl . '&unknownCSV=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_CSV_returned_unknown_recipient') . '"> ' . $csvIcons . '</span></a>';

        // Icons mailbox full
        $iconsMailbox[] = '<a href="' . $thisurl . '&fullList=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_list_returned_mailbox_full') . '"> ' . $listIcons . '</span></a>';
        $iconsMailbox[] = '<a href="' . $thisurl . '&fullDisable=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_disable_returned_mailbox_full') . '"> ' . $hideIcons . '</span></a>';
        $iconsMailbox[] = '<a href="' . $thisurl . '&fullCSV=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_CSV_returned_mailbox_full') . '"> ' . $csvIcons . '</span></a>';

        // Icons bad host
        $iconsBadhost[] = '<a href="' . $thisurl . '&badHostList=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_list_returned_bad_host') . '"> ' . $listIcons . '</span></a>';
        $iconsBadhost[] = '<a href="' . $thisurl . '&badHostDisable=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_disable_returned_bad_host') . '"> ' . $hideIcons . '</span></a>';
        $iconsBadhost[] = '<a href="' . $thisurl . '&badHostCSV=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_CSV_returned_bad_host') . '"> ' . $csvIcons . '</span></a>';

        // Icons bad header
        $iconsBadheader[] = '<a href="' . $thisurl . '&badHeaderList=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_list_returned_bad_header') . '"> ' . $listIcons . '</span></a>';
        $iconsBadheader[] = '<a href="' . $thisurl . '&badHeaderDisable=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_disable_returned_bad_header') . '"> ' . $hideIcons . '</span></a>';
        $iconsBadheader[] = '<a href="' . $thisurl . '&badHeaderCSV=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_CSV_returned_bad_header') . '"> ' . $csvIcons . '</span></a>';

        // Icons unknown reasons
        // TODO: link to show all reason
        $iconsUnknownReason[] = '<a href="' . $thisurl . '&reasonUnknownList=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_list_returned_reason_unknown') . '"> ' . $listIcons . '</span></a>';
        $iconsUnknownReason[] = '<a href="' . $thisurl . '&reasonUnknownDisable=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_disable_returned_reason_unknown') . '"> ' . $hideIcons . '</span></a>';
        $iconsUnknownReason[] = '<a href="' . $thisurl . '&reasonUnknownCSV=1" class="bubble"><span class="help" title="' . $this->getLanguageService()->getLL('stats_CSV_returned_reason_unknown') . '"> ' . $csvIcons . '</span></a>';

        // Table with Icon
        $queryArray = array('count(*) as counter,return_code', 'sys_dmail_maillog', 'mid=' . intval($row['uid']) . ' AND response_type=-127', 'return_code');
        $responseResult = $this->getQueryRows($queryArray, 'return_code');

        $tblLines = array();
        $tblLines[] = array('',$this->getLanguageService()->getLL('stats_count'),'');
        $tblLines[] = array($this->getLanguageService()->getLL('stats_total_mails_returned'), ($table['-127']['counter']?number_format(intval($table['-127']['counter'])):'0'), implode('&nbsp;', $iconsMailReturned));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_recipient_unknown'), $this->showWithPercent($responseResult['550']['counter']+$responseResult['553']['counter'], $table['-127']['counter']), implode('&nbsp;', $iconsUnknownRecip));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_mailbox_full'), $this->showWithPercent($responseResult['551']['counter'], $table['-127']['counter']), implode('&nbsp;', $iconsMailbox));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_bad_host'), $this->showWithPercent($responseResult['552']['counter'], $table['-127']['counter']), implode('&nbsp;', $iconsBadhost));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_error_in_header'), $this->showWithPercent($responseResult['554']['counter'], $table['-127']['counter']),implode('&nbsp;', $iconsBadheader));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_reason_unkown'), $this->showWithPercent($responseResult['-1']['counter'], $table['-127']['counter']),implode('&nbsp;', $iconsUnknownReason));

        $output.='<br /><h2>' . $this->getLanguageService()->getLL('stats_mails_returned') . '</h2>';
        $output .= DirectMailUtility::formatTable($tblLines, array('nowrap', 'nowrap', ''), 1, array(0, 0, 1));

        // Find all returned mail
        if (GeneralUtility::_GP('returnList')||GeneralUtility::_GP('returnDisable')||GeneralUtility::_GP('returnCSV')) {
            $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
                'rid,rtbl,email',
                'sys_dmail_maillog',
                'mid=' . intval($row['uid']) .
                    ' AND response_type=-127'
                );
            $idLists = array();

            while (($rrow = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res))) {
                switch ($rrow['rtbl']) {
                    case 't':
                        $idLists['tt_address'][]=$rrow['rid'];
                        break;
                    case 'f':
                        $idLists['fe_users'][]=$rrow['rid'];
                        break;
                    case 'P':
                        $idLists['PLAINLIST'][] = $rrow['email'];
                        break;
                    default:
                        $idLists[$rrow['rtbl']][]=$rrow['rid'];
                }
            }

            if (GeneralUtility::_GP('returnList')) {
                if (is_array($idLists['tt_address'])) {
                    $output .= '<h3>' . $this->getLanguageService()->getLL('stats_emails') . '</h3>' . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address', $this->id, 1, $this->sys_dmail_uid);
                }
                if (is_array($idLists['fe_users'])) {
                    $output .= '<h3>' . $this->getLanguageService()->getLL('stats_website_users') . '</h3>' . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users', $this->id, 1, $this->sys_dmail_uid);
                }
                if (is_array($idLists['PLAINLIST'])) {
                    $output .= '<h3>' . $this->getLanguageService()->getLL('stats_plainlist') . '</h3>';
                    $output .= '<ul><li>' . join('</li><li>', $idLists['PLAINLIST']) . '</li></ul>';
                }
            }
            if (GeneralUtility::_GP('returnDisable')) {
                if (is_array($idLists['tt_address'])) {
                    $c=$this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address');
                    $output.='<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_emails_disabled');
                }
                if (is_array($idLists['fe_users'])) {
                    $c=$this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users');
                    $output.='<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_website_users_disabled');
                }
            }
            if (GeneralUtility::_GP('returnCSV')) {
                $emails=array();
                if (is_array($idLists['tt_address'])) {
                    $arr=DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address');
                    foreach ($arr as $v) {
                        $emails[]=$v['email'];
                    }
                }
                if (is_array($idLists['fe_users'])) {
                    $arr=DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users');
                    foreach ($arr as $v) {
                        $emails[]=$v['email'];
                    }
                }
                if (is_array($idLists['PLAINLIST'])) {
                    $emails = array_merge($emails, $idLists['PLAINLIST']);
                }
                $output .= '<br />' . $this->getLanguageService()->getLL('stats_emails_returned_list') . '<br />';
                $output .= '<textarea' . $this->doc->formWidth() . ' rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
            }
        }

        // Find Unknown Recipient
        if (GeneralUtility::_GP('unknownList')||GeneralUtility::_GP('unknownDisable')||GeneralUtility::_GP('unknownCSV')) {
            $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
                'rid,rtbl,email',
                'sys_dmail_maillog',
                'mid=' . intval($row['uid']) .
                    ' AND response_type=-127' .
                    ' AND (return_code=550 OR return_code=553)'
                );

            $idLists = array();
            while (($rrow = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res))) {
                switch ($rrow['rtbl']) {
                    case 't':
                        $idLists['tt_address'][]=$rrow['rid'];
                        break;
                    case 'f':
                        $idLists['fe_users'][]=$rrow['rid'];
                        break;
                    case 'P':
                        $idLists['PLAINLIST'][] = $rrow['email'];
                        break;
                    default:
                        $idLists[$rrow['rtbl']][]=$rrow['rid'];
                }
            }

            if (GeneralUtility::_GP('unknownList')) {
                if (is_array($idLists['tt_address'])) {
                    $output .='<br />' . $this->getLanguageService()->getLL('stats_emails') . '<br />' . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address', $this->id, 1, $this->sys_dmail_uid);
                }
                if (is_array($idLists['fe_users'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_website_users') . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users', $this->id, 1, $this->sys_dmail_uid);
                }
                if (is_array($idLists['PLAINLIST'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_plainlist');
                    $output .= '<ul><li>' . join('</li><li>', $idLists['PLAINLIST']) . '</li></ul>';
                }
            }
            if (GeneralUtility::_GP('unknownDisable')) {
                if (is_array($idLists['tt_address'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address');
                    $output .='<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_emails_disabled');
                }
                if (is_array($idLists['fe_users'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users');
                    $output .='<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_website_users_disabled');
                }
            }
            if (GeneralUtility::_GP('unknownCSV')) {
                $emails = array();
                if (is_array($idLists['tt_address'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address');
                    foreach ($arr as $v) {
                        $emails[]=$v['email'];
                    }
                }
                if (is_array($idLists['fe_users'])) {
                    $arr=DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users');
                    foreach ($arr as $v) {
                        $emails[]=$v['email'];
                    }
                }
                if (is_array($idLists['PLAINLIST'])) {
                    $emails = array_merge($emails, $idLists['PLAINLIST']);
                }
                $output .='<br />' . $this->getLanguageService()->getLL('stats_emails_returned_unknown_recipient_list') . '<br />';
                $output .='<textarea' . $this->doc->formWidth() . ' rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
            }
        }

        // Mailbox Full
        if (GeneralUtility::_GP('fullList')||GeneralUtility::_GP('fullDisable')||GeneralUtility::_GP('fullCSV')) {
            $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
                'rid,rtbl,email',
                'sys_dmail_maillog',
                'mid=' . intval($row['uid']) .
                    ' AND response_type=-127' .
                    ' AND return_code=551'
                );
            $idLists = array();
            while (($rrow = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res))) {
                switch ($rrow['rtbl']) {
                    case 't':
                        $idLists['tt_address'][]=$rrow['rid'];
                        break;
                    case 'f':
                        $idLists['fe_users'][]=$rrow['rid'];
                        break;
                    case 'P':
                        $idLists['PLAINLIST'][] = $rrow['email'];
                        break;
                    default:
                        $idLists[$rrow['rtbl']][]=$rrow['rid'];
                }
            }

            if (GeneralUtility::_GP('fullList')) {
                if (is_array($idLists['tt_address'])) {
                    $output .='<br />' . $this->getLanguageService()->getLL('stats_emails') . '<br />' . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address', $this->id, 1, $this->sys_dmail_uid);
                }
                if (is_array($idLists['fe_users'])) {
                    $output.= '<br />' . $this->getLanguageService()->getLL('stats_website_users') . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users', $this->id, 1, $this->sys_dmail_uid);
                }
                if (is_array($idLists['PLAINLIST'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_plainlist');
                    $output .= '<ul><li>' . join('</li><li>', $idLists['PLAINLIST']) . '</li></ul>';
                }
            }
            if (GeneralUtility::_GP('fullDisable')) {
                if (is_array($idLists['tt_address'])) {
                    $c=$this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address');
                    $output.='<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_emails_disabled');
                }
                if (is_array($idLists['fe_users'])) {
                    $c=$this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users');
                    $output.='<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_website_users_disabled');
                }
            }
            if (GeneralUtility::_GP('fullCSV')) {
                $emails=array();
                if (is_array($idLists['tt_address'])) {
                    $arr=DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address');
                    foreach ($arr as $v) {
                        $emails[]=$v['email'];
                    }
                }
                if (is_array($idLists['fe_users'])) {
                    $arr=DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users');
                    foreach ($arr as $v) {
                        $emails[]=$v['email'];
                    }
                }
                if (is_array($idLists['PLAINLIST'])) {
                    $emails = array_merge($emails, $idLists['PLAINLIST']);
                }
                $output .='<br />' . $this->getLanguageService()->getLL('stats_emails_returned_mailbox_full_list') . '<br />';
                $output .='<textarea' . $this->doc->formWidth() . ' rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
            }
        }

        // find Bad Host
        if (GeneralUtility::_GP('badHostList')||GeneralUtility::_GP('badHostDisable')||GeneralUtility::_GP('badHostCSV')) {
            $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
                'rid,rtbl,email',
                'sys_dmail_maillog',
                'mid=' . intval($row['uid']) .
                    ' AND response_type=-127' .
                    ' AND return_code=552'
                );
            $idLists = array();
            while (($rrow = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res))) {
                switch ($rrow['rtbl']) {
                    case 't':
                        $idLists['tt_address'][]=$rrow['rid'];
                        break;
                    case 'f':
                        $idLists['fe_users'][]=$rrow['rid'];
                        break;
                    case 'P':
                        $idLists['PLAINLIST'][] = $rrow['email'];
                        break;
                    default:
                        $idLists[$rrow['rtbl']][]=$rrow['rid'];
                }
            }

            if (GeneralUtility::_GP('badHostList')) {
                if (is_array($idLists['tt_address'])) {
                    $output .='<br />' . $this->getLanguageService()->getLL('stats_emails') . '<br />' . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address', $this->id, 1, $this->sys_dmail_uid);
                }
                if (is_array($idLists['fe_users'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_website_users') . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users', $this->id, 1, $this->sys_dmail_uid);
                }
                if (is_array($idLists['PLAINLIST'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_plainlist');
                    $output .= '<ul><li>' . join('</li><li>', $idLists['PLAINLIST']) . '</li></ul>';
                }
            }
            if (GeneralUtility::_GP('badHostDisable')) {
                if (is_array($idLists['tt_address'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address');
                    $output .='<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_emails_disabled');
                }
                if (is_array($idLists['fe_users'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users');
                    $output .= '<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_website_users_disabled');
                }
            }
            if (GeneralUtility::_GP('badHostCSV')) {
                $emails = array();
                if (is_array($idLists['tt_address'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address');
                    foreach ($arr as $v) {
                        $emails[] = $v['email'];
                    }
                }
                if (is_array($idLists['fe_users'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users');
                    foreach ($arr as $v) {
                        $emails[] = $v['email'];
                    }
                }
                if (is_array($idLists['PLAINLIST'])) {
                    $emails = array_merge($emails, $idLists['PLAINLIST']);
                }
                $output .= '<br />' . $this->getLanguageService()->getLL('stats_emails_returned_bad_host_list') . '<br />';
                $output .= '<textarea' . $this->doc->formWidth() . ' rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
            }
        }

        // find Bad Header
        if (GeneralUtility::_GP('badHeaderList')||GeneralUtility::_GP('badHeaderDisable')||GeneralUtility::_GP('badHeaderCSV')) {
            $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
                'rid,rtbl,email',
                'sys_dmail_maillog',
                'mid=' . intval($row['uid']) .
                    ' AND response_type=-127' .
                    ' AND return_code=554'
                );
            $idLists = array();
            while (($rrow = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res))) {
                switch ($rrow['rtbl']) {
                    case 't':
                        $idLists['tt_address'][] = $rrow['rid'];
                        break;
                    case 'f':
                        $idLists['fe_users'][] = $rrow['rid'];
                        break;
                    case 'P':
                        $idLists['PLAINLIST'][] = $rrow['email'];
                        break;
                    default:
                        $idLists[$rrow['rtbl']][] = $rrow['rid'];
                }
            }

            if (GeneralUtility::_GP('badHeaderList')) {
                if (is_array($idLists['tt_address'])) {
                    $output .='<br />' . $this->getLanguageService()->getLL('stats_emails') . '<br />' . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address', $this->id, 1, $this->sys_dmail_uid);
                }
                if (is_array($idLists['fe_users'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_website_users') . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users', $this->id, 1, $this->sys_dmail_uid);
                }
                if (is_array($idLists['PLAINLIST'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_plainlist');
                    $output .= '<ul><li>' . join('</li><li>', $idLists['PLAINLIST']) . '</li></ul>';
                }
            }

            if (GeneralUtility::_GP('badHeaderDisable')) {
                if (is_array($idLists['tt_address'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address');
                    $output .='<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_emails_disabled');
                }
                if (is_array($idLists['fe_users'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users');
                    $output .='<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_website_users_disabled');
                }
            }
            if (GeneralUtility::_GP('badHeaderCSV')) {
                $emails = array();
                if (is_array($idLists['tt_address'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address');
                    foreach ($arr as $v) {
                        $emails[] = $v['email'];
                    }
                }
                if (is_array($idLists['fe_users'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users');
                    foreach ($arr as $v) {
                        $emails[] = $v['email'];
                    }
                }
                if (is_array($idLists['PLAINLIST'])) {
                    $emails = array_merge($emails, $idLists['PLAINLIST']);
                }
                $output .= '<br />' . $this->getLanguageService()->getLL('stats_emails_returned_bad_header_list') .  '<br />';
                $output .= '<textarea' . $this->doc->formWidth() . ' rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
            }
        }

        // find Unknown Reasons
        // TODO: list all reason
        if (GeneralUtility::_GP('reasonUnknownList')||GeneralUtility::_GP('reasonUnknownDisable')||GeneralUtility::_GP('reasonUnknownCSV')) {
            $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
                'rid,rtbl,email',
                'sys_dmail_maillog',
                'mid=' . intval($row['uid']) .
                    ' AND response_type=-127' .
                    ' AND return_code=-1'
                );
            $idLists = array();
            while (($rrow = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res))) {
                switch ($rrow['rtbl']) {
                    case 't':
                        $idLists['tt_address'][] = $rrow['rid'];
                        break;
                    case 'f':
                        $idLists['fe_users'][] = $rrow['rid'];
                        break;
                    case 'P':
                        $idLists['PLAINLIST'][] = $rrow['email'];
                        break;
                    default:
                        $idLists[$rrow['rtbl']][] = $rrow['rid'];
                }
            }

            if (GeneralUtility::_GP('reasonUnknownList')) {
                if (is_array($idLists['tt_address'])) {
                    $output .='<br />' . $this->getLanguageService()->getLL('stats_emails') . '<br />' . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address', $this->id, 1, $this->sys_dmail_uid);
                }
                if (is_array($idLists['fe_users'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_website_users') . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users', $this->id, 1, $this->sys_dmail_uid);
                }
                if (is_array($idLists['PLAINLIST'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_plainlist');
                    $output .= '<ul><li>' . join('</li><li>', $idLists['PLAINLIST']) . '</li></ul>';
                }
            }
            if (GeneralUtility::_GP('reasonUnknownDisable')) {
                if (is_array($idLists['tt_address'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address');
                    $output .= '<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_emails_disabled');
                }
                if (is_array($idLists['fe_users'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users');
                    $output .= '<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_website_users_disabled');
                }
            }
            if (GeneralUtility::_GP('reasonUnknownCSV')) {
                $emails = array();
                if (is_array($idLists['tt_address'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address');
                    foreach ($arr as $v) {
                        $emails[]=$v['email'];
                    }
                }
                if (is_array($idLists['fe_users'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users');
                    foreach ($arr as $v) {
                        $emails[]=$v['email'];
                    }
                }
                if (is_array($idLists['PLAINLIST'])) {
                    $emails = array_merge($emails, $idLists['PLAINLIST']);
                }
                $output .= '<br />' . $this->getLanguageService()->getLL('stats_emails_returned_reason_unknown_list') . '<br />';
                $output .= '<textarea' . $this->doc->formWidth() . ' rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
            }
        }

        /**
         * Hook for cmd_stats_postProcess
         * insert a link to open extended importer
         */
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod4']['cmd_stats'])) {
            $hookObjectsArr = array();
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod4']['cmd_stats'] as $classRef) {
                $hookObjectsArr[] = &GeneralUtility::getUserObj($classRef);
            }

            // assigned $output to class property to make it acesssible inside hook
            $this->output = $output;

            // and clear the former $output to collect hoot return code there
            $output = '';

            foreach ($hookObjectsArr as $hookObj) {
                if (method_exists($hookObj, 'cmd_stats_postProcess')) {
                    $output .= $hookObj->cmd_stats_postProcess($row, $this);
                }
            }
        }

        $this->noView = 1;
        // put all the stats tables in a section
        $theOutput = $this->doc->section($this->getLanguageService()->getLL('stats_direct_mail'), $output, 1, 1, 0, true);
        $theOutput .= '<div style="padding-top: 20px;"></div>';

        $link = '<p><a style="text-decoration: underline;" href="' . $thisurl . '">' . $this->getLanguageService()->getLL('stats_recalculate_stats') . '</a></p>';
        $theOutput .= $this->doc->section($this->getLanguageService()->getLL('stats_recalculate_cached_data'), $link, 1, 1, 0, true);
        return $theOutput;
    }


    /**
     * This method returns the label for a specified URL.
     * If the page is local and contains a fragment it returns the label of the content element linked to.
     * In any other case it simply fetches the page and extracts the <title> tag content as label
     *
     * @param string $url The statistics click-URL for which to return a label
     * @param string $urlStr  A processed variant of the url string. This could get appended to the label???
     * @param bool $forceFetch When this parameter is set to true the "fetch and extract <title> tag" method will get used
     * @param string $linkedWord The word to be linked
     *
     * @return string The label for the passed $url parameter
     */
    public function getLinkLabel($url, $urlStr, $forceFetch = false, $linkedWord = '')
    {
        $pathSite = $this->getBaseURL();
        $label = $linkedWord;
        $contentTitle = '';

        $urlParts = parse_url($url);
        if (!$forceFetch && (substr($url, 0, strlen($pathSite)) === $pathSite)) {
            if ($urlParts['fragment'] && (substr($urlParts['fragment'], 0, 1) == 'c')) {
                // linking directly to a content
                $elementUid = intval(substr($urlParts['fragment'], 1));
                $row = BackendUtility::getRecord('tt_content', $elementUid);
                if ($row) {
                    $contentTitle = BackendUtility::getRecordTitle('tt_content', $row, false, true);
                }
            } else {
                $contentTitle = $this->getLinkLabel($url, $urlStr, true);
            }
        } else {
            if (empty($urlParts['host']) && (substr($url, 0, strlen($pathSite)) !== $pathSite)) {
                // it's internal
                $url = $pathSite . $url;
            }

            $content = GeneralUtility::getURL($url);

            if (preg_match('/\<\s*title\s*\>(.*)\<\s*\/\s*title\s*\>/i', $content, $matches)) {
                // get the page title
                $contentTitle = GeneralUtility::fixed_lgd_cs(trim($matches[1]), 50);
            } else {
                // file?
                $file = GeneralUtility::split_fileref($url);
                $contentTitle = $file['file'];
            }
        }

        if ($this->params['showContentTitle'] == 1) {
            $label = $contentTitle;
        }

        if ($this->params['prependContentTitle'] == 1) {
            $label =  $contentTitle . ' (' . $linkedWord . ')';
        }

        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXT']['directmail']['getLinkLabel'])) {
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXT']['directmail']['getLinkLabel'] as $funcRef) {
                $params = array('pObj' => &$this, 'url' => $url, 'urlStr' => $urlStr, 'label' => $label);
                $label = GeneralUtility::callUserFunction($funcRef, $params, $this);
            }
        }

            // Fallback to url
        if ($label === '') {
            $label = $url;
        }

        if (isset($this->params['maxLabelLength']) && ($this->params['maxLabelLength'] > 0)) {
            $label = GeneralUtility::fixed_lgd_cs($label, $this->params['maxLabelLength']);
        }

        return $label;
    }


    /**
     * Generates a string for the URL
     *
     * @param array $urlParts The parts of the URL
     *
     * @return string The URL string
     */
    public function getUrlStr(array $urlParts)
    {
        $baseUrl = $this->getBaseURL();

        if (is_array($urlParts) && GeneralUtility::getIndpEnv('TYPO3_HOST_ONLY') == $urlParts['host']) {
            $m = array();
            // do we have an id?
            if (preg_match('/(?:^|&)id=([0-9a-z_]+)/', $urlParts['query'], $m)) {
                $isInt = MathUtility::canBeInterpretedAsInteger($m[1]);

                if ($isInt) {
                    $uid = intval($m[1]);
                } else {
                    $uid = $this->sys_page->getPageIdFromAlias($m[1]);
                }
                $rootLine = $this->sys_page->getRootLine($uid);
                $pages = array_shift($rootLine);
                // array_shift reverses the array (rootline has numeric index in the wrong order!)
                $rootLine = array_reverse($rootLine);
                $query = preg_replace('/(?:^|&)id=([0-9a-z_]+)/', '', $urlParts['query']);
                $urlstr = GeneralUtility::fixed_lgd_cs($pages['title'], 50) . GeneralUtility::fixed_lgd_cs(($query ? ' / ' . $query : ''), 20);
            } else {
                $urlstr = $baseUrl . substr($urlParts['path'], 1);
                $urlstr .= $urlParts['query'] ? '?' . $urlParts['query'] : '';
                $urlstr .= $urlParts['fragment'] ? '#' . $urlParts['fragment'] : '';
            }
        } else {
            $urlstr =  ($urlParts['host'] ? $urlParts['scheme'] . '://' . $urlParts['host'] : $baseUrl) . $urlParts['path'];
            $urlstr .= $urlParts['query'] ? '?' . $urlParts['query'] : '';
            $urlstr .= $urlParts['fragment'] ? '#' . $urlParts['fragment'] : '';
        }

        return $urlstr;
    }

    /**
     * Get baseURL of the FE
     * force http if UseHttpToFetch is set
     *
     * @return string the baseURL
     */
    public function getBaseURL()
    {
        $baseUrl = GeneralUtility::getIndpEnv("TYPO3_SITE_URL");

        # if fetching the newsletter using http, set the url to http here
        if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['UseHttpToFetch'] == 1) {
            $baseUrl = str_replace('https', 'http', $baseUrl);
        }

        return $baseUrl;
    }

    /**
     * Set disable=1 to all record in an array
     *
     * @param array $arr DB records
     * @param string $table table name
     *
     * @return int total of disabled records
     */
    public function disableRecipients(array $arr, $table)
    {
        if ($GLOBALS['TCA'][$table]) {
            $values = array();
            $enField = $GLOBALS['TCA'][$table]['ctrl']['enablecolumns']['disabled'];
            if ($enField) {
                $values[$enField] = 1;
                $count = count($arr);
                $uidList = array_keys($arr);
                if (count($uidList)) {
                    $res = $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                        $table,
                        'uid IN (' . implode(',', $GLOBALS['TYPO3_DB']->cleanIntArray($uidList)) . ')',
                        $values
                        );
                    $GLOBALS["TYPO3_DB"]->sql_free_result($res);
                }
            }
        }
        return intval($count);
    }

    /**
     * Write the statistic to a temporary table
     *
     * @param array $mrow DB mail records
     *
     * @return void
     */
    public function makeStatTempTableContent(array $mrow)
    {
        // Remove old:
        $GLOBALS["TYPO3_DB"]->exec_DELETEquery(
            'cache_sys_dmail_stat',
            'mid=' . intval($mrow['uid'])
            );

        $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
            'rid,rtbl,tstamp,response_type,url_id,html_sent,size',
            'sys_dmail_maillog',
            'mid=' . intval($mrow['uid']),
            '',
            'rtbl,rid,tstamp'
            );

        $currentRec = '';
        $recRec = '';

        while (($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res))) {
            $thisRecPointer = $row['rtbl'] . $row['rid'];

            if ($thisRecPointer != $currentRec) {
                $recRec = array(
                    'mid'            => intval($mrow['uid']),
                    'rid'            => $row['rid'],
                    'rtbl'            => $row['rtbl'],
                    'pings'            => array(),
                    'plain_links'    => array(),
                    'html_links'    => array(),
                    'response'        => array(),
                    'links'            => array()
                    );
                $currentRec = $thisRecPointer;
            }
            switch ($row['response_type']) {
                case '-1':
                    $recRec['pings'][] = $row['tstamp'];
                    $recRec['response'][] = $row['tstamp'];
                    break;
                case '0':
                    $recRec['recieved_html'] = $row['html_sent']&1;
                    $recRec['recieved_plain'] = $row['html_sent']&2;
                    $recRec['size'] = $row['size'];
                    $recRec['tstamp'] = $row['tstamp'];
                    break;
                case '1':
                    // treat html links like plain text
                case '2':
                    // plain text link response
                    $recRec[($row['response_type']==1?'html_links':'plain_links')][] = $row['tstamp'];
                    $recRec['links'][] = $row['tstamp'];
                    if (!$recRec['firstlink']) {
                        $recRec['firstlink'] = $row['url_id'];
                        $recRec['firstlink_time'] = intval(@max($recRec['pings']));
                        $recRec['firstlink_time'] = $recRec['firstlink_time'] ? $row['tstamp']-$recRec['firstlink_time'] : 0;
                    } elseif (!$recRec['secondlink']) {
                        $recRec['secondlink'] = $row['url_id'];
                        $recRec['secondlink_time'] = intval(@max($recRec['pings']));
                        $recRec['secondlink_time'] = $recRec['secondlink_time'] ? $row['tstamp']-$recRec['secondlink_time'] : 0;
                    } elseif (!$recRec['thirdlink']) {
                        $recRec['thirdlink'] = $row['url_id'];
                        $recRec['thirdlink_time'] = intval(@max($recRec['pings']));
                        $recRec['thirdlink_time'] = $recRec['thirdlink_time'] ? $row['tstamp']-$recRec['thirdlink_time'] : 0;
                    }
                    $recRec['response'][] = $row['tstamp'];
                    break;
                case '-127':
                    $recRec['returned'] = 1;
                    break;
                default:
                    // do nothing
            }
        }

        $GLOBALS["TYPO3_DB"]->sql_free_result($res);
        $this->storeRecRec($recRec);
    }

    /**
     * Insert statistic to a temporary table
     *
     * @param array $recRec Statistic array
     *
     * @return void
     */
    public function storeRecRec(array $recRec)
    {
        if (is_array($recRec)) {
            $recRec['pings_first'] = intval(@min($recRec['pings']));
            $recRec['pings_last'] = intval(@max($recRec['pings']));
            $recRec['pings'] = count($recRec['pings']);

            $recRec['html_links_first'] = intval(@min($recRec['html_links']));
            $recRec['html_links_last'] = intval(@max($recRec['html_links']));
            $recRec['html_links'] = count($recRec['html_links']);

            $recRec['plain_links_first'] = intval(@min($recRec['plain_links']));
            $recRec['plain_links_last'] = intval(@max($recRec['plain_links']));
            $recRec['plain_links'] = count($recRec['plain_links']);

            $recRec['links_first'] = intval(@min($recRec['links']));
            $recRec['links_last'] = intval(@max($recRec['links']));
            $recRec['links'] = count($recRec['links']);

            $recRec['response_first'] = DirectMailUtility::intInRangeWrapper(intval(@min($recRec['response']))-$recRec['tstamp'], 0);
            $recRec['response_last'] = DirectMailUtility::intInRangeWrapper(intval(@max($recRec['response']))-$recRec['tstamp'], 0);
            $recRec['response'] = count($recRec['response']);

            $recRec['time_firstping'] = DirectMailUtility::intInRangeWrapper($recRec['pings_first']-$recRec['tstamp'], 0);
            $recRec['time_lastping'] = DirectMailUtility::intInRangeWrapper($recRec['pings_last']-$recRec['tstamp'], 0);

            $recRec['time_first_link'] = DirectMailUtility::intInRangeWrapper($recRec['links_first']-$recRec['tstamp'], 0);
            $recRec['time_last_link'] = DirectMailUtility::intInRangeWrapper($recRec['links_last']-$recRec['tstamp'], 0);

            $res = $GLOBALS['TYPO3_DB']->exec_INSERTquery(
                'cache_sys_dmail_stat',
                $recRec
            );
            $GLOBALS["TYPO3_DB"]->sql_free_result($res);
        }
    }

    /**
     * Make a select query
     *
     * @param array $queryArray Part of select-statement in an array
     * @param string $fieldName DB fieldname to be the array keys
     *
     * @return array Result of the Select-query
     */
    public function getQueryRows(array $queryArray, $fieldName)
    {
        $res = $GLOBALS["TYPO3_DB"]->exec_SELECTquery(
            $queryArray[0],
            $queryArray[1],
            $queryArray[2],
            $queryArray[3],
            $queryArray[4],
            $queryArray[5]
            );
        $lines = array();
        while (($row = $GLOBALS["TYPO3_DB"]->sql_fetch_assoc($res))) {
            if ($fieldName) {
                $lines[$row[$fieldName]] = $row;
            } else {
                $lines[] = $row;
            }
        }
        $GLOBALS["TYPO3_DB"]->sql_free_result($res);

        return $lines;
    }

    /**
     * Make a percent from the given parameters
     *
     * @param int $pieces Number of pieces
     * @param int $total Total of pieces
     *
     * @return string show number of pieces and the percent
     */
    public function showWithPercent($pieces, $total)
    {
        $total = intval($total);
        $str = $pieces?number_format(intval($pieces)):'0';
        if ($total) {
            $str .= ' / ' . number_format(($pieces/$total*100), 2) . '%';
        }
        return $str;
    }

    /**
     * Set up URL variables for this $row.
     *
     * @param array $row DB records
     *
     * @return void
     */
    public function setURLs(array $row)
    {
        // Finding the domain to use
        $this->urlbase = DirectMailUtility::getUrlBase($row['use_domain']);

            // Finding the url to fetch content from
        switch ((string)$row['type']) {
            case 1:
                $this->url_html = $row['HTMLParams'];
                $this->url_plain = $row['plainParams'];
                break;
            default:
                $this->url_html = $this->urlbase . '?id=' . $row['page'] . $row['HTMLParams'];
                $this->url_plain = $this->urlbase . '?id=' . $row['page'] . $row['plainParams'];
        }

        // plain
        if (!($row['sendOptions']&1) || !$this->url_plain) {
            $this->url_plain = '';
        } else {
            $urlParts = @parse_url($this->url_plain);
            if (!$urlParts['scheme']) {
                $this->url_plain = 'http://' . $this->url_plain;
            }
        }

        // html
        if (!($row['sendOptions']&2) || !$this->url_html) {
            $this->url_html = '';
        } else {
            $urlParts = @parse_url($this->url_html);
            if (!$urlParts['scheme']) {
                $this->url_html = 'http://' . $this->url_html;
            }
        }
    }

    /**
     * Show the compact information of a direct mail record
     *
     * @param array $row Direct mail record
     *
     * @return string The compact infos of the direct mail record
     */
    public function directMail_compactView($row)
    {
        // Render record:
        if ($row['type']) {
            $dmailData = $row['plainParams'] . ', ' . $row['HTMLParams'];
        } else {
            $page = BackendUtility::getRecord('pages', $row['page'], 'title');
            $dmailData = $row['page'] . ', ' . htmlspecialchars($page['title']);

            $dmailInfo = DirectMailUtility::fName('plainParams') . ' ' . htmlspecialchars($row['plainParams'] . LF . DirectMailUtility::fName('HTMLParams') . $row['HTMLParams']) . '; ' . LF;
        }

        $dmailInfo .= $this->getLanguageService()->getLL('view_media') . ' ' . BackendUtility::getProcessedValue('sys_dmail', 'includeMedia', $row['includeMedia']) . '; ' . LF .
            $this->getLanguageService()->getLL('view_flowed') . ' ' . BackendUtility::getProcessedValue('sys_dmail', 'flowedFormat', $row['flowedFormat']);

        $dmailInfo = '<span title="' . $dmailInfo . '">' .
            $this->iconFactory->getIcon('actions-document-info', Icon::SIZE_SMALL) .
            '</span>';

        $fromInfo = $this->getLanguageService()->getLL('view_replyto') . ' ' . htmlspecialchars($row['replyto_name'] . ' <' . $row['replyto_email'] . '>') . '; ' . LF .
            DirectMailUtility::fName('organisation') . ' ' . htmlspecialchars($row['organisation']) . '; ' . LF .
            DirectMailUtility::fName('return_path') . ' ' . htmlspecialchars($row['return_path']);
        $fromInfo = '<span title="' . $fromInfo . '">' .
            $this->iconFactory->getIcon('actions-document-info', Icon::SIZE_SMALL) .
            '</span>';

        $mailInfo = DirectMailUtility::fName('priority') . ' ' . BackendUtility::getProcessedValue('sys_dmail', 'priority', $row['priority']) . '; ' . LF .
            DirectMailUtility::fName('encoding') . ' ' . BackendUtility::getProcessedValue('sys_dmail', 'encoding', $row['encoding']) . '; ' . LF .
            DirectMailUtility::fName('charset') . ' ' . BackendUtility::getProcessedValue('sys_dmail', 'charset', $row['charset']);
        $mailInfo = '<span title="' . $mailInfo . '">' .
            $this->iconFactory->getIcon('actions-document-info', Icon::SIZE_SMALL) .
            '</span>';

        $delBegin = ($row["scheduled_begin"]?BackendUtility::datetime($row["scheduled_begin"]):'-');
        $delEnd = ($row["scheduled_end"]?BackendUtility::datetime($row["scheduled_begin"]):'-');

        // count total recipient from the query_info
        $totalRecip = 0;
        $idLists = unserialize($row['query_info']);
        foreach ($idLists['id_lists'] as $idArray) {
            $totalRecip += count($idArray);
        }
        $sentRecip = $GLOBALS['TYPO3_DB']->sql_num_rows($GLOBALS['TYPO3_DB']->exec_SELECTquery('*', 'sys_dmail_maillog', 'mid=' . $row['uid'] . ' AND response_type = 0', '', 'rid ASC'));

        $out = '<table class="table table-striped table-hover">';
        $out .= '<tr class="t3-row-header"><td colspan="3">' . $this->iconFactory->getIconForRecord('sys_dmail', $row)->render() . htmlspecialchars($row['subject']) . '</td></tr>';
        $out .= '<tr class="db_list_normal"><td>' . $this->getLanguageService()->getLL('view_from') . '</td>' .
            '<td>' . htmlspecialchars($row['from_name'] . ' <' . htmlspecialchars($row['from_email']) . '>') . '</td>' .
            '<td>' . $fromInfo . '</td></tr>';
        $out .= '<tr class="db_list_normal"><td>' . $this->getLanguageService()->getLL('view_dmail') . '</td>' .
            '<td>' . BackendUtility::getProcessedValue('sys_dmail', 'type', $row['type']) . ': ' . $dmailData . '</td>' .
            '<td>' . $dmailInfo . '</td></tr>';
        $out .= '<tr class="db_list_normal"><td>' . $this->getLanguageService()->getLL('view_mail') . '</td>' .
            '<td>' . BackendUtility::getProcessedValue('sys_dmail', 'sendOptions', $row['sendOptions']) . ($row['attachment']?'; ':'') . BackendUtility::getProcessedValue('sys_dmail', 'attachment', $row['attachment']) . '</td>' .
            '<td>' . $mailInfo . '</td></tr>';
        $out .= '<tr class="db_list_normal"><td>' . $this->getLanguageService()->getLL('view_delivery_begin_end') . '</td>' .
            '<td>' . $delBegin . ' / ' . $delEnd . '</td>' .
            '<td>&nbsp;</td></tr>';
        $out .= '<tr class="db_list_normal"><td>' . $this->getLanguageService()->getLL('view_recipient_total_sent') . '</td>' .
            '<td>' . $totalRecip . ' / ' . $sentRecip . '</td>' .
            '<td>&nbsp;</td></tr>';
        $out .= '</table>';
        $out .= '<div style="padding-top: 5px;"></div>';

        return $out;
    }

    /**
     * Returns LanguageService
     *
     * @return \TYPO3\CMS\Lang\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
