<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Module;

use DirectMailTeam\DirectMail\DmQueryGenerator;
use DirectMailTeam\DirectMail\Importer;
use DirectMailTeam\DirectMail\Repository\FeGroupsRepository;
use DirectMailTeam\DirectMail\Repository\FeUsersRepository;
use DirectMailTeam\DirectMail\Repository\SysDmailGroupRepository;
use DirectMailTeam\DirectMail\Repository\TempRepository;
use DirectMailTeam\DirectMail\Repository\TtAddressRepository;
use DirectMailTeam\DirectMail\Utility\DmCsvUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\GeneralUtility;

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

    protected $MOD_SETTINGS;

    protected int $uid = 0;
    protected string $table = '';
    protected array $indata = [];

    protected $requestHostOnly = '';
    protected $requestUri = '';
    protected $httpReferer = '';
    protected array $allowedTables = ['tt_address', 'fe_users'];

    private bool $submit = false;

    protected function initRecipientList(ServerRequestInterface $request): void
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $normalizedParams = $request->getAttribute('normalizedParams');
        $this->requestHostOnly = $normalizedParams->getRequestHostOnly();
        $this->requestUri = $normalizedParams->getRequestUri();
        $this->httpReferer = $request->getServerParams()['HTTP_REFERER'];

        $this->group_uid = (int)($parsedBody['group_uid'] ?? $queryParams['group_uid'] ?? 0);
        $this->lCmd = $parsedBody['lCmd'] ?? $queryParams['lCmd'] ?? '';
        $this->csv = $parsedBody['csv'] ?? $queryParams['csv'] ?? '';
        $this->set = is_array($parsedBody['SET'] ?? '') ? $parsedBody['SET'] : [];

        $this->uid = (int)($parsedBody['uid'] ?? $queryParams['uid'] ?? 0);
        $this->table = (string)($parsedBody['table'] ?? $queryParams['table'] ?? '');
        $this->indata = $parsedBody['indata'] ?? $queryParams['indata'] ?? [];
        $this->submit = (bool)($parsedBody['submit'] ?? $queryParams['submit'] ?? false);
    }

    public function indexAction(ServerRequestInterface $request): ResponseInterface
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
                    $data = $this->moduleContent();
                    $this->view->assignMultiple(
                        [
                            'data' => $data['data'],
                            'type' => $data['type'],
                            'formcontent' => $data['content'],
                            'show' => true,
                        ]
                    );
                } elseif ($this->id != 0) {
                    $message = $this->createFlashMessage($this->getLanguageService()->getLL('dmail_noRegular'), $this->getLanguageService()->getLL('dmail_newsletters'), 1, false);
                    $this->messageQueue->addMessage($message);
                }
            } else {
                $message = $this->createFlashMessage($this->getLanguageService()->getLL('select_folder'), $this->getLanguageService()->getLL('header_recip'), 1, false);
                $this->messageQueue->addMessage($message);
                $this->view->assignMultiple(
                    [
                        'dmLinks' => $this->getDMPages($this->moduleName),
                    ]
                );
            }
        } else {
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
        $theOutput = '';
        $data = [];
        // COMMAND:
        switch ($this->cmd) {
            case 'displayUserInfo': //@TODO ???
                $data = $this->displayUserInfo();
                $type = 1;
                break;
            case 'displayMailGroup':
                $result = $this->cmd_compileMailGroup($this->group_uid);
                $data = $this->displayMailGroup($result);
                $type = 2;
                break;
            case 'displayImport':
                /* @var $importer \DirectMailTeam\DirectMail\Importer */
                $importer = GeneralUtility::makeInstance(Importer::class);
                $importer->init($this);
                $theOutput = $importer->displayImport();
                $type = 3;
                break;
            default:
                $data = $this->showExistingRecipientLists();
                $theOutput = '';
                $type = 4;
        }

        return ['data' => $data, 'content' => $theOutput, 'type' => $type];
    }

    /**
     * Shows the existing recipient lists and shows link to create a new one or import a list
     *
     * @return string List of existing recipient list, link to create a new list and link to import
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function showExistingRecipientLists()
    {
        $data = [
            'rows' => [],
        ];

        $rows = GeneralUtility::makeInstance(SysDmailGroupRepository::class)->selectSysDmailGroupByPid($this->id, trim($GLOBALS['TCA']['sys_dmail_group']['ctrl']['default_sortby']));

        foreach ($rows as $row) {
            $result = $this->cmd_compileMailGroup((int)$row['uid']);
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
            $data['rows'][] = [
                'id'          => $row['uid'],
                'icon'        => $this->iconFactory->getIconForRecord('sys_dmail_group', $row, Icon::SIZE_SMALL)->render(),
                'editLink'    => $this->editLink('sys_dmail_group', $row['uid']),
                'reciplink'   => $this->linkRecip_record('<strong>' . htmlspecialchars(GeneralUtility::fixed_lgd_cs($row['title'], 30)) . '</strong>&nbsp;&nbsp;', $row['uid']),
                'type'        => htmlspecialchars((string) BackendUtility::getProcessedValue('sys_dmail_group', 'type', $row['type'])),
                'description' => BackendUtility::getProcessedValue('sys_dmail_group', 'description', htmlspecialchars($row['description'] ?? '')),
                'count'       => $count,
            ];
        }

        $data['editOnClickLink'] = $this->getEditOnClickLink([
            'edit' => [
                'sys_dmail_group' => [
                    $this->id => 'new',
                ],
            ],
            'returnUrl' => $this->requestUri,
        ]);

        $data['sysDmailGroupIcon'] = $this->iconFactory->getIconForRecord('sys_dmail_group', [], Icon::SIZE_SMALL);

        // Import
        $data['moduleUrl'] = $this->buildUriFromRoute(
            $this->moduleName,
            [
                'id' => $this->id,
                'cmd' => 'displayImport',
            ]
        );
        return $data;
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
                                        $pageIdArray = array_merge($pageIdArray, $this->getRecursiveSelect($pageUid, $this->perms_clause));
                                    }
                                }
                            }
                        }

                        // Remove any duplicates
                        $pageIdArray = array_unique($pageIdArray);
                        $info['recursive'] = $mailGroup['recursive'];

                        // Make queries
                        if (count($pageIdArray)) {
                            $whichTables = (int)$mailGroup['whichtables'];
                            // tt_address
                            if ($whichTables&1) {
                                $idLists['tt_address'] = GeneralUtility::makeInstance(TtAddressRepository::class)->getIdList($pageIdArray, $groupUid, $mailGroup['select_categories']);
                            }
                            // fe_users
                            if ($whichTables&2) {
                                $idLists['fe_users'] = GeneralUtility::makeInstance(FeUsersRepository::class)->getIdList($pageIdArray, $groupUid, $mailGroup['select_categories']);
                            }
                            // user table
                            if ($this->userTable && ($whichTables&4)) {
                                $idLists[$this->userTable] = GeneralUtility::makeInstance(TempRepository::class)->getIdList($this->userTable, $pageIdArray, $groupUid, $mailGroup['select_categories']);
                            }
                            // fe_groups
                            if ($whichTables&8) {
                                if (!is_array($idLists['fe_users'])) {
                                    $idLists['fe_users'] = [];
                                }
                                $idLists['fe_users'] = GeneralUtility::makeInstance(FeGroupsRepository::class)->getIdList($pageIdArray, $groupUid, $mailGroup['select_categories']);
                                $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users'], $idLists['fe_users']));
                            }
                        }
                        break;
                    case 1:
                        // List of mails
                        $mailGroupList = (string)$mailGroup['list'];
                        if ($mailGroup['csv'] == 1) {
                            $dmCsvUtility = GeneralUtility::makeInstance(DmCsvUtility::class);
                            $recipients = $dmCsvUtility->rearrangeCsvValues($dmCsvUtility->getCsvValues($mailGroupList), $this->getFieldList());
                        } else {
                            $recipients = $mailGroupList ? $this->rearrangePlainMails(array_unique(preg_split('|[[:space:],;]+|', $mailGroupList))) : [];
                        }
                        $idLists['PLAINLIST'] = $this->cleanPlainList($recipients);
                        break;
                    case 2:
                        // Static MM list
                        $idLists['tt_address'] = GeneralUtility::makeInstance(TtAddressRepository::class)->getStaticIdList($groupUid);
                        $idLists['fe_users'] = GeneralUtility::makeInstance(FeUsersRepository::class)->getStaticIdList($groupUid);
                        $tempGroups = GeneralUtility::makeInstance(FeGroupsRepository::class)->getStaticIdList($groupUid);
                        $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users'], $tempGroups));
                        if ($this->userTable) {
                            $idLists[$this->userTable] = GeneralUtility::makeInstance(TempRepository::class)->getStaticIdList($this->userTable, $groupUid);
                        }
                        break;
                    case 3:
                        // Special query list
                        $mailGroup = $this->updateSpecialQuery($mailGroup);
                        $whichTables = (int)$mailGroup['whichtables'];
                        $table = '';
                        if ($whichTables&1) {
                            $table = 'tt_address';
                        } elseif ($whichTables&2) {
                            $table = 'fe_users';
                        } elseif ($this->userTable && ($whichTables&4)) {
                            $table = $this->userTable;
                        }

                        if ($table) {
                            $queryGenerator = GeneralUtility::makeInstance(DmQueryGenerator::class, $this->MOD_SETTINGS, [], $this->moduleName);
                            $idLists[$table] = GeneralUtility::makeInstance(TempRepository::class)->getSpecialQueryIdList($queryGenerator, $table, $mailGroup);
                        }
                        break;
                    case 4:
                        $groups = array_unique(GeneralUtility::makeInstance(SysDmailGroupRepository::class)->getMailGroups($mailGroup['mail_groups'], [$mailGroup['uid']], $this->perms_clause));
                        foreach ($groups as $group) {
                            $collect = $this->cmd_compileMailGroup($group);
                            if (is_array($collect['queryInfo']['id_lists'])) {
                                $idLists = array_merge_recursive($idLists, $collect['queryInfo']['id_lists']);
                            }
                        }

                        // Make unique entries
                        if (is_array($idLists['tt_address'] ?? null)) {
                            $idLists['tt_address'] = array_unique($idLists['tt_address']);
                        }
                        if (is_array($idLists['fe_users'] ?? null)) {
                            $idLists['fe_users'] = array_unique($idLists['fe_users']);
                        }
                        if (is_array($idLists[$this->userTable] ?? null) && $this->userTable) {
                            $idLists[$this->userTable] = array_unique($idLists[$this->userTable]);
                        }
                        if (is_array($idLists['PLAINLIST'] ?? null)) {
                            $idLists['PLAINLIST'] = $this->cleanPlainList($idLists['PLAINLIST']);
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
            'queryInfo' => ['id_lists' => $idLists],
        ];
    }

    /**
     * Shows edit link
     *
     * @param string $table Table name
     * @param int $uid Record uid
     *
     * @return array the edit link config
     */
    protected function editLink($table, $uid): array
    {
        $editLinkConfig = ['onClick' => '', 'icon' => $this->getIconActionsOpen()];
        // check if the user has the right to modify the table
        if ($this->getBackendUser()->check('tables_modify', $table)) {
            $editLinkConfig['onClick'] = $this->getEditOnClickLink([
                'edit' => [
                    $table => [
                        $uid => 'edit',
                    ],
                ],
                'returnUrl' => $this->requestUri,
            ]);
        }

        return $editLinkConfig;
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
                'SET[dmail_mode]' => 'recip',
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
    protected function displayMailGroup($result)
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
        if (!in_array($this->userTable, ['tt_address', 'fe_users', 'PLAINLIST']) && is_array($idLists[$this->userTable] ?? false)) {
            $totalRecipients += count($idLists[$this->userTable]);
        }

        $group = BackendUtility::getRecord('sys_dmail_group', $this->group_uid);
        $group = is_array($group) ? $group : [];
        $data = [
            'queryLimitDisabled' => $group['queryLimitDisabled'] ?? true,
            'group_id' => $this->group_uid,
            'group_icon' => $this->iconFactory->getIconForRecord('sys_dmail_group', $group, Icon::SIZE_SMALL),
            'group_title' => htmlspecialchars($group['title'] ?? ''),
            'group_totalRecipients' => $totalRecipients,
            'group_link_listall' => ($this->lCmd == '') ? GeneralUtility::linkThisScript(['lCmd'=>'listall']) : '',
            'tables' => [],
            'special' => [],
        ];

        // do the CSV export
        $csvValue = $this->csv; //'tt_address', 'fe_users', 'PLAINLIST', $this->userTable
        if ($csvValue) {
            $dmCsvUtility = GeneralUtility::makeInstance(DmCsvUtility::class);

            if ($csvValue == 'PLAINLIST') {
                $dmCsvUtility->downloadCSV($idLists['PLAINLIST']);
            } elseif (GeneralUtility::inList('tt_address,fe_users,' . $this->userTable, $csvValue)) {
                if ($this->getBackendUser()->check('tables_select', $csvValue)) {
                    $fields = $csvValue == 'fe_users' ? $this->getFieldListFeUsers() : $this->getFieldList();
                    $fields[] = 'tstamp';
                    $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists[$csvValue], $csvValue, $fields);
                    $dmCsvUtility->downloadCSV($rows);
                } else {
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
                if (is_array($idLists['tt_address'] ?? false)) {
                    //https://github.com/FriendsOfTYPO3/tt_address/blob/master/ext_tables.sql
                    $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues(
                        $idLists['tt_address'],
                        'tt_address',
                        ['uid', 'name', 'first_name', 'middle_name', 'last_name', 'email']
                    );
                    $data['tables'][] = [
                        'title_table' => 'mailgroup_table_address',
                        'recipListConfig' => $this->getRecordList($rows, 'tt_address'),
                        'table_custom' => '',
                    ];
                }
                if (is_array($idLists['fe_users'] ?? false)) {
                    $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists['fe_users'], 'fe_users');
                    $data['tables'][] = [
                        'title_table' => 'mailgroup_table_fe_users',
                        'recipListConfig' => $this->getRecordList($rows, 'fe_users'),
                        'table_custom' => '',
                    ];
                }
                if (is_array($idLists['PLAINLIST'] ?? false)) {
                    $data['tables'][] = [
                        'title_table' => 'mailgroup_plain_list',
                        'recipListConfig' => $this->getRecordList($idLists['PLAINLIST'], 'sys_dmail_group'),
                        'table_custom' => '',
                    ];
                }
                if (!in_array($this->userTable, ['tt_address', 'fe_users', 'PLAINLIST']) && is_array($idLists[$this->userTable] ?? false)) {
                    $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists[$this->userTable], $this->userTable);
                    $data['tables'][] = [
                        'title_table' => 'mailgroup_table_custom',
                        'recipListConfig' => $this->getRecordList($rows, $this->userTable),
                        'table_custom' => ' ' . $this->userTable,
                    ];
                }
                break;
            default:
                if (is_array($idLists['tt_address'] ?? false) && count($idLists['tt_address'])) {
                    $data['tables'][] = [
                        'title_table' => 'mailgroup_table_address',
                        'title_recip' => 'mailgroup_recip_number',
                        'recip_counter' => ' ' . count($idLists['tt_address']),
                        'mailgroup_download_link' => GeneralUtility::linkThisScript(['csv'=>'tt_address']),
                    ];
                }

                if (is_array($idLists['fe_users'] ?? false) && count($idLists['fe_users'])) {
                    $data['tables'][] = [
                        'title_table' => 'mailgroup_table_fe_users',
                        'title_recip' => 'mailgroup_recip_number',
                        'recip_counter' => ' ' . count($idLists['fe_users']),
                        'mailgroup_download_link' => GeneralUtility::linkThisScript(['csv'=>'fe_users']),
                    ];
                }

                if (is_array($idLists['PLAINLIST'] ?? false) && count($idLists['PLAINLIST'])) {
                    $data['tables'][] = [
                        'title_table' => 'mailgroup_plain_list',
                        'title_recip' => 'mailgroup_recip_number',
                        'recip_counter' => ' ' . count($idLists['PLAINLIST']),
                        'mailgroup_download_link' => GeneralUtility::linkThisScript(['csv'=>'PLAINLIST']),
                    ];
                }

                if (!in_array($this->userTable, ['tt_address', 'fe_users', 'PLAINLIST']) && is_array($idLists[$this->userTable] ?? false) && count($idLists[$this->userTable])) {
                    $data['tables'][] = [
                        'title_table' => 'mailgroup_table_custom',
                        'title_recip' => 'mailgroup_recip_number',
                        'recip_counter' => ' ' . count($idLists[$this->userTable]),
                        'mailgroup_download_link' => GeneralUtility::linkThisScript(['csv' => $this->userTable]),
                    ];
                }

                if (($group['type'] ?? false) == 3) {
                    if ($this->getBackendUser()->check('tables_modify', 'sys_dmail_group')) {
                        $data['special'] = $this->specialQuery();
                    }
                }
        }

        return $data;
    }

    /**
     * Update recipient list record with a special query
     *
     * @param array $mailGroup DB records
     *
     * @return array Updated DB records
     */
    protected function updateSpecialQuery($mailGroup)
    {
        $set = $this->set;
        $queryTable = $set['queryTable'] ?? '';
        $queryLimit = $set['queryLimit'] ?? $mailGroup['queryLimit'] ?? 100;
        $queryLimitDisabled = ($set['queryLimitDisabled'] ?? $mailGroup['queryLimitDisabled']) == '' ? 0 : 1;
        $queryConfig = GeneralUtility::_GP('queryConfig');
        $whichTables = (int)$mailGroup['whichtables'];
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

        $this->MOD_SETTINGS['search_query_makeQuery'] = 'all';
        $this->MOD_SETTINGS['search'] = 'query';

        if ($this->MOD_SETTINGS['queryTable'] != $table) {
            $this->MOD_SETTINGS['queryConfig'] = '';
        }

        $this->MOD_SETTINGS['queryLimit'] = $queryLimit;

        if ($this->MOD_SETTINGS['queryTable'] != $table
            || $this->MOD_SETTINGS['queryConfig'] != $mailGroup['query']
            || $this->MOD_SETTINGS['queryLimit'] != $mailGroup['queryLimit']
            || $queryLimitDisabled != $mailGroup['queryLimitDisabled']
        ) {
            $whichTables = 0;
            if ($this->MOD_SETTINGS['queryTable'] == 'tt_address') {
                $whichTables = 1;
            } elseif ($this->MOD_SETTINGS['queryTable'] == 'fe_users') {
                $whichTables = 2;
            } elseif ($this->MOD_SETTINGS['queryTable'] == $this->userTable) {
                $whichTables = 4;
            }
            $updateFields = [
                'whichtables' => (int)$whichTables,
                'query' => $this->MOD_SETTINGS['queryConfig'],
                'queryLimit' => $this->MOD_SETTINGS['queryLimit'],
                'queryLimitDisabled' => $queryLimitDisabled,
            ];

            $done = GeneralUtility::makeInstance(SysDmailGroupRepository::class)->updateSysDmailGroupRecord((int)$mailGroup['uid'], $updateFields);
            $mailGroup = BackendUtility::getRecord('sys_dmail_group', $mailGroup['uid']);
        }
        return $mailGroup;
    }

    /**
     * Show HTML form to make special query
     *
     * @return array HTML form to make a special query
     */
    protected function specialQuery()
    {
        $queryGenerator = GeneralUtility::makeInstance(DmQueryGenerator::class, $this->MOD_SETTINGS, [], $this->moduleName);
        //$queryGenerator->setFormName('dmailform');
        $queryGenerator->setFormName('queryform');

        //if ($this->MOD_SETTINGS['queryTable'] && $this->MOD_SETTINGS['queryConfig']) {
        //    $queryGenerator->extFieldLists['queryFields'] = 'uid';
        //}

        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Lowlevel/QueryGenerator');
        $this->pageRenderer->loadRequireJsModule('TYPO3/CMS/Backend/DateTimePicker');
        [$html, $query] = $queryGenerator->queryMakerDM($this->allowedTables);
        return ['selectTables' => $html, 'query' => $query];
    }

    /**
     * Shows user's info and categories
     *
     * @return	string HTML showing user's info and the categories
     */
    protected function displayUserInfo()
    {
        if (!in_array($this->table, ['tt_address', 'fe_users'])) {
            return [];
        }
        if ($this->submit) {
            if (count($this->indata) < 1) {
                $this->indata['html'] = 0;
            }
        }

        switch ($this->table) {
            case 'tt_address':
                // see fe_users
            case 'fe_users':
                if (is_array($this->indata) && count($this->indata)) {
                    $data = [];
                    if (is_array($this->indata['categories'] ?? false)) {
                        reset($this->indata['categories']);
                        foreach ($this->indata['categories'] as $recValues) {
                            reset($recValues);
                            $enabled = [];
                            foreach ($recValues as $k => $b) {
                                if ($b) {
                                    $enabled[] = $k;
                                }
                            }
                            $data[$this->table][$this->uid]['module_sys_dmail_category'] = implode(',', $enabled);
                        }
                    }
                    $data[$this->table][$this->uid]['module_sys_dmail_html'] = $this->indata['html'] ? 1 : 0;

                    /* @var $dataHandler \TYPO3\CMS\Core\DataHandling\DataHandler*/
                    $dataHandler = $this->getDataHandler();
                    $dataHandler->stripslashes_values = 0;
                    $dataHandler->start($data, []);
                    $dataHandler->process_datamap();
                }
                break;
            default:
                // do nothing
        }

        $rows = [];
        switch ($this->table) {
            case 'tt_address':
                $rows = GeneralUtility::makeInstance(TtAddressRepository::class)->selectTtAddressByUid($this->uid, $this->perms_clause);
                break;
            case 'fe_users':
                $rows = GeneralUtility::makeInstance(FeUsersRepository::class)->selectFeUsersByUid($this->uid, $this->perms_clause);
                break;
            default:
                // do nothing
        }

        $theOutput = '';

        $row = $rows[0] ?? [];

        if (is_array($row) && count($row)) {
            $mmTable = $GLOBALS['TCA'][$this->table]['columns']['module_sys_dmail_category']['config']['MM'];
            $resCat = GeneralUtility::makeInstance(TempRepository::class)->getDisplayUserInfo((string)$mmTable, (int)$row['uid']);
            $categoriesArray = [];
            if ($resCat && count($resCat)) {
                foreach ($resCat as $rowCat) {
                    $categoriesArray[] = $rowCat['uid_foreign'];
                }
            }

            $categories = implode(',', $categoriesArray);

            $editOnClickLink = $this->getEditOnClickLink([
                'edit' => [
                    $this->table => [
                        $row['uid'] => 'edit',
                    ],
                ],
                'returnUrl' => $this->requestUri,
            ]);

            $dataout = [
                'icon' => $this->iconFactory->getIconForRecord($this->table, $row)->render(),
                'iconActionsOpen' => $this->getIconActionsOpen(),
                'name' => htmlspecialchars($row['name'] ?? ''),
                'email' => htmlspecialchars($row['email'] ?? ''),
                'uid' => $row['uid'],
                'editOnClickLink' => $editOnClickLink,
                'categories' => [],
                'table' => $this->table,
                'thisID' => $this->uid,
                'cmd' => $this->cmd,
                'html' => $row['module_sys_dmail_html'] ? true : false,
            ];
            $this->categories = GeneralUtility::makeInstance(TempRepository::class)->makeCategories($this->table, $row, $this->sys_language_uid);

            reset($this->categories);
            foreach ($this->categories as $pKey => $pVal) {
                $dataout['categories'][] = [
                    'pkey'    => $pKey,
                    'pVal'    => htmlspecialchars($pVal),
                    'checked' => GeneralUtility::inList($categories, $pKey) ? true : false,
                ];
            }
        }
        return $dataout;
    }

    public function getRequestHostOnly(): string
    {
        return $this->requestHostOnly;
    }

    public function getHttpReferer(): string
    {
        return $this->httpReferer;
    }
}
