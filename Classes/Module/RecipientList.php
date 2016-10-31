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
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use DirectMailTeam\DirectMail\DirectMailUtility;

/**
 * Recipient list module for tx_directmail extension.
 *
 * @author		Kasper Sk�rh�j <kasper@typo3.com>
 * @author  	Jan-Erik Revsbech <jer@moccompany.com>
 * @author  	Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author		Ivan-Dharma Kartolo	<ivan.kartolo@dkd.de>
 */
class RecipientList extends \TYPO3\CMS\Backend\Module\BaseScriptClass
{
    public $fieldList = 'uid,name,first_name,middle_name,last_name,title,email,phone,www,address,company,city,zip,country,fax,module_sys_dmail_category,module_sys_dmail_html';
    // Internal
    public $params = array();
    public $perms_clause = '';
    public $pageinfo = '';
    public $sys_dmail_uid;
    public $CMD;
    public $pages_uid;
    public $categories;
    public $id;
    public $urlbase;
    public $back;
    public $noView;
    public $url_plain;
    public $url_html;
    public $mode;
    public $implodedParams = array();
    // If set a valid user table is around
    public $userTable;
    public $sys_language_uid = 0;
    public $error = '';
    public $allowedTables = array('tt_address', 'fe_users');

    /**
     * Query generator.
     *
     * @var \DirectMailTeam\DirectMail\MailSelect
     */
    public $queryGenerator;
    public $MCONF;
    public $cshTable;
    public $formname = 'dmailform';

    /**
     * IconFactory for skinning.
     *
     * @var \TYPO3\CMS\Core\Imaging\IconFactory
     */
    protected $iconFactory;

    /**
     * The name of the module.
     *
     * @var string
     */
    protected $moduleName = 'DirectMailNavFrame_RecipientList';

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->MCONF = array(
                'name' => $this->moduleName,
        );
    }

    /**
     * First initialization of global variables.
     */
    public function init()
    {
        parent::init();

        // initialize IconFactory
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);

        $temp = BackendUtility::getModTSconfig($this->id, 'mod.web_modules.dmail');
        if (!is_array($temp['properties'])) {
            $temp['properties'] = array();
        }
        $this->params = $temp['properties'];
        $this->implodedParams = DirectMailUtility::implodeTSParams($this->params);
        if ($this->params['userTable'] && is_array($GLOBALS['TCA'][$this->params['userTable']])) {
            $this->userTable = $this->params['userTable'];
            $this->allowedTables[] = $this->userTable;
        }
        $this->MOD_MENU['dmail_mode'] = BackendUtility::unsetMenuItems($this->params, $this->MOD_MENU['dmail_mode'], 'menu.dmail_mode');

            // initialize the query generator
        $this->queryGenerator = GeneralUtility::makeInstance('DirectMailTeam\\DirectMail\\MailSelect');

            // initialize backend user language
        if ($this->getLanguageService()->lang && ExtensionManagementUtility::isLoaded('static_info_tables')) {
            $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
                'sys_language.uid',
                'sys_language LEFT JOIN static_languages ON sys_language.static_lang_isocode=static_languages.uid',
                'static_languages.lg_typo3='.$GLOBALS['TYPO3_DB']->fullQuoteStr($this->getLanguageService()->lang, 'static_languages').
                    BackendUtility::BEenableFields('sys_language').
                    BackendUtility::deleteClause('sys_language').
                    BackendUtility::deleteClause('static_languages')
                );
            while (($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))) {
                $this->sys_language_uid = $row['uid'];
            }
        }
            // load contextual help
        $this->cshTable = '_MOD_'.$this->MCONF['name'];
        if ($GLOBALS['BE_USER']->uc['edit_showFieldHelp']) {
            $this->getLanguageService()->loadSingleTableDescription($this->cshTable);
        }
    }

    /**
     * Entrance from the backend module. This replace the _dispatch.
     *
     * @param ServerRequestInterface $request  The request object from the backend
     * @param ResponseInterface      $response The reponse object sent to the backend
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
     */
    public function main()
    {
        $this->CMD = GeneralUtility::_GP('CMD');
        $this->pages_uid = intval(GeneralUtility::_GP('pages_uid'));
        $this->sys_dmail_uid = intval(GeneralUtility::_GP('sys_dmail_uid'));
        $this->pageinfo = BackendUtility::readPageAccess($this->id, $this->perms_clause);
        $access = is_array($this->pageinfo) ? 1 : 0;

        if (($this->id && $access) || ($GLOBALS['BE_USER']->user['admin'] && !$this->id)) {

            // Draw the header.
            $this->doc = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
            $this->doc->backPath = $GLOBALS['BACK_PATH'];
            $this->doc->setModuleTemplate('EXT:direct_mail/Resources/Private/Templates/Module.html');
            $this->doc->form = '<form action="" method="post" name="'.$this->formname.'" enctype="multipart/form-data">';

            // CSS
            // hide textarea in import
            $this->doc->inDocStyles = 'textarea.hide{display:none;}';

            // JavaScript
            $this->doc->JScode = '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{ //
						window.location.href = URL;
					}
					function jumpToUrlD(URL) { //
						window.location.href = URL+"&sys_dmail_uid=' .$this->sys_dmail_uid.'";
					}
				</script>
			';

            $this->doc->postCode = '
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds[\'web\'] = ' .intval($this->id).';
				</script>
			';

            $markers = array(
                'FLASHMESSAGES' => '',
                'CONTENT' => '',
            );

            $docHeaderButtons = array(
                'PAGEPATH' => $this->getLanguageService()->sL('LLL:EXT:lang/locallang_core.php:labels.path').': '.GeneralUtility::fixed_lgd_cs($this->pageinfo['_thePath'], 50),
                'SHORTCUT' => '',
                'CSH' => BackendUtility::cshItem($this->cshTable, '', $GLOBALS['BACK_PATH']),
            );
                // shortcut icon
            if ($GLOBALS['BE_USER']->mayMakeShortcut()) {
                $docHeaderButtons['SHORTCUT'] = $this->doc->makeShortcutIcon('id', implode(',', array_keys($this->MOD_MENU)), $this->MCONF['name']);
            }

            $module = $this->pageinfo['module'];
            if (!$module) {
                $pidrec = BackendUtility::getRecord('pages', intval($this->pageinfo['pid']));
                $module = $pidrec['module'];
            }

                // Render content:
            if ($module == 'dmail') {
                // Direct mail module
                if ($this->pageinfo['doktype'] == 254 && $this->pageinfo['module'] == 'dmail') {
                    $markers['CONTENT'] = '<h1>'.$this->getLanguageService()->getLL('mailgroup_header').'</h1>'.
                        $this->moduleContent();
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
                /* @var $flashMessage FlashMessage */
                $flashMessage = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage',
                    $this->getLanguageService()->getLL('select_folder'),
                    $this->getLanguageService()->getLL('header_recip'),
                    FlashMessage::WARNING
                );
                $markers['FLASHMESSAGES'] = $flashMessage->render();
            }

            $this->content = $this->doc->startPage($this->getLanguageService()->getLL('mailgroup_header'));
            $this->content .= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers, array());
        } else {
            // If no access or if ID == zero

            $this->doc = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
            $this->doc->backPath = $GLOBALS['BACK_PATH'];

            $this->content .= $this->doc->startPage($this->getLanguageService()->getLL('title'));
            $this->content .= $this->doc->header($this->getLanguageService()->getLL('title'));
        }
    }

    /**
     * Prints out the module HTML.
     */
    public function printContent()
    {
        $this->content .= $this->doc->endPage();
    }

    /**
     * Show the module content.
     *
     * @return string The compiled content of the module
     */
    protected function moduleContent()
    {

            // COMMAND:
        switch ($this->CMD) {
            case 'displayUserInfo':
                $theOutput = $this->cmd_displayUserInfo();
                break;
            case 'displayMailGroup':
                $result = $this->cmd_compileMailGroup(intval(GeneralUtility::_GP('group_uid')));
                $theOutput = $this->cmd_displayMailGroup($result);
                break;
            case 'displayImport':
                /* @var $importer \DirectMailTeam\DirectMail\Importer */
                $importer = GeneralUtility::makeInstance('DirectMailTeam\\DirectMail\\Importer');
                $importer->init($this);
                $theOutput = $importer->cmd_displayImport();
                break;
            default:
                $theOutput = $this->showExistingRecipientLists();
        }

        return $theOutput;
    }

    /**
     * Shows the existing recipient lists and shows link to create a new one or import a list.
     *
     * @return string List of existing recipient list, link to create a new list and link to import
     */
    public function showExistingRecipientLists()
    {
        $out = '<thead>
					<th colspan="2">&nbsp;</th>
					<th>' .$this->getLanguageService()->sL(BackendUtility::getItemLabel('sys_dmail_group', 'title')).'</th>
					<th>' .$this->getLanguageService()->sL(BackendUtility::getItemLabel('sys_dmail_group', 'type')).'</th>
					<th>' .$this->getLanguageService()->sL(BackendUtility::getItemLabel('sys_dmail_group', 'description')).'</th>
					<th>' .$this->getLanguageService()->getLL('recip_group_amount').'</th>
				</thead>';

        $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
            'uid,pid,title,description,type',
            'sys_dmail_group',
            'pid = '.intval($this->id).
                BackendUtility::deleteClause('sys_dmail_group'),
            '',
            $GLOBALS['TYPO3_DB']->stripOrderBy($GLOBALS['TCA']['sys_dmail_group']['ctrl']['default_sortby'])
        );

        while (($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))) {
            $result = $this->cmd_compileMailGroup(intval($row['uid']));
            $count = 0;
            $idLists = $result['queryInfo']['id_lists'];
            if (is_array($idLists['tt_address'])) {
                $count += count($idLists['tt_address']);
            }
            if (is_array($idLists['fe_users'])) {
                $count += count($idLists['fe_users']);
            }
            if (is_array($idLists['PLAINLIST'])) {
                $count += count($idLists['PLAINLIST']);
            }
            if (is_array($idLists[$this->userTable])) {
                $count += count($idLists[$this->userTable]);
            }

            $out .= '<tr class="db_list_normal">
					<td nowrap="nowrap">' .$this->iconFactory->getIconForRecord('sys_dmail_group', $row, Icon::SIZE_SMALL)->render().'</td>
					<td>' .$this->editLink('sys_dmail_group', $row['uid']).'</td>
					<td nowrap="nowrap">' .$this->linkRecip_record('<strong>'.htmlspecialchars(GeneralUtility::fixed_lgd_cs($row['title'], 30)).'</strong>&nbsp;&nbsp;', $row['uid']).'</td>
					<td nowrap="nowrap">' .htmlspecialchars(BackendUtility::getProcessedValue('sys_dmail_group', 'type', $row['type'])).'&nbsp;&nbsp;</td>
					<td>' .BackendUtility::getProcessedValue('sys_dmail_group', 'description', htmlspecialchars($row['description'])).'&nbsp;&nbsp;</td>
					<td>' .$count.'</td>
				</tr>';
        }

        $out = ' <table class="table table-striped table-hover">'.$out.'</table>';
        $theOutput = $this->doc->section(BackendUtility::cshItem($this->cshTable, 'select_mailgroup', $GLOBALS['BACK_PATH']).$this->getLanguageService()->getLL('recip_select_mailgroup'), $out, 1, 1, 0, true);

            // New:
        $out = '<a href="#" class="t3-link" onClick="'.BackendUtility::editOnClick('&edit[sys_dmail_group]['.$this->id.']=new', $GLOBALS['BACK_PATH'], '').'">'.
            $this->iconFactory->getIconForRecord('sys_dmail_group', array(), Icon::SIZE_SMALL).
            $this->getLanguageService()->getLL('recip_create_mailgroup_msg').'</a>';
        $theOutput .= '<div style="padding-top: 20px;"></div>';
        $theOutput .= $this->doc->section(BackendUtility::cshItem($this->cshTable, 'create_mailgroup', $GLOBALS['BACK_PATH']).$this->getLanguageService()->getLL('recip_create_mailgroup'), $out, 1, 0, false, true, false, true);

            // Import
        $out = '<a class="t3-link" href="'.BackendUtility::getModuleUrl('DirectMailNavFrame_RecipientList').'&id='.$this->id.'&CMD=displayImport">'.$this->getLanguageService()->getLL('recip_import_mailgroup_msg').'</a>';
        $theOutput .= '<div style="padding-top: 20px;"></div>';
        $theOutput .= $this->doc->section($this->getLanguageService()->getLL('mailgroup_import'), $out, 1, 1, 0, true);

        return $theOutput;
    }

    /**
     * Shows edit link.
     *
     * @param string $table Table name
     * @param int    $uid   Record uid
     *
     * @return string the edit link
     */
    public function editLink($table, $uid)
    {
        $str = '';

        // check if the user has the right to modify the table
        if ($GLOBALS['BE_USER']->check('tables_modify', $table)) {
            $params = '&edit['.$table.']['.$uid.']=edit';
            $str = '<a href="#" onClick="'.BackendUtility::editOnClick($params, $GLOBALS['BACK_PATH'], '').'" title="'.$this->getLanguageService()->getLL('dmail_edit').'">'.
                $this->iconFactory->getIcon('actions-open', Icon::SIZE_SMALL).
                '</a>';
        }

        return $str;
    }

    /**
     * Shows link to show the recipient infos.
     *
     * @param string $str Name of the recipient link
     * @param int    $uid Uid of the recipient link
     *
     * @return string The link
     */
    public function linkRecip_record($str, $uid)
    {
        return '<a href="'.BackendUtility::getModuleUrl('DirectMailNavFrame_RecipientList').'&id='.$this->id.'&CMD=displayMailGroup&group_uid='.$uid.'&SET[dmail_mode]=recip">'.$str.'</a>';
    }

    /**
     * Put all recipients uid from all table into an array.
     *
     * @param int $groupUid Uid of the group
     *
     * @return array List of the uid in an array
     */
    public function cmd_compileMailGroup($groupUid)
    {
        $idLists = array();
        if ($groupUid) {
            $mailGroup = BackendUtility::getRecord('sys_dmail_group', $groupUid);
            if (is_array($mailGroup) && $mailGroup['pid'] == $this->id) {
                switch ($mailGroup['type']) {
                    case 0:
                        // From pages
                        // use current page if no else
                        $thePages = $mailGroup['pages'] ? $mailGroup['pages'] : $this->id;
                        // Explode the pages
                        $pages = GeneralUtility::intExplode(',', $thePages);
                        $pageIdArray = array();
                        foreach ($pages as $pageUid) {
                            if ($pageUid > 0) {
                                $pageinfo = BackendUtility::readPageAccess($pageUid, $this->perms_clause);
                                if (is_array($pageinfo)) {
                                    $info['fromPages'][] = $pageinfo;
                                    $pageIdArray[] = $pageUid;
                                    if ($mailGroup['recursive']) {
                                        $pageIdArray = array_merge($pageIdArray, DirectMailUtility::getRecursiveSelect($pageUid, $this->perms_clause));
                                    }
                                }
                            }
                        }
                            // Remove any duplicates
                        $pageIdArray = array_unique($pageIdArray);
                        $pidList = implode(',', $pageIdArray);
                        $info['recursive'] = $mailGroup['recursive'];

                            // Make queries
                        if ($pidList) {
                            $whichTables = intval($mailGroup['whichtables']);
                            // tt_address
                            if ($whichTables & 1) {
                                $idLists['tt_address'] = DirectMailUtility::getIdList('tt_address', $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            // fe_users
                            if ($whichTables & 2) {
                                $idLists['fe_users'] = DirectMailUtility::getIdList('fe_users', $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            // user table
                            if ($this->userTable && ($whichTables & 4)) {
                                $idLists[$this->userTable] = DirectMailUtility::getIdList($this->userTable, $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            // fe_groups
                            if ($whichTables & 8) {
                                if (!is_array($idLists['fe_users'])) {
                                    $idLists['fe_users'] = array();
                                }
                                $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users'], DirectMailUtility::getIdList('fe_groups', $pidList, $groupUid, $mailGroup['select_categories'])));
                            }
                        }
                        break;
                    case 1:
                        // List of mails
                        if ($mailGroup['csv'] == 1) {
                            $recipients = DirectMailUtility::rearrangeCsvValues(DirectMailUtility::getCsvValues($mailGroup['list']), $this->fieldList);
                        } else {
                            $recipients = DirectMailUtility::rearrangePlainMails(array_unique(preg_split('|[[:space:],;]+|', $mailGroup['list'])));
                        }
                        $idLists['PLAINLIST'] = DirectMailUtility::cleanPlainList($recipients);
                        break;
                    case 2:
                        // Static MM list
                        $idLists['tt_address'] = DirectMailUtility::getStaticIdList('tt_address', $groupUid);
                        $idLists['fe_users'] = DirectMailUtility::getStaticIdList('fe_users', $groupUid);
                        $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users'], DirectMailUtility::getStaticIdList('fe_groups', $groupUid)));
                        if ($this->userTable) {
                            $idLists[$this->userTable] = DirectMailUtility::getStaticIdList($this->userTable, $groupUid);
                        }
                        break;
                    case 3:
                        // Special query list
                        $mailGroup = $this->update_SpecialQuery($mailGroup);
                        $whichTables = intval($mailGroup['whichtables']);
                        $table = '';
                        if ($whichTables & 1) {
                            $table = 'tt_address';
                        } elseif ($whichTables & 2) {
                            $table = 'fe_users';
                        } elseif ($this->userTable && ($whichTables & 4)) {
                            $table = $this->userTable;
                        }
                        if ($table) {
                            $idLists[$table] = DirectMailUtility::getSpecialQueryIdList($this->queryGenerator, $table, $mailGroup);
                        }
                        break;
                    case 4:
                        $groups = array_unique(DirectMailUtility::getMailGroups($mailGroup['mail_groups'], array($mailGroup['uid']), $this->perms_clause));
                        foreach ($groups as $group) {
                            $collect = $this->cmd_compileMailGroup($group);
                            if (is_array($collect['queryInfo']['id_lists'])) {
                                $idLists = array_merge_recursive($idLists, $collect['queryInfo']['id_lists']);
                            }
                        }

                            // Make unique entries
                        if (is_array($idLists['tt_address'])) {
                            $idLists['tt_address'] = array_unique($idLists['tt_address']);
                        }
                        if (is_array($idLists['fe_users'])) {
                            $idLists['fe_users'] = array_unique($idLists['fe_users']);
                        }
                        if (is_array($idLists[$this->userTable]) && $this->userTable) {
                            $idLists[$this->userTable] = array_unique($idLists[$this->userTable]);
                        }
                        if (is_array($idLists['PLAINLIST'])) {
                            $idLists['PLAINLIST'] = DirectMailUtility::cleanPlainList($idLists['PLAINLIST']);
                        }
                        break;
                    default:
                }
            }
        }

        /*
        * Hook for cmd_compileMailGroup
        * manipulate the generated id_lists
        */
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod3']['cmd_compileMailGroup'])) {
            $hookObjectsArr = array();

            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod3']['cmd_compileMailGroup'] as $classRef) {
                $hookObjectsArr[] = &GeneralUtility::getUserObj($classRef);
            }
            foreach ($hookObjectsArr as $hookObj) {
                if (method_exists($hookObj, 'cmd_compileMailGroup_postProcess')) {
                    $temporaryList = $hookObj->cmd_compileMailGroup_postProcess($idLists, $this, $mailGroup);
                }
            }

            unset($idLists);
            $idLists = $temporaryList;
        }

        return array(
            'queryInfo' => array('id_lists' => $idLists),
        );
    }

    /**
     * Display infos of the mail group.
     *
     * @param array $result Array containing list of recipient uid
     *
     * @return string list of all recipient (HTML)
     */
    public function cmd_displayMailGroup($result)
    {
        $totalRecipients = 0;
        $idLists = $result['queryInfo']['id_lists'];
        if (is_array($idLists['tt_address'])) {
            $totalRecipients += count($idLists['tt_address']);
        }
        if (is_array($idLists['fe_users'])) {
            $totalRecipients += count($idLists['fe_users']);
        }
        if (is_array($idLists['PLAINLIST'])) {
            $totalRecipients += count($idLists['PLAINLIST']);
        }
        if (is_array($idLists[$this->userTable])) {
            $totalRecipients += count($idLists[$this->userTable]);
        }

        $group = BackendUtility::getRecord('sys_dmail_group', intval(GeneralUtility::_GP('group_uid')));
        $out = $this->iconFactory->getIconForRecord('sys_dmail_group', $group, Icon::SIZE_SMALL).htmlspecialchars($group['title']);

        $lCmd = GeneralUtility::_GP('lCmd');

        $mainC = $this->getLanguageService()->getLL('mailgroup_recip_number').' <strong>'.$totalRecipients.'</strong>';
        if (!$lCmd) {
            $mainC .= '<br /><br /><strong><a class="t3-link" href="'.GeneralUtility::linkThisScript(array('lCmd' => 'listall')).'">'.$this->getLanguageService()->getLL('mailgroup_list_all').'</a></strong>';
        }

        $theOutput = $this->doc->section($this->getLanguageService()->getLL('mailgroup_recip_from').' '.$out, $mainC, 1, 1, 0, true);
        $theOutput .= '<div style="padding-top: 20px;"></div>';

            // do the CSV export
        $csvValue = GeneralUtility::_GP('csv');
        if ($csvValue) {
            if ($csvValue == 'PLAINLIST') {
                $this->downloadCSV($idLists['PLAINLIST']);
            } elseif (GeneralUtility::inList('tt_address,fe_users,'.$this->userTable, $csvValue)) {
                $this->downloadCSV(DirectMailUtility::fetchRecordsListValues($idLists[$csvValue], $csvValue, (($csvValue == 'fe_users') ? str_replace('phone', 'telephone', $this->fieldList) : $this->fieldList).',tstamp'));
            }
        }

        switch ($lCmd) {
            case 'listall':
                if (is_array($idLists['tt_address'])) {
                    $theOutput .= $this->doc->section($this->getLanguageService()->getLL('mailgroup_table_address'), DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address', $this->id));
                    $theOutput .= '<div style="padding-top: 20px;"></div>';
                }
                if (is_array($idLists['fe_users'])) {
                    $theOutput .= $this->doc->section($this->getLanguageService()->getLL('mailgroup_table_fe_users'), DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users', $this->id));
                    $theOutput .= '<div style="padding-top: 20px;"></div>';
                }
                if (is_array($idLists['PLAINLIST'])) {
                    $theOutput .= $this->doc->section($this->getLanguageService()->getLL('mailgroup_plain_list'), DirectMailUtility::getRecordList($idLists['PLAINLIST'], 'default', $this->id));
                    $theOutput .= '<div style="padding-top: 20px;"></div>';
                }
                if (is_array($idLists[$this->userTable])) {
                    $theOutput .= $this->doc->section($this->getLanguageService()->getLL('mailgroup_table_custom').' '.$this->userTable, DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists[$this->userTable], $this->userTable), $this->userTable, $this->id));
                }
                break;
            default:

                if (is_array($idLists['tt_address']) && count($idLists['tt_address'])) {
                    $recipContent = $this->getLanguageService()->getLL('mailgroup_recip_number').' '.count($idLists['tt_address']).'<br /><a href="'.GeneralUtility::linkThisScript(array('csv' => 'tt_address')).'" class="t3-link">'.$this->getLanguageService()->getLL('mailgroup_download').'</a>';
                    $theOutput .= $this->doc->section($this->getLanguageService()->getLL('mailgroup_table_address'), $recipContent);
                    $theOutput .= '<div style="padding-top: 20px;"></div>';
                }

                if (is_array($idLists['fe_users']) && count($idLists['fe_users'])) {
                    $recipContent = $this->getLanguageService()->getLL('mailgroup_recip_number').' '.count($idLists['fe_users']).'<br /><a href="'.GeneralUtility::linkThisScript(array('csv' => 'fe_users')).'" class="t3-link">'.$this->getLanguageService()->getLL('mailgroup_download').'</a>';
                    $theOutput .= $this->doc->section($this->getLanguageService()->getLL('mailgroup_table_fe_users'), $recipContent);
                    $theOutput .= '<div style="padding-top: 20px;"></div>';
                }

                if (is_array($idLists['PLAINLIST']) && count($idLists['PLAINLIST'])) {
                    $recipContent = $this->getLanguageService()->getLL('mailgroup_recip_number').' '.count($idLists['PLAINLIST']).'<br /><a href="'.GeneralUtility::linkThisScript(array('csv' => 'PLAINLIST')).'" class="t3-link">'.$this->getLanguageService()->getLL('mailgroup_download').'</a>';
                    $theOutput .= $this->doc->section($this->getLanguageService()->getLL('mailgroup_plain_list'), $recipContent);
                    $theOutput .= '<div style="padding-top: 20px;"></div>';
                }

                if (is_array($idLists[$this->userTable]) && count($idLists[$this->userTable])) {
                    $recipContent = $this->getLanguageService()->getLL('mailgroup_recip_number').' '.count($idLists[$this->userTable]).'<br /><a href="'.GeneralUtility::linkThisScript(array('csv' => $this->userTable)).'" class="t3-link">'.$this->getLanguageService()->getLL('mailgroup_download').'</a>';
                    $theOutput .= $this->doc->section($this->getLanguageService()->getLL('mailgroup_table_custom'), $recipContent);
                    $theOutput .= '<div style="padding-top: 20px;"></div>';
                }

                if ($group['type'] == 3) {
                    if ($GLOBALS['BE_USER']->check('tables_modify', 'sys_dmail_group')) {
                        $theOutput .= $this->cmd_specialQuery($group);
                    }
                }
        }

        return $theOutput;
    }

    /**
     * Update recipient list record with a special query.
     *
     * @param array $mailGroup DB records
     *
     * @return array Updated DB records
     */
    public function update_specialQuery($mailGroup)
    {
        $set = GeneralUtility::_GP('SET');
        $queryTable = $set['queryTable'];
        $queryConfig = GeneralUtility::_GP('dmail_queryConfig');
        $dmailUpdateQuery = GeneralUtility::_GP('dmailUpdateQuery');

        $whichTables = intval($mailGroup['whichtables']);
        $table = '';
        if ($whichTables & 1) {
            $table = 'tt_address';
        } elseif ($whichTables & 2) {
            $table = 'fe_users';
        } elseif ($this->userTable && ($whichTables & 4)) {
            $table = $this->userTable;
        }

        $this->MOD_SETTINGS['queryTable'] = $queryTable ? $queryTable : $table;
        $this->MOD_SETTINGS['queryConfig'] = $queryConfig ? serialize($queryConfig) : $mailGroup['query'];
        $this->MOD_SETTINGS['search_query_smallparts'] = 1;

        if ($this->MOD_SETTINGS['queryTable'] != $table) {
            $this->MOD_SETTINGS['queryConfig'] = '';
        }

        if ($this->MOD_SETTINGS['queryTable'] != $table || $this->MOD_SETTINGS['queryConfig'] != $mailGroup['query']) {
            $whichTables = 0;
            if ($this->MOD_SETTINGS['queryTable'] == 'tt_address') {
                $whichTables = 1;
            } elseif ($this->MOD_SETTINGS['queryTable'] == 'fe_users') {
                $whichTables = 2;
            } elseif ($this->MOD_SETTINGS['queryTable'] == $this->userTable) {
                $whichTables = 4;
            }
            $updateFields = array(
                'whichtables' => intval($whichTables),
                'query' => $this->MOD_SETTINGS['queryConfig'],
            );

            $res_update = $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
                'sys_dmail_group',
                'uid='.intval($mailGroup['uid']),
                $updateFields
                );
            $mailGroup = BackendUtility::getRecord('sys_dmail_group', $mailGroup['uid']);
        }

        return $mailGroup;
    }

    /**
     * Show HTML form to make special query.
     *
     * @param array $mailGroup Recipient list DB record
     *
     * @return string HTML form to make a special query
     */
    public function cmd_specialQuery($mailGroup)
    {
        $out = '';
        $this->queryGenerator->init('dmail_queryConfig', $this->MOD_SETTINGS['queryTable']);

        if ($this->MOD_SETTINGS['queryTable'] && $this->MOD_SETTINGS['queryConfig']) {
            $this->queryGenerator->queryConfig = $this->queryGenerator->cleanUpQueryConfig(unserialize($this->MOD_SETTINGS['queryConfig']));
            $this->queryGenerator->extFieldLists['queryFields'] = 'uid';
            $out .= $this->queryGenerator->getSelectQuery();
            $out .= '<div style="padding-top: 20px;"></div>';
        }

        $this->queryGenerator->setFormName($this->formname);
        $this->queryGenerator->noWrap = '';
        $this->queryGenerator->allowedTables = $this->allowedTables;
        $tmpCode = $this->queryGenerator->makeSelectorTable($this->MOD_SETTINGS, 'table,query');
        $tmpCode .= '<input type="hidden" name="CMD" value="displayMailGroup" /><input type="hidden" name="group_uid" value="'.$mailGroup['uid'].'" />';
        $tmpCode .= '<input type="submit" value="'.$this->getLanguageService()->getLL('dmail_updateQuery').'" />';
        $out .= $this->doc->section($this->getLanguageService()->getLL('dmail_makeQuery'), $tmpCode);

        $theOutput = '<div style="padding-top: 20px;"></div>';
        $theOutput .= $this->doc->section($this->getLanguageService()->getLL('dmail_query'), $out);

        return $theOutput;
    }

    /**
     * Send csv values as download by sending appropriate HTML header.
     *
     * @param array $idArr Values to be put into csv
     */
    public function downloadCSV(array $idArr)
    {
        $lines = array();
        if (is_array($idArr) && count($idArr)) {
            reset($idArr);
            $lines[] = GeneralUtility::csvValues(array_keys(current($idArr)), ',', '');

            reset($idArr);
            foreach ($idArr as $rec) {
                $lines[] = GeneralUtility::csvValues($rec);
            }
        }

        $filename = 'DirectMail_export_'.date('dmy-Hi').'.csv';
        $mimeType = 'application/octet-stream';
        header('Content-Type: '.$mimeType);
        header('Content-Disposition: attachment; filename='.$filename);
        echo implode(CR.LF, $lines);
        exit;
    }

    /**
     * Shows user's info and categories.
     *
     * @return string HTML showing user's info and the categories
     */
    public function cmd_displayUserInfo()
    {
        $uid = intval(GeneralUtility::_GP('uid'));
        $indata = GeneralUtility::_GP('indata');
        $table = GeneralUtility::_GP('table');

        $mmTable = $GLOBALS['TCA'][$table]['columns']['module_sys_dmail_category']['config']['MM'];

        if (GeneralUtility::_GP('submit')) {
            $indata = GeneralUtility::_GP('indata');
            if (!$indata) {
                $indata['html'] = 0;
            }
        }

        switch ($table) {
            case 'tt_address':
                // see fe_users
            case 'fe_users':
                if (is_array($indata)) {
                    $data = array();
                    if (is_array($indata['categories'])) {
                        reset($indata['categories']);
                        foreach ($indata['categories'] as $recValues) {
                            reset($recValues);
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
                    /* @var $tce \TYPO3\CMS\Core\DataHandling\DataHandler*/
                    $tce = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\DataHandling\\DataHandler');
                    $tce->stripslashes_values = 0;
                    $tce->start($data, array());
                    $tce->process_datamap();
                }
                break;
            default:
                // do nothing
        }

        switch ($table) {
            case 'tt_address':
                $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
                    'tt_address.*',
                    'tt_address LEFT JOIN pages ON pages.uid=tt_address.pid',
                    'tt_address.uid='.intval($uid).
                        ' AND '.$this->perms_clause.
                        BackendUtility::deleteClause('pages').
                        BackendUtility::BEenableFields('tt_address').
                        BackendUtility::deleteClause('tt_address')
                    );
                $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
                break;
            case 'fe_users':
                $res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
                    'fe_users.*',
                    'fe_users LEFT JOIN pages ON pages.uid=fe_users.pid',
                    'fe_users.uid='.intval($uid).
                        ' AND '.$this->perms_clause.
                        BackendUtility::deleteClause('pages').
                        BackendUtility::BEenableFields('fe_users').
                        BackendUtility::deleteClause('fe_users')
                    );
                $row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res);
                break;
            default:
                // do nothing
        }

        $theOutput = '';

        if (is_array($row)) {
            $resCat = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
                'uid_foreign',
                $mmTable,
                'uid_local='.$row['uid']
                );

            while (($rowCat = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resCat))) {
                $categoriesArray[] = $rowCat['uid_foreign'];
            }
            $categories = implode($categoriesArray, ',');

            $editParams = '&edit['.$table.']['.$row['uid'].']=edit';

            $out = '';
            $out .= $this->iconFactory->getIconForRecord($table, $row)->render().htmlspecialchars($row['name']).htmlspecialchars(' <'.$row['email'].'>');
            $out .= '&nbsp;&nbsp;<a href="#" onClick="'.BackendUtility::editOnClick($editParams, $GLOBALS['BACK_PATH'], '').'" title="'.$this->getLanguageService()->getLL('dmail_edit').'">'.
                $this->iconFactory->getIcon('actions-open', Icon::SIZE_SMALL).
                '<b>'.$this->getLanguageService()->getLL('dmail_edit').'</b></a>';
            $theOutput = $this->doc->section($this->getLanguageService()->getLL('subscriber_info'), $out);

            $out = '';
            $outCheckBox = '';

            $this->categories = DirectMailUtility::makeCategories($table, $row, $this->sys_language_uid);

            reset($this->categories);
            foreach ($this->categories as $pKey => $pVal) {
                $outCheckBox .= '<input type="hidden" name="indata[categories]['.$row['uid'].']['.$pKey.']" value="0" />'.
                '<input type="checkbox" name="indata[categories]['.$row['uid'].']['.$pKey.']" value="1"'.(GeneralUtility::inList($categories, $pKey) ? ' checked="checked"' : '').' /> '.htmlspecialchars($pVal).'<br />';
            }
            $outCheckBox .= '<br /><br /><input type="checkbox" name="indata[html]" value="1"'.($row['module_sys_dmail_html'] ? ' checked="checked"' : '').' /> ';
            $outCheckBox .= $this->getLanguageService()->getLL('subscriber_profile_htmlemail').'<br />';
            $out .= $outCheckBox;

            $out .= '<input type="hidden" name="table" value="'.$table.'" />'.
                '<input type="hidden" name="uid" value="'.$uid.'" />'.
                '<input type="hidden" name="CMD" value="'.$this->CMD.'" />'.
                '<br /><input type="submit" name="submit" value="'.htmlspecialchars($this->getLanguageService()->getLL('subscriber_profile_update')).'" />';
            $theOutput .= '<div style="padding-top: 20px;"></div>';
            $theOutput .= $this->doc->section($this->getLanguageService()->getLL('subscriber_profile'), $this->getLanguageService()->getLL('subscriber_profile_instructions').'<br /><br />'.$out);
        }

        return $theOutput;
    }

    /**
     * Returns LanguageService.
     *
     * @return \TYPO3\CMS\Lang\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
