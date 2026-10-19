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

use App\Modules\Templates\Helper\Settings\Parameters;
use App\Modules\Templates\Helper\Settings\SettingsParametersPolicy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SettingsParametersPolicyTest extends TestCase
{
	private Parameters&MockObject $parametersMock;
	private SettingsParametersPolicy $policy;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->parametersMock = $this->createMock(Parameters::class);
		$this->policy         = new SettingsParametersPolicy($this->parametersMock);
	}

	#[Group('units')]
	public function testAddEditFormElements(): void
	{
		$this->parametersMock->expects($this->once())->method('addOwner');
		$this->parametersMock->expects($this->once())->method('addVisibility');
		$this->parametersMock->expects($this->never())->method('addType');

		$this->policy->addEditFormElements();
	}

	#[Group('units')]
	public function testAddCreateFormElements(): void
	{
		$this->parametersMock->expects($this->once())->method('addOwner');
		$this->parametersMock->expects($this->once())->method('addVisibility');
		$this->parametersMock->expects($this->once())->method('addType');

		$this->policy->addCreateFormElements();
	}
}
