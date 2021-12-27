<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Module;

use DirectMailTeam\DirectMail\Importer;
use DirectMailTeam\DirectMail\MailSelect;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\CsvUtility;
use DirectMailTeam\DirectMail\DirectMailUtility;

class RecipientListController extends MainController
{
    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = '';
    
    protected int $group_uid = 0;
    protected string $lCmd = '';
    protected string $csv = '';
    protected array $set = [];
    protected string $fieldList = 'uid,name,first_name,middle_name,last_name,title,email,phone,www,address,company,city,zip,country,fax,module_sys_dmail_category,module_sys_dmail_html';
    
    protected function initRecipientList(ServerRequestInterface $request): void {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();
        
        $this->group_uid = (int)($parsedBody['group_uid'] ?? $queryParams['group_uid'] ?? 0);
        $this->lCmd = $parsedBody['lCmd'] ?? $queryParams['lCmd'] ?? '';
        $this->csv = $parsedBody['csv'] ?? $queryParams['csv'] ?? '';
        $this->set = is_array($parsedBody['csv'] ?? '') ? $parsedBody['csv'] : (is_array($queryParams['csv'] ?? '') ? $queryParams['csv'] : []);
    }
    
    public function indexAction(ServerRequestInterface $request) : ResponseInterface
    {
        $this->view = $this->configureTemplatePaths('RecipientList');
        
        $this->init($request);
        $this->initRecipientList($request);
        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');
        
        if (($this->id && $this->access) || ($this->isAdmin() && !$this->id)) {
            $module = $this->getModulName();
            $this->moduleName = (string)($request->getQueryParams()['currentModule'] ?? $request->getParsedBody()['currentModule'] ?? 'DirectMailNavFrame_RecipientList');

            if ($module == 'dmail') {
                // Direct mail module
                if (($this->pageinfo['doktype'] ?? 0) == 254) {
                    $formcontent = $this->moduleContent();
                    $this->view->assignMultiple(
                        [
                            'formcontent' => $formcontent,
                            'show' => true
                        ]
                    );
                }
                elseif ($this->id != 0) {
                    $message = $this->createFlashMessage($this->getLanguageService()->getLL('dmail_noRegular'), $this->getLanguageService()->getLL('dmail_newsletters'), 1, false);
                    $this->messageQueue->addMessage($message);
                }
            }
            else {
                $message = $this->createFlashMessage($this->getLanguageService()->getLL('select_folder'), $this->getLanguageService()->getLL('header_recip'), 1, false);
                $this->messageQueue->addMessage($message);
            }
        }
        else {
            // If no access or if ID == zero
            $this->view = $this->configureTemplatePaths('NoAccess');
            $message = $this->createFlashMessage('If no access or if ID == zero', 'No Access', 1, false);
            $this->messageQueue->addMessage($message);
        }
    
        /**
         * Render template and return html content
         */
        $this->moduleTemplate->setContent($this->view->render());
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }
    
    /**
     * Show the module content
     *
     * @return string The compiled content of the module.
     */
    protected function moduleContent()
    {
        // COMMAND:
        switch ($this->cmd) {
            case 'displayUserInfo': //@TODO ???
                $theOutput = $this->cmd_displayUserInfo();
                break;
            case 'displayMailGroup':
                $result = $this->cmd_compileMailGroup($this->group_uid);
                $theOutput = $this->cmd_displayMailGroup($result);
                break;
            case 'displayImport':
                /* @var $importer \DirectMailTeam\DirectMail\Importer */
                $importer = GeneralUtility::makeInstance(Importer::class);
                $importer->init($this);
                $theOutput = $importer->cmd_displayImport();
                break;
            default:
                $theOutput = $this->showExistingRecipientLists();
        }
        
        return $theOutput;
    }
    
    /**
     * Shows the existing recipient lists and shows link to create a new one or import a list
     *
     * @return string List of existing recipient list, link to create a new list and link to import
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function showExistingRecipientLists()
    {
        
        $out = '<thead>
					<th colspan="2">&nbsp;</th>
                    <th>' . $this->getLanguageService()->sL(BackendUtility::getItemLabel('sys_dmail_group', 'title')) . '</th>
                    <th>' . $this->getLanguageService()->sL(BackendUtility::getItemLabel('sys_dmail_group', 'type')) . '</th>
					<th>' . $this->getLanguageService()->sL(BackendUtility::getItemLabel('sys_dmail_group', 'description')) . '</th>
					<th>' . $this->getLanguageService()->getLL('recip_group_amount') . '</th>
				</thead>';
        
        $queryBuilder = $this->getQueryBuilder('sys_dmail_group');
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $res = $queryBuilder->select('uid','pid','title','description','type')
        ->from('sys_dmail_group')
        ->where(
            $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($this->id,\PDO::PARAM_INT))
            )
            ->orderBy(
                preg_replace(
                    '/^(?:ORDER[[:space:]]*BY[[:space:]]*)+/i', '',
                    trim($GLOBALS['TCA']['sys_dmail_group']['ctrl']['default_sortby'])
                    )
                )
                ->execute()
                ->fetchAll();
                
        foreach($res as $row) {
            $result = $this->cmd_compileMailGroup(intval($row['uid']));
            $count = 0;
            $idLists = $result['queryInfo']['id_lists'];
            if (is_array($idLists['tt_address'] ?? false)) {
                $count += count($idLists['tt_address']);
            }
            if (is_array($idLists['fe_users'] ?? false)) {
                $count += count($idLists['fe_users']);
            }
            if (is_array($idLists['PLAINLIST'] ?? false)) {
                $count += count($idLists['PLAINLIST']);
            }
            if (is_array($idLists[$this->userTable] ?? false)) {
                $count += count($idLists[$this->userTable]);
            }
            
            $out .= '<tr class="db_list_normal">
			<td nowrap="nowrap">' .  $this->iconFactory->getIconForRecord('sys_dmail_group', $row, Icon::SIZE_SMALL)->render() . '</td>
			<td>' . $this->editLink('sys_dmail_group', $row['uid']) . '</td>
			<td nowrap="nowrap">' . $this->linkRecip_record('<strong>' . htmlspecialchars(GeneralUtility::fixed_lgd_cs($row['title'], 30)) . '</strong>&nbsp;&nbsp;', $row['uid']) . '</td>
			<td nowrap="nowrap">' . htmlspecialchars(BackendUtility::getProcessedValue('sys_dmail_group', 'type', $row['type'])) . '&nbsp;&nbsp;</td>
			<td>' . BackendUtility::getProcessedValue('sys_dmail_group', 'description', htmlspecialchars($row['description'])) . '&nbsp;&nbsp;</td>
			<td>' . $count . '</td>
		</tr>';
        }
                
        $out = ' <table class="table table-striped table-hover">' . $out . '</table>';
        $theOutput = '<h3>' . $this->getLanguageService()->getLL('recip_select_mailgroup') . '</h3>' .
            $out;
            
            $editOnClickLink = DirectMailUtility::getEditOnClickLink([
                'edit' => [
                    'sys_dmail_group' => [
                        $this->id => 'new'
                    ]
                ],
                'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI'),
            ]);
                    
        // New:
        $out = '<a href="#" class="t3-link" onClick="' . $editOnClickLink . '">' .
            $this->iconFactory->getIconForRecord('sys_dmail_group', [], Icon::SIZE_SMALL) .
            $this->getLanguageService()->getLL('recip_create_mailgroup_msg') . '</a>';
            $theOutput .= '<div style="padding-top: 20px;"></div>';
            $theOutput .= '<h3>' . $this->getLanguageService()->getLL('recip_select_mailgroup') . '</h3>' .
                $out;
            
        // Import
        $moduleUrl = $this->buildUriFromRoute(
            $this->moduleName,
            [
                'id' => $this->id,
                'cmd' => 'displayImport'
            ]
        );
        $out = '<a class="t3-link" href="' . $moduleUrl . '">' . $this->getLanguageService()->getLL('recip_import_mailgroup_msg') . '</a>';
        $theOutput .= '<div style="padding-top: 20px;"></div>';
        $theOutput .= '<h3>' . $this->getLanguageService()->getLL('mailgroup_import') . '</h3>' . $out;
        return $theOutput;
    }
    
    /**
     * Put all recipients uid from all table into an array
     *
     * @param int $groupUid Uid of the group
     *
     * @return	array List of the uid in an array
     */
    protected function cmd_compileMailGroup(int $groupUid)
    {
        $idLists = [];
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
                        $pageIdArray = [];
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
                            if ($whichTables&1) {
                                $idLists['tt_address'] = DirectMailUtility::getIdList('tt_address', $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            // fe_users
                            if ($whichTables&2) {
                                $idLists['fe_users'] = DirectMailUtility::getIdList('fe_users', $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            // user table
                            if ($this->userTable && ($whichTables&4)) {
                                $idLists[$this->userTable] = DirectMailUtility::getIdList($this->userTable, $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            // fe_groups
                            if ($whichTables&8) {
                                if (!is_array($idLists['fe_users'])) {
                                    $idLists['fe_users'] = [];
                                }
                                $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users'], DirectMailUtility::getIdList('fe_groups', $pidList, $groupUid, $mailGroup['select_categories'])));
                            }
                        }
                        break;
                    case 1:
                        // List of mails
                        if ($mailGroup['csv']==1) {
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
                        if ($whichTables&1) {
                            $table = 'tt_address';
                        } elseif ($whichTables&2) {
                            $table = 'fe_users';
                        } elseif ($this->userTable && ($whichTables&4)) {
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
        /**
         * Hook for cmd_compileMailGroup
         * manipulate the generated id_lists
         */
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod3']['cmd_compileMailGroup'] ?? false)) {
            $hookObjectsArr = [];
            
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod3']['cmd_compileMailGroup'] as $classRef) {
                $hookObjectsArr[] = GeneralUtility::makeInstance($classRef);
            }
            foreach ($hookObjectsArr as $hookObj) {
                if (method_exists($hookObj, 'cmd_compileMailGroup_postProcess')) {
                    $temporaryList = $hookObj->cmd_compileMailGroup_postProcess($idLists, $this, $mailGroup);
                }
            }
            
            unset($idLists);
            $idLists = $temporaryList;
        }
        
        return [
            'queryInfo' => ['id_lists' => $idLists]
        ];
    }
    
    /**
     * Shows edit link
     *
     * @param string $table Table name
     * @param int $uid Record uid
     *
     * @return string the edit link
     */
    protected function editLink($table, $uid)
    {
        $str = '';
        
        // check if the user has the right to modify the table
        if ($this->getBackendUser()->check('tables_modify', $table)) {
            $editOnClickLink = DirectMailUtility::getEditOnClickLink([
                'edit' => [
                    $table => [
                        $uid => 'edit',
                    ],
                ],
                'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI'),
            ]);
            $str = '<a href="#" onClick="' . $editOnClickLink . '" title="' . $this->getLanguageService()->getLL('dmail_edit') . '">' .
                $this->iconFactory->getIcon('actions-open', Icon::SIZE_SMALL) .
                '</a>';
        }
        
        return $str;
    }
    
    /**
     * Shows link to show the recipient infos
     *
     * @param string $str Name of the recipient link
     * @param int $uid Uid of the recipient link
     *
     * @return string The link
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function linkRecip_record($str, $uid)
    {
        $moduleUrl = $this->buildUriFromRoute(
            $this->moduleName,
            [
                'id' => $this->id,
                'group_uid' => $uid,
                'cmd' => 'displayMailGroup',
                'SET[dmail_mode]' => 'recip'
            ]
        );
        return '<a href="' . $moduleUrl . '">' . $str . '</a>';
    }
    
    /**
     * Display infos of the mail group
     *
     * @param array $result Array containing list of recipient uid
     *
     * @return string list of all recipient (HTML)
     */
    protected function cmd_displayMailGroup($result)
    {
        $totalRecipients = 0;
        $idLists = $result['queryInfo']['id_lists'];
        if (is_array($idLists['tt_address'] ?? false)) {
            $totalRecipients += count($idLists['tt_address']);
        }
        if (is_array($idLists['fe_users'] ?? false)) {
            $totalRecipients += count($idLists['fe_users']);
        }
        if (is_array($idLists['PLAINLIST'] ?? false)) {
            $totalRecipients += count($idLists['PLAINLIST']);
        }
        if (is_array($idLists[$this->userTable] ?? false)) {
            $totalRecipients += count($idLists[$this->userTable]);
        }

        $group = BackendUtility::getRecord('sys_dmail_group', $this->group_uid);
        $out = $this->iconFactory->getIconForRecord('sys_dmail_group', $group, Icon::SIZE_SMALL) . htmlspecialchars($group['title']);

        $mainC = $this->getLanguageService()->getLL('mailgroup_recip_number') . ' <strong>' . $totalRecipients . '</strong>';
        if (!$this->lCmd) {
            $mainC.= '<br /><br /><strong><a class="t3-link" href="' . GeneralUtility::linkThisScript(['lCmd'=>'listall']) . '">' . $this->getLanguageService()->getLL('mailgroup_list_all') . '</a></strong>';
        }
        
        $theOutput = '<h3>' . $this->getLanguageService()->getLL('mailgroup_recip_from') . ' ' . $out . '</h3>' . $mainC;
        $theOutput .= '<div style="padding-top: 20px;"></div>';

        // do the CSV export
        $csvValue = $this->csv;
        if ($csvValue) {
            if ($csvValue == 'PLAINLIST') {
                $this->downloadCSV($idLists['PLAINLIST']);
            } 
            elseif (GeneralUtility::inList('tt_address,fe_users,' . $this->userTable, $csvValue)) {
                if($this->getBackendUser()->check('tables_select', $csvValue)) {
                    $this->downloadCSV(DirectMailUtility::fetchRecordsListValues($idLists[$csvValue], $csvValue, (($csvValue == 'fe_users') ? str_replace('phone', 'telephone', $this->fieldList) : $this->fieldList) . ',tstamp'));
                } 
                else {
                    $message = $this->createFlashMessage(
                        '', 
                        $this->getLanguageService()->getLL('mailgroup_table_disallowed_csv'), 
                        2, 
                        false
                    );
                    $this->messageQueue->addMessage($message);
                }
            }
        }
            
        switch ($this->lCmd) {
            case 'listall':
                if (is_array($idLists['tt_address'])) {
                    $theOutput.= '<h3>' . $this->getLanguageService()->getLL('mailgroup_table_address') . '</h3>' .
                        DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address', $this->id);
                        $theOutput.= '<div style="padding-top: 20px;"></div>';
                }
                if (is_array($idLists['fe_users'] ?? false)) {
                    $theOutput.= '<h3>' . $this->getLanguageService()->getLL('mailgroup_table_fe_users') .'</h3>' .
                        DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users', $this->id);
                        $theOutput.= '<div style="padding-top: 20px;"></div>';
                }
                if (is_array($idLists['PLAINLIST'] ?? false)) {
                    $theOutput.= '<h3>' . $this->getLanguageService()->getLL('mailgroup_plain_list') .'</h3>' .
                        DirectMailUtility::getRecordList($idLists['PLAINLIST'], 'sys_dmail_group', $this->id);
                        $theOutput.= '<div style="padding-top: 20px;"></div>';
                }
                if (is_array($idLists[$this->userTable] ?? false)) {
                    $theOutput.= '<h3>' . $this->getLanguageService()->getLL('mailgroup_table_custom') . ' ' . $this->userTable . '</h3>' .
                        DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists[$this->userTable], $this->userTable), $this->userTable, $this->id);
                }
                break;
            default:
                    
                if (is_array($idLists['tt_address'] ?? false) && count($idLists['tt_address'])) {
                    $recipContent = $this->getLanguageService()->getLL('mailgroup_recip_number') . ' ' . count($idLists['tt_address']) .
                    '<br /><a href="' . GeneralUtility::linkThisScript(['csv'=>'tt_address']) . '" class="t3-link">' . $this->getLanguageService()->getLL('mailgroup_download') . '</a>';
                    $theOutput .= '<h3>' . $this->getLanguageService()->getLL('mailgroup_table_address') .'</h3>' . $recipContent;
                    $theOutput .= '<div style="padding-top: 20px;"></div>';
                }
                
                if (is_array($idLists['fe_users'] ?? false) && count($idLists['fe_users'])) {
                    $recipContent = $this->getLanguageService()->getLL('mailgroup_recip_number') . ' ' . count($idLists['fe_users']) .
                    '<br /><a href="' . GeneralUtility::linkThisScript(['csv'=>'fe_users']) . '" class="t3-link">' . $this->getLanguageService()->getLL('mailgroup_download') . '</a>';
                    $theOutput .= '<h3>' . $this->getLanguageService()->getLL('mailgroup_table_fe_users') . '</h3>' . $recipContent;
                    $theOutput .= '<div style="padding-top: 20px;"></div>';
                }
                        
                if (is_array($idLists['PLAINLIST'] ?? false) && count($idLists['PLAINLIST'])) {
                    $recipContent = $this->getLanguageService()->getLL('mailgroup_recip_number') . ' ' . count($idLists['PLAINLIST']) .
                    '<br /><a href="' . GeneralUtility::linkThisScript(['csv'=>'PLAINLIST']) . '" class="t3-link">' . $this->getLanguageService()->getLL('mailgroup_download') . '</a>';
                    $theOutput .= '<h3>' . $this->getLanguageService()->getLL('mailgroup_plain_list') .'</h3>' . $recipContent;
                    $theOutput .= '<div style="padding-top: 20px;"></div>';
                }
                
                if (is_array($idLists[$this->userTable] ?? false) && count($idLists[$this->userTable])) {
                    $recipContent = $this->getLanguageService()->getLL('mailgroup_recip_number') . ' ' . count($idLists[$this->userTable]) .
                    '<br /><a href="' . GeneralUtility::linkThisScript(['csv' => $this->userTable]) . '" class="t3-link">' . $this->getLanguageService()->getLL('mailgroup_download') . '</a>';
                    $theOutput .= '<h3>' . $this->getLanguageService()->getLL('mailgroup_table_custom') . '</h3>' . $recipContent;
                    $theOutput .= '<div style="padding-top: 20px;"></div>';
                }
                        
                if ($group['type'] == 3) {
                    if ($this->getBackendUser()->check('tables_modify', 'sys_dmail_group')) {
                        $theOutput .= $this->cmd_specialQuery($group);
                    }
                }
        }
        return $theOutput;
    }
    
    /**
     * Update recipient list record with a special query
     *
     * @param array $mailGroup DB records
     *
     * @return array Updated DB records
     */
    protected function update_specialQuery($mailGroup)
    {
        $set = $this->set;
        $queryTable = $set['queryTable'] ?? '';
        $queryConfig = GeneralUtility::_GP('dmail_queryConfig');
        $dmailUpdateQuery = GeneralUtility::_GP('dmailUpdateQuery');
        
        $whichTables = intval($mailGroup['whichtables']);
        $table = '';
        if ($whichTables&1) {
            $table = 'tt_address';
        } elseif ($whichTables&2) {
            $table = 'fe_users';
        } elseif ($this->userTable && ($whichTables&4)) {
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
            $updateFields = [
                'whichtables' => intval($whichTables),
                'query' => $this->MOD_SETTINGS['queryConfig']
            ];
            
            $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
            $connection = $connectionPool->getConnectionForTable('sys_dmail_group');
            
            $connection->update(
                'sys_dmail_group', // table
                $updateFields,
                [ 'uid' => intval($mailGroup['uid']) ] // where
            );
            $mailGroup = BackendUtility::getRecord('sys_dmail_group', $mailGroup['uid']);
        }
        return $mailGroup;
    }
    
    /**
     * Show HTML form to make special query
     *
     * @param array $mailGroup Recipient list DB record
     *
     * @return string HTML form to make a special query
     */
    protected function cmd_specialQuery($mailGroup)
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
        $tmpCode .= '<input type="hidden" name="cmd" value="displayMailGroup" /><input type="hidden" name="group_uid" value="' . $mailGroup['uid'] . '" />';
        $tmpCode .= '<input type="submit" value="' . $this->getLanguageService()->getLL('dmail_updateQuery') . '" />';
        $out .= '<h3>' . $this->getLanguageService()->getLL('dmail_makeQuery') . '</h3>' . $tmpCode;
            
        $theOutput = '<div style="padding-top: 20px;"></div>';
        $theOutput .= '<h3>' . $this->getLanguageService()->getLL('dmail_query') . '</h3>' . $out;
            
        return $theOutput;
    }
    
    /**
     * Send csv values as download by sending appropriate HTML header
     *
     * @param array $idArr Values to be put into csv
     *
     * @return void Sent HML header for a file download
     */
    protected function downloadCSV(array $idArr)
    {
        // https://api.typo3.org/master/class_t_y_p_o3_1_1_c_m_s_1_1_core_1_1_utility_1_1_csv_utility.html
        $lines = [];
        if (is_array($idArr) && count($idArr)) {
            reset($idArr);
            $lines[] = CsvUtility::csvValues(array_keys(current($idArr)));
            
            reset($idArr);
            foreach ($idArr as $rec) {
                $lines[] = CsvUtility::csvValues($rec);
            }
        }
        
        $filename = 'DirectMail_export_' . date('dmy-Hi') . '.csv';
        $mimeType = 'application/octet-stream';
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename=' . $filename);
        echo implode(CR . LF, $lines);
        exit;
    }
    
    /**
     * Shows user's info and categories
     *
     * @return	string HTML showing user's info and the categories
     */
    protected function cmd_displayUserInfo()
    {
        $uid = intval(GeneralUtility::_GP('uid'));
        $indata = GeneralUtility::_GP('indata');
        $table = GeneralUtility::_GP('table');
        
        $mmTable = $GLOBALS['TCA'][$table]['columns']['module_sys_dmail_category']['config']['MM'];
        
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
                    $data=[];
                    if (is_array($indata['categories'])) {
                        reset($indata['categories']);
                        foreach ($indata['categories'] as $recValues) {
                            reset($recValues);
                            $enabled = [];
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
                    $tce = GeneralUtility::makeInstance(DataHandler::class);
                    $tce->stripslashes_values = 0;
                    $tce->start($data, []);
                    $tce->process_datamap();
                }
                break;
            default:
                // do nothing
        }
        
        switch ($table) {
            case 'tt_address':
                $queryBuilder = $this->getQueryBuilder('tt_address');
                $res = $queryBuilder
                ->select('tt_address.*')
                ->from('tt_address')
                ->leftJoin(
                    'tt_address',
                    'pages',
                    'pages',
                    $queryBuilder->expr()->eq('pages.uid', $queryBuilder->quoteIdentifier('tt_address.pid'))
                    )
                    ->add('where','tt_address.uid=' . intval($uid) .
                        ' AND ' . $this->perms_clause )
                        ->execute()
                        ->fetchAll();

                break;
            case 'fe_users':
                $queryBuilder = $this->getQueryBuilder('fe_users');
                $res = $queryBuilder
                ->select('fe_users.*')
                ->from('fe_users')
                ->leftJoin(
                    'fe_users',
                    'pages',
                    'pages',
                    $queryBuilder->expr()->eq('pages.uid', $queryBuilder->quoteIdentifier('fe_users.pid'))
                    )
                    ->add('where','fe_users.uid=' . intval($uid) .
                        ' AND ' . $this->perms_clause )
                        ->execute()
                        ->fetchAll();
                        
                        break;
            default:
                // do nothing
        }
        
        $theOutput = '';
        
        if (is_array($res)) {
            foreach($res as $row){
                $queryBuilder = $this->getQueryBuilder($mmTable);
                $resCat = $queryBuilder
                ->select('uid_foreign')
                ->from($mmTable)
                ->where($queryBuilder->expr()->eq('uid_local', $queryBuilder->createNamedParameter($row['uid'])))
                ->execute()
                ->fetchAll();
                
                foreach ($resCat as $rowCat) {
                    $categoriesArray[] = $rowCat['uid_foreign'];
                }
                
                $categories = implode($categoriesArray, ',');
                
                $editOnClickLink = DirectMailUtility::getEditOnClickLink([
                    'edit' => [
                        $table => [
                            $row['uid'] => 'edit'
                        ]
                    ],
                    'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI'),
                ]);
                
                $out = '';
                $out .= $this->iconFactory->getIconForRecord($table, $row)->render() . htmlspecialchars($row['name']) . htmlspecialchars(' <' . $row['email'] . '>');
                $out .= '&nbsp;&nbsp;<a href="#" onClick="' . $editOnClickLink . '" title="' . $this->getLanguageService()->getLL('dmail_edit') . '">' .
                    $this->iconFactory->getIcon('actions-open', Icon::SIZE_SMALL) .
                    '<b>' . $this->getLanguageService()->getLL('dmail_edit') . '</b></a>';
                    $theOutput = '<h3>' . $this->getLanguageService()->getLL('subscriber_info') . '</h3>' .
                        $out;
                        
                        $out = '';
                        $outCheckBox = '';
                        
                        $this->categories = DirectMailUtility::makeCategories($table, $row, $this->sys_language_uid);
                        
                        reset($this->categories);
                        foreach ($this->categories as $pKey => $pVal) {
                            $outCheckBox .= '<input type="hidden" name="indata[categories][' . $row['uid'] . '][' . $pKey . ']" value="0" />' .
                                '<input type="checkbox" name="indata[categories][' . $row['uid'] . '][' . $pKey . ']" value="1"' . (GeneralUtility::inList($categories, $pKey) ? ' checked="checked"' : '') . ' /> ' . htmlspecialchars($pVal) . '<br />';
                        }
                        $outCheckBox .= '<br /><br /><input type="checkbox" name="indata[html]" value="1"' . ($row['module_sys_dmail_html'] ? ' checked="checked"' : '') . ' /> ';
                        $outCheckBox .= $this->getLanguageService()->getLL('subscriber_profile_htmlemail') . '<br />';
                        $out .= $outCheckBox;
                        
                        $out .= '<input type="hidden" name="table" value="' . $table . '" />' .
                            '<input type="hidden" name="uid" value="' . $uid . '" />' .
                            '<input type="hidden" name="cmd" value="' . $this->cmd . '" />' .
                            '<br /><input type="submit" name="submit" value="' . htmlspecialchars($this->getLanguageService()->getLL('subscriber_profile_update')) . '" />';
                        $theOutput .= '<div style="padding-top: 20px;"></div>';
                        $theOutput .= '<h3>' . $this->getLanguageService()->getLL('subscriber_profile') . '</h3>' .
                            $this->getLanguageService()->getLL('subscriber_profile_instructions') . '<br /><br />' . $out;
            }
        }
        return $theOutput;
    }
}