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

namespace Tests\Unit\Modules\Playlists\Helper\Compose;

use App\Framework\Core\Config\Config;
use App\Framework\Core\Translate\Translator;
use App\Framework\Exceptions\CoreException;
use App\Framework\Exceptions\FrameworkException;
use App\Modules\Playlists\Helper\Compose\RightsChecker;
use App\Modules\Playlists\Services\AclValidator;
use Phpfastcache\Exceptions\PhpfastcacheSimpleCacheException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\SimpleCache\InvalidArgumentException;

class RightsCheckerTest extends TestCase
{
	private Translator&Stub $translatorMock;
	private AclValidator&Stub $aclValidatorMock;
	private Config&Stub $configMock;
	private RightsChecker $checker;


	/**
	 * @throws Exception
	 */
	protected function setUp(): void
	{
		parent::setUp();
		$this->translatorMock   = static::createStub(Translator::class);
		$this->aclValidatorMock = static::createStub(AclValidator::class);
		$this->configMock       = static::createStub(Config::class);
		$this->aclValidatorMock->method('getConfig')->willReturn($this->configMock);

	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCheckInsertExternalMediaEdge(): void
	{
		$this->configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);
		$this->checker = new RightsChecker($this->translatorMock, $this->aclValidatorMock);

		$result = $this->checker->checkInsertExternalMedia();

		static::assertSame([], $result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCheckInsertExternalMediaCore(): void
	{
		$this->configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_CORE);
		$this->checker = new RightsChecker($this->translatorMock, $this->aclValidatorMock);

		$this->translatorMock->method('translate')->willReturn('Translated Message');

		$result = $this->checker->checkInsertExternalMedia();

		static::assertSame(['LANG_INSERT_EXTERNAL_MEDIA' => 'Translated Message'], $result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCheckInsertPlaylistWithTimeLimit(): void
	{
		$this->checker = new RightsChecker($this->translatorMock, $this->aclValidatorMock);

		$result = $this->checker->checkInsertPlaylist(5);

		static::assertSame([], $result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCheckInsertPlaylistNoTimeLimit(): void
	{
		$this->checker = new RightsChecker($this->translatorMock, $this->aclValidatorMock);

		$this->translatorMock->method('translate')->willReturn('Translated Playlist Message');

		$result = $this->checker->checkInsertPlaylist(0);

		static::assertSame(['LANG_INSERT_PLAYLISTS' => 'Translated Playlist Message'], $result);
	}

	/**
	 * @return void
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testCheckInsertExternalPlaylistEdgeWithTimeLimit(): void
	{
		$this->configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);
		$this->checker = new RightsChecker($this->translatorMock, $this->aclValidatorMock);

		$result = $this->checker->checkInsertExternalPlaylist(10);

		static::assertSame([], $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testCheckInsertExternalPlaylistCoreWithTimeLimit(): void
	{
		$this->configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_CORE);
		$this->checker = new RightsChecker($this->translatorMock, $this->aclValidatorMock);

		$result = $this->checker->checkInsertExternalPlaylist(10);

		static::assertSame([], $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testCheckInsertExternalPlaylistCoreWithoutTimeLimit(): void
	{
		$this->configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_CORE);
		$this->checker = new RightsChecker($this->translatorMock, $this->aclValidatorMock);

		$this->translatorMock->method('translate')->willReturn('Translated External Playlist Message');

		$result = $this->checker->checkInsertExternalPlaylist(0);

		static::assertSame(['LANG_INSERT_EXTERNAL_PLAYLISTS' => 'Translated External Playlist Message'], $result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCheckInsertTemplatesEdgeEdition(): void
	{
		$this->configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);
		$this->checker = new RightsChecker($this->translatorMock, $this->aclValidatorMock);

		$result = $this->checker->checkInsertTemplates();

		$expected = ['LANG_INSERT_TEMPLATES' => ''];

		static::assertSame($expected, $result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCheckInsertTemplatesCoreEdition(): void
	{
		$this->configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_CORE);
		$this->checker = new RightsChecker($this->translatorMock, $this->aclValidatorMock);

		$this->translatorMock->method('translate')->willReturn('Translated Template Message');

		$result = $this->checker->checkInsertTemplates();

		static::assertSame(['LANG_INSERT_TEMPLATES' => 'Translated Template Message'], $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testCheckInsertChannelsEdgeEdition(): void
	{
		$this->configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_EDGE);
		$this->checker = new RightsChecker($this->translatorMock, $this->aclValidatorMock);

		$result = $this->checker->checkInsertChannels();

		static::assertSame([], $result);
	}

	/**
	 * @throws CoreException
	 * @throws FrameworkException
	 * @throws InvalidArgumentException
	 * @throws PhpfastcacheSimpleCacheException
	 */
	#[Group('units')]
	public function testCheckInsertChannelsCoreEdition(): void
	{
		$this->configMock->method('getEdition')->willReturn(Config::PLATFORM_EDITION_CORE);
		$this->checker = new RightsChecker($this->translatorMock, $this->aclValidatorMock);

		$this->translatorMock->method('translate')->willReturn('Translated Channels Message');

		$result = $this->checker->checkInsertChannels();

		static::assertSame(['LANG_INSERT_CHANNELS' => 'Translated Channels Message'], $result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCheckTimeLimitWithZero(): void
	{
		$this->checker = new RightsChecker($this->translatorMock, $this->aclValidatorMock);

		$result = $this->checker->checkTimeLimit(0);

		static::assertSame([], $result);
	}

	/**
	 * @throws CoreException
	 * @throws PhpfastcacheSimpleCacheException
	 * @throws InvalidArgumentException
	 * @throws FrameworkException
	 */
	#[Group('units')]
	public function testCheckTimeLimitWithNonZero(): void
	{
		$this->checker = new RightsChecker($this->translatorMock, $this->aclValidatorMock);

		$this->translatorMock->method('translate')->willReturn('Translated Remain Duration Message');

		$result = $this->checker->checkTimeLimit(10);

		static::assertSame(['LANG_PLAYLIST_REMAIN_DURATION' => 'Translated Remain Duration Message'], $result);
	}
}
