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

use DirectMailTeam\DirectMail\Utility\AuthCodeUtility;
use DirectMailTeam\DirectMail\Repository\SysDmailRepository;
use DirectMailTeam\DirectMail\Repository\SysDmailMaillogRepository;
use DirectMailTeam\DirectMail\Repository\TempRepository;
use Doctrine\DBAL\ForwardCompatibility\DriverStatement;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;

/**
 * Class, doing the sending of Direct-mails, eg. through a cron-job
 *
 * @author		Kasper Skårhøj <kasperYYYY@typo3.com>
 * @author      Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 */
class Dmailer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /*
     * @var int amount of mail sent in one batch
     */
    public $sendPerCycle = 50;

    public $mailHasContent;
    public $flag_html = 0;
    public $flag_plain = 0;
    public $includeMedia = 0;
    public $flowedFormat = 0;
    public $user_dmailerLang = 'en';
    public $testmail = false;

    /**
     * @var bool Whether there was a sending error in the current execution
     */
    public $hasSendingError = false;

    /*
     * @var string
     */
    public $charset = '';

    /*
     * @var string
     * Todo: Symfony mailer does not have an encoding you can change. Check if this has side effects
     */
    public $encoding = '';

    /*
     * @var array the mail parts (HTML and Plain, incl. href and link to media)
     */
    public $theParts = [];

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

    public $tempFileList = [];

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

    protected $message = '';

    protected $notificationJob = false;

    public $jumperURL_prefix = '';
    public $jumperURL_useMailto = '';

    /** @var bool|int */
    public $jumperURL_useId = false;

    protected function getCharsetConverter()
    {
        if (!$this->charsetConverter) {
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
        $sys_dmail_uid = $row['uid'];

        $this->logger->info('<-- Direct mail start sys_dmail_uid=' . $sys_dmail_uid . ' -->');

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

        $this->replyto_email = $row['replyto_email'] ?? '';
        $this->replyto_name  = ($row['replyto_name'] ? $this->getCharsetConverter()->conv($row['replyto_name'], $this->backendCharset, $this->charset) : '');

        $this->organisation  = ($row['organisation'] ? $this->getCharsetConverter()->conv($row['organisation'], $this->backendCharset, $this->charset) : '');

        $this->priority      = DirectMailUtility::intInRangeWrapper((int)$row['priority'], 1, 5);
        $this->mailer        = 'TYPO3 Direct Mail module';
        $this->authCode_fieldList = $row['authcode_fieldList'] ?? '' ?: 'uid';

        $this->dmailer['sectionBoundary'] = '<!--DMAILER_SECTION_BOUNDARY';
        $this->dmailer['html_content']    = $this->theParts['html']['content'] ?? '';
        $this->dmailer['plain_content']   = $this->theParts['plain']['content'] ?? '';
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
            if(!isset($this->dmailer['boundaryParts_html'][$bKey]['mediaList'])) {
                $this->dmailer['boundaryParts_html'][$bKey]['mediaList'] = '';
            }
            foreach ($mediaParts as $part) {
                $this->dmailer['boundaryParts_html'][$bKey]['mediaList'] .= ',' . strtok($part, '.');
            }
        }
        $this->dmailer['boundaryParts_plain'] = explode($this->dmailer['sectionBoundary'], '_END-->' . $this->dmailer['plain_content']);
        foreach ($this->dmailer['boundaryParts_plain'] as $bKey => $bContent) {
            $this->dmailer['boundaryParts_plain'][$bKey] = explode('-->', $bContent, 2);
        }

        $this->flag_html    = (($this->theParts['html']['content'] ?? false) ? 1 : 0);
        $this->flag_plain   = (($this->theParts['plain']['content'] ?? false) ? 1 : 0);
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
        $content = preg_replace('/[\t\v\n\r\f]*<!(?:--[^\[\<\>][\s\S]*?--\s*)?>[\t\v\n\r\f]*/', '', $content);
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
        $uppercaseFieldsArray = ['name', 'firstname'];
        foreach ($uppercaseFieldsArray as $substField) {
            $subst = $this->getCharsetConverter()->conv($recipRow[$substField], $this->backendCharset, $this->charset);
            $markers['###USER_' . strtoupper($substField) . '###'] = strtoupper($subst);
        }

        // Hook allows to manipulate the markers to add salutation etc.
        if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailMarkersHook'])) {
            $mailMarkersHook =& $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailMarkersHook'];
            if (is_array($mailMarkersHook)) {
                $hookParameters = [
                    'row'     => &$recipRow,
                    'markers' => &$markers,
                ];
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
     * @param int $logUid The ID of the log entry
     *
     * @return int Which kind of email is sent, 1 = HTML, 2 = plain, 3 = both
     */
    public function dmailer_sendAdvanced(array $recipRow, $tableNameChar, int $logUid)
    {
        $returnCode = 0;
        $tempRow = [];

        $this->logger->info('E-mail recipient: ' . $recipRow['email']);

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
            $authCode = AuthCodeUtility::getHmac($recipRow, $this->authCode_fieldList);

            $additionalMarkers = [
                    // Put in the tablename of the userinformation
                '###SYS_TABLE_NAME###'      => $tableNameChar,
                    // Put in the uid of the mail-record
                '###SYS_MAIL_ID###'         => $this->dmailer['sys_dmail_uid'],
                '###SYS_AUTHCODE###'        => $authCode,
                    // Put in the unique message id in HTML-code
                $this->dmailer['messageID'] => $uniqMsgId,
            ];

            $this->mediaList = '';
            $this->theParts['html']['content'] = '';
            if ($this->flag_html && ($recipRow['module_sys_dmail_html'] || $tableNameChar == 'P')) {
                $tempContent_HTML = $this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_html'], $recipRow['sys_dmail_categories_list']);
                if ($this->mailHasContent) {
                    $tempContent_HTML = $this->replaceMailMarkers($tempContent_HTML, $recipRow, $additionalMarkers);
                    $this->theParts['html']['content'] = $this->encodeMsg($tempContent_HTML);
                    $returnCode|=1;
                    $this->logger->info('$this->mailHasContent HTML TRUE: ' . $recipRow['email'] .' $returnCode: ' . $returnCode);
                } else {
                    $this->logger->warning('$this->mailHasContent HTML FALSE: no boundaryParts_html? $tableNameChar: ' . $tableNameChar . ' email: ' . $recipRow['email'] .' $returnCode:' . $returnCode);
                    $this->logger->info('$tempContent_HTML: ' . print_r($tempContent_HTML, true));
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
                    $this->logger->info('$this->mailHasContent PLAIN TRUE: ' . $recipRow['email'] .' $returnCode: ' . $returnCode);
                } else {
                    $this->logger->info('$this->mailHasContent PLAIN FALSE: $tableNameChar: ' . $tableNameChar . ' email: ' . $recipRow['email'] .' $returnCode: ' . $returnCode);
                }
            }

            $this->TYPO3MID = $midRidId . '-' . md5($midRidId);
            $this->dmailer['sys_dmail_rec']['return_path'] = str_replace('###XID###', $midRidId, $this->dmailer['sys_dmail_rec']['return_path']);

            // check if the email valids
            $recipient = [];
            if (GeneralUtility::validEmail($recipRow['email'])) {
                $email = $recipRow['email'];
                $name = $this->ensureCorrectEncoding($recipRow['name']);

                $recipient = $this->createRecipient($email, $name);
            } else {
                $this->logger->warning('E-mail invalid: ' . $recipRow['email']);
            }

            if ($returnCode && !empty($recipient)) {
                $mailWasSent = $this->sendTheMail($recipient, $logUid, $recipRow);
                if (!$mailWasSent) {
                    // Set return code to 99
                    $returnCode = 99;
                }
            } else {
                $this->logger->warning('No e-mail for user ' . $recipRow['firstname'] . ' - ' . $recipRow['name']);
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
        if ($this->theParts['html']['content'] ?? false) {
            $this->theParts['html']['content'] = $this->encodeMsg($this->dmailer_getBoundaryParts($this->dmailer['boundaryParts_html'], -1));
        } else {
            $this->theParts['html']['content'] = '';
        }
        if ($this->theParts['plain']['content'] ?? false) {
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
            $cP['mediaList'] = $cP['mediaList'] ?? '';
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
    public function getListOfRecipentCategories(string $table, int $uid): string
    {
        if ($table === 'PLAINLIST') {
            return '';
        }

        $relationTable = $GLOBALS['TCA'][$table]['columns']['module_sys_dmail_category']['config']['MM'];

        $rows = GeneralUtility::makeInstance(TempRepository::class)->getListOfRecipentCategories($table, $relationTable, $uid);

        $list = '';
        if($rows && count($rows)) {
            foreach($rows as $row) {
                $list .= $row['uid_foreign'] . ',';
            }
        }

        return rtrim($list, ',');
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
        $enableFields['tt_address'] = 'tt_address.deleted=0 AND tt_address.hidden=0';
        $enableFields['fe_users']   = 'fe_users.deleted=0 AND fe_users.disable=0';

        $c = 0;
        $returnVal = true;
        if (is_array($query_info['id_lists'])) {
            foreach ($query_info['id_lists'] as $table => $listArr) {
                if (is_array($listArr)) {
                    $ct = 0;
                    // Find tKey
                    switch ($table) {
                        case 'tt_address':
                        case 'fe_users':
                            $tKey = substr($table, 0, 1);
                            break;
                        case 'PLAINLIST':
                            $tKey = 'P';
                            break;
                        default:
                            $tKey = 'u';
                    }

                    // Send mails
                    $sendIds = GeneralUtility::makeInstance(SysDmailMaillogRepository::class)->dmailerGetSentMails((int)$mid, $tKey);
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
                    }
                    else {
                        $idList = implode(',', $listArr);
                        if ($idList) {
                            $rows = GeneralUtility::makeInstance(TempRepository::class)->selectForMasssendList($table, $idList, ($this->sendPerCycle + 1), $sendIds);
                            if($rows && count($rows)) {
                                foreach($rows as $recipRow) {
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
                    }

                    $this->logger->debug($this->getLanguageService()->getLL('dmailer_sending') . ' ' . $ct . ' ' . $this->getLanguageService()->getLL('dmailer_sending_to_table') . ' ' . $table);
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
    public function shipOfMail(int $mid, array $recipRow, string $tableKey): void
    {
        $sysDmailMaillogRepository = GeneralUtility::makeInstance(SysDmailMaillogRepository::class);
        if ($sysDmailMaillogRepository->dmailerIsSend($mid, (int)$recipRow['uid'], $tableKey) === false) {
            
            /*
             * In the patched version, dmail_isSend says it was not sent at all
             * or not sent successfully. Therefore we perform an additional check
             * to see if there is already a log entry.
             */
            $logEntryForRecipient = $this->dmailer_getMailLogEntryForRecipient($mid, $recipRow['uid'], $tableKey);
            
            $pt = self::getMilliseconds();
            $recipRow = self::convertFields($recipRow);

            // We check for false here because $logEntryForRecipient would be NULL
            // in case of error
            $logUid = false;
            if ($logEntryForRecipient === false) {

                // write to dmail_maillog table. if it can be written, continue with sending.
                // if not, stop the script and report error
                $logUid = $sysDmailMaillogRepository->dmailerAddToMailLog($mid, $tableKey . '_' . $recipRow['uid'], strlen($this->message), self::getMilliseconds() - $pt, 0, $recipRow['email']);
            }

            if ($logUid || is_array($logEntryForRecipient)) {
                $values = [
                    'logUid' => (int)$logUid,
                    'html_sent' => (int)$this->dmailer_sendAdvanced($recipRow, $tableKey,  $logUid),
                    'parsetime' => self::getMilliseconds() - $pt,
                    'size' => strlen($this->message)
                ];
                $ok = $sysDmailMaillogRepository->updateSysDmailMaillogForShipOfMail($values);

                $this->logger->info('Logging start of e-mail processing: ' . $recipRow['email'] . ': is log OK? ' . $ok);

                if ($ok === false) {
                    $message = 'Unable to update Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid=' . $mid . ')';
                    $this->logger->critical($message);
                    die($message);
                }
            }
            else {
                // stop the script if dummy log can't be made
                $message = 'Unable to update Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid=' . $mid . ')';
                $this->logger->critical($message);
                die($message);
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
    public static function convertFields(array $recipRow): array
    {
        // Compensation for the fact that fe_users has the field 'telephone' instead of 'phone'
        if ($recipRow['telephone'] ?? false) {
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
    public function dmailer_setBeginEnd(int $mid, string $key)
    {
        $subject = '';
        $message = '';

        GeneralUtility::makeInstance(SysDmailRepository::class)->dmailerSetBeginEnd($mid, $key);

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

        $this->logger->debug($subject . ': ' . $message);

        if ($this->notificationJob === true) {
            $from_name = $this->getCharsetConverter()->conv($this->from_name, $this->charset, $this->backendCharset) ?? '';

            $mail = GeneralUtility::makeInstance(MailMessage::class);
            $mail->setTo($this->from_email, $from_name);
            $mail->setFrom($this->from_email, $from_name);
            $mail->setSubject($subject);

            if ($this->replyto_email !== '') {
                $mail->setReplyTo($this->replyto_email);
            }

            $mail->text($message);
            $mail->send();
        }
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
        $this->notificationJob = (bool)($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['notificationJob']);

        if (!is_object($this->getLanguageService())) {
            $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageService::class);
            $language = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['cron_language'] ? $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['cron_language'] : $this->user_dmailerLang;
            $this->getLanguageService()->init(trim($language));
        }

        // always include locallang file
        $this->getLanguageService()->includeLLFile('EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf');

        $pt = self::getMilliseconds();
        $row = GeneralUtility::makeInstance(SysDmailRepository::class)->selectForRuncron();
        $this->logger->debug($this->getLanguageService()->getLL('dmailer_invoked_at') . ' ' . date('h:i:s d-m-Y'));

        if (is_array($row)) {
            $this->logger->debug($this->getLanguageService()->getLL('dmailer_sys_dmail_record') . ' ' . $row['uid'] . ', \'' . $row['subject'] . '\'' . $this->getLanguageService()->getLL('dmailer_processed'));
            $this->dmailer_prepare($row);
            $query_info = unserialize($row['query_info']);

            if (!$row['scheduled_begin']) {
                // Hook to alter the list of recipients
                if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['queryInfoHook'])) {
                    $queryInfoHook =& $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['queryInfoHook'];
                    if (is_array($queryInfoHook)) {
                        $hookParameters = [
                            'row'    => $row,
                            'query_info' => &$query_info,
                        ];
                        $hookReference = &$this;
                        foreach ($queryInfoHook as $hookFunction) {
                            GeneralUtility::callUserFunction($hookFunction, $hookParameters, $hookReference);
                        }
                    }
                }
                $this->dmailer_setBeginEnd((int)$row['uid'], 'begin');
            }

            $finished = $this->dmailer_masssend_list($query_info, $row['uid']);

            if ($finished && !$this->hasSendingError) {
                // We only mark the mailing as finished if there were no errors
                // If there were errors, failed recipients are retried in the next run
                $this->dmailer_setBeginEnd((int)$row['uid'], 'end');
            }
        } else {
            $this->logger->debug($this->getLanguageService()->getLL('dmailer_nothing_to_do'));
        }

        $parsetime = self::getMilliseconds() - $pt;
        $this->logger->debug($this->getLanguageService()->getLL('dmailer_ending') . ' ' . $parsetime . ' ms');
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
        $host = $this->getHostname();
        if (!$host || $host == '127.0.0.1' || $host == 'localhost' || $host == 'localhost.localdomain') {
            $host = ($GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename'] ? preg_replace('/[^A-Za-z0-9_\-]/', '_', $GLOBALS['TYPO3_CONF_VARS']['SYS']['sitename']) : 'localhost') . '.TYPO3';
        }

        $idLeft = time() . '.' . uniqid();
        $idRight = !empty($host) ? $host : 'symfony.generated';
        $this->messageid = $idLeft . '@' . $idRight;

        // Default line break for Unix systems.
        $this->linebreak = LF;
        // Line break for Windows. This is needed because PHP on Windows systems
        // send mails via SMTP instead of using sendmail, and thus the linebreak needs to be \r\n.
        if (Environment::isWindows()) {
            $this->linebreak = CRLF;
        }

        // Mailer engine parameters
        $this->sendPerCycle = $user_dmailer_sendPerCycle;
        $this->user_dmailerLang = $user_dmailer_lang;
        if (isset($this->nonCron) && !$this->nonCron) {
            $this->logger->debug('Starting directmail cronjob');
            // write this temp file for checking the engine in the status module
            $this->dmailer_log('starting directmail cronjob');
        }
    }

    /**
     * Set the content from $this->theParts['html'] or $this->theParts['plain'] to the mailbody
     *
     * @return void
     * @var MailMessage $mailer Mailer Object
     */
    public function setContent(&$mailer)
    {
        // todo: css??
        // iterate through the media array and embed them
        if ($this->includeMedia && !empty($this->theParts['html']['content'])) {
            // extract all media path from the mail message
            $this->extractMediaLinks();
            foreach ($this->theParts['html']['media'] as $media) {
                // TODO: why are there table related tags here?
                if (($media['tag'] === 'img' || $media['tag'] === 'table' || $media['tag'] === 'tr' || $media['tag'] === 'td') && !$media['use_jumpurl'] && !$media['do_not_embed']) {
                    if (ini_get('allow_url_fopen')) {
                        if (($fp = fopen($media['absRef'], 'r')) !== false ) {
                            $mailer->embed($fp, basename($media['absRef']));
                        }
                    } else {
                        $mailer->embed(GeneralUtility::getUrl($media['absRef']), basename($media['absRef']));
                    }
                    $this->theParts['html']['content'] = str_replace($media['subst_str'], 'cid:' . basename($media['absRef']), $this->theParts['html']['content']);
                }
            }
            // remove ` do_not_embed="1"` attributes
            $this->theParts['html']['content'] = str_replace(' do_not_embed="1"', '', $this->theParts['html']['content']);
        }

        // set the html content
        if ($this->theParts['html']['content']) {
            $mailer->html($this->theParts['html']['content']);
        }
        // set the plain content as alt part
        if ($this->theParts['plain']['content']) {
            $mailer->text($this->theParts['plain']['content']);
        }

        // handle FAL attachments
        if ((int)$this->dmailer['sys_dmail_rec']['attachment'] > 0) {
            $files = DirectMailUtility::getAttachments((int)$this->dmailer['sys_dmail_rec']['uid']);
            /** @var FileReference $file */
            foreach ($files as $file) {
                $filePath = Environment::getPublicPath() . '/' . $file->getPublicUrl();
                $mailer->attachFromPath($filePath);
            }
        }
    }

    /**
     * If available, get the existing mail log entry for a recipient
     *
     * @param int $mailId Newsletter ID. UID of the sys_dmail record
     * @param int $recipientId Recipient UID
     * @param string $recipientTable Recipient table
     *
     * @return array|false|null
     */
    protected function dmailer_getMailLogEntryForRecipient($mailId, $recipientId, $recipientTable)
    {
        $tableName = 'sys_dmail_maillog';
        $queryBuilder = $this->getQueryBuilder($tableName);
        $queryBuilder->select('uid')
            ->from($tableName)
            ->where($queryBuilder->expr()->eq('rid', $queryBuilder->createNamedParameter($recipientId, \PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('rtbl', $queryBuilder->createNamedParameter($recipientTable)))
            ->andWhere($queryBuilder->expr()->eq('mid', $queryBuilder->createNamedParameter((int)$mailId, \PDO::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->eq('response_type', '0'))
            ->execute();
        return $queryBuilder->execute()->fetchAssociative();
    }

    /**
     * Send of the email using php mail function.
     *
     * @param Address|string   $recipient The recipient to send the mail to
     * @param int $logUid  The ID of the log entry
     * @param array     $recipRow  Recipient's data array
     *
     * @return	bool
     */
    public function sendTheMail($recipient, int $logUid = 0, $recipRow = null)
    {
        /** @var MailMessage $mailer */
        $mailer = GeneralUtility::makeInstance(MailMessage::class);
        $mailer
            ->from(new Address($this->from_email, $this->from_name))
            ->to($recipient)
            ->subject($this->subject)
            ->priority($this->priority);

        if ($this->replyto_email) {
            $mailer->replyTo(new Address($this->replyto_email, $this->replyto_name));
        } else {
            $mailer->replyTo(new Address($this->from_email, $this->from_name));
        }

        if (GeneralUtility::validEmail($this->dmailer['sys_dmail_rec']['return_path'])) {
            $mailer->sender($this->dmailer['sys_dmail_rec']['return_path']);
        }

        // TODO: setContent should set the images (includeMedia) or add attachment
        $this->setContent($mailer);

        // setting additional header
        // organization and TYPO3MID
        $header = $mailer->getHeaders();
        if ($this->TYPO3MID) {
            $header->addTextHeader('X-TYPO3MID', $this->TYPO3MID);
        }

        if ($this->organisation) {
            $header->addTextHeader('Organization', $this->organisation);
        }

        // Hook to edit or add the mail headers
        if (isset($GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailHeadersHook'])) {
            $mailHeadersHook =& $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/direct_mail']['res/scripts/class.dmailer.php']['mailHeadersHook'];
            if (is_array($mailHeadersHook)) {
                $hookParameters = [
                    'row'     => &$recipRow,
                    'header' => &$header,
                ];
                $hookReference = &$this;
                foreach ($mailHeadersHook as $hookFunction) {
                    GeneralUtility::callUserFunction($hookFunction, $hookParameters, $hookReference);
                }
            }
        }

        if (is_array($recipient)) {
            $email = implode(',', $recipient);
            $emailList = $email;
        } else {
            $email = $recipient;
            if ($email instanceof Address) {
                $emailList = $email->getAddress();
            } else {
                $emailList = $email;
            }
        }
        $this->logger->info('E-mail will be sent to: ' . $emailList);

        try {
            $sent = $mailer->send();

            $this->logger->info('According to Mailer, mail to ' . $recipRow['email'] . ' was sent to number of recipients: ' . $sent);
            
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
            return true;
        } catch (\Exception $e) {
            $this->hasSendingError = true;
            $this->logger->warning(sprintf('E-mail could not be sent to %s: %s (%s)', $emailList, $e->getMessage(), $e->getCode()));

            if ($logUid === 0) {
                return false;
            }

            // Log failed attempts
            $tableName = 'sys_dmail_maillog';
            /** @var Connection $connection */
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable($tableName);

            /** @var DriverStatement $statement */
            $statement = $connection->prepare(
                sprintf(
                    'UPDATE sys_dmail_maillog SET failed_sending_attempts = failed_sending_attempts + 1 WHERE uid=%d',
                    $logUid
                )
            );
            $statement->execute();
            return false;
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

            $user = '';
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
     * @param string $logMsg Log message
     */
    public function dmailer_log(string $logMsg): void
    {
        $content = time() . ' => ' . $logMsg . LF;
        $logfilePath = Environment::getPublicPath() . '/typo3temp/tx_directmail_dmailer_log.txt';
        GeneralUtility::writeFile($logfilePath, $content);
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
        if (!isset($this->jumperURL_prefix) || !$this->jumperURL_prefix) {
            return $content;
        }

        $jumpUrlCounter = 1;
        return preg_replace_callback(
            '/http[s]?:\/\/\S+/',
            function ($urlMatches) use (&$jumpUrlCounter) {
                $url = $urlMatches[0];
                if (strpos($url, '&no_jumpurl=1') !== false) {
                    // A link parameter "&no_jumpurl=1" allows to disable jumpurl for plain text links
                    $url = str_replace('&no_jumpurl=1', '', $url);
                } elseif ($this->jumperURL_useId) {
                    $this->theParts['plain']['link_ids'][$jumpUrlCounter] = $url;
                    $url = $this->jumperURL_prefix . '-' . $jumpUrlCounter;
                    $jumpUrlCounter++;
                } else {
                    $url = $this->jumperURL_prefix . str_replace('%2F', '/', rawurlencode($url));
                }
                return $url;
            },
            $content
        );
    }

    /**
     * Wrapper function. always quoted_printable
     *
     * @param string $content The content that will be encoded
     *
     * @return string The encoded content
     * @deprecated WTF?
     */
    public function encodeMsg($content)
    {
        return $content;
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
        $this->theParts['html']['media'] = [];

        $htmlContent = $this->theParts['html']['content'];
        $attribRegex = $this->tag_regex(['img', 'table', 'td', 'tr', 'body', 'iframe', 'script', 'input', 'embed']);
        $imageList = '';

        // split the document by the beginning of the above tags
        $codepieces = preg_split($attribRegex, $htmlContent);
        $len = strlen($codepieces[0]);
        $pieces = count($codepieces);
        $reg = [];
        for ($i = 1; $i < $pieces; $i++) {
            $tag = strtolower(strtok(substr($htmlContent, $len + 1, 10), ' '));
            $len += strlen($tag) + strlen($codepieces[$i]) + 2;
            $dummy = preg_match('/[^>]*/', $codepieces[$i], $reg);

            // Fetches the attributes for the tag
            $attributes = $this->get_tag_attributes($reg[0]);
            $imageData = [];

            // Finds the src or background attribute
            $imageData['ref'] = ($attributes['src'] ?? $attributes['background'] ?? '');
            if ($imageData['ref']) {
                // find out if the value had quotes around it
                $imageData['quotes'] = (substr($codepieces[$i], strpos($codepieces[$i], $imageData['ref']) - 1, 1) == '"') ? '"' : '';
                // subst_str is the string to look for, when substituting lateron
                $imageData['subst_str'] = $imageData['quotes'] . $imageData['ref'] . $imageData['quotes'];
                if ($imageData['ref'] && !strstr($imageList, '|' . $imageData['subst_str'] . '|')) {
                    $imageList .= '|' . $imageData['subst_str'] . '|';
                    $imageData['absRef'] = $this->absRef($imageData['ref']);
                    $imageData['tag'] = $tag;
                    $imageData['use_jumpurl'] = (isset($attributes['dmailerping']) && $attributes['dmailerping']) ? 1 : 0;
                    $imageData['do_not_embed'] = !empty($attributes['do_not_embed']);
                    $this->theParts['html']['media'][] = $imageData;
                }
            }
        }

        // Extracting stylesheets
        $attribRegex = $this->tag_regex(['link']);
        // Split the document by the beginning of the above tags
        $codepieces = preg_split($attribRegex, $htmlContent);
        $pieces = count($codepieces);
        for ($i = 1; $i < $pieces; $i++) {
            $dummy = preg_match('/[^>]*/', $codepieces[$i], $reg);
            // fetches the attributes for the tag
            $attributes = $this->get_tag_attributes($reg[0]);
            $imageData = [];
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
        $attribRegex = $this->tag_regex(['a', 'form', 'area']);

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
            $hrefData = [];
            $hrefData['ref'] = ($attributes['href'] ?? '') ?: ($attributes['action'] ?? '');
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
                    $hrefData['no_jumpurl'] = intval(trim(($attributes['no_jumpurl'] ?? ''), '"')) ? 1 : 0;
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
                if (!strstr($linkList, '|' . $hrefData['subst_str'] . '|')) {
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
        $info = [];
        if (strpos(' ' . $htmlCode, '<frame ')) {
            $attribRegex = $this->tag_regex('frame');
            // Splits the document by the beginning of the above tags
            $codepieces = preg_split($attribRegex, $htmlCode, 1000000);
            $pieces = count($codepieces);
            for ($i = 1; $i < $pieces; $i++) {
                $dummy = preg_match('/[^>]*/', $codepieces[$i], $reg);
                // Fetches the attributes for the tag
                $attributes = $this->get_tag_attributes($reg[0]);
                $frame = [];
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
        $tags = (!is_array($tags) ? [$tags] : $tags);
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
        $attributes = [];
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
        if ($info['scheme'] ?? false) {
            // if ref is an url
            // do nothing
        } elseif (preg_match('/^\//', $ref)) {
            // if ref is an absolute link
            $addr = parse_url($this->theParts['html']['path']);
            $ref = $addr['scheme'] . '://' . $addr['host'] . (($addr['port'] ?? false) ? ':' . $addr['port'] : '') . $ref;
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
     * Get the fully-qualified domain name of the host
     * Copy from TYPO3 v9.5, will be removed in TYPO3 v10.0
     *
     * @param bool $requestHost Use request host (when not in CLI mode).
     * @return string The fully-qualified host name.
     */
    protected static function getHostname($requestHost = true)
    {
        $host = '';
        // If not called from the command-line, resolve on getIndpEnv()
        if ($requestHost && !Environment::isCli()) {
            $host = GeneralUtility::getIndpEnv('HTTP_HOST');
        }
        if (!$host) {
            // will fail for PHP 4.1 and 4.2
            $host = @php_uname('n');
            // 'n' is ignored in broken installations
            if (strpos($host, ' ')) {
                $host = '';
            }
        }
        // We have not found a FQDN yet
        if ($host && strpos($host, '.') === false) {
            $ip = gethostbyname($host);
            // We got an IP address
            if ($ip != $host) {
                $fqdn = gethostbyaddr($ip);
                if ($ip != $fqdn) {
                    $host = $fqdn;
                }
            }
        }
        if (!$host) {
            $host = 'localhost.localdomain';
        }
        return $host;
    }

    /**
     * Returns LanguageService
     *
     * @return LanguageService
     */
    protected function getLanguageService()
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Creates an address object ready to be used with the symonfy mailer
     *
     * @param string $email
     * @param string|NULL $name
     * @return Address
     */
    protected function createRecipient($email, $name = NULL)
    {
        if (!empty($name)) {
            $recipient = new Address($email, $name);
        } else {
            $recipient = new Address($email);
        }

        return $recipient;
    }

    /**
     * @param string $payload
     * @return string
     */
    protected function ensureCorrectEncoding($payload)
    {
        return $this
            ->getCharsetConverter()
            ->conv(
                $payload,
                $this->backendCharset,
                $this->charset
            );
    }

    /**
     * Gets the unixtime as milliseconds.
     *
     * @return int The unixtime as milliseconds
     */
    public static function getMilliseconds()
    {
        return round(microtime(true) * 1000);
    }

    protected function getConnectionPool(): ConnectionPool
    {
        return GeneralUtility::makeInstance(ConnectionPool::class);
    }

    protected function getConnection(string $table): Connection
    {
        return $this->getConnectionPool()->getConnectionForTable($table);
    }

    protected function getQueryBuilder(string $table): QueryBuilder
    {
        return $this->getConnectionPool()->getQueryBuilderForTable($table);
    }
}
