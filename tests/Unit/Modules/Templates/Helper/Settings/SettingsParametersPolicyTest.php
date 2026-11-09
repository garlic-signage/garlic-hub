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

namespace Tests\Unit\Modules\Templates\Helper\Settings;

use App\Framework\Validation\Sanitizer;
use App\Modules\Templates\Helper\Settings\Parameters;
use App\Modules\Auth\UserSession;
use App\Modules\Templates\Helper\Settings\SettingsParametersPolicy;
use App\Modules\Templates\Services\AclValidator;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

class SettingsParametersPolicyTest extends TestCase
{
	private Parameters $parameters;
	private SettingsParametersPolicy $policy;

	private const array TEMPLATE = ['UID' => 2, 'company_id' => 1];

	protected function setUp(): void
	{
		parent::setUp();
		$this->parameters = new Parameters(new Sanitizer(), new MemorySession());
		$this->policy     = $this->policyFor(true);
	}

	private function policyFor(bool $isAdmin): SettingsParametersPolicy
	{
		$session = $this->createStub(UserSession::class);
		$session->method('getUID')->willReturn(7);
		$acl = $this->createMock(AclValidator::class);
		$acl->method('isAdmin')->with(7, self::TEMPLATE)->willReturn($isAdmin);

		return new SettingsParametersPolicy($this->parameters, $acl, $session);
	}

	/**
	 * @return list<string>
	 */
	private function added(): array
	{
		return array_values(array_filter(['UID', 'visibility', 'type'], fn(string $name): bool => $this->parameters->hasParameter($name)));
	}

	#[Group('units')]
	public function testTheEditFormOfTheAdminHasTheOwnerAndTheVisibility(): void
	{
		$this->policy->addEditFormElements(self::TEMPLATE);

		static::assertSame(['UID', 'visibility'], $this->added());
	}

	/**
	 * An editor works on the template, making it global would share it with everybody.
	 */
	#[Group('units')]
	public function testTheEditFormOfAnEditorHasNeitherOwnerNorVisibility(): void
	{
		$this->policyFor(false)->addEditFormElements(self::TEMPLATE);

		static::assertSame([], $this->added());
	}

	#[Group('units')]
	public function testTheCreateFormHasTheTypeToo(): void
	{
		$this->policy->addCreateFormElements();

		static::assertSame(['UID', 'visibility', 'type'], $this->added());
	}
}
