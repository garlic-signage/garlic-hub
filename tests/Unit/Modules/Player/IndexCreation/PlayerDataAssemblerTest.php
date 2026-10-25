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

namespace Tests\Unit\Modules\Player\IndexCreation;

use App\Framework\Config\Config;
use App\Framework\Exceptions\ModuleException;
use App\Modules\Player\Entities\PlayerEntityFactory;
use App\Modules\Player\IndexCreation\PlayerDataAssembler;
use App\Modules\Player\IndexCreation\PlayerDetector;
use App\Modules\Player\IndexCreation\UserAgentHandler;
use App\Modules\Player\Repositories\PlayerIndexRepository;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingConfigLoader;

/**
 * The registration of players is tested with the real database in tests/Integration/Modules/Player/Index.
 * Here is the case which the database does not give: the insert of the player does not work.
 */
class PlayerDataAssemblerTest extends TestCase
{
	private function assembler(): PlayerDataAssembler
	{
		$config     = new Config(new RecordingConfigLoader([]));
		$repository = $this->createStub(PlayerIndexRepository::class);
		$repository->method('findPlayerById')->willReturn([]);
		$repository->method('insertPlayer')->willReturn(0);

		$assembler = new PlayerDataAssembler(new UserAgentHandler(new PlayerDetector($config)), $repository, $config, new PlayerEntityFactory($config));
		$assembler->parseUserAgent('GAPI/1.0 (UUID:3f0cd56c-d511-486a-a8e1-9d2cefd78b3f; NAME:Eingang) garlic/v0.6.0.679 (MODEL:Garlic)');

		return $assembler;
	}

	#[Group('units')]
	public function testAPlayerWhichCanNotBeRegisteredIsAnError(): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Failed to insert player');

		$this->assembler()->insertNewPlayer(2);
	}

	#[Group('units')]
	public function testALocalPlayerWhichCanNotBeRegisteredIsAnError(): void
	{
		$this->expectException(ModuleException::class);
		$this->expectExceptionMessage('Failed to insert local player');

		$this->assembler()->handleLocalPlayer();
	}
}
