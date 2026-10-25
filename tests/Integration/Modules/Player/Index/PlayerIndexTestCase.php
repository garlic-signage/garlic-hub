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

namespace Tests\Integration\Modules\Player\Index;

use App\Framework\Config\Config;
use App\Framework\Config\IniConfigLoader;
use App\Framework\TemplateEngine\AdapterInterface;
use App\Modules\Player\Entities\PlayerEntityFactory;
use App\Modules\Player\IndexCreation\Builder\Preparers\PreparerFactory;
use App\Modules\Player\IndexCreation\Builder\TemplatePreparer;
use App\Modules\Player\IndexCreation\IndexCreator;
use App\Modules\Player\IndexCreation\IndexFile;
use App\Modules\Player\IndexCreation\IndexProvider;
use App\Modules\Player\IndexCreation\IndexTemplateSelector;
use App\Modules\Player\IndexCreation\PlayerDataAssembler;
use App\Modules\Player\IndexCreation\PlayerDetector;
use App\Modules\Player\IndexCreation\UserAgentHandler;
use App\Modules\Player\Repositories\PlayerIndexRepository;
use App\Modules\Player\Services\PlayerIndexService;
use App\Modules\Playlists\Collector\Builder\PlaylistBuilderFactory;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The index of a player the way the application builds it: the real database, the real playlist builders and
 * templates, and a storage in a temporary directory with the SMIL files of the defaults.
 * Users: 1 admin, 2 anna, 3 bernd. Playlists of playlists/playlists.sql with the items of playlists/items.sql.
 */
abstract class PlayerIndexTestCase extends ContainerTestCase
{
	protected const string UUID    = '3f0cd56c-d511-486a-a8e1-9d2cefd78b3f';
	protected const string AGENT   = 'GAPI/1.0 (UUID:3f0cd56c-d511-486a-a8e1-9d2cefd78b3f; NAME:Eingang) garlic-macOS/v0.6.0.679 (MODEL:Garlic)';

	protected string $directory;
	protected TestHandler $logs;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('playlists/playlists.sql');
		$this->loadFixture('playlists/items.sql');
		$this->loadFixture('playlists/mediapool_files.sql');
		$this->directory = sys_get_temp_dir() . '/garlic-hub-playerindex-' . bin2hex(random_bytes(4));
		mkdir($this->directory . '/var/smil/indexes', 0775, true);
		$this->copyDirectory(dirname(__DIR__, 5) . '/public/smil', $this->directory . '/public/smil');
		$this->logs = new TestHandler();
	}

	protected function tearDown(): void
	{
		$this->removeDirectory($this->directory);
		parent::tearDown();
	}

	protected function config(string $edition = Config::PLATFORM_EDITION_EDGE, string $container = ''): Config
	{
		return new Config(
			new IniConfigLoader(dirname(__DIR__, 5) . '/config/settings'),
			['systemDir' => $this->directory],
			['APP_PLATFORM_EDITION' => $edition, 'APP_CONTAINER' => $container]
		);
	}

	protected function service(string $edition = Config::PLATFORM_EDITION_EDGE, string $container = ''): PlayerIndexService
	{
		$config  = $this->config($edition, $container);
		$logger  = new Logger('test', [$this->logs]);
		$creator = new IndexCreator(
			$this->container->get(PlaylistBuilderFactory::class),
			new IndexTemplateSelector(),
			new IndexFile(new Filesystem(new LocalFilesystemAdapter($this->directory)), $logger),
			new TemplatePreparer(new PreparerFactory()),
			$this->container->get(AdapterInterface::class)
		);

		return new PlayerIndexService(
			new PlayerDataAssembler(new UserAgentHandler(new PlayerDetector($config)), new PlayerIndexRepository($this->connection), $config, new PlayerEntityFactory($config)),
			new IndexProvider($config, $creator),
			$logger
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	protected function playerRow(string $uuid = self::UUID): array
	{
		/** @var array<string,mixed>|false $row */
		$row = $this->connection->fetchAssociative('SELECT * FROM player WHERE uuid = ?', [$uuid]);

		return $row === false ? [] : $row;
	}

	protected function countPlayers(): int
	{
		return (int) $this->connection->fetchOne('SELECT COUNT(*) FROM player');
	}

	/**
	 * A player which is known: its row in the database.
	 */
	protected function givePlayer(int $status, int $playlistId = 0, string $uuid = self::UUID, int $id = 5): void
	{
		$this->connection->executeStatement(
			"INSERT INTO player (player_id, playlist_id, UID, status, uuid, player_name, licence_id, last_access) VALUES (?, ?, 2, ?, ?, 'Eingang', 1, '2000-01-01 00:00:00')",
			[$id, $playlistId, $status, $uuid]
		);
	}

	protected function relative(string $path): string
	{
		return str_starts_with($path, $this->directory) ? substr($path, strlen($this->directory)) : $path;
	}

	private function copyDirectory(string $from, string $to): void
	{
		mkdir($to, 0775, true);
		foreach (array_diff((array) scandir($from), ['.', '..']) as $entry)
			is_dir($from . '/' . $entry) ? $this->copyDirectory($from . '/' . $entry, $to . '/' . $entry) : copy($from . '/' . $entry, $to . '/' . $entry);
	}

	private function removeDirectory(string $dir): void
	{
		if (!is_dir($dir))
			return;

		foreach (array_diff((array) scandir($dir), ['.', '..']) as $entry)
			is_dir($dir . '/' . $entry) ? $this->removeDirectory($dir . '/' . $entry) : unlink($dir . '/' . $entry);
		rmdir($dir);
	}
}
