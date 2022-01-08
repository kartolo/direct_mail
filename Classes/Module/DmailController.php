<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Module;

use DirectMailTeam\DirectMail\Dmailer;
use DirectMailTeam\DirectMail\DirectMailUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageRendererResolver;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class DmailController extends MainController
{
    protected $cshTable;
    protected string $error = '';
    
    protected int $currentStep = 1;
    
    /**
     * for cmd == 'delete'
     * @var integer
     */
    protected int $uid = 0;
    
    protected bool $backButtonPressed = false;
    
    protected string $currentCMD = '';
    protected bool $fetchAtOnce = false;

    protected array $quickmail = [];
    protected int $createMailFrom_UID = 0;
    protected string $createMailFrom_URL = '';
    protected int $createMailFrom_LANG = 0;
    protected string $createMailFrom_HTMLUrl = '';
    protected string $createMailFrom_plainUrl = '';
    protected array $mailgroup_uid = [];
    protected bool $mailingMode_simple = false;
    protected int $tt_address_uid = 0;
    
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
//     public function __construct(ModuleTemplate $moduleTemplate = null)
//     {
//         $this->moduleTemplate = $moduleTemplate ?? GeneralUtility::makeInstance(ModuleTemplate::class);
//         $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf');
//         $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');
//     }
    
    protected function initDmail(ServerRequestInterface $request): void {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();
        
        $this->uid = (int)($parsedBody['uid'] ?? $queryParams['uid'] ?? 0);
        
        $update_cats = $parsedBody['update_cats'] ?? $queryParams['update_cats'] ?? false;
        if ($update_cats) {
            $this->cmd = 'cats';
        }
        
        $this->mailingMode_simple = (bool)($parsedBody['mailingMode_simple'] ?? $queryParams['mailingMode_simple'] ?? false);
        if ($this->mailingMode_simple) {
            $this->cmd = 'send_mail_test';
        }
        
        $this->backButtonPressed = (bool)($parsedBody['back'] ?? $queryParams['back'] ?? false);
        
        $this->currentCMD = (string)($parsedBody['currentCMD'] ?? $queryParams['currentCMD'] ?? '');
        // Create DirectMail and fetch the data
        $this->fetchAtOnce = (bool)($parsedBody['fetchAtOnce'] ?? $queryParams['fetchAtOnce'] ?? false);
        
        $this->quickmail = $parsedBody['quickmail'] ?? $queryParams['quickmail'] ?? [];
        $this->createMailFrom_UID = (int)($parsedBody['createMailFrom_UID'] ?? $queryParams['createMailFrom_UID'] ?? 0);
        $this->createMailFrom_URL = (string)($parsedBody['createMailFrom_URL'] ?? $queryParams['createMailFrom_URL'] ?? '');
        $this->createMailFrom_LANG = (int)($parsedBody['createMailFrom_LANG'] ?? $queryParams['createMailFrom_LANG'] ?? 0);
        $this->createMailFrom_HTMLUrl = (string)($parsedBody['createMailFrom_HTMLUrl'] ?? $queryParams['createMailFrom_HTMLUrl'] ?? '');
        $this->createMailFrom_plainUrl = (string)($parsedBody['createMailFrom_plainUrl'] ?? $queryParams['createMailFrom_plainUrl'] ?? '');
        $this->mailgroup_uid = $parsedBody['mailgroup_uid'] ?? $queryParams['mailgroup_uid'] ?? [];
        $this->tt_address_uid = (int)($parsedBody['tt_address_uid'] ?? $queryParams['tt_address_uid'] ?? 0);
    }
    
    public function indexAction(ServerRequestInterface $request) : ResponseInterface
    {
        $currentModule = 'Dmail';
        $this->view = $this->configureTemplatePaths($currentModule);
        
        $this->init($request);
        $this->initDmail($request);
        
        // get the config from pageTS
        $this->params['pid'] = intval($this->id);
        
        $this->cshTable = '_MOD_' . $this->moduleName;
        
        if (($this->id && $this->access) || ($this->isAdmin() && !$this->id)) {
            $module = $this->getModulName();

            if ($module == 'dmail') {
                // Direct mail module
                if (($this->pageinfo['doktype'] ?? 0) == 254) {
                    $this->pageRenderer->addJsInlineCode($currentModule, $this->getJS($this->sys_dmail_uid));
                    $markers = $this->moduleContent();
                    $formcontent = $markers['CONTENT'];

                    $this->view->assignMultiple(
                        [
                            'wizardsteps' => $markers['WIZARDSTEPS'],
                            'navigation'  => $markers['NAVIGATION'],
                            'flashmessages' => $markers['FLASHMESSAGES'],
                            'title' => $markers['TITLE'],
                            'content' => $formcontent
                        ]
                    );
                }
                elseif ($this->id != 0) {
                    $message = $this->createFlashMessage($this->getLanguageService()->getLL('dmail_noRegular'), $this->getLanguageService()->getLL('dmail_newsletters'), 1, false);
                    $this->messageQueue->addMessage($message);
                }
            }
            else {
                $message = $this->createFlashMessage($this->getLanguageService()->getLL('select_folder'), $this->getLanguageService()->getLL('header_directmail'), 1, false);
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
        $isExternalDirectMailRecord = false;
        
        $markers = [
            'WIZARDSTEPS' => '',
            'FLASHMESSAGES' => '',
            'NAVIGATION' => '',
            'TITLE' => ''
        ];
        
        if ($this->cmd == 'delete') {
            $this->deleteDMail($this->uid);
        }
        
        $row = [];
        if (intval($this->sys_dmail_uid)) {
            $row = BackendUtility::getRecord('sys_dmail', $this->sys_dmail_uid);
            $isExternalDirectMailRecord = (is_array($row) && $row['type'] == 1);
        }
        
        $hideCategoryStep = false;
        $tsconfig = $this->getTSConfig();

        if ((isset($tsconfig['tx_directmail.']['hideSteps']) &&
            $tsconfig['tx_directmail.']['hideSteps'] === 'cat') || $isExternalDirectMailRecord) {
                $hideCategoryStep = true;
        }

        if ($this->backButtonPressed) {
            // CMD move 1 step back
            switch ($this->currentCMD) {
                case 'info':
                    $this->cmd = '';
                    break;
                case 'cats':
                    $this->cmd = 'info';
                    break;
                case 'send_test':
                    // Same as send_mail_test
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
                // step 2: create the Direct Mail record, or use existing
                $this->currentStep = 2;
                $markers['TITLE'] = $this->getLanguageService()->getLL('dmail_wiz2_detail');
                
                $fetchMessage = '';
                
                // greyed out next-button if fetching is not successful (on error)
                $fetchError = true;
                
                $quickmail = $this->quickmail;
                $quickmail['send'] = $quickmail['send'] ?? false;

                // internal page
                if ($this->createMailFrom_UID && !$quickmail['send']) {
                    $newUid = DirectMailUtility::createDirectMailRecordFromPage($this->createMailFrom_UID, $this->params, $this->createMailFrom_LANG);
                    
                    if (is_numeric($newUid)) {
                        $this->sys_dmail_uid = $newUid;
                        // Read new record (necessary because TCEmain sets default field values)
                        $row = BackendUtility::getRecord('sys_dmail', $newUid);
                        // fetch the data
                        if ($this->fetchAtOnce) {
                            $fetchMessage = DirectMailUtility::fetchUrlContentsForDirectMailRecord($row, $this->params);
                            $fetchError = ((strstr($fetchMessage, $this->getLanguageService()->getLL('dmail_error')) === false) ? false : true);
                        }
                        $theOutput .= '<input type="hidden" name="cmd" value="' . ($nextCmd ? $nextCmd : 'cats') . '">';
                    } else {
                        // TODO: Error message - Error while adding the DB set
                    }
                } 
                // external URL
                // $this->createMailFrom_URL is the External URL subject
                elseif ($this->createMailFrom_URL != '' && !$quickmail['send']) {
                    $newUid = DirectMailUtility::createDirectMailRecordFromExternalURL(
                        $this->createMailFrom_URL, 
                        $this->createMailFrom_HTMLUrl, 
                        $this->createMailFrom_plainUrl, 
                        $this->params
                    );
                    if (is_numeric($newUid)) {
                        $this->sys_dmail_uid = $newUid;
                        // Read new record (necessary because TCEmain sets default field values)
                        $row = BackendUtility::getRecord('sys_dmail', $newUid);
                        // fetch the data
                        if ($this->fetchAtOnce) {
                            $fetchMessage = DirectMailUtility::fetchUrlContentsForDirectMailRecord($row, $this->params);
                            $fetchError = ((strstr($fetchMessage, $this->getLanguageService()->getLL('dmail_error')) === false) ? false : true);
                        }
                        $theOutput .= '<input type="hidden" name="cmd" value="send_test">';
                    } else {
                        // TODO: Error message - Error while adding the DB set
                        $this->error = 'no_valid_url';
                    }
                } 
                // Quickmail
                elseif ($quickmail['send']) {
                    $fetchMessage = $this->createDMail_quick($quickmail);
                    $fetchError = ((strstr($fetchMessage, $this->getLanguageService()->getLL('dmail_error')) === false) ? false : true);
                    $row = BackendUtility::getRecord('sys_dmail', $this->sys_dmail_uid);
                    $theOutput .= '<input type="hidden" name="cmd" value="send_test">';
                    $theOutput .= '<input type="hidden" name="quickmail[senderName]" value="' . htmlspecialchars($quickmail['senderName'] ?? '') . '" />';
                    $theOutput .= '<input type="hidden" name="quickmail[senderEmail]" value="' . htmlspecialchars($quickmail['senderEmail'] ?? '') . '" />';
                    $theOutput .= '<input type="hidden" name="quickmail[subject]" value="' . htmlspecialchars($quickmail['subject'] ?? '') . '" />';
                    $theOutput .= '<input type="hidden" name="quickmail[message]" value="' . htmlspecialchars($quickmail['message'] ?? '') . '" />';
                    if($quickmail['breakLines'] ?? false) {
                        $theOutput .= '<input type="hidden" name="quickmail[breakLines]" value="'. (int)$quickmail['breakLines'] . '" />';
                    }
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
                        if ($this->fetchAtOnce) {
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
                } 
                elseif (!$fetchError && $this->fetchAtOnce) {
                    $message = $this->createFlashMessage(
                        '', 
                        $this->getLanguageService()->getLL('dmail_wiz2_fetch_success'), 
                        0, 
                        false
                    );
                    $this->messageQueue->addMessage($message);
                }
                
                if (is_array($row)) {
                    $theOutput .= '<div id="box-1" class="toggleBox">';
                    $theOutput .= $this->renderRecordDetailsTable($row);
                    $theOutput .= '</div>';
                }
                
                $theOutput .= '<input type="hidden" name="sys_dmail_uid" value="' . $this->sys_dmail_uid . '">';
                $theOutput .= !empty($row['page'])?'<input type="hidden" name="pages_uid" value="' . $row['page'] . '">':'';
                $theOutput .= '<input type="hidden" name="currentCMD" value="' . $this->cmd . '">';
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
                $theOutput .= '<input type="hidden" name="currentCMD" value="' . $this->cmd . '">';
                break;
                    
            case 'send_test':
                // Same as send_mail_test
            case 'send_mail_test':
                // send test mail
                $this->currentStep = (4 - (5 - $totalSteps));
                $markers['TITLE'] = $this->getLanguageService()->getLL('dmail_wiz4_testmail');
                
                $navigationButtons = '<input type="submit" class="btn btn-default" value="' . $this->getLanguageService()->getLL('dmail_wiz_back') . '" name="back">&nbsp;';
                $navigationButtons.= '<input type="submit" class="btn btn-default" value="' . $this->getLanguageService()->getLL('dmail_wiz_next') . '">';
                
                if ($this->cmd == 'send_mail_test') {
                    // using Flashmessages to show sent test mail
                    $markers['FLASHMESSAGES'] = $this->cmd_send_mail($row);
                }
                $theOutput .= '<br /><div id="box-1" class="toggleBox">';
                $theOutput .= $this->cmd_testmail();
                $theOutput .= '</div></div>';
                
                $theOutput .= '<input type="hidden" name="cmd" value="send_mass">';
                $theOutput .= '<input type="hidden" name="sys_dmail_uid" value="' . $this->sys_dmail_uid . '">';
                $theOutput .= '<input type="hidden" name="pages_uid" value="' . $this->pages_uid . '">';
                $theOutput .= '<input type="hidden" name="currentCMD" value="' . $this->cmd . '">';
                break;
                    
            case 'send_mail_final':
                // same as send_mass
            case 'send_mass':
                $this->currentStep = (5 - (5 - $totalSteps));
                
                if ($this->cmd == 'send_mass') {
                    $navigationButtons = '<input type="submit" class="btn btn-default" value="' . $this->getLanguageService()->getLL('dmail_wiz_back') . '" name="back">';
                }
                
                if ($this->cmd == 'send_mail_final') {
                    if (is_array($this->mailgroup_uid)) {
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
                
                $theOutput = '<h3>' . $this->getLanguageService()->getLL('dmail_wiz5_sendmass') . '</h3>' . $theOutput;
                    
                $theOutput .= '<input type="hidden" name="cmd" value="send_mail_final">';
                $theOutput .= '<input type="hidden" name="sys_dmail_uid" value="' . $this->sys_dmail_uid . '">';
                $theOutput .= '<input type="hidden" name="pages_uid" value="' . $this->pages_uid . '">';
                $theOutput .= '<input type="hidden" name="currentCMD" value="' . $this->cmd . '">';
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
        // opened - closes
        $icon = $expand ? 'apps-pagetree-expand' : 'apps-pagetree-collapse';
        return $this->iconFactory->getIcon($icon, Icon::SIZE_SMALL);
    }
    
    /**
     * Show the list of existing directmail records, which haven't been sent
     *
     * @return	string		HTML
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function cmd_news()
    {
        // Here the list of subpages, news, is rendered
        $queryBuilder = $this->getQueryBuilder('pages');
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
                    $createDmailLink = $this->buildUriFromRoute(
                        $this->moduleName, 
                        [
                            'id' => $this->id,
                            'createMailFrom_UID' => $row['uid'],
                            'fetchAtOnce' => 1,
                            'cmd' => 'info'
                        ]
                    );
                    $pageIcon = $this->iconFactory->getIconForRecord('pages', $row, Icon::SIZE_SMALL) . '&nbsp;' .  htmlspecialchars($row['title']);
                    
                    $previewHTMLLink = $previewTextLink = $createLink = '';
                    foreach ($languages as $languageUid => $lang) {
                        $langParam = DirectMailUtility::getLanguageParam($languageUid, $this->params);
                        $createLangParam = ($languageUid ? '&createMailFrom_LANG=' . $languageUid : '');
                        $langIconOverlay = (count($languages) > 1 ? $lang['flagIcon'] : null);
                        $langTitle = (count($languages) > 1 ? ' - ' . $lang['title'] : '');
                        $plainParams = $this->implodedParams['plainParams'] ?? '' . $langParam;
                        $htmlParams = $this->implodedParams['HTMLParams'] ?? '' . $langParam;
                        $htmlIcon = $this->iconFactory->getIcon('directmail-dmail-preview-html', Icon::SIZE_SMALL, $langIconOverlay);
                        $plainIcon = $this->iconFactory->getIcon('directmail-dmail-preview-text', Icon::SIZE_SMALL, $langIconOverlay);
                        $createIcon = $this->iconFactory->getIcon('directmail-dmail-new', Icon::SIZE_SMALL, $langIconOverlay);
                        
                        $attributes = \TYPO3\CMS\Backend\Routing\PreviewUriBuilder::create($row['uid'], '')
                        ->withRootLine(BackendUtility::BEgetRootLine($row['uid']))
                        //->withSection('')
                        ->withAdditionalQueryParameters($htmlParams)
                         ->buildDispatcherDataAttributes([]);

                        $serializedAttributes = GeneralUtility::implodeAttributes([
                            'href' => '#',
                            'data-dispatch-action' => $attributes['dispatch-action'],
                            'data-dispatch-args' => $attributes['dispatch-args'],
                            'title' => htmlentities($this->getLanguageService()->getLL('nl_viewPage_HTML') . $langTitle)
                        ], true);
                        
                        $previewHTMLLink .= '<a ' . $serializedAttributes . '>' . $htmlIcon . '</a>';
                        
                        $attributes = \TYPO3\CMS\Backend\Routing\PreviewUriBuilder::create($row['uid'], '')
                        ->withRootLine(BackendUtility::BEgetRootLine($row['uid']))
                        //->withSection('')
                        ->withAdditionalQueryParameters($plainParams)
                        ->buildDispatcherDataAttributes([]);

                        $serializedAttributes = GeneralUtility::implodeAttributes([
                            'href' => '#',
                            'data-dispatch-action' => $attributes['dispatch-action'],
                            'data-dispatch-args' => $attributes['dispatch-args'],
                            'title' => htmlentities($this->getLanguageService()->getLL('nl_viewPage_TXT') . $langTitle)
                        ], true);

                        $previewTextLink .= '<a href="#" ' . $serializedAttributes . '>' . $plainIcon . '</a>';
                        $createLink .= '<a href="' . $createDmailLink . $createLangParam . '" title="' . htmlentities($this->getLanguageService()->getLL('nl_create') . $langTitle) . '">' . $createIcon . '</a>';
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
                        '<a onclick="' . $editOnClickLink . '" href="#" title="' . $this->getLanguageService()->getLL('nl_editPage') . '">' . 
                        $this->iconFactory->getIcon('actions-open', Icon::SIZE_SMALL) . '</a>',
                        $previewLink
                    ];
                }
                $out = DirectMailUtility::formatTable($outLines, [], 0, [1, 1, 1, 1]);
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
                $queryBuilder = $this->getQueryBuilder('pages');

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
			$output .= '<h3>' . $this->getLanguageService()->getLL('dmail_dovsk_crFromUrl') . BackendUtility::cshItem($this->cshTable, 'create_directmail_from_url', $GLOBALS['BACK_PATH'] ?? '') . '</h3>';
			$output .= $out;
			$output .= '</div></div>';
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
        $output .= '<a href="#" onclick="toggleDisplay(\'' . $boxId . '\', event, ' . $totalBox . ')">' . $imgSrc . $this->getLanguageService()->getLL('dmail_wiz1_quickmail') . '</a>';
        $output .= '</div><div id="' . $boxId . '" class="toggleBox" style="display:' . ($open?'block':'none') . '">';
        $output .= '<h3>' . $this->getLanguageService()->getLL('dmail_wiz1_quickmail_header') . '</h3>';
        $output .= $this->cmd_quickmail();
        $output .= '</div></div>';
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
        $indata = $this->quickmail;

        $senderName = ($indata['senderName'] ?? $this->getBackendUser()->user['realName']);
        $senderMail = ($indata['senderEmail'] ?? $this->getBackendUser()->user['email']);
        
        $breakLines = $indata['breakLines'] ?? false;
        // Set up form:
        $theOutput .= '<input type="hidden" name="id" value="' . $this->id . '" />';
        $theOutput .= $this->getLanguageService()->getLL('quickmail_sender_name') . '<br /><input type="text" name="quickmail[senderName]" value="' . htmlspecialchars($senderName) . '" style="width: 460px;" /><br />';
        $theOutput .= $this->getLanguageService()->getLL('quickmail_sender_email') . '<br /><input type="text" name="quickmail[senderEmail]" value="' . htmlspecialchars($senderMail) . '" style="width: 460px;" /><br />';
        $theOutput .= $this->getLanguageService()->getLL('dmail_subject') . '<br /><input type="text" name="quickmail[subject]" value="' . htmlspecialchars($indata['subject'] ?? '') . '" style="width: 460px;" /><br />';
        $theOutput .= $this->getLanguageService()->getLL('quickmail_message') . '<br /><textarea rows="20" name="quickmail[message]" style="width: 460px;">' . LF . htmlspecialchars($indata['message'] ?? '') . '</textarea><br />';
        $theOutput .= $this->getLanguageService()->getLL('quickmail_break_lines') . ' <input type="checkbox" name="quickmail[breakLines]" value="1"' . ($breakLines ? ' checked="checked"' : '') . ' /><br /><br />';
        $theOutput .= '<input type="Submit" name="quickmail[send]" value="' . $this->getLanguageService()->getLL('dmail_wiz_next') . '" />';
        
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
            }
            else{
                $sOrder = trim(str_replace('DESC','',$sOrder));
                $ascDesc = 'DESC';
            }
        }
        $queryBuilder = $this->getQueryBuilder('sys_dmail');
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
    
    /**
     * Creates a directmail entry in th DB.
     * used only for quickmail.
     *
     * @param array $indata Quickmail data (quickmail content, etc.)
     *
     * @return string error or warning message produced during the process
     */
    protected function createDMail_quick(array $indata)
    {
        $theOutput = '';
        // Set default values:
        $dmail = [];
        $dmail['sys_dmail']['NEW'] = [
            'from_email'        => $indata['senderEmail'],
            'from_name'         => $indata['senderName'],
            'replyto_email'     => $this->params['replyto_email'] ?? '',
            'replyto_name'      => $this->params['replyto_name'] ?? '',
            'return_path'       => $this->params['return_path'] ?? '',
            'priority'          => (int) $this->params['priority'],
            'use_rdct'          => (int) $this->params['use_rdct'],
            'long_link_mode'    => (int) $this->params['long_link_mode'],
            'organisation'      => $this->params['organisation'] ?? '',
            'authcode_fieldList'=> $this->params['authcode_fieldList'] ?? '',
            'plainParams'       => ''
        ];
        
        // always plaintext
        $dmail['sys_dmail']['NEW']['sendOptions'] = 1;
        $dmail['sys_dmail']['NEW']['long_link_rdct_url'] = DirectMailUtility::getUrlBase((int)$this->params['pid']);
        $dmail['sys_dmail']['NEW']['subject'] = $indata['subject'];
        $dmail['sys_dmail']['NEW']['type'] = 1;
        $dmail['sys_dmail']['NEW']['pid'] = $this->pageinfo['uid'];
        $dmail['sys_dmail']['NEW']['charset'] = isset($this->params['quick_mail_charset']) ? $this->params['quick_mail_charset'] : 'utf-8';
        
        // If params set, set default values:
        if (isset($this->params['includeMedia'])) {
            $dmail['sys_dmail']['NEW']['includeMedia'] = $this->params['includeMedia'];
        }
        if (isset($this->params['flowedFormat'])) {
            $dmail['sys_dmail']['NEW']['flowedFormat'] = $this->params['flowedFormat'];
        }
        if (isset($this->params['direct_mail_encoding'])) {
            $dmail['sys_dmail']['NEW']['encoding'] = $this->params['direct_mail_encoding'];
        }

        if ($dmail['sys_dmail']['NEW']['pid'] && $dmail['sys_dmail']['NEW']['sendOptions']) {
            /* @var $tce \TYPO3\CMS\Core\DataHandling\DataHandler */
            $tce = GeneralUtility::makeInstance(DataHandler::class);
            $tce->stripslashes_values = 0;
            $tce->start($dmail, []);
            $tce->process_datamap();
            $this->sys_dmail_uid = $tce->substNEWwithIDs['NEW'];
            
            $row = BackendUtility::getRecord('sys_dmail', intval($this->sys_dmail_uid));
            // link in the mail
            $message = '<!--DMAILER_SECTION_BOUNDARY_-->' . $indata['message'] . '<!--DMAILER_SECTION_BOUNDARY_END-->';
            if (trim($this->params['use_rdct'])) {
                $message = DirectMailUtility::substUrlsInPlainText(
                    $message,
                    $this->params['long_link_mode'] ? 'all' : '76',
                    DirectMailUtility::getUrlBase((int)$this->params['pid'])
                );
            }
            if ($indata['breakLines'] ?? false) {
                $message = wordwrap($message, 76, "\n");
            }
            // fetch functions
            $theOutput = $this->compileQuickMail($row, $message);
            // end fetch function
        } else {
            if (!$dmail['sys_dmail']['NEW']['sendOptions']) {
                $this->error = 'no_valid_url';
            }
        }
        
        return $theOutput;
    }
    
    /**
     * Wrap a string as a link
     *
     * @param string $str String to be linked
     * @param int $uid UID of the directmail record
     *
     * @return string the link
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function linkDMail_record($str, $uid)
    {
        $moduleUrl = $this->buildUriFromRoute(
            $this->moduleName,
            [
                'id' => $this->id,
                'sys_dmail_uid' => $uid,
                'fetchAtOnce' => 1,
                'cmd' => 'info'
            ]
        );
        return '<a class="t3-link" href="' . $moduleUrl . '">' . htmlspecialchars($str) . '</a>';
    }
    
    /**
     * Create delete link with trash icon
     *
     * @param int $uid Uid of the record
     *
     * @return string link with the trash icon
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function deleteLink($uid)
    {
        $dmail = BackendUtility::getRecord('sys_dmail', $uid);
        
        if (!$dmail['scheduled_begin']) {
            $icon = $this->iconFactory->getIcon('actions-edit-delete', Icon::SIZE_SMALL);
            $moduleUrl = $this->buildUriFromRoute(
                $this->moduleName,
                [
                    'id' => $this->id,
                    'uid' => $uid,
                    'cmd' => 'delete'
                ]
            );
            return '<a href="' . $moduleUrl . '">' . $icon . '</a>';
        }
        
        return '';
    }
    
    /**
     * Delete existing dmail record
     *
     * @param int $uid record uid to be deleted
     *
     * @return void
     */
    protected function deleteDMail($uid)
    {
        $table = 'sys_dmail';
        if ($GLOBALS['TCA'][$table]['ctrl']['delete']) {
            
            $connection = $this->getConnection($table);
            $connection->update(
                $table, // table
                [ $GLOBALS['TCA'][$table]['ctrl']['delete'] => 1 ],
                [ 'uid' => $uid ] // where
            );
        }
        
        return;
    }
    
    /**
     * Compiling the quickmail content and save to DB
     *
     * @param array $row The sys_dmail record
     * @param string $message Body of the mail
     *
     * @return string
     * @TODO: remove htmlmail, compiling mail
     */
    protected function compileQuickMail(array $row, $message)
    {
        $errorMsg = '';
        $warningMsg = '';
        
        // Compile the mail
        /* @var $htmlmail Dmailer */
        $htmlmail = GeneralUtility::makeInstance(Dmailer::class);
        $htmlmail->nonCron = 1;
        $htmlmail->start();
        $htmlmail->charset = $row['charset'];
        $htmlmail->addPlain($message);
        
        if (!$message || !$htmlmail->theParts['plain']['content']) {
            $errorMsg .= '&nbsp;<strong>' . $this->getLanguageService()->getLL('dmail_no_plain_content') . '</strong>';
        } elseif (!strstr(base64_decode($htmlmail->theParts['plain']['content']), '<!--DMAILER_SECTION_BOUNDARY')) {
            $warningMsg .= '&nbsp;<strong>' . $this->getLanguageService()->getLL('dmail_no_plain_boundaries') . '</strong>';
        }
        
        // add attachment is removed. since it will be add during sending
        
        if (!$errorMsg) {
            // Update the record:
            $htmlmail->theParts['messageid'] = $htmlmail->messageid;
            $mailContent = base64_encode(serialize($htmlmail->theParts));
            $queryBuilder = $this->getQueryBuilder('sys_dmail');
            $queryBuilder
            ->update('sys_dmail')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    intval($this->sys_dmail_uid)
                    )
                )
                ->set('issent', 0)
                ->set('charset', $htmlmail->charset)
                ->set('mailContent', $mailContent)
                ->set('renderedSize', strlen($mailContent))
                ->execute();
                
            if ($warningMsg) {
                return '<h3>' . $this->getLanguageService()->getLL('dmail_warning') . '</h3>' . $warningMsg . '<br /><br />';
            }
        }
        
        return '';
    }
    
    /**
     * Shows the infos of a directmail record in a table
     *
     * @param array $row DirectMail DB record
     *
     * @return string the HTML output
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function renderRecordDetailsTable(array $row)
    {
        if (isset($row['issent']) && !$row['issent']) {
            if ($this->getBackendUser()->check('tables_modify', 'sys_dmail')) {
                // $requestUri = rawurlencode(GeneralUtility::linkThisScript(array('sys_dmail_uid' => $row['uid'], 'createMailFrom_UID' => '', 'createMailFrom_URL' => '')));
                $requestUri = $this->buildUriFromRoute(
                    $this->moduleName,
                    [
                        'id' => $this->id,
                        'sys_dmail_uid' => $row['uid'],
                        'fetchAtOnce' => 1,
                        'cmd' => 'info'
                    ]
                );
                
                $editParams = DirectMailUtility::getEditOnClickLink([
                    'edit' => [
                        'sys_dmail' => [
                            $row['uid'] => 'edit',
                        ],
                    ],
                    'returnUrl' => $requestUri->__toString(),
                ]);
                
                $content = '<a href="#" onClick="' . $editParams . '" title="' . $this->getLanguageService()->getLL('dmail_edit') . '">' .
                    $this->iconFactory->getIcon('actions-open', Icon::SIZE_SMALL) .
                    '<b>' . $this->getLanguageService()->getLL('dmail_edit') . '</b></a>';
            } else {
                $content = $this->iconFactory->getIcon('actions-open', Icon::SIZE_SMALL) . ' (' . $this->getLanguageService()->getLL('dmail_noEdit_noPerms') . ')';
            }
        } else {
            $content = $this->iconFactory->getIcon('actions-open', Icon::SIZE_SMALL) . '(' . $this->getLanguageService()->getLL('dmail_noEdit_isSent') . ')';
        }
        
        $content = '<thead >
			<th>' . DirectMailUtility::fName('subject') . ' <b>' . GeneralUtility::fixed_lgd_cs(htmlspecialchars($row['subject'] ?? ''), 60) . '</b></th>
			<th style="text-align: right;">' . $content . '</th>
		</thead>';
        
        $nameArr = explode(',', 'from_name,from_email,replyto_name,replyto_email,organisation,return_path,priority,type,page,sendOptions,includeMedia,flowedFormat,sys_language_uid,plainParams,HTMLParams,encoding,charset,issent,renderedsize');
        foreach ($nameArr as $name) {
            $content .= '
			<tr class="db_list_normal">
				<td>' . DirectMailUtility::fName($name) . '</td>
				<td>' . htmlspecialchars((string)BackendUtility::getProcessedValue('sys_dmail', $name, ($row[$name] ?? false))) . '</td>
			</tr>';
        }
        // attachments need to be fetched manually as BackendUtility::getProcessedValue can't do that
        $fileNames = [];
        $attachments = DirectMailUtility::getAttachments($row['uid'] ?? 0);
        /** @var FileReference $attachment */
        foreach ($attachments as $attachment) {
            $fileNames[] = $attachment->getName();
        }
        $content .= '
			<tr class="db_list_normal">
				<td>' . DirectMailUtility::fName('attachment') . '</td>
				<td>' . implode(', ', $fileNames) . '</td>
			</tr>';
        $content = '<table width="460" class="table table-striped table-hover">' . $content . '</table>';
        
        $sectionTitle = $this->iconFactory->getIconForRecord('sys_dmail', $row, Icon::SIZE_SMALL)->render() . '&nbsp;' . htmlspecialchars($row['subject'] ?? '');
        return '<h3>' . $sectionTitle . '</h3>' . $content;
    }
    
    /**
     * Show the step of sending a test mail
     *
     * @return string the HTML form
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function cmd_testmail()
    {
        $theOutput = '';
        
        if ($this->params['test_tt_address_uids'] ?? false) {
            $intList = implode(',', GeneralUtility::intExplode(',', $this->params['test_tt_address_uids']));
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
                ->add('where','tt_address.uid IN (' . $intList . ')' .
                    ' AND ' . $this->perms_clause )
                    ->execute()
                    ->fetchAll();
                    
                    $msg = $this->getLanguageService()->getLL('testmail_individual_msg') . '<br /><br />';
                    
                    $ids = [];
                    
                    foreach ($res as $row) {
                        $ids[] = $row['uid'];
                    }
                    
                    $msg .= $this->getRecordList(DirectMailUtility::fetchRecordsListValues($ids, 'tt_address'), 'tt_address', 1, 1);
                    
            $theOutput.= '<h3>' . $this->getLanguageService()->getLL('testmail_individual') . '</h3>' .
                $msg;
                
            $theOutput.= '<div style="padding-top: 20px;"></div>';
        }
        
        if ($this->params['test_dmail_group_uids'] ?? false) {
            $intList = implode(',', GeneralUtility::intExplode(',', $this->params['test_dmail_group_uids']));
            
            $queryBuilder = $this->getQueryBuilder('sys_dmail_group');
            $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
            $res = $queryBuilder
            ->select('sys_dmail_group.*')
            ->from('sys_dmail_group')
            ->leftJoin(
                'sys_dmail_group',
                'pages',
                'pages',
                $queryBuilder->expr()->eq('sys_dmail_group.pid', $queryBuilder->quoteIdentifier('pages.uid'))
                )
                ->add('where','sys_dmail_group.uid IN (' . $intList . ')' .
                    ' AND ' . $this->perms_clause )
                    ->execute()
                    ->fetchAll();
                    
                    $msg = $this->getLanguageService()->getLL('testmail_mailgroup_msg') . '<br /><br />';
                    
                    foreach ($res as $row) {
                        $moduleUrl = $this->buildUriFromRoute(
                            $this->moduleName,
                            [
                                'id' => $this->id,
                                'sys_dmail_uid' => $this->sys_dmail_uid,
                                'CMD' => 'send_mail_test',
                                'sys_dmail_group_uid[]' => $row['uid']
                            ]
                        );
                        $msg .='<a href="' . $moduleUrl . '">' .
                            $this->iconFactory->getIconForRecord('sys_dmail_group', $row, Icon::SIZE_SMALL) .
                            htmlspecialchars($row['title']) . '</a><br />';
                            // Members:
                            $result = $this->cmd_compileMailGroup([$row['uid']]);
                            $msg.='<table border="0" class="table table-striped table-hover">
				<tr>
					<td>' . $this->cmd_displayMailGroup_test($result) . '</td>
				</tr>
				</table>';
                    }
                    
            $theOutput.= '<h3>' . $this->getLanguageService()->getLL('testmail_mailgroup') . '</h3>' .
                $msg;
                $theOutput.= '<div style="padding-top: 20px;"></div>';
        }
        
        $msg = '';
        $msg.= $this->getLanguageService()->getLL('testmail_simple_msg') . '<br /><br />';
        $msg.= '<input style="width: 460px;" type="text" name="SET[dmail_test_email]" value="' . ($this->MOD_SETTINGS['dmail_test_email'] ?? '') . '" /><br /><br />';
        
        $msg.= '<input type="hidden" name="id" value="' . $this->id . '" />';
        $msg.= '<input type="hidden" name="sys_dmail_uid" value="' . $this->sys_dmail_uid . '" />';
        $msg.= '<input type="hidden" name="cmd" value="send_mail_test" />';
        $msg.= '<input type="submit" name="mailingMode_simple" value="' . $this->getLanguageService()->getLL('dmail_send') . '" />';
        
        $theOutput.= '<h3>' . $this->getLanguageService()->getLL('testmail_simple') . '</h3>' .
            $msg;
            
        $this->noView = 1;
        return $theOutput;
    }
    
    /**
     * Sending the mail.
     * if it's a test mail, then will be sent directly.
     * if mass-send mail, only update the DB record. the dmailer script will send it.
     *
     * @param array $row Directmal DB record
     *
     * @return string Messages if the mail is sent or planned to sent
     * @todo	remove htmlmail. sending test mail
     */
    protected function cmd_send_mail($row)
    {
        // Preparing mailer
        /* @var $htmlmail Dmailer */
        $htmlmail = GeneralUtility::makeInstance(Dmailer::class);
        $htmlmail->nonCron = 1;
        $htmlmail->start();
        $htmlmail->dmailer_prepare($row);
        $sentFlag = false;
        
        // send out non-personalized emails
        if ($this->mailingMode_simple) {
            // step 4, sending simple test emails
            // setting Testmail flag
            $htmlmail->testmail = $this->params['testmail'] ?? false;
            
            // Fixing addresses:
            $addresses = GeneralUtility::_GP('SET');
            $addressList = $addresses['dmail_test_email'] ? $addresses['dmail_test_email'] : $this->MOD_SETTINGS['dmail_test_email'];
            $addresses = preg_split('|[' . LF . ',;]|', $addressList);
            
            foreach ($addresses as $key => $val) {
                $addresses[$key] = trim($val);
                if (!GeneralUtility::validEmail($addresses[$key])) {
                    unset($addresses[$key]);
                }
            }
            $hash = array_flip($addresses);
            $addresses = array_keys($hash);
            $addressList = implode(',', $addresses);
            
            if ($addressList) {
                // Sending the same mail to lots of recipients
                $htmlmail->dmailer_sendSimple($addressList);
                $sentFlag = true;
                $message = $this->createFlashMessage(
                    $this->getLanguageService()->getLL('send_was_sent') . ' ' .
                    $this->getLanguageService()->getLL('send_recipients') . ' ' . htmlspecialchars($addressList), 
                    $this->getLanguageService()->getLL('send_sending'), 
                    0, 
                    false
                );
                $this->messageQueue->addMessage($message);
                
                $this->noView = 1;
            }
        } 
        elseif ($this->cmd == 'send_mail_test') {
            // step 4, sending test personalized test emails
            // setting Testmail flag
            $htmlmail->testmail = $this->params['testmail'];
            
            if ($this->tt_address_uid) {
                // personalized to tt_address
                $queryBuilder = $this->getQueryBuilder('tt_address');
                $res = $queryBuilder
                ->select('a.*')
                ->from('tt_address', 'a')
                ->leftJoin('a', 'pages', 'pages', $queryBuilder->expr()->eq('pages.uid', $queryBuilder->quoteIdentifier('a.pid')))
                ->where($queryBuilder->expr()->eq('a.uid', $queryBuilder->createNamedParameter($this->tt_address_uid, \PDO::PARAM_INT)))
                ->andWhere($this->perms_clause)
                ->execute()
                ->fetchAll();
                
                if (!empty($res)) {
                    foreach ($res as $recipRow) {
                        $recipRow = Dmailer::convertFields($recipRow);
                        $recipRow['sys_dmail_categories_list'] = $htmlmail->getListOfRecipentCategories('tt_address', $recipRow['uid']);
                        $htmlmail->dmailer_sendAdvanced($recipRow, 't');
                        $sentFlag = true;
                        
                        $message = $this->createFlashMessage(
                            sprintf($this->getLanguageService()->getLL('send_was_sent_to_name'), $recipRow['name'] . ' <' . $recipRow['email'] . '>'), 
                            $this->getLanguageService()->getLL('send_sending'), 
                            0, 
                            false
                        );
                        $this->messageQueue->addMessage($message);
                    }
                } else {
                    $message = $this->createFlashMessage(
                        'Error: No valid recipient found to send test mail to. #1579209279', 
                        $this->getLanguageService()->getLL('send_sending'), 
                        2, 
                        false
                    );
                    $this->messageQueue->addMessage($message);
                }
                
            } elseif (is_array(GeneralUtility::_GP('sys_dmail_group_uid'))) {
                // personalized to group
                $result = $this->cmd_compileMailGroup(GeneralUtility::_GP('sys_dmail_group_uid'));
                
                $idLists = $result['queryInfo']['id_lists'];
                $sendFlag = 0;
                $sendFlag += $this->sendTestMailToTable($idLists, 'tt_address', $htmlmail);
                $sendFlag += $this->sendTestMailToTable($idLists, 'fe_users', $htmlmail);
                $sendFlag += $this->sendTestMailToTable($idLists, 'PLAINLIST', $htmlmail);
                $sendFlag += $this->sendTestMailToTable($idLists, $this->userTable, $htmlmail);
                
                $message = $this->createFlashMessage(
                    sprintf($this->getLanguageService()->getLL('send_was_sent_to_number'), $sendFlag), 
                    $this->getLanguageService()->getLL('send_sending'), 
                    0, 
                    false
                );
                $this->messageQueue->addMessage($message);
            }
        } else {
            // step 5, sending personalized emails to the mailqueue
            
            // prepare the email for sending with the mailqueue
            $recipientGroups = GeneralUtility::_GP('mailgroup_uid');
            if (GeneralUtility::_GP('mailingMode_mailGroup') && $this->sys_dmail_uid && is_array($recipientGroups)) {
                // Update the record:
                $result = $this->cmd_compileMailGroup($recipientGroups);
                $queryInfo = $result['queryInfo'];
                
                $distributionTime = strtotime(GeneralUtility::_GP('send_mail_datetime_hr'));
                if ($distributionTime < time()) {
                    $distributionTime = time();
                }
                
                $updateFields = [
                    'recipientGroups' => implode(',', $recipientGroups),
                    'scheduled'  => $distributionTime,
                    'query_info' => serialize($queryInfo)
                ];
                
                if (GeneralUtility::_GP('testmail')) {
                    $updateFields['subject'] = ($this->params['testmail'] ?? '') . ' ' . $row['subject'];
                }
                
                // create a draft version of the record
                if (GeneralUtility::_GP('savedraft')) {
                    if ($row['type'] == 0) {
                        $updateFields['type'] = 2;
                    } else {
                        $updateFields['type'] = 3;
                    }
                    
                    $updateFields['scheduled'] = 0;
                    $content = $this->getLanguageService()->getLL('send_draft_scheduler');
                    $sectionTitle = $this->getLanguageService()->getLL('send_draft_saved');
                } else {
                    $content = $this->getLanguageService()->getLL('send_was_scheduled_for') . ' ' . BackendUtility::datetime($distributionTime);
                    $sectionTitle = $this->getLanguageService()->getLL('send_was_scheduled');
                }
                $sentFlag = true;
                $connection = $this->getConnection('sys_dmail');
                $connection->update(
                    'sys_dmail', // table
                    $updateFields,
                    [ 'uid' => intval($this->sys_dmail_uid) ] // where
                );
                
                $message = $this->createFlashMessage(
                    $sectionTitle . ' ' . $content, 
                    $this->getLanguageService()->getLL('dmail_wiz5_sendmass'), 
                    0, 
                    false
                );
                $this->messageQueue->addMessage($message);
            }
        }
        
        // Setting flags and update the record:
        if ($sentFlag && $this->cmd == 'send_mail_final') {

            $connection = $this->getConnection('sys_dmail');
            $connection->update(
                'sys_dmail', // table
                ['issent' => 1],
                [ 'uid' => intval($this->sys_dmail_uid) ] // where
            );
        }
    }
    
    /**
     * Shows the final steps of the process. Show recipient list and calendar library
     *
     * @param array $direct_mail_row
     * @return	string		HTML
     */
    protected function cmd_finalmail($direct_mail_row)
    {
        /**
         * Hook for cmd_finalmail
         * insert a link to open extended importer
         */
        $hookSelectDisabled = '';
        $hookContents = '';
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_finalmail'] ?? false)) {
            $hookObjectsArr = [];
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_finalmail'] as $classRef) {
                $hookObjectsArr[] = GeneralUtility::makeInstance($classRef);
            }
            foreach ($hookObjectsArr as $hookObj) {
                if (method_exists($hookObj, 'cmd_finalmail')) {
                    $hookContents = $hookObj->cmd_finalmail($this);
                    $hookSelectDisabled = $hookObj->selectDisabled;
                }
            }
        }
        
        // Mail groups
        $queryBuilder = $this->getQueryBuilder('sys_dmail_group');
        $statement = $queryBuilder
        ->select('uid','pid','title')
        ->from('sys_dmail_group')
        ->where(
            $queryBuilder->expr()->eq(
                'pid',
                intval($this->id)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->in(
                    'sys_language_uid',
                    '-1, ' . $direct_mail_row['sys_language_uid']
                    )
                )
            ->orderBy(
                preg_replace(
                    '/^(?:ORDER[[:space:]]*BY[[:space:]]*)+/i', '',
                    trim($GLOBALS['TCA']['sys_dmail_group']['ctrl']['default_sortby'])
                    )
                )
            ->execute();
                    
        $opt = [];
        $lastGroup = null;
        while (($group = $statement->fetch())) {
            $result = $this->cmd_compileMailGroup([$group['uid']]);
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
            $opt[] = '<option value="' . $group['uid'] . '">' . htmlspecialchars($group['title'] . ' (#' . $count . ')') . '</option>';
            $lastGroup = $group;
        }
        
        $groupInput = '';
        // added disabled. see hook
        if (count($opt) === 0) {
            $message = $this->createFlashMessage(
                $this->getLanguageService()->getLL('error.no_recipient_groups_found'), 
                '', 
                2, 
                false
            );
            $this->messageQueue->addMessage($message);
        } 
        elseif (count($opt) === 1) {
            if (!$hookSelectDisabled) {
                $groupInput .= '<input type="hidden" name="mailgroup_uid[]" value="' . $lastGroup['uid'] . '" />';
            }
            $groupInput .= '* ' . htmlentities($lastGroup['title']);
            if ($hookSelectDisabled) {
                $groupInput .= '<em>disabled</em>';
            }
        } 
        else {
            $groupInput = '<select class="form-control" size="20" multiple="multiple" name="mailgroup_uid[]" '.($hookSelectDisabled ? 'disabled' : '').'>'.implode(chr(10),$opt).'</select>';
        }
        // Set up form:
        $msg = '';
        $msg .= '<input type="hidden" name="id" value="' . $this->id . '" />';
        $msg .= '<input type="hidden" name="sys_dmail_uid" value="' . $this->sys_dmail_uid . '" />';
        $msg .= '<input type="hidden" name="CMD" value="send_mail_final" />';
        $msg .= $this->getLanguageService()->getLL('schedule_mailgroup') . '<br />' . $groupInput . '<br /><br />';
        
        // put content from hook
        $msg .= $hookContents;
        $msg .= $this->getLanguageService()->getLL('schedule_time') .
        '<br /><div class="form-control-wrap"><div class="input-group">' .
        '<input class="form-control t3js-datetimepicker t3js-clearable" data-date-type="datetime" data-date-offset="0" type="text" id="tceforms-datetimefield-startdate" name="send_mail_datetime_hr" value="' . strftime('%H:%M %d-%m-%Y', time()) . '">' .
        '<input name="send_mail_datetime" value="' . strftime('%H:%M %d-%m-%Y', time()) . '" type="hidden">' .
        '<span class="input-group-btn"><label class="btn btn-default" for="tceforms-datetimefield-startdate"><span class="fa fa-calendar"></span></label></span>' .
        '</div></div><br />';
        
        $msg .= '<br/><label for="tx-directmail-sendtestmail-check"><input type="checkbox" name="testmail" id="tx-directmail-sendtestmail-check" value="1" />&nbsp;' . $this->getLanguageService()->getLL('schedule_testmail') . '</label>';
        $msg .= '<br/><label for="tx-directmail-savedraft-check"><input type="checkbox" name="savedraft" id="tx-directmail-savedraft-check" value="1" />&nbsp;' . $this->getLanguageService()->getLL('schedule_draft') . '</label>';
        $msg .= '<br /><br /><input class="btn btn-default" type="Submit" name="mailingMode_mailGroup" value="' . $this->getLanguageService()->getLL('schedule_send_all') . '" />';
                    
        $theOutput = '<h3>' . $this->getLanguageService()->getLL('schedule_select_mailgroup') . '</h3>' . $msg;
        $theOutput .= '<div style="padding-top: 20px;"></div>';
                        
        $this->noView = 1;
        return $theOutput;
    }
    
    /**
     * Get the recipient IDs given a list of group IDs
     *
     * @param array $groups List of selected group IDs
     *
     * @return array list of the recipient ID
     */
    public function cmd_compileMailGroup(array $groups)
    {
        // If supplied with an empty array, quit instantly as there is nothing to do
        if (!count($groups)) {
            return [];
        }
        
        // Looping through the selected array, in order to fetch recipient details
        $idLists = [];
        foreach ($groups as $group) {
            // Testing to see if group ID is a valid integer, if not - skip to next group ID
            $group = MathUtility::convertToPositiveInteger($group);
            if (!$group) {
                continue;
            }
            
            $recipientList = $this->getSingleMailGroup($group);
            if (!is_array($recipientList)) {
                continue;
            }
            
            $idLists = array_merge_recursive($idLists, $recipientList);
        }
        
        // Make unique entries
        if (is_array($idLists['tt_address'] ?? false)) {
            $idLists['tt_address'] = array_unique($idLists['tt_address']);
        }
        
        if (is_array($idLists['fe_users'] ?? false)) {
            $idLists['fe_users'] = array_unique($idLists['fe_users']);
        }
        
        if (is_array($idLists[$this->userTable] ?? false) && $this->userTable) {
            $idLists[$this->userTable] = array_unique($idLists[$this->userTable]);
        }
        
        if (is_array($idLists['PLAINLIST'] ?? false)) {
            $idLists['PLAINLIST'] = DirectMailUtility::cleanPlainList($idLists['PLAINLIST']);
        }
        
        /**
         * Hook for cmd_compileMailGroup
         * manipulate the generated id_lists
         */
        if (is_array($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_compileMailGroup'] ?? false)) {
            $hookObjectsArr = [];
            $temporaryList = '';
            
            foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_compileMailGroup'] as $classRef) {
                $hookObjectsArr[] = GeneralUtility::makeInstance($classRef);
            }
            foreach ($hookObjectsArr as $hookObj) {
                if (method_exists($hookObj, 'cmd_compileMailGroup_postProcess')) {
                    $temporaryList = $hookObj->cmd_compileMailGroup_postProcess($idLists, $this, $groups);
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
     * Fetches recipient IDs from a given group ID
     * Most of the functionality from cmd_compileMailGroup in order to use multiple recipient lists when sending
     *
     * @param int $groupUid Recipient group ID
     *
     * @return array List of recipient IDs
     */
    protected function getSingleMailGroup($groupUid)
    {
        $idLists = [];
        if ($groupUid) {
            $mailGroup = BackendUtility::getRecord('sys_dmail_group', $groupUid);
            
            if (is_array($mailGroup)) {
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
                            if ($whichTables&1) {
                                // tt_address
                                $idLists['tt_address'] = DirectMailUtility::getIdList('tt_address', $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            if ($whichTables&2) {
                                // fe_users
                                $idLists['fe_users'] = DirectMailUtility::getIdList('fe_users', $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            if ($this->userTable && ($whichTables&4)) {
                                // user table
                                $idLists[$this->userTable] = DirectMailUtility::getIdList($this->userTable, $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            if ($whichTables&8) {
                                // fe_groups
                                if (!is_array($idLists['fe_users'])) {
                                    $idLists['fe_users'] = [];
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
                        if ($whichTables&1) {
                            $table = 'tt_address';
                        } elseif ($whichTables&2) {
                            $table = 'fe_users';
                        } elseif ($this->userTable && ($whichTables&4)) {
                            $table = $this->userTable;
                        }
                        if ($table) {
                            // initialize the query generator
                            $queryGenerator = GeneralUtility::makeInstance(MailSelect::class);
                            $idLists[$table] = DirectMailUtility::getSpecialQueryIdList($queryGenerator, $table, $mailGroup);
                        }
                        break;
                    case 4:
                        $groups = array_unique(DirectMailUtility::getMailGroups($mailGroup['mail_groups'], [$mailGroup['uid']], $this->perms_clause));
                        foreach ($groups as $v) {
                            $collect = $this->getSingleMailGroup($v);
                            if (is_array($collect)) {
                                $idLists = array_merge_recursive($idLists, $collect);
                            }
                        }
                        break;
                    default:
                }
            }
        }
        return $idLists;
    }


    /**
     * Show the categories table for user to categorize the directmail content
     * TYPO3 content)
     *
     * @param array $row The dmail row.
     *
     * @return string HTML form showing the categories
     */
    public function makeCategoriesForm(array $row)
    {
        $indata = GeneralUtility::_GP('indata');
        if (is_array($indata['categories'])) {
            $data = [];
            foreach ($indata['categories'] as $recUid => $recValues) {
                $enabled = [];
                foreach ($recValues as $k => $b) {
                    if ($b) {
                        $enabled[] = $k;
                    }
                }
                $data['tt_content'][$recUid]['module_sys_dmail_category'] = implode(',', $enabled);
            }

            /* @var $tce \TYPO3\CMS\Core\DataHandling\DataHandler */
            $tce = GeneralUtility::makeInstance(DataHandler::class);
            $tce->stripslashes_values = 0;
            $tce->start($data, []);
            $tce->process_datamap();

            // remove cache
            $tce->clear_cacheCmd($this->pages_uid);
            $out = DirectMailUtility::fetchUrlContentsForDirectMailRecord($row, $this->params);
        }

        // Todo Perhaps we should here check if TV is installed and fetch content from that instead of the old Columns...
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('tt_content');
        $res = $queryBuilder
            ->select('colPos', 'CType', 'uid', 'pid', 'header', 'bodytext', 'module_sys_dmail_category')
            ->from('tt_content')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($this->pages_uid, \PDO::PARAM_INT)
                ),
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    $queryBuilder->createNamedParameter($row['sys_language_uid'], \PDO::PARAM_INT)
                )
            )
            ->orderBy('colPos')
            ->addOrderBy('sorting')
            ->execute()
            ->fetchAll();

        if (empty($res)) {
            $theOutput = '<h3>' . $this->getLanguageService()->getLL('nl_cat') . '</h3>' . $this->getLanguageService()->getLL('nl_cat_msg1');
        } else {
            $out = '';
            $colPosVal = 99;
            foreach ($res as $row) {
                $categoriesRow = '';

                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable('sys_dmail_ttcontent_category_mm');
                $resCat = $queryBuilder
                    ->select('uid_foreign')
                    ->from('sys_dmail_ttcontent_category_mm')
                    ->add('where','uid_local=' . $row['uid'])
                    ->execute()
                    ->fetchAll();

                foreach ($resCat as $rowCat) {
                    $categoriesRow .= $rowCat['uid_foreign'] . ',';
                }

                $categoriesRow = rtrim($categoriesRow, ',');

                if ($colPosVal != $row['colPos']) {
                    $out .= '<tr><td colspan="3" bgcolor="' . $this->doc->bgColor5 . '">' . $this->getLanguageService()->getLL('nl_l_column') . ': <strong>' . BackendUtility::getProcessedValue('tt_content', 'colPos', $row['colPos']) . '</strong></td></tr>';
                    $colPosVal = $row['colPos'];
                }
                $out .= '<tr>';
                $out .= '<td valign="top" width="75%">' . $this->iconFactory->getIconForRecord('tt_content', $row, Icon::SIZE_SMALL) .
                    $row['header'] . '<br />' . GeneralUtility::fixed_lgd_cs(strip_tags($row['bodytext']), 200) . '<br /></td>';

                $out .= '<td nowrap valign="top">';
                $checkBox = '';
                if ($row['module_sys_dmail_category']) {
                    $checkBox .= '<strong style="color:red;">' . $this->getLanguageService()->getLL('nl_l_ONLY') . '</strong>';
                } else {
                    $checkBox .= '<strong style="color:green">' . $this->getLanguageService()->getLL('nl_l_ALL') . '</strong>';
                }
                $checkBox .= '<br />';

                $this->categories = DirectMailUtility::makeCategories('tt_content', $row, $this->sys_language_uid);
                reset($this->categories);
                foreach ($this->categories as $pKey => $pVal) {
                    $checkBox .= '<input type="hidden" name="indata[categories][' . $row['uid'] . '][' . $pKey . ']" value="0">' .
                        '<input type="checkbox" name="indata[categories][' . $row['uid'] . '][' . $pKey . ']" value="1"' . (GeneralUtility::inList($categoriesRow, $pKey) ?' checked':'') . '> ' .
                        htmlspecialchars($pVal) . '<br />';
                }
                $out .= $checkBox . '</td></tr>';
            }

            $out = '<table border="0" cellpadding="0" cellspacing="0" class="table table-striped table-hover">' . $out . '</table>';
            $out .= '<input type="hidden" name="pages_uid" value="' . $this->pages_uid . '">' .
                '<input type="hidden" name="CMD" value="' . $this->CMD . '"><br />' .
                '<input type="submit" name="update_cats" value="' . $this->getLanguageService()->getLL('nl_l_update') . '">';

            $theOutput = '<h3>' . $this->getLanguageService()->getLL('nl_cat') . '</h3>' .
                BackendUtility::cshItem($this->cshTable, 'assign_categories', $GLOBALS['BACK_PATH']) .
                $out;

        }
        return $theOutput;
    }
}
