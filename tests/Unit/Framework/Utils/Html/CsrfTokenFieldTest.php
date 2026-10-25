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

namespace Tests\Unit\Framework\Utils\Html;

use App\Framework\Security\Crypt;
use App\Framework\Security\CsrfToken;
use App\Framework\Utils\Html\CsrfTokenField;
use App\Framework\Utils\Html\FieldType;
use Defuse\Crypto\Exception\EnvironmentIsBrokenException;
use Defuse\Crypto\Key;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

class CsrfTokenFieldTest extends TestCase
{
	/**
	 * @throws EnvironmentIsBrokenException
	 */
	#[Group('units')]
	public function testTheFieldCarriesTheTokenOfTheSession(): void
	{
		$csrfToken = new CsrfToken(new Crypt(Key::createNewRandomKey()), new MemorySession());

		$field = new CsrfTokenField(['id' => 'csrf_token', 'type' => FieldType::CSRF, 'name' => 'csrf_token_name'], $csrfToken);

		static::assertSame('csrf_token', $field->getId());
		static::assertSame('csrf_token_name', $field->getName());
		static::assertSame($csrfToken->getToken(), $field->getValue());
		static::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $field->getValue());
	}
}
