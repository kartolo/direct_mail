<?php
/***************************************************************
 *  Copyright notice
 *
 *  (c) 1999-2004 Kasper Skaarhoj (kasper@typo3.com)
 *  (c) 2005-2006 Jan-Erik Revsbech <jer@moccompany.com>
 *  (c) 2006 Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 2 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *  A copy is found in the textfile GPL.txt and important notices to the license
 *  from the author is found in LICENSE.txt distributed with these scripts.
 *
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * @author		Kasper Skårhøj <kasper@typo3.com>
 * @author  	Jan-Erik Revsbech <jer@moccompany.com>
 * @author  	Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 * @author		Ivan-Dharma Kartolo	<ivan.kartolo@dkd.de>
 *
 * @package 	TYPO3
 * @subpackage	tx_directmail
 *
 * @version 	$Id: class.tx_directmail_dmail.php 30935 2010-03-09 18:12:41Z ivankartolo $
 */

require_once (PATH_t3lib.'class.t3lib_scbase.php');
require_once (PATH_t3lib.'class.t3lib_tstemplate.php');
require_once(PATH_t3lib.'class.t3lib_timetrack.php');
require_once(t3lib_extMgm::extPath('direct_mail').'res/scripts/class.mailselect.php');
require_once(t3lib_extMgm::extPath('direct_mail').'res/scripts/class.dmailer.php');
require_once(t3lib_extMgm::extPath('direct_mail').'res/scripts/class.tx_directmail_static.php');

/**
 * Direct mail Module of the tx_directmail extension for sending newsletter
 *
 */
class tx_directmail_dmail extends t3lib_SCbase {
	var $extKey = 'direct_mail';
	var $TSconfPrefix = 'mod.web_modules.dmail.';
	var $fieldList = 'uid,name,title,email,phone,www,address,company,city,zip,country,fax,module_sys_dmail_category,module_sys_dmail_html';
	// Internal
	var $params = array();
	var $perms_clause = '';
	var $pageinfo = '';
	var $sys_dmail_uid;
	var $CMD;
	var $pages_uid;
	var $categories;
	var $id;
	var $urlbase;
	var $back;
	var $noView;
	var $mode;
	var $implodedParams = array();
	var $userTable;		// If set a valid user table is around
	var $sys_language_uid = 0;
	var $error='';
	var $allowedTables = array('tt_address','fe_users');
	var $MCONF;
	var $cshTable;
	var $formname = 'dmailform';


	protected $currentStep = 1;

	/**
	 * first initialization of global variables
	 *
	 * @return	void		...
	 */
	function init()	{
		$this->MCONF = $GLOBALS['MCONF'];

		$this->include_once[]=PATH_t3lib.'class.t3lib_tcemain.php';

		parent::init();

		// get the config from pageTS
		$temp = t3lib_BEfunc::getModTSconfig($this->id,'mod.web_modules.dmail');
		$this->params = $temp['properties'];
		$this->implodedParams = t3lib_BEfunc::implodeTSParams($this->params);
		if ($this->params['userTable'] && is_array($GLOBALS['TCA'][$this->params['userTable']]))	{
			$this->userTable = $this->params['userTable'];
			t3lib_div::loadTCA($this->userTable);
			$this->allowedTables[] = $this->userTable;
		}

		// check if the right domain shoud be set
		if (!$this->params['use_domain']) {
			$rootLine = t3lib_BEfunc::BEgetRootLine($this->id);
			if ($rootLine) {
				$parts = parse_url(t3lib_div::getIndpEnv('TYPO3_SITE_URL'));
				if (t3lib_BEfunc::getDomainStartPage($parts['host'],$parts['path'])) {
					$preUrl_temp = t3lib_BEfunc::firstDomainRecord($rootLine);
					$domain = t3lib_BEfunc::getRecordsByField('sys_domain','domainName',$preUrl_temp,' AND hidden=0','','sorting');
					if (is_array($domain)) {
						reset($domain);
						$dom = current($domain);
						$this->params['use_domain'] = $dom['uid'];
					}
				}
			}
		}

		$this->MOD_MENU['dmail_mode'] = t3lib_BEfunc::unsetMenuItems($this->params,$this->MOD_MENU['dmail_mode'],'menu.dmail_mode');

			// initialize backend user language
		if ($GLOBALS['LANG']->lang && t3lib_extMgm::isLoaded('static_info_tables')) {
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'sys_language.uid',
				'sys_language LEFT JOIN static_languages ON sys_language.static_lang_isocode=static_languages.uid',
				'static_languages.lg_typo3='.$GLOBALS['TYPO3_DB']->fullQuoteStr($GLOBALS['LANG']->lang,'static_languages').
					t3lib_BEfunc::BEenableFields('sys_language').
					t3lib_BEfunc::deleteClause('sys_language').
					t3lib_BEfunc::deleteClause('static_languages')
				);
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$this->sys_language_uid = $row['uid'];
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
		}
			// load contextual help
		$this->cshTable = '_MOD_'.$this->MCONF['name'];
		if ($GLOBALS['BE_USER']->uc['edit_showFieldHelp']){
			$GLOBALS['LANG']->loadSingleTableDescription($this->cshTable);
		}

		t3lib_div::loadTCA('sys_dmail');
	}

	/**
	 * Prints out the module HTML
	 *
	 * @return	void		...
	 */
	function printContent()	{
		$this->content .= $this->doc->endPage();
		echo $this->content;
	}

	/**
	 * The main function. Set CSS and JS
	 *
	 * @return	void		...
	 */
	function main()	{
		global $BE_USER;

		$this->CMD = t3lib_div::_GP('CMD');
		$this->pages_uid = intval(t3lib_div::_GP('pages_uid'));
		$this->sys_dmail_uid = intval(t3lib_div::_GP('sys_dmail_uid'));
		$this->pageinfo = t3lib_BEfunc::readPageAccess($this->id,$this->perms_clause);
		$this->params['pid'] = intval($this->id);

		$access = is_array($this->pageinfo) ? 1 : 0;

		if (($this->id && $access) || ($BE_USER->user['admin'] && !$this->id)) {

			// Draw the header.
			$this->doc = t3lib_div::makeInstance('template');
			$this->doc->backPath = $GLOBALS['BACK_PATH'];
			$this->doc->setModuleTemplate('EXT:direct_mail/mod2/mod_template.html');
			$this->doc->form = '<form action="" method="post" name="'.$this->formname.'" enctype="multipart/form-data">';

			$pageRenderer = $this->doc->getPageRenderer();
				// Add CSS
			$pageRenderer->addCssFile('../Resources/Public/StyleSheets/modules.css', 'stylesheet', 'all', '', FALSE, FALSE);

			// JavaScript
			if (t3lib_div::inList('send_mail_final,send_mass',$this->CMD)) {
				// Load necessary extJS lib
				/** @var $pageRenderer t3lib_PageRenderer */
				$pageRenderer = $this->doc->getPageRenderer();
				$pageRenderer->loadExtJS();
				$pageRenderer->addJsFile($this->doc->backPath . '../t3lib/js/extjs/tceforms.js');
				if (t3lib_div::compat_version('4.6')) {
					$pageRenderer->addJsFile($this->doc->backPath .'../t3lib/js/extjs/ux/Ext.ux.DateTimePicker.js');
				}

				// Define settings for Date Picker
				$typo3Settings = array(
					'datePickerUSmode' => $GLOBALS['TYPO3_CONF_VARS']['SYS']['USdateFormat'] ? 1 : 0,
					'dateFormat'       => array('j-n-Y', 'G:i j-n-Y'),
					'dateFormatUS'     => array('n-j-Y', 'G:i n-j-Y'),
				);
				$pageRenderer->addInlineSettingArray('', $typo3Settings);
			}

			$this->doc->JScode .= '
				<script language="javascript" type="text/javascript">
					script_ended = 0;
					function jumpToUrl(URL)	{ //
						window.location.href = URL;
					}
					function jumpToUrlD(URL) { //
						window.location.href = URL+"&sys_dmail_uid='.$this->sys_dmail_uid.'";
					}
					function toggleDisplay(toggleId, e, countBox) { //
						if (!e) {
							e = window.event;
						}
						if (!document.getElementById) {
							return false;
						}

						prefix = toggleId.split("-");
						for (i=1; i<=countBox; i++){
							newToggleId = prefix[0]+"-"+i;
							body = document.getElementById(newToggleId);
							image = document.getElementById(toggleId + "_toggle");
							if (newToggleId != toggleId){
								if (body.style.display == "block"){
									body.style.display = "none";
									if (image) {
										image.src = "'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/button_right.gif', '', 1).'";
									}
								}
							}
						}

						var body = document.getElementById(toggleId);
						if (!body) {
							return false;
						}
						var image = document.getElementById(toggleId + "_toggle");
						if (body.style.display == "none") {
							body.style.display = "block";
							if (image) {
								image.src = "'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/button_down.gif', '', 1).'";
							}
						} else {
							body.style.display = "none";
							if (image) {
								image.src = "'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/button_right.gif', '', 1).'";
							}
						}
						if (e) {
							// Stop the event from propagating, which
							// would cause the regular HREF link to
							// be followed, ruining our hard work.
							e.cancelBubble = true;
							if (e.stopPropagation) {
								e.stopPropagation();
							}
						}
					}
				</script>
			';

			$this->doc->postCode='
				<script language="javascript" type="text/javascript">
					script_ended = 1;
					if (top.fsMod) top.fsMod.recentIds[\'web\'] = '.intval($this->id).';
				</script>
			';

			$markers = array(
				'TITLE' => '',
				'FLASHMESSAGES' => '',
				'CONTENT' => '',
				'WIZARDSTEPS' => '',
				'NAVIGATION' => ''
			);

			$docHeaderButtons = array(
				'PAGEPATH' => $GLOBALS['LANG']->sL('LLL:EXT:lang/locallang_core.php:labels.path').': '.t3lib_div::fixed_lgd_cs($this->pageinfo['_thePath'],50),
				'SHORTCUT' => ''
			);
				// shortcut icon
			if ($BE_USER->mayMakeShortcut()) {
				$docHeaderButtons['SHORTCUT'] = $this->doc->makeShortcutIcon('id', implode(',', array_keys($this->MOD_MENU)), $this->MCONF['name']);
			}

			$module = $this->pageinfo['module'];
			if (!$module) {
				$pidrec = t3lib_BEfunc::getRecord('pages',intval($this->pageinfo['pid']));
				$module = $pidrec['module'];
			}
			if ($module == 'dmail') {
					// Render content:
						// Direct mail module
				if (($this->pageinfo['doktype'] == 254) && ($this->pageinfo['module'] == 'dmail'))	{
					$markers = $this->moduleContent();
				} elseif ($this->id != 0) {
					/** @var $flashMessage t3lib_FlashMessage */
					$flashMessage = t3lib_div::makeInstance('t3lib_FlashMessage',
						$GLOBALS['LANG']->getLL('dmail_noRegular'),
						$GLOBALS['LANG']->getLL('dmail_newsletters'),
						t3lib_FlashMessage::WARNING
					);
					$markers['FLASHMESSAGES'] = $flashMessage->render();
				}
			} else {
				$flashMessage = t3lib_div::makeInstance('t3lib_FlashMessage',
					$GLOBALS['LANG']->getLL('select_folder'),
					$GLOBALS['LANG']->getLL('header_directmail'),
					t3lib_FlashMessage::WARNING
				);
				$markers['FLASHMESSAGES'] = $flashMessage->render();
			}

			$this->content = $this->doc->startPage($GLOBALS['LANG']->getLL('title'));
			$this->content.= $this->doc->moduleBody($this->pageinfo, $docHeaderButtons, $markers, array());

		} else {
			// If no access or if ID == zero

			$this->doc = t3lib_div::makeInstance('template');
			$this->doc->backPath = $GLOBALS['BACK_PATH'];

			$this->content .= $this->doc->startPage($GLOBALS['LANG']->getLL('title'));
			$this->content .= $this->doc->header($GLOBALS['LANG']->getLL('title'));
			$this->content .= $this->doc->spacer(5);
			$this->content .= $this->doc->spacer(10);
		}
	}

	/**
	 * Creates a directmail entry in th DB.
	 * used only for quickmail.
	 *
	 * @param	array		$indata: quickmail data (quickmail content, etc.)
	 * @return	string		error or warning message produced during the process
	 */
	function createDMail_quick($indata)	{
		$theOutput = "";
				// Set default values:
			$dmail = array();
			$dmail['sys_dmail']['NEW'] = array (
				'from_email'		=> $indata['senderEmail'],
				'from_name'			=> $indata['senderName'],
				'replyto_email'		=> $this->params['replyto_email'],
				'replyto_name'		=> $this->params['replyto_name'],
				'return_path'		=> $this->params['return_path'],
				'priority'			=> $this->params['priority'],
				'use_domain'		=> $this->params['use_domain'],
				'use_rdct'			=> $this->params['use_rdct'],
				'long_link_mode'	=> $this->params['long_link_mode'],
				'organisation'		=> $this->params['organisation'],
				'authcode_fieldList'=> $this->params['authcode_fieldList'],
				'plainParams'		=> ''
				);

			$dmail['sys_dmail']['NEW']['sendOptions'] = 1;		//always plaintext
			$dmail['sys_dmail']['NEW']['long_link_rdct_url'] = tx_directmail_static::getUrlBase($this->params['use_domain']);
			$dmail['sys_dmail']['NEW']['subject'] = $indata['subject'];
			$dmail['sys_dmail']['NEW']['type'] = 1;
			$dmail['sys_dmail']['NEW']['pid'] = $this->pageinfo['uid'];
			$dmail['sys_dmail']['NEW']['charset'] = isset($this->params['quick_mail_charset'])? $this->params['quick_mail_charset'] : 'utf-8';

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
				/** @var $tce t3lib_TCEmain */
				$tce = t3lib_div::makeInstance('t3lib_TCEmain');
				$tce->stripslashes_values = 0;
				$tce->start($dmail,Array());
				$tce->process_datamap();
				$this->sys_dmail_uid = $tce->substNEWwithIDs['NEW'];

				$row = t3lib_BEfunc::getRecord('sys_dmail',intval($this->sys_dmail_uid));
				//link in the mail
				$message = '<!--DMAILER_SECTION_BOUNDARY_-->'.$indata['message'].'<!--DMAILER_SECTION_BOUNDARY_END-->';
				if (trim($this->params['use_rdct'])) {
					$message = t3lib_div::substUrlsInPlainText($message,$this->params['long_link_mode']?'all':'76',tx_directmail_static::getUrlBase($this->params['use_domain']));
				}
				if ($indata['breakLines'])	{
					$message = wordwrap($message,76,"\n");
				}
				//fetch functions
				$theOutput = $this->compileQuickMail($row, $message);
				/* end fetch function*/
			} else {
				if (!$dmail['sys_dmail']['NEW']['sendOptions']) {
					$this->error = 'no_valid_url';
				}
			}

		return $theOutput;
	}

	/**
	 * compiling the quickmail content and save to DB
	 *
	 * @param array $row: the sys_dmail record
	 * @param string $message: body of the mail
	 * @TODO: remove htmlmail, compiling mail
	 */
	function compileQuickMail($row, $message) {
		$errorMsg = "";
		$warningMsg = "";

		// Compile the mail
		/** @var $htmlmail dmailer */
		$htmlmail = t3lib_div::makeInstance('dmailer');
		$htmlmail->nonCron = 1;
		$htmlmail->start();
		$htmlmail->charset = $row['charset'];
		$htmlmail->addPlain($message);

		if (!$message || !$htmlmail->theParts['plain']['content']) {
			$errorMsg .= '<br /><strong>' . $GLOBALS['LANG']->getLL('dmail_no_plain_content') . '</strong>';
		} elseif (!strstr(base64_decode($htmlmail->theParts['plain']['content']),'<!--DMAILER_SECTION_BOUNDARY')) {
			$warningMsg .= '<br /><strong>' . $GLOBALS['LANG']->getLL('dmail_no_plain_boundaries') . '</strong>';
		}

		//add attachment is removed. since it will be add during sending

		if (!$errorMsg) {
			// Update the record:
			$htmlmail->theParts['messageid'] = $htmlmail->messageid;
			$mailContent = base64_encode(serialize($htmlmail->theParts));
			$updateFields = array(
				'issent' => 0,
				'charset' => $htmlmail->charset,
				'mailContent' => $mailContent,
				'renderedSize' => strlen($mailContent),
				'long_link_rdct_url' => $this->urlbase
			);
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				'sys_dmail',
				'uid='.intval($this->sys_dmail_uid),
				$updateFields
			);

			if ($warningMsg)	{
				$theOutput = $this->doc->section($GLOBALS['LANG']->getLL('dmail_warning'), $warningMsg.'<br /><br />');
			}
		}
	}

	/**
	 * showing steps number on top of every page
	 *
	 * @param	integer		$totalSteps: total step
	 * @return	string		HTML
	 */
	function showSteps($totalSteps) {
		$content = '';
		for ($i = 1; $i <= $totalSteps; $i++) {
			$cssClass = ($i == $this->currentStep) ? 't3-wizard-item t3-wizard-item-active' : 't3-wizard-item';
			$content .= '<span class="' . $cssClass . '">&nbsp;' . $i . '&nbsp;</span>';
		}

		return '<div class="typo3-message message-ok t3-wizard-steps">' . $content . '</div>';
	}

	/**
	 * Function mailModule main()
	 *
	 * @return	string		HTML (steps)
	 */
	function moduleContent() {
		$theOutput = "";
		$isExternalDirectMailRecord = FALSE;

		$markers = array(
			'WIZARDSTEPS' => '',
			'FLASHMESSAGES' => '',
			'NAVIGATION' => '',
			'TITLE' => ''
		);

		if ($this->CMD == 'delete') {
			$this->deleteDMail(intval(t3lib_div::_GP('uid')));
		}

		$row = array();
		if (intval($this->sys_dmail_uid)) {
			$row = t3lib_BEfunc::getRecord('sys_dmail', intval($this->sys_dmail_uid));
			$isExternalDirectMailRecord = (is_array($row) && $row['type'] == 1);
		}

		$hideCategoryStep = FALSE;
		if (($GLOBALS['BE_USER']->userTS['tx_directmail.']['hideSteps'] &&
			$GLOBALS['BE_USER']->userTS['tx_directmail.']['hideSteps'] == 'cat') || $isExternalDirectMailRecord) {
			$hideCategoryStep = TRUE;
		}

		if (t3lib_div::_GP('update_cats')) {
			$this->CMD = 'cats';
		}

		if (t3lib_div::_GP('mailingMode_simple')) {
			$this->CMD = 'send_mail_test';
		}

		$backButtonPressed = t3lib_div::_GP('back');
		if ($backButtonPressed) {
				//CMD move 1 step back
			switch (t3lib_div::_GP('currentCMD')) {
				case 'info':
					$this->CMD = '';
				break;

				case 'cats':
					$this->CMD = 'info';
				break;

				case 'send_test':
				case 'send_mail_test':
					if (($this->CMD == 'send_mass') && $hideCategoryStep) {
						$this->CMD = 'info';
					} else {
						$this->CMD = 'cats';
					}
				break;

				case 'send_mail_final':
				case 'send_mass':
					$this->CMD = 'send_test';
				break;
			}
		}

		$nextCMD = "";
		if ($hideCategoryStep) {
			$totalSteps = 4;
			if ($this->CMD == 'info') {
				$nextCMD = 'send_test';
			}
		} else {
			$totalSteps = 5;
			if ($this->CMD == 'info') {
				$nextCMD = 'cats';
			}
		}

		$navigationButtons = "";
		switch ($this->CMD) {
			case 'info':
				$fetchMessage = "";

					// step 2: create the Direct Mail record, or use existing
				$this->currentStep = 2;
				$markers['TITLE'] = $GLOBALS['LANG']->getLL('dmail_wiz2_detail');

				// greyed out next-button if fetching is not successful (on error)
				$fetchError = TRUE;

				// Create DirectMail and fetch the data
				$shouldFetchData = t3lib_div::_GP('fetchAtOnce');

				$quickmail = t3lib_div::_GP('quickmail');

				$createMailFromInternalPage = intval(t3lib_div::_GP('createMailFrom_UID'));
				$createMailFromExternalUrl = t3lib_div::_GP('createMailFrom_URL');

					// internal page
				if ($createMailFromInternalPage && !$quickmail['send']) {
					$newUid = tx_directmail_static::createDirectMailRecordFromPage($createMailFromInternalPage, $this->params);
					if (is_numeric($newUid)) {
						$this->sys_dmail_uid = $newUid;
							// Read new record (necessary because TCEmain sets default field values)
						$row = t3lib_BEfunc::getRecord('sys_dmail', $newUid);
							// fetch the data
						if ($shouldFetchData) {
							$fetchMessage = tx_directmail_static::fetchUrlContentsForDirectMailRecord($row, $this->params);
							$fetchError = ((strstr($fetchMessage, $GLOBALS['LANG']->getLL('dmail_error')) === FALSE) ? FALSE : TRUE);
						}
						$theOutput .= '<input type="hidden" name="CMD" value="' . ($nextCMD ? $nextCMD : 'cats') . '">';
					} else {
							// TODO: Error message - Error while adding the DB set
					}

					// external URL
				} elseif ($createMailFromExternalUrl && !$quickmail['send']) {
						// $createMailFromExternalUrl is the External URL subject
					$htmlUrl = t3lib_div::_GP('createMailFrom_HTMLUrl');
					$plainTextUrl = t3lib_div::_GP('createMailFrom_plainUrl');
					$newUid = tx_directmail_static::createDirectMailRecordFromExternalURL($createMailFromExternalUrl, $htmlUrl, $plainTextUrl, $this->params);
					if (is_numeric($newUid)) {
						$this->sys_dmail_uid = $newUid;
							// Read new record (necessary because TCEmain sets default field values)
						$row = t3lib_BEfunc::getRecord('sys_dmail', $newUid);
							// fetch the data
						if ($shouldFetchData) {
							$fetchMessage = tx_directmail_static::fetchUrlContentsForDirectMailRecord($row, $this->params);
							$fetchError = ((strstr($fetchMessage, $GLOBALS['LANG']->getLL('dmail_error')) === FALSE) ? FALSE : TRUE);
						}
						$theOutput .= '<input type="hidden" name="CMD" value="send_test">';
					} else {
							// TODO: Error message - Error while adding the DB set
						$this->error = 'no_valid_url';
					}

						// Quickmail
				} elseif ($quickmail['send']) {
					$fetchMessage = $this->createDMail_quick($quickmail);
					$fetchError = ((strstr($fetchMessage, $GLOBALS['LANG']->getLL('dmail_error')) === FALSE) ? FALSE : TRUE);
					$row = t3lib_BEfunc::getRecord('sys_dmail',$this->sys_dmail_uid);
					$theOutput.= '<input type="hidden" name="CMD" value="send_test">';
					// existing dmail
				} elseif ($row) {
					if ($row['type'] == '1' && ((empty($row['HTMLParams'])) || (empty($row['plainParams'])))) {

							// it's a quickmail
						$fetchError = FALSE;
						$theOutput .= '<input type="hidden" name="CMD" value="send_test">';

						//add attachment here, since attachment added in 2nd step
						$unserializedMailContent = unserialize(base64_decode($row['mailContent']));
						$theOutput .= $this->compileQuickMail($row, $unserializedMailContent['plain']['content'],false);

					} else {

						if ($shouldFetchData) {
							$fetchMessage = tx_directmail_static::fetchUrlContentsForDirectMailRecord($row, $this->params);
							$fetchError = ((strstr($fetchMessage, $GLOBALS['LANG']->getLL('dmail_error')) === FALSE) ? FALSE : TRUE);
						}

						if ($row['type'] == 0) {
							$theOutput .= '<input type="hidden" name="CMD" value="'.$nextCMD.'">';
						} else {
							$theOutput .= '<input type="hidden" name="CMD" value="send_test">';
						}
					}
				}

				$navigationButtons = '<input type="submit" class="t3-btn-back" value="' . $GLOBALS['LANG']->getLL('dmail_wiz_back') . '" name="back">';
				$navigationButtons.= '<input type="submit" value="'.$GLOBALS['LANG']->getLL('dmail_wiz_next').'" ' . ($fetchError ? 'disabled="disabled" class="next t3-btn-disabled"' : ' class="t3-btn-next"').'>';

				if ($fetchMessage) {
					$markers['FLASHMESSAGES'] = $fetchMessage;
				} elseif (!$fetchError && $shouldFetchData) {
					/** @var $flashMessage t3lib_FlashMessage */
					$flashMessage = t3lib_div::makeInstance('t3lib_FlashMessage',
						'',
						$GLOBALS['LANG']->getLL('dmail_wiz2_fetch_success'),
						t3lib_FlashMessage::OK
					);
					$markers['FLASHMESSAGES'] = $flashMessage->render();
				}

				if (is_array($row)) {
					$theOutput .= '<div id="box-1" class="toggleBox">';
					$theOutput .= $this->renderRecordDetailsTable($row);
					$theOutput .= '</div>';
				}

				$theOutput .= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'">';
				$theOutput .= !empty($row['page'])?'<input type="hidden" name="pages_uid" value="'.$row['page'].'">':'';
				$theOutput .= '<input type="hidden" name="currentCMD" value="'.$this->CMD.'">';
			break;

			case 'cats':
				// shows category if content-based cat
				$this->currentStep = 3;
				$markers['TITLE'] = $GLOBALS['LANG']->getLL('dmail_wiz3_cats');

				$navigationButtons = '<input type="submit" class="t3-btn-back" value="'.$GLOBALS['LANG']->getLL('dmail_wiz_back').'" name="back">';
				$navigationButtons.= '<input type="submit" class="t3-btn-next" value="'.$GLOBALS['LANG']->getLL('dmail_wiz_next').'">';

				$theOutput .= '<div id="box-1" class="toggleBox">';
				$theOutput .= $this->makeCategoriesForm($row);
				$theOutput .= '</div></div>';

				$theOutput .= '<input type="hidden" name="CMD" value="send_test">';
				$theOutput .= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'">';
				$theOutput .= '<input type="hidden" name="pages_uid" value="'.$this->pages_uid.'">';
				$theOutput .= '<input type="hidden" name="currentCMD" value="'.$this->CMD.'">';
			break;

			case 'send_test':
			case 'send_mail_test':
					// send test mail
				$this->currentStep = (4 - (5 - $totalSteps));
				$markers['TITLE'] = $GLOBALS['LANG']->getLL('dmail_wiz4_testmail');

				$navigationButtons = '<input type="submit" class="t3-btn-back" value="'.$GLOBALS['LANG']->getLL('dmail_wiz_back').'" name="back">';
				$navigationButtons.= '<input type="submit" class="t3-btn-next" value="'.$GLOBALS['LANG']->getLL('dmail_wiz_next').'">';

				if ($this->CMD == 'send_mail_test') {
					//using Flashmessages to show sent test mail
					$markers['FLASHMESSAGES'] = $this->cmd_send_mail($row);
				}
				$theOutput .= '<br /><div id="box-1" class="toggleBox">';
				$theOutput .= $this->cmd_testmail();
				$theOutput .= '</div></div>';

				$theOutput .= '<input type="hidden" name="CMD" value="send_mass">';
				$theOutput .= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'">';
				$theOutput .= '<input type="hidden" name="pages_uid" value="'.$this->pages_uid.'">';
				$theOutput .= '<input type="hidden" name="currentCMD" value="'.$this->CMD.'">';
			break;

			case 'send_mail_final':
			case 'send_mass':
				$this->currentStep = (5 - (5 - $totalSteps));

				if ($this->CMD == 'send_mass') {
					$navigationButtons = '<input type="submit" class="t3-btn-back" value="'.$GLOBALS['LANG']->getLL('dmail_wiz_back').'" name="back">';
				}

				if($this->CMD=='send_mail_final'){
					$selectedMailGroups = t3lib_div::_GP('mailgroup_uid');
					if(is_array($selectedMailGroups)){
						$markers['FLASHMESSAGES'] = $this->cmd_send_mail($row);
						break;
					} else {
						$theOutput .= 'no recipients';
					}
				}
				//send mass, show calendar
				$theOutput .= '<div id="box-1" class="toggleBox">';
				$theOutput .= $this->cmd_finalmail();
				$theOutput .= '</div>';

				$theOutput = $this->doc->section($GLOBALS['LANG']->getLL('dmail_wiz5_sendmass'),$theOutput,1,1,0, TRUE);

				$theOutput .= '<input type="hidden" name="CMD" value="send_mail_final">';
				$theOutput .= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'">';
				$theOutput .= '<input type="hidden" name="pages_uid" value="'.$this->pages_uid.'">';
				$theOutput .= '<input type="hidden" name="currentCMD" value="'.$this->CMD.'">';

			break;

			default:
					//choose source newsletter
				$this->currentStep = 1;
				$markers['TITLE'] = $GLOBALS['LANG']->getLL('dmail_wiz1_new_newsletter') . ' - ' . $GLOBALS['LANG']->getLL('dmail_wiz1_select_nl_source');

				$showTabs = array('int','ext','quick','dmail');
				$hideTabs = t3lib_div::trimExplode(',',$GLOBALS['BE_USER']->userTS['tx_directmail.']['hideTabs']);
				foreach ($hideTabs as $hideTab) {
					$showTabs = t3lib_div::removeArrayEntryByValue($showTabs, $hideTab);
				}

				if (!$GLOBALS['BE_USER']->userTS['tx_directmail.']['defaultTab']) {
					$GLOBALS['BE_USER']->userTS['tx_directmail.']['defaultTab'] = 'dmail';
				}

				$i = 1;
				$countTabs = count($showTabs);
				foreach ($showTabs as $showTab) {
					$open = FALSE;
					if ($GLOBALS['BE_USER']->userTS['tx_directmail.']['defaultTab'] == $showTab) {
						$open = TRUE;
					}
					switch ($showTab) {
						case 'int':
							$theOutput .= $this->makeFormInternal('box-'.$i, $countTabs, $open);
							break;
						case 'ext':
							$theOutput .= $this->makeFormExternal('box-'.$i, $countTabs, $open);
							break;
						case 'quick':
							$theOutput .= $this->makeFormQuickMail('box-'.$i, $countTabs, $open);
							break;
						case 'dmail':
							$theOutput .= $this->makeListDMail('box-'.$i, $countTabs, $open);
							break;
						default:
							break;
					}
					$i++;
				}
				$theOutput .= '<input type="hidden" name="CMD" value="info" />';
				break;
		}

		$markers['NAVIGATION'] = $navigationButtons;
		$markers['CONTENT'] = $theOutput;
		$markers['WIZARDSTEPS'] = $this->showSteps($totalSteps);
		return $markers;
	}

	/**
	 * shows the final steps of the process. Show recipient list and calendar library
	 *
	 * @return	string		HTML
	 */
	function cmd_finalmail()	{
		/**
		 * Hook for cmd_finalmail
		 * insert a link to open extended importer
		 */
		$hookSelectDisabled = "";
		$hookContents = "";
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_finalmail'])) {
			$hookObjectsArr = array();
			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_finalmail'] as $classRef) {
				$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
			}
			foreach($hookObjectsArr as $hookObj)    {
				if (method_exists($hookObj, 'cmd_finalmail')) {
					$hookContents = $hookObj->cmd_finalmail($this);
					$hookSelectDisabled = $hookObj->selectDisabled;
				}
			}
		}

			// Mail groups
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'uid,pid,title',
			'sys_dmail_group',
			'pid='.intval($this->id).
				t3lib_BEfunc::deleteClause('sys_dmail_group'),
			'',
			$GLOBALS['TYPO3_DB']->stripOrderBy($GLOBALS['TCA']['sys_dmail_group']['ctrl']['default_sortby'])
		);

		$opt = array();
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {

			$result = $this->cmd_compileMailGroup(array($row['uid']));
			$count = 0;
			$idLists = $result['queryInfo']['id_lists'];
			if (is_array($idLists['tt_address'])) {
				$count += count($idLists['tt_address']);
			}
			if (is_array($idLists['fe_users'])) {
				$count += count($idLists['fe_users']);
			}
			if (is_array($idLists['PLAINLIST'])) {
				$count += count($idLists['PLAINLIST']);
			}
			if (is_array($idLists[$this->userTable])) {
				$count += count($idLists[$this->userTable]);
			}
			$opt[] = '<option value="'.$row['uid'].'">'.htmlspecialchars($row['title'].' (#'.$count.')').'</option>';
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);

		// added disabled. see hook
		$select = '<select multiple="multiple" name="mailgroup_uid[]" '.($hookSelectDisabled ? 'disabled' : '').'>'.implode(chr(10),$opt).'</select>';

			// Set up form:
		$msg = "";
		$msg .= '<input type="hidden" name="id" value="'.$this->id.'" />';
		$msg .= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'" />';
		$msg .= '<input type="hidden" name="CMD" value="send_mail_final" />';
		$msg .= $GLOBALS['LANG']->getLL('schedule_mailgroup') . '<br />'.$select.'<br /><br />';

		// put content from hook
		$msg .= $hookContents;


		$msg .= $GLOBALS['LANG']->getLL('schedule_time') .
			'<br /><input type="text" id="tceforms-datetimefield-startdate" name="send_mail_datetime'.'" '.$GLOBALS['TBE_TEMPLATE']->formWidth(20).' value="'.strftime('%H:%M %d-%m-%Y',time()).'">'.
			t3lib_iconWorks::getSpriteIcon(
				'actions-edit-pick-date',
				array(
					'style' => 'cursor:pointer;',
					'id' => 'picker-tceforms-datetimefield-startdate'
				)
			).
			'<br />';

		$msg .= '<br/><label for="tx-directmail-sendtestmail-check"><input type="checkbox" name="testmail" id="tx-directmail-sendtestmail-check" value="1" />&nbsp;' . $GLOBALS['LANG']->getLL('schedule_testmail') . '</label>';
		$msg .= '<br/><label for="tx-directmail-savedraft-check"><input type="checkbox" name="savedraft" id="tx-directmail-savedraft-check" value="1" />&nbsp;' . $GLOBALS['LANG']->getLL('schedule_draft') . '</label>';
		$msg .= '<br /><br /><input type="Submit" name="mailingMode_mailGroup" value="' . $GLOBALS['LANG']->getLL('schedule_send_all') . '" />';

		$theOutput = $this->doc->section($GLOBALS['LANG']->getLL('schedule_select_mailgroup'),$msg, 1, 1, 0, TRUE);
		$theOutput .= $this->doc->spacer(20);

		$this->noView = 1;
		return $theOutput;
	}

	/**
	 * sending the mail.
	 * if it's a test mail, then will be sent directly.
	 * if it's a mass-send mail, only update the DB record. the dmailer script will send it.
	 *
	 * @param	array		$row: directmal DB record
	 * @return	string		Messages if the mail is sent or planned to sent
	 * @todo	remove htmlmail. sending test mail
	 */
	function cmd_send_mail($row) {
			// Preparing mailer
		/** @var $htmlmail dmailer */
		$htmlmail = t3lib_div::makeInstance('dmailer');
		$htmlmail->nonCron = 1;
		$htmlmail->start();
		$htmlmail->dmailer_prepare($row);

			// send out non-personalized emails
		$simpleMailMode = t3lib_div::_GP('mailingMode_simple');

		$sentFlag = FALSE;
		if ($simpleMailMode) {
			// step 4, sending simple test emails

				// setting Testmail flag
			$htmlmail->testmail = $this->params['testmail'];

				// Fixing addresses:
			$addresses = t3lib_div::_GP('SET');
			$addressList = $addresses['dmail_test_email'] ? $addresses['dmail_test_email'] : $this->MOD_SETTINGS['dmail_test_email'];
			$addresses = preg_split('|['.chr(10).',;]|',$addressList);

			foreach ($addresses as $key => $val) {
				$addresses[$key] = trim($val);
				if (!t3lib_div::validEmail($addresses[$key]))	{
					unset($addresses[$key]);
				}
			}
			$hash = array_flip($addresses);
			$addresses = array_keys($hash);
			$addressList = implode(',', $addresses);

			if ($addressList) {
					// Sending the same mail to lots of recipients
				$htmlmail->dmailer_sendSimple($addressList);
				$sentFlag = TRUE;

				$flashMessage = t3lib_div::makeInstance('t3lib_FlashMessage',
					$GLOBALS['LANG']->getLL('send_was_sent').
						'<br /><br />'.
						$GLOBALS['LANG']->getLL('send_recipients') . '<br />'.htmlspecialchars($addressList), //text
					$GLOBALS['LANG']->getLL('send_sending'), //header
					t3lib_FlashMessage::OK //severity
				);

				$this->noView = 1;
			}
		} elseif ($this->CMD == 'send_mail_test') {
			// step 4, sending test personalized test emails
			// setting Testmail flag
			$htmlmail->testmail = $this->params['testmail'];

			if (t3lib_div::_GP('tt_address_uid'))	{
				//personalized to tt_address
				$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'tt_address.*',
					'tt_address LEFT JOIN pages ON pages.uid=tt_address.pid',
					'tt_address.uid='.intval(t3lib_div::_GP('tt_address_uid')).
						' AND '.$this->perms_clause.
						t3lib_BEfunc::deleteClause('pages').
						t3lib_BEfunc::BEenableFields('tt_address').
						t3lib_BEfunc::deleteClause('tt_address')
					);
				if ($recipRow = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
					$recipRow = dmailer::convertFields($recipRow);
					$recipRow['sys_dmail_categories_list'] = $htmlmail->getListOfRecipentCategories('tt_address',$recipRow['uid']);
					$htmlmail->dmailer_sendAdvanced($recipRow,'t');
					$sentFlag=true;

					$flashMessage = t3lib_div::makeInstance('t3lib_FlashMessage',
						sprintf($GLOBALS['LANG']->getLL('send_was_sent_to_name'), htmlspecialchars($recipRow['name']).htmlspecialchars(' <'.$recipRow['email'].'>')),
						$GLOBALS['LANG']->getLL('send_sending'),
						t3lib_FlashMessage::OK
					);
				}
				$GLOBALS['TYPO3_DB']->sql_free_result($res);

			} elseif (is_array(t3lib_div::_GP('sys_dmail_group_uid'))) {
				// personalized to group
				$result = $this->cmd_compileMailGroup(t3lib_div::_GP('sys_dmail_group_uid'));

				$idLists = $result['queryInfo']['id_lists'];
				$sendFlag = 0;
				$sendFlag += $this->sendTestMailToTable($idLists,'tt_address',$htmlmail);
				$sendFlag += $this->sendTestMailToTable($idLists,'fe_users',$htmlmail);
				$sendFlag += $this->sendTestMailToTable($idLists,'PLAINLIST',$htmlmail);
				$sendFlag += $this->sendTestMailToTable($idLists,$this->userTable,$htmlmail);

				$flashMessage = t3lib_div::makeInstance('t3lib_FlashMessage',
					sprintf($GLOBALS['LANG']->getLL('send_was_sent_to_number'), $sendFlag),
					$GLOBALS['LANG']->getLL('send_sending'),
					t3lib_FlashMessage::OK
				);
			}
		} else {
				// step 5, sending personalized emails to the mailqueue

				// prepare the email for sending with the mailqueue
			$recipientGroups = t3lib_div::_GP('mailgroup_uid');
			if (t3lib_div::_GP('mailingMode_mailGroup') && $this->sys_dmail_uid && is_array($recipientGroups)) {
					// Update the record:
				$result = $this->cmd_compileMailGroup($recipientGroups);
				$queryInfo = $result['queryInfo'];

				$distributionTime = intval(strtotime(t3lib_div::_GP('send_mail_datetime')));
				if ($distributionTime < time()) {
					$distributionTime = time();
				}

				$updateFields = array(
					'scheduled'  => $distributionTime,
					'query_info' => serialize($queryInfo)
				);

				if (t3lib_div::_GP('testmail')) {
					$updateFields['subject'] = $this->params['testmail'] . ' ' . $row['subject'];
				}

					// create a draft version of the record
				if (t3lib_div::_GP('savedraft')) {
					if ($row['type'] == 0) {
						$updateFields['type'] = 2;
					} else {
						$updateFields['type'] = 3;
					}

					$updateFields['scheduled'] = 0;
					$content = $GLOBALS['LANG']->getLL('send_draft_scheduler');
					$sectionTitle = $GLOBALS['LANG']->getLL('send_draft_saved');
				} else {
					$content = $GLOBALS['LANG']->getLL('send_was_scheduled_for') . ' '.t3lib_BEfunc::datetime($distributionTime);
					$sectionTitle = $GLOBALS['LANG']->getLL('send_was_scheduled');
				}
				$sentFlag = TRUE;
				$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
					'sys_dmail',
					'uid=' . intval($this->sys_dmail_uid),
					$updateFields
				);

				$flashMessage = t3lib_div::makeInstance('t3lib_FlashMessage',
					$sectionTitle.'<br /><br />'.$content,
					$GLOBALS['LANG']->getLL('dmail_wiz5_sendmass'),
					t3lib_FlashMessage::OK
				);

			}
		}

			// Setting flags and update the record:
		if ($sentFlag && $this->CMD == 'send_mail_final') {
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				'sys_dmail',
				'uid=' . intval($this->sys_dmail_uid),
				array('issent' => 1)
			);
		}

		/** @var $flashMessage t3lib_FlashMessage */
		return $flashMessage->render();
	}

	/**
	 * send mail to recipient based on table.
	 *
	 * @param	array		$idLists: list of recipient ID
	 * @param	string		$table: table name
	 * @param	dmailer		$htmlmail: object of the dmailer script
	 * @return	integer		total of sent mail
	 * @todo: remove htmlmail. sending mails to table
	 */
	protected function sendTestMailToTable($idLists, $table, $htmlmail) {
		$sentFlag = 0;
		if (is_array($idLists[$table])) {
			if ($table != 'PLAINLIST') {
				$recs = tx_directmail_static::fetchRecordsListValues($idLists[$table], $table, '*');
			} else {
				$recs = $idLists['PLAINLIST'];
			}
			foreach ($recs as $k => $rec) {
				$recipRow = $htmlmail->convertFields($rec);
				$recipRow['sys_dmail_categories_list'] = $htmlmail->getListOfRecipentCategories($table, $recipRow['uid']);
				$kc = substr($table, 0, 1);
				$returnCode = $htmlmail->dmailer_sendAdvanced($recipRow,$kc=='p'?'P':$kc);
				if ($returnCode) {
					$sentFlag++;
				}
			}
		}
		return $sentFlag;
	}

	/**
	 * show the step of sending a test mail
	 *
	 * @return	string		the HTML form
	 */
	function cmd_testmail()	{
		$theOutput = "";

		if ($this->params['test_tt_address_uids'])	{
			$intList = implode(',', t3lib_div::intExplode(',',$this->params['test_tt_address_uids']));
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'tt_address.*',
				'tt_address LEFT JOIN pages ON tt_address.pid=pages.uid',
				'tt_address.uid IN ('.$intList.')'.
					' AND '.$this->perms_clause.
					t3lib_BEfunc::deleteClause('pages').
					t3lib_BEfunc::BEenableFields('tt_address').
					t3lib_BEfunc::deleteClause('tt_address')
				);
			$msg = $GLOBALS['LANG']->getLL('testmail_individual_msg') . '<br /><br />';

			$ids = array();
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$ids[] = $row['uid'];
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($res);
			$msg .= $this->getRecordList(tx_directmail_static::fetchRecordsListValues($ids,'tt_address'),'tt_address', 0, 1, 1);

			$theOutput.= $this->doc->section($GLOBALS['LANG']->getLL('testmail_individual'),$msg, 1, 1, 0, TRUE);
			$theOutput.= $this->doc->spacer(20);
		}

		if ($this->params['test_dmail_group_uids'])	{
			$intList = implode(',', t3lib_div::intExplode(',',$this->params['test_dmail_group_uids']));
			$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
				'sys_dmail_group.*',
				'sys_dmail_group LEFT JOIN pages ON sys_dmail_group.pid=pages.uid',
				'sys_dmail_group.uid IN ('.$intList.')'.
					' AND '.$this->perms_clause.
					t3lib_BEfunc::deleteClause('pages').
					t3lib_BEfunc::deleteClause('sys_dmail_group')
				);
			$msg = $GLOBALS['LANG']->getLL('testmail_mailgroup_msg') . '<br /><br />';
			while ($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res))	{
				$msg.='<a href="index.php?id='.$this->id.'&sys_dmail_uid='.$this->sys_dmail_uid.'&CMD=send_mail_test&sys_dmail_group_uid[]='.$row['uid'].'">'.t3lib_iconWorks::getIconImage('sys_dmail_group', $row, $GLOBALS['BACK_PATH'], 'width="18" height="16" style="vertical-align: top;"').htmlspecialchars($row['title']).'</a><br />';
					// Members:
				$result = $this->cmd_compileMailGroup(array($row['uid']));
				$msg.='<table border="0">
				<tr>
					<td style="width: 50px;"></td>
					<td>'.$this->cmd_displayMailGroup_test($result).'</td>
				</tr>
				</table>';
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($res);

			$theOutput.= $this->doc->section($GLOBALS['LANG']->getLL('testmail_mailgroup'),$msg, 1, 1, 0, TRUE);
			$theOutput.= $this->doc->spacer(20);
		}

		$msg='';
		$msg.= $GLOBALS['LANG']->getLL('testmail_simple_msg') . '<br /><br />';
		$msg.= '<input'.$GLOBALS['TBE_TEMPLATE']->formWidth().' type="text" name="SET[dmail_test_email]" value="'.$this->MOD_SETTINGS['dmail_test_email'].'" /><br /><br />';

		$msg.= '<input type="hidden" name="id" value="'.$this->id.'" />';
		$msg.= '<input type="hidden" name="sys_dmail_uid" value="'.$this->sys_dmail_uid.'" />';
		$msg.= '<input type="hidden" name="CMD" value="send_mail_test" />';
		$msg.= '<input type="submit" name="mailingMode_simple" value="' . $GLOBALS['LANG']->getLL('dmail_send') . '" />';

		$theOutput.= $this->doc->section($GLOBALS['LANG']->getLL('testmail_simple'),$msg, 1, 1, 0, TRUE);

		$this->noView=1;
		return $theOutput;
	}

	/**
	 * display the test mail group, which configured in the configuration module
	 *
	 * @param	array		$result: lists of the recipient IDs based on directmail DB record
	 * @return	string		list of the recipient (in HTML)
	 */
	function cmd_displayMailGroup_test($result)	{
		$idLists = $result['queryInfo']['id_lists'];
		$out='';
		if (is_array($idLists['tt_address'])) {
			$out .= $this->getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['tt_address'],'tt_address'),'tt_address');
		}
		if (is_array($idLists['fe_users'])) {
			$out .= $this->getRecordList(tx_directmail_static::fetchRecordsListValues($idLists['fe_users'],'fe_users'),'fe_users');
		}
		if (is_array($idLists['PLAINLIST'])) {
			$out.=$this->getRecordList($idLists['PLAINLIST'],'default',1);
		}
		if (is_array($idLists[$this->userTable])) {
			$out.=$this->getRecordList(tx_directmail_static::fetchRecordsListValues($idLists[$this->userTable],$this->userTable),$this->userTable);
		}

		return $out;
	}

	/**
	 * show the recipient info and a link to edit it
	 *
	 * @param	array		$listArr: list of recipients ID
	 * @param	string		$table: table name
	 * @param bool|int $dim : if set, icon will be shaded
	 * @param bool|int $editLinkFlag : if set, edit link is showed
	 * @param bool|int $testMailLink : if set, send mail link is showed
	 * @return	string		HTML, the table showing the recipient's info
	 */
	function getRecordList($listArr, $table, $dim=0, $editLinkFlag=1, $testMailLink=0) {
		$count = 0;
		$lines = array();
		$out = '';
		if (is_array($listArr))	{
			$count = count($listArr);
			foreach ($listArr as $row) {
				$tableIcon = '';
				$editLink = '';
				$testLink = "";

				if ($row['uid']) {
					$tableIcon = '<td>'.t3lib_iconWorks::getIconImage($table,array(),$GLOBALS['BACK_PATH'],'title="'.($row['uid']?'uid: '.$row['uid']:'').'"',$dim).'</td>';
					if ($editLinkFlag) {
						$requestURI = t3lib_div::getIndpEnv('REQUEST_URI').'&CMD=send_test&sys_dmail_uid='.$this->sys_dmail_uid.'&pages_uid='.$this->pages_uid;
						$editLink = '<td><a href="#" onClick="'.t3lib_BEfunc::editOnClick('&edit[tt_address]['.$row['uid'].']=edit',$GLOBALS['BACK_PATH'],$requestURI).'">' .
								'<img'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/edit2.gif', 'width="12" height="12"').' alt="' . $GLOBALS['LANG']->getLL('dmail_edit') . '" width="12" height="12" style="margin:0px 5px; vertical-align:top;" title="' . $GLOBALS['LANG']->getLL('dmail_edit') . '" />' .
								'</a></td>';
					}

					if ($testMailLink) {
						$testLink = '<a href="index.php?id='.$this->id.'&sys_dmail_uid='.$this->sys_dmail_uid.'&CMD=send_mail_test&tt_address_uid='.$row['uid'].'">'.htmlspecialchars($row['email']).'</a>';
					} else {
						$testLink = htmlspecialchars($row['email']);
					}
				}

				$lines[]='<tr bgcolor="'.$this->doc->bgColor4.'">
				'.$tableIcon.'
				'.$editLink.'
				<td nowrap> '.$testLink.' </td>
				<td nowrap> '.htmlspecialchars($row['name']).' </td>
				</tr>';
			}
		}
		if (count($lines))	{
			$out= $GLOBALS['LANG']->getLL('dmail_number_records') . '<strong>'.$count.'</strong><br />';
			$out.='<table border="0" cellspacing="1" cellpadding="0">'.implode(chr(10),$lines).'</table>';
		}
		return $out;
	}

	/**
	 * Get the recipient IDs given a list of group IDs
	 *
	 * @param	array		$groups: list of selected group IDs
	 * @return	array		list of the recipient ID
	 */
	protected function cmd_compileMailGroup(array $groups) {
		// If supplied with an empty array, quit instantly as there is nothing to do
		if (!count($groups)){
			return array();
		}

		// Looping through the selected array, in order to fetch recipient details
		$id_lists = array();
		foreach ($groups AS $group) {
			// Testing to see if group ID is a valid integer, if not - skip to next group ID
			if (t3lib_div::compat_version('4.6')) {
				$group = t3lib_utility_Math::convertToPositiveInteger($group);
			} else {
				$group = t3lib_div::intval_positive($group);
			}
			if (!$group) {
				continue;
			}

			$recipientList = $this->getSingleMailGroup($group);
			if (!is_array($recipientList)) {
				continue;
			}

			$id_lists = array_merge_recursive($id_lists, $recipientList);
		}
		// Make unique entries
		if (is_array($id_lists['tt_address']))	$id_lists['tt_address'] = array_unique($id_lists['tt_address']);
		if (is_array($id_lists['fe_users']))	$id_lists['fe_users'] = array_unique($id_lists['fe_users']);
		if (is_array($id_lists[$this->userTable]) && $this->userTable)	$id_lists[$this->userTable] = array_unique($id_lists[$this->userTable]);
		if (is_array($id_lists['PLAINLIST']))	{$id_lists['PLAINLIST'] = tx_directmail_static::cleanPlainList($id_lists['PLAINLIST']);}

		/**
		 * Hook for cmd_compileMailGroup
		 * manipulate the generated id_lists
		 */
		if (is_array ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_compileMailGroup'])) {
			$hookObjectsArr = array();
			$temp_lists = "";

			foreach ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['mod2']['cmd_compileMailGroup'] as $classRef) {
				$hookObjectsArr[] = &t3lib_div::getUserObj($classRef);
			}
			foreach($hookObjectsArr as $hookObj)    {
				if (method_exists($hookObj, 'cmd_compileMailGroup_postProcess')) {
					$temp_lists = $hookObj->cmd_compileMailGroup_postProcess($id_lists, $this, $groups);
				}
			}

			unset ($id_lists);
			$id_lists = $temp_lists;
		}

		return array(
			'queryInfo' => array('id_lists' => $id_lists)
		);
	}

	/**
	 * Fetches recipient IDs from a given group ID
	 *
	 * Most of the functionality from cmd_compileMailGroup in order to use multiple recipient lists when sending
	 *
	 * @param integer		$group_uid: recipient group ID
	 * @return array		list of recipient IDs
	 */
	protected function getSingleMailGroup($group_uid) {
		$id_lists = array();
		if ($group_uid) {
			$mailGroup = t3lib_BEfunc::getRecord('sys_dmail_group',$group_uid);

			if (is_array($mailGroup)) {
				switch($mailGroup['type']) {
				case 0:
					// From pages
					$thePages = $mailGroup['pages'] ? $mailGroup['pages'] : $this->id;		// use current page if no else
					$pages = t3lib_div::intExplode(',',$thePages);	// Explode the pages
					$pageIdArray = array();

					foreach ($pages AS $pageUid) {
						if ($pageUid > 0) {
							$pageinfo = t3lib_BEfunc::readPageAccess($pageUid,$this->perms_clause);
							if (is_array($pageinfo)) {
								$info['fromPages'][] = $pageinfo;
								$pageIdArray[] = $pageUid;
								if ($mailGroup['recursive']) {
									$pageIdArray = array_merge($pageIdArray,tx_directmail_static::getRecursiveSelect($pageUid,$this->perms_clause));
								}
							}
						}
					}
						// Remove any duplicates
					$pageIdArray = array_unique($pageIdArray);
					$pidList = implode(',',$pageIdArray);
					$info['recursive'] = $mailGroup['recursive'];

						// Make queries
					if ($pidList)	{
						$whichTables = intval($mailGroup['whichtables']);
						if ($whichTables&1)	{	// tt_address
							$id_lists['tt_address'] = tx_directmail_static::getIdList('tt_address',$pidList,$group_uid,$mailGroup['select_categories']);
						}
						if ($whichTables&2)	{	// fe_users
							$id_lists['fe_users'] = tx_directmail_static::getIdList('fe_users',$pidList,$group_uid,$mailGroup['select_categories']);
						}
						if ($this->userTable && ($whichTables&4))	{	// user table
							$id_lists[$this->userTable] = tx_directmail_static::getIdList($this->userTable,$pidList,$group_uid,$mailGroup['select_categories']);
						}
						if ($whichTables&8)	{	// fe_groups
							if (!is_array($id_lists['fe_users'])) $id_lists['fe_users'] = array();
							$id_lists['fe_users'] = array_unique(array_merge($id_lists['fe_users'], tx_directmail_static::getIdList('fe_groups',$pidList,$group_uid,$mailGroup['select_categories'])));
						}
					}
					break;
				case 1: // List of mails
					if ($mailGroup['csv']==1)	{
						$recipients = tx_directmail_static::rearrangeCsvValues(tx_directmail_static::getCsvValues($mailGroup['list']), $this->fieldList);
					} else {
						$recipients = tx_directmail_static::rearrangePlainMails(array_unique(preg_split('|[[:space:],;]+|',$mailGroup['list'])));
					}
					$id_lists['PLAINLIST'] = tx_directmail_static::cleanPlainList($recipients);
					break;
				case 2:	// Static MM list
					$id_lists['tt_address'] = tx_directmail_static::getStaticIdList('tt_address',$group_uid);
					$id_lists['fe_users'] = tx_directmail_static::getStaticIdList('fe_users',$group_uid);
					$id_lists['fe_users'] = array_unique(array_merge($id_lists['fe_users'],tx_directmail_static::getStaticIdList('fe_groups',$group_uid)));
					if ($this->userTable)	{
						$id_lists[$this->userTable] = tx_directmail_static::getStaticIdList($this->userTable,$group_uid);
					}
					break;
				case 3:	// Special query list
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
						$queryGenerator = t3lib_div::makeInstance('mailSelect');
						$id_lists[$table] = tx_directmail_static::getSpecialQueryIdList($queryGenerator,$table,$mailGroup);
					}
					break;
				case 4:	//
					$groups = array_unique(tx_directmail_static::getMailGroups($mailGroup['mail_groups'],array($mailGroup['uid']),$this->perms_clause));
					foreach($groups AS $v) {
						$collect = $this->getSingleMailGroup($v);
						if (is_array($collect)) {
							$id_lists = array_merge_recursive($id_lists,$collect);
						}
					}
					break;
				}
			}
		}
		return $id_lists;
	}

	/**
	 * update the mailgroup DB record
	 *
	 * @param	array		$mailGroup: mailgroup DB record
	 * @return	array		mailgroup DB record after updated
	 */
	function update_specialQuery($mailGroup) {
		$set = t3lib_div::_GP('SET');
		$queryTable = $set['queryTable'];
		$queryConfig = t3lib_div::_GP('dmail_queryConfig');
		$dmailUpdateQuery = t3lib_div::_GP('dmailUpdateQuery');

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
			$updateFields = array(
				'whichtables' => intval($whichTables),
				'query' => $this->MOD_SETTINGS['queryConfig']
			);
			$res_update = $GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				"sys_dmail_group",
				"uid = ".intval($mailGroup['uid']),
				$updateFields
			);
			$GLOBALS['TYPO3_DB']->sql_free_result($res_update);

			$mailGroup = t3lib_BEfunc::getRecord('sys_dmail_group',$mailGroup['uid']);
		}
		return $mailGroup;
	}

	/**
	 * show the categories table for user to categorize the directmail content (TYPO3 content)
	 *
	 * @param	array		$row: the dmail row.
	 * @return	string		HTML form showing the categories
	 */
	function makeCategoriesForm($row){
		$indata = t3lib_div::_GP('indata');
		if (is_array($indata['categories']))	{
			$data = array();
			foreach ($indata['categories'] as $recUid => $recValues) {
				$enabled = array();
				foreach ($recValues as $k => $b) {
					if ($b) {
						$enabled[] = $k;
					}
				}
				$data['tt_content'][$recUid]['module_sys_dmail_category'] = implode(',', $enabled);
			}

			/** @var $tce t3lib_TCEmain */
			$tce = t3lib_div::makeInstance('t3lib_TCEmain');
			$tce->stripslashes_values = 0;
			$tce->start($data, array());
			$tce->process_datamap();

			//remove cache
			$tce->clear_cache('pages',$this->pages_uid);
			$out = tx_directmail_static::fetchUrlContentsForDirectMailRecord($row, $this->params);
		}
				//Todo Perhaps we should here check if TV is installed and fetch cotnent from that instead of the old Columns...
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'colPos, CType, uid, pid, header, bodytext, module_sys_dmail_category',
			'tt_content',
			'pid='.intval($this->pages_uid).
				t3lib_BEfunc::deleteClause('tt_content').
				t3lib_BEfunc::BEenableFields('tt_content'),
			'',
			'colPos,sorting'
		);

		if (!$GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			$theOutput = $this->doc->section($GLOBALS['LANG']->getLL('nl_cat'),$GLOBALS['LANG']->getLL('nl_cat_msg1'),1,1,0,TRUE);
		} else {
			$out = '';
			$colPosVal = 99;
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {
				$row_categories = '';
				$resCat = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
					'uid_foreign',
					'sys_dmail_ttcontent_category_mm',
					'uid_local='.$row['uid']
					);
				while($rowCat = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($resCat)) {
					$row_categories .= $rowCat['uid_foreign'].',';
				}
				$GLOBALS['TYPO3_DB']->sql_free_result($resCat);

				$row_categories = rtrim($row_categories,',');

				$out .= '<tr><td colspan="3" style="height: 15px;"></td></tr>';
				if ($colPosVal != $row['colPos'])	{
					$out .= '<tr><td colspan="3" bgcolor="'.$this->doc->bgColor5.'">'.$GLOBALS['LANG']->getLL('nl_l_column').': <strong>'.t3lib_BEfunc::getProcessedValue('tt_content','colPos',$row['colPos']).'</strong></td></tr>';
					$colPosVal = $row["colPos"];
				}
				$out .= '<tr>';
				$out .= '<td valign="top" width="75%">'.t3lib_iconWorks::getIconImage("tt_content", $row, $GLOBALS['BACK_PATH'], 'width="18" height="16" title="'.htmlspecialchars(t3lib_BEfunc::getProcessedValue('tt_content','CType',$row['CType'])).'" style="vertical-align: top;"').
					$row['header'].'<br />'.t3lib_div::fixed_lgd_cs(strip_tags($row['bodytext']),200).'<br /></td>';

				$out .= '<td>  </td><td nowrap valign="top">';
				$out_check = '';
				if ($row['module_sys_dmail_category']) {
					$out_check .= '<font color="red"><strong>'.$GLOBALS['LANG']->getLL('nl_l_ONLY').'</strong></font>';
				} else {
					$out_check .= '<font color="green"><strong>'.$GLOBALS['LANG']->getLL('nl_l_ALL').'</strong></font>';
				}
				$out_check .= '<br />';

				$this->categories = tx_directmail_static::makeCategories('tt_content', $row, $this->sys_language_uid);
				reset($this->categories);
				foreach ($this->categories as $pKey => $pVal) {
					$out_check .= '<input type="hidden" name="indata[categories]['.$row["uid"].']['.$pKey.']" value="0"><input type="checkbox" name="indata[categories]['.$row['uid'].']['.$pKey.']" value="1"'.(t3lib_div::inList($row_categories,$pKey) ?' checked':'').'> '.htmlspecialchars($pVal).'<br />';
				}
				$out .= $out_check.'</td></tr>';
			}
			$GLOBALS['TYPO3_DB']->sql_free_result($res);

			$out = '<table border="0" cellpadding="0" cellspacing="0">'.$out.'</table>';
			$out .= '<input type="hidden" name="pages_uid" value="'.$this->pages_uid.'"><input type="hidden" name="CMD" value="'.$this->CMD.'"><br /><input type="submit" name="update_cats" value="'.$GLOBALS['LANG']->getLL('nl_l_update').'">';
			$theOutput = $this->doc->section($GLOBALS['LANG']->getLL('nl_cat').t3lib_BEfunc::cshItem($this->cshTable,'assign_categories',$GLOBALS['BACK_PATH']), $out, 1, 1, 0, TRUE);
		}
		return $theOutput;
	}

	/**
	 * makes box for internal page. (first step)
	 *
	 * @param	string		$boxID: ID name for the HTML element
	 * @param	integer		$totalBox: total of all boxes
	 * @param	bool		$open: state of the box
	 * @return	string		HTML with list of internal pages
	 */
	function makeFormInternal($boxID, $totalBox, $open=FALSE){
		$imgSrc = t3lib_iconWorks::skinImg(
			$GLOBALS['BACK_PATH'],
			'gfx/button_'. ($open?'down':'right') .'.gif'
		);

		$output = '<div class="box"><div class="toggleTitle">';
		$output .= '<a href="#" onclick="toggleDisplay(\''.$boxID.'\', event, '.$totalBox.')"><img id="'.$boxID.'_toggle" '.$imgSrc.' alt="" >'.$GLOBALS['LANG']->getLL('dmail_wiz1_internal_page').'</a>';
		$output .= '</div><div id="'.$boxID.'" class="toggleBox" style="display:'. ($open?'block':'none') .'">';
		$output .= $this->cmd_news();
		$output .= '</div></div></div>';
		return $output;
	}

	/**
	 * make input form for external URL (first step)
	 *
	 * @param	string		$boxID: ID name for the HTML element
	 * @param	integer		$totalBox:total of the boxes
	 * @param	bool		$open: state of the box
	 * @return	string		HTML input form for inputing the external page information
	 */
	function makeFormExternal($boxID, $totalBox, $open=FALSE){
		$imgSrc = t3lib_iconWorks::skinImg(
			$GLOBALS['BACK_PATH'],
			'gfx/button_'. ($open?'down':'right') .'.gif'
		);

		$output = '<div class="box"><div class="toggleTitle">';
		$output .= '<a href="#" onclick="toggleDisplay(\''.$boxID.'\', event, '.$totalBox.')"><img id="'.$boxID.'_toggle" '.$imgSrc.' alt="" >'.$GLOBALS['LANG']->getLL('dmail_wiz1_external_page').'</a>';
		$output .= '</div><div id="'.$boxID.'" class="toggleBox" style="display:'. ($open?'block':'none') .'">';

			// Create
		$out = $GLOBALS['LANG']->getLL('dmail_HTML_url') . '<br />
				<input type="text" value="http://" name="createMailFrom_HTMLUrl"'.$GLOBALS['TBE_TEMPLATE']->formWidth(40).' /><br />' .
				$GLOBALS['LANG']->getLL('dmail_plaintext_url') . '<br />
				<input type="text" value="http://" name="createMailFrom_plainUrl"'.$GLOBALS['TBE_TEMPLATE']->formWidth(40).' /><br />' .
				$GLOBALS['LANG']->getLL('dmail_subject') . '<br />' .
				'<input type="text" value="' . $GLOBALS['LANG']->getLL('dmail_write_subject') . '" name="createMailFrom_URL" onFocus="this.value=\'\';"'.$GLOBALS['TBE_TEMPLATE']->formWidth(40).' /><br />' .
				(($this->error == 'no_valid_url')?('<br /><b>'.$GLOBALS['LANG']->getLL('dmail_no_valid_url').'</b><br /><br />'):'') .
				'<input type="submit" value="'.$GLOBALS['LANG']->getLL("dmail_createMail").'" />
				<input type="hidden" name="fetchAtOnce" value="1">';
		$output.= '<h3>'.$GLOBALS['LANG']->getLL('dmail_dovsk_crFromUrl').t3lib_BEfunc::cshItem($this->cshTable,'create_directmail_from_url',$GLOBALS['BACK_PATH']).'</h3>';
		$output.= $out;

		$output.= '</div></div>';
		return $output;
	}

	/**
	 * makes input form for the quickmail (first step)
	 *
	 * @param	string		$boxID: ID name for the HTML element
	 * @param	integer		$totalBox: total of the boxes
	 * @param	bool		$open: state of the box
	 * @return	string		HTML input form for the quickmail
	 */
	function makeFormQuickMail($boxID, $totalBox, $open=FALSE){
		$imgSrc = t3lib_iconWorks::skinImg(
			$GLOBALS['BACK_PATH'],
			'gfx/button_'. ($open?'down':'right') .'.gif'
		);

		$output = '<div class="box"><div class="toggleTitle">';
		$output.= '<a href="#" onclick="toggleDisplay(\''.$boxID.'\', event, '.$totalBox.')"><img id="'.$boxID.'_toggle" '.$imgSrc.' alt="" >'.$GLOBALS['LANG']->getLL('dmail_wiz1_quickmail').'</a>';
		$output.= '</div><div id="'.$boxID.'" class="toggleBox" style="display:'. ($open?'block':'none') .'">';
		$output.= '<h3>'.$GLOBALS['LANG']->getLL('dmail_wiz1_quickmail_header').'</h3>';
		$output.= $this->cmd_quickmail();
		$output.= '</div></div>';
		return $output;
	}

	/**
	 * list all direct mail, which have not been sent (first step)
	 *
	 * @param	string		$boxID: ID name for the HTML element
	 * @param	integer		$totalBox: total of the boxes
	 * @param	bool		$open: state of the box
	 * @return	string		HTML lists of all existing dmail records
	 */
	function makeListDMail($boxID, $totalBox, $open=FALSE){
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'uid,pid,subject,tstamp,issent,renderedsize,attachment,type',
			'sys_dmail',
			'pid = '.intval($this->id).
				' AND scheduled=0 AND issent=0'.t3lib_BEfunc::deleteClause('sys_dmail'),
			'',
			$GLOBALS['TYPO3_DB']->stripOrderBy($GLOBALS['TCA']['sys_dmail']['ctrl']['default_sortby'])
		);

		$tblLines = array();
		$tblLines[] = array(
			'',
			$GLOBALS['LANG']->getLL('nl_l_subject'),
			$GLOBALS['LANG']->getLL('nl_l_lastM'),
			$GLOBALS['LANG']->getLL('nl_l_sent'),
			$GLOBALS['LANG']->getLL('nl_l_size'),
			$GLOBALS['LANG']->getLL('nl_l_attach'),
			$GLOBALS['LANG']->getLL('nl_l_type'),
			''
		);
		while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)){
			$tblLines[] = array(
				t3lib_iconWorks::getIconImage('sys_dmail',$row, $GLOBALS['BACK_PATH'], ' style="vertical-align: top;"'),
				$this->linkDMail_record($row['subject'],$row['uid']),
				t3lib_BEfunc::date($row['tstamp']),
				($row['issent'] ? $GLOBALS['LANG']->getLL('dmail_yes') : $GLOBALS['LANG']->getLL('dmail_no')),
				($row['renderedsize'] ? t3lib_div::formatSize($row['renderedsize']) : ''),
				($row['attachment'] ? '<img '.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], t3lib_extMgm::extRelPath($this->extKey).'res/gfx/attach.gif', 'width="9" height="13"').' alt="'.htmlspecialchars($GLOBALS['LANG']->getLL('nl_l_attach')).'" title="'.htmlspecialchars($row['attachment']).'" width="9" height="13">' : ''),
				($row['type'] ? $GLOBALS['LANG']->getLL('nl_l_tUrl') : $GLOBALS['LANG']->getLL('nl_l_tPage')),
				$this->deleteLink($row['uid'])
			);
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);

		$imgSrc = t3lib_iconWorks::skinImg(
			$GLOBALS['BACK_PATH'],
			'gfx/button_'. ($open?'down':'right') .'.gif'
		);

		$output = '<div id="header" class="box"><div class="toggleTitle">';
		$output.= '<a href="#" onclick="toggleDisplay(\''.$boxID.'\', event, '.$totalBox.')"><img id="'.$boxID.'_toggle" '.$imgSrc.' alt="" >'.$GLOBALS['LANG']->getLL('dmail_wiz1_list_dmail').'</a>';
		$output.= '</div><div id="'.$boxID.'" class="toggleBox" style="display:'. ($open?'block':'none') .'">';
		$output.= '<h3>'.$GLOBALS['LANG']->getLL('dmail_wiz1_list_header').'</h3>';
		$output.= tx_directmail_static::formatTable($tblLines, array(), 1, array(1,1,1,0,0,1,0,1), 'border="0" cellspacing="0" cellpadding="3"');
		$output.= '</div></div>';
		return $output;
	}

	/**
	 * show the quickmail input form (first step)
	 *
	 * @return	string		HTML input form
	 */
	function cmd_quickmail()	{
		$theOutput='';
		$indata = t3lib_div::_GP('quickmail');

		$senderName = ($indata['senderName']?$indata['senderName']:$GLOBALS['BE_USER']->user['realName']);
		$senderMail = ($indata['senderEmail']?$indata['senderEmail']:$GLOBALS['BE_USER']->user['email']);
			// Set up form:
		$theOutput.= '<input type="hidden" name="id" value="'.$this->id.'" />';
		$theOutput.= $GLOBALS['LANG']->getLL('quickmail_sender_name') . '<br /><input type="text" name="quickmail[senderName]" value="'.htmlspecialchars($senderName).'"'.$this->doc->formWidth().' /><br />';
		$theOutput.= $GLOBALS['LANG']->getLL('quickmail_sender_email') . '<br /><input type="text" name="quickmail[senderEmail]" value="'.htmlspecialchars($senderMail).'"'.$this->doc->formWidth().' /><br />';
		$theOutput.= $GLOBALS['LANG']->getLL('dmail_subject') . '<br /><input type="text" name="quickmail[subject]" value="'.htmlspecialchars($indata['subject']).'"'.$this->doc->formWidth().' /><br />';
		$theOutput.= $GLOBALS['LANG']->getLL('quickmail_message') . '<br /><textarea rows="20" name="quickmail[message]"'.$this->doc->formWidthText().'>'.t3lib_div::formatForTextarea($indata['message']).'</textarea><br />';
		$theOutput.= $GLOBALS['LANG']->getLL('quickmail_break_lines') . ' <input type="checkbox" name="quickmail[breakLines]" value="1"'.($indata['breakLines']?' checked="checked"':'').' /><br /><br />';
		$theOutput.= '<input type="Submit" name="quickmail[send]" value="' . $GLOBALS['LANG']->getLL('dmail_wiz_next') . '" />';

		return $theOutput;
	}

	/**
	 * show the list of existing directmail records, which haven't been sent
	 *
	 * @return	string		HTML
	 */
	function cmd_news () {
			// Here the list of subpages, news, is rendered
		$res = $GLOBALS['TYPO3_DB']->exec_SELECTquery(
			'uid,doktype,title,abstract',
			'pages',
			'pid='.intval($this->id).
				' AND doktype IN ('.$GLOBALS['TYPO3_CONF_VARS']['FE']['content_doktypes'].')'.
				' AND '.$this->perms_clause.
				t3lib_BEfunc::BEenableFields('pages').
				t3lib_BEfunc::deleteClause('pages'),
			'',
			'sorting'
			);
		if (!$GLOBALS['TYPO3_DB']->sql_num_rows($res)) {
			$theOutput = $this->doc->section($GLOBALS['LANG']->getLL('nl_select'),$GLOBALS['LANG']->getLL('nl_select_msg1'),0,1);
		} else {
			$outLines = array();
			while($row = $GLOBALS['TYPO3_DB']->sql_fetch_assoc($res)) {

				$iconPreviewHTML = '<a href="#" onClick="'.t3lib_BEfunc::viewOnClick($row['uid'],$GLOBALS['BACK_PATH'],t3lib_BEfunc::BEgetRootLine($row['uid']),'','',$this->implodedParams['HTMLParams']).'"><img src="../res/gfx/preview_html.gif" width="16" height="16" alt="" style="vertical-align:top;" title="'.$GLOBALS['LANG']->getLL('nl_viewPage_HTML').'"/></a>';
				$iconPreviewText = '<a href="#" onClick="'.t3lib_BEfunc::viewOnClick($row['uid'],$GLOBALS['BACK_PATH'],t3lib_BEfunc::BEgetRootLine($row['uid']),'','',$this->implodedParams['plainParams']).'"><img src="../res/gfx/preview_txt.gif" width="16" height="16" alt="" style="vertical-align:top;" title="'.$GLOBALS['LANG']->getLL('nl_viewPage_TXT').'"/></a>';

				//switch
				switch ($this->params['sendOptions']) {
					case 1:
						$iconPreview = $iconPreviewText;
						break;
					case 2:
						$iconPreview = $iconPreviewHTML;
						break;
					case 3:
					default:
						$iconPreview = $iconPreviewHTML.'&nbsp;&nbsp;'.$iconPreviewText;
					break;
				}

				$createDmailLink = 'index.php?id='.$this->id.'&createMailFrom_UID='.$row['uid'].'&fetchAtOnce=1&CMD=info';

				if (t3lib_div::compat_version('4.4')) {
					//new
					$pageIcon = t3lib_iconWorks::getSpriteIconForRecord('pages', $row).htmlspecialchars($row['title']);
				} else {
					//old
					$pageIcon = t3lib_iconWorks::getIconImage('pages', $row, $GLOBALS['BACK_PATH'], ' title="'.htmlspecialchars(t3lib_BEfunc::getRecordPath ($row['uid'],$this->perms_clause,20)).'" style="vertical-align: top;"').htmlspecialchars($row['title']);
				}

				$outLines[] = array(
					'<a href="'.$createDmailLink.'">'.$pageIcon.'</a>',
					'<a href="'.$createDmailLink.'"><img'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/newmail', 'width="16" height="18"').' alt="'.$GLOBALS['LANG']->getLL("dmail_createMail").'" style="vertical-align:top;" title="'.$GLOBALS['LANG']->getLL("nl_create").'" /></a>',
					'<a onclick="'.htmlspecialchars(t3lib_BEfunc::editOnClick('&edit[pages]['.$row['uid'].']=edit',$this->doc->backPath)).'" href="#"><img'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$GLOBALS['LANG']->getLL("dmail_edit").'" style="vertical-align:top;" title="'.$GLOBALS['LANG']->getLL("nl_editPage").'" /></a>',
					$iconPreview
					);
			}
			$out = tx_directmail_static::formatTable($outLines, array(), 0, array(1,1,1,1), 'border="0" cellspacing="1" cellpadding="0"');
			$theOutput = $this->doc->section($GLOBALS['LANG']->getLL('dmail_dovsk_crFromNL').t3lib_BEfunc::cshItem($this->cshTable,'select_newsletter',$GLOBALS['BACK_PATH']), $out, 1, 1, 0, TRUE);
		}
		$GLOBALS['TYPO3_DB']->sql_free_result($res);

		return $theOutput;
	}

	/**
	 * wrap a string as a link
	 *
	 * @param	string		$str: String to be linked
	 * @param	integer		$uid: UID of the directmail record
	 * @return	string		the link
	 */
	function linkDMail_record($str,$uid)	{
		return '<a class="t3-link" href="index.php?id='.$this->id.'&sys_dmail_uid='.$uid.'&CMD=info&fetchAtOnce=1">'.htmlspecialchars($str).'</a>';
	}


	/**
	 * shows the infos of a directmail
	 * record in a table
	 *
	 * @param	array	$row: DirectMail DB record
	 * @return	string	the HTML output
	 */
	protected function renderRecordDetailsTable($row) {
		if (!$row['issent']) {
			if ($GLOBALS['BE_USER']->check('tables_modify','sys_dmail')) {
				$retUrl = 'returnUrl='.rawurlencode(t3lib_div::linkThisScript(array('sys_dmail_uid' => $row['uid'], 'createMailFrom_UID' => '', 'createMailFrom_URL' => '')));
				$Eparams='&edit[sys_dmail]['.$row['uid'].']=edit';
				$editOnClick = 'document.location=\''.$GLOBALS['BACK_PATH'].'alt_doc.php?'.$retUrl.$Eparams.'\'; return false;';
				$content = '<a href="#" onClick="' .$editOnClick . '"><img'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$GLOBALS['LANG']->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$GLOBALS['LANG']->getLL("dmail_edit").'" />'.'<b>'.$GLOBALS['LANG']->getLL('dmail_edit').'</b>'.'</a>';
			} else {
				$content = '<img'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$GLOBALS['LANG']->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$GLOBALS['LANG']->getLL("dmail_edit").'" />'.'('.$GLOBALS['LANG']->getLL('dmail_noEdit_noPerms').')';
			}
		} else {
			$content = '<img'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/edit2.gif', 'width="12" height="12"').' alt="'.$GLOBALS['LANG']->getLL("dmail_edit").'" width="12" height="12" style="margin: 2px 3px; vertical-align:top;" title="'.$GLOBALS['LANG']->getLL("dmail_edit").'" />'.'('.$GLOBALS['LANG']->getLL('dmail_noEdit_isSent').')';
		}

		$content = '<tr>
			<td class="t3-row-header">' . tx_directmail_static::fName('subject') . ' <b>' . t3lib_div::fixed_lgd_cs(htmlspecialchars($row['subject']), 60) . '</b></td>
			<td class="t3-row-header" style="text-align: right;">' . $content . '</td>
		</tr>';

		$nameArr = explode(',','from_name,from_email,replyto_name,replyto_email,organisation,return_path,priority,attachment,type,page,sendOptions,includeMedia,flowedFormat,plainParams,HTMLParams,encoding,charset,issent,renderedsize');
		foreach ($nameArr as $name) {
			$content .= '
			<tr>
				<td>' . tx_directmail_static::fName($name) . '</td>
				<td>' . htmlspecialchars(t3lib_BEfunc::getProcessedValue('sys_dmail', $name, $row[$name])) . '</td>
			</tr>';
		}
		$content = '<table border="0" cellpadding="1" cellspacing="1" width="460" class="typo3-dblist">' . $content . '</table>';

		$sectionTitle = t3lib_iconWorks::getIconImage('sys_dmail', $row, $GLOBALS['BACK_PATH'], 'style="vertical-align: top;"') . '&nbsp;' . htmlspecialchars($row['subject']);
		return $this->doc->section($sectionTitle, $content, 1, 1, 0, TRUE);
	}

	/**
	 * create delete link with trash icon
	 *
	 * @param	int		$uid: uid of the record
	 * @return	string	link with the trash icon
	 */
	function deleteLink($uid) {
		$icon = '<img'.t3lib_iconWorks::skinImg($GLOBALS['BACK_PATH'], 'gfx/delete_record.gif').' />';
		$dmail = t3lib_BEfunc::getRecord('sys_dmail', $uid);
		if (!$dmail['scheduled_begin']) {
			return '<a href="index.php?id='.$this->id.'&CMD=delete&uid='.$uid.'">'.$icon.'</a>';
		} else {
			return '';
		}
	}

	/**
	 * delete existing dmail record
	 *
	 * @param int $uid: record uid to be deleted
	 * @return void
	 */
	function deleteDMail($uid) {
		$table = 'sys_dmail';
		if ($GLOBALS['TCA'][$table]['ctrl']['delete']) {
			$GLOBALS['TYPO3_DB']->exec_UPDATEquery(
				$table,
				'uid = '.$uid,
				array($GLOBALS['TCA'][$table]['ctrl']['delete'] => 1)
			);
		}

		return;
	}
}

if (defined('TYPO3_MODE') && $TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod2/class.tx_directmail_dmail.php'])	{
	include_once($TYPO3_CONF_VARS[TYPO3_MODE]['XCLASS']['ext/direct_mail/mod2/class.tx_directmail_dmail.php']);
}

?>