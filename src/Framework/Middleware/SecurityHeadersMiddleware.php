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
 * The CSP does not restrict script-src/style-src yet, because the templates still
 * contain inline scripts and styles. It already blocks framing by other sites, plugins,
 * a manipulated <base> tag and form posts to foreign targets.
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
		'Content-Security-Policy' => "frame-ancestors 'self'; base-uri 'self'; object-src 'none'; form-action 'self'",
		'Permissions-Policy'      => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
		'Cross-Origin-Opener-Policy' => 'same-origin',
	];

	public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
	{
		$response = $handler->handle($request);

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
