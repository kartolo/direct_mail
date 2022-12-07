<?php
declare(strict_types=1);

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
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\Components\ButtonBar;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Imaging\Icon;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class NavFrameController extends MainController
{
    /**
     * Set highlight
     * @var	string
     */
    protected $doHighlight;

    public function indexAction(ServerRequestInterface $request) : ResponseInterface
    {
        $currentModule = (string)($request->getQueryParams()['currentModule'] ?? $request->getParsedBody()['currentModule'] ?? 'DirectMailNavFrame_Configuration');
        /** @var UriBuilder $uriBuilder */
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        $currentSubScript = $uriBuilder->buildUriFromRoute($currentModule);

        // Setting highlight mode:
        $disableTitleHighlight = $this->getTSConfig()['options.']['pageTree.']['disableTitleHighlight'] ?? false;
        $this->doHighlight = (bool)($disableTitleHighlight) ? false : true;

        $this->view = $this->configureTemplatePaths('NavFrame');

        $rows = $this->getPages();
        $pages = [];
        while (($row = $rows->fetchAssociative()) !== false) {
            if (BackendUtility::readPageAccess($row['uid'], $this->getBackendUser()->getPagePermsClause(1))) {
                $icon = $this->iconFactory->getIconForRecord('pages', $row, Icon::SIZE_SMALL)->render();
                $pages[] = ['icon' => $icon, 'page' => $row];
            }
        }
        unset($rows);

        $this->setDocHeader('index');

        //$this->moduleTemplate->addJavaScriptCode($this->getJS($currentModule, $currentSubScript));
        $this->pageRenderer->addJsInlineCode($currentModule, $this->getJSNavFrame($currentModule, $currentSubScript));

        $this->view->assignMultiple(
            [
                'pages' => $pages,
            ]
        );

        /**
         * Render template and return html content
         */
        $this->moduleTemplate->setContent($this->view->render());
        return new HtmlResponse($this->moduleTemplate->renderContent());
    }

    protected function getJSNavFrame($currentModule, $currentSubScript) {
        // @TODO Uncaught Error: Writing to fsMod is not possible anymore, use ModuleStateStorage instead.
        // https://docs.typo3.org/c/typo3/cms-core/master/en-us/Changelog/11.4/Deprecation-94762-DeprecateJavaScriptTopfsModState.html
        // https://github.com/typo3/typo3/commit/ca4afee813
        // https://git.higidi.com/TYPO3/TYPO3.CMS/-/commit/1da997b9d7823900300568e181d7d1c17ecef71f
        // https://docs.typo3.org/c/typo3/cms-core/main/en-us/Changelog/11.4/Deprecation-95011-VariousGlobalJavaScriptFunctionsAndVariables.html
        return ($currentModule ? 'top.currentSubScript=unescape("' . rawurlencode($currentSubScript) . '");' : '') . '
			function jumpTo(params,linkObj,highLightID)	{
				var theUrl = top.currentSubScript+"&"+params;

				if (top.condensedMode)	{
					top.content.document.location=theUrl;
				} else {
					parent.list_frame.document.location=theUrl;
				}
				' . ($this->doHighlight ? 'hilight_row("row"+top.fsMod.recentIds["DirectMailNavFrame"],highLightID);' : '') . '
				' . ((!isset($GLOBALS['CLIENT']['FORMSTYLE']) || !$GLOBALS['CLIENT']['FORMSTYLE']) ? '' : 'if (linkObj) {linkObj.blur();}') . '
				return false;
			}

            // Call this function, refresh_nav(), from another script in the backend if you want to refresh the navigation frame (eg. after having changed a page title or moved pages etc.)
			// See t3lib_BEfunc::getSetUpdateSignal()
			function refresh_nav() {
				window.setTimeout("_refresh_nav();",0);
			}

			function _refresh_nav()	{
				document.location="' . htmlspecialchars(GeneralUtility::getIndpEnv('SCRIPT_NAME') . '?unique=' . time()) . '";
			}

			// Highlighting rows in the page tree:
			function hilight_row(frameSetModule,highLightID) {
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
        ;
    }

    protected function getPages() {
        $queryBuilder = $this->getQueryBuilder('pages');
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $statement = $queryBuilder
            ->select('uid', 'title', 'module')
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
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'sys_language_uid',
                    '0'
                )
            )
            ->orderBy('title')
//             debug($statement->getSQL());
//             debug($statement->getParameters());
            ->execute();

        return $statement;
    }

    private function setDocHeader(string $active) {
        /**
        $docHeaderButtons = [
            'CSH' => BackendUtility::cshItem('_MOD_DirectMailNavFrame', 'folders', $GLOBALS['BACK_PATH'], true),
            'REFRESH' => '<a class="btn btn-default btn-sm " href="' . htmlspecialchars(GeneralUtility::linkThisScript(['unique' => uniqid('directmail_navframe')])) . '"></a>'
        ];
        */

        $buttonBar = $this->moduleTemplate->getDocHeaderComponent()->getButtonBar();
        $list = $buttonBar->makeLinkButton()
        ->setHref(GeneralUtility::linkThisScript(['unique' => uniqid('directmail_navframe')]))
        //->setHref(GeneralUtility::getIndpEnv('REQUEST_URI'))
        ->setTitle($this->getLanguageService()->getLL('labels.reload'))
        ->setShowLabelText('Link')
        ->setIcon($this->iconFactory->getIcon('actions-refresh', Icon::SIZE_SMALL));
        $buttonBar->addButton($list, ButtonBar::BUTTON_POSITION_RIGHT, 1);
    }
}
