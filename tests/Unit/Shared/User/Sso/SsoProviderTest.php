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

namespace Tests\Unit\Shared\User\Sso;

use App\Shared\User\Sso\SsoProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * A provider from a row of sso_providers: a row without what the login needs is no provider.
 */
class SsoProviderTest extends TestCase
{
	/**
	 * @return array<string,mixed>
	 */
	private static function row(): array
	{
		return ['provider_id' => 3, 'company_id' => 7, 'name' => 'Okta', 'issuer' => 'https://example.okta.com', 'client_id' => 'hub', 'client_secret' => 'enc', 'allowed_domains' => '', 'enabled' => 1];
	}

	#[Group('units')]
	public function testARowBecomesAProviderWithoutTheSpacesAround(): void
	{
		$provider = SsoProvider::fromRow(['company_id' => '7', 'name' => ' Okta ', 'issuer' => ' https://example.okta.com ', 'client_id' => ' hub '] + self::row());

		static::assertEquals(new SsoProvider(3, 7, 'Okta', 'https://example.okta.com', 'hub', 'enc', [], true), $provider);
	}

	#[Group('units')]
	public function testWithoutACompanyItIsTheOneOfCore(): void
	{
		$row = self::row();
		unset($row['company_id']);

		static::assertSame(1, SsoProvider::fromRow($row)?->companyId);
	}

	/**
	 * @return array<string,array{array<string,mixed>}>
	 */
	public static function incompleteRows(): array
	{
		return [
			'no id'        => [['provider_id' => null] + self::row()],
			'no issuer'    => [['issuer' => ' '] + self::row()],
			'no client id' => [['client_id' => ''] + self::row()],
		];
	}

	/**
	 * @param array<string,mixed> $row
	 */
	#[Group('units')]
	#[DataProvider('incompleteRows')]
	public function testARowWithoutWhatTheLoginNeedsIsNoProvider(array $row): void
	{
		static::assertNull(SsoProvider::fromRow($row));
	}

	#[Group('units')]
	public function testTheDomainsAreAListInSmallLettersOnceEach(): void
	{
		static::assertSame(['example.com', 'example.org'], SsoProvider::parseDomains(' Example.com, example.com,, EXAMPLE.org '));
		static::assertSame([], SsoProvider::parseDomains(''));
	}
}
