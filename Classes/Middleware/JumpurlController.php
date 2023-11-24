<?php

declare(strict_types=1);

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

use DirectMailTeam\DirectMail\Repository\FeUsersRepository;
use DirectMailTeam\DirectMail\Repository\SysDmailMaillogRepository;
use DirectMailTeam\DirectMail\Repository\SysDmailRepository;
use DirectMailTeam\DirectMail\Repository\TtAddressRepository;
use DirectMailTeam\DirectMail\Utility\AuthCodeUtility;
use DirectMailTeam\DirectMail\Utility\Typo3ConfVarsUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * JumpUrl processing hook on TYPO3\CMS\Frontend\Http\RequestHandler
 *
 * @author		Ivan Kartolo <ivan.kartolo@gmail.com>
 */
class JumpurlController implements MiddlewareInterface
{
    public const RECIPIENT_TABLE_TTADDRESS = 'tt_address';
    public const RECIPIENT_TABLE_FEUSER = 'fe_users';

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
            $mailId = (int)$this->request->getQueryParams()['mid'];
            $submittedRecipient = isset($this->request->getQueryParams()['rid']) ? (string)$this->request->getQueryParams()['rid'] : '';
            $submittedAuthCode  = $this->request->getQueryParams()['aC'] ?? '';
            $jumpurl = $this->request->getQueryParams()['jumpurl'] ?? '';

            $urlId = 0;
            if (MathUtility::canBeInterpretedAsInteger($jumpurl)) {
                $urlId = $jumpurl;
                $this->initDirectMailRecord($mailId);
                $this->initRecipientRecord($submittedRecipient);
                $rid = $this->recipientRecord['uid'] ?? 0;

                $jumpurl = $this->getTargetUrl((int)$jumpurl);

                // try to build the ready-to-use target url
                if (!empty($this->recipientRecord)) {
                    $valid = AuthCodeUtility::validateAuthCode($submittedAuthCode, $this->recipientRecord, ($this->directMailRecord['authcode_fieldList'] ?: 'uid'));
                    if (!$valid) {
                        throw new \Exception(
                            'authCode verification failed.',
                            1376899631
                        );
                    }

                    $jumpurl = $this->substituteMarkersFromTargetUrl($jumpurl);

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
                $submittedAuthCode = preg_replace("/[^a-zA-Z0-9]/", "", $submittedAuthCode);
            }

            if ($this->responseType !== 0) {
                $mailLogParams = [
                    'mid'           => $mailId,
                    'tstamp'        => time(),
                    'url'           => $jumpurl,
                    'response_type' => $this->responseType,
                    'url_id'        => (int)$urlId,
                    'rtbl'          => mb_substr($this->recipientTable, 0, 1),
                    'rid'           => $rid ?? $submittedAuthCode,
                ];
                $sysDmailMaillogRepository = GeneralUtility::makeInstance(SysDmailMaillogRepository::class);
                if ($sysDmailMaillogRepository->hasRecentLog($mailLogParams) === false) {
                    $sysDmailMaillogRepository->insertForJumpurl($mailLogParams);
                }
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
     * Returns true of the conditions are met to process this middleware
     *
     * @return bool
     */
    protected function shouldProcess(): bool
    {
        $mid = $this->request->getQueryParams()['mid'] ?? null;
        return $mid && MathUtility::canBeInterpretedAsInteger($mid);
    }

    /**
     * Fills $this->directMailRecord with the requested sys_dmail record
     *
     * @param int $mailId
     */
    protected function initDirectMailRecord(int $mailId): void
    {
        $this->directMailRecord = GeneralUtility::makeInstance(SysDmailRepository::class)->selectForJumpurl($mailId);
    }

    /**
     * Fetches the target url from the direct mail record
     *
     * @param int $targetIndex
     * @return string
     */
    protected function getTargetUrl(int $targetIndex): string
    {
        $targetUrl = '';

        if (!empty($this->directMailRecord)) {
            $mailContent = unserialize(
                base64_decode((string)$this->directMailRecord['mailContent']),
                ['allowed_classes' => false]
            );

            if(is_array($mailContent)) {
                if ($targetIndex >= 0) {
                    // Link (number)
                    $this->responseType = self::RESPONSE_TYPE_HREF;
                    $targetUrl = $mailContent['html']['hrefs'][$targetIndex]['absRef'];
                } else {
                    // Link (number, plaintext)
                    $this->responseType = self::RESPONSE_TYPE_PLAIN;
                    $targetUrl = $mailContent['plain']['link_ids'][abs($targetIndex)];
                }
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
    protected function initRecipientRecord(string $combinedRecipient): void
    {
        // this will split up the "rid=f_13667", where the first part
        // is the DB table name and the second part the UID of the record in the DB table
        $recipientTable = '';
        $recipientUid = 0;
        if (!empty($combinedRecipient)) {
            list($recipientTable, $recipientUid) = explode('_', $combinedRecipient);
        }

        switch ($recipientTable) {
            case 't':
                $this->recipientTable = self::RECIPIENT_TABLE_TTADDRESS;
                $this->recipientRecord = GeneralUtility::makeInstance(TtAddressRepository::class)->getRawRecord((int)$recipientUid);
                break;
            case 'f':
                $this->recipientTable = self::RECIPIENT_TABLE_FEUSER;
                $this->recipientRecord = GeneralUtility::makeInstance(FeUsersRepository::class)->getRawRecord((int)$recipientUid);
                break;
            default:
                $this->recipientTable = '';
        }
    }

    /**
     * wrapper function for multiple substitution methods
     *
     * @param string $targetUrl
     * @return string
     */
    protected function substituteMarkersFromTargetUrl(string $targetUrl): string
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
    protected function substituteUserMarkersFromTargetUrl(string $targetUrl): string
    {
        $rowFieldsArray = Typo3ConfVarsUtility::getDMConfigMergedFields();
        reset($rowFieldsArray);
        $processedTargetUrl = $targetUrl;
        foreach ($rowFieldsArray as $substField) {
            if (isset($this->recipientRecord[$substField])) {
                $processedTargetUrl = str_replace(
                    '###USER_' . $substField . '###',
                    (string) $this->recipientRecord[$substField],
                    $processedTargetUrl
                );
            }
        }
        return $processedTargetUrl;
    }

    /**
     * @param string $targetUrl
     * @return string
     */
    protected function substituteSystemMarkersFromTargetUrl(string $targetUrl): string
    {
        $mailId = $this->request->getQueryParams()['mid'];
        $submittedAuthCode = $this->request->getQueryParams()['aC'];

        // substitute system markers
        $markers = ['###SYS_TABLE_NAME###', '###SYS_MAIL_ID###', '###SYS_AUTHCODE###'];
        $substitutions = [
            mb_substr($this->recipientTable, 0, 1),
            $mailId,
            $submittedAuthCode,
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
    protected function calculateJumpUrlHash(string $targetUrl): string
    {
        return GeneralUtility::hmac($targetUrl, 'jumpurl');
    }

    /**
     * Checks if the target is allowed to be given to jumpurl
     *
     * @param string $target
     * @return bool
     *
     * @throws \Exception
     */
    protected function isAllowedJumpUrlTarget(string $target): bool
    {
        $allowed = false;

        // Check if jumpurl is a valid link to a "dmailerping.gif"
        // Make $checkPath an absolute path pointing to dmailerping.gif so it can get checked via ::isAllowedAbsPath()
        $checkPath = Environment::getPublicPath() . '/' . ltrim($target, '/');

        // Now check if $checkPath is a valid path and points to a "/dmailerping.gif"
        if (preg_match('#/dmailerping\\.(gif|png)$#', $checkPath) && (GeneralUtility::isAllowedAbsPath($checkPath) || GeneralUtility::isValidUrl($target))) {
            // set juHash as done for external_url in core: http://forge.typo3.org/issues/46071
            $allowed = true;
        } elseif (GeneralUtility::isValidUrl($target)) {
            // if it's a valid URL, throw exception
            throw new \Exception('direct_mail: Invalid target.', 1578347190);
        }

        return $allowed;
    }
}
