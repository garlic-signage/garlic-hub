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
use App\Modules\Users\Helper\Settings\Parameters;
use App\Modules\Users\Helper\Settings\SettingsParametersPolicy;
use App\Modules\Users\Services\AclValidator;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * Which fields of the user form the current user (UID 7) may use.
 */
#[Group('acl')]
class SettingsParametersPolicyTest extends ContainerTestCase
{
	private Parameters $parameters;

	protected function setUp(): void
	{
		parent::setUp();
		$this->parameters = $this->container->get(Parameters::class);
	}

	private function policy(bool $mayCreate, bool $mayEdit): SettingsParametersPolicy
	{
		$acl = $this->createStub(AclValidator::class);
		$acl->method('isSimpleAdmin')->willReturn($mayCreate);
		$acl->method('isAdmin')->willReturn($mayEdit);
		$session = $this->createStub(UserSession::class);
		$session->method('getUID')->willReturn(7);

		return new SettingsParametersPolicy($this->parameters, $acl, $session);
	}

	/**
	 * @return list<string> the fields of the form which are parameters
	 */
	private function fields(): array
	{
		return array_values(array_filter(['username', 'email', 'status', 'locale'], fn(string $name): bool => $this->parameters->hasParameter($name)));
	}

	public function testWhoMayCreateUsersGetsAllFields(): void
	{
		$policy = $this->policy(true, false);

		static::assertTrue($policy->mayCreate());
		$policy->addCreateFormElements();
		static::assertSame(['username', 'email', 'status', 'locale'], $this->fields());
	}

	public function testWhoMayNotCreateUsersGetsNoFields(): void
	{
		$policy = $this->policy(false, true);

		static::assertFalse($policy->mayCreate());
		$policy->addCreateFormElements();
		static::assertSame([], $this->fields());
	}

	public function testWhoMayEditAUserGetsAllFields(): void
	{
		$policy = $this->policy(false, true);

		static::assertTrue($policy->mayEdit(['UID' => 3, 'company_id' => 1]));
		$policy->addEditFormElements(['UID' => 3, 'company_id' => 1]);
		static::assertSame(['username', 'email', 'status', 'locale'], $this->fields());
	}

	public function testWhoMayNotEditAUserGetsNoFields(): void
	{
		$policy = $this->policy(true, false);

		static::assertFalse($policy->mayEdit(['UID' => 3, 'company_id' => 1]));
		$policy->addEditFormElements(['UID' => 3, 'company_id' => 1]);
		static::assertSame([], $this->fields());
	}

	public function testWhoEditsHimselfCanNotChangeTheStatus(): void
	{
		$this->policy(true, true)->addEditFormElements(['UID' => 7, 'company_id' => 1]);

		static::assertSame(['username', 'email', 'locale'], $this->fields());
	}
}
