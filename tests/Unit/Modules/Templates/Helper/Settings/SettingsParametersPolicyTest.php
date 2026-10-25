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
use App\Modules\Templates\Helper\Settings\SettingsParametersPolicy;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\MemorySession;

class SettingsParametersPolicyTest extends TestCase
{
	private Parameters $parameters;
	private SettingsParametersPolicy $policy;

	protected function setUp(): void
	{
		parent::setUp();
		$this->parameters = new Parameters(new Sanitizer(), new MemorySession());
		$this->policy     = new SettingsParametersPolicy($this->parameters);
	}

	/**
	 * @return list<string>
	 */
	private function added(): array
	{
		return array_values(array_filter(['UID', 'visibility', 'type'], fn(string $name): bool => $this->parameters->hasParameter($name)));
	}

	#[Group('units')]
	public function testTheEditFormHasTheOwnerAndTheVisibility(): void
	{
		$this->policy->addEditFormElements();

		static::assertSame(['UID', 'visibility'], $this->added());
	}

	#[Group('units')]
	public function testTheCreateFormHasTheTypeToo(): void
	{
		$this->policy->addCreateFormElements();

		static::assertSame(['UID', 'visibility', 'type'], $this->added());
	}
}
