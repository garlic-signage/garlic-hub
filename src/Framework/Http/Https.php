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

namespace App\Framework\Http;

/**
 * Detects whether the current request was made over HTTPS.
 *
 * Behind a reverse proxy the TLS is terminated before PHP, so X-Forwarded-Proto is considered too.
 * A client can fake that header, but this only influences the flags of its own cookies.
 */
class Https
{
	public static function isActive(): bool
	{
		if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off')
			return true;

		if (($_SERVER['SERVER_PORT'] ?? null) === '443' || ($_SERVER['SERVER_PORT'] ?? null) === 443)
			return true;

		return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
	}
}
