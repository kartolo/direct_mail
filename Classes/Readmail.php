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

use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Extension of the t3lib_readmail class for the purposes of the Direct mail extension.
 * Analysis of return mail reason is enhanced by checking more possible reason texts.
 * Tested on mailing list of approx. 1500 members with most domains in M�xico and reason text in English or Spanish.
 *
 * @author  Kasper Sk�rh�j <kasper@typo3.com>
 * @author  Stanislas Rolland <stanislas.rolland(arobas)fructifor.ca>
 *
 * @package  TYPO3
 * @subpackage  tx_directmail
 * @version  $Id: class.readmail.php 6012 2007-07-23 12:54:25Z ivankartolo $
 */
class Readmail
{
    protected $reason_text = array(
        '550' => 'no mailbox|account does not exist|user unknown|Recipient unknown|recipient unknown|account that you tried to reach is disabled|User Unknown|User unknown|unknown in relay recipient table|user is unknown|unknown user|unknown local part|unrouteable address|does not have an account here|no such user|user not listed|account has been disabled or discontinued|user disabled|unknown recipient|invalid recipient|recipient problem|recipient name is not recognized|mailbox unavailable|550 5\.1\.1 recipient|status: 5\.1\.1|delivery failed 550|550 requested action not taken|receiver not found|unknown or illegal alias|is unknown at host|is not a valid mailbox|no mailbox here by that name|we do not relay|5\.7\.1 unable to relay|cuenta no activa|inactive user|user is inactive|mailaddress is administratively disabled|not found in directory|not listed in public name & address book|destination addresses were unknown|recipient address rejected|Recipient address rejected|Address rejected|rejected address|not listed in domino directory|domino directory entry does not|550-5\.1.1 The email account that you tried to reach does not exist|The email address you entered couldn',
        '551' => 'over quota|quota exceeded|mailbox full|mailbox is full|not enough space on the disk|mailfolder is over the allowed quota|recipient reached disk quota|temporalmente sobre utilizada|recipient storage full|mailbox lleno|user mailbox exceeds allowed size',
        '552' => 'connection refused|Connection refused|connection timed out|Connection timed out|timed out while|Host not found|host not found|Unable to connect to DNS|t find any host named|unrouteable mail domain|not reached for any host after a long failure period|domain invalid|host lookup did not complete: retry timeout exceeded|no es posible conectar correctamente',
        '554' => 'error in header|header error|invalid message|invalid structure|header line format error'
    );

    public $dateAbbrevs = array(
        'JAN' => 1,
        'FEB' => 2,
        'MAR' => 3,
        'APR' => 4,
        'MAY' => 5,
        'JUN' => 6,
        'JUL' => 7,
        'AUG' => 8,
        'SEP' => 9,
        'OCT' => 10,
        'NOV' => 11,
        'DEC' => 12
    );

    public $serverGMToffsetMinutes = 60;

    /**
     * Returns special TYPO3 Message ID (MID) from input TO header
     * (the return address of the sent mail from Dmailer)
     *
     * @param string  $to Email address, return address string
     *
     * @return array  array with 'mid', 'rtbl' and 'rid' keys are returned.
     */
    public function find_MIDfromReturnPath($to)
    {
        $parts = explode('mid', strtolower($to));
        $moreParts=explode('_', $parts[1]);
        $out=array(
            'mid' => $moreParts[0],
            'rtbl' => substr($moreParts[1], 0, 1),
            'rid' => intval(substr($moreParts[1], 1))
        );
        if ($out['rtbl']=='p') {
            $out['rtbl']='P';
        }

        return($out);
    }

    /**
     * Returns special TYPO3 Message ID (MID) from input mail content
     *
     * @param string  $content Mail (header) content
     *
     * @return mixed  If "X-Typo3MID" header is found and integrity is OK,
     *  then an array with 'mid', 'rtbl' and 'rid' keys are returned. Otherwise void.
     */
    public function find_XTypo3MID($content)
    {
        if (strstr($content, 'X-TYPO3MID:')) {
            $p = explode('X-TYPO3MID:', $content, 2);
            $l = explode(LF, $p[1], 2);
            list($mid, $hash) = GeneralUtility::trimExplode('-', $l[0]);
            if (md5($mid) == $hash) {
                $moreParts = explode('_', substr($mid, 3));
                $out = array(
                    'mid' => $moreParts[0],
                    'rtbl' => substr($moreParts[1], 0, 1),
                    'rid' => substr($moreParts[1], 1)
                );
                return($out);
            }
        }
        return '';
    }

    /**
     * Returns the text content of a mail which has previously been parsed by eg. extractMailHeader()
     * Probably obsolete since the function fullParse() is more advanced and safer to use.
     * The getMessage method is modified to avoid breaking the message when it contains a Content-Type: message/delivery-status
     *
     * @param array $mailParts Output from extractMailHeader()
     *
     * @return string  only the content part
     */
    public function getMessage(array $mailParts)
    {
        if (preg_match('/^Content-Type: message\/delivery-status/', substr($mailParts['CONTENT'], 0, 5000))) {
            // Don't break it, we're only looking for a reason
            $c = $mailParts['CONTENT'];
        } elseif ($mailParts['content-type']) {
            $cType = $this->getCType($mailParts['content-type']);
            if ($cType['boundary']) {
                $parts = $this->getMailBoundaryParts($cType['boundary'], $mailParts['CONTENT']);
                $c = $this->getTextContent($parts[0]);
            } else {
                $c=$this->getTextContent(
                    'Content-Type: ' . $mailParts['content-type'] . '
     ' . $mailParts['CONTENT']
                );
            }
        } else {
            $c = $mailParts['CONTENT'];
        }
        return $c;
    }

    /**
     * Returns the body part of a raw mail message (including headers)
     * Probably obsolete since the function fullParse() is more advanced and safer to use.
     *
     * @param string $content Raw mail content
     *
     * @return string Body of message
     */
    public function getTextContent($content)
    {
        $p = $this->extractMailHeader($content);
        // Here some decoding might be needed...
        // However we just return what is believed to be the proper notification:
        return $p['CONTENT'];
    }

    /**
     * Splits the body of a mail into parts based on the boundary string given.
     * Obsolete, use fullParse()
     *
     * @param string $boundary Boundary string used to split the content.
     * @param string $content BODY section of a mail
     *
     * @return array Parts of the mail based on this
     */
    public function getMailBoundaryParts($boundary, $content)
    {
        $mParts = explode('--' . $boundary, $content);
        unset($mParts[0]);
        $new = array();
        foreach ($mParts as $val) {
            if (trim($val) == '--') {
                break;
            }
            $new[] = ltrim($val);
        }
        return $new;
    }

    /**
     * Returns Content Type plus more.
     * Obsolete, use fullParse()
     *
     * @param string $str ContentType string with more
     *
     * @return array Parts in key/value pairs
     * @ignore
     */
    public function getCType($str)
    {
        $parts = explode(';', $str);
        $cTypes = array();
        $cTypes['ContentType'] = $parts[0];
        next($parts);
        foreach ($parts as $ppstr) {
            $mparts = explode('=', $ppstr, 2);
            if (count($mparts) > 1) {
                $cTypes[strtolower(trim($mparts[0]))] = preg_replace('/^"/', '', trim(preg_replace('/"$/', '', trim($mparts[1]))));
            } else {
                $cTypes[] = $ppstr;
            }
        }
        return $cTypes;
    }

    /**
     * Analyses the return-mail content for the Dmailer module
     * used to find what reason there was for rejecting the mail
     * Used by the Dmailer, but not exclusively.
     *
     * @param string  $c Message Body/text
     *
     * @return array  key/value pairs with analysis result.
     *  Eg. "reason", "content", "reason_text", "mailserver" etc.
     */
    public function analyseReturnError($c)
    {
        $cp = array();
        // QMAIL
        if (preg_match('/' . preg_quote('--- Below this line is a copy of the message.') . '|' . preg_quote('------ This is a copy of the message, including all the headers.') . '/i', $c)) {
            if (preg_match('/' . preg_quote('--- Below this line is a copy of the message.') . '/i', $c)) {
                // Splits by the QMAIL divider
                $parts = explode('-- Below this line is a copy of the message.', $c, 2);
            } else {
                // Splits by the QMAIL divider
                $parts = explode('------ This is a copy of the message, including all the headers.', $c, 2);
            }
            $cp['content'] = trim($parts[0]);
            $parts = explode('>:', $cp['content'], 2);
            $cp['reason_text'] = trim($parts[1])?trim($parts[1]):$cp['content'];
            $cp['mailserver'] = 'Qmail';
            $cp['reason'] = $this->extractReason($cp['reason_text']);
        } elseif (strstr($c, 'The Postfix program')) {
            // Postfix
            $cp['content'] = trim($c);
            $parts = explode('>:', $c, 2);
            $cp['reason_text'] = trim($parts[1]);
            $cp['mailserver'] = 'Postfix';
            if (stristr($cp['reason_text'], '550')) {
                // 550 Invalid recipient, User unknown
                $cp['reason'] = 550;
            } elseif (stristr($cp['reason_text'], '553')) {
                // No such user
                $cp['reason'] = 553;
            } elseif (stristr($cp['reason_text'], '551')) {
                // Mailbox full
                $cp['reason'] = 551;
            } elseif (stristr($cp['reason_text'], 'recipient storage full')) {
                // Mailbox full
                $cp['reason'] = 551;
            } else {
                $cp['reason'] = -1;
            }
        } elseif (strstr($c, 'Your message cannot be delivered to the following recipients:')) {
            // whoever this is...
            $cp['content'] = trim($c);
            $cp['reason_text'] = trim(strstr($cp['content'], 'Your message cannot be delivered to the following recipients:'));
            $cp['reason_text']=trim(substr($cp['reason_text'], 0, 500));
            $cp['mailserver']='unknown';
            $cp['reason'] = $this->extractReason($cp['reason_text']);
        } elseif (strstr($c, 'Diagnostic-Code: X-Notes')) {
            // Lotus Notes
            $cp['content'] = trim($c);
            $cp['reason_text'] = trim(strstr($cp['content'], 'Diagnostic-Code: X-Notes'));
            $cp['reason_text'] = trim(substr($cp['reason_text'], 0, 200));
            $cp['mailserver']='Notes';
            $cp['reason'] = $this->extractReason($cp['reason_text']);
        } else {
            // No-named:
            $cp['content'] = trim($c);
            $cp['reason_text'] = trim(substr($c, 0, 1000));
            $cp['mailserver'] = 'unknown';
            $cp['reason'] = $this->extractReason($cp['reason_text']);
        }

        return $cp;
    }

    /**
     * Try to match reason found in the returned email
     * with the defined reasons (see $reason_text)
     *
     * @param string  $text Content of the returned email
     *
     * @return int  The error code.
     */
    public function extractReason($text)
    {
        $reason = -1;
        foreach ($this->reason_text as $case => $value) {
            if (preg_match('/' . $value . '/i', $text)) {
                return intval($case);
            }
        }
        return $reason;
    }

    /**
     * Decodes a header-string with the =?....?= syntax
     * including base64/quoted-printable encoding.
     *
     * @param string $str A string (encoded or not) from a mail header, like sender name etc.
     *
     * @return string The input string, but with the parts in =?....?= decoded.
     */
    public function decodeHeaderString($str)
    {
        $parts = explode('=?', $str, 2);
        if (count($parts) == 2) {
            list($charset, $encType, $encContent) = explode('?', $parts[1], 3);
            $subparts = explode('?=', $encContent, 2);
            $encContent = $subparts[0];
            switch (strtolower($encType)) {
                case 'q':
                    $encContent = quoted_printable_decode($encContent);
                    $encContent = str_replace('_', ' ', $encContent);
                    break;
                case 'b':
                    $encContent = base64_decode($encContent);
                    break;
                default:
            }
            // Calls decodeHeaderString recursively for any subsequent encoded section.
            $parts[1] = $encContent . $this->decodeHeaderString($subparts[1]);
        }
        return implode('', $parts);
    }

    /**
     * Extracts name/email parts from a header field
     * (like 'To:' or 'From:' with name/email mixed up.
     *
     * @param string $str Value from a header field containing name/email values.
     *
     * @return array Array with the name and email in.
     *  Email is validated, otherwise not set.
     */
    public function extractNameEmail($str)
    {
        $outArr = array();
        // Email:
        $reg = '';
        preg_match('/<([^>]*)>/', $str, $reg);
        if (GeneralUtility::validEmail($str)) {
            $outArr['email'] = $str;
        } elseif ($reg[1] && GeneralUtility::validEmail($reg[1])) {
            $outArr['email'] = $reg[1];
            // Find name:
            list($namePart) = explode($reg[0], $str);
            if (trim($namePart)) {
                $reg = '';
                preg_match('/"([^"]*)"/', $str, $reg);
                if (trim($reg[1])) {
                    $outArr['name'] = trim($reg[1]);
                } else {
                    $outArr['name'] = trim($namePart);
                }
            }
        }
        return $outArr;
    }

    /**
     * Returns the data from the 'content-type' field.
     * That is the boundary, charset and mime-type
     *
     * @param string $contentTypeStr Content-type-string
     *
     * @return array key/value pairs with the result.
     */
    public function getContentTypeData($contentTypeStr)
    {
        $outValue = array();
        $cTypeParts = GeneralUtility::trimExplode(';', $contentTypeStr, 1);
        // Content type, first value is supposed to be the mime-type,
        // whatever after the first is something else.
        $outValue['_MIME_TYPE'] = $cTypeParts[0];
        reset($cTypeParts);
        next($cTypeParts);
        while (list(, $v) = Each($cTypeParts)) {
            $reg = '';
            preg_match('/([^=]*)="(.*)"/i', $v, $reg);
            if (trim($reg[1]) && trim($reg[2])) {
                $outValue[strtolower($reg[1])] = $reg[2];
            }
        }
        return $outValue;
    }

    /**
     * Makes a UNIX-date based on the timestamp in the 'Date' header field.
     *
     * @param string $dateStr String with a timestamp according to email standards.
     *
     * @return int The timestamp converted to unix-time in seconds and compensated for GMT/CET ($this->serverGMToffsetMinutes);
     */
    public function makeUnixDate($dateStr)
    {
        $dateParts = explode(',', $dateStr);
        $dateStr = count($dateParts) > 1 ? $dateParts[1] : $dateParts[0];
        $spaceParts = GeneralUtility::trimExplode(' ', $dateStr, 1);
        $spaceParts[1] = $this->dateAbbrevs[strtoupper($spaceParts[1])];
        $timeParts = explode(':', $spaceParts[3]);
        $timeStamp = mktime($timeParts[0], $timeParts[1], $timeParts[2], $spaceParts[1], $spaceParts[0], $spaceParts[2]);
        $offset = $this->getGMToffset($spaceParts[4]);
        // Compensates for GMT by subtracting the number of seconds which the date is offset from serverTime
        $timeStamp -= $offset * 60;
        return $timeStamp;
    }

    /**
     * Parsing the GMT offset value from a mail timestamp.
     *
     * @param string $GMT A string like "+0100" or so.
     *
     * @return int Minutes to offset the timestamp
     * @access private
     */
    public function getGMToffset($GMT)
    {
        $GMToffset = intval(substr($GMT, 1, 2)) * 60 + intval(substr($GMT, 3, 2));
        $GMToffset *= substr($GMT, 0, 1) == '+' ? 1 : -1;
        $GMToffset -= $this->serverGMToffsetMinutes;
        return $GMToffset;
    }

    /**
     * This returns the mail header items in an array with
     * associative keys and the mail body part in another CONTENT field
     *
     * @param string $content Raw mail content
     * @param int $limit A safety limit that will put a upper length
     *  to how many header chars will be processed.
     *  Set to zero means that there is no limit.
     * (Uses a simple substr() to limit the amount of mail data to process to avoid run-away)
     *
     * @return array An array where each key/value pair is a header-key/value pair.
     * The mail BODY is returned in the key 'CONTENT' if $limit is not set!
     */
    public function extractMailHeader($content, $limit = 0)
    {
        if ($limit) {
            $content = substr($content, 0, $limit);
        }
        $lines = explode(LF, ltrim($content));
        $headers = array();
        $p = '';
        foreach ($lines as $k => $str) {
            if (!trim($str)) {
                break;
            }
            // Header finished
            $parts = explode(' ', $str, 2);
            if ($parts[0] && substr($parts[0], -1) == ':') {
                $p = strtolower(substr($parts[0], 0, -1));
                if (isset($headers[$p])) {
                    $headers[$p . '.'][] = $headers[$p];
                    $headers[$p] = '';
                }
                $headers[$p] = trim($parts[1]);
            } else {
                $headers[$p] .= ' ' . trim($str);
            }
            unset($lines[$k]);
        }
        if (!$limit) {
            $headers['CONTENT'] = ltrim(implode(LF, $lines));
        }
        return $headers;
    }

    /**
     * The extended version of the extractMailHeader()
     * which will also parse all the content body into an array and
     * further process the header fields and decode content etc. Returns every part of the mail ready to go.
     *
     * @param string $content Raw email input.
     *
     * @return array Multidimensional array with all parts of the message organized nicely.
     */
    public function fullParse($content)
    {
        // *************************
        // PROCESSING the HEADER part of the mail
        // *************************
        // Splitting header and body of mail:
        $mailParts = $this->extractMailHeader($content);
        // Decoding header values which potentially can be encoded by =?...?=
        $list = explode(',', 'subject,thread-topic,from,to');
        foreach ($list as $headerType) {
            if (isset($mailParts[$headerType])) {
                $mailParts[$headerType] = $this->decodeHeaderString($mailParts[$headerType]);
            }
        }
        // Separating email/names from header fields which can contain email addresses.
        $list = explode(',', 'from,to,reply-to,sender,return-path');
        foreach ($list as $headerType) {
            if (isset($mailParts[$headerType])) {
                $mailParts['_' . strtoupper($headerType)] = $this->extractNameEmail($mailParts[$headerType]);
            }
        }
        // Decode date from human-readable format to unix-time (includes compensation for GMT CET)
        $mailParts['_DATE'] = $this->makeUnixDate($mailParts['date']);
        // Transfer encodings of body content
        switch (strtolower($mailParts['content-transfer-encoding'])) {
            case 'quoted-printable':
                $mailParts['CONTENT'] = quoted_printable_decode($mailParts['CONTENT']);
                break;
            case 'base64':
                $mailParts['CONTENT'] = base64_decode($mailParts['CONTENT']);
                break;
            default:
                // do nothing
        }
        // Content types
        $mailParts['_CONTENT_TYPE_DAT'] = $this->getContentTypeData($mailParts['content-type']);
        // *************************
        // PROCESSING the CONTENT part of the mail (the body)
        // *************************
        $cType = strtolower($mailParts['_CONTENT_TYPE_DAT']['_MIME_TYPE']);
        // Only looking for 'multipart' in string.
        $cType = substr($cType, 0, 9);
        switch ($cType) {
            case 'multipart':
                if ($mailParts['_CONTENT_TYPE_DAT']['boundary']) {
                    $contentSectionParts = GeneralUtility::trimExplode('--' . $mailParts['_CONTENT_TYPE_DAT']['boundary'], $mailParts['CONTENT'], 1);
                    $contentSectionParts_proc = array();
                    foreach ($contentSectionParts as $k => $v) {
                        if (substr($v, 0, 2) == '--') {
                            break;
                        }
                        $contentSectionParts_proc[$k] = $this->fullParse($v);
                    }
                    $mailParts['CONTENT'] = $contentSectionParts_proc;
                } else {
                    $mailParts['CONTENT'] = 'ERROR: No boundary found.';
                }
                break;
            default:
                if (strtolower($mailParts['_CONTENT_TYPE_DAT']['charset']) == 'utf-8') {
                    $mailParts['CONTENT'] = utf8_decode($mailParts['CONTENT']);
                }
        }
        return $mailParts;
    }
}
