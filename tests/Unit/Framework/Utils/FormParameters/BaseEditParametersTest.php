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

namespace Tests\Unit\Framework\Utils\FormParameters;

use App\Framework\Exceptions\ModuleException;
use App\Framework\Http\Session;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Framework\Validation\Sanitizer;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

class ConcreteEditBaseParameters extends BaseEditParameters
{
	public function __construct(Session $session)
	{
		parent::__construct('testModule', new Sanitizer(), $session);
		$this->currentParameters = [];
		$this->userInputs        = [];
	}

	public function addDefaultParameter(): void
	{
		$this->currentParameters = $this->defaultParameters;
	}
}

class BaseEditParametersTest extends TestCase
{
	private ConcreteEditBaseParameters $parameters;

	protected function setUp(): void
	{
		parent::setUp();
		$this->parameters = new ConcreteEditBaseParameters(new MemorySession());
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheCsrfTokenOfTheRequestIsTaken(): void
	{
		$this->parameters->addDefaultParameter();
		$this->parameters->setUserInputs([BaseEditParameters::PARAMETER_CSRF_TOKEN => ' goodToken ', 'other' => 'ignored'])->parseInputAllParameters();

		static::assertSame('goodToken', $this->parameters->getCsrfToken());
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testWithoutATokenInTheRequestItIsEmpty(): void
	{
		$this->parameters->addDefaultParameter();
		$this->parameters->setUserInputs([])->parseInputAllParameters();

		static::assertSame('', $this->parameters->getCsrfToken());
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testTheTokenCanBeSet(): void
	{
		$this->parameters->addDefaultParameter();
		$this->parameters->setValueOfParameter(BaseEditParameters::PARAMETER_CSRF_TOKEN, 'goodToken');

		static::assertSame('goodToken', $this->parameters->getCsrfToken());
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testWithoutTheParameterThereIsNoToken(): void
	{
		static::assertSame('', $this->parameters->getCsrfToken());
	}

	#[Group('units')]
	public function testAParameterWhichWasNotParsedYetHasNoValue(): void
	{
		$this->parameters->addDefaultParameter();

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('A value for parameter with name: csrf_token is not set.');

		$this->parameters->getCsrfToken();
	}
}
