<?php
namespace DirectMailTeam\DirectMail\Module;

use DirectMailTeam\DirectMail\DirectMailUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

class MainController {
    
    /**
     * ModuleTemplate Container
     *
     * @var ModuleTemplate
     */
    protected $moduleTemplate;
    
    /**
     * @var StandaloneView
     */
    protected $view;

    protected int $id = 0;
    protected string $cmd = '';
    protected int $sys_dmail_uid = 0;
    protected string $pages_uid = '';
    
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
    protected $allowedTables = [];
    protected int $sys_language_uid = 0;
    protected array $pageinfo = [];
    protected bool $access = false;
    
    protected $messageQueue;
    
    /**
     * Constructor Method
     *
     * @var ModuleTemplate $moduleTemplate
     */
    public function __construct(ModuleTemplate $moduleTemplate = null)
    {
        $this->moduleTemplate = $moduleTemplate ?? GeneralUtility::makeInstance(ModuleTemplate::class);
        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf');
    }
    
    protected function init(ServerRequestInterface $request): void {
        $queryParams = $request->getQueryParams();
        $parsedBody = $request->getParsedBody();
        
        $this->id = (int)($parsedBody['id'] ?? $queryParams['id'] ?? 0);
        $this->cmd = (string)($parsedBody['cmd'] ?? $queryParams['cmd'] ?? '');
        $this->pages_uid = (string)($parsedBody['pages_uid'] ?? $queryParams['pages_uid'] ?? '');
        $this->sys_dmail_uid = (int)($parsedBody['sys_dmail_uid'] ?? $queryParams['sys_dmail_uid'] ?? 0);
        
        $this->perms_clause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        $this->pageinfo = BackendUtility::readPageAccess($this->id, $this->perms_clause);
        
        $this->access = is_array($this->pageinfo) ? true : false;
        
        // get the config from pageTS
        $this->params = BackendUtility::getPagesTSconfig($this->id)['mod.']['web_modules.']['dmail.'] ?? [];
        $this->implodedParams = DirectMailUtility::implodeTSParams($this->params);
        if ($this->params['userTable'] ?? false && isset($GLOBALS['TCA'][$this->params['userTable']]) && is_array($GLOBALS['TCA'][$this->params['userTable']])) {
            $this->userTable = $this->params['userTable'];
            $this->allowedTables[] = $this->userTable;
        }
        // initialize backend user language
        //$this->sys_language_uid = 0; //@TODO
        
        $this->messageQueue = $this->getMessageQueue();
    }

    /**
     * Configure template paths for your backend module
     * @return StandaloneView
     */
    protected function configureTemplatePaths (string $templateName): StandaloneView
    {
        $view = GeneralUtility::makeInstance(StandaloneView::class);
        $view->setTemplateRootPaths(['EXT:direct_mail/Resources/Private/Templates/']);
        $view->setPartialRootPaths(['EXT:direct_mail/Resources/Private/Partials/']);
        $view->setLayoutRootPaths(['EXT:direct_mail/Resources/Private/Layouts/']);
        $view->setTemplate($templateName);
        return $view;
    }
    
    /**
     *  
        https://api.typo3.org/11.5/class_t_y_p_o3_1_1_c_m_s_1_1_core_1_1_messaging_1_1_abstract_message.html
        const 	NOTICE = -2
        const 	INFO = -1
        const 	OK = 0
        const 	WARNING = 1
        const 	ERROR = 2
     * @param string $messageText
     * @param string $messageHeader
     * @param int $messageType
     * @param bool $storeInSession
     */
    protected function createFlashMessage(string $messageText, string $messageHeader = '', int $messageType = 0, bool $storeInSession = false) {
        return GeneralUtility::makeInstance(FlashMessage::class,
            $messageText,
            $messageHeader, // [optional] the header
            $messageType, // [optional] the severity defaults to \TYPO3\CMS\Core\Messaging\FlashMessage::OK
            $storeInSession // [optional] whether the message should be stored in the session or only in the \TYPO3\CMS\Core\Messaging\FlashMessageQueue object (default is false)
        );
    }
    
    protected function getMessageQueue() {
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        return $flashMessageService->getMessageQueueByIdentifier();
    }
    
    /**
     * @return LanguageService
     */
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
    
    /**
     * Returns the Backend User
     * @return BackendUserAuthentication
     */
    protected function getBackendUser()
    {
        return $GLOBALS['BE_USER'];
    }

    protected function isAdmin(): bool
    {
        return $GLOBALS['BE_USER']->isAdmin();
    }

    protected function getTSConfig() {
        return $GLOBALS['BE_USER']->getTSConfig();
    }

    protected function getQueryBuilder($table): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
    }
}