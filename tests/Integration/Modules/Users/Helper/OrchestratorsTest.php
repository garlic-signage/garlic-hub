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

use App\Framework\Config\Config;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Exceptions\TranslatableModuleException;
use App\Framework\Localization\Translate\Translator;
use App\Modules\Users\Helper\InitialAdmin\FormInputHandler;
use App\Modules\Users\Helper\InitialAdmin\Orchestrator as InitialAdminOrchestrator;
use App\Modules\Users\Helper\InitialAdmin\SettingsFormBuilder;
use App\Modules\Users\Helper\Settings\Orchestrator as SettingsOrchestrator;
use App\Modules\Users\Services\UsersAdminCreateService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * What the controllers of the users can not reach: the orchestrators in the states their callers exclude.
 */
#[Group('acl')]
class OrchestratorsTest extends ContainerTestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->actingAs(1);
	}

	/**
	 * @return array<string,array{string}>
	 */
	public static function actionsOnAUser(): array
	{
		return ['edit form' => ['buildEditForm'], 'rights' => ['checkEditRights'], 'store' => ['storeEditSettings'], 'reset' => ['resetPassword']];
	}

	#[DataProvider('actionsOnAUser')]
	public function testAUserWhoIsNotLoadedCanNotBeEdited(string $action): void
	{
		$orchestrator = $this->container->get(SettingsOrchestrator::class);

		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('No user loaded for editing.');
		match ($action)
		{
			'buildEditForm'     => $orchestrator->buildEditForm(),
			'checkEditRights'   => $orchestrator->checkEditRights(),
			'storeEditSettings' => $orchestrator->storeEditSettings(3, ['username' => 'bernd', 'email' => 'bernd@example.com']),
			default             => $orchestrator->resetPassword(3, ['username' => 'bernd', 'email' => 'bernd@example.com']),
		};
	}

	public function testAnAdminWhoIsCreatedByAnotherRequestIsAnErrorOfTheForm(): void
	{
		$service = $this->createStub(UsersAdminCreateService::class);
		$service->method('insertNewAdminUser')->willThrowException(new TranslatableModuleException('users', 'admin_exists'));
		$orchestrator = new InitialAdminOrchestrator(
			$service,
			$this->container->get(FormInputHandler::class),
			$this->container->get(SettingsFormBuilder::class),
			$this->container->get(Config::class),
			$this->container->get(Translator::class)
		);

		$result = $orchestrator->store(['username' => 'root', 'email' => 'root@example.com', 'locale' => 'en_US', 'password' => 'Secret123!', 'password_confirm' => 'Secret123!']);

		static::assertFalse($result['success']);
		static::assertCount(1, $result['errors'] ?? []);
		static::assertSame($this->container->get(Translator::class)->translateException(new TranslatableModuleException('users', 'admin_exists')), $result['errors'][0] ?? '');
	}
}
