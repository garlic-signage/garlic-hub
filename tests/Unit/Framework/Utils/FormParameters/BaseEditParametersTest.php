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

namespace Tests\Unit\Framework\Utils\FormParameters;

use App\Framework\Validation\Sanitizer;
use App\Framework\Http\Session;
use App\Framework\Exceptions\ModuleException;
use App\Framework\Utils\FormParameters\BaseEditParameters;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;


class ConcreteEditBaseParameters extends BaseEditParameters
{
	public function __construct(Sanitizer $sanitizer, Session $session)
	{
		parent::__construct('testModule', $sanitizer, $session);
	}

	public function addDefaultParameter(): void
	{
		$this->currentParameters = $this->defaultParameters;
	}
	public function addNoParameter(): void
	{
		$this->currentParameters = [];
	}
}

class BaseEditParametersTest extends TestCase
{
	private ConcreteEditBaseParameters $baseEditParameters;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$sanitizerStub = $this->createStub(Sanitizer::class);
		$sessionStub = $this->createStub(Session::class);

		$this->baseEditParameters = new ConcreteEditBaseParameters($sanitizerStub, $sessionStub);
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCheckCsrfTokenPasses(): void
	{
		$this->baseEditParameters->addDefaultParameter();
		$this->baseEditParameters->setValueOfParameter(BaseEditParameters::PARAMETER_CSRF_TOKEN, 'goodToken');

		static::assertSame('goodToken', $this->baseEditParameters->getCsrfToken());
	}

	/**
	 * @throws ModuleException
	 */
	#[Group('units')]
	public function testCheckCsrfTokenFails(): void
	{
		$this->baseEditParameters->addNoParameter();
		static::assertEmpty($this->baseEditParameters->getCsrfToken());
	}
}
