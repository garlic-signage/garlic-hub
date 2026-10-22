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

namespace Tests\Unit\Modules\Templates\Helper\Datatable;

use App\Framework\Validation\Sanitizer;
use App\Framework\Http\Session;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseFilterParametersInterface;
use App\Modules\Templates\Helper\Datatable\Parameters;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class ParametersTest extends TestCase
{
	private Parameters $parameters;

	/**
	 * @throws ModuleException
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->parameters = new Parameters($this->createStub(Sanitizer::class), $this->createStub(Session::class));
	}

	#[Group('units')]
	public function testConstructor(): void
	{
		static::assertCount(6, $this->parameters->getCurrentParameters());
		static::assertSame('templates', $this->parameters->getModuleName());
		static::assertTrue($this->parameters->hasParameter(Parameters::PARAMETER_TEMPLATE_NAME));
		static::assertTrue($this->parameters->hasParameter(Parameters::PARAMETER_TYPE));
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testDefaultSortColumnIsTemplateId(): void
	{
		static::assertSame(
			Parameters::PARAMETER_TEMPLATE_ID,
			$this->parameters->getDefaultValueOfParameter(BaseFilterParametersInterface::PARAMETER_SORT_COLUMN)
		);
	}
}
