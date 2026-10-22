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

namespace Tests\Unit\Modules\Templates\Helper\Settings;

use App\Framework\Validation\Sanitizer;
use App\Framework\Http\Session;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use App\Modules\Templates\Helper\Settings\Parameters;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class ParametersTest extends TestCase
{
	private Parameters $parameters;

	protected function setUp(): void
	{
		parent::setUp();
		$this->parameters = new Parameters($this->createStub(Sanitizer::class), $this->createStub(Session::class));
	}

	#[Group('units')]
	public function testConstructor(): void
	{
		static::assertCount(2, $this->parameters->getCurrentParameters());
		static::assertSame('templates', $this->parameters->getModuleName());
		static::assertTrue($this->parameters->hasParameter(BaseEditParameters::PARAMETER_CSRF_TOKEN));
		static::assertTrue($this->parameters->hasParameter(Parameters::PARAMETER_NAME));
	}

	/**
	 * A missing name must end up as empty string, so the validator can report it.
	 *
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testMissingNameIsParsedAsEmptyString(): void
	{
		$parameters = new Parameters(new Sanitizer(), $this->createStub(Session::class));
		$parameters->setUserInputs([])->parseInputAllParameters();

		static::assertSame('', $parameters->getValueOfParameter(Parameters::PARAMETER_NAME));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAddVisibility(): void
	{
		static::assertFalse($this->parameters->hasParameter(Parameters::PARAMETER_VISIBILITY));

		$this->parameters->addVisibility();

		static::assertCount(3, $this->parameters->getCurrentParameters());
		static::assertSame('private', $this->parameters->getDefaultValueOfParameter(Parameters::PARAMETER_VISIBILITY));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testAddType(): void
	{
		static::assertFalse($this->parameters->hasParameter(Parameters::PARAMETER_TYPE));

		$this->parameters->addType();

		static::assertCount(3, $this->parameters->getCurrentParameters());
		static::assertSame('canvas', $this->parameters->getDefaultValueOfParameter(Parameters::PARAMETER_TYPE));
	}
}
