<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Module;

use DirectMailTeam\DirectMail\DirectMailUtility;
use DirectMailTeam\DirectMail\Repository\SysDmailRepository;
use DirectMailTeam\DirectMail\Repository\SysDmailMaillogRepository;
use DirectMailTeam\DirectMail\Repository\FeUsersRepository;
use DirectMailTeam\DirectMail\Repository\TtAddressRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

class StatisticsController extends MainController
{   
    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = 'DirectMailNavFrame_Statistics';
    
    private int $uid = 0;
    private string $table = '';
    private array $tables = ['tt_address', 'fe_users'];
    private bool $recalcCache = false;
    private bool $submit = false;
    private array $indata = [];
    
    private bool $returnList    = false;
    private bool $returnDisable = false;
    private bool $returnCSV     = false;
    
    private bool $unknownList    = false;
    private bool $unknownDisable = false;
    private bool $unknownCSV     = false;
    
    private bool $fullList    = false;
    private bool $fullDisable = false;
    private bool $fullCSV     = false;
    
    private bool $badHostList    = false;
    private bool $badHostDisable = false;
    private bool $badHostCSV     = false;
    
    private bool $badHeaderList    = false;
    private bool $badHeaderDisable = false;
    private bool $badHeaderCSV     = false;
    
    private bool $reasonUnknownList    = false;
    private bool $reasonUnknownDisable = false;
    private bool $reasonUnknownCSV     = false;
        
    protected function initStatistics(ServerRequestInterface $request): void {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();
        
        $this->uid = (int)($parsedBody['uid'] ?? $queryParams['uid'] ?? 0);
        
        $table = (string)($parsedBody['table'] ?? $queryParams['table'] ?? '');
        if(in_array($table, $this->tables)) {
            $this->table = (string)($table);
        }
        
        $this->recalcCache = (bool)($parsedBody['recalcCache'] ?? $queryParams['recalcCache'] ?? false);
        $this->submit = (bool)($parsedBody['submit'] ?? $queryParams['submit'] ?? false);
        
        $this->indata = $parsedBody['indata'] ?? $queryParams['indata'] ?? [];
        
        $this->returnList    = (bool)($parsedBody['returnList'] ?? $queryParams['returnList'] ?? false);
        $this->returnDisable = (bool)($parsedBody['returnDisable'] ?? $queryParams['returnDisable'] ?? false);
        $this->returnCSV     = (bool)($parsedBody['returnCSV'] ?? $queryParams['returnCSV'] ?? false);
        
        $this->unknownList    = (bool)($parsedBody['unknownList'] ?? $queryParams['unknownList'] ?? false);
        $this->unknownDisable = (bool)($parsedBody['unknownDisable'] ?? $queryParams['unknownDisable'] ?? false);
        $this->unknownCSV     = (bool)($parsedBody['unknownCSV'] ?? $queryParams['unknownCSV'] ?? false);
        
        $this->fullList    = (bool)($parsedBody['fullList'] ?? $queryParams['fullList'] ?? false);
        $this->fullDisable = (bool)($parsedBody['fullDisable'] ?? $queryParams['fullDisable'] ?? false);
        $this->fullCSV     = (bool)($parsedBody['fullCSV'] ?? $queryParams['fullCSV'] ?? false);
            
        $this->badHostList    = (bool)($parsedBody['badHostList'] ?? $queryParams['badHostList'] ?? false);
        $this->badHostDisable = (bool)($parsedBody['badHostDisable'] ?? $queryParams['badHostDisable'] ?? false);
        $this->badHostCSV     = (bool)($parsedBody['badHostCSV'] ?? $queryParams['badHostCSV'] ?? false);
            
        $this->badHeaderList    = (bool)($parsedBody['badHeaderList'] ?? $queryParams['badHeaderList'] ?? false);
        $this->badHeaderDisable = (bool)($parsedBody['badHeaderDisable'] ?? $queryParams['badHeaderDisable'] ?? false);
        $this->badHeaderCSV     = (bool)($parsedBody['badHeaderCSV'] ?? $queryParams['badHeaderCSV'] ?? false);
            
        $this->reasonUnknownList    = (bool)($parsedBody['reasonUnknownList'] ?? $queryParams['reasonUnknownList'] ?? false);
        $this->reasonUnknownDisable = (bool)($parsedBody['reasonUnknownDisable'] ?? $queryParams['reasonUnknownDisable'] ?? false);
        $this->reasonUnknownCSV     = (bool)($parsedBody['reasonUnknownCSV'] ?? $queryParams['reasonUnknownCSV'] ?? false);
    }
    
    public function indexAction(ServerRequestInterface $request) : ResponseInterface
    {
        $this->view = $this->configureTemplatePaths('Statistics');
        
        $this->init($request);
        $this->initStatistics($request);
        
        if (($this->id && $this->access) || ($this->isAdmin() && !$this->id)) {
            $module = $this->getModulName();

            if ($module == 'dmail') {
                // Direct mail module
                if (($this->pageinfo['doktype'] ?? 0) == 254) {
                    $data = $this->moduleContent();
                    $this->view->assignMultiple(
                        [
                            'data' => $data,
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
                $message = $this->createFlashMessage($this->getLanguageService()->getLL('select_folder'), $this->getLanguageService()->getLL('header_stat'), 1, false);
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
    
    protected function moduleContent()
    {
        $theOutput = [];
        
        if (!$this->sys_dmail_uid) {
            $theOutput['dataPageInfo'] = $this->displayPageInfo();
        } 
        else {
            $row = GeneralUtility::makeInstance(SysDmailRepository::class)->selectSysDmailById($this->sys_dmail_uid, $this->id);
            
//          $this->noView = 0;
            if (is_array($row)) {
                // Set URL data for commands
                $this->setURLs($row);
                
                // COMMAND:
                switch ($this->cmd) {
                    case 'displayUserInfo':
                        $theOutput['dataUserInfo'] = $this->displayUserInfo();
                        break;
                    case 'stats':
                        $theOutput['dataStats'] = $this->stats($row);
                        break;
                    default:
                        // Hook for handling of custom direct mail commands:
                        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXT']['directmail']['handledirectmailcmd-' . $this->cmd])) {
                            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXT']['directmail']['handledirectmailcmd-' . $this->cmd] as $funcRef) {
                                $params = ['pObj' => &$this];
                                $theOutput['dataHook'] = GeneralUtility::callUserFunction($funcRef, $params, $this);
                            }
                        }
                }
            }
        }
        return $theOutput;
    }
    
    /**
     * Shows the info of a page
     *
     * @return string The infopage of the sent newsletters
     */
    protected function displayPageInfo()
    {
        // Here the dmail list is rendered:
        $rows = GeneralUtility::makeInstance(SysDmailRepository::class)->selectForPageInfo($this->id);
        $data = [];
        if (is_array($rows)) {
            foreach ($rows as $row)  {
                $data[] = [
                    'icon'            => $this->iconFactory->getIconForRecord('sys_dmail', $row, Icon::SIZE_SMALL)->render(),
                    'subject'         => $this->linkDMail_record(GeneralUtility::fixed_lgd_cs($row['subject'], 30) . '  ', $row['uid'], $row['subject']),
                    'scheduled'       => BackendUtility::datetime($row['scheduled']),
                    'scheduled_begin' => $row['scheduled_begin'] ? BackendUtility::datetime($row['scheduled_begin']) : '',
                    'scheduled_end'   => $row['scheduled_end'] ? BackendUtility::datetime($row['scheduled_end']) : '',
                    'sent'            => $row['count'] ? $row['count'] : '',
                    'status'          => $this->getSentStatus($row)
                ];
            }
        }

        return $data;
    }
    
    protected function getSentStatus(array $row): string {
        if (!empty($row['scheduled_begin'])) {
            if (!empty($row['scheduled_end'])) {
                $sent = $this->getLanguageService()->getLL('stats_overview_sent');
            } else {
                $sent = $this->getLanguageService()->getLL('stats_overview_sending');
            }
        } else {
            $sent = $this->getLanguageService()->getLL('stats_overview_queuing');
        }
        return $sent;
    }
    
    /**
     * Shows user's info and categories
     *
     * @return string HTML showing user's info and the categories
     */
    protected function displayUserInfo()
    {
        $indata = $this->indata;

        $mmTable = $GLOBALS['TCA'][$this->table]['columns']['module_sys_dmail_category']['config']['MM'];
        
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
                    if (is_array($this->indata['categories'])) {
                        reset($this->indata['categories']);
                        foreach ($this->indata['categories'] as $recValues) {
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
                    
                    /* @var $tce \TYPO3\CMS\Core\DataHandling\DataHandler */
                    $tce = GeneralUtility::makeInstance(DataHandler::class);
                    $tce->stripslashes_values = 0;
                    $tce->start($data, []);
                    $tce->process_datamap();
                }
                break;
            default:
                // do nothing
        }
        
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
        
        $row = $rows[0] ?? [];

        if (is_array($row)) {
            //@TODO
            $categories = '';
            $queryBuilder = $this->getQueryBuilder($mmTable);
            $resCat = $queryBuilder
            ->select('uid_foreign')
            ->from($mmTable)
            ->add('where','uid_local=' . $row['uid'])
            ->execute();
            while ($rowCat = $resCat->fetch()) {
                $categories .= $rowCat['uid_foreign'] . ',';
            }
            $categories = rtrim($categories, ',');
            
            $editOnClickLink = DirectMailUtility::getEditOnClickLink([
                'edit' => [
                    $this->table => [
                        $row['uid'] => 'edit',
                    ],
                ],
                'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI'),
            ]);

            $this->categories = DirectMailUtility::makeCategories($this->table, $row, $this->sys_language_uid);
            
            $data = [
                'icon'            => $this->iconFactory->getIconForRecord($this->table, $row)->render(),
                'iconActionsOpen' => $this->iconFactory->getIcon('actions-open', Icon::SIZE_SMALL),
                'name'            => htmlspecialchars($row['name']),
                'email'           => htmlspecialchars($row['email']),
                'uid'             => $row['uid'],
                'editOnClickLink' => $editOnClickLink,
                'categories'      => [],
                'catChecked'      => 0,
                'table'           => $this->table,
                'thisID'          => $this->uid,
                'cmd'             => $this->cmd,
                'html'            => $row['module_sys_dmail_html'] ? true : false
            ];

            foreach ($this->categories as $pKey => $pVal) {
                $data['categories'][] = [
                    'pkey'    => $pKey,
                    'pVal'    => htmlspecialchars($pVal),
                    'checked' => GeneralUtility::inList($categories, $pKey) ? true : false
                ];
            }
        }
        
        return $data;
    }
    
    protected function mailResponsesGeneral(int $mailingId): array {
        $table = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->countSysDmailMaillogsResponseTypeByMid($mailingId);
        $table = $this->changekeyname($table,'counter','COUNT(*)');

        // Plaintext/HTML
        $res = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->countSysDmailMaillogAllByMid($mailingId);
        
        /* this function is called to change the key from 'COUNT(*)' to 'counter' */
        $res = $this->changekeyname($res,'counter','COUNT(*)');
        
        $textHtml = [];
        foreach($res as $row2){
            // 0:No mail; 1:HTML; 2:TEXT; 3:HTML+TEXT
            $textHtml[$row2['html_sent']] = $row2['counter'];
        }
        
        // Unique responses, html
        $res = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->countSysDmailMaillogHtmlByMid($mailingId);
        $uniqueHtmlResponses = count($res);//sql_num_rows($res);
        
        // Unique responses, Plain
        $res = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->countSysDmailMaillogPlainByMid($mailingId);
        $uniquePlainResponses = count($res); //sql_num_rows($res);
        
        // Unique responses, pings
        $res = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->countSysDmailMaillogPingByMid($mailingId);
        $uniquePingResponses = count($res); //sql_num_rows($res);
        
        $totalSent = intval(($textHtml['1'] ?? 0) + ($textHtml['2'] ?? 0) + ($textHtml['3'] ?? 0));
        $htmlSent  = intval(($textHtml['1'] ?? 0) + ($textHtml['3'] ?? 0));
        $plainSent = intval(($textHtml['2'] ?? 0));
        
        return [
            'table' => [
                'head' => [
                    '', 'stats_total', 'stats_HTML', 'stats_plaintext'
                ],
                'body' => [
                    [
                        'stats_mails_sent',
                        $totalSent,
                        $htmlSent,
                        $plainSent
                    ],
                    [
                        'stats_mails_returned',
                        $this->showWithPercent($table['-127']['counter'] ?? 0, $totalSent),
                        '',
                        ''
                    ],
                    [
                        'stats_HTML_mails_viewed',
                        '',
                        $this->showWithPercent($uniquePingResponses, $htmlSent),
                        ''
                    ],
                    [
                        'stats_unique_responses',
                        $this->showWithPercent($uniqueHtmlResponses + $uniquePlainResponses, $totalSent),
                        $this->showWithPercent($uniqueHtmlResponses, $htmlSent),
                        $this->showWithPercent($uniquePlainResponses, $plainSent ? $plainSent : $htmlSent)
                    ]
                ]
            ],
            'uniqueHtmlResponses' => $uniqueHtmlResponses,
            'uniquePlainResponses' => $uniquePlainResponses,
            'totalSent' => $totalSent,
            'htmlSent' => $htmlSent,
            'plainSent' => $plainSent,
            'db' => $table
        ];
    }
    
    /**
     * Get statistics from DB and compile them.
     *
     * @param array $row DB record
     *
     * @return string Statistics of a mail
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function stats($row)
    {
        if ($this->recalcCache) {
            $this->makeStatTempTableContent($row);
        }
        
        $thisurl = $this->buildUriFromRoute(
            $this->moduleName,
            [
                'id' => $this->id,
                'sys_dmail_uid' => $row['uid'],
                'cmd' => $this->cmd,
                'recalcCache' => 1
            ]
        );

        $compactView = $this->directMail_compactView($row);
        
        $mailResponsesGeneral = $this->mailResponsesGeneral($row['uid']);
        $tables = [];
        $tables[1] = $mailResponsesGeneral['table'];
        $uniqueHtmlResponses = $mailResponsesGeneral['uniqueHtmlResponses'];
        $uniquePlainResponses = $mailResponsesGeneral['uniquePlainResponses'];
        $totalSent = $mailResponsesGeneral['totalSent'];
        $htmlSent = $mailResponsesGeneral['htmlSent']; 
        $plainSent =  $mailResponsesGeneral['plainSent'];
        $table = $mailResponsesGeneral['db'];
        
        $output = '';
        
        // ******************
        // Links:
        // ******************
        
        // initialize $urlCounter
        $urlCounter =  [
            'total' => [],
            'plain' => [],
            'html' => [],
        ];
        // Most popular links, html:
        $htmlUrlsTable = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->selectMostPopularLinksHtml($row['uid']);
        $htmlUrlsTable = $this->changekeyname($htmlUrlsTable, 'counter', 'COUNT(*)');

        // Most popular links, plain:
        $plainUrlsTable = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->selectMostPopularLinksPlain($row['uid']);
        $plainUrlsTable = $this->changekeyname($plainUrlsTable, 'counter', 'COUNT(*)');

        // Find urls:
        $unpackedMail = unserialize(base64_decode($row['mailContent']));
        // this array will include a unique list of all URLs that are used in the mailing
        $urlArr = [];
        
        $urlMd5Map = [];
        if (is_array($unpackedMail['html']['hrefs'] ?? false)) {
            foreach ($unpackedMail['html']['hrefs'] as $k => $v) {
                // convert &amp; of query params back
                $urlArr[$k] = html_entity_decode($v['absRef']);
                $urlMd5Map[md5($v['absRef'])] = $k;
            }
        }
        if (is_array($unpackedMail['plain']['link_ids'] ?? false)) {
            foreach ($unpackedMail['plain']['link_ids'] as $k => $v) {
                $urlArr[intval(-$k)] = $v;
            }
        }
        
        // Traverse plain urls:
        $mappedPlainUrlsTable = [];
        foreach ($plainUrlsTable as $id => $c) {
            $url = $urlArr[intval($id)];
            if (isset($urlMd5Map[md5($url)])) {
                $mappedPlainUrlsTable[$urlMd5Map[md5($url)]] = $c;
            } else {
                $mappedPlainUrlsTable[$id] = $c;
            }
        }
        
        $urlCounter['total'] = [];
        // Traverse html urls:
        $urlCounter['html'] = [];
        if (count($htmlUrlsTable) > 0) {
            foreach ($htmlUrlsTable as $id => $c) {
                $urlCounter['html'][$id]['counter'] = $urlCounter['total'][$id]['counter'] = $c['counter'];
            }
        }
        
        // Traverse plain urls:
        $urlCounter['plain'] = [];
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

        $tables[2] = [
            'head' => [
                '', 'stats_total', 'stats_HTML', 'stats_plaintext'
            ],
            'body' => [
                [
                    'stats_total_responses',
                    ($table['1']['counter'] ?? 0) + ($table['2']['counter'] ?? 0),
                    $table['1']['counter'] ?? '0',
                    $table['2']['counter'] ?? '0'
                ],
                [
                    'stats_unique_responses',
                    $this->showWithPercent($uniqueHtmlResponses + $uniquePlainResponses, $totalSent),
                    $this->showWithPercent($uniqueHtmlResponses, $htmlSent),
                    $this->showWithPercent($uniquePlainResponses, $plainSent ? $plainSent : $htmlSent)
                ],
                [
                    'stats_links_clicked_per_respondent',
                    ($uniqueHtmlResponses+$uniquePlainResponses ? number_format(($table['1']['counter'] + $table['2']['counter']) / ($uniqueHtmlResponses+$uniquePlainResponses), 2) : '-'),
                    ($uniqueHtmlResponses  ? number_format(($table['1']['counter']) / ($uniqueHtmlResponses), 2)  : '-'),
                    ($uniquePlainResponses ? number_format(($table['2']['counter']) / ($uniquePlainResponses), 2) : '-')
                ]
            ]
        ]; 
        arsort($urlCounter['total']);
        arsort($urlCounter['html']);
        arsort($urlCounter['plain']);
        reset($urlCounter['total']);
        
        $tblLines = [];
        $tblLines[] = [
            '',
            $this->getLanguageService()->getLL('stats_HTML_link_nr'),
            $this->getLanguageService()->getLL('stats_plaintext_link_nr'),
            $this->getLanguageService()->getLL('stats_total'),$this->getLanguageService()->getLL('stats_HTML'),
            $this->getLanguageService()->getLL('stats_plaintext'),
            ''
        ];
        
        // HTML mails
        if (intval($row['sendOptions']) & 0x2) {
            $htmlContent = $unpackedMail['html']['content'];
            
            $htmlLinks = [];
            if (is_array($unpackedMail['html']['hrefs'])) {
                foreach ($unpackedMail['html']['hrefs'] as $jumpurlId => $data) {
                    $htmlLinks[$jumpurlId] = [
                        'url'   => $data['ref'],
                        'label' => ''
                    ];
                }
            }
            
            // Parse mail body
            $dom = new \DOMDocument;
            @$dom->loadHTML($htmlContent);
            $links = [];
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
                } 
                else {
                    $label = '<span title="' . $targetUrl . '">' . GeneralUtility::fixed_lgd_cs(substr($targetUrl, -40), 40) . '</span>';
                }
                
                $htmlLinks[$jumpurlId]['label'] = $label;
            }
        }
        
        $iconAppsToolbarMenuSearch = $this->iconFactory->getIcon('apps-toolbar-menu-search', Icon::SIZE_SMALL)->render();
        
        foreach ($urlCounter['total'] as $id => $_) {
            // $id is the jumpurl ID
            $origId = $id;
            $id     = abs(intval($id));
            $url    = $htmlLinks[$id]['url'] ? $htmlLinks[$id]['url'] : $urlArr[$origId];
            // a link to this host?
            $uParts = @parse_url($url);
            $urlstr = $this->getUrlStr($uParts);
            
            $label = $this->getLinkLabel($url, $urlstr, false, $htmlLinks[$id]['label']);
            
            $img = '<a href="' . $urlstr . '" target="_blank">' .  $iconAppsToolbarMenuSearch . '</a>';
            
            if (isset($urlCounter['html'][$id]['plainId'])) {
                $tblLines[] = [
                    $label,
                    $id,
                    $urlCounter['html'][$id]['plainId'],
                    $urlCounter['total'][$origId]['counter'],
                    $urlCounter['html'][$id]['counter'],
                    $urlCounter['html'][$id]['plainCounter'],
                    $img
                ];
            } else {
                $html = (empty($urlCounter['html'][$id]['counter']) ? 0 : 1);
                $tblLines[] = [
                    $label,
                    ($html ? $id : '-'),
                    ($html ? '-' : $id),
                    ($html ? $urlCounter['html'][$id]['counter'] : $urlCounter['plain'][$origId]['counter']),
                    $urlCounter['html'][$id]['counter'],
                    $urlCounter['plain'][$origId]['counter'],
                    $img
                ];
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
                $img = '<a href="' . htmlspecialchars($link) . '" target="_blank">' .  $iconAppsToolbarMenuSearch . '</a>';
                $tblLines[] = [
                    $label,
                    ($html ? $id : '-'),
                    ($html ? '-' : abs($id)),
                    ($html ? $urlCounter['html'][$id]['counter'] : $urlCounter['plain'][$id]['counter']),
                    $urlCounter['html'][$id]['counter'],
                    $urlCounter['plain'][$id]['counter'],
                    $img
                ];
            }
        }
        
        if ($urlCounter['total']) {
            $output .= '<br /><h2>' . $this->getLanguageService()->getLL('stats_response_link') . '</h2>';
            
            /**
             * Hook for cmd_stats_linkResponses
             */
            if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod4']['cmd_stats_linkResponses'])) {
                $hookObjectsArr = [];
                foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod4']['cmd_stats_linkResponses'] as $classRef) {
                    $hookObjectsArr[] = GeneralUtility::makeInstance($classRef);
                }
                
                foreach ($hookObjectsArr as $hookObj) {
                    if (method_exists($hookObj, 'cmd_stats_linkResponses')) {
                        $output .= $hookObj->cmd_stats_linkResponses($tblLines, $this);
                    }
                }
            } else {
                $output .= DirectMailUtility::formatTable($tblLines, ['nowrap', 'nowrap width="100"', 'nowrap width="100"', 'nowrap', 'nowrap', 'nowrap', 'nowrap'], 1, [1, 0, 0, 0, 0, 0, 1]);
            }
        }

        // ******************
        // Returned mails
        // ******************
        
        // The icons:
        $listIcons = $this->iconFactory->getIcon('actions-system-list-open', Icon::SIZE_SMALL);
        $csvIcons  = $this->iconFactory->getIcon('actions-document-export-csv', Icon::SIZE_SMALL);
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
        $responseResult = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->countReturnCode($row['uid']);
        $responseResult = $this->changekeyname($responseResult, 'counter', 'COUNT(*)');

        $tables[4] = [
            'head' => [
                '', 'stats_count', ''
            ],
            'body' => [
                [
                    'stats_total_mails_returned',
                    number_format(intval($table['-127']['counter'] ?? 0)),
                    implode('&nbsp;', $iconsMailReturned)
                ],
                [
                    'stats_recipient_unknown',
                    $this->showWithPercent(($responseResult['550']['counter'] ?? 0) + ($responseResult['553']['counter'] ?? 0), ($table['-127']['counter'] ?? 0)),
                    implode('&nbsp;', $iconsUnknownRecip)
                ],
                [
                    'stats_mailbox_full',
                    $this->showWithPercent(($responseResult['551']['counter'] ?? 0), ($table['-127']['counter'] ?? 0)),
                    implode('&nbsp;', $iconsMailbox)
                ],
                [
                    'stats_bad_host',
                    $this->showWithPercent(($responseResult['552']['counter'] ?? 0), ($table['-127']['counter'] ?? 0)),
                    implode('&nbsp;', $iconsBadhost)
                ],
                [
                    'stats_error_in_header',
                    $this->showWithPercent(($responseResult['554']['counter'] ?? 0), ($table['-127']['counter'] ?? 0)),
                    implode('&nbsp;', $iconsBadheader)
                ],
                [
                    'stats_reason_unkown',
                    $this->showWithPercent(($responseResult['-1']['counter'] ?? 0), ($table['-127']['counter'] ?? 0)),
                    implode('&nbsp;', $iconsUnknownReason)
                ]
            ]
        ]; 
        
        
        // Find all returned mail
        if ($this->returnList || $this->returnDisable || $this->returnCSV) {
            $rrows = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->findAllReturnedMail($row['uid']);
            $idLists = [
                'tt_address' => [], 
                'fe_users' => [], 
                'PLAINLIST' => []
            ];

            if(is_array($rrows)) {
                foreach($rrows as $rrow) {
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
            }

            if ($this->returnList) {
                if (count($idLists['tt_address'])) {
                    $output .= '<h3>' . $this->getLanguageService()->getLL('stats_emails') . '</h3>' . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address', $this->id, 1, $this->sys_dmail_uid);
                }
                if (count($idLists['fe_users'])) {
                    $output .= '<h3>' . $this->getLanguageService()->getLL('stats_website_users') . '</h3>' . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users', $this->id, 1, $this->sys_dmail_uid);
                }
                if (count($idLists['PLAINLIST'])) {
                    $output .= '<h3>' . $this->getLanguageService()->getLL('stats_plainlist') . '</h3>';
                    $output .= '<ul><li>' . join('</li><li>', $idLists['PLAINLIST']) . '</li></ul>';
                }
            }
            
            if ($this->returnDisable) {
                if (count($idLists['tt_address'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address');
                    $output .= '<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_emails_disabled');
                }
                if (count($idLists['fe_users'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users');
                    $output .= '<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_website_users_disabled');
                }
            }
            
            if ($this->returnCSV) {
                $emails = [];
                if (count($idLists['tt_address'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address');
                    foreach ($arr as $v) {
                        $emails[] = $v['email'];
                    }
                }
                if (count($idLists['fe_users'])) {
                    $arr=DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users');
                    foreach ($arr as $v) {
                        $emails[] = $v['email'];
                    }
                }
                if (count($idLists['PLAINLIST'])) {
                    $emails = array_merge($emails, $idLists['PLAINLIST']);
                }
                $output .= '<br />' . $this->getLanguageService()->getLL('stats_emails_returned_list') . '<br />';
                $output .= '<textarea style="width:460px;" rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
            }
        }
        
        // Find Unknown Recipient
        if ($this->unknownList || $this->unknownDisable || $this->unknownCSV) {
            $rrows = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->findUnknownRecipient($row['uid']);
            $idLists = [
                'tt_address' => [],
                'fe_users' => [],
                'PLAINLIST' => []
            ];

            if(is_array($rrows)) {
                foreach($rrows as $rrow) {
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
            }
                
            if ($this->unknownList) {
                if (count($idLists['tt_address'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_emails') . '<br />' . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address', $this->id, 1, $this->sys_dmail_uid);
                }
                if (count($idLists['fe_users'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_website_users') . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users', $this->id, 1, $this->sys_dmail_uid);
                }
                if (count($idLists['PLAINLIST'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_plainlist');
                    $output .= '<ul><li>' . join('</li><li>', $idLists['PLAINLIST']) . '</li></ul>';
                }
            }
            
            if ($this->unknownDisable) {
                if (count($idLists['tt_address'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address');
                    $output .= '<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_emails_disabled');
                }
                if (count($idLists['fe_users'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users');
                    $output .= '<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_website_users_disabled');
                }
            }
            
            if ($this->unknownCSV) {
                $emails = [];
                if (count($idLists['tt_address'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address');
                    foreach ($arr as $v) {
                        $emails[] = $v['email'];
                    }
                }
                if (count($idLists['fe_users'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users');
                    foreach ($arr as $v) {
                        $emails[] = $v['email'];
                    }
                }
                if (count($idLists['PLAINLIST'])) {
                    $emails = array_merge($emails, $idLists['PLAINLIST']);
                }
                $output .= '<br />' . $this->getLanguageService()->getLL('stats_emails_returned_unknown_recipient_list') . '<br />';
                $output .= '<textarea style="width:460px;" rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
            }
        }
        
        // Mailbox Full
        if ($this->fullList || $this->fullDisable || $this->fullCSV) {
            $rrows = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->findMailboxFull($row['uid']);
            $idLists = [
                'tt_address' => [],
                'fe_users' => [],
                'PLAINLIST' => []
            ];
            
            if(is_array($rrows)) {
                foreach($rrows as $rrow) {
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
            }
                
            if ($this->fullList) {
                if (count($idLists['tt_address'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_emails') . '<br />' . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address', $this->id, 1, $this->sys_dmail_uid);
                }
                if (count($idLists['fe_users'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_website_users') . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users', $this->id, 1, $this->sys_dmail_uid);
                }
                if (count($idLists['PLAINLIST'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_plainlist');
                    $output .= '<ul><li>' . join('</li><li>', $idLists['PLAINLIST']) . '</li></ul>';
                }
            }
            
            if ($this->fullDisable) {
                if (count($idLists['tt_address'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address');
                    $output .= '<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_emails_disabled');
                }
                if (count($idLists['fe_users'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users');
                    $output .= '<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_website_users_disabled');
                }
            }

            if ($this->fullCSV) {
                $emails = [];
                if (count($idLists['tt_address'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address');
                    foreach ($arr as $v) {
                        $emails[] = $v['email'];
                    }
                }
                if (count($idLists['fe_users'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users');
                    foreach ($arr as $v) {
                        $emails[] = $v['email'];
                    }
                }
                if (count($idLists['PLAINLIST'])) {
                    $emails = array_merge($emails, $idLists['PLAINLIST']);
                }
                $output .= '<br />' . $this->getLanguageService()->getLL('stats_emails_returned_mailbox_full_list') . '<br />';
                $output .= '<textarea style="width:460px;" rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
            }
        }
        
        // find Bad Host
        if ($this->badHostList || $this->badHostDisable || $this->badHostCSV) {
            $rrows = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->findBadHost($row['uid']);
            $idLists = [
                'tt_address' => [],
                'fe_users' => [],
                'PLAINLIST' => []
            ];

            if(is_array($rrows)) {
                foreach($rrows as $rrow) {
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
            }
                
            if ($this->badHostList) {
                if (count($idLists['tt_address'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_emails') . '<br />' . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address', $this->id, 1, $this->sys_dmail_uid);
                }
                if (count($idLists['fe_users'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_website_users') . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users', $this->id, 1, $this->sys_dmail_uid);
                }
                if (count($idLists['PLAINLIST'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_plainlist');
                    $output .= '<ul><li>' . join('</li><li>', $idLists['PLAINLIST']) . '</li></ul>';
                }
            }
            
            if ($this->badHostDisable) {
                if (count($idLists['tt_address'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address');
                    $output .= '<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_emails_disabled');
                }
                if (count($idLists['fe_users'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users');
                    $output .= '<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_website_users_disabled');
                }
            }
            
            if ($this->badHostCSV) {
                $emails = [];
                if (count($idLists['tt_address'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address');
                    foreach ($arr as $v) {
                        $emails[] = $v['email'];
                    }
                }
                
                if (count($idLists['fe_users'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users');
                    foreach ($arr as $v) {
                        $emails[] = $v['email'];
                    }
                }
                
                if (count($idLists['PLAINLIST'])) {
                    $emails = array_merge($emails, $idLists['PLAINLIST']);
                }
                
                $output .= '<br />' . $this->getLanguageService()->getLL('stats_emails_returned_bad_host_list') . '<br />';
                $output .= '<textarea style="width:460px;" rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
            }
        }
        
        // find Bad Header
        if ($this->badHeaderList || $this->badHeaderDisable || $this->badHeaderCSV) {
            $rrows = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->findBadHeader($row['uid']);
            $idLists = [
                'tt_address' => [],
                'fe_users' => [],
                'PLAINLIST' => []
            ];

            if(is_array($rrows)) {
                foreach($rrows as $rrow) {
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
            }
                
            if ($this->badHeaderList) {
                if (count($idLists['tt_address'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_emails') . '<br />' . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address', $this->id, 1, $this->sys_dmail_uid);
                }
                if (count($idLists['fe_users'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_website_users') . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users', $this->id, 1, $this->sys_dmail_uid);
                }
                if (count($idLists['PLAINLIST'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_plainlist');
                    $output .= '<ul><li>' . join('</li><li>', $idLists['PLAINLIST']) . '</li></ul>';
                }
            }
                
            if ($this->badHeaderDisable) {
                if (count($idLists['tt_address'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address');
                    $output .= '<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_emails_disabled');
                }
                if (count($idLists['fe_users'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users');
                    $output .= '<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_website_users_disabled');
                }
            }
            
            if ($this->badHeaderCSV) {
                $emails = [];
                if (count($idLists['tt_address'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address');
                    foreach ($arr as $v) {
                        $emails[] = $v['email'];
                    }
                }
                if (count($idLists['fe_users'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users');
                    foreach ($arr as $v) {
                        $emails[] = $v['email'];
                    }
                }
                if (count($idLists['PLAINLIST'])) {
                    $emails = array_merge($emails, $idLists['PLAINLIST']);
                }
                $output .= '<br />' . $this->getLanguageService()->getLL('stats_emails_returned_bad_header_list') .  '<br />';
                $output .= '<textarea style="width:460px;" rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
            }
        }
        
        // find Unknown Reasons
        // TODO: list all reason
        if ($this->reasonUnknownList || $this->reasonUnknownDisable || $this->reasonUnknownCSV) {
            $rrows = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->findUnknownReasons($row['uid']);
            $idLists = [
                'tt_address' => [],
                'fe_users' => [],
                'PLAINLIST' => []
            ];

            if(is_array($rrows)) {
                foreach($rrows as $rrow) {
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
            }
                
            if ($this->reasonUnknownList) {
                if (count($idLists['tt_address'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_emails') . '<br />' . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address', $this->id, 1, $this->sys_dmail_uid);
                }
                if (count($idLists['fe_users'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_website_users') . DirectMailUtility::getRecordList(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users', $this->id, 1, $this->sys_dmail_uid);
                }
                if (count($idLists['PLAINLIST'])) {
                    $output .= '<br />' . $this->getLanguageService()->getLL('stats_plainlist');
                    $output .= '<ul><li>' . join('</li><li>', $idLists['PLAINLIST']) . '</li></ul>';
                }
            }
            
            if ($this->reasonUnknownDisable) {
                if (count($idLists['tt_address'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address'), 'tt_address');
                    $output .= '<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_emails_disabled');
                }
                if (count($idLists['fe_users'])) {
                    $c = $this->disableRecipients(DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users'), 'fe_users');
                    $output .= '<br />' . $c . ' ' . $this->getLanguageService()->getLL('stats_website_users_disabled');
                }
            }
            
            if ($this->reasonUnknownCSV) {
                $emails = [];
                if (count($idLists['tt_address'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['tt_address'], 'tt_address');
                    foreach ($arr as $v) {
                        $emails[] = $v['email'];
                    }
                }
                if (count($idLists['fe_users'])) {
                    $arr = DirectMailUtility::fetchRecordsListValues($idLists['fe_users'], 'fe_users');
                    foreach ($arr as $v) {
                        $emails[] = $v['email'];
                    }
                }
                if (count($idLists['PLAINLIST'])) {
                    $emails = array_merge($emails, $idLists['PLAINLIST']);
                }
                $output .= '<br />' . $this->getLanguageService()->getLL('stats_emails_returned_reason_unknown_list') . '<br />';
                $output .= '<textarea style="width:460px;" rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
            }
        }
        
        /**
         * Hook for cmd_stats_postProcess
         * insert a link to open extended importer
         */
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod4']['cmd_stats'] ?? false)) {
            $hookObjectsArr = [];
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod4']['cmd_stats'] as $classRef) {
                $hookObjectsArr[] = GeneralUtility::makeInstance($classRef);
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

        return ['out' => $output, 'compactView' => $compactView, 'thisurl' => $thisurl, 'tables' => $tables];
    }
    
    /**
     * Wrap a string with a link
     *
     * @param string $str String to be wrapped with a link
     * @param int $uid Record uid to be link
     * @param string $aTitle Title param of the link tag
     *
     * @return string wrapped string as a link
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function linkDMail_record($str, $uid, $aTitle='')
    {
        $moduleUrl = $this->buildUriFromRoute(
            $this->moduleName,
            [
                'id' => $this->id,
                'sys_dmail_uid' => $uid,
                'cmd' => 'stats',
                'SET[dmail_mode]' => 'direct'
            ]
        );
        return '<a title="' . htmlspecialchars($aTitle) . '" href="' . $moduleUrl . '">' . htmlspecialchars($str) . '</a>';
    }
    
    /**
     * Set up URL variables for this $row.
     *
     * @param array $row DB records
     *
     * @return void
     */
    protected function setURLs(array $row)
    {
        // Finding the domain to use
        $this->urlbase = DirectMailUtility::getUrlBase((int)$row['page']);
        
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
     * count total recipient from the query_info
     */
    protected function countTotalRecipientFromQueryInfo(string $queryInfo): int
    {
        $totalRecip = 0;
        $idLists = unserialize($queryInfo);
        if(is_array($idLists)) {
            foreach ($idLists['id_lists'] as $idArray) {
                $totalRecip += count($idArray);
            }
        }
        return $totalRecip;
    }
    
    /**
     * Show the compact information of a direct mail record
     *
     * @param array $row Direct mail record
     *
     * @return string The compact infos of the direct mail record
     */
    protected function directMail_compactView($row)
    {
        $dmailInfo = '';
        // Render record:
        if ($row['type']) {
            $dmailData = $row['plainParams'] . ', ' . $row['HTMLParams'];
        } else {
            $page = BackendUtility::getRecord('pages', $row['page'], 'title');
            $dmailData = $row['page'] . ', ' . htmlspecialchars($page['title']);
            $dmailInfo = DirectMailUtility::fName('plainParams') . ' ' . htmlspecialchars($row['plainParams'] . LF . DirectMailUtility::fName('HTMLParams') . $row['HTMLParams']) . '; ' . LF;
        }

        $res = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->selectSysDmailMaillogsCompactView($row['uid']);
        
        $data = [
            'icon'          => $this->iconFactory->getIconForRecord('sys_dmail', $row, Icon::SIZE_SMALL)->render(),
            'iconInfo'      => $this->iconFactory->getIcon('actions-document-info', Icon::SIZE_SMALL)->render(),
            'subject'       => htmlspecialchars($row['subject']),
            'from_name'     => htmlspecialchars($row['from_name']),
            'from_email'    => htmlspecialchars($row['from_email']),
            'replyto_name'  => htmlspecialchars($row['replyto_name']),
            'replyto_email' => htmlspecialchars($row['replyto_email']),
            'type'          => BackendUtility::getProcessedValue('sys_dmail', 'type', $row['type']),
            'dmailData'     => $dmailData,
            'dmailInfo'     => $dmailInfo,
            'priority'      => BackendUtility::getProcessedValue('sys_dmail', 'priority', $row['priority']),
            'encoding'      => BackendUtility::getProcessedValue('sys_dmail', 'encoding', $row['encoding']),
            'charset'       => BackendUtility::getProcessedValue('sys_dmail', 'charset', $row['charset']),
            'sendOptions'   => BackendUtility::getProcessedValue('sys_dmail', 'sendOptions', $row['sendOptions']) . ($row['attachment'] ? '; ' : ''),
            'attachment'    => BackendUtility::getProcessedValue('sys_dmail', 'attachment', $row['attachment']),
            'flowedFormat'  => BackendUtility::getProcessedValue('sys_dmail', 'flowedFormat', $row['flowedFormat']),
            'includeMedia'  => BackendUtility::getProcessedValue('sys_dmail', 'includeMedia', $row['includeMedia']),
            'delBegin'      => $row['scheduled_begin'] ? BackendUtility::datetime($row['scheduled_begin']) : '-',
            'delEnd'        => $row['scheduled_end'] ? BackendUtility::datetime($row['scheduled_begin']) : '-',
            'totalRecip'    => $this->countTotalRecipientFromQueryInfo($row['query_info']),
            'sentRecip'     => count($res),
            'organisation'  => htmlspecialchars($row['organisation']),
            'return_path'   => htmlspecialchars($row['return_path'])
        ];
        return $data;
    }
    
    /**
     * Switch the key of an array
     *
     * @return $array
     */
    private function changekeyname($array, $newkey, $oldkey)
    {
        foreach ($array as $key => $value)
        {
            if (is_array($value)) {
                $array[$key] = $this->changekeyname($value,$newkey,$oldkey);
            }
            else {
                $array[$newkey] =  $array[$oldkey];
            }
        }
        unset($array[$oldkey]);
        return $array;
    }
    
    /**
     * Make a percent from the given parameters
     *
     * @param int $pieces Number of pieces
     * @param int $total Total of pieces
     *
     * @return string show number of pieces and the percent
     */
    protected function showWithPercent($pieces, $total)
    {
        $total = intval($total);
        $str = $pieces ? number_format(intval($pieces)) : '0';
        if ($total) {
            $str .= ' / ' . number_format(($pieces/$total*100), 2) . '%';
        }
        return $str;
    }
    
    /**
     * Write the statistic to a temporary table
     *
     * @param array $mrow DB mail records
     *
     * @return void
     */
    protected function makeStatTempTableContent(array $mrow)
    {
        // Remove old:
        
        $connection = $this->getConnection('cache_sys_dmail_stat');
        $connection->delete(
            'cache_sys_dmail_stat', // from
            [ 'mid' => intval($mrow['uid']) ] // where
        );
        
        $rows = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->selectStatTempTableContent($mrow['uid']);
        
        $currentRec = '';
        $recRec = [];

        if(is_array($rows)) {
            foreach($rows as $row) {
                $thisRecPointer = $row['rtbl'] . $row['rid'];
                
                if ($thisRecPointer != $currentRec) {
                    $recRec = [
                        'mid'         => intval($mrow['uid']),
                        'rid'         => $row['rid'],
                        'rtbl'        => $row['rtbl'],
                        'pings'       => [],
                        'plain_links' => [],
                        'html_links'  => [],
                        'response'    => [],
                        'links'       => []
                    ];
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
                            $recRec['firstlink_time'] = $recRec['firstlink_time'] ? $row['tstamp'] - $recRec['firstlink_time'] : 0;
                        } elseif (!$recRec['secondlink']) {
                            $recRec['secondlink'] = $row['url_id'];
                            $recRec['secondlink_time'] = intval(@max($recRec['pings']));
                            $recRec['secondlink_time'] = $recRec['secondlink_time'] ? $row['tstamp'] - $recRec['secondlink_time'] : 0;
                        } elseif (!$recRec['thirdlink']) {
                            $recRec['thirdlink'] = $row['url_id'];
                            $recRec['thirdlink_time'] = intval(@max($recRec['pings']));
                            $recRec['thirdlink_time'] = $recRec['thirdlink_time'] ? $row['tstamp'] - $recRec['thirdlink_time'] : 0;
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
        }
        
        $this->storeRecRec($recRec);
    }
    
    /**
     * Insert statistic to a temporary table
     *
     * @param array $recRec Statistic array
     *
     * @return void
     */
    protected function storeRecRec(array $recRec)
    {
        if (is_array($recRec)) {
            $recRec['pings_first'] = empty($recRec['pings']) ? 0 : intval(@min($recRec['pings']));
            $recRec['pings_last']  = empty($recRec['pings']) ? 0 : intval(@max($recRec['pings']));
            $recRec['pings'] = count($recRec['pings']);
            
            $recRec['html_links_first'] = empty($recRec['html_links']) ? 0 : intval(@min($recRec['html_links']));
            $recRec['html_links_last']  = empty($recRec['html_links']) ? 0 : intval(@max($recRec['html_links']));
            $recRec['html_links'] = count($recRec['html_links']);
            
            $recRec['plain_links_first'] = empty($recRec['plain_links']) ? 0 : intval(@min($recRec['plain_links']));
            $recRec['plain_links_last']  = empty($recRec['plain_links']) ? 0 : intval(@max($recRec['plain_links']));
            $recRec['plain_links'] = count($recRec['plain_links']);
            
            $recRec['links_first'] = empty($recRec['links']) ? 0 : intval(@min($recRec['links']));
            $recRec['links_last']  = empty($recRec['links']) ? 0 : intval(@max($recRec['links']));
            $recRec['links'] = count($recRec['links']);
            
            $recRec['response_first'] = DirectMailUtility::intInRangeWrapper((int)((int)(empty($recRec['response']) ? 0 : @min($recRec['response'])) - $recRec['tstamp']), 0);
            $recRec['response_last']  = DirectMailUtility::intInRangeWrapper((int)((int)(empty($recRec['response']) ? 0 : @max($recRec['response'])) - $recRec['tstamp']), 0);
            $recRec['response'] = count($recRec['response']);
            
            $recRec['time_firstping'] = DirectMailUtility::intInRangeWrapper((int)($recRec['pings_first'] - $recRec['tstamp']), 0);
            $recRec['time_lastping']  = DirectMailUtility::intInRangeWrapper((int)($recRec['pings_last'] - $recRec['tstamp']), 0);
            
            $recRec['time_first_link'] = DirectMailUtility::intInRangeWrapper((int)($recRec['links_first'] - $recRec['tstamp']), 0);
            $recRec['time_last_link']  = DirectMailUtility::intInRangeWrapper((int)($recRec['links_last'] - $recRec['tstamp']), 0);
            
            $connection = $this->getConnection('cache_sys_dmail_stat');
            $connection->insert(
                'cache_sys_dmail_stat',
                $recRec
            );
        }
    }
}