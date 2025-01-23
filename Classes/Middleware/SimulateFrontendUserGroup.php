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

use DirectMailTeam\DirectMail\Utility\DmRegistryUtility;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\UserAspect;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class SimulateFrontendUserGroup implements MiddlewareInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        protected readonly Context $context,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $directMailFeGroup = (int)($request->getQueryParams()['dmail_fe_group'] ?? 0);
        $accessToken = (string)($request->getQueryParams()['access_token'] ?? '');

        if ($directMailFeGroup > 0 && GeneralUtility::makeInstance(DmRegistryUtility::class)->validateAndRemoveAccessToken($accessToken)) {
            $userAspect = $this->context->getAspect('frontend.user');

            // overwrite 'frontend.user' aspect if required
            if (!in_array($directMailFeGroup, $userAspect->getGroupIds(), true)) {
                $frontendUser = $request->getAttribute('frontend.user');
                $this->context->setAspect(
                    'frontend.user',
                    new UserAspect($frontendUser, [$directMailFeGroup]),
                );
            }
        }

        return $handler->handle($request);
    }
}
