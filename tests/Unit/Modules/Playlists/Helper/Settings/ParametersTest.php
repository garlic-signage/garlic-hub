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

namespace Tests\Unit\Modules\Playlists\Helper\Settings;

use App\Framework\Core\Sanitizer;
use App\Framework\Core\Session;
use App\Modules\Playlists\Helper\Settings\Parameters;
use Exception;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

class ParametersTest extends TestCase
{
	private Parameters $parameters;

	/**
	 * @throws Exception|\PHPUnit\Framework\MockObject\Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$sanitizerMock = $this->createMock(Sanitizer::class);
		$sessionMock = $this->createMock(Session::class);

		$this->parameters    = new Parameters($sanitizerMock, $sessionMock);
	}

	#[Group('units')]
	public function testConstructor(): void
	{
		$this->assertCount(2, $this->parameters->getCurrentParameters());
		$this->assertSame('playlists', $this->parameters->getModuleName());
	}

	/**
	 */
	#[Group('units')]
	public function testAddPlaylistMode(): void
	{
		$this->assertFalse($this->parameters->hasParameter(Parameters::PARAMETER_PLAYLIST_MODE));
		$this->parameters->addPlaylistMode();
		$this->assertCount(3, $this->parameters->getCurrentParameters());
		$this->assertTrue($this->parameters->hasParameter(Parameters::PARAMETER_PLAYLIST_MODE));
	}

	/**
	 */
	#[Group('units')]
	public function testAddPlaylistId(): void
	{
		$this->assertFalse($this->parameters->hasParameter(Parameters::PARAMETER_PLAYLIST_ID));
		$this->parameters->addPlaylistId();
		$this->assertCount(3, $this->parameters->getCurrentParameters());
		$this->assertTrue($this->parameters->hasParameter(Parameters::PARAMETER_PLAYLIST_ID));
	}

	/**
	 */
	#[Group('units')]
	public function testAddTimeLimit(): void
	{
		$this->assertFalse($this->parameters->hasParameter(Parameters::PARAMETER_TIME_LIMIT));
		$this->parameters->addTimeLimit();
		$this->assertCount(3, $this->parameters->getCurrentParameters());
		$this->asserttrue($this->parameters->hasParameter(Parameters::PARAMETER_TIME_LIMIT));
	}

}
