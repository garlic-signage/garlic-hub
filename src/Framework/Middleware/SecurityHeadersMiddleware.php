<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Adds security headers to every response which does not set them itself.
 *
 * The CSP lets only scripts of this server and inline scripts with the nonce of the request run: the nonce is
 * a request attribute for the templates (csp_nonce). style-src is not restricted yet, because the templates still
 * contain inline styles. The CSP also blocks framing by other sites, plugins, a manipulated <base> tag and form
 * posts to foreign targets.
 *
 * Cross-Origin-Embedder-Policy and Cross-Origin-Resource-Policy are left out on purpose: the players and
 * pages of other sites load media and content from this server.
 */
readonly class SecurityHeadersMiddleware implements MiddlewareInterface
{
	private const array HEADERS = [
		'X-Content-Type-Options'  => 'nosniff',
		'X-Frame-Options'         => 'SAMEORIGIN',
		'Referrer-Policy'         => 'same-origin',
		'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
		'Cross-Origin-Opener-Policy' => 'same-origin',
	];

	public const string NONCE_ATTRIBUTE = 'csp_nonce';

	private const string POLICY = "frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'; script-src 'self' 'nonce-%s'";

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		// hex, so the templates need no escaping and the value is the same in the header and in the attribute
		$nonce    = bin2hex(random_bytes(16));
		$response = $handler->handle($request->withAttribute(self::NONCE_ATTRIBUTE, $nonce));

		if (!$response->hasHeader('Content-Security-Policy'))
			$response = $response->withHeader('Content-Security-Policy', sprintf(self::POLICY, $nonce));

		// the version of PHP is nobody's business, whatever expose_php says
		header_remove('X-Powered-By');

		foreach (self::HEADERS as $name => $value)
		{
			if (!$response->hasHeader($name))
				$response = $response->withHeader($name, $value);
		}

		return $response;
	}
}
