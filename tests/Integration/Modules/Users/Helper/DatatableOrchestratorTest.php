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

namespace Tests\Integration\Modules\Users\Helper;

use App\Modules\Auth\UserSession;
use App\Modules\Users\Helper\Datatable\DatatableBuilder;
use App\Modules\Users\Helper\Datatable\DatatablePreparer;
use App\Modules\Users\Helper\Datatable\Orchestrator;
use App\Modules\Users\Services\UsersDatatableService;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The orchestrator of the list of the users: the parameters of the current user and the page.
 */
#[Group('acl')]
class DatatableOrchestratorTest extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
	}

	/**
	 * The filters depend on the user who looks at the list (an admin filters by company).
	 */
	public function testTheParametersAreConfiguredForTheCurrentUser(): void
	{
		$this->actingAs(2);
		$builder = $this->createMock(DatatableBuilder::class);
		$builder->expects($this->once())->method('configureParameters')->with(2);
		$orchestrator = new Orchestrator($builder, $this->container->get(DatatablePreparer::class), $this->container->get(UsersDatatableService::class), $this->container->get(UserSession::class));

		$orchestrator->configure();
	}

	public function testThePageHasTheStylesAndTheScriptOfTheList(): void
	{
		$this->actingAs(1);
		$orchestrator = $this->container->get(Orchestrator::class);
		$orchestrator->configure();
		$orchestrator->processSubmittedUserInput([]);

		$page = $orchestrator->prepareDataGrid()->prepareUITemplate();

		static::assertSame(['/css/users/datatable.css'], $page['additional_css']);
		static::assertSame(['/js/users/datatable/init.js'], $page['footer_modules']);
		static::assertSame(['users/datatable', 'users'], [$page['template_name'], $page['module_name']]);
	}
}
