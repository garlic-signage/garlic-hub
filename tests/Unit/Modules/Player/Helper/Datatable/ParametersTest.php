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

namespace Tests\Unit\Modules\Player\Helper\Datatable;

use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\ScalarType;
use App\Modules\Player\Helper\Datatable\Parameters;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ParametersTest extends TestCase
{
	private Parameters $parameters;

	/**
	 * @throws ModuleException
	 * @throws Exception
	 * @throws \PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$sanitizerStub = $this->createStub(Sanitizer::class);
		$sessionStub = $this->createStub(Session::class);

		$this->parameters    = new Parameters($sanitizerStub, $sessionStub);
	}

	#[Group('units')]
	public function testConstructor(): void
	{
		static::assertCount(7, $this->parameters->getCurrentParameters());
		static::assertSame('player', $this->parameters->getModuleName());
	}

	#[Group('units')]
	public function testAddActivity(): void
	{
		// Call the method to add the activity parameter
		$this->parameters->addActivity();

		// Assert that the parameter is now present
		static::assertTrue($this->parameters->hasParameter(Parameters::PARAMETER_ACTIVITY));

		// Assert that the parameter has the correct type and default value
		$currentParameters = $this->parameters->getCurrentParameters();
		static::assertArrayHasKey(Parameters::PARAMETER_ACTIVITY, $currentParameters);
		static::assertSame('', $currentParameters[Parameters::PARAMETER_ACTIVITY]['default_value']);
		static::assertSame(ScalarType::STRING, $currentParameters[Parameters::PARAMETER_ACTIVITY]['scalar_type']);
	}


}
