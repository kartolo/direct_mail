<?php
namespace DirectMailTeam\DirectMail\Module;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

class StatisticsController extends MainController
{   
    public function indexAction(ServerRequestInterface $request) : ResponseInterface
    {
        $this->view = $this->configureTemplatePaths('Statistics');
        
        $this->init($request);
        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');
        
        if (($this->id && $this->access) || ($this->isAdmin() && !$this->id)) {
            $module = $this->getModulName();

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
        $theOutput = '';
        
        if (!$this->sys_dmail_uid) {
            $theOutput = $this->displayPageInfo();
        } else {
            $table = 'sys_dmail';
            $queryBuilder = $this->getQueryBuilder($table);
            $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            $res = $queryBuilder->select('*')
            ->from('sys_dmail')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($this->id, \PDO::PARAM_INT)),
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($this->sys_dmail_uid, \PDO::PARAM_INT))
            )
    //      debug($statement->getSQL());
    //      debug($statement->getParameters());
            ->execute();
            
//          $this->noView = 0;
            if (($row = $res->fetch())) {
                // Set URL data for commands
                $this->setURLs($row);
                
                // COMMAND:
                switch ($this->cmd) {
                    case 'displayUserInfo':
                        $theOutput = $this->displayUserInfo();
                        break;
                    case 'stats':
                        $theOutput = $this->stats($row);
                        break;
                    default:
                        // Hook for handling of custom direct mail commands:
                        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXT']['directmail']['handledirectmailcmd-' . $this->cmd])) {
                            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXT']['directmail']['handledirectmailcmd-' . $this->cmd] as $funcRef) {
                                $params = ['pObj' => &$this];
                                $theOutput = GeneralUtility::callUserFunction($funcRef, $params, $this);
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
        $table = 'sys_dmail';
        $queryBuilder = $this->getQueryBuilder($table);
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        
        $res = $queryBuilder
        ->selectLiteral('sys_dmail.uid', 'sys_dmail.subject', 'sys_dmail.scheduled', 'sys_dmail.scheduled_begin', 'sys_dmail.scheduled_end', 'COUNT(sys_dmail_maillog.mid) AS count')
        ->from('sys_dmail','sys_dmail')
        ->leftJoin(
            'sys_dmail',
            'sys_dmail_maillog',
            'sys_dmail_maillog',
            $queryBuilder->expr()->eq('sys_dmail.uid', $queryBuilder->quoteIdentifier('sys_dmail_maillog.mid'))
        )
        ->add('where','sys_dmail.pid = ' . intval($this->id) .
            ' AND sys_dmail.type IN (0,1)' .
            ' AND sys_dmail.issent = 1'.
            ' AND sys_dmail_maillog.response_type = 0'.
            ' AND sys_dmail_maillog.html_sent > 0')
            ->groupBy('sys_dmail_maillog.mid')
            ->orderBy('sys_dmail.scheduled','DESC')
            ->addOrderBy('sys_dmail.scheduled_begin','DESC')
            ->execute()
            ->fetchAll();

        $out ='<table border="0" cellpadding="0" cellspacing="0" class="table table-striped table-hover">';
        $out .='<thead>
					<th>&nbsp;</th>
					<th><b>' . $this->getLanguageService()->getLL('stats_overview_subject') . '</b></th>
					<th><b>' . $this->getLanguageService()->getLL('stats_overview_scheduled') . '</b></th>
					<th><b>' . $this->getLanguageService()->getLL('stats_overview_delivery_begun') . '</b></th>
					<th><b>' . $this->getLanguageService()->getLL('stats_overview_delivery_ended') . '</b></th>
					<th nowrap="nowrap"><b>' . $this->getLanguageService()->getLL('stats_overview_total_sent') . '</b></th>
					<th><b>' . $this->getLanguageService()->getLL('stats_overview_status') . '</b></th>
				</thead>';
        
        if ($res) {
            foreach ($res as $row)  {
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
					<td>' .  $this->moduleTemplate->getIconFactory()->getIconForRecord('sys_dmail', $row, Icon::SIZE_SMALL)->render() . '</td>
					<td>' . $this->linkDMail_record(GeneralUtility::fixed_lgd_cs($row['subject'], 30) . '  ', $row['uid'], $row['subject']) . '&nbsp;&nbsp;</td>
					<td>' . BackendUtility::datetime($row['scheduled']) . '</td>
					<td>' . ($row['scheduled_begin']?BackendUtility::datetime($row['scheduled_begin']):'&nbsp;') . '</td>
					<td>' . ($row['scheduled_end']?BackendUtility::datetime($row['scheduled_end']):'&nbsp;') . '</td>
					<td>' . ($row['count']?$row['count']:'&nbsp;') . '</td>
					<td>' . $sent . '</td>
				</tr>';
            }
        }
        $out .= '</table>';
        $out = '<h3>' . $this->getLanguageService()->getLL('stats_overview_choose') . '</h3>' .$out;
        $out .= '<div style="padding-top: 20px;"></div>';

        return $out;
    }
    
    /**
     * Shows user's info and categories
     *
     * @return string HTML showing user's info and the categories
     */
    protected function displayUserInfo()
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
                    $data = [];
                    if (is_array($indata['categories'])) {
                        reset($indata['categories']);
                        foreach ($indata['categories'] as $recValues) {
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
        
        switch ($table) {
            case 'tt_address':
                
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_language');
                $res = $queryBuilder
                ->select('tt_address.*')
                ->from('tt_address','tt_address')
                ->leftjoin(
                    'tt_address',
                    'pages',
                    'pages',
                    $queryBuilder->expr()->eq('pages.uid', $queryBuilder->quoteIdentifier('tt_address.pid'))
                    )
                    ->add('where','tt_address.uid=' . intval($uid) .
                        ' AND ' . $this->perms_clause . ' AND pages.deleted = 0')
                        ->execute();
                        break;
            case 'fe_users':
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('fe_users');
                $res = $queryBuilder
                ->select('fe_users.*')
                ->from('fe_users','fe_users')
                ->leftjoin(
                    'fe_users',
                    'pages',
                    'pages',
                    $queryBuilder->expr()->eq('pages.uid', $queryBuilder->quoteIdentifier('fe_users.pid'))
                    )
                    ->add('where','fe_users.uid=' . intval($uid) .
                        ' AND ' . $this->perms_clause . ' AND pages.deleted = 0')
                        ->execute();
                        break;
            default:
                // do nothing
        }
        
        $row = [];
        if ($res) {
            $row = $res->fetch();
        }
        
        $theOutput = '';
        if (is_array($row)) {
            $categories = '';
            
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($mmTable);
            $resCat = $queryBuilder
            ->select('uid_foreign')
            ->from($mmTable)
            ->add('where','uid_local=' . $row['uid'])
            ->execute();
            while (($rowCat = $resCat->fetch())) {
                $categories .= $rowCat['uid_foreign'] . ',';
            }
            $categories = rtrim($categories, ',');
            
            $editOnClickLink = DirectMailUtility::getEditOnClickLink([
                'edit' => [
                    $table => [
                        $row['uid'] => 'edit',
                    ],
                ],
                'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI'),
            ]);
            
            $out = '';
            $out .=  $this->moduleTemplate->getIconFactory()->getIconForRecord($table, $row)->render() . htmlspecialchars($row['name'] . ' <' . $row['email'] . '>');
            $out .= '&nbsp;&nbsp;<a href="#" onClick="' . $editOnClickLink . '" title="' . $this->getLanguageService()->getLL('dmail_edit') . '">' .
                $this->moduleTemplate->getIconFactory()->getIcon('actions-open', Icon::SIZE_SMALL) .
                $this->getLanguageService()->getLL('dmail_edit') . '</b></a>';
                $theOutput = '<h3>' . $this->getLanguageService()->getLL('subscriber_info') . '</h3>' . $out;
                
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
                    '<input type="hidden" name="cmd" value="' . $this->cmd . '" /><br />' .
                    '<input type="submit" name="submit" value="' . htmlspecialchars($this->getLanguageService()->getLL('subscriber_profile_update')) . '" />';
                $theOutput .= '<div style="padding-top: 20px;"></div>';
                $theOutput .= '<h3>' . $this->getLanguageService()->getLL('subscriber_profile') . '</h3>' .
                    $this->getLanguageService()->getLL('subscriber_profile_instructions') . '<br /><br />' . $out;
        }
        
        return $theOutput;
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
        if (GeneralUtility::_GP('recalcCache')) {
            $this->makeStatTempTableContent($row);
        }
        /** @var UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $thisurl = $uriBuilder->buildUriFromRoute(
            $this->moduleName,
            [
                'id' => $this->id,
                'sys_dmail_uid' => $row['uid'],
                'cmd' => $this->cmd,
                'recalcCache' => 1
            ]
            );
        $output = $this->directMail_compactView($row);
        
        // *****************************
        // Mail responses, general:
        // *****************************
        
        $mailingId = intval($row['uid']);
        $fieldRows = 'response_type';
        $addFieldRows = '*';
        $tableRows =  'sys_dmail_maillog';
        $whereRows = 'mid=' . $mailingId;
        $groupByRows = 'response_type';
        $orderByRows = '';
        $queryArray = [$fieldRows, $addFieldRows, $tableRows, $whereRows, $groupByRows, $orderByRows];
        
        $table = $this->getQueryRows($queryArray, 'response_type');
        
        // Plaintext/HTML
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_maillog');
        
        $res = $queryBuilder
        ->count('*')
        ->addSelect('html_sent')
        ->from('sys_dmail_maillog')
        ->add('where','mid=' . $mailingId . ' AND response_type=0')
        ->groupBy('html_sent')
        ->execute()
        ->fetchAll();
        
        /* this function is called to change the key from 'COUNT(*)' to 'counter' */
        $res = $this->changekeyname($res,'counter','COUNT(*)');
        
        $textHtml = [];
        foreach($res as $row2){
            // 0:No mail; 1:HTML; 2:TEXT; 3:HTML+TEXT
            $textHtml[$row2['html_sent']] = $row2['counter'];
        }
        
        // Unique responses, html
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_maillog');
        
        $res = $queryBuilder
        ->count('*')
        ->from('sys_dmail_maillog')
        ->add('where','mid=' . $mailingId . ' AND response_type=1')
        ->groupBy('rid')
        ->addGroupBy('rtbl')
        ->orderBy('COUNT(*)')
        ->execute()
        ->fetchAll();
        
        $uniqueHtmlResponses = count($res);//sql_num_rows($res);
        
        // Unique responses, Plain
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_maillog');
        
        $res = $queryBuilder
        ->count('*')
        ->from('sys_dmail_maillog')
        ->add('where','mid=' . $mailingId . ' AND response_type=2')
        ->groupBy('rid')
        ->addGroupBy('rtbl')
        ->orderBy('COUNT(*)')
        ->execute()
        ->fetchAll();
        $uniquePlainResponses = count($res); //sql_num_rows($res);
        
        // Unique responses, pings
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_maillog');
        
        $res = $queryBuilder
        ->count('*')
        ->from('sys_dmail_maillog')
        ->add('where','mid=' . $mailingId . ' AND response_type=-1')
        ->groupBy('rid')
        ->addGroupBy('rtbl')
        ->orderBy('COUNT(*)')
        ->execute()
        ->fetchAll();
        $uniquePingResponses = count($res);//sql_num_rows($res);
        ;
        
        $tblLines = [];
        $tblLines[] = ['',$this->getLanguageService()->getLL('stats_total'),$this->getLanguageService()->getLL('stats_HTML'),$this->getLanguageService()->getLL('stats_plaintext')];
        
        $totalSent = intval($textHtml['1'] + $textHtml['2'] + $textHtml['3']);
        $htmlSent = intval($textHtml['1']+$textHtml['3']);
        $plainSent = intval($textHtml['2']);
        
        $tblLines[] = array($this->getLanguageService()->getLL('stats_mails_sent'),$totalSent,$htmlSent,$plainSent);
        $tblLines[] = array($this->getLanguageService()->getLL('stats_mails_returned'),$this->showWithPercent($table['-127']['counter'], $totalSent));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_HTML_mails_viewed'),'',$this->showWithPercent($uniquePingResponses, $htmlSent));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_unique_responses'),$this->showWithPercent($uniqueHtmlResponses+$uniquePlainResponses, $totalSent),$this->showWithPercent($uniqueHtmlResponses, $htmlSent),$this->showWithPercent($uniquePlainResponses, $plainSent?$plainSent:$htmlSent));
        
        $output.='<br /><h2>' . $this->getLanguageService()->getLL('stats_general_information') . '</h2>';
        $output.= DirectMailUtility::formatTable($tblLines, ['nowrap', 'nowrap', 'nowrap', 'nowrap'], 1, []);
        
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
        $fieldRows = 'url_id';
        $addFieldRows = '*';
        $tableRows =  'sys_dmail_maillog';
        $whereRows = 'mid=' . intval($row['uid']) . ' AND response_type=1';
        $groupByRows = 'url_id';
        $orderByRows = 'COUNT(*)';
        //$queryArray = array('url_id,count(*) as counter', 'sys_dmail_maillog', 'mid=' . intval($row['uid']) . ' AND response_type=1', 'url_id', 'counter');
        $queryArray = [$fieldRows, $addFieldRows, $tableRows, $whereRows, $groupByRows, $orderByRows];
        $htmlUrlsTable=$this->getQueryRows($queryArray, 'url_id');
        
        // Most popular links, plain:
        $fieldRows = 'url_id';
        $addFieldRows = '*';
        $tableRows =  'sys_dmail_maillog';
        $whereRows = 'mid=' . intval($row['uid']) . ' AND response_type=2';
        $groupByRows = 'url_id';
        $orderByRows = 'COUNT(*)';
        //$queryArray = array('url_id,count(*) as counter', 'sys_dmail_maillog', 'mid=' . intval($row['uid']) . ' AND response_type=2', 'url_id', 'counter');
        $queryArray = [$fieldRows, $addFieldRows, $tableRows, $whereRows, $groupByRows, $orderByRows];
        $plainUrlsTable=$this->getQueryRows($queryArray, 'url_id');
        
        
        // Find urls:
        $unpackedMail = unserialize(base64_decode($row['mailContent']));
        // this array will include a unique list of all URLs that are used in the mailing
        $urlArr = [];
        
        $urlMd5Map = [];
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
        
        $tblLines = [];
        $tblLines[] = array('',$this->getLanguageService()->getLL('stats_total'),$this->getLanguageService()->getLL('stats_HTML'),$this->getLanguageService()->getLL('stats_plaintext'));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_total_responses'),$table['1']['counter'] + $table['2']['counter'],$table['1']['counter']?$table['1']['counter']:'0',$table['2']['counter']?$table['2']['counter']:'0');
        $tblLines[] = array($this->getLanguageService()->getLL('stats_unique_responses'),$this->showWithPercent($uniqueHtmlResponses+$uniquePlainResponses, $totalSent), $this->showWithPercent($uniqueHtmlResponses, $htmlSent), $this->showWithPercent($uniquePlainResponses, $plainSent?$plainSent:$htmlSent));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_links_clicked_per_respondent'),
            ($uniqueHtmlResponses+$uniquePlainResponses ? number_format(($table['1']['counter']+$table['2']['counter'])/($uniqueHtmlResponses+$uniquePlainResponses), 2) : '-'),
            ($uniqueHtmlResponses  ? number_format(($table['1']['counter'])/($uniqueHtmlResponses), 2)  : '-'),
            ($uniquePlainResponses ? number_format(($table['2']['counter'])/($uniquePlainResponses), 2) : '-')
        );
        
        $output.='<br /><h2>' . $this->getLanguageService()->getLL('stats_response') . '</h2>';
        $output.=DirectMailUtility::formatTable($tblLines, ['nowrap', 'nowrap', 'nowrap', 'nowrap'], 1, [0, 0, 0, 0]);
        
        arsort($urlCounter['total']);
        arsort($urlCounter['html']);
        arsort($urlCounter['plain']);
        reset($urlCounter['total']);
        
        $tblLines = [];
        $tblLines[] = array('',$this->getLanguageService()->getLL('stats_HTML_link_nr'),$this->getLanguageService()->getLL('stats_plaintext_link_nr'),$this->getLanguageService()->getLL('stats_total'),$this->getLanguageService()->getLL('stats_HTML'),$this->getLanguageService()->getLL('stats_plaintext'),'');
        
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
            
            $img = '<a href="' . $urlstr . '" target="_blank">' .  $this->moduleTemplate->getIconFactory()->getIcon('apps-toolbar-menu-search', Icon::SIZE_SMALL) . '</a>';
            
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
                $img = '<a href="' . htmlspecialchars($link) . '" target="_blank">' .  $this->moduleTemplate->getIconFactory()->getIcon('apps-toolbar-menu-search', Icon::SIZE_SMALL) . '</a>';
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
                $output .= DirectMailUtility::formatTable($tblLines, array('nowrap', 'nowrap width="100"', 'nowrap width="100"', 'nowrap', 'nowrap', 'nowrap', 'nowrap'), 1, [1, 0, 0, 0, 0, 0, 1]);
            }
        }

        // ******************
        // Returned mails
        // ******************
        
        // The icons:
        $listIcons = $this->moduleTemplate->getIconFactory()->getIcon('actions-system-list-open', Icon::SIZE_SMALL);
        $csvIcons  = $this->moduleTemplate->getIconFactory()->getIcon('actions-document-export-csv', Icon::SIZE_SMALL);
        $hideIcons = $this->moduleTemplate->getIconFactory()->getIcon('actions-lock', Icon::SIZE_SMALL);
        
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
        $fieldRows = 'return_code';
        $addFieldRows = '*';
        $tableRows =  'sys_dmail_maillog';
        $whereRows = 'mid=' . intval($row['uid']) . ' AND response_type=-127';
        $groupByRows = 'return_code';
        $orderByRows = '';
        $queryArray = [$fieldRows, $addFieldRows, $tableRows, $whereRows, $groupByRows, $orderByRows];
        //$queryArray = array('COUNT(*) as counter'.','.'return_code', 'sys_dmail_maillog', 'mid=' . intval($row['uid']) . ' AND response_type=-127', 'return_code');
        $responseResult = $this->getQueryRows($queryArray, 'return_code');
        
        $tblLines = [];
        $tblLines[] = array('',$this->getLanguageService()->getLL('stats_count'),'');
        $tblLines[] = array($this->getLanguageService()->getLL('stats_total_mails_returned'), ($table['-127']['counter']?number_format(intval($table['-127']['counter'])):'0'), implode('&nbsp;', $iconsMailReturned));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_recipient_unknown'), $this->showWithPercent($responseResult['550']['counter']+$responseResult['553']['counter'], $table['-127']['counter']), implode('&nbsp;', $iconsUnknownRecip));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_mailbox_full'), $this->showWithPercent($responseResult['551']['counter'], $table['-127']['counter']), implode('&nbsp;', $iconsMailbox));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_bad_host'), $this->showWithPercent($responseResult['552']['counter'], $table['-127']['counter']), implode('&nbsp;', $iconsBadhost));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_error_in_header'), $this->showWithPercent($responseResult['554']['counter'], $table['-127']['counter']),implode('&nbsp;', $iconsBadheader));
        $tblLines[] = array($this->getLanguageService()->getLL('stats_reason_unkown'), $this->showWithPercent($responseResult['-1']['counter'], $table['-127']['counter']),implode('&nbsp;', $iconsUnknownReason));
        
        $output.='<br /><h2>' . $this->getLanguageService()->getLL('stats_mails_returned') . '</h2>';
        $output .= DirectMailUtility::formatTable($tblLines, ['nowrap', 'nowrap', ''], 1, [0, 0, 1]);
        
        // Find all returned mail
        if (GeneralUtility::_GP('returnList')||GeneralUtility::_GP('returnDisable')||GeneralUtility::_GP('returnCSV')) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_dmail_maillog');
            $res =  $queryBuilder
            ->select('rid','rtbl','email')
            ->from('sys_dmail_maillog')
            ->add('where','mid=' . intval($row['uid']) .
                ' AND response_type=-127')
                ->execute();
                
                $idLists = [];
                
                while (($rrow = $res->fetch())) {
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
                    $emails=[];
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
                    $output .= '<textarea style="width:460px;" rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
                }
        }
        
        // Find Unknown Recipient
        if (GeneralUtility::_GP('unknownList')||GeneralUtility::_GP('unknownDisable')||GeneralUtility::_GP('unknownCSV')) {
            
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_dmail_maillog');
            $res =  $queryBuilder
            ->select('rid','rtbl','email')
            ->from('sys_dmail_maillog')
            ->add('where','mid=' . intval($row['uid']) .
                ' AND response_type=-127' .
                ' AND (return_code=550 OR return_code=553)')
                ->execute();
                $idLists = [];
                while (($rrow = $res->fetch())) {
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
                    $emails = [];
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
                    $output .='<textarea style="width:460px;" rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
                }
        }
        
        // Mailbox Full
        if (GeneralUtility::_GP('fullList')||GeneralUtility::_GP('fullDisable')||GeneralUtility::_GP('fullCSV')) {
            
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_dmail_maillog');
            $res =  $queryBuilder
            ->select('rid','rtbl','email')
            ->from('sys_dmail_maillog')
            ->add('where','mid=' . intval($row['uid']) .
                ' AND response_type=-127' .
                ' AND return_code=551')
                ->execute();
                $idLists = [];
                while (($rrow = $res->fetch())) {
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
                    $emails=[];
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
                    $output .='<textarea style="width:460px;" rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
                }
        }
        
        // find Bad Host
        if (GeneralUtility::_GP('badHostList')||GeneralUtility::_GP('badHostDisable')||GeneralUtility::_GP('badHostCSV')) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_dmail_maillog');
            $res =  $queryBuilder
            ->select('rid','rtbl','email')
            ->from('sys_dmail_maillog')
            ->add('where','mid=' . intval($row['uid']) .
                ' AND response_type=-127' .
                ' AND return_code=552')
                ->execute();
                $idLists = [];
                while (($rrow = $res->fetch())) {
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
                    $emails = [];
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
                    $output .= '<textarea style="width:460px;" rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
                }
        }
        
        // find Bad Header
        if (GeneralUtility::_GP('badHeaderList')||GeneralUtility::_GP('badHeaderDisable')||GeneralUtility::_GP('badHeaderCSV')) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_dmail_maillog');
            $res =  $queryBuilder
            ->select('rid','rtbl','email')
            ->from('sys_dmail_maillog')
            ->add('where','mid=' . intval($row['uid']) .
                ' AND response_type=-127' .
                ' AND return_code=554')
                ->execute();
                
                $idLists = [];
                while (($rrow = $res->fetch())) {
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
                    $emails = [];
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
                    $output .= '<textarea style="width:460px;" rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
                }
        }
        
        // find Unknown Reasons
        // TODO: list all reason
        if (GeneralUtility::_GP('reasonUnknownList')||GeneralUtility::_GP('reasonUnknownDisable')||GeneralUtility::_GP('reasonUnknownCSV')) {
            
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_dmail_maillog');
            $res =  $queryBuilder
            ->select('rid','rtbl','email')
            ->from('sys_dmail_maillog')
            ->add('where','mid=' . intval($row['uid']) .
                ' AND response_type=-127' .
                ' AND return_code=-1')
                ->execute();
                $idLists = [];
                while (($rrow = $res->fetch())) {
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
                    $emails = [];
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
                    $output .= '<textarea style="width:460px;" rows="6" name="nothing">' . LF . htmlspecialchars(implode(LF, $emails)) . '</textarea>';
                }
        }
        
        /**
         * Hook for cmd_stats_postProcess
         * insert a link to open extended importer
         */
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod4']['cmd_stats'])) {
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
        // put all the stats tables in a section
        $theOutput = '<h3>' . $this->getLanguageService()->getLL('stats_direct_mail') .'</h3>' . $output;
        $theOutput .= '<div style="padding-top: 20px;"></div>';
        
        $theOutput .= '<h3>' . $this->getLanguageService()->getLL('stats_recalculate_cached_data') . '</h3>' .
            '<p><a style="text-decoration: underline;" href="' . $thisurl . '">' . $this->getLanguageService()->getLL('stats_recalculate_stats') . '</a></p>';
        return $theOutput;
    }
}