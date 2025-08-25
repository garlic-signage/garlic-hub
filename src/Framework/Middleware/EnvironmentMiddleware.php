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

namespace App\Framework\Middleware;

use App\Framework\Core\Config\Config;
use App\Framework\Core\Locales\Locales;
use App\Framework\Core\Translate\Translator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Middleware responsible for injecting environment-specific data into the request.
 *
 * This middleware adds configuration, locale, and translation-related data
 * as attributes in the PSR-7 request object, enabling their access throughout
 * the application lifecycle.
 */
class EnvironmentMiddleware implements MiddlewareInterface
{
	private Config $config;
	private Locales $locales;
	private Translator $translator;

	public function __construct(Config $config, Locales $locale, Translator $translator)
	{
		$this->config = $config;
		$this->locales = $locale;
		$this->translator = $translator;
	}
	/**
	 * @inheritDoc
	 */
	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$this->locales->determineCurrentLocale();

		$request = $request
			->withAttribute('config', $this->config)
			->withAttribute('locales', $this->locales)
			->withAttribute('translator', $this->translator);

		return $handler->handle($request);
	}
}