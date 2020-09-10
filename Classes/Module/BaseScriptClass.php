<?php
namespace DirectMailTeam\DirectMail\Module;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use DirectMailTeam\DirectMail\DirectMailUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Imaging\IconFactory;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Type\Bitmask\Permission;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Parent class for 'ScriptClasses' in backend modules.
 *
 * Copy from TYPO3 v9.5
 * Postpone Deprecation: #86225 - Classes BaseScriptClass and AbstractFunctionModule
 *
 * EXAMPLE PROTOTYPE
 *
 * As for examples there are lots of them if you search for classes which extends \TYPO3\CMS\Backend\Module\BaseScriptClass
 * However you can see a prototype example of how a module might use this class in an index.php file typically hosting a backend module.
 *
 * NOTICE: This example only outlines the basic structure of how this class is used.
 * You should consult the documentation and other real-world examples for some actual things to do when building modules.
 *
 * TYPICAL SETUP OF A BACKEND MODULE:
 *
 * PrototypeController EXTENDS THE CLASS \TYPO3\CMS\Backend\Module\BaseScriptClass with a main() function:
 *
 * namespace Vendor\Prototype\Controller;
 *
 * class PrototypeController extends \TYPO3\CMS\Backend\Module\BaseScriptClass {
 * 	public function __construct() {
 * 		$this->getLanguageService()->includeLLFile('EXT:prototype/Resources/Private/Language/locallang.xlf');
 * 		$this->getBackendUser()->modAccess($GLOBALS['MCONF']);
 * 	}
 * }
 *
 * MAIN FUNCTION - HERE YOU CREATE THE MODULE CONTENT IN $this->content
 * public function main() {
 * 	TYPICALLY THE INTERNAL VAR, $this->doc is instantiated like this:
 * 	$this->doc = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Backend\Template\DocumentTemplate::class);
 * 	... AND OF COURSE A LOT OF OTHER THINGS GOES ON - LIKE PUTTING CONTENT INTO $this->content
 * 	$this->content='';
 * }
 *
 * MAKE INSTANCE OF THE SCRIPT CLASS AND CALL init()
 * $GLOBALS['SOBE'] = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\Vendor\Prototype\Controller\PrototypeController::class);
 * $GLOBALS['SOBE']->init();
 *
 * THEN WE CALL THE main() METHOD AND THIS SHOULD SPARK THE CREATION OF THE MODULE OUTPUT.
 * $GLOBALS['SOBE']->main();
 */
class BaseScriptClass
{
    /**
     * Loaded with the global array $MCONF which holds some module configuration from the conf.php file of backend modules.
     *
     * @see init()
     * @var array
     */
    public $MCONF = [];

    /**
     * The integer value of the GET/POST var, 'id'. Used for submodules to the 'Web' module (page id)
     *
     * @see init()
     * @var int
     */
    public $id;

    /**
     * The value of GET/POST var, 'CMD'
     *
     * @see init()
     * @var mixed
     */
    public $CMD;

    /**
     * A WHERE clause for selection records from the pages table based on read-permissions of the current backend user.
     *
     * @see init()
     * @var string
     */
    public $perms_clause;

    /**
     * The module menu items array. Each key represents a key for which values can range between the items in the array of that key.
     *
     * @see init()
     * @var array
     */
    public $MOD_MENU = [
        'function' => []
    ];

    /**
     * Current settings for the keys of the MOD_MENU array
     *
     * @see $MOD_MENU
     * @var array
     */
    public $MOD_SETTINGS = [];

    /**
     * Module TSconfig based on PAGE TSconfig / USER TSconfig
     *
     * @see menuConfig()
     * @var array
     */
    public $modTSconfig;

    /**
     * If type is 'ses' then the data is stored as session-lasting data. This means that it'll be wiped out the next time the user logs in.
     * Can be set from extension classes of this class before the init() function is called.
     *
     * @see menuConfig(), \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData()
     * @var string
     */
    public $modMenu_type = '';

    /**
     * dontValidateList can be used to list variables that should not be checked if their value is found in the MOD_MENU array. Used for dynamically generated menus.
     * Can be set from extension classes of this class before the init() function is called.
     *
     * @see menuConfig(), \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData()
     * @var string
     */
    public $modMenu_dontValidateList = '';

    /**
     * List of default values from $MOD_MENU to set in the output array (only if the values from MOD_MENU are not arrays)
     * Can be set from extension classes of this class before the init() function is called.
     *
     * @see menuConfig(), \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData()
     * @var string
     */
    public $modMenu_setDefaultList = '';

    /**
     * Contains module configuration parts from TBE_MODULES_EXT if found
     *
     * @see handleExternalFunctionValue()
     * @var array
     */
    public $extClassConf;

    /**
     * Generally used for accumulating the output content of backend modules
     *
     * @var string
     */
    public $content = '';

    /**
     * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
     */
    public $doc;

    /**
     * @var PageRenderer
     */
    protected $pageRenderer;

    /**
     * First initialization of global variables
     *
     * @return	void
     */
    public function init()
    {
        // Name might be set from outside
        if (!$this->MCONF['name']) {
            $this->MCONF = $GLOBALS['MCONF'];
        }
        $this->id = (int)GeneralUtility::_GP('id');
        $this->CMD = GeneralUtility::_GP('CMD');
        $this->perms_clause = $this->getBackendUser()->getPagePermsClause(Permission::PAGE_SHOW);
        $this->menuConfig();
        $this->handleExternalFunctionValue();

        // initialize IconFactory
        $this->iconFactory = GeneralUtility::makeInstance(IconFactory::class);

        // get the config from pageTS
        $this->params = BackendUtility::getPagesTSconfig($this->id)['mod.']['web_modules.']['dmail.'] ?? [];
        $this->implodedParams = DirectMailUtility::implodeTSParams($this->params);
        if ($this->params['userTable'] && is_array($GLOBALS['TCA'][$this->params['userTable']])) {
            $this->userTable = $this->params['userTable'];
            $this->allowedTables[] = $this->userTable;
        }

        $this->MOD_MENU['dmail_mode'] = $this->unsetMenuItems($this->params, $this->MOD_MENU['dmail_mode'], 'menu.dmail_mode');

        // initialize backend user language
        if ($this->getLanguageService()->lang && ExtensionManagementUtility::isLoaded('static_info_tables')) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getQueryBuilderForTable('sys_language');
            $res = $queryBuilder
                ->select('sys_language.uid')
                ->from('sys_language')
                ->leftJoin(
                    'sys_language',
                    'static_languages',
                    'static_languages',
                    $queryBuilder->expr()->eq('sys_language.static_lang_isocode', $queryBuilder->quoteIdentifier('static_languages.uid'))
                )
                ->where(
                    $queryBuilder->expr()->eq('static_languages.lg_typo3', $queryBuilder->createNamedParameter($this->getLanguageService()->lang))
                )
                ->execute()
                ->fetchAll();
            foreach ($res as $row) {
                $this->sys_language_uid = $row['uid'];
            }
        }
        // load contextual help
        $this->cshTable = '_MOD_' . $this->MCONF['name'];
        if ($GLOBALS['BE_USER']->uc['edit_showFieldHelp']) {
            $this->getLanguageService()->loadSingleTableDescription($this->cshTable);
        }
    }

    /**
     * Initializes the internal MOD_MENU array setting and unsetting items based on various conditions. It also merges in external menu items from the global array TBE_MODULES_EXT (see mergeExternalItems())
     * Then MOD_SETTINGS array is cleaned up (see \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData()) so it contains only valid values. It's also updated with any SET[] values submitted.
     * Also loads the modTSconfig internal variable.
     *
     * @see init(), $MOD_MENU, $MOD_SETTINGS, \TYPO3\CMS\Backend\Utility\BackendUtility::getModuleData(), mergeExternalItems()
     */
    public function menuConfig()
    {
        // Page / user TSconfig settings and blinding of menu-items
        $this->modTSconfig['properties'] = BackendUtility::getPagesTSconfig($this->id)['mod.'][$this->MCONF['name'] . '.'] ?? [];
        $this->MOD_MENU['function'] = $this->mergeExternalItems($this->MCONF['name'], 'function', $this->MOD_MENU['function']);
        $blindActions = $this->modTSconfig['properties']['menu.']['function.'] ?? [];
        foreach ($blindActions as $key => $value) {
            if (!$value && array_key_exists($key, $this->MOD_MENU['function'])) {
                unset($this->MOD_MENU['function'][$key]);
            }
        }
        $this->MOD_SETTINGS = BackendUtility::getModuleData($this->MOD_MENU, GeneralUtility::_GP('SET'), $this->MCONF['name'], $this->modMenu_type, $this->modMenu_dontValidateList, $this->modMenu_setDefaultList);
    }

    /**
     * Merges menu items from global array $TBE_MODULES_EXT
     *
     * @param string $modName Module name for which to find value
     * @param string $menuKey Menu key, eg. 'function' for the function menu.
     * @param array $menuArr The part of a MOD_MENU array to work on.
     * @return array Modified array part.
     * @internal
     * @see \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::insertModuleFunction(), menuConfig()
     */
    public function mergeExternalItems($modName, $menuKey, $menuArr)
    {
        $mergeArray = $GLOBALS['TBE_MODULES_EXT'][$modName]['MOD_MENU'][$menuKey];
        if (is_array($mergeArray)) {
            foreach ($mergeArray as $k => $v) {
                if (((string)$v['ws'] === '' || $this->getBackendUser()->workspace === 0 && GeneralUtility::inList($v['ws'], 'online')) || $this->getBackendUser()->workspace === -1 && GeneralUtility::inList($v['ws'], 'offline') || $this->getBackendUser()->workspace > 0 && GeneralUtility::inList($v['ws'], 'custom')) {
                    $menuArr[$k] = $this->getLanguageService()->sL($v['title']);
                }
            }
        }
        return $menuArr;
    }

    /**
     * Loads $this->extClassConf with the configuration for the CURRENT function of the menu.
     *
     * @param string $MM_key The key to MOD_MENU for which to fetch configuration. 'function' is default since it is first and foremost used to get information per "extension object" (I think that is what its called)
     * @param string $MS_value The value-key to fetch from the config array. If NULL (default) MOD_SETTINGS[$MM_key] will be used. This is useful if you want to force another function than the one defined in MOD_SETTINGS[function]. Call this in init() function of your Script Class: handleExternalFunctionValue('function', $forcedSubModKey)
     * @see getExternalItemConfig(), init()
     */
    public function handleExternalFunctionValue($MM_key = 'function', $MS_value = null)
    {
        if ($MS_value === null) {
            $MS_value = $this->MOD_SETTINGS[$MM_key];
        }
        $this->extClassConf = $this->getExternalItemConfig($this->MCONF['name'], $MM_key, $MS_value);
    }

    /**
     * Returns configuration values from the global variable $TBE_MODULES_EXT for the module given.
     * For example if the module is named "web_info" and the "function" key ($menuKey) of MOD_SETTINGS is "stat" ($value) then you will have the values of $TBE_MODULES_EXT['webinfo']['MOD_MENU']['function']['stat'] returned.
     *
     * @param string $modName Module name
     * @param string $menuKey Menu key, eg. "function" for the function menu. See $this->MOD_MENU
     * @param string $value Optionally the value-key to fetch from the array that would otherwise have been returned if this value was not set. Look source...
     * @return mixed The value from the TBE_MODULES_EXT array.
     * @see handleExternalFunctionValue()
     */
    public function getExternalItemConfig($modName, $menuKey, $value = '')
    {
        if (isset($GLOBALS['TBE_MODULES_EXT'][$modName])) {
            return (string)$value !== '' ? $GLOBALS['TBE_MODULES_EXT'][$modName]['MOD_MENU'][$menuKey][$value] : $GLOBALS['TBE_MODULES_EXT'][$modName]['MOD_MENU'][$menuKey];
        }
        return null;
    }

    /**
     * Returns the Language Service
     * @return LanguageService
     */
    protected function getLanguageService()
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

    /**
     * @return PageRenderer
     */
    protected function getPageRenderer()
    {
        if ($this->pageRenderer === null) {
            $this->pageRenderer = GeneralUtility::makeInstance(PageRenderer::class);
        }

        return $this->pageRenderer;
    }

    /**
     * Removes menu items from $itemArray if they are configured to be removed by TSconfig for the module ($modTSconfig)
     * Copy from TYPO3 9.5.13
     *
     * @param array $modTSconfig Module TS config array
     * @param array $itemArray Array of items from which to remove items.
     * @param string $TSref $TSref points to the "object string" in $modTSconfig
     * @return array The modified $itemArray is returned.
     */
    protected function unsetMenuItems($modTSconfig, $itemArray, $TSref)
    {
        // Getting TS-config options for this module for the Backend User:
        $conf = $this->getBackendUserAuthentication()->getTSConfig($TSref, $modTSconfig);
        if (is_array($conf['properties'])) {
            foreach ($conf['properties'] as $key => $val) {
                if (!$val) {
                    unset($itemArray[$key]);
                }
            }
        }
        return $itemArray;
    }

    /**
     * @return BackendUserAuthentication
     */
    protected function getBackendUserAuthentication()
    {
        return $GLOBALS['BE_USER'];
    }
}
