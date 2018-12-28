<?php
namespace DirectMailTeam\DirectMail;

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

use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Class, doing the sending of Direct-mails, eg. through a cron-job
 *
 * @author		Kasper Skaarhoj <kasperYYYY@typo3.com>
 * @author      Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 */
class Dmailer
{

    /*
     * @var int amount of mail sent in one batch
     */
    public $sendPerCycle = 50;

    public $logArray = array();
    public $massend_id_lists = array();
    public $mailHasContent;
    public $flag_html = 0;
    public $flag_plain = 0;
    public $includeMedia = 0;
    public $flowedFormat = 0;
    public $user_dmailerLang = 'en';
    public $mailObject = null;
    public $testmail = false;

    /*
     * @var string
     * Todo: need this in swift?
     */
    public $charset = '';

    /*
     * @var string
     * Todo: need this in swift?
     */
    public $encoding = '';

    /*
     * @var array the mail parts (HTML and Plain, incl. href and link to media)
     */
    public $theParts = array();

    /*
     * @var string the mail message ID
     * todo: do we still need this
     */
    public $messageid = '';

    /*
     * @var string the subject of the mail
     */
    public $subject = '';

    /*
     * @var string the sender mail
     */
    public $from_email = '';

    /*
     * @var string the sender's name
     */
    public $from_name = '';

    /*
     * @var string organisation of the mail
     */
    public $organisation = '';

    /*
     * special header to identify returned mail
     *
     * @var string
     */
    public $TYPO3MID;

    public $replyto_email = '';
    public $replyto_name = '';
    public $priority = 0;
    public $mailer;
    public $authCode_fieldList;
    public $dmailer;
    public $mediaList;

    public $tempFileList = array();

    //in TYPO3 9 LanguageService->charset has been removed because backend charset is always utf-8
    protected $backendCharset= 'utf-8';

    /*
     * @var integer Usergroup that is simulated when fetching the mail content
     */
    public $simulateUsergroup;

    /**
     * @var CharsetConverter
     */
    protected $charsetConverter;

    /**
     * @var MarkerBasedTemplateService
     */
    protected $templateService;

    protected function getCharsetConverter()
    {
        if ($this->charsetConverter && ($this->charsetConverter instanceof CharsetConverter)) {
            // charsetConverter is set already
        } else
        {
            $this->charsetConverter = GeneralUtility::makeInstance(CharsetConverter::class);
        }
        return $this->charsetConverter;
    }

    /**
     * Preparing the Email. Headers are set in global variables
     *
     * @param array $row Record from the sys_dmail table
     *
     * @return void
     */
    public function dmailer_prepare(array $row)
    {
        global $LANG;

        $sys_dmail_uid = $row['uid'];
        if ($row['flowedFormat']) {
            $this->flowedFormat = 1;
        }
        if ($row['charset']) {
            if ($row['type'] == 0) {
                $this->charset = 'utf-8';
            } else {
                $this->charset = $row['charset'];
            }
        }

        $this->encoding = $row['encoding'];

        $this->theParts  = unserialize(base64_decode($row['mailContent']));
        $this->messageid = $this->theParts['messageid'];

        $this->subject = $this->getCharsetConverter()->conv($row['subject'], $this->backendCharset, $this->charset);

        $this->from_email = $row['from_email'];
        $this->from_name = ($row['from_name'] ? $this->getCharsetConverter()->conv($row['from_name'], $this->backendCharset, $this->charset) : '');

        $this->replyto_email = ($row['replyto_email'] ? $row['replyto_email'] : '');
        $this->replyto_name  = ($row['replyto_name'] ? $this->getCharsetConverter()->conv($row['replyto_name'], $this->backendCharset, $this->charset) : '');

        $this->organisation  = ($row['organisation'] ? $this->getCharsetConverter()->conv($row['organisation'], $this->backendCharset, $this->charset) : '');

        $this->priority      = DirectMailUtility::intInRangeWrapper($row['priority'], 1, 5);
        $this->mailer        = 'TYPO3 Direct Mail module';
        $this->authCode_fieldList = ($row['authcode_fieldList'] ? $row['authcode_fieldList'] : 'uid');

        $this->dmailer['sectionBoundary'] = '<!--DMAILER_SECTION_BOUNDARY';
        $this->dmailer['html_content']    =  $this->theParts['html']['content'];
        $this->dmailer['plain_content']   = $this->theParts['plain']['content'];
        $this->dmailer['messageID']       = $this->messageid;
        $this->dmailer['sys_dmail_uid']   = $sys_dmail_uid;
        $this->dmailer['sys_dmail_rec']   = $row;

        $this->dmailer['boundaryParts_html'] = explode($this->dmailer['sectionBoundary'], '_END-->' . $this->dmailer['html_content']);
        foreach ($this->dmailer['boundaryParts_html'] as $bKey => $bContent) {
            $this->dmailer['boundaryParts_html'][$bKey] = explode('-->', $bContent, 2);

            // Remove useless HTML comments
            if (substr($this->dmailer['boundaryParts_html'][$bKey][0], 1) == 'END') {
                $this->dmailer['boundaryParts_html'][$bKey][1] = $this->removeHTMLComments($this->dmailer['boundaryParts_html'][$bKey][1]);
            }

            // Now, analyzing which media files are used in this part of the mail:
            $mediaParts = explode('cid:part', $this->dmailer['boundaryParts_html'][$bKey][1]);
            reset($mediaParts);
            next($mediaParts);
            foreach ($mediaParts as $part) {
                $this->dmailer['boundaryParts_html'][$bKey]['mediaList'] .= ',' . strtok($part, '.');
            }
        }
        $this->dmailer['boundaryParts_plain'] = explode($this->dmailer['sectionBoundary'], '_END-->' . $this->dmailer['plain_content']);
        foreach ($this->dmailer['boundaryParts_plain'] as $bKey => $bContent) {
            $this->dmailer['boundaryParts_plain'][$bKey] = explode('-->', $bContent, 2);
        }

        $this->flag_html    = ($this->theParts['html']['content']  ? 1 : 0);
        $this->flag_plain   = ($this->theParts['plain']['content'] ? 1 : 0);
        $this->includeMedia = $row['includeMedia'];
    }

    /**
     * Removes html comments when outside script and style pairs
     *
     * @param string $content The email content
     *
     * @return string HTML content without comments
     */
    public function removeHTMLComments($content)
    {
        $content = preg_replace('/\/\*<!\[CDATA\[\*\/[\t\v\n\r\f]*<!--/', '/*<![CDATA[*/', $content);
        $content = preg_replace('/[\t\v\n\r\f]*<!(?:--[^\[][\s\S]*?--\s*)?>[\t\v\n\r\f]*/', '', $content);
        return preg_replace('/\/\*<!\[CDATA\[\*\//', '/*<![CDATA[*/<!--', $content);
    }


    /**
     * Replace the marker with recipient data and then send it
     *
     * @param string $content The HTML or plaintext part
     * @param array $recipRow Recipient's data array
     * @param array $markers Existing markers that are mail-specific, not user-specific
     *
     * @return int Which kind of email is sent, 1 = HTML, 2 = plain, 3 = both
     */
    public function replaceMailMarkers($content, array $recipRow, array $markers)
    {
        // replace %23%23%23 with ###, since typolink generated link with urlencode
        $content = str_replace('%23%23%23', '###', $content);

        $rowFieldsArray = GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['defaultRecipFields']);
        if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['addRecipFields']) {
            $rowFieldsArray = array_merge($rowFieldsArray, GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['addRecipFields']));
        }

        foreach ($rowFieldsArray as $substField) {
            $subst = $this->getCharsetConverter()->conv($recipRow[$substField], $this->backendCharset, $this->charset);
            $markers['###USER_' . $substField . '###'] = $subst;
        }

        // uppercase fields with uppercased values
        $uppercaseFieldsArray = array('name', 'firstname');
        foreach ($uppercaseFieldsArray as $substField) {
            $subst = $this->getCharsetConverter()->conv($recipRow[$substField], $this->backendCharset, $this->charset);
            $markers['###USER_' . strtoupper($substField) . '###'] = strtoupper($subst);
        }

        // Hook allows to manipulate the markers to add salutation etc.
        if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailMarkersHook'])) {
            $mailMarkersHook =& $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailMarkersHook'];
            if (is_array($mailMarkersHook)) {
                $hookParameters = array(
                    'row'     => &$recipRow,
                    'markers' => &$markers,
                );
                $hookReference = &$this;
                foreach ($mailMarkersHook as $hookFunction) {
                    GeneralUtility::callUserFunction($hookFunction, $hookParameters, $hookReference);
                }
            }
        }

        // initialize Marker Support
        $this->templateService = GeneralUtility::makeInstance(MarkerBasedTemplateService::class);
        return $this->templateService->substituteMarkerArray($content, $markers);
    }


    /**
     * Replace the marker with recipient data and then send it
     *
     * @param array $recipRow Recipient's data array
     * @param string $tableNameChar Tablename, from which the recipient come from
     *
     * @return int Which kind of email is sent, 1 = HTML, 2 = plain, 3 = both
     */
    public function dmailer_sendAdvanced(array $recipRow, $tableNameChar)
    {
        $returnCode = 0;
        $tempRow = array();

        // check recipRow for HTML
        foreach ($recipRow as $k => $v) {
            $tempRow[$k] = htmlspecialchars($v);
        }
        unset($recipRow);
        $recipRow = $tempRow;

        // Workaround for strict checking of email addresses in TYPO3
        // (trailing newline = invalid address)
        $recipRow['email'] = trim($recipRow['email']);

        if ($recipRow['email']) {
            $midRidId  = 'MID' . $this->dmailer['sys_dmail_uid'] . '_' . $tableNameChar . $recipRow['uid'];
            $uniqMsgId = md5(microtime()) . '_' . $midRidId;
            $authCode = GeneralUtility::stdAuthCode($recipRow, $this->authCode_fieldList);

            $additionalMarkers = array(
                    // Put in the tablename of the userinformation
                '###SYS_TABLE_NAME###'      => $tableNameChar,
                    // Put in the uid of the mail-record
                '###SYS_MAIL_ID###'         => $this->dmailer['sys_dmail_uid'],
                '###SYS_AUTHCODE###'        => $authCode,
                    // Put in the unique message id in HTML-code
                $this->dmailer['messageID'] => $uniqMsgId,
            );

            $this->mediaList = '';
            $this->theParts['html']['content'] = '';
            if ($this->flag_html && ($recipRow['module_sys_dmail_html'] || $tableNameChar == 'P')) {
                $tempContent_HTML = $this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_html'], $recipRow['sys_dmail_categories_list']);
                if ($this->mailHasContent) {
                    $tempContent_HTML = $this->replaceMailMarkers($tempContent_HTML, $recipRow, $additionalMarkers);
                    $this->theParts['html']['content'] = $this->encodeMsg($tempContent_HTML);
                    $returnCode|=1;
                }
            }

            // Plain
            $this->theParts['plain']['content'] = '';
            if ($this->flag_plain) {
                $tempContent_Plain = $this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_plain'], $recipRow['sys_dmail_categories_list']);
                if ($this->mailHasContent) {
                    $tempContent_Plain = $this->replaceMailMarkers($tempContent_Plain, $recipRow, $additionalMarkers);
                    if (trim($this->dmailer['sys_dmail_rec']['use_rdct']) || trim($this->dmailer['sys_dmail_rec']['long_link_mode'])) {
                        $tempContent_Plain = DirectMailUtility::substUrlsInPlainText($tempContent_Plain, $this->dmailer['sys_dmail_rec']['long_link_mode']?'all':'76', $this->dmailer['sys_dmail_rec']['long_link_rdct_url']);
                    }
                    $this->theParts['plain']['content'] = $this->encodeMsg($tempContent_Plain);
                    $returnCode|=2;
                }
            }

            $this->TYPO3MID = $midRidId . '-' . md5($midRidId);
            $this->dmailer['sys_dmail_rec']['return_path'] = str_replace('###XID###', $midRidId, $this->dmailer['sys_dmail_rec']['return_path']);

            // recipient swiftmailer style
            // check if the email valids
            $recipient = array();
            if (GeneralUtility::validEmail($recipRow['email'])) {
                if (!empty($recipRow['name'])) {
                    // if there's a name
                    $recipient = array(
                        $recipRow['email'] => $this->getCharsetConverter()->conv($recipRow['name'], $this->backendCharset, $this->charset),
                    );
                } else {
                    // if only email is given
                    $recipient = array(
                        $recipRow['email'],
                    );
                }
            }


            if ($returnCode && !empty($recipient)) {
                $this->sendTheMail($recipient, $recipRow);
            }
        }
        return $returnCode;
    }

    /**
     * Send a simple email (without personalizing)
     *
     * @param string $addressList list of recipient address, comma list of emails
     *
     * @return	bool
     */
    public function dmailer_sendSimple($addressList)
    {
        if ($this->theParts['html']['content']) {
            $this->theParts['html']['content'] = $this->encodeMsg($this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_html'], -1));
        } else {
            $this->theParts['html']['content'] = '';
        }
        if ($this->theParts['plain']['content']) {
            $this->theParts['plain']['content'] = $this->encodeMsg($this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_plain'], -1));
        } else {
            $this->theParts['plain']['content'] = '';
        }

        $recipients = explode(',', $addressList);
        foreach ($recipients as $recipient) {
            $this->sendTheMail($recipient);
        }

        return true;
    }

    /**
     * This function checks which content elements are suppsed to be sent to the recipient.
     * tslib_content inserts dmail boudary markers in the content specifying which elements are intended for which categories,
     * this functions check if the recipeient is subscribing to any of these categories and
     * filters out the elements that are inteded for categories not subscribed to.
     *
     * @param array $cArray Array of content split by dmail boundary
     * @param string $userCategories The list of categories the user is subscribing to.
     *
     * @return	string		Content of the email, which the recipient subscribed
     */
    public function dmailer_getBoundaryParts($cArray, $userCategories)
    {
        $returnVal='';
        $this->mailHasContent = false;
        $boundaryMax = count($cArray)-1;
        foreach ($cArray as $bKey => $cP) {
            $key = substr($cP[0], 1);
            $isSubscribed = false;
            if (!$key || (intval($userCategories) == -1)) {
                $returnVal .= $cP[1];
                $this->mediaList .= $cP['mediaList'];
                if ($cP[1]) {
                    $this->mailHasContent = true;
                }
            } elseif ($key == 'END') {
                $returnVal .= $cP[1];
                $this->mediaList .= $cP['mediaList'];
                // There is content and it is not just the header and footer content, or it is the only content because we have no direct mail boundaries.
                if (($cP[1] && !($bKey == 0 || $bKey == $boundaryMax)) || count($cArray) == 1) {
                    $this->mailHasContent = true;
                }
            } else {
                foreach (explode(',', $key) as $group) {
                    if (GeneralUtility::inList($userCategories, $group)) {
                        $isSubscribed = true;
                    }
                }
                if ($isSubscribed) {
                    $returnVal .= $cP[1];
                    $this->mediaList .= $cP['mediaList'];
                    $this->mailHasContent = true;
                }
            }
        }
        return $returnVal;
    }

    /**
     * Get the list of categories ids subscribed to by recipient $uid from table $table
     *
     * @param string $table Tablename of the recipient
     * @param int $uid Uid of the recipient
     *
     * @return	string		list of categories
     */
    public function getListOfRecipentCategories($table, $uid)
    {
        if ($table == 'PLAINLIST') {
            return '';
        }

        $mm_table = $GLOBALS['TCA'][$table]['columns']['module_sys_dmail_category']['config']['MM'];

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $statement = $queryBuilder
            ->select('uid_foreign')
            ->from($mm_table)
            ->leftJoin(
                $table,
                $mm_table,
                $mm_table,
                $queryBuilder->expr()->eq(
                    $mm_table . '.uid_local',
                    $table . '.uid'
                )
            )
            ->where(
                $queryBuilder->expr()->eq(
                    $mm_table . '.uid_local',
                    intval($uid)
                )
            )
            ->execute();

        $list = array();
        while (($row = $statement->fetch())) {
            $list[] = $row['uid_foreign'];
        }

        return implode(',', $list);
    }

    /**
     * Mass send to recipient in the list
     *
     * @param	array $query_info List of recipients' ID in the sys_dmail table
     * @param	int $mid Directmail ID. UID of the sys_dmail table
     * @return	boolean
     */
    public function dmailer_masssend_list(array $query_info, $mid)
    {
        /* @var $LANG \TYPO3\CMS\Lang\LanguageService */
        global $LANG;

        $enableFields['tt_address'] = 'tt_address.deleted=0 AND tt_address.hidden=0';
        $enableFields['fe_users']   = 'fe_users.deleted=0 AND fe_users.disable=0';

        $c = 0;
        $returnVal = true;
        if (is_array($query_info['id_lists'])) {
            foreach ($query_info['id_lists'] as $table => $listArr) {
                if (is_array($listArr)) {
                    $ct = 0;
                    // Find tKey
                    if ($table=='tt_address' || $table=='fe_users') {
                        $tKey = substr($table, 0, 1);
                    } elseif ($table=='PLAINLIST') {
                        $tKey='P';
                    } else {
                        $tKey='u';
                    }

                    // Send mails
                    $sendIds = $this->dmailer_getSentMails($mid, $tKey);
                    if ($table == 'PLAINLIST') {
                        $sendIdsArr = explode(',', $sendIds);
                        foreach ($listArr as $kval => $recipRow) {
                            $kval++;
                            if (!in_array($kval, $sendIdsArr)) {
                                if ($c >= $this->sendPerCycle) {
                                    $returnVal = false;
                                    break;
                                }
                                $recipRow['uid'] = $kval;
                                $this->shipOfMail($mid, $recipRow, $tKey);
                                $ct++;
                                $c++;
                            }
                        }
                    } else {
                        $idList = implode(',', $listArr);
                        if ($idList) {
                            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
                            $statement = $queryBuilder
                                ->select('*')
                                ->from($table)
                                ->where(
                                    $queryBuilder->expr()->in(
                                        'uid',
                                        $idList
                                    )
                                )
                                ->andWhere(
                                    $queryBuilder->expr()->notIn(
                                        'uid',
                                        ($sendIds ? $sendIds : 0)
                                    )
                                )
                                ->setMaxResults($this->sendPerCycle + 1)
                                ->execute();

                            while (($recipRow = $statement->fetch())) {
                                $recipRow['sys_dmail_categories_list'] = $this->getListOfRecipentCategories($table, $recipRow['uid']);
                                if ($c >= $this->sendPerCycle) {
                                    $returnVal = false;
                                    break;
                                }
                                // We are NOT finished!
                                $this->shipOfMail($mid, $recipRow, $tKey);
                                $ct++;
                                $c++;
                            }
                        }
                    }
                    if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['enable_errorDLOG']){
                        GeneralUtility::devLog($LANG->getLL('dmailer_sending') . ' ' . $ct . ' ' . $LANG->getLL('dmailer_sending_to_table') . ' ' . $table, 'direct_mail');
                    }
                    $this->logArray[] = $LANG->getLL('dmailer_sending') . ' ' . $ct . ' ' . $LANG->getLL('dmailer_sending_to_table') . ' ' . $table;
                }
            }
        }
        return $returnVal;
    }

    /**
     * Sending the email and write to log.
     *
     * @param int $mid Newsletter ID. UID of the sys_dmail table
     * @param array $recipRow Recipient's data array
     * @param string $tableKey Table name
     *
     * @internal param string $tKey : table of the recipient
     *
     * @return	void
     */
    public function shipOfMail($mid, array $recipRow, $tableKey)
    {
        if (!$this->dmailer_isSend($mid, $recipRow['uid'], $tableKey)) {
            $pt = GeneralUtility::milliseconds();
            $recipRow = self::convertFields($recipRow);

            // write to dmail_maillog table. if it can be written, continue with sending.
            // if not, stop the script and report error
            $rC = 0;
            $logUid = $this->dmailer_addToMailLog($mid, $tableKey . '_' . $recipRow['uid'], strlen($this->message), GeneralUtility::milliseconds() - $pt, $rC, $recipRow['email']);

            if ($logUid) {
                $rC     = $this->dmailer_sendAdvanced($recipRow, $tableKey);
                $parsetime = GeneralUtility::milliseconds() - $pt;
                // Update the log with real values
                $updateFields = array(

                );
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_maillog');
                $ok = $queryBuilder
                    ->update('sys_dmail_maillog')
                    ->where(
                        $queryBuilder->expr()->eq(
                            'uid',
                            $logUid
                        )
                    )
                    ->set('tstamp', time())
                    ->set('size', strlen($this->message))
                    ->set('parsetime', $parsetime)
                    ->set('html_sent', intval($rC))
                    ->execute();

                if (!$ok) {
                    if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['enable_errorDLOG']) {
                        GeneralUtility::devLog('Unable to update Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid=' . $mid . ')', 'direct_mail', 3);
                    }
                    die('Unable to update Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid=' . $mid . ')');
                }
            } else {
                // stop the script if dummy log can't be made
                if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['enable_errorDLOG']) {
                    GeneralUtility::devLog('Unable to update Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid=' . $mid . ')', 'direct_mail', 3);
                }
                die('Unable to update Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid=' . $mid . ')');
            }
        }
    }

    /**
     * Converting array key.
     * fe_user and tt_address are using different fieldname for the same information
     *
     * @param array	$recipRow Recipient's data array
     *
     * @return array Fixed recipient's data array
     */
    public static function convertFields(array $recipRow)
    {

        // Compensation for the fact that fe_users has the field 'telephone' instead of 'phone'
        if ($recipRow['telephone']) {
            $recipRow['phone'] = $recipRow['telephone'];
        }

        // Firstname must be more that 1 character
        $recipRow['firstname'] = trim(strtok(trim($recipRow['name']), ' '));
        if (strlen($recipRow['firstname']) < 2 || preg_match('|[^[:alnum:]]$|', $recipRow['firstname'])) {
            $recipRow['firstname'] = $recipRow['name'];
        }
        if (!trim($recipRow['firstname'])) {
            $recipRow['firstname'] = $recipRow['email'];
        }
        return $recipRow;
    }

    /**
     * Set job begin and end time. And send this to admin
     *
     * @param int $mid Sys_dmail UID
     * @param string $key Begin or end
     *
     * @return	void
     */
    public function dmailer_setBeginEnd($mid, $key)
    {
        $subject = '';
        $message = '';

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail');
        $queryBuilder
            ->update('sys_dmail')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    intval($mid)
                )
            )
            ->set(
                'scheduled_' . $key,
                time()
            )
            ->execute();

        switch ($key) {
            case 'begin':
                $subject = $this->getLanguageService()->getLL('dmailer_mid') . ' ' . $mid . ' ' . $this->getLanguageService()->getLL('dmailer_job_begin');
                $message = $this->getLanguageService()->getLL('dmailer_job_begin') . ': ' . date('d-m-y h:i:s');
                break;
            case 'end':
                $subject = $this->getLanguageService()->getLL('dmailer_mid') . ' ' . $mid . ' ' . $this->getLanguageService()->getLL('dmailer_job_end');
                $message = $this->getLanguageService()->getLL('dmailer_job_end') . ': ' . date('d-m-y h:i:s');
                break;
            default:
                // do nothing
        }

        //
        if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['enable_errorDLOG']){
            GeneralUtility::devLog($subject . ': ' . $message, 'direct_mail');
        }
        $this->logArray[] = $subject . ': ' . $message;


        if ($this->notificationJob) {
            $from_name = '';
            if ($this->from_name) {
                $from_name = $this->getCharsetConverter()->conv($this->from_name, $this->charset, $this->backendCharset);
            }

            /* @var $mail \TYPO3\CMS\Core\Mail\MailMessage */
            $mail = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Mail\\MailMessage');
            $mail->setTo($this->from_email, $from_name);
            $mail->setFrom($this->from_email, $from_name);
            $mail->setSubject($subject);
            if (!empty($this->replyto_email)) {
                $mail->setReplyTo($this->replyto_email);
            }
            $mail->setBody($message);
            $mail->send();
        }
    }

    /**
     * Count how many email have been sent
     *
     * @param int $mid Newsletter ID. UID of the sys_dmail record
     * @param string $rtbl Recipient table
     *
     * @return int Number of sent emails
     */
    public function dmailer_howManySendMails($mid, $rtbl='')
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_maillog');
        $queryBuilder
            ->count('*')
            ->from('sys_dmail_maillog')
            ->where(
                $queryBuilder->expr()->eq(
                    'mid',
                    intval($mid)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'response_type',
                    0
                )
            );
        if ($rtbl) {
            $statement = $queryBuilder
                ->andWhere(
                    $queryBuilder->expr()->eq(
                        'rtbl',
                        $queryBuilder->createNamedParameter($rtbl)
                    )
                )
                ->execute();
        } else {
            $statement = $queryBuilder->execute();
        }

        $row = $statement->fetchAll();
        return $row[0];
    }

    /**
     * Find out, if an email has been sent to a recipient
     *
     * @param int $mid Newsletter ID. UID of the sys_dmail record
     * @param int $rid Recipient UID
     * @param string $rtbl Recipient table
     *
     * @return	int Number of found records
     */
    public function dmailer_isSend($mid, $rid, $rtbl)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_maillog');

        $statement = $queryBuilder
            ->select('uid')
            ->from('sys_dmail_maillog')
            ->where(
                $queryBuilder->expr()->eq(
                    'rid',
                    intval(($rid))
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'rtbl',
                    $queryBuilder->createNamedParameter($rtbl)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'mid',
                    intval($mid)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'response_type',
                    '0'
                )
            )
            ->execute();

        return $statement->rowCount();
    }

    /**
     * Get IDs of recipient, which has been sent
     *
     * @param	int $mid Newsletter ID. UID of the sys_dmail record
     * @param	string $rtbl Recipient table
     *
     * @return	string		list of sent recipients
     */
    public function dmailer_getSentMails($mid, $rtbl)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail_maillog');
        $statement = $queryBuilder
            ->select('rid')
            ->from('sys_dmail_maillog')
            ->where(
                $queryBuilder->expr()->eq(
                    'mid',
                    intval($mid)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'rtbl',
                    $queryBuilder->createNamedParameter($rtbl)
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'response_type',
                    '0'
                )
            )
            ->execute();

        $list = array();

        while (($row = $statement->fetch())) {
            $list[] = $row['rid'];
        }

        return implode(',', $list);
    }

    /**
     * Add action to sys_dmail_maillog table
     *
     * @param int $mid Newsletter ID
     * @param int $rid Recipient ID
     * @param int $size Size of the sent email
     * @param int $parsetime Parse time of the email
     * @param int $html Set if HTML email is sent
     * @param string $email Recipient's email
     *
     * @return bool True on success or False on error
     */
    public function dmailer_addToMailLog($mid, $rid, $size, $parsetime, $html, $email)
    {
        $temp_recip = explode('_', $rid);
        $insertFields = array(
            'mid'       => intval($mid),
            'rtbl'      => $temp_recip[0],
            'rid'       => intval($temp_recip[1]),
            'email'     => $email,
            'tstamp'    => time(),
            'url'       => '',
            'size'      => $size,
            'parsetime' => $parsetime,
            'html_sent' => intval($html)
        );

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('sys_dmail_maillog');
        $queryBuilder
            ->insert(
                'sys_dmail_maillog',
                $insertFields
            );

        return (int)$queryBuilder->lastInsertId('sys_dmail_maillog');
    }

    /**
     * Called from the dmailerd script.
     * Look if there is newsletter to be sent
     * and do the sending process. Otherwise quit runtime
     *
     * @return	void
     */
    public function runcron()
    {
        $this->sendPerCycle = trim($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['sendPerCycle']) ? intval($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['sendPerCycle']) : 50;
        $this->notificationJob = intval($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['notificationJob']);

        if (!is_object($this->getLanguageService())) {
            /* @var $LANG \TYPO3\CMS\Lang\LanguageService */
            $GLOBALS['LANG'] = GeneralUtility::makeInstance('TYPO3\\CMS\\Lang\\LanguageService');
            $language = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['cron_language'] ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['cron_language'] : $this->user_dmailerLang;
            $this->getLanguageService()->init(trim($language));
        }

        // always include locallang file
        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf');

        $pt = GeneralUtility::milliseconds();

        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('sys_dmail');
        $queryBuilder
            ->getRestrictions()
            ->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        $statement = $queryBuilder
            ->select('*')
            ->from('sys_dmail')
            ->where(
                $queryBuilder->expr()->neq(
                    'scheduled',
                    '0'
                )
            )
            ->andWhere(
                $queryBuilder->expr()->lt(
                    'scheduled',
                    time()
                )
            )
            ->andWhere(
                $queryBuilder->expr()->eq(
                    'scheduled_end',
                    '0'
                )
            )
            ->andWhere(
                $queryBuilder->expr()->notIn(
                    'type',
                    ['2', '3']
                )
            )
            ->orderBy('scheduled')
            ->execute();

        if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['enable_errorDLOG']){
            GeneralUtility::devLog($this->getLanguageService()->getLL('dmailer_invoked_at') . ' ' . date('h:i:s d-m-Y'), 'direct_mail');
        }
        $this->logArray[] = $this->getLanguageService()->getLL('dmailer_invoked_at') . ' ' . date('h:i:s d-m-Y');

        if (($row = $statement->fetch())) {
            if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['enable_errorDLOG']){
                GeneralUtility::devLog($this->getLanguageService()->getLL('dmailer_sys_dmail_record') . ' ' . $row['uid'] . ', \'' . $row['subject'] . '\'' . $this->getLanguageService()->getLL('dmailer_processed'), 'direct_mail');
            }
            $this->logArray[] = $this->getLanguageService()->getLL('dmailer_sys_dmail_record') . ' ' . $row['uid'] . ', \'' . $row['subject'] . '\'' . $this->getLanguageService()->getLL('dmailer_processed');
            $this->dmailer_prepare($row);
            $query_info = unserialize($row['query_info']);

            if (!$row['scheduled_begin']) {
                $this->dmailer_setBeginEnd($row['uid'], 'begin');
            }

            $finished = $this->dmailer_masssend_list($query_info, $row['uid']);

            if ($finished) {
                $this->dmailer_setBeginEnd($row['uid'], 'end');
            }
        } else {
            if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['enable_errorDLOG']){
                GeneralUtility::devLog($this->getLanguageService()->getLL('dmailer_nothing_to_do'), 'direct_mail');
            }
            $this->logArray[] = $this->getLanguageService()->getLL('dmailer_nothing_to_do');
        }



        $parsetime = GeneralUtility::milliseconds()-$pt;
        if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['enable_errorDLOG']){
            GeneralUtility::devLog($this->getLanguageService()->getLL('dmailer_ending') . ' ' . $parsetime . ' ms', 'direct_mail');
        }
        $this->logArray[] = $this->getLanguageService()->getLL('dmailer_ending') . ' ' . $parsetime . ' ms';
    }

    /**
     * Initializing the MailMessage class and setting the first global variables. Write to log file if it's a cronjob
     *
     * @param int $user_dmailer_sendPerCycle Total of recipient in a cycle
     * @param string $user_dmailer_lang Language of the user
     *
     * @return	void
     */
    public function start($user_dmailer_sendPerCycle = 50, $user_dmailer_lang = 'en')
    {

        // Sets the message id
        $host = GeneralUtility::getHostname();
        if (!$host || $host == '127.0.0.1' || $host == 'localhost' || $host == 'localhost.localdomain') {
            $host = ($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']) : 'localhost') . '.TYPO3';
        }

        $idLeft = time() . '.' . uniqid();
        $idRight = !empty($host) ? $host : 'swift.generated';
        $this->messageid = $idLeft . '@' . $idRight;

        // Default line break for Unix systems.
        $this->linebreak = LF;
        // Line break for Windows. This is needed because PHP on Windows systems
        // send mails via SMTP instead of using sendmail, and thus the linebreak needs to be \r\n.
        if (TYPO3_OS == 'WIN') {
            $this->linebreak = CRLF;
        }

        // Mailer engine parameters
        $this->sendPerCycle = $user_dmailer_sendPerCycle;
        $this->user_dmailerLang = $user_dmailer_lang;
        if (!$this->nonCron) {
            if ($GLOBALS['TYPO3_CONF_VARS']['SYS']['enable_errorDLOG']){
                GeneralUtility::devLog('Starting directmail cronjob', 'direct_mail');
            }
            // write this temp file for checking the engine in the status module
            $this->dmailer_log('w', 'starting directmail cronjob');
        }
    }

    /**
     * Set the content from $this->theParts['html'] or $this->theParts['plain'] to the swiftmailer
     *
     * @var $mailer \TYPO3\CMS\Core\Mail\MailMessage Mailer Object
     *
     * @return void
     */
    public function setContent(&$mailer)
    {
        // todo: css??
        // iterate through the media array and embed them
        if ($this->includeMedia) {
            // extract all media path from the mail message
            $this->extractMediaLinks();
            foreach ($this->theParts['html']['media'] as $media) {
                if (($media['tag'] == 'img' || $media['tag'] == 'table' || $media['tag'] == 'tr' || $media['tag'] == 'td') && !$media['use_jumpurl'] && !$media['do_not_embed']) {
                    if (ini_get('allow_url_fopen')) {
                        // SwiftMailer depends on allow_url_fopen in PHP
                        $cid = $mailer->embed(\Swift_Image::fromPath($media['absRef']));
                    } else {
                        // If allow_url_fopen is deactivated
                        // SwiftMailer depends on allow_url_fopen in PHP
                        // To work around this, download the files using t3lib::getURL() to a temporary location.
                        $fileContent = GeneralUtility::getUrl($media['absRef']);
                        $tempFile = PATH_site . 'uploads/tx_directmail/' . basename($media['absRef']);
                        GeneralUtility::writeFile($tempFile, $fileContent);

                        unset($fileContent);

                        $cid = $mailer->embed(\Swift_Image::fromPath($tempFile));
                        // Temporary files will be removed again after the mail was sent!
                        $this->tempFileList[] = $tempFile;
                    }

                    $this->theParts['html']['content'] = str_replace($media['subst_str'], $cid, $this->theParts['html']['content']);
                }
            }
            // remove ` do_not_embed="1"` attributes
            $this->theParts['html']['content'] = str_replace(' do_not_embed="1"', '', $this->theParts['html']['content']);
        }

        // TODO: multiple instance for each NL type? HTML+Plain or Plain only?
        // http://groups.google.com/group/swiftmailer/browse_thread/thread/98041a123223e63d
        // $mailer->attach($entity);

        // set the html content
        if ($this->theParts['html']) {
            $mailer->setBody($this->theParts['html']['content'], 'text/html');
            // set the plain content as alt part
            if ($this->theParts['plain']) {
                $mailer->addPart($this->theParts['plain']['content'], 'text/plain');
            }
        } elseif ($this->theParts['plain']) {
            $mailer->setBody($this->theParts['plain']['content'], 'text/plain');
        }

        // set the attachment from $this->dmailer['sys_dmail_rec']['attachment']
        // comma separated files
        if (!empty($this->dmailer['sys_dmail_rec']['attachment'])) {
            $files = explode(',', $this->dmailer['sys_dmail_rec']['attachment']);
            foreach ($files as $file) {
                $mailer->attach(\Swift_Attachment::fromPath(PATH_site . 'uploads/tx_directmail/' . $file));
            }
        }
    }

    /**
     * Send of the email using php mail function.
     *
     * @param	string/array	$recipient The recipient array. array($name => $mail)
     * @param   array           $recipRow  Recipient's data array
     *
     * @return	void
     */
    public function sendTheMail($recipient, $recipRow = null)
    {
        // init the swiftmailer object
        /* @var $mailer \TYPO3\CMS\Core\Mail\MailMessage */
        $mailer = GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Mail\\MailMessage');
        $mailer->setFrom(array($this->from_email => $this->from_name));
        $mailer->setSubject($this->subject);
        $mailer->setPriority($this->priority);

        if ($this->replyto_email) {
            $mailer->setReplyTo(array($this->replyto_email => $this->replyto_name));
        } else {
            $mailer->setReplyTo(array($this->from_email => $this->from_name));
        }

        // setting additional header
        // organization and TYPO3MID
        $header = $mailer->getHeaders();
        $header->addTextHeader('X-TYPO3MID', $this->TYPO3MID);

        if ($this->organisation) {
            $header->addTextHeader('Organization', $this->organisation);
        }

        // Hook to edit or add the mail headers
        if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailHeadersHook'])) {
            $mailHeadersHook =& $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailHeadersHook'];
            if (is_array($mailHeadersHook)) {
                $hookParameters = array(
                    'row'     => &$recipRow,
                    'header' => &$header,
                );
                $hookReference = &$this;
                foreach ($mailHeadersHook as $hookFunction) {
                    GeneralUtility::callUserFunction($hookFunction, $hookParameters, $hookReference);
                }
            }
        }

        if (GeneralUtility::validEmail($this->dmailer['sys_dmail_rec']['return_path'])) {
            $mailer->setReturnPath($this->dmailer['sys_dmail_rec']['return_path']);
        }

        // set the recipient
        $mailer->setTo($recipient);

        // TODO: setContent should set the images (includeMedia) or add attachment
        $this->setContent($mailer);

        if ($this->encoding == 'base64') {
            $mailer->setEncoder(\Swift_Encoding::getBase64Encoding());
        }

        if ($this->encoding == '8bit') {
            $mailer->setEncoder(\Swift_Encoding::get8BitEncoding());
        }

        // TODO: do we really need the return value?
        $sent = $mailer->send();
        $failed = $mailer->getFailedRecipients();

        // unset the mailer object
        unset($mailer);

        // Delete temporary files
        // see setContent, where temp images are downloaded
        if (!empty($this->tempFileList)) {
            foreach ($this->tempFileList as $tempFile) {
                if (file_exists($tempFile)) {
                    unlink($tempFile);
                }
            }
        }
    }


    /**
     * Add HTML to an email
     *
     * @param	string $file String location of the HTML
     *
     * @return	mixed		bool: HTML fetch status. string: if HTML is a frameset.
     */
    public function addHTML($file)
    {
        // Adds HTML and media, encodes it from a URL or file
        $status = $this->fetchHTML($file);
        if (!$status) {
            return false;
        }
        if ($this->extractFramesInfo()) {
            return 'Document was a frameset. Stopped';
        }
        $this->extractHyperLinks();
        $this->substHREFsInHTML();
        $this->setHTML($this->encodeMsg($this->theParts['html']['content']));
        return true;
    }

    /**
     * Fetches the HTML-content from either url or local server file
     *
     * @param	string $url Url of the html to fetch
     *
     * @return bool Whether the data was fetched or not
     */
    public function fetchHTML($url)
    {
        // Fetches the content of the page
        $this->theParts['html']['content'] = GeneralUtility::getURL($url);
        if ($this->theParts['html']['content']) {
            $urlPart = parse_url($url);
            if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['UseHttpToFetch'] == 1) {
                $urlPart['scheme'] = 'http';
            }

            if (!empty($urlPart['user'])) {
                $user = $urlPart['user'];
                if (!empty($urlPart['pass'])) {
                    $user .= ':' . $urlPart['pass'];
                }
                $user .= '@';
            }

            $this->theParts['html']['path'] = $urlPart['scheme'] . '://' . $user . $urlPart['host'] . GeneralUtility::getIndpEnv('TYPO3_SITE_PATH');

            return true;
        } else {
            return false;
        }
    }

    /**
     * If it's a HTML email, which MIME type?
     *
     * @return	string		MIME type of the email
     */
    public function getHTMLContentType()
    {
        return (count($this->theParts['html']['media']) && $this->includeMedia) ? 'multipart/related' : 'multipart/alternative';
    }

    /**
     * This function returns the mime type of the file specified by the url
     *
     * @param string $url The url
     *
     * @return string $mimeType: the mime type found in the header
     */
    public function getMimeType($url)
    {
        $mimeType = '';
        $headers = trim(GeneralUtility::getURL($url, 2));
        if ($headers) {
            $matches = array();
            if (preg_match('/(Content-Type:[\s]*)([a-zA-Z_0-9\/\-\+\.]*)([\s]|$)/', $headers, $matches)) {
                $mimeType = trim($matches[2]);
            }
        }
        return $mimeType;
    }

    /**
     * Rewrite core function, since it has bug.
     * See Bug Tracker 8265. It will be removed if it's fixed.
     * This function substitutes the hrefs in $this->theParts["html"]["content"]
     *
     * @return	void
     */
    public function substHREFsInHTML()
    {
        if (!is_array($this->theParts['html']['hrefs'])) {
            return;
        }
        foreach ($this->theParts['html']['hrefs'] as $urlId => $val) {
            if ($val['no_jumpurl']) {
                // A tag attribute "no_jumpurl=1" allows to disable jumpurl for custom links
                $substVal = $val['absRef'];
            } elseif ($this->jumperURL_prefix && ($val['tag'] != 'form') && (!strstr($val['ref'], 'mailto:'))) {
                // Form elements cannot use jumpurl!
                if ($this->jumperURL_useId) {
                    $substVal = $this->jumperURL_prefix . $urlId;
                } else {
                    $substVal = $this->jumperURL_prefix . str_replace('%2F', '/', rawurlencode($val['absRef']));
                }
            } elseif (strstr($val['ref'], 'mailto:') && $this->jumperURL_useMailto) {
                if ($this->jumperURL_useId) {
                    $substVal = $this->jumperURL_prefix . $urlId;
                } else {
                    $substVal = $this->jumperURL_prefix . str_replace('%2F', '/', rawurlencode($val['absRef']));
                }
            } else {
                $substVal = $val['absRef'];
            }
            $this->theParts['html']['content'] = str_replace(
                $val['subst_str'],
                $val['quotes'] . $substVal . $val['quotes'],
                $this->theParts['html']['content']
            );
        }
    }

    /**
     * Write to log file and send a notification email to admin if no records in sys_dmail_maillog table can be made
     *
     * @param string $writeMode Mode to open a file
     * @param string $logMsg Log message
     *
     * @return	void
     */
    public function dmailer_log($writeMode, $logMsg)
    {
        $content = time() . ' => ' . $logMsg . LF;
        $logfilePath = 'typo3temp/tx_directmail_dmailer_log.txt';

        $fp = fopen(PATH_site . $logfilePath, $writeMode);
        if ($fp) {
            fwrite($fp, $content);
            fclose($fp);
        }
    }

    /**
     * Adds plain-text, replaces the HTTP urls in the plain text and then encodes it
     *
     * @param string $content The plain text content
     *
     * @return void
     */
    public function addPlain($content)
    {
        $content = $this->substHTTPurlsInPlainText($content);
        $this->setPlain($this->encodeMsg($content));
    }

    /**
     * This substitutes the http:// urls in plain text with links
     *
     * @todo Use preg_replace_callback for link parsing and replacement instead of "explode"
     *
     * @param string $content The content to use to substitute
     *
     * @return string The changed content
     */
    public function substHTTPurlsInPlainText($content)
    {
        if (!$this->jumperURL_prefix) {
            return $content;
        }

        $textpieces = explode('http://', $content);
        $pieces = count($textpieces);
        $textstr = $textpieces[0];
        for ($i = 1; $i < $pieces; $i++) {
            $len = strcspn($textpieces[$i], chr(32) . TAB . CRLF);
            if (trim(substr($textstr, -1)) == '' && $len) {
                $lastChar = substr($textpieces[$i], $len - 1, 1);
                if (!preg_match('/[A-Za-z0-9\/#]/', $lastChar)) {
                    $len--;
                }

                $parts = array();
                $parts[0] = 'http://' . substr($textpieces[$i], 0, $len);
                $parts[1] = substr($textpieces[$i], $len);

                if (strpos($parts[0], '&no_jumpurl=1') !== false) {
                    // A link parameter "&no_jumpurl=1" allows to disable jumpurl for plain text links
                    $parts[0] = str_replace('&no_jumpurl=1', '', $parts[0]);
                } elseif ($this->jumperURL_useId) {
                    $this->theParts['plain']['link_ids'][$i] = $parts[0];
                    $parts[0] = $this->jumperURL_prefix . '-' . $i;
                } else {
                    $parts[0] = $this->jumperURL_prefix . str_replace('%2F', '/', rawurlencode($parts[0]));
                }
                $textstr .= $parts[0] . $parts[1];
            } else {
                $textstr .= 'http://' . $textpieces[$i];
            }
        }
        return $textstr;
    }

    /**
     * Wrapper function. always quoted_printable
     *
     * @param string $content The content that will be encoded
     *
     * @return string The encoded content
     */
    public function encodeMsg($content)
    {
        return $content;
    }

    /**
     * Returns base64-encoded content, which is broken every 76 character
     *
     * @param string $inputstr The string to encode
     *
     * @return string The encoded string
     */
    public function makeBase64($inputstr)
    {
        return chunk_split(base64_encode($inputstr));
    }

    /**
     * Sets the plain-text part. No processing done.
     *
     * @param string $content The plain content
     *
     * @return	void
     */
    public function setPlain($content)
    {
        $this->theParts['plain']['content'] = $content;
    }

    /**
     * Sets the HTML-part. No processing done.
     *
     * @param string $content The HTML content
     *
     * @return void
     */
    public function setHtml($content)
    {
        $this->theParts['html']['content'] = $content;
    }

    /**
     * Extracts all media-links from $this->theParts['html']['content']
     *
     * @return	void
     */
    public function extractMediaLinks()
    {
        $this->theParts['html']['media'] = array();

        $htmlContent = $this->theParts['html']['content'];
        $attribRegex = $this->tag_regex(array('img', 'table', 'td', 'tr', 'body', 'iframe', 'script', 'input', 'embed'));
        $imageList = '';

        // split the document by the beginning of the above tags
        $codepieces = preg_split($attribRegex, $htmlContent);
        $len = strlen($codepieces[0]);
        $pieces = count($codepieces);
        $reg = array();
        for ($i = 1; $i < $pieces; $i++) {
            $tag = strtolower(strtok(substr($htmlContent, $len + 1, 10), ' '));
            $len += strlen($tag) + strlen($codepieces[$i]) + 2;
            $dummy = preg_match('/[^>]*/', $codepieces[$i], $reg);

            // Fetches the attributes for the tag
            $attributes = $this->get_tag_attributes($reg[0]);
            $imageData = array();

            // Finds the src or background attribute
            $imageData['ref'] = ($attributes['src'] ? $attributes['src'] : $attributes['background']);
            if ($imageData['ref']) {
                // find out if the value had quotes around it
                $imageData['quotes'] = (substr($codepieces[$i], strpos($codepieces[$i], $imageData['ref']) - 1, 1) == '"') ? '"' : '';
                // subst_str is the string to look for, when substituting lateron
                $imageData['subst_str'] = $imageData['quotes'] . $imageData['ref'] . $imageData['quotes'];
                if ($imageData['ref'] && !strstr($imageList, '|' . $imageData['subst_str'] . '|')) {
                    $imageList .= '|' . $imageData['subst_str'] . '|';
                    $imageData['absRef'] = $this->absRef($imageData['ref']);
                    $imageData['tag'] = $tag;
                    $imageData['use_jumpurl'] = $attributes['dmailerping'] ? 1 : 0;
                    $imageData['do_not_embed'] = !empty($attributes['do_not_embed']);
                    $this->theParts['html']['media'][] = $imageData;
                }
            }
        }

        // Extracting stylesheets
        $attribRegex = $this->tag_regex(array('link'));
        // Split the document by the beginning of the above tags
        $codepieces = preg_split($attribRegex, $htmlContent);
        $pieces = count($codepieces);
        for ($i = 1; $i < $pieces; $i++) {
            $dummy = preg_match('/[^>]*/', $codepieces[$i], $reg);
            // fetches the attributes for the tag
            $attributes = $this->get_tag_attributes($reg[0]);
            $imageData = array();
            if (strtolower($attributes['rel']) == 'stylesheet' && $attributes['href']) {
                // Finds the src or background attribute
                $imageData['ref'] = $attributes['href'];
                // Finds out if the value had quotes around it
                $imageData['quotes'] = (substr($codepieces[$i], strpos($codepieces[$i], $imageData['ref']) - 1, 1) == '"') ? '"' : '';
                // subst_str is the string to look for, when substituting lateron
                $imageData['subst_str'] = $imageData['quotes'] . $imageData['ref'] . $imageData['quotes'];
                if ($imageData['ref'] && !strstr($imageList, '|' . $imageData['subst_str'] . '|')) {
                    $imageList .= '|' . $imageData['subst_str'] . '|';
                    $imageData['absRef'] = $this->absRef($imageData['ref']);
                    $this->theParts['html']['media'][] = $imageData;
                }
            }
        }

        // fixes javascript rollovers
        $codepieces = explode('.src', $htmlContent);
        $pieces = count($codepieces);
        $expr = '/^[^' . quotemeta('"') . quotemeta("'") . ']*/';
        for ($i = 1; $i < $pieces; $i++) {
            $temp = $codepieces[$i];
            $temp = trim(str_replace('=', '', trim($temp)));
            preg_match($expr, substr($temp, 1, strlen($temp)), $reg);
            $imageData['ref'] = $reg[0];
            $imageData['quotes'] = substr($temp, 0, 1);
            // subst_str is the string to look for, when substituting lateron
            $imageData['subst_str'] = $imageData['quotes'] . $imageData['ref'] . $imageData['quotes'];
            $theInfo = GeneralUtility::split_fileref($imageData['ref']);

            switch ($theInfo['fileext']) {
                case 'gif':
                    // do like jpg
                case 'jpeg':
                    // do like jpg
                case 'jpg':
                    if ($imageData['ref'] && !strstr($imageList, '|' . $imageData['subst_str'] . '|')) {
                        $imageList .= '|' . $imageData['subst_str'] . '|';
                        $imageData['absRef'] = $this->absRef($imageData['ref']);
                        $this->theParts['html']['media'][] = $imageData;
                    }
                    break;
                default:
                    // do nothing
            }
        }
    }

    /**
     * Extracts all hyper-links from $this->theParts["html"]["content"]
     *
     * @return	void
     */
    public function extractHyperLinks()
    {
        $linkList = '';

        $htmlContent = $this->theParts['html']['content'];
        $attribRegex = $this->tag_regex(array('a', 'form', 'area'));

        // Splits the document by the beginning of the above tags
        $codepieces = preg_split($attribRegex, $htmlContent);
        $len = strlen($codepieces[0]);
        $pieces = count($codepieces);
        for ($i = 1; $i < $pieces; $i++) {
            $tag = strtolower(strtok(substr($htmlContent, $len + 1, 10), ' '));
            $len += strlen($tag) + strlen($codepieces[$i]) + 2;

            $dummy = preg_match('/[^>]*/', $codepieces[$i], $reg);
            // Fetches the attributes for the tag
            $attributes = $this->get_tag_attributes($reg[0], false);
            $hrefData = array();
            $hrefData['ref'] = $attributes['href'] ?: $attributes['action'];
            $quotes = (substr($hrefData['ref'], 0, 1) === '"') ? '"' : '';
            $hrefData['ref'] = trim($hrefData['ref'], '"');
            if ($hrefData['ref']) {
                // Finds out if the value had quotes around it
                $hrefData['quotes'] = $quotes;
                // subst_str is the string to look for when substituting later on
                $hrefData['subst_str'] = $quotes . $hrefData['ref'] . $quotes;
                if ($hrefData['ref'] && substr(trim($hrefData['ref']), 0, 1) != '#' && !strstr($linkList, '|' . $hrefData['subst_str'] . '|')) {
                    $linkList .= '|' . $hrefData['subst_str'] . '|';
                    $hrefData['absRef'] = $this->absRef($hrefData['ref']);
                    $hrefData['tag'] = $tag;
                    $hrefData['no_jumpurl'] = intval(trim($attributes['no_jumpurl'], '"')) ? 1 : 0;
                    $this->theParts['html']['hrefs'][] = $hrefData;
                }
            }
        }
        // Extracts TYPO3 specific links made by the openPic() JS function
        $codepieces = explode("onClick=\"openPic('", $htmlContent);
        $pieces = count($codepieces);
        for ($i = 1; $i < $pieces; $i++) {
            $showpicArray = explode("'", $codepieces[$i]);
            $hrefData['ref'] = $showpicArray[0];
            if ($hrefData['ref']) {
                $hrefData['quotes'] = "'";
                // subst_str is the string to look for, when substituting lateron
                $hrefData['subst_str'] = $hrefData['quotes'] . $hrefData['ref'] . $hrefData['quotes'];
                if ($hrefData['ref'] && !strstr($linkList, '|' . $hrefData['subst_str'] . '|')) {
                    $linkList .= '|' . $hrefData['subst_str'] . '|';
                    $hrefData['absRef'] = $this->absRef($hrefData['ref']);
                    $this->theParts['html']['hrefs'][] = $hrefData;
                }
            }
        }

        // substitute dmailerping URL
        // get all media and search for use_jumpurl then add it to the hrefs array
        $this->extractMediaLinks();
        foreach ($this->theParts['html']['media'] as $mediaData) {
            if ($mediaData['use_jumpurl'] === 1) {
                $this->theParts['html']['hrefs'][$mediaData['ref']] = $mediaData;
            }
        }
    }


    /**
     * Extracts all media-links from $this->theParts["html"]["content"]
     *
     * @return	array	two-dimensional array with information about each frame
     */
    public function extractFramesInfo()
    {
        $htmlCode = $this->theParts['html']['content'];
        $info = array();
        if (strpos(' ' . $htmlCode, '<frame ')) {
            $attribRegex = $this->tag_regex('frame');
            // Splits the document by the beginning of the above tags
            $codepieces = preg_split($attribRegex, $htmlCode, 1000000);
            $pieces = count($codepieces);
            for ($i = 1; $i < $pieces; $i++) {
                $dummy = preg_match('/[^>]*/', $codepieces[$i], $reg);
                // Fetches the attributes for the tag
                $attributes = $this->get_tag_attributes($reg[0]);
                $frame = array();
                $frame['src'] = $attributes['src'];
                $frame['name'] = $attributes['name'];
                $frame['absRef'] = $this->absRef($frame['src']);
                $info[] = $frame;
            }
            return $info;
        }
    }

    /**
     * Creates a regular expression out of a list of tags
     *
     * @param string|array $tags Array the list of tags
     * 		(either as array or string if it is one tag)
     *
     * @return string the regular expression
     */
    public function tag_regex($tags)
    {
        $tags = (!is_array($tags) ? array($tags) : $tags);
        $regexp = '/';
        $c = count($tags);
        foreach ($tags as $tag) {
            $c--;
            $regexp .= '<' . $tag . '[[:space:]]' . (($c) ? '|' : '');
        }
        return $regexp . '/i';
    }

    /**
     * This function analyzes a HTML tag
     * If an attribute is empty (like OPTION) the value of that key is just empty.
     * Check it with is_set();
     *
     * @param string $tag Tag is either like this "<TAG OPTION ATTRIB=VALUE>" or
     *				 this " OPTION ATTRIB=VALUE>" which means you can omit the tag-name
     * @param boolean $removeQuotes When TRUE (default) quotes around a value will get removed
     *
     * @return array array with attributes as keys in lower-case
     */
    public function get_tag_attributes($tag, $removeQuotes = true)
    {
        $attributes = array();
        $tag = ltrim(preg_replace('/^<[^ ]*/', '', trim($tag)));
        $tagLen = strlen($tag);
        $safetyCounter = 100;
        // Find attribute
        while ($tag) {
            $value = '';
            $reg = preg_split('/[[:space:]=>]/', $tag, 2);
            $attrib = $reg[0];

            $tag = ltrim(substr($tag, strlen($attrib), $tagLen));
            if (substr($tag, 0, 1) === '=') {
                $tag = ltrim(substr($tag, 1, $tagLen));
                if (substr($tag, 0, 1) === '"' && $removeQuotes) {
                    // Quotes around the value
                    $reg = explode('"', substr($tag, 1, $tagLen), 2);
                    $tag = ltrim($reg[1]);
                    $value = $reg[0];
                } else {
                    // No quotes around value
                    preg_match('/^([^[:space:]>]*)(.*)/', $tag, $reg);
                    $value = trim($reg[1]);
                    $tag = ltrim($reg[2]);
                    if (substr($tag, 0, 1) === '>') {
                        $tag = '';
                    }
                }
            }
            $attributes[strtolower($attrib)] = $value;
            $safetyCounter--;
            if ($safetyCounter < 0) {
                break;
            }
        }
        return $attributes;
    }

    /**
     * Returns the absolute address of a link. This is based on
     * $this->theParts["html"]["path"] being the root-address
     *
     * @param string $ref Address to use
     *
     * @return string The absolute address
     */
    public function absRef($ref)
    {
        $ref = trim($ref);
        $info = parse_url($ref);
        if ($info['scheme']) {
            // if ref is an url
            // do nothing
        } elseif (preg_match('/^\//', $ref)) {
            // if ref is an absolute link
            $addr = parse_url($this->theParts['html']['path']);
            $ref = $addr['scheme'] . '://' . $addr['host'] . ($addr['port'] ? ':' . $addr['port'] : '') . $ref;
        } else {
            // If the reference is relative, the path is added,
            // in order for us to fetch the content
            if (substr($this->theParts['html']['path'], -1) == '/') {
                // if the last char is a /, then prepend the ref
                $ref = $this->theParts['html']['path'] . $ref;
            } else {
                // if the last char not a /, then assume it's an absolute
                $addr = parse_url($this->theParts['html']['path']);
                $ref = $addr['scheme'] . '://' . $addr['host'] . ($addr['port'] ? ':' . $addr['port'] : '') . '/' . $ref;
            }
        }

        return $ref;
    }

    /**
     * Reads a url or file
     *
     * @param string $url The URL to fetch
     *
     * @return string The content of the URL
     */
    public function getURL($url)
    {
        $url = $this->addUserPass($url);
        $url = $this->addSimulateUsergroup($url);
        return GeneralUtility::getURL($url);
    }

    /**
     * Adds HTTP user and password (from $this->http_username) to a URL
     *
     * @param string $url The URL
     *
     * @return string The URL with the added values
     */
    public function addUserPass($url)
    {
        $user = $this->http_username;
        $pass = $this->http_password;
        $matches = array();
        if ($user && $pass && preg_match('/^(https?:\/\/)/', $url, $matches)) {
            return $matches[1] . $user . ':' . $pass . '@' . substr($url, strlen($matches[1]));
        }
        return $url;
    }

    /**
     * If the page containing the mail is access protected,
     * access permission can be simulated when fetching the e-mail
     * by adding a special parameter to the URL
     *
     * @param string $url The URL
     *
     * @return string The URL with the added values
     */
    public function addSimulateUsergroup($url)
    {
        if ($this->simulateUsergroup && MathUtility::canBeInterpretedAsInteger($this->simulateUsergroup)) {
            return $url . '&dmail_fe_group=' . (int)$this->simulateUsergroup . '&access_token=' . DirectMailUtility::createAndGetAccessToken();
        }
        return $url;
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
