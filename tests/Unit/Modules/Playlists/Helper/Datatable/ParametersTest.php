<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2025 Nikolaos Sagiadinos <garlic@saghiadinos.de>
 This file is part of the garlic-hub source code

 This program is free software: you can redistribute it and/or  modify
 it under the terms of the GNU Affero General Public License, version 3,
 as published by the Free Software Foundation.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU Affero General Public License for more details.

 You should have received a copy of the GNU Affero General Public License
 along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/


namespace Tests\Unit\Modules\Playlists\Helper\Datatable;

use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Playlists\Helper\Datatable\Parameters;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ParametersTest extends TestCase
{
	private readonly Sanitizer&MockObject $sanitizerMock;
	private readonly Session&MockObject $sessionMock;
	private readonly Parameters $parameters;

	/**
	 * @throws ModuleException
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		$this->sanitizerMock = $this->createMock(Sanitizer::class);
		$this->sessionMock   = $this->createMock(Session::class);

		$this->parameters    = new Parameters($this->sanitizerMock, $this->sessionMock);
	}

	#[Group('units')]
	public function testConstructor()
	{
		$this->assertCount(6, $this->parameters->getCurrentParameters());
		$this->assertSame('playlists', $this->parameters->getModuleName());
		$this->assertInstanceOf(Parameters::class, $this->parameters);
	}

}
