<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2024 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
declare(strict_types=1);

namespace App\Modules\Account\Controller;

use App\Framework\Localization\Locales\Locales;
use App\Framework\Http\Session;
use App\Modules\Account\Services\AccountService;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles operations related to setting and managing user locale preferences.
 */
class EditLocalesController
{
	private AccountService $accountService;
	private Session $session;
	private Locales $locales;

	public function __construct(AccountService $accountService, Session $session, Locales $locales)
	{
		$this->accountService = $accountService;
		$this->session        = $session;
		$this->locales        = $locales;
	}

	/**
	 * @param array<string,string> $args
	 * @throws Exception
	 */
	public function setLocales(ServerRequestInterface $request, ResponseInterface $response, array $args):
	ResponseInterface
	{
		$locale = $args['locale'];

		// only locales from the whitelist are stored in session and database
		if ($this->locales->isLocaleValid($locale))
		{
			$this->session->set('locale', $locale);

			if ($this->session->exists('user'))
			{
				$user = $this->session->get('user');
				if (is_array($user))
				{
					$user['locale'] = $locale;
					$this->session->set('user', $user);
					$this->accountService->updateLocale((int) $user['UID'], $locale);
				}
			}
		}

		// determine current locale secure because it checks a whitelist
		// of available locales
		$this->locales->determineCurrentLocale();

		return $response
			->withHeader('Location', $this->determineReturnPath($request))
			->withStatus(302); // 302: forwarding

	}


	/**
	 * Back to the page the user came from. The Referer is only taken for a page of this site,
	 * otherwise a link on any other site could send the user back to itself.
	 * Only the path and query are returned.
	 */
	private function determineReturnPath(ServerRequestInterface $request): string
	{
		$referer = $request->getHeaderLine('Referer');
		$parts   = parse_url($referer);
		if ($parts === false)
			return '/';

		if (isset($parts['host']))
		{
			$host = $request->getHeaderLine('Host') ?: $request->getUri()->getHost();
			if (!in_array($parts['scheme'] ?? '', ['http', 'https'], true) || strcasecmp($parts['host'] . (isset($parts['port']) ? ':' . $parts['port'] : ''), $host) !== 0)
				return '/';
			$path = $parts['path'] ?? '/';
		}
		else
			$path = $parts['path'] ?? '';

		// a path of this site: "//host" and "/\host" are addresses of other sites for a browser
		if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_starts_with($path, '/\\'))
			return '/';

		return $path . (isset($parts['query']) ? '?' . $parts['query'] : '');
	}
}
