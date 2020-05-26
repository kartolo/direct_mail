<?php
namespace DirectMailTeam\DirectMail\Middleware;

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
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * JumpUrl processing hook on TYPO3\CMS\Frontend\Http\RequestHandler
 *
 * @author		Ivan Kartolo <ivan.kartolo@gmail.com>
 *
 * @package 	TYPO3
 * @subpackage 	tx_directmail
 */
class JumpurlController implements MiddlewareInterface
{

    public const RECIPIENT_TABLE_TTADDRESS = 'tt_address';
    public const RECIPIENT_TABLE_FEUSER = 'fe_user';

    public const RESPONSE_TYPE_URL = -1;
    public const RESPONSE_TYPE_HREF = 1;
    public const RESPONSE_TYPE_PLAIN = 2;

    /**
     * @var int
     */
    protected $responseType = 0;

    /**
     * @var string
     */
    protected $recipientTable = '';

    /**
     * @var array
     */
    protected $recipientRecord = [];

    /**
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * @var array
     */
    protected $directMailRecord;

    /**
     * This is a preprocessor for the actual jumpurl extension to allow counting of clicked links
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     * @throws \Exception
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->request = $request;
        $queryParamsToPass = $request->getQueryParams();

        if ($this->shouldProcess()) {
            $mailId = $this->request->getQueryParams()['mid'];
            $submittedRecipient = $this->request->getQueryParams()['rid'];
            $submittedAuthCode  = $this->request->getQueryParams()['aC'];
            $jumpurl = $this->request->getQueryParams()['jumpurl'];

            $urlId = 0;
            if (MathUtility::canBeInterpretedAsInteger($jumpurl)) {
                $urlId = $jumpurl;
                $this->initDirectMailRecord($mailId);
                $this->initRecipientRecord($submittedRecipient);
                $targetUrl = $this->getTargetUrl($jumpurl);

                // try to build the ready-to-use target url
                if (!empty($this->recipientRecord)) {
                    $this->validateAuthCode($submittedAuthCode);
                    $jumpurl = $this->substituteMarkersFromTargetUrl($targetUrl);

                    $this->performFeUserAutoLogin();
                }
                // jumpUrl generation failed. Early exit here
                if (empty($jumpurl)) {
                    die('Error: No further link. Please report error to the mail sender.');
                }

            } else {
                // jumpUrl is not an integer -- then this is a URL, that means that the "dmailerping"
                // functionality was used to count the number of "opened mails" received (url, dmailerping)

                if ($this->isAllowedJumpUrlTarget($jumpurl)) {
                    $this->responseType = self::RESPONSE_TYPE_URL;
                }

                // to count the dmailerping correctly, we need something unique
                $recipientUid = $submittedAuthCode;
            }

            if ($this->responseType !== 0) {

                $connection = GeneralUtility::makeInstance(ConnectionPool::class)
                                            ->getConnectionForTable('sys_dmail_maillog');
                $connection->insert(
                    'sys_dmail_maillog',
                    [
                        'mid'           => (int)$mailId,
                        'tstamp'        => time(),
                        'url'           => $jumpurl,
                        'response_type' => $this->responseType,
                        'url_id'        => $urlId,
                        'rtbl'          => mb_substr($this->recipientTable, 0, 1),
                        'rid'           => $recipientUid ?? $this->recipientRecord['uid']
                    ]
                );
            }
        }

        // finally - finish preprocessing of the jumpurl params
        if (!empty($jumpurl)) {
            $queryParamsToPass['juHash'] = $this->calculateJumpUrlHash($jumpurl);
            $queryParamsToPass['jumpurl'] = $jumpurl;
        }

        return $handler->handle($request->withQueryParams($queryParamsToPass));
    }

    /**
     * Returns record no matter what - except if record is deleted
     *
     * @param string $table The table name to search
     * @param int $uid The uid to look up in $table
     * @param string $fields The fields to select, default is "*"
     *
     * @return mixed Returns array (the record) if found, otherwise blank/0 (zero)
     * @see getPage_noCheck()
     */
    public function getRawRecord($table, $uid, $fields = '*')
    {
        $uid = (int)$uid;
        if ($uid > 0) {
            $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable($table);
            $res = $queryBuilder->select($fields)
                ->from($table)
                ->where(
                    $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, \PDO::PARAM_INT)),
                    $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0))
                )
                ->execute();

            $row = $res->fetchAll();

            if ($row) {
                if (is_array($row[0])) {
                    return $row[0];
                }
            }
        }
        return 0;
    }

    /**
     * Returns true of the conditions are met to process this middleware
     *
     * @return bool
     */
    protected function shouldProcess(): bool
    {
        return ($this->request->getQueryParams()['mid'] !== null);
    }

    /**
     * Fills $this->directMailRecord with the requested sys_dmail record
     *
     * @param int $mailId
     */
    protected function initDirectMailRecord($mailId): void
    {
        /** @var QueryBuilder $queryBuilder */
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                                      ->getQueryBuilderForTable('sys_dmail');
        $result = $queryBuilder
            ->select('mailContent','page','authcode_fieldList')
            ->from('sys_dmail')
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($mailId, \PDO::PARAM_INT)
                )
            )
            ->execute()
            ->fetch();

        $this->directMailRecord = $result;
    }

    /**
     * Fetches the target url from the direct mail record
     *
     * @param int $targetIndex
     * @return string|null
     */
    protected function getTargetUrl($targetIndex): ?string
    {
        $targetUrl = null;

        if (!empty($this->directMailRecord)) {
            $mailContent = unserialize(
                base64_decode($this->directMailRecord['mailContent']),
                ['allowed_classes' => false]
            );
            if ($targetIndex >= 0) {
                // Link (number)
                $this->responseType = self::RESPONSE_TYPE_HREF;
                $targetUrl = $mailContent['html']['hrefs'][$targetIndex]['absRef'];
            } else {
                // Link (number, plaintext)
                $this->responseType = self::RESPONSE_TYPE_PLAIN;
                $targetUrl = $mailContent['plain']['link_ids'][abs($targetIndex)];
            }
            $targetUrl = htmlspecialchars_decode(urldecode($targetUrl));
        }
        return $targetUrl;
    }

    /**
     * Will split the combined recipient parameter into the table and uid and fetches the record if successful.
     *
     * @param string $combinedRecipient eg. "f_13667".
     */
    protected function initRecipientRecord($combinedRecipient): void
    {
        // this will split up the "rid=f_13667", where the first part
        // is the DB table name and the second part the UID of the record in the DB table
        $recipientTable = '';
        $recipientUid = '';
        if (!empty($combinedRecipient)) {
            list($recipientTable, $recipientUid) = explode('_', $combinedRecipient);
        }

        switch ($recipientTable) {
            case 't':
                $this->recipientTable = self::RECIPIENT_TABLE_TTADDRESS;
                break;
            case 'f':
                $this->recipientTable = self::RECIPIENT_TABLE_FEUSER;
                break;
            default:
                $this->recipientTable = '';
        }

        if (!empty($this->recipientTable)) {
            $this->recipientRecord = $this->getRawRecord($this->recipientTable, $recipientUid);
        }
    }

    /**
     * check if the supplied auth code is identical with the counted authCode
     *
     * @param string $submittedAuthCode
     */
    protected function validateAuthCode($submittedAuthCode): void
    {
        $authCodeToMatch = GeneralUtility::stdAuthCode(
            $this->recipientRecord,
            ($this->directMailRecord['authcode_fieldList'] ?? 'uid')
        );

         if (!empty($submittedAuthCode) && $submittedAuthCode === $authCodeToMatch) {
             // TODO: do we really need that much information?
             throw new \Exception(
                 'authCode verification failed.' .
                 ' recipientUid = ' . $this->recipientRecord['uid'] .
                 ' theTable = ' . $this->recipientTable .
                 ' authcode_fieldList' . $this->directMailRecord['authcode_fieldList'] .
                 ' AC = ' . $submittedAuthCode . ' AuthCode = ' . $authCodeToMatch,
                 1376899631
             );
         }
    }

    /**
     * wrapper function for multiple substitution methods
     *
     * @param string $targetUrl
     * @return string
     */
    protected function substituteMarkersFromTargetUrl($targetUrl): string
    {
        $targetUrl = $this->substituteUserMarkersFromTargetUrl($targetUrl);
        $targetUrl = $this->substituteSystemMarkersFromTargetUrl($targetUrl);

        $targetUrl = str_replace('#', '%23', $targetUrl);

        return $targetUrl;
    }

    /**
     * Substitutes ###USER_*### markers in url
     *
     * @return string
     */
    protected function substituteUserMarkersFromTargetUrl($targetUrl): string
    {
        $rowFieldsArray = explode(
            ',',
            $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['defaultRecipFields']
        );
        if ($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['addRecipFields']) {
            $rowFieldsArray = array_merge(
                $rowFieldsArray,
                explode(
                    ',',
                    $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['direct_mail']['addRecipFields']
                )
            );
        }

        reset($rowFieldsArray);
        $processedTargetUrl = $targetUrl;
        foreach ($rowFieldsArray as $substField) {
            $processedTargetUrl = str_replace(
                '###USER_' . $substField . '###',
                $this->recipientRecord[$substField],
                $processedTargetUrl
            );
        }

        return $processedTargetUrl;
    }

    /**
     * @param string $targetUrl
     * @return string
     */
    protected function substituteSystemMarkersFromTargetUrl($targetUrl): string
    {
        $mailId = $this->request->getAttribute('mid');
        $submittedAuthCode = $this->request->getAttribute('aC');

        // substitute system markers
        $markers = ['###SYS_TABLE_NAME###', '###SYS_MAIL_ID###', '###SYS_AUTHCODE###'];
        $substitutions = [
            mb_substr($this->recipientTable, 0, 1),
            $mailId,
            $submittedAuthCode
        ];
        $targetUrl = str_replace($markers, $substitutions, $targetUrl);

        return $targetUrl;
    }

    /**
     * Auto Login an FE User, only possible if we're allowed to set the $_POST variables and
     * in the authcode_fieldlist the field "password" is computed in as well
     *
     * TODO: Is this still valid?
     */
    protected function performFeUserAutoLogin()
    {
        // TODO: add a switch in Direct Mail configuration to decide if this option should be enabled by default
        if ($this->recipientTable === 'fe_users' &&
            GeneralUtility::inList(
                $this->directMailRecord['authcode_fieldList'],
                'password'
            )) {
            $_POST['user'] = $this->recipientRecord['username'];
            $_POST['pass'] = $this->recipientRecord['password'];
            $_POST['pid'] = $this->recipientRecord['pid'];
            $_POST['logintype'] = 'login';
        }
    }

    /**
     * Calculates the verification hash for the jumpUrl extension
     *
     * @param string $targetUrl
     *
     * @return string
     */
    protected function calculateJumpUrlHash($targetUrl): string
    {
        return GeneralUtility::hmac($targetUrl, 'jumpurl');
    }

    /**
     * Checks if the target is allowed to be given to jumpurl
     *
     * @param string $target
     * @return bool
     */
    protected function isAllowedJumpUrlTarget($target): bool
    {
        $allowed = false;

        // Check if jumpurl is a valid link to a "dmailerping.gif"
        // Make $checkPath an absolute path pointing to dmailerping.gif so it can get checked via ::isAllowedAbsPath()
        $checkPath = Environment::getPublicPath() . '/' . ltrim($target, '/');

        // Now check if $checkPath is a valid path and points to a "/dmailerping.gif"
        if (preg_match('#/dmailerping\\.(gif|png)$#', $checkPath) && GeneralUtility::isAllowedAbsPath($checkPath)) {
            // set juHash as done for external_url in core: http://forge.typo3.org/issues/46071
            $allowed = true;
        } elseif (preg_match('#^(http://|https://)#', $target) && GeneralUtility::isValidUrl($target)) {
            // Also allow jumpurl to be a valid URL
            $allowed = true;
        } elseif (preg_match('#^(mailto:)#', $target) && GeneralUtility::validEmail(mb_substr($target,7))) {
            // Also allow jumpurl to be a valid mailto link
            $allowed = true;
        }

        return $allowed;
    }

}
