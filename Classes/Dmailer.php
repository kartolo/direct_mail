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

use DirectMailTeam\DirectMail\Repository\SysDmailMaillogRepository;
use DirectMailTeam\DirectMail\Repository\SysDmailRepository;
use DirectMailTeam\DirectMail\Repository\TempRepository;
use DirectMailTeam\DirectMail\Utility\AuthCodeUtility;
use DirectMailTeam\DirectMail\Utility\FetchUtility;
use DirectMailTeam\DirectMail\Utility\Typo3ConfVarsUtility;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Mail\MailMessage;
use TYPO3\CMS\Core\Resource\FileReference;
use TYPO3\CMS\Core\Service\MarkerBasedTemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Class, doing the sending of Direct-mails, eg. through a cron-job
 *
 * @author		Kasper Skårhøj <kasperYYYY@typo3.com>
 * @author      Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 */
class Dmailer implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    /*
     * @var int amount of mail sent in one batch
     */
    protected int $sendPerCycle = 50;
    protected bool $mailHasContent = false;
    protected bool $flagHtml = false;
    protected bool $flagPlain = false;
    protected int $includeMedia = 0;
    protected bool $flowedFormat = false;
    protected string $userDmailerLang = 'en';
    protected bool $testmail = false;
    protected string $charset = '';

    /*
     * @var string
     * Todo: Symfony mailer does not have an encoding you can change. Check if this has side effects
     * @TODO Where it is used?
     */
    //protected string $encoding = '';

    /*
     * @var array the mail parts (HTML and Plain, incl. href and link to media)
     */
    protected array $theParts = [];

    /*
     * @var string the mail message ID
     * todo: do we still need this
     */
    protected string $messageid = '';

    /*
     * @var string the subject of the mail
     */
    protected string $subject = '';

    /*
     * @var string the sender mail
     */
    protected string $fromEmail = '';

    /*
     * @var string the sender's name
     */
    protected string $fromName = '';

    /*
     * @var string organisation of the mail
     */
    protected string $organisation = '';

    /*
     * special header to identify returned mail
     *
     * @var string
     */
    protected string $TYPO3MID = '';
    protected string $replyToEmail = '';
    protected string $replyToName = '';
    protected int $priority = 0;

    /*
     * @TODO Where it is used?
     */
    //protected string $mailer = '';
    protected string $authCodeFieldList = '';
    protected array $dmailer = [];

    /*
     * @TODO Where it is used?
     */
    //protected string $mediaList = '';

    /*
     * @TODO Where it is used?
     */
    //protected array $tempFileList = [];

    //in TYPO3 9 LanguageService->charset has been removed because backend charset is always utf-8
    protected string $backendCharset = 'utf-8';

    /*
     * @var integer Usergroup that is simulated when fetching the mail content
     * @TODO Where it is used?
     */
    protected int $simulateUsergroup = 0;

    /**
     * @var CharsetConverter
     */
    protected $charsetConverter;
    protected string $message = '';
    protected bool $notificationJob = false;
    protected string $jumperURLPrefix = '';
    protected bool $jumperURLUseMailto = false;
    protected bool $jumperURLUseId = false;
    protected bool $nonCron = false;

    public function setNonCron(bool $nonCron): void
    {
        $this->nonCron = $nonCron;
    }

    public function setSimulateUsergroup(int $simulateUsergroup): void
    {
        $this->simulateUsergroup = $simulateUsergroup;
    }

    public function setCharset(string $charset): void
    {
        $this->charset = $charset;
    }

    public function getCharset(): string
    {
        return $this->charset;
    }

    public function setPartHtmlConfig(string $key, $value): void
    {
        $this->theParts['html'][$key] = $value;
    }

    public function getPartHtmlConfig(string $key)
    {
        return $this->theParts['html'][$key];
    }

    public function getPartPlainConfig(string $key)
    {
        return $this->theParts['plain'][$key];
    }

    public function setPartMessageIdConfig(string $messageId): void
    {
        $this->theParts['messageid'] = $messageId;
    }

    public function getParts(): array
    {
        return $this->theParts;
    }

    public function setIncludeMedia(int $includeMedia): void
    {
        $this->includeMedia = $includeMedia;
    }

    public function setTestmail(bool $testmail): void
    {
        $this->testmail = $testmail;
    }

    public function getTestmail(): bool
    {
        return $this->testmail;
    }

    public function getMessageid(): string
    {
        return $this->messageid;
    }

    public function setJumperURLPrefix(string $jumperURLPrefix): void
    {
        $this->jumperURLPrefix = $jumperURLPrefix;
    }

    public function setJumperURLUseMailto(bool $jumperURLUseMailto): void
    {
        $this->jumperURLUseMailto = $jumperURLUseMailto;
    }

    public function setJumperURLUseId(bool $jumperURLUseId): void
    {
        $this->jumperURLUseId = $jumperURLUseId;
    }

    protected function getCharsetConverter()
    {
        if (!$this->charsetConverter) {
            $this->charsetConverter = GeneralUtility::makeInstance(CharsetConverter::class);
        }
        return $this->charsetConverter;
    }

    protected function getMarkerBasedTemplateService(): MarkerBasedTemplateService
    {
        return GeneralUtility::makeInstance(MarkerBasedTemplateService::class);
    }

    /**
     * Preparing the Email. Headers are set in global variables
     *
     * @param array $row Record from the sys_dmail table
     */
    public function prepare(array $row): void
    {
        if ($row['flowedFormat']) {
            $this->flowedFormat = true;
        }
        if ($row['charset']) {
            $this->charset = ($row['type'] == 0) ? 'utf-8' : $row['charset'];
        }

        //$this->encoding          = $row['encoding'];
        $this->theParts          = unserialize(base64_decode($row['mailContent']));
        $this->messageid         = $this->theParts['messageid'];
        $this->subject           = $this->ensureCorrectEncoding($row['subject']);
        $this->fromEmail         = $row['from_email'];
        $this->fromName          = $this->ensureCorrectEncoding($row['from_name']);
        $this->replyToEmail      = $row['replyto_email'] ?? '';
        $this->replyToName       = $this->ensureCorrectEncoding($row['replyto_name']);
        $this->organisation      = $this->ensureCorrectEncoding($row['organisation']);
        $this->priority          = DirectMailUtility::intInRangeWrapper((int)$row['priority'], 1, 5);
        //$this->mailer            = 'TYPO3 Direct Mail module';
        $this->authCodeFieldList = $row['authcode_fieldList'] ?? '' ?: 'uid';

        $this->dmailer['sectionBoundary']    = '<!--DMAILER_SECTION_BOUNDARY';
        $this->dmailer['html_content']       = $this->theParts['html']['content'] ?? '';
        $this->dmailer['plain_content']      = $this->theParts['plain']['content'] ?? '';
        $this->dmailer['messageID']          = $this->theParts['messageid'];
        $this->dmailer['sys_dmail_uid']      = $row['uid'];
        $this->dmailer['sys_dmail_rec']      = $row;
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
            if (!isset($this->dmailer['boundaryParts_html'][$bKey]['mediaList'])) {
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

        $this->flagHtml     = ($this->theParts['html']['content'] ?? false) ? true : false;
        $this->flagPlain    = ($this->theParts['plain']['content'] ?? false) ? true : false;
        $this->includeMedia = $row['includeMedia'];
    }

    /**
     * Removes html comments when outside script and style pairs
     *
     * @param string $content The email content
     *
     * @return string HTML content without comments
     */
    protected function removeHTMLComments(string $content): string
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
     * @return string The processed output stream
     */
    protected function replaceMailMarkers(string $content, array $recipRow, array $markers): string
    {
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

        // replace %23%23%23 with ###, since typolink generated link with urlencode
        $content = str_replace('%23%23%23', '###', $content);

        return $this->getMarkerBasedTemplateService()->substituteMarkerArray($content, $markers);
    }

    /**
     * Replace the marker with recipient data and then send it
     *
     * @param array $recipientRow Recipient's data array
     * @param string $tableNameChar Tablename, from which the recipient come from
     *
     * @return int Which kind of email is sent, 1 = HTML, 2 = plain, 3 = both
     */
    public function sendAdvanced(array $recipientRow, string $tableNameChar): int
    {
        $returnCode = 0;
        foreach($recipientRow as $key => $val) {
            $recipientRow[$key] = is_null($val) ? $val : htmlspecialchars($val);
        }

        // Workaround for strict checking of email addresses in TYPO3
        // (trailing newline = invalid address)
        $recipientRow['email'] = trim($recipientRow['email']);

        // check if the email valids
        if (GeneralUtility::validEmail($recipientRow['email'])) {
            $midRidId  = 'MID' . $this->dmailer['sys_dmail_uid'] . '_' . $tableNameChar . $recipientRow['uid'];

            $additionalMarkers = [
                // Put in the tablename of the userinformation
                '###SYS_TABLE_NAME###'      => $tableNameChar,
                // Put in the uid of the mail-record
                '###SYS_MAIL_ID###'         => $this->dmailer['sys_dmail_uid'],
                '###SYS_AUTHCODE###'        => AuthCodeUtility::getHmac($recipientRow, $this->authCodeFieldList),
                 // Put in the unique message id in HTML-code
                $this->dmailer['messageID'] => md5(microtime()) . '_' . $midRidId,
            ];
            $rowFieldsArray = Typo3ConfVarsUtility::getDMConfigMergedFields();
            if (count($rowFieldsArray)) {
                foreach ($rowFieldsArray as $substField) {
                    $additionalMarkers['###USER_' . $substField . '###'] = $this->ensureCorrectEncoding($recipientRow[$substField] ?? '');
                }
            }
            // uppercase fields with uppercased values
            $uppercaseFieldsArray = ['name', 'firstname'];
            foreach ($uppercaseFieldsArray as $substField) {
                $subst = $this->ensureCorrectEncoding($recipientRow[$substField] ?? '');
                $additionalMarkers['###USER_' . strtoupper($substField) . '###'] = strtoupper($subst);
            }

            $this->theParts['html']['content'] = '';
            if ($this->flagHtml && (($recipientRow['module_sys_dmail_html'] ?? false) || $tableNameChar == 'P')) {
                $tempContentHTML = $this->getBoundaryParts($this->dmailer['boundaryParts_html'], $recipientRow['sys_dmail_categories_list'] ?? '');
                if ($this->mailHasContent) {
                    $this->theParts['html']['content'] = $this->replaceMailMarkers($tempContentHTML, $recipientRow, $additionalMarkers);
                    $returnCode |= 1;
                }
            }

            // Plain
            $this->theParts['plain']['content'] = '';
            if ($this->flagPlain) {
                $tempContentPlain = $this->getBoundaryParts($this->dmailer['boundaryParts_plain'], $recipientRow['sys_dmail_categories_list'] ?? '');
                if ($this->mailHasContent) {
                    $tempContentPlain = $this->replaceMailMarkers($tempContentPlain, $recipientRow, $additionalMarkers);
                    if (trim($this->dmailer['sys_dmail_rec']['use_rdct']) || trim($this->dmailer['sys_dmail_rec']['long_link_mode'])) {
                        $tempContentPlain = DirectMailUtility::substUrlsInPlainText(
                            $tempContentPlain,
                            $this->dmailer['sys_dmail_rec']['long_link_mode'] ? 'all' : '76',
                            $this->dmailer['sys_dmail_rec']['long_link_rdct_url']
                        );
                    }
                    $this->theParts['plain']['content'] = $tempContentPlain;
                    $returnCode |= 2;
                }
            }

            $this->TYPO3MID = $midRidId . '-' . md5($midRidId);
            $this->dmailer['sys_dmail_rec']['return_path'] = str_replace('###XID###', $midRidId, $this->dmailer['sys_dmail_rec']['return_path']);

            if ($returnCode) {
                $recipientName = $recipientRow['name'] ?? '';
                if ($recipientName == '') {
                    if ($recipientRow['first_name'] ?? '') {
                        $recipientName = $recipientRow['first_name'] . ' ';
                    }
                    if ($recipientRow['middle_name'] ?? '') {
                        $recipientName .= $recipientRow['middle_name'] . ' ';
                    }
                    $recipientName .= $recipientRow['last_name'] ?? '';
                }
                $recipient = $this->createRecipient($recipientRow['email'], $this->ensureCorrectEncoding($recipientName));
                $this->sendTheMail($recipient, $recipientRow);
            }
        }

        return $returnCode;
    }

    /**
     * Send a simple email (without personalizing)
     *
     * @param array $recipients list of recipient address
     */
    public function sendSimple(array $recipients): bool
    {
        $this->theParts['html']['content']  = ($this->theParts['html']['content'] ?? false) ? $this->getBoundaryParts($this->dmailer['boundaryParts_html'], -1) : '';
        $this->theParts['plain']['content'] = ($this->theParts['plain']['content'] ?? false) ? $this->getBoundaryParts($this->dmailer['boundaryParts_plain'], -1) : '';

        foreach ($recipients as $recipient) {
            $this->sendTheMail($this->createRecipient($recipient));
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
    protected function getBoundaryParts($cArray, $userCategories): string
    {
        $returnVal = '';
        $this->mailHasContent = false;
        $boundaryMax = count($cArray) - 1;
        foreach ($cArray as $bKey => $cP) {
            $key = substr($cP[0], 1);
            $isSubscribed = false;
            $cP['mediaList'] = $cP['mediaList'] ?? '';
            if (!$key || ((int)$userCategories == -1)) {
                $returnVal .= $cP[1];
                //$this->mediaList .= $cP['mediaList'];
                if ($cP[1]) {
                    $this->mailHasContent = true;
                }
            } elseif ($key == 'END') {
                $returnVal .= $cP[1];
                //$this->mediaList .= $cP['mediaList'];
                // There is content and it is not just the header and footer content,
                // or it is the only content because we have no direct mail boundaries.
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
                    //$this->mediaList .= $cP['mediaList'];
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

        $relationTable = $GLOBALS['TCA'][$table]['columns']['module_sys_dmail_category']['config']['MM'] ?? null;
        if ($relationTable === null) {
            return '';
        }

        $rows = GeneralUtility::makeInstance(TempRepository::class)->getListOfRecipentCategories($table, $relationTable, $uid);

        $list = '';
        if ($rows && count($rows)) {
            foreach ($rows as $row) {
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
     * @return	bool
     */
    protected function masssendList(array $query_info, int $mid): bool
    {
        //$enableFields['tt_address'] = 'tt_address.deleted=0 AND tt_address.hidden=0';
        //$enableFields['fe_users']   = 'fe_users.deleted=0 AND fe_users.disable=0';

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
                    } else {
                        $idList = implode(',', $listArr);
                        if ($idList) {
                            $rows = GeneralUtility::makeInstance(TempRepository::class)->selectForMasssendList($table, $idList, ($this->sendPerCycle + 1), $sendIds);
                            if ($rows && count($rows)) {
                                foreach ($rows as $recipRow) {
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

                    $this->logger->debug(
                        $this->getLanguageService()->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf:dmailer_sending') . ' ' . $ct . ' ' . $this->getLanguageService()->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf:dmailer_sending_to_table') . ' ' . $table
                    );
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
     */
    protected function shipOfMail(int $mid, array $recipRow, string $tableKey): void
    {
        $sysDmailMaillogRepository = GeneralUtility::makeInstance(SysDmailMaillogRepository::class);
        if ($sysDmailMaillogRepository->dmailerIsSend($mid, (int)$recipRow['uid'], $tableKey) === false) {
            $parseTimeStart = $this->getMilliseconds();
            $recipRow = $this->convertFields($recipRow);

            /**
             * @TODO
             * $this->message is empty!
             */
            // write to dmail_maillog table. if it can be written, continue with sending.
            // if not, stop the script and report error
            $logUid = $sysDmailMaillogRepository->dmailerAddToMailLog(
                $mid,
                $tableKey . '_' . $recipRow['uid'],
                strlen($this->message),
                $this->getMilliseconds() - $parseTimeStart,
                0,
                $recipRow['email']
            );

            if ($logUid) {
                $values = [
                    'logUid' => $logUid,
                    'html_sent' => (int)$this->sendAdvanced($recipRow, $tableKey),
                    'parsetime' => $this->getMilliseconds() - $parseTimeStart,
                    'size' => strlen($this->message)
                ];
                $ok = $sysDmailMaillogRepository->updateSysDmailMaillogForShipOfMail($values);

                if ($ok === false) {
                    $message = 'Unable to update Log-Entry in table sys_dmail_maillog. Table full? Mass-Sending stopped. Delete each entries except the entries of active mailings (mid=' . $mid . ')';
                    $this->logger->critical($message);
                    die($message);
                }
            } else {
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
    public function convertFields(array $recipRow): array
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
     */
    protected function setBeginEnd(int $mid, string $key): void
    {
        $subject = $this->getLanguageService()->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf:dmailer_mid') . ' ' . $mid . ' ';
        $message = '';

        GeneralUtility::makeInstance(SysDmailRepository::class)->dmailerSetBeginEnd($mid, $key);

        switch ($key) {
            case 'begin':
                $subject .= $this->getLanguageService()->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf:dmailer_job_begin');
                $message = $this->getLanguageService()->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf:');
                break;
            case 'end':
                $subject .= $this->getLanguageService()->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf:dmailer_job_end');
                $message = $this->getLanguageService()->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf:dmailer_job_end');
                break;
            default:
                // do nothing
        }
        $message .= ': ' . date('d-m-y h:i:s');

        $this->logger->debug($subject . ': ' . $message);

        if ($this->notificationJob === true) {
            $fromName = $this->getCharsetConverter()->conv($this->fromName, $this->charset, $this->backendCharset) ?? '';

            $mail = GeneralUtility::makeInstance(MailMessage::class);
            $mail->setTo($this->fromEmail, $fromName);
            $mail->setFrom($this->fromEmail, $fromName);
            $mail->setSubject($subject);

            if ($this->replyToEmail !== '') {
                $mail->setReplyTo($this->replyToEmail);
            }

            $mail->text($message);
            $mail->send();
        }
    }

    /**
     * Called from the dmailerd script.
     * Look if there is newsletter to be sent
     * and do the sending process. Otherwise quit runtime
     */
    public function runcron(): void
    {
        $parseTimeStart = $this->getMilliseconds();
        $this->sendPerCycle = Typo3ConfVarsUtility::getDMConfigSendPerCycle();
        $this->notificationJob = Typo3ConfVarsUtility::getDMConfigNotificationJob();

        if (!is_object($this->getLanguageService())) {
            $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageService::class);
            $language = Typo3ConfVarsUtility::getDMConfigCronLanguage() ?: $this->userDmailerLang;
            $this->getLanguageService()->init(trim($language));
        }

        $row = GeneralUtility::makeInstance(SysDmailRepository::class)->selectForRuncron();
        $this->logger->debug($this->getLanguageService()->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf:dmailer_invoked_at') . ' ' . date('h:i:s d-m-Y'));

        if (is_array($row)) {
            $this->logger->debug(
                $this->getLanguageService()->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf:dmailer_sys_dmail_record') . ' ' . $row['uid'] . ', \'' . $row['subject'] . '\'' . $this->getLanguageService()->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf:dmailer_processed')
            );
            $this->prepare($row);
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
                $this->setBeginEnd((int)$row['uid'], 'begin');
            }

            $finished = $this->masssendList($query_info, $row['uid']);

            if ($finished) {
                $this->setBeginEnd((int)$row['uid'], 'end');
            }
        } else {
            $this->logger->debug($this->getLanguageService()->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf:dmailer_nothing_to_do'));
        }

        $parsetime = $this->getMilliseconds() - $parseTimeStart;
        $this->logger->debug($this->getLanguageService()->sL('LLL:EXT:direct_mail/Resources/Private/Language/locallang_mod2-6.xlf:dmailer_ending') . ' ' . $parsetime . ' ms');
    }

    /**
     * Initializing the MailMessage class and setting the first global variables.
     * Write to log file if it's a cronjob
     */
    public function start(): void
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
        // Line break for Windows. This is needed because PHP on Windows systems
        // send mails via SMTP instead of using sendmail, and thus the linebreak needs to be \r\n.
        //$this->linebreak = Environment::isWindows() ? CRLF : LF;

        // Mailer engine parameters
        if (!$this->nonCron) {
            $this->logger->debug('Starting directmail cronjob');
            // write this temp file for checking the engine in the status module
            $this->dmailerLog('starting directmail cronjob');
        }
    }

    /**
     * Set the content from $this->theParts['html'] or $this->theParts['plain'] to the mailbody
     */
    protected function setContent(MailMessage $mailer): void
    {
        // todo: css??
        // iterate through the media array and embed them
        if ($this->includeMedia && !empty($this->theParts['html']['content'])) {
            // extract all media path from the mail message
            $this->extractMediaLinks();
            foreach ($this->theParts['html']['media'] as $media) {
                // TODO: why are there table related tags here?
                if (in_array($media['tag'], ['img', 'table', 'tr', 'td'], true) && !$media['use_jumpurl'] && !$media['do_not_embed']) {
                    if (ini_get('allow_url_fopen')) {
                        $context = GeneralUtility::makeInstance(FetchUtility::class)->getStreamContext();
                        if (($fp = fopen($media['absRef'], 'r', false, $context)) !== false) {
                            $mailer->embed($fp, basename($media['absRef']));
                        }
                    } else {
                        $mailer->embed(GeneralUtility::makeInstance(FetchUtility::class)->getContents($media['absRef']), basename($media['absRef']));
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
            foreach ($files as $file) {
                $mailer->attachFromPath($file->getForLocalProcessing(), $file->getName());
            }
        }
    }

    protected function sendTheMail(Address $recipient, array $recipientRow = null): void
    {
        /** @var MailMessage $mailer */
        $mailer = GeneralUtility::makeInstance(MailMessage::class);
        $mailer
            ->from($this->createRecipient($this->fromEmail, $this->fromName))
            ->to($recipient)
            ->subject($this->subject)
            ->priority($this->priority);

        if ($this->replyToEmail) {
            $mailer->replyTo($this->createRecipient($this->replyToEmail, $this->replyToName));
        } else {
            $mailer->replyTo($this->createRecipient($this->fromEmail, $this->fromName));
        }

        if (GeneralUtility::validEmail($this->dmailer['sys_dmail_rec']['return_path'])) {
            $mailer->returnPath($this->dmailer['sys_dmail_rec']['return_path']);
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
                    'row'    => &$recipientRow,
                    'header' => &$header,
                ];
                $hookReference = &$this;
                foreach ($mailHeadersHook as $hookFunction) {
                    GeneralUtility::callUserFunction($hookFunction, $hookParameters, $hookReference);
                }
            }
        }

        $mailer->send();
    }

    /**
     * Add HTML to an email
     *
     * @param	string $file String location of the HTML
     *
     * @return	mixed		bool: HTML fetch status. string: if HTML is a frameset.
     */
    public function addHTML(string $file)
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
        $this->setHTML($this->theParts['html']['content']);
        return true;
    }

    /**
     * Fetches the HTML-content from either url or local server file
     *
     * @param	string $url Url of the html to fetch
     *
     * @return bool Whether the data was fetched or not
     */
    protected function fetchHTML(string $url): bool
    {
        // Fetches the content of the page
        $this->theParts['html']['content'] = GeneralUtility::makeInstance(FetchUtility::class)->getContents($url);
        if ($this->theParts['html']['content']) {
            $urlPart = parse_url($url);
            if (Typo3ConfVarsUtility::getDMConfigUseHttpToFetch()) {
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

            //@TODO
            $this->theParts['html']['path'] = $urlPart['scheme'] . '://' . $user . $urlPart['host'] . GeneralUtility::getIndpEnv('TYPO3_SITE_PATH');
            return true;
        }

        return false;
    }

    /**
     * Rewrite core function, since it has bug.
     * See Bug Tracker 8265. It will be removed if it's fixed.
     * This function substitutes the hrefs in $this->theParts["html"]["content"]
     */
    protected function substHREFsInHTML(): void
    {
        if (!is_array($this->theParts['html']['hrefs'])) {
            return;
        }
        foreach ($this->theParts['html']['hrefs'] as $urlId => $val) {
            if ($val['no_jumpurl'] ?? false) {
                // A tag attribute "no_jumpurl=1" allows to disable jumpurl for custom links
                $substVal = $val['absRef'];
            } elseif ($this->jumperURLPrefix && ($val['tag'] != 'form') && (!strstr($val['ref'], 'mailto:'))) {
                // Form elements cannot use jumpurl!
                $substVal = $this->jumperURLPrefix;
                $substVal .= $this->jumperURLUseId ? $urlId : str_replace('%2F', '/', rawurlencode($val['absRef']));
            } elseif (strstr($val['ref'], 'mailto:') && $this->jumperURLUseMailto) {
                $substVal = $this->jumperURLPrefix;
                $substVal .= $this->jumperURLUseId ? $urlId : str_replace('%2F', '/', rawurlencode($val['absRef']));
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
    protected function dmailerLog(string $logMsg): void
    {
        $content = time() . ' => ' . $logMsg . LF;
        $logfilePath = Environment::getPublicPath() . '/typo3temp/tx_directmail_dmailer_log.txt';
        GeneralUtility::writeFile($logfilePath, $content);
    }

    /**
     * Adds plain-text, replaces the HTTP urls in the plain text and then encodes it
     *
     * @param string $content The plain text content
     */
    public function addPlain(string $content): void
    {
        $content = $this->substHTTPurlsInPlainText($content);
        $this->setPlain($content);
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
    protected function substHTTPurlsInPlainText(string $content): string
    {
        if (!isset($this->jumperURLPrefix) || !$this->jumperURLPrefix) {
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
                } elseif ($this->jumperURLUseId) {
                    $this->theParts['plain']['link_ids'][$jumpUrlCounter] = $url;
                    $url = $this->jumperURLPrefix . '-' . $jumpUrlCounter;
                    $jumpUrlCounter++;
                } else {
                    $url = $this->jumperURLPrefix . str_replace('%2F', '/', rawurlencode($url));
                }
                return $url;
            },
            $content
        );
    }

    /**
     * Sets the plain-text part. No processing done.
     *
     * @param string $content The plain content
     */
    protected function setPlain(string $content): void
    {
        $this->theParts['plain']['content'] = $content;
    }

    /**
     * Sets the HTML-part. No processing done.
     *
     * @param string $content The HTML content
     */
    protected function setHtml(string $content): void
    {
        $this->theParts['html']['content'] = $content;
    }

    /**
     * Extracts all media-links from $this->theParts['html']['content']
     */
    public function extractMediaLinks(): void
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
            preg_match('/[^>]*/', $codepieces[$i], $reg);

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
            preg_match('/[^>]*/', $codepieces[$i], $reg);
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

            if (in_array($theInfo['fileext'], ['gif', 'jpeg', 'jpg'])) {
                if ($imageData['ref'] && !strstr($imageList, '|' . $imageData['subst_str'] . '|')) {
                    $imageList .= '|' . $imageData['subst_str'] . '|';
                    $imageData['absRef'] = $this->absRef($imageData['ref']);
                    $this->theParts['html']['media'][] = $imageData;
                }
            }
        }
    }

    /**
     * Extracts all hyper-links from $this->theParts["html"]["content"]
     */
    public function extractHyperLinks(): void
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

            preg_match('/[^>]*/', $codepieces[$i], $reg);
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
                    $hrefData['no_jumpurl'] = (int)(trim(($attributes['no_jumpurl'] ?? ''), '"')) ? 1 : 0;
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
            if (($mediaData['use_jumpurl'] ?? false) === 1) {
                $this->theParts['html']['hrefs'][$mediaData['ref']] = $mediaData;
            }
        }
    }

    /**
     * Extracts all media-links from $this->theParts["html"]["content"]
     *
     * @return bool
     */
    public function extractFramesInfo(): bool
    {
        $htmlCode = $this->theParts['html']['content'];
        $info = [];
        if (strpos(' ' . $htmlCode, '<frame ')) {
            $attribRegex = $this->tag_regex(['frame']);
            // Splits the document by the beginning of the above tags
            $codepieces = preg_split($attribRegex, $htmlCode, 1000000);
            $pieces = count($codepieces);
            for ($i = 1; $i < $pieces; $i++) {
                preg_match('/[^>]*/', $codepieces[$i], $reg);
                // Fetches the attributes for the tag
                $attributes = $this->get_tag_attributes($reg[0]);
                $info[] = [
                    'src' => $attributes['src'],
                    'name' => $attributes['name'],
                    'absRef' => $this->absRef($frame['src']),
                ];
            }

            if (count($info)) {
                return false;
            }
        }

        return false;
    }

    /**
     * Creates a regular expression out of a list of tags
     *
     * @param array $tags Array the list of tags
     * 		(either as array or string if it is one tag)
     *
     * @return string the regular expression
     */
    protected function tag_regex(array $tags): string
    {
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
     * @param bool $removeQuotes When TRUE (default) quotes around a value will get removed
     *
     * @return array array with attributes as keys in lower-case
     */
    protected function get_tag_attributes(string $tag, bool $removeQuotes = true): array
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
    protected function absRef(string $ref): string
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
    protected function getHostname(bool $requestHost = true)
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
        if ($host && !str_contains($host, '.')) {
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
    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }

    /**
     * Creates an address object ready to be used with the symfony mailer
     */
    protected function createRecipient(string $email, string $name = ''): Address
    {
        return new Address($email, $name);
    }

    protected function ensureCorrectEncoding(string $inputString): string
    {
        if ($inputString) {
            return $this
                ->getCharsetConverter()
                ->conv(
                    $inputString,
                    $this->backendCharset,
                    $this->charset
                );
        }

        return '';
    }

    protected function getMilliseconds(): int
    {
        return round(microtime(true) * 1000);
    }
}
