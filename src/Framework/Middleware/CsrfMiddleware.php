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

use App\Framework\Controller\JsonResponseHandler;
use App\Framework\Localization\Translate\Translator;
use App\Framework\Security\CsrfToken;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Flash\Messages;
use Slim\Psr7\Response;

/**
 * Every request that can change something (POST, PUT, PATCH, DELETE) needs the CSRF token of the session
 * in the field csrf_token of its body. Secure by default: a new route is protected without doing anything.
 *
 * /api is left out, OAuth clients call it without a session. Runs after the LocaleMiddleware, so the message
 * is translated. The token and the flash messages should be lazy objects: a request which changes nothing
 * must not start a session.
 */
readonly class CsrfMiddleware implements MiddlewareInterface
{
	private const array SAFE_METHODS = ['GET', 'HEAD', 'OPTIONS'];
	private const array EXCLUDED_SECTIONS = ['api'];
	private const array JSON_SECTIONS = ['async'];
	public const string FIELD = 'csrf_token';

	public function __construct(
		private CsrfToken $csrfToken,
		private Messages $flash,
		private Translator $translator,
		private JsonResponseHandler $jsonResponseHandler
	) {}

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		if (in_array($request->getMethod(), self::SAFE_METHODS, true))
			return $handler->handle($request);

		$section = $this->determineSection($request);
		if (in_array($section, self::EXCLUDED_SECTIONS, true))
			return $handler->handle($request);

		if ($this->csrfToken->validateToken($this->receivedToken($request)))
			return $handler->handle($request);

		$message = $this->translator->translate('csrf_token_mismatch', 'security');
		if (in_array($section, self::JSON_SECTIONS, true) || $request->getHeaderLine('Accept') === 'application/json')
			return $this->jsonResponseHandler->jsonError(new Response(), $message, 403);

		// a form: the message is shown by the page the visitor comes from
		$this->flash->addMessage('error', $message);

		return new Response(303)->withHeader('Location', $this->backTo($request));
	}

	private function determineSection(ServerRequestInterface $request): string
	{
		return explode('/', ltrim($request->getUri()->getPath(), '/'), 2)[0];
	}

	private function receivedToken(ServerRequestInterface $request): string
	{
		$body = $request->getParsedBody();
		if (!is_array($body))
			return '';

		return is_string($body[self::FIELD] ?? null) ? $body[self::FIELD] : '';
	}

	/**
	 * Only the path of the referer counts, so the redirect never leaves the site. Without one, the address
	 * of the request: forms are mostly shown and sent at the same address.
	 */
	private function backTo(ServerRequestInterface $request): string
	{
		$referer = parse_url($request->getHeaderLine('Referer'));
		$path    = is_array($referer) ? ($referer['path'] ?? '') : '';

		// a browser takes //host and /\host for another site
		if (!str_starts_with($path, '/') || str_starts_with($path, '//') || str_contains($path, '\\'))
			return $request->getUri()->getPath();

		return isset($referer['query']) ? $path . '?' . $referer['query'] : $path;
	}
}
