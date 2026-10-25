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

namespace Tests\Integration\Modules\Player\Services;

use App\Framework\Localization\Translate\Translator;
use App\Modules\Player\Dashboard\PlayerDashboard;
use PHPUnit\Framework\Attributes\Group;
use Tests\Integration\Support\ContainerTestCase;

/**
 * The dashboard of the players on the start page: the numbers of the players by their last access.
 */
#[Group('acl')]
class PlayerDashboardTest extends ContainerTestCase
{
	private PlayerDashboard $dashboard;

	protected function setUp(): void
	{
		parent::setUp();
		$this->loadFixture('acl/users.sql');
		$this->loadFixture('acl/players.sql');
		$this->dashboard = $this->container->get(PlayerDashboard::class);
	}

	private function lastAccessSecondsAgo(int $playerId, int $seconds): void
	{
		$this->connection->executeStatement('UPDATE player SET refresh = 300, last_access = ? WHERE player_id = ?', [gmdate('Y-m-d H:i:s', time() - $seconds), $playerId]);
	}

	/**
	 * @return array<string,int> the numbers by the name of the line
	 */
	private function numbers(): array
	{
		$translator = $this->container->get(Translator::class);
		$html       = $this->dashboard->renderContent();
		$numbers    = [];
		foreach (['count_active', 'count_pending', 'count_inactive'] as $key)
		{
			static::assertSame(1, preg_match('#<strong>' . preg_quote($translator->translate($key, 'player'), '#') . ':</strong><span>([^<]*)</span>#', $html, $match), $key);
			$numbers[$key] = $match[1] === '' ? -1 : (int) $match[1];
		}

		return $numbers;
	}

	public function testTheDashboardHasItsIdAndTitle(): void
	{
		static::assertSame('player', $this->dashboard->getId());
		static::assertSame($this->container->get(Translator::class)->translate('dashboard', 'player'), $this->dashboard->getTitle());
	}

	public function testThePlayersAreCountedByTheirLastAccess(): void
	{
		$this->lastAccessSecondsAgo(1, 10);
		$this->lastAccessSecondsAgo(2, 700);
		$this->lastAccessSecondsAgo(3, 5000);
		$this->lastAccessSecondsAgo(4, 5000);

		static::assertSame(['count_active' => 1, 'count_pending' => 1, 'count_inactive' => 2], $this->numbers());
	}

	/**
	 * The sums of a table without players are NULL in the database: the dashboard shows 0 and not an empty field.
	 */
	public function testWithoutPlayersTheDashboardShowsZeros(): void
	{
		$this->connection->executeStatement('DELETE FROM player');

		static::assertSame(['count_active' => 0, 'count_pending' => 0, 'count_inactive' => 0], $this->numbers());
	}

	public function testTheContentIsAListWithThreeLines(): void
	{
		$html = $this->dashboard->renderContent();

		static::assertStringStartsWith('<ul>', $html);
		static::assertSame(3, substr_count($html, '<li>'));
		static::assertStringEndsWith('</ul>', $html);
	}
}
