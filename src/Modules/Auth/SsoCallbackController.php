<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace App\Modules\Auth;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Localization\Translate\Translator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * The provider of the SSO sends the browser back from its site. The session cookie is SameSite=Strict, so the
 * browser does not send it with this request: a page of our site leads on to LoginController::finishSso(), with
 * the cookie. This request must not start a session: its new cookie would replace the one which waits.
 */
readonly class SsoCallbackController
{
	public function __construct(private Translator $translator)
	{}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	public function callback(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
	{
		$url = htmlspecialchars('/login/sso/finish?' . http_build_query($request->getQueryParams()), ENT_QUOTES);
		$response->getBody()->write('<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="refresh" content="0;url=' . $url . '">'
			. '<meta name="referrer" content="no-referrer"><title>SSO</title></head><body><a href="' . $url . '">' . $this->translator->translate('sso_continue', 'login') . '</a></body></html>');

		return $response->withHeader('Content-Type', 'text/html; charset=utf-8')->withHeader('Cache-Control', 'no-store');
	}
}
