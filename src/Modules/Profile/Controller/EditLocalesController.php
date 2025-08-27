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

namespace App\Modules\Profile\Controller;

use App\Framework\Core\Locales\Locales;
use App\Framework\Core\Session;
use App\Modules\Profile\Services\ProfileService;
use Doctrine\DBAL\Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Handles operations related to setting and managing user locale preferences.
 */
class EditLocalesController
{
	private ProfileService $profileService;

	public function __construct(ProfileService $profileService)
	{
		$this->profileService = $profileService;
	}

	/**
	 * @param array<string,string> $args
	 * @throws Exception
	 */
	public function setLocales(ServerRequestInterface $request, ResponseInterface $response, array $args):
	ResponseInterface
	{
		$locale  = htmlentities($args['locale'], ENT_QUOTES);

		// set locale into session
		/** @var  Session $session */
		$session = $request->getAttribute('session');
		$session->set('locale', $locale);

		if ($session->exists('user'))
		{
			/** @var array<string,mixed> $user */
			$user = $session->get('user');
			$user['locale'] = $locale;
			$session->set('user', $user);
			$this->profileService->updateLocale($user['UID'], $locale);
		}

		// determine current locale secure because it checks a whitelist
		// of available locales
		/** @var  Locales $locales */
		$locales    = $request->getAttribute('locales');
		$locales->determineCurrentLocale();
		$previousUrl = $request->getHeaderLine('Referer') ?: '/';

		return $response
			->withHeader('Location', $previousUrl)
			->withStatus(302); // 302: forwarding

	}

}