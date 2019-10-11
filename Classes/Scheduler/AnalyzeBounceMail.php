<?php
namespace DirectMailTeam\DirectMail\Scheduler;

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

use DirectMailTeam\DirectMail\Readmail;
use Doctrine\DBAL\FetchMode;
use Fetch\Message;
use Fetch\Server;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
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
            // get unread mails
            $messages = $mailServer->search('UNSEEN', $this->maxProcessed);
            /** @var Message $message The message object */
            foreach ($messages as $i => $message) {
                // process the mail
                if ($this->processBounceMail($message)) {
                    // set delete
                    $message->delete();
                } else {
                    $message->setFlag('SEEN');
                }
            }

            // expunge to delete permanently
            $mailServer->expunge();
            imap_close($mailServer->getImapStream());
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
        $readMail = GeneralUtility::makeInstance(Readmail::class);

        // get attachment
        $attachmentArray = $message->getAttachments();
        $midArray = array();
        if (is_array($attachmentArray)) {
            // search in attachment
            foreach ($attachmentArray as $v => $attachment) {
                $bouncedMail = $attachment->getData();
                // Find mail id
                $midArray = $readMail->find_XTypo3MID($bouncedMail);
                if (false === empty($midArray)) {
                    // if mid, rid and rtbl are found, then stop looping
                    break;
                }
            }
        } else {
            // search in MessageBody (see rfc822-headers as Attachments placed )
            $midArray = $readMail->find_XTypo3MID($message->getMessageBody());
        }

        if (empty($midArray)) {
            // no mid, rid and rtbl found - exit
            return false;
        }

        // Extract text content
        $cp = $readMail->analyseReturnError($message->getMessageBody());

        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_dmail_maillog');
        $row = $queryBuilder
            ->select('uid','email')
            ->from('sys_dmail_maillog')
            ->where(
                $queryBuilder->expr()->andX(
                    $queryBuilder->expr()->eq(
                        'rid',
                        $queryBuilder->createNamedParameter((int)$midArray['rid'], \PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq(
                        'rtbl',
                        $queryBuilder->createNamedParameter($midArray['rtbl'], \PDO::PARAM_STR)
                    ),
                    $queryBuilder->expr()->eq(
                        'mid',
                        $queryBuilder->createNamedParameter((int)$midArray['mid'], \PDO::PARAM_INT)
                    ),
                    $queryBuilder->expr()->eq('response_type', 0)
                )
            )
            ->setMaxResults(1)
            ->execute()
            ->fetch(FetchMode::ASSOCIATIVE);
        // only write to log table, if we found a corresponding recipient record
        if (!empty($row)) {
            /** @var Connection $connection */
            $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                ->getConnectionForTable('sys_dmail_maillog');
            try {
                $midArray['email'] = $row['email'];
                $insertFields = [
                    'tstamp' => $GLOBALS['EXEC_TIME'],
                    'response_type' => -127,
                    'mid' => (int)$midArray['mid'],
                    'rid' => (int)$midArray['rid'],
                    'email' => $midArray['email'],
                    'rtbl' => $midArray['rtbl'],
                    'return_content' => serialize($cp),
                    'return_code' => (int)$cp['reason']
                ];
                $connection->insert('sys_dmail_maillog', $insertFields);
                $sql_insert_id = $connection->lastInsertId('sys_dmail_maillog');
                return (bool)$sql_insert_id;
            } catch (\Doctrine\DBAL\DBALException $e) {
                // Log $e->getMessage();
                return false;
            }
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
            Server::class,
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
}
