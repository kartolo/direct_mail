<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Module;

use DirectMailTeam\DirectMail\DirectMailUtility;
use DirectMailTeam\DirectMail\Dmailer;
use DirectMailTeam\DirectMail\DmQueryGenerator;
use DirectMailTeam\DirectMail\Enum\DmailCmdEnum;
use DirectMailTeam\DirectMail\Event\DmailCompileMailGroupEvent;
use DirectMailTeam\DirectMail\Repository\FeGroupsRepository;
use DirectMailTeam\DirectMail\Repository\FeUsersRepository;
use DirectMailTeam\DirectMail\Repository\PagesRepository;
use DirectMailTeam\DirectMail\Repository\SysDmailGroupRepository;
use DirectMailTeam\DirectMail\Repository\SysDmailRepository;
use DirectMailTeam\DirectMail\Repository\TempRepository;
use DirectMailTeam\DirectMail\Repository\TtAddressRepository;
use DirectMailTeam\DirectMail\Repository\TtContentCategoryMmRepository;
use DirectMailTeam\DirectMail\Repository\TtContentRepository;
use DirectMailTeam\DirectMail\Utility\DmCsvUtility;
use DirectMailTeam\DirectMail\Utility\TsUtility;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Configuration\TranslationConfigurationProvider;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Error\Http\ServiceUnavailableException;
use TYPO3\CMS\Core\Exception\SiteNotFoundException;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\ImmediateResponseException;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Routing\InvalidRouteArgumentsException;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\ArrayUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Backend\Attribute\Controller;
// the module template will be initialized in handleRequest()
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

final class DmailController extends MainController
{

    protected FlashMessageQueue $flashMessageQueue;

    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly IconFactory $iconFactory,
        protected readonly PageRenderer $pageRenderer,

        protected readonly string $moduleName = 'directmail_module_directmail',
        protected readonly string $lllFile = 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf',

        protected ?LanguageService $languageService = null,

        protected array $pageinfo = [],
        protected int $id = 0,
        protected bool $access = false,
        protected string $cmd = '',

        protected string $cshTable = '',
        protected string $error = '',

        protected int $currentStep = 1,

        protected int $uid = 0,
        protected array $MOD_SETTINGS = [],

        protected bool $backButtonPressed = false,

        protected string $currentCMD = '',
        protected bool $fetchAtOnce = false,

        protected array $quickmail = [],
        protected int $createMailFrom_UID = 0,
        protected string $createMailFrom_URL = '',
        protected int $createMailFrom_LANG = 0,
        protected string $createMailFrom_HTMLUrl = '',
        protected string $createMailFrom_plainUrl = '',
        protected array $mailgroup_uid = [],
        protected bool $mailingMode_simple = false,
        protected int $tt_address_uid = 0,
        protected array $indata = [],
        protected array $addresses = [],
        protected array $sysDmailGroupUid = [],
        protected array $mailgroupUid = [],
        protected bool $mailingModeMailGroup = false,
        protected string $requestUri = '',
        protected string $queryConfig = '',
        protected string $sendMailDatetimeHr = '',
        protected bool $testmail = false,
        protected bool $savedraft = false,
        protected array $set = [],
    ) {
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->languageService = $this->getLanguageService();
        $this->flashMessageQueue = $this->getFlashMessageQueue('DmailQueue');

        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $this->id             = (int)($parsedBody['id']              ?? $queryParams['id'] ?? 0);
        $this->cmd            = (string)($parsedBody['cmd']          ?? $queryParams['cmd'] ?? '');
        $this->pages_uid      = (string)($parsedBody['pages_uid']    ?? $queryParams['pages_uid'] ?? '');
        $this->sys_dmail_uid  = (int)($parsedBody['sys_dmail_uid']   ?? $queryParams['sys_dmail_uid'] ?? 0);
        $this->updatePageTree = (bool)($parsedBody['updatePageTree'] ?? $queryParams['updatePageTree'] ?? false);

        $permsClause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        $pageAccess = BackendUtility::readPageAccess($this->id, $permsClause);
        $this->pageinfo = is_array($pageAccess) ? $pageAccess : [];
        $this->access = is_array($this->pageinfo) ? true : false;

        // get the config from pageTS
        $this->params = BackendUtility::getPagesTSconfig($this->id)['mod.']['web_modules.']['dmail.'] ?? [];
        $this->implodedParams = GeneralUtility::makeInstance(TsUtility::class)->implodeTSParams($this->params);

        if ($this->params['userTable'] ?? false && isset($GLOBALS['TCA'][$this->params['userTable']]) && is_array($GLOBALS['TCA'][$this->params['userTable']])) {
            $this->userTable = $this->params['userTable'];
            $this->allowedTables[] = $this->userTable;
        }

        $normalizedParams = $request->getAttribute('normalizedParams');

        $this->pageRenderer->loadJavaScriptModule('@typo3/backend/date-time-picker.js');

        $this->requestUri = $normalizedParams->getRequestUri();

        $this->uid = (int)($parsedBody['uid'] ?? $queryParams['uid'] ?? 0);

        $update_cats = $parsedBody['update_cats'] ?? $queryParams['update_cats'] ?? false;
        if ($update_cats) {
            $this->cmd = DmailCmdEnum::Categories->value;
        }

        $this->mailingMode_simple = (bool)($parsedBody['mailingMode_simple'] ?? $queryParams['mailingMode_simple'] ?? false);
        if ($this->mailingMode_simple) {
            $this->cmd = DmailCmdEnum::SendMailTest->value;
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

        $this->indata = $parsedBody['indata'] ?? $queryParams['indata'] ?? [];
        $this->addresses = $parsedBody['SET'] ?? $queryParams['SET'] ?? [];
        $this->sysDmailGroupUid = $parsedBody['sys_dmail_group_uid'] ?? $queryParams['sys_dmail_group_uid'] ?? [];
        $this->mailgroupUid = $parsedBody['mailgroup_uid'] ?? $queryParams['mailgroup_uid'] ?? [];
        $this->mailingModeMailGroup = (bool)($parsedBody['mailingMode_mailGroup'] ?? $queryParams['mailingMode_mailGroup'] ?? false);
        $this->queryConfig = (string)($parsedBody['queryConfig'] ?? $queryParams['queryConfig'] ?? '');
        $this->sendMailDatetimeHr = (string)($parsedBody['send_mail_datetime_hr'] ?? $queryParams['send_mail_datetime_hr'] ?? '');
        $this->testmail = (bool)($parsedBody['testmail'] ?? $queryParams['testmail'] ?? false);
        $this->savedraft = (bool)($parsedBody['savedraft'] ?? $queryParams['savedraft'] ?? false);
        $this->set = is_array($parsedBody['SET'] ?? '') ? $parsedBody['SET'] : [];

        if ($this->updatePageTree) {
            \TYPO3\CMS\Backend\Utility\BackendUtility::setUpdateSignal('updatePageTree');
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        return $this->indexAction($moduleTemplate);
    }

    public function indexAction(ModuleTemplate $view): ResponseInterface
    {
        // get the config from pageTS
        $this->params['pid'] = $this->id;
        $this->cshTable = '_MOD_' . $this->moduleName;

        if (($this->id && $this->access) || ($this->isAdmin() && !$this->id)) {

            $module = $this->getModulName();

            if ($module == 'dmail') {
                // Direct mail module
                if (($this->pageinfo['doktype'] ?? 0) == 254) {
                    $this->pageRenderer->loadJavaScriptModule('@typo3/backend/date-time-picker.js');
                    $markers = $this->moduleContent();
                    $view->assignMultiple(
                        [
                            'flashmessages' => $markers['FLASHMESSAGES'],
                            'data' => $markers['data'],
                        ]
                    );
                } elseif ($this->id != 0) {
                    $message = $this->createFlashMessage(
                        $this->languageService->sL($this->lllFile . ':dmail_noRegular'),
                        $this->languageService->sL($this->lllFile . ':dmail_newsletters'),
                        ContextualFeedbackSeverity::WARNING,
                        false
                    );
                    $this->flashMessageQueue->addMessage($message);
                }
            } else {
                $message = $this->createFlashMessage(
                    $this->languageService->sL($this->lllFile . ':select_folder'),
                    $this->languageService->sL($this->lllFile . ':header_directmail'),
                    ContextualFeedbackSeverity::WARNING,
                    false
                );
                $this->flashMessageQueue->addMessage($message);
                $view->assignMultiple(
                    [
                        'dmLinks' => $this->getDMPages($this->moduleName),
                    ]
                );
            }
        } else {
            $message = $this->createFlashMessage(
                $this->languageService->sL($this->lllFile . ':mod.main.no_access'),
                $this->languageService->sL($this->lllFile . ':mod.main.no_access.title'),
                ContextualFeedbackSeverity::WARNING,
                false
            );
            $this->flashMessageQueue->addMessage($message);
            return $view->renderResponse('NoAccess');
        }

        return $view->renderResponse('Dmail');
    }

    protected function moduleContent(): array
    {
        $isExternalDirectMailRecord = false;

        $markers = [
            'FLASHMESSAGES' => '',
            'data' => [],
        ];

        if ($this->cmd == DmailCmdEnum::Delete->value) {
            $this->deleteDMail($this->uid);
        }

        $row = [];
        if ((int)$this->sys_dmail_uid) {
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
                case DmailCmdEnum::Info->value:
                    $this->cmd = '';
                    break;
                case DmailCmdEnum::Categories->value:
                    $this->cmd = DmailCmdEnum::Info->value;
                    break;
                case DmailCmdEnum::SendTest->value:
                case DmailCmdEnum::SendMailTest->value:
                    if (($this->cmd == DmailCmdEnum::SendMass->value) && $hideCategoryStep) {
                        $this->cmd = DmailCmdEnum::Info->value;
                    } else {
                        $this->cmd = DmailCmdEnum::Categories->value;
                    }
                    break;
                case DmailCmdEnum::SendMailFinal->value:
                case DmailCmdEnum::SendMass->value:
                    $this->cmd = DmailCmdEnum::SendTest->value;
                    break;
                default:
                    // Do nothing
            }
        }

        $nextCmd = '';
        if ($hideCategoryStep) {
            $totalSteps = 4;
            if ($this->cmd == DmailCmdEnum::Info->value) {
                $nextCmd = DmailCmdEnum::SendTest->value;
            }
        } else {
            $totalSteps = 5;
            if ($this->cmd == DmailCmdEnum::Info->value) {
                $nextCmd = DmailCmdEnum::Categories->value;
            }
        }

        $data = [
            'navigation' => [
                'back' => false,
                'next' => false,
                'next_error' => false,
                'totalSteps' => $totalSteps,
                'currentStep' => 1,
                'steps' => array_fill(1, $totalSteps, ''),
            ],
        ];

        switch ($this->cmd) {
            case DmailCmdEnum::Info->value:
                // step 2: create the Direct Mail record, or use existing
                $this->currentStep = 2;
                $data['navigation']['currentStep'] = $this->currentStep;
                $data['info'] = [
                    'currentStep' => $this->currentStep,
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
                            $fetchError = ((strstr($fetchMessage, $this->languageService->sL($this->lllFile . ':dmail_error')) === false) ? false : true);
                        }

                        $data['info']['internal']['cmd'] = $nextCmd ? $nextCmd : DmailCmdEnum::Categories->value;
                    }
                // TODO: Error message - Error while adding the DB set
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
                            $fetchError = ((strstr($fetchMessage, $this->languageService->sL($this->lllFile . ':dmail_error')) === false) ? false : true);
                        }

                        $data['info']['external']['cmd'] = DmailCmdEnum::SendTest->value;
                    } else {
                        // TODO: Error message - Error while adding the DB set
                        $this->error = 'no_valid_url';
                    }
                }
                // Quickmail
                elseif ($quickmail['send']) {
                    $temp = $this->createDMailQuick($quickmail);
                    if (!$temp['errorTitle']) {
                        $fetchError = false;
                    }
                    if ($temp['errorTitle']) {
                        $this->flashMessageQueue->addMessage($this->createFlashMessage($temp['errorText'], $temp['errorTitle'], ContextualFeedbackSeverity::ERROR, false));
                    }
                    if ($temp['warningTitle']) {
                        $this->flashMessageQueue->addMessage($this->createFlashMessage($temp['warningText'], $temp['warningTitle'], ContextualFeedbackSeverity::WARNING, false));
                    }

                    $row = BackendUtility::getRecord('sys_dmail', $this->sys_dmail_uid);

                    $data['info']['quickmail']['cmd'] = DmailCmdEnum::SendTest->value;
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

                        $data['info']['dmail']['cmd'] = DmailCmdEnum::SendTest->value;

                        // add attachment here, since attachment added in 2nd step
                        $unserializedMailContent = unserialize(base64_decode($row['mailContent'] ?: ''));
                        $temp = $this->compileQuickMail($row, $unserializedMailContent['plain']['content'] ?? '', false);
                        if ($temp['errorTitle']) {
                            $this->flashMessageQueue->addMessage($this->createFlashMessage($temp['errorText'], $temp['errorTitle'], ContextualFeedbackSeverity::ERROR, false));
                        }
                        if ($temp['warningTitle']) {
                            $this->flashMessageQueue->addMessage($this->createFlashMessage($temp['warningText'], $temp['warningTitle'], ContextualFeedbackSeverity::WARNING, false));
                        }
                    } else {
                        if ($this->fetchAtOnce) {
                            $fetchMessage = DirectMailUtility::fetchUrlContentsForDirectMailRecord($row, $this->params);
                            $fetchError = ((strstr($fetchMessage, $this->languageService->sL($this->lllFile . ':dmail_error')) === false) ? false : true);
                        }

                        $data['info']['dmail']['cmd'] = ($row['type'] == 0) ? $nextCmd : DmailCmdEnum::SendTest->value;
                    }
                }

                $data['navigation']['back'] = true;
                $data['navigation']['next'] = true;
                $data['navigation']['next_error'] = $fetchError;

                if ($fetchMessage) {
                    $markers['FLASHMESSAGES'] = $fetchMessage;
                } elseif (!$fetchError && $this->fetchAtOnce) {
                    $message = $this->createFlashMessage(
                        '',
                        $this->languageService->sL($this->lllFile . ':dmail_wiz2_fetch_success'),
                        ContextualFeedbackSeverity::OK,
                        false
                    );
                    $this->flashMessageQueue->addMessage($message);
                }
                $data['info']['table'] = is_array($row) ? $this->renderRecordDetailsTable($row) : '';
                $data['info']['sys_dmail_uid'] = $this->sys_dmail_uid;
                $data['info']['pages_uid'] = $row['page'] ?: '';
                $data['info']['currentCMD'] = $this->cmd;
                break;

            case DmailCmdEnum::Categories->value:
                // shows category if content-based cat
                $this->currentStep = 3;
                $data['navigation']['currentStep'] = $this->currentStep;
                $data['cats'] = [
                    'currentStep' => $this->currentStep,
                ];

                $data['navigation']['back'] = true;
                $data['navigation']['next'] = true;

                $temp = $this->makeCategoriesForm($row, $this->indata);
                $data['cats']['output'] = $temp['output'];
                $data['cats']['catsForm'] = $temp['theOutput'];

                $data['cats']['cmd'] = DmailCmdEnum::SendTest->value;
                $data['cats']['sys_dmail_uid'] = $this->sys_dmail_uid;
                $data['cats']['pages_uid'] = $this->pages_uid;
                $data['cats']['currentCMD'] = $this->cmd;
                break;

            case DmailCmdEnum::SendTest->value:
            case DmailCmdEnum::SendMailTest->value:
                // send test mail
                $this->currentStep = (4 - (5 - $totalSteps));
                $data['navigation']['currentStep'] = $this->currentStep;
                $data['test'] = [
                    'currentStep' => $this->currentStep,
                ];

                $data['navigation']['back'] = true;
                $data['navigation']['next'] = true;

                if ($this->cmd == DmailCmdEnum::SendMailTest->value) {
                    $this->sendMail($row);
                }
                $data['test']['testFormData'] = $this->getTestMailConfig();
                $data['test']['cmd'] = DmailCmdEnum::SendMass->value;
                $data['test']['sys_dmail_uid'] = $this->sys_dmail_uid;
                $data['test']['pages_uid'] = $this->pages_uid;
                $data['test']['currentCMD'] = $this->cmd;
                break;

            case DmailCmdEnum::SendMailFinal->value:
            case DmailCmdEnum::SendMass->value:
                $this->currentStep = (5 - (5 - $totalSteps));
                $data['navigation']['currentStep'] = $this->currentStep;
                $data['final'] = [
                    'currentStep' => $this->currentStep,
                ];

                if ($this->cmd == DmailCmdEnum::SendMass->value) {
                    $data['navigation']['back'] = true;
                }

                if ($this->cmd == DmailCmdEnum::SendMailFinal->value) {
                    if (is_array($this->mailgroup_uid) && count($this->mailgroup_uid)) {
                        $this->sendMail($row);
                        break;
                    }

                    $message = $this->createFlashMessage(
                        $this->languageService->sL($this->lllFile . ':mod.no_recipients'),
                        '',
                        ContextualFeedbackSeverity::WARNING,
                        false
                    );
                    $this->flashMessageQueue->addMessage($message);
                }
                // send mass, show calendar
                $data['final']['finalForm'] = $this->cmd_finalmail($row);
                $data['final']['cmd'] = DmailCmdEnum::SendMailFinal->value;
                $data['final']['sys_dmail_uid'] = $this->sys_dmail_uid;
                $data['final']['pages_uid'] = $this->pages_uid;
                $data['final']['currentCMD'] = $this->cmd;
                break;

            default:
                // choose source newsletter
                $this->currentStep = 1;

                $showTabs = ['int', 'ext', 'quick', 'dmail'];
                if (isset($tsconfig['tx_directmail.']['hideTabs'])) {
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
     * Makes box for internal page. (first step)
     *
     * @return array config for form list of internal pages
     */
    protected function getConfigFormInternal(): array
    {
        return [
            'title' => 'dmail_dovsk_crFromNL',
            'news' => $this->getNews(),
        ];
    }

    /**
     * The icon for the source tab
     *
     * @todo: unused function
     *
     * @param bool $expand State of the tab
     *
     * @return string
     */
    protected function getNewsletterTabIcon(bool $expand = false)
    {
        // opened - closes
        $icon = $expand ? 'apps-pagetree-expand' : 'apps-pagetree-collapse';
        return $this->iconFactory->getIcon($icon, Icon::SIZE_SMALL);
    }

    /**
     * Show the list of existing directmail records, which haven't been sent
     */
    protected function getNews(): array
    {
        $rows = GeneralUtility::makeInstance(PagesRepository::class)->selectPagesForDmail($this->id, $this->perms_clause);
        $data = [];
        $empty = $rows === [];
        if (!$empty) {
            $iconActionsOpen = $this->getIconActionsOpen();
            foreach ($rows as $row) {
                $languages = $this->getAvailablePageLanguages($row['uid']);
                $createDmailLink = $this->buildUriFromRoute(
                    $this->moduleName,
                    [
                        'id' => $this->id,
                        'createMailFrom_UID' => $row['uid'],
                        'fetchAtOnce' => 1,
                        'cmd' => DmailCmdEnum::Info->value,
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
                        'title' => htmlentities($this->languageService->sL($this->lllFile . ':nl_viewPage_HTML') . $langTitle),
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
                            'title' => htmlentities($this->languageService->sL($this->lllFile . ':nl_viewPage_TXT') . $langTitle),
                        ], true);

                    $previewTextLink .= '<a href="#" ' . $serializedAttributes . '>' . $plainIcon . '</a>';
                    $createLink .= '<a href="' . $createDmailLink . $createLangParam . '" title="' . htmlentities($this->languageService->sL($this->lllFile . ':nl_create') . $langTitle) . '">' . $createIcon . '</a>';
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
                        ],
                    ],
                    'returnUrl' => $this->requestUri,
                ];

                $data[] = [
                    'id' => $row['uid'],
                    'pageIcon' => $this->iconFactory->getIconForRecord('pages', $row, Icon::SIZE_SMALL),
                    'title' => htmlspecialchars($row['title']),
                    'createDmailLink' => $createDmailLink,
                    'createLink' => $createLink,
                    'editOnClickLink' => $this->getEditOnClickLink($params),
                    'iconActionsOpen' => $iconActionsOpen,
                    'previewLink' => $previewLink,
                ];
            }
        }

        return ['empty' => $empty, 'rows' => $data];
    }

    /**
     * Get available languages for a page
     */
    protected function getAvailablePageLanguages($pageUid): array
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
    protected function getConfigFormExternal(): array
    {
        return [
            'title' => 'dmail_dovsk_crFromUrl',
            'no_valid_url' => (bool)($this->error == 'no_valid_url'),
        ];
    }

    /**
     * Makes config for form for the quickmail (first step)
     *
     * @return array config for form for the quickmail
     */
    protected function getConfigFormQuickMail(): array
    {
        return [
            'id' => $this->id,
            'senderName' => htmlspecialchars($this->quickmail['senderName'] ?? $this->getBackendUser()->user['realName']),
            'senderMail' => htmlspecialchars($this->quickmail['senderEmail'] ?? $this->getBackendUser()->user['email']),
            'subject' => htmlspecialchars($this->quickmail['subject'] ?? ''),
            'message' => htmlspecialchars($this->quickmail['message'] ?? ''),
            'breakLines' => (bool)($this->quickmail['breakLines'] ?? false),
        ];
    }

    /**
     * List all direct mail, which have not been sent (first step)
     *
     * @return array config for form lists of all existing dmail records
     */
    protected function getConfigFormDMail(): array
    {
        $sOrder = preg_replace(
            '/^(?:ORDER[[:space:]]*BY[[:space:]]*)+/i',
            '',
            trim($GLOBALS['TCA']['sys_dmail']['ctrl']['default_sortby'])
        );
        if (!empty($sOrder)) {
            if (substr_count($sOrder, 'ASC') > 0) {
                $sOrder = trim(str_replace('ASC', '', $sOrder));
                $ascDesc = 'ASC';
            } else {
                $sOrder = trim(str_replace('DESC', '', $sOrder));
                $ascDesc = 'DESC';
            }
        }
        $rows = GeneralUtility::makeInstance(SysDmailRepository::class)->selectForMkeListDMail($this->id, $sOrder, $ascDesc);

        $data = [];
        foreach ($rows as $row) {
            $data[] = [
                'id' => $row['uid'],
                'icon' => $this->iconFactory->getIconForRecord('sys_dmail', $row, Icon::SIZE_SMALL)->render(),
                'link' => $this->linkDMailRecord($row['uid']),
                'linkText' => htmlspecialchars($row['subject'] ?: '_'),
                'tstamp' => BackendUtility::date($row['tstamp']),
                'issent' => ($row['issent'] ? $this->languageService->sL($this->lllFile . ':dmail_yes') : $this->languageService->sL($this->lllFile . ':dmail_no')),
                'renderedsize' => ($row['renderedsize'] ? GeneralUtility::formatSize($row['renderedsize']) : ''),
                'attachment' => ($row['attachment'] ? $this->iconFactory->getIcon('directmail-attachment', Icon::SIZE_SMALL) : ''),
                'type' => ($row['type'] & 0x1 ? $this->languageService->sL($this->lllFile . ':nl_l_tUrl') : $this->languageService->sL($this->lllFile . ':nl_l_tPage')) . ($row['type']  & 0x2 ? ' (' . $this->languageService->sL($this->lllFile . ':nl_l_tDraft') . ')' : ''),
                'deleteLink' => $this->deleteLink($row['uid']),
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
    protected function createDMailQuick(array $indata): array
    {
        $theOutput = [];
        // Set default values:
        $dmail = [];
        $dmail['sys_dmail']['NEW'] = [
            'from_email'         => $indata['senderEmail'],
            'from_name'          => $indata['senderName'],
            'replyto_email'      => $this->params['replyto_email'] ?? '',
            'replyto_name'       => $this->params['replyto_name'] ?? '',
            'return_path'        => $this->params['return_path'] ?? '',
            'priority'           => $this->params['priority'] ?? 3,
            'use_rdct'           => (!empty($this->params['use_rdct']) ? (int)$this->params['use_rdct'] : 0),
            'long_link_mode'     => (!empty($this->params['long_link_mode']) ? (int)$this->params['long_link_mode'] : 0),
            'organisation'       => $this->params['organisation'] ?? '',
            'authcode_fieldList' => $this->params['authcode_fieldList'] ?? '',
            'plainParams'        => '',
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
            $dataHandler->start($dmail, []);
            $dataHandler->process_datamap();
            $this->sys_dmail_uid = $dataHandler->substNEWwithIDs['NEW'];

            $row = BackendUtility::getRecord('sys_dmail', (int)$this->sys_dmail_uid);
            // link in the mail
            $message = '<!--DMAILER_SECTION_BOUNDARY_-->' . $indata['message'] . '<!--DMAILER_SECTION_BOUNDARY_END-->';
            if (isset($this->params['use_rdct'])) {
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
     * @param int $uid UID of the directmail record
     *
     * @return string the link
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function linkDMailRecord(int $uid)
    {
        return $this->buildUriFromRoute(
            $this->moduleName,
            [
                'id' => $this->id,
                'sys_dmail_uid' => $uid,
                'fetchAtOnce' => 1,
                'cmd' => DmailCmdEnum::Info->value,
            ]
        );
    }

    /**
     * Create delete link with trash icon
     *
     * @param int $uid Uid of the record
     *
     * @return Uri|string link with the trash icon
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    protected function deleteLink(int $uid)
    {
        $dmail = BackendUtility::getRecord('sys_dmail', $uid);

        if (!$dmail['scheduled_begin']) {
            return $this->buildUriFromRoute(
                $this->moduleName,
                [
                    'id' => $this->id,
                    'uid' => $uid,
                    'cmd' => DmailCmdEnum::Delete->value,
                ]
            );
        }

        return '';
    }

    /**
     * Delete existing dmail record
     *
     * @param int $uid record uid to be deleted
     */
    protected function deleteDMail(int $uid): void
    {
        $table = 'sys_dmail';
        if ($GLOBALS['TCA'][$table]['ctrl']['delete']) {
            GeneralUtility::makeInstance(SysDmailRepository::class)->updateSysDmailRecord($uid, [$GLOBALS['TCA'][$table]['ctrl']['delete'] => 1]);
        }
    }

    /**
     * Compiling the quickmail content and save to DB
     *
     * @param array $row The sys_dmail record
     * @param string $messageBody Body of the mail
     * @TODO: remove htmlmail, compiling mail
     */
    protected function compileQuickMail(array $row, string $messageBody): array
    {
        $erg = ['errorTitle' => '', 'errorText' => '', 'warningTitle' => '', 'warningText' => ''];

        // Compile the mail
        /* @var $htmlmail Dmailer */
        $htmlmail = GeneralUtility::makeInstance(Dmailer::class);
        $htmlmail->setNonCron(true);
        $htmlmail->start();
        $htmlmail->setCharset($row['charset']);
        $htmlmail->addPlain($messageBody);

        if (!$messageBody || !$htmlmail->getPartPlainConfig('content')) {
            $erg['errorTitle'] = $this->languageService->sL($this->lllFile . ':dmail_error');
            $erg['errorText'] = $this->languageService->sL($this->lllFile . ':dmail_no_plain_content');
        } elseif (!strstr(base64_decode($htmlmail->getPartPlainConfig('content')), '<!--DMAILER_SECTION_BOUNDARY')) {
            $erg['warningTitle'] = $this->languageService->sL($this->lllFile . ':dmail_warning');
            $erg['warningText'] = $this->languageService->sL($this->lllFile . ':dmail_no_plain_boundaries');
        }

        // add attachment is removed. since it will be add during sending

        if (!$erg['errorTitle']) {
            // Update the record:
            $htmlmail->setPartMessageIdConfig($htmlmail->getMessageid());
            $mailContent = base64_encode(serialize($htmlmail->getParts()));

            GeneralUtility::makeInstance(SysDmailRepository::class)->updateSysDmail(
                $this->sys_dmail_uid,
                $htmlmail->getCharset(),
                $mailContent
            );
        }

        return $erg;
    }

    /**
     * Shows the infos of a directmail record in a table
     *
     * @param array $row DirectMail DB record
     */
    protected function renderRecordDetailsTable(array $row): array
    {
        $label = '';
        $edit = false;
        $editParams = '';
        if (isset($row['issent']) && !$row['issent']) {
            if ($this->getBackendUser()->check('tables_modify', 'sys_dmail')) {
                $edit = true;
                $requestUri = $this->buildUriFromRoute(
                    $this->moduleName,
                    [
                        'id' => $this->id,
                        'sys_dmail_uid' => $row['uid'],
                        'fetchAtOnce' => 1,
                        'cmd' => DmailCmdEnum::Info->value,
                    ]
                );

                $editParams = $this->getEditOnClickLink([
                    'edit' => [
                        'sys_dmail' => [
                            $row['uid'] => 'edit',
                        ],
                    ],
                    'returnUrl' => $requestUri->__toString(),
                ]);
            } else {
                $label = $this->languageService->sL($this->lllFile . ':dmail_noEdit_noPerms');
            }
        } else {
            $label = $this->languageService->sL($this->lllFile . ':dmail_noEdit_isSent');
        }

        $trs = [];
        $nameArr = ['from_name', 'from_email', 'replyto_name', 'replyto_email', 'organisation', 'return_path', 'priority', 'type', 'page',
            'sendOptions', 'includeMedia', 'flowedFormat', 'sys_language_uid', 'plainParams', 'HTMLParams', 'encoding', 'charset', 'issent', 'renderedsize'];
        foreach ($nameArr as $name) {
            $trs[] = [
                'title' => DirectMailUtility::fName($name),
                'value' => htmlspecialchars((string)BackendUtility::getProcessedValue('sys_dmail', $name, ($row[$name] ?? false))),
            ];
        }

        // attachments need to be fetched manually as BackendUtility::getProcessedValue can't do that
        $files = [];
        $attachments = DirectMailUtility::getAttachments((int)($row['uid'] ?? 0));

        foreach ($attachments as $attachment) {
            $files[] = [
                'name' => $attachment->getName(),
                'url' => $attachment->getPublicUrl()
            ];
        }

        $trs[] = [
            'title' => DirectMailUtility::fName('attachment'),
            'files' => $files,
        ];

        return [
            'icon' => $this->iconFactory->getIconForRecord('sys_dmail', $row, Icon::SIZE_SMALL),
            'title' => htmlspecialchars($row['subject'] ?? ''),
            'theadTitle1' => DirectMailUtility::fName('subject'),
            'theadTitle2' => GeneralUtility::fixed_lgd_cs(htmlspecialchars($row['subject'] ?? ''), 60),
            'trs' => $trs,
            'label' => $label,
            'edit' => $edit,
            'editParams' => $editParams,
        ];
    }

    /**
     * Show the step of sending a test mail
     *
     * @return array config for form
     */
    protected function getTestMailConfig(): array
    {
        $data = [
            'test_tt_address'  => '',
            'test_dmail_group_table' => [],
        ];

        if ($this->params['test_tt_address_uids'] ?? false) {
            // https://api.typo3.org/12.4/class_t_y_p_o3_1_1_c_m_s_1_1_core_1_1_utility_1_1_general_utility.html#a87225a3db04071355a62a36ed8636add
            $intList = GeneralUtility::intExplode(',', $this->params['test_tt_address_uids'], true);
            $rows = GeneralUtility::makeInstance(TtAddressRepository::class)->selectTtAddressForTestmail($intList, $this->perms_clause);
            $ids = [];
            foreach ($rows as $row) {
                $ids[] = $row['uid'];
            }
            $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($ids, 'tt_address');
            $data['test_tt_address'] = $this->getRecordList($rows, 'tt_address', 1, 1);
        }

        if ($this->params['test_dmail_group_uids'] ?? false) {
            $intList = GeneralUtility::intExplode(',', $this->params['test_dmail_group_uids'], true);
            $rows = GeneralUtility::makeInstance(SysDmailGroupRepository::class)->selectSysDmailGroupForTestmail($intList, $this->perms_clause);

            foreach ($rows as $row) {
                $moduleUrl = $this->buildUriFromRoute(
                    $this->moduleName,
                    [
                        'id' => $this->id,
                        'sys_dmail_uid' => $this->sys_dmail_uid,
                        'cmd' => DmailCmdEnum::SendMailTest->value,
                        'sys_dmail_group_uid[]' => $row['uid'],
                    ]
                );

                // Members:
                $result = $this->cmd_compileMailGroup([$row['uid']]);

                $data['test_dmail_group_table'][] = [
                    'moduleUrl' => $moduleUrl,
                    'iconFactory' => $this->iconFactory->getIconForRecord('sys_dmail_group', $row, Icon::SIZE_SMALL),
                    'title' => htmlspecialchars($row['title']),
                    'uid' => $row['uid'],
                    'tds' => $this->displayMailGroupTest($result),
                ];
            }
        }

        $data['dmail_test_email'] = $this->MOD_SETTINGS['dmail_test_email'] ?? '';
        $data['id'] = $this->id;
        $data['cmd'] = DmailCmdEnum::SendMailTest->value;
        $data['sys_dmail_uid'] = $this->sys_dmail_uid;

        return $data;
    }

    /**
     * Display the test mail group, which configured in the configuration module
     *
     * @param array $result Lists of the recipient IDs based on directmail DB record
     *
     * @return array List of the recipient
     */
    public function displayMailGroupTest(array $result): array
    {
        $idLists = $result['queryInfo']['id_lists'];
        $out = [];
        if (is_array($idLists['tt_address'] ?? false)) {
            $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists['tt_address'], 'tt_address');
            $out[] = $this->getRecordList($rows, 'tt_address');
        }
        if (is_array($idLists['fe_users'] ?? false)) {
            $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists['fe_users'], 'fe_users');
            $out[] = $this->getRecordList($rows, 'fe_users');
        }
        if (is_array($idLists['PLAINLIST'] ?? false)) {
            $out[] = $this->getRecordList($idLists['PLAINLIST'], 'default');
        }
        if (is_array($idLists[$this->userTable] ?? false)) {
            $rows = GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists[$this->userTable], $this->userTable);
            $out[] = $this->getRecordList($rows, $this->userTable);
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
        $htmlmail->setNonCron(true);
        $htmlmail->start();
        $htmlmail->prepare($row);
        $sentFlag = false;

        // send out non-personalized emails
        if ($this->mailingMode_simple) {
            // step 4, sending simple test emails
            // setting Testmail flag
            $htmlmail->setTestmail((bool)($this->params['testmail'] ?? false));

            // Fixing addresses:
            $addresses = $this->addresses;
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

            if ($addresses !== []) {
                // Sending the same mail to lots of recipients
                $htmlmail->sendSimple($addresses);
                $sentFlag = true;
                $message = $this->createFlashMessage(
                    $this->languageService->sL($this->lllFile . ':send_was_sent') . ' ' .
                    $this->languageService->sL($this->lllFile . ':send_recipients') . ' ' . htmlspecialchars(implode(',', $addresses)),
                    $this->languageService->sL($this->lllFile . ':send_sending'),
                    ContextualFeedbackSeverity::OK,
                    false
                );
                $this->flashMessageQueue->addMessage($message);
            }
        } elseif ($this->cmd == DmailCmdEnum::SendMailTest->value) {
            // step 4, sending test personalized test emails
            // setting Testmail flag
            $htmlmail->setTestmail((bool)$this->params['testmail']);

            if ($this->tt_address_uid) {
                // personalized to tt_address
                $res = GeneralUtility::makeInstance(TtAddressRepository::class)->selectTtAddressForSendMailTest($this->tt_address_uid, $this->perms_clause);

                if (!empty($res)) {
                    foreach ($res as $recipRow) {
                        $recipRow = $htmlmail->convertFields($recipRow);
                        $recipRow['sys_dmail_categories_list'] = $htmlmail->getListOfRecipentCategories('tt_address', $recipRow['uid']);
                        $htmlmail->sendAdvanced($recipRow, 't');
                        $sentFlag = true;

                        $message = $this->createFlashMessage(
                            sprintf($this->languageService->sL($this->lllFile . ':send_was_sent_to_name'), $recipRow['name'] . ' <' . $recipRow['email'] . '>'),
                            $this->languageService->sL($this->lllFile . ':send_sending'),
                            ContextualFeedbackSeverity::OK,
                            false
                        );
                        $this->flashMessageQueue->addMessage($message);
                    }
                } else {
                    $message = $this->createFlashMessage(
                        'Error: No valid recipient found to send test mail to. #1579209279',
                        $this->languageService->sL($this->lllFile . ':send_sending'),
                        ContextualFeedbackSeverity::ERROR,
                        false
                    );
                    $this->flashMessageQueue->addMessage($message);
                }
            } elseif (is_array($this->sysDmailGroupUid)) {
                // personalized to group
                $result = $this->cmd_compileMailGroup($this->sysDmailGroupUid);

                $idLists = $result['queryInfo']['id_lists'];
                $sendFlag = 0;
                $sendFlag += $this->sendTestMailToTable($idLists, 'tt_address', $htmlmail);
                $sendFlag += $this->sendTestMailToTable($idLists, 'fe_users', $htmlmail);
                $sendFlag += $this->sendTestMailToTable($idLists, 'PLAINLIST', $htmlmail);
                $sendFlag += $this->sendTestMailToTable($idLists, (string)$this->userTable, $htmlmail);
                $message = $this->createFlashMessage(
                    sprintf($this->languageService->sL($this->lllFile . ':send_was_sent_to_number'), $sendFlag),
                    $this->languageService->sL($this->lllFile . ':send_sending'),
                    ContextualFeedbackSeverity::OK,
                    false
                );
                $this->flashMessageQueue->addMessage($message);
            }
        } else {
            // step 5, sending personalized emails to the mailqueue
            // prepare the email for sending with the mailqueue
            $recipientGroups = $this->mailgroupUid;
            if ($this->mailingModeMailGroup && $this->sys_dmail_uid && is_array($recipientGroups)) {
                // Update the record:
                $result = $this->cmd_compileMailGroup($recipientGroups);
                $queryInfo = $result['queryInfo'];

                $distributionTime = strtotime($this->sendMailDatetimeHr);
                if ($distributionTime < time()) {
                    $distributionTime = time();
                }

                $updateFields = [
                    'recipientGroups' => implode(',', $recipientGroups),
                    'scheduled'  => $distributionTime,
                    'query_info' => serialize($queryInfo),
                ];

                if ($this->testmail) {
                    $updateFields['subject'] = ($this->params['testmail'] ?? '') . ' ' . $row['subject'];
                }

                // create a draft version of the record
                if ($this->savedraft) {
                    $updateFields['type'] = $row['type'] == 0 ? 2 : 3;
                    $updateFields['scheduled'] = 0;
                    $content = $this->languageService->sL($this->lllFile . ':send_draft_scheduler');
                    $sectionTitle = $this->languageService->sL($this->lllFile . ':send_draft_saved');
                } else {
                    $content = $this->languageService->sL($this->lllFile . ':send_was_scheduled_for') . ' ' . BackendUtility::datetime($distributionTime);
                    $sectionTitle = $this->languageService->sL($this->lllFile . ':send_was_scheduled');
                }
                $sentFlag = true;
                $done = GeneralUtility::makeInstance(SysDmailRepository::class)->updateSysDmailRecord(
                    (int)$this->sys_dmail_uid,
                    $updateFields
                );

                $message = $this->createFlashMessage(
                    $sectionTitle . ' ' . $content,
                    $this->languageService->sL($this->lllFile . ':dmail_wiz5_sendmass'),
                    ContextualFeedbackSeverity::OK,
                    false
                );
                $this->flashMessageQueue->addMessage($message);
            }
        }

        // Setting flags and update the record:
        if ($sentFlag && $this->cmd == DmailCmdEnum::SendMailFinal->value) {
            $done = GeneralUtility::makeInstance(SysDmailRepository::class)->updateSysDmailRecord(
                (int)$this->sys_dmail_uid,
                ['issent' => 1]
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
    protected function sendTestMailToTable(array $idLists, string $table, Dmailer $htmlmail): int
    {
        $sentFlag = 0;
        if (isset($idLists[$table]) && is_array($idLists[$table])) {
            $rows = ($table != 'PLAINLIST') ? GeneralUtility::makeInstance(TempRepository::class)->fetchRecordsListValues($idLists[$table], $table, ['*']) : $idLists['PLAINLIST'];
            foreach ($rows as $row) {
                $recipRow = $htmlmail->convertFields($row);
                $recipRow['sys_dmail_categories_list'] = $htmlmail->getListOfRecipentCategories($table, $recipRow['uid']);
                $kc = substr($table, 0, 1);
                $kc = $kc == 'p' ? 'P' : $kc;
                $returnCode = $htmlmail->sendAdvanced($recipRow, $kc);
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
     * @return array the table showing the recipient's info
     * @throws RouteNotFoundException If the named route doesn't exist
     */
    public function getRecordList(
        array $listArr,
        string $table,
        $editLinkFlag = 1,
        $testMailLink = 0): array
    {
        $count = 0;
        $trs = [];
        $out = [];
        if (is_array($listArr)) {
            $count = count($listArr);
            /** @var UriBuilder $uriBuilder */
            $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
            foreach ($listArr as $row) {
                $tableIcon = '';
                $moduleUrl = '';
                $editOnClick = '';
                if ($row['uid']) {
                    $tableIcon = $this->iconFactory->getIconForRecord($table, $row, Icon::SIZE_SMALL);
                    if ($editLinkFlag) {
                        $params = [
                            'edit' => [
                                $table => [
                                    $row['uid'] => 'edit',
                                ],
                            ],
                            'returnUrl' => $this->requestUri . '&cmd=' . DmailCmdEnum::SendTest->value . '&sys_dmail_uid=' . $this->sys_dmail_uid . '&pages_uid=' . $this->pages_uid,
                        ];

                        $editOnClick = $this->getEditOnClickLink($params);
                    }

                    if ($testMailLink) {
                        $moduleUrl = $uriBuilder->buildUriFromRoute(
                            $this->moduleName,
                            [
                                'id' => $this->id,
                                'sys_dmail_uid' => $this->sys_dmail_uid,
                                'cmd' => DmailCmdEnum::SendMailTest->value,
                                'tt_address_uid' => $row['uid'],
                            ]
                        );
                    }
                }

                $trs[] = [
                    'icon' => $tableIcon,
                    'editOnClick' => $editOnClick,
                    'testLink' => $moduleUrl,
                    'uid' => $row['uid'],
                    'name' => $row['name'],
                    'email' => $row['email'],
                ];
            }
        }
        if (count($trs)) {
            $out['count'] = $count;
            $out['trs'] = $trs;
        }

        return $out;
    }

    /**
     * Shows the final steps of the process. Show recipient list and calendar library
     *
     * @param array $direct_mail_row
     * @return	array		HTML
     */
    protected function cmd_finalmail(array $direct_mail_row): array
    {
        /**
         * Hook for cmd_finalmail
         * insert a link to open extended importer
         */
        $hookSelectDisabled = false;
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
        if ($groups) {
            foreach ($groups as $group) {
                $result = $this->cmd_compileMailGroup([$group['uid']]);
                $opt[] = [
                    'uid' => $group['uid'],
                    'title' => $group['title'],
                    'count' => $this->countRecipients($result['queryInfo']['id_lists']),
                ];
                $lastGroup = $group;
            }
        }

        // added disabled. see hook
        if (count($opt) === 0) {
            $message = $this->createFlashMessage(
                $this->languageService->sL($this->lllFile . ':error.no_recipient_groups_found'),
                '',
                ContextualFeedbackSeverity::ERROR,
                false
            );
            $this->flashMessageQueue->addMessage($message);
        }
        $sendMailDatetime = date('H:i d-m-Y', time());
        return [
            'id' => $this->id,
            'sys_dmail_uid' => $this->sys_dmail_uid,
            'hookContents' => $hookContents, // put content from hook
            'hookSelectDisabled' => $hookSelectDisabled, // put content from hook
            'lastGroup' => $lastGroup,
            'opt' => $opt,
            'send_mail_datetime_hr' => $sendMailDatetime,
            'send_mail_datetime' =>    $sendMailDatetime,
        ];
    }

    /**
     * Get the recipient IDs given a list of group IDs
     *
     * @param array $groups List of selected group IDs
     *
     * @return array list of the recipient ID
     */
    public function cmd_compileMailGroup(array $groups): array
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
            $idLists['PLAINLIST'] = $this->cleanPlainList($idLists['PLAINLIST']);
        }

        /** @var DmailCompileMailGroupEvent $event */
        $event = $this->eventDispatcher->dispatch(
            new DmailCompileMailGroupEvent($idLists, $groups)
        );
        $idLists = $event->getIdLists();

        return [
            'queryInfo' => ['id_lists' => $idLists],
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
    protected function getSingleMailGroup(int $groupUid): array
    {
        $idLists = [];
        if ($groupUid) {
            $mailGroup = BackendUtility::getRecord('sys_dmail_group', $groupUid);

            if (is_array($mailGroup)) {
                switch ($mailGroup['type']) {
                    case 0:
                        // From pages
                        // use current page if no else
                        $thePages = (string)($mailGroup['pages'] ?? $this->id);
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
                            if ($whichTables&1) {
                                // tt_address
                                $idLists['tt_address'] = GeneralUtility::makeInstance(TtAddressRepository::class)->getIdList($pageIdArray, $groupUid, $mailGroup['select_categories']);
                            }
                            if ($whichTables&2) {
                                // fe_users
                                $idLists['fe_users'] = GeneralUtility::makeInstance(FeUsersRepository::class)->getIdList($pageIdArray, $groupUid, $mailGroup['select_categories']);
                            }
                            if ($this->userTable && ($whichTables&4)) {
                                // user table
                                $idLists[$this->userTable] = GeneralUtility::makeInstance(TempRepository::class)->getIdList($this->userTable, $pageIdArray, $groupUid, $mailGroup['select_categories']);
                            }
                            if ($whichTables&8) {
                                // fe_groups
                                if (!is_array($idLists['fe_users'])) {
                                    $idLists['fe_users'] = [];
                                }
                                $idLists['fe_users'] = GeneralUtility::makeInstance(FeGroupsRepository::class)->getIdList($pageIdArray, $groupUid, $mailGroup['select_categories']);
                                $idLists['fe_users'] = array_unique(array_merge($idLists['fe_users']));
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
                            $queryGenerator = GeneralUtility::makeInstance(DmQueryGenerator::class, $this->iconFactory, GeneralUtility::makeInstance(UriBuilder::class), $this->moduleTemplateFactory);
                            $idLists[$table] = GeneralUtility::makeInstance(TempRepository::class)->getSpecialQueryIdList($queryGenerator, $table, $mailGroup);
                        }
                        break;
                    case 4:
                        $groups = array_unique(GeneralUtility::makeInstance(SysDmailGroupRepository::class)->getMailGroups($mailGroup['mail_groups'] ?? '', [$mailGroup['uid']], $this->perms_clause));
                        foreach ($groups as $group) {
                            $group = MathUtility::convertToPositiveInteger($group);
                            if (!$group) {
                                continue;
                            }
                            $collect = $this->getSingleMailGroup($group);
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
    public function updateSpecialQuery(array $mailGroup): array
    {
        $set = $this->set;
        $queryTable = $set['queryTable'] ?? '';
        $queryLimit = $set['queryLimit'] ?? $mailGroup['queryLimit'] ?? 100;
        $queryLimitDisabled = ($set['queryLimitDisabled'] ?? $mailGroup['queryLimitDisabled']) == '' ? 0 : 1;
        $queryConfig = $this->queryConfig;
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
     * Show the categories table for user to categorize the directmail content
     * TYPO3 content)
     *
     * @param array $row The dmail row.
     * @param $indata
     *
     * @return array HTML form showing the categories
     */
    public function makeCategoriesForm(array $row, $indata): array
    {
        $output = [
            'title' => $this->languageService->sL($this->lllFile . ':nl_cat'),
            'rowsFound' => false,
            'rows' => [],
            'pages_uid' => $this->pages_uid,
            'cmd' => $this->cmd,
            'update_cats' => $this->languageService->sL($this->lllFile . ':nl_l_update'),
            'output' => '',
        ];
        $theOutput = '';

        if (is_array($indata['categories'] ?? null)) {
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
        if (!empty($rows)) {
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
                        'title' => $this->languageService->sL($this->lllFile . ':nl_l_column'),
                        'value' => BackendUtility::getProcessedValue('tt_content', 'colPos', $row['colPos']),
                    ];
                    $colPosVal = $row['colPos'];
                }

                $categories = GeneralUtility::makeInstance(TempRepository::class)->makeCategories('tt_content', $row, $this->sys_language_uid);
                $cboxes = [];
                foreach ($categories as $pKey => $pVal) {
                    $cboxes[] = [
                        'pKey' => $pKey,
                        'checked' => GeneralUtility::inList($categoriesRow, $pKey) ? true : false,
                        'pVal' => htmlspecialchars($pVal),
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
                    'labelOnlyAll' => $row['module_sys_dmail_category'] ? $this->languageService->sL($this->lllFile . ':nl_l_ONLY') : $this->languageService->sL($this->lllFile . ':nl_l_ALL'),
                    'checkboxes' => $cboxes,
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
    public function getLanguageParam($sysLanguageUid, array $params): string
    {
        if (isset($params['langParams.'][$sysLanguageUid])) {
            $param = $params['langParams.'][$sysLanguageUid];

        // fallback: L == sys_language_uid
        } else {
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
            'priority'              => $parameters['priority'] ?? 3,
            'use_rdct'              => (!empty($parameters['use_rdct']) ? $parameters['use_rdct'] : 0), /*$parameters['use_rdct'],*/
            'long_link_mode'        => (!empty($parameters['long_link_mode']) ? $parameters['long_link_mode'] : 0), //$parameters['long_link_mode'],
            'organisation'          => $parameters['organisation'] ?? '',
            'authcode_fieldList'    => $parameters['authcode_fieldList'] ?? '',
            'sendOptions'           => $GLOBALS['TCA']['sys_dmail']['columns']['sendOptions']['config']['default'],
            'long_link_rdct_url'    => $this->getUrlBase((int)$pageUid),
            'sys_language_uid'      => (int)$sysLanguageUid,
            'attachment'            => '',
            'mailContent'           => '',
        ];

        if ($newRecord['sys_language_uid'] > 0) {
            $langParam = $this->getLanguageParam($newRecord['sys_language_uid'], $parameters);
            foreach (['plainParams', 'HTMLParams'] as $param) {
                $parameters[$param] = empty($parameters[$param]) ? $langParam : $parameters[$param] . $langParam;
            }
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
            if ($pageRecordOverlay !== false && $pageRecordOverlay !== '') {
                $pageRecord['title'] = $pageRecordOverlay;
            }
        }

        if ($pageRecord['doktype']) {
            $newRecord['subject'] = $pageRecord['title'];
            $newRecord['page']    = $pageRecord['uid'];
            $newRecord['charset'] = $this->getCharacterSet();
        }

        // save to database
        if ($newRecord['page'] && $newRecord['sendOptions']) {
            $tcemainData = [
                'sys_dmail' => [
                    'NEW' => $newRecord,
                ],
            ];

            /* @var $dataHandler \TYPO3\CMS\Core\DataHandling\DataHandler */
            $dataHandler = $this->getDataHandler();
            $dataHandler->start($tcemainData, []);
            $dataHandler->process_datamap();
            $result = $dataHandler->substNEWwithIDs['NEW'];
        } elseif (!$newRecord['sendOptions']) {
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
    public function createDirectMailRecordFromExternalURL(
        string $subject,
        string $externalUrlHtml,
        string $externalUrlPlain,
        array $parameters)
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
            'priority'              => $parameters['priority'] ?? 3,
            'use_rdct'              => (!empty($parameters['use_rdct']) ? $parameters['use_rdct'] : 0),
            'long_link_mode'        => $parameters['long_link_mode'] ?? '',
            'organisation'          => $parameters['organisation'] ?? '',
            'authcode_fieldList'    => $parameters['authcode_fieldList'] ?? '',
            'sendOptions'           => $GLOBALS['TCA']['sys_dmail']['columns']['sendOptions']['config']['default'],
            'long_link_rdct_url'    => $this->getUrlBase((int)($parameters['page'] ?? 0)),
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
        } else {
            $newRecord['plainParams'] = $externalUrlPlain;
        }

        // No html url
        $urlParts = @parse_url($externalUrlHtml);
        if (!$externalUrlHtml || $urlParts === false || !$urlParts['host']) {
            $newRecord['sendOptions'] &= 253;
        } else {
            $newRecord['HTMLParams'] = $externalUrlHtml;
        }

        // save to database
        if ($newRecord['pid'] && $newRecord['sendOptions']) {
            $tcemainData = [
                'sys_dmail' => [
                    'NEW' => $newRecord,
                ],
            ];

            /* @var $dataHandler \TYPO3\CMS\Core\DataHandling\DataHandler */
            $dataHandler = $this->getDataHandler();
            $dataHandler->start($tcemainData, []);
            $dataHandler->process_datamap();
            $result = $dataHandler->substNEWwithIDs['NEW'];
        } elseif (!$newRecord['sendOptions']) {
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

            return ''; // No site found in root line of pageId
        }

        return ''; // No valid pageId
    }

        /**
     * Get the configured charset.
     *
     * This method used to initialize the TSFE object to get the charset on a per page basis. Now it just evaluates the
     * configured charset of the instance
     *
     * @throws ImmediateResponseException
     * @throws ServiceUnavailableException
     */
    protected function getCharacterSet(): string
    {
        /** @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManager $configurationManager */
        $configurationManager = GeneralUtility::makeInstance(ConfigurationManager::class);

        $settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_FULL_TYPOSCRIPT
        );

        $characterSet = 'utf-8';

        if (!empty($settings['config.']['metaCharset'])) {
            $characterSet = $settings['config.']['metaCharset'];
        } elseif (!empty($GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'])) {
            $characterSet = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'];
        }

        return mb_strtolower($characterSet);
    }
}
