<?php
/**
 * Created by PhpStorm.
 * User: dkd-kartolo
 * Date: 30/04/16
 * Time: 22:56
 */
namespace DirectMailTeam\DirectMail\Scheduler;

use DirectMailTeam\DirectMail\Readmail;
use Fetch\Message;
use Fetch\Server;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Scheduler\Task\AbstractTask;

/**
 * Class AnalyzeBounceMail
 * @package DirectMailTeam\DirectMail\Scheduler
 * @author Ivan Kartolo <ivan.kartolo@gmail.com>
 */
class AnalyzeBounceMail extends AbstractTask
{
    /**
     * url of the mail server
     * @var string
     */
    protected $server;

    /**
     * Port number of the mail server
     * @var int
     */
    protected $port;

    /**
     * Username to use to authenticate
     * @var string
     */
    protected $user;

    /**
     * Password of the user
     * @var string
     */
    protected $password;

    /**
     * Mailserver type (imap or pop3)
     * @var string
     */
    protected $service;

    /**
     * Maximum number of bounce mail to be processed
     * @var int
     */
    protected $maxProcessed;

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @param int $port
     */
    public function setPort($port)
    {
        $this->port = $port;
    }

    /**
     * @return string
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param string $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @return string
     */
    public function getService()
    {
        return $this->service;
    }

    /**
     * @param string $service
     */
    public function setService($service)
    {
        $this->service = $service;
    }

    /**
     * @return mixed
     */
    public function getServer()
    {
        return $this->server;
    }

    /**
     * @param mixed $server
     */
    public function setServer($server)
    {
        $this->server = $server;
    }

    /**
     * @return mixed
     */
    public function getMaxProcessed()
    {
        return $this->maxProcessed;
    }

    /**
     * @param mixed $maxProcessed
     */
    public function setMaxProcessed($maxProcessed)
    {
        $this->maxProcessed = (int) $maxProcessed;
    }

    /**
     * execute the scheduler task.
     *
     * @return bool
     */
    public function execute()
    {
        // try connect to mail server
        $mailServer = $this->connectMailServer();
        if ($mailServer instanceof Server) {
            // we are connected to mail server
            // get mails
            // TODO: how to get only unread mail
            $messages = $mailServer->getMessages($this->maxProcessed);
            /** @var Message $message The message object */
            foreach ($messages as $i => $message) {
                // process the mail
                if ($this->processBounceMail($message)) {
                    // set delete
                    //$message->delete();
                }
            }

            // expunge to delete permanently
            //$mailServer->expunge();
            return true;
        } else {
            return false;
        }
    }

    /**
     * Process the bounce mail
     * @param Message $message the message object
     * @return bool true if bounce mail can be parsed, else false
     */
    private function processBounceMail($message)
    {
        /** @var Readmail $readMail */
        $readMail = GeneralUtility::makeInstance('DirectMailTeam\\DirectMail\\Readmail');

        // get attachment
        $attachmentArray = $message->getAttachments();
        $midArray = array();
        foreach ($attachmentArray as $v => $attachment) {
            //Todo: check attachment mimeType $attachment->mimeType?
            $bouncedMail = $attachment->getData();
            // Find mail id
            $midArray = $readMail->find_XTypo3MID($bouncedMail);
            if (is_array($midArray)) {
                // if mid, rid and rtbl are found, then continue
                break;
            }
        }

        // Extract text content
        $cp = $readMail->analyseReturnError($message->getMessageBody());

        $res = $this->getDatabaseConnection()->exec_SELECTquery(
            'uid,email',
            'sys_dmail_maillog',
            'rid=' . intval($midArray['rid']) . ' AND rtbl="' .
            $this->getDatabaseConnection()->quoteStr($midArray['rtbl'], 'sys_dmail_maillog') . '"' .
            ' AND mid=' . intval($midArray['mid']) . ' AND response_type=0'
        );

        // only write to log table, if we found a corresponding recipient record
        if ($this->getDatabaseConnection()->sql_num_rows($res)) {
            $row = $this->getDatabaseConnection()->sql_fetch_assoc($res);
            $midArray['email'] = $row['email'];
            $insertFields = array(
                'tstamp' => time(),
                'response_type' => -127,
                'mid' => intval($midArray['mid']),
                'rid' => intval($midArray['rid']),
                'email' => $midArray['email'],
                'rtbl' => $midArray['rtbl'],
                'return_content' => serialize($cp),
                'return_code' => intval($cp['reason'])
            );
            DebugUtility::debug($insertFields);
            return $this->getDatabaseConnection()->exec_INSERTquery('sys_dmail_maillog', $insertFields);
        } else {
            return false;
        }
    }

    /**
     * Create connection to mail server.
     * Return mailServer object or false on error
     *
     * @return bool|Server
     */
    private function connectMailServer()
    {
        // check if we can connect using the given data
        /** @var Server $mailServer */
        $mailServer = GeneralUtility::makeInstance(
            'Fetch\\Server',
            $this->server,
            (int) $this->port,
            $this->service
        );

        // set mail username and password
        $mailServer->setAuthentication($this->user, $this->password);

        try {
            $imapStream = $mailServer->getImapStream();
            return $mailServer;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get the DB global object
     *
     * @return \TYPO3\CMS\Core\Database\DatabaseConnection
     */
    protected function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }
}
