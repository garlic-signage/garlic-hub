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
use App\Modules\Player\Enums\PlayerModel;
use App\Modules\Player\Enums\PlayerStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;

/**
 * What a player gets on its request for the index, by its status. The answer is the path of the file.
 */
#[Group('acl')]
#[Group('io')]
class PlayerIndexServiceTest extends PlayerIndexTestCase
{
	public function testAnAgentWhichIsNoPlayerGetsTheForbiddenFileAndIsNotRegistered(): void
	{
		$path = $this->service()->handleIndexRequest(2, 'Mozilla/5.0 (X11; Linux x86_64) Firefox/120.0', [], false);

		static::assertSame('/public/smil/defaults/forbidden.smil', $this->relative($path));
		static::assertFileExists($path);
		static::assertSame(0, $this->countPlayers());
	}

	public function testANewPlayerIsRegisteredForItsOwnerAndGetsTheUnreleasedFile(): void
	{
		$path = $this->service()->handleIndexRequest(2, self::AGENT, [], false);

		static::assertSame('/public/smil/defaults/unreleased.smil', $this->relative($path));
		$row = $this->playerRow();
		static::assertSame(2, (int) $row['UID']);
		static::assertSame('Eingang', $row['player_name']);
		static::assertSame(PlayerModel::GARLIC->value, (int) $row['model']);
		static::assertSame('garlic-macOS/v0.6.0.679', $row['firmware']);
		static::assertSame(0, (int) $row['playlist_id']);
		static::assertSame(300, (int) $row['refresh']);
	}

	/**
	 * @return array<string,array{string,int,int}> the edition, the status and the licence of the new player
	 */
	public static function editions(): array
	{
		return [
			'edge: free of charge, released at once' => [Config::PLATFORM_EDITION_EDGE, PlayerStatus::RELEASED->value, 1],
			'enterprise: the admin releases it'      => [Config::PLATFORM_EDITION_ENTERPRISE, PlayerStatus::UNRELEASED->value, 0],
		];
	}

	#[DataProvider('editions')]
	public function testTheEditionDecidesAboutTheStatusOfANewPlayer(string $edition, int $status, int $licence): void
	{
		$this->service($edition)->handleIndexRequest(2, self::AGENT, [], false);

		static::assertSame($status, (int) $this->playerRow()['status']);
		static::assertSame($licence, (int) $this->playerRow()['licence_id']);
	}

	public function testTheSecondRequestOfANewPlayerDoesNotRegisterItAgain(): void
	{
		$this->service()->handleIndexRequest(2, self::AGENT, [], false);
		$this->service()->handleIndexRequest(2, self::AGENT, [], false);
		$this->service()->handleIndexRequest(3, self::AGENT, [], false);

		static::assertSame(1, $this->countPlayers());
		static::assertSame(2, (int) $this->playerRow()['UID'], 'The owner is the one of the first request.');
	}

	public function testAPlayerWhichIsNotReleasedGetsTheUnreleasedFile(): void
	{
		$this->givePlayer(PlayerStatus::UNRELEASED->value);

		$path = $this->service()->handleIndexRequest(2, self::AGENT, [], false);

		static::assertSame('/public/smil/defaults/unreleased.smil', $this->relative($path));
	}

	public function testAReleasedPlayerWithoutAPlaylistGetsTheReleasedFile(): void
	{
		$this->givePlayer(PlayerStatus::RELEASED->value);

		$path = $this->service()->handleIndexRequest(2, self::AGENT, [], false);

		static::assertSame('/public/smil/defaults/released.smil', $this->relative($path));
	}

	public function testTheLastAccessOfAKnownPlayerIsUpdated(): void
	{
		$this->givePlayer(PlayerStatus::RELEASED->value);

		$this->service()->handleIndexRequest(2, self::AGENT, [], false);

		static::assertGreaterThan('2000-01-01 00:00:00', $this->playerRow()['last_access']);
	}

	public function testAReleasedPlayerGetsTheIndexOfItsPlaylistInItsOwnDirectory(): void
	{
		$this->givePlayer(PlayerStatus::RELEASED->value, 1);

		$path = $this->service()->handleIndexRequest(2, self::AGENT, [], false);

		static::assertSame('/var/smil/indexes/' . self::UUID . '/1.smil', $this->relative($path));
		$smil = (string) file_get_contents($path);
		static::assertStringContainsString('<smil', $smil);
		static::assertStringContainsString('Eingang - Annas Lobby', $smil);
		static::assertStringNotContainsString('{{', $smil);
	}

	public function testTheIndexIsNotWrittenAgainWhenNothingChanged(): void
	{
		$this->givePlayer(PlayerStatus::RELEASED->value, 1);
		$path = $this->service()->handleIndexRequest(2, self::AGENT, [], false);
		touch($path, 1_750_000_000);
		clearstatcache();

		$this->service()->handleIndexRequest(2, self::AGENT, [], false);
		clearstatcache();

		static::assertSame(1_750_000_000, filemtime($path));
	}

	public function testTheIndexIsWrittenAgainWhenThePlaylistChanged(): void
	{
		$this->givePlayer(PlayerStatus::RELEASED->value, 1);
		$path   = $this->service()->handleIndexRequest(2, self::AGENT, [], false);
		$before = file_get_contents($path);

		$this->connection->executeStatement("UPDATE playlists SET playlist_name = 'Neuer Name' WHERE playlist_id = 1");
		$this->service()->handleIndexRequest(2, self::AGENT, [], false);

		static::assertNotSame($before, file_get_contents($path));
		static::assertStringContainsString('Eingang - Neuer Name', (string) file_get_contents($path));
	}

	/**
	 * @return array<string,array{int,string}> the status and the file which a player with this status gets
	 */
	public static function statusFiles(): array
	{
		return [
			'debug ftp'            => [PlayerStatus::DEBUG_FTP->value, '/public/smil/tests/index.smil'],
			'smil is ok'           => [PlayerStatus::TEST_SMIL_OK->value, '/public/smil/simulations/without_errors.smil'],
			'smil with an error'   => [PlayerStatus::TEST_SMIL_ERROR->value, '/public/smil/simulations/broken_index.smil'],
			'no content'           => [PlayerStatus::TEST_NO_CONTENT->value, '/public/smil/simulations/unreachable_content.smil'],
			'no prefetch content'  => [PlayerStatus::TEST_NO_PREFETCH->value, '/public/smil/simulations/unreachable_prefetch_content.smil'],
		];
	}

	#[DataProvider('statusFiles')]
	public function testAPlayerInATestStatusGetsItsTestFile(int $status, string $file): void
	{
		$this->givePlayer($status);

		$path = $this->service()->handleIndexRequest(2, self::AGENT, [], false);

		static::assertSame($file, $this->relative($path));
		static::assertFileExists($path);
	}

	/**
	 * @return array<string,array{int}>
	 */
	public static function statusesWithoutAFile(): array
	{
		return ['not used 6' => [6], 'not used 7' => [7], 'unknown' => [99], 'negative' => [-1]];
	}

	/**
	 * A status the application does not know is no reason for an error page for the player: it gets nothing.
	 */
	#[DataProvider('statusesWithoutAFile')]
	public function testAStatusWithoutAFileGivesNoFileAndAnEntryInTheLog(int $status): void
	{
		$this->givePlayer($status);

		static::assertSame('', $this->service()->handleIndexRequest(2, self::AGENT, [], false));
		static::assertTrue($this->logs->hasErrorThatContains('Unknown player status'));
	}

	public function testTheLocalPlayerIsRegisteredWithTheIdOneAndTheLocalApi(): void
	{
		$path = $this->service()->handleIndexRequest(2, self::AGENT, [], true);

		$row = $this->playerRow();
		static::assertSame(1, (int) $row['player_id']);
		static::assertSame(PlayerStatus::RELEASED->value, (int) $row['status']);
		static::assertSame('http://localhost:8080/v2', $row['api_endpoint']);
		static::assertSame(1, (int) $row['is_intranet']);
		static::assertSame(1, (int) $row['licence_id']);
		static::assertSame('/public/smil/defaults/released.smil', $this->relative($path));
	}

	public function testTheLocalPlayerInDockerUsesTheHostOfDocker(): void
	{
		$this->service(Config::PLATFORM_EDITION_EDGE, Config::APP_CONTAINER_DOCKER)->handleIndexRequest(2, self::AGENT, [], true);

		static::assertSame('http://host.docker.internal:8080/v2', $this->playerRow()['api_endpoint']);
	}

	public function testTheLocalPlayerKeepsItsUuid(): void
	{
		$this->service()->handleIndexRequest(2, self::AGENT, [], true);

		$other = 'GAPI/1.0 (UUID:15920d5d-7e68-4a61-a145-15b58b6d2090; NAME:Other) garlic/v1 (MODEL:Garlic)';

		static::assertSame('', $this->service()->handleIndexRequest(2, $other, [], true));
		static::assertTrue($this->logs->hasErrorThatContains('Wrong Uuid for local player'));
		static::assertSame(1, $this->countPlayers());
		static::assertSame(self::UUID, $this->playerRow()['uuid']);
	}

	public function testTheRequestOfAKnownLocalPlayerUpdatesItsLastAccess(): void
	{
		$this->service()->handleIndexRequest(2, self::AGENT, [], true);
		$this->connection->executeStatement("UPDATE player SET last_access = '2000-01-01 00:00:00' WHERE player_id = 1");

		$this->service()->handleIndexRequest(2, self::AGENT, [], true);

		static::assertGreaterThan('2000-01-01 00:00:00', $this->playerRow()['last_access']);
	}
}
