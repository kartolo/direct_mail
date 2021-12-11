<?php
namespace DirectMailTeam\DirectMail\Module;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageRendererResolver;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;
use DirectMailTeam\DirectMail\DirectMailUtility;

class DmailController extends MainController
{
    protected $cshTable;
    protected string $error = '';
    
    protected int $currentStep = 1;
    
    /**
     * The name of the module
     *
     * @var string
     */
    protected string $moduleName = 'DirectMailNavFrame_DirectMail';
    
    /**
     * Constructor Method
     *
     * @var ModuleTemplate $moduleTemplate
     */
    public function __construct(ModuleTemplate $moduleTemplate = null)
    {
        $this->moduleTemplate = $moduleTemplate ?? GeneralUtility::makeInstance(ModuleTemplate::class);
        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf');
        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');
    }
    
    public function indexAction(ServerRequestInterface $request) : ResponseInterface
    {
        $this->view = $this->configureTemplatePaths('Dmail');
        
        $this->init($request);
        
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        // get the config from pageTS
        $this->params['pid'] = intval($this->id);
        
        $this->cshTable = '_MOD_' . $this->moduleName;
        
        if (($this->id && $this->access) || ($this->isAdmin() && !$this->id)) {
            $markers = $this->moduleContent();
            $formcontent = $markers['CONTENT'];
            $this->view->assignMultiple(
                [
                    'wizardsteps' => $markers['WIZARDSTEPS'],
                    'navigation'  => $markers['NAVIGATION'],
                    'flashmessages' => $markers['FLASHMESSAGES'],
                    'title' => $markers['TITLE'],
                    'content' => $markers['CONTENT']
                ]
            );
        }
        else {
            // If no access or if ID == zero
            $this->view = $this->configureTemplatePaths('NoAccess');
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
        $isExternalDirectMailRecord = false;
        
        $markers = [
            'WIZARDSTEPS' => '',
            'FLASHMESSAGES' => '',
            'NAVIGATION' => '',
            'TITLE' => ''
        ];
        
        if ($this->cmd == 'delete') {
            $this->deleteDMail(intval(GeneralUtility::_GP('uid'))); //@TODO
        }
        
        $row = [];
        if (intval($this->sys_dmail_uid)) {
            $row = BackendUtility::getRecord('sys_dmail', intval($this->sys_dmail_uid));
            $isExternalDirectMailRecord = (is_array($row) && $row['type'] == 1);
        }
        
        $hideCategoryStep = false;
        $tsconfig = $this->getTSConfig();

        if ((isset($tsconfig['tx_directmail.']['hideSteps']) &&
            $tsconfig['tx_directmail.']['hideSteps'] === 'cat') || $isExternalDirectMailRecord) {
                $hideCategoryStep = true;
            }
            
        if (GeneralUtility::_GP('update_cats')) { //@TODO
            $this->cmd = 'cats';
        }
            
        if (GeneralUtility::_GP('mailingMode_simple')) { //@TODO
            $this->cmd = 'send_mail_test';
        }
            
        $backButtonPressed = GeneralUtility::_GP('back');  //@TODO
        if ($backButtonPressed) {
            // CMD move 1 step back
            switch (GeneralUtility::_GP('currentCMD')) {  //@TODO
                case 'info':
                    $this->cmd = '';
                    break;
                case 'cats':
                    $this->cmd = 'info';
                    break;
                case 'send_test':
                    // Sameas send_mail_test
                case 'send_mail_test':
                    if (($this->cmd == 'send_mass') && $hideCategoryStep) {
                        $this->cmd = 'info';
                    } else {
                        $this->cmd = 'cats';
                    }
                    break;
                    
                case 'send_mail_final':
                    // The same as send_mass
                case 'send_mass':
                    $this->cmd = 'send_test';
                    break;
                default:
                    // Do nothing
            }
        }
            
        $nextCmd = '';
        if ($hideCategoryStep) {
            $totalSteps = 4;
            if ($this->cmd == 'info') {
                $nextCmd = 'send_test';
            }
        } else {
            $totalSteps = 5;
            if ($this->cmd == 'info') {
                $nextCmd = 'cats';
            }
        }
            
        $navigationButtons = '';
        switch ($this->cmd) {
            case 'info':
                $fetchMessage = '';
                
                // step 2: create the Direct Mail record, or use existing
                $this->currentStep = 2;
                $markers['TITLE'] = $this->getLanguageService()->getLL('dmail_wiz2_detail');
                
                // greyed out next-button if fetching is not successful (on error)
                $fetchError = true;
                
                // Create DirectMail and fetch the data
                $shouldFetchData = GeneralUtility::_GP('fetchAtOnce');
                
                $quickmail = GeneralUtility::_GP('quickmail');
                
                $createMailFromInternalPage = intval(GeneralUtility::_GP('createMailFrom_UID'));
                $createMailFromExternalUrl = GeneralUtility::_GP('createMailFrom_URL');
                
                // internal page
                if ($createMailFromInternalPage && !$quickmail['send']) {
                    $createMailFromInternalPageLang = (int)GeneralUtility::_GP('createMailFrom_LANG');
                    $newUid = DirectMailUtility::createDirectMailRecordFromPage($createMailFromInternalPage, $this->params, $createMailFromInternalPageLang);
                    
                    if (is_numeric($newUid)) {
                        $this->sys_dmail_uid = $newUid;
                        // Read new record (necessary because TCEmain sets default field values)
                        $row = BackendUtility::getRecord('sys_dmail', $newUid);
                        // fetch the data
                        if ($shouldFetchData) {
                            $fetchMessage = DirectMailUtility::fetchUrlContentsForDirectMailRecord($row, $this->params);
                            $fetchError = ((strstr($fetchMessage, $this->getLanguageService()->getLL('dmail_error')) === false) ? false : true);
                        }
                        $theOutput .= '<input type="hidden" name="cmd" value="' . ($nextCmd ? $nextCmd : 'cats') . '">';
                    } else {
                        // TODO: Error message - Error while adding the DB set
                    }
                    
                    // external URL
                } elseif ($createMailFromExternalUrl && !$quickmail['send']) {
                    // $createMailFromExternalUrl is the External URL subject
                    $htmlUrl = GeneralUtility::_GP('createMailFrom_HTMLUrl');
                    $plainTextUrl = GeneralUtility::_GP('createMailFrom_plainUrl');
                    $newUid = DirectMailUtility::createDirectMailRecordFromExternalURL($createMailFromExternalUrl, $htmlUrl, $plainTextUrl, $this->params);
                    if (is_numeric($newUid)) {
                        $this->sys_dmail_uid = $newUid;
                        // Read new record (necessary because TCEmain sets default field values)
                        $row = BackendUtility::getRecord('sys_dmail', $newUid);
                        // fetch the data
                        if ($shouldFetchData) {
                            $fetchMessage = DirectMailUtility::fetchUrlContentsForDirectMailRecord($row, $this->params);
                            $fetchError = ((strstr($fetchMessage, $this->getLanguageService()->getLL('dmail_error')) === false) ? false : true);
                        }
                        $theOutput .= '<input type="hidden" name="cmd" value="send_test">';
                    } else {
                        // TODO: Error message - Error while adding the DB set
                        $this->error = 'no_valid_url';
                    }
                    
                    // Quickmail
                } elseif ($quickmail['send']) {
                    $fetchMessage = $this->createDMail_quick($quickmail);
                    $fetchError = ((strstr($fetchMessage, $this->getLanguageService()->getLL('dmail_error')) === false) ? false : true);
                    $row = BackendUtility::getRecord('sys_dmail', $this->sys_dmail_uid);
                    $theOutput.= '<input type="hidden" name="CMD" value="send_test">';
                    // existing dmail
                } elseif ($row) {
                    if ($row['type'] == '1' && ((empty($row['HTMLParams'])) || (empty($row['plainParams'])))) {
                        
                        // it's a quickmail
                        $fetchError = false;
                        $theOutput .= '<input type="hidden" name="cmd" value="send_test">';
                        
                        // add attachment here, since attachment added in 2nd step
                        $unserializedMailContent = unserialize(base64_decode($row['mailContent']));
                        $theOutput .= $this->compileQuickMail($row, $unserializedMailContent['plain']['content'], false);
                    } else {
                        if ($shouldFetchData) {
                            $fetchMessage = DirectMailUtility::fetchUrlContentsForDirectMailRecord($row, $this->params);
                            $fetchError = ((strstr($fetchMessage, $this->getLanguageService()->getLL('dmail_error')) === false) ? false : true);
                        }
                        
                        if ($row['type'] == 0) {
                            $theOutput .= '<input type="hidden" name="cmd" value="' . $nextCmd . '">';
                        } else {
                            $theOutput .= '<input type="hidden" name="cmd" value="send_test">';
                        }
                    }
                }
                
                $navigationButtons = '<input type="submit" class="btn btn-default" value="' . $this->getLanguageService()->getLL('dmail_wiz_back') . '" name="back"> &nbsp;';
                $navigationButtons .= '<input type="submit" value="' . $this->getLanguageService()->getLL('dmail_wiz_next') . '" ' . ($fetchError ? 'disabled="disabled" class="next btn btn-default disabled"' : ' class="btn btn-default"') . '>';
                
                if ($fetchMessage) {
                    $markers['FLASHMESSAGES'] = $fetchMessage;
                } elseif (!$fetchError && $shouldFetchData) {
                    $markers['FLASHMESSAGES'] = GeneralUtility::makeInstance(FlashMessageRendererResolver::class)
                    ->resolve()
                    ->render([
                        GeneralUtility::makeInstance(
                            FlashMessage::class,
                            '',
                            $this->getLanguageService()->getLL('dmail_wiz2_fetch_success'),
                            FlashMessage::OK
                            )
                    ]);
                }
                
                if (is_array($row)) {
                    $theOutput .= '<div id="box-1" class="toggleBox">';
                    $theOutput .= $this->renderRecordDetailsTable($row);
                    $theOutput .= '</div>';
                }
                
                $theOutput .= '<input type="hidden" name="sys_dmail_uid" value="' . $this->sys_dmail_uid . '">';
                $theOutput .= !empty($row['page'])?'<input type="hidden" name="pages_uid" value="' . $row['page'] . '">':'';
                $theOutput .= '<input type="hidden" name="currentCMD" value="' . $this->CMD . '">';
                break;
                
            case 'cats':
                // shows category if content-based cat
                $this->currentStep = 3;
                $markers['TITLE'] = $this->getLanguageService()->getLL('dmail_wiz3_cats');
                
                $navigationButtons = '<input type="submit" class="btn btn-default " value="' . $this->getLanguageService()->getLL('dmail_wiz_back') . '" name="back">&nbsp;';
                $navigationButtons .= '<input type="submit" class="btn btn-default " value="' . $this->getLanguageService()->getLL('dmail_wiz_next') . '">';
                
                $theOutput .= '<div id="box-1" class="toggleBox">';
                $theOutput .= $this->makeCategoriesForm($row);
                $theOutput .= '</div></div>';
                
                $theOutput .= '<input type="hidden" name="cmd" value="send_test">';
                $theOutput .= '<input type="hidden" name="sys_dmail_uid" value="' . $this->sys_dmail_uid . '">';
                $theOutput .= '<input type="hidden" name="pages_uid" value="' . $this->pages_uid . '">';
                $theOutput .= '<input type="hidden" name="currentCMD" value="' . $this->CMD . '">';
                break;
                    
            case 'send_test':
                // Same as send_mail_test
            case 'send_mail_test':
                // send test mail
                $this->currentStep = (4 - (5 - $totalSteps));
                $markers['TITLE'] = $this->getLanguageService()->getLL('dmail_wiz4_testmail');
                
                $navigationButtons = '<input type="submit" class="btn btn-default" value="' . $this->getLanguageService()->getLL('dmail_wiz_back') . '" name="back">&nbsp;';
                $navigationButtons.= '<input type="submit" class="btn btn-default" value="' . $this->getLanguageService()->getLL('dmail_wiz_next') . '">';
                
                if ($this->CMD == 'send_mail_test') {
                    // using Flashmessages to show sent test mail
                    $markers['FLASHMESSAGES'] = $this->cmd_send_mail($row);
                }
                $theOutput .= '<br /><div id="box-1" class="toggleBox">';
                $theOutput .= $this->cmd_testmail();
                $theOutput .= '</div></div>';
                
                $theOutput .= '<input type="hidden" name="cmd" value="send_mass">';
                $theOutput .= '<input type="hidden" name="sys_dmail_uid" value="' . $this->sys_dmail_uid . '">';
                $theOutput .= '<input type="hidden" name="pages_uid" value="' . $this->pages_uid . '">';
                $theOutput .= '<input type="hidden" name="currentCMD" value="' . $this->CMD . '">';
                break;
                    
            case 'send_mail_final':
                // same as send_mass
            case 'send_mass':
                $this->currentStep = (5 - (5 - $totalSteps));
                
                if ($this->cmd == 'send_mass') {
                    $navigationButtons = '<input type="submit" class="btn btn-default" value="' . $this->getLanguageService()->getLL('dmail_wiz_back') . '" name="back">';
                }
                
                if ($this->cmd == 'send_mail_final') {
                    $selectedMailGroups = GeneralUtility::_GP('mailgroup_uid');
                    if (is_array($selectedMailGroups)) {
                        $markers['FLASHMESSAGES'] = $this->cmd_send_mail($row);
                        break;
                    } else {
                        $theOutput .= 'no recipients';
                    }
                }
                // send mass, show calendar
                $theOutput .= '<div id="box-1" class="toggleBox">';
                $theOutput .= $this->cmd_finalmail($row);
                $theOutput .= '</div>';
                
                $theOutput = '<h3>' . $this->getLanguageService()->getLL('dmail_wiz5_sendmass') . '</h3>' .
                    $theOutput;
                    
                $theOutput .= '<input type="hidden" name="cmd" value="send_mail_final">';
                $theOutput .= '<input type="hidden" name="sys_dmail_uid" value="' . $this->sys_dmail_uid . '">';
                $theOutput .= '<input type="hidden" name="pages_uid" value="' . $this->pages_uid . '">';
                $theOutput .= '<input type="hidden" name="currentCMD" value="' . $this->CMD . '">';
                break;
                        
            default:
                // choose source newsletter
                $this->currentStep = 1;
                $markers['TITLE'] = $this->getLanguageService()->getLL('dmail_wiz1_new_newsletter') . ' - ' . $this->getLanguageService()->getLL('dmail_wiz1_select_nl_source');
                
                $showTabs = ['int', 'ext', 'quick', 'dmail'];
                if(isset($tsconfig['tx_directmail.']['hideTabs'])) {
                    $hideTabs = GeneralUtility::trimExplode(',', $tsconfig['tx_directmail.']['hideTabs']);
                    foreach ($hideTabs as $hideTab) {
                        $showTabs = ArrayUtility::removeArrayEntryByValue($showTabs, $hideTab);
                    }
                }
                if (!isset($tsconfig['tx_directmail.']['defaultTab'])) {
                    $tsconfig['tx_directmail.']['defaultTab'] = 'dmail';
                }
                
                $i = 1;
                $countTabs = count($showTabs);
                foreach ($showTabs as $showTab) {
                    $open = false;
                    if ($tsconfig['tx_directmail.']['defaultTab'] == $showTab) {
                        $open = true;
                    }
                    switch ($showTab) {
                        case 'int':
                            $theOutput .= $this->makeFormInternal('box-' . $i, $countTabs, $open);
                            break;
                        case 'ext':
                            $theOutput .= $this->makeFormExternal('box-' . $i, $countTabs, $open);
                            break;
                        case 'quick':
                            $theOutput .= $this->makeFormQuickMail('box-' . $i, $countTabs, $open);
                            break;
                        case 'dmail':
                            $theOutput .= $this->makeListDMail('box-' . $i, $countTabs, $open);
                            break;
                        default:
                    }
                    $i++;
                }
            $theOutput .= '<input type="hidden" name="cmd" value="info" />';
        }
            
        $markers['NAVIGATION'] = $navigationButtons;
        $markers['CONTENT'] = $theOutput;
        $markers['WIZARDSTEPS'] = $this->showSteps($totalSteps);
        return $markers;
    }
    
    /**
     * Showing steps number on top of every page
     *
     * @param int $totalSteps Total step
     *
     * @return string HTML
     */
    protected function showSteps($totalSteps)
    {
        $content = '';
        for ($i = 1; $i <= $totalSteps; $i++) {
            $cssClass = ($i == $this->currentStep) ? 't3-wizard-item t3-wizard-item-active' : 't3-wizard-item';
            $content .= '<span class="' . $cssClass . '">&nbsp;' . $i . '&nbsp;</span>';
        }
        
        return '<div class="typo3-message message-ok t3-wizard-steps">' . $content . '</div>';
    }
    
    /**
     * Makes box for internal page. (first step)
     *
     * @param string $boxId ID name for the HTML element
     * @param int $totalBox Total of all boxes
     * @param bool $open State of the box
     *
     * @return string HTML with list of internal pages
     */
    protected function makeFormInternal($boxId, $totalBox, $open = false)
    {
        $imgSrc = $this->getNewsletterTabIcon($open);
        
        $output = '<div class="box"><div class="toggleTitle">';
        $output .= '<a href="#" onclick="toggleDisplay(\'' . $boxId . '\', event, ' . $totalBox . ')">' . $imgSrc . $this->getLanguageService()->getLL('dmail_wiz1_internal_page') . '</a>';
        $output .= '</div><div id="' . $boxId . '" class="toggleBox" style="display:' . ($open?'block':'none') . '">';
        $output .= $this->cmd_news();
        $output .= '</div></div>';
        return $output;
    }
    
    /**
     * The icon for the source tab
     *
     * @param bool $expand State of the tab
     *
     * @return string
     */
    protected function getNewsletterTabIcon($expand = false)
    {
        if ($expand) {
            // opened
            return $this->moduleTemplate->getIconFactory()->getIcon('apps-pagetree-expand', Icon::SIZE_SMALL);
        }
        
        // closes
        return $this->moduleTemplate->getIconFactory()->getIcon('apps-pagetree-collapse', Icon::SIZE_SMALL);
    }
    
    /**
     * Show the list of existing directmail records, which haven't been sent
     *
     * @return	string		HTML
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    public function cmd_news()
    {
        // Here the list of subpages, news, is rendered
        
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
        ->getQueryBuilderForTable('pages');
        
        $queryBuilder
        ->select('uid', 'doktype', 'title', 'abstract')
        ->from('pages')
        ->where(
            $queryBuilder->expr()->eq(
                'pid',
                $queryBuilder->createNamedParameter($this->id, \PDO::PARAM_INT)
                ),
            $queryBuilder->expr()->eq('l10n_parent', 0), // Exclude translated page records from list
            $this->perms_clause
            );
        /**
         * Postbone Breaking: #82803 - Global configuration option "content_doktypes" removed in TYPO3 v9
         * Regards custom configurations, otherwise ignores spacers (199), recyclers (255) and folders (254)
         *
         * @deprecated since TYPO3 v9.
         **/
        if (isset($GLOBALS['TYPO3_CONF_VARS']['FE']['content_doktypes'])
            && !empty($GLOBALS['TYPO3_CONF_VARS']['FE']['content_doktypes'])
            ) {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->in(
                        'doktype',
                        GeneralUtility::intExplode(',', $GLOBALS['TYPO3_CONF_VARS']['FE']['content_doktypes'])
                        )
                    );
            } else {
                $queryBuilder->andWhere(
                    $queryBuilder->expr()->notIn(
                        'doktype',
                        [199,254,255]
                        )
                    );
            }
            $rows = $queryBuilder->orderBy('sorting')->execute()->fetchAll();
            
            if (empty($rows)) {
                $theOutput = '<h3>' . $this->getLanguageService()->getLL('nl_select') . '</h3>' . $this->getLanguageService()->getLL('nl_select_msg1');
            } else {
                $outLines = [];
                foreach ($rows as $row) {
                    $languages = $this->getAvailablePageLanguages($row['uid']);
                    
                    /** @var UriBuilder $uriBuilder */
                    $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
                    $createDmailLink = $uriBuilder->buildUriFromRoute(
                        $this->moduleName,
                        [
                            'id' => $this->id,
                            'createMailFrom_UID' => $row['uid'],
                            'fetchAtOnce' => 1,
                            'CMD' => 'info'
                        ]
                        );
                    $pageIcon = $this->moduleTemplate->getIconFactory()->getIconForRecord('pages', $row, Icon::SIZE_SMALL) . '&nbsp;' .  htmlspecialchars($row['title']);
                    
                    $previewHTMLLink = $previewTextLink = $createLink = '';
                    foreach ($languages as $languageUid => $lang) {
                        $langParam = DirectMailUtility::getLanguageParam($languageUid, $this->params);
                        $createLangParam = ($languageUid ? '&createMailFrom_LANG=' . $languageUid : '');
                        $langIconOverlay = (count($languages) > 1 ? $lang['flagIcon'] : null);
                        $langTitle = (count($languages) > 1 ? ' - ' . $lang['title'] : '');
                        $plainParams = $this->implodedParams['plainParams'] ?? '' . $langParam;
                        $htmlParams = $this->implodedParams['HTMLParams'] ?? '' . $langParam;
                        $htmlIcon = $this->moduleTemplate->getIconFactory()->getIcon('directmail-dmail-preview-html', Icon::SIZE_SMALL, $langIconOverlay);
                        $plainIcon = $this->moduleTemplate->getIconFactory()->getIcon('directmail-dmail-preview-text', Icon::SIZE_SMALL, $langIconOverlay);
                        $createIcon = $this->moduleTemplate->getIconFactory()->getIcon('directmail-dmail-new', Icon::SIZE_SMALL, $langIconOverlay);
                        
                        $previewHTMLLink .= '<a href="#" onClick="' . BackendUtility::viewOnClick(
                            $row['uid'],
                            $GLOBALS['BACK_PATH'] ?? '',
                            BackendUtility::BEgetRootLine($row['uid']),
                            '',
                            '',
                            $htmlParams
                            ) . '" title="' . htmlentities($GLOBALS['LANG']->getLL('nl_viewPage_HTML') . $langTitle) . '">' . $htmlIcon . '</a>';
                        
                        $previewTextLink .= '<a href="#" onClick="' . BackendUtility::viewOnClick(
                            $row['uid'],
                            $GLOBALS['BACK_PATH'] ?? '',
                            BackendUtility::BEgetRootLine($row['uid']),
                            '',
                            '',
                            $plainParams
                            ) . '" title="' . htmlentities($GLOBALS['LANG']->getLL('nl_viewPage_TXT') . $langTitle) . '">' . $plainIcon . '</a>';
                            $createLink .= '<a href="' . $createDmailLink . $createLangParam . '" title="' . htmlentities($GLOBALS['LANG']->getLL('nl_create') . $langTitle) . '">' . $createIcon . '</a>';
                    }
                    
                    switch ($this->params['sendOptions'] ?? 0) {
                        case 1:
                            $previewLink = $previewTextLink;
                            break;
                        case 2:
                            $previewLink = $previewHTMLLink;
                            break;
                        case 3:
                            // also as default
                        default:
                            $previewLink = $previewHTMLLink . '&nbsp;&nbsp;' . $previewTextLink;
                    }
                    
                    $params = [
                        'edit' => [
                            'pages' => [
                                $row['uid'] => 'edit',
                            ]
                        ],
                        'returnUrl' => GeneralUtility::getIndpEnv('REQUEST_URI'),
                    ];
                    $editOnClickLink = DirectMailUtility::getEditOnClickLink($params);
                    
                    $outLines[] = [
                        '<a href="' . $createDmailLink . '">' . $pageIcon . '</a>',
                        $createLink,
                        '<a onclick="' . $editOnClickLink . '" href="#" title="' . $GLOBALS['LANG']->getLL('nl_editPage') . '">' . 
                        $this->moduleTemplate->getIconFactory()->getIcon('actions-open', Icon::SIZE_SMALL) . '</a>',
                        $previewLink
                    ];
                }
                $out = DirectMailUtility::formatTable($outLines, [], 0, array(1, 1, 1, 1));
                $theOutput = '<h3>' . $this->getLanguageService()->getLL('dmail_dovsk_crFromNL') .
                BackendUtility::cshItem($this->cshTable, 'select_newsletter', $GLOBALS['BACK_PATH'] ?? '#') .
                '</h3>' .
                $out;
            }
            
            return $theOutput;
    }
    
    /**
     * Get available languages for a page
     *
     * @param $pageUid
     * @return array
     */
    protected function getAvailablePageLanguages($pageUid)
    {
        static $languages;
        $languageUids = [];
        
        if ($languages === null) {
            $languages = GeneralUtility::makeInstance(TranslationConfigurationProvider::class)->getSystemLanguages();
        }
        
        // loop trough all sys languages and check if there is matching page translation
        foreach ($languages as $lang) {
            // we skip -1
            if ((int)$lang['uid'] < 0) {
                continue;
            }
            
            // 0 is always present so only for > 0
            if ((int)$lang['uid'] > 0) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
                
                $langRow = $queryBuilder
                ->select('sys_language_uid')
                ->from('pages')
                ->where($queryBuilder->expr()->eq('l10n_parent', $queryBuilder->createNamedParameter($pageUid, \PDO::PARAM_INT)))
                ->andWhere($queryBuilder->expr()->eq('sys_language_uid', $queryBuilder->createNamedParameter($lang['uid'], \PDO::PARAM_INT)))
                ->execute()
                ->fetchAll();
                
                if (empty($langRow)) {
                    continue;
                }
            }
            
            $languageUids[(int)$lang['uid']] = $lang;
        }
        
        return $languageUids;
    }
    
    /**
     * Make input form for external URL (first step)
     *
     * @param string $boxId ID name for the HTML element
     * @param int $totalBox Total of the boxes
     * @param bool $open State of the box
     *
     * @return string HTML input form for inputing the external page information
     */
    protected function makeFormExternal($boxId, $totalBox, $open = false)
    {
        $imgSrc = $this->getNewsletterTabIcon($open);
        
        $output = '<div class="box"><div class="toggleTitle">';
        $output .= '<a href="#" onclick="toggleDisplay(\'' . $boxId . '\', event, ' . $totalBox . ')">' . $imgSrc . $this->getLanguageService()->getLL('dmail_wiz1_external_page') . '</a>';
        $output .= '</div><div id="' . $boxId . '" class="toggleBox" style="display:' . ($open?'block':'none') . '">';
        
        // Create
        $out = $this->getLanguageService()->getLL('dmail_HTML_url') . '<br />
			<input type="text" value="http://" name="createMailFrom_HTMLUrl" style="width: 384px;" /><br />' .
			$this->getLanguageService()->getLL('dmail_plaintext_url') . '<br />
			<input type="text" value="http://" name="createMailFrom_plainUrl" style="width: 384px;" /><br />' .
			$this->getLanguageService()->getLL('dmail_subject') . '<br />' .
			'<input type="text" value="' . $this->getLanguageService()->getLL('dmail_write_subject') . '" name="createMailFrom_URL" onFocus="this.value=\'\';" style="width: 384px;" /><br />' .
			(($this->error == 'no_valid_url')?('<br /><b>' . $this->getLanguageService()->getLL('dmail_no_valid_url') . '</b><br /><br />'):'') .
			'<input type="submit" value="' . $this->getLanguageService()->getLL('dmail_createMail') . '" />
			<input type="hidden" name="fetchAtOnce" value="1">';
			$output.= '<h3>' . $this->getLanguageService()->getLL('dmail_dovsk_crFromUrl') . BackendUtility::cshItem($this->cshTable, 'create_directmail_from_url', $GLOBALS['BACK_PATH'] ?? '') . '</h3>';
			$output.= $out;
			
			$output.= '</div></div>';
		return $output;
    }
    
    /**
     * Makes input form for the quickmail (first step)
     *
     * @param string $boxId ID name for the HTML element
     * @param int $totalBox Total of the boxes
     * @param bool $open State of the box
     *
     * @return string HTML input form for the quickmail
     */
    protected function makeFormQuickMail($boxId, $totalBox, $open = false)
    {
        $imgSrc = $this->getNewsletterTabIcon($open);
        
        $output = '<div class="box"><div class="toggleTitle">';
        $output.= '<a href="#" onclick="toggleDisplay(\'' . $boxId . '\', event, ' . $totalBox . ')">' . $imgSrc . $this->getLanguageService()->getLL('dmail_wiz1_quickmail') . '</a>';
        $output.= '</div><div id="' . $boxId . '" class="toggleBox" style="display:' . ($open?'block':'none') . '">';
        $output.= '<h3>' . $this->getLanguageService()->getLL('dmail_wiz1_quickmail_header') . '</h3>';
        $output.= $this->cmd_quickmail();
        $output.= '</div></div>';
        return $output;
    }
    
    /**
     * Show the quickmail input form (first step)
     *
     * @return	string HTML input form
     */
    protected function cmd_quickmail()
    {
        $theOutput = '';
        $indata = GeneralUtility::_GP('quickmail'); //@TODO
        
        $senderName = ($indata['senderName'] ?? $GLOBALS['BE_USER']->user['realName']);
        $senderMail = ($indata['senderEmail'] ?? $GLOBALS['BE_USER']->user['email']);
        
        $breakLines = $indata['breakLines'] ?? false;
        // Set up form:
        $theOutput.= '<input type="hidden" name="id" value="' . $this->id . '" />';
        $theOutput.= $this->getLanguageService()->getLL('quickmail_sender_name') . '<br /><input type="text" name="quickmail[senderName]" value="' . htmlspecialchars($senderName) . '" style="width: 460px;" /><br />';
        $theOutput.= $this->getLanguageService()->getLL('quickmail_sender_email') . '<br /><input type="text" name="quickmail[senderEmail]" value="' . htmlspecialchars($senderMail) . '" style="width: 460px;" /><br />';
        $theOutput.= $this->getLanguageService()->getLL('dmail_subject') . '<br /><input type="text" name="quickmail[subject]" value="' . htmlspecialchars($indata['subject'] ?? '') . '" style="width: 460px;" /><br />';
        $theOutput.= $this->getLanguageService()->getLL('quickmail_message') . '<br /><textarea rows="20" name="quickmail[message]" style="width: 460px;">' . LF . htmlspecialchars($indata['message'] ?? '') . '</textarea><br />';
        $theOutput.= $this->getLanguageService()->getLL('quickmail_break_lines') . ' <input type="checkbox" name="quickmail[breakLines]" value="1"' . ($breakLines ? ' checked="checked"' : '') . ' /><br /><br />';
        $theOutput.= '<input type="Submit" name="quickmail[send]" value="' . $this->getLanguageService()->getLL('dmail_wiz_next') . '" />';
        
        return $theOutput;
    }
    
    /**
     * List all direct mail, which have not been sent (first step)
     *
     * @param string $boxId ID name for the HTML element
     * @param int $totalBox Total of the boxes
     * @param bool $open State of the box
     *
     * @return string HTML lists of all existing dmail records
     */
    protected function makeListDMail($boxId, $totalBox, $open=false)
    {
        
        $sOrder = preg_replace(
            '/^(?:ORDER[[:space:]]*BY[[:space:]]*)+/i', '',
            trim($GLOBALS['TCA']['sys_dmail']['ctrl']['default_sortby'])
            );
        if (!empty($sOrder)){
            if (substr_count($sOrder, 'ASC') > 0 ){
                $sOrder = trim(str_replace('ASC','',$sOrder));
                $ascDesc = 'ASC';
            }else{
                $sOrder = trim(str_replace('DESC','',$sOrder));
                $ascDesc = 'DESC';
            }
            
        }
        
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail');
        $queryBuilder
        ->getRestrictions()
        ->removeAll()
        ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $res = $queryBuilder->select('uid','pid','subject','tstamp','issent','renderedsize','attachment','type')
        ->from('sys_dmail')
        ->add('where','pid = ' . intval($this->id) .
            ' AND scheduled=0 AND issent=0')
            ->orderBy($sOrder,$ascDesc)
            ->execute()
            ->fetchAll();
            
            $tblLines = [];
            $tblLines[] = [
                '',
                $this->getLanguageService()->getLL('nl_l_subject'),
                $this->getLanguageService()->getLL('nl_l_lastM'),
                $this->getLanguageService()->getLL('nl_l_sent'),
                $this->getLanguageService()->getLL('nl_l_size'),
                $this->getLanguageService()->getLL('nl_l_attach'),
                $this->getLanguageService()->getLL('nl_l_type'),
                ''
            ];
            
            foreach ($res as $row) {
                $tblLines[] = [
                    $this->iconFactory->getIconForRecord('sys_dmail', $row, Icon::SIZE_SMALL)->render(),
                    $this->linkDMail_record($row['subject'], $row['uid']),
                    BackendUtility::date($row['tstamp']),
                    ($row['issent'] ? $this->getLanguageService()->getLL('dmail_yes') : $this->getLanguageService()->getLL('dmail_no')),
                    ($row['renderedsize'] ? GeneralUtility::formatSize($row['renderedsize']) : ''),
                    ($row['attachment'] ? $this->iconFactory->getIcon('directmail-attachment', Icon::SIZE_SMALL) : ''),
                    ($row['type'] & 0x1 ? $this->getLanguageService()->getLL('nl_l_tUrl') : $this->getLanguageService()->getLL('nl_l_tPage')) . ($row['type']  & 0x2 ? ' (' . $this->getLanguageService()->getLL('nl_l_tDraft') . ')' : ''),
                    $this->deleteLink($row['uid'])
                ];
            }
            
            $imgSrc = $this->getNewsletterTabIcon($open);
            
            $output = '<div id="header" class="box"><div class="toggleTitle">';
            $output.= '<a href="#" onclick="toggleDisplay(\'' . $boxId . '\', event, ' . $totalBox . ')">' . $imgSrc . $this->getLanguageService()->getLL('dmail_wiz1_list_dmail') . '</a>';
            $output.= '</div><div id="' . $boxId . '" class="toggleBox" style="display:' . ($open?'block':'none') . '">';
            $output.= '<h3>' . $this->getLanguageService()->getLL('dmail_wiz1_list_header') . '</h3>';
            $output.= DirectMailUtility::formatTable($tblLines, [], 1, [1, 1, 1, 0, 0, 1, 0, 1]);
            $output.= '</div></div>';
            return $output;
    }
}