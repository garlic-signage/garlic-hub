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

namespace Tests\Unit\Modules\Templates\Helper;

use App\Modules\Templates\Helper\Settings\FormInputHandler;
use App\Modules\Templates\Helper\Settings\Orchestrator;
use App\Modules\Templates\Helper\Settings\SettingsFormBuilder;
use App\Modules\Templates\Helper\Settings\SettingsParametersPolicy;
use App\Modules\Templates\Services\TemplatesService;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/**
 * What the settings answer when the service reports that no row was changed. The database of the integration tests
 * (SQLite) counts the rows which match, so these answers can only be reached with a service which returns 0.
 * Everything else of the settings is tested through the controller (tests/Integration/Modules/Templates/Controller).
 */
class UpdateFailuresTest extends TestCase
{
	private function orchestrator(TemplatesService $service): Orchestrator
	{
		$inputHandler = $this->createStub(FormInputHandler::class);
		$inputHandler->method('validateCreate')->willReturn([]);
		$inputHandler->method('validateEdit')->willReturn([]);
		$inputHandler->method('getParsed')->willReturn(['name' => 'Neu']);

		return new Orchestrator($this->createStub(SettingsFormBuilder::class), $this->createStub(SettingsParametersPolicy::class), $service, $inputHandler);
	}

	#[Group('units')]
	public function testATemplateWhichIsNotInsertedIsAnErrorOfTheForm(): void
	{
		$service = $this->createStub(TemplatesService::class);
		$service->method('insert')->willReturn(0);

		static::assertSame(['success' => false, 'errors' => ['No save possible']], $this->orchestrator($service)->storeCreateSettings(['name' => 'Neu']));
	}

	#[Group('units')]
	public function testATemplateWhichIsNotUpdatedIsAnErrorOfTheForm(): void
	{
		$service = $this->createStub(TemplatesService::class);
		$service->method('update')->willReturn(0);

		static::assertSame(['success' => false, 'errors' => ['No save possible']], $this->orchestrator($service)->storeEditSettings(2, ['name' => 'Neu']));
	}
}
