<?php

declare(strict_types=1);

namespace DirectMailTeam\DirectMail\Module;

use DirectMailTeam\DirectMail\Repository\PagesRepository;
use DirectMailTeam\DirectMail\Utility\TsUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\Controller;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Tree\View\PageTreeView;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Http\Uri;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageQueue;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class MainController
{
    /**
     * ModuleTemplate Container
     *
     * @var ModuleTemplate
     */
    protected $moduleTemplate;
    #protected IconFactory $iconFactory;
    #protected PageRenderer $pageRenderer;

    /**
     * @var StandaloneView
     */
    protected $view;

    protected int $id = 0;
    protected string $cmd = '';
    protected int $sys_dmail_uid = 0;
    protected string $pages_uid = '';
    protected bool $updatePageTree = false;

    protected $params = [];

    /**
     * A WHERE clause for selection records from the pages table based on read-permissions of the current backend user.
     *
     * @see init()
     * @var string
     */
    protected string $perms_clause = '';

    protected array $implodedParams = [];
    protected $userTable;
    protected array $allowedTables = [];
    protected int $sys_language_uid = 0;
    protected array $pageinfo = [];
    protected bool $access = false;

    protected $messageQueue;

    /**
     * Constructor Method
     *
     * @var ModuleTemplate $moduleTemplate
     */
    public function __construct(
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
        # $moduleTemplate = null,
        protected readonly IconFactory $iconFactory,
        protected readonly PageRenderer $pageRenderer
    ) {
        #$this->moduleTemplate = $moduleTemplate ?? GeneralUtility::makeInstance(ModuleTemplate::class);
        #$this->iconFactory = $iconFactory ?? GeneralUtility::makeInstance(IconFactory::class);
        #$this->pageRenderer = $pageRenderer ?? GeneralUtility::makeInstance(PageRenderer::class);
        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf');
        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');
    }

    protected function init(ServerRequestInterface $request): void
    {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();

        $this->moduleTemplate = $this->moduleTemplateFactory->create($request);

        $this->id             = (int)($parsedBody['id']              ?? $queryParams['id'] ?? 0);
        $this->cmd            = (string)($parsedBody['cmd']          ?? $queryParams['cmd'] ?? '');
        $this->pages_uid      = (string)($parsedBody['pages_uid']    ?? $queryParams['pages_uid'] ?? '');
        $this->sys_dmail_uid  = (int)($parsedBody['sys_dmail_uid']   ?? $queryParams['sys_dmail_uid'] ?? 0);
        $this->updatePageTree = (bool)($parsedBody['updatePageTree'] ?? $queryParams['updatePageTree'] ?? false);

        $this->perms_clause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        $pageAccess = BackendUtility::readPageAccess($this->id, $this->perms_clause);
        $this->pageinfo = is_array($pageAccess) ? $pageAccess : [];
        $this->access = is_array($this->pageinfo) ? true : false;

        // get the config from pageTS
        $this->params = BackendUtility::getPagesTSconfig($this->id)['mod.']['web_modules.']['dmail.'] ?? [];
        $this->implodedParams = GeneralUtility::makeInstance(TsUtility::class)->implodeTSParams($this->params);

        if ($this->params['userTable'] ?? false && isset($GLOBALS['TCA'][$this->params['userTable']]) && is_array($GLOBALS['TCA'][$this->params['userTable']])) {
            $this->userTable = $this->params['userTable'];
            $this->allowedTables[] = $this->userTable;
        }
        // initialize backend user language
        //$this->sys_language_uid = 0; //@TODO

        if ($this->updatePageTree) {
            \TYPO3\CMS\Backend\Utility\BackendUtility::setUpdateSignal('updatePageTree');
        }
    }

    /**
     * Configure template paths for your backend module
     * @return StandaloneView
     */
    protected function configureTemplatePaths(string $templateName): StandaloneView
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplateRootPaths(['EXT:direct_mail/Resources/Private/Templates/']);
        $view->setPartialRootPaths(['EXT:direct_mail/Resources/Private/Partials/']);
        $view->setLayoutRootPaths(['EXT:direct_mail/Resources/Private/Layouts/']);
        $view->setTemplate($templateName);
        return $view;
    }

    /**
        https://api.typo3.org/main/class_t_y_p_o3_1_1_c_m_s_1_1_core_1_1_messaging_1_1_abstract_message.html
        const 	NOTICE = -2
        const 	INFO = -1
        const 	OK = 0
        const 	WARNING = 1
        const 	ERROR = 2
     * @param string $messageText
     * @param string $messageHeader
     * @param ContextualFeedbackSeverity $messageType
     * @param bool $storeInSession
     */
    protected function createFlashMessage(
        string $messageText,
        string $messageHeader = '',
        ContextualFeedbackSeverity $messageType,
        bool $storeInSession = false): FlashMessage
    {
        return GeneralUtility::makeInstance(
            FlashMessage::class,
            $messageText,
            $messageHeader, // [optional] the header
            $messageType, // [optional] the severity defaults to \TYPO3\CMS\Core\Messaging\FlashMessage::OK
            $storeInSession // [optional] whether the message should be stored in the session or only in the \TYPO3\CMS\Core\Messaging\FlashMessageQueue object (default is false)
        );
    }

    protected function getModulName()
    {
        $module = $this->pageinfo['module'] ?? false;

        if (!$module && isset($this->pageinfo['uid'])) {
            $pidrec = BackendUtility::getRecord('pages', (int)$this->pageinfo['uid']);
            $module = $pidrec['module'] ?? false;
        }

        return $module;
    }

    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return GeneralUtility::makeInstance(LanguageServiceFactory::class)->createFromUserPreferences($this->getBackendUser());
    }

    /**
     * @return FlashMessageQueue
     */
    protected function getFlashMessageQueue(string $identifier): FlashMessageQueue
    {
        return GeneralUtility::makeInstance(FlashMessageService::class)->getMessageQueueByIdentifier($identifier);
    }

    /**
     * Returns the Backend User
     * @return BackendUserAuthentication
     */
    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    protected function isAdmin(): bool
    {
        return $GLOBALS['BE_USER']->isAdmin();
    }

    protected function getTSConfig()
    {
        return $GLOBALS['BE_USER']->getTSConfig();
    }

    protected function getQueryBuilder(string $table): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
    }

    protected function getDataHandler(): DataHandler
    {
        return GeneralUtility::makeInstance(DataHandler::class);
    }

    protected function buildUriFromRoute(string $name, array $parameters = []): Uri
    {
        /** @var UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        return $uriBuilder->buildUriFromRoute(
            $name,
            $parameters
        );
    }

    protected function getDMPages(string $moduleName): array
    {
        $dmLinks = [];
        $rows = GeneralUtility::makeInstance(PagesRepository::class)->getDMPages();

        if (count($rows)) {
            foreach ($rows as $row) {
                if ($this->getBackendUser()->doesUserHaveAccess(BackendUtility::getRecord('pages', (int)$row['uid']), 2)) {
                    $dmLinks[] = [
                        'id' => $row['uid'],
                        'url' => $this->buildUriFromRoute($this->moduleName, ['id' => $row['uid'], 'updatePageTree' => '1']),
                        'title' => $row['title'],
                    ];
                }
            }
        }
        return $dmLinks;
    }

    protected function getFieldList(): array
    {
        return [
            'uid',
            'name',
            'first_name',
            'middle_name',
            'last_name',
            'title',
            'email',
            'phone',
            'www',
            'address',
            'company',
            'city',
            'zip',
            'country',
            'fax',
            'module_sys_dmail_category',
            'module_sys_dmail_html'
        ];
    }

    protected function getFieldListFeUsers(): array
    {
        $fieldList = $this->getFieldList();
        foreach(['telephone' => 'phone'] as $key => $val) {
            $index = array_search($val, $fieldList);
            $fieldList[$index] = $key;
        }

        return $fieldList;
    }

    protected function getTempPath(): string
    {
        return Environment::getPublicPath() . '/typo3temp/';
    }

    protected function getDmailerLockFilePath(): string
    {
        return $this->getTempPath() . 'tx_directmail_cron.lock';
    }

    protected function getIconActionsOpen(): Icon
    {
        return $this->iconFactory->getIcon('actions-open', Icon::SIZE_SMALL);
    }

    /**
     * Prepare DB record
     *
     * @param array $listArr All DB records to be formated
     * @param string $table Table name
     *
     * @return	array		list of record
     */
    protected function getRecordList(array $listArr, string $table)
    {
        $lang = $this->getLanguageService();
        $lllFile = 'LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf';
        $output = [
            'title' => $lang->sL($lllFile . ':dmail_number_records'),
            'editLinkTitle' => $lang->sL($lllFile . ':dmail_edit'),
            'actionsOpen' => $this->iconFactory->getIcon('actions-open', Icon::SIZE_SMALL),
            'counter' => is_array($listArr) ? count($listArr) : 0,
            'rows' => [],
        ];

        $isAllowedDisplayTable = $this->getBackendUser()->check('tables_select', $table);
        $isAllowedEditTable = $this->getBackendUser()->check('tables_modify', $table);

        if (is_array($listArr)) {
            $notAllowedPlaceholder = $lang->sL($lllFile . ':mailgroup_table_disallowed_placeholder');
            $tableIcon = $this->iconFactory->getIconForRecord($table, []);
            foreach ($listArr as $row) {
                $editLink = '';
                if (($row['uid'] ?? false) && $isAllowedEditTable) {
                    $urlParameters = [
                        'edit' => [
                            $table => [
                                $row['uid'] => 'edit',
                            ],
                        ],
                        'returnUrl' => $this->requestUri,
                    ];

                    $editLink = $this->buildUriFromRoute('record_edit', $urlParameters);
                }

                $name = $row['name'] ?? '';
                if ($name == '') {
                    if ($row['first_name'] ?? '') {
                        $name = $row['first_name'] . ' ';
                    }
                    if ($row['middle_name'] ?? '') {
                        $name .= $row['middle_name'] . ' ';
                    }
                    $name .= $row['last_name'] ?? '';
                }

                $output['rows'][] = [
                    'icon' => $tableIcon,
                    'editLink' => $editLink,
                    'email' => $isAllowedDisplayTable ? htmlspecialchars($row['email']) : $notAllowedPlaceholder,
                    'name' => $isAllowedDisplayTable ? htmlspecialchars($name) : '',
                ];
            }
        }

        return $output;
    }

    /**
     * generate edit link for records
     * https://docs.typo3.org/m/typo3/reference-coreapi/main/en-us/ApiOverview/Backend/EditLinks.html
     *
     * @param $params
     * @return Uri
     * @throws \TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException
     */
    protected function getEditOnClickLink(array $params): Uri
    {
        $backendUriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        return $backendUriBuilder->buildUriFromRoute('record_edit', $params);
    }

    /**
     * Rearrange emails array into a 2-dimensional array
     *
     * @param array $plainMails Recipient emails
     *
     * @return array a 2-dimensional array consisting email and name
     */
    protected function rearrangePlainMails(array $plainMails): array
    {
        $out = [];
        if (is_array($plainMails)) {
            $c = 0;
            foreach ($plainMails as $v) {
                $out[$c]['email'] = trim($v);
                $out[$c]['name'] = '';
                $c++;
            }
        }
        return $out;
    }

    /**
     * Remove double record in an array
     *
     * @param array $plainlist Email of the recipient
     *
     * @return array Cleaned array
     */
    protected function cleanPlainList(array $plainlist)
    {
        /**
         * $plainlist is a multidimensional array.
         * this method only remove if a value has the same array
         * $plainlist = [
         * 		0 => [
         * 			name => '',
         * 			email => '',
         * 		],
         * 		1 => [
         * 			name => '',
         * 			email => '',
         * 		],
         * ];
         */
        return array_map('unserialize', array_unique(array_map('serialize', $plainlist)));
    }

    /**
     * Get the ID of page in a tree
     *
     * @param int $id Page ID
     * @param string $perms_clause Select query clause
     * @return array the page ID, recursively
     */
    protected function getRecursiveSelect($id, $perms_clause)
    {
        $getLevels = 10000;
        // Finding tree and offer setting of values recursively.
        $tree = GeneralUtility::makeInstance(PageTreeView::class);
        $tree->init('AND ' . $perms_clause);
        $tree->makeHTML = 0;
        $tree->setRecs = 0;
        $tree->getTree($id, $getLevels, '');

        return $tree->ids;
    }

}
