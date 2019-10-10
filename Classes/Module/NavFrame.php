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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Imaging\IconFactory;

/**
 * Class to producing navigation frame of the tx_directmail extension
 *
 * @author		Kasper Skårhøj <kasper@typo3.com>
 * @author		Ivan-Dharma Kartolo	<ivan.kartolo@dkd.de>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 * @version 	$Id: index.php 30331 2010-02-22 22:27:07Z ivankartolo $
 */

class NavFrame
{

    /**
     * The template object
     * @var \TYPO3\CMS\Backend\Template\DocumentTemplate
     */
    public $doc;

    /**
     * Set highlight
     * @var	string
     */
    protected $doHighlight;

    /**
     * HTML output
     * @var string
     */
    protected $content;

    public $pageinfo;

    /**
     * The name of the module
     *
     * @var string
     */
    protected $moduleName = 'DirectMailNavFrame';

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->MCONF = array(
            'name' => $this->moduleName
        );
    }

    /**
     * First initialization of the global variables. Set some JS-code
     *
     * @return	void
     */
    public function init()
    {
        $this->doc = GeneralUtility::makeInstance('TYPO3\\CMS\\Backend\\Template\\DocumentTemplate');
        $this->doc->setModuleTemplate('EXT:direct_mail/Resources/Private/Templates/NavFrame.html');
        $this->doc->showFlashMessages = false;

        $currentModule = GeneralUtility::_GP('currentModule');
        $currentSubScript = BackendUtility::getModuleUrl($currentModule);

        // Setting highlight mode:
        $this->doHighlight = (bool)($GLOBALS['BE_USER']->getTSConfig()['options.']['pageTree.']['disableTitleHighlight']) ? false : true;

        $this->doc->inDocStylesArray[] = '#typo3-docheader-row2 { line-height: 14px !important; }
		#typo3-docheader-row2 span { font-weight: bold; margin-top: -3px; color: #000; margin-top: 0; padding-left: 20px; }';

        // Setting JavaScript for menu.
        $this->doc->JScode = GeneralUtility::wrapJS(
            ($currentModule ? 'top.currentSubScript=unescape("' . rawurlencode($currentSubScript) . '");' : '') . '

			function jumpTo(params,linkObj,highLightID)	{ //
				var theUrl = top.currentSubScript+"&"+params;

				if (top.condensedMode)	{
					top.content.document.location=theUrl;
				} else {
					parent.list_frame.document.location=theUrl;
				}
				' . ($this->doHighlight ? 'hilight_row("row"+top.fsMod.recentIds["DirectMailNavFrame"],highLightID);' : '') . '
				' . (!$GLOBALS['CLIENT']['FORMSTYLE'] ? '' : 'if (linkObj) {linkObj.blur();}') . '
				return false;
			}


				// Call this function, refresh_nav(), from another script in the backend if you want to refresh the navigation frame (eg. after having changed a page title or moved pages etc.)
				// See t3lib_BEfunc::getSetUpdateSignal()
			function refresh_nav() { //
				window.setTimeout("_refresh_nav();",0);
			}


			function _refresh_nav()	{ //
				document.location="' . htmlspecialchars(GeneralUtility::getIndpEnv('SCRIPT_NAME') . '?unique=' . time()) . '";
			}

				// Highlighting rows in the page tree:
			function hilight_row(frameSetModule,highLightID) { //
					// Remove old:
				theObj = document.getElementById(top.fsMod.navFrameHighlightedID[frameSetModule]);
				if (theObj)	{
					theObj.style.backgroundColor="";
				}

					// Set new:
				top.fsMod.navFrameHighlightedID[frameSetModule] = highLightID;
				theObj = document.getElementById(highLightID);
			}
		'
        );
    }

    /**
     * Entrance from the backend module. This replace the _dispatch
     *
     * @param ServerRequestInterface $request The request object from the backend
     * @param ResponseInterface $response The reponse object sent to the backend
     *
     * @return ResponseInterface Return the response object
     */
    public function mainAction(ServerRequestInterface $request, ResponseInterface $response)
    {
        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf');
        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_csh_sysdmail.xlf');

        $this->init();

        $this->main();
        $this->printContent();

        $response->getBody()->write($this->content);
        return $response;
    }

    /**
     * Main function, rendering the browsable page tree
     *
     * @return	void
     */
    public function main()
    {
        $iconFactory = GeneralUtility::makeInstance(IconFactory::class);

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $statement = $queryBuilder
            ->select('*')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'doktype',
                    '254'
                )
            )
            ->andWhere(
                $queryBuilder->expr()->in(
                    'module',
                    $queryBuilder->createNamedParameter(
                        ['dmail'],
                        Connection::PARAM_STR_ARRAY
                    )
                )
            )
            ->orderBy('title')
            ->execute();

        $out = '';

        while (($row = $statement->fetch())) {
            if (BackendUtility::readPageAccess($row['uid'], $GLOBALS['BE_USER']->getPagePermsClause(1))) {
                $icon = $iconFactory->getIconForRecord('pages', $row, Icon::SIZE_SMALL)->render();

                $out .= '<tr>' .
                    '<td id="dmail_' . $row['uid'] . '" >
						<a href="#" onclick="top.fsMod.recentIds[\'DirectMailNavFrame\']=' . $row['uid'] . ';jumpTo(\'id=' . $row['uid'] . '\',this,\'dmail_' . $row['uid'] . '\');">' .
                    $icon .
                    '&nbsp;' . htmlspecialchars($row['title']) . '</a></td></tr>';
            }
        }

        $content = '<table cellspacing="0" cellpadding="0" border="0" width="100%">' . $out . '</table>';

        // Adding highlight - JavaScript
        if ($this->doHighlight) {
            $content .= GeneralUtility::wrapJS('hilight_row("",top.fsMod.navFrameHighlightedID["web"]);');
        }


        $docHeaderButtons = array(
            'CSH' => BackendUtility::cshItem('_MOD_DirectMailNavFrame', 'folders', $GLOBALS['BACK_PATH'], true),
            'REFRESH' => '<a href="' . htmlspecialchars(GeneralUtility::linkThisScript(array('unique' => uniqid('directmail_navframe')))) . '">' .
                $iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL) . '</a>'
        );

        $markers = array(
            'HEADLINE' => '',
            'CONTENT' => $this->getLanguageService()->getLL('dmail_folders') . $content
        );
        // Build the <body> for the module
        $this->content = $this->doc->startPage('TYPO3 Direct Mail Navigation');
        $this->content .= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers);
    }

    /**
     * Outputting the accumulated content to screen
     *
     * @return	void
     */
    public function printContent()
    {
        $this->content.= $this->doc->endPage();
        $this->content = $this->doc->insertStylesAndJS($this->content);
    }

    /**
     * Returns LanguageService
     *
     * @return \TYPO3\CMS\Lang\LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }
}
