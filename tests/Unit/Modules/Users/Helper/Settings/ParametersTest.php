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


namespace Tests\Unit\Modules\Users\Helper\Settings;

use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\ScalarType;
use App\Modules\Users\Helper\Settings\Parameters;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class ParametersTest extends TestCase
{
	private Parameters $parameters;

	/**
	 * @throws Exception|\PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$sanitizerStub = $this->createStub(Sanitizer::class);
		$sessionStub = $this->createStub(Session::class);

		$this->parameters    = new Parameters($sanitizerStub, $sessionStub);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAddUserNameAddsUsernameParameter(): void
	{
		static::assertFalse($this->parameters->hasParameter(Parameters::PARAMETER_USER_NAME));

		$this->parameters->addUserName();

		static::assertTrue($this->parameters->hasParameter(Parameters::PARAMETER_USER_NAME));
		static::assertSame('', $this->parameters->getDefaultValueOfParameter(Parameters::PARAMETER_USER_NAME));
		static::assertSame(ScalarType::STRING, $this->parameters->getCurrentParameters()[Parameters::PARAMETER_USER_NAME]['scalar_type']);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAddUserEmailAddsEmailParameter(): void
	{
		static::assertFalse($this->parameters->hasParameter(Parameters::PARAMETER_USER_EMAIL));

		$this->parameters->addUserEmail();

		static::assertTrue($this->parameters->hasParameter(Parameters::PARAMETER_USER_EMAIL));
		static::assertSame('', $this->parameters->getDefaultValueOfParameter(Parameters::PARAMETER_USER_EMAIL));
		static::assertSame(ScalarType::STRING, $this->parameters->getCurrentParameters()[Parameters::PARAMETER_USER_EMAIL]['scalar_type']);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAddUserStatusAddsStatusParameter(): void
	{
		static::assertFalse($this->parameters->hasParameter(Parameters::PARAMETER_USER_STATUS));

		$this->parameters->addUserStatus();

		static::assertTrue($this->parameters->hasParameter(Parameters::PARAMETER_USER_STATUS));
		static::assertSame(0, $this->parameters->getDefaultValueOfParameter(Parameters::PARAMETER_USER_STATUS));
		static::assertSame(ScalarType::INT, $this->parameters->getCurrentParameters()[Parameters::PARAMETER_USER_STATUS]['scalar_type']);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAddUserLocaleAddsLocaleParameter(): void
	{
		static::assertFalse($this->parameters->hasParameter(Parameters::PARAMETER_USER_LOCALE));

		$this->parameters->addUserLocale();

		static::assertTrue($this->parameters->hasParameter(Parameters::PARAMETER_USER_LOCALE));
		static::assertSame('en_US', $this->parameters->getDefaultValueOfParameter(Parameters::PARAMETER_USER_LOCALE));
		static::assertSame(ScalarType::STRING, $this->parameters->getCurrentParameters()[Parameters::PARAMETER_USER_LOCALE]['scalar_type']);
	}
}
