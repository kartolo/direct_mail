<?php
declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Module;

use DirectMailTeam\DirectMail\Dmailer;
use DirectMailTeam\DirectMail\DirectMailUtility;
use DirectMailTeam\DirectMail\Utility\DmCsvUtility;
use DirectMailTeam\DirectMail\Repository\PagesRepository;
use DirectMailTeam\DirectMail\Repository\SysDmailGroupRepository;
use DirectMailTeam\DirectMail\Repository\SysDmailRepository;
use DirectMailTeam\DirectMail\Repository\TempRepository;
use DirectMailTeam\DirectMail\Repository\TtAddressRepository;
use DirectMailTeam\DirectMail\Repository\TtContentCategoryMmRepository;
use DirectMailTeam\DirectMail\Repository\TtContentRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Backend\Routing\PreviewUriBuilder;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageRendererResolver;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ArrayUtility;
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
    
    protected $requestUri = '';
    
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
        
        $normalizedParams = $request->getAttribute('normalizedParams');
        $this->requestUri = $normalizedParams->getRequestUri();
        
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
                    $markers = $this->moduleContent();

                    $this->view->assignMultiple(
                        [
                            'flashmessages' => $markers['FLASHMESSAGES'],
                            'data' => $markers['data']
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
        $isExternalDirectMailRecord = false;
        
        $markers = [
            'FLASHMESSAGES' => '',
            'data' => []
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

        $data = [
            'navigation' => [
                'back' => false,
                'next' => false,
                'next_error' => false,
                'totalSteps' => $totalSteps,
                'currentStep' => 1,
                'steps' => array_fill(1, $totalSteps, '')
            ]
        ];
        
        switch ($this->cmd) {
            case 'info':
                // step 2: create the Direct Mail record, or use existing
                $this->currentStep = 2;
                $data['navigation']['currentStep'] = $this->currentStep;
                $data['info'] = [
                    'currentStep' => $this->currentStep
                ];
                
                $fetchMessage = '';
                
                // greyed out next-button if fetching is not successful (on error)
                $fetchError = true;
                
                $quickmail = $this->quickmail;
                $quickmail['send'] = $quickmail['send'] ?? false;

                // internal page
                if ($this->createMailFrom_UID && !$quickmail['send']) {
                    $newUid = $this->createDirectMailRecordFromPage($this->createMailFrom_UID, $this->params, $this->createMailFrom_LANG);
                    if (is_numeric($newUid)) {
                        $this->sys_dmail_uid = $newUid;
                        // Read new record (necessary because TCEmain sets default field values)
                        $row = BackendUtility::getRecord('sys_dmail', $newUid);
                        // fetch the data
                        if ($this->fetchAtOnce) {
                            $fetchMessage = DirectMailUtility::fetchUrlContentsForDirectMailRecord($row, $this->params);
                            $fetchError = ((strstr($fetchMessage, $this->getLanguageService()->getLL('dmail_error')) === false) ? false : true);
                        }

                        $data['info']['internal']['cmd'] = $nextCmd ? $nextCmd : 'cats';
                    } else {
                        // TODO: Error message - Error while adding the DB set
                    }
                } 
                // external URL
                // $this->createMailFrom_URL is the External URL subject
                elseif ($this->createMailFrom_URL != '' && !$quickmail['send']) {
                    $newUid = $this->createDirectMailRecordFromExternalURL(
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

                        $data['info']['external']['cmd'] = 'send_test';
                    } else {
                        // TODO: Error message - Error while adding the DB set
                        $this->error = 'no_valid_url';
                    }
                } 
                // Quickmail
                elseif ($quickmail['send']) {
                    $temp = $this->createDMailQuick($quickmail);
                    if(!$temp['errorTitle']) {
                        $fetchError = false;
                    }
                    if($temp['errorTitle']) {
                        $this->messageQueue->addMessage($this->createFlashMessage($temp['errorText'], $temp['errorTitle'], 2, false));
                    }
                    if($temp['warningTitle']) {
                        $this->messageQueue->addMessage($this->createFlashMessage($temp['warningText'], $temp['warningTitle'], 1, false));
                    }

                    $row = BackendUtility::getRecord('sys_dmail', $this->sys_dmail_uid);
                    
                    $data['info']['quickmail']['cmd'] = 'send_test';
                    $data['info']['quickmail']['senderName'] = htmlspecialchars($quickmail['senderName'] ?? '');
                    $data['info']['quickmail']['senderEmail'] = htmlspecialchars($quickmail['senderEmail'] ?? '');
                    $data['info']['quickmail']['subject'] = htmlspecialchars($quickmail['subject'] ?? '');
                    $data['info']['quickmail']['message'] = htmlspecialchars($quickmail['message'] ?? '');
                    $data['info']['quickmail']['breakLines'] = ($quickmail['breakLines'] ?? false) ? (int)$quickmail['breakLines'] : 0;
                } 
                // existing dmail
                elseif ($row) {
                    if ($row['type'] == '1' && (empty($row['HTMLParams']) || empty($row['plainParams']))) {
                        // it's a quickmail
                        $fetchError = false;
                        
                        $data['info']['dmail']['cmd'] = 'send_test';
                        
                        // add attachment here, since attachment added in 2nd step
                        $unserializedMailContent = unserialize(base64_decode($row['mailContent']));
                        $temp = $this->compileQuickMail($row, $unserializedMailContent['plain']['content'] ?? '', false);
                        if($temp['errorTitle']) {
                            $this->messageQueue->addMessage($this->createFlashMessage($temp['errorText'], $temp['errorTitle'], 2, false));
                        }
                        if($temp['warningTitle']) {
                            $this->messageQueue->addMessage($this->createFlashMessage($temp['warningText'], $temp['warningTitle'], 1, false));
                        }
                    } 
                    else {
                        if ($this->fetchAtOnce) {
                            $fetchMessage = DirectMailUtility::fetchUrlContentsForDirectMailRecord($row, $this->params);
                            $fetchError = ((strstr($fetchMessage, $this->getLanguageService()->getLL('dmail_error')) === false) ? false : true);
                        }
                        
                        $data['info']['dmail']['cmd'] = ($row['type'] == 0) ? $nextCmd : 'send_test';
                    }
                }
                
                $data['navigation']['back'] = true;
                $data['navigation']['next'] = true;
                $data['navigation']['next_error'] = $fetchError;
                
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
                $data['info']['table'] = is_array($row) ? $this->renderRecordDetailsTable($row) : '';
                $data['info']['sys_dmail_uid'] = $this->sys_dmail_uid;
                $data['info']['pages_uid'] = $row['page'] ?: '';
                $data['info']['currentCMD'] = $this->cmd;
                break;
                
            case 'cats':
                // shows category if content-based cat
                $this->currentStep = 3;
                $data['navigation']['currentStep'] = $this->currentStep;
                $data['cats'] = [
                    'currentStep' => $this->currentStep
                ];
                
                $data['navigation']['back'] = true;
                $data['navigation']['next'] = true;
                
                $indata = GeneralUtility::_GP('indata');
                $temp = $this->makeCategoriesForm($row, $indata);
                $data['cats']['output'] = $temp['output'];;
                $data['cats']['catsForm'] = $temp['theOutput'];

                $data['cats']['cmd'] = 'send_test';
                $data['cats']['sys_dmail_uid'] = $this->sys_dmail_uid;
                $data['cats']['pages_uid'] = $this->pages_uid;
                $data['cats']['currentCMD'] = $this->cmd;
                break;
                    
            case 'send_test':
                // Same as send_mail_test
            case 'send_mail_test':
                // send test mail
                $this->currentStep = (4 - (5 - $totalSteps));
                $data['navigation']['currentStep'] = $this->currentStep;
                $data['test'] = [
                    'currentStep' => $this->currentStep
                ];
                
                $data['navigation']['back'] = true;
                $data['navigation']['next'] = true;
                
                if ($this->cmd == 'send_mail_test') {
                    $this->sendMail($row);
                }
                $data['test']['testFormData'] = $this->getTestMailConfig();
                $data['test']['cmd'] = 'send_mass';
                $data['test']['sys_dmail_uid'] = $this->sys_dmail_uid;
                $data['test']['pages_uid'] = $this->pages_uid;
                $data['test']['currentCMD'] = $this->cmd;
                break;
                    
            case 'send_mail_final':
                // same as send_mass
            case 'send_mass':
                $this->currentStep = (5 - (5 - $totalSteps));
                $data['navigation']['currentStep'] = $this->currentStep;
                $data['final'] = [
                    'currentStep' => $this->currentStep
                ];
                
                if ($this->cmd == 'send_mass') {
                    $data['navigation']['back'] = true;
                }
                
                if ($this->cmd == 'send_mail_final') {
                    if (is_array($this->mailgroup_uid) && count($this->mailgroup_uid)) {
                        $this->sendMail($row);
                        break;
                    } 
                    else {
                        $message = $this->createFlashMessage(
                            $this->getLanguageService()->getLL('mod.no_recipients'),
                            '',
                            1,
                            false
                        );
                        $this->messageQueue->addMessage($message);
                    }
                }
                // send mass, show calendar
                $data['final']['finalForm'] = $this->cmd_finalmail($row);
                $data['final']['cmd'] = 'send_mail_final';
                $data['final']['sys_dmail_uid'] = $this->sys_dmail_uid;
                $data['final']['pages_uid'] = $this->pages_uid;
                $data['final']['currentCMD'] = $this->cmd;
                break;
                        
            default:
                // choose source newsletter
                $this->currentStep = 1;
                
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

                foreach ($showTabs as $showTab) {
                    $open = ($tsconfig['tx_directmail.']['defaultTab'] == $showTab);
                    switch ($showTab) {
                        case 'int':
                            $temp = $this->getConfigFormInternal();
                            $temp['open'] = $open;
                            $data['default']['internal'] = $temp;
                            break;
                        case 'ext':
                            $temp = $this->getConfigFormExternal();
                            $temp['open'] = $open;
                            $data['default']['external'] = $temp;
                            break;
                        case 'quick':
                            $temp = $this->getConfigFormQuickMail();
                            $temp['open'] = $open;
                            $data['default']['quick'] = $temp;
                            break;
                        case 'dmail':
                            $temp = $this->getConfigFormDMail();
                            $temp['open'] = $open;
                            $data['default']['dmail'] = $temp;
                            break;
                        default:
                    }
                }
        }

        $markers['data'] = $data;
        return $markers;
    }
    
    /**
     * Showing steps number on top of every page
     *
     * @param int $totalSteps Total step
     *
     * @return string HTML
     */
/**    
    protected function showSteps($totalSteps)
    {
        $content = '';
        for ($i = 1; $i <= $totalSteps; $i++) {
            $cssClass = ($i == $this->currentStep) ? 't3-wizard-item-active' : '';
            $content .= '<span class="t3-wizard-item ' . $cssClass . '">&nbsp;' . $i . '&nbsp;</span>';
        }
        return $content;
    }
*/
    /**
     * Makes expandable section using TYPO3-typical markup.
     *
     * @param int $sectionId
     * @param string $title
     * @param string $content
     * @return string
     */
/**    
    protected function makeSection(string $title, string $content, bool $isOpen): string
    {
        static $sectionId = 1;

        return sprintf(
            '<div class="panel panel-default">
                <div class="panel-heading" role="tab" id="heading%1$d">
                    <h4 class="panel-title">
                        <a role="button" data-bs-toggle="collapse" data-bs-parent="#accordion" href="#collapse%1$d" aria-expanded="' . ($isOpen ? 'true' : 'false') . '" aria-controls="collapse%1$d" class="' . (!$isOpen ? 'collapsed' : '') . '">
                            <span class="caret"></span>
                            %2$s
                        </a>
                    </h4>
                </div>
                <div id="collapse%1$d" class="panel-collapse collapse' . ($isOpen ? ' show' : '') . '" role="tabpanel" aria-labelledby="heading%1$d">
                    <div class="panel-body">
                        %3$s
                    </div>
                </div>
            </div>
            ',
            $sectionId++,
            $this->getLanguageService()->getLL($title),
            $content
        );
    }
*/
    /**
     * Makes box for internal page. (first step)
     *
     * @return array config for form list of internal pages
     */
    protected function getConfigFormInternal()
    {
        return [
            'title' => 'dmail_dovsk_crFromNL',
            'news' => $this->getNews(),
            'cshItem' => BackendUtility::cshItem($this->cshTable, 'select_newsletter'),
        ];
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
     * @return	array
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function getNews(): array
    {
        $rows = GeneralUtility::makeInstance(PagesRepository::class)->selectPagesForDmail($this->id, $this->perms_clause);
        $data = [];
        $empty = false;
        if (empty($rows)) {
            $empty = true;
        } 
        else {
            $iconActionsOpen = $this->getIconActionsOpen();
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
                    
                $previewHTMLLink = $previewTextLink = $createLink = '';
                foreach ($languages as $languageUid => $lang) {
                    $langParam = $this->getLanguageParam($languageUid, $this->params);
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
                    'returnUrl' => $this->requestUri,
                ];
                
                $data[] = [
                    'pageIcon' => $this->iconFactory->getIconForRecord('pages', $row, Icon::SIZE_SMALL),
                    'title' => htmlspecialchars($row['title']),
                    'createDmailLink' => $createDmailLink,
                    'createLink' => $createLink,
                    'editOnClickLink' => DirectMailUtility::getEditOnClickLink($params),
                    'iconActionsOpen' => $iconActionsOpen,
                    'previewLink' => $previewLink
                ];
            }
        }
            
        return ['empty' => $empty, 'rows' => $data];
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
                $langRow = GeneralUtility::makeInstance(PagesRepository::class)->selectPageByL10nAndSysLanguageUid($pageUid, $lang['uid']);

                if (!$langRow || empty($langRow)) {
                    continue;
                }
            }
            
            $languageUids[(int)$lang['uid']] = $lang;
        }
        
        return $languageUids;
    }
    
    /**
     * Makes config for form for external URL (first step)
     *
     * @return array config for form for inputing the external page information
     */
    protected function getConfigFormExternal()
    {
        return [
            'title' => 'dmail_dovsk_crFromUrl',
            'cshItem' => BackendUtility::cshItem($this->cshTable, 'create_directmail_from_url'),
            'no_valid_url' => (bool)($this->error == 'no_valid_url')
        ];
    }

    /**
     * Makes config for form for the quickmail (first step)
     *
     * @return array config for form for the quickmail
     */
    protected function getConfigFormQuickMail()
    {
        return [
            'id' => $this->id,
            'senderName' => htmlspecialchars($this->quickmail['senderName'] ?? $this->getBackendUser()->user['realName']),
            'senderMail' => htmlspecialchars($this->quickmail['senderEmail'] ?? $this->getBackendUser()->user['email']),
            'subject' => htmlspecialchars($this->quickmail['subject'] ?? ''),
            'message' => htmlspecialchars($this->quickmail['message'] ?? ''),
            'breakLines' => (bool)($this->quickmail['breakLines'] ?? false)
        ];
    }
    
    /**
     * List all direct mail, which have not been sent (first step)
     *
     * @return array config for form lists of all existing dmail records
     */
    protected function getConfigFormDMail()
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
        $rows = GeneralUtility::makeInstance(SysDmailRepository::class)->selectForMkeListDMail($this->id, $sOrder, $ascDesc);

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'icon' => $this->iconFactory->getIconForRecord('sys_dmail', $row, Icon::SIZE_SMALL)->render(),
                'link' => $this->linkDMailRecord($row['uid']),
                'linkText' => htmlspecialchars($row['subject'] ?: '_'),
                'tstamp' => BackendUtility::date($row['tstamp']),
                'issent' => ($row['issent'] ? $this->getLanguageService()->getLL('dmail_yes') : $this->getLanguageService()->getLL('dmail_no')),
                'renderedsize' => ($row['renderedsize'] ? GeneralUtility::formatSize($row['renderedsize']) : ''),
                'attachment' => ($row['attachment'] ? $this->iconFactory->getIcon('directmail-attachment', Icon::SIZE_SMALL) : ''),
                'type' => ($row['type'] & 0x1 ? $this->getLanguageService()->getLL('nl_l_tUrl') : $this->getLanguageService()->getLL('nl_l_tPage')) . ($row['type']  & 0x2 ? ' (' . $this->getLanguageService()->getLL('nl_l_tDraft') . ')' : ''),
                'deleteLink' => $this->deleteLink($row['uid'])
            ];
        }
        
        return $data;
    }

    /**
     * Creates a directmail entry in th DB.
     * used only for quickmail.
     *
     * @param array $indata Quickmail data (quickmail content, etc.)
     *
     * @return array error or warning message produced during the process
     */
    protected function createDMailQuick(array $indata)
    {
        $theOutput = [];
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
        $dmail['sys_dmail']['NEW']['long_link_rdct_url'] = $this->getUrlBase((int)$this->params['pid']);
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
            /* @var $dataHandler \TYPO3\CMS\Core\DataHandling\DataHandler */
            $dataHandler = $this->getDataHandler();
            $dataHandler->stripslashes_values = 0;
            $dataHandler->start($dmail, []);
            $dataHandler->process_datamap();
            $this->sys_dmail_uid = $dataHandler->substNEWwithIDs['NEW'];
            
            $row = BackendUtility::getRecord('sys_dmail', intval($this->sys_dmail_uid));
            // link in the mail
            $message = '<!--DMAILER_SECTION_BOUNDARY_-->' . $indata['message'] . '<!--DMAILER_SECTION_BOUNDARY_END-->';
            if (trim($this->params['use_rdct'])) {
                $message = DirectMailUtility::substUrlsInPlainText(
                    $message,
                    $this->params['long_link_mode'] ? 'all' : '76',
                    $this->getUrlBase((int)$this->params['pid'])
                );
            }
            if ($indata['breakLines'] ?? false) {
                $message = wordwrap($message, 76, "\n");
            }
            // fetch functions
            $theOutput = $this->compileQuickMail($row, $message);
            // end fetch function
        } 
        else {
            if (!$dmail['sys_dmail']['NEW']['sendOptions']) {
                $this->error = 'no_valid_url';
            }
        }
        
        return $theOutput;
    }
    
    /**
     * Wrap a string as a link
     *
     * @param int $uid UID of the directmail record
     *
     * @return string the link
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function linkDMailRecord($uid)
    {
        return $this->buildUriFromRoute(
            $this->moduleName,
            [
                'id' => $this->id,
                'sys_dmail_uid' => $uid,
                'fetchAtOnce' => 1,
                'cmd' => 'info'
            ]
        );
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
            return $this->buildUriFromRoute(
                $this->moduleName,
                [
                    'id' => $this->id,
                    'uid' => $uid,
                    'cmd' => 'delete'
                ]
            );
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
        $erg = ['errorTitle' => '', 'errorText' => '', 'warningTitle' => '', 'warningText' => ''];
        
        // Compile the mail
        /* @var $htmlmail Dmailer */
        $htmlmail = GeneralUtility::makeInstance(Dmailer::class);
        $htmlmail->nonCron = 1;
        $htmlmail->start();
        $htmlmail->charset = $row['charset'];
        $htmlmail->addPlain($message);

        if (!$message || !$htmlmail->theParts['plain']['content']) {
            $erg['errorTitle'] = $this->getLanguageService()->getLL('dmail_error');
            $erg['errorText'] = $this->getLanguageService()->getLL('dmail_no_plain_content');
        } 
        elseif (!strstr(base64_decode($htmlmail->theParts['plain']['content']), '<!--DMAILER_SECTION_BOUNDARY')) {
            $erg['warningTitle'] = $this->getLanguageService()->getLL('dmail_warning');
            $erg['warningText'] = $this->getLanguageService()->getLL('dmail_no_plain_boundaries');
        }

        // add attachment is removed. since it will be add during sending
        
        if (!$erg['errorTitle']) {
            // Update the record:
            $htmlmail->theParts['messageid'] = $htmlmail->messageid;
            $mailContent = base64_encode(serialize($htmlmail->theParts));

            GeneralUtility::makeInstance(SysDmailRepository::class)->updateSysDmail(
                intval($this->sys_dmail_uid), 
                $htmlmail->charset, $mailContent
            );
        }
        
        return $erg;
    }
    
    /**
     * Shows the infos of a directmail record in a table
     *
     * @param array $row DirectMail DB record
     *
     * @return array 
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function renderRecordDetailsTable(array $row)
    {
        $iconActionsOpen = $this->getIconActionsOpen();
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
                
                $content = '<a href="#" onClick="' . $editParams . '">' . $iconActionsOpen . '<b> ' . $this->getLanguageService()->getLL('dmail_edit') . '</b></a>';
            } 
            else {
                $content = $iconActionsOpen . ' (' . $this->getLanguageService()->getLL('dmail_noEdit_noPerms') . ')';
            }
        } 
        else {
            $content = $iconActionsOpen . '(' . $this->getLanguageService()->getLL('dmail_noEdit_isSent') . ')';
        }

        $trs = [];
        $nameArr = ['from_name','from_email','replyto_name','replyto_email','organisation','return_path','priority','type','page',
            'sendOptions','includeMedia','flowedFormat','sys_language_uid','plainParams','HTMLParams','encoding','charset','issent','renderedsize'];
        foreach ($nameArr as $name) {
            $trs[] = [
                'title' => DirectMailUtility::fName($name),
                'value' => htmlspecialchars((string)BackendUtility::getProcessedValue('sys_dmail', $name, ($row[$name] ?? false)))
            ];
        }
        
        // attachments need to be fetched manually as BackendUtility::getProcessedValue can't do that
        $fileNames = [];
        $attachments = DirectMailUtility::getAttachments($row['uid'] ?? 0);
        /** @var FileReference $attachment */
        if(count($attachments)) {
            foreach ($attachments as $attachment) {
                $fileNames[] = $attachment->getName();
            }
        }
        
        $trs[] = [
            'title' => DirectMailUtility::fName('attachment'),
            'value' => implode(', ', $fileNames)
        ];
        
        return [
            'icon' => $this->iconFactory->getIconForRecord('sys_dmail', $row, Icon::SIZE_SMALL),
            'title' => htmlspecialchars($row['subject'] ?? ''),
            'theadTitle1' => DirectMailUtility::fName('subject'),
            'theadTitle2' => GeneralUtility::fixed_lgd_cs(htmlspecialchars($row['subject'] ?? ''), 60),
            'trs' => $trs,
            'out'  => $content
        ];
    }
    
    /**
     * Show the step of sending a test mail
     *
     * @return config for form
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function getTestMailConfig(): array
    {
        $data = [
            'test_tt_address'  => '',
            'test_dmail_group_table' => []
        ];

        if ($this->params['test_tt_address_uids'] ?? false) {
            $intList = implode(',', GeneralUtility::intExplode(',', $this->params['test_tt_address_uids']));            
            $rows = GeneralUtility::makeInstance(TtAddressRepository::class)->selectTtAddressForTestmail($intList, $this->perms_clause);
        
            $ids = [];
                    
            foreach ($rows as $row) {
                $ids[] = $row['uid'];
            }
            $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($ids, 'tt_address');
            $data['test_tt_address'] = $this->getRecordList($rows, 'tt_address', 1, 1);
        }

        if ($this->params['test_dmail_group_uids'] ?? false) {
            $intList = implode(',', GeneralUtility::intExplode(',', $this->params['test_dmail_group_uids']));
            $rows = GeneralUtility::makeInstance(SysDmailGroupRepository::class)->selectSysDmailGroupForTestmail($intList, $this->perms_clause);
       
            foreach ($rows as $row) {
                $moduleUrl = $this->buildUriFromRoute(
                    $this->moduleName,
                    [
                        'id' => $this->id,
                        'sys_dmail_uid' => $this->sys_dmail_uid,
                        'cmd' => 'send_mail_test',
                        'sys_dmail_group_uid[]' => $row['uid']
                    ]
                );

                // Members:
                $result = $this->cmd_compileMailGroup([$row['uid']]);
                
                $data['test_dmail_group_table'][] = [
                    'moduleUrl' => $moduleUrl,
                    'iconFactory' => $this->iconFactory->getIconForRecord('sys_dmail_group', $row, Icon::SIZE_SMALL),
                    'title' => htmlspecialchars($row['title']),
                    'tds' => $this->displayMailGroup_test($result)
                ];
            }
        }
        
        $data['dmail_test_email'] = $this->MOD_SETTINGS['dmail_test_email'] ?? '';
        $data['id'] = $this->id;
        $data['cmd'] = 'send_mail_test';
        $data['sys_dmail_uid'] = $this->sys_dmail_uid;

        return $data;
    }

    /**
     * Display the test mail group, which configured in the configuration module
     *
     * @param array $result Lists of the recipient IDs based on directmail DB record
     *
     * @return string List of the recipient (in HTML)
     */
    public function displayMailGroup_test($result)
    {
        $idLists = $result['queryInfo']['id_lists'];
        $out = '';
        if (is_array($idLists['tt_address'] ?? false)) {
            $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists['tt_address'], 'tt_address');
            $out .= $this->getRecordList($rows, 'tt_address');
        }
        if (is_array($idLists['fe_users'] ?? false)) {
            $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists['fe_users'], 'fe_users');
            $out .= $this->getRecordList($rows, 'fe_users');
        }
        if (is_array($idLists['PLAINLIST'] ?? false)) {
            $out .= $this->getRecordList($idLists['PLAINLIST'], 'default');
        }
        if (is_array($idLists[$this->userTable] ?? false)) {
            $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists[$this->userTable], $this->userTable);
            $out .= $this->getRecordList($rows, $this->userTable);
        }
        
        return $out;
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
    protected function sendMail($row)
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
            $addresses = preg_split('|[' . LF . ',;]|', $addressList ?? '');
            
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
            }
        } 
        elseif ($this->cmd == 'send_mail_test') {
            // step 4, sending test personalized test emails
            // setting Testmail flag
            $htmlmail->testmail = $this->params['testmail'];

            if ($this->tt_address_uid) {
                // personalized to tt_address
                $res = GeneralUtility::makeInstance(TtAddressRepository::class)->selectTtAddressForSendMailTest($this->tt_address_uid, $this->perms_clause);

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
                } 
                else {
                    $message = $this->createFlashMessage(
                        'Error: No valid recipient found to send test mail to. #1579209279', 
                        $this->getLanguageService()->getLL('send_sending'), 
                        2, 
                        false
                    );
                    $this->messageQueue->addMessage($message);
                }
            } 
            elseif (is_array(GeneralUtility::_GP('sys_dmail_group_uid'))) {
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
        } 
        else {
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
     * Send mail to recipient based on table.
     *
     * @param array $idLists List of recipient ID
     * @param string $table Table name
     * @param Dmailer $htmlmail Object of the dmailer script
     *
     * @return int Total of sent mail
     * @todo: remove htmlmail. sending mails to table
     */
    protected function sendTestMailToTable(array $idLists, $table, Dmailer $htmlmail): int
    {
        $sentFlag = 0;
        if (is_array($idLists[$table])) {
            if ($table != 'PLAINLIST') {
                $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists[$table], $table, '*');
            } 
            else {
                $rows = $idLists['PLAINLIST'];
            }
            foreach ($rows as $rec) {
                $recipRow = $htmlmail->convertFields($rec);
                $recipRow['sys_dmail_categories_list'] = $htmlmail->getListOfRecipentCategories($table, $recipRow['uid']);
                $kc = substr($table, 0, 1);
                $returnCode = $htmlmail->dmailer_sendAdvanced($recipRow, $kc == 'p' ? 'P' : $kc);
                if ($returnCode) {
                    $sentFlag++;
                }
            }
        }
        return $sentFlag;
    }

    /**
     * Show the recipient info and a link to edit it
     *
     * @param array $listArr List of recipients ID
     * @param string $table Table name
     * @param bool|int $editLinkFlag If set, edit link is showed
     * @param bool|int $testMailLink If set, send mail link is showed
     *
     * @return string HTML, the table showing the recipient's info
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    public function getRecordList(array $listArr, $table, $editLinkFlag=1, $testMailLink=0)
    {
        $count = 0;
        $lines = [];
        $out = '';
        if (is_array($listArr)) {
            $iconActionsOpen = $this->getIconActionsOpen();
            $count = count($listArr);
            /** @var UriBuilder $uriBuilder */
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            foreach ($listArr as $row) {
                $tableIcon = '';
                $editLink = '';
                $testLink = '';
                
                if ($row['uid']) {
                    $tableIcon = '<td>' . $this->iconFactory->getIconForRecord($table, $row, Icon::SIZE_SMALL) . '</td>';
                    if ($editLinkFlag) {
                        $requestUri = $this->requestUri . '&cmd=send_test&sys_dmail_uid=' . $this->sys_dmail_uid . '&pages_uid=' . $this->pages_uid;
                        
                        $params = [
                            'edit' => [
                                $table => [
                                    $row['uid'] => 'edit',
                                ]
                            ],
                            'returnUrl' => $requestUri
                        ];
                        
                        $editOnClick = DirectMailUtility::getEditOnClickLink($params);
                        $editLink = '<td><a href="#" onClick="' . $editOnClick . '" title="' . $this->getLanguageService()->getLL('dmail_edit') . '">' .
                            $iconActionsOpen .
                            '</a></td>';
                    }
                    
                    if ($testMailLink) {
                        $moduleUrl = $uriBuilder->buildUriFromRoute(
                            $this->moduleName,
                            [
                                'id' => $this->id,
                                'sys_dmail_uid' => $this->sys_dmail_uid,
                                'cmd' => 'send_mail_test',
                                'tt_address_uid' => $row['uid']
                            ]
                            );
                        $testLink = '<a href="' . $moduleUrl . '">' . htmlspecialchars($row['email']) . '</a>';
                    } else {
                        $testLink = htmlspecialchars($row['email']);
                    }
                }
                
                $lines[] = '<tr class="db_list_normal">
				' . $tableIcon . '
				' . $editLink . '
				<td nowrap> ' . $testLink . ' </td>
				<td nowrap> ' . htmlspecialchars($row['name']) . ' </td>
				</tr>';
            }
        }
        if (count($lines)) {
            $out = '<p>'.$this->getLanguageService()->getLL('dmail_number_records') . ' <strong>' . $count . '</strong></p><br />';
            $out .= '<table border="0" cellspacing="1" cellpadding="0" class="table table-striped table-hover">' . implode(LF, $lines) . '</table>';
        }
        return $out;
    }
    
    /**
     * Shows the final steps of the process. Show recipient list and calendar library
     *
     * @param array $direct_mail_row
     * @return	array		HTML
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
        $groups = GeneralUtility::makeInstance(SysDmailGroupRepository::class)->selectSysDmailGroupForFinalMail(
            $this->id, 
            (int)$direct_mail_row['sys_language_uid'], 
            trim($GLOBALS['TCA']['sys_dmail_group']['ctrl']['default_sortby'])
        );

        $opt = [];
        $lastGroup = null;
        if($groups) {
            foreach($groups as $group)  {
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
            $groupInput = '<select class="form-control" size="20" multiple="multiple" name="mailgroup_uid[]" '.($hookSelectDisabled ? 'disabled' : '').'>'.implode(chr(10), $opt).'</select>';
        }

        return [
            'id' => $this->id,
            'sys_dmail_uid' => $this->sys_dmail_uid,
            'groupInput' => $groupInput,
            'hookContents' => $hookContents, // put content from hook
            'send_mail_datetime_hr' => strftime('%H:%M %d-%m-%Y', time()),
            'send_mail_datetime' => strftime('%H:%M %d-%m-%Y', time())
        ];
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
                                $idLists['tt_address'] = GeneralUtility::makeInstance(TempRepository::class)->getIdList('tt_address', $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            if ($whichTables&2) {
                                // fe_users
                                $idLists['fe_users'] = GeneralUtility::makeInstance(TempRepository::class)->getIdList('fe_users', $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            if ($this->userTable && ($whichTables&4)) {
                                // user table
                                $idLists[$this->userTable] = GeneralUtility::makeInstance(TempRepository::class)->getIdList($this->userTable, $pidList, $groupUid, $mailGroup['select_categories']);
                            }
                            if ($whichTables&8) {
                                // fe_groups
                                if (!is_array($idLists['fe_users'])) {
                                    $idLists['fe_users'] = [];
                                }
                                $idLists['fe_users'] = GeneralUtility::makeInstance(TempRepository::class)->getIdList('fe_groups', $pidList, $groupUid, $mailGroup['select_categories']);
                                $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users']));
                            }
                        }
                        break;
                    case 1:
                        // List of mails
                        if ($mailGroup['csv'] == 1) {
                            $dmCsvUtility = GeneralUtility::makeInstance(DmCsvUtility::class);
                            $recipients = $dmCsvUtility->rearrangeCsvValues($dmCsvUtility->getCsvValues($mailGroup['list']), $this->fieldList);
                        } 
                        else {
                            $recipients = DirectMailUtility::rearrangePlainMails(array_unique(preg_split('|[[:space:],;]+|', $mailGroup['list'])));
                        }
                        $idLists['PLAINLIST'] = DirectMailUtility::cleanPlainList($recipients);
                        break;
                    case 2:
                        // Static MM list
                        $idLists['tt_address'] = GeneralUtility::makeInstance(TempRepository::class)->getStaticIdList('tt_address', $groupUid);
                        $idLists['fe_users'] = GeneralUtility::makeInstance(TempRepository::class)->getStaticIdList('fe_users', $groupUid);
                        $tempGroups = GeneralUtility::makeInstance(TempRepository::class)->getStaticIdList('fe_groups', $groupUid);
                        $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users'], $tempGroups));
                        if ($this->userTable) {
                            $idLists[$this->userTable] = GeneralUtility::makeInstance(TempRepository::class)->getStaticIdList($this->userTable, $groupUid);
                        }
                        break;
                    case 3:
                        // Special query list
                        $mailGroup = $this->updateSpecialQuery($mailGroup);
                        $whichTables = intval($mailGroup['whichtables']);
                        $table = '';
                        if ($whichTables&1) {
                            $table = 'tt_address';
                        } 
                        elseif ($whichTables&2) {
                            $table = 'fe_users';
                        } 
                        elseif ($this->userTable && ($whichTables&4)) {
                            $table = $this->userTable;
                        }

                        if ($table) {
                            $queryGenerator = GeneralUtility::makeInstance(MailSelect::class, $this->MOD_SETTINGS, [], $this->moduleName);
                            $idLists[$table] = GeneralUtility::makeInstance(TempRepository::class)->getSpecialQueryIdList($queryGenerator, $table, $mailGroup);
                        }
                        break;
                    case 4:
                        $groups = array_unique(GeneralUtility::makeInstance(TempRepository::class)->getMailGroups($mailGroup['mail_groups'], [$mailGroup['uid']], $this->perms_clause));
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
     * Update the mailgroup DB record
     *
     * @param array $mailGroup Mailgroup DB record
     *
     * @return array Mailgroup DB record after updated
     */
    public function updateSpecialQuery(array $mailGroup)
    {
        $set = GeneralUtility::_GP('SET');
        $queryTable = $set['queryTable'];
        $queryConfig = GeneralUtility::_GP('queryConfig');
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

            $connection = $this->getConnection('sys_dmail_group');
            
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
     * Show the categories table for user to categorize the directmail content
     * TYPO3 content)
     *
     * @param array $row The dmail row.
     * @param $indata
     *
     * @return string HTML form showing the categories
     */
    public function makeCategoriesForm(array $row, $indata)
    {
        $output = [
            'title' => $this->getLanguageService()->getLL('nl_cat'), 
            'subtitle' => '',
            'rowsFound' => false,
            'rows' => [],
            'pages_uid' => $this->pages_uid,
            'cmd' => $this->CMD,
            'update_cats' => $this->getLanguageService()->getLL('nl_l_update'),
            'output' => ''
        ];
        $theOutput = '';
        
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

            /* @var $dataHandler \TYPO3\CMS\Core\DataHandling\DataHandler */
            $dataHandler = $this->getDataHandler();
            $dataHandler->stripslashes_values = 0;
            $dataHandler->start($data, []);
            $dataHandler->process_datamap();

            // remove cache
            $dataHandler->clear_cacheCmd($this->pages_uid);
            $theOutput = DirectMailUtility::fetchUrlContentsForDirectMailRecord($row, $this->params);
        }

        // @TODO Perhaps we should here check if TV is installed and fetch content from that instead of the old Columns...
        $rows = GeneralUtility::makeInstance(TtContentRepository::class)->selectTtContentByPidAndSysLanguageUid(
            (int)$this->pages_uid, 
            (int)$row['sys_language_uid']
        );
        if (empty($rows)) {
            $output['subtitle'] = $this->getLanguageService()->getLL('nl_cat_msg1');
        } 
        else {
            //https://api.typo3.org/master/class_t_y_p_o3_1_1_c_m_s_1_1_backend_1_1_utility_1_1_backend_utility.html#a5522e461e5ce3b1b5c87ee7546af449d
            $output['subtitle'] = BackendUtility::cshItem($this->cshTable, 'assign_categories');
            $output['rowsFound'] = true;
            
            $colPosVal = 99;
            foreach ($rows as $row) {
                $categoriesRow = '';
                $resCat = GeneralUtility::makeInstance(TtContentCategoryMmRepository::class)->selectUidForeignByUid($row['uid']);

                foreach ($resCat as $rowCat) {
                    $categoriesRow .= $rowCat['uid_foreign'] . ',';
                }
                $categoriesRow = rtrim($categoriesRow, ',');

                if ($colPosVal != $row['colPos']) {
                    $output['rows'][] = [
                        'separator' => true,
                        'bgcolor' => $this->doc->bgColor5,
                        'title' => $this->getLanguageService()->getLL('nl_l_column'),
                        'value' => BackendUtility::getProcessedValue('tt_content', 'colPos', $row['colPos']) 
                    ];
                    $colPosVal = $row['colPos'];
                }
                
                $this->categories = GeneralUtility::makeInstance(TempRepository::class)->makeCategories('tt_content', $row, $this->sys_language_uid);
                reset($this->categories);
                $cboxes = [];
                foreach ($this->categories as $pKey => $pVal) {
                    $cboxes[] = [
                        'pKey' => $pKey,
                        'checked' => GeneralUtility::inList($categoriesRow, $pKey) ? true : false,
                        'pVal' => htmlspecialchars($pVal)
                    ];
                }

                $output['rows'][] = [
                    'uid' => $row['uid'],
                    'icon' => $this->iconFactory->getIconForRecord('tt_content', $row, Icon::SIZE_SMALL),
                    'header' => $row['header'],
                    'CType' => $row['CType'],
                    'list_type' => $row['list_type'],
                    'bodytext' => empty($row['bodytext']) ? '' : GeneralUtility::fixed_lgd_cs(strip_tags($row['bodytext']), 200),
                    'color' => $row['module_sys_dmail_category'] ? 'red' : 'green',
                    'labelOnlyAll' => $row['module_sys_dmail_category'] ? $this->getLanguageService()->getLL('nl_l_ONLY') : $this->getLanguageService()->getLL('nl_l_ALL'),
                    'checkboxes' => $cboxes
                ];
            }
        }
        return ['output' => $output, 'theOutput' => $theOutput];
    }
    
    /**
     * Get language param
     *
     * @param string $sysLanguageUid
     * @param array $params direct_mail settings
     * @return string
     */
    public function getLanguageParam($sysLanguageUid, array $params)
    {
        if (isset($params['langParams.'][$sysLanguageUid])) {
            $param = $params['langParams.'][$sysLanguageUid];
            
            // fallback: L == sys_language_uid
        }
        else {
            $param = '&L=' . $sysLanguageUid;
        }
        
        return $param;
    }
    
    /**
     * Creates a directmail entry in th DB.
     * Used only for internal pages
     *
     * @param int $pageUid The page ID
     * @param array $parameters The dmail Parameter
     *
     * @param int $sysLanguageUid
     * @return int|bool new record uid or FALSE if failed
     */
    public function createDirectMailRecordFromPage(int $pageUid, array $parameters, int $sysLanguageUid = 0)
    {
        $result = false;
        
        $newRecord = [
            'type'                  => 0,
            'pid'                   => $parameters['pid'] ?? 0,
            'from_email'            => $parameters['from_email'] ?? '',
            'from_name'             => $parameters['from_name'] ?? '',
            'replyto_email'         => $parameters['replyto_email'] ?? '',
            'replyto_name'          => $parameters['replyto_name'] ?? '',
            'return_path'           => $parameters['return_path'] ?? '',
            'priority'              => $parameters['priority'] ?? 0,
            'use_rdct'              => (!empty($parameters['use_rdct']) ? $parameters['use_rdct']:0), /*$parameters['use_rdct'],*/
            'long_link_mode'        => (!empty($parameters['long_link_mode']) ? $parameters['long_link_mode']:0),//$parameters['long_link_mode'],
            'organisation'          => $parameters['organisation'] ?? '',
            'authcode_fieldList'    => $parameters['authcode_fieldList'] ?? '',
            'sendOptions'           => $GLOBALS['TCA']['sys_dmail']['columns']['sendOptions']['config']['default'],
            'long_link_rdct_url'    => $this->getUrlBase((int)$pageUid),
            'sys_language_uid'      => (int)$sysLanguageUid,
            'attachment'            => '',
            'mailContent'           => ''
        ];
        
        if ($newRecord['sys_language_uid'] > 0) {
            $langParam = $this->getLanguageParam($newRecord['sys_language_uid'], $parameters);
            $parameters['plainParams'] .= $langParam;
            $parameters['HTMLParams'] .= $langParam;
        }
        
        // If params set, set default values:
        $paramsToOverride = ['sendOptions', 'includeMedia', 'flowedFormat', 'HTMLParams', 'plainParams'];
        foreach ($paramsToOverride as $param) {
            if (isset($parameters[$param])) {
                $newRecord[$param] = $parameters[$param];
            }
        }
        if (isset($parameters['direct_mail_encoding'])) {
            $newRecord['encoding'] = $parameters['direct_mail_encoding'];
        }
        
        $pageRecord = BackendUtility::getRecord('pages', $pageUid);
        // Fetch page title from translated page
        if ($newRecord['sys_language_uid'] > 0) {
            $pageRecordOverlay = GeneralUtility::makeInstance(PagesRepository::class)->selectTitleTranslatedPage($pageUid, (int)$newRecord['sys_language_uid']);
            if (is_array($pageRecordOverlay)) {
                $pageRecord['title'] = $pageRecordOverlay['title'];
            }
        }
        
        if ($pageRecord['doktype']) {
            $newRecord['subject'] = $pageRecord['title'];
            $newRecord['page']    = $pageRecord['uid'];
            $newRecord['charset'] = DirectMailUtility::getCharacterSet();
        }
        
        // save to database
        if ($newRecord['page'] && $newRecord['sendOptions']) {
            $tcemainData = [
                'sys_dmail' => [
                    'NEW' => $newRecord
                ]
            ];
            
            /* @var $dataHandler \TYPO3\CMS\Core\DataHandling\DataHandler */
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->stripslashes_values = 0;
            $dataHandler->start($tcemainData, []);
            $dataHandler->process_datamap();
            $result = $dataHandler->substNEWwithIDs['NEW'];
        }
        elseif (!$newRecord['sendOptions']) {
            $result = false;
        }
        return $result;
    }
    
    /**
     * Creates a directmail entry in th DB.
     * Used only for external pages
     *
     * @param string $subject Subject of the newsletter
     * @param string $externalUrlHtml Link to the HTML version
     * @param string $externalUrlPlain Linkt to the text version
     * @param array $parameters Additional newsletter parameters
     *
     * @return	int/bool Error or warning message produced during the process
     */
    public function createDirectMailRecordFromExternalURL($subject, $externalUrlHtml, $externalUrlPlain, array $parameters)
    {
        $result = false;
        
        $newRecord = [
            'type'                  => 1,
            'pid'                   => $parameters['pid'] ?? 0,
            'subject'               => $subject,
            'from_email'            => $parameters['from_email'] ?? '',
            'from_name'             => $parameters['from_name'] ?? '',
            'replyto_email'         => $parameters['replyto_email'] ?? '',
            'replyto_name'          => $parameters['replyto_name'] ?? '',
            'return_path'           => $parameters['return_path'] ?? '',
            'priority'              => $parameters['priority'] ?? 0,
            'use_rdct'              => (!empty($parameters['use_rdct']) ? $parameters['use_rdct'] : 0),
            'long_link_mode'        => $parameters['long_link_mode'] ?? '',
            'organisation'          => $parameters['organisation'] ?? '',
            'authcode_fieldList'    => $parameters['authcode_fieldList'] ?? '',
            'sendOptions'           => $GLOBALS['TCA']['sys_dmail']['columns']['sendOptions']['config']['default'],
            'long_link_rdct_url'    => $this->getUrlBase((int)($parameters['page'] ?? 0))
        ];
        
        // If params set, set default values:
        $paramsToOverride = ['sendOptions', 'includeMedia', 'flowedFormat', 'HTMLParams', 'plainParams'];
        foreach ($paramsToOverride as $param) {
            if (isset($parameters[$param])) {
                $newRecord[$param] = $parameters[$param];
            }
        }
        if (isset($parameters['direct_mail_encoding'])) {
            $newRecord['encoding'] = $parameters['direct_mail_encoding'];
        }
        
        $urlParts = @parse_url($externalUrlPlain);
        // No plain text url
        if (!$externalUrlPlain || $urlParts === false || !$urlParts['host']) {
            $newRecord['plainParams'] = '';
            $newRecord['sendOptions'] &= 254;
        }
        else {
            $newRecord['plainParams'] = $externalUrlPlain;
        }
        
        // No html url
        $urlParts = @parse_url($externalUrlHtml);
        if (!$externalUrlHtml || $urlParts === false || !$urlParts['host']) {
            $newRecord['sendOptions'] &= 253;
        }
        else {
            $newRecord['HTMLParams'] = $externalUrlHtml;
        }
        
        // save to database
        if ($newRecord['pid'] && $newRecord['sendOptions']) {
            $tcemainData = [
                'sys_dmail' => [
                    'NEW' => $newRecord
                ]
            ];
            
            /* @var $dataHandler \TYPO3\CMS\Core\DataHandling\DataHandler */
            $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
            $dataHandler->stripslashes_values = 0;
            $dataHandler->start($tcemainData, []);
            $dataHandler->process_datamap();
            $result = $dataHandler->substNEWwithIDs['NEW'];
        }
        elseif (!$newRecord['sendOptions']) {
            $result = false;
        }
        return $result;
    }
    
    /**
     * Get the base URL
     *
     * @param int $pageId
     * @return string
     * @throws SiteNotFoundException
     * @throws InvalidRouteArgumentsException
     */
    protected function getUrlBase(int $pageId): string
    {
        if ($pageId > 0) {
            /** @var SiteFinder $siteFinder */
            $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
            if (!empty($siteFinder->getAllSites())) {
                $site = $siteFinder->getSiteByPageId($pageId);
                $base = $site->getBase();
                
                return sprintf('%s://%s', $base->getScheme(), $base->getHost());
            }
            else {
                return ''; // No site found in root line of pageId
            }
        }
        
        return ''; // No valid pageId
    }
}
