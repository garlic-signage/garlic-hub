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

namespace App\Shared\User\Sso;

/**
 * An OpenID Connect provider of the login, a row of sso_providers. The client secret stays encrypted here.
 */
final readonly class SsoProvider
{
	/**
	 * @param list<string> $allowedDomains lower case, empty: everybody whom the provider lets in
	 */
	public function __construct(
		public int $id,
		public int $companyId,
		public string $name,
		public string $issuer,
		public string $clientId,
		public string $encryptedSecret,
		public array $allowedDomains,
		public bool $enabled
	) {}

	/**
	 * @param array<mixed> $row
	 */
	public static function fromRow(array $row): ?self
	{
		$text = static fn(string $key): string => is_scalar($row[$key] ?? null) ? trim((string) $row[$key]) : '';
		if (!is_numeric($row['provider_id'] ?? null) || $text('issuer') === '' || $text('client_id') === '')
			return null;

		return new self(
			(int) $row['provider_id'],
			is_numeric($row['company_id'] ?? null) ? (int) $row['company_id'] : 1,
			$text('name'),
			$text('issuer'),
			$text('client_id'),
			$text('client_secret'),
			self::parseDomains($text('allowed_domains')),
			(string) ($row['enabled'] ?? '0') === '1'
		);
	}

	/**
	 * "example.com, Example.org" as a list
	 *
	 * @return list<string>
	 */
	public static function parseDomains(string $domains): array
	{
		return array_values(array_unique(array_filter(array_map(static fn(string $domain): string => strtolower(trim($domain)), explode(',', $domains)))));
	}
}
