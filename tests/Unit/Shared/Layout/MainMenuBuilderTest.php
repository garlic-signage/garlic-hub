<?php
/*
 garlic-hub: Digital Signage Management Platform

 Copyright (C) 2026 Nikolaos Sagiadinos <garlic@saghiadinos.de>
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

namespace Tests\Unit\Shared\Layout;

use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Framework\Http\Session;
use App\Framework\Localization\Translate\Translator;
use App\Shared\Layout\MainMenuBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

class MainMenuBuilderTest extends TestCase
{
	private Session&Stub $sessionStub;
	private MainMenuBuilder $builder;

	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->sessionStub = $this->createStub(Session::class);
		$translatorStub    = $this->createStub(Translator::class);
		$translatorStub->method('translate')->willReturnCallback(static fn(string $key, string $module): string => $module . '.' . $key);

		$this->builder = new MainMenuBuilder($this->sessionStub, $translatorStub);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildForGuestShowsLogin(): void
	{
		$this->sessionStub->method('exists')->willReturn(false);

		static::assertSame([['URL' => '/login', 'LANG_MENU_POINT' => 'login.login']], $this->builder->build());
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testBuildForUserShowsModules(): void
	{
		$this->sessionStub->method('exists')->willReturn(true);

		static::assertSame([
			['URL' => '/player', 'LANG_MENU_POINT' => 'menu.player'],
			['URL' => '/playlists', 'LANG_MENU_POINT' => 'menu.playlists'],
			['URL' => '/mediapool', 'LANG_MENU_POINT' => 'menu.mediapool']
		], $this->builder->build());
	}
}
